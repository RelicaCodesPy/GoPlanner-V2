<?php
// api/accounts/reject.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/accounts/pending.php');
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request. Please try again.');
    redirect('/pages/accounts/pending.php');
}

$userId = (int)($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    setFlash('error', 'Invalid account ID.');
    redirect('/pages/accounts/pending.php');
}

try {
    $db = db();

    $stmt = $db->prepare('SELECT id, first_name, last_name, status FROM users WHERE id = ? AND is_archived = 0');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        setFlash('error', 'Account not found.');
        redirect('/pages/accounts/pending.php');
    }

    // Reject — set status to rejected (keep the record for reference)
    $stmt = $db->prepare('UPDATE users SET status = "rejected" WHERE id = ?');
    $stmt->execute([$userId]);

    setFlash('success', 'Account rejected: ' . $target['first_name'] . ' ' . $target['last_name']);
    redirect('/pages/accounts/pending.php');

} catch (Exception $e) {
    setFlash('error', 'Failed to reject account. Please try again.');
    redirect('/pages/accounts/pending.php');
}