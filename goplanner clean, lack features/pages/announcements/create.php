<?php
// pages/announcements/create.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin', 'instructor']);

$user = currentUser();
$db = db();
$bp = APP_BASE_PATH;
$cc = $user['college_color'] ?? '#3b82f6';
$ini = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1));
$role = $user['role'] ?? 'instructor';
$collegeId = $user['college_id'] ?? 0;

// Edit mode
$editId = (int)($_GET['edit'] ?? 0);
$editAnn = null;

if ($editId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt->execute([$editId]);
        $editAnn = $stmt->fetch();
        // Only allow editing own announcements or super_admin
        if ($editAnn && $editAnn['created_by'] != $user['id'] && $role !== 'super_admin') {
            $editAnn = null;
        }
    } catch (Exception $e) {}
}

// Colleges for selection
$colleges = [];
try {
    if ($role === 'super_admin') {
        $colleges = $db->query("SELECT id, abbreviation, name, color FROM colleges WHERE is_active = 1 ORDER BY name")->fetchAll();
    } else {
        $stmt = $db->prepare("SELECT id, abbreviation, name, color FROM colleges WHERE id = ? AND is_active = 1");
        $stmt->execute([$collegeId]);
        $colleges = $stmt->fetchAll();
    }
} catch (Exception $e) {}

// Programs
$programs = [];
try {
    $programs = $db->query("SELECT id, college_id, code, name, major FROM programs WHERE is_active = 1 ORDER BY college_id, name")->fetchAll();
} catch (Exception $e) {}

$unreadNotifs = 0;
try {
    $s = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $s->execute([$user['id']]);
    $unreadNotifs = (int)$s->fetchColumn();
} catch (Exception $e) {}

$flash = getFlash();

$programsByCollege = [];
foreach ($programs as $p) {
    $programsByCollege[$p['college_id']][] = $p;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editAnn ? 'Edit' : 'Create'; ?> Announcement — GoPlanner</title>
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

    .content{flex:1;padding:32px;max-width:800px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

    .page-header{margin-bottom:28px;animation:fadeUp 0.5s ease}
    .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px}
    .page-header p{font-size:0.88rem;color:#64748b}

    .flash-message{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;font-weight:500;animation:fadeUp 0.3s ease}
    .flash-success{background:rgba(16,185,129,0.1);color:#6ee7b7;border-left:4px solid #10b981}
    .flash-error{background:rgba(239,68,68,0.1);color:#fca5a5;border-left:4px solid #ef4444}

    .form-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:28px;margin-bottom:24px;opacity:0;animation:fadeUp 0.5s ease forwards;animation-delay:0.1s}
    .form-card-title{font-size:0.92rem;font-weight:700;color:#f1f5f9;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #334155;display:flex;align-items:center;gap:8px}
    .form-card-title svg{flex-shrink:0}

    .form-group{margin-bottom:18px}
    .form-group:last-child{margin-bottom:0}
    .form-group label{display:block;font-size:0.82rem;font-weight:600;color:#cbd5e1;margin-bottom:6px}
    .form-group label .req{color:#ef4444;margin-left:2px}
    .form-group label .optional{color:#475569;font-weight:400;font-size:0.75rem;margin-left:4px}
    .form-hint{font-size:0.75rem;color:#475569;margin-top:4px}

    .form-control{width:100%;padding:11px 14px;background:#0f172a;border:1.5px solid #334155;border-radius:10px;color:#f1f5f9;font-size:0.88rem;font-family:'Inter',sans-serif;outline:none;transition:all 0.25s ease}
    .form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.12)}
    .form-control::placeholder{color:#475569}
    textarea.form-control{resize:vertical;min-height:160px;line-height:1.7}

    select.form-control{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px}
    select.form-control option{background:#1e293b;color:#f1f5f9}

    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}

    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:0.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.2s ease;text-decoration:none;border:none}
    .btn-primary{background:#3b82f6;color:white;padding:14px 28px;font-size:0.92rem}
    .btn-primary:hover{background:#2563eb;transform:translateY(-1px);box-shadow:0 4px 12px rgba(59,130,246,0.3)}
    .btn-outline{background:transparent;border:1.5px solid #334155;color:#cbd5e1}
    .btn-outline:hover{border-color:#3b82f6;color:#3b82f6}

    .form-actions{display:flex;gap:12px;align-items:center;margin-top:8px}
    .spinner{width:18px;height:18px;border:2.5px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.6s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}

    .target-note{font-size:0.78rem;color:#64748b;padding:10px 14px;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.15);border-radius:8px;margin-top:8px}

    @media(max-width:1024px){.main-area{margin-left:0}.menu-toggle{display:block}}
    @media(max-width:768px){.content{padding:20px 16px}.topbar{padding:0 16px}.page-header h1{font-size:1.3rem}.form-row{grid-template-columns:1fr}}
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
                <div><h1><?php echo $editAnn ? 'Edit' : 'Create'; ?> Announcement</h1><p><?php echo $editAnn ? 'Update announcement details' : 'Share news, events, and updates with your college'; ?></p></div>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <form action="<?php echo $bp; ?>/api/announcements/store.php" method="POST" id="annForm">
                <?php if ($editAnn): ?>
                    <input type="hidden" name="id" value="<?php echo $editAnn['id']; ?>">
                <?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">

                <!-- CONTENT -->
                <div class="form-card">
                    <div class="form-card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                        Announcement Content
                    </div>

                    <div class="form-group">
                        <label>Title <span class="req">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g., Midterm Exam Schedule for CCIT" required maxlength="255" value="<?php echo htmlspecialchars($editAnn['title'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Content <span class="req">*</span></label>
                        <textarea name="content" class="form-control" placeholder="Write the full announcement content here..." required><?php echo htmlspecialchars($editAnn['content'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                <?php foreach (['low','normal','high','urgent'] as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php echo ($editAnn['priority'] ?? 'normal') === $p ? 'selected' : ''; ?>><?php echo ucfirst($p); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="published" <?php echo ($editAnn['status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Published</option>
                                <option value="draft" <?php echo ($editAnn['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_pinned" value="1" <?php echo ($editAnn['is_pinned'] ?? 0) ? 'checked' : ''; ?>>
                            Pin this announcement
                        </label>
                        <div class="form-hint">Pinned announcements appear at the top of the list.</div>
                    </div>
                </div>

                <!-- TARGETING -->
                <div class="form-card">
                    <div class="form-card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        Audience Targeting
                    </div>

                    <?php if ($role === 'super_admin'): ?>
                    <div class="form-group">
                        <label>College <span class="optional">(leave empty for all colleges)</span></label>
                        <select name="college_id" id="collegeSelect" class="form-control">
                            <option value="">All Colleges</option>
                            <?php foreach ($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($editAnn['college_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['abbreviation'] . ' — ' . $c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="college_id" value="<?php echo $collegeId; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Program <span class="optional">(leave empty for all programs)</span></label>
                        <select name="program_id" id="programSelect" class="form-control">
                            <option value="">All Programs</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Target Year <span class="optional">(all years if empty)</span></label>
                            <select name="target_year" class="form-control">
                                <option value="">All Years</option>
                                <?php for ($i=1;$i<=5;$i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($editAnn['target_year'] ?? '') == $i ? 'selected' : ''; ?>><?php echo $i; ?><?php echo $i===1?'st':($i===2?'nd':($i===3?'rd':'th')); ?> Year</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Target Section <span class="optional">(all sections if empty)</span></label>
                            <input type="text" name="target_section" class="form-control" placeholder="e.g., A" maxlength="10" value="<?php echo htmlspecialchars($editAnn['target_section'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="target-note">
                        Leave targeting fields empty to make the announcement visible to everyone in the selected college.
                    </div>
                </div>

                <!-- EVENT DETAILS -->
                <div class="form-card">
                    <div class="form-card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Event Details <span class="optional">(optional)</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Event Date</label>
                            <input type="date" name="event_date" class="form-control" value="<?php echo $editAnn['event_date'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Event Time</label>
                            <input type="time" name="event_time" class="form-control" value="<?php echo $editAnn['event_time'] ?? ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>End Date <span class="optional">(for multi-day events)</span></label>
                        <input type="date" name="event_end_date" class="form-control" value="<?php echo $editAnn['event_end_date'] ?? ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Location / Address</label>
                        <input type="text" name="map_address" class="form-control" placeholder="e.g., DEBESMSCAT Main Campus, Masbate City" maxlength="500" value="<?php echo htmlspecialchars($editAnn['map_address'] ?? ''); ?>">
                    </div>
                </div>
                
                <!-- FILE UPLOADS -->
                <div class="form-card">
                    <div class="form-card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        Attachments <span class="optional">(optional)</span>
                    </div>

                    <div class="form-group">
                        <label>Upload Files</label>
                        <input type="file" name="files[]" class="form-control" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif">
                        <div class="form-hint">Max 10MB per file. Allowed: PDF, Word, PowerPoint, Excel, Images (JPG, PNG, GIF)</div>
                    </div>
                </div>

                <!-- SUBMIT -->
                <div class="form-actions">
                    <form action="<?php echo $bp; ?>/api/announcements/store.php" method="POST" id="annForm" enctype="multipart/form-data">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                        <span class="btn-text"><?php echo $editAnn ? 'Update Announcement' : 'Publish Announcement'; ?></span>
                        <span class="btn-loading" style="display:none;"><span class="spinner"></span> Saving...</span>
                    </button>
                    <a href="<?php echo $bp; ?>/pages/announcements/view.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    var programsByCollege = <?php echo json_encode($programsByCollege); ?>;
    var collegeSel = document.getElementById('collegeSelect');
    var programSel = document.getElementById('programSelect');
    var form = document.getElementById('annForm');
    var submitBtn = document.getElementById('submitBtn');

    function updatePrograms() {
        var cid = collegeSel ? collegeSel.value : '<?php echo $collegeId; ?>';
        programSel.innerHTML = '<option value="">All Programs</option>';
        if (cid && programsByCollege[cid]) {
            programsByCollege[cid].forEach(function(p) {
                var o = document.createElement('option');
                o.value = p.id;
                var label = '[' + p.code + '] ' + p.name;
                if (p.major) label += ' — ' + p.major;
                o.textContent = label;
                o.selected = ('<?php echo $editAnn['program_id'] ?? ''; ?>' == p.id);
                programSel.appendChild(o);
            });
        }
    }

    if (collegeSel) {
        collegeSel.addEventListener('change', updatePrograms);
        updatePrograms();
    } else {
        updatePrograms();
    }

    form.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.querySelector('.btn-text').style.display = 'none';
        submitBtn.querySelector('.btn-loading').style.display = 'inline-flex';
    });
})();
</script>
</body>
</html>