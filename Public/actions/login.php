<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Services\AuthService;

Session::start();

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);
$auth = new AuthService($db->pdo());

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    Session::flash('error', 'Security check failed. Please try again.');
    header('Location: /QuickHire/public/index.php');
    exit;
}

try {
    $user = $auth->login($_POST['email'] ?? '', $_POST['password'] ?? '');

    // Always redirect to the appropriate dashboard — the dashboard handles
    // the profile-completion overlay if is_profile_complete = 0
    header('Location: /QuickHire/public/' . ($user['role'] === 'EMPLOYER' ? 'employer-dashboard.php' : 'jobseeker-dashboard.php'));
    exit;

} catch (Exception $e) {
    Session::flash('error', $e->getMessage());
    header('Location: /QuickHire/public/index.php?open=login');
    exit;
}