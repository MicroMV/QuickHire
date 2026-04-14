<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;

Session::start();

if (!Auth::isLoggedIn()) {
    die("Not logged in");
}

// Call the actual endpoint
$url = 'http://localhost/QuickHire/Public/actions/get_conversations.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
curl_close($ch);

echo "<h2>Raw Response from get_conversations.php</h2>";
echo "<pre>";
echo htmlspecialchars($response);
echo "</pre>";

echo "<h2>Parsed JSON</h2>";
$data = json_decode($response, true);
echo "<pre>";
print_r($data);
echo "</pre>";

if (isset($data['conversations'][0])) {
    echo "<h2>First Conversation Fields</h2>";
    echo "<pre>";
    print_r(array_keys($data['conversations'][0]));
    echo "</pre>";
    
    echo "<h2>other_last_active value</h2>";
    echo "<pre>";
    var_dump($data['conversations'][0]['other_last_active'] ?? 'KEY NOT FOUND');
    echo "</pre>";
}
