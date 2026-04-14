<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Database;

Session::start();

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);

    // Update last_active timestamp in UTC
    $stmt = $db->pdo()->prepare("UPDATE users SET last_active = UTC_TIMESTAMP() WHERE id = ?");
    $stmt->execute([$userId]);

    echo json_encode(['ok' => true, 'updated_at' => gmdate('Y-m-d\TH:i:s\Z')]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
