<?php
// api/auth/logout.php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

$basePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/goplanner';

// Log activity before destroying session (safe)
if (isLoggedIn()) {
    try {
        logActivity('user_logout', 'user', $_SESSION['user_id'] ?? null);
    } catch (Exception $e) {
        // Don't fail logout for logging errors
    }
}

// Destroy session
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Redirect to login
header('Location: ' . $basePath . '/pages/login.php');
exit;