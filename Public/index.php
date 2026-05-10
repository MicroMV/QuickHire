<?php
require __DIR__ . '/../vendor/autoload.php';
use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
Session::start();
if (Auth::isLoggedIn()) {
  $role = Auth::role();
  if ($role === 'EMPLOYER') header("Location: /QuickHire/Public/employer-dashboard.php");
  else header("Location: /QuickHire/Public/jobseeker-dashboard.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>QuickHire: Hire Smarter. Get Hired Faster.</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/landingPage.css">
  <link rel="stylesheet" href="/QuickHire/Public/assets/css/landing-new.css">
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="landing-body">

<!-- NAVBAR -->
<nav class="ln-nav">
  <div class="ln-nav-inner">
    <a href="#" class="ln-logo">
      <img src="/QuickHire/Public/images/quickhire-logo.png" alt="QuickHire" style="height:36px;border-radius:6px;">
    </a>
    <ul class="ln-nav-links">
      <li><a href="#features">Features</a></li>
      <li><a href="#categories">Jobs</a></li>
      <li><a href="#how">How it works</a></li>
    </ul>
    <div class="ln-nav-actions">
      <a href="#" class="ln-btn-ghost" id="openLogin">Log In</a>
      <a href="#" class="ln-btn-primary" id="openRegister">Get Started</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="ln-hero">
  <div class="ln-glow ln-glow-1"></div>
  <div class="ln-glow ln-glow-2"></div>
  <div class="ln-dots"></div>
  <div class="ln-hero-content">
    <div class="ln-badge">⚡ Smart Skill-Based Matching</div>
    <h1 class="ln-hero-title">
      Hire top IT talent.<br>
      <span class="ln-accent">Get hired faster.</span>
    </h1>
    <p class="ln-hero-sub">QuickHire connects developers, designers, and engineers with companies that need them, through real-time matching, video calls, and direct messaging.</p>
    <div class="ln-hero-ctas">
      <a href="#" class="ln-btn-primary ln-btn-lg" id="heroGetHired">Find Jobs →</a>
      <a href="#" class="ln-btn-outline ln-btn-lg" id="heroHire">Post a Job</a>
    </div>
  </div>
  <!-- Dashboard Preview -->
  <div class="ln-hero-preview">
    <div class="ln-preview-card ln-preview-main">
      <div class="ln-preview-header">
        <div class="ln-preview-dots"><span></span><span></span><span></span></div>
        <span class="ln-preview-title">Live Job Matches</span>
      </div>
      <div class="ln-preview-job">
        <div class="ln-pjob-avatar" style="background:#6366f1;">JS</div>
        <div class="ln-pjob-info"><strong>Senior Frontend Dev</strong><span>TechCorp · Remote · $85/hr</span></div>
        <div class="ln-pjob-badge ln-badge-green">98% match</div>
      </div>
      <div class="ln-preview-job">
        <div class="ln-pjob-avatar" style="background:#ec4899;">PY</div>
        <div class="ln-pjob-info"><strong>Python Backend Engineer</strong><span>DataFlow · US · $70/hr</span></div>
        <div class="ln-pjob-badge ln-badge-blue">91% match</div>
      </div>
      <div class="ln-preview-job">
        <div class="ln-pjob-avatar" style="background:#f59e0b;">UX</div>
        <div class="ln-pjob-info"><strong>UI/UX Designer</strong><span>Pixel Studio · UK · $60/hr</span></div>
        <div class="ln-pjob-badge ln-badge-purple">87% match</div>
      </div>
    </div>
  </div>
</section>

<!-- TRUST BAR removed -->

<!-- FEATURES -->
<section class="ln-features" id="features">
  <div class="ln-section-label">Features</div>
  <h2 class="ln-section-title">Everything you need to hire or get hired</h2>
  <p class="ln-section-sub">Built specifically for the IT industry, not a generic job board.</p>
  <div class="ln-features-grid">
    <div class="ln-feat-card">
      <div class="ln-feat-icon" style="background:rgba(99,102,241,0.15);color:#818cf8;">⚡</div>
      <h3>Real-Time Matching</h3>
      <p>Skill-based algorithm instantly connects jobseekers with employers who need exactly their stack.</p>
    </div>
    <div class="ln-feat-card">
      <div class="ln-feat-icon" style="background:rgba(236,72,153,0.15);color:#f472b6;">📹</div>
      <h3>Instant Video Calls</h3>
      <p>Skip the back-and-forth. Jump straight into a video interview the moment a match is found.</p>
    </div>
    <div class="ln-feat-card">
      <div class="ln-feat-icon" style="background:rgba(16,185,129,0.15);color:#34d399;">💬</div>
      <h3>Direct Messaging</h3>
      <p>Chat directly with candidates or employers. Share files, resumes, and portfolios in one thread.</p>
    </div>
    <div class="ln-feat-card">
      <div class="ln-feat-icon" style="background:rgba(245,158,11,0.15);color:#fbbf24;">🎯</div>
      <h3>Smart Job Browsing</h3>
      <p>Jobseekers browse curated listings filtered by role, tech stack, rate, and employment type.</p>
    </div>
    <div class="ln-feat-card">
      <div class="ln-feat-icon" style="background:rgba(59,130,246,0.15);color:#60a5fa;">🔍</div>
      <h3>Candidate Search</h3>
      <p>Employers search and filter thousands of IT professionals by skill, role, and availability.</p>
    </div>
    <div class="ln-feat-card">
      <div class="ln-feat-icon" style="background:rgba(168,85,247,0.15);color:#c084fc;">📋</div>
      <h3>Job Post Management</h3>
      <p>Create, edit, and manage job posts. Track applicants and conversations from one dashboard.</p>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="ln-how" id="how">
  <div class="ln-glow ln-glow-3"></div>
  <div class="ln-section-label">How it works</div>
  <h2 class="ln-section-title">Up and running in minutes</h2>
  <div class="ln-how-grid">
    <div class="ln-how-card">
      <div class="ln-how-num">01</div>
      <h3>Create your profile</h3>
      <p>Set your skills, role, rate, and availability. Employers see exactly what you bring to the table.</p>
    </div>
    <div class="ln-how-arrow">→</div>
    <div class="ln-how-card">
      <div class="ln-how-num">02</div>
      <h3>Get matched instantly</h3>
      <p>Our algorithm finds the best fit for both sides. No endless scrolling, no wasted time.</p>
    </div>
    <div class="ln-how-arrow">→</div>
    <div class="ln-how-card">
      <div class="ln-how-num">03</div>
      <h3>Connect & get hired</h3>
      <p>Chat, video call, and close the deal, all inside QuickHire. No third-party tools needed.</p>
    </div>
  </div>
</section>

<!-- JOB CATEGORIES -->
<section class="ln-categories" id="categories">
  <div class="ln-section-label">Browse by role</div>
  <h2 class="ln-section-title">Find your next opportunity</h2>
  <div class="ln-search-bar">
    <span class="ln-search-icon">🔍</span>
    <input type="text" placeholder="Search by role, skill, or tech stack..." class="ln-search-input" readonly onclick="document.getElementById('openRegister').click()">
    <button class="ln-btn-primary" onclick="document.getElementById('openRegister').click()">Search Jobs</button>
  </div>
  <div class="ln-categories-grid">
    <div class="ln-cat-card"><div class="ln-cat-icon">⚛️</div><span>Frontend Dev</span></div>
    <div class="ln-cat-card"><div class="ln-cat-icon">🖥️</div><span>Backend Dev</span></div>
    <div class="ln-cat-card"><div class="ln-cat-icon">📱</div><span>Mobile Dev</span></div>
    <div class="ln-cat-card"><div class="ln-cat-icon">☁️</div><span>Cloud / DevOps</span></div>
    <div class="ln-cat-card"><div class="ln-cat-icon">🎨</div><span>UI/UX Design</span></div>
    <div class="ln-cat-card"><div class="ln-cat-icon">🤖</div><span>AI / ML</span></div>
    <div class="ln-cat-card"><div class="ln-cat-icon">🔒</div><span>Security</span></div>
    <div class="ln-cat-card"><div class="ln-cat-icon">📊</div><span>Data Science</span></div>
  </div>
</section>

<!-- CTA SECTION -->
<section class="ln-cta">
  <div class="ln-glow ln-glow-4"></div>
  <div class="ln-cta-inner">
    <div class="ln-badge">Free to get started</div>
    <h2>Ready to find your perfect match?</h2>
    <p>Join thousands of IT professionals and companies already using QuickHire.</p>
    <div class="ln-cta-btns">
      <a href="#" class="ln-btn-primary ln-btn-lg" id="ctaJobseeker">I'm looking for work</a>
      <a href="#" class="ln-btn-outline ln-btn-lg" id="ctaEmployer">I'm hiring talent</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="ln-footer">
  <div class="ln-footer-inner">
    <div class="ln-footer-brand">
      <div class="ln-footer-brand-name">
        <img src="/QuickHire/Public/images/quickhire-logo.png" alt="QuickHire" style="height:32px;border-radius:4px;">
      </div>
      <p>Connecting IT talent with the companies that need them.</p>
    </div>
    <div class="ln-footer-links">
      <div><strong>Platform</strong><a href="#">Browse Jobs</a><a href="#">Post a Job</a><a href="#">Matching</a></div>
      <div><strong>Company</strong><a href="#">About</a><a href="#">Contact</a><a href="#">Privacy</a></div>
    </div>
  </div>
  <div class="ln-footer-bottom">© 2025 QuickHire. All rights reserved.</div>
</footer>

<!-- AUTH SIDEBAR -->
<div class="ln-sidebar-overlay" id="authModal">
  <div class="ln-sidebar">
    <div class="ln-sidebar-header">
      <div>
        <h2 class="ln-sidebar-title">Welcome to QuickHire</h2>
        <p class="ln-sidebar-sub">Sign in or create an account to continue.</p>
      </div>
      <button class="ln-sidebar-close" id="closeModal">✕</button>
    </div>

    <?php
      $openAuth = $_GET['open'] ?? '';
      $authError = \Rongie\QuickHire\Core\Session::flash('error');
      $authSuccess = \Rongie\QuickHire\Core\Session::flash('success');
      $csrfToken = \Rongie\QuickHire\Core\Csrf::token();
      $rcConfig = require __DIR__ . '/../Config/config.php';
      $rcSiteKey = htmlspecialchars($rcConfig['recaptcha']['site_key'] ?? '');
    ?>

    <!-- TABS -->
    <div class="ln-sidebar-tabs">
      <button class="ln-sidebar-tab active" id="tabLogin">Sign In</button>
      <button class="ln-sidebar-tab" id="tabRegister">Sign Up</button>
    </div>

    <!-- LOGIN FORM -->
    <form id="loginForm" class="ln-auth-form" method="POST" action="/QuickHire/Public/actions/login.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <?php if ($authError && $openAuth === 'login'): ?>
        <div class="ln-alert-error"><?= htmlspecialchars($authError) ?></div>
      <?php endif; ?>
      <?php if ($authSuccess && ($openAuth === 'login' || $openAuth === '')): ?>
        <div class="ln-alert-success"><?= htmlspecialchars($authSuccess) ?></div>
      <?php endif; ?>
      <div class="ln-form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="you@example.com" required>
      </div>
      <div class="ln-form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="button" onclick="switchTab('forgot')" class="ln-forgot-link">Forgot password?</button>
      <div style="display:flex;justify-content:center;margin-bottom:12px;">
        <div class="g-recaptcha" data-sitekey="<?= $rcSiteKey ?>" data-theme="dark"></div>
      </div>
      <button type="submit" class="ln-btn-primary ln-btn-full">Sign In</button>
      <p class="ln-auth-agreement">
        <span class="ln-auth-agreement-check" aria-hidden="true">&#10003;</span>
        By using QuickHire, you agree to our Privacy Policy and respectful hiring standards.
      </p>
    </form>

    <!-- REGISTER FORM -->
    <form id="registerForm" class="ln-auth-form" style="display:none;" method="POST" action="/QuickHire/Public/actions/register.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <?php if ($authError && $openAuth === 'register'): ?>
        <div class="ln-alert-error"><?= htmlspecialchars($authError) ?></div>
      <?php endif; ?>
      <div class="ln-role-toggle">
        <label class="ln-role-opt">
          <input type="radio" name="role" value="JOBSEEKER" checked>
          <span>👨‍💻 I'm a Jobseeker</span>
        </label>
        <label class="ln-role-opt">
          <input type="radio" name="role" value="EMPLOYER">
          <span>🏢 I'm an Employer</span>
        </label>
      </div>
      <div class="ln-form-row">
        <div class="ln-form-group"><label>First Name</label><input type="text" name="first_name" placeholder="John" required></div>
        <div class="ln-form-group"><label>Last Name</label><input type="text" name="last_name" placeholder="Doe" required></div>
      </div>
      <div class="ln-form-group"><label>Email</label><input type="email" name="email" placeholder="you@example.com" required></div>
      <div class="ln-form-group"><label>Password</label><input type="password" name="password" placeholder="Min. 8 characters" required></div>
      <div class="ln-form-group"><label>Confirm Password</label><input type="password" name="password_confirm" placeholder="Repeat password" required></div>
      <div style="display:flex;justify-content:center;margin-bottom:12px;">
        <div class="g-recaptcha" data-sitekey="<?= $rcSiteKey ?>" data-theme="dark"></div>
      </div>
      <button type="submit" class="ln-btn-primary ln-btn-full">Create Account</button>
    </form>

    <!-- FORGOT PASSWORD FORM -->
    <div id="forgotForm" class="ln-auth-form" style="display:none;">
      <?php if ($authError && $openAuth === 'forgot'): ?>
        <div class="ln-alert-error"><?= htmlspecialchars($authError) ?></div>
      <?php endif; ?>
      <?php if ($authSuccess && $openAuth === 'forgot'): ?>
        <div class="ln-alert-success"><?= htmlspecialchars($authSuccess) ?></div>
      <?php endif; ?>

      <form method="POST" action="/QuickHire/Public/actions/request_password_reset.php" style="display:grid;gap:14px;margin-bottom:16px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <div class="ln-form-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="you@example.com" required>
        </div>
        <button type="submit" class="ln-btn-primary ln-btn-full">Send Reset Code</button>
      </form>

      <form method="POST" action="/QuickHire/Public/actions/reset_password_with_code.php" style="display:grid;gap:14px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <div class="ln-form-group"><label>Email</label><input type="email" name="email" placeholder="same email" required></div>
        <div class="ln-form-group"><label>Code</label><input name="reset_code" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="6-digit code" required></div>
        <div class="ln-form-group"><label>New Password</label><input type="password" name="new_password" placeholder="Min. 8 characters" required minlength="8"></div>
        <div class="ln-form-group"><label>Confirm Password</label><input type="password" name="confirm_password" placeholder="Repeat password" required minlength="8"></div>
        <button type="submit" class="ln-btn-primary ln-btn-full">Reset Password</button>
      </form>

      <button type="button" onclick="switchTab('login')" style="margin-top:10px;background:none;border:0;color:#8b5cf6;font-weight:800;cursor:pointer;width:100%;padding:8px;">Back to Sign In</button>
    </div>
  </div>
</div>

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
</body>
</html>
