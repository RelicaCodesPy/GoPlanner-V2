<?php
// pages/about.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About — GoPlanner</title>
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

    .content{flex:1;padding:32px;display:flex;align-items:center;justify-content:center}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

    .about-card{max-width:560px;width:100%;text-align:center;animation:fadeUp 0.6s ease}
    .about-logo{width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#10b981);display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;color:white;box-shadow:0 8px 32px rgba(59,130,246,0.3);margin-bottom:24px;overflow:hidden}
    .about-logo img{width:100%;height:100%;object-fit:contain;border-radius:20px}
    .about-title{font-size:2rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .about-version{font-size:0.85rem;color:#3b82f6;font-weight:600;margin-bottom:8px;font-family:'JetBrains Mono',monospace}
    .about-subtitle{font-size:0.92rem;color:#64748b;margin-bottom:32px}

    .about-card-inner{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:28px;text-align:left;margin-bottom:20px}
    .about-card-inner h3{font-size:0.92rem;font-weight:700;margin-bottom:12px;color:#f1f5f9}
    .about-card-inner p{font-size:0.85rem;color:#94a3b8;line-height:1.7;margin-bottom:12px}
    .about-card-inner p:last-child{margin-bottom:0}

    .about-credits{font-size:0.82rem;color:#64748b;line-height:1.8}
    .about-credits strong{color:#cbd5e1}

    .about-footer{margin-top:32px;padding-top:20px;border-top:1px solid #1e293b;font-size:0.75rem;color:#475569}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}}
    @media(max-width:480px){.topbar-username,.topbar-date{display:none}.about-title{font-size:1.5rem}}
    </style>
</head>
<body>
<div class="page-wrapper">
    <?php require_once __DIR__ . '/../components/sidebar.php'; ?>

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
            <div class="about-card">
                <div class="about-logo">
                    <?php $logoFile = __DIR__ . '/../assets/images/logo/goplanner-logo.png'; ?>
                    <?php if (file_exists($logoFile)): ?>
                        <img src="<?php echo $bp; ?>/assets/images/logo/goplanner-logo.png" alt="GoPlanner">
                    <?php else: ?>
                        GP
                    <?php endif; ?>
                </div>
                <h1 class="about-title">GoPlanner</h1>
                <div class="about-version">v<?php echo APP_VERSION; ?>.0</div>
                <p class="about-subtitle">Academic Announcement & Planning System</p>

                <div class="about-card-inner">
                    <h3>About GoPlanner</h3>
                    <p>GoPlanner is a centralized academic announcement and planning system built for Dr. Emilio B. Espinosa Sr. Memorial State College of Agriculture and Technology (DEBESMSCAT).</p>
                    <p>It provides a unified platform for administrators, instructors, and students to manage announcements, collaborate in rooms, track academic events, and stay connected across all six colleges.</p>
                </div>

                <div class="about-card-inner">
                    <h3>Developers</h3>
                    <p class="about-credits">
                        <strong>Relica Malinao</strong> — Lead Developer<br>
                        <strong>Maria Bea Belasa</strong> — Co-Developer
                    </p>
                </div>

                <div class="about-card-inner">
                    <h3>Colleges</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <?php
                        $collegeList = [
                            ['abbr' => 'CAAf', 'name' => 'College of Agriculture', 'color' => '#166534'],
                            ['abbr' => 'CCIT', 'name' => 'College of Computing & IT', 'color' => '#0D9488'],
                            ['abbr' => 'CBTE', 'name' => 'College of Business, Tourism & Economics', 'color' => '#DC2626'],
                            ['abbr' => 'CEng', 'name' => 'College of Engineering', 'color' => '#800020'],
                            ['abbr' => 'CE', 'name' => 'College of Education', 'color' => '#1E3A8A'],
                            ['abbr' => 'CIT', 'name' => 'College of Industrial Technology', 'color' => '#4B5563'],
                        ];
                        foreach ($collegeList as $c): ?>
                        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;">
                            <div style="width:10px;height:10px;border-radius:3px;background:<?php echo $c['color']; ?>;flex-shrink:0;"></div>
                            <span style="font-size:0.82rem;"><?php echo htmlspecialchars($c['abbr']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="about-footer">
                    &copy; 2026 DEBESMSCAT &middot; All rights reserved
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>