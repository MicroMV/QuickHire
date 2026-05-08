<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Core\Auth;
use Rongie\QuickHire\Core\Database;
use Rongie\QuickHire\Services\SearchService;

Session::start();
Auth::requireLogin();

header('Content-Type: application/json');

if (Auth::role() !== 'EMPLOYER') {
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method']);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (empty($query)) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

// Release session lock — this endpoint only reads data
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (empty($query)) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

try {
    $config = require __DIR__ . '/../../Config/config.php';
    $db = new Database($config['db']);
    $searchService = new SearchService($db->pdo());

    $results = $searchService->searchJobSeekers($query);

    echo json_encode([
        'ok' => true,
        'results' => $results,
        'count' => count($results)
    ]);

} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['ok' => false, 'error' => 'Search failed: ' . $e->getMessage()]);
}