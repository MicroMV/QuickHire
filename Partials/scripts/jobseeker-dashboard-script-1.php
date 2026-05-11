<script>
(function() {
  document.body.style.overflow = 'hidden';
  let currentStep = 1;

  function jsGoStep(n) {
    document.getElementById('js-step-' + currentStep).classList.remove('active');
    document.getElementById('js-step-' + n).classList.add('active');
    document.querySelectorAll('#profileCompletionOverlay .cp-step').forEach(el => {
      const s = parseInt(el.dataset.step);
      el.classList.remove('active','done');
      if (s === n) el.classList.add('active');
      if (s < n)  el.classList.add('done');
    });
    document.querySelectorAll('#profileCompletionOverlay .cp-step-line').forEach((line, i) => {
      line.classList.toggle('done', i < n - 1);
    });
    currentStep = n;
    document.querySelector('.profile-overlay').scrollTop = 0;
  }
  window.jsGoStep = jsGoStep;

  window.jsNextStep = function(from) {
    const panel = document.getElementById('js-step-' + from);
    panel.querySelectorAll('.cp-invalid').forEach(el => el.classList.remove('cp-invalid'));
    panel.querySelector('.cp-validation-msg')?.remove();

    let valid = true;
    if (from === 1) {
      panel.querySelectorAll('[required]').forEach(el => {
        if (!el.value.trim()) { el.classList.add('cp-invalid'); valid = false; }
      });
    }
    if (from === 2) {
      const checked = document.querySelectorAll('#ovJsSkillsContainer input[type=checkbox]:checked');
      if (checked.length === 0) {
        document.getElementById('ovJsSkillsContainer').classList.add('cp-invalid-box');
        valid = false;
      } else {
        document.getElementById('ovJsSkillsContainer').classList.remove('cp-invalid-box');
      }
    }
    if (from === 3) {
      panel.querySelectorAll('[required]').forEach(el => {
        if (!el.value.trim()) { el.classList.add('cp-invalid'); valid = false; }
      });
    }
    if (!valid) {
      const msg = document.createElement('p');
      msg.className = 'cp-validation-msg';
      msg.textContent = from === 2 ? 'Please select at least one skill.' : 'Please fill in all required fields.';
      const grid = panel.querySelector('.cp-grid');
      if (grid) grid.after(msg);
      else panel.querySelector('.cp-nav').before(msg);
      return;
    }
    jsGoStep(from + 1);
  };

  function showWizardMessage(panel, message) {
    panel.querySelector('.cp-validation-msg')?.remove();
    const msg = document.createElement('p');
    msg.className = 'cp-validation-msg';
    msg.textContent = message;
    panel.querySelector('.cp-nav')?.before(msg);
  }

  function validateSelectedResume(file) {
    if (!file) return true;
    const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
    if (!isPdf) return 'Please upload a PDF file.';
    if (file.size > 5_000_000) return 'Resume must be 5MB or smaller.';
    return true;
  }

  // Validate the whole wizard on form submit so the button never fails silently.
  document.getElementById('jsProfileForm').addEventListener('submit', function(e) {
    for (const step of [1, 3]) {
      const panel = document.getElementById('js-step-' + step);
      let valid = true;
      panel.querySelectorAll('[required]').forEach(el => {
        if (!String(el.value || '').trim()) {
          el.classList.add('cp-invalid');
          valid = false;
        }
      });
      if (!valid) {
        e.preventDefault();
        jsGoStep(step);
        showWizardMessage(panel, 'Please fill in all required fields.');
        return;
      }
    }

    const checkedSkills = document.querySelectorAll('#ovJsSkillsContainer input[type=checkbox]:checked');
    if (checkedSkills.length === 0) {
      e.preventDefault();
      jsGoStep(2);
      const panel = document.getElementById('js-step-2');
      document.getElementById('ovJsSkillsContainer').classList.add('cp-invalid-box');
      showWizardMessage(panel, 'Please select at least one skill.');
      return;
    }

    const resumeInput = document.getElementById('ovJsResume');
    const newResumeBox = document.getElementById('ovJsNewResume');
    const hasExistingResume = <?= !empty($profile['resume_url']) ? 'true' : 'false' ?>;
    const hasNewFile = resumeInput && resumeInput.files && resumeInput.files.length > 0;
    const resumeCheck = validateSelectedResume(hasNewFile ? resumeInput.files[0] : null);

    if (resumeCheck !== true) {
      e.preventDefault();
      jsGoStep(4);
      const panel = document.getElementById('js-step-4');
      showWizardMessage(panel, resumeCheck);
      const dropZone = document.getElementById('ovJsDropZone');
      if (dropZone) dropZone.style.borderColor = '#ef4444';
      return;
    }

    if (!hasNewFile && !hasExistingResume) {
      e.preventDefault();
      jsGoStep(4);
      const panel = document.getElementById('js-step-4');
      showWizardMessage(panel, 'Please upload your resume (PDF).');
      const dropZone = document.getElementById('ovJsDropZone');
      if (dropZone) dropZone.style.borderColor = '#ef4444';
    }
  });

  // Resume preview + drag & drop
  const resumeInput      = document.getElementById('ovJsResume');
  const newResumeBox     = document.getElementById('ovJsNewResume');
  const resumeFileName   = document.getElementById('ovJsResumeFileName');
  const currentResumeBox = document.getElementById('ovJsCurrentResume');
  const dropZone         = document.getElementById('ovJsDropZone');

  function showResumeFile(file) {
    if (!file) return;
    const resumeCheck = validateSelectedResume(file);
    if (resumeCheck !== true) {
      const panel = document.getElementById('js-step-4');
      showWizardMessage(panel, resumeCheck);
      if (dropZone) dropZone.style.borderColor = '#ef4444';
      resumeInput.value = '';
      return;
    }
    if (resumeFileName)   resumeFileName.textContent = file.name;
    if (newResumeBox)     newResumeBox.style.display = 'flex';
    if (currentResumeBox) currentResumeBox.style.display = 'none';
    if (dropZone)         dropZone.style.borderColor = '#10b981';
    // Clear any validation error
    document.getElementById('js-step-4')?.querySelector('.cp-validation-msg')?.remove();
    if (dropZone) dropZone.style.borderColor = '#10b981';
  }

  window.clearResume = function() {
    resumeInput.value = '';
    if (newResumeBox)     newResumeBox.style.display = 'none';
    if (currentResumeBox) currentResumeBox.style.display = 'flex';
    if (dropZone)         dropZone.style.borderColor = 'rgba(99,102,241,0.4)';
  };

  window.handleResumeDrop = function(e) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (!file) return;
    const resumeCheck = validateSelectedResume(file);
    if (resumeCheck !== true) {
      showWizardMessage(document.getElementById('js-step-4'), resumeCheck);
      if (dropZone) dropZone.style.borderColor = '#ef4444';
      return;
    }
    // Assign to the file input via DataTransfer
    const dt = new DataTransfer();
    dt.items.add(file);
    resumeInput.files = dt.files;
    showResumeFile(file);
    if (dropZone) {
      dropZone.style.borderColor = 'rgba(99,102,241,0.4)';
      dropZone.style.background = 'rgba(99,102,241,0.04)';
    }
  };

  if (resumeInput) {
    resumeInput.addEventListener('change', () => {
      const file = resumeInput.files[0];
      if (file) {
        showResumeFile(file);
      } else {
        if (newResumeBox)     newResumeBox.style.display = 'none';
        if (currentResumeBox) currentResumeBox.style.display = 'flex';
      }
    });
  }

  // Skills search & tab filter
  const search = document.getElementById('ovJsSkillsSearch');
  const tabs   = document.querySelectorAll('#js-step-2 .skills-tab');
  const sects  = document.querySelectorAll('#ovJsSkillsContainer .category-section');
  let activeCategory = 'all';
  function filterSkills() {
    const q = search ? search.value.toLowerCase() : '';
    sects.forEach(sect => {
      const catMatch = activeCategory === 'all' || sect.dataset.category === activeCategory;
      let anyVisible = false;
      sect.querySelectorAll('.skill-checkbox').forEach(cb => {
        const show = catMatch && (!q || (cb.dataset.skillName || '').includes(q));
        cb.style.display = show ? '' : 'none';
        if (show) anyVisible = true;
      });
      sect.style.display = anyVisible ? '' : 'none';
    });
  }
  if (search) search.addEventListener('input', filterSkills);
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeCategory = tab.dataset.category;
      if (search) search.value = '';
      filterSkills();
    });
  });

  // Skill limit: max 10
  const SKILL_LIMIT = 10;
  const skillContainer = document.getElementById('ovJsSkillsContainer');
  const limitMsg = document.createElement('p');
  limitMsg.id = 'ovJsSkillLimitMsg';
  limitMsg.style.cssText = 'color:#fca5a5;font-size:13px;font-weight:600;margin:8px 0 0;display:none;';
  limitMsg.textContent = 'Maximum 10 skills allowed.';
  skillContainer.parentElement.appendChild(limitMsg);

  skillContainer.addEventListener('change', e => {
    if (!e.target.matches('input[type=checkbox]')) return;
    const checked = skillContainer.querySelectorAll('input[type=checkbox]:checked');
    if (checked.length > SKILL_LIMIT) {
      e.target.checked = false;
      limitMsg.style.display = 'block';
    } else {
      limitMsg.style.display = checked.length === SKILL_LIMIT ? 'block' : 'none';
      if (checked.length === SKILL_LIMIT) limitMsg.textContent = 'Maximum of 10 skills reached.';
    }
    // Disable unchecked boxes when at limit
    skillContainer.querySelectorAll('input[type=checkbox]:not(:checked)').forEach(cb => {
      cb.disabled = checked.length >= SKILL_LIMIT;
    });
  });
})();
</script>
