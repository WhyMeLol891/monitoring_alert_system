<?php
/**
 * Cron Job: 90-Day Logs Cleanup
 * Recommended Cron: Run once per day (e.g. at midnight)
 * Example: 0 0 * * * php /path/to/cron/cleanup.php
 */

// Allow execution from CLI or Web (with optional key check)
if (php_sapi_name() !== 'cli') {
    $reqKey = $_GET['key'] ?? '';
    if (!empty(CRON_SECRET_KEY) && $reqKey !== CRON_SECRET_KEY) {
        http_response_code(403);
        die('Access Denied: Invalid cron key.');
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDB();
$nowStr = date('Y-m-d H:i:s');
$formattedTime = format_datetime($nowStr);

// Delete logs strictly older than 90 days
$stmt = $pdo->prepare("
    DELETE FROM monitoring_logs 
    WHERE checked_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
");
$stmt->execute();
$deletedLogsCount = $stmt->rowCount();

// Optional: optimize table after bulk deletion if needed
if ($deletedLogsCount > 0) {
    try {
        $pdo->query("OPTIMIZE TABLE monitoring_logs");
    } catch (Exception $e) {
        // Optimization may not be allowed on some restricted hosts, ignore gracefully
    }
}

if (php_sapi_name() === 'cli') {
    echo "========================================================\n";
    echo "90-Day Logs Cleanup Finished at {$formattedTime}\n";
    echo "Deleted {$deletedLogsCount} logs older than 90 days.\n";
    echo "========================================================\n";
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success'       => true,
        'deleted_logs'  => $deletedLogsCount,
        'timestamp'     => $nowStr
    ], JSON_PRETTY_PRINT);
}
