<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\ProfileService;
use Rongie\QuickHire\Services\FileUpload;

Session::start();
Auth::requireLogin();

$config = require __DIR__ . '/../config/config.php';
$db = new Database($config['db']);

$profileService = new ProfileService($db->pdo(), new FileUpload());
$userId = Auth::userId();
$role = Auth::role();

echo "<h2>Profile Debug Information</h2>";
echo "<p><strong>User ID:</strong> $userId</p>";
echo "<p><strong>Role:</strong> $role</p>";

if ($role === 'JOBSEEKER') {
    $profile = $profileService->getJobseeker($userId);
    echo "<h3>Jobseeker Profile Data:</h3>";
} else {
    $profile = $profileService->getEmployer($userId);
    echo "<h3>Employer Profile Data:</h3>";
}

echo "<pre>";
print_r($profile);
echo "</pre>";

// Check if uploads directories exist
$uploadsDir = __DIR__ . '/uploads';
$avatarsDir = __DIR__ . '/uploads/avatars';

echo "<h3>Directory Status:</h3>";
echo "<p><strong>Uploads dir exists:</strong> " . (is_dir($uploadsDir) ? 'YES' : 'NO') . "</p>";
echo "<p><strong>Avatars dir exists:</strong> " . (is_dir($avatarsDir) ? 'YES' : 'NO') . "</p>";

if (is_dir($avatarsDir)) {
    $files = scandir($avatarsDir);
    echo "<p><strong>Files in avatars directory:</strong></p>";
    echo "<pre>";
    print_r($files);
    echo "</pre>";
}

// Get user info
$userStmt = $db->pdo()->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch();

echo "<h3>User Information:</h3>";
echo "<pre>";
print_r($userInfo);
echo "</pre>";
?>