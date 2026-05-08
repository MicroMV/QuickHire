<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Database;

Session::start();
ob_start();

header('Content-Type: application/json');

// Release session lock — this endpoint only reads data
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

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

    $room  = trim($_GET['room']  ?? '');
    $after = max(0, (int)($_GET['after'] ?? 0));

    if ($room === '') {
        json_response(['ok' => false, 'error' => 'Missing room'], 400);
    }

    $uid = (int)Session::get('user_id', 0);
    if ($uid <= 0) {
        json_response(['ok' => false, 'error' => 'Not logged in'], 401);
    }

    $callStmt = $pdo->prepare("
        SELECT employer_user_id, jobseeker_user_id
        FROM calls
        WHERE room_code = ?
        LIMIT 1
    ");
    $callStmt->execute([$room]);
    $call = $callStmt->fetch();

    if (!$call) {
        json_response(['ok' => false, 'error' => 'Room not found'], 404);
    }

    $empId = (int)$call['employer_user_id'];
    $jsId  = (int)($call['jobseeker_user_id'] ?? 0);

    if ($uid !== $empId && $uid !== $jsId) {
        json_response(['ok' => false, 'error' => 'Not authorized'], 403);
    }

    $stmt = $pdo->prepare("
        SELECT s.id,
               s.sender_id,
               s.payload,
               s.created_at,
               u.first_name,
               u.last_name,
               u.role
        FROM webrtc_signals s
        JOIN users u ON u.id = s.sender_id
        WHERE s.room_code = ?
          AND s.message_type = 'chat'
          AND s.id > ?
        ORDER BY s.id ASC
        LIMIT 100
    ");
    $stmt->execute([$room, $after]);
    $rows = $stmt->fetchAll();

    $lastId = $after;
    $messages = [];

    foreach ($rows as $row) {
        $payload = json_decode((string)$row['payload'], true);
        $messages[] = [
            'id'         => (int)$row['id'],
            'sender_id'  => (int)$row['sender_id'],
            'message'    => is_array($payload) ? (string)($payload['message'] ?? '') : '',
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
            'role'       => $row['role'],
            'created_at' => $row['created_at'],
        ];
        $lastId = max($lastId, (int)$row['id']);
    }

    json_response(['ok' => true, 'messages' => $messages, 'after' => $lastId]);
} catch (\Throwable $e) {
    error_log("chat_poll error: " . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Failed to load chat messages'], 500);
}
