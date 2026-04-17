-- ============================================================
-- Pediatric Clinic Management System - Complete Database Schema
-- ============================================================
-- Run this file on a fresh MySQL install to create all tables.
-- Database: pediatric_clinic
-- ============================================================

CREATE DATABASE IF NOT EXISTS `pediatric_clinic`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `pediatric_clinic`;

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `user_type` ENUM('PARENT','DOCTOR','DOCTOR_OWNER','ADMIN') NOT NULL DEFAULT 'PARENT',
    `status` ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'active',
    `date_of_birth` DATE DEFAULT NULL,
    `gender` ENUM('MALE','FEMALE','OTHER') DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `emergency_contact_name` VARCHAR(100) DEFAULT NULL,
    `emergency_contact_phone` VARCHAR(20) DEFAULT NULL,
    `profile_picture` VARCHAR(255) DEFAULT NULL,
    -- Doctor-specific fields
    `specialization` VARCHAR(100) DEFAULT NULL,
    `license_number` VARCHAR(50) DEFAULT NULL,
    `years_of_experience` INT UNSIGNED DEFAULT NULL,
    -- Email verification
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `email_verification_token` VARCHAR(64) DEFAULT NULL,
    -- Password reset
    `password_reset_token` VARCHAR(64) DEFAULT NULL,
    `password_reset_expires` TIMESTAMP NULL DEFAULT NULL,
    -- Two-factor authentication
    `two_factor_secret` VARCHAR(255) DEFAULT NULL,
    `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    -- Login security
    `login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` TIMESTAMP NULL DEFAULT NULL,
    `force_password_change` TINYINT(1) NOT NULL DEFAULT 0,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    `last_login_ip` VARCHAR(45) DEFAULT NULL,
    -- Timestamps
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`),
    INDEX `idx_users_type` (`user_type`),
    INDEX `idx_users_status` (`status`),
    INDEX `idx_users_email_verified` (`email_verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. PATIENTS (Children)
-- ============================================================
CREATE TABLE IF NOT EXISTS `patients` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `gender` ENUM('MALE','FEMALE','OTHER') NOT NULL,
    `blood_type` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
    `height` DECIMAL(5,2) DEFAULT NULL COMMENT 'in cm',
    `weight` DECIMAL(5,2) DEFAULT NULL COMMENT 'in kg',
    `allergies` TEXT DEFAULT NULL,
    `medical_conditions` TEXT DEFAULT NULL,
    `special_notes` TEXT DEFAULT NULL,
    `profile_picture` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_patients_parent` (`parent_id`),
    INDEX `idx_patients_dob` (`date_of_birth`),
    CONSTRAINT `fk_patients_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. APPOINTMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `appointments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id` INT UNSIGNED NOT NULL,
    `doctor_id` INT UNSIGNED NOT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `end_time` TIME DEFAULT NULL,
    `type` ENUM('CONSULTATION','VACCINATION','CHECKUP','FOLLOW_UP','EMERGENCY','OTHER') NOT NULL DEFAULT 'CONSULTATION',
    `status` ENUM('SCHEDULED','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED','NO_SHOW','WAITLISTED') NOT NULL DEFAULT 'SCHEDULED',
    `reason` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `duration` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'in minutes',
    `cancellation_reason` TEXT DEFAULT NULL,
    `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_appointments_doctor_date` (`doctor_id`, `appointment_date`),
    INDEX `idx_appointments_patient` (`patient_id`),
    INDEX `idx_appointments_date` (`appointment_date`),
    INDEX `idx_appointments_status` (`status`),
    CONSTRAINT `fk_appointments_patient` FOREIGN KEY (`patient_id`)
        REFERENCES `patients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_appointments_doctor` FOREIGN KEY (`doctor_id`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_appointments_creator` FOREIGN KEY (`created_by`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. PRESCRIPTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `prescriptions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `prescription_number` VARCHAR(30) NOT NULL,
    `patient_id` INT UNSIGNED NOT NULL,
    `doctor_id` INT UNSIGNED NOT NULL,
    `appointment_id` INT UNSIGNED DEFAULT NULL,
    `prescription_date` DATE NOT NULL,
    `diagnosis` TEXT DEFAULT NULL,
    `medications` JSON NOT NULL COMMENT 'Array of {name, dosage, frequency, duration, instructions}',
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_prescription_number` (`prescription_number`),
    INDEX `idx_prescriptions_patient` (`patient_id`),
    INDEX `idx_prescriptions_doctor` (`doctor_id`),
    INDEX `idx_prescriptions_date` (`prescription_date`),
    CONSTRAINT `fk_prescriptions_patient` FOREIGN KEY (`patient_id`)
        REFERENCES `patients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_prescriptions_doctor` FOREIGN KEY (`doctor_id`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_prescriptions_appointment` FOREIGN KEY (`appointment_id`)
        REFERENCES `appointments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. CONSULTATION NOTES
-- ============================================================
CREATE TABLE IF NOT EXISTS `consultation_notes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id` INT UNSIGNED NOT NULL,
    `doctor_id` INT UNSIGNED NOT NULL,
    `appointment_id` INT UNSIGNED DEFAULT NULL,
    `consultation_date` DATE NOT NULL,
    `chief_complaint` TEXT DEFAULT NULL,
    `symptoms` TEXT DEFAULT NULL,
    `diagnosis` TEXT DEFAULT NULL,
    `treatment_plan` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    -- Vital signs
    `temperature` DECIMAL(4,1) DEFAULT NULL,
    `blood_pressure` VARCHAR(20) DEFAULT NULL,
    `heart_rate` INT UNSIGNED DEFAULT NULL,
    `respiratory_rate` INT UNSIGNED DEFAULT NULL,
    `height` DECIMAL(5,2) DEFAULT NULL,
    `weight` DECIMAL(5,2) DEFAULT NULL,
    `follow_up_date` DATE DEFAULT NULL,
    `is_visible_to_parent` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_consultation_patient` (`patient_id`),
    INDEX `idx_consultation_doctor` (`doctor_id`),
    INDEX `idx_consultation_date` (`consultation_date`),
    CONSTRAINT `fk_consultation_patient` FOREIGN KEY (`patient_id`)
        REFERENCES `patients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_consultation_doctor` FOREIGN KEY (`doctor_id`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_consultation_appointment` FOREIGN KEY (`appointment_id`)
        REFERENCES `appointments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. PATIENT FILES (Uploads)
-- ============================================================
CREATE TABLE IF NOT EXISTS `patient_files` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id` INT UNSIGNED NOT NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL COMMENT 'in bytes',
    `file_category` ENUM('LAB_RESULT','XRAY','PRESCRIPTION','REFERRAL','OTHER') NOT NULL DEFAULT 'OTHER',
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_files_patient` (`patient_id`),
    INDEX `idx_files_uploader` (`uploaded_by`),
    CONSTRAINT `fk_files_patient` FOREIGN KEY (`patient_id`)
        REFERENCES `patients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_files_uploader` FOREIGN KEY (`uploaded_by`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. VACCINES (Master List)
-- ============================================================
CREATE TABLE IF NOT EXISTS `vaccines` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `manufacturer` VARCHAR(100) DEFAULT NULL,
    `vaccine_type` ENUM('ROUTINE','OPTIONAL','SPECIAL') NOT NULL DEFAULT 'ROUTINE',
    `total_doses` INT UNSIGNED NOT NULL DEFAULT 1,
    `dose_interval_days` INT UNSIGNED DEFAULT NULL COMMENT 'Days between doses',
    `min_age_months` INT UNSIGNED DEFAULT NULL,
    `max_age_months` INT UNSIGNED DEFAULT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_vaccines_type` (`vaccine_type`),
    INDEX `idx_vaccines_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. VACCINE SCHEDULE (Recommended Schedule)
-- ============================================================
CREATE TABLE IF NOT EXISTS `vaccine_schedule` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vaccine_id` INT UNSIGNED NOT NULL,
    `dose_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `recommended_age_months` INT UNSIGNED NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_schedule_vaccine` (`vaccine_id`),
    INDEX `idx_schedule_age` (`recommended_age_months`),
    CONSTRAINT `fk_schedule_vaccine` FOREIGN KEY (`vaccine_id`)
        REFERENCES `vaccines`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. VACCINATION RECORDS
-- ============================================================
CREATE TABLE IF NOT EXISTS `vaccination_records` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id` INT UNSIGNED NOT NULL,
    `vaccine_id` INT UNSIGNED DEFAULT NULL,
    `vaccine_name` VARCHAR(100) NOT NULL,
    `vaccine_type` ENUM('ROUTINE','OPTIONAL','SPECIAL') NOT NULL DEFAULT 'ROUTINE',
    `dose_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `total_doses` INT UNSIGNED NOT NULL DEFAULT 1,
    `administration_date` DATE NOT NULL,
    `next_due_date` DATE DEFAULT NULL,
    `administered_by` INT UNSIGNED NOT NULL,
    `lot_number` VARCHAR(50) DEFAULT NULL,
    `manufacturer` VARCHAR(100) DEFAULT NULL,
    `site` ENUM('LEFT_ARM','RIGHT_ARM','LEFT_THIGH','RIGHT_THIGH','ORAL') NOT NULL DEFAULT 'LEFT_ARM',
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('COMPLETED','SCHEDULED','MISSED','OVERDUE') NOT NULL DEFAULT 'COMPLETED',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_vaccination_patient` (`patient_id`),
    INDEX `idx_vaccination_vaccine` (`vaccine_id`),
    INDEX `idx_vaccination_date` (`administration_date`),
    INDEX `idx_vaccination_status` (`status`),
    CONSTRAINT `fk_vaccination_patient` FOREIGN KEY (`patient_id`)
        REFERENCES `patients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_vaccination_vaccine` FOREIGN KEY (`vaccine_id`)
        REFERENCES `vaccines`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_vaccination_admin` FOREIGN KEY (`administered_by`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. PATIENT VACCINE NEEDS
-- ============================================================
CREATE TABLE IF NOT EXISTS `patient_vaccine_needs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id` INT UNSIGNED NOT NULL,
    `vaccine_id` INT UNSIGNED DEFAULT NULL,
    `vaccine_name` VARCHAR(100) NOT NULL,
    `dose_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `recommended_date` DATE DEFAULT NULL,
    `status` ENUM('PENDING','SCHEDULED','COMPLETED','MISSED','SKIPPED') NOT NULL DEFAULT 'PENDING',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_vaccine_needs_patient` (`patient_id`),
    INDEX `idx_vaccine_needs_status` (`status`),
    CONSTRAINT `fk_vaccine_needs_patient` FOREIGN KEY (`patient_id`)
        REFERENCES `patients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_vaccine_needs_vaccine` FOREIGN KEY (`vaccine_id`)
        REFERENCES `vaccines`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. DOCTOR AVAILABILITY
-- ============================================================
CREATE TABLE IF NOT EXISTS `doctor_availability` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `doctor_id` INT UNSIGNED NOT NULL,
    `day_of_week` ENUM('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY') DEFAULT NULL,
    `specific_date` DATE DEFAULT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `slot_duration` INT UNSIGNED NOT NULL DEFAULT 30,
    `max_patients` INT UNSIGNED NOT NULL DEFAULT 10,
    `availability_type` ENUM('RECURRING','AVAILABLE','UNAVAILABLE') NOT NULL DEFAULT 'RECURRING',
    `reason` VARCHAR(255) DEFAULT NULL,
    `is_all_day` TINYINT(1) NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_availability_doctor` (`doctor_id`),
    INDEX `idx_availability_date` (`specific_date`),
    INDEX `idx_availability_day` (`day_of_week`),
    CONSTRAINT `fk_availability_doctor` FOREIGN KEY (`doctor_id`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('APPOINTMENT','VACCINATION','SYSTEM','REMINDER','ALERT') NOT NULL DEFAULT 'SYSTEM',
    `channel` ENUM('IN_APP','EMAIL','SMS','ALL') NOT NULL DEFAULT 'IN_APP',
    `related_type` VARCHAR(50) DEFAULT NULL COMMENT 'e.g. appointment, prescription',
    `related_id` INT UNSIGNED DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notifications_user` (`user_id`),
    INDEX `idx_notifications_read` (`user_id`, `is_read`),
    INDEX `idx_notifications_type` (`type`),
    INDEX `idx_notifications_created` (`created_at`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. ACTIVITY LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'e.g. user, patient, appointment',
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_activity_user` (`user_id`),
    INDEX `idx_activity_action` (`action`),
    INDEX `idx_activity_timestamp` (`created_at`),
    INDEX `idx_activity_entity` (`entity_type`, `entity_id`),
    CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. CLINIC SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `clinic_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` ENUM('STRING','INTEGER','BOOLEAN','JSON') NOT NULL DEFAULT 'STRING',
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`),
    CONSTRAINT `fk_settings_updater` FOREIGN KEY (`updated_by`)
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. SERVICES
-- ============================================================
CREATE TABLE IF NOT EXISTS `services` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `duration` INT UNSIGNED NOT NULL DEFAULT 30,
    `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_services_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. PRESCRIPTION SEQUENCE (Transaction-safe numbering)
-- ============================================================
CREATE TABLE IF NOT EXISTS `prescription_sequences` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `year` YEAR NOT NULL,
    `last_number` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sequence_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. WAITLIST
-- ============================================================
CREATE TABLE IF NOT EXISTS `appointment_waitlist` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id` INT UNSIGNED NOT NULL,
    `doctor_id` INT UNSIGNED NOT NULL,
    `preferred_date` DATE NOT NULL,
    `preferred_time_start` TIME DEFAULT NULL,
    `preferred_time_end` TIME DEFAULT NULL,
    `type` ENUM('CONSULTATION','VACCINATION','CHECKUP','FOLLOW_UP','OTHER') NOT NULL DEFAULT 'CONSULTATION',
    `reason` TEXT DEFAULT NULL,
    `status` ENUM('WAITING','OFFERED','ACCEPTED','EXPIRED','CANCELLED') NOT NULL DEFAULT 'WAITING',
    `notified_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_waitlist_doctor_date` (`doctor_id`, `preferred_date`),
    INDEX `idx_waitlist_status` (`status`),
    CONSTRAINT `fk_waitlist_patient` FOREIGN KEY (`patient_id`)
        REFERENCES `patients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_waitlist_doctor` FOREIGN KEY (`doctor_id`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_waitlist_creator` FOREIGN KEY (`created_by`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. LOGIN HISTORY
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `status` ENUM('SUCCESS','FAILED','LOCKED') NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_login_user` (`user_id`),
    INDEX `idx_login_created` (`created_at`),
    CONSTRAINT `fk_login_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. DOCTOR SCHEDULES (Recurring weekly schedules)
-- ============================================================
CREATE TABLE IF NOT EXISTS `doctor_schedules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `doctor_id` INT UNSIGNED NOT NULL,
    `day_of_week` ENUM('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY') NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `slot_duration` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'in minutes',
    `max_patients` INT UNSIGNED NOT NULL DEFAULT 10,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_schedules_doctor` (`doctor_id`),
    INDEX `idx_schedules_day` (`day_of_week`),
    CONSTRAINT `fk_schedules_doctor` FOREIGN KEY (`doctor_id`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. ANNOUNCEMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `content` TEXT NOT NULL,
    `category` ENUM('GENERAL','MAINTENANCE','HEALTH_ADVISORY','EVENT','PROMOTION') NOT NULL DEFAULT 'GENERAL',
    `priority` ENUM('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `published_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_announcements_active` (`is_active`),
    INDEX `idx_announcements_published` (`published_at`),
    INDEX `idx_announcements_category` (`category`),
    CONSTRAINT `fk_announcements_creator` FOREIGN KEY (`created_by`)
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
