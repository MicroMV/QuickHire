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
    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized - Please log in']);
        exit;
    }

    if (Auth::role() !== 'EMPLOYER') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Only employers can post jobs']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    if (!isset($_POST['csrf_token']) || !Csrf::validate($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $roleTitle    = trim($_POST['role_title'] ?? '') ?: null;
    $employmentType = trim($_POST['employment_type'] ?? '') ?: null;
    $country      = trim($_POST['country'] ?? '') ?: null;
    $ratePerHour  = isset($_POST['rate_per_hour']) && $_POST['rate_per_hour'] !== '' ? (float)$_POST['rate_per_hour'] : null;
    $hoursPerWeek = isset($_POST['hours_per_week']) && $_POST['hours_per_week'] !== '' ? (int)$_POST['hours_per_week'] : null;
    $skillIds     = $_POST['skill_ids'] ?? [];

    if (empty($title)) {
        echo json_encode(['ok' => false, 'error' => 'Job title is required']);
        exit;
    }

    if (empty($description)) {
        echo json_encode(['ok' => false, 'error' => 'Job description is required']);
        exit;
    }

    $validSkillIds = [];
    foreach ((array)$skillIds as $sid) {
        if (is_numeric($sid) && $sid > 0) {
            $validSkillIds[] = (int)$sid;
        }
    }

    $config     = require __DIR__ . '/../../Config/config.php';
    $db         = new Database($config['db']);
    $jobService = new JobService($db->pdo());

    $result = $jobService->createJobPost(
        Auth::userId(),
        $title,
        $description,
        $roleTitle,
        $employmentType,
        $country,
        $ratePerHour,
        $validSkillIds,
        $hoursPerWeek
    );

    echo json_encode($result);

} catch (Throwable $e) {
    error_log('Job posting error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
