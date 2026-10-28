<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$inspectionId = $_GET['inspection_id'] ?? '';
$inspection = null;
$inspectionsRaw = firebase_get('inspections');
$inspections = is_array($inspectionsRaw) ? $inspectionsRaw : [];
$inspectionKey = null;

foreach ($inspections as $key => $item) {
    if (!empty($item['inspection_id']) && $item['inspection_id'] === $inspectionId) {
        $inspection = $item;
        $inspectionKey = $key;
        break;
    }
}

if (!$inspection) {
    header('Location: /admin/mic_viewer.php');
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
$inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
$controlNumber = trim((string) ($inspection['control_number'] ?? $inspectionData['control_number'] ?? ''));

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">MIC Certificate</h3>
                <p class="text-secondary mb-0">View the MIC form, scan the QR, and print or download it as PDF.</p>
            </div>
            <div class="d-flex gap-2 no-print">
                <a href="/admin/mic_viewer.php" class="btn btn-outline-secondary">Back to MIC Viewer</a>
                <button class="btn btn-primary" onclick="window.print();"><i class="fa-solid fa-print me-2"></i>Print</button>
                <button class="btn btn-success" onclick="downloadCertificatePdf();"><i class="fa-solid fa-download me-2"></i>Download PDF</button>
            </div>
        </div>

        <div class="certificate-preview-card certificate-admin-view" id="certificate">
            <div class="certificate-preview-paper">
                <header class="certificate-letterhead">
                    <p>Republic of the Philippines<br>City of Lipa</p>
                    <h1>OFFICE OF THE CITY VETERINARIAN</h1>
                    <p class="certificate-control-number"><strong>CN:</strong> <?php echo htmlspecialchars($controlNumber !== '' ? $controlNumber : '________'); ?></p>
                    <p class="certificate-letterhead-office">LIPA CITY SLAUGHTERHOUSE</p>
                    <p class="certificate-letterhead-accreditation">NMIS ACCREDITATION NO. SH-989-AA</p>
                </header>
                <h2>MEAT INSPECTION CERTIFICATE</h2>
                <p>This certifies that meat/meat by products described below are slaughtered in accordance with the Meat Inspection Code of the Philippines and at the time and date of inspection are found fit for human consumption.</p>

                <?php
                $meatDetails = $inspection['inspection_data']['meat_details'] ?? [];
                $ownerDealer = $inspection['inspection_data']['owner_dealer'] ?? $registration['owner_name'] ?? $inspection['customer_name'] ?? '';
                if (empty($ownerDealer) && !empty($inspection['inspection_data']['customer']['owner_name'])) {
                    $ownerDealer = $inspection['inspection_data']['customer']['owner_name'];
                }
                $origin = 'FMIMC';
                $destination = 'Lipa';
                $dateIssued = $inspectionData['date_issued'] ?? $inspection['inspection_date'] ?? substr($inspection['created_at'] ?? '', 0, 10);
                $timeIssued = $inspectionData['time_issued'] ?? '';
                if (empty($timeIssued) && !empty($inspection['inspection_data']['customer']['time_issued'])) {
                    $timeIssued = $inspection['inspection_data']['customer']['time_issued'];
                }
                ?>
                <div class="table-responsive mb-4">
                    <table class="certificate-table w-100 text-uppercase">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Type of Meat/Entrails</th>
                                <th style="width: 25%;">Quantity</th>
                                <th style="width: 25%;">Weight</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $meatTypes = [
                                'PORK' => 'pork',
                                'BEEF' => 'beef',
                                'CARABEEF' => 'carabeef',
                                'ENTRAILS' => 'entrails',
                            ];
                            foreach ($meatTypes as $label => $key):
                                $qty = $meatDetails[$key]['qty'] ?? ($inspection['qty_' . $key] ?? '');
                                $weight = $meatDetails[$key]['weight'] ?? ($inspection['weight_' . $key] ?? '');
                            ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo $label; ?></td>
                                    <td><?php echo htmlspecialchars($qty !== '' ? $qty : 'hd/s'); ?></td>
                                    <td><?php echo htmlspecialchars($weight !== '' ? $weight : 'kls.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <dl>
                    <dt>Name of Owner (Dealer)</dt><dd><?php echo htmlspecialchars($ownerDealer); ?></dd>
                    <dt>Point of Origin</dt><dd><?php echo htmlspecialchars($origin); ?></dd>
                    <dt>Destination</dt><dd><?php echo htmlspecialchars($destination); ?></dd>
                    <dt>Time Issued</dt><dd><?php echo htmlspecialchars($timeIssued); ?></dd>
                    <dt>Date Issued</dt><dd><?php echo htmlspecialchars($dateIssued); ?></dd>
                </dl>
                <div class="certificate-preview-signatures"><span>Owner/Dealer</span><span>Meat Inspector</span></div>
                <div class="certificate-preview-qr">
                    <img src="<?php echo htmlspecialchars(inspection_qr_url($inspection['inspection_id'] ?? $inspectionId)); ?>" alt="QR code for inspection <?php echo htmlspecialchars($inspection['inspection_id'] ?? $inspectionId); ?>">
                    <span>Scan to verify inspection</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
<script>
function downloadCertificatePdf() {
    const element = document.getElementById('certificate');
    const opt = {
        margin: 0.5,
        filename: 'MIC-<?php echo addslashes($inspection['inspection_id']); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>
