-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 06, 2026 at 01:41 PM
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
-- Database: `schema`
--

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `id` int(10) UNSIGNED NOT NULL,
  `incident_id` int(10) UNSIGNED NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` int(10) UNSIGNED NOT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attachments`
--

INSERT INTO `attachments` (`id`, `incident_id`, `filename`, `original`, `mime_type`, `size_bytes`, `uploaded_by`, `uploaded_at`) VALUES
(1, 1, 'd619712ebcc9a2da5de4c477.jpg', 'domy.jpg', 'image/jpeg', 126485, 1, '2026-05-04 10:27:43'),
(2, 3, '65210c379245f206160a0893.jpeg', 'analyse response.jpeg', 'image/jpeg', 267518, 2, '2026-05-04 18:48:16'),
(3, 5, '3efbb418011a9df2d0926803.jpg', 'sample ui.jpg', 'image/jpeg', 76952, 4, '2026-05-05 18:07:07');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(10) UNSIGNED DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'auth.register', 'user', 1, '{\"email\":\"chuwadominic51@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:24:52'),
(2, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:24:58'),
(3, 1, 'incident.submitted', 'incident', 1, '{\"ref\":\"INC-2026-0001\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:27:44'),
(4, 1, 'auth.logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:28:31'),
(5, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:32:10'),
(6, 1, 'auth.logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:35:12'),
(7, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:35:24'),
(8, 1, 'user.created', 'user', 2, '{\"email\":\"chuwadominic52@gmail.com\",\"role\":\"officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:37:03'),
(9, 1, 'user.created', 'user', 3, '{\"email\":\"support@gmail.com\",\"role\":\"officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:37:46'),
(10, 1, 'user.role_changed', 'user', 2, '{\"new_role\":\"reporter\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:37:58'),
(11, 1, 'settings.updated', NULL, NULL, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:42:07'),
(12, 1, 'incident.status_changed', 'incident', 1, '{\"from\":\"New\",\"to\":\"Acknowledged\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:43:02'),
(13, 1, 'auth.logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:43:46'),
(14, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:43:50'),
(15, 2, 'incident.submitted', 'incident', 2, '{\"ref\":\"INC-2026-0002\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:44:45'),
(16, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:46:10'),
(17, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:46:16'),
(18, 3, 'incident.status_changed', 'incident', 1, '{\"from\":\"Acknowledged\",\"to\":\"Resolved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:46:34'),
(19, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:46:51'),
(20, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 10:46:59'),
(21, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 12:16:32'),
(22, 1, 'auth.logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 12:21:25'),
(23, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 12:26:07'),
(24, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:11:25'),
(25, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:12:53'),
(26, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@admin.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:13:06'),
(27, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:13:30'),
(28, 1, 'auth.logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:16:01'),
(29, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"chuwadominic53@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:16:27'),
(30, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"chuwadominic54@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:16:35'),
(31, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"chuwadominic53@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:17:01'),
(32, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:23:08'),
(33, 3, 'incident.status_changed', 'incident', 2, '{\"from\":\"New\",\"to\":\"Closed\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:23:40'),
(34, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 18:24:16'),
(35, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:41:29'),
(36, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:41:38'),
(37, 1, 'user.role_changed', 'user', 2, '{\"new_role\":\"admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:42:54'),
(38, 1, 'user.role_changed', 'user', 2, '{\"new_role\":\"reporter\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:43:01'),
(39, 1, 'analytics.export', NULL, NULL, '{\"from\":\"2026-01-01\",\"to\":\"2026-05-04\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:43:22'),
(40, 1, 'auth.logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:45:14'),
(41, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 18:46:04'),
(42, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 18:46:08'),
(43, 2, 'incident.submitted', 'incident', 3, '{\"ref\":\"INC-2026-0003\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 18:48:21'),
(44, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:48:49'),
(45, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 18:49:18'),
(46, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 18:49:27'),
(47, 3, 'incident.status_changed', 'incident', 3, '{\"from\":\"New\",\"to\":\"Acknowledged\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 18:50:41'),
(48, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 18:50:45'),
(49, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 18:50:50'),
(50, 1, 'incident.note_added', 'incident', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:54:54'),
(51, 1, 'incident.status_changed', 'incident', 3, '{\"from\":\"Acknowledged\",\"to\":\"Acknowledged\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-04 18:55:08'),
(52, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-04 18:57:34'),
(53, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 04:32:36'),
(54, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:35:40'),
(55, 1, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:49:11'),
(56, 1, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:49:18'),
(57, 1, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:49:23'),
(58, 1, 'auth.logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:51:00'),
(59, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:51:05'),
(60, 3, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:51:18'),
(61, 3, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:51:28'),
(62, 3, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:56:50'),
(63, 3, 'incident.note_added', 'incident', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:57:36'),
(64, 3, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:57:37'),
(65, 3, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:58:26'),
(66, 3, 'incident.note_added', 'incident', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:58:41'),
(67, 3, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:58:41'),
(68, 3, 'incident.note_added', 'incident', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:59:02'),
(69, 3, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 04:59:02'),
(70, 3, 'incident.status_changed', 'incident', 3, '{\"from\":\"Acknowledged\",\"to\":\"In Progress\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 05:03:22'),
(71, 3, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 05:03:22'),
(72, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 05:03:42'),
(73, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 05:04:32'),
(74, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 05:15:41'),
(75, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:05:35'),
(76, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:05:45'),
(77, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:10:03'),
(78, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:10:37'),
(79, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:25:31'),
(80, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:27:17'),
(81, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:28:23'),
(82, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:37:22'),
(83, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:40:34'),
(84, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:43:32'),
(85, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 06:46:40'),
(86, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 07:00:16'),
(87, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 07:00:23'),
(88, 1, 'auth.logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 07:00:34'),
(89, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-05 07:00:39'),
(90, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:12:46'),
(91, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:29:39'),
(92, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:40:54'),
(93, 2, 'incident.submitted', 'incident', 4, '{\"ref\":\"INC-2026-0004\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:41:55'),
(94, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:42:12'),
(95, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:42:24'),
(96, 1, 'incident.status_changed', 'incident', 4, '{\"from\":\"New\",\"to\":\"Acknowledged\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:42:46'),
(97, 1, 'auth.logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:43:15'),
(98, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:43:29'),
(99, 3, 'incident.status_changed', 'incident', 4, '{\"from\":\"Acknowledged\",\"to\":\"In Progress\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:43:42'),
(100, 3, 'incident.note_added', 'incident', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:44:00'),
(101, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:44:07'),
(102, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:44:14'),
(103, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 07:44:27'),
(104, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 14:28:03'),
(105, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 14:28:03'),
(106, NULL, 'auth.register', 'user', 4, '{\"email\":\"hildakimaro720@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:04:48'),
(107, 4, 'auth.login', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:04:59'),
(108, 4, 'incident.submitted', 'incident', 5, '{\"ref\":\"INC-2026-0005\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:07:07'),
(109, 4, 'auth.logout', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:07:18'),
(110, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@gmailcom\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:07:34'),
(111, 1, 'auth.login', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:08:00'),
(112, 1, 'incident.note_added', 'incident', 5, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:08:59'),
(113, 1, 'incident.status_changed', 'incident', 5, '{\"from\":\"New\",\"to\":\"Acknowledged\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:09:15'),
(114, 1, 'auth.logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:09:23'),
(115, 4, 'auth.login', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:09:35'),
(116, 4, 'auth.logout', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:32:34'),
(117, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:32:56'),
(118, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-05 18:33:04'),
(119, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-06 14:34:08'),
(120, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-06 14:34:13'),
(121, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-06 14:36:01'),
(122, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-06 14:36:06'),
(123, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-06 14:38:19');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'Phishing & Social Engineering', 'Deceptive emails, fake websites, or manipulation to steal credentials', 1, '2026-05-04 08:07:25'),
(2, 'Malware & Ransomware', 'Virus, worm, trojan, or ransomware infection on campus devices', 1, '2026-05-04 08:07:25'),
(3, 'Unauthorized Access', 'Account compromise or unauthorized login to systems', 1, '2026-05-04 08:07:25'),
(4, 'Network Intrusion / DoS', 'Suspicious network activity or denial-of-service attacks', 1, '2026-05-04 08:07:25'),
(5, 'Data Breach & Data Loss', 'Accidental or deliberate exposure of sensitive data', 1, '2026-05-04 08:07:25'),
(6, 'System Misuse & Policy Violation', 'Abuse of campus resources or violation of IT policy', 1, '2026-05-04 08:07:25'),
(7, 'Other / Unknown', 'Incidents that do not fit the above categories', 1, '2026-05-04 08:07:25');

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference` varchar(20) NOT NULL,
  `reporter_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `severity` enum('Low','Medium','High','Critical') NOT NULL,
  `status` enum('New','Acknowledged','In Progress','Resolved','Closed') NOT NULL DEFAULT 'New',
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `affected_system` varchar(200) DEFAULT NULL,
  `is_ongoing` tinyint(1) NOT NULL DEFAULT 0,
  `incident_time` datetime NOT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `sla_deadline` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `reference`, `reporter_id`, `category_id`, `severity`, `status`, `title`, `description`, `affected_system`, `is_ongoing`, `incident_time`, `assigned_to`, `sla_deadline`, `resolved_at`, `closed_at`, `created_at`, `updated_at`) VALUES
(1, 'INC-2026-0001', 1, 4, 'Critical', 'Resolved', 'Network Congestion', 'it occurs when my request to the server do not functional correctly as i expect', 'Student Portal', 1, '2026-05-04 10:25:00', NULL, '2026-05-04 12:27:40', '2026-05-04 10:46:33', NULL, '2026-05-04 10:27:40', '2026-05-04 10:46:33'),
(2, 'INC-2026-0002', 2, 5, 'High', 'Closed', 'qwertyu', 'qwertyudfkhfdjafbaofpoifhkfbfiefjfopwqJDBFOWHFEIRGFF', 'Utumish Portal', 1, '2026-05-04 10:44:00', NULL, '2026-05-04 18:44:44', NULL, '2026-05-04 18:23:39', '2026-05-04 10:44:44', '2026-05-04 18:23:39'),
(3, 'INC-2026-0003', 2, 7, 'Low', 'In Progress', 'ISMS LOADING', 'I M trting to access my isms portal but the functionality of loading is too much', 'ISMS', 0, '2026-05-04 18:46:00', NULL, '2026-05-07 18:48:15', NULL, NULL, '2026-05-04 18:48:15', '2026-05-05 05:03:22'),
(4, 'INC-2026-0004', 2, 4, 'High', 'In Progress', 'SYSTEM DOWNTIME', 'It occurs in my laptop right now, the system corrupt idont know what to do.', 'ISMS', 1, '2026-05-05 07:40:00', NULL, '2026-05-05 15:41:55', NULL, NULL, '2026-05-05 07:41:55', '2026-05-05 07:43:42'),
(5, 'INC-2026-0005', 4, 3, 'Low', 'Acknowledged', 'PASSWORD FAIL OF ISMS', 'QWERTYUIOKMNBFDSSFSGHDFGHUIJOKPOIUOUIYUTYDFYUHIJO;KLJ', 'ISMS', 1, '2026-05-05 18:05:00', 3, '2026-05-08 18:07:07', NULL, NULL, '2026-05-05 18:07:07', '2026-05-05 18:09:15');

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `id` int(10) UNSIGNED NOT NULL,
  `incident_id` int(10) UNSIGNED NOT NULL,
  `author_id` int(10) UNSIGNED NOT NULL,
  `body` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notes`
--

INSERT INTO `notes` (`id`, `incident_id`, `author_id`, `body`, `is_internal`, `created_at`) VALUES
(1, 3, 1, 'Your incident is processed and currently review by our IT support Team, Please be patient.', 0, '2026-05-04 18:54:54'),
(2, 1, 3, 'Your password and issue have been resolved. Please check your email for further instructions.', 0, '2026-05-05 04:57:35'),
(3, 3, 3, 'We are escalating this issue to the senior security team for further analysis.', 1, '2026-05-05 04:58:41'),
(4, 3, 3, 'Your account has been securely unlocked. Please try logging in again.', 0, '2026-05-05 04:59:02'),
(5, 4, 3, 'We have acknowledged your report and are currently investigating the issue.', 0, '2026-05-05 07:44:00'),
(6, 5, 1, 'We have acknowledged your report and are currently investigating the issue.', 0, '2026-05-05 18:08:59');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `incident_id` int(10) UNSIGNED DEFAULT NULL,
  `type` varchar(80) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `error_msg` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `incident_id`, `type`, `subject`, `sent_at`, `status`, `error_msg`) VALUES
(1, 1, 1, 'incident.submitted', 'New Incident Reported: INC-2026-0001 – Critical severity', NULL, 'pending', NULL),
(2, 2, 2, 'incident.submitted', 'New Incident Reported: INC-2026-0002 – High severity', NULL, 'pending', NULL),
(3, 2, 3, 'incident.submitted', 'New Incident Reported: INC-2026-0003 – Low severity', NULL, 'pending', NULL),
(4, 2, 4, 'incident.submitted', 'New Incident Reported: INC-2026-0004 – High severity', NULL, 'pending', NULL),
(5, 4, 5, 'incident.submitted', 'New Incident Reported: INC-2026-0005 – Low severity', NULL, 'pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`, `updated_at`) VALUES
('max_upload_mb', '5', 'Maximum file upload size in MB', '2026-05-04 10:42:07'),
('notify_email', 'support@iaa.ac.tz', 'Primary IT security notification email', '2026-05-04 10:42:06'),
('session_timeout', '300', 'Session timeout in seconds (30 min)', '2026-05-04 10:42:07'),
('sla_critical_hours', '2', 'SLA hours for Critical severity incidents', '2026-05-04 08:07:28'),
('sla_high_hours', '8', 'SLA hours for High severity incidents', '2026-05-04 08:07:28'),
('sla_low_hours', '72', 'SLA hours for Low severity incidents', '2026-05-04 08:07:28'),
('sla_medium_hours', '24', 'SLA hours for Medium severity incidents', '2026-05-04 08:07:28'),
('smtp_host', 'smtp.iaa.ac.tz', 'SMTP server hostname', '2026-05-04 10:42:05'),
('smtp_pass', '12345678', 'SMTP password (encrypted)', '2026-05-04 10:42:07'),
('smtp_port', '587', 'SMTP server port', '2026-05-04 08:07:28'),
('smtp_user', 'support@gmail.com', 'SMTP username', '2026-05-04 10:42:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('reporter','officer','admin') NOT NULL DEFAULT 'reporter',
  `department` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `department`, `phone`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$12$H.yDuuuqHlGKk0oOieTL5ucoOruXNb1.slDfTCkhhGhJZIYQasKHK', 'admin', 'Computer Science', '+255659263416', 1, '2026-05-04 10:24:52', '2026-05-04 10:35:03'),
(2, 'Erick Chuwa', 'chuwadominic52@gmail.com', '$2y$12$WIidvrZDSMOZ9DMOwRbEC.m.Z1At7EPNNheD3S1VNj74Pb9R7K3fu', 'reporter', 'Computer Science', NULL, 1, '2026-05-04 10:37:02', '2026-05-04 18:43:00'),
(3, 'Dominic Chuwa', 'support@gmail.com', '$2y$12$osu0Nj7cnH6PNR/PH8ubNeSe/JdKDE0p4Cjz.aXDcceXzIc28uT6y', 'officer', '', NULL, 1, '2026-05-04 10:37:45', '2026-05-04 10:37:45'),
(4, 'Hilda Kimaro', 'hildakimaro720@gmail.com', '$2y$12$DNou/u6.GDvS48txG.5bZuoqYiKGrA08ZbPCyPX39TXInjwcghkEu', 'reporter', 'BUSINESS MANAGEMENT', '255659263416', 1, '2026-05-05 18:04:48', '2026-05-05 18:04:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_incidents_status` (`status`),
  ADD KEY `idx_incidents_severity` (`severity`),
  ADD KEY `idx_incidents_reporter` (`reporter_id`),
  ADD KEY `idx_incidents_assigned` (`assigned_to`),
  ADD KEY `idx_incidents_created` (`created_at`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `idx_notes_incident` (`incident_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `incident_id` (`incident_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `incidents_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
