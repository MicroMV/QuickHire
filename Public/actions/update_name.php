<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Core\Csrf;

header('Content-Type: application/json');

Session::start();
Auth::requireLogin();

// Verify CSRF token
if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');

// Validate input
if (empty($firstName) || empty($lastName)) {
    echo json_encode(['ok' => false, 'error' => 'First name and last name are required']);
    exit;
}

// Validate name format (letters, spaces, hyphens only)
if (!preg_match('/^[a-zA-Z\s\-]+$/', $firstName) || !preg_match('/^[a-zA-Z\s\-]+$/', $lastName)) {
    echo json_encode(['ok' => false, 'error' => 'Names can only contain letters, spaces, and hyphens']);
    exit;
}

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    
    $userId = Auth::userId();
    
    // Update user's name
    $stmt = $db->pdo()->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
    $stmt->execute([$firstName, $lastName, $userId]);
    
    echo json_encode([
        'ok' => true,
        'first_name' => $firstName,
        'last_name' => $lastName
    ]);
    
} catch (Exception $e) {
    error_log("Update name error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to update name']);
}
