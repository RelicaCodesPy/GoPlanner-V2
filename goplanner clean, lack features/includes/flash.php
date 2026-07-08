<?php
// includes/flash.php
// DEBESMSCAT GoPlanner V2 — Flash Messages

// Safe sanitize fallback in case auth.php hasn't loaded yet
if (!function_exists('sanitize')) {
    function sanitize($value): string {
        return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// ============================================================
// SET FLASH MESSAGE
// ============================================================
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function setFlashSuccess(string $message): void {
    setFlash('success', $message);
}

function setFlashError(string $message): void {
    setFlash('error', $message);
}

function setFlashWarning(string $message): void {
    setFlash('warning', $message);
}

function setFlashInfo(string $message): void {
    setFlash('info', $message);
}

// ============================================================
// RENDER FLASH MESSAGE
// ============================================================
function renderFlash(): string {
    $html = '';

    // Check $_SESSION['flash']
    if (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] ?? 'info';
        $message = $_SESSION['flash']['message'] ?? '';

        if (!empty($message)) {
            $classes = [
                'success' => 'flash-success',
                'error' => 'flash-error',
                'warning' => 'flash-warning',
                'info' => 'flash-info'
            ];
            $class = $classes[$type] ?? 'flash-info';

            $html .= '<div class="flash-message ' . $class . '">' . sanitize($message) . '</div>';
        }

        unset($_SESSION['flash']);
    }

    // Check $_SESSION['flash_success']
    if (isset($_SESSION['flash_success']) && !empty($_SESSION['flash_success'])) {
        $html .= '<div class="flash-message flash-success">' . sanitize($_SESSION['flash_success']) . '</div>';
        unset($_SESSION['flash_success']);
    }

    // Check $_SESSION['flash_error']
    if (isset($_SESSION['flash_error']) && !empty($_SESSION['flash_error'])) {
        $html .= '<div class="flash-message flash-error">' . sanitize($_SESSION['flash_error']) . '</div>';
        unset($_SESSION['flash_error']);
    }

    // Check $_SESSION['flash_warning']
    if (isset($_SESSION['flash_warning']) && !empty($_SESSION['flash_warning'])) {
        $html .= '<div class="flash-message flash-warning">' . sanitize($_SESSION['flash_warning']) . '</div>';
        unset($_SESSION['flash_warning']);
    }

    // Check $_SESSION['flash_info']
    if (isset($_SESSION['flash_info']) && !empty($_SESSION['flash_info'])) {
        $html .= '<div class="flash-message flash-info">' . sanitize($_SESSION['flash_info']) . '</div>';
        unset($_SESSION['flash_info']);
    }

    return $html;
}

// ============================================================
// CHECK IF FLASH EXISTS
// ============================================================
function hasFlash(): bool {
    return isset($_SESSION['flash'])
        || isset($_SESSION['flash_success'])
        || isset($_SESSION['flash_error'])
        || isset($_SESSION['flash_warning'])
        || isset($_SESSION['flash_info']);
}