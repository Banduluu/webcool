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
            record_activity_log('Updated administrator settings');
            $message = 'Settings saved successfully.';
        }
    }
}

$activityLogsResult = firebase_request('GET', 'activity_logs');
$activityLogsError = is_array($activityLogsResult) && isset($activityLogsResult['error']);
$activityLogs = !$activityLogsError && is_array($activityLogsResult)
    ? array_values(array_filter($activityLogsResult, 'is_array'))
    : [];
usort($activityLogs, static function (array $first, array $second): int {
    return strcmp((string) ($second['created_at'] ?? ''), (string) ($first['created_at'] ?? ''));
});
$activityLogs = array_slice($activityLogs, 0, 100);

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

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Activity Logs</h5>
                        <p class="text-secondary small mb-0">Recent account and record changes, newest first.</p>
                    </div>
                    <span class="badge text-bg-secondary"><?php echo count($activityLogs); ?> recent</span>
                </div>
                <?php if ($activityLogsError): ?>
                    <div class="alert alert-danger mb-0">Unable to load activity logs from Firebase. Please try again later.</div>
                <?php elseif (!$activityLogs): ?>
                    <div class="alert alert-light border mb-0">No activity has been recorded yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Date and Time</th>
                                    <th scope="col">User</th>
                                    <th scope="col">Action</th>
                                    <th scope="col">Record</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activityLogs as $activityLog):
                                    $activityTimestamp = strtotime((string) ($activityLog['created_at'] ?? ''));
                                ?>
                                    <tr>
                                        <td><?php echo $activityTimestamp !== false ? htmlspecialchars(date('M d, Y h:i A', $activityTimestamp)) : 'N/A'; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars((string) ($activityLog['actor_name'] ?? 'Unknown')); ?>
                                            <span class="text-secondary small">(<?php echo htmlspecialchars((string) ($activityLog['actor_role'] ?? '')); ?>)</span>
                                        </td>
                                        <td><?php echo htmlspecialchars((string) ($activityLog['action'] ?? 'Activity')); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($activityLog['resource'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
