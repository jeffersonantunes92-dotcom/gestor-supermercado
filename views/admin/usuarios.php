<?php
declare(strict_types=1);

AuthController::verificarPermissao(['admin']);

$usuarioLogado = $_SESSION['usuario'];
$idLogado      = (int) $usuarioLogado['id'];
$nomeUsuario   = htmlspecialchars((string) $usuarioLogado['nome'], ENT_QUOTES, 'UTF-8');
$perfilUsuario = (string) $usuarioLogado['perfil'];
$perfilSeguro  = htmlspecialchars(strtoupper($perfilUsuario), ENT_QUOTES, 'UTF-8');

$flashSucesso = $_SESSION['flash_sucesso'] ?? null;
$flashErro    = $_SESSION['flash_erro'] ?? null;
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - Supermercado ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Barra Superior -->
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
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?rota=caixa">
                            <i class="bi bi-cash-register me-1"></i>Caixa PDV
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?rota=estoque">
                            <i class="bi bi-box-seam me-1"></i>Estoque/Mercadorias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?rota=financeiro">
                            <i class="bi bi-wallet2 me-1"></i>Financeiro
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold" href="index.php?rota=usuarios">
                            <i class="bi bi-people me-1"></i>Usuários
                        </a>
                    </li>
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

    <main class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Gestão de Usuários</h1>
                <p class="text-muted mb-0">Cadastre colaboradores e controle permissões de acesso por perfil.</p>
            </div>
            <button type="button" class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalUsuario">
                <i class="bi bi-person-plus me-1"></i>Novo Colaborador
            </button>
        </div>

        <?php if ($flashSucesso !== null): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars((string) $flashSucesso, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <?php if ($flashErro !== null): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars((string) $flashErro, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-bold mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Colaboradores Cadastrados</h2>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Usuário</th>
                                <th class="text-center">Perfil</th>
                                <th class="text-center">Status</th>
                                <th>Cadastrado em</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usuarios)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Nenhum usuário encontrado.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $usr): ?>
                                    <?php
                                    $id         = (int) $usr['id'];
                                    $ativo      = filter_var($usr['ativo'], FILTER_VALIDATE_BOOLEAN);
                                    $perfilUsr  = (string) $usr['perfil'];
                                    $dataCriado = $usr['criado_em'] ?? $usr['criado_em'] ?? null;
                                    $dataFmt    = $dataCriado ? date('d/m/Y H:i', strtotime((string) $dataCriado)) : '-';

                                    $badgePerfil = match ($perfilUsr) {
                                        'admin'      => 'bg-dark',
                                        'caixa'      => 'bg-primary',
                                        'estoque'    => 'bg-info text-dark',
                                        'financeiro' => 'bg-success',
                                        default      => 'bg-secondary',
                                    };
                                    ?>
                                    <tr class="<?= !$ativo ? 'table-secondary text-muted' : '' ?>">
                                        <td class="fw-semibold">
                                            <?= htmlspecialchars((string) $usr['nome'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($id === $idLogado): ?>
                                                <span class="badge bg-primary bg-opacity-75 ms-1">Você</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars((string) $usr['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $badgePerfil ?>">
                                                <?= htmlspecialchars(strtoupper($perfilUsr), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $ativo ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $ativo ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                        </td>
                                        <td class="small"><?= htmlspecialchars($dataFmt, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-end">
                                            <?php if ($id !== $idLogado): ?>
                                                <form method="POST" action="index.php?rota=usuarios_status" class="d-inline">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <input type="hidden" name="ativo" value="<?= $ativo ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-sm <?= $ativo ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                                        <i class="bi <?= $ativo ? 'bi-person-slash' : 'bi-person-check' ?> me-1"></i>
                                                        <?= $ativo ? 'Desativar' : 'Ativar' ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="small text-muted">Sessão atual</span>
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
    </main>

    <!-- Modal: Novo Colaborador -->
    <div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="index.php?rota=usuarios_salvar" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalUsuarioLabel">
                        <i class="bi bi-person-plus me-2"></i>Cadastrar Novo Colaborador
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="usr_nome" class="form-label fw-semibold">Nome Completo</label>
                        <input
                            type="text"
                            class="form-control"
                            id="usr_nome"
                            name="nome"
                            placeholder="Ex: Maria Oliveira"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="usr_email" class="form-label fw-semibold">Usuário de Acesso</label>
                        <input
                            type="text"
                            class="form-control"
                            id="usr_email"
                            name="email"
                            placeholder="maria@supermercado.com"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="usr_senha" class="form-label fw-semibold">Senha Inicial</label>
                        <input
                            type="password"
                            class="form-control"
                            id="usr_senha"
                            name="senha"
                            minlength="6"
                            placeholder="Mínimo 6 caracteres"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="usr_perfil" class="form-label fw-semibold">Perfil de Acesso</label>
                        <select class="form-select" id="usr_perfil" name="perfil" required>
                            <option value="">Selecione o perfil...</option>
                            <option value="caixa">Caixa (PDV)</option>
                            <option value="estoque">Estoque / Mercadorias</option>
                            <option value="financeiro">Financeiro</option>
                            <option value="admin">Administrador (Acesso Total)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">Salvar Colaborador</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>