<script>
(function() {
  document.body.style.overflow = 'hidden';
  let currentStep = 1;

  function empGoStep(n) {
    document.getElementById('emp-step-' + currentStep).classList.remove('active');
    document.getElementById('emp-step-' + n).classList.add('active');
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

    // Populate review on step 3
    if (n === 3) {
      const company = document.querySelector('[name=company_name]')?.value || '';
      const country = document.querySelector('[name=country]')?.value || '';
      const checkedBoxes = document.querySelectorAll('#ovEmpSkillsContainer input[type=checkbox]:checked');

      document.getElementById('empReviewCompany').textContent = company;
      document.getElementById('empReviewCountry').textContent = country;
      document.getElementById('empReviewSkillCount').textContent = checkedBoxes.length;

      // Render skill pills
      const pillsContainer = document.getElementById('empReviewSkillPills');
      pillsContainer.innerHTML = '';
      if (checkedBoxes.length === 0) {
        pillsContainer.innerHTML = '<span style="color:#64748b;font-size:13px;font-style:italic;">No skills selected</span>';
      } else {
        checkedBoxes.forEach(cb => {
          const label = cb.closest('label');
          const name = label ? label.textContent.trim() : cb.value;
          const pill = document.createElement('span');
          pill.textContent = name;
          pill.style.cssText = 'background:rgba(99,102,241,0.15);color:#a5b4fc;border:1px solid rgba(99,102,241,0.3);border-radius:20px;padding:3px 10px;font-size:12px;font-weight:600;';
          pillsContainer.appendChild(pill);
        });
      }
    }
  }
  window.empGoStep = empGoStep;

  window.empNextStep = function(from) {
    const panel = document.getElementById('emp-step-' + from);
    panel.querySelectorAll('.cp-invalid').forEach(el => el.classList.remove('cp-invalid'));
    panel.querySelector('.cp-validation-msg')?.remove();
    let valid = true;
    panel.querySelectorAll('[required]').forEach(el => {
      if (!el.value.trim()) { el.classList.add('cp-invalid'); valid = false; }
    });
    if (!valid) {
      const msg = document.createElement('p');
      msg.className = 'cp-validation-msg';
      msg.textContent = 'Please fill in all required fields.';
      const grid = panel.querySelector('.cp-grid');
      if (grid) {
        grid.style.marginBottom = '0';
        grid.after(msg);
      } else {
        panel.querySelector('.cp-nav').before(msg);
      }
      return;
    }
    empGoStep(from + 1);
  };

  // Skills search & tab filter
  const search = document.getElementById('ovEmpSkillsSearch');
  const tabs   = document.querySelectorAll('#emp-step-2 .skills-tab');
  const sects  = document.querySelectorAll('#ovEmpSkillsContainer .category-section');
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
  const skillContainer = document.getElementById('ovEmpSkillsContainer');
  const limitMsg = document.createElement('p');
  limitMsg.style.cssText = 'color:#fca5a5;font-size:13px;font-weight:600;margin:8px 0 0;display:none;';
  limitMsg.textContent = 'Maximum of 10 skills reached.';
  skillContainer.parentElement.appendChild(limitMsg);
  skillContainer.addEventListener('change', e => {
    if (!e.target.matches('input[type=checkbox]')) return;
    const checked = skillContainer.querySelectorAll('input[type=checkbox]:checked');
    if (checked.length > SKILL_LIMIT) { e.target.checked = false; }
    const count = skillContainer.querySelectorAll('input[type=checkbox]:checked').length;
    limitMsg.style.display = count >= SKILL_LIMIT ? 'block' : 'none';
    skillContainer.querySelectorAll('input[type=checkbox]:not(:checked)').forEach(cb => {
      cb.disabled = count >= SKILL_LIMIT;
    });
  });
})();
</script>
