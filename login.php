<?php
/**
 * Admin Login Page
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to admin dashboard
if (is_logged_in()) {
    redirect('admin/dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                login_admin((int)$admin['id'], $admin['username']);
                set_flash_message('success', "Welcome back, {$admin['username']}!");
                redirect('admin/dashboard.php');
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}

$pageTitle = "Admin Login - " . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="login-page-container">
    <div class="login-card">
        <div class="login-header">
            <div class="brand-icon" style="margin: 0 auto; width: 44px; height: 44px; font-size: 22px;">🔐</div>
            <h1 class="login-title">Admin Login</h1>
            <p class="login-subtitle">Sign in to manage website monitoring & alerts</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">⚠️</span>
                <div class="alert-text"><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/login.php" method="POST" autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username" class="form-label">Username <span class="required">*</span></label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="form-control" 
                       value="<?= e($username) ?>" 
                       required 
                       autofocus 
                       placeholder="Enter admin username">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password <span class="required">*</span></label>
                <div class="input-password-wrapper">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           required 
                           placeholder="Enter admin password">
                    <button type="button" class="password-toggle-btn" data-target="password" title="Show password">👁️</button>
                </div>
                <div class="form-text">Default login: <code>admin</code> / <code>admin123</code></div>
            </div>

            <div style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <span>Sign In to Dashboard &rarr;</span>
                </button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <a href="<?= BASE_URL ?>/status.php" style="font-size: 0.9rem; color: var(--neutral-500);">
                &larr; Return to Public Status Page
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
