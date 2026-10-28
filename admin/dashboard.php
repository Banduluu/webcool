<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$inspections = array_values(array_filter(get_array_values(firebase_get('inspections')), 'is_array'));
$savedCertificates = array_values(array_filter(get_array_values(firebase_get('certificates')), 'is_array'));
$requestedDate = trim((string) ($_GET['date'] ?? ''));
$selectedDate = DateTime::createFromFormat('!Y-m-d', $requestedDate);
if (!$selectedDate || $selectedDate->format('Y-m-d') !== $requestedDate) {
    $selectedDate = new DateTime('today');
}
$today = $selectedDate->format('Y-m-d');
$yesterday = (clone $selectedDate)->modify('-1 day')->format('Y-m-d');
$selectedDateLabel = $selectedDate->format('F d, Y');

$getInspectionHeads = static function (array $inspection, array $inspectionData): int {
    $anteMortem = is_array($inspectionData['ante_mortem'] ?? null) ? $inspectionData['ante_mortem'] : [];
    $meatSummary = is_array($inspectionData['meat_summary'] ?? null) ? $inspectionData['meat_summary'] : [];
    foreach ([$anteMortem['no_of_heads'] ?? null, $inspection['head_count'] ?? null, $meatSummary['no_of_heads'] ?? null] as $savedHeads) {
        $heads = (int) $savedHeads;
        if ($heads > 0) {
            return $heads;
        }
    }

    $meatDetails = is_array($inspectionData['meat_details'] ?? null) ? $inspectionData['meat_details'] : [];
    $heads = 0;
    foreach (['pork', 'beef', 'carabeef', 'entrails'] as $meatType) {
        $detail = is_array($meatDetails[$meatType] ?? null) ? $meatDetails[$meatType] : [];
        $heads += (int) ($detail['qty'] ?? $inspection['qty_' . $meatType] ?? 0);
    }

    return $heads;
};

$getDailyInspectionMetrics = static function (array $items, string $date) use ($getInspectionHeads): array {
    $totals = [
        'received' => 0,
        'passed' => 0,
        'ante_mortem_inspections' => 0,
        'slaughtered' => 0,
        'released_weight' => 0.0,
        'condemned_weight' => 0.0,
    ];

    foreach ($items as $inspection) {
        $inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
        $condemnation = is_array($inspectionData['condemnation'] ?? null) ? $inspectionData['condemnation'] : [];
        $hasCondemnationRecord = !empty($condemnation);

        $condemnationDate = substr((string) ($condemnation['recorded_at'] ?? ''), 0, 10);
        if ($hasCondemnationRecord && $condemnationDate === $date) {
            $totals['condemned_weight'] += (float) ($condemnation['weight'] ?? 0);
        }

        $status = strtolower((string) ($inspection['status'] ?? ''));
        $slaughterData = is_array($inspectionData['slaughter'] ?? null) ? $inspectionData['slaughter'] : [];
        $slaughterDate = '';
        foreach ([
            $inspectionData['slaughter_date'] ?? '',
            $slaughterData['slaughter_date'] ?? '',
            $slaughterData['date'] ?? '',
            $inspection['inspection_date'] ?? '',
            $inspection['created_at'] ?? '',
            $inspectionData['date_issued'] ?? '',
            $inspection['certificate_created_at'] ?? '',
        ] as $dateValue) {
            $candidateDate = substr(trim((string) $dateValue), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidateDate)) {
                $slaughterDate = $candidateDate;
                break;
            }
        }
        $hasSlaughterRecord = !empty($inspectionData['slaughter'])
            || !empty($inspectionData['decision'])
            || !empty($inspection['certificate_id'])
            || in_array($status, ['mic', 'slaughter', 'passed', 'partially condemned', 'condemned'], true);
        if ($hasSlaughterRecord && $slaughterDate === $date) {
            $totals['slaughtered'] += $getInspectionHeads($inspection, $inspectionData);
        }

        $createdAt = trim((string) ($inspection['created_at'] ?? ''));
        $recordDate = substr($createdAt !== '' ? $createdAt : (string) ($inspection['inspection_date'] ?? ''), 0, 10);
        if ($recordDate !== $date) {
            continue;
        }

        $anteMortem = is_array($inspectionData['ante_mortem'] ?? null) ? $inspectionData['ante_mortem'] : [];
        if ($anteMortem) {
            $totals['ante_mortem_inspections']++;
        }
        $meatDetails = is_array($inspectionData['meat_details'] ?? null) ? $inspectionData['meat_details'] : [];
        $decision = strtolower((string) ($inspection['decision'] ?? $inspectionData['decision'] ?? ''));
        $heads = $getInspectionHeads($inspection, $inspectionData);
        $totalWeight = 0.0;

        foreach (['pork', 'beef', 'carabeef', 'entrails'] as $meatType) {
            $detail = is_array($meatDetails[$meatType] ?? null) ? $meatDetails[$meatType] : [];
            $totalWeight += (float) ($detail['weight'] ?? $inspection['weight_' . $meatType] ?? 0);
        }

        $isPassed = $decision === 'passed' || $status === 'passed' || !empty($inspection['certificate_id']);
        $isCondemned = $decision === 'condemned' || $status === 'condemned';
        $totals['received'] += $heads;
        if ($isPassed) {
            $totals['passed'] += $heads;
            $totals['released_weight'] += $totalWeight;
        }
        if ($isCondemned && !$hasCondemnationRecord) {
            $totals['condemned_weight'] += $totalWeight;
        }
    }

    return $totals;
};

$getIssuedCertificateCount = static function (array $inspectionItems, array $certificateItems, string $date): int {
    $issuedCertificates = [];
    foreach ($inspectionItems as $inspection) {
        $certificateId = (string) ($inspection['certificate_id'] ?? '');
        if ($certificateId === '' || strtolower((string) ($inspection['decision'] ?? '')) !== 'passed') {
            continue;
        }
        $inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
        $recordDate = substr((string) (
            $inspectionData['date_issued']
            ?? $inspection['inspection_date']
            ?? $inspection['created_at']
            ?? $inspection['certificate_created_at']
            ?? ''
        ), 0, 10);
        if ($recordDate === $date) {
            $issuedCertificates[$certificateId] = true;
        }
    }

    foreach ($certificateItems as $certificate) {
        $certificateId = (string) ($certificate['certificate_id'] ?? '');
        if ($certificateId === '' || isset($issuedCertificates[$certificateId])) {
            continue;
        }
        $certificateData = is_array($certificate['inspection_data'] ?? null) ? $certificate['inspection_data'] : [];
        $recordDate = substr((string) ($certificateData['date_issued'] ?? $certificate['issued_at'] ?? ''), 0, 10);
        if ($recordDate === $date) {
            $issuedCertificates[$certificateId] = true;
        }
    }

    return count($issuedCertificates);
};

$todayMetrics = $getDailyInspectionMetrics($inspections, $today);
$yesterdayMetrics = $getDailyInspectionMetrics($inspections, $yesterday);
$todayCertificates = $getIssuedCertificateCount($inspections, $savedCertificates, $today);
$yesterdayCertificates = $getIssuedCertificateCount($inspections, $savedCertificates, $yesterday);
$dashboardStats = [
    ['label' => 'Animals Received', 'value' => number_format($todayMetrics['received']), 'change' => percent_change($todayMetrics['received'], $yesterdayMetrics['received']), 'current' => $todayMetrics['received'], 'previous' => $yesterdayMetrics['received'], 'icon' => 'fa-cow', 'tone' => 'green'],
    ['label' => 'Certificates Issued', 'value' => number_format($todayCertificates), 'change' => percent_change($todayCertificates, $yesterdayCertificates), 'current' => $todayCertificates, 'previous' => $yesterdayCertificates, 'icon' => 'fa-circle-check', 'tone' => 'green'],
    ['label' => 'Ante-Mortem Inspections', 'value' => number_format($todayMetrics['ante_mortem_inspections']), 'change' => percent_change($todayMetrics['ante_mortem_inspections'], $yesterdayMetrics['ante_mortem_inspections']), 'current' => $todayMetrics['ante_mortem_inspections'], 'previous' => $yesterdayMetrics['ante_mortem_inspections'], 'icon' => 'fa-file-medical', 'tone' => 'green'],
    ['label' => 'Slaughtered', 'value' => number_format($todayMetrics['slaughtered']), 'change' => percent_change($todayMetrics['slaughtered'], $yesterdayMetrics['slaughtered']), 'current' => $todayMetrics['slaughtered'], 'previous' => $yesterdayMetrics['slaughtered'], 'icon' => 'fa-scissors', 'tone' => 'purple'],
    ['label' => 'Meat Released', 'value' => number_format($todayMetrics['released_weight']) . ' kg', 'change' => percent_change((int) round($todayMetrics['released_weight']), (int) round($yesterdayMetrics['released_weight'])), 'current' => $todayMetrics['released_weight'], 'previous' => $yesterdayMetrics['released_weight'], 'icon' => 'fa-bacon', 'tone' => 'orange'],
    ['label' => 'Condemned', 'value' => number_format($todayMetrics['condemned_weight']) . ' kg', 'change' => percent_change((int) round($todayMetrics['condemned_weight']), (int) round($yesterdayMetrics['condemned_weight'])), 'current' => $todayMetrics['condemned_weight'], 'previous' => $yesterdayMetrics['condemned_weight'], 'icon' => 'fa-trash-can', 'tone' => 'red', 'inverse' => true],
];

$timelineEvents = [];
foreach ($inspections as $inspection) {
    $inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
    $createdAt = (string) ($inspection['created_at'] ?? '');
    $createdDate = substr($createdAt !== '' ? $createdAt : (string) ($inspection['inspection_date'] ?? ''), 0, 10);
    if ($createdDate === $today) {
        $status = strtolower((string) ($inspection['status'] ?? ''));
        $timelineEvents[] = [
            'timestamp' => $createdAt !== '' ? strtotime($createdAt) : strtotime($today . ' 00:00:00'),
            'time' => $createdAt !== '' ? date('H:i', strtotime($createdAt)) : '00:00',
            'title' => $status === 'ante-mortem' ? 'Ante-Mortem Inspection Recorded' : 'New Inspection Received',
            'status' => 'success',
        ];
    }

    $dateIssued = substr((string) ($inspectionData['date_issued'] ?? $inspection['inspection_date'] ?? $inspection['created_at'] ?? ''), 0, 10);
    if ($dateIssued === $today && !empty($inspection['certificate_id'])) {
        $timeIssued = (string) ($inspectionData['time_issued'] ?? '');
        $issuedTimestamp = strtotime($today . ($timeIssued !== '' ? ' ' . $timeIssued : ' 00:00:00'));
        $timelineEvents[] = [
            'timestamp' => $issuedTimestamp,
            'time' => date('H:i', $issuedTimestamp),
            'title' => 'Certificate Issued',
            'status' => 'success',
        ];
    }

    $condemnation = is_array($inspectionData['condemnation'] ?? null) ? $inspectionData['condemnation'] : [];
    $recordedAt = (string) ($condemnation['recorded_at'] ?? '');
    if (substr($recordedAt, 0, 10) === $today) {
        $condemnationTimestamp = strtotime($recordedAt);
        if ($condemnationTimestamp === false) {
            $condemnationTimestamp = strtotime($today . ' 00:00:00');
        }
        $timelineEvents[] = [
            'timestamp' => $condemnationTimestamp,
            'time' => date('H:i', $condemnationTimestamp),
            'title' => 'Condemnation Record Submitted',
        ];
    }
}
usort($timelineEvents, static function (array $first, array $second): int {
    return $second['timestamp'] <=> $first['timestamp'];
});
$timelineEvents = array_slice($timelineEvents, 0, 5);

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content dashboard-page">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="row g-2 mb-3">
            <?php foreach ($dashboardStats as $card): ?>
                <?php
                $trend = $card['current'] <=> $card['previous'];
                $isPositiveTrend = $trend > 0;
                if (!empty($card['inverse'])) {
                    $isPositiveTrend = !$isPositiveTrend;
                }
                $changeClass = $trend === 0 ? 'text-secondary' : ($isPositiveTrend ? 'text-success' : 'text-danger');
                $trendIcon = $trend > 0 ? 'fa-arrow-up' : ($trend < 0 ? 'fa-arrow-down' : 'fa-minus');
                ?>
                <div class="col-6 col-xl-2">
                    <div class="card stats-card">
                        <span class="stats-card-icon stats-icon-<?php echo htmlspecialchars($card['tone']); ?>"><i class="fa-solid <?php echo htmlspecialchars($card['icon']); ?>"></i></span>
                        <div class="stats-card-content">
                            <h3><?php echo htmlspecialchars($card['value']); ?></h3>
                            <h6><?php echo htmlspecialchars($card['label']); ?></h6>
                            <?php if (!empty($card['description'])): ?>
                                <span class="stats-description"><?php echo htmlspecialchars($card['description']); ?></span>
                            <?php endif; ?>
                            <span class="stats-period"><?php echo htmlspecialchars($selectedDateLabel); ?></span>
                            <div class="stats-change"><i class="fa-solid <?php echo $trendIcon; ?> <?php echo $changeClass; ?>"></i><span class="<?php echo $changeClass; ?>"><?php echo htmlspecialchars($card['change']); ?></span><span>vs yesterday</span></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="card-title mb-1">Inspection Forecast</h5>
                                <p class="text-secondary mb-0">Historical inspections and predicted volume for the next 7 days.</p>
                            </div>
                            <div class="text-muted">Next 7 Days</div>
                        </div>
                        <canvas id="inspectionForecastChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($selectedDateLabel); ?> Activities</h5>
                        <ul class="timeline mt-4">
                            <?php foreach ($timelineEvents as $event): ?>
                                <li class="timeline-item">
                                    <span class="timeline-time"><?php echo htmlspecialchars(date('g:i A', strtotime($event['time']))); ?></span>
                                    <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($event['title']); ?></p>
                                    <?php if (!empty($event['status'])): ?>
                                        <span class="badge bg-<?php echo htmlspecialchars($event['status']); ?>-light text-<?php echo htmlspecialchars($event['status']); ?>"><?php echo ucfirst(htmlspecialchars($event['status'])); ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($timelineEvents)): ?>
                                <li class="timeline-item text-secondary">No activities recorded for <?php echo htmlspecialchars($selectedDateLabel); ?>.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-0">Recent Inspections</h5>
                        <p class="text-secondary mb-0">Latest inspection requests.</p>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-download me-2"></i>Export</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>Inspection ID</th>
                                <th>Owner Name</th>
                                <th>Animal Type</th>
                                <th>Heads</th>
                                <th>Weight (kg)</th>
                                <th>Inspector</th>
                                <th>Date Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inspections as $inspection):
                                $inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
                                $customerData = is_array($inspectionData['customer'] ?? null) ? $inspectionData['customer'] : [];
                                $meatDetails = is_array($inspectionData['meat_details'] ?? null) ? $inspectionData['meat_details'] : [];
                                $heads = (int) ($inspection['head_count'] ?? $inspectionData['meat_summary']['no_of_heads'] ?? 0);
                                $totalWeight = 0;
                                foreach ($meatDetails as $detail) {
                                    if (!is_array($detail)) {
                                        continue;
                                    }
                                    $heads += (int) ($detail['qty'] ?? 0);
                                    $totalWeight += (float) ($detail['weight'] ?? 0);
                                }
                                $animalType = $customerData['animal_type'] ?? $inspectionData['mic']['animal_type'] ?? 'N/A';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($inspection['inspection_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($inspection['customer_name'] ?? $customerData['owner_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($animalType); ?></td>
                                    <td><?php echo htmlspecialchars($heads); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($totalWeight, 2)); ?></td>
                                    <td><?php echo htmlspecialchars($inspection['inspector_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($inspection['created_at'] ?? $inspection['inspection_date'] ?? 'N/A'); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary">View</button>
                                        <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                        <button class="btn btn-sm btn-outline-success">Certificate</button>
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
<script>
    const forecastLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const forecastData = [28, 34, 45, 38, 46, 51, 57];
    const predictedData = [32, 38, 42, 40, 49, 55, 60];

    window.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('inspectionForecastChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: forecastLabels,
                    datasets: [
                        {
                            label: 'Historical Inspections',
                            data: forecastData,
                            borderColor: '#800000',
                            backgroundColor: 'rgba(128, 0, 0, 0.15)',
                            tension: 0.35,
                            fill: true,
                            pointRadius: 4,
                        },
                        {
                            label: 'Predicted Inspection Volume',
                            data: predictedData,
                            borderColor: '#1E3A8A',
                            backgroundColor: 'rgba(30, 58, 138, 0.12)',
                            tension: 0.35,
                            fill: true,
                            pointRadius: 4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 10 }
                        }
                    }
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
