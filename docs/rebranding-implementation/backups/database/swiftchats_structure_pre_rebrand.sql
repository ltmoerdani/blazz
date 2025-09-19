-- MySQL dump 10.13  Distrib 9.3.0, for macos15.2 (arm64)
--
-- Host: localhost    Database: swiftchats
-- ------------------------------------------------------
-- Server version	9.3.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `addons`
--

DROP TABLE IF EXISTS `addons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `license` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_plan_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `update_available` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auto_replies`
--

DROP TABLE IF EXISTS `auto_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auto_replies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `match_criteria` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int NOT NULL,
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auto_replies_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_credits`
--

DROP TABLE IF EXISTS `billing_credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_credits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_credits_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_debits`
--

DROP TABLE IF EXISTS `billing_debits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_debits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_debits_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_invoices`
--

DROP TABLE IF EXISTS `billing_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `subtotal` decimal(19,4) NOT NULL,
  `coupon_id` int DEFAULT NULL,
  `coupon_amount` decimal(23,2) DEFAULT '0.00',
  `tax` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
  `tax_type` enum('inclusive','exclusive') COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_invoices_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_items`
--

DROP TABLE IF EXISTS `billing_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `billing_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interval` int NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_payments`
--

DROP TABLE IF EXISTS `billing_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int NOT NULL,
  `processor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_payments_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_tax_rates`
--

DROP TABLE IF EXISTS `billing_tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_tax_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_id` int NOT NULL,
  `rate` decimal(19,4) NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_tax_rates_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_transactions`
--

DROP TABLE IF EXISTS `billing_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int NOT NULL,
  `entity_type` enum('payment','invoice','credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_transactions_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blog_authors`
--

DROP TABLE IF EXISTS `blog_authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_authors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `deleted` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_authors_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blog_categories`
--

DROP TABLE IF EXISTS `blog_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_categories_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blog_posts`
--

DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int NOT NULL,
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_id` int NOT NULL,
  `is_featured` tinyint NOT NULL DEFAULT '0',
  `published` int NOT NULL DEFAULT '0',
  `deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `publish_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_posts_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blog_tags`
--

DROP TABLE IF EXISTS `blog_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_tags_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaign_log_retries`
--

DROP TABLE IF EXISTS `campaign_log_retries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_log_retries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_log_id` bigint unsigned NOT NULL,
  `chat_id` bigint unsigned DEFAULT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaign_logs`
--

DROP TABLE IF EXISTS `campaign_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int NOT NULL,
  `contact_id` int NOT NULL,
  `chat_id` int DEFAULT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','success','failed','ongoing') COLLATE utf8mb4_unicode_ci NOT NULL,
  `retry_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id` int NOT NULL,
  `contact_group_id` int NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `scheduled_at` datetime DEFAULT NULL,
  `created_by` int NOT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_logs`
--

DROP TABLE IF EXISTS `chat_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int NOT NULL,
  `entity_type` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_media`
--

DROP TABLE IF EXISTS `chat_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` enum('local','amazon') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_notes`
--

DROP TABLE IF EXISTS `chat_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_status_logs`
--

DROP TABLE IF EXISTS `chat_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` int NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_ticket_logs`
--

DROP TABLE IF EXISTS `chat_ticket_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_ticket_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_tickets`
--

DROP TABLE IF EXISTS `chat_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int NOT NULL,
  `assigned_to` int DEFAULT NULL,
  `status` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_tickets_contact_id_index` (`contact_id`),
  KEY `idx_chat_tickets_contact_assigned_to_status` (`contact_id`,`assigned_to`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chats`
--

DROP TABLE IF EXISTS `chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int NOT NULL,
  `wam_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_id` int NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `type` enum('inbound','outbound') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_id` int DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chats_uuid_unique` (`uuid`),
  KEY `chats_contact_id_index` (`contact_id`),
  KEY `chats_created_at_index` (`created_at`),
  KEY `idx_chats_contact_org_deleted_at` (`contact_id`,`organization_id`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_contact_group`
--

DROP TABLE IF EXISTS `contact_contact_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_contact_group` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` bigint unsigned NOT NULL,
  `contact_group_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_contact_group_contact_id_foreign` (`contact_id`),
  KEY `contact_contact_group_contact_group_id_foreign` (`contact_group_id`),
  CONSTRAINT `contact_contact_group_contact_group_id_foreign` FOREIGN KEY (`contact_group_id`) REFERENCES `contact_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_contact_group_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_fields`
--

DROP TABLE IF EXISTS `contact_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` bigint unsigned DEFAULT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `required` tinyint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_groups`
--

DROP TABLE IF EXISTS `contact_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_groups_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int NOT NULL,
  `first_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latest_chat_created_at` timestamp NULL DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `contact_group_id` int DEFAULT NULL,
  `is_favorite` tinyint NOT NULL DEFAULT '0',
  `ai_assistance_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contacts_uuid_unique` (`uuid`),
  KEY `contacts_organization_id_index` (`organization_id`),
  KEY `contacts_deleted_at_index` (`deleted_at`),
  KEY `contacts_latest_chat_created_at_index` (`latest_chat_created_at`),
  KEY `idx_contacts_first_name` (`first_name`),
  KEY `idx_contacts_last_name` (`last_name`),
  KEY `idx_contacts_email` (`email`),
  KEY `idx_contacts_phone` (`phone`),
  FULLTEXT KEY `fulltext_contacts_name_email_phone` (`first_name`,`last_name`,`phone`,`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `quantity_redeemed` int DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint unsigned NOT NULL,
  `source` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `embeddings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `status` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documents_uuid_unique` (`uuid`),
  CONSTRAINT `documents_chk_1` CHECK (json_valid(`embeddings`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `recipient` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('queued','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `attempts` int NOT NULL DEFAULT '0',
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_logs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_templates`
--

DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` blob NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `faqs`
--

DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faqs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question` text COLLATE utf8mb4_unicode_ci,
  `answer` text COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_available_at_index` (`queue`,`available_at`),
  KEY `jobs_attempts_index` (`attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `languages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_rtl` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actions` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notifications_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `organization_api_keys`
--

DROP TABLE IF EXISTS `organization_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_api_keys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organization_api_keys_token_unique` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `timezone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organizations_uuid_unique` (`uuid`),
  CONSTRAINT `organizations_chk_1` CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_gateways`
--

DROP TABLE IF EXISTS `payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_gateways` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` int NOT NULL DEFAULT '0',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `module` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `role_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_uuid_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `seeder_histories`
--

DROP TABLE IF EXISTS `seeder_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seeder_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seeder_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seeder_histories_seeder_name_unique` (`seeder_name`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscription_plans`
--

DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(23,2) NOT NULL,
  `period` enum('monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','deleted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_plans_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint unsigned NOT NULL,
  `plan_id` bigint unsigned DEFAULT NULL,
  `payment_details` text COLLATE utf8mb4_unicode_ci,
  `start_date` timestamp NULL DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `status` enum('trial','active') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trial',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscriptions_uuid_unique` (`uuid`),
  KEY `subscriptions_organization_id_foreign` (`organization_id`),
  CONSTRAINT `subscriptions_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tax_rates`
--

DROP TABLE IF EXISTS `tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `team_invites`
--

DROP TABLE IF EXISTS `team_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_invites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `email` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invited_by` bigint unsigned NOT NULL,
  `expire_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `team_invites_organization_id_foreign` (`organization_id`),
  KEY `team_invites_invited_by_foreign` (`invited_by`),
  CONSTRAINT `team_invites_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`),
  CONSTRAINT `team_invites_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` enum('owner','manager','agent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manager',
  `status` enum('active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_uuid_unique` (`uuid`),
  KEY `teams_organization_id_foreign` (`organization_id`),
  KEY `teams_user_id_foreign` (`user_id`),
  KEY `teams_created_by_foreign` (`created_by`),
  CONSTRAINT `teams_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `teams_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `teams_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `templates`
--

DROP TABLE IF EXISTS `templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint unsigned NOT NULL,
  `meta_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `templates_organization_id_foreign` (`organization_id`),
  KEY `templates_created_by_foreign` (`created_by`),
  CONSTRAINT `templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `templates_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_categories`
--

DROP TABLE IF EXISTS `ticket_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_comments`
--

DROP TABLE IF EXISTS `ticket_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `message` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_comments_ticket_id_foreign` (`ticket_id`),
  KEY `ticket_comments_user_id_foreign` (`user_id`),
  CONSTRAINT `ticket_comments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `subject` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('critical','high','medium','low') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `status` enum('open','pending','resolved','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `closed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tickets_user_id_foreign` (`user_id`),
  KEY `tickets_category_id_foreign` (`category_id`),
  KEY `tickets_assigned_to_foreign` (`assigned_to`),
  KEY `tickets_closed_by_foreign` (`closed_by`),
  CONSTRAINT `tickets_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `tickets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`),
  CONSTRAINT `tickets_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `facebook_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `phone` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tfa_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tfa` tinyint NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '1',
  `meta` text COLLATE utf8mb4_unicode_ci,
  `plan` text COLLATE utf8mb4_unicode_ci,
  `plan_id` bigint unsigned DEFAULT NULL,
  `will_expire` date DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_deleted_at_unique` (`email`,`deleted_at`),
  UNIQUE KEY `users_facebook_id_unique` (`facebook_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-19  9:14:14
