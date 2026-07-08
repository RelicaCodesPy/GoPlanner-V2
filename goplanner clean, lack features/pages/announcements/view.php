<?php
// pages/announcements/view.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
$role = $user['role'] ?? 'student';
$collegeId = $user['college_id'] ?? 0;

// Filters
$search = trim($_GET['q'] ?? '');
$priorityFilter = $_GET['priority'] ?? '';
$collegeFilter = $_GET['college'] ?? '';
$viewMode = $_GET['view'] ?? 'all'; // all, my, college

// Single announcement view
$singleAnn = null;
$singleId = (int)($_GET['id'] ?? 0);

if ($singleId > 0) {
    try {
        $stmt = $db->prepare("
            SELECT a.*, u.first_name, u.last_name, u.email AS author_email,
                   c.name AS college_name, c.abbreviation AS college_abbr, c.color AS college_color,
                   p.name AS program_name, p.code AS program_code
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN colleges c ON a.college_id = c.id
            LEFT JOIN programs p ON a.program_id = p.id
            WHERE a.id = ?
        ");
        $stmt->execute([$singleId]);
        $singleAnn = $stmt->fetch();

        // Increment view count
        if ($singleAnn) {
            try {
                $db->prepare("UPDATE announcements SET view_count = view_count + 1 WHERE id = ?")->execute([$singleId]);
            } catch (Exception $e) {}
        }
    } catch (Exception $e) {}

    if (!$singleAnn) {
        setFlash('error', 'Announcement not found.');
        redirect('/pages/announcements/view.php');
    }

    // Fetch files
    $annFiles = [];
    try {
        $stmt = $db->prepare("SELECT * FROM announcement_files WHERE announcement_id = ? ORDER BY created_at ASC");
        $stmt->execute([$singleId]);
        $annFiles = $stmt->fetchAll();
    } catch (Exception $e) {}
}

// Build list query
$allowedToCreate = in_array($role, ['super_admin', 'admin', 'instructor']);

if (!$singleAnn) {
    $where = ["a.status = 'published'"];
    $params = [];

    // Role-based filtering
    if ($role === 'student') {
        $where[] = "(a.college_id = ? OR a.college_id IS NULL)";
        $params[] = $collegeId;
    } elseif ($role === 'instructor') {
        $where[] = "(a.college_id = ? OR a.college_id IS NULL)";
        $params[] = $collegeId;
    }

    if ($search) {
        $where[] = "(a.title LIKE ? OR a.content LIKE ?)";
        $s = "%{$search}%";
        $params = array_merge($params, [$s, $s]);
    }
    if ($priorityFilter && in_array($priorityFilter, ['low','normal','high','urgent'])) {
        $where[] = "a.priority = ?";
        $params[] = $priorityFilter;
    }
    if ($collegeFilter) {
        $where[] = "a.college_id = ?";
        $params[] = (int)$collegeFilter;
    }
    if ($viewMode === 'my' && $allowedToCreate) {
        $where[] = "a.created_by = ?";
        $params[] = $user['id'];
    }

    $whereSQL = implode(' AND ', $where);

    $announcements = [];
    try {
        $stmt = $db->prepare("
            SELECT a.id, a.title, a.priority, a.status, a.is_pinned, a.event_date, a.event_time,
                   a.map_address, a.view_count, a.created_at,
                   u.first_name, u.last_name,
                   c.name AS college_name, c.abbreviation AS college_abbr, c.color AS college_color
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN colleges c ON a.college_id = c.id
            WHERE {$whereSQL}
            ORDER BY a.is_pinned DESC, a.created_at DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        $announcements = $stmt->fetchAll();
    } catch (Exception $e) {}

    $colleges = [];
    try {
        $colleges = $db->query("SELECT id, abbreviation, name, color FROM colleges WHERE is_active = 1 ORDER BY name")->fetchAll();
    } catch (Exception $e) {}
}

$unreadNotifs = 0;
try {
    $s = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $s->execute([$user['id']]);
    $unreadNotifs = (int)$s->fetchColumn();
} catch (Exception $e) {}

$flash = getFlash();
$priorityColors = ['low' => '#64748b', 'normal' => '#3b82f6', 'high' => '#f59e0b', 'urgent' => '#ef4444'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $singleAnn ? htmlspecialchars($singleAnn['title']) . ' — ' : ''; ?>Announcements — GoPlanner</title>
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
    .btn-outline{background:transparent;border:1.5px solid #334155;color:#cbd5e1}
    .btn-outline:hover{border-color:#3b82f6;color:#3b82f6}
    .btn-sm{padding:6px 14px;font-size:0.78rem;border-radius:6px}

    .flash-message{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;font-weight:500;animation:fadeUp 0.3s ease}
    .flash-success{background:rgba(16,185,129,0.1);color:#6ee7b7;border-left:4px solid #10b981}
    .flash-error{background:rgba(239,68,68,0.1);color:#fca5a5;border-left:4px solid #ef4444}

    /* FILTERS */
    .filters{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;animation:fadeUp 0.5s ease;animation-delay:0.05s;opacity:0;animation-fill-mode:forwards}
    .filter-input{padding:8px 14px;background:#0f172a;border:1.5px solid #334155;border-radius:8px;color:#f1f5f9;font-size:0.85rem;font-family:'Inter',sans-serif;outline:none;transition:all 0.2s}
    .filter-input:focus{border-color:#3b82f6}
    .filter-input::placeholder{color:#475569}
    select.filter-input{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px}
    select.filter-input option{background:#1e293b;color:#f1f5f9}
    .filter-btn{padding:8px 18px;background:#3b82f6;color:white;border:none;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;transition:all 0.2s}
    .filter-btn:hover{background:#2563eb}
    .filter-count{font-size:0.82rem;color:#64748b;margin-left:auto;font-weight:500}
    .view-tabs{display:flex;gap:4px;background:#0f172a;border-radius:8px;padding:3px}
    .view-tab{padding:6px 14px;border-radius:6px;font-size:0.78rem;font-weight:600;color:#64748b;cursor:pointer;border:none;background:transparent;font-family:'Inter',sans-serif;transition:all 0.2s;text-decoration:none}
    .view-tab.active{background:#1e293b;color:#f1f5f9}
    .view-tab:hover:not(.active){color:#94a3b8}

    /* ANNOUNCEMENT CARDS */
    .ann-grid{display:flex;flex-direction:column;gap:12px}
    .ann-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;transition:all 0.25s ease;cursor:pointer;opacity:0;animation:fadeUp 0.5s ease forwards;text-decoration:none;color:inherit;display:block}
    .ann-card:hover{border-color:#475569;transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.2)}
    .ann-card-top{display:flex;align-items:flex-start;gap:12px;margin-bottom:10px}
    .ann-priority-dot{width:8px;height:8px;border-radius:4px;flex-shrink:0;margin-top:6px}
    .ann-card-title{font-size:1rem;font-weight:700;color:#f1f5f9;line-height:1.4}
    .ann-card-title:hover{color:#3b82f6}
    .ann-card-meta{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:0.75rem;color:#64748b;margin-bottom:8px}
    .ann-card-content{font-size:0.85rem;color:#94a3b8;line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .ann-card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid rgba(30,41,59,0.5)}
    .ann-card-footer-left{display:flex;align-items:center;gap:8px}
    .ann-card-footer-right{display:flex;align-items:center;gap:12px}
    .pin-icon{color:#f59e0b;font-size:0.7rem}
    .badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600}
    .stat-sm{font-size:0.72rem;color:#475569;display:flex;align-items:center;gap:4px}

    /* SINGLE VIEW */
    .ann-single{animation:fadeUp 0.5s ease}
    .ann-single-header{margin-bottom:24px}
    .ann-single-breadcrumb{font-size:0.78rem;color:#64748b;margin-bottom:16px;display:flex;align-items:center;gap:6px}
    .ann-single-breadcrumb a{color:#3b82f6;text-decoration:none}
    .ann-single-breadcrumb a:hover{text-decoration:underline}
    .ann-single-title{font-size:1.8rem;font-weight:800;letter-spacing:-0.5px;line-height:1.3;margin-bottom:12px}
    .ann-single-meta{display:flex;flex-wrap:wrap;align-items:center;gap:12px;font-size:0.82rem;color:#64748b;margin-bottom:8px}
    .ann-single-author{display:flex;align-items:center;gap:10px;margin-bottom:24px}
    .ann-single-avatar{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.75rem;font-weight:700;flex-shrink:0}
    .ann-single-body{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;margin-bottom:24px;line-height:1.8;font-size:0.95rem}
    .ann-single-body p{margin-bottom:16px}
    .ann-single-body p:last-child{margin-bottom:0}
    .ann-single-event{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;margin-bottom:24px;display:flex;align-items:center;gap:16px}
    .event-date-box{width:56px;height:56px;border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0}
    .event-day{font-size:1.3rem;font-weight:800;line-height:1;color:white}
    .event-month{font-size:0.65rem;font-weight:600;text-transform:uppercase;color:rgba(255,255,255,0.8)}
    .ann-single-files{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;margin-bottom:24px}
    .file-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(30,41,59,0.5)}
    .file-item:last-child{border-bottom:none}
    .file-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}

    .empty-state{text-align:center;padding:60px 20px;animation:fadeUp 0.5s ease}
    .empty-icon{width:80px;height:80px;margin:0 auto 20px;background:rgba(59,130,246,0.08);border-radius:20px;display:flex;align-items:center;justify-content:center}
    .empty-title{font-size:1.1rem;font-weight:700;color:#f1f5f9;margin-bottom:6px}
    .empty-desc{font-size:0.88rem;color:#64748b}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}.ann-single-title{font-size:1.4rem}.ann-single-body{padding:20px}}
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
            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <?php if ($singleAnn): ?>
            <!-- SINGLE ANNOUNCEMENT VIEW -->
            <div class="ann-single">
                <div class="ann-single-breadcrumb">
                    <a href="<?php echo $bp; ?>/pages/announcements/view.php">Announcements</a>
                    <span>&rsaquo;</span>
                    <span><?php echo htmlspecialchars($singleAnn['title']); ?></span>
                </div>

                <div class="ann-single-header">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <?php if ($singleAnn['is_pinned']): ?><span class="pin-icon" style="font-size:0.85rem;">&#128204;</span><?php endif; ?>
                        <?php if ($singleAnn['priority'] !== 'normal'): ?>
                            <span class="badge" style="background:<?php echo $priorityColors[$singleAnn['priority']]; ?>20;color:<?php echo $priorityColors[$singleAnn['priority']]; ?>;"><?php echo ucfirst($singleAnn['priority']); ?></span>
                        <?php endif; ?>
                        <?php if ($singleAnn['college_abbr']): ?>
                            <span class="badge" style="background:<?php echo $singleAnn['college_color']; ?>20;color:<?php echo $singleAnn['college_color']; ?>;"><?php echo htmlspecialchars($singleAnn['college_abbr']); ?></span>
                        <?php endif; ?>
                    </div>
                    <h1 class="ann-single-title"><?php echo htmlspecialchars($singleAnn['title']); ?></h1>

                    <div class="ann-single-author">
                        <div class="ann-single-avatar" style="background:<?php echo $singleAnn['college_color'] ?? '#3b82f6'; ?>;">
                            <?php echo strtoupper(substr($singleAnn['first_name'],0,1) . substr($singleAnn['last_name'],0,1)); ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.88rem;"><?php echo htmlspecialchars($singleAnn['first_name'] . ' ' . $singleAnn['last_name']); ?></div>
                            <div style="font-size:0.78rem;color:#64748b;"><?php echo htmlspecialchars($singleAnn['author_email']); ?> &middot; <?php echo timeAgo($singleAnn['created_at']); ?></div>
                        </div>
                    </div>
                </div>

                <?php if ($singleAnn['event_date']): ?>
                <div class="ann-single-event">
                    <div class="event-date-box" style="background:<?php echo $singleAnn['college_color'] ?? '#3b82f6'; ?>;">
                        <div class="event-day"><?php echo date('d', strtotime($singleAnn['event_date'])); ?></div>
                        <div class="event-month"><?php echo date('M', strtotime($singleAnn['event_date'])); ?></div>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:0.95rem;"><?php echo date('l, F d, Y', strtotime($singleAnn['event_date'])); ?></div>
                        <div style="font-size:0.82rem;color:#64748b;">
                            <?php if ($singleAnn['event_time']): echo date('g:i A', strtotime($singleAnn['event_time'])); endif; ?>
                            <?php if ($singleAnn['event_end_date']): ?> — <?php echo date('M d, Y', strtotime($singleAnn['event_end_date'])); ?><?php endif; ?>
                            <?php if ($singleAnn['map_address']): ?> &middot; <?php echo htmlspecialchars($singleAnn['map_address']); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="ann-single-body">
                    <?php echo nl2br(htmlspecialchars($singleAnn['content'])); ?>
                </div>

                <?php if (!empty($annFiles)): ?>
                <div class="ann-single-files">
                    <div class="card-title" style="margin-bottom:12px;">Attachments (<?php echo count($annFiles); ?>)</div>
                    <?php foreach ($annFiles as $f): ?>
                    <div class="file-item">
                        <div class="file-icon" style="background:rgba(59,130,246,0.12);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:0.85rem;font-weight:600;"><?php echo htmlspecialchars($f['original_name'] ?? $f['file_name'] ?? 'File'); ?></div>
                            <div style="font-size:0.72rem;color:#64748b;"><?php echo isset($f['file_size']) ? number_format($f['file_size'] / 1024, 1) . ' KB' : ''; ?></div>
                        </div>
                        <a href="<?php echo $bp; ?>/uploads/announcements/<?php echo htmlspecialchars($f['file_name'] ?? ''); ?>" class="btn btn-outline btn-sm" download>Download</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:12px;">
                    <a href="<?php echo $bp; ?>/pages/announcements/view.php" class="btn btn-outline">Back to Announcements</a>
                    <?php if ($singleAnn['created_by'] == $user['id'] || in_array($role, ['super_admin'])): ?>
                        <a href="<?php echo $bp; ?>/pages/announcements/create.php?edit=<?php echo $singleAnn['id']; ?>" class="btn btn-outline">Edit</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <!-- LIST VIEW -->
            <div class="page-header">
                <div><h1>Announcements</h1><p>Stay updated with the latest news and events</p></div>
                <?php if ($allowedToCreate): ?>
                    <a href="<?php echo $bp; ?>/pages/announcements/create.php" class="btn btn-primary">+ New Announcement</a>
                <?php endif; ?>
            </div>

            <form class="filters" method="GET">
                <input type="text" name="q" class="filter-input" placeholder="Search announcements..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;min-width:200px;">
                <select name="priority" class="filter-input">
                    <option value="">All Priorities</option>
                    <option value="low" <?php echo $priorityFilter==='low'?'selected':''; ?>>Low</option>
                    <option value="normal" <?php echo $priorityFilter==='normal'?'selected':''; ?>>Normal</option>
                    <option value="high" <?php echo $priorityFilter==='high'?'selected':''; ?>>High</option>
                    <option value="urgent" <?php echo $priorityFilter==='urgent'?'selected':''; ?>>Urgent</option>
                </select>
                <?php if (in_array($role, ['super_admin'])): ?>
                <select name="college" class="filter-input">
                    <option value="">All Colleges</option>
                    <?php foreach ($colleges as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $collegeFilter==$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['abbreviation']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <?php if ($allowedToCreate): ?>
                <div class="view-tabs">
                    <a href="?view=all" class="view-tab <?php echo $viewMode==='all'?'active':''; ?>">All</a>
                    <a href="?view=my" class="view-tab <?php echo $viewMode==='my'?'active':''; ?>">Mine</a>
                </div>
                <?php endif; ?>
                <button type="submit" class="filter-btn">Filter</button>
                <span class="filter-count"><?php echo count($announcements); ?> result<?php echo count($announcements)!==1?'s':''; ?></span>
            </form>

            <?php if (empty($announcements)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                    </div>
                    <div class="empty-title">No announcements found</div>
                    <div class="empty-desc">There are no announcements matching your criteria.</div>
                </div>
            <?php else: ?>
                <div class="ann-grid">
                    <?php foreach ($announcements as $i => $ann): ?>
                    <a href="?id=<?php echo $ann['id']; ?>" class="ann-card" style="animation-delay:<?php echo 0.05 * $i; ?>s;">
                        <div class="ann-card-top">
                            <div class="ann-priority-dot" style="background:<?php echo $priorityColors[$ann['priority']]; ?>;"></div>
                            <div style="flex:1;min-width:0;">
                                <div class="ann-card-title">
                                    <?php if ($ann['is_pinned']): ?><span class="pin-icon">&#128204;</span> <?php endif; ?>
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="ann-card-meta">
                            <span><?php echo htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']); ?></span>
                            <span>&middot;</span>
                            <span><?php echo timeAgo($ann['created_at']); ?></span>
                            <?php if ($ann['college_abbr']): ?>
                                <span>&middot;</span>
                                <span class="badge" style="background:<?php echo $ann['college_color']; ?>20;color:<?php echo $ann['college_color']; ?>;"><?php echo htmlspecialchars($ann['college_abbr']); ?></span>
                            <?php endif; ?>
                            <?php if ($ann['priority'] !== 'normal'): ?>
                                <span class="badge" style="background:<?php echo $priorityColors[$ann['priority']]; ?>20;color:<?php echo $priorityColors[$ann['priority']]; ?>;"><?php echo ucfirst($ann['priority']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="ann-card-footer">
                            <div class="ann-card-footer-left">
                                <?php if ($ann['event_date']): ?>
                                    <span class="stat-sm">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                                        <?php echo date('M d', strtotime($ann['event_date'])); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($ann['map_address']): ?>
                                    <span class="stat-sm">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        <?php echo htmlspecialchars($ann['map_address']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="ann-card-footer-right">
                                <span class="stat-sm">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <?php echo $ann['view_count']; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>