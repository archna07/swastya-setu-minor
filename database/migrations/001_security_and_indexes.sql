-- =========================================================================
-- Swastya Setu – Database Migration 001: Performance Indexes & Integrity
-- =========================================================================

-- Add composite indexes for high-frequency queries
ALTER TABLE `appointments` 
  ADD INDEX IF NOT EXISTS `idx_appt_patient_status` (`patient_id`, `status`),
  ADD INDEX IF NOT EXISTS `idx_appt_hospital_status` (`hospital_id`, `status`),
  ADD INDEX IF NOT EXISTS `idx_appt_date` (`appointment_date`);

ALTER TABLE `prescriptions` 
  ADD INDEX IF NOT EXISTS `idx_rx_patient_status` (`patient_id`, `status`),
  ADD INDEX IF NOT EXISTS `idx_rx_pharmacy_status` (`pharmacy_id`, `status`);

ALTER TABLE `orders` 
  ADD INDEX IF NOT EXISTS `idx_order_patient_status` (`patient_id`, `order_status`),
  ADD INDEX IF NOT EXISTS `idx_order_pharmacy_status` (`pharmacy_id`, `order_status`);

ALTER TABLE `predictions` 
  ADD INDEX IF NOT EXISTS `idx_pred_user_date` (`user_id`, `created_at`);

ALTER TABLE `ambulance_requests` 
  ADD INDEX IF NOT EXISTS `idx_sos_status` (`status`),
  ADD INDEX IF NOT EXISTS `idx_sos_user_status` (`user_id`, `status`);

ALTER TABLE `medicines` 
  ADD INDEX IF NOT EXISTS `idx_med_pharm_stock` (`pharmacy_id`, `stock_quantity`);

