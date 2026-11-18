<?php
declare(strict_types=1);

class Produto
{
    private PDO $pdo;

    private const TIPOS_MOVIMENTACAO_VALIDOS = ['entrada', 'saida'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listarCategorias(): array
    {
        $sql = 'SELECT id, nome
                FROM categorias
                ORDER BY nome ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criarCategoria(string $nome): bool
    {
        $nomeLimpo = trim($nome);
        if ($nomeLimpo === '') {
            throw new InvalidArgumentException('O nome da categoria é obrigatório.');
        }

        $sql = 'INSERT INTO categorias (nome) VALUES (:nome)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':nome', $nomeLimpo, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Lista mercadorias com categoria e fornecedor principal.
     */
    public function listarTodos(): array
    {
        $sql = 'SELECT
                    p.id,
                    p.codigo_barras,
                    p.categoria_id,
                    p.fornecedor_id,
                    p.nome,
                    p.preco_custo,
                    p.preco_venda,
                    p.estoque_atual,
                    p.estoque_minimo,
                    c.nome AS categoria_nome,
                    f.nome AS fornecedor_nome
                FROM produtos p
                LEFT JOIN categorias c ON c.id = p.categoria_id
                LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
                ORDER BY p.nome ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inteligência de Giro de Estoque (últimos 30 dias):
     * Calcula total vendido, média diária (total_30d / 30.0) e duração estimada em dias.
     */
    public function giroProdutos(): array
    {
        $sqlBase = "SELECT
                        p.id,
                        p.codigo_barras,
                        p.categoria_id,
                        p.fornecedor_id,
                        p.nome,
                        p.preco_custo,
                        p.preco_venda,
                        p.estoque_atual,
                        p.estoque_minimo,
                        c.nome AS categoria_nome,
                        f.nome AS fornecedor_nome,
                        COALESCE(v30.total_30d, 0) AS total_30d,
                        ROUND(COALESCE(v30.total_30d, 0) / 30.0, 2) AS media_diaria,
                        CASE
                            WHEN (COALESCE(v30.total_30d, 0) / 30.0) > 0
                            THEN ROUND(p.estoque_atual / (COALESCE(v30.total_30d, 0) / 30.0))
                            ELSE NULL
                        END AS duracao_estimada_dias
                    FROM produtos p
                    LEFT JOIN categorias c ON c.id = p.categoria_id
                    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
                    LEFT JOIN (
                        SELECT
                            vi.produto_id,
                            SUM(vi.quantidade) AS total_30d
                        FROM venda_itens vi
                        INNER JOIN vendas v ON v.id = vi.venda_id
                        WHERE v.%s >= (CURRENT_TIMESTAMP - INTERVAL '30 days')
                        GROUP BY vi.produto_id
                    ) v30 ON v30.produto_id = p.id
                    ORDER BY p.nome ASC";

        try {
            $stmt = $this->pdo->prepare(sprintf($sqlBase, 'criado_em'));
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback caso a tabela vendas utilize criado_em
            $stmt = $this->pdo->prepare(sprintf($sqlBase, 'criado_em'));
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    /**
     * Cadastra uma nova mercadoria incluindo fornecedor principal opcional.
     */
    public function criar(array $dados): bool
    {
        $codigoBarras  = trim((string) ($dados['codigo_barras'] ?? ''));
        $categoriaId   = (int) ($dados['categoria_id'] ?? 0);
        $fornecedorId  = !empty($dados['fornecedor_id']) ? (int) $dados['fornecedor_id'] : null;
        $nome          = trim((string) ($dados['nome'] ?? ''));
        $precoCusto    = (float) ($dados['preco_custo'] ?? 0);
        $precoVenda    = (float) ($dados['preco_venda'] ?? 0);
        $estoqueAtual  = (int) ($dados['estoque_atual'] ?? 0);
        $estoqueMinimo = (int) ($dados['estoque_minimo'] ?? 0);

        if ($nome === '' || $categoriaId <= 0) {
            throw new InvalidArgumentException('Nome do produto e categoria são obrigatórios.');
        }

        if ($precoCusto < 0 || $precoVenda < 0 || $estoqueAtual < 0 || $estoqueMinimo < 0) {
            throw new InvalidArgumentException('Preços e quantidades não podem ser negativos.');
        }

        $sql = 'INSERT INTO produtos (
                    codigo_barras,
                    categoria_id,
                    fornecedor_id,
                    nome,
                    preco_custo,
                    preco_venda,
                    estoque_atual,
                    estoque_minimo
                ) VALUES (
                    :codigo_barras,
                    :categoria_id,
                    :fornecedor_id,
                    :nome,
                    :preco_custo,
                    :preco_venda,
                    :estoque_atual,
                    :estoque_minimo
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo_barras', $codigoBarras !== '' ? $codigoBarras : null, $codigoBarras !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':categoria_id', $categoriaId, PDO::PARAM_INT);
        $stmt->bindValue(':fornecedor_id', $fornecedorId, $fornecedorId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':preco_custo', number_format($precoCusto, 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':preco_venda', number_format($precoVenda, 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':estoque_atual', $estoqueAtual, PDO::PARAM_INT);
        $stmt->bindValue(':estoque_minimo', $estoqueMinimo, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Atualiza dados cadastrais de uma mercadoria existente (incluindo fornecedor principal).
     */
    public function atualizar(int $id, array $dados): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Mercadoria inválida.');
        }

        $codigoBarras  = trim((string) ($dados['codigo_barras'] ?? ''));
        $categoriaId   = (int) ($dados['categoria_id'] ?? 0);
        $fornecedorId  = !empty($dados['fornecedor_id']) ? (int) $dados['fornecedor_id'] : null;
        $nome          = trim((string) ($dados['nome'] ?? ''));
        $precoCusto    = (float) ($dados['preco_custo'] ?? 0);
        $precoVenda    = (float) ($dados['preco_venda'] ?? 0);
        $estoqueMinimo = (int) ($dados['estoque_minimo'] ?? 0);

        if ($nome === '' || $categoriaId <= 0) {
            throw new InvalidArgumentException('Nome do produto e categoria são obrigatórios.');
        }

        if ($precoCusto < 0 || $precoVenda < 0 || $estoqueMinimo < 0) {
            throw new InvalidArgumentException('Preços e estoque mínimo não podem ser negativos.');
        }

        $sql = 'UPDATE produtos
                SET codigo_barras  = :codigo_barras,
                    categoria_id   = :categoria_id,
                    fornecedor_id  = :fornecedor_id,
                    nome           = :nome,
                    preco_custo    = :preco_custo,
                    preco_venda    = :preco_venda,
                    estoque_minimo = :estoque_minimo
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo_barras', $codigoBarras !== '' ? $codigoBarras : null, $codigoBarras !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':categoria_id', $categoriaId, PDO::PARAM_INT);
        $stmt->bindValue(':fornecedor_id', $fornecedorId, $fornecedorId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':preco_custo', number_format($precoCusto, 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':preco_venda', number_format($precoVenda, 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':estoque_minimo', $estoqueMinimo, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function registrarMovimentacao(
        int $produtoId,
        int $usuarioId,
        string $tipo,
        int $quantidade,
        string $motivo
    ): bool {
        $tipoNormalizado = strtolower(trim($tipo));
        $motivoLimpo     = trim($motivo);

        if ($produtoId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('Produto ou usuário inválido.');
        }

        if (!in_array($tipoNormalizado, self::TIPOS_MOVIMENTACAO_VALIDOS, true)) {
            throw new InvalidArgumentException('Tipo de movimentação inválido. Use "entrada" ou "saida".');
        }

        if ($quantidade <= 0) {
            throw new InvalidArgumentException('A quantidade movimentada deve ser maior que zero.');
        }

        if ($motivoLimpo === '') {
            throw new InvalidArgumentException('Informe o motivo da movimentação.');
        }

        try {
            $this->pdo->beginTransaction();

            $sqlBusca = 'SELECT id, estoque_atual
                         FROM produtos
                         WHERE id = :id
                         FOR UPDATE';
            $stmtBusca = $this->pdo->prepare($sqlBusca);
            $stmtBusca->bindValue(':id', $produtoId, PDO::PARAM_INT);
            $stmtBusca->execute();

            $produto = $stmtBusca->fetch(PDO::FETCH_ASSOC);
            if ($produto === false) {
                throw new RuntimeException('Mercadoria não encontrada.');
            }

            $estoqueAtual = (int) $produto['estoque_atual'];

            if ($tipoNormalizado === 'saida' && $estoqueAtual < $quantidade) {
                throw new RuntimeException(
                    "Estoque insuficiente para saída. Disponível atualmente: {$estoqueAtual} unidade(s)."
                );
            }

            $novoEstoque = $tipoNormalizado === 'entrada'
                ? $estoqueAtual + $quantidade
                : $estoqueAtual - $quantidade;

            $sqlUpdate = 'UPDATE produtos
                          SET estoque_atual = :novo_estoque
                          WHERE id = :id';
            $stmtUpdate = $this->pdo->prepare($sqlUpdate);
            $stmtUpdate->bindValue(':novo_estoque', $novoEstoque, PDO::PARAM_INT);
            $stmtUpdate->bindValue(':id', $produtoId, PDO::PARAM_INT);
            $stmtUpdate->execute();

            $sqlMov = 'INSERT INTO movimentacoes_estoque (
                           produto_id,
                           usuario_id,
                           tipo,
                           quantidade,
                           motivo
                       ) VALUES (
                           :produto_id,
                           :usuario_id,
                           :tipo,
                           :quantidade,
                           :motivo
                       )';
            $stmtMov = $this->pdo->prepare($sqlMov);
            $stmtMov->bindValue(':produto_id', $produtoId, PDO::PARAM_INT);
            $stmtMov->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmtMov->bindValue(':tipo', $tipoNormalizado, PDO::PARAM_STR);
            $stmtMov->bindValue(':quantidade', $quantidade, PDO::PARAM_INT);
            $stmtMov->bindValue(':motivo', $motivoLimpo, PDO::PARAM_STR);
            $stmtMov->execute();

            return $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listarMovimentacoes(int $limite = 25): array
    {
        $sqlBase = 'SELECT
                        m.id,
                        m.tipo,
                        m.quantidade,
                        m.motivo,
                        m.%s AS criado_em,
                        p.nome AS produto_nome,
                        p.codigo_barras,
                        u.nome AS usuario_nome
                    FROM movimentacoes_estoque m
                    INNER JOIN produtos p ON p.id = m.produto_id
                    INNER JOIN usuarios u ON u.id = m.usuario_id
                    ORDER BY m.%s DESC, m.id DESC
                    LIMIT :limite';

        try {
            $stmt = $this->pdo->prepare(sprintf($sqlBase, 'criado_em', 'criado_em'));
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $stmt = $this->pdo->prepare(sprintf($sqlBase, 'criado_em', 'criado_em'));
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}