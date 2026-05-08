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

<script>
(function() {
  document.body.style.overflow = 'hidden';
  let currentStep = 1;

  function jsGoStep(n) {
    document.getElementById('js-step-' + currentStep).classList.remove('active');
    document.getElementById('js-step-' + n).classList.add('active');
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
  }
  window.jsGoStep = jsGoStep;

  window.jsNextStep = function(from) {
    const panel = document.getElementById('js-step-' + from);
    panel.querySelectorAll('.cp-invalid').forEach(el => el.classList.remove('cp-invalid'));
    panel.querySelector('.cp-validation-msg')?.remove();

    let valid = true;
    if (from === 1) {
      panel.querySelectorAll('[required]').forEach(el => {
        if (!el.value.trim()) { el.classList.add('cp-invalid'); valid = false; }
      });
    }
    if (from === 2) {
      const checked = document.querySelectorAll('#ovJsSkillsContainer input[type=checkbox]:checked');
      if (checked.length === 0) {
        document.getElementById('ovJsSkillsContainer').classList.add('cp-invalid-box');
        valid = false;
      } else {
        document.getElementById('ovJsSkillsContainer').classList.remove('cp-invalid-box');
      }
    }
    if (from === 3) {
      panel.querySelectorAll('[required]').forEach(el => {
        if (!el.value.trim()) { el.classList.add('cp-invalid'); valid = false; }
      });
    }
    if (!valid) {
      const msg = document.createElement('p');
      msg.className = 'cp-validation-msg';
      msg.textContent = from === 2 ? 'Please select at least one skill.' : 'Please fill in all required fields.';
      const grid = panel.querySelector('.cp-grid');
      if (grid) grid.after(msg);
      else panel.querySelector('.cp-nav').before(msg);
      return;
    }
    jsGoStep(from + 1);
  };

  function showWizardMessage(panel, message) {
    panel.querySelector('.cp-validation-msg')?.remove();
    const msg = document.createElement('p');
    msg.className = 'cp-validation-msg';
    msg.textContent = message;
    panel.querySelector('.cp-nav')?.before(msg);
  }

  function validateSelectedResume(file) {
    if (!file) return true;
    const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
    if (!isPdf) return 'Please upload a PDF file.';
    if (file.size > 5_000_000) return 'Resume must be 5MB or smaller.';
    return true;
  }

  // Validate the whole wizard on form submit so the button never fails silently.
  document.getElementById('jsProfileForm').addEventListener('submit', function(e) {
    for (const step of [1, 3]) {
      const panel = document.getElementById('js-step-' + step);
      let valid = true;
      panel.querySelectorAll('[required]').forEach(el => {
        if (!String(el.value || '').trim()) {
          el.classList.add('cp-invalid');
          valid = false;
        }
      });
      if (!valid) {
        e.preventDefault();
        jsGoStep(step);
        showWizardMessage(panel, 'Please fill in all required fields.');
        return;
      }
    }

    const checkedSkills = document.querySelectorAll('#ovJsSkillsContainer input[type=checkbox]:checked');
    if (checkedSkills.length === 0) {
      e.preventDefault();
      jsGoStep(2);
      const panel = document.getElementById('js-step-2');
      document.getElementById('ovJsSkillsContainer').classList.add('cp-invalid-box');
      showWizardMessage(panel, 'Please select at least one skill.');
      return;
    }

    const resumeInput = document.getElementById('ovJsResume');
    const newResumeBox = document.getElementById('ovJsNewResume');
    const hasExistingResume = <?= !empty($profile['resume_url']) ? 'true' : 'false' ?>;
    const hasNewFile = resumeInput && resumeInput.files && resumeInput.files.length > 0;
    const resumeCheck = validateSelectedResume(hasNewFile ? resumeInput.files[0] : null);

    if (resumeCheck !== true) {
      e.preventDefault();
      jsGoStep(4);
      const panel = document.getElementById('js-step-4');
      showWizardMessage(panel, resumeCheck);
      const dropZone = document.getElementById('ovJsDropZone');
      if (dropZone) dropZone.style.borderColor = '#ef4444';
      return;
    }

    if (!hasNewFile && !hasExistingResume) {
      e.preventDefault();
      jsGoStep(4);
      const panel = document.getElementById('js-step-4');
      showWizardMessage(panel, 'Please upload your resume (PDF).');
      const dropZone = document.getElementById('ovJsDropZone');
      if (dropZone) dropZone.style.borderColor = '#ef4444';
    }
  });

  // Resume preview + drag & drop
  const resumeInput      = document.getElementById('ovJsResume');
  const newResumeBox     = document.getElementById('ovJsNewResume');
  const resumeFileName   = document.getElementById('ovJsResumeFileName');
  const currentResumeBox = document.getElementById('ovJsCurrentResume');
  const dropZone         = document.getElementById('ovJsDropZone');

  function showResumeFile(file) {
    if (!file) return;
    const resumeCheck = validateSelectedResume(file);
    if (resumeCheck !== true) {
      const panel = document.getElementById('js-step-4');
      showWizardMessage(panel, resumeCheck);
      if (dropZone) dropZone.style.borderColor = '#ef4444';
      resumeInput.value = '';
      return;
    }
    if (resumeFileName)   resumeFileName.textContent = file.name;
    if (newResumeBox)     newResumeBox.style.display = 'flex';
    if (currentResumeBox) currentResumeBox.style.display = 'none';
    if (dropZone)         dropZone.style.borderColor = '#10b981';
    // Clear any validation error
    document.getElementById('js-step-4')?.querySelector('.cp-validation-msg')?.remove();
    if (dropZone) dropZone.style.borderColor = '#10b981';
  }

  window.clearResume = function() {
    resumeInput.value = '';
    if (newResumeBox)     newResumeBox.style.display = 'none';
    if (currentResumeBox) currentResumeBox.style.display = 'flex';
    if (dropZone)         dropZone.style.borderColor = 'rgba(99,102,241,0.4)';
  };

  window.handleResumeDrop = function(e) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (!file) return;
    const resumeCheck = validateSelectedResume(file);
    if (resumeCheck !== true) {
      showWizardMessage(document.getElementById('js-step-4'), resumeCheck);
      if (dropZone) dropZone.style.borderColor = '#ef4444';
      return;
    }
    // Assign to the file input via DataTransfer
    const dt = new DataTransfer();
    dt.items.add(file);
    resumeInput.files = dt.files;
    showResumeFile(file);
    if (dropZone) {
      dropZone.style.borderColor = 'rgba(99,102,241,0.4)';
      dropZone.style.background = 'rgba(99,102,241,0.04)';
    }
  };

  if (resumeInput) {
    resumeInput.addEventListener('change', () => {
      const file = resumeInput.files[0];
      if (file) {
        showResumeFile(file);
      } else {
        if (newResumeBox)     newResumeBox.style.display = 'none';
        if (currentResumeBox) currentResumeBox.style.display = 'flex';
      }
    });
  }

  // Skills search & tab filter
  const search = document.getElementById('ovJsSkillsSearch');
  const tabs   = document.querySelectorAll('#js-step-2 .skills-tab');
  const sects  = document.querySelectorAll('#ovJsSkillsContainer .category-section');
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
  const skillContainer = document.getElementById('ovJsSkillsContainer');
  const limitMsg = document.createElement('p');
  limitMsg.id = 'ovJsSkillLimitMsg';
  limitMsg.style.cssText = 'color:#fca5a5;font-size:13px;font-weight:600;margin:8px 0 0;display:none;';
  limitMsg.textContent = 'Maximum 10 skills allowed.';
  skillContainer.parentElement.appendChild(limitMsg);

  skillContainer.addEventListener('change', e => {
    if (!e.target.matches('input[type=checkbox]')) return;
    const checked = skillContainer.querySelectorAll('input[type=checkbox]:checked');
    if (checked.length > SKILL_LIMIT) {
      e.target.checked = false;
      limitMsg.style.display = 'block';
    } else {
      limitMsg.style.display = checked.length === SKILL_LIMIT ? 'block' : 'none';
      if (checked.length === SKILL_LIMIT) limitMsg.textContent = 'Maximum of 10 skills reached.';
    }
    // Disable unchecked boxes when at limit
    skillContainer.querySelectorAll('input[type=checkbox]:not(:checked)').forEach(cb => {
      cb.disabled = checked.length >= SKILL_LIMIT;
    });
  });
})();
</script>
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
          <?php foreach (['Afghanistan','Albania','Algeria','Argentina','Australia','Austria','Bangladesh','Belgium','Brazil','Canada','China','Colombia','Denmark','Egypt','Finland','France','Germany','Greece','India','Indonesia','Ireland','Italy','Japan','Malaysia','Mexico','Netherlands','New Zealand','Norway','Pakistan','Philippines','Poland','Portugal','Russia','Saudi Arabia','Singapore','South Africa','South Korea','Spain','Sweden','Switzerland','Thailand','Turkey','United Arab Emirates','United Kingdom','United States','Vietnam'] as $c): ?>
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
          <button class="btn danger" type="submit" style="justify-self:start;background:#dc2626;color:white;border-color:#dc2626;padding:8px 12px;font-size:13px;">Delete My Account</button>
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
        <p style="margin:0 0 20px;color:#64748b;font-size:14px;">
          <?= htmlspecialchars($profile['country'] ?? '') ?>
          <?php if (!empty($profile['portfolio_url'])): ?>
             <a href="<?= htmlspecialchars($profile['portfolio_url']) ?>" target="_blank" style="color:#6366f1;text-decoration:none;"><?= htmlspecialchars($profile['portfolio_url']) ?></a>
          <?php endif; ?>
        </p>

        <!-- Stats pills -->
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:28px;">
          <span style="padding:8px 16px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.25);border-radius:20px;color:#a5b4fc;font-size:13px;font-weight:600;">💰 $<?= htmlspecialchars($profile['rate_per_hour'] ?? '0') ?>/hr</span>
          <span style="padding:8px 16px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);border-radius:20px;color:#34d399;font-size:13px;font-weight:600;">⏰ <?= htmlspecialchars($profile['available_time'] ?? '') ?>h/day</span>
          <span style="padding:8px 16px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);border-radius:20px;color:#fbbf24;font-size:13px;font-weight:600;">🗣️ <?= htmlspecialchars($profile['english_mastery'] ?? '') ?></span>
          <span style="padding:8px 16px;background:rgba(139,92,246,0.1);border:1px solid rgba(139,92,246,0.25);border-radius:20px;color:#c084fc;font-size:13px;font-weight:600;">💼 <?= htmlspecialchars(str_replace('_', '-', $profile['employment_type'] ?? '')) ?></span>
          <?php if (!empty($profile['age'])): ?>
            <span style="padding:8px 16px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:20px;color:#94a3b8;font-size:13px;font-weight:600;">🎂 <?= htmlspecialchars($profile['age']) ?> yrs</span>
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

<script>
let avatarCameraStream = null;
let avatarCameraTargetInput = '';
let avatarCameraTargetPreview = '';
let avatarCameraImage = '';

async function openAvatarCamera(inputId, previewId) {
  avatarCameraTargetInput = inputId;
  avatarCameraTargetPreview = previewId;
  avatarCameraImage = '';
  const modal = document.getElementById('avatarCameraModal');
  const error = document.getElementById('avatarCameraError');
  const video = document.getElementById('avatarCameraVideo');
  const snapshot = document.getElementById('avatarCameraSnapshot');
  error.style.display = 'none';
  snapshot.style.display = 'none';
  video.style.display = 'block';
  document.getElementById('avatarCaptureBtn').style.display = '';
  document.getElementById('avatarRetakeBtn').style.display = 'none';
  document.getElementById('avatarUseBtn').style.display = 'none';
  modal.style.display = 'flex';
  try {
    avatarCameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
    video.srcObject = avatarCameraStream;
  } catch (err) {
    error.textContent = 'Camera access is required for profile photos. Please allow camera permission and try again.';
    error.style.display = 'block';
  }
}

function stopAvatarCamera() {
  if (avatarCameraStream) {
    avatarCameraStream.getTracks().forEach(track => track.stop());
    avatarCameraStream = null;
  }
}

function closeAvatarCamera() {
  stopAvatarCamera();
  document.getElementById('avatarCameraModal').style.display = 'none';
}

function captureAvatarPhoto() {
  const video = document.getElementById('avatarCameraVideo');
  if (!video.videoWidth || !video.videoHeight) return;
  const canvas = document.getElementById('avatarCameraCanvas');
  const size = Math.min(video.videoWidth, video.videoHeight);
  const sx = (video.videoWidth - size) / 2;
  const sy = (video.videoHeight - size) / 2;
  canvas.getContext('2d').drawImage(video, sx, sy, size, size, 0, 0, canvas.width, canvas.height);
  avatarCameraImage = canvas.toDataURL('image/jpeg', 0.9);
  document.getElementById('avatarCameraSnapshot').src = avatarCameraImage;
  document.getElementById('avatarCameraSnapshot').style.display = 'block';
  video.style.display = 'none';
  stopAvatarCamera();
  document.getElementById('avatarCaptureBtn').style.display = 'none';
  document.getElementById('avatarRetakeBtn').style.display = '';
  document.getElementById('avatarUseBtn').style.display = '';
}

function retakeAvatarPhoto() {
  openAvatarCamera(avatarCameraTargetInput, avatarCameraTargetPreview);
}

function useAvatarPhoto() {
  if (!avatarCameraImage) return;
  const input = document.getElementById(avatarCameraTargetInput);
  const preview = document.getElementById(avatarCameraTargetPreview);
  if (input) input.value = avatarCameraImage;
  if (preview) preview.innerHTML = `<img src="${avatarCameraImage}" style="width:100%;height:100%;object-fit:cover;">`;
  closeAvatarCamera();
}
</script>

</body>

<script>
  // Basic functionality
  const APP_BASE = <?= json_encode($publicBase) ?>;
  const assetUrl = (path) => `${APP_BASE}/${String(path || '').replace(/^\/+/, '')}`;
  const btnFindEmployer = document.getElementById('btnFindEmployer');
  const btnFindEmployer2 = document.getElementById('btnFindEmployer2');
  const btnHome = document.getElementById('btnHome');
  const btnEditProfile = document.getElementById('btnEditProfile');
  const btnEditProfile2 = document.getElementById('btnEditProfile2');
  const btnBrowseJobs = document.getElementById('btnBrowseJobs');
  const btnSettings = document.getElementById('btnSettings');
  const messagingPanel = document.getElementById('messagingPanel');
  
  const dashboardContent = document.getElementById('dashboardContent');
  const jobBrowsingContent = document.getElementById('jobBrowsingContent');
  const profileEditContent = document.getElementById('profileEditContent');
  const settingsContent = document.getElementById('settingsContent');
  const myProfileContent   = document.getElementById('myProfileContent');
  const btnCancelEdit = document.getElementById('btnCancelEdit');

  function setMessagesNavActive() {
    localStorage.setItem('js_active_page', 'home'); // don't restore messages on reload
    btnHome.classList.remove('active');
    btnBrowseJobs.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSettings.classList.remove('active');
    document.getElementById('btnMessages')?.classList.add('active');
  }

  // Job Browsing Functionality
  let currentJobOffset = 0;
  const jobsPerPage = 10;
  let allJobsLoaded = false;
  let totalJobsCount = 0;
  let currentPage = 1;
  let currentJobs = []; // Store current jobs for detail view

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char]));
  }

  function escapeJsString(value) {
    return String(value ?? '')
      .replace(/\\/g, '\\\\')
      .replace(/'/g, "\\'")
      .replace(/\r/g, '\\r')
      .replace(/\n/g, '\\n')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function getAppliedJobs(conversation) {
    if (conversation && Array.isArray(conversation.applied_jobs) && conversation.applied_jobs.length > 0) {
      return conversation.applied_jobs;
    }

    if (conversation && conversation.job_post_id && conversation.job_post_title) {
      return [{ id: conversation.job_post_id, title: conversation.job_post_title }];
    }

    return [];
  }

  function conversationJobText(conversation) {
    return getAppliedJobs(conversation)
      .map(job => job.title || '')
      .join(' ')
      .toLowerCase();
  }

  function renderAppliedJobLinks(conversation) {
    const jobs = getAppliedJobs(conversation);

    return jobs.map(job => {
      const jobId = Number.parseInt(job.id, 10);
      if (!Number.isFinite(jobId) || jobId <= 0) return '';

      const title = escapeHtml(job.title || `Job #${jobId}`);
      const href = `/QuickHire/Public/jobseeker-dashboard.php?job_id=${encodeURIComponent(jobId)}`;

      return `<a href="${href}" class="conversation-job-link" onclick="event.preventDefault(); event.stopPropagation(); window.showJobDetailById(${jobId});">${title}</a>`;
    }).filter(Boolean).join('<span class="conversation-job-separator">, </span>');
  }

  async function findEmployer() {
    btnFindEmployer.disabled = true;
    btnFindEmployer2.disabled = true;
    btnFindEmployer.textContent = '🔍 Searching...';
    btnFindEmployer2.textContent = 'Searching...';

    const resetButtons = () => {
      btnFindEmployer.disabled = false;
      btnFindEmployer2.disabled = false;
      btnFindEmployer.textContent = '🔍 Find Employer';
      btnFindEmployer2.textContent = 'Find Employer';
    };

    try {
      const response = await fetch('/QuickHire/Public/actions/find_employer.php');
      const data = await response.json();

      if (data.ok && data.room) {
        resetButtons();
        showCallConfirmation(data.room, 'employer');
      } else if (data.waiting) {
        resetButtons();
        showToast('No employers are currently looking. Please wait.', 'info');
      } else {
        resetButtons();
        showToast(data.error || 'Unable to find employers.', 'error');
      }
    } catch (error) {
      resetButtons();
      showToast('Connection error. Please try again.', 'error');
    }
  }

  function showCallConfirmation(room, type) {
    // Remove any existing confirmation
    document.getElementById('callConfirmModal')?.remove();

    const modal = document.createElement('div');
    modal.id = 'callConfirmModal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);z-index:99999;display:flex;align-items:center;justify-content:center;';
    modal.innerHTML = `
      <div style="background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:36px 40px;max-width:420px;width:90%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,0.5);">
        <div style="font-size:52px;margin-bottom:16px;">🤝</div>
        <h2 style="margin:0 0 10px;font-size:22px;font-weight:900;color:#f8fafc;">Ready to Connect!</h2>
        <p style="margin:0 0 28px;color:#94a3b8;font-size:15px;line-height:1.6;">An employer is ready to connect with you. Make sure your camera and microphone are ready before joining.</p>
        <div style="display:flex;gap:12px;justify-content:center;">
          <button onclick="document.getElementById('callConfirmModal').remove()" style="padding:12px 24px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:12px;color:#e2e8f0;font-weight:700;font-size:14px;cursor:pointer;">Cancel</button>
          <button onclick="window.location.href='/QuickHire/Public/call.php?room=${encodeURIComponent(room)}'" style="padding:12px 28px;background:linear-gradient(135deg,#10b981,#059669);border:none;border-radius:12px;color:white;font-weight:800;font-size:14px;cursor:pointer;box-shadow:0 0 20px rgba(16,185,129,0.3);">Join Call →</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  }

  // Clean up empty conversations - COMPLETELY DISABLED
  async function cleanupEmptyConversation() {
    // NEVER clean up conversations - keep all conversations permanently
    if (window.pendingConversation) {
      window.pendingConversation = null;
    }
  }

  function showDashboard() {
    localStorage.setItem('js_active_page', 'home');
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      if (window._hideMessagingMobile) window._hideMessagingMobile();
      currentConversationId = null;
      if (messagePollingInterval) { clearInterval(messagePollingInterval); messagePollingInterval = null; }
      if (conversationRefreshInterval) { clearInterval(conversationRefreshInterval); conversationRefreshInterval = null; }
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    document.getElementById('btnMessages')?.classList.remove('active');
    
    dashboardContent.style.display = 'grid';
    jobBrowsingContent.style.display = 'none';
    if (profileEditContent) profileEditContent.style.display = 'none';
    if (settingsContent) settingsContent.style.display = 'none';
    if (myProfileContent)   myProfileContent.style.display = 'none';
    
    btnHome.classList.add('active');
    btnBrowseJobs.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSettings.classList.remove('active');
    
    document.querySelector('.title').textContent = 'Welcome back 👋';
    document.querySelector('.subtitle').textContent = 'Click "Find Employer" to automatically connect with employers who are currently looking for candidates like you.';
  }

  function showJobBrowsing(loadJobs = true) {
    localStorage.setItem('js_active_page', 'browse');
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      if (window._hideMessagingMobile) window._hideMessagingMobile();
      currentConversationId = null;
      if (messagePollingInterval) { clearInterval(messagePollingInterval); messagePollingInterval = null; }
      if (conversationRefreshInterval) { clearInterval(conversationRefreshInterval); conversationRefreshInterval = null; }
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    document.getElementById('btnMessages')?.classList.remove('active');
    
    dashboardContent.style.display = 'none';
    jobBrowsingContent.style.display = 'block';
    if (profileEditContent) profileEditContent.style.display = 'none';
    if (settingsContent) settingsContent.style.display = 'none';
    if (myProfileContent)   myProfileContent.style.display = 'none';
    
    btnHome.classList.remove('active');
    btnBrowseJobs.classList.add('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSettings.classList.remove('active');
    
    document.querySelector('.title').textContent = 'Browse Jobs';
    document.querySelector('.subtitle').textContent = 'Discover job opportunities from employers looking for candidates like you.';
    
    if (loadJobs !== false) {
      localStorage.removeItem('js_job_detail_index');
      localStorage.removeItem('js_job_detail_id');
      const filterBar = document.getElementById('jobFilterBar');
      if (filterBar) filterBar.style.display = 'flex';
      loadJobListings();
    }
  }

  window.showMyProfile = function() {
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      if (window._hideMessagingMobile) window._hideMessagingMobile();
      currentConversationId = null;
      if (messagePollingInterval) { clearInterval(messagePollingInterval); messagePollingInterval = null; }
      if (conversationRefreshInterval) { clearInterval(conversationRefreshInterval); conversationRefreshInterval = null; }
    }
    document.getElementById('btnMessages')?.classList.remove('active');

    dashboardContent.style.display = 'none';
    jobBrowsingContent.style.display = 'none';
    if (profileEditContent) profileEditContent.style.display = 'none';
    if (settingsContent) settingsContent.style.display = 'none';
    if (myProfileContent)   myProfileContent.style.display = 'block';

    btnHome.classList.remove('active');
    btnBrowseJobs.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSettings.classList.remove('active');

    document.querySelector('.title').textContent = 'My Profile';
    document.querySelector('.subtitle').textContent = 'Your public profile as seen by employers.';
  };

  function showProfileEdit() {
    localStorage.setItem('js_active_page', 'edit');
    // Close messaging if open
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      if (window._hideMessagingMobile) window._hideMessagingMobile();
      currentConversationId = null;
      if (messagePollingInterval) { clearInterval(messagePollingInterval); messagePollingInterval = null; }
      if (conversationRefreshInterval) { clearInterval(conversationRefreshInterval); conversationRefreshInterval = null; }
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    document.getElementById('btnMessages')?.classList.remove('active');
    
    dashboardContent.style.display = 'none';
    jobBrowsingContent.style.display = 'none';
    if (profileEditContent) profileEditContent.style.display = 'block';
    if (settingsContent) settingsContent.style.display = 'none';
    if (myProfileContent)   myProfileContent.style.display = 'none';
    
    // Update active states
    btnHome.classList.remove('active');
    btnBrowseJobs.classList.remove('active');
    btnEditProfile.classList.add('active');
    btnEditProfile2.classList.add('active');
    btnSettings.classList.remove('active');
    
    document.querySelector('.title').textContent = 'Edit Profile';
    document.querySelector('.subtitle').textContent = 'Update your profile information to attract the right employers.';
  }

  function showSettings() {
    localStorage.setItem('js_active_page', 'settings');
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      if (window._hideMessagingMobile) window._hideMessagingMobile();
      currentConversationId = null;
      if (messagePollingInterval) { clearInterval(messagePollingInterval); messagePollingInterval = null; }
      if (conversationRefreshInterval) { clearInterval(conversationRefreshInterval); conversationRefreshInterval = null; }
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    document.getElementById('btnMessages')?.classList.remove('active');

    dashboardContent.style.display = 'none';
    jobBrowsingContent.style.display = 'none';
    if (profileEditContent) profileEditContent.style.display = 'none';
    if (settingsContent) settingsContent.style.display = 'block';
    if (myProfileContent) myProfileContent.style.display = 'none';

    btnHome.classList.remove('active');
    btnBrowseJobs.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSettings.classList.add('active');

    document.querySelector('.title').textContent = 'Settings';
    document.querySelector('.subtitle').textContent = 'Manage your account and security options.';
  }

  function confirmDeleteAccount(form) {
    const typed = form.querySelector('[name="confirm_delete"]').value.trim();
    if (typed !== 'DELETE') {
      showToast('Type DELETE to confirm account deletion.', 'error');
      return false;
    }

    return window.confirm('Permanently delete your account? This cannot be undone.');
  }

  // Initialize with Home active
  btnHome.classList.add('active');

  // Restore last active page on reload
  const savedPage = localStorage.getItem('js_active_page');
  if (savedPage === 'browse') showJobBrowsing();
  else if (savedPage === 'edit') showProfileEdit();
  else if (savedPage === 'settings') showSettings();
  else showDashboard(); // messages always resets to home on reload

  // Event listeners
  btnFindEmployer.addEventListener('click', findEmployer);
  btnFindEmployer2.addEventListener('click', findEmployer);
  btnHome.addEventListener('click', showDashboard);
  btnBrowseJobs.addEventListener('click', showJobBrowsing);
  btnSettings.addEventListener('click', showSettings);
  
  // Edit profile buttons
  btnEditProfile.addEventListener('click', function() {
    showProfileEdit();
  });
  
  btnEditProfile2.addEventListener('click', function() {
    showProfileEdit();
  });

  // Cancel edit button
  if (btnCancelEdit) {
    btnCancelEdit.addEventListener('click', function() {
      showDashboard();
    });
  }

  // Jobseeker name edit functions
  function editJsName() {
    document.getElementById('jsNameDisplay').style.display = 'none';
    document.getElementById('jsNameEdit').style.display = 'block';
    document.getElementById('jsFirstNameInput').focus();
  }
  function cancelJsEditName() {
    document.getElementById('jsNameDisplay').style.display = 'inline-flex';
    document.getElementById('jsNameEdit').style.display = 'none';
  }
  function saveJsName() {
    const first = document.getElementById('jsFirstNameInput').value.trim();
    const last  = document.getElementById('jsLastNameInput').value.trim();
    if (first || last) {
      document.getElementById('jsNameDisplay').innerHTML =
        `${first} ${last} <span style="opacity:0.5;"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>`;
    }
    cancelJsEditName();
  }

  // Edit profile resume drag & drop
  (function() {
    const input       = document.getElementById('editJsResume');
    const dropZone    = document.getElementById('editJsDropZone');
    const newBox      = document.getElementById('editJsNewResume');
    const fileName    = document.getElementById('editJsResumeFileName');
    const currentBox  = document.getElementById('editJsCurrentResume');

    function showFile(file) {
      if (!file) return;
      if (fileName)   fileName.textContent = file.name;
      if (newBox)     newBox.style.display = 'flex';
      if (currentBox) currentBox.style.display = 'none';
      if (dropZone)   dropZone.style.borderColor = '#10b981';
    }

    window.clearEditResume = function() {
      if (input) input.value = '';
      if (newBox)     newBox.style.display = 'none';
      if (currentBox) currentBox.style.display = 'flex';
      if (dropZone)   dropZone.style.borderColor = 'rgba(99,102,241,0.4)';
    };

    window.handleEditResumeDrop = function(e) {
      e.preventDefault();
      const file = e.dataTransfer.files[0];
      if (!file) return;
      if (file.type !== 'application/pdf') { showToast('Please upload a PDF file.', 'error'); return; }
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      showFile(file);
      if (dropZone) { dropZone.style.borderColor = 'rgba(99,102,241,0.4)'; dropZone.style.background = 'rgba(99,102,241,0.04)'; }
    };

    if (input) {
      input.addEventListener('change', () => {
        const file = input.files[0];
        if (file) showFile(file);
        else { if (newBox) newBox.style.display = 'none'; if (currentBox) currentBox.style.display = 'flex'; }
      });
    }
  })();

  // Skill limit: max 10 for edit profile skills
  (function() {
    const container = document.getElementById('skillsContainer');
    if (!container) return;
    const LIMIT = 10;
    const msg = document.createElement('p');
    msg.style.cssText = 'color:#fca5a5;font-size:13px;font-weight:600;margin:8px 0 0;display:none;';
    msg.textContent = 'Maximum of 10 skills reached.';
    container.parentElement.appendChild(msg);
    container.addEventListener('change', e => {
      if (!e.target.matches('input[type=checkbox]')) return;
      const checked = container.querySelectorAll('input[type=checkbox]:checked');
      if (checked.length > LIMIT) { e.target.checked = false; }
      const count = container.querySelectorAll('input[type=checkbox]:checked').length;
      msg.style.display = count >= LIMIT ? 'block' : 'none';
      container.querySelectorAll('input[type=checkbox]:not(:checked)').forEach(cb => {
        cb.disabled = count >= LIMIT;
      });
    });

    // Tab filter for edit profile skills
    const tabs = container.closest('.skills-container').querySelectorAll('.skills-tab');
    const sects = container.querySelectorAll('.category-section');
    const searchInput = document.getElementById('skillsSearch');

    tabs.forEach(tab => {
      tab.addEventListener('click', function() {
        tabs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const cat = this.getAttribute('data-category');
        sects.forEach(s => {
          s.style.display = (cat === 'all' || s.getAttribute('data-category') === cat) ? 'block' : 'none';
        });
        if (searchInput) searchInput.value = '';
        container.querySelectorAll('.skill-checkbox').forEach(c => c.style.display = 'flex');
      });
    });

    // Search filter for edit profile skills
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        container.querySelectorAll('.skill-checkbox').forEach(c => {
          c.style.display = c.getAttribute('data-skill-name').includes(term) ? 'flex' : 'none';
        });
        sects.forEach(s => {
          const visible = s.querySelectorAll('.skill-checkbox[style*="flex"], .skill-checkbox:not([style])');
          s.style.display = visible.length > 0 ? 'block' : 'none';
        });
      });
    }
  })();

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

  // Load job listings
  async function loadJobListings(reset = true) {
    const container = document.getElementById('jobListings');
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    
    if (reset) {
      currentPage = 1;
      currentJobOffset = 0;
      allJobsLoaded = false;
      container.innerHTML = '<div class="loading">Loading job opportunities...</div>';
      const filterBar = document.getElementById('jobFilterBar');
      if (filterBar) filterBar.style.display = 'flex';
      loadMoreContainer.style.display = 'none';
    }

    const search  = document.getElementById('jobSearch')?.value.trim() || '';
    const role    = document.getElementById('jobFilterRole')?.value || '';
    const type    = document.getElementById('jobFilterType')?.value || '';
    const country = document.getElementById('jobFilterCountry')?.value || '';
    
    try {
      const params = new URLSearchParams({
        limit: jobsPerPage,
        offset: currentJobOffset,
        search, role, type, country
      });
      const response = await fetch(`/QuickHire/Public/actions/get_job_posts.php?${params}`);
      
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      
      const result = await response.json();
      
      if (result.ok) {
        displayJobListings(result.job_posts);
        totalJobsCount = result.total_count ?? (result.job_posts.length < jobsPerPage ? currentJobOffset + result.job_posts.length : currentJobOffset + jobsPerPage + 1);
        renderPagination(result.job_posts.length);
      } else {
        container.innerHTML = `<div class="empty-state"><h3>Error loading jobs</h3><p>${result.error || 'Unknown error'}</p><button class="btn outline" onclick="loadJobListings(true)" style="margin-top:12px;">Try Again</button></div>`;
      }
    } catch (error) {
      container.innerHTML = `<div class="empty-state"><h3>Error loading jobs</h3><p>${error.message}</p><button class="btn outline" onclick="loadJobListings(true)" style="margin-top:12px;">Try Again</button></div>`;
    }
  }

  function renderPagination(returnedCount) {
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    const controls = document.getElementById('paginationControls');
    const hasMore = returnedCount === jobsPerPage;
    const hasPrev = currentPage > 1;

    if (!hasMore && !hasPrev) {
      loadMoreContainer.style.display = 'none';
      return;
    }

    loadMoreContainer.style.display = 'block';

    const btnStyle = (active) => `padding:8px 14px; border-radius:8px; border:1px solid ${active ? 'var(--primary)' : 'var(--line)'}; background:${active ? 'var(--primary)' : '#fff'}; color:${active ? '#fff' : '#111'}; font-weight:700; cursor:pointer; font-size:13px;`;

    controls.innerHTML = `
      <button style="${btnStyle(false)} ${!hasPrev ? 'opacity:0.4;cursor:not-allowed;' : ''}" onclick="goToPage(${currentPage - 1})" ${!hasPrev ? 'disabled' : ''}>← Prev</button>
      <span style="padding:8px 14px; font-weight:700; font-size:13px; color:#f8fafc;">Page ${currentPage}</span>
      <button style="${btnStyle(false)} ${!hasMore ? 'opacity:0.4;cursor:not-allowed;' : ''}" onclick="goToPage(${currentPage + 1})" ${!hasMore ? 'disabled' : ''}>Next →</button>
    `;
  }

  function goToPage(page) {
    if (page < 1) return;
    currentPage = page;
    currentJobOffset = (page - 1) * jobsPerPage;
    document.getElementById('jobListings').scrollIntoView({ behavior: 'smooth', block: 'start' });
    loadJobListings(false);
  }

  // Display job listings
  function displayJobListings(jobs) {
    const container = document.getElementById('jobListings');
    
    if (jobs.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <h3>No job opportunities available</h3>
          <p>There are currently no job postings available. Check back later or encourage employers to post jobs!</p>
        </div>
      `;
      return;
    }
    
    container.innerHTML = generateJobListingsHTML(jobs);
  }

  // Append job listings (for load more)
  function appendJobListings(jobs) {
    const container = document.getElementById('jobListings');
    container.innerHTML += generateJobListingsHTML(jobs);
  }

  // Generate HTML for job listings
  function generateJobListingsHTML(jobs) {
    currentJobs = jobs; // Store jobs for detail view
    let html = '';
    
    jobs.forEach((job, index) => {
      
      const skillsHtml = job.skills.length > 0 
        ? job.skills.slice(0, 3).map(skill => `<span class="skill-tag">${skill.name}</span>`).join('')
        : '<span class="no-skills">No specific skills required</span>';
      
      const moreSkills = job.skills.length > 3 ? ` +${job.skills.length - 3} more` : '';
      
      // Use actual employer profile picture or fallback to initials
      const employerAvatarHtml = job.employer_profile_picture_url 
        ? `<img src="/QuickHire/Public/${job.employer_profile_picture_url}" alt="Employer Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`
        : (job.employer_first_name ? job.employer_first_name.charAt(0).toUpperCase() : 'E');
      
      const rateDisplay = job.rate_per_hour ? `$${parseFloat(job.rate_per_hour).toFixed(2)}/hr` : null;
      const hoursDisplay = job.hours_per_week ? `${job.hours_per_week} hrs/week` : null;
      
      // Truncate description to 150 characters
      const shortDescription = job.description.length > 150 
        ? job.description.substring(0, 150) + '...' 
        : job.description;
      
      
      html += `
        <div class="job-card" data-job-index="${index}" onclick="showJobDetail(${index})" style="cursor: pointer;">
          <div class="job-card-header">
            <div class="job-card-company">
              <div class="company-avatar" style="position:relative;">${employerAvatarHtml}${statusDot(job.employer_last_active)}</div>
              <div class="company-info">
                <div class="company-name">${job.company_name || (job.employer_first_name + ' ' + job.employer_last_name)}</div>
                <div class="company-location">${job.country || job.employer_country || 'Location not specified'}</div>
              </div>
            </div>
            <div class="job-card-date">${new Date(job.created_at).toLocaleDateString()}</div>
          </div>
          
          <div class="job-card-title">${job.title}</div>
          
          <div class="job-card-meta">
            ${job.role_title ? `<span class="meta-tag">🎯 ${job.role_title}</span>` : ''}
            ${job.employment_type ? `<span class="meta-tag">💼 ${job.employment_type.replace('_', ' ')}</span>` : ''}
            ${rateDisplay ? `<span class="meta-tag">💰 ${rateDisplay}</span>` : ''}
            ${hoursDisplay ? `<span class="meta-tag">⏰ ${hoursDisplay}</span>` : ''}
          </div>
          
          <div class="job-card-description">
            ${shortDescription}
            ${job.description.length > 150 ? `<button class="read-more-btn" onclick="event.stopPropagation(); showJobDetail(${index})">READ MORE</button>` : ''}
          </div>
          
          <div class="job-card-skills">
            ${skillsHtml} ${moreSkills}
          </div>
        </div>
      `;
    });
    
    return html;
  }

  // Show job detail view
  function showJobDetail(jobIndex) {
    localStorage.setItem('js_active_page', 'browse');
    localStorage.setItem('js_job_detail_index', jobIndex);
    if (currentJobs[jobIndex]) {
      localStorage.setItem('js_job_detail_id', currentJobs[jobIndex].id);
    }
    // Hide pagination and filter bar when viewing job detail
    document.getElementById('loadMoreContainer').style.display = 'none';
    document.getElementById('jobFilterBar').style.display = 'none';
    
    const job = currentJobs[jobIndex];
    if (!job) return;
    
    const container = document.getElementById('jobListings');
    const skillsHtml = job.skills.length > 0 
      ? job.skills.map(skill => `<span class="skill-tag">${skill.name}</span>`).join('')
      : '<span class="no-skills">No specific skills required</span>';
    
    // Use actual employer profile picture or fallback to initials
    const employerAvatarHtml = job.employer_profile_picture_url 
      ? `<img src="/QuickHire/Public/${job.employer_profile_picture_url}" alt="Employer Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
      : (job.employer_first_name ? job.employer_first_name.charAt(0).toUpperCase() : 'E');
    
    const rateDisplay = job.rate_per_hour ? `$${parseFloat(job.rate_per_hour).toFixed(2)}/hr` : null;
    const hoursDisplay = job.hours_per_week ? `${job.hours_per_week} hrs/week` : null;
    
    container.innerHTML = `
      <div class="job-detail-view">
        <div class="job-detail-header">
          <button class="back-to-jobs-btn" onclick="showJobsList()">← Back to Jobs</button>
          <div class="job-detail-date">${new Date(job.created_at).toLocaleDateString()}</div>
        </div>
        
        <div class="job-detail-company">
          <div class="company-avatar large" style="position:relative;">${employerAvatarHtml}${statusDot(job.employer_last_active)}</div>
          <div class="company-info">
            <div class="company-name">${job.company_name || (job.employer_first_name + ' ' + job.employer_last_name)}</div>
            <div class="company-location">${job.country || job.employer_country || 'Location not specified'}</div>
          </div>
        </div>
        
        <div class="job-detail-title" style="color:var(--text-primary,#f8fafc);">${job.title}</div>
        
        <div class="job-detail-meta">
          ${job.role_title ? `<span class="meta-tag">🎯 ${job.role_title}</span>` : ''}
          ${job.employment_type ? `<span class="meta-tag">💼 ${job.employment_type.replace('_', ' ')}</span>` : ''}
          ${rateDisplay ? `<span class="meta-tag">💰 ${rateDisplay}</span>` : ''}
          ${hoursDisplay ? `<span class="meta-tag">⏰ ${hoursDisplay}</span>` : ''}
        </div>
        
        <div class="job-detail-section">
          <h3>Job Description</h3>
          <div class="job-detail-description">${job.description.replace(/\n/g, '<br>')}</div>
        </div>
        
        <div class="job-detail-section">
          <h3>Required Skills</h3>
          <div class="job-detail-skills">
            ${skillsHtml}
          </div>
        </div>
        
        <div class="job-detail-actions">
          <button class="btn primary large" onclick="messageEmployerAboutJob(${Number.parseInt(job.employer_id, 10)}, ${Number.parseInt(job.id, 10)}, '${escapeJsString(job.title)}', this)">
            Apply for this Job
          </button>
        </div>
      </div>
    `;
  }

  // Show jobs list view
  function showJobsList() {
    localStorage.removeItem('js_job_detail_index');
    localStorage.removeItem('js_job_detail_id');
    document.getElementById('jobFilterBar').style.display = 'flex';
    displayJobListings(currentJobs);
    renderPagination(currentJobs.length);
  }

  // Make functions globally available
  window.showJobDetail = showJobDetail;
  window.showJobsList = showJobsList;
  window.messageEmployerAboutJob = messageEmployerAboutJob;

  // Show job detail by job post ID (used from message banner)
  window.showJobDetailById = async function(jobPostId) {
    jobPostId = Number.parseInt(jobPostId, 10);
    if (!Number.isFinite(jobPostId) || jobPostId <= 0) return;

    const idx = currentJobs.findIndex(j => j.id == jobPostId);
    showJobBrowsing(false);

    if (idx !== -1) {
      showJobDetail(idx);
      return;
    }

    const container = document.getElementById('jobListings');
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    const filterBar = document.getElementById('jobFilterBar');

    if (container) {
      container.innerHTML = '<div class="loading">Loading job details...</div>';
    }
    if (loadMoreContainer) loadMoreContainer.style.display = 'none';
    if (filterBar) filterBar.style.display = 'none';

    try {
      const response = await fetch(`/QuickHire/Public/actions/get_job_posts.php?job_id=${encodeURIComponent(jobPostId)}`);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const result = await response.json();
      if (!result.ok || !result.job_post) {
        throw new Error(result.error || 'Job post not found');
      }

      currentJobs = [result.job_post];
      showJobDetail(0);
    } catch (error) {
      if (container) {
        container.innerHTML = `
          <div class="empty-state">
            <h3>Job unavailable</h3>
            <p>${error.message || 'This job post could not be loaded.'}</p>
            <button class="btn outline" onclick="showJobsList()" style="margin-top:12px;">Back to Jobs</button>
          </div>
        `;
      }
    }
  };

  const initialJobPostId = new URLSearchParams(window.location.search).get('job_id');
  if (initialJobPostId) {
    window.showJobDetailById(initialJobPostId);
  }

  // Message employer about a job - direct to message input
  async function messageEmployerAboutJob(employerId, jobPostId, jobTitle, buttonElement) {
    const button = buttonElement;
    
    if (!button) {
      showToast('Failed to start conversation: Button not found', 'error');
      return;
    }
    
    button.disabled = true;
    button.textContent = 'Starting...';

    try {
      
      const formData = new FormData();
      formData.append('employer_id', employerId);
      if (jobPostId) formData.append('job_post_id', jobPostId);

      const response = await fetch('/QuickHire/Public/actions/start_conversation_with_employer.php', {
        method: 'POST',
        body: formData
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (data.ok) {
        // Only store as pending if it's truly a NEW conversation with no messages
        if (!data.is_existing) {
          window.pendingConversation = {
            id: data.conversation_id,
            employerName: data.employer_name,
            jobTitle: jobTitle,
            isNew: true
          };
        } else {
          // Clear any pending conversation since this is an existing one
          window.pendingConversation = null;
        }
        
        // Open messaging panel
        messagingPanel.classList.add('open');
        setMessagesNavActive();
        
        // Load conversations first
        await loadConversations();
        
        // Wait a bit for conversations to load
        await new Promise(resolve => setTimeout(resolve, 200));
        
        
        // Find the conversation
        const conversation = currentConversations.find(conv => conv.id == data.conversation_id);
        
        if (conversation) {
          await openConversation(conversation.id, `${conversation.other_first_name} ${conversation.other_last_name}`, conversation.other_profile_picture_url || '');
          
          // Focus on message input and add placeholder text
          setTimeout(() => {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
              messageInput.placeholder = `Write your message about "${jobTitle}"...`;
              messageInput.focus();
            }
          }, 300);
          
          showToast(`Ready to message ${data.employer_name} about "${jobTitle}"`, 'success');
        } else {
          // Fallback - try to find conversation after longer delay
          setTimeout(async () => {
            await loadConversations();
            
            const retryConversation = currentConversations.find(conv => conv.id == data.conversation_id);
            
            if (retryConversation) {
              await openConversation(retryConversation.id, `${retryConversation.other_first_name} ${retryConversation.other_last_name}`, retryConversation.other_profile_picture_url || '');
              setTimeout(() => {
                const messageInput = document.getElementById('messageInput');
                if (messageInput) {
                  messageInput.placeholder = `Write your message about "${jobTitle}"...`;
                  messageInput.focus();
                }
              }, 300);
            } else {
              showToast('Conversation created but could not open. Please check your messages.', 'info');
            }
          }, 1000);
          
          showToast(`Ready to message about "${jobTitle}"`, 'success');
        }
        
      } else {
        showToast('Failed to start conversation: ' + data.error, 'error');
      }
    } catch (error) {
      showToast('Failed to start conversation: ' + error.message, 'error');
    } finally {
      button.disabled = false;
      button.textContent = 'Apply for this Job';
    }
  }

  // Load more jobs button
  window.goToPage = goToPage;

  // Search & filter listeners - debounce search input
  let searchDebounce;
  document.getElementById('jobSearch').addEventListener('input', () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => loadJobListings(true), 400);
  });
  document.getElementById('jobFilterRole').addEventListener('change', () => loadJobListings(true));
  document.getElementById('jobFilterType').addEventListener('change', () => loadJobListings(true));
  document.getElementById('jobFilterCountry').addEventListener('change', () => loadJobListings(true));
  document.getElementById('btnClearFilters').addEventListener('click', () => {
    document.getElementById('jobSearch').value = '';
    document.getElementById('jobFilterRole').value = '';
    document.getElementById('jobFilterType').value = '';
    document.getElementById('jobFilterCountry').value = '';
    loadJobListings(true);
  });

  // Show toast notification if there's a success message
  <?php if ($flashSuccess): ?>
  document.addEventListener('DOMContentLoaded', function() {
    showToast('<?= addslashes($flashSuccess) ?>', 'success');
  });
  <?php endif; ?>

  // Messaging functionality
  let currentConversationId = null;
  let currentConversations = [];
  let messagePollingInterval = null;
  let conversationRefreshInterval = null;

  function resetJobseekerMessageSelection() {
    currentConversationId = null;
    const chatTitle = document.getElementById('chatTitle');
    if (chatTitle) chatTitle.textContent = 'Select a conversation';
    const chatStatus = document.getElementById('chatStatus');
    if (chatStatus) chatStatus.innerHTML = '';
    const jobBanner = document.getElementById('jobBanner');
    if (jobBanner) {
      jobBanner.style.display = 'none';
      jobBanner.innerHTML = '';
    }
    const menuBtn = document.getElementById('chatMenuBtn');
    if (menuBtn) menuBtn.style.display = 'none';
    const avatarEl = document.getElementById('chatHeaderAvatar');
    if (avatarEl) {
      avatarEl.style.display = 'none';
      avatarEl.innerHTML = '';
    }
    const msgContainer = document.getElementById('messagesContainer');
    if (msgContainer) {
      msgContainer.innerHTML = `<div class="empty-state"><h3>Select a conversation</h3><p>Choose a conversation from the sidebar to start messaging</p></div>`;
    }
    const inputArea = document.getElementById('messageInputArea');
    if (inputArea) inputArea.style.display = 'none';
    document.querySelectorAll('.conversation-item').forEach(item => item.classList.remove('active'));
  }

  // Show messaging panel
  function showMessaging() {
    const panel = document.getElementById('messagingPanel');
    if (!panel) return;
    
    panel.classList.add('open');
    setMessagesNavActive();
    
    // Reset chat header to default state when opening fresh
    currentConversationId = null;

    // Clear search input
    const jsSearch = document.getElementById('jsConvSearchInput');
    if (jsSearch) jsSearch.value = '';

    // Stop any active message polling from a previous conversation
    if (messagePollingInterval) {
      clearInterval(messagePollingInterval);
      messagePollingInterval = null;
    }

    const chatTitle = document.getElementById('chatTitle');
    if (chatTitle) chatTitle.textContent = 'Select a conversation';
    const chatStatus = document.getElementById('chatStatus');
    if (chatStatus) chatStatus.innerHTML = '';
    const menuBtnReset = document.getElementById('chatMenuBtn');
    if (menuBtnReset) menuBtnReset.style.display = 'none';
    const avatarEl = document.getElementById('chatHeaderAvatar');
    if (avatarEl) { avatarEl.style.display = 'none'; avatarEl.innerHTML = ''; }
    const jobBannerReset = document.getElementById('jobBanner');
    if (jobBannerReset) { jobBannerReset.style.display = 'none'; jobBannerReset.innerHTML = ''; }
    const msgContainer = document.getElementById('messagesContainer');
    if (msgContainer) msgContainer.innerHTML = `
      <div class="empty-state">
        <h3>Select a conversation</h3>
        <p>Choose a conversation from the sidebar to start messaging</p>
      </div>`;
    const inputArea = document.getElementById('messageInputArea');
    if (inputArea) inputArea.style.display = 'none';

    // Always show conversations list
    const conversationsList = document.getElementById('conversationsList');
    if (conversationsList) conversationsList.style.display = 'block';
    
    // Load conversations
    loadConversations();
    
    // Start auto-refresh
    if (conversationRefreshInterval) clearInterval(conversationRefreshInterval);
    conversationRefreshInterval = setInterval(loadConversations, 10000);
  }

  // Hide messaging panel
  function hideMessaging() {
    const panel = document.getElementById('messagingPanel');
    if (!panel) return;
    
    panel.classList.remove('open');
    
    if (messagePollingInterval) {
      clearInterval(messagePollingInterval);
      messagePollingInterval = null;
    }
    if (conversationRefreshInterval) {
      clearInterval(conversationRefreshInterval);
      conversationRefreshInterval = null;
    }
    
    currentConversationId = null;
    const chatArea = document.getElementById('chatArea');
    const conversationsList = document.getElementById('conversationsList');
    const messageInputArea = document.getElementById('messageInputArea');
    
    if (chatArea) chatArea.style.display = 'none';
    if (conversationsList) conversationsList.style.display = 'block';
    if (messageInputArea) messageInputArea.style.display = 'none';
  }

  // Load conversations
  async function loadConversations() {
    try {
      const response = await fetch('/QuickHire/Public/actions/get_conversations.php');
      const data = await response.json();
      
      
      if (data.ok) {
        currentConversations = data.conversations;
        if (currentConversationId && !currentConversations.some(c => parseInt(c.id, 10) === parseInt(currentConversationId, 10))) {
          resetJobseekerMessageSelection();
        }
        displayConversations(data.conversations);
      } else {
        document.getElementById('conversationsList').innerHTML = `
          <div class="empty-state">
            <h3>Error loading conversations</h3>
            <p>${data.error}</p>
          </div>
        `;
      }
    } catch (error) {
      document.getElementById('conversationsList').innerHTML = `
        <div class="empty-state">
          <h3>Error loading conversations</h3>
          <p>Network error occurred</p>
        </div>
      `;
    }
  }

  // Display conversations
  function displayConversations(conversations) {
    const container = document.getElementById('conversationsList');
    
    if (conversations.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <h3>No conversations yet</h3>
          <p>Start browsing jobs and message employers to begin conversations.</p>
        </div>
      `;
      return;
    }
    
    let html = '';
    conversations.forEach(conv => {
      const isActive = currentConversationId === conv.id;
      const unreadBadge = conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : '';
      const participantName = `${conv.other_first_name || ''} ${conv.other_last_name || ''}`.trim();
      const appliedJobLinks = renderAppliedJobLinks(conv);
      
      // Calculate active status for chat header (not list)
      let activeText = '';
      if (conv.other_last_active) {
        const lastActive = new Date(conv.other_last_active);
        const diffMinutes = Math.floor((new Date() - lastActive) / (1000 * 60));
        if (diffMinutes <= 1) {
          activeText = '<div style="font-size: 11px; color: #10b981; margin-top: 2px;">Active now</div>';
        } else if (diffMinutes <= 5) {
          activeText = `<div style="font-size: 11px; color: #64748b; margin-top: 2px;">Active ${diffMinutes} min ago</div>`;
        }
      }
      
      // Show profile picture or initials
      const avatarHtml = conv.other_profile_picture_url 
        ? `<img src="/QuickHire/Public/${conv.other_profile_picture_url}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
        : (conv.other_first_name ? conv.other_first_name.charAt(0).toUpperCase() : 'U');
      
      // Show appropriate preview text
      let previewText = conv.last_message || 'No messages yet';
      if (window.pendingConversation && conv.id == window.pendingConversation.id && !conv.last_message) {
        previewText = `Ready to discuss: ${window.pendingConversation.jobTitle}`;
      }
      
      html += `
        <div class="conversation-item ${isActive ? 'active' : ''}" onclick="openConversation(${Number.parseInt(conv.id, 10)}, '${escapeJsString(participantName)}', '${escapeJsString(conv.other_profile_picture_url || '')}')">
          <div class="conversation-avatar" style="position: relative;">
            ${avatarHtml}
            ${statusDot(conv.other_last_active)}
          </div>
          <div class="conversation-info">
            <div class="conversation-name">${escapeHtml(participantName)}</div>
            ${appliedJobLinks ? `<div class="conversation-job-links">${appliedJobLinks}</div>` : ''}
            <div class="conversation-preview">${escapeHtml(previewText)}</div>
          </div>
          ${unreadBadge}
        </div>
      `;
    });
    
    container.innerHTML = html;
  }

  // Filter conversations by search query
  function jsFilterConversations() {
    const q = (document.getElementById('jsConvSearchInput')?.value || '').toLowerCase().trim();
    if (!q) {
      displayConversations(currentConversations || []);
      return;
    }
    const filtered = (currentConversations || []).filter(c =>
      `${c.other_first_name} ${c.other_last_name}`.toLowerCase().includes(q) ||
      (c.last_message || '').toLowerCase().includes(q) ||
      (c.job_post_title || '').toLowerCase().includes(q) ||
      conversationJobText(c).includes(q)
    );
    displayConversations(filtered);
  }

  // Open conversation
  async function openConversation(conversationId, participantName, avatarUrl = '') {
    currentConversationId = conversationId;
    
    // Update UI - keep conversations sidebar visible on desktop, only hide on mobile
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
      document.getElementById('conversationsList').style.display = 'none';
    }
    document.getElementById('chatArea').style.display = 'flex';
    document.getElementById('chatTitle').textContent = participantName;

    // Show active status below name in chat header
    const conv = (currentConversations || []).find(c => c.id == conversationId);
    const chatStatusEl = document.getElementById('chatStatus');
    if (chatStatusEl) {
      let statusHtml = '';
      if (conv && conv.other_last_active) {
        const diffMs = new Date() - new Date(conv.other_last_active);
        const diffMin = Math.floor(diffMs / 60000);
        if (diffMin <= 1) {
          statusHtml = `<span style="color:#10b981;font-size:12px;font-weight:600;">Active now</span>`;
        } else if (diffMin <= 5) {
          statusHtml = `<span style="color:#64748b;font-size:12px;">Active ${diffMin} min ago</span>`;
        }
      }
      chatStatusEl.innerHTML = statusHtml;
    }

    // Show job post banner
    const jobBanner = document.getElementById('jobBanner');
    if (jobBanner) {
      const appliedJobLinks = conv ? renderAppliedJobLinks(conv) : '';

      if (appliedJobLinks) {
        jobBanner.style.display = 'block';
        jobBanner.innerHTML = `
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:13px;color:#94a3b8;">Applied for:</span>
            <span class="applied-job-links">${appliedJobLinks}</span>
          </div>`;
      } else {
        jobBanner.style.display = 'none';
        jobBanner.innerHTML = '';
      }
    }

    // Set avatar in header
    const avatarEl = document.getElementById('chatHeaderAvatar');
    if (avatarEl) {
      avatarEl.style.position = 'relative';
      if (avatarUrl) {
        avatarEl.innerHTML = `<img src="/QuickHire/Public/${avatarUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">${statusDot(conv ? conv.other_last_active : null)}`;
      } else {
        avatarEl.innerHTML = `${participantName.charAt(0).toUpperCase()}${statusDot(conv ? conv.other_last_active : null)}`;
      }
      avatarEl.style.display = 'flex';
    }
    // Show the ⋮ menu button
    const menuBtn = document.getElementById('chatMenuBtn');
    if (menuBtn) menuBtn.style.display = 'block';
    
    // Show message input area
    const messageInputArea = document.getElementById('messageInputArea');
    if (messageInputArea) {
      messageInputArea.style.display = 'block';
    }
    
    // Update active states in conversation list
    document.querySelectorAll('.conversation-item').forEach(item => {
      item.classList.remove('active');
    });
    
    // Find and mark the correct conversation as active
    document.querySelectorAll('.conversation-item').forEach(item => {
      const onclick = item.getAttribute('onclick');
      if (onclick && onclick.includes(`openConversation(${conversationId},`)) {
        item.classList.add('active');
      }
    });
    
    // Load messages
    await loadMessages(conversationId);
    
    // Start polling for new messages
    if (messagePollingInterval) {
      clearInterval(messagePollingInterval);
    }
    messagePollingInterval = setInterval(() => loadMessages(conversationId), 3000);
  }

  // Load messages
  async function loadMessages(conversationId) {
    try {
      const response = await fetch(`/QuickHire/Public/actions/get_messages.php?conversation_id=${conversationId}`);
      const data = await response.json();
      
      if (data.ok) {
        displayMessages(data.messages);
      } else {
      }
    } catch (error) {
    }
  }

  // Display messages
  function displayMessages(messages) {
    const container = document.getElementById('messagesContainer');
    
    if (messages.length === 0) {
      container.innerHTML = '<div class="empty-state"><p>No messages yet. Start the conversation!</p></div>';
      return;
    }
    
    let html = '';
    messages.forEach(msg => {
      const isOwn = msg.sender_id == <?= $userId ?>;
      const messageTime = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
      
      // Show profile picture or initials - use sender_avatar from the query
      const avatarHtml = msg.sender_avatar 
        ? `<img src="/QuickHire/Public/${msg.sender_avatar}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
        : (msg.first_name ? msg.first_name.charAt(0).toUpperCase() : 'U');
      
      let messageContent = '';
      
      if (msg.file_url) {
        // File message
        const fileName = msg.file_name || msg.file_url.split('/').pop();
        const fileSize = msg.file_size ? formatFileSize(msg.file_size) : '';
        const ext = fileName.split('.').pop().toLowerCase();
        const isImage = ['jpg','jpeg','png','gif','webp'].includes(ext);

        if (isImage) {
          messageContent = `
            <img src="${msg.file_url}" alt="${fileName}"
              onclick="openImageModal('${msg.file_url}', '${fileName}')"
              style="max-width:260px;max-height:260px;border-radius:10px;display:block;cursor:zoom-in;object-fit:cover;">
            ${fileSize ? `<div class="file-size" style="margin-top:4px;">${fileName} · ${fileSize}</div>` : ''}
          `;
        } else {
          const fileIcon = getFileIcon(fileName);
          messageContent = `
            <div class="file-message">
              <div class="file-icon">${fileIcon}</div>
              <div class="file-info">
                <a href="${msg.file_url}" target="_blank" class="file-link">${fileName}</a>
                ${fileSize ? `<div class="file-size">${fileSize}</div>` : ''}
              </div>
            </div>
          `;
        }
      }
      
      if (msg.content) {
        messageContent += `<div class="message-text">${msg.content.replace(/\n/g, '<br>')}</div>`;
      }
      
      // Add call indicator if this is a call message
      const callIndicator = msg.room_code ? '<div class="call-indicator">📞 Video Call Message</div>' : '';
      
      html += `
        <div class="message ${isOwn ? 'own' : ''}">
          <div class="message-avatar">${avatarHtml}</div>
          <div class="message-content">
            ${callIndicator}
            ${messageContent}
            <div class="message-time">${messageTime}</div>
          </div>
        </div>
      `;
    });
    
    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
  }

  // Send message
  document.getElementById('messageForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!currentConversationId) return;
    
    const messageInput = document.getElementById('messageInput');
    const fileInput = document.getElementById('fileInput');
    const messageText = messageInput.value.trim();
    
    if (!messageText && !fileInput.files[0]) return;
    
    const formData = new FormData();
    formData.append('conversation_id', currentConversationId);
    if (messageText) formData.append('message', messageText);
    if (fileInput.files[0]) formData.append('file', fileInput.files[0]);
    
    try {
      const response = await fetch('/QuickHire/Public/actions/send_message.php', {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      if (data.ok) {
        messageInput.value = '';
        fileInput.value = '';
        removeFilePreview();
        
        // Clear pending conversation since message was sent
        if (window.pendingConversation && window.pendingConversation.id == currentConversationId) {
          window.pendingConversation = null;
        }
        
        // Reload messages immediately to show the sent message
        await loadMessages(currentConversationId);
        
        // Refresh conversations to update last message and move conversation to top
        await loadConversations();
      } else {
        showToast('Failed to send message: ' + data.error, 'error');
      }
    } catch (error) {
      showToast('Failed to send message', 'error');
    }
  });

  // File preview functionality
  document.getElementById('fileInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
      showFilePreview(file);
    }
  });

  function showFilePreview(file) {
    const preview = document.getElementById('filePreview');
    const nameEl = document.getElementById('filePreviewName');
    const sizeEl = document.getElementById('filePreviewSize');
    
    nameEl.textContent = file.name;
    sizeEl.textContent = formatFileSize(file.size);
    preview.style.display = 'block';
  }

  function removeFilePreview() {
    document.getElementById('filePreview').style.display = 'none';
    document.getElementById('fileInput').value = '';
  }

  // Utility functions
  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  function getFileIcon(fileName) {
    const ext = fileName.split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return '🖼️';
    if (['pdf'].includes(ext)) return '📄';
    if (['doc', 'docx'].includes(ext)) return '📝';
    if (['zip', 'rar', '7z'].includes(ext)) return '📦';
    return '📎';
  }

  // Show conversations list (mobile back button)
  function showConversationsList() {
    document.getElementById('conversationsList').style.display = 'block';
    document.getElementById('chatArea').style.display = 'none';
    
    // Stop message polling
    if (messagePollingInterval) {
      clearInterval(messagePollingInterval);
      messagePollingInterval = null;
    }
    
    currentConversationId = null;
  }

  // Chat menu functionality
  function toggleChatMenu() {
    const menu = document.getElementById('chatMenu');
    const btn  = document.getElementById('chatMenuBtn');
    if (!menu || !btn) return;
    if (menu.style.display !== 'none') { menu.style.display = 'none'; return; }
    const rect = btn.getBoundingClientRect();
    menu.style.top   = (rect.bottom + 6) + 'px';
    menu.style.right = (window.innerWidth - rect.right) + 'px';
    menu.style.left  = 'auto';
    menu.style.display = 'block';
  }

  // Close chat menu when clicking outside
  document.addEventListener('click', function(e) {
    const menu = document.getElementById('chatMenu');
    const menuBtn = document.getElementById('chatMenuBtn');
    if (menu && !menu.contains(e.target) && e.target !== menuBtn) {
      menu.style.display = 'none';
    }
  });

  // Delete conversation
  async function deleteCurrentConversation() {
    if (!currentConversationId) return;
    
    // Hide menu first
    const menu = document.getElementById('chatMenu');
    if (menu) menu.style.display = 'none';

    if (!confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
      return;
    }
    
    try {
      const formData = new FormData();
      formData.append('conversation_id', currentConversationId);
      
      const response = await fetch('/QuickHire/Public/actions/delete_conversation.php', {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      if (data.ok) {
        // Reset chat header
        resetJobseekerMessageSelection();

        showToast('Conversation deleted successfully', 'success');
        loadConversations();
      } else {
        showToast('Failed to delete conversation: ' + data.error, 'error');
      }
    } catch (error) {
      showToast('Failed to delete conversation', 'error');
    }
  }

  // Global alias so floating menu can call it
  window.deleteConversation = deleteCurrentConversation;

  // Add event listener for messages button
  document.getElementById('btnMessages').addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    showMessaging();
  });

  // Close messages button removed — panel closed via sidebar nav buttons

  // Debug function to check messaging state
  window.debugMessaging = function() {
  };

  // Auto-resize message input
  document.getElementById('messageInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });
</script>

<script>
// Activity tracking - update on any click/interaction
let lastActivityUpdate = Date.now();

// Shared helper: returns green dot (active) or grey dot (offline) HTML
function statusDot(lastActive) {
  if (lastActive && (new Date() - new Date(lastActive)) < 60000) {
    return `<span class="status-dot status-dot--active"></span>`;
  }
  return `<span class="status-dot status-dot--offline"></span>`;
}
const updateActivity = () => {
  const now = Date.now();
  // Only send update if 5 seconds have passed since last update (throttle)
  if (now - lastActivityUpdate > 5000) {
    fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' })
      .catch(() => {});
    lastActivityUpdate = now;
  }
};

// Track clicks anywhere in the app
document.addEventListener('click', updateActivity);
document.addEventListener('keypress', updateActivity);
document.addEventListener('scroll', updateActivity);

// Fallback: update every 30 seconds if user is idle but page is open
setInterval(() => {
  fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' })
    .catch(() => {});
}, 30000);

// Update on page load and when tab regains focus
fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' });
window.addEventListener('focus', updateActivity);
</script>

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
<script>
function openImageModal(url, name) {
  const lb = document.getElementById('imageLightbox');
  document.getElementById('lightboxImg').src = url;
  document.getElementById('lightboxName').textContent = name || '';
  document.getElementById('lightboxDownload').href = url;
  lb.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeImageModal() {
  document.getElementById('imageLightbox').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeImageModal(); });
</script>

</html>
