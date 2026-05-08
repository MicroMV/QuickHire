<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MessagingService;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);
$messagingService = new MessagingService($db->pdo());

$userId = Auth::userId();
$userRole = Auth::role();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $conversations = $messagingService->getUserConversations($userId, $userRole);

    echo json_encode([
        'ok' => true,
        'conversations' => $conversations
    ]);
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to load conversations'
    ]);
}
