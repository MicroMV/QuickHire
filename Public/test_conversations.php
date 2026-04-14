<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\MessagingService;

Session::start();

if (!Auth::isLoggedIn()) {
    die("Not logged in. Please log in first.");
}

$config = require __DIR__ . '/../Config/config.php';
$db = new Database($config['db']);
$messagingService = new MessagingService($db->pdo());

$userId = Auth::userId();
$userRole = Auth::role();

echo "<h2>Testing Conversations for User ID: $userId, Role: $userRole</h2>";

try {
    $conversations = $messagingService->getUserConversations($userId, $userRole);
    
    echo "<h3>Success! Found " . count($conversations) . " conversations</h3>";
    echo "<pre>";
    print_r($conversations);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h3>Error:</h3>";
    echo "<p style='color:red'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
