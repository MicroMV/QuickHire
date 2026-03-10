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

$config = require __DIR__ . '/../config/config.php';
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
  <link rel="stylesheet" href="assets/css/landingPage.css">

  <style>
    .wrap{max-width:900px;margin:40px auto;padding:0 18px;font-family:Inter,system-ui,Arial;}
    .card{background:#fff;border:1px solid #eee;border-radius:16px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,.06);}
    .h{font-size:28px;margin:0 0 8px;font-weight:900}
    .sub{color:#555;margin:0 0 18px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .full{grid-column:1/-1}
    label{display:block;font-weight:700;margin:10px 0 6px}
    input,select,textarea{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:12px}
    textarea{min-height:110px;resize:vertical}
    .btnsave{margin-top:16px;background:#1f6f82;color:#fff;border:0;border-radius:14px;padding:12px 18px;font-weight:900;cursor:pointer}
    .alert{padding:12px 14px;border-radius:12px;margin:0 0 14px;font-weight:800}
    .alert.err{background:#ffe1e1;color:#7a0b0b}
    .alert.ok{background:#e6ffef;color:#0c5a2a}
    .hint{font-size:12px;color:#666;margin-top:6px}
    
    /* Skills organization */
    .skills-container { margin-top: 10px; }
    .skills-search { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; font-size: 14px; }
    .skills-tabs { display: flex; gap: 5px; margin-bottom: 10px; flex-wrap: wrap; }
    .skills-tab { padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px; background: #fff; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s; }
    .skills-tab.active { background: #1f6f82; color: white; border-color: #1f6f82; }
    .skills-tab:hover { border-color: #1f6f82; }
    .category-section { margin-bottom: 15px; }
    .category-title { font-weight: 800; color: #1f6f82; margin-bottom: 8px; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 3px; }
    .skills-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
    .skill-checkbox { display: flex; align-items: center; gap: 8px; }
    .skill-checkbox input { width: 18px; height: 18px; cursor: pointer; }
    .skill-checkbox label { margin: 0; font-weight: 600; cursor: pointer; font-size: 14px; }
    
    /* Skills grid styling - keep for backward compatibility */
    .skills-section{margin-top:20px;}
    .skills-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:12px;margin-top:10px;max-height:300px;overflow-y:auto;border:1px solid #ddd;border-radius:12px;padding:16px;}
    .skill-item{display:flex;align-items:center;gap:8px;padding:6px 0;}
    .skill-item input[type="checkbox"]{width:18px;height:18px;cursor:pointer;}
    .skill-item label{margin:0;font-weight:600;cursor:pointer;font-size:14px;}
    .category-header{font-weight:900;color:#1f6f82;margin:12px 0 8px;border-bottom:1px solid #eee;padding-bottom:4px;grid-column:1/-1;}
    
    /* Toast notification styles */
    .toast {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%) translateY(-100px);
      background: #4ade80;
      color: white;
      padding: 16px 20px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      font-weight: 700;
      z-index: 1000;
      opacity: 0;
      transition: all 0.3s ease-in-out;
      max-width: 400px;
      text-align: center;
    }
    
    .toast.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }
    
    .toast.error {
      background: #ef4444;
    }
    
    .avatar-upload {
      position: relative;
      width: 120px;
      height: 120px;
      margin: 0 auto 20px;
      cursor: pointer;
    }
    
    .avatar-preview {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: #eaf3f5;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 900;
      color: #1f6f82;
      font-size: 48px;
      overflow: hidden;
      border: 3px solid #ddd;
      transition: all 0.3s ease;
    }
    
    .avatar-preview:hover {
      border-color: #1f6f82;
      transform: scale(1.05);
    }
    
    .avatar-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .avatar-overlay {
      position: absolute;
      bottom: 0;
      right: 0;
      background: #1f6f82;
      color: white;
      border-radius: 50%;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      border: 3px solid white;
      cursor: pointer;
    }
    
    .avatar-upload input[type="file"] {
      display: none !important;
      visibility: hidden !important;
      position: absolute !important;
      left: -9999px !important;
      width: 0 !important;
      height: 0 !important;
      opacity: 0 !important;
      z-index: -1 !important;
    }
    
    /* Hide any file input related elements */
    .avatar-upload input[type="file"]::-webkit-file-upload-button {
      display: none !important;
    }
    
    .avatar-upload input[type="file"]::file-selector-button {
      display: none !important;
    }
    
    /* Hide any browser-generated file input text within avatar upload */
    .avatar-upload input[type="file"]::before,
    .avatar-upload input[type="file"]::after {
      display: none !important;
      content: none !important;
    }
    
    /* Ensure avatar upload container doesn't show any text */
    .avatar-upload::after {
      content: none !important;
    }
    
    @media (max-width:720px){.grid{grid-template-columns:1fr}.skills-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
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
                    <img src="<?= htmlspecialchars($js['profile_picture_url']) ?>" alt="Profile Picture">
                  <?php else: ?>
                    <?= strtoupper(substr($userInfo['first_name'] ?? 'U', 0, 1)) ?>
                  <?php endif; ?>
                </div>
                <div class="avatar-overlay">✏️</div>
              </div>
              <div style="text-align: center; margin-top: 10px; font-weight: 700; color: #333;">
                <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
              </div>
              <input type="file" id="profile_picture_js_complete" name="profile_picture" accept="image/*">
            </div>

            <div>
              <label>Desired Job Role *</label>
              <input name="role_title" value="<?= htmlspecialchars($js['role_title'] ?? '') ?>" required>
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
              <input name="country" value="<?= htmlspecialchars($js['country'] ?? '') ?>" required>
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
                          <div class="skill-checkbox" data-skill-name="<?= strtolower($skill['name']) ?>">
                            <input type="checkbox" id="js_skill_<?= $skill['id'] ?>" name="skill_ids[]" value="<?= $skill['id'] ?>" 
                                   <?= in_array($skill['id'], $currentSkills) ? 'checked' : '' ?>>
                            <label for="js_skill_<?= $skill['id'] ?>"><?= htmlspecialchars($skill['name']) ?></label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <div>
              <label>Bachelor's Degree</label>
              <input name="bachelors_degree" value="<?= htmlspecialchars($js['bachelors_degree'] ?? '') ?>">
            </div>

            <div>
              <label>Portfolio/Website</label>
              <input name="portfolio_url" value="<?= htmlspecialchars($js['portfolio_url'] ?? '') ?>">
            </div>

            <div>
              <label>Age</label>
              <input name="age" type="number" value="<?= htmlspecialchars($js['age'] ?? '') ?>">
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
                    <img src="<?= htmlspecialchars($emp['profile_picture_url']) ?>" alt="Profile Picture">
                  <?php else: ?>
                    <?= strtoupper(substr($userInfo['first_name'] ?? 'E', 0, 1)) ?>
                  <?php endif; ?>
                </div>
                <div class="avatar-overlay">✏️</div>
              </div>
              <div style="text-align: center; margin-top: 10px; font-weight: 700; color: #333;">
                <?= htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '')) ?>
              </div>
              <input type="file" id="profile_picture_emp_complete" name="profile_picture" accept="image/*">
            </div>

            <div>
              <label>Country *</label>
              <input name="country" value="<?= htmlspecialchars($emp['country'] ?? '') ?>" required>
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
                          <div class="skill-checkbox" data-skill-name="<?= strtolower($skill['name']) ?>">
                            <input type="checkbox" id="emp_skill_<?= $skill['id'] ?>" name="required_skill_ids[]" value="<?= $skill['id'] ?>" 
                                   <?= in_array($skill['id'], $currentRequiredSkills) ? 'checked' : '' ?>>
                            <label for="emp_skill_<?= $skill['id'] ?>"><?= htmlspecialchars($skill['name']) ?></label>
                          </div>
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
    });
  </script>
</body>
</html>