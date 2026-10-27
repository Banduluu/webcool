<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$registrations = get_array_values(firebase_get('client_registrations'));

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">Client Registrations</h3>
                <p class="text-secondary mb-0">View registration records submitted by clients.</p>
            </div>
            <button class="btn btn-success"><i class="fa-solid fa-download me-2"></i>Export Registrations</button>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable mb-0">
                        <thead>
                            <tr>
                                <th>Inspection ID</th>
                                <th>Owner</th>
                                <th>Animal Type</th>
                                <th>Submitted At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $id => $registration): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($id); ?></td>
                                    <td><?php echo htmlspecialchars($registration['owner_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($registration['animal_type'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($registration['submitted_at'] ?? 'N/A'); ?></td>
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
