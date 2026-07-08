<?php
// api/announcements/store.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin', 'admin', 'instructor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/announcements/view.php');
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request.');
    redirect('/pages/announcements/create.php');
}

$user = currentUser();
$db = db();
$role = $user['role'];

// Collect input
$id = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$priority = $_POST['priority'] ?? 'normal';
$status = $_POST['status'] ?? 'published';
$isPinned = isset($_POST['is_pinned']) ? 1 : 0;
$collegeId = $_POST['college_id'] !== '' ? (int)$_POST['college_id'] : null;
$programId = $_POST['program_id'] !== '' ? (int)$_POST['program_id'] : null;
$targetYear = $_POST['target_year'] !== '' ? (int)$_POST['target_year'] : null;
$targetSection = trim($_POST['target_section'] ?? '') ?: null;
$eventDate = $_POST['event_date'] ?: null;
$eventTime = $_POST['event_time'] ?: null;
$eventEndDate = $_POST['event_end_date'] ?: null;
$mapAddress = trim($_POST['map_address'] ?? '') ?: null;

// Validate
if (empty($title) || empty($content)) {
    setFlash('error', 'Title and content are required.');
    redirect('/pages/announcements/create.php');
}

if (!in_array($priority, ['low','normal','high','urgent'])) {
    $priority = 'normal';
}
if (!in_array($status, ['draft','published'])) {
    $status = 'published';
}

// Non-super_admin can only post to their own college
if ($role !== 'super_admin') {
    $collegeId = $user['college_id'];
}

try {
    if ($id > 0) {
        // UPDATE
        $stmt = $db->prepare("SELECT id, created_by FROM announcements WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();

        if (!$existing || ($existing['created_by'] != $user['id'] && $role !== 'super_admin')) {
            setFlash('error', 'You cannot edit this announcement.');
            redirect('/pages/announcements/view.php');
        }

        $stmt = $db->prepare("
            UPDATE announcements SET
                title = ?, content = ?, priority = ?, status = ?, is_pinned = ?,
                college_id = ?, program_id = ?, target_year = ?, target_section = ?,
                event_date = ?, event_time = ?, event_end_date = ?, map_address = ?,
                updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $content, $priority, $status, $isPinned,
            $collegeId, $programId, $targetYear, $targetSection,
            $eventDate, $eventTime, $eventEndDate, $mapAddress,
            $user['id'], $id
        ]);

        setFlash('success', 'Announcement updated successfully.');
        redirect('/pages/announcements/view.php?id=' . $id);

    } else {
        // INSERT
        $stmt = $db->prepare("
            INSERT INTO announcements
            (title, content, priority, status, is_pinned, college_id, program_id,
             target_year, target_section, event_date, event_time, event_end_date,
             map_address, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $title, $content, $priority, $status, $isPinned,
            $collegeId, $programId, $targetYear, $targetSection,
            $eventDate, $eventTime, $eventEndDate, $mapAddress,
            $user['id']
        ]);

        $newId = $db->lastInsertId();
        setFlash('success', 'Announcement published successfully.');
        redirect('/pages/announcements/view.php?id=' . $newId);
    }

    // Handle file uploads
if (!empty($_FILES['files']['name'][0])) {
    $uploadDir = __DIR__ . '/../../uploads/announcements/';
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];

    foreach ($_FILES['files']['name'] as $i => $name) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $tmpName = $_FILES['files']['tmp_name'][$i];
        $fileSize = $_FILES['files']['size'][$i];
        $fileType = $_FILES['files']['type'][$i];

        if ($fileSize > MAX_FILE_SIZE) continue;
        if (!in_array($fileType, $allowedTypes)) continue;

        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $safeName = uniqid('ann_') . '.' . $ext;
        $destPath = $uploadDir . $safeName;

        if (move_uploaded_file($tmpName, $destPath)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO announcement_files (announcement_id, file_name, original_name, file_size, file_type, uploaded_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$newId ?? $id, $safeName, $name, $fileSize, $fileType, $user['id']]);
            } catch (Exception $e) {}
        }
    }
}

} catch (Exception $e) {
    setFlash('error', 'Failed to save announcement. Please try again.');
    redirect('/pages/announcements/create.php');
}