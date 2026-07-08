<?php
// config/app.php
// DEBESMSCAT GoPlanner V2 - Application Configuration

define('APP_NAME', 'DEBESMSCAT GoPlanner');
define('APP_VERSION', '2.0');
define('APP_URL', 'http://localhost/goplanner');
define('APP_BASE_PATH', '/goplanner');

// Upload limits
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg',
    'image/png',
    'image/gif'
]);

// College color mapping
define('COLLEGE_COLORS', [
    'CAAf'  => ['hex' => '#166534', 'name' => 'green',  'light' => '#dcfce7', 'text' => '#15803d'],
    'CCIT'  => ['hex' => '#0D9488', 'name' => 'teal',   'light' => '#ccfbf1', 'text' => '#0f766e'],
    'CBTE'  => ['hex' => '#DC2626', 'name' => 'red',    'light' => '#fee2e2', 'text' => '#dc2626'],
    'CEng'  => ['hex' => '#800020', 'name' => 'maroon', 'light' => '#fce7f3', 'text' => '#9d174d'],
    'CE'    => ['hex' => '#1E3A8A', 'name' => 'blue',   'light' => '#dbeafe', 'text' => '#1d4ed8'],
    'CIT'   => ['hex' => '#4B5563', 'name' => 'gray',   'light' => '#f3f4f6', 'text' => '#374151']
]);

// Role permissions
define('ROLES', [
    'super_admin' => [
        'manage_all_users',
        'manage_colleges',
        'manage_announcements',
        'manage_rooms',
        'view_analytics',
        'system_backup',
        'archive_users',
        'confirm_accounts'
    ],
    'admin' => [
        'manage_college_users',
        'manage_announcements',
        'manage_rooms',
        'view_college_analytics',
        'archive_users',
        'confirm_accounts'
    ],
    'instructor' => [
        'create_announcements',
        'manage_own_rooms',
        'upload_files',
        'view_college_announcements'
    ],
    'student' => [
        'view_announcements',
        'view_calendar',
        'join_rooms',
        'post_in_rooms',
        'download_files'
    ]
]);

// ── Bootstrap auth & session (after all constants are defined) ──
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';