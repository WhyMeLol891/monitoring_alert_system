<?php
/**
 * Cron Job: Website Monitoring Engine
 * Recommended Cron: Run every 1 minute
 * Example: * * * * * php /path/to/cron/monitor.php
 */

// Allow execution from CLI or Web (with optional key check)
if (php_sapi_name() !== 'cli') {
    // If accessed via browser, verify secret key if set
    $reqKey = $_GET['key'] ?? '';
    if (!empty(CRON_SECRET_KEY) && $reqKey !== CRON_SECRET_KEY) {
        http_response_code(403);
        die('Access Denied: Invalid cron key.');
    }
}

// Ignore user abort and remove time limit for long runs
ignore_user_abort(true);
set_time_limit(300);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/telegram.php';

$pdo = getDB();
$nowStr = date('Y-m-d H:i:s');
$formattedTime = format_datetime($nowStr);

// Check if specific site is requested (for instant recheck button in admin)
$specificSiteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : (isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : null);
$forceAll = isset($_GET['force']) && $_GET['force'] == '1';

if ($specificSiteId) {
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND enabled = 1");
    $stmt->execute([$specificSiteId]);
    $websites = $stmt->fetchAll();
} elseif ($forceAll) {
    $stmt = $pdo->query("SELECT * FROM websites WHERE enabled = 1");
    $websites = $stmt->fetchAll();
} else {
    // Fetch websites whose interval has passed or never checked
    $stmt = $pdo->query("
        SELECT * FROM websites 
        WHERE enabled = 1 
          AND (
              last_checked IS NULL 
              OR last_checked <= DATE_SUB(NOW(), INTERVAL monitoring_interval MINUTE)
          )
        ORDER BY last_checked ASC
    ");
    $websites = $stmt->fetchAll();
}

$totalChecked = count($websites);
$results = [];

if (php_sapi_name() === 'cli') {
    echo "========================================================\n";
    echo "Website Monitor Cron Started at {$formattedTime}\n";
    echo "Websites to check: {$totalChecked}\n";
    echo "========================================================\n";
}

foreach ($websites as $website) {
    $siteId = (int)$website['id'];
    $siteName = $website['name'];
    $siteUrl = $website['url'];
    $interval = (int)$website['monitoring_interval'];
    $slowThreshold = (int)$website['slow_threshold'];
    $prevStatus = $website['current_status'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $siteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) WebsiteMonitoringBot/1.0');
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTimeSec = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    // Calculate response time in milliseconds
    $responseTimeMs = (int)round(($totalTimeSec > 0 ? $totalTimeSec : ($endTime - $startTime)) * 1000);
    if ($responseTimeMs <= 0) {
        $responseTimeMs = 1;
    }

    // Determine status
    $errorMessage = null;
    if ($curlErrno !== 0 || $httpCode === 0 || $httpCode >= 500) {
        $newStatus = 'DOWN';
        $errorMessage = !empty($curlError) ? $curlError : "Server responded with HTTP {$httpCode}";
    } elseif ($responseTimeMs >= $slowThreshold) {
        $newStatus = 'SLOW';
    } else {
        $newStatus = 'UP';
    }

    // 1. Record in monitoring_logs
    $logStmt = $pdo->prepare("
        INSERT INTO monitoring_logs (website_id, status, response_time, http_status_code, error_message, checked_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $logStmt->execute([
        $siteId,
        $newStatus,
        $responseTimeMs,
        $httpCode ?: null,
        $errorMessage
    ]);

    // 2. State transition logic & Incidents & Telegram alerts
    $statusChanged = ($prevStatus !== $newStatus && $prevStatus !== 'PENDING');

    if ($statusChanged) {
        // Condition 1: UP/SLOW/PENDING -> DOWN (Outage started)
        if ($newStatus === 'DOWN') {
            // Create new Incident
            $incStmt = $pdo->prepare("
                INSERT INTO incidents (website_id, previous_status, current_status, response_time, created_at)
                VALUES (?, ?, 'DOWN', ?, NOW())
            ");
            $incStmt->execute([$siteId, $prevStatus, $responseTimeMs]);

            // Send Telegram DOWN alert
            send_telegram_down_alert($website, $formattedTime, $errorMessage);
        }
        // Condition 2: DOWN -> UP (Recovery)
        elseif ($prevStatus === 'DOWN' && $newStatus === 'UP') {
            // Close active Incident
            $closeStmt = $pdo->prepare("
                UPDATE incidents 
                SET resolved_at = NOW() 
                WHERE website_id = ? AND resolved_at IS NULL 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $closeStmt->execute([$siteId]);

            // Send Telegram RECOVERY alert
            send_telegram_recovery_alert($website, $responseTimeMs, $formattedTime);
        }
        // Condition 3: DOWN -> SLOW (Partial recovery)
        elseif ($prevStatus === 'DOWN' && $newStatus === 'SLOW') {
            $closeStmt = $pdo->prepare("
                UPDATE incidents 
                SET resolved_at = NOW() 
                WHERE website_id = ? AND resolved_at IS NULL 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $closeStmt->execute([$siteId]);

            send_telegram_slow_alert($website, $responseTimeMs, $slowThreshold, $formattedTime);
        }
        // Condition 4: UP -> SLOW (Degraded performance warning)
        elseif ($prevStatus === 'UP' && $newStatus === 'SLOW') {
            // Send Telegram SLOW warning
            send_telegram_slow_alert($website, $responseTimeMs, $slowThreshold, $formattedTime);
        }
        // Condition 5: SLOW -> UP (Normal performance restored)
        // No duplicate alerts sent
    }

    // 3. Update websites record
    $updateStmt = $pdo->prepare("
        UPDATE websites 
        SET current_status = ?, 
            last_checked = NOW(), 
            response_time = ?, 
            http_status_code = ?, 
            updated_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([
        $newStatus,
        $responseTimeMs,
        $httpCode ?: null,
        $siteId
    ]);

    $statusIcon = match($newStatus) {
        'UP'   => '🟢 UP',
        'DOWN' => '🔴 DOWN',
        'SLOW' => '🟡 SLOW',
        default => '⚪ PENDING'
    };

    $results[] = [
        'id'            => $siteId,
        'name'          => $siteName,
        'url'           => $siteUrl,
        'status'        => $newStatus,
        'http_code'     => $httpCode,
        'response_time' => $responseTimeMs,
        'status_changed'=> $statusChanged,
        'error'         => $errorMessage
    ];

    if (php_sapi_name() === 'cli') {
        echo "[{$statusIcon}] {$siteName} ({$siteUrl}) | Code: {$httpCode} | Time: {$responseTimeMs}ms" . ($statusChanged ? " [STATUS CHANGED: {$prevStatus} -> {$newStatus}]" : "") . "\n";
    }
}

if (php_sapi_name() === 'cli') {
    echo "========================================================\n";
    echo "Cron finished successfully.\n";
} else {
    // If called via Web / API
    header('Content-Type: application/json');
    echo json_encode([
        'success'       => true,
        'checked_count' => $totalChecked,
        'timestamp'     => $nowStr,
        'results'       => $results
    ], JSON_PRETTY_PRINT);
}
