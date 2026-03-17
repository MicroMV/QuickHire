#!/usr/bin/env php
<?php
/**
 * Cron job script to clean up abandoned calls
 * Run this every 5 minutes: */5 * * * * /path/to/php /path/to/cleanup_cron.php
 */

require __DIR__ . '/vendor/autoload.php';

use Rongie\QuickHire\Core\Database;

try {
    $config = require __DIR__ . '/Config/config.php';
    $db = new Database($config['db']);
    $pdo = $db->pdo();
    
    // Find calls that have been in progress for more than 10 minutes without recent activity
    $stmt = $pdo->prepare("
        SELECT c.room_code, c.employer_user_id, c.jobseeker_user_id, c.updated_at
        FROM calls c
        WHERE c.status IN ('RINGING', 'IN_CALL')
        AND c.updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
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
        echo "Cleaned up abandoned call: {$call['room_code']}\n";
    }
    
    if ($cleanedCount > 0) {
        echo "Total cleaned calls: $cleanedCount\n";
    } else {
        echo "No abandoned calls found\n";
    }
    
} catch (Exception $e) {
    error_log("Cleanup cron error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>