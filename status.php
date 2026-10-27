<?php
require_once __DIR__ . '/includes/firebase.php';

$currentStatus = '';
$inspectionId = '';
$errorMessage = '';
$isApproved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inspectionId = trim($_POST['inspection_id'] ?? '');
    if ($inspectionId === '') {
        $errorMessage = 'Inspection ID is required.';
    } else {
        $inspectionRequest = firebase_get('inspection_requests/' . $inspectionId);
        if (is_array($inspectionRequest) && isset($inspectionRequest['status'])) {
            $currentStatus = $inspectionRequest['status'];
            $isApproved = $currentStatus === 'Approved';
        } else {
            $errorMessage = 'Inspection ID not found. Please verify the code.';
        }
    }
}

require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-3">
                            <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Track Status</li>
                        </ol>
                    </nav>
                    <h2 class="h4 text-primary-custom mb-2">Track Inspection Status</h2>
                    <p class="text-muted">Enter your Inspection ID to see the current status and certificate details.</p>
                    <form method="post" class="row g-3 mt-3 needs-validation" novalidate>
                        <div class="col-md-9">
                            <label for="inspection_id" class="form-label">Inspection ID</label>
                            <input type="text" name="inspection_id" id="inspection_id" class="form-control" value="<?php echo htmlspecialchars($inspectionId); ?>" required>
                            <div class="invalid-feedback">Inspection ID is required.</div>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-primary mt-4">Check Status</button>
                        </div>
                    </form>

                    <?php if ($errorMessage !== ''): ?>
                        <div class="alert alert-danger mt-4"><?php echo htmlspecialchars($errorMessage); ?></div>
                    <?php elseif ($currentStatus !== ''): ?>
                        <div class="alert alert-info mt-4">
                            <h5 class="mb-2">Current Status</h5>
                            <p class="mb-0"><strong><?php echo htmlspecialchars($currentStatus); ?></strong></p>
                        </div>
                        <?php if ($isApproved): ?>
                            <div class="card mt-3 shadow-sm border-0">
                                <div class="card-body">
                                    <h5 class="card-title">Certificate Available</h5>
                                    <p class="card-text">Your inspection has been approved. You can view, download, or print the certificate.</p>
                                    <a href="/certificate.php?id=<?php echo urlencode($inspectionId); ?>" class="btn btn-primary">View Certificate</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
