-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 20, 2026 at 09:23 PM
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
-- Database: `mms`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'fa-tags',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `icon`, `sort_order`, `created_at`) VALUES
(1, 'Ramen & Noodles', 'fa-bowl-food', 1, '2026-07-17 05:00:27'),
(2, 'Snacks', 'fa-cookie-bite', 2, '2026-07-17 05:00:27'),
(3, 'Beverages', 'fa-mug-hot', 3, '2026-07-17 05:00:27'),
(4, 'Sauces & Condiments', 'fa-jar', 4, '2026-07-17 05:00:27'),
(5, 'Frozen & Instant', 'fa-snowflake', 5, '2026-07-17 05:00:27'),
(6, 'Kimchi & Sides', 'fa-pepper-hot', 6, '2026-07-17 05:00:27');

-- --------------------------------------------------------

--
-- Table structure for table `login_audit`
--

CREATE TABLE `login_audit` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `success` tinyint(1) NOT NULL,
  `reason` enum('success','invalid_email_format','user_not_found','invalid_password','account_locked') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_audit`
--

INSERT INTO `login_audit` (`id`, `email`, `success`, `reason`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 14:11:12'),
(2, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 14:11:30'),
(3, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 14:12:12'),
(4, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 16:35:26'),
(5, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 16:41:05'),
(6, 'cj3@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 17:10:29'),
(7, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 17:11:06'),
(8, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 13:37:27'),
(9, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 13:37:27'),
(10, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 14:07:17'),
(11, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 14:14:07'),
(12, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 14:14:07'),
(13, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 14:48:52'),
(14, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 18:35:28'),
(15, 'cj@gmail.com', 0, 'invalid_password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:59:15'),
(16, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:59:19'),
(17, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 20:01:50'),
(18, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 20:02:34'),
(19, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 20:04:24'),
(20, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 20:05:23'),
(21, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 07:51:11'),
(22, 'cj2@gmail.com', 0, 'invalid_password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 08:08:09'),
(23, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 08:08:13'),
(24, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 08:08:28'),
(25, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 08:09:06'),
(26, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 08:09:27'),
(27, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 17:31:46'),
(28, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 17:32:00'),
(29, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 17:33:16'),
(30, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 17:35:41'),
(31, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 17:44:42'),
(32, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 17:49:16'),
(33, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 17:49:34'),
(34, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 17:50:31'),
(35, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 18:17:17'),
(36, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 18:19:52'),
(37, 'cj@gmail.com', 0, 'invalid_password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 18:29:19'),
(38, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 18:29:21'),
(39, 'cj3@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 18:29:36'),
(40, 'cj2@gmail.com', 0, 'invalid_password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 18:29:57'),
(41, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 18:30:01'),
(42, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 18:58:42'),
(43, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 19:13:31'),
(44, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 19:14:13'),
(45, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 19:32:09'),
(46, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 19:34:09'),
(47, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:05:08'),
(48, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 08:12:10'),
(49, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 08:12:11'),
(50, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 08:45:18'),
(51, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 14:59:09'),
(52, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 15:59:23'),
(53, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 16:03:29'),
(54, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 16:11:42'),
(55, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 16:15:15'),
(56, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 16:50:25'),
(57, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 17:03:23'),
(58, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 17:03:45'),
(59, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 17:22:50'),
(60, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 17:37:22'),
(61, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:06:22'),
(62, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:28:17'),
(63, 'cj@gmail.com', 0, 'invalid_password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:40:28'),
(64, 'cj@gmail.com', 0, 'invalid_password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:40:34'),
(65, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:40:37'),
(66, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:47:44'),
(67, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:47:58'),
(68, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:48:12'),
(69, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:52:35'),
(70, 'cj2@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:52:50'),
(71, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:53:04'),
(72, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 18:58:32'),
(73, 'cj@gmail.com', 1, 'success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 19:13:30');

-- --------------------------------------------------------

--
-- Table structure for table `pending_orders`
--

CREATE TABLE `pending_orders` (
  `id` int(11) NOT NULL,
  `order_no` varchar(30) NOT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_contact` varchar(50) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `promotion_name` varchar(150) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `item_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `processed_by_email` varchar(255) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL COMMENT 'set once processed - links to the resulting sales row',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_order_items`
--

CREATE TABLE `pending_order_items` (
  `id` int(11) NOT NULL,
  `pending_order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `line_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost` decimal(10,2) DEFAULT NULL,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10,
  `expiry_date` date DEFAULT NULL COMMENT 'Optional. Set for perishable/dated stock so the engine can flag it as it nears expiry.',
  `is_superseded` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Manually flagged when a newer model/version/flavor has replaced this product and remaining stock should be cleared.',
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `price`, `cost`, `stock_qty`, `low_stock_threshold`, `expiry_date`, `is_superseded`, `image`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'RN-001', 'Shin Ramyun Spicy Cup', 65.00, 42.00, 48, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 05:00:27'),
(2, 1, 'RN-002', 'Buldak Hot Chicken Ramen', 72.00, 48.00, 8, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 05:00:27'),
(3, 1, 'RN-003', 'Jin Ramen Mild', 58.00, 38.00, 29, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 16:52:55'),
(4, 1, 'RN-004', 'Nissin Yakisoba Noodles', 60.00, 40.00, 22, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 05:00:27'),
(5, 2, 'SN-001', 'Honey Butter Chips', 85.00, 55.00, 30, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 05:00:27'),
(6, 2, 'SN-002', 'Pocky Chocolate Sticks', 55.00, 34.00, 5, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 16:52:55'),
(7, 2, 'SN-003', 'Melona Bar (Melon)', 40.00, 24.00, 66, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-19 16:20:59'),
(8, 2, 'SN-004', 'Choco Pie 12pk', 150.00, 98.00, 8, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 09:18:24'),
(9, 3, 'BV-001', 'Milkis Soda Can', 50.00, 30.00, 39, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 18:11:16'),
(10, 3, 'BV-002', 'Pocari Sweat 500ml', 55.00, 34.00, 8, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 16:52:55'),
(11, 3, 'BV-003', 'Barley Tea (Boricha)', 48.00, 28.00, 26, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 05:00:27'),
(12, 3, 'BV-004', 'Ramune Original 200ml', 65.00, 40.00, 19, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 18:11:16'),
(13, 4, 'SC-001', 'Gochujang Paste 500g', 210.00, 140.00, 15, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 05:00:27'),
(14, 4, 'SC-002', 'Soy Sauce (Sempio) 500ml', 120.00, 78.00, 24, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 05:00:27'),
(15, 4, 'SC-003', 'Sesame Oil 320ml', 180.00, 120.00, 5, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 05:00:27'),
(16, 4, 'SC-004', 'Gochugaru Chili Flakes 200g', 160.00, 105.00, 12, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 05:00:27'),
(17, 5, 'FZ-001', 'Frozen Mandu Dumplings 1kg', 280.00, 190.00, 0, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 08:56:20'),
(18, 5, 'FZ-002', 'Frozen Tteokbokki Rice Cake', 135.00, 88.00, 4, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 18:11:16'),
(19, 5, 'FZ-003', 'Frozen Gyoza 500g', 165.00, 110.00, 12, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 16:52:55'),
(20, 5, 'FZ-004', 'Instant Japchae Kit', 145.00, 96.00, 15, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 16:52:55'),
(21, 6, 'KM-001', 'Cabbage Kimchi 500g', 190.00, 120.00, 12, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 16:52:55'),
(22, 6, 'KM-002', 'Radish Kimchi (Kkakdugi) 400g', 175.00, 112.00, 4, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-17 05:00:27'),
(23, 6, 'KM-003', 'Pickled Radish (Danmuji) 300g', 95.00, 60.00, 20, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 16:52:55'),
(24, 6, 'KM-004', 'Seasoned Seaweed (Gim) 20pk', 80.00, 50.00, 27, 10, NULL, 0, NULL, 1, '2026-07-17 05:00:27', '2026-07-18 18:11:16');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `reason` enum('manual','near_expiration','slow_selling','replaced_model') NOT NULL DEFAULT 'manual',
  `auto_generated` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = owned/maintained by the promotion engine scan (products.php -> Run Analytics Scan). Manual edits to these are overwritten on the next scan.',
  `scope` enum('storewide','product') NOT NULL DEFAULT 'storewide',
  `discount_percent` decimal(5,2) NOT NULL COMMENT 'e.g. 10.00 = 10% off',
  `notes` varchar(255) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`id`, `name`, `reason`, `auto_generated`, `scope`, `discount_percent`, `notes`, `starts_at`, `ends_at`, `is_active`, `created_at`) VALUES
(2, 'Storewide', 'near_expiration', 0, 'storewide', 50.00, NULL, '2026-07-17 00:00:00', '2026-07-24 00:00:00', 0, '2026-07-17 06:26:23'),
(3, 'Bagsak Presyo', 'slow_selling', 0, 'storewide', 70.00, NULL, '2026-07-17 00:00:00', '2026-07-24 00:00:00', 1, '2026-07-17 08:27:09');

-- --------------------------------------------------------

--
-- Table structure for table `promotion_products`
--

CREATE TABLE `promotion_products` (
  `id` int(11) NOT NULL,
  `promotion_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(11) NOT NULL,
  `request_no` varchar(30) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `quantity_requested` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','forwarded','fulfilled','declined') NOT NULL DEFAULT 'pending',
  `requested_by_id` int(11) NOT NULL,
  `requested_by_email` varchar(150) NOT NULL,
  `forwarded_by_email` varchar(150) DEFAULT NULL,
  `forwarded_at` datetime DEFAULT NULL,
  `resolution_notes` varchar(255) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `supplier_notified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `reservation_no` varchar(30) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_contact` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `staff_email` varchar(255) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `promotion_id` int(11) DEFAULT NULL,
  `promotion_name` varchar(150) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `item_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('reserved','fulfilled','cancelled') NOT NULL DEFAULT 'reserved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `fulfilled_sale_id` int(11) DEFAULT NULL COMMENT 'sales.id this reservation was rung up as, once processed at POS',
  `cancelled_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `reservation_no`, `customer_name`, `customer_contact`, `notes`, `staff_id`, `staff_email`, `subtotal`, `discount`, `promotion_id`, `promotion_name`, `total`, `item_count`, `status`, `created_at`, `fulfilled_at`, `fulfilled_sale_id`, `cancelled_at`) VALUES
(1, 'RSV20260718-00001', 'cj', '213131', NULL, 1, 'cj@gmail.com', 370.00, 259.00, 3, 'Bagsak Presyo', 111.00, 5, 'reserved', '2026-07-18 18:11:16', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reservation_items`
--

CREATE TABLE `reservation_items` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `promotion_id` int(11) DEFAULT NULL,
  `promotion_name` varchar(150) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_items`
--

INSERT INTO `reservation_items` (`id`, `reservation_id`, `product_id`, `product_name`, `unit_price`, `promotion_id`, `promotion_name`, `quantity`, `discount_amount`, `line_total`) VALUES
(1, 1, 18, 'Frozen Tteokbokki Rice Cake', 135.00, 3, 'Bagsak Presyo', 1, 94.50, 40.50),
(2, 1, 9, 'Milkis Soda Can', 50.00, 3, 'Bagsak Presyo', 1, 35.00, 15.00),
(3, 1, 24, 'Seasoned Seaweed (Gim) 20pk', 80.00, 3, 'Bagsak Presyo', 1, 56.00, 24.00),
(4, 1, 12, 'Ramune Original 200ml', 65.00, 3, 'Bagsak Presyo', 1, 45.50, 19.50),
(5, 1, 7, 'Melona Bar (Melon)', 40.00, 3, 'Bagsak Presyo', 1, 28.00, 12.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `receipt_no` varchar(30) NOT NULL,
  `cashier_id` int(11) DEFAULT NULL,
  `cashier_email` varchar(255) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `promotion_id` int(11) DEFAULT NULL,
  `promotion_name` varchar(150) DEFAULT NULL COMMENT 'snapshotted so receipts stay accurate if the promo is later edited/deleted',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_received` decimal(10,2) NOT NULL DEFAULT 0.00,
  `change_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','gcash','card') NOT NULL DEFAULT 'cash',
  `item_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('completed','voided') NOT NULL DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `receipt_no`, `cashier_id`, `cashier_email`, `subtotal`, `discount`, `promotion_id`, `promotion_name`, `total`, `amount_received`, `change_due`, `payment_method`, `item_count`, `status`, `created_at`) VALUES
(1, 'RY-DEMO-00001', NULL, 'cj@gmail.com', 260.00, 0.00, NULL, NULL, 260.00, 300.00, 40.00, 'cash', 4, 'completed', '2026-07-15 05:00:27'),
(2, 'RY-DEMO-00002', NULL, 'cj@gmail.com', 143.00, 0.00, NULL, NULL, 143.00, 200.00, 57.00, 'cash', 3, 'completed', '2026-07-16 05:00:27'),
(3, 'RY-DEMO-00003', NULL, 'cj@gmail.com', 425.00, 0.00, NULL, NULL, 425.00, 500.00, 75.00, 'cash', 7, 'completed', '2026-07-17 05:00:27'),
(4, 'RY20260717-00004', 2, 'cj@gmail.com', 473.00, 47.30, NULL, 'Storewide Sale', 425.70, 500.00, 74.30, 'cash', 3, 'completed', '2026-07-17 05:03:43'),
(5, 'RY20260717-00005', 2, 'cj@gmail.com', 174.00, 0.00, NULL, NULL, 174.00, 233.00, 59.00, 'cash', 3, 'completed', '2026-07-17 05:04:11'),
(6, 'RY20260717-00006', 2, 'cj@gmail.com', 1255.00, 627.50, 2, 'Storewide', 627.50, 666.00, 38.50, 'cash', 5, 'completed', '2026-07-17 06:26:55'),
(7, 'RY20260717-00007', 2, 'cj@gmail.com', 150.00, 0.00, NULL, NULL, 150.00, 600.00, 450.00, 'cash', 1, 'completed', '2026-07-17 08:30:04'),
(8, 'RY20260717-00008', 2, 'cj@gmail.com', 543.00, 380.10, 3, 'Bagsak Presyo', 162.90, 555.00, 392.10, 'cash', 4, 'completed', '2026-07-17 08:49:25'),
(9, 'RY20260717-00009', 2, 'cj@gmail.com', 1120.00, 784.00, 3, 'Bagsak Presyo', 336.00, 400.00, 64.00, 'cash', 4, 'completed', '2026-07-17 08:56:20'),
(10, 'RY20260717-00010', 2, 'cj@gmail.com', 750.00, 525.00, 3, 'Bagsak Presyo', 225.00, 500.00, 275.00, 'cash', 5, 'completed', '2026-07-17 09:18:24'),
(11, 'RY20260717-00011', 2, 'cj@gmail.com', 825.00, 577.50, 3, 'Bagsak Presyo', 247.50, 300.00, 52.50, 'cash', 5, 'completed', '2026-07-17 09:19:05');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `promotion_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `promotion_name` varchar(150) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `promotion_id`, `product_name`, `unit_price`, `promotion_name`, `quantity`, `discount_amount`, `line_total`) VALUES
(1, 1, 1, NULL, 'Shin Ramyun Spicy Cup', 65.00, NULL, 2, 0.00, 130.00),
(2, 1, 9, NULL, 'Milkis Soda Can', 50.00, NULL, 1, 0.00, 50.00),
(3, 1, 24, NULL, 'Seasoned Seaweed (Gim) 20pk', 80.00, NULL, 1, 0.00, 80.00),
(4, 2, 5, NULL, 'Honey Butter Chips', 85.00, NULL, 1, 0.00, 85.00),
(5, 2, 9, NULL, 'Milkis Soda Can', 50.00, NULL, 1, 0.00, 50.00),
(6, 2, 23, NULL, 'Pickled Radish (Danmuji) 300g', 8.00, NULL, 1, 0.00, 8.00),
(7, 3, 1, NULL, 'Shin Ramyun Spicy Cup', 65.00, NULL, 4, 0.00, 260.00),
(8, 3, 9, NULL, 'Milkis Soda Can', 50.00, NULL, 1, 0.00, 50.00),
(9, 3, 6, NULL, 'Pocky Chocolate Sticks', 55.00, NULL, 1, 0.00, 55.00),
(10, 3, 4, NULL, 'Nissin Yakisoba Noodles', 60.00, NULL, 1, 0.00, 60.00),
(11, 4, 18, NULL, 'Frozen Tteokbokki Rice Cake', 135.00, NULL, 1, 0.00, 135.00),
(12, 4, 17, NULL, 'Frozen Mandu Dumplings 1kg', 280.00, NULL, 1, 0.00, 280.00),
(13, 4, 3, NULL, 'Jin Ramen Mild', 58.00, NULL, 1, 0.00, 58.00),
(14, 5, 3, NULL, 'Jin Ramen Mild', 58.00, NULL, 3, 0.00, 174.00),
(15, 6, 17, 2, 'Frozen Mandu Dumplings 1kg', 280.00, 'Storewide', 4, 560.00, 560.00),
(16, 6, 18, 2, 'Frozen Tteokbokki Rice Cake', 135.00, 'Storewide', 1, 67.50, 67.50),
(17, 7, 8, NULL, 'Choco Pie 12pk', 150.00, NULL, 1, 0.00, 150.00),
(18, 8, 19, 3, 'Frozen Gyoza 500g', 165.00, 'Bagsak Presyo', 1, 115.50, 49.50),
(19, 8, 17, 3, 'Frozen Mandu Dumplings 1kg', 280.00, 'Bagsak Presyo', 1, 196.00, 84.00),
(20, 8, 7, 3, 'Melona Bar (Melon)', 40.00, 'Bagsak Presyo', 1, 28.00, 12.00),
(21, 8, 3, 3, 'Jin Ramen Mild', 58.00, 'Bagsak Presyo', 1, 40.60, 17.40),
(22, 9, 17, 3, 'Frozen Mandu Dumplings 1kg', 280.00, 'Bagsak Presyo', 4, 784.00, 336.00),
(23, 10, 8, 3, 'Choco Pie 12pk', 150.00, 'Bagsak Presyo', 5, 525.00, 225.00),
(24, 11, 19, 3, 'Frozen Gyoza 500g', 165.00, 'Bagsak Presyo', 5, 577.50, 247.50);

-- --------------------------------------------------------

--
-- Table structure for table `staff_concerns`
--

CREATE TABLE `staff_concerns` (
  `id` int(11) NOT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `submitted_by_email` varchar(255) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','in_review','resolved') NOT NULL DEFAULT 'open',
  `resolved_by` int(11) DEFAULT NULL,
  `resolution_notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_concerns`
--

INSERT INTO `staff_concerns` (`id`, `submitted_by`, `submitted_by_email`, `subject`, `message`, `status`, `resolved_by`, `resolution_notes`, `created_at`, `resolved_at`) VALUES
(1, 2, 'cj2@gmail.com', 'Failed to retrieve pause status', 'naamo', 'resolved', 1, 'ok na buang', '2026-07-18 19:13:29', '2026-07-20 00:54:13'),
(2, 2, 'cj2@gmail.com', 'Other — nothing', 'hello, sir!', 'in_review', 1, 'Thanks for bringing this up — we are reviewing it and will follow up with you soon.', '2026-07-19 18:58:31', '2026-07-20 03:31:48');

-- --------------------------------------------------------

--
-- Table structure for table `staff_warnings`
--

CREATE TABLE `staff_warnings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_by` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_warnings`
--

INSERT INTO `staff_warnings` (`id`, `user_id`, `message`, `sent_by`, `read_at`, `created_at`) VALUES
(1, 2, 'you miss a shift', 1, '2026-07-18 22:13:12', '2026-07-18 14:06:44'),
(2, 2, 'don\'t let this happen again', 1, NULL, '2026-07-20 18:54:29');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `change_qty` int(11) NOT NULL COMMENT 'negative for sales/deductions, positive for restocks',
  `reason` enum('restock','reservation','purchase_request') NOT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'e.g. sale_id when reason = sale/void',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `change_qty`, `reason`, `reference_id`, `created_at`) VALUES
(1, 18, -1, '', 4, '2026-07-17 05:03:43'),
(2, 17, -1, '', 4, '2026-07-17 05:03:43'),
(3, 3, -1, '', 4, '2026-07-17 05:03:43'),
(4, 3, -3, '', 5, '2026-07-17 05:04:11'),
(5, 17, -4, '', 6, '2026-07-17 06:26:55'),
(6, 18, -1, '', 6, '2026-07-17 06:26:55'),
(7, 8, -1, '', 7, '2026-07-17 08:30:04'),
(8, 19, -1, '', 8, '2026-07-17 08:49:25'),
(9, 17, -1, '', 8, '2026-07-17 08:49:25'),
(10, 7, -1, '', 8, '2026-07-17 08:49:25'),
(11, 3, -1, '', 8, '2026-07-17 08:49:25'),
(12, 17, -4, '', 9, '2026-07-17 08:56:20'),
(13, 8, -5, '', 10, '2026-07-17 09:18:24'),
(14, 19, -5, '', 11, '2026-07-17 09:19:05'),
(15, 19, -1, 'reservation', 1, '2026-07-18 16:52:55'),
(16, 3, -1, 'reservation', 1, '2026-07-18 16:52:55'),
(17, 20, -1, 'reservation', 1, '2026-07-18 16:52:55'),
(18, 6, -1, 'reservation', 1, '2026-07-18 16:52:55'),
(19, 10, -1, 'reservation', 1, '2026-07-18 16:52:55'),
(20, 21, -1, 'reservation', 1, '2026-07-18 16:52:55'),
(21, 23, -1, 'reservation', 1, '2026-07-18 16:52:55'),
(22, 18, -1, 'reservation', 1, '2026-07-18 18:11:16'),
(23, 9, -1, 'reservation', 1, '2026-07-18 18:11:16'),
(24, 24, -1, 'reservation', 1, '2026-07-18 18:11:16'),
(25, 12, -1, 'reservation', 1, '2026-07-18 18:11:16'),
(26, 7, -1, 'reservation', 1, '2026-07-18 18:11:16'),
(27, 7, 50, 'purchase_request', 1, '2026-07-19 16:20:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','manager','cashier') NOT NULL DEFAULT 'cashier',
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL COMMENT 'Heartbeat — refreshed on every authenticated page load, used to tell if someone is really active right now vs. just logged in earlier today',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `full_name`, `birth_date`, `phone`, `address`, `shift_start`, `shift_end`, `password_hash`, `role`, `failed_attempts`, `locked_until`, `last_seen`, `created_at`) VALUES
(1, 'cj@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2b$10$hk.8sWnqskzHmy0GQiuhHuXIeIOk2MSBaMi.2Py4itLd16VJi/b0i', 'admin', 0, NULL, '2026-07-21 03:13:31', '2026-07-17 14:10:59'),
(2, 'cj2@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2b$10$iGmx8ypqPQOYsal/1Auqae4MM1v8U318shgdSGhja1q8YFnqu5jkK', 'cashier', 0, NULL, '2026-07-21 02:52:54', '2026-07-17 14:10:59'),
(3, 'cj3@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$xFL3ukEve6QyPh2A3UaNBuQv/tFpok0E.U0Y0YA6ZkcKEjYcPfBq.', 'cashier', 0, NULL, NULL, '2026-07-17 17:09:14'),
(8, 'christianmamaril003@gmail.com', 'juan', '2000-03-22', '421123213', 'dito lang', '07:00:00', '19:00:00', '$2y$10$HrdnZ8H1G/1SJkPPvjeX.OzCniNbYoaStejZHOLQAiJStePI5YQbi', 'cashier', 0, NULL, NULL, '2026-07-20 11:51:01'),
(10, 'christianmamaril006@gmail.com', 'juan', '1999-03-17', '3213123', 'dito lang', '20:54:00', '21:54:00', '$2y$10$FdgUY02kqz2gsll6ubakHe.W4uSnKdMcYPOBa4J/eBpo7vkI9g5R.', 'cashier', 0, NULL, NULL, '2026-07-20 11:55:15'),
(11, 'rivasjashleyt@gmail.com', 'jaja', '2005-07-29', '09075815484', 'dito lang', '15:00:00', '21:00:00', '$2y$10$o6utqNH52Yl1c75V6tI9neL6LuFiJMG39Tx0IW..8jKvUkpbYn0X6', 'cashier', 0, NULL, NULL, '2026-07-20 11:57:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_name` (`name`);

--
-- Indexes for table `login_audit`
--
ALTER TABLE `login_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_created` (`email`,`created_at`),
  ADD KEY `idx_success_created` (`success`,`created_at`);

--
-- Indexes for table `pending_orders`
--
ALTER TABLE `pending_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pending_orders_order_no` (`order_no`),
  ADD KEY `idx_pending_orders_status` (`status`);

--
-- Indexes for table `pending_order_items`
--
ALTER TABLE `pending_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_poi_order` (`pending_order_id`),
  ADD KEY `idx_poi_product` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_sku` (`sku`),
  ADD KEY `idx_products_category` (`category_id`),
  ADD KEY `idx_products_stock` (`stock_qty`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_promotions_active_window` (`is_active`,`starts_at`,`ends_at`);

--
-- Indexes for table `promotion_products`
--
ALTER TABLE `promotion_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_promo_product` (`promotion_id`,`product_id`),
  ADD KEY `idx_promo_products_product` (`product_id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pr_status` (`status`),
  ADD KEY `idx_pr_requested_by` (`requested_by_id`),
  ADD KEY `idx_pr_product` (`product_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reservations_no` (`reservation_no`),
  ADD KEY `idx_reservations_status` (`status`),
  ADD KEY `idx_reservations_created` (`created_at`),
  ADD KEY `fk_reservations_promotion` (`promotion_id`),
  ADD KEY `fk_reservations_sale` (`fulfilled_sale_id`);

--
-- Indexes for table `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservation_items_reservation` (`reservation_id`),
  ADD KEY `idx_reservation_items_product` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sales_receipt_no` (`receipt_no`),
  ADD KEY `idx_sales_created` (`created_at`),
  ADD KEY `idx_sales_cashier` (`cashier_id`),
  ADD KEY `idx_sales_promotion` (`promotion_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale_items_sale` (`sale_id`),
  ADD KEY `idx_sale_items_product` (`product_id`),
  ADD KEY `idx_sale_items_promotion` (`promotion_id`);

--
-- Indexes for table `staff_concerns`
--
ALTER TABLE `staff_concerns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_concerns_status_created` (`status`,`created_at`),
  ADD KEY `idx_concerns_submitted_by` (`submitted_by`),
  ADD KEY `fk_concerns_resolved_by` (`resolved_by`);

--
-- Indexes for table `staff_warnings`
--
ALTER TABLE `staff_warnings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `fk_warnings_sent_by` (`sent_by`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_movements_product` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_last_seen` (`last_seen`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `login_audit`
--
ALTER TABLE `login_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `pending_orders`
--
ALTER TABLE `pending_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pending_order_items`
--
ALTER TABLE `pending_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `promotion_products`
--
ALTER TABLE `promotion_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reservation_items`
--
ALTER TABLE `reservation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `staff_concerns`
--
ALTER TABLE `staff_concerns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_warnings`
--
ALTER TABLE `staff_warnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `pending_order_items`
--
ALTER TABLE `pending_order_items`
  ADD CONSTRAINT `fk_poi_order` FOREIGN KEY (`pending_order_id`) REFERENCES `pending_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_poi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `promotion_products`
--
ALTER TABLE `promotion_products`
  ADD CONSTRAINT `fk_promo_products_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_promo_products_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservations_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reservations_sale` FOREIGN KEY (`fulfilled_sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD CONSTRAINT `fk_reservation_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reservation_items_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sale_items_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_concerns`
--
ALTER TABLE `staff_concerns`
  ADD CONSTRAINT `fk_concerns_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_concerns_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_warnings`
--
ALTER TABLE `staff_warnings`
  ADD CONSTRAINT `fk_warnings_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_warnings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
