<?php
/**
 * Core Helper Functions
 */

require_once __DIR__ . '/../config/config.php';

/**
 * HTML Sanitization helper
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF Protection Helpers
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash Messages
 */
function set_flash_message(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash_messages(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function render_flash_messages(): string {
    $messages = get_flash_messages();
    if (empty($messages)) {
        return '';
    }
    $html = '<div class="flash-messages-container">';
    foreach ($messages as $msg) {
        $type = e($msg['type']);
        $text = e($msg['message']);
        $icon = match($type) {
            'success' => '✅',
            'danger'  => '🚨',
            'warning' => '⚠️',
            default   => 'ℹ️'
        };
        $html .= "<div class=\"alert alert-{$type}\"><span class=\"alert-icon\">{$icon}</span> <div class=\"alert-text\">{$text}</div><button type=\"button\" class=\"alert-close\" onclick=\"this.parentElement.remove();\">&times;</button></div>";
    }
    $html .= '</div>';
    return $html;
}

/**
 * Redirect helper
 */
function redirect(string $path): void {
    if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
        $path = BASE_URL . '/' . ltrim($path, '/');
    }
    header("Location: {$path}");
    exit;
}

/**
 * JSON Response helper
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Date and Time formatters (Malaysia Time - Asia/Kuala_Lumpur)
 */
function format_datetime(?string $datetime, string $format = 'd M Y, h:i A'): string {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'Never';
    }
    try {
        $dt = new DateTime($datetime, new DateTimeZone('Asia/Kuala_Lumpur'));
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

function format_time(?string $datetime): string {
    return format_datetime($datetime, 'h:i A');
}

function format_date(?string $datetime): string {
    return format_datetime($datetime, 'd M Y');
}

function time_ago(?string $datetime): string {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'Never';
    }
    try {
        $now = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
        $past = new DateTime($datetime, new DateTimeZone('Asia/Kuala_Lumpur'));
        $diff = $now->diff($past);

        if ($diff->y > 0) return $diff->y . ' ' . ($diff->y > 1 ? 'years' : 'year') . ' ago';
        if ($diff->m > 0) return $diff->m . ' ' . ($diff->m > 1 ? 'months' : 'month') . ' ago';
        if ($diff->d > 0) return $diff->d . ' ' . ($diff->d > 1 ? 'days' : 'day') . ' ago';
        if ($diff->h > 0) return $diff->h . ' ' . ($diff->h > 1 ? 'hrs' : 'hr') . ' ago';
        if ($diff->i > 0) return $diff->i . ' ' . ($diff->i > 1 ? 'mins' : 'min') . ' ago';
        return 'Just now';
    } catch (Exception $e) {
        return $datetime;
    }
}

function format_duration(int $seconds): string {
    if ($seconds <= 0) return '0s';
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $sec = $seconds % 60;

    $parts = [];
    if ($days > 0) $parts[] = "{$days}d";
    if ($hours > 0) $parts[] = "{$hours}h";
    if ($minutes > 0) $parts[] = "{$minutes}m";
    if (empty($parts) || ($days == 0 && $hours == 0 && $sec > 0)) $parts[] = "{$sec}s";

    return implode(' ', $parts);
}

/**
 * Format Response Time
 */
function format_ms(?int $ms): string {
    if ($ms === null || $ms < 0) {
        return '-';
    }
    if ($ms >= 1000) {
        return number_format($ms / 1000, 2) . ' s';
    }
    return $ms . ' ms';
}

/**
 * Status Badges
 */
function get_status_badge(string $status, bool $withIcon = true): string {
    $status = strtoupper($status);
    return match($status) {
        'UP' => '<span class="badge badge-up">' . ($withIcon ? '<span class="status-dot dot-up"></span> ' : '') . 'UP</span>',
        'DOWN' => '<span class="badge badge-down">' . ($withIcon ? '<span class="status-dot dot-down"></span> ' : '') . 'DOWN</span>',
        'SLOW' => '<span class="badge badge-slow">' . ($withIcon ? '<span class="status-dot dot-slow"></span> ' : '') . 'SLOW</span>',
        default => '<span class="badge badge-pending">' . ($withIcon ? '<span class="status-dot dot-pending"></span> ' : '') . 'PENDING</span>',
    };
}

function get_status_text(string $status): string {
    return match(strtoupper($status)) {
        'UP' => 'Operational',
        'DOWN' => 'Outage (Down)',
        'SLOW' => 'Degraded Performance (Slow)',
        default => 'Pending Check',
    };
}

/**
 * Calculate uptime percentage using the specified formula:
 * (UP checks / Total checks) * 100
 */
function calculate_uptime(int $upChecks, int $totalChecks): string {
    if ($totalChecks <= 0) {
        return 'N/A';
    }
    $percentage = ($upChecks / $totalChecks) * 100;
    return number_format($percentage, 2) . '%';
}

/**
 * Fetch 90-day history statistics for a specific website
 * Generates an array of 90 days (from 89 days ago through today)
 * with actual checks, up/down/slow breakdown and uptime percentage.
 */
function get_90_days_history(PDO $pdo, int $websiteId): array {
    // Query aggregate checks grouped by DATE(checked_at) in the last 90 days
    $stmt = $pdo->prepare("
        SELECT 
            DATE(checked_at) AS check_date,
            COUNT(*) AS total_checks,
            SUM(CASE WHEN status = 'UP' THEN 1 ELSE 0 END) AS up_checks,
            SUM(CASE WHEN status = 'DOWN' THEN 1 ELSE 0 END) AS down_checks,
            SUM(CASE WHEN status = 'SLOW' THEN 1 ELSE 0 END) AS slow_checks,
            AVG(response_time) AS avg_response_time
        FROM monitoring_logs
        WHERE website_id = ? 
          AND checked_at >= DATE_SUB(CURDATE(), INTERVAL 89 DAY)
        GROUP BY DATE(checked_at)
        ORDER BY check_date ASC
    ");
    $stmt->execute([$websiteId]);
    $dbRows = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

    $history = [];
    $today = new DateTime('today', new DateTimeZone('Asia/Kuala_Lumpur'));
    
    // Overall counters for 90 days
    $totalOverallChecks = 0;
    $totalUpChecks = 0;
    $totalDownChecks = 0;
    $totalSlowChecks = 0;

    for ($i = 89; $i >= 0; $i--) {
        $dateObj = (clone $today)->modify("-{$i} days");
        $dateStr = $dateObj->format('Y-m-d');
        $formattedDate = $dateObj->format('M j, Y');

        if (isset($dbRows[$dateStr])) {
            $row = $dbRows[$dateStr];
            $total = (int)$row['total_checks'];
            $up = (int)$row['up_checks'];
            $down = (int)$row['down_checks'];
            $slow = (int)$row['slow_checks'];
            $avgResp = round((float)$row['avg_response_time']);

            $totalOverallChecks += $total;
            $totalUpChecks += $up;
            $totalDownChecks += $down;
            $totalSlowChecks += $slow;

            // Status determination for the day:
            // DOWN if any down checks occurred, else SLOW if any slow checks, else UP
            if ($down > 0) {
                $dayStatus = 'DOWN';
            } elseif ($slow > 0) {
                $dayStatus = 'SLOW';
            } else {
                $dayStatus = 'UP';
            }

            $uptimePct = ($total > 0) ? number_format(($up / $total) * 100, 2) . '%' : 'N/A';

            $history[] = [
                'date'              => $dateStr,
                'formatted_date'    => $formattedDate,
                'has_data'          => true,
                'status'            => $dayStatus,
                'total_checks'      => $total,
                'up_checks'         => $up,
                'down_checks'       => $down,
                'slow_checks'       => $slow,
                'uptime_percentage' => $uptimePct,
                'avg_response_time' => $avgResp
            ];
        } else {
            // No data recorded for this day
            $history[] = [
                'date'              => $dateStr,
                'formatted_date'    => $formattedDate,
                'has_data'          => false,
                'status'            => 'NO_DATA',
                'total_checks'      => 0,
                'up_checks'         => 0,
                'down_checks'       => 0,
                'slow_checks'       => 0,
                'uptime_percentage' => 'N/A',
                'avg_response_time' => 0
            ];
        }
    }

    $overallUptime = ($totalOverallChecks > 0) 
        ? number_format(($totalUpChecks / $totalOverallChecks) * 100, 2) . '%' 
        : 'N/A';

    return [
        'days'                 => $history,
        'total_checks'         => $totalOverallChecks,
        'up_checks'            => $totalUpChecks,
        'down_checks'          => $totalDownChecks,
        'slow_checks'          => $totalSlowChecks,
        'overall_uptime'       => $overallUptime
    ];
}

/**
 * Get Overall System Status
 */
function get_overall_system_status(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN current_status = 'DOWN' THEN 1 ELSE 0 END) AS down_count,
            SUM(CASE WHEN current_status = 'SLOW' THEN 1 ELSE 0 END) AS slow_count,
            SUM(CASE WHEN current_status = 'UP' THEN 1 ELSE 0 END) AS up_count
        FROM websites
        WHERE enabled = 1
    ");
    $stats = $stmt->fetch();
    
    $total = (int)($stats['total'] ?? 0);
    $down = (int)($stats['down_count'] ?? 0);
    $slow = (int)($stats['slow_count'] ?? 0);
    $up = (int)($stats['up_count'] ?? 0);

    if ($total === 0) {
        return [
            'status' => 'PENDING',
            'title'  => 'No Monitored Systems',
            'desc'   => 'There are currently no active websites configured for monitoring.',
            'badge'  => 'badge-pending',
            'color'  => 'gray'
        ];
    }

    if ($down > 0) {
        return [
            'status' => 'DOWN',
            'title'  => ($down === $total) ? 'Major Outage' : 'Partial System Outage',
            'desc'   => "{$down} of {$total} monitored systems " . ($down === 1 ? "is" : "are") . " currently experiencing downtime.",
            'badge'  => 'badge-down',
            'color'  => 'red'
        ];
    }

    if ($slow > 0) {
        return [
            'status' => 'SLOW',
            'title'  => 'Degraded System Performance',
            'desc'   => "{$slow} of {$total} monitored systems " . ($slow === 1 ? "is" : "are") . " experiencing high response latency.",
            'badge'  => 'badge-slow',
            'color'  => 'yellow'
        ];
    }

    return [
        'status' => 'UP',
        'title'  => 'All Systems Operational',
        'desc'   => "All {$total} monitored systems are online, reachable, and responding normally.",
        'badge'  => 'badge-up',
        'color'  => 'green'
    ];
}
