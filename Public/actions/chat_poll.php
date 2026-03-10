<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

$room = $_GET['room'] ?? '';
$after = (int)($_GET['after'] ?? 0);

if (empty($room)) {
    echo json_encode(['ok' => false, 'error' => 'Missing room']);
    exit;
}

$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

// Verify user is in this room
$stmt = $pdo->prepare("SELECT * FROM calls WHERE room_code = ? LIMIT 1");
$stmt->execute([$room]);
$call = $stmt->fetch();

if (!$call) {
    echo json_encode(['ok' => false, 'error' => 'Room not found']);
    exit;
}

$uid = Auth::userId();
if ($uid !== (int)$call['employer_user_id'] && $uid !== (int)$call['jobseeker_user_id']) {
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

// Get messages after the given ID
try {
    $stmt = $pdo->prepare("
        SELECT cm.*, u.first_name, u.last_name, u.role
        FROM chat_messages cm
        JOIN users u ON u.id = cm.sender_id
        WHERE cm.room_code = ? AND cm.id > ?
        ORDER BY cm.id ASC
    ");
    $stmt->execute([$room, $after]);
    $messages = $stmt->fetchAll();
    
    $lastId = $after;
    if (!empty($messages)) {
        $lastId = (int)$messages[count($messages) - 1]['id'];
    }
    
    echo json_encode([
        'ok' => true,
        'messages' => $messages,
        'after' => $lastId
    ]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
