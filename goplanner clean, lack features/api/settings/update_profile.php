<?php
// api/settings/update_profile.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/pages/settings/profile.php'); }
if (!verifyCSRF($_POST['csrf_token'] ?? '')) { setFlash('error', 'Invalid request.'); redirect('/pages/settings/profile.php'); }

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$suffix = trim($_POST['suffix'] ?? '');

if (empty($firstName) || empty($lastName)) {
    setFlash('error', 'First name and last name are required.');
    redirect('/pages/settings/profile.php');
}

try {
    $db = db();
    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, middle_name = ?, suffix = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$firstName, $lastName, $middleName ?: null, $suffix ?: null, $_SESSION['user_id']]);

    setFlash('success', 'Profile updated successfully.');
} catch (Exception $e) {
    setFlash('error', 'Failed to update profile.');
}

redirect('/pages/settings/profile.php');