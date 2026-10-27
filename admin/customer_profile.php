<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

$customersRaw = firebase_get('customers');
$customers = is_array($customersRaw) ? $customersRaw : [];

$customerKey = $_GET['id'] ?? '';
$customer = $customerKey && isset($customers[$customerKey]) ? $customers[$customerKey] : null;

$errors = [];
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ownerName = trim($_POST['owner_name'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $origin = trim($_POST['origin'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($ownerName === '') {
        $errors[] = 'Owner Name is required.';
    }
    if ($contactNumber === '') {
        $errors[] = 'Contact Number is required.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    }

    if (empty($errors)) {
        $customerId = $customer['customer_id'] ?? generate_customer_id(get_array_values($customers));
        $customerData = [
            'customer_id' => $customerId,
            'owner_name' => $ownerName,
            'contact_number' => $contactNumber,
            'email' => $email,
            'address' => $address,
            'origin' => $origin,
            'province' => $province,
            'remarks' => $remarks,
            'created_at' => $customer['created_at'] ?? date('Y-m-d H:i:s'),
        ];

        $qrPath = save_customer_qr_image($customerId);
        if ($qrPath === null) {
            $errors[] = 'Failed to generate customer QR image. Please check server permissions.';
        } else {
            $customerData['qr_image'] = customer_qr_image_web_path($customerId);
        }

        if (empty($errors)) {
            if ($customerKey) {
                firebase_patch('customers/' . $customerKey, $customerData);
                // Redirect to refresh the page and ensure images are served from disk
                header('Location: /admin/customer_profile.php?id=' . urlencode($customerKey) . '&saved=1');
                exit;
            } else {
                $pushResult = firebase_post('customers', $customerData);
                if (!empty($pushResult['name'])) {
                    $customerKey = $pushResult['name'];
                    header('Location: /admin/customer_profile.php?id=' . urlencode($customerKey) . '&saved=1');
                    exit;
                } else {
                    $errors[] = 'Failed to save customer. Please try again.';
                }
            }
            $customer = $customerData;
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>
<div class="main-content">
    <?php require_once __DIR__ . '/../includes/admin_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1"><?php echo $customer ? 'Edit Customer' : 'New Customer'; ?></h3>
                <p class="text-secondary mb-0">Register a new customer and generate a QR code for identification.</p>
            </div>
            <a href="/admin/customers.php" class="btn btn-outline-secondary">Back to Customers</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-xl-7">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Owner Name</label>
                                <input type="text" name="owner_name" class="form-control" value="<?php echo htmlspecialchars($customer['owner_name'] ?? ''); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($customer['contact_number'] ?? ''); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Origin / Municipality</label>
                                <input type="text" name="origin" class="form-control" value="<?php echo htmlspecialchars($customer['origin'] ?? ''); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Province</label>
                                <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($customer['province'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="3"><?php echo htmlspecialchars($customer['remarks'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-success"><?php echo $customer ? 'Save Customer' : 'Create Customer'; ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">Customer QR Code</h5>
                        <?php if (!empty($customer['customer_id'])): ?>
                            <p class="text-secondary mb-2">Customer ID</p>
                            <p class="fw-semibold mb-4"><?php echo htmlspecialchars($customer['customer_id']); ?></p>
                            <?php if (!empty($customer['qr_image'])): ?>
                                <img src="<?php echo htmlspecialchars($customer['qr_image']); ?>" alt="Customer QR Code" class="img-fluid mb-3" style="max-width:260px;">
                                <div class="d-grid gap-2">
                                    <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($customer['qr_image']); ?>" download="<?php echo htmlspecialchars($customer['customer_id']); ?>-qr.png"><i class="fa-solid fa-download me-2"></i>Download QR</a>
                                    <button class="btn btn-outline-secondary" onclick="window.print();"><i class="fa-solid fa-print me-2"></i>Print QR Card</button>
                                </div>
                                <?php if (isset($_GET['debug']) && $_GET['debug'] === 'qr'): ?>
                                    <div class="alert alert-info mt-3 text-start">
                                        <strong>QR debug:</strong><br>
                                        Image URL: <?php echo htmlspecialchars($customer['qr_image']); ?><br>
                                        <?php $fsPath = __DIR__ . '/../' . ltrim($customer['qr_image'], '/'); ?>
                                        Server file: <?php echo htmlspecialchars($fsPath); ?><br>
                                        Exists: <?php echo file_exists($fsPath) ? 'yes' : 'no'; ?>
                                        <?php if (file_exists($fsPath)): ?>
                                            , size: <?php echo filesize($fsPath); ?> bytes
                                            <div class="mt-2">
                                                <strong>Embedded preview:</strong><br>
                                                <?php $data = base64_encode(file_get_contents($fsPath)); ?>
                                                <img src="data:image/png;base64,<?php echo $data; ?>" alt="Embedded QR" style="max-width:260px;" />
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-warning">QR image not generated yet.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-secondary">Save the customer first to generate the QR code.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>