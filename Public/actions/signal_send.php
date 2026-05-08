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

if ($room === '' || !in_array($type, ['join','offer','answer','candidate','leave'], true) || $payload === null) {
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

echo json_encode(['ok' => true]);
