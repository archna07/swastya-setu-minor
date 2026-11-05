<?php
/**
 * Swastya Setu - AI Disease Prediction & Symptom Checker
 * Production Re-Engineered with Unified Symptom Catalog & Medical Safety Escalation
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('patient');
$user = currentUser();

$predictionResult = null;
$error = '';
$serviceOffline = false;

// 1. Load Authoritative Centralized Symptoms Catalog
$catalogFile = __DIR__ . '/../config/symptoms_catalog.json';
if (!file_exists($catalogFile)) {
    $catalogFile = __DIR__ . '/../ai_service/symptoms_catalog.json';
}

$catalog = file_exists($catalogFile) ? json_decode(file_get_contents($catalogFile), true) : [];
$symptomList = $catalog['symptoms'] ?? [];
$emergencyIds = $catalog['emergency_symptom_ids'] ?? ['chest_pain', 'shortness_of_breath', 'stiff_neck', 'confusion'];

// Group symptoms by category for clean UI
$categorizedSymptoms = [];
$symptomMap = [];
foreach ($symptomList as $s) {
    $cat = $s['category'] ?? 'General';
    $categorizedSymptoms[$cat][] = $s;
    $symptomMap[$s['id']] = $s['label'];
}

// 2. Handle Prediction Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $selectedSymptoms = $_POST['symptoms'] ?? [];
        
        if (empty($selectedSymptoms) || !is_array($selectedSymptoms)) {
            $error = 'Please select at least one symptom to run the clinical decision-support analysis.';
        } else {
            // Sanitize selected symptom keys
            $validSelected = [];
            foreach ($selectedSymptoms as $symId) {
                $cleanId = sanitize($symId);
                if (isset($symptomMap[$cleanId])) {
                    $validSelected[] = $cleanId;
                }
            }

            if (empty($validSelected)) {
                $error = 'No recognized symptoms were selected. Please choose from the available options.';
            } else {
                // Call Flask AI Microservice
                $apiUrl = defined('AI_SERVICE_URL') ? AI_SERVICE_URL : 'http://127.0.0.1:5000';
                $ch = curl_init($apiUrl . '/predict');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['symptoms' => $validSelected]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
                    $predictionResult = json_decode($response, true);

                    // Save valid prediction to MySQL with prepared statements
                    if ($predictionResult && isset($predictionResult['primary_condition'])) {
                        try {
                            $db = getDB();
                            $sympLabels = array_map(function($id) use ($symptomMap) {
                                return $symptomMap[$id] ?? $id;
                            }, $validSelected);
                            $sympStr = implode(', ', $sympLabels);
                            
                            $precautionsStr = implode('; ', $predictionResult['precautions'] ?? []);

                            $ins = $db->prepare("
                                INSERT INTO predictions 
                                (user_id, symptoms, top_prediction, confidence, prediction_json, specialist_recommended, precautions) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $ins->execute([
                                $user['id'],
                                $sympStr,
                                $predictionResult['primary_condition'],
                                $predictionResult['confidence'],
                                json_encode($predictionResult),
                                $predictionResult['specialist'],
                                $precautionsStr
                            ]);

                            createNotification(
                                $user['id'],
                                'AI Diagnostics Report Generated',
                                "Analysis: {$predictionResult['primary_condition']} (Score: {$predictionResult['confidence']}%)",
                                'user/prediction.php'
                            );

                        } catch (Exception $e) {
                            // Log error silently without failing user request
                            error_log("Database error storing prediction: " . $e->getMessage());
                        }
                    }
                } else {
                    // Honest service failure handling — NEVER fabricate a fake disease prediction!
                    $serviceOffline = true;
                    $error = 'The AI Diagnostic Microservice is currently undergoing scheduled maintenance or is unreachable. '
                           . 'Please consult our specialist doctors directly using the appointment booking service below.';
                }
            }
        }
    }
}

// 3. Fetch Patient's Previous Prediction History
$previousPredictions = [];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM predictions WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    $stmt->execute([$user['id']]);
    $previousPredictions = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching prediction history: " . $e->getMessage());
}

$pageTitle = 'AI Health Assessment & Symptom Analysis';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid p-0">
            
            <?= displayFlash() ?>

            <!-- Page Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fa-solid fa-wand-magic-sparkles text-primary me-2"></i> Clinical Decision Support & Symptom Analyzer</h2>
                    <p class="text-muted small mb-0">Select your active symptoms below for machine-learning pattern analysis and recommended medical specialists.</p>
                </div>
                <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill small">
                    <i class="fa-solid fa-microchip me-1"></i> Multi-Tree Random Forest Model v2.1
                </div>
            </div>

            <!-- Clinical Disclaimer Alert Banner -->
            <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4 d-flex align-items-start gap-3">
                <i class="fa-solid fa-triangle-exclamation text-warning fs-3 mt-1 flex-shrink-0"></i>
                <div class="small">
                    <strong>CLINICAL DECISION-SUPPORT PROTOTYPE NOTICE:</strong>
                    This automated symptom checker is an educational decision-support demonstration tool. It does <strong>NOT</strong> provide a definitive medical diagnosis, nor does it recommend prescription drugs or dosages. If you are experiencing acute distress, please visit an emergency department or dial <strong>108</strong> immediately.
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Emergency Red-Flag Banner (If Triggered) -->
            <?php if ($predictionResult && !empty($predictionResult['emergency_alert'])): ?>
                <div class="alert alert-danger border-0 shadow-lg rounded-4 mb-4 p-4 text-white" style="background: linear-gradient(135deg, #c62828, #b71c1c);">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <i class="fa-solid fa-truck-medical fs-1"></i>
                        <div>
                            <h4 class="fw-bold mb-0 text-white">POTENTIAL EMERGENCY RED-FLAG SYMPTOM DETECTED</h4>
                            <p class="mb-0 opacity-90 small">You reported critical symptoms requiring immediate medical evaluation.</p>
                        </div>
                    </div>
                    <p class="mt-3 mb-3 bg-white bg-opacity-10 p-3 rounded-3 small">
                        <?= htmlspecialchars($predictionResult['emergency_guidance']) ?>
                    </p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="sos.php" class="btn btn-warning text-dark fw-bold rounded-pill px-4">
                            <i class="fa-solid fa-phone-volume me-2"></i> Trigger 108 Emergency SOS
                        </a>
                        <a href="hospitals.php" class="btn btn-outline-light rounded-pill px-4">
                            <i class="fa-solid fa-hospital me-2"></i> View Nearest Emergency Trauma Centers
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Prediction Results Card -->
            <?php if ($predictionResult && isset($predictionResult['primary_condition'])): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" id="resultsCard">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                        <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold">
                            <i class="fa-solid fa-circle-check me-1"></i> Analysis Complete
                        </span>
                        <small class="text-muted">Analyzed <?= (int)($predictionResult['matched_symptoms_count'] ?? 1) ?> symptoms</small>
                    </div>

                    <div class="card-body p-4">
                        <div class="row align-items-center g-4">
                            <div class="col-lg-8">
                                <span class="text-muted small text-uppercase fw-bold tracking-wide">Primary Condition Pattern</span>
                                <h2 class="display-6 fw-bold text-dark mb-2">
                                    <?= htmlspecialchars($predictionResult['primary_condition']) ?>
                                </h2>
                                <p class="text-muted mb-3">
                                    <?= htmlspecialchars($predictionResult['description']) ?>
                                </p>
                                
                                <div class="p-3 bg-light rounded-3 mb-3 small">
                                    <i class="fa-solid fa-circle-info text-primary me-2"></i>
                                    <strong>Score Interpretation:</strong> <?= htmlspecialchars($predictionResult['score_explanation']) ?>
                                </div>

                                <div class="d-flex flex-wrap gap-4 align-items-center pt-2">
                                    <div>
                                        <span class="text-muted small d-block">Recommended Specialist:</span>
                                        <span class="fw-bold text-primary fs-6">
                                            <i class="fa-solid fa-user-doctor me-1"></i> <?= htmlspecialchars($predictionResult['specialist']) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <a href="appointments.php" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                                            <i class="fa-solid fa-calendar-plus me-1"></i> Book Doctor Consultation
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4 text-center border-start-lg ps-lg-4">
                                <div class="p-4 bg-primary-subtle rounded-4 d-inline-block w-100">
                                    <span class="text-muted small fw-bold d-block mb-1">Pattern Affinity Score</span>
                                    <div class="display-4 fw-bold text-primary mb-2">
                                        <?= htmlspecialchars($predictionResult['confidence']) ?>%
                                    </div>
                                    <div class="progress rounded-pill" style="height: 10px;">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= min(100, max(5, (float)$predictionResult['confidence'])) ?>%;"></div>
                                    </div>
                                    <small class="text-muted mt-2 d-block">Decision tree alignment</small>
                                </div>
                            </div>
                        </div>

                        <!-- General Precautions & Self-Care Guidance -->
                        <?php if (!empty($predictionResult['precautions'])): ?>
                            <div class="mt-4 pt-3 border-top">
                                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-shield-heart text-success me-2"></i> Recommended General Precautions & Lifestyle Guidance:</h6>
                                <div class="row g-2">
                                    <?php foreach ($predictionResult['precautions'] as $precaution): ?>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-start gap-2 bg-light p-3 rounded-3 small">
                                                <i class="fa-solid fa-check text-success mt-1"></i>
                                                <span><?= htmlspecialchars($precaution) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Differential Diagnosis Candidates -->
                        <?php if (!empty($predictionResult['top_predictions']) && count($predictionResult['top_predictions']) > 1): ?>
                            <div class="mt-4 pt-3 border-top">
                                <h6 class="fw-bold text-muted small text-uppercase mb-3">Differential Diagnosis Considerations:</h6>
                                <div class="row g-2">
                                    <?php foreach (array_slice($predictionResult['top_predictions'], 1) as $alt): ?>
                                        <div class="col-md-6">
                                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="fw-bold text-dark d-block"><?= htmlspecialchars($alt['condition']) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($alt['specialist']) ?></small>
                                                </div>
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 fw-bold">
                                                    <?= htmlspecialchars($alt['probability']) ?>% affinity
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Symptom Selector Form -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="POST" action="prediction.php" id="predictionForm">
                        <?= csrfField() ?>

                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                            <div>
                                <h5 class="fw-bold mb-1">Select Your Symptoms</h5>
                                <p class="text-muted small mb-0">Click to select all symptoms currently experienced. Search or browse by category.</p>
                            </div>
                            <div class="w-100 w-md-auto" style="max-width: 320px;">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                    <input type="text" id="symptomSearch" class="form-control bg-light border-start-0" placeholder="Filter symptoms...">
                                </div>
                            </div>
                        </div>

                        <!-- Categorized Symptom Selection Chips -->
                        <div class="symptom-category-container">
                            <?php foreach ($categorizedSymptoms as $category => $symptoms): ?>
                                <div class="category-block mb-3">
                                    <h6 class="text-muted small fw-bold text-uppercase border-bottom pb-1 mb-2">
                                        <?= htmlspecialchars($category) ?>
                                    </h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($symptoms as $sym): ?>
                                            <?php 
                                            $isEmergency = !empty($sym['is_emergency']);
                                            $chipClass = $isEmergency ? 'border-danger-subtle text-danger' : 'border-secondary-subtle text-dark';
                                            ?>
                                            <label class="symptom-chip-label cursor-pointer mb-0">
                                                <input type="checkbox" name="symptoms[]" value="<?= htmlspecialchars($sym['id']) ?>" class="symptom-checkbox visually-hidden" id="sym_<?= htmlspecialchars($sym['id']) ?>">
                                                <span class="badge p-2 px-3 rounded-pill border bg-white <?= $chipClass ?> symptom-chip transition-all">
                                                    <?php if ($isEmergency): ?>
                                                        <i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-plus me-1 chip-icon"></i>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($sym['label']) ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <span class="small text-muted" id="selectedCountText">0 symptoms selected</span>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-3 btn-sm" onclick="clearSelectedSymptoms()">
                                    Clear All
                                </button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" id="analyzeBtn">
                                    <i class="fa-solid fa-wand-magic-sparkles me-2"></i> Analyze Symptoms
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Patient Previous Predictions History -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1"><i class="fa-solid fa-clock-rotate-left text-muted me-2"></i> Your Recent Health Assessment History</h5>
                    <p class="text-muted small mb-0">Past symptom checks logged securely for doctor consultation review.</p>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($previousPredictions)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-heart-pulse fs-2 opacity-50 mb-2"></i>
                            <p class="small mb-0">No past assessments found. Run your first symptom analysis above.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th>Date</th>
                                        <th>Reported Symptoms</th>
                                        <th>Top Condition</th>
                                        <th>Pattern Score</th>
                                        <th>Specialist Consulted</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <?php foreach ($previousPredictions as $row): ?>
                                        <tr>
                                            <td class="text-nowrap"><?= formatDate($row['created_at'], true) ?></td>
                                            <td>
                                                <span class="text-truncate d-inline-block" style="max-width: 250px;" title="<?= htmlspecialchars($row['symptoms']) ?>">
                                                    <?= htmlspecialchars($row['symptoms']) ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($row['top_prediction']) ?></td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1">
                                                    <?= htmlspecialchars($row['confidence']) ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?= htmlspecialchars($row['specialist_recommended'] ?? 'General Physician') ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.transition-all { transition: all 0.2s ease-in-out; }
.symptom-chip { font-size: 0.85rem; user-select: none; }
.symptom-checkbox:checked + .symptom-chip {
    background-color: #1E88E5 !important;
    color: white !important;
    border-color: #1E88E5 !important;
}
.symptom-checkbox:checked + .symptom-chip i.chip-icon {
    transform: rotate(45deg);
}
@media (min-width: 992px) {
    .border-start-lg {
        border-left: 1px solid #dee2e6 !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.symptom-checkbox');
    const countText = document.getElementById('selectedCountText');
    const searchInput = document.getElementById('symptomSearch');

    function updateCount() {
        const count = document.querySelectorAll('.symptom-checkbox:checked').length;
        countText.innerText = count + ' symptoms selected';
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateCount);
    });

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const chips = document.querySelectorAll('.symptom-chip-label');
        chips.forEach(chip => {
            const text = chip.innerText.toLowerCase();
            chip.style.display = text.includes(query) ? 'inline-block' : 'none';
        });
    });

    window.clearSelectedSymptoms = function() {
        checkboxes.forEach(cb => cb.checked = false);
        updateCount();
    };

    // Scroll to results if present
    const resCard = document.getElementById('resultsCard');
    if (resCard) {
        resCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
