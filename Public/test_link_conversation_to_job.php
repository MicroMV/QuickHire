<?php
require __DIR__ . '/../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;

Session::start();

if (!Auth::isLoggedIn()) {
    die("Not logged in. Please log in first.");
}

$config = require __DIR__ . '/../Config/config.php';
$db = new Database($config['db']);

// Get the first job post
$jobStmt = $db->pdo()->query("SELECT id, title FROM job_posts WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$job = $jobStmt->fetch();

if (!$job) {
    die("No active job posts found. Please create a job post first.");
}

// Get the conversation
$convStmt = $db->pdo()->prepare("SELECT * FROM conversations WHERE id = 3");
$convStmt->execute();
$conv = $convStmt->fetch();

if (!$conv) {
    die("Conversation ID 3 not found.");
}

echo "<h2>Linking Conversation to Job Post</h2>";
echo "<p>Conversation ID: {$conv['id']}</p>";
echo "<p>Job Post: {$job['title']} (ID: {$job['id']})</p>";

// Update the conversation to link it to the job
$updateStmt = $db->pdo()->prepare("UPDATE conversations SET job_id = ? WHERE id = ?");
$updateStmt->execute([$job['id'], $conv['id']]);

echo "<h3 style='color:green'>✓ Success! Conversation linked to job post.</h3>";
echo "<p>Now refresh your Messages panel as the employer to see the filter.</p>";
