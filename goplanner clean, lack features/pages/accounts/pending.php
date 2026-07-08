<?php
// pages/accounts/pending.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin']);

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));

// Fetch pending accounts
$pending = [];
try {
    $stmt = $db->query("
        SELECT u.id, u.student_id, u.email, u.first_name, u.last_name, u.middle_name, u.suffix,
               u.role, u.year_level, u.section, u.created_at,
               c.name AS college_name, c.abbreviation AS college_abbr, c.color AS college_color,
               p.name AS program_name, p.code AS program_code
        FROM users u
        LEFT JOIN colleges c ON u.college_id = c.id
        LEFT JOIN programs p ON u.program_id = p.id
        WHERE u.status = 'pending' AND u.is_archived = 0
        ORDER BY u.created_at ASC
    ");
    $pending = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

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
    <title>Pending Accounts — GoPlanner</title>
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
    .notif-badge{position:absolute;top:-2px;right:-2px;width:16px;height:16px;background:#ef4444;color:white;border-radius:50%;font-size:0.6rem;display:flex;align-items:center;justify-content:center;font-weight:700}
    .topbar-user{display:flex;align-items:center;gap:8px;text-decoration:none;padding:4px 8px;border-radius:8px;transition:background 0.2s ease}
    .topbar-user:hover{background:#1e293b}
    .topbar-avatar{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.7rem;font-weight:700}
    .topbar-username{font-size:0.82rem;color:#f1f5f9;font-weight:500}

    .content{flex:1;padding:32px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeIn{from{opacity:0}to{opacity:1}}

    .page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;flex-wrap:wrap;gap:12px;animation:fadeUp 0.5s ease}
    .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .page-header p{font-size:0.88rem;color:#64748b}

    .count-badge{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;background:rgba(245,158,11,0.15);color:#f59e0b;border-radius:8px;font-size:0.85rem;font-weight:700;font-family:'JetBrains Mono',monospace;margin-left:8px}

    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:0.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.2s ease;text-decoration:none;border:none}
    .btn-primary{background:#3b82f6;color:white}
    .btn-primary:hover{background:#2563eb;transform:translateY(-1px);box-shadow:0 4px 12px rgba(59,130,246,0.3)}

    .flash-message{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;font-weight:500;animation:fadeUp 0.3s ease;display:flex;align-items:center;gap:10px}
    .flash-success{background:rgba(16,185,129,0.1);color:#6ee7b7;border-left:4px solid #10b981}
    .flash-error{background:rgba(239,68,68,0.1);color:#fca5a5;border-left:4px solid #ef4444}

    /* PENDING CARDS */
    .pending-grid{display:flex;flex-direction:column;gap:16px}
    .pending-card{background:#1e293b;border:1px solid #334155;border-radius:12px;overflow:hidden;transition:all 0.25s ease;opacity:0;animation:fadeUp 0.5s ease forwards}
    .pending-card:hover{border-color:#475569;box-shadow:0 8px 24px rgba(0,0,0,0.2)}
    .pending-card-header{display:flex;align-items:center;gap:16px;padding:20px 24px;border-bottom:1px solid #334155}
    .pending-avatar{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.85rem;font-weight:700;flex-shrink:0}
    .pending-name{font-size:1rem;font-weight:700;color:#f1f5f9}
    .pending-email{font-size:0.78rem;color:#64748b;margin-top:2px}
    .pending-role{display:inline-block;padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:600;margin-top:4px}
    .role-student{background:rgba(59,130,246,0.12);color:#60a5fa}
    .role-instructor{background:rgba(16,185,129,0.12);color:#6ee7b7}
    .role-admin{background:rgba(139,92,246,0.12);color:#c4b5fd}

    .pending-card-body{padding:20px 24px}
    .detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
    .detail-item{display:flex;flex-direction:column;gap:2px}
    .detail-label{font-size:0.7rem;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;font-weight:600}
    .detail-value{font-size:0.88rem;color:#f1f5f9;font-weight:500}
    .detail-value.mono{font-family:'JetBrains Mono',monospace;font-weight:600}

    .pending-card-actions{display:flex;gap:10px;padding:16px 24px;background:rgba(15,23,42,0.5);border-top:1px solid #334155}
    .btn-approve{background:rgba(16,185,129,0.12);color:#10b981;border:1.5px solid rgba(16,185,129,0.3);flex:1}
    .btn-approve:hover{background:rgba(16,185,129,0.2);border-color:#10b981;transform:translateY(-1px)}
    .btn-reject{background:rgba(239,68,68,0.12);color:#ef4444;border:1.5px solid rgba(239,68,68,0.3);flex:1}
    .btn-reject:hover{background:rgba(239,68,68,0.2);border-color:#ef4444;transform:translateY(-1px)}

    .empty-state{text-align:center;padding:60px 20px;animation:fadeUp 0.5s ease}
    .empty-icon{width:80px;height:80px;margin:0 auto 20px;background:rgba(16,185,129,0.08);border-radius:20px;display:flex;align-items:center;justify-content:center}
    .empty-title{font-size:1.1rem;font-weight:700;color:#f1f5f9;margin-bottom:6px}
    .empty-desc{font-size:0.88rem;color:#64748b}

    /* CONFIRM MODAL */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:100;align-items:center;justify-content:center}
    .modal-overlay.show{display:flex}
    .modal{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:32px;max-width:420px;width:90%;animation:fadeUp 0.3s ease}
    .modal h3{font-size:1.1rem;font-weight:700;margin-bottom:8px}
    .modal p{font-size:0.88rem;color:#94a3b8;margin-bottom:24px;line-height:1.6}
    .modal-actions{display:flex;gap:10px;justify-content:flex-end}
    .btn-cancel{background:transparent;color:#94a3b8;border:1.5px solid #334155}
    .btn-cancel:hover{border-color:#64748b;color:#f1f5f9}
    .btn-confirm-approve{background:#10b981;color:white}
    .btn-confirm-approve:hover{background:#059669}
    .btn-confirm-reject{background:#ef4444;color:white}
    .btn-confirm-reject:hover{background:#dc2626}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}.detail-grid{grid-template-columns:1fr 1fr}}
    @media(max-width:480px){.topbar-username,.topbar-date{display:none}.pending-card-header{flex-direction:column;align-items:flex-start}.detail-grid{grid-template-columns:1fr}}
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
                    <h1>Pending Accounts <span class="count-badge"><?php echo count($pending); ?></span></h1>
                    <p>Review and approve or reject student and faculty registrations</p>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($pending)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="empty-title">All caught up!</div>
                    <div class="empty-desc">There are no pending accounts to review right now.</div>
                </div>
            <?php else: ?>
                <div class="pending-grid">
                    <?php foreach ($pending as $i => $p): ?>
                    <div class="pending-card" style="animation-delay:<?php echo 0.05 * $i; ?>s;">
                        <div class="pending-card-header">
                            <div class="pending-avatar" style="background:<?php echo $p['college_color'] ?? '#3b82f6'; ?>;">
                                <?php echo strtoupper(substr($p['first_name'],0,1) . substr($p['last_name'],0,1)); ?>
                            </div>
                            <div>
                                <div class="pending-name">
                                    <?php echo htmlspecialchars($p['first_name'] . ($p['middle_name'] ? ' ' . $p['middle_name'] : '') . ' ' . $p['last_name'] . ($p['suffix'] ? ' ' . $p['suffix'] : '')); ?>
                                </div>
                                <div class="pending-email"><?php echo htmlspecialchars($p['email']); ?></div>
                                <span class="pending-role role-<?php echo $p['role']; ?>"><?php echo ucfirst($p['role']); ?></span>
                            </div>
                        </div>
                        <div class="pending-card-body">
                            <div class="detail-grid">
                                <?php if ($p['student_id']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Student ID</span>
                                    <span class="detail-value mono"><?php echo htmlspecialchars($p['student_id']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($p['college_abbr']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">College</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($p['college_abbr'] . ' — ' . $p['college_name']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($p['program_name']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Program</span>
                                    <span class="detail-value"><?php echo htmlspecialchars(($p['program_code'] ? '[' . $p['program_code'] . '] ' : '') . $p['program_name']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($p['year_level']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Year & Section</span>
                                    <span class="detail-value"><?php echo $p['year_level']; ?><?php echo $p['section'] ? '-' . htmlspecialchars($p['section']) : ''; ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <span class="detail-label">Registered</span>
                                    <span class="detail-value"><?php echo timeAgo($p['created_at']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="pending-card-actions">
                            <button class="btn btn-approve" onclick="showApproveModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['first_name'] . ' ' . $p['last_name'])); ?>')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                Approve
                            </button>
                            <button class="btn btn-reject" onclick="showRejectModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['first_name'] . ' ' . $p['last_name'])); ?>')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Reject
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- APPROVE MODAL -->
<div class="modal-overlay" id="approveModal">
    <div class="modal">
        <h3>Approve Account</h3>
        <p>Are you sure you want to approve <strong id="approveName"></strong>? They will be granted access to GoPlanner.</p>
        <form id="approveForm" method="POST" action="<?php echo $bp; ?>/api/accounts/approve.php">
            <input type="hidden" name="user_id" id="approveId">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="btn btn-confirm-approve">Confirm Approve</button>
            </div>
        </form>
    </div>
</div>

<!-- REJECT MODAL -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <h3>Reject Account</h3>
        <p>Are you sure you want to reject <strong id="rejectName"></strong>? They will be notified that their registration was declined.</p>
        <form id="rejectForm" method="POST" action="<?php echo $bp; ?>/api/accounts/reject.php">
            <input type="hidden" name="user_id" id="rejectId">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-confirm-reject">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>

<script>
function showApproveModal(id, name) {
    document.getElementById('approveId').value = id;
    document.getElementById('approveName').textContent = name;
    document.getElementById('approveModal').classList.add('show');
}

function showRejectModal(id, name) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectName').textContent = name;
    document.getElementById('rejectModal').classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

document.querySelectorAll('.modal-overlay').forEach(function(m) {
    m.addEventListener('click', function(e) {
        if (e.target === m) m.classList.remove('show');
    });
});
</script>
</body>
</html>