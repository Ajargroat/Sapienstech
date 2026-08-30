-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 30, 2026 at 03:09 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sapienstech_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `answers`
--

CREATE TABLE `answers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `question_id` bigint(20) UNSIGNED NOT NULL,
  `answer_text` text NOT NULL,
  `answer_image_path` varchar(255) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attempt_answers`
--

CREATE TABLE `attempt_answers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `attempt_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `question_id` bigint(20) UNSIGNED NOT NULL,
  `chosen_answer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `consultant_id` bigint(20) UNSIGNED NOT NULL COMMENT 'references users.id',
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `visibility` enum('public','students_only') NOT NULL DEFAULT 'public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_post_images`
--

CREATE TABLE `blog_post_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `post_id` bigint(20) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `pdf_file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chapters`
--

CREATE TABLE `chapters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `book_id` bigint(20) UNSIGNED NOT NULL,
  `chapter_number` int(11) NOT NULL,
  `chapter_title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultant_student_assignments`
--

CREATE TABLE `consultant_student_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT 'the consultant/staff member',
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contents`
--

CREATE TABLE `contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(100) NOT NULL COMMENT 'e.g. homepage, about, contact',
  `title` varchar(255) DEFAULT NULL,
  `body` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `domains`
--

CREATE TABLE `domains` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `domain` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `domains`
--

INSERT INTO `domains` (`id`, `tenant_id`, `domain`, `is_primary`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'tenant1.sapienstech.local', 1, NULL, '2026-08-26 16:46:41', '2026-08-26 16:46:41');

-- --------------------------------------------------------

--
-- Table structure for table `event_comments`
--

CREATE TABLE `event_comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `commenter_type` enum('user','student') NOT NULL,
  `commenter_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `commenter_student_id` bigint(20) UNSIGNED DEFAULT NULL,
  `comment_text` text NOT NULL,
  `commented_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `features`
--

CREATE TABLE `features` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(100) NOT NULL COMMENT 'e.g. blog, student_evaluation, chat, ai_assistant',
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_comments`
--

CREATE TABLE `item_comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `commenter_type` enum('user','student') NOT NULL,
  `commenter_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `commenter_student_id` bigint(20) UNSIGNED DEFAULT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `chapter_id` bigint(20) UNSIGNED NOT NULL,
  `topic_id` bigint(20) UNSIGNED DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_image_path` varchar(255) DEFAULT NULL,
  `solution_text` text DEFAULT NULL,
  `solution_image_path` varchar(255) DEFAULT NULL,
  `question_number_in_book` varchar(50) DEFAULT NULL,
  `difficulty` enum('Easy','Medium','Hard') DEFAULT NULL,
  `question_type` varchar(50) NOT NULL DEFAULT 'multiple_choice',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_events`
--

CREATE TABLE `schedule_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '1=Monday, 7=Sunday',
  `event_color` varchar(7) NOT NULL DEFAULT '#007bff',
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_items`
--

CREATE TABLE `schedule_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `week_start_date` date NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `color` varchar(50) NOT NULL,
  `item_type` enum('consultant_event','student_personal_block') NOT NULL,
  `created_by_type` enum('user','student') NOT NULL,
  `created_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by_student_id` bigint(20) UNSIGNED DEFAULT NULL,
  `link_url` varchar(2083) DEFAULT NULL,
  `book_name` varchar(255) DEFAULT NULL,
  `test_count` int(11) DEFAULT NULL,
  `page_count` int(11) DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completion_timestamp` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `schedule_items`
--

INSERT INTO `schedule_items` (`id`, `tenant_id`, `student_id`, `week_start_date`, `title`, `description`, `start_datetime`, `end_datetime`, `color`, `item_type`, `created_by_type`, `created_by_user_id`, `created_by_student_id`, `link_url`, `book_name`, `test_count`, `page_count`, `is_completed`, `completion_timestamp`, `created_at`, `updated_at`) VALUES
(1, 1, 48, '2026-08-29', 'as', NULL, '2026-08-29 07:15:00', '2026-08-29 08:45:00', '#3b82f6', 'consultant_event', 'user', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-08-30 17:28:31', '2026-08-30 17:28:31');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `grade` varchar(50) DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `major` varchar(50) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `tenant_id`, `name`, `email`, `password`, `grade`, `gender`, `major`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(40, 1, 'مازیار', 'mahmoudieh.simin@example.net', '$2y$12$9MlyrSmeZFP4N0CQM8eCxe.et1IG5BEYPD3cOG8F7gSfQ0BWaSjTG', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(41, 1, 'مهربانو', 'dhusseini@example.net', '$2y$12$t2oRNd9dA.oz9cNVavj6dOvckm014a9GbZ..hOpumkG74Z98fDEOi', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(42, 1, 'پارسا', 'yaghoub78@example.net', '$2y$12$4ePhki0Y5FMB9RU.DH2.gOqYjLudApitMy5iWfKCoWAvnjTz50z.i', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(43, 1, 'پارمین', 'laleh63@example.net', '$2y$12$u2n8f./xyPu5q0O5p3XLFOBZhWA0mpKqVPjc0mlHhvKtJHQbtqE0e', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(44, 1, 'گلنسا', 'yrahmani@example.com', '$2y$12$mAsDsH4Zi3i8/yW7oi7qfu/8hApcYngVSuQePEKXhSrsEYrq5qNAq', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(45, 1, 'پیروزه', 'mhusseini@example.org', '$2y$12$pH6ytrt6pkVzpur3dzh5dOpYSuhzebGTR3nS/e6kVxevsxQf03bJy', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(46, 1, 'فرامرز', 'salehi.anousheh@example.net', '$2y$12$vMzthZH9Gz8j/LBdacwB0.ZYtLAIGjfEx4W8xmtF0PXptN9jErE8u', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(47, 1, 'تبسم', 'namazi.mahshid@example.net', '$2y$12$LNBLB/H.AqTeZ0CFKjkEkuqujwIMkyF0AeM5yBm6BSTVK1GLR9yNu', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(48, 1, 'آتاناز', 'khorsandi.mohammad@example.net', '$2y$12$.enRFrABHN54pFa5BIsEc.jmIeOPIaX2GBRZjnTsg2sj4kDCUhGa6', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(49, 1, 'بارمان', 'lmokri@example.org', '$2y$12$s/rESs4aKb7N7eXTowhKSO9jR5zpXB/8FNRjBXyZXfC5feYw9hbO2', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(50, 1, 'عدیله', 'hijazi.saman@example.org', '$2y$12$7iAnReiTA5JP.nvTh.8N3.m9RyQk6nuvfgBlr4szIpwAAZboh/Ji.', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(51, 1, 'آذین بانو', 'salemi.kourosh@example.com', '$2y$12$DVTBjILimVUzUL0/L7wUheWhDcboPyYxJNqn3xf5Wiv0TDkuEFQkO', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(52, 1, 'امیر', 'farnaz.mahmoudi@example.org', '$2y$12$x3t19Lt8iW8rAvlLqy14euJotoQmrM2IYaP7HuEUp6T8pjHdwZQ/m', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(53, 1, 'قباد', 'ljamshidi@example.com', '$2y$12$u3h8r3y6ZX0R5P7EdLe9/eF73bqKGTpRPyQTZZOTA9Js.nMtSuwXS', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(54, 1, 'زرتشت', 'mtalebi@example.net', '$2y$12$8qkucdBYcy5K9sN3TX/0s.xnrKGeIRLWwi91P747ZUSok/hj4vawe', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(55, 1, 'بهامین', 'bnamdar@example.com', '$2y$12$g5qAK5XuQJj0o8HEG3K6oei/nZ0c9WTtIZvTJtsTU9KWsUFXQ2qLK', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(56, 1, 'خندان', 'shahin.ahmadi@example.org', '$2y$12$IK9DJrE3Y7Mjd6fGA.7cfu6/dFRWOfBXVkeL6NUCNmig59CZtlGJG', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(57, 1, 'سیامک', 'meysam.asadi@example.com', '$2y$12$C5SjPbNbaWTl1yRmtO8TguL5YM0UL/DRfN5UXfOmtdAv6FkDnQrm6', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(58, 1, 'آتنا', 'farhad99@example.org', '$2y$12$aisYm3lWOi0E852ZtrmZCesnXrgBasM23OeuTGAtW1ZYkGCt4nRKW', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13'),
(59, 1, 'چیکا', 'manuchehr.zandi@example.org', '$2y$12$veL/rSHjSb79/vYscAhHmeZ.HKFIS0z0Pbbohn6FlvHgpO5zFAu2y', NULL, NULL, NULL, NULL, NULL, '2026-08-28 17:46:13', '2026-08-28 17:46:13');

-- --------------------------------------------------------

--
-- Table structure for table `student_assigned_quizzes`
--

CREATE TABLE `student_assigned_quizzes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `test_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_completed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_book_permissions`
--

CREATE TABLE `student_book_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `book_id` bigint(20) UNSIGNED NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_flagged_questions`
--

CREATE TABLE `student_flagged_questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `question_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_test_attempts`
--

CREATE TABLE `student_test_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `assignment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `test_id` bigint(20) UNSIGNED DEFAULT NULL,
  `score_simple_percent` decimal(5,2) NOT NULL,
  `score_negative_percent` decimal(5,2) NOT NULL,
  `time_taken_seconds` int(11) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_weekly_settings`
--

CREATE TABLE `student_weekly_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `week_start_date` date NOT NULL,
  `display_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`display_config`)),
  `last_modified_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `status` enum('trial','active','suspended') NOT NULL DEFAULT 'trial',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `slug`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Tenant One', 'tenant-one', 'active', '2026-08-26 16:46:40', '2026-08-26 16:46:40');

-- --------------------------------------------------------

--
-- Table structure for table `tests`
--

CREATE TABLE `tests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `test_title` varchar(255) NOT NULL,
  `time_limit_minutes` int(11) DEFAULT NULL,
  `created_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_questions`
--

CREATE TABLE `test_questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `test_id` bigint(20) UNSIGNED NOT NULL,
  `question_id` bigint(20) UNSIGNED NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `topics`
--

CREATE TABLE `topics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `chapter_id` bigint(20) UNSIGNED NOT NULL,
  `topic_title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL only for platform_admin',
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('platform_admin','tenant_admin','consultant_staff') NOT NULL DEFAULT 'consultant_staff',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `website_configs`
--

CREATE TABLE `website_configs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `theme` varchar(100) NOT NULL DEFAULT 'default',
  `logo_path` varchar(255) DEFAULT NULL,
  `favicon_path` varchar(255) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT NULL,
  `secondary_color` varchar(7) DEFAULT NULL,
  `font` varchar(100) DEFAULT NULL,
  `layout_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'header/footer/nav structure' CHECK (json_valid(`layout_config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `answers_tenant_id_index` (`tenant_id`),
  ADD KEY `answers_question_id_index` (`question_id`);

--
-- Indexes for table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attempt_answers_attempt_id_question_id_unique` (`attempt_id`,`question_id`),
  ADD KEY `attempt_answers_tenant_id_index` (`tenant_id`),
  ADD KEY `attempt_answers_student_id_index` (`student_id`),
  ADD KEY `attempt_answers_question_id_index` (`question_id`),
  ADD KEY `attempt_answers_chosen_answer_id_index` (`chosen_answer_id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blog_posts_tenant_id_index` (`tenant_id`),
  ADD KEY `blog_posts_consultant_id_index` (`consultant_id`);

--
-- Indexes for table `blog_post_images`
--
ALTER TABLE `blog_post_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blog_post_images_tenant_id_index` (`tenant_id`),
  ADD KEY `blog_post_images_post_id_index` (`post_id`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `books_tenant_id_index` (`tenant_id`);

--
-- Indexes for table `chapters`
--
ALTER TABLE `chapters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chapters_tenant_id_index` (`tenant_id`),
  ADD KEY `chapters_book_id_index` (`book_id`);

--
-- Indexes for table `consultant_student_assignments`
--
ALTER TABLE `consultant_student_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `csa_user_id_student_id_unique` (`user_id`,`student_id`),
  ADD KEY `csa_tenant_id_index` (`tenant_id`),
  ADD KEY `csa_student_id_index` (`student_id`);

--
-- Indexes for table `contents`
--
ALTER TABLE `contents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contents_tenant_id_key_unique` (`tenant_id`,`key`);

--
-- Indexes for table `domains`
--
ALTER TABLE `domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `domains_domain_unique` (`domain`),
  ADD KEY `domains_tenant_id_index` (`tenant_id`);

--
-- Indexes for table `event_comments`
--
ALTER TABLE `event_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_comments_tenant_id_index` (`tenant_id`),
  ADD KEY `event_comments_event_id_index` (`event_id`),
  ADD KEY `event_comments_commenter_user_id_index` (`commenter_user_id`),
  ADD KEY `event_comments_commenter_student_id_index` (`commenter_student_id`);

--
-- Indexes for table `features`
--
ALTER TABLE `features`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `features_tenant_id_key_unique` (`tenant_id`,`key`);

--
-- Indexes for table `item_comments`
--
ALTER TABLE `item_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_comments_tenant_id_index` (`tenant_id`),
  ADD KEY `item_comments_item_id_index` (`item_id`),
  ADD KEY `item_comments_commenter_user_id_index` (`commenter_user_id`),
  ADD KEY `item_comments_commenter_student_id_index` (`commenter_student_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `questions_tenant_id_index` (`tenant_id`),
  ADD KEY `questions_chapter_id_index` (`chapter_id`),
  ADD KEY `questions_topic_id_index` (`topic_id`);

--
-- Indexes for table `schedule_events`
--
ALTER TABLE `schedule_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_events_tenant_id_index` (`tenant_id`),
  ADD KEY `schedule_events_user_id_index` (`user_id`);

--
-- Indexes for table `schedule_items`
--
ALTER TABLE `schedule_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_items_tenant_id_index` (`tenant_id`),
  ADD KEY `schedule_items_student_id_index` (`student_id`),
  ADD KEY `schedule_items_created_by_user_id_index` (`created_by_user_id`),
  ADD KEY `schedule_items_created_by_student_id_index` (`created_by_student_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `students_tenant_id_email_unique` (`tenant_id`,`email`);

--
-- Indexes for table `student_assigned_quizzes`
--
ALTER TABLE `student_assigned_quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `saq_tenant_id_index` (`tenant_id`),
  ADD KEY `saq_test_id_index` (`test_id`),
  ADD KEY `saq_student_id_index` (`student_id`),
  ADD KEY `saq_assigned_by_user_id_index` (`assigned_by_user_id`);

--
-- Indexes for table `student_book_permissions`
--
ALTER TABLE `student_book_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sbp_student_id_book_id_unique` (`student_id`,`book_id`),
  ADD KEY `sbp_tenant_id_index` (`tenant_id`),
  ADD KEY `sbp_book_id_index` (`book_id`);

--
-- Indexes for table `student_flagged_questions`
--
ALTER TABLE `student_flagged_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sfq_student_id_question_id_unique` (`student_id`,`question_id`),
  ADD KEY `sfq_tenant_id_index` (`tenant_id`),
  ADD KEY `sfq_question_id_index` (`question_id`);

--
-- Indexes for table `student_test_attempts`
--
ALTER TABLE `student_test_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sta_tenant_id_index` (`tenant_id`),
  ADD KEY `sta_assignment_id_index` (`assignment_id`),
  ADD KEY `sta_student_id_index` (`student_id`),
  ADD KEY `sta_test_id_index` (`test_id`);

--
-- Indexes for table `student_weekly_settings`
--
ALTER TABLE `student_weekly_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sws_student_id_week_start_date_unique` (`student_id`,`week_start_date`),
  ADD KEY `sws_tenant_id_index` (`tenant_id`),
  ADD KEY `sws_last_modified_by_user_id_index` (`last_modified_by_user_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenants_slug_unique` (`slug`);

--
-- Indexes for table `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tests_tenant_id_index` (`tenant_id`),
  ADD KEY `tests_created_by_user_id_index` (`created_by_user_id`);

--
-- Indexes for table `test_questions`
--
ALTER TABLE `test_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `test_questions_test_id_question_id_unique` (`test_id`,`question_id`),
  ADD KEY `test_questions_tenant_id_index` (`tenant_id`),
  ADD KEY `test_questions_question_id_index` (`question_id`);

--
-- Indexes for table `topics`
--
ALTER TABLE `topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `topics_tenant_id_index` (`tenant_id`),
  ADD KEY `topics_chapter_id_index` (`chapter_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_tenant_id_email_unique` (`tenant_id`,`email`);

--
-- Indexes for table `website_configs`
--
ALTER TABLE `website_configs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `website_configs_tenant_id_unique` (`tenant_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `answers`
--
ALTER TABLE `answers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_post_images`
--
ALTER TABLE `blog_post_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chapters`
--
ALTER TABLE `chapters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consultant_student_assignments`
--
ALTER TABLE `consultant_student_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contents`
--
ALTER TABLE `contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `domains`
--
ALTER TABLE `domains`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `event_comments`
--
ALTER TABLE `event_comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `features`
--
ALTER TABLE `features`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_comments`
--
ALTER TABLE `item_comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_events`
--
ALTER TABLE `schedule_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_items`
--
ALTER TABLE `schedule_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `student_assigned_quizzes`
--
ALTER TABLE `student_assigned_quizzes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_book_permissions`
--
ALTER TABLE `student_book_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_flagged_questions`
--
ALTER TABLE `student_flagged_questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_test_attempts`
--
ALTER TABLE `student_test_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_weekly_settings`
--
ALTER TABLE `student_weekly_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `tests`
--
ALTER TABLE `tests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test_questions`
--
ALTER TABLE `test_questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `topics`
--
ALTER TABLE `topics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `website_configs`
--
ALTER TABLE `website_configs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `answers`
--
ALTER TABLE `answers`
  ADD CONSTRAINT `answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `answers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  ADD CONSTRAINT `attempt_answers_attempt_id_foreign` FOREIGN KEY (`attempt_id`) REFERENCES `student_test_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attempt_answers_chosen_answer_id_foreign` FOREIGN KEY (`chosen_answer_id`) REFERENCES `answers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attempt_answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attempt_answers_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attempt_answers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD CONSTRAINT `blog_posts_consultant_id_foreign` FOREIGN KEY (`consultant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blog_posts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `blog_post_images`
--
ALTER TABLE `blog_post_images`
  ADD CONSTRAINT `blog_post_images_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blog_post_images_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `books`
--
ALTER TABLE `books`
  ADD CONSTRAINT `books_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `chapters`
--
ALTER TABLE `chapters`
  ADD CONSTRAINT `chapters_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chapters_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `consultant_student_assignments`
--
ALTER TABLE `consultant_student_assignments`
  ADD CONSTRAINT `csa_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `csa_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `csa_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contents`
--
ALTER TABLE `contents`
  ADD CONSTRAINT `contents_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `domains`
--
ALTER TABLE `domains`
  ADD CONSTRAINT `domains_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `event_comments`
--
ALTER TABLE `event_comments`
  ADD CONSTRAINT `event_comments_commenter_student_id_foreign` FOREIGN KEY (`commenter_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `event_comments_commenter_user_id_foreign` FOREIGN KEY (`commenter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `event_comments_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `schedule_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_comments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `features`
--
ALTER TABLE `features`
  ADD CONSTRAINT `features_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `item_comments`
--
ALTER TABLE `item_comments`
  ADD CONSTRAINT `item_comments_commenter_student_id_foreign` FOREIGN KEY (`commenter_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `item_comments_commenter_user_id_foreign` FOREIGN KEY (`commenter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `item_comments_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `schedule_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_comments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_chapter_id_foreign` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `questions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `questions_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `schedule_events`
--
ALTER TABLE `schedule_events`
  ADD CONSTRAINT `schedule_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `schedule_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule_items`
--
ALTER TABLE `schedule_items`
  ADD CONSTRAINT `schedule_items_created_by_student_id_foreign` FOREIGN KEY (`created_by_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_items_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_items_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_items_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `student_assigned_quizzes`
--
ALTER TABLE `student_assigned_quizzes`
  ADD CONSTRAINT `saq_assigned_by_user_id_foreign` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saq_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saq_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `saq_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_book_permissions`
--
ALTER TABLE `student_book_permissions`
  ADD CONSTRAINT `sbp_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sbp_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sbp_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `student_flagged_questions`
--
ALTER TABLE `student_flagged_questions`
  ADD CONSTRAINT `sfq_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sfq_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sfq_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `student_test_attempts`
--
ALTER TABLE `student_test_attempts`
  ADD CONSTRAINT `sta_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `student_assigned_quizzes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sta_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sta_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `sta_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_weekly_settings`
--
ALTER TABLE `student_weekly_settings`
  ADD CONSTRAINT `sws_last_modified_by_user_id_foreign` FOREIGN KEY (`last_modified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sws_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sws_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `tests`
--
ALTER TABLE `tests`
  ADD CONSTRAINT `tests_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tests_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `test_questions`
--
ALTER TABLE `test_questions`
  ADD CONSTRAINT `test_questions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_questions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `test_questions_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `topics`
--
ALTER TABLE `topics`
  ADD CONSTRAINT `topics_chapter_id_foreign` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `topics_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `website_configs`
--
ALTER TABLE `website_configs`
  ADD CONSTRAINT `website_configs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
