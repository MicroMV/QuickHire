<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

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
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

// Delete messages then conversation
$pdo->prepare("DELETE FROM messages WHERE conversation_id = ?")->execute([$conversationId]);
$pdo->prepare("DELETE FROM conversations WHERE id = ?")->execute([$conversationId]);

echo json_encode(['ok' => true]);
