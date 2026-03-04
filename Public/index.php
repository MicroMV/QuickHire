<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Csrf;

Session::start();

$error = Session::flash('error');
$success = Session::flash('success');

$open = $_GET['open'] ?? '';
if (($error || $success) && $open === '') {
  $open = $error ? 'login' : 'register';
}
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

</head>

<body>
  <!-- top bar -->
  <header class="topbar">
    <div class="topbar__inner">
      <a href="index.php" class="brand">
        <img src="images/quickhire-logo.jpg" alt="QuickHire" class="brand__logo">
      </a>

      <div class="topbar__actions">
        <button type="button" class="btn btn--outline" data-open="login">Log in</button>
        <button type="button" class="btn btn--primary" data-open="register">Register</button>
      </div>
    </div>
  </header>

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
      <?php if ($error): ?>
        <div class="modal__alert modal__alert--error">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="modal__alert modal__alert--success">
          <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

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