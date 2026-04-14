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

$config = require __DIR__ . '/../Config/config.php';
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
    
    // Update active states
    btnHome.classList.add('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    
    // Update title when showing dashboard
    document.querySelector('.title').textContent = 'Welcome back 👋';
    document.querySelector('.subtitle').textContent = 'Click "Find Employer" to automatically connect with employers who are currently looking for candidates like you.';
  }

  function showProfileEdit() {
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'block';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.add('active');
    btnEditProfile2.classList.add('active');
    
    // Update title when showing edit form
    document.querySelector('.title').textContent = 'Edit Your Profile';
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

</html>
