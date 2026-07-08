<?php
// pages/rooms/create.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin', 'instructor']);

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));

$unreadNotifs = 0;
try {
    $s = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $s->execute([$user['id']]);
    $unreadNotifs = (int)$s->fetchColumn();
} catch (Exception $e) {}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Room — GoPlanner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $bp; ?>/assets/css/sidebar.css?v=2">
    <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    html,body{height:100%}
    body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;line-height:1.5;-webkit-font-smoothing:antialiased}
    .page-wrapper{display:flex;min-height:100vh}
    .main-area{flex:1;margin-left:var(--sb-width,260px);display:flex;flex-direction:column;min-height:100vh}

    .topbar{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:60px;background:rgba(15,23,42,0.85);backdrop-filter:blur(12px);border-bottom:1px solid #1e293b}
    .topbar-left{display:flex;align-items:center;gap:12px}
    .menu-toggle{display:none;background:none;border:none;color:#f1f5f9;font-size:1.2rem;cursor:pointer;padding:6px;border-radius:6px}
    .menu-toggle:hover{background:#1e293b}
    .topbar-date{font-size:0.82rem;color:#64748b;font-weight:500}
    .topbar-right{display:flex;align-items:center;gap:16px}
    .topbar-link{position:relative;color:#94a3b8;text-decoration:none;padding:6px;border-radius:8px;transition:all 0.2s ease;display:flex;align-items:center}
    .topbar-link:hover{color:#f1f5f9;background:#1e293b}
    .notif-badge{position:absolute;top:-2px;right:-2px;width:16px;height:16px;background:#ef4444;color:white;border-radius:50%;font-size:0.6rem;display:flex;align-items:center;justify-content:center;font-weight:700}
    .topbar-user{display:flex;align-items:center;gap:8px;text-decoration:none;padding:4px 8px;border-radius:8px;transition:background 0.2s ease}
    .topbar-user:hover{background:#1e293b}
    .topbar-avatar{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.7rem;font-weight:700}
    .topbar-username{font-size:0.82rem;color:#f1f5f9;font-weight:500}

    .content{flex:1;padding:32px;max-width:640px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

    .page-header{margin-bottom:28px;animation:fadeUp 0.5s ease}
    .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .page-header p{font-size:0.88rem;color:#64748b}

    .flash-message{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;font-weight:500;animation:fadeUp 0.3s ease}
    .flash-success{background:rgba(16,185,129,0.1);color:#6ee7b7;border-left:4px solid #10b981}
    .flash-error{background:rgba(239,68,68,0.1);color:#fca5a5;border-left:4px solid #ef4444}

    .form-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:28px;margin-bottom:24px;opacity:0;animation:fadeUp 0.5s ease forwards;animation-delay:0.1s}
    .form-card-title{font-size:0.92rem;font-weight:700;color:#f1f5f9;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #334155;display:flex;align-items:center;gap:8px}

    .form-group{margin-bottom:18px}
    .form-group:last-child{margin-bottom:0}
    .form-group label{display:block;font-size:0.82rem;font-weight:600;color:#cbd5e1;margin-bottom:6px}
    .form-group label .req{color:#ef4444;margin-left:2px}
    .form-group label .optional{color:#475569;font-weight:400;font-size:0.75rem;margin-left:4px}
    .form-hint{font-size:0.75rem;color:#475569;margin-top:4px}

    .form-control{width:100%;padding:11px 14px;background:#0f172a;border:1.5px solid #334155;border-radius:10px;color:#f1f5f9;font-size:0.88rem;font-family:'Inter',sans-serif;outline:none;transition:all 0.25s ease}
    .form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.12)}
    .form-control::placeholder{color:#475569}
    textarea.form-control{resize:vertical;min-height:100px;line-height:1.7}

    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:0.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.2s ease;text-decoration:none;border:none}
    .btn-primary{background:#3b82f6;color:white;padding:14px 28px;font-size:0.92rem}
    .btn-primary:hover{background:#2563eb;transform:translateY(-1px);box-shadow:0 4px 12px rgba(59,130,246,0.3)}
    .btn-outline{background:transparent;border:1.5px solid #334155;color:#cbd5e1}
    .btn-outline:hover{border-color:#3b82f6;color:#3b82f6}

    .form-actions{display:flex;gap:12px;align-items:center;margin-top:8px}
    .spinner{width:18px;height:18px;border:2.5px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.6s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}}
    @media(max-width:480px){.topbar-username,.topbar-date{display:none}}
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php require_once __DIR__ . '/../../components/sidebar.php'; ?>

    <div class="main-area">
        <div class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="openSidebar()">&#9776;</button>
                <span class="topbar-date"><?php echo date('l, F d, Y'); ?></span>
            </div>
            <div class="topbar-right">
                <a href="<?php echo $bp; ?>/pages/notifications/index.php" class="topbar-link" title="Notifications">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($unreadNotifs > 0): ?>
                        <span class="notif-badge"><?php echo $unreadNotifs > 9 ? '9+' : $unreadNotifs; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo $bp; ?>/pages/settings/profile.php" class="topbar-user">
                    <div class="topbar-avatar" style="background:<?php echo $cc; ?>;"><?php echo $ini; ?></div>
                    <span class="topbar-username"><?php echo htmlspecialchars($user['first_name']); ?></span>
                </a>
            </div>
        </div>

        <div class="content">
            <div class="page-header">
                <div><h1>Create Room</h1><p>Set up a new collaborative space for your class or group</p></div>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <form action="<?php echo $bp; ?>/api/rooms/store.php" method="POST" id="roomForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">

                <div class="form-card">
                    <div class="form-card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Room Details
                    </div>

                    <div class="form-group">
                        <label>Room Name <span class="req">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g., BSCS 3-A — Data Structures" required maxlength="100">
                    </div>

                    <div class="form-group">
                        <label>Description <span class="optional">(optional)</span></label>
                        <textarea name="description" class="form-control" placeholder="What is this room for? Add any important details..."></textarea>
                    </div>

                    <div class="form-hint">Once created, you can add members and start posting messages.</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span class="btn-text">Create Room</span>
                        <span class="btn-loading" style="display:none;"><span class="spinner"></span> Creating...</span>
                    </button>
                    <a href="<?php echo $bp; ?>/pages/rooms/index.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('roomForm').addEventListener('submit', function() {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.querySelector('.btn-text').style.display = 'none';
    btn.querySelector('.btn-loading').style.display = 'inline-flex';
});
</script>
</body>
</html>