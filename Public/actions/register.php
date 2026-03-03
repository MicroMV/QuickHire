<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Services\AuthService;

Session::start();

$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$auth = new AuthService($db->pdo());

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    Session::flash('error', 'Security check failed. Please try again.');
    header('Location: /QuickHire/public/index.php');
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
    header('Location: /QuickHire/public/index.php?open=login');
    exit;

} catch (Exception $e) {
    Session::flash('error', $e->getMessage());
    header('Location: /QuickHire/public/index.php?open=register');
    exit;
}