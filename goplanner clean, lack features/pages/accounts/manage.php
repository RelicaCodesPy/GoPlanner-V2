<?php
// pages/accounts/manage.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin']);

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));

// Filters
$search = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? 'active';
$collegeFilter = $_GET['college'] ?? '';

// Build query
$where = ["u.is_archived = 0", "u.status = 'active'"];
$params = [];

if ($search) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($roleFilter && in_array($roleFilter, ['student','instructor','admin','super_admin'])) {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
}
if ($collegeFilter) {
    $where[] = "u.college_id = ?";
    $params[] = (int)$collegeFilter;
}

$whereSQL = implode(' AND ', $where);

$users = [];
try {
    $stmt = $db->prepare("
        SELECT u.id, u.student_id, u.email, u.first_name, u.last_name, u.role, u.status,
               u.year_level, u.section, u.last_login, u.created_at,
               c.name AS college_name, c.abbreviation AS college_abbr, c.color AS college_color,
               p.name AS program_name, p.code AS program_code
        FROM users u
        LEFT JOIN colleges c ON u.college_id = c.id
        LEFT JOIN programs p ON u.program_id = p.id
        WHERE {$whereSQL}
        ORDER BY u.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Exception $e) {}

// Colleges for filter
$colleges = [];
try {
    $colleges = $db->query("SELECT id, abbreviation, name FROM colleges WHERE is_active = 1 ORDER BY name")->fetchAll();
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
    <title>Manage Accounts — GoPlanner</title>
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

    /* TABLE */
    .table-wrap{background:#1e293b;border:1px solid #334155;border-radius:12px;overflow:hidden;animation:fadeUp 0.5s ease;animation-delay:0.1s;opacity:0;animation-fill-mode:forwards}
    table{width:100%;border-collapse:collapse}
    thead th{padding:12px 16px;text-align:left;font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #334155;background:rgba(15,23,42,0.5)}
    tbody td{padding:12px 16px;font-size:0.85rem;border-bottom:1px solid rgba(30,41,59,0.5);vertical-align:middle}
    tbody tr{transition:background 0.15s}
    tbody tr:hover{background:rgba(59,130,246,0.04)}
    tbody tr:last-child td{border-bottom:none}

    .user-cell{display:flex;align-items:center;gap:10px}
    .user-avatar-sm{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.62rem;font-weight:700;flex-shrink:0}
    .user-name-sm{font-weight:600;color:#f1f5f9;font-size:0.85rem}
    .user-email-sm{font-size:0.72rem;color:#64748b}

    .role-badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600}

    .action-btn{padding:6px 12px;border-radius:6px;font-size:0.75rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;transition:all 0.2s;font-family:'Inter',sans-serif;background:transparent}
    .action-archive{color:#ef4444;border-color:rgba(239,68,68,0.3)}
    .action-archive:hover{background:rgba(239,68,68,0.1);border-color:#ef4444}

    .empty-state{text-align:center;padding:48px 20px;color:#64748b;font-size:0.88rem}

    /* MODAL */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:100;align-items:center;justify-content:center}
    .modal-overlay.show{display:flex}
    .modal{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:32px;max-width:420px;width:90%;animation:fadeUp 0.3s ease}
    .modal h3{font-size:1.1rem;font-weight:700;margin-bottom:8px}
    .modal p{font-size:0.88rem;color:#94a3b8;margin-bottom:24px;line-height:1.6}
    .modal-actions{display:flex;gap:10px;justify-content:flex-end}
    .btn-cancel{background:transparent;color:#94a3b8;border:1.5px solid #334155}
    .btn-cancel:hover{border-color:#64748b;color:#f1f5f9}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){
        .content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}
        .table-wrap{overflow-x:auto}
        table{min-width:640px}
    }
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
                <div><h1>Manage Accounts</h1><p>View and manage all active user accounts</p></div>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <form class="filters" method="GET">
                <input type="text" name="q" class="filter-input" placeholder="Search name, email, ID..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;min-width:200px;">
                <select name="role" class="filter-input">
                    <option value="">All Roles</option>
                    <?php foreach ($roleLabels as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo $roleFilter === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="college" class="filter-input">
                    <option value="">All Colleges</option>
                    <?php foreach ($colleges as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $collegeFilter == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['abbreviation'] . ' — ' . $c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="filter-btn">Filter</button>
                <span class="filter-count"><?php echo count($users); ?> account<?php echo count($users) !== 1 ? 's' : ''; ?></span>
            </form>

            <?php if (empty($users)): ?>
                <div class="empty-state">No accounts found matching your criteria.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>College</th>
                                <th>Program</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-sm" style="background:<?php echo $roleColors[$u['role']] ?? '#3b82f6'; ?>;">
                                            <?php echo strtoupper(substr($u['first_name'],0,1) . substr($u['last_name'],0,1)); ?>
                                        </div>
                                        <div>
                                            <div class="user-name-sm"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                            <div class="user-email-sm"><?php echo htmlspecialchars($u['student_id'] ?? $u['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge" style="background:<?php echo $roleColors[$u['role']] ?? '#3b82f6'; ?>20;color:<?php echo $roleColors[$u['role']] ?? '#3b82f6'; ?>;">
                                        <?php echo $roleLabels[$u['role']] ?? $u['role']; ?>
                                    </span>
                                </td>
                                <td style="font-size:0.82rem;"><?php echo $u['college_abbr'] ? htmlspecialchars($u['college_abbr']) : '<span style="color:#475569">—</span>'; ?></td>
                                <td style="font-size:0.82rem;color:#94a3b8;"><?php echo $u['program_name'] ? htmlspecialchars($u['program_code'] ?? '') : '<span style="color:#475569">—</span>'; ?></td>
                                <td style="font-size:0.82rem;color:#94a3b8;"><?php echo $u['last_login'] ? timeAgo($u['last_login']) : '<span style="color:#475569">Never</span>'; ?></td>
                                <td>
                                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                        <button class="action-btn action-archive" onclick="showArchiveModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['first_name'] . ' ' . $u['last_name'])); ?>')">Archive</button>
                                    <?php else: ?>
                                        <span style="font-size:0.72rem;color:#475569;">You</span>
                                    <?php endif; ?>
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

<!-- ARCHIVE MODAL -->
<div class="modal-overlay" id="archiveModal">
    <div class="modal">
        <h3>Archive Account</h3>
        <p>Are you sure you want to archive <strong id="archiveName"></strong>? They will no longer be able to log in. You can restore them later from the Archived Accounts page.</p>
        <form method="POST" action="<?php echo $bp; ?>/api/accounts/archive.php">
            <input type="hidden" name="user_id" id="archiveId">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal('archiveModal')">Cancel</button>
                <button type="submit" class="btn" style="background:#ef4444;color:white;">Confirm Archive</button>
            </div>
        </form>
    </div>
</div>

<script>
function showArchiveModal(id, name) {
    document.getElementById('archiveId').value = id;
    document.getElementById('archiveName').textContent = name;
    document.getElementById('archiveModal').classList.add('show');
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