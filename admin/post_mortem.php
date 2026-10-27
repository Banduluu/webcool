<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$allInspections = array_values(array_filter(get_array_values(firebase_get('inspections')), 'is_array'));
$requestedDate = trim((string) ($_GET['date'] ?? ''));
$selectedDate = DateTime::createFromFormat('!Y-m-d', $requestedDate);
if (!$selectedDate || $selectedDate->format('Y-m-d') !== $requestedDate) {
    $selectedDate = new DateTime('today');
}
$selectedDateValue = $selectedDate->format('Y-m-d');
$inspections = array_values(array_filter($allInspections, static function (array $item) use ($selectedDateValue): bool {
    $inspectionData = is_array($item['inspection_data'] ?? null) ? $item['inspection_data'] : [];
    $recordDate = substr((string) ($inspectionData['slaughter_date'] ?? $item['inspection_date'] ?? $item['created_at'] ?? ''), 0, 10);
    return $recordDate === $selectedDateValue
        && (isset($inspectionData['slaughter'])
        || !empty($inspectionData['decision'])
        || in_array($item['status'] ?? '', ['Slaughter', 'Passed', 'Partially Condemned', 'Condemned'], true));
}));

$records = [];
foreach ($inspections as $index => $inspection) {
    $inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
    $customer = is_array($inspectionData['customer'] ?? null) ? $inspectionData['customer'] : [];
    $anteMortem = is_array($inspectionData['ante_mortem'] ?? null) ? $inspectionData['ante_mortem'] : [];
    $meatDetails = is_array($inspectionData['meat_details'] ?? null) ? $inspectionData['meat_details'] : [];
    $heads = (int) ($anteMortem['no_of_heads'] ?? $inspection['head_count'] ?? $inspectionData['meat_summary']['no_of_heads'] ?? 0);
    $animalType = $customer['animal_type'] ?? $inspectionData['mic']['animal_type'] ?? '';
    if ($animalType === '') {
        foreach (['pork', 'beef', 'carabeef', 'entrails'] as $meatType) {
            $detail = is_array($meatDetails[$meatType] ?? null) ? $meatDetails[$meatType] : [];
            if (!empty($detail['qty']) || !empty($detail['weight'])) {
                $animalType = ucfirst($meatType);
                break;
            }
        }
    }

    $records[] = [
        'record_number' => 'PM-' . date('Y') . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
        'inspection_id' => (string) ($inspection['inspection_id'] ?? ''),
        'customer_code' => (string) ($customer['owner_code'] ?? $customer['customer_id'] ?? $inspection['customer_id'] ?? 'N/A'),
        'animal_type' => $animalType !== '' ? ucfirst((string) $animalType) : 'N/A',
        'slaughter_date' => (string) ($inspectionData['slaughter_date'] ?? $inspection['inspection_date'] ?? $inspection['created_at'] ?? 'N/A'),
        'heads' => $heads > 0 ? (string) $heads : 'Recorded quantity',
        'inspection_date' => (string) ($inspection['inspection_date'] ?? $inspection['created_at'] ?? 'N/A'),
        'inspector' => (string) ($inspection['inspector_name'] ?? 'Linked inspector account'),
    ];
}

$summary = [
    'total' => count($records),
    'slaughter' => count(array_filter($inspections, static fn(array $item): bool => ($item['status'] ?? '') === 'Slaughter')),
];

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <main class="container-fluid px-4 py-4 post-mortem-page">
        <div class="post-mortem-heading">
            <span class="post-mortem-heading-icon"><i class="fa-solid fa-database"></i></span>
            <div>
                <h1>All Post-Mortem Records</h1>
                <p>Records from all meat inspectors for <?php echo htmlspecialchars($selectedDate->format('F d, Y')); ?></p>
            </div>
        </div>

        <div class="post-mortem-summary">
            <span>Total Records: <strong><?php echo $summary['total']; ?></strong></span>
            <span>Slaughter: <strong><?php echo $summary['slaughter']; ?></strong></span>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle post-mortem-table mb-0">
                        <thead>
                            <tr>
                                <th>Record Number</th>
                                <th>Customer / Code</th>
                                <th>Animal Type</th>
                                <th>Slaughter Date</th>
                                <th>Number of Heads</th>
                                <th>Inspection Date</th>
                                <th>Inspector</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr id="pm-<?php echo htmlspecialchars($record['inspection_id']); ?>">
                                    <td><?php echo htmlspecialchars($record['record_number']); ?></td>
                                    <td><?php echo htmlspecialchars($record['customer_code']); ?></td>
                                    <td><?php echo htmlspecialchars($record['animal_type']); ?></td>
                                    <td><?php echo htmlspecialchars($record['slaughter_date']); ?></td>
                                    <td><?php echo htmlspecialchars($record['heads']); ?></td>
                                    <td><?php echo htmlspecialchars($record['inspection_date']); ?></td>
                                    <td><?php echo htmlspecialchars($record['inspector']); ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="#pm-<?php echo htmlspecialchars($record['inspection_id']); ?>"><i class="fa-regular fa-eye me-1"></i>View Details</a>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" onclick="window.print();"><i class="fa-solid fa-print me-1"></i>Print</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$records): ?>
                        <p class="post-mortem-empty mb-0">No post-mortem records have been submitted.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
