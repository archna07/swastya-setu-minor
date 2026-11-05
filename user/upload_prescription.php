<?php
/**
 * Swastya Setu - Secure Prescription Upload & Pharmacy Dispatch
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');
$user = currentUser();
$db = getDB();

$selectedPharmId = (int)($_GET['pharmacy_id'] ?? 0);
$error = '';

// Fetch Patient profile address for auto-fill with prepared statement
$profStmt = $db->prepare("SELECT address, city FROM user_profiles WHERE user_id = ?");
$profStmt->execute([$user['id']]);
$userProf = $profStmt->fetch();
$defaultAddress = $userProf['address'] ?? '';

// Fetch Approved Pharmacies for Selection
$pharmacies = $db->query("SELECT id, name, city, is_24x7, home_delivery FROM pharmacies WHERE status = 'approved' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh the page.';
    } else {
        $pharmacyId = (int)($_POST['pharmacy_id'] ?? 0);
        $deliveryAddress = trim($_POST['delivery_address'] ?? '');
        $patientNotes = trim($_POST['patient_notes'] ?? '');

        if (!$pharmacyId) {
            $error = 'Please select a preferred pharmacy to fulfill your prescription.';
        } elseif (empty($deliveryAddress)) {
            $error = 'Delivery address is required for doorstep medicine delivery.';
        } elseif (!isset($_FILES['prescription_file']) || $_FILES['prescription_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please upload a prescription image (JPG, PNG) or PDF document.';
        } else {
            $file = $_FILES['prescription_file'];
            $fileSize = $file['size'];
            $origName = basename($file['name']);
            $fileTmp  = $file['tmp_name'];
            $fileExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
            $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];

            // Verify file size (< 10MB)
            if ($fileSize > MAX_UPLOAD_SIZE) {
                $error = 'Uploaded file exceeds the maximum size limit of 10MB.';
            } elseif (!in_array($fileExt, $allowedExtensions)) {
                $error = 'Invalid file extension. Only JPG, JPEG, PNG, and PDF files are permitted.';
            } else {
                // Verify actual MIME type using mime_content_type
                $realMime = mime_content_type($fileTmp);
                if (!in_array($realMime, $allowedMimes)) {
                    $error = 'Invalid file format detected. Please upload a genuine image or PDF document.';
                } else {
                    // Generate secure random hashed filename
                    $safeFileName = 'rx_' . bin2hex(random_bytes(16)) . '.' . $fileExt;
                    $targetPath = UPLOAD_DIR . $safeFileName;

                    if (!is_dir(UPLOAD_DIR)) {
                        mkdir(UPLOAD_DIR, 0755, true);
                    }

                    if (move_uploaded_file($fileTmp, $targetPath)) {
                        try {
                            $ins = $db->prepare("
                                INSERT INTO prescriptions (patient_id, pharmacy_id, file_path, original_filename, file_type, file_size, delivery_address, patient_notes, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'uploaded')
                            ");
                            $ins->execute([
                                $user['id'],
                                $pharmacyId,
                                $safeFileName,
                                $origName,
                                $realMime,
                                $fileSize,
                                $deliveryAddress,
                                $patientNotes
                            ]);

                            $rxId = $db->lastInsertId();

                            // Notify Pharmacy Owner
                            $pharmUserStmt = $db->prepare("SELECT user_id FROM pharmacies WHERE id = ?");
                            $pharmUserStmt->execute([$pharmacyId]);
                            $pharmUserId = $pharmUserStmt->fetchColumn();
                            if ($pharmUserId) {
                                createNotification(
                                    $pharmUserId,
                                    'New Prescription Uploaded',
                                    "Patient {$user['name']} submitted prescription #RX-{$rxId} for verification.",
                                    'pharmacy/prescriptions.php'
                                );
                            }

                            setFlash('success', "Prescription uploaded successfully! The pharmacy will review it and provide a quote.");
                            header('Location: ' . BASE_URL . '/user/my_prescriptions.php');
                            exit;

                        } catch (Exception $e) {
                            $error = 'Database error while saving prescription: ' . $e->getMessage();
                        }
                    } else {
                        $error = 'Failed to save uploaded file to the secure server directory.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Upload Prescription & Request Delivery';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-file-prescription text-primary me-2"></i> Upload Doctor's Prescription</h2>
                <p class="text-muted small mb-0">Upload a scan or PDF of your medical prescription. Our partner pharmacy will verify the medicines and provide a quote.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 shadow-sm mb-4">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                        <form method="POST" action="upload_prescription.php" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            
                            <!-- Drag & Drop Zone -->
                            <div class="upload-dropzone p-5 text-center rounded-4 mb-4" id="dropArea" style="border: 2px dashed #1E88E5; background: #E3F2FD; cursor: pointer;">
                                <i class="fa-solid fa-cloud-arrow-up fs-1 text-primary mb-3"></i>
                                <h5 class="fw-bold text-dark mb-1">Click to Browse or Drag & Drop Prescription</h5>
                                <p class="text-muted small mb-0">Supported formats: JPG, PNG, PDF (Max 10MB)</p>
                                <input type="file" name="prescription_file" id="rxFileInput" class="d-none" accept=".jpg,.jpeg,.png,.pdf" required onchange="handleFile(this.files[0])">
                            </div>

                            <!-- File Preview Container -->
                            <div id="filePreviewBox" class="p-3 rounded-4 bg-light border mb-4 d-none align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <i id="previewIcon" class="fa-solid fa-file-lines fs-2 text-primary"></i>
                                    <div>
                                        <div class="fw-bold text-dark small" id="previewFileName">filename.pdf</div>
                                        <div class="small text-muted" id="previewFileSize">1.2 MB</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="removeFile()">Remove</button>
                            </div>

                            <!-- Pharmacy Selection -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Select Fulfilling Pharmacy *</label>
                                <select name="pharmacy_id" class="form-select rounded-3 py-2" required>
                                    <option value="">-- Choose Partner Pharmacy --</option>
                                    <?php foreach ($pharmacies as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= ($selectedPharmId == $p['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['city']) ?>) <?= $p['home_delivery'] ? '• Home Delivery' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Delivery Address -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Delivery Address *</label>
                                <textarea name="delivery_address" class="form-control rounded-3" rows="3" placeholder="Enter complete doorstep delivery address with pincode..." required><?= htmlspecialchars($defaultAddress) ?></textarea>
                            </div>

                            <!-- Patient Notes -->
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-muted">Special Instructions / Notes for Pharmacist (Optional)</label>
                                <input type="text" name="patient_notes" class="form-control rounded-3" placeholder="e.g. Please provide generic alternative if original brand is unavailable...">
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4 fw-semibold shadow-sm w-100">
                                <i class="fa-solid fa-paper-plane me-2"></i> Submit Prescription to Pharmacy
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right Side Info Card -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-circle-info text-primary me-2"></i> How It Works</h5>
                        
                        <div class="d-flex gap-3 mb-3">
                            <div class="badge bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; flex-shrink: 0;">1</div>
                            <small class="text-muted"><strong class="text-dark d-block">Upload Prescription:</strong> Clear photograph or PDF of doctor's prescription.</small>
                        </div>

                        <div class="d-flex gap-3 mb-3">
                            <div class="badge bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; flex-shrink: 0;">2</div>
                            <small class="text-muted"><strong class="text-dark d-block">Pharmacist Verification:</strong> Licensed pharmacist manually inspects prescription, checks stock, and enters prices.</small>
                        </div>

                        <div class="d-flex gap-3 mb-3">
                            <div class="badge bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; flex-shrink: 0;">3</div>
                            <small class="text-muted"><strong class="text-dark d-block">Review & Confirm Quote:</strong> You receive an itemized quote to confirm with Cash on Delivery.</small>
                        </div>

                        <div class="d-flex gap-3">
                            <div class="badge bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; flex-shrink: 0;">4</div>
                            <small class="text-muted"><strong class="text-dark d-block">Express Home Delivery:</strong> Medicines packed and delivered to your doorstep.</small>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-light border">
                        <h6 class="fw-bold text-danger mb-2"><i class="fa-solid fa-shield-halved me-1"></i> Privacy & Security</h6>
                        <small class="text-muted">
                            All prescription files are stored securely outside public web directories and are only viewable by you and the designated licensed pharmacy.
                        </small>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('rxFileInput');
    const previewBox = document.getElementById('filePreviewBox');
    const previewName = document.getElementById('previewFileName');
    const previewSize = document.getElementById('previewFileSize');
    const previewIcon = document.getElementById('previewIcon');

    dropArea.addEventListener('click', () => fileInput.click());

    dropArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropArea.style.background = '#BBDEFB';
    });

    dropArea.addEventListener('dragleave', () => {
        dropArea.style.background = '#E3F2FD';
    });

    dropArea.addEventListener('drop', (e) => {
        e.preventDefault();
        dropArea.style.background = '#E3F2FD';
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFile(e.dataTransfer.files[0]);
        }
    });

    function handleFile(file) {
        if (!file) return;
        previewName.innerText = file.name;
        previewSize.innerText = (file.size / 1024 / 1024).toFixed(2) + ' MB';
        
        if (file.type.includes('pdf')) {
            previewIcon.className = 'fa-solid fa-file-pdf fs-2 text-danger';
        } else {
            previewIcon.className = 'fa-solid fa-file-image fs-2 text-success';
        }
        
        previewBox.classList.remove('d-none');
        previewBox.classList.add('d-flex');
    }

    function removeFile() {
        fileInput.value = '';
        previewBox.classList.remove('d-flex');
        previewBox.classList.add('d-none');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
