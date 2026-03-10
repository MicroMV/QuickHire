<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

$userId = Auth::userId();
$role = Auth::role();

try {
  if ($role === 'EMPLOYER') {
    // Check for active calls as employer
    $stmt = $pdo->prepare("
      SELECT room_code FROM calls
      WHERE employer_user_id = ? AND status IN ('RINGING','IN_CALL')
      ORDER BY id DESC LIMIT 1
    ");
  } else {
    // Check for active calls as jobseeker
    $stmt = $pdo->prepare("
      SELECT room_code FROM calls
      WHERE jobseeker_user_id = ? AND status IN ('RINGING','IN_CALL')
      ORDER BY id DESC LIMIT 1
    ");
  }
  
  $stmt->execute([$userId]);
  $call = $stmt->fetch();
  
  if ($call) {
    echo json_encode(['ok' => true, 'room' => $call['room_code']]);
  } else {
    echo json_encode(['ok' => false, 'room' => null]);
  }

} catch (Exception $e) {
  error_log("Check active call error: " . $e->getMessage());
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}