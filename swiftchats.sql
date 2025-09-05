-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 19, 2025 at 02:27 AM
-- Server version: 10.6.22-MariaDB
-- PHP Version: 8.3.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `swiftchats`
--

-- --------------------------------------------------------

--
-- Table structure for table `addons`
--

CREATE TABLE `addons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `category` varchar(128) NOT NULL,
  `name` varchar(255) NOT NULL,
  `logo` varchar(128) NOT NULL,
  `description` text DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `license` varchar(255) DEFAULT NULL,
  `version` varchar(128) DEFAULT NULL,
  `is_plan_restricted` tinyint(1) NOT NULL DEFAULT 0,
  `update_available` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addons`
--

INSERT INTO `addons` (`id`, `uuid`, `category`, `name`, `logo`, `description`, `metadata`, `license`, `version`, `is_plan_restricted`, `update_available`, `status`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '4d0de1bd-6a3c-4cf1-b58a-ad55023849ec', 'chat', 'Embedded Signup', 'whatsapp.png', 'An Embedded Signup add-on allows app users to register using their WhatsApp account.', '{\"name\":\"EmbeddedSignup\",\"input_fields\":[{\"element\":\"input\",\"type\":\"text\",\"name\":\"whatsapp_client_id\",\"label\":\"App ID\",\"class\":\"col-span-1\"},{\"element\":\"input\",\"type\":\"password\",\"name\":\"whatsapp_client_secret\",\"label\":\"App secret\",\"class\":\"col-span-1\"},{\"element\":\"input\",\"type\":\"text\",\"name\":\"whatsapp_config_id\",\"label\":\"Config ID\",\"class\":\"col-span-2\"},{\"element\":\"input\",\"type\":\"password\",\"name\":\"whatsapp_access_token\",\"label\":\"Access token\",\"class\":\"col-span-2\"}]}', NULL, NULL, 1, 0, 1, 0, '2025-06-19 02:50:39', '2025-06-19 02:51:01'),
(2, '37bbadd7-9692-4ca3-a8ae-ff15ebce214d', 'recaptcha', 'Google Recaptcha', 'google_recaptcha.png', 'Google reCAPTCHA enhances website security by preventing spam and abusive activities.', '{\n    \"input_fields\": [\n        {\n            \"element\": \"input\",\n            \"type\": \"password\",\n            \"name\": \"recaptcha_site_key\",\n            \"label\": \"Recaptcha site key\",\n            \"class\": \"col-span-2\"\n        },\n        {\n            \"element\": \"input\",\n            \"type\": \"password\",\n            \"name\": \"recaptcha_secret_key\",\n            \"label\": \"Recaptcha secret key\",\n            \"class\": \"col-span-2\"\n        }\n    ],\n    \"name\": \"GoogleRecaptcha\"\n}', NULL, NULL, 0, 0, 1, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(3, '15c17e0d-2d87-4a61-962e-298f88bffd15', 'analytics', 'Google Analytics', 'google_analytics.png', 'Google Analytics tracks website performance and provides valuable insights for optimization.', '{\"input_fields\": [{\"element\": \"input\", \"type\": \"text\", \"name\": \"google_analytics_tracking_id\", \"label\": \"Google analytics tracking ID\", \"class\": \"col-span-2\"}]}', NULL, NULL, 0, 0, 1, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(4, 'f422453f-c3d1-4393-9dca-f276ff2c0a5e', 'maps', 'Google Maps', 'google_maps.png', 'Google Maps provides interactive maps for whatsapp messages.', '{\"input_fields\": [{\"element\": \"input\", \"type\": \"text\", \"name\": \"google_maps_api_key\", \"label\": \"Google maps API key\", \"class\": \"col-span-2\"}]}', NULL, NULL, 0, 0, 1, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(5, 'dcd215a5-86f9-4a15-b889-791f6f8e99e9', 'payments', 'Razorpay', 'razorpay.png', 'Razorpay is a payment platform that simplies payment processing.', '{\n    \"input_fields\": [\n        {\n            \"element\": \"input\",\n            \"type\": \"text\",\n            \"name\": \"razorpay_key_id\",\n            \"label\": \"Key ID\",\n            \"class\": \"col-span-2\"\n        },\n        {\n            \"element\": \"input\",\n            \"type\": \"text\",\n            \"name\": \"razorpay_secret_key\",\n            \"label\": \"Secret Key\",\n            \"class\": \"col-span-2\"\n        },\n        {\n            \"element\": \"input\",\n            \"type\": \"text\",\n            \"name\": \"razorpay_webhook_secret\",\n            \"label\": \"Webhook secret\",\n            \"class\": \"col-span-2\"\n        }\n    ],\n    \"name\": \"Razorpay\"\n}', NULL, NULL, 0, 0, 1, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(6, 'aded5848-dafb-4090-88ee-662cdef59838', 'ai', 'AI Assistant', 'ai.png', 'The AI assistant delivers intelligent, AI-driven responses by utilizing user data for training.', '{\n    \"name\": \"IntelliReply\"\n}', NULL, NULL, 1, 0, 0, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(7, '357a4168-bedb-4c98-b19d-e6f309484c54', 'utility', 'Webhooks', 'webhook_icon.png', 'Webhooks enable real-time data transfer by sending automated notifications on specific events.', '{\n    \"name\": \"Webhook\"\n}', NULL, NULL, 1, 0, 0, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(8, 'a62e6f03-79b7-4259-8cf5-728715be2f19', 'utility', 'Flow builder', 'flow_icon.png', 'Flow Builder automation allows users to visually create and manage messaging workflows.', '{\n    \"name\": \"FlowBuilder\"\n}', NULL, NULL, 1, 0, 0, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(9, 'bed080fa-b8bc-4658-84f8-ea8c44617927', 'two factor authentication', 'Google Authenticator', 'google_authenticator.png', 'Google Authenticator enhances security with two-factor authentication.', '{\n    \"name\": \"GoogleAuthenticator\"\n}', 'regular', '1.0', 0, 0, 1, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10');

-- --------------------------------------------------------

--
-- Table structure for table `auto_replies`
--

CREATE TABLE `auto_replies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `trigger` text NOT NULL,
  `match_criteria` varchar(100) NOT NULL,
  `metadata` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_credits`
--

CREATE TABLE `billing_credits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(19,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_debits`
--

CREATE TABLE `billing_debits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(19,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_invoices`
--

CREATE TABLE `billing_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `subtotal` decimal(19,4) NOT NULL,
  `coupon_id` int(11) DEFAULT NULL,
  `coupon_amount` decimal(23,2) DEFAULT 0.00,
  `tax` decimal(23,10) NOT NULL DEFAULT 0.0000000000,
  `tax_type` enum('inclusive','exclusive') NOT NULL,
  `total` decimal(19,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_items`
--

CREATE TABLE `billing_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `billing_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `interval` int(11) NOT NULL,
  `amount` decimal(19,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_payments`
--

CREATE TABLE `billing_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `processor` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `amount` decimal(19,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_tax_rates`
--

CREATE TABLE `billing_tax_rates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `rate` decimal(19,4) NOT NULL,
  `amount` decimal(19,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_transactions`
--

CREATE TABLE `billing_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `entity_type` enum('payment','invoice','credit','debit') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_authors`
--

CREATE TABLE `blog_authors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(128) NOT NULL,
  `first_name` varchar(128) NOT NULL,
  `last_name` varchar(128) NOT NULL,
  `bio` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_categories`
--

CREATE TABLE `blog_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `is_featured` tinyint(4) NOT NULL DEFAULT 0,
  `published` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `publish_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_tags`
--

CREATE TABLE `blog_tags` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE `campaigns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `template_id` int(11) NOT NULL,
  `contact_group_id` int(11) NOT NULL,
  `metadata` text NOT NULL,
  `status` varchar(128) NOT NULL DEFAULT 'pending',
  `scheduled_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_logs`
--

CREATE TABLE `campaign_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `chat_id` int(11) DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `status` enum('pending','success','failed','ongoing') NOT NULL,
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_log_retries`
--

CREATE TABLE `campaign_log_retries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `campaign_log_id` bigint(20) UNSIGNED NOT NULL,
  `chat_id` bigint(20) UNSIGNED DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chats`
--

CREATE TABLE `chats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `wam_id` varchar(128) DEFAULT NULL,
  `contact_id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` enum('inbound','outbound') DEFAULT NULL,
  `metadata` text NOT NULL,
  `media_id` int(11) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_logs`
--

CREATE TABLE `chat_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `contact_id` int(11) NOT NULL,
  `entity_type` varchar(128) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_media`
--

CREATE TABLE `chat_media` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `path` varchar(255) DEFAULT NULL,
  `location` enum('local','amazon') NOT NULL DEFAULT 'local',
  `type` varchar(255) DEFAULT NULL,
  `size` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_notes`
--

CREATE TABLE `chat_notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `contact_id` bigint(20) UNSIGNED NOT NULL,
  `content` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_status_logs`
--

CREATE TABLE `chat_status_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `chat_id` int(11) NOT NULL,
  `metadata` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_tickets`
--

CREATE TABLE `chat_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `contact_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` varchar(128) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_ticket_logs`
--

CREATE TABLE `chat_ticket_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `contact_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `first_name` varchar(128) DEFAULT NULL,
  `last_name` varchar(128) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `latest_chat_created_at` timestamp NULL DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `contact_group_id` int(11) DEFAULT NULL,
  `is_favorite` tinyint(4) NOT NULL DEFAULT 0,
  `ai_assistance_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_contact_group`
--

CREATE TABLE `contact_contact_group` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `contact_id` bigint(20) UNSIGNED NOT NULL,
  `contact_group_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_fields`
--

CREATE TABLE `contact_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `position` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `type` varchar(128) NOT NULL,
  `value` text DEFAULT NULL,
  `required` tinyint(3) UNSIGNED NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_groups`
--

CREATE TABLE `contact_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `quantity_redeemed` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `source` varchar(128) NOT NULL,
  `title` varchar(128) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `embeddings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`embeddings`)),
  `status` varchar(128) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `metadata` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` blob NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `name`, `subject`, `body`, `updated_at`, `updated_by`) VALUES
(1, 'Reset Password', 'Reset Password', 0x3c703e4869207b7b46697273744e616d657d7d2c3c2f703e3c703e596f752068617665207375626d697474656420612070617373776f726420726573657420666f7220796f7572206163636f756e742e204966207468697320776173206e6f7420796f752c2073696d706c792069676e6f7265207468697320656d61696c2e2042757420696620796f75206469642c20636c69636b206f6e2074686973206c696e6b207b7b4c696e6b7d7d20746f20726573657420796f75722070617373776f72642e204966207468617420646f65736e277420776f726b2c20636f707920616e642070617374652074686973206c696e6b20746f20796f75722062726f777365722e3c2f703e3c703e7b7b4c696e6b7d7d3c2f703e, '2025-06-19 02:26:10', 1),
(2, 'Password Reset Notification', 'Your Password has been reset', 0x3c703e4869207b7b46697273744e616d657d7d2c3c2f703e3c703e596f75722070617373776f726420686173206265656e207265736574207375636365737366756c6c792120596f752063616e206c6f67696e20746f20796f7572206163636f756e742e3c2f703e, '2025-06-19 02:26:10', 1),
(3, 'Registration', 'Welcome to {{CompanyName}}', 0x3c703e48656c6c6f207b7b46697273744e616d657d7d2c3c2f703e3c703e4920616d204a6f652c2074686520666f756e646572206f66207b7b436f6d70616e794e616d657d7d20616e64204920776f756c64206c696b6520746f20657874656e64206d7920686561727466656c742077656c636f6d6520746f20796f7520666f72206a6f696e696e67206f757220706c6174666f726d2e20576520617265206578636974656420746f206861766520796f75206f6e626f6172642e204665656c206672656520746f206578706c6f7265206f757220706c6174666f726d20616e64206c6574207573206b6e6f7720696620796f75206861766520616e79207175657374696f6e73206f72206e65656420617373697374616e63652e203c2f703e3c703e5468616e6b20796f7520666f722063686f6f73696e67206f757220706c6174666f726d213c2f703e3c703e4265737420726567617264732c3c2f703e3c703e546865207b7b436f6d70616e794e616d657d7d205465616d3c2f703e3c703e3c62723e3c2f703e, '2025-06-19 02:26:10', 1),
(4, 'Invite', 'You have been invited to join {{CompanyName}}', 0x3c703e48656c6c6f2074686572652c3c2f703e3c703e3c7370616e207374796c653d22636f6c6f723a207267622835352c2036352c203831293b223e596f7527766520726563656976656420616e20696e7669746174696f6e20746f206a6f696e207b7b436f6d70616e794e616d657d7d2773206163636f756e742066726f6d207b7b46697273744e616d657d7d2e3c2f7370616e3e3c2f703e3c703e546f2067657420737461727465642c2073696d706c7920636c69636b206f6e2074686520666f6c6c6f77696e67206c696e6b3a207b7b4c696e6b7d7d3c2f703e3c703e5468616e6b20796f7520616e642077656c636f6d652061626f617264213c2f703e3c703e4265737420726567617264732c3c2f703e3c703e7b7b436f6d70616e794e616d657d7d205465616d203c2f703e3c703e3c62723e3c2f703e, '2025-06-19 02:26:10', 1),
(5, 'Verify Email', 'Please verify your email', 0x3c703e4869207b66697273744e616d657d2c3c2f703e3c703e506c656173652076657269667920796f757220656d61696c20627920636c69636b696e67206f6e20746865206c696e6b2062656c6f772e3c2f703e3c703e7b766572696669636174696f6e4c696e6b7d3c2f703e3c703e3c7370616e207374796c653d226c65747465722d73706163696e673a2030656d3b20746578742d616c69676e3a20766172282d2d62732d626f64792d746578742d616c69676e293b223e4265737420726567617264732c3c2f7370616e3e3c2f703e3c703e3c62723e3c2f703e, '2025-06-19 02:26:10', 1),
(6, 'Payment Success', 'Your subscription payment was successful', 0x3c703e48656c6c6f2c3c2f703e3c703e596f757220737562736372697074696f6e207061796d656e7420776173207375636365737366756c213c2f703e, '2025-06-19 02:26:10', 1),
(7, 'Payment Failed', 'Your subscription payment was unsuccessful', 0x3c703e48656c6c6f2c3c2f703e3c703e596f7572207061796d656e742077617320756e7375636365737366756c2c20706c6561736520636865636b20796f7572207061796d656e7420616e6420636f6e6669726d2e3c2f703e3c703e3c62723e3c2f703e, '2025-06-19 02:26:10', 1),
(8, 'Subscription Renewal', 'Your subscription has been renewed successfully', 0x3c703e4869207b7b46697273744e616d657d7d2c3c2f703e3c703e596f757220737562736372697074696f6e20686173206265656e2072656e65776564207375636365737366756c6c792e203c2f703e, '2025-06-19 02:26:10', 1),
(9, 'Subscription Plan Purchase', 'Your have subscribed to {{plan}} successfully', 0x3c703e4869207b7b46697273744e616d657d7d2c3c2f703e3c703e596f752068617665206265656e207375627363726962656420746f20746865207b7b706c616e7d7d20706c616e207375636365737366756c6c792e3c2f703e, '2025-06-19 02:26:10', 1);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `question` text DEFAULT NULL,
  `answer` text DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE `languages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_rtl` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `languages`
--

INSERT INTO `languages` (`id`, `name`, `code`, `status`, `is_rtl`, `deleted_at`, `deleted_by`, `created_at`, `updated_at`) VALUES
(1, 'English', 'en', 'active', 0, NULL, NULL, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(2, 'French', 'fr', 'active', 0, NULL, NULL, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(3, 'Spanish', 'es', 'active', 0, NULL, NULL, '2025-06-19 02:26:10', '2025-06-19 02:26:10');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2024_03_20_050200_create_auto_replies_table', 1),
(2, '2024_03_20_050311_create_billing_credits_table', 1),
(3, '2024_03_20_050348_create_billing_debits_table', 1),
(4, '2024_03_20_050430_create_billing_invoices_table', 1),
(5, '2024_03_20_050508_create_billing_items_table', 1),
(6, '2024_03_20_050600_create_billing_payments_table', 1),
(7, '2024_03_20_050635_create_billing_tax_rates_table', 1),
(8, '2024_03_20_050711_create_billing_transactions_table', 1),
(9, '2024_03_20_050751_create_blog_authors_table', 1),
(10, '2024_03_20_050826_create_blog_categories_table', 1),
(11, '2024_03_20_050912_create_blog_posts_table', 1),
(12, '2024_03_20_050959_create_blog_tags_table', 1),
(13, '2024_03_20_051036_create_campaigns_table', 1),
(14, '2024_03_20_051111_create_campaign_logs_table', 1),
(15, '2024_03_20_051154_create_chats_table', 1),
(16, '2024_03_20_051253_create_chat_logs_table', 1),
(17, '2024_03_20_051336_create_chat_media_table', 1),
(18, '2024_03_20_051414_create_contacts_table', 1),
(19, '2024_03_20_051449_create_contact_groups_table', 1),
(20, '2024_03_20_051537_create_coupons_table', 1),
(21, '2024_03_20_051613_create_email_logs_table', 1),
(22, '2024_03_20_051655_create_email_templates_table', 1),
(23, '2024_03_20_051739_create_failed_jobs_table', 1),
(24, '2024_03_20_051807_create_faqs_table', 1),
(25, '2024_03_20_051847_create_jobs_table', 1),
(26, '2024_03_20_051919_create_modules_table', 1),
(27, '2024_03_20_051953_create_notifications_table', 1),
(28, '2024_03_20_052034_create_organizations_table', 1),
(29, '2024_03_20_052107_create_pages_table', 1),
(30, '2024_03_20_052141_create_password_reset_tokens_table', 1),
(31, '2024_03_20_052223_create_payment_gateways_table', 1),
(32, '2024_03_20_052338_create_reviews_table', 1),
(33, '2024_03_20_052401_create_users_table', 1),
(34, '2024_03_20_052430_create_roles_table', 1),
(35, '2024_03_20_052513_create_role_permissions_table', 1),
(36, '2024_03_20_052620_create_settings_table', 1),
(37, '2024_03_20_052654_create_subscriptions_table', 1),
(38, '2024_03_20_052731_create_subscription_plans_table', 1),
(39, '2024_03_20_052808_create_tax_rates_table', 1),
(40, '2024_03_20_052839_create_teams_table', 1),
(41, '2024_03_20_052914_create_team_invites_table', 1),
(42, '2024_03_20_052920_create_ticket_categories_table', 1),
(43, '2024_03_20_052956_create_templates_table', 1),
(44, '2024_03_20_053038_create_tickets_table', 1),
(45, '2024_03_20_053205_create_ticket_comments_table', 1),
(46, '2024_04_08_133150_create_organization_api_keys_table', 1),
(47, '2024_04_24_211852_create_languages', 1),
(48, '2024_04_27_155643_create_contact_fields_table', 1),
(49, '2024_04_27_160152_add_metadata_to_contacts_table', 1),
(50, '2024_05_11_052902_create_chat_notes_table', 1),
(51, '2024_05_11_052925_create_chat_tickets_table', 1),
(52, '2024_05_11_052940_create_chat_ticket_logs_table', 1),
(53, '2024_05_11_053846_rename_chat_logs_table', 1),
(54, '2024_05_11_054010_create_chat_logs_2_table', 1),
(55, '2024_05_11_063255_add_user_id_to_chats_table', 1),
(56, '2024_05_11_063540_add_role_to_team_invites_table', 1),
(57, '2024_05_11_063819_update_agent_role_to_teams_table', 1),
(58, '2024_05_11_064650_add_deleted_by_to_organization_api_keys_table', 1),
(59, '2024_05_11_065031_add_organization_id_to_tickets_table', 1),
(60, '2024_05_28_080331_make_password_nullable_in_users_table', 1),
(61, '2024_05_30_125859_modify_campaigns_table', 1),
(62, '2024_06_03_124254_create_addons_table', 1),
(63, '2024_06_07_040536_update_users_table_for_facebook_login', 1),
(64, '2024_06_07_040843_update_chat_media_table', 1),
(65, '2024_06_07_074903_add_soft_delete_to_teams_and_organizations', 1),
(66, '2024_06_09_155053_modify_billing_payments_table', 1),
(67, '2024_06_12_070820_modify_faqs_table', 1),
(68, '2024_07_04_053236_modify_amount_columns_in_billing_tables', 1),
(69, '2024_07_04_054143_modify_contacts_table_encoding', 1),
(70, '2024_07_09_011419_drop_seo_from_pages_table', 1),
(71, '2024_07_17_062442_allow_null_content_in_pages_table', 1),
(72, '2024_07_24_080535_add_latest_chat_created_at_to_contacts_table', 1),
(73, '2024_08_01_050752_add_ongoing_to_status_enum_in_campaign_logs_table', 1),
(74, '2024_08_08_130306_add_is_read_to_chats_table', 1),
(75, '2024_08_10_071237_create_documents_table', 1),
(76, '2024_10_16_201832_change_metadata_column_in_organizations_table', 1),
(77, '2024_11_12_101941_add_license_column_to_addons_table', 1),
(78, '2024_11_25_114450_add_version_and_update_needed_to_addons_table', 1),
(79, '2024_11_28_083453_add_tfa_secret_to_users_table', 1),
(80, '2024_11_29_070806_create_seeder_histories_table', 1),
(81, '2024_12_20_081118_add_is_plan_restricted_to_addons_table', 1),
(82, '2024_12_20_130829_add_is_active_table', 1),
(83, '2025_01_24_090926_add_index_to_chats_table', 1),
(84, '2025_01_24_091012_add_index_to_chat_tickets_table', 1),
(85, '2025_01_24_091043_add_index_to_contacts_first_name', 1),
(86, '2025_01_24_091115_add_fulltext_index_to_contacts_table', 1),
(87, '2025_01_29_071445_modify_status_column_in_chats_table', 1),
(88, '2025_02_21_084110_create_job_batches_table', 1),
(89, '2025_02_21_093829_add_queue_indexes', 1),
(90, '2025_04_02_085132_create_contact_contact_group_table', 1),
(91, '2025_05_01_045837_create_campaign_log_retries_table', 1),
(92, '2025_05_01_053318_add_retry_count_to_campaign_logs_table', 1),
(93, '2025_05_23_101200_add_rtl_to_languages_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `actions` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `name`, `actions`) VALUES
(1, 'customers', 'view, create, edit, delete'),
(2, 'organizations', 'view, create, edit, delete'),
(3, 'billing', 'view'),
(4, 'support', 'view, create, assign'),
(5, 'team', 'view, create, edit, delete'),
(6, 'roles', 'view, create, edit, delete'),
(7, 'subscription_plans', 'view, create, edit, delete'),
(8, 'settings', 'general, timezone, broadcast_driver, payment_gateways, smtp, email_templates, billing, tax_rates, coupons, frontend');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `url` varchar(191) DEFAULT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `identifier` varchar(128) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `timezone` varchar(128) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organization_api_keys`
--

CREATE TABLE `organization_api_keys` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(255) NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(128) NOT NULL,
  `content` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `name`, `content`, `updated_at`, `created_at`) VALUES
(1, 'Privacy Policy', 'Introduction<p>Thanks for using our products and services (\"Services\"). The Services are provided by &lt;Your Business Name&gt;.</p><p>By using our Services, you are agreeing to these terms. Please read them carefully.</p><p>Our Services are very diverse, so sometimes additional terms or product requirements (including age requirements) may apply. Additional terms will be available with the relevant Services, and those additional terms become part of your agreement with us if you use those Services.</p>Using our services<p>You must follow any policies made available to you within the Services.</p><p>Don\'t misuse our Services. For example, don\'t interfere with our Services or try to access them using a method other than the interface and the instructions that we provide. You may use our Services only as permitted by law, including applicable export and re-export control laws and regulations. We may suspend or stop providing our Services to you if you do not comply with our terms or policies or if we are investigating suspected misconduct.</p><p>Using our Services does not give you ownership of any intellectual property rights in our Services or the content you access. You may not use content from our Services unless you obtain permission from its owner or are otherwise permitted by law. These terms do not grant you the right to use any branding or logos used in our Services. Don\'t remove, obscure, or alter any legal notices displayed in or along with our Services.</p>Privacy and copyright protection<p>&lt;Your Business Name&gt;\'s privacy policies explain how we treat your personal data and protect your privacy when you use our Services. By using our Services, you agree that &lt;Your Business Name&gt; can use such data in accordance with our privacy policies.</p><p>We respond to notices of alleged copyright infringement and terminate accounts of repeat infringers according to the process set out in the U.S. Digital Millennium Copyright Act.</p><p>We provide information to help copyright holders manage their intellectual property online. If you think somebody is violating your copyrights and want to notify us, you can find information about submitting notices and &lt;Your Business Name&gt;\'s policy about responding to notices in our Help Center.</p>Your content in our services<p>Some of our Services allow you to upload, submit, store, send or receive content. You retain ownership of any intellectual property rights that you hold in that content. In short, what belongs to you stays yours.</p><p>When you upload, submit, store, send or receive content to or through our Services, you give &lt;Your Business Name&gt; (and those we work with) a worldwide license to use, host, store, reproduce, modify, create derivative works (such as those resulting from translations, adaptations or other changes we make so that your content works better with our Services), communicate, publish, publicly perform, publicly display and distribute such content. The rights you grant in this license are for the limited purpose of operating, promoting, and improving our Services, and to develop new ones. This license continues even if you stop using our Services (for example, for a business listing you have added to &lt;Your Business Name&gt; Maps). Some Services may offer you ways to access and remove content that has been provided to that Service. Also, in some of our Services, there are terms or settings that narrow the scope of our use of the content submitted in those Services. Make sure you have the necessary rights to grant us this license for any content that you submit to our Services.</p>', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(2, 'Terms of Service', 'Introduction<p>Thanks for using our products and services (\"Services\"). The Services are provided by &lt;Your Business Name&gt;.</p><p>By using our Services, you are agreeing to these terms. Please read them carefully.</p><p>Our Services are very diverse, so sometimes additional terms or product requirements (including age requirements) may apply. Additional terms will be available with the relevant Services, and those additional terms become part of your agreement with us if you use those Services.</p>Using our services<p>You must follow any policies made available to you within the Services.</p><p>Don\'t misuse our Services. For example, don\'t interfere with our Services or try to access them using a method other than the interface and the instructions that we provide. You may use our Services only as permitted by law, including applicable export and re-export control laws and regulations. We may suspend or stop providing our Services to you if you do not comply with our terms or policies or if we are investigating suspected misconduct.</p><p>Using our Services does not give you ownership of any intellectual property rights in our Services or the content you access. You may not use content from our Services unless you obtain permission from its owner or are otherwise permitted by law. These terms do not grant you the right to use any branding or logos used in our Services. Don\'t remove, obscure, or alter any legal notices displayed in or along with our Services.</p>Privacy and copyright protection<p>&lt;Your Business Name&gt;\'s privacy policies explain how we treat your personal data and protect your privacy when you use our Services. By using our Services, you agree that &lt;Your Business Name&gt; can use such data in accordance with our privacy policies.</p><p>We respond to notices of alleged copyright infringement and terminate accounts of repeat infringers according to the process set out in the U.S. Digital Millennium Copyright Act.</p><p>We provide information to help copyright holders manage their intellectual property online. If you think somebody is violating your copyrights and want to notify us, you can find information about submitting notices and &lt;Your Business Name&gt;\'s policy about responding to notices in our Help Center.</p>Your content in our services<p>Some of our Services allow you to upload, submit, store, send or receive content. You retain ownership of any intellectual property rights that you hold in that content. In short, what belongs to you stays yours.</p><p>When you upload, submit, store, send or receive content to or through our Services, you give &lt;Your Business Name&gt; (and those we work with) a worldwide license to use, host, store, reproduce, modify, create derivative works (such as those resulting from translations, adaptations or other changes we make so that your content works better with our Services), communicate, publish, publicly perform, publicly display and distribute such content. The rights you grant in this license are for the limited purpose of operating, promoting, and improving our Services, and to develop new ones. This license continues even if you stop using our Services (for example, for a business listing you have added to &lt;Your Business Name&gt; Maps). Some Services may offer you ways to access and remove content that has been provided to that Service. Also, in some of our Services, there are terms or settings that narrow the scope of our use of the content submitted in those Services. Make sure you have the necessary rights to grant us this license for any content that you submit to our Services.</p>', '2025-06-19 02:26:10', '2025-06-19 02:26:10');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateways`
--

CREATE TABLE `payment_gateways` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(128) NOT NULL,
  `metadata` text DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_gateways`
--

INSERT INTO `payment_gateways` (`id`, `name`, `metadata`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Paypal', NULL, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(2, 'Stripe', NULL, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(3, 'Flutterwave', NULL, 0, '2025-06-19 02:26:10', '2025-06-19 02:26:10');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `review` text NOT NULL,
  `rating` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `uuid`, `name`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '28765916-f1b0-4113-b678-11b6437bd366', 'admin', '2025-06-19 02:26:10', '2025-06-19 02:26:10', NULL),
(2, '35b04812-36df-4ab5-b0ba-c40d5f1d64d5', 'Staff', '2025-06-19 02:26:10', '2025-06-19 02:26:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `module` varchar(128) NOT NULL,
  `action` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seeder_histories`
--

CREATE TABLE `seeder_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `seeder_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seeder_histories`
--

INSERT INTO `seeder_histories` (`id`, `seeder_name`, `created_at`, `updated_at`) VALUES
(1, 'Database\\Seeders\\AddonsLicenseSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(2, 'Database\\Seeders\\AddonsTableSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(3, 'Database\\Seeders\\AddonsTableSeeder2', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(4, 'Database\\Seeders\\AddonsTableSeeder3', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(5, 'Database\\Seeders\\AddonsTableSeeder4', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(6, 'Database\\Seeders\\AddonsTableSeeder5', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(7, 'Database\\Seeders\\AddonsTableSeeder6', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(8, 'Database\\Seeders\\AddonsTableSeeder7', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(9, 'Database\\Seeders\\AddonsTableSeeder8', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(10, 'Database\\Seeders\\EmailTemplateSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(11, 'Database\\Seeders\\LanguageTableSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(12, 'Database\\Seeders\\ModulesTableSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(13, 'Database\\Seeders\\PageSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(14, 'Database\\Seeders\\PaymentGatewaysTableSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(15, 'Database\\Seeders\\RolesTableSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(16, 'Database\\Seeders\\SettingsTableSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(17, 'Database\\Seeders\\TicketCategoriesTableSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10'),
(18, 'Database\\Seeders\\WebhookModuleSeeder', '2025-06-19 02:26:10', '2025-06-19 02:26:10');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key`, `value`) VALUES
('address', NULL),
('allow_facebook_login', '0'),
('allow_google_login', '0'),
('app_environment', 'local'),
('available_version', NULL),
('aws_access_key', NULL),
('aws_bucket', NULL),
('aws_default_region', NULL),
('aws_secret_key', NULL),
('billing_address', NULL),
('billing_city', NULL),
('billing_country', NULL),
('billing_name', NULL),
('billing_phone_1', NULL),
('billing_phone_2', NULL),
('billing_postal_code', NULL),
('billing_state', NULL),
('billing_tax_id', NULL),
('broadcast_driver', 'pusher'),
('company_name', 'Swiftchats'),
('currency', 'USD'),
('date_format', 'd-M-y'),
('default_image_api', NULL),
('email', NULL),
('facebook_login', NULL),
('favicon', NULL),
('google_analytics_status', '0'),
('google_analytics_tracking_id', NULL),
('google_login', NULL),
('google_maps_api_key', NULL),
('invoice_prefix', NULL),
('is_tax_inclusive', '1'),
('is_update_available', '0'),
('last_update_check', '2025-06-19 02:26:49'),
('logo', NULL),
('mail_config', NULL),
('phone', NULL),
('pusher_app_cluster', NULL),
('pusher_app_id', NULL),
('pusher_app_key', NULL),
('pusher_app_secret', NULL),
('recaptcha_active', '0'),
('recaptcha_secret_key', NULL),
('recaptcha_site_key', NULL),
('release_date', NULL),
('smtp_email_active', '0'),
('socials', NULL),
('storage_system', 'local'),
('time_format', 'H:i'),
('timezone', 'UTC'),
('title', NULL),
('trial_period', '20'),
('verify_email', '0'),
('version', '2.8.8'),
('whatsapp_callback_token', '20250619022610u3Cn');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `plan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_details` text DEFAULT NULL,
  `start_date` timestamp NULL DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `status` enum('trial','active') NOT NULL DEFAULT 'trial',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(23,2) NOT NULL,
  `period` enum('monthly','yearly') NOT NULL,
  `metadata` text NOT NULL,
  `status` enum('active','inactive','deleted') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_rates`
--

CREATE TABLE `tax_rates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('owner','manager','agent') NOT NULL DEFAULT 'manager',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_invites`
--

CREATE TABLE `team_invites` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(128) NOT NULL,
  `code` varchar(255) NOT NULL,
  `role` varchar(128) DEFAULT NULL,
  `invited_by` bigint(20) UNSIGNED NOT NULL,
  `expire_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `templates`
--

CREATE TABLE `templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `meta_id` varchar(128) NOT NULL,
  `name` varchar(128) NOT NULL,
  `category` varchar(128) NOT NULL,
  `language` varchar(128) NOT NULL,
  `metadata` text NOT NULL,
  `status` varchar(128) NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(50) NOT NULL,
  `reference` varchar(128) NOT NULL,
  `organization_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `subject` varchar(1024) NOT NULL,
  `message` varchar(1024) NOT NULL,
  `priority` enum('critical','high','medium','low') DEFAULT NULL,
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('open','pending','resolved','closed') NOT NULL DEFAULT 'pending',
  `closed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_categories`
--

CREATE TABLE `ticket_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_categories`
--

INSERT INTO `ticket_categories` (`id`, `name`) VALUES
(1, 'Signup/login issues'),
(2, 'Campaigns issues'),
(3, 'Whatsapp issue'),
(4, 'Template Issues'),
(5, 'Chatbot Issues'),
(6, 'Other');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_comments`
--

CREATE TABLE `ticket_comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `message` varchar(1024) NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(128) NOT NULL,
  `last_name` varchar(128) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `facebook_id` varchar(255) DEFAULT NULL,
  `avatar` varchar(191) DEFAULT NULL,
  `role` varchar(191) NOT NULL DEFAULT 'user',
  `phone` varchar(191) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `tfa_secret` varchar(255) DEFAULT NULL,
  `tfa` tinyint(4) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1,
  `meta` text DEFAULT NULL,
  `plan` text DEFAULT NULL,
  `plan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `will_expire` date DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `facebook_id`, `avatar`, `role`, `phone`, `address`, `email_verified_at`, `password`, `tfa_secret`, `tfa`, `status`, `meta`, `plan`, `plan_id`, `will_expire`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Admin', 'Swiftchats', 'admin@demo.com', NULL, NULL, 'admin', NULL, NULL, NULL, '$2y$10$Y8O5BBzQzMf4NH9m4cDIEO/NvLK0d6eMn.1RLuiAPGzymMUsdpN0y', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '2025-06-19 02:26:10', '2025-06-19 02:26:10', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addons`
--
ALTER TABLE `addons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `auto_replies`
--
ALTER TABLE `auto_replies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `auto_replies_uuid_unique` (`uuid`);

--
-- Indexes for table `billing_credits`
--
ALTER TABLE `billing_credits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `billing_credits_uuid_unique` (`uuid`);

--
-- Indexes for table `billing_debits`
--
ALTER TABLE `billing_debits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `billing_debits_uuid_unique` (`uuid`);

--
-- Indexes for table `billing_invoices`
--
ALTER TABLE `billing_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `billing_invoices_uuid_unique` (`uuid`);

--
-- Indexes for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `billing_payments`
--
ALTER TABLE `billing_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `billing_payments_uuid_unique` (`uuid`);

--
-- Indexes for table `billing_tax_rates`
--
ALTER TABLE `billing_tax_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `billing_tax_rates_uuid_unique` (`uuid`);

--
-- Indexes for table `billing_transactions`
--
ALTER TABLE `billing_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `billing_transactions_uuid_unique` (`uuid`);

--
-- Indexes for table `blog_authors`
--
ALTER TABLE `blog_authors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blog_authors_uuid_unique` (`uuid`);

--
-- Indexes for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blog_categories_uuid_unique` (`uuid`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blog_posts_uuid_unique` (`uuid`);

--
-- Indexes for table `blog_tags`
--
ALTER TABLE `blog_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blog_tags_uuid_unique` (`uuid`);

--
-- Indexes for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `campaigns_uuid_unique` (`uuid`);

--
-- Indexes for table `campaign_logs`
--
ALTER TABLE `campaign_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `campaign_log_retries`
--
ALTER TABLE `campaign_log_retries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chats_uuid_unique` (`uuid`),
  ADD KEY `chats_contact_id_index` (`contact_id`),
  ADD KEY `chats_created_at_index` (`created_at`),
  ADD KEY `idx_chats_contact_org_deleted_at` (`contact_id`,`organization_id`,`deleted_at`);

--
-- Indexes for table `chat_logs`
--
ALTER TABLE `chat_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_media`
--
ALTER TABLE `chat_media`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_notes`
--
ALTER TABLE `chat_notes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_status_logs`
--
ALTER TABLE `chat_status_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_tickets`
--
ALTER TABLE `chat_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_tickets_contact_id_index` (`contact_id`),
  ADD KEY `idx_chat_tickets_contact_assigned_to_status` (`contact_id`,`assigned_to`,`status`);

--
-- Indexes for table `chat_ticket_logs`
--
ALTER TABLE `chat_ticket_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contacts_uuid_unique` (`uuid`),
  ADD KEY `contacts_organization_id_index` (`organization_id`),
  ADD KEY `contacts_deleted_at_index` (`deleted_at`),
  ADD KEY `contacts_latest_chat_created_at_index` (`latest_chat_created_at`),
  ADD KEY `idx_contacts_first_name` (`first_name`),
  ADD KEY `idx_contacts_last_name` (`last_name`),
  ADD KEY `idx_contacts_email` (`email`),
  ADD KEY `idx_contacts_phone` (`phone`);
ALTER TABLE `contacts` ADD FULLTEXT KEY `fulltext_contacts_name_email_phone` (`first_name`,`last_name`,`phone`,`email`);

--
-- Indexes for table `contact_contact_group`
--
ALTER TABLE `contact_contact_group`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contact_contact_group_contact_id_foreign` (`contact_id`),
  ADD KEY `contact_contact_group_contact_group_id_foreign` (`contact_group_id`);

--
-- Indexes for table `contact_fields`
--
ALTER TABLE `contact_fields`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_groups`
--
ALTER TABLE `contact_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contact_groups_uuid_unique` (`uuid`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupons_code_unique` (`code`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `documents_uuid_unique` (`uuid`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_logs_uuid_unique` (`uuid`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_available_at_index` (`queue`,`available_at`),
  ADD KEY `jobs_attempts_index` (`attempts`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notifications_uuid_unique` (`uuid`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `organizations_uuid_unique` (`uuid`);

--
-- Indexes for table `organization_api_keys`
--
ALTER TABLE `organization_api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `organization_api_keys_token_unique` (`token`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_uuid_unique` (`uuid`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `seeder_histories`
--
ALTER TABLE `seeder_histories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `seeder_histories_seeder_name_unique` (`seeder_name`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subscriptions_uuid_unique` (`uuid`),
  ADD KEY `subscriptions_organization_id_foreign` (`organization_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subscription_plans_uuid_unique` (`uuid`);

--
-- Indexes for table `tax_rates`
--
ALTER TABLE `tax_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teams_uuid_unique` (`uuid`),
  ADD KEY `teams_organization_id_foreign` (`organization_id`),
  ADD KEY `teams_user_id_foreign` (`user_id`),
  ADD KEY `teams_created_by_foreign` (`created_by`);

--
-- Indexes for table `team_invites`
--
ALTER TABLE `team_invites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_invites_organization_id_foreign` (`organization_id`),
  ADD KEY `team_invites_invited_by_foreign` (`invited_by`);

--
-- Indexes for table `templates`
--
ALTER TABLE `templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `templates_organization_id_foreign` (`organization_id`),
  ADD KEY `templates_created_by_foreign` (`created_by`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tickets_user_id_foreign` (`user_id`),
  ADD KEY `tickets_category_id_foreign` (`category_id`),
  ADD KEY `tickets_assigned_to_foreign` (`assigned_to`),
  ADD KEY `tickets_closed_by_foreign` (`closed_by`);

--
-- Indexes for table `ticket_categories`
--
ALTER TABLE `ticket_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_comments_ticket_id_foreign` (`ticket_id`),
  ADD KEY `ticket_comments_user_id_foreign` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_deleted_at_unique` (`email`,`deleted_at`),
  ADD UNIQUE KEY `users_facebook_id_unique` (`facebook_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addons`
--
ALTER TABLE `addons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `auto_replies`
--
ALTER TABLE `auto_replies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_credits`
--
ALTER TABLE `billing_credits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_debits`
--
ALTER TABLE `billing_debits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_invoices`
--
ALTER TABLE `billing_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_items`
--
ALTER TABLE `billing_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_payments`
--
ALTER TABLE `billing_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_tax_rates`
--
ALTER TABLE `billing_tax_rates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_transactions`
--
ALTER TABLE `billing_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_authors`
--
ALTER TABLE `blog_authors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_categories`
--
ALTER TABLE `blog_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_tags`
--
ALTER TABLE `blog_tags`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaign_logs`
--
ALTER TABLE `campaign_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaign_log_retries`
--
ALTER TABLE `campaign_log_retries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chats`
--
ALTER TABLE `chats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_logs`
--
ALTER TABLE `chat_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_media`
--
ALTER TABLE `chat_media`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_notes`
--
ALTER TABLE `chat_notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_status_logs`
--
ALTER TABLE `chat_status_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_tickets`
--
ALTER TABLE `chat_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_ticket_logs`
--
ALTER TABLE `chat_ticket_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_contact_group`
--
ALTER TABLE `contact_contact_group`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_fields`
--
ALTER TABLE `contact_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_groups`
--
ALTER TABLE `contact_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `languages`
--
ALTER TABLE `languages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organization_api_keys`
--
ALTER TABLE `organization_api_keys`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seeder_histories`
--
ALTER TABLE `seeder_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_rates`
--
ALTER TABLE `tax_rates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_invites`
--
ALTER TABLE `team_invites`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `templates`
--
ALTER TABLE `templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_categories`
--
ALTER TABLE `ticket_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ticket_comments`
--
ALTER TABLE `ticket_comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contact_contact_group`
--
ALTER TABLE `contact_contact_group`
  ADD CONSTRAINT `contact_contact_group_contact_group_id_foreign` FOREIGN KEY (`contact_group_id`) REFERENCES `contact_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contact_contact_group_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `teams_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `teams_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  ADD CONSTRAINT `teams_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `team_invites`
--
ALTER TABLE `team_invites`
  ADD CONSTRAINT `team_invites_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `team_invites_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);

--
-- Constraints for table `templates`
--
ALTER TABLE `templates`
  ADD CONSTRAINT `templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `templates_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tickets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`),
  ADD CONSTRAINT `tickets_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD CONSTRAINT `ticket_comments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
