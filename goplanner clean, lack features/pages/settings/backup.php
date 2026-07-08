<?php
// pages/settings/backup.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin']);

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));

// Existing backups
$backups = [];
$backupDir = __DIR__ . '/../../backups/';
if (!is_dir($backupDir)) { @mkdir($backupDir, 0755, true); }
$files = glob($backupDir . '*.sql');
foreach ($files as $f) {
    $backups[] = [
        'name' => basename($f),
        'size' => filesize($f),
        'date' => filemtime($f)
    ];
}
usort($backups, function($a, $b) { return $b['date'] - $a['date']; });

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
    <title>Backup & Restore — GoPlanner</title>
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

    .content{flex:1;padding:32px;max-width:700px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

    .page-header{margin-bottom:28px;animation:fadeUp 0.5s ease}
    .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .page-header p{font-size:0.88rem;color:#64748b}

    .flash-message{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;font-weight:500;animation:fadeUp 0.3s ease}
    .flash-success{background:rgba(16,185,129,0.1);color:#6ee7b7;border-left:4px solid #10b981}
    .flash-error{background:rgba(239,68,68,0.1);color:#fca5a5;border-left:4px solid #ef4444}

    .form-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:28px;margin-bottom:24px;opacity:0;animation:fadeUp 0.5s ease forwards;animation-delay:0.1s}
    .form-card-title{font-size:0.92rem;font-weight:700;color:#f1f5f9;margin-bottom:16px;display:flex;align-items:center;gap:8px}

    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:0.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.2s ease;text-decoration:none;border:none}
    .btn-primary{background:#3b82f6;color:white}
    .btn-primary:hover{background:#2563eb;transform:translateY(-1px);box-shadow:0 4px 12px rgba(59,130,246,0.3)}
    .btn-outline{background:transparent;border:1.5px solid #334155;color:#cbd5e1}
    .btn-outline:hover{border-color:#3b82f6;color:#3b82f6}
    .btn-sm{padding:6px 14px;font-size:0.78rem;border-radius:6px}
    .btn-danger{background:transparent;border:1.5px solid rgba(239,68,68,0.3);color:#ef4444}
    .btn-danger:hover{background:rgba(239,68,68,0.1);border-color:#ef4444}

    .backup-item{display:flex;align-items:center;gap:12px;padding:12px;background:#0f172a;border-radius:10px;margin-bottom:8px;border:1px solid transparent;transition:all 0.2s}
    .backup-item:hover{border-color:#334155}
    .backup-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(59,130,246,0.12)}
    .backup-name{font-size:0.85rem;font-weight:600;font-family:'JetBrains Mono',monospace}
    .backup-meta{font-size:0.72rem;color:#64748b;margin-top:2px}

    .warning-box{background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.2);border-radius:10px;padding:14px 16px;font-size:0.82rem;color:#fbbf24;line-height:1.6;margin-bottom:16px}

    .empty-state{text-align:center;padding:32px;color:#64748b;font-size:0.85rem}

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
                <div><h1>Backup & Restore</h1><p>Create and manage database backups</p></div>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <!-- CREATE BACKUP -->
            <div class="form-card">
                <div class="form-card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Create Backup
                </div>
                <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:16px;">Generate a full database backup. The file will be saved on the server and available for download.</p>
                <form method="POST" action="<?php echo $bp; ?>/api/settings/backup.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Create Backup Now
                    </button>
                </form>
            </div>

            <!-- EXISTING BACKUPS -->
            <div class="form-card">
                <div class="form-card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/></svg>
                    Existing Backups (<?php echo count($backups); ?>)
                </div>
                <?php if (empty($backups)): ?>
                    <div class="empty-state">No backups created yet.</div>
                <?php else: ?>
                    <?php foreach ($backups as $b): ?>
                    <div class="backup-item">
                        <div class="backup-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="backup-name"><?php echo htmlspecialchars($b['name']); ?></div>
                            <div class="backup-meta"><?php echo number_format($b['size'] / 1024, 1); ?> KB &middot; <?php echo date('M d, Y g:i A', $b['date']); ?></div>
                        </div>
                        <a href="<?php echo $bp; ?>/api/settings/restore.php?file=<?php echo urlencode($b['name']); ?>" class="btn btn-outline btn-sm" onclick="return confirm('Download backup file?')">Download</a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- RESTORE -->
            <div class="form-card">
                <div class="form-card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Restore from Backup
                </div>
                <div class="warning-box">
                    <strong>Warning:</strong> Restoring a backup will overwrite all current data. This action cannot be undone. Make sure to create a backup first.
                </div>
                <p style="font-size:0.85rem;color:#94a3b8;">To restore, download a backup file, open phpMyAdmin, select the <code style="background:#0f172a;padding:2px 6px;border-radius:4px;font-family:'JetBrains Mono',monospace;font-size:0.82rem;">goplanner_db</code> database, click Import, and upload the .sql file.</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>