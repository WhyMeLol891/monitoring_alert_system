<?php
/**
 * Website Management Page
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();

// Handle POST actions: Delete, Toggle Enable, Instant Single Check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $siteId = (int)($_POST['site_id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash_message('danger', 'Invalid or expired session token.');
        redirect('admin/websites.php');
    }

    if ($action === 'delete' && $siteId > 0) {
        $stmt = $pdo->prepare("DELETE FROM websites WHERE id = ?");
        $stmt->execute([$siteId]);
        set_flash_message('success', 'Website removed successfully.');
        redirect('admin/websites.php');
    } elseif ($action === 'toggle_enabled' && $siteId > 0) {
        $stmt = $pdo->prepare("UPDATE websites SET enabled = IF(enabled = 1, 0, 1) WHERE id = ?");
        $stmt->execute([$siteId]);
        set_flash_message('success', 'Monitoring status updated.');
        redirect('admin/websites.php');
    } elseif ($action === 'check_now' && $siteId > 0) {
        require_once __DIR__ . '/../includes/telegram.php';
        $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
        $stmt->execute([$siteId]);
        $website = $stmt->fetch();

        if ($website) {
            $slowThreshold = (int)$website['slow_threshold'];
            $prevStatus = $website['current_status'];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $website['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $startTime = microtime(true);
            $response = curl_exec($ch);
            $endTime = microtime(true);
            
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $totalTimeSec = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);

            $responseTimeMs = (int)round(($totalTimeSec > 0 ? $totalTimeSec : ($endTime - $startTime)) * 1000);
            if ($responseTimeMs <= 0) $responseTimeMs = 1;

            $errorMessage = null;
            if ($curlErrno !== 0 || $httpCode === 0 || $httpCode >= 500) {
                $newStatus = 'DOWN';
                $errorMessage = !empty($curlError) ? $curlError : "Server responded with HTTP {$httpCode}";
            } elseif ($responseTimeMs >= $slowThreshold) {
                $newStatus = 'SLOW';
            } else {
                $newStatus = 'UP';
            }

            // Insert log
            $logStmt = $pdo->prepare("INSERT INTO monitoring_logs (website_id, status, response_time, http_status_code, error_message, checked_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $logStmt->execute([$siteId, $newStatus, $responseTimeMs, $httpCode ?: null, $errorMessage]);

            // Handle transition
            $statusChanged = ($prevStatus !== $newStatus && $prevStatus !== 'PENDING');
            if ($statusChanged) {
                $timeNow = format_datetime(date('Y-m-d H:i:s'));
                if ($newStatus === 'DOWN') {
                    $pdo->prepare("INSERT INTO incidents (website_id, previous_status, current_status, response_time, created_at) VALUES (?, ?, 'DOWN', ?, NOW())")->execute([$siteId, $prevStatus, $responseTimeMs]);
                    send_telegram_down_alert($website, $timeNow, $errorMessage);
                } elseif ($prevStatus === 'DOWN' && $newStatus === 'UP') {
                    $pdo->prepare("UPDATE incidents SET resolved_at = NOW() WHERE website_id = ? AND resolved_at IS NULL ORDER BY created_at DESC LIMIT 1")->execute([$siteId]);
                    send_telegram_recovery_alert($website, $responseTimeMs, $timeNow);
                } elseif ($prevStatus === 'UP' && $newStatus === 'SLOW') {
                    send_telegram_slow_alert($website, $responseTimeMs, $slowThreshold, $timeNow);
                }
            }

            // Update website
            $pdo->prepare("UPDATE websites SET current_status = ?, last_checked = NOW(), response_time = ?, http_status_code = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newStatus, $responseTimeMs, $httpCode ?: null, $siteId]);

            set_flash_message('success', "Website checked: Status is {$newStatus} (" . format_ms($responseTimeMs) . ", HTTP {$httpCode}).");
        }
        redirect('admin/websites.php');
    }
}

// Fetch all websites
$stmt = $pdo->query("SELECT * FROM websites ORDER BY id DESC");
$websites = $stmt->fetchAll();

$pageTitle = "Manage Websites - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Monitored Websites</h1>
        <p class="page-subtitle">Configure URLs, intervals, response thresholds, and monitoring state</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/admin/website-add.php" class="btn btn-primary">
            ➕ Add Website
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Website Name & URL</th>
                        <th>Interval</th>
                        <th>Threshold</th>
                        <th>Last Check</th>
                        <th>Response</th>
                        <th>Monitoring</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($websites)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 36px; color: var(--neutral-500);">
                                No websites configured yet. Click <strong>Add Website</strong> to get started!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($websites as $site): ?>
                            <tr>
                                <td><?= get_status_badge($site['current_status']) ?></td>
                                <td>
                                    <strong><?= e($site['name']) ?></strong>
                                    <div style="font-size: 0.82rem; color: var(--neutral-500); margin-top: 2px;">
                                        <a href="<?= e($site['url']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--neutral-500);">
                                            <?= e($site['url']) ?> &#8599;
                                        </a>
                                    </div>
                                </td>
                                <td><?= (int)$site['monitoring_interval'] ?> min</td>
                                <td><?= format_ms((int)$site['slow_threshold']) ?></td>
                                <td>
                                    <span title="<?= format_datetime($site['last_checked']) ?>">
                                        <?= time_ago($site['last_checked']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= format_ms($site['response_time']) ?></strong>
                                    <?php if ($site['http_status_code']): ?>
                                        <small style="color: var(--neutral-400);"> (<?= e((string)$site['http_status_code']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="<?= BASE_URL ?>/admin/websites.php" method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_enabled">
                                        <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $site['enabled'] ? 'btn-success' : 'btn-secondary' ?>" style="font-size: 0.75rem;">
                                            <?= $site['enabled'] ? 'Active' : 'Paused' ?>
                                        </button>
                                    </form>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <!-- Quick Check -->
                                        <form action="<?= BASE_URL ?>/admin/websites.php" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="check_now">
                                            <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Check now">
                                                🔄 Check
                                            </button>
                                        </form>

                                        <!-- Edit -->
                                        <a href="<?= BASE_URL ?>/admin/website-edit.php?id=<?= $site['id'] ?>" class="btn btn-secondary btn-sm" title="Edit website">
                                            ✏️ Edit
                                        </a>

                                        <!-- Delete -->
                                        <form action="<?= BASE_URL ?>/admin/websites.php" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Are you sure you want to delete <?= e($site['name']) ?>? All logs and incidents will be removed." title="Delete website">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
