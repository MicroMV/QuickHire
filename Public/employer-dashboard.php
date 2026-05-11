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

if (Auth::role() !== 'EMPLOYER') {
  header("Location: /QuickHire/Public/index.php");
  exit;
}

// Read flash messages before closing session
$flashError  = \Rongie\QuickHire\Core\Session::flash('error');
$flashSuccess = \Rongie\QuickHire\Core\Session::flash('success');
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

// Get available skills for matching
$skillsStmt = $db->pdo()->query("SELECT id, name, category FROM skills ORDER BY category ASC, name ASC");
$allSkills = $skillsStmt->fetchAll();

// Check if profile is complete
$profileCompleteStmt = $db->pdo()->prepare("SELECT is_profile_complete FROM users WHERE id = ?");
$profileCompleteStmt->execute([$userId]);
$profileCompleteRow = $profileCompleteStmt->fetch();
$isProfileComplete = !empty($profileCompleteRow['is_profile_complete']);

// Build skills by category for overlay form
$overlayEmpSkillsByCategory = [];
foreach ($allSkills as $skill) {
  $overlayEmpSkillsByCategory[$skill['category']][] = $skill;
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
  <title>Employer Dashboard - QuickHire</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/css/landingPage.css')) ?>?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/css/employer-dashboard.css')) ?>?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/css/dark-theme.css')) ?>?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/css/dashboard-mobile.css')) ?>?v=<?= time() ?>">
  <script src="<?= htmlspecialchars(public_url('assets/js/dashboard-mobile.js')) ?>?v=<?= time() ?>" defer></script>
</head>
<body class="landing-body">

<?php if (!$isProfileComplete): ?>
<!-- -- PROFILE COMPLETION OVERLAY (STEP WIZARD) -- -->
<div class="profile-overlay" id="profileCompletionOverlay">
  <div class="profile-overlay-card">

    <!-- Step progress bar -->
    <div class="cp-steps">
      <div class="cp-step active" data-step="1"><span class="cp-step-num">1</span><span class="cp-step-label">Company Info</span></div>
      <div class="cp-step-line"></div>
      <div class="cp-step" data-step="2"><span class="cp-step-num">2</span><span class="cp-step-label">Skills</span></div>
      <div class="cp-step-line"></div>
      <div class="cp-step" data-step="3"><span class="cp-step-num">3</span><span class="cp-step-label">Finish</span></div>
    </div>

    <?php if ($flashError): ?>
      <div class="cp-alert cp-err"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <form method="POST" action="/QuickHire/Public/actions/save_profile.php" enctype="multipart/form-data" id="empProfileForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="profile_type" value="EMPLOYER">

      <!-- -- STEP 1: Company Info -- -->
      <div class="cp-step-panel active" id="emp-step-1">
        <h2 class="cp-step-title">🏢 Company Information</h2>
        <p class="cp-step-desc">Set up your company profile so jobseekers can find you.</p>

        <div class="cp-grid">
          <div class="cp-full">
            <div class="avatar-upload" onclick="openAvatarCamera('ov_emp_avatar_data', 'ovEmpAvatarPreview')">
              <div class="avatar-preview" id="ovEmpAvatarPreview">
                <?php if (!empty($profile['profile_picture_url'])): ?>
                  <img src="<?= htmlspecialchars(public_url($profile['profile_picture_url'])) ?>" alt="Profile Picture">
                <?php else: ?>
                  <?= strtoupper(substr($userInfo['first_name'] ?? 'E', 0, 1)) ?>
                <?php endif; ?>
              </div>
              <div class="avatar-overlay"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
              <input type="hidden" id="ov_emp_avatar_data" name="captured_avatar">
            </div>
            <div class="cp-avatar-name"><?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?></div>
          </div>

          <div>
            <label>Business Name / Company Name *</label>
            <input name="company_name" value="<?= htmlspecialchars($profile['company_name'] ?? '') ?>" required placeholder="e.g. Acme Corp">
          </div>

          <div>
            <label>Country *</label>
            <select name="country" required>
              <option value="">Select Country</option>
              <?php foreach (['Afghanistan','Albania','Algeria','Argentina','Australia','Austria','Bangladesh','Belgium','Brazil','Canada','China','Colombia','Denmark','Egypt','Finland','France','Germany','Greece','India','Indonesia','Ireland','Italy','Japan','Malaysia','Mexico','Netherlands','New Zealand','Norway','Pakistan','Philippines','Poland','Portugal','Russia','Saudi Arabia','Singapore','South Africa','South Korea','Spain','Sweden','Switzerland','Thailand','Turkey','United Arab Emirates','United Kingdom','United States','Vietnam','Other'] as $c): ?>
                <option value="<?= $c ?>" <?= ($profile['country'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="cp-nav">
          <span></span>
          <button type="button" class="cp-btn-next" onclick="empNextStep(1)">Next →</button>
        </div>
      </div><!-- /emp-step-1 -->

      <!-- -- STEP 2: Skills -- -->
      <div class="cp-step-panel" id="emp-step-2">
        <h2 class="cp-step-title">🛠️ Skills You Look For</h2>
        <p class="cp-step-desc">Select skills you commonly require from jobseekers. This helps with matching.</p>

        <div class="skills-container">
          <input type="text" class="skills-search" placeholder="🔍 Search skills..." id="ovEmpSkillsSearch">
          <div class="skills-tabs">
            <div class="skills-tab active" data-category="all">All</div>
            <?php foreach (array_keys($overlayEmpSkillsByCategory) as $cat): ?>
              <div class="skills-tab" data-category="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></div>
            <?php endforeach; ?>
          </div>
          <div class="skills-grid" id="ovEmpSkillsContainer">
            <?php foreach ($overlayEmpSkillsByCategory as $cat => $skills): ?>
              <div class="category-section" data-category="<?= htmlspecialchars($cat) ?>">
                <div class="category-title"><?= htmlspecialchars($cat) ?></div>
                <div class="skills-row">
                  <?php foreach ($skills as $skill): ?>
                    <label class="skill-checkbox" data-skill-name="<?= strtolower(htmlspecialchars($skill['name'])) ?>" style="display:flex;align-items:center;gap:6px;cursor:pointer;margin:0;padding:2px 0;font-weight:600;font-size:13px;line-height:1.4;">
                      <input type="checkbox" name="required_skill_ids[]" value="<?= $skill['id'] ?>" <?= in_array($skill['id'], $currentRequiredSkills) ? 'checked' : '' ?> style="width:14px;height:14px;flex-shrink:0;cursor:pointer;accent-color:#6366f1;margin:0;">
                      <?= htmlspecialchars($skill['name']) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="cp-nav">
          <button type="button" class="cp-btn-back" onclick="empGoStep(1)">← Back</button>
          <button type="button" class="cp-btn-next" onclick="empGoStep(3)">Next →</button>
        </div>
      </div><!-- /emp-step-2 -->

      <!-- -- STEP 3: Review & Submit -- -->
      <div class="cp-step-panel" id="emp-step-3">
        <h2 class="cp-step-title">✅ All Set!</h2>
        <p class="cp-step-desc">Your profile is ready. Click below to enter your dashboard.</p>

        <div class="cp-review-box">
          <div class="cp-review-row"><span class="cp-review-label">Company</span><span class="cp-review-val" id="empReviewCompany"></span></div>
          <div class="cp-review-row"><span class="cp-review-label">Country</span><span class="cp-review-val" id="empReviewCountry"></span></div>
          <div class="cp-review-row" style="flex-direction:column;align-items:flex-start;gap:8px;">
            <span class="cp-review-label">Skills selected (<span id="empReviewSkillCount">0</span>)</span>
            <div id="empReviewSkillPills" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
          </div>
        </div>

        <div class="cp-nav">
          <button type="button" class="cp-btn-back" onclick="empGoStep(2)">← Back</button>
          <button type="submit" class="cp-btn-submit">Complete Profile</button>
        </div>
      </div><!-- /emp-step-3 -->

    </form>
  </div>
</div>

<?php require __DIR__ . '/../Partials/scripts/employer-dashboard-script-1.php'; ?>
<?php endif; ?>

<div class="layout">
  <!-- SIDEBAR -->
  <aside class="side">
    <div class="brandRow">
      <img src="<?= htmlspecialchars(public_url('images/quickhire-logo.png')) ?>" alt="QuickHire Logo">
    </div>

    <div class="profileCard">
      <div class="avatar">
        <?php if (!empty($profile['profile_picture_url'])): ?>
          <img src="<?= htmlspecialchars(public_url($profile['profile_picture_url'])) ?>" alt="Avatar">
        <?php else: ?>
          <?= strtoupper(substr($userInfo['first_name'] ?? 'E', 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="name">
          <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
        </div>
        <div class="meta">
          <?= htmlspecialchars(($profile['company_name'] ?? 'Employer')) ?>
        </div>
      </div>
    </div>

    <nav class="nav">
      <button class="success" id="btnFindMatch">🔍 Find Jobseeker</button>

      <div class="nav-section-label">HIRING</div>
      <button id="btnSearchJobseekers">🔍 Search Jobseekers</button>
      <button id="btnPostJob">📢 Post Job</button>
      <button id="btnMessages" type="button" style="position:relative;">
        💬 Messages
        <?php if ($unreadCount > 0): ?>
          <span style="margin-left:auto;background:#ef4444;color:white;border-radius:10px;padding:2px 7px;font-size:11px;font-weight:700;"><?= $unreadCount ?></span>
        <?php endif; ?>
      </button>

      <div class="nav-section-label">ACCOUNT</div>
      <button id="btnHome">🏠 Home</button>
      <button id="btnEditProfile">✏️ Edit Profile</button>
      <button id="btnEditPreferences">🎯 Matching Preferences</button>

      <button id="btnSettings" type="button">⚙️ Settings</button>

      <div class="nav-section-label">SESSION</div>
      <form method="POST" action="/QuickHire/Public/actions/logout.php" style="margin:0;" onsubmit="return confirm('Are you sure you want to logout?');">
        <button class="danger" type="submit">🚪 Logout</button>
      </form>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="main">
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
          <span class="pill">Company: <?= htmlspecialchars($profile['company_name'] ?? '') ?></span>
          <span class="pill">Country: <?= htmlspecialchars($profile['country'] ?? '') ?></span>
        </div>
        <div class="pillRow">
          <span class="pill">Required Skills: <?= count($currentRequiredSkills) ?> selected</span>
        </div>

        <div style="margin-top:14px; color:#f8fafc; line-height:1.5;">
          <strong style="color:#f8fafc;">Profile Status:</strong><br>
          <?php if (!empty($profile['company_name']) && !empty($profile['country'])): ?>
            <span style="color:#f8fafc;">✅ Profile complete and active</span>
          <?php else: ?>
            <span style="color:#f8fafc;">⚠️ Please complete your profile to start matching</span>
          <?php endif; ?>
        </div>

        <?php if (!empty($currentRequiredSkills)): ?>
          <div style="margin-top:14px;">
            <strong style="color:#f8fafc;">Skills You Look For:</strong><br>
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
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="profile_type" value="EMPLOYER">

        <div class="grid">
          <div style="grid-column:1/-1; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius:16px; padding: 40px 20px 24px; position:relative; overflow:hidden;">
            <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%, rgba(99,102,241,0.15) 0%, transparent 70%);pointer-events:none;"></div>
            <div class="avatar-upload" onclick="openAvatarCamera('profile_picture_emp_data', 'empEditAvatarPreview')">
              <div class="avatar-preview" id="empEditAvatarPreview">
                <?php if (!empty($profile['profile_picture_url'])): ?>
                  <img src="<?= htmlspecialchars(public_url($profile['profile_picture_url'])) ?>" alt="Profile Picture">
                <?php else: ?>
                  <?= strtoupper(substr($userInfo['first_name'] ?? 'E', 0, 1)) ?>
                <?php endif; ?>
              </div>
              <div class="avatar-overlay"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
              <input type="hidden" id="profile_picture_emp_data" name="captured_avatar">
            </div>
            <div style="text-align: center; margin-top: 10px; font-weight: 700; color: #f8fafc;">
              <div id="nameDisplay" style="cursor: pointer; padding: 5px; border-radius: 5px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'" onclick="editName()">
                <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
                <span style="margin-left: 6px; opacity: 0.5;"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>
              </div>
              <div id="nameEdit" style="display: none;">
                <input type="text" id="firstNameInput" name="first_name" value="<?= htmlspecialchars($userInfo['first_name'] ?? '') ?>" placeholder="First Name" style="width: 45%; padding: 8px; margin: 5px 2%; border: 1px solid var(--line); border-radius: 8px;">
                <input type="text" id="lastNameInput" name="last_name" value="<?= htmlspecialchars($userInfo['last_name'] ?? '') ?>" placeholder="Last Name" style="width: 45%; padding: 8px; margin: 5px 2%; border: 1px solid var(--line); border-radius: 8px;">
                <div style="margin-top: 10px;">
                  <button type="button" onclick="saveName()" style="padding: 6px 12px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; margin-right: 5px;">Save</button>
                  <button type="button" onclick="cancelEditName()" style="padding: 6px 12px; background: rgba(255,255,255,0.1); color: #e2e8f0; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                </div>
              </div>
            </div>
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Country *</label>
            <select name="country" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <option value="">Select Country</option>
              <option value="Afghanistan" <?= ($profile['country'] ?? '') === 'Afghanistan' ? 'selected' : '' ?>>Afghanistan</option>
              <option value="Albania" <?= ($profile['country'] ?? '') === 'Albania' ? 'selected' : '' ?>>Albania</option>
              <option value="Algeria" <?= ($profile['country'] ?? '') === 'Algeria' ? 'selected' : '' ?>>Algeria</option>
              <option value="Argentina" <?= ($profile['country'] ?? '') === 'Argentina' ? 'selected' : '' ?>>Argentina</option>
              <option value="Australia" <?= ($profile['country'] ?? '') === 'Australia' ? 'selected' : '' ?>>Australia</option>
              <option value="Austria" <?= ($profile['country'] ?? '') === 'Austria' ? 'selected' : '' ?>>Austria</option>
              <option value="Bangladesh" <?= ($profile['country'] ?? '') === 'Bangladesh' ? 'selected' : '' ?>>Bangladesh</option>
              <option value="Belgium" <?= ($profile['country'] ?? '') === 'Belgium' ? 'selected' : '' ?>>Belgium</option>
              <option value="Brazil" <?= ($profile['country'] ?? '') === 'Brazil' ? 'selected' : '' ?>>Brazil</option>
              <option value="Canada" <?= ($profile['country'] ?? '') === 'Canada' ? 'selected' : '' ?>>Canada</option>
              <option value="China" <?= ($profile['country'] ?? '') === 'China' ? 'selected' : '' ?>>China</option>
              <option value="Colombia" <?= ($profile['country'] ?? '') === 'Colombia' ? 'selected' : '' ?>>Colombia</option>
              <option value="Denmark" <?= ($profile['country'] ?? '') === 'Denmark' ? 'selected' : '' ?>>Denmark</option>
              <option value="Egypt" <?= ($profile['country'] ?? '') === 'Egypt' ? 'selected' : '' ?>>Egypt</option>
              <option value="Finland" <?= ($profile['country'] ?? '') === 'Finland' ? 'selected' : '' ?>>Finland</option>
              <option value="France" <?= ($profile['country'] ?? '') === 'France' ? 'selected' : '' ?>>France</option>
              <option value="Germany" <?= ($profile['country'] ?? '') === 'Germany' ? 'selected' : '' ?>>Germany</option>
              <option value="Greece" <?= ($profile['country'] ?? '') === 'Greece' ? 'selected' : '' ?>>Greece</option>
              <option value="India" <?= ($profile['country'] ?? '') === 'India' ? 'selected' : '' ?>>India</option>
              <option value="Indonesia" <?= ($profile['country'] ?? '') === 'Indonesia' ? 'selected' : '' ?>>Indonesia</option>
              <option value="Ireland" <?= ($profile['country'] ?? '') === 'Ireland' ? 'selected' : '' ?>>Ireland</option>
              <option value="Italy" <?= ($profile['country'] ?? '') === 'Italy' ? 'selected' : '' ?>>Italy</option>
              <option value="Japan" <?= ($profile['country'] ?? '') === 'Japan' ? 'selected' : '' ?>>Japan</option>
              <option value="Malaysia" <?= ($profile['country'] ?? '') === 'Malaysia' ? 'selected' : '' ?>>Malaysia</option>
              <option value="Mexico" <?= ($profile['country'] ?? '') === 'Mexico' ? 'selected' : '' ?>>Mexico</option>
              <option value="Netherlands" <?= ($profile['country'] ?? '') === 'Netherlands' ? 'selected' : '' ?>>Netherlands</option>
              <option value="New Zealand" <?= ($profile['country'] ?? '') === 'New Zealand' ? 'selected' : '' ?>>New Zealand</option>
              <option value="Norway" <?= ($profile['country'] ?? '') === 'Norway' ? 'selected' : '' ?>>Norway</option>
              <option value="Pakistan" <?= ($profile['country'] ?? '') === 'Pakistan' ? 'selected' : '' ?>>Pakistan</option>
              <option value="Philippines" <?= ($profile['country'] ?? '') === 'Philippines' ? 'selected' : '' ?>>Philippines</option>
              <option value="Poland" <?= ($profile['country'] ?? '') === 'Poland' ? 'selected' : '' ?>>Poland</option>
              <option value="Portugal" <?= ($profile['country'] ?? '') === 'Portugal' ? 'selected' : '' ?>>Portugal</option>
              <option value="Russia" <?= ($profile['country'] ?? '') === 'Russia' ? 'selected' : '' ?>>Russia</option>
              <option value="Saudi Arabia" <?= ($profile['country'] ?? '') === 'Saudi Arabia' ? 'selected' : '' ?>>Saudi Arabia</option>
              <option value="Singapore" <?= ($profile['country'] ?? '') === 'Singapore' ? 'selected' : '' ?>>Singapore</option>
              <option value="South Africa" <?= ($profile['country'] ?? '') === 'South Africa' ? 'selected' : '' ?>>South Africa</option>
              <option value="South Korea" <?= ($profile['country'] ?? '') === 'South Korea' ? 'selected' : '' ?>>South Korea</option>
              <option value="Spain" <?= ($profile['country'] ?? '') === 'Spain' ? 'selected' : '' ?>>Spain</option>
              <option value="Sweden" <?= ($profile['country'] ?? '') === 'Sweden' ? 'selected' : '' ?>>Sweden</option>
              <option value="Switzerland" <?= ($profile['country'] ?? '') === 'Switzerland' ? 'selected' : '' ?>>Switzerland</option>
              <option value="Thailand" <?= ($profile['country'] ?? '') === 'Thailand' ? 'selected' : '' ?>>Thailand</option>
              <option value="Turkey" <?= ($profile['country'] ?? '') === 'Turkey' ? 'selected' : '' ?>>Turkey</option>
              <option value="United Arab Emirates" <?= ($profile['country'] ?? '') === 'United Arab Emirates' ? 'selected' : '' ?>>United Arab Emirates</option>
              <option value="United Kingdom" <?= ($profile['country'] ?? '') === 'United Kingdom' ? 'selected' : '' ?>>United Kingdom</option>
              <option value="United States" <?= ($profile['country'] ?? '') === 'United States' ? 'selected' : '' ?>>United States</option>
              <option value="Vietnam" <?= ($profile['country'] ?? '') === 'Vietnam' ? 'selected' : '' ?>>Vietnam</option>
              <option value="Japan" <?= ($profile['country'] ?? '') === 'Japan' ? 'selected' : '' ?>>Japan</option>
              <option value="China" <?= ($profile['country'] ?? '') === 'China' ? 'selected' : '' ?>>China</option>
              <option value="Other" <?= ($profile['country'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
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

    <!-- Settings Content (Hidden by default) -->
    <div class="card" id="settingsContent" style="display:none;">
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
          This permanently removes your employer profile, jobs, conversations, calls, and login account.
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

    <!-- Search Content (Hidden by default) -->
    <div class="card" id="searchContent" style="display:none;">
      <h2>🔍 Search Job Seekers</h2>
      <p style="color: var(--muted); margin-bottom: 20px;">
        Search for job seekers by name, job role, or skills. Click "Message" to start a conversation.
      </p>

      <div class="search-container">
        <div class="search-input-container">
          <input type="text" id="searchInput" placeholder="Search by name, job role, or skills (e.g., 'designer', 'Photoshop', 'John')" class="search-input">
          <button type="button" id="searchButton" class="search-button">Search</button>
        </div>
        
        <div id="searchResults" class="search-results" style="display: none;">
          <div class="search-results-header">
            <span id="searchResultsCount">0 results found</span>
          </div>
          <div id="searchResultsList" class="search-results-list">
            <!-- Results will be populated here -->
          </div>
        </div>
        
        <div id="searchEmpty" class="search-empty" style="display: none;">
          <div class="empty-state">
            <h3>No results found</h3>
            <p>Try searching with different keywords like job roles (designer, developer) or skills (Photoshop, JavaScript).</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Job Posting Content (Hidden by default) -->
    <div class="card" id="jobPostingContent" style="display:none;">


      <!-- Job Posting Form -->
      <form id="jobPostingForm" style="margin-bottom: 30px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        
        <div class="form-group">
          <label for="job_title">Job Title *</label>
          <input type="text" id="job_title" name="title" required maxlength="255" placeholder="e.g., Senior Frontend Developer">
        </div>

        <div class="form-group">
          <label for="job_description">Job Description *</label>
          <textarea id="job_description" name="description" required maxlength="5000" rows="6" placeholder="Describe the role, responsibilities, requirements, and what you're looking for in a candidate..."></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="job_role_title">Role Category</label>
            <select id="job_role_title" name="role_title">
              <option value="">Select Role Category</option>
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
          </div>

          <div class="form-group">
            <label for="job_employment_type">Employment Type</label>
            <select id="job_employment_type" name="employment_type">
              <option value="">Select Employment Type</option>
              <option value="FULL_TIME">Full-time</option>
              <option value="PART_TIME">Part-time</option>
              <option value="CONTRACT">Contract</option>
              <option value="FREELANCE">Freelance</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="job_country">Location/Country</label>
            <select id="job_country" name="country">
              <option value="">Select Country</option>
              <option value="Remote">Remote</option>
              <?php foreach (['Afghanistan','Albania','Algeria','Argentina','Australia','Austria','Bangladesh','Belgium','Brazil','Canada','China','Colombia','Denmark','Egypt','Finland','France','Germany','Greece','India','Indonesia','Ireland','Italy','Japan','Malaysia','Mexico','Netherlands','New Zealand','Norway','Pakistan','Philippines','Poland','Portugal','Russia','Saudi Arabia','Singapore','South Africa','South Korea','Spain','Sweden','Switzerland','Thailand','Turkey','United Arab Emirates','United Kingdom','United States','Vietnam','Other'] as $c): ?>
                <option value="<?= $c ?>"><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="job_rate_per_hour">Rate per Hour (USD)</label>
            <input type="number" id="job_rate_per_hour" name="rate_per_hour" step="0.01" min="0" max="999999.99" placeholder="e.g., 50.00">
            <small style="color: var(--muted); font-size: 12px; margin-top: 4px; display: block;">Optional - Leave blank if not applicable</small>
          </div>

          <div class="form-group">
            <label for="job_hours_per_week">Hours per Week</label>
            <input type="number" id="job_hours_per_week" name="hours_per_week" min="1" max="168" placeholder="e.g., 40">
            <small style="color: var(--muted); font-size: 12px; margin-top: 4px; display: block;">Optional - Leave blank if not applicable</small>
          </div>
        </div>

        <div class="form-group">
          <label>Required Skills (Optional)</label>
          <div style="font-size:12px; color:var(--muted); margin-bottom:6px;">Select skills that are required for this position</div>
          
          <div class="skills-container">
            <input type="text" class="skills-search" placeholder="🔍 Search skills..." id="jobSkillsSearch">
            
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
            
            <div class="skills-grid" id="jobSkillsContainer">
              <?php 
                // Create skills by category for job posting form
                $jobSkillsByCategory = [];
                foreach ($allSkills as $skill) {
                  $jobSkillsByCategory[$skill['category']][] = $skill;
                }
                
                foreach ($jobSkillsByCategory as $category => $skills): 
              ?>
                <div class="category-section" data-category="<?= htmlspecialchars($category) ?>">
                  <div class="category-title"><?= htmlspecialchars($category) ?></div>
                  <div class="skills-row">
                    <?php foreach ($skills as $skill): ?>
                      <label class="skill-checkbox" data-skill-name="<?= strtolower($skill['name']) ?>" style="display:flex;align-items:center;gap:6px;cursor:pointer;margin:0;padding:2px 0;font-weight:600;font-size:13px;line-height:1.4;">
                        <input type="checkbox" name="skill_ids[]" value="<?= $skill['id'] ?>" style="width:14px;height:14px;flex-shrink:0;cursor:pointer;accent-color:#6366f1;margin:0;">
                        <?= htmlspecialchars($skill['name']) ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:20px; justify-content:flex-end;">
          <button type="submit" class="btn primary" id="submitJobPost">Post Job</button>
          <button type="button" class="btn outline" id="btnCancelJobPost">Cancel</button>
        </div>
      </form>

      <!-- My Job Posts Section -->
      <div style="border-top: 1px solid var(--line); padding-top: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h3>📋 My Job Posts</h3>
        </div>
        <div id="myJobPosts" class="job-posts-list">
          <div class="loading">Loading your job posts...</div>
        </div>
      </div>
    </div>

    <!-- Preferences Modal -->
    <div class="modal" id="preferencesModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>🎯 Matching Preferences</h2>
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
            <select id="pref_role_title" name="role_title" required>
              <option value="">Select Job Role</option>
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
          </div>

          <div class="form-group">
            <label for="pref_country">Country *</label>
            <select id="pref_country" name="country" required>
              <option value="">Select Country</option>
              <option value="Afghanistan">Afghanistan</option>
              <option value="Albania">Albania</option>
              <option value="Algeria">Algeria</option>
              <option value="Argentina">Argentina</option>
              <option value="Australia">Australia</option>
              <option value="Austria">Austria</option>
              <option value="Bangladesh">Bangladesh</option>
              <option value="Belgium">Belgium</option>
              <option value="Brazil">Brazil</option>
              <option value="Canada">Canada</option>
              <option value="China">China</option>
              <option value="Colombia">Colombia</option>
              <option value="Denmark">Denmark</option>
              <option value="Egypt">Egypt</option>
              <option value="Finland">Finland</option>
              <option value="France">France</option>
              <option value="Germany">Germany</option>
              <option value="Greece">Greece</option>
              <option value="India">India</option>
              <option value="Indonesia">Indonesia</option>
              <option value="Ireland">Ireland</option>
              <option value="Italy">Italy</option>
              <option value="Japan">Japan</option>
              <option value="Malaysia">Malaysia</option>
              <option value="Mexico">Mexico</option>
              <option value="Netherlands">Netherlands</option>
              <option value="New Zealand">New Zealand</option>
              <option value="Norway">Norway</option>
              <option value="Pakistan">Pakistan</option>
              <option value="Philippines">Philippines</option>
              <option value="Poland">Poland</option>
              <option value="Portugal">Portugal</option>
              <option value="Russia">Russia</option>
              <option value="Saudi Arabia">Saudi Arabia</option>
              <option value="Singapore">Singapore</option>
              <option value="South Africa">South Africa</option>
              <option value="South Korea">South Korea</option>
              <option value="Spain">Spain</option>
              <option value="Sweden">Sweden</option>
              <option value="Switzerland">Switzerland</option>
              <option value="Thailand">Thailand</option>
              <option value="Turkey">Turkey</option>
              <option value="United Arab Emirates">United Arab Emirates</option>
              <option value="United Kingdom">United Kingdom</option>
              <option value="United States">United States</option>
              <option value="Vietnam">Vietnam</option>
            </select>
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
                        <label class="skill-checkbox" data-skill-name="<?= strtolower($skill['name']) ?>" style="display:flex;align-items:center;gap:6px;cursor:pointer;margin:0;padding:2px 0;font-weight:600;font-size:13px;line-height:1.4;">
                          <input type="checkbox" id="pref_skill_<?= (int)$skill['id'] ?>" name="skill_ids[]" value="<?= (int)$skill['id'] ?>" style="width:14px;height:14px;flex-shrink:0;cursor:pointer;accent-color:#6366f1;margin:0;">
                          <?= htmlspecialchars($skill['name']) ?>
                        </label>
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

    <!-- MESSAGING PANEL -->
    <div class="messaging-panel" id="messagingPanel">
      <div class="messaging-header">
        <h3>💬 Messages</h3>
      </div>
      
      <div class="messaging-content">
        <div class="conversations-sidebar">

          <!-- Search + Job filter -->
          <div style="padding:12px 12px 0; background:#0f172a; border-bottom:1px solid rgba(255,255,255,0.08);">
            <!-- Search -->
            <div style="position:relative; margin-bottom:10px;">
              <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#64748b;font-size:14px;pointer-events:none;">🔍</span>
              <input type="text" id="convSearchInput" placeholder="Search conversations..."
                style="width:100%;padding:8px 10px 8px 32px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;font-size:13px;background:rgba(255,255,255,0.05);color:#f1f5f9;box-sizing:border-box;outline:none;font-family:inherit;"
                oninput="filterConversations()">
            </div>
            <!-- Job filter pills -->
            <div id="jobFilterBar" style="display:none; padding-bottom:10px;">
              <div id="jobFilterPills" style="display:flex;gap:6px;flex-wrap:wrap;"></div>
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

    <!-- -- JOBSEEKER PROFILE VIEW -- -->
    <div class="card" id="jsProfileView" style="display:none; max-width:none; width:100%; padding:0; overflow:hidden;">
      <!-- Cover -->
      <div id="jsProfileCover" style="height:160px;background:linear-gradient(135deg,#1e293b 0%,#0f172a 50%,#1e1b4b 100%);position:relative;border-radius:18px 18px 0 0;">
        <div style="position:absolute;bottom:-50px;left:32px;">
          <div id="jsProfileAvatar" style="width:100px;height:100px;border-radius:50%;border:4px solid #0f172a;overflow:visible;background:#1e293b;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:900;color:#a5b4fc;position:relative;"></div>
        </div>
        <div style="position:absolute;top:16px;right:16px;display:flex;gap:10px;align-items:center;">
          <button id="jsProfileMsgBtn" style="padding:8px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:10px;color:white;font-weight:700;font-size:13px;cursor:pointer;">💬 Message</button>
          <button onclick="showSearchJobseekers()" style="padding:8px 18px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:10px;color:#e2e8f0;font-weight:700;font-size:13px;cursor:pointer;">← Back</button>
        </div>
      </div>

      <div style="padding:64px 32px 32px;">
        <h2 id="jsProfileName" style="margin:0 0 4px;font-size:26px;font-weight:900;color:#f8fafc;"></h2>
        <p id="jsProfileRole" style="margin:0 0 6px;color:#6366f1;font-weight:600;font-size:16px;"></p>
        <p id="jsProfileMeta" style="margin:0 0 20px;color:#64748b;font-size:14px;"></p>

        <div id="jsProfilePills" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:28px;"></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
          <div style="grid-column:1/-1;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:20px;">
            <h3 style="margin:0 0 12px;font-size:15px;font-weight:800;color:#f8fafc;">About</h3>
            <p id="jsProfileAbout" style="margin:0;color:#94a3b8;line-height:1.7;font-size:14px;"></p>
          </div>
          <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:20px;">
            <h3 style="margin:0 0 14px;font-size:15px;font-weight:800;color:#f8fafc;">Skills</h3>
            <div id="jsProfileSkills" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
          </div>
          <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:20px;">
            <h3 style="margin:0 0 14px;font-size:15px;font-weight:800;color:#f8fafc;">Details</h3>
            <div id="jsProfileDetails" style="display:flex;flex-direction:column;gap:10px;"></div>
          </div>
        </div>
      </div>
    </div>

  </main>
</div>
<?php require __DIR__ . '/../Partials/scripts/employer-dashboard-script-2.php'; ?>

<?php require __DIR__ . '/../Partials/scripts/employer-dashboard-script-3.php'; ?>

<?php require __DIR__ . '/../Partials/scripts/employer-dashboard-script-4.php'; ?>

<?php require __DIR__ . '/../Partials/scripts/employer-dashboard-script-5.php'; ?>

<!-- Floating chat menu  appended to body to escape overflow:hidden containers -->
<div id="chatMenu" style="display:none;position:fixed;background:#1e293b;border:1px solid rgba(255,255,255,0.12);border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.5);min-width:190px;z-index:99999;overflow:hidden;">
  <button onclick="deleteConversation(currentConversationId)" style="display:flex;align-items:center;gap:10px;width:100%;padding:13px 16px;background:none;border:none;cursor:pointer;color:#fca5a5;font-size:14px;font-weight:600;" onmouseover="this.style.background='rgba(239,68,68,0.12)'" onmouseout="this.style.background='none'">🗑 Delete Conversation</button>
</div>

<!-- -- Edit Job Modal -- -->
<div id="editJobModal" class="edit-job-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);z-index:99999;overflow-y:auto;padding:40px 16px;">
  <div class="edit-job-dialog" style="background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:32px;max-width:760px;width:100%;margin:0 auto;box-shadow:0 24px 60px rgba(0,0,0,0.5);">
    <div class="edit-job-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <h2 style="margin:0;font-size:20px;font-weight:900;color:#f8fafc;">✏️ Edit Job Post</h2>
      <button type="button" class="edit-job-close" onclick="closeEditJobModal()" aria-label="Close edit job modal" style="background:none;border:none;color:#94a3b8;font-size:22px;cursor:pointer;line-height:1;padding:4px;">&times;</button>
    </div>

    <form id="editJobForm">
      <input type="hidden" id="edit_job_id">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

      <div class="edit-job-form-grid" style="display:grid;grid-template-columns:1fr;gap:16px;">

        <div class="edit-job-field">
          <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Job Title *</label>
          <input type="text" id="edit_job_title" required maxlength="255" placeholder="e.g., Senior Frontend Developer"
            style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:rgba(255,255,255,0.05);color:#f8fafc;font-family:inherit;font-size:14px;box-sizing:border-box;">
        </div>

        <div class="edit-job-field">
          <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Job Description *</label>
          <textarea id="edit_job_description" required maxlength="5000" rows="5" placeholder="Describe the role..."
            style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:rgba(255,255,255,0.05);color:#f8fafc;font-family:inherit;font-size:14px;resize:vertical;box-sizing:border-box;"></textarea>
        </div>

        <div class="edit-job-two-col" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div class="edit-job-field">
            <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Role Category</label>
            <select id="edit_job_role_title" style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:#1e293b;color:#f8fafc;font-family:inherit;font-size:14px;">
              <option value="">Select Role</option>
              <?php foreach (['Software Engineer','Software Developer','Web Developer','Mobile Developer','Full Stack Developer','Frontend Developer','Backend Developer','DevOps Engineer','Cloud Engineer','Data Scientist','Data Engineer','Data Analyst','Machine Learning Engineer','AI Engineer','Database Administrator','System Administrator','Network Engineer','Security Engineer','QA Engineer','QA Automation Engineer','UI/UX Designer','Product Designer','Technical Product Manager','IT Project Manager','Scrum Master','Business Intelligence Analyst','IT Support Specialist','Technical Writer'] as $r): ?>
                <option value="<?= $r ?>"><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="edit-job-field">
            <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Employment Type</label>
            <select id="edit_job_employment_type" style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:#1e293b;color:#f8fafc;font-family:inherit;font-size:14px;">
              <option value="">Select Type</option>
              <option value="FULL_TIME">Full-time</option>
              <option value="PART_TIME">Part-time</option>
              <option value="CONTRACT">Contract</option>
              <option value="FREELANCE">Freelance</option>
            </select>
          </div>
        </div>

        <div class="edit-job-three-col" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
          <div class="edit-job-field">
            <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Country</label>
            <select id="edit_job_country" style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:#1e293b;color:#f8fafc;font-family:inherit;font-size:14px;">
              <option value="">Select Country</option>
              <?php foreach (['Afghanistan','Albania','Algeria','Argentina','Australia','Austria','Bangladesh','Belgium','Brazil','Canada','China','Colombia','Denmark','Egypt','Finland','France','Germany','Greece','India','Indonesia','Ireland','Italy','Japan','Malaysia','Mexico','Netherlands','New Zealand','Norway','Pakistan','Philippines','Poland','Portugal','Russia','Saudi Arabia','Singapore','South Africa','South Korea','Spain','Sweden','Switzerland','Thailand','Turkey','United Arab Emirates','United Kingdom','United States','Vietnam','Remote','Other'] as $c): ?>
                <option value="<?= $c ?>"><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="edit-job-field">
            <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Rate/hr (USD)</label>
            <input type="number" id="edit_job_rate" step="0.01" min="0" placeholder="e.g. 50.00"
              style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:rgba(255,255,255,0.05);color:#f8fafc;font-family:inherit;font-size:14px;box-sizing:border-box;">
          </div>
          <div class="edit-job-field">
            <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Hrs/week</label>
            <input type="number" id="edit_job_hours" min="1" max="168" placeholder="e.g. 40"
              style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:rgba(255,255,255,0.05);color:#f8fafc;font-family:inherit;font-size:14px;box-sizing:border-box;">
          </div>
        </div>

        <!-- Required Skills -->
        <div class="edit-job-field">
          <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Required Skills <span style="color:#64748b;font-weight:400;">(Optional)</span></label>
          <div class="edit-job-help">Select skills required for this job</div>
          <div class="skills-container">
            <input type="text" class="skills-search" placeholder="🔍 Search skills..." id="editJobSkillsSearch">
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
            <div class="skills-grid" id="editJobSkillsContainer">
              <?php
                $editAllSkillsStmt = $db->pdo()->query("SELECT id, name, category FROM skills ORDER BY category ASC, name ASC");
                $editAllSkills = $editAllSkillsStmt->fetchAll();
                $editSkillsByCategory = [];
                foreach ($editAllSkills as $skill) {
                  $editSkillsByCategory[$skill['category']][] = $skill;
                }
                foreach ($editSkillsByCategory as $cat => $skills):
              ?>
                <div class="category-section" data-category="<?= htmlspecialchars($cat) ?>">
                  <div class="category-title"><?= htmlspecialchars($cat) ?></div>
                  <div class="skills-row">
                    <?php foreach ($skills as $skill): ?>
                      <label class="skill-checkbox" data-skill-name="<?= strtolower(htmlspecialchars($skill['name'])) ?>" style="display:flex;align-items:center;gap:6px;cursor:pointer;margin:0;padding:2px 0;font-weight:600;font-size:13px;line-height:1.4;">
                        <input type="checkbox" class="edit-job-skill" name="edit_skill_ids[]" value="<?= $skill['id'] ?>" style="width:14px;height:14px;flex-shrink:0;cursor:pointer;accent-color:#6366f1;margin:0;">
                        <?= htmlspecialchars($skill['name']) ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>

      <div class="edit-job-actions" style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
        <button type="submit" id="editJobSubmitBtn" style="padding:11px 28px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:12px;color:white;font-weight:800;font-size:14px;cursor:pointer;box-shadow:0 0 20px rgba(99,102,241,0.3);">Save Changes</button>
        <button type="button" onclick="closeEditJobModal()" style="padding:11px 22px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:12px;color:#e2e8f0;font-weight:700;font-size:14px;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../Partials/scripts/employer-dashboard-script-6.php'; ?>

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
<?php require __DIR__ . '/../Partials/scripts/employer-dashboard-script-7.php'; ?>

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

<?php require __DIR__ . '/../Partials/scripts/employer-dashboard-script-8.php'; ?>

</body>
</html>








