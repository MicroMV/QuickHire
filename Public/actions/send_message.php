<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MessagingService;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $messagingService = new MessagingService($db->pdo());

    $userId = Auth::userId();
    $conversationId = (int)($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    // Verify user has access to this conversation
    $conversation = $messagingService->getConversation($conversationId);
    if (!$conversation) {
        echo json_encode(['ok' => false, 'error' => 'Conversation not found']);
        exit;
    }

    $userRole = Auth::role();
    $hasAccess = ($userRole === 'EMPLOYER' && $conversation['employer_id'] == $userId) ||
                 ($userRole === 'JOBSEEKER' && $conversation['jobseeker_id'] == $userId);

    if (!$hasAccess) {
        echo json_encode(['ok' => false, 'error' => 'Access denied']);
        exit;
    }

    // Handle file upload
    $fileUrl = null;
    $fileName = null;
    $fileSize = null;
    $messageType = 'text';

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        try {
            $fileData = $messagingService->uploadMessageFile($_FILES['file']);
            $fileUrl = $fileData['url'];
            $fileName = $fileData['name'];
            $fileSize = $fileData['size'];
            $messageType = 'file';
            
            // If no text message, use file name as message
            if (empty($message)) {
                $message = "Sent a file: " . $fileName;
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'File upload failed: ' . $e->getMessage()]);
            exit;
        }
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Log file upload errors for debugging
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File too large (exceeds php.ini limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds form limit)',
            UPLOAD_ERR_PARTIAL => 'File upload was interrupted',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $errorCode = $_FILES['file']['error'];
        $errorMessage = $errorMessages[$errorCode] ?? 'Unknown upload error';
        echo json_encode(['ok' => false, 'error' => $errorMessage]);
        exit;
    }

    // Validate message
    if (empty($message)) {
        echo json_encode(['ok' => false, 'error' => 'Message cannot be empty']);
        exit;
    }

    // Send message
    $messageId = $messagingService->sendMessage(
        $conversationId, 
        $userId, 
        $message, 
        $messageType, 
        $fileUrl, 
        $fileName, 
        $fileSize,
        null // room_code is null for regular messages
    );

    echo json_encode([
        'ok' => true, 
        'message_id' => $messageId,
        'message' => 'Message sent successfully'
    ]);

} catch (Exception $e) {
    error_log("Send message error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to send message: ' . $e->getMessage()]);
}