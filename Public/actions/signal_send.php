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

$input = json_decode(file_get_contents('php://input'), true);

$room = trim($input['room'] ?? '');
$type = $input['type'] ?? '';
$payload = $input['payload'] ?? null;

if ($room === '' || !in_array($type, ['join','offer','answer','candidate','leave'], true) || $payload === null) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Bad request']);
  exit;
}

$stmt = $pdo->prepare("
  INSERT INTO webrtc_signals (room_code, sender_id, message_type, payload)
  VALUES (?, ?, ?, ?)
");
$stmt->execute([$room, Auth::userId(), $type, json_encode($payload)]);

echo json_encode(['ok' => true]);
