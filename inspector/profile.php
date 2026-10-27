<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_inspector_logged_in();

$currentEmail = strtolower(trim((string) ($_SESSION['inspector_username'] ?? '')));
$profile = null;
$profileKey = null;
$errors = [];
$message = null;

$inspectors = firebase_get('users/inspectors');
if (is_array($inspectors)) {
    foreach ($inspectors as $key => $inspector) {
        if (
            is_array($inspector)
            && (
                strtolower(trim((string) ($inspector['email'] ?? ''))) === $currentEmail
                || strtolower(trim((string) ($inspector['username'] ?? ''))) === $currentEmail
            )
        ) {
            $profile = $inspector;
            $profileKey = $key;
            break;
        }
    }

    $legacyUsername = strtolower(trim((string) (get_config()['inspector_user']['username'] ?? '')));
    $inspectorProfiles = array_values(array_filter($inspectors, 'is_array'));
    if ($profile === null && $legacyUsername !== '' && $currentEmail === $legacyUsername && count($inspectorProfiles) === 1) {
        foreach ($inspectors as $key => $inspector) {
            if (is_array($inspector)) {
                $profile = $inspector;
                $profileKey = $key;
                break;
            }
        }
    }
}

if ($profile === null) {
    $errors[] = 'Your inspector profile could not be found.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profile !== null && $profileKey !== null) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $position = trim((string) ($_POST['position'] ?? 'Meat Inspector'));

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($username === '') {
        $errors[] = 'Username is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($phone === '') {
        $errors[] = 'Contact number is required.';
    }
    if ($position === '') {
        $errors[] = 'Position is required.';
    }

    if (empty($errors)) {
        $fields = [
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'position' => $position,
            'updated_at' => date('c'),
        ];

        $result = firebase_patch('users/inspectors/' . $profileKey, $fields);
        if (is_array($result) && !isset($result['error'])) {
            $profile = array_merge($profile, $fields);
            $_SESSION['inspector_username'] = $email;
            $currentEmail = $email;
            $message = 'Profile updated successfully.';
        } else {
            $errors[] = 'Unable to update your profile. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/inspector_navbar.php'; ?>
    <main class="container-fluid px-3 px-md-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="mb-2">My Profile</h1>
                <p class="text-secondary mb-0">Update your inspector account information.</p>
            </div>
            <a href="/inspector/dashboard.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($profile !== null): ?>
            <div class="card shadow-sm border-0 inspector-profile-card">
                <div class="inspector-profile-hero">
                    <span class="inspector-profile-avatar"><i class="fa-regular fa-user"></i></span>
                    <h2><?php echo htmlspecialchars($profile['name'] ?? 'Inspector Name'); ?></h2>
                    <p><?php echo htmlspecialchars($profile['position'] ?? 'Meat Inspector'); ?></p>
                    <span class="badge bg-success">Active Account</span>
                </div>
                <div class="card-body p-4">
                    <form method="post">
                        <h5 class="mb-3">Personal Information</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Full Name</label>
                                <input class="form-control" type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? $profile['name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="username">Username</label>
                                <input class="form-control" type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? $profile['username'] ?? $profile['email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email Address</label>
                                <input class="form-control" type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? $profile['email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Contact Number</label>
                                <input class="form-control" type="text" id="phone" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? $profile['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="position">Position</label>
                                <input class="form-control" type="text" id="position" name="position" required value="<?php echo htmlspecialchars($_POST['position'] ?? $profile['position'] ?? 'Meat Inspector'); ?>">
                            </div>
                        </div>
                        <hr class="my-4">
                        <h5 class="mb-3">Account Security</h5>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <i class="fa-solid fa-lock text-success"></i>
                            <div><strong>Password</strong><small class="d-block text-secondary">Change your account password through Firebase account recovery.</small></div>
                        </div>
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <i class="fa-regular fa-calendar-check text-success"></i>
                            <div><strong>Account Created</strong><small class="d-block text-secondary"><?php echo htmlspecialchars($profile['created_at'] ?? 'Date unavailable'); ?></small></div>
                        </div>
                        <button type="submit" class="btn btn-success w-100"><i class="fa-solid fa-floppy-disk me-2"></i>Save Profile</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
