-- MySQL dump 10.13  Distrib 9.3.0, for macos15.2 (arm64)
--
-- Host: 127.0.0.1    Database: blazz
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
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `license` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_plan_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `update_available` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addons`
--

LOCK TABLES `addons` WRITE;
/*!40000 ALTER TABLE `addons` DISABLE KEYS */;
INSERT INTO `addons` VALUES (1,'64ab82db-94cb-486a-bcf7-0dcb8838065a','authentication','Google Authenticator','google-auth.png','Two-factor authentication using Google Authenticator','{\n    \"name\": \"GoogleAuthenticator\"\n}','regular','1.0.0',0,0,1,1,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(2,'198424cd-2e06-4652-9361-0b9701fcc169','chat','Embedded Signup','whatsapp.png','An Embedded Signup add-on allows app users to register using their WhatsApp account.','{\n    \"name\": \"EmbeddedSignup\"\n}',NULL,NULL,1,0,0,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(3,'3bbcb561-2279-49fe-b426-3575234de6be','recaptcha','Google Recaptcha','google_recaptcha.png','Google reCAPTCHA enhances website security by preventing spam and abusive activities.','{\n    \"input_fields\": [\n        {\n            \"element\": \"input\",\n            \"type\": \"password\",\n            \"name\": \"recaptcha_site_key\",\n            \"label\": \"Recaptcha site key\",\n            \"class\": \"col-span-2\"\n        },\n        {\n            \"element\": \"input\",\n            \"type\": \"password\",\n            \"name\": \"recaptcha_secret_key\",\n            \"label\": \"Recaptcha secret key\",\n            \"class\": \"col-span-2\"\n        }\n    ],\n    \"name\": \"GoogleRecaptcha\"\n}',NULL,NULL,0,0,1,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(4,'f6cdc7cd-0295-490b-851c-728145e4077e','analytics','Google Analytics','google_analytics.png','Google Analytics tracks website performance and provides valuable insights for optimization.','{\"input_fields\": [{\"element\": \"input\", \"type\": \"text\", \"name\": \"google_analytics_tracking_id\", \"label\": \"Google analytics tracking ID\", \"class\": \"col-span-2\"}]}',NULL,NULL,0,0,1,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(5,'160aec69-d3ad-4910-a4d9-63d2a7d8d261','maps','Google Maps','google_maps.png','Google Maps provides interactive maps for whatsapp messages.','{\"input_fields\": [{\"element\": \"input\", \"type\": \"text\", \"name\": \"google_maps_api_key\", \"label\": \"Google maps API key\", \"class\": \"col-span-2\"}]}',NULL,NULL,0,0,1,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(6,'ccd94fa8-23d7-48db-add2-062cf17e3866','payments','Razorpay','razorpay.png','Razorpay is a payment platform that simplies payment processing.','{\n    \"input_fields\": [\n        {\n            \"element\": \"input\",\n            \"type\": \"text\",\n            \"name\": \"razorpay_key_id\",\n            \"label\": \"Key ID\",\n            \"class\": \"col-span-2\"\n        },\n        {\n            \"element\": \"input\",\n            \"type\": \"text\",\n            \"name\": \"razorpay_secret_key\",\n            \"label\": \"Secret Key\",\n            \"class\": \"col-span-2\"\n        },\n        {\n            \"element\": \"input\",\n            \"type\": \"text\",\n            \"name\": \"razorpay_webhook_secret\",\n            \"label\": \"Webhook secret\",\n            \"class\": \"col-span-2\"\n        }\n    ],\n    \"name\": \"Razorpay\"\n}',NULL,NULL,0,0,1,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(7,'0501a319-8038-423b-a471-404225e90486','ai','AI Assistant','ai.png','The AI assistant delivers intelligent, AI-driven responses by utilizing user data for training.','{\n    \"name\": \"IntelliReply\"\n}',NULL,NULL,1,0,0,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(8,'4d8f188e-08f7-4fc5-98b6-b809d8054f66','utility','Webhooks','webhook_icon.png','Webhooks enable real-time data transfer by sending automated notifications on specific events.','{\n    \"name\": \"Webhook\"\n}',NULL,NULL,1,0,0,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(9,'75c20a5d-100e-4e4d-aea1-cd7b5f79769c','utility','Flow builder','flow_icon.png','Flow Builder automation allows users to visually create and manage messaging workflows.','{\n    \"name\": \"FlowBuilder\"\n}',NULL,NULL,1,0,0,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(10,'c579f27a-e648-406d-b373-86bbbada1ff0','payments','Pabbly Subscriptions','pabbly.png','Pabbly Subscriptions automates recurring billing and payment processing.',NULL,'regular','1.0',0,0,0,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(11,'cebe6245-6ba7-4c8d-86e4-fb97ddb9f3f0','plugins','Woocommerce','woocommerce.png','Keep your woocommerce customers informed and connected with order updates and live chat.',NULL,'regular','1.0',0,0,0,0,'2025-11-13 23:47:58','2025-11-13 23:47:58');
/*!40000 ALTER TABLE `addons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_data` json DEFAULT NULL,
  `status_code` int DEFAULT NULL,
  `response_size` bigint DEFAULT NULL,
  `execution_time` decimal(10,3) DEFAULT NULL,
  `memory_usage` bigint DEFAULT NULL,
  `success` tinyint(1) DEFAULT NULL,
  `event_result` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_organization_id_created_at_index` (`created_at`),
  KEY `audit_logs_ip_address_created_at_index` (`ip_address`,`created_at`),
  KEY `audit_logs_event_type_created_at_index` (`event_type`,`created_at`),
  KEY `audit_logs_success_created_at_index` (`success`,`created_at`),
  KEY `audit_logs_event_type_index` (`event_type`),
  KEY `audit_logs_endpoint_index` (`endpoint`),
  KEY `audit_logs_ip_address_index` (`ip_address`),
  KEY `audit_logs_user_id_index` (`user_id`),
  KEY `audit_logs_session_id_index` (`session_id`),
  KEY `audit_logs_status_code_index` (`status_code`),
  KEY `audit_logs_success_index` (`success`),
  KEY `audit_logs_event_result_index` (`event_result`),
  KEY `audit_logs_request_id_index` (`request_id`),
  KEY `audit_logs_workspace_id_index` (`workspace_id`),
  CONSTRAINT `audit_logs_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES ('req_6916a72aa9726_9721','req_6916a72aa9726_9721','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Vjp3DF2F0aJmuxRk4vc2HKYFdQ00v2RLE5KMIpXY','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255@c.us_3EB00E51170E88F9A083BF\", \"to\": \"62811801641@c.us\", \"body\": \"daffa mau lanjutin podcastnya\", \"from\": \"628999338255@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763092265}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,89.888,31457280,1,'success','2025-11-13 20:51:06','2025-11-13 20:51:06'),('req_6916a72f31ac2_3483','req_6916a72f31ac2_3483','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'f4dsMFqJ9KrOC3qhCcfbtRRJxIM7rnlVpXConr84','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255@c.us_3EB07795300CE2BC55F141\", \"to\": \"62811801641@c.us\", \"body\": \"wkwk\", \"from\": \"628999338255@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763092270}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,53.572,29360128,1,'success','2025-11-13 20:51:11','2025-11-13 20:51:11'),('req_6916a74f2a24e_4594','req_6916a74f2a24e_4594','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'t3ZLkR5el9zY5HEL3ytkIieRbTfMk12VDzf3BKLz','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,56.791,29360128,1,'success','2025-11-13 20:51:43','2025-11-13 20:51:43'),('req_6916a751b8c28_7743','req_6916a751b8c28_7743','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'2HjeBKDREVgIdSy8elReoQwXwCPe1wL2XNI86eLo','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255@c.us_3EB0AABF9E58D1C0834E2F\", \"to\": \"62811801641@c.us\", \"body\": \"Ka setelah ditonton ulang\", \"from\": \"628999338255@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763092304}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,56.091,29360128,1,'success','2025-11-13 20:51:45','2025-11-13 20:51:45'),('req_6916a75235ebf_1772','req_6916a75235ebf_1772','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'tQCRCHinI0CSTH49ag2JRHGBEnKwISbNXGJUdTVw','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255@c.us_3EB0E554D9D7FB96F0D0F3\", \"to\": \"62811801641@c.us\", \"body\": \"Seriusin bikin podcast kayanya oke deh ka wkwkw\", \"from\": \"628999338255@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763092305}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,73.809,29360128,1,'success','2025-11-13 20:51:46','2025-11-13 20:51:46'),('req_6916a7529bd54_4880','req_6916a7529bd54_4880','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'qIZzvGHpB4TEO3gdQXhyC0dHeq4kFybonrUr4rfb','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255@c.us_3EB0FC8BEF98EC8C3E2079\", \"to\": \"62811801641@c.us\", \"body\": \"Abis nyari konten konten yang sama\", \"from\": \"628999338255@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763092305}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,44.236,29360128,1,'success','2025-11-13 20:51:46','2025-11-13 20:51:46'),('req_6916a752e6f25_2780','req_6916a752e6f25_2780','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'MLGveV3Wdz1k7EfKE8m9Yc6fXidCNOqAoRjPVYsm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255@c.us_3EB0482BCB1DAB6511E344\", \"to\": \"62811801641@c.us\", \"body\": \"Lumayan juga trs aku buat story di ig impresinya lumayan 🤣\", \"from\": \"628999338255@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763092305}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,37.362,29360128,1,'success','2025-11-13 20:51:46','2025-11-13 20:51:46'),('req_6916a7533aec6_5649','req_6916a7533aec6_5649','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'BMWJcSiKxwhP1LSiVgEYolosWbXodwGhQ9IFBYFJ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255@c.us_3EB0BEA502019CEF39E7A1\", \"to\": \"62811801641@c.us\", \"body\": \"Ayo ka, gapapa modal dikit serius deh 🤣\", \"from\": \"628999338255@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763092305}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,74.501,29360128,1,'success','2025-11-13 20:51:47','2025-11-13 20:51:47'),('req_6916a7539b58c_6906','req_6916a7539b58c_6906','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'y2TdtqbxaQLSRLCr4ZLt9RQZFtsgquPXDSuJvnRz','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255@c.us_3EB0989A15A804E110E557\", \"to\": \"62811801641@c.us\", \"body\": \"Soalnya belum banyak dan peluang nya ada, apalagi ekonomi lagi jadi perbincangan menarik di indo semenjak kenaikan pak purbaya\", \"from\": \"628999338255@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763092305}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,47.251,29360128,1,'success','2025-11-13 20:51:47','2025-11-13 20:51:47'),('req_6916a768df5ca_7063','req_6916a768df5ca_7063','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'9d3cQxMBUamWEHTRFR4bua7hSIaW62CDsAryvCyo','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255@c.us_3EB0DC8758A56CA9D28773\", \"to\": \"62811801641@c.us\", \"body\": \"berdua aku hehe\", \"from\": \"628999338255@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763092328}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,36.199,29360128,1,'success','2025-11-13 20:52:08','2025-11-13 20:52:08'),('req_6916a85cc86b6_5590','req_6916a85cc86b6_5590','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'iZlO0eZrmrVA1ZtNzIhLovizimqnKoLHjVBAOaLL','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,278.578,29360128,1,'success','2025-11-13 20:56:12','2025-11-13 20:56:13'),('req_6916a8863ca98_4766','req_6916a8863ca98_4766','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'JZqObuGYVJyiuIXioNAJGwfW3Y0i07ALn69w9ije','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,61.038,29360128,1,'success','2025-11-13 20:56:54','2025-11-13 20:56:54'),('req_6916aa5ec7f9e_1336','req_6916aa5ec7f9e_1336','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'9MdvucaAesnLwMzA56JaBzexqJYj80tJYelynbgT','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255@c.us_3EB056614BAC0147E1A71A\", \"to\": \"62811801641@c.us\", \"body\": \"foto anak2 mana yang\", \"from\": \"628999338255@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763093085}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,106.523,29360128,1,'success','2025-11-13 21:04:46','2025-11-13 21:04:46'),('req_6916aa79c72bb_4089','req_6916aa79c72bb_4089','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'0R6t18NtqBjVnNyaHuKcNLTs3wtGVQ12L1T9X1Ha','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,36.755,31457280,1,'success','2025-11-13 21:05:13','2025-11-13 21:05:13'),('req_6916aa7b8ec31_6752','req_6916aa7b8ec31_6752','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'MpTuZGCs6EytVDnqZyAhkGmDkKj04cneBQpAJSDP','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,63.549,31457280,1,'success','2025-11-13 21:05:15','2025-11-13 21:05:15'),('req_6916aa7c6cf68_7309','req_6916aa7c6cf68_7309','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'COYUgnP028yWBhNGJoW98RK23lTtLfUXzOUkii9u','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,79.378,31457280,1,'success','2025-11-13 21:05:16','2025-11-13 21:05:16'),('req_6916abcc3b4bb_6309','req_6916abcc3b4bb_6309','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'izxN9KhXKiPM6Nf63BLEJinnKXAABr3J7bpF2aly','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,74.136,29360128,1,'success','2025-11-13 21:10:52','2025-11-13 21:10:52'),('req_6916acc003861_4921','req_6916acc003861_4921','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'m0wRn4r9EZiFxbBOlxoGa1NsdKdRprY4rS1iqicR','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC04707AEE95DB766CF3E3CC20EB25AA_6285693753524@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"soft spoken\\nbut deadly...\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763093694, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Sahrul Fahmi\", \"sender_phone\": \"6285693753524\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,57.903,29360128,1,'success','2025-11-13 21:14:56','2025-11-13 21:14:56'),('req_6916ace10f3e1_5427','req_6916ace10f3e1_5427','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'eY2r2fvFt7Q7T3VQZ4CYS4S1OWuLwKXZlt94LS1L','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_6281386177732@c.us_3EB00C5CEBF6B1A27A4537\", \"to\": \"62811801641@c.us\", \"body\": \"OM OCI\", \"from\": \"6281386177732@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763093728}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,123.769,29360128,1,'success','2025-11-13 21:15:29','2025-11-13 21:15:29'),('req_6916ace363f29_7554','req_6916ace363f29_7554','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'sKgQcZj6Dbd0JVfZl2jww7eldiJweQxwdQqHOduT','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_6281386177732@c.us_3EB0657A4C574F3D9769B5\", \"to\": \"62811801641@c.us\", \"body\": \"om oci\", \"from\": \"6281386177732@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763093730}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,76.835,29360128,1,'success','2025-11-13 21:15:31','2025-11-13 21:15:31'),('req_6916acec1aa65_7689','req_6916acec1aa65_7689','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'p1PLgW8pMpyE7px2cG6DPQ854jqbpODNi56bbFOr','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_6281386177732@c.us_3EB064C09BCC5796072BF8\", \"to\": \"62811801641@c.us\", \"body\": \"invoicenya boleh di kriim yang baru om\", \"from\": \"6281386177732@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763093739}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,46.728,29360128,1,'success','2025-11-13 21:15:40','2025-11-13 21:15:40'),('req_6916acf23606b_2201','req_6916acf23606b_2201','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'RYNwYutNM2QuvZZimWFjRkpfgOKq7ayuYuo642iW','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_6281386177732@c.us_3EB0B2B19DE1F0FFAD424F\", \"to\": \"62811801641@c.us\", \"body\": \"sudah nggk aktif VA nya\", \"from\": \"6281386177732@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763093745}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,56.096,29360128,1,'success','2025-11-13 21:15:46','2025-11-13 21:15:46'),('req_6916ad2abc1b8_5004','req_6916ad2abc1b8_5004','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'URKAINotUJqHQFuRdErHsOy2jXdqimKfRixasWSB','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_62811123313-1501661717@g.us_3A70C78978B99EDC76F7_228681272311891@lid\", \"to\": \"62811801641@c.us\", \"body\": \"*Murceee 😍🌺\\n\\n9D -HK30 \\nGA980 18DEC CGKJED 1150 1740\\nGA983 26DEC JEDCGK 2155 1150+1\", \"from\": \"62811123313-1501661717@g.us\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"62811123313-1501661717@g.us\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763093801, \"group_name\": \"ASOSIASI MHTI -2\", \"sender_name\": \"Ella Kamil\", \"sender_phone\": \"6281361553815\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,52.234,29360128,1,'success','2025-11-13 21:16:42','2025-11-13 21:16:42'),('req_6916ad2b180c7_3839','req_6916ad2b180c7_3839','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'y3uMsJ3fDZ8YyBLRxI5X2Q6gf1Q8kqeXFIK7xsFD','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_6285884588727-1536891583@g.us_3A2D6C487406E1373666_228681272311891@lid\", \"to\": \"62811801641@c.us\", \"body\": \"*Murceee 😍🌺\\n\\n9D -HK30 \\nGA980 18DEC CGKJED 1150 1740\\nGA983 26DEC JEDCGK 2155 1150+1\", \"from\": \"6285884588727-1536891583@g.us\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"6285884588727-1536891583@g.us\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763093801, \"group_name\": \"GROUP MEMBER MHTI\", \"sender_name\": \"Ella Kamil\", \"sender_phone\": \"6281361553815\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,38.395,29360128,1,'success','2025-11-13 21:16:43','2025-11-13 21:16:43'),('req_6916b19c57b8d_6338','req_6916b19c57b8d_6338','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'5ZKnF5QE1Vz3Hm25EFNF5VmP94CuRQ19uoP3w4pG','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,93.764,29360128,1,'success','2025-11-13 21:35:40','2025-11-13 21:35:40'),('req_6916d15a0dd5b_9569','req_6916d15a0dd5b_9569','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'oGI5EFs8aa7boiDJME80g0naFaDoT4qB00WUVSpo','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,370,428.084,31457280,0,'unknown','2025-11-13 23:51:06','2025-11-13 23:51:06'),('req_691707eed1548_8328','req_691707eed1548_8328','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'bqWq9JHDASdcwh4QNBo6VQXgmGZUitAMdY1hkF3j','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,366,482.640,31457280,0,'unknown','2025-11-14 03:43:58','2025-11-14 03:43:59'),('req_69170d62eadec_4042','req_69170d62eadec_4042','request_attempt','generated::L0bOmLIBxodMefOa','POST','http://127.0.0.1:8000/settings/contacts','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'YdWEvrcdwzAVf66CEzbN1oOygV0LfvkEOCv3m4XP','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/contacts\", \"expects_json\": false, \"input_summary\": {\"location\": \"before\", \"workspace\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',200,49860,47.898,31457280,1,'success','2025-11-14 04:07:14','2025-11-14 04:07:15');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `authentication_events`
--

DROP TABLE IF EXISTS `authentication_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `authentication_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `audit_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` enum('login_attempt','login_success','login_failure','logout','password_reset','account_locked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `failure_reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `authentication_events_workspace_id_index` (`workspace_id`),
  CONSTRAINT `authentication_events_audit_id_foreign` FOREIGN KEY (`audit_id`) REFERENCES `audit_logs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `authentication_events_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auto_replies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `match_criteria` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int NOT NULL,
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auto_replies_uuid_unique` (`uuid`),
  KEY `auto_replies_workspace_id_index` (`workspace_id`),
  CONSTRAINT `auto_replies_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_credits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_credits_uuid_unique` (`uuid`),
  KEY `billing_credits_workspace_id_index` (`workspace_id`),
  CONSTRAINT `billing_credits_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_debits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_debits_uuid_unique` (`uuid`),
  KEY `billing_debits_workspace_id_index` (`workspace_id`),
  CONSTRAINT `billing_debits_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `plan_id` int NOT NULL,
  `subtotal` decimal(19,4) NOT NULL,
  `coupon_id` int DEFAULT NULL,
  `coupon_amount` decimal(23,2) DEFAULT '0.00',
  `tax` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
  `tax_type` enum('inclusive','exclusive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_invoices_uuid_unique` (`uuid`),
  KEY `billing_invoices_workspace_id_index` (`workspace_id`),
  CONSTRAINT `billing_invoices_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `billing_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `interval` int NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `processor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `amount` decimal(19,4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_payments_uuid_unique` (`uuid`),
  KEY `billing_payments_workspace_id_index` (`workspace_id`),
  CONSTRAINT `billing_payments_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_tax_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_id` int NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `entity_type` enum('payment','invoice','credit','debit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_transactions_uuid_unique` (`uuid`),
  KEY `billing_transactions_workspace_id_index` (`workspace_id`),
  CONSTRAINT `billing_transactions_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blocked_ips` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_authors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `deleted` tinyint NOT NULL DEFAULT '0',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int NOT NULL,
  `tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_log_retries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_log_id` bigint unsigned NOT NULL,
  `chat_id` bigint unsigned DEFAULT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int NOT NULL,
  `contact_id` int NOT NULL,
  `whatsapp_account_id` bigint unsigned DEFAULT NULL,
  `chat_id` int DEFAULT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','success','failed','ongoing') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `retry_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_logs_whatsapp_session_id_foreign` (`whatsapp_account_id`),
  KEY `campaign_logs_campaign_id_whatsapp_session_id_index` (`campaign_id`,`whatsapp_account_id`),
  CONSTRAINT `campaign_logs_whatsapp_session_id_foreign` FOREIGN KEY (`whatsapp_account_id`) REFERENCES `whatsapp_accounts` (`id`) ON DELETE SET NULL
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `campaign_type` enum('template','direct') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'template',
  `template_id` int DEFAULT NULL,
  `message_content` text COLLATE utf8mb4_unicode_ci,
  `header_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `header_text` text COLLATE utf8mb4_unicode_ci,
  `header_media` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_text` text COLLATE utf8mb4_unicode_ci,
  `footer_text` text COLLATE utf8mb4_unicode_ci,
  `buttons_data` json DEFAULT NULL,
  `contact_group_id` int NOT NULL,
  `preferred_provider` enum('webjs','meta_api') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'webjs',
  `whatsapp_account_id` int DEFAULT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `messages_sent` int NOT NULL DEFAULT '0',
  `messages_delivered` int NOT NULL DEFAULT '0',
  `messages_read` int NOT NULL DEFAULT '0',
  `messages_failed` int NOT NULL DEFAULT '0',
  `scheduled_at` datetime DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_uuid_unique` (`uuid`),
  KEY `campaigns_workspace_id_index` (`workspace_id`),
  KEY `campaigns_campaign_type_status_index` (`status`),
  KEY `campaigns_workspace_id_status_index` (`workspace_id`,`status`),
  KEY `campaigns_preferred_provider_status_index` (`status`),
  CONSTRAINT `campaigns_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
-- Table structure for table `chat_logs`
--

DROP TABLE IF EXISTS `chat_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int NOT NULL,
  `entity_type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `deleted_by` int DEFAULT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` enum('local','amazon') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` int NOT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_ticket_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int NOT NULL,
  `assigned_to` int DEFAULT NULL,
  `status` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `whatsapp_account_id` bigint unsigned DEFAULT NULL,
  `wam_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_id` int NOT NULL,
  `group_id` bigint unsigned DEFAULT NULL COMMENT 'FK to whatsapp_groups for group chats',
  `user_id` bigint unsigned DEFAULT NULL,
  `type` enum('inbound','outbound') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_id` int DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Provider: meta | webjs',
  `chat_type` enum('private','group') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chat type: private contact or group',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chats_uuid_unique` (`uuid`),
  KEY `chats_contact_id_index` (`contact_id`),
  KEY `chats_created_at_index` (`created_at`),
  KEY `idx_chats_contact_org_deleted_at` (`contact_id`,`deleted_at`),
  KEY `idx_chat_timeline_performance` (`created_at`,`type`),
  KEY `idx_chat_participants_opt` (`contact_id`,`status`),
  KEY `idx_chat_media_timeline` (`media_id`,`created_at`),
  KEY `chats_workspace_id_index` (`workspace_id`),
  KEY `chats_whatsapp_session_id_created_at_index` (`whatsapp_account_id`,`created_at`),
  KEY `idx_chats_provider_type` (`workspace_id`,`provider_type`,`created_at`),
  KEY `idx_chats_chat_type` (`workspace_id`,`chat_type`,`created_at`),
  KEY `idx_chats_type_session` (`workspace_id`,`chat_type`,`whatsapp_account_id`,`created_at`),
  KEY `idx_chats_provider_session` (`workspace_id`,`provider_type`,`whatsapp_account_id`),
  KEY `idx_chats_contact_chat` (`contact_id`,`created_at`),
  KEY `idx_chats_group_chat` (`group_id`,`created_at`),
  CONSTRAINT `chats_whatsapp_session_id_foreign` FOREIGN KEY (`whatsapp_account_id`) REFERENCES `whatsapp_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chats_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chats_group_id` FOREIGN KEY (`group_id`) REFERENCES `whatsapp_groups` (`id`) ON DELETE SET NULL
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
-- Table structure for table `contact_accounts`
--

DROP TABLE IF EXISTS `contact_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` bigint unsigned NOT NULL,
  `whatsapp_account_id` bigint unsigned NOT NULL,
  `first_interaction_at` timestamp NULL DEFAULT NULL,
  `last_interaction_at` timestamp NULL DEFAULT NULL,
  `total_messages` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_sessions_contact_id_whatsapp_session_id_unique` (`contact_id`,`whatsapp_account_id`),
  KEY `contact_sessions_contact_id_last_interaction_at_index` (`contact_id`,`last_interaction_at`),
  KEY `contact_sessions_whatsapp_session_id_last_interaction_at_index` (`whatsapp_account_id`,`last_interaction_at`),
  CONSTRAINT `contact_sessions_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_sessions_whatsapp_session_id_foreign` FOREIGN KEY (`whatsapp_account_id`) REFERENCES `whatsapp_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_accounts`
--

LOCK TABLES `contact_accounts` WRITE;
/*!40000 ALTER TABLE `contact_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_accounts` ENABLE KEYS */;
UNLOCK TABLES;

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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` bigint unsigned DEFAULT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `required` tinyint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_fields_workspace_id_index` (`workspace_id`),
  CONSTRAINT `contact_fields_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_groups_uuid_unique` (`uuid`),
  KEY `contact_groups_workspace_id_index` (`workspace_id`),
  CONSTRAINT `contact_groups_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `source_account_id` bigint unsigned DEFAULT NULL,
  `source_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'meta',
  `first_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latest_chat_created_at` timestamp NULL DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `contact_group_id` int DEFAULT NULL,
  `is_favorite` tinyint NOT NULL DEFAULT '0',
  `ai_assistance_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contacts_uuid_unique` (`uuid`),
  KEY `contacts_deleted_at_index` (`deleted_at`),
  KEY `contacts_latest_chat_created_at_index` (`latest_chat_created_at`),
  KEY `idx_contacts_first_name` (`first_name`),
  KEY `idx_contacts_last_name` (`last_name`),
  KEY `idx_contacts_email` (`email`),
  KEY `idx_contacts_phone` (`phone`),
  KEY `contacts_workspace_id_index` (`workspace_id`),
  FULLTEXT KEY `fulltext_contacts_name_email_phone` (`first_name`,`last_name`,`phone`,`email`),
  CONSTRAINT `contacts_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `quantity_redeemed` int DEFAULT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_access_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `audit_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `target_user_id` bigint unsigned DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `data_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accessed_fields` json DEFAULT NULL,
  `purpose` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consent_given` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `data_target_type_created_idx` (`target_user_id`,`data_type`,`created_at`),
  KEY `data_org_access_created_idx` (`access_type`,`created_at`),
  KEY `data_consent_created_idx` (`consent_given`,`created_at`),
  KEY `data_user_access_created_idx` (`user_id`,`access_type`,`created_at`),
  KEY `data_access_logs_audit_id_index` (`audit_id`),
  KEY `data_access_logs_user_id_index` (`user_id`),
  KEY `data_access_logs_target_user_id_index` (`target_user_id`),
  KEY `data_access_logs_data_type_index` (`data_type`),
  KEY `data_access_logs_access_type_index` (`access_type`),
  KEY `data_access_logs_consent_given_index` (`consent_given`),
  KEY `data_access_organization_created_idx` (`data_type`,`created_at`),
  KEY `data_access_logs_workspace_id_index` (`workspace_id`),
  CONSTRAINT `data_access_logs_audit_id_foreign` FOREIGN KEY (`audit_id`) REFERENCES `audit_logs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `data_access_logs_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `source` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `embeddings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `status` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documents_uuid_unique` (`uuid`),
  KEY `documents_workspace_id_index` (`workspace_id`),
  CONSTRAINT `documents_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_chk_1` CHECK (json_valid(`embeddings`))
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `recipient` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('queued','sent','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `attempts` int NOT NULL DEFAULT '0',
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` blob NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_templates`
--

LOCK TABLES `email_templates` WRITE;
/*!40000 ALTER TABLE `email_templates` DISABLE KEYS */;
INSERT INTO `email_templates` VALUES (1,'Reset Password','Reset Password',_binary '<p>Hi {{FirstName}},</p><p>You have submitted a password reset for your account. If this was not you, simply ignore this email. But if you did, click on this link {{Link}} to reset your password. If that doesn\'t work, copy and paste this link to your browser.</p><p>{{Link}}</p>','2025-11-13 23:47:58',1),(2,'Password Reset Notification','Your Password has been reset',_binary '<p>Hi {{FirstName}},</p><p>Your password has been reset successfully! You can login to your account.</p>','2025-11-13 23:47:58',1),(3,'Registration','Welcome to {{CompanyName}}',_binary '<p>Hello {{FirstName}},</p><p>I am Joe, the founder of {{CompanyName}} and I would like to extend my heartfelt welcome to you for joining our platform. We are excited to have you onboard. Feel free to explore our platform and let us know if you have any questions or need assistance. </p><p>Thank you for choosing our platform!</p><p>Best regards,</p><p>The {{CompanyName}} Team</p><p><br></p>','2025-11-13 23:47:58',1),(4,'Invite','You have been invited to join {{CompanyName}}',_binary '<p>Hello there,</p><p><span style=\"color: rgb(55, 65, 81);\">You\'ve received an invitation to join {{CompanyName}}\'s account from {{FirstName}}.</span></p><p>To get started, simply click on the following link: {{Link}}</p><p>Thank you and welcome aboard!</p><p>Best regards,</p><p>{{CompanyName}} Team </p><p><br></p>','2025-11-13 23:47:58',1),(5,'Verify Email','Please verify your email',_binary '<p>Hi {firstName},</p><p>Please verify your email by clicking on the link below.</p><p>{verificationLink}</p><p><span style=\"letter-spacing: 0em; text-align: var(--bs-body-text-align);\">Best regards,</span></p><p><br></p>','2025-11-13 23:47:58',1),(6,'Payment Success','Your subscription payment was successful',_binary '<p>Hello,</p><p>Your subscription payment was successful!</p>','2025-11-13 23:47:58',1),(7,'Payment Failed','Your subscription payment was unsuccessful',_binary '<p>Hello,</p><p>Your payment was unsuccessful, please check your payment and confirm.</p><p><br></p>','2025-11-13 23:47:58',1),(8,'Subscription Renewal','Your subscription has been renewed successfully',_binary '<p>Hi {{FirstName}},</p><p>Your subscription has been renewed successfully. </p>','2025-11-13 23:47:58',1),(9,'Subscription Plan Purchase','Your have subscribed to {{plan}} successfully',_binary '<p>Hi {{FirstName}},</p><p>You have been subscribed to the {{plan}} plan successfully.</p>','2025-11-13 23:47:58',1);
/*!40000 ALTER TABLE `email_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faqs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL DEFAULT '0',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `languages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_rtl` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages`
--

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` VALUES (1,'English','en','active',0,NULL,NULL,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(2,'French','fr','active',0,NULL,NULL,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(3,'Spanish','es','active',0,NULL,NULL,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(4,'Indonesian','id','active',0,NULL,NULL,'2025-11-13 23:47:58','2025-11-13 23:47:58');
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2024_03_20_050200_create_auto_replies_table',1),(2,'2024_03_20_050311_create_billing_credits_table',1),(3,'2024_03_20_050348_create_billing_debits_table',1),(4,'2024_03_20_050430_create_billing_invoices_table',1),(5,'2024_03_20_050508_create_billing_items_table',1),(6,'2024_03_20_050600_create_billing_payments_table',1),(7,'2024_03_20_050635_create_billing_tax_rates_table',1),(8,'2024_03_20_050711_create_billing_transactions_table',1),(9,'2024_03_20_050751_create_blog_authors_table',1),(10,'2024_03_20_050826_create_blog_categories_table',1),(11,'2024_03_20_050912_create_blog_posts_table',1),(12,'2024_03_20_050959_create_blog_tags_table',1),(13,'2024_03_20_051036_create_campaigns_table',1),(14,'2024_03_20_051111_create_campaign_logs_table',1),(15,'2024_03_20_051154_create_chats_table',1),(16,'2024_03_20_051253_create_chat_logs_table',1),(17,'2024_03_20_051336_create_chat_media_table',1),(18,'2024_03_20_051414_create_contacts_table',1),(19,'2024_03_20_051449_create_contact_groups_table',1),(20,'2024_03_20_051537_create_coupons_table',1),(21,'2024_03_20_051613_create_email_logs_table',1),(22,'2024_03_20_051655_create_email_templates_table',1),(23,'2024_03_20_051739_create_failed_jobs_table',1),(24,'2024_03_20_051807_create_faqs_table',1),(25,'2024_03_20_051847_create_jobs_table',1),(26,'2024_03_20_051919_create_modules_table',1),(27,'2024_03_20_051953_create_notifications_table',1),(28,'2024_03_20_052034_create_workspaces_table',1),(29,'2024_03_20_052107_create_pages_table',1),(30,'2024_03_20_052141_create_password_reset_tokens_table',1),(31,'2024_03_20_052223_create_payment_gateways_table',1),(32,'2024_03_20_052338_create_reviews_table',1),(33,'2024_03_20_052401_create_users_table',1),(34,'2024_03_20_052430_create_roles_table',1),(35,'2024_03_20_052513_create_role_permissions_table',1),(36,'2024_03_20_052620_create_settings_table',1),(37,'2024_03_20_052654_create_subscriptions_table',1),(38,'2024_03_20_052731_create_subscription_plans_table',1),(39,'2024_03_20_052808_create_tax_rates_table',1),(40,'2024_03_20_052839_create_teams_table',1),(41,'2024_03_20_052914_create_team_invites_table',1),(42,'2024_03_20_052920_create_ticket_categories_table',1),(43,'2024_03_20_052956_create_templates_table',1),(44,'2024_03_20_053038_create_tickets_table',1),(45,'2024_03_20_053205_create_ticket_comments_table',1),(46,'2024_04_08_133150_create_workspace_api_keys_table',1),(47,'2024_04_24_211852_create_languages',1),(48,'2024_04_27_155643_create_contact_fields_table',1),(49,'2024_04_27_160152_add_metadata_to_contacts_table',1),(50,'2024_05_11_052902_create_chat_notes_table',1),(51,'2024_05_11_052925_create_chat_tickets_table',1),(52,'2024_05_11_052940_create_chat_ticket_logs_table',1),(53,'2024_05_11_053846_rename_chat_logs_table',1),(54,'2024_05_11_054010_create_chat_logs_2_table',1),(55,'2024_05_11_063255_add_user_id_to_chats_table',1),(56,'2024_05_11_063540_add_role_to_team_invites_table',1),(57,'2024_05_11_063819_update_agent_role_to_teams_table',1),(58,'2024_05_11_064650_add_deleted_by_to_workspace_api_keys_table',1),(59,'2024_05_11_065031_add_workspace_id_to_tickets_table',1),(60,'2024_05_28_080331_make_password_nullable_in_users_table',1),(61,'2024_05_30_125859_modify_campaigns_table',1),(62,'2024_06_03_124254_create_addons_table',1),(63,'2024_06_07_040536_update_users_table_for_facebook_login',1),(64,'2024_06_07_040843_update_chat_media_table',1),(65,'2024_06_07_074903_add_soft_delete_to_teams_and_workspaces',1),(66,'2024_06_09_155053_modify_billing_payments_table',1),(67,'2024_06_12_070820_modify_faqs_table',1),(68,'2024_07_04_053236_modify_amount_columns_in_billing_tables',1),(69,'2024_07_04_054143_modify_contacts_table_encoding',1),(70,'2024_07_09_011419_drop_seo_from_pages_table',1),(71,'2024_07_17_062442_allow_null_content_in_pages_table',1),(72,'2024_07_24_080535_add_latest_chat_created_at_to_contacts_table',1),(73,'2024_08_01_050752_add_ongoing_to_status_enum_in_campaign_logs_table',1),(74,'2024_08_08_130306_add_is_read_to_chats_table',1),(75,'2024_08_10_071237_create_documents_table',1),(76,'2024_10_16_201832_change_metadata_column_in_workspaces_table',1),(77,'2024_11_12_101941_add_license_column_to_addons_table',1),(78,'2024_11_25_114450_add_version_and_update_needed_to_addons_table',1),(79,'2024_11_28_083453_add_tfa_secret_to_users_table',1),(80,'2024_11_29_070806_create_seeder_histories_table',1),(81,'2024_12_20_081118_add_is_plan_restricted_to_addons_table',1),(82,'2024_12_20_130829_add_is_active_table',1),(83,'2025_01_24_090926_add_index_to_chats_table',1),(84,'2025_01_24_091012_add_index_to_chat_tickets_table',1),(85,'2025_01_24_091043_add_index_to_contacts_first_name',1),(86,'2025_01_24_091115_add_fulltext_index_to_contacts_table',1),(87,'2025_01_29_071445_modify_status_column_in_chats_table',1),(88,'2025_02_21_084110_create_job_batches_table',1),(89,'2025_02_21_093829_add_queue_indexes',1),(90,'2025_04_02_085132_create_contact_contact_group_table',1),(91,'2025_05_01_045837_create_campaign_log_retries_table',1),(92,'2025_05_01_053318_add_retry_count_to_campaign_logs_table',1),(93,'2025_05_23_101200_add_rtl_to_languages_table',1),(94,'2025_09_18_102755_optimize_database_indexes_for_performance',2),(95,'2025_09_18_110851_create_audit_logs_table',2),(96,'2025_09_18_112313_create_missing_security_tables',2),(97,'2025_09_18_115536_fix_security_tables_schema',2),(98,'2025_09_29_163230_create_workspaces_table',2),(99,'2025_09_29_163249_add_workspace_id_to_tables',2),(100,'2025_09_29_163357_migrate_workspaces_to_workspaces_data',2),(101,'2025_09_29_163521_add_workspace_foreign_key_constraints',2),(102,'2025_09_30_113358_remove_workspace_id_from_teams_table',3),(103,'2025_09_30_115254_remove_all_workspace_id_columns',4),(104,'2025_10_13_000000_create_whatsapp_sessions_table',5),(105,'2025_10_13_000001_migrate_existing_whatsapp_credentials',5),(106,'2025_10_13_000002_add_session_foreign_keys',5),(107,'2025_10_22_000001_add_chat_provider_and_groups',5),(108,'2025_10_22_000002_add_chat_indexes',5),(111,'2025_11_14_092838_update_remaining_column_references',6);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actions` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modules`
--

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
INSERT INTO `modules` VALUES (1,'customers','view, create, edit, delete'),(2,'workspaces','view, create, edit, delete'),(3,'billing','view'),(4,'support','view, create, assign'),(5,'team','view, create, edit, delete'),(6,'roles','view, create, edit, delete'),(7,'subscription_plans','view, create, edit, delete'),(8,'settings','general, timezone, broadcast_driver, payment_gateways, smtp, email_templates, billing, tax_rates, coupons, frontend');
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,'Privacy Policy','Introduction<p>Thanks for using our products and services (\"Services\"). The Services are provided by &lt;Your Business Name&gt;.</p><p>By using our Services, you are agreeing to these terms. Please read them carefully.</p><p>Our Services are very diverse, so sometimes additional terms or product requirements (including age requirements) may apply. Additional terms will be available with the relevant Services, and those additional terms become part of your agreement with us if you use those Services.</p>Using our services<p>You must follow any policies made available to you within the Services.</p><p>Don\'t misuse our Services. For example, don\'t interfere with our Services or try to access them using a method other than the interface and the instructions that we provide. You may use our Services only as permitted by law, including applicable export and re-export control laws and regulations. We may suspend or stop providing our Services to you if you do not comply with our terms or policies or if we are investigating suspected misconduct.</p><p>Using our Services does not give you ownership of any intellectual property rights in our Services or the content you access. You may not use content from our Services unless you obtain permission from its owner or are otherwise permitted by law. These terms do not grant you the right to use any branding or logos used in our Services. Don\'t remove, obscure, or alter any legal notices displayed in or along with our Services.</p>Privacy and copyright protection<p>&lt;Your Business Name&gt;\'s privacy policies explain how we treat your personal data and protect your privacy when you use our Services. By using our Services, you agree that &lt;Your Business Name&gt; can use such data in accordance with our privacy policies.</p><p>We respond to notices of alleged copyright infringement and terminate accounts of repeat infringers according to the process set out in the U.S. Digital Millennium Copyright Act.</p><p>We provide information to help copyright holders manage their intellectual property online. If you think somebody is violating your copyrights and want to notify us, you can find information about submitting notices and &lt;Your Business Name&gt;\'s policy about responding to notices in our Help Center.</p>Your content in our services<p>Some of our Services allow you to upload, submit, store, send or receive content. You retain ownership of any intellectual property rights that you hold in that content. In short, what belongs to you stays yours.</p><p>When you upload, submit, store, send or receive content to or through our Services, you give &lt;Your Business Name&gt; (and those we work with) a worldwide license to use, host, store, reproduce, modify, create derivative works (such as those resulting from translations, adaptations or other changes we make so that your content works better with our Services), communicate, publish, publicly perform, publicly display and distribute such content. The rights you grant in this license are for the limited purpose of operating, promoting, and improving our Services, and to develop new ones. This license continues even if you stop using our Services (for example, for a business listing you have added to &lt;Your Business Name&gt; Maps). Some Services may offer you ways to access and remove content that has been provided to that Service. Also, in some of our Services, there are terms or settings that narrow the scope of our use of the content submitted in those Services. Make sure you have the necessary rights to grant us this license for any content that you submit to our Services.</p>','2025-11-13 23:47:58','2025-11-13 23:47:58'),(2,'Terms of Service','Introduction<p>Thanks for using our products and services (\"Services\"). The Services are provided by &lt;Your Business Name&gt;.</p><p>By using our Services, you are agreeing to these terms. Please read them carefully.</p><p>Our Services are very diverse, so sometimes additional terms or product requirements (including age requirements) may apply. Additional terms will be available with the relevant Services, and those additional terms become part of your agreement with us if you use those Services.</p>Using our services<p>You must follow any policies made available to you within the Services.</p><p>Don\'t misuse our Services. For example, don\'t interfere with our Services or try to access them using a method other than the interface and the instructions that we provide. You may use our Services only as permitted by law, including applicable export and re-export control laws and regulations. We may suspend or stop providing our Services to you if you do not comply with our terms or policies or if we are investigating suspected misconduct.</p><p>Using our Services does not give you ownership of any intellectual property rights in our Services or the content you access. You may not use content from our Services unless you obtain permission from its owner or are otherwise permitted by law. These terms do not grant you the right to use any branding or logos used in our Services. Don\'t remove, obscure, or alter any legal notices displayed in or along with our Services.</p>Privacy and copyright protection<p>&lt;Your Business Name&gt;\'s privacy policies explain how we treat your personal data and protect your privacy when you use our Services. By using our Services, you agree that &lt;Your Business Name&gt; can use such data in accordance with our privacy policies.</p><p>We respond to notices of alleged copyright infringement and terminate accounts of repeat infringers according to the process set out in the U.S. Digital Millennium Copyright Act.</p><p>We provide information to help copyright holders manage their intellectual property online. If you think somebody is violating your copyrights and want to notify us, you can find information about submitting notices and &lt;Your Business Name&gt;\'s policy about responding to notices in our Help Center.</p>Your content in our services<p>Some of our Services allow you to upload, submit, store, send or receive content. You retain ownership of any intellectual property rights that you hold in that content. In short, what belongs to you stays yours.</p><p>When you upload, submit, store, send or receive content to or through our Services, you give &lt;Your Business Name&gt; (and those we work with) a worldwide license to use, host, store, reproduce, modify, create derivative works (such as those resulting from translations, adaptations or other changes we make so that your content works better with our Services), communicate, publish, publicly perform, publicly display and distribute such content. The rights you grant in this license are for the limited purpose of operating, promoting, and improving our Services, and to develop new ones. This license continues even if you stop using our Services (for example, for a business listing you have added to &lt;Your Business Name&gt; Maps). Some Services may offer you ways to access and remove content that has been provided to that Service. Also, in some of our Services, there are terms or settings that narrow the scope of our use of the content submitted in those Services. Make sure you have the necessary rights to grant us this license for any content that you submit to our Services.</p>','2025-11-13 23:47:58','2025-11-13 23:47:58');
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_gateways` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_gateways`
--

LOCK TABLES `payment_gateways` WRITE;
/*!40000 ALTER TABLE `payment_gateways` DISABLE KEYS */;
INSERT INTO `payment_gateways` VALUES (1,'Paypal',NULL,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(2,'Stripe',NULL,0,'2025-11-13 23:47:58','2025-11-13 23:47:58'),(3,'Flutterwave',NULL,0,'2025-11-13 23:47:58','2025-11-13 23:47:58');
/*!40000 ALTER TABLE `payment_gateways` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `query_performance_logs`
--

DROP TABLE IF EXISTS `query_performance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `query_performance_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `query_hash` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `query_sql` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `execution_time` decimal(10,6) NOT NULL,
  `rows_examined` int NOT NULL,
  `rows_sent` int NOT NULL,
  `connection_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `controller_action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `query_bindings` json DEFAULT NULL,
  `executed_at` timestamp NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_limit_violations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `rate_limit_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT '1',
  `limit_threshold` int NOT NULL,
  `window_duration` int NOT NULL,
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
  KEY `rate_limit_violations_workspace_id_index` (`workspace_id`),
  CONSTRAINT `rate_limit_violations_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` int NOT NULL DEFAULT '0',
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int DEFAULT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `module` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_uuid_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'3e33faef-b93b-4468-b877-3bf00716df3a','admin','2025-11-13 23:47:58','2025-11-13 23:47:58',NULL),(2,'8ae8e43d-08c0-496c-b8cd-d5053cea7b6c','Staff','2025-11-13 23:47:58','2025-11-13 23:47:58',NULL);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_assessments`
--

DROP TABLE IF EXISTS `security_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `security_assessments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `risk_score` int NOT NULL DEFAULT '0',
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
  KEY `security_assessments_organization_id_index` (`workspace_id`)
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `security_incidents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `audit_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `incident_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high','critical') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `endpoint` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` json DEFAULT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT '0',
  `resolution_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  KEY `security_org_type_created_idx` (`incident_type`,`created_at`),
  KEY `security_incidents_workspace_id_index` (`workspace_id`),
  CONSTRAINT `security_incidents_audit_id_foreign` FOREIGN KEY (`audit_id`) REFERENCES `audit_logs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `security_incidents_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_incidents`
--

LOCK TABLES `security_incidents` WRITE;
/*!40000 ALTER TABLE `security_incidents` DISABLE KEYS */;
/*!40000 ALTER TABLE `security_incidents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seeder_histories`
--

DROP TABLE IF EXISTS `seeder_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seeder_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seeder_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seeder_histories_seeder_name_unique` (`seeder_name`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seeder_histories`
--

LOCK TABLES `seeder_histories` WRITE;
/*!40000 ALTER TABLE `seeder_histories` DISABLE KEYS */;
INSERT INTO `seeder_histories` VALUES (1,'Database\\Seeders\\AddonSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(2,'Database\\Seeders\\AddonsLicenseSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(3,'Database\\Seeders\\AddonsTableSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(4,'Database\\Seeders\\AddonsTableSeeder2','2025-11-13 23:47:58','2025-11-13 23:47:58'),(5,'Database\\Seeders\\AddonsTableSeeder3','2025-11-13 23:47:58','2025-11-13 23:47:58'),(6,'Database\\Seeders\\AddonsTableSeeder4','2025-11-13 23:47:58','2025-11-13 23:47:58'),(7,'Database\\Seeders\\AddonsTableSeeder5','2025-11-13 23:47:58','2025-11-13 23:47:58'),(8,'Database\\Seeders\\AddonsTableSeeder6','2025-11-13 23:47:58','2025-11-13 23:47:58'),(9,'Database\\Seeders\\AddonsTableSeeder7','2025-11-13 23:47:58','2025-11-13 23:47:58'),(10,'Database\\Seeders\\AddonsTableSeeder8','2025-11-13 23:47:58','2025-11-13 23:47:58'),(11,'Database\\Seeders\\AddonsTableSeeder9','2025-11-13 23:47:58','2025-11-13 23:47:58'),(12,'Database\\Seeders\\DemoUsersSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(13,'Database\\Seeders\\EmailTemplateSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(14,'Database\\Seeders\\LanguageTableSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(15,'Database\\Seeders\\LaravelReverbSettingsSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(16,'Database\\Seeders\\ModulesTableSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(17,'Database\\Seeders\\PageSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(18,'Database\\Seeders\\PaymentGatewaysTableSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(19,'Database\\Seeders\\ReverbSettingsSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(20,'Database\\Seeders\\RolesTableSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(21,'Database\\Seeders\\SettingsTableSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(22,'Database\\Seeders\\TicketCategoriesTableSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58'),(23,'Database\\Seeders\\WebhookModuleSeeder','2025-11-13 23:47:58','2025-11-13 23:47:58');
/*!40000 ALTER TABLE `seeder_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('address',NULL),('allow_facebook_login','0'),('allow_google_login','0'),('app_environment','local'),('app_name','Blazz'),('available_version',NULL),('aws_access_key',NULL),('aws_bucket',NULL),('aws_default_region',NULL),('aws_secret_key',NULL),('billing_address',NULL),('billing_city',NULL),('billing_country',NULL),('billing_name',NULL),('billing_phone_1',NULL),('billing_phone_2',NULL),('billing_postal_code',NULL),('billing_state',NULL),('billing_tax_id',NULL),('broadcast_driver','reverb'),('company_name','Blazz'),('currency','USD'),('date_format','d-M-y'),('default_image_api',NULL),('display_frontend','1'),('email',NULL),('enable_ai_billing','0'),('facebook_login',NULL),('favicon',NULL),('google_analytics_status','0'),('google_analytics_tracking_id',NULL),('google_login',NULL),('google_maps_api_key',NULL),('invoice_prefix',NULL),('is_tax_inclusive','1'),('is_update_available','0'),('last_update_check','2025-11-14 06:47:58'),('logo',NULL),('mail_config',NULL),('phone',NULL),('pusher_app_cluster',NULL),('pusher_app_id',NULL),('pusher_app_key',NULL),('pusher_app_secret',NULL),('recaptcha_active','0'),('recaptcha_secret_key',NULL),('recaptcha_site_key',NULL),('release_date',NULL),('reverb_app_id','526180'),('reverb_app_key','ohrtagckj2hqoiocg7wz'),('reverb_app_secret','ag0aapako3p6n90f6etl'),('reverb_host','127.0.0.1'),('reverb_port','8080'),('reverb_scheme','http'),('smtp_email_active','0'),('socials',NULL),('storage_system','local'),('time_format','H:i'),('timezone','UTC'),('title','Blazz - WhatsApp Business Solution'),('trial_period','20'),('verify_email','0'),('version',NULL),('whatsapp_callback_token','20251114064758FvWW');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscription_plans`
--

DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(23,2) NOT NULL,
  `period` enum('monthly','yearly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','deleted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_plans_uuid_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscription_plans`
--

LOCK TABLES `subscription_plans` WRITE;
/*!40000 ALTER TABLE `subscription_plans` DISABLE KEYS */;
INSERT INTO `subscription_plans` VALUES (1,'9c207067-40b4-4e56-8bcd-4c1dc013670d','Free Trial',0.00,'monthly','{\"features\":[\"Basic Features\",\"Limited Messages\",\"1 WhatsApp Session\"],\"limits\":{\"messages_per_month\":1000,\"contacts\":500,\"campaigns\":10}}','active','2025-11-13 23:47:58','2025-11-13 23:47:58',NULL);
/*!40000 ALTER TABLE `subscription_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `plan_id` bigint unsigned DEFAULT NULL,
  `payment_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_date` timestamp NULL DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `status` enum('trial','active') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trial',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscriptions_uuid_unique` (`uuid`),
  KEY `subscriptions_workspace_id_index` (`workspace_id`),
  CONSTRAINT `subscriptions_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
INSERT INTO `subscriptions` VALUES (1,'ad275d85-9b14-49e6-8a11-54cb39061c75',1,1,NULL,'2025-11-13 23:47:58','2025-12-14 06:47:58','trial','2025-11-13 23:47:58','2025-11-13 23:47:58');
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_rates`
--

DROP TABLE IF EXISTS `tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_invites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `email` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invited_by` bigint unsigned NOT NULL,
  `expire_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `team_invites_invited_by_foreign` (`invited_by`),
  KEY `team_invites_workspace_id_index` (`workspace_id`),
  CONSTRAINT `team_invites_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`),
  CONSTRAINT `team_invites_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` enum('owner','manager','agent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manager',
  `status` enum('active','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_uuid_unique` (`uuid`),
  KEY `teams_user_id_foreign` (`user_id`),
  KEY `teams_created_by_foreign` (`created_by`),
  KEY `idx_team_membership_complete` (`user_id`,`role`,`created_at`),
  KEY `teams_workspace_id_index` (`workspace_id`),
  CONSTRAINT `teams_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `teams_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `teams_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

LOCK TABLES `teams` WRITE;
/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
INSERT INTO `teams` VALUES (1,'de830011-50e9-46a8-b8dc-bdde3ec5cdcc',1,2,'owner','active',2,NULL,NULL,'2025-11-13 23:47:58','2025-11-13 23:47:58');
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `templates`
--

DROP TABLE IF EXISTS `templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `meta_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `templates_created_by_foreign` (`created_by`),
  KEY `templates_workspace_id_index` (`workspace_id`),
  CONSTRAINT `templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `templates_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `threat_ips` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `threat_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `confidence_score` int NOT NULL DEFAULT '0',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_categories`
--

LOCK TABLES `ticket_categories` WRITE;
/*!40000 ALTER TABLE `ticket_categories` DISABLE KEYS */;
INSERT INTO `ticket_categories` VALUES (1,'Signup/login issues'),(2,'Campaigns issues'),(3,'Whatsapp issue'),(4,'Template Issues'),(5,'Chatbot Issues'),(6,'Other');
/*!40000 ALTER TABLE `ticket_categories` ENABLE KEYS */;
UNLOCK TABLES;

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
  `message` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `subject` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('critical','high','medium','low') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `status` enum('open','pending','resolved','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `closed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tickets_user_id_foreign` (`user_id`),
  KEY `tickets_category_id_foreign` (`category_id`),
  KEY `tickets_assigned_to_foreign` (`assigned_to`),
  KEY `tickets_closed_by_foreign` (`closed_by`),
  KEY `tickets_workspace_id_index` (`workspace_id`),
  CONSTRAINT `tickets_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `tickets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`),
  CONSTRAINT `tickets_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `tickets_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `facebook_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `phone` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tfa_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tfa` tinyint NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '1',
  `meta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `plan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `plan_id` bigint unsigned DEFAULT NULL,
  `will_expire` date DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_deleted_at_unique` (`email`,`deleted_at`),
  UNIQUE KEY `users_facebook_id_unique` (`facebook_id`),
  KEY `idx_user_verification_timeline` (`email_verified_at`,`created_at`),
  KEY `idx_user_role_timeline` (`role`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','Demo','admin@demo.com',NULL,NULL,'admin','+6281234567890','Jakarta, Indonesia','2025-11-13 23:47:58','$2y$10$KFW.8BlsCTfz1MkAl4U/aeeEFBbvaojlpB043lH7BOZvZkOYb8jdy',NULL,0,1,NULL,NULL,NULL,NULL,NULL,'2025-11-13 23:47:58','2025-11-13 23:47:58',NULL),(2,'Laksmana','Moerdani','ltmoerdani@yahoo.com',NULL,NULL,'user','+6281234567891','Indonesia','2025-11-13 23:47:58','$2y$10$I1jSmLRlnp.uCVmlU7QWI.I8ZFZjB2izWpxhM7HrgNVeUSRH.J6uu','VM3ZSDSV4ZDWH62K6U2P6GQ4GVLH4GEZ',0,1,NULL,NULL,NULL,NULL,NULL,'2025-11-13 23:47:58','2025-11-13 23:51:06',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_accounts`
--

DROP TABLE IF EXISTS `whatsapp_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned NOT NULL,
  `session_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_type` enum('meta','webjs') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'webjs',
  `status` enum('qr_scanning','authenticated','connected','disconnected','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'qr_scanning',
  `qr_code` text COLLATE utf8mb4_unicode_ci,
  `session_data` longtext COLLATE utf8mb4_unicode_ci,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `last_connected_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_sessions_uuid_unique` (`uuid`),
  UNIQUE KEY `whatsapp_sessions_session_id_unique` (`session_id`),
  KEY `whatsapp_sessions_created_by_foreign` (`created_by`),
  KEY `whatsapp_sessions_workspace_id_status_index` (`workspace_id`,`status`),
  KEY `whatsapp_sessions_session_id_status_index` (`session_id`,`status`),
  KEY `whatsapp_sessions_provider_type_is_active_index` (`provider_type`,`is_active`),
  KEY `whatsapp_sessions_workspace_id_is_primary_index` (`workspace_id`,`is_primary`),
  CONSTRAINT `whatsapp_sessions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `whatsapp_sessions_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_accounts`
--

LOCK TABLES `whatsapp_accounts` WRITE;
/*!40000 ALTER TABLE `whatsapp_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_groups`
--

DROP TABLE IF EXISTS `whatsapp_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned NOT NULL,
  `whatsapp_account_id` bigint unsigned NOT NULL,
  `group_jid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'WhatsApp group identifier (e.g., 1234567890-1234567890@g.us)',
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `owner_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Group creator phone number',
  `participants` json NOT NULL COMMENT '[{phone, name, isAdmin, joinedAt}]',
  `invite_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` json DEFAULT NULL COMMENT '{messagesAdminsOnly, editInfoAdminsOnly}',
  `group_created_at` timestamp NULL DEFAULT NULL COMMENT 'When group was created on WhatsApp',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_groups_uuid_unique` (`uuid`),
  UNIQUE KEY `whatsapp_groups_group_jid_unique` (`group_jid`),
  KEY `idx_groups_workspace` (`workspace_id`),
  KEY `idx_groups_session` (`whatsapp_account_id`),
  KEY `idx_groups_workspace_session` (`workspace_id`,`whatsapp_account_id`),
  CONSTRAINT `whatsapp_groups_whatsapp_session_id_foreign` FOREIGN KEY (`whatsapp_account_id`) REFERENCES `whatsapp_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `whatsapp_groups_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_groups`
--

LOCK TABLES `whatsapp_groups` WRITE;
/*!40000 ALTER TABLE `whatsapp_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workspace_api_keys`
--

DROP TABLE IF EXISTS `workspace_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workspace_api_keys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned DEFAULT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organization_api_keys_token_unique` (`token`),
  KEY `organization_api_keys_workspace_id_index` (`workspace_id`),
  CONSTRAINT `organization_api_keys_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workspace_api_keys`
--

LOCK TABLES `workspace_api_keys` WRITE;
/*!40000 ALTER TABLE `workspace_api_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `workspace_api_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workspaces`
--

DROP TABLE IF EXISTS `workspaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workspaces` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `timezone` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspaces_uuid_unique` (`uuid`),
  KEY `workspaces_uuid_index` (`uuid`),
  KEY `workspaces_identifier_index` (`identifier`),
  KEY `workspaces_created_by_index` (`created_by`),
  CONSTRAINT `workspaces_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workspaces`
--

LOCK TABLES `workspaces` WRITE;
/*!40000 ALTER TABLE `workspaces` DISABLE KEYS */;
INSERT INTO `workspaces` VALUES (1,'b4da1694-765c-419f-a703-938099dd2b8d','laksmana-workspace','Laksmana Workspace','Indonesia','{\"created_via\":\"seeder\",\"environment\":\"testing\"}','Asia/Jakarta',2,'2025-11-13 23:47:58','2025-11-13 23:47:58',NULL);
/*!40000 ALTER TABLE `workspaces` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-14 19:11:42
