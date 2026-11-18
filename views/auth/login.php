<?php
declare(strict_types=1);

$erroLogin = $_SESSION['erro_login'] ?? null;
unset($_SESSION['erro_login']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestão de Supermercado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-9 col-md-7 col-lg-5 col-xl-4">
                <div class="card shadow border-0 rounded-3">
                    <div class="card-header bg-primary text-white text-center py-4 rounded-top-3">
                        <i class="bi bi-cart4 fs-1"></i>
                        <h1 class="h4 fw-bold mt-2 mb-0">Supermercado ERP</h1>
                        <small class="opacity-75">PDV, Estoque &amp; Financeiro</small>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($erroLogin !== null): ?>
                            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <div><?= htmlspecialchars((string) $erroLogin, ENT_QUOTES, 'UTF-8') ?></div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="index.php?rota=autenticar" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">Usuário ou E-mail</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="email"
                                        name="email"
                                        placeholder="admin"
                                        required
                                        autofocus
                                    >
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="senha" class="form-label fw-semibold">Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="senha"
                                        name="senha"
                                        placeholder="Digite sua senha"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> Entrar no Sistema
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer bg-white text-center py-3 text-muted small rounded-bottom-3">
                        Acesso restrito a colaboradores autorizados
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
