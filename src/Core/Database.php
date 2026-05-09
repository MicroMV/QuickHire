<?php
namespace Rongie\QuickHire\Core;

use PDO;
use PDOException;

class Database
{
    private PDO $pdo; 

    public function __construct(array $dbConfig)
    {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
        try {
            $this->pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            // Show a plain error without exposing technical details
            if (!headers_sent()) {
                http_response_code(503);
                echo <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Service Unavailable - QuickHire</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
      font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: #e2e8f0;
      background:
        radial-gradient(circle at 18% 20%, rgba(16, 185, 129, 0.18), transparent 30%),
        radial-gradient(circle at 82% 18%, rgba(99, 102, 241, 0.24), transparent 32%),
        linear-gradient(135deg, #020617 0%, #0f172a 52%, #111827 100%);
    }
    .qh-error {
      width: min(560px, 100%);
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 24px;
      padding: 38px;
      text-align: center;
      background: rgba(15, 23, 42, 0.86);
      box-shadow: 0 30px 90px rgba(0,0,0,0.42);
    }
    .qh-error::before {
      content: "";
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
      background-size: 34px 34px;
      mask-image: linear-gradient(to bottom, rgba(0,0,0,0.7), transparent);
      pointer-events: none;
    }
    .qh-error > * { position: relative; z-index: 1; }
    .qh-logo {
      width: 180px;
      height: 58px;
      margin: 0 auto 18px;
      display: block;
      border-radius: 12px;
      object-fit: contain;
      object-position: center;
      background: rgba(255,255,255,0.04);
      padding: 8px 12px;
      box-shadow: 0 16px 44px rgba(99,102,241,0.18);
    }
    .qh-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 7px 13px;
      margin-bottom: 18px;
      border-radius: 999px;
      border: 1px solid rgba(251,191,36,0.25);
      background: rgba(251,191,36,0.1);
      color: #fde68a;
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    h1 {
      margin: 0 0 12px;
      color: #f8fafc;
      font-size: clamp(28px, 5vw, 42px);
      line-height: 1.08;
      font-weight: 900;
      letter-spacing: -0.03em;
    }
    p {
      max-width: 430px;
      margin: 0 auto 26px;
      color: #94a3b8;
      font-size: 15px;
      line-height: 1.7;
    }
    .qh-actions {
      display: flex;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .qh-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 44px;
      padding: 0 18px;
      border-radius: 12px;
      color: #fff;
      font-size: 14px;
      font-weight: 800;
      text-decoration: none;
      border: 1px solid transparent;
      background: linear-gradient(135deg, #10b981, #059669);
      box-shadow: 0 14px 34px rgba(16,185,129,0.22);
    }
    .qh-button.secondary {
      color: #e2e8f0;
      background: rgba(255,255,255,0.07);
      border-color: rgba(255,255,255,0.14);
      box-shadow: none;
    }
    .qh-footnote {
      margin-top: 22px;
      color: #64748b;
      font-size: 12px;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <main class="qh-error" role="main">
    <img class="qh-logo" src="/QuickHire/Public/images/quickhire-logo.png" alt="QuickHire">
    <div class="qh-pill">Service temporarily unavailable</div>
    <h1>QuickHire is taking a short pause</h1>
    <p>We could not connect to the service needed to load this page. Please refresh in a moment or return to the landing page while we recover.</p>
    <div class="qh-actions">
      <a class="qh-button" href="">Try Again</a>
      <a class="qh-button secondary" href="/QuickHire/Public/index.php">Go Home</a>
    </div>
    <div class="qh-footnote">No account data is affected. This is usually temporary.</div>
  </main>
</body>
</html>
HTML;
                exit;
            }
            // If headers already sent (e.g. AJAX), return JSON error
            echo json_encode(['ok' => false, 'error' => 'Service temporarily unavailable']);
            exit;
        }
    }

    public function pdo(): PDO 
    {
        return $this->pdo;
    }
}
