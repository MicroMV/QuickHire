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
