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

$config = require __DIR__ . '/../../config/config.php';
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
  $queueId = $svc->enqueueEmployer(Auth::userId(), $criteria, $skillIds);
  $room = $svc->matchEmployerNow($queueId, Auth::userId());

  if (!$room) {
    Session::flash('error', 'No available jobseeker match right now. Try again later.');
    header("Location: /QuickHire/Public/employer-dashboard.php");
    exit;
  }

  // Redirect to call room
  header("Location: /QuickHire/Public/call.php?room=" . urlencode($room));
  exit;

} catch (Exception $e) {
  Session::flash('error', $e->getMessage());
  header("Location: /QuickHire/Public/employer-dashboard.php");
  exit;
}