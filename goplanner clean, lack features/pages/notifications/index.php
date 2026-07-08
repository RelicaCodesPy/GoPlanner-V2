<?php
// pages/notifications/index.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));

// Fetch notifications
$notifications = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY is_read ASC, created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {}

$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unreadCount++;
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — GoPlanner</title>
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

    .content{flex:1;padding:32px;max-width:700px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

    .page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:12px;animation:fadeUp 0.5s ease}
    .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .page-header p{font-size:0.88rem;color:#64748b}

    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:0.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.2s ease;text-decoration:none;border:none}
    .btn-outline{background:transparent;border:1.5px solid #334155;color:#cbd5e1}
    .btn-outline:hover{border-color:#3b82f6;color:#3b82f6}
    .btn-sm{padding:6px 14px;font-size:0.78rem;border-radius:6px}

    .flash-message{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;font-weight:500;animation:fadeUp 0.3s ease}
    .flash-success{background:rgba(16,185,129,0.1);color:#6ee7b7;border-left:4px solid #10b981}

    .notif-list{display:flex;flex-direction:column;gap:8px}
    .notif-item{display:flex;align-items:flex-start;gap:14px;padding:16px;background:#1e293b;border:1px solid #334155;border-radius:12px;transition:all 0.2s;opacity:0;animation:fadeUp 0.5s ease forwards;text-decoration:none;color:inherit;cursor:pointer}
    .notif-item:hover{border-color:#475569;transform:translateY(-1px)}
    .notif-item.unread{border-left:3px solid #3b82f6;background:rgba(59,130,246,0.04)}
    .notif-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .notif-title{font-size:0.88rem;font-weight:600;color:#f1f5f9;margin-bottom:2px}
    .notif-message{font-size:0.82rem;color:#94a3b8;line-height:1.5}
    .notif-time{font-size:0.72rem;color:#64748b;margin-top:4px}

    .empty-state{text-align:center;padding:60px 20px;animation:fadeUp 0.5s ease}
    .empty-icon{width:80px;height:80px;margin:0 auto 20px;background:rgba(139,92,246,0.08);border-radius:20px;display:flex;align-items:center;justify-content:center}
    .empty-title{font-size:1.1rem;font-weight:700;color:#f1f5f9;margin-bottom:6px}
    .empty-desc{font-size:0.88rem;color:#64748b}

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
                    <?php if ($unreadCount > 0): ?>
                        <span class="notif-badge"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
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
                <div><h1>Notifications <span style="color:#64748b;font-size:0.9rem;font-weight:500;">(<?php echo $unreadCount; ?> unread)</span></h1><p>Stay updated with alerts and messages</p></div>
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" action="<?php echo $bp; ?>/api/notifications/mark_read.php">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                        <button type="submit" class="btn btn-outline">Mark All Read</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </div>
                    <div class="empty-title">No notifications</div>
                    <div class="empty-desc">You're all caught up! Notifications will appear here when there's activity.</div>
                </div>
            <?php else: ?>
                <div class="notif-list">
                    <?php foreach ($notifications as $i => $n): ?>
                    <div class="notif-item <?php echo $n['is_read'] ? '' : 'unread'; ?>" style="animation-delay:<?php echo 0.03 * $i; ?>s;" onclick="markRead(<?php echo $n['id']; ?>, this)">
                        <div class="notif-icon" style="background:<?php echo $n['is_read'] ? 'rgba(100,116,139,0.1)' : 'rgba(59,130,246,0.12)'; ?>;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?php echo $n['is_read'] ? '#64748b' : '#3b82f6'; ?>" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                            <?php if ($n['message']): ?>
                                <div class="notif-message"><?php echo htmlspecialchars($n['message']); ?></div>
                            <?php endif; ?>
                            <div class="notif-time"><?php echo timeAgo($n['created_at']); ?></div>
                        </div>
                        <?php if (!$n['is_read']): ?>
                            <div style="width:8px;height:8px;border-radius:50%;background:#3b82f6;flex-shrink:0;margin-top:6px;"></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function markRead(id, el) {
    el.classList.remove('unread');
    var dot = el.querySelector('div:last-child');
    if (dot && dot.style.background) dot.remove();
    fetch('<?php echo $bp; ?>/api/notifications/mark_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&csrf_token=<?php echo generateCSRF(); ?>'
    });
}
</script>
</body>
</html>