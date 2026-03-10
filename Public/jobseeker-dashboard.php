<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\ProfileService;
use Rongie\QuickHire\Services\FileUpload;

Session::start();
Auth::requireLogin();

if (Auth::role() !== 'JOBSEEKER') {
  header("Location: /QuickHire/Public/index.php");
  exit;
}

$config = require __DIR__ . '/../config/config.php';
$db = new Database($config['db']);

// Load profile details to display on dashboard
$profileService = new ProfileService($db->pdo(), new FileUpload());
$userId = Auth::userId();
$profile = $profileService->getJobseeker($userId);
$incomingStmt = $db->pdo()->prepare("
  SELECT room_code FROM calls
  WHERE jobseeker_user_id = ? AND status IN ('RINGING','IN_CALL')
  ORDER BY id DESC LIMIT 1
");
$incomingStmt->execute([$userId]);
$incoming = $incomingStmt->fetch();
$incomingRoom = $incoming['room_code'] ?? null;
$flashError = Session::flash('error');
$flashSuccess = Session::flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Jobseeker Dashboard - QuickHire</title>

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
        <div class="t2">Jobseeker Dashboard</div>
      </div>
    </div>

    <div class="profileCard">
      <div class="avatar">
        <?php if (!empty($profile['profile_picture_url'])): ?>
          <img src="/QuickHire/Public/<?= htmlspecialchars($profile['profile_picture_url']) ?>" alt="Avatar">
        <?php else: ?>
          <?= strtoupper(substr((string)Session::get('role','J'), 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="name">
          <?= htmlspecialchars(($profile['role_title'] ?? 'Your Role')) ?>
        </div>
        <div class="meta">
          <?= htmlspecialchars(($profile['country'] ?? 'Country not set')) ?>
          • $<?= htmlspecialchars((string)($profile['rate_per_hour'] ?? '0')) ?>/hr
        </div>
      </div>
    </div>

    <nav class="nav">
      <button class="primary" id="btnFindEmployer">🔍 Find Employer</button>

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
        🔔 Incoming call from employer! Click "Find Employer" to join the call.
      </div>
    <?php else: ?>
      <div class="notice" style="background:#f0f4f8; color:#1f6f82; margin-bottom:14px;">
        ⏳ Ready to find employers? Click "Find Employer" to start matching or join waiting calls.
      </div>
    <?php endif; ?>

    <div class="topbar">
      <div>
        <h1 class="title">Welcome back 👋</h1>
        <p class="subtitle">
          Your profile is live. Employers will automatically match with you based on your skills and availability.
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
        <h3>📊 Your Status</h3>
        <p style="margin:0; color:var(--muted); line-height:1.5;">
          Your profile is active and visible to employers. When an employer finds a match, you'll receive an incoming call notification automatically.
        </p>

        <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
          <button class="btn primary" id="btnFindEmployer2">Find Employer</button>
          <button class="btn outline" id="btnEditProfile2">Edit Profile</button>
        </div>
      </section>

      <!-- Profile summary -->
      <aside class="card">
        <h3>Your Profile Summary</h3>
        <div class="pillRow">
          <span class="pill">Role: <?= htmlspecialchars($profile['role_title'] ?? '—') ?></span>
          <span class="pill">Availability: <?= htmlspecialchars($profile['available_time'] ?? '—') ?></span>
          <span class="pill">English: <?= htmlspecialchars($profile['english_mastery'] ?? '—') ?></span>
        </div>

        <div style="margin-top:14px; color:var(--muted); line-height:1.5;">
          <strong style="color:#111;">Overview:</strong><br>
          <?= htmlspecialchars($profile['profile_description'] ?? 'No overview yet. Update your profile to attract employers.') ?>
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
        <input type="hidden" name="profile_type" value="JOBSEEKER">

        <div class="grid">
          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:900; margin-bottom:6px;">Profile Picture (JPG/PNG)</label>
            <input type="file" name="profile_picture" accept="image/*" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Desired Job Role *</label>
            <input name="role_title" value="<?= htmlspecialchars($profile['role_title'] ?? '') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Rate per Hour (USD) *</label>
            <input name="rate_per_hour" type="number" step="0.01" value="<?= htmlspecialchars($profile['rate_per_hour'] ?? '') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Available Hours per Day *</label>
            <input name="available_time" value="<?= htmlspecialchars($profile['available_time'] ?? '') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Country *</label>
            <input name="country" value="<?= htmlspecialchars($profile['country'] ?? '') ?>" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">English Mastery *</label>
            <select name="english_mastery" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <?php
                $levels = ['BEGINNER','INTERMEDIATE','ADVANCED','FLUENT','NATIVE'];
                $cur = $profile['english_mastery'] ?? '';
                echo '<option value="">Select</option>';
                foreach ($levels as $lv) {
                  $sel = ($cur === $lv) ? 'selected' : '';
                  echo "<option value=\"$lv\" $sel>$lv</option>";
                }
              ?>
            </select>
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Bachelor's Degree</label>
            <input name="bachelors_degree" value="<?= htmlspecialchars($profile['bachelors_degree'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Portfolio/Website</label>
            <input name="portfolio_url" value="<?= htmlspecialchars($profile['portfolio_url'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Age</label>
            <input name="age" type="number" value="<?= htmlspecialchars($profile['age'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Gender</label>
            <select name="gender" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <?php $g = $profile['gender'] ?? ''; ?>
              <option value="">Prefer not to say</option>
              <option value="MALE" <?= $g==='MALE'?'selected':'' ?>>Male</option>
              <option value="FEMALE" <?= $g==='FEMALE'?'selected':'' ?>>Female</option>
              <option value="OTHER" <?= $g==='OTHER'?'selected':'' ?>>Other</option>
            </select>
          </div>

          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:900; margin-bottom:6px;">Profile Description *</label>
            <textarea name="profile_description" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px; min-height:100px; resize:vertical;"><?= htmlspecialchars($profile['profile_description'] ?? '') ?></textarea>
          </div>

          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:900; margin-bottom:6px;">Resume (PDF)</label>
            <input type="file" name="resume" accept="application/pdf" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
            <div style="font-size:12px; color:var(--muted); margin-top:6px;">If you upload a new one, it replaces the old resume.</div>
          </div>
        </div>

        <div style="margin-top:20px; display:flex; gap:10px;">
          <button type="submit" class="btn primary" style="flex:1;">Save Profile</button>
          <button type="button" class="btn outline" id="btnCancelEdit" style="flex:1;">Cancel</button>
        </div>
      </form>
    </div>
  </main>
</div>

</body>

<script>
  const btnFindEmployer = document.getElementById('btnFindEmployer');
  const btnFindEmployer2 = document.getElementById('btnFindEmployer2');
  const btnEditProfile = document.getElementById('btnEditProfile');
  const btnEditProfile2 = document.getElementById('btnEditProfile2');
  const btnCancelEdit = document.getElementById('btnCancelEdit');
  
  const dashboardContent = document.getElementById('dashboardContent');
  const profileEditContent = document.getElementById('profileEditContent');

  async function findEmployer() {
    // Disable buttons to prevent multiple clicks
    btnFindEmployer.disabled = true;
    btnFindEmployer2.disabled = true;
    btnFindEmployer.textContent = '🔍 Searching...';
    btnFindEmployer2.textContent = 'Searching...';

    try {
      const response = await fetch('/QuickHire/Public/actions/find_employer.php');
      const data = await response.json();

      if (data.ok && data.room) {
        // Direct redirect to call page (same as "Join now")
        window.location.href = '/QuickHire/Public/call.php?room=' + encodeURIComponent(data.room);
      } else {
        alert(data.error || 'No employers available right now. Please try again later.');
        // Re-enable buttons
        btnFindEmployer.disabled = false;
        btnFindEmployer2.disabled = false;
        btnFindEmployer.textContent = '🔍 Find Employer';
        btnFindEmployer2.textContent = 'Find Employer';
      }
    } catch (error) {
      alert('Connection error. Please try again.');
      // Re-enable buttons
      btnFindEmployer.disabled = false;
      btnFindEmployer2.disabled = false;
      btnFindEmployer.textContent = '🔍 Find Employer';
      btnFindEmployer2.textContent = 'Find Employer';
    }
  }

  function showProfileEdit() {
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'block';
  }

  function showDashboard() {
    dashboardContent.style.display = 'grid';
    profileEditContent.style.display = 'none';
  }

  btnFindEmployer.addEventListener('click', findEmployer);
  btnFindEmployer2.addEventListener('click', findEmployer);
  btnEditProfile.addEventListener('click', showProfileEdit);
  btnEditProfile2.addEventListener('click', showProfileEdit);
  btnCancelEdit.addEventListener('click', showDashboard);
</script>

</html>
