<?php
header('Content-Type: application/json');

require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Services\JobService;

Session::start();

try {
    // Check authentication
    if (!Auth::isLoggedIn() || Auth::role() !== 'EMPLOYER') {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    // Validate CSRF token
    if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    // Get form data
    $jobId = (int)($_POST['job_id'] ?? 0);
    $isActive = (int)($_POST['is_active'] ?? 0);

    // Validate job ID
    if ($jobId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid job ID']);
        exit;
    }

    // Initialize database and service
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $jobService = new JobService($db->pdo());

    // Update job post status
    $result = $jobService->updateJobPostStatus($jobId, Auth::userId(), $isActive === 1);

    echo json_encode($result);

} catch (Throwable $e) {
    error_log('Toggle job status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}