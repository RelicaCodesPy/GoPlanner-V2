<?php
// api/notifications/mark_read.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$db = db();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Mark single or all as read
$notifId = (int)($_POST['id'] ?? 0);

try {
    if ($notifId > 0) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
    } else {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
    }
} catch (Exception $e) {}

if ($notifId === 0) {
    setFlash('success', 'All notifications marked as read.');
    redirect('/pages/notifications/index.php');
}

http_response_code(200);
echo json_encode(['success' => true]);