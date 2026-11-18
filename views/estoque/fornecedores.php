<?php
$usuarioLogado = $_SESSION['usuario'] ?? ['nome' => 'Usuário', 'perfil' => 'estoque'];
$perfil = (string)($usuarioLogado['perfil'] ?? '');

$sucesso = $_SESSION['sucesso'] ?? null;
$erro    = $_SESSION['erro'] ?? null;
unset($_SESSION['sucesso'], $_SESSION['erro']);

$fornecedores   = $fornecedores ?? [];
$produtos       = $produtos ?? [];
$compras        = $compras ?? ($pedidos ?? []);
$itensPorCompra = $itensPorCompra ?? [];

// Cálculo dos 4 KPIs Executivos diretamente em PHP a partir dos dados carregados
$qtdFornecedoresAtivos = 0;
$totalBoletosAberto    = 0.0;
$qtdParcelasAberto     = 0;
$totalPagoFornecedores = 0.0;
$qtdComBoletosAberto   = 0;

foreach ($fornecedores as $f) {
    $ativo = !isset($f['ativo']) || (bool)$f['ativo'];
    if ($ativo) {
        $qtdFornecedoresAtivos++;
    }

    $pendente    = (float)($f['total_pendente'] ?? $f['boletos_pendentes'] ?? $f['valor_pendente'] ?? 0);
    $qtdParcelas = (int)($f['qtd_pendente'] ?? $f['qtd_boletos_pendentes'] ?? $f['parcelas_pendentes'] ?? 0);
    $pago        = (float)($f['total_pago'] ?? $f['valor_pago'] ?? 0);

    $totalBoletosAberto    += $pendente;
    $qtdParcelasAberto     += $qtdParcelas;
    $totalPagoFornecedores += $pago;

    if ($pendente > 0) {
        $qtdComBoletosAberto++;
    }
}

$qtdAguardandoEntrega = 0;
$mapaItensParaJs      = [];

foreach ($compras as $c) {
    $cid    = (int)($c['id'] ?? 0);
    $status = strtolower(trim((string)($c['status'] ?? '')));
    if ($status === 'aguardando_entrega') {
        $qtdAguardandoEntrega++;
    }

    $itensCompra = $c['itens'] ?? ($itensPorCompra[$cid] ?? null);
    if (is_array($itensCompra)) {
        $mapaItensParaJs[$cid] = $itensCompra;
    }
}

$hojeIso = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fornecedores & Compras - Sistema Supermercado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
        }
        .kpi-card {
            border: none;
            border-radius: 12px;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .08) !important;
        }
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        .table-erp th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            white-space: nowrap;
        }
        .table-erp td {
            vertical-align: middle;
        }
        .pedido-aguardando {
            background-color: #fffdf5;
            border-left: 4px solid #ffc107;
        }
        .filter-pill.active {
            font-weight: 600;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="index.php?rota=dashboard">
            <i class="bi bi-cart4 me-2"></i>SuperMercado ERP
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSistema">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSistema">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php?rota=dashboard">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <?php if (in_array($perfil, ['admin', 'caixa'], true)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?rota=caixa">
                            <i class="bi bi-upc-scan me-1"></i>Frente de Caixa (PDV)
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (in_array($perfil, ['admin', 'estoque'], true)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-boxes me-1"></i>Estoque / Mercadorias
                        </a>
                        <ul class="dropdown-menu shadow">
                            <li>
                                <a class="dropdown-item" href="index.php?rota=estoque">
                                    <i class="bi bi-grid-3x3-gap me-2"></i>Catálogo & Giro
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="index.php?rota=estoque_historico">
                                    <i class="bi bi-clock-history me-2"></i>Histórico de Movimentações
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                <?php if (in_array($perfil, ['admin', 'estoque', 'financeiro'], true)): ?>
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold" href="index.php?rota=fornecedores">
                            <i class="bi bi-truck me-1"></i>Fornecedores & Compras
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (in_array($perfil, ['admin', 'financeiro'], true)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?rota=financeiro">
                            <i class="bi bi-cash-coin me-1"></i>Financeiro
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($perfil === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?rota=usuarios">
                            <i class="bi bi-people me-1"></i>Usuários
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-3 text-white">
                <span class="small">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars((string)$usuarioLogado['nome'], ENT_QUOTES, 'UTF-8') ?>
                    <span class="badge bg-light text-primary ms-1"><?= htmlspecialchars(strtoupper($perfil), ENT_QUOTES, 'UTF-8') ?></span>
                </span>
                <a href="index.php?rota=logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Sair
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <?php if ($sucesso): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars((string)$sucesso, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars((string)$erro, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Cabeçalho e Ações -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-truck text-primary me-2"></i>Fornecedores, Pedidos de Compra & Conferência
            </h3>
            <p class="text-muted mb-0 small">
                Emita pedidos de compra, confira mercadorias na entrega com corte de itens faltantes e gere boletos parcelados automaticamente.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#modalNovoFornecedor">
                <i class="bi bi-building-add me-1"></i>Novo Fornecedor
            </button>
            <button type="button" class="btn btn-primary fw-semibold" id="btnAbrirNovoPedidoGeral" data-bs-toggle="modal" data-bs-target="#modalNovoPedido">
                <i class="bi bi-cart-plus me-1"></i>Novo Pedido de Compra
            </button>
        </div>
    </div>

    <!-- 4 Cards Executivos de Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Fornecedores Ativos</span>
                        <h4 class="fw-bold text-primary mt-1 mb-1">
                            <?= $qtdFornecedoresAtivos ?>
                        </h4>
                        <span class="small text-muted">
                            De <?= count($fornecedores) ?> parceiros cadastrados
                        </span>
                    </div>
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-buildings"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Pedidos Aguardando Entrega</span>
                        <h4 class="fw-bold text-warning mt-1 mb-1">
                            <?= $qtdAguardandoEntrega ?> <?= $qtdAguardandoEntrega === 1 ? 'pedido' : 'pedidos' ?>
                        </h4>
                        <span class="small text-muted">
                            Prontos para conferência e entrada
                        </span>
                    </div>
                    <div class="kpi-icon bg-warning bg-opacity-25 text-warning">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm h-100 border-start border-4 border-danger">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Boletos a Pagar (Aberto)</span>
                        <h4 class="fw-bold text-danger mt-1 mb-1">
                            R$ <?= number_format($totalBoletosAberto, 2, ',', '.') ?>
                        </h4>
                        <span class="small text-muted">
                            <?= $qtdParcelasAberto > 0 ? $qtdParcelasAberto . ' parcelas pendentes' : $qtdComBoletosAberto . ' fornecedores com saldo' ?>
                        </span>
                    </div>
                    <div class="kpi-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-receipt-cutoff"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total Pago a Fornecedores</span>
                        <h4 class="fw-bold text-success mt-1 mb-1">
                            R$ <?= number_format($totalPagoFornecedores, 2, ',', '.') ?>
                        </h4>
                        <span class="small text-success">
                            Histórico acumulado liquidado
                        </span>
                    </div>
                    <div class="kpi-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Busca Rápida e Pills de Fornecedores -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="filtroFornecedorBusca" class="form-control" placeholder="Buscar fornecedor por nome, CNPJ ou contato...">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-dark filter-pill active" data-forn-filter="todos">
                            Todos (<?= count($fornecedores) ?>)
                        </button>
                        <button type="button" class="btn btn-outline-danger filter-pill" data-forn-filter="com_boletos">
                            <i class="bi bi-exclamation-circle me-1"></i>Com Boletos a Pagar (<?= $qtdComBoletosAberto ?>)
                        </button>
                    </div>
                </div>
                <div class="col-12 col-md-3 text-md-end">
                    <span class="small text-muted" id="contadorFornecedores">
                        Exibindo <?= count($fornecedores) ?> de <?= count($fornecedores) ?> fornecedores
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela: Fornecedores Cadastrados -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-building text-primary me-2"></i>Fornecedores Cadastrados
            </h5>
            <span class="badge bg-light text-dark border">Gestão Financeira por Parceiro</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-erp mb-0" id="tabelaFornecedores">
                <thead class="table-light">
                    <tr>
                        <th>Fornecedor</th>
                        <th>CNPJ</th>
                        <th>Contato / Telefone</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Boletos a Pagar</th>
                        <th class="text-end">Total Já Pago</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($fornecedores)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-buildings fs-1 d-block mb-2"></i>
                            Nenhum fornecedor cadastrado até o momento.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($fornecedores as $f): ?>
                        <?php
                        $fid         = (int)$f['id'];
                        $fnome       = (string)($f['nome'] ?? '');
                        $fcnpj       = (string)($f['cnpj'] ?? '');
                        $ftelefone   = (string)($f['telefone'] ?? '');
                        $fcontato    = (string)($f['contato'] ?? '');
                        $fativo      = !isset($f['ativo']) || (bool)$f['ativo'];
                        $pendente    = (float)($f['total_pendente'] ?? $f['boletos_pendentes'] ?? $f['valor_pendente'] ?? 0);
                        $qtdParcelas = (int)($f['qtd_pendente'] ?? $f['qtd_boletos_pendentes'] ?? $f['parcelas_pendentes'] ?? 0);
                        $pago        = (float)($f['total_pago'] ?? $f['valor_pago'] ?? 0);

                        $searchIndex = strtolower($fnome . ' ' . $fcnpj . ' ' . $fcontato . ' ' . $ftelefone);
                        ?>
                        <tr class="fornecedor-row <?= !$fativo ? 'table-light text-muted' : '' ?>"
                            data-search="<?= htmlspecialchars($searchIndex, ENT_QUOTES, 'UTF-8') ?>"
                            data-pendente="<?= $pendente > 0 ? '1' : '0' ?>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <i class="bi bi-shop"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">
                                            <?= htmlspecialchars($fnome, ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <span class="small text-muted">ID #<?= $fid ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="font-monospace small bg-light px-2 py-1 rounded border text-dark">
                                    <?= $fcnpj !== '' ? htmlspecialchars($fcnpj, ENT_QUOTES, 'UTF-8') : 'Não informado' ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small">
                                    <i class="bi bi-person me-1 text-muted"></i><?= $fcontato !== '' ? htmlspecialchars($fcontato, ENT_QUOTES, 'UTF-8') : '-' ?>
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-telephone me-1"></i><?= $ftelefone !== '' ? htmlspecialchars($ftelefone, ENT_QUOTES, 'UTF-8') : '-' ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($fativo): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i>Ativo
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-1">
                                        <i class="bi bi-pause-circle-fill me-1"></i>Inativo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($pendente > 0): ?>
                                    <div class="fw-bold text-danger">
                                        R$ <?= number_format($pendente, 2, ',', '.') ?>
                                    </div>
                                    <?php if ($qtdParcelas > 0): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                            <?= $qtdParcelas ?> <?= $qtdParcelas === 1 ? 'parcela' : 'parcelas' ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">R$ 0,00</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold <?= $pago > 0 ? 'text-success' : 'text-muted' ?>">
                                    R$ <?= number_format($pago, 2, ',', '.') ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <?php if ($fativo): ?>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary btn-novo-pedido-fornecedor"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalNovoPedido"
                                                data-fornecedor-id="<?= $fid ?>">
                                            <i class="bi bi-cart-plus me-1"></i>Novo Pedido
                                        </button>
                                    <?php endif; ?>
                                    <form action="index.php?rota=fornecedor_alternar_status" method="POST" class="d-inline">
                                        <input type="hidden" name="fornecedor_id" value="<?= $fid ?>">
                                        <input type="hidden" name="id" value="<?= $fid ?>">
                                        <input type="hidden" name="ativo" value="<?= $fativo ? '0' : '1' ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= $fativo ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                                data-tooltip="true"
                                                title="<?= $fativo ? 'Desativar Fornecedor' : 'Reativar Fornecedor' ?>">
                                            <i class="bi <?= $fativo ? 'bi-toggle-on' : 'bi-toggle-off' ?> me-1"></i><?= $fativo ? 'Desativar' : 'Ativar' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="nenhumFornecedorFiltro" class="text-center py-5 text-muted d-none">
            <i class="bi bi-search fs-1 d-block mb-2"></i>
            Nenhum fornecedor encontrado para o filtro aplicado.
        </div>
    </div>

    <!-- Tabela: Últimas Compras / Pedidos -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-clipboard-check text-primary me-2"></i>Últimas Compras / Pedidos
            </h5>
            <span class="small text-muted">
                Pedidos aguardando entrega exigem conferência antes de atualizar estoque e financeiro
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-erp mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Pedido #</th>
                        <th>Data / Hora</th>
                        <th>Fornecedor</th>
                        <th>Condição de Pagamento</th>
                        <th class="text-end">Valor Total</th>
                        <th class="text-center">Status</th>
                        <th>Observação / Recebimento</th>
                        <th class="text-center">Ação</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($compras)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-cart-x fs-1 d-block mb-2"></i>
                            Nenhum pedido de compra registrado até o momento.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($compras as $c): ?>
                        <?php
                        $cid            = (int)$c['id'];
                        $fornNome       = (string)($c['fornecedor_nome'] ?? 'Fornecedor');
                        $valorTotal     = (float)($c['valor_total'] ?? 0);
                        $condicao       = trim((string)($c['condicao_pagamento'] ?? 'À vista'));
                        $obs            = trim((string)($c['observacao'] ?? ''));
                        $status         = strtolower(trim((string)($c['status'] ?? 'aguardando_entrega')));
                        $dataPedidoRaw  = (string)($c['criado_em'] ?? $c['criado_at'] ?? '');
                        $dataPedido     = $dataPedidoRaw !== '' ? date('d/m/Y H:i', strtotime($dataPedidoRaw)) : '-';
                        $recebidoEmRaw  = (string)($c['recebido_em'] ?? '');
                        $recebidoEm     = $recebidoEmRaw !== '' ? date('d/m/Y H:i', strtotime($recebidoEmRaw)) : '';
                        $ehAguardando   = ($status === 'aguardando_entrega');
                        ?>
                        <tr class="<?= $ehAguardando ? 'pedido-aguardando' : '' ?>">
                            <td class="fw-bold font-monospace">#<?= $cid ?></td>
                            <td class="text-nowrap small"><?= htmlspecialchars($dataPedido, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($fornNome, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($c['usuario_nome'])): ?>
                                    <span class="small text-muted">Solicitado por: <?= htmlspecialchars((string)$c['usuario_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-normal px-2 py-1">
                                    <i class="bi bi-calendar2-week me-1 text-primary"></i><?= htmlspecialchars($condicao !== '' ? $condicao : 'À vista', ENT_QUOTES, 'UTF-8') ?> dias
                                </span>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                R$ <?= number_format($valorTotal, 2, ',', '.') ?>
                            </td>
                            <td class="text-center">
                                <?php if ($status === 'aguardando_entrega'): ?>
                                    <span class="badge bg-warning text-dark px-3 py-2">
                                        <i class="bi bi-hourglass-split me-1"></i>Aguardando Entrega
                                    </span>
                                <?php elseif ($status === 'recebido'): ?>
                                    <span class="badge bg-success px-3 py-2">
                                        <i class="bi bi-check-circle-fill me-1"></i>Recebido
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary px-3 py-2">
                                        <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($obs !== ''): ?>
                                    <div class="text-dark"><?= htmlspecialchars($obs, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                                <?php if ($recebidoEm !== ''): ?>
                                    <div class="text-success small mt-1">
                                        <i class="bi bi-check2-all me-1"></i>Conferido em <?= htmlspecialchars($recebidoEm, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($ehAguardando): ?>
                                    <button type="button"
                                            class="btn btn-success btn-sm fw-semibold shadow-sm btn-conferir-pedido"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalReceberPedido"
                                            data-compra-id="<?= $cid ?>"
                                            data-fornecedor="<?= htmlspecialchars($fornNome, ENT_QUOTES, 'UTF-8') ?>"
                                            data-condicao="<?= htmlspecialchars($condicao, ENT_QUOTES, 'UTF-8') ?>"
                                            data-observacao="<?= htmlspecialchars($obs, ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-box-arrow-in-down me-1"></i>Conferir & Receber
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="bi bi-lock-fill me-1"></i>Finalizado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal Novo Fornecedor -->
<div class="modal fade" id="modalNovoFornecedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="index.php?rota=fornecedor_salvar" method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-building-add me-2"></i>Cadastrar Novo Fornecedor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Razão Social / Nome Fantasia *</label>
                    <input type="text" name="nome" class="form-control" required placeholder="Ex: Distribuidora Alimentos Sul Ltda">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">CNPJ</label>
                    <input type="text" name="cnpj" class="form-control font-monospace" placeholder="00.000.000/0001-00">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nome do Contato / Vendedor</label>
                        <input type="text" name="contato" class="form-control" placeholder="Ex: Carlos Silva">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Telefone / WhatsApp</label>
                        <input type="text" name="telefone" class="form-control" placeholder="(11) 99999-9999">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-check-lg me-1"></i>Salvar Fornecedor
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Novo Pedido de Compra (Etapa 1 - Aguardando Entrega) -->
<div class="modal fade" id="modalNovoPedido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <form action="index.php?rota=fornecedor_comprar" method="POST" class="modal-content" id="formNovoPedido">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-cart-plus me-2"></i>Novo Pedido de Compra (Etapa 1 - Solicitação)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Este pedido ficará com status <strong>Aguardando Entrega</strong>. O estoque e os boletos no financeiro só serão lançados na conferência de recebimento.
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Fornecedor *</label>
                        <select name="fornecedor_id" id="pedidoFornecedorId" class="form-select" required>
                            <option value="">Selecione o fornecedor...</option>
                            <?php foreach ($fornecedores as $f): ?>
                                <?php if (!isset($f['ativo']) || (bool)$f['ativo']): ?>
                                    <option value="<?= (int)$f['id'] ?>">
                                        <?= htmlspecialchars((string)$f['nome'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Condição / Prazos de Pagamento (dias) *</label>
                        <input type="text" name="condicao_pagamento" id="pedidoCondicao" class="form-control" value="21/28/35/42" placeholder="Ex: 21/28/35/42 ou 30" required>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2 small preset-condicao" data-target="pedidoCondicao" data-val="0">À vista</button>
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2 small preset-condicao" data-target="pedidoCondicao" data-val="30">30d</button>
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2 small preset-condicao" data-target="pedidoCondicao" data-val="15/30/45">15/30/45</button>
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2 small preset-condicao" data-target="pedidoCondicao" data-val="21/28/35/42">21/28/35/42</button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Previsão 1º Vencimento</label>
                        <input type="date" name="data_vencimento_boleto" class="form-control" value="<?= $hojeIso ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Observação do Pedido</label>
                        <input type="text" name="observacao" class="form-control" placeholder="Ex: Pedido semanal reposição mercearia / entrega manhã">
                    </div>
                </div>

                <!-- Seleção Rápida para Adicionar Item ao Pedido -->
                <div class="card bg-light border mb-3">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold mb-1">Adicionar Mercadoria</label>
                                <select id="selProdutoPedido" class="form-select">
                                    <option value="">Selecione um produto...</option>
                                    <?php foreach ($produtos as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"
                                                data-nome="<?= htmlspecialchars((string)$p['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-custo="<?= number_format((float)($p['preco_custo'] ?? 0), 2, '.', '') ?>"
                                                data-estoque="<?= (int)($p['estoque_atual'] ?? 0) ?>">
                                            <?= htmlspecialchars((string)$p['nome'], ENT_QUOTES, 'UTF-8') ?> (Est: <?= (int)($p['estoque_atual'] ?? 0) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold mb-1">Qtd Pedida</label>
                                <input type="number" id="inputQtdPedido" min="1" value="1" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold mb-1">Custo Unit. (R$)</label>
                                <input type="number" id="inputCustoPedido" step="0.01" min="0.01" class="form-control" placeholder="0.00">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="button" id="btnAdicionarItemPedido" class="btn btn-dark fw-semibold">
                                    <i class="bi bi-plus-lg me-1"></i>Incluir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0" id="tabelaItensNovoPedido">
                        <thead class="table-light">
                            <tr>
                                <th>Mercadoria</th>
                                <th class="text-center" style="width: 120px;">Quantidade</th>
                                <th class="text-end" style="width: 150px;">Custo Unitário (R$)</th>
                                <th class="text-end" style="width: 150px;">Subtotal (R$)</th>
                                <th class="text-center" style="width: 60px;">Remover</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyItensNovoPedido">
                            <tr id="rowPedidoVazio">
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Nenhum item adicionado ao pedido ainda.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <th colspan="3" class="text-end">VALOR TOTAL ESTIMADO DO PEDIDO:</th>
                                <th class="text-end fs-5" id="totalNovoPedido">R$ 0,00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-semibold" id="btnSalvarNovoPedido">
                    <i class="bi bi-send-check me-1"></i>Registrar Pedido de Compra
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Conferir & Receber Mercadoria (Etapa 2 - Entrada no Estoque & Geração de Boletos) -->
<div class="modal fade" id="modalReceberPedido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <form action="index.php?rota=fornecedor_receber" method="POST" class="modal-content" id="formReceberPedido">
            <input type="hidden" name="compra_id" id="recCompraId" value="">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-box-arrow-in-down me-2"></i>Conferência & Recebimento do Pedido <span id="recTituloId"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded border h-100">
                            <span class="small text-muted d-block">Fornecedor</span>
                            <strong class="fs-6 text-dark" id="recFornecedorNome">-</strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Condição de Pagamento (dias) *</label>
                        <input type="text" name="condicao_pagamento" id="recCondicao" class="form-control" required placeholder="Ex: 21/28/35/42">
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2 small preset-condicao-rec" data-val="0">À vista</button>
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2 small preset-condicao-rec" data-val="30">30d</button>
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2 small preset-condicao-rec" data-val="21/28/35/42">21/28/35/42</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Data Base Faturamento / Recebimento *</label>
                        <input type="date" name="data_vencimento_boleto" id="recDataBase" class="form-control" value="<?= $hojeIso ?>" required>
                    </div>
                </div>

                <div class="alert alert-warning py-2 small mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Confira fisicamente cada item. Se o fornecedor entregou menos unidades que o pedido, reduza a <strong>Qtd Recebida</strong> para aplicar o corte automático antes de gerar os boletos.
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mercadoria</th>
                                <th class="text-center" style="width: 110px;">Qtd Pedida</th>
                                <th class="text-center" style="width: 160px;">Qtd Recebida *</th>
                                <th class="text-end" style="width: 140px;">Custo Unit. (R$)</th>
                                <th class="text-end" style="width: 150px;">Subtotal Real</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyItensRecebimento">
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Carregando itens do pedido...
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <th colspan="4" class="text-end">VALOR FINAL CONFERIDO (A GERAR EM BOLETOS):</th>
                                <th class="text-end fs-5" id="recValorTotalReal">R$ 0,00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Prévia das Parcelas / Boletos -->
                <div class="card border-info bg-info bg-opacity-10">
                    <div class="card-body py-2 px-3">
                        <div class="fw-semibold small text-dark mb-1">
                            <i class="bi bi-calendar-check me-1 text-primary"></i>Prévia de Boletos a Lançar no Financeiro:
                        </div>
                        <div id="recPreviewParcelas" class="d-flex flex-wrap gap-2 small"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success fw-semibold" id="btnConfirmarRecebimento">
                    <i class="bi bi-check2-circle me-1"></i>Confirmar Entrada no Estoque & Gerar Boletos
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const itensPorCompraCache = <?= json_encode($mapaItensParaJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

document.addEventListener('DOMContentLoaded', () => {
    // Tooltips sem conflito com modal triggers
    document.querySelectorAll('[data-tooltip="true"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });

    // =========================================================================
    // 1. FILTROS INSTANTÂNEOS NA TABELA DE FORNECEDORES
    // =========================================================================
    const filtroBusca = document.getElementById('filtroFornecedorBusca');
    const pillsForn   = document.querySelectorAll('.filter-pill');
    const linhasForn  = document.querySelectorAll('.fornecedor-row');
    const contador    = document.getElementById('contadorFornecedores');
    const avisoVazio  = document.getElementById('nenhumFornecedorFiltro');
    let modoFiltro    = 'todos';

    function filtrarFornecedores() {
        const termo = filtroBusca.value.trim().toLowerCase();
        let visiveis = 0;

        linhasForn.forEach(tr => {
            const matchBusca = !termo || (tr.dataset.search || '').includes(termo);
            const matchTipo  = modoFiltro === 'todos' || tr.dataset.pendente === '1';

            if (matchBusca && matchTipo) {
                tr.classList.remove('d-none');
                visiveis++;
            } else {
                tr.classList.add('d-none');
            }
        });

        contador.textContent = `Exibindo ${visiveis} de ${linhasForn.length} fornecedores`;
        if (linhasForn.length > 0 && visiveis === 0) {
            avisoVazio.classList.remove('d-none');
        } else {
            avisoVazio.classList.add('d-none');
        }
    }

    filtroBusca.addEventListener('input', filtrarFornecedores);
    pillsForn.forEach(pill => {
        pill.addEventListener('click', () => {
            pillsForn.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            modoFiltro = pill.dataset.fornFilter || 'todos';
            filtrarFornecedores();
        });
    });

    // =========================================================================
    // 2. MODAL NOVO PEDIDO DE COMPRA (ETAPA 1)
    // =========================================================================
    const selectFornPedido = document.getElementById('pedidoFornecedorId');
    const selProdutoPedido = document.getElementById('selProdutoPedido');
    const inputQtdPedido   = document.getElementById('inputQtdPedido');
    const inputCustoPedido = document.getElementById('inputCustoPedido');
    const tbodyNovoPedido  = document.getElementById('tbodyItensNovoPedido');
    const rowPedidoVazio   = document.getElementById('rowPedidoVazio');
    const totalNovoPedido  = document.getElementById('totalNovoPedido');
    const formNovoPedido   = document.getElementById('formNovoPedido');

    document.getElementById('btnAbrirNovoPedidoGeral').addEventListener('click', () => {
        selectFornPedido.value = '';
    });

    document.querySelectorAll('.btn-novo-pedido-fornecedor').forEach(btn => {
        btn.addEventListener('click', () => {
            selectFornPedido.value = btn.dataset.fornecedorId || '';
        });
    });

    document.querySelectorAll('.preset-condicao').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (target) target.value = btn.dataset.val;
        });
    });

    selProdutoPedido.addEventListener('change', () => {
        const opt = selProdutoPedido.selectedOptions[0];
        if (opt && opt.value) {
            inputCustoPedido.value = opt.dataset.custo || '0.00';
            inputQtdPedido.focus();
        } else {
            inputCustoPedido.value = '';
        }
    });

    function formatarMoeda(valor) {
        return 'R$ ' + Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcularTotalNovoPedido() {
        const linhas = tbodyNovoPedido.querySelectorAll('tr.item-pedido-row');
        let soma = 0;

        linhas.forEach(tr => {
            const qtd   = parseInt(tr.querySelector('.inp-qtd-pedido').value || '0', 10);
            const custo = parseFloat(tr.querySelector('.inp-custo-pedido').value || '0');
            const sub   = Math.max(0, qtd) * Math.max(0, custo);
            tr.querySelector('.col-subtotal-pedido').textContent = formatarMoeda(sub);
            soma += sub;
        });

        rowPedidoVazio.classList.toggle('d-none', linhas.length > 0);
        totalNovoPedido.textContent = formatarMoeda(soma);
    }

    document.getElementById('btnAdicionarItemPedido').addEventListener('click', () => {
        const opt = selProdutoPedido.selectedOptions[0];
        if (!opt || !opt.value) {
            selProdutoPedido.focus();
            return;
        }

        const prodId   = opt.value;
        const prodNome = opt.dataset.nome || opt.textContent;
        const qtd      = Math.max(1, parseInt(inputQtdPedido.value || '1', 10));
        const custo    = Math.max(0.01, parseFloat(inputCustoPedido.value || opt.dataset.custo || '0.01'));

        // Se já existir na lista, apenas soma a quantidade
        const existente = tbodyNovoPedido.querySelector(`tr.item-pedido-row[data-produto-id="${prodId}"]`);
        if (existente) {
            const inpQtd = existente.querySelector('.inp-qtd-pedido');
            inpQtd.value = parseInt(inpQtd.value || '0', 10) + qtd;
            existente.querySelector('.inp-custo-pedido').value = custo.toFixed(2);
            recalcularTotalNovoPedido();
        } else {
            const tr = document.createElement('tr');
            tr.className = 'item-pedido-row';
            tr.dataset.produtoId = prodId;
            tr.innerHTML = `
                <td>
                    <span class="fw-semibold text-dark"></span>
                    <input type="hidden" name="produto_id[]" value="">
                </td>
                <td>
                    <input type="number" name="quantidade[]" min="1" class="form-control form-control-sm text-center inp-qtd-pedido" required>
                </td>
                <td>
                    <input type="number" name="preco_custo[]" step="0.01" min="0.01" class="form-control form-control-sm text-end inp-custo-pedido" required>
                </td>
                <td class="text-end fw-bold col-subtotal-pedido">R$ 0,00</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remover-item-pedido">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tr.querySelector('span').textContent = prodNome;
            tr.querySelector('input[name="produto_id[]"]').value = prodId;
            tr.querySelector('.inp-qtd-pedido').value = qtd;
            tr.querySelector('.inp-custo-pedido').value = custo.toFixed(2);

            tr.querySelector('.inp-qtd-pedido').addEventListener('input', recalcularTotalNovoPedido);
            tr.querySelector('.inp-custo-pedido').addEventListener('input', recalcularTotalNovoPedido);
            tr.querySelector('.btn-remover-item-pedido').addEventListener('click', () => {
                tr.remove();
                recalcularTotalNovoPedido();
            });

            tbodyNovoPedido.appendChild(tr);
            recalcularTotalNovoPedido();
        }

        selProdutoPedido.value = '';
        inputQtdPedido.value   = '1';
        inputCustoPedido.value = '';
    });

    formNovoPedido.addEventListener('submit', (e) => {
        if (tbodyNovoPedido.querySelectorAll('tr.item-pedido-row').length === 0) {
            e.preventDefault();
            selProdutoPedido.focus();
        }
    });

    // =========================================================================
    // 3. MODAL CONFERÊNCIA & RECEBIMENTO (ETAPA 2 - CORTE + BOLETOS PARCELADOS)
    // =========================================================================
    const recCompraId       = document.getElementById('recCompraId');
    const recTituloId       = document.getElementById('recTituloId');
    const recFornecedorNome = document.getElementById('recFornecedorNome');
    const recCondicao       = document.getElementById('recCondicao');
    const recDataBase       = document.getElementById('recDataBase');
    const tbodyRecebimento  = document.getElementById('tbodyItensRecebimento');
    const recValorTotalReal = document.getElementById('recValorTotalReal');
    const recPreviewParc    = document.getElementById('recPreviewParcelas');

    document.querySelectorAll('.preset-condicao-rec').forEach(btn => {
        btn.addEventListener('click', () => {
            recCondicao.value = btn.dataset.val;
            atualizarTotaisRecebimento();
        });
    });

    function calcularPreviewParcelas(totalReais) {
        const condTexto = recCondicao.value.trim();
        const partes    = condTexto.split('/').map(s => parseInt(s.trim(), 10)).filter(n => !isNaN(n) && n >= 0);
        const prazos    = partes.length > 0 ? partes : [0];
        const qtdParc   = prazos.length;

        const totalCentavos = Math.round(totalReais * 100);
        const baseCent      = Math.floor(totalCentavos / qtdParc);
        const resto         = totalCentavos % qtdParc;

        const dataBaseStr = recDataBase.value || '<?= $hojeIso ?>';
        const dataBaseObj = new Date(dataBaseStr + 'T12:00:00');

        recPreviewParc.innerHTML = '';

        if (totalCentavos <= 0) {
            recPreviewParc.innerHTML = '<span class="text-muted">Nenhum boleto será gerado com valor R$ 0,00.</span>';
            return;
        }

        prazos.forEach((dias, idx) => {
            const centParcela = baseCent + (idx < resto ? 1 : 0);
            const valorParcela = centParcela / 100;

            const venc = new Date(dataBaseObj.getTime());
            venc.setDate(venc.getDate() + dias);
            const vencFmt = venc.toLocaleDateString('pt-BR');

            const badge = document.createElement('span');
            badge.className = 'badge bg-white text-dark border border-info px-2 py-1';
            badge.textContent = `${idx + 1}/${qtdParc} (${dias}d - ${vencFmt}): ${formatarMoeda(valorParcela)}`;
            recPreviewParc.appendChild(badge);
        });
    }

    function atualizarTotaisRecebimento() {
        let total = 0;
        tbodyRecebimento.querySelectorAll('tr.rec-item-row').forEach(tr => {
            const pedida   = parseInt(tr.dataset.pedida || '0', 10);
            const inpRec   = tr.querySelector('.inp-qtd-recebida');
            let recebida   = parseInt(inpRec.value || '0', 10);
            if (isNaN(recebida) || recebida < 0) recebida = 0;
            if (recebida > pedida) recebida = pedida;
            inpRec.value = recebida;

            const custo = parseFloat(tr.dataset.custo || '0');
            const sub   = recebida * custo;
            tr.querySelector('.col-rec-subtotal').textContent = formatarMoeda(sub);

            const badgeCorte = tr.querySelector('.badge-corte');
            if (recebida < pedida) {
                badgeCorte.textContent = `Corte: -${pedida - recebida} un`;
                badgeCorte.classList.remove('d-none');
            } else {
                badgeCorte.classList.add('d-none');
            }

            total += sub;
        });

        recValorTotalReal.textContent = formatarMoeda(total);
        calcularPreviewParcelas(total);
    }

    function renderizarItensRecebimento(itens) {
        tbodyRecebimento.innerHTML = '';

        if (!Array.isArray(itens) || itens.length === 0) {
            tbodyRecebimento.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-danger">
                        Não foi possível localizar os itens deste pedido.
                    </td>
                </tr>
            `;
            atualizarTotaisRecebimento();
            return;
        }

        itens.forEach(item => {
            const itemId   = item.id;
            const prodNome = item.produto_nome || item.nome || ('Produto #' + item.produto_id);
            const pedida   = parseInt(item.quantidade || 0, 10);
            const custo    = parseFloat(item.preco_custo_unitario || item.preco_custo || 0);

            const tr = document.createElement('tr');
            tr.className = 'rec-item-row';
            tr.dataset.pedida = pedida;
            tr.dataset.custo  = custo.toFixed(2);
            tr.innerHTML = `
                <td>
                    <div class="fw-semibold text-dark nome-item"></div>
                    <span class="badge bg-warning text-dark badge-corte d-none mt-1"></span>
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary">${pedida} un</span>
                </td>
                <td class="text-center">
                    <input type="number"
                           name="quantidade_recebida[${itemId}]"
                           min="0"
                           max="${pedida}"
                           value="${pedida}"
                           class="form-control form-control-sm text-center fw-bold inp-qtd-recebida"
                           required>
                </td>
                <td class="text-end">${formatarMoeda(custo)}</td>
                <td class="text-end fw-bold col-rec-subtotal">${formatarMoeda(pedida * custo)}</td>
            `;
            tr.querySelector('.nome-item').textContent = prodNome;
            tr.querySelector('.inp-qtd-recebida').addEventListener('input', atualizarTotaisRecebimento);
            tbodyRecebimento.appendChild(tr);
        });

        atualizarTotaisRecebimento();
    }

    recCondicao.addEventListener('input', atualizarTotaisRecebimento);
    recDataBase.addEventListener('change', atualizarTotaisRecebimento);

    document.querySelectorAll('.btn-conferir-pedido').forEach(btn => {
        btn.addEventListener('click', async () => {
            const compraId = parseInt(btn.dataset.compraId || '0', 10);
            recCompraId.value             = compraId;
            recTituloId.textContent       = `#${compraId}`;
            recFornecedorNome.textContent = btn.dataset.fornecedor || '-';
            recCondicao.value             = btn.dataset.condicao || '21/28/35/42';

            if (itensPorCompraCache[compraId]) {
                renderizarItensRecebimento(itensPorCompraCache[compraId]);
                return;
            }

            tbodyRecebimento.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <div class="spinner-border spinner-border-sm text-success me-2"></div>Carregando itens do pedido...
                    </td>
                </tr>
            `;

            try {
                const resp = await fetch(`index.php?rota=fornecedor_compra_detalhes&compra_id=${compraId}&id=${compraId}`);
                const data = await resp.json();
                const lista = Array.isArray(data) ? data : (data.itens || []);
                itensPorCompraCache[compraId] = lista;
                renderizarItensRecebimento(lista);
            } catch (err) {
                tbodyRecebimento.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4 text-danger">
                            Erro ao carregar itens do pedido. Tente novamente.
                        </td>
                    </tr>
                `;
            }
        });
    });
});
</script>
</body>
</html>