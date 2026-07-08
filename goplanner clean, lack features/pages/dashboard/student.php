<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
$collegeId = $user['college_id'] ?? 0;

$recentAnn = [];
$upcomingEvents = [];
$unreadNotifs = 0;
$totalAnn = 0;
$totalRooms = 0;

try {
    $stmt = $db->prepare('
        SELECT a.id, a.title, a.priority, a.is_pinned, a.event_date, a.created_at,
               u.first_name, u.last_name,
               c.abbreviation AS college_abbr, c.color AS college_color
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.id
        LEFT JOIN colleges c ON a.college_id = c.id
        WHERE a.status = "published"
          AND (a.college_id = ? OR a.college_id IS NULL)
        ORDER BY a.is_pinned DESC, a.created_at DESC
        LIMIT 5
    ');
    $stmt->execute([$collegeId]);
    $recentAnn = $stmt->fetchAll();
} catch (Exception $e) {}

try {
    $stmt = $db->prepare('
        SELECT a.id, a.title, a.event_date, a.event_time, a.map_address,
               c.color AS college_color
        FROM announcements a
        LEFT JOIN colleges c ON a.college_id = c.id
        WHERE a.status = "published"
          AND a.event_date >= CURDATE()
          AND (a.college_id = ? OR a.college_id IS NULL)
        ORDER BY a.event_date ASC
        LIMIT 5
    ');
    $stmt->execute([$collegeId]);
    $upcomingEvents = $stmt->fetchAll();
} catch (Exception $e) {}

try {
    $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    $unreadNotifs = (int)$stmt->fetchColumn();
} catch (Exception $e) { $unreadNotifs = 0; }

try {
    $stmt = $db->prepare('
        SELECT COUNT(*) FROM announcements
        WHERE status = "published"
          AND (college_id = ? OR college_id IS NULL)
    ');
    $stmt->execute([$collegeId]);
    $totalAnn = (int)$stmt->fetchColumn();
} catch (Exception $e) { $totalAnn = 0; }

try {
    $stmt = $db->query('SELECT COUNT(*) FROM rooms WHERE is_archived = 0');
    $totalRooms = (int)$stmt->fetchColumn();
} catch (Exception $e) { $totalRooms = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — GoPlanner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $bp; ?>/assets/css/sidebar.css?v=2">
    <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    html,body{height:100%}
    body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;line-height:1.5;-webkit-font-smoothing:antialiased}
    .page-wrapper{display:flex;min-height:100vh}
    .main-area{flex:1;margin-left:var(--sb-width,260px);display:flex;flex-direction:column;min-height:100vh}

    /* TOPBAR */
    .topbar{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:60px;background:rgba(15,23,42,0.85);backdrop-filter:blur(12px);border-bottom:1px solid #1e293b}
    .topbar-left{display:flex;align-items:center;gap:12px}
    .menu-toggle{display:none;background:none;border:none;color:#f1f5f9;font-size:1.2rem;cursor:pointer;padding:6px;border-radius:6px}
    .menu-toggle:hover{background:#1e293b}
    .topbar-date{font-size:0.82rem;color:#64748b}
    .topbar-right{display:flex;align-items:center;gap:16px}
    .topbar-link{position:relative;color:#94a3b8;text-decoration:none;padding:6px;border-radius:8px;transition:all 0.2s ease;display:flex;align-items:center}
    .topbar-link:hover{color:#f1f5f9;background:#1e293b}
    .notif-badge{position:absolute;top:-2px;right:-2px;width:16px;height:16px;background:#ef4444;color:white;border-radius:50%;font-size:0.6rem;display:flex;align-items:center;justify-content:center;font-weight:700}
    .topbar-user{display:flex;align-items:center;gap:8px;text-decoration:none;padding:4px 8px;border-radius:8px;transition:background 0.2s ease}
    .topbar-user:hover{background:#1e293b}
    .topbar-avatar{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.7rem;font-weight:700}
    .topbar-username{font-size:0.82rem;color:#f1f5f9;font-weight:500}

    /* CONTENT */
    .content{flex:1;padding:32px}
    .page-header{margin-bottom:28px}
    .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .page-header p{font-size:0.88rem;color:#64748b}

    /* STATS */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px}
    .stat-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;display:flex;align-items:center;gap:14px;transition:all 0.2s ease;cursor:default}
    .stat-card:hover{border-color:#475569;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.2)}
    .stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .stat-val{font-size:1.4rem;font-weight:800;color:#f1f5f9;line-height:1;margin-bottom:2px}
    .stat-lbl{font-size:0.75rem;color:#64748b;font-weight:500}

    /* CARDS */
    .card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:24px}
    .card-title{font-size:1rem;font-weight:700;color:#f1f5f9}
    .link-sm{font-size:0.82rem;color:#3b82f6;text-decoration:none;font-weight:500}
    .link-sm:hover{text-decoration:underline}
    .badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600}

    /* ANNOUNCEMENTS */
    .ann-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid rgba(30,41,59,0.5)}
    .ann-item:last-child{border-bottom:none}
    .ann-dot{width:8px;height:8px;border-radius:4px;flex-shrink:0}
    .ann-title-link{font-size:0.85rem;font-weight:600;color:#f1f5f9;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
    .ann-title-link:hover{color:#3b82f6}
    .ann-meta{font-size:0.72rem;color:#64748b;margin-top:2px}

    /* EVENTS */
    .event-item{display:flex;align-items:center;gap:12px;padding:10px 12px;background:#0f172a;border-radius:10px;margin-bottom:8px;border:1px solid transparent;transition:all 0.2s}
    .event-item:hover{border-color:#334155}
    .event-date-box{width:44px;height:44px;border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0}
    .event-day{font-size:1rem;font-weight:800;line-height:1;color:white}
    .event-month{font-size:0.55rem;font-weight:600;text-transform:uppercase;color:rgba(255,255,255,0.8)}
    .event-title{font-size:0.82rem;font-weight:600;color:#f1f5f9}
    .event-time{font-size:0.72rem;color:#64748b}

    /* QUICK LINKS */
    .quick-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
    .quick-link{display:flex;align-items:center;gap:10px;padding:14px;background:#0f172a;border:1px solid #1e293b;border-radius:10px;text-decoration:none;color:#f1f5f9;transition:all 0.2s}
    .quick-link:hover{border-color:#3b82f6;background:rgba(59,130,246,0.05)}
    .quick-link-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .quick-link-text{font-size:0.82rem;font-weight:600}
    .quick-link-desc{font-size:0.68rem;color:#64748b}

    .empty{text-align:center;padding:32px;color:#64748b;font-size:0.85rem}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}.two-col{grid-template-columns:1fr!important}.quick-grid{grid-template-columns:1fr}}
    @media(max-width:480px){.topbar-username,.topbar-date{display:none}.stats-grid{grid-template-columns:1fr 1fr}}
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php require_once __DIR__ . '/../../components/sidebar.php'; ?>
    <div class="main-area">
        <div class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="openSidebar()">☰</button>
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
                <h1>Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                <p>Here's what's happening today</p>
            </div>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(59,130,246,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?php echo number_format($totalAnn); ?></div>
                        <div class="stat-lbl">Announcements</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(16,185,129,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?php echo number_format($totalRooms); ?></div>
                        <div class="stat-lbl">Rooms</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(139,92,246,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </div>
                    <div>
                        <div class="stat-val" style="color:<?php echo $unreadNotifs > 0 ? '#8b5cf6' : '#f1f5f9'; ?>;"><?php echo number_format($unreadNotifs); ?></div>
                        <div class="stat-lbl">Unread</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(245,158,11,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?php echo count($upcomingEvents); ?></div>
                        <div class="stat-lbl">Upcoming</div>
                    </div>
                </div>
            </div>

            <!-- TWO COLUMNS -->
            <div class="two-col" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
                <!-- LEFT -->
                <div>
                    <div class="card" style="margin-bottom:24px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <div class="card-title">Recent Announcements</div>
                            <a href="<?php echo $bp; ?>/pages/announcements/view.php" class="link-sm">View All →</a>
                        </div>
                        <?php if (empty($recentAnn)): ?>
                            <div class="empty">No announcements yet.</div>
                        <?php else: ?>
                            <?php foreach ($recentAnn as $ann): ?>
                            <div class="ann-item">
                                <div class="ann-dot" style="background:<?php echo $ann['college_color'] ?? '#3b82f6'; ?>;"></div>
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <?php if ($ann['is_pinned']): ?><span style="color:#f59e0b;font-size:0.65rem;">📌</span><?php endif; ?>
                                        <a href="<?php echo $bp; ?>/pages/announcements/view.php?id=<?php echo $ann['id']; ?>" class="ann-title-link"><?php echo htmlspecialchars($ann['title']); ?></a>
                                    </div>
                                    <div class="ann-meta">
                                        <?php echo htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']); ?>
                                        · <?php echo timeAgo($ann['created_at']); ?>
                                        <?php if (!empty($ann['college_abbr'])): ?> · <?php echo htmlspecialchars($ann['college_abbr']); ?><?php endif; ?>
                                        <?php if ($ann['priority'] !== 'normal'): ?>
                                            · <span style="color:<?php echo $ann['priority']==='urgent'?'#ef4444':($ann['priority']==='high'?'#f59e0b':'#64748b'); ?>;"><?php echo ucfirst($ann['priority']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- QUICK LINKS -->
                    <div class="card">
                        <div class="card-title" style="margin-bottom:16px;">Quick Links</div>
                        <div class="quick-grid">
                            <a href="<?php echo $bp; ?>/pages/announcements/view.php" class="quick-link">
                                <div class="quick-link-icon" style="background:rgba(59,130,246,0.12);">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                                </div>
                                <div><div class="quick-link-text">Announcements</div><div class="quick-link-desc">View latest updates</div></div>
                            </a>
                            <a href="<?php echo $bp; ?>/pages/rooms/index.php" class="quick-link">
                                <div class="quick-link-icon" style="background:rgba(16,185,129,0.12);">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </div>
                                <div><div class="quick-link-text">Rooms</div><div class="quick-link-desc">Browse available rooms</div></div>
                            </a>
                            <a href="<?php echo $bp; ?>/pages/notifications/index.php" class="quick-link">
                                <div class="quick-link-icon" style="background:rgba(139,92,246,0.12);">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                </div>
                                <div><div class="quick-link-text">Notifications</div><div class="quick-link-desc">Check your alerts</div></div>
                            </a>
                            <a href="<?php echo $bp; ?>/pages/calendar/index.php" class="quick-link">
                                <div class="quick-link-icon" style="background:rgba(245,158,11,0.12);">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </div>
                                <div><div class="quick-link-text">Calendar</div><div class="quick-link-desc">View schedule</div></div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- RIGHT -->
                <div>
                    <div class="card" style="margin-bottom:24px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <div class="card-title">Upcoming Events</div>
                        </div>
                        <?php if (empty($upcomingEvents)): ?>
                            <div class="empty">No upcoming events.</div>
                        <?php else: ?>
                            <?php foreach ($upcomingEvents as $ev): ?>
                            <div class="event-item">
                                <div class="event-date-box" style="background:<?php echo $ev['college_color'] ?? '#3b82f6'; ?>;">
                                    <div class="event-day"><?php echo date('d', strtotime($ev['event_date'])); ?></div>
                                    <div class="event-month"><?php echo date('M', strtotime($ev['event_date'])); ?></div>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div class="event-title"><?php echo htmlspecialchars($ev['title']); ?></div>
                                    <div class="event-time">
                                        <?php if ($ev['event_time']): echo date('g:i A', strtotime($ev['event_time'])); endif; ?>
                                        <?php if ($ev['map_address']): ?> · <?php echo htmlspecialchars($ev['map_address']); ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="card-title" style="margin-bottom:16px;">Your Info</div>
                        <div style="display:flex;flex-direction:column;gap:10px;font-size:0.82rem;">
                            <div><span style="color:#64748b;">College:</span> <span style="font-weight:600;"><?php echo htmlspecialchars($user['college_name'] ?? 'N/A'); ?></span></div>
                            <div><span style="color:#64748b;">Program:</span> <span style="font-weight:600;"><?php echo htmlspecialchars($user['program_name'] ?? 'N/A'); ?></span></div>
                            <div><span style="color:#64748b;">Year Level:</span> <span style="font-weight:600;"><?php echo htmlspecialchars($user['year_level'] ?? 'N/A'); ?></span></div>
                            <div><span style="color:#64748b;">Section:</span> <span style="font-weight:600;"><?php echo htmlspecialchars($user['section'] ?? 'N/A'); ?></span></div>
                            <div><span style="color:#64748b;">Email:</span> <span style="font-weight:600;"><?php echo htmlspecialchars($user['email'] ?? ''); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>