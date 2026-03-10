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
    echo json_encode(['ok' => false, 'error' => 'Only employers can save preferences']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON input']);
    exit;
}

$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$userId = Auth::userId();
$skillIds = $input['skill_ids'] ?? [];

// Validate skill IDs
$skillIds = array_filter(array_map('intval', is_array($skillIds) ? $skillIds : []));

try {
    $pdo->beginTransaction();
    
    // Clear existing employer required skills
    $pdo->prepare("DELETE FROM employer_required_skills WHERE employer_user_id = ?")
        ->execute([$userId]);
    
    // Insert new required skills
    if (!empty($skillIds)) {
        $stmt = $pdo->prepare("INSERT INTO employer_required_skills (employer_user_id, skill_id) VALUES (?, ?)");
        foreach ($skillIds as $skillId) {
            $stmt->execute([$userId, $skillId]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'ok' => true, 
        'message' => 'Preferences saved successfully',
        'skills_count' => count($skillIds)
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Save employer preferences error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save preferences']);
}