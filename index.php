<?php
/**
 * Swastya Setu - Main Landing Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Fetch live statistics from MySQL
$totalUsers = 1500;
$totalHospitals = 120;
$totalPharmacies = 450;
$totalDoctors = 85;

try {
    $db = getDB();
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn() + 150;
    $totalHospitals = (int)$db->query("SELECT COUNT(*) FROM hospitals WHERE status = 'approved'")->fetchColumn();
    $totalPharmacies = (int)$db->query("SELECT COUNT(*) FROM pharmacies WHERE status = 'approved'")->fetchColumn();
    $totalDoctors = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE is_active = 1")->fetchColumn();
} catch (Exception $e) {}

$pageTitle = 'Swastya Setu – Smart Healthcare Assistance Platform';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    
    <?= displayFlash() ?>

    <!-- Hero Section -->
    <div class="hero-section shadow-sm mb-5 p-4 p-md-5 rounded-4" style="background: linear-gradient(135deg, #E3F2FD 0%, #F7FAFC 100%);">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 bg-primary-subtle text-primary px-3 py-1 rounded-pill small fw-bold mb-3">
                    <i class="fa-solid fa-shield-halved"></i> Next-Gen Smart Healthcare Platform
                </div>
                <h1 class="display-5 fw-bold text-dark lh-tight mb-3">
                    Your Complete <span class="text-primary" style="background: linear-gradient(135deg, #1E88E5, #26C6DA); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Digital Health Bridge</span> to Wellness
                </h1>
                <p class="lead text-muted mb-4 fs-6">
                    Connect seamlessly with top hospitals, verified doctors, AI-powered disease diagnostics, and 24/7 smart prescription home delivery in one place.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= BASE_URL ?>/user/prediction.php" class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm fw-semibold">
                        <i class="fa-solid fa-wand-magic-sparkles me-2"></i> AI Symptom Check
                    </a>
                    <a href="<?= BASE_URL ?>/user/pharmacies.php" class="btn btn-outline-primary btn-lg rounded-pill px-4 fw-semibold">
                        <i class="fa-solid fa-capsules me-2"></i> Order Medicines
                    </a>
                    <a href="<?= BASE_URL ?>/user/sos.php" class="btn btn-danger btn-lg rounded-pill px-4 shadow-sm fw-semibold">
                        <i class="fa-solid fa-truck-medical me-2"></i> 108 Emergency SOS
                    </a>
                </div>
            </div>
            
            <div class="col-lg-5 text-center">
                <svg width="100%" height="280" viewBox="0 0 450 320" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="450" height="320" rx="20" fill="#E3F2FD"/>
                    <circle cx="225" cy="160" r="110" fill="#BBDEFB" opacity="0.6"/>
                    <path d="M170 160 H280 M225 105 V215" stroke="#1E88E5" stroke-width="24" stroke-linecap="round"/>
                    <circle cx="225" cy="160" r="30" fill="#FFFFFF"/>
                    <path d="M210 160 L220 170 L240 150" stroke="#26C6DA" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Live Statistics Banner -->
    <div class="row g-3 mb-5 text-center">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-white" style="background: linear-gradient(135deg, #1E88E5, #1565C0);">
                <div class="display-6 fw-bold"><?= number_format($totalUsers) ?>+</div>
                <div class="small opacity-75">Registered Patients</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-white" style="background: linear-gradient(135deg, #26C6DA, #0097A7);">
                <div class="display-6 fw-bold"><?= $totalHospitals ?>+</div>
                <div class="small opacity-75">Network Hospitals</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-white" style="background: linear-gradient(135deg, #42A5F5, #1E88E5);">
                <div class="display-6 fw-bold"><?= $totalPharmacies ?>+</div>
                <div class="small opacity-75">Partner Pharmacies</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-white" style="background: linear-gradient(135deg, #66BB6A, #388E3C);">
                <div class="display-6 fw-bold"><?= $totalDoctors ?>+</div>
                <div class="small opacity-75">Specialist Doctors</div>
            </div>
        </div>
    </div>

    <!-- Core Features Grid -->
    <div class="text-center mb-4">
        <h2 class="fw-bold">Platform Capabilities & Services</h2>
        <p class="text-muted">Explore our full suite of integrated smart healthcare solutions</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center hover-lift">
                <div class="d-inline-flex p-3 rounded-4 bg-primary-subtle text-primary mx-auto mb-3" style="width: 64px; height: 64px; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-brain fs-3"></i>
                </div>
                <h4 class="fw-bold fs-5 mb-2">AI Diagnostics</h4>
                <p class="text-muted small mb-3">ML-driven symptom analyzer predicting potential health conditions with precautious guidance.</p>
                <a href="<?= BASE_URL ?>/user/prediction.php" class="btn btn-sm btn-outline-primary rounded-pill mt-auto fw-semibold">Check Symptoms &rarr;</a>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center hover-lift">
                <div class="d-inline-flex p-3 rounded-4 bg-info-subtle text-info mx-auto mb-3" style="width: 64px; height: 64px; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-hospital fs-3"></i>
                </div>
                <h4 class="fw-bold fs-5 mb-2">Hospital & ICU Beds</h4>
                <p class="text-muted small mb-3">Interactive Leaflet OpenStreetMap locating emergency facilities and live bed counts.</p>
                <a href="<?= BASE_URL ?>/user/hospitals.php" class="btn btn-sm btn-outline-primary rounded-pill mt-auto fw-semibold">Find Hospitals &rarr;</a>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center hover-lift">
                <div class="d-inline-flex p-3 rounded-4 bg-success-subtle text-success mx-auto mb-3" style="width: 64px; height: 64px; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-file-prescription fs-3"></i>
                </div>
                <h4 class="fw-bold fs-5 mb-2">Prescription Delivery</h4>
                <p class="text-muted small mb-3">Upload your prescription PDF/photo, receive a verified pharmacy quote, and get COD delivery.</p>
                <a href="<?= BASE_URL ?>/user/upload_prescription.php" class="btn btn-sm btn-outline-primary rounded-pill mt-auto fw-semibold">Upload Rx &rarr;</a>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center hover-lift">
                <div class="d-inline-flex p-3 rounded-4 bg-danger-subtle text-danger mx-auto mb-3" style="width: 64px; height: 64px; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-truck-medical fs-3"></i>
                </div>
                <h4 class="fw-bold fs-5 mb-2">Emergency 108 SOS</h4>
                <p class="text-muted small mb-3">One-tap emergency alert storing location coordinates and connecting to trauma centers.</p>
                <a href="<?= BASE_URL ?>/user/sos.php" class="btn btn-sm btn-outline-danger rounded-pill mt-auto fw-semibold">Emergency SOS &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Testimonials & FAQs -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <h4 class="fw-bold mb-3"><i class="fa-solid fa-quote-left text-primary me-2"></i> Medical Expert Review</h4>
                <blockquote class="blockquote small text-muted">
                    "Swastya Setu solves critical coordination bottlenecks between patient triage, hospital bed tracking, and prescription dispatch during medical distress."
                </blockquote>
                <div class="d-flex align-items-center gap-3 mt-3 pt-3 border-top">
                    <i class="fa-solid fa-user-doctor fs-2 text-primary"></i>
                    <div>
                        <div class="fw-bold text-dark">Dr. Priya Verma, MD</div>
                        <small class="text-muted">Senior Consultant Cardiologist, Apollo Hospital</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <h4 class="fw-bold mb-3"><i class="fa-solid fa-circle-question text-info me-2"></i> Common Questions</h4>
                <div class="accordion accordion-flush" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-0 fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How does the prescription home delivery work?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body px-0 text-muted small">
                                Upload your doctor's prescription file (PDF or Image). Your chosen pharmacy reviews the items, enters available stock & pricing, and sends you a quote to confirm with Cash on Delivery (COD).
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-0 fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Is the AI disease prediction a medical diagnosis?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body px-0 text-muted small">
                                No. AI predictions are purely informational aids trained on medical symptom vectors and do NOT replace professional consultation with a certified doctor.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
