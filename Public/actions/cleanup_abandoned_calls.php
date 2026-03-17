<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Database;

header('Content-Type: application/json');

// This endpoint can be called periodically to clean up abandoned calls
// Calls that have been "IN_CALL" for more than 10 minutes without activity

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $pdo = $db->pdo();
    
    // Find calls that have been in progress for more than 10 minutes without recent signals
    $stmt = $pdo->prepare("
        SELECT c.room_code, c.employer_user_id, c.jobseeker_user_id
        FROM calls c
        WHERE c.status IN ('RINGING', 'IN_CALL')
        AND c.updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        AND NOT EXISTS (
            SELECT 1 FROM webrtc_signals s 
            WHERE s.room_code = c.room_code 
            AND s.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        )
    ");
    $stmt->execute();
    $abandonedCalls = $stmt->fetchAll();
    
    $cleanedCount = 0;
    
    foreach ($abandonedCalls as $call) {
        // Mark call as completed
        $updateStmt = $pdo->prepare("
            UPDATE calls 
            SET status = 'COMPLETED', 
                updated_at = CURRENT_TIMESTAMP 
            WHERE room_code = ?
        ");
        $updateStmt->execute([$call['room_code']]);
        
        // Send leave signal to notify any remaining participants
        $signalStmt = $pdo->prepare("
            INSERT INTO webrtc_signals (room_code, sender_id, message_type, payload, created_at) 
            VALUES (?, 0, 'leave', ?, CURRENT_TIMESTAMP)
        ");
        $signalStmt->execute([
            $call['room_code'], 
            json_encode(['bye' => true, 'reason' => 'call_abandoned'])
        ]);
        
        $cleanedCount++;
    }
    
    echo json_encode([
        'ok' => true, 
        'cleaned_calls' => $cleanedCount,
        'message' => "Cleaned up $cleanedCount abandoned calls"
    ]);
    
} catch (Exception $e) {
    error_log("Cleanup abandoned calls error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
?>