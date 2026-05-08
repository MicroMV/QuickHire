<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\FileUpload;
use Rongie\QuickHire\Services\ProfileService;

Session::start();
Auth::requireLogin();

function profileSaveRedirectTarget(): string
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (str_contains($referer, 'jobseeker-dashboard')) {
        return '/QuickHire/Public/jobseeker-dashboard.php';
    }
    if (str_contains($referer, 'employer-dashboard')) {
        return '/QuickHire/Public/employer-dashboard.php';
    }

    $role = Auth::role();
    if ($role === 'JOBSEEKER') {
        return '/QuickHire/Public/jobseeker-dashboard.php';
    }
    if ($role === 'EMPLOYER') {
        return '/QuickHire/Public/employer-dashboard.php';
    }

    return '/QuickHire/Public/index.php';
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    Session::flash('error', 'Security check failed.');
    header('Location: ' . profileSaveRedirectTarget());
    exit;
}

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);

$service = new ProfileService($db->pdo(), new FileUpload());

$userId = Auth::userId();
$role = Auth::role();
$type = $_POST['profile_type'] ?? '';

$publicRoot = dirname(__DIR__); // .../Public
$avatarAbs = $publicRoot . '/uploads/avatars';
$resumeAbs = $publicRoot . '/uploads/resumes';
$avatarRel = 'uploads/avatars';
$resumeRel = 'uploads/resumes';

try {
    if ($role === 'JOBSEEKER' && $type === 'JOBSEEKER') {
        $service->saveJobseeker($userId, $_POST, $_FILES, $avatarAbs, $avatarRel, $resumeAbs, $resumeRel);
        Session::flash('success', 'Profile saved!');
        header("Location: /QuickHire/Public/jobseeker-dashboard.php");
        exit;
    }

    if ($role === 'EMPLOYER' && $type === 'EMPLOYER') {
        $service->saveEmployer($userId, $_POST, $_FILES, $avatarAbs, $avatarRel);
        Session::flash('success', 'Profile saved!');
        header("Location: /QuickHire/Public/employer-dashboard.php");
        exit;
    }

    throw new Exception("Invalid profile submission.");
} catch (Exception $e) {
    error_log("Profile save error: " . $e->getMessage());
    error_log("Files received: " . print_r($_FILES, true));
    Session::flash('error', $e->getMessage());
    header('Location: ' . profileSaveRedirectTarget());
    exit;
}
