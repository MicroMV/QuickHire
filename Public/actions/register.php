<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Services\AuthService;

Session::start();

$config = require __DIR__ . '/../../Config/config.php';

// reCAPTCHA verification
$recaptchaSecret = $config['recaptcha']['secret_key'] ?? '';
$recaptchaToken  = $_POST['g-recaptcha-response'] ?? '';
if (empty($recaptchaToken)) {
    Session::flash('error', 'Please complete the reCAPTCHA check.');
    header('Location: /QuickHire/Public/index.php?open=register');
    exit;
}
$rcVerify = file_get_contents(
    'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($recaptchaSecret) .
    '&response=' . urlencode($recaptchaToken) .
    '&remoteip=' . urlencode($_SERVER['REMOTE_ADDR'] ?? '')
);
$rcResult = json_decode($rcVerify, true);
if (empty($rcResult['success'])) {
    Session::flash('error', 'reCAPTCHA verification failed. Please try again.');
    header('Location: /QuickHire/Public/index.php?open=register');
    exit;
}

$db = new Database($config['db']);
$auth = new AuthService($db->pdo());

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    Session::flash('error', 'Security check failed. Please try again.');
    header('Location: /QuickHire/Public/index.php');
    exit;
}

try {
    $userId = $auth->register(
        $_POST['role'] ?? '',
        $_POST['first_name'] ?? '',
        $_POST['last_name'] ?? '',
        $_POST['email'] ?? '',
        $_POST['password'] ?? '',
        $_POST['password_confirm'] ?? ''
    );

    Session::flash('success', 'Registration successful! You can now log in.');
    header('Location: /QuickHire/Public/index.php?open=login');
    exit;

} catch (Exception $e) {
    Session::flash('error', $e->getMessage());
    header('Location: /QuickHire/Public/index.php?open=register');
    exit;
}