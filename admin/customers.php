<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$customersRaw = firebase_get('customers');
$requestedDate = trim((string) ($_GET['date'] ?? ''));
$selectedDate = DateTime::createFromFormat('!Y-m-d', $requestedDate);
$selectedDateValue = $selectedDate && $selectedDate->format('Y-m-d') === $requestedDate
    ? $selectedDate->format('Y-m-d')
    : null;
$today = $selectedDateValue ?? date('Y-m-d');
$customers = is_array($customersRaw) ? array_filter($customersRaw, function ($customer) use ($selectedDateValue) {
    if (!is_array($customer)) {
        return false;
    }
    if ($selectedDateValue === null) {
        return true;
    }
    $recordDate = substr((string) ($customer['created_at'] ?? ''), 0, 10);
    return $recordDate === $selectedDateValue;
}) : [];

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">Customers</h3>
                <p class="text-secondary mb-0">
                    <?php if ($selectedDateValue !== null): ?>
                        Customer profiles registered on <?php echo htmlspecialchars($selectedDate->format('F d, Y')); ?>.
                    <?php else: ?>
                        Showing all registered customer profiles.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable mb-0">
                        <thead>
                            <tr>
                                <th>Customer ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Origin</th>
                                <th>Registered</th>
                                <th>QR</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $key => $customer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer['customer_id'] ?? $key); ?></td>
                                    <td><?php echo htmlspecialchars($customer['owner_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($customer['contact_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($customer['origin'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $registeredAt = (string) ($customer['created_at'] ?? '');
                                        echo htmlspecialchars($registeredAt !== '' ? date('M d, Y h:i A', strtotime($registeredAt)) : 'N/A');
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($customer['qr_image'])): ?>
                                            <span class="badge bg-success">Saved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Missing</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/admin/customer_profile.php?id=<?php echo urlencode($key); ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td class="text-center">No customers registered yet.</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>