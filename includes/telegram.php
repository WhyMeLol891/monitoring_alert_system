<?php
/**
 * Telegram Bot API Integration
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Fetch Telegram configuration from DB
 */
function get_telegram_config(): array {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM telegram_config WHERE id = 1 LIMIT 1");
    $config = $stmt->fetch();
    if (!$config) {
        return [
            'id'        => 1,
            'bot_token' => '',
            'chat_id'   => '',
            'enabled'   => 0
        ];
    }
    return $config;
}

/**
 * Send raw Telegram message via cURL
 */
function send_telegram_raw(string $message, ?string $botToken = null, ?string $chatId = null): array {
    if (empty($botToken) || empty($chatId)) {
        $config = get_telegram_config();
        $botToken = $config['bot_token'] ?? '';
        $chatId = $config['chat_id'] ?? '';
        
        // If not manually provided, check if telegram is enabled
        if (empty($config['enabled']) || $config['enabled'] != 1) {
            return [
                'success' => false,
                'message' => 'Telegram alerts are disabled in settings.'
            ];
        }
    }

    $botToken = trim($botToken);
    $chatId = trim($chatId);

    if (empty($botToken) || empty($chatId)) {
        return [
            'success' => false,
            'message' => 'Telegram Bot Token or Chat ID is missing.'
        ];
    }

    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $payload = [
        'chat_id'                  => $chatId,
        'text'                     => $message,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'message' => 'cURL connection error: ' . $curlError
        ];
    }

    $result = json_decode($response, true);
    if ($httpCode === 200 && !empty($result['ok'])) {
        return [
            'success' => true,
            'message' => 'Telegram message sent successfully.'
        ];
    }

    $desc = $result['description'] ?? 'HTTP ' . $httpCode;
    return [
        'success' => false,
        'message' => 'Telegram API Error: ' . $desc
    ];
}

/**
 * Send DOWN Alert
 */
function send_telegram_down_alert(array $website, string $timeStr, ?string $reason = null): array {
    $name = htmlspecialchars($website['name']);
    $url = htmlspecialchars($website['url']);
    $reasonText = $reason ? "\n<b>Reason:</b> " . htmlspecialchars($reason) : "";

    $message = "🚨 <b>WEBSITE DOWN</b>\n\n"
             . "<b>Website:</b> {$name}\n"
             . "<b>URL:</b> {$url}\n"
             . "<b>Status:</b> DOWN{$reasonText}\n"
             . "<b>Time:</b> {$timeStr}";

    return send_telegram_raw($message);
}

/**
 * Send RECOVERY Alert (DOWN -> UP)
 */
function send_telegram_recovery_alert(array $website, int $responseTime, string $timeStr): array {
    $name = htmlspecialchars($website['name']);
    $url = htmlspecialchars($website['url']);
    $formattedResp = format_ms($responseTime);

    $message = "✅ <b>WEBSITE RECOVERED</b>\n\n"
             . "<b>Website:</b> {$name}\n"
             . "<b>URL:</b> {$url}\n"
             . "<b>Status:</b> UP\n"
             . "<b>Response Time:</b> {$formattedResp}\n"
             . "<b>Time:</b> {$timeStr}";

    return send_telegram_raw($message);
}

/**
 * Send SLOW Warning Alert (UP -> SLOW)
 */
function send_telegram_slow_alert(array $website, int $responseTime, int $threshold, string $timeStr): array {
    $name = htmlspecialchars($website['name']);
    $url = htmlspecialchars($website['url']);
    $formattedResp = format_ms($responseTime);
    $formattedThreshold = format_ms($threshold);

    $message = "⚠️ <b>SLOW RESPONSE</b>\n\n"
             . "<b>Website:</b> {$name}\n"
             . "<b>URL:</b> {$url}\n"
             . "<b>Response Time:</b> {$formattedResp}\n"
             . "<b>Threshold:</b> {$formattedThreshold}\n"
             . "<b>Time:</b> {$timeStr}";

    return send_telegram_raw($message);
}

/**
 * Send Test Notification
 */
function send_telegram_test_message(string $botToken, string $chatId): array {
    $timeStr = format_datetime(date('Y-m-d H:i:s'));
    $message = "🔔 <b>WEBSITE MONITOR TEST NOTIFICATION</b>\n\n"
             . "Your Telegram bot integration is configured successfully and ready to deliver real-time website downtime and recovery alerts!\n\n"
             . "<b>System:</b> " . APP_NAME . "\n"
             . "<b>Time:</b> {$timeStr}\n"
             . "<b>Status:</b> Active & Monitoring";

    return send_telegram_raw($message, $botToken, $chatId);
}
