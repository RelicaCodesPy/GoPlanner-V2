<?php
// api/auth/validate_student_id.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$studentId = trim($_POST['student_id'] ?? '');

if (empty($studentId)) {
    echo json_encode(['valid' => false, 'message' => 'Student ID is required.']);
    exit;
}

if (!preg_match('/^\d{2}-[1-5]-\d{4}$/', $studentId)) {
    echo json_encode(['valid' => false, 'message' => 'Invalid format. Use YY-Y-NNNN (e.g., 25-1-0001).']);
    exit;
}

try {
    $db = db();
    $stmt = $db->prepare("SELECT id, status FROM users WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['status'] === 'pending') {
            echo json_encode(['valid' => false, 'message' => 'This ID is pending approval. Please wait.']);
        } elseif ($existing['status'] === 'active') {
            echo json_encode(['valid' => false, 'message' => 'This Student ID is already registered and active.']);
        } else {
            echo json_encode(['valid' => false, 'message' => 'This Student ID is already in use.']);
        }
    } else {
        echo json_encode(['valid' => true, 'message' => 'Student ID is available!']);
    }
} catch (Exception $e) {
    echo json_encode(['valid' => false, 'message' => 'Could not verify. Please try again.']);
}