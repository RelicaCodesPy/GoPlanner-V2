<?php
// index.php — GoPlanner Entry Point

require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    redirectToDashboard();
} else {
    header('Location: ' . APP_BASE_PATH . '/pages/login.php');
}
exit;