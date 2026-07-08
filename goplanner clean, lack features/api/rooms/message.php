<?php
// api/rooms/message.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/pages/rooms/index.php'); }
if (!verifyCSRF($_POST['csrf_token'] ?? '')) { redirect('/pages/rooms/index.php'); }

$roomId = (int)($_POST['room_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($roomId <= 0 || empty($message)) { redirect('/pages/rooms/index.php'); }

try {
    $db = db();

    // Verify membership
    $stmt = $db->prepare("SELECT id FROM room_members WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$roomId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) { redirect('/pages/rooms/view.php?id=' . $roomId); }

    $stmt = $db->prepare("INSERT INTO room_messages (room_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$roomId, $_SESSION['user_id'], $message]);

} catch (Exception $e) {}

redirect('/pages/rooms/view.php?id=' . $roomId);