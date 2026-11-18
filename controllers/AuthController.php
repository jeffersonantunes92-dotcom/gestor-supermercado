<?php
declare(strict_types=1);

class AuthController
{
    private Usuario $usuarioModel;

    public function __construct(PDO $pdo)
    {
        $this->usuarioModel = new Usuario($pdo);
    }

    /**
     * Exibe a tela de login ou redireciona caso já esteja autenticado.
     */
    public function exibirLogin(): void
    {
        if (isset($_SESSION['usuario']['id'])) {
            header('Location: index.php?rota=dashboard');
            exit;
        }

        require BASE_PATH . '/views/auth/login.php';
    }

    /**
     * Processa a autenticação via POST.
     */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=login');
            exit;
        }

        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? '';

        if (trim($email) === '' || $senha === '') {
            $_SESSION['erro_login'] = 'Informe o e-mail e a senha para entrar.';
            header('Location: index.php?rota=login');
            exit;
        }

        $usuario = $this->usuarioModel->buscarPorEmail($email);

        if ($usuario === null || !password_verify($senha, (string) $usuario['senha'])) {
            $_SESSION['erro_login'] = 'E-mail ou senha inválidos.';
            header('Location: index.php?rota=login');
            exit;
        }

        $ativo = filter_var($usuario['ativo'], FILTER_VALIDATE_BOOLEAN);
        if (!$ativo) {
            $_SESSION['erro_login'] = 'Usuário inativo. Contate o administrador.';
            header('Location: index.php?rota=login');
            exit;
        }

        // Previne session fixation
        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id'     => (int) $usuario['id'],
            'nome'   => (string) $usuario['nome'],
            'perfil' => (string) $usuario['perfil'],
        ];

        header('Location: index.php?rota=dashboard');
        exit;
    }

    /**
     * Encerra a sessão com segurança e redireciona para o login.
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: index.php?rota=login');
        exit;
    }

    /**
     * Verifica se há sessão ativa e se o perfil do usuário tem permissão de acesso.
     *
     * @param string[] $perfisPermitidos Ex: ['admin', 'caixa']
     */
    public static function verificarPermissao(array $perfisPermitidos): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (
            !isset($_SESSION['usuario']) ||
            empty($_SESSION['usuario']['id']) ||
            empty($_SESSION['usuario']['perfil'])
        ) {
            $_SESSION['erro_login'] = 'Sessão expirada ou não autenticada. Faça login novamente.';
            header('Location: index.php?rota=login');
            exit;
        }

        $perfilAtual = (string) $_SESSION['usuario']['perfil'];

        if (!in_array($perfilAtual, $perfisPermitidos, true)) {
            http_response_code(403);
            $perfilSeguro = htmlspecialchars($perfilAtual, ENT_QUOTES, 'UTF-8');
            echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado - Supermercado ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-sm border-danger" style="max-width: 460px;">
        <div class="card-body text-center p-4">
            <h1 class="display-6 text-danger fw-bold">403 - Acesso Negado</h1>
            <p class="text-muted mt-2 mb-4">
                Seu perfil (<strong>{$perfilSeguro}</strong>) não possui permissão para acessar esta área.
            </p>
            <a href="index.php?rota=dashboard" class="btn btn-primary me-2">Voltar ao Painel</a>
            <a href="index.php?rota=logout" class="btn btn-outline-secondary">Sair</a>
        </div>
    </div>
</body>
</html>
HTML;
            exit;
        }
    }
}