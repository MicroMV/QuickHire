<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

header('Content-Type: application/json');

Session::start();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$room = $input['room'] ?? '';

if (empty($room)) {
    echo json_encode(['ok' => false, 'error' => 'Room code required']);
    exit;
}

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $pdo = $db->pdo();
    
    $userId = Auth::userId();
    
    // Find the call
    $stmt = $pdo->prepare("SELECT * FROM calls WHERE room_code = ? LIMIT 1");
    $stmt->execute([$room]);
    $call = $stmt->fetch();
    
    if (!$call) {
        echo json_encode(['ok' => false, 'error' => 'Call not found']);
        exit;
    }
    
    // Verify user is part of this call
    if ($userId !== (int)$call['employer_user_id'] && $userId !== (int)$call['jobseeker_user_id']) {
        echo json_encode(['ok' => false, 'error' => 'Not authorized']);
        exit;
    }
    
    // End the call - set status to COMPLETED
    $updateStmt = $pdo->prepare("
        UPDATE calls 
        SET status = 'COMPLETED', 
            updated_at = CURRENT_TIMESTAMP 
        WHERE room_code = ? AND status IN ('RINGING', 'IN_CALL')
    ");
    $updateStmt->execute([$room]);
    
    // Send leave signal to notify the other participant
    $signalStmt = $pdo->prepare("
        INSERT INTO webrtc_signals (room_code, sender_id, message_type, payload, created_at) 
        VALUES (?, ?, 'leave', ?, CURRENT_TIMESTAMP)
    ");
    $signalStmt->execute([$room, $userId, json_encode(['bye' => true, 'reason' => 'user_left'])]);
    
    echo json_encode(['ok' => true, 'message' => 'Call ended successfully']);
    
} catch (Exception $e) {
    error_log("Cleanup call error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
?>