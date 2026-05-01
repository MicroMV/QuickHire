<?php
header('Content-Type: application/json');

require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\JobService;

Session::start();

try {
    // Check authentication
    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized - Please log in']);
        exit;
    }

    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    // Initialize database and service
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $jobService = new JobService($db->pdo());

    $userRole = Auth::role();
    $userId = Auth::userId();

    if ($userRole === 'EMPLOYER') {
        // Get employer's job posts
        $result = $jobService->getEmployerJobPosts($userId);
    } else if ($userRole === 'JOBSEEKER') {
        // Get all active job posts for job seekers
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);
        $search = trim($_GET['search'] ?? '');
        $role   = trim($_GET['role'] ?? '');
        $type   = trim($_GET['type'] ?? '');
        $country = trim($_GET['country'] ?? '');
        
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        
        $result = $jobService->getActiveJobPosts($limit, $offset, $search, $role, $type, $country);
    } else {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Access denied - Invalid user role: ' . $userRole]);
        exit;
    }

    // Add debug information
    if (!$result['ok']) {
        error_log('Job posts error for user ' . $userId . ' (' . $userRole . '): ' . $result['error']);
    }

    echo json_encode($result);

} catch (Throwable $e) {
    error_log('Get job posts error: ' . $e->getMessage() . ' - Stack: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}