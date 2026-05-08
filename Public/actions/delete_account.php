<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Session;

Session::start();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /QuickHire/Public/index.php');
    exit;
}

$role = Auth::role();
$redirect = $role === 'EMPLOYER'
    ? '/QuickHire/Public/employer-dashboard.php'
    : '/QuickHire/Public/jobseeker-dashboard.php';

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    Session::flash('error', 'Security check failed. Please try again.');
    header('Location: ' . $redirect);
    exit;
}

if (trim((string)($_POST['confirm_delete'] ?? '')) !== 'DELETE') {
    Session::flash('error', 'Type DELETE to confirm account deletion.');
    header('Location: ' . $redirect);
    exit;
}

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();
$userId = Auth::userId();

function qhTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function qhDeleteIfTable(PDO $pdo, string $table, string $sql, array $params): void
{
    if (!qhTableExists($pdo, $table)) {
        return;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function qhCollectUploadPath(?string $path, string $publicRoot): ?string
{
    $path = trim((string) $path);
    if ($path === '' || str_contains($path, '..')) {
        return null;
    }

    $fullPath = realpath($publicRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    if (!$fullPath || strpos($fullPath, $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }

    return $fullPath;
}

try {
    $publicRoot = realpath(__DIR__ . '/..');
    $uploadPaths = [];

    if ($publicRoot && qhTableExists($pdo, 'jobseeker_profiles')) {
        $stmt = $pdo->prepare("SELECT profile_picture_url, resume_url FROM jobseeker_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch() ?: [];
        foreach (['profile_picture_url', 'resume_url'] as $column) {
            $path = qhCollectUploadPath($profile[$column] ?? null, $publicRoot);
            if ($path) {
                $uploadPaths[] = $path;
            }
        }
    }

    if ($publicRoot && qhTableExists($pdo, 'employer_profiles')) {
        $stmt = $pdo->prepare("SELECT profile_picture_url FROM employer_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch() ?: [];
        $path = qhCollectUploadPath($profile['profile_picture_url'] ?? null, $publicRoot);
        if ($path) {
            $uploadPaths[] = $path;
        }
    }

    $pdo->beginTransaction();

    if (qhTableExists($pdo, 'messages')) {
        if (qhTableExists($pdo, 'conversations')) {
            $stmt = $pdo->prepare("
                DELETE FROM messages
                WHERE sender_id = ?
                   OR conversation_id IN (
                        SELECT id FROM conversations WHERE employer_id = ? OR jobseeker_id = ?
                   )
            ");
            $stmt->execute([$userId, $userId, $userId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM messages WHERE sender_id = ?");
            $stmt->execute([$userId]);
        }
    }

    qhDeleteIfTable($pdo, 'conversations', "DELETE FROM conversations WHERE employer_id = ? OR jobseeker_id = ?", [$userId, $userId]);
    qhDeleteIfTable($pdo, 'webrtc_signals', "DELETE FROM webrtc_signals WHERE sender_id = ?", [$userId]);
    qhDeleteIfTable($pdo, 'calls', "DELETE FROM calls WHERE employer_user_id = ? OR jobseeker_user_id = ?", [$userId, $userId]);
    if (qhTableExists($pdo, 'job_post_skills') && qhTableExists($pdo, 'job_posts')) {
        $stmt = $pdo->prepare("
            DELETE FROM job_post_skills
            WHERE job_post_id IN (SELECT id FROM job_posts WHERE employer_id = ?)
        ");
        $stmt->execute([$userId]);
    }
    qhDeleteIfTable($pdo, 'job_posts', "DELETE FROM job_posts WHERE employer_id = ?", [$userId]);
    if (qhTableExists($pdo, 'matchmaking_queue_skills') && qhTableExists($pdo, 'matchmaking_queue')) {
        $stmt = $pdo->prepare("
            DELETE FROM matchmaking_queue_skills
            WHERE queue_id IN (SELECT id FROM matchmaking_queue WHERE user_id = ?)
        ");
        $stmt->execute([$userId]);
    }
    qhDeleteIfTable($pdo, 'matchmaking_queue', "DELETE FROM matchmaking_queue WHERE user_id = ?", [$userId]);
    qhDeleteIfTable($pdo, 'employer_required_skills', "DELETE FROM employer_required_skills WHERE employer_user_id = ?", [$userId]);
    qhDeleteIfTable($pdo, 'jobseeker_skills', "DELETE FROM jobseeker_skills WHERE jobseeker_user_id = ?", [$userId]);
    qhDeleteIfTable($pdo, 'employer_profiles', "DELETE FROM employer_profiles WHERE user_id = ?", [$userId]);
    qhDeleteIfTable($pdo, 'jobseeker_profiles', "DELETE FROM jobseeker_profiles WHERE user_id = ?", [$userId]);

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    $pdo->commit();

    foreach (array_unique($uploadPaths) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    Session::destroy();
    header('Location: /QuickHire/Public/index.php?account_deleted=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Delete account error: ' . $e->getMessage());
    Session::flash('error', 'Unable to delete your account right now. Please try again.');
    header('Location: ' . $redirect);
    exit;
}
