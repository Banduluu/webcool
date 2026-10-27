<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_inspector_logged_in();

$customerId = trim($_GET['customer_id'] ?? $_POST['customer_id'] ?? '');
$inspectionId = trim($_GET['inspection_id'] ?? $_POST['inspection_id'] ?? '');
$customer = null;
$inspection = null;
$inspectionKey = null;
$errors = [];
$message = null;

$inspectionsRaw = firebase_get('inspections');
$inspections = get_array_values($inspectionsRaw);
if ($inspectionId !== '') {
    foreach (is_array($inspectionsRaw) ? $inspectionsRaw : [] as $key => $item) {
        if (is_array($item) && ($item['inspection_id'] ?? '') === $inspectionId) {
            $inspection = $item;
            $inspectionKey = $key;
            $customer = is_array($item['inspection_data']['customer'] ?? null) ? $item['inspection_data']['customer'] : null;
            $customerId = $item['customer_id'] ?? $customer['customer_id'] ?? '';
            break;
        }
    }
    if ($inspection === null) {
        $errors[] = 'The requested Ante Mortem report could not be found.';
    }
} else {
    $customersResult = firebase_request('get', 'customers');
    if (is_array($customersResult) && !isset($customersResult['error'])) {
        foreach ($customersResult as $item) {
            if (is_array($item) && ($item['customer_id'] ?? '') === $customerId) {
                $customer = $item;
                break;
            }
        }
    } elseif ($customerId !== '') {
        $errors[] = 'Customer database is unavailable. Check the Firebase connection and try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $customer !== null) {
    $heads = trim($_POST['no_of_heads'] ?? '');
    $farmOwner = trim($_POST['farm_owner'] ?? '');
    $origin = trim($_POST['origin'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $documentPresented = array_values(array_filter(array_map('trim', is_array($_POST['document_presented'] ?? null) ? $_POST['document_presented'] : [])));
    $documentNumber = trim($_POST['document_number'] ?? '');

    if ($heads === '' || $farmOwner === '' || $origin === '' || $category === '' || !$documentPresented) {
        $errors[] = 'Complete all required Ante Mortem report fields.';
    }

    if (empty($errors)) {
        $anteMortem = [
            'no_of_heads' => $heads,
            'farm_owner' => $farmOwner,
            'origin' => $origin,
            'category' => $category,
            'document_presented' => $documentPresented,
            'document_number' => $documentNumber,
        ];
        if ($inspection !== null && $inspectionKey !== null) {
            $inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
            $result = firebase_patch('inspections/' . $inspectionKey, [
                'inspection_data' => array_merge($inspectionData, [
                    'customer' => $customer,
                    'ante_mortem' => $anteMortem,
                ]),
            ]);
            if ($result !== null) {
                $message = 'Ante Mortem report updated successfully.';
                $inspection['inspection_data'] = array_merge($inspectionData, [
                    'customer' => $customer,
                    'ante_mortem' => $anteMortem,
                ]);
            } else {
                $errors[] = 'Failed to update the Ante Mortem report.';
            }
        } else {
            $newInspectionId = generate_inspection_id($inspections);
            $newInspection = [
                'inspection_id' => $newInspectionId,
                'batch_id' => generate_batch_id($inspections),
                'inspection_date' => date('Y-m-d'),
                'inspector_name' => get_current_inspector_name(),
                'customer_id' => $customer['customer_id'] ?? $customerId,
                'customer_name' => $customer['owner_name'] ?? '',
                'customer_contact' => $customer['contact_number'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
                'status' => 'Ante-Mortem',
                'decision' => null,
                'qr_code_value' => $newInspectionId,
                'inspection_data' => [
                    'customer' => $customer,
                    'ante_mortem' => $anteMortem,
                ],
                'certificate_id' => null,
            ];
            $result = firebase_post('inspections', $newInspection);
            if (!empty($result['name'])) {
                $message = 'Ante Mortem report saved successfully. Inspection ID: ' . $newInspectionId . '.';
            } else {
                $errors[] = 'Failed to save the Ante Mortem report.';
            }
        }
    }
}

$anteMortem = is_array($inspection['inspection_data']['ante_mortem'] ?? null) ? $inspection['inspection_data']['ante_mortem'] : [];
$isEdit = $inspection !== null;
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="main-content inspector-ante-page">
    <?php require_once __DIR__ . '/../includes/inspector_navbar.php'; ?>
    <main class="container-fluid px-3 px-md-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="mb-2"><?php echo $isEdit ? 'Edit Ante Mortem Report' : 'Ante Mortem Report'; ?></h1>
                <p class="text-secondary mb-0">Record the animal details and documents before inspection.</p>
            </div>
            <a href="/inspector/customer_scanner.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Back to Scanner</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?><div><?php echo htmlspecialchars($error); ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

        <?php if ($customer): ?>
            <form method="post">
                <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customerId); ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="inspection_id" value="<?php echo htmlspecialchars($inspectionId); ?>">
                <?php endif; ?>
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3 p-md-4">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0 inspector-ante-report">
                                <thead>
                                    <tr>
                                        <th>Date / Ante Mortem Report</th>
                                        <th>Owner / Code</th>
                                        <th>No. of Heads</th>
                                        <th>Name of Farm / Farmer</th>
                                        <th>Origin / Place</th>
                                        <th>Category</th>
                                        <th>Document Presented</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php echo date('F d, Y'); ?></td>
                                        <td><?php echo htmlspecialchars(($customer['owner_code'] ?? $customer['customer_id'] ?? 'N/A') . ' / ' . ($customer['owner_name'] ?? 'N/A')); ?></td>
                                        <td><input class="form-control" type="number" min="1" name="no_of_heads" required value="<?php echo htmlspecialchars($_POST['no_of_heads'] ?? $anteMortem['no_of_heads'] ?? ''); ?>"></td>
                                        <td><input class="form-control" type="text" name="farm_owner" required value="<?php echo htmlspecialchars($_POST['farm_owner'] ?? $anteMortem['farm_owner'] ?? $customer['owner_name'] ?? ''); ?>"></td>
                                        <td><input class="form-control" type="text" name="origin" required value="<?php echo htmlspecialchars($_POST['origin'] ?? $anteMortem['origin'] ?? ''); ?>"></td>
                                        <td><input class="form-control" type="text" name="category" required value="<?php echo htmlspecialchars($_POST['category'] ?? $anteMortem['category'] ?? ''); ?>"></td>
                                        <td>
                                            <?php
                                            $documentOptions = [
                                            'Animal Identification Certificate (AIC)',
                                            'Veterinary Health Certificate (VHC)',
                                            'African Swine Fever (ASF)',
                                            'Shipping Permit',
                                            ];
                                            $savedDocuments = is_array($_POST['document_presented'] ?? null)
                                            ? $_POST['document_presented']
                                            : (is_array($anteMortem['document_presented'] ?? null) ? $anteMortem['document_presented'] : (($anteMortem['document_presented'] ?? '') !== '' ? [$anteMortem['document_presented']] : []));
                                            ?>
                                            <div class="d-flex flex-column gap-2">
                                            <?php foreach ($documentOptions as $document): ?>
                                                <label class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="document_presented[]" value="<?php echo htmlspecialchars($document); ?>"<?php echo in_array($document, $savedDocuments, true) ? ' checked' : ''; ?>>
                                                    <span class="form-check-label"><?php echo htmlspecialchars($document); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                            </div>
                                            <input class="form-control mt-2" type="text" name="document_number" placeholder="Document number" value="<?php echo htmlspecialchars($_POST['document_number'] ?? $anteMortem['document_number'] ?? ''); ?>">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4">
                            <a href="/inspector/ante_mortems.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk me-2"></i><?php echo $isEdit ? 'Update' : 'Save'; ?> Ante Mortem Report</button>
                        </div>
                    </div>
                </div>
            </form>
        <?php elseif ($customerId === ''): ?>
            <div class="alert alert-warning">Scan a customer first before opening the Ante Mortem report.</div>
        <?php endif; ?>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
