<?php
/**
 * Admin Dashboard
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();

// Handle manual trigger all check if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'trigger_all') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        // Run monitor cron inline
        require_once __DIR__ . '/../includes/telegram.php';
        $stmt = $pdo->query("SELECT * FROM websites WHERE enabled = 1");
        $websites = $stmt->fetchAll();
        $checkedCount = 0;
        
        foreach ($websites as $website) {
            $siteId = (int)$website['id'];
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
                    $incStmt = $pdo->prepare("INSERT INTO incidents (website_id, previous_status, current_status, response_time, created_at) VALUES (?, ?, 'DOWN', ?, NOW())");
                    $incStmt->execute([$siteId, $prevStatus, $responseTimeMs]);
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

            $checkedCount++;
        }

        set_flash_message('success', "Successfully checked {$checkedCount} website(s).");
        redirect('admin/dashboard.php');
    }
}

// 1. Stats Counters
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_websites,
        SUM(CASE WHEN enabled = 1 AND current_status = 'UP' THEN 1 ELSE 0 END) AS up_websites,
        SUM(CASE WHEN enabled = 1 AND current_status = 'DOWN' THEN 1 ELSE 0 END) AS down_websites,
        SUM(CASE WHEN enabled = 1 AND current_status = 'SLOW' THEN 1 ELSE 0 END) AS slow_websites,
        SUM(CASE WHEN enabled = 0 THEN 1 ELSE 0 END) AS disabled_websites
    FROM websites
");
$stats = $statsStmt->fetch();

$totalCount = (int)($stats['total_websites'] ?? 0);
$upCount = (int)($stats['up_websites'] ?? 0);
$downCount = (int)($stats['down_websites'] ?? 0);
$slowCount = (int)($stats['slow_websites'] ?? 0);
$disabledCount = (int)($stats['disabled_websites'] ?? 0);

// 2. Recent Monitoring Activity (Latest 8 checks)
$recentLogsStmt = $pdo->query("
    SELECT l.*, w.name AS website_name, w.url AS website_url 
    FROM monitoring_logs l
    JOIN websites w ON l.website_id = w.id
    ORDER BY l.checked_at DESC
    LIMIT 8
");
$recentLogs = $recentLogsStmt->fetchAll();

// 3. Recent Incidents / Status Changes (Latest 5)
$recentIncidentsStmt = $pdo->query("
    SELECT i.*, w.name AS website_name, w.url AS website_url 
    FROM incidents i
    JOIN websites w ON i.website_id = w.id
    ORDER BY i.created_at DESC
    LIMIT 5
");
$recentIncidents = $recentIncidentsStmt->fetchAll();

// 4. Telegram Config Status
$telegramStmt = $pdo->query("SELECT * FROM telegram_config WHERE id = 1 LIMIT 1");
$tgConfig = $telegramStmt->fetch();
$tgEnabled = !empty($tgConfig['enabled']) && !empty($tgConfig['bot_token']) && !empty($tgConfig['chat_id']);

$pageTitle = "Admin Dashboard - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard Overview</h1>
        <p class="page-subtitle">Real-time status monitoring, metrics, and alerts</p>
    </div>
    <div class="page-actions">
        <form action="<?= BASE_URL ?>/admin/dashboard.php" method="POST" style="display: inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="trigger_all">
            <button type="submit" class="btn btn-secondary">
                🔄 Check All Websites Now
            </button>
        </form>
        <a href="<?= BASE_URL ?>/admin/website-add.php" class="btn btn-primary">
            ➕ Add Website
        </a>
    </div>
</div>

<!-- KPI Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-total">🌐</div>
        <div class="stat-info">
            <div class="stat-label">Total Websites</div>
            <div class="stat-value"><?= $totalCount ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-up">🟢</div>
        <div class="stat-info">
            <div class="stat-label">Operational (UP)</div>
            <div class="stat-value" style="color: var(--success);"><?= $upCount ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-down">🔴</div>
        <div class="stat-info">
            <div class="stat-label">Outages (DOWN)</div>
            <div class="stat-value" style="color: var(--danger);"><?= $downCount ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-slow">🟡</div>
        <div class="stat-info">
            <div class="stat-label">Slow (Degraded)</div>
            <div class="stat-value" style="color: var(--warning);"><?= $slowCount ?></div>
        </div>
    </div>
</div>

<!-- Telegram Status Banner -->
<?php if (!$tgEnabled): ?>
    <div class="alert alert-warning" style="margin-bottom: 24px;">
        <span class="alert-icon">⚠️</span>
        <div class="alert-text">
            <strong>Telegram Alerts are not configured or inactive.</strong> 
            Configure your Bot Token and Chat ID in <a href="<?= BASE_URL ?>/admin/settings.php" style="text-decoration: underline; font-weight: 600;">Settings</a> to receive instant downtime alerts.
        </div>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
    <!-- Recent Monitoring Logs -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Monitoring Activity</h2>
            <a href="<?= BASE_URL ?>/admin/logs.php" class="btn btn-secondary btn-sm">View All Logs &rarr;</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Website</th>
                            <th>Status</th>
                            <th>Response</th>
                            <th>HTTP Code</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLogs)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--neutral-500); padding: 24px;">
                                    No monitoring logs yet. Run the cron job or click "Check All Websites Now".
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($log['website_name']) ?></strong>
                                    </td>
                                    <td><?= get_status_badge($log['status']) ?></td>
                                    <td><?= format_ms($log['response_time']) ?></td>
                                    <td>
                                        <?php if ($log['http_status_code']): ?>
                                            <code><?= e((string)$log['http_status_code']) ?></code>
                                        <?php else: ?>
                                            <span style="color: var(--neutral-400);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= time_ago($log['checked_at']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Status Changes & Incidents -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Status Changes & Incidents</h2>
            <a href="<?= BASE_URL ?>/admin/incidents.php" class="btn btn-secondary btn-sm">View All Incidents &rarr;</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentIncidents)): ?>
                <div style="text-align: center; padding: 24px; color: var(--neutral-500);">
                    <div style="font-size: 28px; margin-bottom: 6px;">✨</div>
                    <strong>No incidents recorded.</strong>
                    <p style="margin-top: 4px; font-size: 0.85rem;">All websites have remained stable.</p>
                </div>
            <?php else: ?>
                <div class="incident-timeline">
                    <?php foreach ($recentIncidents as $inc): ?>
                        <?php 
                        $isResolved = !empty($inc['resolved_at']);
                        $startTime = strtotime($inc['created_at']);
                        $endTime = $isResolved ? strtotime($inc['resolved_at']) : time();
                        $durationSec = max(0, $endTime - $startTime);
                        ?>
                        <div class="incident-card <?= $isResolved ? 'incident-resolved' : '' ?>" style="padding: 12px 14px;">
                            <div class="incident-card-header" style="margin-bottom: 4px;">
                                <div class="incident-title" style="font-size: 0.92rem;">
                                    <strong><?= e($inc['website_name']) ?></strong> &bull; <?= $isResolved ? 'Recovered' : 'Down' ?>
                                </div>
                                <div>
                                    <?php if ($isResolved): ?>
                                        <span class="badge badge-up" style="font-size: 0.7rem;">Resolved</span>
                                    <?php else: ?>
                                        <span class="badge badge-down" style="font-size: 0.7rem;">Active</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="incident-meta" style="font-size: 0.8rem;">
                                <span>Started: <?= format_datetime($inc['created_at']) ?></span>
                                <span>Duration: <?= format_duration($durationSec) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
