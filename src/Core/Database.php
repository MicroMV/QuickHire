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
            // Show a plain error without exposing technical details
            if (!headers_sent()) {
                http_response_code(503);
                echo '<!DOCTYPE html><html><head><title>Service Unavailable</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><h2>Service Temporarily Unavailable</h2><p>Please try again later.</p></body></html>';
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