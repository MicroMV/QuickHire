<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MessagingService;

Session::start();

header('Content-Type: application/json');

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db  = new Database($config['db']);
    $pdo = $db->pdo();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'Invalid request body'], 400);
    }

    $room    = trim((string)($input['room'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));

    if ($room === '' || $message === '') {
        json_response(['ok' => false, 'error' => 'Missing room or message'], 400);
    }

    $uid = (int)Session::get('user_id', 0);
    if ($uid <= 0) {
        json_response(['ok' => false, 'error' => 'Not logged in'], 401);
    }

    $stmt = $pdo->prepare("
        SELECT employer_user_id, jobseeker_user_id
        FROM calls
        WHERE room_code = ?
        LIMIT 1
    ");
    $stmt->execute([$room]);
    $call = $stmt->fetch();

    if (!$call) {
        json_response(['ok' => false, 'error' => 'Room not found'], 404);
    }

    $empId = (int)$call['employer_user_id'];
    $jsId  = (int)($call['jobseeker_user_id'] ?? 0);

    if ($uid !== $empId && $uid !== $jsId) {
        json_response(['ok' => false, 'error' => 'Not authorized'], 403);
    }

    if ($jsId <= 0) {
        json_response(['ok' => false, 'error' => 'No call participant yet'], 409);
    }

    $messagingService = new MessagingService($pdo);
    $conversationId = $messagingService->getOrCreateConversation($empId, $jsId);
    $messageId = $messagingService->sendMessage(
        $conversationId,
        $uid,
        $message,
        'text',
        null,
        null,
        null,
        $room
    );

    $messageStmt = $pdo->prepare("
        SELECT m.id,
               m.sender_id,
               m.content,
               m.created_at,
               u.first_name,
               u.last_name,
               u.role
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.id = ?
        LIMIT 1
    ");
    $messageStmt->execute([$messageId]);
    $savedMessage = $messageStmt->fetch();

    json_response([
        'ok' => true,
        'conversation_id' => $conversationId,
        'after' => $messageId,
        'message' => [
            'id' => $messageId,
            'sender_id' => $uid,
            'message' => $savedMessage['content'] ?? $message,
            'first_name' => $savedMessage['first_name'] ?? '',
            'last_name' => $savedMessage['last_name'] ?? '',
            'role' => $savedMessage['role'] ?? '',
            'created_at' => $savedMessage['created_at'] ?? date('Y-m-d H:i:s'),
        ],
    ]);
} catch (\Throwable $e) {
    error_log("chat_send error: " . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Failed to send message'], 500);
}
