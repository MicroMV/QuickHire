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

$flashError = Session::flash('error');
$flashSuccess = Session::flash('success');

// (Temporary) Call room link placeholder. Later you’ll generate this from matches/job requests.
$callLink = $profile['call_link'] ?? ''; // not in DB yet; can be empty for now
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

    /* Call modal */
    .modal{ position:fixed; inset:0; display:none; z-index:60; }
    .modal.is-open{ display:block; }
    .backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.35); }
    .panel{
      position:relative;
      max-width:520px;
      margin:10vh auto;
      background:#fff;
      border-radius:18px;
      padding:18px;
      border:1px solid var(--line);
      box-shadow:0 20px 60px rgba(0,0,0,.2);
    }
    .close{ position:absolute; right:12px; top:10px; border:0; background:transparent; font-size:22px; cursor:pointer; }
    .field{ margin-top:10px; }
    .field label{ display:block; font-weight:900; margin-bottom:6px; }
    .field input{ width:100%; padding:10px 12px; border-radius:12px; border:1px solid var(--line); }

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
      <img src="/QuickHire/Public/images/quickhire-logo.jpg" alt="QuickHire">
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
          • ₱<?= htmlspecialchars((string)($profile['rate_per_hour'] ?? '0')) ?>/hr
        </div>
      </div>
    </div>

    <nav class="nav">
      <a class="primary" href="#" id="openCall">Jump into Call</a>

      <a href="/QuickHire/Public/complete-profile.php">Edit Profile</a>
      <a href="/QuickHire/Public/jobseeker-skills.php">Update Skills</a>
      <a href="/QuickHire/Public/jobseeker-resume.php">Upload / Update Resume</a>
      <a href="/QuickHire/Public/settings.php">Settings</a>

      <form method="POST" action="/QuickHire/Public/actions/logout.php" style="margin:0;">
        <button class="danger" type="submit">Logout</button>
      </form>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="topbar">
      <div>
        <h1 class="title">Welcome back 👋</h1>
        <p class="subtitle">
          Here’s your QuickHire home. Use the sidebar to update your profile, then join calls when employers invite you.
        </p>
      </div>

      <div style="display:flex; gap:10px; align-items:center;">
        <a class="btn outline" href="/QuickHire/Public/complete-profile.php">Update Profile</a>
        <button class="btn primary" id="openCallTop" type="button">Jump into Call</button>
      </div>
    </div>

    <?php if ($flashError): ?><div class="notice err"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
    <?php if ($flashSuccess): ?><div class="notice ok"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>

    <div class="grid">
      <!-- Call card -->
      <section class="card">
        <h3>Call Center</h3>
        <p style="margin:0; color:var(--muted); line-height:1.5;">
          When an employer matches with you, they’ll provide a call room link/code.  
          You can paste it here and jump in instantly.
        </p>

        <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
          <button class="btn primary" type="button" id="openCallMain">Join a Call</button>
          <a class="btn outline" href="/QuickHire/Public/matches.php">View Matches</a>
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
          <?= htmlspecialchars($profile['overview'] ?? 'No overview yet. Update your profile to attract employers.') ?>
        </div>
      </aside>
    </div>
  </main>
</div>

<!-- CALL MODAL -->
<div class="modal" id="callModal" aria-hidden="true">
  <div class="backdrop" id="closeCall"></div>
  <div class="panel" role="dialog" aria-modal="true" aria-labelledby="callTitle">
    <button class="close" id="closeCallBtn" type="button" aria-label="Close">×</button>
    <h3 id="callTitle" style="margin:0 0 8px; font-weight:1000;">Join a Call</h3>
    <p style="margin:0 0 10px; color:var(--muted);">
      Paste your call link or enter a call code provided by the employer.
    </p>

    <div class="field">
      <label for="callLink">Call link</label>
      <input id="callLink" type="text" placeholder="https://meet.google.com/..." value="<?= htmlspecialchars($callLink) ?>">
      <div class="hint">If you only have a code, paste it here too.</div>
    </div>

    <div style="display:flex; gap:10px; margin-top:14px;">
      <button class="btn primary" type="button" id="joinNow">Join Now</button>
      <button class="btn outline" type="button" id="cancelCall">Cancel</button>
    </div>
  </div>
</div>

<script>
  const callModal = document.getElementById('callModal');
  const openBtns = [document.getElementById('openCall'), document.getElementById('openCallTop'), document.getElementById('openCallMain')].filter(Boolean);
  const closeBackdrop = document.getElementById('closeCall');
  const closeBtn = document.getElementById('closeCallBtn');
  const cancelBtn = document.getElementById('cancelCall');
  const joinNow = document.getElementById('joinNow');
  const callLink = document.getElementById('callLink');

  function openCallModal() {
    callModal.classList.add('is-open');
    callModal.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
    setTimeout(() => callLink.focus(), 50);
  }

  function closeCallModal() {
    callModal.classList.remove('is-open');
    callModal.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  openBtns.forEach(b => b.addEventListener('click', (e) => { e.preventDefault(); openCallModal(); }));
  closeBackdrop.addEventListener('click', closeCallModal);
  closeBtn.addEventListener('click', closeCallModal);
  cancelBtn.addEventListener('click', closeCallModal);

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && callModal.classList.contains('is-open')) closeCallModal();
  });

  joinNow.addEventListener('click', () => {
    const link = callLink.value.trim();
    if (!link) {
      alert('Please enter a call link or code.');
      return;
    }
    // For now: open link in new tab. Later: validate + store per match.
    if (link.startsWith('http://') || link.startsWith('https://')) {
      window.open(link, '_blank', 'noopener');
    } else {
      alert('Call code received: ' + link + '\n\nNext step: we can map codes to real call rooms.');
    }
    closeCallModal();
  });
</script>
</body>
</html>