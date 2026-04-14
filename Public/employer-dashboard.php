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
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/employer-dashboard.css">
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
      <button class="primary" id="btnSearchJobseekers">🔍 Search Jobseekers</button>
      <button class="primary" id="btnPostJob">📢 Post Job</button>
      <a href="#" class="nav-link" id="btnMessages" style="display: block; padding: 12px 20px; color: #3b82f6; text-decoration: none; border-radius: 8px; margin: 8px 0; background: #f1f5f9; text-align: center; font-weight: 600; position: relative;">
        💬 Messages
        <?php if ($unreadCount > 0): ?>
          <span style="position: absolute; top: 4px; right: 8px; background: #ef4444; color: white; border-radius: 10px; padding: 2px 6px; font-size: 12px; font-weight: bold;"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>

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
              <input type="file" id="profile_picture_emp" name="profile_picture" accept="image/*">
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
      <h2>📢 Post a Job</h2>
      <p style="color: var(--muted); margin-bottom: 20px;">
        Create a job posting to attract qualified candidates. Your job will be visible to all job seekers on the platform.
      </p>

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
              <option value="United States">United States</option>
              <option value="United Kingdom">United Kingdom</option>
              <option value="Canada">Canada</option>
              <option value="Australia">Australia</option>
              <option value="Germany">Germany</option>
              <option value="France">France</option>
              <option value="Netherlands">Netherlands</option>
              <option value="Singapore">Singapore</option>
              <option value="India">India</option>
              <option value="Philippines">Philippines</option>
              <option value="Other">Other</option>
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
                      <div class="skill-checkbox" data-skill-name="<?= strtolower($skill['name']) ?>">
                        <input type="checkbox" id="job_skill_<?= $skill['id'] ?>" name="skill_ids[]" value="<?= $skill['id'] ?>">
                        <label for="job_skill_<?= $skill['id'] ?>" style="margin:0; font-weight:600;"><?= htmlspecialchars($skill['name']) ?></label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:20px; justify-content:flex-end;">
          <button type="submit" class="btn primary" id="submitJobPost">📢 Post Job</button>
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

    <!-- MESSAGING PANEL -->
    <div class="messaging-panel" id="messagingPanel" style="display: none;">
      <div class="messaging-header">
        <h3>💬 Messages</h3>
        <button class="close-btn" id="closeMessages">✕</button>
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
    // Disable buttons to prevent multiple clicks
    btnFindMatch.disabled = true;
    btnFindMatch2.disabled = true;
    btnFindMatch.textContent = '🔍 Searching...';
    btnFindMatch2.textContent = 'Searching...';

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
    searchContent.style.display = 'none';
    jobPostingContent.style.display = 'none';
    
    // Update active states
    btnHome.classList.add('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSearchJobseekers.classList.remove('active');
    btnPostJob.classList.remove('active');
    
    // Update title when showing dashboard
    document.querySelector('.title').textContent = 'Welcome back 👋';
    document.querySelector('.subtitle').textContent = 'Find and connect with qualified jobseekers through skill-based matching.';
  }

  function showProfileEdit() {
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
    
    // Update title when showing edit form
    document.querySelector('.title').textContent = 'Edit Your Profile';
    document.querySelector('.subtitle').textContent = 'Update your company information and skill requirements for better matching.';
  }

  function showSearch() {
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'none';
    searchContent.style.display = 'block';
    jobPostingContent.style.display = 'none';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSearchJobseekers.classList.add('active');
    btnPostJob.classList.remove('active');
    
    // Update title when showing search
    document.querySelector('.title').textContent = 'Search Job Seekers';
    document.querySelector('.subtitle').textContent = 'Find qualified candidates by searching their names, job roles, or skills.';
    
    // Focus on search input
    document.getElementById('searchInput').focus();
  }

  function showJobPosting() {
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

  btnFindMatch.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    findJobseeker();
  });
  
  btnFindMatch2.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    findJobseeker();
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
  
  btnEditPreferences.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showPreferencesModal();
  });
  
  btnSearchJobseekers.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showSearch();
  });

  btnPostJob.addEventListener('click', function() {
    // Close messaging panel if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    showJobPosting();
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

  btnCancelJobPost.addEventListener('click', function() {
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
        submitButton.textContent = '📢 Post Job';
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

  // Edit job post
  function editJob(jobId) {
    // Find the job data
    const jobData = currentJobPosts.find(job => job.id === jobId);
    if (!jobData) {
      showToast('Job not found', 'error');
      return;
    }
    
    // Populate the form with existing data
    document.getElementById('job_title').value = jobData.title || '';
    document.getElementById('job_description').value = jobData.description || '';
    document.getElementById('job_role_title').value = jobData.role_title || '';
    document.getElementById('job_employment_type').value = jobData.employment_type || '';
    document.getElementById('job_country').value = jobData.country || '';
    document.getElementById('job_rate_per_hour').value = jobData.rate_per_hour || '';
    document.getElementById('job_hours_per_week').value = jobData.hours_per_week || '';
    
    // Check the skills
    document.querySelectorAll('input[name="skill_ids[]"]').forEach(checkbox => {
      checkbox.checked = jobData.skills.some(skill => skill.id == checkbox.value);
    });
    
    // Change form to edit mode
    document.getElementById('submitJobPost').textContent = '💾 Update Job';
    document.getElementById('submitJobPost').setAttribute('data-edit-id', jobId);
    
    // Show the job posting section
    showJobPostingContent();
    
    // Scroll to form
    document.getElementById('jobPostingContent').scrollIntoView({ behavior: 'smooth' });
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
        submitBtn.textContent = '📢 Post Job';
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
        submitBtn.textContent = '📢 Post Job';
      }
    }
  });

  // Cancel job posting
  document.getElementById('btnCancelJobPost').addEventListener('click', function() {
    // Reset form
    document.getElementById('jobPostingForm').reset();
    document.getElementById('submitJobPost').textContent = '📢 Post Job';
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
      <div class="search-result-item">
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
        <div class="search-result-actions">
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
      messagingPanel.style.display = 'flex';
      
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
  e.stopPropagation();
  console.log('Messages button clicked');
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
  
  try {
    const fd = new FormData();
    fd.append('conversation_id', conversationId);
    
    console.log('Deleting conversation:', conversationId);
    const res = await fetch('/QuickHire/Public/actions/delete_conversation.php', { method: 'POST', body: fd });
    console.log('Delete response status:', res.status);
    
    const data = await res.json();
    console.log('Delete response data:', data);
    
    if (data.ok) {
      if (currentConversationId === conversationId) {
        currentConversationId = null;
        document.getElementById('chatArea').style.display = 'none';
      }
      await loadConversations();
      alert('Conversation deleted successfully');
    } else {
      console.error('Delete failed:', data.error);
      alert('Failed to delete conversation: ' + data.error);
    }
  } catch (error) {
    console.error('Delete conversation error:', error);
    alert('Error deleting conversation: ' + error.message);
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
  if (messagingPanel.style.display === 'flex') {
    loadConversations();
  }
}, 10000);

// Initial activity update
fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' });
</script>

</body>
</html>



