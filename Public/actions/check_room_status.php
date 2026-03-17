<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

$room = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['room'] ?? '');
if (empty($room)) {
    echo json_encode(['ok' => false, 'error' => 'Room code required']);
    exit;
}

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);

try {
    $stmt = $db->pdo()->prepare("SELECT status, jobseeker_user_id FROM calls WHERE room_code = ? LIMIT 1");
    $stmt->execute([$room]);
    $call = $stmt->fetch();
    
    if (!$call) {
        echo json_encode(['ok' => false, 'error' => 'Room not found']);
        exit;
    }
    
    // Verify user has access to this room
    $uid = Auth::userId();
    $hasAccess = false;
    
    if (Auth::role() === 'EMPLOYER') {
        $empStmt = $db->pdo()->prepare("SELECT employer_user_id FROM calls WHERE room_code = ? AND employer_user_id = ?");
        $empStmt->execute([$room, $uid]);
        $hasAccess = (bool)$empStmt->fetch();
    } elseif (Auth::role() === 'JOBSEEKER') {
        $jsStmt = $db->pdo()->prepare("SELECT jobseeker_user_id FROM calls WHERE room_code = ? AND jobseeker_user_id = ?");
        $jsStmt->execute([$room, $uid]);
        $hasAccess = (bool)$jsStmt->fetch();
    }
    
    if (!$hasAccess) {
        echo json_encode(['ok' => false, 'error' => 'Access denied']);
        exit;
    }
    
    echo json_encode([
        'ok' => true,
        'status' => $call['status'],
        'has_jobseeker' => !empty($call['jobseeker_user_id'])
    ]);
    
} catch (Exception $e) {
    error_log("Check room status error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
?>