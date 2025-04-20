<?php
session_start([
    'use_cookies' => 1,         // Enable cookies
    'use_only_cookies' => 0,    // Allow session ID in URL
    'use_trans_sid' => 1        // Automatically append session ID to URLs
]);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'user_authentication');
define('DB_USER', 'root');
define('DB_PASS', '');

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getDBConnection() {
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("SET NAMES utf8");
        return $conn;
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>