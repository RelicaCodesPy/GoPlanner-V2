<?php
// api/rooms/store.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin', 'instructor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/rooms/index.php');
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request.');
    redirect('/pages/rooms/create.php');
}

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if (empty($name)) {
    setFlash('error', 'Room name is required.');
    redirect('/pages/rooms/create.php');
}

try {
    $db = db();

    $stmt = $db->prepare("INSERT INTO rooms (name, description, created_by, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$name, $description ?: null, $_SESSION['user_id']]);

    $roomId = $db->lastInsertId();

    // Auto-join the creator
    $stmt = $db->prepare("INSERT INTO room_members (room_id, user_id, role, joined_at) VALUES (?, ?, 'admin', NOW())");
    $stmt->execute([$roomId, $_SESSION['user_id']]);

    setFlash('success', 'Room created: ' . $name);
    redirect('/pages/rooms/view.php?id=' . $roomId);

} catch (Exception $e) {
    setFlash('error', 'Failed to create room. Please try again.');
    redirect('/pages/rooms/create.php');
}