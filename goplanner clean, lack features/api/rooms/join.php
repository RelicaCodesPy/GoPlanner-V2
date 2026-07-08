<?php
// api/rooms/join.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/pages/rooms/index.php'); }
if (!verifyCSRF($_POST['csrf_token'] ?? '')) { redirect('/pages/rooms/index.php'); }

$roomId = (int)($_POST['room_id'] ?? 0);
if ($roomId <= 0) { redirect('/pages/rooms/index.php'); }

try {
    $db = db();

    $stmt = $db->prepare("SELECT id FROM room_members WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$roomId, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        redirect('/pages/rooms/view.php?id=' . $roomId);
    }

    $stmt = $db->prepare("SELECT id FROM room_blocked_users WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$roomId, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        setFlash('error', 'You have been blocked from this room.');
        redirect('/pages/rooms/index.php');
    }

    $stmt = $db->prepare("INSERT INTO room_members (room_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
    $stmt->execute([$roomId, $_SESSION['user_id']]);

} catch (Exception $e) {}

redirect('/pages/rooms/view.php?id=' . $roomId);