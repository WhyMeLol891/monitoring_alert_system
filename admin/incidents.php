<?php
/**
 * Incidents & Downtime History
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();

// Handle manual resolve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_incident') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $incId = (int)($_POST['incident_id'] ?? 0);
        if ($incId > 0) {
            $stmt = $pdo->prepare("UPDATE incidents SET resolved_at = NOW() WHERE id = ? AND resolved_at IS NULL");
            $stmt->execute([$incId]);
            set_flash_message('success', 'Incident marked as resolved.');
        }
        redirect('admin/incidents.php');
    }
}

// Fetch all incidents
$stmt = $pdo->query("
    SELECT i.*, w.name AS website_name, w.url AS website_url, w.current_status AS live_status
    FROM incidents i
    JOIN websites w ON i.website_id = w.id
    ORDER BY i.created_at DESC
");
$incidents = $stmt->fetchAll();

// Counters
$activeCount = 0;
$resolvedCount = 0;
foreach ($incidents as $inc) {
    if (empty($inc['resolved_at'])) {
        $activeCount++;
    } else {
        $resolvedCount++;
    }
}

$pageTitle = "Incidents History - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Incidents History</h1>
        <p class="page-subtitle">Track downtime events, outages, durations, and resolution times</p>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-total">⚠️</div>
        <div class="stat-info">
            <div class="stat-label">Total Incidents</div>
            <div class="stat-value"><?= count($incidents) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-down">🚨</div>
        <div class="stat-info">
            <div class="stat-label">Active Outages</div>
            <div class="stat-value" style="color: var(--danger);"><?= $activeCount ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-up">✅</div>
        <div class="stat-info">
            <div class="stat-label">Resolved</div>
            <div class="stat-value" style="color: var(--success);"><?= $resolvedCount ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">All Recorded Incidents</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Website</th>
                        <th>Started At</th>
                        <th>Recovered At</th>
                        <th>Total Downtime</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($incidents)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 36px; color: var(--neutral-500);">
                                <div style="font-size: 28px; margin-bottom: 6px;">🎉</div>
                                No incidents have occurred yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($incidents as $inc): ?>
                            <?php
                            $isResolved = !empty($inc['resolved_at']);
                            $startTime = strtotime($inc['created_at']);
                            $endTime = $isResolved ? strtotime($inc['resolved_at']) : time();
                            $durationSec = max(0, $endTime - $startTime);
                            ?>
                            <tr>
                                <td>
                                    <?php if ($isResolved): ?>
                                        <span class="badge badge-up">Resolved</span>
                                    <?php else: ?>
                                        <span class="badge badge-down">Active Outage</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= e($inc['website_name']) ?></strong>
                                    <div style="font-size: 0.78rem; color: var(--neutral-500);">
                                        <a href="<?= e($inc['website_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--neutral-500);">
                                            <?= e($inc['website_url']) ?> &#8599;
                                        </a>
                                    </div>
                                </td>
                                <td><?= format_datetime($inc['created_at']) ?></td>
                                <td>
                                    <?= $isResolved ? format_datetime($inc['resolved_at']) : '<strong style="color: var(--danger);">Ongoing</strong>' ?>
                                </td>
                                <td>
                                    <strong><?= format_duration($durationSec) ?></strong>
                                </td>
                                <td style="text-align: right;">
                                    <?php if (!$isResolved): ?>
                                        <form action="<?= BASE_URL ?>/admin/incidents.php" method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="resolve_incident">
                                            <input type="hidden" name="incident_id" value="<?= $inc['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" title="Mark as resolved manually">
                                                Mark Resolved
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--neutral-400); font-size: 0.85rem;">-</span>
                                    <?php endif; ?>
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
