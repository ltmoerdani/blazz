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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addons`
--

LOCK TABLES `addons` WRITE;
/*!40000 ALTER TABLE `addons` DISABLE KEYS */;
INSERT INTO `addons` VALUES (1,'b0531e42-c063-433a-b7f5-637a74ae93d5','Security','Google Recaptcha','recaptcha.png','Google reCAPTCHA v2 integration',NULL,NULL,NULL,0,0,0,0,'2025-11-09 20:27:51','2025-11-09 20:27:51');
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
INSERT INTO `audit_logs` VALUES ('req_69115aafac40b_6026','req_69115aafac40b_6026','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'yw0c1O3vg9Or05uz2ntl0VGY2xbqUcofP8cae26k','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',500,1295218,440.278,35651584,0,'server_error','2025-11-09 20:23:27','2025-11-09 20:23:28'),('req_69115b5f2b0bf_7922','req_69115b5f2b0bf_7922','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'yw0c1O3vg9Or05uz2ntl0VGY2xbqUcofP8cae26k','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,93.924,31457280,0,'unknown','2025-11-09 20:26:23','2025-11-09 20:26:23'),('req_69115bd5028aa_6726','req_69115bd5028aa_6726','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'yw0c1O3vg9Or05uz2ntl0VGY2xbqUcofP8cae26k','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,370,392.271,31457280,0,'unknown','2025-11-09 20:28:21','2025-11-09 20:28:21'),('req_69115c2911f5a_2588','req_69115c2911f5a_2588','request_attempt','user.workspace.selectWorkspace','POST','http://127.0.0.1:8000/select-workspace','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,NULL,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/select-workspace\", \"expects_json\": false, \"input_summary\": {\"uuid\": \"806e33e9-d284-44ee-972b-afad3256e336\"}, \"accept_language\": \"en-US,en;q=0.6\"}',302,370,13.120,29360128,0,'unknown','2025-11-09 20:29:45','2025-11-09 20:29:45'),('req_69115d1b9de3b_6550','req_69115d1b9de3b_6550','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',500,21149,99.443,33554432,0,'server_error','2025-11-09 20:33:47','2025-11-09 20:33:47'),('req_69115f3b247fd_2859','req_69115f3b247fd_2859','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',403,101,42.480,31457280,0,'client_error','2025-11-09 20:42:51','2025-11-09 20:42:51'),('req_69115f60212cd_7142','req_69115f60212cd_7142','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',403,101,26.295,31457280,0,'client_error','2025-11-09 20:43:28','2025-11-09 20:43:28'),('req_691168591baa5_1345','req_691168591baa5_1345','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',403,101,35.419,31457280,0,'client_error','2025-11-09 21:21:45','2025-11-09 21:21:45'),('req_69116e686cb94_2151','req_69116e686cb94_2151','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',403,101,27.445,31457280,0,'client_error','2025-11-09 21:47:36','2025-11-09 21:47:36'),('req_69116fe0de398_8358','req_69116fe0de398_8358','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,290,104.624,31457280,1,'success','2025-11-09 21:53:52','2025-11-09 21:53:53'),('req_691170682ef3c_7442','req_691170682ef3c_7442','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,290,72.801,31457280,1,'success','2025-11-09 21:56:08','2025-11-09 21:56:08'),('req_691171006dd75_2650','req_691171006dd75_2650','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,417,66.640,31457280,1,'success','2025-11-09 21:58:40','2025-11-09 21:58:40'),('req_6911746f79201_5112','req_6911746f79201_5112','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,417,71.530,31457280,1,'success','2025-11-09 22:13:19','2025-11-09 22:13:19'),('req_691175f7aa29b_9762','req_691175f7aa29b_9762','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'yMLwKeJ0Dl5p5zvMBJMAzj6J1U9ijo2m1td0pw54','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,73.861,29360128,1,'success','2025-11-09 22:19:51','2025-11-09 22:19:51'),('req_6911760d666ba_4118','req_6911760d666ba_4118','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'sQ9GraXAlzXg3WLjqLvUFdLFpLWr7OzKnVWcCfXe','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,107.562,29360128,1,'success','2025-11-09 22:20:13','2025-11-09 22:20:13'),('req_69117815c159c_7956','req_69117815c159c_7956','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,417,64.648,31457280,1,'success','2025-11-09 22:28:53','2025-11-09 22:28:53'),('req_69117b58e1bd8_5633','req_69117b58e1bd8_5633','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,417,85.903,31457280,1,'success','2025-11-09 22:42:48','2025-11-09 22:42:49'),('req_691186de0946d_2551','req_691186de0946d_2551','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',NULL,NULL,NULL,NULL,NULL,NULL,'2025-11-09 23:31:58','2025-11-09 23:31:58'),('req_69118705e25bd_7002','req_69118705e25bd_7002','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,443,9165.993,31457280,1,'success','2025-11-09 23:32:37','2025-11-09 23:32:47'),('req_6911870f4964a_6193','req_6911870f4964a_6193','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'bDXDp1OiPsPfanaC1EdlFDQDRdJEgZSMC1ej2fNR','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,45.377,29360128,1,'success','2025-11-09 23:32:47','2025-11-09 23:32:47'),('req_6911874b10c1c_6657','req_6911874b10c1c_6657','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'KOi6pHiLEZ7xSrzg2jaFrkqpd265ALWWYqfoCUPH','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,77.330,29360128,1,'success','2025-11-09 23:33:47','2025-11-09 23:33:47'),('req_6911875f1de92_7042','req_6911875f1de92_7042','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ZIyRATSb4KTLvfvTXLHq7IWRf3MfYz1D77obCBig','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,71.561,29360128,1,'success','2025-11-09 23:34:07','2025-11-09 23:34:07'),('req_691187730e3d8_9881','req_691187730e3d8_9881','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'r8Ul561huFkImDNgyGsApQB1SlAditCXHpSX5oOK','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,48.377,29360128,1,'success','2025-11-09 23:34:27','2025-11-09 23:34:27'),('req_691187872d8dd_8022','req_691187872d8dd_8022','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ZNqA8V0ucSTXdLsIMq7L10KMn3g5fUSU1dWuamZe','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,80.403,29360128,1,'success','2025-11-09 23:34:47','2025-11-09 23:34:47'),('req_6911879b1c481_4554','req_6911879b1c481_4554','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Q5CG6Mqzyr7OcoBHKM2blyb5Nl1In7grTU4c9ICX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,62.529,29360128,1,'success','2025-11-09 23:35:07','2025-11-09 23:35:07'),('req_691187e2725e7_6827','req_691187e2725e7_6827','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'zazqTcdUhatD98DitCtl99vHKlxAukwiAYYKSVLd','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,73.484,29360128,1,'success','2025-11-09 23:36:18','2025-11-09 23:36:18'),('req_691188e4497d5_6269','req_691188e4497d5_6269','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'KJWyBmhdjhGJyEu2rFj2TGiTONXfrXJuBscfpdLT','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,81.787,29360128,1,'success','2025-11-09 23:40:36','2025-11-09 23:40:36'),('req_691188ec55d2c_5981','req_691188ec55d2c_5981','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,443,6862.468,31457280,1,'success','2025-11-09 23:40:44','2025-11-09 23:40:51'),('req_691188f369ac8_6792','req_691188f369ac8_6792','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'2PZTxPz7Kxm6vMSZ0CoqP8qNVP0OO8AJNtLpcN5T','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,83.854,29360128,1,'success','2025-11-09 23:40:51','2025-11-09 23:40:51'),('req_691189203f65b_2152','req_691189203f65b_2152','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Gdi6cZEAp25Rx7sw3kioKdePAWDxh6cmVGj7vrT9','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,53.381,29360128,1,'success','2025-11-09 23:41:36','2025-11-09 23:41:36'),('req_6911892f2857b_9779','req_6911892f2857b_9779','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'BqAhUfQbUwzpVu0I87GTJLCO7VzdJiI3xuU5KmPg','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,50.908,29360128,1,'success','2025-11-09 23:41:51','2025-11-09 23:41:51'),('req_691189344db10_6904','req_691189344db10_6904','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'OVTtZKjsml3xr8jRfv0aocTnhX0uS8ookNZmL59c','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,48.848,29360128,1,'success','2025-11-09 23:41:56','2025-11-09 23:41:56'),('req_691189434766a_5873','req_691189434766a_5873','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'tUIFXjAVqYqWcN48Th060aF0OeKR06m4cMhKQtrW','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,96.875,29360128,1,'success','2025-11-09 23:42:11','2025-11-09 23:42:11'),('req_691189484c8e0_6460','req_691189484c8e0_6460','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'5vbWwx0pyXHL14sduHOOrYsGa7qyXSBn5cmjWXSe','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,51.229,29360128,1,'success','2025-11-09 23:42:16','2025-11-09 23:42:16'),('req_6911895734530_4436','req_6911895734530_4436','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'yKSonp1hqsWrSbiWzUXMsJRuGxUYfk4qbU74qwXY','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,62.002,29360128,1,'success','2025-11-09 23:42:31','2025-11-09 23:42:31'),('req_6911895c4e459_2167','req_6911895c4e459_2167','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'M3oFWj3Z2NPiO7zMGXihE0KzQGc8IMnHJnNjWfT0','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,63.484,29360128,1,'success','2025-11-09 23:42:36','2025-11-09 23:42:36'),('req_6911896b36979_9541','req_6911896b36979_9541','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'fFAw6RNoXkBznN0xoMHB5RjG8rjGdzcYQTZXrEeF','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,51.297,29360128,1,'success','2025-11-09 23:42:51','2025-11-09 23:42:51'),('req_6911897056c63_4445','req_6911897056c63_4445','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'yODfskHcUUQFBBR7z65Gsad56e0MbxIFY9VYRnEy','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,55.893,29360128,1,'success','2025-11-09 23:42:56','2025-11-09 23:42:56'),('req_6911897f2dd2b_9991','req_6911897f2dd2b_9991','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'X37X6k6tTbkr5PQZIX50aV0ppGXf10m0xv3qICGQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,44.895,29360128,1,'success','2025-11-09 23:43:11','2025-11-09 23:43:11'),('req_691189b7970f5_6750','req_691189b7970f5_6750','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'AE0CZhWQYvoGpxKqGZmZsObai9gEwxajv1wOcQwz','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,62.842,29360128,1,'success','2025-11-09 23:44:07','2025-11-09 23:44:07'),('req_691189c66ebc9_3499','req_691189c66ebc9_3499','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'0yzdkNJnsNSnlsVggUfOHBFvGmjjO9kzwZjX1b4x','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,63.218,29360128,1,'success','2025-11-09 23:44:22','2025-11-09 23:44:22'),('req_691189f3a5914_3770','req_691189f3a5914_3770','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'QpJ130BnbkYyi63aVjDuQzHXB6IwkWsPhteR0hJw','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,77.547,29360128,1,'success','2025-11-09 23:45:07','2025-11-09 23:45:07'),('req_69118a02722ad_3222','req_69118a02722ad_3222','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ErwnKz4dJuT9TVgLKeA4wCxHKbqLTO5B2Ma3OZIW','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,46.336,29360128,1,'success','2025-11-09 23:45:22','2025-11-09 23:45:22'),('req_69118a07889ed_2148','req_69118a07889ed_2148','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'lmbyAMoNo5t9QTyWXwmVW4BuA0eqnRhWbl1skO6P','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,46.665,29360128,1,'success','2025-11-09 23:45:27','2025-11-09 23:45:27'),('req_69118a1673d8e_6330','req_69118a1673d8e_6330','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'JLzjZog6kH3yErgc7qTDhQuS96k7mP8sSa3cf2Ke','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,43.616,29360128,1,'success','2025-11-09 23:45:42','2025-11-09 23:45:42'),('req_69118a1b95868_7985','req_69118a1b95868_7985','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'QlCOXFFJSO19pMoNxTidb6cGDacmbvzaN9NZtAR1','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,66.402,29360128,1,'success','2025-11-09 23:45:47','2025-11-09 23:45:47'),('req_69118a2a860b7_5125','req_69118a2a860b7_5125','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'70mEjoHmBfQEUvP6xThaKUMgG8m27OzpZkjp87RN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,43.899,29360128,1,'success','2025-11-09 23:46:02','2025-11-09 23:46:02'),('req_69118a2f90adb_8726','req_69118a2f90adb_8726','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'qdXcwbjldHY4cZiWnIP2VuRURFVxKqFfoKGq6gmV','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,46.810,29360128,1,'success','2025-11-09 23:46:07','2025-11-09 23:46:07'),('req_69118a3e787b6_9113','req_69118a3e787b6_9113','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Zm7ZP2pdlKG3KIU0APotcBULdbwiuv8KfHiXP3r9','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,45.058,29360128,1,'success','2025-11-09 23:46:22','2025-11-09 23:46:22'),('req_69118a438d6a4_8284','req_69118a438d6a4_8284','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'zQDOPyykV9kPwg3d9CqZJsTehab6eJNly4EmJ4Wb','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,47.149,29360128,1,'success','2025-11-09 23:46:27','2025-11-09 23:46:27'),('req_69118a527a637_7955','req_69118a527a637_7955','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'v7d9Q1kecgabkLb8cy1avtkyW1uebbPB81zaACCF','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,44.106,29360128,1,'success','2025-11-09 23:46:42','2025-11-09 23:46:42'),('req_69118a8ad5ffb_8407','req_69118a8ad5ffb_8407','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Qj0YDz7oay6jH0z4GV3tDIlcL7dVSgSQ4EZsosbN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,64.111,29360128,1,'success','2025-11-09 23:47:38','2025-11-09 23:47:38'),('req_69118a9a1dfe7_7099','req_69118a9a1dfe7_7099','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'bnYAzKdzisYeLdnb97OJSOhVQbpmKITwtjxRauWa','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,66.277,29360128,1,'success','2025-11-09 23:47:54','2025-11-09 23:47:54'),('req_691191e00507b_1273','req_691191e00507b_1273','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,443,29091.145,31457280,1,'success','2025-11-10 00:18:56','2025-11-10 00:19:25'),('req_691191fd64fbb_6576','req_691191fd64fbb_6576','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'5AuwbCpy0gSeJiqAq8fB178H1StHa06AsWqFkiJS','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,59.802,29360128,1,'success','2025-11-10 00:19:25','2025-11-10 00:19:25'),('req_6911921680aaa_8283','req_6911921680aaa_8283','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'nteKltJ26oxQX8nNDLBS2VuugLbKTXR4xdvlABKg','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,443,6411.562,31457280,1,'success','2025-11-10 00:19:50','2025-11-10 00:19:56'),('req_6911921d2359e_8041','req_6911921d2359e_8041','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'tmeAU2D57VlTg3KMd2nKnxtGiakmiWINIl898vyy','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,53.996,29360128,1,'success','2025-11-10 00:19:57','2025-11-10 00:19:57'),('req_691193545096c_2900','req_691193545096c_2900','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'SJnzlHlISMBKjuIJPr7gXyw4OnNZ6Hg2WNmw3OlQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,74.043,29360128,1,'success','2025-11-10 00:25:08','2025-11-10 00:25:08'),('req_69119496bfcbc_7904','req_69119496bfcbc_7904','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'2aiU4cAhUWy65T2qsyOuGL0xoppeqj5NMFOcFA2G','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,71.594,29360128,1,'success','2025-11-10 00:30:30','2025-11-10 00:30:30'),('req_691195ce5c2cd_9781','req_691195ce5c2cd_9781','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'0PN0MfTms3wgx1gBj7tXE34sw100UYWWtfLYFTXG','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,83.926,29360128,1,'success','2025-11-10 00:35:42','2025-11-10 00:35:42'),('req_69119711dd0d6_8479','req_69119711dd0d6_8479','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'902mOZLoIQFNAcoqcW68IpPy7q6vw5njbnhfqJyD','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,72.483,29360128,1,'success','2025-11-10 00:41:05','2025-11-10 00:41:05'),('req_691198492eacb_7680','req_691198492eacb_7680','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Jigpw4f5HJDByyaG6D5TxX6yJXE8SuTlEcc8Ydfc','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,77.440,29360128,1,'success','2025-11-10 00:46:17','2025-11-10 00:46:17'),('req_6911998bf2caf_7077','req_6911998bf2caf_7077','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'KMCx8b279MRZdN1FjLYJVr8Ly3o0fALK0I3ZXZM6','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,89.824,29360128,1,'success','2025-11-10 00:51:40','2025-11-10 00:51:40'),('req_69119ac3640d5_8638','req_69119ac3640d5_8638','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'lLiB8XD7O3E4J4q1CKQMQ2fYLNqdKTi6nFDZw1Bv','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,61.617,29360128,1,'success','2025-11-10 00:56:51','2025-11-10 00:56:51'),('req_69119c061468b_7673','req_69119c061468b_7673','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'01GGR1BQLyo1WyPaY7wIidxF2HqQhsUyuLvLdvSl','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,66.531,29360128,1,'success','2025-11-10 01:02:14','2025-11-10 01:02:14'),('req_69119d3d4c16e_1817','req_69119d3d4c16e_1817','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'zRlCgcSzZ5N0wiaMATFqhnXvcLkxP2N5yE8KmZ0W','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,66.743,29360128,1,'success','2025-11-10 01:07:25','2025-11-10 01:07:25'),('req_69119e80160f7_7575','req_69119e80160f7_7575','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'k1s00sM9VwpYVrf9kcCpzjYAqaU3LRxSFiA7yasr','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,71.589,29360128,1,'success','2025-11-10 01:12:48','2025-11-10 01:12:48'),('req_69119fb73d0c1_1140','req_69119fb73d0c1_1140','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'N5BvstTexgL2nhrIM4n61XAEBpagryTFa88bFYvR','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,73.500,29360128,1,'success','2025-11-10 01:17:59','2025-11-10 01:17:59'),('req_6911a0fa6656a_3497','req_6911a0fa6656a_3497','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'TlCcCa10QTMO01Bm3heLckb3lpG73ZytJ5mnb3eA','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,67.957,29360128,1,'success','2025-11-10 01:23:22','2025-11-10 01:23:22'),('req_6911a23197190_4734','req_6911a23197190_4734','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'XYJbAQc72TvteV7hYRqu9abs8ZWcwPuKP94vSYQT','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,80.863,29360128,1,'success','2025-11-10 01:28:33','2025-11-10 01:28:33'),('req_6911a3840d4ae_9706','req_6911a3840d4ae_9706','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'MIri94zhvqY7aTb3SRW4QJas96KXeKXvvQ9pz35K','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,83.477,29360128,1,'success','2025-11-10 01:34:12','2025-11-10 01:34:12'),('req_6911a4efad2ed_5981','req_6911a4efad2ed_5981','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'504HqIitDi6PAIngjcAbyLRKv3j95mccvZEYi3yA','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,69.203,29360128,1,'success','2025-11-10 01:40:15','2025-11-10 01:40:15'),('req_6911a6274ce5f_3625','req_6911a6274ce5f_3625','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'KUXjO63cJZFWfBTtOw4Qu62MkeuZJ9HRZFVXubxL','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,72.121,29360128,1,'success','2025-11-10 01:45:27','2025-11-10 01:45:27'),('req_6911a7699b435_3436','req_6911a7699b435_3436','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'6MJjhlboW5dZLFubSSciiWjwobu2gTwybJ9QIIi6','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,60.176,29360128,1,'success','2025-11-10 01:50:49','2025-11-10 01:50:49'),('req_6911a8a137b72_6394','req_6911a8a137b72_6394','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'uh2silqULD9deFUumMeestokhjxlvj0PuZoaDovW','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,69.481,29360128,1,'success','2025-11-10 01:56:01','2025-11-10 01:56:01'),('req_6911a9e39ce70_5900','req_6911a9e39ce70_5900','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'tnyVCEPDghQSc8UQ18Kzt1iMKqQCeawEbfLXMCrU','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,68.837,29360128,1,'success','2025-11-10 02:01:23','2025-11-10 02:01:23'),('req_6911ab1b148d2_4353','req_6911ab1b148d2_4353','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'GhO6GXO6EUKs1Q0YAvRLiR8X9Q4LInK2nZz75NqW','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,64.961,29360128,1,'success','2025-11-10 02:06:35','2025-11-10 02:06:35'),('req_6911ac5d8e054_3097','req_6911ac5d8e054_3097','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'fPFZR2cM9CIMKFi1R8x9vsriqLRZqgBX8AW64ixe','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,80.949,29360128,1,'success','2025-11-10 02:11:57','2025-11-10 02:11:57'),('req_6911ad954acfb_9866','req_6911ad954acfb_9866','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'iVozATkPyGdeXqbAjlxwp0WH4QfhSCW9rp9UTjFY','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,90.833,29360128,1,'success','2025-11-10 02:17:09','2025-11-10 02:17:09'),('req_6911aed84221f_8335','req_6911aed84221f_8335','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'wZ3XRBxXyGArxCHzjQ3P2TPfMJeA2hgxbUQIWrF0','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,62.446,29360128,1,'success','2025-11-10 02:22:32','2025-11-10 02:22:32'),('req_6911c7d116d02_6384','req_6911c7d116d02_6384','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'0gZqWoTcAKxzdNPGtTDZAhygGOsOtIBVCPQOGOBB','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,345.410,29360128,0,'unknown','2025-11-10 04:09:05','2025-11-10 04:09:05'),('req_6911c7dd26def_4282','req_6911c7dd26def_4282','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'0gZqWoTcAKxzdNPGtTDZAhygGOsOtIBVCPQOGOBB','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"admin@demo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,209.187,29360128,0,'unknown','2025-11-10 04:09:17','2025-11-10 04:09:17'),('req_6911c80ef38f3_2164','req_6911c80ef38f3_2164','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'0gZqWoTcAKxzdNPGtTDZAhygGOsOtIBVCPQOGOBB','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,220.851,29360128,0,'unknown','2025-11-10 04:10:06','2025-11-10 04:10:07'),('req_6911c92e41706_5121','req_6911c92e41706_5121','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'0gZqWoTcAKxzdNPGtTDZAhygGOsOtIBVCPQOGOBB','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,370,275.381,31457280,0,'unknown','2025-11-10 04:14:54','2025-11-10 04:14:54'),('req_691280ba7619c_3981','req_691280ba7619c_3981','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'FXfqZsrzZhPHwWZ3JizlpX6npxzO59WDeSJDM1Am','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,354,659.274,31457280,0,'unknown','2025-11-10 17:18:02','2025-11-10 17:18:03'),('req_6912819b86114_2303','req_6912819b86114_2303','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'FXfqZsrzZhPHwWZ3JizlpX6npxzO59WDeSJDM1Am','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,398,300.879,31457280,0,'unknown','2025-11-10 17:21:47','2025-11-10 17:21:47'),('req_691281a3cc7ba_7995','req_691281a3cc7ba_7995','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'goZjyslfWFnWwh0HDyp1SFU1QwHohOTnhKQ41ubK','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp/sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',NULL,NULL,NULL,NULL,NULL,NULL,'2025-11-10 17:21:55','2025-11-10 17:21:55'),('req_691281db8e4d8_2313','req_691281db8e4d8_2313','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'goZjyslfWFnWwh0HDyp1SFU1QwHohOTnhKQ41ubK','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp/sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,443,9409.266,33554432,1,'success','2025-11-10 17:22:51','2025-11-10 17:23:00'),('req_691281e53a4c8_3653','req_691281e53a4c8_3653','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'V3PAIsrV5pfu8ta7gJJQqtjop64cBRxHSzAggjRL','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,87.071,31457280,1,'success','2025-11-10 17:23:01','2025-11-10 17:23:01'),('req_6912822f00fcc_8834','req_6912822f00fcc_8834','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'QBMarkMhgJeQKAoeDQcBAU3w4s8apO6YBY4U6FTV','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"status\": \"authenticated\", \"session_id\": \"webjs_1_1762820571_o6D3dXlF\", \"workspace_id\": 1}, \"event\": \"session_authenticated\"}, \"accept_language\": null}',200,21,85.721,31457280,1,'success','2025-11-10 17:24:15','2025-11-10 17:24:15'),('req_6912823056e24_9177','req_6912823056e24_9177','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'7hGeav04HTHQNezox3IJhXac2muse3aLD3pM3ySY','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"status\": \"connected\", \"session_id\": \"webjs_1_1762820571_o6D3dXlF\", \"phone_number\": \"62816108641\", \"workspace_id\": 1}, \"event\": \"session_ready\"}, \"accept_language\": null}',200,21,66.429,31457280,1,'success','2025-11-10 17:24:16','2025-11-10 17:24:16'),('req_69128230b0263_2750','req_69128230b0263_2750','request_attempt','whatsapp.sessions.set-primary','POST','http://127.0.0.1:8000/settings/whatsapp-sessions/b5f5a67e-d080-4013-82cc-caf4947928ec/set-primary','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'goZjyslfWFnWwh0HDyp1SFU1QwHohOTnhKQ41ubK','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp/sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',500,20694,25.861,31457280,0,'server_error','2025-11-10 17:24:16','2025-11-10 17:24:16'),('req_6912823138557_7512','req_6912823138557_7512','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/chats/sync','127.0.0.1','WhatsApp-WebJS-Service/1.0',NULL,NULL,'zMKpxsCJlFpzp5TQ6BjUxkmtwUyCDSmu4dOAPxRh','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',500,12709,34.478,29360128,0,'server_error','2025-11-10 17:24:17','2025-11-10 17:24:17'),('req_6912823283677_3467','req_6912823283677_3467','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/chats/sync','127.0.0.1','WhatsApp-WebJS-Service/1.0',NULL,NULL,'iMf4Jte8RsXfZXolKiYLKoayUDsyVsyJYe3bX9i9','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',500,12709,28.012,29360128,0,'server_error','2025-11-10 17:24:18','2025-11-10 17:24:18'),('req_69128234ccee1_3816','req_69128234ccee1_3816','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/chats/sync','127.0.0.1','WhatsApp-WebJS-Service/1.0',NULL,NULL,'GdQZGhFbDtHjkJyGUm5mqTK0UTOdCsHa3Urdt7Io','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',500,12709,25.595,29360128,0,'server_error','2025-11-10 17:24:20','2025-11-10 17:24:20'),('req_6912823af1a28_5055','req_6912823af1a28_5055','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Esw409bpGVrUzNo1CGBUXt2qAkkrsybO0McoGQji','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"status\": \"authenticated\", \"session_id\": \"webjs_1_1762820571_o6D3dXlF\", \"workspace_id\": 1}, \"event\": \"session_authenticated\"}, \"accept_language\": null}',200,21,109.320,31457280,1,'success','2025-11-10 17:24:26','2025-11-10 17:24:27'),('req_6912823b4edfc_6306','req_6912823b4edfc_6306','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'03amlXwnzGejOI37WMqPMFllhwy4juoHgzjlqpF4','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"status\": \"authenticated\", \"session_id\": \"webjs_1_1762820571_o6D3dXlF\", \"workspace_id\": 1}, \"event\": \"session_authenticated\"}, \"accept_language\": null}',200,21,47.180,31457280,1,'success','2025-11-10 17:24:27','2025-11-10 17:24:27'),('req_6912823ba017d_1413','req_6912823ba017d_1413','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'yshw7Y0OyitrNcZtn71AiECqho6gxMDRcS4IYqtd','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"status\": \"authenticated\", \"session_id\": \"webjs_1_1762820571_o6D3dXlF\", \"workspace_id\": 1}, \"event\": \"session_authenticated\"}, \"accept_language\": null}',200,21,51.702,31457280,1,'success','2025-11-10 17:24:27','2025-11-10 17:24:27'),('req_6912823bee32c_2055','req_6912823bee32c_2055','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'IvI2nnGB5a0T3aSR8qZp3LFKcXiyR3TP9ypKD5li','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"status\": \"connected\", \"session_id\": \"webjs_1_1762820571_o6D3dXlF\", \"phone_number\": \"62816108641\", \"workspace_id\": 1}, \"event\": \"session_ready\"}, \"accept_language\": null}',200,21,86.650,31457280,1,'success','2025-11-10 17:24:27','2025-11-10 17:24:28'),('req_6912823c63b43_5273','req_6912823c63b43_5273','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Hq3McC9EWuTQFlGv3aV5V3fuwrku3A25GI5Ilw7s','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"status\": \"connected\", \"session_id\": \"webjs_1_1762820571_o6D3dXlF\", \"phone_number\": \"62816108641\", \"workspace_id\": 1}, \"event\": \"session_ready\"}, \"accept_language\": null}',200,21,71.134,31457280,1,'success','2025-11-10 17:24:28','2025-11-10 17:24:28'),('req_6912823cb396a_5648','req_6912823cb396a_5648','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'klvUY0jLolKxzqtUCUudLenFJYp9ZhKZP6F9ocyz','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"status\": \"connected\", \"session_id\": \"webjs_1_1762820571_o6D3dXlF\", \"phone_number\": \"62816108641\", \"workspace_id\": 1}, \"event\": \"session_ready\"}, \"accept_language\": null}',200,21,53.870,31457280,1,'success','2025-11-10 17:24:28','2025-11-10 17:24:28'),('req_69128313b5dea_1726','req_69128313b5dea_1726','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'pl3ZQqXLVhxcZVa1DucuGb07PnIRntlH6cEVHHE4','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,70.625,31457280,1,'success','2025-11-10 17:28:03','2025-11-10 17:28:03'),('req_6912844b77c35_1029','req_6912844b77c35_1029','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Cll1JqvyHZ4SllrGdClhEbdXFdFINxhD5szZkFuv','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,95.817,31457280,1,'success','2025-11-10 17:33:15','2025-11-10 17:33:15'),('req_6912858e3a923_4996','req_6912858e3a923_4996','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'HiusUIUHki54HAqODoaPFZcN7wIFHhe148Q5INnX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,68.539,31457280,1,'success','2025-11-10 17:38:38','2025-11-10 17:38:38'),('req_691286c56b525_5591','req_691286c56b525_5591','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ZPG0a3aNbgmj50EVWrMyHW6Pbo4G2m7IGUJ0HFpZ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,81.895,31457280,1,'success','2025-11-10 17:43:49','2025-11-10 17:43:49'),('req_691288081adc2_2881','req_691288081adc2_2881','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'wRB52wtmKUJWigVSG2vmd1wnvHD66mbWEkJkq4V2','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,76.916,31457280,1,'success','2025-11-10 17:49:12','2025-11-10 17:49:12'),('req_691289403d042_5028','req_691289403d042_5028','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'7l5XCb6jicK6vGgcgd1iJ704zvdgE8dR9BkjLdEC','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,67.510,31457280,1,'success','2025-11-10 17:54:24','2025-11-10 17:54:24'),('req_69128b26d9dbe_8084','req_69128b26d9dbe_8084','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'bn0tFcsueX26rJ03IEAf7RMFXMbxtJqPaNwvVpEG','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,74.137,31457280,1,'success','2025-11-10 18:02:30','2025-11-10 18:02:30'),('req_69128c5e95578_2023','req_69128c5e95578_2023','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'jUVTQqmEr7wZm8vNcDa8yFKj1DFFsKxCv9Deo0g6','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,87.836,31457280,1,'success','2025-11-10 18:07:42','2025-11-10 18:07:42'),('req_69128da16c7da_3250','req_69128da16c7da_3250','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'zphHRyiorvrry8HQ9ToPHnOUlQWQ01HXAljANQ91','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,81.644,31457280,1,'success','2025-11-10 18:13:05','2025-11-10 18:13:05'),('req_69128ed91c922_8027','req_69128ed91c922_8027','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'bkyef9XySm1kHva0UaApKdkqAlpzaK6y6Vgkzcqw','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,113.267,31457280,1,'success','2025-11-10 18:18:17','2025-11-10 18:18:17'),('req_6912901b83c37_4605','req_6912901b83c37_4605','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'6Fm9crmWF7Q8qNYzXYeb9Fa3gKKWvaTMcPRhACTf','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,100.469,31457280,1,'success','2025-11-10 18:23:39','2025-11-10 18:23:39'),('req_69129152c4601_8218','req_69129152c4601_8218','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ar4hlDBwQYP6D7CeGza4mgWiif1ettm93cpIh3ah','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,112.950,31457280,1,'success','2025-11-10 18:28:50','2025-11-10 18:28:50'),('req_6912929573a84_1596','req_6912929573a84_1596','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'d8s7lvZauKLKkpGV88SGGhgIr41yc4Tuce7E4HWV','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,98.529,31457280,1,'success','2025-11-10 18:34:13','2025-11-10 18:34:13'),('req_691293ccb57c4_9246','req_691293ccb57c4_9246','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'DPnjFKKobX2xedD0ocAFeXNLDBzYxNZ385YUmL0L','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,80.609,31457280,1,'success','2025-11-10 18:39:24','2025-11-10 18:39:24'),('req_69129510e0064_4531','req_69129510e0064_4531','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'xmfUw3QObycpaN9Juwbn6DG2Vu3Vlgy3wvkK3ZuK','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,130.264,31457280,1,'success','2025-11-10 18:44:48','2025-11-10 18:44:49'),('req_6912967b84377_4353','req_6912967b84377_4353','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'oPpH3fT057sVXM1hqelnSaavgCej0JnDiyruPiCK','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,85.121,31457280,1,'success','2025-11-10 18:50:51','2025-11-10 18:50:51'),('req_691297b3238e4_6650','req_691297b3238e4_6650','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'i3z13auHMrbPnfvTa1dHfZiOnDB3ObACxBEMA85Y','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,80.246,31457280,1,'success','2025-11-10 18:56:03','2025-11-10 18:56:03'),('req_691298f61c8c9_5497','req_691298f61c8c9_5497','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'1XMhC843YBlap6xJEZ8jM276dyAA9UcxlV5ghlx2','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,79.798,31457280,1,'success','2025-11-10 19:01:26','2025-11-10 19:01:26'),('req_69129a2d4fe6b_3665','req_69129a2d4fe6b_3665','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'XOSEZhJYFv6O8lcvJ2G5OWltrgewmuGWaZ0Bdv1c','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,84.945,31457280,1,'success','2025-11-10 19:06:37','2025-11-10 19:06:37'),('req_69129b702487f_8134','req_69129b702487f_8134','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'EL804tyA1cu4rpPOU9dnU62JNC9311SnQkomJHQu','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,76.730,31457280,1,'success','2025-11-10 19:12:00','2025-11-10 19:12:00'),('req_69129ca760107_2077','req_69129ca760107_2077','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'8tmAlXgd44k8CzPh02Hfk5cvOIQk1AahWDUHfg56','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,97.970,31457280,1,'success','2025-11-10 19:17:11','2025-11-10 19:17:11'),('req_6912ac972adeb_6243','req_6912ac972adeb_6243','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'oLlsFb3SOdjqqJkohbyUOiSIgMkkgzoy9IpLS8by','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,438,347.335,31457280,0,'unknown','2025-11-10 20:25:11','2025-11-10 20:25:11'),('req_6916749168121_6175','req_6916749168121_6175','request_attempt','login.submit','POST','http://127.0.0.1:8000/login','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',NULL,NULL,'lPEKfEHlrlyMn2RTncAnHK1BXqwXNkESW0ulDeFw','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/login\", \"expects_json\": false, \"input_summary\": {\"email\": \"ltmoerdani@yahoo.com\", \"recaptcha_response\": null}, \"accept_language\": \"en-US,en;q=0.6\"}',302,370,585.743,31457280,0,'unknown','2025-11-13 17:15:13','2025-11-13 17:15:14'),('req_6916749958bb0_7822','req_6916749958bb0_7822','request_attempt','whatsapp.sessions.store','POST','http://127.0.0.1:8000/settings/whatsapp-sessions','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'LAKyh3VgUwKedoWNA9Wgo36uZQXEm8ByTeohecFW','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1, \"provider_type\": \"webjs\"}, \"accept_language\": \"en-US,en;q=0.6\"}',201,443,32312.466,33554432,1,'success','2025-11-13 17:15:21','2025-11-13 17:15:53'),('req_691674ba0d25d_8684','req_691674ba0d25d_8684','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'AxIAr4c0g4P9fjO7p8GYO2Dl1wH9Ib3c5UbkCzlp','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,74.080,31457280,1,'success','2025-11-13 17:15:54','2025-11-13 17:15:54'),('req_691674fcad445_1852','req_691674fcad445_1852','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'2tWDlm0hYivOYVpPeUe2Ye93BnbbFEoisb9DDC0K','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"status\": \"authenticated\", \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"session_authenticated\"}, \"accept_language\": null}',200,21,57.865,31457280,1,'success','2025-11-13 17:17:00','2025-11-13 17:17:00'),('req_691674fdb135f_8522','req_691674fdb135f_8522','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'NtkgstM40jemcSrCuZ3sTEccSrZmawN2MPgSXi5D','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"status\": \"connected\", \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"phone_number\": \"62811801641\", \"workspace_id\": 1}, \"event\": \"session_ready\"}, \"accept_language\": null}',200,21,66.977,31457280,1,'success','2025-11-13 17:17:01','2025-11-13 17:17:01'),('req_691674fe10c86_3028','req_691674fe10c86_3028','request_attempt','whatsapp.sessions.set-primary','POST','http://127.0.0.1:8000/settings/whatsapp-sessions/d9662fd6-96b2-49c1-bab6-bda4e28b9a42/set-primary','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',2,1,'LAKyh3VgUwKedoWNA9Wgo36uZQXEm8ByTeohecFW','{\"is_ajax\": true, \"referer\": \"http://127.0.0.1:8000/settings/whatsapp-sessions\", \"expects_json\": true, \"input_summary\": {\"workspace\": 1}, \"accept_language\": \"en-US,en;q=0.6\"}',500,20694,31.645,31457280,0,'server_error','2025-11-13 17:17:02','2025-11-13 17:17:02'),('req_6916750744c65_6102','req_6916750744c65_6102','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'CBi9Oe3HpBqwIRcmIuh5IzBxNXXl38IrIysrNzhx','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC437CDE6970638DD1B09B5408A7C64E_6281281537273@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1762998881, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Agung Priyambodo\", \"sender_phone\": \"6281281537273\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,62.091,29360128,1,'success','2025-11-13 17:17:11','2025-11-13 17:17:11'),('req_691675079813f_5241','req_691675079813f_5241','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'yVqvQF8b55T0apHme11m8PSiGA3BbDpss5GqF6Ml','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_ACA626D2254450F7B502F2B84CEBE4A0_6281281537273@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1762998881, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Agung Priyambodo\", \"sender_phone\": \"6281281537273\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,34.404,29360128,1,'success','2025-11-13 17:17:11','2025-11-13 17:17:11'),('req_69167507d2729_4975','req_69167507d2729_4975','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'DYzjbkl0CGb39RU05h5TSeNCGDtOoZ3ZPSuzfeUQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_A588B0C5E2BAF838CA079AFEA3BBB62E_6281319393938@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"Tumbler time masuk lagi di tokoibun nih moms...\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763006215, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Intan Puspitasari\", \"sender_phone\": \"6281319393938\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,28.129,29360128,1,'success','2025-11-13 17:17:11','2025-11-13 17:17:11'),('req_69167508278c0_1388','req_69167508278c0_1388','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'RPkCfH1HOrdVIKE0ncMb6uY5JSVogy8WJZEwWuOV','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_A524B78AE1F12176674271454B280F03_6281319393938@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"Kebab wins Ready Stock :\\n\\nKebab mau Mozarella tidak pedas 12box\\nKebab original tidak pedas 4box\\nKebab Original pedes 3box\\nPizza wins 5box\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763009281, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Intan Puspitasari\", \"sender_phone\": \"6281319393938\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,50.454,29360128,1,'success','2025-11-13 17:17:12','2025-11-13 17:17:12'),('req_6916750869ca9_1772','req_6916750869ca9_1772','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'wBtR1lvz998F8ecAUF5twfjkUHIP6GUfXZ4eywDY','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_A5FF3CFEF6011EF0704F4E47CB1146DB_6281319393938@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"Duren Medan Sibolga\\nMasih ada 100box \\nGaaaasss mumpung ada yang Murah dan Manis 👍\\n\\n*Reseller come to me ☺️*\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763009995, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Intan Puspitasari\", \"sender_phone\": \"6281319393938\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,38.444,29360128,1,'success','2025-11-13 17:17:12','2025-11-13 17:17:12'),('req_69167508aa28e_7158','req_69167508aa28e_7158','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'UBaY6qF1x3ikTfK81ThOC6t6Q2Far4FUH7U7IPoa','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC1051243D39068B7A8E1D7626E3F08E_6285693753524@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"ruang.tunggu\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763001317, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Sahrul Fahmihasan\", \"sender_phone\": \"6285693753524\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,34.754,29360128,1,'success','2025-11-13 17:17:12','2025-11-13 17:17:12'),('req_69167508ea37e_7306','req_69167508ea37e_7306','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'rs7rb5meqLLUqTks4rmlEnwaQ1dzpgDAK16TeOfO','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_A5A98D2006DA79088CE0BD493A31212E_6281213092882@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"Tadi diceritain, salah satu customer Latisha Kitchen, yg mana beliau ini punya beberapa resto dan hotel. Beliau bilang, dia tahu nih kalau di Ayam Matah kami pakai minyak kelapanya yang kualitas top.. Maa syaa Allah.. senang deh ada yang notice tentang bahan yang kami pakai..\\nIya bu.. setengah liter minyak kelapa ini harganya jauh lebih mahal dari 1 liter minyak kelapa sawit.. :\\\")\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763001391, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Uchy Sudhanto\", \"sender_phone\": \"6281213092882\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,28.832,29360128,1,'success','2025-11-13 17:17:12','2025-11-13 17:17:12'),('req_69167509445f0_8612','req_69167509445f0_8612','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'JXFCKEVuIDTeJhQeBcHpFIUnRWfnBDqv8Sz0S8lE','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_ACE775B8B56D1079E47E96F3804AEC9F_966568515842@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"اللهم صل وسلم وزد وبارك وأكرم وأنعم على سيدنا ونبينا وحبيبنا وقدوتنا محمد وعلى آله وأصحابه أجمعين \\n\\n🤲🤲🤲\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763014475, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Ahmad Amin S\", \"sender_phone\": \"966568515842\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,29.077,29360128,1,'success','2025-11-13 17:17:13','2025-11-13 17:17:13'),('req_6916750985592_5766','req_6916750985592_5766','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'RAkjS77chSMEEPiajuZN6XnOxxHwOyeE2DeoWKWL','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,106.663,29360128,1,'success','2025-11-13 17:17:13','2025-11-13 17:17:13'),('req_69167509dcb10_5834','req_69167509dcb10_5834','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'IpZIm2x4RRUwOshJg2fHaRLStMjw3biWxYMi9aah','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,36.989,29360128,1,'success','2025-11-13 17:17:13','2025-11-13 17:17:13'),('req_6916750a371e2_1125','req_6916750a371e2_1125','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'j5NkLxr9JOJWquWqnFZPTOiNA2Wx06limqHMJkHg','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,31.709,29360128,1,'success','2025-11-13 17:17:14','2025-11-13 17:17:14'),('req_6916750a894e5_8481','req_6916750a894e5_8481','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'QLAFN9hADUxj5t8Vq3gFEUetnAR5PiX1elPa6Yb5','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,36.851,29360128,1,'success','2025-11-13 17:17:14','2025-11-13 17:17:14'),('req_6916750ad9686_6008','req_6916750ad9686_6008','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'23DoYnvh8LtyW18DuIfjR5PD9E7wtCrJTVeNJcEr','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,71.468,29360128,1,'success','2025-11-13 17:17:14','2025-11-13 17:17:14'),('req_6916750b356ef_6547','req_6916750b356ef_6547','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'gUUtxPz79Qf2CYMtZjgOHoFruR0utdZXB5RSadEo','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,37.045,29360128,1,'success','2025-11-13 17:17:15','2025-11-13 17:17:15'),('req_6916750b7b718_7787','req_6916750b7b718_7787','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'0lpC4NFvUPcM2Ld47dsBuWy7bBhln7IPr02Wk5sj','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC246B94B7F9D6FFF1B90C01269CF5D6_6287824158066@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"Notice the apostrophe: O believers\\n\\nFor indeed, modesty and shyness are atrributes of the believers\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763031131, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Akmal\", \"sender_phone\": \"6287824158066\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,64.067,29360128,1,'success','2025-11-13 17:17:15','2025-11-13 17:17:15'),('req_6916750bdfeaa_7508','req_6916750bdfeaa_7508','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Sw8Bl4FlvUoYNAU9Hh9LxnMedoYQ846PL4awVjRZ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,95.767,29360128,1,'success','2025-11-13 17:17:15','2025-11-13 17:17:16'),('req_6916750c3c7e9_4049','req_6916750c3c7e9_4049','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'UdtSTC9rF78yqS9gQIgkRWYofaAbMel0GFToqJjy','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC1ACBC9D25CA4B1B944A277DBE42AF9_6281380752950@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"https://www.instagram.com/reel/DQ-1ZNbEbwH/?igsh=MW52YjdoN3VscDJhdA==&share_type=SHARE_TO_STATUS\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763033959, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Dessy Rilia\", \"sender_phone\": \"6281380752950\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,24.091,29360128,1,'success','2025-11-13 17:17:16','2025-11-13 17:17:16'),('req_6916750c81f63_1539','req_6916750c81f63_1539','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'UkDlk9f2zb1HpyGDbSoZlOQ6rENjZP85ZLJrOtxp','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,34.965,29360128,1,'success','2025-11-13 17:17:16','2025-11-13 17:17:16'),('req_6916750cf3643_4990','req_6916750cf3643_4990','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'R8YASV5OMtiTCAS0dKhE3hDVXJf1T3tfWiVu2qX1','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC7BEB8A400B537733858283D92E6DA1_6281380752950@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"https://www.instagram.com/reel/DQ-1ZNbEbwH/?igsh=MW52YjdoN3VscDJhdA==&fallback_url=https%3A%2F%2Fwww.instagram.com%2Freel%2FDQ-1ZNbEbwH%2F%3Figsh%3DMW52YjdoN3VscDJhdA%3D%3D&share_type=SHARE_TO_STATUS\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763033959, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Dessy Rilia\", \"sender_phone\": \"6281380752950\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,134.752,29360128,1,'success','2025-11-13 17:17:17','2025-11-13 17:17:17'),('req_6916750d5960f_5361','req_6916750d5960f_5361','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'mLJdeOjwtOtyNS67QagthaAoWG7uLHajdhyX1wnQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,35.865,29360128,1,'success','2025-11-13 17:17:17','2025-11-13 17:17:17'),('req_6916750da2388_7045','req_6916750da2388_7045','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'DdKDeeMAZ7O3mxZv5mRZLJQLc5DtCMTwEf4S9hR6','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,29.267,29360128,1,'success','2025-11-13 17:17:17','2025-11-13 17:17:17'),('req_6916750de6c9a_3478','req_6916750de6c9a_3478','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'1XsVTansyjbPxd3m6RXCoBsOksR8jx67dmktYArM','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_A5395183CC1C7627FF6995AAB1A40270_6281213092882@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"*﷽*\\nٱلسَّلَامُ عَلَيْكُمْ وَرَحْمَةُ ٱللَّٰهِ وَبَرَكَاتُهُ\\n\\n *MENU SARAPAN BESOK:*\\n\\n✨ 🌶️Nasi Bakar Ayam Suwir Bumbu Bali + Sambal Rp. 20.000\\n✨🌶️Nasi Bakar Tongkol Suwir + Sambal Rp. 20.000\\n✨🌶️Nasi Bakar Teri Kemangi + Sambal Rp. 20.000\\n✨ Cheesy Macaroni Schotel Rp. 20.000\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763038313, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Uchy Sudhanto\", \"sender_phone\": \"6281213092882\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,28.640,29360128,1,'success','2025-11-13 17:17:17','2025-11-13 17:17:17'),('req_6916750e693c0_7578','req_6916750e693c0_7578','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'y5p2TYnK9bofkkFoMNBEMFV8chvtPktEYfNQS4gA','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,41.541,29360128,1,'success','2025-11-13 17:17:18','2025-11-13 17:17:18'),('req_6916750eb52dc_9432','req_6916750eb52dc_9432','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'l4Q1PRX1wD0pIV7LXGTvBnFLghecBvBCTA5K8SOU','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,50.159,29360128,1,'success','2025-11-13 17:17:18','2025-11-13 17:17:18'),('req_6916750f1da42_9032','req_6916750f1da42_9032','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'6sroEqNm8tjRrtmDcwK2sFTAMuaY1nmabFWP1C0V','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_3A3908B0FF5006808841_628119868886@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"The less you know, the more confident you are.\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763037261, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Zahra\", \"sender_phone\": \"628119868886\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,34.558,29360128,1,'success','2025-11-13 17:17:19','2025-11-13 17:17:19'),('req_6916750f640f8_6695','req_6916750f640f8_6695','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'tLjquhty067qBC45AQryYoujdkf4zK7foqeFNhqs','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,31.884,29360128,1,'success','2025-11-13 17:17:19','2025-11-13 17:17:19'),('req_6916750fa9cc7_4536','req_6916750fa9cc7_4536','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'56AMjbyYPSS0EnE6ThAL1KzHvia6fetHNJosiGVg','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC5E5CF4CED7A39968A1BBC2517A6ECF_6283879045534@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"Heran,, kenapa BB nambah terus ya..🫣\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763040517, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Susi F12\", \"sender_phone\": \"6283879045534\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,34.777,29360128,1,'success','2025-11-13 17:17:19','2025-11-13 17:17:19'),('req_6916750ff1013_9209','req_6916750ff1013_9209','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'UaxLvCLewSfPp3hzIvJJJlWMWVmQHlGd8HD92sao','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_2A8A6B571DB87AB488C3_6282113856556@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"wkwkwkwk iyaa samaa… vt.tiktok.com/ZSyWobv1R/\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763037337, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Faizah Syihab\", \"sender_phone\": \"6282113856556\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,36.038,29360128,1,'success','2025-11-13 17:17:19','2025-11-13 17:17:20'),('req_6916751049f64_4563','req_6916751049f64_4563','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'PgsDi6e3uI31qS13yJMHTrskE2OtvtDhEPbrCLAV','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,30.123,29360128,1,'success','2025-11-13 17:17:20','2025-11-13 17:17:20'),('req_69167510a58ca_6386','req_69167510a58ca_6386','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'uiXFfN8Vk1S6oTtLR5fFgDSa6T2R0hW4AkohO3mn','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,39.248,29360128,1,'success','2025-11-13 17:17:20','2025-11-13 17:17:20'),('req_69167510f3a00_7490','req_69167510f3a00_7490','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'oic5zzRkyAZBW56AzjXuhOK6T93tC3FPCLHsx1EN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,46.767,29360128,1,'success','2025-11-13 17:17:21','2025-11-13 17:17:21'),('req_691675114cc10_8631','req_691675114cc10_8631','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'eHFtgK8jM7kEHZw8A5TWXzwAVEff1FWFuzZdNmxy','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,40.603,29360128,1,'success','2025-11-13 17:17:21','2025-11-13 17:17:21'),('req_69167511a6c66_5172','req_69167511a6c66_5172','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'YNNgu6NkgIey5lyAxsVdfS4673zURReKJjHHDPMl','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,60.812,29360128,1,'success','2025-11-13 17:17:21','2025-11-13 17:17:21'),('req_691675120ad00_2659','req_691675120ad00_2659','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'OPb4r8ZuFCqVoX0Kwzxu6YBZ8SdO7hGXRjgeUIZO','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_ACA112CDC076327136B7DC75D1AC8EBD_6281281537273@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763041111, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Agung Priyambodo\", \"sender_phone\": \"6281281537273\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,55.502,29360128,1,'success','2025-11-13 17:17:22','2025-11-13 17:17:22'),('req_6916751250058_3413','req_6916751250058_3413','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'vvqJg4e08YaO1fGcdCeJ118rX0Qb46cjicIYTRuq','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_ACAA07A87BF7F2830640405067D62FBE_6281281537273@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763041111, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Agung Priyambodo\", \"sender_phone\": \"6281281537273\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,70.090,29360128,1,'success','2025-11-13 17:17:22','2025-11-13 17:17:22'),('req_69167512c37ff_1351','req_69167512c37ff_1351','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'hv8mJJfcZaozeR0YDorVXeRYLNCt70EWxrCHeP2f','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,40.961,29360128,1,'success','2025-11-13 17:17:22','2025-11-13 17:17:22'),('req_6916751319fb6_2100','req_6916751319fb6_2100','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'DUGw7aJsAKP38zR016DmPjTKZiUb3B7rLbSv8xpm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,30.336,29360128,1,'success','2025-11-13 17:17:23','2025-11-13 17:17:23'),('req_691675135db26_6675','req_691675135db26_6675','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'5SdsvXY7BCJEtLxDUNJw018OWS5v0Sut6KYiRYJF','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,30.551,29360128,1,'success','2025-11-13 17:17:23','2025-11-13 17:17:23'),('req_69167513b9d41_7794','req_69167513b9d41_7794','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'NzQbjULzeNz8bOnVj1bRVZtE0Ju4KtZNtSStVzft','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,71.740,29360128,1,'success','2025-11-13 17:17:23','2025-11-13 17:17:23'),('req_6916751422a92_5058','req_6916751422a92_5058','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'RERP5Jwv1Pksb9SZdK6LtLFgSlAQqnexARm1E6c2','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,48.860,29360128,1,'success','2025-11-13 17:17:24','2025-11-13 17:17:24'),('req_691675147101e_9281','req_691675147101e_9281','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'3MufvJPBeSOBbaBruLffwbNyXQ0Eub1rq7pEBdbm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,63.637,29360128,1,'success','2025-11-13 17:17:24','2025-11-13 17:17:24'),('req_691675153a9b2_4729','req_691675153a9b2_4729','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'a57RpUe5zboh0BUTsEXOxREBS0I9OcP1SWX676zT','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,37.277,29360128,1,'success','2025-11-13 17:17:25','2025-11-13 17:17:25'),('req_69167515860fe_3613','req_69167515860fe_3613','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'mYgG4gm2PUsrF06MMp6lRbuMnfOB1npwtLm4PNzH','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,44.389,29360128,1,'success','2025-11-13 17:17:25','2025-11-13 17:17:25'),('req_69167515d6539_5120','req_69167515d6539_5120','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'sNSsXQA2Z2wCYxNUhhFRzz1Gm1vMi9g34lzHbmzx','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,36.729,29360128,1,'success','2025-11-13 17:17:25','2025-11-13 17:17:25'),('req_6916751630ff6_7243','req_6916751630ff6_7243','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ke3mzs0LLVsj87aO1oDO5qmKO2WUqRqDCz5Pwmfd','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,34.223,29360128,1,'success','2025-11-13 17:17:26','2025-11-13 17:17:26'),('req_691675169998c_2673','req_691675169998c_2673','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'fs3qG8y6rLnDjIHjCxnkf2Ma6N5Nj6SpSsb4cgGZ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,29.682,29360128,1,'success','2025-11-13 17:17:26','2025-11-13 17:17:26'),('req_69167516dc185_3660','req_69167516dc185_3660','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'KwPCPCyS29wLIxlq5qx7pg23F2W2bJur4j9yoS9m','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,30.928,29360128,1,'success','2025-11-13 17:17:26','2025-11-13 17:17:26'),('req_691675173883e_9772','req_691675173883e_9772','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'b8iGx0YIYpi6NP3Y6IB5jQ8dAP6jTWVNkI4l9VUi','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,47.639,29360128,1,'success','2025-11-13 17:17:27','2025-11-13 17:17:27'),('req_691675179b1e3_7343','req_691675179b1e3_7343','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'UVTeZctaTw8xYJhBkEw6bgpIDn1C6GnSG3JJzx7I','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,124.601,29360128,1,'success','2025-11-13 17:17:27','2025-11-13 17:17:27'),('req_69167518182ac_9533','req_69167518182ac_9533','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'zOj8ap4WupRqpterSo2pdjq8mOT1yaROQRPwkNIL','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,32.485,29360128,1,'success','2025-11-13 17:17:28','2025-11-13 17:17:28'),('req_69167518574ef_5507','req_69167518574ef_5507','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'MNDr9DEVcIElhklsFksJQt3rd9Yhk22o6FVxafKG','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC115C7991C16A63501279877EFDA4C4_966568515842@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"اللهم صيبا نافعا \\n\\n🌧🌧🌧\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763051011, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Ahmad Amin S\", \"sender_phone\": \"966568515842\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,30.430,29360128,1,'success','2025-11-13 17:17:28','2025-11-13 17:17:28'),('req_69167518aa79f_6060','req_69167518aa79f_6060','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'8tJkLUHuaZ6SY3cK7kNtuNNsAGyPWlxDWiA2dSIc','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,43.729,29360128,1,'success','2025-11-13 17:17:28','2025-11-13 17:17:28'),('req_691675190148d_8251','req_691675190148d_8251','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'wtP7MT3PLv0KbUSJKhJysg7ClpK9ZyfJY4hAXzoL','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_ACB116ED628741897167BE94035EC4BB_6281213610421@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763064974, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Rachmawati Prihartatiningrum\", \"sender_phone\": \"6281213610421\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,35.392,29360128,1,'success','2025-11-13 17:17:29','2025-11-13 17:17:29'),('req_691675196107a_8395','req_691675196107a_8395','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'iSelUlFgV9XHezM3joCcdE1Z4hMVQY7rV3wIxYr2','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,31.451,29360128,1,'success','2025-11-13 17:17:29','2025-11-13 17:17:29'),('req_69167519a7002_7938','req_69167519a7002_7938','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'khGFh9kDjjneAn4WnZKnQhqUMJHXXV7y7aShdbhw','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,44.279,29360128,1,'success','2025-11-13 17:17:29','2025-11-13 17:17:29'),('req_69167519eae47_5499','req_69167519eae47_5499','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'lPjNybrAXZVgqqiUauNqcV0tzuMbVdX2ywtrLZWT','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC30B07BB48472FCF738389BC54DC2AD_6281213610421@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763064974, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Rachmawati Prihartatiningrum\", \"sender_phone\": \"6281213610421\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,29.397,29360128,1,'success','2025-11-13 17:17:29','2025-11-13 17:17:29'),('req_6916751a37fd3_6645','req_6916751a37fd3_6645','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ztNTJu7aJ3R7d9imL3TF0uB2kG59oC13p5ihiLJ9','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_ACEB873FC394F60DD33BABFF5F082A54_6281213610421@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763065810, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Rachmawati Prihartatiningrum\", \"sender_phone\": \"6281213610421\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,36.153,29360128,1,'success','2025-11-13 17:17:30','2025-11-13 17:17:30'),('req_6916751a80fae_2154','req_6916751a80fae_2154','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'mr20oj0Ll4k6Hczxtp2gvEsjlYQKO9YMdfOP93XP','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC7FA0B3FA7BA7AD7FF81A64D291EC9F_6281213610421@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763065810, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Rachmawati Prihartatiningrum\", \"sender_phone\": \"6281213610421\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,53.077,29360128,1,'success','2025-11-13 17:17:30','2025-11-13 17:17:30'),('req_6916751acff0d_8463','req_6916751acff0d_8463','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'BCN1o6kkFUBNzCkBMfr45AwWE0T6S5WFI1N1Tcjh','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,42.731,29360128,1,'success','2025-11-13 17:17:30','2025-11-13 17:17:30'),('req_6916751b20405_4132','req_6916751b20405_4132','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'90cwIeHxNs5UeLnRTIdjwzJg7jGMJPhIwUDcnLir','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,35.955,29360128,1,'success','2025-11-13 17:17:31','2025-11-13 17:17:31'),('req_6916751b88920_1629','req_6916751b88920_1629','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'gM204YfVXqA9oGm16mbKGi7qXQvDJKBiMphhKNPe','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,33.891,29360128,1,'success','2025-11-13 17:17:31','2025-11-13 17:17:31'),('req_6916751bc791b_3429','req_6916751bc791b_3429','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ROflIaKdCIuQgMhhYwkrdB5sRdYpPbCumE6xiCMv','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC7C711E8BA0BB68FD3A10EB3863F85B_6281213610421@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763069185, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Rachmawati Prihartatiningrum\", \"sender_phone\": \"6281213610421\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,33.158,29360128,1,'success','2025-11-13 17:17:31','2025-11-13 17:17:31'),('req_6916751c1464d_6154','req_6916751c1464d_6154','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'00629vb6q9lBV7xk53sdsPOM1mICtlg3IFtTPP2V','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC243F25BAC7A41559E3939DB4F67428_6281213610421@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763069185, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Rachmawati Prihartatiningrum\", \"sender_phone\": \"6281213610421\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,28.025,29360128,1,'success','2025-11-13 17:17:32','2025-11-13 17:17:32'),('req_6916751c525d9_6935','req_6916751c525d9_6935','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'pFeSsFXQ6099QqsTGonBBqrH1ALQyNn2WllzZ5x2','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC02985EF840187195F3ABC21D5C01F9_6281213610421@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763072361, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Rachmawati Prihartatiningrum\", \"sender_phone\": \"6281213610421\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,60.372,29360128,1,'success','2025-11-13 17:17:32','2025-11-13 17:17:32'),('req_6916751caff77_8024','req_6916751caff77_8024','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'uhGZR0p555KyYdvoBMNAZevOjeMb3HgZEdPxLMHm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC2DE6C20130300E85BC2F7F14A41A80_6281213610421@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763072361, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Rachmawati Prihartatiningrum\", \"sender_phone\": \"6281213610421\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,98.140,29360128,1,'success','2025-11-13 17:17:32','2025-11-13 17:17:32'),('req_6916751d03a52_3495','req_6916751d03a52_3495','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'RumiCoiHj7FqJpR7XM00YoNZdntA2aBx1tUzqFzq','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC98395CF30433F9C0A216808AA7F5FC_6281213610421@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763072650, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Rachmawati Prihartatiningrum\", \"sender_phone\": \"6281213610421\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,36.194,29360128,1,'success','2025-11-13 17:17:33','2025-11-13 17:17:33'),('req_6916751d46449_9009','req_6916751d46449_9009','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'H1DZg3iaKjsLtiyomwkyy9Kth4b73eXAKK6UYNZX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC293570C61B571FA01568C31DE98880_6281213610421@c.us\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"status@broadcast\", \"type\": \"unknown\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763072650, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Rachmawati Prihartatiningrum\", \"sender_phone\": \"6281213610421\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,43.385,29360128,1,'success','2025-11-13 17:17:33','2025-11-13 17:17:33'),('req_6916751d9383d_4590','req_6916751d9383d_4590','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'04mrKjDdtVtv1fI7toblkrg4XSGIFcPul7M9ODrQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,27.289,29360128,1,'success','2025-11-13 17:17:33','2025-11-13 17:17:33'),('req_6916751dd60aa_9240','req_6916751dd60aa_9240','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'j46yrLbLGbKvq4fvzXhNGuYNyS3TmUKSZMbbMQIp','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,61.434,29360128,1,'success','2025-11-13 17:17:33','2025-11-13 17:17:33'),('req_6916751e330ec_9990','req_6916751e330ec_9990','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'xl2r6Cr7KHYQjts8kZKNLqat7nDrqbc6fokTCy9S','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,28.116,29360128,1,'success','2025-11-13 17:17:34','2025-11-13 17:17:34'),('req_6916751e7b8d7_5840','req_6916751e7b8d7_5840','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'aGFFUfl3zJcCx45UKhJYHtx8iUWLsC306UZ83C3l','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,36.517,29360128,1,'success','2025-11-13 17:17:34','2025-11-13 17:17:34'),('req_6916751ec1d8d_5527','req_6916751ec1d8d_5527','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'2naNdJDDXOouLaoqoP8rKCTVKF1MuZOUkNYwHCdH','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,33.782,29360128,1,'success','2025-11-13 17:17:34','2025-11-13 17:17:34'),('req_6916751f1fc2f_2920','req_6916751f1fc2f_2920','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'hHD8MISllgUprjHHMaDVdyPrgjPLYyhxbeILhk9e','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,71.413,29360128,1,'success','2025-11-13 17:17:35','2025-11-13 17:17:35'),('req_6916751f71787_7097','req_6916751f71787_7097','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'0oSEjANj6DvCR4P8GhWSX6QsYP206z23LpjTwvPd','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,32.928,29360128,1,'success','2025-11-13 17:17:35','2025-11-13 17:17:35'),('req_6916751fc2402_6357','req_6916751fc2402_6357','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'iumP6tzMjrcZuJwEze6StTkqWl7njjQactnJ7s93','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,39.936,29360128,1,'success','2025-11-13 17:17:35','2025-11-13 17:17:35'),('req_6916752012e07_1796','req_6916752012e07_1796','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'kx1hcOCzHNEtnvxHyPy988xz8WU7CDEdkJjh8Vif','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,31.684,29360128,1,'success','2025-11-13 17:17:36','2025-11-13 17:17:36'),('req_6916752067e4f_9159','req_6916752067e4f_9159','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'WAaDAqmWhSel32Yfo6Vm15uKyJ4feCNWEZy6t3Iz','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,76.432,29360128,1,'success','2025-11-13 17:17:36','2025-11-13 17:17:36'),('req_69167520b227a_4935','req_69167520b227a_4935','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'bcmJGLUv94PVa4yl3u6HnHm7So6r6Cxh3WNgQVxA','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_AC4FE8792E2E080A2589BF4694FB1852_966568515842@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"اللهم صل وسلم وزد وبارك وأكرم وأنعم على سيدنا ونبينا وحبيبنا وقدوتنا محمد وعلى آله وأصحابه أجمعين \\n\\n🤲🤲🤲\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763075382, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Ahmad Amin S\", \"sender_phone\": \"966568515842\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,28.182,29360128,1,'success','2025-11-13 17:17:36','2025-11-13 17:17:36'),('req_691675210b711_4721','req_691675210b711_4721','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'rDFUuqpGSINT8xCtR2zxebmzS5XYRX6HwyVwix5q','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,29.537,29360128,1,'success','2025-11-13 17:17:37','2025-11-13 17:17:37'),('req_691675215390e_4391','req_691675215390e_4391','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'vnaTvqclGdn0XkMGk6j9pfF7tk2rCnEpXS2kMkh4','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,46.381,29360128,1,'success','2025-11-13 17:17:37','2025-11-13 17:17:37'),('req_69167521a7bc0_1362','req_69167521a7bc0_1362','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'cqWxtC9fUoCWUBL6WiynkL4u0j3AJmYVDHe8oDwp','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,35.064,29360128,1,'success','2025-11-13 17:17:37','2025-11-13 17:17:37'),('req_69167521e8e2e_7943','req_69167521e8e2e_7943','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'QhkizEQ7XGhAPF3HIzS0IVdKv6kPMY2VybjRNIaA','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,32.082,29360128,1,'success','2025-11-13 17:17:37','2025-11-13 17:17:37'),('req_691675223e0d1_3829','req_691675223e0d1_3829','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'TWIBLlyEICYVpOS6nwopks8ORpNiRyFIiHffzlMr','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,36.304,29360128,1,'success','2025-11-13 17:17:38','2025-11-13 17:17:38'),('req_69167522b34a0_9162','req_69167522b34a0_9162','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'zFAHHdLhmA5HT4NRoa7AXVRlsJVZvZMAECpsJC2b','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,33.107,29360128,1,'success','2025-11-13 17:17:38','2025-11-13 17:17:38'),('req_691675230829b_2220','req_691675230829b_2220','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'nCH4MnRgzEL4eR2q7jMyd1ChLfG2Eogt5NPHCW9L','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,53.751,29360128,1,'success','2025-11-13 17:17:39','2025-11-13 17:17:39'),('req_691675235400c_6357','req_691675235400c_6357','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'6K25W6h3O5gpGuYzgkdVN6wooS7ZTXf9kpYomNAm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,36.176,29360128,1,'success','2025-11-13 17:17:39','2025-11-13 17:17:39'),('req_69167523983d8_4222','req_69167523983d8_4222','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'g8V9mBvaJ62FYFjDGRNe6rexcfO1xeKO4qKJFZgX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,32.604,29360128,1,'success','2025-11-13 17:17:39','2025-11-13 17:17:39'),('req_69167523ddfae_2367','req_69167523ddfae_2367','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ObYzwzEtM04dLMSvThJiuE4jf6clRUgkZzL7SAXi','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,44.900,29360128,1,'success','2025-11-13 17:17:39','2025-11-13 17:17:39'),('req_691675242c925_1895','req_691675242c925_1895','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'bZz0yfYeRR5pKy04qNmCqMxYYifNay8EP2NDcp5W','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,28.861,29360128,1,'success','2025-11-13 17:17:40','2025-11-13 17:17:40'),('req_6916752471162_5789','req_6916752471162_5789','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'cEWAOpk8C8ina3YAQUfhFNUby0RY9AhPKKbN5WNj','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,28.597,29360128,1,'success','2025-11-13 17:17:40','2025-11-13 17:17:40'),('req_69167524b7ec3_1059','req_69167524b7ec3_1059','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'aewgux73jYtmo2jc7uBPM11XjTUXOU2rKgbtoqMs','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,37.928,29360128,1,'success','2025-11-13 17:17:40','2025-11-13 17:17:40'),('req_691675250aaea_5195','req_691675250aaea_5195','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'uuH6WFnz4eE0hBVWs9jF5mnb8k91aufHvlPS58PQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,31.672,29360128,1,'success','2025-11-13 17:17:41','2025-11-13 17:17:41'),('req_691675254931a_9216','req_691675254931a_9216','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'iLKafgTjVRnTn3IfpFt4RS0VndW1NSr8eeuiu3iN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,41.762,29360128,1,'success','2025-11-13 17:17:41','2025-11-13 17:17:41'),('req_6916752592d86_6304','req_6916752592d86_6304','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'2eFerfVSPFUHaG7CrwYhfycGeydxw9FDKTYX4SjE','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,33.492,29360128,1,'success','2025-11-13 17:17:41','2025-11-13 17:17:41'),('req_69167525d1a0f_9272','req_69167525d1a0f_9272','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'6fQg3AGTf2MaBfjPKiD9ga0G6dIUW42lO2jwtQvg','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,30.521,29360128,1,'success','2025-11-13 17:17:41','2025-11-13 17:17:41'),('req_691675261eb30_7113','req_691675261eb30_7113','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'tz5kBHdU0xeztcD6YXzssu6Gv2TcoS5SmLSlzThc','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,29.187,29360128,1,'success','2025-11-13 17:17:42','2025-11-13 17:17:42'),('req_69167526634b1_8512','req_69167526634b1_8512','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'9ltZfGar0hYBMxBSRTybgmmLfArsFWtEf3brfbap','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,28.012,29360128,1,'success','2025-11-13 17:17:42','2025-11-13 17:17:42'),('req_69167526bb17a_4800','req_69167526bb17a_4800','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'3f3a74OkXowbNreO3KsENrTB0p3IW0p6vJiKD5kN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,28.623,29360128,1,'success','2025-11-13 17:17:42','2025-11-13 17:17:42'),('req_6916752712123_8236','req_6916752712123_8236','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'gCE1N2eDLvgnMR4m4wsDwVPn2gyVXWa3DdIbiVvj','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,39.136,29360128,0,'client_error','2025-11-13 17:17:43','2025-11-13 17:17:43'),('req_691675275fd05_3070','req_691675275fd05_3070','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'CIWBqRu294eznIpkW3pcpsqQBqpX4bZ6mmpkqZMW','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,27.178,29360128,0,'client_error','2025-11-13 17:17:43','2025-11-13 17:17:43'),('req_69167527af678_4037','req_69167527af678_4037','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'kyS3KOCu0lLYbj6cTilllulcs9ilpIb8Anz1YFhU','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,29.267,29360128,0,'client_error','2025-11-13 17:17:43','2025-11-13 17:17:43'),('req_69167527ed1cc_5914','req_69167527ed1cc_5914','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'90cuZ8Ba2uOdOppAPusLjl2OAMOrktp7XZAYBgwA','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,28.612,29360128,0,'client_error','2025-11-13 17:17:43','2025-11-13 17:17:43'),('req_6916752846ae6_3017','req_6916752846ae6_3017','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'4rDkoJPXa3qRvsXoJiXf2tbZGPG1yAxi6GGq9y6r','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.576,29360128,0,'client_error','2025-11-13 17:17:44','2025-11-13 17:17:44'),('req_6916752885e12_2989','req_6916752885e12_2989','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'d4TrsDEfbIaDyTrdrWtrxSChGtnVXFo8I8eDvxk6','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,39.256,29360128,0,'client_error','2025-11-13 17:17:44','2025-11-13 17:17:44'),('req_69167528dcf31_4124','req_69167528dcf31_4124','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'r4zIcfPoFRjmobGfKPB8XPxKA4mpAR8lybaL4NTY','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,25.947,29360128,0,'client_error','2025-11-13 17:17:44','2025-11-13 17:17:44'),('req_691675292d8c3_2301','req_691675292d8c3_2301','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'5lmTOHwdZ6NFku6G7z4jqS0xiljVuI3lWoky3eq8','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,23.184,29360128,0,'client_error','2025-11-13 17:17:45','2025-11-13 17:17:45'),('req_691675296c6f8_5884','req_691675296c6f8_5884','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'L803SNTKqQRNQkSPnK49ZkdOEXtW6wlDnNbZPGLA','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,31.326,29360128,0,'client_error','2025-11-13 17:17:45','2025-11-13 17:17:45'),('req_69167529c3a6f_9052','req_69167529c3a6f_9052','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Loe0tSKfPBWnXAcJ3F50BX78uwksixJQEL5iZxLq','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,36.602,29360128,0,'client_error','2025-11-13 17:17:45','2025-11-13 17:17:45'),('req_6916752a2f6c1_9656','req_6916752a2f6c1_9656','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'3UGzc3ncoGvTvcjJU9Z39salrYNDbB5Zz50gCtxN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,28.986,29360128,0,'client_error','2025-11-13 17:17:46','2025-11-13 17:17:46'),('req_6916752a7e4fb_7857','req_6916752a7e4fb_7857','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'6ginT1z25L32R5J8vVm4VH7Cv0RWP0QJ4TJuhJ4R','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_3A536EBC5ACD13FF2840_6285253761216@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"Badan su menjerit kaka😑😑😑\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1762995166, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Desy Dwijayanti\", \"sender_phone\": \"6285253761216\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',429,12076,28.372,29360128,0,'client_error','2025-11-13 17:17:46','2025-11-13 17:17:46'),('req_6916752ad0fbc_7043','req_6916752ad0fbc_7043','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'J93YYSQ9tfJ7894CpVvMLYoTBcPMeciCvNNBh2xb','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,39.331,29360128,0,'client_error','2025-11-13 17:17:46','2025-11-13 17:17:46'),('req_6916752b37db7_2594','req_6916752b37db7_2594','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'fXg3gV2guF0aMt035bHQYKLZRK6lK14QYUf9358t','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,31.769,29360128,0,'client_error','2025-11-13 17:17:47','2025-11-13 17:17:47'),('req_6916752b9356f_7204','req_6916752b9356f_7204','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'sQdkF3PCIlGpbRyRRrjqQMYnWDPQFucUaOurOsRf','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,67.421,29360128,0,'client_error','2025-11-13 17:17:47','2025-11-13 17:17:47'),('req_6916752c194ef_4863','req_6916752c194ef_4863','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ACLTW2p02C2srTjisgKKfJknVc8wOY3qNQTjjWCm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,60.023,29360128,0,'client_error','2025-11-13 17:17:48','2025-11-13 17:17:48'),('req_6916752c67b2f_1690','req_6916752c67b2f_1690','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'aQY2TL7eA8mBS7t4C7QB8ETwTcltL7eE6AKUB3qc','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,43.877,29360128,0,'client_error','2025-11-13 17:17:48','2025-11-13 17:17:48'),('req_6916752cd3672_4113','req_6916752cd3672_4113','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ubaUvCUTgfTcbyliNhw42jngkDF2U7HYAGQYEF18','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,27.995,29360128,0,'client_error','2025-11-13 17:17:48','2025-11-13 17:17:48'),('req_6916752d26b3d_5964','req_6916752d26b3d_5964','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'DlUH7nJWVBuryhts1zfi7Poup707w8dwE8Do7QyJ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,33.099,29360128,0,'client_error','2025-11-13 17:17:49','2025-11-13 17:17:49'),('req_6916752d747b0_5533','req_6916752d747b0_5533','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'lqVEt1Jm1QIcrOBnrYHZSwYVgNXBm6sgOzyevsXm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,65.674,29360128,0,'client_error','2025-11-13 17:17:49','2025-11-13 17:17:49'),('req_6916752dcef0b_7223','req_6916752dcef0b_7223','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Y0CyqaHnlFuBbjIHQArmV8nvs7HWX0QwImaeXCxx','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,29.226,29360128,0,'client_error','2025-11-13 17:17:49','2025-11-13 17:17:49'),('req_6916752e24bf8_9723','req_6916752e24bf8_9723','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'GqpFLHarxIWuADSBCVISsblPCSXmql7gQU2mYwxN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,28.314,29360128,0,'client_error','2025-11-13 17:17:50','2025-11-13 17:17:50'),('req_6916752e77436_1606','req_6916752e77436_1606','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'uQhYQswzP1gxygPpHZlqVAAAZEjUJfCNq58ubTYD','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,24.323,29360128,0,'client_error','2025-11-13 17:17:50','2025-11-13 17:17:50'),('req_6916752ece912_5688','req_6916752ece912_5688','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'QlEoscFmeL4bN3ueY5QvZCwUVUiR5oM65mT1O6h4','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,30.880,29360128,0,'client_error','2025-11-13 17:17:50','2025-11-13 17:17:50'),('req_6916752f1fe53_8994','req_6916752f1fe53_8994','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'7RLFs6MI4OsfI6NWZ5AXDsCrrwoiNyCCVOmaA7DJ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.943,29360128,0,'client_error','2025-11-13 17:17:51','2025-11-13 17:17:51'),('req_6916752f6a4b5_1275','req_6916752f6a4b5_1275','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ADvkLpUbhCjgin1jytJv2Ddi3j1kwNBn6Ks1IFcl','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,27.527,29360128,0,'client_error','2025-11-13 17:17:51','2025-11-13 17:17:51'),('req_6916752fc9b90_5181','req_6916752fc9b90_5181','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'HQRlbvCNF4592JIlzTOIe4isaRPmOzqMvGcjAVCx','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,37.384,29360128,0,'client_error','2025-11-13 17:17:51','2025-11-13 17:17:51'),('req_69167530254a4_6011','req_69167530254a4_6011','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ZQeuylHtGIdxPkKdyt5a5doptRX7nhz7IEMMPPJx','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,34.303,29360128,0,'client_error','2025-11-13 17:17:52','2025-11-13 17:17:52'),('req_691675306bf89_9102','req_691675306bf89_9102','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'9DmbFjWlzf52N7XvVKnPWXFOGpCUuLinKTGjNPa6','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,27.809,29360128,0,'client_error','2025-11-13 17:17:52','2025-11-13 17:17:52'),('req_69167530af0eb_6416','req_69167530af0eb_6416','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'N8ldbiQbVOA4hmLVFmz4MVcgYx5REcsEHvEgaR7z','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,28.530,29360128,0,'client_error','2025-11-13 17:17:52','2025-11-13 17:17:52'),('req_69167530f3c65_2117','req_69167530f3c65_2117','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'hD4LYoCHlfaF36dIhW31DukTksKHykg3qX6wowkE','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,23.895,29360128,0,'client_error','2025-11-13 17:17:53','2025-11-13 17:17:53'),('req_6916753151b35_1881','req_6916753151b35_1881','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'M9i4oJpodUI6OdU02jXQN4e01fR8EVCwI0rjV8U1','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,30.651,29360128,0,'client_error','2025-11-13 17:17:53','2025-11-13 17:17:53'),('req_691675319c9fa_4739','req_691675319c9fa_4739','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'5qs953iZkKqC6zBQxfz5T7dr42fsLGvBGpjYjW3P','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,31.593,29360128,0,'client_error','2025-11-13 17:17:53','2025-11-13 17:17:53'),('req_6916753205c49_3875','req_6916753205c49_3875','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'3oCteqHrRAu0OIfbOatrw2HoeERs3rgxblP1sHKb','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,38.119,29360128,0,'client_error','2025-11-13 17:17:54','2025-11-13 17:17:54'),('req_6916753268a66_1323','req_6916753268a66_1323','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'XRrp5bMSa6YxQtUO2TUrYp8BpjxJg1D4gnUIiJnm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,27.893,29360128,0,'client_error','2025-11-13 17:17:54','2025-11-13 17:17:54'),('req_69167532c77fd_3177','req_69167532c77fd_3177','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'mHYMnA1tGoFvzESys85qYwMEavSb8inMlPvxlbH7','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,29.887,29360128,0,'client_error','2025-11-13 17:17:54','2025-11-13 17:17:54'),('req_6916753316c1e_7042','req_6916753316c1e_7042','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'8UYZfrxcHyTqvdiaflIYfA74WPU3SMdTIaBqZmqR','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,21.760,29360128,0,'client_error','2025-11-13 17:17:55','2025-11-13 17:17:55'),('req_6916753352314_1215','req_6916753352314_1215','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'d1Y2jKFGsKUvex4B1kX4oomniV4s0aXR4iEx5ono','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,22.758,29360128,0,'client_error','2025-11-13 17:17:55','2025-11-13 17:17:55'),('req_69167533a5327_5631','req_69167533a5327_5631','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'BehODIrudgvPNeRkc1EYdlBFE6BVugBR0laJ5jtq','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,29.075,29360128,0,'client_error','2025-11-13 17:17:55','2025-11-13 17:17:55'),('req_69167533f0ea6_6798','req_69167533f0ea6_6798','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'lNLrRc8RtCF2mVznNWr2qoK6rPxtfdydDTDTlxrV','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,23.852,29360128,0,'client_error','2025-11-13 17:17:55','2025-11-13 17:17:56'),('req_6916753446e47_5008','req_6916753446e47_5008','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'mc1HpU2CPu1uKr7WPqpmV2e2HiAtJ8gOuG9poMVo','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,33.011,29360128,0,'client_error','2025-11-13 17:17:56','2025-11-13 17:17:56'),('req_6916753495dca_3972','req_6916753495dca_3972','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'9GfESkskAEewZzK2slezHvJI7mfXJ4BOUuSY84SF','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,27.915,29360128,0,'client_error','2025-11-13 17:17:56','2025-11-13 17:17:56'),('req_69167534db182_2469','req_69167534db182_2469','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'omyIo3uLSvV434cH8tlZkQ8KarfFv5vFo9l3tZTJ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,31.375,29360128,0,'client_error','2025-11-13 17:17:56','2025-11-13 17:17:56'),('req_69167535354b2_7313','req_69167535354b2_7313','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'72yDxENtw623cGu6wGsV6yGZ6W8bgJ4wmTQjKIWP','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,42.350,29360128,0,'client_error','2025-11-13 17:17:57','2025-11-13 17:17:57'),('req_69167535a1533_8302','req_69167535a1533_8302','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'18UwsndwidZAdkgku2e06ncrf5QmAJZ7aoEpTGzw','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,54.819,29360128,0,'client_error','2025-11-13 17:17:57','2025-11-13 17:17:57'),('req_691675360df40_5214','req_691675360df40_5214','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'USn0lrH9hW2g5VPnO7XUEZQoXzl90TU1r1BQPumv','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.136,29360128,0,'client_error','2025-11-13 17:17:58','2025-11-13 17:17:58'),('req_6916753659b73_6587','req_6916753659b73_6587','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'YwNaRb4ZVLVosYXbZxfbQKNZm8E9EsJDarVrdUlj','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.609,29360128,0,'client_error','2025-11-13 17:17:58','2025-11-13 17:17:58'),('req_69167536afee7_4368','req_69167536afee7_4368','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'VR3djIeO5DxtHUCGTHrKVsXfRTlzGBLCmpETxnGs','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.590,29360128,0,'client_error','2025-11-13 17:17:58','2025-11-13 17:17:58'),('req_691675370dfbf_9387','req_691675370dfbf_9387','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'AAGUifah4KwE3cfHQJTAkwTlm3blAGZUMkw4lSI0','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,27.544,29360128,0,'client_error','2025-11-13 17:17:59','2025-11-13 17:17:59'),('req_691675374f20a_4713','req_691675374f20a_4713','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'4WbIgHLwOBySdzPloHRLKBwxaZ41cYH5xrHichvI','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,23.539,29360128,0,'client_error','2025-11-13 17:17:59','2025-11-13 17:17:59'),('req_6916753793529_1455','req_6916753793529_1455','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'HMweeSpZYELxktasRceH83tmc17SR46URP2CtBgq','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.190,29360128,0,'client_error','2025-11-13 17:17:59','2025-11-13 17:17:59'),('req_69167537dc171_9652','req_69167537dc171_9652','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'E0hujnOWoaW59wBh211UJTFn709Sd4poo1tbSb3K','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,25.541,29360128,0,'client_error','2025-11-13 17:17:59','2025-11-13 17:17:59'),('req_69167538284c0_8977','req_69167538284c0_8977','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'v7NjGi9tHzIhOYeQwNUvXJkarmlcod5w32kE60dx','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,25.114,29360128,0,'client_error','2025-11-13 17:18:00','2025-11-13 17:18:00'),('req_691675387003a_6017','req_691675387003a_6017','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'cK2p5bkZGnWz4FbrZnuKgq1MRWxPBrAoHeq3IEjm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,24.111,29360128,0,'client_error','2025-11-13 17:18:00','2025-11-13 17:18:00'),('req_69167538b99eb_6702','req_69167538b99eb_6702','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'yvsn88c4bfxSsHi1qZpiaYkovLlbMFJ5hCAuePQx','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,24.947,29360128,0,'client_error','2025-11-13 17:18:00','2025-11-13 17:18:00'),('req_6916753909159_6691','req_6916753909159_6691','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'067XUd61creWjNYvwsx8g4bYLGvvpiQVy7kL3m6j','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,24.593,29360128,0,'client_error','2025-11-13 17:18:01','2025-11-13 17:18:01'),('req_6916753949896_6789','req_6916753949896_6789','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'4qmvl5rKHVXb4DlD8IvnC8OC4K2aBEHCIvTkD9nv','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,39.327,29360128,0,'client_error','2025-11-13 17:18:01','2025-11-13 17:18:01'),('req_691675398ffbe_9251','req_691675398ffbe_9251','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'dC9QY75HUkZUoAFaTLapozsfxzxpHnoUGHehnG7z','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,25.204,29360128,0,'client_error','2025-11-13 17:18:01','2025-11-13 17:18:01'),('req_69167539d11e5_5272','req_69167539d11e5_5272','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'igRVSzEHpcGgj9wREs14En2wIHo0YCi8mIjKoCh5','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,24.909,29360128,0,'client_error','2025-11-13 17:18:01','2025-11-13 17:18:01'),('req_6916753a27d36_6598','req_6916753a27d36_6598','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'4mY9M4I4F2WLdplohQEmoRhNyhGOAcWIi8yT72Ea','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,27.254,29360128,0,'client_error','2025-11-13 17:18:02','2025-11-13 17:18:02'),('req_6916753a6957d_4718','req_6916753a6957d_4718','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'DKga2QNrwpOSPHrUp4qj5gFVmwOQlGXCGodwKUm5','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,23.937,29360128,0,'client_error','2025-11-13 17:18:02','2025-11-13 17:18:02'),('req_6916753aa93ba_9368','req_6916753aa93ba_9368','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'eBUHl0y1Zvtn7bgqSGKuaw28LmNtjf5Jg2d16Xtu','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.158,29360128,0,'client_error','2025-11-13 17:18:02','2025-11-13 17:18:02'),('req_6916753aef14c_2891','req_6916753aef14c_2891','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'v3WmD9weVwwwb0wuWawIxfyjWJIsAim3ojK45dRr','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,56.062,29360128,0,'client_error','2025-11-13 17:18:02','2025-11-13 17:18:03'),('req_6916753b478ec_8000','req_6916753b478ec_8000','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'iqVK1ZF68C1Dk21SRfPu1X2c81S4Z7n8uvRwTYXt','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,43.291,29360128,0,'client_error','2025-11-13 17:18:03','2025-11-13 17:18:03'),('req_6916753ba7443_3427','req_6916753ba7443_3427','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'OzvaLa43KvqfcDI25gBAa9HYjAPP4HIGi9QwTxgE','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,24.798,29360128,0,'client_error','2025-11-13 17:18:03','2025-11-13 17:18:03'),('req_6916753c05dfe_9761','req_6916753c05dfe_9761','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'TX0Ra98bvx9cktewca17JMftYRFSN13TOFqkLzJu','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.237,29360128,0,'client_error','2025-11-13 17:18:04','2025-11-13 17:18:04'),('req_6916753c59165_5230','req_6916753c59165_5230','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'xJUqfYJdwu1AXMKj2AG9n4Drbpdp4YAYHWECwkhB','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,29.881,29360128,0,'client_error','2025-11-13 17:18:04','2025-11-13 17:18:04'),('req_6916753cb3e84_9116','req_6916753cb3e84_9116','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'AtVxFBv1HXon4DLat6dPmoiwedx1JJlZ5TOJ5iH0','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,32.010,29360128,0,'client_error','2025-11-13 17:18:04','2025-11-13 17:18:04'),('req_6916753d25d3f_4299','req_6916753d25d3f_4299','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'L0US3QrD8yNadVzhMyRdTqsDRLof4pTFSWtlidny','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,31.032,29360128,0,'client_error','2025-11-13 17:18:05','2025-11-13 17:18:05'),('req_6916753d82d86_7012','req_6916753d82d86_7012','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'1ig9uxTcgwtcCdu7eAfbNwRGzT2Stplv8ydREUMj','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,33.093,29360128,0,'client_error','2025-11-13 17:18:05','2025-11-13 17:18:05'),('req_6916753de0c9a_8761','req_6916753de0c9a_8761','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'jB4MnLbWjB7Mpq3xPdtbJthIXOm7i3RwWF7z0jln','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,43.386,29360128,0,'client_error','2025-11-13 17:18:05','2025-11-13 17:18:05'),('req_6916753e4b1b0_5660','req_6916753e4b1b0_5660','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'kj0IALXkCtVCwK0YbycxpwtjFJui4KLnvt8PisyN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,50.609,29360128,0,'client_error','2025-11-13 17:18:06','2025-11-13 17:18:06'),('req_6916753ea6f14_8309','req_6916753ea6f14_8309','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Ezhj2WdpOChmLnVvgFaM6A2OXXSga8eOHihN2nfH','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,25.731,29360128,0,'client_error','2025-11-13 17:18:06','2025-11-13 17:18:06'),('req_6916753f08503_6307','req_6916753f08503_6307','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'vwRHs3DemezrgZGfnp8XA4R6Bf9QR9m7fKhRi0Ld','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,25.207,29360128,0,'client_error','2025-11-13 17:18:07','2025-11-13 17:18:07'),('req_6916753f68fb2_7038','req_6916753f68fb2_7038','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'A1QUhpZK620zZVxGCrN4TAiSs7StdfI8k9XCSUwt','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,43.336,29360128,0,'client_error','2025-11-13 17:18:07','2025-11-13 17:18:07'),('req_6916753fba58c_7757','req_6916753fba58c_7757','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'3PBoGKHtKWJudpXjlzTkWY6MOw6jlvss0rm7xwFY','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,41.856,29360128,0,'client_error','2025-11-13 17:18:07','2025-11-13 17:18:07'),('req_6916754014799_7432','req_6916754014799_7432','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'DM4Pjysb5yS78CDoCygSgpuogeyhlaaxCpVX5sG9','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.996,29360128,0,'client_error','2025-11-13 17:18:08','2025-11-13 17:18:08'),('req_69167540617d6_6891','req_69167540617d6_6891','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'T5VXvwkD8nY9JCk56vK6vjqpnCVZLHFLOl0KOIbO','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,36.212,29360128,0,'client_error','2025-11-13 17:18:08','2025-11-13 17:18:08'),('req_69167540a909e_1768','req_69167540a909e_1768','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'jqwKpbmGH8azPFKrIhx8vlPwjvt9XWQgLrnZ2xud','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,25.773,29360128,0,'client_error','2025-11-13 17:18:08','2025-11-13 17:18:08'),('req_69167541101c8_5343','req_69167541101c8_5343','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'lZmlX51uw8RdlzNh4psXce9kmCpF3TbS2QG2okL5','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,41.120,31457280,0,'client_error','2025-11-13 17:18:09','2025-11-13 17:18:09'),('req_691675415f21e_2530','req_691675415f21e_2530','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'UyuwEIfIkuaoVlXF9np0Z2aWEfaohnZScOD2s5uH','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.650,29360128,0,'client_error','2025-11-13 17:18:09','2025-11-13 17:18:09'),('req_69167541adada_6294','req_69167541adada_6294','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'RlKLQgOhvOSwycwnDVpo2DmEeuGZerExxexmydVC','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,30.294,29360128,0,'client_error','2025-11-13 17:18:09','2025-11-13 17:18:09'),('req_69167541f3388_3587','req_69167541f3388_3587','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'T6LZ7JB6fGPRO0nE66SYUSG8GwEaMnngPb3LM3AG','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,30.117,29360128,0,'client_error','2025-11-13 17:18:10','2025-11-13 17:18:10'),('req_6916754268664_5035','req_6916754268664_5035','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'tRenkq5WjbFcHyXVCxH0wcdRPjbbu7jLMZBATFiN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,51.361,29360128,0,'client_error','2025-11-13 17:18:10','2025-11-13 17:18:10'),('req_69167542bddd2_2898','req_69167542bddd2_2898','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'2TX5iqMPBs42dGr02gztBSRDrLkT7biN3oedGctW','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,39.430,29360128,0,'client_error','2025-11-13 17:18:10','2025-11-13 17:18:10'),('req_691675431d0a1_6551','req_691675431d0a1_6551','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'PueniQii4D4RtbUsNgvlFnZAvootlU6mrJhEtAZK','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,39.488,29360128,0,'client_error','2025-11-13 17:18:11','2025-11-13 17:18:11'),('req_691675435feef_2847','req_691675435feef_2847','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'YOQoCJizCyKf5nxQ95KkvqAiDPsIo4QyExXGZStD','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,21.810,29360128,0,'client_error','2025-11-13 17:18:11','2025-11-13 17:18:11'),('req_6916754397855_6023','req_6916754397855_6023','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'QAJ2slzdbIfvG6EXRzjFSP1TZAe2VDfKO3w2riOS','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,23.255,29360128,0,'client_error','2025-11-13 17:18:11','2025-11-13 17:18:11'),('req_6916754408714_3795','req_6916754408714_3795','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'telPabsnb7HkMElvksAYtymFHU5gnaEGXlrwN8HP','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,57.510,29360128,0,'client_error','2025-11-13 17:18:12','2025-11-13 17:18:12'),('req_691675446cd6d_2783','req_691675446cd6d_2783','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'mS7gTn39LaoJO514GhCWlRsXsh5hccAjQMre2Ihy','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,30.259,29360128,0,'client_error','2025-11-13 17:18:12','2025-11-13 17:18:12'),('req_69167544b5ed6_6811','req_69167544b5ed6_6811','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'sVGBjJVFNtkoEIvpN72DzekqpWTjKqcKx7QBMObX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,22.490,29360128,0,'client_error','2025-11-13 17:18:12','2025-11-13 17:18:12'),('req_69167545141c5_8432','req_69167545141c5_8432','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'iyfsbokuD2UZuekleE1DjDlrlNjXp97hlCti2Mik','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,33.979,29360128,0,'client_error','2025-11-13 17:18:13','2025-11-13 17:18:13'),('req_691675459256e_9437','req_691675459256e_9437','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'fizAPCpsFIQOf8RStQeE89CRD7RLTubveeWbbJEs','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,32.324,29360128,0,'client_error','2025-11-13 17:18:13','2025-11-13 17:18:13'),('req_6916754602096_6779','req_6916754602096_6779','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'WXCUs96p4hAesPc6pBvAIHwzm8fMhteTqMyBILz9','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,44.936,29360128,0,'client_error','2025-11-13 17:18:14','2025-11-13 17:18:14'),('req_691675465f8b5_1431','req_691675465f8b5_1431','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'qozp6B1AePatet6YOxtVjxPXjKvYvfqSLzCaJb4q','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,27.689,29360128,0,'client_error','2025-11-13 17:18:14','2025-11-13 17:18:14'),('req_69167546bd635_6057','req_69167546bd635_6057','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'G0HFHlahnBL70c8cLOJckcXmUUNOABZ0fFcjJVth','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,45.128,29360128,0,'client_error','2025-11-13 17:18:14','2025-11-13 17:18:14'),('req_6916754734c4d_8094','req_6916754734c4d_8094','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'dCpjy11fQJGJ78iOrMvNsT5z47NI7lBHOTpoIt3X','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,40.064,29360128,0,'client_error','2025-11-13 17:18:15','2025-11-13 17:18:15'),('req_691675478bc1f_9457','req_691675478bc1f_9457','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'nVLGmFkO7DCppRfGVRadM2T9zQJno2iIksiHxros','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,32.152,29360128,0,'client_error','2025-11-13 17:18:15','2025-11-13 17:18:15'),('req_691675480807a_4676','req_691675480807a_4676','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'eE6P6faddlak07gExzjggpahhiDHFBshh7lMcoXO','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,46.196,29360128,0,'client_error','2025-11-13 17:18:16','2025-11-13 17:18:16'),('req_69167548665f9_5001','req_69167548665f9_5001','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'QQ2eJJF4QOcwW4Dmyo59tUt19cUZw6dgsEwbazzz','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,35.743,29360128,0,'client_error','2025-11-13 17:18:16','2025-11-13 17:18:16'),('req_69167548ba5cb_5816','req_69167548ba5cb_5816','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'H3HdXKEiQT3Gu5lCsWgEzkI9V1mSqjGdqEjGvcc4','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,33.817,29360128,0,'client_error','2025-11-13 17:18:16','2025-11-13 17:18:16'),('req_691675493b52b_8013','req_691675493b52b_8013','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'jY1SIKMotNxW0L9h2TB21QJIYAUVAUvLY5j8J6u7','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,65.070,29360128,0,'client_error','2025-11-13 17:18:17','2025-11-13 17:18:17'),('req_691675498fddb_1724','req_691675498fddb_1724','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'xywFXtXpNA0YEm1ZCHTwKRQqFMtBsogroYD85MKe','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,34.851,29360128,0,'client_error','2025-11-13 17:18:17','2025-11-13 17:18:17'),('req_6916754a15cdd_1252','req_6916754a15cdd_1252','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'dIp5PrmYwrkE9SpmDiNhm6EGpnVidfnkxnSXQemm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,49.916,29360128,0,'client_error','2025-11-13 17:18:18','2025-11-13 17:18:18'),('req_6916754a8203e_3021','req_6916754a8203e_3021','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ciRqKksx6uvpaSy3qg1Cl5OhtfUdgaDnodSg5Zuq','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.331,29360128,0,'client_error','2025-11-13 17:18:18','2025-11-13 17:18:18'),('req_6916754af2030_5507','req_6916754af2030_5507','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'sv4SxB5bXID3bovbSkjUO8CSLM57eBJIG0TYsO6h','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,37.459,29360128,0,'client_error','2025-11-13 17:18:18','2025-11-13 17:18:19'),('req_6916754b620f0_2513','req_6916754b620f0_2513','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'HAJ2TmBD2AU6by8gR1hBviO9WjH8xDDck15mZ2EX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,112.665,29360128,0,'client_error','2025-11-13 17:18:19','2025-11-13 17:18:19'),('req_6916754bd4cc2_4747','req_6916754bd4cc2_4747','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Q8LZilZzavUm5k7PRzq03euoZD2IsqURldHf2wpS','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,37.375,29360128,0,'client_error','2025-11-13 17:18:19','2025-11-13 17:18:19'),('req_6916754c44ac5_9686','req_6916754c44ac5_9686','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'2ECsUCD5VfHaan13Wx4IfgnG11cjjrEZaYwkB6td','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,29.336,29360128,0,'client_error','2025-11-13 17:18:20','2025-11-13 17:18:20'),('req_6916754ca489b_3001','req_6916754ca489b_3001','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'SR3YW165OiZA4LU1SMXF8fbjcQHxkIaorP4L18Wh','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,41.855,29360128,0,'client_error','2025-11-13 17:18:20','2025-11-13 17:18:20'),('req_6916754d482da_2017','req_6916754d482da_2017','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'03y7Mx4TGIQ5DWPRVIfREK8PeAvUIPOTV0cNvSJf','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,56.470,29360128,0,'client_error','2025-11-13 17:18:21','2025-11-13 17:18:21'),('req_6916754db4bc7_8697','req_6916754db4bc7_8697','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'fYxj8C7T7BVGau9Is1JmUW8sPJubLCNivYcO67NP','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,54.107,29360128,0,'client_error','2025-11-13 17:18:21','2025-11-13 17:18:21'),('req_6916754e45109_8370','req_6916754e45109_8370','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'3CvPbBVKdYzxUzrDwVRl5w5nKRJ984lDpLTaiQ4j','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,52.274,29360128,0,'client_error','2025-11-13 17:18:22','2025-11-13 17:18:22'),('req_6916754eab698_2626','req_6916754eab698_2626','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ziUFlktvAbpShRLBdRyrAVPIOIsVHEEeKcCqrvS4','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,22.078,29360128,0,'client_error','2025-11-13 17:18:22','2025-11-13 17:18:22'),('req_6916754f166b3_6954','req_6916754f166b3_6954','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'7DwcBC7omhqAv7qS9wTpRFYf5TLIZ90UMT3xIZvC','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,43.873,29360128,0,'client_error','2025-11-13 17:18:23','2025-11-13 17:18:23'),('req_6916754f7f69d_7839','req_6916754f7f69d_7839','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'8Lt2Jh0IBIQaDBUsQS8tP17nLyUl6M8TzrhVzvUj','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,25.499,29360128,0,'client_error','2025-11-13 17:18:23','2025-11-13 17:18:23'),('req_6916754fc9cd4_8812','req_6916754fc9cd4_8812','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'9mUynbVDeO6gG03sHUdsQYTEmlL2V1ThLOC6NGvz','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,31.151,29360128,0,'client_error','2025-11-13 17:18:23','2025-11-13 17:18:23'),('req_691675503934c_5960','req_691675503934c_5960','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'NKNLdGBOYG60QPca5JvkDYk7Or8j2eE4ZisUq4Sg','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,46.399,29360128,0,'client_error','2025-11-13 17:18:24','2025-11-13 17:18:24'),('req_691675509bca0_6673','req_691675509bca0_6673','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'qg0v2yBiNzFECxjlgljfteX4c4zZO4EXTTOeziYs','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,33.802,29360128,0,'client_error','2025-11-13 17:18:24','2025-11-13 17:18:24'),('req_6916755100d50_9644','req_6916755100d50_9644','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'e0QlrVcMB1yRnFYmb2ojAn4bNoELpP5ddZ97Dih6','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,33.675,29360128,0,'client_error','2025-11-13 17:18:25','2025-11-13 17:18:25'),('req_69167551596b6_3180','req_69167551596b6_3180','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'qLn0VotRxURnzqq4PRimdvaY141RrN2WPgquNpl1','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,38.006,29360128,0,'client_error','2025-11-13 17:18:25','2025-11-13 17:18:25'),('req_69167551ab325_5542','req_69167551ab325_5542','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'wOBcIaVbVVsI76tvdoO82VH9JNFcqkEz5zjptOBQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.455,29360128,0,'client_error','2025-11-13 17:18:25','2025-11-13 17:18:25'),('req_691675522968a_8012','req_691675522968a_8012','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'wLQEhEzChrZn60yosLPmN1utOqFGbXg0FpH7kWMG','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,30.271,29360128,0,'client_error','2025-11-13 17:18:26','2025-11-13 17:18:26'),('req_6916755289528_7922','req_6916755289528_7922','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'qa8rG5jaJdNHsTqi7iWWPLA864DYUFAhB43K2Vrj','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,33.183,29360128,0,'client_error','2025-11-13 17:18:26','2025-11-13 17:18:26'),('req_69167552e1ce9_1039','req_69167552e1ce9_1039','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'51s7G30eflHu5Xbflpn5vWUoDanWVufQT11l0jVU','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,38.678,29360128,0,'client_error','2025-11-13 17:18:26','2025-11-13 17:18:26'),('req_6916755342926_8852','req_6916755342926_8852','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'I2kKOSoHVLBbeEptgPwCYZNiawPtfOrAXqhZCGnb','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,29.886,29360128,0,'client_error','2025-11-13 17:18:27','2025-11-13 17:18:27'),('req_6916755394c4e_8342','req_6916755394c4e_8342','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'PZFf2awOpxQTUxmGDRgpYUP9sEiFmo1YGS1pfUGC','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,23.049,29360128,0,'client_error','2025-11-13 17:18:27','2025-11-13 17:18:27'),('req_69167553e14e9_3054','req_69167553e14e9_3054','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'gDTzufjmgr4pzKCnaamOXZsG48UeSMeyhurvsUVt','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,29.322,29360128,0,'client_error','2025-11-13 17:18:27','2025-11-13 17:18:27'),('req_691675544032b_9427','req_691675544032b_9427','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'EtzTDAv09jR2m4i5WzV3AHbJ10Ag0ge67kUphUV0','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,45.403,29360128,0,'client_error','2025-11-13 17:18:28','2025-11-13 17:18:28'),('req_69167554bb987_9998','req_69167554bb987_9998','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'rMfglEe1IcWWdVVh7jnCxV1OCzNZnGoFaKj5RYtX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,37.493,31457280,0,'client_error','2025-11-13 17:18:28','2025-11-13 17:18:28'),('req_691675551fece_3917','req_691675551fece_3917','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'oIMX5e72SLHIudich8FxpC72iqakinDtYxN8ADTN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,29.369,29360128,0,'client_error','2025-11-13 17:18:29','2025-11-13 17:18:29'),('req_69167555724e7_6334','req_69167555724e7_6334','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'fZb0viK0OAmk0YB90OQVMBa7lRg0HBjSlIgqmfC0','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,64.184,29360128,0,'client_error','2025-11-13 17:18:29','2025-11-13 17:18:29'),('req_69167555d43e6_5804','req_69167555d43e6_5804','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'yWr6PkF2QxLU8Olp0WvLojCJrhLP0S0QhewV3BjI','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,86.344,29360128,0,'client_error','2025-11-13 17:18:29','2025-11-13 17:18:29'),('req_6916755650b34_2443','req_6916755650b34_2443','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'DOXu38EXSdsoKGM97auLSM3kkK8pCNMbPwn2UzBV','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,53.452,29360128,0,'client_error','2025-11-13 17:18:30','2025-11-13 17:18:30'),('req_69167556a3434_8238','req_69167556a3434_8238','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'jhEbbV2nasN710jw70PEokw8Qao6kwV7GJBWTAxr','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,38.765,29360128,0,'client_error','2025-11-13 17:18:30','2025-11-13 17:18:30'),('req_6916755720679_3438','req_6916755720679_3438','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'6KeYBdoOu0sJ23YJvF8YhVya1FaPXTFnHHQpmxQD','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,49.518,29360128,0,'client_error','2025-11-13 17:18:31','2025-11-13 17:18:31'),('req_6916755772f11_5771','req_6916755772f11_5771','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'2sUDx4Xfv8En5wrKu6iZPDnhKwzCgA6nya52pmsP','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,24.518,29360128,0,'client_error','2025-11-13 17:18:31','2025-11-13 17:18:31'),('req_69167557c0f10_6086','req_69167557c0f10_6086','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Ae0WU24qCyXTd69YHEax5YhQJK1CWTZchz0Bp1mx','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,30.080,29360128,0,'client_error','2025-11-13 17:18:31','2025-11-13 17:18:31'),('req_6916755821e9f_9187','req_6916755821e9f_9187','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'1CuLyKixbqE6eoECxka4f2dOCDZFFcsrm7l8eM7c','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.965,29360128,0,'client_error','2025-11-13 17:18:32','2025-11-13 17:18:32'),('req_691675587fcbc_2316','req_691675587fcbc_2316','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'V9zmt2HWlEoZv6ed7MI1veSfwR2KrLJGvyfl8Rqy','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,38.976,29360128,0,'client_error','2025-11-13 17:18:32','2025-11-13 17:18:32'),('req_69167558dec3b_5487','req_69167558dec3b_5487','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'k8cIFNAR5oGj58fhDfof24t8lNz51kcWZIUBX4Pn','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,28.258,29360128,0,'client_error','2025-11-13 17:18:32','2025-11-13 17:18:32'),('req_691675593a7c3_8887','req_691675593a7c3_8887','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'utqahImD9ZDMHu1dAdpHFUiHC4LIFqmvt56SEqA9','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,35.524,29360128,0,'client_error','2025-11-13 17:18:33','2025-11-13 17:18:33'),('req_6916755993148_5754','req_6916755993148_5754','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'DMvrfz1G4psyghmaQSKoxrD0qhDEvsYcbfSwvqbA','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,40.470,29360128,0,'client_error','2025-11-13 17:18:33','2025-11-13 17:18:33'),('req_6916755a0224e_3501','req_6916755a0224e_3501','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'t6UA2lbyNyYN3o2qz4drQAMWitp9L2WehvII3l0A','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,42.416,29360128,0,'client_error','2025-11-13 17:18:34','2025-11-13 17:18:34'),('req_6916755a69821_7410','req_6916755a69821_7410','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'dTgim6KHCmEvTPDsM6yvg8Ti8xuWdCwfnEkF9SNf','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,53.866,29360128,0,'client_error','2025-11-13 17:18:34','2025-11-13 17:18:34'),('req_6916755ab3954_7271','req_6916755ab3954_7271','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'drxFWLyywJC0A82mpwKxGwJCJTFQIkjLignkVsWd','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,28.030,29360128,0,'client_error','2025-11-13 17:18:34','2025-11-13 17:18:34'),('req_6916755b0dbb5_6701','req_6916755b0dbb5_6701','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'XiAqsZtNOHKN6eQvgOTmuOCWoWPKXGOWLadpeasI','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,42.220,29360128,0,'client_error','2025-11-13 17:18:35','2025-11-13 17:18:35'),('req_6916755b6fbc7_6375','req_6916755b6fbc7_6375','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'afgFqTm0HMpzcPs3y9hZgeF5NFjxq9UepYo13bw7','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,42.774,29360128,0,'client_error','2025-11-13 17:18:35','2025-11-13 17:18:35'),('req_6916755bd07ce_6965','req_6916755bd07ce_6965','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'fOAG6gTaTLgCqMS7RPE9TKwJZvoxvCNq6czwg7rV','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,56.717,29360128,0,'client_error','2025-11-13 17:18:35','2025-11-13 17:18:35'),('req_6916755c4d74e_6569','req_6916755c4d74e_6569','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'EXHR4cROcNQLP3mVzvEWGiiy7ZPzKqyTeGjP9G1p','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.877,29360128,0,'client_error','2025-11-13 17:18:36','2025-11-13 17:18:36'),('req_6916755cabe61_9445','req_6916755cabe61_9445','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'nU1riPZME0YCDvWiwde2DqDLg3KC037RrwZmKldb','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,31.111,29360128,0,'client_error','2025-11-13 17:18:36','2025-11-13 17:18:36'),('req_6916755d13c90_8922','req_6916755d13c90_8922','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'k21KRslYhJzTeCQ3U11S2ShlS2Xex9Pq9Zd1ybut','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,37.547,29360128,0,'client_error','2025-11-13 17:18:37','2025-11-13 17:18:37'),('req_6916755d76b66_9275','req_6916755d76b66_9275','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'E6lvlHS49XA7Cj6Cg9CHcVoW6tYxFmGvEHMuaTj9','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,53.203,29360128,0,'client_error','2025-11-13 17:18:37','2025-11-13 17:18:37'),('req_6916755dddd56_3934','req_6916755dddd56_3934','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'5R0rj61zKUJXYkYPKeqenn2d2jaqhvYWi4uUNUAf','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.547,29360128,0,'client_error','2025-11-13 17:18:37','2025-11-13 17:18:37'),('req_6916755e3e77b_2356','req_6916755e3e77b_2356','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'wV8xlNwIAr9TYS2hF2qIyrbahZbbvO11voWPYlVX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,44.517,29360128,0,'client_error','2025-11-13 17:18:38','2025-11-13 17:18:38'),('req_6916755e9c91c_7354','req_6916755e9c91c_7354','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'JIKRZEyiZWxzM51WTYL80xHM99sib6R4YvbK0hfN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,35.105,29360128,0,'client_error','2025-11-13 17:18:38','2025-11-13 17:18:38'),('req_6916755eef6a4_5465','req_6916755eef6a4_5465','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'wfazzehGbjYCErSms1F6tnSiLbMUe4n4gFjQEbLx','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,26.159,29360128,0,'client_error','2025-11-13 17:18:38','2025-11-13 17:18:39'),('req_6916755f5b070_4578','req_6916755f5b070_4578','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ylvdn4LNaGwGFMz99fJkBhKvshdKkWSLgS97j1Cw','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,33.231,29360128,0,'client_error','2025-11-13 17:18:39','2025-11-13 17:18:39'),('req_6916755fc9e85_1640','req_6916755fc9e85_1640','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Ti8Ncz1DLGUNaq9ILv6FzzwJePA0csADVGAN3Dzc','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,49.145,29360128,0,'client_error','2025-11-13 17:18:39','2025-11-13 17:18:39'),('req_6916756069812_5041','req_6916756069812_5041','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'6mi7g8BF1O2vsW7sVpOvjmA2OK0A1CQCqNZhkg1l','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,71.485,33554432,0,'client_error','2025-11-13 17:18:40','2025-11-13 17:18:40'),('req_69167560ea3c6_1475','req_69167560ea3c6_1475','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'cwVFEMJn1XpLsnUzWMl8T6pKRiWyM9dmVl9LJrxQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,31.911,31457280,0,'client_error','2025-11-13 17:18:40','2025-11-13 17:18:40'),('req_691675614cee8_4662','req_691675614cee8_4662','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'f7G46QnspBPlOR5WSXrZg3B9Bl9aCTT6A5XAJHsO','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,64.277,29360128,0,'client_error','2025-11-13 17:18:41','2025-11-13 17:18:41'),('req_69167561b74f0_5743','req_69167561b74f0_5743','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ILL4WZDqefxO9wP7pdQj6LYfoBlDzogFh5K0gQVZ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',429,12076,53.752,29360128,0,'client_error','2025-11-13 17:18:41','2025-11-13 17:18:41'),('req_691675622fe32_7199','req_691675622fe32_7199','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'D5r5j01Gibp4Xs8Z7gx1yFd1vPsMMQisBsc42hOT','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,62.782,29360128,1,'success','2025-11-13 17:18:42','2025-11-13 17:18:42'),('req_69167562ab113_2889','req_69167562ab113_2889','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'iOIbmXIkHTI3StVL06pRP7tqa3kg6uCHP2ntWwe0','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,65.661,29360128,1,'success','2025-11-13 17:18:42','2025-11-13 17:18:42'),('req_6916756316b01_9095','req_6916756316b01_9095','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'r0F5ea3wIhReRXVGkwJPMX6wQUz43OAZGTCUZLN6','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,37.616,29360128,1,'success','2025-11-13 17:18:43','2025-11-13 17:18:43'),('req_6916756371f02_9777','req_6916756371f02_9777','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'1ezPzjeAjP6UCU50eeBtnzqJDIHHPEbB6O43MMYV','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,41.995,29360128,1,'success','2025-11-13 17:18:43','2025-11-13 17:18:43'),('req_69167563cf5a8_8308','req_69167563cf5a8_8308','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'bOLRGCYd5Eq1cQnA9nteGEgbRLmOCHU1ZZm0iupQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,39.980,29360128,1,'success','2025-11-13 17:18:43','2025-11-13 17:18:43'),('req_6916756435d13_2455','req_6916756435d13_2455','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'CCh5TMZa2hBoiyFYeYZ3yLXqWOfwgztRwfRHGOvt','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,40.480,29360128,1,'success','2025-11-13 17:18:44','2025-11-13 17:18:44'),('req_691675649d998_2039','req_691675649d998_2039','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'KKZcGOZ5QpnJPBiOyNILc8Dvw9GhAuADm0XtXxE9','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,55.715,29360128,1,'success','2025-11-13 17:18:44','2025-11-13 17:18:44'),('req_6916756516451_6204','req_6916756516451_6204','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ryUKY4pHckwznaqwNL3kQPWUQ01WuadDuT84xnpw','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,49.957,29360128,1,'success','2025-11-13 17:18:45','2025-11-13 17:18:45'),('req_6916756603cd3_5532','req_6916756603cd3_5532','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'GmX7LNseIB08WE54Cyzq5A2RY4qOqSfVkDyB3PVP','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,33.934,29360128,1,'success','2025-11-13 17:18:46','2025-11-13 17:18:46'),('req_6916756666c1d_9074','req_6916756666c1d_9074','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'r7oQj1LffcSRNiT9Z67CSAG2ildnT8OnjK0J1jCY','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,68.785,29360128,1,'success','2025-11-13 17:18:46','2025-11-13 17:18:46'),('req_69167566cec54_7783','req_69167566cec54_7783','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'D0EyxAP4xj37Eawz86RebR3vj3jlvU3ZwRIuqqyQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,39.239,29360128,1,'success','2025-11-13 17:18:46','2025-11-13 17:18:46'),('req_6916756742405_7172','req_6916756742405_7172','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'HhCTHsQZGQze5atwuA6abhfo0E3nyxe5PYSzMp4k','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,75.652,31457280,1,'success','2025-11-13 17:18:47','2025-11-13 17:18:47'),('req_69167567aff8f_6942','req_69167567aff8f_6942','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'qIm0WdG6ZaOSMpeUUH486mXvOd8tGtzMOJcpoFJn','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,57.349,29360128,1,'success','2025-11-13 17:18:47','2025-11-13 17:18:47'),('req_691675681b4c1_5319','req_691675681b4c1_5319','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'xkTZqcBBx4fQ6iltp05HNMmi5c6L4UDKah4fKCZM','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,33.042,29360128,1,'success','2025-11-13 17:18:48','2025-11-13 17:18:48'),('req_6916756876baa_7845','req_6916756876baa_7845','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'XElsPzRb6BTvWA8BAnqHyeMtS296rJ4i8hSGrNlA','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,77.489,29360128,1,'success','2025-11-13 17:18:48','2025-11-13 17:18:48'),('req_69167568e0bcb_5575','req_69167568e0bcb_5575','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'PtdU64MhgTdvYWxztRrEKlBoBK6uUge1P89Ou8sf','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,54.665,29360128,1,'success','2025-11-13 17:18:48','2025-11-13 17:18:48'),('req_69167569636ee_4192','req_69167569636ee_4192','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'CPmn3SEiBJJvanFICTMibORuR5tLDnTUCqFWHMb1','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,45.150,29360128,1,'success','2025-11-13 17:18:49','2025-11-13 17:18:49'),('req_69167569cc70e_9204','req_69167569cc70e_9204','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'eGGsritDgSkRD1hlBJMHvy6pJllmXM9zeSrYBp3O','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,44.486,29360128,1,'success','2025-11-13 17:18:49','2025-11-13 17:18:49'),('req_6916756a53d84_8329','req_6916756a53d84_8329','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'M6kd9cA8mN0mo7VBk7zpDApHguOgTM7hMg83jNvj','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,101.948,31457280,1,'success','2025-11-13 17:18:50','2025-11-13 17:18:50'),('req_6916756ad0583_8357','req_6916756ad0583_8357','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'PqwcD5cnK5R16LmUmJ0d2ZFn4Iw87K6YWNeCFCov','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,70.932,31457280,1,'success','2025-11-13 17:18:50','2025-11-13 17:18:50'),('req_6916756b56ea9_5938','req_6916756b56ea9_5938','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'vOAhISIxv0iR9z5ZGf0yLCYMBz1tUY1TKcQ0GteU','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,71.300,31457280,1,'success','2025-11-13 17:18:51','2025-11-13 17:18:51'),('req_6916756bd2e4c_8474','req_6916756bd2e4c_8474','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Jr4J26DFXwn1dbmGIwjhHna8jVcRP3HkBN0bxr3K','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,36.966,29360128,1,'success','2025-11-13 17:18:51','2025-11-13 17:18:51'),('req_6916756c8f12b_7262','req_6916756c8f12b_7262','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'M46Yi1Qg3OgrSCJzu41in6wF8swfvKkNqZO1fdWQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,70.001,33554432,1,'success','2025-11-13 17:18:52','2025-11-13 17:18:52'),('req_6916756d2b49c_8401','req_6916756d2b49c_8401','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ToU7m5mA0VSwSGBAFE5948Hdic8Wo6kT7wnYlabL','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,44.182,31457280,1,'success','2025-11-13 17:18:53','2025-11-13 17:18:53'),('req_6916756de1ebe_8799','req_6916756de1ebe_8799','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'uyTvphMcGda1jhdoezHGAM6Ize64GTUPGhJ031SQ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,50.496,33554432,1,'success','2025-11-13 17:18:53','2025-11-13 17:18:53'),('req_6916756e86cee_1158','req_6916756e86cee_1158','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Oq2H0NS0ZOJGGBEmnPgsl9LHe4uiPqpOiTqhdDiF','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,54.989,31457280,1,'success','2025-11-13 17:18:54','2025-11-13 17:18:54'),('req_6916756f58abf_2499','req_6916756f58abf_2499','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'RhzZs6nHlH5dyZoumAwg43KkncwGPJCcynaLmQtX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,86.011,33554432,1,'success','2025-11-13 17:18:55','2025-11-13 17:18:55'),('req_6916757163cd8_6754','req_6916757163cd8_6754','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'1xuhKVUXSl8xZk0I5wWbct3KozuBRYJsr07vkawz','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,85.774,39583744,1,'success','2025-11-13 17:18:57','2025-11-13 17:18:57'),('req_6916757283031_9340','req_6916757283031_9340','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'5A8nNPHe6488Heq8qUad07whU7A2ZqZtwkB1Dp0Z','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,78.400,39780352,1,'success','2025-11-13 17:18:58','2025-11-13 17:18:58'),('req_69167573c7d06_9279','req_69167573c7d06_9279','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ZapoKwwx9ANBz4Ik2ylSRhkZcdhgbPmgGxqTFWKp','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,105.891,41795584,1,'success','2025-11-13 17:18:59','2025-11-13 17:18:59'),('req_6916757542fb5_4180','req_6916757542fb5_4180','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'3PcEVmlOAB2f56wLhpdwthhIhBaqIvAvb6y0pWST','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,91.304,42926080,1,'success','2025-11-13 17:19:01','2025-11-13 17:19:01'),('req_69167577167b0_6482','req_69167577167b0_6482','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'GrrwqV6LA81tPM5QF9XRhuuh4PqIu44MIh8e0ODt','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,139.777,46415872,1,'success','2025-11-13 17:19:03','2025-11-13 17:19:03'),('req_69167578bdd1a_7823','req_69167578bdd1a_7823','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'kE2UokFcBI1dhN1B32vSOEAaF0aZ1Sa393KcwVY5','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,116.431,46120960,1,'success','2025-11-13 17:19:04','2025-11-13 17:19:04'),('req_6916757ac1a2e_5085','req_6916757ac1a2e_5085','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'L377TARvLX68Xngz3i9PkyXfSEVvKGICQ4pdlw2J','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,147.815,51380224,1,'success','2025-11-13 17:19:06','2025-11-13 17:19:06'),('req_6916757d7e43d_7116','req_6916757d7e43d_7116','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'I6Hqu8lN7e4xBroggR4DOArnjG52zlPO2DfOJnTc','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,158.689,50003968,1,'success','2025-11-13 17:19:09','2025-11-13 17:19:09'),('req_691675821f0bf_6325','req_691675821f0bf_6325','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'rgLDAeM8f43DLKu6oKNrYifsjAZo6Ir9u4wgFHXX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,148.928,53542912,1,'success','2025-11-13 17:19:14','2025-11-13 17:19:14'),('req_6916758545912_9590','req_6916758545912_9590','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'h9ekQEVaw6DOQU5D69pz2MwOq5RxLyhbXg30DoFW','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,146.476,53641216,1,'success','2025-11-13 17:19:17','2025-11-13 17:19:17'),('req_691675e90fb21_2719','req_691675e90fb21_2719','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'sBLB7nr5WY0oxH4LCDjLED3chSbh4gTHucrlDVfq','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_6281261268811@c.us_3EB0728997FC7C432619\", \"to\": \"62811801641@c.us\", \"body\": null, \"from\": \"6281261268811@c.us\", \"type\": \"notification_template\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763079656}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,192.107,33554432,1,'success','2025-11-13 17:20:57','2025-11-13 17:20:57'),('req_691675e98896c_9241','req_691675e98896c_9241','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/chats/sync','127.0.0.1','WhatsApp-WebJS-Service/1.0',NULL,NULL,'EdLsRZ5HPh142iCd9jfT7LSlVISGJFJlnCJ0ZATA','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',500,12709,30.316,29360128,0,'server_error','2025-11-13 17:20:57','2025-11-13 17:20:57'),('req_691675e9cfb76_7531','req_691675e9cfb76_7531','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/chats/sync','127.0.0.1','WhatsApp-WebJS-Service/1.0',NULL,NULL,'atpyogT9E75i64iZaKtWTUO2Zr2BjVnrXrWBjbvJ','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',500,12709,35.473,31457280,0,'server_error','2025-11-13 17:20:57','2025-11-13 17:20:57'),('req_691675eacd8f5_3470','req_691675eacd8f5_3470','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/chats/sync','127.0.0.1','WhatsApp-WebJS-Service/1.0',NULL,NULL,'4fXY78OHDzpxrfvAOQ4SPuoX9sSnvDqVJa4Tu0vG','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',500,12709,24.879,29360128,0,'server_error','2025-11-13 17:20:58','2025-11-13 17:20:58'),('req_691675eb30fa2_9741','req_691675eb30fa2_9741','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/chats/sync','127.0.0.1','WhatsApp-WebJS-Service/1.0',NULL,NULL,'IbDm22Vg2JDrNUatc0Kq8qI1Q3MjfV1WI2DPcTac','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',500,12709,25.792,31457280,0,'server_error','2025-11-13 17:20:59','2025-11-13 17:20:59'),('req_691675ed18675_3019','req_691675ed18675_3019','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/chats/sync','127.0.0.1','WhatsApp-WebJS-Service/1.0',NULL,NULL,'ManoNgHvcEei95G9lsqFrhiD8R65jvYPFM2KyA13','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',500,12709,21.663,29360128,0,'server_error','2025-11-13 17:21:01','2025-11-13 17:21:01'),('req_691675ed724c9_3510','req_691675ed724c9_3510','request_attempt','unknown','POST','http://127.0.0.1:8000/api/whatsapp/chats/sync','127.0.0.1','WhatsApp-WebJS-Service/1.0',NULL,NULL,'oD1qDpfeRIOU6xC8SqmPhHBmPtquF5jkSNyNOtew','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',500,12709,21.502,31457280,0,'server_error','2025-11-13 17:21:01','2025-11-13 17:21:01'),('req_691676bbaf687_4146','req_691676bbaf687_4146','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'l2cQVCfGM7IgScswQu8BkOqosNwuJ4dD27q2lkx6','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,113.416,43696128,1,'success','2025-11-13 17:24:27','2025-11-13 17:24:27'),('req_691676c00bf27_7639','req_691676c00bf27_7639','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'2zEGnMIMJuWdezQukLliIkJrF57FmjWhVpKbuFaE','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,98.837,33554432,1,'success','2025-11-13 17:24:32','2025-11-13 17:24:32'),('req_69167728547e2_2266','req_69167728547e2_2266','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'iwYZ8h2WyoMfMmwDaZUPf8MkqMboPAbQABGGCD6I','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,45.367,31457280,1,'success','2025-11-13 17:26:16','2025-11-13 17:26:16'),('req_691677a008d4c_3727','req_691677a008d4c_3727','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'AOV1J9u0wjw4dASmQVFXhfet5MuCJRWPPD4CLe4N','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,51.426,31457280,1,'success','2025-11-13 17:28:16','2025-11-13 17:28:16'),('req_69167871ca2ae_2765','req_69167871ca2ae_2765','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'vsG6tKfwjdLzf0pPZrjQ43GC6k3FD9ytHgMA8uXL','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_6281383963619@c.us_ACBB3B7997E9FFD7B5101D7C95E37EAB\", \"to\": \"62811801641@c.us\", \"body\": \"Mas db pro prod udh dibackup dan dites import aman\", \"from\": \"6281383963619@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763080305}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,211.876,33554432,1,'success','2025-11-13 17:31:45','2025-11-13 17:31:46'),('req_6916787f2091c_7295','req_6916787f2091c_7295','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'dMaYlILpyFHFSOWtXDvKRKWbrKLFpOtZblxnUUf8','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_6281383963619@c.us_AC8B3DD442BC808583F93F70F377C6C7\", \"to\": \"62811801641@c.us\", \"body\": \"Niatnya clear malem ini\", \"from\": \"6281383963619@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763080318}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,104.141,33554432,1,'success','2025-11-13 17:31:59','2025-11-13 17:31:59'),('req_69167afdcc44b_5311','req_69167afdcc44b_5311','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'LZk4Wog9xdHU9ngyIlJc4VeO3PiUGJSvfUhHNrGX','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,47.927,31457280,1,'success','2025-11-13 17:42:37','2025-11-13 17:42:37'),('req_69167b94eecfe_8806','req_69167b94eecfe_8806','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'rvNoaVbCPIDmQMe2RWuq2a3PC3ZCdT5YHhF9f1kN','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,39.016,31457280,1,'success','2025-11-13 17:45:08','2025-11-13 17:45:09'),('req_69167ba43d47a_2635','req_69167ba43d47a_2635','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'zqGLVmR3QozbNPNa76r2KHOy5cqYdgCYjr0zXrRa','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,31.365,31457280,1,'success','2025-11-13 17:45:24','2025-11-13 17:45:24'),('req_69167bc3384e6_1425','req_69167bc3384e6_1425','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'wGvNo6c1xC9re3yUCPpz62q9pjGCtUgnPlhJItg4','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255-1483620503@g.us_3AC2CA7C224F0457E57B_4196350857418@lid\", \"to\": \"62811801641@c.us\", \"body\": \"Aamiiin ya rabb\", \"from\": \"628999338255-1483620503@g.us\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"628999338255-1483620503@g.us\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763081154, \"group_name\": \"Abdul Rahman\'s\", \"sender_name\": \"Dita Yunita Rahman\", \"sender_phone\": \"628999338255\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,83.366,33554432,1,'success','2025-11-13 17:45:55','2025-11-13 17:45:55'),('req_69167bdcb01b2_3929','req_69167bdcb01b2_3929','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'q9EF9DHUZGDflJhPunWsSul9XLEsN59iVkOfd9na','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,169.735,33554432,1,'success','2025-11-13 17:46:20','2025-11-13 17:46:20'),('req_69167bec0e597_3300','req_69167bec0e597_3300','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'9HDnIjAeCdmFs35yImPPdQHtbtg7Dw7qseISdXLC','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,38.134,31457280,1,'success','2025-11-13 17:46:36','2025-11-13 17:46:36'),('req_69167bf2347cd_2629','req_69167bf2347cd_2629','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'yAMqtd8tg2KcYrOz9fYpiSbFEG8BifDjBw5zsJGC','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255-1483620503@g.us_3A04CEE59EB358951514_50685060104331@lid\", \"to\": \"62811801641@c.us\", \"body\": \"Aamiin🤲🏻\", \"from\": \"628999338255-1483620503@g.us\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"628999338255-1483620503@g.us\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763081201, \"group_name\": \"Abdul Rahman\'s\", \"sender_name\": \"Zul\", \"sender_phone\": \"6281316597432\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,65.082,33554432,1,'success','2025-11-13 17:46:42','2025-11-13 17:46:42'),('req_69167c07b0ca6_6859','req_69167c07b0ca6_6859','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'3axPJCpEKoegyDBwJzU4m6DMLhxgBuwrWR5qnApm','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_628999338255-1483620503@g.us_3AA42BE30F93562E0D36_55787548364846@lid\", \"to\": \"62811801641@c.us\", \"body\": \"Aamiin yarabb❤️\", \"from\": \"628999338255-1483620503@g.us\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"628999338255-1483620503@g.us\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763081222, \"group_name\": \"Abdul Rahman\'s\", \"sender_name\": \"Itsme\", \"sender_phone\": \"62895803201166\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,63.135,33554432,1,'success','2025-11-13 17:47:03','2025-11-13 17:47:03'),('req_69167c55b13d3_6477','req_69167c55b13d3_6477','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'uMUtugRsmnjKjMRBN6mjWuFYwOFzpEJuF3mBZPLP','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,50.513,31457280,1,'success','2025-11-13 17:48:21','2025-11-13 17:48:21'),('req_69167cd8bec07_2434','req_69167cd8bec07_2434','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'hxqiRUzQdidNupEnAJ7TaFxcO9Xaxiwv3tElzcfy','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,43.451,31457280,1,'success','2025-11-13 17:50:32','2025-11-13 17:50:32'),('req_69167d2203002_8105','req_69167d2203002_8105','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'Akv3l26pKyMAOfmcEhIs3QZJsIssG7gZBP4NoZ9H','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_6281383963619@c.us_AC8944E62F5B5549457C187C33730855\", \"to\": \"62811801641@c.us\", \"body\": \"Siap mas\", \"from\": \"6281383963619@c.us\", \"type\": \"chat\", \"from_me\": false, \"chat_type\": \"private\", \"has_media\": false, \"timestamp\": 1763081505}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,112.478,33554432,1,'success','2025-11-13 17:51:46','2025-11-13 17:51:46'),('req_69167fd670dcf_7450','req_69167fd670dcf_7450','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'IMO2xn96O8yirPX2lKXXEThJGHwIfsTjCRo8ZjMY','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,58.387,31457280,1,'success','2025-11-13 18:03:18','2025-11-13 18:03:18'),('req_6916825b805e9_8218','req_6916825b805e9_8218','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'kSKcR77D3jWlAZwsUm0i0cZjwKWrQ8TOivd04F0Z','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_3ADE870D89E63104DDDB_628119868886@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"Konsep mandi pagi diperkenalkan oleh negara mana sih?\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763082841, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Zahra\", \"sender_phone\": \"628119868886\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,46.204,31457280,1,'success','2025-11-13 18:14:03','2025-11-13 18:14:03'),('req_6916826d1fee2_2627','req_6916826d1fee2_2627','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'PyVTgBExM8sImQJzJCtac1E23cVv8e78as1Z5nF8','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,39.005,31457280,1,'success','2025-11-13 18:14:21','2025-11-13 18:14:21'),('req_6916827634484_1103','req_6916827634484_1103','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'AqhGaKQSnMXhJO9Y18gm7mXePn1FYmktS7FI5VtV','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,40.835,31457280,1,'success','2025-11-13 18:14:30','2025-11-13 18:14:30'),('req_69168278dca08_2051','req_69168278dca08_2051','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'ODa2Yl4Z4eKWbdSPKTN3U6ndYqOwG1rBUNOrNB1n','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,32.682,31457280,1,'success','2025-11-13 18:14:32','2025-11-13 18:14:32'),('req_6916833be9c2b_5227','req_6916833be9c2b_5227','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'4bQlzt9GDQmAgyPzqfk80TUr0tcOkqg8ffpAdUDG','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"input_summary\": {\"data\": {\"message\": {\"id\": \"false_status@broadcast_A53665C0B7F750DEF2C449DDD3503041_6281213092882@c.us\", \"to\": \"62811801641@c.us\", \"body\": \"Nasi Bakar Teri Sold Out..\\n\\nReady 2 Pack Nasi Bakar Ayam Suwir Bumbu Bali \\n2 pack Nasi Bakar Tongkol Kemangi\", \"from\": \"status@broadcast\", \"type\": \"chat\", \"from_me\": false, \"group_id\": \"status@broadcast\", \"chat_type\": \"group\", \"has_media\": false, \"timestamp\": 1763083066, \"group_name\": \"Unnamed Group\", \"sender_name\": \"Uchy Sudhanto\", \"sender_phone\": \"6281213092882\"}, \"session_id\": \"webjs_1_1763079321_JSmiuJVF\", \"workspace_id\": 1}, \"event\": \"message_received\"}, \"accept_language\": null}',200,21,75.489,31457280,1,'success','2025-11-13 18:17:47','2025-11-13 18:17:48'),('req_691683b21ac7a_7939','req_691683b21ac7a_7939','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'kem5ICjp5SK4xr8M9EIwUsDiUpUlJl9Y8wudX8W5','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,139.433,50839552,1,'success','2025-11-13 18:19:46','2025-11-13 18:19:46'),('req_691683d7a424d_9882','req_691683d7a424d_9882','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'khzp39OxjeJlyf9bLctuCJBDrqCFGSAMIPPlRAbr','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,145.491,53100544,1,'success','2025-11-13 18:20:23','2025-11-13 18:20:23'),('req_6916843c12833_3958','req_6916843c12833_3958','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'wky2zsojOaf4en9sAahhx6faT6V6oqWBCgQgfu3h','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,62.352,31457280,1,'success','2025-11-13 18:22:04','2025-11-13 18:22:04'),('req_691684505748a_1128','req_691684505748a_1128','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'atLGfFFEZySvWw0y2eg35pMtXZqJCCJIzS7gWFoO','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,47.090,31457280,1,'success','2025-11-13 18:22:24','2025-11-13 18:22:24'),('req_691684969fd36_9553','req_691684969fd36_9553','request_attempt','generated::19F5DBF2lAwFfEh3','POST','http://127.0.0.1:8000/api/whatsapp/webhooks/webjs','127.0.0.1','axios/1.12.2',NULL,NULL,'oQAmy36poW0HCiIdldFRQi1vS9xCg28vkazfqLVB','{\"is_ajax\": false, \"referer\": null, \"expects_json\": true, \"accept_language\": null}',200,21,72.197,31457280,1,'success','2025-11-13 18:23:34','2025-11-13 18:23:34');
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
  `whatsapp_session_id` bigint unsigned DEFAULT NULL,
  `chat_id` int DEFAULT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','success','failed','ongoing') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `retry_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_logs_whatsapp_session_id_foreign` (`whatsapp_session_id`),
  KEY `campaign_logs_campaign_id_whatsapp_session_id_index` (`campaign_id`,`whatsapp_session_id`),
  CONSTRAINT `campaign_logs_whatsapp_session_id_foreign` FOREIGN KEY (`whatsapp_session_id`) REFERENCES `whatsapp_sessions` (`id`) ON DELETE SET NULL
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
  `template_id` int NOT NULL,
  `contact_group_id` int NOT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `scheduled_at` datetime DEFAULT NULL,
  `created_by` int NOT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_uuid_unique` (`uuid`),
  KEY `campaigns_workspace_id_index` (`workspace_id`),
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
  `whatsapp_session_id` bigint unsigned DEFAULT NULL,
  `wam_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_id` int NOT NULL,
  `group_id` bigint unsigned DEFAULT NULL COMMENT 'FK to whatsapp_groups for group chats',
  `user_id` bigint unsigned DEFAULT NULL,
  `type` enum('inbound','outbound') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_id` int DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Provider: meta | webjs',
  `chat_type` enum('private','group') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chat type: private contact or group',
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
  KEY `chats_whatsapp_session_id_created_at_index` (`whatsapp_session_id`,`created_at`),
  KEY `idx_chats_provider_type` (`workspace_id`,`provider_type`,`created_at`),
  KEY `idx_chats_chat_type` (`workspace_id`,`chat_type`,`created_at`),
  KEY `idx_chats_type_session` (`workspace_id`,`chat_type`,`whatsapp_session_id`,`created_at`),
  KEY `idx_chats_provider_session` (`workspace_id`,`provider_type`,`whatsapp_session_id`),
  KEY `idx_chats_contact_chat` (`contact_id`,`created_at`),
  KEY `idx_chats_group_chat` (`group_id`,`created_at`),
  KEY `idx_chats_workspace_session_created` (`workspace_id`,`whatsapp_session_id`,`created_at`),
  CONSTRAINT `chats_whatsapp_session_id_foreign` FOREIGN KEY (`whatsapp_session_id`) REFERENCES `whatsapp_sessions` (`id`) ON DELETE SET NULL,
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
-- Table structure for table `contact_sessions`
--

DROP TABLE IF EXISTS `contact_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` bigint unsigned NOT NULL,
  `whatsapp_session_id` bigint unsigned NOT NULL,
  `first_interaction_at` timestamp NULL DEFAULT NULL,
  `last_interaction_at` timestamp NULL DEFAULT NULL,
  `total_messages` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_sessions_contact_id_whatsapp_session_id_unique` (`contact_id`,`whatsapp_session_id`),
  KEY `contact_sessions_contact_id_last_interaction_at_index` (`contact_id`,`last_interaction_at`),
  KEY `contact_sessions_whatsapp_session_id_last_interaction_at_index` (`whatsapp_session_id`,`last_interaction_at`),
  CONSTRAINT `contact_sessions_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_sessions_whatsapp_session_id_foreign` FOREIGN KEY (`whatsapp_session_id`) REFERENCES `whatsapp_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_sessions`
--

LOCK TABLES `contact_sessions` WRITE;
/*!40000 ALTER TABLE `contact_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_sessions` ENABLE KEYS */;
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
  `source_session_id` bigint unsigned DEFAULT NULL,
  `source_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'meta',
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
  KEY `contacts_source_session_id_foreign` (`source_session_id`),
  KEY `contacts_workspace_id_source_session_id_index` (`workspace_id`,`source_session_id`),
  FULLTEXT KEY `fulltext_contacts_name_email_phone` (`first_name`,`last_name`,`phone`,`email`),
  CONSTRAINT `contacts_source_session_id_foreign` FOREIGN KEY (`source_session_id`) REFERENCES `whatsapp_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contacts_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT INTO `contacts` VALUES (1,'3002d913-8b45-4ebd-a04e-30a1e6304cf1',1,17,'webjs','6281261268811',NULL,'+6281261268811',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,'2025-11-13 17:20:57','2025-11-13 17:20:57',NULL),(2,'4c131fb3-b1aa-4be7-8412-3884aa7d0800',1,17,'webjs','6281383963619',NULL,'+6281383963619',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,'2025-11-13 17:31:45','2025-11-13 17:31:45',NULL);
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2024_03_20_050200_create_auto_replies_table',1),(2,'2024_03_20_050311_create_billing_credits_table',1),(3,'2024_03_20_050348_create_billing_debits_table',1),(4,'2024_03_20_050430_create_billing_invoices_table',1),(5,'2024_03_20_050508_create_billing_items_table',1),(6,'2024_03_20_050600_create_billing_payments_table',1),(7,'2024_03_20_050635_create_billing_tax_rates_table',1),(8,'2024_03_20_050711_create_billing_transactions_table',1),(9,'2024_03_20_050751_create_blog_authors_table',1),(10,'2024_03_20_050826_create_blog_categories_table',1),(11,'2024_03_20_050912_create_blog_posts_table',1),(12,'2024_03_20_050959_create_blog_tags_table',1),(13,'2024_03_20_051036_create_campaigns_table',1),(14,'2024_03_20_051111_create_campaign_logs_table',1),(15,'2024_03_20_051154_create_chats_table',1),(16,'2024_03_20_051253_create_chat_logs_table',1),(17,'2024_03_20_051336_create_chat_media_table',1),(18,'2024_03_20_051414_create_contacts_table',1),(19,'2024_03_20_051449_create_contact_groups_table',1),(20,'2024_03_20_051537_create_coupons_table',1),(21,'2024_03_20_051613_create_email_logs_table',1),(22,'2024_03_20_051655_create_email_templates_table',1),(23,'2024_03_20_051739_create_failed_jobs_table',1),(24,'2024_03_20_051807_create_faqs_table',1),(25,'2024_03_20_051847_create_jobs_table',1),(26,'2024_03_20_051919_create_modules_table',1),(27,'2024_03_20_051953_create_notifications_table',1),(28,'2024_03_20_052034_create_workspaces_table',1),(29,'2024_03_20_052107_create_pages_table',1),(30,'2024_03_20_052141_create_password_reset_tokens_table',1),(31,'2024_03_20_052223_create_payment_gateways_table',1),(32,'2024_03_20_052338_create_reviews_table',1),(33,'2024_03_20_052401_create_users_table',1),(34,'2024_03_20_052430_create_roles_table',1),(35,'2024_03_20_052513_create_role_permissions_table',1),(36,'2024_03_20_052620_create_settings_table',1),(37,'2024_03_20_052654_create_subscriptions_table',1),(38,'2024_03_20_052731_create_subscription_plans_table',1),(39,'2024_03_20_052808_create_tax_rates_table',1),(40,'2024_03_20_052839_create_teams_table',1),(41,'2024_03_20_052914_create_team_invites_table',1),(42,'2024_03_20_052920_create_ticket_categories_table',1),(43,'2024_03_20_052956_create_templates_table',1),(44,'2024_03_20_053038_create_tickets_table',1),(45,'2024_03_20_053205_create_ticket_comments_table',1),(46,'2024_04_08_133150_create_workspace_api_keys_table',1),(47,'2024_04_24_211852_create_languages',1),(48,'2024_04_27_155643_create_contact_fields_table',1),(49,'2024_04_27_160152_add_metadata_to_contacts_table',1),(50,'2024_05_11_052902_create_chat_notes_table',1),(51,'2024_05_11_052925_create_chat_tickets_table',1),(52,'2024_05_11_052940_create_chat_ticket_logs_table',1),(53,'2024_05_11_053846_rename_chat_logs_table',1),(54,'2024_05_11_054010_create_chat_logs_2_table',1),(55,'2024_05_11_063255_add_user_id_to_chats_table',1),(56,'2024_05_11_063540_add_role_to_team_invites_table',1),(57,'2024_05_11_063819_update_agent_role_to_teams_table',1),(58,'2024_05_11_064650_add_deleted_by_to_workspace_api_keys_table',1),(59,'2024_05_11_065031_add_workspace_id_to_tickets_table',1),(60,'2024_05_28_080331_make_password_nullable_in_users_table',1),(61,'2024_05_30_125859_modify_campaigns_table',1),(62,'2024_06_03_124254_create_addons_table',1),(63,'2024_06_07_040536_update_users_table_for_facebook_login',1),(64,'2024_06_07_040843_update_chat_media_table',1),(65,'2024_06_07_074903_add_soft_delete_to_teams_and_workspaces',1),(66,'2024_06_09_155053_modify_billing_payments_table',1),(67,'2024_06_12_070820_modify_faqs_table',1),(68,'2024_07_04_053236_modify_amount_columns_in_billing_tables',1),(69,'2024_07_04_054143_modify_contacts_table_encoding',1),(70,'2024_07_09_011419_drop_seo_from_pages_table',1),(71,'2024_07_17_062442_allow_null_content_in_pages_table',1),(72,'2024_07_24_080535_add_latest_chat_created_at_to_contacts_table',1),(73,'2024_08_01_050752_add_ongoing_to_status_enum_in_campaign_logs_table',1),(74,'2024_08_08_130306_add_is_read_to_chats_table',1),(75,'2024_08_10_071237_create_documents_table',1),(76,'2024_10_16_201832_change_metadata_column_in_workspaces_table',1),(77,'2024_11_12_101941_add_license_column_to_addons_table',1),(78,'2024_11_25_114450_add_version_and_update_needed_to_addons_table',1),(79,'2024_11_28_083453_add_tfa_secret_to_users_table',1),(80,'2024_11_29_070806_create_seeder_histories_table',1),(81,'2024_12_20_081118_add_is_plan_restricted_to_addons_table',1),(82,'2024_12_20_130829_add_is_active_table',1),(83,'2025_01_24_090926_add_index_to_chats_table',1),(84,'2025_01_24_091012_add_index_to_chat_tickets_table',1),(85,'2025_01_24_091043_add_index_to_contacts_first_name',1),(86,'2025_01_24_091115_add_fulltext_index_to_contacts_table',1),(87,'2025_01_29_071445_modify_status_column_in_chats_table',1),(88,'2025_02_21_084110_create_job_batches_table',1),(89,'2025_02_21_093829_add_queue_indexes',1),(90,'2025_04_02_085132_create_contact_contact_group_table',1),(91,'2025_05_01_045837_create_campaign_log_retries_table',1),(92,'2025_05_01_053318_add_retry_count_to_campaign_logs_table',1),(93,'2025_05_23_101200_add_rtl_to_languages_table',1),(94,'2025_09_18_102755_optimize_database_indexes_for_performance',2),(95,'2025_09_18_110851_create_audit_logs_table',2),(96,'2025_09_18_112313_create_missing_security_tables',2),(97,'2025_09_18_115536_fix_security_tables_schema',2),(98,'2025_09_29_163230_create_workspaces_table',2),(99,'2025_09_29_163249_add_workspace_id_to_tables',2),(100,'2025_09_29_163357_migrate_workspaces_to_workspaces_data',2),(101,'2025_09_29_163521_add_workspace_foreign_key_constraints',2),(102,'2025_09_30_113358_remove_workspace_id_from_teams_table',3),(103,'2025_09_30_115254_remove_all_workspace_id_columns',4),(104,'2025_10_13_000000_create_whatsapp_sessions_table',5),(105,'2025_10_13_000001_migrate_existing_whatsapp_credentials',5),(106,'2025_10_13_000002_add_session_foreign_keys',5),(107,'2025_10_22_000001_add_chat_provider_and_groups',5),(108,'2025_10_22_000002_add_chat_indexes',5),(109,'2025_10_23_042933_add_source_session_id_to_contacts_table',5);
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
) ENGINE=InnoDB AUTO_INCREMENT=181 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_incidents`
--

LOCK TABLES `security_incidents` WRITE;
/*!40000 ALTER TABLE `security_incidents` DISABLE KEYS */;
INSERT INTO `security_incidents` VALUES (1,'req_69115aafac40b_6026','server_error','high','127.0.0.1',NULL,NULL,'login.submit','{\"user_id\": null, \"audit_id\": \"req_69115aafac40b_6026\", \"endpoint\": \"login.submit\", \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": null, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-09 20:23:28','2025-11-09 20:23:28'),(2,'req_69115d1b9de3b_6550','server_error','high','127.0.0.1',2,1,'whatsapp.sessions.store','{\"user_id\": 2, \"audit_id\": \"req_69115d1b9de3b_6550\", \"endpoint\": \"whatsapp.sessions.store\", \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": 1, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-09 20:33:47','2025-11-09 20:33:47'),(3,'req_69115f3b247fd_2859','forbidden_access','medium','127.0.0.1',2,1,'whatsapp.sessions.store','{\"user_id\": 2, \"audit_id\": \"req_69115f3b247fd_2859\", \"endpoint\": \"whatsapp.sessions.store\", \"severity\": \"medium\", \"ip_address\": \"127.0.0.1\", \"status_code\": 403, \"workspace_id\": 1, \"incident_type\": \"forbidden_access\"}',0,NULL,NULL,'2025-11-09 20:42:51','2025-11-09 20:42:51'),(4,'req_69115f60212cd_7142','forbidden_access','medium','127.0.0.1',2,1,'whatsapp.sessions.store','{\"user_id\": 2, \"audit_id\": \"req_69115f60212cd_7142\", \"endpoint\": \"whatsapp.sessions.store\", \"severity\": \"medium\", \"ip_address\": \"127.0.0.1\", \"status_code\": 403, \"workspace_id\": 1, \"incident_type\": \"forbidden_access\"}',0,NULL,NULL,'2025-11-09 20:43:28','2025-11-09 20:43:28'),(5,'req_691168591baa5_1345','forbidden_access','medium','127.0.0.1',2,1,'whatsapp.sessions.store','{\"user_id\": 2, \"audit_id\": \"req_691168591baa5_1345\", \"endpoint\": \"whatsapp.sessions.store\", \"severity\": \"medium\", \"ip_address\": \"127.0.0.1\", \"status_code\": 403, \"workspace_id\": 1, \"incident_type\": \"forbidden_access\"}',0,NULL,NULL,'2025-11-09 21:21:45','2025-11-09 21:21:45'),(6,'req_69116e686cb94_2151','forbidden_access','medium','127.0.0.1',2,1,'whatsapp.sessions.store','{\"user_id\": 2, \"audit_id\": \"req_69116e686cb94_2151\", \"endpoint\": \"whatsapp.sessions.store\", \"severity\": \"medium\", \"ip_address\": \"127.0.0.1\", \"status_code\": 403, \"workspace_id\": 1, \"incident_type\": \"forbidden_access\"}',0,NULL,NULL,'2025-11-09 21:47:36','2025-11-09 21:47:36'),(7,'req_69128230b0263_2750','server_error','high','127.0.0.1',2,1,'whatsapp.sessions.set-primary','{\"user_id\": 2, \"audit_id\": \"req_69128230b0263_2750\", \"endpoint\": \"whatsapp.sessions.set-primary\", \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": 1, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-10 17:24:16','2025-11-10 17:24:16'),(8,'req_6912823138557_7512','server_error','high','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6912823138557_7512\", \"endpoint\": null, \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": null, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-10 17:24:17','2025-11-10 17:24:17'),(9,'req_6912823283677_3467','server_error','high','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6912823283677_3467\", \"endpoint\": null, \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": null, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-10 17:24:18','2025-11-10 17:24:18'),(10,'req_69128234ccee1_3816','server_error','high','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69128234ccee1_3816\", \"endpoint\": null, \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": null, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-10 17:24:20','2025-11-10 17:24:20'),(11,'req_691674fe10c86_3028','server_error','high','127.0.0.1',2,1,'whatsapp.sessions.set-primary','{\"user_id\": 2, \"audit_id\": \"req_691674fe10c86_3028\", \"endpoint\": \"whatsapp.sessions.set-primary\", \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": 1, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-13 17:17:02','2025-11-13 17:17:02'),(12,'req_6916752712123_8236','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752712123_8236\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:43','2025-11-13 17:17:43'),(13,'req_691675275fd05_3070','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675275fd05_3070\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:43','2025-11-13 17:17:43'),(14,'req_69167527af678_4037','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167527af678_4037\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:43','2025-11-13 17:17:43'),(15,'req_69167527ed1cc_5914','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167527ed1cc_5914\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:43','2025-11-13 17:17:43'),(16,'req_6916752846ae6_3017','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752846ae6_3017\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:44','2025-11-13 17:17:44'),(17,'req_6916752885e12_2989','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752885e12_2989\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:44','2025-11-13 17:17:44'),(18,'req_69167528dcf31_4124','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167528dcf31_4124\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:44','2025-11-13 17:17:44'),(19,'req_691675292d8c3_2301','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675292d8c3_2301\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:45','2025-11-13 17:17:45'),(20,'req_691675296c6f8_5884','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675296c6f8_5884\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:45','2025-11-13 17:17:45'),(21,'req_69167529c3a6f_9052','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167529c3a6f_9052\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:45','2025-11-13 17:17:45'),(22,'req_6916752a2f6c1_9656','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752a2f6c1_9656\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:46','2025-11-13 17:17:46'),(23,'req_6916752a7e4fb_7857','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752a7e4fb_7857\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:46','2025-11-13 17:17:46'),(24,'req_6916752ad0fbc_7043','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752ad0fbc_7043\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:46','2025-11-13 17:17:46'),(25,'req_6916752b37db7_2594','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752b37db7_2594\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:47','2025-11-13 17:17:47'),(26,'req_6916752b9356f_7204','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752b9356f_7204\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:47','2025-11-13 17:17:47'),(27,'req_6916752c194ef_4863','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752c194ef_4863\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:48','2025-11-13 17:17:48'),(28,'req_6916752c67b2f_1690','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752c67b2f_1690\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:48','2025-11-13 17:17:48'),(29,'req_6916752cd3672_4113','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752cd3672_4113\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:48','2025-11-13 17:17:48'),(30,'req_6916752d26b3d_5964','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752d26b3d_5964\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:49','2025-11-13 17:17:49'),(31,'req_6916752d747b0_5533','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752d747b0_5533\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:49','2025-11-13 17:17:49'),(32,'req_6916752dcef0b_7223','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752dcef0b_7223\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:49','2025-11-13 17:17:49'),(33,'req_6916752e24bf8_9723','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752e24bf8_9723\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:50','2025-11-13 17:17:50'),(34,'req_6916752e77436_1606','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752e77436_1606\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:50','2025-11-13 17:17:50'),(35,'req_6916752ece912_5688','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752ece912_5688\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:50','2025-11-13 17:17:50'),(36,'req_6916752f1fe53_8994','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752f1fe53_8994\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:51','2025-11-13 17:17:51'),(37,'req_6916752f6a4b5_1275','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752f6a4b5_1275\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:51','2025-11-13 17:17:51'),(38,'req_6916752fc9b90_5181','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916752fc9b90_5181\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:51','2025-11-13 17:17:51'),(39,'req_69167530254a4_6011','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167530254a4_6011\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:52','2025-11-13 17:17:52'),(40,'req_691675306bf89_9102','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675306bf89_9102\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:52','2025-11-13 17:17:52'),(41,'req_69167530af0eb_6416','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167530af0eb_6416\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:52','2025-11-13 17:17:52'),(42,'req_69167530f3c65_2117','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167530f3c65_2117\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:53','2025-11-13 17:17:53'),(43,'req_6916753151b35_1881','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753151b35_1881\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:53','2025-11-13 17:17:53'),(44,'req_691675319c9fa_4739','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675319c9fa_4739\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:53','2025-11-13 17:17:53'),(45,'req_6916753205c49_3875','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753205c49_3875\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:54','2025-11-13 17:17:54'),(46,'req_6916753268a66_1323','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753268a66_1323\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:54','2025-11-13 17:17:54'),(47,'req_69167532c77fd_3177','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167532c77fd_3177\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:54','2025-11-13 17:17:54'),(48,'req_6916753316c1e_7042','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753316c1e_7042\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:55','2025-11-13 17:17:55'),(49,'req_6916753352314_1215','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753352314_1215\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:55','2025-11-13 17:17:55'),(50,'req_69167533a5327_5631','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167533a5327_5631\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:55','2025-11-13 17:17:55'),(51,'req_69167533f0ea6_6798','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167533f0ea6_6798\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:56','2025-11-13 17:17:56'),(52,'req_6916753446e47_5008','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753446e47_5008\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:56','2025-11-13 17:17:56'),(53,'req_6916753495dca_3972','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753495dca_3972\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:56','2025-11-13 17:17:56'),(54,'req_69167534db182_2469','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167534db182_2469\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:56','2025-11-13 17:17:56'),(55,'req_69167535354b2_7313','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167535354b2_7313\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:57','2025-11-13 17:17:57'),(56,'req_69167535a1533_8302','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167535a1533_8302\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:57','2025-11-13 17:17:57'),(57,'req_691675360df40_5214','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675360df40_5214\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:58','2025-11-13 17:17:58'),(58,'req_6916753659b73_6587','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753659b73_6587\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:58','2025-11-13 17:17:58'),(59,'req_69167536afee7_4368','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167536afee7_4368\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:58','2025-11-13 17:17:58'),(60,'req_691675370dfbf_9387','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675370dfbf_9387\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:59','2025-11-13 17:17:59'),(61,'req_691675374f20a_4713','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675374f20a_4713\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:59','2025-11-13 17:17:59'),(62,'req_6916753793529_1455','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753793529_1455\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:59','2025-11-13 17:17:59'),(63,'req_69167537dc171_9652','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167537dc171_9652\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:17:59','2025-11-13 17:17:59'),(64,'req_69167538284c0_8977','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167538284c0_8977\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:00','2025-11-13 17:18:00'),(65,'req_691675387003a_6017','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675387003a_6017\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:00','2025-11-13 17:18:00'),(66,'req_69167538b99eb_6702','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167538b99eb_6702\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:00','2025-11-13 17:18:00'),(67,'req_6916753909159_6691','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753909159_6691\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:01','2025-11-13 17:18:01'),(68,'req_6916753949896_6789','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753949896_6789\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:01','2025-11-13 17:18:01'),(69,'req_691675398ffbe_9251','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675398ffbe_9251\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:01','2025-11-13 17:18:01'),(70,'req_69167539d11e5_5272','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167539d11e5_5272\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:01','2025-11-13 17:18:01'),(71,'req_6916753a27d36_6598','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753a27d36_6598\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:02','2025-11-13 17:18:02'),(72,'req_6916753a6957d_4718','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753a6957d_4718\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:02','2025-11-13 17:18:02'),(73,'req_6916753aa93ba_9368','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753aa93ba_9368\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:02','2025-11-13 17:18:02'),(74,'req_6916753aef14c_2891','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753aef14c_2891\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:03','2025-11-13 17:18:03'),(75,'req_6916753b478ec_8000','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753b478ec_8000\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:03','2025-11-13 17:18:03'),(76,'req_6916753ba7443_3427','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753ba7443_3427\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:03','2025-11-13 17:18:03'),(77,'req_6916753c05dfe_9761','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753c05dfe_9761\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:04','2025-11-13 17:18:04'),(78,'req_6916753c59165_5230','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753c59165_5230\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:04','2025-11-13 17:18:04'),(79,'req_6916753cb3e84_9116','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753cb3e84_9116\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:04','2025-11-13 17:18:04'),(80,'req_6916753d25d3f_4299','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753d25d3f_4299\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:05','2025-11-13 17:18:05'),(81,'req_6916753d82d86_7012','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753d82d86_7012\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:05','2025-11-13 17:18:05'),(82,'req_6916753de0c9a_8761','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753de0c9a_8761\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:05','2025-11-13 17:18:05'),(83,'req_6916753e4b1b0_5660','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753e4b1b0_5660\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:06','2025-11-13 17:18:06'),(84,'req_6916753ea6f14_8309','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753ea6f14_8309\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:06','2025-11-13 17:18:06'),(85,'req_6916753f08503_6307','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753f08503_6307\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:07','2025-11-13 17:18:07'),(86,'req_6916753f68fb2_7038','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753f68fb2_7038\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:07','2025-11-13 17:18:07'),(87,'req_6916753fba58c_7757','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916753fba58c_7757\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:07','2025-11-13 17:18:07'),(88,'req_6916754014799_7432','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754014799_7432\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:08','2025-11-13 17:18:08'),(89,'req_69167540617d6_6891','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167540617d6_6891\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:08','2025-11-13 17:18:08'),(90,'req_69167540a909e_1768','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167540a909e_1768\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:08','2025-11-13 17:18:08'),(91,'req_69167541101c8_5343','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167541101c8_5343\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:09','2025-11-13 17:18:09'),(92,'req_691675415f21e_2530','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675415f21e_2530\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:09','2025-11-13 17:18:09'),(93,'req_69167541adada_6294','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167541adada_6294\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:09','2025-11-13 17:18:09'),(94,'req_69167541f3388_3587','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167541f3388_3587\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:10','2025-11-13 17:18:10'),(95,'req_6916754268664_5035','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754268664_5035\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:10','2025-11-13 17:18:10'),(96,'req_69167542bddd2_2898','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167542bddd2_2898\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:10','2025-11-13 17:18:10'),(97,'req_691675431d0a1_6551','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675431d0a1_6551\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:11','2025-11-13 17:18:11'),(98,'req_691675435feef_2847','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675435feef_2847\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:11','2025-11-13 17:18:11'),(99,'req_6916754397855_6023','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754397855_6023\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:11','2025-11-13 17:18:11'),(100,'req_6916754408714_3795','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754408714_3795\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:12','2025-11-13 17:18:12'),(101,'req_691675446cd6d_2783','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675446cd6d_2783\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:12','2025-11-13 17:18:12'),(102,'req_69167544b5ed6_6811','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167544b5ed6_6811\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:12','2025-11-13 17:18:12'),(103,'req_69167545141c5_8432','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167545141c5_8432\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:13','2025-11-13 17:18:13'),(104,'req_691675459256e_9437','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675459256e_9437\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:13','2025-11-13 17:18:13'),(105,'req_6916754602096_6779','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754602096_6779\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:14','2025-11-13 17:18:14'),(106,'req_691675465f8b5_1431','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675465f8b5_1431\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:14','2025-11-13 17:18:14'),(107,'req_69167546bd635_6057','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167546bd635_6057\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:14','2025-11-13 17:18:14'),(108,'req_6916754734c4d_8094','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754734c4d_8094\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:15','2025-11-13 17:18:15'),(109,'req_691675478bc1f_9457','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675478bc1f_9457\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:15','2025-11-13 17:18:15'),(110,'req_691675480807a_4676','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675480807a_4676\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:16','2025-11-13 17:18:16'),(111,'req_69167548665f9_5001','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167548665f9_5001\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:16','2025-11-13 17:18:16'),(112,'req_69167548ba5cb_5816','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167548ba5cb_5816\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:16','2025-11-13 17:18:16'),(113,'req_691675493b52b_8013','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675493b52b_8013\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:17','2025-11-13 17:18:17'),(114,'req_691675498fddb_1724','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675498fddb_1724\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:17','2025-11-13 17:18:17'),(115,'req_6916754a15cdd_1252','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754a15cdd_1252\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:18','2025-11-13 17:18:18'),(116,'req_6916754a8203e_3021','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754a8203e_3021\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:18','2025-11-13 17:18:18'),(117,'req_6916754af2030_5507','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754af2030_5507\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:19','2025-11-13 17:18:19'),(118,'req_6916754b620f0_2513','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754b620f0_2513\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:19','2025-11-13 17:18:19'),(119,'req_6916754bd4cc2_4747','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754bd4cc2_4747\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:19','2025-11-13 17:18:19'),(120,'req_6916754c44ac5_9686','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754c44ac5_9686\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:20','2025-11-13 17:18:20'),(121,'req_6916754ca489b_3001','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754ca489b_3001\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:20','2025-11-13 17:18:20'),(122,'req_6916754d482da_2017','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754d482da_2017\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:21','2025-11-13 17:18:21'),(123,'req_6916754db4bc7_8697','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754db4bc7_8697\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:21','2025-11-13 17:18:21'),(124,'req_6916754e45109_8370','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754e45109_8370\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:22','2025-11-13 17:18:22'),(125,'req_6916754eab698_2626','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754eab698_2626\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:22','2025-11-13 17:18:22'),(126,'req_6916754f166b3_6954','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754f166b3_6954\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:23','2025-11-13 17:18:23'),(127,'req_6916754f7f69d_7839','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754f7f69d_7839\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:23','2025-11-13 17:18:23'),(128,'req_6916754fc9cd4_8812','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916754fc9cd4_8812\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:23','2025-11-13 17:18:23'),(129,'req_691675503934c_5960','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675503934c_5960\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:24','2025-11-13 17:18:24'),(130,'req_691675509bca0_6673','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675509bca0_6673\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:24','2025-11-13 17:18:24'),(131,'req_6916755100d50_9644','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755100d50_9644\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:25','2025-11-13 17:18:25'),(132,'req_69167551596b6_3180','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167551596b6_3180\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:25','2025-11-13 17:18:25'),(133,'req_69167551ab325_5542','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167551ab325_5542\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:25','2025-11-13 17:18:25'),(134,'req_691675522968a_8012','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675522968a_8012\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:26','2025-11-13 17:18:26'),(135,'req_6916755289528_7922','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755289528_7922\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:26','2025-11-13 17:18:26'),(136,'req_69167552e1ce9_1039','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167552e1ce9_1039\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:26','2025-11-13 17:18:26'),(137,'req_6916755342926_8852','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755342926_8852\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:27','2025-11-13 17:18:27'),(138,'req_6916755394c4e_8342','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755394c4e_8342\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:27','2025-11-13 17:18:27'),(139,'req_69167553e14e9_3054','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167553e14e9_3054\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:27','2025-11-13 17:18:27'),(140,'req_691675544032b_9427','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675544032b_9427\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:28','2025-11-13 17:18:28'),(141,'req_69167554bb987_9998','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167554bb987_9998\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:28','2025-11-13 17:18:28'),(142,'req_691675551fece_3917','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675551fece_3917\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:29','2025-11-13 17:18:29'),(143,'req_69167555724e7_6334','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167555724e7_6334\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:29','2025-11-13 17:18:29'),(144,'req_69167555d43e6_5804','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167555d43e6_5804\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:29','2025-11-13 17:18:29'),(145,'req_6916755650b34_2443','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755650b34_2443\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:30','2025-11-13 17:18:30'),(146,'req_69167556a3434_8238','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167556a3434_8238\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:30','2025-11-13 17:18:30'),(147,'req_6916755720679_3438','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755720679_3438\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:31','2025-11-13 17:18:31'),(148,'req_6916755772f11_5771','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755772f11_5771\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:31','2025-11-13 17:18:31'),(149,'req_69167557c0f10_6086','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167557c0f10_6086\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:31','2025-11-13 17:18:31'),(150,'req_6916755821e9f_9187','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755821e9f_9187\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:32','2025-11-13 17:18:32'),(151,'req_691675587fcbc_2316','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675587fcbc_2316\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:32','2025-11-13 17:18:32'),(152,'req_69167558dec3b_5487','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167558dec3b_5487\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:32','2025-11-13 17:18:32'),(153,'req_691675593a7c3_8887','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675593a7c3_8887\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:33','2025-11-13 17:18:33'),(154,'req_6916755993148_5754','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755993148_5754\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:33','2025-11-13 17:18:33'),(155,'req_6916755a0224e_3501','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755a0224e_3501\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:34','2025-11-13 17:18:34'),(156,'req_6916755a69821_7410','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755a69821_7410\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:34','2025-11-13 17:18:34'),(157,'req_6916755ab3954_7271','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755ab3954_7271\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:34','2025-11-13 17:18:34'),(158,'req_6916755b0dbb5_6701','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755b0dbb5_6701\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:35','2025-11-13 17:18:35'),(159,'req_6916755b6fbc7_6375','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755b6fbc7_6375\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:35','2025-11-13 17:18:35'),(160,'req_6916755bd07ce_6965','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755bd07ce_6965\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:35','2025-11-13 17:18:35'),(161,'req_6916755c4d74e_6569','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755c4d74e_6569\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:36','2025-11-13 17:18:36'),(162,'req_6916755cabe61_9445','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755cabe61_9445\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:36','2025-11-13 17:18:36'),(163,'req_6916755d13c90_8922','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755d13c90_8922\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:37','2025-11-13 17:18:37'),(164,'req_6916755d76b66_9275','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755d76b66_9275\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:37','2025-11-13 17:18:37'),(165,'req_6916755dddd56_3934','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755dddd56_3934\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:37','2025-11-13 17:18:37'),(166,'req_6916755e3e77b_2356','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755e3e77b_2356\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:38','2025-11-13 17:18:38'),(167,'req_6916755e9c91c_7354','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755e9c91c_7354\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:38','2025-11-13 17:18:38'),(168,'req_6916755eef6a4_5465','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755eef6a4_5465\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:39','2025-11-13 17:18:39'),(169,'req_6916755f5b070_4578','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755f5b070_4578\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:39','2025-11-13 17:18:39'),(170,'req_6916755fc9e85_1640','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916755fc9e85_1640\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:39','2025-11-13 17:18:39'),(171,'req_6916756069812_5041','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_6916756069812_5041\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:40','2025-11-13 17:18:40'),(172,'req_69167560ea3c6_1475','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167560ea3c6_1475\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:40','2025-11-13 17:18:40'),(173,'req_691675614cee8_4662','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675614cee8_4662\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:41','2025-11-13 17:18:41'),(174,'req_69167561b74f0_5743','rate_limit_exceeded','low','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_69167561b74f0_5743\", \"endpoint\": null, \"severity\": \"low\", \"ip_address\": \"127.0.0.1\", \"status_code\": 429, \"workspace_id\": null, \"incident_type\": \"rate_limit_exceeded\"}',0,NULL,NULL,'2025-11-13 17:18:41','2025-11-13 17:18:41'),(175,'req_691675e98896c_9241','server_error','high','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675e98896c_9241\", \"endpoint\": null, \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": null, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-13 17:20:57','2025-11-13 17:20:57'),(176,'req_691675e9cfb76_7531','server_error','high','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675e9cfb76_7531\", \"endpoint\": null, \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": null, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-13 17:20:57','2025-11-13 17:20:57'),(177,'req_691675eacd8f5_3470','server_error','high','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675eacd8f5_3470\", \"endpoint\": null, \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": null, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-13 17:20:58','2025-11-13 17:20:58'),(178,'req_691675eb30fa2_9741','server_error','high','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675eb30fa2_9741\", \"endpoint\": null, \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": null, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-13 17:20:59','2025-11-13 17:20:59'),(179,'req_691675ed18675_3019','server_error','high','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675ed18675_3019\", \"endpoint\": null, \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": null, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-13 17:21:01','2025-11-13 17:21:01'),(180,'req_691675ed724c9_3510','server_error','high','127.0.0.1',NULL,NULL,NULL,'{\"user_id\": null, \"audit_id\": \"req_691675ed724c9_3510\", \"endpoint\": null, \"severity\": \"high\", \"ip_address\": \"127.0.0.1\", \"status_code\": 500, \"workspace_id\": null, \"incident_type\": \"server_error\"}',0,NULL,NULL,'2025-11-13 17:21:01','2025-11-13 17:21:01');
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
INSERT INTO `settings` VALUES ('address',NULL),('allow_facebook_login','0'),('allow_google_login','0'),('app_environment','local'),('app_name','Blazz'),('available_version',NULL),('aws_access_key',NULL),('aws_bucket',NULL),('aws_default_region',NULL),('aws_secret_key',NULL),('billing_address',NULL),('billing_city',NULL),('billing_country',NULL),('billing_name',NULL),('billing_phone_1',NULL),('billing_phone_2',NULL),('billing_postal_code',NULL),('billing_state',NULL),('billing_tax_id',NULL),('broadcast_driver','pusher'),('company_name','Blazz'),('currency','USD'),('date_format','d-M-y'),('default_image_api',NULL),('display_frontend','1'),('email',NULL),('enable_ai_billing','0'),('facebook_login',NULL),('favicon',NULL),('google_analytics_status','0'),('google_analytics_tracking_id',NULL),('google_login',NULL),('google_maps_api_key',NULL),('invoice_prefix',NULL),('is_tax_inclusive','1'),('is_update_available','0'),('last_update_check','2025-11-10 03:22:57'),('logo',NULL),('mail_config',NULL),('phone',NULL),('pusher_app_cluster',NULL),('pusher_app_id',NULL),('pusher_app_key',NULL),('pusher_app_secret',NULL),('recaptcha_active','0'),('recaptcha_secret_key',NULL),('recaptcha_site_key',NULL),('release_date',NULL),('smtp_email_active','0'),('socials',NULL),('storage_system','local'),('time_format','H:i'),('timezone','UTC'),('title','Blazz - WhatsApp Business Solution'),('trial_period','20'),('verify_email','0'),('version',NULL),('whatsapp_callback_token','20251110032257BBrQ');
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
INSERT INTO `subscription_plans` VALUES (1,'fab8878d-d6ac-481a-ab51-6c028e584672','Free Trial',0.00,'monthly','{\"features\":[\"Basic Features\",\"Limited Messages\",\"1 WhatsApp Session\"],\"limits\":{\"messages_per_month\":1000,\"contacts\":500,\"campaigns\":10}}','active','2025-11-09 20:27:51','2025-11-09 20:27:51',NULL);
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
INSERT INTO `subscriptions` VALUES (1,'e6510aa1-bb30-47ae-96f6-ddedc4e0ce2e',1,1,NULL,'2025-11-09 20:27:51','2025-12-10 03:27:51','trial','2025-11-09 20:27:51','2025-11-09 20:27:51');
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
INSERT INTO `teams` VALUES (1,'b0fb3a41-f6a6-43f9-a9e1-ded267ba2479',1,2,'owner','active',2,NULL,NULL,'2025-11-09 20:27:51','2025-11-09 20:27:51');
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
INSERT INTO `users` VALUES (1,'Admin','Demo','admin@demo.com',NULL,NULL,'admin','+6281234567890','Jakarta, Indonesia','2025-11-09 20:27:51','$2y$10$Qm9a8kQbDH1GXpnNaKPEz.h26DttP0Hmv4dX63KJcYKtZ/ga6mux6',NULL,0,1,NULL,NULL,NULL,NULL,NULL,'2025-11-09 20:27:51','2025-11-09 20:27:51',NULL),(2,'Laksmana','Moerdani','ltmoerdani@yahoo.com',NULL,NULL,'user','+6281234567891','Indonesia','2025-11-09 20:27:51','$2y$10$pJfx.zVXK6Tz9o/Nojdu1Oarp9Rmo0zNNHC.4MN.UuyZmBwBoEOY6',NULL,0,1,NULL,NULL,NULL,NULL,NULL,'2025-11-09 20:27:51','2025-11-09 20:27:51',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_groups`
--

DROP TABLE IF EXISTS `whatsapp_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned NOT NULL,
  `whatsapp_session_id` bigint unsigned NOT NULL,
  `group_jid` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'WhatsApp group identifier (e.g., 1234567890-1234567890@g.us)',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `owner_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Group creator phone number',
  `participants` json NOT NULL COMMENT '[{phone, name, isAdmin, joinedAt}]',
  `invite_code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` json DEFAULT NULL COMMENT '{messagesAdminsOnly, editInfoAdminsOnly}',
  `group_created_at` timestamp NULL DEFAULT NULL COMMENT 'When group was created on WhatsApp',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_groups_uuid_unique` (`uuid`),
  UNIQUE KEY `whatsapp_groups_group_jid_unique` (`group_jid`),
  KEY `idx_groups_workspace` (`workspace_id`),
  KEY `idx_groups_session` (`whatsapp_session_id`),
  KEY `idx_groups_workspace_session` (`workspace_id`,`whatsapp_session_id`),
  CONSTRAINT `whatsapp_groups_whatsapp_session_id_foreign` FOREIGN KEY (`whatsapp_session_id`) REFERENCES `whatsapp_sessions` (`id`) ON DELETE CASCADE,
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
-- Table structure for table `whatsapp_sessions`
--

DROP TABLE IF EXISTS `whatsapp_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workspace_id` bigint unsigned NOT NULL,
  `session_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_type` enum('meta','webjs') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'webjs',
  `status` enum('qr_scanning','authenticated','connected','disconnected','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'qr_scanning',
  `qr_code` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `session_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_sessions`
--

LOCK TABLES `whatsapp_sessions` WRITE;
/*!40000 ALTER TABLE `whatsapp_sessions` DISABLE KEYS */;
INSERT INTO `whatsapp_sessions` VALUES (1,'085d3c75-c0d7-4fdb-b8fa-9fb23ddcb7e8',1,'webjs_1_1762745627_lPZ65ObO',NULL,'webjs','qr_scanning',NULL,NULL,0,1,NULL,NULL,'{\"created_at\": \"2025-11-10T03:33:47.723606Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-09 20:33:47','2025-11-09 20:33:47',NULL),(2,'06e7fc06-e6ec-4fe5-b901-089face92764',1,'webjs_1_1762746055_2wNCPcFI',NULL,'webjs','qr_scanning',NULL,NULL,0,1,NULL,NULL,'{\"created_via\": \"test\", \"creation_timestamp\": \"2025-11-10T03:40:55.904574Z\"}',1,'2025-11-09 20:40:55','2025-11-09 20:40:55',NULL),(3,'e8c47535-8ef8-4b00-9d1b-6af575998c6b',1,'webjs_1_1762750432_gPP6NK0e',NULL,'webjs','qr_scanning',NULL,NULL,0,1,NULL,NULL,'{\"created_at\": \"2025-11-10T04:53:52.972212Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-09 21:53:52','2025-11-09 21:53:52',NULL),(4,'4b9647c4-9287-4944-895f-00afeede7543',1,'webjs_1_1762750568_hMuzZJMD',NULL,'webjs','qr_scanning',NULL,NULL,0,1,NULL,NULL,'{\"created_at\": \"2025-11-10T04:56:08.235984Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-09 21:56:08','2025-11-09 21:56:08',NULL),(5,'7134507a-7db8-4355-a4c9-ad20871f653f',1,'webjs_1_1762750720_KKEv5Com',NULL,'webjs','qr_scanning',NULL,NULL,0,1,NULL,NULL,'{\"created_at\": \"2025-11-10T04:58:40.487480Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-09 21:58:40','2025-11-09 21:58:40',NULL),(6,'b9f82104-bbc3-4052-a948-214a2c2076ba',1,'webjs_1_1762751599_4ULlb53R',NULL,'webjs','qr_scanning',NULL,NULL,0,1,NULL,NULL,'{\"created_at\": \"2025-11-10T05:13:19.533804Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-09 22:13:19','2025-11-09 22:13:19',NULL),(7,'94794c8b-9f43-44c9-8735-89357488d137',1,'webjs_test_1762752006',NULL,'webjs','qr_scanning','data:image/png;base64,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',NULL,0,1,'2025-11-09 22:20:13',NULL,NULL,2,'2025-11-09 22:20:06','2025-11-09 22:20:13',NULL),(8,'3b505885-112c-4466-9f1b-d5c1fe9bb4aa',1,'webjs_1_1762752533_LkOr1QP6',NULL,'webjs','qr_scanning',NULL,NULL,0,1,NULL,NULL,'{\"created_at\": \"2025-11-10T05:28:53.833367Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-09 22:28:53','2025-11-09 22:28:53',NULL),(9,'4906837b-019c-4ac3-aa66-69cddbd53e1a',1,'webjs_1_1762753368_WhUgREnd',NULL,'webjs','qr_scanning',NULL,NULL,0,1,NULL,NULL,'{\"created_at\": \"2025-11-10T05:42:48.974887Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-09 22:42:48','2025-11-09 22:42:48',NULL),(10,'424cdd2e-df5c-40af-8ec3-ac8ca03bd627',1,'webjs_1_1762756318_PriidUcw',NULL,'webjs','qr_scanning',NULL,NULL,0,1,NULL,NULL,'{\"created_at\": \"2025-11-10T06:31:58.085857Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-09 23:31:58','2025-11-09 23:31:58',NULL),(11,'e53353d7-c235-447c-bda2-0f22649d4f25',1,'webjs_1_1762756357_RWDWEOFx',NULL,'webjs','qr_scanning','data:image/png;base64,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',NULL,0,1,'2025-11-09 23:47:38',NULL,'{\"created_at\": \"2025-11-10T06:32:37.966044Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-09 23:32:37','2025-11-09 23:47:38',NULL),(12,'7b07665d-e936-4245-87be-cdef3d1069f0',1,'webjs_1_1762756844_Q4VaUDvR',NULL,'webjs','qr_scanning','data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAQAAAAEACAYAAABccqhmAAAAAklEQVR4AewaftIAABJkSURBVO3BQW4c2ZIAQfcE739lH20IxIZPTFZR/QcZZvYHa61HulhrPdbFWuuxLtZaj3Wx1nqsi7XWY12stR7rYq31WB/8hcq/VDGp3FExqfxUxaRyUnGiclJxonJScaIyVXxFZaqYVO6omFSmijtUpopJZaqYVD5VTConFZPKScWk8i9VfOVirfVYF2utx7pYaz3WBzdVvJPKKyomlTsqvqJyh8pJxaQyqdxRcaJyovJdKicVk8odKlPFv6IyVfxLFe+k8l0Xa63HulhrPdbFWuuxPniRyh0V/19VTCpTxaQyqUwVd6hMKlPFVDGpfKXiDpU7VE4qJpWTildUfJfKv6RyR8VPXay1HutirfVYF2utx/rgf1zFpDKp3FExqXyXylQxqZxU3KFyh8pUMVV8l8odKq9QuUPlFSrvUjGp/H9xsdZ6rIu11mNdrLUe64P/cSpTxYnKVDGpTBVfUTlROamYVE4qTiruUPktFZPKScWkMlVMKlPFpHJSMalMFZ9UpopJ5URlqvj/4mKt9VgXa63HulhrPdYHL6r4TRWTyh0qU8Wk8qliqphUpop3UjlRmSruqPgulVdUTConKicqJxWTyr9S8U4V/8rFWuuxLtZaj3Wx1nqsD25S+ZdUpopJZaqYVH5KZaqYVKaKSWWqmFSmikllqphUpoo7VD5VnFRMKlPFpDJVTCpTxaQyVUwqd1RMKp8qJpWpYlI5UZkqTlT+Kxdrrce6WGs91sVa67E++IuK/1LFKyomlanik8pUMalMFScVk8pUcVIxqUwVr6h4F5Wp4hUV76QyVXyXylTxior/FRdrrce6WGs91sVa67E++AuVqeJE5TdV3KEyVUwq76JyUjGpTBWTylRxonKi8i4Vk8qkclJxonJScVIxqUwqv6ViUpkqTlSmiknljoqvXKy1HutirfVYF2utx7I/uEHljopJZaqYVKaKSeUVFV9ROan4TSpTxYnKv1IxqZxUnKhMFScqJxUnKl+pmFTuqJhU7qg4UZkqJpWp4rsu1lqPdbHWeqyLtdZjffDLVKaKSeUVFScqk8pU8aliUplUpopJZap4J5WTikllqviKyisqTlSmiknlN1V8RWWqmFSmikllqphU7lCZKiaVE5Wp4isXa63HulhrPdbFWuuxPvgLlXdSmSomlXeq+C6VqeI3qUwVr1C5Q+VTxaQyqZyoTBUnKu+kMlX8FpWp4o6KSeVE5aTipy7WWo91sdZ6rIu11mN9cFPFpDJVnKhMKq9QmSpOVKaKTxUnKlPFVDGpTBWvqJhUpop3qbhDZVK5o2JSuaNiUjmp+FQxqdyhclIxqUwVk8pUMam8y8Va67Eu1lqPdbHWeiz7gxtUpooTlaniRGWqmFSmihOV76qYVKaKSeWkYlKZKiaVqWJSmSreRWWqmFSmikllqjhRmSpOVE4qTlSmip9SOamYVN6pYlKZKr7rYq31WBdrrce6WGs91gd/oTJVTConFScq76QyVUwqU8UnlROVqeKOiknlRGWquEPluyruUDlROak4UZkqTlSmiqliUvlKxaTymyomlaniDpWp4isXa63HulhrPdYHf1ExqUwVk8qkMlVMFZPKO6lMFT9VcaJyR8WkMlXcofJTKlPFScWJylTxTipTxR0V31Vxh8pUcUfFpHJHxXddrLUe62Kt9VgXa63H+uCmiknlFSpTxW9S+UrFO1WcqJyoTBWTyknFpPKViknlN6lMFVPFHSonFd+lMlWcqLxC5aRiUpkqfupirfVYF2utx7pYaz3WBzepnFTcUXGi8psqPqlMFZPKScU7VUwqd6hMFZPKVypOVKaKE5Wp4kRlqjipOFH5KZWp4g6Vk4pJ5V+5WGs91sVa67Eu1lqP9cFfqJxUTConFZPKVDFVTCqTyh0Vk8pXVE4q7lD5TRXvojJVTBWTylRxh8pU8QqV76qYVE5UpooTlaliUpkqJpWpYlI5qfjKxVrrsS7WWo91sdZ6rA/+ouJEZaqYVCaVqWJSmSpOKiaVqeK7KiaVqeIOlaliUpkqTiomlUllqvgulaliUpkq7lCZKqaKSWWquKNiUpkq3kVlqpgqJpXfVPFdF2utx7pYaz3WxVrrsT74C5U7VKaKSWVSmSomlTsqJpWp4l1UpoqpYlKZKiaVqWJSmSomlUnlpOKTyknFHSq/qWJSmSqmiq+onFRMKlPFHRWvUHmXi7XWY12stR7rYq31WPYHb6QyVUwqU8UdKlPFpDJVnKh8qniFyisqJpU7KiaVk4pPKlPFpHJSMancUTGp3FFxh8pXKiaVf6niDpWTiq9crLUe62Kt9VgXa63H+uBFKlPFpHKi8ptUpoqvqEwVk8pUcVJxojKp3FFxUjGpfKViUjmpeEXFpHJHxYnKVPGVikllqjhRmSomlZOKSWWqOKmYVL7rYq31WBdrrce6WGs9lv3BC1ROKiaVqeK/pPKp4hUqr6g4UTmpmFSmiq+o3FFxh8pUMalMFb9J5VPFpHJSMan8lyp+6mKt9VgXa63HulhrPdYHN6lMFZPKHSonFScqU8WJynepTBUnFZPKScWJylQxqdyh8pWKSWWqmFReoTJVTCpTxaQyVUwqU8W7qEwVk8pUMancUfFbLtZaj3Wx1nqsi7XWY33wFypTxR0qJxWTyonKKyomla9UTConFVPFicpUcUfFScWJyieVOyomlZOKSeUOlVeoTBWfVKaKSeVEZaqYVF6hclIxqUwVX7lYaz3WxVrrsS7WWo9lf/AClTsqJpWTihOVOyomlU8Vd6jcUXGickfFicpU8V0qJxWTym+qmFROKiaVn6qYVKaKO1SmikllqphU7qj4ysVa67Eu1lqPdbHWeqwP3qzijoo7VE4qJpWfUpkqpopJZao4UZkqJpUTlTtUvlIxVUwqk8pUcYfKScWkclLxW1TuUJkqpopJZap4RcV3Xay1HutirfVYF2utx7I/eCOVf6liUpkqJpWp4pPKVHGiclJxonJSMan8VyruULmj4kTlFRWTyqeKSeWOiknlf0nFVy7WWo91sdZ6rIu11mN9cJPKScWkMlXcofIKlaliUvkulVeoTBUnKu9U8V0qk8pJxVQxqUwVJyp3VPyUyh0Vk8pJxR0qU8WJylTxXRdrrce6WGs91sVa67E++AuVk4pJ5Q6VqeIOlaliUplUpopPKpPKVHGHylTxThWTyh0qnypeofIKlaliUpkqJpWp4qTik8pJxUnFpHKiMlWcqEwVU8WkMlV85WKt9VgXa63H+uAvKiaVSeUVFXdUTCqTyknFpPKp4kRlqphUTlROKqaKOyomlZOK31IxqZxUTCpTxaRyojJVfKViUjlRmSruqHgnlZ+6WGs91sVa67Eu1lqPZX9woDJV3KHyThWvUPmuihOVk4pJZaqYVKaKSWWqmFR+S8WJyknFHSpTxTup/FTFpPKbKiaVqWJSmSq+crHWeqyLtdZjXay1HuuDf6ziRGWqmFSmildUfFI5UZkq7qg4qZhU7qg4UfmuiknlpGJSmVReofK/QmWqmFR+U8VJxXddrLUe62Kt9VgXa63Hsj94gcpvqphUTiomlaniKyrvVPFOKndUTCpTxXep3FExqdxRMam8ouIrKlPFpDJVTCqvqDhRmSpOVKaKr1ystR7rYq31WBdrrcf64EUVk8pJxYnKpDJVTCq/pWJS+U0qU8VUMalMFZPKicpXKqaKO1SmihOVSeU3qXxFZaqYVKaKd1I5UZkqfupirfVYF2utx7pYaz3WBzep3FExqZxUTCqTyonKVDGpTBWfKiaVd1K5Q+WdKiaVTxWTylQxqUwVr6iYVKaKSWWqmFS+q2JSuUNlqphUpopJ5aRiUnmXi7XWY12stR7rYq31WPYH/5DKVDGpnFT8f6HymypOVKaKr6hMFZPKVHGHyknFpPKKikllqvgplZOKE5WpYlKZKn7LxVrrsS7WWo91sdZ6LPuDN1KZKk5UpooTlZOKSWWq+C6VqWJSeUXFK1ROKiaVf6XiROUVFZPKHRWfVE4qTlReUTGpvKLiuy7WWo91sdZ6rIu11mN98BcqU8WkcofKVHGiMlX8KxWTyh0VJypTxaQyVUwVd1RMKt9VcaLyiooTlTsqJpVJ5V0q/ksVk8pU8ZWLtdZjXay1HutirfVYH7yoYlKZVE5UTiomlaliUvkplTsq7lA5UTlRmSomlZOKd1GZKiaVqWKqeIXKicpUMal8qphU7lC5o+IVFZPKT12stR7rYq31WBdrrceyP3gjlaniDpWTiknlpGJSmSo+qdxRMalMFf+SylTxXSqvqJhUTipOVO6omFR+qmJSmSreSWWqOFE5qfiui7XWY12stR7rYq31WB/cpHJSMalMFZPKHSpTxYnKVDGpfKqYVH6TyknFpDJVvEvFHSqTylQxqUwqU8VJxYnKScWk8qnipGJSOak4UXlFxYnKVPGVi7XWY12stR7rYq31WPYHN6hMFZPKVPEKlaniRGWq+C6VqeIVKicVk8pUcaIyVZyofKViUnlFxYnKKyomlZ+qOFGZKk5UpopJZaqYVKaKE5Wp4rsu1lqPdbHWeqyLtdZj2R8cqEwVk8pUcaJyUjGpnFRMKndUfFI5qZhUpopJZaq4Q2WqOFE5qZhUPlWcqLxTxaTyioo7VH6qYlI5qfhNKicVX7lYaz3WxVrrsS7WWo9lf3CgckfFicpUMamcVEwqU8WkMlVMKp8qTlSmihOVd6r4LSpTxYnKScWk8i9VTCpfqZhU7qg4UZkqJpU7Kk5UpoqvXKy1HutirfVYF2utx/rgpop3UpkqJpU7VKaKd6mYVE4qJpWpYlL5TSpfqZhUpoqTiknlpGJSOamYVE5UpopJ5ZPKVHGHyknFpHJSMan8lou11mNdrLUe64O/qJhUpopJ5Y6KSeUVFXdUfEXljopJZaqYVE4q7lCZKqaKSeWTylQxqdxR8YqKk4pJZaqYVL5ScYfKScWkclJxh8pU8VMXa63HulhrPdbFWuux7A8OVF5RMamcVEwqJxWTylTxW1ROKiaVqWJSOamYVKaKSeWnKiaVV1TcoTJV3KEyVXyXyknFpPK/rOIrF2utx7pYaz3WxVrrsT54UcWJylTxTiqvUPlUcaIyVZyonKjcoTJV/FcqJpWp4kTlpGJSOamYKiaVqeIrFScq71QxqZxUTCo/dbHWeqyLtdZjXay1Hsv+4AUqv6niRGWqmFROKj6pTBWvULmjYlKZKiaVOyq+ojJV3KFyR8WkMlW8QmWq+IrKKyruULmjYlK5o+IrF2utx7pYaz3WxVrrsewPDlTuqJhUpop/SWWq+CmVOypOVE4qJpWTihOVr1RMKlPFHSpTxYnKVDGp3FFxovJfqThReUXFd12stR7rYq31WBdrrceyPzhQeUXFicpJxTupTBVfUZkq3knljopJZaqYVN6l4g6VV1TcofJTFZPKScWk8r+s4isXa63HulhrPdbFWuuxPviLit9UcaLyioqp4l1U7qg4qZhU3qniu1QmlVdUvJPKScWk8hWV31Rxh8pUcYfKd12stR7rYq31WBdrrcf64C9U/qWKOyomlUnlpyruqJhUJpWp4qTipOIVKp8q7qiYVKaKO1SmiknlpGJSOan4pDJVTCqTyitUpooTlZOKn7pYaz3WxVrrsS7WWo/1wU0V76TyThUnKt+lclJxUjGp3KEyVZyo3FHxUypTxR0qJypTxYnKT1W8omJSOam4o+K3XKy1HutirfVYF2utx/rgRSp3VPwmlZOK71KZKk5UTiomlZOKd1J5l4pJ5RUVk8qkclJxovIVlZOKV6j8JpWfulhrPdbFWuuxLtZaj/XB/zMVJxWTyonKVyomlanipGJSuUNlqjipmFR+i8pUcaJyR8WJyqRyh8qniknlROWkYlK5o+JEZaqYVL7rYq31WBdrrce6WGs91gf/4yomlanipGJS+UrFpHKickfFO6lMFVPFicqniknlpGJSeYXKVHFSMam8S8WkMlX8JpWp4kTlpy7WWo91sdZ6rIu11mN98KKKf6liUpkqJpWTiu+qOFF5hcpUMalMFZPKVDGpTBWfVE4q3qniROUVFZPKV1SmileoTBWTylRxovJbLtZaj3Wx1nqsi7XWY31wk8q/pDJVnKi8S8WkMlVMFXeoTBWTyonKVDGpTBWTyk+pTBWTyonKVHFSMalMFZPKVDGpfKqYVE5UpoqpYlKZKiaVk4pJZaqYVL7rYq31WBdrrce6WGs9lv3BWuuRLtZaj3Wx1nqsi7XWY12stR7rYq31WBdrrcf6PxzNwO8pg+lfAAAAAElFTkSuQmCC',NULL,0,1,'2025-11-09 23:47:54',NULL,'{\"created_at\": \"2025-11-10T06:40:44.405568Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-09 23:40:44','2025-11-09 23:47:54',NULL),(13,'60f24e1e-79cd-49de-b289-9d0ba625e948',1,'webjs_1_1762759136_T2LLYMmY',NULL,'webjs','qr_scanning','data:image/png;base64,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',NULL,0,1,'2025-11-10 00:19:25',NULL,'{\"created_at\": \"2025-11-10T07:18:56.091839Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-10 00:18:56','2025-11-10 00:19:25',NULL),(14,'a0024f02-253e-4b45-8f5c-0b612d7eb321',1,'webjs_1_1762759190_Ixt2K4cz',NULL,'webjs','qr_scanning','data:image/png;base64,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',NULL,0,1,'2025-11-10 02:22:32',NULL,'{\"created_at\": \"2025-11-10T07:19:50.558310Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-10 00:19:50','2025-11-10 02:22:32',NULL),(15,'133c6580-f0e7-40b5-99a0-bf31a7665762',1,'webjs_1_1762820515_arWzPQdQ',NULL,'webjs','qr_scanning',NULL,NULL,0,1,NULL,NULL,'{\"created_at\": \"2025-11-11T00:21:55.972323Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-10 17:21:55','2025-11-10 17:21:55',NULL),(16,'b5f5a67e-d080-4013-82cc-caf4947928ec',1,'webjs_1_1762820571_o6D3dXlF','62816108641','webjs','qr_scanning','data:image/png;base64,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',NULL,0,1,'2025-11-10 19:17:11','2025-11-10 17:24:28','{\"created_at\": \"2025-11-11T00:22:51.630712Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-10 17:22:51','2025-11-10 19:17:11',NULL),(17,'d9662fd6-96b2-49c1-bab6-bda4e28b9a42',1,'webjs_1_1763079321_JSmiuJVF','62811801641','webjs','connected','data:image/png;base64,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',NULL,0,1,'2025-11-13 17:17:01','2025-11-13 17:17:01','{\"created_at\": \"2025-11-14T00:15:21.473011Z\", \"created_by\": \"ltmoerdani@yahoo.com\", \"provider_type\": \"webjs\"}',2,'2025-11-13 17:15:21','2025-11-13 17:17:01',NULL);
/*!40000 ALTER TABLE `whatsapp_sessions` ENABLE KEYS */;
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
INSERT INTO `workspaces` VALUES (1,'806e33e9-d284-44ee-972b-afad3256e336','laksmana-workspace','Laksmana Workspace','Indonesia','{\"created_via\":\"seeder\",\"environment\":\"testing\"}','Asia/Jakarta',2,'2025-11-09 20:27:51','2025-11-09 20:27:51',NULL);
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

-- Dump completed on 2025-11-14  8:24:32
