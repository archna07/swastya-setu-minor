<?php
/**
 * Swastya Setu - Patient Medical Profile & Health Passport
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');
$user = currentUser();
$db = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $age = (int)($_POST['age'] ?? 30);
        $gender = $_POST['gender'] ?? 'Male';
        $bloodGroup = trim($_POST['blood_group'] ?? 'O+');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $medHistory = trim($_POST['medical_history'] ?? '');
        $allergies = trim($_POST['allergies'] ?? '');
        $emName = trim($_POST['emergency_contact_name'] ?? '');
        $emPhone = trim($_POST['emergency_contact_phone'] ?? '');
        $insurance = trim($_POST['insurance_info'] ?? '');

        if (empty($name) || empty($phone)) {
            $error = 'Name and phone number are required.';
        } else {
            try {
                $db->beginTransaction();

                // Update users table
                $updUser = $db->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $updUser->execute([$name, $phone, $user['id']]);

                // Update user_profiles table (INSERT or UPDATE)
                $chkProf = $db->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
                $chkProf->execute([$user['id']]);
                if ($chkProf->fetch()) {
                    $updProf = $db->prepare("
                        UPDATE user_profiles 
                        SET age = ?, gender = ?, blood_group = ?, city = ?, address = ?, medical_history = ?, allergies = ?, emergency_contact_name = ?, emergency_contact_phone = ?, insurance_info = ?
                        WHERE user_id = ?
                    ");
                    $updProf->execute([$age, $gender, $bloodGroup, $city, $address, $medHistory, $allergies, $emName, $emPhone, $insurance, $user['id']]);
                } else {
                    $insProf = $db->prepare("
                        INSERT INTO user_profiles (user_id, age, gender, blood_group, city, address, medical_history, allergies, emergency_contact_name, emergency_contact_phone, insurance_info)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insProf->execute([$user['id'], $age, $gender, $bloodGroup, $city, $address, $medHistory, $allergies, $emName, $emPhone, $insurance]);
                }

                $db->commit();
                
                // Refresh session user name
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['phone'] = $phone;

                setFlash('success', 'Health Profile and emergency passport updated successfully.');
                header('Location: ' . BASE_URL . '/user/profile.php');
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Error updating profile: ' . $e->getMessage();
            }
        }
    }
}

// Fetch Profile Details
$profStmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1");
$profStmt->execute([$user['id']]);
$profile = $profStmt->fetch() ?: [];

$pageTitle = 'My Health Profile & Medical Passport';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <div class="mb-4">
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-id-card text-primary me-2"></i> Patient Health Profile</h2>
                <p class="text-muted small mb-0">Maintain your digital medical history, emergency contacts, and insurance details.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-4 shadow-sm mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                        <form method="POST" action="profile.php">
                            <?= csrfField() ?>
                            
                            <h5 class="fw-bold text-dark mb-3">Personal & Contact Information</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Full Name *</label>
                                    <input type="text" name="name" class="form-control rounded-3" value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Registered Email</label>
                                    <input type="email" class="form-control rounded-3 bg-light" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Phone Number *</label>
                                    <input type="tel" name="phone" class="form-control rounded-3" value="<?= htmlspecialchars($user['phone']) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted">Age (Years)</label>
                                    <input type="number" name="age" class="form-control rounded-3" value="<?= htmlspecialchars($profile['age'] ?? 30) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted">Gender</label>
                                    <select name="gender" class="form-select rounded-3">
                                        <option value="Male" <?= ($profile['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($profile['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($profile['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">City</label>
                                    <input type="text" name="city" class="form-control rounded-3" value="<?= htmlspecialchars($profile['city'] ?? 'New Delhi') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Blood Group</label>
                                    <select name="blood_group" class="form-select rounded-3">
                                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bg): ?>
                                            <option value="<?= $bg ?>" <?= ($profile['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-muted">Residential Address</label>
                                    <textarea name="address" class="form-control rounded-3" rows="2"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-3">Clinical History & Allergies</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Past Medical Conditions / History</label>
                                    <textarea name="medical_history" class="form-control rounded-3" rows="3" placeholder="e.g. Mild Hypertension, Asthma, Type 2 Diabetes..."><?= htmlspecialchars($profile['medical_history'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Known Allergies</label>
                                    <textarea name="allergies" class="form-control rounded-3" rows="3" placeholder="e.g. Penicillin, Sulfa drugs, Peanuts, Dust..."><?= htmlspecialchars($profile['allergies'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-3">Emergency Contact & Insurance</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Emergency Contact Name & Relation</label>
                                    <input type="text" name="emergency_contact_name" class="form-control rounded-3" value="<?= htmlspecialchars($profile['emergency_contact_name'] ?? 'Family Contact') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Emergency Contact Phone Number</label>
                                    <input type="tel" name="emergency_contact_phone" class="form-control rounded-3" value="<?= htmlspecialchars($profile['emergency_contact_phone'] ?? '+91 9876543210') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-muted">Health Insurance Policy Number & Provider</label>
                                    <input type="text" name="insurance_info" class="form-control rounded-3" value="<?= htmlspecialchars($profile['insurance_info'] ?? 'Star Health Comprehensive (#SH-90812)') ?>" placeholder="Policy details for hospital cashless admission">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-semibold shadow-sm">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Update Health Passport
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right Summary Card -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white text-center mb-4">
                        <div class="d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary mb-3">
                            <i class="fa-solid fa-circle-user fs-1"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                        <div class="mb-3">
                            <span class="badge bg-danger rounded-pill px-3 py-1 fs-6">Blood: <?= htmlspecialchars($profile['blood_group'] ?? 'O+') ?></span>
                        </div>
                        
                        <div class="text-start small text-muted border-top pt-3">
                            <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($user['phone']) ?></p>
                            <p class="mb-1"><strong>Emergency:</strong> <?= htmlspecialchars($profile['emergency_contact_phone'] ?? 'N/A') ?></p>
                            <p class="mb-1"><strong>Allergies:</strong> <?= htmlspecialchars($profile['allergies'] ?: 'None reported') ?></p>
                            <p class="mb-0"><strong>Insurance:</strong> <?= htmlspecialchars($profile['insurance_info'] ?: 'None') ?></p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
