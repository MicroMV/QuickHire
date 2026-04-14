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
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/jobseeker-dashboard.css">
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
      <button class="primary" id="btnBrowseJobs">📋 Browse Jobs</button>
      <a href="#" class="nav-link" id="btnMessages" style="display: block; padding: 12px 20px; color: #3b82f6; text-decoration: none; border-radius: 8px; margin: 8px 0; background: #f1f5f9; text-align: center; font-weight: 600; position: relative;">
        💬 Messages
        <?php if ($unreadCount > 0): ?>
          <span style="position: absolute; top: 4px; right: 8px; background: #ef4444; color: white; border-radius: 10px; padding: 2px 6px; font-size: 12px; font-weight: bold;"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>

      <button id="btnHome">🏠 Home</button>
      <button id="btnEditProfile">✏️ Edit Profile</button>

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
          Click "Find Employer" to automatically connect with employers who are currently looking for candidates like you.
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
              <input type="file" id="profile_picture_js" name="profile_picture" accept="image/*">
            </div>
            <div style="text-align: center; margin-top: 10px; font-weight: 700; color: #333;">
              <div id="nameDisplay" style="cursor: pointer; padding: 5px; border-radius: 5px; transition: background 0.2s;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='transparent'" onclick="editName()">
                <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
                <span style="font-size: 12px; color: #666; margin-left: 5px;">✏️</span>
              </div>
              <div id="nameEdit" style="display: none;">
                <input type="text" id="firstNameInput" name="first_name" value="<?= htmlspecialchars($userInfo['first_name'] ?? '') ?>" placeholder="First Name" style="width: 45%; padding: 8px; margin: 5px 2%; border: 1px solid var(--line); border-radius: 8px;">
                <input type="text" id="lastNameInput" name="last_name" value="<?= htmlspecialchars($userInfo['last_name'] ?? '') ?>" placeholder="Last Name" style="width: 45%; padding: 8px; margin: 5px 2%; border: 1px solid var(--line); border-radius: 8px;">
                <div style="margin-top: 10px;">
                  <button type="button" onclick="saveName()" style="padding: 6px 12px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; margin-right: 5px;">Save</button>
                  <button type="button" onclick="cancelEditName()" style="padding: 6px 12px; background: #ccc; color: #333; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                </div>
              </div>
            </div>
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Desired Job Role *</label>
            <select name="role_title" required style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <option value="">Select Job Role</option>
              <option value="Software Engineer" <?= ($profile['role_title'] ?? '') === 'Software Engineer' ? 'selected' : '' ?>>Software Engineer</option>
              <option value="Software Developer" <?= ($profile['role_title'] ?? '') === 'Software Developer' ? 'selected' : '' ?>>Software Developer</option>
              <option value="Web Developer" <?= ($profile['role_title'] ?? '') === 'Web Developer' ? 'selected' : '' ?>>Web Developer</option>
              <option value="Mobile Developer" <?= ($profile['role_title'] ?? '') === 'Mobile Developer' ? 'selected' : '' ?>>Mobile Developer</option>
              <option value="Full Stack Developer" <?= ($profile['role_title'] ?? '') === 'Full Stack Developer' ? 'selected' : '' ?>>Full Stack Developer</option>
              <option value="Frontend Developer" <?= ($profile['role_title'] ?? '') === 'Frontend Developer' ? 'selected' : '' ?>>Frontend Developer</option>
              <option value="Backend Developer" <?= ($profile['role_title'] ?? '') === 'Backend Developer' ? 'selected' : '' ?>>Backend Developer</option>
              <option value="DevOps Engineer" <?= ($profile['role_title'] ?? '') === 'DevOps Engineer' ? 'selected' : '' ?>>DevOps Engineer</option>
              <option value="Cloud Engineer" <?= ($profile['role_title'] ?? '') === 'Cloud Engineer' ? 'selected' : '' ?>>Cloud Engineer</option>
              <option value="Data Scientist" <?= ($profile['role_title'] ?? '') === 'Data Scientist' ? 'selected' : '' ?>>Data Scientist</option>
              <option value="Data Engineer" <?= ($profile['role_title'] ?? '') === 'Data Engineer' ? 'selected' : '' ?>>Data Engineer</option>
              <option value="Data Analyst" <?= ($profile['role_title'] ?? '') === 'Data Analyst' ? 'selected' : '' ?>>Data Analyst</option>
              <option value="Machine Learning Engineer" <?= ($profile['role_title'] ?? '') === 'Machine Learning Engineer' ? 'selected' : '' ?>>Machine Learning Engineer</option>
              <option value="AI Engineer" <?= ($profile['role_title'] ?? '') === 'AI Engineer' ? 'selected' : '' ?>>AI Engineer</option>
              <option value="Database Administrator" <?= ($profile['role_title'] ?? '') === 'Database Administrator' ? 'selected' : '' ?>>Database Administrator</option>
              <option value="System Administrator" <?= ($profile['role_title'] ?? '') === 'System Administrator' ? 'selected' : '' ?>>System Administrator</option>
              <option value="Network Engineer" <?= ($profile['role_title'] ?? '') === 'Network Engineer' ? 'selected' : '' ?>>Network Engineer</option>
              <option value="Security Engineer" <?= ($profile['role_title'] ?? '') === 'Security Engineer' ? 'selected' : '' ?>>Security Engineer</option>
              <option value="QA Engineer" <?= ($profile['role_title'] ?? '') === 'QA Engineer' ? 'selected' : '' ?>>QA Engineer</option>
              <option value="QA Automation Engineer" <?= ($profile['role_title'] ?? '') === 'QA Automation Engineer' ? 'selected' : '' ?>>QA Automation Engineer</option>
              <option value="UI/UX Designer" <?= ($profile['role_title'] ?? '') === 'UI/UX Designer' ? 'selected' : '' ?>>UI/UX Designer</option>
              <option value="Product Designer" <?= ($profile['role_title'] ?? '') === 'Product Designer' ? 'selected' : '' ?>>Product Designer</option>
              <option value="Technical Product Manager" <?= ($profile['role_title'] ?? '') === 'Technical Product Manager' ? 'selected' : '' ?>>Technical Product Manager</option>
              <option value="IT Project Manager" <?= ($profile['role_title'] ?? '') === 'IT Project Manager' ? 'selected' : '' ?>>IT Project Manager</option>
              <option value="Scrum Master" <?= ($profile['role_title'] ?? '') === 'Scrum Master' ? 'selected' : '' ?>>Scrum Master</option>
              <option value="Business Intelligence Analyst" <?= ($profile['role_title'] ?? '') === 'Business Intelligence Analyst' ? 'selected' : '' ?>>Business Intelligence Analyst</option>
              <option value="IT Support Specialist" <?= ($profile['role_title'] ?? '') === 'IT Support Specialist' ? 'selected' : '' ?>>IT Support Specialist</option>
              <option value="Technical Writer" <?= ($profile['role_title'] ?? '') === 'Technical Writer' ? 'selected' : '' ?>>Technical Writer</option>
            </select>
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
            </select>
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
            <select name="bachelors_degree" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
              <option value="">Select Degree (Optional)</option>
              <option value="Computer Science" <?= ($profile['bachelors_degree'] ?? '') === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
              <option value="Information Technology" <?= ($profile['bachelors_degree'] ?? '') === 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
              <option value="Software Engineering" <?= ($profile['bachelors_degree'] ?? '') === 'Software Engineering' ? 'selected' : '' ?>>Software Engineering</option>
              <option value="Computer Engineering" <?= ($profile['bachelors_degree'] ?? '') === 'Computer Engineering' ? 'selected' : '' ?>>Computer Engineering</option>
              <option value="Information Systems" <?= ($profile['bachelors_degree'] ?? '') === 'Information Systems' ? 'selected' : '' ?>>Information Systems</option>
              <option value="Data Science" <?= ($profile['bachelors_degree'] ?? '') === 'Data Science' ? 'selected' : '' ?>>Data Science</option>
              <option value="Cybersecurity" <?= ($profile['bachelors_degree'] ?? '') === 'Cybersecurity' ? 'selected' : '' ?>>Cybersecurity</option>
              <option value="Network Engineering" <?= ($profile['bachelors_degree'] ?? '') === 'Network Engineering' ? 'selected' : '' ?>>Network Engineering</option>
              <option value="Artificial Intelligence" <?= ($profile['bachelors_degree'] ?? '') === 'Artificial Intelligence' ? 'selected' : '' ?>>Artificial Intelligence</option>
              <option value="Web Development" <?= ($profile['bachelors_degree'] ?? '') === 'Web Development' ? 'selected' : '' ?>>Web Development</option>
              <option value="Game Development" <?= ($profile['bachelors_degree'] ?? '') === 'Game Development' ? 'selected' : '' ?>>Game Development</option>
              <option value="Mobile Application Development" <?= ($profile['bachelors_degree'] ?? '') === 'Mobile Application Development' ? 'selected' : '' ?>>Mobile Application Development</option>
              <option value="Cloud Computing" <?= ($profile['bachelors_degree'] ?? '') === 'Cloud Computing' ? 'selected' : '' ?>>Cloud Computing</option>
              <option value="Digital Media" <?= ($profile['bachelors_degree'] ?? '') === 'Digital Media' ? 'selected' : '' ?>>Digital Media</option>
              <option value="Graphic Design" <?= ($profile['bachelors_degree'] ?? '') === 'Graphic Design' ? 'selected' : '' ?>>Graphic Design</option>
              <option value="Other Technology Degree" <?= ($profile['bachelors_degree'] ?? '') === 'Other Technology Degree' ? 'selected' : '' ?>>Other Technology Degree</option>
              <option value="No Degree" <?= ($profile['bachelors_degree'] ?? '') === 'No Degree' ? 'selected' : '' ?>>No Degree</option>
            </select>
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Portfolio/Website</label>
            <input name="portfolio_url" value="<?= htmlspecialchars($profile['portfolio_url'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>

          <div>
            <label style="display:block; font-weight:900; margin-bottom:6px;">Age</label>
            <input name="age" type="number" min="18" max="60" value="<?= htmlspecialchars($profile['age'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
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

    <!-- Job Browsing Content (Hidden by default) -->
    <div class="card" id="jobBrowsingContent" style="display:none;">
      <h2>📋 Browse Jobs</h2>
      <p style="color: var(--muted); margin-bottom: 20px;">
        Discover job opportunities posted by employers. Click "Message Employer" to start a conversation about any position that interests you.
      </p>

      <div id="jobListings" class="job-listings">
        <div class="loading">Loading job opportunities...</div>
      </div>

      <div id="loadMoreContainer" style="text-align: center; margin-top: 20px; display: none;">
        <button class="btn outline" id="loadMoreJobs">Load More Jobs</button>
      </div>
    </div>

    <!-- MESSAGING PANEL -->
    <div class="messaging-panel" id="messagingPanel" style="display: none;">
      <div class="messaging-header">
        <h3>💬 Messages</h3>
        <button class="close-btn" id="closeMessages">✕</button>
      </div>
      
      <div class="messaging-content">
        <div class="conversations-sidebar">
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
              <button id="chatMenuBtn" onclick="toggleChatMenu()" style="background:none;border:none;cursor:pointer;font-size:20px;color:#64748b;padding:4px 8px;border-radius:6px;line-height:1;" title="Options">⋮</button>
              <div id="chatMenu" style="display:none;position:absolute;right:0;top:32px;background:white;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,0.12);min-width:180px;z-index:100;">
                <button onclick="deleteConversation(currentConversationId)" style="display:flex;align-items:center;gap:8px;width:100%;padding:12px 16px;background:none;border:none;cursor:pointer;color:#ef4444;font-size:14px;font-weight:600;border-radius:10px;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='none'">🗑 Delete Conversation</button>
              </div>
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
              <button type="button" class="file-button" onclick="document.getElementById('fileInput').click()">📎</button>
              <button type="submit" class="send-button" id="sendButton">Send</button>
            </form>
          </div>
        </div>
      </div>
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
  const btnBrowseJobs = document.getElementById('btnBrowseJobs');
  const btnCancelEdit = document.getElementById('btnCancelEdit');
  
  const dashboardContent = document.getElementById('dashboardContent');
  const profileEditContent = document.getElementById('profileEditContent');
  const jobBrowsingContent = document.getElementById('jobBrowsingContent');

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
        if (data.match_score) {
          // Match found with score
        }
        // Redirect to call
        window.location.href = '/QuickHire/Public/call.php?room=' + encodeURIComponent(data.room);
      } else if (data.waiting) {
        showToast('No employers are currently looking. Please wait.', 'info');
      } else {
        showToast(data.error || 'Unable to find employers.', 'error');
      }
    } catch (error) {
      console.error('Find employer error:', error);
      showToast('Connection error. Please try again.', 'error');
    } finally {
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
    jobBrowsingContent.style.display = 'none';
    
    // Update active states
    btnHome.classList.add('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnBrowseJobs.classList.remove('active');
    
    // Update title when showing dashboard
    document.querySelector('.title').textContent = 'Welcome back 👋';
    document.querySelector('.subtitle').textContent = 'Click "Find Employer" to automatically connect with employers who are currently looking for candidates like you.';
  }

  function showProfileEdit() {
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'block';
    jobBrowsingContent.style.display = 'none';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.add('active');
    btnEditProfile2.classList.add('active');
    btnBrowseJobs.classList.remove('active');
    
    // Update title when showing edit form
    document.querySelector('.title').textContent = 'Edit Your Profile';
    document.querySelector('.subtitle').textContent = 'Update your information to improve matching with employers.';
  }

  function showJobBrowsing() {
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'none';
    jobBrowsingContent.style.display = 'block';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnBrowseJobs.classList.add('active');
    
    // Update title when showing job browsing
    document.querySelector('.title').textContent = 'Browse Jobs';
    document.querySelector('.subtitle').textContent = 'Discover job opportunities from employers looking for candidates like you.';
    
    // Load job listings
    loadJobListings();
  }

  // Initialize with Home active
  btnHome.classList.add('active');

  btnFindEmployer.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    findEmployer();
  });
  
  btnFindEmployer2.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    findEmployer();
  });
  btnHome.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showDashboard();
  });
  
  btnEditProfile.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showProfileEdit();
  });
  
  btnEditProfile2.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showProfileEdit();
  });
  
  btnBrowseJobs.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showJobBrowsing();
  });
  
  btnCancelEdit.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showDashboard();
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

  // Job Browsing Functionality
  let currentJobOffset = 0;
  const jobsPerPage = 10;
  let allJobsLoaded = false;

  // Load job listings
  async function loadJobListings(reset = true) {
    const container = document.getElementById('jobListings');
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    
    if (reset) {
      currentJobOffset = 0;
      allJobsLoaded = false;
      container.innerHTML = '<div class="loading">Loading job opportunities...</div>';
      loadMoreContainer.style.display = 'none';
    }
    
    try {
      console.log('Loading job listings with offset:', currentJobOffset);
      const response = await fetch(`/QuickHire/Public/actions/get_job_posts.php?limit=${jobsPerPage}&offset=${currentJobOffset}`);
      
      console.log('Response status:', response.status, response.statusText);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const contentType = response.headers.get('content-type');
      console.log('Response content type:', contentType);
      
      if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        console.error('Non-JSON response:', text);
        throw new Error('Server returned non-JSON response');
      }
      
      const result = await response.json();
      console.log('Job posts API response:', result);
      
      if (result.ok) {
        console.log('Successfully loaded', result.job_posts.length, 'job posts');
        if (reset) {
          displayJobListings(result.job_posts);
        } else {
          appendJobListings(result.job_posts);
        }
        
        currentJobOffset += result.job_posts.length;
        
        // Show/hide load more button
        if (result.job_posts.length < jobsPerPage) {
          allJobsLoaded = true;
          loadMoreContainer.style.display = 'none';
        } else {
          loadMoreContainer.style.display = 'block';
        }
      } else {
        console.error('API returned error:', result.error);
        container.innerHTML = `<div class="empty-state">
          <h3>Error loading job listings</h3>
          <p>${result.error || 'Unknown error occurred'}</p>
          <button class="btn outline" onclick="loadJobListings(true)" style="margin-top: 12px;">Try Again</button>
        </div>`;
      }
    } catch (error) {
      console.error('Error loading job listings:', error);
      container.innerHTML = `<div class="empty-state">
        <h3>Error loading job listings</h3>
        <p>${error.message}</p>
        <button class="btn outline" onclick="loadJobListings(true)" style="margin-top: 12px;">Try Again</button>
        <br><br>
      </div>`;
    }
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
    let html = '';
    
    jobs.forEach(job => {
      const skillsHtml = job.skills.length > 0 
        ? job.skills.slice(0, 5).map(skill => `<span class="skill-tag">${skill.name}</span>`).join('')
        : '<span class="no-skills">No specific skills required</span>';
      
      const moreSkills = job.skills.length > 5 ? ` +${job.skills.length - 5} more` : '';
      
      const employerAvatar = job.employer_first_name ? job.employer_first_name.charAt(0).toUpperCase() : 'E';
      
      const rateDisplay = job.rate_per_hour ? `$${parseFloat(job.rate_per_hour).toFixed(2)}/hr` : null;
      const hoursDisplay = job.hours_per_week ? `${job.hours_per_week} hrs/week` : null;
      
      html += `
        <div class="job-listing-item">
          <div class="job-listing-header">
            <div class="job-listing-title">${job.title}</div>
            <div class="job-listing-date">${new Date(job.created_at).toLocaleDateString()}</div>
          </div>
          
          <div class="job-listing-company">
            <div class="company-avatar">${employerAvatar}</div>
            <div class="company-info">
              <div class="company-name">${job.company_name || (job.employer_first_name + ' ' + job.employer_last_name)}</div>
              <div class="company-location">${job.country || job.employer_country || 'Location not specified'}</div>
            </div>
          </div>
          
          <div class="job-listing-meta">
            ${job.role_title ? `<span class="meta-item">💼 ${job.role_title}</span>` : ''}
            ${job.employment_type ? `<span class="meta-item">⏰ ${job.employment_type.replace('_', ' ')}</span>` : ''}
            ${rateDisplay ? `<span class="meta-item">💰 ${rateDisplay}</span>` : ''}${hoursDisplay ? `<span class="meta-item">🕐 ${hoursDisplay}</span>` : ''}
          </div>
          
          <div class="job-listing-description">
            ${job.description.length > 200 ? job.description.substring(0, 200) + '...' : job.description}
          </div>
          
          <div class="job-listing-skills">
            <strong>Required Skills:</strong> ${skillsHtml}${moreSkills}
          </div>
          
          <div class="job-listing-actions">
            <button class="btn primary" onclick="messageEmployerAboutJob(${job.employer_id}, ${job.id}, '${job.title.replace(/'/g, "\\'")}', this)">
              💬 Message Employer
            </button>
          </div>
        </div>
      `;
    });
    
    return html;
  }

  // Message employer about a job
  async function messageEmployerAboutJob(employerId, jobPostId, jobTitle, buttonElement) {
    const button = buttonElement;
    
    if (!button) {
      console.error('Button element not found');
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
        const actionText = data.is_existing ? 'Opened existing conversation' : 'Started new conversation';
        
        // Open messaging panel first
        messagingPanel.style.display = 'flex';
        
        // Load conversations to get the latest list
        await loadConversations();
        
        // Give a small delay to ensure conversations are loaded
        await new Promise(resolve => setTimeout(resolve, 100));
        
        // Find the conversation by ID
        const conversation = conversations.find(c => c.id == data.conversation_id);
        
        if (conversation) {
          await openConversation(conversation.id);
          showToast(`${actionText} about "${jobTitle}"`, 'success');
        } else {
          showToast(`Conversation about "${jobTitle}" is ready. Please check your messages.`, 'success');
        }
      } else {
        showToast('Failed to start conversation: ' + data.error, 'error');
      }
    } catch (error) {
      console.error('Start conversation error:', error);
      showToast('Failed to start conversation: ' + error.message, 'error');
    } finally {
      button.disabled = false;
      button.textContent = '💬 Message Employer';
    }
  }

  // Load more jobs button
  document.getElementById('loadMoreJobs').addEventListener('click', function() {
    if (!allJobsLoaded) {
      loadJobListings(false);
    }
  });

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
        document.getElementById('nameDisplay').innerHTML = `${firstName} ${lastName} <span style="font-size: 12px; color: #666; margin-left: 5px;">✏️</span>`;
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
const closeMessages = document.getElementById('closeMessages');
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
  messagingPanel.style.display = 'flex';
  loadConversations();
});

// Close messaging panel
closeMessages.addEventListener('click', () => {
  messagingPanel.style.display = 'none';
  currentConversationId = null;
  document.getElementById('messageInputArea').style.display = 'none';
});

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
      displayConversations();
    } else {
      conversationsList.innerHTML = '<div class="empty-state">No conversations yet</div>';
    }
  } catch (error) {
    conversationsList.innerHTML = '<div class="empty-state">Error loading conversations</div>';
  }
}

// Display conversations
function displayConversations() {
  if (conversations.length === 0) {
    conversationsList.innerHTML = '<div class="empty-state">No conversations yet</div>';
    return;
  }
  
  let html = '';
  conversations.forEach(conv => {
    // Active now = within 60 seconds, show green dot
    const isActive = conv.other_last_active && (new Date() - new Date(conv.other_last_active)) < 60000;
    const minutesAgo = conv.other_last_active ? Math.floor((new Date() - new Date(conv.other_last_active)) / 60000) : null;
    // Show badge only for 1-5 minutes ago
    const showBadge = minutesAgo !== null && minutesAgo >= 1 && minutesAgo <= 5;
    const activeIndicator = isActive ? `<span class="active-dot"></span>` : "";
    const minuteBadge = showBadge ? `<span class="minute-badge">${minutesAgo}</span>` : "";
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
          <div class="conversation-name">
            ${conv.other_first_name} ${conv.other_last_name}
          </div>
          <div class="conversation-preview">
            ${conv.other_role || 'User'}
          </div>
          ${conv.last_message ? `
            <div class="conversation-preview">
              ${conv.last_message.substring(0, 50)}${conv.last_message.length > 50 ? '...' : ''}
            </div>
          ` : ''}
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
          ${conv.unread_count > 0 ? `<div class="unread-badge">${conv.unread_count}</div>` : ''}
        </div>
      </div>
    `;
  });
  
  conversationsList.innerHTML = html;
}

// Toggle chat options menu
function toggleChatMenu() {
  const menu = document.getElementById('chatMenu');
  menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
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
  if (!confirm('Delete this conversation? This cannot be undone.')) return;
  const fd = new FormData();
  fd.append('conversation_id', conversationId);
  const res = await fetch('/QuickHire/Public/actions/delete_conversation.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    if (currentConversationId === conversationId) {
      currentConversationId = null;
      document.getElementById('chatArea').style.display = 'none';
    }
    await loadConversations();
  } else {
    alert('Failed to delete conversation');
  }
}

// Open conversation
async function openConversation(conversationId) {
  currentConversationId = conversationId;
  const conversation = conversations.find(c => c.id === conversationId);
  
  if (!conversation) return;
  
  // Update active conversation
  document.querySelectorAll('.conversation-item').forEach(item => {
    item.classList.remove("active");
    if (item.getAttribute("data-conversation-id") == conversationId) {
      item.classList.add("active");
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

  // Update chat header avatar
  const avatarEl = document.getElementById('chatHeaderAvatar');
  avatarEl.style.display = 'flex';
  if (conversation.other_avatar) {
    avatarEl.innerHTML = `<img src="/QuickHire/Public/${conversation.other_avatar}" style="width:100%;height:100%;object-fit:cover;">`;
  } else {
    avatarEl.innerHTML = conversation.other_first_name.charAt(0).toUpperCase();
  }
  document.getElementById('chatMenu').style.display = 'none';
  
  // On mobile, hide conversations and show chat
  if (window.innerWidth <= 768) {
    document.querySelector('.conversations-sidebar').style.display = 'none';
    chatArea.style.display = 'flex';
  }
  
  // Set conversation ID in form
  document.getElementById('conversationId').value = conversationId;
  
  // Show message input area
  document.getElementById('messageInputArea').style.display = 'block';
  
  // Load messages
  await loadMessages(conversationId);
}

// Load messages
async function loadMessages(conversationId) {
  try {
    messagesContainer.innerHTML = '<div class="loading">Loading messages...</div>';
    
    const response = await fetch(`/QuickHire/Public/actions/get_messages.php?conversation_id=${conversationId}`);
    const data = await response.json();
    
    if (data.ok) {
      displayMessages(data.messages);
    } else {
      messagesContainer.innerHTML = '<div class="empty-state">No messages yet</div>';
    }
  } catch (error) {
    messagesContainer.innerHTML = '<div class="empty-state">Error loading messages</div>';
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

// Toggle chat options menu
function toggleChatMenu() {
  const menu = document.getElementById('chatMenu');
  menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', (e) => {
  if (!e.target.closest('#chatMenuBtn') && !e.target.closest('#chatMenu')) {
    const menu = document.getElementById('chatMenu');
    if (menu) menu.style.display = 'none';
  }
});

// Delete conversation
async function deleteConversation(conversationId) {
  if (!confirm('Delete this conversation? This cannot be undone.')) return;
  const fd = new FormData();
  fd.append('conversation_id', conversationId);
  const res = await fetch('/QuickHire/Public/actions/delete_conversation.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    if (currentConversationId === conversationId) {
      currentConversationId = null;
      document.getElementById('chatArea').style.display = 'none';
    }
    await loadConversations();
  } else {
    alert('Failed to delete conversation');
  }
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
      if (messagingPanel && messagingPanel.style.display !== 'none') {
        messagingPanel.style.display = 'none';
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
    console.log('Updating activity...');
    fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' })
      .then(r => r.json())
      .then(data => console.log('Activity updated:', data))
      .catch(err => console.error('Activity update failed:', err));
    lastActivityUpdate = now;
  }
};

// Track clicks anywhere in the app
document.addEventListener('click', updateActivity);
document.addEventListener('keypress', updateActivity);
document.addEventListener('scroll', updateActivity);

// Fallback: update every 30 seconds if user is idle but page is open
setInterval(() => {
  console.log('Fallback activity update...');
  fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' })
    .then(r => r.json())
    .then(data => console.log('Fallback activity updated:', data))
    .catch(err => console.error('Fallback activity update failed:', err));
}, 30000);

// Refresh conversations every 10 seconds to update active status
setInterval(() => {
  if (messagingPanel.style.display === 'flex') {
    console.log('Refreshing conversations for active status...');
    loadConversations();
  }
}, 10000);

console.log('Initial activity update...');
fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' })
  .then(r => r.json())
  .then(data => console.log('Initial activity updated:', data))
  .catch(err => console.error('Initial activity update failed:', err));
</script>

</html>


