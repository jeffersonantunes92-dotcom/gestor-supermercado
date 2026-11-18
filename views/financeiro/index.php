<?php
declare(strict_types=1);

AuthController::verificarPermissao(['admin', 'financeiro']);

$usuarioLogado = $_SESSION['usuario'];
$nomeUsuario   = htmlspecialchars((string) $usuarioLogado['nome'], ENT_QUOTES, 'UTF-8');
$perfilUsuario = (string) $usuarioLogado['perfil'];
$perfilSeguro  = htmlspecialchars(strtoupper($perfilUsuario), ENT_QUOTES, 'UTF-8');

$flashSucesso = $_SESSION['flash_sucesso'] ?? null;
$flashErro    = $_SESSION['flash_erro'] ?? null;
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);

$saldoLiquido      = (float) ($resumo['saldo_liquido'] ?? 0);
$hojeIso           = date('Y-m-d');
$seteDiasIso       = date('Y-m-d', strtotime('+7 days'));

$cuponsJson = json_encode(
    array_map(static function (array $c): array {
        return [
            'id'              => (int) $c['id'],
            'numero_caixa'    => (string) ($c['numero_caixa'] ?? 'PDV'),
            'operador_nome'   => (string) ($c['operador_nome'] ?? '-'),
            'forma_pagamento' => (string) ($c['forma_pagamento'] ?? ''),
            'valor_total'     => (float) $c['valor_total'],
            'criado_em'       => !empty($c['criado_em']) ? date('d/m/Y H:i:s', strtotime((string) $c['criado_em'])) : '-',
            'itens'           => array_map(static function (array $it): array {
                return [
                    'produto_nome'   => (string) $it['produto_nome'],
                    'quantidade'     => (int) $it['quantidade'],
                    'preco_unitario' => (float) $it['preco_unitario'],
                    'subtotal'       => (float) $it['subtotal'],
                ];
            }, $c['itens'] ?? []),
        ];
    }, $cuponsPdv),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$fornecedoresChartJson = json_encode(
    array_map(static fn(array $r): array => [
        'nome'     => (string) $r['fornecedor_nome'],
        'pendente' => (float) $r['total_pendente'],
        'pago'     => (float) $r['total_pago'],
    ], array_slice($resumoFornecedores, 0, 8)),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$categoriasChartJson = json_encode(
    array_map(static fn(array $r): array => [
        'categoria' => (string) $r['categoria'],
        'total'     => (float) $r['total_geral'],
    ], $despesasPorCategoria),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$fluxoChartJson = json_encode(
    $serieFluxoDiario,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão Financeira ERP - Supermercado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body.erp-body {
            background-color: #f1f5f9;
        }
        .setor-card {
            border: 0;
            border-radius: 0.75rem;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        }
        .setor-card .setor-icon-bg {
            position: absolute;
            right: -10px;
            bottom: -14px;
            font-size: 5rem;
            opacity: 0.16;
            pointer-events: none;
        }
        .bg-setor-estoque    { background: linear-gradient(135deg, #2ec4b6 0%, #1ea89a 100%); }
        .bg-setor-receitas   { background: linear-gradient(135deg, #00a8e8 0%, #007ea7 100%); }
        .bg-setor-tesouraria { background: linear-gradient(135deg, #778da9 0%, #415a77 100%); }
        .bg-setor-avisos     { background: linear-gradient(135deg, #ff9f1c 0%, #e67e00 100%); }

        .btn-acao-rapida {
            border: 0;
            border-radius: 0.65rem;
            color: #ffffff;
            padding: 0.9rem 1rem;
            font-weight: 700;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }
        .btn-acao-rapida:hover {
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.14);
        }
        .btn-acao-receita   { background-color: #0077b6; }
        .btn-acao-despesa   { background-color: #c1121f; }
        .btn-acao-auditoria { background-color: #3c096c; }
        .btn-acao-pdf       { background-color: #2a9d8f; }

        .chart-container {
            position: relative;
            height: 240px;
            width: 100%;
        }
        .cabecalho-impressao {
            display: none;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            body.erp-body {
                background-color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .navbar,
            .no-print,
            .btn,
            .modal {
                display: none !important;
            }
            .cabecalho-impressao {
                display: flex !important;
                justify-content: space-between;
                align-items: center;
                border-bottom: 2px solid #0d3b66;
                padding-bottom: 8px;
                margin-bottom: 14px;
            }
            .card,
            .setor-card {
                box-shadow: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .chart-container {
                height: 200px !important;
            }
            table {
                font-size: 11px !important;
            }
            thead {
                display: table-header-group;
            }
            tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="erp-body">
    <!-- Barra de Navegação Superior Padrão -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm no-print">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php?rota=dashboard">
                <i class="bi bi-cart4 me-2"></i>Supermercado ERP
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Alternar navegação">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?rota=dashboard">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>

                    <?php if (in_array($perfilUsuario, ['admin', 'caixa'], true)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?rota=caixa">
                                <i class="bi bi-cash-register me-1"></i>Caixa PDV
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (in_array($perfilUsuario, ['admin', 'estoque'], true)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?rota=estoque">
                                <i class="bi bi-box-seam me-1"></i>Estoque/Mercadorias
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (in_array($perfilUsuario, ['admin', 'estoque', 'financeiro'], true)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?rota=fornecedores">
                                <i class="bi bi-truck me-1"></i>Fornecedores
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (in_array($perfilUsuario, ['admin', 'financeiro'], true)): ?>
                        <li class="nav-item">
                            <a class="nav-link active fw-semibold" href="index.php?rota=financeiro">
                                <i class="bi bi-wallet2 me-1"></i>Financeiro
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($perfilUsuario === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?rota=usuarios">
                                <i class="bi bi-people me-1"></i>Usuários
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center text-white gap-3">
                    <div class="text-end lh-sm">
                        <div class="fw-semibold"><?= $nomeUsuario ?></div>
                        <span class="badge bg-light text-primary"><?= $perfilSeguro ?></span>
                    </div>
                    <a href="index.php?rota=logout" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-4 py-4">
        <!-- Cabeçalho exclusivo para impressão PDF -->
        <div class="cabecalho-impressao">
            <div>
                <h2 class="h4 fw-bold mb-0">Relatório Gerencial Financeiro — Supermercado ERP</h2>
                <small class="text-muted">Emitido por: <?= $nomeUsuario ?> (<?= $perfilSeguro ?>)</small>
            </div>
            <div class="text-end small">
                <div>Data de Emissão: <strong><?= date('d/m/Y H:i') ?></strong></div>
                <div>Ordenação: Vencimento mais próximo</div>
            </div>
        </div>

        <!-- Alertas Flash -->
        <?php if ($flashSucesso !== null): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm no-print" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars((string) $flashSucesso, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <?php if ($flashErro !== null): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm no-print" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars((string) $flashErro, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <!-- 1. TOPO: 4 Blocos Coloridos por Setor -->
        <section class="row g-3 mb-4">
            <!-- Card 1: ESTOQUE & COMPRAS (#2ec4b6) -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card setor-card bg-setor-estoque h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase fw-bold small opacity-90">Estoque &amp; Compras</span>
                            <i class="bi bi-box-seam fs-5"></i>
                        </div>
                        <div class="h3 fw-bold mb-2">
                            <?= (int) $indicadoresEstoque['produtos_ativos'] ?> Produtos
                        </div>
                        <div class="small d-flex flex-column gap-1">
                            <span>
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Estoque Crítico: <strong><?= (int) $indicadoresEstoque['estoque_critico'] ?></strong>
                            </span>
                            <span>
                                <i class="bi bi-truck me-1"></i>
                                Pedidos Aguardando: <strong><?= (int) $indicadoresEstoque['pedidos_aguardando'] ?></strong>
                            </span>
                        </div>
                        <i class="bi bi-box-seam-fill setor-icon-bg"></i>
                    </div>
                </div>
            </div>

            <!-- Card 2: RECEITAS & CAIXA (#00a8e8) -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card setor-card bg-setor-receitas h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase fw-bold small opacity-90">Receitas &amp; Caixa</span>
                            <i class="bi bi-cash-stack fs-5"></i>
                        </div>
                        <div class="h3 fw-bold mb-2">
                            R$ <?= number_format((float) $resumo['receitas_pagas'], 2, ',', '.') ?>
                        </div>
                        <div class="small">
                            <i class="bi bi-check2-circle me-1"></i>
                            Fechamentos de Caixa, Sangrias e Recebimentos Pagos
                        </div>
                        <i class="bi bi-cash-coin setor-icon-bg"></i>
                    </div>
                </div>
            </div>

            <!-- Card 3: TESOURARIA (#778da9) -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card setor-card bg-setor-tesouraria h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase fw-bold small opacity-90">Tesouraria</span>
                            <i class="bi bi-bank fs-5"></i>
                        </div>
                        <div class="h4 fw-bold mb-2">
                            Saldo: R$ <?= number_format($saldoLiquido, 2, ',', '.') ?>
                        </div>
                        <div class="small d-flex flex-column gap-1">
                            <span>
                                A Receber (Pend.): <strong>R$ <?= number_format((float) $resumo['contas_receber_pendentes'], 2, ',', '.') ?></strong>
                            </span>
                            <span>
                                A Pagar (Pend.): <strong>R$ <?= number_format((float) $resumo['contas_pagar_pendentes'], 2, ',', '.') ?></strong>
                            </span>
                        </div>
                        <i class="bi bi-bank2 setor-icon-bg"></i>
                    </div>
                </div>
            </div>

            <!-- Card 4: AVISOS & VENCIMENTOS (#ff9f1c) -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card setor-card bg-setor-avisos h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase fw-bold small opacity-90">Avisos &amp; Vencimentos</span>
                            <i class="bi bi-bell-fill fs-5"></i>
                        </div>
                        <div class="h3 fw-bold mb-1">
                            R$ <?= number_format((float) $avisosVencimentos['valor_proximos_7d'], 2, ',', '.') ?>
                        </div>
                        <div class="small d-flex flex-column gap-1">
                            <span>
                                <strong><?= (int) $avisosVencimentos['qtd_proximos_7d'] ?></strong> boleto(s) vencendo hoje / próx. 7 dias
                            </span>
                            <?php if ((int) $avisosVencimentos['qtd_atrasados'] > 0): ?>
                                <span class="badge bg-danger align-self-start">
                                    <?= (int) $avisosVencimentos['qtd_atrasados'] ?> atrasado(s): R$ <?= number_format((float) $avisosVencimentos['valor_atrasados'], 2, ',', '.') ?>
                                </span>
                            <?php else: ?>
                                <span><i class="bi bi-shield-check me-1"></i>Nenhum boleto em atraso</span>
                            <?php endif; ?>
                        </div>
                        <i class="bi bi-calendar-event-fill setor-icon-bg"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. MEIO: Barra de Botões de Ação Rápida -->
        <section class="row g-3 mb-4 no-print">
            <div class="col-12 col-sm-6 col-lg-3">
                <button
                    type="button"
                    id="btnAtalhoReceita"
                    class="btn btn-acao-rapida btn-acao-receita w-100 d-flex align-items-center justify-content-center gap-2"
                    data-bs-toggle="modal"
                    data-bs-target="#modalLancamento"
                >
                    <i class="bi bi-plus-circle-fill fs-5"></i>
                    <span>+ Lançar Receita</span>
                </button>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <button
                    type="button"
                    id="btnAtalhoDespesa"
                    class="btn btn-acao-rapida btn-acao-despesa w-100 d-flex align-items-center justify-content-center gap-2"
                    data-bs-toggle="modal"
                    data-bs-target="#modalLancamento"
                >
                    <i class="bi bi-file-earmark-minus-fill fs-5"></i>
                    <span>+ Lançar Despesa / Boleto</span>
                </button>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <button
                    type="button"
                    class="btn btn-acao-rapida btn-acao-auditoria w-100 d-flex align-items-center justify-content-center gap-2"
                    data-bs-toggle="modal"
                    data-bs-target="#modalAuditoriaCupons"
                >
                    <i class="bi bi-receipt-cutoff fs-5"></i>
                    <span>Auditoria Cupons PDV (<?= count($cuponsPdv) ?>)</span>
                </button>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <button
                    type="button"
                    id="btnExportarPdf"
                    class="btn btn-acao-rapida btn-acao-pdf w-100 d-flex align-items-center justify-content-center gap-2"
                >
                    <i class="bi bi-file-earmark-pdf-fill fs-5"></i>
                    <span>Exportar Relatório PDF</span>
                </button>
            </div>
        </section>

        <!-- 3. SEÇÃO ANALÍTICA: 3 Cards Lado a Lado com Chart.js -->
        <section class="row g-3 mb-4">
            <!-- Esquerda: Comparativo por Fornecedor -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h2 class="h6 fw-bold mb-0">
                            <i class="bi bi-truck me-2 text-primary"></i>A Pagar por Fornecedor
                        </h2>
                        <a href="index.php?rota=fornecedores" class="small text-decoration-none no-print">Gerenciar</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($resumoFornecedores)): ?>
                            <div class="text-center text-muted py-5 small">
                                Nenhuma conta vinculada a fornecedores no período.
                            </div>
                        <?php else: ?>
                            <div class="chart-container mb-3">
                                <canvas id="chartFornecedores"></canvas>
                            </div>
                            <div class="table-responsive" style="max-height: 130px;">
                                <table class="table table-sm mb-0 small">
                                    <tbody>
                                        <?php foreach ($resumoFornecedores as $rf): ?>
                                            <tr>
                                                <td class="fw-semibold text-truncate" style="max-width: 160px;">
                                                    <?= htmlspecialchars((string) $rf['fornecedor_nome'], ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="text-end text-danger fw-bold">
                                                    R$ <?= number_format((float) $rf['total_pendente'], 2, ',', '.') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Centro: Gráfico de Rosca (Doughnut) Despesas por Categoria -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h2 class="h6 fw-bold mb-0">
                            <i class="bi bi-pie-chart-fill me-2 text-danger"></i>Despesas por Categoria
                        </h2>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <?php if (empty($despesasPorCategoria)): ?>
                            <div class="text-center text-muted py-5 small">
                                Nenhuma despesa encontrada para exibir no gráfico.
                            </div>
                        <?php else: ?>
                            <div class="chart-container">
                                <canvas id="chartCategorias"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Direita: Gráfico Entradas vs Saídas por Data -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h2 class="h6 fw-bold mb-0">
                            <i class="bi bi-bar-chart-line-fill me-2 text-success"></i>Entradas vs Saídas (Período)
                        </h2>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <?php if (empty($serieFluxoDiario)): ?>
                            <div class="text-center text-muted py-5 small">
                                Sem movimentações no período filtrado.
                            </div>
                        <?php else: ?>
                            <div class="chart-container">
                                <canvas id="chartFluxoDiario"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. PARTE INFERIOR: Filtros e Tabela Ordenada por Vencimento (data_vencimento ASC) -->
        <section class="card border-0 shadow-sm mb-4 no-print">
            <div class="card-body">
                <form method="GET" action="index.php" class="row g-3 align-items-end">
                    <input type="hidden" name="rota" value="financeiro">

                    <div class="col-6 col-md-2">
                        <label for="data_inicio" class="form-label small fw-semibold">Data Início</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars((string) $filtros['data_inicio'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-6 col-md-2">
                        <label for="data_fim" class="form-label small fw-semibold">Data Fim</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= htmlspecialchars((string) $filtros['data_fim'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-6 col-md-2">
                        <label for="tipo" class="form-label small fw-semibold">Tipo</label>
                        <select class="form-select" id="tipo" name="tipo">
                            <option value="">Todos</option>
                            <option value="receita" <?= $filtros['tipo'] === 'receita' ? 'selected' : '' ?>>Receita</option>
                            <option value="despesa" <?= $filtros['tipo'] === 'despesa' ? 'selected' : '' ?>>Despesa</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label for="filtro_fornecedor" class="form-label small fw-semibold">Fornecedor</label>
                        <select class="form-select" id="filtro_fornecedor" name="fornecedor_id">
                            <option value="">Todos</option>
                            <?php foreach ($fornecedores as $forn): ?>
                                <option value="<?= (int) $forn['id'] ?>" <?= (int) ($filtros['fornecedor_id'] ?? 0) === (int) $forn['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $forn['nome'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label for="status" class="form-label small fw-semibold">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <option value="pendente" <?= $filtros['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="pago" <?= $filtros['status'] === 'pago' ? 'selected' : '' ?>>Pago</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100 fw-semibold">
                            <i class="bi bi-funnel me-1"></i>Filtrar
                        </button>
                        <a href="index.php?rota=financeiro" class="btn btn-outline-secondary" title="Limpar filtros">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </div>
                </form>
            </div>
        </section>

        <!-- Tabela Principal de Lançamentos -->
        <section class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 fw-bold mb-0">
                    <i class="bi bi-calendar-check me-2 text-primary"></i>Lançamentos Financeiros (Ordenados por Vencimento Mais Próximo)
                </h2>
                <span class="badge bg-light text-dark border"><?= count($movimentacoes) ?> registro(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Vencimento</th>
                                <th>Tipo</th>
                                <th>Categoria / Fornecedor</th>
                                <th>Descrição</th>
                                <th class="text-end">Valor</th>
                                <th class="text-center">Status</th>
                                <th class="text-end no-print">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movimentacoes)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Nenhum lançamento financeiro encontrado para os filtros selecionados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($movimentacoes as $lanc): ?>
                                    <?php
                                    $isReceita  = $lanc['tipo'] === 'receita';
                                    $isPago     = $lanc['status'] === 'pago';
                                    $dataIso    = (string) ($lanc['data_vencimento'] ?? '');
                                    $vencimento = $dataIso !== '' ? date('d/m/Y', strtotime($dataIso)) : '-';

                                    $badgeAlertaVencimento = '';
                                    $classeLinha           = '';
                                    if (!$isPago && $dataIso !== '') {
                                        if ($dataIso < $hojeIso) {
                                            $badgeAlertaVencimento = '<span class="badge bg-danger ms-1">Atrasado</span>';
                                            $classeLinha = 'table-danger';
                                        } elseif ($dataIso === $hojeIso) {
                                            $badgeAlertaVencimento = '<span class="badge bg-warning text-dark ms-1">Vence Hoje</span>';
                                            $classeLinha = 'table-warning';
                                        } elseif ($dataIso <= $seteDiasIso) {
                                            $badgeAlertaVencimento = '<span class="badge bg-info text-dark ms-1">Próx. 7 dias</span>';
                                        }
                                    }
                                    ?>
                                    <tr class="<?= $classeLinha ?>">
                                        <td class="small fw-semibold text-nowrap">
                                            <?= htmlspecialchars($vencimento, ENT_QUOTES, 'UTF-8') ?>
                                            <?= $badgeAlertaVencimento ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $isReceita ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $isReceita ? 'Receita' : 'Despesa' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string) $lanc['categoria'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php if (!empty($lanc['fornecedor_nome'])): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle">
                                                    <i class="bi bi-truck me-1"></i><?= htmlspecialchars((string) $lanc['fornecedor_nome'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars((string) $lanc['descricao'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-end fw-bold text-nowrap <?= $isReceita ? 'text-success' : 'text-danger' ?>">
                                            <?= $isReceita ? '+' : '-' ?> R$ <?= number_format((float) $lanc['valor'], 2, ',', '.') ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $isPago ? 'bg-success bg-opacity-75' : 'bg-warning text-dark' ?>">
                                                <?= $isPago ? 'PAGO' : 'PENDENTE' ?>
                                            </span>
                                        </td>
                                        <td class="text-end no-print">
                                            <?php if (!$isPago): ?>
                                                <form method="POST" action="index.php?rota=financeiro_baixar" class="d-inline">
                                                    <input type="hidden" name="id" value="<?= (int) $lanc['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success fw-semibold">
                                                        <i class="bi bi-check2-circle me-1"></i>Dar Baixa
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small"><i class="bi bi-check-all"></i> Quitado</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal: Novo Lançamento (Receita ou Despesa / Boleto) -->
    <div class="modal fade" id="modalLancamento" tabindex="-1" aria-labelledby="modalLancamentoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="index.php?rota=financeiro_salvar" class="modal-content">
                <div class="modal-header" id="modalLancamentoHeader">
                    <h5 class="modal-title fw-bold" id="modalLancamentoLabel">
                        <i class="bi bi-wallet2 me-2"></i>Novo Lançamento Financeiro
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="lanc_tipo" class="form-label fw-semibold">Tipo</label>
                            <select class="form-select" id="lanc_tipo" name="tipo" required>
                                <option value="despesa">Despesa / Boleto (A Pagar)</option>
                                <option value="receita">Receita (A Receber)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="lanc_status" class="form-label fw-semibold">Status Inicial</label>
                            <select class="form-select" id="lanc_status" name="status" required>
                                <option value="pendente">Pendente</option>
                                <option value="pago">Pago</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="lanc_categoria" class="form-label fw-semibold">Categoria</label>
                            <input
                                type="text"
                                class="form-control"
                                id="lanc_categoria"
                                name="categoria"
                                list="sugestoesCategorias"
                                placeholder="Ex: Fornecedores, Aluguel, Luz, Outras Receitas..."
                                required
                            >
                            <datalist id="sugestoesCategorias">
                                <option value="Fornecedores">
                                <option value="Aluguel">
                                <option value="Luz">
                                <option value="Água">
                                <option value="Internet/Telefone">
                                <option value="Folha de Pagamento">
                                <option value="Impostos">
                                <option value="Manutenção">
                                <option value="Outras Receitas">
                            </datalist>
                        </div>

                        <div class="col-12" id="grupoFornecedorModal">
                            <label for="lanc_fornecedor_id" class="form-label fw-semibold">Fornecedor (opcional)</label>
                            <select class="form-select" id="lanc_fornecedor_id" name="fornecedor_id">
                                <option value="">Nenhum / Lançamento Geral</option>
                                <?php foreach ($fornecedores as $forn): ?>
                                    <option value="<?= (int) $forn['id'] ?>">
                                        <?= htmlspecialchars((string) $forn['nome'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="lanc_descricao" class="form-label fw-semibold">Descrição</label>
                            <input type="text" class="form-control" id="lanc_descricao" name="descricao" required>
                        </div>

                        <div class="col-md-6">
                            <label for="lanc_valor" class="form-label fw-semibold">Valor (R$)</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="lanc_valor" name="valor" required>
                        </div>

                        <div class="col-md-6">
                            <label for="lanc_vencimento" class="form-label fw-semibold">Data de Vencimento</label>
                            <input type="date" class="form-control" id="lanc_vencimento" name="data_vencimento" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">Salvar Lançamento</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Auditoria de Cupons PDV -->
    <div class="modal fade" id="modalAuditoriaCupons" tabindex="-1" aria-labelledby="modalAuditoriaCuponsLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color: #3c096c;">
                    <h5 class="modal-title fw-bold" id="modalAuditoriaCuponsLabel">
                        <i class="bi bi-receipt-cutoff me-2"></i>Auditoria de Cupons PDV (Vendas Individuais do Caixa)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive" style="max-height: 480px;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3">Cupom</th>
                                    <th>Data / Hora</th>
                                    <th>Terminal / Turno</th>
                                    <th>Operador</th>
                                    <th>Pagamento</th>
                                    <th class="text-end">Valor Total</th>
                                    <th class="text-end pe-3">Itens</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cuponsPdv)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Nenhum cupom de venda encontrado para o período.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cuponsPdv as $cupom): ?>
                                        <?php $cupomId = (int) $cupom['id']; ?>
                                        <tr>
                                            <td class="ps-3 fw-bold font-monospace">#<?= $cupomId ?></td>
                                            <td class="small text-muted">
                                                <?= !empty($cupom['criado_em']) ? date('d/m/Y H:i:s', strtotime((string) $cupom['criado_em'])) : '-' ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle">
                                                    <?= htmlspecialchars((string) ($cupom['numero_caixa'] ?? 'PDV'), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <?php if (!empty($cupom['turno_id'])): ?>
                                                    <small class="text-muted ms-1">Turno #<?= (int) $cupom['turno_id'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars((string) ($cupom['operador_nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars((string) $cupom['forma_pagamento'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td class="text-end fw-bold text-success">
                                                R$ <?= number_format((float) $cupom['valor_total'], 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-ver-cupom" data-cupom-id="<?= $cupomId ?>">
                                                    <i class="bi bi-eye me-1"></i>Ver Itens
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Painel de Detalhes do Cupom Selecionado dentro da Modal -->
                    <div id="painelDetalhesCupom" class="border-top bg-light p-3 d-none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong id="tituloCupomDetalhe" class="text-primary"></strong>
                            <small id="metaCupomDetalhe" class="text-muted"></small>
                        </div>
                        <div class="table-responsive bg-white border rounded">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Produto</th>
                                        <th class="text-center">Qtd</th>
                                        <th class="text-end">Unitário</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="corpoItensCupomDetalhe"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            // =========================================================================
            // 1. ATALHOS PARA MODAL DE LANÇAMENTO (RECEITA vs DESPESA/BOLETO)
            // =========================================================================
            const selectTipo         = document.getElementById('lanc_tipo');
            const inputCategoria     = document.getElementById('lanc_categoria');
            const grupoFornecedor    = document.getElementById('grupoFornecedorModal');
            const selectFornecedor   = document.getElementById('lanc_fornecedor_id');
            const tituloModalLanc    = document.getElementById('modalLancamentoLabel');

            function configurarModalLancamento(tipo) {
                selectTipo.value = tipo;
                if (tipo === 'receita') {
                    tituloModalLanc.innerHTML = '<i class="bi bi-plus-circle me-2 text-primary"></i>Lançar Nova Receita';
                    inputCategoria.value = 'Outras Receitas';
                    selectFornecedor.value = '';
                    grupoFornecedor.classList.add('d-none');
                } else {
                    tituloModalLanc.innerHTML = '<i class="bi bi-file-earmark-minus me-2 text-danger"></i>Lançar Despesa / Boleto';
                    inputCategoria.value = '';
                    grupoFornecedor.classList.remove('d-none');
                }
            }

            document.getElementById('btnAtalhoReceita').addEventListener('click', () => configurarModalLancamento('receita'));
            document.getElementById('btnAtalhoDespesa').addEventListener('click', () => configurarModalLancamento('despesa'));
            selectTipo.addEventListener('change', function () {
                grupoFornecedor.classList.toggle('d-none', this.value === 'receita');
            });

            // =========================================================================
            // 2. EXPORTAR RELATÓRIO PDF (window.print)
            // =========================================================================
            document.getElementById('btnExportarPdf').addEventListener('click', function () {
                window.print();
            });

            // =========================================================================
            // 3. AUDITORIA DE CUPONS PDV
            // =========================================================================
            const cupons           = <?= $cuponsJson !== false ? $cuponsJson : '[]' ?>;
            const painelDetalhes   = document.getElementById('painelDetalhesCupom');
            const tituloCupomDet   = document.getElementById('tituloCupomDetalhe');
            const metaCupomDet     = document.getElementById('metaCupomDetalhe');
            const corpoItensDet    = document.getElementById('corpoItensCupomDetalhe');

            function fmtMoeda(v) {
                return 'R$ ' + Number(v).toFixed(2).replace('.', ',');
            }

            document.querySelectorAll('.btn-ver-cupom').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = parseInt(this.dataset.cupomId || '0', 10);
                    const cupom = cupons.find(c => c.id === id);
                    if (!cupom) return;

                    painelDetalhes.classList.remove('d-none');
                    tituloCupomDet.textContent = `Cupom #${cupom.id} — Total: ${fmtMoeda(cupom.valor_total)}`;
                    metaCupomDet.textContent   = `${cupom.numero_caixa} | Operador: ${cupom.operador_nome} | ${cupom.forma_pagamento} | ${cupom.criado_em}`;
                    corpoItensDet.innerHTML    = '';

                    cupom.itens.forEach(function (it) {
                        const tr = document.createElement('tr');

                        const tdNome = document.createElement('td');
                        tdNome.className = 'fw-semibold';
                        tdNome.textContent = it.produto_nome;

                        const tdQtd = document.createElement('td');
                        tdQtd.className = 'text-center';
                        tdQtd.textContent = String(it.quantidade);

                        const tdUnit = document.createElement('td');
                        tdUnit.className = 'text-end';
                        tdUnit.textContent = fmtMoeda(it.preco_unitario);

                        const tdSub = document.createElement('td');
                        tdSub.className = 'text-end fw-bold';
                        tdSub.textContent = fmtMoeda(it.subtotal);

                        tr.appendChild(tdNome);
                        tr.appendChild(tdQtd);
                        tr.appendChild(tdUnit);
                        tr.appendChild(tdSub);
                        corpoItensDet.appendChild(tr);
                    });
                });
            });

            // =========================================================================
            // 4. GRÁFICOS CHART.JS (3 CARDS ANALÍTICOS)
            // =========================================================================
            const dadosFornecedores = <?= $fornecedoresChartJson !== false ? $fornecedoresChartJson : '[]' ?>;
            const dadosCategorias   = <?= $categoriasChartJson !== false ? $categoriasChartJson : '[]' ?>;
            const dadosFluxo        = <?= $fluxoChartJson !== false ? $fluxoChartJson : '[]' ?>;

            // Gráfico 1: Comparativo por Fornecedor (Barras Horizontais)
            const ctxForn = document.getElementById('chartFornecedores');
            if (ctxForn && dadosFornecedores.length > 0) {
                new Chart(ctxForn, {
                    type: 'bar',
                    data: {
                        labels: dadosFornecedores.map(f => f.nome.length > 18 ? f.nome.slice(0, 18) + '…' : f.nome),
                        datasets: [
                            {
                                label: 'A Pagar (Pendente)',
                                data: dadosFornecedores.map(f => f.pendente),
                                backgroundColor: '#e63946',
                                borderRadius: 4,
                            },
                            {
                                label: 'Já Pago',
                                data: dadosFornecedores.map(f => f.pago),
                                backgroundColor: '#2ec4b6',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                        },
                        scales: {
                            x: { beginAtZero: true }
                        }
                    }
                });
            }

            // Gráfico 2: Despesas por Categoria (Doughnut)
            const ctxCat = document.getElementById('chartCategorias');
            if (ctxCat && dadosCategorias.length > 0) {
                new Chart(ctxCat, {
                    type: 'doughnut',
                    data: {
                        labels: dadosCategorias.map(c => c.categoria),
                        datasets: [{
                            data: dadosCategorias.map(c => c.total),
                            backgroundColor: [
                                '#00a8e8', '#ff9f1c', '#e63946', '#2ec4b6',
                                '#778da9', '#8338ec', '#fb5607', '#3a86ff'
                            ],
                            borderWidth: 2,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                        }
                    }
                });
            }

            // Gráfico 3: Entradas vs Saídas por Data (Barras)
            const ctxFluxo = document.getElementById('chartFluxoDiario');
            if (ctxFluxo && dadosFluxo.length > 0) {
                new Chart(ctxFluxo, {
                    type: 'bar',
                    data: {
                        labels: dadosFluxo.map(d => d.label),
                        datasets: [
                            {
                                label: 'Entradas (Receitas)',
                                data: dadosFluxo.map(d => d.receitas),
                                backgroundColor: '#2ec4b6',
                                borderRadius: 4,
                            },
                            {
                                label: 'Saídas (Despesas)',
                                data: dadosFluxo.map(d => d.despesas),
                                backgroundColor: '#e63946',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
        })();
    </script>
</body>
</html>