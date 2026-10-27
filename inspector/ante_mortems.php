<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_inspector_logged_in();

$inspections = array_values(array_filter(get_array_values(firebase_get('inspections')), 'is_array'));
$selectedDate = trim($_GET['date'] ?? date('Y-m-d'));
$anteMortems = [];
foreach ($inspections as $inspection) {
    $report = $inspection['inspection_data']['ante_mortem'] ?? null;
    if (!is_array($report)) {
        continue;
    }

    $customer = $inspection['inspection_data']['customer'] ?? [];
    $anteMortems[] = [
        'inspection_id' => $inspection['inspection_id'] ?? 'N/A',
        'date' => $inspection['inspection_date'] ?? substr((string) ($inspection['created_at'] ?? ''), 0, 10),
        'time' => !empty($inspection['created_at']) ? date('h:i A', strtotime($inspection['created_at'])) : 'N/A',
        'customer_id' => $inspection['customer_id'] ?? $customer['customer_id'] ?? '',
        'owner_code' => $customer['owner_code'] ?? $inspection['customer_id'] ?? 'N/A',
        'owner_name' => $inspection['customer_name'] ?? $customer['owner_name'] ?? 'N/A',
        'heads' => $report['no_of_heads'] ?? 'N/A',
        'farm_owner' => $report['farm_owner'] ?? 'N/A',
        'origin' => $report['origin'] ?? 'N/A',
        'category' => $report['category'] ?? 'N/A',
        'document' => is_array($report['document_presented'] ?? null)
            ? implode(', ', $report['document_presented'])
            : ($report['document_presented'] ?? 'N/A'),
        'document_number' => $report['document_number'] ?? '',
    ];
}
usort($anteMortems, static function (array $first, array $second): int {
    return strcmp((string) $second['date'], (string) $first['date']);
});

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="main-content inspector-ante-list-page">
    <?php require_once __DIR__ . '/../includes/inspector_navbar.php'; ?>
    <main class="container-fluid px-3 px-md-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="mb-2"><i class="fa-solid fa-file-circle-check me-2 text-success"></i>Daily Ante-Mortem Report</h1>
                <p class="text-secondary mb-0">Record and review the daily ante-mortem inspection details.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="/inspector/dashboard.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard</a>
                <button type="button" class="btn btn-outline-success" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
                <a href="/inspector/customer_scanner.php" class="btn btn-success"><i class="fa-solid fa-plus me-2"></i>Add Record</a>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="reportDate">Report date</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                            <input class="form-control" type="date" id="reportDate" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button class="btn btn-success" type="submit"><i class="fa-solid fa-filter me-2"></i>Load Report</button>
                    </div>
                    <div class="col-12 col-md">
                        <div class="alert alert-success mb-0 py-2"><i class="fa-solid fa-circle-info me-2"></i>This report follows the Daily Ante-Mortem Report format used by the Meat Inspection Division.</div>
                    </div>
                </form>
            </div>
        </div>

        <?php
        $filteredAnteMortems = array_values(array_filter($anteMortems, static function (array $report) use ($selectedDate): bool {
            return $selectedDate === '' || $report['date'] === $selectedDate;
        }));
        ?>
        <div class="card shadow-sm border-0 inspector-ante-record-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0"><i class="fa-solid fa-clipboard-list me-2 text-success"></i>Inspection Records</h2>
                <span class="badge bg-success"><?php echo count($filteredAnteMortems); ?> record<?php echo count($filteredAnteMortems) === 1 ? '' : 's'; ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 inspector-ante-list-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Owner / Code</th>
                                <th>No. of Heads</th>
                                <th>Name of Farm / Farmer</th>
                                <th>Origin / Place</th>
                                <th>Category</th>
                                <th>Document Presented</th>
                                <th>Date &amp; Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredAnteMortems as $index => $report): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($report['owner_name']); ?></strong>
                                        <small class="d-block text-secondary"><?php echo htmlspecialchars($report['owner_code']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) $report['heads']); ?></td>
                                    <td><?php echo htmlspecialchars($report['farm_owner']); ?></td>
                                    <td><?php echo htmlspecialchars($report['origin']); ?></td>
                                    <td><?php echo htmlspecialchars($report['category']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($report['document']); ?>
                                        <?php if ($report['document_number'] !== ''): ?>
                                            <small class="d-block text-secondary"><?php echo htmlspecialchars($report['document_number']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($report['date'] !== '' ? date('M d, Y', strtotime($report['date'])) : 'N/A'); ?><small class="d-block text-secondary"><?php echo htmlspecialchars($report['time']); ?></small></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a class="btn btn-sm btn-outline-primary" href="/inspector/ante_mortem.php?inspection_id=<?php echo urlencode($report['inspection_id']); ?>"><i class="fa-solid fa-pen me-1"></i>Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($filteredAnteMortems)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-secondary py-4">No Ante Mortem reports were saved for this date.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-secondary small">
                Showing <?php echo count($filteredAnteMortems); ?> of <?php echo count($anteMortems); ?> saved Ante-Mortem records.
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
