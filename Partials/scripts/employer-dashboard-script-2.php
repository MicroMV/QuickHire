<script>
  // Initialize currentJobPosts at the top to prevent undefined errors
  window.currentJobPosts = [];
  
  // Debug function to check messaging panel state
  window.debugMessagingPanel = function() {
    const panel = document.getElementById('messagingPanel');
    console.log('Messaging Panel Debug:', {
      exists: !!panel,
      isOpen: panel ? panel.classList.contains('open') : false,
      zIndex: panel ? getComputedStyle(panel).zIndex : 'N/A',
      display: panel ? getComputedStyle(panel).display : 'N/A',
      pointerEvents: panel ? getComputedStyle(panel).pointerEvents : 'N/A'
    });
  };
  
  // Declare messaging panel variables in main scope
  const messagingPanel = document.getElementById('messagingPanel');
  const btnMessages = document.getElementById('btnMessages');
  
  // Close messaging panel function - declare in main scope
  function closeMessagingPanel() {
    if (!messagingPanel || !messagingPanel.classList.contains('open')) return;
    messagingPanel.classList.remove('open');
    window.currentConversationId = null;
    const inputArea = document.getElementById('messageInputArea');
    if (inputArea) inputArea.style.display = 'none';
    const menuBtn = document.getElementById('chatMenuBtn');
    if (menuBtn) menuBtn.style.display = 'none';
    // Call mobile cleanup if function exists
    if (typeof window._hideMessagingMobile === 'function') {
      window._hideMessagingMobile();
    }
  }
  
  const APP_BASE = <?= json_encode($publicBase) ?>;
  const assetUrl = (path) => `${APP_BASE}/${String(path || '').replace(/^\/+/, '')}`;
  const btnFindMatch = document.getElementById('btnFindMatch');
  const btnFindMatch2 = document.getElementById('btnFindMatch2');
  const btnHome = document.getElementById('btnHome');
  const btnEditProfile = document.getElementById('btnEditProfile');
  const btnEditProfile2 = document.getElementById('btnEditProfile2');
  const btnEditPreferences = document.getElementById('btnEditPreferences');
  const btnCancelEdit = document.getElementById('btnCancelEdit');
  const btnSearchJobseekers = document.getElementById('btnSearchJobseekers');
  const btnPostJob = document.getElementById('btnPostJob');
  const btnSettings = document.getElementById('btnSettings');
  const btnCancelJobPost = document.getElementById('btnCancelJobPost');

  function setEmployerMessagesNavActive() {
    localStorage.setItem('emp_active_page', 'home'); // don't restore messages on reload
    btnHome.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSearchJobseekers.classList.remove('active');
    btnPostJob.classList.remove('active');
    btnSettings.classList.remove('active');
    btnMessages.classList.add('active');
  }
  
  const dashboardContent = document.getElementById('dashboardContent');
  const profileEditContent = document.getElementById('profileEditContent');
  const settingsContent = document.getElementById('settingsContent');
  const searchContent = document.getElementById('searchContent');
  const jobPostingContent = document.getElementById('jobPostingContent');
  
  // Preferences modal elements
  const preferencesModal = document.getElementById('preferencesModal');
  const preferencesForm = document.getElementById('preferencesForm');
  const btnClosePreferences = document.getElementById('btnClosePreferences');
  const btnCancelPreferences = document.getElementById('btnCancelPreferences');
  const MATCHING_PREFS_KEY = 'matchingPreferences_' + <?= json_encode($userId) ?>;

  function isValidPreferences(preferences) {
    return !!preferences
      && String(preferences.role_title || '').trim() !== ''
      && String(preferences.country || '').trim() !== '';
  }

  function readStoredPreferences() {
    const prefs = localStorage.getItem(MATCHING_PREFS_KEY);
    if (!prefs) return null;

    try {
      const parsed = JSON.parse(prefs);
      if (!isValidPreferences(parsed)) {
        localStorage.removeItem(MATCHING_PREFS_KEY);
        return null;
      }
      return parsed;
    } catch (error) {
      localStorage.removeItem(MATCHING_PREFS_KEY);
      return null;
    }
  }

  // Check if this employer has usable saved preferences
  function hasPreferences() {
    return readStoredPreferences() !== null;
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
    }
    
    // Load other preferences from localStorage if available
    const parsed = readStoredPreferences();
    if (parsed) {
      preferences.role_title = parsed.role_title;
      preferences.country = parsed.country;
      preferences.employment_type = parsed.employment_type || 'FULL_TIME';
    }
    
    return preferences;
  }

  // Save preferences to localStorage and database (only skills to database)
  async function savePreferences(preferences) {
    // Save to localStorage for immediate use (role, country, employment_type)
    localStorage.setItem(MATCHING_PREFS_KEY, JSON.stringify({
      role_title: String(preferences.role_title || '').trim(),
      country: String(preferences.country || '').trim(),
      employment_type: preferences.employment_type || 'FULL_TIME',
      skill_ids: preferences.skill_ids || []
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
        }
      } catch (error) {
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
      document.querySelectorAll('#prefSkillsContainer input[name="skill_ids[]"]').forEach(cb => cb.checked = false);
      
      // Check saved skills
      if (prefs.skill_ids && prefs.skill_ids.length > 0) {
        prefs.skill_ids.forEach(skillId => {
          const checkbox = document.getElementById('pref_skill_' + skillId)
            || document.querySelector(`#prefSkillsContainer input[name="skill_ids[]"][value="${skillId}"]`);
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
    const preferences = await loadPreferences();

    // First time, stale browser data, or missing required fields - show preferences modal
    if (!hasPreferences() || !isValidPreferences(preferences)) {
      // First time - show preferences modal
      await showPreferencesModal();
      return;
    }

    await executeJobseekerSearch(preferences);
  }

  async function executeJobseekerSearch(preferences) {
    if (!isValidPreferences(preferences)) {
      await showPreferencesModal();
      return;
    }

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
        <div style="font-size:52px;margin-bottom:16px;">🤝</div>
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
    
    // Ensure messaging panel is properly closed
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      // Call mobile cleanup if function exists
      if (typeof window._hideMessagingMobile === 'function') {
        window._hideMessagingMobile();
      }
    }
    
    dashboardContent.style.display = 'grid';
    profileEditContent.style.display = 'none';
    settingsContent.style.display = 'none';
    searchContent.style.display = 'none';
    jobPostingContent.style.display = 'none';
    document.getElementById('jsProfileView').style.display = 'none';
    
    // Update active states
    btnHome.classList.add('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSettings.classList.remove('active');
    btnSearchJobseekers.classList.remove('active');
    btnPostJob.classList.remove('active');
    btnMessages.classList.remove('active');
    
    // Update title when showing dashboard
    document.querySelector('.title').textContent = 'Welcome back 👋';
    document.querySelector('.subtitle').textContent = 'Find and connect with qualified jobseekers through skill-based matching.';
  }

  function showProfileEdit() {
    localStorage.setItem('emp_active_page', 'edit');
    
    // Ensure messaging panel is properly closed
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      if (typeof window._hideMessagingMobile === 'function') {
        window._hideMessagingMobile();
      }
    }
    
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'block';
    settingsContent.style.display = 'none';
    searchContent.style.display = 'none';
    jobPostingContent.style.display = 'none';
    document.getElementById('jsProfileView').style.display = 'none';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.add('active');
    btnEditProfile2.classList.add('active');
    btnSettings.classList.remove('active');
    btnSearchJobseekers.classList.remove('active');
    btnPostJob.classList.remove('active');
    btnMessages.classList.remove('active');
    
    // Update title when showing edit form
    document.querySelector('.title').textContent = 'Edit Your Profile';
    document.querySelector('.subtitle').textContent = 'Update your company information and skill requirements for better matching.';
  }

  function showSearch() {
    localStorage.setItem('emp_active_page', 'search');
    
    // Ensure messaging panel is properly closed
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      if (typeof window._hideMessagingMobile === 'function') {
        window._hideMessagingMobile();
      }
    }
    
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'none';
    settingsContent.style.display = 'none';
    searchContent.style.display = 'block';
    jobPostingContent.style.display = 'none';
    document.getElementById('jsProfileView').style.display = 'none';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSettings.classList.remove('active');
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
    
    // Ensure messaging panel is properly closed
    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      if (typeof window._hideMessagingMobile === 'function') {
        window._hideMessagingMobile();
      }
    }
    
    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'none';
    settingsContent.style.display = 'none';
    searchContent.style.display = 'none';
    jobPostingContent.style.display = 'block';
    document.getElementById('jsProfileView').style.display = 'none';
    
    // Update active states
    btnHome.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSearchJobseekers.classList.remove('active');
    btnSettings.classList.remove('active');
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

  function showSettings() {
    localStorage.setItem('emp_active_page', 'settings');

    if (messagingPanel && messagingPanel.classList.contains('open')) {
      messagingPanel.classList.remove('open');
      if (typeof window._hideMessagingMobile === 'function') {
        window._hideMessagingMobile();
      }
    }

    dashboardContent.style.display = 'none';
    profileEditContent.style.display = 'none';
    settingsContent.style.display = 'block';
    searchContent.style.display = 'none';
    jobPostingContent.style.display = 'none';
    document.getElementById('jsProfileView').style.display = 'none';

    btnHome.classList.remove('active');
    btnEditProfile.classList.remove('active');
    btnEditProfile2.classList.remove('active');
    btnSearchJobseekers.classList.remove('active');
    btnPostJob.classList.remove('active');
    btnSettings.classList.add('active');
    btnMessages.classList.remove('active');

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
  const savedEmpPage = localStorage.getItem('emp_active_page');
  if (savedEmpPage === 'edit') showProfileEdit();
  else if (savedEmpPage === 'search') showSearch();
  else if (savedEmpPage === 'jobs') showJobPosting();
  else if (savedEmpPage === 'settings') showSettings();
  else showDashboard(); // messages always resets to home on reload

  btnFindMatch.addEventListener('click', function() {
    closeMessagingPanel();
    findJobseeker();
  });
  
  btnFindMatch2.addEventListener('click', function() {
    closeMessagingPanel();
    findJobseeker();
  });
  btnHome.addEventListener('click', function() {
    closeMessagingPanel();
    showDashboard();
  });
  
  btnEditProfile.addEventListener('click', function() {
    closeMessagingPanel();
    showProfileEdit();
  });
  
  btnEditProfile2.addEventListener('click', function() {
    closeMessagingPanel();
    showProfileEdit();
  });
  
  btnEditPreferences.addEventListener('click', function() {
    closeMessagingPanel();
    showPreferencesModal();
  });

  btnSettings.addEventListener('click', function() {
    closeMessagingPanel();
    showSettings();
  });
  
  btnSearchJobseekers.addEventListener('click', function() {
    closeMessagingPanel();
    showSearch();
  });

  window.showSearchJobseekers = showSearch;

  btnPostJob.addEventListener('click', function() {
    closeMessagingPanel();
    showJobPosting();
  });
  
  btnCancelEdit.addEventListener('click', function() {
    closeMessagingPanel();
    showDashboard();
  });

  btnCancelJobPost.addEventListener('click', function() {
    closeMessagingPanel();
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
      container.innerHTML = '<div class="empty-state">Error loading job posts</div>';
    }
  }

  // Display employer's job posts
  function displayMyJobPosts(jobPosts) {
    const container = document.getElementById('myJobPosts');
    jobPosts = Array.isArray(jobPosts) ? jobPosts : [];
    window.currentJobPosts = jobPosts; // Store for editing
    currentJobPosts = jobPosts;
    
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
            <span><span class="meta-label">Date:</span> ${new Date(job.created_at).toLocaleDateString()}</span>
            ${job.role_title ? `<span><span class="meta-label">Role:</span> ${job.role_title}</span>` : ''}
            ${job.employment_type ? `<span><span class="meta-label">Type:</span> ${job.employment_type.replace('_', ' ')}</span>` : ''}
            ${job.country ? `<span><span class="meta-label">Location:</span> ${job.country}</span>` : ''}
            ${rateDisplay ? `<span><span class="meta-label">Rate:</span> ${rateDisplay}</span>` : ''}${hoursDisplay ? `<span><span class="meta-label">Hours:</span> ${hoursDisplay}</span>` : ''}
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

  // Edit job post  opens modal
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
      formData.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');
      
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
      showToast('Error deleting job post', 'error');
    }
  }

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
        showToast(result.message || 'Job posted successfully!', 'success');
        
        // Reset form
        document.getElementById('jobPostingForm').reset();
        submitBtn.textContent = 'Post Job';
        submitBtn.removeAttribute('data-edit-id');
        
        // Reload job posts list below the form
        await loadMyJobPosts();
      } else {
        showToast('Error: ' + result.error, 'error');
      }
    } catch (error) {
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
    
    // Go back to dashboard
    showDashboard();
  });


</script>
