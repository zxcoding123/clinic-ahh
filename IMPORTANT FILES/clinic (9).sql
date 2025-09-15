-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 31, 2025 at 04:11 PM
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `patient_id` int(11) DEFAULT NULL,
  `child_id` int(11) DEFAULT NULL,
  `staff_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `consultation_date` date NOT NULL,
  `consultation_time` time NOT NULL,
  `grade_course_section` varchar(100) DEFAULT NULL,
  `age` int(11) NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `weight` varchar(10) DEFAULT NULL,
  `height` int(11) NOT NULL,
  `birthday` date NOT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `temperature` varchar(10) DEFAULT NULL,
  `heart_rate` varchar(10) DEFAULT NULL,
  `oxygen_saturation` varchar(10) DEFAULT NULL,
  `respiratory_rate` int(11) NOT NULL,
  `complaints` text NOT NULL,
  `diagnosis` text NOT NULL,
  `treatment` text NOT NULL,
  `staff_signature` longtext DEFAULT NULL,
  `staff_name` text NOT NULL,
  `consultation_type` enum('medical','dental') NOT NULL DEFAULT 'medical',
  `test_results` text NOT NULL,
  `history` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `patient_id`, `child_id`, `staff_id`, `name`, `consultation_date`, `consultation_time`, `grade_course_section`, `age`, `sex`, `weight`, `height`, `birthday`, `blood_pressure`, `temperature`, `heart_rate`, `oxygen_saturation`, `respiratory_rate`, `complaints`, `diagnosis`, `treatment`, `staff_signature`, `staff_name`, `consultation_type`, `test_results`, `history`, `created_at`, `updated_at`) VALUES
(89, NULL, NULL, 23, 'Test Test', '2025-08-31', '20:45:00', '0', 23, '', '12', 0, '2002-07-02', '1', '12', '12', '12', 0, 'This patient is here for: check-up', 'dude is safe', 'dude is healthy', 'data:image/png;base64,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', 'ahmad', 'medical', 'none', '', '2025-08-31 12:48:02', '2025-08-31 12:48:02'),
(90, NULL, NULL, 23, 'Test Test', '2025-08-31', '20:50:00', '0', 23, '', '1', 0, '2002-07-02', '1', '1', '1', '1', 0, 'Teeth check-up because pain', 'all goods naman', 'maarte lang yung pasyente', 'data:image/png;base64,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', '', 'dental', '', '', '2025-08-31 12:53:18', '2025-08-31 12:53:18');

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
(23, '27274653be77eef8675806f0e00b520cc0c4cb4c4c3a686da5ab8cbe187173e4', '2025-08-31 21:35:19');

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
(201, 209, 'Test', 'Test', 'Test', '09536640199', 'Parent', 'Test', '2025-08-31 13:25:42');

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
(195, 205, 'asthma,rhinitis,hyperthyroidism,hypothyroidism,anemia,migraine,epilepsy,gerd,bowel_syndrome,psychiatric,depression,bipolar,anxiety,panic,ptsd,schizophrenia,lupus,hypertension,diabetes,dyslipidemia,arthritis,sle,pcos,Other Mental Illness: Dementia,Cancer: Brain,Allergies: Patatas,Other: Foot Injury', 'paracetamol:1:mg:once daily,Something:1:mg:twice daily', 'fully', 0, NULL, 0, 0, NULL, 'varicella,dengue,tuberculosis,pneumonia,uti,appendicitis,cholecystitis,measles,typhoid fever,amoebiasis,kidney stones,injury,burn,stab,fracture', '2000:Removal of Brain', 'asthma,rhinitis,hyperthyroidism,hypothyroidism,anemia,migraine,diabetes,stroke,heart_failure,coronary,copd,chronic_kidney,epilepsy,gerd,bowel_syndrome,liver_disease,psychiatric,depression,bipolar,anxiety,panic,ptsd,schizophrenia,lupus,hypertension,dyslipidemia,arthritis,pcos,Other Mental Illness (Family):Dementia,Cancer:Brain,Family Allergies:Shrimp,Other (Family):Foot Injury', 'Test', '2025-08-31 12:32:14', '2025-08-31 12:33:54'),
(196, 110, 'asthma,rhinitis,hyperthyroidism,hypothyroidism,anemia,migraine,epilepsy,gerd,bowel_syndrome,hypertension,diabetes,dyslipidemia,arthritis,lupus,pcos,cancer,other,depression,anxiety,panic,stress,Allergies: asd', 'asd:1:mg:once daily', 'fully', 0, NULL, 0, 0, NULL, 'varicella,dengue,cholecystitis,measles,amoebiasis,kidney stones', '2000:Appendicitis', NULL, NULL, '2025-08-31 13:02:45', '2025-08-31 13:02:45'),
(197, 206, 'asthma,Allergies: asd', 'paracetamol:1:mg:once daily', 'fully', 0, NULL, 0, 0, NULL, NULL, '2000:Appendicitis', NULL, NULL, '2025-08-31 13:08:18', '2025-08-31 13:08:18'),
(198, 208, 'asthma, Allergies: Test, rhinitis, hyperthyroidism, hypothyroidism, anemia, migraine, epilepsy, gerd, psychiatric, depression, bipolar, anxiety, panic, ptsd, schizophrenia, lupus, hypertension, diabetes, dyslipidemia, arthritis, pcos, Cancer: Test, Other: Test, psychiatric,depression,bipolar,anxiety,panic,ptsd,schizophrenia,Other Mental Illness: Test', 'Test: 1 mg twice daily', 'fully', NULL, '', NULL, NULL, NULL, 'varicella, dengue, tuberculosis, pneumonia, UTI, appendicitis, Cholecystitis, measles, typhoid fever, amoebiasis, kidney stones, injury, burn, stab, fracture, Other: Test', '2000: Test', 'hypertension, congestive, diabetes, chronic_kidney, copd, gerd, bowel_syndrome, epilepsy, stroke, asthma, tuberculosis, rhinitis, hyperthyroidism, hypothyroidism, anemia, migraine, psychiatric, depression, bipolar, anxiety, panic, ptsd, schizophrenia, liver_disease, arthritis, blood_disorder, dyslipidemia, pcos, lupus', NULL, '2025-08-31 13:25:42', '2025-08-31 13:25:42'),
(199, 209, NULL, ':  mg', 'fully', NULL, '', NULL, NULL, NULL, '', NULL, '', NULL, '2025-08-31 13:25:42', '2025-08-31 13:25:42');

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
(202, 205, 'asthma,rhinitis,hyperthyroidism,hypothyroidism,anemia,migraine,epilepsy,gerd,bowel_syndrome,psychiatric,depression,bipolar,anxiety,panic,ptsd,schizophrenia,lupus,hypertension,diabetes,dyslipidemia,arthritis,sle,pcos,Other Mental Illness: Dementia,Cancer: Brain,Allergies: Patatas,Other: Foot Injury', 'paracetamol:1:mg:once daily,Something:1:mg:twice daily', 'fully', 0, NULL, 0, 0, NULL, 'varicella,dengue,tuberculosis,pneumonia,uti,appendicitis,cholecystitis,measles,typhoid fever,amoebiasis,kidney stones,injury,burn,stab,fracture', '2000:Removal of Brain', 'asthma,rhinitis,hyperthyroidism,hypothyroidism,anemia,migraine,diabetes,stroke,heart_failure,coronary,copd,chronic_kidney,epilepsy,gerd,bowel_syndrome,liver_disease,psychiatric,depression,bipolar,anxiety,panic,ptsd,schizophrenia,lupus,hypertension,dyslipidemia,arthritis,pcos,Other Mental Illness (Family):Dementia,Cancer:Brain,Family Allergies:Shrimp,Other (Family):Foot Injury', 'Test', '2025-08-31 13:02:45', '2025-08-31 13:02:45', '2025-08-31 21:02:45');

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
(430, 25, 'medical_certificate_submission', 'New Medical Certificate Submission', 'Parent Parent (Parent) has submitted a medical certificate', 'medical-documents.php', 'unread', '2025-08-31 21:29:52', '2025-08-31 21:29:52');

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

--
-- Dumping data for table `patients_history`
--

INSERT INTO `patients_history` (`id`, `user_id`, `student_id`, `surname`, `firstname`, `middlename`, `suffix`, `birthday`, `age`, `sex`, `blood_type`, `religion`, `nationality`, `civil_status`, `email`, `contact_number`, `city_address`, `provincial_address`, `photo_path`, `grade_level`, `grading_quarter`, `track_strand`, `section`, `semester`, `department`, `course`, `year_level`, `position`, `employee_id`, `id_path`, `cor_path`, `created_at`, `updated_at`, `archived_at`) VALUES
(125, 110, '2020-01524', 'Test', 'Test', 'Test', NULL, '2002-07-02', 23, 'male', 'A+', 'OTHER: Atheist', 'Filipino', 'single', 'ahmadaquino.2002@gmail.com', '09536640199', 'City address', 'City address', 'Uploads/patient_photos/a40176c1f41e377383af7bd9a87c81d5.jpg', NULL, NULL, NULL, NULL, 'Midterm', 'CCS', 'BSCompSci', 1, NULL, NULL, NULL, NULL, '2025-08-31 12:32:14', '2025-08-31 12:32:14', '2025-08-31 13:01:05'),
(126, 110, '2020-01524', 'Test', 'Test', 'Test', NULL, '2002-07-02', 23, 'male', 'A+', 'OTHER: Atheist', 'Filipino', 'single', 'ahmadaquino.2002@gmail.com', '09536640199', 'City address', 'City address', 'Uploads/patient_photos/a40176c1f41e377383af7bd9a87c81d5.jpg', NULL, NULL, NULL, NULL, 'Midterm', 'CCS', 'BSCompSci', 1, NULL, NULL, NULL, NULL, '2025-08-31 12:32:14', '2025-08-31 12:32:14', '2025-08-31 13:01:05');

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

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `consultation_id`, `patient_name`, `age`, `sex`, `diagnosis`, `medications`, `prescribing_physician`, `physician_signature`, `prescription_date`, `created_at`, `updated_at`) VALUES
(53, 89, 'Test Test', 23, 'male', 'dude is safe', '0', 'FELICITAS ASUNCION C. ELAGO, MD', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAZAAAABLCAYAAABeMdGUAAAL/UlEQVR4Aeydu7LsRhWGB7C5g7mYFKrsABKIgAAKCgpSiOARIOY9eAN4A4oIUigITAJEJoHABmLMxdwpMPb6ts/a7q3TmpFGmplu6TvV/2mN1Ope/bW0/tHM2fu8+eAfCUhAAhKQwBkENJAzoHmKBCQgAQkcDhqIV4EEbkXAcSXQOQENpPMFNHwJS', '2025-08-31', '2025-08-31 12:48:02', '2025-08-31 12:48:02'),
(54, 90, 'Test Test', 23, 'male', 'all goods naman', '0', 'FELICITAS ASUNCION C. ELAGO, MD', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAZAAAABLCAYAAABeMdGUAAAK+0lEQVR4Aeydua4sSRGGe9gXCRgJCXAQWOCABR6L5g1Agodg8cAEC0zwWB4CJHgFFg8scMCawQIJic1gZyC+q/MzeWq6+1R1V3dVVn2jipN7ZOQXWRGn+17dec3B/yQgAQlIQAIXEDCBXADNJRKQgAQkcDiYQLwFEliKgPtKoHMCJpDOHaj5EpCAB', '2025-08-31', '2025-08-31 12:53:18', '2025-08-31 12:53:18');

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
(25, 'Admin', 'Dental', NULL, 'dentaladmin@wmsu.edu.ph', '$2y$10$VPwoxm9tByG1.RLYlWCdNOoAsILkrefp0QB9QRxy7sGLi6.h0tbLi', 'Dental Admin', NULL, NULL, NULL, 1, '2025-05-02 19:23:11', '2025-05-03 03:23:11', NULL, NULL, NULL, NULL, 1, 0, 1);

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
(51, 110, 'medical_certificate', 'Medical Certificate Issued', 'Your medical certificate has been issued and sent to your email', '#', 'unread', '2025-08-31 20:41:03', '2025-08-31 20:41:03'),
(52, 110, 'medical_certificate', 'Medical Consultation Form Issued!', 'Your medical consultation and certificate has been issued and sent to your email!', '#', 'unread', '2025-08-31 20:48:12', '2025-08-31 20:48:12'),
(53, 110, 'dental_certificate', 'Dental Consultation Form Issued!', 'Your dental consultation and certificate has been issued and sent to your email!', '#', 'unread', '2025-08-31 20:53:30', '2025-08-31 20:53:30'),
(54, 110, 'profile_reminder', 'Profile Update Reminder', 'Please review and update your patient profile and account details to ensure they are accurate.', '', 'unread', '2025-08-31 20:59:52', '2025-08-31 20:59:52');

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
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `idx_consultation_date` (`consultation_date`);

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
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `consent_forms`
--
ALTER TABLE `consent_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

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
-- AUTO_INCREMENT for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=202;

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
-- AUTO_INCREMENT for table `medical_documents`
--
ALTER TABLE `medical_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=213;

--
-- AUTO_INCREMENT for table `medical_info`
--
ALTER TABLE `medical_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT for table `medical_info_history`
--
ALTER TABLE `medical_info_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=203;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications_admin`
--
ALTER TABLE `notifications_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=431;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=210;

--
-- AUTO_INCREMENT for table `patients_history`
--
ALTER TABLE `patients_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `senior_high_students`
--
ALTER TABLE `senior_high_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

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
  ADD CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `consultations_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_consultations_staff_id` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `consultation_advice`
--
ALTER TABLE `consultation_advice`
  ADD CONSTRAINT `consultation_advice_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultation_advice_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `consultation_advice_ibfk_3` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incoming_freshmen`
--
ALTER TABLE `incoming_freshmen`
  ADD CONSTRAINT `incoming_freshmen_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
