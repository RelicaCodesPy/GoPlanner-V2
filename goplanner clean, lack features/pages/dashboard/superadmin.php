<?php
// pages/dashboard/superadmin.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin']);

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'S', 0, 1) . substr($user['last_name'] ?? 'A', 0, 1));

// Fetch stats
$totalStudents = 0;
$totalInstructors = 0;
$totalAnn = 0;
$totalRooms = 0;
$totalColleges = 0;
$pendingCount = 0;

try {
    $s = $db->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_archived=0");
    $totalStudents = (int)$s->fetchColumn();
} catch (Exception $e) {}

try {
    $s = $db->query("SELECT COUNT(*) FROM users WHERE role='instructor' AND is_archived=0");
    $totalInstructors = (int)$s->fetchColumn();
} catch (Exception $e) {}

try {
    $s = $db->query("SELECT COUNT(*) FROM announcements WHERE status='published'");
    $totalAnn = (int)$s->fetchColumn();
} catch (Exception $e) {}

try {
    $s = $db->query("SELECT COUNT(*) FROM rooms WHERE is_archived=0");
    $totalRooms = (int)$s->fetchColumn();
} catch (Exception $e) {}

try {
    $s = $db->query("SELECT COUNT(*) FROM colleges WHERE is_active=1");
    $totalColleges = (int)$s->fetchColumn();
} catch (Exception $e) {}

try {
    $s = $db->query("SELECT COUNT(*) FROM users WHERE status='pending'");
    $pendingCount = (int)$s->fetchColumn();
} catch (Exception $e) {}

// College overview
$colleges = [];
try {
    $s = $db->query("
        SELECT c.id, c.name, c.abbreviation, c.color,
            (SELECT COUNT(*) FROM users u WHERE u.college_id=c.id AND u.role='student' AND u.is_archived=0) AS student_count,
            (SELECT COUNT(*) FROM users u WHERE u.college_id=c.id AND u.role='instructor' AND u.is_archived=0) AS faculty_count,
            (SELECT COUNT(*) FROM users u WHERE u.college_id=c.id AND u.role='admin' AND u.is_archived=0) AS admin_count
        FROM colleges c WHERE c.is_active=1 ORDER BY c.name
    ");
    $colleges = $s->fetchAll();
} catch (Exception $e) {}

// Recent announcements
$recentAnn = [];
try {
    $s = $db->query("
        SELECT a.id, a.title, a.priority, a.is_pinned, a.created_at,
               u.first_name, u.last_name, c.abbreviation AS college_abbr, c.color AS college_color
        FROM announcements a
        LEFT JOIN users u ON a.created_by=u.id
        LEFT JOIN colleges c ON a.college_id=c.id
        WHERE a.status='published'
        ORDER BY a.is_pinned DESC, a.created_at DESC LIMIT 5
    ");
    $recentAnn = $s->fetchAll();
} catch (Exception $e) {}

// Pending accounts
$pendingUsers = [];
try {
    $s = $db->query("
        SELECT id, first_name, last_name, email, role, created_at
        FROM users WHERE status='pending' AND is_archived=0
        ORDER BY created_at DESC LIMIT 5
    ");
    $pendingUsers = $s->fetchAll();
} catch (Exception $e) {}

// Recent activity
$activities = [];
try {
    $s = $db->query("
        SELECT al.action, al.created_at, u.first_name, u.last_name
        FROM activity_log al
        LEFT JOIN users u ON al.user_id=u.id
        ORDER BY al.created_at DESC LIMIT 10
    ");
    $activities = $s->fetchAll();
} catch (Exception $e) {}

$unreadNotifs = 0;
try {
    $s = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
    $s->execute([$user['id']]);
    $unreadNotifs = (int)$s->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard — GoPlanner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
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

    /* CONTENT */
    .content{flex:1;padding:32px}

    /* PAGE HEADER */
    .page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;flex-wrap:wrap;gap:12px;animation:fadeUp 0.5s ease}
    .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .page-header p{font-size:0.88rem;color:#64748b}

    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeIn{from{opacity:0}to{opacity:1}}

    /* STATS */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:28px}
    .stat-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;display:flex;align-items:center;gap:14px;transition:all 0.25s ease;cursor:default;opacity:0;animation:fadeUp 0.5s ease forwards}
    .stat-card:nth-child(1){animation-delay:0.05s}
    .stat-card:nth-child(2){animation-delay:0.1s}
    .stat-card:nth-child(3){animation-delay:0.15s}
    .stat-card:nth-child(4){animation-delay:0.2s}
    .stat-card:nth-child(5){animation-delay:0.25s}
    .stat-card:nth-child(6){animation-delay:0.3s}
    .stat-card:hover{border-color:#475569;transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.25)}
    .stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .stat-val{font-size:1.4rem;font-weight:800;color:#f1f5f9;line-height:1;margin-bottom:2px}
    .stat-lbl{font-size:0.75rem;color:#64748b;font-weight:500}

    /* CARDS */
    .card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:24px;opacity:0;animation:fadeUp 0.5s ease forwards;animation-delay:0.35s}
    .card + .card{animation-delay:0.4s}
    .card-title{font-size:1rem;font-weight:700;color:#f1f5f9}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:0.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.2s ease;text-decoration:none;border:none}
    .btn-primary{background:#3b82f6;color:white}
    .btn-primary:hover{background:#2563eb;transform:translateY(-1px);box-shadow:0 4px 12px rgba(59,130,246,0.3)}
    .btn-outline{background:transparent;border:1.5px solid #334155;color:#cbd5e1}
    .btn-outline:hover{border-color:#3b82f6;color:#3b82f6;background:rgba(59,130,246,0.05)}
    .link-sm{font-size:0.82rem;color:#3b82f6;text-decoration:none;font-weight:500;transition:color 0.2s}
    .link-sm:hover{text-decoration:underline;color:#60a5fa}

    /* COLLEGE ROWS */
    .college-row{display:flex;align-items:center;gap:12px;padding:12px;background:#0f172a;border-radius:10px;transition:all 0.2s;border:1px solid transparent}
    .college-row:hover{border-color:#334155;background:rgba(15,23,42,0.8)}
    .college-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.7rem;font-weight:700;flex-shrink:0;overflow:hidden}
    .college-icon img{width:100%;height:100%;object-fit:contain;border-radius:10px}
    .college-stat{text-align:center}
    .college-stat-num{font-weight:700;font-family:'JetBrains Mono',monospace;font-size:0.88rem}
    .college-stat-lbl{font-size:0.62rem;color:#64748b}

    /* ANNOUNCEMENTS */
    .ann-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid rgba(30,41,59,0.5)}
    .ann-item:last-child{border-bottom:none}
    .ann-dot{width:8px;height:8px;border-radius:4px;flex-shrink:0}
    .ann-title{font-size:0.85rem;font-weight:600;color:#f1f5f9;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;transition:color 0.2s}
    .ann-title:hover{color:#3b82f6}
    .ann-meta{font-size:0.72rem;color:#64748b;margin-top:2px}

    /* PENDING */
    .pending-row{display:flex;align-items:center;gap:10px;padding:8px 0}
    .pending-avatar{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.62rem;font-weight:700;flex-shrink:0}

    /* ACTIVITY */
    .activity-item{display:flex;gap:10px;font-size:0.78rem}
    .activity-dot{width:6px;height:6px;border-radius:3px;margin-top:6px;flex-shrink:0}

    .empty-state{text-align:center;padding:32px;color:#64748b;font-size:0.85rem}

    .badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){
        .content{padding:20px 16px}
        .topbar{padding:0 16px}
        .page-header h1{font-size:1.3rem}
        .two-col{grid-template-columns:1fr!important}
        .stats-grid{grid-template-columns:repeat(2,1fr)}
    }
    @media(max-width:480px){
        .stats-grid{grid-template-columns:1fr}
        .topbar-username,.topbar-date{display:none}
    }
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
                    <h1>Super Admin Dashboard</h1>
                    <p>Overview of the entire GoPlanner system</p>
                </div>
                <a href="<?php echo $bp; ?>/pages/announcements/create.php" class="btn btn-primary">+ New Announcement</a>
            </div>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(59,130,246,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                    <div><div class="stat-val"><?php echo number_format($totalStudents); ?></div><div class="stat-lbl">Students</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(16,185,129,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <div><div class="stat-val"><?php echo number_format($totalInstructors); ?></div><div class="stat-lbl">Instructors</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(139,92,246,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                    </div>
                    <div><div class="stat-val"><?php echo number_format($totalAnn); ?></div><div class="stat-lbl">Announcements</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(236,72,153,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div><div class="stat-val"><?php echo number_format($totalRooms); ?></div><div class="stat-lbl">Rooms</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(20,184,166,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#14b8a6" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/></svg>
                    </div>
                    <div><div class="stat-val"><?php echo number_format($totalColleges); ?></div><div class="stat-lbl">Colleges</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(245,158,11,0.12);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div><div class="stat-val" style="color:<?php echo $pendingCount > 0 ? '#f59e0b' : '#f1f5f9'; ?>;"><?php echo number_format($pendingCount); ?></div><div class="stat-lbl">Pending</div></div>
                </div>
            </div>

            <!-- TWO COLUMNS -->
            <div class="two-col" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
                <!-- LEFT -->
                <div>
                    <!-- COLLEGE OVERVIEW -->
                    <div class="card" style="margin-bottom:24px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <div class="card-title">College Overview</div>
                            <a href="<?php echo $bp; ?>/pages/accounts/manage.php" class="link-sm">View All &rarr;</a>
                        </div>
                        <?php if (empty($colleges)): ?>
                            <div class="empty-state">No colleges configured.</div>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <?php foreach ($colleges as $col): ?>
                                <div class="college-row">
                                    <div class="college-icon" style="background:<?php echo htmlspecialchars($col['color']); ?>;">
                                        <?php echo strtoupper(substr($col['abbreviation'], 0, 2)); ?>
                                    </div>
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-size:0.85rem;font-weight:600;"><?php echo htmlspecialchars($col['abbreviation']); ?></div>
                                        <div style="font-size:0.72rem;color:#64748b;"><?php echo htmlspecialchars($col['name']); ?></div>
                                    </div>
                                    <div style="display:flex;gap:16px;flex-shrink:0;">
                                        <div class="college-stat">
                                            <div class="college-stat-num"><?php echo $col['student_count']; ?></div>
                                            <div class="college-stat-lbl">Students</div>
                                        </div>
                                        <div class="college-stat">
                                            <div class="college-stat-num"><?php echo $col['faculty_count']; ?></div>
                                            <div class="college-stat-lbl">Faculty</div>
                                        </div>
                                        <div class="college-stat">
                                            <div class="college-stat-num"><?php echo $col['admin_count']; ?></div>
                                            <div class="college-stat-lbl">Admins</div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- RECENT ANNOUNCEMENTS -->
                    <div class="card">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <div class="card-title">Recent Announcements</div>
                            <a href="<?php echo $bp; ?>/pages/announcements/view.php" class="link-sm">View All &rarr;</a>
                        </div>
                        <?php if (empty($recentAnn)): ?>
                            <div class="empty-state">No announcements yet.</div>
                        <?php else: ?>
                            <?php foreach ($recentAnn as $ann): ?>
                            <div class="ann-item">
                                <div class="ann-dot" style="background:<?php echo $ann['college_color'] ?? '#3b82f6'; ?>;"></div>
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <?php if ($ann['is_pinned']): ?><span style="color:#f59e0b;font-size:0.65rem;">&#128204;</span><?php endif; ?>
                                        <a href="<?php echo $bp; ?>/pages/announcements/view.php?id=<?php echo $ann['id']; ?>" class="ann-title"><?php echo htmlspecialchars($ann['title']); ?></a>
                                    </div>
                                    <div class="ann-meta">
                                        <?php echo htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']); ?>
                                        &middot; <?php echo timeAgo($ann['created_at']); ?>
                                        <?php if (!empty($ann['college_abbr'])): ?> &middot; <?php echo htmlspecialchars($ann['college_abbr']); ?><?php endif; ?>
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

                <!-- RIGHT -->
                <div>
                    <!-- PENDING ACCOUNTS -->
                    <div class="card" style="margin-bottom:24px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <div class="card-title">Pending <span style="color:#f59e0b;font-size:0.8rem;font-family:'JetBrains Mono',monospace;">(<?php echo $pendingCount; ?>)</span></div>
                            <a href="<?php echo $bp; ?>/pages/accounts/pending.php" class="link-sm">View All &rarr;</a>
                        </div>
                        <?php if (empty($pendingUsers)): ?>
                            <div class="empty-state">No pending accounts.</div>
                        <?php else: ?>
                            <?php foreach ($pendingUsers as $pu): ?>
                            <div class="pending-row">
                                <div class="pending-avatar" style="background:#3b82f6;"><?php echo strtoupper(substr($pu['first_name'],0,1) . substr($pu['last_name'],0,1)); ?></div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:0.82rem;font-weight:600;"><?php echo htmlspecialchars($pu['first_name'] . ' ' . $pu['last_name']); ?></div>
                                    <div style="font-size:0.72rem;color:#64748b;"><?php echo htmlspecialchars($pu['email']); ?></div>
                                </div>
                                <a href="<?php echo $bp; ?>/pages/accounts/pending.php" class="link-sm">Review</a>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- RECENT ACTIVITY -->
                    <div class="card" style="margin-bottom:24px;">
                        <div class="card-title" style="margin-bottom:16px;">Recent Activity</div>
                        <?php if (empty($activities)): ?>
                            <div class="empty-state">No recent activity.</div>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;gap:10px;">
                                <?php foreach ($activities as $act): ?>
                                <div class="activity-item">
                                    <div class="activity-dot" style="background:<?php echo strpos($act['action'],'login') !== false ? '#10b981' : '#3b82f6'; ?>;"></div>
                                    <div>
                                        <span style="color:#f1f5f9;font-weight:600;"><?php echo htmlspecialchars($act['first_name'] . ' ' . $act['last_name']); ?></span>
                                        <span style="color:#64748b;"><?php echo htmlspecialchars(str_replace('_',' ',$act['action'])); ?></span>
                                        <div style="font-size:0.68rem;color:#64748b;margin-top:2px;"><?php echo timeAgo($act['created_at']); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- QUICK ACTIONS -->
                    <div class="card">
                        <div class="card-title" style="margin-bottom:16px;">Quick Actions</div>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <a href="<?php echo $bp; ?>/pages/announcements/create.php" class="btn btn-outline" style="justify-content:flex-start;">+ Create Announcement</a>
                            <a href="<?php echo $bp; ?>/pages/accounts/pending.php" class="btn btn-outline" style="justify-content:flex-start;">Approve Accounts</a>
                            <a href="<?php echo $bp; ?>/pages/accounts/manage.php" class="btn btn-outline" style="justify-content:flex-start;">Manage Accounts</a>
                            <a href="<?php echo $bp; ?>/pages/rooms/create.php" class="btn btn-outline" style="justify-content:flex-start;">Create Room</a>
                            <a href="<?php echo $bp; ?>/pages/analytics/index.php" class="btn btn-outline" style="justify-content:flex-start;">View Analytics</a>
                            <a href="<?php echo $bp; ?>/pages/settings/backup.php" class="btn btn-outline" style="justify-content:flex-start;">Backup &amp; Restore</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>