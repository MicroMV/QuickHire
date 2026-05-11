<script>
  // Name editing functionality
  function editName() {
    document.getElementById('nameDisplay').style.display = 'none';
    document.getElementById('nameEdit').style.display = 'block';
    document.getElementById('firstNameInput').focus();
  }

  function cancelEditName() {
    document.getElementById('nameDisplay').style.display = 'block';
    document.getElementById('nameEdit').style.display = 'none';
  }

  async function saveName() {
    const firstName = document.getElementById('firstNameInput').value.trim();
    const lastName = document.getElementById('lastNameInput').value.trim();

    if (!firstName || !lastName) {
      showToast('Please enter both first and last name', 'error');
      return;
    }

    try {
      const formData = new FormData();
      formData.append('first_name', firstName);
      formData.append('last_name', lastName);
      formData.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');

      const response = await fetch('/QuickHire/Public/actions/update_name.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.ok) {
        // Update the display
        document.getElementById('nameDisplay').innerHTML = `${firstName} ${lastName} <span style="margin-left:6px;opacity:0.5;"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>`;
        cancelEditName();
        showToast('Name updated successfully', 'success');
        
        // Update sidebar name
        const sidebarName = document.querySelector('.profileCard .name');
        if (sidebarName) {
          sidebarName.textContent = `${firstName} ${lastName}`;
        }
      } else {
        showToast(result.error || 'Failed to update name', 'error');
      }
    } catch (error) {
      showToast('Connection error. Please try again.', 'error');
    }
  }
</script>
