-- =========================================================================
-- SWASTYA SETU – SMART HEALTHCARE ASSISTANCE PLATFORM
-- Database Schema & Sample Data
-- Database Name: swastya_setu
-- =========================================================================

CREATE DATABASE IF NOT EXISTS `swastya_setu` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `swastya_setu`;

-- 1. USERS TABLE
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `role` ENUM('patient', 'hospital', 'pharmacy', 'admin') NOT NULL DEFAULT 'patient',
  `status` ENUM('active', 'pending', 'rejected') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_role (`role`),
  INDEX idx_user_email (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USER PROFILES TABLE (Patient Medical & Personal Details)
CREATE TABLE IF NOT EXISTS `user_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `blood_group` VARCHAR(10) DEFAULT 'O+',
  `age` INT DEFAULT 30,
  `gender` ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
  `address` TEXT NULL,
  `city` VARCHAR(100) DEFAULT 'New Delhi',
  `emergency_contact_name` VARCHAR(150) DEFAULT 'Suman Kumar (Family)',
  `emergency_contact_phone` VARCHAR(20) DEFAULT '+91 9876543210',
  `medical_history` TEXT NULL,
  `allergies` TEXT NULL,
  `insurance_info` VARCHAR(255) DEFAULT 'Star Health Comprehensive (#SH-90812)',
  CONSTRAINT fk_user_profile FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. HOSPITALS TABLE
CREATE TABLE IF NOT EXISTS `hospitals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `name` VARCHAR(200) NOT NULL,
  `city` VARCHAR(100) NOT NULL DEFAULT 'New Delhi',
  `address` TEXT NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `hospital_type` ENUM('Govt', 'Private') NOT NULL DEFAULT 'Private',
  `emergency_services` TINYINT(1) NOT NULL DEFAULT 1,
  `icu_available` TINYINT(1) NOT NULL DEFAULT 1,
  `blood_bank` TINYINT(1) NOT NULL DEFAULT 1,
  `available_beds` INT NOT NULL DEFAULT 12,
  `total_beds` INT NOT NULL DEFAULT 50,
  `latitude` DECIMAL(10, 7) NOT NULL DEFAULT 28.6139,
  `longitude` DECIMAL(10, 7) NOT NULL DEFAULT 77.2090,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hosp_city (`city`),
  INDEX idx_hosp_type (`hospital_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. DOCTORS TABLE
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `hospital_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `specialization` VARCHAR(150) NOT NULL,
  `qualification` VARCHAR(150) NOT NULL,
  `experience_years` INT NOT NULL DEFAULT 8,
  `consultation_fee` DECIMAL(10, 2) NOT NULL DEFAULT 600.00,
  `available_days` VARCHAR(100) NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat',
  `available_time_slots` VARCHAR(255) NOT NULL DEFAULT '09:30 AM, 11:00 AM, 02:00 PM, 04:30 PM',
  `phone` VARCHAR(20) NULL,
  `email` VARCHAR(150) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_doctor_hosp FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. PHARMACIES TABLE
CREATE TABLE IF NOT EXISTS `pharmacies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `name` VARCHAR(200) NOT NULL,
  `owner_name` VARCHAR(150) NOT NULL,
  `license_number` VARCHAR(100) NOT NULL,
  `address` TEXT NOT NULL,
  `city` VARCHAR(100) NOT NULL DEFAULT 'New Delhi',
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `latitude` DECIMAL(10, 7) NOT NULL DEFAULT 28.6200,
  `longitude` DECIMAL(10, 7) NOT NULL DEFAULT 77.2150,
  `is_24x7` TINYINT(1) NOT NULL DEFAULT 1,
  `is_open` TINYINT(1) NOT NULL DEFAULT 1,
  `home_delivery` TINYINT(1) NOT NULL DEFAULT 1,
  `discount_info` VARCHAR(150) DEFAULT 'Up to 20% off on generics',
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pharm_city (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. MEDICINES TABLE (Inventory for each Pharmacy)
CREATE TABLE IF NOT EXISTS `medicines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pharmacy_id` INT NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `category` VARCHAR(100) NOT NULL DEFAULT 'General',
  `description` TEXT NULL,
  `price` DECIMAL(10, 2) NOT NULL DEFAULT 50.00,
  `stock_quantity` INT NOT NULL DEFAULT 100,
  `prescription_required` TINYINT(1) NOT NULL DEFAULT 0,
  `expiry_date` DATE NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_med_pharmacy FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. APPOINTMENTS TABLE
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `hospital_id` INT NOT NULL,
  `appointment_date` DATE NOT NULL,
  `time_slot` VARCHAR(50) NOT NULL,
  `reason` TEXT NULL,
  `status` ENUM('pending', 'confirmed', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_appt_patient FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_appt_doctor FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_appt_hospital FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. PREDICTIONS TABLE (AI ML Logs)
CREATE TABLE IF NOT EXISTS `predictions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `symptoms` TEXT NOT NULL,
  `top_prediction` VARCHAR(200) NOT NULL,
  `confidence` DECIMAL(5, 2) NOT NULL,
  `prediction_json` LONGTEXT NULL,
  `specialist_recommended` VARCHAR(150) NULL,
  `precautions` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pred_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. PRESCRIPTIONS TABLE (Uploads & Quotes)
CREATE TABLE IF NOT EXISTS `prescriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT NOT NULL,
  `pharmacy_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(50) NOT NULL,
  `file_size` INT NOT NULL,
  `delivery_address` TEXT NOT NULL,
  `patient_notes` TEXT NULL,
  `status` ENUM('uploaded', 'under_review', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'uploaded',
  `pharmacy_notes` TEXT NULL,
  `total_amount` DECIMAL(10, 2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_rx_patient FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_rx_pharmacy FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. PRESCRIPTION ITEMS TABLE (Medicines added by Pharmacist)
CREATE TABLE IF NOT EXISTS `prescription_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `prescription_id` INT NOT NULL,
  `medicine_name` VARCHAR(200) NOT NULL,
  `dosage` VARCHAR(100) DEFAULT 'As prescribed',
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_item_rx FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. ORDERS TABLE (Confirmed Medicine Deliveries)
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `prescription_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `pharmacy_id` INT NOT NULL,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `subtotal` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `delivery_charges` DECIMAL(10, 2) NOT NULL DEFAULT 25.00,
  `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `delivery_address` TEXT NOT NULL,
  `payment_method` ENUM('COD') NOT NULL DEFAULT 'COD',
  `order_status` ENUM('order_confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') NOT NULL DEFAULT 'order_confirmed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_rx FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_order_patient FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_order_pharmacy FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. ORDER ITEMS TABLE (Prescription itemized fulfillment)
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `medicine_name` VARCHAR(200) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_item_order FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. AMBULANCE REQUESTS (Emergency SOS Log)
CREATE TABLE IF NOT EXISTS `ambulance_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `patient_name` VARCHAR(150) NOT NULL,
  `contact_phone` VARCHAR(20) NOT NULL,
  `latitude` DECIMAL(10, 7) NOT NULL DEFAULT 28.6139,
  `longitude` DECIMAL(10, 7) NOT NULL DEFAULT 77.2090,
  `address` TEXT NOT NULL,
  `emergency_type` VARCHAR(100) DEFAULT 'Cardiac / Trauma Emergency',
  `status` ENUM('pending', 'dispatched', 'resolved', 'cancelled') NOT NULL DEFAULT 'pending',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sos_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- SAMPLE SEED DATA
-- Default Passwords:
-- Admin:    Admin@123
-- Patient:  Patient@123
-- Hospital: Hospital@123
-- Pharmacy: Pharmacy@123
-- =========================================================================

-- Seed Users
-- Password hash for 'Admin@123': $2y$10$wN2Lp6Q7Yx06X1k6Gk1Ghe7c4O1Zqf9Z1Yg4X5C7V9B0N1M2K3L4. (or dynamically generated by setup)
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `phone`, `role`, `status`) VALUES
(1, 'System Administrator', 'admin@swastyasetu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+91 9999999999', 'admin', 'active'),
(2, 'Rajesh Kumar', 'patient@swastyasetu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+91 9876543210', 'patient', 'active'),
(3, 'Apollo Hospital Admin', 'apollo@swastyasetu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+91 9811223344', 'hospital', 'active'),
(4, 'Apollo Pharmacy Owner', 'pharmacy@swastyasetu.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+91 9822334455', 'pharmacy', 'active')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- Seed Patient Profile
INSERT INTO `user_profiles` (`user_id`, `blood_group`, `age`, `gender`, `address`, `city`, `emergency_contact_name`, `emergency_contact_phone`, `medical_history`, `allergies`, `insurance_info`) VALUES
(2, 'B+', 34, 'Male', 'Flat 402, Green Avenue, Connaught Place', 'New Delhi', 'Suman Kumar (Spouse)', '+91 9876543211', 'Mild Hypertension (Under Medication)', 'Penicillin', 'Star Health Policy #SH-882910')
ON DUPLICATE KEY UPDATE `user_id` = VALUES(`user_id`);

-- Seed Hospitals
INSERT INTO `hospitals` (`id`, `user_id`, `name`, `city`, `address`, `phone`, `email`, `hospital_type`, `emergency_services`, `icu_available`, `blood_bank`, `available_beds`, `total_beds`, `latitude`, `longitude`, `status`) VALUES
(1, 3, 'Apollo Multi-Specialty Hospital', 'New Delhi', 'Sarita Vihar, Mathura Road, New Delhi 110076', '+91 11 2692 5858', 'apollo@swastyasetu.in', 'Private', 1, 1, 1, 14, 50, 28.5355, 77.2910, 'approved'),
(2, NULL, 'AIIMS Central Trauma Center', 'New Delhi', 'Ansari Nagar, New Delhi 110029', '+91 11 2658 8500', 'trauma@aiims.edu', 'Govt', 1, 1, 1, 4, 120, 28.5672, 77.2100, 'approved'),
(3, NULL, 'Fortis Memorial Research Institute', 'Gurugram', 'Sector 44, Opposite HUDA City Centre, Gurugram 122002', '+91 124 4962200', 'contact@fortishealthcare.com', 'Private', 1, 1, 1, 22, 60, 28.4595, 77.0725, 'approved'),
(4, NULL, 'Max Super Specialty Hospital', 'New Delhi', '1, 2, Press Enclave Road, Saket, New Delhi 110017', '+91 11 2651 5050', 'care@maxhealthcare.com', 'Private', 1, 1, 1, 8, 45, 28.5283, 77.2120, 'approved')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Doctors
INSERT INTO `doctors` (`id`, `hospital_id`, `name`, `specialization`, `qualification`, `experience_years`, `consultation_fee`, `available_days`, `available_time_slots`, `phone`, `email`, `is_active`) VALUES
(1, 1, 'Dr. Priya Verma', 'Cardiology', 'MBBS, MD, DM (Cardiology)', 14, 800.00, 'Mon,Tue,Wed,Thu,Fri', '10:00 AM, 11:30 AM, 02:00 PM, 04:30 PM', '+91 9811001122', 'priya.verma@apollo.com', 1),
(2, 1, 'Dr. Rajesh Sharma', 'Neurology', 'MBBS, MS, M.Ch (Neurology)', 18, 1200.00, 'Mon,Wed,Fri,Sat', '11:00 AM, 01:00 PM, 03:30 PM', '+91 9811002233', 'rajesh.sharma@apollo.com', 1),
(3, 1, 'Dr. Sunita Rao', 'Pediatrics', 'MBBS, DCH, DNB (Pediatrics)', 10, 600.00, 'Mon,Tue,Wed,Thu,Fri,Sat', '09:30 AM, 12:00 PM, 05:00 PM', '+91 9811003344', 'sunita.rao@apollo.com', 1),
(4, 2, 'Dr. Vikram Malhotra', 'Orthopedics', 'MBBS, MS (Orthopedics)', 16, 500.00, 'Tue,Thu,Sat', '10:30 AM, 01:30 PM, 03:00 PM', '+91 9811004455', 'vikram.m@aiims.edu', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Pharmacies
INSERT INTO `pharmacies` (`id`, `user_id`, `name`, `owner_name`, `license_number`, `address`, `city`, `phone`, `email`, `latitude`, `longitude`, `is_24x7`, `is_open`, `home_delivery`, `discount_info`, `status`) VALUES
(1, 4, 'Apollo 24x7 Express Pharmacy', 'Suresh Gupta', 'DL-PH-2024-9841', 'Shop 12, Main Market, Connaught Place, New Delhi', 'New Delhi', '+91 9822334455', 'pharmacy@swastyasetu.in', 28.6315, 77.2167, 1, 1, 1, 'Flat 20% off on all Generics', 'approved'),
(2, NULL, 'Tata 1mg Express Hub', 'Vikas Mehra', 'DL-PH-2024-8832', 'Plot 45, Green Park Main, New Delhi', 'New Delhi', '+91 11 4567 8900', 'greenpark@tata1mg.com', 28.5589, 77.2045, 0, 1, 1, '15% Discount on Prescriptions', 'approved'),
(3, NULL, 'MedPlus Chemist & Druggist', 'Ramesh Yadav', 'DL-PH-2023-7721', 'A-8, South Extension Part 1, New Delhi', 'New Delhi', '+91 11 2461 3322', 'southex@medplus.com', 28.5726, 77.2217, 1, 1, 1, '10% Cashback on COD', 'approved')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Medicines
INSERT INTO `medicines` (`id`, `pharmacy_id`, `name`, `category`, `description`, `price`, `stock_quantity`, `prescription_required`, `expiry_date`, `is_active`) VALUES
(1, 1, 'Amoxicillin 500mg', 'Antibiotic', 'Broad-spectrum antibiotic for bacterial infections', 120.00, 150, 1, '2027-12-31', 1),
(2, 1, 'Paracetamol 650mg (Dolo)', 'Antipyretic', 'Fever and pain relief tablets', 45.00, 300, 0, '2028-06-30', 1),
(3, 1, 'Azithromycin 500mg', 'Antibiotic', 'Respiratory and throat infection tablets', 140.00, 80, 1, '2027-08-31', 1),
(4, 1, 'Pantoprazole 40mg', 'Gastrointestinal', 'Acid reflux and stomach ulcer treatment', 95.00, 200, 0, '2028-01-31', 1),
(5, 1, 'Cetirizine 10mg', 'Antiallergic', 'Allergy, cold, and runny nose relief', 35.00, 250, 0, '2028-04-30', 1),
(6, 1, 'Metformin 500mg', 'Antidiabetic', 'Type 2 diabetes blood sugar management', 60.00, 180, 1, '2027-11-30', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Initial Notification
INSERT INTO `notifications` (`user_id`, `title`, `message`, `link`, `is_read`) VALUES
(2, 'Welcome to Swastya Setu', 'Your smart healthcare dashboard is active. You can now predict symptoms, book doctor visits, or order medicines.', 'user/dashboard.php', 0);
