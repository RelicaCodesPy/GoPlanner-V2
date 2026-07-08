<?php
// pages/analytics/index.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin']);

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
$role = $user['role'];
$collegeId = $user['college_id'] ?? 0;

$isSuperAdmin = ($role === 'super_admin');
$collegeFilter = $isSuperAdmin ? '' : "AND u.college_id = {$collegeId}";
$annFilter = $isSuperAdmin ? '' : "AND (a.college_id = {$collegeId} OR a.college_id IS NULL)";

// Stats
$stats = ['total_users' => 0, 'active_users' => 0, 'students' => 0, 'instructors' => 0, 'admins' => 0,
          'total_ann' => 0, 'published_ann' => 0, 'total_rooms' => 0, 'total_messages' => 0, 'total_views' => 0];
try {
    $s = $db->query("SELECT COUNT(*) FROM users WHERE is_archived = 0 {$collegeFilter}");
    $stats['total_users'] = (int)$s->fetchColumn();
    $s = $db->query("SELECT COUNT(*) FROM users WHERE is_archived = 0 AND status = 'active' {$collegeFilter}");
    $stats['active_users'] = (int)$s->fetchColumn();
    $s = $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_archived = 0 {$collegeFilter}");
    $stats['students'] = (int)$s->fetchColumn();
    $s = $db->query("SELECT COUNT(*) FROM users WHERE role = 'instructor' AND is_archived = 0 {$collegeFilter}");
    $stats['instructors'] = (int)$s->fetchColumn();
    $s = $db->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin') AND is_archived = 0 {$collegeFilter}");
    $stats['admins'] = (int)$s->fetchColumn();
    $s = $db->query("SELECT COUNT(*) FROM announcements WHERE 1=1 {$annFilter}");
    $stats['total_ann'] = (int)$s->fetchColumn();
    $s = $db->query("SELECT COUNT(*) FROM announcements WHERE status = 'published' {$annFilter}");
    $stats['published_ann'] = (int)$s->fetchColumn();
    $s = $db->query("SELECT COUNT(*) FROM rooms WHERE is_archived = 0");
    $stats['total_rooms'] = (int)$s->fetchColumn();
    $s = $db->query("SELECT COUNT(*) FROM room_messages");
    $stats['total_messages'] = (int)$s->fetchColumn();
    $s = $db->query("SELECT SUM(view_count) FROM announcements");
    $stats['total_views'] = (int)($s->fetchColumn() ?: 0);
} catch (Exception $e) {}

// Announcements by month (last 6 months)
$monthlyAnn = [];
try {
    $s = $db->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
               DATE_FORMAT(created_at, '%b %Y') AS month_label,
               COUNT(*) AS cnt
        FROM announcements
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) {$annFilter}
        GROUP BY month_key, month_label
        ORDER BY month_key ASC
    ");
    $monthlyAnn = $s->fetchAll();
} catch (Exception $e) {}

// Users by college
$usersByCollege = [];
try {
    $q = $isSuperAdmin ? "SELECT c.abbreviation, c.color, COUNT(u.id) AS cnt FROM colleges c LEFT JOIN users u ON u.college_id = c.id AND u.is_archived = 0 WHERE c.is_active = 1 GROUP BY c.id ORDER BY cnt DESC"
                        : "SELECT c.abbreviation, c.color, COUNT(u.id) AS cnt FROM colleges c LEFT JOIN users u ON u.college_id = c.id AND u.is_archived = 0 WHERE c.id = {$collegeId} GROUP BY c.id";
    $s = $db->query($q);
    $usersByCollege = $s->fetchAll();
} catch (Exception $e) {}

// Activity log
$activities = [];
try {
    $limit = $isSuperAdmin ? "LIMIT 15" : "AND u.college_id = {$collegeId} LIMIT 15";
    $s = $db->query("
        SELECT al.action, al.created_at, u.first_name, u.last_name
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE 1=1 {$limit}
        ORDER BY al.created_at DESC
    ");
    $activities = $s->fetchAll();
} catch (Exception $e) {}

$unreadNotifs = 0;
try {
    $s = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $s->execute([$user['id']]);
    $unreadNotifs = (int)$s->fetchColumn();
} catch (Exception $e) {}

// Max for bar chart scaling
$maxMonthly = max(array_column($monthlyAnn, 'cnt') ?: [1]);
$maxCollege = max(array_column($usersByCollege, 'cnt') ?: [1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — GoPlanner</title>
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

    .page-header{margin-bottom:28px;animation:fadeUp 0.5s ease}
    .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .page-header p{font-size:0.88rem;color:#64748b}

    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px}
    .stat-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;transition:all 0.25s;opacity:0;animation:fadeUp 0.5s ease forwards}
    .stat-card:hover{border-color:#475569;transform:translateY(-2px)}
    .stat-val{font-size:1.5rem;font-weight:800;color:#f1f5f9;font-family:'JetBrains Mono',monospace;line-height:1;margin-bottom:4px}
    .stat-lbl{font-size:0.75rem;color:#64748b;font-weight:500}

    .card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:24px;opacity:0;animation:fadeUp 0.5s ease forwards;animation-delay:0.2s}
    .card-title{font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:20px}

    .bar-chart{display:flex;align-items:flex-end;gap:8px;height:160px;padding:0 4px}
    .bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;min-width:0}
    .bar{width:100%;border-radius:6px 6px 0 0;transition:height 0.5s ease;min-height:4px}
    .bar-val{font-size:0.72rem;font-weight:700;color:#f1f5f9;font-family:'JetBrains Mono',monospace}
    .bar-label{font-size:0.62rem;color:#64748b;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}

    .horizontal-bar{display:flex;align-items:center;gap:12px;margin-bottom:12px}
    .hbar-label{width:60px;font-size:0.78rem;font-weight:600;text-align:right;flex-shrink:0}
    .hbar-track{flex:1;height:28px;background:#0f172a;border-radius:6px;overflow:hidden;position:relative}
    .hbar-fill{height:100%;border-radius:6px;transition:width 0.5s ease;display:flex;align-items:center;padding-left:10px;font-size:0.72rem;font-weight:700;color:white;min-width:32px}
    .hbar-count{font-size:0.78rem;font-weight:600;width:40px;text-align:left;flex-shrink:0;font-family:'JetBrains Mono',monospace;color:#94a3b8}

    .activity-item{display:flex;gap:10px;font-size:0.78rem;padding:8px 0;border-bottom:1px solid rgba(30,41,59,0.5)}
    .activity-item:last-child{border-bottom:none}
    .activity-dot{width:6px;height:6px;border-radius:3px;margin-top:6px;flex-shrink:0}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}.stats-grid{grid-template-columns:repeat(2,1fr)}.two-col{grid-template-columns:1fr!important}}
    @media(max-width:480px){.topbar-username,.topbar-date{display:none}.stats-grid{grid-template-columns:1fr}}
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
                <div><h1>Analytics</h1><p><?php echo $isSuperAdmin ? 'System-wide performance overview' : 'College performance overview'; ?></p></div>
            </div>

            <div class="stats-grid">
                <?php
                $statCards = [
                    ['val' => $stats['total_users'], 'lbl' => 'Total Users', 'color' => '#3b82f6'],
                    ['val' => $stats['active_users'], 'lbl' => 'Active Users', 'color' => '#10b981'],
                    ['val' => $stats['students'], 'lbl' => 'Students', 'color' => '#60a5fa'],
                    ['val' => $stats['instructors'], 'lbl' => 'Instructors', 'color' => '#34d399'],
                    ['val' => $stats['published_ann'], 'lbl' => 'Published', 'color' => '#8b5cf6'],
                    ['val' => $stats['total_views'], 'lbl' => 'Total Views', 'color' => '#f59e0b'],
                    ['val' => $stats['total_rooms'], 'lbl' => 'Rooms', 'color' => '#ec4899'],
                    ['val' => $stats['total_messages'], 'lbl' => 'Messages', 'color' => '#14b8a6'],
                ];
                foreach ($statCards as $i => $sc): ?>
                <div class="stat-card" style="animation-delay:<?php echo 0.04 * $i; ?>s;">
                    <div class="stat-val" style="color:<?php echo $sc['color']; ?>;"><?php echo number_format($sc['val']); ?></div>
                    <div class="stat-lbl"><?php echo $sc['lbl']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="two-col" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
                <div>
                    <!-- MONTHLY ANNOUNCEMENTS -->
                    <div class="card" style="margin-bottom:24px;">
                        <div class="card-title">Announcements by Month</div>
                        <?php if (empty($monthlyAnn)): ?>
                            <p style="color:#64748b;font-size:0.85rem;">No data yet.</p>
                        <?php else: ?>
                            <div class="bar-chart">
                                <?php foreach ($monthlyAnn as $m): ?>
                                <div class="bar-col">
                                    <div class="bar-val"><?php echo $m['cnt']; ?></div>
                                    <div class="bar" style="height:<?php echo max(4, ($m['cnt'] / $maxMonthly) * 120); ?>px;background:#3b82f6;"></div>
                                    <div class="bar-label"><?php echo $m['month_label']; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- USERS BY COLLEGE -->
                    <div class="card">
                        <div class="card-title">Users by College</div>
                        <?php if (empty($usersByCollege)): ?>
                            <p style="color:#64748b;font-size:0.85rem;">No data yet.</p>
                        <?php else: ?>
                            <?php foreach ($usersByCollege as $c): ?>
                            <div class="horizontal-bar">
                                <div class="hbar-label"><?php echo htmlspecialchars($c['abbreviation']); ?></div>
                                <div class="hbar-track">
                                    <div class="hbar-fill" style="width:<?php echo max(5, ($c['cnt'] / $maxCollege) * 100); ?>%;background:<?php echo $c['color']; ?>;">
                                        <?php if ($c['cnt'] > 0) echo $c['cnt']; ?>
                                    </div>
                                </div>
                                <div class="hbar-count"><?php echo $c['cnt']; ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <div class="card">
                        <div class="card-title">Recent Activity</div>
                        <?php if (empty($activities)): ?>
                            <p style="color:#64748b;font-size:0.85rem;">No recent activity.</p>
                        <?php else: ?>
                            <?php foreach ($activities as $act): ?>
                            <div class="activity-item">
                                <div class="activity-dot" style="background:<?php echo strpos($act['action'],'login') !== false ? '#10b981' : '#3b82f6'; ?>;"></div>
                                <div>
                                    <span style="color:#f1f5f9;font-weight:600;"><?php echo htmlspecialchars($act['first_name'] . ' ' . $act['last_name']); ?></span>
                                    <span style="color:#64748b;"><?php echo htmlspecialchars(str_replace('_', ' ', $act['action'])); ?></span>
                                    <div style="font-size:0.68rem;color:#475569;margin-top:2px;"><?php echo timeAgo($act['created_at']); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>