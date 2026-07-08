<?php
// pages/rooms/index.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
$role = $user['role'] ?? 'student';

$search = trim($_GET['q'] ?? '');

$rooms = [];
try {
    $where = ["r.is_archived = 0"];
    $params = [];

    // Students/instructors see only rooms they're members of
    if (in_array($role, ['student', 'instructor'])) {
        $where[] = "(rm.user_id IS NOT NULL OR r.created_by = ?)";
        $params[] = $user['id'];
    }

    if ($search) {
        $where[] = "(r.name LIKE ? OR r.description LIKE ?)";
        $s = "%{$search}%";
        $params = array_merge($params, [$s, $s]);
    }

    $whereSQL = implode(' AND ', $where);

    $stmt = $db->prepare("
        SELECT r.id, r.name, r.description, r.created_by, r.created_at,
               u.first_name, u.last_name,
               c.abbreviation AS college_abbr, c.color AS college_color,
               (SELECT COUNT(*) FROM room_members WHERE room_id = r.id) AS member_count,
               (SELECT COUNT(*) FROM room_messages WHERE room_id = r.id) AS message_count,
               (SELECT MAX(created_at) FROM room_messages WHERE room_id = r.id) AS last_message_at,
               rm.user_id AS is_member
        FROM rooms r
        LEFT JOIN users u ON r.created_by = u.id
        LEFT JOIN colleges c ON u.college_id = c.id
        LEFT JOIN room_members rm ON rm.room_id = r.id AND rm.user_id = ?
        WHERE {$whereSQL}
        ORDER BY last_message_at DESC, r.created_at DESC
        LIMIT 50
    ");
    $allParams = array_merge([$user['id']], $params);
    $stmt->execute($allParams);
    $rooms = $stmt->fetchAll();
} catch (Exception $e) {}

$unreadNotifs = 0;
try {
    $s = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $s->execute([$user['id']]);
    $unreadNotifs = (int)$s->fetchColumn();
} catch (Exception $e) {}

$flash = getFlash();
$canCreate = in_array($role, ['super_admin', 'admin', 'instructor']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms — GoPlanner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
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

    .content{flex:1;padding:32px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

    .page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:12px;animation:fadeUp 0.5s ease}
    .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .page-header p{font-size:0.88rem;color:#64748b}

    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:0.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.2s ease;text-decoration:none;border:none}
    .btn-primary{background:#3b82f6;color:white}
    .btn-primary:hover{background:#2563eb;transform:translateY(-1px);box-shadow:0 4px 12px rgba(59,130,246,0.3)}

    .flash-message{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;font-weight:500;animation:fadeUp 0.3s ease}
    .flash-success{background:rgba(16,185,129,0.1);color:#6ee7b7;border-left:4px solid #10b981}
    .flash-error{background:rgba(239,68,68,0.1);color:#fca5a5;border-left:4px solid #ef4444}

    .search-bar{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;align-items:center;animation:fadeUp 0.5s ease;animation-delay:0.05s;opacity:0;animation-fill-mode:forwards}
    .search-input{flex:1;padding:8px 14px;background:#0f172a;border:1.5px solid #334155;border-radius:8px;color:#f1f5f9;font-size:0.85rem;font-family:'Inter',sans-serif;outline:none;transition:all 0.2s}
    .search-input:focus{border-color:#3b82f6}
    .search-input::placeholder{color:#475569}
    .search-btn{padding:8px 18px;background:#3b82f6;color:white;border:none;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;transition:all 0.2s}
    .search-btn:hover{background:#2563eb}

    .rooms-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
    .room-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;transition:all 0.25s ease;cursor:pointer;opacity:0;animation:fadeUp 0.5s ease forwards;text-decoration:none;color:inherit;display:block}
    .room-card:hover{border-color:#475569;transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.25)}
    .room-card-header{display:flex;align-items:center;gap:12px;margin-bottom:12px}
    .room-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .room-name{font-size:1rem;font-weight:700;color:#f1f5f9}
    .room-creator{font-size:0.72rem;color:#64748b;margin-top:2px}
    .room-desc{font-size:0.82rem;color:#94a3b8;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:14px}
    .room-footer{display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid rgba(30,41,59,0.5)}
    .room-stat{font-size:0.72rem;color:#64748b;display:flex;align-items:center;gap:4px}
    .room-badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:0.68rem;font-weight:600}
    .room-joined{background:rgba(16,185,129,0.12);color:#10b981}
    .room-open{background:rgba(59,130,246,0.12);color:#60a5fa}

    .empty-state{text-align:center;padding:60px 20px;animation:fadeUp 0.5s ease}
    .empty-icon{width:80px;height:80px;margin:0 auto 20px;background:rgba(236,72,153,0.08);border-radius:20px;display:flex;align-items:center;justify-content:center}
    .empty-title{font-size:1.1rem;font-weight:700;color:#f1f5f9;margin-bottom:6px}
    .empty-desc{font-size:0.88rem;color:#64748b}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}.rooms-grid{grid-template-columns:1fr}}
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
                <div><h1>Rooms</h1><p>Collaborate and communicate in shared spaces</p></div>
                <?php if ($canCreate): ?>
                    <a href="<?php echo $bp; ?>/pages/rooms/create.php" class="btn btn-primary">+ Create Room</a>
                <?php endif; ?>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <form class="search-bar" method="GET">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" class="search-input" placeholder="Search rooms..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">Search</button>
            </form>

            <?php if (empty($rooms)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div class="empty-title">No rooms found</div>
                    <div class="empty-desc"><?php echo $search ? 'No rooms match your search.' : ($canCreate ? 'Create your first room to get started.' : 'You haven\'t been added to any rooms yet.'); ?></div>
                </div>
            <?php else: ?>
                <div class="rooms-grid">
                    <?php foreach ($rooms as $i => $rm): ?>
                    <a href="<?php echo $bp; ?>/pages/rooms/view.php?id=<?php echo $rm['id']; ?>" class="room-card" style="animation-delay:<?php echo 0.05 * $i; ?>s;">
                        <div class="room-card-header">
                            <div class="room-icon" style="background:rgba(236,72,153,0.12);">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div class="room-name"><?php echo htmlspecialchars($rm['name']); ?></div>
                                <div class="room-creator">by <?php echo htmlspecialchars($rm['first_name'] . ' ' . $rm['last_name']); ?></div>
                            </div>
                            <?php if ($rm['is_member']): ?>
                                <span class="room-badge room-joined">Joined</span>
                            <?php else: ?>
                                <span class="room-badge room-open">Open</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($rm['description']): ?>
                            <div class="room-desc"><?php echo htmlspecialchars($rm['description']); ?></div>
                        <?php endif; ?>
                        <div class="room-footer">
                            <span class="room-stat">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                <?php echo $rm['member_count']; ?> members
                            </span>
                            <span class="room-stat">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <?php echo $rm['message_count']; ?> messages
                            </span>
                            <span class="room-stat"><?php echo $rm['last_message_at'] ? timeAgo($rm['last_message_at']) : timeAgo($rm['created_at']); ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>