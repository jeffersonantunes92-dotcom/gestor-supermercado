<?php
$usuarioLogado = $_SESSION['usuario'] ?? ['nome' => 'Usuário', 'perfil' => 'estoque'];
$perfil = (string)($usuarioLogado['perfil'] ?? '');

$sucesso = $_SESSION['sucesso'] ?? null;
$erro    = $_SESSION['erro'] ?? null;
unset($_SESSION['sucesso'], $_SESSION['erro']);

$produtos      = $produtos ?? [];
$movimentacoes = $movimentacoes ?? [];

$totalRegistros = count($movimentacoes);
$totalEntradas  = 0;
$totalSaidas    = 0;

foreach ($movimentacoes as $m) {
    $tipo = strtolower((string)($m['tipo'] ?? ''));
    $qtd  = (int)($m['quantidade'] ?? 0);
    if ($tipo === 'entrada') {
        $totalEntradas += $qtd;
    } elseif ($tipo === 'saida') {
        $totalSaidas += $qtd;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Movimentações - Sistema Supermercado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
        }
        .kpi-card {
            border: none;
            border-radius: 12px;
        }
        .table-historico th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            white-space: nowrap;
        }
        .table-historico td {
            vertical-align: middle;
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
                                <a class="dropdown-item" href="index.php?rota=estoque">
                                    <i class="bi bi-grid-3x3-gap me-2"></i>Catálogo & Giro
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item active" href="index.php?rota=estoque_historico">
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

    <!-- Cabeçalho -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-clock-history text-primary me-2"></i>Histórico de Movimentações de Estoque
            </h3>
            <p class="text-muted mb-0 small">
                Auditoria completa de entradas (compras/ajustes) e saídas (vendas PDV/baixas manuais).
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php?rota=estoque" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Voltar ao Catálogo
            </a>
            <button type="button" class="btn btn-warning text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#modalMovimentarHistorico">
                <i class="bi bi-arrow-left-right me-1"></i>Nova Movimentação
            </button>
        </div>
    </div>

    <!-- Resumo Dinâmico das Movimentações Filtradas -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card kpi-card shadow-sm border-start border-4 border-primary">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Movimentações Listadas</span>
                        <h4 class="fw-bold text-dark mt-1 mb-0" id="kpiTotalRegistros"><?= $totalRegistros ?></h4>
                    </div>
                    <i class="bi bi-journal-text fs-2 text-primary opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card kpi-card shadow-sm border-start border-4 border-success">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total de Entradas</span>
                        <h4 class="fw-bold text-success mt-1 mb-0" id="kpiTotalEntradas">+<?= $totalEntradas ?> un</h4>
                    </div>
                    <i class="bi bi-box-arrow-in-down fs-2 text-success opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card kpi-card shadow-sm border-start border-4 border-danger">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total de Saídas</span>
                        <h4 class="fw-bold text-danger mt-1 mb-0" id="kpiTotalSaidas">-<?= $totalSaidas ?> un</h4>
                    </div>
                    <i class="bi bi-box-arrow-up fs-2 text-danger opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros Funcionais -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted mb-1">Produto</label>
                    <select id="filtroProduto" class="form-select">
                        <option value="">Todos os Produtos</option>
                        <?php foreach ($produtos as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= htmlspecialchars((string)$p['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$p['codigo_barras'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Tipo</label>
                    <select id="filtroTipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="entrada">Entrada</option>
                        <option value="saida">Saída</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Data</label>
                    <input type="date" id="filtroData" class="form-control">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Motivo / Responsável</label>
                    <input type="text" id="filtroTexto" class="form-control" placeholder="Buscar...">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="button" id="btnHoje" class="btn btn-outline-primary flex-fill" title="Filtrar movimentações de hoje">
                        Hoje
                    </button>
                    <button type="button" id="btnLimparFiltrosHistorico" class="btn btn-outline-secondary flex-fill">
                        Limpar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela Completa de Movimentações -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-list-ul me-2 text-primary"></i>Registro de Movimentações
            </h5>
            <span class="small text-muted" id="contadorHistorico">
                Exibindo <?= $totalRegistros ?> de <?= $totalRegistros ?> registros
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-striped table-historico mb-0" id="tabelaHistorico">
                <thead class="table-light">
                    <tr>
                        <th>Data / Hora</th>
                        <th>Mercadoria</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-end">Quantidade</th>
                        <th>Motivo</th>
                        <th>Responsável</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($movimentacoes)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                            Nenhuma movimentação de estoque registrada até o momento.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movimentacoes as $mov): ?>
                        <?php
                        $dataBruta   = (string)($mov['criado_em'] ?? $mov['criado_em'] ?? '');
                        $timestamp   = $dataBruta !== '' ? strtotime($dataBruta) : false;
                        $dataIso     = $timestamp ? date('Y-m-d', $timestamp) : '';
                        $dataExibida = $timestamp ? date('d/m/Y H:i:s', $timestamp) : '-';

                        $produtoId   = (int)($mov['produto_id'] ?? 0);
                        $produtoNome = (string)($mov['produto_nome'] ?? 'Mercadoria #' . $produtoId);
                        $codigoEan   = (string)($mov['codigo_barras'] ?? '');
                        $tipo        = strtolower((string)($mov['tipo'] ?? ''));
                        $quantidade  = (int)($mov['quantidade'] ?? 0);
                        $motivo      = (string)($mov['motivo'] ?? '-');
                        $responsavel = (string)($mov['usuario_nome'] ?? 'Sistema');

                        $textoBusca = strtolower($produtoNome . ' ' . $codigoEan . ' ' . $motivo . ' ' . $responsavel);
                        ?>
                        <tr class="mov-row"
                            data-produto="<?= $produtoId ?>"
                            data-tipo="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>"
                            data-data="<?= htmlspecialchars($dataIso, ENT_QUOTES, 'UTF-8') ?>"
                            data-quantidade="<?= $quantidade ?>"
                            data-search="<?= htmlspecialchars($textoBusca, ENT_QUOTES, 'UTF-8') ?>">
                            <td class="text-nowrap">
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($dataExibida, ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <?= htmlspecialchars($produtoNome, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php if ($codigoEan !== ''): ?>
                                    <span class="small text-muted font-monospace">EAN: <?= htmlspecialchars($codigoEan, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($tipo === 'entrada'): ?>
                                    <span class="badge bg-success px-3 py-2">
                                        <i class="bi bi-arrow-down-circle me-1"></i>ENTRADA
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger px-3 py-2">
                                        <i class="bi bi-arrow-up-circle me-1"></i>SAÍDA
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold <?= $tipo === 'entrada' ? 'text-success' : 'text-danger' ?>">
                                <?= $tipo === 'entrada' ? '+' : '-' ?><?= $quantidade ?> un
                            </td>
                            <td>
                                <?= htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <span class="text-dark">
                                    <i class="bi bi-person-badge me-1 text-muted"></i><?= htmlspecialchars($responsavel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="nenhumaMovimentacaoFiltro" class="text-center py-5 text-muted d-none">
            <i class="bi bi-search fs-1 d-block mb-2"></i>
            Nenhuma movimentação encontrada para os filtros selecionados.
        </div>
    </div>

</div>

<!-- Modal Nova Movimentação -->
<div class="modal fade" id="modalMovimentarHistorico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="index.php?rota=estoque_movimentar" method="POST" class="modal-content">
            <input type="hidden" name="origem" value="estoque_historico">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-arrow-left-right me-2"></i>Nova Movimentação de Estoque
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mercadoria *</label>
                    <select name="produto_id" class="form-select" required>
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
    const filtroProduto = document.getElementById('filtroProduto');
    const filtroTipo    = document.getElementById('filtroTipo');
    const filtroData    = document.getElementById('filtroData');
    const filtroTexto   = document.getElementById('filtroTexto');
    const linhas        = document.querySelectorAll('.mov-row');

    const kpiTotalRegistros = document.getElementById('kpiTotalRegistros');
    const kpiTotalEntradas  = document.getElementById('kpiTotalEntradas');
    const kpiTotalSaidas    = document.getElementById('kpiTotalSaidas');
    const contadorHistorico = document.getElementById('contadorHistorico');
    const avisoVazio        = document.getElementById('nenhumaMovimentacaoFiltro');

    function aplicarFiltros() {
        const prodSel = filtroProduto.value;
        const tipoSel = filtroTipo.value;
        const dataSel = filtroData.value;
        const texto   = filtroTexto.value.trim().toLowerCase();

        let visiveis = 0;
        let entradas = 0;
        let saidas   = 0;

        linhas.forEach(tr => {
            const matchProd  = !prodSel || tr.dataset.produto === prodSel;
            const matchTipo  = !tipoSel || tr.dataset.tipo === tipoSel;
            const matchData  = !dataSel || tr.dataset.data === dataSel;
            const matchTexto = !texto || (tr.dataset.search || '').includes(texto);

            if (matchProd && matchTipo && matchData && matchTexto) {
                tr.classList.remove('d-none');
                visiveis++;
                const qtd = parseInt(tr.dataset.quantidade || '0', 10);
                if (tr.dataset.tipo === 'entrada') {
                    entradas += qtd;
                } else if (tr.dataset.tipo === 'saida') {
                    saidas += qtd;
                }
            } else {
                tr.classList.add('d-none');
            }
        });

        kpiTotalRegistros.textContent = String(visiveis);
        kpiTotalEntradas.textContent  = `+${entradas} un`;
        kpiTotalSaidas.textContent    = `-${saidas} un`;
        contadorHistorico.textContent = `Exibindo ${visiveis} de ${linhas.length} registros`;

        if (linhas.length > 0 && visiveis === 0) {
            avisoVazio.classList.remove('d-none');
        } else {
            avisoVazio.classList.add('d-none');
        }
    }

    filtroProduto.addEventListener('change', aplicarFiltros);
    filtroTipo.addEventListener('change', aplicarFiltros);
    filtroData.addEventListener('change', aplicarFiltros);
    filtroTexto.addEventListener('input', aplicarFiltros);

    document.getElementById('btnHoje').addEventListener('click', () => {
        const hoje = new Date();
        const yyyy = hoje.getFullYear();
        const mm   = String(hoje.getMonth() + 1).padStart(2, '0');
        const dd   = String(hoje.getDate()).padStart(2, '0');
        filtroData.value = `${yyyy}-${mm}-${dd}`;
        aplicarFiltros();
    });

    document.getElementById('btnLimparFiltrosHistorico').addEventListener('click', () => {
        filtroProduto.value = '';
        filtroTipo.value    = '';
        filtroData.value    = '';
        filtroTexto.value   = '';
        aplicarFiltros();
    });
});
</script>
</body>
</html>