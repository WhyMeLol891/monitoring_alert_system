<?php
/**
 * Application Configuration
 * Compatible with PHP 8+, XAMPP, and cPanel
 */

// Strict error reporting for development, logs errors
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Timezone requirement: Asia/Kuala_Lumpur (Malaysia Time)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session cookie parameters
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path'     => '/',
        'domain'   => $cookieParams['domain'],
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Database Credentials (Configure as per your XAMPP or cPanel setup)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'db_website_monitor');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Details
define('APP_NAME', 'Website Monitoring System');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');

// Cron security key (Optional for HTTP trigger: /cron/monitor.php?key=your_secret_key)
define('CRON_SECRET_KEY', 'monitor_cron_secret_2026');

// Root Paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// Dynamic Base URL Detection (Works in root or any subfolder)
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine relative directory from DocumentRoot
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = str_replace('\\', '/', ROOT_PATH);
    $subDir = str_replace($docRoot, '', $appRoot);
    $subDir = '/' . ltrim($subDir, '/');
    if ($subDir === '/') {
        $subDir = '';
    }
    
    define('BASE_URL', rtrim($protocol . $host . $subDir, '/'));
}
