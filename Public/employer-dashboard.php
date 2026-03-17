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

$config = require __DIR__ . '/../Config/config.php';
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
              <option value="Philippines" <?= ($profile['country'] ?? '') === 'Philippines' ? 'selected' : '' ?>>Philippines</option>
              <option value="United States" <?= ($profile['country'] ?? '') === 'United States' ? 'selected' : '' ?>>United States</option>
              <option value="United Kingdom" <?= ($profile['country'] ?? '') === 'United Kingdom' ? 'selected' : '' ?>>United Kingdom</option>
              <option value="Canada" <?= ($profile['country'] ?? '') === 'Canada' ? 'selected' : '' ?>>Canada</option>
              <option value="Australia" <?= ($profile['country'] ?? '') === 'Australia' ? 'selected' : '' ?>>Australia</option>
              <option value="India" <?= ($profile['country'] ?? '') === 'India' ? 'selected' : '' ?>>India</option>
              <option value="Singapore" <?= ($profile['country'] ?? '') === 'Singapore' ? 'selected' : '' ?>>Singapore</option>
              <option value="Malaysia" <?= ($profile['country'] ?? '') === 'Malaysia' ? 'selected' : '' ?>>Malaysia</option>
              <option value="Germany" <?= ($profile['country'] ?? '') === 'Germany' ? 'selected' : '' ?>>Germany</option>
              <option value="France" <?= ($profile['country'] ?? '') === 'France' ? 'selected' : '' ?>>France</option>
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
              <option value="Philippines">Philippines</option>
              <option value="United States">United States</option>
              <option value="United Kingdom">United Kingdom</option>
              <option value="Canada">Canada</option>
              <option value="Australia">Australia</option>
              <option value="India">India</option>
              <option value="Singapore">Singapore</option>
              <option value="Malaysia">Malaysia</option>
              <option value="Germany">Germany</option>
              <option value="France">France</option>
              <option value="Japan">Japan</option>
              <option value="China">China</option>
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

</body>
</html>
