<?php
// pages/register.php
session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$basePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/goplanner';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $basePath . '/pages/dashboard/student.php');
    exit;
}

$pdo = db();

// Load colleges
$colleges = $pdo->query("
    SELECT id, code, abbreviation, name, logo, color, description
    FROM colleges
    WHERE is_active = 1
    ORDER BY abbreviation
")->fetchAll(PDO::FETCH_ASSOC);

// Load programs grouped by college
$programsByCollege = [];
$progs = $pdo->query("
    SELECT id, college_id, code, name, major
    FROM programs
    WHERE is_active = 1
    ORDER BY code
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($progs as $p) {
    $programsByCollege[$p['college_id']][] = $p;
}

// Flash messages
$error = $_SESSION['register_error'] ?? '';
$old = $_SESSION['register_old'] ?? [];
unset($_SESSION['register_error'], $_SESSION['register_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Register — GoPlanner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        width:40%;
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
        max-width:340px;
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
        box-shadow:0 8px 32px rgba(59,130,246,0.3);
        margin-bottom:24px;
        overflow:hidden;
    }

    .logo-icon img { width:100%;height:100%;border-radius:18px;object-fit:contain; }

    .brand-title { font-size:2.2rem;font-weight:800;color:#f1f5f9;letter-spacing:-1px;margin-bottom:4px; }
    .brand-subtitle { font-size:0.92rem;font-weight:600;color:#3b82f6;letter-spacing:3px;text-transform:uppercase;margin-bottom:12px; }
    .brand-tagline { font-size:0.88rem;color:#94a3b8;line-height:1.6;margin-bottom:32px; }

    .brand-colleges { display:flex;justify-content:center;gap:8px;margin-bottom:32px;flex-wrap:wrap; }

    .college-dot {
        width:36px;height:36px;border-radius:10px;
        display:inline-flex;align-items:center;justify-content:center;
        font-size:0.65rem;font-weight:700;color:white;
        transition:transform 0.2s ease;cursor:default;overflow:hidden;
    }
    .college-dot:hover { transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,0.3); }
    .college-dot img { width:100%;height:100%;border-radius:10px;object-fit:contain; }

    .brand-steps { border-top:1px solid rgba(148,163,184,0.15);padding-top:24px;text-align:left; }
    .brand-step { display:flex;align-items:flex-start;gap:12px;margin-bottom:16px; }
    .brand-step:last-child { margin-bottom:0; }
    .step-num {
        width:28px;height:28px;border-radius:50%;
        background:rgba(59,130,246,0.15);color:#60a5fa;
        display:flex;align-items:center;justify-content:center;
        font-size:0.75rem;font-weight:700;flex-shrink:0;
    }
    .step-text { font-size:0.82rem;color:#94a3b8;line-height:1.5; }
    .step-text strong { color:#cbd5e1; }

    /* ========== RIGHT PANEL ========== */
    .auth-form-panel {
        width:60%;
        min-height:100vh;
        display:flex;
        align-items:flex-start;
        justify-content:center;
        padding:40px 48px;
        overflow-y:auto;
    }

    .auth-form-wrapper {
        width:100%;
        max-width:540px;
        padding:20px 0;
    }

    .form-title { font-size:1.75rem;font-weight:800;color:#f1f5f9;letter-spacing:-0.5px;margin-bottom:4px; }
    .form-subtitle { font-size:0.88rem;color:#64748b;margin-bottom:28px; }

    /* ========== FLASH ========== */
    .flash-message {
        padding:12px 16px;border-radius:8px;margin-bottom:20px;
        font-size:0.85rem;font-weight:500;
        display:flex;align-items:center;gap:10px;
    }
    .flash-error { background:rgba(239,68,68,0.1);color:#fca5a5;border-left:4px solid #ef4444; }
    .flash-success { background:rgba(16,185,129,0.1);color:#6ee7b7;border-left:4px solid #10b981; }

    /* ========== FORMAT GUIDE ========== */
    .format-guide {
        display:flex;align-items:center;gap:10px;padding:11px 14px;
        background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.15);
        border-radius:8px;margin-bottom:20px;
    }
    .format-guide code {
        font-family:'JetBrains Mono',monospace;font-weight:700;font-size:0.82rem;
        background:rgba(59,130,246,0.12);padding:2px 8px;border-radius:4px;color:#60a5fa;
    }
    .format-guide span { font-size:0.78rem;color:#94a3b8;line-height:1.4; }

    /* ========== ID VISUAL ========== */
    .id-visual { display:flex;align-items:center;gap:4px;margin-bottom:16px;padding:0 2px; }
    .id-visual .id-part { flex:1;text-align:center; }
    .id-visual .id-part-box {
        border-radius:8px;padding:8px 4px;
        font-family:'JetBrains Mono',monospace;font-weight:700;font-size:1.1rem;margin-bottom:4px;
    }
    .id-visual .id-part-label { font-size:0.62rem;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;font-weight:600; }
    .id-visual .id-dash { color:#475569;font-weight:800;font-size:1.2rem;margin:0 2px;padding-bottom:16px; }

    .id-yy { background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.25);color:#60a5fa; }
    .id-yl { background:rgba(139,92,246,0.12);border:1px solid rgba(139,92,246,0.25);color:#c4b5fd; }
    .id-nn { background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.25);color:#6ee7b7; }

    /* ========== FORM ========== */
    .form-group { margin-bottom:16px; }
    .form-group label { display:block;font-size:0.82rem;font-weight:600;color:#cbd5e1;margin-bottom:6px; }
    .form-group label .req { color:#ef4444;margin-left:2px; }

    .form-control {
        width:100%;padding:11px 14px;background:#1e293b;
        border:1.5px solid #334155;border-radius:10px;
        color:#f1f5f9;font-size:0.88rem;font-family:'Inter',sans-serif;
        outline:none;transition:all 0.25s ease;
    }
    .form-control:focus { border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.12); }
    .form-control::placeholder { color:#475569; }
    .form-control.valid { border-color:rgba(16,185,129,0.5); }
    .form-control.invalid { border-color:rgba(239,68,68,0.5); }

    select.form-control {
        cursor:pointer;appearance:none;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat:no-repeat;background-position:right 14px center;padding-right:36px;
    }
    select.form-control option { background:#1e293b;color:#f1f5f9; }

    .form-row { display:grid;grid-template-columns:1fr 1fr;gap:12px; }

    /* Student ID */
    .sid-input-wrap { position:relative; }
    .sid-input-wrap .form-control {
        font-family:'JetBrains Mono',monospace;font-size:1.05rem;font-weight:600;
        letter-spacing:3px;text-align:center;padding-right:44px;text-transform:uppercase;
    }
    .sid-status { position:absolute;right:14px;top:50%;transform:translateY(-50%); }
    .sid-hint { font-size:0.75rem;margin-top:6px;color:#64748b;min-height:18px;transition:color 0.2s; }

    /* College preview */
    .college-preview {
        display:none;margin-top:10px;padding:10px 14px;
        background:rgba(30,41,59,0.5);border:1px solid #334155;
        border-radius:8px;align-items:center;gap:12px;
    }
    .college-preview.show { display:flex; }
    .college-preview-logo { width:36px;height:36px;border-radius:8px;overflow:hidden;flex-shrink:0;border:1px solid #334155; }
    .college-preview-logo img { width:100%;height:100%;object-fit:cover; }
    .college-preview-name { font-size:0.82rem;font-weight:700;color:#f1f5f9; }
    .college-preview-desc { font-size:0.72rem;color:#64748b;margin-top:2px; }

    /* Button */
    .btn-register {
        display:flex;align-items:center;justify-content:center;gap:10px;
        width:100%;padding:14px 24px;
        background:#3b82f6;color:white;border:none;border-radius:10px;
        font-size:0.92rem;font-weight:600;font-family:'Inter',sans-serif;
        cursor:pointer;transition:all 0.2s ease;margin-top:8px;
    }
    .btn-register:hover:not(:disabled) { background:#2563eb;transform:translateY(-1px);box-shadow:0 4px 16px rgba(59,130,246,0.3); }
    .btn-register:disabled { opacity:0.7;cursor:not-allowed; }

    .login-link { text-align:center;margin-top:20px;font-size:0.85rem;color:#64748b; }
    .login-link a { color:#3b82f6;text-decoration:none;font-weight:600; }
    .login-link a:hover { text-decoration:underline; }

    .spinner { width:18px;height:18px;border:2.5px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.6s linear infinite; }
    @keyframes spin { to{transform:rotate(360deg)} }

    /* ========== RESPONSIVE ========== */
    @media(max-width:900px) {
        .auth-container{flex-direction:column}
        .auth-brand{width:100%;min-height:auto;padding:32px 24px}
        .brand-steps{display:none}
        .brand-title{font-size:1.6rem}
        .auth-form-panel{width:100%;padding:32px 24px;min-height:auto}
    }

    @media(max-width:540px) {
        .auth-brand{padding:24px 16px}
        .auth-form-panel{padding:24px 16px}
        .form-row{grid-template-columns:1fr}
        .form-title{font-size:1.4rem}
        .id-visual .id-part-box{font-size:0.95rem;padding:6px 2px}
        .college-dot{width:30px;height:30px;font-size:0.55rem}
    }
    </style>
</head>
<body>
    <div class="auth-container">

        <!-- LEFT: Branding -->
        <div class="auth-brand">
            <div class="brand-content">
                <div class="logo-icon">
                    <?php $logoFile = __DIR__ . '/../assets/images/logo/goplanner-logo.png'; ?>
                    <?php if (file_exists($logoFile)): ?>
                        <img src="<?php echo $basePath; ?>/assets/images/logo/goplanner-logo.png" alt="GoPlanner">
                    <?php else: ?>
                        GP
                    <?php endif; ?>
                </div>
                <h1 class="brand-title">GoPlanner</h1>
                <p class="brand-subtitle">DEBESMSCAT</p>
                <p class="brand-tagline">Create your student account to access your academic schedule and announcements</p>

                <div class="brand-colleges">
                    <?php
                    $collegeList = [
                        ['abbr'=>'CA','color'=>'#166534'],
                        ['abbr'=>'CCIT','color'=>'#0D9488'],
                        ['abbr'=>'CBTE','color'=>'#DC2626'],
                        ['abbr'=>'CEng','color'=>'#800020'],
                        ['abbr'=>'CE','color'=>'#1E3A8A'],
                        ['abbr'=>'CIT','color'=>'#4B5563'],
                    ];
                    foreach ($collegeList as $col):
                        $colLogoFile = __DIR__ . '/../assets/images/colleges/' . $col['abbr'] . '.png';
                        $colLogoSrc  = $basePath . '/assets/images/colleges/' . $col['abbr'] . '.png';
                        $hasLogo = file_exists($colLogoFile);
                    ?>
                        <span class="college-dot"
                              style="background:<?php echo $hasLogo ? 'rgba(255,255,255,0.1)' : $col['color']; ?>;"
                              title="<?php echo htmlspecialchars($col['abbr']); ?>">
                            <?php if ($hasLogo): ?>
                                <img src="<?php echo $colLogoSrc; ?>" alt="<?php echo htmlspecialchars($col['abbr']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($col['abbr'], 0, 2)); ?>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <div class="brand-steps">
                    <div class="brand-step">
                        <div class="step-num">1</div>
                        <div class="step-text"><strong>Enter your Student ID</strong><br>Format: YY-Y-NNNN (e.g., 25-1-0001)</div>
                    </div>
                    <div class="brand-step">
                        <div class="step-num">2</div>
                        <div class="step-text"><strong>Fill in your details</strong><br>Name, college, program, year & section</div>
                    </div>
                    <div class="brand-step">
                        <div class="step-num">3</div>
                        <div class="step-text"><strong>Wait for approval</strong><br>Admin will verify and activate your account</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Registration Form -->
        <div class="auth-form-panel">
            <div class="auth-form-wrapper">
                <h2 class="form-title">Create Account</h2>
                <p class="form-subtitle">Register as a student to get started</p>

                <?php
                // Show registration errors
                if (isset($_SESSION['reg_errors'])) {
                    echo '<div class="flash-message flash-error">';
                    foreach ($_SESSION['reg_errors'] as $err) {
                        echo '<div>' . htmlspecialchars($err) . '</div>';
                    }
                    
                    echo '</div>';
                    unset($_SESSION['reg_errors']);
                }

                // Restore old input values
                $old = $_SESSION['reg_old'] ?? [];
                unset($_SESSION['reg_old']);
                ?>

                <form action="<?php echo $basePath; ?>/api/auth/register.php" method="POST" id="registerForm" novalidate>

                    <!-- Format guide -->
                    <div class="format-guide">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>Student ID format: <code>YY-Y-NNNN</code> &nbsp;Example: <code>25-1-0001</code></span>
                    </div>

                    <!-- Visual breakdown -->
                    <div class="id-visual">
                        <div class="id-part">
                            <div class="id-part-box id-yy" id="vYY">YY</div>
                            <div class="id-part-label">Year</div>
                        </div>
                        <div class="id-dash">&ndash;</div>
                        <div class="id-part">
                            <div class="id-part-box id-yl" id="vYL">Y</div>
                            <div class="id-part-label">Level</div>
                        </div>
                        <div class="id-dash">&ndash;</div>
                        <div class="id-part">
                            <div class="id-part-box id-nn" id="vNN">NNNN</div>
                            <div class="id-part-label">Number</div>
                        </div>
                    </div>

                    <!-- Student ID -->
                    <div class="form-group">
                        <label>Student ID <span class="req">*</span></label>
                        <div class="sid-input-wrap">
                            <input type="text" name="student_id" id="studentIdInput" class="form-control" placeholder="25-1-0001" required maxlength="10" autocomplete="off" value="<?= htmlspecialchars($old['student_id'] ?? '') ?>">
                            <div class="sid-status" id="sidStatus"></div>
                        </div>
                        <div class="sid-hint" id="sidHint">Enter your Student ID</div>
                    </div>

                    <!-- Name -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="req">*</span></label>
                            <input type="text" name="first_name" class="form-control" placeholder="Juan" required value="<?= htmlspecialchars($old['first_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="req">*</span></label>
                            <input type="text" name="last_name" class="form-control" placeholder="Dela Cruz" required value="<?= htmlspecialchars($old['last_name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" placeholder="Santos" value="<?= htmlspecialchars($old['middle_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <select name="suffix" class="form-control">
                                <option value="">None</option>
                                <?php foreach (['Jr.','Sr.','II','III','IV','V','VI','VII'] as $sfx): ?>
                                    <option value="<?= $sfx ?>" <?= (isset($old['suffix']) && $old['suffix'] === $sfx) ? 'selected' : '' ?>><?= $sfx ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label>Email Address <span class="req">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="juan.delacruz@email.com" required value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                    </div>

                    <!-- College -->
                    <div class="form-group">
                        <label>College <span class="req">*</span></label>
                        <select name="college_id" id="collegeSelect" class="form-control" required>
                            <option value="">Select your college</option>
                            <?php foreach ($colleges as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                    data-logo="<?= htmlspecialchars($c['logo'] ?? '') ?>"
                                    data-color="<?= htmlspecialchars($c['color'] ?? '#3b82f6') ?>"
                                    data-abbr="<?= htmlspecialchars($c['abbreviation'] ?? $c['code']) ?>"
                                    data-desc="<?= htmlspecialchars($c['description'] ?? '') ?>"
                                    <?= (isset($old['college_id']) && $old['college_id'] == $c['id']) ? 'selected' : '' ?>
                                ><?= htmlspecialchars($c['abbreviation']) ?> &mdash; <?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="college-preview" id="collegePreview">
                            <div class="college-preview-logo" id="collegeLogo"></div>
                            <div>
                                <div class="college-preview-name" id="collegeName"></div>
                                <div class="college-preview-desc" id="collegeDesc"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Program -->
                    <div class="form-group">
                        <label>Program <span class="req">*</span></label>
                        <select name="program_id" id="programSelect" class="form-control" required>
                            <option value="">Select college first</option>
                        </select>
                    </div>

                    <!-- Year + Section -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Year Level <span class="req">*</span></label>
                            <select name="year_level" class="form-control" required>
                                <option value="">Select year</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= (isset($old['year_level']) && $old['year_level'] == $i) ? 'selected' : '' ?>><?= $i ?><?= $i===1?'st':($i===2?'nd':($i===3?'rd':'th')) ?> Year</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Section</label>
                            <input type="text" name="section" class="form-control" placeholder="e.g. A" maxlength="10" value="<?= htmlspecialchars($old['section'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password <span class="req">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password <span class="req">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn-register" id="submitBtn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        Create Account
                    </button>
                </form>

                <div class="login-link">
                    Already have an account? <a href="<?php echo $basePath; ?>/pages/login.php">Sign In</a>
                </div>
            </div>
        </div>
    </div>

<script>
(function() {
    'use strict';

    var sidInput  = document.getElementById('studentIdInput');
    var sidStatus = document.getElementById('sidStatus');
    var sidHint   = document.getElementById('sidHint');
    var vYY = document.getElementById('vYY');
    var vYL = document.getElementById('vYL');
    var vNN = document.getElementById('vNN');
    var collegeSel = document.getElementById('collegeSelect');
    var programSel = document.getElementById('programSelect');
    var submitBtn  = document.getElementById('submitBtn');
    var form       = document.getElementById('registerForm');

    var programsByCollege = <?= json_encode($programsByCollege) ?>;
    var validateTimer = null;

    // ── Student ID ──
    sidInput.addEventListener('input', function() {
        var val = this.value.trim();
        var clean = val.replace(/[^0-9-]/g, '');
        var digits = clean.replace(/-/g, '');

        if (digits.length >= 2 && !clean.includes('-')) {
            clean = digits.substring(0, 2);
            if (digits.length > 2) clean += '-' + digits.substring(2, 3);
            if (digits.length > 3) clean += '-' + digits.substring(3, 7);
            this.value = clean;
            val = clean;
        }

        var parts = val.split('-');
        vYY.textContent = parts[0] || 'YY';
        vYL.textContent = parts[1] || 'Y';
        vNN.textContent = parts[2] || 'NNNN';

        clearTimeout(validateTimer);

        if (!val) {
            clearSidStatus();
            sidHint.textContent = 'Enter your Student ID';
            sidHint.style.color = '#64748b';
            sidInput.classList.remove('valid', 'invalid');
            return;
        }

        var formatRegex = /^\d{2}-[1-5]-\d{0,4}$/;
        var completeRegex = /^\d{2}-[1-5]-\d{4}$/;

        if (!formatRegex.test(val)) {
            if (/^\d{1,2}$/.test(val)) {
                sidHint.innerHTML = 'Add <strong style="color:#60a5fa">-</strong> then year level <strong style="color:#c4b5fd">1-5</strong>';
            } else if (/^\d{2}-$/.test(val)) {
                sidHint.innerHTML = 'Type year level <strong style="color:#c4b5fd">1-5</strong>';
            } else if (/^\d{2}-[1-5]$/.test(val)) {
                sidHint.innerHTML = 'Add <strong style="color:#60a5fa">-</strong> then 4-digit number <strong style="color:#6ee7b7">0001-9999</strong>';
            } else {
                sidHint.textContent = 'Format: YY-Y-NNNN (e.g., 25-1-0001)';
                sidHint.style.color = '#ef4444';
            }
            sidHint.style.color = sidHint.style.color || '#94a3b8';
            sidInput.classList.remove('valid');
            sidInput.classList.add('invalid');
            clearSidStatus();
            return;
        }

        if (!completeRegex.test(val)) {
            sidHint.textContent = 'Need 4 digits for sequence number';
            sidHint.style.color = '#f59e0b';
            sidInput.classList.remove('valid');
            sidInput.classList.add('invalid');
            clearSidStatus();
            return;
        }

        sidStatus.innerHTML = '<div class="spinner"></div>';
        sidHint.textContent = 'Checking availability...';
        sidHint.style.color = '#94a3b8';
        sidInput.classList.remove('invalid');

        validateTimer = setTimeout(function() {
            fetch('<?php echo $basePath; ?>/api/auth/validate_student_id.php', {
                method: 'POST',
                body: new FormData().set('student_id', val) || (() => { var f = new FormData(); f.append('student_id', val); return f; })()
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.valid) {
                    sidInput.classList.add('valid');
                    sidInput.classList.remove('invalid');
                    sidStatus.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>';
                    sidHint.textContent = res.message;
                    sidHint.style.color = '#10b981';
                } else {
                    sidInput.classList.add('invalid');
                    sidInput.classList.remove('valid');
                    sidStatus.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
                    sidHint.textContent = res.message;
                    sidHint.style.color = '#ef4444';
                }
            })
            .catch(function() {
                clearSidStatus();
                sidHint.textContent = 'Could not verify. Try again.';
                sidHint.style.color = '#ef4444';
            });
        }, 500);
    });

    sidInput.addEventListener('blur', function() { this.value = this.value.toUpperCase().trim(); });
    function clearSidStatus() { sidStatus.innerHTML = ''; }

    // ── College → Programs ──
    collegeSel.addEventListener('change', function() {
        var cid = this.value;
        var opt = this.options[this.selectedIndex];

        programSel.innerHTML = '<option value="">Select your program</option>';

        if (cid && programsByCollege[cid]) {
            programsByCollege[cid].forEach(function(p) {
                var o = document.createElement('option');
                o.value = p.id;
                var label = '[' + p.code + '] ' + p.name;
                if (p.major) label += ' — ' + p.major;
                o.textContent = label;
                programSel.appendChild(o);
            });
            programSel.disabled = false;
        } else {
            programSel.innerHTML = '<option value="">Select college first</option>';
            programSel.disabled = true;
        }

        var preview = document.getElementById('collegePreview');
        if (cid) {
            var logo = opt.dataset.logo;
            var color = opt.dataset.color || '#3b82f6';
            var abbr = opt.dataset.abbr || '';
            var desc = opt.dataset.desc || '';

            if (logo) {
                document.getElementById('collegeLogo').innerHTML = '<img src="<?php echo $basePath; ?>/' + logo + '" alt="" onerror="this.parentElement.innerHTML=\'<div style=&quot;width:100%;height:100%;background:' + color + ';display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:0.65rem;border-radius:8px&quot;>' + abbr.substring(0,3) + '</div>\'">';
            } else {
                document.getElementById('collegeLogo').innerHTML = '<div style="width:100%;height:100%;background:' + color + ';display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:0.65rem;border-radius:8px;">' + abbr.substring(0,3) + '</div>';
            }

            document.getElementById('collegeName').textContent = opt.textContent;
            document.getElementById('collegeDesc').textContent = desc;
            preview.classList.add('show');
        } else {
            preview.classList.remove('show');
        }
    });

    // Fix FormData append
    var origInput = sidInput;
    origInput.addEventListener('input', function() {
        clearTimeout(validateTimer);
    });

    // ── Form Submit ──
    form.addEventListener('submit', function(e) {
        var sid = sidInput.value.trim();
        var completeRegex = /^\d{2}-[1-5]-\d{4}$/;

        if (!completeRegex.test(sid)) {
            e.preventDefault();
            sidInput.focus();
            sidInput.classList.add('invalid');
            sidHint.textContent = 'Please enter a valid Student ID (YY-Y-NNNN)';
            sidHint.style.color = '#ef4444';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<div class="spinner"></div> Creating Account...';
    });

    if (collegeSel.value) collegeSel.dispatchEvent(new Event('change'));
})();
</script>
</body>
</html>