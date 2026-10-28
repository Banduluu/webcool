<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$inspectionsRaw = firebase_get('inspections');
$savedCertificates = array_values(array_filter(get_array_values(firebase_get('certificates')), 'is_array'));
$requestedDate = trim((string) ($_GET['date'] ?? ''));
$selectedDate = DateTime::createFromFormat('!Y-m-d', $requestedDate);
if (!$selectedDate || $selectedDate->format('Y-m-d') !== $requestedDate) {
    $selectedDate = new DateTime('today');
}
$selectedDateValue = $selectedDate->format('Y-m-d');
$getRecordDate = static function (array $item): string {
    $inspectionData = is_array($item['inspection_data'] ?? null) ? $item['inspection_data'] : [];
    return substr((string) (
        $inspectionData['date_issued']
        ?? $item['inspection_date']
        ?? $item['created_at']
        ?? $item['certificate_created_at']
        ?? ''
    ), 0, 10);
};
$inspections = array_values(array_filter(get_array_values($inspectionsRaw), function ($item) use ($selectedDateValue, $getRecordDate) {
    if (!is_array($item) || (($item['status'] ?? '') !== 'MIC' && empty($item['certificate_id']))) {
        return false;
    }
    return $getRecordDate($item) === $selectedDateValue;
}));
$inspectionIds = [];
foreach ($inspections as $inspection) {
    if (!empty($inspection['certificate_id'])) {
        $inspectionIds[(string) $inspection['certificate_id']] = true;
    }
}
foreach ($savedCertificates as $certificate) {
    $certificateId = (string) ($certificate['certificate_id'] ?? '');
    if ($certificateId === '' || isset($inspectionIds[$certificateId])) {
        continue;
    }
    $certificateData = is_array($certificate['inspection_data'] ?? null) ? $certificate['inspection_data'] : [];
    $recordDate = substr((string) ($certificateData['date_issued'] ?? $certificate['issued_at'] ?? ''), 0, 10);
    if ($recordDate !== $selectedDateValue) {
        continue;
    }
    $inspections[] = [
        'inspection_id' => (string) ($certificate['inspection_id'] ?? ''),
        'certificate_id' => $certificateId,
        'customer_name' => (string) ($certificate['customer_name'] ?? $certificateData['owner_dealer'] ?? 'N/A'),
        'inspector_name' => (string) ($certificate['inspector_name'] ?? 'N/A'),
        'inspection_date' => $recordDate,
        'inspection_data' => $certificateData,
        'record_date' => $recordDate,
    ];
}
$summary = [
    'total' => count($inspections),
];

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">MIC Viewer</h3>
                <p class="text-secondary mb-0">Saved inspection records for <?php echo htmlspecialchars($selectedDate->format('F d, Y')); ?> are compiled here for quick review and management.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ([
                ['label' => 'Total MIC Records', 'value' => $summary['total'], 'icon' => 'fa-database', 'color' => 'primary'],
            ] as $card) { ?>
                <div class="col-md-6 col-xl-3">
                    <div class="card shadow-sm border-0 p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="text-muted"><i class="fa-solid <?php echo $card['icon']; ?> fa-lg"></i></div>
                            <span class="badge bg-<?php echo $card['color']; ?>-light text-<?php echo $card['color']; ?>"><?php echo $card['value']; ?></span>
                        </div>
                        <h6 class="text-secondary mb-1"><?php echo $card['label']; ?></h6>
                        <h3 class="fw-bold"><?php echo $card['value']; ?></h3>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">Compiled MIC Records</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable mb-0" data-certificate-number="true">
                        <thead>
                            <tr>
                                <th>Inspection ID</th>
                                <th>Certificate #</th>
                                <th>Customer</th>
                                <th>Inspector</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inspections as $index => $inspection): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($inspection['inspection_id'] ?? 'N/A'); ?></td>
                                    <td class="certificate-number"><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($inspection['customer_name'] ?? $inspection['customer_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($inspection['inspector_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($inspection['record_date'] ?? ($getRecordDate($inspection) ?: 'N/A')); ?></td>
                                    <td>
                                        <a href="/admin/mic_certificate.php?inspection_id=<?php echo urlencode($inspection['inspection_id'] ?? ''); ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <a href="/admin/mic_edit.php?inspection_id=<?php echo urlencode($inspection['inspection_id'] ?? ''); ?>" class="btn btn-sm btn-outline-secondary">Edit MIC</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
