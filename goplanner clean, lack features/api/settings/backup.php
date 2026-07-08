<?php
// api/settings/backup.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/pages/settings/backup.php'); }
if (!verifyCSRF($_POST['csrf_token'] ?? '')) { setFlash('error', 'Invalid request.'); redirect('/pages/settings/backup.php'); }

$backupDir = __DIR__ . '/../../backups/';
if (!is_dir($backupDir)) { @mkdir($backupDir, 0755, true); }

$filename = 'goplanner_backup_' . date('Y-m-d_His') . '.sql';
$filepath = $backupDir . $filename;

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$name = 'goplanner_db';

$cmd = "mysqldump --host={$host} --user={$user}";
if ($pass) $cmd .= " --password={$pass}";
$cmd .= " {$name} > " . escapeshellarg($filepath);

exec($cmd, $output, $returnCode);

if ($returnCode === 0 && file_exists($filepath)) {
    setFlash('success', 'Backup created: ' . $filename);
} else {
    setFlash('error', 'Backup failed. Make sure mysqldump is in your system PATH. You can also backup via phpMyAdmin.');
}

redirect('/pages/settings/backup.php');