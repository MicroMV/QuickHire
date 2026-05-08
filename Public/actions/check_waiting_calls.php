<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

header('Content-Type: application/json');

Session::start();
Auth::requireLogin();

// Release session lock — this endpoint only reads data
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (Auth::role() !== 'JOBSEEKER') {
    echo json_encode(['ok' => false, 'error' => 'Only jobseekers can use this feature']);
    exit;
}

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$userId = Auth::userId();

try {
    // Check if there's a call waiting for this jobseeker
    $stmt = $pdo->prepare("
        SELECT room_code, created_at 
        FROM calls 
        WHERE jobseeker_user_id = ? AND status = 'RINGING'
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $call = $stmt->fetch();
    
    if ($call) {
        echo json_encode([
            'ok' => true,
            'has_call' => true,
            'room' => $call['room_code'],
            'created_at' => $call['created_at']
        ]);
    } else {
        echo json_encode([
            'ok' => true,
            'has_call' => false
        ]);
    }
    
} catch (Exception $e) {
    error_log("Check waiting calls error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
?>