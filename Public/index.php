<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Csrf;

Session::start();

$error = Session::flash('error');
$success = Session::flash('success');

$open = $_GET['open'] ?? ''; // login | register
$csrf = Csrf::token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>QuickHire</title>

  <link rel="stylesheet" href="assets/css/landingPage.css">
  <script src="assets/js/auth-modal.js" defer></script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Kalam:wght@700&display=swap" rel="stylesheet">

  <style>
    /* Minimal modal CSS (keep or move into your landingPage.css) */
    .modal { position: fixed; inset: 0; display: none; z-index: 50; }
    .modal.is-open { display: block; }
    .modal__backdrop { position:absolute; inset:0; background: rgba(0,0,0,.35); }
    .modal__panel {
      position: relative;
      max-width: 440px;
      margin: 8vh auto;
      background: #fff;
      border-radius: 16px;
      padding: 22px;
      box-shadow: 0 20px 50px rgba(0,0,0,.15);
      font-family: Inter, sans-serif;
    }
    .modal__close { position:absolute; right:14px; top:10px; font-size: 22px; border:0; background: transparent; cursor: pointer; }
    .modal__title { margin: 0 0 14px; font-weight: 800; }
    .modal__label { display:block; margin: 10px 0 6px; font-weight: 600; }
    .modal__input, .modal__select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 10px; }
    .modal__submit { width:100%; margin-top: 14px; padding: 12px; border:0; border-radius: 12px; background:#1f6f82; color:#fff; font-weight: 800; cursor:pointer; }
    .modal__row { display:flex; justify-content: space-between; align-items:center; margin-top: 10px; }
    .modal__link { color:#1f6f82; font-weight: 700; text-decoration: none; }
    .notice { max-width: 820px; margin: 18px auto 0; padding: 12px 16px; border-radius: 12px; font-family: Inter, sans-serif; }
    .notice.error { background: #ffe1e1; color:#7a0b0b; }
    .notice.success { background: #e6ffef; color:#0c5a2a; }
    .tabs { display:flex; gap:8px; margin-bottom: 12px; }
    .tab { flex:1; border:1px solid #e5e5e5; padding:10px; border-radius:12px; background:#fff; cursor:pointer; font-weight:800; }
    .tab.is-active { border-color:#1f6f82; color:#1f6f82; }
    .hidden { display:none; }
  </style>
</head>

<body>
  <!-- top bar -->
  <header class="topbar">
    <div class="topbar__inner">
      <a href="index.php" class="brand">
        <img src="images/quickhire-logo.png" alt="QuickHire" class="brand__logo">
      </a>

      <div class="topbar__actions">
        <button type="button" class="btn btn--outline" data-open="login">Log in</button>
        <button type="button" class="btn btn--primary" data-open="register">Register</button>
      </div>
    </div>
  </header>

  <?php if ($error): ?>
    <div class="notice error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="notice success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- hero -->
  <main class="hero">
    <h1 class="hero__title">
      Find your desire,<br>
      get hired with <span class="hero__brand">QuickHire</span>
    </h1>

    <p class="hero__subtitle">
      A web-based job recruitment platform designed to help job seekers quickly find
      suitable employment and participate in interviews online.
    </p>

    <button class="btn btn--cta" type="button" data-open="register">Apply Now!</button>
  </main>

  <!-- AUTH MODAL (login/register in 1 modal) -->
  <div class="modal" id="authModal" aria-hidden="true">
    <div class="modal__backdrop" data-close></div>

    <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="authTitle">
      <button class="modal__close" type="button" data-close aria-label="Close">×</button>

      <h2 class="modal__title" id="authTitle">Welcome to QuickHire</h2>

      <div class="tabs">
        <button class="tab" type="button" data-tab="login">Log in</button>
        <button class="tab" type="button" data-tab="register">Register</button>
      </div>

      <!-- LOGIN FORM -->
      <form id="loginForm" method="POST" action="actions/login.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <label class="modal__label" for="login_email">Email Account</label>
        <input class="modal__input" id="login_email" name="email" type="email" required>

        <label class="modal__label" for="login_password">Password</label>
        <input class="modal__input" id="login_password" name="password" type="password" required>

        <div class="modal__row">
          <span></span>
          <a class="modal__link" href="#" onclick="alert('Forgot password feature can be added next.'); return false;">Forgot Password</a>
        </div>

        <button class="modal__submit" type="submit">Login</button>
      </form>

      <!-- REGISTER FORM -->
      <form id="registerForm" class="hidden" method="POST" action="actions/register.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <label class="modal__label" for="role">Register as</label>
        <select class="modal__select" id="role" name="role" required>
          <option value="">Select role</option>
          <option value="JOBSEEKER">Jobseeker</option>
          <option value="EMPLOYER">Employer</option>
        </select>

        <label class="modal__label" for="first_name">First Name</label>
        <input class="modal__input" id="first_name" name="first_name" type="text" required>

        <label class="modal__label" for="last_name">Last Name</label>
        <input class="modal__input" id="last_name" name="last_name" type="text" required>

        <label class="modal__label" for="reg_email">Email</label>
        <input class="modal__input" id="reg_email" name="email" type="email" required>

        <label class="modal__label" for="reg_password">Password</label>
        <input class="modal__input" id="reg_password" name="password" type="password" required>

        <label class="modal__label" for="password_confirm">Confirm Password</label>
        <input class="modal__input" id="password_confirm" name="password_confirm" type="password" required>

        <button class="modal__submit" type="submit">Create Account</button>
      </form>
    </div>
  </div>

  <script>
    // auto-open based on query string (?open=login or ?open=register)
    window.__OPEN_AUTH__ = "<?= htmlspecialchars($open) ?>";
  </script>
</body>
</html>