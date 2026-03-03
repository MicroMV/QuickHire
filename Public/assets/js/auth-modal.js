(() => {
  const modal = document.getElementById('authModal');
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const tabBtns = document.querySelectorAll('.tab');

  const openButtons = document.querySelectorAll('[data-open]');
  const closeButtons = document.querySelectorAll('[data-close]');

  function openModal(tab = 'login') {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    setTab(tab);
    setTimeout(() => {
      const focusEl = tab === 'login' ? document.getElementById('login_email') : document.getElementById('role');
      focusEl?.focus();
    }, 50);
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function setTab(tab) {
    tabBtns.forEach(b => b.classList.toggle('is-active', b.dataset.tab === tab));
    loginForm.classList.toggle('hidden', tab !== 'login');
    registerForm.classList.toggle('hidden', tab !== 'register');
  }

  openButtons.forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.open));
  });

  closeButtons.forEach(btn => {
    btn.addEventListener('click', closeModal);
  });

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => setTab(btn.dataset.tab));
  });

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });

  // open from PHP query string helper
  if (window.__OPEN_AUTH__ === 'register') openModal('register');
  if (window.__OPEN_AUTH__ === 'login') openModal('login');
})();