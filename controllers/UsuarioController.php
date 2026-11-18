<?php
declare(strict_types=1);

class UsuarioController
{
    private Usuario $usuarioModel;

    public function __construct(PDO $pdo)
    {
        $this->usuarioModel = new Usuario($pdo);
    }

    /**
     * Lista todos os colaboradores cadastrados.
     */
    public function index(): void
    {
        AuthController::verificarPermissao(['admin']);

        $usuarios = $this->usuarioModel->listarTodos();

        require BASE_PATH . '/views/admin/usuarios.php';
    }

    /**
     * Cadastra um novo colaborador com senha criptografada via password_hash().
     */
    public function salvar(): void
    {
        AuthController::verificarPermissao(['admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=usuarios');
            exit;
        }

        $nome   = trim((string) ($_POST['nome'] ?? ''));
        $email  = trim((string) ($_POST['email'] ?? ''));
        $senha  = (string) ($_POST['senha'] ?? '');
        $perfil = trim((string) ($_POST['perfil'] ?? ''));

        if ($nome === '' || !filter_var($email, FILTER_UNSAFE_RAW)) {
            $_SESSION['flash_erro'] = 'Informe um nome e um e-mail válidos.';
            header('Location: index.php?rota=usuarios');
            exit;
        }

        if (strlen($senha) < 6) {
            $_SESSION['flash_erro'] = 'A senha deve possuir no mínimo 6 caracteres.';
            header('Location: index.php?rota=usuarios');
            exit;
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        try {
            $this->usuarioModel->criar($nome, $email, $senhaHash, $perfil);
            $_SESSION['flash_sucesso'] = 'Colaborador cadastrado com sucesso!';
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $_SESSION['flash_erro'] = 'Já existe um usuário cadastrado com este e-mail.';
            } else {
                $_SESSION['flash_erro'] = 'Erro ao cadastrar colaborador no banco de dados.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_erro'] = $e->getMessage();
        }

        header('Location: index.php?rota=usuarios');
        exit;
    }

    /**
     * Ativa ou desativa o acesso de um colaborador via POST.
     */
    public function alternarStatus(): void
    {
        AuthController::verificarPermissao(['admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?rota=usuarios');
            exit;
        }

        $id         = (int) ($_POST['id'] ?? 0);
        $novoStatus = ((string) ($_POST['ativo'] ?? '0')) === '1';
        $idLogado   = (int) ($_SESSION['usuario']['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['flash_erro'] = 'Usuário inválido.';
            header('Location: index.php?rota=usuarios');
            exit;
        }

        if ($id === $idLogado && !$novoStatus) {
            $_SESSION['flash_erro'] = 'Você não pode desativar o próprio usuário logado.';
            header('Location: index.php?rota=usuarios');
            exit;
        }

        try {
            $this->usuarioModel->alternarStatus($id, $novoStatus);
            $texto = $novoStatus ? 'ativado' : 'desativado';
            $_SESSION['flash_sucesso'] = "Acesso do colaborador {$texto} com sucesso!";
        } catch (Throwable $e) {
            $_SESSION['flash_erro'] = 'Erro ao alterar status do colaborador.';
        }

        header('Location: index.php?rota=usuarios');
        exit;
    }
}