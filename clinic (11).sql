-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 08, 2025 at 10:24 PM
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
-- Database: `clinic`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `start_year` int(11) NOT NULL,
  `end_year` int(11) NOT NULL,
  `grading_quarter` tinyint(4) NOT NULL CHECK (`grading_quarter` between 1 and 4),
  `semester` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `start_year`, `end_year`, `grading_quarter`, `semester`, `created_at`) VALUES
(12, 2022, 2023, 1, '1st Semester', '2025-09-08 13:20:29');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `content` text NOT NULL,
  `date` date NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `child_id` int(11) DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('Pending','Cancelled','Completed') DEFAULT 'Pending',
  `appointment_type` enum('dental','medical') NOT NULL DEFAULT 'medical',
  `academic_school_year` text NOT NULL,
  `semester` text NOT NULL,
  `grading_quarter` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `user_id`, `child_id`, `reason`, `appointment_date`, `appointment_time`, `status`, `appointment_type`, `academic_school_year`, `semester`, `grading_quarter`, `created_at`, `updated_at`) VALUES
(156, 113, NULL, 'check-up', '2025-09-10', '08:00:00', 'Cancelled', 'dental', '2022-2023', '1st Semester', '1', '2025-09-08 19:48:49', '2025-09-08 20:20:47'),
(157, 113, NULL, 'check-up', '2025-09-11', '08:00:00', 'Cancelled', 'dental', '2022-2023', '1st Semester', '1', '2025-09-08 19:49:53', '2025-09-08 20:20:53'),
(158, 113, NULL, 'check-up', '2025-09-09', '08:00:00', 'Cancelled', 'dental', '2022-2023', '1st Semester', '1', '2025-09-08 20:14:12', '2025-09-08 20:20:56'),
(159, 113, NULL, 'check-up', '2025-09-09', '08:00:00', 'Cancelled', 'dental', '2022-2023', '1st Semester', '1', '2025-09-08 20:22:37', '2025-09-08 20:23:00'),
(160, 113, NULL, 'illness', '2025-09-10', '08:00:00', 'Cancelled', 'dental', '2022-2023', '1st Semester', '1', '2025-09-08 20:22:47', '2025-09-08 20:23:03'),
(161, 113, NULL, 'illness', '2025-09-11', '08:00:00', 'Cancelled', 'dental', '2022-2023', '1st Semester', '1', '2025-09-08 20:22:52', '2025-09-08 20:23:05');

-- --------------------------------------------------------

--
-- Table structure for table `certificate_logs`
--

CREATE TABLE `certificate_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `child_id` int(11) DEFAULT NULL,
  `admin_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `document_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `type` enum('Kindergarten','Elementary') NOT NULL,
  `id_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `patient_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `college_students`
--

CREATE TABLE `college_students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cor_path` varchar(255) NOT NULL,
  `school_id_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `college_students`
--

INSERT INTO `college_students` (`id`, `user_id`, `cor_path`, `school_id_path`, `created_at`) VALUES
(33, 113, 'Uploads/documents/68b5a86463b80_COR.png', 'Uploads/documents/68b5a8646459f_id.jpg', '2025-09-01 14:06:28');

-- --------------------------------------------------------

--
-- Table structure for table `consent_forms`
--

CREATE TABLE `consent_forms` (
  `id` int(11) NOT NULL,
  `parent_name` varchar(255) NOT NULL,
  `child_name` varchar(255) NOT NULL,
  `residence` varchar(255) DEFAULT NULL,
  `consent_procedure` text NOT NULL,
  `witness_printed_name` varchar(255) DEFAULT NULL,
  `parent_printed_name` varchar(255) DEFAULT NULL,
  `patient_printed_name` varchar(255) DEFAULT NULL,
  `witness_date` date DEFAULT NULL,
  `parent_date` date DEFAULT NULL,
  `patient_date` date DEFAULT NULL,
  `witness_signature` text DEFAULT NULL,
  `parent_signature` text DEFAULT NULL,
  `patient_signature` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `child_id` int(11) DEFAULT NULL,
  `staff_id` int(11) NOT NULL,
  `consultation_date` date NOT NULL,
  `consultation_time` time NOT NULL,
  `type` enum('medical','dental') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `appointment_id`, `patient_id`, `child_id`, `staff_id`, `consultation_date`, `consultation_time`, `type`, `created_at`, `updated_at`, `status`) VALUES
(132, 118, 113, NULL, 23, '2025-09-07', '15:44:32', 'medical', '2025-09-07 07:44:32', '2025-09-07 12:34:43', 'Completed');

-- --------------------------------------------------------

--
-- Table structure for table `consultations_main`
--

CREATE TABLE `consultations_main` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `consultation_date` date NOT NULL,
  `consultation_time` time NOT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `weight` varchar(10) DEFAULT NULL,
  `height` varchar(10) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `temperature` varchar(10) DEFAULT NULL,
  `heart_rate` varchar(10) DEFAULT NULL,
  `respiratory_rate` varchar(10) DEFAULT NULL,
  `oxygen_saturation` varchar(10) DEFAULT NULL,
  `complaints` text NOT NULL,
  `history` text DEFAULT NULL,
  `physical_exam` text DEFAULT NULL,
  `assessment` text NOT NULL,
  `plan` text NOT NULL,
  `medications` text DEFAULT NULL,
  `consultation_type` varchar(50) DEFAULT NULL,
  `follow_up` text DEFAULT NULL,
  `physician_name` varchar(255) NOT NULL,
  `physician_license` varchar(100) DEFAULT NULL,
  `signature_data` longtext DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultation_advice`
--

CREATE TABLE `consultation_advice` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `child_id` int(11) DEFAULT NULL,
  `admin_user_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `date_advised` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consultation_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `content`
--

CREATE TABLE `content` (
  `id` int(11) NOT NULL,
  `page_name` varchar(50) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `content_text` text DEFAULT NULL,
  `staff_position` varchar(255) DEFAULT NULL,
  `content_html` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `content`
--

INSERT INTO `content` (`id`, `page_name`, `section_name`, `content_text`, `staff_position`, `content_html`, `image_url`, `last_updated`) VALUES
(1, 'index', 'hero_title', 'Your Health, Our Priority', NULL, NULL, NULL, '2025-08-02 10:55:49'),
(2, 'index', 'hero_subtitle', 'Comprehensive Healthcare for the WMSU Community', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(3, 'index', 'services_main_title', 'OUR SERVICES', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(4, 'index', 'service_1_title', 'PRIMARY CARE', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(5, 'index', 'service_1_desc', 'Comprehensive medical care for routine check-ups, illness treatment, and health management.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(6, 'index', 'service_2_title', 'PHARMACY', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(7, 'index', 'service_2_desc', 'Convenient access to prescription medications and expert pharmacist advice.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(8, 'index', 'service_3_title', 'SCREENINGS', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(9, 'index', 'service_3_desc', 'Early detection through regular screenings for common health concerns.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(10, 'index', 'service_4_title', 'DENTAL CARE', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(11, 'index', 'service_4_desc', 'Oral health services, including dental check-ups, cleanings, and treatments.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(12, 'index', 'service_5_title', 'VACCINATIONS', NULL, NULL, NULL, '2025-06-15 07:49:23'),
(13, 'index', 'service_5_desc', 'Protective immunizations for various diseases, administered by qualified professionals.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(14, 'index', 'service_6_title', 'EDUCATION', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(15, 'index', 'service_6_desc', 'Empowering students with health knowledge through workshops and consultations.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(16, 'index', 'contact_telephone', '(062) 991-6736', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(17, 'index', 'contact_email', 'healthservices@wmsu.edu.ph', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(18, 'index', 'contact_location', 'Health Services Building, WMSU Campus, Zamboanga City, Philippines', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(19, 'index', 'footer_text', '© 2025 Western Mindanao State University Health Services. All rights reserved. | wmsu.edu.ph', NULL, NULL, NULL, '2025-06-29 10:47:08'),
(20, 'homepage', 'about_us_title', 'About Me', NULL, NULL, NULL, '2025-06-16 19:09:26'),
(21, 'homepage', 'about_us_text', 'The WMSU Health Services Center is dedicated to providing comprehensive and compassionate healthcare to the students, faculty, and staff of Western Mindanao State University. We aim to promote a healthy campus environment through preventative care, timely medical interventions, and health education. Our team of skilled medical professionals is committed to ensuring the well-being of the entire WMSU community.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(22, 'homepage', 'welcome_title', 'Welcome to WMSU Health Services', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(23, 'homepage', 'welcome_text', 'We are committed to providing accessible and high-quality healthcare services to support your academic success and overall well-being. Explore our services and resources designed with your health in mind.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(24, 'homepage', 'staff_title', 'Our Dedicated Team', NULL, NULL, NULL, '2025-06-16 17:40:38'),
(25, 'homepage', 'staff_intro_text', 'Meet the compassionate professionals who are here to serve your health needs. Our team is committed to providing excellent care.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(26, 'homepage', 'core_values_title', 'Our Core Values', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(27, 'homepage', 'core_value_1_title', 'Excellence', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(28, 'homepage', 'core_value_1_desc', 'We pursue excellence in everything we do, constantly striving to exceed expectations.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(29, 'homepage', 'core_value_2_title', 'Integrity', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(30, 'homepage', 'core_value_2_desc', 'We conduct business with honesty, transparency, and ethical standards.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(31, 'homepage', 'core_value_3_title', 'Innovation', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(32, 'homepage', 'core_value_3_desc', 'We embrace creativity and forward-thinking to develop cutting-edge solutions.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(33, 'homepage', 'core_value_4_title', 'Collaboration', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(34, 'homepage', 'core_value_4_desc', 'We believe in the power of teamwork and partnerships to achieve shared goals.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(35, 'homepage', 'vision_title', 'Vision', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(36, 'homepage', 'vision_text', 'A premier university in the ASEAN region, recognized for its excellence in instruction, research, extension, and production, and for its significant contributions to sustainable development and peace in Mindanao and beyond.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(37, 'homepage', 'mission_title', 'Mission', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(38, 'homepage', 'mission_text', 'WMSU commits to creating a vibrant atmosphere of learning where science, technology, innovation, research, the arts and humanities, and community engagement flourish, producing world-class professionals committed to sustainable development and peace.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(39, 'upload', 'faq_1_title', 'How do we fill-up the forms and annotate our signatures electronically?', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(40, 'upload', 'faq_1_body', 'Using your laptop/computer, tablet, or cellphone, you may open and edit the forms using any PDF reader and editor (e.g. Adobe Acrobat, Foxit, Xodo, Microsoft Edge). To annotate your electronic signatures, you may insert an image of your signature or use the \"draw\" tool.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(41, 'upload', 'faq_2_title', 'What if I don\'t have a laptop or a phone with internet access?', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(42, 'upload', 'faq_2_body', 'You may visit the College of Engineering Computer Laboratory (Campus A) to accomplish the electronic forms and stop by the Health Services Center to physically submit your chest x-ray and laboratory test results.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(43, 'upload', 'faq_3_title', 'May we avail old chest-x ray and/or blood typing results?', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(44, 'upload', 'faq_3_body', 'Yes. You may submit old chest x-ray or laboratory results from any DOH-accredited facility provided that they were done during the past 3 months.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(45, 'upload', 'faq_4_title', 'May we submit a medical certificate from another physician?', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(46, 'upload', 'faq_4_body', 'Yes. We offer a free medical certificate for all incoming freshmen to minimize the students\' enrollment-related expenses. However, you are allowed to avail services from a physician of your choice, provided that you submit a copy of the medical certificate to the University Health Services Center. Note that you may still be required to fill-up the \"Patient Health Profile & Consultations Record\" and the \"Waiver for Collection of Personal and Sensitive Health Information\" upon your first consultation at the university clinic.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(47, 'upload', 'faq_5_title', 'How long do I have to wait before the release of my medical certificate?', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(48, 'upload', 'faq_5_body', 'Given the number of the university\'s incoming freshmen, please allow the Health Services Center 1-3 working days to process your request for a medical certificate.', NULL, NULL, NULL, '2025-06-13 23:30:50'),
(64, 'index', 'operating_hours_mon_fri', '8:00 AM - 5:00 PM', NULL, NULL, NULL, '2025-06-14 07:21:30'),
(65, 'index', 'operating_hours_saturday', '8:00 AM - 12:00 PM', NULL, NULL, NULL, '2025-06-16 17:37:40'),
(66, 'index', 'operating_hours_sunday', 'Closed <small>(Emergency services available)</small>', NULL, NULL, NULL, '2025-06-14 07:21:30'),
(843, 'upload', 'upload_title', 'Medical Documents', NULL, NULL, NULL, '2025-06-16 19:18:47'),
(844, 'upload', 'incoming_freshman_title', 'Incoming Freshman Requirements', NULL, NULL, NULL, '2025-06-16 19:18:29'),
(845, 'upload', 'incoming_freshman_text', 'As an incoming freshman, you are required to submit the following medical documents:', NULL, NULL, NULL, '2025-06-16 19:18:29'),
(846, 'upload', 'other_users_title', 'Medical Certificate Request', NULL, NULL, NULL, '2025-06-16 19:18:29'),
(847, 'upload', 'other_users_text', 'For other user types, you can request a medical certificate by filling out the form below:', NULL, NULL, NULL, '2025-06-16 19:18:29'),
(848, 'upload', 'history_title', 'Upload History', NULL, NULL, NULL, '2025-06-16 19:18:29'),
(849, 'upload', 'faq_title', 'Frequently Asked Questions (FAQ)', NULL, NULL, NULL, '2025-06-16 19:18:29');

-- --------------------------------------------------------

--
-- Table structure for table `csrf_tokens`
--

CREATE TABLE `csrf_tokens` (
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `csrf_tokens`
--

INSERT INTO `csrf_tokens` (`user_id`, `token`, `created_at`) VALUES
(23, 'c0298fc044b021baa98265e279ada2a374ba2d45ae9f07f163b9c014749ba48a', '2025-09-09 01:34:14');

-- --------------------------------------------------------

--
-- Table structure for table `dental_consultations`
--

CREATE TABLE `dental_consultations` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `own_brush` enum('yes','no') NOT NULL,
  `pt_upper_right_quadrant` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pt_upper_right_quadrant`)),
  `pt_upper_left_quadrant` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pt_upper_left_quadrant`)),
  `pt_lower_left_quadrant` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pt_lower_left_quadrant`)),
  `pt_lower_right_quadrant` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pt_lower_right_quadrant`)),
  `tt_upper_right_quadrant` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tt_upper_right_quadrant`)),
  `tt_upper_left_quadrant` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tt_upper_left_quadrant`)),
  `tt_lower_left_quadrant` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tt_lower_left_quadrant`)),
  `tt_lower_right_quadrant` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tt_lower_right_quadrant`)),
  `permanent_teeth` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permanent_teeth`)),
  `temporary_teeth` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`temporary_teeth`)),
  `dental_treatment_record` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dental_treatment_record`)),
  `remarks` text DEFAULT NULL,
  `examined_by` varchar(255) DEFAULT NULL,
  `signature_data` text DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_contacts`
--

CREATE TABLE `emergency_contacts` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  `city_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emergency_contacts`
--

INSERT INTO `emergency_contacts` (`id`, `patient_id`, `surname`, `firstname`, `middlename`, `contact_number`, `relationship`, `city_address`, `created_at`) VALUES
(197, 205, 'Qwe', 'Qwe', 'Qwe', '09302471032', 'Other: City Address', 'City address', '2025-08-31 12:32:14'),
(198, 110, 'Asd', 'Asd', 'Asd', '09536640199', 'Other: asd', 'Asd', '2025-08-31 13:02:45'),
(199, 206, 'Zcx', 'Zxc', 'Zxxc', '09350771992', 'Other: asd', 'Asd', '2025-08-31 13:08:18'),
(200, 208, 'Test', 'Test', 'Test', '09536640199', 'Parent', 'Test', '2025-08-31 13:25:42'),
(201, 209, 'Test', 'Test', 'Test', '09536640199', 'Parent', 'Test', '2025-08-31 13:25:42'),
(202, 210, 'Aquino', 'Ahmad', 'Pandaog', '09536640199', 'Other: Self', 'Oh no', '2025-09-01 14:08:01'),
(203, 211, 'Ahmadoes', 'Ahmadoes', 'Ahmadoes', '09536640199', 'Other: Other', 'City address', '2025-09-01 14:11:03'),
(204, 212, 'Aquino', 'Aquino', 'Aquino', '09536640199', 'Child', 'City address', '2025-09-01 14:12:48'),
(205, 213, 'Namer', 'Namer', 'Namer', '09536640199', 'Guardian', 'City address', '2025-09-01 14:19:15'),
(206, 214, 'Ahmadoes', 'Ahmadoes', 'Ahmadoes', '09536640199', 'Other: Relationship', 'City address', '2025-09-01 14:22:34'),
(207, 215, 'City addressor', 'City addressor', 'City addressor', '09536640199', 'Parent', 'Asd', '2025-09-01 14:27:10'),
(208, 216, 'Aquinos', 'Aquinos', 'Aquinos', '09536640199', 'Parent', 'City addressor', '2025-09-01 14:30:49'),
(209, 217, 'Sur', 'Sur', 'Sur', '09536640199', 'Other: SUR', 'Hey', '2025-09-01 14:42:58'),
(210, 218, 'Emerneaugfh', 'Emerneaugfh', 'Emerneaugfh', '09302471932', 'Other: EMERNEAUGFH', 'Emerneaugfh', '2025-09-01 14:45:06'),
(211, 113, 'Asdasdas', 'Asdasdasd', 'Asdasd', '09302471932', 'Other: asdasdasd', 'Emerneaugfh', '2025-09-01 14:50:20');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_contacts_history`
--

CREATE TABLE `emergency_contacts_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  `city_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `user_id`, `id_path`) VALUES
(8, 111, 'Uploads/documents/68b4491ad9ebf_aamon.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `page` varchar(50) NOT NULL DEFAULT 'upload'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `question`, `answer`, `display_order`, `page`) VALUES
(1, 'What is the process?', 'Complete the form.', 1, 'upload'),
(2, 'Paano magcode?', 'put yourself in hell and learn.', 2, 'upload');

-- --------------------------------------------------------

--
-- Table structure for table `file_requirements`
--

CREATE TABLE `file_requirements` (
  `id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `allowed_extensions` varchar(255) NOT NULL,
  `max_size_mb` int(11) NOT NULL,
  `validity_period_days` int(11) NOT NULL,
  `description` text NOT NULL,
  `display_order` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `file_requirements`
--

INSERT INTO `file_requirements` (`id`, `document_type`, `allowed_extensions`, `max_size_mb`, `validity_period_days`, `description`, `display_order`) VALUES
(1, 'Photo ID', '.pdf,.jpg', 5, 365, 'Valid ID required', 1);

-- --------------------------------------------------------

--
-- Table structure for table `highschool_students`
--

CREATE TABLE `highschool_students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `highschool_students`
--

INSERT INTO `highschool_students` (`id`, `user_id`, `id_path`) VALUES
(0, 114, 'Uploads/documents/68bec742e7bb8_image-removebg-preview__1_.png');

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `id` int(11) NOT NULL,
  `page_name` varchar(100) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_alt` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `images`
--

INSERT INTO `images` (`id`, `page_name`, `section_name`, `image_path`, `image_alt`, `original_filename`, `file_size`, `mime_type`, `upload_date`, `updated_date`) VALUES
(1, 'index', 'logo', '/CMS/uploads/images/logos/684fd5d6f3884_logo.png', 'WMSU Clinic Logo', 'logo.png', 118900, 'image/png', '2025-06-14 07:50:16', '2025-06-16 08:29:11'),
(2, 'index', 'hero_background', '/uploads/images/backgrounds/68506bab694dd_12.jpg', '', '12.jpg', 467703, 'image/jpeg', '2025-06-14 07:50:16', '2025-06-16 19:08:27'),
(3, 'homepage', 'background_about', '/CMS/uploads/uploads/backgrounds/685057029cd49_healthservices.jpg', '', 'healthservices.jpg', 86771, 'image/jpeg', '2025-06-15 13:37:52', '2025-06-16 17:40:18'),
(4, 'homepage', 'background_welcome', '/CMS/images/logo.png', 'WMSU Clinic Logo', '4.JPG', 144688, 'image/jpeg', '2025-06-15 13:42:56', '2025-06-16 08:58:44');

-- --------------------------------------------------------

--
-- Table structure for table `incoming_freshmen`
--

CREATE TABLE `incoming_freshmen` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_consultations`
--

CREATE TABLE `medical_consultations` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `oxygen_saturation` decimal(5,2) DEFAULT NULL,
  `complaints` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `test_results` text NOT NULL,
  `staff_name` varchar(255) DEFAULT NULL,
  `staff_signature` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_documents`
--

CREATE TABLE `medical_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `child_id` int(11) DEFAULT NULL,
  `document_type` text NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `original_file_name` varchar(255) DEFAULT NULL,
  `request_type` enum('ojt-internship','non-ojt') DEFAULT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `competition_scope` varchar(50) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_info`
--

CREATE TABLE `medical_info` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `illnesses` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `vaccination_status` enum('fully','partially','not') DEFAULT NULL,
  `menstruation_age` int(11) DEFAULT NULL,
  `menstrual_pattern` text DEFAULT NULL,
  `pregnancies` int(11) DEFAULT NULL,
  `live_children` int(11) DEFAULT NULL,
  `menstrual_symptoms` text DEFAULT NULL,
  `past_illnesses` text DEFAULT NULL,
  `hospital_admissions` text DEFAULT NULL,
  `family_history` text DEFAULT NULL,
  `other_conditions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_info`
--

INSERT INTO `medical_info` (`id`, `patient_id`, `illnesses`, `medications`, `vaccination_status`, `menstruation_age`, `menstrual_pattern`, `pregnancies`, `live_children`, `menstrual_symptoms`, `past_illnesses`, `hospital_admissions`, `family_history`, `other_conditions`, `created_at`, `updated_at`) VALUES
(208, 218, 'Cancer: asd,Other: asd', NULL, 'fully', 1, 'regular', 1, 2, 'dysmenorrhea,migraine,consciousness,other,other: EMERNEAUGFH', 'other, Other: EMERNEAUGFH', NULL, NULL, NULL, '2025-09-01 14:45:06', '2025-09-01 14:45:06'),
(209, 113, 'cancer,other', 'paracetamol:1:mg:once daily,ibuprofen:2:ml:twice daily', 'fully', 1, 'regular', 1, 2, 'dysmenorrhea,migraine,consciousness,other,other: EMERNEAUGFH', NULL, NULL, NULL, 'hey', '2025-09-01 14:50:20', '2025-09-01 14:50:20');

-- --------------------------------------------------------

--
-- Table structure for table `medical_info_history`
--

CREATE TABLE `medical_info_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `illnesses` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `vaccination_status` enum('fully','partially','not') DEFAULT NULL,
  `menstruation_age` int(11) DEFAULT NULL,
  `menstrual_pattern` enum('regular','irregular') DEFAULT NULL,
  `pregnancies` int(11) DEFAULT NULL,
  `live_children` int(11) DEFAULT NULL,
  `menstrual_symptoms` text DEFAULT NULL,
  `past_illnesses` text DEFAULT NULL,
  `hospital_admissions` text DEFAULT NULL,
  `family_history` text DEFAULT NULL,
  `other_conditions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_info_history`
--

INSERT INTO `medical_info_history` (`id`, `patient_id`, `illnesses`, `medications`, `vaccination_status`, `menstruation_age`, `menstrual_pattern`, `pregnancies`, `live_children`, `menstrual_symptoms`, `past_illnesses`, `hospital_admissions`, `family_history`, `other_conditions`, `created_at`, `updated_at`, `archived_at`) VALUES
(203, 218, 'Cancer: asd,Other: asd', NULL, 'fully', 1, 'regular', 1, 2, 'dysmenorrhea,migraine,consciousness,other,other: EMERNEAUGFH', 'other, Other: EMERNEAUGFH', NULL, NULL, NULL, '2025-09-01 14:50:20', '2025-09-01 14:50:20', '2025-09-01 22:50:20');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications_admin`
--

CREATE TABLE `notifications_admin` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications_admin`
--

INSERT INTO `notifications_admin` (`id`, `user_id`, `type`, `title`, `description`, `link`, `status`, `created_at`, `updated_at`) VALUES
(388, 23, 'health_profile_submission', 'New Health Profile Submission!', 'Test Test (College) has submitted their health profile', 'patient-profile.php', 'read', '2025-08-31 20:32:14', '2025-08-31 20:36:22'),
(389, 24, 'health_profile_submission', 'New Health Profile Submission!', 'Test Test (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-08-31 20:32:14', '2025-08-31 20:32:14'),
(390, 25, 'health_profile_submission', 'New Health Profile Submission!', 'Test Test (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-08-31 20:32:14', '2025-08-31 20:32:14'),
(391, 23, 'waiver_submission', 'New Waiver Submission', 'TEST, TEST has signed a waiver.', '#', 'read', '2025-08-31 20:35:36', '2025-08-31 20:36:22'),
(392, 24, 'waiver_submission', 'New Waiver Submission', 'TEST, TEST has signed a waiver.', '#', 'unread', '2025-08-31 20:35:36', '2025-08-31 20:35:36'),
(393, 25, 'waiver_submission', 'New Waiver Submission', 'TEST, TEST has signed a waiver.', '#', 'unread', '2025-08-31 20:35:36', '2025-08-31 20:35:36'),
(394, 23, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Test Test (College) has submitted a medical certificate', 'medical-documents.php', 'read', '2025-08-31 20:38:42', '2025-08-31 20:45:13'),
(395, 24, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Test Test (College) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 20:38:42', '2025-08-31 20:38:42'),
(396, 25, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Test Test (College) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 20:38:42', '2025-08-31 20:38:42'),
(397, 23, 'document_update', 'Document Update', 'Test Test (College) has updated a medical document', 'medical-documents.php', 'read', '2025-08-31 20:39:06', '2025-08-31 20:45:13'),
(398, 24, 'document_update', 'Document Update', 'Test Test (College) has updated a medical document', 'medical-documents.php', 'unread', '2025-08-31 20:39:06', '2025-08-31 20:39:06'),
(399, 25, 'document_update', 'Document Update', 'Test Test (College) has updated a medical document', 'medical-documents.php', 'unread', '2025-08-31 20:39:06', '2025-08-31 20:39:06'),
(400, 23, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Test Test (College) has submitted a medical certificate', 'medical-documents.php', 'read', '2025-08-31 20:42:35', '2025-08-31 20:45:13'),
(401, 24, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Test Test (College) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 20:42:35', '2025-08-31 20:42:35'),
(402, 25, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Test Test (College) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 20:42:35', '2025-08-31 20:42:35'),
(403, 23, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Test Test (College) has submitted a medical certificate', 'medical-documents.php', 'read', '2025-08-31 20:43:29', '2025-08-31 20:45:13'),
(404, 24, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Test Test (College) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 20:43:29', '2025-08-31 20:43:29'),
(405, 25, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Test Test (College) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 20:43:29', '2025-08-31 20:43:29'),
(406, 23, 'waiver_submission', 'Medical Appointment Request', 'TEST TEST TEST has requested for a medical appointment!', '#', 'read', '2025-08-31 20:45:01', '2025-08-31 20:45:13'),
(407, 24, 'waiver_submission', 'Medical Appointment Request', 'TEST TEST TEST has requested for a medical appointment!', '#', 'unread', '2025-08-31 20:45:01', '2025-08-31 20:45:01'),
(408, 23, 'waiver_submission', 'Dental Appointment Request', 'TEST TEST TEST has requested for a dental appointment!', '#', 'unread', '2025-08-31 20:50:30', '2025-08-31 20:50:30'),
(409, 25, 'waiver_submission', 'Dental Appointment Request', 'TEST TEST TEST has requested for a dental appointment!', '#', 'unread', '2025-08-31 20:50:30', '2025-08-31 20:50:30'),
(410, 23, 'health_profile_submission', 'Health Profile Updated!', 'Test Test - (College) has updated their health profile.', 'patient-profile.php', 'unread', '2025-08-31 21:02:45', '2025-08-31 21:02:45'),
(411, 24, 'health_profile_submission', 'Health Profile Updated!', 'Test Test - (College) has updated their health profile.', 'patient-profile.php', 'unread', '2025-08-31 21:02:45', '2025-08-31 21:02:45'),
(412, 25, 'health_profile_submission', 'Health Profile Updated!', 'Test Test - (College) has updated their health profile.', 'patient-profile.php', 'unread', '2025-08-31 21:02:45', '2025-08-31 21:02:45'),
(413, 23, 'health_profile_submission', 'New Health Profile Submission!', 'Employee Employee (Employee) has submitted their health profile', 'patient-profile.php', 'unread', '2025-08-31 21:08:18', '2025-08-31 21:08:18'),
(414, 24, 'health_profile_submission', 'New Health Profile Submission!', 'Employee Employee (Employee) has submitted their health profile', 'patient-profile.php', 'unread', '2025-08-31 21:08:18', '2025-08-31 21:08:18'),
(415, 25, 'health_profile_submission', 'New Health Profile Submission!', 'Employee Employee (Employee) has submitted their health profile', 'patient-profile.php', 'unread', '2025-08-31 21:08:18', '2025-08-31 21:08:18'),
(416, 23, 'waiver_submission', 'New Waiver Submission', 'Employee, Employee has signed a waiver.', '#', 'unread', '2025-08-31 21:08:26', '2025-08-31 21:08:26'),
(417, 24, 'waiver_submission', 'New Waiver Submission', 'Employee, Employee has signed a waiver.', '#', 'unread', '2025-08-31 21:08:26', '2025-08-31 21:08:26'),
(418, 25, 'waiver_submission', 'New Waiver Submission', 'Employee, Employee has signed a waiver.', '#', 'unread', '2025-08-31 21:08:26', '2025-08-31 21:08:26'),
(419, 23, 'health_profile_submission', 'New Health Profile Submission!', 'Aamon Aamon (Elementary Student) has submitted their health profile', 'patient-profile.php', 'unread', '2025-08-31 21:25:42', '2025-08-31 21:25:42'),
(420, 24, 'health_profile_submission', 'New Health Profile Submission!', 'Aamon Aamon (Elementary Student) has submitted their health profile', 'patient-profile.php', 'unread', '2025-08-31 21:25:42', '2025-08-31 21:25:42'),
(421, 25, 'health_profile_submission', 'New Health Profile Submission!', 'Aamon Aamon (Elementary Student) has submitted their health profile', 'patient-profile.php', 'unread', '2025-08-31 21:25:42', '2025-08-31 21:25:42'),
(422, 23, 'waiver_submission', 'New Waiver Submission', 'Parent, Parent has signed a waiver.', '#', 'unread', '2025-08-31 21:28:40', '2025-08-31 21:28:40'),
(423, 24, 'waiver_submission', 'New Waiver Submission', 'Parent, Parent has signed a waiver.', '#', 'unread', '2025-08-31 21:28:40', '2025-08-31 21:28:40'),
(424, 25, 'waiver_submission', 'New Waiver Submission', 'Parent, Parent has signed a waiver.', '#', 'unread', '2025-08-31 21:28:40', '2025-08-31 21:28:40'),
(425, 23, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Parent Parent (Parent) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 21:29:15', '2025-08-31 21:29:15'),
(426, 24, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Parent Parent (Parent) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 21:29:15', '2025-08-31 21:29:15'),
(427, 25, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Parent Parent (Parent) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 21:29:15', '2025-08-31 21:29:15'),
(428, 23, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Parent Parent (Parent) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 21:29:52', '2025-08-31 21:29:52'),
(429, 24, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Parent Parent (Parent) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 21:29:52', '2025-08-31 21:29:52'),
(430, 25, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Parent Parent (Parent) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 21:29:52', '2025-08-31 21:29:52'),
(431, 23, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:08:01', '2025-09-01 22:08:01'),
(432, 24, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:08:01', '2025-09-01 22:08:01'),
(433, 25, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:08:01', '2025-09-01 22:08:01'),
(434, 23, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:11:03', '2025-09-01 22:11:03'),
(435, 24, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:11:03', '2025-09-01 22:11:03'),
(436, 25, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:11:03', '2025-09-01 22:11:03'),
(437, 23, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:12:48', '2025-09-01 22:12:48'),
(438, 24, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:12:48', '2025-09-01 22:12:48'),
(439, 25, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:12:48', '2025-09-01 22:12:48'),
(440, 23, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:19:15', '2025-09-01 22:19:15'),
(441, 24, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:19:15', '2025-09-01 22:19:15'),
(442, 25, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:19:15', '2025-09-01 22:19:15'),
(443, 23, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:22:34', '2025-09-01 22:22:34'),
(444, 24, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:22:34', '2025-09-01 22:22:34'),
(445, 25, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:22:34', '2025-09-01 22:22:34'),
(446, 23, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:27:10', '2025-09-01 22:27:10'),
(447, 24, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:27:10', '2025-09-01 22:27:10'),
(448, 25, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:27:10', '2025-09-01 22:27:10'),
(449, 23, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:30:49', '2025-09-01 22:30:49'),
(450, 24, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:30:49', '2025-09-01 22:30:49'),
(451, 25, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:30:49', '2025-09-01 22:30:49'),
(452, 23, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:42:58', '2025-09-01 22:42:58'),
(453, 24, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:42:58', '2025-09-01 22:42:58'),
(454, 25, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:42:58', '2025-09-01 22:42:58'),
(455, 23, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:45:06', '2025-09-01 22:45:06'),
(456, 24, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:45:06', '2025-09-01 22:45:06'),
(457, 25, 'health_profile_submission', 'New Health Profile Submission!', 'College guy College guy (College) has submitted their health profile', 'patient-profile.php', 'unread', '2025-09-01 22:45:06', '2025-09-01 22:45:06'),
(458, 23, 'health_profile_submission', 'Health Profile Updated!', '213 Asd - (College) has updated their health profile.', 'patient-profile.php', 'unread', '2025-09-01 22:50:20', '2025-09-01 22:50:20'),
(459, 24, 'health_profile_submission', 'Health Profile Updated!', '213 Asd - (College) has updated their health profile.', 'patient-profile.php', 'unread', '2025-09-01 22:50:20', '2025-09-01 22:50:20'),
(460, 25, 'health_profile_submission', 'Health Profile Updated!', '213 Asd - (College) has updated their health profile.', 'patient-profile.php', 'unread', '2025-09-01 22:50:20', '2025-09-01 22:50:20'),
(461, 23, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:31:51', '2025-09-07 01:31:51'),
(462, 24, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:31:51', '2025-09-07 01:31:51'),
(463, 23, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:36:07', '2025-09-07 01:36:07'),
(464, 24, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:36:07', '2025-09-07 01:36:07'),
(465, 23, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:42:25', '2025-09-07 01:42:25'),
(466, 24, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:42:25', '2025-09-07 01:42:25'),
(467, 23, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:46:15', '2025-09-07 01:46:15'),
(468, 24, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:46:15', '2025-09-07 01:46:15'),
(469, 23, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:50:35', '2025-09-07 01:50:35'),
(470, 24, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:50:35', '2025-09-07 01:50:35'),
(471, 23, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:51:14', '2025-09-07 01:51:14'),
(472, 24, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 01:51:14', '2025-09-07 01:51:14'),
(473, 23, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 12:43:32', '2025-09-07 12:43:32'),
(474, 24, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-07 12:43:32', '2025-09-07 12:43:32'),
(475, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-07 14:21:06', '2025-09-07 14:21:06'),
(476, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-07 14:21:06', '2025-09-07 14:21:06'),
(477, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-07 14:45:18', '2025-09-07 14:45:18'),
(478, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-07 14:45:18', '2025-09-07 14:45:18'),
(479, 23, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-08 20:40:53', '2025-09-08 20:40:53'),
(480, 24, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-08 20:40:53', '2025-09-08 20:40:53'),
(481, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 20:53:23', '2025-09-08 20:53:23'),
(482, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 20:53:23', '2025-09-08 20:53:23'),
(483, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 20:59:03', '2025-09-08 20:59:03'),
(484, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 20:59:03', '2025-09-08 20:59:03'),
(485, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:03:22', '2025-09-08 21:03:22'),
(486, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:03:22', '2025-09-08 21:03:22'),
(487, 23, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-08 21:05:00', '2025-09-08 21:05:00'),
(488, 24, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-08 21:05:00', '2025-09-08 21:05:00'),
(489, 23, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-08 21:05:05', '2025-09-08 21:05:05'),
(490, 24, 'waiver_submission', 'Medical Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a medical appointment!', '#', 'unread', '2025-09-08 21:05:05', '2025-09-08 21:05:05'),
(491, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:13:02', '2025-09-08 21:13:02'),
(492, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:13:02', '2025-09-08 21:13:02'),
(493, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:22:34', '2025-09-08 21:22:34'),
(494, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:22:34', '2025-09-08 21:22:34'),
(495, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:39:08', '2025-09-08 21:39:08'),
(496, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:39:08', '2025-09-08 21:39:08'),
(497, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:46:13', '2025-09-08 21:46:13'),
(498, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:46:13', '2025-09-08 21:46:13'),
(499, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:46:18', '2025-09-08 21:46:18'),
(500, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-08 21:46:18', '2025-09-08 21:46:18'),
(501, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 02:11:14', '2025-09-09 02:11:14'),
(502, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 02:11:14', '2025-09-09 02:11:14'),
(503, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 02:12:37', '2025-09-09 02:12:37'),
(504, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 02:12:37', '2025-09-09 02:12:37'),
(505, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 02:21:31', '2025-09-09 02:21:31'),
(506, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 02:21:31', '2025-09-09 02:21:31'),
(507, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 02:34:52', '2025-09-09 02:34:52'),
(508, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 02:34:52', '2025-09-09 02:34:52'),
(509, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:42:53', '2025-09-09 03:42:53'),
(510, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:42:53', '2025-09-09 03:42:53'),
(511, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:46:26', '2025-09-09 03:46:26'),
(512, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:46:26', '2025-09-09 03:46:26'),
(513, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:46:33', '2025-09-09 03:46:33'),
(514, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:46:33', '2025-09-09 03:46:33'),
(515, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:47:57', '2025-09-09 03:47:57'),
(516, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:47:57', '2025-09-09 03:47:57'),
(517, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:48:49', '2025-09-09 03:48:49'),
(518, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:48:49', '2025-09-09 03:48:49'),
(519, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:49:53', '2025-09-09 03:49:53'),
(520, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 03:49:53', '2025-09-09 03:49:53'),
(521, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 04:14:12', '2025-09-09 04:14:12'),
(522, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 04:14:12', '2025-09-09 04:14:12'),
(523, 23, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:20:47', '2025-09-09 04:20:47'),
(524, 25, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:20:47', '2025-09-09 04:20:47'),
(525, 23, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:20:53', '2025-09-09 04:20:53'),
(526, 25, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:20:53', '2025-09-09 04:20:53'),
(527, 23, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:20:56', '2025-09-09 04:20:56'),
(528, 25, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:20:56', '2025-09-09 04:20:56'),
(529, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 04:22:37', '2025-09-09 04:22:37'),
(530, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 04:22:37', '2025-09-09 04:22:37'),
(531, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 04:22:47', '2025-09-09 04:22:47'),
(532, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 04:22:47', '2025-09-09 04:22:47'),
(533, 23, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 04:22:52', '2025-09-09 04:22:52'),
(534, 25, 'waiver_submission', 'Dental Appointment Request', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has requested for a dental appointment!', '#', 'unread', '2025-09-09 04:22:52', '2025-09-09 04:22:52'),
(535, 23, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:23:00', '2025-09-09 04:23:00'),
(536, 25, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:23:00', '2025-09-09 04:23:00'),
(537, 23, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:23:03', '2025-09-09 04:23:03'),
(538, 25, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:23:03', '2025-09-09 04:23:03'),
(539, 23, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:23:05', '2025-09-09 04:23:05'),
(540, 25, 'waiver_submission', 'Dental Appointment Cancellation', 'COLLEGE GUY COLLEGE GUY COLLEGE GUY has canelled the dental appointment!', '#', 'unread', '2025-09-09 04:23:05', '2025-09-09 04:23:05');

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_id` text DEFAULT NULL,
  `surname` varchar(50) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `birthday` date NOT NULL,
  `age` int(11) NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown') DEFAULT 'unknown',
  `religion` varchar(50) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Filipino',
  `civil_status` enum('single','married','widowed','divorced') DEFAULT 'single',
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `city_address` text NOT NULL,
  `provincial_address` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `grade_level` varchar(20) DEFAULT NULL,
  `grading_quarter` varchar(20) DEFAULT NULL,
  `track_strand` varchar(100) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `id_path` varchar(255) DEFAULT NULL,
  `cor_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `student_id`, `surname`, `firstname`, `middlename`, `suffix`, `birthday`, `age`, `sex`, `blood_type`, `religion`, `nationality`, `civil_status`, `email`, `contact_number`, `city_address`, `provincial_address`, `photo_path`, `grade_level`, `grading_quarter`, `track_strand`, `section`, `semester`, `department`, `course`, `year_level`, `position`, `employee_id`, `id_path`, `cor_path`, `created_at`, `updated_at`) VALUES
(218, 113, '2020-01524', 'Asd', '213', 'Asd', NULL, '2001-07-02', 24, 'female', 'A+', 'Roman Catholic', 'Filipino', 'single', 'ahmadaquino.2002@gmail.com', '09125712561', 'Zxczxxczxc', 'Asdasdasd', 'Uploads/patient_photos/9a933ff1a3907c1032f99c6d1d0a2025.png', NULL, NULL, NULL, NULL, 'Midterm', 'CPADS', 'BSPubAdmin', 1, NULL, NULL, NULL, NULL, '2025-09-01 14:45:06', '2025-09-01 14:50:20');

-- --------------------------------------------------------

--
-- Table structure for table `patients_history`
--

CREATE TABLE `patients_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_id` text DEFAULT NULL,
  `surname` varchar(50) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `birthday` date NOT NULL,
  `age` int(11) NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown') DEFAULT 'unknown',
  `religion` varchar(50) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Filipino',
  `civil_status` enum('single','married','widowed','divorced') DEFAULT 'single',
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `city_address` text NOT NULL,
  `provincial_address` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `grade_level` varchar(20) DEFAULT NULL,
  `grading_quarter` varchar(20) DEFAULT NULL,
  `track_strand` varchar(100) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `id_path` varchar(255) DEFAULT NULL,
  `cor_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `patient_name` varchar(150) NOT NULL,
  `age` int(11) NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `diagnosis` text NOT NULL,
  `medications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`medications`)),
  `prescribing_physician` varchar(100) NOT NULL,
  `physician_signature` varchar(255) NOT NULL,
  `prescription_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `senior_high_students`
--

CREATE TABLE `senior_high_students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `senior_high_students`
--

INSERT INTO `senior_high_students` (`id`, `user_id`, `id_path`, `created_at`) VALUES
(6, 115, 'Uploads/documents/68b5c14211c10_id.jpg', '2025-09-01 15:52:34');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_alt` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `name`, `position`, `image_path`, `image_alt`, `original_filename`, `mime_type`, `file_size`, `is_active`, `created_at`, `updated_at`) VALUES
(22, 'Juliana Salatan', 'Doctor', '/uploads/staff/68506c3d12045_default-profile.jpg', '', 'default-profile.jpg', 'image/jpeg', 8225, 1, '2025-06-16 07:31:32', '2025-06-16 19:10:53'),
(23, 'JM', 'Patient', '/uploads/staff/68506c46cba8a_default-profile.jpg', '', 'default-profile.jpg', 'image/jpeg', 8225, 1, '2025-06-16 07:32:00', '2025-06-16 19:11:02');

-- --------------------------------------------------------

--
-- Table structure for table `submission_status`
--

CREATE TABLE `submission_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `has_submitted` tinyint(1) DEFAULT 0,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('Parent','Highschool','Senior High School','College','Employee','Incoming Freshman','Super Admin','Medical Admin','Dental Admin') DEFAULT NULL,
  `College` text DEFAULT NULL,
  `verification_token` varchar(64) DEFAULT NULL,
  `token_created_at` datetime DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL,
  `remember_expiry` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `profile_submitted` tinyint(1) DEFAULT 0,
  `profile_update_required` tinyint(1) DEFAULT 0,
  `documents_uploaded` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `last_name`, `first_name`, `middle_name`, `email`, `password`, `user_type`, `College`, `verification_token`, `token_created_at`, `verified`, `created_at`, `verified_at`, `remember_token`, `remember_expiry`, `reset_token`, `reset_expiry`, `profile_submitted`, `profile_update_required`, `documents_uploaded`) VALUES
(23, 'Admin', 'Super', NULL, 'superadmin@gmail.com', '$2y$10$X2VI14OzWKQNdOI6AUSmueUI9XqXW/gD3vlYnnZX3tqxkOEpUxaVy', 'Super Admin', NULL, NULL, NULL, 1, '2025-05-02 19:23:11', '2025-05-03 03:23:11', 'f46e34bb3f98b156a6dc0b754b6fef60bfe2e5dc30ffb020ffc9345ae11b43cb', '2025-09-30 20:24:12', NULL, NULL, 1, 0, 1),
(24, 'Admin', 'Medical', NULL, 'medicaladmin@wmsu.edu.ph', '$2y$10$bIvSaSZtSyN01Yk7R2jTpOQB7vGFx/8YdigMzF8gMYaTI5p.G8dxG', 'Medical Admin', NULL, NULL, NULL, 1, '2025-05-02 19:23:11', '2025-05-03 03:23:11', '1b1ab9b4fe46ae705f6995a6f92703a77615ba9a89766d950e8932be54e7c5f2', '2025-09-02 13:11:12', NULL, NULL, 1, 0, 1),
(25, 'Admin', 'Dental', NULL, 'dentaladmin@wmsu.edu.ph', '$2y$10$VPwoxm9tByG1.RLYlWCdNOoAsILkrefp0QB9QRxy7sGLi6.h0tbLi', 'Dental Admin', NULL, NULL, NULL, 1, '2025-05-02 19:23:11', '2025-05-03 03:23:11', NULL, NULL, NULL, NULL, 1, 0, 1),
(113, 'COLLEGE GUY', 'COLLEGE GUY', 'COLLEGE GUY', 'ahmadaquino.2002@gmail.com', '$2y$10$4P7cM.KC4ntOhAtNmD6aousHbbokrffAMTJ9TxIQHK3ULGZOCaN7W', 'Employee', NULL, NULL, NULL, 1, '2025-09-01 13:56:40', '2025-09-01 21:59:21', 'a8c11901b4fa21865c50e43647284b1af961ef4c8e46a398037a29a558128ef3', '2025-10-08 19:53:47', NULL, NULL, 1, 0, 1),
(114, 'EMPLOYER', 'EMPLOYER', 'EMPLOYER', 'xt202001524@wmsu.edu.ph', '$2y$10$1zuzkBc7a8I1n7V7qCRlTOcXYNDvUa.7jASxiSCLgx8X5I7zP3gTq', 'Highschool', NULL, '858cd11ce5a861b6ec62f6a3d0f3996cd7063d01e3b61bac675dc60341f5734d', '2025-09-01 21:59:46', 1, '2025-09-01 13:59:46', NULL, NULL, NULL, NULL, NULL, 0, 0, 1),
(115, 'SENIOR HIGHER', 'SENIOR HIGHER', 'SENIOR HIGHER', 'seniorhigher@wmsu.edu.ph', '$2y$10$N0YRnREB3Du7goqXekP7POcRMDDk1dCCXNm4wZL04rCRbojNVGCCu', 'Senior High School', NULL, '4403f573da36d318cf6a6e31925e28d5d664f9d4a7d3146b51d441673a894703', '2025-09-01 22:00:47', 1, '2025-09-01 14:00:47', NULL, NULL, NULL, NULL, NULL, 0, 0, 1),
(116, 'JUNIOR HIGHER', 'JUNIOR HIGHER', 'JUNIOR HIGHER', 'juniorhigher@gmail.com', '$2y$10$9G0deDbd7LasITMjUq/NmOUyN62ILmW7OGYc4YTIB3Go0GCdvGNaO', NULL, NULL, 'd02149087a3641425027c0d55a1adbb0c2f542cda7097f71d875970b7a3b3bac', '2025-09-01 22:01:27', 0, '2025-09-01 14:01:27', NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(117, 'INCOMING FRESHMAN GUY', 'INCOMING FRESHMAN GUY', 'INCOMING FRESHMAN GUY', 'incomingfreshman@gmail.com', '$2y$10$OfS6MAd4n1v87ZrpBVp5zeZxBz5WdR/t7xLBhYm9CZmxZu4KbIaFG', NULL, NULL, 'a93b6c43c19aab70573ed19a0b369d43bf4bf7f2c2b6c8089d9afdba55292a19', '2025-09-01 22:03:31', 0, '2025-09-01 14:03:31', NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(118, 'PARENT', 'PARENT', 'PARENT', 'parent@gmail.com', '$2y$10$.YzlKJxc0pGEnMroTVEgvOQtHvLqS0JeV8ZFUWZum.Ilg3F4nq6rq', NULL, NULL, '1a86ce19d8242697ea1c7711f96a37c4043d705b4f7bb29057a75ff861869cdc', '2025-09-01 22:04:08', 0, '2025-09-01 14:04:08', NULL, NULL, NULL, NULL, NULL, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `link` varchar(255) NOT NULL,
  `status` enum('read','unread') NOT NULL DEFAULT 'unread',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `type`, `title`, `description`, `link`, `status`, `created_at`, `updated_at`) VALUES
(61, 113, 'system', 'Academic Year Updated', 'The academic year has been updated to 2022 - 2023 (1st Semester, Quarter: 1).', '#', 'read', '2025-09-08 20:31:20', '2025-09-08 20:31:20'),
(62, 114, 'system', 'Academic Year Updated', 'The academic year has been updated to 2022 - 2023 (1st Semester, Quarter: 1).', '#', 'unread', '2025-09-08 20:31:20', '2025-09-08 20:31:20'),
(63, 115, 'system', 'Academic Year Updated', 'The academic year has been updated to 2022 - 2023 (1st Semester, Quarter: 1).', '#', 'unread', '2025-09-08 20:31:20', '2025-09-08 20:31:20'),
(64, 113, 'system', 'Academic Year Updated', 'The academic year has been updated to 2022 - 2023 (1st Semester, Quarter: 1).', '#', 'read', '2025-09-08 21:20:29', '2025-09-08 21:20:29'),
(65, 114, 'system', 'Academic Year Updated', 'The academic year has been updated to 2022 - 2023 (1st Semester, Quarter: 1).', '#', 'unread', '2025-09-08 21:20:29', '2025-09-08 21:20:29'),
(66, 115, 'system', 'Academic Year Updated', 'The academic year has been updated to 2022 - 2023 (1st Semester, Quarter: 1).', '#', 'unread', '2025-09-08 21:20:29', '2025-09-08 21:20:29');

-- --------------------------------------------------------

--
-- Table structure for table `waivers`
--

CREATE TABLE `waivers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `signature_path` varchar(255) NOT NULL,
  `date_signed` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `walk_ins`
--

CREATE TABLE `walk_ins` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `child_id` int(11) DEFAULT NULL,
  `queue_number` varchar(50) NOT NULL,
  `patient_name` varchar(150) NOT NULL,
  `time_arrived` time NOT NULL,
  `priority` enum('normal','high','emergency') NOT NULL DEFAULT 'normal',
  `status` enum('Waiting','In Progress','Completed','Cancelled') DEFAULT 'Waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `child_id` (`child_id`);

--
-- Indexes for table `certificate_logs`
--
ALTER TABLE `certificate_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `college_students`
--
ALTER TABLE `college_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `consent_forms`
--
ALTER TABLE `consent_forms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_consultation_child` (`child_id`),
  ADD KEY `fk_consultation_staff` (`staff_id`),
  ADD KEY `fk_consultation_patient` (`patient_id`);

--
-- Indexes for table `consultations_main`
--
ALTER TABLE `consultations_main`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `consultation_advice`
--
ALTER TABLE `consultation_advice`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `admin_user_id` (`admin_user_id`),
  ADD KEY `idx_admin_user` (`admin_user_id`),
  ADD KEY `idx_consultation_advice_status` (`status`),
  ADD KEY `idx_consultation_advice_user_id` (`user_id`);

--
-- Indexes for table `content`
--
ALTER TABLE `content`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_name` (`page_name`,`section_name`);

--
-- Indexes for table `csrf_tokens`
--
ALTER TABLE `csrf_tokens`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_token` (`user_id`,`token`);

--
-- Indexes for table `dental_consultations`
--
ALTER TABLE `dental_consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dental_consultation` (`consultation_id`);

--
-- Indexes for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `emergency_contacts_history`
--
ALTER TABLE `emergency_contacts_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `file_requirements`
--
ALTER TABLE `file_requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_page_section` (`page_name`,`section_name`);

--
-- Indexes for table `incoming_freshmen`
--
ALTER TABLE `incoming_freshmen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `medical_consultations`
--
ALTER TABLE `medical_consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_medical_consultation` (`consultation_id`);

--
-- Indexes for table `medical_documents`
--
ALTER TABLE `medical_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `child_id` (`child_id`);

--
-- Indexes for table `medical_info`
--
ALTER TABLE `medical_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medical_patient` (`patient_id`);

--
-- Indexes for table `medical_info_history`
--
ALTER TABLE `medical_info_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medical_patient` (`patient_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `notifications_admin`
--
ALTER TABLE `notifications_admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_user` (`user_id`);

--
-- Indexes for table `patients_history`
--
ALTER TABLE `patients_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_user` (`user_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consultation_id` (`consultation_id`);

--
-- Indexes for table `senior_high_students`
--
ALTER TABLE `senior_high_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `submission_status`
--
ALTER TABLE `submission_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_verification_token` (`verification_token`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `waivers`
--
ALTER TABLE `waivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `walk_ins`
--
ALTER TABLE `walk_ins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `child_id` (`child_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=162;

--
-- AUTO_INCREMENT for table `certificate_logs`
--
ALTER TABLE `certificate_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `college_students`
--
ALTER TABLE `college_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `consent_forms`
--
ALTER TABLE `consent_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT for table `consultations_main`
--
ALTER TABLE `consultations_main`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `consultation_advice`
--
ALTER TABLE `consultation_advice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `content`
--
ALTER TABLE `content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=901;

--
-- AUTO_INCREMENT for table `dental_consultations`
--
ALTER TABLE `dental_consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=212;

--
-- AUTO_INCREMENT for table `emergency_contacts_history`
--
ALTER TABLE `emergency_contacts_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `file_requirements`
--
ALTER TABLE `file_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `incoming_freshmen`
--
ALTER TABLE `incoming_freshmen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `medical_consultations`
--
ALTER TABLE `medical_consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `medical_documents`
--
ALTER TABLE `medical_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=213;

--
-- AUTO_INCREMENT for table `medical_info`
--
ALTER TABLE `medical_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=210;

--
-- AUTO_INCREMENT for table `medical_info_history`
--
ALTER TABLE `medical_info_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=204;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications_admin`
--
ALTER TABLE `notifications_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=541;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=219;

--
-- AUTO_INCREMENT for table `patients_history`
--
ALTER TABLE `patients_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `senior_high_students`
--
ALTER TABLE `senior_high_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `submission_status`
--
ALTER TABLE `submission_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `waivers`
--
ALTER TABLE `waivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `walk_ins`
--
ALTER TABLE `walk_ins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `certificate_logs`
--
ALTER TABLE `certificate_logs`
  ADD CONSTRAINT `certificate_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificate_logs_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `certificate_logs_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `fk_children_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `college_students`
--
ALTER TABLE `college_students`
  ADD CONSTRAINT `college_students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consultation_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`),
  ADD CONSTRAINT `fk_consultation_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consultation_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`);

--
-- Constraints for table `consultation_advice`
--
ALTER TABLE `consultation_advice`
  ADD CONSTRAINT `consultation_advice_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultation_advice_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `consultation_advice_ibfk_3` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dental_consultations`
--
ALTER TABLE `dental_consultations`
  ADD CONSTRAINT `fk_dental_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incoming_freshmen`
--
ALTER TABLE `incoming_freshmen`
  ADD CONSTRAINT `incoming_freshmen_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_consultations`
--
ALTER TABLE `medical_consultations`
  ADD CONSTRAINT `fk_medical_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_documents`
--
ALTER TABLE `medical_documents`
  ADD CONSTRAINT `medical_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_documents_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `senior_high_students`
--
ALTER TABLE `senior_high_students`
  ADD CONSTRAINT `senior_high_students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_status`
--
ALTER TABLE `submission_status`
  ADD CONSTRAINT `submission_status_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `waivers`
--
ALTER TABLE `waivers`
  ADD CONSTRAINT `waivers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `walk_ins`
--
ALTER TABLE `walk_ins`
  ADD CONSTRAINT `walk_ins_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `walk_ins_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
