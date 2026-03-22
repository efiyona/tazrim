-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: מרץ 22, 2026 בזמן 04:28 PM
-- גרסת שרת: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tazrim`
--

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `ai_api_logs`
--

CREATE TABLE `ai_api_logs` (
  `id` int(11) NOT NULL,
  `home_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `ai_api_logs`
--

INSERT INTO `ai_api_logs` (`id`, `home_id`, `user_id`, `action_type`, `created_at`) VALUES
(1, 2, 2, 'Generated Burn Rate Insight', '2026-03-17 09:55:26'),
(2, 3, 4, 'AI Burn Rate Insight - Success', '2026-03-17 10:09:25'),
(3, 3, 4, 'AI Burn Rate Insight - Success', '2026-03-17 10:15:18'),
(4, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-17 10:17:18'),
(5, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-17 10:28:52'),
(6, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-17 10:29:19'),
(7, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-17 10:57:41'),
(8, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-17 10:57:56'),
(9, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-17 10:58:15'),
(10, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-17 15:03:40'),
(11, 2, 2, 'AI Burn Rate Insight - Failed (Code: 503)', '2026-03-17 15:15:30'),
(12, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-17 15:16:57'),
(13, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-17 17:00:16'),
(14, 2, 2, 'AI Burn Rate Insight - Failed (Code: 403)', '2026-03-19 11:53:42'),
(15, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-19 11:55:39'),
(16, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-22 09:59:40'),
(17, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-22 10:11:08'),
(18, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-22 10:11:53'),
(19, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-22 12:32:24'),
(20, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-22 13:37:31'),
(21, 2, 2, 'AI Burn Rate Insight - Success', '2026-03-22 13:38:16');

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `ai_insights_cache`
--

CREATE TABLE `ai_insights_cache` (
  `id` int(11) NOT NULL,
  `home_id` int(11) NOT NULL,
  `insight_type` varchar(50) NOT NULL,
  `insight_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `home_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `api_tokens`
--

INSERT INTO `api_tokens` (`id`, `user_id`, `home_id`, `token`, `created_at`, `last_used`) VALUES
(1, 2, 2, 'EFI_APPLE_PAY_2026_SECURE', '2026-03-22 14:20:25', '2026-03-22 15:20:39');

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `home_id` int(11) DEFAULT NULL,
  `budget_limit` decimal(10,2) DEFAULT 0.00,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-tag',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `categories`
--

INSERT INTO `categories` (`id`, `home_id`, `budget_limit`, `name`, `type`, `icon`, `is_active`) VALUES
(1, 2, 0.00, 'מזון וסופר', 'expense', 'fa-cart-shopping', 1),
(2, 2, 2400.00, 'דיור ושכירות', 'expense', 'fa-house', 0),
(3, 2, 150.00, 'תחבורה ורכב', 'expense', 'fa-car', 0),
(4, 2, 50.00, 'בריאות', 'expense', 'fa-baby', 1),
(5, 2, 0.00, 'שכר עבודה', 'income', 'fa-money-bill-wave', 1),
(6, 2, 0.00, 'מתנות', 'income', 'fa-gift', 1),
(7, 2, 400.00, 'כללי', 'expense', 'fa-hammer', 0),
(10, 2, 0.00, 'EE', 'income', 'fa-tag', 0),
(11, 4, 0.00, 'משכורת', 'income', 'fa-money-bill-wave', 1),
(12, 4, 300.00, 'סופרמרקט', 'expense', 'fa-cart-shopping', 1),
(13, 4, 1500.00, 'חשבונות הבית', 'expense', 'fa-bolt', 1),
(14, 4, 0.00, 'שונות', 'expense', 'fa-tag', 1),
(15, 2, 500.00, 'ביגוד', 'expense', 'fa-shirt', 1),
(16, 3, 0.00, 'משכורת', 'income', 'fa-money-bill-wave', 1),
(17, 3, 0.00, 'מתנות והחזרים', 'income', 'fa-gift', 1),
(18, 3, 2500.00, 'סופרמרקט', 'expense', 'fa-cart-shopping', 1),
(19, 3, 800.00, 'תחבורה ודלק', 'expense', 'fa-car', 1),
(20, 3, 1500.00, 'חשבונות הבית', 'expense', 'fa-bolt', 1);

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `homes`
--

CREATE TABLE `homes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `primary_user_id` int(11) DEFAULT NULL,
  `join_code` varchar(4) NOT NULL,
  `initial_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `homes`
--

INSERT INTO `homes` (`id`, `name`, `primary_user_id`, `join_code`, `initial_balance`, `created_at`) VALUES
(2, 'משפחה יונה המקוריתת', 2, '5246', 777.00, '2026-03-16 14:00:52'),
(3, 'משפחת יונה', 4, '6136', 0.00, '2026-03-17 10:09:08'),
(4, 'משפחת תהילה', 5, '3624', 4000.00, '2026-03-22 11:30:41');

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `home_id` int(11) DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT 0,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','error') DEFAULT 'info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `notifications`
--

INSERT INTO `notifications` (`id`, `home_id`, `user_id`, `creator_id`, `title`, `message`, `type`, `created_at`) VALUES
(8, 0, NULL, 0, 'ברוכים הבאים ל\"התזרים\"! 👋', 'אנחנו שמחים שהצטרפתם! כאן תוכלו לנהל את התקציב המשפחתי בצורה חכמה.', 'success', '2026-03-22 12:44:37'),
(10, 2, NULL, 0, 'אפי', 'הוסיף פעולה חדשה: <span class=\'notif-bold\'>משכורת</span> בסך 56.00 ₪', 'info', '2026-03-22 12:52:17'),
(11, 2, NULL, 0, 'אפי', 'הוסיף פעולה חדשה: <span class=\'notif-bold\'>אוכל</span> בסך 3,000.00 ₪', 'info', '2026-03-22 13:27:45'),
(12, 2, NULL, 0, 'פעולה מהירה', 'הוסיף מהאייפון 📱: <span class=\'notif-bold\'>קפה ומאפה לבדיקה</span> בסך 46 ₪', 'info', '2026-03-22 14:29:47'),
(13, 2, NULL, 0, 'פעולה מהירה', 'הוסיף מהאייפון 📱: <span class=\'notif-bold\'>ירקות לבדיקה</span> בסך 50 ₪', 'info', '2026-03-22 14:53:50'),
(14, 2, NULL, 0, 'פעולה מהירה', 'הוסיף מהאייפון 📱: <span class=\'notif-bold\'>ירקות לבדיקה</span> בסך 50 ₪', 'info', '2026-03-22 14:54:28'),
(15, 2, NULL, 0, 'פעולה מהירה', 'הוסיף מהאייפון 📱: <span class=\'notif-bold\'>ירקות לבדיקה</span> בסך 50 ₪', 'info', '2026-03-22 15:00:31');

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `notification_reads`
--

CREATE TABLE `notification_reads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `notification_reads`
--

INSERT INTO `notification_reads` (`id`, `user_id`, `notification_id`, `read_at`) VALUES
(3, 4, 8, '2026-03-22 12:50:36'),
(4, 4, 9, '2026-03-22 12:50:49'),
(5, 2, 1, '2026-03-22 12:51:26'),
(6, 2, 2, '2026-03-22 12:51:26'),
(7, 2, 3, '2026-03-22 12:51:26'),
(8, 2, 4, '2026-03-22 12:51:26'),
(9, 2, 5, '2026-03-22 12:51:26'),
(10, 2, 8, '2026-03-22 12:51:26'),
(12, 2, 10, '2026-03-22 12:52:19'),
(13, 2, 11, '2026-03-22 13:27:47');

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `recurring_transactions`
--

CREATE TABLE `recurring_transactions` (
  `id` int(11) NOT NULL,
  `home_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `day_of_month` int(11) NOT NULL,
  `last_injected_month` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `recurring_transactions`
--

INSERT INTO `recurring_transactions` (`id`, `home_id`, `user_id`, `type`, `amount`, `category`, `description`, `day_of_month`, `last_injected_month`, `is_active`, `created_at`) VALUES
(5, 2, 2, 'expense', 3000.00, 15, 'אוכל', 24, '2026-03-01', 1, '2026-03-22 13:27:45');

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `home_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `transactions`
--

INSERT INTO `transactions` (`id`, `home_id`, `user_id`, `amount`, `type`, `category`, `description`, `transaction_date`, `created_at`) VALUES
(1, 2, 2, 78.00, 'expense', '3', 'קניות שבועיות בשופרסל', '2026-03-16', '2026-03-16 14:54:28'),
(2, 2, 2, 12000.00, 'income', '5', 'משכורת חודש מרץ', '2026-03-10', '2026-03-16 14:54:28'),
(3, 2, 2, 3500.00, 'expense', '2', 'תשלום שכירות', '2026-03-01', '2026-03-16 14:54:28'),
(5, 2, 2, 150.00, 'expense', '4', 'סופר-פארם תרופות', '2026-03-12', '2026-03-16 14:54:28'),
(6, 2, 2, 500.00, 'income', '6', 'מתנה ליום הולדת', '2026-03-15', '2026-03-16 14:54:28'),
(7, 2, 2, 85.50, 'expense', '1', 'פיצה בערב', '2026-03-13', '2026-03-16 14:54:28'),
(8, 2, 2, 300.00, 'expense', '3', 'חניה ורכבת', '2026-03-08', '2026-03-16 14:54:28'),
(9, 2, 2, 120.00, 'expense', '4', 'ביקור אצל רופא שיניים', '2026-03-05', '2026-03-16 14:54:28'),
(10, 2, 2, 1000.00, 'income', '6', 'מכירת ציוד ישן', '2026-03-09', '2026-03-16 14:54:28'),
(12, 2, 2, 55.00, 'expense', '3', 'שטיפת רכב', '2026-03-07', '2026-03-16 14:54:28'),
(13, 2, 2, 50.00, 'expense', '1', 'ירקות', '2026-03-17', '2026-03-17 15:03:20'),
(14, 2, 2, 56.00, 'income', '6', 'משכורת טרםם', '2026-03-19', '2026-03-17 15:04:07'),
(15, 2, 2, 30.00, 'expense', '1', 'adobe מנוי אפי', '2026-03-01', '2026-03-17 15:05:13'),
(18, 2, 2, 67.00, 'expense', '3', 'כ', '2026-03-22', '2026-03-22 10:44:55'),
(19, 2, 2, 500.00, 'income', '6', 'ט.ר.ם', '2026-03-22', '2026-03-22 11:56:52'),
(20, 2, 2, 56.00, 'expense', '3', 'טסט', '2026-03-22', '2026-03-22 12:17:57'),
(21, 2, 2, 56.00, 'expense', '3', 'ads', '2026-03-22', '2026-03-22 12:20:16'),
(22, 2, 2, 3.00, 'expense', '3', '3', '2026-03-22', '2026-03-22 12:21:03'),
(23, 2, 2, 45.00, 'income', '6', 'הגדה', '2026-03-22', '2026-03-22 12:22:52'),
(24, 2, 2, 45.00, 'expense', '1', 'ירקות', '2026-03-22', '2026-03-22 12:31:25'),
(25, 2, 2, 45.00, 'expense', '1', 'לחם', '2026-03-22', '2026-03-22 12:36:00'),
(26, 3, 4, 56.00, 'expense', '19', 'דגהגד', '2026-03-22', '2026-03-22 12:50:47'),
(29, 2, 2, 45.50, 'expense', '4', 'קפה ומאפה לבדיקה', '2026-03-22', '2026-03-22 14:29:47'),
(30, 2, 2, 50.00, 'expense', '1', 'ירקות לבדיקה', '2026-03-22', '2026-03-22 14:53:50'),
(31, 2, 2, 50.00, 'expense', '15', 'ירקות לבדיקה', '2026-03-22', '2026-03-22 14:54:28'),
(32, 2, 2, 50.00, 'expense', '15', 'ירקות לבדיקה', '2026-03-22', '2026-03-22 15:00:31');

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `home_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `remember_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `users`
--

INSERT INTO `users` (`id`, `home_id`, `first_name`, `last_name`, `nickname`, `email`, `password`, `phone`, `role`, `remember_token`, `created_at`) VALUES
(2, 2, 'אפי', 'יונה', 'הבעל', 'efiyona10@gmail.com', '$2y$10$kSVJ7U2bOJHldRRD6WZLX.JizeXeMX9PM4vEH0bjXwJVyfoiX4SQi', '0556889131', 'home_admin', 'fc8782ed390eef64c05831a9de45a7e6d18f23b372f58fb851ace1214220eb8b', '2026-03-16 14:00:52'),
(3, 2, 'הודיה', 'יונה', 'אישה שלי', 'hodaya.roze@gmail.com', '$2y$10$9vN1T/VOYLZXDCcf1ARYC.1uvW78soN2sxokK3kmrHgYXF1EYPLWq', '0543448205', 'user', NULL, '2026-03-17 09:35:13'),
(4, 3, 'משה', 'יונה', '', 'moshiky@gmail.com', '$2y$10$o8TjKjVRAbkRXN.KTMJxcOkBXmqR8vSrj05UcdGG2rVSJXe3NAU16', '0585598765', 'home_admin', NULL, '2026-03-17 10:09:08'),
(5, 4, 'יאיר', 'יונה', 'האח', 'yairyona10@gmail.com', '$2y$10$KG348cMftASMJzC5Avz2GuJ..QltyADOL0FmyCzB3XNBaNgqxl9fm', '0543343382', 'home_admin', NULL, '2026-03-22 11:30:41');

--
-- Indexes for dumped tables
--

--
-- אינדקסים לטבלה `ai_api_logs`
--
ALTER TABLE `ai_api_logs`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `ai_insights_cache`
--
ALTER TABLE `ai_insights_cache`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- אינדקסים לטבלה `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `homes`
--
ALTER TABLE `homes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `join_code` (`join_code`);

--
-- אינדקסים לטבלה `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_notif` (`user_id`,`notification_id`);

--
-- אינדקסים לטבלה `recurring_transactions`
--
ALTER TABLE `recurring_transactions`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `home_id` (`home_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_api_logs`
--
ALTER TABLE `ai_api_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `ai_insights_cache`
--
ALTER TABLE `ai_insights_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `homes`
--
ALTER TABLE `homes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `notification_reads`
--
ALTER TABLE `notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `recurring_transactions`
--
ALTER TABLE `recurring_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
