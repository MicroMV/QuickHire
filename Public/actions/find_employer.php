<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MatchmakingService;
use Rongie\QuickHire\Models\MatchEngine;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

// ONLY JOBSEEKERS CAN USE THIS
if (Auth::role() !== 'JOBSEEKER') {
  echo json_encode(['ok' => false, 'error' => 'Only jobseekers can use this feature']);
  exit;
}

$config = require __DIR__ . '/../../Config/config.php';
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

  // CLEANUP: Delete old rooms before searching
  $pdo->prepare("
    DELETE FROM calls 
    WHERE status IN ('WAITING', 'RINGING', 'MISSED')
    AND created_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)
  ")->execute();
  
  error_log("FIND_EMPLOYER: Cleaned up old rooms");

  // Look for WAITING employer rooms (rooms created by employers)
  $engine = new MatchEngine();
  $matchmakingService = new MatchmakingService($db->pdo(), $engine);
  $availableRooms = $matchmakingService->getAvailableEmployerRooms($userId);

  error_log("FIND_EMPLOYER: Jobseeker $userId looking for rooms");
  error_log("FIND_EMPLOYER: Found " . count($availableRooms) . " available rooms");

  // If no rooms available
  if (empty($availableRooms)) {
    error_log("FIND_EMPLOYER: No rooms available");
    echo json_encode([
      'ok' => false, 
      'error' => 'No employers are currently looking for candidates. Please wait.',
      'waiting' => true
    ]);
    exit;
  }

  // Get the best matching room (already sorted by score)
  $bestRoom = $availableRooms[0];
  
  error_log("FIND_EMPLOYER: Best match - Room: {$bestRoom['room_code']}, Score: {$bestRoom['match_score']}");

  // Join the employer's room
  $joined = $matchmakingService->joinEmployerRoom($bestRoom['room_code'], $userId);
  
  if ($joined) {
    error_log("FIND_EMPLOYER: Successfully joined room {$bestRoom['room_code']}");
    echo json_encode([
      'ok' => true, 
      'room' => $bestRoom['room_code'],
      'match_score' => $bestRoom['match_score'],
      'employer_name' => $bestRoom['employer_name']
    ]);
  } else {
    error_log("FIND_EMPLOYER: Failed to join room {$bestRoom['room_code']}");
    echo json_encode([
      'ok' => false, 
      'error' => 'Failed to join employer room. Room may have been taken by another jobseeker.'
    ]);
  }

} catch (Exception $e) {
  error_log("FIND_EMPLOYER ERROR: " . $e->getMessage());
  echo json_encode(['ok' => false, 'error' => 'System error: ' . $e->getMessage()]);
}
?>