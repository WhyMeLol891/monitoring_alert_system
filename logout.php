<?php
/**
 * Admin Logout Page
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

logout_admin();
set_flash_message('info', 'You have been successfully logged out.');
redirect('login.php');
