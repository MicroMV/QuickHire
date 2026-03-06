<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MatchmakingService;
use Rongie\QuickHire\Models\MatchEngine;

Session::start();
Auth::requireLogin();

if (Auth::role() !== 'JOBSEEKER') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Only jobseekers can search for employers']);
  exit;
}

$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$userId = Auth::userId();

// Check if jobseeker already has an active call
$stmt = $pdo->prepare("
  SELECT room_code FROM calls
  WHERE jobseeker_user_id = ? AND status IN ('RINGING','IN_CALL')
  LIMIT 1
");
$stmt->execute([$userId]);
$existingCall = $stmt->fetch();

if ($existingCall) {
  echo json_encode(['ok' => true, 'room' => $existingCall['room_code'], 'status' => 'existing']);
  exit;
}

// Get all active employer queues that are looking for matches
$stmt = $pdo->prepare("
  SELECT mq.id, mq.user_id as employer_id, mq.wanted_role, mq.wanted_country, mq.employment_type
  FROM matchmaking_queue mq
  WHERE mq.role = 'EMPLOYER' AND mq.is_active = 1
  ORDER BY mq.created_at DESC
  LIMIT 10
");
$stmt->execute();
$employers = $stmt->fetchAll();

if (empty($employers)) {
  echo json_encode(['ok' => false, 'error' => 'No employers available right now. Try again later.']);
  exit;
}

// Get jobseeker profile
$stmt = $pdo->prepare("SELECT * FROM jobseeker_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$jobseekerProfile = $stmt->fetch();

if (!$jobseekerProfile) {
  echo json_encode(['ok' => false, 'error' => 'Please complete your profile first.']);
  exit;
}

// Try to match with the first available employer
$engine = new MatchEngine();
$matched = false;
$matchedQueueId = null;
$matchedEmployerId = null;

foreach ($employers as $emp) {
  $empQueueId = (int)$emp['id'];
  $empId = (int)$emp['employer_id'];

  // Get employer's required skills
  $stmt = $pdo->prepare("SELECT skill_id FROM matchmaking_queue_skills WHERE queue_id = ?");
  $stmt->execute([$empQueueId]);
  $requiredSkills = array_map(fn($x) => (int)$x['skill_id'], $stmt->fetchAll());

  // Get jobseeker's skills
  $stmt = $pdo->prepare("SELECT skill_id FROM jobseeker_skills WHERE jobseeker_user_id = ?");
  $stmt->execute([$userId]);
  $jobseekerSkills = array_map(fn($x) => (int)$x['skill_id'], $stmt->fetchAll());

  // Score the match
  $score = $engine->score(
    [
      'role_title' => $emp['wanted_role'] ?? '',
      'employment_type' => $emp['employment_type'] ?? 'PART_TIME',
      'country' => $emp['wanted_country'] ?? ''
    ],
    $jobseekerProfile,
    $requiredSkills,
    $jobseekerSkills
  );

  if ($score >= 80) {
    $matchedQueueId = $empQueueId;
    $matchedEmployerId = $empId;
    $matched = true;
    break;
  }
}

if (!$matched) {
  echo json_encode(['ok' => false, 'error' => 'No suitable match found. Your profile may not meet current employer requirements.']);
  exit;
}

// Create call room
$room = 'QH-' . bin2hex(random_bytes(6));

try {
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    INSERT INTO calls (room_code, employer_user_id, jobseeker_user_id, status)
    VALUES (?, ?, ?, 'RINGING')
  ");
  $stmt->execute([$room, $matchedEmployerId, $userId]);

  // Deactivate the queue
  $stmt = $pdo->prepare("UPDATE matchmaking_queue SET is_active = 0 WHERE id = ?");
  $stmt->execute([$matchedQueueId]);

  $pdo->commit();

  echo json_encode(['ok' => true, 'room' => $room, 'status' => 'matched']);
} catch (\Throwable $e) {
  $pdo->rollBack();
  echo json_encode(['ok' => false, 'error' => 'Failed to create match']);
}
