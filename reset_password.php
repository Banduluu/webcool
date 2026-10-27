<?php
require_once __DIR__ . '/includes/admin_functions.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;
$message = null;

if ($token === '') {
    $error = 'This password reset link is invalid.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirmation'] ?? '';
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'Passwords do not match.';
    } else {
        $resetRecords = firebase_get('password_resets');
        $resetKey = null;
        $reset = null;
        $tokenHash = hash('sha256', $token);
        if (is_array($resetRecords)) {
            foreach ($resetRecords as $key => $record) {
                if (is_array($record) && hash_equals((string) ($record['token_hash'] ?? ''), $tokenHash)) {
                    $resetKey = $key;
                    $reset = $record;
                    break;
                }
            }
        }

        if ($resetKey === null || empty($reset['inspector_id']) || (int) ($reset['expires_at'] ?? 0) < time()) {
            $error = 'This password reset link is invalid or expired.';
        } else {
            $result = firebase_patch('users/inspectors/' . $reset['inspector_id'], [
                'password_hash' => hash_password($password),
                'updated_at' => date('c'),
            ]);
            if (is_array($result) && isset($result['error'])) {
                $error = 'Unable to update the password. Please try again.';
            } else {
                firebase_delete('password_resets/' . $resetKey);
                $message = 'Password updated successfully. You can now sign in.';
                $token = '';
            }
        }
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>
<div class="auth-page d-flex align-items-center justify-content-center min-vh-100 py-5 px-3">
    <div class="auth-card shadow-sm border-0 p-4 p-md-5 w-100">
        <div class="text-center mb-4">
            <div class="auth-logo text-white mb-3 mx-auto d-inline-flex align-items-center justify-content-center rounded-3">
                <i class="fa-solid fa-lock fa-lg"></i>
            </div>
            <span class="auth-kicker">Account recovery</span>
            <h1 class="h3 mb-2">Set a new password</h1>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($token !== ''): ?>
            <form method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <label class="form-label" for="password">New password</label>
                <input class="form-control form-control-lg mb-3" type="password" id="password" name="password" required minlength="8">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input class="form-control form-control-lg mb-4" type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
                <button class="btn btn-success w-100 py-2" type="submit">Update password</button>
            </form>
        <?php endif; ?>
        <a class="auth-forgot-link d-block text-center mt-4" href="/login.php">Back to login</a>
    </div>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
