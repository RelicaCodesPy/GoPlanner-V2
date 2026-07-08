<?php
// pages/accounts/archived.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin']);

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));

$archived = [];
try {
    $stmt = $db->query("
        SELECT u.id, u.student_id, u.email, u.first_name, u.last_name, u.role,
               u.archived_at, u.created_at,
               c.abbreviation AS college_abbr, c.color AS college_color
        FROM users u
        LEFT JOIN colleges c ON u.college_id = c.id
        WHERE u.is_archived = 1
        ORDER BY u.archived_at DESC
    ");
    $archived = $stmt->fetchAll();
} catch (Exception $e) {}

$unreadNotifs = 0;
try {
    $s = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $s->execute([$user['id']]);
    $unreadNotifs = (int)$s->fetchColumn();
} catch (Exception $e) {}

$flash = getFlash();

$roleLabels = ['student' => 'Student', 'instructor' => 'Instructor', 'admin' => 'Admin', 'super_admin' => 'Super Admin'];
$roleColors = ['student' => '#3b82f6', 'instructor' => '#10b981', 'admin' => '#8b5cf6', 'super_admin' => '#f59e0b'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Accounts — GoPlanner</title>
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

    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:0.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.2s ease;text-decoration:none;border:none}

    .flash-message{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;font-weight:500;animation:fadeUp 0.3s ease}
    .flash-success{background:rgba(16,185,129,0.1);color:#6ee7b7;border-left:4px solid #10b981}
    .flash-error{background:rgba(239,68,68,0.1);color:#fca5a5;border-left:4px solid #ef4444}

    .table-wrap{background:#1e293b;border:1px solid #334155;border-radius:12px;overflow:hidden;animation:fadeUp 0.5s ease;animation-delay:0.1s;opacity:0;animation-fill-mode:forwards}
    table{width:100%;border-collapse:collapse}
    thead th{padding:12px 16px;text-align:left;font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #334155;background:rgba(15,23,42,0.5)}
    tbody td{padding:12px 16px;font-size:0.85rem;border-bottom:1px solid rgba(30,41,59,0.5);vertical-align:middle}
    tbody tr{transition:background 0.15s}
    tbody tr:hover{background:rgba(59,130,246,0.04)}
    tbody tr:last-child td{border-bottom:none}

    .user-cell{display:flex;align-items:center;gap:10px}
    .user-avatar-sm{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.62rem;font-weight:700;flex-shrink:0;opacity:0.6}
    .user-name-sm{font-weight:600;color:#94a3b8;font-size:0.85rem}
    .user-email-sm{font-size:0.72rem;color:#475569}

    .role-badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;opacity:0.7}

    .action-btn{padding:6px 12px;border-radius:6px;font-size:0.75rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;transition:all 0.2s;font-family:'Inter',sans-serif;background:transparent}
    .action-restore{color:#10b981;border-color:rgba(16,185,129,0.3)}
    .action-restore:hover{background:rgba(16,185,129,0.1);border-color:#10b981}

    .empty-state{text-align:center;padding:60px 20px;animation:fadeUp 0.5s ease}
    .empty-icon{width:80px;height:80px;margin:0 auto 20px;background:rgba(100,116,139,0.08);border-radius:20px;display:flex;align-items:center;justify-content:center}
    .empty-title{font-size:1.1rem;font-weight:700;color:#f1f5f9;margin-bottom:6px}
    .empty-desc{font-size:0.88rem;color:#64748b}

    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:100;align-items:center;justify-content:center}
    .modal-overlay.show{display:flex}
    .modal{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:32px;max-width:420px;width:90%;animation:fadeUp 0.3s ease}
    .modal h3{font-size:1.1rem;font-weight:700;margin-bottom:8px}
    .modal p{font-size:0.88rem;color:#94a3b8;margin-bottom:24px;line-height:1.6}
    .modal-actions{display:flex;gap:10px;justify-content:flex-end}
    .btn-cancel{background:transparent;color:#94a3b8;border:1.5px solid #334155}
    .btn-cancel:hover{border-color:#64748b;color:#f1f5f9}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}.table-wrap{overflow-x:auto}table{min-width:580px}}
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
                <div><h1>Archived Accounts</h1><p>Restore previously archived user accounts</p></div>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <?php if (empty($archived)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.5"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/></svg>
                    </div>
                    <div class="empty-title">No archived accounts</div>
                    <div class="empty-desc">There are no archived user accounts in the system.</div>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>College</th>
                                <th>Archived</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archived as $a): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-sm" style="background:<?php echo $a['college_color'] ?? '#64748b'; ?>;">
                                            <?php echo strtoupper(substr($a['first_name'],0,1) . substr($a['last_name'],0,1)); ?>
                                        </div>
                                        <div>
                                            <div class="user-name-sm"><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></div>
                                            <div class="user-email-sm"><?php echo htmlspecialchars($a['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge" style="background:<?php echo $roleColors[$a['role']] ?? '#3b82f6'; ?>20;color:<?php echo $roleColors[$a['role']] ?? '#3b82f6'; ?>;">
                                        <?php echo $roleLabels[$a['role']] ?? $a['role']; ?>
                                    </span>
                                </td>
                                <td style="font-size:0.82rem;"><?php echo $a['college_abbr'] ? htmlspecialchars($a['college_abbr']) : '<span style="color:#475569">—</span>'; ?></td>
                                <td style="font-size:0.82rem;color:#94a3b8;"><?php echo $a['archived_at'] ? timeAgo($a['archived_at']) : '—'; ?></td>
                                <td>
                                    <button class="action-btn action-restore" onclick="showRestoreModal(<?php echo $a['id']; ?>, '<?php echo htmlspecialchars(addslashes($a['first_name'] . ' ' . $a['last_name'])); ?>')">Restore</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- RESTORE MODAL -->
<div class="modal-overlay" id="restoreModal">
    <div class="modal">
        <h3>Restore Account</h3>
        <p>Restore <strong id="restoreName"></strong>? They will be able to log in again with their previous credentials.</p>
        <form method="POST" action="<?php echo $bp; ?>/api/accounts/restore.php">
            <input type="hidden" name="user_id" id="restoreId">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal('restoreModal')">Cancel</button>
                <button type="submit" class="btn" style="background:#10b981;color:white;">Confirm Restore</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRestoreModal(id, name) {
    document.getElementById('restoreId').value = id;
    document.getElementById('restoreName').textContent = name;
    document.getElementById('restoreModal').classList.add('show');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}
document.querySelectorAll('.modal-overlay').forEach(function(m) {
    m.addEventListener('click', function(e) { if (e.target === m) m.classList.remove('show'); });
});
</script>
</body>
</html>