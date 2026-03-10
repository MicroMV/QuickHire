<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$room = $input['room'] ?? '';
$message = trim($input['message'] ?? '');

if (empty($room) || empty($message)) {
    echo json_encode(['ok' => false, 'error' => 'Missing room or message']);
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

// Insert message
try {
    $stmt = $pdo->prepare("INSERT INTO chat_messages (room_code, sender_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$room, $uid, $message]);
    
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
