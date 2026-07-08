<?php
// pages/login.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    $r = $_SESSION['role'] ?? 'student';
    $map = [
        'super_admin' => '/pages/dashboard/superadmin.php',
        'admin'       => '/pages/dashboard/admin.php',
        'instructor'  => '/pages/dashboard/instructor.php',
        'student'     => '/pages/dashboard/student.php',
    ];
    header('Location: ' . APP_BASE_PATH . ($map[$r] ?? $map['student']));
    exit;
}

$basePath = APP_BASE_PATH;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — DEBESMSCAT GoPlanner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="theme-color" content="#0f172a">
    <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}

    body {
        font-family:'Inter',sans-serif;
        background:#0f172a;
        color:#f1f5f9;
        min-height:100vh;
    }

    .auth-container {
        display:flex;
        width:100%;
        min-height:100vh;
    }

    /* ========== LEFT PANEL ========== */
    .auth-brand {
        width:45%;
        min-height:100vh;
        background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#0f172a 100%);
        display:flex;
        align-items:center;
        justify-content:center;
        padding:48px;
        position:relative;
        overflow:hidden;
    }

    .auth-brand::before {
        content:'';
        position:absolute;
        top:-50%;left:-50%;
        width:200%;height:200%;
        background:
            radial-gradient(circle at 30% 50%,rgba(59,130,246,0.08) 0%,transparent 50%),
            radial-gradient(circle at 70% 80%,rgba(16,185,129,0.06) 0%,transparent 40%);
        pointer-events:none;
    }

    .brand-content {
        position:relative;
        z-index:1;
        text-align:center;
        max-width:380px;
        animation:fadeIn 0.8s ease;
    }

    @keyframes fadeIn {
        from{opacity:0;transform:translateY(10px)}
        to{opacity:1;transform:translateY(0)}
    }

    .logo-icon {
        width:72px;height:72px;
        border-radius:18px;
        background:linear-gradient(135deg,#3b82f6,#10b981);
        display:inline-flex;
        align-items:center;
        justify-content:center;
        font-size:1.5rem;
        font-weight:800;
        color:white;
        letter-spacing:-1px;
        box-shadow:0 8px 32px rgba(59,130,246,0.3);
        margin-bottom:24px;
        overflow:hidden;
    }

    .logo-icon img {
        width:100%;height:100%;
        border-radius:18px;
        object-fit:contain;
    }

    .brand-title {
        font-size:2.2rem;
        font-weight:800;
        color:#f1f5f9;
        letter-spacing:-1px;
        margin-bottom:4px;
    }

    .brand-subtitle {
        font-size:0.92rem;
        font-weight:600;
        color:#3b82f6;
        letter-spacing:3px;
        text-transform:uppercase;
        margin-bottom:12px;
    }

    .brand-tagline {
        font-size:0.88rem;
        color:#94a3b8;
        line-height:1.6;
        margin-bottom:32px;
    }

    .brand-colleges {
        display:flex;
        justify-content:center;
        gap:8px;
        margin-bottom:40px;
    }

    .college-dot {
        width:36px;height:36px;
        border-radius:10px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        font-size:0.65rem;
        font-weight:700;
        color:white;
        transition:transform 0.2s ease,box-shadow 0.2s ease;
        cursor:default;
        overflow:hidden;
    }

    .college-dot:hover {
        transform:translateY(-3px);
        box-shadow:0 6px 20px rgba(0,0,0,0.3);
    }

    .college-dot img {
        width:100%;height:100%;
        border-radius:10px;
        object-fit:contain;
    }

    .brand-vision {
        border-top:1px solid rgba(148,163,184,0.15);
        padding-top:24px;
    }

    .vision-label {
        font-size:0.68rem;
        font-weight:700;
        color:#64748b;
        letter-spacing:2px;
        text-transform:uppercase;
        margin-bottom:8px;
    }

    .vision-text {
        font-size:0.82rem;
        color:#94a3b8;
        line-height:1.7;
        font-style:italic;
    }

    /* ========== RIGHT PANEL ========== */
    .auth-form-panel {
        width:55%;
        min-height:100vh;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:48px;
        background:#0f172a;
    }

    .auth-form-wrapper {
        width:100%;
        max-width:420px;
        animation:slideUp 0.5s ease;
    }

    @keyframes slideUp {
        from{opacity:0;transform:translateY(20px)}
        to{opacity:1;transform:translateY(0)}
    }

    .form-title {
        font-size:1.75rem;
        font-weight:800;
        color:#f1f5f9;
        letter-spacing:-0.5px;
        margin-bottom:6px;
    }

    .form-subtitle {
        font-size:0.88rem;
        color:#64748b;
        margin-bottom:32px;
    }

    /* ========== FORM ========== */
    .auth-form {
        display:flex;
        flex-direction:column;
        gap:20px;
    }

    .form-group {
        display:flex;
        flex-direction:column;
        gap:6px;
    }

    .form-label {
        font-size:0.82rem;
        font-weight:600;
        color:#cbd5e1;
    }

    .input-wrapper {
        position:relative;
        display:flex;
        align-items:center;
    }

    .input-icon {
        position:absolute;
        left:14px;
        color:#475569;
        pointer-events:none;
        z-index:1;
        transition:color 0.2s ease;
    }

    .form-input {
        width:100%;
        padding:12px 44px 12px 44px;
        background:#1e293b;
        border:1.5px solid #334155;
        border-radius:10px;
        color:#f1f5f9;
        font-size:0.92rem;
        font-family:'Inter',sans-serif;
        outline:none;
        transition:all 0.2s ease;
    }

    .form-input::placeholder {
        color:#475569;
    }

    .form-input:focus {
        border-color:#3b82f6;
        box-shadow:0 0 0 3px rgba(59,130,246,0.15);
    }

    .form-input:focus ~ .input-icon {
        color:#3b82f6;
    }

    .toggle-password {
        position:absolute;
        right:12px;
        background:none;
        border:none;
        color:#475569;
        cursor:pointer;
        padding:4px;
        display:flex;
        align-items:center;
        z-index:1;
        transition:color 0.2s ease;
    }

    .toggle-password:hover {
        color:#94a3b8;
    }

    /* ========== BUTTONS ========== */
    .btn-primary {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        width:100%;
        padding:14px 24px;
        background:#3b82f6;
        color:white;
        border:none;
        border-radius:10px;
        font-size:0.92rem;
        font-weight:600;
        font-family:'Inter',sans-serif;
        cursor:pointer;
        transition:all 0.2s ease;
    }

    .btn-primary:hover:not(:disabled) {
        background:#2563eb;
        transform:translateY(-1px);
        box-shadow:0 4px 16px rgba(59,130,246,0.3);
    }

    .btn-primary:active:not(:disabled) {
        transform:translateY(0);
    }

    .btn-primary:disabled {
        opacity:0.7;
        cursor:not-allowed;
    }

    .auth-divider {
        display:flex;
        align-items:center;
        gap:16px;
        margin:8px 0;
    }

    .auth-divider::before,
    .auth-divider::after {
        content:'';
        flex:1;
        height:1px;
        background:#334155;
    }

    .auth-divider span {
        font-size:0.78rem;
        color:#64748b;
        white-space:nowrap;
    }

    .btn-secondary {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:100%;
        padding:12px 24px;
        background:transparent;
        border:1.5px solid #334155;
        border-radius:10px;
        color:#cbd5e1;
        font-size:0.88rem;
        font-weight:600;
        font-family:'Inter',sans-serif;
        text-decoration:none;
        cursor:pointer;
        transition:all 0.2s ease;
    }

    .btn-secondary:hover {
        border-color:#3b82f6;
        color:#3b82f6;
        background:rgba(59,130,246,0.05);
    }

    .auth-footer {
        text-align:center;
        font-size:0.7rem;
        color:#475569;
        margin-top:32px;
        line-height:1.5;
    }

    /* ========== FLASH ========== */
    .flash-message {
        padding:12px 16px;
        border-radius:8px;
        margin-bottom:16px;
        font-size:0.85rem;
        font-weight:500;
        animation:flashIn 0.3s ease;
    }

    @keyframes flashIn {
        from{opacity:0;transform:translateY(-8px)}
        to{opacity:1;transform:translateY(0)}
    }

    .flash-error {
        background:rgba(239,68,68,0.1);
        color:#fca5a5;
        border-left:4px solid #ef4444;
    }

    .flash-success {
        background:rgba(16,185,129,0.1);
        color:#6ee7b7;
        border-left:4px solid #10b981;
    }

    /* ========== SPINNER ========== */
    .spinner {
        display:inline-block;
        width:16px;height:16px;
        border:2px solid rgba(255,255,255,0.3);
        border-top-color:white;
        border-radius:50%;
        animation:spin 0.6s linear infinite;
    }

    @keyframes spin { to{transform:rotate(360deg)} }

    .btn-loading {
        display:inline-flex;
        align-items:center;
        gap:8px;
    }

    /* ========== RESPONSIVE ========== */
    @media(max-width:768px) {
        .auth-container { flex-direction:column; }
        .auth-brand { width:100%;min-height:auto;padding:32px 24px; }
        .brand-vision { display:none; }
        .brand-title { font-size:1.6rem; }
        .auth-form-panel { width:100%;padding:32px 24px;min-height:auto; }
        .form-title { font-size:1.4rem; }
    }

    @media(max-width:480px) {
        .auth-brand { padding:24px 16px; }
        .logo-icon { width:56px;height:56px;font-size:1.2rem; }
        .brand-title { font-size:1.3rem; }
        .college-dot { width:30px;height:30px;font-size:0.55rem; }
        .auth-form-panel { padding:24px 16px; }
        .form-input { font-size:16px; }
    }
    </style>
</head>
<body>
    <div class="auth-container">

        <!-- LEFT: Branding -->
        <div class="auth-brand">
            <div class="brand-content">
                <div class="logo-icon">
                    <?php
                    $logoFile = __DIR__ . '/../assets/images/logo/goplanner-logo.png';
                    if (file_exists($logoFile)):
                    ?>
                        <img src="<?php echo $basePath; ?>/assets/images/logo/goplanner-logo.png" alt="GoPlanner">
                    <?php else: ?>
                        GP
                    <?php endif; ?>
                </div>
                <h1 class="brand-title">GoPlanner</h1>
                <p class="brand-subtitle">DEBESMSCAT</p>
                <p class="brand-tagline">Your centralized academic announcement and planning system</p>
                <div class="brand-colleges">
                    <?php
                    $collegeList = [
                        ['abbr' => 'CA', 'name' => 'College of Agriculture', 'color' => '#166534'],
                        ['abbr' => 'CCIT', 'name' => 'College of Computing & IT', 'color' => '#0D9488'],
                        ['abbr' => 'CBTE', 'name' => 'College of Business Teacher Education', 'color' => '#DC2626'],
                        ['abbr' => 'CEng', 'name' => 'College of Engineering', 'color' => '#800020'],
                        ['abbr' => 'CE',   'name' => 'College of Education', 'color' => '#1E3A8A'],
                        ['abbr' => 'CIT',  'name' => 'College of Industrial Technology', 'color' => '#4B5563'],
                    ];
                    foreach ($collegeList as $col):
                        $colLogoFile = __DIR__ . '/../assets/images/colleges/' . $col['abbr'] . '.png';
                        $colLogoSrc  = $basePath . '/assets/images/colleges/' . $col['abbr'] . '.png';
                        $hasLogo = file_exists($colLogoFile);
                    ?>
                        <span class="college-dot"
                              style="background:<?php echo $hasLogo ? 'rgba(255,255,255,0.1)' : $col['color']; ?>;"
                              title="<?php echo htmlspecialchars($col['name']); ?>">
                            <?php if ($hasLogo): ?>
                                <img src="<?php echo $colLogoSrc; ?>" alt="<?php echo htmlspecialchars($col['abbr']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($col['abbr'], 0, 2)); ?>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <div class="brand-vision">
                    <p class="vision-label">VISION</p>
                    <p class="vision-text">A premier state college recognized for excellence in agriculture, fisheries, and related fields.</p>
                </div>
            </div>
        </div>

        <!-- RIGHT: Login Form -->
        <div class="auth-form-panel">
            <div class="auth-form-wrapper">
                <h2 class="form-title">Welcome Back</h2>
                <p class="form-subtitle">Sign in to your GoPlanner account</p>

                <?php
                if (isset($_GET['error'])) {
                    $errors = [
                        'empty'    => 'Please fill in all fields.',
                        'invalid'  => 'Invalid email or password.',
                        'inactive' => 'Your account is not yet approved.',
                        'archived' => 'Your account has been archived.',
                        'session'  => 'Session expired. Please login again.',
                    ];
                    $msg = $errors[$_GET['error']] ?? 'An error occurred.';
                    echo '<div class="flash-message flash-error">' . htmlspecialchars($msg) . '</div>';
                }
                if (isset($_GET['registered'])) {
                    echo '<div class="flash-message flash-success">Registration successful! Please wait for admin confirmation.</div>';
                }
                if (isset($_GET['logout'])) {
                    echo '<div class="flash-message flash-success">You have been logged out.</div>';
                }
                ?>

                <form action="<?php echo $basePath; ?>/api/auth/login.php" method="POST" class="auth-form" id="loginForm">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            <input type="email" id="email" name="email" class="form-input"
                                   placeholder="your.name@debesmscat.edu.ph"
                                   required autocomplete="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="password" name="password" class="form-input"
                                   placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" id="loginBtn">
                        <span class="btn-text">Sign In</span>
                        <span class="btn-loading" style="display:none;">
                            <span class="spinner"></span> Signing in...
                        </span>
                    </button>
                </form>

                <div class="auth-divider">
                    <span>New to GoPlanner?</span>
                </div>

                <a href="register.php" class="btn-secondary">Create an Account</a>

                <p class="auth-footer">
                    Developed by <strong>Relica Malinao</strong> & <strong>Maria Bea Belasa</strong><br>
                    <span style="opacity:0.6">&copy; 2026 Dr. Emilio B. Espinosa Sr. Memorial State College of Agriculture and Technology</span>
                </p>
            </div>
        </div>

    </div>

    <script>
    function togglePassword() {
        var input = document.getElementById('password');
        var icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        } else {
            input.type = 'password';
            icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
    }

    document.getElementById('loginForm').addEventListener('submit', function() {
        var btn = document.getElementById('loginBtn');
        btn.querySelector('.btn-text').style.display = 'none';
        btn.querySelector('.btn-loading').style.display = 'inline-flex';
        btn.disabled = true;
    });
    </script>
</body>
</html>