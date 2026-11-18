<?php
$usuarioLogado = $_SESSION['usuario'] ?? ['nome' => 'Usuário', 'perfil' => 'estoque'];
$perfil = (string)($usuarioLogado['perfil'] ?? '');

$sucesso = $_SESSION['sucesso'] ?? null;
$erro    = $_SESSION['erro'] ?? null;
unset($_SESSION['sucesso'], $_SESSION['erro']);

$produtos     = $produtos ?? [];
$categorias   = $categorias ?? [];
$fornecedores = $fornecedores ?? [];
$giroMap      = $giroMap ?? [];

// Cálculo dos 4 KPIs Executivos diretamente em PHP a partir de $produtos e $giroMap
$capitalCusto      = 0.0;
$valorPotencial    = 0.0;
$totalUnidades     = 0;
$qtdAtencao        = 0;
$qtdSemGiro        = 0;
$qtdProdutosAtivos = 0;

foreach ($produtos as $p) {
    $id            = (int)$p['id'];
    $ativo         = !isset($p['ativo']) || (bool)$p['ativo'];
    $estoqueAtual  = max(0, (int)($p['estoque_atual'] ?? 0));
    $estoqueMinimo = (int)($p['estoque_minimo'] ?? 0);
    $precoCusto    = (float)($p['preco_custo'] ?? 0);
    $precoVenda    = (float)($p['preco_venda'] ?? 0);

    $giro = $giroMap[$id] ?? ['total_30d' => 0, 'media_diaria' => 0.0, 'duracao_estimada_dias' => null];
    $total30d = (int)($giro['total_30d'] ?? 0);
    $duracao  = $giro['duracao_estimada_dias'] ?? null;

    if ($ativo) {
        $qtdProdutosAtivos++;
        $totalUnidades  += $estoqueAtual;
        $capitalCusto   += ($estoqueAtual * $precoCusto);
        $valorPotencial += ($estoqueAtual * $precoVenda);

        if ((int)$p['estoque_atual'] <= $estoqueMinimo || ($duracao !== null && (float)$duracao < 7.0)) {
            $qtdAtencao++;
        }

        if ($total30d <= 0) {
            $qtdSemGiro++;
        }
    }
}

$lucroEstimado = $valorPotencial - $capitalCusto;
$margemMedia   = $capitalCusto > 0 ? (($lucroEstimado / $capitalCusto) * 100) : 0.0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque & Mercadorias - Sistema Supermercado</title>
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
        .table-estoque th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            white-space: nowrap;
        }
        .table-estoque td {
            vertical-align: middle;
        }
        .ean-badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.78rem;
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
            <i class="bi bi-cart4 me-2 text-info"></i>SuperMercado ERP
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
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-boxes me-1"></i>Estoque / Mercadorias
                        </a>
                        <ul class="dropdown-menu shadow">
                            <li>
                                <a class="dropdown-item active" href="index.php?rota=estoque">
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
                        <a class="nav-link" href="index.php?rota=fornecedores">
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
                    <span class="badge bg-secondary ms-1"><?= htmlspecialchars(strtoupper($perfil), ENT_QUOTES, 'UTF-8') ?></span>
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

    <!-- Cabeçalho e Barra de Ações -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-boxes text-primary me-2"></i>Catálogo de Mercadorias & Inteligência de Giro
            </h3>
            <p class="text-muted mb-0 small">
                Controle de estoque mínimo, precificação com margem e previsão de duração com base nas vendas dos últimos 30 dias.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                <i class="bi bi-tags me-1"></i>Nova Categoria
            </button>
            <button type="button" class="btn btn-primary" id="btnAbrirNovaMercadoria" data-bs-toggle="modal" data-bs-target="#modalProduto">
                <i class="bi bi-plus-circle me-1"></i>Nova Mercadoria
            </button>
            <button type="button" class="btn btn-warning text-dark fw-semibold" id="btnAbrirMovimentacao" data-bs-toggle="modal" data-bs-target="#modalMovimentar">
                <i class="bi bi-arrow-left-right me-1"></i>Movimentar Estoque
            </button>
            <a href="index.php?rota=estoque_historico" class="btn btn-outline-dark fw-semibold">
                <i class="bi bi-clock-history me-1"></i>Histórico de Movimentações
            </a>
        </div>
    </div>

    <!-- 4 Cards Executivos de Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Capital em Estoque (Custo)</span>
                        <h4 class="fw-bold text-dark mt-1 mb-1">
                            R$ <?= number_format($capitalCusto, 2, ',', '.') ?>
                        </h4>
                        <span class="small text-muted">
                            <?= number_format($totalUnidades, 0, ',', '.') ?> un em <?= $qtdProdutosAtivos ?> itens ativos
                        </span>
                    </div>
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Valor Potencial de Venda</span>
                        <h4 class="fw-bold text-success mt-1 mb-1">
                            R$ <?= number_format($valorPotencial, 2, ',', '.') ?>
                        </h4>
                        <span class="small text-success fw-semibold">
                            Lucro est.: R$ <?= number_format($lucroEstimado, 2, ',', '.') ?> (<?= number_format($margemMedia, 1, ',', '.') ?>%)
                        </span>
                    </div>
                    <div class="kpi-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm h-100 border-start border-4 border-danger">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Atenção / Reposição</span>
                        <h4 class="fw-bold text-danger mt-1 mb-1">
                            <?= $qtdAtencao ?> <?= $qtdAtencao === 1 ? 'item' : 'itens' ?>
                        </h4>
                        <span class="small text-muted">
                            Estoque &le; mínimo ou duração &lt; 7 dias
                        </span>
                    </div>
                    <div class="kpi-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-exclamation-octagon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card kpi-card shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Produtos Sem Giro (30d)</span>
                        <h4 class="fw-bold text-dark mt-1 mb-1">
                            <?= $qtdSemGiro ?> <?= $qtdSemGiro === 1 ? 'item' : 'itens' ?>
                        </h4>
                        <span class="small text-muted">
                            Nenhuma venda registrada nos últimos 30d
                        </span>
                    </div>
                    <div class="kpi-icon bg-warning bg-opacity-25 text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros Instantâneos -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted mb-1">Buscar Mercadoria ou EAN</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="filtroBusca" class="form-control" placeholder="Digite nome ou código de barras...">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Categoria</label>
                    <select id="filtroCategoria" class="form-select">
                        <option value="">Todas as Categorias</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>">
                                <?= htmlspecialchars((string)$cat['nome'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Fornecedor</label>
                    <select id="filtroFornecedor" class="form-select">
                        <option value="">Todos os Fornecedores</option>
                        <?php foreach ($fornecedores as $forn): ?>
                            <option value="<?= (int)$forn['id'] ?>">
                                <?= htmlspecialchars((string)$forn['nome'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="button" id="btnLimparFiltros" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Limpar
                    </button>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-dark filter-pill active" data-status-filter="todos">
                        Todos (<?= count($produtos) ?>)
                    </button>
                    <button type="button" class="btn btn-outline-danger filter-pill" data-status-filter="critico">
                        <i class="bi bi-exclamation-triangle me-1"></i>Estoque Baixo / Crítico (<?= $qtdAtencao ?>)
                    </button>
                    <button type="button" class="btn btn-outline-warning text-dark filter-pill" data-status-filter="sem_giro">
                        <i class="bi bi-pause-circle me-1"></i>Sem Giro 30d (<?= $qtdSemGiro ?>)
                    </button>
                </div>
                <span class="small text-muted" id="contadorResultados">
                    Exibindo <?= count($produtos) ?> de <?= count($produtos) ?> mercadorias
                </span>
            </div>
        </div>
    </div>

    <!-- Catálogo de Mercadorias -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-table me-2 text-primary"></i>Catálogo de Mercadorias
            </h5>
            <span class="badge bg-light text-dark border">
                <span class="text-danger fw-bold">&bull;</span> Linhas em vermelho indicam estoque &le; mínimo
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-estoque mb-0" id="tabelaProdutos">
                <thead class="table-light">
                    <tr>
                        <th>Mercadoria / EAN</th>
                        <th>Categoria / Fornecedor</th>
                        <th class="text-end">Custo / Venda</th>
                        <th style="min-width: 170px;">Estoque Atual / Mín.</th>
                        <th>Giro (30d) & Previsão</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($produtos)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Nenhuma mercadoria cadastrada até o momento.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($produtos as $p): ?>
                        <?php
                        $id            = (int)$p['id'];
                        $nome          = (string)$p['nome'];
                        $ean           = (string)$p['codigo_barras'];
                        $catId         = (int)($p['categoria_id'] ?? 0);
                        $catNome       = (string)($p['categoria_nome'] ?? 'Sem categoria');
                        $fornId        = (int)($p['fornecedor_id'] ?? 0);
                        $fornNome      = (string)($p['fornecedor_nome'] ?? 'Não vinculado');
                        $precoCusto    = (float)($p['preco_custo'] ?? 0);
                        $precoVenda    = (float)($p['preco_venda'] ?? 0);
                        $estoqueAtual  = (int)($p['estoque_atual'] ?? 0);
                        $estoqueMinimo = (int)($p['estoque_minimo'] ?? 0);
                        $ativo         = !isset($p['ativo']) || (bool)$p['ativo'];

                        $margemPct = $precoCusto > 0
                            ? (($precoVenda - $precoCusto) / $precoCusto) * 100
                            : 0.0;

                        $estoqueBaixo = ($estoqueAtual <= $estoqueMinimo);

                        $giro     = $giroMap[$id] ?? ['total_30d' => 0, 'media_diaria' => 0.0, 'duracao_estimada_dias' => null];
                        $total30d = (int)($giro['total_30d'] ?? 0);
                        $mediaDia = (float)($giro['media_diaria'] ?? 0.0);
                        $duracao  = $giro['duracao_estimada_dias'] ?? null;

                        $ehCritico = $estoqueBaixo || ($duracao !== null && (float)$duracao < 7.0);
                        $ehSemGiro = ($total30d <= 0);

                        // Barra de progresso visual do estoque em relação ao mínimo (meta saudável = 2x mínimo ou mínimo 10)
                        $referenciaMax = max($estoqueMinimo * 2, 10);
                        $percentualBar = min(100, max(0, (int)round(($estoqueAtual / $referenciaMax) * 100)));
                        $corBarra = $estoqueBaixo ? 'bg-danger' : ($estoqueAtual <= ($estoqueMinimo * 1.5) ? 'bg-warning' : 'bg-success');

                        $searchIndex = strtolower($nome . ' ' . $ean . ' ' . $catNome . ' ' . $fornNome);
                        ?>
                        <tr class="<?= $estoqueBaixo ? 'table-danger' : '' ?> produto-row"
                            data-search="<?= htmlspecialchars($searchIndex, ENT_QUOTES, 'UTF-8') ?>"
                            data-categoria="<?= $catId ?>"
                            data-fornecedor="<?= $fornId ?>"
                            data-critico="<?= $ehCritico ? '1' : '0' ?>"
                            data-semgiro="<?= $ehSemGiro ? '1' : '0' ?>">
                            <td>
                                <div class="fw-bold text-dark">
                                    <?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!$ativo): ?>
                                        <span class="badge bg-secondary ms-1">Inativo</span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-light text-dark border ean-badge mt-1">
                                    <i class="bi bi-upc me-1"></i><?= htmlspecialchars($ean, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                    <?= htmlspecialchars($catNome, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-truck me-1"></i><?= htmlspecialchars($fornNome, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="small text-muted">
                                    Custo: R$ <?= number_format($precoCusto, 2, ',', '.') ?>
                                </div>
                                <div class="fw-bold text-dark">
                                    Venda: R$ <?= number_format($precoVenda, 2, ',', '.') ?>
                                </div>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">
                                    Margem: <?= number_format($margemPct, 1, ',', '.') ?>%
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold <?= $estoqueBaixo ? 'text-danger' : 'text-dark' ?>">
                                        <?= $estoqueAtual ?> un
                                    </span>
                                    <span class="small text-muted">Mín: <?= $estoqueMinimo ?></span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar <?= $corBarra ?>" style="width: <?= $percentualBar ?>%"></div>
                                </div>
                                <?php if ($estoqueBaixo): ?>
                                    <span class="badge bg-danger mt-1">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Repor Estoque
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($total30d > 0): ?>
                                    <div class="small fw-semibold text-dark">
                                        <?= $total30d ?> un vendidas <span class="text-muted fw-normal">(<?= number_format($mediaDia, 1, ',', '.') ?>/dia)</span>
                                    </div>
                                    <?php if ($duracao !== null): ?>
                                        <?php
                                        $durFloat = (float)$duracao;
                                        $badgeDur = $durFloat < 7 ? 'bg-danger' : ($durFloat <= 15 ? 'bg-warning text-dark' : 'bg-success');
                                        ?>
                                        <span class="badge <?= $badgeDur ?> mt-1">
                                            Duração est.: <?= number_format($durFloat, 0, ',', '.') ?> dias
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-25 text-dark">
                                        <i class="bi bi-pause-circle me-1"></i>Sem giro (30d)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button type="button"
                                            class="btn btn-outline-primary btn-editar-produto"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalProduto"
                                            data-tooltip="true"
                                            title="Editar Mercadoria"
                                            data-id="<?= $id ?>"
                                            data-ean="<?= htmlspecialchars($ean, ENT_QUOTES, 'UTF-8') ?>"
                                            data-nome="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>"
                                            data-categoria="<?= $catId ?>"
                                            data-fornecedor="<?= $fornId ?>"
                                            data-custo="<?= number_format($precoCusto, 2, '.', '') ?>"
                                            data-venda="<?= number_format($precoVenda, 2, '.', '') ?>"
                                            data-estoque="<?= $estoqueAtual ?>"
                                            data-minimo="<?= $estoqueMinimo ?>"
                                            data-ativo="<?= $ativo ? '1' : '0' ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-outline-warning text-dark btn-movimentar-produto"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalMovimentar"
                                            data-tooltip="true"
                                            title="Entrada / Saída Manual"
                                            data-id="<?= $id ?>">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="nenhumResultadoFiltro" class="text-center py-5 text-muted d-none">
            <i class="bi bi-search fs-1 d-block mb-2"></i>
            Nenhuma mercadoria encontrada para os filtros aplicados.
        </div>
    </div>

</div>

<!-- Modal Nova Categoria -->
<div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="index.php?rota=estoque_categoria_salvar" method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-tags me-2"></i>Nova Categoria</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Nome da Categoria *</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: Bebidas, Hortifruti, Limpeza..." required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Categoria</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cadastrar / Editar Mercadoria -->
<div class="modal fade" id="modalProduto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="index.php?rota=estoque_produto_salvar" method="POST" class="modal-content">
            <input type="hidden" name="produto_id" id="prodId" value="">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalProdutoTitulo">
                    <i class="bi bi-box-seam me-2"></i>Cadastrar Nova Mercadoria
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Código de Barras *</label>
                        <input type="text" name="codigo_barras" id="prodEan" class="form-control" required placeholder="789...">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Nome da Mercadoria *</label>
                        <input type="text" name="nome" id="prodNome" class="form-control" required placeholder="Ex: Arroz Tipo 1 5kg">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Categoria *</label>
                        <select name="categoria_id" id="prodCategoria" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>">
                                    <?= htmlspecialchars((string)$cat['nome'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Fornecedor Principal</label>
                        <select name="fornecedor_id" id="prodFornecedor" class="form-select">
                            <option value="">-- Nenhum / Opcional --</option>
                            <?php foreach ($fornecedores as $forn): ?>
                                <option value="<?= (int)$forn['id'] ?>">
                                    <?= htmlspecialchars((string)$forn['nome'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Preço Custo (R$) *</label>
                        <input type="number" step="0.01" min="0" name="preco_custo" id="prodCusto" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Margem (%)</label>
                        <input type="number" step="0.01" id="prodMargem" class="form-control" placeholder="Ex: 35.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Preço Venda (R$) *</label>
                        <input type="number" step="0.01" min="0.01" name="preco_venda" id="prodVenda" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Estoque Atual *</label>
                        <input type="number" min="0" name="estoque_atual" id="prodEstoque" class="form-control" value="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Estoque Mínimo *</label>
                        <input type="number" min="0" name="estoque_minimo" id="prodMinimo" class="form-control" value="5" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Situação</label>
                        <select name="ativo" id="prodAtivo" class="form-select">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-check-lg me-1"></i>Salvar Mercadoria
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Movimentar Estoque -->
<div class="modal fade" id="modalMovimentar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="index.php?rota=estoque_movimentar" method="POST" class="modal-content">
            <input type="hidden" name="origem" value="estoque">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-arrow-left-right me-2"></i>Movimentar Estoque
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mercadoria *</label>
                    <select name="produto_id" id="movProdutoId" class="form-select" required>
                        <option value="">Selecione a mercadoria...</option>
                        <?php foreach ($produtos as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= htmlspecialchars((string)$p['nome'], ENT_QUOTES, 'UTF-8') ?> (Atual: <?= (int)$p['estoque_atual'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Tipo *</label>
                        <select name="tipo" class="form-select" required>
                            <option value="entrada">Entrada (+)</option>
                            <option value="saida">Saída (-)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Quantidade *</label>
                        <input type="number" name="quantidade" min="1" class="form-control" required>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Motivo *</label>
                    <input type="text" name="motivo" class="form-control" placeholder="Ex: Ajuste de inventário, Quebra, Bonificação..." required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning fw-semibold">Confirmar Movimentação</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Tooltips sem conflito com data-bs-toggle="modal"
    document.querySelectorAll('[data-tooltip="true"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });

    // Cálculo bidirecional Custo / Margem (%) / Preço de Venda
    const inputCusto  = document.getElementById('prodCusto');
    const inputMargem = document.getElementById('prodMargem');
    const inputVenda  = document.getElementById('prodVenda');

    function calcularVendaPelaMargem() {
        const custo  = parseFloat(inputCusto.value);
        const margem = parseFloat(inputMargem.value);
        if (!isNaN(custo) && custo >= 0 && !isNaN(margem)) {
            const venda = custo + (custo * (margem / 100));
            inputVenda.value = venda.toFixed(2);
        }
    }

    function calcularMargemPelaVenda() {
        const custo = parseFloat(inputCusto.value);
        const venda = parseFloat(inputVenda.value);
        if (!isNaN(custo) && custo > 0 && !isNaN(venda)) {
            const margem = ((venda - custo) / custo) * 100;
            inputMargem.value = margem.toFixed(2);
        } else if (!isNaN(custo) && custo === 0) {
            inputMargem.value = '';
        }
    }

    inputMargem.addEventListener('input', calcularVendaPelaMargem);
    inputCusto.addEventListener('input', () => {
        if (inputMargem.value !== '') {
            calcularVendaPelaMargem();
        } else if (inputVenda.value !== '') {
            calcularMargemPelaVenda();
        }
    });
    inputVenda.addEventListener('input', calcularMargemPelaVenda);

    // Preencher modal para Nova Mercadoria vs Editar Mercadoria
    const modalTitulo    = document.getElementById('modalProdutoTitulo');
    const prodId         = document.getElementById('prodId');
    const prodEan        = document.getElementById('prodEan');
    const prodNome       = document.getElementById('prodNome');
    const prodCategoria  = document.getElementById('prodCategoria');
    const prodFornecedor = document.getElementById('prodFornecedor');
    const prodEstoque    = document.getElementById('prodEstoque');
    const prodMinimo     = document.getElementById('prodMinimo');
    const prodAtivo      = document.getElementById('prodAtivo');

    document.getElementById('btnAbrirNovaMercadoria').addEventListener('click', () => {
        modalTitulo.innerHTML = '<i class="bi bi-box-seam me-2"></i>Cadastrar Nova Mercadoria';
        prodId.value         = '';
        prodEan.value        = '';
        prodNome.value       = '';
        prodCategoria.value  = '';
        prodFornecedor.value = '';
        inputCusto.value     = '';
        inputMargem.value    = '';
        inputVenda.value     = '';
        prodEstoque.value    = '0';
        prodMinimo.value     = '5';
        prodAtivo.value      = '1';
    });

    document.querySelectorAll('.btn-editar-produto').forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitulo.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Mercadoria';
            prodId.value         = btn.dataset.id || '';
            prodEan.value        = btn.dataset.ean || '';
            prodNome.value       = btn.dataset.nome || '';
            prodCategoria.value  = btn.dataset.categoria || '';
            prodFornecedor.value = btn.dataset.fornecedor && btn.dataset.fornecedor !== '0' ? btn.dataset.fornecedor : '';
            inputCusto.value     = btn.dataset.custo || '0.00';
            inputVenda.value     = btn.dataset.venda || '0.00';
            prodEstoque.value    = btn.dataset.estoque || '0';
            prodMinimo.value     = btn.dataset.minimo || '5';
            prodAtivo.value      = btn.dataset.ativo || '1';
            calcularMargemPelaVenda();
        });
    });

    // Pré-selecionar produto no modal de Movimentação
    const movProdutoSelect = document.getElementById('movProdutoId');
    document.getElementById('btnAbrirMovimentacao').addEventListener('click', () => {
        movProdutoSelect.value = '';
    });
    document.querySelectorAll('.btn-movimentar-produto').forEach(btn => {
        btn.addEventListener('click', () => {
            movProdutoSelect.value = btn.dataset.id || '';
        });
    });

    // Filtros instantâneos no catálogo
    const filtroBusca      = document.getElementById('filtroBusca');
    const filtroCategoria  = document.getElementById('filtroCategoria');
    const filtroFornecedor = document.getElementById('filtroFornecedor');
    const pills            = document.querySelectorAll('.filter-pill');
    const linhas           = document.querySelectorAll('.produto-row');
    const contador         = document.getElementById('contadorResultados');
    const avisoVazio       = document.getElementById('nenhumResultadoFiltro');
    let statusAtivo        = 'todos';

    function aplicarFiltros() {
        const termo      = filtroBusca.value.trim().toLowerCase();
        const catSel     = filtroCategoria.value;
        const fornSel    = filtroFornecedor.value;
        let visiveis     = 0;

        linhas.forEach(tr => {
            const matchBusca = !termo || (tr.dataset.search || '').includes(termo);
            const matchCat   = !catSel || tr.dataset.categoria === catSel;
            const matchForn  = !fornSel || tr.dataset.fornecedor === fornSel;

            let matchStatus = true;
            if (statusAtivo === 'critico') {
                matchStatus = tr.dataset.critico === '1';
            } else if (statusAtivo === 'sem_giro') {
                matchStatus = tr.dataset.semgiro === '1';
            }

            if (matchBusca && matchCat && matchForn && matchStatus) {
                tr.classList.remove('d-none');
                visiveis++;
            } else {
                tr.classList.add('d-none');
            }
        });

        contador.textContent = `Exibindo ${visiveis} de ${linhas.length} mercadorias`;
        if (linhas.length > 0 && visiveis === 0) {
            avisoVazio.classList.remove('d-none');
        } else {
            avisoVazio.classList.add('d-none');
        }
    }

    filtroBusca.addEventListener('input', aplicarFiltros);
    filtroCategoria.addEventListener('change', aplicarFiltros);
    filtroFornecedor.addEventListener('change', aplicarFiltros);

    pills.forEach(pill => {
        pill.addEventListener('click', () => {
            pills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            statusAtivo = pill.dataset.statusFilter || 'todos';
            aplicarFiltros();
        });
    });

    document.getElementById('btnLimparFiltros').addEventListener('click', () => {
        filtroBusca.value      = '';
        filtroCategoria.value  = '';
        filtroFornecedor.value = '';
        statusAtivo            = 'todos';
        pills.forEach(p => p.classList.toggle('active', p.dataset.statusFilter === 'todos'));
        aplicarFiltros();
    });
});
</script>
</body>
</html>