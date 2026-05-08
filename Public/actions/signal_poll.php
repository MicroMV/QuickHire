<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

// Release session lock — this endpoint only reads data
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$config = require __DIR__ . '/../../Config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$room = trim($_GET['room'] ?? '');
$after = (int)($_GET['after'] ?? 0);

if ($room === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Missing room']);
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
  SELECT s.id, s.sender_id, s.message_type, s.payload,
         u.first_name, u.last_name, u.role
  FROM webrtc_signals s
  JOIN users u ON u.id = s.sender_id
  WHERE s.room_code = ? AND s.id > ? AND s.sender_id <> ?
  ORDER BY s.id ASC
  LIMIT 50
");
$stmt->execute([$room, $after, $userId]);
$rows = $stmt->fetchAll();

$lastId = $after;
foreach ($rows as $r) $lastId = max($lastId, (int)$r['id']);

echo json_encode([
  'ok' => true,
  'after' => $lastId,
  'messages' => array_map(fn($r) => [
    'id' => (int)$r['id'],
    'sender_id' => (int)$r['sender_id'],
    'type' => $r['message_type'],
    'payload' => json_decode($r['payload'], true),
    'first_name' => $r['first_name'],
    'last_name' => $r['last_name'],
    'role' => $r['role'],
  ], $rows)
]);
