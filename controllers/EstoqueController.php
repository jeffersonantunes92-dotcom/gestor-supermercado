<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Produto.php';

if (file_exists(__DIR__ . '/../models/Fornecedor.php')) {
    require_once __DIR__ . '/../models/Fornecedor.php';
}

class EstoqueController
{
    private Produto $produtoModel;

    public function __construct(PDO $pdo)
    {
        $this->produtoModel = new Produto($pdo);
    }

    public function index(): void
    {
        AuthController::verificarPermissao(['admin', 'estoque']);

        $categorias = $this->produtoModel->listarCategorias();
        $produtos   = $this->produtoModel->listarTodos();

        $giroBruto = method_exists($this->produtoModel, 'giroProdutos')
            ? $this->produtoModel->giroProdutos()
            : [];

        $giroMap = [];
        foreach ($giroBruto as $chave => $item) {
            if (is_array($item) && isset($item['produto_id'])) {
                $giroMap[(int)$item['produto_id']] = $item;
            } else {
                $giroMap[(int)$chave] = $item;
            }
        }

        $fornecedores = [];
        if (class_exists('Fornecedor')) {
            $fornecedorModel = new Fornecedor(Database::getConnection());
            $fornecedores = method_exists($fornecedorModel, 'listarAtivos')
                ? $fornecedorModel->listarAtivos()
                : $fornecedorModel->listarTodos();
        }

        require __DIR__ . '/../views/estoque/index.php';
    }

    public function historico(): void
    {
        AuthController::verificarPermissao(['admin', 'estoque']);

        $produtos = $this->produtoModel->listarTodos();
        $movimentacoes = method_exists($this->produtoModel, 'listarMovimentacoes')
            ? $this->produtoModel->listarMovimentacoes(1000)
            : [];

        require __DIR__ . '/../views/estoque/historico.php';
    }

    public function salvarCategoria(): void
    {
        AuthController::verificarPermissao(['admin', 'estoque']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=estoque');
            exit;
        }

        $nome = trim((string)($_POST['nome'] ?? ''));

        if ($nome === '') {
            $_SESSION['erro'] = 'Informe o nome da categoria.';
            header('Location: index.php?rota=estoque');
            exit;
        }

        try {
            $this->produtoModel->criarCategoria($nome);
            $_SESSION['sucesso'] = 'Categoria cadastrada com sucesso!';
        } catch (Throwable $e) {
            $_SESSION['erro'] = 'Erro ao cadastrar categoria: ' . $e->getMessage();
        }

        header('Location: index.php?rota=estoque');
        exit;
    }

    public function salvarProduto(): void
    {
        AuthController::verificarPermissao(['admin', 'estoque']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=estoque');
            exit;
        }

        $produtoId = (int)($_POST['produto_id'] ?? $_POST['id'] ?? 0);

        $codigoBarras  = trim((string)($_POST['codigo_barras'] ?? ''));
        $nome          = trim((string)($_POST['nome'] ?? ''));
        $categoriaId   = (int)($_POST['categoria_id'] ?? 0);
        $fornecedorRaw = $_POST['fornecedor_id'] ?? '';
        $fornecedorId  = ($fornecedorRaw !== '' && (int)$fornecedorRaw > 0) ? (int)$fornecedorRaw : null;
        $precoCusto    = $this->normalizarDecimal($_POST['preco_custo'] ?? 0);
        $precoVenda    = $this->normalizarDecimal($_POST['preco_venda'] ?? 0);
        $estoqueAtual  = (int)($_POST['estoque_atual'] ?? 0);
        $estoqueMinimo = (int)($_POST['estoque_minimo'] ?? 0);
        $ativo         = isset($_POST['ativo']) ? ((string)$_POST['ativo'] === '1' || (string)$_POST['ativo'] === 'true') : true;

        if ($codigoBarras === '' || $nome === '' || $categoriaId <= 0) {
            $_SESSION['erro'] = 'Preencha código de barras, nome e categoria da mercadoria.';
            header('Location: index.php?rota=estoque');
            exit;
        }

        if ($precoCusto < 0 || $precoVenda <= 0 || $estoqueAtual < 0 || $estoqueMinimo < 0) {
            $_SESSION['erro'] = 'Valores financeiros e quantidades de estoque devem ser válidos.';
            header('Location: index.php?rota=estoque');
            exit;
        }

        $dados = [
            'codigo_barras'  => $codigoBarras,
            'categoria_id'   => $categoriaId,
            'fornecedor_id'  => $fornecedorId,
            'nome'           => $nome,
            'preco_custo'    => $precoCusto,
            'preco_venda'    => $precoVenda,
            'estoque_atual'  => $estoqueAtual,
            'estoque_minimo' => $estoqueMinimo,
            'ativo'          => $ativo,
        ];

        try {
            if ($produtoId > 0 && method_exists($this->produtoModel, 'atualizar')) {
                $this->produtoModel->atualizar($produtoId, $dados);
                $_SESSION['sucesso'] = 'Mercadoria atualizada com sucesso!';
            } else {
                $this->produtoModel->criar($dados);
                $_SESSION['sucesso'] = 'Mercadoria cadastrada com sucesso!';
            }
        } catch (Throwable $e) {
            $_SESSION['erro'] = 'Erro ao salvar mercadoria: ' . $e->getMessage();
        }

        header('Location: index.php?rota=estoque');
        exit;
    }

    public function movimentarEstoque(): void
    {
        AuthController::verificarPermissao(['admin', 'estoque']);

        $origem = (string)($_POST['origem'] ?? 'estoque');
        $rotasPermitidas = ['estoque', 'estoque_historico'];
        $rotaDestino = in_array($origem, $rotasPermitidas, true) ? $origem : 'estoque';

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=' . $rotaDestino);
            exit;
        }

        $produtoId  = (int)($_POST['produto_id'] ?? 0);
        $tipo       = strtolower(trim((string)($_POST['tipo'] ?? '')));
        $quantidade = (int)($_POST['quantidade'] ?? 0);
        $motivo     = trim((string)($_POST['motivo'] ?? ''));
        $usuarioId  = (int)($_SESSION['usuario']['id'] ?? 0);

        if ($produtoId <= 0 || !in_array($tipo, ['entrada', 'saida'], true) || $quantidade <= 0 || $motivo === '') {
            $_SESSION['erro'] = 'Preencha todos os campos da movimentação com uma quantidade válida.';
            header('Location: index.php?rota=' . $rotaDestino);
            exit;
        }

        try {
            $this->produtoModel->registrarMovimentacao($produtoId, $usuarioId, $tipo, $quantidade, $motivo);
            $_SESSION['sucesso'] = 'Movimentação de estoque registrada com sucesso!';
        } catch (Throwable $e) {
            $_SESSION['erro'] = 'Não foi possível registrar a movimentação: ' . $e->getMessage();
        }

        header('Location: index.php?rota=' . $rotaDestino);
        exit;
    }

    private function normalizarDecimal(mixed $valor): float
    {
        $texto = trim((string)$valor);
        if (str_contains($texto, ',')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        }
        return (float)$texto;
    }
}