<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Database;

Session::start();
ob_start();

header('Content-Type: application/json');

function json_response(array $payload, int $status = 200): void
{
    if (ob_get_length() !== false) {
        ob_clean();
    }
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

    $signalStmt = $pdo->prepare("
        INSERT INTO webrtc_signals (room_code, sender_id, message_type, payload, created_at)
        VALUES (?, ?, 'chat', ?, NOW())
    ");
    $signalStmt->execute([$room, $uid, json_encode(['message' => $message])]);
    $messageId = (int)$pdo->lastInsertId();

    $userStmt = $pdo->prepare("SELECT first_name, last_name, role FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$uid]);
    $sender = $userStmt->fetch() ?: [];

    $conversationId = null;
    try {
        $conversationStmt = $pdo->prepare("SELECT id FROM conversations WHERE employer_id = ? AND jobseeker_id = ? LIMIT 1");
        $conversationStmt->execute([$empId, $jsId]);
        $conversationId = $conversationStmt->fetchColumn();

        if (!$conversationId) {
            $createConversation = $pdo->prepare("
                INSERT INTO conversations (employer_id, jobseeker_id, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ");
            $createConversation->execute([$empId, $jsId]);
            $conversationId = (int)$pdo->lastInsertId();
        }

        $mirrorStmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, message_type, content, room_code, created_at)
            VALUES (?, ?, 'text', ?, ?, NOW())
        ");
        $mirrorStmt->execute([(int)$conversationId, $uid, $message, $room]);
        $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([(int)$conversationId]);
    } catch (\Throwable $mirrorError) {
        error_log("chat_send mirror warning: " . $mirrorError->getMessage());
    }

    json_response([
        'ok' => true,
        'conversation_id' => $conversationId,
        'after' => $messageId,
        'message' => [
            'id' => $messageId,
            'sender_id' => $uid,
            'message' => $message,
            'first_name' => $sender['first_name'] ?? '',
            'last_name' => $sender['last_name'] ?? '',
            'role' => $sender['role'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (\Throwable $e) {
    error_log("chat_send error: " . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Failed to send message'], 500);
}
