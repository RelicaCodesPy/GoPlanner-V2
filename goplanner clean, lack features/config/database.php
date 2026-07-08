<?php
// config/database.php
// DEBESMSCAT GoPlanner V2 - Database Connection

class Database {
    private static ?PDO $instance = null;

    private const HOST = 'localhost';
    private const DBNAME = 'goplanner_db';
    private const USERNAME = 'root';
    private const PASSWORD = '';
    private const CHARSET = 'utf8mb4';

    private function __construct() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::HOST,
                self::DBNAME,
                self::CHARSET
            );

            try {
                self::$instance = new PDO($dsn, self::USERNAME, self::PASSWORD, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND  => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                die('Database connection error. Please check your XAMPP MySQL service.');
            }
        }
        return self::$instance;
    }
}

function db(): PDO {
    return Database::getInstance();
}