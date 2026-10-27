<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$inspectionId = trim((string) ($_GET['inspection_id'] ?? ''));
$inspectionsRaw = firebase_get('inspections');
$inspections = is_array($inspectionsRaw) ? $inspectionsRaw : [];
$inspection = null;
$inspectionKey = null;

foreach ($inspections as $key => $item) {
    if (is_array($item) && ($item['inspection_id'] ?? '') === $inspectionId) {
        $inspection = $item;
        $inspectionKey = $key;
        break;
    }
}

if (!$inspection) {
    header('Location: /admin/mic_viewer.php');
    exit;
}

$inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
$meatDetails = is_array($inspectionData['meat_details'] ?? null) ? $inspectionData['meat_details'] : [];
$message = null;
$error = null;
$meatTypes = [
    'pork' => 'PORK',
    'beef' => 'BEEF',
    'carabeef' => 'CARABEEF',
    'entrails' => 'ENTRAILS',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_mic') {
    $updatedMeatDetails = [];
    foreach ($meatTypes as $key => $label) {
        $updatedMeatDetails[$key] = [
            'qty' => trim((string) ($_POST['qty_' . $key] ?? '')),
            'weight' => trim((string) ($_POST['weight_' . $key] ?? '')),
        ];
    }

    $updatedData = array_merge($inspectionData, [
        'meat_details' => $updatedMeatDetails,
        'owner_dealer' => trim((string) ($_POST['owner_dealer'] ?? '')),
        'origin' => 'FMIMC',
        'destination' => 'Lipa',
        'date_issued' => trim((string) ($_POST['date_issued'] ?? '')),
        'time_issued' => trim((string) ($_POST['time_issued'] ?? '')),
    ]);

    if (firebase_patch('inspections/' . $inspectionKey, ['inspection_data' => $updatedData]) !== null) {
        header('Location: /admin/mic_viewer.php?date=' . urlencode(substr((string) ($inspection['inspection_date'] ?? $inspection['created_at'] ?? ''), 0, 10)));
        exit;
    }
    $error = 'Unable to save the MIC. Please try again.';
}

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">Edit MIC</h3>
                <p class="text-secondary mb-0">Update the certificate information for <?php echo htmlspecialchars($inspectionId); ?>.</p>
            </div>
            <a href="/admin/mic_viewer.php" class="btn btn-outline-secondary">Back to MIC Viewer</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="save_mic">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3">Certificate Details</h5>
                    <div class="row g-3">
                        <?php foreach ($meatTypes as $key => $label): ?>
                            <div class="col-12">
                                <label class="form-label fw-semibold"><?php echo $label; ?></label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input class="form-control" name="qty_<?php echo $key; ?>" placeholder="Quantity" value="<?php echo htmlspecialchars($meatDetails[$key]['qty'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <input class="form-control" name="weight_<?php echo $key; ?>" placeholder="Weight" value="<?php echo htmlspecialchars($meatDetails[$key]['weight'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-md-6">
                            <label class="form-label">Owner / Dealer</label>
                            <input class="form-control" name="owner_dealer" value="<?php echo htmlspecialchars($inspectionData['owner_dealer'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Origin</label>
                            <input class="form-control" name="origin" value="FMIMC" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Destination</label>
                            <input class="form-control" name="destination" value="Lipa" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date Issued</label>
                            <input type="date" class="form-control" name="date_issued" value="<?php echo htmlspecialchars($inspectionData['date_issued'] ?? substr((string) ($inspection['created_at'] ?? ''), 0, 10)); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Time Issued</label>
                            <input type="time" class="form-control" name="time_issued" value="<?php echo htmlspecialchars($inspectionData['time_issued'] ?? ''); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success mt-4">Save MIC</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
