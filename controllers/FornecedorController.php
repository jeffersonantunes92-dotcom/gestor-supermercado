<?php
declare(strict_types=1);

class FornecedorController
{
    private Fornecedor $fornecedorModel;
    private Produto $produtoModel;

    public function __construct(PDO $pdo)
    {
        $this->fornecedorModel = new Fornecedor($pdo);
        $this->produtoModel    = new Produto($pdo);
    }

    /**
     * Exibe cadastro de fornecedores, boletos e pedidos de compra.
     */
    public function index(): void
    {
        AuthController::verificarPermissao(['admin', 'estoque', 'financeiro']);

        $fornecedores       = $this->fornecedorModel->listarTodosComFinanceiro();
        $fornecedoresAtivos = $this->fornecedorModel->listarAtivos();
        $produtos           = $this->produtoModel->listarTodos();
        $comprasRecentes    = $this->fornecedorModel->listarComprasComItens(25);

        require BASE_PATH . '/views/estoque/fornecedores.php';
    }

    /**
     * Cadastra novo fornecedor ou alterna status (ativo/inativo) via POST.
     */
    public function salvar(): void
    {
        AuthController::verificarPermissao(['admin', 'estoque', 'financeiro']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=fornecedores');
            exit;
        }

        $acao = (string) ($_POST['acao'] ?? 'criar');

        try {
            if ($acao === 'alternar_status') {
                $id         = (int) ($_POST['id'] ?? 0);
                $novoStatus = ((string) ($_POST['ativo'] ?? '0')) === '1';

                $this->fornecedorModel->alternarStatus($id, $novoStatus);
                $statusTexto = $novoStatus ? 'ativado' : 'desativado';
                $_SESSION['flash_sucesso'] = "Fornecedor {$statusTexto} com sucesso!";
            } else {
                $this->fornecedorModel->criar([
                    'nome'     => $_POST['nome'] ?? '',
                    'cnpj'     => $_POST['cnpj'] ?? '',
                    'telefone' => $_POST['telefone'] ?? '',
                    'contato'  => $_POST['contato'] ?? '',
                ]);
                $_SESSION['flash_sucesso'] = 'Fornecedor cadastrado com sucesso!';
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $_SESSION['flash_erro'] = 'Já existe um fornecedor cadastrado com este CNPJ.';
            } else {
                $_SESSION['flash_erro'] = 'Erro ao salvar dados do fornecedor.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_erro'] = $e->getMessage();
        }

        header('Location: index.php?rota=fornecedores');
        exit;
    }

    /**
     * ETAPA 1: Salva Pedido de Compra (status = 'aguardando_entrega'), sem alterar estoque ou financeiro.
     */
    public function comprar(): void
    {
        AuthController::verificarPermissao(['admin', 'estoque', 'financeiro']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=fornecedores');
            exit;
        }

        $fornecedorId      = (int) ($_POST['fornecedor_id'] ?? 0);
        $usuarioId         = (int) ($_SESSION['usuario']['id'] ?? 0);
        $condicaoPagamento = trim((string) ($_POST['condicao_pagamento'] ?? ''));
        $observacao        = trim((string) ($_POST['observacao'] ?? ''));

        $produtosIds = $_POST['produto_id'] ?? [];
        $quantidades = $_POST['quantidade'] ?? [];
        $custos      = $_POST['preco_custo_unitario'] ?? [];

        $itens = [];
        if (is_array($produtosIds) && is_array($quantidades) && is_array($custos)) {
            $totalLinhas = count($produtosIds);
            for ($i = 0; $i < $totalLinhas; $i++) {
                $produtoId  = (int) ($produtosIds[$i] ?? 0);
                $quantidade = (int) ($quantidades[$i] ?? 0);
                $custo      = $this->normalizarDecimal($custos[$i] ?? '0');

                if ($produtoId > 0 && $quantidade > 0) {
                    $itens[] = [
                        'produto_id'           => $produtoId,
                        'quantidade'           => $quantidade,
                        'preco_custo_unitario' => $custo,
                    ];
                }
            }
        }

        try {
            $compraId = $this->fornecedorModel->salvarPedido(
                $fornecedorId,
                $usuarioId,
                $condicaoPagamento,
                $observacao,
                $itens
            );

            $_SESSION['flash_sucesso'] = "Pedido de Compra #{$compraId} criado (Aguardando Entrega). Faça a conferência quando a mercadoria chegar.";
        } catch (Throwable $e) {
            $_SESSION['flash_erro'] = $e->getMessage();
        }

        header('Location: index.php?rota=fornecedores');
        exit;
    }

    /**
     * ETAPA 2: Confere quantidades recebidas, dá entrada no estoque e gera parcelas no financeiro.
     */
    public function receber(): void
    {
        AuthController::verificarPermissao(['admin', 'estoque', 'financeiro']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=fornecedores');
            exit;
        }

        $compraId      = (int) ($_POST['compra_id'] ?? 0);
        $usuarioId     = (int) ($_SESSION['usuario']['id'] ?? 0);
        $prazoParcelas = trim((string) ($_POST['prazo_parcelas'] ?? ''));

        $rawRecebidos = $_POST['quantidade_recebida'] ?? [];
        $quantidadesRecebidas = [];
        if (is_array($rawRecebidos)) {
            foreach ($rawRecebidos as $itemId => $qtd) {
                $quantidadesRecebidas[(int) $itemId] = (int) $qtd;
            }
        }

        try {
            $resultado = $this->fornecedorModel->receberPedido(
                $compraId,
                $usuarioId,
                $prazoParcelas,
                $quantidadesRecebidas
            );

            $valorFmt = number_format($resultado['valor_total'], 2, ',', '.');
            $_SESSION['flash_sucesso'] = "Pedido #{$resultado['compra_id']} conferido e recebido! Estoque atualizado (R$ {$valorFmt}) e {$resultado['total_parcelas']} boleto(s) gerado(s) no Financeiro.";
        } catch (Throwable $e) {
            $_SESSION['flash_erro'] = $e->getMessage();
        }

        header('Location: index.php?rota=fornecedores');
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