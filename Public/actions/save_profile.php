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

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    Session::flash('error', 'Security check failed.');
    header("Location: /QuickHire/Public/complete-profile.php");
    exit;
}

$config = require __DIR__ . '/../../config/config.php';
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
    Session::flash('error', $e->getMessage());
    header("Location: /QuickHire/Public/complete-profile.php");
    exit;
}