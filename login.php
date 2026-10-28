<?php
require_once __DIR__ . '/includes/admin_functions.php';

if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /admin/dashboard.php');
    exit;
}

if (!empty($_SESSION['inspector_logged_in']) && $_SESSION['inspector_logged_in'] === true) {
    header('Location: /inspector/dashboard.php');
    exit;
}

$error = null;
$firebaseWeb = get_config()['firebase_web'] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_text($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (admin_login($username, $password)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        record_activity_log('Signed in');
        header('Location: /admin/dashboard.php');
        exit;
    }

    if (inspector_login($username, $password)) {
        $_SESSION['inspector_logged_in'] = true;
        $_SESSION['inspector_username'] = $username;
        record_activity_log('Signed in');
        header('Location: /inspector/dashboard.php');
        exit;
    }

    $error = 'Invalid username or password.';
}

require_once __DIR__ . '/includes/admin_header.php';
?>
<div class="auth-page d-flex align-items-center justify-content-center min-vh-100 py-5 px-3">
    <div class="auth-card shadow-sm border-0 p-4 p-md-5 w-100">
        <div class="text-center mb-4">
            <div class="auth-logo text-white mb-3 mx-auto d-inline-flex align-items-center justify-content-center rounded-3">
                <i class="fa-solid fa-shield-halved fa-lg"></i>
            </div>
            <span class="auth-kicker">Meat Inspection and Certification</span>
            <h1 class="h3 mb-2">Welcome back</h1>
            <p class="text-secondary mb-0">Sign in to access your dashboard.</p>
        </div>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">Email or username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button class="btn btn-outline-secondary auth-password-toggle" type="button" id="togglePassword" aria-label="Show password" aria-pressed="false">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="d-flex justify-content-end mb-4">
                <a class="auth-forgot-link" href="/forgot_password.php">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-success w-100 py-2">Sign in</button>
        </form>
        <div class="auth-divider"><span>or</span></div>
        <button type="button" class="btn btn-outline-dark w-100 py-2" id="googleSignIn">
            <i class="fa-brands fa-google me-2"></i>Continue with Google
        </button>
        <p class="text-center text-secondary small mt-4 mb-0">
            Don't have an account?
            <a class="auth-forgot-link" href="/account_register.php">Register as an inspector</a>
        </p>
    </div>
</div>
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
    import { getAuth, GoogleAuthProvider, signInWithPopup, signInWithEmailAndPassword } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';

    const firebaseConfig = {
        apiKey: <?php echo json_encode($firebaseWeb['api_key'] ?? ''); ?>,
        authDomain: <?php echo json_encode($firebaseWeb['auth_domain'] ?? ''); ?>,
        projectId: <?php echo json_encode($firebaseWeb['project_id'] ?? ''); ?>,
        storageBucket: <?php echo json_encode($firebaseWeb['storage_bucket'] ?? ''); ?>,
        messagingSenderId: <?php echo json_encode($firebaseWeb['messaging_sender_id'] ?? ''); ?>,
        appId: <?php echo json_encode($firebaseWeb['app_id'] ?? ''); ?>
    };
    const auth = getAuth(initializeApp(firebaseConfig));
    const loginForm = document.querySelector('.auth-card form');
    loginForm.addEventListener('submit', async function (event) {
        const email = document.getElementById('username').value.trim();
        if (!email.includes('@')) return;
        event.preventDefault();
        const button = loginForm.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const result = await signInWithEmailAndPassword(auth, email, document.getElementById('password').value);
            if (!result.user.emailVerified) {
                throw new Error('Please verify your email address using the link sent to your email before signing in.');
            }
            const token = await result.user.getIdToken();
            const response = await fetch('/firebase_session.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id_token: token, email: result.user.email})
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Unable to sign in.');
            window.location.href = data.redirect;
        } catch (error) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger mt-3';
            alert.textContent = error.message || 'Sign-in failed.';
            loginForm.after(alert);
        } finally {
            button.disabled = false;
        }
    });
    document.getElementById('googleSignIn').addEventListener('click', async function () {
        const emailInput = document.getElementById('username');
        const enteredEmail = emailInput.value.trim().toLowerCase();
        const button = this;
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(enteredEmail)) {
            emailInput.focus();
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger mt-3';
            alert.textContent = 'Enter the email address you will use for Google sign-in first.';
            loginForm.after(alert);
            return;
        }
        button.disabled = true;
        try {
            const result = await signInWithPopup(auth, new GoogleAuthProvider());
            const email = (result.user.email || '').toLowerCase();
            if (enteredEmail && enteredEmail !== email) {
                throw new Error('The Google account email does not match the email entered above.');
            }
            const token = await result.user.getIdToken();
            const response = await fetch('/firebase_session.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id_token: token, email: email, expected_email: enteredEmail})
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to sign in with Google.');
            }
            window.location.href = data.redirect;
        } catch (error) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger mt-3';
            alert.textContent = error.message || 'Google sign-in failed.';
            document.querySelector('.auth-card form').after(alert);
        } finally {
            button.disabled = false;
        }
    });
</script>
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const password = document.getElementById('password');
        const icon = this.querySelector('i');
        const isVisible = password.type === 'text';
        password.type = isVisible ? 'password' : 'text';
        icon.classList.toggle('fa-eye', isVisible);
        icon.classList.toggle('fa-eye-slash', !isVisible);
        this.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        this.setAttribute('aria-pressed', String(!isVisible));
    });
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
