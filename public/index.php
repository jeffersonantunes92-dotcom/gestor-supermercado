<?php
declare(strict_types=1);

// Configurações seguras de cookie de sessão antes do session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/models/Usuario.php';
require_once BASE_PATH . '/models/Produto.php';
require_once BASE_PATH . '/models/Venda.php';
require_once BASE_PATH . '/models/Financeiro.php';
require_once BASE_PATH . '/models/Fornecedor.php';
require_once BASE_PATH . '/controllers/AuthController.php';
require_once BASE_PATH . '/controllers/EstoqueController.php';
require_once BASE_PATH . '/controllers/CaixaController.php';
require_once BASE_PATH . '/controllers/FinanceiroController.php';
require_once BASE_PATH . '/controllers/UsuarioController.php';
require_once BASE_PATH . '/controllers/FornecedorController.php';

$pdo = Database::getConnection();

$authController       = new AuthController($pdo);
$estoqueController    = new EstoqueController($pdo);
$caixaController      = new CaixaController($pdo);
$financeiroController = new FinanceiroController($pdo);
$usuarioController    = new UsuarioController($pdo);
$fornecedorController = new FornecedorController($pdo);

$rota = $_GET['rota'] ?? 'login';

switch ($rota) {
    // Autenticação
    case 'login':
        $authController->exibirLogin();
        break;

    case 'autenticar':
        $authController->login();
        break;

    case 'logout':
        $authController->logout();
        break;

    // Dashboard Executivo
    case 'dashboard':
        AuthController::verificarPermissao(['admin', 'caixa', 'estoque', 'financeiro']);

        $dashboard = [
            'vendas_hoje_qtd'        => 0,
            'faturamento_hoje'       => 0.0,
            'saldo_tesouraria'       => 0.0,
            'alertas_estoque'        => 0,
            'boletos_7d_qtd'         => 0,
            'boletos_7d_valor'       => 0.0,
            'serie_faturamento_7d'   => [],
            'meios_pagamento'        => [],
            'caixas_abertos'         => [],
            'produtos_criticos'      => [],
            'pedidos_aguardando'     => [],
        ];

        // 1. Faturamento Hoje + Série últimos 7 dias + Meios de Pagamento (compatível com criado_em ou criado_em)
        $colunaDataVendas = 'criado_em';
        try {
            $stmtHoje = $pdo->prepare(
                "SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total), 0) AS total
                 FROM vendas
                 WHERE DATE(criado_em) = CURRENT_DATE"
            );
            $stmtHoje->execute();
            $resHoje = $stmtHoje->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $colunaDataVendas = 'criado_em';
            try {
                $stmtHoje = $pdo->prepare(
                    "SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total), 0) AS total
                     FROM vendas
                     WHERE DATE(criado_em) = CURRENT_DATE"
                );
                $stmtHoje->execute();
                $resHoje = $stmtHoje->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $ignored) {
                $resHoje = false;
            }
        }

        if (!empty($resHoje)) {
            $dashboard['vendas_hoje_qtd']  = (int) $resHoje['qtd'];
            $dashboard['faturamento_hoje'] = (float) $resHoje['total'];
        }

        try {
            $stmt7d = $pdo->prepare(
                "SELECT
                    d.dia::date AS data_dia,
                    COALESCE(SUM(v.valor_total), 0) AS total
                 FROM generate_series(CURRENT_DATE - INTERVAL '6 days', CURRENT_DATE, INTERVAL '1 day') AS d(dia)
                 LEFT JOIN vendas v
                     ON DATE(v.{$colunaDataVendas}) = d.dia::date
                 GROUP BY d.dia
                 ORDER BY d.dia ASC"
            );
            $stmt7d->execute();
            foreach ($stmt7d->fetchAll(PDO::FETCH_ASSOC) as $linha) {
                $dashboard['serie_faturamento_7d'][] = [
                    'label' => date('d/m', strtotime((string) $linha['data_dia'])),
                    'total' => (float) $linha['total'],
                ];
            }

            $stmtPag = $pdo->prepare(
                "SELECT
                    forma_pagamento,
                    COUNT(*) AS qtd,
                    COALESCE(SUM(valor_total), 0) AS total
                 FROM vendas
                 GROUP BY forma_pagamento
                 ORDER BY total DESC"
            );
            $stmtPag->execute();
            foreach ($stmtPag->fetchAll(PDO::FETCH_ASSOC) as $pag) {
                $dashboard['meios_pagamento'][] = [
                    'forma' => (string) $pag['forma_pagamento'],
                    'qtd'   => (int) $pag['qtd'],
                    'total' => (float) $pag['total'],
                ];
            }
        } catch (PDOException $ignored) {
        }

        // 2. Saldo Tesouraria e Boletos Próximos (7 dias)
        try {
            $stmtFin = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(valor) FILTER (WHERE tipo = 'receita' AND status = 'pago'), 0)
                    - COALESCE(SUM(valor) FILTER (WHERE tipo = 'despesa' AND status = 'pago'), 0) AS saldo_tesouraria,
                    COUNT(*) FILTER (WHERE tipo = 'despesa' AND status = 'pendente' AND data_vencimento <= (CURRENT_DATE + INTERVAL '7 days')) AS boletos_qtd,
                    COALESCE(SUM(valor) FILTER (WHERE tipo = 'despesa' AND status = 'pendente' AND data_vencimento <= (CURRENT_DATE + INTERVAL '7 days')), 0) AS boletos_valor
                 FROM financeiro"
            );
            $stmtFin->execute();
            $resFin = $stmtFin->fetch(PDO::FETCH_ASSOC);
            if ($resFin !== false) {
                $dashboard['saldo_tesouraria'] = (float) $resFin['saldo_tesouraria'];
                $dashboard['boletos_7d_qtd']   = (int) $resFin['boletos_qtd'];
                $dashboard['boletos_7d_valor'] = (float) $resFin['boletos_valor'];
            }
        } catch (PDOException $ignored) {
        }

        // 3. Alertas de Estoque (ativo = true AND estoque_atual <= estoque_minimo)
        try {
            $stmtEst = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM produtos
                 WHERE ativo = TRUE AND estoque_atual <= estoque_minimo"
            );
            $stmtEst->execute();
            $dashboard['alertas_estoque'] = (int) $stmtEst->fetchColumn();

            $stmtCrit = $pdo->prepare(
                "SELECT id, codigo_barras, nome, estoque_atual, estoque_minimo
                 FROM produtos
                 WHERE ativo = TRUE AND estoque_atual <= estoque_minimo
                 ORDER BY estoque_atual ASC, nome ASC
                 LIMIT 8"
            );
            $stmtCrit->execute();
            $dashboard['produtos_criticos'] = $stmtCrit->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback caso a tabela produtos ainda não tenha a coluna 'ativo'
            try {
                $stmtEstAlt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM produtos
                     WHERE estoque_atual <= estoque_minimo"
                );
                $stmtEstAlt->execute();
                $dashboard['alertas_estoque'] = (int) $stmtEstAlt->fetchColumn();

                $stmtCritAlt = $pdo->prepare(
                    "SELECT id, codigo_barras, nome, estoque_atual, estoque_minimo
                     FROM produtos
                     WHERE estoque_atual <= estoque_minimo
                     ORDER BY estoque_atual ASC, nome ASC
                     LIMIT 8"
                );
                $stmtCritAlt->execute();
                $dashboard['produtos_criticos'] = $stmtCritAlt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $ignored) {
            }
        }

        // 4. Monitor de Caixas em Tempo Real (status = 'aberto')
        try {
            $stmtCaixas = $pdo->prepare(
                "SELECT
                    ct.id,
                    ct.numero_caixa,
                    ct.total_vendas,
                    ct.total_sangrias,
                    ct.aberto_em,
                    u.nome AS operador_nome
                 FROM caixas_turnos ct
                 INNER JOIN usuarios u ON u.id = ct.operador_id
                 WHERE ct.status = 'aberto'
                 ORDER BY ct.numero_caixa ASC"
            );
            $stmtCaixas->execute();
            $dashboard['caixas_abertos'] = $stmtCaixas->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $ignored) {
        }

        // 5. Pedidos de Compra Aguardando Entrega
        try {
            $stmtPed = $pdo->prepare(
                "SELECT
                    c.id,
                    c.valor_total,
                    c.criado_em,
                    f.nome AS fornecedor_nome
                 FROM compras c
                 INNER JOIN fornecedores f ON f.id = c.fornecedor_id
                 WHERE c.status = 'aguardando_entrega'
                 ORDER BY c.criado_em DESC
                 LIMIT 6"
            );
            $stmtPed->execute();
            $dashboard['pedidos_aguardando'] = $stmtPed->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $ignored) {
        }

        require BASE_PATH . '/views/admin/dashboard.php';
        break;

    // Módulo de Estoque e Mercadorias
    case 'estoque_historico': $estoqueController->historico(); break;
    case 'estoque':
        $estoqueController->index();
        break;

    case 'estoque_salvar_categoria':
        $estoqueController->salvarCategoria();
        break;

    case 'estoque_salvar_produto':
        $estoqueController->salvarProduto();
        break;

    case 'estoque_movimentar':
        $estoqueController->movimentarEstoque();
        break;

    // Módulo de Fornecedores, Pedidos e Recebimento
    case 'fornecedores':
        $fornecedorController->index();
        break;

    case 'fornecedores_salvar':
        $fornecedorController->salvar();
        break;

    case 'fornecedores_comprar':
        $fornecedorController->comprar();
        break;

    case 'fornecedores_receber':
        $fornecedorController->receber();
        break;

    // Módulo Frente de Caixa (PDV + Turnos + Sangria + Fechamento)
    case 'caixa':
    case 'pdv':
        $caixaController->index();
        break;

    case 'caixa_abrir':
        $caixaController->abrir();
        break;

    case 'caixa_finalizar':
        $caixaController->finalizar();
        break;

    case 'caixa_sangria':
        $caixaController->sangria();
        break;

    case 'caixa_fechar':
        $caixaController->fechar();
        break;

    // Módulo Financeiro e Relatórios
    case 'financeiro':
        $financeiroController->index();
        break;

    case 'financeiro_salvar':
        $financeiroController->salvar();
        break;

    case 'financeiro_baixar':
        $financeiroController->baixar();
        break;

    // Módulo Gestão de Usuários
    case 'usuarios':
        $usuarioController->index();
        break;

    case 'usuarios_salvar':
        $usuarioController->salvar();
        break;

    case 'usuarios_status':
        $usuarioController->alternarStatus();
        break;

    default:
        header('Location: index.php?rota=login');
        exit;
}