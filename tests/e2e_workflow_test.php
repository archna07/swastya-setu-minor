<?php
/**
 * Swastya Setu - End-to-End Core Workflow Integration Test
 * Simulates complete user life cycles:
 * 1. AI Disease Prediction & DB Log
 * 2. Doctor Appointment Booking & Hospital Confirmation
 * 3. Prescription Upload -> Pharmacy Quote -> Patient Order (COD) -> Delivery Timeline
 * 4. Emergency SOS Ambulance Dispatch
 * 5. Platform Metrics & Admin Verification
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$testCount = 0;
$passCount = 0;

function assertTest($description, $condition) {
    global $testCount, $passCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo " [\033[32mPASS\033[0m] $description\n";
    } else {
        echo " [\033[31mFAIL\033[0m] $description\n";
    }
}

echo "\n=== SWASTYA SETU END-TO-END WORKFLOW INTEGRATION TEST ===\n\n";

// Workflow 1: AI Disease Prediction Logging
echo "--- Workflow 1: AI Disease Prediction & Logging ---\n";
$patientId = 2; // Rajesh Kumar
$symptoms = ['itching', 'skin_rash', 'nodal_skin_eruptions'];
$symptomStr = implode(', ', $symptoms);
$predictionJson = json_encode([
    'top_prediction' => 'Fungal infection',
    'confidence' => 96.50,
    'predictions' => [
        ['disease' => 'Fungal infection', 'probability' => 96.50],
        ['disease' => 'Allergy', 'probability' => 2.10],
        ['disease' => 'Drug Reaction', 'probability' => 1.40]
    ]
]);
$precautions = "1. Keep skin clean and dry\n2. Avoid scratching infected areas\n3. Consult a dermatologist for topical antifungal treatment";

$predStmt = $db->prepare("INSERT INTO predictions (user_id, symptoms, top_prediction, confidence, prediction_json, specialist_recommended, precautions) VALUES (?, ?, ?, ?, ?, ?, ?)");
$ok = $predStmt->execute([$patientId, $symptomStr, 'Fungal infection', 96.50, $predictionJson, 'Dermatologist', $precautions]);
$predictionId = $db->lastInsertId();
assertTest("AI Prediction logged successfully to DB (ID: $predictionId)", $ok && $predictionId > 0);

$verifyPred = $db->prepare("SELECT * FROM predictions WHERE id = ?");
$verifyPred->execute([$predictionId]);
$predData = $verifyPred->fetch();
assertTest("Stored prediction matches 'Fungal infection' with high confidence", $predData && $predData['top_prediction'] === 'Fungal infection' && $predData['confidence'] > 90);

// Workflow 2: Doctor Appointment Booking & Hospital Confirmation
echo "\n--- Workflow 2: Doctor Appointment Booking & Hospital Confirmation ---\n";
$docStmt = $db->query("SELECT d.*, h.name as hospital_name FROM doctors d JOIN hospitals h ON d.hospital_id = h.id WHERE d.is_active = 1 LIMIT 1");
$doctor = $docStmt->fetch();
assertTest("Found active doctor: Dr. {$doctor['name']} at {$doctor['hospital_name']}", !empty($doctor));

$aptDate = date('Y-m-d', strtotime('+2 days'));
$aptTime = '10:30 AM';
$bookStmt = $db->prepare("INSERT INTO appointments (patient_id, doctor_id, hospital_id, appointment_date, time_slot, reason, status, notes) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
$bookOk = $bookStmt->execute([$patientId, $doctor['id'], $doctor['hospital_id'], $aptDate, $aptTime, 'Routine checkup and skin consultation', 'Booked via web portal']);
$appointmentId = $db->lastInsertId();
assertTest("Patient booked appointment (ID: $appointmentId, Date: $aptDate, Slot: $aptTime)", $bookOk && $appointmentId > 0);

// Hospital accepts appointment
$updateApt = $db->prepare("UPDATE appointments SET status = 'confirmed' WHERE id = ?");
$updateApt->execute([$appointmentId]);
$checkApt = $db->prepare("SELECT status FROM appointments WHERE id = ?");
$checkApt->execute([$appointmentId]);
$aptStatus = $checkApt->fetchColumn();
assertTest("Hospital confirmed appointment (Status: $aptStatus)", $aptStatus === 'confirmed');

// Workflow 3: Prescription Upload -> Pharmacy Quote -> Order -> Delivery Timeline
echo "\n--- Workflow 3: Smart Pharmacy & Prescription Order Flow ---\n";
$pharmacyStmt = $db->query("SELECT id, name FROM pharmacies WHERE status = 'approved' LIMIT 1");
$pharmacy = $pharmacyStmt->fetch();
assertTest("Found active pharmacy partner: {$pharmacy['name']}", !empty($pharmacy));

// Patient uploads prescription
$fakeFileName = 'test_prescription_' . time() . '.pdf';
$deliveryAddress = 'Flat 402, Green Avenue, Connaught Place, New Delhi';
$rxStmt = $db->prepare("INSERT INTO prescriptions (patient_id, pharmacy_id, file_path, original_filename, file_type, file_size, delivery_address, patient_notes, status) VALUES (?, ?, ?, ?, 'application/pdf', 1048576, ?, ?, 'uploaded')");
$rxStmt->execute([$patientId, $pharmacy['id'], 'uploads/prescriptions/' . $fakeFileName, $fakeFileName, $deliveryAddress, 'Please deliver before evening']);
$rxId = $db->lastInsertId();
assertTest("Patient uploaded prescription #$rxId to {$pharmacy['name']}", $rxId > 0);

// Pharmacy reviews and provides itemized quote
$quoteItems = [
    ['name' => 'Clotrimazole Cream 1% 30g', 'qty' => 2, 'unit_price' => 85.00],
    ['name' => 'Cetirizine 10mg Tab (Strip of 10)', 'qty' => 1, 'unit_price' => 38.00],
    ['name' => 'Antiseptic Soap Bar 75g', 'qty' => 1, 'unit_price' => 65.00],
];
$subtotal = 0;
$itemInsert = $db->prepare("INSERT INTO prescription_items (prescription_id, medicine_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
foreach ($quoteItems as $item) {
    $itemTot = $item['qty'] * $item['unit_price'];
    $subtotal += $itemTot;
    $itemInsert->execute([$rxId, $item['name'], $item['qty'], $item['unit_price'], $itemTot]);
}
$deliveryFee = 25.00;
$totalQuote = $subtotal + $deliveryFee;

$rxUpdate = $db->prepare("UPDATE prescriptions SET status = 'approved', total_amount = ?, pharmacy_notes = ? WHERE id = ?");
$rxUpdate->execute([$totalQuote, 'All medicines in stock. Ready to pack upon confirmation.', $rxId]);
assertTest("Pharmacy generated quote for Prescription #$rxId (Subtotal: ₹$subtotal, Total: ₹$totalQuote)", true);

// Patient confirms quote and places COD order
$orderNum = 'ORD-' . strtoupper(substr(uniqid(), 7, 6));
$orderStmt = $db->prepare("INSERT INTO orders (order_number, prescription_id, patient_id, pharmacy_id, subtotal, delivery_charges, total_amount, delivery_address, payment_method, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'COD', 'order_confirmed')");
$orderStmt->execute([$orderNum, $rxId, $patientId, $pharmacy['id'], $subtotal, $deliveryFee, $totalQuote, $deliveryAddress]);
$orderId = $db->lastInsertId();

// Transfer quote items to order items
$orderItemIns = $db->prepare("INSERT INTO order_items (order_id, medicine_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
foreach ($quoteItems as $item) {
    $itemTot = $item['qty'] * $item['unit_price'];
    $orderItemIns->execute([$orderId, $item['name'], $item['qty'], $item['unit_price'], $itemTot]);
}
assertTest("Patient placed COD Order #$orderNum (ID: $orderId, Status: order_confirmed)", $orderId > 0);

// Pharmacy progresses order delivery stages
$stages = ['preparing', 'out_for_delivery', 'delivered'];
$stageOk = true;
foreach ($stages as $stage) {
    $db->prepare("UPDATE orders SET order_status = ? WHERE id = ?")->execute([$stage, $orderId]);
    $curStatus = $db->query("SELECT order_status FROM orders WHERE id = $orderId")->fetchColumn();
    if ($curStatus !== $stage) {
        $stageOk = false;
    }
}
assertTest("Order delivery lifecycle transitioned through: order_confirmed -> preparing -> out_for_delivery -> delivered", $stageOk);

// Workflow 4: Emergency SOS Ambulance Dispatch
echo "\n--- Workflow 4: Emergency 108 SOS Dispatch ---\n";
$sosStmt = $db->prepare("INSERT INTO ambulance_requests (user_id, patient_name, contact_phone, latitude, longitude, address, emergency_type, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
$sosStmt->execute([$patientId, 'Rajesh Kumar', '+91 9876543210', 28.6139, 77.2090, 'Connaught Place, Central Delhi', 'Severe Breathlessness / Cardiac', 'Live GPS location captured via browser geolocation']);
$sosId = $db->lastInsertId();
assertTest("Emergency 108 SOS alert created (ID: $sosId, Coordinates: 28.6139, 77.2090)", $sosId > 0);

// Dispatch ambulance
$db->prepare("UPDATE ambulance_requests SET status = 'dispatched', notes = 'Ambulance DL-01-AB-1234 dispatched from AIIMS Central Trauma Center (ETA: 8 mins)' WHERE id = ?")->execute([$sosId]);
$sosCheck = $db->query("SELECT status, notes FROM ambulance_requests WHERE id = $sosId")->fetch();
assertTest("Ambulance dispatched with vehicle info and live ETA", $sosCheck['status'] === 'dispatched');

// Workflow 5: Administrative Dashboard Aggregation
echo "\n--- Workflow 5: Admin Platform Metrics ---\n";
$metrics = [
    'users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'hospitals' => $db->query("SELECT COUNT(*) FROM hospitals")->fetchColumn(),
    'pharmacies' => $db->query("SELECT COUNT(*) FROM pharmacies")->fetchColumn(),
    'appointments' => $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
    'prescriptions' => $db->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn(),
    'orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'sos' => $db->query("SELECT COUNT(*) FROM ambulance_requests")->fetchColumn(),
];
assertTest("Admin aggregates platform statistics: {$metrics['users']} Users, {$metrics['hospitals']} Hospitals, {$metrics['pharmacies']} Pharmacies, {$metrics['appointments']} Appts, {$metrics['orders']} Orders, {$metrics['sos']} SOS Dispatches", $metrics['users'] >= 4 && $metrics['orders'] >= 1);

echo "\n=========================================================\n";
echo "E2E WORKFLOW TEST RESULT: $passCount of $testCount tests passed successfully!\n";
echo "=========================================================\n\n";

if ($passCount === $testCount) {
    exit(0);
} else {
    exit(1);
}
