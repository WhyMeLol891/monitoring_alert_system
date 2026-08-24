<?php
/**
 * Public Status Page (UptimeRobot-style 90-Day History)
 * Public access: NO LOGIN REQUIRED.
 * Zero credentials or private configuration exposed.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();
$selectedSiteId = isset($_GET['site']) ? (int)$_GET['site'] : null;

// Overall system status
$systemStatus = get_overall_system_status($pdo);

// If viewing a single website detail
$singleWebsite = null;
$singleWebsiteHistory = null;
$singleWebsiteIncidents = [];

if ($selectedSiteId) {
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND enabled = 1 LIMIT 1");
    $stmt->execute([$selectedSiteId]);
    $singleWebsite = $stmt->fetch();

    if ($singleWebsite) {
        $singleWebsiteHistory = get_90_days_history($pdo, $selectedSiteId);
        
        $incStmt = $pdo->prepare("
            SELECT * FROM incidents 
            WHERE website_id = ? 
            ORDER BY created_at DESC 
            LIMIT 15
        ");
        $incStmt->execute([$selectedSiteId]);
        $singleWebsiteIncidents = $incStmt->fetchAll();
    }
}

// Fetch all enabled websites for the general overview
$stmt = $pdo->query("SELECT * FROM websites WHERE enabled = 1 ORDER BY name ASC");
$allWebsites = $stmt->fetchAll();

// Pre-calculate 90-day history for each website in general view
$websitesWithHistory = [];
foreach ($allWebsites as $site) {
    $historyData = get_90_days_history($pdo, (int)$site['id']);
    $websitesWithHistory[] = [
        'site'    => $site,
        'history' => $historyData
    ];
}

// Fetch recent global incidents for the last 90 days
$recentIncidentsStmt = $pdo->query("
    SELECT i.*, w.name AS website_name, w.url AS website_url 
    FROM incidents i
    JOIN websites w ON i.website_id = w.id
    WHERE w.enabled = 1
    ORDER BY i.created_at DESC
    LIMIT 10
");
$recentIncidents = $recentIncidentsStmt->fetchAll();

$pageTitle = ($singleWebsite ? e($singleWebsite['name']) . " - Status" : "Live Service Status") . " | " . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($singleWebsite): ?>
    <!-- ====================================================================
         Single Website Detail View (/status.php?site=ID)
         ==================================================================== -->
    <div style="margin-bottom: 20px;">
        <a href="<?= BASE_URL ?>/status.php" class="btn btn-secondary btn-sm">
            &larr; Back to All Systems Status
        </a>
    </div>

    <div class="card" style="margin-bottom: 30px;">
        <div class="card-body">
            <div class="site-card-top" style="margin-bottom: 20px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                        <h1 class="page-title" style="margin: 0;"><?= e($singleWebsite['name']) ?></h1>
                        <?= get_status_badge($singleWebsite['current_status']) ?>
                    </div>
                    <div class="site-url">
                        <a href="<?= e($singleWebsite['url']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--neutral-500);">
                            <?= e($singleWebsite['url']) ?> &#8599;
                        </a>
                    </div>
                </div>

                <div class="site-quick-stats">
                    <div class="site-quick-stat-item">
                        <span style="color: var(--neutral-500); display: block; font-size: 0.78rem; text-transform: uppercase;">Response Time</span>
                        <strong><?= format_ms($singleWebsite['response_time']) ?></strong>
                    </div>
                    <div class="site-quick-stat-item">
                        <span style="color: var(--neutral-500); display: block; font-size: 0.78rem; text-transform: uppercase;">90-Day Uptime</span>
                        <strong style="color: var(--success-dark);"><?= e($singleWebsiteHistory['overall_uptime']) ?></strong>
                    </div>
                    <div class="site-quick-stat-item">
                        <span style="color: var(--neutral-500); display: block; font-size: 0.78rem; text-transform: uppercase;">Last Checked</span>
                        <strong><?= time_ago($singleWebsite['last_checked']) ?></strong>
                    </div>
                </div>
            </div>

            <!-- 90-Day History Bar -->
            <div class="uptime-section" style="padding-top: 14px; border-top: 1px solid var(--border-color);">
                <div class="uptime-header">
                    <span class="uptime-label">90-Day Uptime History</span>
                    <span class="uptime-percentage"><?= e($singleWebsiteHistory['overall_uptime']) ?> uptime</span>
                </div>

                <div class="history-bar-container">
                    <?php foreach ($singleWebsiteHistory['days'] as $day): ?>
                        <?php
                        $tickClass = match($day['status']) {
                            'UP'      => 'tick-up',
                            'DOWN'    => 'tick-down',
                            'SLOW'    => 'tick-slow',
                            default   => 'tick-no-data'
                        };
                        ?>
                        <div class="history-tick <?= $tickClass ?>">
                            <div class="custom-tooltip">
                                <strong><?= e($day['formatted_date']) ?></strong><br>
                                <?php if ($day['has_data']): ?>
                                    Status: <b><?= e($day['status']) ?></b><br>
                                    Uptime: <b><?= e($day['uptime_percentage']) ?></b><br>
                                    Total Checks: <?= $day['total_checks'] ?><br>
                                    UP: <?= $day['up_checks'] ?> | DOWN: <?= $day['down_checks'] ?> | SLOW: <?= $day['slow_checks'] ?><br>
                                    Avg Resp: <?= format_ms($day['avg_response_time']) ?>
                                <?php else: ?>
                                    No monitoring checks recorded on this day.
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="history-legend">
                    <span>90 days ago</span>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <span><span class="status-dot dot-up"></span> Operational</span>
                        <span><span class="status-dot dot-slow"></span> Slow</span>
                        <span><span class="status-dot dot-down"></span> Down</span>
                        <span><span class="status-dot dot-pending"></span> No Data</span>
                    </div>
                    <span>Today</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Website Incidents -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Incidents for <?= e($singleWebsite['name']) ?></h2>
        </div>
        <div class="card-body">
            <?php if (empty($singleWebsiteIncidents)): ?>
                <div style="text-align: center; padding: 30px; color: var(--neutral-500);">
                    <div style="font-size: 32px; margin-bottom: 8px;">🎉</div>
                    <strong>No incidents recorded in the last 90 days.</strong>
                    <p style="margin-top: 4px; font-size: 0.88rem;">This service has maintained flawless uptime during this period.</p>
                </div>
            <?php else: ?>
                <div class="incident-timeline">
                    <?php foreach ($singleWebsiteIncidents as $inc): ?>
                        <?php 
                        $isResolved = !empty($inc['resolved_at']);
                        $startTime = strtotime($inc['created_at']);
                        $endTime = $isResolved ? strtotime($inc['resolved_at']) : time();
                        $durationSec = max(0, $endTime - $startTime);
                        ?>
                        <div class="incident-card <?= $isResolved ? 'incident-resolved' : '' ?>">
                            <div class="incident-card-header">
                                <div class="incident-title">
                                    <?= $isResolved ? '✅ Service Outage Resolved' : '🚨 Active Service Outage' ?>
                                </div>
                                <div>
                                    <?php if ($isResolved): ?>
                                        <span class="badge badge-up">Resolved</span>
                                    <?php else: ?>
                                        <span class="badge badge-down">Ongoing</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="incident-meta">
                                <div><strong>Started:</strong> <?= format_datetime($inc['created_at']) ?></div>
                                <div><strong>Recovered:</strong> <?= $isResolved ? format_datetime($inc['resolved_at']) : 'In Progress' ?></div>
                                <div><strong>Duration:</strong> <?= format_duration($durationSec) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- ====================================================================
         Overall System Status Overview (Main Status Page)
         ==================================================================== -->
    
    <!-- System Hero Banner -->
    <?php
    $bannerClass = match($systemStatus['status']) {
        'UP'    => 'banner-up',
        'DOWN'  => 'banner-down',
        'SLOW'  => 'banner-slow',
        default => 'banner-pending'
    };
    $bannerIcon = match($systemStatus['status']) {
        'UP'    => '🟢',
        'DOWN'  => '🔴',
        'SLOW'  => '🟡',
        default => '⚪'
    };
    ?>
    <div class="status-banner <?= $bannerClass ?>">
        <div class="status-banner-content">
            <div class="status-banner-icon"><?= $bannerIcon ?></div>
            <div>
                <h1 class="status-banner-title"><?= e($systemStatus['title']) ?></h1>
                <div class="status-banner-desc"><?= e($systemStatus['desc']) ?></div>
            </div>
        </div>
        <div class="status-banner-meta">
            <div id="auto-refresh-timer">Auto-refresh in 60s</div>
            <div style="font-size: 0.78rem; opacity: 0.85; margin-top: 2px;">Last updated: <?= format_time(date('Y-m-d H:i:s')) ?></div>
        </div>
    </div>

    <!-- Monitored Services List -->
    <div class="page-header" style="margin-bottom: 16px;">
        <h2 class="page-title" style="font-size: 1.35rem;">Monitored Services</h2>
        <div style="font-size: 0.88rem; color: var(--neutral-500);">
            Showing real-time checks &bull; 90-day history
        </div>
    </div>

    <?php if (empty($websitesWithHistory)): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 40px; color: var(--neutral-500);">
                <p>No websites are currently being monitored.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($websitesWithHistory as $item): ?>
            <?php 
            $site = $item['site'];
            $history = $item['history'];
            ?>
            <div class="site-item-card">
                <div class="site-card-top">
                    <div class="site-meta-group">
                        <h3 class="site-title">
                            <a href="<?= BASE_URL ?>/status.php?site=<?= $site['id'] ?>" style="color: inherit;">
                                <?= e($site['name']) ?>
                            </a>
                        </h3>
                        <?= get_status_badge($site['current_status']) ?>
                    </div>

                    <div class="site-quick-stats">
                        <div class="site-quick-stat-item">
                            <span style="color: var(--neutral-500); font-size: 0.78rem; text-transform: uppercase;">Response Time:</span>
                            <strong><?= format_ms($site['response_time']) ?></strong>
                        </div>
                        <div class="site-quick-stat-item">
                            <span style="color: var(--neutral-500); font-size: 0.78rem; text-transform: uppercase;">Last Checked:</span>
                            <strong><?= time_ago($site['last_checked']) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- 90-Day Visual History -->
                <div class="uptime-section">
                    <div class="uptime-header">
                        <span class="uptime-label">
                            <a href="<?= BASE_URL ?>/status.php?site=<?= $site['id'] ?>" style="color: inherit; text-decoration: underline;">
                                90-day uptime history
                            </a>
                        </span>
                        <span class="uptime-percentage"><?= e($history['overall_uptime']) ?></span>
                    </div>

                    <div class="history-bar-container">
                        <?php foreach ($history['days'] as $day): ?>
                            <?php
                            $tickClass = match($day['status']) {
                                'UP'      => 'tick-up',
                                'DOWN'    => 'tick-down',
                                'SLOW'    => 'tick-slow',
                                default   => 'tick-no-data'
                            };
                            ?>
                            <div class="history-tick <?= $tickClass ?>">
                                <div class="custom-tooltip">
                                    <strong><?= e($day['formatted_date']) ?></strong><br>
                                    <?php if ($day['has_data']): ?>
                                        Status: <b><?= e($day['status']) ?></b><br>
                                        Uptime: <b><?= e($day['uptime_percentage']) ?></b><br>
                                        Total Checks: <?= $day['total_checks'] ?><br>
                                        UP: <?= $day['up_checks'] ?> | DOWN: <?= $day['down_checks'] ?> | SLOW: <?= $day['slow_checks'] ?><br>
                                        Avg Resp: <?= format_ms($day['avg_response_time']) ?>
                                    <?php else: ?>
                                        No checks logged on this day.
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="history-legend">
                        <span>90 days ago</span>
                        <span><?= e($history['overall_uptime']) ?> uptime</span>
                        <span>Today</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Recent System Incidents -->
    <div class="card" style="margin-top: 36px;">
        <div class="card-header">
            <h2 class="card-title">Recent System Incidents</h2>
        </div>
        <div class="card-body">
            <?php if (empty($recentIncidents)): ?>
                <div style="text-align: center; padding: 24px; color: var(--neutral-500);">
                    <div style="font-size: 28px; margin-bottom: 6px;">🎉</div>
                    <strong>No incidents reported in the last 90 days.</strong>
                    <p style="margin-top: 4px; font-size: 0.85rem;">All systems have experienced smooth, uninterrupted uptime.</p>
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
                        <div class="incident-card <?= $isResolved ? 'incident-resolved' : '' ?>">
                            <div class="incident-card-header">
                                <div class="incident-title">
                                    <?= $isResolved ? '✅ Service Outage Resolved' : '🚨 Active Outage' ?> &bull; 
                                    <a href="<?= BASE_URL ?>/status.php?site=<?= $inc['website_id'] ?>"><?= e($inc['website_name']) ?></a>
                                </div>
                                <div>
                                    <?php if ($isResolved): ?>
                                        <span class="badge badge-up">Resolved</span>
                                    <?php else: ?>
                                        <span class="badge badge-down">Active Outage</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="incident-meta">
                                <div><strong>Started:</strong> <?= format_datetime($inc['created_at']) ?></div>
                                <div><strong>Recovered:</strong> <?= $isResolved ? format_datetime($inc['resolved_at']) : 'In Progress' ?></div>
                                <div><strong>Duration:</strong> <?= format_duration($durationSec) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
