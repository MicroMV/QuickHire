<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();

if (!Auth::isLoggedIn()) {
    die("Not logged in");
}

$config = require __DIR__ . '/../Config/config.php';
$db = new Database($config['db']);

// Get all users with their last_active status
$stmt = $db->pdo()->query("
    SELECT id, first_name, last_name, role, last_active,
           TIMESTAMPDIFF(SECOND, last_active, NOW()) as seconds_ago
    FROM users 
    WHERE last_active IS NOT NULL
    ORDER BY last_active DESC
");

$users = $stmt->fetchAll();

echo "<h2>Active Status Test</h2>";
echo "<p>Users are considered 'active' if last_active is within 5 minutes (300 seconds)</p>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Last Active</th><th>Seconds Ago</th><th>Status</th></tr>";

foreach ($users as $user) {
    $isActive = $user['seconds_ago'] < 300;
    $status = $isActive ? '<span style="color:green">● ACTIVE</span>' : '<span style="color:gray">○ Offline</span>';
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['first_name']} {$user['last_name']}</td>";
    echo "<td>{$user['role']}</td>";
    echo "<td>{$user['last_active']}</td>";
    echo "<td>{$user['seconds_ago']}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<br><p><a href='/QuickHire/Public/actions/update_activity.php'>Update my activity now</a></p>";
