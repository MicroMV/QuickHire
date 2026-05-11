<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\ProfileService;
use Rongie\QuickHire\Services\FileUpload;
use Rongie\QuickHire\Services\MessagingService;

Session::start();
Auth::requireLogin();

if (Auth::role() !== 'JOBSEEKER') {
  header("Location: /QuickHire/Public/index.php");
  exit;
}

// Read flash messages before closing session
$flashError   = \Rongie\QuickHire\Core\Session::flash('error');
$flashSuccess  = \Rongie\QuickHire\Core\Session::flash('success');
$csrfToken = \Rongie\QuickHire\Core\Csrf::token();

// Release session lock before heavy DB work — prevents blocking AJAX requests
if (session_status() === PHP_SESSION_ACTIVE) {
  session_write_close();
}

$config = require __DIR__ . '/../Config/config.php';
$db = new Database($config['db']);
$messagingService = new MessagingService($db->pdo());

// Get unread message count
$userId = Auth::userId();
$userRole = Auth::role();
$conversations = $messagingService->getUserConversations($userId, $userRole);
$unreadCount = array_sum(array_column($conversations, 'unread_count'));

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

// Check if profile is complete
$profileCompleteStmt = $db->pdo()->prepare("SELECT is_profile_complete FROM users WHERE id = ?");
$profileCompleteStmt->execute([$userId]);
$profileCompleteRow = $profileCompleteStmt->fetch();
$isProfileComplete = !empty($profileCompleteRow['is_profile_complete']);

// Build skills by category for overlay form
$overlaySkillsByCategory = [];
foreach ($allSkills as $skill) {
  $overlaySkillsByCategory[$skill['category']][] = $skill;
}

$publicBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($publicBase === '' || $publicBase === '.') {
  $publicBase = '';
}

function public_url(string $path): string
{
  global $publicBase;
  return ($publicBase === '' ? '' : $publicBase) . '/' . ltrim($path, '/');
}

// $flashError and $flashSuccess already read before session_write_close above
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Jobseeker Dashboard - QuickHire</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/css/landingPage.css')) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/css/jobseeker-dashboard.css')) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/css/dark-theme.css')) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/css/dashboard-mobile.css')) ?>">
  <script src="<?= htmlspecialchars(public_url('assets/js/dashboard-mobile.js')) ?>" defer></script>
</head>
<body class="landing-body">

<?php if (!$isProfileComplete): ?>
<!-- -- PROFILE COMPLETION OVERLAY (STEP WIZARD) -- -->
<div class="profile-overlay" id="profileCompletionOverlay">
  <div class="profile-overlay-card">

    <!-- Step progress bar -->
    <div class="cp-steps">
      <div class="cp-step active" data-step="1"><span class="cp-step-num">1</span><span class="cp-step-label">Basic Info</span></div>
      <div class="cp-step-line"></div>
      <div class="cp-step" data-step="2"><span class="cp-step-num">2</span><span class="cp-step-label">Skills</span></div>
      <div class="cp-step-line"></div>
      <div class="cp-step" data-step="3"><span class="cp-step-num">3</span><span class="cp-step-label">About You</span></div>
      <div class="cp-step-line"></div>
      <div class="cp-step" data-step="4"><span class="cp-step-num">4</span><span class="cp-step-label">Resume</span></div>
    </div>

    <?php if ($flashError): ?>
      <div class="cp-alert cp-err"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <form method="POST" action="/QuickHire/Public/actions/save_profile.php" enctype="multipart/form-data" id="jsProfileForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="profile_type" value="JOBSEEKER">

      <!-- -- STEP 1: Basic Info -- -->
      <div class="cp-step-panel active" id="js-step-1">
        <h2 class="cp-step-title">👤 Basic Information</h2>
        <p class="cp-step-desc">Let's start with your profile photo and key details.</p>
        <div class="cp-grid">
        <div class="cp-full">
          <div class="avatar-upload" onclick="openAvatarCamera('ov_js_avatar_data', 'ovJsAvatarPreview')">
            <div class="avatar-preview" id="ovJsAvatarPreview">
              <?php if (!empty($profile['profile_picture_url'])): ?>
                <img src="<?= htmlspecialchars(public_url($profile['profile_picture_url'])) ?>" alt="Profile Picture">
              <?php else: ?>
                <?= strtoupper(substr($userInfo['first_name'] ?? 'U', 0, 1)) ?>
              <?php endif; ?>
            </div>
            <div class="avatar-overlay"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
            <input type="hidden" id="ov_js_avatar_data" name="captured_avatar">
          </div>
          <div class="cp-avatar-name"><?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?></div>
          <div style="text-align:center;font-size:12px;color:#64748b;margin-top:4px;">Camera photo required</div>
        </div>

        <!-- Desired Job Role -->
        <div>
          <label>Desired Job Role *</label>
          <select name="role_title" required>
            <option value="">Select Job Role</option>
            <?php foreach (['Software Engineer','Software Developer','Web Developer','Mobile Developer','Full Stack Developer','Frontend Developer','Backend Developer','DevOps Engineer','Cloud Engineer','Data Scientist','Data Engineer','Data Analyst','Machine Learning Engineer','AI Engineer','Database Administrator','System Administrator','Network Engineer','Security Engineer','QA Engineer','QA Automation Engineer','UI/UX Designer','Product Designer','Technical Product Manager','IT Project Manager','Scrum Master','Business Intelligence Analyst','IT Support Specialist','Technical Writer'] as $rt): ?>
              <option value="<?= $rt ?>" <?= ($profile['role_title'] ?? '') === $rt ? 'selected' : '' ?>><?= $rt ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Rate per Hour (USD) *</label>
          <input name="rate_per_hour" type="number" step="0.01" value="<?= htmlspecialchars($profile['rate_per_hour'] ?? '') ?>" required placeholder="e.g. 25">
        </div>

        <div>
          <label>Available Hours per Day *</label>
          <input name="available_time" type="number" min="1" max="24" value="<?= htmlspecialchars($profile['available_time'] ?? '') ?>" required placeholder="e.g. 8">
        </div>

        <div>
          <label>Country *</label>
          <select name="country" required>
            <option value="">Select Country</option>
            <?php foreach (['Afghanistan','Albania','Algeria','Argentina','Australia','Austria','Bangladesh','Belgium','Brazil','Canada','China','Colombia','Denmark','Egypt','Finland','France','Germany','Greece','India','Indonesia','Ireland','Italy','Japan','Malaysia','Mexico','Netherlands','New Zealand','Norway','Pakistan','Philippines','Poland','Portugal','Russia','Saudi Arabia','Singapore','South Africa','South Korea','Spain','Sweden','Switzerland','Thailand','Turkey','United Arab Emirates','United Kingdom','United States','Vietnam'] as $c): ?>
              <option value="<?= $c ?>" <?= ($profile['country'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Employment Type *</label>
          <select name="employment_type" required>
            <option value="">Select Employment Type</option>
            <?php foreach (['PART_TIME' => 'Part-time','FULL_TIME' => 'Full-time','CONTRACT' => 'Contract','FREELANCE' => 'Freelance'] as $val => $lbl): ?>
              <option value="<?= $val ?>" <?= ($profile['employment_type'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>English Mastery *</label>
          <select name="english_mastery" required>
            <option value="">Select</option>
            <?php foreach (['BEGINNER','INTERMEDIATE','ADVANCED','FLUENT','NATIVE'] as $lv): ?>
              <option value="<?= $lv ?>" <?= ($profile['english_mastery'] ?? '') === $lv ? 'selected' : '' ?>><?= $lv ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        </div><!-- /cp-grid -->

        <div class="cp-nav">
          <span></span>
          <button type="button" class="cp-btn-next" onclick="jsNextStep(1)">Next →</button>
        </div>
      </div><!-- /step-1 -->

      <!-- -- STEP 2: Skills -- -->
      <div class="cp-step-panel" id="js-step-2">
        <h2 class="cp-step-title">🛠️ Your Skills</h2>
        <p class="cp-step-desc">Select the skills that match your expertise. Pick as many as apply.</p>

        <div class="skills-container">
          <input type="text" class="skills-search" placeholder="🔍 Search skills..." id="ovJsSkillsSearch">
          <div class="skills-tabs">
            <div class="skills-tab active" data-category="all">All</div>
            <?php foreach (array_keys($overlaySkillsByCategory) as $cat): ?>
              <div class="skills-tab" data-category="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></div>
            <?php endforeach; ?>
          </div>
          <div class="skills-grid" id="ovJsSkillsContainer">
            <?php foreach ($overlaySkillsByCategory as $cat => $skills): ?>
              <div class="category-section" data-category="<?= htmlspecialchars($cat) ?>">
                <div class="category-title"><?= htmlspecialchars($cat) ?></div>
                <div class="skills-row">
                  <?php foreach ($skills as $skill): ?>
                    <label class="skill-checkbox" data-skill-name="<?= strtolower(htmlspecialchars($skill['name'])) ?>" style="display:flex;align-items:center;gap:6px;cursor:pointer;margin:0;padding:2px 0;font-weight:600;font-size:13px;line-height:1.4;">
                      <input type="checkbox" name="skill_ids[]" value="<?= $skill['id'] ?>" <?= in_array($skill['id'], $currentSkills) ? 'checked' : '' ?> style="width:14px;height:14px;flex-shrink:0;cursor:pointer;accent-color:#6366f1;margin:0;">
                      <?= htmlspecialchars($skill['name']) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="cp-nav">
          <button type="button" class="cp-btn-back" onclick="jsGoStep(1)">← Back</button>
          <button type="button" class="cp-btn-next" onclick="jsNextStep(2)">Next →</button>
        </div>
      </div><!-- /step-2 -->

      <!-- -- STEP 3: About You -- -->
      <div class="cp-step-panel" id="js-step-3">
        <h2 class="cp-step-title">📝 About You</h2>
        <p class="cp-step-desc">Tell employers more about your background.</p>

        <div class="cp-grid">
          <div class="cp-full">
            <label>Profile Description *</label>
            <textarea name="profile_description" required placeholder="Tell employers about yourself, your experience, and what you're looking for..."><?= htmlspecialchars($profile['profile_description'] ?? '') ?></textarea>
          </div>

          <div>
            <label>Bachelor's Degree *</label>
            <select name="bachelors_degree" required>
              <option value="">Select Degree</option>
              <?php foreach (['Computer Science','Information Technology','Software Engineering','Computer Engineering','Information Systems','Data Science','Cybersecurity','Network Engineering','Artificial Intelligence','Web Development','Game Development','Mobile Application Development','Cloud Computing','Digital Media','Graphic Design','Other Technology Degree','No Degree'] as $deg): ?>
                <option value="<?= $deg ?>" <?= ($profile['bachelors_degree'] ?? '') === $deg ? 'selected' : '' ?>><?= $deg ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Portfolio/Website <span style="font-weight:400;color:#64748b;font-size:12px;">(Optional)</span></label>
            <input name="portfolio_url" type="text" value="<?= htmlspecialchars($profile['portfolio_url'] ?? '') ?>" placeholder="https://yourportfolio.com">
          </div>

          <div>
            <label>Age *</label>
            <input name="age" type="number" min="18" max="60" value="<?= htmlspecialchars($profile['age'] ?? '') ?>" placeholder="e.g. 25" required>
          </div>

          <div>
            <label>Gender *</label>
            <select name="gender" required>
              <?php $g = $profile['gender'] ?? ''; ?>
              <option value="">Select Gender</option>
              <option value="MALE" <?= $g === 'MALE' ? 'selected' : '' ?>>Male</option>
              <option value="FEMALE" <?= $g === 'FEMALE' ? 'selected' : '' ?>>Female</option>
              <option value="OTHER" <?= $g === 'OTHER' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
        </div>

        <div class="cp-nav">
          <button type="button" class="cp-btn-back" onclick="jsGoStep(2)">← Back</button>
          <button type="button" class="cp-btn-next" onclick="jsNextStep(3)">Next →</button>
        </div>
      </div><!-- /step-3 -->

      <!-- -- STEP 4: Resume -- -->
      <div class="cp-step-panel" id="js-step-4">
        <h2 class="cp-step-title">📄 Resume</h2>
        <p class="cp-step-desc">Upload your resume so employers can learn more about you.</p>

        <div class="cp-resume-upload">
          <!-- Hidden file input -->
          <input type="file" name="resume" accept="application/pdf" id="ovJsResume" style="display:none;">

          <!-- Drag & drop zone -->
          <div id="ovJsDropZone" style="border:2px dashed rgba(99,102,241,0.4);border-radius:14px;padding:36px 24px;text-align:center;cursor:pointer;transition:all 0.2s;background:rgba(99,102,241,0.04);"
               onclick="document.getElementById('ovJsResume').click()"
               ondragover="event.preventDefault();this.style.borderColor='#6366f1';this.style.background='rgba(99,102,241,0.1)';"
               ondragleave="this.style.borderColor='rgba(99,102,241,0.4)';this.style.background='rgba(99,102,241,0.04)';"
               ondrop="handleResumeDrop(event)">
            <div style="font-size:36px;margin-bottom:10px;">&#128196;</div>
            <div style="font-weight:700;color:#e2e8f0;font-size:15px;margin-bottom:6px;">Drag & drop your resume here</div>
            <div style="color:#64748b;font-size:13px;margin-bottom:14px;">or click to browse</div>
            <div style="display:inline-block;padding:8px 20px;background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.4);border-radius:8px;color:#a5b4fc;font-size:13px;font-weight:600;">Choose PDF File</div>
            <div style="margin-top:10px;font-size:12px;color:#475569;">PDF only  Max 5MB</div>
          </div>

          <?php if (!empty($profile['resume_url'])): ?>
            <div id="ovJsCurrentResume" class="cp-file-box" style="margin-top:12px;">
              <span class="cp-file-icon" aria-hidden="true">&#128196;</span>
              <div>
                <div style="font-weight:600;">Current Resume</div>
                <a href="<?= htmlspecialchars($profile['resume_url']) ?>" target="_blank" class="cp-file-link">View Resume</a>
              </div>
            </div>
          <?php endif; ?>

          <div id="ovJsNewResume" class="cp-file-box cp-file-new" style="display:none;margin-top:12px;">
            <span class="cp-file-icon" aria-hidden="true">&#128196;</span>
            <div>
              <div style="font-weight:600;" id="ovJsResumeFileName">New Resume Selected</div>
              <div class="cp-file-ready">Ready to upload</div>
            </div>
            <button type="button" onclick="clearResume()" aria-label="Remove selected resume" title="Remove selected resume" style="margin-left:auto;background:none;border:none;color:#94a3b8;cursor:pointer;font-size:22px;line-height:1;">&times;</button>
          </div>
        </div>

        <div class="cp-nav">
          <button type="button" class="cp-btn-back" onclick="jsGoStep(3)">← Back</button>
          <button type="submit" class="cp-btn-submit">Complete Profile</button>
        </div>
      </div><!-- /step-4 -->

    </form>
  </div>
</div>

<?php require __DIR__ . '/../Partials/scripts/jobseeker-dashboard-script-1.php'; ?>
<?php endif; ?>

<div class="layout">
  <!-- SIDEBAR -->
  <aside class="side">
    <div class="brandRow">
      <img src="<?= htmlspecialchars(public_url('images/quickhire-logo.png')) ?>" alt="QuickHire Logo">
    </div>

    <div class="profileCard" onclick="showMyProfile()" style="cursor:pointer;" title="View my profile">
      <div class="avatar">
        <?php if (!empty($profile['profile_picture_url'])): ?>
          <img src="<?= htmlspecialchars(public_url($profile['profile_picture_url'])) ?>" alt="Avatar">
        <?php else: ?>
          <?= strtoupper(substr($userInfo['first_name'] ?? 'U', 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="name">
          <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
        </div>
        <div class="meta">
          <?= htmlspecialchars(($profile['role_title'] ?? 'Jobseeker')) ?>
        </div>
      </div>
    </div>

    <nav class="nav">
      <button class="success" id="btnFindEmployer">🔍 Find Employer</button>

      <div class="nav-section-label">DISCOVER</div>
      <button id="btnBrowseJobs">📋 Browse Jobs</button>
      <button id="btnMessages" type="button" style="position:relative;">
        💬 Messages
        <?php if ($unreadCount > 0): ?>
          <span style="margin-left:auto;background:#ef4444;color:white;border-radius:10px;padding:2px 7px;font-size:11px;font-weight:700;"><?= $unreadCount ?></span>
        <?php endif; ?>
      </button>

      <div class="nav-section-label">ACCOUNT</div>
      <button id="btnHome">🏠 Home</button>
      <button id="btnEditProfile">✏️ Edit Profile</button>

      <button id="btnSettings" type="button">⚙️ Settings</button>

      <div class="nav-section-label">SESSION</div>
      <form method="POST" action="/QuickHire/Public/actions/logout.php" style="margin:0;" onsubmit="return confirm('Are you sure you want to logout?');">
        <button class="danger" type="submit">🚪 Logout</button>
      </form>
    </nav>
</aside>

  <div class="js-waiting-overlay" id="jobseekerWaitingOverlay" aria-hidden="true">
    <div class="js-waiting-card" role="dialog" aria-modal="true" aria-labelledby="jsWaitingTitle">
      <div class="js-waiting-orbit" aria-hidden="true">
        <span></span>
        <span></span>
        <div class="js-waiting-core">
          <?php if (!empty($profile['profile_picture_url'])): ?>
            <img src="<?= htmlspecialchars(public_url($profile['profile_picture_url'])) ?>" alt="Your avatar" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
          <?php else: ?>
            <?= strtoupper(substr($userInfo['first_name'] ?? 'U', 0, 1)) ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="js-waiting-kicker">Live matching</div>
      <h2 id="jsWaitingTitle">Waiting for an employer</h2>
      <p class="js-waiting-copy" id="jsWaitingCopy">
        Keep this screen open while QuickHire looks for an employer who matches your profile.
      </p>

      <div class="js-waiting-timer">
        <span>Time remaining</span>
        <strong id="jsWaitingTime">03:00</strong>
      </div>

      <div class="js-waiting-progress" aria-hidden="true">
        <span id="jsWaitingProgress"></span>
      </div>

      <div class="js-waiting-steps">
        <div><span></span>Checking active employers</div>
        <div><span></span>Comparing skills and role fit</div>
        <div><span></span>Preparing your call room</div>
      </div>

      <button type="button" class="js-stop-waiting" id="btnStopWaiting">Stop waiting</button>
    </div>
  </div>

  <!-- MAIN -->
  <main class="main">
    <div class="topbar">
      <div>
        <h1 class="title">Welcome back 👋</h1>
        <p class="subtitle">
          Click "Find Employer" to automatically connect with employers who are currently looking for candidates like you.
        </p>
      </div>
    </div>

    <?php if ($flashError): ?><div class="notice err"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="grid" id="dashboardContent">
      <!-- Status card -->
      <section class="card">
        <h3>📊 Your Status</h3>
        <p style="margin:0; color:var(--muted); line-height:1.5;">
          Your profile is active and visible to employers. Click "Find Employer" to automatically connect with employers who are currently looking for candidates matching your profile.
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
          <span class="pill">Role: <?= htmlspecialchars($profile['role_title'] ?? '-') ?></span>
          <span class="pill">Type: <?= htmlspecialchars($profile['employment_type'] ?? '-') ?></span>
          <span class="pill">Rate: $<?= htmlspecialchars($profile['rate_per_hour'] ?? '0') ?>/hr</span>
        </div>
        <div class="pillRow">
          <span class="pill">Availability: <?= htmlspecialchars($profile['available_time'] ?? '-') ?>h/day</span>
          <span class="pill">English: <?= htmlspecialchars($profile['english_mastery'] ?? '-') ?></span>
          <span class="pill">Skills: <?= count($currentSkills) ?> selected</span>
        </div>

        <div style="margin-top:14px;">
          <strong style="color:#f8fafc; font-size:14px; font-weight:1000; display:block; margin-bottom:4px;">Overview:</strong>
          <span style="color:#94a3b8; line-height:1.5; font-size:13px;"><?= htmlspecialchars($profile['profile_description'] ?? 'No overview yet. Update your profile to attract employers.') ?></span>
        </div>

        <?php if (!empty($currentSkills)): ?>
          <div style="margin-top:14px;">
            <strong style="color:#f8fafc; font-size:14px; font-weight:800;">Skills:</strong><br>
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

    <!-- Job Browsing Content (Hidden by default) -->
    <div class="card" id="jobBrowsingContent" style="display:none; max-width: none; width: 100%;">

      <!-- Search & Filter Bar -->
      <div id="jobFilterBar" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; align-items:center;">
        <div style="flex:1; min-width:200px; position:relative;">
          <input type="text" id="jobSearch" placeholder="🔍 Search by title, company..." style="width:100%; padding:10px 14px; border:1px solid var(--line); border-radius:10px; font-size:14px; box-sizing:border-box;">
        </div>
        <select id="jobFilterRole" style="padding:10px 12px; border:1px solid var(--line); border-radius:10px; font-size:14px; min-width:160px;">
          <option value="">All Roles</option>
          <option value="Software Engineer">Software Engineer</option>
          <option value="Software Developer">Software Developer</option>
          <option value="Web Developer">Web Developer</option>
          <option value="Mobile Developer">Mobile Developer</option>
          <option value="Full Stack Developer">Full Stack Developer</option>
          <option value="Frontend Developer">Frontend Developer</option>
          <option value="Backend Developer">Backend Developer</option>
          <option value="DevOps Engineer">DevOps Engineer</option>
          <option value="Cloud Engineer">Cloud Engineer</option>
          <option value="Data Scientist">Data Scientist</option>
          <option value="Data Engineer">Data Engineer</option>
          <option value="Data Analyst">Data Analyst</option>
          <option value="Machine Learning Engineer">Machine Learning Engineer</option>
          <option value="AI Engineer">AI Engineer</option>
          <option value="Database Administrator">Database Administrator</option>
          <option value="System Administrator">System Administrator</option>
          <option value="Network Engineer">Network Engineer</option>
          <option value="Security Engineer">Security Engineer</option>
          <option value="QA Engineer">QA Engineer</option>
          <option value="QA Automation Engineer">QA Automation Engineer</option>
          <option value="UI/UX Designer">UI/UX Designer</option>
          <option value="Product Designer">Product Designer</option>
          <option value="Technical Product Manager">Technical Product Manager</option>
          <option value="IT Project Manager">IT Project Manager</option>
          <option value="Scrum Master">Scrum Master</option>
          <option value="Business Intelligence Analyst">Business Intelligence Analyst</option>
          <option value="IT Support Specialist">IT Support Specialist</option>
          <option value="Technical Writer">Technical Writer</option>
        </select>
        <select id="jobFilterType" style="padding:10px 12px; border:1px solid var(--line); border-radius:10px; font-size:14px; min-width:140px;">
          <option value="">All Types</option>
          <option value="FULL_TIME">Full-time</option>
          <option value="PART_TIME">Part-time</option>
          <option value="CONTRACT">Contract</option>
          <option value="FREELANCE">Freelance</option>
        </select>
        <select id="jobFilterCountry" style="padding:10px 12px; border:1px solid var(--line); border-radius:10px; font-size:14px; min-width:140px;">
          <option value="">All Countries</option>
          <?php foreach (['Remote','Afghanistan','Albania','Algeria','Argentina','Australia','Austria','Bangladesh','Belgium','Brazil','Canada','China','Colombia','Denmark','Egypt','Finland','France','Germany','Greece','India','Indonesia','Ireland','Italy','Japan','Malaysia','Mexico','Netherlands','New Zealand','Norway','Pakistan','Philippines','Poland','Portugal','Russia','Saudi Arabia','Singapore','South Africa','South Korea','Spain','Sweden','Switzerland','Thailand','Turkey','United Arab Emirates','United Kingdom','United States','Vietnam'] as $c): ?>
            <option value="<?= $c ?>"><?= $c ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn outline" id="btnClearFilters" style="padding:10px 16px; white-space:nowrap;">Clear</button>
      </div>

      <div id="jobListings" class="job-listings">
        <div class="loading">Loading job opportunities...</div>
      </div>

      <div id="loadMoreContainer" style="text-align: center; margin-top: 20px; display: none;">
        <div id="paginationControls" style="display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap;"></div>
      </div>
    </div>

    <!-- Profile Edit Form (Hidden by default) -->
    <div class="card" id="profileEditContent" style="display:none; max-width: none; width: 100%;">

      <form method="POST" action="/QuickHire/Public/actions/save_profile.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="profile_type" value="JOBSEEKER">

        <div class="grid">
          <!-- Avatar + Name -->
          <div style="grid-column:1/-1; display:flex; flex-direction:column; align-items:center; margin-bottom:8px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius:16px; padding: 40px 20px 24px; position:relative; overflow:hidden;">
            <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%, rgba(99,102,241,0.15) 0%, transparent 70%);pointer-events:none;"></div>
            <div class="avatar-upload" onclick="openAvatarCamera('profile_picture_js_data', 'jsEditAvatarPreview')" style="cursor:pointer;">
              <div class="avatar-preview" id="jsEditAvatarPreview" style="width:110px;height:110px;border-radius:50%;overflow:hidden;background:#64748b;display:flex;align-items:center;justify-content:center;color:white;font-size:40px;font-weight:bold;">
                <?php if (!empty($profile['profile_picture_url'])): ?>
                  <img src="<?= htmlspecialchars(public_url($profile['profile_picture_url'])) ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                  <?= strtoupper(substr($userInfo['first_name'] ?? 'J', 0, 1)) ?>
                <?php endif; ?>
              </div>
              <div class="avatar-overlay"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
              <input type="hidden" id="profile_picture_js_data" name="captured_avatar">
            </div>

            <!-- Editable name display -->
            <div style="margin-top:12px;text-align:center;">
              <div id="jsNameDisplay" style="font-weight:700;font-size:16px;cursor:pointer;padding:5px 10px;border-radius:6px;display:inline-flex;align-items:center;gap:6px;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'" onclick="editJsName()">
                <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
                <span style="opacity:0.5;"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>
              </div>
              <div id="jsNameEdit" style="display:none;margin-top:8px;">
                <input type="text" id="jsFirstNameInput" name="first_name" value="<?= htmlspecialchars($userInfo['first_name'] ?? '') ?>" placeholder="First Name" style="width:45%;padding:8px;margin:0 2%;border:1px solid var(--line);border-radius:8px;background:rgba(255,255,255,0.05);color:var(--text-primary,#f8fafc);">
                <input type="text" id="jsLastNameInput" name="last_name" value="<?= htmlspecialchars($userInfo['last_name'] ?? '') ?>" placeholder="Last Name" style="width:45%;padding:8px;margin:0 2%;border:1px solid var(--line);border-radius:8px;background:rgba(255,255,255,0.05);color:var(--text-primary,#f8fafc);">
                <div style="margin-top:8px;">
                  <button type="button" onclick="saveJsName()" style="padding:6px 14px;background:var(--primary);color:white;border:none;border-radius:6px;cursor:pointer;margin-right:6px;font-weight:600;">Save</button>
                  <button type="button" onclick="cancelJsEditName()" style="padding:6px 14px;background:rgba(255,255,255,0.08);color:#e2e8f0;border:1px solid rgba(255,255,255,0.15);border-radius:6px;cursor:pointer;font-weight:600;">Cancel</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Role Title -->
          <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">Role Title *</label>
            <select name="role_title" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <option value="">Select Role</option>
              <?php foreach (['Software Engineer','Software Developer','Web Developer','Mobile Developer','Full Stack Developer','Frontend Developer','Backend Developer','DevOps Engineer','Cloud Engineer','Data Scientist','Data Engineer','Data Analyst','Machine Learning Engineer','AI Engineer','Database Administrator','System Administrator','Network Engineer','Security Engineer','QA Engineer','QA Automation Engineer','UI/UX Designer','Product Designer','Technical Product Manager','IT Project Manager','Scrum Master','Business Intelligence Analyst','IT Support Specialist','Technical Writer'] as $role): ?>
                <option value="<?= $role ?>" <?= ($profile['role_title'] ?? '') === $role ? 'selected' : '' ?>><?= $role ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Employment Type -->
          <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">Employment Type *</label>
            <select name="employment_type" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <option value="">Select Type</option>
              <option value="FULL_TIME" <?= ($profile['employment_type'] ?? '') === 'FULL_TIME' ? 'selected' : '' ?>>Full-time</option>
              <option value="PART_TIME" <?= ($profile['employment_type'] ?? '') === 'PART_TIME' ? 'selected' : '' ?>>Part-time</option>
              <option value="CONTRACT" <?= ($profile['employment_type'] ?? '') === 'CONTRACT' ? 'selected' : '' ?>>Contract</option>
              <option value="FREELANCE" <?= ($profile['employment_type'] ?? '') === 'FREELANCE' ? 'selected' : '' ?>>Freelance</option>
            </select>
          </div>

          <!-- Country -->
          <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">Country *</label>
            <select name="country" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <option value="">Select Country</option>
              <?php foreach (['Afghanistan','Albania','Algeria','Argentina','Australia','Austria','Bangladesh','Belgium','Brazil','Canada','China','Colombia','Denmark','Egypt','Finland','France','Germany','Greece','India','Indonesia','Ireland','Italy','Japan','Malaysia','Mexico','Netherlands','New Zealand','Norway','Pakistan','Philippines','Poland','Portugal','Russia','Saudi Arabia','Singapore','South Africa','South Korea','Spain','Sweden','Switzerland','Thailand','Turkey','United Arab Emirates','United Kingdom','United States','Vietnam','Other'] as $c): ?>
                <option value="<?= $c ?>" <?= ($profile['country'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Rate per Hour -->
          <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">Rate per Hour (USD)</label>
            <input type="number" name="rate_per_hour" step="0.01" min="0" value="<?= htmlspecialchars($profile['rate_per_hour'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;" placeholder="e.g. 25.00">
          </div>

          <!-- Available Hours -->
          <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">Available Hours/Day</label>
            <input type="number" name="available_time" min="1" max="24" value="<?= htmlspecialchars($profile['available_time'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;" placeholder="e.g. 8">
          </div>

          <!-- English Mastery -->
          <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">English Mastery</label>
            <select name="english_mastery" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <option value="">Select Level</option>
              <?php foreach (['BEGINNER','INTERMEDIATE','ADVANCED','FLUENT','NATIVE'] as $level): ?>
                <option value="<?= $level ?>" <?= ($profile['english_mastery'] ?? '') === $level ? 'selected' : '' ?>><?= ucfirst(strtolower($level)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Age -->
          <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">Age</label>
            <input type="number" name="age" min="16" max="100" value="<?= htmlspecialchars($profile['age'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;" placeholder="e.g. 25">
          </div>

          <!-- Gender -->
          <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">Gender</label>
            <select name="gender" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <option value="">Select Gender</option>
              <?php foreach (['MALE' => 'Male','FEMALE' => 'Female','OTHER' => 'Other'] as $val => $lbl): ?>
                <option value="<?= $val ?>" <?= ($profile['gender'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Bachelor's Degree -->
          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:700; margin-bottom:6px;">Bachelor's Degree</label>
            <select name="bachelors_degree" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <option value="">Select Degree</option>
              <?php foreach (['Computer Science','Information Technology','Software Engineering','Computer Engineering','Information Systems','Data Science','Cybersecurity','Network Engineering','Artificial Intelligence','Web Development','Game Development','Mobile Application Development','Cloud Computing','Digital Media','Graphic Design','Other Technology Degree','No Degree'] as $deg): ?>
                <option value="<?= $deg ?>" <?= ($profile['bachelors_degree'] ?? '') === $deg ? 'selected' : '' ?>><?= $deg ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Portfolio URL -->
          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:700; margin-bottom:6px;">Portfolio URL</label>
            <input type="text" name="portfolio_url" value="<?= htmlspecialchars($profile['portfolio_url'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;" placeholder="https://yourportfolio.com">
          </div>

          <!-- Profile Description -->
          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:700; margin-bottom:6px;">Profile Description</label>
            <textarea name="profile_description" rows="4" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px; resize:vertical;" placeholder="Tell employers about yourself..."><?= htmlspecialchars($profile['profile_description'] ?? '') ?></textarea>
          </div>

          <!-- Skills -->
          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:700; margin-bottom:6px;">Skills</label>
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
                        <label class="skill-checkbox" data-skill-name="<?= strtolower($skill['name']) ?>" style="display:flex;align-items:center;gap:6px;cursor:pointer;margin:0;padding:2px 0;font-weight:600;font-size:13px;line-height:1.4;">
                          <input type="checkbox" name="skill_ids[]" value="<?= $skill['id'] ?>" <?= in_array($skill['id'], $currentSkills) ? 'checked' : '' ?> style="width:14px;height:14px;flex-shrink:0;cursor:pointer;accent-color:#6366f1;margin:0;">
                          <?= htmlspecialchars($skill['name']) ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Resume -->
          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:700; margin-bottom:8px;">Resume (PDF)</label>
            <input type="file" name="resume" accept="application/pdf" id="editJsResume" style="display:none;">

            <div id="editJsDropZone"
                 onclick="document.getElementById('editJsResume').click()"
                 ondragover="event.preventDefault();this.style.borderColor='#6366f1';this.style.background='rgba(99,102,241,0.1)';"
                 ondragleave="this.style.borderColor='rgba(99,102,241,0.4)';this.style.background='rgba(99,102,241,0.04)';"
                 ondrop="handleEditResumeDrop(event)"
                 style="border:2px dashed rgba(99,102,241,0.4);border-radius:14px;padding:28px 24px;text-align:center;cursor:pointer;transition:all 0.2s;background:rgba(99,102,241,0.04);">
              <div style="font-size:28px;margin-bottom:8px;">📄</div>
              <div style="font-weight:700;color:#e2e8f0;font-size:14px;margin-bottom:4px;">Drag & drop your resume here</div>
              <div style="color:#64748b;font-size:12px;margin-bottom:12px;">or click to browse</div>
              <div style="display:inline-block;padding:7px 18px;background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.4);border-radius:8px;color:#a5b4fc;font-size:12px;font-weight:600;">Choose PDF File</div>
              <div style="margin-top:8px;font-size:11px;color:#475569;">PDF only  Max 5MB</div>
            </div>

            <?php if (!empty($profile['resume_url'])): ?>
              <div id="editJsCurrentResume" style="margin-top:10px;padding:10px 14px;border:1px solid rgba(255,255,255,0.08);border-radius:10px;background:rgba(255,255,255,0.03);display:flex;align-items:center;gap:10px;">
                <span style="font-size:20px;">📄</span>
                <div style="flex:1;">
                  <div style="font-weight:600;font-size:13px;">Current Resume</div>
                  <a href="<?= htmlspecialchars(public_url($profile['resume_url'])) ?>" target="_blank" style="color:#6366f1;font-size:12px;text-decoration:none;">View Resume</a>
                </div>
              </div>
            <?php endif; ?>

            <div id="editJsNewResume" style="display:none;margin-top:10px;padding:10px 14px;border:1px solid rgba(16,185,129,0.4);border-radius:10px;background:rgba(16,185,129,0.06);display:none;align-items:center;gap:10px;">
              <span style="font-size:20px;">📄</span>
              <div style="flex:1;">
                <div style="font-weight:600;font-size:13px;" id="editJsResumeFileName">New Resume Selected</div>
                <div style="font-size:12px;color:#34d399;">Ready to upload</div>
              </div>
              <button type="button" onclick="clearEditResume()" aria-label="Remove selected resume" title="Remove selected resume" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:22px;line-height:1;">&times;</button>
            </div>
          </div>
        </div>

        <div style="margin-top:24px; display:flex; gap:10px; justify-content:flex-end;">
          <button type="submit" class="btn primary">Save Changes</button>
          <button type="button" class="btn outline" id="btnCancelEdit">Cancel</button>
        </div>
      </form>
    </div>

    <!-- Settings Content (Hidden by default) -->
    <div class="card" id="settingsContent" style="display:none; max-width:none; width:100%;">
      <h2 style="margin-top:0;">Account Settings</h2>
      <p style="color:var(--muted);line-height:1.6;margin-bottom:22px;">
        Manage account-level actions for <?= htmlspecialchars($userInfo['email'] ?? 'your account') ?>.
      </p>

      <section style="border:1px solid rgba(99,102,241,0.28);background:rgba(99,102,241,0.08);border-radius:14px;padding:16px;margin-bottom:14px;">
        <h3 style="margin:0 0 10px;color:#e0e7ff;font-size:17px;">Change Password</h3>
        <form method="POST" action="/QuickHire/Public/actions/change_password.php" style="display:grid;gap:10px;max-width:520px;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="password" name="current_password" placeholder="Current password" required autocomplete="current-password" style="padding:10px 12px;border:1px solid rgba(148,163,184,0.28);border-radius:10px;background:rgba(15,23,42,0.65);color:#f8fafc;">
          <input type="password" name="new_password" placeholder="New password" required minlength="8" autocomplete="new-password" style="padding:10px 12px;border:1px solid rgba(148,163,184,0.28);border-radius:10px;background:rgba(15,23,42,0.65);color:#f8fafc;">
          <input type="password" name="confirm_password" placeholder="Confirm new password" required minlength="8" autocomplete="new-password" style="padding:10px 12px;border:1px solid rgba(148,163,184,0.28);border-radius:10px;background:rgba(15,23,42,0.65);color:#f8fafc;">
          <button class="btn" type="submit" style="justify-self:start;">Update Password</button>
        </form>
      </section>

      <section style="border:1px solid rgba(239,68,68,0.28);background:rgba(239,68,68,0.06);border-radius:12px;padding:12px;max-width:520px;">
        <h3 style="margin:0 0 6px;color:#fecaca;font-size:15px;">Delete Account</h3>
        <p style="margin:0 0 10px;color:#fca5a5;line-height:1.45;font-size:13px;">
          This permanently removes your jobseeker profile, resume, conversations, calls, and login account.
        </p>
        <form method="POST" action="/QuickHire/Public/actions/delete_account.php" onsubmit="return confirmDeleteAccount(this);" style="display:grid;gap:8px;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <label style="display:block;font-weight:800;color:#f8fafc;font-size:13px;">
            Type DELETE to confirm
            <input name="confirm_delete" autocomplete="off" placeholder="DELETE" required style="margin-top:5px;width:100%;padding:8px 10px;border:1px solid rgba(239,68,68,0.45);border-radius:9px;background:rgba(15,23,42,0.65);color:#f8fafc;">
          </label>
          <button class="btn danger" type="submit" style="justify-self:start;color:white;border-color:rgba(185,28,28,0.6);padding:8px 12px;font-size:13px;">Delete My Account</button>
        </form>
      </section>
    </div>

    <!-- -- MY PROFILE VIEW -- -->
    <div class="card" id="myProfileContent" style="display:none; max-width:none; width:100%; padding:0; overflow:hidden;">

      <!-- Cover banner -->
      <div style="height:160px;background:linear-gradient(135deg,#1e293b 0%,#0f172a 50%,#1e1b4b 100%);position:relative;border-radius:18px 18px 0 0;">
        <div style="position:absolute;bottom:-50px;left:32px;">
          <div style="width:100px;height:100px;border-radius:50%;border:4px solid #0f172a;overflow:hidden;background:#1e293b;">
            <?php if (!empty($profile['profile_picture_url'])): ?>
              <img src="<?= htmlspecialchars(public_url($profile['profile_picture_url'])) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:900;color:#a5b4fc;"><?= strtoupper(substr($userInfo['first_name'] ?? 'U', 0, 1)) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div style="position:absolute;top:16px;right:16px;">
          <button onclick="showProfileEdit()" style="padding:8px 18px;background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.4);border-radius:10px;color:#a5b4fc;font-weight:700;font-size:13px;cursor:pointer;">✏️ Edit Profile</button>
        </div>
      </div>

      <!-- Profile info -->
      <div style="padding:64px 32px 32px;">
        <h2 style="margin:0 0 4px;font-size:26px;font-weight:900;color:#f8fafc;"><?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?></h2>
        <p style="margin:0 0 6px;color:#6366f1;font-weight:600;font-size:16px;"><?= htmlspecialchars($profile['role_title'] ?? '') ?></p>
        <p style="margin:0 0 4px;color:#64748b;font-size:14px;"><?= htmlspecialchars($profile['country'] ?? '') ?></p>
        <?php if (!empty($profile['portfolio_url'])): ?>
          <p style="margin:0 0 20px;font-size:13px;">
            <span style="font-weight:600;opacity:0.6;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;margin-right:4px;">Portfolio:</span>
            <a href="<?= htmlspecialchars($profile['portfolio_url']) ?>" target="_blank" style="color:#6366f1;text-decoration:none;"><?= htmlspecialchars($profile['portfolio_url']) ?></a>
          </p>
        <?php else: ?>
          <div style="margin-bottom:20px;"></div>
        <?php endif; ?>

        <!-- Stats pills -->
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:28px;">
          <span style="padding:8px 16px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.25);border-radius:20px;color:#a5b4fc;font-size:13px;font-weight:600;"><span style="font-weight:600;opacity:0.6;font-size:10px;text-transform:uppercase;letter-spacing:0.04em;margin-right:3px;">Rate:</span> $<?= htmlspecialchars($profile['rate_per_hour'] ?? '0') ?>/hr</span>
          <span style="padding:8px 16px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);border-radius:20px;color:#34d399;font-size:13px;font-weight:600;"><span style="font-weight:600;opacity:0.6;font-size:10px;text-transform:uppercase;letter-spacing:0.04em;margin-right:3px;">Hours:</span><?= htmlspecialchars($profile['available_time'] ?? '') ?>h/day</span>
          <span style="padding:8px 16px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);border-radius:20px;color:#fbbf24;font-size:13px;font-weight:600;"><span style="font-weight:600;opacity:0.6;font-size:10px;text-transform:uppercase;letter-spacing:0.04em;margin-right:3px;">English:</span><?= htmlspecialchars($profile['english_mastery'] ?? '') ?></span>
          <span style="padding:8px 16px;background:rgba(139,92,246,0.1);border:1px solid rgba(139,92,246,0.25);border-radius:20px;color:#c084fc;font-size:13px;font-weight:600;"><span style="font-weight:600;opacity:0.6;font-size:10px;text-transform:uppercase;letter-spacing:0.04em;margin-right:3px;">Type:</span><?= htmlspecialchars(str_replace('_', '-', $profile['employment_type'] ?? '')) ?></span>
          <?php if (!empty($profile['age'])): ?>
            <span style="padding:8px 16px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:20px;color:#94a3b8;font-size:13px;font-weight:600;"><span style="font-weight:600;opacity:0.6;font-size:10px;text-transform:uppercase;letter-spacing:0.04em;margin-right:3px;">Age:</span><?= htmlspecialchars($profile['age']) ?> yrs</span>
          <?php endif; ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

          <!-- About -->
          <div style="grid-column:1/-1;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:20px;">
            <h3 style="margin:0 0 12px;font-size:15px;font-weight:800;color:#f8fafc;">About</h3>
            <p style="margin:0;color:#94a3b8;line-height:1.7;font-size:14px;"><?= nl2br(htmlspecialchars($profile['profile_description'] ?? 'No description yet.')) ?></p>
          </div>

          <!-- Skills -->
          <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:20px;">
            <h3 style="margin:0 0 14px;font-size:15px;font-weight:800;color:#f8fafc;">Skills</h3>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
              <?php foreach ($allSkills as $skill): ?>
                <?php if (in_array($skill['id'], $currentSkills)): ?>
                  <span style="padding:5px 12px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.3);border-radius:20px;color:#a5b4fc;font-size:12px;font-weight:600;"><?= htmlspecialchars($skill['name']) ?></span>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php if (empty($currentSkills)): ?>
                <span style="color:#64748b;font-size:13px;">No skills added yet.</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Education & Details -->
          <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:20px;">
            <h3 style="margin:0 0 14px;font-size:15px;font-weight:800;color:#f8fafc;">Details</h3>
            <div style="display:flex;flex-direction:column;gap:10px;">
              <?php if (!empty($profile['bachelors_degree'])): ?>
                <div style="display:flex;gap:10px;align-items:center;">
                  <span style="font-size:18px;">🎓</span>
                  <div>
                    <div style="font-size:12px;color:#64748b;">Education</div>
                    <div style="font-size:14px;font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($profile['bachelors_degree']) ?></div>
                  </div>
                </div>
              <?php endif; ?>
              <?php if (!empty($profile['gender'])): ?>
                <div style="display:flex;gap:10px;align-items:center;">
                  <span style="font-size:18px;">👤</span>
                  <div>
                    <div style="font-size:12px;color:#64748b;">Gender</div>
                    <div style="font-size:14px;font-weight:600;color:#e2e8f0;"><?= htmlspecialchars(ucfirst(strtolower($profile['gender']))) ?></div>
                  </div>
                </div>
              <?php endif; ?>
              <?php if (!empty($profile['resume_url'])): ?>
                <div style="display:flex;gap:10px;align-items:center;">
                  <span style="font-size:18px;">📄</span>
                  <div>
                    <div style="font-size:12px;color:#64748b;">Resume</div>
                    <a href="<?= htmlspecialchars(public_url($profile['resume_url'])) ?>" target="_blank" style="font-size:14px;font-weight:600;color:#6366f1;text-decoration:none;">View Resume</a>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>
    </div>

  </main>

  <!-- MESSAGING PANEL -->
  <div class="messaging-panel" id="messagingPanel">
    <div class="messaging-header">
      <h3>💬 Messages</h3>
    </div>
    
    <div class="messaging-content">
      <div class="conversations-sidebar">
        <!-- Search -->
        <div style="padding:12px 12px 0; background:#0f172a; border-bottom:1px solid rgba(255,255,255,0.08);">
          <div style="position:relative; margin-bottom:12px;">
            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#64748b;font-size:14px;pointer-events:none;">🔍</span>
            <input type="text" id="jsConvSearchInput" placeholder="Search conversations..."
              style="width:100%;padding:8px 10px 8px 32px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;font-size:13px;background:rgba(255,255,255,0.05);color:#f1f5f9;box-sizing:border-box;outline:none;font-family:inherit;"
              oninput="jsFilterConversations()">
          </div>
        </div>
        <div class="conversations-list" id="conversationsList">
          <div class="loading">Loading conversations...</div>
        </div>
      </div>
      
      <div class="chat-area" id="chatArea">
        <div class="chat-header" id="chatHeader">
          <button class="back-btn" id="backToConversations">← Back</button>
          <div id="chatHeaderAvatar" style="display:none;width:38px;height:38px;border-radius:50%;background:#64748b;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:15px;flex-shrink:0;overflow:visible;position:relative;"></div>
          <div style="display:flex;flex-direction:column;gap:1px;">
            <div class="chat-title" id="chatTitle">Select a conversation</div>
            <div id="chatStatus" style="min-height:16px;"></div>
          </div>
          <div style="margin-left:auto;position:relative;">
            <button id="chatMenuBtn" onclick="toggleChatMenu()" style="display:none;background:none;border:none;cursor:pointer;font-size:20px;color:#64748b;padding:4px 8px;border-radius:6px;line-height:1;" title="Options">⋮</button>
          </div>
        </div>
        
        <div id="jobBanner" style="display:none;padding:10px 16px;background:rgba(99,102,241,0.1);border-bottom:1px solid rgba(99,102,241,0.25);"></div>

        <div class="messages-container" id="messagesContainer">
          <div class="empty-state">
            <h3>Select a conversation</h3>
            <p>Choose a conversation from the sidebar to start messaging</p>
          </div>
        </div>
        
        <div class="message-input-area" id="messageInputArea" style="display: none;">
          <div class="file-preview" id="filePreview" style="display: none;">
            <div class="file-preview-content">
              <div class="file-preview-icon">📎</div>
              <div class="file-preview-info">
                <div class="file-preview-name" id="filePreviewName"></div>
                <div class="file-preview-size" id="filePreviewSize"></div>
              </div>
              <button type="button" class="file-preview-remove" id="filePreviewRemove">✕</button>
            </div>
          </div>
          <form class="message-form" id="messageForm" enctype="multipart/form-data">
            <input type="hidden" name="conversation_id" id="conversationId">
            <textarea class="message-input" name="message" placeholder="Type your message..." rows="1" id="messageInput"></textarea>
            <input type="file" id="fileInput" name="file" style="display: none;" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip">
            <button type="button" class="file-button" onclick="document.getElementById('fileInput').click()" title="Attach file">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            </button>
            <button type="submit" class="send-button" id="sendButton">Send</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Floating chat menu — appended to body to escape overflow:hidden containers -->
<div id="chatMenu" style="display:none;position:fixed;background:#1e293b;border:1px solid rgba(255,255,255,0.12);border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.5);min-width:190px;z-index:99999;overflow:hidden;">
  <button onclick="deleteConversation(currentConversationId)" style="display:flex;align-items:center;gap:10px;width:100%;padding:13px 16px;background:none;border:none;cursor:pointer;color:#fca5a5;font-size:14px;font-weight:600;" onmouseover="this.style.background='rgba(239,68,68,0.12)'" onmouseout="this.style.background='none'">🗑 Delete Conversation</button>
</div>

<div id="avatarCameraModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.86);z-index:100000;align-items:center;justify-content:center;padding:18px;">
  <div style="width:min(520px,100%);background:#0f172a;border:1px solid rgba(255,255,255,0.12);border-radius:18px;padding:18px;box-shadow:0 24px 70px rgba(0,0,0,0.55);">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
      <h3 style="margin:0;color:#f8fafc;font-size:18px;">Take Profile Photo</h3>
      <button type="button" onclick="closeAvatarCamera()" style="background:none;border:0;color:#94a3b8;font-size:24px;line-height:1;cursor:pointer;">&times;</button>
    </div>
    <div id="avatarCameraError" style="display:none;margin-bottom:12px;padding:10px 12px;border-radius:10px;background:rgba(239,68,68,0.14);color:#fca5a5;font-size:13px;font-weight:700;"></div>
    <div style="position:relative;width:100%;aspect-ratio:1/1;background:#020617;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);">
      <video id="avatarCameraVideo" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;"></video>
      <img id="avatarCameraSnapshot" alt="Captured avatar preview" style="display:none;width:100%;height:100%;object-fit:cover;">
      <canvas id="avatarCameraCanvas" width="640" height="640" style="display:none;"></canvas>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;flex-wrap:wrap;">
      <button type="button" id="avatarRetakeBtn" onclick="retakeAvatarPhoto()" style="display:none;padding:10px 16px;border-radius:10px;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.06);color:#e2e8f0;font-weight:800;cursor:pointer;">Retake</button>
      <button type="button" id="avatarCaptureBtn" onclick="captureAvatarPhoto()" style="padding:10px 16px;border-radius:10px;border:0;background:#6366f1;color:white;font-weight:900;cursor:pointer;">Capture</button>
      <button type="button" id="avatarUseBtn" onclick="useAvatarPhoto()" style="display:none;padding:10px 16px;border-radius:10px;border:0;background:#10b981;color:white;font-weight:900;cursor:pointer;">Use Photo</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../Partials/scripts/jobseeker-dashboard-script-2.php'; ?>

</body>

<?php require __DIR__ . '/../Partials/scripts/jobseeker-dashboard-script-3.php'; ?>

<?php require __DIR__ . '/../Partials/scripts/jobseeker-dashboard-script-4.php'; ?>

<!-- Image Lightbox Modal -->
<div id="imageLightbox" onclick="closeImageModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:999999;align-items:center;justify-content:center;flex-direction:column;gap:12px;cursor:zoom-out;">
  <img id="lightboxImg" src="" alt="" style="max-width:90vw;max-height:85vh;border-radius:12px;object-fit:contain;box-shadow:0 24px 60px rgba(0,0,0,0.5);">
  <div style="display:flex;align-items:center;gap:16px;">
    <span id="lightboxName" style="color:#94a3b8;font-size:13px;"></span>
    <a id="lightboxDownload" href="" download target="_blank" onclick="event.stopPropagation()"
       style="padding:6px 14px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:8px;color:#e2e8f0;font-size:13px;font-weight:600;text-decoration:none;">
      ↓ Download
    </a>
  </div>
  <button onclick="closeImageModal()" style="position:absolute;top:16px;right:20px;background:none;border:none;color:#94a3b8;font-size:28px;cursor:pointer;line-height:1;">✕</button>
</div>
<?php require __DIR__ . '/../Partials/scripts/jobseeker-dashboard-script-5.php'; ?>

</html>





