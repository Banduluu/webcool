<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$inspectorsRaw = firebase_get('users/inspectors');
$inspectors = is_array($inspectorsRaw) ? $inspectorsRaw : [];

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">User Management</h3>
                <p class="text-secondary mb-0">View and manage registered meat inspectors.</p>
            </div>
            <button id="openAddInspector" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#inspectorModal">
                <i class="fa-solid fa-user-plus me-2"></i>Add Inspector
            </button>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable align-middle mb-0" id="inspectorsTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inspectors as $key => $inspector): ?>
                                <tr data-inspector-id="<?php echo htmlspecialchars($key); ?>">
                                    <td><?php echo htmlspecialchars($inspector['name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($inspector['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($inspector['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($inspector['status'] ?? 'Inactive'); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary inspector-edit" data-inspector-id="<?php echo htmlspecialchars($key); ?>">Edit</button>
                                        <button class="btn btn-sm btn-outline-warning inspector-toggle-status" data-inspector-id="<?php echo htmlspecialchars($key); ?>" data-status="<?php echo htmlspecialchars($inspector['status'] === 'Active' ? 'Inactive' : 'Active'); ?>"><?php echo $inspector['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button>
                                        <button class="btn btn-sm btn-outline-danger inspector-delete" data-inspector-id="<?php echo htmlspecialchars($key); ?>">Delete</button>
                                        <button class="btn btn-sm btn-outline-info inspector-reset-password" data-inspector-id="<?php echo htmlspecialchars($key); ?>">Reset Password</button>
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

<div class="modal fade" id="inspectorModal" tabindex="-1" aria-labelledby="inspectorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inspectorModalLabel">Add Meat Inspector</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="inspectorForm">
                <div class="modal-body">
                    <input type="hidden" id="inspector_id" name="inspector_id" value="">
                    <input type="hidden" id="form_action" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label" for="inspector_name">Name</label>
                        <input type="text" class="form-control" id="inspector_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="inspector_email">Email</label>
                        <input type="email" class="form-control" id="inspector_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="inspector_phone">Contact Number</label>
                        <input type="text" class="form-control" id="inspector_phone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="inspector_status">Status</label>
                        <select class="form-select" id="inspector_status" name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="inspector_password">Password</label>
                        <input type="password" class="form-control" id="inspector_password" name="password" placeholder="Leave blank for default or unchanged">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="inspectorSubmitButton">Save Inspector</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
<script>
    const inspectorsData = <?php echo json_encode($inspectors); ?>;
</script>
