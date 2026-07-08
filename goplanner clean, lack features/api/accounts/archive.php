<?php
// api/accounts/archive.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/accounts/manage.php');
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request.');
    redirect('/pages/accounts/manage.php');
}

$userId = (int)($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    setFlash('error', 'Invalid account ID.');
    redirect('/pages/accounts/manage.php');
}

// Prevent archiving yourself
if ($userId === $_SESSION['user_id']) {
    setFlash('error', 'You cannot archive your own account.');
    redirect('/pages/accounts/manage.php');
}

try {
    $db = db();

    $stmt = $db->prepare('SELECT id, first_name, last_name FROM users WHERE id = ? AND is_archived = 0');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        setFlash('error', 'Account not found or already archived.');
        redirect('/pages/accounts/manage.php');
    }

    $stmt = $db->prepare('UPDATE users SET is_archived = 1, archived_at = NOW() WHERE id = ?');
    $stmt->execute([$userId]);

    setFlash('success', 'Account archived: ' . $target['first_name'] . ' ' . $target['last_name']);
    redirect('/pages/accounts/manage.php');

} catch (Exception $e) {
    setFlash('error', 'Failed to archive account.');
    redirect('/pages/accounts/manage.php');
}