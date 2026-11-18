<?php
class Database {
    private static ?PDO $instance = null;
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = getenv("DB_HOST") ?: "aws-0-us-east-1.pooler.supabase.com";
            $port = getenv("DB_PORT") ?: "5432";
            $dbname = getenv("DB_NAME") ?: "postgres";
            $user = getenv("DB_USER") ?: "postgres.vztdmtqovvvvdqnsjhsk";
            $password = getenv("DB_PASS") ?: "";
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
            try {
                self::$instance = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                die("Erro de conexao: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}
