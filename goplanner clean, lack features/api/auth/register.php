<?php
// api/auth/register.php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/register.php');
}

$studentId = trim($_POST['student_id'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$suffix = trim($_POST['suffix'] ?? '');
$email = trim($_POST['email'] ?? '');
$collegeId = (int)($_POST['college_id'] ?? 0);
$programId = (int)($_POST['program_id'] ?? 0);
$yearLevel = (int)($_POST['year_level'] ?? 0);
$section = trim($_POST['section'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate
$errors = [];

if (empty($studentId) || !preg_match('/^\d{2}-[1-5]-\d{4}$/', $studentId)) {
    $errors[] = 'Invalid Student ID format (YY-Y-NNNN).';
}
if (empty($firstName)) $errors[] = 'First name is required.';
if (empty($lastName)) $errors[] = 'Last name is required.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
if ($collegeId <= 0) $errors[] = 'Please select a college.';
if ($programId <= 0) $errors[] = 'Please select a program.';
if ($yearLevel < 1 || $yearLevel > 5) $errors[] = 'Please select a year level.';
if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';

if (!empty($errors)) {
    $_SESSION['reg_errors'] = $errors;
    $_SESSION['reg_old'] = $_POST;
    redirect('/pages/register.php');
}

try {
    $db = db();

    // Check duplicate student_id
    $stmt = $db->prepare("SELECT id FROM users WHERE student_id = ?");
    $stmt->execute([$studentId]);
    if ($stmt->fetch()) {
        $_SESSION['reg_errors'] = ['This Student ID is already registered.'];
        $_SESSION['reg_old'] = $_POST;
        redirect('/pages/register.php');
    }

    // Check duplicate email
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['reg_errors'] = ['This email is already registered.'];
        $_SESSION['reg_old'] = $_POST;
        redirect('/pages/register.php');
    }

    // Insert
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("
        INSERT INTO users (student_id, email, password_hash, first_name, last_name, middle_name, suffix,
                           role, college_id, program_id, year_level, section, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'student', ?, ?, ?, ?, 'pending', NOW(), NOW())
    ");
    $stmt->execute([
        $studentId, $email, $passwordHash, $firstName, $lastName,
        $middleName ?: null, $suffix ?: null,
        $collegeId, $programId, $yearLevel ?: null, $section ?: null
    ]);

    unset($_SESSION['reg_errors'], $_SESSION['reg_old']);
    redirect('/pages/login.php?registered=1');

} catch (Exception $e) {
    $_SESSION['reg_errors'] = ['Registration failed. Please try again.'];
    $_SESSION['reg_old'] = $_POST;
    redirect('/pages/register.php');
}