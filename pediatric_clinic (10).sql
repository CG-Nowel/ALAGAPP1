-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 17, 2026 at 03:27 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pediatric_clinic`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'e.g. user, patient, appointment',
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-03-13 00:10:37'),
(2, 1, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-03-13 00:12:55'),
(3, 1, 'CREATE', NULL, NULL, 'Created new user: CHRISTIAN GEREMILLO (PARENT) - ID: 9', '::1', NULL, '2026-03-13 00:16:17'),
(4, 1, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-03-13 00:16:24'),
(5, 9, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-03-13 00:16:28'),
(6, 9, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-03-13 00:24:43'),
(7, 9, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-03-13 00:25:35'),
(8, 9, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-03-13 00:30:39'),
(9, 4, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-03-13 00:31:28'),
(10, 4, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-03-13 00:36:53'),
(11, 1, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-03-13 00:37:03'),
(12, 1, 'CREATE', NULL, NULL, 'Created announcement: TEST', '::1', NULL, '2026-03-13 00:44:22'),
(13, 1, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-03-13 00:44:28'),
(14, 1, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-03-13 00:45:03'),
(15, 2, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-16 16:47:20'),
(16, 2, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-16 16:47:25'),
(17, 2, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-16 16:47:43'),
(18, 2, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-16 16:47:46'),
(19, 2, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-16 16:47:51'),
(20, 9, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-16 16:48:00'),
(21, 9, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-16 16:48:18'),
(22, 9, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-16 16:48:28'),
(23, 1, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-17 01:05:37'),
(24, 1, 'CREATE', NULL, NULL, 'Added new service: mlmo ($20, 30min)', '::1', NULL, '2026-04-17 01:06:08'),
(25, 1, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-17 01:06:19'),
(26, 1, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-17 01:07:18'),
(27, 1, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-17 01:09:14'),
(28, 9, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-17 01:09:41'),
(29, 9, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-17 01:11:50'),
(30, 2, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-17 01:11:57'),
(31, 2, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-17 01:12:07'),
(32, 9, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-17 01:12:13'),
(33, 9, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-17 01:13:16'),
(34, 2, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-17 01:13:24'),
(35, 2, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-17 01:13:33'),
(36, 9, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-17 01:13:40'),
(37, 9, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-17 01:15:32'),
(38, 2, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-17 01:15:37'),
(39, 2, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-17 01:21:39'),
(40, 1, 'LOGIN', NULL, NULL, NULL, '::1', NULL, '2026-04-17 01:21:44'),
(41, 1, 'LOGOUT', NULL, NULL, 'User logged out', '::1', NULL, '2026-04-17 01:22:57');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `category` enum('GENERAL','MAINTENANCE','HEALTH_ADVISORY','EVENT','PROMOTION') NOT NULL DEFAULT 'GENERAL',
  `priority` enum('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `published_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `category`, `priority`, `is_active`, `published_at`, `expires_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'TEST', 'TEST', 'GENERAL', 'HIGH', 1, '2026-03-13 00:44:22', NULL, 1, '2026-03-13 00:44:22', '2026-03-13 00:44:22');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `type` enum('CONSULTATION','VACCINATION','CHECKUP','FOLLOW_UP','EMERGENCY','OTHER') NOT NULL DEFAULT 'CONSULTATION',
  `status` enum('SCHEDULED','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED','NO_SHOW','WAITLISTED') NOT NULL DEFAULT 'SCHEDULED',
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `duration` int(10) UNSIGNED NOT NULL DEFAULT 30 COMMENT 'in minutes',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `end_time`, `type`, `status`, `reason`, `notes`, `duration`, `cancellation_reason`, `cancelled_at`, `created_by`, `created_at`, `updated_at`) VALUES
(8, 1, 2, '2026-03-25', '10:30:00', NULL, 'CONSULTATION', 'SCHEDULED', '', NULL, 30, NULL, NULL, 9, '2026-03-13 00:30:32', '2026-03-13 00:30:32'),
(9, 1, 2, '2026-04-24', '14:00:00', NULL, 'CONSULTATION', 'COMPLETED', '', NULL, 30, NULL, NULL, 9, '2026-04-17 01:11:42', '2026-04-17 01:13:29');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_waitlist`
--

CREATE TABLE `appointment_waitlist` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time_start` time DEFAULT NULL,
  `preferred_time_end` time DEFAULT NULL,
  `type` enum('CONSULTATION','VACCINATION','CHECKUP','FOLLOW_UP','OTHER') NOT NULL DEFAULT 'CONSULTATION',
  `reason` text DEFAULT NULL,
  `status` enum('WAITING','OFFERED','ACCEPTED','EXPIRED','CANCELLED') NOT NULL DEFAULT 'WAITING',
  `notified_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_settings`
--

CREATE TABLE `clinic_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('STRING','INTEGER','BOOLEAN','JSON') NOT NULL DEFAULT 'STRING',
  `description` varchar(255) DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clinic_settings`
--

INSERT INTO `clinic_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'clinic_name', 'PediCare Clinic', 'STRING', 'Name of the clinic', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(2, 'clinic_phone', '+63 917 123 4567', 'STRING', 'Clinic contact number', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(3, 'clinic_email', 'info@pedicare.com', 'STRING', 'Clinic email address', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(4, 'clinic_address', '123 Health St, Medical City, Metro Manila', 'STRING', 'Clinic physical address', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(5, 'business_hours', '{\"monday\":{\"open\":\"08:00\",\"close\":\"17:00\"},\"tuesday\":{\"open\":\"08:00\",\"close\":\"17:00\"},\"wednesday\":{\"open\":\"08:00\",\"close\":\"17:00\"},\"thursday\":{\"open\":\"08:00\",\"close\":\"17:00\"},\"friday\":{\"open\":\"08:00\",\"close\":\"17:00\"},\"saturday\":{\"open\":\"09:00\",\"close\":\"13:00\"},\"sunday\":null}', 'JSON', 'Weekly business hours', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(6, 'appointment_slot_duration', '30', 'INTEGER', 'Default appointment slot in minutes', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(7, 'max_advance_booking_days', '60', 'INTEGER', 'Maximum days in advance for booking', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(8, 'cancellation_hours', '24', 'INTEGER', 'Minimum hours before appointment for free cancellation', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(9, 'smtp_host', '', 'STRING', 'SMTP server hostname', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(10, 'smtp_port', '587', 'INTEGER', 'SMTP server port', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(11, 'smtp_username', '', 'STRING', 'SMTP username', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(12, 'smtp_password', '', 'STRING', 'SMTP password', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(13, 'sms_provider', '', 'STRING', 'SMS gateway provider', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(14, 'sms_api_key', '', 'STRING', 'SMS gateway API key', NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14');

-- --------------------------------------------------------

--
-- Table structure for table `consultation_notes`
--

CREATE TABLE `consultation_notes` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED DEFAULT NULL,
  `consultation_date` date NOT NULL,
  `chief_complaint` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `heart_rate` int(10) UNSIGNED DEFAULT NULL,
  `respiratory_rate` int(10) UNSIGNED DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `is_visible_to_parent` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `developmental_milestones`
--

CREATE TABLE `developmental_milestones` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `milestone_type` enum('MOTOR','LANGUAGE','SOCIAL','COGNITIVE') NOT NULL,
  `milestone_description` varchar(255) NOT NULL,
  `achieved_date` date NOT NULL,
  `expected_age_months` int(11) NOT NULL,
  `achieved_age_months` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_availability`
--

CREATE TABLE `doctor_availability` (
  `id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` enum('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY') DEFAULT NULL,
  `specific_date` date DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `max_patients` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `availability_type` enum('RECURRING','AVAILABLE','UNAVAILABLE') NOT NULL DEFAULT 'RECURRING',
  `reason` varchar(255) DEFAULT NULL,
  `is_all_day` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_availability`
--

INSERT INTO `doctor_availability` (`id`, `doctor_id`, `day_of_week`, `specific_date`, `start_time`, `end_time`, `slot_duration`, `max_patients`, `availability_type`, `reason`, `is_all_day`, `active`, `created_at`, `updated_at`) VALUES
(1, 2, 'MONDAY', NULL, '08:00:00', '17:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(2, 2, 'TUESDAY', NULL, '08:00:00', '17:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(3, 2, 'WEDNESDAY', NULL, '08:00:00', '17:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(4, 2, 'THURSDAY', NULL, '08:00:00', '17:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(5, 2, 'FRIDAY', NULL, '08:00:00', '17:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(6, 2, 'SATURDAY', NULL, '09:00:00', '13:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(7, 3, 'MONDAY', NULL, '09:00:00', '17:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(8, 3, 'TUESDAY', NULL, '09:00:00', '17:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(9, 3, 'WEDNESDAY', NULL, '09:00:00', '17:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(10, 3, 'THURSDAY', NULL, '09:00:00', '17:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(11, 3, 'FRIDAY', NULL, '09:00:00', '17:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(12, 3, 'SATURDAY', NULL, '09:00:00', '13:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(13, 4, 'MONDAY', NULL, '08:00:00', '16:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(14, 4, 'TUESDAY', NULL, '08:00:00', '16:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(15, 4, 'WEDNESDAY', NULL, '08:00:00', '16:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(16, 4, 'THURSDAY', NULL, '08:00:00', '16:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(17, 4, 'FRIDAY', NULL, '08:00:00', '16:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(18, 4, 'SATURDAY', NULL, '09:00:00', '13:00:00', 30, 10, 'RECURRING', NULL, 0, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(19, 2, NULL, '2026-04-30', '00:00:00', '23:59:59', 30, 10, 'UNAVAILABLE', '', 1, 1, '2026-04-16 16:47:30', '2026-04-16 16:47:30');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedules`
--

CREATE TABLE `doctor_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` enum('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration` int(10) UNSIGNED NOT NULL DEFAULT 30 COMMENT 'in minutes',
  `max_patients` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_schedules`
--

INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `day_of_week`, `start_time`, `end_time`, `slot_duration`, `max_patients`, `active`, `created_at`, `updated_at`) VALUES
(1, 2, 'MONDAY', '08:00:00', '17:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(2, 2, 'TUESDAY', '08:00:00', '17:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(3, 2, 'WEDNESDAY', '08:00:00', '17:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(4, 2, 'THURSDAY', '08:00:00', '17:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(5, 2, 'FRIDAY', '08:00:00', '17:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(6, 2, 'SATURDAY', '09:00:00', '13:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(7, 3, 'MONDAY', '09:00:00', '17:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(8, 3, 'TUESDAY', '09:00:00', '17:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(9, 3, 'WEDNESDAY', '09:00:00', '17:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(10, 3, 'THURSDAY', '09:00:00', '17:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(11, 3, 'FRIDAY', '09:00:00', '17:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(12, 3, 'SATURDAY', '09:00:00', '13:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(13, 4, 'MONDAY', '08:00:00', '16:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(14, 4, 'TUESDAY', '08:00:00', '16:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(15, 4, 'WEDNESDAY', '08:00:00', '16:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(16, 4, 'THURSDAY', '08:00:00', '16:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(17, 4, 'FRIDAY', '08:00:00', '16:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(18, 4, 'SATURDAY', '09:00:00', '13:00:00', 30, 10, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14');

-- --------------------------------------------------------

--
-- Table structure for table `growth_records`
--

CREATE TABLE `growth_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `height` decimal(5,2) NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `head_circumference` decimal(4,2) DEFAULT NULL,
  `bmi` decimal(4,2) DEFAULT NULL,
  `height_percentile` decimal(5,2) DEFAULT NULL,
  `weight_percentile` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('SUCCESS','FAILED','LOCKED') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_certificates`
--

CREATE TABLE `medical_certificates` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED DEFAULT NULL,
  `certificate_date` date NOT NULL,
  `certificate_number` varchar(50) DEFAULT NULL,
  `purpose` enum('SCHOOL','SPORTS','TRAVEL','WORK','OTHER') NOT NULL DEFAULT 'SCHOOL',
  `diagnosis` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `valid_from` date NOT NULL,
  `valid_until` date NOT NULL,
  `clinic_stamp` varchar(255) DEFAULT NULL,
  `doctor_signature` varchar(255) DEFAULT NULL,
  `is_printed` tinyint(1) NOT NULL DEFAULT 0,
  `printed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `record_type` enum('CONSULTATION','CHECKUP','FOLLOW_UP','EMERGENCY','OTHER') NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `temperature` decimal(4,2) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `heart_rate` int(10) UNSIGNED DEFAULT NULL,
  `respiratory_rate` int(10) UNSIGNED DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `prescriptions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('APPOINTMENT','VACCINATION','SYSTEM','REMINDER','ALERT') NOT NULL DEFAULT 'SYSTEM',
  `channel` enum('IN_APP','EMAIL','SMS','ALL') NOT NULL DEFAULT 'IN_APP',
  `related_type` varchar(50) DEFAULT NULL COMMENT 'e.g. appointment, prescription',
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('MALE','FEMALE','OTHER') NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL COMMENT 'in cm',
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'in kg',
  `allergies` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `special_notes` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `parent_id`, `first_name`, `last_name`, `date_of_birth`, `gender`, `blood_type`, `height`, `weight`, `allergies`, `medical_conditions`, `special_notes`, `profile_picture`, `created_at`, `updated_at`) VALUES
(1, 9, 'Dallas Nowel', 'Geremillo', '2025-11-12', 'FEMALE', 'B-', 0.30, 0.40, '', '', NULL, NULL, '2026-03-13 00:17:02', '2026-03-13 00:17:02');

-- --------------------------------------------------------

--
-- Table structure for table `patient_files`
--

CREATE TABLE `patient_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL COMMENT 'in bytes',
  `file_category` enum('LAB_RESULT','XRAY','PRESCRIPTION','REFERRAL','OTHER') NOT NULL DEFAULT 'OTHER',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_vaccine_needs`
--

CREATE TABLE `patient_vaccine_needs` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED DEFAULT NULL,
  `vaccine_name` varchar(100) NOT NULL,
  `dose_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `recommended_date` date DEFAULT NULL,
  `status` enum('PENDING','SCHEDULED','COMPLETED','MISSED','SKIPPED') NOT NULL DEFAULT 'PENDING',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patient_vaccine_needs`
--

INSERT INTO `patient_vaccine_needs` (`id`, `patient_id`, `vaccine_id`, `vaccine_name`, `dose_number`, `recommended_date`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Hib', 1, NULL, '', '0', 2, '2026-04-17 01:18:29', '2026-04-17 01:18:29');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `prescription_number` varchar(30) NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED DEFAULT NULL,
  `prescription_date` date NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `medications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of {name, dosage, frequency, duration, instructions}' CHECK (json_valid(`medications`)),
  `notes` text DEFAULT NULL,
  `status` enum('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  `refills_allowed` int(10) UNSIGNED DEFAULT 0,
  `refill_instructions` text DEFAULT NULL,
  `pharmacy_notes` text DEFAULT NULL,
  `clinic_address` text DEFAULT NULL,
  `doctor_signature` varchar(255) DEFAULT NULL,
  `is_printed` tinyint(1) NOT NULL DEFAULT 0,
  `printed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_sequences`
--

CREATE TABLE `prescription_sequences` (
  `id` int(10) UNSIGNED NOT NULL,
  `year` year(4) NOT NULL,
  `last_number` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `prescription_sequences`
--

INSERT INTO `prescription_sequences` (`id`, `year`, `last_number`) VALUES
(1, '2026', 0);

-- --------------------------------------------------------

--
-- Table structure for table `print_templates`
--

CREATE TABLE `print_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `template_type` enum('PRESCRIPTION','MEDICAL_CERTIFICATE','LAB_REPORT','OTHER') NOT NULL,
  `template_content` text NOT NULL,
  `header_html` text DEFAULT NULL,
  `footer_html` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duration` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `duration`, `cost`, `active`, `created_at`, `updated_at`) VALUES
(1, 'General Consultation', 'Standard pediatric consultation', 30, 500.00, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(2, 'Vaccination', 'Routine immunization administration', 15, 300.00, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(3, 'Well-Baby Checkup', 'Comprehensive developmental assessment', 45, 800.00, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(4, 'Follow-up Visit', 'Follow-up on previous consultation', 20, 400.00, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(5, 'Emergency Consultation', 'Urgent pediatric care', 60, 1500.00, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(6, 'Growth Assessment', 'Height, weight, and developmental monitoring', 30, 600.00, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(7, 'General Consultation', 'Standard pediatric consultation', 30, 500.00, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(8, 'Vaccination', 'Routine immunization administration', 15, 300.00, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(9, 'Well-Baby Checkup', 'Comprehensive developmental assessment', 45, 800.00, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(10, 'Follow-up Visit', 'Follow-up on previous consultation', 20, 400.00, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(11, 'Emergency Consultation', 'Urgent pediatric care', 60, 1500.00, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(12, 'Growth Assessment', 'Height, weight, and developmental monitoring', 30, 600.00, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(13, 'mlmo', '', 30, 20.00, 1, '2026-04-17 01:06:08', '2026-04-17 01:06:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('PARENT','DOCTOR','DOCTOR_OWNER','ADMIN') NOT NULL DEFAULT 'PARENT',
  `status` enum('active','inactive','suspended','pending') NOT NULL DEFAULT 'active',
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('MALE','FEMALE','OTHER') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `years_of_experience` int(10) UNSIGNED DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `email_verification_token` varchar(64) DEFAULT NULL,
  `password_reset_token` varchar(64) DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `login_attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `force_password_change` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `password`, `user_type`, `status`, `date_of_birth`, `gender`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `profile_picture`, `specialization`, `license_number`, `years_of_experience`, `email_verified_at`, `email_verification_token`, `password_reset_token`, `password_reset_expires`, `two_factor_secret`, `two_factor_enabled`, `login_attempts`, `locked_until`, `force_password_change`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`) VALUES
(1, 'System', 'Administrator', 'admin@pedicare.com', '9171234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-13 00:10:14', NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(2, 'Maria', 'Santos', 'dr.santos@pedicare.com', '9171111111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DOCTOR', 'active', NULL, NULL, NULL, NULL, NULL, NULL, 'General Pediatrics', 'PRC-2024-001', 15, '2026-03-13 00:10:14', NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(3, 'Juan', 'Dela Cruz', 'dr.delacruz@pedicare.com', '9172222222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DOCTOR', 'active', NULL, NULL, NULL, NULL, NULL, NULL, 'Pediatric Cardiology', 'PRC-2024-002', 10, '2026-03-13 00:10:14', NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(4, 'Ana', 'Reyes', 'dr.reyes@pedicare.com', '9173333333', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DOCTOR', 'active', NULL, NULL, NULL, NULL, NULL, NULL, 'Pediatric Neurology', 'PRC-2024-003', 8, '2026-03-13 00:10:14', NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(9, 'CHRISTIAN', 'GEREMILLO', 'geremillo@gmail.com', '09317093056', '$2y$10$2fMI31xgj1mHORO/vSLfoup/3jz6kzc5RR3Tjzbkmfpu9aP5lFi8W', 'PARENT', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, '2026-03-13 00:16:17', '2026-03-13 00:16:17');

-- --------------------------------------------------------

--
-- Table structure for table `vaccination_records`
--

CREATE TABLE `vaccination_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED DEFAULT NULL,
  `vaccine_name` varchar(100) NOT NULL,
  `vaccine_type` enum('ROUTINE','OPTIONAL','SPECIAL') NOT NULL DEFAULT 'ROUTINE',
  `dose_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `total_doses` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `administration_date` date NOT NULL,
  `next_due_date` date DEFAULT NULL,
  `administered_by` int(10) UNSIGNED NOT NULL,
  `lot_number` varchar(50) DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `site` enum('LEFT_ARM','RIGHT_ARM','LEFT_THIGH','RIGHT_THIGH','ORAL') NOT NULL DEFAULT 'LEFT_ARM',
  `notes` text DEFAULT NULL,
  `status` enum('COMPLETED','SCHEDULED','MISSED','OVERDUE') NOT NULL DEFAULT 'COMPLETED',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vaccines`
--

CREATE TABLE `vaccines` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `disease_protected` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `vaccine_type` enum('ROUTINE','OPTIONAL','SPECIAL') NOT NULL DEFAULT 'ROUTINE',
  `total_doses` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `dose_interval_days` int(10) UNSIGNED DEFAULT NULL COMMENT 'Days between doses',
  `min_age_months` int(10) UNSIGNED DEFAULT NULL,
  `max_age_months` int(10) UNSIGNED DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vaccines`
--

INSERT INTO `vaccines` (`id`, `name`, `disease_protected`, `description`, `manufacturer`, `vaccine_type`, `total_doses`, `dose_interval_days`, `min_age_months`, `max_age_months`, `active`, `created_at`, `updated_at`) VALUES
(1, 'BCG', NULL, 'Bacillus Calmette-Guerin - Tuberculosis vaccine', 'Various', 'ROUTINE', 1, NULL, 0, 12, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(2, 'Hepatitis B', NULL, 'Hepatitis B vaccine', 'Various', 'ROUTINE', 3, 30, 0, 6, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(3, 'DTaP', NULL, 'Diphtheria, Tetanus, Pertussis', 'Various', 'ROUTINE', 5, 60, 2, 72, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(4, 'IPV', NULL, 'Inactivated Polio Vaccine', 'Various', 'ROUTINE', 4, 60, 2, 72, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(5, 'Hib', NULL, 'Haemophilus influenzae type b', 'Various', 'ROUTINE', 4, 60, 2, 15, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(6, 'PCV13', NULL, 'Pneumococcal Conjugate Vaccine', 'Pfizer', 'ROUTINE', 4, 60, 2, 15, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(7, 'Rotavirus', NULL, 'Rotavirus vaccine', 'Various', 'ROUTINE', 3, 30, 2, 8, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(8, 'MMR', NULL, 'Measles, Mumps, Rubella', 'Various', 'ROUTINE', 2, 90, 12, 72, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(9, 'Varicella', NULL, 'Chickenpox vaccine', 'Various', 'ROUTINE', 2, 90, 12, 72, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(10, 'Hepatitis A', NULL, 'Hepatitis A vaccine', 'Various', 'ROUTINE', 2, 180, 12, 24, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(11, 'Influenza', NULL, 'Seasonal Flu vaccine', 'Various', 'OPTIONAL', 1, NULL, 6, 216, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(12, 'HPV', NULL, 'Human Papillomavirus vaccine', 'Various', 'OPTIONAL', 3, 60, 108, 156, 1, '2026-03-13 00:10:14', '2026-03-13 00:10:14'),
(13, 'BCG', NULL, 'Bacillus Calmette-Guerin - Tuberculosis vaccine', 'Various', 'ROUTINE', 1, NULL, 0, 12, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(14, 'Hepatitis B', NULL, 'Hepatitis B vaccine', 'Various', 'ROUTINE', 3, 30, 0, 6, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(15, 'DTaP', NULL, 'Diphtheria, Tetanus, Pertussis', 'Various', 'ROUTINE', 5, 60, 2, 72, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(16, 'IPV', NULL, 'Inactivated Polio Vaccine', 'Various', 'ROUTINE', 4, 60, 2, 72, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(17, 'Hib', NULL, 'Haemophilus influenzae type b', 'Various', 'ROUTINE', 4, 60, 2, 15, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(18, 'PCV13', NULL, 'Pneumococcal Conjugate Vaccine', 'Pfizer', 'ROUTINE', 4, 60, 2, 15, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(19, 'Rotavirus', NULL, 'Rotavirus vaccine', 'Various', 'ROUTINE', 3, 30, 2, 8, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(20, 'MMR', NULL, 'Measles, Mumps, Rubella', 'Various', 'ROUTINE', 2, 90, 12, 72, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(21, 'Varicella', NULL, 'Chickenpox vaccine', 'Various', 'ROUTINE', 2, 90, 12, 72, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(22, 'Hepatitis A', NULL, 'Hepatitis A vaccine', 'Various', 'ROUTINE', 2, 180, 12, 24, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(23, 'Influenza', NULL, 'Seasonal Flu vaccine', 'Various', 'OPTIONAL', 1, NULL, 6, 216, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28'),
(24, 'HPV', NULL, 'Human Papillomavirus vaccine', 'Various', 'OPTIONAL', 3, 60, 108, 156, 1, '2026-03-13 00:10:28', '2026-03-13 00:10:28');

-- --------------------------------------------------------

--
-- Table structure for table `vaccine_schedule`
--

CREATE TABLE `vaccine_schedule` (
  `id` int(10) UNSIGNED NOT NULL,
  `vaccine_id` int(10) UNSIGNED NOT NULL,
  `dose_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `recommended_age_months` int(10) UNSIGNED NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vaccine_schedule`
--

INSERT INTO `vaccine_schedule` (`id`, `vaccine_id`, `dose_number`, `recommended_age_months`, `description`, `is_mandatory`, `created_at`) VALUES
(1, 1, 1, 0, 'BCG at birth', 1, '2026-03-13 00:10:14'),
(2, 2, 1, 0, 'Hepatitis B - 1st dose at birth', 1, '2026-03-13 00:10:14'),
(3, 2, 2, 1, 'Hepatitis B - 2nd dose at 1 month', 1, '2026-03-13 00:10:14'),
(4, 2, 3, 6, 'Hepatitis B - 3rd dose at 6 months', 1, '2026-03-13 00:10:14'),
(5, 3, 1, 2, 'DTaP - 1st dose at 2 months', 1, '2026-03-13 00:10:14'),
(6, 3, 2, 4, 'DTaP - 2nd dose at 4 months', 1, '2026-03-13 00:10:14'),
(7, 3, 3, 6, 'DTaP - 3rd dose at 6 months', 1, '2026-03-13 00:10:14'),
(8, 3, 4, 18, 'DTaP - 4th dose at 18 months', 1, '2026-03-13 00:10:14'),
(9, 3, 5, 48, 'DTaP - 5th dose at 4 years', 1, '2026-03-13 00:10:14'),
(10, 4, 1, 2, 'IPV - 1st dose at 2 months', 1, '2026-03-13 00:10:14'),
(11, 4, 2, 4, 'IPV - 2nd dose at 4 months', 1, '2026-03-13 00:10:14'),
(12, 4, 3, 6, 'IPV - 3rd dose at 6 months', 1, '2026-03-13 00:10:14'),
(13, 4, 4, 48, 'IPV - 4th dose at 4 years', 1, '2026-03-13 00:10:14'),
(14, 8, 1, 12, 'MMR - 1st dose at 12 months', 1, '2026-03-13 00:10:14'),
(15, 8, 2, 48, 'MMR - 2nd dose at 4 years', 1, '2026-03-13 00:10:14'),
(16, 9, 1, 12, 'Varicella - 1st dose at 12 months', 1, '2026-03-13 00:10:14'),
(17, 9, 2, 48, 'Varicella - 2nd dose at 4 years', 1, '2026-03-13 00:10:14'),
(18, 1, 1, 0, 'BCG at birth', 1, '2026-03-13 00:10:28'),
(19, 2, 1, 0, 'Hepatitis B - 1st dose at birth', 1, '2026-03-13 00:10:28'),
(20, 2, 2, 1, 'Hepatitis B - 2nd dose at 1 month', 1, '2026-03-13 00:10:28'),
(21, 2, 3, 6, 'Hepatitis B - 3rd dose at 6 months', 1, '2026-03-13 00:10:28'),
(22, 3, 1, 2, 'DTaP - 1st dose at 2 months', 1, '2026-03-13 00:10:28'),
(23, 3, 2, 4, 'DTaP - 2nd dose at 4 months', 1, '2026-03-13 00:10:28'),
(24, 3, 3, 6, 'DTaP - 3rd dose at 6 months', 1, '2026-03-13 00:10:28'),
(25, 3, 4, 18, 'DTaP - 4th dose at 18 months', 1, '2026-03-13 00:10:28'),
(26, 3, 5, 48, 'DTaP - 5th dose at 4 years', 1, '2026-03-13 00:10:28'),
(27, 4, 1, 2, 'IPV - 1st dose at 2 months', 1, '2026-03-13 00:10:28'),
(28, 4, 2, 4, 'IPV - 2nd dose at 4 months', 1, '2026-03-13 00:10:28'),
(29, 4, 3, 6, 'IPV - 3rd dose at 6 months', 1, '2026-03-13 00:10:28'),
(30, 4, 4, 48, 'IPV - 4th dose at 4 years', 1, '2026-03-13 00:10:28'),
(31, 8, 1, 12, 'MMR - 1st dose at 12 months', 1, '2026-03-13 00:10:28'),
(32, 8, 2, 48, 'MMR - 2nd dose at 4 years', 1, '2026-03-13 00:10:28'),
(33, 9, 1, 12, 'Varicella - 1st dose at 12 months', 1, '2026-03-13 00:10:28'),
(34, 9, 2, 48, 'Varicella - 2nd dose at 4 years', 1, '2026-03-13 00:10:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_action` (`action`),
  ADD KEY `idx_activity_timestamp` (`created_at`),
  ADD KEY `idx_activity_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcements_active` (`is_active`),
  ADD KEY `idx_announcements_published` (`published_at`),
  ADD KEY `idx_announcements_category` (`category`),
  ADD KEY `fk_announcements_creator` (`created_by`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appointments_doctor_date` (`doctor_id`,`appointment_date`),
  ADD KEY `idx_appointments_patient` (`patient_id`),
  ADD KEY `idx_appointments_date` (`appointment_date`),
  ADD KEY `idx_appointments_status` (`status`),
  ADD KEY `fk_appointments_creator` (`created_by`);

--
-- Indexes for table `appointment_waitlist`
--
ALTER TABLE `appointment_waitlist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_waitlist_doctor_date` (`doctor_id`,`preferred_date`),
  ADD KEY `idx_waitlist_status` (`status`),
  ADD KEY `fk_waitlist_patient` (`patient_id`),
  ADD KEY `fk_waitlist_creator` (`created_by`);

--
-- Indexes for table `clinic_settings`
--
ALTER TABLE `clinic_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_setting_key` (`setting_key`),
  ADD KEY `fk_settings_updater` (`updated_by`);

--
-- Indexes for table `consultation_notes`
--
ALTER TABLE `consultation_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_consultation_patient` (`patient_id`),
  ADD KEY `idx_consultation_doctor` (`doctor_id`),
  ADD KEY `idx_consultation_date` (`consultation_date`),
  ADD KEY `fk_consultation_appointment` (`appointment_id`);

--
-- Indexes for table `developmental_milestones`
--
ALTER TABLE `developmental_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_milestones_patient` (`patient_id`),
  ADD KEY `idx_milestones_type` (`milestone_type`),
  ADD KEY `fk_milestones_recorded_by` (`recorded_by`);

--
-- Indexes for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_availability_doctor` (`doctor_id`),
  ADD KEY `idx_availability_date` (`specific_date`),
  ADD KEY `idx_availability_day` (`day_of_week`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedules_doctor` (`doctor_id`),
  ADD KEY `idx_schedules_day` (`day_of_week`);

--
-- Indexes for table `growth_records`
--
ALTER TABLE `growth_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_growth_patient` (`patient_id`),
  ADD KEY `idx_growth_date` (`record_date`),
  ADD KEY `fk_growth_recorded_by` (`recorded_by`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_user` (`user_id`),
  ADD KEY `idx_login_created` (`created_at`);

--
-- Indexes for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_certificate_number` (`certificate_number`),
  ADD KEY `idx_certificates_patient` (`patient_id`),
  ADD KEY `idx_certificates_doctor` (`doctor_id`),
  ADD KEY `idx_certificates_appointment` (`appointment_id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medical_records_patient` (`patient_id`),
  ADD KEY `idx_medical_records_doctor` (`doctor_id`),
  ADD KEY `idx_medical_records_date` (`record_date`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_read` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_type` (`type`),
  ADD KEY `idx_notifications_created` (`created_at`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patients_parent` (`parent_id`),
  ADD KEY `idx_patients_dob` (`date_of_birth`);

--
-- Indexes for table `patient_files`
--
ALTER TABLE `patient_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_files_patient` (`patient_id`),
  ADD KEY `idx_files_uploader` (`uploaded_by`);

--
-- Indexes for table `patient_vaccine_needs`
--
ALTER TABLE `patient_vaccine_needs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vaccine_needs_patient` (`patient_id`),
  ADD KEY `idx_vaccine_needs_status` (`status`),
  ADD KEY `idx_vaccine_needs_created_by` (`created_by`),
  ADD KEY `fk_vaccine_needs_vaccine` (`vaccine_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_prescription_number` (`prescription_number`),
  ADD KEY `idx_prescriptions_patient` (`patient_id`),
  ADD KEY `idx_prescriptions_doctor` (`doctor_id`),
  ADD KEY `idx_prescriptions_date` (`prescription_date`),
  ADD KEY `fk_prescriptions_appointment` (`appointment_id`);

--
-- Indexes for table `prescription_sequences`
--
ALTER TABLE `prescription_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sequence_year` (`year`);

--
-- Indexes for table `print_templates`
--
ALTER TABLE `print_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_services_active` (`active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_users_email` (`email`),
  ADD KEY `idx_users_type` (`user_type`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_email_verified` (`email_verified_at`);

--
-- Indexes for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vaccination_patient` (`patient_id`),
  ADD KEY `idx_vaccination_vaccine` (`vaccine_id`),
  ADD KEY `idx_vaccination_date` (`administration_date`),
  ADD KEY `idx_vaccination_status` (`status`),
  ADD KEY `fk_vaccination_admin` (`administered_by`);

--
-- Indexes for table `vaccines`
--
ALTER TABLE `vaccines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vaccines_type` (`vaccine_type`),
  ADD KEY `idx_vaccines_active` (`active`);

--
-- Indexes for table `vaccine_schedule`
--
ALTER TABLE `vaccine_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedule_vaccine` (`vaccine_id`),
  ADD KEY `idx_schedule_age` (`recommended_age_months`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `appointment_waitlist`
--
ALTER TABLE `appointment_waitlist`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clinic_settings`
--
ALTER TABLE `clinic_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `consultation_notes`
--
ALTER TABLE `consultation_notes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `developmental_milestones`
--
ALTER TABLE `developmental_milestones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `growth_records`
--
ALTER TABLE `growth_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `patient_files`
--
ALTER TABLE `patient_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_vaccine_needs`
--
ALTER TABLE `patient_vaccine_needs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescription_sequences`
--
ALTER TABLE `prescription_sequences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `print_templates`
--
ALTER TABLE `print_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vaccines`
--
ALTER TABLE `vaccines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `vaccine_schedule`
--
ALTER TABLE `vaccine_schedule`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointments_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `appointment_waitlist`
--
ALTER TABLE `appointment_waitlist`
  ADD CONSTRAINT `fk_waitlist_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_waitlist_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_waitlist_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `clinic_settings`
--
ALTER TABLE `clinic_settings`
  ADD CONSTRAINT `fk_settings_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `consultation_notes`
--
ALTER TABLE `consultation_notes`
  ADD CONSTRAINT `fk_consultation_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consultation_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consultation_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `developmental_milestones`
--
ALTER TABLE `developmental_milestones`
  ADD CONSTRAINT `fk_milestones_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_milestones_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  ADD CONSTRAINT `fk_availability_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `fk_schedules_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `growth_records`
--
ALTER TABLE `growth_records`
  ADD CONSTRAINT `fk_growth_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_growth_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `fk_login_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  ADD CONSTRAINT `fk_certificates_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_certificates_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_certificates_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `fk_medical_records_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_medical_records_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patients_parent` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patient_files`
--
ALTER TABLE `patient_files`
  ADD CONSTRAINT `fk_files_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_files_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patient_vaccine_needs`
--
ALTER TABLE `patient_vaccine_needs`
  ADD CONSTRAINT `fk_vaccine_needs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vaccine_needs_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vaccine_needs_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_prescriptions_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prescriptions_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prescriptions_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD CONSTRAINT `fk_vaccination_admin` FOREIGN KEY (`administered_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vaccination_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vaccination_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `vaccine_schedule`
--
ALTER TABLE `vaccine_schedule`
  ADD CONSTRAINT `fk_schedule_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
