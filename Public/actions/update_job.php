<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Csrf;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\JobService;

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

    if (Auth::role() !== 'EMPLOYER') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Only employers can update jobs']);
        exit;
    }

    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $pdo = $db->pdo();
    $jobService = new JobService($pdo);
    $userId = Auth::userId();
    $jobId = (int)($_POST['job_id'] ?? 0);

    if (!$jobId) {
        echo json_encode(['ok' => false, 'error' => 'Missing job ID']);
        exit;
    }

    // Verify the job belongs to the current user
    $stmt = $pdo->prepare("SELECT id FROM job_posts WHERE id = ? AND employer_id = ? LIMIT 1");
    $stmt->execute([$jobId, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Job not found or not authorized']);
        exit;
    }

    $jobData = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'role_title' => trim($_POST['role_title'] ?? '') ?: null,
        'employment_type' => trim($_POST['employment_type'] ?? '') ?: null,
        'country' => trim($_POST['country'] ?? '') ?: null,
        'rate_per_hour' => isset($_POST['rate_per_hour']) && $_POST['rate_per_hour'] !== '' ? (float)$_POST['rate_per_hour'] : null,
        'hours_per_week' => isset($_POST['hours_per_week']) && $_POST['hours_per_week'] !== '' ? (int)$_POST['hours_per_week'] : null,
    ];

    $skillIds = [];
    foreach ((array)($_POST['skill_ids'] ?? []) as $skillId) {
        if (is_numeric($skillId) && (int)$skillId > 0) {
            $skillIds[] = (int)$skillId;
        }
    }

    // Validate required fields
    if (empty($jobData['title'])) {
        echo json_encode(['ok' => false, 'error' => 'Job title is required']);
        exit;
    }

    if (empty($jobData['description'])) {
        echo json_encode(['ok' => false, 'error' => 'Job description is required']);
        exit;
    }

    // Update the job post
    $success = $jobService->updateJobPost($jobId, $jobData, $skillIds);

    if ($success) {
        echo json_encode(['ok' => true, 'message' => 'Job post updated successfully']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to update job post']);
    }

} catch (Throwable $e) {
    error_log("Update job error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred while updating the job post']);
}
