<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Services\Mailer;

Session::start();
$redirect = '/QuickHire/Public/index.php?open=forgot';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    Session::flash('error', 'Security check failed. Please try again.');
    header('Location: ' . $redirect);
    exit;
}

function qhEnsurePasswordResetTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_reset_codes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_password_reset_user (user_id),
            INDEX idx_password_reset_email (email),
            CONSTRAINT fk_password_reset_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $pdo = $db->pdo();
    qhEnsurePasswordResetTable($pdo);

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Enter a valid email address.');
    }

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        Session::flash('success', 'If that email exists, QuickHire sent a reset code.');
        header('Location: ' . $redirect);
        exit;
    }

    $code = (string)random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);

    $pdo->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
        ->execute([(int)$user['id']]);
    $insert = $pdo->prepare('INSERT INTO password_reset_codes (user_id, email, code_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))');
    $insert->execute([(int)$user['id'], $email, $hash]);

    $mailer = new Mailer($config['mail'] ?? []);
    $sent = $mailer->send(
        $email,
        'QuickHire password reset code',
        "Your QuickHire password reset code is: {$code}\n\nThis code expires in 15 minutes."
    );

    if ($sent) {
        Session::flash('success', 'A QuickHire reset code was sent to your email.');
    } else {
        Session::set('dev_password_reset_code', $code);
        Session::flash('success', 'Reset code generated. Mail is not configured locally, so your development code is: ' . $code);
    }
} catch (Exception $e) {
    Session::flash('error', $e->getMessage());
}

header('Location: ' . $redirect);
exit;
