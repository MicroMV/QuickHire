<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

if (Auth::role() !== 'JOBSEEKER') {
  header("Location: /QuickHire/Public/index.php");
  exit;
}

$config = require __DIR__ . '/../Config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$userId = Auth::userId();

// Get jobseeker info
$stmt = $pdo->prepare("SELECT * FROM jobseeker_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$jobseeker = $stmt->fetch();

if (!$jobseeker) {
  header("Location: /QuickHire/Public/jobseeker-dashboard.php");
  exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Find Employer - QuickHire</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/landingPage.css">
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/find-employer.css">
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/dark-theme.css">
</head>
<body class="landing-body">
  <div class="container">
    <!-- MATCHING SCREEN -->
    <div class="matching-screen active" id="matchingScreen">
      <div class="spinner"></div>
      <div class="matching-text">Finding an employer...</div>
      <div class="matching-subtext">Please wait while we search for the perfect match</div>
    </div>

    <!-- CALL SCREEN -->
    <div class="call-screen" id="callScreen">
      <div class="call-header">
        <div class="call-info">
          <div>
            <div style="font-size: 12px; color: #cbd5e1;">Connected with</div>
            <div style="font-weight: 900; font-size: 16px;" id="employerName">Employer</div>
          </div>
        </div>
        <div class="call-timer" id="callTimer">00:00</div>
      </div>

      <div class="video-grid">
        <div class="video-container">
          <div class="video-label">You</div>
          <video id="localVideo" autoplay playsinline muted></video>
        </div>
        <div class="video-container">
          <div class="video-label">Employer</div>
          <video id="remoteVideo" autoplay playsinline></video>
        </div>
      </div>

      <div class="controls">
        <button class="control-btn" id="btnCam">ðŸ“¹ Camera: ON</button>
        <button class="control-btn" id="btnMic">ðŸŽ¤ Mic: ON</button>
        <button class="control-btn danger" id="btnHang">End Call</button>
      </div>

      <div class="log" id="log"></div>
    </div>

    <!-- ERROR SCREEN -->
    <div class="error-screen" id="errorScreen">
      <div class="error-icon">âš ï¸</div>
      <div class="error-title" id="errorTitle">No Match Found</div>
      <div class="error-message" id="errorMessage">Unable to find a suitable employer match at this time.</div>
      <a href="/QuickHire/Public/jobseeker-dashboard.php" class="btn-primary">Back to Dashboard</a>
    </div>
  </div>

  <?php require __DIR__ . '/../Partials/scripts/find-employer-script-1.php'; ?>
</body>
</html>





