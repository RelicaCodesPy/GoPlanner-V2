<?php
// pages/rooms/view.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
$role = $user['role'] ?? 'student';

$roomId = (int)($_GET['id'] ?? 0);
if ($roomId <= 0) { redirect('/pages/rooms/index.php'); }

// Fetch room
$room = null;
try {
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email AS creator_email,
               c.abbreviation AS college_abbr, c.color AS college_color
        FROM rooms r
        LEFT JOIN users u ON r.created_by = u.id
        LEFT JOIN colleges c ON u.college_id = c.id
        WHERE r.id = ?
    ");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();
} catch (Exception $e) {}

if (!$room) { setFlash('error', 'Room not found.'); redirect('/pages/rooms/index.php'); }

// Check membership
$isMember = false;
$memberRole = null;
try {
    $stmt = $db->prepare("SELECT role FROM room_members WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$roomId, $user['id']]);
    $m = $stmt->fetch();
    if ($m) { $isMember = true; $memberRole = $m['role']; }
} catch (Exception $e) {}

$isCreator = ($room['created_by'] == $user['id']);
$isAdmin = in_array($role, ['super_admin', 'admin']) || $memberRole === 'admin';

// Auto-join if not blocked
if (!$isMember && !$room['is_archived']) {
    try {
        $stmt = $db->prepare("SELECT id FROM room_blocked_users WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$roomId, $user['id']]);
        $blocked = $stmt->fetch();
        if (!$blocked) {
            $stmt = $db->prepare("INSERT INTO room_members (room_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
            $stmt->execute([$roomId, $user['id']]);
            $isMember = true;
            $memberRole = 'member';
        }
    } catch (Exception $e) {}
}

// Fetch messages
$messages = [];
if ($isMember) {
    try {
        $stmt = $db->prepare("
            SELECT rm.*, u.first_name, u.last_name, c.color AS user_color
            FROM room_messages rm
            LEFT JOIN users u ON rm.user_id = u.id
            LEFT JOIN colleges c ON u.college_id = c.id
            WHERE rm.room_id = ?
            ORDER BY rm.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$roomId]);
        $messages = array_reverse($stmt->fetchAll());
    } catch (Exception $e) {}
}

// Members
$members = [];
try {
    $stmt = $db->prepare("
        SELECT rm.role AS member_role, rm.joined_at, u.id, u.first_name, u.last_name, u.email,
               c.color AS user_color, c.abbreviation AS college_abbr
        FROM room_members rm
        JOIN users u ON rm.user_id = u.id
        LEFT JOIN colleges c ON u.college_id = c.id
        WHERE rm.room_id = ?
        ORDER BY rm.role DESC, u.first_name ASC
    ");
    $stmt->execute([$roomId]);
    $members = $stmt->fetchAll();
} catch (Exception $e) {}

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
    <title><?php echo htmlspecialchars($room['name']); ?> — GoPlanner</title>
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

    .content{flex:1;padding:32px;display:flex;gap:24px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

    .flash-message{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;font-weight:500;animation:fadeUp 0.3s ease}
    .flash-success{background:rgba(16,185,129,0.1);color:#6ee7b7;border-left:4px solid #10b981}
    .flash-error{background:rgba(239,68,68,0.1);color:#fca5a5;border-left:4px solid #ef4444}

    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:0.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.2s ease;text-decoration:none;border:none}
    .btn-sm{padding:6px 14px;font-size:0.78rem;border-radius:6px}
    .btn-outline{background:transparent;border:1.5px solid #334155;color:#cbd5e1}
    .btn-outline:hover{border-color:#3b82f6;color:#3b82f6}
    .btn-primary{background:#3b82f6;color:white}
    .btn-primary:hover{background:#2563eb}

    /* CHAT AREA */
    .chat-area{flex:1;display:flex;flex-direction:column;min-height:calc(100vh - 124px)}
    .chat-header{background:#1e293b;border:1px solid #334155;border-radius:12px 12px 0 0;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;animation:fadeUp 0.5s ease}
    .chat-header-info{display:flex;align-items:center;gap:12px}
    .chat-header-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .chat-header-name{font-size:1rem;font-weight:700}
    .chat-header-meta{font-size:0.75rem;color:#64748b}

    .chat-messages{flex:1;background:#1e293b;border-left:1px solid #334155;border-right:1px solid #334155;padding:20px;overflow-y:auto;display:flex;flex-direction:column;gap:12px;min-height:300px;max-height:calc(100vh - 280px)}
    .msg{display:flex;gap:10px;animation:fadeUp 0.3s ease}
    .msg-avatar{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.6rem;font-weight:700;flex-shrink:0;margin-top:2px}
    .msg-body{flex:1;min-width:0}
    .msg-header{display:flex;align-items:baseline;gap:8px;margin-bottom:2px}
    .msg-name{font-size:0.82rem;font-weight:600}
    .msg-time{font-size:0.68rem;color:#475569}
    .msg-text{font-size:0.85rem;color:#cbd5e1;line-height:1.6;white-space:pre-wrap;word-wrap:break-word}

    .chat-input-area{background:#1e293b;border:1px solid #334155;border-radius:0 0 12px 12px;padding:16px 20px;animation:fadeUp 0.5s ease;animation-delay:0.1s;opacity:0;animation-fill-mode:forwards}
    .chat-form{display:flex;gap:10px}
    .chat-input{flex:1;padding:10px 14px;background:#0f172a;border:1.5px solid #334155;border-radius:10px;color:#f1f5f9;font-size:0.88rem;font-family:'Inter',sans-serif;outline:none;transition:all 0.2s}
    .chat-input:focus{border-color:#3b82f6}
    .chat-input::placeholder{color:#475569}
    .chat-send{width:44px;height:44px;border-radius:10px;background:#3b82f6;border:none;color:white;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s}
    .chat-send:hover{background:#2563eb;transform:translateY(-1px)}

    /* MEMBERS PANEL */
    .members-panel{width:280px;flex-shrink:0;animation:fadeUp 0.5s ease;animation-delay:0.15s;opacity:0;animation-fill-mode:forwards}
    .members-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px}
    .members-title{font-size:0.92rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between}
    .members-count{font-size:0.78rem;color:#64748b;font-weight:500;font-family:'JetBrains Mono',monospace}
    .member-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(30,41,59,0.5)}
    .member-item:last-child{border-bottom:none}
    .member-avatar{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.55rem;font-weight:700;flex-shrink:0}
    .member-name{font-size:0.82rem;font-weight:500;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .member-badge{font-size:0.62rem;padding:2px 6px;border-radius:4px;font-weight:600}
    .badge-admin{background:rgba(245,158,11,0.12);color:#f59e0b}
    .badge-member{background:rgba(100,116,139,0.12);color:#64748b}

    .room-desc-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;margin-bottom:16px}
    .room-desc-title{font-size:0.85rem;font-weight:700;margin-bottom:8px}
    .room-desc-text{font-size:0.82rem;color:#94a3b8;line-height:1.6}
    .room-info-row{display:flex;justify-content:space-between;font-size:0.78rem;color:#64748b;padding:6px 0;border-bottom:1px solid rgba(30,41,59,0.5)}
    .room-info-row:last-child{border-bottom:none}

    .empty-chat{text-align:center;padding:40px 20px;color:#64748b;font-size:0.88rem}

    .not-member{display:flex;align-items:center;justify-content:center;min-height:300px;animation:fadeUp 0.5s ease}
    .not-member-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:40px;text-align:center;max-width:400px}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}.members-panel{display:none}}
    @media(max-width:768px){.content{padding:20px 16px;flex-direction:column}.topbar{padding:0 16px}.chat-messages{max-height:400px}}
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
            <div class="chat-area">
                <div class="chat-header">
                    <div class="chat-header-info">
                        <div class="chat-header-icon" style="background:rgba(236,72,153,0.12);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div>
                            <div class="chat-header-name"><?php echo htmlspecialchars($room['name']); ?></div>
                            <div class="chat-header-meta"><?php echo count($members); ?> members &middot; Created by <?php echo htmlspecialchars($room['first_name'] . ' ' . $room['last_name']); ?></div>
                        </div>
                    </div>
                    <a href="<?php echo $bp; ?>/pages/rooms/index.php" class="btn btn-outline btn-sm">Back</a>
                </div>

                <?php if ($flash): ?>
                    <div class="flash-message flash-<?php echo $flash['type']; ?>" style="margin:12px 0 0;"><?php echo htmlspecialchars($flash['message']); ?></div>
                <?php endif; ?>

                <?php if (!$isMember): ?>
                    <div class="not-member">
                        <div class="not-member-card">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="1.5" style="margin-bottom:16px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <h3 style="font-size:1.1rem;margin-bottom:8px;">Join this room</h3>
                            <p style="color:#64748b;font-size:0.88rem;margin-bottom:20px;"><?php echo $room['description'] ? htmlspecialchars($room['description']) : 'Join to view messages and participate.'; ?></p>
                            <form method="POST" action="<?php echo $bp; ?>/api/rooms/join.php">
                                <input type="hidden" name="room_id" value="<?php echo $roomId; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                <button type="submit" class="btn btn-primary">Join Room</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($messages)): ?>
                            <div class="empty-chat">No messages yet. Start the conversation!</div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                            <div class="msg">
                                <div class="msg-avatar" style="background:<?php echo $msg['user_color'] ?? '#3b82f6'; ?>;">
                                    <?php echo strtoupper(substr($msg['first_name'],0,1) . substr($msg['last_name'],0,1)); ?>
                                </div>
                                <div class="msg-body">
                                    <div class="msg-header">
                                        <span class="msg-name"><?php echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?></span>
                                        <span class="msg-time"><?php echo date('M d, g:i A', strtotime($msg['created_at'])); ?></span>
                                    </div>
                                    <div class="msg-text"><?php echo htmlspecialchars($msg['content'] ?? $msg['message'] ?? ''); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="chat-input-area">
                        <form class="chat-form" method="POST" action="<?php echo $bp; ?>/api/rooms/message.php">
                            <input type="hidden" name="room_id" value="<?php echo $roomId; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                            <input type="text" name="message" class="chat-input" placeholder="Type a message..." autocomplete="off" required>
                            <button type="submit" class="chat-send">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="members-panel">
                <?php if ($room['description']): ?>
                <div class="room-desc-card">
                    <div class="room-desc-title">About</div>
                    <div class="room-desc-text"><?php echo htmlspecialchars($room['description']); ?></div>
                    <div style="margin-top:12px;">
                        <div class="room-info-row"><span>Created</span><span><?php echo timeAgo($room['created_at']); ?></span></div>
                        <?php if ($room['college_abbr']): ?>
                        <div class="room-info-row"><span>College</span><span><?php echo htmlspecialchars($room['college_abbr']); ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="members-card">
                    <div class="members-title">
                        Members
                        <span class="members-count"><?php echo count($members); ?></span>
                    </div>
                    <?php foreach ($members as $m): ?>
                    <div class="member-item">
                        <div class="member-avatar" style="background:<?php echo $m['user_color'] ?? '#3b82f6'; ?>;">
                            <?php echo strtoupper(substr($m['first_name'],0,1) . substr($m['last_name'],0,1)); ?>
                        </div>
                        <div class="member-name"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></div>
                        <?php if ($m['member_role'] === 'admin'): ?>
                            <span class="member-badge badge-admin">Admin</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var chatBox = document.getElementById('chatMessages');
if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
</script>
</body>
</html>