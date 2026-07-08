<?php

/**
 * Checks if the logged-in user has one of the allowed roles.
 * Redirects to an unauthorized page if not.
 *
 * @param array $allowedRoles  e.g. ['superadmin', 'admin']
 */
function requirePermission(array $allowedRoles): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /goplanner/pages/login.php');
        exit;
    }

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        http_response_code(403);
        echo "Access Denied. You do not have permission to view this page.";
        exit;
    }
}