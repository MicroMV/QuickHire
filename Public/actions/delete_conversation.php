<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $pdo = $db->pdo();

    $userId = Auth::userId();
    $conversationId = (int)($_POST['conversation_id'] ?? 0);

    if (!$conversationId) {
        echo json_encode(['ok' => false, 'error' => 'Missing conversation_id']);
        exit;
    }

    // Verify user is part of this conversation
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND (employer_id = ? OR jobseeker_id = ?)");
    $stmt->execute([$conversationId, $userId, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Not authorized to delete this conversation']);
        exit;
    }

    // Delete messages then conversation
    $deleteMessages = $pdo->prepare("DELETE FROM messages WHERE conversation_id = ?");
    $deleteMessages->execute([$conversationId]);
    
    $deleteConversation = $pdo->prepare("DELETE FROM conversations WHERE id = ?");
    $deleteConversation->execute([$conversationId]);

    echo json_encode(['ok' => true, 'message' => 'Conversation deleted successfully']);

} catch (Exception $e) {
    error_log("Delete conversation error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
