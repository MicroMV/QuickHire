<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Session;

Session::start();
Auth::requireLogin();

$role = Auth::role();
$redirect = $role === 'EMPLOYER'
    ? '/QuickHire/Public/employer-dashboard.php'
    : '/QuickHire/Public/jobseeker-dashboard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    Session::flash('error', 'Security check failed. Please try again.');
    header('Location: ' . $redirect);
    exit;
}

$current = (string)($_POST['current_password'] ?? '');
$new = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

try {
    if (strlen($new) < 8) {
        throw new Exception('New password must be at least 8 characters.');
    }
    if ($new !== $confirm) {
        throw new Exception('New passwords do not match.');
    }

    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $pdo = $db->pdo();

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([Auth::userId()]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        throw new Exception('Current password is incorrect.');
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->execute([$hash, Auth::userId()]);

    Session::flash('success', 'Password changed successfully.');
} catch (Exception $e) {
    Session::flash('error', $e->getMessage());
}

header('Location: ' . $redirect);
exit;
