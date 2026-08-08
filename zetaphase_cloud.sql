-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 08, 2026 at 09:22 PM
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
-- Database: `zetaphase_cloud`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_arms`
--

CREATE TABLE `academic_arms` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `class_name` varchar(100) DEFAULT NULL,
  `arm_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_arms`
--

INSERT INTO `academic_arms` (`id`, `school_uuid`, `class_name`, `arm_name`) VALUES
(1, 'sch-fded1718575ebd', 'JSS1', 'A'),
(2, 'sch-fded1718575ebd', 'JSS2', 'A'),
(3, 'sch-fded1718575ebd', 'JSS3', 'A'),
(11, 'sch-fded1718575ebd', 'SS1', 'Arts'),
(8, 'sch-fded1718575ebd', 'SS1', 'Science'),
(12, 'sch-fded1718575ebd', 'SS2', 'Arts'),
(9, 'sch-fded1718575ebd', 'SS2', 'Science'),
(13, 'sch-fded1718575ebd', 'SS3', 'Arts'),
(10, 'sch-fded1718575ebd', 'SS3', 'Science');

-- --------------------------------------------------------

--
-- Table structure for table `academic_classes`
--

CREATE TABLE `academic_classes` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `class_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_classes`
--

INSERT INTO `academic_classes` (`id`, `school_uuid`, `class_name`) VALUES
(1, 'sch-fded1718575ebd', 'JSS1'),
(2, 'sch-fded1718575ebd', 'JSS2'),
(3, 'sch-fded1718575ebd', 'JSS3'),
(4, 'sch-fded1718575ebd', 'SS1'),
(5, 'sch-fded1718575ebd', 'SS2'),
(6, 'sch-fded1718575ebd', 'SS3');

-- --------------------------------------------------------

--
-- Table structure for table `academic_sessions`
--

CREATE TABLE `academic_sessions` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `session_name` varchar(50) NOT NULL,
  `is_current` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_sessions`
--

INSERT INTO `academic_sessions` (`id`, `school_uuid`, `session_name`, `is_current`) VALUES
(1, 'sch-fded1718575ebd', '2026/2027', 1);

-- --------------------------------------------------------

--
-- Table structure for table `academic_subjects`
--

CREATE TABLE `academic_subjects` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `department_name` varchar(100) DEFAULT 'General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_subjects`
--

INSERT INTO `academic_subjects` (`id`, `school_uuid`, `subject_code`, `subject_name`, `department_name`) VALUES
(1, 'sch-fded1718575ebd', 'MTH', 'Mathematics', 'General'),
(2, 'sch-fded1718575ebd', 'ENG', 'English Studies', 'General'),
(3, 'sch-fded1718575ebd', 'PHY', 'Physics', 'General'),
(4, 'sch-fded1718575ebd', 'GOV', 'Government', 'General');

-- --------------------------------------------------------

--
-- Table structure for table `academic_terms`
--

CREATE TABLE `academic_terms` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `term_name` varchar(50) NOT NULL,
  `is_current` tinyint(4) DEFAULT 0,
  `is_open` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = school admin has opened this term for attendance/operations; 0 = closed',
  `start_date` date DEFAULT NULL COMMENT 'Optional informational term start date',
  `end_date` date DEFAULT NULL COMMENT 'Optional informational term end date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_terms`
--

INSERT INTO `academic_terms` (`id`, `school_uuid`, `term_name`, `is_current`, `is_open`, `start_date`, `end_date`) VALUES
(1, 'sch-fded1718575ebd', 'First Term', 1, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `alumni`
--

CREATE TABLE `alumni` (
  `id` int(11) NOT NULL,
  `alumni_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `graduation_year` int(11) NOT NULL,
  `final_class` varchar(50) DEFAULT 'SSS3',
  `cumulative_gpa` decimal(4,2) DEFAULT 3.85,
  `character_conduct` varchar(50) DEFAULT 'Exemplary & Outstanding',
  `testimonial_text` text DEFAULT NULL,
  `archived_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `assignment_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `session_name` varchar(50) DEFAULT NULL,
  `term_name` varchar(50) DEFAULT NULL,
  `teacher_name` varchar(100) NOT NULL,
  `assigned_by_staff_uuid` varchar(50) DEFAULT NULL,
  `assigned_by_staff_name` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `due_date` date NOT NULL,
  `max_score` int(11) DEFAULT 100,
  `attachment_url` text DEFAULT NULL,
  `approval_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' COMMENT 'Only Approved assignments are visible to parents/students',
  `approved_by` varchar(150) DEFAULT NULL COMMENT 'Name of the full-access staff/admin who approved it',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_note` varchar(255) DEFAULT NULL COMMENT 'e.g. "Direct approval" or "Approved via parent meeting with ... on ..."',
  `linked_appointment_uuid` varchar(50) DEFAULT NULL COMMENT 'parent_teacher_appointments.appointment_uuid, if this approval was granted via a confirmed parent meeting',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--

CREATE TABLE `assignment_submissions` (
  `id` int(11) NOT NULL,
  `submission_uuid` varchar(50) NOT NULL,
  `assignment_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `submission_text` text DEFAULT NULL,
  `file_url` text DEFAULT NULL,
  `grade_score` decimal(5,2) DEFAULT NULL,
  `teacher_feedback` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Submitted',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `id` int(11) NOT NULL,
  `attachment_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) DEFAULT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_uuid` varchar(50) NOT NULL,
  `label` varchar(150) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `session_name` varchar(50) DEFAULT NULL,
  `term_name` varchar(50) DEFAULT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `arm_name` varchar(50) DEFAULT NULL,
  `marked_by` varchar(50) DEFAULT NULL COMMENT 'staff_uuid of the class teacher who marked',
  `status` varchar(20) DEFAULT 'Present',
  `auto_marked` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) DEFAULT NULL,
  `user_email` varchar(150) DEFAULT NULL,
  `action` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `log_uuid` varchar(50) DEFAULT NULL,
  `user_uuid` varchar(50) DEFAULT NULL,
  `target_uuid` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `school_uuid`, `user_email`, `action`, `created_at`, `log_uuid`, `user_uuid`, `target_uuid`, `description`, `ip_address`) VALUES
(1, NULL, NULL, 'auth.login', '2026-08-07 07:58:08', 'log-6a7590104bc47', 'usr-platform-mgr-0001', 'usr-platform-mgr-0001', 'Authenticated via platform portal', '127.0.0.1'),
(2, NULL, NULL, 'auth.login', '2026-08-07 07:58:59', 'log-6a7590438d25a', 'usr-platform-mgr-0001', 'usr-platform-mgr-0001', 'Authenticated via platform portal', '127.0.0.1'),
(3, NULL, NULL, 'auth.login', '2026-08-08 01:17:14', 'log-6a76839a3efd1', 'usr-platform-mgr-0001', 'usr-platform-mgr-0001', 'Authenticated via platform portal', '127.0.0.1'),
(4, 'sch-fded1718575ebd', NULL, 'auth.login', '2026-08-08 01:21:19', 'log-6a76848fabcfd', 'usr-8fbcd0818a6868', 'usr-8fbcd0818a6868', 'Authenticated via khadob portal', '127.0.0.1'),
(5, 'sch-fded1718575ebd', 'zaruqzainab@gmail.com', 'Changed password', '2026-08-08 01:21:40', NULL, NULL, NULL, NULL, NULL),
(6, 'sch-fded1718575ebd', 'zaruqzainab@gmail.com', 'Changed personal account password', '2026-08-08 01:26:27', NULL, NULL, NULL, NULL, NULL),
(7, 'sch-fded1718575ebd', NULL, 'auth.login', '2026-08-08 01:26:42', 'log-6a7685d2bd2e3', 'usr-8fbcd0818a6868', 'usr-8fbcd0818a6868', 'Authenticated via khadob portal', '127.0.0.1'),
(8, 'sch-fded1718575ebd', NULL, 'settings.condition_of_service', '2026-08-08 01:30:44', 'log-6a7686c45f7b7', 'usr-8fbcd0818a6868', 'sch-fded1718575ebd', 'Updated condition of service', '127.0.0.1'),
(9, 'sch-fded1718575ebd', NULL, 'settings.school_policy', '2026-08-08 01:30:54', 'log-6a7686ce617ca', 'usr-8fbcd0818a6868', 'sch-fded1718575ebd', 'Updated school policy', '127.0.0.1'),
(10, 'sch-fded1718575ebd', NULL, 'settings.assessment_templates', '2026-08-08 01:34:34', 'log-6a7687aad01a2', 'usr-8fbcd0818a6868', NULL, 'Assessment templates updated via JSON', '127.0.0.1'),
(11, 'sch-fded1718575ebd', NULL, 'settings.grading_scale', '2026-08-08 01:36:22', 'log-6a7688166968f', 'usr-8fbcd0818a6868', 'sch-fded1718575ebd', 'Updated grading scale', '127.0.0.1'),
(12, 'sch-fded1718575ebd', NULL, 'session.create', '2026-08-08 01:36:44', 'log-6a76882c58061', 'usr-8fbcd0818a6868', NULL, 'Added session 2026/2027', '127.0.0.1'),
(13, 'sch-fded1718575ebd', NULL, 'term.open', '2026-08-08 01:36:53', 'log-6a768835f09c5', 'usr-8fbcd0818a6868', '1', 'Term opened for attendance/operations', '127.0.0.1'),
(14, 'sch-fded1718575ebd', NULL, 'subject.add', '2026-08-08 01:41:59', 'log-6a768967de7de', 'usr-8fbcd0818a6868', NULL, 'Added subject: Mathematics', '127.0.0.1'),
(15, 'sch-fded1718575ebd', NULL, 'subject.add', '2026-08-08 01:42:10', 'log-6a7689727836f', 'usr-8fbcd0818a6868', NULL, 'Added subject: English Studies', '127.0.0.1'),
(16, 'sch-fded1718575ebd', NULL, 'subject.add', '2026-08-08 01:42:17', 'log-6a768979d4c45', 'usr-8fbcd0818a6868', NULL, 'Added subject: Physics', '127.0.0.1'),
(17, 'sch-fded1718575ebd', NULL, 'subject.add', '2026-08-08 01:42:30', 'log-6a7689866b5ab', 'usr-8fbcd0818a6868', NULL, 'Added subject: Government', '127.0.0.1'),
(18, 'sch-fded1718575ebd', NULL, 'subject.add', '2026-08-08 01:42:38', 'log-6a76898e38f76', 'usr-8fbcd0818a6868', NULL, 'Added subject: sdvfv', '127.0.0.1'),
(19, 'sch-fded1718575ebd', NULL, 'settings.assessment_config', '2026-08-08 01:43:07', 'log-6a7689abae6bd', 'usr-8fbcd0818a6868', NULL, 'Assessment config saved/updated: 2026/2027/First Term/All', '127.0.0.1'),
(20, 'sch-fded1718575ebd', NULL, 'settings.assessment_config', '2026-08-08 01:43:18', 'log-6a7689b6dfd19', 'usr-8fbcd0818a6868', NULL, 'Assessment config saved/updated: 2026/2027/First Term/All', '127.0.0.1'),
(21, 'sch-fded1718575ebd', NULL, 'settings.assessment_config', '2026-08-08 01:43:32', 'log-6a7689c448417', 'usr-8fbcd0818a6868', NULL, 'Assessment config saved/updated: 2026/2027/First Term/All', '127.0.0.1'),
(22, 'sch-fded1718575ebd', NULL, 'settings.assessment_config', '2026-08-08 01:43:48', 'log-6a7689d42a4c6', 'usr-8fbcd0818a6868', NULL, 'Assessment config saved/updated: 2026/2027/First Term/All', '127.0.0.1'),
(23, 'sch-fded1718575ebd', NULL, 'settings.assessment_config', '2026-08-08 01:44:03', 'log-6a7689e356312', 'usr-8fbcd0818a6868', NULL, 'Assessment config saved/updated: 2026/2027/First Term/All', '127.0.0.1'),
(24, 'sch-fded1718575ebd', NULL, 'settings.assessment_config', '2026-08-08 01:44:23', 'log-6a7689f761ae9', 'usr-8fbcd0818a6868', NULL, 'Assessment config saved/updated: 2026/2027/First Term/All', '127.0.0.1'),
(25, 'sch-fded1718575ebd', NULL, 'settings.assessment_config', '2026-08-08 01:44:37', 'log-6a768a053f701', 'usr-8fbcd0818a6868', NULL, 'Assessment config saved/updated: 2026/2027/First Term/All', '127.0.0.1'),
(26, 'sch-fded1718575ebd', NULL, 'settings.assessment_config', '2026-08-08 01:44:50', 'log-6a768a12eb714', 'usr-8fbcd0818a6868', NULL, 'Assessment config saved/updated: 2026/2027/First Term/All', '127.0.0.1'),
(27, 'sch-fded1718575ebd', NULL, 'auth.login', '2026-08-08 01:51:24', 'log-6a768b9ca0ee2', 'usr-8fbcd0818a6868', 'usr-8fbcd0818a6868', 'Authenticated via khadob portal', '127.0.0.1'),
(28, 'sch-fded1718575ebd', NULL, 'student.create', '2026-08-08 01:57:23', 'log-6a768d03c31b9', 'usr-8fbcd0818a6868', 'std-7bd7e0d71cba75', 'Enrolled Oyewumi Aliyat — RC2026-001', '127.0.0.1'),
(29, 'sch-fded1718575ebd', NULL, 'student.csv_import', '2026-08-08 02:05:19', 'log-6a768edfb671d', 'usr-8fbcd0818a6868', NULL, 'Imported 206, skipped 0', '127.0.0.1'),
(30, 'sch-fded1718575ebd', NULL, 'timetable_period.add', '2026-08-08 02:10:17', 'log-6a769009e3b3d', 'usr-8fbcd0818a6868', 'ttp-eb5aef7f48c8e6', '7:30-8:00', '127.0.0.1'),
(31, 'sch-fded1718575ebd', NULL, 'timetable.auto_fill_all', '2026-08-08 02:10:21', 'log-6a76900d9eed1', 'usr-8fbcd0818a6868', NULL, '0 slots across 9 class/arm rows', '127.0.0.1'),
(32, 'sch-fded1718575ebd', NULL, 'career_advisory.save', '2026-08-08 02:17:16', 'log-6a7691ac80a0f', 'usr-8fbcd0818a6868', 'std-7bd7e0d71cba75', NULL, '127.0.0.1'),
(33, 'sch-fded1718575ebd', NULL, 'result_slip_template.select', '2026-08-08 02:17:33', 'log-6a7691bd9dff8', 'usr-8fbcd0818a6868', 'rst-5c387af504560d', NULL, '127.0.0.1'),
(34, 'sch-fded1718575ebd', NULL, 'results.batch_save', '2026-08-08 02:18:30', 'log-6a7691f642165', 'usr-8fbcd0818a6868', NULL, 'Saved 1 scores — SS3 Science / English Studies / First Term 2026/2027 (dynamic assessments)', '127.0.0.1'),
(35, 'sch-fded1718575ebd', NULL, 'settings.assessment_config', '2026-08-08 02:19:04', 'log-6a7692187c8a9', 'usr-8fbcd0818a6868', NULL, 'Assessment config saved/updated: 2026/2027/First Term/All', '127.0.0.1'),
(36, 'sch-fded1718575ebd', NULL, 'results.batch_save', '2026-08-08 02:19:36', 'log-6a76923862b4e', 'usr-8fbcd0818a6868', NULL, 'Saved 1 scores — SS3 Science / Government / First Term 2026/2027 (dynamic assessments)', '127.0.0.1'),
(37, 'sch-fded1718575ebd', NULL, 'results.batch_save', '2026-08-08 02:19:47', 'log-6a76924342dd4', 'usr-8fbcd0818a6868', NULL, 'Saved 1 scores — SS3 Science / English Studies / First Term 2026/2027 (dynamic assessments)', '127.0.0.1'),
(38, 'sch-fded1718575ebd', NULL, 'results.batch_save', '2026-08-08 02:20:01', 'log-6a7692514c352', 'usr-8fbcd0818a6868', NULL, 'Saved 1 scores — SS3 Science / English Studies / First Term 2026/2027 (dynamic assessments)', '127.0.0.1'),
(39, 'sch-fded1718575ebd', NULL, 'results.batch_save', '2026-08-08 02:20:09', 'log-6a7692592333b', 'usr-8fbcd0818a6868', NULL, 'Saved 1 scores — SS3 Science / English Studies / First Term 2026/2027 (dynamic assessments)', '127.0.0.1'),
(40, 'sch-fded1718575ebd', NULL, 'omr.generate_strips', '2026-08-08 02:28:30', 'log-6a76944e50677', 'usr-8fbcd0818a6868', 'sheet-6497a7a00db7eb', '1 strip(s) generated for SS3', '127.0.0.1'),
(41, 'sch-fded1718575ebd', NULL, 'omr.generate_strips', '2026-08-08 02:28:35', 'log-6a769453511fd', 'usr-8fbcd0818a6868', 'sheet-6497a7a00db7eb', '0 strip(s) generated for SS3', '127.0.0.1'),
(42, 'sch-fded1718575ebd', NULL, 'finance.fee_structure.save', '2026-08-08 02:31:13', 'log-6a7694f1039e7', 'usr-8fbcd0818a6868', 'fs-1d7d011df21a95', 'Fee structure saved: SS3 / First Term / 2026/2027 — ₦60000 (1 items)', '127.0.0.1'),
(43, 'sch-fded1718575ebd', NULL, 'finance.invoice.generate_bulk', '2026-08-08 02:31:20', 'log-6a7694f8b55c6', 'usr-8fbcd0818a6868', NULL, '1 invoice(s) generated for SS3 — First Term 2026/2027 (₦60000 each)', '127.0.0.1'),
(44, 'sch-fded1718575ebd', NULL, 'auth.login', '2026-08-08 18:30:22', 'log-6a7775becac66', 'usr-8fbcd0818a6868', 'usr-8fbcd0818a6868', 'Authenticated via khadob portal', '127.0.0.1');

-- --------------------------------------------------------

--
-- Table structure for table `broadcast_messages`
--

CREATE TABLE `broadcast_messages` (
  `id` int(11) NOT NULL,
  `broadcast_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'SMS',
  `recipient_group` varchar(100) NOT NULL DEFAULT 'All Parents',
  `message_text` text NOT NULL,
  `recipient_count` int(11) DEFAULT 1,
  `status` varchar(20) DEFAULT 'Sent',
  `sent_by` varchar(150) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gateway_response` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cafeteria_billing`
--

CREATE TABLE `cafeteria_billing` (
  `id` int(11) NOT NULL,
  `bill_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `plan_uuid` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Unpaid' COMMENT 'Unpaid, Paid',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cafeteria_meal_plans`
--

CREATE TABLE `cafeteria_meal_plans` (
  `id` int(11) NOT NULL,
  `plan_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `plan_type` varchar(30) NOT NULL DEFAULT 'Daily' COMMENT 'Daily, Weekly, Termly',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cafeteria_menu_items`
--

CREATE TABLE `cafeteria_menu_items` (
  `id` int(11) NOT NULL,
  `item_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `meal_type` varchar(30) NOT NULL DEFAULT 'Lunch' COMMENT 'Breakfast, Lunch, Dinner, Snack',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `career_advisory_notes`
--

CREATE TABLE `career_advisory_notes` (
  `id` int(11) NOT NULL,
  `note_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `recommended_paths` text DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `counselor_notes` text DEFAULT NULL,
  `created_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `career_advisory_notes`
--

INSERT INTO `career_advisory_notes` (`id`, `note_uuid`, `school_uuid`, `student_uuid`, `recommended_paths`, `strengths`, `counselor_notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'cad-a1b7d6eb9eae3a', 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', 'rthg', 'xghnm', 'hgnmhn', 'Zaruq Zainab', '2026-08-08 02:17:16', '2026-08-08 02:17:16');

-- --------------------------------------------------------

--
-- Table structure for table `cbt_questions`
--

CREATE TABLE `cbt_questions` (
  `id` int(11) NOT NULL,
  `question_uuid` varchar(50) NOT NULL,
  `test_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` text NOT NULL,
  `option_b` text NOT NULL,
  `option_c` text NOT NULL,
  `option_d` text NOT NULL,
  `correct_option` char(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbt_tests`
--

CREATE TABLE `cbt_tests` (
  `id` int(11) NOT NULL,
  `test_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `status` varchar(30) DEFAULT 'Pending Approval',
  `approved_by` varchar(100) DEFAULT NULL,
  `created_by` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_teacher_assignments`
--

CREATE TABLE `class_teacher_assignments` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `staff_uuid` varchar(50) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `arm_name` varchar(50) NOT NULL DEFAULT 'Gold',
  `session_name` varchar(50) NOT NULL,
  `term_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_log`
--

CREATE TABLE `email_log` (
  `id` int(11) NOT NULL,
  `email_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `recipient_group` varchar(100) DEFAULT 'All Parents',
  `to_email` varchar(200) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `recipient_count` int(11) DEFAULT 1,
  `status` varchar(20) DEFAULT 'Sent',
  `gateway_response` text DEFAULT NULL,
  `sent_by` varchar(150) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `essay_evaluations`
--

CREATE TABLE `essay_evaluations` (
  `id` int(11) NOT NULL,
  `evaluation_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) DEFAULT NULL,
  `student_name` varchar(150) NOT NULL,
  `assignment_title` varchar(200) NOT NULL,
  `essay_text` text NOT NULL,
  `marking_guide` text NOT NULL,
  `score` decimal(5,2) DEFAULT 0.00,
  `max_score` int(11) DEFAULT 100,
  `grammar_rating` varchar(50) DEFAULT 'Good',
  `coherence_rating` varchar(50) DEFAULT 'High',
  `feedback_comments` text DEFAULT NULL,
  `scanned_image_url` text DEFAULT NULL,
  `evaluated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fee_structures`
--

CREATE TABLE `fee_structures` (
  `id` int(11) NOT NULL,
  `fee_uuid` varchar(255) NOT NULL,
  `school_uuid` varchar(255) NOT NULL,
  `fee_type` varchar(255) DEFAULT NULL,
  `class_name` varchar(255) DEFAULT NULL,
  `session_name` varchar(255) DEFAULT NULL,
  `term_name` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `items_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of {name, amount} — the itemized fee list' CHECK (json_valid(`items_json`)),
  `total_amount` decimal(10,2) DEFAULT NULL COMMENT 'Cached sum of items_json, kept in sync on save',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fee_structures`
--

INSERT INTO `fee_structures` (`id`, `fee_uuid`, `school_uuid`, `fee_type`, `class_name`, `session_name`, `term_name`, `amount`, `description`, `items_json`, `total_amount`, `created_at`) VALUES
(1, 'fs-1d7d011df21a95', 'sch-fded1718575ebd', 'Itemized Fee Structure', 'SS3', '2026/2027', 'First Term', 60000.00, NULL, '[{\"name\":\"Tuition\",\"amount\":60000}]', 60000.00, '2026-08-08 02:31:13');

-- --------------------------------------------------------

--
-- Table structure for table `flutterwave_settings`
--

CREATE TABLE `flutterwave_settings` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `public_key_enc` text DEFAULT NULL,
  `secret_key_enc` text DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gate_attendance_logs`
--

CREATE TABLE `gate_attendance_logs` (
  `id` int(11) NOT NULL,
  `log_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `person_type` varchar(20) DEFAULT 'Student',
  `person_uuid` varchar(50) NOT NULL,
  `person_name` varchar(150) NOT NULL,
  `check_type` varchar(20) DEFAULT 'Check-In',
  `qr_date` date DEFAULT NULL COMMENT 'Date encoded in the scanned QR pass',
  `status` varchar(20) NOT NULL DEFAULT 'Valid' COMMENT 'Valid | Expired | Invalid',
  `scanned_by` varchar(50) DEFAULT NULL COMMENT 'user_uuid of the staff member operating the scanner',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `healthcare_records`
--

CREATE TABLE `healthcare_records` (
  `id` int(11) NOT NULL,
  `record_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `person_type` varchar(20) NOT NULL,
  `person_uuid` varchar(50) NOT NULL,
  `person_name` varchar(100) NOT NULL,
  `visit_date` date NOT NULL,
  `symptoms` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `attending_staff` varchar(100) DEFAULT 'School Nurse',
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hostels`
--

CREATE TABLE `hostels` (
  `id` int(11) NOT NULL,
  `hostel_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `gender` varchar(20) NOT NULL DEFAULT 'Mixed',
  `capacity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hostel_allocations`
--

CREATE TABLE `hostel_allocations` (
  `id` int(11) NOT NULL,
  `allocation_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `hostel_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `room_number` varchar(30) DEFAULT NULL,
  `bed_number` varchar(30) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `allocated_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hr_employment_letters_issued`
--

CREATE TABLE `hr_employment_letters_issued` (
  `id` int(11) NOT NULL,
  `letter_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `staff_uuid` varchar(50) NOT NULL,
  `template_uuid` varchar(50) DEFAULT NULL,
  `rendered_html` longtext NOT NULL,
  `issued_by` varchar(150) DEFAULT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hr_employment_letter_templates`
--

CREATE TABLE `hr_employment_letter_templates` (
  `id` int(11) NOT NULL,
  `template_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL DEFAULT 'Letter of Employment',
  `body_html` longtext NOT NULL,
  `is_default` tinyint(4) DEFAULT 0,
  `updated_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_plans`
--

CREATE TABLE `lesson_plans` (
  `id` int(11) NOT NULL,
  `plan_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `teacher_uuid` varchar(50) DEFAULT NULL,
  `teacher_name` varchar(150) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `topic` varchar(200) NOT NULL,
  `week_number` int(11) DEFAULT 1,
  `behavioral_objectives` text NOT NULL,
  `lesson_notes` text NOT NULL,
  `exercises` text NOT NULL,
  `homework` text NOT NULL,
  `status` varchar(30) DEFAULT 'Pending Review',
  `reviewer_feedback` text DEFAULT NULL,
  `reviewed_by` varchar(150) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `library_books`
--

CREATE TABLE `library_books` (
  `id` int(11) NOT NULL,
  `book_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(150) DEFAULT NULL,
  `isbn` varchar(50) DEFAULT NULL,
  `category` varchar(80) DEFAULT 'General',
  `total_copies` int(11) NOT NULL DEFAULT 1,
  `available_copies` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `library_checkouts`
--

CREATE TABLE `library_checkouts` (
  `id` int(11) NOT NULL,
  `checkout_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `book_uuid` varchar(50) NOT NULL,
  `borrower_type` varchar(20) NOT NULL DEFAULT 'Student',
  `borrower_uuid` varchar(50) DEFAULT NULL,
  `borrower_name` varchar(150) NOT NULL,
  `checkout_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Borrowed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `notification_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) DEFAULT NULL,
  `recipient_uuid` varchar(50) DEFAULT NULL COMMENT 'user_uuid; NULL = broadcast to all in school_uuid/role',
  `recipient_role` varchar(50) DEFAULT NULL COMMENT 'used when recipient_uuid is NULL to target a role',
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(30) DEFAULT 'info' COMMENT 'info, success, warning, alert',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_log`
--

CREATE TABLE `notification_log` (
  `id` int(11) NOT NULL,
  `log_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `category` varchar(20) NOT NULL,
  `trigger_key` varchar(30) DEFAULT NULL,
  `recipient_name` varchar(150) DEFAULT NULL,
  `recipient_phone` varchar(30) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Sent',
  `gateway_response` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_templates`
--

CREATE TABLE `notification_templates` (
  `id` int(11) NOT NULL,
  `template_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `category` varchar(20) NOT NULL,
  `audience` varchar(20) DEFAULT NULL,
  `trigger_key` varchar(30) DEFAULT NULL,
  `slot_index` tinyint(4) DEFAULT 1,
  `title` varchar(150) NOT NULL DEFAULT 'Untitled',
  `body` text NOT NULL,
  `is_active` tinyint(4) DEFAULT 0,
  `updated_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `omr_answer_keys`
--

CREATE TABLE `omr_answer_keys` (
  `id` int(11) NOT NULL,
  `key_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `sheet_uuid` varchar(50) NOT NULL,
  `question_number` int(11) NOT NULL,
  `correct_option` char(1) NOT NULL,
  `marks` decimal(5,2) DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `omr_evaluations`
--

CREATE TABLE `omr_evaluations` (
  `id` int(11) NOT NULL,
  `evaluation_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) DEFAULT NULL,
  `student_name` varchar(150) DEFAULT NULL,
  `exam_title` varchar(150) NOT NULL,
  `total_questions` int(11) DEFAULT 20,
  `correct_count` int(11) DEFAULT 0,
  `wrong_count` int(11) DEFAULT 0,
  `percentage_score` decimal(5,2) DEFAULT 0.00,
  `scanned_image_url` text DEFAULT NULL,
  `detected_answers_json` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Evaluated',
  `evaluated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sheet_student_uuid` varchar(50) DEFAULT NULL,
  `scan_confidence` varchar(20) DEFAULT NULL,
  `flagged_questions_json` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `omr_sheets`
--

CREATE TABLE `omr_sheets` (
  `id` int(11) NOT NULL,
  `sheet_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `exam_title` varchar(150) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `total_questions` int(11) DEFAULT 50,
  `generated_by` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `omr_sheets`
--

INSERT INTO `omr_sheets` (`id`, `sheet_uuid`, `school_uuid`, `exam_title`, `class_name`, `total_questions`, `generated_by`, `created_at`) VALUES
(1, 'sheet-6cbd286d986d34', 'sch-fded1718575ebd', 'f', 'JSS1', 20, 'Zaruq Zainab', '2026-08-08 02:28:13'),
(2, 'sheet-6497a7a00db7eb', 'sch-fded1718575ebd', 'f', 'SS3', 20, 'Zaruq Zainab', '2026-08-08 02:28:25');

-- --------------------------------------------------------

--
-- Table structure for table `omr_sheet_students`
--

CREATE TABLE `omr_sheet_students` (
  `id` int(11) NOT NULL,
  `sheet_student_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `sheet_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `roll_number` varchar(50) DEFAULT NULL,
  `serial_code` varchar(12) NOT NULL,
  `scanned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `omr_sheet_students`
--

INSERT INTO `omr_sheet_students` (`id`, `sheet_student_uuid`, `school_uuid`, `sheet_uuid`, `student_uuid`, `student_name`, `roll_number`, `serial_code`, `scanned_at`, `created_at`) VALUES
(1, 'sts-d4701df6cba7de', 'sch-fded1718575ebd', 'sheet-6497a7a00db7eb', 'std-7bd7e0d71cba75', 'Oyewumi Aliyat', 'RC2026-001', '000001', NULL, '2026-08-08 02:28:30');

-- --------------------------------------------------------

--
-- Table structure for table `onboarding_requests`
--

CREATE TABLE `onboarding_requests` (
  `id` int(11) NOT NULL,
  `school_name` varchar(150) NOT NULL,
  `subdomain` varchar(100) NOT NULL,
  `contact_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `student_count` int(11) NOT NULL,
  `plan` varchar(20) DEFAULT 'Standard',
  `billing_cycle` varchar(20) DEFAULT 'Monthly',
  `applicant_role` varchar(50) DEFAULT 'School Admin',
  `status` varchar(20) DEFAULT 'Pending',
  `request_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `onboarding_requests`
--

INSERT INTO `onboarding_requests` (`id`, `school_name`, `subdomain`, `contact_name`, `email`, `phone`, `student_count`, `plan`, `billing_cycle`, `applicant_role`, `status`, `request_date`) VALUES
(1, 'Khadob College Akure', 'khadob', 'Zaruq Zainab', 'zaruqzainab@gmail.com', '2348121491727', 350, 'Pro', 'Monthly', 'School Admin', 'Approved', '2026-08-08');

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `parent_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `address` text DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `employer` varchar(100) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `linked_student_uuids` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_of_birth` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parent_teacher_appointments`
--

CREATE TABLE `parent_teacher_appointments` (
  `id` int(11) NOT NULL,
  `appointment_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `parent_uuid` varchar(50) NOT NULL,
  `parent_name` varchar(150) NOT NULL,
  `teacher_uuid` varchar(50) NOT NULL,
  `teacher_name` varchar(150) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `meeting_date` date NOT NULL,
  `meeting_time` varchar(50) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `status` varchar(30) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parent_teacher_messages`
--

CREATE TABLE `parent_teacher_messages` (
  `id` int(11) NOT NULL,
  `message_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `sender_uuid` varchar(50) NOT NULL,
  `sender_name` varchar(150) NOT NULL,
  `sender_role` varchar(30) NOT NULL,
  `receiver_uuid` varchar(50) NOT NULL,
  `receiver_name` varchar(150) NOT NULL,
  `message_text` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_requests`
--

CREATE TABLE `payment_requests` (
  `id` int(11) NOT NULL,
  `request_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `parent_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `admin_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
  `txn_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `invoice_uuid` varchar(50) DEFAULT NULL,
  `student_uuid` varchar(50) DEFAULT NULL,
  `parent_uuid` varchar(50) DEFAULT NULL,
  `reference` varchar(100) NOT NULL COMMENT 'Paystack transaction reference',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `status` varchar(20) NOT NULL DEFAULT 'Pending' COMMENT 'Pending, Success, Failed',
  `gateway` varchar(30) NOT NULL DEFAULT 'Paystack',
  `gateway_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_feature_catalog`
--

CREATE TABLE `platform_feature_catalog` (
  `id` int(11) NOT NULL,
  `feature_key` varchar(100) NOT NULL COMMENT 'matches dashboard.php section/tab key',
  `feature_label` varchar(150) NOT NULL,
  `category` varchar(50) DEFAULT 'General',
  `is_core` tinyint(4) DEFAULT 0 COMMENT '1 = always on, cannot be disabled (e.g. Dashboard Home)',
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_feature_catalog`
--

INSERT INTO `platform_feature_catalog` (`id`, `feature_key`, `feature_label`, `category`, `is_core`, `sort_order`) VALUES
(1, 'dashboard_home', 'Dashboard Overview', 'General', 1, 1),
(3, 'staff', 'Staff & HR Directory', 'Academics', 0, 3),
(4, 'parents', 'Parent Records', 'Academics', 0, 4),
(5, 'attendance', 'Attendance Log', 'Academics', 0, 5),
(6, 'timetable', 'Timetable', 'Academics', 0, 6),
(7, 'lesson_plans', 'Lesson Plans & Schemes', 'Academics', 0, 7),
(8, 'results', 'Results Entry', 'Academics', 0, 8),
(9, 'report_cards', 'Report Cards', 'Academics', 0, 9),
(10, 'cbt', 'CBT Quizzes', 'Academics', 0, 10),
(11, 'omr', 'OMR Sheets & Marking', 'Academics', 0, 11),
(12, 'library', 'Library Management', 'Facilities', 0, 12),
(13, 'inventory', 'Inventory / Store', 'Facilities', 0, 13),
(14, 'hostel', 'Hostel & Dorms', 'Facilities', 0, 14),
(15, 'transport', 'Transport & Logistics', 'Facilities', 0, 15),
(16, 'finance', 'Finance & Invoicing', 'Finance', 0, 16),
(17, 'healthcare', 'Healthcare Records', 'Welfare', 0, 17),
(18, 'disciplinary', 'Disciplinary Records', 'Welfare', 0, 18),
(19, 'news_notices', 'Notice Board / News', 'Communication', 0, 19),
(20, 'sms_broadcast', 'SMS & Broadcast Centre', 'Communication', 0, 20),
(21, 'email_centre', 'Email Centre', 'Communication', 0, 21),
(24, 'admissions', 'Online Admissions', 'Academics', 0, 24),
(25, 'alumni', 'Alumni Network', 'Academics', 0, 25),
(26, 'settings', 'School Settings & Theme', 'Settings', 0, 26),
(37, 'broadsheet', 'Broadsheet View', 'Academics', 0, 82),
(38, 'affective', 'Affective Domain Ratings', 'Academics', 0, 83),
(39, 'psychomotor', 'Psychomotor Domain Ratings', 'Academics', 0, 84),
(40, 'teacher_comment', 'Teacher Comment on Report', 'Academics', 0, 85),
(41, 'staff_portal', 'Staff Portal Access', 'General', 1, 0),
(42, 'roster', 'Student Management', 'Academics', 0, 2),
(43, 'in_app_notifications', 'In-App Notifications', 'Communication', 0, 27),
(44, 'essay_ocr', 'AI Essay & OCR', 'Settings', 0, 28),
(45, 'consultations', 'Parent-Teacher Chat', 'Settings', 0, 29),
(46, 'primary_ops', 'Sessions & Classes', 'Academics', 0, 31),
(47, 'id_cards', 'ID Card Designer', 'Academics', 0, 3),
(48, 'gate_scanner', 'Gate QR Check-In', 'Academics', 0, 8),
(49, 'cafeteria_meals', 'Cafeteria & Meals', 'Facilities', 0, 18),
(50, 'assignments', 'Assignments', 'Academics', 0, 11),
(51, 'condition_of_service', 'Condition of Service', 'Academics', 0, 5),
(52, 'career_advisory', 'Career Advisory', 'Academics', 0, 30),
(53, 'student_history', 'Student History Timeline', 'Academics', 0, 31),
(54, 'staff', 'Staff & HR Directory', 'Academics', 0, 6),
(55, 'staff_attendance', 'Staff Attendance', 'Academics', 0, 7),
(56, 'timetable', 'Timetable', 'Academics', 0, 8),
(57, 'virtual_classroom', 'Virtual Classroom', 'Academics', 0, 9),
(58, 'primary_ops', 'Sessions & Classes', 'Academics', 0, 2),
(59, 'results', 'Results Entry', 'Academics', 0, 10),
(60, 'report_cards', 'Report Cards', 'Academics', 0, 15),
(61, 'transcripts', 'Transcript Generator', 'Academics', 0, 16),
(62, 'annual_report', 'Annual Report', 'Academics', 0, 17),
(63, 'result_slip_templates', 'Result Slip Templates', 'Academics', 0, 18),
(64, 'question_bank', 'Question Bank', 'Academics', 0, 19),
(65, 'transport', 'Transport & Logistics', 'Facilities', 0, 20),
(66, 'sms_broadcast', 'SMS & Broadcast Centre', 'Communication', 0, 21),
(67, 'consultations', 'Parent‑Teacher Chat', 'Communication', 0, 22),
(68, 'settings', 'School Settings & Theme', 'Settings', 0, 23);

-- --------------------------------------------------------

--
-- Table structure for table `pricing_packages`
--

CREATE TABLE `pricing_packages` (
  `id` int(11) NOT NULL,
  `tier_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `monthly_price` decimal(10,2) NOT NULL,
  `yearly_price` decimal(10,2) NOT NULL,
  `max_students` int(11) NOT NULL,
  `features_json` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pricing_packages`
--

INSERT INTO `pricing_packages` (`id`, `tier_name`, `description`, `monthly_price`, `yearly_price`, `max_students`, `features_json`) VALUES
(1, 'Basic', 'Essential OS for small preparatory schools', 3000.00, 33000.00, 100, '[\"Dashboard Overview\",\"Student Management\",\"Staff & HR Directory\",\"Results Entry\",\"Report Cards\",\"Parent-Teacher Chat\",\"Sessions & Classes\"]'),
(2, 'Standard', 'Full administrative suite for growing academies', 5000.00, 550000.00, 500, '[\"Dashboard Overview\",\"Student Management\",\"Staff & HR Directory\",\"Parent Records\",\"Results Entry\",\"Report Cards\",\"CBT Quizzes\",\"Finance & Invoicing\",\"In-App Notifications\",\"School Settings & Theme\"]'),
(3, 'Pro', 'Enterprise cloud solution for large multi-campus schools', 10000.00, 100000.00, 0, '[\"Staff Portal Access\",\"Dashboard Overview\",\"Student Management\",\"Staff & HR Directory\",\"ID Card Designer\",\"Parent Records\",\"Attendance Log\",\"Condition of Service\",\"Timetable\",\"Lesson Plans & Schemes\",\"Results Entry\",\"Gate QR Check-In\",\"Report Cards\",\"CBT Quizzes\",\"OMR Sheets & Marking\",\"Assignments\",\"Library Management\",\"Inventory \\/ Store\",\"Hostel & Dorms\",\"Transport & Logistics\",\"Finance & Invoicing\",\"Healthcare Records\",\"Disciplinary Records\",\"Cafeteria & Meals\",\"Notice Board \\/ News\",\"SMS & Broadcast Centre\",\"Email Centre\",\"In-App Notifications\",\"AI Essay & OCR\",\"Online Admissions\",\"Alumni Network\",\"School Settings & Theme\",\"In-App Notifications\",\"AI Essay & OCR\",\"Parent-Teacher Chat\",\"Sessions & Classes\"]');

-- --------------------------------------------------------

--
-- Table structure for table `printed_exam_papers`
--

CREATE TABLE `printed_exam_papers` (
  `id` int(11) NOT NULL,
  `paper_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `question_uuids` text NOT NULL,
  `created_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promotion_log`
--

CREATE TABLE `promotion_log` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `session_name` varchar(50) NOT NULL,
  `from_class` varchar(50) NOT NULL,
  `to_class` varchar(50) NOT NULL,
  `promoted_count` int(11) NOT NULL DEFAULT 0,
  `promoted_by` varchar(150) DEFAULT NULL,
  `promoted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `public_applications`
--

CREATE TABLE `public_applications` (
  `id` int(11) NOT NULL,
  `app_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `applicant_type` varchar(20) NOT NULL,
  `applicant_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `place_of_birth` varchar(100) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `state_of_origin` varchar(50) DEFAULT NULL,
  `lga` varchar(50) DEFAULT NULL,
  `marital_status` varchar(20) DEFAULT NULL,
  `religion` varchar(30) DEFAULT NULL,
  `applied_class_or_role` varchar(100) NOT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_phone` varchar(50) DEFAULT NULL,
  `parent_email` varchar(150) DEFAULT NULL,
  `qualification` varchar(150) DEFAULT NULL,
  `healthcare_json` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `documents_json` text DEFAULT NULL COMMENT 'array of {label,path} for uploaded certificates/ID/birth cert etc',
  `status` varchar(20) DEFAULT 'Pending',
  `reviewed_by` varchar(150) DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `public_applications`
--

INSERT INTO `public_applications` (`id`, `app_uuid`, `school_uuid`, `applicant_type`, `applicant_name`, `email`, `phone`, `date_of_birth`, `gender`, `place_of_birth`, `nationality`, `state_of_origin`, `lga`, `marital_status`, `religion`, `applied_class_or_role`, `parent_name`, `parent_phone`, `parent_email`, `qualification`, `healthcare_json`, `photo_path`, `documents_json`, `status`, `reviewed_by`, `review_note`, `reviewed_at`, `created_at`) VALUES
(1, 'app-stu-6a768ad283a8e', 'sch-fded1718575ebd', 'student', 'David Benson', 'Davidbenson@gmail.com', '09022445562', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'JSS1', 'Chief Robert Benson', '09022334455', NULL, NULL, '{\"blood_group\":\"O+\",\"genotype\":\"AA\",\"allergies\":\"none\",\"medical_conditions\":\"none\",\"emergency_contact\":\"Dr. Benson - 09022334455\",\"physician\":\"None\"}', 'uploads\\applications\\student_9049b97967cb.webp', '[{\"label\":\"Previous Term Result\",\"path\":\"uploads\\/applications\\/doc_6a768ad281d8f.png\",\"type\":\"image\\/png\"}]', 'Rejected', 'Zaruq Zainab', '', '2026-08-08 01:53:31', '2026-08-08 01:48:02');

-- --------------------------------------------------------

--
-- Table structure for table `question_bank`
--

CREATE TABLE `question_bank` (
  `id` int(11) NOT NULL,
  `question_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `question_type` varchar(20) NOT NULL DEFAULT 'objective',
  `question_text` text NOT NULL,
  `option_a` varchar(500) DEFAULT NULL,
  `option_b` varchar(500) DEFAULT NULL,
  `option_c` varchar(500) DEFAULT NULL,
  `option_d` varchar(500) DEFAULT NULL,
  `correct_option` varchar(1) DEFAULT NULL,
  `year` varchar(10) DEFAULT NULL,
  `topic` varchar(150) DEFAULT NULL,
  `for_printed_exam` tinyint(4) DEFAULT 0,
  `created_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_cards`
--

CREATE TABLE `report_cards` (
  `id` int(11) NOT NULL,
  `report_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `session_name` varchar(50) NOT NULL,
  `term_name` varchar(50) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `arm_name` varchar(50) DEFAULT 'Gold',
  `grades_json` text NOT NULL,
  `teacher_comment` text DEFAULT NULL,
  `teacher_comment_by` varchar(50) DEFAULT NULL COMMENT 'staff_uuid of comment author',
  `comment_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = locked after approval, staff cannot overwrite',
  `principal_comment` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Pending Approval',
  `approved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `id` int(11) NOT NULL,
  `result_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `session_name` varchar(50) NOT NULL,
  `term_name` varchar(50) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `arm_name` varchar(50) DEFAULT 'Gold',
  `subject_name` varchar(100) NOT NULL,
  `ca1_score` decimal(5,2) DEFAULT 0.00,
  `ca2_score` decimal(5,2) DEFAULT 0.00,
  `exam_score` decimal(5,2) DEFAULT 0.00,
  `total_score` decimal(5,2) DEFAULT 0.00,
  `grade` varchar(5) DEFAULT NULL,
  `subject_teacher_remark` varchar(200) DEFAULT NULL,
  `entered_by` varchar(150) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `assessment_config_uuid` varchar(50) DEFAULT NULL,
  `assessment_template_uuid` varchar(50) DEFAULT NULL,
  `score` decimal(6,2) DEFAULT 0.00,
  `max_score` decimal(6,2) DEFAULT 0.00,
  `is_active` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`id`, `result_uuid`, `school_uuid`, `student_uuid`, `session_name`, `term_name`, `class_name`, `arm_name`, `subject_name`, `ca1_score`, `ca2_score`, `exam_score`, `total_score`, `grade`, `subject_teacher_remark`, `entered_by`, `updated_at`, `assessment_config_uuid`, `assessment_template_uuid`, `score`, `max_score`, `is_active`) VALUES
(1, 'res-20dc2a03b88d50', 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'Science', 'English Studies', 0.00, 0.00, 0.00, 100.00, 'A1', '', 'Zaruq Zainab', '2026-08-08 02:18:30', NULL, NULL, 0.00, 0.00, 1),
(2, 'res-6abc201d463461', 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'Science', 'Government', 0.00, 0.00, 0.00, 85.00, 'A1', '', 'Zaruq Zainab', '2026-08-08 02:19:36', NULL, NULL, 0.00, 0.00, 1),
(3, 'res-0f535e953156d4', 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'Science', 'English Studies', 0.00, 0.00, 0.00, 80.00, 'A1', '', 'Zaruq Zainab', '2026-08-08 02:19:47', NULL, NULL, 0.00, 0.00, 1),
(4, 'res-ffd81d3120a813', 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'Science', 'English Studies', 0.00, 0.00, 0.00, 90.00, 'A1', '', 'Zaruq Zainab', '2026-08-08 02:20:01', NULL, NULL, 0.00, 0.00, 1),
(5, 'res-c5a10e43ab80b3', 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'Science', 'English Studies', 0.00, 0.00, 0.00, 90.00, 'A1', '', 'Zaruq Zainab', '2026-08-08 02:20:09', NULL, NULL, 0.00, 0.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `result_assessment_scores`
--

CREATE TABLE `result_assessment_scores` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `session_name` varchar(50) NOT NULL,
  `term_name` varchar(50) NOT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `subject_name` varchar(100) NOT NULL,
  `config_uuid` varchar(50) NOT NULL COMMENT 'assessment_configurations.config_uuid',
  `template_uuid` varchar(50) NOT NULL COMMENT 'assessment_templates.template_uuid',
  `score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `max_score` decimal(6,2) NOT NULL DEFAULT 100.00,
  `entered_by` varchar(150) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `result_assessment_scores`
--

INSERT INTO `result_assessment_scores` (`id`, `school_uuid`, `student_uuid`, `session_name`, `term_name`, `class_name`, `subject_name`, `config_uuid`, `template_uuid`, `score`, `max_score`, `entered_by`, `updated_at`) VALUES
(1, 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'English Studies', 'tpl_4b327229055d222077b5', 'tpl_4b327229055d222077b5', 10.00, 10.00, 'Zaruq Zainab', '2026-08-08 02:18:30'),
(2, 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'English Studies', 'tpl_da8131136a4d6e9d3a6d', 'tpl_da8131136a4d6e9d3a6d', 10.00, 10.00, 'Zaruq Zainab', '2026-08-08 02:18:30'),
(3, 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'English Studies', 'tpl_b0103bb4f3ddae0bfcad', 'tpl_b0103bb4f3ddae0bfcad', 10.00, 10.00, 'Zaruq Zainab', '2026-08-08 02:18:30'),
(4, 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'English Studies', 'tpl_f1da3b3ff90a4fb60e71', 'tpl_f1da3b3ff90a4fb60e71', 10.00, 10.00, 'Zaruq Zainab', '2026-08-08 02:18:30'),
(5, 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'English Studies', 'tpl_463150f80983c20e220f', 'tpl_463150f80983c20e220f', 50.00, 60.00, 'Zaruq Zainab', '2026-08-08 02:20:01'),
(6, 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'Government', 'tpl_4b327229055d222077b5', 'tpl_4b327229055d222077b5', 10.00, 10.00, 'Zaruq Zainab', '2026-08-08 02:19:36'),
(7, 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'Government', 'tpl_da8131136a4d6e9d3a6d', 'tpl_da8131136a4d6e9d3a6d', 10.00, 10.00, 'Zaruq Zainab', '2026-08-08 02:19:36'),
(8, 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'Government', 'tpl_b0103bb4f3ddae0bfcad', 'tpl_b0103bb4f3ddae0bfcad', 10.00, 10.00, 'Zaruq Zainab', '2026-08-08 02:19:36'),
(9, 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'Government', 'tpl_f1da3b3ff90a4fb60e71', 'tpl_f1da3b3ff90a4fb60e71', 10.00, 10.00, 'Zaruq Zainab', '2026-08-08 02:19:36'),
(10, 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', '2026/2027', 'First Term', 'SS3', 'Government', 'tpl_463150f80983c20e220f', 'tpl_463150f80983c20e220f', 45.00, 60.00, 'Zaruq Zainab', '2026-08-08 02:19:36');

-- --------------------------------------------------------

--
-- Table structure for table `result_slip_templates`
--

CREATE TABLE `result_slip_templates` (
  `id` int(11) NOT NULL,
  `template_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `layout_json` longtext NOT NULL,
  `is_platform_default` tinyint(4) DEFAULT 0,
  `created_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `result_slip_templates`
--

INSERT INTO `result_slip_templates` (`id`, `template_uuid`, `school_uuid`, `name`, `layout_json`, `is_platform_default`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'rst-5c387af504560d', NULL, 'sms', '{\"page\":{\"background_image\":null,\"background_color\":\"#ffffff\"},\"elements\":[{\"id\":\"el_8scxmvdh\",\"key\":\"school_logo\",\"label\":\"School Logo\",\"x\":43,\"y\":9,\"w\":28,\"h\":28,\"z\":1,\"fontFamily\":\"Georgia, serif\",\"fontSize\":11,\"color\":\"#111111\",\"bold\":false,\"italic\":false,\"align\":\"left\"},{\"id\":\"el_t6dmx37o\",\"key\":\"school_name\",\"label\":\"School Name & Address\",\"x\":75.25,\"y\":9.69,\"w\":96,\"h\":27,\"z\":2,\"fontFamily\":\"Georgia, serif\",\"fontSize\":18,\"color\":\"#111111\",\"bold\":true,\"italic\":false,\"align\":\"left\"},{\"id\":\"el_lres6wmt\",\"key\":\"student_photo\",\"label\":\"Student Photo\",\"x\":171,\"y\":40,\"w\":26,\"h\":32,\"z\":3,\"fontFamily\":\"Georgia, serif\",\"fontSize\":11,\"color\":\"#111111\",\"bold\":false,\"italic\":false,\"align\":\"left\"},{\"id\":\"el_coelxuiq\",\"key\":\"student_name\",\"label\":\"Student Name\",\"x\":9,\"y\":42,\"w\":60,\"h\":10,\"z\":4,\"fontFamily\":\"Georgia, serif\",\"fontSize\":14,\"color\":\"#111111\",\"bold\":true,\"italic\":false,\"align\":\"left\"},{\"id\":\"el_6buvxg7i\",\"key\":\"admission_no\",\"label\":\"Admission Number\",\"x\":9,\"y\":56,\"w\":60,\"h\":10,\"z\":5,\"fontFamily\":\"Georgia, serif\",\"fontSize\":11,\"color\":\"#111111\",\"bold\":false,\"italic\":false,\"align\":\"left\"},{\"id\":\"el_tkku3rwv\",\"key\":\"class_arm\",\"label\":\"Class & Arm\",\"x\":9,\"y\":70,\"w\":60,\"h\":10,\"z\":6,\"fontFamily\":\"Georgia, serif\",\"fontSize\":11,\"color\":\"#111111\",\"bold\":false,\"italic\":false,\"align\":\"left\"},{\"id\":\"el_yiyhiz2c\",\"key\":\"session_term\",\"label\":\"Session & Term\",\"x\":70,\"y\":42,\"w\":60,\"h\":10,\"z\":7,\"fontFamily\":\"Georgia, serif\",\"fontSize\":11,\"color\":\"#111111\",\"bold\":false,\"italic\":false,\"align\":\"left\"},{\"id\":\"el_jafa5j6a\",\"key\":\"total_average\",\"label\":\"Total & Average\",\"x\":70,\"y\":56,\"w\":60,\"h\":10,\"z\":8,\"fontFamily\":\"Georgia, serif\",\"fontSize\":11,\"color\":\"#111111\",\"bold\":false,\"italic\":false,\"align\":\"left\"},{\"id\":\"el_cghvi8c4\",\"key\":\"position\",\"label\":\"Class Position\",\"x\":70,\"y\":70,\"w\":60,\"h\":10,\"z\":9,\"fontFamily\":\"Georgia, serif\",\"fontSize\":11,\"color\":\"#111111\",\"bold\":false,\"italic\":false,\"align\":\"left\"},{\"id\":\"el_a2di6vhl\",\"key\":\"attendance_summary\",\"label\":\"Attendance Summary\",\"x\":131,\"y\":42,\"w\":60,\"h\":10,\"z\":10,\"fontFamily\":\"Georgia, serif\",\"fontSize\":11,\"color\":\"#111111\",\"bold\":false,\"italic\":false,\"align\":\"left\"},{\"id\":\"el_uzvqy72e\",\"key\":\"next_term_begins\",\"label\":\"Next Term Resumption Date\",\"x\":131,\"y\":56,\"w\":60,\"h\":10,\"z\":11,\"fontFamily\":\"Georgia, serif\",\"fontSize\":11,\"color\":\"#111111\",\"bold\":false,\"italic\":false,\"align\":\"left\"}]}', 0, 'Precious Philip Godwin', '2026-08-07 08:00:25', '2026-08-07 08:07:27');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `feature_key` varchar(100) NOT NULL,
  `access_level` enum('hide','read','write','full') NOT NULL DEFAULT 'hide' COMMENT 'Default access level for this role at this school, used when no staff-specific override exists.',
  `is_enabled` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schema_versions`
--

CREATE TABLE `schema_versions` (
  `id` int(11) NOT NULL,
  `version_id` int(11) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schema_versions`
--

INSERT INTO `schema_versions` (`id`, `version_id`, `applied_at`) VALUES
(1, 2, '2026-08-03 12:41:49'),
(2, 4, '2026-08-03 21:11:08'),
(3, 8, '2026-08-04 08:06:01'),
(4, 9, '2026-08-04 18:40:51'),
(5, 10, '2026-08-04 23:44:35'),
(6, 11, '2026-08-05 07:33:16');

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `subdomain` varchar(100) NOT NULL,
  `custom_domain` varchar(150) DEFAULT NULL,
  `admin_email` varchar(150) NOT NULL,
  `school_admin_uuid` varchar(50) DEFAULT NULL COMMENT 'user_uuid of the school admin (set by platform manager)',
  `status` varchar(20) DEFAULT 'Active',
  `plan` varchar(20) DEFAULT 'Standard',
  `billing_cycle` varchar(20) DEFAULT 'Monthly',
  `student_count` int(11) DEFAULT 150,
  `monthly_fee` decimal(10,2) DEFAULT 65000.00,
  `theme_color` varchar(10) DEFAULT '#4F46E5',
  `logo_path` varchar(255) DEFAULT NULL,
  `letterhead_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `theme_mode` varchar(10) DEFAULT 'dark',
  `condition_of_service_text` text DEFAULT NULL,
  `school_policy_text` text DEFAULT NULL,
  `gate_qr_secret` varchar(64) DEFAULT NULL COMMENT 'HMAC key for signing gate QR passes; auto-generated on first use',
  `report_card_config_json` text DEFAULT NULL,
  `manual_billing_override_json` text DEFAULT NULL,
  `feature_overrides_json` text DEFAULT NULL,
  `created_date` date NOT NULL,
  `gate_qr_mode` varchar(10) NOT NULL DEFAULT 'daily',
  `active_result_slip_template_uuid` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`id`, `school_uuid`, `name`, `subdomain`, `custom_domain`, `admin_email`, `school_admin_uuid`, `status`, `plan`, `billing_cycle`, `student_count`, `monthly_fee`, `theme_color`, `logo_path`, `letterhead_path`, `signature_path`, `theme_mode`, `condition_of_service_text`, `school_policy_text`, `gate_qr_secret`, `report_card_config_json`, `manual_billing_override_json`, `feature_overrides_json`, `created_date`, `gate_qr_mode`, `active_result_slip_template_uuid`) VALUES
(1, 'sch-fded1718575ebd', 'Khadob College Akure', 'khadob', NULL, 'zaruqzainab@gmail.com', NULL, 'Active', 'Pro', 'Monthly', 150, 65000.00, '#f4e68a', 'uploads\\school_logos\\logo_sch-fded1718575ebd_b2bb08259d74.webp', NULL, NULL, 'dark', 'KHADOB COLLEGE, AKURE\r\nCONDITIONS OF SERVICE FOR STAFF\r\n\r\nEffective Date: January 2026\r\nVersion: 1.0\r\n\r\nTABLE OF CONTENTS\r\n\r\nPreamble\r\n\r\nGeneral Provisions\r\n\r\nAppointment and Probation\r\n\r\nTerms of Employment\r\n\r\nRemuneration and Benefits\r\n\r\nWorking Hours and Leave\r\n\r\nCode of Conduct\r\n\r\nPerformance Management\r\n\r\nGrievance Procedure\r\n\r\nDisciplinary Procedure\r\n\r\nTermination of Employment\r\n\r\nResignation\r\n\r\nConfidentiality and Intellectual Property\r\n\r\nHealth and Safety\r\n\r\nStaff Development and Training\r\n\r\nRetirement and Pension\r\n\r\nMiscellaneous Provisions\r\n\r\nPREAMBLE\r\n\r\n1.1 These Conditions of Service (hereinafter referred to as \"the Conditions\") govern the employment relationship between KHADOB College, Akure (hereinafter referred to as \"the College\") and its employees (hereinafter referred to as \"Staff\").\r\n\r\n1.2 The College is committed to maintaining a professional, supportive, and productive work environment that upholds the highest standards of educational excellence, integrity, and mutual respect.\r\n\r\n1.3 All staff members are expected to familiarize themselves with these Conditions and comply with all provisions herein.\r\n\r\n1.4 These Conditions supersede all previous conditions of service and shall be reviewed periodically by the College Management.\r\n\r\nGENERAL PROVISIONS\r\n\r\n2.1 Interpretation\r\n\"College\" means KHADOB College, Akure.\r\n\"Staff\" means any person employed by the College on a full-time, part-time, or contract basis.\r\n\"Management\" means the Principal/Head of School and the Governing Board.\r\n\"Academic Staff\" means teachers and instructors engaged in teaching and academic activities.\r\n\"Non-Academic Staff\" means administrative, technical, and support staff.\r\n\r\n2.2 Effective Date\r\nThese Conditions shall take effect from January 1, 2026, and shall remain in force until amended or replaced by the College Management.\r\n\r\n2.3 Application\r\nThese Conditions apply to all staff members regardless of their designation, provided that specific contractual terms may vary for individual staff based on their employment letter.\r\n\r\nAPPOINTMENT AND PROBATION\r\n\r\n3.1 Appointment Process\r\nAll appointments shall be made by the Governing Board or its authorized representative.\r\nAppointments shall be confirmed in writing through an offer letter containing:\r\nJob title and description\r\nSalary and benefits\r\nReporting line\r\nCommencement date\r\nProbation period\r\nAny special conditions\r\n\r\n3.2 Probationary Period\r\nAll new staff members shall serve a probationary period of six (6) months.\r\nThe probationary period may be extended by an additional three (3) months at the discretion of Management.\r\nDuring probation, employment may be terminated with fourteen (14) days\' notice without recourse to the disciplinary procedure.\r\nConfirmation of appointment shall be communicated in writing upon successful completion of probation.\r\n\r\n3.3 Confirmation of Appointment\r\nConfirmation shall be based on:\r\nSatisfactory performance during probation\r\nCompliance with College policies and code of conduct\r\nSuccessful completion of any required training\r\nVerification of credentials and references\r\n\r\nTERMS OF EMPLOYMENT\r\n\r\n4.1 Employment Categories\r\nPermanent Staff: Confirmed staff with continuous employment.\r\nContract Staff: Staff employed for a fixed term.\r\nPart-Time Staff: Staff employed on a part-time basis.\r\nTemporary/Relief Staff: Staff engaged for short-term assignments.\r\n\r\n4.2 Full-Time Employment\r\nFull-time staff shall work a minimum of 40 hours per week.\r\nCore working hours: 8:00 AM – 4:00 PM, Monday to Friday.\r\n\r\n4.3 Part-Time Employment\r\nPart-time staff shall work hours as specified in their contract.\r\nBenefits shall be prorated based on hours worked.\r\n\r\n4.4 Contract Employment\r\nContract staff shall serve for a fixed term as specified in their contract.\r\nRenewal of contract shall be at the discretion of the College Management.\r\n\r\n4.5 Job Descriptions\r\nEach staff member shall receive a detailed job description.\r\nJob descriptions may be reviewed and updated as needed.\r\n\r\nREMUNERATION AND BENEFITS\r\n\r\n5.1 Salary Structure\r\nSalaries shall be paid monthly on the 25th day of each month.\r\nSalary structure shall be determined by the Governing Board based on:\r\nQualifications\r\nExperience\r\nJob responsibilities\r\nMarket rates\r\n\r\n5.2 Salary Review\r\nSalaries shall be reviewed annually (effective January 1 each year).\r\nReview shall consider:\r\nCost of living adjustments\r\nPerformance\r\nMarket trends\r\nCollege financial position\r\n\r\n5.3 Allowances\r\nThe College may provide the following allowances:\r\nHousing Allowance: For staff not provided with accommodation.\r\nTransport Allowance: For commuting costs.\r\nMedical Allowance: For health-related expenses.\r\nLeave Allowance: Paid upon commencement of annual leave.\r\n\r\n5.4 Bonuses and Incentives\r\nPerformance Bonus: Annual bonus based on performance evaluation.\r\n13th Month Salary: Paid in December to eligible staff.\r\nLong Service Award: Recognizes staff with 10, 15, 20, and 25 years of service.\r\n\r\n5.5 Pension and Gratuity\r\nStaff shall contribute to the Contributory Pension Scheme as required by law.\r\nGratuity shall be paid to eligible staff upon retirement or completion of service.\r\n\r\n5.6 Tax Obligations\r\nAll salaries and allowances shall be subject to statutory deductions.\r\nThe College shall remit taxes to the relevant authorities as required by law.\r\n\r\nWORKING HOURS AND LEAVE\r\n\r\n6.1 Working Hours\r\nCore Hours: 8:00 AM – 4:00 PM, Monday to Friday.\r\nAcademic staff shall be present 15 minutes before the start of the first period.\r\nAll staff must clock in and out daily using the College attendance system.\r\n\r\n6.2 Overtime\r\nOvertime may be required from time to time.\r\nOvertime shall be compensated at 1.5 times the normal hourly rate or granted as time off in lieu.\r\nApproval from the Head of Department/Principal is required before overtime is worked.\r\n\r\n6.3 Annual Leave\r\nAcademic staff: 6 weeks of annual leave during school holidays.\r\nNon-academic staff: 4 weeks of annual leave per annum.\r\nLeave shall be taken during school holidays for academic staff.\r\nLeave must be applied for in writing at least two (2) weeks in advance.\r\nLeave not taken within the leave year shall be forfeited.\r\n\r\n6.4 Sick Leave\r\nStaff shall be entitled to 10 working days of paid sick leave per annum.\r\nSick leave exceeding 3 consecutive days requires a medical certificate from a registered medical practitioner.\r\nStaff on sick leave must notify the College as early as possible.\r\n\r\n6.5 Maternity Leave\r\nFemale staff shall be entitled to 12 weeks of fully paid maternity leave.\r\nMaternity leave may commence 4 weeks before the expected delivery date.\r\nStaff must apply for maternity leave at least 4 weeks in advance.\r\nStaff returning from maternity leave shall be entitled to an additional 3 months of reduced workload at the discretion of Management.\r\n\r\n6.6 Paternity Leave\r\nMale staff shall be entitled to 2 weeks of fully paid paternity leave.\r\nPaternity leave must be taken within 4 weeks of the birth of the child.\r\n\r\n6.7 Compassionate Leave\r\nStaff shall be entitled to 5 working days of compassionate leave per annum for the death of a direct family member.\r\nCompassionate leave may be extended at the discretion of Management.\r\n\r\n6.8 Other Leave Types\r\nStudy Leave: May be granted to staff pursuing further studies relevant to their role.\r\nSabbatical Leave: May be granted to academic staff with 10+ years of service.\r\nLeave of Absence: May be granted for personal reasons at Management\'s discretion.\r\n\r\nCODE OF CONDUCT\r\n\r\n7.1 Professional Conduct\r\nStaff shall maintain the highest standards of professionalism at all times.\r\nStaff shall treat students, parents, colleagues, and visitors with respect and dignity.\r\nStaff shall not engage in any form of discrimination, harassment, or bullying.\r\n\r\n7.2 Dress Code\r\nStaff shall wear professional attire appropriate to their role.\r\nAcademic staff shall wear the College-approved uniform as prescribed.\r\nStaff shall maintain personal hygiene and grooming standards.\r\n\r\n7.3 Punctuality and Attendance\r\nStaff shall be punctual for all duties, classes, and meetings.\r\nUnexcused lateness shall be recorded and may be subject to disciplinary action.\r\nStaff shall not leave the College premises during working hours without authorization.\r\n\r\n7.4 Use of College Property\r\nStaff shall use College property and resources responsibly.\r\nCollege equipment, vehicles, and facilities shall not be used for personal purposes without authorization.\r\nStaff shall report any damage or loss of College property immediately.\r\n\r\n7.5 Use of Technology and Social Media\r\nStaff shall use College computers, internet, and email for work-related purposes only.\r\nStaff shall maintain professional standards on social media platforms.\r\nStaff shall not post or share any content that may bring the College into disrepute.\r\nStaff shall not share confidential information on social media.\r\n\r\n7.6 Academic Integrity\r\nStaff shall maintain the highest standards of academic integrity.\r\nStaff shall not engage in any form of academic fraud, including grading manipulation.\r\nStaff shall report any suspected academic misconduct to Management.\r\n\r\n7.7 Relationships with Students\r\nStaff shall maintain professional boundaries with students at all times.\r\nRomantic or inappropriate relationships with students are strictly prohibited.\r\nStaff shall report any concerns regarding student welfare to the designated safeguarding officer.\r\n\r\n7.8 Alcohol and Substance Abuse\r\nStaff shall not consume alcohol or use any illegal substances during working hours.\r\nStaff shall not report to work under the influence of alcohol or drugs.\r\nViolation of this policy shall result in immediate disciplinary action.\r\n\r\n7.9 Gifts and Bribes\r\nStaff shall not accept any gifts or bribes from students, parents, or third parties that may influence their professional duties.\r\nNominal gifts (under NGN 5,000) may be accepted at the discretion of Management.\r\nStaff shall report any offer of a bribe to Management immediately.\r\n\r\nPERFORMANCE MANAGEMENT\r\n\r\n8.1 Performance Appraisal\r\nStaff shall undergo a formal performance appraisal twice a year.\r\nAppraisals shall be conducted by the Head of Department or Principal.\r\nAppraisal shall cover:\r\nJob performance\r\nProfessional development\r\nPunctuality and attendance\r\nInterpersonal skills\r\nCompliance with College policies\r\n\r\n8.2 Performance Improvement Plan\r\nStaff with unsatisfactory performance shall be placed on a Performance Improvement Plan (PIP).\r\nThe PIP shall outline specific improvement targets and timelines.\r\nStaff shall be supported with coaching, training, and resources during the PIP period.\r\nFailure to meet PIP targets may result in disciplinary action.\r\n\r\n8.3 Recognition of Outstanding Performance\r\nOutstanding staff shall be recognized through:\r\nMonthly Staff Recognition Awards\r\nAnnual Excellence Awards\r\nLetters of commendation\r\nFinancial bonuses\r\n\r\nGRIEVANCE PROCEDURE\r\n\r\n9.1 Purpose\r\nThe grievance procedure provides a formal mechanism for staff to raise concerns about their employment.\r\n\r\n9.2 Informal Stage\r\nStaff should first attempt to resolve the matter informally with their immediate supervisor.\r\nConcerns should be raised orally or in writing within 5 working days of the incident.\r\n\r\n9.3 Formal Stage\r\nIf the matter is not resolved informally, staff may submit a formal grievance in writing to the Head of HR.\r\nThe formal grievance shall include:\r\nThe nature of the grievance\r\nSupporting evidence\r\nDesired resolution\r\n\r\n9.4 Investigation and Response\r\nManagement shall investigate the grievance within 10 working days.\r\nStaff shall be given an opportunity to present their case.\r\nA written response shall be provided to the staff member within 5 working days of the investigation.\r\n\r\n9.5 Appeal\r\nStaff may appeal the decision in writing to the Governing Board within 5 working days.\r\nThe Governing Board shall review the appeal and communicate its final decision within 14 working days.\r\n\r\nDISCIPLINARY PROCEDURE\r\n\r\n10.1 Purpose\r\nThe disciplinary procedure ensures fair and consistent handling of staff misconduct.\r\n\r\n10.2 Minor Infractions\r\nMinor infractions include:\r\nUnexcused lateness\r\nMinor breaches of dress code\r\nFailure to submit required reports on time\r\nMinor breaches of College policies\r\n\r\n10.3 Disciplinary Actions for Minor Infractions\r\nVerbal warning\r\nWritten warning\r\nFinal written warning\r\nSuspension without pay (for up to 3 days)\r\n\r\n10.4 Major Infractions\r\nMajor infractions include:\r\nGross insubordination\r\nTheft or fraud\r\nViolence or harassment\r\nSubstance abuse\r\nBreach of confidentiality\r\nGross negligence of duty\r\nAcademic fraud or misconduct\r\nInappropriate relationships with students\r\nCriminal conduct\r\n\r\n10.5 Disciplinary Actions for Major Infractions\r\nImmediate suspension pending investigation\r\nDismissal without notice\r\n\r\n10.6 Disciplinary Hearing\r\nStaff accused of major infractions shall be invited to a disciplinary hearing.\r\nStaff shall be informed of the allegations in writing at least 5 working days before the hearing.\r\nStaff may be accompanied by a colleague or union representative.\r\nThe hearing shall be conducted by a panel of at least 3 Management representatives.\r\nThe panel shall consider all evidence and make a decision.\r\n\r\n10.7 Dismissal\r\nDismissal shall be communicated in writing.\r\nDismissed staff shall receive all outstanding salary and benefits within 14 days.\r\nDismissal may be appealed through the grievance procedure.\r\n\r\nTERMINATION OF EMPLOYMENT\r\n\r\n11.1 Termination by the College\r\nThe College may terminate employment for:\r\nPoor performance (following PIP)\r\nMisconduct\r\nRedundancy\r\nIll health or incapacity\r\nRetirement\r\n\r\n11.2 Notice Period\r\nPermanent staff: 3 months\' notice or payment in lieu.\r\nContract staff: as per the contract terms.\r\nProbationary staff: 14 days\' notice.\r\n\r\n11.3 Redundancy\r\nStaff may be made redundant due to:\r\nRestructuring of the College\r\nFinancial constraints\r\nChanges in student enrolment\r\nTechnological changes\r\n\r\n11.4 Redundancy Procedure\r\nStaff at risk of redundancy shall be notified in writing.\r\nStaff shall be consulted and given an opportunity to explore alternatives.\r\nRedundancy shall be based on objective criteria including performance, qualifications, and length of service.\r\nRedundancy pay shall be calculated as per the relevant labour laws.\r\n\r\n11.5 Summary Dismissal\r\nThe College reserves the right to summarily dismiss staff for gross misconduct without notice.\r\nSummary dismissal shall be reserved for extreme cases as defined in section 10.4.\r\n\r\nRESIGNATION\r\n\r\n12.1 Notice of Resignation\r\nStaff intending to resign shall provide written notice as per their contract:\r\nPermanent staff: 3 months\' notice\r\nContract staff: as per the contract terms\r\nProbationary staff: 14 days\' notice\r\n\r\n12.2 Resignation Procedure\r\nStaff must submit a formal resignation letter to the Principal/Head of HR.\r\nResignation letters must state the effective date of resignation.\r\nStaff shall be required to conduct an exit interview.\r\nStaff shall return all College property upon resignation.\r\n\r\n12.3 Payment Upon Resignation\r\nResigning staff shall receive:\r\nAll outstanding salary\r\nPro-rated leave allowance\r\nPension contributions\r\nAny other entitled benefits\r\n\r\n12.4 Withdrawal of Resignation\r\nStaff may withdraw their resignation in writing before the notice period expires.\r\nWithdrawal shall be at the discretion of Management.\r\n\r\nCONFIDENTIALITY AND INTELLECTUAL PROPERTY\r\n\r\n13.1 Confidential Information\r\nStaff shall maintain the confidentiality of all College information, including:\r\nStudent records and personal data\r\nStaff personnel files\r\nFinancial information\r\nExamination materials and results\r\nStrategic plans\r\nAny other information marked as confidential\r\n\r\n13.2 Non-Disclosure\r\nStaff shall not disclose confidential information to any third party without authorization.\r\nConfidentiality obligations shall continue after the termination of employment.\r\n\r\n13.3 Intellectual Property\r\nAny intellectual property created by staff during their employment shall belong to the College.\r\nThis includes:\r\nLesson plans and teaching materials\r\nCurriculum content\r\nExamination questions\r\nSoftware and digital resources\r\nResearch and publications\r\n\r\n13.4 Data Protection\r\nStaff shall comply with the Nigeria Data Protection Regulation (NDPR).\r\nStaff shall ensure proper handling and protection of personal data.\r\nStaff shall report any data breaches to Management immediately.\r\n\r\nHEALTH AND SAFETY\r\n\r\n14.1 General Health and Safety\r\nThe College is committed to providing a safe and healthy work environment.\r\nStaff shall comply with all health and safety policies and procedures.\r\nStaff shall report any hazards or safety concerns to Management.\r\n\r\n14.2 First Aid\r\nThe College shall maintain first aid facilities and supplies.\r\nTrained first aiders shall be available during working hours.\r\nStaff shall report any injury or illness immediately.\r\n\r\n14.3 Fire Safety\r\nThe College shall maintain fire safety equipment and procedures.\r\nStaff shall participate in fire drills and safety training.\r\nStaff shall familiarize themselves with emergency evacuation routes.\r\n\r\n14.4 Mental Health and Wellbeing\r\nThe College is committed to supporting staff mental health and wellbeing.\r\nStaff shall have access to counselling services.\r\nStaff experiencing mental health challenges are encouraged to seek support.\r\n\r\n14.5 Workplace Harassment\r\nThe College maintains a zero-tolerance policy toward harassment.\r\nStaff shall report any incidents of harassment to Management.\r\nThe College shall investigate all reports promptly and confidentially.\r\n\r\nSTAFF DEVELOPMENT AND TRAINING\r\n\r\n15.1 Continuous Professional Development\r\nThe College is committed to the professional development of all staff.\r\nStaff shall participate in mandatory and optional training programs.\r\n\r\n15.2 Training Programs\r\nThe College may provide:\r\nInduction training for new staff\r\nSubject-specific pedagogical training\r\nLeadership and management training\r\nTechnology and digital skills training\r\nSafeguarding and child protection training\r\nHealth and safety training\r\n\r\n15.3 Study Leave\r\nStaff may apply for study leave to pursue further qualifications.\r\nStudy leave shall be granted at the discretion of Management.\r\nStaff may be required to return to the College for a specified period after study leave.\r\n\r\n15.4 Conference and Workshop Attendance\r\nStaff may be sponsored to attend conferences and workshops.\r\nSponsorship shall be based on relevance to the College and budget availability.\r\nStaff attending conferences shall share learnings with colleagues.\r\n\r\nRETIREMENT AND PENSION\r\n\r\n16.1 Retirement Age\r\nThe retirement age for all staff shall be 60 years.\r\nThe College may retain staff beyond retirement age on a contract basis at its discretion.\r\n\r\n16.2 Retirement Procedure\r\nStaff shall be notified of their upcoming retirement at least 6 months in advance.\r\nRetirement planning sessions shall be made available to staff approaching retirement.\r\nStaff shall undergo an exit process upon retirement.\r\n\r\n16.3 Pension Benefits\r\nStaff shall be enrolled in the Contributory Pension Scheme.\r\nThe College shall contribute the employer\'s portion as required by law.\r\nStaff shall receive their pension benefits upon retirement as per the Pension Reform Act.\r\n\r\n16.4 Gratuity\r\nStaff who have served the College for at least 5 years shall be eligible for gratuity.\r\nGratuity shall be calculated based on years of service and final salary.\r\nGratuity shall be paid within 60 days of retirement.\r\n\r\nMISCELLANEOUS PROVISIONS\r\n\r\n17.1 Amendment of Conditions\r\nThese Conditions may be amended from time to time by the Governing Board.\r\nStaff shall be notified of any amendments in writing at least 30 days before implementation.\r\n\r\n17.2 Conflict of Interest\r\nStaff shall disclose any actual or potential conflicts of interest.\r\nStaff shall not engage in activities that conflict with the interests of the College.\r\n\r\n17.3 Outside Employment\r\nStaff shall not engage in outside employment that conflicts with their duties.\r\nOutside employment shall require prior written approval from Management.\r\n\r\n17.4 Indemnification\r\nThe College shall indemnify staff against claims arising from the proper performance of their duties.\r\nIndemnification shall not apply to acts of negligence, wilful misconduct, or fraud.\r\n\r\n17.5 Governing Law\r\nThese Conditions shall be governed by the laws of the Federal Republic of Nigeria.\r\n\r\n17.6 Dispute Resolution\r\nAny disputes arising from these Conditions shall be resolved through internal procedures first.\r\nUnresolved disputes shall be referred to the National Industrial Court of Nigeria.\r\n\r\nACKNOWLEDGEMENT\r\n\r\nI, the undersigned, acknowledge that I have received, read, and understood the Conditions of Service of KHADOB College, Akure. I agree to comply with all provisions as a condition of my employment.\r\n\r\nName:\r\nSignature:\r\nDate:', 'KHADOB COLLEGE, AKURE\r\nSCHOOL POLICY DOCUMENT\r\n\r\nEffective Date: January 2026\r\nVersion: 1.0\r\n\r\nTABLE OF CONTENTS\r\n\r\nIntroduction and Philosophy\r\n\r\nAdmission Policy\r\n\r\nAcademic Policy\r\n\r\nAssessment and Examination Policy\r\n\r\nAttendance Policy\r\n\r\nDress Code Policy\r\n\r\nBehaviour and Discipline Policy\r\n\r\nAnti-Bullying Policy\r\n\r\nSafeguarding and Child Protection Policy\r\n\r\nHealth and Medical Policy\r\n\r\nICT and Internet Use Policy\r\n\r\nParent and Community Engagement Policy\r\n\r\nFinancial and Fee Policy\r\n\r\nTransport Policy\r\n\r\nEmergency and Crisis Management Policy\r\n\r\nEqual Opportunity and Inclusion Policy\r\n\r\nEnvironmental and Sustainability Policy\r\n\r\nReview and Amendment Policy\r\n\r\nINTRODUCTION AND PHILOSOPHY\r\n\r\n1.1 Mission Statement\r\nKHADOB College, Akure is committed to providing a holistic, world-class education that nurtures academic excellence, character development, and lifelong learning in a safe, inclusive, and supportive environment.\r\n\r\n1.2 Vision Statement\r\nTo raise a generation of confident, innovative, and morally upright leaders who will positively impact their communities and the nation at large.\r\n\r\n1.3 Core Values\r\nExcellence\r\nIntegrity\r\nRespect\r\nResponsibility\r\nInnovation\r\nCommunity\r\n\r\n1.4 Policy Scope\r\nThis policy document applies to all students, staff, parents, and visitors of KHADOB College, Akure.\r\n\r\n1.5 Policy Compliance\r\nAll members of the school community are expected to comply with all policies outlined in this document.\r\n\r\nADMISSION POLICY\r\n\r\n2.1 Admission Criteria\r\nAdmission to KHADOB College shall be based on:\r\nSuccessful completion of an entrance examination\r\nPerformance in an interview\r\nSubmission of all required documents\r\nAvailability of space in the relevant class\r\n\r\n2.2 Admission Process\r\nParents shall complete and submit an application form.\r\nApplicants shall sit for the entrance examination.\r\nSuccessful applicants shall be invited for an interview.\r\nAdmission letters shall be issued to successful candidates.\r\nParents shall complete registration formalities within the specified period.\r\n\r\n2.3 Required Documents\r\nBirth certificate\r\nPrevious school report cards\r\nPassport photographs\r\nMedical certificate\r\nParent identification documents\r\n\r\n2.4 Age Requirements\r\nNursery: 2–4 years\r\nPrimary: 5–11 years\r\nSecondary: 11–18 years\r\n\r\n2.5 Transfer Admission\r\nStudents transferring from other schools must provide:\r\nTransfer certificate\r\nAcademic records\r\nCharacter reference from previous school\r\n\r\nACADEMIC POLICY\r\n\r\n3.1 Curriculum\r\nThe College shall follow the Nigerian National Curriculum.\r\nThe College shall integrate skills development, character education, and extracurricular activities.\r\n\r\n3.2 Class Size\r\nMaximum class size shall not exceed 35 students per class.\r\nThe College reserves the right to adjust class sizes based on available resources.\r\n\r\n3.3 School Calendar\r\nThe academic year shall be divided into three terms.\r\nEach term shall last approximately 12 weeks.\r\nTerm dates shall be communicated to parents at least two weeks before the term begins.\r\n\r\n3.4 Homework Policy\r\nHomework shall be assigned regularly to reinforce classroom learning.\r\nHomework shall be age-appropriate and manageable.\r\nParents are encouraged to support their children in completing homework.\r\n\r\n3.5 Academic Support\r\nStudents requiring additional academic support shall be provided with:\r\nRemedial classes\r\nPeer tutoring\r\nIndividualized learning plans\r\n\r\nASSESSMENT AND EXAMINATION POLICY\r\n\r\n4.1 Continuous Assessment\r\nContinuous assessment shall be conducted throughout the term.\r\nAssessment may include:\r\nWeekly quizzes\r\nClass participation\r\nHomework and assignments\r\nOral presentations\r\nPractical work\r\n\r\n4.2 Examinations\r\nFormal examinations shall be conducted at the end of each term.\r\nExamination timetables shall be published at least two weeks in advance.\r\nStudents must sit for all examinations in the subjects they are registered for.\r\n\r\n4.3 Grading System\r\nThe following grading scale shall apply:\r\nA: 75% – 100%\r\nB: 60% – 74%\r\nC: 50% – 59%\r\nD: 40% – 49%\r\nF: Below 40%\r\n\r\n4.4 Report Cards\r\nReport cards shall be issued at the end of each term.\r\nReport cards shall include:\r\nSubject grades\r\nTeacher comments\r\nClass position\r\nAttendance record\r\n\r\n4.5 Promotion Requirements\r\nStudents must pass a minimum of 60% of their subjects.\r\nStudents must pass English and Mathematics at the required level.\r\nStudents with unsatisfactory performance may be required to repeat the class.\r\n\r\n4.6 Examination Integrity\r\nStudents shall adhere to all examination rules and regulations.\r\nCheating, collusion, or any form of examination malpractice shall result in disciplinary action.\r\n\r\nATTENDANCE POLICY\r\n\r\n5.1 General Attendance\r\nStudents are expected to attend all scheduled classes and school activities.\r\nAttendance shall be recorded daily.\r\nParents must inform the school in advance of any planned absence.\r\n\r\n5.2 Absence Procedure\r\nAbsences must be reported to the school by 8:00 AM on the day of absence.\r\nWritten documentation (doctor\'s note, parental note) must be provided upon return.\r\n\r\n5.3 Tardiness\r\nStudents are expected to arrive at school by 7:30 AM.\r\nStudents arriving after 8:00 AM shall be marked as late.\r\nChronic lateness shall be addressed with the student and parent.\r\n\r\n5.4 Excused Absences\r\nExcused absences include:\r\nMedical emergencies\r\nFamily emergencies\r\nReligious observances\r\nApproved school activities\r\n\r\n5.5 Unexcused Absences\r\nUnexcused absences shall be recorded and may result in:\r\nLoss of participation marks\r\nDetention\r\nParent-teacher meeting\r\nSuspension for chronic absenteeism\r\n\r\n5.6 Truancy\r\nTruancy is a serious offence.\r\nStudents found to be truant shall be disciplined.\r\nPersistent truancy may lead to expulsion.\r\n\r\n5.7 Independent Study for Absences\r\nStudents who are absent shall be responsible for catching up on missed work.\r\nParents may collect assignments from the school office.\r\n\r\nDRESS CODE POLICY\r\n\r\n6.1 General Dress Code\r\nAll students shall wear the prescribed school uniform daily.\r\nThe uniform must be clean, neat, and in good condition.\r\nProper footwear shall be worn at all times.\r\n\r\n6.2 Uniform Specifications\r\nBoys:\r\nWhite shirt with the school crest\r\nKhaki trousers\r\nSchool tie\r\nSchool blazer (optional)\r\nBlack shoes\r\n\r\nGirls:\r\nWhite blouse with the school crest\r\nKhaki skirt\r\nSchool tie\r\nSchool blazer (optional)\r\nBlack shoes\r\n\r\n6.3 Physical Education Uniform\r\nPE uniform shall consist of the school-branded T-shirt and shorts.\r\nPE uniform shall be worn only during physical education classes.\r\n\r\n6.4 Grooming Standards\r\nStudents shall maintain neat and clean appearance.\r\nHair shall be kept clean and well-groomed.\r\nBoys: Hair shall be neatly cut and not below the collar.\r\nGirls: Hair shall be neatly styled and tied back if long.\r\nStudents shall not wear jewelry, makeup, or nail polish.\r\n\r\n6.5 Uniform Offences\r\nStudents not in proper uniform shall be sent home to change.\r\nRepeated uniform offences may result in disciplinary action.\r\n\r\nBEHAVIOUR AND DISCIPLINE POLICY\r\n\r\n7.1 Positive Behaviour Culture\r\nThe College promotes a culture of positive behaviour based on respect, responsibility, and mutual support.\r\n\r\n7.2 Expected Behaviour\r\nStudents shall:\r\nRespect staff and fellow students\r\nUse polite language\r\nFollow instructions\r\nMaintain a positive attitude\r\nReport bullying or misconduct\r\nTake care of school property\r\n\r\n7.3 Rewards and Recognition\r\nStudents exhibiting positive behaviour shall be recognized through:\r\nCommendation letters\r\nMerit points and certificates\r\nStar student recognition\r\nTrophies and awards\r\n\r\n7.4 Disciplinary Measures\r\nMinor misconduct may result in:\r\nVerbal warning\r\nWritten warning\r\nDetention\r\nLoss of privileges\r\n\r\nMajor misconduct may result in:\r\nSuspension\r\nExpulsion\r\n\r\n7.5 Behavioural Interventions\r\nStudents with persistent behaviour challenges shall be supported through:\r\nBehavioural contracts\r\nParent-teacher meetings\r\nCounselling sessions\r\nBehavioural intervention plans\r\n\r\nANTI-BULLYING POLICY\r\n\r\n8.1 Policy Statement\r\nKHADOB College has a zero-tolerance policy towards all forms of bullying.\r\n\r\n8.2 Definition of Bullying\r\nBullying includes any intentional, repeated behaviour that causes harm, fear, or distress to another person. This includes:\r\nPhysical bullying: hitting, pushing, or any physical violence\r\nVerbal bullying: name-calling, teasing, or verbal abuse\r\nSocial bullying: exclusion, spreading rumours, or public humiliation\r\nCyberbullying: bullying through digital platforms, including social media and messaging apps\r\n\r\n8.3 Prohibited Conduct\r\nThe following behaviours are strictly prohibited:\r\nAny act of bullying, regardless of severity\r\nRetaliation against any person who reports bullying\r\nEncouraging or facilitating bullying\r\n\r\n8.4 Reporting Procedures\r\nReports of bullying may be made to:\r\nA class teacher\r\nThe school counsellor\r\nThe Head of School\r\nAny staff member\r\n\r\n8.5 Investigation and Response\r\nAll reports shall be investigated promptly and confidentially.\r\nAppropriate disciplinary action shall be taken against perpetrators.\r\nSupport shall be provided to victims.\r\n\r\n8.6 Bystander Responsibility\r\nAll students are encouraged to report bullying they witness.\r\nStudents who witness bullying and fail to report it may be subject to disciplinary action.\r\n\r\nSAFEGUARDING AND CHILD PROTECTION POLICY\r\n\r\n9.1 Policy Statement\r\nKHADOB College is committed to safeguarding the welfare and well-being of all students.\r\n\r\n9.2 Designated Safeguarding Officer\r\nThe College shall appoint a Designated Safeguarding Officer.\r\nThe Safeguarding Officer shall be responsible for coordinating safeguarding activities and responding to concerns.\r\n\r\n9.3 Staff Training\r\nAll staff shall undergo safeguarding and child protection training.\r\nTraining shall cover signs of abuse, reporting procedures, and appropriate conduct with children.\r\n\r\n9.4 Reporting Safeguarding Concerns\r\nAny person with a safeguarding concern must report it to the Designated Safeguarding Officer.\r\nReports may be made anonymously if necessary.\r\n\r\n9.5 Confidentiality\r\nAll safeguarding reports shall be handled confidentially.\r\nInformation shall be shared only on a need-to-know basis and to protect the child\'s welfare.\r\n\r\n9.6 Safe Recruitment\r\nThe College shall ensure safe recruitment practices, including:\r\nCriminal record checks\r\nBackground checks\r\nVerification of credentials\r\n\r\nHEALTH AND MEDICAL POLICY\r\n\r\n10.1 Health Services\r\nThe College shall maintain a school clinic.\r\nA qualified nurse shall be available during school hours.\r\nFirst aid kits shall be available in multiple locations.\r\n\r\n10.2 Medical Records\r\nThe College shall maintain medical records for all students.\r\nMedical records shall be kept confidential.\r\n\r\n10.3 Medication Administration\r\nStudents may take prescribed medication only under the supervision of a designated staff member.\r\nParents must provide written authorization and instructions for medication administration.\r\n\r\n10.4 Communicable Diseases\r\nStudents with communicable diseases shall be excluded from school until cleared by a medical professional.\r\nParents must inform the school of any communicable disease diagnosed.\r\n\r\n10.5 Emergency Medical Care\r\nIn case of a medical emergency:\r\nFirst aid shall be administered immediately.\r\nParents shall be notified immediately.\r\nEmergency services shall be called when necessary.\r\n\r\nICT AND INTERNET USE POLICY\r\n\r\n11.1 Acceptable Use\r\nSchool computers, tablets, and internet access shall be used for educational purposes only.\r\nStudents shall not use school devices for non-academic activities without authorization.\r\n\r\n11.2 Internet Access\r\nThe school shall provide filtered internet access.\r\nStudents may not bypass security filters or firewalls.\r\nStudents shall not use the internet to access inappropriate content.\r\n\r\n11.3 Personal Devices\r\nStudents are not permitted to bring personal devices to school without authorization.\r\nPersonal devices shall be kept in the school office during school hours.\r\n\r\n11.4 Cyber Safety\r\nStudents shall not share personal information online.\r\nStudents shall not create or share content that is harmful or offensive.\r\nStudents shall not engage in cyberbullying.\r\n\r\n11.5 Violations\r\nViolations of this policy shall result in disciplinary action.\r\n\r\nPARENT AND COMMUNITY ENGAGEMENT POLICY\r\n\r\n12.1 Parent Involvement\r\nThe College values active parent involvement in the education of their children.\r\nParents are encouraged to attend school events and activities.\r\n\r\n12.2 Parent-Teacher Meetings\r\nParent-teacher meetings shall be held at least once per term.\r\nParents shall be notified of meeting dates in advance.\r\n\r\n12.3 Parent-Teacher Association\r\nThe College shall maintain a Parent-Teacher Association.\r\nThe PTA shall serve as a forum for communication and collaboration.\r\n\r\n12.4 Communication\r\nThe College shall communicate with parents through:\r\nNotice boards\r\nText messages\r\nLetters and newsletters\r\nSchool portal\r\nEmail\r\n\r\n12.5 Parent Concerns\r\nParents shall direct concerns to the class teacher in the first instance.\r\nUnresolved concerns shall be escalated to the Principal.\r\n\r\nFINANCIAL AND FEE POLICY\r\n\r\n13.1 Tuition and Fees\r\nTuition and fees shall be determined by the Governing Board.\r\nFees shall be communicated to parents before the start of each term.\r\n\r\n13.2 Payment Terms\r\nAll fees must be paid in full before the first day of the term.\r\nLate payment may incur penalties.\r\n\r\n13.3 Payment Methods\r\nFees may be paid through:\r\nDirect bank transfer\r\nSchool portal\r\nOther approved payment methods\r\n\r\n13.4 Fee Refund Policy\r\nFees shall be refunded in the following cases:\r\nWithdrawal within 4 weeks of term commencement: 50% refund\r\nWithdrawal after 4 weeks: no refund\r\nOverpayment: full refund\r\n\r\n13.5 Financial Assistance\r\nScholarships and financial assistance may be available for eligible students.\r\nApplications for financial assistance shall be submitted to the school office.\r\n\r\nTRANSPORT POLICY\r\n\r\n14.1 School Transport\r\nThe College may provide transport services for students.\r\nTransport services shall be subject to availability.\r\n\r\n14.2 Route and Schedules\r\nBus routes and schedules shall be communicated to parents.\r\nPick-up and drop-off points shall be designated.\r\n\r\n14.3 Conduct on School Vehicles\r\nStudents shall conduct themselves safely and respectfully on school vehicles.\r\nSeat belts shall be worn at all times.\r\nStudents shall not distract the driver.\r\n\r\n14.4 Private Transport\r\nParents using private transport shall ensure that vehicles are roadworthy and drivers are licensed.\r\n\r\nEMERGENCY AND CRISIS MANAGEMENT POLICY\r\n\r\n15.1 Emergency Preparedness\r\nThe College shall develop and maintain emergency response plans.\r\nEmergency plans shall cover fires, medical emergencies, natural disasters, and security incidents.\r\n\r\n15.2 Emergency Drills\r\nEmergency drills shall be conducted at least twice per term.\r\nAll students and staff shall participate in emergency drills.\r\n\r\n15.3 Crisis Communication\r\nIn a crisis, the College shall notify parents through established communication channels.\r\nCommunication shall be timely, clear, and accurate.\r\n\r\n15.4 Emergency Contacts\r\nParents shall provide up-to-date emergency contact information.\r\nEmergency contacts shall be reviewed at the start of every term.\r\n\r\nEQUAL OPPORTUNITY AND INCLUSION POLICY\r\n\r\n16.1 Non-Discrimination\r\nKHADOB College does not discriminate on the basis of:\r\nRace or ethnicity\r\nGender\r\nReligion\r\nDisability\r\nSocioeconomic background\r\n\r\n16.2 Inclusive Environment\r\nThe College is committed to creating an inclusive environment for all students.\r\nSupport and accommodations shall be provided to students with disabilities.\r\n\r\n16.3 Cultural Diversity\r\nThe College celebrates cultural diversity and encourages students to appreciate different cultures.\r\n\r\n16.4 Addressing Discrimination\r\nAny incidents of discrimination shall be reported and addressed promptly.\r\n\r\nENVIRONMENTAL AND SUSTAINABILITY POLICY\r\n\r\n17.1 Environmental Commitment\r\nKHADOB College is committed to environmental sustainability and responsible resource use.\r\n\r\n17.2 Waste Management\r\nThe College shall implement waste management practices, including recycling.\r\nStudents and staff shall be encouraged to reduce waste.\r\n\r\n17.3 Energy Conservation\r\nThe College shall promote energy conservation.\r\nLights and appliances shall be turned off when not in use.\r\n\r\n17.4 Environmental Education\r\nEnvironmental awareness shall be integrated into the curriculum.\r\nStudents shall participate in environmental activities and projects.\r\n\r\n17.5 Green Initiatives\r\nThe College may implement green initiatives such as tree planting and garden projects.\r\n\r\nREVIEW AND AMENDMENT POLICY\r\n\r\n18.1 Regular Review\r\nThis policy document shall be reviewed at least once every two years.\r\nThe review shall involve staff, parents, and the Governing Board.\r\n\r\n18.2 Amendments\r\nAmendments shall be approved by the Governing Board.\r\nAmendments shall be communicated to all stakeholders.\r\n\r\n18.3 Implementation\r\nThe Principal shall oversee the implementation of all policies.\r\nAll staff are responsible for enforcing the policies within their scope of work.\r\n\r\nACKNOWLEDGEMENT\r\n\r\nI, the undersigned, acknowledge that I have received, read, and understood the School Policy of KHADOB College, Akure. I agree to comply with all provisions as a condition of enrolment/employment.\r\n\r\nName:\r\nSignature:\r\nDate:', 'd171b6c7507f3eb6007865815aa1c2d6', NULL, NULL, NULL, '2026-08-08', 'daily', 'rst-5c387af504560d');

-- --------------------------------------------------------

--
-- Table structure for table `school_calendar_days`
--

CREATE TABLE `school_calendar_days` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `calendar_date` date NOT NULL,
  `is_school_day` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = not a school day (e.g. mid-week break) even though it might otherwise default to one',
  `is_public_holiday` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = public holiday; attendance is never marked on a holiday regardless of is_school_day',
  `title` varchar(200) DEFAULT NULL COMMENT 'Holiday/event name shown to staff, e.g. "Democracy Day"',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_credit_balances`
--

CREATE TABLE `school_credit_balances` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `credit_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_feature_access`
--

CREATE TABLE `school_feature_access` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `feature_key` varchar(100) NOT NULL,
  `is_enabled` tinyint(4) DEFAULT 1,
  `access_level` enum('hide','read','write','full') NOT NULL DEFAULT 'full' COMMENT 'Platform-set ceiling: hide=feature off, read=view only, write=can edit, full=can edit + approve/release. School admin cannot exceed this for any staff.',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_invoices`
--

CREATE TABLE `school_invoices` (
  `id` int(11) NOT NULL,
  `invoice_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) DEFAULT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `plan` varchar(50) NOT NULL,
  `billing_cycle` varchar(20) DEFAULT 'Monthly',
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(20) DEFAULT 'Unpaid',
  `due_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_name` varchar(50) DEFAULT NULL,
  `term_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_invoices`
--

INSERT INTO `school_invoices` (`id`, `invoice_uuid`, `school_uuid`, `student_uuid`, `invoice_no`, `plan`, `billing_cycle`, `amount`, `status`, `due_date`, `created_at`, `session_name`, `term_name`) VALUES
(1, 'inv-0ba4bc3a802532', 'sch-fded1718575ebd', 'std-7bd7e0d71cba75', 'INV-2026-0001', 'Tuition', 'Termly', 60000.00, 'Unpaid', '2026-09-07', '2026-08-08 02:31:20', '2026/2027', 'First Term');

-- --------------------------------------------------------

--
-- Table structure for table `school_notices_calendar`
--

CREATE TABLE `school_notices_calendar` (
  `id` int(11) NOT NULL,
  `notice_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `category` varchar(50) DEFAULT 'Announcement',
  `content` text NOT NULL,
  `event_date` date DEFAULT NULL,
  `target_audience` varchar(50) DEFAULT 'All',
  `sent_sms_alert` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_receipts`
--

CREATE TABLE `school_receipts` (
  `id` int(11) NOT NULL,
  `receipt_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `invoice_uuid` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_ref` varchar(100) DEFAULT NULL,
  `payment_date` date DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_by` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_settings`
--

CREATE TABLE `school_settings` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `school_name` varchar(150) NOT NULL,
  `motto` varchar(255) DEFAULT NULL,
  `theme_mode` varchar(250) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(150) DEFAULT NULL,
  `current_session` varchar(50) DEFAULT '2025/2026',
  `current_term` varchar(50) DEFAULT 'First Term',
  `currency` varchar(10) DEFAULT 'NGN',
  `principal_name` varchar(100) DEFAULT 'Dr. A. B. Cole',
  `report_card_template_json` text DEFAULT NULL,
  `letterhead_header_text` text DEFAULT NULL,
  `custom_feature_overrides` text DEFAULT NULL,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` smallint(6) DEFAULT 587,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_encryption` varchar(10) DEFAULT 'tls',
  `smtp_from_name` varchar(150) DEFAULT NULL,
  `smtp_from_email` varchar(150) DEFAULT NULL,
  `sms_provider` varchar(50) DEFAULT 'termii',
  `sms_api_key` varchar(255) DEFAULT NULL,
  `sms_sender_id` varchar(50) DEFAULT 'School',
  `sms_at_username` varchar(100) DEFAULT NULL,
  `whatsapp_provider` varchar(50) DEFAULT 'twilio',
  `whatsapp_account_sid` varchar(100) DEFAULT NULL,
  `whatsapp_auth_token` varchar(255) DEFAULT NULL,
  `whatsapp_from_number` varchar(50) DEFAULT NULL,
  `whatsapp_meta_token` varchar(500) DEFAULT NULL,
  `whatsapp_meta_phone_id` varchar(100) DEFAULT NULL,
  `paystack_public_key` varchar(255) DEFAULT NULL,
  `paystack_secret_key` varchar(255) DEFAULT NULL,
  `flutterwave_public_key` varchar(255) DEFAULT NULL,
  `grading_json` longtext DEFAULT NULL,
  `use_dynamic_assessments` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = use assessment_configurations table; 0 = legacy ca1/ca2/exam_max',
  `ai_provider` varchar(30) DEFAULT 'openai',
  `ai_api_key` varchar(255) DEFAULT NULL,
  `ai_model` varchar(100) DEFAULT NULL,
  `ai_essay_prompt` text DEFAULT NULL,
  `ai_lesson_prompt` text DEFAULT NULL,
  `payments_enabled` tinyint(1) DEFAULT 0,
  `flutterwave_secret_key` text DEFAULT NULL,
  `flutterwave_enabled` tinyint(1) DEFAULT 0,
  `assessment_templates_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assessment_templates_json`)),
  `assessment_configurations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assessment_configurations_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_settings`
--

INSERT INTO `school_settings` (`id`, `school_uuid`, `school_name`, `motto`, `theme_mode`, `address`, `phone`, `email`, `website`, `current_session`, `current_term`, `currency`, `principal_name`, `report_card_template_json`, `letterhead_header_text`, `custom_feature_overrides`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`, `smtp_from_name`, `smtp_from_email`, `sms_provider`, `sms_api_key`, `sms_sender_id`, `sms_at_username`, `whatsapp_provider`, `whatsapp_account_sid`, `whatsapp_auth_token`, `whatsapp_from_number`, `whatsapp_meta_token`, `whatsapp_meta_phone_id`, `paystack_public_key`, `paystack_secret_key`, `flutterwave_public_key`, `grading_json`, `use_dynamic_assessments`, `ai_provider`, `ai_api_key`, `ai_model`, `ai_essay_prompt`, `ai_lesson_prompt`, `payments_enabled`, `flutterwave_secret_key`, `flutterwave_enabled`, `assessment_templates_json`, `assessment_configurations_json`) VALUES
(1, 'sch-fded1718575ebd', '', NULL, NULL, NULL, NULL, NULL, NULL, '2026/2027', 'First Term', 'NGN', 'Dr. A. B. Cole', NULL, NULL, NULL, NULL, 587, NULL, NULL, 'tls', NULL, NULL, 'termii', NULL, 'School', NULL, 'twilio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '[{\"min\":80,\"max\":100,\"grade\":\"A1\",\"remark\":\"Distinction\",\"points\":5},{\"min\":75,\"max\":79,\"grade\":\"B2\",\"remark\":\"Excellent\",\"points\":4.5},{\"min\":70,\"max\":74,\"grade\":\"B3\",\"remark\":\"Very Good\",\"points\":4},{\"min\":65,\"max\":69,\"grade\":\"C4\",\"remark\":\"Credit\",\"points\":3.5},{\"min\":60,\"max\":64,\"grade\":\"C5\",\"remark\":\"Credit\",\"points\":3},{\"min\":55,\"max\":59,\"grade\":\"C6\",\"remark\":\"Credit\",\"points\":2.5},{\"min\":50,\"max\":54,\"grade\":\"D7\",\"remark\":\"Pass\",\"points\":2},{\"min\":45,\"max\":49,\"grade\":\"E8\",\"remark\":\"Pass\",\"points\":1},{\"min\":0,\"max\":44,\"grade\":\"F9\",\"remark\":\"Fail\",\"points\":0}]', 1, 'openai', NULL, NULL, NULL, NULL, 0, NULL, 0, '[{\"name\":\"CA1\",\"description\":\"Continous Assessment 1\",\"is_active\":true},{\"name\":\"CW\",\"description\":\"Course Work\",\"is_active\":true},{\"name\":\"PW\",\"description\":\"Project Work\",\"is_active\":true},{\"name\":\"CA2\",\"description\":\"Continous Assessment 2\",\"is_active\":true},{\"name\":\"Exam\",\"description\":\"Terminal Examination\",\"is_active\":true}]', '[{\"session_name\":\"2026\\/2027\",\"term_name\":\"First Term\",\"class_name\":\"\",\"template_name\":\"CA1\",\"assessment_order\":1,\"max_score\":10,\"is_required\":1},{\"session_name\":\"2026\\/2027\",\"term_name\":\"First Term\",\"class_name\":\"\",\"template_name\":\"CW\",\"assessment_order\":2,\"max_score\":10,\"is_required\":1},{\"session_name\":\"2026\\/2027\",\"term_name\":\"First Term\",\"class_name\":\"\",\"template_name\":\"PW\",\"assessment_order\":3,\"max_score\":10,\"is_required\":1},{\"session_name\":\"2026\\/2027\",\"term_name\":\"First Term\",\"class_name\":\"\",\"template_name\":\"CA2\",\"assessment_order\":4,\"max_score\":10,\"is_required\":1},{\"session_name\":\"2026\\/2027\",\"term_name\":\"First Term\",\"class_name\":\"\",\"template_name\":\"Exam\",\"assessment_order\":5,\"max_score\":60,\"is_required\":1}]');

-- --------------------------------------------------------

--
-- Table structure for table `shareable_page_tokens`
--

CREATE TABLE `shareable_page_tokens` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `token` varchar(64) NOT NULL,
  `page_type` varchar(50) NOT NULL COMMENT 'e.g. result_entry, report_card_print',
  `resource_id` varchar(50) DEFAULT NULL COMMENT 'student_uuid, report_uuid, etc.',
  `params_json` text DEFAULT NULL COMMENT 'extra scoped params (session, term, subject, class)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'staff_uuid who generated the share link',
  `expires_at` datetime DEFAULT NULL,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `staff_uuid` varchar(50) DEFAULT NULL,
  `user_uuid` varchar(50) DEFAULT NULL,
  `school_uuid` varchar(50) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'Teacher',
  `department` varchar(100) DEFAULT NULL,
  `qualification` varchar(150) DEFAULT NULL,
  `trcn_number` varchar(50) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT 120000.00,
  `status` varchar(20) DEFAULT 'Active',
  `photo_path` varchar(255) DEFAULT NULL,
  `healthcare_json` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_employed` date DEFAULT NULL,
  `employer` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_appraisals`
--

CREATE TABLE `staff_appraisals` (
  `id` int(11) NOT NULL,
  `appraisal_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `staff_uuid` varchar(50) NOT NULL,
  `staff_name` varchar(150) NOT NULL,
  `period_label` varchar(50) NOT NULL,
  `punctuality_rating` tinyint(4) DEFAULT 3,
  `subject_mastery_rating` tinyint(4) DEFAULT 3,
  `classroom_management_rating` tinyint(4) DEFAULT 3,
  `teamwork_rating` tinyint(4) DEFAULT 3,
  `overall_comment` text DEFAULT NULL,
  `appraised_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL,
  `record_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `staff_uuid` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `clock_in` time DEFAULT NULL,
  `clock_out` time DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Present'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_feature_permissions`
--

CREATE TABLE `staff_feature_permissions` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `staff_uuid` varchar(50) NOT NULL,
  `feature_key` varchar(100) NOT NULL COMMENT 'matches platform_feature_catalog.feature_key',
  `access_level` enum('hide','read','write','full') NOT NULL DEFAULT 'hide' COMMENT 'Per-staff override set by the school admin. hide=no access, read=view only, write=can edit, full=can edit + approve/release (e.g. release results, approve assignments).',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_leave_requests`
--

CREATE TABLE `staff_leave_requests` (
  `id` int(11) NOT NULL,
  `leave_uuid` varchar(50) DEFAULT NULL,
  `school_uuid` varchar(50) DEFAULT NULL,
  `staff_uuid` varchar(50) DEFAULT NULL,
  `staff_name` varchar(150) DEFAULT NULL,
  `leave_type` varchar(50) DEFAULT 'Annual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `reviewed_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_payslips`
--

CREATE TABLE `staff_payslips` (
  `id` int(11) NOT NULL,
  `payslip_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `staff_uuid` varchar(50) NOT NULL,
  `staff_name` varchar(150) NOT NULL,
  `pay_period` varchar(50) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `allowances` decimal(10,2) DEFAULT 0.00,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `net_pay` decimal(10,2) NOT NULL,
  `approval_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by` varchar(150) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `disbursed_at` timestamp NULL DEFAULT NULL COMMENT 'Set only after Approved — actual salary payment date',
  `generated_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_subject_assignments`
--

CREATE TABLE `staff_subject_assignments` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `staff_uuid` varchar(50) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `arm_name` varchar(50) DEFAULT NULL COMMENT 'NULL means all arms of the class',
  `periods_per_week` int(11) DEFAULT NULL,
  `session_name` varchar(50) NOT NULL,
  `term_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_inventory`
--

CREATE TABLE `store_inventory` (
  `id` int(11) NOT NULL,
  `item_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category` varchar(50) DEFAULT 'Uniform',
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_pos_sales`
--

CREATE TABLE `store_pos_sales` (
  `id` int(11) NOT NULL,
  `sale_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) DEFAULT NULL,
  `student_name` varchar(150) NOT NULL,
  `items_summary_json` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'Cash / Ledger',
  `receipt_number` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `admission_number` varchar(50) DEFAULT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `class` varchar(50) NOT NULL,
  `arm` varchar(50) DEFAULT 'Gold',
  `roll_number` varchar(50) NOT NULL,
  `parent_name` varchar(100) NOT NULL,
  `parent_email` varchar(150) NOT NULL,
  `parent_phone` varchar(50) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `healthcare_json` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `admission_date` date DEFAULT NULL,
  `parent_uuid` varchar(50) DEFAULT NULL COMMENT 'links to parents table'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_uuid`, `admission_number`, `school_uuid`, `name`, `class`, `arm`, `roll_number`, `parent_name`, `parent_email`, `parent_phone`, `photo_path`, `status`, `healthcare_json`, `date_of_birth`, `gender`, `admission_date`, `parent_uuid`) VALUES
(1, 'std-7bd7e0d71cba75', 'ADM-2026-0001', 'sch-fded1718575ebd', 'Oyewumi Aliyat', 'SS3', 'Science', 'RC2026-001', 'Samuel Ayo', 'samuelayo@gmail.com', '08043267263', 'admin\\uploads\\photos\\students\\std_71b7b27f5ee6.webp', 'Active', '{\"blood_group\":\"O+\",\"geno\":\"AA\",\"genotype\":\"AA\",\"allergies\":\"none\",\"emergency_contact\":\"Samuel Ayo - 08043267263\"}', '2010-01-01', 'Female', '2026-08-08', NULL),
(2, 'std-64f8591f351bef', 'ADM-2026-0002', 'sch-fded1718575ebd', '??ࡱ\Z?\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0>\0\0??	\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0-\0\0\0\0\0\0????\0\0\0\0\0\0\0\0??????????????????????', 'JSS1', 'Gold', 'RC2026-002', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(3, 'std-11ad8bbd54d7d2', 'ADM-2026-0003', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\Z\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0 \0\0\0!\0\0\0\"\0\0\0#\0\0\0$\0\0\0', '????????/\0\0\01\0\0\0??????????????????????????????????', 'Gold', 'RC2026-003', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(4, 'std-2407c9dfc5d737', 'ADM-2026-0004', 'sch-fded1718575ebd', '?\0\0\0\0\0\0\0	\0\0\0?\r???\0\0\0?\0\0??\0\0\0\0?\0\0\0\\\0p\0\0D\0E\0L\0L\0                                         ', 'JSS1', 'Gold', 'RC2026-004', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(5, 'std-e349dcc92729fa', 'ADM-2026-0005', 'sch-fded1718575ebd', 'P?8\0\0\0\0\0\0X@\0\0\0\0?\0\0\0\0\"\0\0\0\0\0\0\0?\0\0\0?\0\0\0\01\0\0?\0\0\0\0?\0\0\0\0\0C\0a\0l\0i\0b\0r\0i\01\0\0?\0\0\0??\0\0\0\0', 'JSS1', 'Gold', 'RC2026-005', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(6, 'std-3a58ccaebeecfa', 'ADM-2026-0006', 'sch-fded1718575ebd', '?\0\0\0\0\0C\0a\0l\0i\0b\0r\0i\01\0\0h\0\06\0?\0\0\0\0\0C\0a\0l\0i\0b\0r\0i\01\0\0?\0\0\0?\0\0\0\0\0C\0a\0l\0i\0b\0r\0i\01\0', '\0\06\0?\0\0\0\0\0C\0a\0l\0i\0b\0r\0i\01\0\0\0\06\0?\0\0\0\0\0C', '#\0#\00\0;\0\\\0-\0\"\0?\0\"\0#', 'RC2026-006', '#\0#\00\05\0\0\0\"\0?\0\"\0#', '#\0#\00\0;\0[\0R\0e\0d\0]\0\\\0-\0\"\0?\0\"\0#', '#\0#\00\07\0\0\0\"\0?\0\"\0#', NULL, 'Active', NULL, '0000-00-00', '#\0#\00\0.\00\0', NULL, NULL),
(7, 'std-da750f8514d5fd', 'ADM-2026-0007', 'sch-fded1718575ebd', '??\0\0?\0\0@ @ \0\0? ?\0\0\0\0\0??\0\0?\0 @ @\0\0? ?\0\0\0\0\0??\0\0?\0 @ @\0\0? ?\0\0\r\0\0\0??\0\0?\0 @ @\0\0? ?\0\0\r\0\0\0??\0', '?\0\0\0\0\0??\0\0?\0\0@ @ \05 ?\0\0\0\0\0??\0\0?\0\0@ @ \0\Z ?\0', 'Gold', 'RC2026-007', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(8, 'std-e0b7e809977e46', 'ADM-2026-0008', 'sch-fded1718575ebd', '\0\0\0\0????\0\0\0\0\0\0\0\0\0\0\0\0\0????\0\0\0\0\0\0\0\0\0\0\0\0\0????\0\0\0\0\0\0\0\0\0\0\0\0\0????\0\0\0\0\0\0\0\0\0\0}-\0}\0\0\0\0\0\0\0\0\0\0\0', 'JSS1', 'Gold', 'RC2026-008', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(9, 'std-b62547417ec1ed', 'ADM-2026-0009', 'sch-fded1718575ebd', '\0\0\0\0?\0\0\0\0\0\0\0\0\0\0\0\0\0?\0\0\0\0\0\0\0\0\0\0\0\0\0?\0\0\0\0\0\0\0\0\0\0\0\0\0?̙?\0\0\0\0\0\0\0\0\r\0\0\0\0\0??v?\0\0\0\0\0\0\0\0\0', 'JSS1', 'Gold', 'RC2026-009', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(10, 'std-730ff0f4d8a8ce', 'ADM-2026-0010', 'sch-fded1718575ebd', '\0\0\0\0????\0\0\0\0\0\0\0\0\0\0\0\0\0????\0\0\0\0\0\0\0\0\0\0\0\0\0????\0\0\0\0\0\0\0\0\0\0\0\0\0????\0\0\0\0\0\0\0\0\r\0\0\0\0\0????\0\0\0\0\0\0\0\0\0', 'JSS1', 'Gold', 'RC2026-010', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(11, 'std-4a3929d68fc390', 'ADM-2026-0011', 'sch-fded1718575ebd', '\0\0\0\0?\0\0\0\0\0\0\0\0\0\0\0\0\0?\0\0\0\0\0\0\0\0\0\0\0\0\0?\0\0\0\0\0\0\0\0\0\0\0\0\0????\0\0\0\0\0\0\0\0\r\0\0\0\0\0?}\0?\0\0\0\0\0\0\0\0\0', 'JSS1', 'Gold', 'RC2026-011', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(12, 'std-32ae6fd184064e', 'ADM-2026-0012', 'sch-fded1718575ebd', '\0\0\0\0????\0\0\0\0\0\0\0\0\0\0\0\0\0????\0\0\0\0\0\0\0\0\0\0\0\0\0????\0\0\0\0\0\0\0\0\0\0\0\0\0????\0\0\0\0\0\0\0\0\r\0\0\0\0\0????\0\0\0\0\0\0\0\0\0', '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\r\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0}A', 'Gold', 'RC2026-012', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(13, 'std-004a3f1dab04ab', 'ADM-2026-0013', 'sch-fded1718575ebd', '?\0N\0o\0t\0e\0\0\0\0\0?\0\0\0W\0a\0r\0n\0i\0n\0g\0 \0T\0e\0x\0t\0?.\0?\0\0\0\0\0\0\0\0\0\0?\0W\0a\0r\0n\0i\0n\0g\0 \0T\0e\0x\0t\0\0\0\0\0?', '?\0\0\0\0\0\0\0\0\0\0?\0C\0a\0l\0c\0u\0l\0a\0t\0i\0o\0n\0\0\0\0\0?\0\"', 'Gold', 'RC2026-013', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(14, 'std-3c02bdfb8cc7bd', 'ADM-2026-0014', 'sch-fded1718575ebd', 'C\0h\0e\0c\0k\0 \0C\0e\0l\0l\0?*\0?\0\0\0\0\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-014', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(15, 'std-4e39b4d2d60a0d', 'ADM-2026-0015', 'sch-fded1718575ebd', 'C\0h\0e\0c\0k\0 \0C\0e\0l\0l\0\0\0\0\0?\0#\0\0L\0i\0n\0k\0e\0d\0 \0C\0e\0l\0l\0?', '?\0\0\0\0\0\0\0\0\0\0?\0L\0i\0n\0k\0e\0d\0 \0C\0e\0l\0l\0\0\0\0\0?\0$\0', '\0A\0c\0c\0e\0n\0t\02\0?$\0?\0\0\0\0\0\0\0\0\0\0!?\0A\0c\0c\0e\0n\0t', 'RC2026-015', '?\r\06\00\0%\0 \0-\0 \0A\0c\0c\0e\0n\0t\04\0\0\0\0\0?\08\0\0A\0c\0c\0e\0n\0t\05\0?$\0?\0\0\0\0\0\0\0\0\0\0-?\0A\0c\0c\0e\0n\0t\05\0\0\0\0\0?\0', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(16, 'std-c278ef3d61afbb', 'ADM-2026-0016', 'sch-fded1718575ebd', '?N?@\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0theme/PK', 'JSS1', 'Gold', 'RC2026-016', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(17, 'std-41879c6a6ea5cc', 'ADM-2026-0017', 'sch-fded1718575ebd', '?N?@\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0theme/theme/PK\0\0\0\0?N?@ky?}\0\0\0?\0\0\0\0\0\0theme/theme/themeManager.xml\r?M', 'JSS1', 'Gold', 'RC2026-017', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(18, 'std-7ad926ee3b0e30', 'ADM-2026-0018', 'sch-fded1718575ebd', '? @?}?w??7c?(Eb?ˮ??\0C?\ZAǠҟ?????7??՛K\rY', '?\r?e?.???|', '???H?', 'RC2026-018', 'l????xɴ??I?sQ}#Ր???? ֵ+?!?', '?^?$j=?GW???)?E?+&', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(19, 'std-b38cd9f0446eb1', 'ADM-2026-0019', 'sch-fded1718575ebd', '8?PK\0\0\0\0?N?@|(\0??\0\0(\0\0\0\0\0theme/theme/theme1.xml?YMo7??X콑d??2\"?>?6vDJ??]j?w? );?ɱ@?', '?)<?0? 	_yT	9:?	?lU??J?H?{)J???Ʉ??[??S??J?ʇ?)^', '?4h#?y>??!5?\'N2????숥?W/_?=yq??׳?OϞ?lz??Q\Z?v?', 'RC2026-019', 'PH?/cx{??w???=? \".???#??0?3I3ߊx?=`ܹ?[j.#ȣY\Z?\'?3w???]?Z???2PO?rٍ?E?.E?DN???36?ر???Xq=&g?', '?????0????`?l<???D??ep????I$', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(20, 'std-d206c00311f12d', 'ADM-2026-0020', 'sch-fded1718575ebd', 'ב?2&???????Βc???ZM?4s?H.ǫ?rn2G7[?U?^????wA@پ		c2?Ķ?Dk1?????4	??+a?v??Q??Zc?ʬ?9Ƀ?U?o??????', 'JSS1', 'Gold', 'RC2026-020', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(21, 'std-dc973d7085bbcd', 'ADM-2026-0021', 'sch-fded1718575ebd', 'ٲ?V\ZS!???A]C?8?Y???b?y?R^????ߊ5C@???p?fi???e??9l???????왖????V1k?EX??嚼?jbh?f?ϥ{Ur??[9\'?]', 'j???+?.F?ޱX?9?.?$?o.ܮĭ???`?R??V??&?s????????', 'Gold', 'RC2026-021', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(22, 'std-f5717a1154f274', 'ADM-2026-0022', 'sch-fded1718575ebd', '?N?@\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0_rels/PK\0\0\0\0?N?@?֧??\0\0\06\0\0\0\0\0_rels/.rels???j?0???}Q??%v/??C/?}\0?(h\"?', 'JSS1', 'Gold', 'RC2026-022', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(23, 'std-9a9a00971bca9e', 'ADM-2026-0023', 'sch-fded1718575ebd', '??????=??????? ????C??h?v=??Ʌ??%[xp??{۵_?Pѣ<?1?H?0???O?R?Bd???JE?4b$??q_????6L??R?7`???', '?En7?Li?b??/?S???e??е????PK', 'Gold', 'RC2026-023', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(24, 'std-c7f4031983325d', 'ADM-2026-0024', 'sch-fded1718575ebd', '?N?@\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0theme/theme/_rels/PK\0\0\0\0?N?@\rѐ??\0\0\0\0\0\'\0\0\0theme/theme/_rels/themeManager.xm', 'JSS1', 'Gold', 'RC2026-024', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(25, 'std-d8d1365e59fb3a', 'ADM-2026-0025', 'sch-fded1718575ebd', '?0???wooӺ?&݈Э???5\r6?$Q??\r?', '.?a??i????c2?1h?\Z:??q??m???@RN??;d?`??o7?g?K(', '????x\\????v??T?U^h?d}㨫???)??*1P?\'???^ח??0)??T', 'RC2026-025', '???5?(?Bȥs??zҕhhs?0U~', '}?2??\ZTo?F?0', '?į?*?=댬o[g??v;? ?9???\'?3??3?y	;	??o?OPK\0\0\0\0\0', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(26, 'std-45a8cb2aae5345', 'ADM-2026-0026', 'sch-fded1718575ebd', '?N?@\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0_rels/PK\0\0\0\0\0?N?@?֧??\0\0\06\0\0\0\0\0\0\0\0\0\0 \0\0\0<\0\0_rels/.relsPK', 'JSS1', 'Gold', 'RC2026-026', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(27, 'std-f619eea03519fe', 'ADM-2026-0027', 'sch-fded1718575ebd', '?N?@\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0theme/PK', 'JSS1', 'Gold', 'RC2026-027', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(28, 'std-e7a37970e51748', 'ADM-2026-0028', 'sch-fded1718575ebd', '?N?@\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0$\0\0\0theme/theme/PK', 'JSS1', 'Gold', 'RC2026-028', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(29, 'std-7ba4936c781c6c', 'ADM-2026-0029', 'sch-fded1718575ebd', '?N?@\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0theme/theme/_rels/PK\0\0\0\0\0?N?@\rѐ??\0\0\0\0\0\'\0\0\0\0\0\0\0\0 \0\0\0O\0\0the', 'JSS1', 'Gold', 'RC2026-029', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(30, 'std-78a9a8f41d5aac', 'ADM-2026-0030', 'sch-fded1718575ebd', '?\0?\0\0\0\0\0\0\0\0\0\0\0\0\0\0?d\0?\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0?\0\0\0\0\0\0\0?\0\0\0\0\0\0\0\0?\0\0\0\0\0\0\0\0?\0	\0\0', '?\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0?\0\023\0\0\0?\0?\0?', 'Gold', 'RC2026-030', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(31, 'std-09f67415f123eb', 'ADM-2026-0031', 'sch-fded1718575ebd', '\0P\0i\0v\0o\0t\0S\0t\0y\0l\0e\0P\0r\0e\0s\0e\0t\02\0_\0A\0c\0c\0e\0n\0t\01\0?\0?\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0?\0?\0\0\0\0\0\0\0\0\0\0\0\0\0', 'JSS1', 'Gold', 'RC2026-031', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(32, 'std-d7b43112084222', 'ADM-2026-0032', 'sch-fded1718575ebd', '?\0?\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0?\0?\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0?\0?\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\r\0\0\0?\0?\0\0\0\0\0\0\0\0\0\0', 'JSS1', 'Gold', 'RC2026-032', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(33, 'std-311dd644d92f7b', 'ADM-2026-0033', 'sch-fded1718575ebd', '?\0\0\0S\0\0\0\0N\0a\0m\0e\0\0C\0l\0a\0s\0s\0\0A\0r\0m\0\0P\0a\0r\0e\0n\0t\0 \0N\0a\0m\0e\0\0P\0a\0r\0e\0n\0t\0 \0E\0m\0a\0i\0l\0\0P\0a\0', 'JSS1', 'Gold', 'RC2026-033', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(34, 'std-cdbb8fa4bc2131', 'ADM-2026-0034', 'sch-fded1718575ebd', 'M\0u\0s\0a\0 \0A\0b\0d\0u\0l\0\0m\0u\0s\0a\0.\0a\0b\0d\0u\0l\0@\0e\0m\0a\0i\0l\0.\0c\0o\0m', 'JSS1', 'Gold', 'RC2026-034', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(35, 'std-4741ff30170f6c', 'ADM-2026-0035', 'sch-fded1718575ebd', 'H\0a\0s\0s\0a\0n\0 \0A\0l\0i\0\0H\0a\0s\0s\0a\0n\0 \0Y\0u\0s\0u\0f\0\0h\0a\0s\0s\0a\0n\0.\0y\0u\0s\0u\0f\0@\0e\0m\0a\0i\0l\0.\0c\0o\0m\0\r\0A\0', 'JSS1', 'Gold', 'RC2026-035', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(36, 'std-b4d8c5bebde306', 'ADM-2026-0036', 'sch-fded1718575ebd', 'S\0?<\0\0', 'JSS1', 'Gold', 'RC2026-036', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(37, 'std-411b79efa70e8c', 'ADM-2026-0037', 'sch-fded1718575ebd', '\0\0\0?\r???\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0%\0\0/T\0\0\r\0\0\0\0\0d\0\0\0\0\0\0\0\0\0\0????MbP?_\0\0\0*\0\0\0\0+\0\0\0\0?\0', '?\0\0?\0\0\0\0\0\0?\0\0\0\0?\0\0\0\0&\0\0\0\0\0\0\0\0??\'\0\0\0\0\0\0\0\0?', 'Gold', 'RC2026-037', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(38, 'std-a22b6126040973', 'ADM-2026-0038', 'sch-fded1718575ebd', '\0Y\0\0\0\0\0\0\0\0\0\0\0Y\0\0\0\0\0\0\0\0\0\0\0Y\0\0\0\0\0\0\0\r\0\0\0\0Y\0\0\0\0\0\0\0\0\0\0\0Y\0\0\0\0\0\0\0\0\0\0', 'JSS1', 'Gold', 'RC2026-038', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(39, 'std-f6fed0fdf1f446', 'ADM-2026-0039', 'sch-fded1718575ebd', '@\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-039', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(40, 'std-8b98ba5313e23b', 'ADM-2026-0040', 'sch-fded1718575ebd', '\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-040', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(41, 'std-1a2004fe4efe27', 'ADM-2026-0041', 'sch-fded1718575ebd', '\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-041', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(42, 'std-1424de3708242e', 'ADM-2026-0042', 'sch-fded1718575ebd', '\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-042', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(43, 'std-42e51beb395aa6', 'ADM-2026-0043', 'sch-fded1718575ebd', '\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-043', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(44, 'std-d61a8dba26d848', 'ADM-2026-0044', 'sch-fded1718575ebd', '\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-044', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(45, 'std-fb312fd404db75', 'ADM-2026-0045', 'sch-fded1718575ebd', '\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-045', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(46, 'std-4a3861cdd7c151', 'ADM-2026-0046', 'sch-fded1718575ebd', '\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-046', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(47, 'std-1997671a33b8a1', 'ADM-2026-0047', 'sch-fded1718575ebd', '\0\0\0@\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-047', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(48, 'std-c6ec20d19fbfb2', 'ADM-2026-0048', 'sch-fded1718575ebd', '\0\0\0	\0\0\0?', 'JSS1', 'Gold', 'RC2026-048', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(49, 'std-0ebf3129a19a6d', 'ADM-2026-0049', 'sch-fded1718575ebd', '\0\0', 'JSS1', 'Gold', 'RC2026-049', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(50, 'std-72f78c0e26c43b', 'ADM-2026-0050', 'sch-fded1718575ebd', '?', 'JSS1', 'Gold', 'RC2026-050', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(51, 'std-53a88807fa9f9f', 'ADM-2026-0051', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-051', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(52, 'std-fce3e8bab50634', 'ADM-2026-0052', 'sch-fded1718575ebd', '\0\0A\0\0\0\0\0\0\0\0\0\0?+??A?', 'JSS1', 'Gold', 'RC2026-052', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(53, 'std-08b16852aaeb0a', 'ADM-2026-0053', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-053', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(54, 'std-be2f649e61f673', 'ADM-2026-0054', 'sch-fded1718575ebd', '\0\0A\0`??@?', 'JSS1', 'Gold', 'RC2026-054', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(55, 'std-a58a8ea4db6307', 'ADM-2026-0055', 'sch-fded1718575ebd', '\0\0\0@\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-055', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(56, 'std-4286d96b643ad6', 'ADM-2026-0056', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-056', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(57, 'std-c30a11a1d81fdb', 'ADM-2026-0057', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-057', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(58, 'std-6ae79c1ddf22e0', 'ADM-2026-0058', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-058', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(59, 'std-4d0eef2db2c2a4', 'ADM-2026-0059', 'sch-fded1718575ebd', '\0\0A\0\0\0\0\0\0\0\0\0\0P????A?', 'JSS1', 'Gold', 'RC2026-059', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(60, 'std-702b7a977cb587', 'ADM-2026-0060', 'sch-fded1718575ebd', '\0\0\0\0\0\0~', 'JSS1', 'Gold', 'RC2026-060', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(61, 'std-957f99c0882597', 'ADM-2026-0061', 'sch-fded1718575ebd', '\0\0A\0 ??@?', 'JSS1', 'Gold', 'RC2026-061', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(62, 'std-d857c30b1c80a6', 'ADM-2026-0062', 'sch-fded1718575ebd', '\0\0\0@\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-062', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(63, 'std-809a7fa6f39e56', 'ADM-2026-0063', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-063', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(64, 'std-82124a87530ffb', 'ADM-2026-0064', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-064', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(65, 'std-c9683a9d3db30c', 'ADM-2026-0065', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-065', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(66, 'std-ca8775ef7e80b0', 'ADM-2026-0066', 'sch-fded1718575ebd', '\0\0A\0\0\0\0\0\0\0\0\0\0 m\\??A?', 'JSS1', 'Gold', 'RC2026-066', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(67, 'std-454066a75aa2c0', 'ADM-2026-0067', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-067', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(68, 'std-a9b9d9b56a3fd1', 'ADM-2026-0068', 'sch-fded1718575ebd', '\0\0A\0\0??@?', 'JSS1', 'Gold', 'RC2026-068', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(69, 'std-057666e2f29693', 'ADM-2026-0069', 'sch-fded1718575ebd', '\0\0\0@\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-069', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(70, 'std-32649357272554', 'ADM-2026-0070', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-070', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(71, 'std-65728031c355c2', 'ADM-2026-0071', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-071', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(72, 'std-438ca9bbab67ee', 'ADM-2026-0072', 'sch-fded1718575ebd', '\0\0\0\Z\0\0\0?', 'JSS1', 'Gold', 'RC2026-072', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(73, 'std-31a6f3fa8afc1c', 'ADM-2026-0073', 'sch-fded1718575ebd', '\0\0A\0\0\0\0\0\0\0\0\0\0P???A?', 'JSS1', 'Gold', 'RC2026-073', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(74, 'std-057e55107490b0', 'ADM-2026-0074', 'sch-fded1718575ebd', '\0\0\0\0\0\0~', 'JSS1', 'Gold', 'RC2026-074', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(75, 'std-970878593aadd9', 'ADM-2026-0075', 'sch-fded1718575ebd', '\0\0A\0?i?@?', 'JSS1', 'Gold', 'RC2026-075', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(76, 'std-5659fea00e5e7f', 'ADM-2026-0076', 'sch-fded1718575ebd', '\0\0\0@\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-076', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(77, 'std-aa1b2d18d990cb', 'ADM-2026-0077', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-077', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(78, 'std-7a8789ea26efcc', 'ADM-2026-0078', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-078', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(79, 'std-7f4b251056b3b9', 'ADM-2026-0079', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-079', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(80, 'std-decff41dda8bed', 'ADM-2026-0080', 'sch-fded1718575ebd', '\0\0A\0\0\0\0\0\0\0\0\0\0@???A?', 'JSS1', 'Gold', 'RC2026-080', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(81, 'std-ad758130180b16', 'ADM-2026-0081', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-081', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(82, 'std-8ab7d74694580f', 'ADM-2026-0082', 'sch-fded1718575ebd', '\0\0A\0?L?@?', 'JSS1', 'Gold', 'RC2026-082', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(83, 'std-c4d80e04b2743e', 'ADM-2026-0083', 'sch-fded1718575ebd', '\0\0\0@\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-083', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(84, 'std-76f39cf9bc85a6', 'ADM-2026-0084', 'sch-fded1718575ebd', '\0\0\0 \0\0\0?', 'JSS1', 'Gold', 'RC2026-084', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(85, 'std-820e3a690674e5', 'ADM-2026-0085', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-085', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(86, 'std-23d6255acfc417', 'ADM-2026-0086', 'sch-fded1718575ebd', '\0\0\0!\0\0\0?', 'JSS1', 'Gold', 'RC2026-086', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(87, 'std-e8e64e2fb6247d', 'ADM-2026-0087', 'sch-fded1718575ebd', '\0\0A\0\"\0\0\0\0\0\0\0\0\0??#?A?', 'JSS1', 'Gold', 'RC2026-087', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(88, 'std-d48bb1bdbd8bfd', 'ADM-2026-0088', 'sch-fded1718575ebd', '\0\0\0\0\0\0~', 'JSS1', 'Gold', 'RC2026-088', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(89, 'std-289afb4b490f50', 'ADM-2026-0089', 'sch-fded1718575ebd', '\0\0A\0?/?@?', 'JSS1', 'Gold', 'RC2026-089', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(90, 'std-f746cbc3507a75', 'ADM-2026-0090', 'sch-fded1718575ebd', '\0\0\0@\0#\0\0\0?', 'JSS1', 'Gold', 'RC2026-090', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(91, 'std-eac7d84c29b063', 'ADM-2026-0091', 'sch-fded1718575ebd', '\0\0\0 \0\0\0?', 'JSS1', 'Gold', 'RC2026-091', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(92, 'std-1b9f05dbadeb6c', 'ADM-2026-0092', 'sch-fded1718575ebd', '\0\0\0\0\0\0?', 'JSS1', 'Gold', 'RC2026-092', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(93, 'std-a691499d0754f1', 'ADM-2026-0093', 'sch-fded1718575ebd', '\0\0\0$\0\0\0?', 'JSS1', 'Gold', 'RC2026-093', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(94, 'std-6e9fae91d9abb2', 'ADM-2026-0094', 'sch-fded1718575ebd', '\0\0A\0%\0\0\0\0\0\0\0\0\0 ???A?', 'JSS1', 'Gold', 'RC2026-094', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(95, 'std-c53f2f28bad5ab', 'ADM-2026-0095', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-095', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(96, 'std-120d71d2e6d824', 'ADM-2026-0096', 'sch-fded1718575ebd', '\0\0A\0?@?@?', 'JSS1', 'Gold', 'RC2026-096', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(97, 'std-2c045ce87ef3fb', 'ADM-2026-0097', 'sch-fded1718575ebd', '\0\0\0@\0&\0\0\0?', 'JSS1', 'Gold', 'RC2026-097', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(98, 'std-99b83c166f273b', 'ADM-2026-0098', 'sch-fded1718575ebd', '\0\0\0\'\0\0\0?', 'JSS1', 'Gold', 'RC2026-098', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(99, 'std-48584141224ab3', 'ADM-2026-0099', 'sch-fded1718575ebd', '\0\0', 'JSS1', 'Gold', 'RC2026-099', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(100, 'std-58b5dbf3c4c69b', 'ADM-2026-0100', 'sch-fded1718575ebd', '?', 'JSS1', 'Gold', 'RC2026-100', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(101, 'std-f6f0425e331734', 'ADM-2026-0101', 'sch-fded1718575ebd', '\0\0\0(\0\0\0?', 'JSS1', 'Gold', 'RC2026-101', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(102, 'std-2564daefbf7fca', 'ADM-2026-0102', 'sch-fded1718575ebd', '\0\0A\0)\0\0\0\0\0\0\0\0\0??H\"?A?', 'JSS1', 'Gold', 'RC2026-102', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(103, 'std-ac43c4455cefd3', 'ADM-2026-0103', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-103', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(104, 'std-b2e1a60b2576f5', 'ADM-2026-0104', 'sch-fded1718575ebd', '\0\0A\0@??@?', 'JSS1', 'Gold', 'RC2026-104', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(105, 'std-2d606694edb5a8', 'ADM-2026-0105', 'sch-fded1718575ebd', '@\0*\0\0\0?', 'JSS1', 'Gold', 'RC2026-105', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(106, 'std-274c37e1215d3d', 'ADM-2026-0106', 'sch-fded1718575ebd', '\0\0\'\0\0\0?', 'JSS1', 'Gold', 'RC2026-106', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(107, 'std-7d1568e5c75dcb', 'ADM-2026-0107', 'sch-fded1718575ebd', '\0\0+\0\0\0?', 'JSS1', 'Gold', 'RC2026-107', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(108, 'std-0320c74e775f40', 'ADM-2026-0108', 'sch-fded1718575ebd', '\0', '?', 'Gold', 'RC2026-108', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(109, 'std-4449bfc3192b13', 'ADM-2026-0109', 'sch-fded1718575ebd', '\0A\0-\0\0\0\0	\0\0\0\0\0\0?W#?A?', 'JSS1', 'Gold', 'RC2026-109', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(110, 'std-f698e3aaa8c04d', 'ADM-2026-0110', 'sch-fded1718575ebd', '\0\0\0\0\0~', 'JSS1', 'Gold', 'RC2026-110', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(111, 'std-206fe78b31e892', 'ADM-2026-0111', 'sch-fded1718575ebd', '\0A\0???@?', 'JSS1', 'Gold', 'RC2026-111', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(112, 'std-b77def33b536e8', 'ADM-2026-0112', 'sch-fded1718575ebd', '@\0.\0\0\0?', 'JSS1', 'Gold', 'RC2026-112', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(113, 'std-ec3f04c9152137', 'ADM-2026-0113', 'sch-fded1718575ebd', '\0\0\'\0\0\0?', 'JSS1', 'Gold', 'RC2026-113', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(114, 'std-04d822d8e515ef', 'ADM-2026-0114', 'sch-fded1718575ebd', '\0', 'JSS1', 'Gold', 'RC2026-114', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(115, 'std-adb7c93e0e2dfd', 'ADM-2026-0115', 'sch-fded1718575ebd', '?', 'JSS1', 'Gold', 'RC2026-115', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(116, 'std-49c5b32f6d61bc', 'ADM-2026-0116', 'sch-fded1718575ebd', '\0\0/\0\0\0?', 'JSS1', 'Gold', 'RC2026-116', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(117, 'std-31315a63f4d2f0', 'ADM-2026-0117', 'sch-fded1718575ebd', '\0A\00\0\0\0', 'JSS1', 'Gold', 'RC2026-117', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(118, 'std-2ceba05b33fc60', 'ADM-2026-0118', 'sch-fded1718575ebd', '\0\0\0\0px?-?A?', 'JSS1', 'Gold', 'RC2026-118', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(119, 'std-bcaf2b8494a57d', 'ADM-2026-0119', 'sch-fded1718575ebd', '\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-119', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(120, 'std-0b8d6cfe9f1b7f', 'ADM-2026-0120', 'sch-fded1718575ebd', '\0A\0 ?@?', 'JSS1', 'Gold', 'RC2026-120', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(121, 'std-ba915cd25f67b2', 'ADM-2026-0121', 'sch-fded1718575ebd', '@\01\0\0\0?', 'JSS1', 'Gold', 'RC2026-121', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(122, 'std-4121fec1a120c4', 'ADM-2026-0122', 'sch-fded1718575ebd', '\0\0\'\0\0\0?', 'JSS1', 'Gold', 'RC2026-122', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(123, 'std-a8f8581644c12e', 'ADM-2026-0123', 'sch-fded1718575ebd', '\0\0+\0\0\0?', 'JSS1', 'Gold', 'RC2026-123', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(124, 'std-ca9e68b248c822', 'ADM-2026-0124', 'sch-fded1718575ebd', '\0\02\0\0\0?', 'JSS1', 'Gold', 'RC2026-124', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(125, 'std-3a8ddc50816367', 'ADM-2026-0125', 'sch-fded1718575ebd', '\0A\03\0\0\0\0\0\0\0\0\0?$?8?A?', 'JSS1', 'Gold', 'RC2026-125', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(126, 'std-e6a15c03af83ef', 'ADM-2026-0126', 'sch-fded1718575ebd', '\0\0\0\0\0~', 'JSS1', 'Gold', 'RC2026-126', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(127, 'std-a32a49e352710d', 'ADM-2026-0127', 'sch-fded1718575ebd', '\0A\0@??@?', 'JSS1', 'Gold', 'RC2026-127', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(128, 'std-f2a304bfdb08ee', 'ADM-2026-0128', 'sch-fded1718575ebd', '\0\0\0@\04\0\0\0?', 'JSS1', 'Gold', 'RC2026-128', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(129, 'std-d543805cf22bba', 'ADM-2026-0129', 'sch-fded1718575ebd', '\0\0\05\0\0\0?', 'JSS1', 'Gold', 'RC2026-129', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(130, 'std-e798a93a246dfa', 'ADM-2026-0130', 'sch-fded1718575ebd', '\0\0', 'JSS1', 'Gold', 'RC2026-130', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(131, 'std-55318f836efa85', 'ADM-2026-0131', 'sch-fded1718575ebd', '?', 'JSS1', 'Gold', 'RC2026-131', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(132, 'std-89c6f3acaccda5', 'ADM-2026-0132', 'sch-fded1718575ebd', '\0\0\06\0\0\0?', 'JSS1', 'Gold', 'RC2026-132', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(133, 'std-9a200b93cb500f', 'ADM-2026-0133', 'sch-fded1718575ebd', '\0\0A\07\0\0\0\0\0\0\0\0\0P?!C?A?', 'JSS1', 'Gold', 'RC2026-133', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(134, 'std-ea393b5da8dfdb', 'ADM-2026-0134', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-134', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(135, 'std-35364a4c95050b', 'ADM-2026-0135', 'sch-fded1718575ebd', '\0\0A\0???@?', 'JSS1', 'Gold', 'RC2026-135', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(136, 'std-26a6117de9516b', 'ADM-2026-0136', 'sch-fded1718575ebd', '@\08\0\0\0?', 'JSS1', 'Gold', 'RC2026-136', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(137, 'std-6173f64c926cf0', 'ADM-2026-0137', 'sch-fded1718575ebd', '\0\05\0\0\0?', 'JSS1', 'Gold', 'RC2026-137', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(138, 'std-688d8c59fab878', 'ADM-2026-0138', 'sch-fded1718575ebd', '\0\0+\0\0\0?', 'JSS1', 'Gold', 'RC2026-138', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(139, 'std-0c0a0687db7b37', 'ADM-2026-0139', 'sch-fded1718575ebd', '\0\09\0\0\0?', 'JSS1', 'Gold', 'RC2026-139', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(140, 'std-389e81868645ff', 'ADM-2026-0140', 'sch-fded1718575ebd', '\0A\0:\0\0\0\0\r\0\0\0\0\0 }?M?A?', 'JSS1', 'Gold', 'RC2026-140', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(141, 'std-c1884aeb6d5bdd', 'ADM-2026-0141', 'sch-fded1718575ebd', '\0\0\0\0\0~', 'JSS1', 'Gold', 'RC2026-141', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(142, 'std-3c9a59ef26e6c8', 'ADM-2026-0142', 'sch-fded1718575ebd', '\0A\0???@?', 'JSS1', 'Gold', 'RC2026-142', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(143, 'std-fcdb2638dac14e', 'ADM-2026-0143', 'sch-fded1718575ebd', '\0\0\0@\0;\0\0\0?', 'JSS1', 'Gold', 'RC2026-143', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(144, 'std-42735e6f9fb0c8', 'ADM-2026-0144', 'sch-fded1718575ebd', '\0\0\05\0\0\0?', 'JSS1', 'Gold', 'RC2026-144', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(145, 'std-f438040c7fc6ce', 'ADM-2026-0145', 'sch-fded1718575ebd', '\0\0', 'JSS1', 'Gold', 'RC2026-145', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(146, 'std-03d1dfba660d6d', 'ADM-2026-0146', 'sch-fded1718575ebd', '?', 'JSS1', 'Gold', 'RC2026-146', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(147, 'std-1131ff182bb8c0', 'ADM-2026-0147', 'sch-fded1718575ebd', '\0\0\0<\0\0\0?', 'JSS1', 'Gold', 'RC2026-147', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(148, 'std-df38c8b4fccfec', 'ADM-2026-0148', 'sch-fded1718575ebd', '\0\0A\0=\0\0\0\0\0\0\0\0\0P#SX?A?', 'JSS1', 'Gold', 'RC2026-148', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(149, 'std-a5875419314255', 'ADM-2026-0149', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-149', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(150, 'std-7303cd8dce085f', 'ADM-2026-0150', 'sch-fded1718575ebd', '\0\0A\0???@?', 'JSS1', 'Gold', 'RC2026-150', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(151, 'std-2242ee1ce57d41', 'ADM-2026-0151', 'sch-fded1718575ebd', '\0\0\0@\0>\0\0\0?', 'JSS1', 'Gold', 'RC2026-151', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(152, 'std-a7ad7d7757c5f0', 'ADM-2026-0152', 'sch-fded1718575ebd', '\0\0\05\0\0\0?', 'JSS1', 'Gold', 'RC2026-152', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(153, 'std-8e5e77c513200c', 'ADM-2026-0153', 'sch-fded1718575ebd', '\0\0\0+\0\0\0?', 'JSS1', 'Gold', 'RC2026-153', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(154, 'std-ff5c81cba85a95', 'ADM-2026-0154', 'sch-fded1718575ebd', '\0\0\0?\0\0\0?', 'JSS1', 'Gold', 'RC2026-154', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(155, 'std-b907576e39366c', 'ADM-2026-0155', 'sch-fded1718575ebd', '\0\0A\0@\0\0\0\0\0\0\0\0\0@??b?A?', 'JSS1', 'Gold', 'RC2026-155', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(156, 'std-ef34556c4ec1d5', 'ADM-2026-0156', 'sch-fded1718575ebd', '\0\0\0\0\0\0~', 'JSS1', 'Gold', 'RC2026-156', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(157, 'std-1375bd6a29b013', 'ADM-2026-0157', 'sch-fded1718575ebd', '\0\0A\0???@?', 'JSS1', 'Gold', 'RC2026-157', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(158, 'std-ae64662716bd79', 'ADM-2026-0158', 'sch-fded1718575ebd', '\0\0\0@\0A\0\0\0?', 'JSS1', 'Gold', 'RC2026-158', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(159, 'std-9961c6489c4034', 'ADM-2026-0159', 'sch-fded1718575ebd', '\0\0\0	\0\0\0?', 'JSS1', 'Gold', 'RC2026-159', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(160, 'std-4221a5c3847d11', 'ADM-2026-0160', 'sch-fded1718575ebd', '\0\0', 'JSS1', 'Gold', 'RC2026-160', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(161, 'std-4933dbaa059b1a', 'ADM-2026-0161', 'sch-fded1718575ebd', '?', 'JSS1', 'Gold', 'RC2026-161', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(162, 'std-7b8140e86b8b39', 'ADM-2026-0162', 'sch-fded1718575ebd', '\0\0\0B\0\0\0?', 'JSS1', 'Gold', 'RC2026-162', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(163, 'std-76fdba0c9663b7', 'ADM-2026-0163', 'sch-fded1718575ebd', '\0\0A\0C\0\0\0\0\0\0\0\0\0?́m?A?', 'JSS1', 'Gold', 'RC2026-163', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(164, 'std-057daa06e8b594', 'ADM-2026-0164', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-164', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(165, 'std-fce83c0ab035ee', 'ADM-2026-0165', 'sch-fded1718575ebd', '\0\0A\0???@?', 'JSS1', 'Gold', 'RC2026-165', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(166, 'std-1e1bccce619c6c', 'ADM-2026-0166', 'sch-fded1718575ebd', '\0\0\0@\0D\0\0\0?', 'JSS1', 'Gold', 'RC2026-166', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(167, 'std-dd5aaffb6bf92f', 'ADM-2026-0167', 'sch-fded1718575ebd', '\0\0\0	\0\0\0?', 'JSS1', 'Gold', 'RC2026-167', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(168, 'std-f035e16ab946b1', 'ADM-2026-0168', 'sch-fded1718575ebd', '\0\0\0+\0\0\0?', 'JSS1', 'Gold', 'RC2026-168', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(169, 'std-0deb0d432ddc43', 'ADM-2026-0169', 'sch-fded1718575ebd', '\0\0\0E\0\0\0?', 'JSS1', 'Gold', 'RC2026-169', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(170, 'std-27ca7a08fbea1d', 'ADM-2026-0170', 'sch-fded1718575ebd', '\0\0A\0F\0\0\0\0\0\0\0\0\0 x?A?', 'JSS1', 'Gold', 'RC2026-170', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(171, 'std-bc4966408e17f9', 'ADM-2026-0171', 'sch-fded1718575ebd', '\0\0\0\0\0\0~', 'JSS1', 'Gold', 'RC2026-171', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(172, 'std-b9fc532f366a37', 'ADM-2026-0172', 'sch-fded1718575ebd', '\0\0A\0\0??@?', 'JSS1', 'Gold', 'RC2026-172', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(173, 'std-1f83a8508f525b', 'ADM-2026-0173', 'sch-fded1718575ebd', '\0\0\0@\0G\0\0\0?', 'JSS1', 'Gold', 'RC2026-173', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(174, 'std-49a18f740784af', 'ADM-2026-0174', 'sch-fded1718575ebd', '\0\0\0	\0\0\0?', 'JSS1', 'Gold', 'RC2026-174', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(175, 'std-f7694757343b0a', 'ADM-2026-0175', 'sch-fded1718575ebd', '\0\0', 'JSS1', 'Gold', 'RC2026-175', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(176, 'std-ddef9c4c704e06', 'ADM-2026-0176', 'sch-fded1718575ebd', '?', 'JSS1', 'Gold', 'RC2026-176', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(177, 'std-5bb3edbda8f6e8', 'ADM-2026-0177', 'sch-fded1718575ebd', '\0\0\0H\0\0\0?', 'JSS1', 'Gold', 'RC2026-177', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(178, 'std-7635a4d4e242a3', 'ADM-2026-0178', 'sch-fded1718575ebd', '\0\0A\0I\0\0\0\0\0\0\0\0\0?????A?', 'JSS1', 'Gold', 'RC2026-178', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(179, 'std-b9cf855cca69d7', 'ADM-2026-0179', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-179', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(180, 'std-d2c55c1bab9afe', 'ADM-2026-0180', 'sch-fded1718575ebd', '\0\0A\0???@?', 'JSS1', 'Gold', 'RC2026-180', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(181, 'std-cd49685c03b68b', 'ADM-2026-0181', 'sch-fded1718575ebd', '\0\0\0@\0J\0\0\0?', 'JSS1', 'Gold', 'RC2026-181', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(182, 'std-73c7bb60e809f2', 'ADM-2026-0182', 'sch-fded1718575ebd', '\0\0\0	\0\0\0?', 'JSS1', 'Gold', 'RC2026-182', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(183, 'std-2ee64b76a42d56', 'ADM-2026-0183', 'sch-fded1718575ebd', '\0\0\0+\0\0\0?', 'JSS1', 'Gold', 'RC2026-183', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(184, 'std-fc843d90559079', 'ADM-2026-0184', 'sch-fded1718575ebd', '\0\0\0K\0\0\0?', 'JSS1', 'Gold', 'RC2026-184', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(185, 'std-8054b0a122833f', 'ADM-2026-0185', 'sch-fded1718575ebd', '\0\0A\0L\0\0\0\0\0\0\0\0\0\0ܵ??A?', 'JSS1', 'Gold', 'RC2026-185', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(186, 'std-b5cfd5449b1fc1', 'ADM-2026-0186', 'sch-fded1718575ebd', '\0\0\0\0\0\0~', 'JSS1', 'Gold', 'RC2026-186', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(187, 'std-6c99373fbc5eca', 'ADM-2026-0187', 'sch-fded1718575ebd', '\0\0A\0?w?@?', 'JSS1', 'Gold', 'RC2026-187', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(188, 'std-3e2a67e22b0b1e', 'ADM-2026-0188', 'sch-fded1718575ebd', '\0\0\0@\0M\0\0\0?', 'JSS1', 'Gold', 'RC2026-188', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(189, 'std-6927eea570cdcd', 'ADM-2026-0189', 'sch-fded1718575ebd', '\0\0\0	\0\0\0?', 'JSS1', 'Gold', 'RC2026-189', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(190, 'std-7bf45b84dbc3ce', 'ADM-2026-0190', 'sch-fded1718575ebd', '\0\0', 'JSS1', 'Gold', 'RC2026-190', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(191, 'std-35716dd3901663', 'ADM-2026-0191', 'sch-fded1718575ebd', '?', 'JSS1', 'Gold', 'RC2026-191', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(192, 'std-c9173df326d189', 'ADM-2026-0192', 'sch-fded1718575ebd', '\0\0\0N\0\0\0?', 'JSS1', 'Gold', 'RC2026-192', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(193, 'std-fd18618d3d7e06', 'ADM-2026-0193', 'sch-fded1718575ebd', '\0\0A\0O\0\0\0\0\0\0\0\0\0p?N??A?', 'JSS1', 'Gold', 'RC2026-193', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(194, 'std-cbb05fad69a9e3', 'ADM-2026-0194', 'sch-fded1718575ebd', '\0\0\0\r\0\0\0~', 'JSS1', 'Gold', 'RC2026-194', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(195, 'std-fbd6708df62f66', 'ADM-2026-0195', 'sch-fded1718575ebd', '\0\0A\0???@?', 'JSS1', 'Gold', 'RC2026-195', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(196, 'std-2422fef5908ca0', 'ADM-2026-0196', 'sch-fded1718575ebd', '\0\0\0@\0P\0\0\0?', 'JSS1', 'Gold', 'RC2026-196', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(197, 'std-76a8b4630d1e5d', 'ADM-2026-0197', 'sch-fded1718575ebd', '\0\0\0	\0\0\0?', 'JSS1', 'Gold', 'RC2026-197', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(198, 'std-b1acb4995e5337', 'ADM-2026-0198', 'sch-fded1718575ebd', '\0\0\0+\0\0\0?', 'JSS1', 'Gold', 'RC2026-198', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(199, 'std-74c306f99b2728', 'ADM-2026-0199', 'sch-fded1718575ebd', '\0\0\0Q\0\0\0?', 'JSS1', 'Gold', 'RC2026-199', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(200, 'std-6fe14e32aef007', 'ADM-2026-0200', 'sch-fded1718575ebd', '\0\0A\0R\0\0\0\0\0\0\0\0\0?4???A?', 'JSS1', 'Gold', 'RC2026-200', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(201, 'std-ef2a7269ca2cc1', 'ADM-2026-0201', 'sch-fded1718575ebd', '\0\0\0\0\0\0~', 'JSS1', 'Gold', 'RC2026-201', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(202, 'std-3d9e2b584b7777', 'ADM-2026-0202', 'sch-fded1718575ebd', '\0\0A\0?z?@?\00\0?\0\0?p\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0t\0>\0?\0\0\0\0@\0\0\0<\0\0\0\0\0\0\0?\0?\0\0\0\0\0\0\0\0\0\0', 'JSS1', 'Gold', 'RC2026-202', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(203, 'std-ea8cd1c6145414', 'ADM-2026-0203', 'sch-fded1718575ebd', 'g\0g\0\0\0\0\0\0\0\0\0\0\0????D', 'JSS1', 'Gold', 'RC2026-203', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(204, 'std-9aead32588b63a', 'ADM-2026-0204', 'sch-fded1718575ebd', '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0	\0\0\0????\0\0\0\0\0\0\r\0\0\0????\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0????????????????', 'JSS1', 'Gold', 'RC2026-204', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(205, 'std-9a2f1dae4e3274', 'ADM-2026-0205', 'sch-fded1718575ebd', '??', 'JSS1', 'Gold', 'RC2026-205', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(206, 'std-8af445b73ea24d', 'ADM-2026-0206', 'sch-fded1718575ebd', '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0?????Oh??\0+\'??0\0\0\0?\0\0\0\0\0\0\0\0\08\0\0\0\0\0\0?@\0\0\0\0\0\0H\0\0\0\0\0\0p\0\0\0\r\0\0\0|\0\0\0\0\0\0?\0\0\0\0', 'JSS1', 'Gold', 'RC2026-206', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL),
(207, 'std-380bede46cca13', 'ADM-2026-0207', 'sch-fded1718575ebd', '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0??՜.??\0+', '??D\0\0\0??՜.??\0+', '???\0\0\0H\0\0\0\0\0\0\0\0\0(\0\0\0\0\0\0?0\0\0\0\0\0\08\0\0\0\0\0\0@\0\0\0\0\0\0', 'RC2026-207', '', '', '', NULL, 'Active', NULL, '0000-00-00', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_behavior_records`
--

CREATE TABLE `student_behavior_records` (
  `id` int(11) NOT NULL,
  `record_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `incident_type` varchar(30) DEFAULT 'Merit',
  `points` int(11) DEFAULT 5,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `action_taken` varchar(200) DEFAULT 'Notice Sent to Parent',
  `reported_by` varchar(150) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_class_history`
--

CREATE TABLE `student_class_history` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `session_name` varchar(50) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `arm_name` varchar(50) DEFAULT NULL,
  `event_type` enum('Initial','Promotion','Graduation','Repeat') NOT NULL DEFAULT 'Initial',
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_domain_ratings`
--

CREATE TABLE `student_domain_ratings` (
  `id` int(11) NOT NULL,
  `rating_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `session_name` varchar(50) NOT NULL,
  `term_name` varchar(50) NOT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `arm_name` varchar(50) DEFAULT NULL,
  `entered_by` varchar(50) DEFAULT NULL COMMENT 'staff_uuid of entering class teacher',
  `domain_type` varchar(20) NOT NULL COMMENT 'Affective or Psychomotor',
  `trait_name` varchar(100) NOT NULL COMMENT 'e.g. Punctuality, Neatness, Sports, Handwriting',
  `rating` tinyint(4) DEFAULT 3 COMMENT '1-5 scale'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_fee_credits`
--

CREATE TABLE `student_fee_credits` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_fee_credit_log`
--

CREATE TABLE `student_fee_credit_log` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `change_type` enum('Overpayment','AppliedToInvoice','ManualAdjustment') NOT NULL,
  `amount` decimal(12,2) NOT NULL COMMENT 'Positive = credit added, negative = credit consumed',
  `related_invoice_uuid` varchar(50) DEFAULT NULL,
  `related_receipt_uuid` varchar(50) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subject_teacher_assignments`
--

CREATE TABLE `subject_teacher_assignments` (
  `id` int(11) NOT NULL,
  `assignment_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `teacher_name` varchar(150) NOT NULL,
  `periods_per_week` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_reminders`
--

CREATE TABLE `subscription_reminders` (
  `id` int(11) NOT NULL,
  `reminder_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `date` date NOT NULL,
  `is_read` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `testimonial_templates`
--

CREATE TABLE `testimonial_templates` (
  `id` int(11) NOT NULL,
  `template_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL DEFAULT 'Standard Testimonial',
  `body_html` longtext NOT NULL,
  `is_default` tinyint(4) DEFAULT 0,
  `updated_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timetables`
--

CREATE TABLE `timetables` (
  `id` int(11) NOT NULL,
  `timetable_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `arm_name` varchar(100) NOT NULL DEFAULT '',
  `day_of_week` varchar(20) NOT NULL,
  `period_time` varchar(50) NOT NULL,
  `subject` varchar(150) DEFAULT NULL,
  `teacher_name` varchar(150) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `has_clash` tinyint(1) DEFAULT 0,
  `clash_overridden` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timetable_days`
--

CREATE TABLE `timetable_days` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `day_name` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timetable_periods`
--

CREATE TABLE `timetable_periods` (
  `id` int(11) NOT NULL,
  `period_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `label` varchar(50) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `timetable_periods`
--

INSERT INTO `timetable_periods` (`id`, `period_uuid`, `school_uuid`, `label`, `sort_order`, `created_at`) VALUES
(1, 'ttp-eb5aef7f48c8e6', 'sch-fded1718575ebd', '7:30-8:00', 1, '2026-08-08 02:10:17');

-- --------------------------------------------------------

--
-- Table structure for table `timetable_publications`
--

CREATE TABLE `timetable_publications` (
  `id` int(11) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `session_name` varchar(50) DEFAULT NULL,
  `term_name` varchar(50) DEFAULT NULL,
  `published_by` varchar(150) DEFAULT NULL,
  `published_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timetable_templates`
--

CREATE TABLE `timetable_templates` (
  `id` int(11) NOT NULL,
  `template_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `days_json` text NOT NULL,
  `periods_json` text NOT NULL,
  `created_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transport_allocations`
--

CREATE TABLE `transport_allocations` (
  `id` int(11) NOT NULL,
  `allocation_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `route_uuid` varchar(50) NOT NULL,
  `student_uuid` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `pickup_point` varchar(150) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transport_routes`
--

CREATE TABLE `transport_routes` (
  `id` int(11) NOT NULL,
  `route_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `route_name` varchar(150) NOT NULL,
  `driver_name` varchar(150) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `capacity` int(11) NOT NULL DEFAULT 0,
  `fee_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `portal_type` enum('platform','school_admin','staff','student','parent') NOT NULL DEFAULT 'staff' COMMENT 'Determines which portal/dashboard this user accesses',
  `phone` varchar(50) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT 'Academics',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `must_reset_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `temp_password_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_uuid`, `school_uuid`, `name`, `email`, `password_hash`, `role`, `portal_type`, `phone`, `photo_path`, `department`, `created_at`, `failed_login_attempts`, `locked_until`, `must_reset_password`, `last_login_at`, `temp_password_expires_at`) VALUES
(7, 'usr-platform-mgr-0001', NULL, 'Precious Philip Godwin', 'preshtrumpgodwin@gmail.com', '$2y$10$7g.6rsDZaGvbHt2JrtXAXOXi/2iY/e22NtiZ7Plch4dnxR1pl1yhy', 'Platform Manager', 'platform', NULL, NULL, 'Platform', '2026-08-07 07:58:48', 0, NULL, 0, '2026-08-08 02:17:14', NULL),
(8, 'usr-8fbcd0818a6868', 'sch-fded1718575ebd', 'Zaruq Zainab', 'zaruqzainab@gmail.com', '$2y$10$YipCqA4aBfmOUabBc6fn0uGwJ/6JEPgUe2JTsqxBRgw.6n59WcPsK', 'School Admin', 'staff', NULL, NULL, 'Academics', '2026-08-08 01:17:34', 0, NULL, 0, '2026-08-08 19:30:22', '2026-08-11 02:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `virtual_classes`
--

CREATE TABLE `virtual_classes` (
  `id` int(11) NOT NULL,
  `class_session_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `meeting_link` varchar(500) NOT NULL,
  `platform` varchar(20) DEFAULT 'Other',
  `scheduled_at` datetime DEFAULT NULL,
  `created_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visitor_logs`
--

CREATE TABLE `visitor_logs` (
  `id` int(11) NOT NULL,
  `visitor_uuid` varchar(50) NOT NULL,
  `school_uuid` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `host_name` varchar(150) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Checked In',
  `checked_in_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `checked_out_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_arms`
--
ALTER TABLE `academic_arms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_academic_arms_1` (`school_uuid`,`class_name`,`arm_name`),
  ADD KEY `idx_academic_arms_1` (`school_uuid`,`class_name`);

--
-- Indexes for table `academic_classes`
--
ALTER TABLE `academic_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_academic_classes_1` (`school_uuid`,`class_name`);

--
-- Indexes for table `academic_sessions`
--
ALTER TABLE `academic_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_academic_sessions_school_uuid` (`school_uuid`);

--
-- Indexes for table `academic_subjects`
--
ALTER TABLE `academic_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_academic_subjects_school_uuid` (`school_uuid`);

--
-- Indexes for table `academic_terms`
--
ALTER TABLE `academic_terms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_academic_terms_school_uuid` (`school_uuid`);

--
-- Indexes for table `alumni`
--
ALTER TABLE `alumni`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_alumni_school_uuid` (`school_uuid`),
  ADD KEY `fk_alumni_student_uuid` (`student_uuid`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_assignments_1` (`assignment_uuid`),
  ADD KEY `idx_assignments_1` (`school_uuid`,`class_name`,`approval_status`),
  ADD KEY `fk_assignments_assigned_by_staff_uuid` (`assigned_by_staff_uuid`),
  ADD KEY `fk_assignments_linked_appointment_uuid` (`linked_appointment_uuid`);

--
-- Indexes for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_assignment_submissions_1` (`assignment_uuid`,`student_uuid`),
  ADD KEY `idx_assignment_submissions_1` (`assignment_uuid`,`student_uuid`),
  ADD KEY `fk_assignment_submissions_school_uuid` (`school_uuid`),
  ADD KEY `fk_assignment_submissions_student_uuid` (`student_uuid`);

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attachments_school_uuid` (`school_uuid`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_attendance_records_1` (`school_uuid`,`student_uuid`,`date`),
  ADD KEY `fk_attendance_records_student_uuid` (`student_uuid`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_logs_1` (`school_uuid`),
  ADD KEY `idx_audit_logs_2` (`created_at`);

--
-- Indexes for table `broadcast_messages`
--
ALTER TABLE `broadcast_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_broadcast_messages_school_uuid` (`school_uuid`);

--
-- Indexes for table `cafeteria_billing`
--
ALTER TABLE `cafeteria_billing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cafeteria_billing_1` (`school_uuid`),
  ADD KEY `idx_cafeteria_billing_2` (`student_uuid`),
  ADD KEY `fk_cafeteria_billing_plan_uuid` (`plan_uuid`);

--
-- Indexes for table `cafeteria_meal_plans`
--
ALTER TABLE `cafeteria_meal_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cafeteria_meal_plans_1` (`plan_uuid`),
  ADD KEY `idx_cafeteria_meal_plans_1` (`school_uuid`),
  ADD KEY `idx_cafeteria_meal_plans_2` (`student_uuid`);

--
-- Indexes for table `cafeteria_menu_items`
--
ALTER TABLE `cafeteria_menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cafeteria_menu_items_1` (`school_uuid`);

--
-- Indexes for table `career_advisory_notes`
--
ALTER TABLE `career_advisory_notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_career_advisory_notes_1` (`note_uuid`),
  ADD KEY `idx_career_advisory_notes_1` (`school_uuid`,`student_uuid`),
  ADD KEY `fk_career_advisory_notes_student_uuid` (`student_uuid`);

--
-- Indexes for table `cbt_questions`
--
ALTER TABLE `cbt_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cbt_questions_school_uuid` (`school_uuid`),
  ADD KEY `fk_cbt_questions_test_uuid` (`test_uuid`);

--
-- Indexes for table `cbt_tests`
--
ALTER TABLE `cbt_tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cbt_tests_1` (`test_uuid`),
  ADD KEY `fk_cbt_tests_school_uuid` (`school_uuid`);

--
-- Indexes for table `class_teacher_assignments`
--
ALTER TABLE `class_teacher_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_class_teacher_assignments_1` (`school_uuid`,`staff_uuid`,`class_name`,`arm_name`,`session_name`,`term_name`),
  ADD KEY `fk_class_teacher_assignments_staff_uuid` (`staff_uuid`);

--
-- Indexes for table `email_log`
--
ALTER TABLE `email_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_email_log_school_uuid` (`school_uuid`);

--
-- Indexes for table `essay_evaluations`
--
ALTER TABLE `essay_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_essay_evaluations_school_uuid` (`school_uuid`),
  ADD KEY `fk_essay_evaluations_student_uuid` (`student_uuid`);

--
-- Indexes for table `fee_structures`
--
ALTER TABLE `fee_structures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_fee_structures_2` (`fee_uuid`),
  ADD KEY `idx_fee_structures_1` (`school_uuid`,`class_name`,`term_name`);

--
-- Indexes for table `flutterwave_settings`
--
ALTER TABLE `flutterwave_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_flutterwave_settings_1` (`school_uuid`);

--
-- Indexes for table `gate_attendance_logs`
--
ALTER TABLE `gate_attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gate_attendance_logs_1` (`school_uuid`,`person_uuid`,`timestamp`);

--
-- Indexes for table `healthcare_records`
--
ALTER TABLE `healthcare_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_healthcare_records_school_uuid` (`school_uuid`);

--
-- Indexes for table `hostels`
--
ALTER TABLE `hostels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_hostels_1` (`hostel_uuid`),
  ADD KEY `fk_hostels_school_uuid` (`school_uuid`);

--
-- Indexes for table `hostel_allocations`
--
ALTER TABLE `hostel_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_hostel_allocations_school_uuid` (`school_uuid`),
  ADD KEY `fk_hostel_allocations_student_uuid` (`student_uuid`),
  ADD KEY `fk_hostel_allocations_hostel_uuid` (`hostel_uuid`);

--
-- Indexes for table `hr_employment_letters_issued`
--
ALTER TABLE `hr_employment_letters_issued`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_hr_employment_letters_issued_1` (`letter_uuid`),
  ADD KEY `idx_hr_employment_letters_issued_1` (`school_uuid`,`staff_uuid`),
  ADD KEY `fk_hr_employment_letters_issued_staff_uuid` (`staff_uuid`),
  ADD KEY `fk_hr_employment_letters_issued_template_uuid` (`template_uuid`);

--
-- Indexes for table `hr_employment_letter_templates`
--
ALTER TABLE `hr_employment_letter_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_hr_employment_letter_templates_1` (`template_uuid`),
  ADD KEY `idx_hr_employment_letter_templates_1` (`school_uuid`);

--
-- Indexes for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lesson_plans_school_uuid` (`school_uuid`),
  ADD KEY `fk_lesson_plans_teacher_uuid` (`teacher_uuid`);

--
-- Indexes for table `library_books`
--
ALTER TABLE `library_books`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_library_books_1` (`book_uuid`),
  ADD KEY `fk_library_books_school_uuid` (`school_uuid`);

--
-- Indexes for table `library_checkouts`
--
ALTER TABLE `library_checkouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_library_checkouts_school_uuid` (`school_uuid`),
  ADD KEY `fk_library_checkouts_book_uuid` (`book_uuid`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_school_uuid` (`school_uuid`),
  ADD KEY `fk_notifications_recipient_uuid` (`recipient_uuid`);

--
-- Indexes for table `notification_log`
--
ALTER TABLE `notification_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_notification_log_1` (`log_uuid`),
  ADD KEY `idx_notification_log_1` (`school_uuid`),
  ADD KEY `idx_notification_log_2` (`sent_at`);

--
-- Indexes for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_notification_templates_1` (`template_uuid`),
  ADD KEY `idx_notification_templates_1` (`school_uuid`,`category`,`audience`),
  ADD KEY `idx_notification_templates_2` (`school_uuid`,`trigger_key`);

--
-- Indexes for table `omr_answer_keys`
--
ALTER TABLE `omr_answer_keys`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_omr_answer_keys_school_uuid` (`school_uuid`),
  ADD KEY `fk_omr_answer_keys_sheet_uuid` (`sheet_uuid`);

--
-- Indexes for table `omr_evaluations`
--
ALTER TABLE `omr_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_omr_evaluations_school_uuid` (`school_uuid`),
  ADD KEY `fk_omr_evaluations_student_uuid` (`student_uuid`),
  ADD KEY `fk_omr_evaluations_sheet_student_uuid` (`sheet_student_uuid`);

--
-- Indexes for table `omr_sheets`
--
ALTER TABLE `omr_sheets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_omr_sheets_1` (`sheet_uuid`),
  ADD KEY `fk_omr_sheets_school_uuid` (`school_uuid`);

--
-- Indexes for table `omr_sheet_students`
--
ALTER TABLE `omr_sheet_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_omr_sheet_students_1` (`sheet_student_uuid`),
  ADD UNIQUE KEY `uk_omr_sheet_students_2` (`sheet_uuid`,`serial_code`),
  ADD KEY `idx_omr_sheet_students_1` (`sheet_uuid`),
  ADD KEY `idx_omr_sheet_students_2` (`school_uuid`),
  ADD KEY `fk_omr_sheet_students_student_uuid` (`student_uuid`);

--
-- Indexes for table `onboarding_requests`
--
ALTER TABLE `onboarding_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_parents_1` (`parent_uuid`),
  ADD KEY `fk_parents_school_uuid` (`school_uuid`);

--
-- Indexes for table `parent_teacher_appointments`
--
ALTER TABLE `parent_teacher_appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_parent_teacher_appointments_1` (`appointment_uuid`),
  ADD KEY `fk_parent_teacher_appointments_school_uuid` (`school_uuid`),
  ADD KEY `fk_parent_teacher_appointments_teacher_uuid` (`teacher_uuid`),
  ADD KEY `fk_parent_teacher_appointments_parent_uuid` (`parent_uuid`);

--
-- Indexes for table `parent_teacher_messages`
--
ALTER TABLE `parent_teacher_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_parent_teacher_messages_school_uuid` (`school_uuid`),
  ADD KEY `fk_parent_teacher_messages_sender_uuid` (`sender_uuid`),
  ADD KEY `fk_parent_teacher_messages_receiver_uuid` (`receiver_uuid`);

--
-- Indexes for table `payment_requests`
--
ALTER TABLE `payment_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_payment_requests_1` (`request_uuid`),
  ADD KEY `idx_payment_requests_1` (`school_uuid`,`status`),
  ADD KEY `fk_payment_requests_student_uuid` (`student_uuid`),
  ADD KEY `fk_payment_requests_parent_uuid` (`parent_uuid`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_payment_transactions_1` (`reference`),
  ADD KEY `idx_payment_transactions_1` (`school_uuid`),
  ADD KEY `fk_payment_transactions_student_uuid` (`student_uuid`),
  ADD KEY `fk_payment_transactions_parent_uuid` (`parent_uuid`),
  ADD KEY `fk_payment_transactions_invoice_uuid` (`invoice_uuid`);

--
-- Indexes for table `platform_feature_catalog`
--
ALTER TABLE `platform_feature_catalog`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pricing_packages`
--
ALTER TABLE `pricing_packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `printed_exam_papers`
--
ALTER TABLE `printed_exam_papers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_printed_exam_papers_1` (`paper_uuid`),
  ADD KEY `idx_printed_exam_papers_1` (`school_uuid`);

--
-- Indexes for table `promotion_log`
--
ALTER TABLE `promotion_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_promotion_log_1` (`school_uuid`,`session_name`,`from_class`);

--
-- Indexes for table `public_applications`
--
ALTER TABLE `public_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_public_applications_school_uuid` (`school_uuid`);

--
-- Indexes for table `question_bank`
--
ALTER TABLE `question_bank`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_question_bank_1` (`question_uuid`),
  ADD KEY `idx_question_bank_1` (`school_uuid`,`subject_name`,`class_name`);

--
-- Indexes for table `report_cards`
--
ALTER TABLE `report_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_report_cards_school_uuid` (`school_uuid`),
  ADD KEY `fk_report_cards_student_uuid` (`student_uuid`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_results_school_uuid` (`school_uuid`),
  ADD KEY `fk_results_student_uuid` (`student_uuid`);

--
-- Indexes for table `result_assessment_scores`
--
ALTER TABLE `result_assessment_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_result_assessment_scores_1` (`school_uuid`,`student_uuid`,`session_name`,`term_name`,`subject_name`,`config_uuid`),
  ADD KEY `fk_result_assessment_scores_student_uuid` (`student_uuid`);

--
-- Indexes for table `result_slip_templates`
--
ALTER TABLE `result_slip_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_result_slip_templates_1` (`template_uuid`),
  ADD KEY `idx_result_slip_templates_1` (`school_uuid`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_role_permissions_school_uuid` (`school_uuid`);

--
-- Indexes for table `schema_versions`
--
ALTER TABLE `schema_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_schema_versions_1` (`version_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_schools_1` (`school_uuid`),
  ADD KEY `fk_schools_school_admin_uuid` (`school_admin_uuid`),
  ADD KEY `fk_schools_active_result_slip_template_uuid` (`active_result_slip_template_uuid`);

--
-- Indexes for table `school_calendar_days`
--
ALTER TABLE `school_calendar_days`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_school_calendar_days_1` (`school_uuid`,`calendar_date`);

--
-- Indexes for table `school_credit_balances`
--
ALTER TABLE `school_credit_balances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_school_credit_balances_school_uuid` (`school_uuid`);

--
-- Indexes for table `school_feature_access`
--
ALTER TABLE `school_feature_access`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_school_feature_access_school_uuid` (`school_uuid`);

--
-- Indexes for table `school_invoices`
--
ALTER TABLE `school_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_school_invoices_1` (`invoice_uuid`),
  ADD KEY `idx_school_invoices_1` (`student_uuid`),
  ADD KEY `fk_school_invoices_school_uuid` (`school_uuid`);

--
-- Indexes for table `school_notices_calendar`
--
ALTER TABLE `school_notices_calendar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_school_notices_calendar_school_uuid` (`school_uuid`);

--
-- Indexes for table `school_receipts`
--
ALTER TABLE `school_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_school_receipts_1` (`receipt_uuid`),
  ADD KEY `fk_school_receipts_school_uuid` (`school_uuid`),
  ADD KEY `fk_school_receipts_invoice_uuid` (`invoice_uuid`);

--
-- Indexes for table `school_settings`
--
ALTER TABLE `school_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_school_settings_school_uuid` (`school_uuid`);

--
-- Indexes for table `shareable_page_tokens`
--
ALTER TABLE `shareable_page_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_shareable_page_tokens_school_uuid` (`school_uuid`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_staff_1` (`staff_uuid`),
  ADD KEY `fk_staff_school_uuid` (`school_uuid`),
  ADD KEY `fk_staff_user_uuid` (`user_uuid`);

--
-- Indexes for table `staff_appraisals`
--
ALTER TABLE `staff_appraisals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_staff_appraisals_school_uuid` (`school_uuid`),
  ADD KEY `fk_staff_appraisals_staff_uuid` (`staff_uuid`);

--
-- Indexes for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_staff_attendance_1` (`record_uuid`),
  ADD UNIQUE KEY `uk_staff_attendance_2` (`school_uuid`,`staff_uuid`,`date`),
  ADD KEY `idx_staff_attendance_1` (`school_uuid`,`date`),
  ADD KEY `fk_staff_attendance_staff_uuid` (`staff_uuid`);

--
-- Indexes for table `staff_feature_permissions`
--
ALTER TABLE `staff_feature_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_staff_feature_permissions_school_uuid` (`school_uuid`),
  ADD KEY `fk_staff_feature_permissions_staff_uuid` (`staff_uuid`);

--
-- Indexes for table `staff_leave_requests`
--
ALTER TABLE `staff_leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_staff_leave_requests_school_uuid` (`school_uuid`),
  ADD KEY `fk_staff_leave_requests_staff_uuid` (`staff_uuid`);

--
-- Indexes for table `staff_payslips`
--
ALTER TABLE `staff_payslips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_staff_payslips_school_uuid` (`school_uuid`),
  ADD KEY `fk_staff_payslips_staff_uuid` (`staff_uuid`);

--
-- Indexes for table `staff_subject_assignments`
--
ALTER TABLE `staff_subject_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_staff_subject_assignments_1` (`school_uuid`,`staff_uuid`,`subject_name`,`class_name`,`arm_name`,`session_name`,`term_name`),
  ADD UNIQUE KEY `uk_staff_subject_assignments_2` (`school_uuid`,`staff_uuid`,`subject_name`,`class_name`,`arm_name`,`session_name`,`term_name`),
  ADD KEY `idx_staff_subject_assignments_1` (`school_uuid`,`class_name`,`arm_name`,`session_name`,`term_name`),
  ADD KEY `fk_staff_subject_assignments_staff_uuid` (`staff_uuid`);

--
-- Indexes for table `store_inventory`
--
ALTER TABLE `store_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_store_inventory_school_uuid` (`school_uuid`);

--
-- Indexes for table `store_pos_sales`
--
ALTER TABLE `store_pos_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_store_pos_sales_school_uuid` (`school_uuid`),
  ADD KEY `fk_store_pos_sales_student_uuid` (`student_uuid`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_students_2` (`student_uuid`),
  ADD UNIQUE KEY `uk_students_1` (`school_uuid`,`admission_number`),
  ADD KEY `fk_students_parent_uuid` (`parent_uuid`);

--
-- Indexes for table `student_behavior_records`
--
ALTER TABLE `student_behavior_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_student_behavior_records_school_uuid` (`school_uuid`),
  ADD KEY `fk_student_behavior_records_student_uuid` (`student_uuid`);

--
-- Indexes for table `student_class_history`
--
ALTER TABLE `student_class_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_student_class_history_1` (`school_uuid`,`student_uuid`,`session_name`),
  ADD KEY `fk_student_class_history_student_uuid` (`student_uuid`);

--
-- Indexes for table `student_domain_ratings`
--
ALTER TABLE `student_domain_ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_student_domain_ratings_school_uuid` (`school_uuid`),
  ADD KEY `fk_student_domain_ratings_student_uuid` (`student_uuid`);

--
-- Indexes for table `student_fee_credits`
--
ALTER TABLE `student_fee_credits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_student_fee_credits_1` (`school_uuid`,`student_uuid`),
  ADD KEY `fk_student_fee_credits_student_uuid` (`student_uuid`);

--
-- Indexes for table `student_fee_credit_log`
--
ALTER TABLE `student_fee_credit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_student_fee_credit_log_school_uuid` (`school_uuid`),
  ADD KEY `fk_student_fee_credit_log_student_uuid` (`student_uuid`),
  ADD KEY `fk_student_fee_credit_log_related_invoice_uuid` (`related_invoice_uuid`),
  ADD KEY `fk_student_fee_credit_log_related_receipt_uuid` (`related_receipt_uuid`);

--
-- Indexes for table `subject_teacher_assignments`
--
ALTER TABLE `subject_teacher_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_subject_teacher_assignments_1` (`assignment_uuid`),
  ADD KEY `idx_subject_teacher_assignments_1` (`school_uuid`,`class_name`);

--
-- Indexes for table `subscription_reminders`
--
ALTER TABLE `subscription_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subscription_reminders_school_uuid` (`school_uuid`);

--
-- Indexes for table `testimonial_templates`
--
ALTER TABLE `testimonial_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_testimonial_templates_1` (`template_uuid`),
  ADD KEY `idx_testimonial_templates_1` (`school_uuid`);

--
-- Indexes for table `timetables`
--
ALTER TABLE `timetables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_timetables_1` (`timetable_uuid`),
  ADD UNIQUE KEY `uk_timetables_2` (`school_uuid`,`class_name`,`arm_name`,`day_of_week`,`period_time`),
  ADD KEY `idx_timetables_1` (`school_uuid`,`class_name`,`arm_name`),
  ADD KEY `idx_timetables_2` (`school_uuid`,`day_of_week`);

--
-- Indexes for table `timetable_days`
--
ALTER TABLE `timetable_days`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_timetable_days_1` (`school_uuid`,`day_name`);

--
-- Indexes for table `timetable_periods`
--
ALTER TABLE `timetable_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_timetable_periods_1` (`period_uuid`),
  ADD KEY `idx_timetable_periods_1` (`school_uuid`);

--
-- Indexes for table `timetable_publications`
--
ALTER TABLE `timetable_publications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_timetable_publications_1` (`school_uuid`,`session_name`,`term_name`);

--
-- Indexes for table `timetable_templates`
--
ALTER TABLE `timetable_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_timetable_templates_1` (`template_uuid`),
  ADD KEY `idx_timetable_templates_1` (`school_uuid`);

--
-- Indexes for table `transport_allocations`
--
ALTER TABLE `transport_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transport_allocations_school_uuid` (`school_uuid`),
  ADD KEY `fk_transport_allocations_student_uuid` (`student_uuid`),
  ADD KEY `fk_transport_allocations_route_uuid` (`route_uuid`);

--
-- Indexes for table `transport_routes`
--
ALTER TABLE `transport_routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_transport_routes_1` (`route_uuid`),
  ADD KEY `fk_transport_routes_school_uuid` (`school_uuid`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_users_1` (`user_uuid`),
  ADD KEY `fk_users_school_uuid` (`school_uuid`);

--
-- Indexes for table `virtual_classes`
--
ALTER TABLE `virtual_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_virtual_classes_1` (`class_session_uuid`),
  ADD KEY `idx_virtual_classes_1` (`school_uuid`),
  ADD KEY `idx_virtual_classes_2` (`scheduled_at`);

--
-- Indexes for table `visitor_logs`
--
ALTER TABLE `visitor_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_visitor_logs_1` (`visitor_uuid`),
  ADD KEY `idx_visitor_logs_1` (`school_uuid`),
  ADD KEY `idx_visitor_logs_2` (`checked_in_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_arms`
--
ALTER TABLE `academic_arms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `academic_classes`
--
ALTER TABLE `academic_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `academic_sessions`
--
ALTER TABLE `academic_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `academic_subjects`
--
ALTER TABLE `academic_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `academic_terms`
--
ALTER TABLE `academic_terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `alumni`
--
ALTER TABLE `alumni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `broadcast_messages`
--
ALTER TABLE `broadcast_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cafeteria_billing`
--
ALTER TABLE `cafeteria_billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cafeteria_meal_plans`
--
ALTER TABLE `cafeteria_meal_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cafeteria_menu_items`
--
ALTER TABLE `cafeteria_menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `career_advisory_notes`
--
ALTER TABLE `career_advisory_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cbt_questions`
--
ALTER TABLE `cbt_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbt_tests`
--
ALTER TABLE `cbt_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_teacher_assignments`
--
ALTER TABLE `class_teacher_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_log`
--
ALTER TABLE `email_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `essay_evaluations`
--
ALTER TABLE `essay_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fee_structures`
--
ALTER TABLE `fee_structures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `flutterwave_settings`
--
ALTER TABLE `flutterwave_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gate_attendance_logs`
--
ALTER TABLE `gate_attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `healthcare_records`
--
ALTER TABLE `healthcare_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hostels`
--
ALTER TABLE `hostels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hostel_allocations`
--
ALTER TABLE `hostel_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hr_employment_letters_issued`
--
ALTER TABLE `hr_employment_letters_issued`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hr_employment_letter_templates`
--
ALTER TABLE `hr_employment_letter_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `library_books`
--
ALTER TABLE `library_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `library_checkouts`
--
ALTER TABLE `library_checkouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_log`
--
ALTER TABLE `notification_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_templates`
--
ALTER TABLE `notification_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `omr_answer_keys`
--
ALTER TABLE `omr_answer_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `omr_evaluations`
--
ALTER TABLE `omr_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `omr_sheets`
--
ALTER TABLE `omr_sheets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `omr_sheet_students`
--
ALTER TABLE `omr_sheet_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `onboarding_requests`
--
ALTER TABLE `onboarding_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_teacher_appointments`
--
ALTER TABLE `parent_teacher_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_teacher_messages`
--
ALTER TABLE `parent_teacher_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_requests`
--
ALTER TABLE `payment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platform_feature_catalog`
--
ALTER TABLE `platform_feature_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `pricing_packages`
--
ALTER TABLE `pricing_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `printed_exam_papers`
--
ALTER TABLE `printed_exam_papers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promotion_log`
--
ALTER TABLE `promotion_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `public_applications`
--
ALTER TABLE `public_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `question_bank`
--
ALTER TABLE `question_bank`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_cards`
--
ALTER TABLE `report_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `result_assessment_scores`
--
ALTER TABLE `result_assessment_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `result_slip_templates`
--
ALTER TABLE `result_slip_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schema_versions`
--
ALTER TABLE `schema_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `school_calendar_days`
--
ALTER TABLE `school_calendar_days`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_credit_balances`
--
ALTER TABLE `school_credit_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_feature_access`
--
ALTER TABLE `school_feature_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_invoices`
--
ALTER TABLE `school_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `school_notices_calendar`
--
ALTER TABLE `school_notices_calendar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_receipts`
--
ALTER TABLE `school_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_settings`
--
ALTER TABLE `school_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shareable_page_tokens`
--
ALTER TABLE `shareable_page_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_appraisals`
--
ALTER TABLE `staff_appraisals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_feature_permissions`
--
ALTER TABLE `staff_feature_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_leave_requests`
--
ALTER TABLE `staff_leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_payslips`
--
ALTER TABLE `staff_payslips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_subject_assignments`
--
ALTER TABLE `staff_subject_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_inventory`
--
ALTER TABLE `store_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_pos_sales`
--
ALTER TABLE `store_pos_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=208;

--
-- AUTO_INCREMENT for table `student_behavior_records`
--
ALTER TABLE `student_behavior_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_class_history`
--
ALTER TABLE `student_class_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_domain_ratings`
--
ALTER TABLE `student_domain_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_fee_credits`
--
ALTER TABLE `student_fee_credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_fee_credit_log`
--
ALTER TABLE `student_fee_credit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subject_teacher_assignments`
--
ALTER TABLE `subject_teacher_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_reminders`
--
ALTER TABLE `subscription_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `testimonial_templates`
--
ALTER TABLE `testimonial_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timetables`
--
ALTER TABLE `timetables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timetable_days`
--
ALTER TABLE `timetable_days`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timetable_periods`
--
ALTER TABLE `timetable_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `timetable_publications`
--
ALTER TABLE `timetable_publications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timetable_templates`
--
ALTER TABLE `timetable_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transport_allocations`
--
ALTER TABLE `transport_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transport_routes`
--
ALTER TABLE `transport_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `virtual_classes`
--
ALTER TABLE `virtual_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visitor_logs`
--
ALTER TABLE `visitor_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_arms`
--
ALTER TABLE `academic_arms`
  ADD CONSTRAINT `fk_academic_arms_academic_classes` FOREIGN KEY (`school_uuid`,`class_name`) REFERENCES `academic_classes` (`school_uuid`, `class_name`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_academic_arms_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `academic_classes`
--
ALTER TABLE `academic_classes`
  ADD CONSTRAINT `fk_academic_classes_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `academic_sessions`
--
ALTER TABLE `academic_sessions`
  ADD CONSTRAINT `fk_academic_sessions_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `academic_subjects`
--
ALTER TABLE `academic_subjects`
  ADD CONSTRAINT `fk_academic_subjects_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `academic_terms`
--
ALTER TABLE `academic_terms`
  ADD CONSTRAINT `fk_academic_terms_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `alumni`
--
ALTER TABLE `alumni`
  ADD CONSTRAINT `fk_alumni_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alumni_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assignments_assigned_by_staff_uuid` FOREIGN KEY (`assigned_by_staff_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignments_linked_appointment_uuid` FOREIGN KEY (`linked_appointment_uuid`) REFERENCES `parent_teacher_appointments` (`appointment_uuid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignments_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD CONSTRAINT `fk_assignment_submissions_assignment_uuid` FOREIGN KEY (`assignment_uuid`) REFERENCES `assignments` (`assignment_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignment_submissions_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignment_submissions_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `fk_attachments_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `fk_attendance_records_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_records_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `broadcast_messages`
--
ALTER TABLE `broadcast_messages`
  ADD CONSTRAINT `fk_broadcast_messages_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cafeteria_billing`
--
ALTER TABLE `cafeteria_billing`
  ADD CONSTRAINT `fk_cafeteria_billing_plan_uuid` FOREIGN KEY (`plan_uuid`) REFERENCES `cafeteria_meal_plans` (`plan_uuid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cafeteria_billing_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cafeteria_billing_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cafeteria_meal_plans`
--
ALTER TABLE `cafeteria_meal_plans`
  ADD CONSTRAINT `fk_cafeteria_meal_plans_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cafeteria_meal_plans_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cafeteria_menu_items`
--
ALTER TABLE `cafeteria_menu_items`
  ADD CONSTRAINT `fk_cafeteria_menu_items_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `career_advisory_notes`
--
ALTER TABLE `career_advisory_notes`
  ADD CONSTRAINT `fk_career_advisory_notes_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_career_advisory_notes_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cbt_questions`
--
ALTER TABLE `cbt_questions`
  ADD CONSTRAINT `fk_cbt_questions_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cbt_questions_test_uuid` FOREIGN KEY (`test_uuid`) REFERENCES `cbt_tests` (`test_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cbt_tests`
--
ALTER TABLE `cbt_tests`
  ADD CONSTRAINT `fk_cbt_tests_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `class_teacher_assignments`
--
ALTER TABLE `class_teacher_assignments`
  ADD CONSTRAINT `fk_class_teacher_assignments_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_class_teacher_assignments_staff_uuid` FOREIGN KEY (`staff_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `email_log`
--
ALTER TABLE `email_log`
  ADD CONSTRAINT `fk_email_log_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `essay_evaluations`
--
ALTER TABLE `essay_evaluations`
  ADD CONSTRAINT `fk_essay_evaluations_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_essay_evaluations_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `fee_structures`
--
ALTER TABLE `fee_structures`
  ADD CONSTRAINT `fk_fee_structures_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `flutterwave_settings`
--
ALTER TABLE `flutterwave_settings`
  ADD CONSTRAINT `fk_flutterwave_settings_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `gate_attendance_logs`
--
ALTER TABLE `gate_attendance_logs`
  ADD CONSTRAINT `fk_gate_attendance_logs_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `healthcare_records`
--
ALTER TABLE `healthcare_records`
  ADD CONSTRAINT `fk_healthcare_records_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `hostels`
--
ALTER TABLE `hostels`
  ADD CONSTRAINT `fk_hostels_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `hostel_allocations`
--
ALTER TABLE `hostel_allocations`
  ADD CONSTRAINT `fk_hostel_allocations_hostel_uuid` FOREIGN KEY (`hostel_uuid`) REFERENCES `hostels` (`hostel_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hostel_allocations_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hostel_allocations_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `hr_employment_letters_issued`
--
ALTER TABLE `hr_employment_letters_issued`
  ADD CONSTRAINT `fk_hr_employment_letters_issued_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_employment_letters_issued_staff_uuid` FOREIGN KEY (`staff_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hr_employment_letters_issued_template_uuid` FOREIGN KEY (`template_uuid`) REFERENCES `hr_employment_letter_templates` (`template_uuid`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `hr_employment_letter_templates`
--
ALTER TABLE `hr_employment_letter_templates`
  ADD CONSTRAINT `fk_hr_employment_letter_templates_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  ADD CONSTRAINT `fk_lesson_plans_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lesson_plans_teacher_uuid` FOREIGN KEY (`teacher_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `library_books`
--
ALTER TABLE `library_books`
  ADD CONSTRAINT `fk_library_books_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `library_checkouts`
--
ALTER TABLE `library_checkouts`
  ADD CONSTRAINT `fk_library_checkouts_book_uuid` FOREIGN KEY (`book_uuid`) REFERENCES `library_books` (`book_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_library_checkouts_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_recipient_uuid` FOREIGN KEY (`recipient_uuid`) REFERENCES `users` (`user_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifications_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notification_log`
--
ALTER TABLE `notification_log`
  ADD CONSTRAINT `fk_notification_log_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD CONSTRAINT `fk_notification_templates_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `omr_answer_keys`
--
ALTER TABLE `omr_answer_keys`
  ADD CONSTRAINT `fk_omr_answer_keys_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_omr_answer_keys_sheet_uuid` FOREIGN KEY (`sheet_uuid`) REFERENCES `omr_sheets` (`sheet_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `omr_evaluations`
--
ALTER TABLE `omr_evaluations`
  ADD CONSTRAINT `fk_omr_evaluations_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_omr_evaluations_sheet_student_uuid` FOREIGN KEY (`sheet_student_uuid`) REFERENCES `omr_sheet_students` (`sheet_student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_omr_evaluations_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `omr_sheets`
--
ALTER TABLE `omr_sheets`
  ADD CONSTRAINT `fk_omr_sheets_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `omr_sheet_students`
--
ALTER TABLE `omr_sheet_students`
  ADD CONSTRAINT `fk_omr_sheet_students_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_omr_sheet_students_sheet_uuid` FOREIGN KEY (`sheet_uuid`) REFERENCES `omr_sheets` (`sheet_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_omr_sheet_students_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `fk_parents_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `parent_teacher_appointments`
--
ALTER TABLE `parent_teacher_appointments`
  ADD CONSTRAINT `fk_parent_teacher_appointments_parent_uuid` FOREIGN KEY (`parent_uuid`) REFERENCES `parents` (`parent_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parent_teacher_appointments_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parent_teacher_appointments_teacher_uuid` FOREIGN KEY (`teacher_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `parent_teacher_messages`
--
ALTER TABLE `parent_teacher_messages`
  ADD CONSTRAINT `fk_parent_teacher_messages_receiver_uuid` FOREIGN KEY (`receiver_uuid`) REFERENCES `users` (`user_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parent_teacher_messages_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parent_teacher_messages_sender_uuid` FOREIGN KEY (`sender_uuid`) REFERENCES `users` (`user_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment_requests`
--
ALTER TABLE `payment_requests`
  ADD CONSTRAINT `fk_payment_requests_parent_uuid` FOREIGN KEY (`parent_uuid`) REFERENCES `parents` (`parent_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_requests_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_requests_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `fk_payment_transactions_invoice_uuid` FOREIGN KEY (`invoice_uuid`) REFERENCES `school_invoices` (`invoice_uuid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_transactions_parent_uuid` FOREIGN KEY (`parent_uuid`) REFERENCES `parents` (`parent_uuid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_transactions_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_transactions_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `printed_exam_papers`
--
ALTER TABLE `printed_exam_papers`
  ADD CONSTRAINT `fk_printed_exam_papers_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `promotion_log`
--
ALTER TABLE `promotion_log`
  ADD CONSTRAINT `fk_promotion_log_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `public_applications`
--
ALTER TABLE `public_applications`
  ADD CONSTRAINT `fk_public_applications_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `question_bank`
--
ALTER TABLE `question_bank`
  ADD CONSTRAINT `fk_question_bank_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `report_cards`
--
ALTER TABLE `report_cards`
  ADD CONSTRAINT `fk_report_cards_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_report_cards_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `fk_results_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_results_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `result_assessment_scores`
--
ALTER TABLE `result_assessment_scores`
  ADD CONSTRAINT `fk_result_assessment_scores_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_result_assessment_scores_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `result_slip_templates`
--
ALTER TABLE `result_slip_templates`
  ADD CONSTRAINT `fk_result_slip_templates_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `schools`
--
ALTER TABLE `schools`
  ADD CONSTRAINT `fk_schools_active_result_slip_template_uuid` FOREIGN KEY (`active_result_slip_template_uuid`) REFERENCES `result_slip_templates` (`template_uuid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_schools_school_admin_uuid` FOREIGN KEY (`school_admin_uuid`) REFERENCES `users` (`user_uuid`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `school_calendar_days`
--
ALTER TABLE `school_calendar_days`
  ADD CONSTRAINT `fk_school_calendar_days_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `school_credit_balances`
--
ALTER TABLE `school_credit_balances`
  ADD CONSTRAINT `fk_school_credit_balances_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `school_feature_access`
--
ALTER TABLE `school_feature_access`
  ADD CONSTRAINT `fk_school_feature_access_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `school_invoices`
--
ALTER TABLE `school_invoices`
  ADD CONSTRAINT `fk_school_invoices_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_school_invoices_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `school_notices_calendar`
--
ALTER TABLE `school_notices_calendar`
  ADD CONSTRAINT `fk_school_notices_calendar_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `school_receipts`
--
ALTER TABLE `school_receipts`
  ADD CONSTRAINT `fk_school_receipts_invoice_uuid` FOREIGN KEY (`invoice_uuid`) REFERENCES `school_invoices` (`invoice_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_school_receipts_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `school_settings`
--
ALTER TABLE `school_settings`
  ADD CONSTRAINT `fk_school_settings_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `shareable_page_tokens`
--
ALTER TABLE `shareable_page_tokens`
  ADD CONSTRAINT `fk_shareable_page_tokens_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff_user_uuid` FOREIGN KEY (`user_uuid`) REFERENCES `users` (`user_uuid`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `staff_appraisals`
--
ALTER TABLE `staff_appraisals`
  ADD CONSTRAINT `fk_staff_appraisals_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff_appraisals_staff_uuid` FOREIGN KEY (`staff_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD CONSTRAINT `fk_staff_attendance_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff_attendance_staff_uuid` FOREIGN KEY (`staff_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_feature_permissions`
--
ALTER TABLE `staff_feature_permissions`
  ADD CONSTRAINT `fk_staff_feature_permissions_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff_feature_permissions_staff_uuid` FOREIGN KEY (`staff_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_leave_requests`
--
ALTER TABLE `staff_leave_requests`
  ADD CONSTRAINT `fk_staff_leave_requests_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff_leave_requests_staff_uuid` FOREIGN KEY (`staff_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_payslips`
--
ALTER TABLE `staff_payslips`
  ADD CONSTRAINT `fk_staff_payslips_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff_payslips_staff_uuid` FOREIGN KEY (`staff_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_subject_assignments`
--
ALTER TABLE `staff_subject_assignments`
  ADD CONSTRAINT `fk_staff_subject_assignments_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff_subject_assignments_staff_uuid` FOREIGN KEY (`staff_uuid`) REFERENCES `staff` (`staff_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `store_inventory`
--
ALTER TABLE `store_inventory`
  ADD CONSTRAINT `fk_store_inventory_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `store_pos_sales`
--
ALTER TABLE `store_pos_sales`
  ADD CONSTRAINT `fk_store_pos_sales_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_store_pos_sales_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_parent_uuid` FOREIGN KEY (`parent_uuid`) REFERENCES `parents` (`parent_uuid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_students_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_behavior_records`
--
ALTER TABLE `student_behavior_records`
  ADD CONSTRAINT `fk_student_behavior_records_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_behavior_records_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_class_history`
--
ALTER TABLE `student_class_history`
  ADD CONSTRAINT `fk_student_class_history_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_class_history_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_domain_ratings`
--
ALTER TABLE `student_domain_ratings`
  ADD CONSTRAINT `fk_student_domain_ratings_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_domain_ratings_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_fee_credits`
--
ALTER TABLE `student_fee_credits`
  ADD CONSTRAINT `fk_student_fee_credits_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_fee_credits_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_fee_credit_log`
--
ALTER TABLE `student_fee_credit_log`
  ADD CONSTRAINT `fk_student_fee_credit_log_related_invoice_uuid` FOREIGN KEY (`related_invoice_uuid`) REFERENCES `school_invoices` (`invoice_uuid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_fee_credit_log_related_receipt_uuid` FOREIGN KEY (`related_receipt_uuid`) REFERENCES `school_receipts` (`receipt_uuid`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_fee_credit_log_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_fee_credit_log_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `subject_teacher_assignments`
--
ALTER TABLE `subject_teacher_assignments`
  ADD CONSTRAINT `fk_subject_teacher_assignments_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `subscription_reminders`
--
ALTER TABLE `subscription_reminders`
  ADD CONSTRAINT `fk_subscription_reminders_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `testimonial_templates`
--
ALTER TABLE `testimonial_templates`
  ADD CONSTRAINT `fk_testimonial_templates_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `timetables`
--
ALTER TABLE `timetables`
  ADD CONSTRAINT `fk_timetables_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `timetable_days`
--
ALTER TABLE `timetable_days`
  ADD CONSTRAINT `fk_timetable_days_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `timetable_periods`
--
ALTER TABLE `timetable_periods`
  ADD CONSTRAINT `fk_timetable_periods_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `timetable_publications`
--
ALTER TABLE `timetable_publications`
  ADD CONSTRAINT `fk_timetable_publications_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `timetable_templates`
--
ALTER TABLE `timetable_templates`
  ADD CONSTRAINT `fk_timetable_templates_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `transport_allocations`
--
ALTER TABLE `transport_allocations`
  ADD CONSTRAINT `fk_transport_allocations_route_uuid` FOREIGN KEY (`route_uuid`) REFERENCES `transport_routes` (`route_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transport_allocations_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transport_allocations_student_uuid` FOREIGN KEY (`student_uuid`) REFERENCES `students` (`student_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `transport_routes`
--
ALTER TABLE `transport_routes`
  ADD CONSTRAINT `fk_transport_routes_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `virtual_classes`
--
ALTER TABLE `virtual_classes`
  ADD CONSTRAINT `fk_virtual_classes_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `visitor_logs`
--
ALTER TABLE `visitor_logs`
  ADD CONSTRAINT `fk_visitor_logs_school_uuid` FOREIGN KEY (`school_uuid`) REFERENCES `schools` (`school_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
