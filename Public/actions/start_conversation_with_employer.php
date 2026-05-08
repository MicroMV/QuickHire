<?php
header('Content-Type: application/json');

require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MessagingService;

Session::start();

try {
    // Check authentication
    if (!Auth::isLoggedIn() || Auth::role() !== 'JOBSEEKER') {
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

    // Get form data
    $employerId  = (int)($_POST['employer_id'] ?? 0);
    $jobPostId   = !empty($_POST['job_post_id']) ? (int)$_POST['job_post_id'] : null;

    // Validate employer ID
    if ($employerId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid employer ID']);
        exit;
    }

    // Initialize database and service
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $messagingService = new MessagingService($db->pdo());

    $jobseekerId = Auth::userId();

    // Verify employer exists and is actually an employer
    $stmt = $db->pdo()->prepare("SELECT first_name, last_name FROM users WHERE id = ? AND role = 'EMPLOYER'");
    $stmt->execute([$employerId]);
    $employer = $stmt->fetch();

    if (!$employer) {
        echo json_encode(['ok' => false, 'error' => 'Employer not found']);
        exit;
    }

    if ($jobPostId !== null) {
        $stmt = $db->pdo()->prepare("
            SELECT id
            FROM job_posts
            WHERE id = ? AND employer_id = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$jobPostId, $employerId]);

        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Job post not found']);
            exit;
        }
    }

    // Create or get existing conversation
    $conversationId = $messagingService->getOrCreateConversation($employerId, $jobseekerId, $jobPostId ?? null);

    // Check if this was an existing conversation by looking for messages
    $stmt = $db->pdo()->prepare("
        SELECT COUNT(*) as message_count
        FROM messages
        WHERE conversation_id = ?
          AND message_type <> 'job_application'
    ");
    $stmt->execute([$conversationId]);
    $messageCount = $stmt->fetch()['message_count'];

    if ($jobPostId !== null) {
        $messagingService->recordJobApplication($conversationId, $jobPostId);
    }
    
    $isExisting = $messageCount > 0;

    echo json_encode([
        'ok' => true,
        'conversation_id' => $conversationId,
        'is_existing' => $isExisting,
        'employer_name' => $employer['first_name'] . ' ' . $employer['last_name']
    ]);

} catch (Throwable $e) {
    error_log('Start conversation with employer error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}
