<?php
// components/sidebar.php
// Must be included after session is started and user is logged in

$sidebarUser = $user ?? currentUser();
$sidebarRole = $sidebarUser['role'] ?? 'student';
$sidebarBP = $bp ?? (defined('APP_BASE_PATH') ? APP_BASE_PATH : '/goplanner');
$sidebarIni = strtoupper(substr($sidebarUser['first_name'] ?? 'U', 0, 1) . substr($sidebarUser['last_name'] ?? 'S', 0, 1));
$sidebarName = trim(($sidebarUser['first_name'] ?? '') . ' ' . ($sidebarUser['last_name'] ?? ''));
$roleLabels = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'instructor' => 'Instructor', 'student' => 'Student'];
$roleLabel = $roleLabels[$sidebarRole] ?? 'User';

// Logo
$sidebarLogoFile = __DIR__ . '/../assets/images/logo/goplanner-logo.png';
$sidebarLogoSrc = $sidebarBP . '/assets/images/logo/goplanner-logo.png';
?>

<!-- OVERLAY -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<div class="sb" id="sidebar">
    <div class="sb-header">
        <a href="<?php echo $sidebarBP; ?>/index.php" class="sb-logo">
            <div class="sb-logo-icon">
                <?php if (file_exists($sidebarLogoFile)): ?>
                    <img src="<?php echo $sidebarLogoSrc; ?>" alt="GoPlanner">
                <?php else: ?>
                    GP
                <?php endif; ?>
            </div>
            <div class="sb-logo-text">
                <span class="sb-logo-name">GoPlanner</span>
                <span class="sb-logo-sub">DEBESMSCAT v2.0</span>
            </div>
        </a>
        <button class="sb-close" onclick="closeSidebar()">&times;</button>
    </div>

    <!-- USER -->
    <div class="sb-user">
        <div class="sb-user-avatar" style="background:<?php echo $sidebarUser['college_color'] ?? '#3b82f6'; ?>;">
            <?php echo $sidebarIni; ?>
        </div>
        <div class="sb-user-info">
            <div class="sb-user-name"><?php echo htmlspecialchars($sidebarName); ?></div>
            <div class="sb-user-role"><?php echo $roleLabel; ?></div>
        </div>
    </div>

    <!-- NAV -->
    <nav class="sb-nav">
        <div class="sb-section">
            <div class="sb-section-title">Main</div>
            <a href="<?php echo $sidebarBP; ?>/pages/dashboard/<?php echo $sidebarRole === 'super_admin' ? 'superadmin' : $sidebarRole; ?>.php" class="sb-link <?php echo basename($_SERVER['PHP_SELF']) === ($sidebarRole === 'super_admin' ? 'superadmin' : $sidebarRole) . '.php' ? 'active' : ''; ?>">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/calendar/index.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Calendar
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/announcements/view.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                Announcements
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/rooms/index.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Rooms
            </a>
        </div>

        <?php if (in_array($sidebarRole, ['super_admin', 'admin'])): ?>
        <div class="sb-section">
            <div class="sb-section-title">Administration</div>
            <a href="<?php echo $sidebarBP; ?>/pages/announcements/create.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Create Announcement
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/accounts/manage.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Manage Accounts
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/accounts/pending.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Pending Accounts
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/rooms/create.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/></svg>
                Create Room
            </a>
        </div>
        <?php endif; ?>

        <?php if ($sidebarRole === 'instructor'): ?>
        <div class="sb-section">
            <div class="sb-section-title">Teaching</div>
            <a href="<?php echo $sidebarBP; ?>/pages/announcements/create.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Create Announcement
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/rooms/create.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/></svg>
                Create Room
            </a>
        </div>
        <?php endif; ?>

        <?php if ($sidebarRole === 'super_admin'): ?>
        <div class="sb-section">
            <div class="sb-section-title">System</div>
            <a href="<?php echo $sidebarBP; ?>/pages/accounts/archived.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/></svg>
                Archived Accounts
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/analytics/index.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Analytics
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/settings/backup.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Backup & Restore
            </a>
        </div>
        <?php endif; ?>

        <?php if ($sidebarRole === 'admin'): ?>
        <div class="sb-section">
            <div class="sb-section-title">System</div>
            <a href="<?php echo $sidebarBP; ?>/pages/accounts/archived.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/></svg>
                Archived Accounts
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/analytics/index.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Analytics
            </a>
        </div>
        <?php endif; ?>

        <div class="sb-section">
            <div class="sb-section-title">Other</div>
            <a href="<?php echo $sidebarBP; ?>/pages/about.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                About
            </a>
            <a href="<?php echo $sidebarBP; ?>/pages/settings/profile.php" class="sb-link">
                <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Settings
            </a>
        </div>
    </nav>

    <div class="sb-footer">
        <a href="<?php echo $sidebarBP; ?>/api/auth/logout.php" class="sb-link sb-logout">
            <svg class="sb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
        </a>
    </div>
</div>

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sbOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sbOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
</script>