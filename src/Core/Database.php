<?php
namespace Rongie\QuickHire\Core;

use PDO;
use PDOException;

class Database
{
    private PDO $pdo; // ✅ encapsulated

    public function __construct(array $dbConfig)
    {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
        try {
            $this->pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("DB Connection failed: " . $e->getMessage());
        }
    }

    public function pdo(): PDO // ✅ controlled access
    {
        return $this->pdo;
    }
}