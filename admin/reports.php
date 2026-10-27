<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$inspections = array_values(array_filter(get_array_values(firebase_get('inspections')), 'is_array'));
$requestedDate = trim((string) ($_GET['date'] ?? ''));
$selectedDate = DateTime::createFromFormat('!Y-m-d', $requestedDate);
if (!$selectedDate || $selectedDate->format('Y-m-d') !== $requestedDate) {
    $selectedDate = new DateTime('today');
}
$selectedDateValue = $selectedDate->format('Y-m-d');
$anteMortemRecords = [];
foreach ($inspections as $inspection) {
    $inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
    $anteMortem = is_array($inspectionData['ante_mortem'] ?? null) ? $inspectionData['ante_mortem'] : [];
    $recordDate = substr((string) ($inspection['inspection_date'] ?? $inspection['created_at'] ?? ''), 0, 10);
    if (!$anteMortem || $recordDate !== $selectedDateValue) {
        continue;
    }

    $customer = is_array($inspectionData['customer'] ?? null) ? $inspectionData['customer'] : [];
    $anteMortemRecords[] = [
        'inspection_id' => (string) ($inspection['inspection_id'] ?? 'N/A'),
        'customer_code' => (string) ($customer['owner_code'] ?? $customer['customer_id'] ?? $inspection['customer_id'] ?? 'N/A'),
        'customer_name' => (string) ($customer['owner_name'] ?? $inspection['customer_name'] ?? 'N/A'),
        'heads' => (string) ($anteMortem['no_of_heads'] ?? 'N/A'),
        'farm_owner' => (string) ($anteMortem['farm_owner'] ?? 'N/A'),
        'origin' => (string) ($anteMortem['origin'] ?? 'N/A'),
        'category' => (string) ($anteMortem['category'] ?? 'N/A'),
        'document' => is_array($anteMortem['document_presented'] ?? null)
            ? implode(', ', $anteMortem['document_presented'])
            : (string) ($anteMortem['document_presented'] ?? 'N/A'),
        'document_number' => (string) ($anteMortem['document_number'] ?? ''),
        'inspector' => (string) ($inspection['inspector_name'] ?? 'N/A'),
    ];
}

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">Reports</h3>
                <p class="text-secondary mb-0">Ante Mortem records for <?php echo htmlspecialchars($selectedDate->format('F d, Y')); ?>.</p>
            </div>
            <button class="btn btn-primary no-print" type="button" onclick="window.print();"><i class="fa-solid fa-print me-2"></i>Print Ante Mortem</button>
        </div>

        <div class="card shadow-sm border-0 reports-ante-mortem-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Ante Mortem Records</h5>
                        <p class="text-secondary mb-0"><?php echo count($anteMortemRecords); ?> record(s) found for the selected date.</p>
                    </div>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($selectedDate->format('F d, Y')); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle reports-ante-mortem-table mb-0">
                        <thead>
                            <tr>
                                <th>Inspection ID</th>
                                <th>Customer / Code</th>
                                <th>No. of Heads</th>
                                <th>Farm / Farmer</th>
                                <th>Origin</th>
                                <th>Category</th>
                                <th>Document Presented</th>
                                <th>Inspector</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($anteMortemRecords as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['inspection_id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($record['customer_name']); ?></strong>
                                        <small class="d-block text-secondary"><?php echo htmlspecialchars($record['customer_code']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['heads']); ?></td>
                                    <td><?php echo htmlspecialchars($record['farm_owner']); ?></td>
                                    <td><?php echo htmlspecialchars($record['origin']); ?></td>
                                    <td><?php echo htmlspecialchars($record['category']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($record['document']); ?>
                                        <?php if ($record['document_number'] !== ''): ?>
                                            <small class="d-block text-secondary"><?php echo htmlspecialchars($record['document_number']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['inspector']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$anteMortemRecords): ?>
                        <p class="text-secondary text-center py-4 mb-0">No Ante Mortem records were found for this date.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
