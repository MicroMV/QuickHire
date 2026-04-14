<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MessagingService;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

$room = $_GET['room'] ?? '';
$after = (int)($_GET['after'] ?? 0);

if (empty($room)) {
    echo json_encode(['ok' => false, 'error' => 'Missing room']);
    exit;
}

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();
$messagingService = new MessagingService($pdo);

// Verify user is in this room
$stmt = $pdo->prepare("SELECT * FROM calls WHERE room_code = ? LIMIT 1");
$stmt->execute([$room]);
$call = $stmt->fetch();

if (!$call) {
    echo json_encode(['ok' => false, 'error' => 'Room not found']);
    exit;
}

$uid = Auth::userId();
if ($uid !== (int)$call['employer_user_id'] && ($call['jobseeker_user_id'] && $uid !== (int)$call['jobseeker_user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

// Get messages for this room using unified messaging system
try {
    $messages = $messagingService->getRoomMessages($room, $after);
    
    $lastId = $after;
    if (!empty($messages)) {
        $lastId = (int)$messages[count($messages) - 1]['id'];
    }
    
    // Format messages for call chat display
    $formattedMessages = [];
    foreach ($messages as $msg) {
        $formattedMessages[] = [
            'id' => $msg['id'],
            'sender_id' => $msg['sender_id'],
            'message' => $msg['content'],
            'first_name' => $msg['first_name'],
            'last_name' => $msg['last_name'],
            'role' => $msg['role'],
            'created_at' => $msg['created_at']
        ];
    }
    
    echo json_encode([
        'ok' => true,
        'messages' => $formattedMessages,
        'after' => $lastId
    ]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
