<?php
declare(strict_types=1);

class Fornecedor
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lista todos os fornecedores com total de boletos pendentes e total já pago.
     */
    public function listarTodosComFinanceiro(): array
    {
        $sql = "SELECT
                    f.id,
                    f.nome,
                    f.cnpj,
                    f.telefone,
                    f.contato,
                    f.ativo,
                    f.criado_em,
                    COALESCE(SUM(fin.valor) FILTER (WHERE fin.status = 'pendente'), 0) AS total_pendente,
                    COALESCE(SUM(fin.valor) FILTER (WHERE fin.status = 'pago'), 0) AS total_pago,
                    COUNT(fin.id) FILTER (WHERE fin.status = 'pendente') AS qtd_boletos_pendentes
                FROM fornecedores f
                LEFT JOIN financeiro fin
                    ON fin.fornecedor_id = f.id
                   AND fin.tipo = 'despesa'
                GROUP BY f.id, f.nome, f.cnpj, f.telefone, f.contato, f.ativo, f.criado_em
                ORDER BY f.nome ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista apenas fornecedores ativos para seletores.
     */
    public function listarAtivos(): array
    {
        $sql = 'SELECT id, nome, cnpj
                FROM fornecedores
                WHERE ativo = TRUE
                ORDER BY nome ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cadastra um novo fornecedor.
     */
    public function criar(array $dados): bool
    {
        $nome     = trim((string) ($dados['nome'] ?? ''));
        $cnpj     = trim((string) ($dados['cnpj'] ?? ''));
        $telefone = trim((string) ($dados['telefone'] ?? ''));
        $contato  = trim((string) ($dados['contato'] ?? ''));

        if ($nome === '') {
            throw new InvalidArgumentException('O nome/razão social do fornecedor é obrigatório.');
        }

        $sql = 'INSERT INTO fornecedores (nome, cnpj, telefone, contato, ativo)
                VALUES (:nome, :cnpj, :telefone, :contato, :ativo)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':cnpj', $cnpj !== '' ? $cnpj : null, $cnpj !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':telefone', $telefone !== '' ? $telefone : null, $telefone !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':contato', $contato !== '' ? $contato : null, $contato !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ativo', true, PDO::PARAM_BOOL);

        return $stmt->execute();
    }

    /**
     * Ativa ou desativa um fornecedor.
     */
    public function alternarStatus(int $id, bool $ativo): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Fornecedor inválido.');
        }

        $sql = 'UPDATE fornecedores
                SET ativo = :ativo
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':ativo', $ativo, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * ETAPA 1: Cria um Pedido de Compra com status 'aguardando_entrega'.
     * NÃO altera o estoque e NÃO gera lançamentos no financeiro.
     *
     * @param array<int, array{produto_id: int, quantidade: int, preco_custo_unitario: float}> $itens
     */
    public function salvarPedido(
        int $fornecedorId,
        int $usuarioId,
        string $condicaoPagamento,
        string $observacao,
        array $itens
    ): int {
        if ($fornecedorId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('Fornecedor ou usuário inválido.');
        }

        if (empty($itens)) {
            throw new InvalidArgumentException('Adicione pelo menos uma mercadoria ao pedido de compra.');
        }

        // Consolida itens por produto_id
        $itensValidados    = [];
        $valorTotalPedido  = 0.0;

        foreach ($itens as $item) {
            $produtoId  = (int) ($item['produto_id'] ?? 0);
            $quantidade = (int) ($item['quantidade'] ?? 0);
            $custoUnit  = (float) ($item['preco_custo_unitario'] ?? 0);

            if ($produtoId <= 0 || $quantidade <= 0 || $custoUnit <= 0) {
                throw new InvalidArgumentException('Todos os itens devem possuir produto, quantidade e custo unitário válidos.');
            }

            $subtotal = round($quantidade * $custoUnit, 2);
            $valorTotalPedido += $subtotal;

            if (!isset($itensValidados[$produtoId])) {
                $itensValidados[$produtoId] = [
                    'produto_id'           => $produtoId,
                    'quantidade'           => $quantidade,
                    'preco_custo_unitario' => $custoUnit,
                    'subtotal'             => $subtotal,
                ];
            } else {
                $itensValidados[$produtoId]['quantidade'] += $quantidade;
                $itensValidados[$produtoId]['preco_custo_unitario'] = $custoUnit;
                $itensValidados[$produtoId]['subtotal'] += $subtotal;
            }
        }

        $valorTotalPedido = round($valorTotalPedido, 2);
        $condicaoLimpa    = trim($condicaoPagamento);
        $obsLimpa         = trim($observacao);

        try {
            $this->pdo->beginTransaction();

            $sqlForn = 'SELECT id, ativo FROM fornecedores WHERE id = :id FOR UPDATE';
            $stmtForn = $this->pdo->prepare($sqlForn);
            $stmtForn->bindValue(':id', $fornecedorId, PDO::PARAM_INT);
            $stmtForn->execute();
            $fornecedor = $stmtForn->fetch(PDO::FETCH_ASSOC);

            if ($fornecedor === false) {
                throw new RuntimeException('Fornecedor não encontrado.');
            }

            if (!filter_var($fornecedor['ativo'], FILTER_VALIDATE_BOOLEAN)) {
                throw new RuntimeException('Fornecedor inativo. Ative-o antes de emitir um pedido.');
            }

            // Garante que todos os produtos solicitados existem
            $sqlCheckProd = 'SELECT id FROM produtos WHERE id = :id';
            $stmtCheckProd = $this->pdo->prepare($sqlCheckProd);
            foreach ($itensValidados as $item) {
                $stmtCheckProd->bindValue(':id', $item['produto_id'], PDO::PARAM_INT);
                $stmtCheckProd->execute();
                if ($stmtCheckProd->fetchColumn() === false) {
                    throw new RuntimeException("Produto ID #{$item['produto_id']} não encontrado.");
                }
            }

            $sqlCompra = 'INSERT INTO compras (
                              fornecedor_id,
                              usuario_id,
                              valor_total,
                              condicao_pagamento,
                              observacao,
                              status
                          ) VALUES (
                              :fornecedor_id,
                              :usuario_id,
                              :valor_total,
                              :condicao_pagamento,
                              :observacao,
                              :status
                          ) RETURNING id';
            $stmtCompra = $this->pdo->prepare($sqlCompra);
            $stmtCompra->bindValue(':fornecedor_id', $fornecedorId, PDO::PARAM_INT);
            $stmtCompra->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmtCompra->bindValue(':valor_total', number_format($valorTotalPedido, 2, '.', ''), PDO::PARAM_STR);
            $stmtCompra->bindValue(':condicao_pagamento', $condicaoLimpa !== '' ? $condicaoLimpa : null, $condicaoLimpa !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtCompra->bindValue(':observacao', $obsLimpa !== '' ? $obsLimpa : null, $obsLimpa !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtCompra->bindValue(':status', 'aguardando_entrega', PDO::PARAM_STR);
            $stmtCompra->execute();

            $compraId = (int) $stmtCompra->fetchColumn();
            if ($compraId <= 0) {
                throw new RuntimeException('Falha ao gerar o número do pedido de compra.');
            }

            $sqlItem = 'INSERT INTO compra_itens (
                            compra_id,
                            produto_id,
                            quantidade,
                            quantidade_recebida,
                            preco_custo_unitario,
                            subtotal
                        ) VALUES (
                            :compra_id,
                            :produto_id,
                            :quantidade,
                            :quantidade_recebida,
                            :preco_custo_unitario,
                            :subtotal
                        )';
            $stmtItem = $this->pdo->prepare($sqlItem);

            foreach ($itensValidados as $item) {
                $stmtItem->bindValue(':compra_id', $compraId, PDO::PARAM_INT);
                $stmtItem->bindValue(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                $stmtItem->bindValue(':quantidade', $item['quantidade'], PDO::PARAM_INT);
                $stmtItem->bindValue(':quantidade_recebida', 0, PDO::PARAM_INT);
                $stmtItem->bindValue(':preco_custo_unitario', number_format($item['preco_custo_unitario'], 2, '.', ''), PDO::PARAM_STR);
                $stmtItem->bindValue(':subtotal', number_format($item['subtotal'], 2, '.', ''), PDO::PARAM_STR);
                $stmtItem->execute();
            }

            $this->pdo->commit();

            return $compraId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ETAPA 2: Conferência e Recebimento do Pedido de Compra.
     * - Aceita SOMENTE os itens originalmente presentes em 'compra_itens' deste pedido.
     * - Atualiza quantidade_recebida, recalcula valor_total real, soma estoque_atual e atualiza preco_custo.
     * - Registra movimentação 'entrada' em 'movimentacoes_estoque'.
     * - Parcela o valor real recebido pelos prazos informados (ex: "21/28/35/42") gerando boletos pendentes no 'financeiro'.
     * - Atualiza status da compra para 'recebido' e recebido_em = NOW().
     *
     * @param array<int, int> $quantidadesRecebidas Mapa [compra_item_id => quantidade_recebida]
     * @return array{compra_id: int, valor_total: float, total_parcelas: int}
     */
    public function receberPedido(
        int $compraId,
        int $usuarioId,
        string $prazoParcelas,
        array $quantidadesRecebidas
    ): array {
        if ($compraId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('Pedido ou usuário inválido.');
        }

        $prazosDias = $this->parsearPrazos($prazoParcelas);
        $condicaoFormatada = implode('/', $prazosDias);

        try {
            $this->pdo->beginTransaction();

            // Bloqueia o cabeçalho da compra para evitar recebimento duplicado
            $sqlCompra = 'SELECT
                              c.id,
                              c.fornecedor_id,
                              c.status,
                              f.nome AS fornecedor_nome
                          FROM compras c
                          INNER JOIN fornecedores f ON f.id = c.fornecedor_id
                          WHERE c.id = :id
                          FOR UPDATE OF c';
            $stmtCompra = $this->pdo->prepare($sqlCompra);
            $stmtCompra->bindValue(':id', $compraId, PDO::PARAM_INT);
            $stmtCompra->execute();
            $compra = $stmtCompra->fetch(PDO::FETCH_ASSOC);

            if ($compra === false) {
                throw new RuntimeException('Pedido de compra não encontrado.');
            }

            if ($compra['status'] !== 'aguardando_entrega') {
                throw new RuntimeException('Este pedido já foi recebido ou cancelado anteriormente.');
            }

            // Busca SOMENTE os itens originalmente pedidos (impede entrada de produtos não solicitados)
            $sqlItens = 'SELECT
                             ci.id AS compra_item_id,
                             ci.produto_id,
                             ci.quantidade AS quantidade_pedida,
                             ci.preco_custo_unitario,
                             p.nome AS produto_nome
                         FROM compra_itens ci
                         INNER JOIN produtos p ON p.id = ci.produto_id
                         WHERE ci.compra_id = :compra_id
                         FOR UPDATE OF ci';
            $stmtItens = $this->pdo->prepare($sqlItens);
            $stmtItens->bindValue(':compra_id', $compraId, PDO::PARAM_INT);
            $stmtItens->execute();
            $itensPedido = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            if (empty($itensPedido)) {
                throw new RuntimeException('Nenhum item encontrado para este pedido.');
            }

            $sqlUpdateItem = 'UPDATE compra_itens
                              SET quantidade_recebida = :quantidade_recebida,
                                  subtotal            = :subtotal
                              WHERE id = :id AND compra_id = :compra_id';
            $stmtUpdateItem = $this->pdo->prepare($sqlUpdateItem);

            $sqlUpdateProd = 'UPDATE produtos
                              SET estoque_atual = estoque_atual + :quantidade_recebida,
                                  preco_custo   = :preco_custo
                              WHERE id = :produto_id';
            $stmtUpdateProd = $this->pdo->prepare($sqlUpdateProd);

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

            $valorTotalRecebido = 0.0;
            $totalItensRecebidos = 0;
            $motivoEntrada = "Recebimento Compra #{$compraId} ({$compra['fornecedor_nome']})";

            foreach ($itensPedido as $item) {
                $itemId         = (int) $item['compra_item_id'];
                $produtoId      = (int) $item['produto_id'];
                $qtdPedida      = (int) $item['quantidade_pedida'];
                $custoUnitario  = (float) $item['preco_custo_unitario'];
                $qtdRecebida    = (int) ($quantidadesRecebidas[$itemId] ?? 0);

                if ($qtdRecebida < 0) {
                    throw new InvalidArgumentException("Quantidade recebida inválida para \"{$item['produto_nome']}\".");
                }

                if ($qtdRecebida > $qtdPedida) {
                    throw new InvalidArgumentException(
                        "A quantidade recebida de \"{$item['produto_nome']}\" ({$qtdRecebida}) não pode exceder a quantidade pedida ({$qtdPedida})."
                    );
                }

                $subtotalReal = round($qtdRecebida * $custoUnitario, 2);
                $valorTotalRecebido += $subtotalReal;
                $totalItensRecebidos += $qtdRecebida;

                $stmtUpdateItem->bindValue(':quantidade_recebida', $qtdRecebida, PDO::PARAM_INT);
                $stmtUpdateItem->bindValue(':subtotal', number_format($subtotalReal, 2, '.', ''), PDO::PARAM_STR);
                $stmtUpdateItem->bindValue(':id', $itemId, PDO::PARAM_INT);
                $stmtUpdateItem->bindValue(':compra_id', $compraId, PDO::PARAM_INT);
                $stmtUpdateItem->execute();

                if ($qtdRecebida > 0) {
                    $stmtUpdateProd->bindValue(':quantidade_recebida', $qtdRecebida, PDO::PARAM_INT);
                    $stmtUpdateProd->bindValue(':preco_custo', number_format($custoUnitario, 2, '.', ''), PDO::PARAM_STR);
                    $stmtUpdateProd->bindValue(':produto_id', $produtoId, PDO::PARAM_INT);
                    $stmtUpdateProd->execute();

                    $stmtMov->bindValue(':produto_id', $produtoId, PDO::PARAM_INT);
                    $stmtMov->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
                    $stmtMov->bindValue(':tipo', 'entrada', PDO::PARAM_STR);
                    $stmtMov->bindValue(':quantidade', $qtdRecebida, PDO::PARAM_INT);
                    $stmtMov->bindValue(':motivo', $motivoEntrada, PDO::PARAM_STR);
                    $stmtMov->execute();
                }
            }

            $valorTotalRecebido = round($valorTotalRecebido, 2);
            if ($totalItensRecebidos <= 0 || $valorTotalRecebido <= 0) {
                throw new InvalidArgumentException('Informe a quantidade recebida (> 0) de pelo menos uma mercadoria.');
            }

            // Parcelamento exato em centavos para não perder arredondamentos
            $totalParcelas       = count($prazosDias);
            $totalCentavos       = (int) round($valorTotalRecebido * 100);
            $parcelaBaseCentavos = intdiv($totalCentavos, $totalParcelas);
            $restoCentavos       = $totalCentavos % $totalParcelas;

            $sqlFin = 'INSERT INTO financeiro (
                           tipo,
                           categoria,
                           descricao,
                           valor,
                           data_vencimento,
                           status,
                           fornecedor_id
                       ) VALUES (
                           :tipo,
                           :categoria,
                           :descricao,
                           :valor,
                           :data_vencimento,
                           :status,
                           :fornecedor_id
                       )';
            $stmtFin = $this->pdo->prepare($sqlFin);

            $hoje = new DateTimeImmutable('today');
            $primeiroVencimento = null;

            foreach ($prazosDias as $indice => $dias) {
                $numeroParcela   = $indice + 1;
                $valorCentavos   = $parcelaBaseCentavos + ($indice < $restoCentavos ? 1 : 0);
                $valorParcela    = $valorCentavos / 100;
                $dataVencimento  = $hoje->modify("+{$dias} days")->format('Y-m-d');

                if ($primeiroVencimento === null) {
                    $primeiroVencimento = $dataVencimento;
                }

                $descricao = "Boleto Compra #{$compraId} - Parcela {$numeroParcela}/{$totalParcelas} (Prazo {$dias} dias)";

                $stmtFin->bindValue(':tipo', 'despesa', PDO::PARAM_STR);
                $stmtFin->bindValue(':categoria', 'Fornecedores', PDO::PARAM_STR);
                $stmtFin->bindValue(':descricao', $descricao, PDO::PARAM_STR);
                $stmtFin->bindValue(':valor', number_format($valorParcela, 2, '.', ''), PDO::PARAM_STR);
                $stmtFin->bindValue(':data_vencimento', $dataVencimento, PDO::PARAM_STR);
                $stmtFin->bindValue(':status', 'pendente', PDO::PARAM_STR);
                $stmtFin->bindValue(':fornecedor_id', (int) $compra['fornecedor_id'], PDO::PARAM_INT);
                $stmtFin->execute();
            }

            // Atualiza cabeçalho da compra para 'recebido'
            $sqlAtualizaCompra = "UPDATE compras
                                  SET valor_total            = :valor_total,
                                      condicao_pagamento     = :condicao_pagamento,
                                      data_vencimento_boleto = :data_vencimento_boleto,
                                      status                 = 'recebido',
                                      recebido_em            = NOW()
                                  WHERE id = :id";
            $stmtAtualizaCompra = $this->pdo->prepare($sqlAtualizaCompra);
            $stmtAtualizaCompra->bindValue(':valor_total', number_format($valorTotalRecebido, 2, '.', ''), PDO::PARAM_STR);
            $stmtAtualizaCompra->bindValue(':condicao_pagamento', $condicaoFormatada, PDO::PARAM_STR);
            $stmtAtualizaCompra->bindValue(':data_vencimento_boleto', $primeiroVencimento, PDO::PARAM_STR);
            $stmtAtualizaCompra->bindValue(':id', $compraId, PDO::PARAM_INT);
            $stmtAtualizaCompra->execute();

            $this->pdo->commit();

            return [
                'compra_id'      => $compraId,
                'valor_total'    => $valorTotalRecebido,
                'total_parcelas' => $totalParcelas,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Lista os últimos pedidos/compras junto com seus itens para conferência imediata.
     */
    public function listarComprasComItens(int $limite = 20): array
    {
        $sql = 'SELECT
                    c.id,
                    c.fornecedor_id,
                    c.valor_total,
                    c.status,
                    c.condicao_pagamento,
                    c.data_vencimento_boleto,
                    c.observacao,
                    c.criado_em,
                    c.recebido_em,
                    f.nome AS fornecedor_nome,
                    u.nome AS usuario_nome
                FROM compras c
                INNER JOIN fornecedores f ON f.id = c.fornecedor_id
                INNER JOIN usuarios u ON u.id = c.usuario_id
                ORDER BY c.criado_em DESC, c.id DESC
                LIMIT :limite';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($compras)) {
            return [];
        }

        $ids = array_map(static fn(array $c): int => (int) $c['id'], $compras);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sqlItens = "SELECT
                         ci.id AS compra_item_id,
                         ci.compra_id,
                         ci.produto_id,
                         ci.quantidade,
                         ci.quantidade_recebida,
                         ci.preco_custo_unitario,
                         ci.subtotal,
                         p.nome AS produto_nome,
                         p.codigo_barras
                     FROM compra_itens ci
                     INNER JOIN produtos p ON p.id = ci.produto_id
                     WHERE ci.compra_id IN ({$placeholders})
                     ORDER BY ci.id ASC";

        $stmtItens = $this->pdo->prepare($sqlItens);
        foreach ($ids as $pos => $compraId) {
            $stmtItens->bindValue($pos + 1, $compraId, PDO::PARAM_INT);
        }
        $stmtItens->execute();

        $itensAgrupados = [];
        foreach ($stmtItens->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itensAgrupados[(int) $item['compra_id']][] = $item;
        }

        foreach ($compras as &$compra) {
            $compra['itens'] = $itensAgrupados[(int) $compra['id']] ?? [];
        }
        unset($compra);

        return $compras;
    }

    /**
     * Converte string de prazos como "21/28/35/42" em array de inteiros [21, 28, 35, 42].
     *
     * @return int[]
     */
    private function parsearPrazos(string $prazoParcelas): array
    {
        $limpo = trim($prazoParcelas);
        if ($limpo === '') {
            throw new InvalidArgumentException('Informe os prazos das parcelas (ex: 21/28/35/42 ou 30/60/90).');
        }

        $partes = array_map('trim', explode('/', $limpo));
        $prazos = [];

        foreach ($partes as $parte) {
            if ($parte === '' || !ctype_digit($parte)) {
                throw new InvalidArgumentException(
                    "Prazo inválido: \"{$parte}\". Informe apenas dias inteiros separados por '/' (ex: 21/28/35/42)."
                );
            }
            $prazos[] = (int) $parte;
        }

        if (empty($prazos)) {
            throw new InvalidArgumentException('Informe ao menos um prazo em dias.');
        }

        return $prazos;
    }
}