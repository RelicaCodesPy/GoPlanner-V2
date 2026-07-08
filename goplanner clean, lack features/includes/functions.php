<?php
// includes/functions.php
// DEBESMSCAT GoPlanner V2 - Utility Functions

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = '127.0.0.1';
        $name = 'goplanner_db';
        $user = 'root';
        $pass = '';

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    return $pdo;
}

function timeAgo(string $datetime): string
{
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->getTimestamp() - $then->getTimestamp();

    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return $then->format('M d, Y');
}

function redirect(string $path): void
{
    header('Location: ' . APP_BASE_PATH . $path);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function generateCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirectToDashboard(): void
{
    $basePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/goplanner';

    switch ($_SESSION['role'] ?? '') {
        case 'super_admin':
            header('Location: ' . $basePath . '/pages/dashboard/superadmin.php');
            break;
        case 'admin':
            header('Location: ' . $basePath . '/pages/dashboard/admin.php');
            break;
        case 'instructor':
            header('Location: ' . $basePath . '/pages/dashboard/instructor.php');
            break;
        case 'student':
        default:
            header('Location: ' . $basePath . '/pages/dashboard/student.php');
            break;
    }
    exit;
}

function logActivity(string $action, string $entityType = null, int $entityId = null): void
{
    try {
        $db = db();
        $stmt = $db->prepare('
            INSERT INTO activity_log (user_id, action, entity_type, entity_id, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $entityType,
            $entityId,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Silently fail — logging should never break the app
    }
}