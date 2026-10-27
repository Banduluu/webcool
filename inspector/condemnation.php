<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_inspector_logged_in();

$inspectionsRaw = firebase_get('inspections');
$inspections = is_array($inspectionsRaw) ? $inspectionsRaw : [];
$errors = [];
$message = null;
$form = [
    'customer_code' => trim((string) ($_POST['customer_code'] ?? '')),
    'animal_type' => (string) ($_POST['animal_type'] ?? 'pork'),
    'part_affected' => (string) ($_POST['part_affected'] ?? 'part'),
    'quantity' => trim((string) ($_POST['quantity'] ?? '1')),
    'weight' => trim((string) ($_POST['weight'] ?? '')),
    'reason' => (string) ($_POST['reason'] ?? 'Abnormal findings'),
    'remarks' => trim((string) ($_POST['remarks'] ?? '')),
];

$matchingInspection = null;
$matchingKey = null;
foreach ($inspections as $key => $inspection) {
    if (!is_array($inspection) || $form['customer_code'] === '') {
        continue;
    }
    $customer = is_array($inspection['inspection_data']['customer'] ?? null) ? $inspection['inspection_data']['customer'] : [];
    $codes = [
        $inspection['inspection_id'] ?? '',
        $inspection['customer_id'] ?? '',
        $inspection['registration_id'] ?? '',
        $inspection['qr_code_value'] ?? '',
        $customer['customer_id'] ?? '',
        $customer['owner_code'] ?? '',
    ];
    if (in_array($form['customer_code'], array_map('strval', $codes), true)) {
        $matchingInspection = $inspection;
        $matchingKey = $key;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowedAnimalTypes = ['pork', 'beef', 'carabeef', 'entrails'];
    $allowedParts = ['whole', 'part', 'organ'];
    $allowedReasons = ['Abnormal findings', 'Disease suspected', 'Contamination', 'Injury', 'Other'];

    if ($matchingInspection === null) {
        $errors[] = 'Enter a Customer Code or QR Record ID that belongs to an existing inspection.';
    }
    if (!in_array($form['animal_type'], $allowedAnimalTypes, true)) {
        $errors[] = 'Select a valid animal or meat type.';
    }
    if (!in_array($form['part_affected'], $allowedParts, true)) {
        $errors[] = 'Select the affected part.';
    }
    if (!in_array($form['reason'], $allowedReasons, true)) {
        $errors[] = 'Select a valid reason for condemnation.';
    }
    if (!ctype_digit($form['quantity']) || (int) $form['quantity'] < 1) {
        $errors[] = 'Quantity must be a whole number greater than zero.';
    }
    if ($form['weight'] === '' || !is_numeric($form['weight']) || (float) $form['weight'] < 0) {
        $errors[] = 'Enter a valid non-negative weight.';
    }
    if ($form['remarks'] === '') {
        $errors[] = 'Enter the inspection findings or remarks.';
    }

    if (!$errors && $matchingInspection !== null && $matchingKey !== null) {
        $inspectionData = is_array($matchingInspection['inspection_data'] ?? null) ? $matchingInspection['inspection_data'] : [];
        $condemnation = [
            'animal_type' => $form['animal_type'],
            'part_affected' => $form['part_affected'],
            'quantity' => (int) $form['quantity'],
            'weight' => (float) $form['weight'],
            'reason' => $form['reason'],
            'remarks' => $form['remarks'],
            'inspector' => get_current_inspector_name(),
            'recorded_at' => date('Y-m-d H:i:s'),
        ];
        $result = firebase_patch('inspections/' . $matchingKey, [
            'inspection_data' => array_merge($inspectionData, ['condemnation' => $condemnation]),
        ]);
        if ($result !== null && (!is_array($result) || !isset($result['error']))) {
            $message = 'Condemnation record saved for inspection ' . ($matchingInspection['inspection_id'] ?? $form['customer_code']) . '.';
            $form = array_fill_keys(array_keys($form), '');
            $form['animal_type'] = 'pork';
            $form['part_affected'] = 'part';
            $form['quantity'] = '1';
            $form['reason'] = 'Abnormal findings';
        } else {
            $errors[] = 'Unable to save the condemnation record. Please check the Firebase connection and try again.';
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="main-content inspector-condemnation-page">
    <?php require_once __DIR__ . '/../includes/inspector_navbar.php'; ?>
    <main class="container-fluid px-3 px-md-4 py-4">
        <div class="condemnation-card">
            <div class="condemnation-heading">
                <span class="condemnation-heading-icon"><i class="fa-regular fa-file-lines"></i></span>
                <div>
                    <h1>A New Condemnation Record</h1>
                    <p>Form fields the inspector fills out.</p>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?><div><?php echo htmlspecialchars($error); ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

            <form method="post" class="condemnation-form">
                <div class="condemnation-field">
                    <label for="customer_code">Customer Code / QR Record ID</label>
                    <input id="customer_code" name="customer_code" type="text" value="<?php echo htmlspecialchars($form['customer_code']); ?>" placeholder="Enter or retrieve customer code" required>
                    <small>Link this record to the customer and relevant inspection record.</small>
                </div>

                <fieldset class="condemnation-field">
                    <legend>Animal / Meat Type</legend>
                    <div class="condemnation-options">
                        <?php foreach (['pork' => 'Pork', 'beef' => 'Beef', 'carabeef' => 'Carabeef', 'entrails' => 'Entrails'] as $value => $label): ?>
                            <label><input type="radio" name="animal_type" value="<?php echo $value; ?>" <?php echo $form['animal_type'] === $value ? 'checked' : ''; ?>> <span><?php echo $label; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="condemnation-field">
                    <legend>Part Affected</legend>
                    <div class="condemnation-options condemnation-options-vertical">
                        <?php foreach (['whole' => 'Whole animal / carcass', 'part' => 'Part of carcass', 'organ' => 'Organ / entrails'] as $value => $label): ?>
                            <label><input type="radio" name="part_affected" value="<?php echo $value; ?>" <?php echo $form['part_affected'] === $value ? 'checked' : ''; ?>> <span><?php echo $label; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="condemnation-two-columns">
                    <div class="condemnation-field">
                        <label for="quantity">Quantity</label>
                        <input id="quantity" name="quantity" type="number" min="1" step="1" value="<?php echo htmlspecialchars($form['quantity']); ?>" required>
                    </div>
                    <div class="condemnation-field">
                        <label for="weight">Weight (kg)</label>
                        <input id="weight" name="weight" type="number" min="0" step="0.01" value="<?php echo htmlspecialchars($form['weight']); ?>" placeholder="Weight in kg" required>
                    </div>
                </div>

                <div class="condemnation-field">
                    <label for="reason">Reason for Condemnation</label>
                    <select id="reason" name="reason">
                        <?php foreach (['Abnormal findings', 'Disease suspected', 'Contamination', 'Injury', 'Other'] as $reason): ?>
                            <option value="<?php echo htmlspecialchars($reason); ?>" <?php echo $form['reason'] === $reason ? 'selected' : ''; ?>><?php echo htmlspecialchars($reason); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="condemnation-field">
                    <label for="remarks">Inspection Findings / Remarks</label>
                    <textarea id="remarks" name="remarks" rows="4" placeholder="Describe the observed findings and basis for the decision." required><?php echo htmlspecialchars($form['remarks']); ?></textarea>
                </div>

                <div class="d-flex justify-content-between gap-2 mt-4">
                    <a href="/inspector/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Save Condemnation Record</button>
                </div>
            </form>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
