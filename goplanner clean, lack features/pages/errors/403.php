<?php
// pages/errors/403.php
$bp = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/goplanner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Access Denied</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px}
    .error-code{font-size:8rem;font-weight:800;letter-spacing:-4px;background:linear-gradient(135deg,#ef4444,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;margin-bottom:8px}
    h1{font-size:1.5rem;font-weight:700;margin-bottom:8px}
    p{color:#64748b;font-size:0.92rem;margin-bottom:32px;max-width:400px}
    a{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:#3b82f6;color:white;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.88rem;transition:all 0.2s}
    a:hover{background:#2563eb;transform:translateY(-1px)}
    </style>
</head>
<body>
    <div>
        <div class="error-code">403</div>
        <h1>Access Denied</h1>
        <p>You don't have permission to view this page. If you believe this is a mistake, contact your administrator.</p>
        <a href="<?php echo $bp; ?>/pages/login.php">Back to Login</a>
    </div>
</body>
</html>