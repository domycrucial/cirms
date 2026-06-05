-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 20, 2026 at 09:46 AM
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
-- Database: `cirms_db`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attachments`
--

INSERT INTO `attachments` (`id`, `incident_id`, `filename`, `original`, `mime_type`, `size_bytes`, `uploaded_by`, `uploaded_at`) VALUES
(1, 4, '74238a3f3f1fc0676bcb4bb2.jpg', 'IMG-20260405-WA0070.jpg', 'image/jpeg', 26524, 4, '2026-05-13 16:06:44'),
(2, 5, '1aabd1d4ed12f013cf1609df.jpeg', 'dsa.jpeg', 'image/jpeg', 39005, 8, '2026-05-18 18:11:07');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@cirms.ac.tz\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:49:00'),
(2, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@cirms.ac.tz\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:49:15'),
(3, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@cirms.ac.tz\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:49:24'),
(4, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@cirms.ac.tz\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:49:51'),
(5, NULL, 'auth.register', 'user', 2, '{\"email\":\"chuwadominic52@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:50:29'),
(6, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:51:51'),
(7, 2, 'incident.submitted', 'incident', 1, '{\"ref\":\"INC-2026-0001\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-13 10:53:27'),
(8, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 13:36:54'),
(9, NULL, 'auth.register', 'user', 3, '{\"email\":\"aniversnormy4@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 13:43:52'),
(10, 3, 'auth.login', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 13:44:29'),
(11, 3, 'incident.submitted', 'incident', 2, '{\"ref\":\"INC-2026-0002\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 13:45:13'),
(12, 3, 'auth.logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 13:48:32'),
(13, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 13:48:56'),
(14, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 13:49:04'),
(15, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 13:49:11'),
(16, 2, 'incident.submitted', 'incident', 3, '{\"ref\":\"INC-2026-0003\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 13:50:30'),
(17, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 15:50:51'),
(18, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 15:55:44'),
(19, NULL, 'auth.register', 'user', 4, '{\"email\":\"hildakimaro720@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 15:57:02'),
(20, 4, 'auth.login', 'user', 4, '[]', '192.168.1.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-05-13 15:59:31'),
(21, 4, 'auth.login', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:02:03'),
(22, 4, 'auth.logout', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:02:36'),
(23, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:02:53'),
(24, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:02:56'),
(25, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@cirms.ac.tz\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:03:17'),
(26, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@cirms.ac.tz\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:03:25'),
(27, 4, 'incident.submitted', 'incident', 4, '{\"ref\":\"INC-2026-0004\"}', '192.168.1.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-05-13 16:06:44'),
(28, 4, 'auth.login', 'user', 4, '[]', '192.168.1.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-05-13 16:08:29'),
(29, 4, 'auth.login', 'user', 4, '[]', '192.168.1.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-05-13 16:09:33'),
(30, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:11:27'),
(31, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:13:24'),
(32, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@cirms.ac.tz\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:13:56'),
(33, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@cirms.ac.tz\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:14:09'),
(34, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@cirms.ac.tz\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:14:27'),
(35, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@cirms.ac.tz\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:14:42'),
(36, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"ashrafjafary64@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:16:34'),
(37, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"ashrafjafary64@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:17:03'),
(38, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"ashrafjafary64@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:17:29'),
(39, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"ashrafjafary64@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:21:10'),
(40, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"ashrafjafary64@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:21:16'),
(41, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"ashrafjafary64@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:21:38'),
(42, NULL, 'auth.register', 'user', 7, '{\"email\":\"profchuwa7@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:25:50'),
(43, 7, 'auth.login', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:26:25'),
(44, 7, 'auth.logout', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:27:40'),
(45, 7, 'auth.login', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:27:43'),
(46, 7, 'auth.logout', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:28:01'),
(47, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:28:06'),
(48, 2, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:28:18'),
(49, 2, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:28:27'),
(50, 2, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:28:51'),
(51, 2, 'incident.note_added', 'incident', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:29:24'),
(52, 2, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:29:37'),
(53, 2, 'incident.status_changed', 'incident', 4, '{\"from\":\"New\",\"to\":\"In Progress\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:29:47'),
(54, 2, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:30:07'),
(55, 2, 'analytics.export', NULL, NULL, '{\"from\":\"2026-01-01\",\"to\":\"2026-05-13\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:32:03'),
(56, 2, 'settings.updated', NULL, NULL, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:34:33'),
(57, 2, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:40:37'),
(58, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 16:41:24'),
(59, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-14 10:06:53'),
(60, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-16 11:23:32'),
(61, 2, 'analytics.export', NULL, NULL, '{\"from\":\"2026-01-01\",\"to\":\"2026-05-16\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-16 11:29:13'),
(62, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-16 11:36:30'),
(63, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-16 11:37:33'),
(64, 2, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-16 11:56:38'),
(65, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-16 13:52:53'),
(66, 2, 'settings.updated', NULL, NULL, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-16 14:22:54'),
(67, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-16 14:23:06'),
(68, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-16 14:23:21'),
(69, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:07:45'),
(70, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:07:54'),
(71, NULL, 'auth.register', 'user', 8, '{\"email\":\"davsonmollel2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:08:35'),
(72, 8, 'auth.login', 'user', 8, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:09:30'),
(73, 8, 'incident.submitted', 'incident', 5, '{\"ref\":\"INC-2026-0005\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:11:07'),
(74, 8, 'auth.logout', 'user', 8, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:12:01'),
(75, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:12:35'),
(76, 2, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:12:59'),
(77, 2, 'incident.status_changed', 'incident', 5, '{\"from\":\"New\",\"to\":\"Acknowledged\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:13:29'),
(78, 2, 'incident.status_changed', 'incident', 5, '{\"from\":\"Acknowledged\",\"to\":\"Acknowledged\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:14:23'),
(79, 2, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:14:25'),
(80, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-18 18:25:54'),
(81, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 21:28:57'),
(82, 2, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 21:52:45'),
(83, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 21:53:43'),
(84, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 21:53:58'),
(85, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 21:54:03'),
(86, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:15:18'),
(87, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:15:35'),
(88, 7, 'auth.login', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:15:42'),
(89, 7, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:15:47'),
(90, 7, 'auth.logout', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:16:34'),
(91, 4, 'auth.login', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:16:45'),
(92, 4, 'auth.logout', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:17:35'),
(93, 4, 'auth.login', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:20:31'),
(94, 4, 'auth.logout', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:20:47'),
(95, 4, 'auth.login', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:23:15'),
(96, 4, 'auth.logout', 'user', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:23:21'),
(97, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"hildakimaro720@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:23:38'),
(98, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"hildakimaro720@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:23:42'),
(99, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"hildakimaro720@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:23:46'),
(100, NULL, 'auth.login_locked', NULL, NULL, '{\"email\":\"hildakimaro720@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:23:50'),
(101, NULL, 'auth.login_locked', NULL, NULL, '{\"email\":\"hildakimaro720@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:24:04'),
(102, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:24:25'),
(103, 2, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:27:27'),
(104, 2, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:31:49'),
(105, 2, 'attachment.downloaded', 'attachment', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:32:12'),
(106, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:46:37'),
(107, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:47:57'),
(108, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:48:04'),
(109, 7, 'auth.login', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 22:48:31'),
(110, 7, 'auth.login', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 23:22:42'),
(111, 7, 'attachment.downloaded', 'attachment', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 23:23:13'),
(112, 7, 'auth.logout', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 23:23:23'),
(113, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 23:27:12'),
(114, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 23:39:52'),
(115, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 23:40:09'),
(116, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-19 08:39:37'),
(117, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-19 09:02:37'),
(118, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-19 09:03:02'),
(119, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-19 09:13:36'),
(120, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-19 09:14:17'),
(121, 2, 'auth.login', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-19 09:30:08'),
(122, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-19 09:37:27'),
(123, 7, 'auth.login', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-19 09:37:41'),
(124, 2, 'auth.logout', 'user', 2, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-19 10:01:03'),
(125, NULL, 'auth.login_failed', NULL, NULL, '{\"email\":\"admin@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-19 10:17:01'),
(126, 7, 'auth.login', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-20 07:59:34'),
(127, 7, 'auth.logout', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-20 07:59:52'),
(128, 7, 'auth.login', 'user', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-20 09:23:15');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'Phishing & Social Engineering', 'Deceptive emails, fake websites, or manipulation to steal credentials', 1, '2026-05-13 10:46:49'),
(2, 'Malware & Ransomware', 'Virus, worm, trojan, or ransomware infection on campus devices', 1, '2026-05-13 10:46:49'),
(3, 'Unauthorized Access', 'Account compromise or unauthorized login to systems', 1, '2026-05-13 10:46:49'),
(4, 'Network Intrusion / DoS', 'Suspicious network activity or denial-of-service attacks', 1, '2026-05-13 10:46:49'),
(5, 'Data Breach & Data Loss', 'Accidental or deliberate exposure of sensitive data', 1, '2026-05-13 10:46:49'),
(6, 'System Misuse & Policy Violation', 'Abuse of campus resources or violation of IT policy', 1, '2026-05-13 10:46:49'),
(7, 'Other / Unknown', 'Incidents that do not fit the above categories', 1, '2026-05-13 10:46:49');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `reference`, `reporter_id`, `category_id`, `severity`, `status`, `title`, `description`, `affected_system`, `is_ongoing`, `incident_time`, `assigned_to`, `sla_deadline`, `resolved_at`, `closed_at`, `created_at`, `updated_at`) VALUES
(1, 'INC-2026-0001', 2, 5, 'Low', 'New', 'dfghjkdsvnds', 'wdaefkdgvfuigfuavufhveufguefvwqoihrw9grfwqoidfhpfgeqgf', 'wqef', 1, '2026-05-13 10:52:00', NULL, '2026-05-16 10:53:25', NULL, NULL, '2026-05-13 10:53:25', '2026-05-13 10:53:25'),
(2, 'INC-2026-0002', 3, 4, 'Medium', 'New', 'DFCGVHJK', 'VDGFUIGASEUIFGEFG8E9TQBFPE89RTF89E2RFTVWEGF8LGFUQEV FL.TF', 'SGCYAGEFCOUAASDDGDFU', 1, '2026-05-13 13:44:00', NULL, '2026-05-14 13:45:13', NULL, NULL, '2026-05-13 13:45:13', '2026-05-13 13:45:13'),
(3, 'INC-2026-0003', 2, 5, 'High', 'New', 'Phishing Email', 'This is affecting my student portal where by i my result have been exposed tom the public', 'Student Portal', 1, '2026-05-13 13:49:00', NULL, '2026-05-13 21:50:29', NULL, NULL, '2026-05-13 13:50:29', '2026-05-13 13:50:29'),
(4, 'INC-2026-0004', 4, 1, 'Medium', 'In Progress', 'Phishing', 'I received an email that was a scam and opened it stf', 'Email system', 1, '2026-05-13 15:59:00', 7, '2026-05-14 16:06:44', NULL, NULL, '2026-05-13 16:06:44', '2026-05-13 16:29:47'),
(5, 'INC-2026-0005', 8, 4, 'Critical', 'Acknowledged', 'ISM OVERLOAD INTERNAL SERVER ERROR', 'I TRIERPSFBKJBDSFNIFDSKLFGNPOABGVOIDSAFGV;DABGVIADBGV/KDSANGV/OADBGFVRAKLGSFBVKSDPFVKFDSBVLKFBVKABFVSLBLBVJBSDFV.BSFEVSFDGVDSFBAVIOBAFVBSODKBVIDABSGV', 'ISMS', 1, '2026-05-18 18:09:00', 6, '2026-05-18 20:11:07', NULL, NULL, '2026-05-18 18:11:07', '2026-05-18 18:13:29');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notes`
--

INSERT INTO `notes` (`id`, `incident_id`, `author_id`, `body`, `is_internal`, `created_at`) VALUES
(1, 4, 2, 'Under Review', 0, '2026-05-13 16:29:24');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`, `updated_at`) VALUES
('max_upload_mb', '10', 'Maximum file upload size in MB', '2026-05-13 10:47:16'),
('notify_confirmation', '0', NULL, '2026-05-16 14:22:51'),
('notify_it_email', '', 'IT Security team notification email', '2026-05-13 10:47:16'),
('notify_note_added', '0', NULL, '2026-05-16 14:22:54'),
('notify_officer_assigned', '0', NULL, '2026-05-16 14:22:53'),
('notify_on_submission', '0', NULL, '2026-05-16 14:22:50'),
('notify_status_change', '0', NULL, '2026-05-16 14:22:52'),
('session_timeout', '300', 'Session timeout in seconds (30 min)', '2026-05-13 16:34:33'),
('sla_critical_hours', '2', 'SLA hours for Critical severity incidents', '2026-05-13 10:47:16'),
('sla_high_hours', '8', 'SLA hours for High severity incidents', '2026-05-13 10:47:16'),
('sla_low_hours', '72', 'SLA hours for Low severity incidents', '2026-05-13 10:47:16'),
('sla_medium_hours', '24', 'SLA hours for Medium severity incidents', '2026-05-13 10:47:16'),
('smtp_from', '', 'From email address', '2026-05-13 10:47:16'),
('smtp_from_name', 'CIRMS Notifications', 'From display name', '2026-05-13 10:47:16'),
('smtp_host', 'smtp.gmail.com', 'SMTP server hostname', '2026-05-13 10:47:16'),
('smtp_pass', '', 'SMTP App Password', '2026-05-13 10:47:16'),
('smtp_port', '587', 'SMTP server port', '2026-05-13 10:47:16'),
('smtp_user', '', 'SMTP username (Gmail address)', '2026-05-13 10:47:16');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `department`, `phone`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 'Admin', 'chuwadominic52@gmail.com', '$2y$12$hUE6u9.jSPodi3aYmA6ewOJAIn8Osv4rmKWPYV32G78UbquG8ULji', 'admin', 'ICT', '255659263416', 1, '2026-05-13 10:50:29', '2026-05-13 16:10:51'),
(3, 'George Newton', 'aniversnormy4@gmail.com', '$2y$12$vKeeGDNVGyB3j/JAgCWYse/9jLXJKlod26UfEKgFE3fBEMGZ4B3M2', 'reporter', 'ICT', '+255784693424', 1, '2026-05-13 13:43:52', '2026-05-13 13:43:52'),
(4, 'Ms Hilda Chuwa', 'hildakimaro720@gmail.com', '$2y$12$QuZ6NL7USCXJLA1iBApB4eEOfygJswfebry5d1TZnacnvO8RzwdI.', 'reporter', 'ICT', '+255762900416', 1, '2026-05-13 15:56:56', '2026-05-13 15:56:56'),
(6, 'Ashraf Jafary', 'ashrafjafary64@gmail.com', '12345678', 'officer', 'ICT', '+255659263416', 1, '2026-05-13 16:20:50', '2026-05-13 16:20:50'),
(7, 'Dickson Chuwa', 'profchuwa7@gmail.com', '$2y$12$kAn.bgFh7X/WSKJpHBO59ufcm3DnYpQG3xNmS7XcJvsEo/rmTO29y', 'officer', 'EDUCATION', '+255714940334', 1, '2026-05-13 16:25:50', '2026-05-13 16:27:20'),
(8, 'Davson Mollel', 'davsonmollel2@gmail.com', '$2y$12$XxFoUakIxXCtEDOvfTri1OFQMjLdpeTxyCh2iIl.Pkpn/j8HFk1Lm', 'reporter', 'IT', '255659263416', 1, '2026-05-18 18:08:35', '2026-05-18 18:08:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attachments_incident` (`incident_id`),
  ADD KEY `fk_attachments_uploader` (`uploaded_by`);

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
  ADD KEY `fk_incidents_category` (`category_id`),
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
  ADD KEY `fk_notes_author` (`author_id`),
  ADD KEY `idx_notes_incident` (`incident_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notif_user` (`user_id`),
  ADD KEY `fk_notif_incident` (`incident_id`),
  ADD KEY `idx_notif_status` (`status`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `fk_attachments_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attachments_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `fk_incidents_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_incidents_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `fk_incidents_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `fk_notes_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_notes_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
