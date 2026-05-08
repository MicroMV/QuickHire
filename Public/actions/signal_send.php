<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$input = json_decode(file_get_contents('php://input'), true);

$room = trim($input['room'] ?? '');
$type = $input['type'] ?? '';
$payload = $input['payload'] ?? null;

function table_has_column(PDO $pdo, string $table, string $column): bool
{
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_NAME = ?
  ");
  $stmt->execute([$table, $column]);
  return (int)$stmt->fetchColumn() > 0;
}

function mirror_call_chat_to_messages(PDO $pdo, array $call, int $senderId, string $room, mixed $payload): void
{
  $message = is_array($payload) ? trim((string)($payload['message'] ?? '')) : '';
  if ($message === '') {
    return;
  }

  $employerId = (int)$call['employer_user_id'];
  $jobseekerId = (int)($call['jobseeker_user_id'] ?? 0);
  if ($employerId <= 0 || $jobseekerId <= 0) {
    return;
  }

  try {
    $conversationStmt = $pdo->prepare("
      SELECT id
      FROM conversations
      WHERE employer_id = ? AND jobseeker_id = ?
      LIMIT 1
    ");
    $conversationStmt->execute([$employerId, $jobseekerId]);
    $conversationId = (int)$conversationStmt->fetchColumn();

    if ($conversationId <= 0) {
      $createStmt = $pdo->prepare("
        INSERT INTO conversations (employer_id, jobseeker_id, created_at, updated_at)
        VALUES (?, ?, NOW(), NOW())
      ");
      $createStmt->execute([$employerId, $jobseekerId]);
      $conversationId = (int)$pdo->lastInsertId();
    }

    if (table_has_column($pdo, 'messages', 'room_code')) {
      $messageStmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, message_type, content, room_code, created_at)
        VALUES (?, ?, 'text', ?, ?, NOW())
      ");
      $messageStmt->execute([$conversationId, $senderId, $message, $room]);
    } else {
      $messageStmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, message_type, content, created_at)
        VALUES (?, ?, 'text', ?, NOW())
      ");
      $messageStmt->execute([$conversationId, $senderId, $message]);
    }

    $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]);
  } catch (Throwable $e) {
    error_log('signal_send chat mirror warning: ' . $e->getMessage());
  }
}

if ($room === '' || !in_array($type, ['join','offer','answer','candidate','leave','chat'], true) || $payload === null) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Bad request']);
  exit;
}

$callStmt = $pdo->prepare("
  SELECT employer_user_id, jobseeker_user_id
  FROM calls
  WHERE room_code = ?
  LIMIT 1
");
$callStmt->execute([$room]);
$call = $callStmt->fetch();
$userId = Auth::userId();

if (!$call || ($userId !== (int)$call['employer_user_id'] && $userId !== (int)($call['jobseeker_user_id'] ?? 0))) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Not authorized for this room']);
  exit;
}

$stmt = $pdo->prepare("
  INSERT INTO webrtc_signals (room_code, sender_id, message_type, payload)
  VALUES (?, ?, ?, ?)
");
$stmt->execute([$room, $userId, $type, json_encode($payload)]);

if ($type === 'chat') {
  mirror_call_chat_to_messages($pdo, $call, $userId, $room, $payload);
}

echo json_encode(['ok' => true]);
