<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_inspector_logged_in();

$inspections = array_values(array_filter(get_array_values(firebase_get('inspections')), 'is_array'));
$savedCertificates = array_values(array_filter(get_array_values(firebase_get('certificates')), 'is_array'));
$registrationsRaw = firebase_get('client_registrations');
$registrations = is_array($registrationsRaw) ? $registrationsRaw : [];
$certificates = [];

foreach ($inspections as $inspection) {
    if (empty($inspection['certificate_id']) || strtolower((string) ($inspection['decision'] ?? '')) !== 'passed') {
        continue;
    }

    $registration = [];
    $registrationKey = $inspection['registration_key'] ?? '';
    if ($registrationKey !== '' && isset($registrations[$registrationKey]) && is_array($registrations[$registrationKey])) {
        $registration = $registrations[$registrationKey];
    } else {
        foreach ($registrations as $registrationItem) {
            if (!is_array($registrationItem)) {
                continue;
            }
            if (!empty($inspection['registration_id']) && ($registrationItem['inspection_id'] ?? '') === $inspection['registration_id']) {
                $registration = $registrationItem;
                break;
            }
        }
    }
    if (!$registration && is_array($inspection['inspection_data']['customer'] ?? null)) {
        $registration = $inspection['inspection_data']['customer'];
    }

    $dateIssued = $inspection['inspection_data']['date_issued']
        ?? $inspection['inspection_date']
        ?? substr((string) ($inspection['created_at'] ?? ''), 0, 10);
    $weight = $registration['estimated_weight']
        ?? $inspection['inspection_data']['meat_summary']['weight']
        ?? $inspection['weight']
        ?? 'N/A';

    $certificates[] = [
        'inspection_id' => (string) ($inspection['inspection_id'] ?? ''),
        'certificate_id' => (string) $inspection['certificate_id'],
        'customer_name' => (string) ($registration['owner_name'] ?? $inspection['customer_name'] ?? 'N/A'),
        'customer_code' => (string) ($registration['owner_code'] ?? $registration['customer_id'] ?? $inspection['customer_id'] ?? $inspection['registration_id'] ?? 'N/A'),
        'meat_type' => (string) ($registration['animal_type'] ?? $inspection['inspection_data']['mic']['animal_type'] ?? 'N/A'),
        'weight' => $weight,
        'date_issued' => (string) $dateIssued,
    ];
}

foreach ($savedCertificates as $savedCertificate) {
    $certificateId = (string) ($savedCertificate['certificate_id'] ?? '');
    if ($certificateId === '' || in_array($certificateId, array_column($certificates, 'certificate_id'), true)) {
        continue;
    }

    $savedData = is_array($savedCertificate['inspection_data'] ?? null) ? $savedCertificate['inspection_data'] : [];
    $certificates[$certificateId] = [
        'inspection_id' => (string) ($savedCertificate['inspection_id'] ?? ''),
        'certificate_id' => $certificateId,
        'customer_name' => (string) ($savedCertificate['customer_name'] ?? $savedData['owner_dealer'] ?? 'N/A'),
        'customer_code' => (string) ($savedCertificate['customer_id'] ?? 'N/A'),
        'meat_type' => 'N/A',
        'weight' => 'N/A',
        'date_issued' => (string) ($savedData['date_issued'] ?? $savedCertificate['issued_at'] ?? 'N/A'),
    ];
}

$certificates = array_values($certificates);
usort($certificates, static function (array $first, array $second): int {
    return strcmp($second['date_issued'], $first['date_issued']);
});

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="main-content inspector-certificates-page">
    <?php require_once __DIR__ . '/../includes/inspector_navbar.php'; ?>
    <div class="certificate-records-page">
        <div class="certificate-records-container">
        <div class="certificate-breadcrumb">
            <a href="/inspector/dashboard.php"><i class="fa-solid fa-house"></i></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Meat Inspection Certificates</span>
        </div>

        <header class="certificate-records-heading">
            <div class="certificate-records-title">
                <span class="certificate-records-icon"><i class="fa-solid fa-file-circle-check"></i></span>
                <div>
                    <h1>Meat Inspection Certificates</h1>
                    <p>View and manage the meat inspection certificates based on inspection records.</p>
                </div>
            </div>
            <a href="/inspector/dashboard.php" class="certificate-back-button">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </header>

        <form class="certificate-records-filters" id="certificateFilters">
            <label class="certificate-search-field">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="certificateSearch" placeholder="Search by certificate number, customer name, or code..." aria-label="Search certificates">
            </label>
            <label class="certificate-date-field">
                <i class="fa-regular fa-calendar-days"></i>
                <span>From Date</span>
                <input type="date" id="certificateFromDate" aria-label="From date">
            </label>
            <label class="certificate-date-field">
                <i class="fa-regular fa-calendar-days"></i>
                <span>To Date</span>
                <input type="date" id="certificateToDate" aria-label="To date">
            </label>
            <label class="certificate-type-field">
                <select id="certificateMeatType" aria-label="Filter by meat type">
                    <option value="">All Meat Types</option>
                    <?php
                    $meatTypes = array_unique(array_filter(array_column($certificates, 'meat_type')));
                    sort($meatTypes);
                    foreach ($meatTypes as $meatType):
                    ?>
                        <option value="<?php echo htmlspecialchars(strtolower($meatType)); ?>"><?php echo htmlspecialchars($meatType); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="certificate-search-button"><i class="fa-solid fa-magnifying-glass"></i>Search</button>
        </form>

        <section class="certificate-records-card">
            <div class="certificate-records-card-heading">
                <h2><i class="fa-regular fa-file-lines"></i> Certificate Records</h2>
                <a href="/inspector/generate_certificate.php" class="certificate-generate-button"><i class="fa-solid fa-plus"></i>Generate Certificate</a>
            </div>
            <div class="certificate-table-wrap">
                <table class="certificate-records-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Certificate No.</th>
                            <th>Customer Name</th>
                            <th>Customer Code</th>
                            <th>Meat Type</th>
                            <th>Weight (kg)</th>
                            <th>Date Issued</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="certificateRecordsBody">
                        <?php foreach ($certificates as $index => $certificate): ?>
                            <tr data-certificate-row
                                data-search="<?php echo htmlspecialchars(strtolower(implode(' ', [$certificate['certificate_id'], $certificate['customer_name'], $certificate['customer_code']])), ENT_QUOTES); ?>"
                                data-date="<?php echo htmlspecialchars(substr($certificate['date_issued'], 0, 10)); ?>"
                                data-meat-type="<?php echo htmlspecialchars(strtolower($certificate['meat_type'])); ?>">
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($certificate['certificate_id']); ?></td>
                                <td><?php echo htmlspecialchars($certificate['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($certificate['customer_code']); ?></td>
                                <td><?php echo htmlspecialchars($certificate['meat_type']); ?></td>
                                <td><?php echo htmlspecialchars(is_numeric($certificate['weight']) ? number_format((float) $certificate['weight'], 2) : (string) $certificate['weight']); ?></td>
                                <td><?php echo htmlspecialchars($certificate['date_issued']); ?></td>
                                <td><span class="certificate-status">Issued</span></td>
                                <td>
                                    <div class="certificate-actions">
                                        <a href="/inspector/generate_certificate.php?inspection_id=<?php echo urlencode($certificate['inspection_id']); ?>&certificate_id=<?php echo urlencode($certificate['certificate_id']); ?>" class="certificate-action-button"><i class="fa-regular fa-eye"></i>View</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="certificate-empty-state<?php echo $certificates ? ' d-none' : ''; ?>" id="certificateEmptyState">No issued certificates found.</div>
            </div>
            <div class="certificate-records-footer">
                <span id="certificateRecordCount"><?php echo $certificates ? 'Showing 1 to ' . count($certificates) . ' of ' . count($certificates) . ' records' : 'Showing 0 to 0 of 0 records'; ?></span>
                <div class="certificate-pagination" aria-label="Certificate pagination">
                    <button type="button" disabled aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="active" aria-current="page">1</button>
                    <button type="button" disabled aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </section>
    </div>
</div>
</div>
<script>
    document.getElementById('certificateFilters').addEventListener('submit', function (event) {
        event.preventDefault();
        const query = document.getElementById('certificateSearch').value.trim().toLowerCase();
        const fromDate = document.getElementById('certificateFromDate').value;
        const toDate = document.getElementById('certificateToDate').value;
        const meatType = document.getElementById('certificateMeatType').value;
        const rows = Array.from(document.querySelectorAll('[data-certificate-row]'));
        const visibleRows = rows.filter(function (row) {
            const rowDate = row.dataset.date || '';
            return (!query || row.dataset.search.includes(query))
                && (!fromDate || rowDate >= fromDate)
                && (!toDate || rowDate <= toDate)
                && (!meatType || row.dataset.meatType === meatType);
        });

        rows.forEach(function (row) {
            row.classList.toggle('d-none', !visibleRows.includes(row));
        });
        document.getElementById('certificateEmptyState').classList.toggle('d-none', visibleRows.length > 0);
        document.getElementById('certificateRecordCount').textContent = visibleRows.length
            ? 'Showing 1 to ' + visibleRows.length + ' of ' + visibleRows.length + ' records'
            : 'Showing 0 to 0 of 0 records';
    });
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
