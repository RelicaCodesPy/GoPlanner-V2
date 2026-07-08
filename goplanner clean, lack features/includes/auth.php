<?php
// includes/auth.php
// DEBESMSCAT GoPlanner V2 - Authentication & Session Bootstrap

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 1800);
    session_start();
}

// Auto-logout after 30 min of inactivity
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: ' . APP_BASE_PATH . '/pages/login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// ── Require login ──
function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . APP_BASE_PATH . '/pages/login.php');
        exit;
    }
}

// ── Require specific permission(s) ──
function requirePermission(array $permissions): void
{
    // First, make sure the user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ' . APP_BASE_PATH . '/pages/login.php');
        exit;
    }

    // Get the permissions for the user's role from the ROLES constant
    $role = $_SESSION['role'];
    $allowed = ROLES[$role] ?? [];

    // Check if the user has at least one of the required permissions
    foreach ($permissions as $perm) {
        if (in_array($perm, $allowed)) {
            return; // Access granted
        }
    }

    // Access denied
    http_response_code(403);
    include __DIR__ . '/../pages/errors/403.php';
    exit;
}

// ── Require exact role ──
function requireRole(array $allowedRoles): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . APP_BASE_PATH . '/pages/login.php');
        exit;
    }

    if (!in_array($_SESSION['role'], $allowedRoles)) {
        http_response_code(403);
        include __DIR__ . '/../pages/errors/403.php';
        exit;
    }
}

// ── Check if logged in (non-redirect, for templates) ──
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $db = db();
    $stmt = $db->prepare('
        SELECT u.*,
               c.name AS college_name,
               c.abbreviation AS college_abbr,
               c.color AS college_color,
               p.name AS program_name
        FROM users u
        LEFT JOIN colleges c ON u.college_id = c.id
        LEFT JOIN programs p ON u.program_id = p.id
        WHERE u.id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}