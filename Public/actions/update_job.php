<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Csrf;
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

    $jobService = new JobService();
    $userId = Auth::userId();
    $jobId = (int)($_POST['job_id'] ?? 0);

    if (!$jobId) {
        echo json_encode(['ok' => false, 'error' => 'Missing job ID']);
        exit;
    }

    // Verify the job belongs to the current user
    $existingJob = $jobService->getJobPost($jobId);
    if (!$existingJob || $existingJob['employer_id'] != $userId) {
        echo json_encode(['ok' => false, 'error' => 'Job not found or not authorized']);
        exit;
    }

    $jobData = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'role_title' => $_POST['role_title'] ?? null,
        'employment_type' => $_POST['employment_type'] ?? null,
        'country' => $_POST['country'] ?? null,
        'rate_per_hour' => !empty($_POST['rate_per_hour']) ? (float)$_POST['rate_per_hour'] : null,
        'hours_per_week' => !empty($_POST['hours_per_week']) ? (int)$_POST['hours_per_week'] : null,
    ];

    $skillIds = $_POST['skill_ids'] ?? [];

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

} catch (Exception $e) {
    error_log("Update job error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An error occurred while updating the job post']);
}