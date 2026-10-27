<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$account = get_admin_account();
$settings = get_admin_settings();
$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['notification_email'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
        $error = 'Username must be 3-64 characters and contain only letters, numbers, dots, underscores, or hyphens.';
    } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Enter a valid notification email address or leave it blank.';
    } elseif (!password_verify($currentPassword, $account['password_hash'])) {
        $error = 'The current password is incorrect.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== '' && $newPassword !== $confirmation) {
        $error = 'New passwords do not match.';
    } else {
        $updatedSettings = $settings;
        $updatedSettings['admin_username'] = $username;
        $updatedSettings['notification_email'] = $email;
        if ($newPassword !== '') {
            $updatedSettings['admin_password_hash'] = hash_password($newPassword);
        }

        if (!save_admin_settings($updatedSettings)) {
            $error = 'Unable to save settings. Check that the application data directory is writable.';
        } else {
            $_SESSION['admin_username'] = $username;
            $account = get_admin_account();
            $settings = get_admin_settings();
            $message = 'Settings saved successfully.';
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">Settings</h3>
                <p class="text-secondary mb-0">Manage your administrator account and notification settings.</p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="mb-4">Administrator Profile</h5>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                <form method="post">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="username">Administrator Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? $account['username']); ?>" required minlength="3" maxlength="64">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="notification_email">Notification Email</label>
                                <input type="email" class="form-control" id="notification_email" name="notification_email" value="<?php echo htmlspecialchars($_POST['notification_email'] ?? ($settings['notification_email'] ?? '')); ?>" placeholder="admin@example.com">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="current_password">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="new_password">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" autocomplete="new-password">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="password_confirmation">Confirm New Password</label>
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" autocomplete="new-password">
                            </div>
                            <div class="col-12">
                                <small class="text-secondary">Enter a new password only if you want to change it. Your current password is required to save any setting.</small>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Save Settings</button>
                                <a class="btn btn-outline-secondary ms-2" href="/admin/settings.php">Clear</a>
                            </div>
                        </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
