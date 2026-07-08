<?php
// api/accounts/restore.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/accounts/archived.php');
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request.');
    redirect('/pages/accounts/archived.php');
}

$userId = (int)($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    setFlash('error', 'Invalid account ID.');
    redirect('/pages/accounts/archived.php');
}

try {
    $db = db();

    $stmt = $db->prepare('SELECT id, first_name, last_name FROM users WHERE id = ? AND is_archived = 1');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        setFlash('error', 'Account not found or not archived.');
        redirect('/pages/accounts/archived.php');
    }

    $stmt = $db->prepare('UPDATE users SET is_archived = 0, archived_at = NULL WHERE id = ?');
    $stmt->execute([$userId]);

    setFlash('success', 'Account restored: ' . $target['first_name'] . ' ' . $target['last_name']);
    redirect('/pages/accounts/archived.php');

} catch (Exception $e) {
    setFlash('error', 'Failed to restore account.');
    redirect('/pages/accounts/archived.php');
}