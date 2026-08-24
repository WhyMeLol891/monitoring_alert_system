<?php
/**
 * Monitoring Logs with Filters & Pagination
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();

// Fetch websites for filter dropdown
$sitesStmt = $pdo->query("SELECT id, name FROM websites ORDER BY name ASC");
$allWebsites = $sitesStmt->fetchAll();

// Filters from GET query
$filterSite = isset($_GET['site_id']) && $_GET['site_id'] !== '' ? (int)$_GET['site_id'] : null;
$filterStatus = isset($_GET['status']) && in_array(strtoupper($_GET['status']), ['UP', 'DOWN', 'SLOW']) ? strtoupper($_GET['status']) : null;
$filterRange = isset($_GET['range']) && in_array($_GET['range'], ['today', '7days', '30days', '90days']) ? $_GET['range'] : 'all';

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

// Build WHERE query
$whereClauses = [];
$params = [];

if ($filterSite) {
    $whereClauses[] = "l.website_id = ?";
    $params[] = $filterSite;
}

if ($filterStatus) {
    $whereClauses[] = "l.status = ?";
    $params[] = $filterStatus;
}

if ($filterRange === 'today') {
    $whereClauses[] = "DATE(l.checked_at) = CURDATE()";
} elseif ($filterRange === '7days') {
    $whereClauses[] = "l.checked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filterRange === '30days') {
    $whereClauses[] = "l.checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($filterRange === '90days') {
    $whereClauses[] = "l.checked_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Count total matching logs
$countSql = "SELECT COUNT(*) FROM monitoring_logs l {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalLogs = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalLogs / $perPage));

// Fetch paginated logs
$logsSql = "
    SELECT l.*, w.name AS website_name, w.url AS website_url
    FROM monitoring_logs l
    JOIN websites w ON l.website_id = w.id
    {$whereSql}
    ORDER BY l.checked_at DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$logsStmt = $pdo->prepare($logsSql);
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll();

// Helper to build pagination query params
function pagination_url(int $targetPage, ?int $site, ?string $status, string $range): string {
    $params = ['page' => $targetPage];
    if ($site) $params['site_id'] = $site;
    if ($status) $params['status'] = $status;
    if ($range !== 'all') $params['range'] = $range;
    return BASE_URL . '/admin/logs.php?' . http_build_query($params);
}

$pageTitle = "Monitoring Logs - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Monitoring Logs</h1>
        <p class="page-subtitle">Detailed check history, response latency, and HTTP status codes</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form action="<?= BASE_URL ?>/admin/logs.php" method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; width: 100%; align-items: flex-end;">
        <div style="flex: 1; min-width: 180px;">
            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Website</label>
            <select name="site_id" class="form-control" style="padding: 8px 12px;">
                <option value="">-- All Websites --</option>
                <?php foreach ($allWebsites as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ($filterSite === (int)$s['id']) ? 'selected' : '' ?>>
                        <?= e($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Status</label>
            <select name="status" class="form-control" style="padding: 8px 12px;">
                <option value="">-- All Statuses --</option>
                <option value="UP" <?= $filterStatus === 'UP' ? 'selected' : '' ?>>🟢 UP</option>
                <option value="DOWN" <?= $filterStatus === 'DOWN' ? 'selected' : '' ?>>🔴 DOWN</option>
                <option value="SLOW" <?= $filterStatus === 'SLOW' ? 'selected' : '' ?>>🟡 SLOW</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Timeframe</label>
            <select name="range" class="form-control" style="padding: 8px 12px;">
                <option value="all" <?= $filterRange === 'all' ? 'selected' : '' ?>>All Available</option>
                <option value="today" <?= $filterRange === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="7days" <?= $filterRange === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="30days" <?= $filterRange === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                <option value="90days" <?= $filterRange === '90days' ? 'selected' : '' ?>>Last 90 Days</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">
                🔍 Apply Filter
            </button>
            <a href="<?= BASE_URL ?>/admin/logs.php" class="btn btn-secondary" style="padding: 8px 16px;">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Logs Table Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            Logs (<?= number_format($totalLogs) ?> total entries)
        </div>
        <div style="font-size: 0.85rem; color: var(--neutral-500);">
            Page <?= $page ?> of <?= $totalPages ?>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Website</th>
                        <th>Status</th>
                        <th>Response Time</th>
                        <th>HTTP Status</th>
                        <th>Error / Response Details</th>
                        <th>Checked At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--neutral-500);">
                                No monitoring logs found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <strong><?= e($log['website_name']) ?></strong>
                                    <div style="font-size: 0.78rem; color: var(--neutral-500);">
                                        <?= e($log['website_url']) ?>
                                    </div>
                                </td>
                                <td><?= get_status_badge($log['status']) ?></td>
                                <td>
                                    <strong><?= format_ms($log['response_time']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($log['http_status_code']): ?>
                                        <code><?= e((string)$log['http_status_code']) ?></code>
                                    <?php else: ?>
                                        <span style="color: var(--neutral-400);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['error_message'])): ?>
                                        <span style="color: var(--danger-dark); font-size: 0.85rem;">
                                            <?= e($log['error_message']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--neutral-400); font-size: 0.85rem;">OK</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.88rem;"><?= format_datetime($log['checked_at']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--neutral-400);"><?= time_ago($log['checked_at']) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination Controls -->
<?php if ($totalPages > 1): ?>
    <div class="pagination-wrapper">
        <div style="font-size: 0.88rem; color: var(--neutral-500);">
            Showing <?= min($totalLogs, $offset + 1) ?> - <?= min($totalLogs, $offset + count($logs)) ?> of <?= number_format($totalLogs) ?> logs
        </div>
        <ul class="pagination">
            <!-- Prev -->
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a href="<?= ($page > 1) ? pagination_url($page - 1, $filterSite, $filterStatus, $filterRange) : '#' ?>" class="page-link">&laquo; Prev</a>
            </li>

            <!-- Page Numbers -->
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            if ($startPage > 1) {
                echo '<li class="page-item"><a href="' . pagination_url(1, $filterSite, $filterStatus, $filterRange) . '" class="page-link">1</a></li>';
                if ($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            for ($p = $startPage; $p <= $endPage; $p++): ?>
                <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                    <a href="<?= pagination_url($p, $filterSite, $filterStatus, $filterRange) ?>" class="page-link"><?= $p ?></a>
                </li>
            <?php endfor;

            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                echo '<li class="page-item"><a href="' . pagination_url($totalPages, $filterSite, $filterStatus, $filterRange) . '" class="page-link">' . $totalPages . '</a></li>';
            }
            ?>

            <!-- Next -->
            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                <a href="<?= ($page < $totalPages) ? pagination_url($page + 1, $filterSite, $filterStatus, $filterRange) : '#' ?>" class="page-link">Next &raquo;</a>
            </li>
        </ul>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
