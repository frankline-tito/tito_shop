<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $pdo->exec("SET time_zone = '+03:00'");
} catch (PDOException $e) {
    http_response_code(500);
    die('<h3>Could not connect to the database.</h3><p>Check that MySQL is running in XAMPP and that '
        . 'config/config.php has the right database name, user and password.</p><p><small>'
        . htmlspecialchars($e->getMessage()) . '</small></p>');
}
