<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Models\MatchEngine;
use Rongie\QuickHire\Services\MatchmakingService;

Session::start();
Auth::requireLogin();

if (Auth::role() !== 'EMPLOYER') {
  http_response_code(403);
  Session::flash('error', 'Only employers can search for jobseekers');
  header("Location: /QuickHire/Public/index.php");
  exit;
}

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);

$engine = new MatchEngine();
$svc = new MatchmakingService($db->pdo(), $engine);

// Validate required fields
$roleTitle = trim($_POST['role_title'] ?? '');
$country = trim($_POST['country'] ?? '');
$employmentType = $_POST['employment_type'] ?? 'PART_TIME';

if (empty($roleTitle) || empty($country)) {
  Session::flash('error', 'Please fill in all required fields (Role Title and Country).');
  header("Location: /QuickHire/Public/employer-dashboard.php");
  exit;
}

// Skill IDs array from form (checkboxes)
$skillIds = $_POST['skill_ids'] ?? [];
$skillIds = array_map('intval', is_array($skillIds) ? $skillIds : []);

$criteria = [
  'role_title' => $roleTitle,
  'employment_type' => $employmentType,
  'country' => $country
];

try {
  // CLEANUP: Delete stale rooms older than 2 minutes (only WAITING/RINGING/MISSED, not IN_CALL)
  $pdo = $db->pdo();
  $pdo->prepare("
    DELETE FROM calls 
    WHERE status IN ('WAITING', 'RINGING', 'MISSED')
    AND created_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    AND employer_user_id = ?
  ")->execute([Auth::userId()]);
  
  // Also delete this employer's older completed rooms to keep the table clean
  $pdo->prepare("
    DELETE FROM calls 
    WHERE employer_user_id = ? 
    AND status IN ('WAITING', 'RINGING', 'MISSED', 'COMPLETED')
  ")->execute([Auth::userId()]);
  
  error_log("FIND_MATCH: Cleaned up old rooms");
  
  // Always create a room for the employer, regardless of matches
  $room = $svc->createEmployerRoom(Auth::userId(), $criteria, $skillIds);

  if (!$room) {
    Session::flash('error', 'Failed to create room. Please try again.');
    header("Location: /QuickHire/Public/employer-dashboard.php");
    exit;
  }

  error_log("FIND_MATCH: Created new room $room for employer " . Auth::userId());

  // Redirect to call room immediately
  header("Location: /QuickHire/Public/call.php?room=" . urlencode($room));
  exit;

} catch (Exception $e) {
  error_log("Room creation error: " . $e->getMessage());
  Session::flash('error', 'Failed to create room: ' . $e->getMessage());
  header("Location: /QuickHire/Public/employer-dashboard.php");
  exit;
}