<?php
namespace Rongie\QuickHire\Core;

use PDO;
use PDOException;

class Database
{
    private PDO $pdo; 

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
            error_log("Database connection failed: " . $e->getMessage());
            // Redirect to maintenance page without exposing technical details
            if (!headers_sent()) {
                header("Location: /QuickHire/Public/maintenance.php");
                exit;
            }
            // If headers already sent (e.g. AJAX), return JSON error
            echo json_encode(['ok' => false, 'error' => 'Service temporarily unavailable']);
            exit;
        }
    }

    public function pdo(): PDO 
    {
        return $this->pdo;
    }
}