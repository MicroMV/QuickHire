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

    <!-- Job Browsing Content (Hidden by default) -->
    <div class="card" id="jobBrowsingContent" style="display:none; max-width: none; width: 100%;">
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

    <!-- Profile Edit Form (Hidden by default) -->
    <div class="card" id="profileEditContent" style="display:none; max-width: none; width: 100%;">
      <h2>✏️ Edit Profile</h2>
      <p style="color: var(--muted); margin-bottom: 20px;">Update your profile to attract the right employers.</p>

      <form method="POST" action="/QuickHire/Public/actions/save_profile.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Rongie\QuickHire\Core\Csrf::token()) ?>">
        <input type="hidden" name="profile_type" value="JOBSEEKER">

        <div class="grid">
          <!-- Avatar -->
          <div style="grid-column:1/-1; display:flex; flex-direction:column; align-items:center;">
            <div class="avatar-upload" onclick="document.getElementById('profile_picture_js').click()" style="cursor:pointer;">
              <div class="avatar-preview" style="width:100px;height:100px;border-radius:50%;overflow:hidden;background:#64748b;display:flex;align-items:center;justify-content:center;color:white;font-size:36px;font-weight:bold;">
                <?php if (!empty($profile['profile_picture_url'])): ?>
                  <img src="/QuickHire/Public/<?= htmlspecialchars($profile['profile_picture_url']) ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                  <?= strtoupper(substr($userInfo['first_name'] ?? 'J', 0, 1)) ?>
                <?php endif; ?>
              </div>
              <input type="file" id="profile_picture_js" name="profile_picture" accept="image/*" style="display:none;">
            </div>
            <div style="margin-top:8px; font-size:13px; color:var(--muted);">Click to change photo</div>
          </div>

          <!-- First / Last Name -->
          <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">First Name</label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($userInfo['first_name'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
          </div>
          <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">Last Name</label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($userInfo['last_name'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
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
              <?php foreach (['Native','Fluent','Advanced','Intermediate','Basic'] as $level): ?>
                <option value="<?= $level ?>" <?= ($profile['english_mastery'] ?? '') === $level ? 'selected' : '' ?>><?= $level ?></option>
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
              <?php foreach (['Male','Female','Other','Prefer not to say'] as $g): ?>
                <option value="<?= $g ?>" <?= ($profile['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Bachelor's Degree -->
          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:700; margin-bottom:6px;">Bachelor's Degree</label>
            <input type="text" name="bachelors_degree" value="<?= htmlspecialchars($profile['bachelors_degree'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;" placeholder="e.g. Computer Science">
          </div>

          <!-- Portfolio URL -->
          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:700; margin-bottom:6px;">Portfolio URL</label>
            <input type="url" name="portfolio_url" value="<?= htmlspecialchars($profile['portfolio_url'] ?? '') ?>" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;" placeholder="https://yourportfolio.com">
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
              <input type="text" class="skills-search" placeholder="🔍 Search skills..." id="skillsSearch" style="width:100%; padding:8px 12px; border:1px solid var(--line); border-radius:8px; margin-bottom:10px;">
              <div class="skills-tabs" style="margin-bottom:10px;">
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
                          <input type="checkbox" id="skill_<?= $skill['id'] ?>" name="skill_ids[]" value="<?= $skill['id'] ?>" <?= in_array($skill['id'], $currentSkills) ? 'checked' : '' ?>>
                          <label for="skill_<?= $skill['id'] ?>" style="margin:0; font-weight:600;"><?= htmlspecialchars($skill['name']) ?></label>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Resume -->
          <div style="grid-column:1/-1;">
            <label style="display:block; font-weight:700; margin-bottom:6px;">Resume</label>
            <input type="file" name="resume" accept=".pdf,.doc,.docx" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
            <?php if (!empty($profile['resume_url'])): ?>
              <div style="margin-top:6px; font-size:12px; color:var(--muted);">
                Current: <a href="/QuickHire/Public/<?= htmlspecialchars($profile['resume_url']) ?>" target="_blank" style="color:var(--primary);">View Resume</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div style="margin-top:24px; display:flex; gap:10px; justify-content:flex-end;">
          <button type="submit" class="btn primary">Save Profile</button>
          <button type="button" class="btn outline" id="btnCancelEdit">Cancel</button>
        </div>
      </form>
    </div>
  </main>

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
</div>

</body>

<script>
  // Basic functionality
  const btnFindEmployer = document.getElementById('btnFindEmployer');
  const btnFindEmployer2 = document.getElementById('btnFindEmployer2');
  const btnHome = document.getElementById('btnHome');
  const btnEditProfile = document.getElementById('btnEditProfile');
  const btnEditProfile2 = document.getElementById('btnEditProfile2');
  const btnBrowseJobs = document.getElementById('btnBrowseJobs');
  
  const dashboardContent = document.getElementById('dashboardContent');
  const jobBrowsingContent = document.getElementById('jobBrowsingContent');
  const profileEditContent = document.getElementById('profileEditContent');
  const btnCancelEdit = document.getElementById('btnCancelEdit');

  // Job Browsing Functionality
  let currentJobOffset = 0;
  const jobsPerPage = 10;
  let allJobsLoaded = false;
  let currentJobs = []; // Store current jobs for detail view

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

  // Clean up empty conversations - COMPLETELY DISABLED
  async function cleanupEmptyConversation() {
    // NEVER clean up conversations - keep all conversations permanently
    if (window.pendingConversation) {
      console.log('Keeping all conversations - no cleanup performed:', window.pendingConversation);
      window.pendingConversation = null;
    }
  }

  function showDashboard() {
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    
    dashboardContent.style.display = 'grid';
    jobBrowsingContent.style.display = 'none';
    if (profileEditContent) profileEditContent.style.display = 'none';
    
    btnHome.classList.add('active');
    btnBrowseJobs.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    
    document.querySelector('.title').textContent = 'Welcome back 👋';
    document.querySelector('.subtitle').textContent = 'Click "Find Employer" to automatically connect with employers who are currently looking for candidates like you.';
  }

  function showJobBrowsing() {
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    
    dashboardContent.style.display = 'none';
    jobBrowsingContent.style.display = 'block';
    if (profileEditContent) profileEditContent.style.display = 'none';
    
    btnHome.classList.remove('active');
    btnBrowseJobs.classList.add('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    
    document.querySelector('.title').textContent = 'Browse Jobs';
    document.querySelector('.subtitle').textContent = 'Discover job opportunities from employers looking for candidates like you.';
    
    loadJobListings();
  }

  function showProfileEdit() {
    // Close messaging if open
    if (messagingPanel && messagingPanel.style.display !== 'none') {
      messagingPanel.style.display = 'none';
      currentConversationId = null;
      if (document.getElementById('messageInputArea')) {
        document.getElementById('messageInputArea').style.display = 'none';
      }
    }
    
    dashboardContent.style.display = 'none';
    jobBrowsingContent.style.display = 'none';
    if (profileEditContent) profileEditContent.style.display = 'block';
    
    // Update active states
    btnHome.classList.remove('active');
    btnBrowseJobs.classList.remove('active');
    btnEditProfile.classList.add('active');
    btnEditProfile2.classList.add('active');
    
    document.querySelector('.title').textContent = 'Edit Profile ✏️';
    document.querySelector('.subtitle').textContent = 'Update your profile information to attract the right employers.';
  }

  // Initialize with Home active
  btnHome.classList.add('active');

  // Event listeners
  btnFindEmployer.addEventListener('click', findEmployer);
  btnFindEmployer2.addEventListener('click', findEmployer);
  btnHome.addEventListener('click', showDashboard);
  btnBrowseJobs.addEventListener('click', showJobBrowsing);
  
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
      const response = await fetch(`/QuickHire/Public/actions/get_job_posts.php?limit=${jobsPerPage}&offset=${currentJobOffset}`);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const result = await response.json();
      
      if (result.ok) {
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
    console.log('generateJobListingsHTML called with jobs:', jobs);
    currentJobs = jobs; // Store jobs for detail view
    let html = '';
    
    jobs.forEach((job, index) => {
      console.log(`Processing job ${index}:`, job.title);
      
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
      
      console.log(`Job ${index} description length:`, job.description.length, 'Show READ MORE:', job.description.length > 150);
      
      html += `
        <div class="job-card" data-job-index="${index}" onclick="showJobDetail(${index})" style="cursor: pointer;">
          <div class="job-card-header">
            <div class="job-card-company">
              <div class="company-avatar">${employerAvatarHtml}</div>
              <div class="company-info">
                <div class="company-name">${job.company_name || (job.employer_first_name + ' ' + job.employer_last_name)}</div>
                <div class="company-location">${job.country || job.employer_country || 'Location not specified'}</div>
              </div>
            </div>
            <div class="job-card-date">${new Date(job.created_at).toLocaleDateString()}</div>
          </div>
          
          <div class="job-card-title">${job.title}</div>
          
          <div class="job-card-meta">
            ${job.role_title ? `<span class="meta-tag">💼 ${job.role_title}</span>` : ''}
            ${job.employment_type ? `<span class="meta-tag">⏰ ${job.employment_type.replace('_', ' ')}</span>` : ''}
            ${rateDisplay ? `<span class="meta-tag">💰 ${rateDisplay}</span>` : ''}
            ${hoursDisplay ? `<span class="meta-tag">🕐 ${hoursDisplay}</span>` : ''}
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
    
    console.log('Generated HTML length:', html.length);
    return html;
  }

  // Show job detail view
  function showJobDetail(jobIndex) {
    console.log('showJobDetail called with index:', jobIndex);
    console.log('currentJobs:', currentJobs);
    
    const job = currentJobs[jobIndex];
    if (!job) {
      console.error('Job not found at index:', jobIndex);
      return;
    }
    
    console.log('Showing job detail for:', job.title);
    
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
          <div class="company-avatar large">${employerAvatarHtml}</div>
          <div class="company-info">
            <div class="company-name">${job.company_name || (job.employer_first_name + ' ' + job.employer_last_name)}</div>
            <div class="company-location">${job.country || job.employer_country || 'Location not specified'}</div>
          </div>
        </div>
        
        <div class="job-detail-title">${job.title}</div>
        
        <div class="job-detail-meta">
          ${job.role_title ? `<span class="meta-tag">💼 ${job.role_title}</span>` : ''}
          ${job.employment_type ? `<span class="meta-tag">⏰ ${job.employment_type.replace('_', ' ')}</span>` : ''}
          ${rateDisplay ? `<span class="meta-tag">💰 ${rateDisplay}</span>` : ''}
          ${hoursDisplay ? `<span class="meta-tag">🕐 ${hoursDisplay}</span>` : ''}
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
          <button class="btn primary large" onclick="messageEmployerAboutJob(${job.employer_id}, ${job.id}, '${job.title.replace(/'/g, "\\'")}', this)">
            🚀 APPLY FOR THIS JOB
          </button>
        </div>
      </div>
    `;
  }

  // Show jobs list view
  function showJobsList() {
    console.log('showJobsList called');
    displayJobListings(currentJobs);
  }

  // Make functions globally available
  window.showJobDetail = showJobDetail;
  window.showJobsList = showJobsList;
  window.messageEmployerAboutJob = messageEmployerAboutJob;

  // Message employer about a job - direct to message input
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
      console.log('Starting conversation with employer:', employerId);
      
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
      console.log('Conversation response:', data);

      if (data.ok) {
        // Only store as pending if it's truly a NEW conversation with no messages
        if (!data.is_existing) {
          window.pendingConversation = {
            id: data.conversation_id,
            employerName: data.employer_name,
            jobTitle: jobTitle,
            isNew: true
          };
          console.log('Stored NEW pending conversation:', window.pendingConversation);
        } else {
          // Clear any pending conversation since this is an existing one
          window.pendingConversation = null;
          console.log('Existing conversation - no pending cleanup needed');
        }
        
        // Open messaging panel
        const messagingPanel = document.getElementById('messagingPanel');
        messagingPanel.style.display = 'flex';
        
        // Load conversations first
        console.log('Loading conversations...');
        await loadConversations();
        
        // Wait a bit for conversations to load
        await new Promise(resolve => setTimeout(resolve, 200));
        
        console.log('Current conversations after load:', currentConversations);
        
        // Find the conversation
        const conversation = currentConversations.find(conv => conv.id == data.conversation_id);
        console.log('Found conversation:', conversation);
        
        if (conversation) {
          console.log('Opening conversation directly...');
          await openConversation(conversation.id, `${conversation.other_first_name} ${conversation.other_last_name}`);
          
          // Focus on message input and add placeholder text
          setTimeout(() => {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
              messageInput.placeholder = `Write your message about "${jobTitle}"...`;
              messageInput.focus();
              console.log('Message input focused');
            }
          }, 300);
          
          showToast(`Ready to message ${data.employer_name} about "${jobTitle}"`, 'success');
        } else {
          console.log('Conversation not found immediately, trying with longer delay...');
          // Fallback - try to find conversation after longer delay
          setTimeout(async () => {
            await loadConversations();
            console.log('Retry - Current conversations:', currentConversations);
            
            const retryConversation = currentConversations.find(conv => conv.id == data.conversation_id);
            console.log('Retry - Found conversation:', retryConversation);
            
            if (retryConversation) {
              await openConversation(retryConversation.id, `${retryConversation.other_first_name} ${retryConversation.other_last_name}`);
              setTimeout(() => {
                const messageInput = document.getElementById('messageInput');
                if (messageInput) {
                  messageInput.placeholder = `Write your message about "${jobTitle}"...`;
                  messageInput.focus();
                }
              }, 300);
            } else {
              console.error('Could not find conversation even after retry');
              showToast('Conversation created but could not open. Please check your messages.', 'info');
            }
          }, 1000);
          
          showToast(`Ready to message about "${jobTitle}"`, 'success');
        }
        
      } else {
        showToast('Failed to start conversation: ' + data.error, 'error');
      }
    } catch (error) {
      console.error('Start conversation error:', error);
      showToast('Failed to start conversation: ' + error.message, 'error');
    } finally {
      button.disabled = false;
      button.textContent = '🚀 APPLY FOR THIS JOB';
    }
  }

  // Load more jobs button
  document.getElementById('loadMoreJobs').addEventListener('click', function() {
    if (!allJobsLoaded) {
      loadJobListings(false);
    }
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

  // Show messaging panel
  function showMessaging() {
    const panel = document.getElementById('messagingPanel');
    if (!panel) return;
    
    panel.style.display = 'flex';
    
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
    
    panel.style.display = 'none';
    
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
      console.log('Loading conversations...');
      const response = await fetch('/QuickHire/Public/actions/get_conversations.php');
      const data = await response.json();
      
      console.log('Conversations response:', data);
      
      if (data.ok) {
        currentConversations = data.conversations;
        displayConversations(data.conversations);
        console.log('Loaded', data.conversations.length, 'conversations');
      } else {
        console.error('Failed to load conversations:', data.error);
        document.getElementById('conversationsList').innerHTML = `
          <div class="empty-state">
            <h3>Error loading conversations</h3>
            <p>${data.error}</p>
          </div>
        `;
      }
    } catch (error) {
      console.error('Error loading conversations:', error);
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
      
      // Calculate active status
      let activeIndicator = '';
      let activeText = '';
      if (conv.other_last_active) {
        const lastActive = new Date(conv.other_last_active + 'Z'); // Add Z for UTC
        const now = new Date();
        const diffMinutes = Math.floor((now - lastActive) / (1000 * 60));
        
        if (diffMinutes <= 1) {
          activeIndicator = '<div class="active-dot"></div>';
          activeText = '<div style="font-size: 11px; color: #10b981; margin-top: 2px;">● Active now</div>';
        } else if (diffMinutes <= 5) {
          activeIndicator = `<div class="minute-badge">${diffMinutes}</div>`;
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
        <div class="conversation-item ${isActive ? 'active' : ''}" onclick="openConversation(${conv.id}, '${conv.other_first_name} ${conv.other_last_name}')">
          <div class="conversation-avatar" style="position: relative;">
            ${avatarHtml}
            ${activeIndicator}
          </div>
          <div class="conversation-info">
            <div class="conversation-name">${conv.other_first_name} ${conv.other_last_name}</div>
            ${activeText}
            <div class="conversation-preview">${previewText}</div>
          </div>
          ${unreadBadge}
        </div>
      `;
    });
    
    container.innerHTML = html;
  }

  // Open conversation
  async function openConversation(conversationId, participantName) {
    console.log('Opening conversation:', conversationId, 'with', participantName);
    currentConversationId = conversationId;
    
    // Update UI - keep conversations sidebar visible on desktop, only hide on mobile
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
      document.getElementById('conversationsList').style.display = 'none';
    }
    document.getElementById('chatArea').style.display = 'flex';
    document.getElementById('chatTitle').textContent = participantName;
    
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
        console.error('Failed to load messages:', data.error);
      }
    } catch (error) {
      console.error('Error loading messages:', error);
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
      console.error('Error sending message:', error);
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
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
  }

  // Close chat menu when clicking outside
  document.addEventListener('click', function(e) {
    const menu = document.getElementById('chatMenu');
    const menuBtn = e.target.closest('.menu-btn');
    if (!menuBtn && menu) {
      menu.style.display = 'none';
    }
  });

  // Delete conversation
  async function deleteCurrentConversation() {
    if (!currentConversationId) return;
    
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
        showToast('Conversation deleted successfully', 'success');
        showConversationsList();
        loadConversations();
      } else {
        showToast('Failed to delete conversation: ' + data.error, 'error');
      }
    } catch (error) {
      console.error('Error deleting conversation:', error);
      showToast('Failed to delete conversation', 'error');
    }
    
    // Hide menu
    document.getElementById('chatMenu').style.display = 'none';
  }

  // Add event listener for messages button
  document.getElementById('btnMessages').addEventListener('click', function(e) {
    e.preventDefault();
    console.log('Messages button clicked');
    showMessaging();
  });

  // Add event listener for close messages button
  document.getElementById('closeMessages').addEventListener('click', function(e) {
    e.preventDefault();
    console.log('Close messages button clicked');
    hideMessaging();
  });

  // Debug function to check messaging state
  window.debugMessaging = function() {
    console.log('Current conversation ID:', currentConversationId);
    console.log('Current conversations:', currentConversations);
    console.log('Messaging panel display:', document.getElementById('messagingPanel').style.display);
    console.log('Chat area display:', document.getElementById('chatArea').style.display);
    console.log('Conversations list display:', document.getElementById('conversationsList').style.display);
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
const updateActivity = () => {
  const now = Date.now();
  // Only send update if 5 seconds have passed since last update (throttle)
  if (now - lastActivityUpdate > 5000) {
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
  fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' })
    .then(r => r.json())
    .then(data => console.log('Fallback activity updated:', data))
    .catch(err => console.error('Fallback activity update failed:', err));
}, 30000);
</script>

</html>