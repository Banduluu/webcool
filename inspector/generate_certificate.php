<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_inspector_logged_in();

$inspectionsRaw = firebase_get('inspections');
$inspections = is_array($inspectionsRaw) ? $inspectionsRaw : [];
$registrationsRaw = firebase_get('client_registrations');
$registrations = is_array($registrationsRaw) ? $registrationsRaw : [];
$inspectionId = trim($_GET['inspection_id'] ?? $_POST['inspection_id'] ?? '');
$certificateLookupId = trim($_GET['certificate_id'] ?? '');
$viewOnly = $certificateLookupId !== '' || ($_GET['view'] ?? '') === '1';
$inspection = null;
$inspectionKey = null;

foreach ($inspections as $key => $item) {
    if (is_array($item) && $inspectionId !== '' && (string) ($item['inspection_id'] ?? '') === $inspectionId) {
        $inspection = $item;
        $inspectionKey = $key;
        break;
    }
}

if (!$inspection && $certificateLookupId !== '') {
    foreach ($inspections as $key => $item) {
        if (is_array($item) && (string) ($item['certificate_id'] ?? '') === $certificateLookupId) {
            $inspection = $item;
            $inspectionKey = $key;
            $inspectionId = (string) ($item['inspection_id'] ?? '');
            break;
        }
    }
}

$customerOptions = [];
foreach ($inspections as $item) {
    if (!is_array($item) || empty($item['inspection_id'])) {
        continue;
    }
    $itemRegistration = [];
    $itemRegistrationKey = $item['registration_key'] ?? '';
    if ($itemRegistrationKey !== '' && isset($registrations[$itemRegistrationKey]) && is_array($registrations[$itemRegistrationKey])) {
        $itemRegistration = $registrations[$itemRegistrationKey];
    } else {
        foreach ($registrations as $registrationItem) {
            if (is_array($registrationItem) && !empty($item['registration_id']) && ($registrationItem['inspection_id'] ?? '') === $item['registration_id']) {
                $itemRegistration = $registrationItem;
                break;
            }
        }
    }
    if (!$itemRegistration && is_array($item['inspection_data']['customer'] ?? null)) {
        $itemRegistration = $item['inspection_data']['customer'];
    }
    $customerOptions[] = [
        'inspection_id' => (string) $item['inspection_id'],
        'customer_id' => (string) ($item['customer_id'] ?? $itemRegistration['customer_id'] ?? ''),
        'name' => (string) ($itemRegistration['owner_name'] ?? $item['customer_name'] ?? 'Unknown Customer'),
        'code' => (string) ($itemRegistration['owner_code'] ?? $itemRegistration['customer_id'] ?? $item['customer_id'] ?? $item['registration_id'] ?? 'No customer code'),
        'status' => (string) ($item['status'] ?? 'Inspection'),
    ];
}

$registration = [];
if ($inspection) {
    $registrationKey = $inspection['registration_key'] ?? '';
    if ($registrationKey !== '' && isset($registrations[$registrationKey]) && is_array($registrations[$registrationKey])) {
        $registration = $registrations[$registrationKey];
    } else {
        foreach ($registrations as $item) {
            if (is_array($item) && !empty($inspection['registration_id']) && ($item['inspection_id'] ?? '') === $inspection['registration_id']) {
                $registration = $item;
                break;
            }
        }
    }
    if (!$registration && is_array($inspection['inspection_data']['customer'] ?? null)) {
        $registration = $inspection['inspection_data']['customer'];
    }
}

$message = null;
$error = null;
$certificateMeatTypes = [
    'pork' => 'PORK',
    'beef' => 'BEEF',
    'carabeef' => 'CARABEEF',
    'entrails' => 'ENTRAILS',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $inspection && isset($_POST['action']) && $_POST['action'] === 'generate_certificate') {
    $existingData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
    $certificateId = generate_certificate_id(get_array_values(firebase_get('certificates')));
    $meatDetailsUpdate = [];
    foreach ($certificateMeatTypes as $meatKey => $meatLabel) {
        $meatDetailsUpdate[$meatKey] = [
            'qty' => trim($_POST['qty_' . $meatKey] ?? ''),
            'weight' => trim($_POST['weight_' . $meatKey] ?? ''),
        ];
    }
    $certificateData = [
        'meat_details' => $meatDetailsUpdate,
        'owner_dealer' => trim($_POST['customer_name'] ?? ''),
        'origin' => 'FMIMC',
        'destination' => 'Lipa',
        'date_issued' => trim($_POST['date_issued'] ?? date('Y-m-d')),
        'time_issued' => trim($_POST['time_issued'] ?? ''),
    ];
    $certificateCreatedAt = date('c');
    $update = [
        'certificate_id' => $certificateId,
        'qr_code_value' => $inspectionId,
        'certificate_created_at' => $certificateCreatedAt,
        'status' => 'Passed',
        'decision' => 'Passed',
        'inspection_data' => array_merge($existingData, $certificateData),
    ];
    $saveResult = firebase_patch('inspections/' . $inspectionKey, $update);
    $certificateRecord = [
        'certificate_id' => $certificateId,
        'inspection_id' => $inspectionId,
        'customer_id' => (string) ($inspection['customer_id'] ?? ''),
        'customer_name' => (string) ($inspection['customer_name'] ?? $certificateData['owner_dealer']),
        'inspector_name' => (string) ($inspection['inspector_name'] ?? get_current_inspector_name()),
        'status' => 'Passed',
        'issued_at' => $certificateCreatedAt,
        'inspection_data' => $certificateData,
    ];
    $certificateSaveResult = firebase_put('certificates/' . $certificateId, $certificateRecord);
    $inspectionSaved = $saveResult !== null && (!is_array($saveResult) || !isset($saveResult['error']));
    $certificateSaved = $certificateSaveResult !== null && (!is_array($certificateSaveResult) || !isset($certificateSaveResult['error']));
    if ($inspectionSaved && $certificateSaved) {
        header('Location: /inspector/certificates.php');
        exit;
    }
    $error = 'Unable to save the certificate. Please try again.';
}

$inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
$meatDetails = is_array($inspectionData['meat_details'] ?? null) ? $inspectionData['meat_details'] : [];
$defaultDate = $viewOnly
    ? ($inspectionData['date_issued'] ?? $inspection['inspection_date'] ?? substr((string) ($inspection['created_at'] ?? date('Y-m-d')), 0, 10))
    : date('Y-m-d');
$defaultTime = $inspectionData['time_issued'] ?? date('H:i');

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="main-content inspector-generate-certificate-page">
    <?php require_once __DIR__ . '/../includes/inspector_navbar.php'; ?>
    <div class="certificate-generator-page">
        <div class="certificate-generator-container">
            <div class="certificate-generator-breadcrumb">
                <a href="/inspector/certificates.php">Meat Inspection Certificates</a>
                <i class="fa-solid fa-chevron-right"></i>
                <span>Generate Certificate</span>
            </div>

            <header class="certificate-generator-heading">
                <span class="certificate-records-icon"><i class="fa-solid fa-file-circle-check"></i></span>
                <div>
                    <h1>Generate Meat Inspection Certificate</h1>
                    <p>Review the inspection details and generate the official certificate.</p>
                </div>
            </header>

            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if (!$inspection): ?>
                <section class="certificate-generator-card certificate-customer-picker">
                    <div class="certificate-generator-card-title"><i class="fa-solid fa-user"></i>Find Customer</div>
                    <div class="certificate-generator-card-body">
                        <p class="generator-picker-help">Select the customer and inspection record for this certificate.</p>
                        <label class="generator-input generator-wide">
                            <span>Search Customer</span>
                            <input type="search" id="customerSearch" placeholder="Type a customer name or code...">
                        </label>
                        <div class="generator-scanner generator-wide">
                            <button type="button" class="certificate-generator-back" id="startCustomerScan"><i class="fa-solid fa-camera"></i>Scan Customer QR Code</button>
                            <div class="generator-scanner-preview d-none" id="customerScannerPreview">
                                <video id="customerScannerVideo" playsinline muted></video>
                                <canvas id="customerScannerCanvas" class="d-none"></canvas>
                                <small>Point the camera at the customer's QR code.</small>
                            </div>
                            <div class="generator-scanner-status" id="customerScannerStatus" role="status"></div>
                        </div>
                        <label class="generator-input generator-wide">
                            <span>Customer</span>
                            <select id="customerInspection" size="5">
                                <?php foreach ($customerOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option['inspection_id']); ?>" data-customer-id="<?php echo htmlspecialchars($option['customer_id']); ?>" data-search="<?php echo htmlspecialchars(strtolower($option['name'] . ' ' . $option['code'] . ' ' . $option['customer_id'])); ?>">
                                        <?php echo htmlspecialchars($option['name'] . ' - ' . $option['code']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="button" class="certificate-generate-submit generator-picker-button" id="selectCustomerButton"><i class="fa-solid fa-arrow-right"></i>Continue with Customer</button>
                    </div>
                </section>
            <?php else: ?>
                <form method="post" class="certificate-generator-layout<?php echo $viewOnly ? ' certificate-view-only' : ''; ?>">
                    <input type="hidden" name="action" value="generate_certificate">
                    <input type="hidden" name="inspection_id" value="<?php echo htmlspecialchars($inspectionId); ?>">
                    <div class="certificate-generator-form">
                        <section class="certificate-generator-card">
                            <div class="certificate-generator-card-title"><i class="fa-solid fa-user"></i>Customer Information</div>
                            <div class="certificate-generator-card-body">
                                <div class="generator-field"><span>Customer Name</span><strong><?php echo htmlspecialchars($registration['owner_name'] ?? $inspection['customer_name'] ?? 'N/A'); ?></strong><input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($registration['owner_name'] ?? $inspection['customer_name'] ?? ''); ?>"></div>
                                <div class="generator-field"><span>Customer Code</span><strong class="generator-code"><?php echo htmlspecialchars($registration['owner_code'] ?? $registration['customer_id'] ?? $inspection['customer_id'] ?? $inspection['registration_id'] ?? 'N/A'); ?></strong></div>
                                <div class="generator-field"><span>Contact Number</span><strong><?php echo htmlspecialchars($registration['contact_number'] ?? $registration['phone'] ?? 'N/A'); ?></strong></div>
                                <div class="generator-field"><span>Address</span><strong><?php echo htmlspecialchars($registration['address'] ?? $registration['origin'] ?? 'N/A'); ?></strong></div>
                            </div>
                        </section>

                        <section class="certificate-generator-card">
                            <div class="certificate-generator-card-title"><i class="fa-solid fa-cow"></i>Inspection Details</div>
                            <div class="certificate-generator-card-body generator-fields-grid">
                                <?php foreach ($certificateMeatTypes as $meatKey => $meatLabel): ?>
                                    <div class="generator-meat-row">
                                        <strong><?php echo $meatLabel; ?></strong>
                                        <label class="generator-input"><span>Quantity</span><input name="qty_<?php echo $meatKey; ?>" value="<?php echo htmlspecialchars($meatDetails[$meatKey]['qty'] ?? ''); ?>" placeholder="<?php echo $meatKey === 'entrails' ? 'set/s' : 'hd/s'; ?>"></label>
                                        <label class="generator-input"><span>Weight (kg)</span><input name="weight_<?php echo $meatKey; ?>" value="<?php echo htmlspecialchars($meatDetails[$meatKey]['weight'] ?? ($meatKey === 'pork' ? ($registration['estimated_weight'] ?? '') : '')); ?>" placeholder="kls."></label>
                                    </div>
                                <?php endforeach; ?>
                                <label class="generator-input generator-wide"><span>Point of Origin</span><input name="origin" value="FMIMC" readonly></label>
                                <label class="generator-input generator-wide"><span>Destination</span><input name="destination" value="Lipa" readonly></label>
                                <label class="generator-input"><span>Date Issued</span><input type="date" name="date_issued" value="<?php echo htmlspecialchars($defaultDate); ?>"></label>
                                <label class="generator-input"><span>Time Issued</span><input type="time" name="time_issued" value="<?php echo htmlspecialchars($defaultTime); ?>"></label>
                            </div>
                        </section>

                        <div class="certificate-generator-notice"><i class="fa-solid fa-circle-check"></i>All information is verified and ready to be included in the Meat Inspection Certificate.</div>
                        <div class="certificate-generator-buttons"><button class="certificate-generate-submit" type="submit"><i class="fa-solid fa-print"></i>Generate Certificate</button><a class="certificate-generator-back" href="/inspector/certificates.php"><i class="fa-solid fa-arrow-left"></i>Back</a></div>
                    </div>

                    <section class="certificate-preview-card">
                        <?php if (!$viewOnly): ?>
                        <div class="certificate-preview-header"><strong><i class="fa-solid fa-file-lines"></i> Certificate Preview</strong><span><button type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button><button type="button" onclick="window.print()"><i class="fa-solid fa-download"></i> Download</button></span></div>
                        <?php endif; ?>
                        <div class="certificate-preview-paper">
                            <h2>MEAT INSPECTION CERTIFICATE</h2>
                            <p>This certifies that meat/meat by products described below are slaughtered in accordance with the Meat Inspection Code of the Philippines and at the time and date of inspection are found fit for human consumption.</p>
                            <table><thead><tr><th>Type of Meat/Entrails</th><th>Quantity</th><th>Weight</th></tr></thead><tbody><?php foreach ($certificateMeatTypes as $meatKey => $meatLabel): ?><tr><td><?php echo $meatLabel; ?></td><td id="preview-qty-<?php echo $meatKey; ?>"><?php echo htmlspecialchars($meatDetails[$meatKey]['qty'] ?? ($meatKey === 'entrails' ? 'set/s' : 'hd/s')); ?></td><td id="preview-weight-<?php echo $meatKey; ?>"><?php echo htmlspecialchars($meatDetails[$meatKey]['weight'] ?? 'kls.'); ?></td></tr><?php endforeach; ?></tbody></table>
                            <dl><dt>Name of Owner (Dealer)</dt><dd><?php echo htmlspecialchars($registration['owner_name'] ?? $inspection['customer_name'] ?? 'N/A'); ?></dd><dt>Point of Origin</dt><dd id="preview-origin">FMIMC</dd><dt>Destination</dt><dd id="preview-destination">Lipa</dd><dt>Time Issued</dt><dd id="preview-time"><?php echo htmlspecialchars($defaultTime); ?></dd><dt>Date Issued</dt><dd id="preview-date"><?php echo htmlspecialchars($defaultDate); ?></dd></dl>
                            <div class="certificate-preview-signatures"><span>Owner/Dealer</span><span>Meat Inspector</span></div>
                            <?php if ($inspectionId !== ''): ?>
                                <div class="certificate-preview-qr">
                                    <img src="<?php echo htmlspecialchars(inspection_qr_url($inspectionId)); ?>" alt="QR code for inspection <?php echo htmlspecialchars($inspectionId); ?>">
                                    <span>Scan to verify inspection</span>
                                    <strong><?php echo htmlspecialchars($inspectionId); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($viewOnly): ?>
                        <div class="certificate-view-actions no-print"><button type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Certificate</button><a href="/inspector/certificates.php">Back to Certificates</a></div>
                        <?php endif; ?>
                    </section>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if (!$inspection): ?>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
    const customerSearch = document.getElementById('customerSearch');
    const customerInspection = document.getElementById('customerInspection');
    const customerScannerPreview = document.getElementById('customerScannerPreview');
    const customerScannerVideo = document.getElementById('customerScannerVideo');
    const customerScannerCanvas = document.getElementById('customerScannerCanvas');
    const customerScannerStatus = document.getElementById('customerScannerStatus');
    let customerScanStream = null;
    let customerScanInterval = null;

    customerSearch.addEventListener('input', function () {
        const query = this.value.trim().toLowerCase();
        Array.from(customerInspection.options).forEach(function (option) {
            option.hidden = query !== '' && !option.dataset.search.includes(query);
        });
    });

    function selectCustomerByQrValue(value) {
        const normalizedValue = value.trim().toLowerCase();
        const option = Array.from(customerInspection.options).find(function (item) {
            return item.dataset.customerId.toLowerCase() === normalizedValue
                || item.value.toLowerCase() === normalizedValue;
        });
        if (!option) {
            customerScannerStatus.textContent = 'Customer QR code was not found in the inspection records.';
            return false;
        }
        customerInspection.value = option.value;
        customerSearch.value = option.textContent.trim();
        stopCustomerScanner();
        customerScannerStatus.textContent = 'Customer found. Click Continue with Customer.';
        return true;
    }

    function stopCustomerScanner() {
        if (customerScanInterval) {
            clearInterval(customerScanInterval);
            customerScanInterval = null;
        }
        if (customerScanStream) {
            customerScanStream.getTracks().forEach(function (track) { track.stop(); });
            customerScanStream = null;
        }
        customerScannerPreview.classList.add('d-none');
        document.getElementById('startCustomerScan').disabled = false;
    }

    document.getElementById('startCustomerScan').addEventListener('click', async function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            customerScannerStatus.textContent = 'Camera access is not supported on this browser. Search by customer name or code instead.';
            return;
        }
        const host = window.location.hostname;
        const isLocalHost = host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
        const isPrivateNetwork = /^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[0-1])\.)/.test(host);
        if (!window.isSecureContext && !isLocalHost && !isPrivateNetwork) {
            customerScannerStatus.textContent = 'Camera scanning requires HTTPS or localhost. Search by customer name or code instead.';
            return;
        }
        try {
            customerScanStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            customerScannerVideo.srcObject = customerScanStream;
            await customerScannerVideo.play();
            customerScannerPreview.classList.remove('d-none');
            this.disabled = true;
            customerScannerStatus.textContent = 'Scanning...';
            const context = customerScannerCanvas.getContext('2d', { willReadFrequently: true });
            const detector = 'BarcodeDetector' in window
                ? new BarcodeDetector({ formats: ['qr_code'] })
                : null;
            customerScanInterval = setInterval(async function () {
                if (customerScannerVideo.readyState < customerScannerVideo.HAVE_ENOUGH_DATA) {
                    return;
                }
                customerScannerCanvas.width = customerScannerVideo.videoWidth;
                customerScannerCanvas.height = customerScannerVideo.videoHeight;
                context.drawImage(customerScannerVideo, 0, 0, customerScannerCanvas.width, customerScannerCanvas.height);
                try {
                    if (typeof jsQR === 'function') {
                        const imageData = context.getImageData(0, 0, customerScannerCanvas.width, customerScannerCanvas.height);
                        const code = jsQR(imageData.data, imageData.width, imageData.height);
                        if (code && code.data) {
                            selectCustomerByQrValue(code.data);
                        }
                    } else if (detector) {
                        const codes = await detector.detect(customerScannerVideo);
                        if (codes.length && codes[0].rawValue) {
                            selectCustomerByQrValue(codes[0].rawValue);
                        }
                    } else {
                        customerScannerStatus.textContent = 'QR decoder could not be loaded. Search by customer name or code instead.';
                    }
                } catch (scanError) {
                    customerScannerStatus.textContent = 'Unable to read the QR code. Please try again.';
                }
            }, 500);
        } catch (cameraError) {
            stopCustomerScanner();
            customerScannerStatus.textContent = 'Camera permission was denied or the camera is unavailable.';
        }
    });

    window.addEventListener('beforeunload', stopCustomerScanner);

    document.getElementById('selectCustomerButton').addEventListener('click', function () {
        const selectedInspection = customerInspection.value;
        if (selectedInspection) {
            window.location.href = '/inspector/generate_certificate.php?inspection_id=' + encodeURIComponent(selectedInspection);
        }
    });
</script>
<?php else: ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.certificate-generator-form');
        if (!form) {
            return;
        }

        function fieldValue(name) {
            const field = form.querySelector('[name="' + name + '"]');
            return field ? field.value.trim() : '';
        }

        function updateCertificatePreview() {
            <?php foreach ($certificateMeatTypes as $meatKey => $meatLabel): ?>
            document.getElementById('preview-qty-<?php echo $meatKey; ?>').textContent = fieldValue('qty_<?php echo $meatKey; ?>') || '<?php echo $meatKey === 'entrails' ? 'set/s' : 'hd/s'; ?>';
            document.getElementById('preview-weight-<?php echo $meatKey; ?>').textContent = fieldValue('weight_<?php echo $meatKey; ?>') || 'kls.';
            <?php endforeach; ?>
            document.getElementById('preview-origin').textContent = fieldValue('origin') || 'N/A';
            document.getElementById('preview-destination').textContent = fieldValue('destination') || 'N/A';
            document.getElementById('preview-time').textContent = fieldValue('time_issued') || 'N/A';
            document.getElementById('preview-date').textContent = fieldValue('date_issued') || 'N/A';
        }

        form.addEventListener('input', updateCertificatePreview);
        form.addEventListener('change', updateCertificatePreview);
        updateCertificatePreview();
    });
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
