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
$db  = new Database($config['db']);
$pdo = $db->pdo();

$uid  = Auth::userId();
$role = Auth::role();

if ($role !== 'EMPLOYER') {
    echo json_encode(['ok' => false, 'error' => 'Only employers can find next matches.']);
    exit;
}

// Verify the employer owns this room
$stmt = $pdo->prepare("SELECT * FROM calls WHERE room_code = ? LIMIT 1");
$stmt->execute([$currentRoom]);
$call = $stmt->fetch();

if (!$call || (int)$call['employer_user_id'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => 'Room not found or not authorized']);
    exit;
}

$skippedJobseekerId = (int)($call['jobseeker_user_id'] ?? 0);

// ── Step 1: close the current call and notify the current jobseeker ──────────
$pdo->prepare("UPDATE calls SET status='COMPLETED', updated_at=CURRENT_TIMESTAMP WHERE room_code=?")
    ->execute([$currentRoom]);

$pdo->prepare("
    INSERT INTO webrtc_signals (room_code, sender_id, message_type, payload, created_at)
    VALUES (?, ?, 'leave', ?, CURRENT_TIMESTAMP)
")->execute([$currentRoom, $uid, json_encode(['bye' => true, 'reason' => 'employer_next'])]);

// ── Step 2: load employer's saved criteria ────────────────────────────────────
$lastQueue = $pdo->prepare("
    SELECT * FROM matchmaking_queue
    WHERE user_id = ? AND role = 'EMPLOYER'
    ORDER BY id DESC LIMIT 1
");
$lastQueue->execute([$uid]);
$q = $lastQueue->fetch();

if (!$q) {
    echo json_encode(['ok' => false, 'error' => 'No saved preferences found. Please start a new search.']);
    exit;
}

$criteria = [
    'role_title'      => $q['wanted_role'] ?? '',
    'employment_type' => $q['employment_type'] ?? 'FULL_TIME',
    'country'         => $q['wanted_country'] ?? '',
];

$engine = new MatchEngine();
$svc    = new MatchmakingService($pdo, $engine);

// Get required skills from the last queue entry
$reqSkillStmt = $pdo->prepare("SELECT skill_id FROM matchmaking_queue_skills WHERE queue_id = ?");
$reqSkillStmt->execute([(int)$q['id']]);
$reqSkillIds = array_column($reqSkillStmt->fetchAll(PDO::FETCH_ASSOC), 'skill_id');
if (empty($reqSkillIds)) {
    $fallback = $pdo->prepare("SELECT skill_id FROM employer_required_skills WHERE employer_user_id = ?");
    $fallback->execute([$uid]);
    $reqSkillIds = array_column($fallback->fetchAll(PDO::FETCH_ASSOC), 'skill_id');
}
$reqSkillIds = array_map('intval', $reqSkillIds);

// ── Step 3: find a jobseeker who is online RIGHT NOW and matches ──────────────
// "Online" = last_active within the last 30 seconds (update_activity fires every 5–30s)
// Priority order:
//   a) Jobseekers actively waiting (in a WAITING call row with a recent heartbeat)
//   b) Any online jobseeker not in an active call
$newRoom = null;

// --- 3a: jobseekers in a WAITING room with a live heartbeat ---
$waitingJs = $pdo->prepare("
    SELECT DISTINCT u.id AS user_id, p.*
    FROM users u
    JOIN jobseeker_profiles p ON p.user_id = u.id
    JOIN calls c ON c.jobseeker_user_id = u.id AND c.status = 'WAITING'
    WHERE u.role = 'JOBSEEKER'
      AND u.is_profile_complete = 1
      AND u.last_active >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 SECOND)
      AND EXISTS (
          SELECT 1 FROM webrtc_signals s
          WHERE s.room_code = c.room_code
            AND s.sender_id  = u.id
            AND s.message_type = 'heartbeat'
            AND s.created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 SECOND)
      )
");
$waitingJs->execute();
$waitingCandidates = $waitingJs->fetchAll(PDO::FETCH_ASSOC);

// --- 3b: any online jobseeker not in an active/waiting call ---
$onlineJs = $pdo->prepare("
    SELECT u.id AS user_id, p.*
    FROM users u
    JOIN jobseeker_profiles p ON p.user_id = u.id
    WHERE u.role = 'JOBSEEKER'
      AND u.is_profile_complete = 1
      AND u.last_active >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 SECOND)
      AND NOT EXISTS (
          SELECT 1 FROM calls c2
          WHERE c2.status IN ('RINGING','IN_CALL','WAITING')
            AND (c2.employer_user_id = u.id OR c2.jobseeker_user_id = u.id)
      )
");
$onlineJs->execute();
$onlineCandidates = $onlineJs->fetchAll(PDO::FETCH_ASSOC);

// Merge: waiting-room candidates first, then online-only candidates (deduplicated)
$seen = [];
$allCandidates = [];
foreach (array_merge($waitingCandidates, $onlineCandidates) as $js) {
    $jsId = (int)$js['user_id'];
    if (!isset($seen[$jsId])) {
        $seen[$jsId] = true;
        $allCandidates[] = $js;
    }
}

$best      = null;
$bestScore = 0;

foreach ($allCandidates as $js) {
    $jsId = (int)$js['user_id'];
    if ($jsId === $skippedJobseekerId) continue; // skip the one we just left

    $jsSkillStmt = $pdo->prepare("SELECT skill_id FROM jobseeker_skills WHERE jobseeker_user_id = ?");
    $jsSkillStmt->execute([$jsId]);
    $jsSkillIds = array_map('intval', array_column($jsSkillStmt->fetchAll(PDO::FETCH_ASSOC), 'skill_id'));

    $score = $engine->score($criteria, $js, $reqSkillIds, $jsSkillIds);

    error_log("NEXT_MATCH: JS#{$jsId} score={$score}");

    if ($score >= 40 && $score > $bestScore) {
        $bestScore = $score;
        $best      = $jsId;
    }
}

// ── Step 4: clean up employer's old WAITING/RINGING rooms ────────────────────
$pdo->prepare("
    DELETE FROM calls
    WHERE employer_user_id = ? AND status IN ('WAITING','RINGING')
")->execute([$uid]);

try {
    if ($best) {
        // Immediate match found — create RINGING room and redirect employer there now
        $newRoom = 'QH-' . bin2hex(random_bytes(6));
        $pdo->prepare("
            INSERT INTO calls (room_code, employer_user_id, jobseeker_user_id, status)
            VALUES (?, ?, ?, 'RINGING')
        ")->execute([$newRoom, $uid, $best]);

        error_log("NEXT_MATCH: Immediate match JS#{$best} → room {$newRoom}");
        echo json_encode(['ok' => true, 'room' => $newRoom, 'immediate' => true]);
    } else {
        // No one available right now — create a WAITING room so the next
        // jobseeker who polls find_employer.php will join automatically
        $newRoom = $svc->createEmployerRoom($uid, $criteria, []);

        error_log("NEXT_MATCH: No immediate match, created WAITING room {$newRoom}");
        echo json_encode(['ok' => true, 'room' => $newRoom, 'immediate' => false]);
    }
} catch (\Throwable $e) {
    error_log("NEXT_MATCH error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
