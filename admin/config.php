<?php
/**
 * Admin Configuration File
 * 
 * Loads the root config (DB connection + loadEnv function),
 * then loads admin-specific environment variables from admin/.env
 */

// Load root config (provides $pdo and loadEnv function)
require_once __DIR__ . '/../config.php';

// Load admin-specific environment variables
$adminEnvPath = __DIR__ . '/.env';
if (file_exists($adminEnvPath)) {
    loadEnv($adminEnvPath);
} else {
    die("Admin .env file not found.");
}

// Fetch admin credentials from admin .env
$admin_user = $_ENV['ADMIN_USER'] ?? 'admin';
$admin_pass = $_ENV['ADMIN_PASS'] ?? 'admin';

// Admin panel settings
$admin_session_name = $_ENV['ADMIN_SESSION_NAME'] ?? 'admin_logged_in';
$admin_title = $_ENV['ADMIN_TITLE'] ?? 'Admin Control';
?>
