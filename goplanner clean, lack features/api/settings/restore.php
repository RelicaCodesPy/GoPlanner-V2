<?php
// api/settings/restore.php — Download backup file

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin']);

$file = $_GET['file'] ?? '';
$backupDir = __DIR__ . '/../../backups/';
$filepath = $backupDir . basename($file);

if (empty($file) || !file_exists($filepath) || pathinfo($filepath, PATHINFO_EXTENSION) !== 'sql') {
    setFlash('error', 'Invalid backup file.');
    redirect('/pages/settings/backup.php');
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;