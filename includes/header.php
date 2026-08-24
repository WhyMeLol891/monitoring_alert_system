<?php
/**
 * Global Header Component
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$isAdmin = is_logged_in();
$adminUser = current_admin();
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-nav">
            <a href="<?= BASE_URL ?>/status.php" class="brand-logo">
                <span class="brand-icon">⚡</span>
                <span><?= e(APP_NAME) ?></span>
            </a>

            <button class="mobile-toggle" aria-label="Toggle navigation">&#9776;</button>

            <ul class="nav-links">
                <?php if ($isAdmin): ?>
                    <li><a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">📊 Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/websites.php" class="nav-link <?= in_array($currentPage, ['websites.php', 'website-add.php', 'website-edit.php']) ? 'active' : '' ?>">🌐 Websites</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/logs.php" class="nav-link <?= ($currentPage === 'logs.php') ? 'active' : '' ?>">📋 Logs</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/incidents.php" class="nav-link <?= ($currentPage === 'incidents.php') ? 'active' : '' ?>">⚠️ Incidents</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/settings.php" class="nav-link <?= ($currentPage === 'settings.php') ? 'active' : '' ?>">⚙️ Settings</a></li>
                    <li><a href="<?= BASE_URL ?>/status.php" target="_blank" class="nav-link">👁️ Public Status</a></li>
                    <li><a href="<?= BASE_URL ?>/logout.php" class="btn-nav-action" data-confirm="Are you sure you want to logout?">🚪 Logout (<?= e($adminUser['username'] ?? 'Admin') ?>)</a></li>
                <?php else: ?>
                    <li><a href="<?= BASE_URL ?>/status.php" class="nav-link <?= ($currentPage === 'status.php') ? 'active' : '' ?>">🟢 Live Status</a></li>
                    <li><a href="<?= BASE_URL ?>/login.php" class="btn-nav-action">🔐 Admin Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <?= render_flash_messages() ?>
