<?php
declare(strict_types=1);

class FinanceiroController
{
    private PDO $pdo;
    private Financeiro $financeiroModel;
    private Fornecedor $fornecedorModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo             = $pdo;
        $this->financeiroModel = new Financeiro($pdo);
        $this->fornecedorModel = new Fornecedor($pdo);
    }

    /**
     * Carrega todos os indicadores setoriais, gráficos e lançamentos ordenados por vencimento.
     */
    public function index(): void
    {
        AuthController::verificarPermissao(['admin', 'financeiro']);

        $filtros = [
            'data_inicio'          => trim((string) ($_GET['data_inicio'] ?? '')),
            'data_fim'             => trim((string) ($_GET['data_fim'] ?? '')),
            'tipo'                 => trim((string) ($_GET['tipo'] ?? '')),
            'categoria'            => trim((string) ($_GET['categoria'] ?? '')),
            'status'               => trim((string) ($_GET['status'] ?? '')),
            'fornecedor_id'        => !empty($_GET['fornecedor_id']) ? (int) $_GET['fornecedor_id'] : null,
            'somente_fornecedores' => ($_GET['somente_fornecedores'] ?? '') === '1',
        ];

        $resumo               = $this->financeiroModel->obterResumo($filtros);
        $despesasPorCategoria = $this->financeiroModel->relatorioDespesasPorCategoria($filtros);
        $resumoFornecedores   = $this->financeiroModel->resumoContasPagarPorFornecedor($filtros);
        $movimentacoes        = $this->financeiroModel->listarMovimentacoes($filtros);
        $cuponsPdv            = $this->financeiroModel->listarCuponsPdv($filtros, 100);
        $categoriasFiltro     = $this->financeiroModel->listarCategoriasExistentes();
        $fornecedores         = $this->fornecedorModel->listarAtivos();

        // Indicadores Setoriais extras para os cards do topo
        $indicadoresEstoque = $this->obterIndicadoresEstoqueCompras();
        $avisosVencimentos  = $this->obterAvisosVencimentos();
        $serieFluxoDiario   = $this->agruparFluxoPorData($movimentacoes);

        require BASE_PATH . '/views/financeiro/index.php';
    }

    /**
     * Salva um novo lançamento (receita ou despesa/boleto) via POST.
     */
    public function salvar(): void
    {
        AuthController::verificarPermissao(['admin', 'financeiro']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=financeiro');
            exit;
        }

        $valorRaw = trim((string) ($_POST['valor'] ?? '0'));
        if (str_contains($valorRaw, ',')) {
            $valorRaw = str_replace('.', '', $valorRaw);
            $valorRaw = str_replace(',', '.', $valorRaw);
        }

        $dados = [
            'tipo'            => $_POST['tipo'] ?? '',
            'categoria'       => $_POST['categoria'] ?? '',
            'descricao'       => $_POST['descricao'] ?? '',
            'valor'           => (float) $valorRaw,
            'data_vencimento' => $_POST['data_vencimento'] ?? '',
            'status'          => $_POST['status'] ?? 'pendente',
            'fornecedor_id'   => !empty($_POST['fornecedor_id']) ? (int) $_POST['fornecedor_id'] : null,
        ];

        try {
            $this->financeiroModel->criarLancamento($dados);
            $_SESSION['flash_sucesso'] = 'Lançamento financeiro registrado com sucesso!';
        } catch (Throwable $e) {
            $_SESSION['flash_erro'] = $e->getMessage();
        }

        header('Location: index.php?rota=financeiro');
        exit;
    }

    /**
     * Marca um lançamento pendente como 'pago' via POST.
     */
    public function baixar(): void
    {
        AuthController::verificarPermissao(['admin', 'financeiro']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=financeiro');
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);

        try {
            $atualizado = $this->financeiroModel->marcarComoPago($id);
            if ($atualizado) {
                $_SESSION['flash_sucesso'] = 'Baixa realizada com sucesso! Lançamento marcado como PAGO.';
            } else {
                $_SESSION['flash_erro'] = 'Lançamento não encontrado para baixa.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_erro'] = $e->getMessage();
        }

        header('Location: index.php?rota=financeiro');
        exit;
    }

    /**
     * Consulta indicadores de Estoque e Pedidos Aguardando Entrega para o Card 1.
     */
    private function obterIndicadoresEstoqueCompras(): array
    {
        $dados = [
            'produtos_ativos'     => 0,
            'estoque_critico'     => 0,
            'pedidos_aguardando'  => 0,
        ];

        try {
            $stmtProd = $this->pdo->prepare(
                "SELECT
                    COUNT(*) AS produtos_ativos,
                    COUNT(*) FILTER (WHERE estoque_atual <= estoque_minimo) AS estoque_critico
                 FROM produtos"
            );
            $stmtProd->execute();
            $resProd = $stmtProd->fetch(PDO::FETCH_ASSOC);
            if ($resProd !== false) {
                $dados['produtos_ativos'] = (int) $resProd['produtos_ativos'];
                $dados['estoque_critico'] = (int) $resProd['estoque_critico'];
            }

            $stmtComp = $this->pdo->prepare(
                "SELECT COUNT(*) AS pedidos_aguardando
                 FROM compras
                 WHERE status = 'aguardando_entrega'"
            );
            $stmtComp->execute();
            $dados['pedidos_aguardando'] = (int) $stmtComp->fetchColumn();
        } catch (PDOException $ignored) {
        }

        return $dados;
    }

    /**
     * Consulta despesas/boletos pendentes vencendo hoje, nos próximos 7 dias ou já atrasados.
     */
    private function obterAvisosVencimentos(): array
    {
        $avisos = [
            'qtd_proximos_7d'   => 0,
            'valor_proximos_7d' => 0.0,
            'qtd_atrasados'     => 0,
            'valor_atrasados'   => 0.0,
        ];

        try {
            $sql = "SELECT
                        COUNT(*) FILTER (WHERE data_vencimento BETWEEN CURRENT_DATE AND (CURRENT_DATE + INTERVAL '7 days')) AS qtd_7d,
                        COALESCE(SUM(valor) FILTER (WHERE data_vencimento BETWEEN CURRENT_DATE AND (CURRENT_DATE + INTERVAL '7 days')), 0) AS valor_7d,
                        COUNT(*) FILTER (WHERE data_vencimento < CURRENT_DATE) AS qtd_atrasados,
                        COALESCE(SUM(valor) FILTER (WHERE data_vencimento < CURRENT_DATE), 0) AS valor_atrasados
                    FROM financeiro
                    WHERE tipo = 'despesa'
                      AND status = 'pendente'";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row !== false) {
                $avisos['qtd_proximos_7d']   = (int) $row['qtd_7d'];
                $avisos['valor_proximos_7d'] = (float) $row['valor_7d'];
                $avisos['qtd_atrasados']     = (int) $row['qtd_atrasados'];
                $avisos['valor_atrasados']   = (float) $row['valor_atrasados'];
            }
        } catch (PDOException $ignored) {
        }

        return $avisos;
    }

    /**
     * Agrupa as movimentações filtradas por data para o gráfico Entradas vs Saídas.
     *
     * @return array<int, array{data: string, label: string, receitas: float, despesas: float}>
     */
    private function agruparFluxoPorData(array $movimentacoes): array
    {
        $agrupado = [];

        foreach ($movimentacoes as $mov) {
            $dataIso = (string) ($mov['data_vencimento'] ?? '');
            if ($dataIso === '') {
                continue;
            }

            if (!isset($agrupado[$dataIso])) {
                $agrupado[$dataIso] = [
                    'data'     => $dataIso,
                    'label'    => date('d/m', strtotime($dataIso)),
                    'receitas' => 0.0,
                    'despesas' => 0.0,
                ];
            }

            $valor = (float) ($mov['valor'] ?? 0);
            if (($mov['tipo'] ?? '') === 'receita') {
                $agrupado[$dataIso]['receitas'] += $valor;
            } else {
                $agrupado[$dataIso]['despesas'] += $valor;
            }
        }

        ksort($agrupado);
        return array_values($agrupado);
    }
}