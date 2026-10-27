<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_inspector_logged_in();

$errors = [];
$message = null;
$customer = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ownerName = trim($_POST['owner_name'] ?? '');
    $ownerCode = trim($_POST['owner_code'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');

    if ($ownerName === '') {
        $errors[] = 'Customer or meat dealer name is required.';
    }
    if ($contactNumber === '') {
        $errors[] = 'Contact number is required.';
    }
    if (empty($errors)) {
        $customers = get_array_values(firebase_get('customers'));
        $customerId = generate_customer_id($customers);
        $ownerCode = $ownerCode !== '' ? $ownerCode : $customerId;
        $qrPath = save_customer_qr_image($customerId);

        $customer = [
            'customer_id' => $customerId,
            'owner_name' => $ownerName,
            'owner_code' => $ownerCode,
            'contact_number' => $contactNumber,
            'created_at' => date('Y-m-d H:i:s'),
            'qr_image' => $qrPath !== null ? customer_qr_image_web_path($customerId) : customer_qr_url($customerId),
        ];
        $result = firebase_post('customers', $customer);
        if (is_array($result) && !empty($result['name'])) {
            $message = 'Customer registered successfully.';
        } else {
            $errors[] = is_array($result) && !empty($result['error'])
                ? 'Customer could not be saved: ' . $result['error']
                : 'Customer could not be saved because the database did not return a record key.';
            $customer = null;
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="main-content inspector-registration-page">
    <?php require_once __DIR__ . '/../includes/inspector_navbar.php'; ?>
    <main class="container-fluid px-3 px-md-4 py-4">
        <div class="inspector-registration-heading mb-4">
            <div>
                <h1 class="mb-2">Customer Registration</h1>
                <p class="mb-0">Register a customer or meat dealer to generate their unique QR code.</p>
            </div>
            <a class="btn btn-outline-secondary" href="/inspector/dashboard.php"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3 p-md-4">
                        <h2 class="h4 mb-4">Customer Information</h2>
                        <form method="post">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="owner_name">Customer / Meat Dealer Name <span class="text-danger">*</span></label>
                                    <input class="form-control form-control-lg" id="owner_name" name="owner_name" value="<?php echo htmlspecialchars($_POST['owner_name'] ?? ''); ?>" placeholder="Enter full name" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="contact_number">Contact Number <span class="text-danger">*</span></label>
                                    <input class="form-control form-control-lg" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>" placeholder="Enter contact number" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="owner_code">Owner / Code</label>
                                    <input class="form-control form-control-lg" id="owner_code" name="owner_code" value="<?php echo htmlspecialchars($_POST['owner_code'] ?? ''); ?>" placeholder="Enter owner code">
                                </div>
                            </div>
                            <p class="text-secondary small mt-4 mb-3">* Required field. Other fields can be adjusted to match your client's actual registration requirements.</p>
                            <button class="btn btn-success btn-lg w-100" type="submit"><i class="fa-solid fa-user-plus me-2"></i>Register Customer &amp; Generate QR Code</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="inspector-qr-panel">
                    <h2 class="h4"><i class="fa-solid fa-qrcode me-2"></i>QR Code Generation</h2>
                    <?php if ($customer): ?>
                        <p>A unique QR code was generated for this customer.</p>
                        <img class="img-fluid mx-auto d-block my-3" src="<?php echo htmlspecialchars($customer['qr_image']); ?>" alt="Customer QR Code">
                        <strong class="d-block text-center mb-3"><?php echo htmlspecialchars($customer['customer_id']); ?></strong>
                        <a class="btn btn-outline-success w-100" href="<?php echo htmlspecialchars($customer['qr_image']); ?>" download="<?php echo htmlspecialchars($customer['customer_id']); ?>-qr.png"><i class="fa-solid fa-download me-2"></i>Download QR Code</a>
                    <?php else: ?>
                        <p>A unique QR code will be generated after the customer information is saved successfully.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
