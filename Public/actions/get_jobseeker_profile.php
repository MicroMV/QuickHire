<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\SearchService;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

if (Auth::role() !== 'EMPLOYER') {
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$jobseekerId = (int)($_GET['jobseeker_id'] ?? 0);
if ($jobseekerId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid jobseeker']);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $service = new SearchService($db->pdo());
    $profile = $service->getJobSeekerDetails($jobseekerId);

    if (!$profile) {
        echo json_encode(['ok' => false, 'error' => 'Jobseeker profile not found']);
        exit;
    }

    echo json_encode(['ok' => true, 'profile' => $profile]);
} catch (Exception $e) {
    error_log('Get jobseeker profile error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to load jobseeker profile']);
}
