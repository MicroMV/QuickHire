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

$flashError = Session::flash('error');
$flashSuccess = Session::flash('success');
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Employer Dashboard - QuickHire</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/landingPage.css">
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/employer-dashboard.css">
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/dark-theme.css">
</head>
<body class="landing-body">

<?php if (!$isProfileComplete): ?>
<!-- ── PROFILE COMPLETION OVERLAY (STEP WIZARD) ── -->
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
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Rongie\QuickHire\Core\Csrf::token()) ?>">
      <input type="hidden" name="profile_type" value="EMPLOYER">

      <!-- ── STEP 1: Company Info ── -->
      <div class="cp-step-panel active" id="emp-step-1">
        <h2 class="cp-step-title">🏢 Company Information</h2>
        <p class="cp-step-desc">Set up your company profile so jobseekers can find you.</p>

        <div class="cp-grid">
          <div class="cp-full">
            <div class="avatar-upload" onclick="document.getElementById('ov_emp_pic').click()">
              <div class="avatar-preview" id="ovEmpAvatarPreview">
                <?php if (!empty($profile['profile_picture_url'])): ?>
                  <img src="/QuickHire/Public/<?= htmlspecialchars($profile['profile_picture_url']) ?>" alt="Profile Picture">
                <?php else: ?>
                  <?= strtoupper(substr($userInfo['first_name'] ?? 'E', 0, 1)) ?>
                <?php endif; ?>
              </div>
              <div class="avatar-overlay"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
              <input type="file" id="ov_emp_pic" name="profile_picture" accept="image/*">
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

      <!-- ── STEP 2: Skills ── -->
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

      <!-- ── STEP 3: Review & Submit ── -->
      <div class="cp-step-panel" id="emp-step-3">
        <h2 class="cp-step-title">✅ All Set!</h2>
        <p class="cp-step-desc">Your profile is ready. Click below to enter your dashboard.</p>

        <div class="cp-review-box">
          <div class="cp-review-row"><span class="cp-review-label">Company</span><span class="cp-review-val" id="empReviewCompany">—</span></div>
          <div class="cp-review-row"><span class="cp-review-label">Country</span><span class="cp-review-val" id="empReviewCountry">—</span></div>
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

<script>
(function() {
  document.body.style.overflow = 'hidden';
  let currentStep = 1;

  function empGoStep(n) {
    document.getElementById('emp-step-' + currentStep).classList.remove('active');
    document.getElementById('emp-step-' + n).classList.add('active');
    document.querySelectorAll('#profileCompletionOverlay .cp-step').forEach(el => {
      const s = parseInt(el.dataset.step);
      el.classList.remove('active','done');
      if (s === n) el.classList.add('active');
      if (s < n)  el.classList.add('done');
    });
    document.querySelectorAll('#profileCompletionOverlay .cp-step-line').forEach((line, i) => {
      line.classList.toggle('done', i < n - 1);
    });
    currentStep = n;
    document.querySelector('.profile-overlay').scrollTop = 0;

    // Populate review on step 3
    if (n === 3) {
      const company = document.querySelector('[name=company_name]')?.value || '—';
      const country = document.querySelector('[name=country]')?.value || '—';
      const checkedBoxes = document.querySelectorAll('#ovEmpSkillsContainer input[type=checkbox]:checked');

      document.getElementById('empReviewCompany').textContent = company;
      document.getElementById('empReviewCountry').textContent = country;
      document.getElementById('empReviewSkillCount').textContent = checkedBoxes.length;

      // Render skill pills
      const pillsContainer = document.getElementById('empReviewSkillPills');
      pillsContainer.innerHTML = '';
      if (checkedBoxes.length === 0) {
        pillsContainer.innerHTML = '<span style="color:#64748b;font-size:13px;font-style:italic;">No skills selected</span>';
      } else {
        checkedBoxes.forEach(cb => {
          const label = cb.closest('label');
          const name = label ? label.textContent.trim() : cb.value;
          const pill = document.createElement('span');
          pill.textContent = name;
          pill.style.cssText = 'background:rgba(99,102,241,0.15);color:#a5b4fc;border:1px solid rgba(99,102,241,0.3);border-radius:20px;padding:3px 10px;font-size:12px;font-weight:600;';
          pillsContainer.appendChild(pill);
        });
      }
    }
  }
  window.empGoStep = empGoStep;

  window.empNextStep = function(from) {
    const panel = document.getElementById('emp-step-' + from);
    panel.querySelectorAll('.cp-invalid').forEach(el => el.classList.remove('cp-invalid'));
    panel.querySelector('.cp-validation-msg')?.remove();
    let valid = true;
    panel.querySelectorAll('[required]').forEach(el => {
      if (!el.value.trim()) { el.classList.add('cp-invalid'); valid = false; }
    });
    if (!valid) {
      const msg = document.createElement('p');
      msg.className = 'cp-validation-msg';
      msg.textContent = 'Please fill in all required fields.';
      const grid = panel.querySelector('.cp-grid');
      if (grid) {
        grid.style.marginBottom = '0';
        grid.after(msg);
      } else {
        panel.querySelector('.cp-nav').before(msg);
      }
      return;
    }
    empGoStep(from + 1);
  };

  // Avatar preview
  const picInput = document.getElementById('ov_emp_pic');
  const preview  = document.getElementById('ovEmpAvatarPreview');
  if (picInput && preview) {
    picInput.addEventListener('change', () => {
      const file = picInput.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = e => { preview.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">'; };
      reader.readAsDataURL(file);
    });
  }

  // Skills search & tab filter
  const search = document.getElementById('ovEmpSkillsSearch');
  const tabs   = document.querySelectorAll('#emp-step-2 .skills-tab');
  const sects  = document.querySelectorAll('#ovEmpSkillsContainer .category-section');
  let activeCategory = 'all';
  function filterSkills() {
    const q = search ? search.value.toLowerCase() : '';
    sects.forEach(sect => {
      const catMatch = activeCategory === 'all' || sect.dataset.category === activeCategory;
      let anyVisible = false;
      sect.querySelectorAll('.skill-checkbox').forEach(cb => {
        const show = catMatch && (!q || (cb.dataset.skillName || '').includes(q));
        cb.style.display = show ? '' : 'none';
        if (show) anyVisible = true;
      });
      sect.style.display = anyVisible ? '' : 'none';
    });
  }
  if (search) search.addEventListener('input', filterSkills);
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeCategory = tab.dataset.category;
      if (search) search.value = '';
      filterSkills();
    });
  });

  // Skill limit: max 10
  const SKILL_LIMIT = 10;
  const skillContainer = document.getElementById('ovEmpSkillsContainer');
  const limitMsg = document.createElement('p');
  limitMsg.style.cssText = 'color:#fca5a5;font-size:13px;font-weight:600;margin:8px 0 0;display:none;';
  limitMsg.textContent = 'Maximum of 10 skills reached.';
  skillContainer.parentElement.appendChild(limitMsg);
  skillContainer.addEventListener('change', e => {
    if (!e.target.matches('input[type=checkbox]')) return;
    const checked = skillContainer.querySelectorAll('input[type=checkbox]:checked');
    if (checked.length > SKILL_LIMIT) { e.target.checked = false; }
    const count = skillContainer.querySelectorAll('input[type=checkbox]:checked').length;
    limitMsg.style.display = count >= SKILL_LIMIT ? 'block' : 'none';
    skillContainer.querySelectorAll('input[type=checkbox]:not(:checked)').forEach(cb => {
      cb.disabled = count >= SKILL_LIMIT;
    });
  });
})();
</script>
<?php endif; ?>

<div class="layout">
  <!-- SIDEBAR -->
  <aside class="side">
    <div class="brandRow">
      <img src="/QuickHire/Public/images/quickhire-logo.png" alt="QuickHire Logo">
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
          <?= htmlspecialchars(($profile['company_name'] ?? 'Employer')) ?>
        </div>
      </div>
    </div>

    <nav class="nav">
      <button class="success" id="btnFindMatch">🔍 Find Jobseeker</button>

      <div class="nav-section-label">HIRING</div>
      <button id="btnSearchJobseekers">🔍 Search Jobseekers</button>
      <button id="btnPostJob">📢 Post Job</button>
      <button id="btnMessages" style="position:relative;">
        💬 Messages
        <?php if ($unreadCount > 0): ?>
          <span style="margin-left:auto;background:#ef4444;color:white;border-radius:10px;padding:2px 7px;font-size:11px;font-weight:700;"><?= $unreadCount ?></span>
        <?php endif; ?>
      </button>

      <div class="nav-section-label">ACCOUNT</div>
      <button id="btnHome">🏠 Home</button>
      <button id="btnEditProfile">✏️ Edit Profile</button>
      <button id="btnEditPreferences">⚙️ Edit Preferences</button>

      <div class="nav-section-label">SESSION</div>
      <form method="POST" action="/QuickHire/Public/actions/logout.php" style="margin:0;">
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
          <div style="grid-column:1/-1; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius:16px; padding: 40px 20px 24px; position:relative; overflow:hidden;">
            <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%, rgba(99,102,241,0.15) 0%, transparent 70%);pointer-events:none;"></div>
            <div class="avatar-upload" onclick="document.getElementById('profile_picture_emp').click()">
              <div class="avatar-preview">
                <?php if (!empty($profile['profile_picture_url'])): ?>
                  <img src="/QuickHire/Public/<?= htmlspecialchars($profile['profile_picture_url']) ?>" alt="Profile Picture">
                <?php else: ?>
                  <?= strtoupper(substr($userInfo['first_name'] ?? 'E', 0, 1)) ?>
                <?php endif; ?>
              </div>
              <div class="avatar-overlay"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
              <input type="file" id="profile_picture_emp" name="profile_picture" accept="image/*">
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
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Rongie\QuickHire\Core\Csrf::token()) ?>">
        
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
          <button type="button" class="btn outline" id="createSampleJob" style="font-size: 13px; padding: 6px 12px;">
            Create Sample Job (for testing)
          </button>
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
          <div id="jobFilterBar" style="padding:12px; border-bottom:2px solid var(--line); background:#f8fafc; display:none;">
            <label style="display:block; font-size:11px; font-weight:700; color:#64748b; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Filter by Job Post</label>
            <select id="jobFilterSelect" style="width:100%; padding:8px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; background:#fff; cursor:pointer; font-weight:500;">
            </select>
          </div>
          <div class="conversations-list" id="conversationsList">
            <div class="loading">Loading conversations...</div>
          </div>
        </div>
        
        <div class="chat-area" id="chatArea">
          <div class="chat-header" id="chatHeader">
            <button class="back-btn" id="backToConversations">← Back</button>
            <div id="chatHeaderAvatar" style="display:none;width:38px;height:38px;border-radius:50%;background:#64748b;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:15px;flex-shrink:0;overflow:hidden;"></div>
            <div class="chat-title" id="chatTitle">Select a conversation</div>
            <div style="margin-left:auto;position:relative;">
              <button id="chatMenuBtn" onclick="toggleChatMenu()" style="display:none;background:none;border:none;cursor:pointer;font-size:20px;color:#64748b;padding:4px 8px;border-radius:6px;line-height:1;" title="Options">⋮</button>
            </div>
          </div>
          
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

    <!-- ── JOBSEEKER PROFILE VIEW ── -->
    <div class="card" id="jsProfileView" style="display:none; max-width:none; width:100%; padding:0; overflow:hidden;">
      <!-- Cover -->
      <div id="jsProfileCover" style="height:160px;background:linear-gradient(135deg,#1e293b 0%,#0f172a 50%,#1e1b4b 100%);position:relative;border-radius:18px 18px 0 0;">
        <div style="position:absolute;bottom:-50px;left:32px;">
          <div id="jsProfileAvatar" style="width:100px;height:100px;border-radius:50%;border:4px solid #0f172a;overflow:hidden;background:#1e293b;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:900;color:#a5b4fc;"></div>
        </div>
        <div style="position:absolute;top:16px;right:16px;display:flex;gap:10px;">
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
<script>
  const btnFindMatch = document.getElementById('btnFindMatch');
  const btnFindMatch2 = document.getElementById('btnFindMatch2');
  const btnHome = document.getElementById('btnHome');
  const btnEditProfile = document.getElementById('btnEditProfile');
  const btnEditProfile2 = document.getElementById('btnEditProfile2');
  const btnEditPreferences = document.getElementById('btnEditPreferences');
  const btnCancelEdit = document.getElementById('btnCancelEdit');
  const btnSearchJobseekers = document.getElementById('btnSearchJobseekers');
  const btnPostJob = document.getElementById('btnPostJob');
  const btnCancelJobPost = document.getElementById('btnCancelJobPost');
  
  const dashboardContent = document.getElementById('dashboardContent');
  const profileEditContent = document.getElementById('profileEditContent');
  const searchContent = document.getElementById('searchContent');
  const jobPostingContent = document.getElementById('jobPostingContent');
  
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

  // Load preferences from database (not localStorage)
  async function loadPreferences() {
    let preferences = {};
    
    // Always load skills from database to get current state
    try {
      const response = await fetch('/QuickHire/Public/actions/get_employer_preferences.php');
      const result = await response.json();
      if (result.ok) {
        preferences.skill_ids = result.skill_ids;
      }
    } catch (error) {
      console.error('Error loading preferences from database:', error);
    }
    
    // Load other preferences from localStorage if available
    const localPrefs = localStorage.getItem('matchingPreferences');
    if (localPrefs) {
      const parsed = JSON.parse(localPrefs);
      preferences.role_title = parsed.role_title;
      preferences.country = parsed.country;
      preferences.employment_type = parsed.employment_type;
    }
    
    return preferences;
  }

  // Save preferences to localStorage and database (only skills to database)
  async function savePreferences(preferences) {
    // Save to localStorage for immediate use (role, country, employment_type)
    localStorage.setItem('matchingPreferences', JSON.stringify({
      role_title: preferences.role_title,
      country: preferences.country,
      employment_type: preferences.employment_type
    }));
    
    // Save skills to database only if they were explicitly set
    if (preferences.skill_ids !== undefined) {
      try {
        const response = await fetch('/QuickHire/Public/actions/save_employer_preferences.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            skill_ids: preferences.skill_ids || []
          })
        });
        
        const result = await response.json();
        if (!result.ok) {
          console.error('Failed to save preferences to database:', result.error);
        }
      } catch (error) {
        console.error('Error saving preferences to database:', error);
      }
    }
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
  async function showPreferencesModal() {
    const savedPrefs = await loadPreferences();
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
      await showPreferencesModal();
      return;
    }

    // Use saved preferences
    const preferences = await loadPreferences();
    await executeJobseekerSearch(preferences);
  }

  async function executeJobseekerSearch(preferences) {
    btnFindMatch.disabled = true;
    btnFindMatch2.disabled = true;
    btnFindMatch.textContent = '🔍 Searching...';
    btnFindMatch2.textContent = 'Searching...';

    const resetButtons = () => {
      btnFindMatch.disabled = false;
      btnFindMatch2.disabled = false;
      btnFindMatch.textContent = '🔍 Find Jobseeker';
      btnFindMatch2.textContent = 'Find Jobseeker';
    };

    try {
      // Proceed with matching using preferences
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
        const url = new URL(response.url);
        const room = url.searchParams.get('room');
        if (room) {
          resetButtons();
          showCallConfirmation(room);
        } else {
          window.location.href = response.url;
        }
        return;
      }

      // If not redirected, check for error
      const text = await response.text();
      if (text.includes('No available jobseeker')) {
        resetButtons();
        showToast('No jobseekers available right now. Please try again later.', 'info');
      } else {
        const roomMatch = text.match(/room=([^"&]+)/);
        if (roomMatch) {
          resetButtons();
          showCallConfirmation(roomMatch[1]);
          return;
        }
        resetButtons();
        showToast('No matches found. Please try again later.', 'info');
      }
    } catch (error) {
      resetButtons();
      showToast('Connection error. Please try again.', 'error');
    }

  function showCallConfirmation(room) {
    document.getElementById('callConfirmModal')?.remove();
    const modal = document.createElement('div');
    modal.id = 'callConfirmModal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);z-index:99999;display:flex;align-items:center;justify-content:center;';
    modal.innerHTML = `
      <div style="background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:36px 40px;max-width:420px;width:90%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,0.5);">
        <div style="font-size:52px;margin-bottom:16px;">📹</div>
        <h2 style="margin:0 0 10px;font-size:22px;font-weight:900;color:#f8fafc;">Ready to Connect!</h2>
        <p style="margin:0 0 28px;color:#94a3b8;font-size:15px;line-height:1.6;">A jobseeker is ready to connect with you. Make sure your camera and microphone are ready before joining.</p>
        <div style="display:flex;gap:12px;justify-content:center;">
          <button onclick="document.getElementById('callConfirmModal').remove()" style="padding:12px 24px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:12px;color:#e2e8f0;font-weight:700;font-size:14px;cursor:pointer;">Cancel</button>
          <button onclick="window.location.href='/QuickHire/Public/call.php?room=${encodeURIComponent(room)}'" style="padding:12px 28px;background:linear-gradient(135deg,#10b981,#059669);border:none;border-radius:12px;color:white;font-weight:800;font-size:14px;cursor:pointer;box-shadow:0 0 20px rgba(16,185,129,0.3);">Join Call →</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  }
    btnFindMatch.textContent = '🔍 Find Jobseeker';
    btnFindMatch2.textContent = 'Find Jobseeker';
  }

  function showDashboard() {
    localStorage.setItem('emp_active_page', 'home');
    dashboardContent.style.display = 'grid';
    profileEditContent.style.display = 'none';
    searchContent.style.display = 'none';
    jobPostingContent.style.display = 'none';
    
    // Update active states
    btnHome.classList.add('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSearchJobseekers.classList.remove('active');
    btnPostJob.classList.remove('active');
    btnMessages.classList.remove('active');
    
    // Update title when showing dashboard
    document.querySelector('.title').textContent = 'Welcome back 👋';
    document.querySelector('.subtitle').textContent = 'Find and connect with qualified jobseekers through skill-based matching.';
  }

  function showProfileEdit() {
    localStorage.setItem('emp_active_page', 'edit');
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'block';
    searchContent.style.display = 'none';
    jobPostingContent.style.display = 'none';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.add('active');
    btnEditProfile2.classList.add('active');
    btnSearchJobseekers.classList.remove('active');
    btnPostJob.classList.remove('active');
    btnMessages.classList.remove('active');
    
    // Update title when showing edit form
    document.querySelector('.title').textContent = 'Edit Your Profile';
    document.querySelector('.subtitle').textContent = 'Update your company information and skill requirements for better matching.';
  }

  function showSearch() {
    localStorage.setItem('emp_active_page', 'search');
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'none';
    searchContent.style.display = 'block';
    jobPostingContent.style.display = 'none';
    document.getElementById('jsProfileView').style.display = 'none';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSearchJobseekers.classList.add('active');
    btnPostJob.classList.remove('active');
    btnMessages.classList.remove('active');
    
    // Update title when showing search
    document.querySelector('.title').textContent = 'Search Job Seekers';
    document.querySelector('.subtitle').textContent = 'Find qualified candidates by searching their names, job roles, or skills.';
    
    // Focus on search input
    document.getElementById('searchInput').focus();
  }

  function showJobPosting() {
    localStorage.setItem('emp_active_page', 'jobs');
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'none';
    searchContent.style.display = 'none';
    jobPostingContent.style.display = 'block';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSearchJobseekers.classList.remove('active');
    btnPostJob.classList.add('active');
    btnMessages.classList.remove('active');
    
    // Update title when showing job posting
    document.querySelector('.title').textContent = 'Post a Job';
    document.querySelector('.subtitle').textContent = 'Create job postings to attract qualified candidates to your company.';
    
    // Load existing job posts
    loadMyJobPosts();
    
    // Focus on job title input
    document.getElementById('job_title').focus();
  }

  // Initialize with Home active
  btnHome.classList.add('active');

  // Restore last active page on reload
  const savedEmpPage = localStorage.getItem('emp_active_page');
  if (savedEmpPage === 'edit') showProfileEdit();
  else if (savedEmpPage === 'search') showSearch();
  else if (savedEmpPage === 'jobs') showJobPosting();
  else showDashboard(); // messages always resets to home on reload

  btnFindMatch.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    findJobseeker();
  });
  
  btnFindMatch2.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    findJobseeker();
  });
  btnHome.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showDashboard();
  });
  
  btnEditProfile.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showProfileEdit();
  });
  
  btnEditProfile2.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showProfileEdit();
  });
  
  btnEditPreferences.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showPreferencesModal();
  });
  
  btnSearchJobseekers.addEventListener('click', function() {
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showSearch();
  });

  window.showSearchJobseekers = showSearch;

  btnPostJob.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showJobPosting();
  });
  
  btnCancelEdit.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showDashboard();
  });

  btnCancelJobPost.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showDashboard();
  });

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
    
    // Save preferences (both localStorage and database)
    await savePreferences(preferences);
    
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
    // Remove any existing toasts first
    document.querySelectorAll('.toast').forEach(t => t.parentNode && t.parentNode.removeChild(t));

    const toast = document.createElement('div');
    toast.className = `toast ${type === 'error' ? 'error' : ''}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
      toast.classList.add('show');
    }, 100);
    
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

  // Job Posting Functionality
  const jobPostingForm = document.getElementById('jobPostingForm');
  const jobSkillsSearch = document.getElementById('jobSkillsSearch');
  const jobSkillsTabs = document.querySelectorAll('#jobPostingContent .skills-tab');
  const jobSkillsContainer = document.getElementById('jobSkillsContainer');
  const jobCategorySections = document.querySelectorAll('#jobSkillsContainer .category-section');

  // Skills search functionality for job posting
  if (jobSkillsSearch) {
    jobSkillsSearch.addEventListener('input', function(e) {
      const searchTerm = e.target.value.toLowerCase();
      const skillCheckboxes = jobSkillsContainer.querySelectorAll('.skill-checkbox');
      
      skillCheckboxes.forEach(checkbox => {
        const skillName = checkbox.getAttribute('data-skill-name');
        const shouldShow = skillName.includes(searchTerm);
        checkbox.style.display = shouldShow ? 'flex' : 'none';
      });
      
      // Show/hide category sections based on visible skills
      jobCategorySections.forEach(section => {
        const visibleSkills = section.querySelectorAll('.skill-checkbox[style*="flex"], .skill-checkbox:not([style])');
        section.style.display = visibleSkills.length > 0 ? 'block' : 'none';
      });
    });
  }

  // Tab functionality for job posting skills
  jobSkillsTabs.forEach(tab => {
    tab.addEventListener('click', function() {
      // Remove active class from all tabs
      jobSkillsTabs.forEach(t => t.classList.remove('active'));
      // Add active class to clicked tab
      this.classList.add('active');
      
      const selectedCategory = this.getAttribute('data-category');
      
      // Show/hide categories based on selected tab
      jobCategorySections.forEach(section => {
        const sectionCategory = section.getAttribute('data-category');
        if (selectedCategory === 'all' || sectionCategory === selectedCategory) {
          section.style.display = 'block';
        } else {
          section.style.display = 'none';
        }
      });
      
      // Clear search when switching tabs
      if (jobSkillsSearch) {
        jobSkillsSearch.value = '';
        // Reset all skill visibility
        const skillCheckboxes = jobSkillsContainer.querySelectorAll('.skill-checkbox');
        skillCheckboxes.forEach(checkbox => {
          checkbox.style.display = 'flex';
        });
      }
    });
  });

  // Handle job posting form submission
  if (jobPostingForm) {
    jobPostingForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const submitButton = document.getElementById('submitJobPost');
      submitButton.disabled = true;
      submitButton.textContent = 'Posting...';
      
      try {
        const formData = new FormData(jobPostingForm);
        // Explicitly collect checked skills (handles hidden tab sections)
        document.querySelectorAll('#jobSkillsContainer input[type="checkbox"]:checked').forEach(cb => {
          formData.append('skill_ids[]', cb.value);
        });
        
        const response = await fetch('/QuickHire/Public/actions/post_job.php', {
          method: 'POST',
          body: formData
        });
        
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (result.ok) {
          showToast('Job posted successfully!', 'success');
          jobPostingForm.reset();
          
          // Clear all skill checkboxes
          document.querySelectorAll('#jobSkillsContainer input[type="checkbox"]').forEach(cb => cb.checked = false);
          
          // Reload job posts
          await loadMyJobPosts();
        } else {
          showToast('Error: ' + (result.error || 'Unknown error'), 'error');
        }
      } catch (error) {
        console.error('Job posting error:', error);
        showToast('Error posting job: ' + error.message, 'error');
      } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Post Job';
      }
    });
  }

  // Load employer's job posts
  async function loadMyJobPosts() {
    const container = document.getElementById('myJobPosts');
    
    try {
      container.innerHTML = '<div class="loading">Loading your job posts...</div>';
      
      const response = await fetch('/QuickHire/Public/actions/get_job_posts.php');
      const result = await response.json();
      
      if (result.ok) {
        displayMyJobPosts(result.job_posts);
      } else {
        container.innerHTML = '<div class="empty-state">Error loading job posts</div>';
      }
    } catch (error) {
      console.error('Error loading job posts:', error);
      container.innerHTML = '<div class="empty-state">Error loading job posts</div>';
    }
  }

  // Display employer's job posts
  function displayMyJobPosts(jobPosts) {
    const container = document.getElementById('myJobPosts');
    currentJobPosts = jobPosts; // Store for editing
    
    if (jobPosts.length === 0) {
      container.innerHTML = '<div class="empty-state">No job posts yet. Create your first job posting above!</div>';
      return;
    }
    
    let html = '';
    jobPosts.forEach(job => {
      const skillsHtml = job.skills.length > 0 
        ? job.skills.map(skill => `<span class="skill-tag">${skill.name}</span>`).join('')
        : '<span class="no-skills">No skills specified</span>';
      
      const statusClass = job.is_active ? 'active' : 'inactive';
      const statusText = job.is_active ? 'Active' : 'Inactive';
      
      const rateDisplay = job.rate_per_hour ? `$${parseFloat(job.rate_per_hour).toFixed(2)}/hr` : null;
      const hoursDisplay = job.hours_per_week ? `${job.hours_per_week} hrs/week` : null;
      
      html += `
        <div class="job-post-item ${statusClass}">
          <div class="job-post-header">
            <div class="job-post-title">${job.title}</div>
            <div class="job-post-status status-${statusClass}">${statusText}</div>
          </div>
          <div class="job-post-meta">
            <span>📅 ${new Date(job.created_at).toLocaleDateString()}</span>
            ${job.role_title ? `<span>💼 ${job.role_title}</span>` : ''}
            ${job.employment_type ? `<span>⏰ ${job.employment_type.replace('_', ' ')}</span>` : ''}
            ${job.country ? `<span>🌍 ${job.country}</span>` : ''}
            ${rateDisplay ? `<span>💰 ${rateDisplay}</span>` : ''}${hoursDisplay ? `<span>🕐 ${hoursDisplay}</span>` : ''}
          </div>
          <div class="job-post-description">
            ${job.description.length > 150 ? job.description.substring(0, 150) + '...' : job.description}
          </div>
          <div class="job-post-skills">
            ${skillsHtml}
          </div>
          <div class="job-post-actions">
            <button class="btn-small outline" onclick="editJob(${job.id})">✏️ Edit</button>
            <button class="btn-small" onclick="deleteJob(${job.id})" style="background:#ef4444;color:white;border-color:#ef4444;">🗑️ Delete</button>
          </div>
        </div>
      `;
    });
    
    container.innerHTML = html;
  }

  // Edit job post — opens modal
  function editJob(jobId) {
    openEditJobModal(jobId);
  }

  // Delete job post
  async function deleteJob(jobId) {
    if (!confirm('Are you sure you want to delete this job post? This action cannot be undone.')) {
      return;
    }
    
    try {
      const formData = new FormData();
      formData.append('job_id', jobId);
      formData.append('csrf_token', '<?= htmlspecialchars(\Rongie\QuickHire\Core\Csrf::token()) ?>');
      
      const response = await fetch('/QuickHire/Public/actions/delete_job.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.ok) {
        showToast('Job post deleted successfully', 'success');
        await loadMyJobPosts();
      } else {
        showToast('Error: ' + result.error, 'error');
      }
    } catch (error) {
      console.error('Error deleting job:', error);
      showToast('Error deleting job post', 'error');
    }
  }

  let currentJobPosts = [];

  // Job posting form submission
  document.getElementById('jobPostingForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitJobPost');
    const isEdit = submitBtn.hasAttribute('data-edit-id');
    const editId = submitBtn.getAttribute('data-edit-id');
    
    submitBtn.disabled = true;
    submitBtn.textContent = isEdit ? '💾 Updating...' : '📢 Posting...';
    
    try {
      const formData = new FormData(this);
      
      // Collect checked skills
      const skillCheckboxes = document.querySelectorAll('input[name="skill_ids[]"]:checked');
      skillCheckboxes.forEach(checkbox => {
        formData.append('skill_ids[]', checkbox.value);
      });
      
      if (isEdit) {
        formData.append('job_id', editId);
      }
      
      const endpoint = isEdit ? '/QuickHire/Public/actions/update_job.php' : '/QuickHire/Public/actions/post_job.php';
      const response = await fetch(endpoint, {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.ok) {
        showToast(result.message, 'success');
        
        // Reset form
        document.getElementById('jobPostingForm').reset();
        submitBtn.textContent = 'Post Job';
        submitBtn.removeAttribute('data-edit-id');
        
        // Reload job posts
        await loadMyJobPosts();
        
        // Hide job posting form
        showHomeContent();
      } else {
        showToast('Error: ' + result.error, 'error');
      }
    } catch (error) {
      console.error('Job posting error:', error);
      showToast('Error posting job', 'error');
    } finally {
      submitBtn.disabled = false;
      if (!submitBtn.hasAttribute('data-edit-id')) {
        submitBtn.textContent = 'Post Job';
      }
    }
  });

  // Cancel job posting
  document.getElementById('btnCancelJobPost').addEventListener('click', function() {
    // Reset form
    document.getElementById('jobPostingForm').reset();
    document.getElementById('submitJobPost').textContent = 'Post Job';
    document.getElementById('submitJobPost').removeAttribute('data-edit-id');
    
    // Show home content
    showHomeContent();
  });

  // Create sample job for testing
  document.getElementById('createSampleJob').addEventListener('click', async function() {
    const button = this;
    button.disabled = true;
    button.textContent = 'Creating...';
    
    try {
      const formData = new FormData();
      formData.append('csrf_token', '<?= htmlspecialchars(\Rongie\QuickHire\Core\Csrf::token()) ?>');
      
      const response = await fetch('/QuickHire/Public/actions/create_sample_job.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.ok) {
        showToast('Sample job created successfully!', 'success');
        await loadMyJobPosts();
      } else {
        showToast('Error: ' + result.error, 'error');
      }
    } catch (error) {
      console.error('Error creating sample job:', error);
      showToast('Error creating sample job', 'error');
    } finally {
      button.disabled = false;
      button.textContent = 'Create Sample Job (for testing)';
    }
  });
</script>

<script>
// Search functionality
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const searchResults = document.getElementById('searchResults');
const searchResultsList = document.getElementById('searchResultsList');
const searchResultsCount = document.getElementById('searchResultsCount');
const searchEmpty = document.getElementById('searchEmpty');

let searchTimeout;

// Search on button click
searchButton.addEventListener('click', performSearch);

// Search on Enter key
searchInput.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    performSearch();
  }
});

// Search with debounce on input
searchInput.addEventListener('input', function() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    if (searchInput.value.trim().length >= 2) {
      performSearch();
    } else {
      hideSearchResults();
    }
  }, 500);
});

async function performSearch() {
  const query = searchInput.value.trim();
  
  if (!query) {
    hideSearchResults();
    return;
  }

  if (query.length < 2) {
    showToast('Please enter at least 2 characters to search', 'error');
    return;
  }

  searchButton.disabled = true;
  searchButton.textContent = 'Searching...';

  try {
    const response = await fetch(`/QuickHire/Public/actions/search_jobseekers.php?q=${encodeURIComponent(query)}`);
    const data = await response.json();

    if (data.ok) {
      displaySearchResults(data.results, query);
    } else {
      showToast('Search failed: ' + data.error, 'error');
      hideSearchResults();
    }
  } catch (error) {
    console.error('Search error:', error);
    showToast('Search failed. Please try again.', 'error');
    hideSearchResults();
  } finally {
    searchButton.disabled = false;
    searchButton.textContent = 'Search';
  }
}

function displaySearchResults(results, query) {
  // Store results for profile view
  window._searchResults = {};
  results.forEach(j => { window._searchResults[j.id] = j; });
  if (results.length === 0) {
    searchResults.style.display = 'none';
    searchEmpty.style.display = 'block';
    return;
  }

  searchEmpty.style.display = 'none';
  searchResults.style.display = 'block';
  
  searchResultsCount.textContent = `${results.length} result${results.length !== 1 ? 's' : ''} found for "${query}"`;
  
  let html = '';
  results.forEach(jobseeker => {
    const avatar = jobseeker.profile_picture_url 
      ? `<img src="/QuickHire/Public/${jobseeker.profile_picture_url}" alt="Avatar">`
      : jobseeker.first_name.charAt(0).toUpperCase();
    
    const skills = jobseeker.skills ? jobseeker.skills.split(', ').slice(0, 5).join(', ') : 'No skills listed';
    const moreSkills = jobseeker.skills && jobseeker.skills.split(', ').length > 5 
      ? ` +${jobseeker.skills.split(', ').length - 5} more` : '';

    html += `
      <div class="search-result-item" onclick="viewJobseekerProfile(this)" style="cursor:pointer;" data-id="${jobseeker.id}">
        <div class="search-result-avatar">
          ${avatar}
        </div>
        <div class="search-result-info">
          <div class="search-result-name">
            ${jobseeker.first_name} ${jobseeker.last_name}
          </div>
          <div class="search-result-role">
            ${jobseeker.role_title || 'Job Seeker'}
          </div>
          <div class="search-result-details">
            <div class="search-result-detail">
              💰 $${jobseeker.rate_per_hour || '0'}/hr
            </div>
            <div class="search-result-detail">
              🌍 ${jobseeker.country || 'Not specified'}
            </div>
            <div class="search-result-detail">
              ⏰ ${jobseeker.available_time || 'N/A'}h/day
            </div>
            <div class="search-result-detail">
              🗣️ ${jobseeker.english_mastery || 'Not specified'}
            </div>
          </div>
          <div class="search-result-skills">
            <strong>Skills:</strong> ${skills}${moreSkills}
          </div>
        </div>
        <div class="search-result-actions" onclick="event.stopPropagation()">
          <button class="message-button" onclick="startConversationWithJobseeker(${jobseeker.id}, this)">
            💬 Message
          </button>
        </div>
      </div>
    `;
  });
  
  searchResultsList.innerHTML = html;
}

function hideSearchResults() {
  searchResults.style.display = 'none';
  searchEmpty.style.display = 'none';
}

// View jobseeker profile
function viewJobseekerProfile(el) {
  const id = parseInt(el.dataset.id);
  const js = window._searchResults && window._searchResults[id];
  if (!js) return;

  // Populate panel
  const avatarEl = document.getElementById('jsProfileAvatar');
  if (js.profile_picture_url) {
    avatarEl.innerHTML = `<img src="/QuickHire/Public/${js.profile_picture_url}" style="width:100%;height:100%;object-fit:cover;">`;
  } else {
    avatarEl.textContent = (js.first_name || 'J').charAt(0).toUpperCase();
  }

  document.getElementById('jsProfileName').textContent = `${js.first_name} ${js.last_name}`;
  document.getElementById('jsProfileRole').textContent = js.role_title || 'Job Seeker';

  let meta = js.country || '';
  if (js.portfolio_url) meta += (meta ? ' · ' : '') + `<a href="${js.portfolio_url}" target="_blank" style="color:#6366f1;text-decoration:none;">${js.portfolio_url}</a>`;
  document.getElementById('jsProfileMeta').innerHTML = meta;

  // Pills
  const pills = [
    js.rate_per_hour ? `<span style="padding:8px 16px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.25);border-radius:20px;color:#a5b4fc;font-size:13px;font-weight:600;">💵 $${js.rate_per_hour}/hr</span>` : '',
    js.available_time ? `<span style="padding:8px 16px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);border-radius:20px;color:#34d399;font-size:13px;font-weight:600;">🕐 ${js.available_time}h/day</span>` : '',
    js.english_mastery ? `<span style="padding:8px 16px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);border-radius:20px;color:#fbbf24;font-size:13px;font-weight:600;">💬 ${js.english_mastery}</span>` : '',
    js.employment_type ? `<span style="padding:8px 16px;background:rgba(139,92,246,0.1);border:1px solid rgba(139,92,246,0.25);border-radius:20px;color:#c084fc;font-size:13px;font-weight:600;">💼 ${js.employment_type.replace('_','-')}</span>` : '',
    js.age ? `<span style="padding:8px 16px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:20px;color:#94a3b8;font-size:13px;font-weight:600;">🎂 ${js.age} yrs</span>` : '',
  ].filter(Boolean).join('');
  document.getElementById('jsProfilePills').innerHTML = pills;

  // About
  document.getElementById('jsProfileAbout').innerHTML = (js.profile_description || 'No description.').replace(/\n/g, '<br>');

  // Skills
  const skillsArr = js.skills ? js.skills.split(', ').filter(Boolean) : [];
  document.getElementById('jsProfileSkills').innerHTML = skillsArr.length
    ? skillsArr.map(s => `<span style="padding:5px 12px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.3);border-radius:20px;color:#a5b4fc;font-size:12px;font-weight:600;">${s}</span>`).join('')
    : '<span style="color:#64748b;font-size:13px;">No skills listed.</span>';

  // Details
  let details = '';
  if (js.bachelors_degree) details += `<div style="display:flex;gap:10px;align-items:center;"><span style="font-size:18px;">🎓</span><div><div style="font-size:12px;color:#64748b;">Education</div><div style="font-size:14px;font-weight:600;color:#e2e8f0;">${js.bachelors_degree}</div></div></div>`;
  if (js.gender) details += `<div style="display:flex;gap:10px;align-items:center;"><span style="font-size:18px;">👤</span><div><div style="font-size:12px;color:#64748b;">Gender</div><div style="font-size:14px;font-weight:600;color:#e2e8f0;">${js.gender.charAt(0)+js.gender.slice(1).toLowerCase()}</div></div></div>`;
  if (js.resume_url) details += `<div style="display:flex;gap:10px;align-items:center;"><span style="font-size:18px;">📄</span><div><div style="font-size:12px;color:#64748b;">Resume</div><a href="/QuickHire/Public/${js.resume_url}" target="_blank" style="font-size:14px;font-weight:600;color:#6366f1;text-decoration:none;">View Resume</a></div></div>`;
  document.getElementById('jsProfileDetails').innerHTML = details || '<span style="color:#64748b;font-size:13px;">No details available.</span>';

  // Message button
  document.getElementById('jsProfileMsgBtn').onclick = () => startConversationWithJobseeker(js.id, document.getElementById('jsProfileMsgBtn'));

  // Show panel
  showJobseekerProfileView();
}

function showJobseekerProfileView() {
  document.getElementById('dashboardContent').style.display = 'none';
  document.getElementById('searchContent').style.display = 'none';
  document.getElementById('jobPostingContent').style.display = 'none';
  document.getElementById('profileEditContent').style.display = 'none';
  document.getElementById('jsProfileView').style.display = 'block';

  btnHome.classList.remove('active');
  btnSearchJobseekers.classList.add('active');
  btnPostJob.classList.remove('active');
  btnEditProfile.classList.remove('active');
  btnEditProfile2.classList.remove('active');
  btnMessages.classList.remove('active');

  document.querySelector('.title').textContent = 'Jobseeker Profile';
  document.querySelector('.subtitle').textContent = 'Viewing candidate profile.';
}

async function startConversationWithJobseeker(jobseekerId, buttonElement) {
  // Use the passed button element
  const button = buttonElement;
  
  if (!button) {
    console.error('Button element not found');
    showToast('Failed to start conversation: Button not found', 'error');
    return;
  }
  
  button.disabled = true;
  button.textContent = 'Starting...';

  try {
    console.log('Starting conversation with jobseeker:', jobseekerId);
    
    const formData = new FormData();
    formData.append('jobseeker_id', jobseekerId);

    console.log('Sending request to:', '/QuickHire/Public/actions/start_conversation.php');
    
    const response = await fetch('/QuickHire/Public/actions/start_conversation.php', {
      method: 'POST',
      body: formData
    });

    console.log('Response received:', response);
    console.log('Response status:', response.status);
    console.log('Response ok:', response.ok);

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const contentType = response.headers.get('content-type');
    console.log('Content type:', contentType);

    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('Non-JSON response:', text);
      throw new Error('Server returned non-JSON response');
    }

    const data = await response.json();
    console.log('Response data:', data);

    if (data.ok) {
      const actionText = data.is_existing ? 'Opened existing conversation' : 'Started new conversation';
      console.log(`${actionText} with ${data.jobseeker_name}, ID: ${data.conversation_id}`);
      
      // Open messaging panel first
      messagingPanel.classList.add('open');
      
      // Load conversations to get the latest list
      await loadConversations();
      
      // Give a small delay to ensure conversations are loaded
      await new Promise(resolve => setTimeout(resolve, 100));
      
      // Find the conversation by ID
      const conversation = conversations.find(c => c.id == data.conversation_id);
      
      if (conversation) {
        console.log('Found conversation, opening:', conversation);
        await openConversation(conversation.id);
        showToast(`${actionText} with ${data.jobseeker_name}`, 'success');
      } else {
        console.log('Conversation not found in list. Available conversations:', conversations);
        console.log('Looking for conversation ID:', data.conversation_id);
        
        // Try one more time with a longer delay
        await new Promise(resolve => setTimeout(resolve, 500));
        await loadConversations();
        
        const retryConversation = conversations.find(c => c.id == data.conversation_id);
        if (retryConversation) {
          await openConversation(retryConversation.id);
          showToast(`${actionText} with ${data.jobseeker_name}`, 'success');
        } else {
          // If still not found, just show the messaging panel
          console.log('Could not find conversation in list, but messaging panel is open');
          showToast(`Conversation with ${data.jobseeker_name} is ready. Please check your messages.`, 'success');
        }
      }
    } else {
      console.error('Server returned error:', data.error);
      showToast('Failed to start conversation: ' + data.error, 'error');
    }
  } catch (error) {
    console.error('Start conversation error:', error);
    console.error('Error details:', {
      name: error.name,
      message: error.message,
      stack: error.stack
    });
    
    if (error.name === 'TypeError' && error.message.includes('fetch')) {
      showToast('Failed to start conversation: Network connection error', 'error');
    } else if (error.message.includes('HTTP error')) {
      showToast('Failed to start conversation: Server error (' + error.message + ')', 'error');
    } else {
      showToast('Failed to start conversation: ' + error.message, 'error');
    }
  } finally {
    button.disabled = false;
    button.textContent = '💬 Message';
  }
}
</script>

<script>
  // Name editing functionality
  function editName() {
    document.getElementById('nameDisplay').style.display = 'none';
    document.getElementById('nameEdit').style.display = 'block';
    document.getElementById('firstNameInput').focus();
  }

  function cancelEditName() {
    document.getElementById('nameDisplay').style.display = 'block';
    document.getElementById('nameEdit').style.display = 'none';
  }

  async function saveName() {
    const firstName = document.getElementById('firstNameInput').value.trim();
    const lastName = document.getElementById('lastNameInput').value.trim();

    if (!firstName || !lastName) {
      showToast('Please enter both first and last name', 'error');
      return;
    }

    try {
      const formData = new FormData();
      formData.append('first_name', firstName);
      formData.append('last_name', lastName);
      formData.append('csrf_token', '<?= htmlspecialchars(\Rongie\QuickHire\Core\Csrf::token()) ?>');

      const response = await fetch('/QuickHire/Public/actions/update_name.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.ok) {
        // Update the display
        document.getElementById('nameDisplay').innerHTML = `${firstName} ${lastName} <span style="margin-left:6px;opacity:0.5;"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>`;
        cancelEditName();
        showToast('Name updated successfully', 'success');
        
        // Update sidebar name
        const sidebarName = document.querySelector('.profileCard .name');
        if (sidebarName) {
          sidebarName.textContent = `${firstName} ${lastName}`;
        }
      } else {
        showToast(result.error || 'Failed to update name', 'error');
      }
    } catch (error) {
      console.error('Error updating name:', error);
      showToast('Connection error. Please try again.', 'error');
    }
  }
</script>

<script>
// Messaging Panel Functionality
const messagingPanel = document.getElementById('messagingPanel');
const btnMessages = document.getElementById('btnMessages');
const conversationsList = document.getElementById('conversationsList');
const chatArea = document.getElementById('chatArea');
const backToConversations = document.getElementById('backToConversations');
const messagesContainer = document.getElementById('messagesContainer');
const messageForm = document.getElementById('messageForm');
const messageInput = document.getElementById('messageInput');

let currentConversationId = null;
let conversations = [];

// Open messaging panel
btnMessages.addEventListener('click', (e) => {
  e.preventDefault();
  e.stopPropagation();
  localStorage.setItem('emp_active_page', 'home'); // don't restore messages on reload
  messagingPanel.classList.add('open');

  // Reset chat header to default state when opening fresh
  currentConversationId = null;
  document.getElementById('chatTitle').textContent = 'Select a conversation';
  const menuBtnReset = document.getElementById('chatMenuBtn');
  if (menuBtnReset) menuBtnReset.style.display = 'none';
  const avatarEl = document.getElementById('chatHeaderAvatar');
  if (avatarEl) { avatarEl.style.display = 'none'; avatarEl.innerHTML = ''; }
  document.getElementById('messagesContainer').innerHTML = `
    <div class="empty-state">
      <h3>Select a conversation</h3>
      <p>Choose a conversation from the sidebar to start messaging</p>
    </div>`;
  document.getElementById('messageInputArea').style.display = 'none';

  loadConversations();

  // Update active states
  btnHome.classList.remove('active');
  btnEditProfile.classList.remove('active');
  btnEditProfile2.classList.remove('active');
  btnSearchJobseekers.classList.remove('active');
  btnPostJob.classList.remove('active');
  btnMessages.classList.add('active');
});

// Close messaging panel (via sidebar nav buttons)
function closeMessagingPanel() {
  messagingPanel.classList.remove('open');
  currentConversationId = null;
  document.getElementById('messageInputArea').style.display = 'none';
  const menuBtn = document.getElementById('chatMenuBtn');
  if (menuBtn) menuBtn.style.display = 'none';
}

// Back to conversations
backToConversations.addEventListener('click', () => {
  // On mobile, hide chat area and show conversations
  if (window.innerWidth <= 768) {
    chatArea.style.display = 'none';
    document.querySelector('.conversations-sidebar').style.display = 'block';
  }
  currentConversationId = null;
  document.getElementById('messageInputArea').style.display = 'none';
});

// Load conversations
async function loadConversations() {
  try {
    conversationsList.innerHTML = '<div class="loading">Loading conversations...</div>';

    const response = await fetch('/QuickHire/Public/actions/get_conversations.php');
    const data = await response.json();

    if (data.ok) {
      conversations = data.conversations;
      buildJobFilter();
      displayConversations();
    } else {
      conversationsList.innerHTML = '<div class="empty-state">No conversations yet</div>';
    }
  } catch (error) {
    console.error('Load conversations error:', error);
    conversationsList.innerHTML = '<div class="empty-state">Error loading conversations: ' + error.message + '</div>';
  }
}

// Build job filter dropdown
function buildJobFilter() {
  const filterBar = document.getElementById('jobFilterBar');
  const select = document.getElementById('jobFilterSelect');
  const jobMap = {};
  conversations.forEach(c => {
    if (c.job_post_id && c.job_post_title) jobMap[c.job_post_id] = c.job_post_title;
  });
  const jobs = Object.entries(jobMap);
  console.log("Job filter debug:", { conversationsCount: conversations.length, jobMap, jobs });
  if (jobs.length === 0) { filterBar.style.display = 'none'; return; }
  filterBar.style.display = 'block';
  const current = select.value;
  select.innerHTML = '<option value="">All conversations</option>';
  jobs.forEach(([id, title]) => {
    select.innerHTML += `<option value="${id}" ${current == id ? 'selected' : ''}>${title}</option>`;
  });
}

let activeJobFilter = '';
document.addEventListener('change', function(e) {
  if (e.target.id === 'jobFilterSelect') {
    activeJobFilter = e.target.value;
    displayConversations();
  }
});

// Display conversations
function displayConversations() {
  const filtered = activeJobFilter
    ? conversations.filter(c => String(c.job_post_id) === String(activeJobFilter))
    : conversations;

  if (filtered.length === 0) {
    conversationsList.innerHTML = '<div class="empty-state">No conversations found</div>';
    return;
  }

  let html = '';
  filtered.forEach(conv => {
    // Active now = within 60 seconds, show green dot
    const isActive = conv.other_last_active && (new Date() - new Date(conv.other_last_active)) < 60000;
    const minutesAgo = conv.other_last_active ? Math.floor((new Date() - new Date(conv.other_last_active)) / 60000) : null;
    // Show badge only for 1-5 minutes ago
    const showBadge = minutesAgo !== null && minutesAgo >= 1 && minutesAgo <= 5;
    const activeIndicator = isActive ? `<span class="active-dot"></span>` : "";
    const minuteBadge = showBadge ? `<span class="minute-badge">${minutesAgo}</span>` : "";
    console.log("Active status:", conv.other_first_name, { last_active: conv.other_last_active, isActive, minutesAgo, showBadge });
    const jobTag = conv.job_post_title
      ? `<div class="conv-job-tag">&#128203; ${conv.job_post_title}</div>`
      : '';
    const avatarHtml = conv.other_avatar
      ? `<img src="/QuickHire/Public/${conv.other_avatar}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
      : conv.other_first_name.charAt(0).toUpperCase();
    html += `
      <div class="conversation-item" data-conversation-id="${conv.id}" onclick="openConversation(${conv.id})">
        <div class="conversation-avatar">
          ${avatarHtml}
          ${activeIndicator}
          ${minuteBadge}
        </div>
        <div class="conversation-info">
          <div class="conversation-name">${conv.other_first_name} ${conv.other_last_name}</div>
          ${jobTag}
          <div class="conversation-preview">${conv.other_role || 'Jobseeker'}</div>
          ${conv.last_message ? `<div class="conversation-preview">${conv.last_message.substring(0,50)}${conv.last_message.length > 50 ? '...' : ''}</div>` : ''}
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
          ${conv.unread_count > 0 ? `<div class="unread-badge">${conv.unread_count}</div>` : ''}
        </div>
      </div>
    `;
  });

  conversationsList.innerHTML = html;
}
// Toggle chat options menu — positioned fixed relative to button to escape overflow:hidden
function toggleChatMenu() {
  const menu = document.getElementById('chatMenu');
  const btn  = document.getElementById('chatMenuBtn');
  if (!menu || !btn) return;

  if (menu.style.display !== 'none') {
    menu.style.display = 'none';
    return;
  }

  // Position relative to button using getBoundingClientRect
  const rect = btn.getBoundingClientRect();
  menu.style.top    = (rect.bottom + 6) + 'px';
  menu.style.right  = (window.innerWidth - rect.right) + 'px';
  menu.style.left   = 'auto';
  menu.style.display = 'block';
}
// Close menu when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('#chatMenuBtn') && !e.target.closest('#chatMenu')) {
    const menu = document.getElementById('chatMenu');
    if (menu) menu.style.display = 'none';
  }
});

// Delete conversation
async function deleteConversation(conversationId) {
  // Hide the menu first
  const menu = document.getElementById('chatMenu');
  if (menu) menu.style.display = 'none';

  if (!confirm('Delete this conversation? This cannot be undone.')) return;
  
  try {
    const fd = new FormData();
    fd.append('conversation_id', conversationId);
    
    const res = await fetch('/QuickHire/Public/actions/delete_conversation.php', { method: 'POST', body: fd });
    const data = await res.json();
    
    if (data.ok) {
      // Reset chat area
      currentConversationId = null;
      document.getElementById('chatTitle').textContent = 'Select a conversation';
      const menuBtnDel = document.getElementById('chatMenuBtn');
      if (menuBtnDel) menuBtnDel.style.display = 'none';
      const avatarEl = document.getElementById('chatHeaderAvatar');
      if (avatarEl) { avatarEl.style.display = 'none'; avatarEl.innerHTML = ''; }
      document.getElementById('messagesContainer').innerHTML = `
        <div class="empty-state">
          <h3>Select a conversation</h3>
          <p>Choose a conversation from the sidebar to start messaging</p>
        </div>`;
      document.getElementById('messageInputArea').style.display = 'none';
      await loadConversations();
      showToast('Conversation deleted.', 'success');
    } else {
      showToast('Failed to delete: ' + data.error, 'error');
    }
  } catch (error) {
    showToast('Error deleting conversation.', 'error');
  }
}

// Open conversation
async function openConversation(conversationId) {
  console.log('openConversation called with:', conversationId);
  conversationId = parseInt(conversationId);
  currentConversationId = conversationId;
  console.log('Parsed conversationId:', conversationId);
  console.log('Available conversations:', conversations);
  const conversation = conversations.find(c => parseInt(c.id) === conversationId);
  console.log('Found conversation:', conversation);
  
  if (!conversation) {
    console.error('Conversation not found!');
    return;
  }
  
  // Update active conversation - find the conversation item by conversation ID
  document.querySelectorAll('.conversation-item').forEach(item => {
    item.classList.remove('active');
  });
  
  // Try to find and highlight the active conversation item
  const conversationItems = document.querySelectorAll('.conversation-item');
  conversationItems.forEach(item => {
    const itemConversationId = item.getAttribute('data-conversation-id');
    if (parseInt(itemConversationId) === conversationId) {
      item.classList.add('active');
    }
  });
  
  // Update chat header
  // Update chat header with active status
  const isActive = conversation.other_last_active && (new Date() - new Date(conversation.other_last_active)) < 60000;
  let statusText = "";
  if (isActive) {
    statusText = `<span style="color:#10b981; font-size:13px; font-weight:normal;">● Active now</span>`;
  } else if (conversation.other_last_active) {
    const minutesAgo = Math.floor((new Date() - new Date(conversation.other_last_active)) / 60000);
    // Only show status for 1-5 minutes ago, nothing after 5 minutes
    if (minutesAgo >= 1 && minutesAgo <= 5) {
      statusText = `<span style="color:#64748b; font-size:13px; font-weight:normal;">Active ${minutesAgo} min ago</span>`;
    }
  }
  document.getElementById("chatTitle").innerHTML = `${conversation.other_first_name} ${conversation.other_last_name}<br>${statusText}`;
  // Show the ⋮ menu button now that a conversation is open
  const menuBtn = document.getElementById('chatMenuBtn');
  if (menuBtn) menuBtn.style.display = 'block';

  // Update chat header avatar
  const avatarEl = document.getElementById('chatHeaderAvatar');
  if (avatarEl) {
    avatarEl.style.display = 'flex';
    if (conversation.other_avatar) {
      avatarEl.innerHTML = `<img src="/QuickHire/Public/${conversation.other_avatar}" style="width:100%;height:100%;object-fit:cover;">`;
    } else {
      avatarEl.innerHTML = conversation.other_first_name.charAt(0).toUpperCase();
    }
  }
  
  const chatMenu = document.getElementById('chatMenu');
  if (chatMenu) {
    chatMenu.style.display = 'none';
  }

  // Show chat area
  chatArea.style.display = 'flex';

  // Set conversation ID in form
  const conversationIdInput = document.getElementById('conversationId');
  if (conversationIdInput) {
    conversationIdInput.value = conversationId;
  }
  
  // Show message input area
  const messageInputArea = document.getElementById('messageInputArea');
  if (messageInputArea) {
    messageInputArea.style.display = 'block';
  }
  
  // Load messages
  console.log('About to load messages for conversation:', conversationId);
  await loadMessages(conversationId);
  console.log('loadMessages completed');
}

// Load messages
async function loadMessages(conversationId) {
  console.log('loadMessages called with:', conversationId);
  try {
    messagesContainer.innerHTML = '<div class="loading">Loading messages...</div>';
    
    const url = `/QuickHire/Public/actions/get_messages.php?conversation_id=${conversationId}`;
    console.log('Fetching messages from:', url);
    const response = await fetch(url);
    console.log('Response status:', response.status);
    const data = await response.json();
    console.log('Response data:', data);
    
    if (data.ok) {
      console.log('Messages loaded successfully:', data.messages.length, 'messages');
      displayMessages(data.messages);
    } else {
      console.error('Server error:', data.error);
      messagesContainer.innerHTML = '<div class="empty-state">Error: ' + data.error + '</div>';
    }
  } catch (error) {
    console.error('loadMessages error:', error);
    messagesContainer.innerHTML = '<div class="empty-state">Error loading messages: ' + error.message + '</div>';
  }
}

// Display messages
function displayMessages(messages) {
  if (messages.length === 0) {
    messagesContainer.innerHTML = '<div class="empty-state">No messages yet</div>';
    return;
  }
  
  let html = '';
  const currentUserId = <?= Auth::userId() ?>;
  
  messages.forEach(msg => {
    const isOwn = msg.sender_id == currentUserId;
    
    // Handle file messages
    let messageContent = '';
    if (msg.message_type === 'file' && msg.file_url) {
      const fileName = msg.file_name || 'File';
      const fileSize = msg.file_size ? `(${(msg.file_size / 1024 / 1024).toFixed(2)}MB)` : '';
      messageContent = `
        <div class="file-message">
          <div class="file-icon">📎</div>
          <div class="file-info">
            <a href="${msg.file_url}" target="_blank" class="file-link">${fileName}</a>
            <div class="file-size">${fileSize}</div>
          </div>
        </div>
        ${msg.content && msg.content !== `Sent a file: ${fileName}` ? `<p class="message-text">${msg.content.replace(/\n/g, '<br>')}</p>` : ''}
      `;
    } else {
      messageContent = `<p class="message-text">${msg.content.replace(/\n/g, '<br>')}</p>`;
    }
    
    html += `
      <div class="message ${isOwn ? 'own' : ''}">
        <div class="message-avatar" style="overflow:hidden;flex-shrink:0;">
          ${msg.sender_avatar
            ? `<img src="/QuickHire/Public/${msg.sender_avatar}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
            : msg.first_name.charAt(0).toUpperCase()}
        </div>
        <div class="message-content">
          ${msg.room_code ? '<div class="call-indicator">📞 Video Call Message</div>' : ''}
          ${messageContent}
          <div class="message-time">
            ${new Date(msg.created_at).toLocaleDateString()} ${new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
          </div>
        </div>
      </div>
    `;
  });
  
  messagesContainer.innerHTML = html;
  messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Handle file selection
const fileInput = document.getElementById('fileInput');
const fileButton = document.querySelector('.file-button');
const filePreview = document.getElementById('filePreview');
const filePreviewName = document.getElementById('filePreviewName');
const filePreviewSize = document.getElementById('filePreviewSize');
const filePreviewRemove = document.getElementById('filePreviewRemove');

if (fileInput) {
  fileInput.addEventListener('change', function() {
    console.log('File input changed:', this.files);
    if (this.files.length > 0) {
      const file = this.files[0];
      const fileName = file.name;
      const fileSize = (file.size / 1024 / 1024).toFixed(2); // Size in MB
      
      console.log('File selected:', fileName, fileSize + 'MB');
      
      // Check file size (10MB limit)
      if (file.size > 10 * 1024 * 1024) {
        alert('File too large. Maximum size is 10MB.');
        this.value = '';
        return;
      }
      
      // Show file preview
      filePreviewName.textContent = fileName;
      filePreviewSize.textContent = `${fileSize} MB`;
      filePreview.style.display = 'block';
      
      // Update message input placeholder
      messageInput.placeholder = `File selected: ${fileName}. Type a message or press Send to upload...`;
    } else {
      hideFilePreview();
    }
  });
}

// Handle file preview removal
if (filePreviewRemove) {
  filePreviewRemove.addEventListener('click', function() {
    removeSelectedFile();
  });
}

function removeSelectedFile() {
  fileInput.value = '';
  hideFilePreview();
}

function hideFilePreview() {
  filePreview.style.display = 'none';
  messageInput.placeholder = 'Type your message...';
}

// Handle message form submission
messageForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(messageForm);
  const message = formData.get('message').trim();
  const file = formData.get('file');
  
  console.log('Form submission:', { message, file: file?.name, fileSize: file?.size });
  
  // Check if we have either a message or a file
  if (!message && (!file || !file.name)) {
    console.log('No message or file to send');
    return;
  }
  
  const sendButton = document.getElementById('sendButton');
  sendButton.disabled = true;
  sendButton.textContent = 'Sending...';
  
  try {
    console.log('Sending request to server...');
    const response = await fetch('/QuickHire/Public/actions/send_message.php', {
      method: 'POST',
      body: formData
    });
    
    console.log('Response status:', response.status);
    const result = await response.json();
    console.log('Response data:', result);
    
    if (result.ok) {
      messageInput.value = '';
      messageInput.placeholder = 'Type your message...';
      
      // Reset file input and preview
      fileInput.value = '';
      hideFilePreview();
      
      await loadMessages(currentConversationId);
      await loadConversations(); // Refresh conversations to update last message
    } else {
      console.error('Server error:', result.error);
      alert('Error: ' + result.error);
    }
  } catch (error) {
    console.error('Send message error:', error);
    alert('Error sending message');
  } finally {
    sendButton.disabled = false;
    sendButton.textContent = 'Send';
  }
});

// Auto-resize textarea
messageInput.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Send on Enter (but not Shift+Enter)
messageInput.addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    messageForm.dispatchEvent(new Event('submit'));
  }
});

// Close messaging panel when other navigation items are clicked
document.addEventListener('DOMContentLoaded', function() {
  // Get all navigation buttons except Messages
  const navButtons = document.querySelectorAll('.nav button:not(#btnMessages), .nav a:not(#btnMessages)');
  
  navButtons.forEach(button => {
    button.addEventListener('click', function() {
      // Close messaging panel if it's open
      if (messagingPanel && messagingPanel.classList.contains('open')) {
        messagingPanel.classList.remove('open');
        currentConversationId = null;
        if (document.getElementById('messageInputArea')) {
          document.getElementById('messageInputArea').style.display = 'none';
        }
      }
    });
  });
});

// Activity tracking - update on any click/interaction
let lastActivityUpdate = Date.now();
const updateActivity = () => {
  const now = Date.now();
  // Only send update if 5 seconds have passed since last update (throttle)
  if (now - lastActivityUpdate > 5000) {
    fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' });
    lastActivityUpdate = now;
  }
};

// Track clicks anywhere in the app
document.addEventListener('click', updateActivity);
document.addEventListener('keypress', updateActivity);
document.addEventListener('scroll', updateActivity);

// Fallback: update every 30 seconds if user is idle but page is open
setInterval(() => {
  fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' });
}, 30000);

// Refresh conversations every 10 seconds to update active status
setInterval(() => {
  if (messagingPanel.classList.contains('open')) {
    loadConversations();
  }
}, 10000);

// Initial activity update
fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' });
</script>

<!-- Floating chat menu — appended to body to escape overflow:hidden containers -->
<div id="chatMenu" style="display:none;position:fixed;background:#1e293b;border:1px solid rgba(255,255,255,0.12);border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.5);min-width:190px;z-index:99999;overflow:hidden;">
  <button onclick="deleteConversation(currentConversationId)" style="display:flex;align-items:center;gap:10px;width:100%;padding:13px 16px;background:none;border:none;cursor:pointer;color:#fca5a5;font-size:14px;font-weight:600;" onmouseover="this.style.background='rgba(239,68,68,0.12)'" onmouseout="this.style.background='none'">🗑 Delete Conversation</button>
</div>

<!-- ── Edit Job Modal ── -->
<div id="editJobModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);z-index:99999;overflow-y:auto;padding:40px 16px;">
  <div style="background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:32px;max-width:760px;width:100%;margin:0 auto;box-shadow:0 24px 60px rgba(0,0,0,0.5);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <h2 style="margin:0;font-size:20px;font-weight:900;color:#f8fafc;">Edit Job Post</h2>
      <button onclick="closeEditJobModal()" style="background:none;border:none;color:#94a3b8;font-size:22px;cursor:pointer;line-height:1;padding:4px;">✕</button>
    </div>

    <form id="editJobForm">
      <input type="hidden" id="edit_job_id">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Rongie\QuickHire\Core\Csrf::token()) ?>">

      <div style="display:grid;grid-template-columns:1fr;gap:16px;">

        <div>
          <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Job Title *</label>
          <input type="text" id="edit_job_title" required maxlength="255" placeholder="e.g., Senior Frontend Developer"
            style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:rgba(255,255,255,0.05);color:#f8fafc;font-family:inherit;font-size:14px;box-sizing:border-box;">
        </div>

        <div>
          <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Job Description *</label>
          <textarea id="edit_job_description" required maxlength="5000" rows="5" placeholder="Describe the role..."
            style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:rgba(255,255,255,0.05);color:#f8fafc;font-family:inherit;font-size:14px;resize:vertical;box-sizing:border-box;"></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div>
            <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Role Category</label>
            <select id="edit_job_role_title" style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:#1e293b;color:#f8fafc;font-family:inherit;font-size:14px;">
              <option value="">Select Role</option>
              <?php foreach (['Software Engineer','Software Developer','Web Developer','Mobile Developer','Full Stack Developer','Frontend Developer','Backend Developer','DevOps Engineer','Cloud Engineer','Data Scientist','Data Engineer','Data Analyst','Machine Learning Engineer','AI Engineer','Database Administrator','System Administrator','Network Engineer','Security Engineer','QA Engineer','QA Automation Engineer','UI/UX Designer','Product Designer','Technical Product Manager','IT Project Manager','Scrum Master','Business Intelligence Analyst','IT Support Specialist','Technical Writer'] as $r): ?>
                <option value="<?= $r ?>"><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
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

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
          <div>
            <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Country</label>
            <select id="edit_job_country" style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:#1e293b;color:#f8fafc;font-family:inherit;font-size:14px;">
              <option value="">Select Country</option>
              <?php foreach (['Afghanistan','Albania','Algeria','Argentina','Australia','Austria','Bangladesh','Belgium','Brazil','Canada','China','Colombia','Denmark','Egypt','Finland','France','Germany','Greece','India','Indonesia','Ireland','Italy','Japan','Malaysia','Mexico','Netherlands','New Zealand','Norway','Pakistan','Philippines','Poland','Portugal','Russia','Saudi Arabia','Singapore','South Africa','South Korea','Spain','Sweden','Switzerland','Thailand','Turkey','United Arab Emirates','United Kingdom','United States','Vietnam','Remote','Other'] as $c): ?>
                <option value="<?= $c ?>"><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Rate/hr (USD)</label>
            <input type="number" id="edit_job_rate" step="0.01" min="0" placeholder="e.g. 50.00"
              style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:rgba(255,255,255,0.05);color:#f8fafc;font-family:inherit;font-size:14px;box-sizing:border-box;">
          </div>
          <div>
            <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Hrs/week</label>
            <input type="number" id="edit_job_hours" min="1" max="168" placeholder="e.g. 40"
              style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:rgba(255,255,255,0.05);color:#f8fafc;font-family:inherit;font-size:14px;box-sizing:border-box;">
          </div>
        </div>

        <!-- Required Skills -->
        <div>
          <label style="display:block;font-weight:700;margin-bottom:6px;color:#e2e8f0;font-size:13px;">Required Skills <span style="color:#64748b;font-weight:400;">(optional)</span></label>
          <div class="skills-container">
            <input type="text" class="skills-search" placeholder="🔍 Search skills..." id="editJobSkillsSearch">
            <div class="skills-tabs">
              <div class="skills-tab active" data-category="all">All</div>
              <?php
                $editSkillsStmt = $db->pdo()->query("SELECT DISTINCT category FROM skills ORDER BY category ASC");
                foreach ($editSkillsStmt->fetchAll(PDO::FETCH_COLUMN) as $cat):
              ?>
                <div class="skills-tab" data-category="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></div>
              <?php endforeach; ?>
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

      <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
        <button type="button" onclick="closeEditJobModal()" style="padding:11px 22px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:12px;color:#e2e8f0;font-weight:700;font-size:14px;cursor:pointer;">Cancel</button>
        <button type="submit" id="editJobSubmitBtn" style="padding:11px 28px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:12px;color:white;font-weight:800;font-size:14px;cursor:pointer;box-shadow:0 0 20px rgba(99,102,241,0.3);">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditJobModal(jobId) {
  const jobData = currentJobPosts.find(j => j.id === jobId);
  if (!jobData) { showToast('Job not found', 'error'); return; }

  document.getElementById('edit_job_id').value = jobId;
  document.getElementById('edit_job_title').value = jobData.title || '';
  document.getElementById('edit_job_description').value = jobData.description || '';
  document.getElementById('edit_job_role_title').value = jobData.role_title || '';
  document.getElementById('edit_job_employment_type').value = jobData.employment_type || '';
  document.getElementById('edit_job_country').value = jobData.country || '';
  document.getElementById('edit_job_rate').value = jobData.rate_per_hour || '';
  document.getElementById('edit_job_hours').value = jobData.hours_per_week || '';

  // Pre-check existing skills
  const currentSkillIds = (jobData.skills || []).map(s => parseInt(s.id));
  document.querySelectorAll('.edit-job-skill').forEach(cb => {
    cb.checked = currentSkillIds.includes(parseInt(cb.value));
  });

  document.getElementById('editJobModal').style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeEditJobModal() {
  document.getElementById('editJobModal').style.display = 'none';
  document.body.style.overflow = '';
}

document.getElementById('editJobModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditJobModal();
});

// Skills search & tab filter for edit modal
(function() {
  const search = document.getElementById('editJobSkillsSearch');
  const tabs   = document.querySelectorAll('#editJobModal .skills-tab');
  const sects  = document.querySelectorAll('#editJobSkillsContainer .category-section');
  let activeCategory = 'all';

  function filterSkills() {
    const q = search ? search.value.toLowerCase() : '';
    sects.forEach(sect => {
      const catMatch = activeCategory === 'all' || sect.dataset.category === activeCategory;
      let anyVisible = false;
      sect.querySelectorAll('.skill-checkbox').forEach(cb => {
        const show = catMatch && (!q || (cb.dataset.skillName || '').includes(q));
        cb.style.display = show ? '' : 'none';
        if (show) anyVisible = true;
      });
      sect.style.display = anyVisible ? '' : 'none';
    });
  }

  if (search) search.addEventListener('input', filterSkills);
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeCategory = tab.dataset.category;
      if (search) search.value = '';
      filterSkills();
    });
  });
})();

document.getElementById('editJobForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('editJobSubmitBtn');
  btn.textContent = 'Saving...';
  btn.disabled = true;

  try {
    const fd = new FormData();
    fd.append('job_id', document.getElementById('edit_job_id').value);
    fd.append('csrf_token', '<?= htmlspecialchars(\Rongie\QuickHire\Core\Csrf::token()) ?>');
    fd.append('title', document.getElementById('edit_job_title').value);
    fd.append('description', document.getElementById('edit_job_description').value);
    fd.append('role_title', document.getElementById('edit_job_role_title').value);
    fd.append('employment_type', document.getElementById('edit_job_employment_type').value);
    fd.append('country', document.getElementById('edit_job_country').value);
    fd.append('rate_per_hour', document.getElementById('edit_job_rate').value);
    fd.append('hours_per_week', document.getElementById('edit_job_hours').value);

    // Collect checked skills
    document.querySelectorAll('.edit-job-skill:checked').forEach(cb => {
      fd.append('skill_ids[]', cb.value);
    });

    const res = await fetch('/QuickHire/Public/actions/update_job.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.ok) {
      closeEditJobModal();
      showToast('Job updated successfully!', 'success');
      await loadMyJobPosts();
    } else {
      showToast(data.error || 'Failed to update job.', 'error');
    }
  } catch (err) {
    showToast('Connection error.', 'error');
  } finally {
    btn.textContent = 'Save Changes';
    btn.disabled = false;
  }
});
</script>

</body>
</html>



