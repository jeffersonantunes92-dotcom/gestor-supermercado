<?php

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = 'aws-0-us-east-1.pooler.supabase.com';
            $port = '5432';
            $dbname = 'postgres';
            $user = 'postgres.vztdmtqovvvvdqnsjhsk';
            $password = 'SUA_SENHA_AQUI';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

            try {
                self::$instance = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                die("Erro de conexão com o banco de dados: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}
