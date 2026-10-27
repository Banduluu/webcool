<?php
require_once __DIR__ . '/includes/admin_functions.php';

$firebaseWeb = get_config()['firebase_web'] ?? [];

require_once __DIR__ . '/includes/admin_header.php';
?>
<div class="auth-page d-flex align-items-center justify-content-center min-vh-100 py-5 px-3">
    <div class="auth-card shadow-sm border-0 p-4 p-md-5 w-100">
        <div class="text-center mb-4">
            <div class="auth-logo text-white mb-3 mx-auto d-inline-flex align-items-center justify-content-center rounded-3">
                <i class="fa-solid fa-key fa-lg"></i>
            </div>
            <span class="auth-kicker">Account recovery</span>
            <h1 class="h3 mb-2">Forgot password?</h1>
            <p class="text-secondary mb-0">Enter your account email and we will send a reset link.</p>
        </div>
        <div id="resetMessage" class="alert d-none"></div>
        <form id="forgotPasswordForm">
            <label class="form-label" for="email">Email address</label>
            <input class="form-control form-control-lg mb-4" type="email" id="email" name="email" required>
            <button class="btn btn-success w-100 py-2" type="submit">Send reset link</button>
        </form>
        <a class="auth-forgot-link d-block text-center mt-4" href="/login.php">Back to login</a>
    </div>
</div>
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
    import { getAuth, sendPasswordResetEmail } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';

    const firebaseConfig = {
        apiKey: <?php echo json_encode($firebaseWeb['api_key'] ?? ''); ?>,
        authDomain: <?php echo json_encode($firebaseWeb['auth_domain'] ?? ''); ?>,
        projectId: <?php echo json_encode($firebaseWeb['project_id'] ?? ''); ?>,
        storageBucket: <?php echo json_encode($firebaseWeb['storage_bucket'] ?? ''); ?>,
        messagingSenderId: <?php echo json_encode($firebaseWeb['messaging_sender_id'] ?? ''); ?>,
        appId: <?php echo json_encode($firebaseWeb['app_id'] ?? ''); ?>
    };
    const auth = getAuth(initializeApp(firebaseConfig));
    const form = document.getElementById('forgotPasswordForm');
    const message = document.getElementById('resetMessage');
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const email = document.getElementById('email').value.trim().toLowerCase();
        const button = form.querySelector('button');
        button.disabled = true;
        message.className = 'alert d-none';
        try {
            await sendPasswordResetEmail(auth, email);
            message.textContent = 'Password reset instructions were sent. Check your email, including the spam folder.';
            message.className = 'alert alert-success';
        } catch (error) {
            message.textContent = error.code === 'auth/user-not-found'
                ? 'No Firebase account was found with that email address.'
                : (error.message || 'Unable to send the password reset email.');
            message.className = 'alert alert-danger';
        } finally {
            button.disabled = false;
        }
    });
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
