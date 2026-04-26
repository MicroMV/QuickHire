<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\FileUpload;
use Rongie\QuickHire\Services\ProfileService;

Session::start();
Auth::requireLogin();

$config = require __DIR__ . '/../Config/config.php';
$db = new Database($config['db']);

$profileService = new ProfileService($db->pdo(), new FileUpload());

$userId = Auth::userId();
$role = Auth::role();

$error = Session::flash('error');
$success = Session::flash('success');

$js = ($role === 'JOBSEEKER') ? $profileService->getJobseeker($userId) : [];
$emp = ($role === 'EMPLOYER') ? $profileService->getEmployer($userId) : [];

// Get user's basic information (name, etc.)
$userStmt = $db->pdo()->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch();

// Get available skills for both roles
$skillsStmt = $db->pdo()->query("SELECT id, name, category FROM skills ORDER BY category ASC, name ASC");
$allSkills = $skillsStmt->fetchAll();

// Get current skills for jobseeker
$currentSkills = [];
if ($role === 'JOBSEEKER') {
    $jsSkillsStmt = $db->pdo()->prepare("SELECT skill_id FROM jobseeker_skills WHERE jobseeker_user_id = ?");
    $jsSkillsStmt->execute([$userId]);
    $currentSkills = array_column($jsSkillsStmt->fetchAll(), 'skill_id');
}

// Get current required skills for employer
$currentRequiredSkills = [];
if ($role === 'EMPLOYER') {
    try {
        $empSkillsStmt = $db->pdo()->prepare("SELECT skill_id FROM employer_required_skills WHERE employer_user_id = ?");
        $empSkillsStmt->execute([$userId]);
        $currentRequiredSkills = array_column($empSkillsStmt->fetchAll(), 'skill_id');
    } catch (Exception $e) {
        // Table might not exist yet, use empty array
        $currentRequiredSkills = [];
    }
}

$csrf = Csrf::token();
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Complete Profile - QuickHire</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/landingPage.css">
  <link rel="stylesheet" href="assets/css/complete-profile.css">
  <link rel="stylesheet" href="assets/css/dark-theme.css">
</head>
<body class="landing-body">
  <div class="wrap">
    <div class="card">
      <h1 class="h">Complete your profile</h1>
      <p class="sub">Role: <strong><?= htmlspecialchars($role) ?></strong></p>

      <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <?php if ($role === 'JOBSEEKER'): ?>
        <form method="POST" action="actions/save_profile.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="profile_type" value="JOBSEEKER">

          <div class="grid">
            <div class="full">
              <div class="avatar-upload" onclick="document.getElementById('profile_picture_js_complete').click()">
                <div class="avatar-preview">
                  <?php if (!empty($js['profile_picture_url'])): ?>
                    <img src="/QuickHire/Public/<?= htmlspecialchars($js['profile_picture_url']) ?>" alt="Profile Picture">
                  <?php else: ?>
                    <?= strtoupper(substr($userInfo['first_name'] ?? 'U', 0, 1)) ?>
                  <?php endif; ?>
                </div>
                <div class="avatar-overlay"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
                <input type="file" id="profile_picture_js_complete" name="profile_picture" accept="image/*">
              </div>
              <div style="text-align: center; margin-top: 10px; font-weight: 700; color: #333;">
                <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
              </div>
            </div>

            <div>
              <label>Desired Job Role *</label>
              <select name="role_title" required>
                <option value="">Select Job Role</option>
                <option value="Software Engineer" <?= ($js['role_title'] ?? '') === 'Software Engineer' ? 'selected' : '' ?>>Software Engineer</option>
                <option value="Software Developer" <?= ($js['role_title'] ?? '') === 'Software Developer' ? 'selected' : '' ?>>Software Developer</option>
                <option value="Web Developer" <?= ($js['role_title'] ?? '') === 'Web Developer' ? 'selected' : '' ?>>Web Developer</option>
                <option value="Mobile Developer" <?= ($js['role_title'] ?? '') === 'Mobile Developer' ? 'selected' : '' ?>>Mobile Developer</option>
                <option value="Full Stack Developer" <?= ($js['role_title'] ?? '') === 'Full Stack Developer' ? 'selected' : '' ?>>Full Stack Developer</option>
                <option value="Frontend Developer" <?= ($js['role_title'] ?? '') === 'Frontend Developer' ? 'selected' : '' ?>>Frontend Developer</option>
                <option value="Backend Developer" <?= ($js['role_title'] ?? '') === 'Backend Developer' ? 'selected' : '' ?>>Backend Developer</option>
                <option value="DevOps Engineer" <?= ($js['role_title'] ?? '') === 'DevOps Engineer' ? 'selected' : '' ?>>DevOps Engineer</option>
                <option value="Cloud Engineer" <?= ($js['role_title'] ?? '') === 'Cloud Engineer' ? 'selected' : '' ?>>Cloud Engineer</option>
                <option value="Data Scientist" <?= ($js['role_title'] ?? '') === 'Data Scientist' ? 'selected' : '' ?>>Data Scientist</option>
                <option value="Data Engineer" <?= ($js['role_title'] ?? '') === 'Data Engineer' ? 'selected' : '' ?>>Data Engineer</option>
                <option value="Data Analyst" <?= ($js['role_title'] ?? '') === 'Data Analyst' ? 'selected' : '' ?>>Data Analyst</option>
                <option value="Machine Learning Engineer" <?= ($js['role_title'] ?? '') === 'Machine Learning Engineer' ? 'selected' : '' ?>>Machine Learning Engineer</option>
                <option value="AI Engineer" <?= ($js['role_title'] ?? '') === 'AI Engineer' ? 'selected' : '' ?>>AI Engineer</option>
                <option value="Database Administrator" <?= ($js['role_title'] ?? '') === 'Database Administrator' ? 'selected' : '' ?>>Database Administrator</option>
                <option value="System Administrator" <?= ($js['role_title'] ?? '') === 'System Administrator' ? 'selected' : '' ?>>System Administrator</option>
                <option value="Network Engineer" <?= ($js['role_title'] ?? '') === 'Network Engineer' ? 'selected' : '' ?>>Network Engineer</option>
                <option value="Security Engineer" <?= ($js['role_title'] ?? '') === 'Security Engineer' ? 'selected' : '' ?>>Security Engineer</option>
                <option value="QA Engineer" <?= ($js['role_title'] ?? '') === 'QA Engineer' ? 'selected' : '' ?>>QA Engineer</option>
                <option value="QA Automation Engineer" <?= ($js['role_title'] ?? '') === 'QA Automation Engineer' ? 'selected' : '' ?>>QA Automation Engineer</option>
                <option value="UI/UX Designer" <?= ($js['role_title'] ?? '') === 'UI/UX Designer' ? 'selected' : '' ?>>UI/UX Designer</option>
                <option value="Product Designer" <?= ($js['role_title'] ?? '') === 'Product Designer' ? 'selected' : '' ?>>Product Designer</option>
                <option value="Technical Product Manager" <?= ($js['role_title'] ?? '') === 'Technical Product Manager' ? 'selected' : '' ?>>Technical Product Manager</option>
                <option value="IT Project Manager" <?= ($js['role_title'] ?? '') === 'IT Project Manager' ? 'selected' : '' ?>>IT Project Manager</option>
                <option value="Scrum Master" <?= ($js['role_title'] ?? '') === 'Scrum Master' ? 'selected' : '' ?>>Scrum Master</option>
                <option value="Business Intelligence Analyst" <?= ($js['role_title'] ?? '') === 'Business Intelligence Analyst' ? 'selected' : '' ?>>Business Intelligence Analyst</option>
                <option value="IT Support Specialist" <?= ($js['role_title'] ?? '') === 'IT Support Specialist' ? 'selected' : '' ?>>IT Support Specialist</option>
                <option value="Technical Writer" <?= ($js['role_title'] ?? '') === 'Technical Writer' ? 'selected' : '' ?>>Technical Writer</option>
              </select>
            </div>

            <div>
              <label>Rate per Hour (USD) *</label>
              <input name="rate_per_hour" type="number" step="0.01" value="<?= htmlspecialchars($js['rate_per_hour'] ?? '') ?>" required>
            </div>

            <div>
              <label>Available Hours per Day *</label>
              <input name="available_time" value="<?= htmlspecialchars($js['available_time'] ?? '') ?>" required>
            </div>

            <div>
              <label>Country *</label>
              <select name="country" required>
                <option value="">Select Country</option>
                <option value="Afghanistan" <?= ($js['country'] ?? '') === 'Afghanistan' ? 'selected' : '' ?>>Afghanistan</option>
                <option value="Albania" <?= ($js['country'] ?? '') === 'Albania' ? 'selected' : '' ?>>Albania</option>
                <option value="Algeria" <?= ($js['country'] ?? '') === 'Algeria' ? 'selected' : '' ?>>Algeria</option>
                <option value="Argentina" <?= ($js['country'] ?? '') === 'Argentina' ? 'selected' : '' ?>>Argentina</option>
                <option value="Australia" <?= ($js['country'] ?? '') === 'Australia' ? 'selected' : '' ?>>Australia</option>
                <option value="Austria" <?= ($js['country'] ?? '') === 'Austria' ? 'selected' : '' ?>>Austria</option>
                <option value="Bangladesh" <?= ($js['country'] ?? '') === 'Bangladesh' ? 'selected' : '' ?>>Bangladesh</option>
                <option value="Belgium" <?= ($js['country'] ?? '') === 'Belgium' ? 'selected' : '' ?>>Belgium</option>
                <option value="Brazil" <?= ($js['country'] ?? '') === 'Brazil' ? 'selected' : '' ?>>Brazil</option>
                <option value="Canada" <?= ($js['country'] ?? '') === 'Canada' ? 'selected' : '' ?>>Canada</option>
                <option value="China" <?= ($js['country'] ?? '') === 'China' ? 'selected' : '' ?>>China</option>
                <option value="Colombia" <?= ($js['country'] ?? '') === 'Colombia' ? 'selected' : '' ?>>Colombia</option>
                <option value="Denmark" <?= ($js['country'] ?? '') === 'Denmark' ? 'selected' : '' ?>>Denmark</option>
                <option value="Egypt" <?= ($js['country'] ?? '') === 'Egypt' ? 'selected' : '' ?>>Egypt</option>
                <option value="Finland" <?= ($js['country'] ?? '') === 'Finland' ? 'selected' : '' ?>>Finland</option>
                <option value="France" <?= ($js['country'] ?? '') === 'France' ? 'selected' : '' ?>>France</option>
                <option value="Germany" <?= ($js['country'] ?? '') === 'Germany' ? 'selected' : '' ?>>Germany</option>
                <option value="Greece" <?= ($js['country'] ?? '') === 'Greece' ? 'selected' : '' ?>>Greece</option>
                <option value="India" <?= ($js['country'] ?? '') === 'India' ? 'selected' : '' ?>>India</option>
                <option value="Indonesia" <?= ($js['country'] ?? '') === 'Indonesia' ? 'selected' : '' ?>>Indonesia</option>
                <option value="Ireland" <?= ($js['country'] ?? '') === 'Ireland' ? 'selected' : '' ?>>Ireland</option>
                <option value="Italy" <?= ($js['country'] ?? '') === 'Italy' ? 'selected' : '' ?>>Italy</option>
                <option value="Japan" <?= ($js['country'] ?? '') === 'Japan' ? 'selected' : '' ?>>Japan</option>
                <option value="Malaysia" <?= ($js['country'] ?? '') === 'Malaysia' ? 'selected' : '' ?>>Malaysia</option>
                <option value="Mexico" <?= ($js['country'] ?? '') === 'Mexico' ? 'selected' : '' ?>>Mexico</option>
                <option value="Netherlands" <?= ($js['country'] ?? '') === 'Netherlands' ? 'selected' : '' ?>>Netherlands</option>
                <option value="New Zealand" <?= ($js['country'] ?? '') === 'New Zealand' ? 'selected' : '' ?>>New Zealand</option>
                <option value="Norway" <?= ($js['country'] ?? '') === 'Norway' ? 'selected' : '' ?>>Norway</option>
                <option value="Pakistan" <?= ($js['country'] ?? '') === 'Pakistan' ? 'selected' : '' ?>>Pakistan</option>
                <option value="Philippines" <?= ($js['country'] ?? '') === 'Philippines' ? 'selected' : '' ?>>Philippines</option>
                <option value="Poland" <?= ($js['country'] ?? '') === 'Poland' ? 'selected' : '' ?>>Poland</option>
                <option value="Portugal" <?= ($js['country'] ?? '') === 'Portugal' ? 'selected' : '' ?>>Portugal</option>
                <option value="Russia" <?= ($js['country'] ?? '') === 'Russia' ? 'selected' : '' ?>>Russia</option>
                <option value="Saudi Arabia" <?= ($js['country'] ?? '') === 'Saudi Arabia' ? 'selected' : '' ?>>Saudi Arabia</option>
                <option value="Singapore" <?= ($js['country'] ?? '') === 'Singapore' ? 'selected' : '' ?>>Singapore</option>
                <option value="South Africa" <?= ($js['country'] ?? '') === 'South Africa' ? 'selected' : '' ?>>South Africa</option>
                <option value="South Korea" <?= ($js['country'] ?? '') === 'South Korea' ? 'selected' : '' ?>>South Korea</option>
                <option value="Spain" <?= ($js['country'] ?? '') === 'Spain' ? 'selected' : '' ?>>Spain</option>
                <option value="Sweden" <?= ($js['country'] ?? '') === 'Sweden' ? 'selected' : '' ?>>Sweden</option>
                <option value="Switzerland" <?= ($js['country'] ?? '') === 'Switzerland' ? 'selected' : '' ?>>Switzerland</option>
                <option value="Thailand" <?= ($js['country'] ?? '') === 'Thailand' ? 'selected' : '' ?>>Thailand</option>
                <option value="Turkey" <?= ($js['country'] ?? '') === 'Turkey' ? 'selected' : '' ?>>Turkey</option>
                <option value="United Arab Emirates" <?= ($js['country'] ?? '') === 'United Arab Emirates' ? 'selected' : '' ?>>United Arab Emirates</option>
                <option value="United Kingdom" <?= ($js['country'] ?? '') === 'United Kingdom' ? 'selected' : '' ?>>United Kingdom</option>
                <option value="United States" <?= ($js['country'] ?? '') === 'United States' ? 'selected' : '' ?>>United States</option>
                <option value="Vietnam" <?= ($js['country'] ?? '') === 'Vietnam' ? 'selected' : '' ?>>Vietnam</option>
              </select>
            </div>

            <div>
              <label>Employment Type *</label>
              <select name="employment_type" required>
                <?php
                  $empTypes = ['PART_TIME' => 'Part-time', 'FULL_TIME' => 'Full-time', 'CONTRACT' => 'Contract', 'FREELANCE' => 'Freelance'];
                  $currentEmpType = $js['employment_type'] ?? '';
                  echo '<option value="">Select Employment Type</option>';
                  foreach ($empTypes as $value => $label) {
                    $sel = ($currentEmpType === $value) ? 'selected' : '';
                    echo "<option value=\"$value\" $sel>$label</option>";
                  }
                ?>
              </select>
            </div>

            <div>
              <label>English Mastery *</label>
              <select name="english_mastery" required>
                <?php
                  $levels = ['BEGINNER','INTERMEDIATE','ADVANCED','FLUENT','NATIVE'];
                  $cur = $js['english_mastery'] ?? '';
                  echo '<option value="">Select</option>';
                  foreach ($levels as $lv) {
                    $sel = ($cur === $lv) ? 'selected' : '';
                    echo "<option value=\"$lv\" $sel>$lv</option>";
                  }
                ?>
              </select>
            </div>

            <div class="full skills-section">
              <label>Your Skills *</label>
              <div class="hint">Select skills that match your expertise</div>
              
              <div class="skills-container">
                <input type="text" class="skills-search" placeholder="🔍 Search skills..." id="jsSkillsSearch">
                
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
                
                <div class="skills-grid" id="jsSkillsContainer">
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
                            <input type="checkbox" name="skill_ids[]" value="<?= $skill['id'] ?>" <?= in_array($skill['id'], $currentSkills) ? 'checked' : '' ?> style="width:14px;height:14px;flex-shrink:0;cursor:pointer;accent-color:#1f6f82;margin:0;">
                            <?= htmlspecialchars($skill['name']) ?>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <div>
              <label>Bachelor's Degree</label>
              <select name="bachelors_degree">
                <option value="">Select Degree (Optional)</option>
                <option value="Computer Science" <?= ($js['bachelors_degree'] ?? '') === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                <option value="Information Technology" <?= ($js['bachelors_degree'] ?? '') === 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                <option value="Software Engineering" <?= ($js['bachelors_degree'] ?? '') === 'Software Engineering' ? 'selected' : '' ?>>Software Engineering</option>
                <option value="Computer Engineering" <?= ($js['bachelors_degree'] ?? '') === 'Computer Engineering' ? 'selected' : '' ?>>Computer Engineering</option>
                <option value="Information Systems" <?= ($js['bachelors_degree'] ?? '') === 'Information Systems' ? 'selected' : '' ?>>Information Systems</option>
                <option value="Data Science" <?= ($js['bachelors_degree'] ?? '') === 'Data Science' ? 'selected' : '' ?>>Data Science</option>
                <option value="Cybersecurity" <?= ($js['bachelors_degree'] ?? '') === 'Cybersecurity' ? 'selected' : '' ?>>Cybersecurity</option>
                <option value="Network Engineering" <?= ($js['bachelors_degree'] ?? '') === 'Network Engineering' ? 'selected' : '' ?>>Network Engineering</option>
                <option value="Artificial Intelligence" <?= ($js['bachelors_degree'] ?? '') === 'Artificial Intelligence' ? 'selected' : '' ?>>Artificial Intelligence</option>
                <option value="Web Development" <?= ($js['bachelors_degree'] ?? '') === 'Web Development' ? 'selected' : '' ?>>Web Development</option>
                <option value="Game Development" <?= ($js['bachelors_degree'] ?? '') === 'Game Development' ? 'selected' : '' ?>>Game Development</option>
                <option value="Mobile Application Development" <?= ($js['bachelors_degree'] ?? '') === 'Mobile Application Development' ? 'selected' : '' ?>>Mobile Application Development</option>
                <option value="Cloud Computing" <?= ($js['bachelors_degree'] ?? '') === 'Cloud Computing' ? 'selected' : '' ?>>Cloud Computing</option>
                <option value="Digital Media" <?= ($js['bachelors_degree'] ?? '') === 'Digital Media' ? 'selected' : '' ?>>Digital Media</option>
                <option value="Graphic Design" <?= ($js['bachelors_degree'] ?? '') === 'Graphic Design' ? 'selected' : '' ?>>Graphic Design</option>
                <option value="Other Technology Degree" <?= ($js['bachelors_degree'] ?? '') === 'Other Technology Degree' ? 'selected' : '' ?>>Other Technology Degree</option>
                <option value="No Degree" <?= ($js['bachelors_degree'] ?? '') === 'No Degree' ? 'selected' : '' ?>>No Degree</option>
              </select>
            </div>

            <div>
              <label>Portfolio/Website</label>
              <input name="portfolio_url" value="<?= htmlspecialchars($js['portfolio_url'] ?? '') ?>">
            </div>

            <div>
              <label>Age</label>
              <input name="age" type="number" min="18" max="60" value="<?= htmlspecialchars($js['age'] ?? '') ?>">
            </div>

            <div>
              <label>Gender</label>
              <select name="gender">
                <?php $g = $js['gender'] ?? ''; ?>
                <option value="">Prefer not to say</option>
                <option value="MALE" <?= $g==='MALE'?'selected':'' ?>>Male</option>
                <option value="FEMALE" <?= $g==='FEMALE'?'selected':'' ?>>Female</option>
                <option value="OTHER" <?= $g==='OTHER'?'selected':'' ?>>Other</option>
              </select>
            </div>

            <div class="full">
              <label>Profile Description *</label>
              <textarea name="profile_description" required><?= htmlspecialchars($js['profile_description'] ?? '') ?></textarea>
            </div>

            <div class="full">
              <label>Attached Resume (PDF)</label>
              <input type="file" name="resume" accept="application/pdf" id="resumeInputComplete">
              <div class="hint">If you upload a new one, it replaces the old resume.</div>
              
              <!-- Current Resume Display -->
              <div id="currentResumeDisplayComplete" style="margin-top:10px;">
                <?php if (!empty($js['resume_url'])): ?>
                  <div style="padding:10px; border:1px solid #ddd; border-radius:8px; background:#f8f9fa; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:20px;">📄</span>
                    <div style="flex:1;">
                      <div style="font-weight:600; color:#333;">Current Resume</div>
                      <div style="font-size:12px; color:#666;">
                        <a href="<?= htmlspecialchars($js['resume_url']) ?>" target="_blank" style="color:#1f6f82; text-decoration:none;">
                          View Resume
                        </a>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
              
              <!-- New Resume Preview -->
              <div id="newResumePreviewComplete" style="margin-top:10px; display:none;">
                <div style="padding:10px; border:1px solid #10b981; border-radius:8px; background:#f0fdf4; display:flex; align-items:center; gap:10px;">
                  <span style="font-size:20px;">📄</span>
                  <div style="flex:1;">
                    <div style="font-weight:600; color:#333;" id="newResumeFileNameComplete">New Resume Selected</div>
                    <div style="font-size:12px; color:#059669;">Ready to upload</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <button class="btnsave" type="submit">Save Profile</button>
        </form>

      <?php elseif ($role === 'EMPLOYER'): ?>
        <?php 
          // Create skills by category for employer section
          $skillsByCategory = [];
          foreach ($allSkills as $skill) {
            $skillsByCategory[$skill['category']][] = $skill;
          }
        ?>
        <form method="POST" action="actions/save_profile.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="profile_type" value="EMPLOYER">

          <div class="grid">
            <div class="full">
              <div class="avatar-upload" onclick="document.getElementById('profile_picture_emp_complete').click()">
                <div class="avatar-preview">
                  <?php if (!empty($emp['profile_picture_url'])): ?>
                    <img src="/QuickHire/Public/<?= htmlspecialchars($emp['profile_picture_url']) ?>" alt="Profile Picture">
                  <?php else: ?>
                    <?= strtoupper(substr($userInfo['first_name'] ?? 'E', 0, 1)) ?>
                  <?php endif; ?>
                </div>
                <div class="avatar-overlay"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
                <input type="file" id="profile_picture_emp_complete" name="profile_picture" accept="image/*">
              </div>
              <div style="text-align: center; margin-top: 10px; font-weight: 700; color: #333;">
                <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
              </div>
            </div>

            <div>
              <label>Country *</label>
              <select name="country" required>
                <option value="">Select Country</option>
                <option value="Afghanistan" <?= ($emp['country'] ?? '') === 'Afghanistan' ? 'selected' : '' ?>>Afghanistan</option>
                <option value="Albania" <?= ($emp['country'] ?? '') === 'Albania' ? 'selected' : '' ?>>Albania</option>
                <option value="Algeria" <?= ($emp['country'] ?? '') === 'Algeria' ? 'selected' : '' ?>>Algeria</option>
                <option value="Argentina" <?= ($emp['country'] ?? '') === 'Argentina' ? 'selected' : '' ?>>Argentina</option>
                <option value="Australia" <?= ($emp['country'] ?? '') === 'Australia' ? 'selected' : '' ?>>Australia</option>
                <option value="Austria" <?= ($emp['country'] ?? '') === 'Austria' ? 'selected' : '' ?>>Austria</option>
                <option value="Bangladesh" <?= ($emp['country'] ?? '') === 'Bangladesh' ? 'selected' : '' ?>>Bangladesh</option>
                <option value="Belgium" <?= ($emp['country'] ?? '') === 'Belgium' ? 'selected' : '' ?>>Belgium</option>
                <option value="Brazil" <?= ($emp['country'] ?? '') === 'Brazil' ? 'selected' : '' ?>>Brazil</option>
                <option value="Canada" <?= ($emp['country'] ?? '') === 'Canada' ? 'selected' : '' ?>>Canada</option>
                <option value="China" <?= ($emp['country'] ?? '') === 'China' ? 'selected' : '' ?>>China</option>
                <option value="Colombia" <?= ($emp['country'] ?? '') === 'Colombia' ? 'selected' : '' ?>>Colombia</option>
                <option value="Denmark" <?= ($emp['country'] ?? '') === 'Denmark' ? 'selected' : '' ?>>Denmark</option>
                <option value="Egypt" <?= ($emp['country'] ?? '') === 'Egypt' ? 'selected' : '' ?>>Egypt</option>
                <option value="Finland" <?= ($emp['country'] ?? '') === 'Finland' ? 'selected' : '' ?>>Finland</option>
                <option value="France" <?= ($emp['country'] ?? '') === 'France' ? 'selected' : '' ?>>France</option>
                <option value="Germany" <?= ($emp['country'] ?? '') === 'Germany' ? 'selected' : '' ?>>Germany</option>
                <option value="Greece" <?= ($emp['country'] ?? '') === 'Greece' ? 'selected' : '' ?>>Greece</option>
                <option value="India" <?= ($emp['country'] ?? '') === 'India' ? 'selected' : '' ?>>India</option>
                <option value="Indonesia" <?= ($emp['country'] ?? '') === 'Indonesia' ? 'selected' : '' ?>>Indonesia</option>
                <option value="Ireland" <?= ($emp['country'] ?? '') === 'Ireland' ? 'selected' : '' ?>>Ireland</option>
                <option value="Italy" <?= ($emp['country'] ?? '') === 'Italy' ? 'selected' : '' ?>>Italy</option>
                <option value="Japan" <?= ($emp['country'] ?? '') === 'Japan' ? 'selected' : '' ?>>Japan</option>
                <option value="Malaysia" <?= ($emp['country'] ?? '') === 'Malaysia' ? 'selected' : '' ?>>Malaysia</option>
                <option value="Mexico" <?= ($emp['country'] ?? '') === 'Mexico' ? 'selected' : '' ?>>Mexico</option>
                <option value="Netherlands" <?= ($emp['country'] ?? '') === 'Netherlands' ? 'selected' : '' ?>>Netherlands</option>
                <option value="New Zealand" <?= ($emp['country'] ?? '') === 'New Zealand' ? 'selected' : '' ?>>New Zealand</option>
                <option value="Norway" <?= ($emp['country'] ?? '') === 'Norway' ? 'selected' : '' ?>>Norway</option>
                <option value="Pakistan" <?= ($emp['country'] ?? '') === 'Pakistan' ? 'selected' : '' ?>>Pakistan</option>
                <option value="Philippines" <?= ($emp['country'] ?? '') === 'Philippines' ? 'selected' : '' ?>>Philippines</option>
                <option value="Poland" <?= ($emp['country'] ?? '') === 'Poland' ? 'selected' : '' ?>>Poland</option>
                <option value="Portugal" <?= ($emp['country'] ?? '') === 'Portugal' ? 'selected' : '' ?>>Portugal</option>
                <option value="Russia" <?= ($emp['country'] ?? '') === 'Russia' ? 'selected' : '' ?>>Russia</option>
                <option value="Saudi Arabia" <?= ($emp['country'] ?? '') === 'Saudi Arabia' ? 'selected' : '' ?>>Saudi Arabia</option>
                <option value="Singapore" <?= ($emp['country'] ?? '') === 'Singapore' ? 'selected' : '' ?>>Singapore</option>
                <option value="South Africa" <?= ($emp['country'] ?? '') === 'South Africa' ? 'selected' : '' ?>>South Africa</option>
                <option value="South Korea" <?= ($emp['country'] ?? '') === 'South Korea' ? 'selected' : '' ?>>South Korea</option>
                <option value="Spain" <?= ($emp['country'] ?? '') === 'Spain' ? 'selected' : '' ?>>Spain</option>
                <option value="Sweden" <?= ($emp['country'] ?? '') === 'Sweden' ? 'selected' : '' ?>>Sweden</option>
                <option value="Switzerland" <?= ($emp['country'] ?? '') === 'Switzerland' ? 'selected' : '' ?>>Switzerland</option>
                <option value="Thailand" <?= ($emp['country'] ?? '') === 'Thailand' ? 'selected' : '' ?>>Thailand</option>
                <option value="Turkey" <?= ($emp['country'] ?? '') === 'Turkey' ? 'selected' : '' ?>>Turkey</option>
                <option value="United Arab Emirates" <?= ($emp['country'] ?? '') === 'United Arab Emirates' ? 'selected' : '' ?>>United Arab Emirates</option>
                <option value="United Kingdom" <?= ($emp['country'] ?? '') === 'United Kingdom' ? 'selected' : '' ?>>United Kingdom</option>
                <option value="United States" <?= ($emp['country'] ?? '') === 'United States' ? 'selected' : '' ?>>United States</option>
                <option value="Vietnam" <?= ($emp['country'] ?? '') === 'Vietnam' ? 'selected' : '' ?>>Vietnam</option>
              </select>
            </div>

            <div>
              <label>Business Name / Company name *</label>
              <input name="company_name" value="<?= htmlspecialchars($emp['company_name'] ?? '') ?>" required>
            </div>

            <div class="full skills-section">
              <label>Skills You Typically Look For</label>
              <div class="hint">Select skills you commonly require from jobseekers</div>
              
              <div class="skills-container">
                <input type="text" class="skills-search" placeholder="🔍 Search skills..." id="empSkillsSearch">
                
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
                
                <div class="skills-grid" id="empSkillsContainer">
                  <?php 
                    foreach ($skillsByCategory as $category => $skills): 
                  ?>
                    <div class="category-section" data-category="<?= htmlspecialchars($category) ?>">
                      <div class="category-title"><?= htmlspecialchars($category) ?></div>
                      <div class="skills-row">
                        <?php foreach ($skills as $skill): ?>
                          <label class="skill-checkbox" data-skill-name="<?= strtolower($skill['name']) ?>" style="display:flex;align-items:center;gap:6px;cursor:pointer;margin:0;padding:2px 0;font-weight:600;font-size:13px;line-height:1.4;">
                            <input type="checkbox" name="required_skill_ids[]" value="<?= $skill['id'] ?>" <?= in_array($skill['id'], $currentRequiredSkills) ? 'checked' : '' ?> style="width:14px;height:14px;flex-shrink:0;cursor:pointer;accent-color:#1f6f82;margin:0;">
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

          <button class="btnsave" type="submit">Save Profile</button>
        </form>
      <?php else: ?>
        <div class="alert err">Unknown role. Please log out and register again.</div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Show toast notification if there's a success message
    <?php if ($success): ?>
    document.addEventListener('DOMContentLoaded', function() {
      showToast('<?= addslashes($success) ?>', 'success');
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
    const jsAvatarInput = document.getElementById('profile_picture_js_complete');
    const empAvatarInput = document.getElementById('profile_picture_emp_complete');
    
    if (jsAvatarInput) {
      jsAvatarInput.addEventListener('change', function(e) {
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
    }
    
    if (empAvatarInput) {
      empAvatarInput.addEventListener('change', function(e) {
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
    }

    // Resume file preview functionality for complete profile
    const resumeInputComplete = document.getElementById('resumeInputComplete');
    const newResumePreviewComplete = document.getElementById('newResumePreviewComplete');
    const newResumeFileNameComplete = document.getElementById('newResumeFileNameComplete');
    const currentResumeDisplayComplete = document.getElementById('currentResumeDisplayComplete');

    if (resumeInputComplete) {
      resumeInputComplete.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          // Show new resume preview
          newResumeFileNameComplete.textContent = file.name;
          newResumePreviewComplete.style.display = 'block';
          
          // Hide current resume display if it exists
          if (currentResumeDisplayComplete) {
            currentResumeDisplayComplete.style.display = 'none';
          }
        } else {
          // Hide new resume preview if no file selected
          newResumePreviewComplete.style.display = 'none';
          
          // Show current resume display again if it exists
          if (currentResumeDisplayComplete) {
            currentResumeDisplayComplete.style.display = 'block';
          }
        }
      });
    }

    // Skills organization functionality
    function initializeSkillsOrganization(searchId, tabsSelector, containerSelector) {
      const skillsSearch = document.getElementById(searchId);
      const skillsTabs = document.querySelectorAll(tabsSelector);
      const skillsContainer = document.getElementById(containerSelector);
      const categorySections = document.querySelectorAll(`#${containerSelector} .category-section`);

      if (!skillsSearch || !skillsContainer) return;

      // Search functionality
      skillsSearch.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const skillCheckboxes = skillsContainer.querySelectorAll('.skill-checkbox');
        
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
          const skillCheckboxes = skillsContainer.querySelectorAll('.skill-checkbox');
          skillCheckboxes.forEach(checkbox => {
            checkbox.style.display = 'flex';
          });
        });
      });
    }

    // Initialize skills organization for both jobseeker and employer
    document.addEventListener('DOMContentLoaded', function() {
      initializeSkillsOrganization('jsSkillsSearch', '.skills-tab', 'jsSkillsContainer');
      initializeSkillsOrganization('empSkillsSearch', '.skills-tab', 'empSkillsContainer');

      // Skill limit: max 10 per picker
      function applySkillLimit(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const LIMIT = 10;
        const msg = document.createElement('p');
        msg.style.cssText = 'color:#c0392b;font-size:13px;font-weight:600;margin:8px 0 0;display:none;';
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
      }

      applySkillLimit('jsSkillsContainer');
      applySkillLimit('empSkillsContainer');
    });
  </script>
</body>
</html>