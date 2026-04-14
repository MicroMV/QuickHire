<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['ok' => false, 'error' => 'Invalid request method']);
        exit;
    }

    if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $pdo = $db->pdo();

    $userId = Auth::userId();
    $jobId = (int)($_POST['job_id'] ?? 0);

    if (!$jobId) {
        echo json_encode(['ok' => false, 'error' => 'Missing job ID']);
        exit;
    }

    // Verify the job belongs to the current user
    $stmt = $pdo->prepare("SELECT id FROM job_posts WHERE id = ? AND employer_id = ?");
    $stmt->execute([$jobId, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Job not found or not authorized']);
        exit;
    }

    // Delete job skills first (foreign key constraint)
    $pdo->prepare("DELETE FROM job_post_skills WHERE job_post_id = ?")->execute([$jobId]);
    
    // Delete the job post
    $pdo->prepare("DELETE FROM job_posts WHERE id = ?")->execute([$jobId]);

    echo json_encode(['ok' => true, 'message' => 'Job post deleted successfully']);

} catch (Exception $e) {
    error_log("Delete job error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error occurred']);
}