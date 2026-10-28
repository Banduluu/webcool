<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$inspections = array_values(array_filter(get_array_values(firebase_get('inspections')), 'is_array'));
$requestedDate = trim((string) ($_GET['date'] ?? ''));
$selectedDate = DateTime::createFromFormat('!Y-m-d', $requestedDate);
if (!$selectedDate || $selectedDate->format('Y-m-d') !== $requestedDate) {
    $selectedDate = new DateTime('today');
}
$today = $selectedDate->format('Y-m-d');
$records = [];
foreach ($inspections as $inspection) {
    $inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
    $condemnation = is_array($inspectionData['condemnation'] ?? null) ? $inspectionData['condemnation'] : null;
    if ($condemnation === null && !in_array($inspection['status'] ?? '', ['Condemned', 'Partially Condemned'], true)) {
        continue;
    }

    $recordedAt = trim((string) ($condemnation['recorded_at'] ?? ''));
    if ($recordedAt === '') {
        $recordedAt = trim((string) ($inspection['created_at'] ?? ''));
    }
    if (substr($recordedAt, 0, 10) !== $today) {
        continue;
    }

    $customer = is_array($inspectionData['customer'] ?? null) ? $inspectionData['customer'] : [];
    $records[] = [
        'inspection_id' => (string) ($inspection['inspection_id'] ?? 'N/A'),
        'customer_code' => (string) ($customer['owner_code'] ?? $customer['customer_id'] ?? $inspection['customer_id'] ?? 'N/A'),
        'customer_name' => (string) ($inspection['customer_name'] ?? $customer['owner_name'] ?? 'N/A'),
        'animal_type' => (string) ($condemnation['animal_type'] ?? 'N/A'),
        'part_affected' => (string) ($condemnation['part_affected'] ?? 'N/A'),
        'quantity' => $condemnation['quantity'] ?? 'N/A',
        'weight' => $condemnation['weight'] ?? 'N/A',
        'reason' => (string) ($condemnation['reason'] ?? 'N/A'),
        'remarks' => (string) ($condemnation['remarks'] ?? $inspection['final_remarks'] ?? 'N/A'),
        'inspector' => (string) ($condemnation['inspector'] ?? $inspection['inspector_name'] ?? 'N/A'),
        'recorded_at' => $recordedAt,
        'status' => ($condemnation['part_affected'] ?? '') === 'whole' ? 'Condemned' : 'Partially Condemned',
    ];
}

usort($records, static function (array $first, array $second): int {
    return strcmp($second['recorded_at'], $first['recorded_at']);
});

$totalRecords = count($records);
$wholeRecords = count(array_filter($records, static fn(array $record): bool => $record['status'] === 'Condemned'));
$partialRecords = count(array_filter($records, static fn(array $record): bool => $record['status'] === 'Partially Condemned'));

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <main class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">Condemnation Records</h3>
                <p class="text-secondary mb-0">Review condemnation decisions and findings recorded on <?php echo htmlspecialchars($selectedDate->format('F d, Y')); ?>.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ([
                ['label' => 'Total Records', 'value' => $totalRecords, 'icon' => 'fa-file-circle-exclamation', 'color' => 'danger'],
                ['label' => 'Whole Animal / Carcass', 'value' => $wholeRecords, 'icon' => 'fa-skull-crossbones', 'color' => 'danger'],
                ['label' => 'Partially Condemned', 'value' => $partialRecords, 'icon' => 'fa-triangle-exclamation', 'color' => 'warning'],
            ] as $card): ?>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="text-muted"><i class="fa-solid <?php echo $card['icon']; ?> fa-lg"></i></div>
                            <span class="badge bg-<?php echo $card['color']; ?>"><?php echo $card['value']; ?></span>
                        </div>
                        <h6 class="text-secondary mb-1"><?php echo htmlspecialchars($card['label']); ?></h6>
                        <h3 class="fw-bold mb-0"><?php echo $card['value']; ?></h3>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-1">Submitted Records</h5>
                        <p class="text-secondary mb-0">Detailed condemnation information recorded on the selected date.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Inspection ID</th>
                                <th>Customer / Code</th>
                                <th>Animal / Part</th>
                                <th>Qty.</th>
                                <th>Weight (kg)</th>
                                <th>Reason</th>
                                <th>Findings / Remarks</th>
                                <th>Inspector</th>
                                <th>Date Recorded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['inspection_id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($record['customer_name']); ?></strong>
                                        <small class="d-block text-secondary"><?php echo htmlspecialchars($record['customer_code']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars(ucfirst($record['animal_type']) . ' / ' . ucfirst($record['part_affected'])); ?></td>
                                    <td><?php echo htmlspecialchars((string) $record['quantity']); ?></td>
                                    <td><?php echo is_numeric($record['weight']) ? htmlspecialchars(number_format((float) $record['weight'], 2)) : htmlspecialchars((string) $record['weight']); ?></td>
                                    <td><?php echo htmlspecialchars($record['reason']); ?></td>
                                    <td><?php echo htmlspecialchars($record['remarks']); ?></td>
                                    <td><?php echo htmlspecialchars($record['inspector']); ?></td>
                                    <td><?php echo htmlspecialchars($record['recorded_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$records): ?>
                        <p class="text-secondary text-center py-4 mb-0">No condemnation records have been submitted for <?php echo htmlspecialchars($selectedDate->format('F d, Y')); ?>.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
