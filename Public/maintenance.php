<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>QuickHire - We'll Be Right Back</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: Inter, system-ui, sans-serif;
      background: #0f172a;
      color: #f1f5f9;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .container {
      text-align: center;
      padding: 40px 24px;
      max-width: 520px;
    }
    .logo {
      margin-bottom: 40px;
    }
    .logo img {
      height: 40px;
      border-radius: 8px;
    }
    .icon {
      font-size: 72px;
      margin-bottom: 24px;
      display: block;
    }
    h1 {
      font-size: 32px;
      font-weight: 900;
      margin-bottom: 14px;
      color: #f8fafc;
    }
    p {
      font-size: 16px;
      color: #94a3b8;
      line-height: 1.7;
      margin-bottom: 12px;
    }
    .divider {
      width: 48px;
      height: 3px;
      background: linear-gradient(90deg, #6366f1, #8b5cf6);
      border-radius: 99px;
      margin: 28px auto;
    }
    .status-box {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 14px;
      padding: 20px 24px;
      margin-top: 32px;
      font-size: 14px;
      color: #64748b;
    }
    .status-dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      background: #f59e0b;
      border-radius: 50%;
      margin-right: 8px;
      animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.3; }
    }
    .retry-btn {
      display: inline-block;
      margin-top: 28px;
      padding: 12px 28px;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      transition: opacity 0.2s;
    }
    .retry-btn:hover { opacity: 0.85; }
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">
      <img src="/QuickHire/Public/images/quickhire-logo.png" alt="QuickHire">
    </div>

    <span class="icon">🔧</span>

    <h1>We'll Be Right Back</h1>

    <div class="divider"></div>

    <p>QuickHire is currently undergoing scheduled maintenance to improve your experience.</p>
    <p>We apologize for the inconvenience and appreciate your patience.</p>

    <div class="status-box">
      <span class="status-dot"></span>
      Our team is working on it and we expect to be back shortly.
    </div>

    <a href="/QuickHire/Public/index.php" class="retry-btn">Try Again</a>
  </div>
</body>
</html>
