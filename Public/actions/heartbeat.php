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

// Release session lock — session data is no longer needed
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $pdo = $db->pdo();
    
    $userId = Auth::userId();
    
    // Update user's last_active so they appear online during calls
    $pdo->prepare("UPDATE users SET last_active = UTC_TIMESTAMP() WHERE id = ?")->execute([$userId]);

    // Update the call's updated_at timestamp to show activity
    $stmt = $pdo->prepare("
        UPDATE calls 
        SET updated_at = CURRENT_TIMESTAMP 
        WHERE room_code = ? 
        AND (employer_user_id = ? OR jobseeker_user_id = ?)
        AND status IN ('RINGING', 'IN_CALL')
    ");
    $stmt->execute([$room, $userId, $userId]);
    
    // Send a heartbeat signal
    $signalStmt = $pdo->prepare("
        INSERT INTO webrtc_signals (room_code, sender_id, message_type, payload, created_at) 
        VALUES (?, ?, 'heartbeat', ?, CURRENT_TIMESTAMP)
    ");
    $signalStmt->execute([$room, $userId, json_encode(['alive' => true])]);
    
    echo json_encode(['ok' => true]);
    
} catch (Exception $e) {
    error_log("Heartbeat error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
?>