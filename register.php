<?php
/**
 * Swastya Setu - Multi-Role Account Registration
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh the page.';
    } else {
        $role = trim($_POST['role'] ?? 'patient');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must be at least 8 characters long and contain both letters and numbers.';
        } elseif (empty($phone)) {
            $error = 'Contact phone number is required.';
        } else {
            try {
                $db = getDB();
                // Check if email already exists
                $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $chk->execute([$email]);
                if ($chk->fetch()) {
                    $error = 'An account with this email address already exists. Please log in.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $db->beginTransaction();

                    if ($role === 'hospital') {
                        $hospitalName = trim($_POST['hospital_name'] ?? '');
                        $contactPerson = trim($_POST['contact_person'] ?? '');
                        $city = trim($_POST['city'] ?? 'New Delhi');
                        $address = trim($_POST['address'] ?? '');
                        $hospType = $_POST['hospital_type'] ?? 'Private';
                        $beds = (int)($_POST['total_beds'] ?? 40);

                        $insUser = $db->prepare("INSERT INTO users (name, email, password_hash, phone, role, status) VALUES (?, ?, ?, ?, 'hospital', 'pending')");
                        $insUser->execute([$contactPerson, $email, $hash, $phone]);
                        $newUserId = $db->lastInsertId();

                        $insHosp = $db->prepare("INSERT INTO hospitals (user_id, name, city, address, phone, email, hospital_type, emergency_services, icu_available, blood_bank, available_beds, total_beds, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, 1, ?, ?, 28.6139, 77.2090, 'pending')");
                        $insHosp->execute([$newUserId, $hospitalName, $city, $address, $phone, $email, $hospType, $beds, $beds]);

                        $db->commit();
                        setFlash('info', 'Hospital partner registration submitted successfully! An administrator will review and verify your account credentials.');
                        header('Location: ' . BASE_URL . '/login.php');
                        exit;

                    } elseif ($role === 'pharmacy') {
                        $pharmacyName = trim($_POST['pharmacy_name'] ?? '');
                        $ownerName = trim($_POST['owner_name'] ?? '');
                        $license = trim($_POST['license_number'] ?? 'DL-PH-' . rand(1000, 9999));
                        $city = trim($_POST['city'] ?? 'New Delhi');
                        $address = trim($_POST['address'] ?? '');
                        $is24x7 = isset($_POST['is_24x7']) ? 1 : 0;
                        $delivery = isset($_POST['home_delivery']) ? 1 : 0;

                        $insUser = $db->prepare("INSERT INTO users (name, email, password_hash, phone, role, status) VALUES (?, ?, ?, ?, 'pharmacy', 'pending')");
                        $insUser->execute([$ownerName, $email, $hash, $phone]);
                        $newUserId = $db->lastInsertId();

                        $insPharm = $db->prepare("INSERT INTO pharmacies (user_id, name, owner_name, license_number, address, city, phone, email, latitude, longitude, is_24x7, is_open, home_delivery, discount_info, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 28.6250, 77.2100, ?, 1, ?, '15% Off on Prescriptions', 'pending')");
                        $insPharm->execute([$newUserId, $pharmacyName, $ownerName, $license, $address, $city, $phone, $email, $is24x7, $delivery]);

                        $db->commit();
                        setFlash('info', 'Pharmacy partner registration submitted successfully! Your account will be activated after administrative license verification.');
                        header('Location: ' . BASE_URL . '/login.php');
                        exit;

                    } else { // Patient default
                        $name = trim($_POST['name'] ?? '');
                        $bloodGroup = trim($_POST['blood_group'] ?? 'O+');
                        $age = (int)($_POST['age'] ?? 25);
                        $gender = $_POST['gender'] ?? 'Male';
                        $city = trim($_POST['city'] ?? 'New Delhi');
                        $address = trim($_POST['address'] ?? '');
                        $emergencyName = trim($_POST['emergency_contact_name'] ?? 'Family Contact');
                        $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? $phone);

                        $insUser = $db->prepare("INSERT INTO users (name, email, password_hash, phone, role, status) VALUES (?, ?, ?, ?, 'patient', 'active')");
                        $insUser->execute([$name, $email, $hash, $phone]);
                        $newUserId = $db->lastInsertId();

                        $insProf = $db->prepare("INSERT INTO user_profiles (user_id, blood_group, age, gender, address, city, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $insProf->execute([$newUserId, $bloodGroup, $age, $gender, $address, $city, $emergencyName, $emergencyPhone]);

                        $db->commit();
                        setFlash('success', 'Registration successful! You can now log in to your patient portal.');
                        header('Location: ' . BASE_URL . '/login.php');
                        exit;
                    }
                }
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Registration error: " . $e->getMessage());
                $error = 'An error occurred during account creation. Please verify your details and try again.';
            }
        }
            }
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="p-4 p-md-5 bg-white">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold mb-1">Create an Account</h2>
                        <p class="text-muted small">Join Swastya Setu for unified healthcare access</p>
                    </div>

                    <!-- Role Selection Pills -->
                    <ul class="nav nav-pills nav-justified mb-4 p-1 bg-light rounded-4" id="regTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active rounded-4 fw-semibold" id="patient-tab" data-bs-toggle="pill" data-bs-target="#tab-patient" type="button" onclick="setRole('patient')">
                                <i class="fa-solid fa-user me-2"></i> Patient
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link rounded-4 fw-semibold" id="hospital-tab" data-bs-toggle="pill" data-bs-target="#tab-hospital" type="button" onclick="setRole('hospital')">
                                <i class="fa-solid fa-hospital me-2"></i> Hospital Partner
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link rounded-4 fw-semibold" id="pharmacy-tab" data-bs-toggle="pill" data-bs-target="#tab-pharmacy" type="button" onclick="setRole('pharmacy')">
                                <i class="fa-solid fa-store me-2"></i> Pharmacy Partner
                            </button>
                        </li>
                    </ul>

                    <form method="POST" action="register.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="role" id="selected-role" value="patient">

                        <!-- Tab Content -->
                        <div class="tab-content" id="regTabContent">
                            
                            <!-- 1. Patient Fields -->
                            <div class="tab-pane fade show active" id="tab-patient">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Full Name *</label>
                                        <input type="text" name="name" class="form-control rounded-3" placeholder="e.g. Ramesh Kumar">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Phone Number *</label>
                                        <input type="tel" name="phone" class="form-control rounded-3" placeholder="+91 9876543210">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted">Blood Group</label>
                                        <select name="blood_group" class="form-select rounded-3">
                                            <option value="A+">A+</option>
                                            <option value="A-">A-</option>
                                            <option value="B+" selected>B+</option>
                                            <option value="B-">B-</option>
                                            <option value="O+">O+</option>
                                            <option value="O-">O-</option>
                                            <option value="AB+">AB+</option>
                                            <option value="AB-">AB-</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted">Age</label>
                                        <input type="number" name="age" class="form-control rounded-3" value="28">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted">Gender</label>
                                        <select name="gender" class="form-select rounded-3">
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold text-muted">Residential Address</label>
                                        <input type="text" name="address" class="form-control rounded-3" placeholder="House/Flat No, Street, Landmark">
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Hospital Fields -->
                            <div class="tab-pane fade" id="tab-hospital">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-8">
                                        <label class="form-label small fw-semibold text-muted">Hospital / Clinic Name *</label>
                                        <input type="text" name="hospital_name" class="form-control rounded-3" placeholder="e.g. Metro Care Hospital">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted">Hospital Type</label>
                                        <select name="hospital_type" class="form-select rounded-3">
                                            <option value="Private">Private</option>
                                            <option value="Govt">Government</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Administrator / Contact Person *</label>
                                        <input type="text" name="contact_person" class="form-control rounded-3" placeholder="Dr. / Admin Name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Total Bed Capacity</label>
                                        <input type="number" name="total_beds" class="form-control rounded-3" value="50">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold text-muted">Hospital Address & Landmark</label>
                                        <input type="text" name="address" class="form-control rounded-3" placeholder="Sector / Road, City">
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Pharmacy Fields -->
                            <div class="tab-pane fade" id="tab-pharmacy">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Pharmacy Name *</label>
                                        <input type="text" name="pharmacy_name" class="form-control rounded-3" placeholder="e.g. LifeLine Chemist">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Owner / Pharmacist Name *</label>
                                        <input type="text" name="owner_name" class="form-control rounded-3" placeholder="Pharmacist Name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">Drug License Number</label>
                                        <input type="text" name="license_number" class="form-control rounded-3" placeholder="e.g. DL-2024-8841">
                                    </div>
                                    <div class="col-md-6 d-flex align-items-center gap-3 pt-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_24x7" id="chk24x7" checked>
                                            <label class="form-check-label small" for="chk24x7">24x7 Open</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="home_delivery" id="chkDelivery" checked>
                                            <label class="form-check-label small" for="chkDelivery">Home Delivery</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold text-muted">Pharmacy Shop Address</label>
                                        <input type="text" name="address" class="form-control rounded-3" placeholder="Shop No, Market, City">
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Common Email, Password, City -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Email Address *</label>
                                <input type="email" name="email" class="form-control rounded-3" placeholder="name@domain.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Password (Min 6 Characters) *</label>
                                <input type="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">City</label>
                                <input type="text" name="city" class="form-control rounded-3" value="New Delhi">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold shadow-sm mt-3">
                            <i class="fa-solid fa-user-plus me-2"></i> Register Account
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <span class="small text-muted">Already registered? </span>
                        <a href="login.php" class="small fw-bold text-primary text-decoration-none">Sign In Here</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function setRole(roleName) {
        document.getElementById('selected-role').value = roleName;
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
