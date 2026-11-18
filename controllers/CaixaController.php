<?php
declare(strict_types=1);

class CaixaController
{
    private Venda $vendaModel;

    public function __construct(PDO $pdo)
    {
        $this->vendaModel = new Venda($pdo);
    }

    /**
     * Exibe o PDV (ou a tela de Abertura de Caixa caso o operador não tenha turno aberto).
     */
    public function index(): void
    {
        AuthController::verificarPermissao(['admin', 'caixa']);

        $operadorId     = (int) ($_SESSION['usuario']['id'] ?? 0);
        $turnoAberto    = $this->vendaModel->obterTurnoAberto($operadorId);
        $terminaisEmUso = $this->vendaModel->listarTerminaisEmUso();
        $terminaisLista = Venda::TERMINAIS_DISPONIVEIS;
        $produtos       = $this->vendaModel->listarProdutosPdv();

        $cuponsTurno   = [];
        $sangriasTurno = [];
        if ($turnoAberto !== null) {
            $turnoId       = (int) $turnoAberto['id'];
            $cuponsTurno   = $this->vendaModel->listarVendasDoTurno($turnoId);
            $sangriasTurno = $this->vendaModel->listarSangriasDoTurno($turnoId);
        }

        require BASE_PATH . '/views/caixa/index.php';
    }

    /**
     * Realiza a Abertura de Caixa selecionando o terminal e o fundo de troco.
     */
    public function abrir(): void
    {
        AuthController::verificarPermissao(['admin', 'caixa']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=caixa');
            exit;
        }

        $operadorId    = (int) ($_SESSION['usuario']['id'] ?? 0);
        $numeroCaixa   = trim((string) ($_POST['numero_caixa'] ?? ''));
        $valorAbertura = $this->normalizarDecimal($_POST['valor_abertura'] ?? '0');

        try {
            $turnoId = $this->vendaModel->abrirCaixa($operadorId, $numeroCaixa, $valorAbertura);
            $_SESSION['flash_sucesso'] = "{$numeroCaixa} aberto com sucesso (Turno #{$turnoId})!";
        } catch (Throwable $e) {
            $_SESSION['flash_erro'] = $e->getMessage();
        }

        header('Location: index.php?rota=caixa');
        exit;
    }

    /**
     * Finaliza a venda via Fetch/AJAX (JSON) somando no turno aberto.
     */
    public function finalizar(): void
    {
        AuthController::verificarPermissao(['admin', 'caixa']);

        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
            exit;
        }

        $corpoBruto = file_get_contents('php://input');
        $payload    = json_decode($corpoBruto !== false ? $corpoBruto : '', true);

        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Payload JSON inválido.']);
            exit;
        }

        $operadorId     = (int) ($_SESSION['usuario']['id'] ?? 0);
        $formaPagamento = (string) ($payload['forma_pagamento'] ?? '');
        $itens          = is_array($payload['itens'] ?? null) ? $payload['itens'] : [];

        try {
            $resultado = $this->vendaModel->finalizarVenda($operadorId, $formaPagamento, $itens);

            echo json_encode([
                'sucesso'            => true,
                'venda_id'           => $resultado['venda_id'],
                'turno_id'           => $resultado['turno_id'],
                'valor_total'        => $resultado['valor_total'],
                'total_vendas_turno' => $resultado['total_vendas_turno'],
                'forma_pagamento'    => $formaPagamento,
                'hora'               => date('H:i:s'),
                'mensagem'           => "Cupom #{$resultado['venda_id']} registrado com sucesso!",
            ]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'sucesso'  => false,
                'mensagem' => 'Erro interno ao processar a venda. Nenhuma alteração foi salva.',
            ]);
        }

        exit;
    }

    /**
     * Registra sangria no turno aberto e envia ao financeiro.
     */
    public function sangria(): void
    {
        AuthController::verificarPermissao(['admin', 'caixa']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=caixa');
            exit;
        }

        $operadorId = (int) ($_SESSION['usuario']['id'] ?? 0);
        $turnoId    = (int) ($_POST['turno_id'] ?? 0);
        $valor      = $this->normalizarDecimal($_POST['valor'] ?? '0');
        $motivo     = trim((string) ($_POST['motivo'] ?? ''));

        try {
            $res = $this->vendaModel->registrarSangria($turnoId, $operadorId, $valor, $motivo);
            $valorFmt = number_format($valor, 2, ',', '.');
            $_SESSION['flash_sucesso'] = "Sangria de R$ {$valorFmt} registrada no {$res['numero_caixa']} e enviada ao Financeiro!";
        } catch (Throwable $e) {
            $_SESSION['flash_erro'] = $e->getMessage();
        }

        header('Location: index.php?rota=caixa');
        exit;
    }

    /**
     * Encerra o turno e lança receita consolidada no Financeiro.
     */
    public function fechar(): void
    {
        AuthController::verificarPermissao(['admin', 'caixa']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=caixa');
            exit;
        }

        $operadorId = (int) ($_SESSION['usuario']['id'] ?? 0);
        $turnoId    = (int) ($_POST['turno_id'] ?? 0);

        try {
            $res = $this->vendaModel->fecharCaixa($turnoId, $operadorId);
            $liquidoFmt = number_format($res['saldo_liquido'], 2, ',', '.');
            $_SESSION['flash_sucesso'] = "{$res['numero_caixa']} (Turno #{$res['turno_id']}) fechado com sucesso! Consolidado enviado ao Financeiro: R$ {$liquidoFmt}.";
        } catch (Throwable $e) {
            $_SESSION['flash_erro'] = $e->getMessage();
        }

        header('Location: index.php?rota=caixa');
        exit;
    }

    private function normalizarDecimal(mixed $valor): float
    {
        $texto = trim((string) $valor);
        if (str_contains($texto, ',')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        }
        return (float) $texto;
    }
}