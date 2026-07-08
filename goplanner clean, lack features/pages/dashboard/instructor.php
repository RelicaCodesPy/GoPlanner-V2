<?php
// pages/dashboard/instructor.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['instructor']);

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#10b981';
$ini = strtoupper(substr($user['first_name'] ?? 'I', 0, 1) . substr($user['last_name'] ?? 'N', 0, 1));
$collegeId = $user['college_id'] ?? 0;

// Stats
$myAnnouncements = 0;
$myRooms = 0;
$collegeAnn = 0;
$unreadNotifs = 0;

try {
    $s = $db->prepare("SELECT COUNT(*) FROM announcements WHERE created_by=? AND status='published'");
    $s->execute([$user['id']]);
    $myAnnouncements = (int)$s->fetchColumn();
} catch (Exception $e) {}

try {
    $s = $db->prepare("SELECT COUNT(*) FROM rooms WHERE created_by=? AND is_archived=0");
    $s->execute([$user['id']]);
    $myRooms = (int)$s->fetchColumn();
} catch (Exception $e) {}

try {
    $s = $db->prepare("SELECT COUNT(*) FROM announcements WHERE status='published' AND (college_id=? OR college_id IS NULL)");
    $s->execute([$collegeId]);
    $collegeAnn = (int)$s->fetchColumn();
} catch (Exception $e) {}

try {
    $s = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
    $s->execute([$user['id']]);
    $unreadNotifs = (int)$s->fetchColumn();
} catch (Exception $e) {}

// Recent announcements (college-wide)
$recentAnn = [];
try {
    $s = $db->prepare("
        SELECT a.id, a.title, a.priority, a.is_pinned, a.created_at,
               u.first_name, u.last_name, c.abbreviation AS college_abbr, c.color AS college_color
        FROM announcements a
        LEFT JOIN users u ON a.created_by=u.id
        LEFT JOIN colleges c ON a.college_id=c.id
        WHERE a.status='published' AND (a.college_id=? OR a.college_id IS NULL)
        ORDER BY a.is_pinned DESC, a.created_at DESC LIMIT 5
    ");
    $s->execute([$collegeId]);
    $recentAnn = $s->fetchAll();
} catch (Exception $e) {}

// My recent announcements
$myAnn = [];
try {
    $s = $db->prepare("
        SELECT a.id, a.title, a.status, a.is_pinned, a.created_at, a.event_date,
               c.abbreviation AS college_abbr, c.color AS college_color
        FROM announcements a
        LEFT JOIN colleges c ON a.college_id=c.id
        WHERE a.created_by=?
        ORDER BY a.created_at DESC LIMIT 5
    ");
    $s->execute([$user['id']]);
    $myAnn = $s->fetchAll();
} catch (Exception $e) {}

// Upcoming events
$upcomingEvents = [];
try {
    $s = $db->prepare("
        SELECT a.id, a.title, a.event_date, a.event_time, a.map_address, c.color AS college_color
        FROM announcements a
        LEFT JOIN colleges c ON a.college_id=c.id
        WHERE a.status='published' AND a.event_date >= CURDATE()
          AND (a.college_id=? OR a.college_id IS NULL)
        ORDER BY a.event_date ASC LIMIT 5
    ");
    $s->execute([$collegeId]);
    $upcomingEvents = $s->fetchAll();
} catch (Exception $e) {}

// My rooms
$rooms = [];
try {
    $s = $db->prepare("
        SELECT r.id, r.name, r.description, r.created_at,
               (SELECT COUNT(*) FROM room_members rm WHERE rm.room_id=r.id) AS member_count
        FROM rooms r
        WHERE r.created_by=? AND r.is_archived=0
        ORDER BY r.created_at DESC LIMIT 5
    ");
    $s->execute([$user['id']]);
    $rooms = $s->fetchAll();
} catch (Exception $e) {}

$statusColors = ['published' => '#10b981', 'draft' => '#f59e0b', 'archived' => '#64748b'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard — GoPlanner</title>
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
    .menu-toggle{display:none;background:none;border:none;color:#f1f5f9;font-size:1.2rem;cursor:pointer;padding:6px;border-radius:6px;transition:background 0.2s}
    .menu-toggle:hover{background:#1e293b}
    .topbar-date{font-size:0.82rem;color:#64748b;font-weight:500}
    .topbar-right{display:flex;align-items:center;gap:16px}
    .topbar-link{position:relative;color:#94a3b8;text-decoration:none;padding:6px;border-radius:8px;transition:all 0.2s ease;display:flex;align-items:center}
    .topbar-link:hover{color:#f1f5f9;background:#1e293b}
    .notif-badge{position:absolute;top:-2px;right:-2px;width:16px;height:16px;background:#ef4444;color:white;border-radius:50%;font-size:0.6rem;display:flex;align-items:center;justify-content:center;font-weight:700;animation:pulse 2s infinite}
    @keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}
    .topbar-user{display:flex;align-items:center;gap:8px;text-decoration:none;padding:4px 8px;border-radius:8px;transition:background 0.2s ease}
    .topbar-user:hover{background:#1e293b}
    .topbar-avatar{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.7rem;font-weight:700}
    .topbar-username{font-size:0.82rem;color:#f1f5f9;font-weight:500}

    .content{flex:1;padding:32px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

    .page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;flex-wrap:wrap;gap:12px;animation:fadeUp 0.5s ease}
    .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .page-header p{font-size:0.88rem;color:#64748b}

    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:28px}
    .stat-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;display:flex;align-items:center;gap:14px;transition:all 0.25s ease;cursor:default;opacity:0;animation:fadeUp 0.5s ease forwards}
    .stat-card:nth-child(1){animation-delay:0.05s}
    .stat-card:nth-child(2){animation-delay:0.1s}
    .stat-card:nth-child(3){animation-delay:0.15s}
    .stat-card:nth-child(4){animation-delay:0.2s}
    .stat-card:hover{border-color:#475569;transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.25)}
    .stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .stat-val{font-size:1.4rem;font-weight:800;color:#f1f5f9;line-height:1;margin-bottom:2px}
    .stat-lbl{font-size:0.75rem;color:#64748b;font-weight:500}

    .card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:24px;opacity:0;animation:fadeUp 0.5s ease forwards;animation-delay:0.25s}
    .card-title{font-size:1rem;font-weight:700;color:#f1f5f9}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:0.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.2s ease;text-decoration:none;border:none}
    .btn-primary{background:#3b82f6;color:white}
    .btn-primary:hover{background:#2563eb;transform:translateY(-1px);box-shadow:0 4px 12px rgba(59,130,246,0.3)}
    .btn-outline{background:transparent;border:1.5px solid #334155;color:#cbd5e1}
    .btn-outline:hover{border-color:#3b82f6;color:#3b82f6;background:rgba(59,130,246,0.05)}
    .link-sm{font-size:0.82rem;color:#3b82f6;text-decoration:none;font-weight:500;transition:color 0.2s}
    .link-sm:hover{text-decoration:underline;color:#60a5fa}

    .ann-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid rgba(30,41,59,0.5)}
    .ann-item:last-child{border-bottom:none}
    .ann-dot{width:8px;height:8px;border-radius:4px;flex-shrink:0}
    .ann-title{font-size:0.85rem;font-weight:600;color:#f1f5f9;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;transition:color 0.2s}
    .ann-title:hover{color:#3b82f6}
    .ann-meta{font-size:0.72rem;color:#64748b;margin-top:2px}
    .badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600}

    .event-item{display:flex;align-items:center;gap:12px;padding:10px 12px;background:#0f172a;border-radius:10px;margin-bottom:8px;border:1px solid transparent;transition:all 0.2s}
    .event-item:hover{border-color:#334155}
    .event-date-box{width:44px;height:44px;border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0}
    .event-day{font-size:1rem;font-weight:800;line-height:1;color:white}
    .event-month{font-size:0.55rem;font-weight:600;text-transform:uppercase;color:rgba(255,255,255,0.8)}
    .event-title{font-size:0.82rem;font-weight:600;color:#f1f5f9}
    .event-time{font-size:0.72rem;color:#64748b}

    .room-card{display:flex;align-items:center;gap:12px;padding:12px;background:#0f172a;border-radius:10px;margin-bottom:8px;border:1px solid transparent;transition:all 0.2s}
    .room-card:hover{border-color:#334155}
    .room-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .room-name{font-size:0.85rem;font-weight:600;color:#f1f5f9}
    .room-meta{font-size:0.72rem;color:#64748b;margin-top:2px}

    .empty-state{text-align:center;padding:32px;color:#64748b;font-size:0.85rem}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}.two-col{grid-template-columns:1fr!important}.stats-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:480px){.stats-grid{grid-template-columns:1fr}.topbar-username,.topbar-date{display:none}}
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
                <div>
                    <h1>Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                    <p>Instructor dashboard &middot; <?php echo htmlspecialchars($user['college_abbr'] ?? ''); ?></p>
                </div>
                <a href="<?php echo $bp; ?>/pages/announcements/create.php" class="btn btn-primary">+ New Announcement</a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(59,130,246,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                    </div>
                    <div><div class="stat-val"><?php echo number_format($myAnnouncements); ?></div><div class="stat-lbl">My Announcements</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(16,185,129,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div><div class="stat-val"><?php echo number_format($myRooms); ?></div><div class="stat-lbl">My Rooms</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(139,92,246,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </div>
                    <div><div class="stat-val" style="color:<?php echo $unreadNotifs > 0 ? '#8b5cf6' : '#f1f5f9'; ?>;"><?php echo number_format($unreadNotifs); ?></div><div class="stat-lbl">Notifications</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(245,158,11,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div><div class="stat-val"><?php echo number_format(count($upcomingEvents)); ?></div><div class="stat-lbl">Upcoming Events</div></div>
                </div>
            </div>

            <div class="two-col" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
                <div>
                    <!-- MY ANNOUNCEMENTS -->
                    <div class="card" style="margin-bottom:24px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <div class="card-title">My Announcements</div>
                            <a href="<?php echo $bp; ?>/pages/announcements/create.php" class="link-sm">+ Create New</a>
                        </div>
                        <?php if (empty($myAnn)): ?>
                            <div class="empty-state">You haven't created any announcements yet.</div>
                        <?php else: ?>
                            <?php foreach ($myAnn as $ann): ?>
                            <div class="ann-item">
                                <div class="ann-dot" style="background:<?php echo $statusColors[$ann['status']] ?? '#64748b'; ?>;"></div>
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <?php if ($ann['is_pinned']): ?><span style="color:#f59e0b;font-size:0.65rem;">&#128204;</span><?php endif; ?>
                                        <a href="<?php echo $bp; ?>/pages/announcements/view.php?id=<?php echo $ann['id']; ?>" class="ann-title"><?php echo htmlspecialchars($ann['title']); ?></a>
                                    </div>
                                    <div class="ann-meta">
                                        <span class="badge" style="background:<?php echo $statusColors[$ann['status']] ?? '#64748b'; ?>20;color:<?php echo $statusColors[$ann['status']] ?? '#64748b'; ?>;"><?php echo ucfirst($ann['status']); ?></span>
                                        &middot; <?php echo timeAgo($ann['created_at']); ?>
                                        <?php if ($ann['event_date']): ?> &middot; Event: <?php echo date('M d, Y', strtotime($ann['event_date'])); ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- COLLEGE ANNOUNCEMENTS -->
                    <div class="card">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <div class="card-title">College Announcements</div>
                            <a href="<?php echo $bp; ?>/pages/announcements/view.php" class="link-sm">View All &rarr;</a>
                        </div>
                        <?php if (empty($recentAnn)): ?>
                            <div class="empty-state">No announcements in your college yet.</div>
                        <?php else: ?>
                            <?php foreach ($recentAnn as $ann): ?>
                            <div class="ann-item">
                                <div class="ann-dot" style="background:<?php echo $ann['college_color'] ?? $cc; ?>;"></div>
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <?php if ($ann['is_pinned']): ?><span style="color:#f59e0b;font-size:0.65rem;">&#128204;</span><?php endif; ?>
                                        <a href="<?php echo $bp; ?>/pages/announcements/view.php?id=<?php echo $ann['id']; ?>" class="ann-title"><?php echo htmlspecialchars($ann['title']); ?></a>
                                    </div>
                                    <div class="ann-meta">
                                        <?php echo htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']); ?>
                                        &middot; <?php echo timeAgo($ann['created_at']); ?>
                                        <?php if ($ann['priority'] !== 'normal'): ?>
                                            &middot; <span class="badge" style="background:rgba(239,68,68,0.12);color:#f87171;"><?php echo ucfirst($ann['priority']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <!-- UPCOMING EVENTS -->
                    <div class="card" style="margin-bottom:24px;">
                        <div class="card-title" style="margin-bottom:16px;">Upcoming Events</div>
                        <?php if (empty($upcomingEvents)): ?>
                            <div class="empty-state">No upcoming events.</div>
                        <?php else: ?>
                            <?php foreach ($upcomingEvents as $ev): ?>
                            <div class="event-item">
                                <div class="event-date-box" style="background:<?php echo $ev['college_color'] ?? $cc; ?>;">
                                    <div class="event-day"><?php echo date('d', strtotime($ev['event_date'])); ?></div>
                                    <div class="event-month"><?php echo date('M', strtotime($ev['event_date'])); ?></div>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div class="event-title"><?php echo htmlspecialchars($ev['title']); ?></div>
                                    <div class="event-time">
                                        <?php if ($ev['event_time']): echo date('g:i A', strtotime($ev['event_time'])); endif; ?>
                                        <?php if ($ev['map_address']): ?> &middot; <?php echo htmlspecialchars($ev['map_address']); ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- MY ROOMS -->
                    <div class="card" style="margin-bottom:24px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <div class="card-title">My Rooms</div>
                            <a href="<?php echo $bp; ?>/pages/rooms/create.php" class="link-sm">+ Create</a>
                        </div>
                        <?php if (empty($rooms)): ?>
                            <div class="empty-state">You haven't created any rooms yet.</div>
                        <?php else: ?>
                            <?php foreach ($rooms as $rm): ?>
                            <div class="room-card">
                                <div class="room-icon" style="background:rgba(16,185,129,0.12);">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div class="room-name"><?php echo htmlspecialchars($rm['name']); ?></div>
                                    <div class="room-meta"><?php echo $rm['member_count']; ?> member<?php echo $rm['member_count'] != 1 ? 's' : ''; ?> &middot; <?php echo timeAgo($rm['created_at']); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- QUICK ACTIONS -->
                    <div class="card">
                        <div class="card-title" style="margin-bottom:16px;">Quick Actions</div>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <a href="<?php echo $bp; ?>/pages/announcements/create.php" class="btn btn-outline" style="justify-content:flex-start;">+ Create Announcement</a>
                            <a href="<?php echo $bp; ?>/pages/rooms/create.php" class="btn btn-outline" style="justify-content:flex-start;">+ Create Room</a>
                            <a href="<?php echo $bp; ?>/pages/calendar/index.php" class="btn btn-outline" style="justify-content:flex-start;">View Calendar</a>
                            <a href="<?php echo $bp; ?>/pages/settings/profile.php" class="btn btn-outline" style="justify-content:flex-start;">Edit Profile</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>