<script>
  const modal = document.getElementById('authModal');
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const forgotForm = document.getElementById('forgotForm');
  const tabLogin = document.getElementById('tabLogin');
  const tabRegister = document.getElementById('tabRegister');

  function openModal(tab = 'login') {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    if (tab === 'register') switchTab('register');
    else if (tab === 'forgot') switchTab('forgot');
    else switchTab('login');
  }

  function switchTab(tab) {
    if (tab === 'login') {
      loginForm.style.display = 'block';
      registerForm.style.display = 'none';
      forgotForm.style.display = 'none';
      tabLogin.classList.add('active');
      tabRegister.classList.remove('active');
    } else if (tab === 'register') {
      loginForm.style.display = 'none';
      registerForm.style.display = 'block';
      forgotForm.style.display = 'none';
      tabLogin.classList.remove('active');
      tabRegister.classList.add('active');
    } else {
      loginForm.style.display = 'none';
      registerForm.style.display = 'none';
      forgotForm.style.display = 'block';
      tabLogin.classList.remove('active');
      tabRegister.classList.remove('active');
    }
  }

  document.getElementById('openLogin').addEventListener('click', e => { e.preventDefault(); openModal('login'); });
  document.getElementById('openRegister').addEventListener('click', e => { e.preventDefault(); openModal('register'); });
  document.getElementById('heroGetHired').addEventListener('click', e => { e.preventDefault(); openModal('register'); });
  document.getElementById('heroHire').addEventListener('click', e => { e.preventDefault(); openModal('register'); });
  document.getElementById('ctaJobseeker').addEventListener('click', e => { e.preventDefault(); openModal('register'); });
  document.getElementById('ctaEmployer').addEventListener('click', e => { e.preventDefault(); openModal('register'); });
  document.getElementById('closeModal').addEventListener('click', () => { modal.classList.remove('active'); document.body.style.overflow = ''; });
  modal.addEventListener('click', e => { if (e.target === modal) { modal.classList.remove('active'); document.body.style.overflow = ''; } });
  tabLogin.addEventListener('click', () => switchTab('login'));
  tabRegister.addEventListener('click', () => switchTab('register'));

  const urlParams = new URLSearchParams(window.location.search);
  const openTab = urlParams.get('open');
  if (openTab === 'login' || openTab === 'register' || openTab === 'forgot') {
    openModal(openTab);
  }
</script>
