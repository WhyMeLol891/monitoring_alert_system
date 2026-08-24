<?php
/**
 * Add New Monitored Website
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();
$error = '';

$name = '';
$url = '';
$interval = 5;
$slowThreshold = 3000;
$enabled = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security session expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $interval = (int)($_POST['monitoring_interval'] ?? 5);
        $slowThreshold = (int)($_POST['slow_threshold'] ?? 3000);
        $enabled = isset($_POST['enabled']) ? 1 : 0;

        if (empty($name)) {
            $error = 'Please provide a website name.';
        } elseif (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'Please enter a valid URL (including http:// or https://).';
        } elseif ($interval < 1) {
            $error = 'Monitoring interval must be at least 1 minute.';
        } elseif ($slowThreshold < 100) {
            $error = 'Slow threshold must be at least 100 ms.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO websites (name, url, monitoring_interval, slow_threshold, enabled, current_status, created_at)
                VALUES (?, ?, ?, ?, ?, 'PENDING', NOW())
            ");
            $stmt->execute([$name, $url, $interval, $slowThreshold, $enabled]);

            set_flash_message('success', "Website '{$name}' added successfully. Initial check will occur on the next cron run.");
            redirect('admin/websites.php');
        }
    }
}

$pageTitle = "Add Website - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Add Website</h1>
        <p class="page-subtitle">Configure a new endpoint for uptime and latency tracking</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/admin/websites.php" class="btn btn-secondary">
            &larr; Back to Websites
        </a>
    </div>
</div>

<div class="container-narrow">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Website Details</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <span class="alert-icon">⚠️</span>
                    <div class="alert-text"><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/admin/website-add.php" method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="name" class="form-label">Website Name <span class="required">*</span></label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           class="form-control" 
                           value="<?= e($name) ?>" 
                           required 
                           placeholder="e.g. My Online Store">
                    <div class="form-text">A friendly, recognizable name for this service.</div>
                </div>

                <div class="form-group">
                    <label for="url" class="form-label">Website URL <span class="required">*</span></label>
                    <input type="url" 
                           id="url" 
                           name="url" 
                           class="form-control" 
                           value="<?= e($url) ?>" 
                           required 
                           placeholder="https://example.com">
                    <div class="form-text">Full URL including <code>https://</code> or <code>http://</code>.</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label for="monitoring_interval" class="form-label">Monitoring Interval <span class="required">*</span></label>
                        <select id="monitoring_interval" name="monitoring_interval" class="form-control">
                            <option value="1" <?= $interval == 1 ? 'selected' : '' ?>>Every 1 Minute</option>
                            <option value="2" <?= $interval == 2 ? 'selected' : '' ?>>Every 2 Minutes</option>
                            <option value="5" <?= $interval == 5 ? 'selected' : '' ?>>Every 5 Minutes (Standard)</option>
                            <option value="10" <?= $interval == 10 ? 'selected' : '' ?>>Every 10 Minutes</option>
                            <option value="15" <?= $interval == 15 ? 'selected' : '' ?>>Every 15 Minutes</option>
                            <option value="30" <?= $interval == 30 ? 'selected' : '' ?>>Every 30 Minutes</option>
                            <option value="60" <?= $interval == 60 ? 'selected' : '' ?>>Every 60 Minutes (Hourly)</option>
                        </select>
                        <div class="form-text">How often the cron will ping this website.</div>
                    </div>

                    <div class="form-group">
                        <label for="slow_threshold" class="form-label">Slow Response Threshold (ms) <span class="required">*</span></label>
                        <input type="number" 
                               id="slow_threshold" 
                               name="slow_threshold" 
                               class="form-control" 
                               value="<?= $slowThreshold ?>" 
                               min="100" 
                               step="100" 
                               required>
                        <div class="form-text">Response time above this triggers a SLOW alert (e.g. 3000 ms).</div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label class="form-switch">
                        <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                        <span style="font-weight: 600;">Enable Monitoring Immediately</span>
                    </label>
                </div>

                <div style="margin-top: 28px; display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <span>Save and Start Monitoring &rarr;</span>
                    </button>
                    <a href="<?= BASE_URL ?>/admin/websites.php" class="btn btn-secondary btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
