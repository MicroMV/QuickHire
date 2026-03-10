<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

if (Auth::role() !== 'EMPLOYER') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Only employers can get preferences']);
    exit;
}

$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$userId = Auth::userId();

try {
    // Get employer's required skills from database
    $stmt = $pdo->prepare("SELECT skill_id FROM employer_required_skills WHERE employer_user_id = ?");
    $stmt->execute([$userId]);
    $skillIds = array_column($stmt->fetchAll(), 'skill_id');
    
    echo json_encode([
        'ok' => true,
        'skill_ids' => array_map('intval', $skillIds)
    ]);
    
} catch (Exception $e) {
    error_log("Get employer preferences error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to get preferences']);
}