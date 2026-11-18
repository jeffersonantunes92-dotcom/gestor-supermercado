<?php
declare(strict_types=1);

class Financeiro
{
    private PDO $pdo;

    private const TIPOS_VALIDOS  = ['receita', 'despesa'];
    private const STATUS_VALIDOS = ['pendente', 'pago'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lista movimentações financeiras ordenadas pelo vencimento mais próximo primeiro
     * (ORDER BY f.data_vencimento ASC, f.id ASC).
     */
    public function listarMovimentacoes(array $filtros = []): array
    {
        $condicoes  = ['1 = 1'];
        $parametros = [];

        $this->aplicarFiltrosComuns($filtros, $condicoes, $parametros, true);

        $sql = 'SELECT
                    f.id,
                    f.tipo,
                    f.categoria,
                    f.descricao,
                    f.valor,
                    f.data_vencimento,
                    f.status,
                    f.venda_id,
                    f.fornecedor_id,
                    f.criado_em,
                    forn.nome AS fornecedor_nome
                FROM financeiro f
                LEFT JOIN fornecedores forn ON forn.id = f.fornecedor_id
                WHERE ' . implode(' AND ', $condicoes) . '
                ORDER BY f.data_vencimento ASC, f.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista cupons individuais do PDV para a aba de Auditoria do Financeiro,
     * sem misturar com o fluxo de contas/fechamentos.
     */
    public function listarCuponsPdv(array $filtros = [], int $limite = 100): array
    {
        $condicoes  = ['1 = 1'];
        $parametros = [];

        if (!empty($filtros['data_inicio'])) {
            $condicoes[]                = 'DATE(v.%s) >= :data_inicio';
            $parametros[':data_inicio'] = $filtros['data_inicio'];
        }
        if (!empty($filtros['data_fim'])) {
            $condicoes[]             = 'DATE(v.%s) <= :data_fim';
            $parametros[':data_fim'] = $filtros['data_fim'];
        }

        $whereTemplate = implode(' AND ', $condicoes);

        $sqlBase = "SELECT
                        v.id,
                        v.turno_id,
                        v.valor_total,
                        v.forma_pagamento,
                        v.%s AS criado_em,
                        u.nome AS operador_nome,
                        ct.numero_caixa
                    FROM vendas v
                    INNER JOIN usuarios u ON u.id = v.operador_id
                    LEFT JOIN caixas_turnos ct ON ct.id = v.turno_id
                    WHERE {$whereTemplate}
                    ORDER BY v.id DESC
                    LIMIT :limite";

        $cupons = [];
        try {
            $sql = str_replace('%s', 'criado_em', $sqlBase);
            $stmt = $this->pdo->prepare($sql);
            foreach ($parametros as $k => $val) {
                $stmt->bindValue($k, $val, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            $cupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $sql = str_replace('%s', 'criado_em', $sqlBase);
            $stmt = $this->pdo->prepare($sql);
            foreach ($parametros as $k => $val) {
                $stmt->bindValue($k, $val, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            $cupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (empty($cupons)) {
            return [];
        }

        // Busca os itens de cada cupom em uma única consulta
        $ids = array_map(static fn(array $c): int => (int) $c['id'], $cupons);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sqlItens = "SELECT
                         vi.venda_id,
                         vi.quantidade,
                         vi.preco_unitario,
                         vi.subtotal,
                         p.nome AS produto_nome
                     FROM venda_itens vi
                     INNER JOIN produtos p ON p.id = vi.produto_id
                     WHERE vi.venda_id IN ({$placeholders})
                     ORDER BY vi.id ASC";
        $stmtItens = $this->pdo->prepare($sqlItens);
        foreach ($ids as $pos => $vendaId) {
            $stmtItens->bindValue($pos + 1, $vendaId, PDO::PARAM_INT);
        }
        $stmtItens->execute();

        $itensPorVenda = [];
        foreach ($stmtItens->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itensPorVenda[(int) $item['venda_id']][] = $item;
        }

        foreach ($cupons as &$cupom) {
            $cupom['itens'] = $itensPorVenda[(int) $cupom['id']] ?? [];
        }
        unset($cupom);

        return $cupons;
    }

    /**
     * Retorna totais consolidados considerando os filtros aplicados.
     */
    public function obterResumo(array $filtros = []): array
    {
        $condicoes  = ['1 = 1'];
        $parametros = [];

        $this->aplicarFiltrosComuns($filtros, $condicoes, $parametros, false);

        $sql = "SELECT
                    COALESCE(SUM(f.valor) FILTER (WHERE f.tipo = 'receita' AND f.status = 'pago'), 0) AS receitas_pagas,
                    COALESCE(SUM(f.valor) FILTER (WHERE f.tipo = 'despesa' AND f.status = 'pago'), 0) AS despesas_pagas,
                    COALESCE(SUM(f.valor) FILTER (WHERE f.tipo = 'despesa' AND f.status = 'pendente'), 0) AS contas_pagar_pendentes,
                    COALESCE(SUM(f.valor) FILTER (WHERE f.tipo = 'receita' AND f.status = 'pendente'), 0) AS contas_receber_pendentes
                FROM financeiro f
                WHERE " . implode(' AND ', $condicoes);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $receitasPagas          = (float) ($dados['receitas_pagas'] ?? 0);
        $despesasPagas          = (float) ($dados['despesas_pagas'] ?? 0);
        $contasPagarPendentes   = (float) ($dados['contas_pagar_pendentes'] ?? 0);
        $contasReceberPendentes = (float) ($dados['contas_receber_pendentes'] ?? 0);

        return [
            'receitas_pagas'           => $receitasPagas,
            'despesas_pagas'           => $despesasPagas,
            'contas_pagar_pendentes'   => $contasPagarPendentes,
            'contas_receber_pendentes' => $contasReceberPendentes,
            'total_pendentes'          => $contasPagarPendentes + $contasReceberPendentes,
            'saldo_liquido'            => $receitasPagas - $despesasPagas,
        ];
    }

    public function relatorioDespesasPorCategoria(array $filtros = []): array
    {
        $condicoes  = ["f.tipo = 'despesa'"];
        $parametros = [];

        $filtrosDespesa = $filtros;
        unset($filtrosDespesa['tipo']);
        $this->aplicarFiltrosComuns($filtrosDespesa, $condicoes, $parametros, true);

        $sql = "SELECT
                    f.categoria,
                    COUNT(*) AS quantidade_lancamentos,
                    COALESCE(SUM(f.valor) FILTER (WHERE f.status = 'pago'), 0) AS total_pago,
                    COALESCE(SUM(f.valor) FILTER (WHERE f.status = 'pendente'), 0) AS total_pendente,
                    COALESCE(SUM(f.valor), 0) AS total_geral
                FROM financeiro f
                WHERE " . implode(' AND ', $condicoes) . '
                GROUP BY f.categoria
                ORDER BY total_geral DESC, f.categoria ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumoContasPagarPorFornecedor(array $filtros = []): array
    {
        $condicoes  = ["f.tipo = 'despesa'", 'f.fornecedor_id IS NOT NULL'];
        $parametros = [];

        if (!empty($filtros['data_inicio'])) {
            $condicoes[]                = 'f.data_vencimento >= :data_inicio';
            $parametros[':data_inicio'] = $filtros['data_inicio'];
        }
        if (!empty($filtros['data_fim'])) {
            $condicoes[]             = 'f.data_vencimento <= :data_fim';
            $parametros[':data_fim'] = $filtros['data_fim'];
        }

        $sql = "SELECT
                    forn.id AS fornecedor_id,
                    forn.nome AS fornecedor_nome,
                    COUNT(f.id) FILTER (WHERE f.status = 'pendente') AS qtd_pendentes,
                    COALESCE(SUM(f.valor) FILTER (WHERE f.status = 'pendente'), 0) AS total_pendente,
                    COALESCE(SUM(f.valor) FILTER (WHERE f.status = 'pago'), 0) AS total_pago
                FROM financeiro f
                INNER JOIN fornecedores forn ON forn.id = f.fornecedor_id
                WHERE " . implode(' AND ', $condicoes) . '
                GROUP BY forn.id, forn.nome
                ORDER BY total_pendente DESC, forn.nome ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criarLancamento(array $dados): bool
    {
        $tipo           = strtolower(trim((string) ($dados['tipo'] ?? '')));
        $categoria      = trim((string) ($dados['categoria'] ?? ''));
        $descricao      = trim((string) ($dados['descricao'] ?? ''));
        $valor          = (float) ($dados['valor'] ?? 0);
        $dataVencimento = trim((string) ($dados['data_vencimento'] ?? ''));
        $status         = strtolower(trim((string) ($dados['status'] ?? 'pendente')));
        $vendaId        = !empty($dados['venda_id']) ? (int) $dados['venda_id'] : null;
        $fornecedorId   = !empty($dados['fornecedor_id']) ? (int) $dados['fornecedor_id'] : null;

        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException('Tipo financeiro inválido.');
        }

        if ($categoria === '' || $descricao === '') {
            throw new InvalidArgumentException('Categoria e descrição são obrigatórias.');
        }

        if ($valor <= 0) {
            throw new InvalidArgumentException('O valor deve ser maior que zero.');
        }

        $dataValida = DateTime::createFromFormat('Y-m-d', $dataVencimento);
        if ($dataValida === false || $dataValida->format('Y-m-d') !== $dataVencimento) {
            throw new InvalidArgumentException('Data de vencimento inválida.');
        }

        if (!in_array($status, self::STATUS_VALIDOS, true)) {
            throw new InvalidArgumentException('Status inválido.');
        }

        $sql = 'INSERT INTO financeiro (
                    tipo,
                    categoria,
                    descricao,
                    valor,
                    data_vencimento,
                    status,
                    venda_id,
                    fornecedor_id
                ) VALUES (
                    :tipo,
                    :categoria,
                    :descricao,
                    :valor,
                    :data_vencimento,
                    :status,
                    :venda_id,
                    :fornecedor_id
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':categoria', $categoria, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $descricao, PDO::PARAM_STR);
        $stmt->bindValue(':valor', number_format($valor, 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':data_vencimento', $dataVencimento, PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':venda_id', $vendaId, $vendaId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':fornecedor_id', $fornecedorId, $fornecedorId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);

        return $stmt->execute();
    }

    public function marcarComoPago(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID do lançamento inválido.');
        }

        $sql = "UPDATE financeiro
                SET status = 'pago'
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function listarCategoriasExistentes(): array
    {
        $sql = "SELECT DISTINCT categoria
                FROM financeiro
                WHERE categoria IS NOT NULL AND TRIM(categoria) <> ''
                ORDER BY categoria ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function aplicarFiltrosComuns(
        array $filtros,
        array &$condicoes,
        array &$parametros,
        bool $filtrarStatus
    ): void {
        if (!empty($filtros['data_inicio'])) {
            $condicoes[]                = 'f.data_vencimento >= :data_inicio';
            $parametros[':data_inicio'] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $condicoes[]             = 'f.data_vencimento <= :data_fim';
            $parametros[':data_fim'] = $filtros['data_fim'];
        }

        if (!empty($filtros['tipo']) && in_array($filtros['tipo'], self::TIPOS_VALIDOS, true)) {
            $condicoes[]         = 'f.tipo = :tipo';
            $parametros[':tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['categoria'])) {
            $condicoes[]              = 'f.categoria = :categoria';
            $parametros[':categoria'] = trim((string) $filtros['categoria']);
        }

        if (!empty($filtros['fornecedor_id'])) {
            $condicoes[]                  = 'f.fornecedor_id = :fornecedor_id';
            $parametros[':fornecedor_id'] = (int) $filtros['fornecedor_id'];
        }

        if (!empty($filtros['somente_fornecedores'])) {
            $condicoes[] = "(f.fornecedor_id IS NOT NULL OR f.categoria = 'Fornecedores')";
        }

        if ($filtrarStatus && !empty($filtros['status']) && in_array($filtros['status'], self::STATUS_VALIDOS, true)) {
            $condicoes[]           = 'f.status = :status';
            $parametros[':status'] = $filtros['status'];
        }
    }
}