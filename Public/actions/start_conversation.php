<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MessagingService;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

if (Auth::role() !== 'EMPLOYER') {
    echo json_encode(['ok' => false, 'error' => 'Access denied - not an employer']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method']);
    exit;
}

$jobseekerId = (int)($_POST['jobseeker_id'] ?? 0);

if (!$jobseekerId) {
    echo json_encode(['ok' => false, 'error' => 'Job seeker ID required']);
    exit;
}

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $messagingService = new MessagingService($db->pdo());

    $employerId = Auth::userId();

    // Verify job seeker exists
    $stmt = $db->pdo()->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? AND role = 'JOBSEEKER'");
    $stmt->execute([$jobseekerId]);
    $jobseeker = $stmt->fetch();
    
    if (!$jobseeker) {
        echo json_encode(['ok' => false, 'error' => 'Job seeker not found']);
        exit;
    }

    // Check if conversation already exists
    $existingStmt = $db->pdo()->prepare("SELECT id FROM conversations WHERE employer_id = ? AND jobseeker_id = ?");
    $existingStmt->execute([$employerId, $jobseekerId]);
    $existingConversation = $existingStmt->fetch();
    
    $isExisting = (bool)$existingConversation;

    // Get or create conversation
    $conversationId = $messagingService->getOrCreateConversation($employerId, $jobseekerId);

    echo json_encode([
        'ok' => true,
        'conversation_id' => $conversationId,
        'jobseeker_name' => $jobseeker['first_name'] . ' ' . $jobseeker['last_name'],
        'is_existing' => $isExisting,
        'message' => $isExisting ? 'Existing conversation opened' : 'New conversation created'
    ]);

} catch (Exception $e) {
    error_log("Start conversation error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to start conversation: ' . $e->getMessage()]);
}