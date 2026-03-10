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

// Get user's basic information (name, etc.)
$userStmt = $db->pdo()->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch();

// Get available skills for profile editing
$skillsStmt = $db->pdo()->query("SELECT id, name, category FROM skills ORDER BY category ASC, name ASC");
$allSkills = $skillsStmt->fetchAll();

// Get jobseeker's current skills
$jsSkillsStmt = $db->pdo()->prepare("SELECT skill_id FROM jobseeker_skills WHERE jobseeker_user_id = ?");
$jsSkillsStmt->execute([$userId]);
$currentSkills = array_column($jsSkillsStmt->fetchAll(), 'skill_id');

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
      transition: all 0.2s ease;
    }
    .nav a:hover, .nav button:hover{ border-color:#cfe5ea; }
    .nav a.active, .nav button.active{ 
      background:var(--primary); 
      border-color:var(--primary); 
      color:#fff; 
      box-shadow: 0 4px 12px rgba(31, 111, 130, 0.3);
    }

    .nav .danger{ border-color:#ffd1d1; color:#7a0b0b; }
    .nav .primary{ background:var(--primary); border-color:var(--primary); color:#fff; }
    .nav .success{ background:#10b981; border-color:#10b981; color:#fff; }

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
      align-items: start;
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

    .skills-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:10px; margin-top:10px; max-height:300px; overflow-y:auto; border:1px solid var(--line); border-radius:12px; padding:16px; }
    .skill-checkbox{ display:flex; align-items:center; gap:8px; }
    .skill-checkbox input{ width:18px; height:18px; cursor:pointer; }
    .category-header{ font-weight:900; color:var(--primary); margin:12px 0 8px; border-bottom:1px solid var(--line); padding-bottom:4px; grid-column:1/-1; }
    
    /* Skills organization */
    .skills-container { margin-top: 10px; }
    .skills-search { width: 100%; padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; margin-bottom: 10px; font-size: 14px; }
    .skills-tabs { display: flex; gap: 5px; margin-bottom: 10px; flex-wrap: wrap; }
    .skills-tab { padding: 6px 12px; border: 1px solid var(--line); border-radius: 6px; background: #fff; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s; }
    .skills-tab.active { background: var(--primary); color: white; border-color: var(--primary); }
    .skills-tab:hover { border-color: var(--primary); }
    .category-section { margin-bottom: 15px; }
    .category-title { font-weight: 800; color: var(--primary); margin-bottom: 8px; font-size: 14px; border-bottom: 1px solid var(--line); padding-bottom: 3px; }
    .skills-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
    .avatar-upload {
      position: relative;
      width: 120px;
      height: 120px;
      margin: 0 auto 20px;
      cursor: pointer;
    }
    
    .avatar-preview {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: #eaf3f5;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 900;
      color: var(--primary);
      font-size: 48px;
      overflow: hidden;
      border: 3px solid var(--line);
      transition: all 0.3s ease;
    }
    
    .avatar-preview:hover {
      border-color: var(--primary);
      transform: scale(1.05);
    }
    
    .avatar-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .avatar-overlay {
      position: absolute;
      bottom: 0;
      right: 0;
      background: var(--primary);
      color: white;
      border-radius: 50%;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      border: 3px solid white;
      cursor: pointer;
    }
    
    .avatar-upload input[type="file"] {
      display: none !important;
      visibility: hidden !important;
      position: absolute !important;
      left: -9999px !important;
      width: 0 !important;
      height: 0 !important;
      opacity: 0 !important;
      z-index: -1 !important;
    }
    
    /* Hide any file input related elements */
    .avatar-upload input[type="file"]::-webkit-file-upload-button {
      display: none !important;
    }
    
    .avatar-upload input[type="file"]::file-selector-button {
      display: none !important;
    }
    
    /* Hide any browser-generated file input text within avatar upload */
    .avatar-upload input[type="file"]::before,
    .avatar-upload input[type="file"]::after {
      display: none !important;
      content: none !important;
    }
    
    /* Ensure avatar upload container doesn't show any text */
    .avatar-upload::after {
      content: none !important;
    }

    @media (max-width: 980px){
      .grid{ grid-template-columns:1fr; }
      .side{ position:relative; height:auto; width:100%; border-right:0; border-bottom:1px solid var(--line); }
      .layout{ flex-direction:column; }
    }

    /* Toast notification styles */
    .toast {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%) translateY(-100px);
      background: #4ade80;
      color: white;
      padding: 16px 20px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      font-weight: 700;
      z-index: 1000;
      opacity: 0;
      transition: all 0.3s ease-in-out;
      max-width: 400px;
      text-align: center;
    }
    
    .toast.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }
    
    .toast.error {
      background: #ef4444;
    }
  </style>
</head>
<body>

<div class="layout">
  <!-- SIDEBAR -->
  <aside class="side">
    <div class="brandRow" style="flex-direction: column; align-items: center; text-align: center;">
      <img src="/QuickHire/Public/images/quickhire-logo.jpg" alt="QuickHire Logo" style="width: auto; height: 32px; border-radius: 4px;">
    </div>

    <div class="profileCard">
      <div class="avatar">
        <?php if (!empty($profile['profile_picture_url'])): ?>
          <img src="/QuickHire/Public/<?= htmlspecialchars($profile['profile_picture_url']) ?>" alt="Avatar">
        <?php else: ?>
          <?= strtoupper(substr($userInfo['first_name'] ?? 'U', 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="name">
          <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
        </div>
        <div class="meta">
          <?= htmlspecialchars(($profile['country'] ?? 'Country not set')) ?>
          • $<?= htmlspecialchars((string)($profile['rate_per_hour'] ?? '0')) ?>/hr
        </div>
      </div>
    </div>

    <nav class="nav">
      <button class="success" id="btnFindEmployer">🔍 Find Employer</button>

      <button id="btnHome">🏠 Home</button>
      <button id="btnEditProfile">✏️ Edit Profile</button>

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
          <span class="pill">Type: <?= htmlspecialchars($profile['employment_type'] ?? '—') ?></span>
          <span class="pill">Rate: $<?= htmlspecialchars($profile['rate_per_hour'] ?? '0') ?>/hr</span>
        </div>
        <div class="pillRow">
          <span class="pill">Availability: <?= htmlspecialchars($profile['available_time'] ?? '—') ?>h/day</span>
          <span class="pill">English: <?= htmlspecialchars($profile['english_mastery'] ?? '—') ?></span>
          <span class="pill">Skills: <?= count($currentSkills) ?> selected</span>
        </div>

        <div style="margin-top:14px; color:var(--muted); line-height:1.5;">
          <strong style="color:#111;">Overview:</strong><br>
          <?= htmlspecialchars($profile['profile_description'] ?? 'No overview yet. Update your profile to attract employers.') ?>
        </div>

        <?php if (!empty($currentSkills)): ?>
          <div style="margin-top:14px;">
            <strong style="color:#111;">Skills:</strong><br>
            <div class="pillRow" style="margin-top:6px;">
              <?php 
                foreach ($allSkills as $skill): 
                  if (in_array($skill['id'], $currentSkills)):
              ?>
                <span class="pill" style="background:#e6f3f7; color:var(--primary);"><?= htmlspecialchars($skill['name']) ?></span>
              <?php 
                  endif;
                endforeach; 
              ?>
            </div>
          </div>
        <?php endif; ?>
      </aside>
    </div>

    <!-- Profile Edit Form (Hidden by default) -->
    <div class="card" id="profileEditContent" style="display:none;">
      <form method="POST" action="/QuickHire/Public/actions/save_profile.php" enctype="multipart/form-data" id="profileForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Rongie\QuickHire\Core\Csrf::token()) ?>">
        <input type="hidden" name="profile_type" value="JOBSEEKER">

        <div class="grid">
          <div style="grid-column:1/-1;">
            <div class="avatar-upload" onclick="document.getElementById('profile_picture_js').click()">
              <div class="avatar-preview">
                <?php if (!empty($profile['profile_picture_url'])): ?>
                  <img src="<?= htmlspecialchars($profile['profile_picture_url']) ?>" alt="Profile Picture">
                <?php else: ?>
                  <?= strtoupper(substr($userInfo['first_name'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
              </div>
              <div class="avatar-overlay">✏️</div>
            </div>
            <div style="text-align: center; margin-top: 10px; font-weight: 700; color: #333;">
              <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
            </div>
            <input type="file" id="profile_picture_js" name="profile_picture" accept="image/*">
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
            <label style="display:block; font-weight:900; margin-bottom:6px;">Employment Type *</label>
            <select name="employment_type" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <?php
                $empTypes = ['PART_TIME' => 'Part-time', 'FULL_TIME' => 'Full-time', 'CONTRACT' => 'Contract', 'FREELANCE' => 'Freelance'];
                $currentEmpType = $profile['employment_type'] ?? '';
                echo '<option value="">Select Employment Type</option>';
                foreach ($empTypes as $value => $label) {
                  $sel = ($currentEmpType === $value) ? 'selected' : '';
                  echo "<option value=\"$value\" $sel>$label</option>";
                }
              ?>
            </select>
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

          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:900; margin-bottom:6px;">Your Skills *</label>
            <div style="font-size:12px; color:var(--muted); margin-bottom:6px;">Select skills that match your expertise</div>
            
            <div class="skills-container">
              <input type="text" class="skills-search" placeholder="🔍 Search skills..." id="skillsSearch">
              
              <div class="skills-tabs">
                <div class="skills-tab active" data-category="all">All</div>
                <div class="skills-tab" data-category="Programming">Programming</div>
                <div class="skills-tab" data-category="Frontend">Frontend</div>
                <div class="skills-tab" data-category="Backend">Backend</div>
                <div class="skills-tab" data-category="Database">Database</div>
                <div class="skills-tab" data-category="Cloud">Cloud</div>
                <div class="skills-tab" data-category="Design">Design</div>
                <div class="skills-tab" data-category="Management">Management</div>
              </div>
              
              <div class="skills-grid" id="skillsContainer">
                <?php 
                  $skillsByCategory = [];
                  foreach ($allSkills as $skill) {
                    $skillsByCategory[$skill['category']][] = $skill;
                  }
                  
                  foreach ($skillsByCategory as $category => $skills): 
                ?>
                  <div class="category-section" data-category="<?= htmlspecialchars($category) ?>">
                    <div class="category-title"><?= htmlspecialchars($category) ?></div>
                    <div class="skills-row">
                      <?php foreach ($skills as $skill): ?>
                        <div class="skill-checkbox" data-skill-name="<?= strtolower($skill['name']) ?>">
                          <input type="checkbox" id="js_skill_<?= $skill['id'] ?>" name="skill_ids[]" value="<?= $skill['id'] ?>" 
                                 <?= in_array($skill['id'], $currentSkills) ? 'checked' : '' ?>>
                          <label for="js_skill_<?= $skill['id'] ?>" style="margin:0; font-weight:600;"><?= htmlspecialchars($skill['name']) ?></label>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
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
            <input type="file" name="resume" accept="application/pdf" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;" id="resumeInput">
            <div style="font-size:12px; color:var(--muted); margin-top:6px;">If you upload a new one, it replaces the old resume.</div>
            
            <!-- Current Resume Display -->
            <div id="currentResumeDisplay" style="margin-top:10px;">
              <?php if (!empty($profile['resume_url'])): ?>
                <div style="padding:10px; border:1px solid var(--line); border-radius:8px; background:#f8f9fa; display:flex; align-items:center; gap:10px;">
                  <span style="font-size:20px;">📄</span>
                  <div style="flex:1;">
                    <div style="font-weight:600; color:#333;">Current Resume</div>
                    <div style="font-size:12px; color:var(--muted);">
                      <a href="<?= htmlspecialchars($profile['resume_url']) ?>" target="_blank" style="color:var(--primary); text-decoration:none;">
                        View Resume
                      </a>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            
            <!-- New Resume Preview -->
            <div id="newResumePreview" style="margin-top:10px; display:none;">
              <div style="padding:10px; border:1px solid #10b981; border-radius:8px; background:#f0fdf4; display:flex; align-items:center; gap:10px;">
                <span style="font-size:20px;">📄</span>
                <div style="flex:1;">
                  <div style="font-weight:600; color:#333;" id="newResumeFileName">New Resume Selected</div>
                  <div style="font-size:12px; color:#059669;">Ready to upload</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
          <button type="submit" class="btn primary">Save Profile</button>
          <button type="button" class="btn outline" id="btnCancelEdit">Cancel</button>
        </div>
      </form>
    </div>
  </main>
</div>

</body>

<script>
  const btnFindEmployer = document.getElementById('btnFindEmployer');
  const btnFindEmployer2 = document.getElementById('btnFindEmployer2');
  const btnHome = document.getElementById('btnHome');
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

  function showDashboard() {
    dashboardContent.style.display = 'grid';
    profileEditContent.style.display = 'none';
    
    // Update active states
    btnHome.classList.add('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    
    // Update title when showing dashboard
    document.querySelector('.title').textContent = 'Welcome back 👋';
    document.querySelector('.subtitle').textContent = 'Your profile is live. Employers will automatically match with you based on your skills and availability.';
  }

  function showProfileEdit() {
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'block';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.add('active');
    btnEditProfile2.classList.add('active');
    
    // Update title when showing edit form
    document.querySelector('.title').textContent = 'Edit Your Profile ✏️';
    document.querySelector('.subtitle').textContent = 'Update your information to improve matching with employers.';
  }

  // Initialize with Home active
  btnHome.classList.add('active');

  btnFindEmployer.addEventListener('click', findEmployer);
  btnFindEmployer2.addEventListener('click', findEmployer);
  btnHome.addEventListener('click', showDashboard);
  btnEditProfile.addEventListener('click', showProfileEdit);
  btnEditProfile2.addEventListener('click', showProfileEdit);
  btnCancelEdit.addEventListener('click', showDashboard);

  // Show toast notification if there's a success message
  <?php if ($flashSuccess): ?>
  document.addEventListener('DOMContentLoaded', function() {
    showToast('<?= addslashes($flashSuccess) ?>', 'success');
  });
  <?php endif; ?>

  // Toast notification function
  function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type === 'error' ? 'error' : ''}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    // Show toast with slide down animation from center-top
    setTimeout(() => {
      toast.classList.add('show');
    }, 100);
    
    // Hide and remove toast after 4 seconds
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    }, 4000);
  }

  // Avatar preview functionality
  document.getElementById('profile_picture_js').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        const avatarPreview = document.querySelector('.avatar-preview');
        avatarPreview.innerHTML = `<img src="${e.target.result}" alt="Profile Picture">`;
      };
      reader.readAsDataURL(file);
    }
  });

  // Resume file preview functionality
  const resumeInput = document.getElementById('resumeInput');
  const newResumePreview = document.getElementById('newResumePreview');
  const newResumeFileName = document.getElementById('newResumeFileName');
  const currentResumeDisplay = document.getElementById('currentResumeDisplay');

  if (resumeInput) {
    resumeInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        // Show new resume preview
        newResumeFileName.textContent = file.name;
        newResumePreview.style.display = 'block';
        
        // Hide current resume display if it exists
        if (currentResumeDisplay) {
          currentResumeDisplay.style.display = 'none';
        }
      } else {
        // Hide new resume preview if no file selected
        newResumePreview.style.display = 'none';
        
        // Show current resume display again if it exists
        if (currentResumeDisplay) {
          currentResumeDisplay.style.display = 'block';
        }
      }
    });
  }

  // Skills organization functionality
  const skillsSearch = document.getElementById('skillsSearch');
  const skillsTabs = document.querySelectorAll('.skills-tab');
  const skillsContainer = document.getElementById('skillsContainer');
  const categorySections = document.querySelectorAll('.category-section');

  // Search functionality
  skillsSearch.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const skillCheckboxes = document.querySelectorAll('.skill-checkbox');
    
    skillCheckboxes.forEach(checkbox => {
      const skillName = checkbox.getAttribute('data-skill-name');
      const shouldShow = skillName.includes(searchTerm);
      checkbox.style.display = shouldShow ? 'flex' : 'none';
    });
    
    // Show/hide category sections based on visible skills
    categorySections.forEach(section => {
      const visibleSkills = section.querySelectorAll('.skill-checkbox[style*="flex"], .skill-checkbox:not([style])');
      section.style.display = visibleSkills.length > 0 ? 'block' : 'none';
    });
  });

  // Tab functionality
  skillsTabs.forEach(tab => {
    tab.addEventListener('click', function() {
      // Remove active class from all tabs
      skillsTabs.forEach(t => t.classList.remove('active'));
      // Add active class to clicked tab
      this.classList.add('active');
      
      const selectedCategory = this.getAttribute('data-category');
      
      // Show/hide categories based on selected tab
      categorySections.forEach(section => {
        const sectionCategory = section.getAttribute('data-category');
        if (selectedCategory === 'all' || sectionCategory === selectedCategory) {
          section.style.display = 'block';
        } else {
          section.style.display = 'none';
        }
      });
      
      // Clear search when switching tabs
      skillsSearch.value = '';
      // Reset all skill visibility
      const skillCheckboxes = document.querySelectorAll('.skill-checkbox');
      skillCheckboxes.forEach(checkbox => {
        checkbox.style.display = 'flex';
      });
    });
  });
</script>

</html>
