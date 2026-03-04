<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\FileUpload;
use Rongie\QuickHire\Services\ProfileService;

Session::start();
Auth::requireLogin();

$config = require __DIR__ . '/../config/config.php';
$db = new Database($config['db']);

$profileService = new ProfileService($db->pdo(), new FileUpload());

$userId = Auth::userId();
$role = Auth::role();

$error = Session::flash('error');
$success = Session::flash('success');

$js = ($role === 'JOBSEEKER') ? $profileService->getJobseeker($userId) : [];
$emp = ($role === 'EMPLOYER') ? $profileService->getEmployer($userId) : [];

$csrf = Csrf::token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Complete Profile - QuickHire</title>
  <link rel="stylesheet" href="assets/css/landingPage.css">

  <style>
    .wrap{max-width:900px;margin:40px auto;padding:0 18px;font-family:Inter,system-ui,Arial;}
    .card{background:#fff;border:1px solid #eee;border-radius:16px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,.06);}
    .h{font-size:28px;margin:0 0 8px;font-weight:900}
    .sub{color:#555;margin:0 0 18px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .full{grid-column:1/-1}
    label{display:block;font-weight:700;margin:10px 0 6px}
    input,select,textarea{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:12px}
    textarea{min-height:110px;resize:vertical}
    .btnsave{margin-top:16px;background:#1f6f82;color:#fff;border:0;border-radius:14px;padding:12px 18px;font-weight:900;cursor:pointer}
    .alert{padding:12px 14px;border-radius:12px;margin:0 0 14px;font-weight:800}
    .alert.err{background:#ffe1e1;color:#7a0b0b}
    .alert.ok{background:#e6ffef;color:#0c5a2a}
    .hint{font-size:12px;color:#666;margin-top:6px}
    @media (max-width:720px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1 class="h">Complete your profile</h1>
      <p class="sub">Role: <strong><?= htmlspecialchars($role) ?></strong></p>

      <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>

      <?php if ($role === 'JOBSEEKER'): ?>
        <form method="POST" action="actions/save_profile.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="profile_type" value="JOBSEEKER">

          <div class="grid">
            <div class="full">
              <label>Profile picture (JPG/PNG)</label>
              <input type="file" name="profile_picture" accept="image/*">
            </div>

            <div>
              <label>Desired Job Role *</label>
              <input name="role_title" value="<?= htmlspecialchars($js['role_title'] ?? '') ?>" required>
            </div>

            <div>
              <label>Rate per Hour (USD) *</label>
              <input name="rate_per_hour" type="number" step="0.01" value="<?= htmlspecialchars($js['rate_per_hour'] ?? '') ?>" required>
            </div>

            <div>
              <label>Available Hours per Day *</label>
              <input name="available_time" value="<?= htmlspecialchars($js['available_time'] ?? '') ?>" required>
            </div>

            <div>
              <label>Country *</label>
              <input name="country" value="<?= htmlspecialchars($js['country'] ?? '') ?>" required>
            </div>

            <div>
              <label>English Mastery *</label>
              <select name="english_mastery" required>
                <?php
                  $levels = ['BEGINNER','INTERMEDIATE','ADVANCED','FLUENT','NATIVE'];
                  $cur = $js['english_mastery'] ?? '';
                  echo '<option value="">Select</option>';
                  foreach ($levels as $lv) {
                    $sel = ($cur === $lv) ? 'selected' : '';
                    echo "<option value=\"$lv\" $sel>$lv</option>";
                  }
                ?>
              </select>
            </div>

            <div>
              <label>Bachelor's Degree</label>
              <input name="bachelors_degree" value="<?= htmlspecialchars($js['bachelors_degree'] ?? '') ?>">
            </div>

            <div>
              <label>Portfolio/Website</label>
              <input name="portfolio_url" value="<?= htmlspecialchars($js['portfolio_url'] ?? '') ?>">
            </div>

            <div>
              <label>Age</label>
              <input name="age" type="number" value="<?= htmlspecialchars($js['age'] ?? '') ?>">
            </div>

            <div>
              <label>Gender</label>
              <select name="gender">
                <?php $g = $js['gender'] ?? ''; ?>
                <option value="">Prefer not to say</option>
                <option value="MALE" <?= $g==='MALE'?'selected':'' ?>>Male</option>
                <option value="FEMALE" <?= $g==='FEMALE'?'selected':'' ?>>Female</option>
                <option value="OTHER" <?= $g==='OTHER'?'selected':'' ?>>Other</option>
              </select>
            </div>

            <div class="full">
              <label>Profile Description *</label>
              <textarea name="profile_description" required><?= htmlspecialchars($js['profile_description'] ?? '') ?></textarea>
            </div>

            <div class="full">
              <label>Attached Resume (PDF)</label>
              <input type="file" name="resume" accept="application/pdf">
              <div class="hint">If you upload a new one, it replaces the old resume.</div>
            </div>
          </div>

          <button class="btnsave" type="submit">Save Profile</button>
        </form>

      <?php elseif ($role === 'EMPLOYER'): ?>
        <form method="POST" action="actions/save_profile.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="profile_type" value="EMPLOYER">

          <div class="grid">
            <div class="full">
              <label>Profile picture (JPG/PNG/WEBP)</label>
              <input type="file" name="profile_picture" accept="image/*">
            </div>

            <div>
              <label>Country *</label>
              <input name="country" value="<?= htmlspecialchars($emp['country'] ?? '') ?>" required>
            </div>

            <div>
              <label>Business Name / Company name *</label>
              <input name="company_name" value="<?= htmlspecialchars($emp['company_name'] ?? '') ?>" required>
            </div>
          </div>

          <button class="btnsave" type="submit">Save Profile</button>
        </form>
      <?php else: ?>
        <div class="alert err">Unknown role. Please log out and register again.</div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>