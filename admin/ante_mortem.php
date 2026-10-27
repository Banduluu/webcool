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
$inspections = array_values(array_filter($allInspections, function ($item) {
	$inspectionData = is_array($item['inspection_data'] ?? null) ? $item['inspection_data'] : [];
	return isset($inspectionData['ante_mortem']) || ($item['status'] ?? '') === 'Ante-Mortem';
}));
$inspections = array_values(array_filter($inspections, function ($item) use ($selectedDateValue) {
	$recordDate = substr((string) ($item['inspection_date'] ?? $item['created_at'] ?? ''), 0, 10);
	return $recordDate === $selectedDateValue;
}));
$pageHeading = 'Ante-Mortem Inspections';
$pageDescription = 'Review records from the ante-mortem inspection stage for ' . $selectedDate->format('F d, Y') . '.';

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
	<?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
	<div class="container-fluid px-4 py-4">
		<div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
			<div>
				<h3 class="mb-1"><?php echo htmlspecialchars($pageHeading); ?></h3>
				<p class="text-secondary mb-0"><?php echo htmlspecialchars($pageDescription); ?></p>
			</div>
		</div>

		<div class="card shadow-sm border-0">
			<div class="card-body">
				<h5 class="card-title mb-3"><?php echo htmlspecialchars($pageHeading); ?> Records</h5>
				<div class="table-responsive">
					<table class="table table-striped table-hover datatable mb-0">
						<thead>
							<tr>
								<th>Owner / Code</th>
								<th>No. of Heads</th>
								<th>Name of Farm / Farmer</th>
								<th>Origin / Place</th>
								<th>Category</th>
								<th>Document Presented</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($inspections as $inspection):
								$inspectionData = is_array($inspection['inspection_data'] ?? null) ? $inspection['inspection_data'] : [];
								$anteMortem = is_array($inspectionData['ante_mortem'] ?? null) ? $inspectionData['ante_mortem'] : [];
								$customerData = is_array($inspectionData['customer'] ?? null) ? $inspectionData['customer'] : [];
								$ownerCode = $customerData['owner_code'] ?? $inspection['owner_code'] ?? $customerData['customer_id'] ?? $inspection['customer_id'] ?? 'N/A';
								$ownerName = $customerData['owner_name'] ?? $inspection['customer_name'] ?? 'N/A';
								$heads = $anteMortem['no_of_heads'] ?? $inspection['no_of_heads'] ?? $inspection['head_count'] ?? $inspectionData['head_count'] ?? $inspectionData['meat_summary']['no_of_heads'] ?? 'N/A';
								$farmOwner = $anteMortem['farm_owner'] ?? $inspection['farm_owner'] ?? 'N/A';
								$origin = $anteMortem['origin'] ?? $inspection['origin'] ?? 'N/A';
								$category = $anteMortem['category'] ?? $inspection['category'] ?? 'N/A';
								$documentPresentedValue = $anteMortem['document_presented'] ?? $inspection['document_presented'] ?? 'N/A';
								$documentPresented = is_array($documentPresentedValue) ? implode(', ', $documentPresentedValue) : $documentPresentedValue;
								$documentNumber = $anteMortem['document_number'] ?? $inspection['document_number'] ?? '';
							?>
								<tr>
									<td><?php echo htmlspecialchars($ownerCode . ' / ' . $ownerName); ?></td>
									<td><?php echo htmlspecialchars($heads); ?></td>
									<td><?php echo htmlspecialchars($farmOwner); ?></td>
									<td><?php echo htmlspecialchars($origin); ?></td>
									<td><?php echo htmlspecialchars($category); ?></td>
									<td>
										<?php echo htmlspecialchars($documentPresented); ?>
										<?php if ($documentNumber !== ''): ?>
											<span class="d-block text-secondary small"><?php echo htmlspecialchars($documentNumber); ?></span>
										<?php endif; ?>
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