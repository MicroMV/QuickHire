<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Csrf;

Session::start();
Auth::requireLogin();

// Never cache the call page â€” each room load must be fresh
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$room = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['room'] ?? '');
if ($room === '') {
    $role = Auth::role();
    $dest = $role === 'EMPLOYER' ? 'employer-dashboard.php' : 'jobseeker-dashboard.php';
    header("Location: /QuickHire/Public/{$dest}");
    exit;
}

// Optional: verify user is allowed in this call room
$config = require __DIR__ . '/../Config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$stmt = $pdo->prepare("SELECT * FROM calls WHERE room_code = ? LIMIT 1");
$stmt->execute([$room]);
$call = $stmt->fetch();
if (!$call) {
    $role = Auth::role();
    $dest = $role === 'EMPLOYER' ? 'employer-dashboard.php' : 'jobseeker-dashboard.php';
    header("Location: /QuickHire/Public/{$dest}");
    exit;
}

$uid = Auth::userId();
$isEmployer = $uid === (int)$call['employer_user_id'];
$isJobseeker = $call['jobseeker_user_id'] && $uid === (int)$call['jobseeker_user_id'];
$publicBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/QuickHire/Public/call.php')), '/');
if ($publicBase === '' || $publicBase === '.') {
    $publicBase = '/';
}
$actionsBase = rtrim($publicBase, '/') . '/actions';

if (!$isEmployer && !$isJobseeker) {
    $role = Auth::role();
    $dest = $role === 'EMPLOYER' ? 'employer-dashboard.php' : 'jobseeker-dashboard.php';
    header("Location: /QuickHire/Public/{$dest}");
    exit;
}

if (in_array($call['status'], ['COMPLETED', 'MISSED', 'CANCELLED'], true)) {
    $role = Auth::role();
    $dest = $role === 'EMPLOYER' ? 'employer-dashboard.php' : 'jobseeker-dashboard.php';
    header("Location: /QuickHire/Public/{$dest}");
    exit;
}

// Update status based on current state
if ($call['status'] === 'WAITING' && $isJobseeker) {
    // Jobseeker joining a waiting room - change to RINGING
    $pdo->prepare("UPDATE calls SET status='RINGING' WHERE room_code=? AND status='WAITING'")->execute([$room]);
    $call['status'] = 'RINGING';
} elseif ($call['status'] === 'RINGING') {
    // Both parties are loading the call page - change to IN_CALL
    $pdo->prepare("UPDATE calls SET status='IN_CALL' WHERE room_code=? AND status='RINGING'")->execute([$room]);
    $call['status'] = 'IN_CALL';
}

// Load employer's saved queue criteria for the "Next Jobseeker" form
$nextJobseekerForm = '';
if ($isEmployer) {
    $qStmt = $pdo->prepare("SELECT mq.*, GROUP_CONCAT(mqs.skill_id) as skill_ids
        FROM matchmaking_queue mq
        LEFT JOIN matchmaking_queue_skills mqs ON mqs.queue_id = mq.id
        WHERE mq.user_id = ? AND mq.role = 'EMPLOYER'
        ORDER BY mq.id DESC LIMIT 1");
    $qStmt->execute([$uid]);
    $savedQ = $qStmt->fetch();

    $csrfToken = Csrf::token();

    if ($savedQ) {
        $skillInputs = '';
        if (!empty($savedQ['skill_ids'])) {
            foreach (explode(',', $savedQ['skill_ids']) as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) $skillInputs .= '<input type="hidden" name="skill_ids[]" value="' . $sid . '">';
            }
        }
        $nextJobseekerForm = '
        <form id="nextJobseekerForm" method="POST" action="/QuickHire/Public/actions/find_match.php" style="display:none;">
            <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">
            <input type="hidden" name="role_title" value="' . htmlspecialchars($savedQ['wanted_role'] ?? '') . '">
            <input type="hidden" name="country" value="' . htmlspecialchars($savedQ['wanted_country'] ?? '') . '">
            <input type="hidden" name="employment_type" value="' . htmlspecialchars($savedQ['employment_type'] ?? 'FULL_TIME') . '">
            ' . $skillInputs . '
        </form>';
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>QuickHire Call</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/QuickHire/Public/assets/css/call.css">
    <link rel="stylesheet" href="/QuickHire/Public/assets/css/dark-theme.css">
</head>

<body class="landing-body">
    <div class="container">
        <!-- Video Section -->
        <div class="video-section">
            <div class="top-bar">
                <div class="badge">Room: <strong><?= htmlspecialchars($room) ?></strong></div>
                <div class="badge">You: <strong><?= htmlspecialchars(Auth::role()) ?></strong></div>
                <div class="badge" id="connectionStatus">Connection: <strong>Connecting...</strong></div>
            </div>

            <div class="video-grid">
                <div class="video-card">
                    <video id="localVideo" autoplay playsinline muted></video>
                    <div class="video-name" id="localVideoName">Loading...</div>
                </div>
                <div class="video-card">
                    <video id="remoteVideo" autoplay playsinline></video>
                    <div class="video-name" id="remoteVideoName">Waiting...</div>
                </div>
            </div>

            <div class="controls">
                <button id="btnCam">ðŸ“¹ Camera: ON</button>
                <button id="btnMic">ðŸŽ¤ Mic: ON</button>
                <button id="btnNext" class="success">Next</button>
                <button id="btnHang" class="danger">ðŸ“ž End Call</button>
            </div>
        </div>
        <?= $nextJobseekerForm ?>

        <!-- Chat Section -->
        <div class="chat-section">
            <div class="chat-header">ðŸ’¬ Chat</div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-input-area">
                <input type="text" id="chatInput" class="chat-input" placeholder="Connect to chat..." disabled />
                <button id="btnSend" class="send-btn" disabled style="opacity:0.4;cursor:not-allowed;">Send</button>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/../Partials/scripts/call-script-1.php'; ?>
</body>

</html>





