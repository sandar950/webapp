-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 20, 2026 at 07:18 PM
-- Server version: 10.4.34-MariaDB
-- PHP Version: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `thai_2d3d_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_activity_logs`
--

INSERT INTO `admin_activity_logs` (`id`, `admin_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'DELETE_PAYMENT_ACCOUNT', 'Deleted payment account ID: 1', '10.131.187.167', '2026-06-19 05:16:32'),
(2, 1, 'DELETE_PAYMENT_ACCOUNT', 'Deleted payment account ID: 2', '10.131.187.167', '2026-06-19 05:16:36'),
(3, 1, 'DELETE_USER', 'Deleted User ID: 2 (Sample User)', '10.131.187.167', '2026-06-19 06:37:22'),
(4, 1, 'DELETE_USER', 'Deleted User ID: 2 (Unknown)', '10.131.187.167', '2026-06-19 06:37:25'),
(5, 1, 'DELETE_USER', 'Deleted User ID: 2 (Unknown)', '10.131.187.167', '2026-06-19 06:39:26'),
(6, 1, 'DELETE_USER', 'Deleted User ID: 2 (Unknown)', '10.131.187.167', '2026-06-19 06:40:20'),
(7, 1, 'DELETE_USER', 'Deleted User ID: 2 (Unknown)', '10.131.187.167', '2026-06-19 06:40:24'),
(8, 1, 'UPDATE_SETTINGS', 'System settings were updated.', '::1', '2026-06-19 08:02:31'),
(9, 1, 'DB_SCHEMA_FIX', 'Executed SQL fix from Health Check page.', '::1', '2026-06-20 09:09:40'),
(10, 1, 'ADD_PAYMENT_ACCOUNT', 'Added payment account: KPay - 09*****8214', '127.0.0.1', '2026-06-20 09:53:34'),
(11, 1, 'ADD_PAYMENT_ACCOUNT', 'Added payment account: WavePay - 09959375147', '127.0.0.1', '2026-06-20 09:58:49'),
(12, 1, 'UPDATE_BANNERS', 'Home banners were updated.', '127.0.0.1', '2026-06-20 13:04:59'),
(13, 1, 'UPDATE_BANNERS', 'Home banners were updated.', '127.0.0.1', '2026-06-20 13:05:40'),
(14, 1, 'APPROVE_DEPOSIT', 'Approved deposit ID: 1 for User ID: 1, Amount: 50,000', '127.0.0.1', '2026-06-20 13:20:32');

-- --------------------------------------------------------

--
-- Table structure for table `bets`
--

CREATE TABLE `bets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bet_number` varchar(10) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `odds` int(11) DEFAULT NULL,
  `bet_section` enum('morning','evening','3d') NOT NULL,
  `target_date` date NOT NULL,
  `status` enum('pending','win','lose') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bets`
--

INSERT INTO `bets` (`id`, `user_id`, `bet_number`, `amount`, `discount_amount`, `odds`, `bet_section`, `target_date`, `status`, `created_at`) VALUES
(3, 1, '008', 100.00, 0.00, 500, '3d', '2026-07-01', 'pending', '2026-06-20 17:13:13');

-- --------------------------------------------------------

--
-- Table structure for table `betting_sessions`
--

CREATE TABLE `betting_sessions` (
  `id` int(11) NOT NULL,
  `game_type` enum('2d','3d') NOT NULL,
  `section` varchar(50) NOT NULL,
  `target_date` date NOT NULL,
  `open_time` datetime NOT NULL,
  `close_time` datetime NOT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `admin_notified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `betting_sessions`
--

INSERT INTO `betting_sessions` (`id`, `game_type`, `section`, `target_date`, `open_time`, `close_time`, `status`, `admin_notified`, `created_at`) VALUES
(1, '2d', 'morning', '2026-06-22', '2026-06-22 01:00:00', '2026-06-22 11:45:00', 'active', 0, '2026-06-20 10:02:40'),
(2, '2d', 'evening', '2026-06-22', '2026-06-22 13:00:00', '2026-06-22 15:45:00', 'active', 0, '2026-06-20 10:03:32'),
(4, '3d', '3d', '2026-07-01', '2026-06-20 16:45:00', '2026-07-01 14:45:00', 'active', 0, '2026-06-20 10:06:51');

-- --------------------------------------------------------

--
-- Table structure for table `bonus_history`
--

CREATE TABLE `bonus_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bonus_type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referred_user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_account_id` int(11) DEFAULT NULL,
  `transaction_id` varchar(50) NOT NULL,
  `slip_image_url` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reject_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`id`, `user_id`, `amount`, `payment_method`, `payment_account_id`, `transaction_id`, `slip_image_url`, `status`, `reject_reason`, `created_at`) VALUES
(1, 1, 50000.00, 'KPay', NULL, '790123', 'uploads/slips/slip_1_1781961573_6a36936555976.jpg', 'approved', NULL, '2026-06-20 13:19:35');

-- --------------------------------------------------------

--
-- Table structure for table `guides`
--

CREATE TABLE `guides` (
  `id` int(11) NOT NULL,
  `guide_key` varchar(50) NOT NULL,
  `icon_class` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guides`
--

INSERT INTO `guides` (`id`, `guide_key`, `icon_class`, `sort_order`, `is_active`) VALUES
(1, 'account', 'fas fa-user-cog text-primary', 10, 1),
(2, 'pin', 'fas fa-shield-alt text-gray-600', 20, 1),
(3, 'telegram', 'fab fa-telegram text-blue-500', 30, 1),
(4, 'deposit_withdraw', 'fas fa-wallet text-green-600', 40, 1),
(5, 'transfer_loan', 'fas fa-exchange-alt text-purple-600', 50, 1),
(6, 'betting', 'fas fa-dice text-blue-600', 60, 1),
(7, 'history', 'fas fa-history text-purple-600', 70, 1),
(8, 'referral', 'fas fa-share-alt text-orange-600', 80, 1),
(9, 'vip', 'fas fa-crown text-yellow-500', 90, 1);

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected','repaid') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `page_seo`
--

CREATE TABLE `page_seo` (
  `id` int(11) NOT NULL,
  `page_name` varchar(100) NOT NULL,
  `seo_title_mm` varchar(255) DEFAULT NULL,
  `seo_title_en` varchar(255) DEFAULT NULL,
  `seo_description_mm` text DEFAULT NULL,
  `seo_description_en` text DEFAULT NULL,
  `seo_keywords_mm` text DEFAULT NULL,
  `seo_keywords_en` text DEFAULT NULL,
  `seo_image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_accounts`
--

CREATE TABLE `payment_accounts` (
  `id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `qr_image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_accounts`
--

INSERT INTO `payment_accounts` (`id`, `payment_method`, `account_name`, `account_number`, `logo_url`, `qr_image_url`, `is_active`, `sort_order`, `created_at`) VALUES
(3, 'KPay', 'Kyaw Htun', '09*****8214', 'https://file.thai2d3dgame.com/files/payment/kbzpay.png', 'https://file.thai2d3dgame.com/files/payment/ec952d2a-164d-48a4-aa61-d09f3ad1ea39.png', 1, 0, '2026-06-20 09:53:34'),
(4, 'WavePay', 'Khat Khat Wai', '09959375147', 'https://file.thai2d3dgame.com/files/payment/wavepay_new.png', NULL, 1, 0, '2026-06-20 09:58:49');

-- --------------------------------------------------------

--
-- Table structure for table `pin_attempts`
--

CREATE TABLE `pin_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pre_approved_transactions`
--

CREATE TABLE `pre_approved_transactions` (
  `id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_id` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','used') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `result_history`
--

CREATE TABLE `result_history` (
  `id` int(11) NOT NULL,
  `result_number` varchar(10) NOT NULL,
  `type` enum('2D','3D') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'max_limit_per_number', '20000', '2026-06-18 16:06:26'),
(2, 'max_limit_per_3d_number', '10000', '2026-06-18 16:06:26'),
(3, 'home_banner_url', 'https://file.thai2d3dgame.com/files/ads/2de0f363-e8b5-4834-a0f1-1782d3f20d4a.png', '2026-06-20 13:04:59'),
(4, 'home_banner_url_2', 'https://file.thai2d3dgame.com/files/ads/8c2a2215-435d-4a2c-ba5f-9c25cdb2b497.jpeg', '2026-06-20 13:05:40'),
(5, 'home_banner_url_3', '', '2026-06-18 16:06:26'),
(6, 'daily_bonus_amount', '0', '2026-06-19 08:02:31'),
(7, 'daily_bonus_standard', '500', '2026-06-18 16:06:26'),
(8, 'daily_bonus_bronze', '1000', '2026-06-18 16:06:26'),
(9, 'daily_bonus_silver', '2000', '2026-06-18 16:06:26'),
(10, 'daily_bonus_gold', '', '2026-06-19 08:02:31'),
(11, 'daily_bonus_diamond', '10000', '2026-06-18 16:06:26'),
(12, 'bet_discount_percent', '0', '2026-06-18 16:06:26'),
(13, 'registration_fee', '0', '2026-06-18 16:06:26'),
(14, 'blocked_2d_numbers', '', '2026-06-18 16:06:26'),
(15, 'blocked_2d_morning', '', '2026-06-18 16:06:26'),
(16, 'blocked_2d_evening', '', '2026-06-18 16:06:26'),
(17, 'blocked_3d_numbers', '', '2026-06-18 16:06:26'),
(18, 'kbz_pay_account', '', '2026-06-19 08:02:31'),
(19, 'kbz_pay_name', '', '2026-06-19 08:02:31'),
(20, 'kbz_pay_qr_url', '', '2026-06-18 16:06:26'),
(21, 'wave_pay_account', '', '2026-06-19 08:02:31'),
(22, 'wave_pay_name', '', '2026-06-19 08:02:31'),
(23, 'wave_pay_qr_url', '', '2026-06-18 16:06:26'),
(24, 'referral_commission_percent', '0', '2026-06-19 08:02:31'),
(25, 'mlm_level_1_percent', '3', '2026-06-18 16:06:26'),
(26, 'mlm_level_2_percent', '1.5', '2026-06-18 16:06:26'),
(27, 'mlm_level_3_percent', '0.5', '2026-06-18 16:06:26'),
(28, 'vip_bronze_threshold', '100000', '2026-06-18 16:06:26'),
(29, 'vip_silver_threshold', '500000', '2026-06-18 16:06:26'),
(30, 'vip_gold_threshold', '2000000', '2026-06-18 16:06:26'),
(31, 'vip_diamond_threshold', '5000000', '2026-06-18 16:06:26'),
(32, 'cashback_standard_percent', '0', '2026-06-18 16:06:26'),
(33, 'cashback_bronze_percent', '3', '2026-06-18 16:06:26'),
(34, 'cashback_silver_percent', '5', '2026-06-18 16:06:26'),
(35, 'cashback_gold_percent', '8', '2026-06-18 16:06:26'),
(36, 'cashback_diamond_percent', '10', '2026-06-18 16:06:26'),
(37, 'min_deposit', '1000', '2026-06-18 16:06:26'),
(38, 'max_deposit', '1000000', '2026-06-18 16:06:26'),
(39, 'min_withdraw', '1000', '2026-06-18 16:06:26'),
(40, 'max_withdraw', '1000000', '2026-06-18 16:06:26'),
(41, 'withdrawal_fee_percent', '0', '2026-06-18 16:06:26'),
(42, 'telegram_bot_token', '', '2026-06-18 16:06:26'),
(43, 'telegram_channel_id', '', '2026-06-18 16:06:26'),
(44, 'bet_cancel_time_limit', '10', '2026-06-18 16:06:26'),
(45, 'announcement_text', '', '2026-06-18 16:06:26'),
(46, 'announcement_image_url', '', '2026-06-18 16:06:26'),
(47, 'announcement_is_active', '0', '2026-06-18 16:06:26'),
(48, 'maintenance_mode', '0', '2026-06-18 16:06:26'),
(49, 'maintenance_message', 'ဆာဗာပြုပြင်ထိန်းသိမ်းမှုများ ပြုလုပ်နေပါသည်။ ခေတ္တစောင့်ဆိုင်းပေးပါ။', '2026-06-18 16:06:26'),
(50, 'cs_messenger_link', '', '2026-06-18 16:06:26'),
(51, 'cs_telegram_link', '', '2026-06-18 16:06:26'),
(52, 'cs_viber_link', '', '2026-06-18 16:06:26'),
(53, 'live_2d_api_url', '', '2026-06-18 16:06:26'),
(54, 'live_3d_api_url', '', '2026-06-18 16:06:26'),
(55, 'enable_dynamic_odds', '1', '2026-06-18 16:06:26'),
(56, 'dynamic_odds_threshold', '80', '2026-06-18 16:06:26'),
(57, 'telegram_alert_chat_id', '', '2026-06-19 04:26:41'),
(97, 'session_timeout_minutes', '60', '2026-06-19 08:02:31'),
(170, 'seo_title_mm', 'Thai 2D3D - အကောင်းဆုံး 2D 3D App', '2026-06-20 13:39:46'),
(171, 'seo_title_en', 'Thai 2D3D - Best 2D 3D App', '2026-06-20 13:39:46'),
(172, 'seo_description_mm', 'Thai 2D3D တွင် ယုံကြည်စိတ်ချစွာဖြင့် 2D 3D ထိုးနိုင်ပါသည်။', '2026-06-20 13:39:46'),
(173, 'seo_description_en', 'Bet 2D 3D securely with Thai 2D3D. Fast deposit and withdrawal.', '2026-06-20 13:39:46'),
(174, 'seo_keywords_mm', '2d, 3d, myanmar 2d', '2026-06-20 13:39:46'),
(175, 'seo_keywords_en', '2d, 3d, myanmar 2d, thai 2d', '2026-06-20 13:39:46'),
(176, 'seo_image_url', 'https://file.thai2d3dgame.com/files/notificationImages/all/allNotiNew.png', '2026-06-20 14:05:46');

-- --------------------------------------------------------

--
-- Table structure for table `sub_admin_permissions`
--

CREATE TABLE `sub_admin_permissions` (
  `user_id` int(11) NOT NULL,
  `can_declare_result` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_transactions` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_users` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_reports` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_blocked_numbers` tinyint(1) NOT NULL DEFAULT 0,
  `can_send_notifications` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `attachment_url` varchar(255) DEFAULT NULL,
  `admin_reply` text DEFAULT NULL,
  `admin_attachment_url` varchar(255) DEFAULT NULL,
  `status` enum('pending','replied') DEFAULT 'pending',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_notifications`
--

CREATE TABLE `system_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_important` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_notifications`
--

INSERT INTO `system_notifications` (`id`, `user_id`, `message`, `image_url`, `is_important`, `sort_order`, `is_read`, `created_at`) VALUES
(3, 1, '✅ Your deposit request (Amount - 50,000 Ks) has been approved.', NULL, 0, 0, 1, '2026-06-20 13:20:32');

-- --------------------------------------------------------

--
-- Table structure for table `transfers`
--

CREATE TABLE `transfers` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `transaction_pin` varchar(255) DEFAULT NULL,
  `google2fa_secret` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `vip_level` varchar(20) DEFAULT 'Standard',
  `lifetime_bet` decimal(15,2) DEFAULT 0.00,
  `agent_commission_percent` decimal(5,2) DEFAULT 0.00,
  `agent_share_percent` decimal(5,2) DEFAULT 0.00,
  `kbz_pay_number` varchar(50) DEFAULT NULL,
  `kbz_pay_name` varchar(100) DEFAULT NULL,
  `wave_pay_number` varchar(50) DEFAULT NULL,
  `wave_pay_name` varchar(100) DEFAULT NULL,
  `payment_info_json` text DEFAULT NULL,
  `role` enum('user','sub_admin','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_banned` tinyint(1) DEFAULT 0,
  `last_bonus_date` date DEFAULT NULL,
  `last_active` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login_ip` varchar(45) DEFAULT NULL,
  `telegram_chat_id` varchar(50) DEFAULT NULL,
  `verification_status` enum('pending','approved','rejected') DEFAULT 'approved',
  `notifications` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `phone_number`, `referral_code`, `referred_by`, `password`, `transaction_pin`, `google2fa_secret`, `avatar`, `balance`, `vip_level`, `lifetime_bet`, `agent_commission_percent`, `agent_share_percent`, `kbz_pay_number`, `kbz_pay_name`, `wave_pay_number`, `wave_pay_name`, `payment_info_json`, `role`, `created_at`, `is_banned`, `last_bonus_date`, `last_active`, `last_login_ip`, `telegram_chat_id`, `verification_status`, `notifications`) VALUES
(1, 'Admin', '09000000001', '34C366', NULL, '$2y$10$Xj1isECsKy9Hfybwz4F4eeO//EIpJt.Tiah.9Xx.YrVWZnMhrCDCa', '$2y$10$RYTorayZq03FY1miIOEsb.SY/cOAtdXCw7nlfOydYmq8JK0Bu6Qn.', NULL, NULL, 50400.00, 'Standard', 700.00, 0.00, 0.00, '', '', '', '', '[]', 'admin', '2026-06-18 16:06:25', 0, '2026-06-20', '2026-06-20 17:12:29', '127.0.0.1', NULL, 'approved', 0);

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fee_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `admin_payment_account` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reject_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `bets`
--
ALTER TABLE `bets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_status_section_date` (`status`,`bet_section`,`target_date`),
  ADD KEY `idx_bet_number_status_date` (`bet_number`,`status`,`target_date`),
  ADD KEY `idx_bet_limit_calc` (`bet_number`,`status`,`target_date`,`bet_section`);

--
-- Indexes for table `betting_sessions`
--
ALTER TABLE `betting_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session` (`game_type`,`section`,`target_date`),
  ADD KEY `idx_status_notified_close` (`status`,`admin_notified`,`close_time`);

--
-- Indexes for table `bonus_history`
--
ALTER TABLE `bonus_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referrer_id` (`referrer_id`),
  ADD KEY `referred_user_id` (`referred_user_id`);

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_transaction_id` (`transaction_id`);

--
-- Indexes for table `guides`
--
ALTER TABLE `guides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `guide_key` (`guide_key`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `page_seo`
--
ALTER TABLE `page_seo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_name` (`page_name`);

--
-- Indexes for table `payment_accounts`
--
ALTER TABLE `payment_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pin_attempts`
--
ALTER TABLE `pin_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pre_approved_transactions`
--
ALTER TABLE `pre_approved_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `result_history`
--
ALTER TABLE `result_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `sub_admin_permissions`
--
ALTER TABLE `sub_admin_permissions`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_notifications`
--
ALTER TABLE `system_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_phone` (`phone_number`),
  ADD UNIQUE KEY `referral_code` (`referral_code`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_account_number` (`account_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `bets`
--
ALTER TABLE `bets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `betting_sessions`
--
ALTER TABLE `betting_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bonus_history`
--
ALTER TABLE `bonus_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `guides`
--
ALTER TABLE `guides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `page_seo`
--
ALTER TABLE `page_seo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_accounts`
--
ALTER TABLE `payment_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pin_attempts`
--
ALTER TABLE `pin_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pre_approved_transactions`
--
ALTER TABLE `pre_approved_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `result_history`
--
ALTER TABLE `result_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_notifications`
--
ALTER TABLE `system_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD CONSTRAINT `admin_activity_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bets`
--
ALTER TABLE `bets`
  ADD CONSTRAINT `bets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bonus_history`
--
ALTER TABLE `bonus_history`
  ADD CONSTRAINT `bonus_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `commissions`
--
ALTER TABLE `commissions`
  ADD CONSTRAINT `commissions_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commissions_ibfk_2` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pin_attempts`
--
ALTER TABLE `pin_attempts`
  ADD CONSTRAINT `pin_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sub_admin_permissions`
--
ALTER TABLE `sub_admin_permissions`
  ADD CONSTRAINT `sub_admin_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `support_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_notifications`
--
ALTER TABLE `system_notifications`
  ADD CONSTRAINT `system_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transfers`
--
ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
