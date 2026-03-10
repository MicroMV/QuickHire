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

// Get user's basic information (name, etc.)
$userStmt = $db->pdo()->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch();

// Get available skills for matching preferences
$skillsStmt = $db->pdo()->query("SELECT id, name, category FROM skills ORDER BY category ASC, name ASC");
$allSkills = $skillsStmt->fetchAll();

// Get employer's current required skills (from their preferences or profile)
try {
  $empSkillsStmt = $db->pdo()->prepare("SELECT skill_id FROM employer_required_skills WHERE employer_user_id = ?");
  $empSkillsStmt->execute([$userId]);
  $currentRequiredSkills = array_column($empSkillsStmt->fetchAll(), 'skill_id');
} catch (Exception $e) {
  // Table might not exist yet, use empty array
  $currentRequiredSkills = [];
}

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
$skillsStmt = $db->pdo()->query("SELECT id, name, category FROM skills ORDER BY category ASC, name ASC");
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

    .history-table{ width:100%; border-collapse:collapse; margin-top:10px; }
    .history-table th, .history-table td{ padding:10px; text-align:left; border-bottom:1px solid var(--line); font-size:13px; }
    .history-table th{ font-weight:900; background:#f9fafb; }
    .status-badge{ display:inline-block; padding:4px 8px; border-radius:6px; font-weight:800; font-size:11px; }
    .status-badge.ringing{ background:#fef3c7; color:#92400e; }
    .status-badge.in-call{ background:#dbeafe; color:#1e40af; }
    .status-badge.completed{ background:#dcfce7; color:#166534; }

    /* Preferences Modal */
    .modal{ display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; }
    .modal.active{ display:flex; }
    .modal-content{
      background:#fff; border-radius:18px; padding:24px; max-width:500px; width:90%; max-height:90vh; overflow-y:auto;
    }
    .modal-header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
    .modal-header h2{ margin:0; font-size:20px; }
    .modal-close{ background:none; border:none; font-size:24px; cursor:pointer; color:var(--muted); }

    .form-group{ margin-bottom:16px; }
    .form-group label{ display:block; font-weight:900; margin-bottom:6px; }
    .form-group input, .form-group select{
      width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px; font-family:inherit;
    }

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
          <?= strtoupper(substr($userInfo['first_name'] ?? 'E', 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="name">
          <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
        </div>
        <div class="meta">
          <?= htmlspecialchars(($profile['country'] ?? 'Country not set')) ?>
        </div>
      </div>
    </div>

    <nav class="nav">
      <button class="success" id="btnFindMatch">🔍 Find Jobseeker</button>

      <button id="btnHome">🏠 Home</button>
      <button id="btnEditProfile">✏️ Edit Profile</button>
      <button id="btnEditPreferences">⚙️ Edit Preferences</button>

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
        <div class="pillRow">
          <span class="pill">Required Skills: <?= count($currentRequiredSkills) ?> selected</span>
        </div>

        <div style="margin-top:14px; color:var(--muted); line-height:1.5;">
          <strong style="color:#111;">Profile Status:</strong><br>
          <?php if (!empty($profile['company_name']) && !empty($profile['country'])): ?>
            ✅ Profile complete and active
          <?php else: ?>
            ⚠️ Please complete your profile to start matching
          <?php endif; ?>
        </div>

        <?php if (!empty($currentRequiredSkills)): ?>
          <div style="margin-top:14px;">
            <strong style="color:#111;">Skills You Look For:</strong><br>
            <div class="pillRow" style="margin-top:6px;">
              <?php 
                foreach ($allSkills as $skill): 
                  if (in_array($skill['id'], $currentRequiredSkills)):
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
        <input type="hidden" name="profile_type" value="EMPLOYER">

        <div class="grid">
          <div style="grid-column:1/-1;">
            <div class="avatar-upload" onclick="document.getElementById('profile_picture_emp').click()">
              <div class="avatar-preview">
                <?php if (!empty($profile['profile_picture_url'])): ?>
                  <img src="<?= htmlspecialchars($profile['profile_picture_url']) ?>" alt="Profile Picture">
                <?php else: ?>
                  <?= strtoupper(substr($userInfo['first_name'] ?? 'E', 0, 1)) ?>
                <?php endif; ?>
              </div>
              <div class="avatar-overlay">✏️</div>
            </div>
            <div style="text-align: center; margin-top: 10px; font-weight: 700; color: #333;">
              <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
            </div>
            <input type="file" id="profile_picture_emp" name="profile_picture" accept="image/*">
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

        <div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
          <button type="submit" class="btn primary">Save Profile</button>
          <button type="button" class="btn outline" id="btnCancelEdit">Cancel</button>
        </div>
      </form>
    </div>

    <!-- Preferences Modal -->
    <div class="modal" id="preferencesModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>⚙️ Matching Preferences</h2>
          <button class="modal-close" id="btnClosePreferences">&times;</button>
        </div>

        <form id="preferencesForm">
          <?php 
            // Create skills by category for preferences modal
            $skillsByCategory = [];
            foreach ($allSkills as $skill) {
              $skillsByCategory[$skill['category']][] = $skill;
            }
          ?>
          <div class="form-group">
            <label for="pref_role_title">Role Title *</label>
            <input type="text" id="pref_role_title" name="role_title" placeholder="e.g., Web Developer, Data Analyst" required>
          </div>

          <div class="form-group">
            <label for="pref_country">Country *</label>
            <input type="text" id="pref_country" name="country" placeholder="e.g., Philippines, USA" required>
          </div>

          <div class="form-group">
            <label for="pref_employment_type">Employment Type</label>
            <select id="pref_employment_type" name="employment_type">
              <option value="PART_TIME">Part-time</option>
              <option value="FULL_TIME">Full-time</option>
              <option value="CONTRACT">Contract</option>
              <option value="FREELANCE">Freelance</option>
            </select>
          </div>

          <div class="form-group">
            <label>Required Skills (Optional)</label>
            <div style="font-size:12px; color:var(--muted); margin-bottom:6px;">Select skills you're looking for</div>
            
            <div class="skills-container">
              <input type="text" class="skills-search" placeholder="🔍 Search skills..." id="prefSkillsSearch">
              
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
              
              <div class="skills-grid" id="prefSkillsContainer">
                <?php 
                  foreach ($skillsByCategory as $category => $skills): 
                ?>
                  <div class="category-section" data-category="<?= htmlspecialchars($category) ?>">
                    <div class="category-title"><?= htmlspecialchars($category) ?></div>
                    <div class="skills-row">
                      <?php foreach ($skills as $skill): ?>
                        <div class="skill-checkbox" data-skill-name="<?= strtolower($skill['name']) ?>">
                          <input type="checkbox" id="pref_skill_<?= $skill['id'] ?>" name="skill_ids[]" value="<?= $skill['id'] ?>">
                          <label for="pref_skill_<?= $skill['id'] ?>" style="margin:0; font-weight:600;"><?= htmlspecialchars($skill['name']) ?></label>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div style="display:flex; gap:10px; margin-top:20px; justify-content:flex-end;">
            <button type="submit" class="btn primary">Save & Find Jobseeker</button>
            <button type="button" class="btn outline" id="btnCancelPreferences">Cancel</button>
          </div>
        </form>
      </div>
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
  const btnHome = document.getElementById('btnHome');
  const btnEditProfile = document.getElementById('btnEditProfile');
  const btnEditProfile2 = document.getElementById('btnEditProfile2');
  const btnEditPreferences = document.getElementById('btnEditPreferences');
  const btnCancelEdit = document.getElementById('btnCancelEdit');
  
  const dashboardContent = document.getElementById('dashboardContent');
  const profileEditContent = document.getElementById('profileEditContent');
  
  // Preferences modal elements
  const preferencesModal = document.getElementById('preferencesModal');
  const preferencesForm = document.getElementById('preferencesForm');
  const btnClosePreferences = document.getElementById('btnClosePreferences');
  const btnCancelPreferences = document.getElementById('btnCancelPreferences');

  // Check if preferences exist in localStorage
  function hasPreferences() {
    const prefs = localStorage.getItem('matchingPreferences');
    return prefs !== null;
  }

  // Load preferences from localStorage
  function loadPreferences() {
    const prefs = localStorage.getItem('matchingPreferences');
    if (prefs) {
      return JSON.parse(prefs);
    }
    return null;
  }

  // Save preferences to localStorage
  function savePreferences(preferences) {
    localStorage.setItem('matchingPreferences', JSON.stringify(preferences));
  }

  // Populate form with saved preferences
  function populatePreferencesForm(prefs) {
    if (prefs) {
      document.getElementById('pref_role_title').value = prefs.role_title || '';
      document.getElementById('pref_country').value = prefs.country || '';
      document.getElementById('pref_employment_type').value = prefs.employment_type || 'FULL_TIME';
      
      // Clear all skill checkboxes first
      document.querySelectorAll('input[name="skill_ids[]"]').forEach(cb => cb.checked = false);
      
      // Check saved skills
      if (prefs.skill_ids && prefs.skill_ids.length > 0) {
        prefs.skill_ids.forEach(skillId => {
          const checkbox = document.getElementById('pref_skill_' + skillId);
          if (checkbox) checkbox.checked = true;
        });
      }
    }
  }

  // Show preferences modal
  function showPreferencesModal() {
    const savedPrefs = loadPreferences();
    populatePreferencesForm(savedPrefs);
    preferencesModal.classList.add('active');
  }

  // Hide preferences modal
  function hidePreferencesModal() {
    preferencesModal.classList.remove('active');
  }

  async function findJobseeker() {
    // Check if we have saved preferences
    if (!hasPreferences()) {
      // First time - show preferences modal
      showPreferencesModal();
      return;
    }

    // Use saved preferences
    const preferences = loadPreferences();
    await executeJobseekerSearch(preferences);
  }

  async function executeJobseekerSearch(preferences) {
    // Disable buttons to prevent multiple clicks
    btnFindMatch.disabled = true;
    btnFindMatch2.disabled = true;
    btnFindMatch.textContent = '🔍 Searching...';
    btnFindMatch2.textContent = 'Searching...';

    try {
      // First check for active calls
      const checkResponse = await fetch('/QuickHire/Public/actions/check_active_call.php');
      const checkData = await checkResponse.json();
      
      if (checkData.ok && checkData.room) {
        // There's already an active call - join it directly
        window.location.href = '/QuickHire/Public/call.php?room=' + encodeURIComponent(checkData.room);
        return;
      }

      // No active call, proceed with matching using preferences
      const formData = new FormData();
      formData.append('role_title', preferences.role_title);
      formData.append('country', preferences.country);
      formData.append('employment_type', preferences.employment_type);
      
      // Add selected skills
      if (preferences.skill_ids && preferences.skill_ids.length > 0) {
        preferences.skill_ids.forEach(skillId => {
          formData.append('skill_ids[]', skillId);
        });
      }

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

  function showDashboard() {
    dashboardContent.style.display = 'grid';
    profileEditContent.style.display = 'none';
    
    // Update active states
    btnHome.classList.add('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    
    // Update title when showing dashboard
    document.querySelector('.title').textContent = 'Welcome back 👋';
    document.querySelector('.subtitle').textContent = 'Find and connect with qualified jobseekers through skill-based matching.';
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
    document.querySelector('.subtitle').textContent = 'Update your company information and skill requirements for better matching.';
  }

  // Initialize with Home active
  btnHome.classList.add('active');

  btnFindMatch.addEventListener('click', findJobseeker);
  btnFindMatch2.addEventListener('click', findJobseeker);
  btnHome.addEventListener('click', showDashboard);
  btnEditProfile.addEventListener('click', showProfileEdit);
  btnEditProfile2.addEventListener('click', showProfileEdit);
  btnEditPreferences.addEventListener('click', showPreferencesModal);
  btnCancelEdit.addEventListener('click', showDashboard);

  // Preferences modal event listeners
  btnClosePreferences.addEventListener('click', hidePreferencesModal);
  btnCancelPreferences.addEventListener('click', hidePreferencesModal);

  // Handle preferences form submission
  preferencesForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Get form data
    const formData = new FormData(preferencesForm);
    const preferences = {
      role_title: formData.get('role_title'),
      country: formData.get('country'),
      employment_type: formData.get('employment_type'),
      skill_ids: formData.getAll('skill_ids[]').map(id => parseInt(id))
    };
    
    // Save preferences
    savePreferences(preferences);
    
    // Hide modal
    hidePreferencesModal();
    
    // Execute search with new preferences
    await executeJobseekerSearch(preferences);
  });

  // Close modal when clicking outside
  preferencesModal.addEventListener('click', (e) => {
    if (e.target === preferencesModal) {
      hidePreferencesModal();
    }
  });

  // Skills organization functionality for preferences modal
  const prefSkillsSearch = document.getElementById('prefSkillsSearch');
  const prefSkillsTabs = document.querySelectorAll('#preferencesModal .skills-tab');
  const prefSkillsContainer = document.getElementById('prefSkillsContainer');
  const prefCategorySections = document.querySelectorAll('#prefSkillsContainer .category-section');

  // Search functionality for preferences modal skills
  if (prefSkillsSearch) {
    prefSkillsSearch.addEventListener('input', function(e) {
      const searchTerm = e.target.value.toLowerCase();
      const skillCheckboxes = prefSkillsContainer.querySelectorAll('.skill-checkbox');
      
      skillCheckboxes.forEach(checkbox => {
        const skillName = checkbox.getAttribute('data-skill-name');
        const shouldShow = skillName.includes(searchTerm);
        checkbox.style.display = shouldShow ? 'flex' : 'none';
      });
      
      // Show/hide category sections based on visible skills
      prefCategorySections.forEach(section => {
        const visibleSkills = section.querySelectorAll('.skill-checkbox[style*="flex"], .skill-checkbox:not([style])');
        section.style.display = visibleSkills.length > 0 ? 'block' : 'none';
      });
    });
  }

  // Tab functionality for preferences modal skills
  prefSkillsTabs.forEach(tab => {
    tab.addEventListener('click', function() {
      // Remove active class from all tabs
      prefSkillsTabs.forEach(t => t.classList.remove('active'));
      // Add active class to clicked tab
      this.classList.add('active');
      
      const selectedCategory = this.getAttribute('data-category');
      
      // Show/hide categories based on selected tab
      prefCategorySections.forEach(section => {
        const sectionCategory = section.getAttribute('data-category');
        if (selectedCategory === 'all' || sectionCategory === selectedCategory) {
          section.style.display = 'block';
        } else {
          section.style.display = 'none';
        }
      });
      
      // Clear search when switching tabs
      if (prefSkillsSearch) {
        prefSkillsSearch.value = '';
        // Reset all skill visibility
        const skillCheckboxes = prefSkillsContainer.querySelectorAll('.skill-checkbox');
        skillCheckboxes.forEach(checkbox => {
          checkbox.style.display = 'flex';
        });
      }
    });
  });

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
  document.getElementById('profile_picture_emp').addEventListener('change', function(e) {
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
</script>

</body>
</html>
