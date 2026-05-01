<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Models\MatchEngine;
use Rongie\QuickHire\Services\MatchmakingService;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$currentRoom = $input['room'] ?? '';

if (empty($currentRoom)) {
    echo json_encode(['ok' => false, 'error' => 'Missing room']);
    exit;
}

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

// Get current call info
$stmt = $pdo->prepare("SELECT * FROM calls WHERE room_code = ? LIMIT 1");
$stmt->execute([$currentRoom]);
$call = $stmt->fetch();

if (!$call) {
    echo json_encode(['ok' => false, 'error' => 'Room not found']);
    exit;
}

$uid = Auth::userId();
$role = Auth::role();

if ($uid !== (int)$call['employer_user_id'] && $uid !== (int)$call['jobseeker_user_id']) {
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

// Mark current call as completed AND send leave signal to notify the jobseeker
$pdo->prepare("UPDATE calls SET status='COMPLETED', updated_at=CURRENT_TIMESTAMP WHERE room_code=?")->execute([$currentRoom]);

// Insert leave signal so the jobseeker's polling picks it up immediately
$pdo->prepare("
    INSERT INTO webrtc_signals (room_code, sender_id, message_type, payload, created_at)
    VALUES (?, ?, 'leave', ?, CURRENT_TIMESTAMP)
")->execute([$currentRoom, $uid, json_encode(['bye' => true, 'reason' => 'employer_next'])]);

// Handle next match based on role
if ($role === 'JOBSEEKER') {
    // Jobseekers cannot initiate next matches - they should return to dashboard
    echo json_encode(['ok' => false, 'error' => 'Jobseekers cannot initiate new matches. Returning to dashboard.', 'redirect' => 'dashboard']);
    exit;
}

// Only employers can find next matches
$partnerId = (int)$call['jobseeker_user_id']; // Skip current jobseeker

// Find next match
$engine = new MatchEngine();
$svc = new MatchmakingService($pdo, $engine);

try {
    $newRoom = $svc->findNextMatch($uid, $role, $partnerId);
    
    if ($newRoom) {
        echo json_encode(['ok' => true, 'room' => $newRoom]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No more jobseekers available']);
    }
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
