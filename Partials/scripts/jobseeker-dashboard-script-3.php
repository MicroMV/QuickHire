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

  const waitingOverlay = document.getElementById('jobseekerWaitingOverlay');
  const waitingTimeEl = document.getElementById('jsWaitingTime');
  const waitingProgressEl = document.getElementById('jsWaitingProgress');
  const waitingCopyEl = document.getElementById('jsWaitingCopy');
  const btnStopWaiting = document.getElementById('btnStopWaiting');
  const WAITING_LIMIT_SECONDS = 180;
  let waitingTimer = null;
  let waitingPoller = null;
  let waitingEndsAt = 0;
  let waitingActive = false;
  let waitingRequestRunning = false;

  function setFindEmployerButtons(isWaiting) {
    btnFindEmployer.disabled = isWaiting;
    btnFindEmployer2.disabled = isWaiting;
    btnFindEmployer.textContent = isWaiting ? 'Waiting...' : '🔍 Find Employer';
    btnFindEmployer2.textContent = isWaiting ? 'Waiting...' : 'Find Employer';
  }

  function formatWaitingTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
  }

  function updateWaitingCountdown() {
    const remaining = Math.max(0, Math.ceil((waitingEndsAt - Date.now()) / 1000));
    waitingTimeEl.textContent = formatWaitingTime(remaining);
    waitingProgressEl.style.width = ((remaining / WAITING_LIMIT_SECONDS) * 100) + '%';

    if (remaining <= 0) {
      stopWaiting('No employer matched within 3 minutes. You can try again anytime.', 'info');
    }
  }

  function showWaitingScreen() {
    waitingActive = true;
    waitingEndsAt = Date.now() + WAITING_LIMIT_SECONDS * 1000;
    waitingCopyEl.textContent = 'Keep this screen open while QuickHire looks for an employer who matches your profile.';
    waitingOverlay.classList.add('active');
    waitingOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    setFindEmployerButtons(true);
    updateWaitingCountdown();
  }

  function stopWaiting(message = '', type = 'info') {
    waitingActive = false;
    waitingRequestRunning = false;
    clearInterval(waitingTimer);
    clearInterval(waitingPoller);
    waitingTimer = null;
    waitingPoller = null;
    waitingOverlay.classList.remove('active');
    waitingOverlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    setFindEmployerButtons(false);

    if (message) {
      showToast(message, type);
    }
  }

  async function checkEmployerMatch() {
    if (!waitingActive || waitingRequestRunning) return;
    waitingRequestRunning = true;

    try {
      // First: check if an employer assigned us directly via "Next Jobseeker"
      const ringedRes = await fetch('/QuickHire/Public/actions/check_waiting_calls.php', {
        headers: { 'Accept': 'application/json' }
      });
      const ringedData = await ringedRes.json();

      if (ringedData.ok && ringedData.has_call && ringedData.room) {
        waitingCopyEl.textContent = 'Employer found. Opening your call room...';
        clearInterval(waitingTimer);
        clearInterval(waitingPoller);
        window.location.href = '/QuickHire/Public/call.php?room=' + encodeURIComponent(ringedData.room);
        return;
      }

      // Second: look for a matching WAITING employer room
      const response = await fetch('/QuickHire/Public/actions/find_employer.php', {
        headers: { 'Accept': 'application/json' }
      });
      const data = await response.json();

      if (!waitingActive) return;

      if (data.ok && data.room) {
        waitingCopyEl.textContent = 'Employer found. Opening your call room...';
        clearInterval(waitingTimer);
        clearInterval(waitingPoller);
        window.location.href = '/QuickHire/Public/call.php?room=' + encodeURIComponent(data.room);
        return;
      }

      // Only stop if it's a hard error (not just "no employers yet")
      if (!data.waiting && data.error && !data.error.includes('No employers')) {
        stopWaiting(data.error || 'Unable to find employers right now.', 'error');
      }
    } catch (error) {
      if (waitingActive) {
        stopWaiting('Connection error. Please try again.', 'error');
      }
    } finally {
      waitingRequestRunning = false;
    }
  }

  async function findEmployer() {
    if (waitingActive) return;
    showWaitingScreen();
    await checkEmployerMatch();
    if (waitingActive) {
      waitingTimer = setInterval(updateWaitingCountdown, 1000);
      waitingPoller = setInterval(checkEmployerMatch, 3500);
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
  btnStopWaiting.addEventListener('click', () => stopWaiting('Waiting stopped.', 'info'));
  btnHome.addEventListener('click', showDashboard);
  btnBrowseJobs.addEventListener('click', showJobBrowsing);
  btnSettings.addEventListener('click', showSettings);

  const autoWaitParam = new URLSearchParams(window.location.search).get('auto_wait');
  if (autoWaitParam === '1') {
    showDashboard();
    setTimeout(findEmployer, 250);
    window.history.replaceState({}, document.title, window.location.pathname);
  }
  
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
            ${job.role_title ? `<span class="meta-tag"><span class="meta-label">Role:</span> ${job.role_title}</span>` : ''}
            ${job.employment_type ? `<span class="meta-tag"><span class="meta-label">Type:</span> ${job.employment_type.replace('_', ' ')}</span>` : ''}
            ${rateDisplay ? `<span class="meta-tag"><span class="meta-label">Rate:</span> ${rateDisplay}</span>` : ''}
            ${hoursDisplay ? `<span class="meta-tag"><span class="meta-label">Hours:</span> ${hoursDisplay}</span>` : ''}
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
          ${job.role_title ? `<span class="meta-tag"><span class="meta-label">Role:</span> ${job.role_title}</span>` : ''}
          ${job.employment_type ? `<span class="meta-tag"><span class="meta-label">Type:</span> ${job.employment_type.replace('_', ' ')}</span>` : ''}
          ${rateDisplay ? `<span class="meta-tag"><span class="meta-label">Rate:</span> ${rateDisplay}</span>` : ''}
          ${hoursDisplay ? `<span class="meta-tag"><span class="meta-label">Hours:</span> ${hoursDisplay}</span>` : ''}
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
        <div class="conversation-item ${isActive ? 'active' : ''}" data-conversation-id="${Number.parseInt(conv.id, 10)}" onclick="openConversation(${Number.parseInt(conv.id, 10)}, '${escapeJsString(participantName)}', '${escapeJsString(conv.other_profile_picture_url || '')}')">
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

  function appendLocalMessage(text) {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    const formatted = escapeHtml(text).replace(/\n/g, '<br>');
    const html = `
      <div class="message own">
        <div class="message-avatar">You</div>
        <div class="message-content">
          <div class="message-text">${formatted}</div>
          <div class="message-time">${new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}</div>
        </div>
      </div>
    `;
    if (container.querySelector('.empty-state')) {
      container.innerHTML = '';
    }
    container.insertAdjacentHTML('beforeend', html);
    container.scrollTop = container.scrollHeight;
  }

  // Send message
  document.getElementById('messageForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!currentConversationId) return;
    
    const messageInput = document.getElementById('messageInput');
    const fileInput = document.getElementById('fileInput');
    const messageText = messageInput.value.trim();
    const hasFile = !!fileInput.files[0];
    
    if (!messageText && !hasFile) return;
    
    const formData = new FormData();
    formData.append('conversation_id', currentConversationId);
    if (messageText) formData.append('message', messageText);
    if (hasFile) formData.append('file', fileInput.files[0]);
    
    try {
      const response = await fetch('/QuickHire/Public/actions/send_message.php', {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      if (data.ok) {
        const previewText = messageText || (hasFile ? 'Sent a file' : 'Sent a message');
        messageInput.value = '';
        fileInput.value = '';
        removeFilePreview();
        
        // Clear pending conversation since message was sent
        if (window.pendingConversation && window.pendingConversation.id == currentConversationId) {
          window.pendingConversation = null;
        }
        
        const conversationItem = document.querySelector(`.conversation-item[data-conversation-id="${currentConversationId}"]`);
        if (conversationItem) {
          const previewEl = conversationItem.querySelector('.conversation-preview');
          if (previewEl) {
            previewEl.textContent = previewText;
          }
        }
        const conv = currentConversations.find(c => parseInt(c.id, 10) === parseInt(currentConversationId, 10));
        if (conv) {
          conv.last_message = previewText;
        }

        if (hasFile) {
          await loadMessages(currentConversationId);
        } else if (messageText) {
          appendLocalMessage(messageText);
        }
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
