<?php
// api/auth/login.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

$basePath = APP_BASE_PATH;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header('Location: ' . $basePath . '/pages/login.php?error=empty');
    exit;
}

try {
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log('Login DB error: ' . $e->getMessage());
    header('Location: ' . $basePath . '/pages/login.php?error=invalid');
    exit;
}

if (!$user) {
    header('Location: ' . $basePath . '/pages/login.php?error=invalid');
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    header('Location: ' . $basePath . '/pages/login.php?error=invalid');
    exit;
}

if ($user['is_archived']) {
    header('Location: ' . $basePath . '/pages/login.php?error=archived');
    exit;
}

if ($user['status'] !== 'active') {
    header('Location: ' . $basePath . '/pages/login.php?error=inactive');
    exit;
}

// === LOGIN SUCCESS ===
session_regenerate_id(true);

$_SESSION['user_id']    = (int)$user['id'];
$_SESSION['role']       = $user['role'];         // ← FIX: was 'user_role'
$_SESSION['login_time'] = time();

// Update last login
try {
    $stmt = db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $stmt->execute([$user['id']]);
} catch (Exception $e) {}

// Redirect by role
switch ($user['role']) {
    case 'super_admin':
        header('Location: ' . $basePath . '/pages/dashboard/superadmin.php');
        break;
    case 'admin':
        header('Location: ' . $basePath . '/pages/dashboard/admin.php');
        break;
    case 'instructor':
        header('Location: ' . $basePath . '/pages/dashboard/instructor.php');
        break;
    case 'student':
    default:
        header('Location: ' . $basePath . '/pages/dashboard/student.php');
        break;
}
exit;