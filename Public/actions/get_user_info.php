<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$userId = Auth::userId();

try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo json_encode([
            'ok' => true,
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role']
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'User not found']);
    }
    
} catch (Exception $e) {
    error_log("Get user info error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to get user info']);
}