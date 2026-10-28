<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_inspector_logged_in();

$customerId = trim($_POST['customer_id'] ?? $_GET['customer_id'] ?? '');
$customer = null;
$error = null;
$message = null;
$inspectionSaved = false;
$scanMode = true;
$findCustomer = static function (array $customers, string $scannedValue): array {
    $candidates = [trim($scannedValue)];
    $parsedUrl = parse_url($scannedValue);
    if (is_array($parsedUrl)) {
        if (!empty($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
            foreach (['customer_id', 'customerId', 'id', 'owner_code', 'ownerCode', 'customer_code', 'code', 'data', 'value'] as $field) {
                if (isset($query[$field]) && is_scalar($query[$field])) {
                    $candidates[] = trim((string) $query[$field]);
                }
            }
        }
        if (!empty($parsedUrl['path'])) {
            $candidates[] = trim(basename(rtrim($parsedUrl['path'], '/')));
        }
    }

    $decoded = json_decode($scannedValue, true);
    if (is_array($decoded)) {
        foreach (['customer_id', 'customerId', 'id', 'owner_code', 'ownerCode', 'customer_code', 'code', 'data', 'value'] as $field) {
            if (isset($decoded[$field]) && is_scalar($decoded[$field])) {
                $candidates[] = trim((string) $decoded[$field]);
            }
        }
    }
    $candidates = array_values(array_unique(array_filter($candidates, static fn(string $candidate): bool => $candidate !== '')));

    foreach ($candidates as $candidate) {
        $matches = array_values(array_filter($customers, static function ($item) use ($candidate): bool {
            return is_array($item) && (string) ($item['customer_id'] ?? '') === $candidate;
        }));
        if (count($matches) === 1) {
            return ['customer' => $matches[0], 'ambiguous' => false];
        }
        if (count($matches) > 1) {
            return ['customer' => null, 'ambiguous' => true];
        }
    }

    foreach ($candidates as $candidate) {
        $matches = array_values(array_filter($customers, static function ($item) use ($candidate): bool {
            return is_array($item)
                && in_array($candidate, [
                    (string) ($item['owner_code'] ?? ''),
                    (string) ($item['customer_code'] ?? ''),
                ], true);
        }));
        if (count($matches) === 1) {
            return ['customer' => $matches[0], 'ambiguous' => false];
        }
        if (count($matches) > 1) {
            return ['customer' => null, 'ambiguous' => true];
        }
    }

    return ['customer' => null, 'ambiguous' => false];
};

$showMicSection = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $customersResult = firebase_request('get', 'customers');
    $customerLookupFailed = is_array($customersResult) && isset($customersResult['error']);
    $customers = $customerLookupFailed || !is_array($customersResult) ? [] : $customersResult;

    if ($_POST['action'] === 'find_customer') {
        if ($customerId === '') {
            $error = 'Please scan a QR code or enter a Customer ID.';
        } else {
            $customerLookup = $findCustomer($customers, $customerId);
            $customer = $customerLookup['customer'];

            if (!$customer) {
                $error = $customerLookupFailed
                    ? 'Customer database is unavailable. Check the Firebase connection and try again.'
                    : ($customerLookup['ambiguous']
                        ? 'This customer code matches more than one profile. Search using the unique customer ID instead.'
                        : 'No customer matched the scanned value "' . htmlspecialchars($customerId) . '". Scan the customer QR or enter their customer ID / owner code.');
            } else {
                $customerId = (string) ($customer['customer_id'] ?? '');
                $scanMode = false;
            }
        }
    }

    if ($_POST['action'] === 'save_mic') {
        $scanMode = false;
        $customerId = trim($_POST['customer_id'] ?? '');
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerContact = trim($_POST['customer_contact'] ?? '');
        $customerOrigin = trim($_POST['customer_origin'] ?? '');
        if ($customerId === '') {
            $error = 'Customer ID is missing. Please scan a QR code again.';
        } else {
            $customerLookup = $findCustomer($customers, $customerId);
            $customer = $customerLookup['customer'];

            if (!$customer) {
                $error = $customerLookupFailed
                    ? 'Customer database is unavailable. Check the Firebase connection and try again.'
                    : ($customerLookup['ambiguous']
                        ? 'This customer code matches more than one profile. Search using the unique customer ID instead.'
                        : 'No customer matched the submitted value. Scan the customer QR or enter their customer ID / owner code.');
                $showMicSection = false;
            } else {
                $customerId = (string) ($customer['customer_id'] ?? '');
                $inspectionRecords = firebase_request('get', 'inspections');
                if (is_array($inspectionRecords) && isset($inspectionRecords['error'])) {
                    $error = 'Inspection database is unavailable. Check the Firebase connection and try again.';
                } elseif ($inspectionRecords !== null && !is_array($inspectionRecords)) {
                    $error = 'Inspection records could not be read. Please try again.';
                } else {
                    $inspections = get_array_values($inspectionRecords);
                $inspectionId = generate_inspection_id($inspections);
                $batchId = generate_batch_id($inspections);
                $inspection = [
                    'inspection_id' => $inspectionId,
                    'batch_id' => $batchId,
                    'inspection_date' => date('Y-m-d'),
                    'inspector_name' => get_current_inspector_name(),
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'customer_contact' => $customerContact,
                    'customer_origin' => $customerOrigin,
                    'created_at' => date('Y-m-d H:i:s'),
                    'status' => 'MIC',
                    'decision' => null,
                    'qr_code_value' => $inspectionId,
                    'inspection_data' => [
                        'customer' => $customer,
                        'meat_details' => [],
                        'origin' => 'FMIMC',
                        'destination' => 'Lipa',
                        'date_issued' => trim($_POST['date_issued'] ?? date('Y-m-d')),
                        'time_issued' => trim($_POST['time_issued'] ?? date('H:i')),
                        'owner_dealer' => trim($_POST['owner_dealer'] ?? $customerName),
                        'received_by' => trim($_POST['received_by'] ?? ''),
                    ],
                    'certificate_id' => null,
                ];

                foreach (['pork', 'beef', 'carabeef', 'entrails'] as $meatType) {
                    $qty = trim($_POST['qty_' . $meatType] ?? '');
                    $weight = trim($_POST['weight_' . $meatType] ?? '');
                    $inspection['inspection_data']['meat_details'][$meatType] = [
                        'qty' => $qty,
                        'weight' => $weight,
                    ];
                    $inspection['qty_' . $meatType] = $qty;
                    $inspection['weight_' . $meatType] = $weight;
                }

                // compute total heads for this MIC inspection and store it on the record
                $totalHeads = 0;
                foreach (['pork', 'beef', 'carabeef', 'entrails'] as $meatType) {
                    $totalHeads += (int) ($inspection['inspection_data']['meat_details'][$meatType]['qty'] ?? 0);
                }
                $inspection['head_count'] = $totalHeads;
                $inspection['inspection_data']['meat_summary'] = ['no_of_heads' => $totalHeads];

                $pushResult = firebase_post('inspections', $inspection);
                if (!empty($pushResult['name'])) {
                    $message = 'MIC inspection saved. Inspection ID: ' . htmlspecialchars($inspectionId) . '. Batch ' . htmlspecialchars($batchId) . ' created.';
                    $inspectionSaved = true;

                } else {
                    $error = 'Failed to save the inspection. Please try again.';
                }
                }
            }
        }
    }
}

if ($customer === null && $customerId !== '') {
    $customersResult = firebase_request('get', 'customers');
    $customers = is_array($customersResult) && !isset($customersResult['error']) ? $customersResult : [];
    foreach ($customers as $item) {
        if (is_array($item) && ($item['customer_id'] ?? '') === $customerId) {
            $customer = $item;
            $scanMode = false;
            break;
        }
    }
}

$recentScans = [];
$recentInspections = get_array_values(firebase_get('inspections'));
foreach ($recentInspections as $recentInspection) {
    if (is_array($recentInspection) && !empty($recentInspection['customer_id'])) {
        $recentScans[] = $recentInspection;
    }
}
usort($recentScans, fn($first, $second) => strtotime($second['created_at'] ?? '') <=> strtotime($first['created_at'] ?? ''));
$recentScans = array_slice($recentScans, 0, 5);

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="main-content inspector-scanner-page">
    <?php require_once __DIR__ . '/../includes/inspector_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">Customer QR Scanner</h3>
                <p class="text-secondary mb-0">Scan a customer's QR code to load their profile and start the MIC form.</p>
            </div>
            <a href="/inspector/dashboard.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="row g-4 inspector-scanner-panels">
            <div class="col-xl-5">
                <div id="scanSection" class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><i class="fa-solid fa-qrcode text-success me-2"></i>QR Code Scanner</h5>
                        <form id="scanForm" method="post">
                            <input type="hidden" name="action" value="find_customer">
                            <input type="hidden" id="customer_id" name="customer_id" value="<?php echo htmlspecialchars($customerId); ?>">
                            <button type="button" id="startScanBtn" class="btn btn-success w-100 mb-3"><i class="fa-solid fa-camera me-2"></i>Start Camera Scan</button>
                            <div id="scannerPreview" class="d-none mb-3">
                                <video id="scannerVideo" class="w-100 rounded-3 border" playsinline muted></video>
                                <canvas id="scannerCanvas" class="d-none"></canvas>
                                <p class="text-secondary small mt-2 mb-0">Point your camera at the customer QR code until it is detected.</p>
                            </div>
                            <div class="inspector-scan-frame mb-3">
                                <i class="fa-solid fa-qrcode"></i>
                                <span>Point your camera at the customer QR code</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="inspector-scan-rule"></span><small class="text-secondary">OR</small><span class="inspector-scan-rule"></span>
                            </div>
                            <label class="form-label" for="manualCustomerId">Enter Customer Code</label>
                            <div class="input-group mb-3">
                                <input type="text" id="manualCustomerId" class="form-control" placeholder="CUST-000001">
                                <button type="button" id="manualSearchBtn" class="btn btn-outline-success">Search</button>
                            </div>
                            <label class="form-label" for="qrImageInput">Upload QR Code Image</label>
                            <input type="file" id="qrImageInput" class="form-control" accept="image/*">
                            <input type="text" id="scannedCustomerId" class="form-control d-none" readonly value="<?php echo htmlspecialchars($customerId); ?>">
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><i class="fa-solid fa-circle-check text-success me-2"></i>Customer Information</h5>
                        <?php if ($customer): ?>
                            <div class="alert alert-success py-2"><strong>QR Code Scanned Successfully!</strong><br><small>Customer information has been retrieved.</small></div>
                            <div class="inspector-customer-summary">
                                <div class="inspector-customer-avatar"><i class="fa-solid fa-user"></i></div>
                                <div class="inspector-customer-details">
                                    <span>Customer Name</span><strong><?php echo htmlspecialchars($customer['owner_name'] ?? 'N/A'); ?></strong>
                                    <span>Contact Number</span><strong><?php echo htmlspecialchars($customer['contact_number'] ?? 'N/A'); ?></strong>
                                </div>
                                <div class="inspector-customer-code"><span>Owner / Code</span><strong><?php echo htmlspecialchars($customer['owner_code'] ?? $customer['customer_id'] ?? 'N/A'); ?></strong></div>
                            </div>
                            <a href="/inspector/ante_mortem.php?customer_id=<?php echo urlencode($customer['customer_id'] ?? ''); ?>" class="btn btn-success w-100 mt-4"><i class="fa-solid fa-arrow-right me-2"></i>Proceed to Ante Mortem</a>
                        <?php else: ?>
                            <div class="inspector-empty-customer"><i class="fa-solid fa-user-slash"></i><p class="mb-0">Scan a customer QR code to view their information.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-clock-rotate-left text-success me-2"></i>Recent Scans</h5>
                    <span class="text-secondary small"><?php echo count($recentScans); ?> records</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Date &amp; Time</th><th>Customer</th><th>Customer Code</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentScans)): ?>
                                <tr><td colspan="5" class="text-center text-secondary py-4">No recent scans.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentScans as $recentScan): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($recentScan['created_at'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($recentScan['customer_name'] ?? $recentScan['customer_id'] ?? 'N/A'); ?></td>
                                        <td><span class="badge bg-success-subtle text-success-emphasis"><?php echo htmlspecialchars($recentScan['customer_id'] ?? 'N/A'); ?></span></td>
                                        <td><span class="badge bg-success-subtle text-success-emphasis">Loaded</span></td>
                                        <td><a class="btn btn-sm btn-outline-success" href="?customer_id=<?php echo urlencode($recentScan['customer_id'] ?? ''); ?>">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

            <?php if ($showMicSection): ?>
                <div class="col-lg-8">
                    <div id="micSection" class="card shadow-sm border-0">
                        <div class="card-body">
                            <h5 class="card-title mb-3">MIC Inspection</h5>
                            <form method="post" id="micForm">
                                <input type="hidden" name="action" value="save_mic">
                                <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customer['customer_id'] ?? ''); ?>">
                                <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($customer['owner_name'] ?? ''); ?>">
                                <input type="hidden" name="customer_contact" value="<?php echo htmlspecialchars($customer['contact_number'] ?? ''); ?>">
                                <div class="row gy-4">
                                    <div class="col-lg-7">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div>
                                                        <h6 class="mb-1">Meat Details</h6>
                                                        <p class="text-secondary mb-0">Enter quantities and weights for each product type.</p>
                                                    </div>
                                                </div>
                                                <?php foreach (['Pork', 'Beef', 'Carabeef', 'Entrails'] as $meatType): ?>
                                                    <div class="row align-items-center py-2 border-bottom">
                                                        <div class="col-4">
                                                            <span class="fw-semibold"><?php echo $meatType; ?></span>
                                                        </div>
                                                        <div class="col-4">
                                                            <input type="number" min="0" name="qty_<?php echo strtolower(str_replace(' ', '_', $meatType)); ?>" class="form-control form-control-sm" placeholder="0" />
                                                        </div>
                                                        <div class="col-4">
                                                            <input type="number" min="0" step="0.01" name="weight_<?php echo strtolower(str_replace(' ', '_', $meatType)); ?>" class="form-control form-control-sm" placeholder="0.00" />
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                                <div class="row text-secondary small mt-2">
                                                    <div class="col-4">Type of Meat</div>
                                                    <div class="col-4">Quantity (heads/sets)</div>
                                                    <div class="col-4">Weight (kg)</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-5">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <h6 class="mb-3">Origin & Destination</h6>
                                                <div class="mb-3">
                                                    <label class="form-label">Point of Origin</label>
                                                    <input type="text" class="form-control" name="point_of_origin" value="FMIMC" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Destination</label>
                                                    <input type="text" class="form-control" name="destination" value="Lipa" readonly>
                                                </div>
                                                <div class="row g-3">
                                                    <div class="col-6">
                                                        <label class="form-label">Date Issued</label>
                                                        <input type="date" class="form-control" name="date_issued" value="<?php echo date('Y-m-d'); ?>">
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label">Time Issued</label>
                                                        <input type="time" class="form-control" name="time_issued" value="<?php echo date('H:i'); ?>">
                                                    </div>
                                                </div>
                                                <div class="mb-3 mt-3">
                                                    <label class="form-label">Meat Inspector</label>
                                                    <input type="text" class="form-control" name="meat_inspector" value="<?php echo htmlspecialchars(get_current_inspector_name()); ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Owner / Dealer</label>
                                                    <input type="text" class="form-control" name="owner_dealer" value="<?php echo htmlspecialchars($customer['owner_name'] ?? ''); ?>"<?php echo $customer ? ' readonly' : ''; ?> placeholder="Enter name">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Received By</label>
                                                    <input type="text" class="form-control" name="received_by" placeholder="Enter name">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex gap-2">
                                            <button type="reset" class="btn btn-outline-danger flex-fill">Cancel</button>
                                            <button type="submit" class="btn btn-light border text-dark flex-fill">Save Record</button>
                                            <button type="submit" class="btn btn-danger text-white flex-fill">Generate QR Code & Print</button>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="customer_origin" value="<?php echo htmlspecialchars($customer['origin'] ?? ''); ?>">
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const startScanBtn = document.getElementById('startScanBtn');
        const manualSearchBtn = document.getElementById('manualSearchBtn');
        const qrImageInput = document.getElementById('qrImageInput');
        const scannerPreview = document.getElementById('scannerPreview');
        const scannerVideo = document.getElementById('scannerVideo');
        const scannerCanvas = document.getElementById('scannerCanvas');
        const scannedCustomerId = document.getElementById('scannedCustomerId');
        const customerIdInput = document.getElementById('customer_id');
        const manualCustomerId = document.getElementById('manualCustomerId');
        const micForm = document.getElementById('micForm');
        let scanInterval;
        let stream;
        const barcodeDetector = 'BarcodeDetector' in window
            ? new BarcodeDetector({ formats: ['qr_code'] })
            : null;

        micForm?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !(event.target instanceof HTMLTextAreaElement)) {
                event.preventDefault();
            }
        });

        function submitCustomerCode(value) {
            const customerCode = value.trim();
            if (!customerCode) return;
            stopScanner();
            scannerPreview.classList.add('d-none');
            startScanBtn.disabled = false;
            scannedCustomerId.value = customerCode;
            customerIdInput.value = customerCode;
            document.getElementById('scanForm').submit();
        }

        function stopScanner() {
            if (scanInterval) {
                clearInterval(scanInterval);
            }
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        }

        function isSecureCameraContext() {
            const host = window.location.hostname;
            const isLocalHost = host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
            return window.isSecureContext || isLocalHost;
        }

        startScanBtn?.addEventListener('click', async function () {
            if (!isSecureCameraContext()) {
                alert('Live camera scanning requires HTTPS on your phone. Open this site using an HTTPS address, or upload a QR image / enter the customer code below.');
                return;
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera access is not available in this browser. Try an up-to-date Chrome or Safari browser, or upload a QR image / enter the customer code below.');
                return;
            }

            try {
                scannerVideo.setAttribute('playsinline', 'true');
                scannerVideo.setAttribute('autoplay', 'true');
                scannerVideo.setAttribute('muted', 'true');
                scannerVideo.muted = true;

                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                });
                scannerVideo.srcObject = stream;
                await scannerVideo.play();
                scannerPreview.classList.remove('d-none');
                startScanBtn.disabled = true;

                const context = scannerCanvas.getContext('2d');
                scanInterval = setInterval(() => {
                    if (scannerVideo.readyState !== scannerVideo.HAVE_ENOUGH_DATA) {
                        return;
                    }
                    scannerCanvas.width = scannerVideo.videoWidth;
                    scannerCanvas.height = scannerVideo.videoHeight;
                    context.drawImage(scannerVideo, 0, 0, scannerCanvas.width, scannerCanvas.height);
                    if (typeof jsQR === 'function') {
                        const imageData = context.getImageData(0, 0, scannerCanvas.width, scannerCanvas.height);
                        const code = jsQR(imageData.data, imageData.width, imageData.height);
                        if (code?.data) submitCustomerCode(code.data);
                    } else if (barcodeDetector) {
                        barcodeDetector.detect(scannerVideo).then(codes => {
                            if (codes[0]?.rawValue) submitCustomerCode(codes[0].rawValue);
                        }).catch(() => {});
                    }
                }, 250);
            } catch (err) {
                console.error(err);
                alert('Camera permission denied or not available. Please allow camera access to scan the QR code.');
            }
        });

        manualSearchBtn?.addEventListener('click', function () {
            const value = manualCustomerId.value.trim();
            if (!value) {
                alert('Enter a Customer ID first.');
                return;
            }
            scannedCustomerId.value = value;
            customerIdInput.value = value;
            document.getElementById('scanForm').submit();
        });

        qrImageInput?.addEventListener('change', function () {
            const file = this.files?.[0];
            if (!file) return;
            if (typeof jsQR === 'undefined' && !barcodeDetector) {
                alert('QR decoder is unavailable. Enter the customer code manually.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function () {
                const image = new Image();
                image.onload = function () {
                    const canvas = document.createElement('canvas');
                    canvas.width = image.naturalWidth;
                    canvas.height = image.naturalHeight;
                    const context = canvas.getContext('2d');
                    context.drawImage(image, 0, 0);
                    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                    const code = typeof jsQR === 'function' ? jsQR(imageData.data, imageData.width, imageData.height) : null;
                    if (!code?.data) {
                        alert('No readable QR code was found in that image.');
                        return;
                    }
                    submitCustomerCode(code.data);
                };
                image.src = reader.result;
            };
            reader.readAsDataURL(file);
        });

        window.addEventListener('beforeunload', stopScanner);
    });
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>