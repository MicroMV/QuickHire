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

if (Auth::role() !== 'JOBSEEKER') {
  echo json_encode(['ok' => false, 'error' => 'Only jobseekers can use this feature']);
  exit;
}

$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$userId = Auth::userId();

try {
  // Check if jobseeker profile is complete
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_profile_complete = 1");
  $stmt->execute([$userId]);
  $user = $stmt->fetch();
  
  if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Please complete your profile first']);
    exit;
  }

  // FIRST: Check if there's already an incoming call (same as "Join now" button)
  $incomingStmt = $pdo->prepare("
    SELECT room_code FROM calls
    WHERE jobseeker_user_id = ? AND status IN ('RINGING','IN_CALL')
    ORDER BY id DESC LIMIT 1
  ");
  $incomingStmt->execute([$userId]);
  $incoming = $incomingStmt->fetch();
  
  if ($incoming) {
    // There's already a call waiting - return that room (same as "Join now")
    echo json_encode(['ok' => true, 'room' => $incoming['room_code']]);
    exit;
  }

  // SECOND: If no existing call, try to find a new match
  $engine = new MatchEngine();
  $svc = new MatchmakingService($pdo, $engine);
  
  $room = $svc->findNextMatch($userId, 'JOBSEEKER');

  if (!$room) {
    echo json_encode(['ok' => false, 'error' => 'No employers available right now. Please try again later.']);
    exit;
  }

  echo json_encode(['ok' => true, 'room' => $room]);

} catch (Exception $e) {
  error_log("Find employer error: " . $e->getMessage());
  echo json_encode(['ok' => false, 'error' => 'Failed to find match: ' . $e->getMessage()]);
}
