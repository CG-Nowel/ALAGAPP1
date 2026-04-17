-- ============================================================
-- Pediatric Clinic Management System - Seed Data
-- ============================================================

USE `pediatric_clinic`;

-- Default Admin User (password: Admin@123)
INSERT INTO `users` (`first_name`, `last_name`, `email`, `phone`, `password`, `user_type`, `status`, `email_verified_at`)
VALUES ('System', 'Administrator', 'admin@pedicare.com', '9171234567',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'ADMIN', 'active', NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Sample Doctors (password: Doctor@123)
INSERT INTO `users` (`first_name`, `last_name`, `email`, `phone`, `password`, `user_type`, `status`, `specialization`, `license_number`, `years_of_experience`, `email_verified_at`)
VALUES
    ('Maria', 'Santos', 'dr.santos@pedicare.com', '9171111111',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
     'DOCTOR', 'active', 'General Pediatrics', 'PRC-2024-001', 15, NOW()),
    ('Juan', 'Dela Cruz', 'dr.delacruz@pedicare.com', '9172222222',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
     'DOCTOR', 'active', 'Pediatric Cardiology', 'PRC-2024-002', 10, NOW()),
    ('Ana', 'Reyes', 'dr.reyes@pedicare.com', '9173333333',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
     'DOCTOR', 'active', 'Pediatric Neurology', 'PRC-2024-003', 8, NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Default Services
INSERT INTO `services` (`name`, `description`, `duration`, `cost`) VALUES
    ('General Consultation', 'Standard pediatric consultation', 30, 500.00),
    ('Vaccination', 'Routine immunization administration', 15, 300.00),
    ('Well-Baby Checkup', 'Comprehensive developmental assessment', 45, 800.00),
    ('Follow-up Visit', 'Follow-up on previous consultation', 20, 400.00),
    ('Emergency Consultation', 'Urgent pediatric care', 60, 1500.00),
    ('Growth Assessment', 'Height, weight, and developmental monitoring', 30, 600.00)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Default Clinic Settings
INSERT INTO `clinic_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
    ('clinic_name', 'PediCare Clinic', 'STRING', 'Name of the clinic'),
    ('clinic_phone', '+63 917 123 4567', 'STRING', 'Clinic contact number'),
    ('clinic_email', 'info@pedicare.com', 'STRING', 'Clinic email address'),
    ('clinic_address', '123 Health St, Medical City, Metro Manila', 'STRING', 'Clinic physical address'),
    ('business_hours', '{"monday":{"open":"08:00","close":"17:00"},"tuesday":{"open":"08:00","close":"17:00"},"wednesday":{"open":"08:00","close":"17:00"},"thursday":{"open":"08:00","close":"17:00"},"friday":{"open":"08:00","close":"17:00"},"saturday":{"open":"09:00","close":"13:00"},"sunday":null}', 'JSON', 'Weekly business hours'),
    ('appointment_slot_duration', '30', 'INTEGER', 'Default appointment slot in minutes'),
    ('max_advance_booking_days', '60', 'INTEGER', 'Maximum days in advance for booking'),
    ('cancellation_hours', '24', 'INTEGER', 'Minimum hours before appointment for free cancellation'),
    ('smtp_host', '', 'STRING', 'SMTP server hostname'),
    ('smtp_port', '587', 'INTEGER', 'SMTP server port'),
    ('smtp_username', '', 'STRING', 'SMTP username'),
    ('smtp_password', '', 'STRING', 'SMTP password'),
    ('sms_provider', '', 'STRING', 'SMS gateway provider'),
    ('sms_api_key', '', 'STRING', 'SMS gateway API key')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Default Vaccines
INSERT INTO `vaccines` (`name`, `description`, `manufacturer`, `vaccine_type`, `total_doses`, `dose_interval_days`, `min_age_months`, `max_age_months`) VALUES
    ('BCG', 'Bacillus Calmette-Guerin - Tuberculosis vaccine', 'Various', 'ROUTINE', 1, NULL, 0, 12),
    ('Hepatitis B', 'Hepatitis B vaccine', 'Various', 'ROUTINE', 3, 30, 0, 6),
    ('DTaP', 'Diphtheria, Tetanus, Pertussis', 'Various', 'ROUTINE', 5, 60, 2, 72),
    ('IPV', 'Inactivated Polio Vaccine', 'Various', 'ROUTINE', 4, 60, 2, 72),
    ('Hib', 'Haemophilus influenzae type b', 'Various', 'ROUTINE', 4, 60, 2, 15),
    ('PCV13', 'Pneumococcal Conjugate Vaccine', 'Pfizer', 'ROUTINE', 4, 60, 2, 15),
    ('Rotavirus', 'Rotavirus vaccine', 'Various', 'ROUTINE', 3, 30, 2, 8),
    ('MMR', 'Measles, Mumps, Rubella', 'Various', 'ROUTINE', 2, 90, 12, 72),
    ('Varicella', 'Chickenpox vaccine', 'Various', 'ROUTINE', 2, 90, 12, 72),
    ('Hepatitis A', 'Hepatitis A vaccine', 'Various', 'ROUTINE', 2, 180, 12, 24),
    ('Influenza', 'Seasonal Flu vaccine', 'Various', 'OPTIONAL', 1, NULL, 6, 216),
    ('HPV', 'Human Papillomavirus vaccine', 'Various', 'OPTIONAL', 3, 60, 108, 156)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Default Vaccine Schedule
INSERT INTO `vaccine_schedule` (`vaccine_id`, `dose_number`, `recommended_age_months`, `description`, `is_mandatory`) VALUES
    (1, 1, 0, 'BCG at birth', 1),
    (2, 1, 0, 'Hepatitis B - 1st dose at birth', 1),
    (2, 2, 1, 'Hepatitis B - 2nd dose at 1 month', 1),
    (2, 3, 6, 'Hepatitis B - 3rd dose at 6 months', 1),
    (3, 1, 2, 'DTaP - 1st dose at 2 months', 1),
    (3, 2, 4, 'DTaP - 2nd dose at 4 months', 1),
    (3, 3, 6, 'DTaP - 3rd dose at 6 months', 1),
    (3, 4, 18, 'DTaP - 4th dose at 18 months', 1),
    (3, 5, 48, 'DTaP - 5th dose at 4 years', 1),
    (4, 1, 2, 'IPV - 1st dose at 2 months', 1),
    (4, 2, 4, 'IPV - 2nd dose at 4 months', 1),
    (4, 3, 6, 'IPV - 3rd dose at 6 months', 1),
    (4, 4, 48, 'IPV - 4th dose at 4 years', 1),
    (8, 1, 12, 'MMR - 1st dose at 12 months', 1),
    (8, 2, 48, 'MMR - 2nd dose at 4 years', 1),
    (9, 1, 12, 'Varicella - 1st dose at 12 months', 1),
    (9, 2, 48, 'Varicella - 2nd dose at 4 years', 1)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Prescription sequence for current year
INSERT INTO `prescription_sequences` (`year`, `last_number`) VALUES (YEAR(NOW()), 0)
ON DUPLICATE KEY UPDATE `id` = `id`;
