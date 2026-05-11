<script>
function openEditJobModal(jobId) {
  const numericJobId = parseInt(jobId, 10);
  const jobData = (window.currentJobPosts || []).find(j => parseInt(j.id, 10) === numericJobId);
  if (!jobData) { showToast('Job not found', 'error'); return; }

  document.getElementById('edit_job_id').value = numericJobId;
  document.getElementById('edit_job_title').value = jobData.title || '';
  document.getElementById('edit_job_description').value = jobData.description || '';
  document.getElementById('edit_job_role_title').value = jobData.role_title || '';
  document.getElementById('edit_job_employment_type').value = jobData.employment_type || '';
  document.getElementById('edit_job_country').value = jobData.country || '';
  document.getElementById('edit_job_rate').value = jobData.rate_per_hour || '';
  document.getElementById('edit_job_hours').value = jobData.hours_per_week || '';

  // Pre-check existing skills
  const currentSkillIds = (jobData.skills || []).map(s => parseInt(s.id));
  document.querySelectorAll('.edit-job-skill').forEach(cb => {
    cb.checked = currentSkillIds.includes(parseInt(cb.value));
  });

  const editJobModal = document.getElementById('editJobModal');
  editJobModal.style.display = 'flex';
  const editJobForm = document.getElementById('editJobForm');
  if (editJobForm) editJobForm.scrollTop = 0;
  document.body.style.overflow = 'hidden';
}

function closeEditJobModal() {
  document.getElementById('editJobModal').style.display = 'none';
  document.body.style.overflow = '';
}

document.getElementById('editJobModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditJobModal();
});

// Skills search & tab filter for edit modal
(function() {
  const search = document.getElementById('editJobSkillsSearch');
  const tabs   = document.querySelectorAll('#editJobModal .skills-tab');
  const sects  = document.querySelectorAll('#editJobSkillsContainer .category-section');
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
})();

document.getElementById('editJobForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('editJobSubmitBtn');
  btn.textContent = 'Saving...';
  btn.disabled = true;

  try {
    const fd = new FormData();
    fd.append('job_id', document.getElementById('edit_job_id').value);
    fd.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');
    fd.append('title', document.getElementById('edit_job_title').value);
    fd.append('description', document.getElementById('edit_job_description').value);
    fd.append('role_title', document.getElementById('edit_job_role_title').value);
    fd.append('employment_type', document.getElementById('edit_job_employment_type').value);
    fd.append('country', document.getElementById('edit_job_country').value);
    fd.append('rate_per_hour', document.getElementById('edit_job_rate').value);
    fd.append('hours_per_week', document.getElementById('edit_job_hours').value);

    // Collect checked skills
    document.querySelectorAll('.edit-job-skill:checked').forEach(cb => {
      fd.append('skill_ids[]', cb.value);
    });

    const res = await fetch('/QuickHire/Public/actions/update_job.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.ok) {
      closeEditJobModal();
      showToast('Job updated successfully!', 'success');
      await loadMyJobPosts();
    } else {
      showToast(data.error || 'Failed to update job.', 'error');
    }
  } catch (err) {
    showToast('Connection error.', 'error');
  } finally {
    btn.textContent = 'Save Changes';
    btn.disabled = false;
  }
});
</script>
