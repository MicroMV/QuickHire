<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$room = $input['room'] ?? '';

if (empty($room)) {
    echo json_encode(['ok' => false, 'error' => 'Missing room']);
    exit;
}

$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$userId = Auth::userId();

try {
    // Get call info with participant details
    $stmt = $pdo->prepare("
        SELECT c.*, 
               e.first_name as emp_first_name, e.last_name as emp_last_name, e.role as emp_role,
               j.first_name as js_first_name, j.last_name as js_last_name, j.role as js_role
        FROM calls c
        JOIN users e ON e.id = c.employer_user_id
        JOIN users j ON j.id = c.jobseeker_user_id
        WHERE c.room_code = ?
        LIMIT 1
    ");
    $stmt->execute([$room]);
    $call = $stmt->fetch();
    
    if (!$call) {
        echo json_encode(['ok' => false, 'error' => 'Call not found']);
        exit;
    }
    
    // Verify user is part of this call
    if ($userId !== (int)$call['employer_user_id'] && $userId !== (int)$call['jobseeker_user_id']) {
        echo json_encode(['ok' => false, 'error' => 'Not authorized']);
        exit;
    }
    
    // Determine partner info based on current user
    $partner = null;
    if ($userId === (int)$call['employer_user_id']) {
        // Current user is employer, partner is jobseeker
        $partner = [
            'first_name' => $call['js_first_name'],
            'last_name' => $call['js_last_name'],
            'role' => $call['js_role']
        ];
    } else {
        // Current user is jobseeker, partner is employer
        $partner = [
            'first_name' => $call['emp_first_name'],
            'last_name' => $call['emp_last_name'],
            'role' => $call['emp_role']
        ];
    }
    
    echo json_encode([
        'ok' => true,
        'partner' => $partner,
        'room_code' => $call['room_code'],
        'status' => $call['status']
    ]);
    
} catch (Exception $e) {
    error_log("Get call info error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to get call info']);
}