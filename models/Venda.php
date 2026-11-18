<?php
declare(strict_types=1);

class Venda
{
    private PDO $pdo;

    public const TERMINAIS_DISPONIVEIS = [
        'Caixa 01',
        'Caixa 02',
        'Caixa 03',
        'Caixa 04',
        'Caixa Rápido',
    ];

    private const FORMAS_PAGAMENTO_VALIDAS = [
        'dinheiro',
        'pix',
        'cartao_credito',
        'cartao_debito',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lista produtos ativos para busca rápida no PDV.
     */
    public function listarProdutosPdv(): array
    {
        $sql = 'SELECT
                    p.id,
                    p.codigo_barras,
                    p.nome,
                    p.preco_venda,
                    p.estoque_atual,
                    p.estoque_minimo,
                    c.nome AS categoria_nome
                FROM produtos p
                LEFT JOIN categorias c ON c.id = p.categoria_id
                ORDER BY p.nome ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna o turno atualmente aberto do operador (ou null se não houver).
     */
    public function obterTurnoAberto(int $operadorId): ?array
    {
        $sql = "SELECT
                    ct.id,
                    ct.operador_id,
                    ct.numero_caixa,
                    ct.valor_abertura,
                    ct.total_vendas,
                    ct.total_sangrias,
                    ct.status,
                    ct.aberto_em
                FROM caixas_turnos ct
                WHERE ct.operador_id = :operador_id
                  AND ct.status = 'aberto'
                ORDER BY ct.id DESC
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':operador_id', $operadorId, PDO::PARAM_INT);
        $stmt->execute();

        $turno = $stmt->fetch(PDO::FETCH_ASSOC);
        return $turno !== false ? $turno : null;
    }

    /**
     * Lista os terminais que já estão abertos por algum operador.
     *
     * @return array<string, array{id: int, operador_nome: string, aberto_em: string}>
     */
    public function listarTerminaisEmUso(): array
    {
        $sql = "SELECT
                    ct.id,
                    ct.numero_caixa,
                    ct.aberto_em,
                    u.nome AS operador_nome
                FROM caixas_turnos ct
                INNER JOIN usuarios u ON u.id = ct.operador_id
                WHERE ct.status = 'aberto'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $mapa = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[(string) $row['numero_caixa']] = [
                'id'            => (int) $row['id'],
                'operador_nome' => (string) $row['operador_nome'],
                'aberto_em'     => (string) $row['aberto_em'],
            ];
        }

        return $mapa;
    }

    /**
     * Abre um novo turno para o operador no terminal escolhido,
     * impedindo abertura simultânea do mesmo terminal por dois operadores.
     */
    public function abrirCaixa(int $operadorId, string $numeroCaixa, float $valorAbertura): int
    {
        if ($operadorId <= 0) {
            throw new InvalidArgumentException('Operador inválido.');
        }

        $terminal = trim($numeroCaixa);
        if (!in_array($terminal, self::TERMINAIS_DISPONIVEIS, true)) {
            throw new InvalidArgumentException('Terminal de caixa inválido.');
        }

        if ($valorAbertura < 0) {
            throw new InvalidArgumentException('O fundo de troco não pode ser negativo.');
        }

        try {
            $this->pdo->beginTransaction();

            // Advisory lock transacional por terminal no PostgreSQL evita condição de corrida
            $stmtLock = $this->pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:terminal))');
            $stmtLock->bindValue(':terminal', 'pdv_' . $terminal, PDO::PARAM_STR);
            $stmtLock->execute();

            // Verifica se o operador já tem turno aberto
            $sqlOp = "SELECT id, numero_caixa
                      FROM caixas_turnos
                      WHERE operador_id = :operador_id AND status = 'aberto'
                      LIMIT 1";
            $stmtOp = $this->pdo->prepare($sqlOp);
            $stmtOp->bindValue(':operador_id', $operadorId, PDO::PARAM_INT);
            $stmtOp->execute();
            $turnoOp = $stmtOp->fetch(PDO::FETCH_ASSOC);

            if ($turnoOp !== false) {
                throw new RuntimeException(
                    "Você já possui o Turno #{$turnoOp['id']} aberto no {$turnoOp['numero_caixa']}."
                );
            }

            // Verifica se o terminal já está em uso por outro operador
            $sqlTerm = "SELECT ct.id, u.nome AS operador_nome
                        FROM caixas_turnos ct
                        INNER JOIN usuarios u ON u.id = ct.operador_id
                        WHERE ct.numero_caixa = :numero_caixa AND ct.status = 'aberto'
                        LIMIT 1";
            $stmtTerm = $this->pdo->prepare($sqlTerm);
            $stmtTerm->bindValue(':numero_caixa', $terminal, PDO::PARAM_STR);
            $stmtTerm->execute();
            $terminalOcupado = $stmtTerm->fetch(PDO::FETCH_ASSOC);

            if ($terminalOcupado !== false) {
                throw new RuntimeException(
                    "O terminal {$terminal} já está aberto pelo operador {$terminalOcupado['operador_nome']}."
                );
            }

            $sqlInsert = "INSERT INTO caixas_turnos (
                              operador_id,
                              numero_caixa,
                              valor_abertura,
                              total_vendas,
                              total_sangrias,
                              status
                          ) VALUES (
                              :operador_id,
                              :numero_caixa,
                              :valor_abertura,
                              0.00,
                              0.00,
                              'aberto'
                          ) RETURNING id";
            $stmtInsert = $this->pdo->prepare($sqlInsert);
            $stmtInsert->bindValue(':operador_id', $operadorId, PDO::PARAM_INT);
            $stmtInsert->bindValue(':numero_caixa', $terminal, PDO::PARAM_STR);
            $stmtInsert->bindValue(':valor_abertura', number_format($valorAbertura, 2, '.', ''), PDO::PARAM_STR);
            $stmtInsert->execute();

            $turnoId = (int) $stmtInsert->fetchColumn();
            $this->pdo->commit();

            return $turnoId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Finaliza a venda no PDV vinculada ao turno aberto do operador:
     * - NÃO lança na tabela 'financeiro' (o envio é consolidado no fechamento).
     * - Baixa estoque em 'produtos', registra 'movimentacoes_estoque' e soma em 'caixas_turnos.total_vendas'.
     *
     * @param array<int, array{produto_id: int, quantidade: int}> $itens
     * @return array{venda_id: int, turno_id: int, valor_total: float, total_vendas_turno: float}
     */
    public function finalizarVenda(int $operadorId, string $formaPagamento, array $itens): array
    {
        if ($operadorId <= 0) {
            throw new InvalidArgumentException('Operador de caixa inválido.');
        }

        $formaLimpa = trim($formaPagamento);
        if (!in_array($formaLimpa, self::FORMAS_PAGAMENTO_VALIDAS, true)) {
            throw new InvalidArgumentException('Forma de pagamento inválida.');
        }

        if (empty($itens)) {
            throw new InvalidArgumentException('O carrinho está vazio. Adicione ao menos um produto.');
        }

        $itensConsolidados = [];
        foreach ($itens as $item) {
            $produtoId  = (int) ($item['produto_id'] ?? 0);
            $quantidade = (int) ($item['quantidade'] ?? 0);

            if ($produtoId <= 0 || $quantidade <= 0) {
                throw new InvalidArgumentException('Item do carrinho com produto ou quantidade inválida.');
            }

            $itensConsolidados[$produtoId] = ($itensConsolidados[$produtoId] ?? 0) + $quantidade;
        }

        try {
            $this->pdo->beginTransaction();

            // Exige turno aberto para o operador
            $sqlTurno = "SELECT id, numero_caixa
                         FROM caixas_turnos
                         WHERE operador_id = :operador_id AND status = 'aberto'
                         ORDER BY id DESC
                         LIMIT 1
                         FOR UPDATE";
            $stmtTurno = $this->pdo->prepare($sqlTurno);
            $stmtTurno->bindValue(':operador_id', $operadorId, PDO::PARAM_INT);
            $stmtTurno->execute();
            $turno = $stmtTurno->fetch(PDO::FETCH_ASSOC);

            if ($turno === false) {
                throw new RuntimeException('Nenhum turno de caixa aberto. Realize a Abertura de Caixa antes de vender.');
            }

            $turnoId     = (int) $turno['id'];
            $numeroCaixa = (string) $turno['numero_caixa'];

            // Valida estoque e calcula valor total no servidor
            $sqlLockProduto = 'SELECT id, nome, preco_venda, estoque_atual
                               FROM produtos
                               WHERE id = :id
                               FOR UPDATE';
            $stmtLock = $this->pdo->prepare($sqlLockProduto);

            $itensProcessados = [];
            $valorTotalVenda  = 0.0;

            foreach ($itensConsolidados as $produtoId => $quantidade) {
                $stmtLock->bindValue(':id', $produtoId, PDO::PARAM_INT);
                $stmtLock->execute();
                $produto = $stmtLock->fetch(PDO::FETCH_ASSOC);

                if ($produto === false) {
                    throw new RuntimeException("Produto ID #{$produtoId} não encontrado.");
                }

                $estoqueAtual = (int) $produto['estoque_atual'];
                if ($estoqueAtual < $quantidade) {
                    throw new RuntimeException(
                        "Estoque insuficiente para \"{$produto['nome']}\". Disponível: {$estoqueAtual} un."
                    );
                }

                $precoUnitario    = (float) $produto['preco_venda'];
                $subtotal         = round($precoUnitario * $quantidade, 2);
                $valorTotalVenda += $subtotal;

                $itensProcessados[] = [
                    'produto_id'     => $produtoId,
                    'quantidade'     => $quantidade,
                    'preco_unitario' => $precoUnitario,
                    'subtotal'       => $subtotal,
                ];
            }

            $valorTotalVenda = round($valorTotalVenda, 2);

            // Insere venda vinculada ao turno_id
            $sqlVenda = 'INSERT INTO vendas (operador_id, turno_id, valor_total, forma_pagamento)
                         VALUES (:operador_id, :turno_id, :valor_total, :forma_pagamento)
                         RETURNING id';
            $stmtVenda = $this->pdo->prepare($sqlVenda);
            $stmtVenda->bindValue(':operador_id', $operadorId, PDO::PARAM_INT);
            $stmtVenda->bindValue(':turno_id', $turnoId, PDO::PARAM_INT);
            $stmtVenda->bindValue(':valor_total', number_format($valorTotalVenda, 2, '.', ''), PDO::PARAM_STR);
            $stmtVenda->bindValue(':forma_pagamento', $formaLimpa, PDO::PARAM_STR);
            $stmtVenda->execute();

            $vendaId = (int) $stmtVenda->fetchColumn();
            if ($vendaId <= 0) {
                throw new RuntimeException('Falha ao obter o ID da venda gerada.');
            }

            $sqlItem = 'INSERT INTO venda_itens (
                            venda_id,
                            produto_id,
                            quantidade,
                            preco_unitario,
                            subtotal
                        ) VALUES (
                            :venda_id,
                            :produto_id,
                            :quantidade,
                            :preco_unitario,
                            :subtotal
                        )';
            $stmtItem = $this->pdo->prepare($sqlItem);

            $sqlBaixa = 'UPDATE produtos
                         SET estoque_atual = estoque_atual - :quantidade
                         WHERE id = :id';
            $stmtBaixa = $this->pdo->prepare($sqlBaixa);

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

            $motivoVenda = "Venda PDV #{$vendaId} ({$numeroCaixa})";

            foreach ($itensProcessados as $item) {
                $stmtItem->bindValue(':venda_id', $vendaId, PDO::PARAM_INT);
                $stmtItem->bindValue(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                $stmtItem->bindValue(':quantidade', $item['quantidade'], PDO::PARAM_INT);
                $stmtItem->bindValue(':preco_unitario', number_format($item['preco_unitario'], 2, '.', ''), PDO::PARAM_STR);
                $stmtItem->bindValue(':subtotal', number_format($item['subtotal'], 2, '.', ''), PDO::PARAM_STR);
                $stmtItem->execute();

                $stmtBaixa->bindValue(':quantidade', $item['quantidade'], PDO::PARAM_INT);
                $stmtBaixa->bindValue(':id', $item['produto_id'], PDO::PARAM_INT);
                $stmtBaixa->execute();

                $stmtMov->bindValue(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                $stmtMov->bindValue(':usuario_id', $operadorId, PDO::PARAM_INT);
                $stmtMov->bindValue(':tipo', 'saida', PDO::PARAM_STR);
                $stmtMov->bindValue(':quantidade', $item['quantidade'], PDO::PARAM_INT);
                $stmtMov->bindValue(':motivo', $motivoVenda, PDO::PARAM_STR);
                $stmtMov->execute();
            }

            // Soma a venda no acumulador do turno aberto
            $sqlAtualizaTurno = 'UPDATE caixas_turnos
                                 SET total_vendas = total_vendas + :valor_venda
                                 WHERE id = :turno_id
                                 RETURNING total_vendas';
            $stmtAtualizaTurno = $this->pdo->prepare($sqlAtualizaTurno);
            $stmtAtualizaTurno->bindValue(':valor_venda', number_format($valorTotalVenda, 2, '.', ''), PDO::PARAM_STR);
            $stmtAtualizaTurno->bindValue(':turno_id', $turnoId, PDO::PARAM_INT);
            $stmtAtualizaTurno->execute();
            $totalVendasTurno = (float) $stmtAtualizaTurno->fetchColumn();

            $this->pdo->commit();

            return [
                'venda_id'           => $vendaId,
                'turno_id'           => $turnoId,
                'valor_total'        => $valorTotalVenda,
                'total_vendas_turno' => $totalVendasTurno,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra uma sangria (retirada de numerário) no turno, soma em caixas_turnos.total_sangrias
     * e envia imediatamente como receita 'pago' ao financeiro identificando terminal, turno e operador.
     */
    public function registrarSangria(int $turnoId, int $operadorId, float $valor, string $motivo): array
    {
        $motivoLimpo = trim($motivo);

        if ($turnoId <= 0 || $operadorId <= 0) {
            throw new InvalidArgumentException('Turno ou operador inválido.');
        }

        if ($valor <= 0) {
            throw new InvalidArgumentException('O valor da sangria deve ser maior que zero.');
        }

        if ($motivoLimpo === '') {
            throw new InvalidArgumentException('Informe o motivo da sangria.');
        }

        try {
            $this->pdo->beginTransaction();

            $sqlTurno = "SELECT
                             ct.id,
                             ct.numero_caixa,
                             ct.status,
                             u.nome AS operador_nome
                         FROM caixas_turnos ct
                         INNER JOIN usuarios u ON u.id = ct.operador_id
                         WHERE ct.id = :turno_id
                           AND ct.operador_id = :operador_id
                         FOR UPDATE OF ct";
            $stmtTurno = $this->pdo->prepare($sqlTurno);
            $stmtTurno->bindValue(':turno_id', $turnoId, PDO::PARAM_INT);
            $stmtTurno->bindValue(':operador_id', $operadorId, PDO::PARAM_INT);
            $stmtTurno->execute();
            $turno = $stmtTurno->fetch(PDO::FETCH_ASSOC);

            if ($turno === false || $turno['status'] !== 'aberto') {
                throw new RuntimeException('Turno não encontrado ou já fechado.');
            }

            $sqlSangria = 'INSERT INTO caixa_sangrias (turno_id, operador_id, valor, motivo)
                           VALUES (:turno_id, :operador_id, :valor, :motivo)';
            $stmtSangria = $this->pdo->prepare($sqlSangria);
            $stmtSangria->bindValue(':turno_id', $turnoId, PDO::PARAM_INT);
            $stmtSangria->bindValue(':operador_id', $operadorId, PDO::PARAM_INT);
            $stmtSangria->bindValue(':valor', number_format($valor, 2, '.', ''), PDO::PARAM_STR);
            $stmtSangria->bindValue(':motivo', $motivoLimpo, PDO::PARAM_STR);
            $stmtSangria->execute();

            $sqlAtualizaTurno = 'UPDATE caixas_turnos
                                 SET total_sangrias = total_sangrias + :valor
                                 WHERE id = :turno_id
                                 RETURNING total_sangrias';
            $stmtAtualiza = $this->pdo->prepare($sqlAtualizaTurno);
            $stmtAtualiza->bindValue(':valor', number_format($valor, 2, '.', ''), PDO::PARAM_STR);
            $stmtAtualiza->bindValue(':turno_id', $turnoId, PDO::PARAM_INT);
            $stmtAtualiza->execute();
            $totalSangrias = (float) $stmtAtualiza->fetchColumn();

            // Envia sangria imediatamente para o Financeiro como receita paga
            $descricaoFin = "Sangria {$turno['numero_caixa']} - Turno #{$turnoId} - {$motivoLimpo} (Op: {$turno['operador_nome']})";

            $sqlFin = "INSERT INTO financeiro (
                           tipo,
                           categoria,
                           descricao,
                           valor,
                           data_vencimento,
                           status
                       ) VALUES (
                           'receita',
                           'Sangria de Caixa',
                           :descricao,
                           :valor,
                           CURRENT_DATE,
                           'pago'
                       )";
            $stmtFin = $this->pdo->prepare($sqlFin);
            $stmtFin->bindValue(':descricao', $descricaoFin, PDO::PARAM_STR);
            $stmtFin->bindValue(':valor', number_format($valor, 2, '.', ''), PDO::PARAM_STR);
            $stmtFin->execute();

            $this->pdo->commit();

            return [
                'turno_id'       => $turnoId,
                'numero_caixa'   => (string) $turno['numero_caixa'],
                'total_sangrias' => $totalSangrias,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Fecha o turno de caixa, calcula saldo líquido (total_vendas - total_sangrias)
     * e envia UMA ÚNICA receita consolidada para o Financeiro.
     */
    public function fecharCaixa(int $turnoId, int $operadorId): array
    {
        if ($turnoId <= 0 || $operadorId <= 0) {
            throw new InvalidArgumentException('Turno ou operador inválido.');
        }

        try {
            $this->pdo->beginTransaction();

            $sqlTurno = "SELECT
                             ct.id,
                             ct.numero_caixa,
                             ct.valor_abertura,
                             ct.total_vendas,
                             ct.total_sangrias,
                             ct.status,
                             u.nome AS operador_nome
                         FROM caixas_turnos ct
                         INNER JOIN usuarios u ON u.id = ct.operador_id
                         WHERE ct.id = :turno_id
                           AND ct.operador_id = :operador_id
                         FOR UPDATE OF ct";
            $stmtTurno = $this->pdo->prepare($sqlTurno);
            $stmtTurno->bindValue(':turno_id', $turnoId, PDO::PARAM_INT);
            $stmtTurno->bindValue(':operador_id', $operadorId, PDO::PARAM_INT);
            $stmtTurno->execute();
            $turno = $stmtTurno->fetch(PDO::FETCH_ASSOC);

            if ($turno === false) {
                throw new RuntimeException('Turno de caixa não encontrado.');
            }

            if ($turno['status'] !== 'aberto') {
                throw new RuntimeException('Este turno de caixa já foi encerrado.');
            }

            $totalVendas   = (float) $turno['total_vendas'];
            $totalSangrias = (float) $turno['total_sangrias'];
            $saldoLiquido  = round($totalVendas - $totalSangrias, 2);

            $sqlFechar = "UPDATE caixas_turnos
                          SET valor_fechamento = :valor_fechamento,
                              status           = 'fechado',
                              fechado_em       = NOW()
                          WHERE id = :turno_id";
            $stmtFechar = $this->pdo->prepare($sqlFechar);
            $stmtFechar->bindValue(':valor_fechamento', number_format($saldoLiquido, 2, '.', ''), PDO::PARAM_STR);
            $stmtFechar->bindValue(':turno_id', $turnoId, PDO::PARAM_INT);
            $stmtFechar->execute();

            // Lança receita consolidada no Financeiro caso haja saldo líquido positivo
            if ($saldoLiquido > 0) {
                $descricaoFin = "Fechamento {$turno['numero_caixa']} - Turno #{$turnoId} - Operador {$turno['operador_nome']}";

                $sqlFin = "INSERT INTO financeiro (
                               tipo,
                               categoria,
                               descricao,
                               valor,
                               data_vencimento,
                               status
                           ) VALUES (
                               'receita',
                               'Fechamento de Caixa',
                               :descricao,
                               :valor,
                               CURRENT_DATE,
                               'pago'
                           )";
                $stmtFin = $this->pdo->prepare($sqlFin);
                $stmtFin->bindValue(':descricao', $descricaoFin, PDO::PARAM_STR);
                $stmtFin->bindValue(':valor', number_format($saldoLiquido, 2, '.', ''), PDO::PARAM_STR);
                $stmtFin->execute();
            }

            $this->pdo->commit();

            return [
                'turno_id'       => $turnoId,
                'numero_caixa'   => (string) $turno['numero_caixa'],
                'total_vendas'   => $totalVendas,
                'total_sangrias' => $totalSangrias,
                'saldo_liquido'  => $saldoLiquido,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Lista os cupons (vendas individuais) realizados no turno atual para conferência no PDV.
     */
    public function listarVendasDoTurno(int $turnoId): array
    {
        $sqlBase = 'SELECT id, valor_total, forma_pagamento, %s AS criado_em
                    FROM vendas
                    WHERE turno_id = :turno_id
                    ORDER BY id DESC';

        try {
            $stmt = $this->pdo->prepare(sprintf($sqlBase, 'criado_em'));
            $stmt->bindValue(':turno_id', $turnoId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $stmt = $this->pdo->prepare(sprintf($sqlBase, 'criado_em'));
            $stmt->bindValue(':turno_id', $turnoId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    /**
     * Lista as sangrias registradas no turno atual.
     */
    public function listarSangriasDoTurno(int $turnoId): array
    {
        $sql = 'SELECT id, valor, motivo, criado_em
                FROM caixa_sangrias
                WHERE turno_id = :turno_id
                ORDER BY id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':turno_id', $turnoId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}