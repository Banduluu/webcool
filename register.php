<?php
require_once __DIR__ . '/includes/firebase.php';

$inspectionId = isset($_GET['id']) ? trim($_GET['id']) : '';
$isValid = false;
$errorMessage = '';

if ($inspectionId !== '') {
    $inspectionRequest = firebase_get('inspection_requests/' . $inspectionId);
    if (is_array($inspectionRequest) && isset($inspectionRequest['status'])) {
        if ($inspectionRequest['status'] === 'Waiting for Client') {
            $isValid = true;
        } else {
            $errorMessage = 'Invalid or expired QR Code. This inspection is not accepting registrations.';
        }
    } else {
        $errorMessage = 'Invalid or expired QR Code. Please verify the code and try again.';
    }
}

require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-3">
                            <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Client Registration</li>
                        </ol>
                    </nav>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="h4 text-primary-custom mb-1">Client Registration</h2>
                            <p class="text-muted mb-0">Enter your details to continue the inspection process.</p>
                        </div>
                        <div>
                            <span class="badge bg-secondary">Inspection ID</span>
                            <div class="fw-semibold mt-1"><?php echo htmlspecialchars($inspectionId ?: 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($inspectionId === ''): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-body p-5 text-center">
                        <h3 class="h5 mb-3">No Inspection ID</h3>
                        <p class="text-muted mb-4">This page requires an inspection ID, for example <code>?id=MIC-2026-000001</code>.</p>
                        <a href="/index.php" class="btn btn-primary">Go Back</a>
                    </div>
                </div>
            <?php elseif (!$isValid): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-body p-5 text-center">
                        <h3 class="h5 text-danger mb-3">Invalid or Expired QR Code</h3>
                        <p class="text-muted mb-4"><?php echo htmlspecialchars($errorMessage); ?></p>
                        <a href="/index.php" class="btn btn-secondary">Return Home</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <form id="clientRegistrationForm" class="needs-validation" novalidate>
                            <input type="hidden" name="inspection_id" value="<?php echo htmlspecialchars($inspectionId); ?>">
                            <div class="mb-4">
                                <h5 class="text-primary-custom mb-3">Section A: Owner Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="owner_name" class="form-label">Owner Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="owner_name" name="owner_name" required>
                                        <div class="invalid-feedback">Owner Name is required.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="business_name" class="form-label">Business Name</label>
                                        <input type="text" class="form-control" id="business_name" name="business_name">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="contact_number" name="contact_number" required>
                                        <div class="invalid-feedback">A valid phone number is required.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                        <div class="invalid-feedback">Enter a valid email address.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="address" class="form-label">Complete Address <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                                        <div class="invalid-feedback">Complete Address is required.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <h5 class="text-primary-custom mb-3">Section B: Animal Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="animal_type" class="form-label">Animal Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="animal_type" name="animal_type" required>
                                            <option value="">Choose...</option>
                                            <option value="Pig">Pig</option>
                                            <option value="Beef">Beef</option>
                                            <option value="Carabao">Carabao</option>
                                            <option value="Goat">Goat</option>
                                            <option value="Chicken">Chicken</option>
                                            <option value="Other">Other</option>
                                        </select>
                                        <div class="invalid-feedback">Animal Type is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="breed" class="form-label">Breed <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="breed" name="breed" required>
                                        <div class="invalid-feedback">Breed is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="number_of_heads" class="form-label">Number of Heads <span class="text-danger">*</span></label>
                                        <input type="number" min="1" class="form-control" id="number_of_heads" name="number_of_heads" required>
                                        <div class="invalid-feedback">Number of Heads is required.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="estimated_weight" class="form-label">Estimated Live Weight (kg) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.1" min="0" class="form-control" id="estimated_weight" name="estimated_weight" required>
                                        <div class="invalid-feedback">Estimated weight is required.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <h5 class="text-primary-custom mb-3">Section C: Origin Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="farm_name" class="form-label">Farm Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="farm_name" name="farm_name" required>
                                        <div class="invalid-feedback">Farm Name is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="origin" class="form-label">Origin / Municipality <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="origin" name="origin" required>
                                        <div class="invalid-feedback">Origin / Municipality is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="province" class="form-label">Province <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="province" name="province" required>
                                        <div class="invalid-feedback">Province is required.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <h5 class="text-primary-custom mb-3">Section D: Document Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="document_presented" class="form-label">Document Presented <span class="text-danger">*</span></label>
                                        <select class="form-select" id="document_presented" name="document_presented" required>
                                            <option value="">Choose...</option>
                                            <option value="Shipping Permit">Shipping Permit</option>
                                            <option value="Livestock Transport Permit">Livestock Transport Permit</option>
                                            <option value="Veterinary Health Certificate">Veterinary Health Certificate</option>
                                            <option value="Others">Others</option>
                                        </select>
                                        <div class="invalid-feedback">Document Presented is required.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="document_number" class="form-label">Document Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="document_number" name="document_number" required>
                                        <div class="invalid-feedback">Document Number is required.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <h5 class="text-primary-custom mb-3">Section E: Additional Information</h5>
                                <div class="mb-3">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="d-flex flex-column flex-sm-row gap-3">
                                <button type="submit" class="btn btn-primary">Submit Registration</button>
                                <button type="reset" class="btn btn-outline-secondary">Reset Form</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
