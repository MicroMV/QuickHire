<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Session;

Session::start();
$redirect = '/QuickHire/Public/index.php?open=forgot';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    Session::flash('error', 'Security check failed. Please try again.');
    header('Location: ' . $redirect);
    exit;
}

$code = trim((string)($_POST['reset_code'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$new = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

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
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Enter the email for this reset code.');
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        throw new Exception('Enter the 6-digit reset code.');
    }
    if (strlen($new) < 8) {
        throw new Exception('New password must be at least 8 characters.');
    }
    if ($new !== $confirm) {
        throw new Exception('New passwords do not match.');
    }

    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $pdo = $db->pdo();
    qhEnsurePasswordResetTable($pdo);

    $userStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $userStmt->execute([$email]);
    $userId = (int)($userStmt->fetchColumn() ?: 0);
    if ($userId <= 0) {
        throw new Exception('Reset code is invalid or expired.');
    }

    $stmt = $pdo->prepare("
        SELECT id, code_hash
        FROM password_reset_codes
        WHERE user_id = ?
          AND used_at IS NULL
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($code, $row['code_hash'])) {
        throw new Exception('Reset code is invalid or expired.');
    }

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
    $pdo->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE id = ?')
        ->execute([(int)$row['id']]);
    $pdo->commit();

    Session::flash('success', 'Password reset successfully. You can sign in now.');
    $redirect = '/QuickHire/Public/index.php?open=login';
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Session::flash('error', $e->getMessage());
}

header('Location: ' . $redirect);
exit;
