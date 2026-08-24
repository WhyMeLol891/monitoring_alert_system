<?php
/**
 * Admin Settings: Telegram Bot & Admin Account & Cron Setup
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/telegram.php';

require_admin();

$pdo = getDB();
$currentAdmin = current_admin();

$tgConfig = get_telegram_config();

$tgSuccess = '';
$tgError = '';
$accountSuccess = '';
$accountError = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash_message('danger', 'Security session expired. Please refresh and try again.');
        redirect('admin/settings.php');
    }

    // 1. Save Telegram Configuration
    if ($action === 'save_telegram') {
        $botToken = trim($_POST['bot_token'] ?? '');
        $chatId = trim($_POST['chat_id'] ?? '');
        $enabled = isset($_POST['enabled']) ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE telegram_config 
            SET bot_token = ?, 
                chat_id = ?, 
                enabled = ?, 
                updated_at = NOW() 
            WHERE id = 1
        ");
        $stmt->execute([$botToken, $chatId, $enabled]);

        set_flash_message('success', 'Telegram configuration saved successfully.');
        redirect('admin/settings.php');
    }

    // 2. Test Telegram Alert Button
    if ($action === 'test_telegram') {
        $botToken = trim($_POST['bot_token'] ?? $tgConfig['bot_token']);
        $chatId = trim($_POST['chat_id'] ?? $tgConfig['chat_id']);

        if (empty($botToken) || empty($chatId)) {
            set_flash_message('danger', 'Please enter both Bot Token and Chat ID before testing.');
        } else {
            $testRes = send_telegram_test_message($botToken, $chatId);
            if ($testRes['success']) {
                set_flash_message('success', '✅ Test message sent successfully! Please check your Telegram.');
            } else {
                set_flash_message('danger', '❌ Failed to send test alert: ' . $testRes['message']);
            }
        }
        redirect('admin/settings.php');
    }

    // 3. Update Admin Credentials
    if ($action === 'update_account') {
        $newUsername = trim($_POST['username'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newUsername)) {
            set_flash_message('danger', 'Username cannot be blank.');
            redirect('admin/settings.php');
        }

        // Verify current password first
        $adminStmt = $pdo->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
        $adminStmt->execute([$currentAdmin['id']]);
        $adminRow = $adminStmt->fetch();

        if (!$adminRow || !password_verify($currentPassword, $adminRow['password'])) {
            set_flash_message('danger', 'Incorrect current password.');
            redirect('admin/settings.php');
        }

        // Check if username is taken by another admin
        $chkStmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ? LIMIT 1");
        $chkStmt->execute([$newUsername, $currentAdmin['id']]);
        if ($chkStmt->fetch()) {
            set_flash_message('danger', 'That username is already in use.');
            redirect('admin/settings.php');
        }

        // If updating password
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                set_flash_message('danger', 'New password must be at least 6 characters.');
                redirect('admin/settings.php');
            }
            if ($newPassword !== $confirmPassword) {
                set_flash_message('danger', 'New passwords do not match.');
                redirect('admin/settings.php');
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upStmt = $pdo->prepare("UPDATE admins SET username = ?, password = ? WHERE id = ?");
            $upStmt->execute([$newUsername, $newHash, $currentAdmin['id']]);
        } else {
            $upStmt = $pdo->prepare("UPDATE admins SET username = ? WHERE id = ?");
            $upStmt->execute([$newUsername, $currentAdmin['id']]);
        }

        $_SESSION['admin_username'] = $newUsername;
        set_flash_message('success', 'Admin account updated successfully.');
        redirect('admin/settings.php');
    }
}

// Re-fetch latest Telegram config
$tgConfig = get_telegram_config();

$pageTitle = "System Settings - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">System Settings</h1>
        <p class="page-subtitle">Configure Telegram alerts, admin credentials, and cron job schedules</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 24px;">

    <!-- 1. Telegram Bot Settings Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🤖 Telegram Alert Configuration</h2>
            <div>
                <?php if (!empty($tgConfig['enabled'])): ?>
                    <span class="badge badge-up">Enabled</span>
                <?php else: ?>
                    <span class="badge badge-pending">Disabled</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <form action="<?= BASE_URL ?>/admin/settings.php" method="POST" id="tgForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="tgAction" value="save_telegram">

                <div class="form-group">
                    <label for="bot_token" class="form-label">Telegram Bot Token <span class="required">*</span></label>
                    <input type="text" 
                           id="bot_token" 
                           name="bot_token" 
                           class="form-control" 
                           value="<?= e($tgConfig['bot_token']) ?>" 
                           placeholder="e.g. 1234567890:ABCdefGhIJKlmNoPQRstuVWXyz">
                    <div class="form-text">Obtain from <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">@BotFather</a> on Telegram.</div>
                </div>

                <div class="form-group">
                    <label for="chat_id" class="form-label">Telegram Chat ID <span class="required">*</span></label>
                    <input type="text" 
                           id="chat_id" 
                           name="chat_id" 
                           class="form-control" 
                           value="<?= e($tgConfig['chat_id']) ?>" 
                           placeholder="e.g. 987654321 or -1001234567890">
                    <div class="form-text">User or Group Chat ID. Use <a href="https://t.me/userinfobot" target="_blank" rel="noopener noreferrer">@userinfobot</a> to find your ID.</div>
                </div>

                <div class="form-group">
                    <label class="form-switch">
                        <input type="checkbox" name="enabled" value="1" <?= !empty($tgConfig['enabled']) ? 'checked' : '' ?>>
                        <span style="font-weight: 600;">Enable Instant Telegram Alerts</span>
                    </label>
                    <div class="form-text">Alerts are dispatched immediately upon status transitions (UP &rarr; DOWN, DOWN &rarr; UP, UP &rarr; SLOW).</div>
                </div>

                <div style="margin-top: 24px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary" onclick="document.getElementById('tgAction').value='save_telegram';">
                        💾 Save Telegram Settings
                    </button>
                    <button type="submit" class="btn btn-secondary" onclick="document.getElementById('tgAction').value='test_telegram';">
                        🔔 Test Telegram Alert
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Admin Credentials Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🔐 Admin Security & Profile</h2>
        </div>
        <div class="card-body">
            <form action="<?= BASE_URL ?>/admin/settings.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_account">

                <div class="form-group">
                    <label for="admin_username" class="form-label">Admin Username <span class="required">*</span></label>
                    <input type="text" 
                           id="admin_username" 
                           name="username" 
                           class="form-control" 
                           value="<?= e($currentAdmin['username']) ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password <span class="required">*</span></label>
                    <div class="input-password-wrapper">
                        <input type="password" 
                               id="current_password" 
                               name="current_password" 
                               class="form-control" 
                               required 
                               placeholder="Enter your current password">
                        <button type="button" class="password-toggle-btn" data-target="current_password">👁️</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password" class="form-label">New Password (Leave blank to keep existing)</label>
                    <div class="input-password-wrapper">
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-control" 
                               placeholder="Minimum 6 characters">
                        <button type="button" class="password-toggle-btn" data-target="new_password">👁️</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-password-wrapper">
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-control" 
                               placeholder="Repeat new password">
                        <button type="button" class="password-toggle-btn" data-target="confirm_password">👁️</button>
                    </div>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">
                        🛡️ Update Credentials
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- 3. Cron Job Setup & Guidance -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h2 class="card-title">⏱️ Automated Cron Job Commands (XAMPP & cPanel)</h2>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 16px; color: var(--neutral-600);">
            To monitor websites 24/7 in real time, configure your server to execute the monitoring cron every minute and the cleanup cron once per day.
        </p>

        <h3 style="font-size: 1rem; color: var(--neutral-900); margin-bottom: 8px;">1. cPanel Cron Jobs (Linux)</h3>
        <div style="background: var(--neutral-900); color: #f8fafc; padding: 14px 18px; border-radius: var(--radius-sm); font-family: monospace; font-size: 0.88rem; margin-bottom: 14px; overflow-x: auto;">
            # Run website monitor every minute<br>
            * * * * * /usr/local/bin/php <?= e(ROOT_PATH) ?>/cron/monitor.php >/dev/null 2>&1<br><br>
            # Run 90-day history cleanup daily at midnight<br>
            0 0 * * * /usr/local/bin/php <?= e(ROOT_PATH) ?>/cron/cleanup.php >/dev/null 2>&1
        </div>

        <h3 style="font-size: 1rem; color: var(--neutral-900); margin-bottom: 8px;">2. XAMPP Windows (Command Prompt / Task Scheduler / PowerShell)</h3>
        <div style="background: var(--neutral-900); color: #f8fafc; padding: 14px 18px; border-radius: var(--radius-sm); font-family: monospace; font-size: 0.88rem; margin-bottom: 14px; overflow-x: auto;">
            # Run monitor in command prompt or PowerShell<br>
            php <?= e(str_replace('/', '\\', ROOT_PATH)) ?>\cron\monitor.php<br><br>
            # Run 90-day cleanup<br>
            php <?= e(str_replace('/', '\\', ROOT_PATH)) ?>\cron\cleanup.php
        </div>

        <h3 style="font-size: 1rem; color: var(--neutral-900); margin-bottom: 8px;">3. Web Cron URL (External Uptime Services / EasyCron)</h3>
        <div style="background: var(--neutral-900); color: #f8fafc; padding: 14px 18px; border-radius: var(--radius-sm); font-family: monospace; font-size: 0.88rem; overflow-x: auto;">
            <?= e(BASE_URL) ?>/cron/monitor.php?key=<?= e(CRON_SECRET_KEY) ?><br>
            <?= e(BASE_URL) ?>/cron/cleanup.php?key=<?= e(CRON_SECRET_KEY) ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
