<?php
// api/settings/change_password.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/pages/settings/profile.php'); }
if (!verifyCSRF($_POST['csrf_token'] ?? '')) { setFlash('error', 'Invalid request.'); redirect('/pages/settings/profile.php'); }

$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($currentPassword) || empty($newPassword)) {
    setFlash('error', 'All password fields are required.');
    redirect('/pages/settings/profile.php');
}

if (strlen($newPassword) < 6) {
    setFlash('error', 'New password must be at least 6 characters.');
    redirect('/pages/settings/profile.php');
}

if ($newPassword !== $confirmPassword) {
    setFlash('error', 'New passwords do not match.');
    redirect('/pages/settings/profile.php');
}

try {
    $db = db();
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        setFlash('error', 'Current password is incorrect.');
        redirect('/pages/settings/profile.php');
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newHash, $_SESSION['user_id']]);

    setFlash('success', 'Password changed successfully.');
} catch (Exception $e) {
    setFlash('error', 'Failed to change password.');
}

redirect('/pages/settings/profile.php');