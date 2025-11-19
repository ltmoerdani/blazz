-- MySQL dump 10.13  Distrib 5.7.39, for osx11.0 (x86_64)
--
-- Host: localhost    Database: blazz
-- ------------------------------------------------------
-- Server version	5.7.39

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
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
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `addons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `license` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_plan_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `update_available` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addons`
--

LOCK TABLES `addons` WRITE;
/*!40000 ALTER TABLE `addons` DISABLE KEYS */;
INSERT INTO `addons` VALUES (1,'a2da2452-8241-4381-9021-6516e50c77e6','chat','Embedded Signup','whatsapp.png','An Embedded Signup add-on allows app users to register using their WhatsApp account.',NULL,NULL,NULL,0,0,0,0,'2025-09-26 09:16:59','2025-09-26 09:16:59'),(2,'72126f5a-1401-4301-945f-9e34bdddca4b','recaptcha','Google Recaptcha','google_recaptcha.png','Google reCAPTCHA enhances website security by preventing spam and abusive activities.','{\"input_fields\": [{\"element\": \"input\", \"type\": \"password\", \"name\": \"recaptcha_site_key\", \"label\": \"Recaptcha site key\", \"class\": \"col-span-2\"}, {\"element\": \"input\", \"type\": \"password\", \"name\": \"recaptcha_secret_key\", \"label\": \"Recaptcha secret key\", \"class\": \"col-span-2\"}, {\"element\": \"toggle\", \"type\": \"checkbox\", \"name\": \"recaptcha_active\", \"label\": \"Activate recaptcha\", \"class\": \"col-span-2\"}]}',NULL,NULL,0,0,1,0,'2025-09-26 09:16:59','2025-09-26 09:16:59'),(3,'db8f1840-7033-4be5-beef-67623c78b6a3','analytics','Google Analytics','google_analytics.png','Google Analytics tracks website performance and provides valuable insights for optimization.','{\"input_fields\": [{\"element\": \"input\", \"type\": \"text\", \"name\": \"google_analytics_tracking_id\", \"label\": \"Google analytics tracking ID\", \"class\": \"col-span-2\"}]}',NULL,NULL,0,0,1,0,'2025-09-26 09:16:59','2025-09-26 09:16:59'),(4,'404ea04c-49d4-487f-954d-5e1da4f5e6d3','maps','Google Maps','google_maps.png','Google Maps provides interactive maps for whatsapp messages.','{\"input_fields\": [{\"element\": \"input\", \"type\": \"text\", \"name\": \"google_maps_api_key\", \"label\": \"Google maps API key\", \"class\": \"col-span-2\"}]}',NULL,NULL,0,0,1,0,'2025-09-26 09:16:59','2025-09-26 09:16:59'),(5,'d022baae-f447-4b15-895d-0c8e75a75936','authentication','Google Authenticator','google-auth.png','Two-factor authentication using Google Authenticator','{\"type\":\"2fa\"}',NULL,'1.0.0',0,0,1,1,'2025-09-29 06:39:01','2025-09-29 06:39:01');
/*!40000 ALTER TABLE `addons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_data` json DEFAULT NULL,
  `status_code` int(11) DEFAULT NULL,
  `response_size` bigint(20) DEFAULT NULL,
  `execution_time` decimal(10,3) DEFAULT NULL,
  `memory_usage` bigint(20) DEFAULT NULL,
  `success` tinyint(1) DEFAULT NULL,
  `event_result` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_organization_id_created_at_index` (`organization_id`,`created_at`),
  KEY `audit_logs_ip_address_created_at_index` (`ip_address`,`created_at`),
  KEY `audit_logs_event_type_created_at_index` (`event_type`,`created_at`),
  KEY `audit_logs_success_created_at_index` (`success`,`created_at`),
  KEY `audit_logs_event_type_index` (`event_type`),
  KEY `audit_logs_endpoint_index` (`endpoint`),
  KEY `audit_logs_ip_address_index` (`ip_address`),
  KEY `audit_logs_user_id_index` (`user_id`),
  KEY `audit_logs_organization_id_index` (`organization_id`),
  KEY `audit_logs_session_id_index` (`session_id`),
  KEY `audit_logs_status_code_index` (`status_code`),
  KEY `audit_logs_success_index` (`success`),
  KEY `audit_logs_event_result_index` (`event_result`),
  KEY `audit_logs_request_id_index` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES ('req_68d6b62330085_8296','req_68d6b62330085_8296','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',NULL,NULL,'0Q7nARKdL8GuMr4krBnoV4c4gqUBwQxEw9HLntlZ','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',500,1297923,587.033,37748736,0,'server_error','2025-09-26 08:49:55','2025-09-26 08:49:55'),('req_68d6b627c2176_9755','req_68d6b627c2176_9755','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',NULL,NULL,'0Q7nARKdL8GuMr4krBnoV4c4gqUBwQxEw9HLntlZ','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',500,1297927,315.927,37748736,0,'server_error','2025-09-26 08:49:59','2025-09-26 08:50:00'),('req_68d6bb0654fc8_3423','req_68d6bb0654fc8_3423','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',NULL,NULL,'0Q7nARKdL8GuMr4krBnoV4c4gqUBwQxEw9HLntlZ','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',500,1297925,465.277,37748736,0,'server_error','2025-09-26 09:10:46','2025-09-26 09:10:46'),('req_68d6bbaa8ef08_3863','req_68d6bbaa8ef08_3863','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',NULL,NULL,'0Q7nARKdL8GuMr4krBnoV4c4gqUBwQxEw9HLntlZ','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,102.118,33554432,0,'unknown','2025-09-26 09:13:30','2025-09-26 09:13:30'),('req_68d6c33b24f7e_7818','req_68d6c33b24f7e_7818','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',NULL,NULL,'0Q7nARKdL8GuMr4krBnoV4c4gqUBwQxEw9HLntlZ','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,54.924,33554432,0,'unknown','2025-09-26 09:45:47','2025-09-26 09:45:47'),('req_68d6c4f875bb1_4263','req_68d6c4f875bb1_4263','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',NULL,NULL,'0Q7nARKdL8GuMr4krBnoV4c4gqUBwQxEw9HLntlZ','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,370,532.585,33554432,0,'unknown','2025-09-26 09:53:12','2025-09-26 09:53:13'),('req_68d6c5f129ec1_1397','req_68d6c5f129ec1_1397','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',NULL,NULL,'FR0y9ddfmoA9Q7nwXfFetN8bgGFksCJoX5RPuruW','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,370,517.327,33554432,0,'unknown','2025-09-26 09:57:21','2025-09-26 09:57:21'),('req_68d6c5fc886bb_9149','req_68d6c5fc886bb_9149','request_attempt','user.organization.store','POST','http://127.0.0.1:8000/organization','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,NULL,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/select-organization\", \"expects_json\": false, \"input_summary\": {\"name\": \"Personal\", \"email\": \"ltmoerdani@yahoo.com\", \"create_user\": 0}, \"accept_language\": \"en-US,en;q=0.6\"}',302,370,60.173,33554432,0,'unknown','2025-09-26 09:57:32','2025-09-26 09:57:32'),('req_68d6c82988326_3971','req_68d6c82988326_3971','request_attempt','channels.generate-qr','POST','http://127.0.0.1:8000/settings/whatsapp/generate-qr','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,1,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": false, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp\", \"expects_json\": true, \"input_summary\": {\"channel_id\": \"default\", \"organization\": 1, \"organization_id\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',400,369,7341.476,35651584,0,'client_error','2025-09-26 10:06:49','2025-09-26 10:06:56'),('req_68d6c85d65968_2558','req_68d6c85d65968_2558','request_attempt','channels.generate-qr','POST','http://127.0.0.1:8000/settings/whatsapp/generate-qr','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,1,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": false, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp\", \"expects_json\": true, \"input_summary\": {\"channel_id\": \"default\", \"organization\": 1, \"organization_id\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',200,309,8410.667,33554432,1,'success','2025-09-26 10:07:41','2025-09-26 10:07:49'),('req_68d6c9d772796_5642','req_68d6c9d772796_5642','request_attempt','channels.generate-qr','POST','http://127.0.0.1:8000/settings/whatsapp/generate-qr','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,1,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": false, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp\", \"expects_json\": true, \"input_summary\": {\"channel_id\": \"default\", \"organization\": 1, \"organization_id\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',200,309,8826.236,33554432,1,'success','2025-09-26 10:13:59','2025-09-26 10:14:08'),('req_68d6cada58457_8150','req_68d6cada58457_8150','request_attempt','channels.generate-qr','POST','http://127.0.0.1:8000/settings/whatsapp/generate-qr','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,1,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": false, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp\", \"expects_json\": true, \"input_summary\": {\"channel_id\": \"default\", \"organization\": 1, \"organization_id\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',200,307,99.800,33554432,1,'success','2025-09-26 10:18:18','2025-09-26 10:18:18'),('req_68d6cc004b465_1321','req_68d6cc004b465_1321','request_attempt','channels.generate-qr','POST','http://127.0.0.1:8000/settings/whatsapp/generate-qr','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,1,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": false, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp\", \"expects_json\": true, \"input_summary\": {\"channel_id\": \"default\", \"organization\": 1, \"organization_id\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',200,307,96.699,33554432,1,'success','2025-09-26 10:23:12','2025-09-26 10:23:12'),('req_68d6cc19071e2_2671','req_68d6cc19071e2_2671','request_attempt','channels.generate-qr','POST','http://127.0.0.1:8000/settings/whatsapp/generate-qr','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,1,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": false, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp\", \"expects_json\": true, \"input_summary\": {\"channel_id\": \"default\", \"organization\": 1, \"organization_id\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',200,307,82.876,33554432,1,'success','2025-09-26 10:23:37','2025-09-26 10:23:37'),('req_68d6cc6f578a5_5929','req_68d6cc6f578a5_5929','request_attempt','channels.generate-qr','POST','http://127.0.0.1:8000/settings/whatsapp/generate-qr','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,1,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": false, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp\", \"expects_json\": true, \"input_summary\": {\"channel_id\": \"default\", \"organization\": 1, \"organization_id\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',200,307,90.895,33554432,1,'success','2025-09-26 10:25:03','2025-09-26 10:25:03'),('req_68d6cddd015a2_2288','req_68d6cddd015a2_2288','request_attempt','channels.generate-qr','POST','http://127.0.0.1:8000/settings/whatsapp/generate-qr','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,1,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": false, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp\", \"expects_json\": true, \"input_summary\": {\"channel_id\": \"default\", \"organization\": 1, \"organization_id\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',200,306,8307.526,33554432,1,'success','2025-09-26 10:31:09','2025-09-26 10:31:17'),('req_68d6cfb4502b8_6286','req_68d6cfb4502b8_6286','request_attempt','channels.generate-qr','POST','http://127.0.0.1:8000/settings/whatsapp/generate-qr','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,1,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": false, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp\", \"expects_json\": true, \"input_summary\": {\"channel_id\": \"default\", \"organization\": 1, \"organization_id\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',400,370,27105.097,35651584,0,'client_error','2025-09-26 10:39:00','2025-09-26 10:39:27'),('req_68d6d270924d6_2505','req_68d6d270924d6_2505','request_attempt','channels.generate-qr','POST','http://127.0.0.1:8000/settings/whatsapp/generate-qr','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',1,1,'krGfbSOgHDzCSTjOfegYgBj1AVNVk1NTg7itYgbv','{\"is_ajax\": false, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp\", \"expects_json\": true, \"input_summary\": {\"channel_id\": \"default\", \"organization\": 1, \"organization_id\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',200,307,138.485,33554432,1,'success','2025-09-26 10:50:40','2025-09-26 10:50:40'),('req_68da846085daf_4555','req_68da846085daf_4555','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'CIUWcuT86bXAl3zdDQN1HBwpopsbttSMPa97Zmk8','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',500,1466180,5941.774,44040192,0,'server_error','2025-09-29 06:06:40','2025-09-29 06:06:46'),('req_68da84e62c6e9_1592','req_68da84e62c6e9_1592','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'CIUWcuT86bXAl3zdDQN1HBwpopsbttSMPa97Zmk8','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"admin@demo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,66.870,29360128,0,'unknown','2025-09-29 06:08:54','2025-09-29 06:08:54'),('req_68da85573e0e4_8749','req_68da85573e0e4_8749','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'CIUWcuT86bXAl3zdDQN1HBwpopsbttSMPa97Zmk8','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"admin@demo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,52.756,29360128,0,'unknown','2025-09-29 06:10:47','2025-09-29 06:10:47'),('req_68da855b214f5_8447','req_68da855b214f5_8447','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'CIUWcuT86bXAl3zdDQN1HBwpopsbttSMPa97Zmk8','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',500,1466178,5314.987,44040192,0,'server_error','2025-09-29 06:10:51','2025-09-29 06:10:56'),('req_68da8b154b1c3_8258','req_68da8b154b1c3_8258','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'CIUWcuT86bXAl3zdDQN1HBwpopsbttSMPa97Zmk8','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"admin@demo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,110.493,29360128,0,'unknown','2025-09-29 06:35:17','2025-09-29 06:35:17'),('req_68da8b1c9df7a_4256','req_68da8b1c9df7a_4256','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'CIUWcuT86bXAl3zdDQN1HBwpopsbttSMPa97Zmk8','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',500,1466176,5330.191,44040192,0,'server_error','2025-09-29 06:35:24','2025-09-29 06:35:29'),('req_68da8c658d7f8_8310','req_68da8c658d7f8_8310','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'CIUWcuT86bXAl3zdDQN1HBwpopsbttSMPa97Zmk8','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"admin@demo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,294.732,29360128,0,'unknown','2025-09-29 06:40:53','2025-09-29 06:40:53'),('req_68da8c75d75dc_6334','req_68da8c75d75dc_6334','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'CIUWcuT86bXAl3zdDQN1HBwpopsbttSMPa97Zmk8','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"admin@demo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,394,501.746,29360128,0,'unknown','2025-09-29 06:41:09','2025-09-29 06:41:10'),('req_68da8c78937ee_3637','req_68da8c78937ee_3637','request_attempt','unknown','GET','http://127.0.0.1:8000/admin/dashboard','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,NULL,'nbokmScUMKlwbG4snnkuDdWxelVdL7kle6YGwqc2','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"accept_language\": \"en-US,en;q=0.6\"}',200,9007,58.648,29360128,1,'success','2025-09-29 06:41:12','2025-09-29 06:41:12'),('req_68da8c837d626_6349','req_68da8c837d626_6349','request_attempt','unknown','GET','http://127.0.0.1:8000/admin/dashboard','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,NULL,'nbokmScUMKlwbG4snnkuDdWxelVdL7kle6YGwqc2','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/admin/dashboard\", \"expects_json\": false, \"accept_language\": \"en-US,en;q=0.6\"}',200,9037,48.158,29360128,1,'success','2025-09-29 06:41:23','2025-09-29 06:41:23'),('req_68da8c8de7715_3277','req_68da8c8de7715_3277','request_attempt','users.index','GET','http://127.0.0.1:8000/admin/users','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,NULL,'nbokmScUMKlwbG4snnkuDdWxelVdL7kle6YGwqc2','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/admin/dashboard\", \"expects_json\": false, \"accept_language\": \"en-US,en;q=0.6\"}',200,10004,35.904,29360128,1,'success','2025-09-29 06:41:33','2025-09-29 06:41:33'),('req_68da8d700b4f5_1192','req_68da8d700b4f5_1192','request_attempt','users.index','GET','http://127.0.0.1:8000/admin/users','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,NULL,'nbokmScUMKlwbG4snnkuDdWxelVdL7kle6YGwqc2','{\"is_ajax\": false, \"referer\": null, \"expects_json\": false, \"accept_language\": \"en-US,en;q=0.6\"}',200,13119,51.869,29360128,1,'success','2025-09-29 06:45:20','2025-09-29 06:45:20'),('req_68da8d7ad67f9_6696','req_68da8d7ad67f9_6696','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'nbokmScUMKlwbG4snnkuDdWxelVdL7kle6YGwqc2','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"admin@demo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,394,526.956,29360128,0,'unknown','2025-09-29 06:45:30','2025-09-29 06:45:31'),('req_68da8d7c2c398_9707','req_68da8d7c2c398_9707','request_attempt','unknown','GET','http://127.0.0.1:8000/admin/dashboard','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,NULL,'Ugc7I6cDrzkKaj9598BzKMDWsDsybTN207cTn8tu','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"accept_language\": \"en-US,en;q=0.6\"}',200,9037,55.007,29360128,1,'success','2025-09-29 06:45:32','2025-09-29 06:45:32'),('req_68da8d81b7a6e_7669','req_68da8d81b7a6e_7669','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'Ugc7I6cDrzkKaj9598BzKMDWsDsybTN207cTn8tu','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,370,512.356,29360128,0,'unknown','2025-09-29 06:45:37','2025-09-29 06:45:38');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `authentication_events`
--

DROP TABLE IF EXISTS `authentication_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `authentication_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` enum('login_attempt','login_success','login_failure','logout','password_reset','account_locked') COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `failure_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suspicious` tinyint(1) NOT NULL DEFAULT '0',
  `additional_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `auth_email_type_created_idx` (`email`,`event_type`,`created_at`),
  KEY `auth_ip_type_created_idx` (`ip_address`,`event_type`,`created_at`),
  KEY `auth_suspicious_created_idx` (`suspicious`,`created_at`),
  KEY `auth_user_type_created_idx` (`user_id`,`event_type`,`created_at`),
  KEY `authentication_events_audit_id_index` (`audit_id`),
  KEY `authentication_events_event_type_index` (`event_type`),
  KEY `authentication_events_email_index` (`email`),
  KEY `authentication_events_user_id_index` (`user_id`),
  KEY `authentication_events_ip_address_index` (`ip_address`),
  KEY `authentication_events_suspicious_index` (`suspicious`),
  KEY `authentication_events_organization_id_index` (`organization_id`),
  CONSTRAINT `authentication_events_audit_id_foreign` FOREIGN KEY (`audit_id`) REFERENCES `audit_logs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `authentication_events`
--

LOCK TABLES `authentication_events` WRITE;
/*!40000 ALTER TABLE `authentication_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `authentication_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auto_replies`
--

DROP TABLE IF EXISTS `auto_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auto_replies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `match_criteria` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int(11) NOT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auto_replies_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auto_replies`
--

LOCK TABLES `auto_replies` WRITE;
/*!40000 ALTER TABLE `auto_replies` DISABLE KEYS */;
/*!40000 ALTER TABLE `auto_replies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing_credits`
--

DROP TABLE IF EXISTS `billing_credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_credits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_credits_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_credits`
--

LOCK TABLES `billing_credits` WRITE;
/*!40000 ALTER TABLE `billing_credits` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_credits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing_debits`
--

DROP TABLE IF EXISTS `billing_debits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_debits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_debits_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_debits`
--

LOCK TABLES `billing_debits` WRITE;
/*!40000 ALTER TABLE `billing_debits` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_debits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing_invoices`
--

DROP TABLE IF EXISTS `billing_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `subtotal` decimal(19,4) NOT NULL,
  `coupon_id` int(11) DEFAULT NULL,
  `coupon_amount` decimal(23,2) DEFAULT '0.00',
  `tax` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
  `tax_type` enum('inclusive','exclusive') COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_invoices_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_invoices`
--

LOCK TABLES `billing_invoices` WRITE;
/*!40000 ALTER TABLE `billing_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing_items`
--

DROP TABLE IF EXISTS `billing_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `billing_id` int(11) NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `interval` int(11) NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_items`
--

LOCK TABLES `billing_items` WRITE;
/*!40000 ALTER TABLE `billing_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing_payments`
--

DROP TABLE IF EXISTS `billing_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `processor` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_payments_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_payments`
--

LOCK TABLES `billing_payments` WRITE;
/*!40000 ALTER TABLE `billing_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing_tax_rates`
--

DROP TABLE IF EXISTS `billing_tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_tax_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `rate` decimal(19,4) NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_tax_rates_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_tax_rates`
--

LOCK TABLES `billing_tax_rates` WRITE;
/*!40000 ALTER TABLE `billing_tax_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_tax_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `billing_transactions`
--

DROP TABLE IF EXISTS `billing_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `entity_type` enum('payment','invoice','credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int(11) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_transactions_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_transactions`
--

LOCK TABLES `billing_transactions` WRITE;
/*!40000 ALTER TABLE `billing_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blocked_ips`
--

DROP TABLE IF EXISTS `blocked_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blocked_ips` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `blocked_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blocked_ips_ip_address_unique` (`ip_address`),
  KEY `blocked_ips_expires_at_blocked_at_index` (`expires_at`,`blocked_at`),
  KEY `blocked_ips_ip_address_index` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blocked_ips`
--

LOCK TABLES `blocked_ips` WRITE;
/*!40000 ALTER TABLE `blocked_ips` DISABLE KEYS */;
/*!40000 ALTER TABLE `blocked_ips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_authors`
--

DROP TABLE IF EXISTS `blog_authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_authors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `created_by` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_authors_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_authors`
--

LOCK TABLES `blog_authors` WRITE;
/*!40000 ALTER TABLE `blog_authors` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_authors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_categories`
--

DROP TABLE IF EXISTS `blog_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_categories_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_categories`
--

LOCK TABLES `blog_categories` WRITE;
/*!40000 ALTER TABLE `blog_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_posts`
--

DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `tags` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `is_featured` tinyint(4) NOT NULL DEFAULT '0',
  `published` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `created_by` int(11) NOT NULL,
  `publish_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_posts_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_posts`
--

LOCK TABLES `blog_posts` WRITE;
/*!40000 ALTER TABLE `blog_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_tags`
--

DROP TABLE IF EXISTS `blog_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_tags_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_tags`
--

LOCK TABLES `blog_tags` WRITE;
/*!40000 ALTER TABLE `blog_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaign_log_retries`
--

DROP TABLE IF EXISTS `campaign_log_retries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_log_retries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_log_id` bigint(20) unsigned NOT NULL,
  `chat_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_log_retries`
--

LOCK TABLES `campaign_log_retries` WRITE;
/*!40000 ALTER TABLE `campaign_log_retries` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign_log_retries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaign_logs`
--

DROP TABLE IF EXISTS `campaign_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `chat_id` int(11) DEFAULT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','success','failed','ongoing') COLLATE utf8mb4_unicode_ci NOT NULL,
  `retry_count` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_logs`
--

LOCK TABLES `campaign_logs` WRITE;
/*!40000 ALTER TABLE `campaign_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id` int(11) NOT NULL,
  `contact_group_id` int(11) NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `scheduled_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaigns`
--

LOCK TABLES `campaigns` WRITE;
/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `channel_audits`
--

DROP TABLE IF EXISTS `channel_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `channel_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `channel_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_from` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_to` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `request_payload` json DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_audits_audit_id_unique` (`audit_id`),
  KEY `channel_audits_organization_id_channel_id_created_at_index` (`organization_id`,`channel_id`,`created_at`),
  KEY `channel_audits_action_created_at_index` (`action`,`created_at`),
  KEY `channel_audits_created_at_index` (`created_at`),
  KEY `channel_audits_organization_id_index` (`organization_id`),
  KEY `channel_audits_channel_id_index` (`channel_id`),
  KEY `channel_audits_action_index` (`action`),
  KEY `channel_audits_user_id_index` (`user_id`),
  CONSTRAINT `channel_audits_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `channel_audits`
--

LOCK TABLES `channel_audits` WRITE;
/*!40000 ALTER TABLE `channel_audits` DISABLE KEYS */;
/*!40000 ALTER TABLE `channel_audits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_logs`
--

DROP TABLE IF EXISTS `chat_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `entity_type` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int(11) NOT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_logs`
--

LOCK TABLES `chat_logs` WRITE;
/*!40000 ALTER TABLE `chat_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_media`
--

DROP TABLE IF EXISTS `chat_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` enum('local','amazon') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_media`
--

LOCK TABLES `chat_media` WRITE;
/*!40000 ALTER TABLE `chat_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_notes`
--

DROP TABLE IF EXISTS `chat_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id` bigint(20) unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `created_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_notes`
--

LOCK TABLES `chat_notes` WRITE;
/*!40000 ALTER TABLE `chat_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_status_logs`
--

DROP TABLE IF EXISTS `chat_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_status_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_status_logs`
--

LOCK TABLES `chat_status_logs` WRITE;
/*!40000 ALTER TABLE `chat_status_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_status_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_ticket_logs`
--

DROP TABLE IF EXISTS `chat_ticket_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_ticket_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_ticket_logs`
--

LOCK TABLES `chat_ticket_logs` WRITE;
/*!40000 ALTER TABLE `chat_ticket_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_ticket_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_tickets`
--

DROP TABLE IF EXISTS `chat_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_tickets_contact_id_index` (`contact_id`),
  KEY `idx_chat_tickets_contact_assigned_to_status` (`contact_id`,`assigned_to`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_tickets`
--

LOCK TABLES `chat_tickets` WRITE;
/*!40000 ALTER TABLE `chat_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chats`
--

DROP TABLE IF EXISTS `chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `wam_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_id` int(11) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('inbound','outbound') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_id` int(11) DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chats_uuid_unique` (`uuid`),
  KEY `chats_contact_id_index` (`contact_id`),
  KEY `chats_created_at_index` (`created_at`),
  KEY `idx_chats_contact_org_deleted_at` (`contact_id`,`organization_id`,`deleted_at`),
  KEY `idx_chat_timeline_performance` (`organization_id`,`created_at`,`type`),
  KEY `idx_chat_participants_opt` (`organization_id`,`contact_id`,`status`),
  KEY `idx_chat_media_timeline` (`media_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chats`
--

LOCK TABLES `chats` WRITE;
/*!40000 ALTER TABLE `chats` DISABLE KEYS */;
/*!40000 ALTER TABLE `chats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_contact_group`
--

DROP TABLE IF EXISTS `contact_contact_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_contact_group` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` bigint(20) unsigned NOT NULL,
  `contact_group_id` bigint(20) unsigned NOT NULL,
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
-- Dumping data for table `contact_contact_group`
--

LOCK TABLES `contact_contact_group` WRITE;
/*!40000 ALTER TABLE `contact_contact_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_contact_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_fields`
--

DROP TABLE IF EXISTS `contact_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_fields` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `required` tinyint(3) unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_fields`
--

LOCK TABLES `contact_fields` WRITE;
/*!40000 ALTER TABLE `contact_fields` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_fields` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_groups`
--

DROP TABLE IF EXISTS `contact_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_groups_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_groups`
--

LOCK TABLES `contact_groups` WRITE;
/*!40000 ALTER TABLE `contact_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `first_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latest_chat_created_at` timestamp NULL DEFAULT NULL,
  `avatar` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `contact_group_id` int(11) DEFAULT NULL,
  `is_favorite` tinyint(4) NOT NULL DEFAULT '0',
  `ai_assistance_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int(11) NOT NULL,
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
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `quantity_redeemed` int(11) DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_access_logs`
--

DROP TABLE IF EXISTS `data_access_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_access_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `target_user_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `data_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accessed_fields` json DEFAULT NULL,
  `purpose` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consent_given` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `data_target_type_created_idx` (`target_user_id`,`data_type`,`created_at`),
  KEY `data_org_access_created_idx` (`organization_id`,`access_type`,`created_at`),
  KEY `data_consent_created_idx` (`consent_given`,`created_at`),
  KEY `data_user_access_created_idx` (`user_id`,`access_type`,`created_at`),
  KEY `data_access_logs_audit_id_index` (`audit_id`),
  KEY `data_access_logs_user_id_index` (`user_id`),
  KEY `data_access_logs_target_user_id_index` (`target_user_id`),
  KEY `data_access_logs_organization_id_index` (`organization_id`),
  KEY `data_access_logs_data_type_index` (`data_type`),
  KEY `data_access_logs_access_type_index` (`access_type`),
  KEY `data_access_logs_consent_given_index` (`consent_given`),
  KEY `data_access_organization_created_idx` (`organization_id`,`data_type`,`created_at`),
  CONSTRAINT `data_access_logs_audit_id_foreign` FOREIGN KEY (`audit_id`) REFERENCES `audit_logs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_access_logs`
--

LOCK TABLES `data_access_logs` WRITE;
/*!40000 ALTER TABLE `data_access_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_access_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `device_activities`
--

DROP TABLE IF EXISTS `device_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `device_activities` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `activity_type` enum('login','logout','session_start','session_end','session_heartbeat','password_change','profile_update','organization_switch','whatsapp_connect','whatsapp_disconnect','whatsapp_session_restore','api_access','admin_access','settings_change','data_export','data_import','message_sent','message_received','chat_created','contact_added','failed_login','suspicious_activity','account_locked','security_alert') COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_description` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endpoint` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` enum('desktop','mobile','tablet','unknown') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `device_os` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_browser` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_country` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_city` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_lat` decimal(10,8) DEFAULT NULL,
  `location_lng` decimal(11,8) DEFAULT NULL,
  `status` enum('success','failed','blocked','warning') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `failure_reason` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'low',
  `is_suspicious` tinyint(1) NOT NULL DEFAULT '0',
  `security_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `device_activities_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `device_activities_organization_id_created_at_index` (`organization_id`,`created_at`),
  KEY `device_activities_activity_type_created_at_index` (`activity_type`,`created_at`),
  KEY `device_activities_device_id_user_id_index` (`device_id`,`user_id`),
  KEY `device_activities_ip_address_created_at_index` (`ip_address`,`created_at`),
  KEY `device_activities_status_risk_level_index` (`status`,`risk_level`),
  KEY `device_activities_is_suspicious_risk_level_index` (`is_suspicious`,`risk_level`),
  KEY `device_activities_request_id_index` (`request_id`),
  CONSTRAINT `device_activities_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `device_activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `device_activities`
--

LOCK TABLES `device_activities` WRITE;
/*!40000 ALTER TABLE `device_activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `device_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `source` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `embeddings` json DEFAULT NULL,
  `status` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documents_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `recipient` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('queued','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `attempts` int(11) NOT NULL DEFAULT '0',
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_logs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_logs`
--

LOCK TABLES `email_logs` WRITE;
/*!40000 ALTER TABLE `email_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_templates`
--

DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` blob NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_templates`
--

LOCK TABLES `email_templates` WRITE;
/*!40000 ALTER TABLE `email_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
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
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faqs`
--

DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faqs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question` text COLLATE utf8mb4_unicode_ci,
  `answer` text COLLATE utf8mb4_unicode_ci,
  `status` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faqs`
--

LOCK TABLES `faqs` WRITE;
/*!40000 ALTER TABLE `faqs` DISABLE KEYS */;
/*!40000 ALTER TABLE `faqs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_available_at_index` (`queue`,`available_at`),
  KEY `jobs_attempts_index` (`attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `languages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_rtl` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages`
--

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2024_03_20_050200_create_auto_replies_table',1),(2,'2024_03_20_050311_create_billing_credits_table',1),(3,'2024_03_20_050348_create_billing_debits_table',1),(4,'2024_03_20_050430_create_billing_invoices_table',1),(5,'2024_03_20_050508_create_billing_items_table',1),(6,'2024_03_20_050600_create_billing_payments_table',1),(7,'2024_03_20_050635_create_billing_tax_rates_table',1),(8,'2024_03_20_050711_create_billing_transactions_table',1),(9,'2024_03_20_050751_create_blog_authors_table',1),(10,'2024_03_20_050826_create_blog_categories_table',1),(11,'2024_03_20_050912_create_blog_posts_table',1),(12,'2024_03_20_050959_create_blog_tags_table',1),(13,'2024_03_20_051036_create_campaigns_table',1),(14,'2024_03_20_051111_create_campaign_logs_table',1),(15,'2024_03_20_051154_create_chats_table',1),(16,'2024_03_20_051253_create_chat_logs_table',1),(17,'2024_03_20_051336_create_chat_media_table',1),(18,'2024_03_20_051414_create_contacts_table',1),(19,'2024_03_20_051449_create_contact_groups_table',1),(20,'2024_03_20_051537_create_coupons_table',1),(21,'2024_03_20_051613_create_email_logs_table',1),(22,'2024_03_20_051655_create_email_templates_table',1),(23,'2024_03_20_051739_create_failed_jobs_table',1),(24,'2024_03_20_051807_create_faqs_table',1),(25,'2024_03_20_051847_create_jobs_table',1),(26,'2024_03_20_051919_create_modules_table',1),(27,'2024_03_20_051953_create_notifications_table',1),(28,'2024_03_20_052034_create_organizations_table',1),(29,'2024_03_20_052107_create_pages_table',1),(30,'2024_03_20_052141_create_password_reset_tokens_table',1),(31,'2024_03_20_052223_create_payment_gateways_table',1),(32,'2024_03_20_052338_create_reviews_table',1),(33,'2024_03_20_052401_create_users_table',1),(34,'2024_03_20_052430_create_roles_table',1),(35,'2024_03_20_052513_create_role_permissions_table',1),(36,'2024_03_20_052620_create_settings_table',1),(37,'2024_03_20_052654_create_subscriptions_table',1),(38,'2024_03_20_052731_create_subscription_plans_table',1),(39,'2024_03_20_052808_create_tax_rates_table',1),(40,'2024_03_20_052839_create_teams_table',1),(41,'2024_03_20_052914_create_team_invites_table',1),(42,'2024_03_20_052920_create_ticket_categories_table',1),(43,'2024_03_20_052956_create_templates_table',1),(44,'2024_03_20_053038_create_tickets_table',1),(45,'2024_03_20_053205_create_ticket_comments_table',1),(46,'2024_04_08_133150_create_organization_api_keys_table',1),(47,'2024_04_24_211852_create_languages',1),(48,'2024_04_27_155643_create_contact_fields_table',1),(49,'2024_04_27_160152_add_metadata_to_contacts_table',1),(50,'2024_05_11_052902_create_chat_notes_table',1),(51,'2024_05_11_052925_create_chat_tickets_table',1),(52,'2024_05_11_052940_create_chat_ticket_logs_table',1),(53,'2024_05_11_053846_rename_chat_logs_table',1),(54,'2024_05_11_054010_create_chat_logs_2_table',1),(55,'2024_05_11_063255_add_user_id_to_chats_table',1),(56,'2024_05_11_063540_add_role_to_team_invites_table',1),(57,'2024_05_11_063819_update_agent_role_to_teams_table',1),(58,'2024_05_11_064650_add_deleted_by_to_organization_api_keys_table',1),(59,'2024_05_11_065031_add_organization_id_to_tickets_table',1),(60,'2024_05_28_080331_make_password_nullable_in_users_table',1),(61,'2024_05_30_125859_modify_campaigns_table',1),(62,'2024_06_03_124254_create_addons_table',1),(63,'2024_06_07_040536_update_users_table_for_facebook_login',1),(64,'2024_06_07_040843_update_chat_media_table',1),(65,'2024_06_07_074903_add_soft_delete_to_teams_and_organizations',1),(66,'2024_06_09_155053_modify_billing_payments_table',1),(67,'2024_06_12_070820_modify_faqs_table',1),(68,'2024_07_04_053236_modify_amount_columns_in_billing_tables',1),(69,'2024_07_04_054143_modify_contacts_table_encoding',1),(70,'2024_07_09_011419_drop_seo_from_pages_table',1),(71,'2024_07_17_062442_allow_null_content_in_pages_table',1),(72,'2024_07_24_080535_add_latest_chat_created_at_to_contacts_table',1),(73,'2024_08_01_050752_add_ongoing_to_status_enum_in_campaign_logs_table',1),(74,'2024_08_08_130306_add_is_read_to_chats_table',1),(75,'2024_08_10_071237_create_documents_table',1),(76,'2024_10_16_201832_change_metadata_column_in_organizations_table',1),(77,'2024_11_12_101941_add_license_column_to_addons_table',1),(78,'2024_11_25_114450_add_version_and_update_needed_to_addons_table',1),(79,'2024_11_28_083453_add_tfa_secret_to_users_table',1),(80,'2024_11_29_070806_create_seeder_histories_table',1),(81,'2024_12_20_081118_add_is_plan_restricted_to_addons_table',1),(82,'2024_12_20_130829_add_is_active_table',1),(83,'2025_01_24_090926_add_index_to_chats_table',1),(84,'2025_01_24_091012_add_index_to_chat_tickets_table',1),(85,'2025_01_24_091043_add_index_to_contacts_first_name',1),(86,'2025_01_24_091115_add_fulltext_index_to_contacts_table',1),(87,'2025_01_29_071445_modify_status_column_in_chats_table',1),(88,'2025_02_21_084110_create_job_batches_table',1),(89,'2025_02_21_093829_add_queue_indexes',1),(90,'2025_04_02_085132_create_contact_contact_group_table',1),(91,'2025_05_01_045837_create_campaign_log_retries_table',1),(92,'2025_05_01_053318_add_retry_count_to_campaign_logs_table',1),(93,'2025_05_23_101200_add_rtl_to_languages_table',1),(94,'2025_09_18_102755_optimize_database_indexes_for_performance',1),(95,'2025_09_18_110851_create_audit_logs_table',1),(96,'2025_09_18_112313_create_missing_security_tables',1),(97,'2025_09_18_115536_fix_security_tables_schema',1),(98,'2025_09_24_060343_create_organization_channels_table',1),(99,'2025_09_24_070313_create_whatsapp_sessions_table',1),(100,'2025_09_24_074706_create_device_activities_table',1),(101,'2025_09_26_044513_create_channel_audits_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actions` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modules`
--

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
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
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_api_keys`
--

DROP TABLE IF EXISTS `organization_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organization_api_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organization_api_keys_token_unique` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_api_keys`
--

LOCK TABLES `organization_api_keys` WRITE;
/*!40000 ALTER TABLE `organization_api_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `organization_api_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_channels`
--

DROP TABLE IF EXISTS `organization_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organization_channels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `type` enum('meta','whatsapp_web') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'meta',
  `display_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('ACTIVE','DISCONNECTED','CONNECTING','ERROR') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DISCONNECTED',
  `credentials` json DEFAULT NULL COMMENT 'Encrypted credentials for connector',
  `limits` json DEFAULT NULL COMMENT 'Rate limits and quotas for channel',
  `metadata` json DEFAULT NULL COMMENT 'Additional channel configuration',
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organization_channels_uuid_unique` (`uuid`),
  KEY `organization_channels_organization_id_type_index` (`organization_id`,`type`),
  KEY `organization_channels_organization_id_status_index` (`organization_id`,`status`),
  KEY `organization_channels_phone_number_index` (`phone_number`),
  KEY `organization_channels_last_activity_at_index` (`last_activity_at`),
  CONSTRAINT `organization_channels_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_channels`
--

LOCK TABLES `organization_channels` WRITE;
/*!40000 ALTER TABLE `organization_channels` DISABLE KEYS */;
/*!40000 ALTER TABLE `organization_channels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `address` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `timezone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organizations_uuid_unique` (`uuid`),
  KEY `idx_org_creator_timeline` (`created_by`,`created_at`),
  KEY `idx_org_status_performance` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organizations`
--

LOCK TABLES `organizations` WRITE;
/*!40000 ALTER TABLE `organizations` DISABLE KEYS */;
INSERT INTO `organizations` VALUES (1,'de34a093-4ee8-49d9-8482-08dddbb20018','202509261657321YItP','Personal','active','{\"street\":null,\"city\":null,\"state\":null,\"zip\":null,\"country\":null}',NULL,NULL,1,NULL,NULL,'2025-09-26 09:57:32','2025-09-26 09:57:32');
/*!40000 ALTER TABLE `organizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_gateways`
--

DROP TABLE IF EXISTS `payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_gateways` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(4) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_gateways`
--

LOCK TABLES `payment_gateways` WRITE;
/*!40000 ALTER TABLE `payment_gateways` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_gateways` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `query_performance_logs`
--

DROP TABLE IF EXISTS `query_performance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `query_performance_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `query_hash` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `query_sql` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `execution_time` decimal(10,6) NOT NULL,
  `rows_examined` int(11) NOT NULL,
  `rows_sent` int(11) NOT NULL,
  `connection_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `controller_action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `query_bindings` json DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slow_queries` (`execution_time`,`executed_at`),
  KEY `idx_query_frequency` (`query_hash`,`executed_at`),
  KEY `query_performance_logs_query_hash_index` (`query_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `query_performance_logs`
--

LOCK TABLES `query_performance_logs` WRITE;
/*!40000 ALTER TABLE `query_performance_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `query_performance_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limit_violations`
--

DROP TABLE IF EXISTS `rate_limit_violations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limit_violations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `rate_limit_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT '1',
  `limit_threshold` int(11) NOT NULL,
  `window_duration` int(11) NOT NULL,
  `first_violation` timestamp NULL DEFAULT NULL,
  `last_violation` timestamp NULL DEFAULT NULL,
  `blocked` tinyint(1) NOT NULL DEFAULT '0',
  `block_expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rate_limit_ip_type_created_idx` (`ip_address`,`rate_limit_type`,`created_at`),
  KEY `rate_limit_blocked_expires_idx` (`blocked`,`block_expires_at`),
  KEY `rate_limit_last_violation_idx` (`last_violation`),
  KEY `rate_limit_violations_ip_address_index` (`ip_address`),
  KEY `rate_limit_violations_user_id_index` (`user_id`),
  KEY `rate_limit_violations_rate_limit_type_index` (`rate_limit_type`),
  KEY `rate_limit_violations_endpoint_index` (`endpoint`),
  KEY `rate_limit_violations_blocked_index` (`blocked`),
  KEY `rate_limit_violations_organization_id_index` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limit_violations`
--

LOCK TABLES `rate_limit_violations` WRITE;
/*!40000 ALTER TABLE `rate_limit_violations` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_limit_violations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` int(11) NOT NULL DEFAULT '0',
  `image` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `module` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `role_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_assessments`
--

DROP TABLE IF EXISTS `security_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_assessments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `risk_score` int(11) NOT NULL DEFAULT '0',
  `threats_detected` json DEFAULT NULL,
  `recommendations` json DEFAULT NULL,
  `blocked` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `security_assessments_risk_score_created_at_index` (`risk_score`,`created_at`),
  KEY `security_assessments_blocked_created_at_index` (`blocked`,`created_at`),
  KEY `security_assessments_ip_address_index` (`ip_address`),
  KEY `security_assessments_user_id_index` (`user_id`),
  KEY `security_assessments_organization_id_index` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_assessments`
--

LOCK TABLES `security_assessments` WRITE;
/*!40000 ALTER TABLE `security_assessments` DISABLE KEYS */;
/*!40000 ALTER TABLE `security_assessments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_incidents`
--

DROP TABLE IF EXISTS `security_incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_incidents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `incident_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `endpoint` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` json DEFAULT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT '0',
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `security_severity_resolved_created_idx` (`severity`,`resolved`,`created_at`),
  KEY `security_type_created_idx` (`incident_type`,`created_at`),
  KEY `security_ip_severity_created_idx` (`ip_address`,`severity`,`created_at`),
  KEY `security_incidents_audit_id_index` (`audit_id`),
  KEY `security_incidents_incident_type_index` (`incident_type`),
  KEY `security_incidents_severity_index` (`severity`),
  KEY `security_incidents_ip_address_index` (`ip_address`),
  KEY `security_incidents_user_id_index` (`user_id`),
  KEY `security_incidents_endpoint_index` (`endpoint`),
  KEY `security_incidents_resolved_index` (`resolved`),
  KEY `security_org_type_created_idx` (`organization_id`,`incident_type`,`created_at`),
  KEY `security_incidents_organization_id_index` (`organization_id`),
  CONSTRAINT `security_incidents_audit_id_foreign` FOREIGN KEY (`audit_id`) REFERENCES `audit_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_incidents`
--

LOCK TABLES `security_incidents` WRITE;
/*!40000 ALTER TABLE `security_incidents` DISABLE KEYS */;
INSERT INTO `security_incidents` VALUES (1,'req_68d6b62330085_8296','server_error','high','127.0.0.1',NULL,NULL,'login.submit','{\"user_id\": null, \"audit_id\": \"req_68d6b62330085_8296\", \"endpoint\": \"login.submit\", \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"incident_type\": \"server_error\", \"organization_id\": null}',0,NULL,NULL,'2025-09-26 08:49:55','2025-09-26 08:49:55'),(2,'req_68d6b627c2176_9755','server_error','high','127.0.0.1',NULL,NULL,'login.submit','{\"user_id\": null, \"audit_id\": \"req_68d6b627c2176_9755\", \"endpoint\": \"login.submit\", \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"incident_type\": \"server_error\", \"organization_id\": null}',0,NULL,NULL,'2025-09-26 08:50:00','2025-09-26 08:50:00'),(3,'req_68d6bb0654fc8_3423','server_error','high','127.0.0.1',NULL,NULL,'login.submit','{\"user_id\": null, \"audit_id\": \"req_68d6bb0654fc8_3423\", \"endpoint\": \"login.submit\", \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"incident_type\": \"server_error\", \"organization_id\": null}',0,NULL,NULL,'2025-09-26 09:10:46','2025-09-26 09:10:46'),(4,'req_68da846085daf_4555','server_error','high','127.0.0.1',NULL,NULL,'login.submit','{\"user_id\": null, \"audit_id\": \"req_68da846085daf_4555\", \"endpoint\": \"login.submit\", \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"incident_type\": \"server_error\", \"organization_id\": null}',0,NULL,NULL,'2025-09-29 06:06:46','2025-09-29 06:06:46'),(5,'req_68da855b214f5_8447','server_error','high','127.0.0.1',NULL,NULL,'login.submit','{\"user_id\": null, \"audit_id\": \"req_68da855b214f5_8447\", \"endpoint\": \"login.submit\", \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"incident_type\": \"server_error\", \"organization_id\": null}',0,NULL,NULL,'2025-09-29 06:10:56','2025-09-29 06:10:56'),(6,'req_68da8b1c9df7a_4256','server_error','high','127.0.0.1',NULL,NULL,'login.submit','{\"user_id\": null, \"audit_id\": \"req_68da8b1c9df7a_4256\", \"endpoint\": \"login.submit\", \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"incident_type\": \"server_error\", \"organization_id\": null}',0,NULL,NULL,'2025-09-29 06:35:29','2025-09-29 06:35:29');
/*!40000 ALTER TABLE `security_incidents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seeder_histories`
--

DROP TABLE IF EXISTS `seeder_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seeder_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seeder_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seeder_histories_seeder_name_unique` (`seeder_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seeder_histories`
--

LOCK TABLES `seeder_histories` WRITE;
/*!40000 ALTER TABLE `seeder_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `seeder_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('address',NULL),('allow_facebook_login','0'),('allow_google_login','0'),('app_environment','local'),('available_version',NULL),('aws_access_key',NULL),('aws_bucket',NULL),('aws_default_region',NULL),('aws_secret_key',NULL),('billing_address',NULL),('billing_city',NULL),('billing_country',NULL),('billing_name',NULL),('billing_phone_1',NULL),('billing_phone_2',NULL),('billing_postal_code',NULL),('billing_state',NULL),('billing_tax_id',NULL),('broadcast_driver','pusher'),('company_name','Blazz'),('currency','USD'),('date_format','d-M-y'),('default_image_api',NULL),('display_frontend','1'),('email',NULL),('enable_ai_billing','0'),('facebook_login',NULL),('favicon',NULL),('google_analytics_status','0'),('google_analytics_tracking_id',NULL),('google_login',NULL),('google_maps_api_key',NULL),('invoice_prefix',NULL),('is_tax_inclusive','1'),('is_update_available','0'),('last_update_check','2025-09-26 15:38:28'),('logo',NULL),('mail_config',NULL),('phone',NULL),('pusher_app_cluster',NULL),('pusher_app_id',NULL),('pusher_app_key',NULL),('pusher_app_secret',NULL),('recaptcha_active','0'),('recaptcha_secret_key',NULL),('recaptcha_site_key',NULL),('release_date',NULL),('smtp_email_active','0'),('socials','{\"facebook\":null,\"twitter\":null,\"instagram\":null,\"slack\":null,\"linkedin\":null}'),('storage_system','local'),('time_format','H:i'),('timezone','UTC'),('title','Blazz'),('trial_period','20'),('verify_email','0'),('version',NULL),('whatsapp_callback_token','20250926153828mVLD');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscription_plans`
--

DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscription_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
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
-- Dumping data for table `subscription_plans`
--

LOCK TABLES `subscription_plans` WRITE;
/*!40000 ALTER TABLE `subscription_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscription_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
INSERT INTO `subscriptions` VALUES (1,'7cfa15cd-5bad-4acd-827c-f9cbbee48249',1,NULL,NULL,'2025-09-26 09:57:32','2025-10-16 16:57:32','trial','2025-09-26 09:57:32','2025-09-26 09:57:32');
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_rates`
--

DROP TABLE IF EXISTS `tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_rates`
--

LOCK TABLES `tax_rates` WRITE;
/*!40000 ALTER TABLE `tax_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_invites`
--

DROP TABLE IF EXISTS `team_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_invites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `email` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invited_by` bigint(20) unsigned NOT NULL,
  `expire_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `team_invites_organization_id_foreign` (`organization_id`),
  KEY `team_invites_invited_by_foreign` (`invited_by`),
  CONSTRAINT `team_invites_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`),
  CONSTRAINT `team_invites_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_invites`
--

LOCK TABLES `team_invites` WRITE;
/*!40000 ALTER TABLE `team_invites` DISABLE KEYS */;
/*!40000 ALTER TABLE `team_invites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` enum('owner','manager','agent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manager',
  `status` enum('active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_uuid_unique` (`uuid`),
  KEY `teams_user_id_foreign` (`user_id`),
  KEY `teams_created_by_foreign` (`created_by`),
  KEY `idx_team_membership_complete` (`organization_id`,`user_id`,`role`,`created_at`),
  CONSTRAINT `teams_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `teams_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `teams_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

LOCK TABLES `teams` WRITE;
/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
INSERT INTO `teams` VALUES (1,'3c3cc95b-7431-405e-a876-8a7ae25b9acc',1,1,'owner','active',1,NULL,NULL,'2025-09-26 09:57:32','2025-09-26 09:57:32');
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `templates`
--

DROP TABLE IF EXISTS `templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `meta_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
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
-- Dumping data for table `templates`
--

LOCK TABLES `templates` WRITE;
/*!40000 ALTER TABLE `templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `threat_ips`
--

DROP TABLE IF EXISTS `threat_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `threat_ips` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `threat_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `confidence_score` int(11) NOT NULL DEFAULT '0',
  `first_seen` timestamp NULL DEFAULT NULL,
  `last_seen` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `threat_ips_ip_address_unique` (`ip_address`),
  KEY `threat_ips_threat_type_confidence_score_index` (`threat_type`,`confidence_score`),
  KEY `threat_ips_expires_at_last_seen_index` (`expires_at`,`last_seen`),
  KEY `threat_ips_ip_address_index` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `threat_ips`
--

LOCK TABLES `threat_ips` WRITE;
/*!40000 ALTER TABLE `threat_ips` DISABLE KEYS */;
/*!40000 ALTER TABLE `threat_ips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_categories`
--

DROP TABLE IF EXISTS `ticket_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_categories`
--

LOCK TABLES `ticket_categories` WRITE;
/*!40000 ALTER TABLE `ticket_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_comments`
--

DROP TABLE IF EXISTS `ticket_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
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
-- Dumping data for table `ticket_comments`
--

LOCK TABLES `ticket_comments` WRITE;
/*!40000 ALTER TABLE `ticket_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `subject` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('critical','high','medium','low') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `status` enum('open','pending','resolved','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `closed_by` bigint(20) unsigned DEFAULT NULL,
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
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `facebook_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `phone` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tfa_secret` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tfa` tinyint(4) NOT NULL DEFAULT '0',
  `status` int(11) NOT NULL DEFAULT '1',
  `meta` text COLLATE utf8mb4_unicode_ci,
  `plan` text COLLATE utf8mb4_unicode_ci,
  `plan_id` bigint(20) unsigned DEFAULT NULL,
  `will_expire` date DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_deleted_at_unique` (`email`,`deleted_at`),
  UNIQUE KEY `users_facebook_id_unique` (`facebook_id`),
  KEY `idx_user_verification_timeline` (`email_verified_at`,`created_at`),
  KEY `idx_user_role_timeline` (`role`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Laksmana','Moerdani','ltmoerdani@yahoo.com',NULL,NULL,'user',NULL,NULL,NULL,'$2y$10$5vyL9K99ZEFQb.NkdHmKW.BFuFUXiPQPLAfnTFezu65GOyrRgMC3u','VAA2OW4SXSMDEOGHXUDIQBINKJRQGVVS',0,1,NULL,NULL,NULL,NULL,NULL,'2025-09-26 09:51:09','2025-09-29 06:45:38',NULL),(2,'Admin','Demo','admin@demo.com',NULL,NULL,'admin',NULL,NULL,NULL,'$2y$10$H94Aa2Bl4pltZ/P/EdvbpOt8LCosQq7X7fF/cqHhWhDVGz8RYck0S','HDP676DHZJGOT4LOEVHHOHEX6FFGWVEV',0,1,NULL,NULL,NULL,NULL,NULL,'2025-09-29 06:39:30','2025-09-29 06:41:10',NULL),(3,'Test','User','test@demo.com',NULL,NULL,'user',NULL,NULL,NULL,'$2y$10$rFvAbWPwCbHjWjkT/ubNTOhRAh00r/kwsB02xAVOV3NKWFvbV0Cdq',NULL,0,1,NULL,NULL,NULL,NULL,NULL,'2025-09-29 06:39:38','2025-09-29 06:39:38',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_sessions`
--

DROP TABLE IF EXISTS `whatsapp_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whatsapp_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `channel_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` longtext COLLATE utf8mb4_unicode_ci,
  `status` enum('DISCONNECTED','CONNECTING','CONNECTED','RECONNECTING','FAILED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DISCONNECTED',
  `failure_count` int(11) NOT NULL DEFAULT '0',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `last_heartbeat_at` timestamp NULL DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capabilities` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_sessions_uuid_unique` (`uuid`),
  KEY `whatsapp_sessions_organization_id_status_index` (`organization_id`,`status`),
  KEY `whatsapp_sessions_status_last_heartbeat_at_index` (`status`,`last_heartbeat_at`),
  KEY `whatsapp_sessions_failure_count_status_index` (`failure_count`,`status`),
  KEY `whatsapp_sessions_channel_id_index` (`channel_id`),
  KEY `whatsapp_sessions_status_index` (`status`),
  CONSTRAINT `whatsapp_sessions_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_sessions`
--

LOCK TABLES `whatsapp_sessions` WRITE;
/*!40000 ALTER TABLE `whatsapp_sessions` DISABLE KEYS */;
INSERT INTO `whatsapp_sessions` VALUES (1,'467fee82-2b27-49b6-8de1-071f0942bd87',1,'default',NULL,'CONNECTING',0,NULL,NULL,'2025-09-26 10:50:40',NULL,NULL,NULL,'2025-09-26 10:06:49','2025-09-26 10:50:40');
/*!40000 ALTER TABLE `whatsapp_sessions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-16  0:18:06
