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
    showToast('Search failed. Please try again.', 'error');
    hideSearchResults();
  } finally {
    searchButton.disabled = false;
    searchButton.textContent = 'Search';
  }
}

function displaySearchResults(results, query) {
  // Store results for profile view
  window._searchResults = {};
  results.forEach(j => { window._searchResults[j.id] = j; });
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

    const isActive = jobseeker.last_active && (new Date() - new Date(jobseeker.last_active)) < 60000;
    const minutesAgo = jobseeker.last_active ? Math.floor((new Date() - new Date(jobseeker.last_active)) / 60000) : null;
    const showBadge = minutesAgo !== null && minutesAgo >= 1 && minutesAgo <= 5;
    
    const skills = jobseeker.skills ? jobseeker.skills.split(', ').slice(0, 5).join(', ') : 'No skills listed';
    const moreSkills = jobseeker.skills && jobseeker.skills.split(', ').length > 5 
      ? ` +${jobseeker.skills.split(', ').length - 5} more` : '';

    html += `
      <div class="search-result-item" onclick="viewJobseekerProfile(this)" style="cursor:pointer;" data-id="${jobseeker.id}">
        <div class="search-result-avatar" style="position:relative;">
          ${avatar}
          ${statusDot(jobseeker.last_active)}
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
              <span class="meta-label">Rate:</span> ${jobseeker.rate_per_hour || '0'}/hr
            </div>
            <div class="search-result-detail">
              <span class="meta-label">Location:</span> ${jobseeker.country || 'Not specified'}
            </div>
            <div class="search-result-detail">
              <span class="meta-label">Hours:</span> ${jobseeker.available_time || 'N/A'}h/day
            </div>
            <div class="search-result-detail">
              <span class="meta-label">English:</span> ${jobseeker.english_mastery || 'Not specified'}
            </div>
          </div>
          <div class="search-result-skills">
            <strong>Skills:</strong> ${skills}${moreSkills}
          </div>
        </div>
        <div class="search-result-actions" onclick="event.stopPropagation()">
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

// View jobseeker profile
function viewJobseekerProfile(el) {
  const id = parseInt(el.dataset.id);
  const js = window._searchResults && window._searchResults[id];
  if (!js) return;

  renderJobseekerProfile(js);
  showJobseekerProfileView();
}

function renderJobseekerProfile(js) {

  // Populate panel
  const avatarEl = document.getElementById('jsProfileAvatar');
  if (js.profile_picture_url) {
    avatarEl.innerHTML = `<img src="/QuickHire/Public/${js.profile_picture_url}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">${statusDot(js.last_active)}`;
  } else {
    avatarEl.innerHTML = `${(js.first_name || '?').charAt(0).toUpperCase()}${statusDot(js.last_active)}`;
  }

  document.getElementById('jsProfileName').textContent = `${js.first_name} ${js.last_name}`;
  document.getElementById('jsProfileRole').textContent = js.role_title || 'Job Seeker';

  let meta = js.country || '';
  if (js.portfolio_url) meta += (meta ? '  ' : '') + `<a href="${js.portfolio_url}" target="_blank" style="color:#6366f1;text-decoration:none;">${js.portfolio_url}</a>`;
  document.getElementById('jsProfileMeta').innerHTML = meta;

  // Pills
  const pills = [
    js.rate_per_hour ? `<span style="padding:8px 16px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.25);border-radius:20px;color:#a5b4fc;font-size:13px;font-weight:600;">💰 $${js.rate_per_hour}/hr</span>` : '',
    js.available_time ? `<span style="padding:8px 16px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);border-radius:20px;color:#34d399;font-size:13px;font-weight:600;">⏰ ${js.available_time}h/day</span>` : '',
    js.english_mastery ? `<span style="padding:8px 16px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);border-radius:20px;color:#fbbf24;font-size:13px;font-weight:600;">🗣️ ${js.english_mastery}</span>` : '',
    js.employment_type ? `<span style="padding:8px 16px;background:rgba(139,92,246,0.1);border:1px solid rgba(139,92,246,0.25);border-radius:20px;color:#c084fc;font-size:13px;font-weight:600;">💼 ${js.employment_type.replace('_','-')}</span>` : '',
    js.age ? `<span style="padding:8px 16px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:20px;color:#94a3b8;font-size:13px;font-weight:600;">🎂 ${js.age} yrs</span>` : '',
  ].filter(Boolean).join('');
  document.getElementById('jsProfilePills').innerHTML = pills;

  // About
  document.getElementById('jsProfileAbout').innerHTML = (js.profile_description || 'No description.').replace(/\n/g, '<br>');

  // Skills
  const skillsArr = js.skills ? js.skills.split(', ').filter(Boolean) : [];
  document.getElementById('jsProfileSkills').innerHTML = skillsArr.length
    ? skillsArr.map(s => `<span style="padding:5px 12px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.3);border-radius:20px;color:#a5b4fc;font-size:12px;font-weight:600;">${s}</span>`).join('')
    : '<span style="color:#64748b;font-size:13px;">No skills listed.</span>';

  // Details
  let details = '';
  if (js.bachelors_degree) details += `<div style="display:flex;gap:10px;align-items:center;"><span style="font-size:18px;">🎓</span><div><div style="font-size:12px;color:#64748b;">Education</div><div style="font-size:14px;font-weight:600;color:#e2e8f0;">${js.bachelors_degree}</div></div></div>`;
  if (js.gender) details += `<div style="display:flex;gap:10px;align-items:center;"><span style="font-size:18px;">👤</span><div><div style="font-size:12px;color:#64748b;">Gender</div><div style="font-size:14px;font-weight:600;color:#e2e8f0;">${js.gender.charAt(0)+js.gender.slice(1).toLowerCase()}</div></div></div>`;
  if (js.resume_url) details += `<div style="display:flex;gap:10px;align-items:center;"><span style="font-size:18px;">📄</span><div><div style="font-size:12px;color:#64748b;">Resume</div><a href="/QuickHire/Public/${js.resume_url}" target="_blank" style="font-size:14px;font-weight:600;color:#6366f1;text-decoration:none;">View Resume</a></div></div>`;
  document.getElementById('jsProfileDetails').innerHTML = details || '<span style="color:#64748b;font-size:13px;">No details available.</span>';

  // Message button
  document.getElementById('jsProfileMsgBtn').onclick = () =>
    startConversationWithJobseeker(js.id, document.getElementById('jsProfileMsgBtn'));
}

function showJobseekerProfileView() {
  closeMessagingPanel();
  localStorage.setItem('emp_active_page', 'home');

  document.getElementById('dashboardContent').style.display = 'none';
  document.getElementById('searchContent').style.display = 'none';
  document.getElementById('jobPostingContent').style.display = 'none';
  document.getElementById('profileEditContent').style.display = 'none';
  document.getElementById('settingsContent').style.display = 'none';
  document.getElementById('jsProfileView').style.display = 'block';

  btnHome.classList.remove('active');
  btnSearchJobseekers.classList.add('active');
  btnPostJob.classList.remove('active');
  btnEditProfile.classList.remove('active');
  btnEditProfile2.classList.remove('active');
  btnSettings.classList.remove('active');
  btnMessages.classList.remove('active');

  document.querySelector('.title').textContent = 'Jobseeker Profile';
  document.querySelector('.subtitle').textContent = 'Viewing candidate profile.';
}

async function openJobseekerProfileFromConversation(jobseekerId) {
  jobseekerId = parseInt(jobseekerId, 10);
  if (!jobseekerId) return;

  try {
    const response = await fetch(`/QuickHire/Public/actions/get_jobseeker_profile.php?jobseeker_id=${jobseekerId}`);
    const data = await response.json();

    if (!data.ok || !data.profile) {
      showToast(data.error || 'Failed to load jobseeker profile', 'error');
      return;
    }

    renderJobseekerProfile(data.profile);
    showJobseekerProfileView();
  } catch (error) {
    showToast('Failed to load jobseeker profile: ' + error.message, 'error');
  }
}

async function startConversationWithJobseeker(jobseekerId, buttonElement, jobPostId = null) {
  const button = buttonElement;
  
  if (!button) {
    showToast('Failed to start conversation: Button not found', 'error');
    return;
  }
  
  button.disabled = true;
  button.textContent = 'Starting...';

  try {
    const formData = new FormData();
    formData.append('jobseeker_id', jobseekerId);
    if (jobPostId) formData.append('job_post_id', jobPostId);

    const response = await fetch('/QuickHire/Public/actions/start_conversation.php', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) throw new Error(`HTTP error: ${response.status}`);
    const data = await response.json();

    if (data.ok) {
      const actionText = data.is_existing ? 'Opened existing conversation' : 'Started new conversation';
      
      // Open messaging panel first
      messagingPanel.classList.add('open');
      setEmployerMessagesNavActive();
      
      // Load conversations to get the latest list
      await loadConversations();
      await new Promise(resolve => setTimeout(resolve, 100));
      
      const conversation = conversations.find(c => c.id == data.conversation_id);
      if (conversation) {
        await openConversation(conversation.id);
        showToast(`${actionText} with ${data.jobseeker_name}`, 'success');
      } else {
        await new Promise(resolve => setTimeout(resolve, 500));
        await loadConversations();
        const retryConversation = conversations.find(c => c.id == data.conversation_id);
        if (retryConversation) {
          await openConversation(retryConversation.id);
          showToast(`${actionText} with ${data.jobseeker_name}`, 'success');
        } else {
          showToast(`Conversation with ${data.jobseeker_name} is ready. Please check your messages.`, 'success');
        }
      }
    } else {
      showToast('Failed to start conversation: ' + data.error, 'error');
    }
  } catch (error) {
    showToast('Failed to start conversation: ' + error.message, 'error');
  } finally {
    button.disabled = false;
    button.textContent = '💬 Message';
  }
}
</script>
