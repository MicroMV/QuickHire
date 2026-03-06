<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$room = trim($_GET['room'] ?? '');
$after = (int)($_GET['after'] ?? 0);

if ($room === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Missing room']);
  exit;
}

$stmt = $pdo->prepare("
  SELECT id, sender_id, message_type, payload
  FROM webrtc_signals
  WHERE room_code = ? AND id > ? AND sender_id <> ?
  ORDER BY id ASC
  LIMIT 50
");
$stmt->execute([$room, $after, Auth::userId()]);
$rows = $stmt->fetchAll();

$lastId = $after;
foreach ($rows as $r) $lastId = max($lastId, (int)$r['id']);

echo json_encode([
  'ok' => true,
  'after' => $lastId,
  'messages' => array_map(fn($r) => [
    'id' => (int)$r['id'],
    'type' => $r['message_type'],
    'payload' => json_decode($r['payload'], true),
  ], $rows)
]);