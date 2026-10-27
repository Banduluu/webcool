<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_inspector_logged_in();

$inspectionId = $_GET['inspection_id'] ?? '';
$inspection = null;
$inspections = firebase_get('inspections');
$inspections = is_array($inspections) ? $inspections : [];
$inspectionKey = null;

foreach ($inspections as $key => $item) {
    if (!empty($item['inspection_id']) && $item['inspection_id'] === $inspectionId) {
        $inspection = $item;
        $inspectionKey = $key;
        break;
    }
}

if (!$inspection) {
    header('Location: /inspector/dashboard.php');
    exit;
}

$registration = null;
$registrations = get_array_values(firebase_get('client_registrations'));
foreach ($registrations as $regItem) {
    if (!empty($inspection['registration_key']) && ($regItem['registration_key'] ?? '') === $inspection['registration_key']) {
        $registration = $regItem;
        break;
    }
    if (!empty($inspection['registration_id']) && !empty($regItem['inspection_id']) && $regItem['inspection_id'] === $inspection['registration_id']) {
        $registration = $regItem;
        break;
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/inspector_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">Inspection Certificate</h3>
                <p class="text-secondary mb-0">Print or export the inspection certificate for a passed inspection.</p>
            </div>
            <div class="d-flex gap-2 no-print">
                <a href="/inspector/certificates.php" class="btn btn-outline-secondary">Back</a>
                <button class="btn btn-primary" onclick="window.print();"><i class="fa-solid fa-print me-2"></i>Print Certificate</button>
            </div>
        </div>

        <div class="card shadow-sm border-0 certificate-card">
            <div class="card-body p-5" id="certificate">
                <div class="text-center mb-4">
                    <p class="certificate-title mb-2">Certificate of Inspection</p>
                    <h2 class="fw-bold">Meat Inspection & Certification</h2>
                    <p class="text-secondary">Certificate of Inspection and Food Safety Compliance</p>
                </div>

                <div class="row mb-4 certificate-section">
                    <div class="col-md-6">
                        <p class="mb-1 text-secondary">Certificate ID</p>
                        <h5 class="fw-semibold"><?php echo htmlspecialchars($inspection['certificate_id'] ?? 'N/A'); ?></h5>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-1 text-secondary">Inspection ID</p>
                        <h5 class="fw-semibold"><?php echo htmlspecialchars($inspection['inspection_id']); ?></h5>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-1 text-secondary">Owner / Farmer</p>
                        <h5 class="fw-semibold"><?php echo htmlspecialchars($registration['owner_name'] ?? 'N/A'); ?></h5>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-1 text-secondary">Animal Type</p>
                        <h5 class="fw-semibold"><?php echo htmlspecialchars($registration['animal_type'] ?? 'N/A'); ?></h5>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <p class="mb-1 text-secondary">Inspection Date</p>
                                <h5 class="fw-semibold"><?php echo htmlspecialchars($inspection['created_at'] ?? 'N/A'); ?></h5>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-1 text-secondary">Decision</p>
                                <h5 class="fw-semibold"><?php echo htmlspecialchars($inspection['decision'] ?? 'N/A'); ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="certificate-qr p-3 rounded-3 border bg-white">
                            <img src="<?php echo inspection_qr_url($inspection['inspection_id']); ?>" alt="Inspection QR Code" class="img-fluid mb-2" />
                            <p class="small text-secondary mb-0">Scan to verify</p>
                        </div>
                    </div>
                </div>

                <div class="border rounded-3 p-4 mb-4 bg-light">
                    <h6 class="mb-3">Inspection Findings</h6>
                    <div class="row gy-3">
                        <div class="col-md-6">
                            <p class="text-secondary mb-1">Slaughter</p>
                            <p class="mb-0"><?php echo htmlspecialchars(implode(', ', array_filter(array_map('trim', (array) ($inspection['inspection_data']['slaughter'] ?? []))))); ?></p>
                        </div>
                    </div>
                </div>

                <div class="row gy-3">
                    <div class="col-md-6">
                        <p class="mb-1 text-secondary">Final Remarks</p>
                        <p><?php echo nl2br(htmlspecialchars($inspection['final_remarks'] ?? 'No additional remarks.')); ?></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-1 text-secondary">Inspector</p>
                        <h6 class="fw-semibold"><?php echo htmlspecialchars($inspection['inspector_name'] ?? get_current_inspector_name()); ?></h6>
                        <p class="text-secondary">Signature:</p>
                        <div class="border-top pt-3 mt-2"></div>
                    </div>
                </div>

                <div class="text-center mt-5">
                    <small class="text-secondary">This certificate verifies that the inspected animal has met the standards required by the Meat Inspection and Certification System (MIC System).</small>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>