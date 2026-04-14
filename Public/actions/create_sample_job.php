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

    // Initialize database and service
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $jobService = new JobService($db->pdo());

    // Create sample job post
    $result = $jobService->createJobPost(
        Auth::userId(),
        'Senior Full Stack Developer',
        'We are looking for an experienced Full Stack Developer to join our growing team. You will be responsible for developing and maintaining web applications using modern technologies like React, Node.js, and PostgreSQL. 

Requirements:
- 3+ years of experience with JavaScript and modern frameworks
- Experience with React and Node.js
- Knowledge of database design and SQL
- Experience with version control (Git)
- Strong problem-solving skills and attention to detail

What we offer:
- Competitive salary and benefits
- Remote work flexibility
- Professional development opportunities
- Collaborative team environment',
        'Full Stack Developer',
        'FULL_TIME',
        'Remote',
        75.00, // $75/hour
        [1, 2, 11, 17, 29] // JavaScript, Python, Node.js, React, MySQL
    );

    echo json_encode($result);

} catch (Throwable $e) {
    error_log('Create sample job error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}