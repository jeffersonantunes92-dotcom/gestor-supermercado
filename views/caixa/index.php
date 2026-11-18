<?php
declare(strict_types=1);

AuthController::verificarPermissao(['admin', 'caixa']);

$usuarioLogado = $_SESSION['usuario'];
$nomeUsuario   = htmlspecialchars((string) $usuarioLogado['nome'], ENT_QUOTES, 'UTF-8');
$perfilUsuario = (string) $usuarioLogado['perfil'];
$perfilSeguro  = htmlspecialchars(strtoupper($perfilUsuario), ENT_QUOTES, 'UTF-8');

$flashSucesso = $_SESSION['flash_sucesso'] ?? null;
$flashErro    = $_SESSION['flash_erro'] ?? null;
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);

$temTurnoAberto = $turnoAberto !== null;
$turnoId        = $temTurnoAberto ? (int) $turnoAberto['id'] : 0;
$numeroCaixa    = $temTurnoAberto ? (string) $turnoAberto['numero_caixa'] : '';
$valorAbertura  = $temTurnoAberto ? (float) $turnoAberto['valor_abertura'] : 0.0;
$totalVendas    = $temTurnoAberto ? (float) $turnoAberto['total_vendas'] : 0.0;
$totalSangrias  = $temTurnoAberto ? (float) $turnoAberto['total_sangrias'] : 0.0;
$saldoLiquido   = round($totalVendas - $totalSangrias, 2);

$produtosJson = json_encode(
    array_map(static function (array $p): array {
        return [
            'id'             => (int) $p['id'],
            'codigo_barras'  => (string) ($p['codigo_barras'] ?? ''),
            'nome'           => (string) $p['nome'],
            'categoria_nome' => (string) ($p['categoria_nome'] ?? 'Geral'),
            'preco_venda'    => (float) $p['preco_venda'],
            'estoque_atual'  => (int) $p['estoque_atual'],
            'estoque_minimo' => (int) $p['estoque_minimo'],
        ];
    }, $produtos),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout PDV - Supermercado ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body.pdv-body {
            background-color: #dce6f2;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .pdv-turno-bar {
            background-color: #f8fafc;
            border-bottom: 1px solid #cbd5e1;
            font-size: 0.875rem;
        }
        .pdv-banner {
            background: linear-gradient(90deg, #0d3b66 0%, #164e85 100%);
            color: #ffffff;
            border-bottom: 3px solid #082744;
        }
        .pdv-banner-titulo {
            font-size: clamp(1.5rem, 2.8vw, 2.4rem);
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .pdv-painel {
            border: 1px solid #b8c9dc;
            border-radius: 0.45rem;
            background-color: #ffffff;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(13, 59, 102, 0.08);
        }
        .pdv-painel-header {
            background-color: #0d3b66;
            color: #ffffff;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 0.4rem 0.75rem;
        }
        .pdv-visor-numero {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0d3b66;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }
        .pdv-visor-subtotal {
            font-size: clamp(2rem, 3.2vw, 2.9rem);
            font-weight: 900;
            color: #0d3b66;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .pdv-cupom-scroll {
            height: 350px;
            overflow-y: auto;
        }
        .pdv-produtos-scroll {
            max-height: 220px;
            overflow-y: auto;
        }
        .pdv-rodape-atalhos {
            background-color: #0d3b66;
            color: #ffffff;
            font-size: 0.85rem;
            border-top: 2px solid #082744;
        }
        .pdv-tecla {
            background-color: #ffffff;
            color: #0d3b66;
            font-weight: 800;
            padding: 0.15rem 0.45rem;
            border-radius: 0.25rem;
            margin-right: 0.3rem;
        }
    </style>
</head>
<body class="pdv-body">
    <!-- Barra de Navegação Superior Padrão -->
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
                        <a class="nav-link" href="index.php?rota=dashboard">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>

                    <?php if (in_array($perfilUsuario, ['admin', 'caixa'], true)): ?>
                        <li class="nav-item">
                            <a class="nav-link active fw-semibold" href="index.php?rota=caixa">
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
                            <a class="nav-link" href="index.php?rota=financeiro">
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

    <?php if (!$temTurnoAberto): ?>
        <!-- TELA DE ABERTURA DE CAIXA (MULTI-TERMINAIS) -->
        <main class="container py-5 flex-grow-1 d-flex align-items-center justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <?php if ($flashSucesso !== null): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?= htmlspecialchars((string) $flashSucesso, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                <?php endif; ?>

                <?php if ($flashErro !== null): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars((string) $flashErro, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-lg overflow-hidden">
                    <div class="card-header py-3 text-white" style="background-color: #0d3b66;">
                        <h1 class="h5 fw-bold mb-0">
                            <i class="bi bi-unlock-fill me-2"></i>Abertura de Caixa (Seleção de Terminal)
                        </h1>
                    </div>
                    <form method="POST" action="index.php?rota=caixa_abrir" class="card-body p-4 bg-white">
                        <p class="text-muted small mb-3">
                            Operador: <strong><?= $nomeUsuario ?></strong>. Escolha o terminal disponível para iniciar seu turno de vendas.
                        </p>

                        <div class="mb-3">
                            <label for="numero_caixa" class="form-label fw-semibold">Terminal / Número do Caixa</label>
                            <select class="form-select form-select-lg" id="numero_caixa" name="numero_caixa" required>
                                <option value="">Selecione o terminal...</option>
                                <?php foreach ($terminaisLista as $terminal): ?>
                                    <?php $ocupado = $terminaisEmUso[$terminal] ?? null; ?>
                                    <option value="<?= htmlspecialchars($terminal, ENT_QUOTES, 'UTF-8') ?>" <?= $ocupado !== null ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($terminal, ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($ocupado !== null): ?>
                                            (Em uso por <?= htmlspecialchars($ocupado['operador_nome'], ENT_QUOTES, 'UTF-8') ?>)
                                        <?php else: ?>
                                            (Disponível)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="valor_abertura" class="form-label fw-semibold">Fundo de Troco / Valor de Abertura (R$)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control form-control-lg fw-bold text-primary"
                                id="valor_abertura"
                                name="valor_abertura"
                                value="0.00"
                                required
                            >
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg fw-bold">
                                <i class="bi bi-play-circle-fill me-2"></i>Abrir Turno no Terminal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    <?php else: ?>
        <!-- BARRA DISCRETA DO TURNO ATUAL -->
        <div class="pdv-turno-bar py-2 px-3 px-lg-4 shadow-sm">
            <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="badge bg-success px-2 py-1">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.55rem;"></i>
                        <?= htmlspecialchars($numeroCaixa, ENT_QUOTES, 'UTF-8') ?> &bull; Turno #<?= $turnoId ?> Aberto
                    </span>
                    <span class="text-muted">
                        Fundo: <strong class="text-dark">R$ <?= number_format($valorAbertura, 2, ',', '.') ?></strong>
                    </span>
                    <span class="text-muted">
                        Vendido no Turno:
                        <strong class="text-success" id="turnoTotalVendasTexto">
                            R$ <?= number_format($totalVendas, 2, ',', '.') ?>
                        </strong>
                    </span>
                    <span class="text-muted">
                        Sangrias:
                        <strong class="text-danger" id="turnoTotalSangriasTexto">
                            R$ <?= number_format($totalSangrias, 2, ',', '.') ?>
                        </strong>
                    </span>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalHistoricoCupons">
                        <i class="bi bi-receipt me-1"></i>Histórico de Vendas (Cupons)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#modalSangria">
                        <i class="bi bi-cash-coin me-1"></i>Sangria
                    </button>
                    <button type="button" class="btn btn-sm btn-danger fw-semibold" data-bs-toggle="modal" data-bs-target="#modalFecharCaixa">
                        <i class="bi bi-lock-fill me-1"></i>Fechar Caixa
                    </button>
                </div>
            </div>
        </div>

        <!-- Faixa Azul-Marinho de Destaque do Produto Atual / Caixa Livre -->
        <section class="pdv-banner py-2 px-4 shadow-sm">
            <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-3 text-truncate">
                    <i class="bi bi-upc-scan fs-2 opacity-75"></i>
                    <div id="bannerProdutoAtual" class="pdv-banner-titulo text-truncate">CAIXA LIVRE</div>
                </div>
                <div class="text-end small opacity-75 d-none d-md-block">
                    <div><?= htmlspecialchars($numeroCaixa, ENT_QUOTES, 'UTF-8') ?> &bull; OPERADOR: <strong><?= $nomeUsuario ?></strong></div>
                    <div id="bannerStatusItens">Aguardando leitura de mercadoria</div>
                </div>
            </div>
        </section>

        <!-- Área Principal do Checkout -->
        <main class="container-fluid px-3 px-lg-4 py-3 flex-grow-1">
            <?php if ($flashSucesso !== null): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm mb-3" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars((string) $flashSucesso, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <?php if ($flashErro !== null): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars((string) $flashErro, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <div id="areaAlertaPdv"></div>

            <div class="row g-3">
                <!-- COLUNA ESQUERDA (4 colunas) -->
                <div class="col-12 col-lg-4 d-flex flex-column gap-3">
                    <div class="pdv-painel">
                        <div class="pdv-painel-header">
                            <i class="bi bi-upc-scan me-1"></i>Código de Barras / Busca Rápida
                        </div>
                        <div class="p-3 bg-white">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-primary-subtle">
                                    <i class="bi bi-search text-primary"></i>
                                </span>
                                <input
                                    type="text"
                                    id="buscaProduto"
                                    class="form-control border-primary-subtle fw-semibold"
                                    placeholder="Bipe ou digite e tecle Enter..."
                                    autocomplete="off"
                                    autofocus
                                >
                                <button type="button" id="btnLimparBusca" class="btn btn-outline-secondary" title="Limpar busca">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-4 col-lg-12">
                            <div class="pdv-painel">
                                <div class="pdv-painel-header">Valor Unitário</div>
                                <div class="px-3 py-2 text-end bg-white">
                                    <span id="visorValorUnitario" class="pdv-visor-numero">0,00</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-4 col-lg-6">
                            <div class="pdv-painel">
                                <div class="pdv-painel-header">Quantidade</div>
                                <div class="px-3 py-2 text-end bg-white">
                                    <span id="visorQuantidadeItem" class="pdv-visor-numero">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-4 col-lg-6">
                            <div class="pdv-painel">
                                <div class="pdv-painel-header">Total do Item</div>
                                <div class="px-3 py-2 text-end bg-white">
                                    <span id="visorTotalItem" class="pdv-visor-numero text-success">0,00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pdv-painel flex-grow-1">
                        <div class="pdv-painel-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-grid me-1"></i>Acesso Rápido (Mercadorias)</span>
                            <span id="contadorProdutosVisiveis" class="badge bg-light text-dark">0</span>
                        </div>
                        <div class="p-2 pdv-produtos-scroll bg-light">
                            <div id="listaRapidaProdutos" class="d-grid gap-2"></div>
                        </div>
                    </div>
                </div>

                <!-- COLUNA DIREITA (8 colunas) -->
                <div class="col-12 col-lg-8 d-flex flex-column gap-3">
                    <div class="pdv-painel">
                        <div class="pdv-painel-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-receipt me-1"></i>Lista de Produtos</span>
                            <button type="button" id="btnLimparCarrinho" class="btn btn-sm btn-outline-light py-0 px-2" style="font-size: 0.72rem;">
                                <i class="bi bi-trash me-1"></i>Limpar (ESC)
                            </button>
                        </div>
                        <div class="pdv-cupom-scroll bg-white">
                            <table class="table table-sm table-striped table-hover align-middle mb-0">
                                <thead class="table-light sticky-top border-bottom">
                                    <tr class="small text-uppercase text-secondary">
                                        <th class="ps-3" style="width: 60px;">Item</th>
                                        <th>Cód. Barras</th>
                                        <th>Descrição</th>
                                        <th class="text-center" style="width: 145px;">Qtd</th>
                                        <th class="text-end">Vlr. Unit</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-center pe-3" style="width: 55px;">Rem.</th>
                                    </tr>
                                </thead>
                                <tbody id="corpoCarrinho"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pdv-painel">
                        <div class="pdv-painel-header d-flex justify-content-between align-items-center">
                            <span>Subtotal</span>
                            <span id="resumoQtdItens" class="fw-normal opacity-75">0 item(ns)</span>
                        </div>
                        <div class="px-4 py-2 bg-white d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold fs-4">R$</span>
                            <span id="totalGeralTexto" class="pdv-visor-subtotal">0,00</span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="pdv-painel h-100">
                                <div class="pdv-painel-header">Forma de Pagamento</div>
                                <div class="p-2 bg-white">
                                    <select id="formaPagamento" class="form-select form-select-lg fw-semibold border-0 bg-light">
                                        <option value="dinheiro">Dinheiro</option>
                                        <option value="pix">PIX</option>
                                        <option value="cartao_credito">Cartão de Crédito</option>
                                        <option value="cartao_debito">Cartão de Débito</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="pdv-painel h-100">
                                <div class="pdv-painel-header">Total Recebido (R$)</div>
                                <div class="p-2 bg-white">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        id="valorRecebido"
                                        class="form-control form-control-lg text-end fw-bold border-0 bg-light text-primary"
                                        placeholder="0,00"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="pdv-painel h-100">
                                <div class="pdv-painel-header">Troco (R$)</div>
                                <div class="px-3 py-2 text-end bg-white d-flex align-items-center justify-content-end" style="min-height: 58px;">
                                    <span id="valorTrocoTexto" class="pdv-visor-numero text-success">0,00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="button" id="btnFinalizarVenda" class="btn btn-success btn-lg py-3 fw-bold fs-4 shadow">
                            <i class="bi bi-check2-circle me-2"></i>FINALIZAR VENDA (F4)
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <!-- Rodapé com Barra de Atalhos -->
        <footer class="pdv-rodape-atalhos py-2 px-4 mt-auto">
            <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <a href="#" id="atalhoF2" class="text-white text-decoration-none">
                        <span class="pdv-tecla">F2</span>Buscar Produto
                    </a>
                    <span class="opacity-50">|</span>
                    <a href="#" id="atalhoF4" class="text-white text-decoration-none">
                        <span class="pdv-tecla">F4</span>Finalizar Venda
                    </a>
                    <span class="opacity-50">|</span>
                    <a href="#" id="atalhoEsc" class="text-white text-decoration-none">
                        <span class="pdv-tecla">ESC</span>Cancelar / Limpar Carrinho
                    </a>
                </div>
                <div class="small opacity-75">
                    <?= htmlspecialchars($numeroCaixa, ENT_QUOTES, 'UTF-8') ?> &bull; Turno #<?= $turnoId ?>
                </div>
            </div>
        </footer>

        <!-- Modal: Registrar Sangria -->
        <div class="modal fade" id="modalSangria" tabindex="-1" aria-labelledby="modalSangriaLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="index.php?rota=caixa_sangria" class="modal-content">
                    <input type="hidden" name="turno_id" value="<?= $turnoId ?>">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold" id="modalSangriaLabel">
                            <i class="bi bi-cash-coin me-2"></i>Sangria - <?= htmlspecialchars($numeroCaixa, ENT_QUOTES, 'UTF-8') ?> (Turno #<?= $turnoId ?>)
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">
                            A sangria será deduzida do fechamento final do turno e lançada imediatamente no Financeiro.
                        </p>
                        <div class="mb-3">
                            <label for="sangria_valor" class="form-label fw-semibold">Valor da Retirada (R$)</label>
                            <input type="number" step="0.01" min="0.01" class="form-control form-control-lg fw-bold" id="sangria_valor" name="valor" required>
                        </div>
                        <div class="mb-3">
                            <label for="sangria_motivo" class="form-label fw-semibold">Motivo / Destino</label>
                            <input type="text" class="form-control" id="sangria_motivo" name="motivo" placeholder="Ex: Retirada para cofre, Pagamento frete" required>
                        </div>

                        <?php if (!empty($sangriasTurno)): ?>
                            <div class="border-top pt-2 mt-3">
                                <span class="small fw-semibold text-muted d-block mb-1">Sangrias já registradas neste turno:</span>
                                <ul class="list-unstyled small mb-0">
                                    <?php foreach ($sangriasTurno as $san): ?>
                                        <li class="d-flex justify-content-between">
                                            <span><?= htmlspecialchars((string) $san['motivo'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <strong class="text-danger">R$ <?= number_format((float) $san['valor'], 2, ',', '.') ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning fw-bold">Confirmar Sangria</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Fechar Caixa (Resumo Consolidado) -->
        <div class="modal fade" id="modalFecharCaixa" tabindex="-1" aria-labelledby="modalFecharCaixaLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="index.php?rota=caixa_fechar" class="modal-content">
                    <input type="hidden" name="turno_id" value="<?= $turnoId ?>">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold" id="modalFecharCaixaLabel">
                            <i class="bi bi-lock-fill me-2"></i>Fechamento - <?= htmlspecialchars($numeroCaixa, ENT_QUOTES, 'UTF-8') ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">
                            Confira os totais do <strong>Turno #<?= $turnoId ?></strong> (Operador: <?= $nomeUsuario ?>). Ao confirmar, o turno será encerrado e o saldo líquido será enviado como receita única ao Financeiro.
                        </p>
                        <ul class="list-group mb-3">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Fundo de Abertura:</span>
                                <strong>R$ <?= number_format($valorAbertura, 2, ',', '.') ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total Vendido no Turno:</span>
                                <strong class="text-success" id="modalFecharTotalVendas">R$ <?= number_format($totalVendas, 2, ',', '.') ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>(-) Total de Sangrias Já Enviadas:</span>
                                <strong class="text-danger">R$ <?= number_format($totalSangrias, 2, ',', '.') ?></strong>
                            </li>
                            <li class="list-group-item list-group-item-primary d-flex justify-content-between fs-5">
                                <span class="fw-bold">Saldo Líquido a Lançar:</span>
                                <strong id="modalFecharSaldoLiquido">R$ <?= number_format($saldoLiquido, 2, ',', '.') ?></strong>
                            </li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                        <button type="submit" class="btn btn-danger fw-bold">Encerrar Turno e Enviar ao Financeiro</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Histórico de Vendas (Cupons do Turno) -->
        <div class="modal fade" id="modalHistoricoCupons" tabindex="-1" aria-labelledby="modalHistoricoCuponsLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="modalHistoricoCuponsLabel">
                            <i class="bi bi-receipt me-2 text-primary"></i>Cupons Emitidos - <?= htmlspecialchars($numeroCaixa, ENT_QUOTES, 'UTF-8') ?> (Turno #<?= $turnoId ?>)
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Cupom</th>
                                        <th>Hora</th>
                                        <th>Pagamento</th>
                                        <th class="text-end pe-3">Valor Total</th>
                                    </tr>
                                </thead>
                                <tbody id="corpoHistoricoCupons">
                                    <?php if (empty($cuponsTurno)): ?>
                                        <tr id="linhaSemCupons">
                                            <td colspan="4" class="text-center text-muted py-4">
                                                Nenhuma venda registrada neste turno ainda.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cuponsTurno as $cupom): ?>
                                            <tr>
                                                <td class="ps-3 fw-bold font-monospace">#<?= (int) $cupom['id'] ?></td>
                                                <td class="small text-muted"><?= !empty($cupom['criado_em']) ? date('H:i:s', strtotime((string) $cupom['criado_em'])) : '-' ?></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars((string) $cupom['forma_pagamento'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                <td class="text-end pe-3 fw-bold text-success">R$ <?= number_format((float) $cupom['valor_total'], 2, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($temTurnoAberto): ?>
    <script>
        (function () {
            const produtos = <?= $produtosJson !== false ? $produtosJson : '[]' ?>;
            const carrinho = new Map();
            let ultimoProdutoId = null;
            let totalSangriasAtual = <?= json_encode($totalSangrias) ?>;

            const buscaInput           = document.getElementById('buscaProduto');
            const btnLimparBusca       = document.getElementById('btnLimparBusca');
            const listaRapidaProdutos  = document.getElementById('listaRapidaProdutos');
            const contadorVisiveis     = document.getElementById('contadorProdutosVisiveis');

            const bannerProdutoAtual   = document.getElementById('bannerProdutoAtual');
            const bannerStatusItens    = document.getElementById('bannerStatusItens');
            const visorValorUnitario   = document.getElementById('visorValorUnitario');
            const visorQuantidadeItem  = document.getElementById('visorQuantidadeItem');
            const visorTotalItem       = document.getElementById('visorTotalItem');

            const corpoCarrinho        = document.getElementById('corpoCarrinho');
            const btnLimparCarrinho    = document.getElementById('btnLimparCarrinho');
            const totalGeralTexto      = document.getElementById('totalGeralTexto');
            const resumoQtdItens       = document.getElementById('resumoQtdItens');
            const formaPagamentoSelect = document.getElementById('formaPagamento');
            const valorRecebidoInput   = document.getElementById('valorRecebido');
            const valorTrocoTexto      = document.getElementById('valorTrocoTexto');
            const btnFinalizarVenda    = document.getElementById('btnFinalizarVenda');
            const areaAlertaPdv        = document.getElementById('areaAlertaPdv');

            const turnoTotalVendasTexto   = document.getElementById('turnoTotalVendasTexto');
            const modalFecharTotalVendas  = document.getElementById('modalFecharTotalVendas');
            const modalFecharSaldoLiquido = document.getElementById('modalFecharSaldoLiquido');
            const corpoHistoricoCupons    = document.getElementById('corpoHistoricoCupons');

            function formatarNumero(valor) {
                return Number(valor).toFixed(2).replace('.', ',');
            }

            function exibirAlerta(tipo, mensagem) {
                areaAlertaPdv.innerHTML = '';
                const div = document.createElement('div');
                div.className = `alert alert-${tipo} alert-dismissible fade show shadow-sm mb-3`;
                div.setAttribute('role', 'alert');

                const texto = document.createElement('span');
                texto.className = 'fw-semibold';
                texto.textContent = mensagem;

                const btnFechar = document.createElement('button');
                btnFechar.type = 'button';
                btnFechar.className = 'btn-close';
                btnFechar.setAttribute('data-bs-dismiss', 'alert');
                btnFechar.setAttribute('aria-label', 'Fechar');

                div.appendChild(texto);
                div.appendChild(btnFechar);
                areaAlertaPdv.appendChild(div);
            }

            function atualizarVisoresUltimoItem() {
                if (carrinho.size === 0) {
                    ultimoProdutoId = null;
                    bannerProdutoAtual.textContent  = 'CAIXA LIVRE';
                    bannerStatusItens.textContent   = 'Aguardando leitura de mercadoria';
                    visorValorUnitario.textContent  = '0,00';
                    visorQuantidadeItem.textContent = '0';
                    visorTotalItem.textContent      = '0,00';
                    return;
                }

                if (!ultimoProdutoId || !carrinho.has(ultimoProdutoId)) {
                    const chaves = Array.from(carrinho.keys());
                    ultimoProdutoId = chaves[chaves.length - 1];
                }

                const itemAtual = carrinho.get(ultimoProdutoId);
                if (!itemAtual) return;

                const totalItem = itemAtual.produto.preco_venda * itemAtual.quantidade;
                bannerProdutoAtual.textContent  = itemAtual.produto.nome;
                bannerStatusItens.textContent   = `Cód: ${itemAtual.produto.codigo_barras || 'S/N'} | Estoque disp.: ${itemAtual.produto.estoque_atual} un.`;
                visorValorUnitario.textContent  = formatarNumero(itemAtual.produto.preco_venda);
                visorQuantidadeItem.textContent = String(itemAtual.quantidade);
                visorTotalItem.textContent      = formatarNumero(totalItem);
            }

            function renderizarListaRapida(termo = '') {
                const filtro = termo.trim().toLowerCase();
                const filtrados = produtos.filter(function (p) {
                    if (!filtro) return true;
                    return p.nome.toLowerCase().includes(filtro) ||
                           p.codigo_barras.toLowerCase().includes(filtro);
                });

                contadorVisiveis.textContent = String(filtrados.length);
                listaRapidaProdutos.innerHTML = '';

                if (filtrados.length === 0) {
                    const vazio = document.createElement('div');
                    vazio.className = 'text-center text-muted small py-3';
                    vazio.textContent = 'Nenhuma mercadoria encontrada.';
                    listaRapidaProdutos.appendChild(vazio);
                    return;
                }

                filtrados.forEach(function (produto) {
                    const semEstoque     = produto.estoque_atual <= 0;
                    const estoqueCritico = produto.estoque_atual <= produto.estoque_minimo;

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.disabled = semEstoque;
                    btn.className = `btn btn-sm text-start d-flex justify-content-between align-items-center p-2 border bg-white ${
                        semEstoque
                            ? 'text-muted opacity-50'
                            : (estoqueCritico ? 'border-danger text-danger' : 'border-secondary-subtle')
                    }`;

                    btn.addEventListener('click', function () {
                        adicionarAoCarrinho(produto.id);
                        buscaInput.focus();
                    });

                    const infoEsq = document.createElement('div');
                    infoEsq.className = 'text-truncate me-2';

                    const nomeEl = document.createElement('div');
                    nomeEl.className = 'fw-semibold text-truncate';
                    nomeEl.textContent = produto.nome;

                    const subEl = document.createElement('small');
                    subEl.className = estoqueCritico ? 'text-danger' : 'text-muted';
                    subEl.textContent = `${produto.codigo_barras || 'Sem EAN'} • Est: ${produto.estoque_atual}`;

                    infoEsq.appendChild(nomeEl);
                    infoEsq.appendChild(subEl);

                    const precoBadge = document.createElement('span');
                    precoBadge.className = 'badge bg-primary fs-6';
                    precoBadge.textContent = 'R$ ' + formatarNumero(produto.preco_venda);

                    btn.appendChild(infoEsq);
                    btn.appendChild(precoBadge);
                    listaRapidaProdutos.appendChild(btn);
                });
            }

            function adicionarAoCarrinho(produtoId) {
                const produto = produtos.find(p => p.id === produtoId);
                if (!produto) return;

                if (produto.estoque_atual <= 0) {
                    exibirAlerta('danger', `Produto "${produto.nome}" está sem estoque disponível.`);
                    return;
                }

                const existente = carrinho.get(produtoId);
                const novaQtd   = existente ? existente.quantidade + 1 : 1;

                if (novaQtd > produto.estoque_atual) {
                    exibirAlerta('warning', `Estoque máximo atingido para "${produto.nome}" (${produto.estoque_atual} un.).`);
                    return;
                }

                carrinho.set(produtoId, { produto: produto, quantidade: novaQtd });
                ultimoProdutoId = produtoId;
                renderizarCarrinho();
            }

            function alterarQuantidade(produtoId, novaQtd) {
                const item = carrinho.get(produtoId);
                if (!item) return;

                if (novaQtd <= 0) {
                    carrinho.delete(produtoId);
                    renderizarCarrinho();
                    return;
                }

                if (novaQtd > item.produto.estoque_atual) {
                    exibirAlerta('warning', `Estoque disponível para "${item.produto.nome}": ${item.produto.estoque_atual} un.`);
                    return;
                }

                item.quantidade = novaQtd;
                ultimoProdutoId = produtoId;
                renderizarCarrinho();
            }

            function calcularTotalGeral() {
                let total = 0;
                let totalUnidades = 0;
                carrinho.forEach(function (item) {
                    total += item.produto.preco_venda * item.quantidade;
                    totalUnidades += item.quantidade;
                });
                return { total, totalUnidades };
            }

            function atualizarTroco() {
                const { total } = calcularTotalGeral();
                const recebido  = parseFloat(valorRecebidoInput.value);

                if (isNaN(recebido) || recebido <= 0 || total <= 0) {
                    valorTrocoTexto.textContent = '0,00';
                    valorTrocoTexto.className   = 'pdv-visor-numero text-success';
                    return;
                }

                const troco = recebido - total;
                if (troco < 0) {
                    valorTrocoTexto.textContent = 'Falta ' + formatarNumero(Math.abs(troco));
                    valorTrocoTexto.className   = 'fs-5 fw-bold text-danger';
                } else {
                    valorTrocoTexto.textContent = formatarNumero(troco);
                    valorTrocoTexto.className   = 'pdv-visor-numero text-success';
                }
            }

            function renderizarCarrinho() {
                corpoCarrinho.innerHTML = '';

                if (carrinho.size === 0) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = 7;
                    td.className = 'text-center text-muted py-5';
                    td.innerHTML = '<i class="bi bi-cart-x fs-2 d-block mb-2 opacity-50"></i>Nenhum item lançado na venda atual.';
                    tr.appendChild(td);
                    corpoCarrinho.appendChild(tr);
                } else {
                    let numeroItem = 1;
                    carrinho.forEach(function (item, produtoId) {
                        const tr = document.createElement('tr');
                        if (produtoId === ultimoProdutoId) {
                            tr.className = 'table-primary bg-opacity-25';
                        }

                        const tdItem = document.createElement('td');
                        tdItem.className = 'ps-3 fw-bold text-muted font-monospace';
                        tdItem.textContent = String(numeroItem++).padStart(3, '0');

                        const tdCodigo = document.createElement('td');
                        tdCodigo.className = 'font-monospace small text-muted';
                        tdCodigo.textContent = item.produto.codigo_barras || '-';

                        const tdDesc = document.createElement('td');
                        tdDesc.className = 'fw-semibold';
                        tdDesc.textContent = item.produto.nome;

                        const tdQtd = document.createElement('td');
                        tdQtd.className = 'text-center';

                        const group = document.createElement('div');
                        group.className = 'input-group input-group-sm justify-content-center';

                        const btnMenos = document.createElement('button');
                        btnMenos.type = 'button';
                        btnMenos.className = 'btn btn-outline-secondary fw-bold';
                        btnMenos.textContent = '-';
                        btnMenos.addEventListener('click', () => alterarQuantidade(produtoId, item.quantidade - 1));

                        const spanQtd = document.createElement('span');
                        spanQtd.className = 'input-group-text bg-white fw-bold px-3';
                        spanQtd.textContent = String(item.quantidade);

                        const btnMais = document.createElement('button');
                        btnMais.type = 'button';
                        btnMais.className = 'btn btn-outline-secondary fw-bold';
                        btnMais.textContent = '+';
                        btnMais.addEventListener('click', () => alterarQuantidade(produtoId, item.quantidade + 1));

                        group.appendChild(btnMenos);
                        group.appendChild(spanQtd);
                        group.appendChild(btnMais);
                        tdQtd.appendChild(group);

                        const tdUnit = document.createElement('td');
                        tdUnit.className = 'text-end font-monospace';
                        tdUnit.textContent = formatarNumero(item.produto.preco_venda);

                        const tdTotal = document.createElement('td');
                        tdTotal.className = 'text-end fw-bold font-monospace';
                        tdTotal.textContent = formatarNumero(item.produto.preco_venda * item.quantidade);

                        const tdRemover = document.createElement('td');
                        tdRemover.className = 'text-center pe-3';
                        const btnRemover = document.createElement('button');
                        btnRemover.type = 'button';
                        btnRemover.className = 'btn btn-sm btn-outline-danger py-0 px-2';
                        btnRemover.title = 'Remover item';
                        btnRemover.innerHTML = '<i class="bi bi-trash"></i>';
                        btnRemover.addEventListener('click', function () {
                            carrinho.delete(produtoId);
                            renderizarCarrinho();
                            buscaInput.focus();
                        });
                        tdRemover.appendChild(btnRemover);

                        tr.appendChild(tdItem);
                        tr.appendChild(tdCodigo);
                        tr.appendChild(tdDesc);
                        tr.appendChild(tdQtd);
                        tr.appendChild(tdUnit);
                        tr.appendChild(tdTotal);
                        tr.appendChild(tdRemover);
                        corpoCarrinho.appendChild(tr);
                    });
                }

                const { total, totalUnidades } = calcularTotalGeral();
                totalGeralTexto.textContent = formatarNumero(total);
                resumoQtdItens.textContent  = `${totalUnidades} unidade(s) • ${carrinho.size} item(ns)`;

                atualizarVisoresUltimoItem();
                atualizarTroco();
            }

            function limparCarrinho() {
                carrinho.clear();
                ultimoProdutoId = null;
                valorRecebidoInput.value = '';
                renderizarCarrinho();
                buscaInput.focus();
            }

            function adicionarCupomNaTabelaHistorico(dados) {
                const linhaVazia = document.getElementById('linhaSemCupons');
                if (linhaVazia) linhaVazia.remove();

                const tr = document.createElement('tr');
                const tdId = document.createElement('td');
                tdId.className = 'ps-3 fw-bold font-monospace';
                tdId.textContent = '#' + dados.venda_id;

                const tdHora = document.createElement('td');
                tdHora.className = 'small text-muted';
                tdHora.textContent = dados.hora || '-';

                const tdForma = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = 'badge bg-secondary';
                badge.textContent = dados.forma_pagamento || '';
                tdForma.appendChild(badge);

                const tdVal = document.createElement('td');
                tdVal.className = 'text-end pe-3 fw-bold text-success';
                tdVal.textContent = 'R$ ' + formatarNumero(dados.valor_total);

                tr.appendChild(tdId);
                tr.appendChild(tdHora);
                tr.appendChild(tdForma);
                tr.appendChild(tdVal);
                corpoHistoricoCupons.prepend(tr);
            }

            async function finalizarVenda() {
                if (carrinho.size === 0) {
                    exibirAlerta('warning', 'Adicione ao menos um produto antes de finalizar a venda.');
                    buscaInput.focus();
                    return;
                }

                const itensPayload = [];
                carrinho.forEach(function (item) {
                    itensPayload.push({
                        produto_id: item.produto.id,
                        quantidade: item.quantidade,
                    });
                });

                btnFinalizarVenda.disabled = true;
                const htmlOriginal = btnFinalizarVenda.innerHTML;
                btnFinalizarVenda.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>FINALIZANDO...';

                try {
                    const resposta = await fetch('index.php?rota=caixa_finalizar', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            forma_pagamento: formaPagamentoSelect.value,
                            itens: itensPayload,
                        }),
                    });

                    const dados = await resposta.json();

                    if (!resposta.ok || !dados.sucesso) {
                        exibirAlerta('danger', dados.mensagem || 'Não foi possível finalizar a venda.');
                        return;
                    }

                    carrinho.forEach(function (item) {
                        item.produto.estoque_atual -= item.quantidade;
                    });

                    // Atualiza os acumuladores da barra do turno e do modal de fechamento
                    const totalVendasAtual = Number(dados.total_vendas_turno || 0);
                    const saldoLiquidoNovo = totalVendasAtual - Number(totalSangriasAtual || 0);
                    turnoTotalVendasTexto.textContent   = 'R$ ' + formatarNumero(totalVendasAtual);
                    modalFecharTotalVendas.textContent  = 'R$ ' + formatarNumero(totalVendasAtual);
                    modalFecharSaldoLiquido.textContent = 'R$ ' + formatarNumero(saldoLiquidoNovo);

                    adicionarCupomNaTabelaHistorico(dados);

                    limparCarrinho();
                    renderizarListaRapida(buscaInput.value);
                    exibirAlerta('success', dados.mensagem);
                } catch (erro) {
                    exibirAlerta('danger', 'Erro de comunicação com o servidor ao finalizar a venda.');
                } finally {
                    btnFinalizarVenda.disabled = false;
                    btnFinalizarVenda.innerHTML = htmlOriginal;
                    buscaInput.focus();
                }
            }

            buscaInput.addEventListener('input', function () {
                renderizarListaRapida(this.value);
            });

            buscaInput.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') return;
                event.preventDefault();

                const termo = this.value.trim().toLowerCase();
                if (!termo) return;

                const matchExato = produtos.find(p => p.codigo_barras.toLowerCase() === termo);
                if (matchExato) {
                    adicionarAoCarrinho(matchExato.id);
                    this.value = '';
                    renderizarListaRapida('');
                    return;
                }

                const filtrados = produtos.filter(p =>
                    p.nome.toLowerCase().includes(termo) || p.codigo_barras.toLowerCase().includes(termo)
                );
                if (filtrados.length === 1) {
                    adicionarAoCarrinho(filtrados[0].id);
                    this.value = '';
                    renderizarListaRapida('');
                } else if (filtrados.length === 0) {
                    exibirAlerta('warning', 'Nenhum produto encontrado para o código ou termo informado.');
                    this.select();
                }
            });

            btnLimparBusca.addEventListener('click', function () {
                buscaInput.value = '';
                renderizarListaRapida('');
                buscaInput.focus();
            });

            btnLimparCarrinho.addEventListener('click', limparCarrinho);
            valorRecebidoInput.addEventListener('input', atualizarTroco);
            btnFinalizarVenda.addEventListener('click', finalizarVenda);

            function modalAberto() {
                return document.querySelector('.modal.show') !== null;
            }

            document.addEventListener('keydown', function (event) {
                if (modalAberto()) return;

                if (event.key === 'F2') {
                    event.preventDefault();
                    buscaInput.focus();
                    buscaInput.select();
                } else if (event.key === 'F4') {
                    event.preventDefault();
                    if (!btnFinalizarVenda.disabled) {
                        finalizarVenda();
                    }
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    limparCarrinho();
                }
            });

            document.getElementById('atalhoF2').addEventListener('click', function (e) {
                e.preventDefault();
                buscaInput.focus();
                buscaInput.select();
            });
            document.getElementById('atalhoF4').addEventListener('click', function (e) {
                e.preventDefault();
                if (!btnFinalizarVenda.disabled) {
                    finalizarVenda();
                }
            });
            document.getElementById('atalhoEsc').addEventListener('click', function (e) {
                e.preventDefault();
                limparCarrinho();
            });

            renderizarListaRapida();
            renderizarCarrinho();
        })();
    </script>
    <?php endif; ?>
</body>
</html>