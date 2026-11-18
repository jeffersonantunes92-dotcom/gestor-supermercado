<?php
declare(strict_types=1);

class Usuario
{
    private PDO $pdo;

    private const PERFIS_VALIDOS = ['admin', 'caixa', 'estoque', 'financeiro'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca um usuário pelo e-mail (utilizado na autenticação).
     */
    public function buscarPorEmail(string $email): ?array
    {
        $sql = 'SELECT id, nome, email, senha, perfil, ativo, criado_em
                FROM usuarios
                WHERE LOWER(email) = LOWER(:email) OR LOWER(nome) = LOWER(:email)
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':email' => trim($email)]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario !== false ? $usuario : null;
    }

    /**
     * Lista todos os usuários cadastrados (sem expor o hash da senha).
     */
    public function listarTodos(): array
    {
        $sql = 'SELECT id, nome, email, perfil, ativo, criado_em
                FROM usuarios
                ORDER BY nome ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cadastra um novo usuário com senha já criptografada (password_hash).
     */
    public function criar(string $nome, string $email, string $senhaHash, string $perfil): bool
    {
        if (!in_array($perfil, self::PERFIS_VALIDOS, true)) {
            throw new InvalidArgumentException('Perfil de usuário inválido.');
        }

        $sql = 'INSERT INTO usuarios (nome, email, senha, perfil, ativo)
                VALUES (:nome, :email, :senha, :perfil, :ativo)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':nome', trim($nome), PDO::PARAM_STR);
        $stmt->bindValue(':email', strtolower(trim($email)), PDO::PARAM_STR);
        $stmt->bindValue(':senha', $senhaHash, PDO::PARAM_STR);
        $stmt->bindValue(':perfil', $perfil, PDO::PARAM_STR);
        $stmt->bindValue(':ativo', true, PDO::PARAM_BOOL);

        return $stmt->execute();
    }

    /**
     * Ativa ou desativa um usuário no sistema.
     */
    public function alternarStatus(int $id, bool $ativo): bool
    {
        $sql = 'UPDATE usuarios
                SET ativo = :ativo
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':ativo', $ativo, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}