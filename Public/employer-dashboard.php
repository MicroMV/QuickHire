<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\ProfileService;
use Rongie\QuickHire\Services\FileUpload;

Session::start();
Auth::requireLogin();

if (Auth::role() !== 'EMPLOYER') {
  header("Location: /QuickHire/Public/index.php");
  exit;
}

$config = require __DIR__ . '/../config/config.php';
$db = new Database($config['db']);

// Load profile details to display on dashboard
$profileService = new ProfileService($db->pdo(), new FileUpload());
$userId = Auth::userId();
$profile = $profileService->getEmployer($userId);

// Get active call if any
$incomingStmt = $db->pdo()->prepare("
  SELECT room_code FROM calls
  WHERE employer_user_id = ? AND status IN ('RINGING','IN_CALL')
  ORDER BY id DESC LIMIT 1
");
$incomingStmt->execute([$userId]);
$incoming = $incomingStmt->fetch();
$incomingRoom = $incoming['room_code'] ?? null;

// Get call history
$historyStmt = $db->pdo()->prepare("
  SELECT c.*, u.email as jobseeker_email, jp.role_title, jp.country
  FROM calls c
  LEFT JOIN users u ON u.id = c.jobseeker_user_id
  LEFT JOIN jobseeker_profiles jp ON jp.user_id = c.jobseeker_user_id
  WHERE c.employer_user_id = ?
  ORDER BY c.created_at DESC
  LIMIT 10
");
$historyStmt->execute([$userId]);
$callHistory = $historyStmt->fetchAll();

// Get available skills for matching
$skillsStmt = $db->pdo()->query("SELECT id, name FROM skills ORDER BY name ASC");
$allSkills = $skillsStmt->fetchAll();

$flashError = Session::flash('error');
$flashSuccess = Session::flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Employer Dashboard - QuickHire</title>

  <link rel="stylesheet" href="/QuickHire/Public/assets/css/landingPage.css">

  <style>
    :root { --primary:#1f6f82; --bg:#f6f7f9; --card:#ffffff; --muted:#6b7280; --line:#e5e7eb; }

    body{ margin:0; font-family:Inter, system-ui, Arial; background:var(--bg); color:#111; }
    .layout{ display:flex; min-height:100vh; }

    /* Sidebar */
    .side{
      width:280px;
      background:var(--card);
      border-right:1px solid var(--line);
      padding:18px;
      position:sticky;
      top:0;
      height:100vh;
      overflow-y:auto;
    }
    .brandRow{ display:flex; align-items:center; gap:10px; padding:6px 6px 14px; }
    .brandRow img{ width:42px; height:42px; border-radius:10px; object-fit:cover; }
    .brandRow .t1{ font-weight:900; }
    .brandRow .t2{ font-size:12px; color:var(--muted); margin-top:2px; }

    .profileCard{
      margin-top:8px;
      border:1px solid var(--line);
      border-radius:16px;
      padding:14px;
      display:flex;
      gap:12px;
      align-items:center;
      background:#fff;
    }
    .avatar{
      width:54px; height:54px; border-radius:16px;
      background:#eaf3f5; overflow:hidden; flex:0 0 auto;
      display:flex; align-items:center; justify-content:center;
      font-weight:900; color:var(--primary);
    }
    .avatar img{ width:100%; height:100%; object-fit:cover; }
    .name{ font-weight:900; }
    .meta{ font-size:12px; color:var(--muted); margin-top:3px; }

    .nav{ margin-top:14px; display:flex; flex-direction:column; gap:10px; }
    .nav a, .nav button{
      display:flex; align-items:center; gap:10px;
      padding:12px 12px;
      border-radius:14px;
      border:1px solid var(--line);
      background:#fff;
      color:#111;
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }
    .nav a:hover, .nav button:hover{ border-color:#cfe5ea; }

    .nav .danger{ border-color:#ffd1d1; color:#7a0b0b; }
    .nav .primary{ background:var(--primary); border-color:var(--primary); color:#fff; }

    /* Main content */
    .main{ flex:1; padding:26px; }
    .topbar{
      display:flex; align-items:flex-start; justify-content:space-between; gap:16px;
      margin-bottom:18px;
    }
    .title{ margin:0; font-size:28px; font-weight:1000; }
    .subtitle{ margin:6px 0 0; color:var(--muted); }

    .notice{ padding:12px 14px; border-radius:14px; font-weight:800; margin-bottom:14px; }
    .notice.err{ background:#ffe1e1; color:#7a0b0b; }
    .notice.ok{ background:#e6ffef; color:#0c5a2a; }

    .grid{
      display:grid;
      grid-template-columns: 1.2fr .8fr;
      gap:16px;
    }
    .card{
      background:var(--card);
      border:1px solid var(--line);
      border-radius:18px;
      padding:18px;
      box-shadow:0 10px 30px rgba(0,0,0,.05);
    }
    .card h3{ margin:0 0 10px; font-size:16px; font-weight:1000; }
    .pillRow{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
    .pill{ padding:8px 10px; border-radius:999px; border:1px solid var(--line); background:#fff; font-weight:800; font-size:12px; color:#111; }

    .btn{
      display:inline-flex; align-items:center; justify-content:center;
      padding:12px 16px; border-radius:14px; border:1px solid transparent;
      font-weight:900; cursor:pointer; text-decoration:none;
    }
    .btn.primary{ background:var(--primary); color:#fff; }
    .btn.outline{ background:#fff; border-color:var(--line); color:#111; }

    .history-table{ width:100%; border-collapse:collapse; margin-top:10px; }
    .history-table th, .history-table td{ padding:10px; text-align:left; border-bottom:1px solid var(--line); font-size:13px; }
    .history-table th{ font-weight:900; background:#f9fafb; }
    .status-badge{ display:inline-block; padding:4px 8px; border-radius:6px; font-weight:800; font-size:11px; }
    .status-badge.ringing{ background:#fef3c7; color:#92400e; }
    .status-badge.in-call{ background:#dbeafe; color:#1e40af; }
    .status-badge.completed{ background:#dcfce7; color:#166534; }

    @media (max-width: 980px){
      .grid{ grid-template-columns:1fr; }
      .side{ position:relative; height:auto; width:100%; border-right:0; border-bottom:1px solid var(--line); }
      .layout{ flex-direction:column; }
    }
  </style>
</head>
<body>

<div class="layout">
  <!-- SIDEBAR -->
  <aside class="side">
    <div class="brandRow">
      <div>
        <div class="t1">QuickHire</div>
        <div class="t2">Employer Dashboard</div>
      </div>
    </div>

    <div class="profileCard">
      <div class="avatar">
        <?php if (!empty($profile['profile_picture_url'])): ?>
          <img src="/QuickHire/Public/<?= htmlspecialchars($profile['profile_picture_url']) ?>" alt="Avatar">
        <?php else: ?>
          E
        <?php endif; ?>
      </div>
      <div>
        <div class="name">
          <?= htmlspecialchars(($profile['company_name'] ?? 'Your Company')) ?>
        </div>
        <div class="meta">
          <?= htmlspecialchars(($profile['country'] ?? 'Country not set')) ?>
        </div>
      </div>
    </div>

    <nav class="nav">
      <button class="primary" id="btnFindMatch">🔍 Find Jobseeker</button>

      <button id="btnEditProfile">Edit Profile</button>
      <a href="/QuickHire/Public/settings.php">Settings</a>

      <form method="POST" action="/QuickHire/Public/actions/logout.php" style="margin:0;">
        <button class="danger" type="submit">Logout</button>
      </form>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <?php if ($incomingRoom): ?>
      <div class="notice ok" style="margin-bottom:14px;">
        🔔 Active call with jobseeker! Click "Find Jobseeker" to join the call.
      </div>
    <?php else: ?>
      <div class="notice" style="background:#f0f4f8; color:#1f6f82; margin-bottom:14px;">
        ⏳ Ready to find jobseekers? Click "Find Jobseeker" to start matching or join active calls.
      </div>
    <?php endif; ?>

    <div class="topbar">
      <div>
        <h1 class="title">Welcome back 👋</h1>
        <p class="subtitle">
          Find and connect with qualified jobseekers through skill-based matching.
        </p>
      </div>

      <div style="display:flex; gap:10px; align-items:center;">
        <!-- Removed Update Profile button -->
      </div>
    </div>

    <?php if ($flashError): ?><div class="notice err"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
    <?php if ($flashSuccess): ?><div class="notice ok"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>

    <div class="grid" id="dashboardContent">
      <!-- Status card -->
      <section class="card">
        <h3>📊 Matching Status</h3>
        <p style="margin:0; color:var(--muted); line-height:1.5;">
          Use skill-based matching to find the perfect jobseeker for your role. The system will match candidates based on their skills, role title, country, and English proficiency.
        </p>

        <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
          <button class="btn primary" id="btnFindMatch2">Find Jobseeker</button>
          <button class="btn outline" id="btnEditProfile2">Edit Profile</button>
        </div>
      </section>

      <!-- Profile summary -->
      <aside class="card">
        <h3>Your Company</h3>
        <div class="pillRow">
          <span class="pill">Company: <?= htmlspecialchars($profile['company_name'] ?? '—') ?></span>
          <span class="pill">Country: <?= htmlspecialchars($profile['country'] ?? '—') ?></span>
        </div>

        <div style="margin-top:14px; color:var(--muted); line-height:1.5;">
          <strong style="color:#111;">Profile Status:</strong><br>
          <?php if (!empty($profile['company_name']) && !empty($profile['country'])): ?>
            ✅ Profile complete and active
          <?php else: ?>
            ⚠️ Please complete your profile to start matching
          <?php endif; ?>
        </div>
      </aside>
    </div>

    <!-- Profile Edit Form (Hidden by default) -->
    <div class="card" id="profileEditContent" style="display:none;">
      <div style="margin-bottom:20px;">
        <h3>✏️ Edit Your Profile</h3>
      </div>

      <form method="POST" action="/QuickHire/Public/actions/save_profile.php" enctype="multipart/form-data" id="profileForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Rongie\QuickHire\Core\Csrf::token()) ?>">
        <input type="hidden" name="profile_type" value="EMPLOYER">

        <div class="grid">
          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:900; margin-bottom:6px;">Profile Picture (JPG/PNG/WEBP)</label>
            <input type="file" name="profile_picture" accept="image/*" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Country *</label>
            <input name="country" value="<?= htmlspecialchars($profile['country'] ?? '') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Business Name / Company Name *</label>
            <input name="company_name" value="<?= htmlspecialchars($profile['company_name'] ?? '') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>
        </div>

        <div style="margin-top:20px; display:flex; gap:10px;">
          <button type="submit" class="btn primary" style="flex:1;">Save Profile</button>
          <button type="button" class="btn outline" id="btnCancelEdit" style="flex:1;">Cancel</button>
        </div>
      </form>
    </div>

    <!-- Call History -->
    <section class="card" style="margin-top:16px;">
      <h3>📞 Recent Calls</h3>
      <?php if (!empty($callHistory)): ?>
        <table class="history-table">
          <thead>
            <tr>
              <th>Jobseeker</th>
              <th>Role</th>
              <th>Country</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($callHistory as $call): ?>
              <tr>
                <td><?= htmlspecialchars($call['jobseeker_email'] ?? 'Unknown') ?></td>
                <td><?= htmlspecialchars($call['role_title'] ?? '—') ?></td>
                <td><?= htmlspecialchars($call['country'] ?? '—') ?></td>
                <td>
                  <span class="status-badge <?= strtolower(str_replace('_', '-', $call['status'])) ?>">
                    <?= htmlspecialchars($call['status']) ?>
                  </span>
                </td>
                <td><?= date('M d, Y H:i', strtotime($call['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:var(--muted); margin:0;">No calls yet. Start by finding a jobseeker match!</p>
      <?php endif; ?>
    </section>
  </main>
</div>
<script>
  const btnFindMatch = document.getElementById('btnFindMatch');
  const btnFindMatch2 = document.getElementById('btnFindMatch2');
  const btnEditProfile = document.getElementById('btnEditProfile');
  const btnEditProfile2 = document.getElementById('btnEditProfile2');
  const btnCancelEdit = document.getElementById('btnCancelEdit');
  
  const dashboardContent = document.getElementById('dashboardContent');
  const profileEditContent = document.getElementById('profileEditContent');

  async function findJobseeker() {
    // Disable buttons to prevent multiple clicks
    btnFindMatch.disabled = true;
    btnFindMatch2.disabled = true;
    btnFindMatch.textContent = '🔍 Searching...';
    btnFindMatch2.textContent = 'Searching...';

    try {
      // First check if there's already an active call (same as "Join now")
      const checkResponse = await fetch('/QuickHire/Public/actions/check_active_call.php');
      const checkData = await checkResponse.json();
      
      if (checkData.ok && checkData.room) {
        // There's already an active call - join it directly
        window.location.href = '/QuickHire/Public/call.php?room=' + encodeURIComponent(checkData.room);
        return;
      }

      // No active call, proceed with matching
      const roleTitle = prompt('Enter role title (e.g., Web Developer):');
      if (!roleTitle) {
        // Re-enable buttons if user cancels
        btnFindMatch.disabled = false;
        btnFindMatch2.disabled = false;
        btnFindMatch.textContent = '🔍 Find Jobseeker';
        btnFindMatch2.textContent = 'Find Jobseeker';
        return;
      }

      const country = prompt('Enter country (e.g., Philippines):');
      if (!country) {
        // Re-enable buttons if user cancels
        btnFindMatch.disabled = false;
        btnFindMatch2.disabled = false;
        btnFindMatch.textContent = '🔍 Find Jobseeker';
        btnFindMatch2.textContent = 'Find Jobseeker';
        return;
      }

      const formData = new FormData();
      formData.append('role_title', roleTitle);
      formData.append('country', country);
      formData.append('employment_type', 'FULL_TIME');
      formData.append('skill_ids', []); // No specific skills required

      const response = await fetch('/QuickHire/Public/actions/find_match.php', {
        method: 'POST',
        body: formData
      });

      // Check if response is a redirect (successful match)
      if (response.redirected) {
        window.location.href = response.url;
        return;
      }

      // If not redirected, check for error
      const text = await response.text();
      if (text.includes('No available jobseeker')) {
        alert('No jobseekers available right now. Please try again later.');
      } else {
        // Try to extract room from response if it's a call page
        const roomMatch = text.match(/room=([^"&]+)/);
        if (roomMatch) {
          window.location.href = '/QuickHire/Public/call.php?room=' + roomMatch[1];
          return;
        }
        alert('No matches found. Please try again later.');
      }
    } catch (error) {
      alert('Connection error. Please try again.');
    }

    // Re-enable buttons
    btnFindMatch.disabled = false;
    btnFindMatch2.disabled = false;
    btnFindMatch.textContent = '🔍 Find Jobseeker';
    btnFindMatch2.textContent = 'Find Jobseeker';
  }

  function showProfileEdit() {
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'block';
  }

  function showDashboard() {
    dashboardContent.style.display = 'grid';
    profileEditContent.style.display = 'none';
  }

  btnFindMatch.addEventListener('click', findJobseeker);
  btnFindMatch2.addEventListener('click', findJobseeker);
  btnEditProfile.addEventListener('click', showProfileEdit);
  btnEditProfile2.addEventListener('click', showProfileEdit);
  btnCancelEdit.addEventListener('click', showDashboard);
</script>

</body>
</html>
