<?php
require_once __DIR__ . '/includes/admin_functions.php';

$firebaseWeb = get_config()['firebase_web'] ?? [];
require_once __DIR__ . '/includes/admin_header.php';
?>
<div class="auth-page d-flex align-items-center justify-content-center min-vh-100 py-5 px-3">
    <div class="auth-card shadow-sm border-0 p-4 p-md-5 w-100">
        <div class="text-center mb-4">
            <div class="auth-logo text-white mb-3 mx-auto d-inline-flex align-items-center justify-content-center rounded-3">
                <i class="fa-solid fa-user-plus fa-lg"></i>
            </div>
            <span class="auth-kicker">Inspector account</span>
            <h1 class="h3 mb-2">Create an account</h1>
            <p class="text-secondary mb-0">Register with email and password or Google.</p>
        </div>
        <div id="registerMessage" class="alert d-none"></div>
        <form id="registerForm">
            <label class="form-label" for="registerEmail">Email address</label>
            <input class="form-control form-control-lg mb-3" type="email" id="registerEmail" required>
            <label class="form-label" for="registerPassword">Password</label>
            <input class="form-control form-control-lg mb-3" type="password" id="registerPassword" minlength="8" required>
            <label class="form-label" for="registerPasswordConfirmation">Confirm password</label>
            <input class="form-control form-control-lg mb-4" type="password" id="registerPasswordConfirmation" minlength="8" required>
            <button class="btn btn-success w-100 py-2" type="submit">Create inspector account</button>
        </form>
        <div class="auth-divider"><span>or</span></div>
        <button class="btn btn-outline-dark w-100 py-2" id="googleRegister" type="button">
            <i class="fa-brands fa-google me-2"></i>Register with Google
        </button>
        <p class="text-center small mt-4 mb-0"><a class="auth-forgot-link" href="/login.php">Back to login</a></p>
    </div>
</div>
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
    import { getAuth, GoogleAuthProvider, createUserWithEmailAndPassword, sendEmailVerification, signInWithPopup } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';

    const auth = getAuth(initializeApp({
        apiKey: <?php echo json_encode($firebaseWeb['api_key'] ?? ''); ?>,
        authDomain: <?php echo json_encode($firebaseWeb['auth_domain'] ?? ''); ?>,
        projectId: <?php echo json_encode($firebaseWeb['project_id'] ?? ''); ?>,
        storageBucket: <?php echo json_encode($firebaseWeb['storage_bucket'] ?? ''); ?>,
        messagingSenderId: <?php echo json_encode($firebaseWeb['messaging_sender_id'] ?? ''); ?>,
        appId: <?php echo json_encode($firebaseWeb['app_id'] ?? ''); ?>
    }));
    const message = document.getElementById('registerMessage');
    function showMessage(text, type) {
        message.textContent = text;
        message.className = 'alert alert-' + type;
    }
    async function finishRegistration(user) {
        const token = await user.getIdToken();
        const response = await fetch('/firebase_session.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id_token: token, email: user.email, expected_email: document.getElementById('registerEmail').value.trim().toLowerCase(), register: true})
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Unable to create the account.');
        window.location.href = data.redirect;
    }
    document.getElementById('registerForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const email = document.getElementById('registerEmail').value.trim();
        const password = document.getElementById('registerPassword').value;
        const confirmation = document.getElementById('registerPasswordConfirmation').value;
        if (password !== confirmation) return showMessage('Passwords do not match.', 'danger');
        try {
            const result = await createUserWithEmailAndPassword(auth, email, password);
            await sendEmailVerification(result.user);
            showMessage('Verification email sent. Open it and verify your email before signing in.', 'success');
        } catch (error) {
            showMessage(error.message || 'Registration failed.', 'danger');
        }
    });
    document.getElementById('googleRegister').addEventListener('click', async function () {
        const enteredEmail = document.getElementById('registerEmail').value.trim().toLowerCase();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(enteredEmail)) {
            document.getElementById('registerEmail').focus();
            return showMessage('Enter the email address you will use for Google registration first.', 'danger');
        }
        try {
            const result = await signInWithPopup(auth, new GoogleAuthProvider());
            if ((result.user.email || '').toLowerCase() !== enteredEmail) {
                throw new Error('The Google account email must exactly match the email entered above.');
            }
            await finishRegistration(result.user);
        } catch (error) {
            showMessage(error.message || 'Google registration failed.', 'danger');
        }
    });
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
