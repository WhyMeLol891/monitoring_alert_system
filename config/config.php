<?php
/**
 * Application Configuration
 * Compatible with PHP 8+, XAMPP, and cPanel
 */

// Define Root Paths First
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// Load Environment (.env) Variables
require_once INCLUDES_PATH . '/env.php';
load_env(ROOT_PATH . '/.env');

// Strict error reporting for development, logs errors
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Timezone configuration (default: Asia/Kuala_Lumpur)
$timezone = env('APP_TIMEZONE', 'Asia/Kuala_Lumpur');
date_default_timezone_set($timezone);

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

// Database Credentials from .env (with safe defaults)
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (string)env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'db_website_monitor'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Application Details
define('APP_NAME', env('APP_NAME', 'Website Monitoring System'));
define('APP_VERSION', env('APP_VERSION', '1.0.0'));
define('APP_TIMEZONE', $timezone);

// Cron security key (for HTTP trigger: /cron/monitor.php?key=your_secret_key)
define('CRON_SECRET_KEY', env('CRON_SECRET_KEY', 'monitor_cron_secret_2026'));

// Base URL: Check .env first, fallback to dynamic autodetection
if (!defined('BASE_URL')) {
    $envBaseUrl = env('BASE_URL');
    if (!empty($envBaseUrl)) {
        define('BASE_URL', rtrim($envBaseUrl, '/'));
    } else {
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
}
