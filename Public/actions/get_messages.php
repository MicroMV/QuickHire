<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MessagingService;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

$conversationId = (int)($_GET['conversation_id'] ?? 0);

if ($conversationId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid conversation ID']);
    exit;
}

// Release session lock immediately so other requests aren't blocked
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);
$messagingService = new MessagingService($db->pdo());

$userId = Auth::userId();
$userRole = Auth::role();

try {
    // Verify user has access to this conversation
    $conversation = $messagingService->getConversation($conversationId);
    if (!$conversation) {
        echo json_encode(['ok' => false, 'error' => 'Conversation not found']);
        exit;
    }

    $hasAccess = ($userRole === 'EMPLOYER' && $conversation['employer_id'] == $userId) ||
                 ($userRole === 'JOBSEEKER' && $conversation['jobseeker_id'] == $userId);

    if (!$hasAccess) {
        echo json_encode(['ok' => false, 'error' => 'Access denied']);
        exit;
    }

    // Get messages and mark as read
    $messages = $messagingService->getMessages($conversationId);
    $messagingService->markMessagesAsRead($conversationId, $userId);
    
    echo json_encode([
        'ok' => true,
        'messages' => $messages
    ]);
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to load messages'
    ]);
}