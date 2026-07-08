<?php
// includes/rbac.php
require_once __DIR__ . '/auth.php';

$PERMISSIONS = [
    'super_admin' => [
        'view_all_stats','manage_all_users','manage_college_users',
        'view_announcements','create_announcements','edit_announcements','delete_announcements',
        'view_rooms','create_rooms','manage_rooms',
        'view_notifications','send_notifications',
        'manage_files','manage_backups','manage_settings',
        'view_analytics','view_audit_log',
        'approve_accounts','archive_accounts','delete_accounts','system_backup'
    ],
    'admin' => [
        'view_all_stats','manage_college_users',
        'view_announcements','create_announcements','edit_announcements','delete_announcements',
        'view_rooms','create_rooms','manage_rooms',
        'view_notifications','send_notifications',
        'manage_files','view_analytics',
        'approve_accounts','archive_accounts'
    ],
    'instructor' => [
        'view_announcements','create_announcements',
        'view_rooms','create_rooms',
        'view_notifications'
    ],
    'student' => [
        'view_announcements',
        'view_rooms',
        'view_notifications'
    ],
];

if (!defined('ROLES')) {
    define('ROLES', $PERMISSIONS);
}

if (!function_exists('can')) {
    function can(string $p): bool {
        global $PERMISSIONS;
        $u = currentUser();
        if (!$u) return false;
        $role = $u['role'] ?? '';
        if ($role === 'super_admin') return true;
        return isset($PERMISSIONS[$role]) && in_array($p, $PERMISSIONS[$role]);
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission(string $p): void {
        requireLogin();
        $u = currentUser();
        if (!$u) { header('Location: ' . APP_BASE_PATH . '/pages/login.php'); exit; }
        if ($u['role'] === 'super_admin') return;
        if (!can($p)) {
            http_response_code(403);
            include __DIR__ . '/../pages/errors/403.php';
            exit;
        }
    }
}

if (!function_exists('requireRole')) {
    function requireRole(string ...$roles): void {
        requireLogin();
        $u = currentUser();
        if (!$u) { header('Location: ' . APP_BASE_PATH . '/pages/login.php'); exit; }
        if ($u['role'] === 'super_admin') return;
        if (!in_array($u['role'], $roles)) {
            http_response_code(403);
            include __DIR__ . '/../pages/errors/403.php';
            exit;
        }
    }
}