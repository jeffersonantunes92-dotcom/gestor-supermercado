<?php
declare(strict_types=1);

AuthController::verificarPermissao(['admin', 'caixa', 'estoque', 'financeiro']);

$usuarioLogado = $_SESSION['usuario'];
$nomeUsuario   = htmlspecialchars((string) $usuarioLogado['nome'], ENT_QUOTES, 'UTF-8');
$perfilUsuario = (string) $usuarioLogado['perfil'];
$perfilSeguro  = htmlspecialchars(strtoupper($perfilUsuario), ENT_QUOTES, 'UTF-8');

$podeCaixa      = in_array($perfilUsuario, ['admin', 'caixa'], true);
$podeEstoque    = in_array($perfilUsuario, ['admin', 'estoque'], true);
$podeFinanceiro = in_array($perfilUsuario, ['admin', 'financeiro'], true);
$podeFornecedor = in_array($perfilUsuario, ['admin', 'estoque', 'financeiro'], true);
$isAdmin        = $perfilUsuario === 'admin';

$serieFaturamentoJson = json_encode(
    $dashboard['serie_faturamento_7d'] ?? [],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
$meiosPagamentoJson = json_encode(
    $dashboard['meios_pagamento'] ?? [],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Executivo - Supermercado ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body.erp-dashboard {
            background-color: #f3f6fa;
        }
        .erp-card {
            border: 0;
            border-radius: 0.9rem;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .erp-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.09);
        }
        .kpi-icon-circle {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            flex-shrink: 0;
        }
        .shortcut-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.9rem;
            background-color: #ffffff;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
        }
        .shortcut-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(13, 110, 253, 0.10);
            border-color: #0d6efd;
            color: inherit;
        }
        .chart-box {
            position: relative;
            height: 270px;
            width: 100%;
        }
    </style>
</head>
<body class="erp-dashboard">
    <!-- Barra Superior Limpa -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
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
                        <a class="nav-link active fw-semibold" href="index.php?rota=dashboard">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>

                    <?php if ($podeCaixa): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?rota=caixa">
                                <i class="bi bi-cash-register me-1"></i>Caixa PDV
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($podeEstoque): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?rota=estoque">
                                <i class="bi bi-box-seam me-1"></i>Estoque/Mercadorias
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($podeFornecedor): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?rota=fornecedores">
                                <i class="bi bi-truck me-1"></i>Fornecedores
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($podeFinanceiro): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?rota=financeiro">
                                <i class="bi bi-wallet2 me-1"></i>Financeiro
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
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
        <!-- Cabeçalho Executivo -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">Visão Executiva</h1>
                <p class="text-muted mb-0">
                    Olá, <strong><?= $nomeUsuario ?></strong> &bull; <?= date('d/m/Y') ?> &bull; Perfil: <span class="fw-semibold"><?= $perfilSeguro ?></span>
                </p>
            </div>
            <?php if ($podeCaixa): ?>
                <a href="index.php?rota=caixa" class="btn btn-success btn-lg fw-semibold shadow-sm px-4">
                    <i class="bi bi-upc-scan me-2"></i>Abrir Frente de Caixa (PDV)
                </a>
            <?php endif; ?>
        </div>

        <!-- Atalhos Grandes e Amigáveis para Perfis Restritos (ex: caixa, estoque, financeiro) -->
        <?php if (!$isAdmin): ?>
            <section class="mb-4">
                <h2 class="h6 text-uppercase text-muted fw-bold mb-3">
                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Acesso Rápido aos Seus Módulos
                </h2>
                <div class="row g-3">
                    <?php if ($podeCaixa): ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <a href="index.php?rota=caixa" class="shortcut-card p-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="kpi-icon-circle bg-success bg-opacity-10 text-success">
                                        <i class="bi bi-cash-register"></i>
                                    </div>
                                    <div>
                                        <div class="h5 fw-bold mb-1">Frente de Caixa (PDV)</div>
                                        <div class="small text-muted">Abrir terminal, registrar vendas, sangria e fechamento</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($podeEstoque): ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <a href="index.php?rota=estoque" class="shortcut-card p-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="kpi-icon-circle bg-primary bg-opacity-10 text-primary">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                    <div>
                                        <div class="h5 fw-bold mb-1">Estoque &amp; Mercadorias</div>
                                        <div class="small text-muted">Cadastrar produtos, preços, giro de 30d e ajustes</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($podeFornecedor): ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <a href="index.php?rota=fornecedores" class="shortcut-card p-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="kpi-icon-circle bg-warning bg-opacity-10 text-warning">
                                        <i class="bi bi-truck"></i>
                                    </div>
                                    <div>
                                        <div class="h5 fw-bold mb-1">Fornecedores &amp; Pedidos</div>
                                        <div class="small text-muted">Emitir pedidos de reposição e conferir entregas</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($podeFinanceiro): ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <a href="index.php?rota=financeiro" class="shortcut-card p-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="kpi-icon-circle bg-info bg-opacity-10 text-info">
                                        <i class="bi bi-wallet2"></i>
                                    </div>
                                    <div>
                                        <div class="h5 fw-bold mb-1">Gestão Financeira</div>
                                        <div class="small text-muted">Boletos, fluxo de caixa, fechamentos e auditoria</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- 1. KPIs DO TOPO (Respeitando permissões por perfil) -->
        <section class="row g-3 mb-4">
            <?php if ($isAdmin || $podeCaixa || $podeFinanceiro): ?>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card erp-card h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <span class="text-muted small text-uppercase fw-bold">Faturamento Hoje</span>
                                <div class="h3 fw-bold text-primary mt-1 mb-0">
                                    R$ <?= number_format((float) $dashboard['faturamento_hoje'], 2, ',', '.') ?>
                                </div>
                                <small class="text-muted">
                                    <?= (int) $dashboard['vendas_hoje_qtd'] ?> cupom(ns) emitido(s)
                                </small>
                            </div>
                            <div class="kpi-icon-circle bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isAdmin || $podeFinanceiro): ?>
                <?php $saldo = (float) $dashboard['saldo_tesouraria']; ?>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card erp-card h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <span class="text-muted small text-uppercase fw-bold">Saldo Tesouraria</span>
                                <div class="h3 fw-bold mt-1 mb-0 <?= $saldo >= 0 ? 'text-success' : 'text-danger' ?>">
                                    R$ <?= number_format($saldo, 2, ',', '.') ?>
                                </div>
                                <small class="text-muted">Receitas pagas − Despesas pagas</small>
                            </div>
                            <div class="kpi-icon-circle bg-success bg-opacity-10 text-success">
                                <i class="bi bi-bank2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isAdmin || $podeEstoque): ?>
                <?php $qtdAlertas = (int) $dashboard['alertas_estoque']; ?>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card erp-card h-100 <?= $qtdAlertas > 0 ? 'border border-danger-subtle' : '' ?>">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <span class="text-muted small text-uppercase fw-bold">Alertas de Estoque</span>
                                <div class="h3 fw-bold mt-1 mb-0 <?= $qtdAlertas > 0 ? 'text-danger' : 'text-dark' ?>">
                                    <?= $qtdAlertas ?>
                                </div>
                                <small class="<?= $qtdAlertas > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                    <?= $qtdAlertas > 0 ? 'Itens no mínimo ou esgotados' : 'Estoque regularizado' ?>
                                </small>
                            </div>
                            <div class="kpi-icon-circle bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isAdmin || $podeFinanceiro): ?>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card erp-card h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <span class="text-muted small text-uppercase fw-bold">Boletos Próximos (7d)</span>
                                <div class="h3 fw-bold text-warning mt-1 mb-0">
                                    R$ <?= number_format((float) $dashboard['boletos_7d_valor'], 2, ',', '.') ?>
                                </div>
                                <small class="text-muted">
                                    <?= (int) $dashboard['boletos_7d_qtd'] ?> boleto(s) vencendo em até 7 dias
                                </small>
                            </div>
                            <div class="kpi-icon-circle bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-calendar2-week-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- 2. GRÁFICOS INTERATIVOS (Admin & Financeiro) -->
        <?php if ($isAdmin || $podeFinanceiro): ?>
            <section class="row g-3 mb-4">
                <div class="col-12 col-lg-8">
                    <div class="card erp-card h-100">
                        <div class="card-header bg-white border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="h6 fw-bold mb-0">Faturamento Diário — Últimos 7 Dias</h2>
                                <small class="text-muted">Evolução das vendas registradas no PDV</small>
                            </div>
                            <span class="badge bg-primary bg-opacity-10 text-primary">7 dias</span>
                        </div>
                        <div class="card-body">
                            <div class="chart-box">
                                <canvas id="chartFaturamento7d"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="card erp-card h-100">
                        <div class="card-header bg-white border-0 pt-3 pb-0">
                            <h2 class="h6 fw-bold mb-0">Meios de Pagamento</h2>
                            <small class="text-muted">Participação por forma de pagamento</small>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center">
                            <?php if (empty($dashboard['meios_pagamento'])): ?>
                                <div class="text-center text-muted py-5 small">
                                    Nenhuma venda registrada ainda para exibir o gráfico.
                                </div>
                            <?php else: ?>
                                <div class="chart-box">
                                    <canvas id="chartMeiosPagamento"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- 3. WIDGETS INFERIORES -->
        <section class="row g-3">
            <!-- Widget 1: Monitor de Caixas em Tempo Real -->
            <?php if ($isAdmin || $podeCaixa || $podeFinanceiro): ?>
                <div class="col-12 <?= ($isAdmin || $podeEstoque || $podeFornecedor) ? 'col-lg-6' : '' ?>">
                    <div class="card erp-card h-100">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="h6 fw-bold mb-0">
                                    <i class="bi bi-display me-2 text-success"></i>Monitor de Caixas em Tempo Real
                                </h2>
                                <small class="text-muted">Terminais atualmente com turno aberto</small>
                            </div>
                            <?php if ($podeCaixa): ?>
                                <a href="index.php?rota=caixa" class="btn btn-sm btn-outline-success fw-semibold">
                                    Ir ao PDV
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Terminal</th>
                                            <th>Operador</th>
                                            <th class="text-end">Vendido no Turno</th>
                                            <th class="text-end pe-3">Aberto às</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($dashboard['caixas_abertos'])): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">
                                                    Nenhum terminal de caixa aberto no momento.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($dashboard['caixas_abertos'] as $cx): ?>
                                                <tr>
                                                    <td class="ps-3">
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">
                                                            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                                                            <?= htmlspecialchars((string) $cx['numero_caixa'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                        <small class="text-muted ms-1">#<?= (int) $cx['id'] ?></small>
                                                    </td>
                                                    <td class="fw-semibold"><?= htmlspecialchars((string) $cx['operador_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-end fw-bold text-success">
                                                        R$ <?= number_format((float) $cx['total_vendas'], 2, ',', '.') ?>
                                                    </td>
                                                    <td class="text-end pe-3 small text-muted">
                                                        <?= !empty($cx['aberto_em']) ? date('H:i', strtotime((string) $cx['aberto_em'])) : '-' ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Widget 2: Atenção no Estoque & Compras -->
            <?php if ($isAdmin || $podeEstoque || $podeFornecedor): ?>
                <div class="col-12 <?= ($isAdmin || $podeCaixa || $podeFinanceiro) ? 'col-lg-6' : '' ?>">
                    <div class="card erp-card h-100">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="h6 fw-bold mb-0">
                                    <i class="bi bi-exclamation-diamond-fill me-2 text-danger"></i>Atenção no Estoque &amp; Compras
                                </h2>
                                <small class="text-muted">Produtos no mínimo e pedidos aguardando conferência</small>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if ($podeEstoque): ?>
                                    <a href="index.php?rota=estoque" class="btn btn-sm btn-outline-danger fw-semibold">
                                        Estoque
                                    </a>
                                <?php endif; ?>
                                <?php if ($podeFornecedor): ?>
                                    <a href="index.php?rota=fornecedores" class="btn btn-sm btn-warning text-dark fw-semibold">
                                        Conferir / Comprar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <h3 class="small text-uppercase fw-bold text-danger mb-2">
                                Produtos em Estoque Mínimo / Crítico
                            </h3>
                            <div class="table-responsive mb-3 border rounded">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-2">Produto</th>
                                            <th class="text-center">Atual</th>
                                            <th class="text-center pe-2">Mínimo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($dashboard['produtos_criticos'])): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-2 small">
                                                    Nenhum produto abaixo do estoque mínimo.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($dashboard['produtos_criticos'] as $prodCrit): ?>
                                                <tr class="table-danger">
                                                    <td class="ps-2 fw-semibold"><?= htmlspecialchars((string) $prodCrit['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-center fw-bold text-danger"><?= (int) $prodCrit['estoque_atual'] ?></td>
                                                    <td class="text-center pe-2"><?= (int) $prodCrit['estoque_minimo'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <h3 class="small text-uppercase fw-bold text-primary mb-2">
                                Pedidos de Compra Aguardando Entrega
                            </h3>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-2">Pedido</th>
                                            <th>Fornecedor</th>
                                            <th class="text-end pe-2">Valor Previsto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($dashboard['pedidos_aguardando'])): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-2 small">
                                                    Nenhum pedido aguardando entrega no momento.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($dashboard['pedidos_aguardando'] as $ped): ?>
                                                <tr>
                                                    <td class="ps-2 fw-bold font-monospace">#<?= (int) $ped['id'] ?></td>
                                                    <td class="fw-semibold"><?= htmlspecialchars((string) $ped['fornecedor_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-end pe-2 fw-bold text-primary">
                                                        R$ <?= number_format((float) $ped['valor_total'], 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($isAdmin || $podeFinanceiro): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const serieFaturamento = <?= $serieFaturamentoJson !== false ? $serieFaturamentoJson : '[]' ?>;
            const meiosPagamento   = <?= $meiosPagamentoJson !== false ? $meiosPagamentoJson : '[]' ?>;

            // Gráfico 1: Linha/Área Suave - Faturamento dos últimos 7 dias
            const ctxFat = document.getElementById('chartFaturamento7d');
            if (ctxFat && serieFaturamento.length > 0) {
                const ctx2d = ctxFat.getContext('2d');
                const gradiente = ctx2d.createLinearGradient(0, 0, 0, 260);
                gradiente.addColorStop(0, 'rgba(13, 110, 253, 0.30)');
                gradiente.addColorStop(1, 'rgba(13, 110, 253, 0.02)');

                new Chart(ctxFat, {
                    type: 'line',
                    data: {
                        labels: serieFaturamento.map(item => item.label),
                        datasets: [{
                            label: 'Faturamento (R$)',
                            data: serieFaturamento.map(item => item.total),
                            borderColor: '#0d6efd',
                            backgroundColor: gradiente,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#0d6efd',
                            borderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            }

            // Gráfico 2: Rosca (Doughnut) - Meios de Pagamento
            const ctxPag = document.getElementById('chartMeiosPagamento');
            if (ctxPag && meiosPagamento.length > 0) {
                new Chart(ctxPag, {
                    type: 'doughnut',
                    data: {
                        labels: meiosPagamento.map(item => item.forma),
                        datasets: [{
                            data: meiosPagamento.map(item => item.total),
                            backgroundColor: ['#2ec4b6', '#00a8e8', '#ff9f1c', '#778da9', '#e63946'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '64%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, padding: 14 }
                            }
                        }
                    }
                });
            }
        })();
    </script>
    <?php endif; ?>
</body>
</html>