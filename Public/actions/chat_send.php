<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MessagingService;

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

// Send message using unified messaging system
try {
    // Only save to unified messages table if both users are present
    if ($call['jobseeker_user_id']) {
        $employerId = (int)$call['employer_user_id'];
        $jobseekerId = (int)$call['jobseeker_user_id'];
        
        // Get or create conversation
        $conversationId = $messagingService->getOrCreateConversation($employerId, $jobseekerId);
        
        // Send message with room_code to link it to the call
        $messagingService->sendMessage($conversationId, $uid, $message, 'text', null, null, null, $room);
    }
    
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
