<?php
// pages/calendar/index.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
$collegeId = $user['college_id'] ?? 0;

// Get month/year from query or default to current
$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$startDay = date('w', $firstDay); // 0=Sun
$monthName = date('F Y', $firstDay);

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Fetch events for this month
$events = [];
try {
    $stmt = $db->prepare("
        SELECT a.id, a.title, a.event_date, a.event_time, a.map_address, a.priority,
               c.abbreviation AS college_abbr, c.color AS college_color
        FROM announcements a
        LEFT JOIN colleges c ON a.college_id = c.id
        WHERE a.status = 'published'
          AND a.event_date IS NOT NULL
          AND MONTH(a.event_date) = ? AND YEAR(a.event_date) = ?
          AND (a.college_id = ? OR a.college_id IS NULL OR ? IN ('super_admin'))
        ORDER BY a.event_date ASC, a.event_time ASC
    ");
    $stmt->execute([$month, $year, $collegeId, $user['role']]);
    $events = $stmt->fetchAll();
} catch (Exception $e) {}

// Group events by day
$eventsByDay = [];
foreach ($events as $ev) {
    $day = (int)date('j', strtotime($ev['event_date']));
    $eventsByDay[$day][] = $ev;
}

// Upcoming events (from today)
$upcoming = [];
try {
    $stmt = $db->prepare("
        SELECT a.id, a.title, a.event_date, a.event_time, a.map_address, a.priority,
               c.abbreviation AS college_abbr, c.color AS college_color
        FROM announcements a
        LEFT JOIN colleges c ON a.college_id = c.id
        WHERE a.status = 'published'
          AND a.event_date >= CURDATE()
          AND (a.college_id = ? OR a.college_id IS NULL OR ? IN ('super_admin'))
        ORDER BY a.event_date ASC, a.event_time ASC
        LIMIT 10
    ");
    $stmt->execute([$collegeId, $user['role']]);
    $upcoming = $stmt->fetchAll();
} catch (Exception $e) {}

$unreadNotifs = 0;
try {
    $s = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $s->execute([$user['id']]);
    $unreadNotifs = (int)$s->fetchColumn();
} catch (Exception $e) {}

$today = date('Y-m-d');
$priorityColors = ['low' => '#64748b', 'normal' => '#3b82f6', 'high' => '#f59e0b', 'urgent' => '#ef4444'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar — GoPlanner</title>
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

    .cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp 0.5s ease;animation-delay:0.05s;opacity:0;animation-fill-mode:forwards}
    .cal-nav-btn{background:#1e293b;border:1px solid #334155;color:#f1f5f9;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.2s;font-size:1.1rem}
    .cal-nav-btn:hover{border-color:#3b82f6;color:#3b82f6}
    .cal-month{font-size:1.2rem;font-weight:700}
    .cal-today-btn{background:transparent;border:1.5px solid #334155;color:#cbd5e1;padding:6px 14px;border-radius:8px;font-size:0.78rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;transition:all 0.2s}
    .cal-today-btn:hover{border-color:#3b82f6;color:#3b82f6}

    .calendar{background:#1e293b;border:1px solid #334155;border-radius:12px;overflow:hidden;animation:fadeUp 0.5s ease;animation-delay:0.1s;opacity:0;animation-fill-mode:forwards}
    .cal-header{display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1px solid #334155}
    .cal-header-cell{padding:12px 8px;text-align:center;font-size:0.72rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px}
    .cal-body{display:grid;grid-template-columns:repeat(7,1fr)}
    .cal-cell{min-height:100px;padding:8px;border-right:1px solid rgba(30,41,59,0.5);border-bottom:1px solid rgba(30,41,59,0.5);transition:background 0.15s;position:relative}
    .cal-cell:nth-child(7n){border-right:none}
    .cal-cell:hover{background:rgba(59,130,246,0.03)}
    .cal-cell.today{background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.2)}
    .cal-cell.other-month{opacity:0.3}
    .cal-day-num{font-size:0.82rem;font-weight:600;color:#94a3b8;margin-bottom:4px}
    .cal-cell.today .cal-day-num{color:#3b82f6;font-weight:800}
    .cal-event{display:block;padding:2px 6px;margin-bottom:2px;border-radius:4px;font-size:0.65rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-decoration:none;color:white;transition:opacity 0.2s}
    .cal-event:hover{opacity:0.85}
    .cal-more{font-size:0.62rem;color:#3b82f6;cursor:pointer;font-weight:600}

    .two-col{display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-top:24px}
    .card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:24px;opacity:0;animation:fadeUp 0.5s ease forwards;animation-delay:0.2s}
    .card-title{font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:16px}

    .event-item{display:flex;align-items:center;gap:12px;padding:10px 12px;background:#0f172a;border-radius:10px;margin-bottom:8px;border:1px solid transparent;transition:all 0.2s;text-decoration:none;color:inherit}
    .event-item:hover{border-color:#334155}
    .event-date-box{width:44px;height:44px;border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0}
    .event-day{font-size:1rem;font-weight:800;line-height:1;color:white}
    .event-month{font-size:0.55rem;font-weight:600;text-transform:uppercase;color:rgba(255,255,255,0.8)}
    .event-title{font-size:0.82rem;font-weight:600;color:#f1f5f9}
    .event-time{font-size:0.72rem;color:#64748b}

    .legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:16px}
    .legend-item{display:flex;align-items:center;gap:6px;font-size:0.72rem;color:#64748b}
    .legend-dot{width:8px;height:8px;border-radius:4px}

    .empty-state{text-align:center;padding:32px;color:#64748b;font-size:0.85rem}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}.two-col{grid-template-columns:1fr}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}.cal-cell{min-height:70px;padding:4px}.cal-event{font-size:0.58rem;padding:1px 4px}}
    @media(max-width:480px){.topbar-username,.topbar-date{display:none}.cal-header-cell{font-size:0.6rem;padding:8px 2px}}
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
                <div><h1>Calendar</h1><p>View scheduled events and announcements</p></div>
            </div>

            <div class="cal-nav">
                <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="cal-nav-btn">&lsaquo;</a>
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="cal-month"><?php echo $monthName; ?></span>
                    <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="cal-today-btn">Today</a>
                </div>
                <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="cal-nav-btn">&rsaquo;</a>
            </div>

            <div class="calendar">
                <div class="cal-header">
                    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                        <div class="cal-header-cell"><?php echo $d; ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="cal-body">
                    <?php
                    // Empty cells before first day
                    for ($i = 0; $i < $startDay; $i++):
                        $prevDay = $daysInMonth - $startDay + $i + 1;
                    ?>
                        <div class="cal-cell other-month">
                            <div class="cal-day-num"><?php echo $prevDay; ?></div>
                        </div>
                    <?php endfor; ?>

                    <?php for ($day = 1; $day <= $daysInMonth; $day++):
                        $dateStr = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $isToday = $dateStr === $today;
                        $dayEvents = $eventsByDay[$day] ?? [];
                    ?>
                        <div class="cal-cell <?php echo $isToday ? 'today' : ''; ?>">
                            <div class="cal-day-num"><?php echo $day; ?></div>
                            <?php
                            $maxShow = 2;
                            $shown = 0;
                            foreach ($dayEvents as $ev):
                                if ($shown >= $maxShow) break;
                            ?>
                                <a href="<?php echo $bp; ?>/pages/announcements/view.php?id=<?php echo $ev['id']; ?>" class="cal-event" style="background:<?php echo $ev['college_color'] ?? '#3b82f6'; ?>;" title="<?php echo htmlspecialchars($ev['title']); ?>">
                                    <?php if ($ev['event_time']): echo date('g:ia', strtotime($ev['event_time'])) . ' '; endif; ?>
                                    <?php echo htmlspecialchars($ev['title']); ?>
                                </a>
                            <?php
                                $shown++;
                            endforeach;
                            if (count($dayEvents) > $maxShow): ?>
                                <div class="cal-more">+<?php echo count($dayEvents) - $maxShow; ?> more</div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>

                    <?php
                    // Empty cells after last day
                    $endDay = ($startDay + $daysInMonth) % 7;
                    if ($endDay > 0):
                        for ($i = $endDay; $i < 7; $i++): ?>
                            <div class="cal-cell other-month">
                                <div class="cal-day-num"><?php echo $i - $endDay + 1; ?></div>
                            </div>
                    <?php endfor;
                    endif; ?>
                </div>
            </div>

            <div class="legend">
                <div class="legend-item"><div class="legend-dot" style="background:#ef4444;"></div> Urgent</div>
                <div class="legend-item"><div class="legend-dot" style="background:#f59e0b;"></div> High</div>
                <div class="legend-item"><div class="legend-dot" style="background:#3b82f6;"></div> Normal</div>
                <div class="legend-item"><div class="legend-dot" style="background:#64748b;"></div> Low</div>
            </div>

            <div class="two-col">
                <div class="card">
                    <div class="card-title">This Month's Events (<?php echo count($events); ?>)</div>
                    <?php if (empty($events)): ?>
                        <div class="empty-state">No events scheduled this month.</div>
                    <?php else: ?>
                        <?php foreach ($events as $ev): ?>
                        <a href="<?php echo $bp; ?>/pages/announcements/view.php?id=<?php echo $ev['id']; ?>" class="event-item">
                            <div class="event-date-box" style="background:<?php echo $ev['college_color'] ?? '#3b82f6'; ?>;">
                                <div class="event-day"><?php echo date('d', strtotime($ev['event_date'])); ?></div>
                                <div class="event-month"><?php echo date('M', strtotime($ev['event_date'])); ?></div>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div class="event-title"><?php echo htmlspecialchars($ev['title']); ?></div>
                                <div class="event-time">
                                    <?php if ($ev['event_time']): echo date('g:i A', strtotime($ev['event_time'])); endif; ?>
                                    <?php if ($ev['college_abbr']): ?> &middot; <?php echo htmlspecialchars($ev['college_abbr']); ?><?php endif; ?>
                                    <?php if ($ev['map_address']): ?> &middot; <?php echo htmlspecialchars($ev['map_address']); ?><?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-title">Upcoming Events</div>
                    <?php if (empty($upcoming)): ?>
                        <div class="empty-state">No upcoming events.</div>
                    <?php else: ?>
                        <?php foreach ($upcoming as $ev): ?>
                        <a href="<?php echo $bp; ?>/pages/announcements/view.php?id=<?php echo $ev['id']; ?>" class="event-item">
                            <div class="event-date-box" style="background:<?php echo $ev['college_color'] ?? '#3b82f6'; ?>;">
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
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>