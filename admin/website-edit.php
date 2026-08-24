<?php
/**
 * Edit Monitored Website
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();
$siteId = (int)($_GET['id'] ?? 0);

if ($siteId <= 0) {
    set_flash_message('danger', 'Invalid website ID.');
    redirect('admin/websites.php');
}

$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
$stmt->execute([$siteId]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('danger', 'Website not found.');
    redirect('admin/websites.php');
}

$error = '';
$name = $website['name'];
$url = $website['url'];
$interval = (int)$website['monitoring_interval'];
$slowThreshold = (int)$website['slow_threshold'];
$enabled = (int)$website['enabled'];

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
                UPDATE websites 
                SET name = ?, 
                    url = ?, 
                    monitoring_interval = ?, 
                    slow_threshold = ?, 
                    enabled = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$name, $url, $interval, $slowThreshold, $enabled, $siteId]);

            set_flash_message('success', "Website '{$name}' updated successfully.");
            redirect('admin/websites.php');
        }
    }
}

$pageTitle = "Edit Website - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Website: <?= e($website['name']) ?></h1>
        <p class="page-subtitle">Update monitoring intervals, URLs, and alerting thresholds</p>
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
            <h2 class="card-title">Website Configuration</h2>
            <div><?= get_status_badge($website['current_status']) ?></div>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <span class="alert-icon">⚠️</span>
                    <div class="alert-text"><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/admin/website-edit.php?id=<?= $siteId ?>" method="POST">
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
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label class="form-switch">
                        <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                        <span style="font-weight: 600;">Enable Monitoring</span>
                    </label>
                </div>

                <div style="margin-top: 28px; display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <span>Save Changes &rarr;</span>
                    </button>
                    <a href="<?= BASE_URL ?>/admin/websites.php" class="btn btn-secondary btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
