<script>
// Messaging Panel Functionality
const conversationsList = document.getElementById('conversationsList');
const chatArea = document.getElementById('chatArea');
const backToConversations = document.getElementById('backToConversations');
const messagesContainer = document.getElementById('messagesContainer');
const messageForm = document.getElementById('messageForm');
const messageInput = document.getElementById('messageInput');

// Declare these in window scope so they're accessible everywhere
window.currentConversationId = null;
let conversations = [];
let activeJobFilter = '';

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value ?? '';
  return div.innerHTML;
}

function resetMessageSelection() {
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
  const messageInputArea = document.getElementById('messageInputArea');
  if (messageInputArea) messageInputArea.style.display = 'none';
  if (messagesContainer) {
    messagesContainer.innerHTML = `
      <div class="empty-state">
        <h3>Select a conversation</h3>
        <p>Choose a conversation from the sidebar to start messaging</p>
      </div>`;
  }
  document.querySelectorAll('.conversation-item').forEach(item => item.classList.remove('active'));
}

// Open messaging panel
btnMessages.addEventListener('click', (e) => {
  e.preventDefault();
  e.stopPropagation();
  
  try {
    messagingPanel.classList.add('open');
    setEmployerMessagesNavActive();

    // Reset chat header to default state when opening fresh
    window.currentConversationId = null;
    activeJobFilter = '';
    jobFilterPage = 0;
    const convSearch = document.getElementById('convSearchInput');
    if (convSearch) convSearch.value = '';
    document.getElementById('chatTitle').textContent = 'Select a conversation';
    const chatStatusReset = document.getElementById('chatStatus');
    if (chatStatusReset) chatStatusReset.innerHTML = '';
    const menuBtnReset = document.getElementById('chatMenuBtn');
    if (menuBtnReset) menuBtnReset.style.display = 'none';
    const avatarEl = document.getElementById('chatHeaderAvatar');
    if (avatarEl) { avatarEl.style.display = 'none'; avatarEl.innerHTML = ''; }
    const jobBannerReset = document.getElementById('jobBanner');
    if (jobBannerReset) { jobBannerReset.style.display = 'none'; jobBannerReset.innerHTML = ''; }
    document.getElementById('messagesContainer').innerHTML = `
      <div class="empty-state">
        <h3>Select a conversation</h3>
        <p>Choose a conversation from the sidebar to start messaging</p>
      </div>`;
    document.getElementById('messageInputArea').style.display = 'none';

    loadConversations();

    // Load job posts for filter pills if not already loaded
    if (!window.currentJobPosts || window.currentJobPosts.length === 0) {
      fetch('/QuickHire/Public/actions/get_job_posts.php')
        .then(r => r.json())
        .then(result => {
          if (result.ok) {
            window.currentJobPosts = result.job_posts;
            if (typeof buildJobFilter === 'function') {
              buildJobFilter(); // Rebuild pills now that we have job data
            }
          }
        })
        .catch((error) => {
          console.error('Error loading job posts:', error);
        });
    }

  } catch (error) {
    console.error('Error opening messaging panel:', error);
    // Fallback: close the panel if there's an error
    messagingPanel.classList.remove('open');
  }
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
      if (currentConversationId && !conversations.some(c => parseInt(c.id, 10) === parseInt(currentConversationId, 10))) {
        resetMessageSelection();
      }
      buildJobFilter();
      displayConversations();
    } else {
      conversationsList.innerHTML = '<div class="empty-state">No conversations yet</div>';
    }
  } catch (error) {
    conversationsList.innerHTML = '<div class="empty-state">Error loading conversations: ' + error.message + '</div>';
  }
}

let jobFilterPage = 0;
const JOB_PILLS_PER_PAGE = 8; // 8 job pills + All + General = max 10 visible

// Build job filter pills with pagination
function buildJobFilter() {
  const filterBar = document.getElementById('jobFilterBar');
  const pillsContainer = document.getElementById('jobFilterPills');

  // Deduplicate: use a Map keyed by job ID
  const jobMap = {};
  conversations.forEach(c => {
    if (c.job_post_id && c.job_post_title) jobMap[c.job_post_id] = c.job_post_title;
  });

  const jobs = Object.entries(jobMap); // unique job entries

  if (jobs.length === 0) {
    activeJobFilter = '';
    jobFilterPage = 0;
    if (pillsContainer) pillsContainer.innerHTML = '';
    if (filterBar) filterBar.style.display = 'none';
    return;
  }
  filterBar.style.display = 'block';

  // Clamp page
  const totalPages = Math.ceil(jobs.length / JOB_PILLS_PER_PAGE);
  jobFilterPage = Math.max(0, Math.min(jobFilterPage, totalPages - 1));
  const pageJobs = jobs.slice(jobFilterPage * JOB_PILLS_PER_PAGE, (jobFilterPage + 1) * JOB_PILLS_PER_PAGE);

  pillsContainer.innerHTML = '';

  // Prev button
  if (jobFilterPage > 0) {
    const prev = document.createElement('div');
    prev.textContent = '←';
    prev.className = 'job-filter-pill job-filter-nav';
    prev.title = 'Previous';
    prev.onclick = () => { jobFilterPage--; buildJobFilter(); };
    pillsContainer.appendChild(prev);
  }

  // "All" pill
  const allPill = document.createElement('div');
  allPill.textContent = 'All';
  allPill.dataset.jobId = '';
  allPill.className = 'job-filter-pill' + (activeJobFilter === '' ? ' active' : '');
  allPill.onclick = () => { activeJobFilter = ''; updatePillActive(); filterConversations(); };
  pillsContainer.appendChild(allPill);

  // Job pills for current page
  pageJobs.forEach(([id, title]) => {
    const pill = document.createElement('div');
    pill.textContent = title.length > 18 ? title.substring(0, 18) + '…' : title;
    pill.title = title;
    pill.dataset.jobId = id;
    pill.className = 'job-filter-pill' + (activeJobFilter === id ? ' active' : '');
    pill.onclick = () => { activeJobFilter = id; updatePillActive(); filterConversations(); };
    pillsContainer.appendChild(pill);
  });

  // Next button
  if (jobFilterPage < totalPages - 1) {
    const next = document.createElement('div');
    next.textContent = '→';
    next.className = 'job-filter-pill job-filter-nav';
    next.title = 'Next';
    next.onclick = () => { jobFilterPage++; buildJobFilter(); };
    pillsContainer.appendChild(next);
  }
}

function updatePillActive() {
  document.querySelectorAll('.job-filter-pill').forEach(p => {
    p.classList.toggle('active', p.dataset.jobId === activeJobFilter);
  });
}

function filterConversations() {
  const q = (document.getElementById('convSearchInput')?.value || '').toLowerCase().trim();
  let jobFiltered;
  if (activeJobFilter) {
    jobFiltered = conversations.filter(c => String(c.job_post_id) === String(activeJobFilter));
  } else {
    jobFiltered = conversations;
  }
  const finalFiltered = q
    ? jobFiltered.filter(c =>
        `${c.other_first_name} ${c.other_last_name}`.toLowerCase().includes(q) ||
        (c.job_post_title || '').toLowerCase().includes(q) ||
        (c.last_message || '').toLowerCase().includes(q)
      )
    : jobFiltered;
  renderConversationList(finalFiltered);
}

// Display conversations  applies current search + job filter
function displayConversations() {
  filterConversations();
}

// Render a filtered list of conversations
function renderConversationList(filtered) {
  if (filtered.length === 0) {
    conversationsList.innerHTML = '<div class="empty-state">No conversations found</div>';
    return;
  }

  let html = '';
  filtered.forEach(conv => {
    const avatarHtml = conv.other_avatar
      ? `<img src="/QuickHire/Public/${conv.other_avatar}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
      : conv.other_first_name.charAt(0).toUpperCase();
    html += `
      <div class="conversation-item" data-conversation-id="${conv.id}" onclick="openConversation(${conv.id})">
        <div class="conversation-avatar" style="position:relative;">
          ${avatarHtml}
          ${statusDot(conv.other_last_active)}
        </div>
        <div class="conversation-info">
          <div class="conversation-name">${conv.other_first_name} ${conv.other_last_name}</div>
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

  // Restore active highlight on the currently open conversation
  if (currentConversationId) {
    const activeItem = conversationsList.querySelector(`[data-conversation-id="${currentConversationId}"]`);
    if (activeItem) activeItem.classList.add('active');
  }
}
// Toggle chat options menu  positioned fixed relative to button to escape overflow:hidden
function toggleChatMenu() {
  const menu = document.getElementById('chatMenu');
  const btn  = document.getElementById('chatMenuBtn');
  if (!menu || !btn) return;

  if (menu.style.display !== 'none') {
    menu.style.display = 'none';
    return;
  }

  // Position relative to button using getBoundingClientRect
  const rect = btn.getBoundingClientRect();
  menu.style.top    = (rect.bottom + 6) + 'px';
  menu.style.right  = (window.innerWidth - rect.right) + 'px';
  menu.style.left   = 'auto';
  menu.style.display = 'block';
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
  // Hide the menu first
  const menu = document.getElementById('chatMenu');
  if (menu) menu.style.display = 'none';

  if (!confirm('Delete this conversation? This cannot be undone.')) return;
  
  try {
    const fd = new FormData();
    fd.append('conversation_id', conversationId);
    
    const res = await fetch('/QuickHire/Public/actions/delete_conversation.php', { method: 'POST', body: fd });
    const data = await res.json();
    
    if (data.ok) {
      // Reset chat area
      resetMessageSelection();
      activeJobFilter = '';
      jobFilterPage = 0;
      const filterBar = document.getElementById('jobFilterBar');
      if (filterBar) filterBar.style.display = 'none';
      const pillsContainer = document.getElementById('jobFilterPills');
      if (pillsContainer) pillsContainer.innerHTML = '';
      await loadConversations();
      showToast('Conversation deleted.', 'success');
    } else {
      showToast('Failed to delete: ' + data.error, 'error');
    }
  } catch (error) {
    showToast('Error deleting conversation.', 'error');
  }
}

// Open conversation
async function openConversation(conversationId) {
  conversationId = parseInt(conversationId);
  currentConversationId = conversationId;
  const conversation = conversations.find(c => parseInt(c.id) === conversationId);
  
  if (!conversation) return;
  
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
  
  // Update chat header with active status
  const isActive = conversation.other_last_active && (new Date() - new Date(conversation.other_last_active)) < 60000;
  let statusText = "";
  if (isActive) {
    statusText = `<span style="color:#10b981; font-size:13px; font-weight:normal;">Active now</span>`;
  } else if (conversation.other_last_active) {
    const minutesAgo = Math.floor((new Date() - new Date(conversation.other_last_active)) / 60000);
    if (minutesAgo >= 1 && minutesAgo <= 5) {
      statusText = `<span style="color:#64748b; font-size:13px; font-weight:normal;">Active ${minutesAgo} min ago</span>`;
    }
  }
  const chatTitleEl = document.getElementById("chatTitle");
  const chatName = `${conversation.other_first_name} ${conversation.other_last_name}`;
  const jobseekerId = parseInt(conversation.jobseeker_id, 10);
  chatTitleEl.innerHTML = jobseekerId
    ? `<button type="button" class="chat-title-link" onclick="openJobseekerProfileFromConversation(${jobseekerId})">${escapeHtml(chatName)}</button>`
    : escapeHtml(chatName);
  const chatStatusEl = document.getElementById('chatStatus');
  if (chatStatusEl) chatStatusEl.innerHTML = statusText;

  // Show job post banner below chat header
  const jobBanner = document.getElementById('jobBanner');
  if (jobBanner) {
    if (conversation.job_post_id && conversation.job_post_title) {
      jobBanner.style.display = 'block';
      jobBanner.innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span style="font-size:13px;color:#94a3b8;">Applied for:</span>
          <span style="font-size:13px;font-weight:700;color:#a5b4fc;display:flex;align-items:center;gap:5px;">
            📋 ${conversation.job_post_title}
          </span>
        </div>`;
    } else {
      jobBanner.style.display = 'none';
      jobBanner.innerHTML = '';
    }
  }
  // Show the ⋮ menu button now that a conversation is open
  const menuBtn = document.getElementById('chatMenuBtn');
  if (menuBtn) menuBtn.style.display = 'block';

  // Update chat header avatar
  const avatarEl = document.getElementById('chatHeaderAvatar');
  if (avatarEl) {
    avatarEl.style.display = 'flex';
    avatarEl.style.position = 'relative';
    if (conversation.other_avatar) {
      avatarEl.innerHTML = `<img src="/QuickHire/Public/${conversation.other_avatar}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">${statusDot(conversation.other_last_active)}`;
    } else {
      avatarEl.innerHTML = `${conversation.other_first_name.charAt(0).toUpperCase()}${statusDot(conversation.other_last_active)}`;
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
  await loadMessages(conversationId);
}

// Load messages
async function loadMessages(conversationId) {
  try {
    messagesContainer.innerHTML = '<div class="loading">Loading messages...</div>';
    
    const url = `/QuickHire/Public/actions/get_messages.php?conversation_id=${conversationId}`;
    const response = await fetch(url);
    const data = await response.json();
    
    if (data.ok) {
      displayMessages(data.messages);
    } else {
      messagesContainer.innerHTML = '<div class="empty-state">Error: ' + data.error + '</div>';
    }
  } catch (error) {
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
      const ext = fileName.split('.').pop().toLowerCase();
      const isImage = ['jpg','jpeg','png','gif','webp'].includes(ext);

      if (isImage) {
        messageContent = `
          <img src="${msg.file_url}" alt="${fileName}"
            onclick="openImageModal('${msg.file_url}', '${fileName}')"
            style="max-width:260px;max-height:260px;border-radius:10px;display:block;cursor:zoom-in;object-fit:cover;">
          ${fileSize ? `<div class="file-size" style="margin-top:4px;">${fileName} · ${fileSize}</div>` : ''}
          ${msg.content && msg.content !== `Sent a file: ${fileName}` ? `<p class="message-text">${msg.content.replace(/\n/g, '<br>')}</p>` : ''}
        `;
      } else {
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
      }
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
    if (this.files.length > 0) {
      const file = this.files[0];
      const fileName = file.name;
      const fileSize = (file.size / 1024 / 1024).toFixed(2); // Size in MB
      
      
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
  
  
  // Check if we have either a message or a file
  if (!message && (!file || !file.name)) {
    return;
  }
  
  const sendButton = document.getElementById('sendButton');
  sendButton.disabled = true;
  sendButton.textContent = 'Sending...';
  
  try {
    const response = await fetch('/QuickHire/Public/actions/send_message.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.ok) {
      messageInput.value = '';
      messageInput.placeholder = 'Type your message...';
      
      // Reset file input and preview
      fileInput.value = '';
      hideFilePreview();
      
      await loadMessages(currentConversationId);
      await loadConversations(); // Refresh conversations to update last message
    } else {
      alert('Error: ' + result.error);
    }
  } catch (error) {
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
      closeMessagingPanel();
    });
  });
});

// Activity tracking - update on any click/interaction
let lastActivityUpdate = Date.now();

// Shared helper: returns green dot (active) or grey dot (offline) HTML
function statusDot(lastActive) {
  if (lastActive && (new Date() - new Date(lastActive)) < 60000) {
    return `<span class="status-dot status-dot--active"></span>`;
  }
  return `<span class="status-dot status-dot--offline"></span>`;
}
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
window.addEventListener('focus', updateActivity);

// Fallback: update every 30 seconds if user is idle but page is open
setInterval(() => {
  fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' });
}, 30000);

// Refresh conversations every 10 seconds to update active status
setInterval(() => {
  if (messagingPanel.classList.contains('open')) {
    loadConversations();
  }
}, 10000);

// Initial activity update
fetch('/QuickHire/Public/actions/update_activity.php', { method: 'POST' });

const initialJobseekerProfileId = parseInt(new URLSearchParams(window.location.search).get('jobseeker_profile'), 10);
if (initialJobseekerProfileId) {
  openJobseekerProfileFromConversation(initialJobseekerProfileId);
}
</script>
