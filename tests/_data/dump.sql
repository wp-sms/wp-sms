-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: test
-- ------------------------------------------------------
-- Server version	8.0.30

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
-- Table structure for table `wp_actionscheduler_actions`
--

DROP TABLE IF EXISTS `wp_actionscheduler_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_actionscheduler_actions` (
  `action_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hook` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `scheduled_date_gmt` datetime DEFAULT '0000-00-00 00:00:00',
  `scheduled_date_local` datetime DEFAULT '0000-00-00 00:00:00',
  `args` varchar(191) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `schedule` longtext COLLATE utf8mb4_unicode_520_ci,
  `group_id` bigint unsigned NOT NULL DEFAULT '0',
  `attempts` int NOT NULL DEFAULT '0',
  `last_attempt_gmt` datetime DEFAULT '0000-00-00 00:00:00',
  `last_attempt_local` datetime DEFAULT '0000-00-00 00:00:00',
  `claim_id` bigint unsigned NOT NULL DEFAULT '0',
  `extended_args` varchar(8000) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`action_id`),
  KEY `hook` (`hook`),
  KEY `status` (`status`),
  KEY `scheduled_date_gmt` (`scheduled_date_gmt`),
  KEY `args` (`args`),
  KEY `group_id` (`group_id`),
  KEY `last_attempt_gmt` (`last_attempt_gmt`),
  KEY `claim_id_status_scheduled_date_gmt` (`claim_id`,`status`,`scheduled_date_gmt`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_actionscheduler_actions`
--

LOCK TABLES `wp_actionscheduler_actions` WRITE;
/*!40000 ALTER TABLE `wp_actionscheduler_actions` DISABLE KEYS */;
INSERT INTO `wp_actionscheduler_actions` VALUES (8,'action_scheduler/migration_hook','complete','2023-01-11 11:31:00','2023-01-11 11:31:00','[]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436660;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436660;}',1,1,'2023-01-11 11:31:02','2023-01-11 11:31:02',0,NULL),(9,'woocommerce_cleanup_draft_orders','complete','2023-01-11 11:30:05','2023-01-11 11:30:05','[]','O:32:\"ActionScheduler_IntervalSchedule\":5:{s:22:\"\0*\0scheduled_timestamp\";i:1673436605;s:18:\"\0*\0first_timestamp\";i:1673436605;s:13:\"\0*\0recurrence\";i:86400;s:49:\"\0ActionScheduler_IntervalSchedule\0start_timestamp\";i:1673436605;s:53:\"\0ActionScheduler_IntervalSchedule\0interval_in_seconds\";i:86400;}',0,1,'2023-01-11 11:30:05','2023-01-11 11:30:05',0,NULL),(10,'woocommerce_cleanup_draft_orders','pending','2023-01-12 11:30:05','2023-01-12 11:30:05','[]','O:32:\"ActionScheduler_IntervalSchedule\":5:{s:22:\"\0*\0scheduled_timestamp\";i:1673523005;s:18:\"\0*\0first_timestamp\";i:1673436605;s:13:\"\0*\0recurrence\";i:86400;s:49:\"\0ActionScheduler_IntervalSchedule\0start_timestamp\";i:1673523005;s:53:\"\0ActionScheduler_IntervalSchedule\0interval_in_seconds\";i:86400;}',0,0,'0000-00-00 00:00:00','0000-00-00 00:00:00',0,NULL),(11,'woocommerce_update_marketplace_suggestions','complete','2023-01-11 11:31:23','2023-01-11 11:31:23','[]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436683;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436683;}',0,1,'2023-01-11 11:31:59','2023-01-11 11:31:59',0,NULL),(12,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:03','2023-01-11 11:32:03','[15,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436723;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436723;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(13,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:03','2023-01-11 11:32:03','[16,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436723;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436723;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(14,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[17,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(15,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[18,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(16,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[19,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(17,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[20,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(18,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[21,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(19,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[22,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(20,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[23,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(21,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[24,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(22,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[25,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(23,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[26,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(24,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[27,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(25,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[28,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(26,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[29,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(27,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:04','2023-01-11 11:32:04','[30,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436724;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436724;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(28,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:05','2023-01-11 11:32:05','[31,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436725;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436725;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(29,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:05','2023-01-11 11:32:05','[32,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436725;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436725;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(30,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:05','2023-01-11 11:32:05','[33,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436725;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436725;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(31,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:05','2023-01-11 11:32:05','[34,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436725;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436725;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(32,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:05','2023-01-11 11:32:05','[35,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436725;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436725;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(33,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:05','2023-01-11 11:32:05','[36,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436725;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436725;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(34,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:05','2023-01-11 11:32:05','[37,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436725;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436725;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(35,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:05','2023-01-11 11:32:05','[38,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436725;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436725;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(36,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:05','2023-01-11 11:32:05','[39,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436725;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436725;}',2,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(37,'woocommerce_admin/stored_state_setup_for_products/async/run_remote_notifications','complete','0000-00-00 00:00:00','0000-00-00 00:00:00','[]','O:28:\"ActionScheduler_NullSchedule\":0:{}',0,1,'2023-01-11 11:32:33','2023-01-11 11:32:33',0,NULL),(38,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:32:59','2023-01-11 11:32:59','[16,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436779;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436779;}',2,1,'2023-01-11 11:33:41','2023-01-11 11:33:41',0,NULL),(39,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:00','2023-01-11 11:33:00','[17,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436780;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436780;}',2,1,'2023-01-11 11:33:41','2023-01-11 11:33:41',0,NULL),(40,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:02','2023-01-11 11:33:02','[18,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436782;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436782;}',2,1,'2023-01-11 11:33:41','2023-01-11 11:33:41',0,NULL),(41,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:06','2023-01-11 11:33:06','[19,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436786;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436786;}',2,1,'2023-01-11 11:33:41','2023-01-11 11:33:41',0,NULL),(42,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:13','2023-01-11 11:33:13','[21,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436793;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436793;}',2,1,'2023-01-11 11:33:41','2023-01-11 11:33:41',0,NULL),(43,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:22','2023-01-11 11:33:22','[23,3]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436802;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436802;}',2,1,'2023-01-11 11:33:41','2023-01-11 11:33:41',0,NULL),(44,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:28','2023-01-11 11:33:28','[25,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436808;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436808;}',2,1,'2023-01-11 11:33:41','2023-01-11 11:33:41',0,NULL),(45,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:42','2023-01-11 11:33:42','[26,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436822;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436822;}',2,1,'2023-01-11 11:33:47','2023-01-11 11:33:47',0,NULL),(46,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:53','2023-01-11 11:33:53','[15,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436833;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436833;}',2,1,'2023-01-11 11:34:02','2023-01-11 11:34:02',0,NULL),(47,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:53','2023-01-11 11:33:53','[29,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436833;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436833;}',2,1,'2023-01-11 11:34:02','2023-01-11 11:34:02',0,NULL),(48,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:53','2023-01-11 11:33:53','[30,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436833;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436833;}',2,1,'2023-01-11 11:34:02','2023-01-11 11:34:02',0,NULL),(49,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:53','2023-01-11 11:33:53','[31,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436833;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436833;}',2,1,'2023-01-11 11:34:02','2023-01-11 11:34:02',0,NULL),(50,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:53','2023-01-11 11:33:53','[16,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436833;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436833;}',2,1,'2023-01-11 11:34:02','2023-01-11 11:34:02',0,NULL),(51,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:53','2023-01-11 11:33:53','[32,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436833;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436833;}',2,1,'2023-01-11 11:34:02','2023-01-11 11:34:02',0,NULL),(52,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:53','2023-01-11 11:33:53','[33,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436833;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436833;}',2,1,'2023-01-11 11:34:02','2023-01-11 11:34:02',0,NULL),(53,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:33:53','2023-01-11 11:33:53','[34,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436833;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436833;}',2,1,'2023-01-11 11:34:02','2023-01-11 11:34:02',0,NULL),(54,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:34:02','2023-01-11 11:34:02','[35,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436842;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436842;}',2,1,'2023-01-11 11:34:02','2023-01-11 11:34:02',0,NULL),(55,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:34:07','2023-01-11 11:34:07','[36,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436847;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436847;}',2,1,'2023-01-11 11:34:42','2023-01-11 11:34:42',0,NULL),(56,'adjust_download_permissions','complete','2023-01-11 11:34:07','2023-01-11 11:34:07','[15]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436847;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436847;}',0,1,'2023-01-11 11:34:42','2023-01-11 11:34:42',0,NULL),(57,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:34:07','2023-01-11 11:34:07','[15,2]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436847;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436847;}',2,1,'2023-01-11 11:34:42','2023-01-11 11:34:42',0,NULL),(58,'adjust_download_permissions','complete','2023-01-11 11:34:07','2023-01-11 11:34:07','[16]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436847;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436847;}',0,1,'2023-01-11 11:34:42','2023-01-11 11:34:42',0,NULL),(59,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:34:07','2023-01-11 11:34:07','[16,2]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436847;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436847;}',2,1,'2023-01-11 11:34:42','2023-01-11 11:34:42',0,NULL),(60,'adjust_download_permissions','complete','2023-01-11 11:34:23','2023-01-11 11:34:23','[37]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436863;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436863;}',0,1,'2023-01-11 11:34:43','2023-01-11 11:34:43',0,NULL),(61,'woocommerce_run_product_attribute_lookup_update_callback','complete','2023-01-11 11:34:28','2023-01-11 11:34:28','[39,1]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1673436868;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1673436868;}',2,1,'2023-01-11 11:34:43','2023-01-11 11:34:43',0,NULL),(62,'woocommerce_run_on_woocommerce_admin_updated','pending','2023-02-28 13:12:19','2023-02-28 13:12:19','[\"Automattic\\\\WooCommerce\\\\Admin\\\\RemoteInboxNotifications\\\\RemoteInboxNotificationsEngine\",\"run_on_woocommerce_admin_updated\"]','O:30:\"ActionScheduler_SimpleSchedule\":2:{s:22:\"\0*\0scheduled_timestamp\";i:1677589939;s:41:\"\0ActionScheduler_SimpleSchedule\0timestamp\";i:1677589939;}',3,0,'0000-00-00 00:00:00','0000-00-00 00:00:00',0,NULL);
/*!40000 ALTER TABLE `wp_actionscheduler_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_actionscheduler_claims`
--

DROP TABLE IF EXISTS `wp_actionscheduler_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_actionscheduler_claims` (
  `claim_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_created_gmt` datetime DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`claim_id`),
  KEY `date_created_gmt` (`date_created_gmt`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_actionscheduler_claims`
--

LOCK TABLES `wp_actionscheduler_claims` WRITE;
/*!40000 ALTER TABLE `wp_actionscheduler_claims` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_actionscheduler_claims` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_actionscheduler_groups`
--

DROP TABLE IF EXISTS `wp_actionscheduler_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_actionscheduler_groups` (
  `group_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`group_id`),
  KEY `slug` (`slug`(191))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_actionscheduler_groups`
--

LOCK TABLES `wp_actionscheduler_groups` WRITE;
/*!40000 ALTER TABLE `wp_actionscheduler_groups` DISABLE KEYS */;
INSERT INTO `wp_actionscheduler_groups` VALUES (1,'action-scheduler-migration'),(2,'woocommerce-db-updates'),(3,'woocommerce-remote-inbox-engine');
/*!40000 ALTER TABLE `wp_actionscheduler_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_actionscheduler_logs`
--

DROP TABLE IF EXISTS `wp_actionscheduler_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_actionscheduler_logs` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `action_id` bigint unsigned NOT NULL,
  `message` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `log_date_gmt` datetime DEFAULT '0000-00-00 00:00:00',
  `log_date_local` datetime DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`log_id`),
  KEY `action_id` (`action_id`),
  KEY `log_date_gmt` (`log_date_gmt`)
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_actionscheduler_logs`
--

LOCK TABLES `wp_actionscheduler_logs` WRITE;
/*!40000 ALTER TABLE `wp_actionscheduler_logs` DISABLE KEYS */;
INSERT INTO `wp_actionscheduler_logs` VALUES (1,8,'action created','2023-01-11 11:30:00','2023-01-11 11:30:00'),(2,9,'action created','2023-01-11 11:30:05','2023-01-11 11:30:05'),(3,9,'action started via WP Cron','2023-01-11 11:30:05','2023-01-11 11:30:05'),(4,9,'action complete via WP Cron','2023-01-11 11:30:05','2023-01-11 11:30:05'),(5,10,'action created','2023-01-11 11:30:05','2023-01-11 11:30:05'),(6,8,'action started via Async Request','2023-01-11 11:31:02','2023-01-11 11:31:02'),(7,8,'action complete via Async Request','2023-01-11 11:31:02','2023-01-11 11:31:02'),(8,11,'action created','2023-01-11 11:31:23','2023-01-11 11:31:23'),(9,11,'action started via WP Cron','2023-01-11 11:31:58','2023-01-11 11:31:58'),(10,11,'action complete via WP Cron','2023-01-11 11:31:59','2023-01-11 11:31:59'),(11,12,'action created','2023-01-11 11:32:02','2023-01-11 11:32:02'),(12,13,'action created','2023-01-11 11:32:02','2023-01-11 11:32:02'),(13,14,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(14,15,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(15,16,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(16,17,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(17,18,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(18,19,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(19,20,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(20,21,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(21,22,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(22,23,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(23,24,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(24,25,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(25,26,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(26,27,'action created','2023-01-11 11:32:03','2023-01-11 11:32:03'),(27,28,'action created','2023-01-11 11:32:04','2023-01-11 11:32:04'),(28,29,'action created','2023-01-11 11:32:04','2023-01-11 11:32:04'),(29,30,'action created','2023-01-11 11:32:04','2023-01-11 11:32:04'),(30,31,'action created','2023-01-11 11:32:04','2023-01-11 11:32:04'),(31,32,'action created','2023-01-11 11:32:04','2023-01-11 11:32:04'),(32,33,'action created','2023-01-11 11:32:04','2023-01-11 11:32:04'),(33,34,'action created','2023-01-11 11:32:04','2023-01-11 11:32:04'),(34,35,'action created','2023-01-11 11:32:04','2023-01-11 11:32:04'),(35,36,'action created','2023-01-11 11:32:04','2023-01-11 11:32:04'),(36,37,'action created','2023-01-11 11:32:32','2023-01-11 11:32:32'),(37,37,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(38,37,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(39,12,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(40,12,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(41,13,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(42,13,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(43,14,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(44,14,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(45,15,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(46,15,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(47,16,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(48,16,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(49,17,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(50,17,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(51,18,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(52,18,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(53,19,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(54,19,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(55,20,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(56,20,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(57,21,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(58,21,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(59,22,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(60,22,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(61,23,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(62,23,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(63,24,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(64,24,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(65,25,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(66,25,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(67,26,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(68,26,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(69,27,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(70,27,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(71,28,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(72,28,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(73,29,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(74,29,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(75,30,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(76,30,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(77,31,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(78,31,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(79,32,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(80,32,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(81,33,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(82,33,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(83,34,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(84,34,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(85,35,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(86,35,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(87,36,'action started via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(88,36,'action complete via Async Request','2023-01-11 11:32:33','2023-01-11 11:32:33'),(89,38,'action created','2023-01-11 11:32:58','2023-01-11 11:32:58'),(90,39,'action created','2023-01-11 11:32:59','2023-01-11 11:32:59'),(91,40,'action created','2023-01-11 11:33:01','2023-01-11 11:33:01'),(92,41,'action created','2023-01-11 11:33:05','2023-01-11 11:33:05'),(93,42,'action created','2023-01-11 11:33:12','2023-01-11 11:33:12'),(94,43,'action created','2023-01-11 11:33:21','2023-01-11 11:33:21'),(95,44,'action created','2023-01-11 11:33:27','2023-01-11 11:33:27'),(96,45,'action created','2023-01-11 11:33:41','2023-01-11 11:33:41'),(97,38,'action started via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(98,38,'action complete via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(99,39,'action started via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(100,39,'action complete via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(101,40,'action started via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(102,40,'action complete via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(103,41,'action started via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(104,41,'action complete via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(105,42,'action started via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(106,42,'action complete via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(107,43,'action started via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(108,43,'action complete via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(109,44,'action started via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(110,44,'action complete via Async Request','2023-01-11 11:33:41','2023-01-11 11:33:41'),(111,45,'action started via Async Request','2023-01-11 11:33:47','2023-01-11 11:33:47'),(112,45,'action complete via Async Request','2023-01-11 11:33:47','2023-01-11 11:33:47'),(113,46,'action created','2023-01-11 11:33:52','2023-01-11 11:33:52'),(114,47,'action created','2023-01-11 11:33:52','2023-01-11 11:33:52'),(115,48,'action created','2023-01-11 11:33:52','2023-01-11 11:33:52'),(116,49,'action created','2023-01-11 11:33:52','2023-01-11 11:33:52'),(117,50,'action created','2023-01-11 11:33:52','2023-01-11 11:33:52'),(118,51,'action created','2023-01-11 11:33:52','2023-01-11 11:33:52'),(119,52,'action created','2023-01-11 11:33:52','2023-01-11 11:33:52'),(120,53,'action created','2023-01-11 11:33:52','2023-01-11 11:33:52'),(121,54,'action created','2023-01-11 11:34:01','2023-01-11 11:34:01'),(122,46,'action started via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(123,46,'action complete via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(124,47,'action started via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(125,47,'action complete via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(126,48,'action started via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(127,48,'action complete via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(128,49,'action started via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(129,49,'action complete via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(130,50,'action started via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(131,50,'action complete via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(132,51,'action started via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(133,51,'action complete via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(134,52,'action started via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(135,52,'action complete via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(136,53,'action started via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(137,53,'action complete via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(138,54,'action started via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(139,54,'action complete via WP Cron','2023-01-11 11:34:02','2023-01-11 11:34:02'),(140,55,'action created','2023-01-11 11:34:06','2023-01-11 11:34:06'),(141,56,'action created','2023-01-11 11:34:06','2023-01-11 11:34:06'),(142,57,'action created','2023-01-11 11:34:06','2023-01-11 11:34:06'),(143,58,'action created','2023-01-11 11:34:06','2023-01-11 11:34:06'),(144,59,'action created','2023-01-11 11:34:06','2023-01-11 11:34:06'),(145,60,'action created','2023-01-11 11:34:22','2023-01-11 11:34:22'),(146,61,'action created','2023-01-11 11:34:27','2023-01-11 11:34:27'),(147,55,'action started via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(148,55,'action complete via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(149,56,'action started via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(150,56,'action complete via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(151,57,'action started via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(152,57,'action complete via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(153,58,'action started via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(154,58,'action complete via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(155,59,'action started via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(156,59,'action complete via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(157,60,'action started via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(158,60,'action complete via Async Request','2023-01-11 11:34:42','2023-01-11 11:34:42'),(159,61,'action started via Async Request','2023-01-11 11:34:43','2023-01-11 11:34:43'),(160,61,'action complete via Async Request','2023-01-11 11:34:43','2023-01-11 11:34:43'),(161,62,'action created','2023-02-28 13:12:19','2023-02-28 13:12:19');
/*!40000 ALTER TABLE `wp_actionscheduler_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_commentmeta`
--

DROP TABLE IF EXISTS `wp_commentmeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_commentmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `comment_id` (`comment_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_commentmeta`
--

LOCK TABLES `wp_commentmeta` WRITE;
/*!40000 ALTER TABLE `wp_commentmeta` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_commentmeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_comments`
--

DROP TABLE IF EXISTS `wp_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_comments` (
  `comment_ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_post_ID` bigint unsigned NOT NULL DEFAULT '0',
  `comment_author` tinytext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `comment_author_email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_author_url` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_author_IP` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_content` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `comment_karma` int NOT NULL DEFAULT '0',
  `comment_approved` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '1',
  `comment_agent` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'comment',
  `comment_parent` bigint unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`comment_ID`),
  KEY `comment_post_ID` (`comment_post_ID`),
  KEY `comment_approved_date_gmt` (`comment_approved`,`comment_date_gmt`),
  KEY `comment_date_gmt` (`comment_date_gmt`),
  KEY `comment_parent` (`comment_parent`),
  KEY `comment_author_email` (`comment_author_email`(10)),
  KEY `woo_idx_comment_type` (`comment_type`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_comments`
--

LOCK TABLES `wp_comments` WRITE;
/*!40000 ALTER TABLE `wp_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_links`
--

DROP TABLE IF EXISTS `wp_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_links` (
  `link_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `link_url` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_image` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_target` varchar(25) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_description` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_visible` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'Y',
  `link_owner` bigint unsigned NOT NULL DEFAULT '1',
  `link_rating` int NOT NULL DEFAULT '0',
  `link_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `link_rel` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_notes` mediumtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `link_rss` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`link_id`),
  KEY `link_visible` (`link_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_links`
--

LOCK TABLES `wp_links` WRITE;
/*!40000 ALTER TABLE `wp_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_options`
--

DROP TABLE IF EXISTS `wp_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_options` (
  `option_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `option_value` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `autoload` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name` (`option_name`),
  KEY `autoload` (`autoload`)
) ENGINE=InnoDB AUTO_INCREMENT=427 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_options`
--

LOCK TABLES `wp_options` WRITE;
/*!40000 ALTER TABLE `wp_options` DISABLE KEYS */;
INSERT INTO `wp_options` VALUES (2,'siteurl','http://fresh.test','yes'),(3,'home','http://fresh.test','yes'),(4,'blogname','Test','yes'),(5,'blogdescription','','yes'),(6,'users_can_register','0','yes'),(7,'admin_email','local@local.test','yes'),(8,'start_of_week','1','yes'),(9,'use_balanceTags','0','yes'),(10,'use_smilies','1','yes'),(11,'require_name_email','1','yes'),(12,'comments_notify','1','yes'),(13,'posts_per_rss','10','yes'),(14,'rss_use_excerpt','0','yes'),(15,'mailserver_url','mail.example.com','yes'),(16,'mailserver_login','login@example.com','yes'),(17,'mailserver_pass','password','yes'),(18,'mailserver_port','110','yes'),(19,'default_category','1','yes'),(20,'default_comment_status','open','yes'),(21,'default_ping_status','open','yes'),(22,'default_pingback_flag','1','yes'),(23,'posts_per_page','10','yes'),(24,'date_format','F j, Y','yes'),(25,'time_format','g:i a','yes'),(26,'links_updated_date_format','F j, Y g:i a','yes'),(27,'comment_moderation','0','yes'),(28,'moderation_notify','1','yes'),(29,'rewrite_rules','','yes'),(30,'hack_file','0','yes'),(31,'blog_charset','UTF-8','yes'),(32,'moderation_keys','','no'),(33,'active_plugins','a:2:{i:0;s:27:\"woocommerce/woocommerce.php\";i:1;s:17:\"wp-sms/wp-sms.php\";}','yes'),(34,'category_base','','yes'),(35,'ping_sites','http://rpc.pingomatic.com/','yes'),(36,'comment_max_links','2','yes'),(37,'gmt_offset','0','yes'),(38,'default_email_category','1','yes'),(39,'recently_edited','','no'),(40,'template','twentytwentythree','yes'),(41,'stylesheet','twentytwentythree','yes'),(42,'comment_registration','0','yes'),(43,'html_type','text/html','yes'),(44,'use_trackback','0','yes'),(45,'default_role','subscriber','yes'),(46,'db_version','53496','yes'),(47,'uploads_use_yearmonth_folders','1','yes'),(48,'upload_path','','yes'),(49,'blog_public','1','yes'),(50,'default_link_category','2','yes'),(51,'show_on_front','posts','yes'),(52,'tag_base','','yes'),(53,'show_avatars','1','yes'),(54,'avatar_rating','G','yes'),(55,'upload_url_path','','yes'),(56,'thumbnail_size_w','150','yes'),(57,'thumbnail_size_h','150','yes'),(58,'thumbnail_crop','1','yes'),(59,'medium_size_w','300','yes'),(60,'medium_size_h','300','yes'),(61,'avatar_default','mystery','yes'),(62,'large_size_w','1024','yes'),(63,'large_size_h','1024','yes'),(64,'image_default_link_type','none','yes'),(65,'image_default_size','','yes'),(66,'image_default_align','','yes'),(67,'close_comments_for_old_posts','0','yes'),(68,'close_comments_days_old','14','yes'),(69,'thread_comments','1','yes'),(70,'thread_comments_depth','5','yes'),(71,'page_comments','0','yes'),(72,'comments_per_page','50','yes'),(73,'default_comments_page','newest','yes'),(74,'comment_order','asc','yes'),(75,'sticky_posts','a:0:{}','yes'),(76,'widget_categories','a:0:{}','yes'),(77,'widget_text','a:0:{}','yes'),(78,'widget_rss','a:0:{}','yes'),(79,'uninstall_plugins','a:0:{}','no'),(80,'timezone_string','','yes'),(81,'page_for_posts','0','yes'),(82,'page_on_front','0','yes'),(83,'default_post_format','0','yes'),(84,'link_manager_enabled','0','yes'),(85,'finished_splitting_shared_terms','1','yes'),(86,'site_icon','0','yes'),(87,'medium_large_size_w','768','yes'),(88,'medium_large_size_h','0','yes'),(89,'wp_page_for_privacy_policy','3','yes'),(90,'show_comments_cookies_opt_in','1','yes'),(91,'admin_email_lifespan','1688988251','yes'),(92,'disallowed_keys','','no'),(93,'comment_previously_approved','1','yes'),(94,'auto_plugin_theme_update_emails','a:0:{}','no'),(95,'auto_update_core_dev','enabled','yes'),(96,'auto_update_core_minor','enabled','yes'),(97,'auto_update_core_major','enabled','yes'),(98,'wp_force_deactivated_plugins','a:0:{}','yes'),(99,'initial_db_version','53496','yes'),(100,'wp_user_roles','a:7:{s:13:\"administrator\";a:2:{s:4:\"name\";s:13:\"Administrator\";s:12:\"capabilities\";a:119:{s:13:\"switch_themes\";b:1;s:11:\"edit_themes\";b:1;s:16:\"activate_plugins\";b:1;s:12:\"edit_plugins\";b:1;s:10:\"edit_users\";b:1;s:10:\"edit_files\";b:1;s:14:\"manage_options\";b:1;s:17:\"moderate_comments\";b:1;s:17:\"manage_categories\";b:1;s:12:\"manage_links\";b:1;s:12:\"upload_files\";b:1;s:6:\"import\";b:1;s:15:\"unfiltered_html\";b:1;s:10:\"edit_posts\";b:1;s:17:\"edit_others_posts\";b:1;s:20:\"edit_published_posts\";b:1;s:13:\"publish_posts\";b:1;s:10:\"edit_pages\";b:1;s:4:\"read\";b:1;s:8:\"level_10\";b:1;s:7:\"level_9\";b:1;s:7:\"level_8\";b:1;s:7:\"level_7\";b:1;s:7:\"level_6\";b:1;s:7:\"level_5\";b:1;s:7:\"level_4\";b:1;s:7:\"level_3\";b:1;s:7:\"level_2\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:17:\"edit_others_pages\";b:1;s:20:\"edit_published_pages\";b:1;s:13:\"publish_pages\";b:1;s:12:\"delete_pages\";b:1;s:19:\"delete_others_pages\";b:1;s:22:\"delete_published_pages\";b:1;s:12:\"delete_posts\";b:1;s:19:\"delete_others_posts\";b:1;s:22:\"delete_published_posts\";b:1;s:20:\"delete_private_posts\";b:1;s:18:\"edit_private_posts\";b:1;s:18:\"read_private_posts\";b:1;s:20:\"delete_private_pages\";b:1;s:18:\"edit_private_pages\";b:1;s:18:\"read_private_pages\";b:1;s:12:\"delete_users\";b:1;s:12:\"create_users\";b:1;s:17:\"unfiltered_upload\";b:1;s:14:\"edit_dashboard\";b:1;s:14:\"update_plugins\";b:1;s:14:\"delete_plugins\";b:1;s:15:\"install_plugins\";b:1;s:13:\"update_themes\";b:1;s:14:\"install_themes\";b:1;s:11:\"update_core\";b:1;s:10:\"list_users\";b:1;s:12:\"remove_users\";b:1;s:13:\"promote_users\";b:1;s:18:\"edit_theme_options\";b:1;s:13:\"delete_themes\";b:1;s:6:\"export\";b:1;s:13:\"wpsms_sendsms\";b:1;s:12:\"wpsms_outbox\";b:1;s:11:\"wpsms_inbox\";b:1;s:17:\"wpsms_subscribers\";b:1;s:13:\"wpsms_setting\";b:1;s:18:\"manage_woocommerce\";b:1;s:24:\"view_woocommerce_reports\";b:1;s:12:\"edit_product\";b:1;s:12:\"read_product\";b:1;s:14:\"delete_product\";b:1;s:13:\"edit_products\";b:1;s:20:\"edit_others_products\";b:1;s:16:\"publish_products\";b:1;s:21:\"read_private_products\";b:1;s:15:\"delete_products\";b:1;s:23:\"delete_private_products\";b:1;s:25:\"delete_published_products\";b:1;s:22:\"delete_others_products\";b:1;s:21:\"edit_private_products\";b:1;s:23:\"edit_published_products\";b:1;s:20:\"manage_product_terms\";b:1;s:18:\"edit_product_terms\";b:1;s:20:\"delete_product_terms\";b:1;s:20:\"assign_product_terms\";b:1;s:15:\"edit_shop_order\";b:1;s:15:\"read_shop_order\";b:1;s:17:\"delete_shop_order\";b:1;s:16:\"edit_shop_orders\";b:1;s:23:\"edit_others_shop_orders\";b:1;s:19:\"publish_shop_orders\";b:1;s:24:\"read_private_shop_orders\";b:1;s:18:\"delete_shop_orders\";b:1;s:26:\"delete_private_shop_orders\";b:1;s:28:\"delete_published_shop_orders\";b:1;s:25:\"delete_others_shop_orders\";b:1;s:24:\"edit_private_shop_orders\";b:1;s:26:\"edit_published_shop_orders\";b:1;s:23:\"manage_shop_order_terms\";b:1;s:21:\"edit_shop_order_terms\";b:1;s:23:\"delete_shop_order_terms\";b:1;s:23:\"assign_shop_order_terms\";b:1;s:16:\"edit_shop_coupon\";b:1;s:16:\"read_shop_coupon\";b:1;s:18:\"delete_shop_coupon\";b:1;s:17:\"edit_shop_coupons\";b:1;s:24:\"edit_others_shop_coupons\";b:1;s:20:\"publish_shop_coupons\";b:1;s:25:\"read_private_shop_coupons\";b:1;s:19:\"delete_shop_coupons\";b:1;s:27:\"delete_private_shop_coupons\";b:1;s:29:\"delete_published_shop_coupons\";b:1;s:26:\"delete_others_shop_coupons\";b:1;s:25:\"edit_private_shop_coupons\";b:1;s:27:\"edit_published_shop_coupons\";b:1;s:24:\"manage_shop_coupon_terms\";b:1;s:22:\"edit_shop_coupon_terms\";b:1;s:24:\"delete_shop_coupon_terms\";b:1;s:24:\"assign_shop_coupon_terms\";b:1;}}s:6:\"editor\";a:2:{s:4:\"name\";s:6:\"Editor\";s:12:\"capabilities\";a:34:{s:17:\"moderate_comments\";b:1;s:17:\"manage_categories\";b:1;s:12:\"manage_links\";b:1;s:12:\"upload_files\";b:1;s:15:\"unfiltered_html\";b:1;s:10:\"edit_posts\";b:1;s:17:\"edit_others_posts\";b:1;s:20:\"edit_published_posts\";b:1;s:13:\"publish_posts\";b:1;s:10:\"edit_pages\";b:1;s:4:\"read\";b:1;s:7:\"level_7\";b:1;s:7:\"level_6\";b:1;s:7:\"level_5\";b:1;s:7:\"level_4\";b:1;s:7:\"level_3\";b:1;s:7:\"level_2\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:17:\"edit_others_pages\";b:1;s:20:\"edit_published_pages\";b:1;s:13:\"publish_pages\";b:1;s:12:\"delete_pages\";b:1;s:19:\"delete_others_pages\";b:1;s:22:\"delete_published_pages\";b:1;s:12:\"delete_posts\";b:1;s:19:\"delete_others_posts\";b:1;s:22:\"delete_published_posts\";b:1;s:20:\"delete_private_posts\";b:1;s:18:\"edit_private_posts\";b:1;s:18:\"read_private_posts\";b:1;s:20:\"delete_private_pages\";b:1;s:18:\"edit_private_pages\";b:1;s:18:\"read_private_pages\";b:1;}}s:6:\"author\";a:2:{s:4:\"name\";s:6:\"Author\";s:12:\"capabilities\";a:10:{s:12:\"upload_files\";b:1;s:10:\"edit_posts\";b:1;s:20:\"edit_published_posts\";b:1;s:13:\"publish_posts\";b:1;s:4:\"read\";b:1;s:7:\"level_2\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:12:\"delete_posts\";b:1;s:22:\"delete_published_posts\";b:1;}}s:11:\"contributor\";a:2:{s:4:\"name\";s:11:\"Contributor\";s:12:\"capabilities\";a:5:{s:10:\"edit_posts\";b:1;s:4:\"read\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:12:\"delete_posts\";b:1;}}s:10:\"subscriber\";a:2:{s:4:\"name\";s:10:\"Subscriber\";s:12:\"capabilities\";a:2:{s:4:\"read\";b:1;s:7:\"level_0\";b:1;}}s:8:\"customer\";a:2:{s:4:\"name\";s:8:\"Customer\";s:12:\"capabilities\";a:1:{s:4:\"read\";b:1;}}s:12:\"shop_manager\";a:2:{s:4:\"name\";s:12:\"Shop manager\";s:12:\"capabilities\";a:92:{s:7:\"level_9\";b:1;s:7:\"level_8\";b:1;s:7:\"level_7\";b:1;s:7:\"level_6\";b:1;s:7:\"level_5\";b:1;s:7:\"level_4\";b:1;s:7:\"level_3\";b:1;s:7:\"level_2\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:4:\"read\";b:1;s:18:\"read_private_pages\";b:1;s:18:\"read_private_posts\";b:1;s:10:\"edit_posts\";b:1;s:10:\"edit_pages\";b:1;s:20:\"edit_published_posts\";b:1;s:20:\"edit_published_pages\";b:1;s:18:\"edit_private_pages\";b:1;s:18:\"edit_private_posts\";b:1;s:17:\"edit_others_posts\";b:1;s:17:\"edit_others_pages\";b:1;s:13:\"publish_posts\";b:1;s:13:\"publish_pages\";b:1;s:12:\"delete_posts\";b:1;s:12:\"delete_pages\";b:1;s:20:\"delete_private_pages\";b:1;s:20:\"delete_private_posts\";b:1;s:22:\"delete_published_pages\";b:1;s:22:\"delete_published_posts\";b:1;s:19:\"delete_others_posts\";b:1;s:19:\"delete_others_pages\";b:1;s:17:\"manage_categories\";b:1;s:12:\"manage_links\";b:1;s:17:\"moderate_comments\";b:1;s:12:\"upload_files\";b:1;s:6:\"export\";b:1;s:6:\"import\";b:1;s:10:\"list_users\";b:1;s:18:\"edit_theme_options\";b:1;s:18:\"manage_woocommerce\";b:1;s:24:\"view_woocommerce_reports\";b:1;s:12:\"edit_product\";b:1;s:12:\"read_product\";b:1;s:14:\"delete_product\";b:1;s:13:\"edit_products\";b:1;s:20:\"edit_others_products\";b:1;s:16:\"publish_products\";b:1;s:21:\"read_private_products\";b:1;s:15:\"delete_products\";b:1;s:23:\"delete_private_products\";b:1;s:25:\"delete_published_products\";b:1;s:22:\"delete_others_products\";b:1;s:21:\"edit_private_products\";b:1;s:23:\"edit_published_products\";b:1;s:20:\"manage_product_terms\";b:1;s:18:\"edit_product_terms\";b:1;s:20:\"delete_product_terms\";b:1;s:20:\"assign_product_terms\";b:1;s:15:\"edit_shop_order\";b:1;s:15:\"read_shop_order\";b:1;s:17:\"delete_shop_order\";b:1;s:16:\"edit_shop_orders\";b:1;s:23:\"edit_others_shop_orders\";b:1;s:19:\"publish_shop_orders\";b:1;s:24:\"read_private_shop_orders\";b:1;s:18:\"delete_shop_orders\";b:1;s:26:\"delete_private_shop_orders\";b:1;s:28:\"delete_published_shop_orders\";b:1;s:25:\"delete_others_shop_orders\";b:1;s:24:\"edit_private_shop_orders\";b:1;s:26:\"edit_published_shop_orders\";b:1;s:23:\"manage_shop_order_terms\";b:1;s:21:\"edit_shop_order_terms\";b:1;s:23:\"delete_shop_order_terms\";b:1;s:23:\"assign_shop_order_terms\";b:1;s:16:\"edit_shop_coupon\";b:1;s:16:\"read_shop_coupon\";b:1;s:18:\"delete_shop_coupon\";b:1;s:17:\"edit_shop_coupons\";b:1;s:24:\"edit_others_shop_coupons\";b:1;s:20:\"publish_shop_coupons\";b:1;s:25:\"read_private_shop_coupons\";b:1;s:19:\"delete_shop_coupons\";b:1;s:27:\"delete_private_shop_coupons\";b:1;s:29:\"delete_published_shop_coupons\";b:1;s:26:\"delete_others_shop_coupons\";b:1;s:25:\"edit_private_shop_coupons\";b:1;s:27:\"edit_published_shop_coupons\";b:1;s:24:\"manage_shop_coupon_terms\";b:1;s:22:\"edit_shop_coupon_terms\";b:1;s:24:\"delete_shop_coupon_terms\";b:1;s:24:\"assign_shop_coupon_terms\";b:1;}}}','yes'),(101,'fresh_site','0','yes'),(102,'user_count','1','no'),(103,'widget_block','a:6:{i:2;a:1:{s:7:\"content\";s:19:\"<!-- wp:search /-->\";}i:3;a:1:{s:7:\"content\";s:154:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Recent Posts</h2><!-- /wp:heading --><!-- wp:latest-posts /--></div><!-- /wp:group -->\";}i:4;a:1:{s:7:\"content\";s:227:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Recent Comments</h2><!-- /wp:heading --><!-- wp:latest-comments {\"displayAvatar\":false,\"displayDate\":false,\"displayExcerpt\":false} /--></div><!-- /wp:group -->\";}i:5;a:1:{s:7:\"content\";s:146:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Archives</h2><!-- /wp:heading --><!-- wp:archives /--></div><!-- /wp:group -->\";}i:6;a:1:{s:7:\"content\";s:150:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Categories</h2><!-- /wp:heading --><!-- wp:categories /--></div><!-- /wp:group -->\";}s:12:\"_multiwidget\";i:1;}','yes'),(104,'sidebars_widgets','a:4:{s:19:\"wp_inactive_widgets\";a:0:{}s:9:\"sidebar-1\";a:3:{i:0;s:7:\"block-2\";i:1;s:7:\"block-3\";i:2;s:7:\"block-4\";}s:9:\"sidebar-2\";a:2:{i:0;s:7:\"block-5\";i:1;s:7:\"block-6\";}s:13:\"array_version\";i:3;}','yes'),(105,'wp_sms_db_version','5.9.1','yes'),(106,'cron','a:19:{i:1673436957;a:1:{s:26:\"action_scheduler_run_queue\";a:1:{s:32:\"0d04ed39571b55704c122d726248bbac\";a:3:{s:8:\"schedule\";s:12:\"every_minute\";s:4:\"args\";a:1:{i:0;s:7:\"WP Cron\";}s:8:\"interval\";i:60;}}}i:1673439851;a:1:{s:34:\"wp_privacy_delete_old_export_files\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:6:\"hourly\";s:4:\"args\";a:0:{}s:8:\"interval\";i:3600;}}}i:1673440200;a:1:{s:33:\"wc_admin_process_orders_milestone\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:6:\"hourly\";s:4:\"args\";a:0:{}s:8:\"interval\";i:3600;}}}i:1673440210;a:1:{s:29:\"wc_admin_unsnooze_admin_notes\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:6:\"hourly\";s:4:\"args\";a:0:{}s:8:\"interval\";i:3600;}}}i:1673479451;a:4:{s:18:\"wp_https_detection\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}s:16:\"wp_version_check\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}s:17:\"wp_update_plugins\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}s:16:\"wp_update_themes\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}}i:1673479553;a:1:{s:21:\"wp_update_user_counts\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}}i:1673522651;a:2:{s:30:\"wp_site_health_scheduled_check\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:6:\"weekly\";s:4:\"args\";a:0:{}s:8:\"interval\";i:604800;}}s:32:\"recovery_mode_clean_expired_keys\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}i:1673522753;a:2:{s:19:\"wp_scheduled_delete\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}s:25:\"delete_expired_transients\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}i:1673522761;a:1:{s:30:\"wp_scheduled_auto_draft_delete\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}i:1673522998;a:1:{s:14:\"wc_admin_daily\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}i:1673523118;a:1:{s:26:\"importer_scheduled_cleanup\";a:1:{s:32:\"78525e41f5c2848ff7e1a2337fb96361\";a:2:{s:8:\"schedule\";b:0;s:4:\"args\";a:1:{i:0;i:14;}}}}i:1676071607;a:1:{s:35:\"wp_sms_check_update_licenses_status\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:22:\"wpsms_monthly_interval\";s:4:\"args\";a:0:{}s:8:\"interval\";i:2635200;}}}i:1677589949;a:3:{s:33:\"woocommerce_cleanup_personal_data\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}s:30:\"woocommerce_tracker_send_event\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}s:30:\"generate_category_lookup_table\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:2:{s:8:\"schedule\";b:0;s:4:\"args\";a:0:{}}}}i:1677589999;a:1:{s:25:\"woocommerce_geoip_updater\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:11:\"fifteendays\";s:4:\"args\";a:0:{}s:8:\"interval\";i:1296000;}}}i:1677593539;a:1:{s:32:\"woocommerce_cancel_unpaid_orders\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:2:{s:8:\"schedule\";b:0;s:4:\"args\";a:0:{}}}}i:1677600739;a:2:{s:24:\"woocommerce_cleanup_logs\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}s:31:\"woocommerce_cleanup_rate_limits\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}i:1677611539;a:1:{s:28:\"woocommerce_cleanup_sessions\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}}i:1677628800;a:1:{s:27:\"woocommerce_scheduled_sales\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}s:7:\"version\";i:2;}','yes'),(107,'widget_pages','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(108,'widget_calendar','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(109,'widget_archives','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(110,'widget_media_audio','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(111,'widget_media_image','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(112,'widget_media_gallery','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(113,'widget_media_video','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(114,'widget_meta','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(115,'widget_search','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(116,'nonce_key','P-c+v2N>FoL`%`&pFA>f#1xHe?4+Gzya4dz0;DW~S^;I:@h.V%M5Ok.W(v4gc^WO','no'),(117,'nonce_salt','{ W.Do-uV$#cwCbAeV *DMxG>CvM&>n 4l}Yj3ad #V}LYB`U2fQ!o0Q0>wxJfz<','no'),(118,'widget_recent-posts','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(119,'widget_recent-comments','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(120,'widget_tag_cloud','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(121,'widget_nav_menu','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(122,'widget_custom_html','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(126,'recovery_keys','a:0:{}','yes'),(129,'theme_mods_twentytwentythree','a:1:{s:18:\"custom_css_post_id\";i:-1;}','yes'),(132,'https_detection_errors','a:1:{s:23:\"ssl_verification_failed\";a:1:{i:0;s:24:\"SSL verification failed.\";}}','yes'),(133,'_site_transient_update_core','O:8:\"stdClass\":4:{s:7:\"updates\";a:1:{i:0;O:8:\"stdClass\":10:{s:8:\"response\";s:6:\"latest\";s:8:\"download\";s:58:\"http://downloads.wordpress.org/release/wordpress-6.1.1.zip\";s:6:\"locale\";s:5:\"en_US\";s:8:\"packages\";O:8:\"stdClass\":5:{s:4:\"full\";s:58:\"http://downloads.wordpress.org/release/wordpress-6.1.1.zip\";s:10:\"no_content\";s:69:\"http://downloads.wordpress.org/release/wordpress-6.1.1-no-content.zip\";s:11:\"new_bundled\";s:70:\"http://downloads.wordpress.org/release/wordpress-6.1.1-new-bundled.zip\";s:7:\"partial\";s:0:\"\";s:8:\"rollback\";s:0:\"\";}s:7:\"current\";s:5:\"6.1.1\";s:7:\"version\";s:5:\"6.1.1\";s:11:\"php_version\";s:6:\"5.6.20\";s:13:\"mysql_version\";s:3:\"5.0\";s:11:\"new_bundled\";s:3:\"6.1\";s:15:\"partial_version\";s:0:\"\";}}s:12:\"last_checked\";i:1673436470;s:15:\"version_checked\";s:5:\"6.1.1\";s:12:\"translations\";a:0:{}}','no'),(138,'_site_transient_update_themes','O:8:\"stdClass\":5:{s:12:\"last_checked\";i:1673436475;s:7:\"checked\";a:3:{s:15:\"twentytwentyone\";s:3:\"1.7\";s:17:\"twentytwentythree\";s:3:\"1.0\";s:15:\"twentytwentytwo\";s:3:\"1.3\";}s:8:\"response\";a:0:{}s:9:\"no_update\";a:3:{s:15:\"twentytwentyone\";a:6:{s:5:\"theme\";s:15:\"twentytwentyone\";s:11:\"new_version\";s:3:\"1.7\";s:3:\"url\";s:45:\"https://wordpress.org/themes/twentytwentyone/\";s:7:\"package\";s:61:\"https://downloads.wordpress.org/theme/twentytwentyone.1.7.zip\";s:8:\"requires\";s:3:\"5.3\";s:12:\"requires_php\";s:3:\"5.6\";}s:17:\"twentytwentythree\";a:6:{s:5:\"theme\";s:17:\"twentytwentythree\";s:11:\"new_version\";s:3:\"1.0\";s:3:\"url\";s:47:\"https://wordpress.org/themes/twentytwentythree/\";s:7:\"package\";s:63:\"https://downloads.wordpress.org/theme/twentytwentythree.1.0.zip\";s:8:\"requires\";s:3:\"6.1\";s:12:\"requires_php\";s:3:\"5.6\";}s:15:\"twentytwentytwo\";a:6:{s:5:\"theme\";s:15:\"twentytwentytwo\";s:11:\"new_version\";s:3:\"1.3\";s:3:\"url\";s:45:\"https://wordpress.org/themes/twentytwentytwo/\";s:7:\"package\";s:61:\"https://downloads.wordpress.org/theme/twentytwentytwo.1.3.zip\";s:8:\"requires\";s:3:\"5.9\";s:12:\"requires_php\";s:3:\"5.6\";}}s:12:\"translations\";a:0:{}}','no'),(142,'can_compress_scripts','0','no'),(143,'recently_activated','a:0:{}','yes'),(160,'wpsms_settings','a:0:{}','yes'),(164,'finished_updating_comment_type','1','yes'),(166,'_site_transient_update_plugins','O:8:\"stdClass\":5:{s:12:\"last_checked\";i:1673436594;s:8:\"response\";a:0:{}s:12:\"translations\";a:0:{}s:9:\"no_update\";a:2:{s:27:\"woocommerce/woocommerce.php\";O:8:\"stdClass\":10:{s:2:\"id\";s:25:\"w.org/plugins/woocommerce\";s:4:\"slug\";s:11:\"woocommerce\";s:6:\"plugin\";s:27:\"woocommerce/woocommerce.php\";s:11:\"new_version\";s:5:\"7.2.3\";s:3:\"url\";s:42:\"https://wordpress.org/plugins/woocommerce/\";s:7:\"package\";s:60:\"https://downloads.wordpress.org/plugin/woocommerce.7.2.3.zip\";s:5:\"icons\";a:2:{s:2:\"2x\";s:64:\"https://ps.w.org/woocommerce/assets/icon-256x256.png?rev=2366418\";s:2:\"1x\";s:64:\"https://ps.w.org/woocommerce/assets/icon-128x128.png?rev=2366418\";}s:7:\"banners\";a:2:{s:2:\"2x\";s:67:\"https://ps.w.org/woocommerce/assets/banner-1544x500.png?rev=2366418\";s:2:\"1x\";s:66:\"https://ps.w.org/woocommerce/assets/banner-772x250.png?rev=2366418\";}s:11:\"banners_rtl\";a:0:{}s:8:\"requires\";s:3:\"5.8\";}s:17:\"wp-sms/wp-sms.php\";O:8:\"stdClass\":10:{s:2:\"id\";s:20:\"w.org/plugins/wp-sms\";s:4:\"slug\";s:6:\"wp-sms\";s:6:\"plugin\";s:17:\"wp-sms/wp-sms.php\";s:11:\"new_version\";s:5:\"5.9.1\";s:3:\"url\";s:37:\"https://wordpress.org/plugins/wp-sms/\";s:7:\"package\";s:55:\"https://downloads.wordpress.org/plugin/wp-sms.5.9.1.zip\";s:5:\"icons\";a:3:{s:2:\"2x\";s:59:\"https://ps.w.org/wp-sms/assets/icon-256x256.png?rev=2718479\";s:2:\"1x\";s:51:\"https://ps.w.org/wp-sms/assets/icon.svg?rev=2772466\";s:3:\"svg\";s:51:\"https://ps.w.org/wp-sms/assets/icon.svg?rev=2772466\";}s:7:\"banners\";a:2:{s:2:\"2x\";s:62:\"https://ps.w.org/wp-sms/assets/banner-1544x500.jpg?rev=2718479\";s:2:\"1x\";s:61:\"https://ps.w.org/wp-sms/assets/banner-772x250.jpg?rev=2718479\";}s:11:\"banners_rtl\";a:0:{}s:8:\"requires\";s:3:\"3.0\";}}s:7:\"checked\";a:2:{s:27:\"woocommerce/woocommerce.php\";s:5:\"7.2.3\";s:17:\"wp-sms/wp-sms.php\";s:5:\"5.9.1\";}}','no'),(167,'action_scheduler_hybrid_store_demarkation','7','yes'),(168,'schema-ActionScheduler_StoreSchema','6.0.1673436597','yes'),(169,'schema-ActionScheduler_LoggerSchema','3.0.1673436597','yes'),(172,'woocommerce_schema_version','430','yes'),(173,'woocommerce_store_address','','yes'),(174,'woocommerce_store_address_2','','yes'),(175,'woocommerce_store_city','','yes'),(176,'woocommerce_default_country','US:CA','yes'),(177,'woocommerce_store_postcode','','yes'),(178,'woocommerce_allowed_countries','all','yes'),(179,'woocommerce_all_except_countries','','yes'),(180,'woocommerce_specific_allowed_countries','','yes'),(181,'woocommerce_ship_to_countries','','yes'),(182,'woocommerce_specific_ship_to_countries','','yes'),(183,'woocommerce_default_customer_address','base','yes'),(184,'woocommerce_calc_taxes','no','yes'),(185,'woocommerce_enable_coupons','yes','yes'),(186,'woocommerce_calc_discounts_sequentially','no','no'),(187,'woocommerce_currency','USD','yes'),(188,'woocommerce_currency_pos','left','yes'),(189,'woocommerce_price_thousand_sep',',','yes'),(190,'woocommerce_price_decimal_sep','.','yes'),(191,'woocommerce_price_num_decimals','2','yes'),(192,'woocommerce_shop_page_id','8','yes'),(193,'woocommerce_cart_redirect_after_add','no','yes'),(194,'woocommerce_enable_ajax_add_to_cart','yes','yes'),(195,'woocommerce_placeholder_image','7','yes'),(196,'woocommerce_weight_unit','kg','yes'),(197,'woocommerce_dimension_unit','cm','yes'),(198,'woocommerce_enable_reviews','yes','yes'),(199,'woocommerce_review_rating_verification_label','yes','no'),(200,'woocommerce_review_rating_verification_required','no','no'),(201,'woocommerce_enable_review_rating','yes','yes'),(202,'woocommerce_review_rating_required','yes','no'),(203,'woocommerce_manage_stock','yes','yes'),(204,'woocommerce_hold_stock_minutes','60','no'),(205,'woocommerce_notify_low_stock','yes','no'),(206,'woocommerce_notify_no_stock','yes','no'),(207,'woocommerce_stock_email_recipient','local@local.test','no'),(208,'woocommerce_notify_low_stock_amount','2','no'),(209,'woocommerce_notify_no_stock_amount','0','yes'),(210,'woocommerce_hide_out_of_stock_items','no','yes'),(211,'woocommerce_stock_format','','yes'),(212,'woocommerce_file_download_method','force','no'),(213,'woocommerce_downloads_redirect_fallback_allowed','no','no'),(214,'woocommerce_downloads_require_login','no','no'),(215,'woocommerce_downloads_grant_access_after_payment','yes','no'),(216,'woocommerce_downloads_deliver_inline','','no'),(217,'woocommerce_downloads_add_hash_to_filename','yes','yes'),(218,'woocommerce_attribute_lookup_enabled','no','yes'),(219,'woocommerce_attribute_lookup_direct_updates','no','yes'),(220,'woocommerce_prices_include_tax','no','yes'),(221,'woocommerce_tax_based_on','shipping','yes'),(222,'woocommerce_shipping_tax_class','inherit','yes'),(223,'woocommerce_tax_round_at_subtotal','no','yes'),(224,'woocommerce_tax_classes','','yes'),(225,'woocommerce_tax_display_shop','excl','yes'),(226,'woocommerce_tax_display_cart','excl','yes'),(227,'woocommerce_price_display_suffix','','yes'),(228,'woocommerce_tax_total_display','itemized','no'),(229,'woocommerce_enable_shipping_calc','yes','no'),(230,'woocommerce_shipping_cost_requires_address','no','yes'),(231,'woocommerce_ship_to_destination','billing','no'),(232,'woocommerce_shipping_debug_mode','no','yes'),(233,'woocommerce_enable_guest_checkout','yes','no'),(234,'woocommerce_enable_checkout_login_reminder','no','no'),(235,'woocommerce_enable_signup_and_login_from_checkout','no','no'),(236,'woocommerce_enable_myaccount_registration','no','no'),(237,'woocommerce_registration_generate_username','yes','no'),(238,'woocommerce_registration_generate_password','yes','no'),(239,'woocommerce_erasure_request_removes_order_data','no','no'),(240,'woocommerce_erasure_request_removes_download_data','no','no'),(241,'woocommerce_allow_bulk_remove_personal_data','no','no'),(242,'woocommerce_registration_privacy_policy_text','Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our [privacy_policy].','yes'),(243,'woocommerce_checkout_privacy_policy_text','Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our [privacy_policy].','yes'),(244,'woocommerce_delete_inactive_accounts','a:2:{s:6:\"number\";s:0:\"\";s:4:\"unit\";s:6:\"months\";}','no'),(245,'woocommerce_trash_pending_orders','','no'),(246,'woocommerce_trash_failed_orders','','no'),(247,'woocommerce_trash_cancelled_orders','','no'),(248,'woocommerce_anonymize_completed_orders','a:2:{s:6:\"number\";s:0:\"\";s:4:\"unit\";s:6:\"months\";}','no'),(249,'woocommerce_email_from_name','Test','no'),(250,'woocommerce_email_from_address','local@local.test','no'),(251,'woocommerce_email_header_image','','no'),(252,'woocommerce_email_footer_text','{site_title} &mdash; Built with {WooCommerce}','no'),(253,'woocommerce_email_base_color','#7f54b3','no'),(254,'woocommerce_email_background_color','#f7f7f7','no'),(255,'woocommerce_email_body_background_color','#ffffff','no'),(256,'woocommerce_email_text_color','#3c3c3c','no'),(257,'woocommerce_merchant_email_notifications','no','no'),(258,'woocommerce_cart_page_id','9','no'),(259,'woocommerce_checkout_page_id','10','no'),(260,'woocommerce_myaccount_page_id','11','no'),(261,'woocommerce_terms_page_id','','no'),(262,'woocommerce_force_ssl_checkout','no','yes'),(263,'woocommerce_unforce_ssl_checkout','no','yes'),(264,'woocommerce_checkout_pay_endpoint','order-pay','yes'),(265,'woocommerce_checkout_order_received_endpoint','order-received','yes'),(266,'woocommerce_myaccount_add_payment_method_endpoint','add-payment-method','yes'),(267,'woocommerce_myaccount_delete_payment_method_endpoint','delete-payment-method','yes'),(268,'woocommerce_myaccount_set_default_payment_method_endpoint','set-default-payment-method','yes'),(269,'woocommerce_myaccount_orders_endpoint','orders','yes'),(270,'woocommerce_myaccount_view_order_endpoint','view-order','yes'),(271,'woocommerce_myaccount_downloads_endpoint','downloads','yes'),(272,'woocommerce_myaccount_edit_account_endpoint','edit-account','yes'),(273,'woocommerce_myaccount_edit_address_endpoint','edit-address','yes'),(274,'woocommerce_myaccount_payment_methods_endpoint','payment-methods','yes'),(275,'woocommerce_myaccount_lost_password_endpoint','lost-password','yes'),(276,'woocommerce_logout_endpoint','customer-logout','yes'),(277,'woocommerce_api_enabled','no','yes'),(278,'woocommerce_allow_tracking','no','no'),(279,'woocommerce_show_marketplace_suggestions','yes','no'),(280,'woocommerce_analytics_enabled','yes','yes'),(281,'woocommerce_navigation_enabled','no','yes'),(282,'woocommerce_feature_custom_order_tables_enabled','no','yes'),(283,'woocommerce_single_image_width','600','yes'),(284,'woocommerce_thumbnail_image_width','300','yes'),(285,'woocommerce_checkout_highlight_required_fields','yes','yes'),(286,'woocommerce_demo_store','no','no'),(287,'wc_downloads_approved_directories_mode','enabled','yes'),(288,'woocommerce_permalinks','a:5:{s:12:\"product_base\";s:7:\"product\";s:13:\"category_base\";s:16:\"product-category\";s:8:\"tag_base\";s:11:\"product-tag\";s:14:\"attribute_base\";s:0:\"\";s:22:\"use_verbose_page_rules\";b:0;}','yes'),(289,'current_theme_supports_woocommerce','yes','yes'),(290,'woocommerce_queue_flush_rewrite_rules','no','yes'),(293,'default_product_cat','15','yes'),(295,'woocommerce_refund_returns_page_id','12','yes'),(298,'woocommerce_paypal_settings','a:23:{s:7:\"enabled\";s:2:\"no\";s:5:\"title\";s:6:\"PayPal\";s:11:\"description\";s:85:\"Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.\";s:5:\"email\";s:16:\"local@local.test\";s:8:\"advanced\";s:0:\"\";s:8:\"testmode\";s:2:\"no\";s:5:\"debug\";s:2:\"no\";s:16:\"ipn_notification\";s:3:\"yes\";s:14:\"receiver_email\";s:16:\"local@local.test\";s:14:\"identity_token\";s:0:\"\";s:14:\"invoice_prefix\";s:3:\"WC-\";s:13:\"send_shipping\";s:3:\"yes\";s:16:\"address_override\";s:2:\"no\";s:13:\"paymentaction\";s:4:\"sale\";s:9:\"image_url\";s:0:\"\";s:11:\"api_details\";s:0:\"\";s:12:\"api_username\";s:0:\"\";s:12:\"api_password\";s:0:\"\";s:13:\"api_signature\";s:0:\"\";s:20:\"sandbox_api_username\";s:0:\"\";s:20:\"sandbox_api_password\";s:0:\"\";s:21:\"sandbox_api_signature\";s:0:\"\";s:12:\"_should_load\";s:2:\"no\";}','yes'),(299,'woocommerce_version','7.4.0','yes'),(300,'woocommerce_db_version','7.4.0','yes'),(301,'woocommerce_admin_install_timestamp','1673436599','yes'),(302,'woocommerce_inbox_variant_assignment','11','yes'),(306,'_transient_jetpack_autoloader_plugin_paths','a:1:{i:0;s:29:\"{{WP_PLUGIN_DIR}}/woocommerce\";}','yes'),(307,'action_scheduler_lock_async-request-runner','1673436942','yes'),(308,'woocommerce_admin_notices','a:2:{i:0;s:20:\"no_secure_connection\";i:1;s:14:\"template_files\";}','yes'),(309,'woocommerce_maxmind_geolocation_settings','a:1:{s:15:\"database_prefix\";s:32:\"hnf9GxAlxP0zyaYj2albzSgU2MZmwHZs\";}','yes'),(310,'_transient_woocommerce_webhook_ids_status_active','a:0:{}','yes'),(311,'widget_woocommerce_widget_cart','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(312,'widget_woocommerce_layered_nav_filters','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(313,'widget_woocommerce_layered_nav','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(314,'widget_woocommerce_price_filter','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(315,'widget_woocommerce_product_categories','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(316,'widget_woocommerce_product_search','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(317,'widget_woocommerce_product_tag_cloud','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(318,'widget_woocommerce_products','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(319,'widget_woocommerce_recently_viewed_products','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(320,'widget_woocommerce_top_rated_products','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(321,'widget_woocommerce_recent_reviews','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(322,'widget_woocommerce_rating_filter','a:1:{s:12:\"_multiwidget\";i:1;}','yes'),(326,'_transient_wc_count_comments','O:8:\"stdClass\":7:{s:14:\"total_comments\";i:0;s:3:\"all\";i:0;s:9:\"moderated\";i:0;s:8:\"approved\";i:0;s:4:\"spam\";i:0;s:5:\"trash\";i:0;s:12:\"post-trashed\";i:0;}','yes'),(329,'wc_remote_inbox_notifications_stored_state','O:8:\"stdClass\":2:{s:22:\"there_were_no_products\";b:1;s:22:\"there_are_now_products\";b:1;}','no'),(334,'_transient_woocommerce_shipping_task_zone_count_transient','0','yes'),(336,'wc_blocks_db_schema_version','260','yes'),(339,'_transient_woocommerce_reports-transient-version','1673436607','yes'),(360,'_transient_shipping-transient-version','1673436613','yes'),(363,'woocommerce_task_list_tracked_completed_tasks','a:2:{i:0;s:8:\"purchase\";i:1;s:8:\"products\";}','yes'),(365,'woocommerce_onboarding_profile','a:1:{s:7:\"skipped\";b:1;}','yes'),(366,'woocommerce_task_list_prompt_shown','1','yes'),(375,'wpsms_gateway_credit','','yes'),(376,'action_scheduler_migration_status','complete','yes'),(378,'woocommerce_marketplace_suggestions','a:2:{s:11:\"suggestions\";a:28:{i:0;a:4:{s:4:\"slug\";s:28:\"product-edit-meta-tab-header\";s:7:\"context\";s:28:\"product-edit-meta-tab-header\";s:5:\"title\";s:22:\"Recommended extensions\";s:13:\"allow-dismiss\";b:0;}i:1;a:6:{s:4:\"slug\";s:39:\"product-edit-meta-tab-footer-browse-all\";s:7:\"context\";s:28:\"product-edit-meta-tab-footer\";s:9:\"link-text\";s:21:\"Browse all extensions\";s:3:\"url\";s:64:\"https://woocommerce.com/product-category/woocommerce-extensions/\";s:8:\"promoted\";s:31:\"category-woocommerce-extensions\";s:13:\"allow-dismiss\";b:0;}i:2;a:9:{s:4:\"slug\";s:46:\"product-edit-mailchimp-woocommerce-memberships\";s:7:\"product\";s:33:\"woocommerce-memberships-mailchimp\";s:14:\"show-if-active\";a:1:{i:0;s:23:\"woocommerce-memberships\";}s:7:\"context\";a:1:{i:0;s:26:\"product-edit-meta-tab-body\";}s:4:\"icon\";s:116:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/mailchimp-for-memberships.svg\";s:5:\"title\";s:25:\"Mailchimp for Memberships\";s:4:\"copy\";s:79:\"Completely automate your email lists by syncing membership changes to Mailchimp\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:67:\"https://woocommerce.com/products/mailchimp-woocommerce-memberships/\";}i:3;a:9:{s:4:\"slug\";s:19:\"product-edit-addons\";s:7:\"product\";s:26:\"woocommerce-product-addons\";s:14:\"show-if-active\";a:2:{i:0;s:25:\"woocommerce-subscriptions\";i:1;s:20:\"woocommerce-bookings\";}s:7:\"context\";a:1:{i:0;s:26:\"product-edit-meta-tab-body\";}s:4:\"icon\";s:106:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/product-add-ons.svg\";s:5:\"title\";s:15:\"Product Add-Ons\";s:4:\"copy\";s:93:\"Offer add-ons like gift wrapping, special messages or other special options for your products\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:49:\"https://woocommerce.com/products/product-add-ons/\";}i:4;a:9:{s:4:\"slug\";s:46:\"product-edit-woocommerce-subscriptions-gifting\";s:7:\"product\";s:33:\"woocommerce-subscriptions-gifting\";s:14:\"show-if-active\";a:1:{i:0;s:25:\"woocommerce-subscriptions\";}s:7:\"context\";a:1:{i:0;s:26:\"product-edit-meta-tab-body\";}s:4:\"icon\";s:116:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/gifting-for-subscriptions.svg\";s:5:\"title\";s:25:\"Gifting for Subscriptions\";s:4:\"copy\";s:70:\"Let customers buy subscriptions for others - they\'re the ultimate gift\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:67:\"https://woocommerce.com/products/woocommerce-subscriptions-gifting/\";}i:5;a:9:{s:4:\"slug\";s:42:\"product-edit-teams-woocommerce-memberships\";s:7:\"product\";s:33:\"woocommerce-memberships-for-teams\";s:14:\"show-if-active\";a:1:{i:0;s:23:\"woocommerce-memberships\";}s:7:\"context\";a:1:{i:0;s:26:\"product-edit-meta-tab-body\";}s:4:\"icon\";s:112:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/teams-for-memberships.svg\";s:5:\"title\";s:21:\"Teams for Memberships\";s:4:\"copy\";s:123:\"Adds B2B functionality to WooCommerce Memberships, allowing sites to sell team, group, corporate, or family member accounts\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:63:\"https://woocommerce.com/products/teams-woocommerce-memberships/\";}i:6;a:8:{s:4:\"slug\";s:29:\"product-edit-variation-images\";s:7:\"product\";s:39:\"woocommerce-additional-variation-images\";s:7:\"context\";a:1:{i:0;s:26:\"product-edit-meta-tab-body\";}s:4:\"icon\";s:118:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/additional-variation-images.svg\";s:5:\"title\";s:27:\"Additional Variation Images\";s:4:\"copy\";s:72:\"Showcase your products in the best light with a image for each variation\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:73:\"https://woocommerce.com/products/woocommerce-additional-variation-images/\";}i:7;a:9:{s:4:\"slug\";s:47:\"product-edit-woocommerce-subscription-downloads\";s:7:\"product\";s:34:\"woocommerce-subscription-downloads\";s:14:\"show-if-active\";a:1:{i:0;s:25:\"woocommerce-subscriptions\";}s:7:\"context\";a:1:{i:0;s:26:\"product-edit-meta-tab-body\";}s:4:\"icon\";s:113:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/subscription-downloads.svg\";s:5:\"title\";s:22:\"Subscription Downloads\";s:4:\"copy\";s:57:\"Give customers special downloads with their subscriptions\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:68:\"https://woocommerce.com/products/woocommerce-subscription-downloads/\";}i:8;a:8:{s:4:\"slug\";s:31:\"product-edit-min-max-quantities\";s:7:\"product\";s:30:\"woocommerce-min-max-quantities\";s:7:\"context\";a:1:{i:0;s:26:\"product-edit-meta-tab-body\";}s:4:\"icon\";s:109:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/min-max-quantities.svg\";s:5:\"title\";s:18:\"Min/Max Quantities\";s:4:\"copy\";s:81:\"Specify minimum and maximum allowed product quantities for orders to be completed\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:52:\"https://woocommerce.com/products/min-max-quantities/\";}i:9;a:8:{s:4:\"slug\";s:28:\"product-edit-name-your-price\";s:7:\"product\";s:27:\"woocommerce-name-your-price\";s:7:\"context\";a:1:{i:0;s:26:\"product-edit-meta-tab-body\";}s:4:\"icon\";s:106:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/name-your-price.svg\";s:5:\"title\";s:15:\"Name Your Price\";s:4:\"copy\";s:70:\"Let customers pay what they want - useful for donations, tips and more\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:49:\"https://woocommerce.com/products/name-your-price/\";}i:10;a:8:{s:4:\"slug\";s:42:\"product-edit-woocommerce-one-page-checkout\";s:7:\"product\";s:29:\"woocommerce-one-page-checkout\";s:7:\"context\";a:1:{i:0;s:26:\"product-edit-meta-tab-body\";}s:4:\"icon\";s:108:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/one-page-checkout.svg\";s:5:\"title\";s:17:\"One Page Checkout\";s:4:\"copy\";s:92:\"Don\'t make customers click around - let them choose products, checkout & pay all on one page\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:63:\"https://woocommerce.com/products/woocommerce-one-page-checkout/\";}i:11;a:9:{s:4:\"slug\";s:24:\"product-edit-automatewoo\";s:7:\"product\";s:11:\"automatewoo\";s:14:\"show-if-active\";a:1:{i:0;s:25:\"woocommerce-subscriptions\";}s:7:\"context\";a:1:{i:0;s:26:\"product-edit-meta-tab-body\";}s:4:\"icon\";s:104:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/subscriptions.svg\";s:5:\"title\";s:23:\"Automate your marketing\";s:4:\"copy\";s:89:\"Win customers and keep them coming back with a nearly endless range of powerful workflows\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:45:\"https://woocommerce.com/products/automatewoo/\";}i:12;a:4:{s:4:\"slug\";s:19:\"orders-empty-header\";s:7:\"context\";s:24:\"orders-list-empty-header\";s:5:\"title\";s:20:\"Tools for your store\";s:13:\"allow-dismiss\";b:0;}i:13;a:6:{s:4:\"slug\";s:30:\"orders-empty-footer-browse-all\";s:7:\"context\";s:24:\"orders-list-empty-footer\";s:9:\"link-text\";s:21:\"Browse all extensions\";s:3:\"url\";s:64:\"https://woocommerce.com/product-category/woocommerce-extensions/\";s:8:\"promoted\";s:31:\"category-woocommerce-extensions\";s:13:\"allow-dismiss\";b:0;}i:14;a:8:{s:4:\"slug\";s:19:\"orders-empty-wc-pay\";s:7:\"context\";s:22:\"orders-list-empty-body\";s:7:\"product\";s:20:\"woocommerce-payments\";s:4:\"icon\";s:111:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/woocommerce-payments.svg\";s:5:\"title\";s:20:\"WooCommerce Payments\";s:4:\"copy\";s:125:\"Securely accept payments and manage transactions directly from your WooCommerce dashboard – no setup costs or monthly fees.\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:54:\"https://woocommerce.com/products/woocommerce-payments/\";}i:15;a:8:{s:4:\"slug\";s:19:\"orders-empty-zapier\";s:7:\"context\";s:22:\"orders-list-empty-body\";s:7:\"product\";s:18:\"woocommerce-zapier\";s:4:\"icon\";s:97:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/zapier.svg\";s:5:\"title\";s:6:\"Zapier\";s:4:\"copy\";s:88:\"Save time and increase productivity by connecting your store to more than 1000+ services\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:52:\"https://woocommerce.com/products/woocommerce-zapier/\";}i:16;a:8:{s:4:\"slug\";s:30:\"orders-empty-shipment-tracking\";s:7:\"context\";s:22:\"orders-list-empty-body\";s:7:\"product\";s:29:\"woocommerce-shipment-tracking\";s:4:\"icon\";s:108:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/shipment-tracking.svg\";s:5:\"title\";s:17:\"Shipment Tracking\";s:4:\"copy\";s:86:\"Let customers know when their orders will arrive by adding shipment tracking to emails\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:51:\"https://woocommerce.com/products/shipment-tracking/\";}i:17;a:8:{s:4:\"slug\";s:32:\"orders-empty-table-rate-shipping\";s:7:\"context\";s:22:\"orders-list-empty-body\";s:7:\"product\";s:31:\"woocommerce-table-rate-shipping\";s:4:\"icon\";s:110:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/table-rate-shipping.svg\";s:5:\"title\";s:19:\"Table Rate Shipping\";s:4:\"copy\";s:122:\"Advanced, flexible shipping. Define multiple shipping rates based on location, price, weight, shipping class or item count\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:53:\"https://woocommerce.com/products/table-rate-shipping/\";}i:18;a:8:{s:4:\"slug\";s:40:\"orders-empty-shipping-carrier-extensions\";s:7:\"context\";s:22:\"orders-list-empty-body\";s:4:\"icon\";s:118:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/shipping-carrier-extensions.svg\";s:5:\"title\";s:27:\"Shipping Carrier Extensions\";s:4:\"copy\";s:116:\"Show live rates from FedEx, UPS, USPS and more directly on your store - never under or overcharge for shipping again\";s:11:\"button-text\";s:13:\"Find Carriers\";s:8:\"promoted\";s:26:\"category-shipping-carriers\";s:3:\"url\";s:99:\"https://woocommerce.com/product-category/woocommerce-extensions/shipping-methods/shipping-carriers/\";}i:19;a:8:{s:4:\"slug\";s:32:\"orders-empty-google-product-feed\";s:7:\"context\";s:22:\"orders-list-empty-body\";s:7:\"product\";s:25:\"woocommerce-product-feeds\";s:4:\"icon\";s:110:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/google-product-feed.svg\";s:5:\"title\";s:19:\"Google Product Feed\";s:4:\"copy\";s:76:\"Increase sales by letting customers find you when they\'re shopping on Google\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:53:\"https://woocommerce.com/products/google-product-feed/\";}i:20;a:4:{s:4:\"slug\";s:35:\"products-empty-header-product-types\";s:7:\"context\";s:26:\"products-list-empty-header\";s:5:\"title\";s:23:\"Other types of products\";s:13:\"allow-dismiss\";b:0;}i:21;a:6:{s:4:\"slug\";s:32:\"products-empty-footer-browse-all\";s:7:\"context\";s:26:\"products-list-empty-footer\";s:9:\"link-text\";s:21:\"Browse all extensions\";s:3:\"url\";s:64:\"https://woocommerce.com/product-category/woocommerce-extensions/\";s:8:\"promoted\";s:31:\"category-woocommerce-extensions\";s:13:\"allow-dismiss\";b:0;}i:22;a:8:{s:4:\"slug\";s:30:\"products-empty-product-vendors\";s:7:\"context\";s:24:\"products-list-empty-body\";s:7:\"product\";s:27:\"woocommerce-product-vendors\";s:4:\"icon\";s:106:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/product-vendors.svg\";s:5:\"title\";s:15:\"Product Vendors\";s:4:\"copy\";s:47:\"Turn your store into a multi-vendor marketplace\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:49:\"https://woocommerce.com/products/product-vendors/\";}i:23;a:8:{s:4:\"slug\";s:26:\"products-empty-memberships\";s:7:\"context\";s:24:\"products-list-empty-body\";s:7:\"product\";s:23:\"woocommerce-memberships\";s:4:\"icon\";s:102:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/memberships.svg\";s:5:\"title\";s:11:\"Memberships\";s:4:\"copy\";s:76:\"Give members access to restricted content or products, for a fee or for free\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:57:\"https://woocommerce.com/products/woocommerce-memberships/\";}i:24;a:9:{s:4:\"slug\";s:35:\"products-empty-woocommerce-deposits\";s:7:\"context\";s:24:\"products-list-empty-body\";s:7:\"product\";s:20:\"woocommerce-deposits\";s:14:\"show-if-active\";a:1:{i:0;s:20:\"woocommerce-bookings\";}s:4:\"icon\";s:99:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/deposits.svg\";s:5:\"title\";s:8:\"Deposits\";s:4:\"copy\";s:75:\"Make it easier for customers to pay by offering a deposit or a payment plan\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:54:\"https://woocommerce.com/products/woocommerce-deposits/\";}i:25;a:8:{s:4:\"slug\";s:40:\"products-empty-woocommerce-subscriptions\";s:7:\"context\";s:24:\"products-list-empty-body\";s:7:\"product\";s:25:\"woocommerce-subscriptions\";s:4:\"icon\";s:104:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/subscriptions.svg\";s:5:\"title\";s:13:\"Subscriptions\";s:4:\"copy\";s:97:\"Let customers subscribe to your products or services and pay on a weekly, monthly or annual basis\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:59:\"https://woocommerce.com/products/woocommerce-subscriptions/\";}i:26;a:8:{s:4:\"slug\";s:35:\"products-empty-woocommerce-bookings\";s:7:\"context\";s:24:\"products-list-empty-body\";s:7:\"product\";s:20:\"woocommerce-bookings\";s:4:\"icon\";s:99:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/bookings.svg\";s:5:\"title\";s:8:\"Bookings\";s:4:\"copy\";s:99:\"Allow customers to book appointments, make reservations or rent equipment without leaving your site\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:54:\"https://woocommerce.com/products/woocommerce-bookings/\";}i:27;a:8:{s:4:\"slug\";s:30:\"products-empty-product-bundles\";s:7:\"context\";s:24:\"products-list-empty-body\";s:7:\"product\";s:27:\"woocommerce-product-bundles\";s:4:\"icon\";s:106:\"https://woocommerce.com/wp-content/plugins/wccom-plugins/marketplace-suggestions/icons/product-bundles.svg\";s:5:\"title\";s:15:\"Product Bundles\";s:4:\"copy\";s:49:\"Offer customizable bundles and assembled products\";s:11:\"button-text\";s:10:\"Learn More\";s:3:\"url\";s:49:\"https://woocommerce.com/products/product-bundles/\";}}s:7:\"updated\";i:1673436718;}','no'),(380,'_transient_product_query-transient-version','1673436867','yes'),(381,'_transient_product-transient-version','1673436867','yes'),(387,'product_cat_children','a:1:{i:16;a:3:{i:0;i:17;i:1;i:18;i:2;i:19;}}','yes'),(395,'pa_size_children','a:0:{}','yes'),(420,'woocommerce_task_list_reminder_bar_hidden','yes','yes'),(423,'woocommerce_new_product_management_enabled','no','yes'),(424,'_transient_timeout__woocommerce_upload_directory_status','1677676339','no'),(425,'_transient__woocommerce_upload_directory_status','protected','no'),(426,'_transient_doing_cron','1677589941.3575880527496337890625','yes');
/*!40000 ALTER TABLE `wp_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_postmeta`
--

DROP TABLE IF EXISTS `wp_postmeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_postmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `post_id` (`post_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB AUTO_INCREMENT=676 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_postmeta`
--

LOCK TABLES `wp_postmeta` WRITE;
/*!40000 ALTER TABLE `wp_postmeta` DISABLE KEYS */;
INSERT INTO `wp_postmeta` VALUES (7,7,'_wp_attached_file','woocommerce-placeholder.png'),(8,7,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:1200;s:6:\"height\";i:1200;s:4:\"file\";s:27:\"woocommerce-placeholder.png\";s:8:\"filesize\";i:102644;s:5:\"sizes\";a:4:{s:6:\"medium\";a:5:{s:4:\"file\";s:35:\"woocommerce-placeholder-300x300.png\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:12475;}s:5:\"large\";a:5:{s:4:\"file\";s:37:\"woocommerce-placeholder-1024x1024.png\";s:5:\"width\";i:1024;s:6:\"height\";i:1024;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:98202;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:35:\"woocommerce-placeholder-150x150.png\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:4204;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:35:\"woocommerce-placeholder-768x768.png\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:60014;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(9,14,'_wp_attached_file','2023/01/sample_products.csv'),(10,14,'_wp_attachment_context','import'),(11,15,'_sku','woo-vneck-tee'),(12,15,'total_sales','0'),(13,15,'_tax_status','taxable'),(14,15,'_tax_class',''),(15,15,'_manage_stock','no'),(16,15,'_backorders','no'),(17,15,'_sold_individually','no'),(18,15,'_virtual','no'),(19,15,'_downloadable','no'),(20,15,'_download_limit','0'),(21,15,'_download_expiry','0'),(22,15,'_stock',NULL),(23,15,'_stock_status','instock'),(24,15,'_wc_average_rating','0'),(25,15,'_wc_review_count','0'),(26,15,'_product_version','7.2.3'),(28,16,'_sku','woo-hoodie'),(29,16,'total_sales','0'),(30,16,'_tax_status','taxable'),(31,16,'_tax_class',''),(32,16,'_manage_stock','no'),(33,16,'_backorders','no'),(34,16,'_sold_individually','no'),(35,16,'_virtual','no'),(36,16,'_downloadable','no'),(37,16,'_download_limit','0'),(38,16,'_download_expiry','0'),(39,16,'_stock',NULL),(40,16,'_stock_status','instock'),(41,16,'_wc_average_rating','0'),(42,16,'_wc_review_count','0'),(43,16,'_product_version','7.2.3'),(45,17,'_sku','woo-hoodie-with-logo'),(46,17,'total_sales','0'),(47,17,'_tax_status','taxable'),(48,17,'_tax_class',''),(49,17,'_manage_stock','no'),(50,17,'_backorders','no'),(51,17,'_sold_individually','no'),(52,17,'_virtual','no'),(53,17,'_downloadable','no'),(54,17,'_download_limit','0'),(55,17,'_download_expiry','0'),(56,17,'_stock',NULL),(57,17,'_stock_status','instock'),(58,17,'_wc_average_rating','0'),(59,17,'_wc_review_count','0'),(60,17,'_product_version','7.2.3'),(62,18,'_sku','woo-tshirt'),(63,18,'total_sales','0'),(64,18,'_tax_status','taxable'),(65,18,'_tax_class',''),(66,18,'_manage_stock','no'),(67,18,'_backorders','no'),(68,18,'_sold_individually','no'),(69,18,'_virtual','no'),(70,18,'_downloadable','no'),(71,18,'_download_limit','0'),(72,18,'_download_expiry','0'),(73,18,'_stock',NULL),(74,18,'_stock_status','instock'),(75,18,'_wc_average_rating','0'),(76,18,'_wc_review_count','0'),(77,18,'_product_version','7.2.3'),(79,19,'_sku','woo-beanie'),(80,19,'total_sales','0'),(81,19,'_tax_status','taxable'),(82,19,'_tax_class',''),(83,19,'_manage_stock','no'),(84,19,'_backorders','no'),(85,19,'_sold_individually','no'),(86,19,'_virtual','no'),(87,19,'_downloadable','no'),(88,19,'_download_limit','0'),(89,19,'_download_expiry','0'),(90,19,'_stock',NULL),(91,19,'_stock_status','instock'),(92,19,'_wc_average_rating','0'),(93,19,'_wc_review_count','0'),(94,19,'_product_version','7.2.3'),(96,20,'_sku','woo-belt'),(97,20,'total_sales','0'),(98,20,'_tax_status','taxable'),(99,20,'_tax_class',''),(100,20,'_manage_stock','no'),(101,20,'_backorders','no'),(102,20,'_sold_individually','no'),(103,20,'_virtual','no'),(104,20,'_downloadable','no'),(105,20,'_download_limit','0'),(106,20,'_download_expiry','0'),(107,20,'_stock',NULL),(108,20,'_stock_status','instock'),(109,20,'_wc_average_rating','0'),(110,20,'_wc_review_count','0'),(111,20,'_product_version','7.2.3'),(113,21,'_sku','woo-cap'),(114,21,'total_sales','0'),(115,21,'_tax_status','taxable'),(116,21,'_tax_class',''),(117,21,'_manage_stock','no'),(118,21,'_backorders','no'),(119,21,'_sold_individually','no'),(120,21,'_virtual','no'),(121,21,'_downloadable','no'),(122,21,'_download_limit','0'),(123,21,'_download_expiry','0'),(124,21,'_stock',NULL),(125,21,'_stock_status','instock'),(126,21,'_wc_average_rating','0'),(127,21,'_wc_review_count','0'),(128,21,'_product_version','7.2.3'),(130,22,'_sku','woo-sunglasses'),(131,22,'total_sales','0'),(132,22,'_tax_status','taxable'),(133,22,'_tax_class',''),(134,22,'_manage_stock','no'),(135,22,'_backorders','no'),(136,22,'_sold_individually','no'),(137,22,'_virtual','no'),(138,22,'_downloadable','no'),(139,22,'_download_limit','0'),(140,22,'_download_expiry','0'),(141,22,'_stock',NULL),(142,22,'_stock_status','instock'),(143,22,'_wc_average_rating','0'),(144,22,'_wc_review_count','0'),(145,22,'_product_version','7.2.3'),(147,23,'_sku','woo-hoodie-with-pocket'),(148,23,'total_sales','0'),(149,23,'_tax_status','taxable'),(150,23,'_tax_class',''),(151,23,'_manage_stock','no'),(152,23,'_backorders','no'),(153,23,'_sold_individually','no'),(154,23,'_virtual','no'),(155,23,'_downloadable','no'),(156,23,'_download_limit','0'),(157,23,'_download_expiry','0'),(158,23,'_stock',NULL),(159,23,'_stock_status','instock'),(160,23,'_wc_average_rating','0'),(161,23,'_wc_review_count','0'),(162,23,'_product_version','7.2.3'),(164,24,'_sku','woo-hoodie-with-zipper'),(165,24,'total_sales','0'),(166,24,'_tax_status','taxable'),(167,24,'_tax_class',''),(168,24,'_manage_stock','no'),(169,24,'_backorders','no'),(170,24,'_sold_individually','no'),(171,24,'_virtual','no'),(172,24,'_downloadable','no'),(173,24,'_download_limit','0'),(174,24,'_download_expiry','0'),(175,24,'_stock',NULL),(176,24,'_stock_status','instock'),(177,24,'_wc_average_rating','0'),(178,24,'_wc_review_count','0'),(179,24,'_product_version','7.2.3'),(181,25,'_sku','woo-long-sleeve-tee'),(182,25,'total_sales','0'),(183,25,'_tax_status','taxable'),(184,25,'_tax_class',''),(185,25,'_manage_stock','no'),(186,25,'_backorders','no'),(187,25,'_sold_individually','no'),(188,25,'_virtual','no'),(189,25,'_downloadable','no'),(190,25,'_download_limit','0'),(191,25,'_download_expiry','0'),(192,25,'_stock',NULL),(193,25,'_stock_status','instock'),(194,25,'_wc_average_rating','0'),(195,25,'_wc_review_count','0'),(196,25,'_product_version','7.2.3'),(198,26,'_sku','woo-polo'),(199,26,'total_sales','0'),(200,26,'_tax_status','taxable'),(201,26,'_tax_class',''),(202,26,'_manage_stock','no'),(203,26,'_backorders','no'),(204,26,'_sold_individually','no'),(205,26,'_virtual','no'),(206,26,'_downloadable','no'),(207,26,'_download_limit','0'),(208,26,'_download_expiry','0'),(209,26,'_stock',NULL),(210,26,'_stock_status','instock'),(211,26,'_wc_average_rating','0'),(212,26,'_wc_review_count','0'),(213,26,'_product_version','7.2.3'),(215,27,'_sku','woo-album'),(216,27,'total_sales','0'),(217,27,'_tax_status','taxable'),(218,27,'_tax_class',''),(219,27,'_manage_stock','no'),(220,27,'_backorders','no'),(221,27,'_sold_individually','no'),(222,27,'_virtual','yes'),(223,27,'_downloadable','yes'),(224,27,'_download_limit','1'),(225,27,'_download_expiry','1'),(226,27,'_stock',NULL),(227,27,'_stock_status','instock'),(228,27,'_wc_average_rating','0'),(229,27,'_wc_review_count','0'),(230,27,'_product_version','7.2.3'),(232,28,'_sku','woo-single'),(233,28,'total_sales','0'),(234,28,'_tax_status','taxable'),(235,28,'_tax_class',''),(236,28,'_manage_stock','no'),(237,28,'_backorders','no'),(238,28,'_sold_individually','no'),(239,28,'_virtual','yes'),(240,28,'_downloadable','yes'),(241,28,'_download_limit','1'),(242,28,'_download_expiry','1'),(243,28,'_stock',NULL),(244,28,'_stock_status','instock'),(245,28,'_wc_average_rating','0'),(246,28,'_wc_review_count','0'),(247,28,'_product_version','7.2.3'),(249,29,'_sku','woo-vneck-tee-red'),(250,29,'total_sales','0'),(251,29,'_tax_status','taxable'),(252,29,'_tax_class',''),(253,29,'_manage_stock','no'),(254,29,'_backorders','no'),(255,29,'_sold_individually','no'),(256,29,'_virtual','no'),(257,29,'_downloadable','no'),(258,29,'_download_limit','0'),(259,29,'_download_expiry','0'),(260,29,'_stock',NULL),(261,29,'_stock_status','instock'),(262,29,'_wc_average_rating','0'),(263,29,'_wc_review_count','0'),(264,29,'_product_version','7.2.3'),(266,30,'_sku','woo-vneck-tee-green'),(267,30,'total_sales','0'),(268,30,'_tax_status','taxable'),(269,30,'_tax_class',''),(270,30,'_manage_stock','no'),(271,30,'_backorders','no'),(272,30,'_sold_individually','no'),(273,30,'_virtual','no'),(274,30,'_downloadable','no'),(275,30,'_download_limit','0'),(276,30,'_download_expiry','0'),(277,30,'_stock',NULL),(278,30,'_stock_status','instock'),(279,30,'_wc_average_rating','0'),(280,30,'_wc_review_count','0'),(281,30,'_product_version','7.2.3'),(283,31,'_sku','woo-vneck-tee-blue'),(284,31,'total_sales','0'),(285,31,'_tax_status','taxable'),(286,31,'_tax_class',''),(287,31,'_manage_stock','no'),(288,31,'_backorders','no'),(289,31,'_sold_individually','no'),(290,31,'_virtual','no'),(291,31,'_downloadable','no'),(292,31,'_download_limit','0'),(293,31,'_download_expiry','0'),(294,31,'_stock',NULL),(295,31,'_stock_status','instock'),(296,31,'_wc_average_rating','0'),(297,31,'_wc_review_count','0'),(298,31,'_product_version','7.2.3'),(300,32,'_sku','woo-hoodie-red'),(301,32,'total_sales','0'),(302,32,'_tax_status','taxable'),(303,32,'_tax_class',''),(304,32,'_manage_stock','no'),(305,32,'_backorders','no'),(306,32,'_sold_individually','no'),(307,32,'_virtual','no'),(308,32,'_downloadable','no'),(309,32,'_download_limit','0'),(310,32,'_download_expiry','0'),(311,32,'_stock',NULL),(312,32,'_stock_status','instock'),(313,32,'_wc_average_rating','0'),(314,32,'_wc_review_count','0'),(315,32,'_product_version','7.2.3'),(317,33,'_sku','woo-hoodie-green'),(318,33,'total_sales','0'),(319,33,'_tax_status','taxable'),(320,33,'_tax_class',''),(321,33,'_manage_stock','no'),(322,33,'_backorders','no'),(323,33,'_sold_individually','no'),(324,33,'_virtual','no'),(325,33,'_downloadable','no'),(326,33,'_download_limit','0'),(327,33,'_download_expiry','0'),(328,33,'_stock',NULL),(329,33,'_stock_status','instock'),(330,33,'_wc_average_rating','0'),(331,33,'_wc_review_count','0'),(332,33,'_product_version','7.2.3'),(334,34,'_sku','woo-hoodie-blue'),(335,34,'total_sales','0'),(336,34,'_tax_status','taxable'),(337,34,'_tax_class',''),(338,34,'_manage_stock','no'),(339,34,'_backorders','no'),(340,34,'_sold_individually','no'),(341,34,'_virtual','no'),(342,34,'_downloadable','no'),(343,34,'_download_limit','0'),(344,34,'_download_expiry','0'),(345,34,'_stock',NULL),(346,34,'_stock_status','instock'),(347,34,'_wc_average_rating','0'),(348,34,'_wc_review_count','0'),(349,34,'_product_version','7.2.3'),(351,35,'_sku','Woo-tshirt-logo'),(352,35,'total_sales','0'),(353,35,'_tax_status','taxable'),(354,35,'_tax_class',''),(355,35,'_manage_stock','no'),(356,35,'_backorders','no'),(357,35,'_sold_individually','no'),(358,35,'_virtual','no'),(359,35,'_downloadable','no'),(360,35,'_download_limit','0'),(361,35,'_download_expiry','0'),(362,35,'_stock',NULL),(363,35,'_stock_status','instock'),(364,35,'_wc_average_rating','0'),(365,35,'_wc_review_count','0'),(366,35,'_product_version','7.2.3'),(368,36,'_sku','Woo-beanie-logo'),(369,36,'total_sales','0'),(370,36,'_tax_status','taxable'),(371,36,'_tax_class',''),(372,36,'_manage_stock','no'),(373,36,'_backorders','no'),(374,36,'_sold_individually','no'),(375,36,'_virtual','no'),(376,36,'_downloadable','no'),(377,36,'_download_limit','0'),(378,36,'_download_expiry','0'),(379,36,'_stock',NULL),(380,36,'_stock_status','instock'),(381,36,'_wc_average_rating','0'),(382,36,'_wc_review_count','0'),(383,36,'_product_version','7.2.3'),(385,37,'_sku','logo-collection'),(386,37,'total_sales','0'),(387,37,'_tax_status','taxable'),(388,37,'_tax_class',''),(389,37,'_manage_stock','no'),(390,37,'_backorders','no'),(391,37,'_sold_individually','no'),(392,37,'_virtual','no'),(393,37,'_downloadable','no'),(394,37,'_download_limit','0'),(395,37,'_download_expiry','0'),(396,37,'_stock',NULL),(397,37,'_stock_status','instock'),(398,37,'_wc_average_rating','0'),(399,37,'_wc_review_count','0'),(400,37,'_product_version','7.2.3'),(402,38,'_sku','wp-pennant'),(403,38,'total_sales','0'),(404,38,'_tax_status','taxable'),(405,38,'_tax_class',''),(406,38,'_manage_stock','no'),(407,38,'_backorders','no'),(408,38,'_sold_individually','no'),(409,38,'_virtual','no'),(410,38,'_downloadable','no'),(411,38,'_download_limit','0'),(412,38,'_download_expiry','0'),(413,38,'_stock',NULL),(414,38,'_stock_status','instock'),(415,38,'_wc_average_rating','0'),(416,38,'_wc_review_count','0'),(417,38,'_product_version','7.2.3'),(419,39,'_sku','woo-hoodie-blue-logo'),(420,39,'total_sales','0'),(421,39,'_tax_status','taxable'),(422,39,'_tax_class',''),(423,39,'_manage_stock','no'),(424,39,'_backorders','no'),(425,39,'_sold_individually','no'),(426,39,'_virtual','no'),(427,39,'_downloadable','no'),(428,39,'_download_limit','0'),(429,39,'_download_expiry','0'),(430,39,'_stock',NULL),(431,39,'_stock_status','instock'),(432,39,'_wc_average_rating','0'),(433,39,'_wc_review_count','0'),(434,39,'_product_version','7.2.3'),(436,40,'_wp_attached_file','2023/01/vneck-tee-2.jpg'),(437,40,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:800;s:4:\"file\";s:23:\"2023/01/vneck-tee-2.jpg\";s:8:\"filesize\";i:49497;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:23:\"vneck-tee-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:7858;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:23:\"vneck-tee-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:3136;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:23:\"vneck-tee-2-768x767.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:767;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:29329;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:23:\"vneck-tee-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:14021;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:23:\"vneck-tee-2-600x599.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:599;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:20707;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:23:\"vneck-tee-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1982;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(438,40,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/vneck-tee-2.jpg'),(439,41,'_wp_attached_file','2023/01/vnech-tee-green-1.jpg'),(440,41,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:800;s:4:\"file\";s:29:\"2023/01/vnech-tee-green-1.jpg\";s:8:\"filesize\";i:102362;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:29:\"vnech-tee-green-1-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:7291;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:29:\"vnech-tee-green-1-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2835;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:29:\"vnech-tee-green-1-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:28479;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:29:\"vnech-tee-green-1-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:13394;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:29:\"vnech-tee-green-1-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:20269;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:29:\"vnech-tee-green-1-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1814;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(441,41,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/vnech-tee-green-1.jpg'),(442,42,'_wp_attached_file','2023/01/vnech-tee-blue-1.jpg'),(443,42,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:800;s:4:\"file\";s:28:\"2023/01/vnech-tee-blue-1.jpg\";s:8:\"filesize\";i:120226;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:28:\"vnech-tee-blue-1-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:7685;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:28:\"vnech-tee-blue-1-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2987;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:28:\"vnech-tee-blue-1-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:30155;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:28:\"vnech-tee-blue-1-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:14199;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:28:\"vnech-tee-blue-1-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:21442;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:28:\"vnech-tee-blue-1-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1879;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(444,42,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/vnech-tee-blue-1.jpg'),(445,15,'_wpcom_is_markdown','1'),(446,15,'_wp_old_slug','import-placeholder-for-44'),(447,15,'_product_image_gallery','41,42'),(448,15,'_thumbnail_id','40'),(449,15,'_product_attributes','a:2:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:1;s:11:\"is_taxonomy\";i:1;}s:7:\"pa_size\";a:6:{s:4:\"name\";s:7:\"pa_size\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:1;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:1;s:11:\"is_taxonomy\";i:1;}}'),(450,43,'_wp_attached_file','2023/01/hoodie-2.jpg'),(451,43,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:801;s:4:\"file\";s:20:\"2023/01/hoodie-2.jpg\";s:8:\"filesize\";i:46079;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:20:\"hoodie-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:7951;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:20:\"hoodie-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:3121;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:20:\"hoodie-2-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:29085;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:20:\"hoodie-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:14066;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:20:\"hoodie-2-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:20490;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:20:\"hoodie-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1974;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(452,43,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/hoodie-2.jpg'),(453,44,'_wp_attached_file','2023/01/hoodie-blue-1.jpg'),(454,44,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:800;s:4:\"file\";s:25:\"2023/01/hoodie-blue-1.jpg\";s:8:\"filesize\";i:101298;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:25:\"hoodie-blue-1-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:7690;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:25:\"hoodie-blue-1-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2917;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:25:\"hoodie-blue-1-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:29070;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:25:\"hoodie-blue-1-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:13858;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:25:\"hoodie-blue-1-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:20761;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:25:\"hoodie-blue-1-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1805;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(455,44,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/hoodie-blue-1.jpg'),(456,45,'_wp_attached_file','2023/01/hoodie-green-1.jpg'),(457,45,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:800;s:4:\"file\";s:26:\"2023/01/hoodie-green-1.jpg\";s:8:\"filesize\";i:98498;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:26:\"hoodie-green-1-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:7576;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:26:\"hoodie-green-1-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2902;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:26:\"hoodie-green-1-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:28529;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:26:\"hoodie-green-1-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:13550;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:26:\"hoodie-green-1-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:20379;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:26:\"hoodie-green-1-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1823;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(458,45,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/hoodie-green-1.jpg'),(459,46,'_wp_attached_file','2023/01/hoodie-with-logo-2.jpg'),(460,46,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:801;s:4:\"file\";s:30:\"2023/01/hoodie-with-logo-2.jpg\";s:8:\"filesize\";i:46969;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:30:\"hoodie-with-logo-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:8250;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:30:\"hoodie-with-logo-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:3091;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:30:\"hoodie-with-logo-2-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:30122;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:30:\"hoodie-with-logo-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:14733;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:30:\"hoodie-with-logo-2-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:21582;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:30:\"hoodie-with-logo-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1913;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(461,46,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/hoodie-with-logo-2.jpg'),(462,16,'_wpcom_is_markdown','1'),(463,16,'_wp_old_slug','import-placeholder-for-45'),(464,16,'_product_image_gallery','44,45,46'),(465,16,'_thumbnail_id','43'),(466,16,'_product_attributes','a:2:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:1;s:11:\"is_taxonomy\";i:1;}s:4:\"logo\";a:6:{s:4:\"name\";s:4:\"Logo\";s:5:\"value\";s:8:\"Yes | No\";s:8:\"position\";i:1;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:1;s:11:\"is_taxonomy\";i:0;}}'),(467,17,'_wpcom_is_markdown','1'),(468,17,'_wp_old_slug','import-placeholder-for-46'),(469,17,'_regular_price','45'),(470,17,'_thumbnail_id','46'),(471,17,'_product_attributes','a:1:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:0;s:11:\"is_taxonomy\";i:1;}}'),(472,17,'_price','45'),(473,47,'_wp_attached_file','2023/01/tshirt-2.jpg'),(474,47,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:801;s:4:\"file\";s:20:\"2023/01/tshirt-2.jpg\";s:8:\"filesize\";i:41155;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:20:\"tshirt-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:7134;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:20:\"tshirt-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2793;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:20:\"tshirt-2-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:26448;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:20:\"tshirt-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:12591;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:20:\"tshirt-2-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:18800;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:20:\"tshirt-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1766;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(475,47,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/tshirt-2.jpg'),(476,18,'_wpcom_is_markdown','1'),(477,18,'_wp_old_slug','import-placeholder-for-47'),(478,18,'_regular_price','18'),(479,18,'_thumbnail_id','47'),(480,18,'_product_attributes','a:1:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:0;s:11:\"is_taxonomy\";i:1;}}'),(481,18,'_price','18'),(482,48,'_wp_attached_file','2023/01/beanie-2.jpg'),(483,48,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:801;s:4:\"file\";s:20:\"2023/01/beanie-2.jpg\";s:8:\"filesize\";i:31568;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:20:\"beanie-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:5695;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:20:\"beanie-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2447;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:20:\"beanie-2-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:21231;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:20:\"beanie-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:9923;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:20:\"beanie-2-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:15022;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:20:\"beanie-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1703;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(484,48,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/beanie-2.jpg'),(485,19,'_wpcom_is_markdown','1'),(486,19,'_wp_old_slug','import-placeholder-for-48'),(487,19,'_regular_price','20'),(488,19,'_sale_price','18'),(489,19,'_thumbnail_id','48'),(490,19,'_product_attributes','a:1:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:0;s:11:\"is_taxonomy\";i:1;}}'),(491,19,'_price','18'),(492,49,'_wp_attached_file','2023/01/belt-2.jpg'),(493,49,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:801;s:4:\"file\";s:18:\"2023/01/belt-2.jpg\";s:8:\"filesize\";i:37339;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:18:\"belt-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:6738;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:18:\"belt-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2681;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:18:\"belt-2-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:25625;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:18:\"belt-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:12111;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:18:\"belt-2-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:17802;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:18:\"belt-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1713;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(494,49,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/belt-2.jpg'),(495,20,'_wpcom_is_markdown','1'),(496,20,'_wp_old_slug','import-placeholder-for-58'),(497,20,'_regular_price','65'),(498,20,'_sale_price','55'),(499,20,'_thumbnail_id','49'),(500,20,'_price','55'),(501,50,'_wp_attached_file','2023/01/cap-2.jpg'),(502,50,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:801;s:4:\"file\";s:17:\"2023/01/cap-2.jpg\";s:8:\"filesize\";i:37675;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:17:\"cap-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:6656;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:17:\"cap-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2559;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:17:\"cap-2-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:25713;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:17:\"cap-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:12271;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:17:\"cap-2-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:17984;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:17:\"cap-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1654;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(503,50,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/cap-2.jpg'),(504,21,'_wpcom_is_markdown','1'),(505,21,'_wp_old_slug','import-placeholder-for-60'),(506,21,'_regular_price','18'),(507,21,'_sale_price','16'),(508,21,'_thumbnail_id','50'),(509,21,'_product_attributes','a:1:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:0;s:11:\"is_taxonomy\";i:1;}}'),(510,21,'_price','16'),(511,51,'_wp_attached_file','2023/01/sunglasses-2.jpg'),(512,51,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:801;s:4:\"file\";s:24:\"2023/01/sunglasses-2.jpg\";s:8:\"filesize\";i:24691;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:24:\"sunglasses-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:5341;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:24:\"sunglasses-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2242;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:24:\"sunglasses-2-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:20643;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:24:\"sunglasses-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:9630;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:24:\"sunglasses-2-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:14479;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:24:\"sunglasses-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1509;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(513,51,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/sunglasses-2.jpg'),(514,22,'_wpcom_is_markdown','1'),(515,22,'_wp_old_slug','import-placeholder-for-62'),(516,22,'_regular_price','90'),(517,22,'_thumbnail_id','51'),(518,22,'_price','90'),(519,52,'_wp_attached_file','2023/01/hoodie-with-pocket-2.jpg'),(520,52,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:801;s:4:\"file\";s:32:\"2023/01/hoodie-with-pocket-2.jpg\";s:8:\"filesize\";i:43268;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:32:\"hoodie-with-pocket-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:7984;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:32:\"hoodie-with-pocket-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:3018;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:32:\"hoodie-with-pocket-2-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:28839;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:32:\"hoodie-with-pocket-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:13835;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:32:\"hoodie-with-pocket-2-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:20472;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:32:\"hoodie-with-pocket-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1890;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(521,52,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/hoodie-with-pocket-2.jpg'),(522,23,'_wpcom_is_markdown','1'),(523,23,'_wp_old_slug','import-placeholder-for-64'),(524,23,'_regular_price','45'),(525,23,'_sale_price','35'),(526,23,'_thumbnail_id','52'),(527,23,'_product_attributes','a:1:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:0;s:11:\"is_taxonomy\";i:1;}}'),(528,23,'_price','35'),(529,53,'_wp_attached_file','2023/01/hoodie-with-zipper-2.jpg'),(530,53,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:800;s:4:\"file\";s:32:\"2023/01/hoodie-with-zipper-2.jpg\";s:8:\"filesize\";i:56609;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:32:\"hoodie-with-zipper-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:9282;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:32:\"hoodie-with-zipper-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:3611;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:32:\"hoodie-with-zipper-2-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:33937;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:32:\"hoodie-with-zipper-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:16441;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:32:\"hoodie-with-zipper-2-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:24427;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:32:\"hoodie-with-zipper-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2148;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(531,53,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/hoodie-with-zipper-2.jpg'),(532,24,'_wpcom_is_markdown','1'),(533,24,'_wp_old_slug','import-placeholder-for-66'),(534,24,'_regular_price','45'),(535,24,'_thumbnail_id','53'),(536,24,'_price','45'),(537,54,'_wp_attached_file','2023/01/long-sleeve-tee-2.jpg'),(538,54,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:801;s:4:\"file\";s:29:\"2023/01/long-sleeve-tee-2.jpg\";s:8:\"filesize\";i:51118;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:29:\"long-sleeve-tee-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:8080;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:29:\"long-sleeve-tee-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:3300;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:29:\"long-sleeve-tee-2-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:29718;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:29:\"long-sleeve-tee-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:14092;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:29:\"long-sleeve-tee-2-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:20969;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:29:\"long-sleeve-tee-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1988;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(539,54,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/long-sleeve-tee-2.jpg'),(540,25,'_wpcom_is_markdown','1'),(541,25,'_wp_old_slug','import-placeholder-for-68'),(542,25,'_regular_price','25'),(543,25,'_thumbnail_id','54'),(544,25,'_product_attributes','a:1:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:0;s:11:\"is_taxonomy\";i:1;}}'),(545,25,'_price','25'),(546,55,'_wp_attached_file','2023/01/polo-2.jpg'),(547,55,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:801;s:6:\"height\";i:800;s:4:\"file\";s:18:\"2023/01/polo-2.jpg\";s:8:\"filesize\";i:44409;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:18:\"polo-2-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:7279;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:18:\"polo-2-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2867;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:18:\"polo-2-768x767.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:767;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:27582;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:18:\"polo-2-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:13208;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:18:\"polo-2-600x599.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:599;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:19453;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:18:\"polo-2-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1814;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(548,55,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/polo-2.jpg'),(549,26,'_wpcom_is_markdown','1'),(550,26,'_wp_old_slug','import-placeholder-for-70'),(551,26,'_regular_price','20'),(552,26,'_thumbnail_id','55'),(553,26,'_product_attributes','a:1:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:0;s:11:\"is_taxonomy\";i:1;}}'),(554,26,'_price','20'),(555,56,'_wp_attached_file','2023/01/album-1.jpg'),(556,56,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:800;s:4:\"file\";s:19:\"2023/01/album-1.jpg\";s:8:\"filesize\";i:120010;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:19:\"album-1-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:9469;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:19:\"album-1-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:3661;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:19:\"album-1-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:33648;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:19:\"album-1-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:16339;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:19:\"album-1-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:24374;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:19:\"album-1-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2219;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(557,56,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2022/05/album-1.jpg'),(558,27,'_wpcom_is_markdown','1'),(559,27,'_wp_old_slug','import-placeholder-for-73'),(560,27,'_regular_price','15'),(561,27,'_thumbnail_id','56'),(562,27,'_downloadable_files','a:2:{s:36:\"6f09308f-fb42-4693-8244-fd0b038a553e\";a:4:{s:2:\"id\";s:36:\"6f09308f-fb42-4693-8244-fd0b038a553e\";s:4:\"name\";s:8:\"Single 1\";s:4:\"file\";s:85:\"https://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2017/08/single.jpg\";s:7:\"enabled\";b:1;}s:36:\"a16a0f20-3983-4d1f-91d3-ad7de6c7496a\";a:4:{s:2:\"id\";s:36:\"a16a0f20-3983-4d1f-91d3-ad7de6c7496a\";s:4:\"name\";s:8:\"Single 2\";s:4:\"file\";s:84:\"https://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2017/08/album.jpg\";s:7:\"enabled\";b:1;}}'),(563,27,'_price','15'),(564,57,'_wp_attached_file','2023/01/single-1.jpg'),(565,57,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:800;s:4:\"file\";s:20:\"2023/01/single-1.jpg\";s:8:\"filesize\";i:124720;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:20:\"single-1-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:9579;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:20:\"single-1-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:3736;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:20:\"single-1-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:34155;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:20:\"single-1-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:16494;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:20:\"single-1-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:24429;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:20:\"single-1-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2274;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(566,57,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/single-1.jpg'),(567,28,'_wpcom_is_markdown','1'),(568,28,'_wp_old_slug','import-placeholder-for-75'),(569,28,'_regular_price','3'),(570,28,'_sale_price','2'),(571,28,'_thumbnail_id','57'),(572,28,'_downloadable_files','a:1:{s:36:\"7cecd641-c03a-4074-b9b0-8338a5259b7d\";a:4:{s:2:\"id\";s:36:\"7cecd641-c03a-4074-b9b0-8338a5259b7d\";s:4:\"name\";s:6:\"Single\";s:4:\"file\";s:85:\"https://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2017/08/single.jpg\";s:7:\"enabled\";b:1;}}'),(573,28,'_price','2'),(574,29,'_wpcom_is_markdown',''),(575,29,'_wp_old_slug','import-placeholder-for-76'),(576,29,'_variation_description','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum sagittis orci ac odio dictum tincidunt. Donec ut metus leo. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Sed luctus, dui eu sagittis sodales, nulla nibh sagittis augue, vel porttitor diam enim non metus. Vestibulum aliquam augue neque. Phasellus tincidunt odio eget ullamcorper efficitur. Cras placerat ut turpis pellentesque vulputate. Nam sed consequat tortor. Curabitur finibus sapien dolor. Ut eleifend tellus nec erat pulvinar dignissim. Nam non arcu purus. Vivamus et massa massa.'),(577,29,'_regular_price','20'),(578,29,'_thumbnail_id','40'),(579,29,'attribute_pa_color','red'),(580,29,'attribute_pa_size',''),(581,29,'_price','20'),(582,30,'_wpcom_is_markdown',''),(583,30,'_wp_old_slug','import-placeholder-for-77'),(584,30,'_variation_description','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum sagittis orci ac odio dictum tincidunt. Donec ut metus leo. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Sed luctus, dui eu sagittis sodales, nulla nibh sagittis augue, vel porttitor diam enim non metus. Vestibulum aliquam augue neque. Phasellus tincidunt odio eget ullamcorper efficitur. Cras placerat ut turpis pellentesque vulputate. Nam sed consequat tortor. Curabitur finibus sapien dolor. Ut eleifend tellus nec erat pulvinar dignissim. Nam non arcu purus. Vivamus et massa massa.'),(585,30,'_regular_price','20'),(586,30,'_thumbnail_id','41'),(587,30,'attribute_pa_color','green'),(588,30,'attribute_pa_size',''),(589,30,'_price','20'),(590,31,'_wpcom_is_markdown',''),(591,31,'_wp_old_slug','import-placeholder-for-78'),(592,31,'_variation_description','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum sagittis orci ac odio dictum tincidunt. Donec ut metus leo. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Sed luctus, dui eu sagittis sodales, nulla nibh sagittis augue, vel porttitor diam enim non metus. Vestibulum aliquam augue neque. Phasellus tincidunt odio eget ullamcorper efficitur. Cras placerat ut turpis pellentesque vulputate. Nam sed consequat tortor. Curabitur finibus sapien dolor. Ut eleifend tellus nec erat pulvinar dignissim. Nam non arcu purus. Vivamus et massa massa.'),(593,31,'_regular_price','15'),(594,31,'_thumbnail_id','42'),(595,31,'attribute_pa_color','blue'),(596,31,'attribute_pa_size',''),(597,31,'_price','15'),(598,32,'_wpcom_is_markdown',''),(599,32,'_wp_old_slug','import-placeholder-for-79'),(600,32,'_variation_description','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum sagittis orci ac odio dictum tincidunt. Donec ut metus leo. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Sed luctus, dui eu sagittis sodales, nulla nibh sagittis augue, vel porttitor diam enim non metus. Vestibulum aliquam augue neque. Phasellus tincidunt odio eget ullamcorper efficitur. Cras placerat ut turpis pellentesque vulputate. Nam sed consequat tortor. Curabitur finibus sapien dolor. Ut eleifend tellus nec erat pulvinar dignissim. Nam non arcu purus. Vivamus et massa massa.'),(601,32,'_regular_price','45'),(602,32,'_sale_price','42'),(603,32,'_thumbnail_id','43'),(604,32,'attribute_pa_color','red'),(605,32,'attribute_logo','No'),(606,32,'_price','42'),(607,33,'_wpcom_is_markdown',''),(608,33,'_wp_old_slug','import-placeholder-for-80'),(609,33,'_variation_description','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum sagittis orci ac odio dictum tincidunt. Donec ut metus leo. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Sed luctus, dui eu sagittis sodales, nulla nibh sagittis augue, vel porttitor diam enim non metus. Vestibulum aliquam augue neque. Phasellus tincidunt odio eget ullamcorper efficitur. Cras placerat ut turpis pellentesque vulputate. Nam sed consequat tortor. Curabitur finibus sapien dolor. Ut eleifend tellus nec erat pulvinar dignissim. Nam non arcu purus. Vivamus et massa massa.'),(610,33,'_regular_price','45'),(611,33,'_thumbnail_id','45'),(612,33,'attribute_pa_color','green'),(613,33,'attribute_logo','No'),(614,33,'_price','45'),(615,34,'_wpcom_is_markdown',''),(616,34,'_wp_old_slug','import-placeholder-for-81'),(617,34,'_variation_description','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum sagittis orci ac odio dictum tincidunt. Donec ut metus leo. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Sed luctus, dui eu sagittis sodales, nulla nibh sagittis augue, vel porttitor diam enim non metus. Vestibulum aliquam augue neque. Phasellus tincidunt odio eget ullamcorper efficitur. Cras placerat ut turpis pellentesque vulputate. Nam sed consequat tortor. Curabitur finibus sapien dolor. Ut eleifend tellus nec erat pulvinar dignissim. Nam non arcu purus. Vivamus et massa massa.'),(618,34,'_regular_price','45'),(619,34,'_thumbnail_id','44'),(620,34,'attribute_pa_color','blue'),(621,34,'attribute_logo','No'),(622,34,'_price','45'),(623,58,'_wp_attached_file','2023/01/t-shirt-with-logo-1.jpg'),(624,58,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:800;s:4:\"file\";s:31:\"2023/01/t-shirt-with-logo-1.jpg\";s:8:\"filesize\";i:67833;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:31:\"t-shirt-with-logo-1-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:8141;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:31:\"t-shirt-with-logo-1-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:3143;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:31:\"t-shirt-with-logo-1-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:30507;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:31:\"t-shirt-with-logo-1-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:14700;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:31:\"t-shirt-with-logo-1-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:21841;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:31:\"t-shirt-with-logo-1-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1963;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(625,58,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/t-shirt-with-logo-1.jpg'),(626,35,'_wpcom_is_markdown','1'),(627,35,'_wp_old_slug','import-placeholder-for-83'),(628,35,'_regular_price','18'),(629,35,'_thumbnail_id','58'),(630,35,'_product_attributes','a:1:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:0;s:11:\"is_taxonomy\";i:1;}}'),(631,35,'_price','18'),(632,59,'_wp_attached_file','2023/01/beanie-with-logo-1.jpg'),(633,59,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:800;s:4:\"file\";s:30:\"2023/01/beanie-with-logo-1.jpg\";s:8:\"filesize\";i:45371;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:30:\"beanie-with-logo-1-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:5814;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:30:\"beanie-with-logo-1-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2428;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:30:\"beanie-with-logo-1-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:21616;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:30:\"beanie-with-logo-1-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:10311;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:30:\"beanie-with-logo-1-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:15342;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:30:\"beanie-with-logo-1-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1672;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(634,59,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/beanie-with-logo-1.jpg'),(635,36,'_wpcom_is_markdown','1'),(636,36,'_wp_old_slug','import-placeholder-for-85'),(637,36,'_regular_price','20'),(638,36,'_sale_price','18'),(639,36,'_thumbnail_id','59'),(640,36,'_product_attributes','a:1:{s:8:\"pa_color\";a:6:{s:4:\"name\";s:8:\"pa_color\";s:5:\"value\";s:0:\"\";s:8:\"position\";i:0;s:10:\"is_visible\";i:1;s:12:\"is_variation\";i:0;s:11:\"is_taxonomy\";i:1;}}'),(641,36,'_price','18'),(642,15,'_price','15'),(643,15,'_price','20'),(646,60,'_wp_attached_file','2023/01/logo-1.jpg'),(647,60,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:799;s:4:\"file\";s:18:\"2023/01/logo-1.jpg\";s:8:\"filesize\";i:139907;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:18:\"logo-1-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:16171;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:18:\"logo-1-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:5876;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:18:\"logo-1-768x767.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:767;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:56868;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:18:\"logo-1-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:28386;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:18:\"logo-1-600x599.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:599;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:41259;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:18:\"logo-1-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:3353;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(648,60,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/logo-1.jpg'),(649,37,'_wpcom_is_markdown','1'),(650,37,'_wp_old_slug','import-placeholder-for-87'),(651,37,'_children','a:3:{i:0;i:17;i:1;i:18;i:2;i:19;}'),(652,37,'_product_image_gallery','59,58,46'),(653,37,'_thumbnail_id','60'),(654,37,'_price','18'),(655,37,'_price','45'),(656,61,'_wp_attached_file','2023/01/pennant-1.jpg'),(657,61,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:800;s:4:\"file\";s:21:\"2023/01/pennant-1.jpg\";s:8:\"filesize\";i:56755;s:5:\"sizes\";a:6:{s:6:\"medium\";a:5:{s:4:\"file\";s:21:\"pennant-1-300x300.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:300;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:6928;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:21:\"pennant-1-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:2581;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:21:\"pennant-1-768x768.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:768;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:28253;}s:21:\"woocommerce_thumbnail\";a:6:{s:4:\"file\";s:21:\"pennant-1-450x450.jpg\";s:5:\"width\";i:450;s:6:\"height\";i:450;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:12984;s:9:\"uncropped\";b:0;}s:18:\"woocommerce_single\";a:5:{s:4:\"file\";s:21:\"pennant-1-600x600.jpg\";s:5:\"width\";i:600;s:6:\"height\";i:600;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:19854;}s:29:\"woocommerce_gallery_thumbnail\";a:5:{s:4:\"file\";s:21:\"pennant-1-100x100.jpg\";s:5:\"width\";i:100;s:6:\"height\";i:100;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:1608;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),(658,61,'_wc_attachment_source','https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/pennant-1.jpg'),(659,38,'_wpcom_is_markdown','1'),(660,38,'_wp_old_slug','import-placeholder-for-89'),(661,38,'_regular_price','11.05'),(662,38,'_thumbnail_id','61'),(663,38,'_product_url','https://mercantile.wordpress.org/product/wordpress-pennant/'),(664,38,'_button_text','Buy on the WordPress swag store!'),(665,38,'_price','11.05'),(666,39,'_wpcom_is_markdown',''),(667,39,'_wp_old_slug','import-placeholder-for-90'),(668,39,'_variation_description','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum sagittis orci ac odio dictum tincidunt. Donec ut metus leo. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Sed luctus, dui eu sagittis sodales, nulla nibh sagittis augue, vel porttitor diam enim non metus. Vestibulum aliquam augue neque. Phasellus tincidunt odio eget ullamcorper efficitur. Cras placerat ut turpis pellentesque vulputate. Nam sed consequat tortor. Curabitur finibus sapien dolor. Ut eleifend tellus nec erat pulvinar dignissim. Nam non arcu purus. Vivamus et massa massa.'),(669,39,'_regular_price','45'),(670,39,'_thumbnail_id','46'),(671,39,'attribute_pa_color','blue'),(672,39,'attribute_logo','Yes'),(673,39,'_price','45'),(674,16,'_price','42'),(675,16,'_price','45');
/*!40000 ALTER TABLE `wp_postmeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_posts`
--

DROP TABLE IF EXISTS `wp_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_posts` (
  `ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_author` bigint unsigned NOT NULL DEFAULT '0',
  `post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_title` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_excerpt` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'publish',
  `comment_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'open',
  `ping_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'open',
  `post_password` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `post_name` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `to_ping` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `pinged` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content_filtered` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_parent` bigint unsigned NOT NULL DEFAULT '0',
  `guid` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `menu_order` int NOT NULL DEFAULT '0',
  `post_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'post',
  `post_mime_type` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_count` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `post_name` (`post_name`(191)),
  KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
  KEY `post_parent` (`post_parent`),
  KEY `post_author` (`post_author`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_posts`
--

LOCK TABLES `wp_posts` WRITE;
/*!40000 ALTER TABLE `wp_posts` DISABLE KEYS */;
INSERT INTO `wp_posts` VALUES (6,1,'2023-01-11 11:26:01','0000-00-00 00:00:00','','Auto Draft','','auto-draft','open','open','','','','','2023-01-11 11:26:01','0000-00-00 00:00:00','',0,'https://fresh.test/?p=6',0,'post','',0),(7,1,'2023-01-11 11:29:58','2023-01-11 11:29:58','','woocommerce-placeholder','','inherit','open','closed','','woocommerce-placeholder','','','2023-01-11 11:29:58','2023-01-11 11:29:58','',0,'http://fresh.test/wp-content/uploads/2023/01/woocommerce-placeholder.png',0,'attachment','image/png',0),(8,1,'2023-01-11 11:29:59','2023-01-11 11:29:59','','Shop','','publish','closed','closed','','shop','','','2023-01-11 11:29:59','2023-01-11 11:29:59','',0,'https://fresh.test/?page_id=8',0,'page','',0),(9,1,'2023-01-11 11:29:59','2023-01-11 11:29:59','<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->','Cart','','publish','closed','closed','','cart','','','2023-01-11 11:29:59','2023-01-11 11:29:59','',0,'https://fresh.test/?page_id=9',0,'page','',0),(10,1,'2023-01-11 11:29:59','2023-01-11 11:29:59','<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->','Checkout','','publish','closed','closed','','checkout','','','2023-01-11 11:29:59','2023-01-11 11:29:59','',0,'https://fresh.test/?page_id=10',0,'page','',0),(11,1,'2023-01-11 11:29:59','2023-01-11 11:29:59','<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->','My account','','publish','closed','closed','','my-account','','','2023-01-11 11:29:59','2023-01-11 11:29:59','',0,'https://fresh.test/?page_id=11',0,'page','',0),(12,1,'2023-01-11 11:29:59','0000-00-00 00:00:00','<!-- wp:paragraph -->\n<p><b>This is a sample page.</b></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<h3>Overview</h3>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Our refund and returns policy lasts 30 days. If 30 days have passed since your purchase, we can’t offer you a full refund or exchange.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>To be eligible for a return, your item must be unused and in the same condition that you received it. It must also be in the original packaging.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Several types of goods are exempt from being returned. Perishable goods such as food, flowers, newspapers or magazines cannot be returned. We also do not accept products that are intimate or sanitary goods, hazardous materials, or flammable liquids or gases.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Additional non-returnable items:</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul>\n<li>Gift cards</li>\n<li>Downloadable software products</li>\n<li>Some health and personal care items</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p>To complete your return, we require a receipt or proof of purchase.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Please do not send your purchase back to the manufacturer.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>There are certain situations where only partial refunds are granted:</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul>\n<li>Book with obvious signs of use</li>\n<li>CD, DVD, VHS tape, software, video game, cassette tape, or vinyl record that has been opened.</li>\n<li>Any item not in its original condition, is damaged or missing parts for reasons not due to our error.</li>\n<li>Any item that is returned more than 30 days after delivery</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<h2>Refunds</h2>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Once your return is received and inspected, we will send you an email to notify you that we have received your returned item. We will also notify you of the approval or rejection of your refund.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>If you are approved, then your refund will be processed, and a credit will automatically be applied to your credit card or original method of payment, within a certain amount of days.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<b>Late or missing refunds</b>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>If you haven’t received a refund yet, first check your bank account again.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Then contact your credit card company, it may take some time before your refund is officially posted.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Next contact your bank. There is often some processing time before a refund is posted.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>If you’ve done all of this and you still have not received your refund yet, please contact us at {email address}.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<b>Sale items</b>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Only regular priced items may be refunded. Sale items cannot be refunded.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<h2>Exchanges</h2>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>We only replace items if they are defective or damaged. If you need to exchange it for the same item, send us an email at {email address} and send your item to: {physical address}.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<h2>Gifts</h2>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>If the item was marked as a gift when purchased and shipped directly to you, you’ll receive a gift credit for the value of your return. Once the returned item is received, a gift certificate will be mailed to you.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>If the item wasn’t marked as a gift when purchased, or the gift giver had the order shipped to themselves to give to you later, we will send a refund to the gift giver and they will find out about your return.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<h2>Shipping returns</h2>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>To return your product, you should mail your product to: {physical address}.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>You will be responsible for paying for your own shipping costs for returning your item. Shipping costs are non-refundable. If you receive a refund, the cost of return shipping will be deducted from your refund.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Depending on where you live, the time it may take for your exchanged product to reach you may vary.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>If you are returning more expensive items, you may consider using a trackable shipping service or purchasing shipping insurance. We don’t guarantee that we will receive your returned item.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<h2>Need help?</h2>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Contact us at {email} for questions related to refunds and returns.</p>\n<!-- /wp:paragraph -->','Refund and Returns Policy','','draft','closed','closed','','refund_returns','','','2023-01-11 11:29:59','0000-00-00 00:00:00','',0,'https://fresh.test/?page_id=12',0,'page','',0),(13,1,'2023-01-11 11:31:23','0000-00-00 00:00:00','','AUTO-DRAFT','','auto-draft','open','closed','','','','','2023-01-11 11:31:23','0000-00-00 00:00:00','',0,'https://fresh.test/?post_type=product&p=13',0,'product','',0),(14,1,'2023-01-11 11:31:58','2023-01-11 11:31:58','http://fresh.test/wp-content/uploads/2023/01/sample_products.csv','sample_products.csv','','private','open','closed','','sample_products-csv','','','2023-01-11 11:31:58','2023-01-11 11:31:58','',0,'http://fresh.test/wp-content/uploads/2023/01/sample_products.csv',0,'attachment','text/csv',0),(15,1,'2023-01-11 11:32:02','2023-01-11 11:32:02','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','V-Neck T-Shirt','This is a variable product.','publish','open','closed','','v-neck-t-shirt','','','2023-01-11 11:34:06','2023-01-11 11:34:06','',0,'https://fresh.test/?post_type=product&#038;p=15',0,'product','',0),(16,1,'2023-01-11 11:32:02','2023-01-11 11:32:02','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Hoodie','This is a variable product.','publish','open','closed','','hoodie','','','2023-01-11 11:34:27','2023-01-11 11:34:27','',0,'https://fresh.test/?post_type=product&#038;p=16',0,'product','',0),(17,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Hoodie with Logo','This is a simple product.','publish','open','closed','','hoodie-with-logo','','','2023-01-11 11:32:58','2023-01-11 11:32:58','',0,'https://fresh.test/?post_type=product&#038;p=17',0,'product','',0),(18,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','T-Shirt','This is a simple product.','publish','open','closed','','t-shirt','','','2023-01-11 11:33:01','2023-01-11 11:33:01','',0,'https://fresh.test/?post_type=product&#038;p=18',0,'product','',0),(19,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Beanie','This is a simple product.','publish','open','closed','','beanie','','','2023-01-11 11:33:05','2023-01-11 11:33:05','',0,'https://fresh.test/?post_type=product&#038;p=19',0,'product','',0),(20,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Belt','This is a simple product.','publish','open','closed','','belt','','','2023-01-11 11:33:09','2023-01-11 11:33:09','',0,'https://fresh.test/?post_type=product&#038;p=20',0,'product','',0),(21,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Cap','This is a simple product.','publish','open','closed','','cap','','','2023-01-11 11:33:11','2023-01-11 11:33:11','',0,'https://fresh.test/?post_type=product&#038;p=21',0,'product','',0),(22,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Sunglasses','This is a simple product.','publish','open','closed','','sunglasses','','','2023-01-11 11:33:18','2023-01-11 11:33:18','',0,'https://fresh.test/?post_type=product&#038;p=22',0,'product','',0),(23,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Hoodie with Pocket','This is a simple product.','publish','open','closed','','hoodie-with-pocket','','','2023-01-11 11:33:21','2023-01-11 11:33:21','',0,'https://fresh.test/?post_type=product&#038;p=23',0,'product','',0),(24,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Hoodie with Zipper','This is a simple product.','publish','open','closed','','hoodie-with-zipper','','','2023-01-11 11:33:24','2023-01-11 11:33:24','',0,'https://fresh.test/?post_type=product&#038;p=24',0,'product','',0),(25,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Long Sleeve Tee','This is a simple product.','publish','open','closed','','long-sleeve-tee','','','2023-01-11 11:33:27','2023-01-11 11:33:27','',0,'https://fresh.test/?post_type=product&#038;p=25',0,'product','',0),(26,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Polo','This is a simple product.','publish','open','closed','','polo','','','2023-01-11 11:33:41','2023-01-11 11:33:41','',0,'https://fresh.test/?post_type=product&#038;p=26',0,'product','',0),(27,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum sagittis orci ac odio dictum tincidunt. Donec ut metus leo. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Sed luctus, dui eu sagittis sodales, nulla nibh sagittis augue, vel porttitor diam enim non metus. Vestibulum aliquam augue neque. Phasellus tincidunt odio eget ullamcorper efficitur. Cras placerat ut turpis pellentesque vulputate. Nam sed consequat tortor. Curabitur finibus sapien dolor. Ut eleifend tellus nec erat pulvinar dignissim. Nam non arcu purus. Vivamus et massa massa.','Album','This is a simple, virtual product.','publish','open','closed','','album','','','2023-01-11 11:33:46','2023-01-11 11:33:46','',0,'https://fresh.test/?post_type=product&#038;p=27',0,'product','',0),(28,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum sagittis orci ac odio dictum tincidunt. Donec ut metus leo. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Sed luctus, dui eu sagittis sodales, nulla nibh sagittis augue, vel porttitor diam enim non metus. Vestibulum aliquam augue neque. Phasellus tincidunt odio eget ullamcorper efficitur. Cras placerat ut turpis pellentesque vulputate. Nam sed consequat tortor. Curabitur finibus sapien dolor. Ut eleifend tellus nec erat pulvinar dignissim. Nam non arcu purus. Vivamus et massa massa.','Single','This is a simple, virtual product.','publish','open','closed','','single','','','2023-01-11 11:33:52','2023-01-11 11:33:52','',0,'https://fresh.test/?post_type=product&#038;p=28',0,'product','',0),(29,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','','V-Neck T-Shirt - Red','Color: Red','publish','closed','closed','','v-neck-t-shirt-red','','','2023-01-11 11:33:52','2023-01-11 11:33:52','',15,'https://fresh.test/?post_type=product&#038;p=29',0,'product_variation','',0),(30,1,'2023-01-11 11:32:03','2023-01-11 11:32:03','','V-Neck T-Shirt - Green','Color: Green','publish','closed','closed','','v-neck-t-shirt-green','','','2023-01-11 11:33:52','2023-01-11 11:33:52','',15,'https://fresh.test/?post_type=product&#038;p=30',0,'product_variation','',0),(31,1,'2023-01-11 11:32:04','2023-01-11 11:32:04','','V-Neck T-Shirt - Blue','Color: Blue','publish','closed','closed','','v-neck-t-shirt-blue','','','2023-01-11 11:33:52','2023-01-11 11:33:52','',15,'https://fresh.test/?post_type=product&#038;p=31',0,'product_variation','',0),(32,1,'2023-01-11 11:32:04','2023-01-11 11:32:04','','Hoodie - Red, No','Color: Red, Logo: No','publish','closed','closed','','hoodie-red-no','','','2023-01-11 11:33:52','2023-01-11 11:33:52','',16,'https://fresh.test/?post_type=product&#038;p=32',1,'product_variation','',0),(33,1,'2023-01-11 11:32:04','2023-01-11 11:32:04','','Hoodie - Green, No','Color: Green, Logo: No','publish','closed','closed','','hoodie-green-no','','','2023-01-11 11:33:52','2023-01-11 11:33:52','',16,'https://fresh.test/?post_type=product&#038;p=33',2,'product_variation','',0),(34,1,'2023-01-11 11:32:04','2023-01-11 11:32:04','','Hoodie - Blue, No','Color: Blue, Logo: No','publish','closed','closed','','hoodie-blue-no','','','2023-01-11 11:33:52','2023-01-11 11:33:52','',16,'https://fresh.test/?post_type=product&#038;p=34',3,'product_variation','',0),(35,1,'2023-01-11 11:32:04','2023-01-11 11:32:04','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','T-Shirt with Logo','This is a simple product.','publish','open','closed','','t-shirt-with-logo','','','2023-01-11 11:34:01','2023-01-11 11:34:01','',0,'https://fresh.test/?post_type=product&#038;p=35',0,'product','',0),(36,1,'2023-01-11 11:32:04','2023-01-11 11:32:04','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Beanie with Logo','This is a simple product.','publish','open','closed','','beanie-with-logo','','','2023-01-11 11:34:06','2023-01-11 11:34:06','',0,'https://fresh.test/?post_type=product&#038;p=36',0,'product','',0),(37,1,'2023-01-11 11:32:04','2023-01-11 11:32:04','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','Logo Collection','This is a grouped product.','publish','open','closed','','logo-collection','','','2023-01-11 11:34:22','2023-01-11 11:34:22','',0,'https://fresh.test/?post_type=product&#038;p=37',0,'product','',0),(38,1,'2023-01-11 11:32:04','2023-01-11 11:32:04','Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.','WordPress Pennant','This is an external product.','publish','open','closed','','wordpress-pennant','','','2023-01-11 11:34:27','2023-01-11 11:34:27','',0,'https://fresh.test/?post_type=product&#038;p=38',0,'product','',0),(39,1,'2023-01-11 11:32:04','2023-01-11 11:32:04','','Hoodie - Blue, Yes','Color: Blue, Logo: Yes','publish','closed','closed','','hoodie-blue-yes','','','2023-01-11 11:34:27','2023-01-11 11:34:27','',16,'https://fresh.test/?post_type=product&#038;p=39',0,'product_variation','',0),(40,1,'2023-01-11 11:32:14','2023-01-11 11:32:14','','vneck-tee-2.jpg','','inherit','open','closed','','vneck-tee-2-jpg','','','2023-01-11 11:32:14','2023-01-11 11:32:14','',15,'http://fresh.test/wp-content/uploads/2023/01/vneck-tee-2.jpg',0,'attachment','image/jpeg',0),(41,1,'2023-01-11 11:32:17','2023-01-11 11:32:17','','vnech-tee-green-1.jpg','','inherit','open','closed','','vnech-tee-green-1-jpg','','','2023-01-11 11:32:17','2023-01-11 11:32:17','',15,'http://fresh.test/wp-content/uploads/2023/01/vnech-tee-green-1.jpg',0,'attachment','image/jpeg',0),(42,1,'2023-01-11 11:32:32','2023-01-11 11:32:32','','vnech-tee-blue-1.jpg','','inherit','open','closed','','vnech-tee-blue-1-jpg','','','2023-01-11 11:32:32','2023-01-11 11:32:32','',15,'http://fresh.test/wp-content/uploads/2023/01/vnech-tee-blue-1.jpg',0,'attachment','image/jpeg',0),(43,1,'2023-01-11 11:32:39','2023-01-11 11:32:39','','hoodie-2.jpg','','inherit','open','closed','','hoodie-2-jpg','','','2023-01-11 11:32:39','2023-01-11 11:32:39','',16,'http://fresh.test/wp-content/uploads/2023/01/hoodie-2.jpg',0,'attachment','image/jpeg',0),(44,1,'2023-01-11 11:32:48','2023-01-11 11:32:48','','hoodie-blue-1.jpg','','inherit','open','closed','','hoodie-blue-1-jpg','','','2023-01-11 11:32:48','2023-01-11 11:32:48','',16,'http://fresh.test/wp-content/uploads/2023/01/hoodie-blue-1.jpg',0,'attachment','image/jpeg',0),(45,1,'2023-01-11 11:32:52','2023-01-11 11:32:52','','hoodie-green-1.jpg','','inherit','open','closed','','hoodie-green-1-jpg','','','2023-01-11 11:32:52','2023-01-11 11:32:52','',16,'http://fresh.test/wp-content/uploads/2023/01/hoodie-green-1.jpg',0,'attachment','image/jpeg',0),(46,1,'2023-01-11 11:32:58','2023-01-11 11:32:58','','hoodie-with-logo-2.jpg','','inherit','open','closed','','hoodie-with-logo-2-jpg','','','2023-01-11 11:32:58','2023-01-11 11:32:58','',16,'http://fresh.test/wp-content/uploads/2023/01/hoodie-with-logo-2.jpg',0,'attachment','image/jpeg',0),(47,1,'2023-01-11 11:33:01','2023-01-11 11:33:01','','tshirt-2.jpg','','inherit','open','closed','','tshirt-2-jpg','','','2023-01-11 11:33:01','2023-01-11 11:33:01','',18,'http://fresh.test/wp-content/uploads/2023/01/tshirt-2.jpg',0,'attachment','image/jpeg',0),(48,1,'2023-01-11 11:33:05','2023-01-11 11:33:05','','beanie-2.jpg','','inherit','open','closed','','beanie-2-jpg','','','2023-01-11 11:33:05','2023-01-11 11:33:05','',19,'http://fresh.test/wp-content/uploads/2023/01/beanie-2.jpg',0,'attachment','image/jpeg',0),(49,1,'2023-01-11 11:33:09','2023-01-11 11:33:09','','belt-2.jpg','','inherit','open','closed','','belt-2-jpg','','','2023-01-11 11:33:09','2023-01-11 11:33:09','',20,'http://fresh.test/wp-content/uploads/2023/01/belt-2.jpg',0,'attachment','image/jpeg',0),(50,1,'2023-01-11 11:33:11','2023-01-11 11:33:11','','cap-2.jpg','','inherit','open','closed','','cap-2-jpg','','','2023-01-11 11:33:11','2023-01-11 11:33:11','',21,'http://fresh.test/wp-content/uploads/2023/01/cap-2.jpg',0,'attachment','image/jpeg',0),(51,1,'2023-01-11 11:33:18','2023-01-11 11:33:18','','sunglasses-2.jpg','','inherit','open','closed','','sunglasses-2-jpg','','','2023-01-11 11:33:18','2023-01-11 11:33:18','',22,'http://fresh.test/wp-content/uploads/2023/01/sunglasses-2.jpg',0,'attachment','image/jpeg',0),(52,1,'2023-01-11 11:33:20','2023-01-11 11:33:20','','hoodie-with-pocket-2.jpg','','inherit','open','closed','','hoodie-with-pocket-2-jpg','','','2023-01-11 11:33:20','2023-01-11 11:33:20','',23,'http://fresh.test/wp-content/uploads/2023/01/hoodie-with-pocket-2.jpg',0,'attachment','image/jpeg',0),(53,1,'2023-01-11 11:33:23','2023-01-11 11:33:23','','hoodie-with-zipper-2.jpg','','inherit','open','closed','','hoodie-with-zipper-2-jpg','','','2023-01-11 11:33:23','2023-01-11 11:33:23','',24,'http://fresh.test/wp-content/uploads/2023/01/hoodie-with-zipper-2.jpg',0,'attachment','image/jpeg',0),(54,1,'2023-01-11 11:33:27','2023-01-11 11:33:27','','long-sleeve-tee-2.jpg','','inherit','open','closed','','long-sleeve-tee-2-jpg','','','2023-01-11 11:33:27','2023-01-11 11:33:27','',25,'http://fresh.test/wp-content/uploads/2023/01/long-sleeve-tee-2.jpg',0,'attachment','image/jpeg',0),(55,1,'2023-01-11 11:33:41','2023-01-11 11:33:41','','polo-2.jpg','','inherit','open','closed','','polo-2-jpg','','','2023-01-11 11:33:41','2023-01-11 11:33:41','',26,'http://fresh.test/wp-content/uploads/2023/01/polo-2.jpg',0,'attachment','image/jpeg',0),(56,1,'2023-01-11 11:33:46','2023-01-11 11:33:46','','album-1.jpg','','inherit','open','closed','','album-1-jpg','','','2023-01-11 11:33:46','2023-01-11 11:33:46','',27,'http://fresh.test/wp-content/uploads/2023/01/album-1.jpg',0,'attachment','image/jpeg',0),(57,1,'2023-01-11 11:33:52','2023-01-11 11:33:52','','single-1.jpg','','inherit','open','closed','','single-1-jpg','','','2023-01-11 11:33:52','2023-01-11 11:33:52','',28,'http://fresh.test/wp-content/uploads/2023/01/single-1.jpg',0,'attachment','image/jpeg',0),(58,1,'2023-01-11 11:34:00','2023-01-11 11:34:00','','t-shirt-with-logo-1.jpg','','inherit','open','closed','','t-shirt-with-logo-1-jpg','','','2023-01-11 11:34:00','2023-01-11 11:34:00','',35,'http://fresh.test/wp-content/uploads/2023/01/t-shirt-with-logo-1.jpg',0,'attachment','image/jpeg',0),(59,1,'2023-01-11 11:34:06','2023-01-11 11:34:06','','beanie-with-logo-1.jpg','','inherit','open','closed','','beanie-with-logo-1-jpg','','','2023-01-11 11:34:06','2023-01-11 11:34:06','',36,'http://fresh.test/wp-content/uploads/2023/01/beanie-with-logo-1.jpg',0,'attachment','image/jpeg',0),(60,1,'2023-01-11 11:34:22','2023-01-11 11:34:22','','logo-1.jpg','','inherit','open','closed','','logo-1-jpg','','','2023-01-11 11:34:22','2023-01-11 11:34:22','',37,'http://fresh.test/wp-content/uploads/2023/01/logo-1.jpg',0,'attachment','image/jpeg',0),(61,1,'2023-01-11 11:34:27','2023-01-11 11:34:27','','pennant-1.jpg','','inherit','open','closed','','pennant-1-jpg','','','2023-01-11 11:34:27','2023-01-11 11:34:27','',38,'http://fresh.test/wp-content/uploads/2023/01/pennant-1.jpg',0,'attachment','image/jpeg',0);
/*!40000 ALTER TABLE `wp_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_sms_otp`
--

DROP TABLE IF EXISTS `wp_sms_otp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_sms_otp` (
  `ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `agent` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `code` char(32) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_sms_otp`
--

LOCK TABLES `wp_sms_otp` WRITE;
/*!40000 ALTER TABLE `wp_sms_otp` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_sms_otp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_sms_otp_attempts`
--

DROP TABLE IF EXISTS `wp_sms_otp_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_sms_otp_attempts` (
  `ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `agent` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `result` tinyint(1) NOT NULL,
  `time` int unsigned NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `phone_number` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_sms_otp_attempts`
--

LOCK TABLES `wp_sms_otp_attempts` WRITE;
/*!40000 ALTER TABLE `wp_sms_otp_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_sms_otp_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_sms_send`
--

DROP TABLE IF EXISTS `wp_sms_send`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_sms_send` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `date` datetime DEFAULT NULL,
  `sender` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `recipient` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `media` text COLLATE utf8mb4_unicode_520_ci,
  `response` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_sms_send`
--

LOCK TABLES `wp_sms_send` WRITE;
/*!40000 ALTER TABLE `wp_sms_send` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_sms_send` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_sms_subscribes`
--

DROP TABLE IF EXISTS `wp_sms_subscribes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_sms_subscribes` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `date` datetime DEFAULT NULL,
  `name` varchar(250) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `mobile` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `activate_key` int DEFAULT NULL,
  `custom_fields` TEXT NULL,
  `group_ID` int DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_sms_subscribes`
--

LOCK TABLES `wp_sms_subscribes` WRITE;
/*!40000 ALTER TABLE `wp_sms_subscribes` DISABLE KEYS */;
INSERT INTO `wp_sms_subscribes` VALUES (1,'2023-01-11 11:30:53','John','0123456789',1,NULL,NULL,0);
/*!40000 ALTER TABLE `wp_sms_subscribes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_sms_subscribes_group`
--

DROP TABLE IF EXISTS `wp_sms_subscribes_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_sms_subscribes_group` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_sms_subscribes_group`
--

LOCK TABLES `wp_sms_subscribes_group` WRITE;
/*!40000 ALTER TABLE `wp_sms_subscribes_group` DISABLE KEYS */;
INSERT INTO `wp_sms_subscribes_group` VALUES (1,'Sport');
/*!40000 ALTER TABLE `wp_sms_subscribes_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_term_relationships`
--

DROP TABLE IF EXISTS `wp_term_relationships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_term_relationships` (
  `object_id` bigint unsigned NOT NULL DEFAULT '0',
  `term_taxonomy_id` bigint unsigned NOT NULL DEFAULT '0',
  `term_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`object_id`,`term_taxonomy_id`),
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_term_relationships`
--

LOCK TABLES `wp_term_relationships` WRITE;
/*!40000 ALTER TABLE `wp_term_relationships` DISABLE KEYS */;
INSERT INTO `wp_term_relationships` VALUES (15,4,0),(15,8,0),(15,17,0),(15,22,0),(15,23,0),(15,24,0),(15,25,0),(15,26,0),(15,27,0),(16,4,0),(16,18,0),(16,22,0),(16,23,0),(16,24,0),(17,2,0),(17,18,0),(17,22,0),(18,2,0),(18,17,0),(18,28,0),(19,2,0),(19,19,0),(19,24,0),(20,2,0),(20,19,0),(21,2,0),(21,8,0),(21,19,0),(21,29,0),(22,2,0),(22,8,0),(22,19,0),(23,2,0),(23,6,0),(23,7,0),(23,8,0),(23,18,0),(23,28,0),(24,2,0),(24,8,0),(24,18,0),(25,2,0),(25,17,0),(25,23,0),(26,2,0),(26,17,0),(26,22,0),(27,2,0),(27,20,0),(28,2,0),(28,20,0),(29,15,0),(30,15,0),(31,15,0),(32,15,0),(33,15,0),(34,15,0),(35,2,0),(35,17,0),(35,28,0),(36,2,0),(36,19,0),(36,24,0),(37,3,0),(37,16,0),(38,5,0),(38,21,0),(39,15,0);
/*!40000 ALTER TABLE `wp_term_relationships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_term_taxonomy`
--

DROP TABLE IF EXISTS `wp_term_taxonomy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_term_taxonomy` (
  `term_taxonomy_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint unsigned NOT NULL DEFAULT '0',
  `taxonomy` varchar(32) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `description` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `parent` bigint unsigned NOT NULL DEFAULT '0',
  `count` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
  KEY `taxonomy` (`taxonomy`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_term_taxonomy`
--

LOCK TABLES `wp_term_taxonomy` WRITE;
/*!40000 ALTER TABLE `wp_term_taxonomy` DISABLE KEYS */;
INSERT INTO `wp_term_taxonomy` VALUES (1,1,'category','',0,0),(2,2,'product_type','',0,14),(3,3,'product_type','',0,1),(4,4,'product_type','',0,2),(5,5,'product_type','',0,1),(6,6,'product_visibility','',0,1),(7,7,'product_visibility','',0,1),(8,8,'product_visibility','',0,5),(9,9,'product_visibility','',0,0),(10,10,'product_visibility','',0,0),(11,11,'product_visibility','',0,0),(12,12,'product_visibility','',0,0),(13,13,'product_visibility','',0,0),(14,14,'product_visibility','',0,0),(15,15,'product_cat','',0,0),(16,16,'product_cat','',0,1),(17,17,'product_cat','',16,5),(18,18,'product_cat','',16,4),(19,19,'product_cat','',16,5),(20,20,'product_cat','',0,2),(21,21,'product_cat','',0,1),(22,22,'pa_color','',0,4),(23,23,'pa_color','',0,3),(24,24,'pa_color','',0,4),(25,25,'pa_size','',0,1),(26,26,'pa_size','',0,1),(27,27,'pa_size','',0,1),(28,28,'pa_color','',0,3),(29,29,'pa_color','',0,1);
/*!40000 ALTER TABLE `wp_term_taxonomy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_termmeta`
--

DROP TABLE IF EXISTS `wp_termmeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_termmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `term_id` (`term_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_termmeta`
--

LOCK TABLES `wp_termmeta` WRITE;
/*!40000 ALTER TABLE `wp_termmeta` DISABLE KEYS */;
INSERT INTO `wp_termmeta` VALUES (1,15,'product_count_product_cat','0'),(2,16,'order','0'),(3,17,'order','0'),(4,18,'order','0'),(5,19,'order','0'),(6,20,'order','0'),(7,21,'order','0'),(8,17,'product_count_product_cat','5'),(9,16,'product_count_product_cat','14'),(10,22,'order_pa_color','0'),(11,23,'order_pa_color','0'),(12,24,'order_pa_color','0'),(13,25,'order_pa_size','0'),(14,26,'order_pa_size','0'),(15,27,'order_pa_size','0'),(16,18,'product_count_product_cat','3'),(17,28,'order_pa_color','0'),(18,19,'product_count_product_cat','5'),(19,29,'order_pa_color','0'),(20,20,'product_count_product_cat','2'),(21,21,'product_count_product_cat','1');
/*!40000 ALTER TABLE `wp_termmeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_terms`
--

DROP TABLE IF EXISTS `wp_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_terms` (
  `term_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `slug` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `term_group` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_id`),
  KEY `slug` (`slug`(191)),
  KEY `name` (`name`(191))
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_terms`
--

LOCK TABLES `wp_terms` WRITE;
/*!40000 ALTER TABLE `wp_terms` DISABLE KEYS */;
INSERT INTO `wp_terms` VALUES (1,'Uncategorized','uncategorized',0),(2,'simple','simple',0),(3,'grouped','grouped',0),(4,'variable','variable',0),(5,'external','external',0),(6,'exclude-from-search','exclude-from-search',0),(7,'exclude-from-catalog','exclude-from-catalog',0),(8,'featured','featured',0),(9,'outofstock','outofstock',0),(10,'rated-1','rated-1',0),(11,'rated-2','rated-2',0),(12,'rated-3','rated-3',0),(13,'rated-4','rated-4',0),(14,'rated-5','rated-5',0),(15,'Uncategorized','uncategorized',0),(16,'Clothing','clothing',0),(17,'Tshirts','tshirts',0),(18,'Hoodies','hoodies',0),(19,'Accessories','accessories',0),(20,'Music','music',0),(21,'Decor','decor',0),(22,'Blue','blue',0),(23,'Green','green',0),(24,'Red','red',0),(25,'Large','large',0),(26,'Medium','medium',0),(27,'Small','small',0),(28,'Gray','gray',0),(29,'Yellow','yellow',0);
/*!40000 ALTER TABLE `wp_terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_usermeta`
--

DROP TABLE IF EXISTS `wp_usermeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_usermeta` (
  `umeta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`umeta_id`),
  KEY `user_id` (`user_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_usermeta`
--

LOCK TABLES `wp_usermeta` WRITE;
/*!40000 ALTER TABLE `wp_usermeta` DISABLE KEYS */;
INSERT INTO `wp_usermeta` VALUES (1,1,'nickname','admin'),(2,1,'first_name',''),(3,1,'last_name',''),(4,1,'description',''),(5,1,'rich_editing','true'),(6,1,'syntax_highlighting','true'),(7,1,'comment_shortcuts','false'),(8,1,'admin_color','fresh'),(9,1,'use_ssl','0'),(10,1,'show_admin_bar_front','true'),(11,1,'locale',''),(12,1,'wp_capabilities','a:1:{s:13:\"administrator\";b:1;}'),(13,1,'wp_user_level','10'),(14,1,'dismissed_wp_pointers',''),(15,1,'show_welcome_panel','1'),(30,1,'session_tokens','a:1:{s:64:\"a063024283f989a20aa95672e2324756ffde56e6c0576ff3697202940df3a165\";a:4:{s:10:\"expiration\";i:1673609153;s:2:\"ip\";s:9:\"127.0.0.1\";s:2:\"ua\";s:117:\"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36\";s:5:\"login\";i:1673436353;}}'),(31,1,'wp_dashboard_quick_press_last_post_id','6'),(32,1,'community-events-location','a:1:{s:2:\"ip\";s:9:\"127.0.0.0\";}'),(33,1,'_woocommerce_tracks_anon_id','woo:Ph9fYvmVBgaBxfjoxBLDZJuV'),(34,1,'meta-box-order_product','a:3:{s:4:\"side\";s:84:\"submitdiv,postimagediv,woocommerce-product-images,product_catdiv,tagsdiv-product_tag\";s:6:\"normal\";s:55:\"woocommerce-product-data,postcustom,slugdiv,postexcerpt\";s:8:\"advanced\";s:0:\"\";}'),(35,1,'wc_last_active','1673395200'),(36,1,'wp_woocommerce_product_import_mapping','a:51:{i:0;s:2:\"id\";i:1;s:4:\"type\";i:2;s:3:\"sku\";i:3;s:4:\"name\";i:4;s:9:\"published\";i:5;s:8:\"featured\";i:6;s:18:\"catalog_visibility\";i:7;s:17:\"short_description\";i:8;s:11:\"description\";i:9;s:17:\"date_on_sale_from\";i:10;s:15:\"date_on_sale_to\";i:11;s:10:\"tax_status\";i:12;s:9:\"tax_class\";i:13;s:12:\"stock_status\";i:14;s:14:\"stock_quantity\";i:15;s:10:\"backorders\";i:16;s:17:\"sold_individually\";i:17;s:0:\"\";i:18;s:0:\"\";i:19;s:0:\"\";i:20;s:0:\"\";i:21;s:15:\"reviews_allowed\";i:22;s:13:\"purchase_note\";i:23;s:10:\"sale_price\";i:24;s:13:\"regular_price\";i:25;s:12:\"category_ids\";i:26;s:7:\"tag_ids\";i:27;s:17:\"shipping_class_id\";i:28;s:6:\"images\";i:29;s:14:\"download_limit\";i:30;s:15:\"download_expiry\";i:31;s:9:\"parent_id\";i:32;s:16:\"grouped_products\";i:33;s:10:\"upsell_ids\";i:34;s:14:\"cross_sell_ids\";i:35;s:11:\"product_url\";i:36;s:11:\"button_text\";i:37;s:10:\"menu_order\";i:38;s:16:\"attributes:name1\";i:39;s:17:\"attributes:value1\";i:40;s:19:\"attributes:visible1\";i:41;s:20:\"attributes:taxonomy1\";i:42;s:16:\"attributes:name2\";i:43;s:17:\"attributes:value2\";i:44;s:19:\"attributes:visible2\";i:45;s:20:\"attributes:taxonomy2\";i:46;s:23:\"meta:_wpcom_is_markdown\";i:47;s:15:\"downloads:name1\";i:48;s:14:\"downloads:url1\";i:49;s:15:\"downloads:name2\";i:50;s:14:\"downloads:url2\";}'),(37,1,'wp_product_import_error_log','a:0:{}');
/*!40000 ALTER TABLE `wp_usermeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_users`
--

DROP TABLE IF EXISTS `wp_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_users` (
  `ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_login` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_pass` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_nicename` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_url` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_status` int NOT NULL DEFAULT '0',
  `display_name` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename` (`user_nicename`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_users`
--

LOCK TABLES `wp_users` WRITE;
/*!40000 ALTER TABLE `wp_users` DISABLE KEYS */;
INSERT INTO `wp_users` VALUES (1,'admin','$P$BLOpQElYnBICVMI7nwzQZJXpyuaGa/1','admin','local@local.test','http://fresh.test','2023-01-11 11:24:11','',0,'admin');
/*!40000 ALTER TABLE `wp_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_admin_note_actions`
--

DROP TABLE IF EXISTS `wp_wc_admin_note_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_admin_note_actions` (
  `action_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `note_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `query` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `actioned_text` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `nonce_action` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `nonce_name` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`action_id`),
  KEY `note_id` (`note_id`)
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_admin_note_actions`
--

LOCK TABLES `wp_wc_admin_note_actions` WRITE;
/*!40000 ALTER TABLE `wp_wc_admin_note_actions` DISABLE KEYS */;
INSERT INTO `wp_wc_admin_note_actions` VALUES (42,34,'notify-refund-returns-page','Edit page','https://fresh.test/wp-admin/post.php?post=12&action=edit','actioned','',NULL,NULL),(43,35,'connect','Connect','?page=wc-addons&section=helper','unactioned','',NULL,NULL),(44,1,'browse_extensions','Browse extensions','https://fresh.test/wp-admin/admin.php?page=wc-addons','unactioned','',NULL,NULL),(45,2,'wayflyer_bnpl_q4_2021','Level up with funding','https://woocommerce.com/products/wayflyer/?utm_source=inbox_note&utm_medium=product&utm_campaign=wayflyer_bnpl_q4_2021','actioned','',NULL,NULL),(46,3,'wc_shipping_mobile_app_usps_q4_2021','Get WooCommerce Shipping','https://woocommerce.com/woocommerce-shipping/?utm_source=inbox_note&utm_medium=product&utm_campaign=wc_shipping_mobile_app_usps_q4_2021','actioned','',NULL,NULL),(47,4,'learn-more','Learn more','https://docs.woocommerce.com/document/woocommerce-shipping-and-tax/?utm_source=inbox','unactioned','',NULL,NULL),(48,5,'learn-more','Learn more','https://woocommerce.com/posts/ecommerce-shipping-solutions-guide/?utm_source=inbox_note&utm_medium=product&utm_campaign=learn-more','actioned','',NULL,NULL),(49,6,'optimizing-the-checkout-flow','Learn more','https://woocommerce.com/posts/optimizing-woocommerce-checkout?utm_source=inbox_note&utm_medium=product&utm_campaign=optimizing-the-checkout-flow','actioned','',NULL,NULL),(50,7,'qualitative-feedback-from-new-users','Share feedback','https://automattic.survey.fm/wc-pay-new','actioned','',NULL,NULL),(51,8,'share-feedback','Share feedback','http://automattic.survey.fm/paypal-feedback','unactioned','',NULL,NULL),(52,9,'get-started','Get started','https://woocommerce.com/products/google-listings-and-ads?utm_source=inbox_note&utm_medium=product&utm_campaign=get-started','actioned','',NULL,NULL),(53,10,'update-wc-subscriptions-3-0-15','View latest version','https://fresh.test/wp-admin/&page=wc-addons&section=helper','actioned','',NULL,NULL),(54,11,'update-wc-core-5-4-0','How to update WooCommerce','https://docs.woocommerce.com/document/how-to-update-woocommerce/','actioned','',NULL,NULL),(55,14,'ppxo-pps-install-paypal-payments-1','View upgrade guide','https://docs.woocommerce.com/document/woocommerce-paypal-payments/paypal-payments-upgrade-guide/','actioned','',NULL,NULL),(56,15,'ppxo-pps-install-paypal-payments-2','View upgrade guide','https://docs.woocommerce.com/document/woocommerce-paypal-payments/paypal-payments-upgrade-guide/','actioned','',NULL,NULL),(57,16,'learn-more','Learn more','https://woocommerce.com/posts/critical-vulnerability-detected-july-2021/?utm_source=inbox_note&utm_medium=product&utm_campaign=learn-more','unactioned','',NULL,NULL),(58,16,'dismiss','Dismiss','','actioned','',NULL,NULL),(59,17,'learn-more','Learn more','https://woocommerce.com/posts/critical-vulnerability-detected-july-2021/?utm_source=inbox_note&utm_medium=product&utm_campaign=learn-more','unactioned','',NULL,NULL),(60,17,'dismiss','Dismiss','','actioned','',NULL,NULL),(61,18,'learn-more','Learn more','https://woocommerce.com/posts/critical-vulnerability-detected-july-2021/?utm_source=inbox_note&utm_medium=product&utm_campaign=learn-more','unactioned','',NULL,NULL),(62,18,'dismiss','Dismiss','','actioned','',NULL,NULL),(63,19,'learn-more','Learn more','https://woocommerce.com/posts/critical-vulnerability-detected-july-2021/?utm_source=inbox_note&utm_medium=product&utm_campaign=learn-more','unactioned','',NULL,NULL),(64,19,'dismiss','Dismiss','','actioned','',NULL,NULL),(65,20,'share-feedback','Share feedback','https://automattic.survey.fm/store-management','unactioned','',NULL,NULL),(66,21,'learn-more','Learn more','https://developer.woocommerce.com/2022/03/10/woocommerce-3-5-10-6-3-1-security-releases/','unactioned','',NULL,NULL),(67,21,'woocommerce-core-paypal-march-2022-dismiss','Dismiss','','actioned','',NULL,NULL),(68,22,'learn-more','Learn more','https://developer.woocommerce.com/2022/03/10/woocommerce-3-5-10-6-3-1-security-releases/','unactioned','',NULL,NULL),(69,22,'dismiss','Dismiss','','actioned','',NULL,NULL),(70,23,'pinterest_03_2022_update','Update Instructions','https://woocommerce.com/document/pinterest-for-woocommerce/?utm_source=inbox_note&utm_medium=product&utm_campaign=pinterest_03_2022_update#section-3','actioned','',NULL,NULL),(71,24,'store_setup_survey_survey_q2_2022_share_your_thoughts','Tell us how it’s going','https://automattic.survey.fm/store-setup-survey-2022','actioned','',NULL,NULL),(72,25,'wc-admin-wisepad3','Grow my business offline','https://woocommerce.com/products/wisepad3-card-reader/?utm_source=inbox_note&utm_medium=product&utm_campaign=wc-admin-wisepad3','actioned','',NULL,NULL),(73,26,'learn-more','Find out more','https://developer.woocommerce.com/2022/08/09/woocommerce-payments-3-9-4-4-5-1-security-releases/','unactioned','',NULL,NULL),(74,26,'dismiss','Dismiss','','actioned','',NULL,NULL),(75,27,'learn-more','Find out more','https://developer.woocommerce.com/2022/08/09/woocommerce-payments-3-9-4-4-5-1-security-releases/','unactioned','',NULL,NULL),(76,27,'dismiss','Dismiss','','actioned','',NULL,NULL),(77,28,'woocommerce_admin_deprecation_q4_2022','Deactivate WooCommerce Admin','https://fresh.test/wp-admin/plugins.php','actioned','',NULL,NULL),(78,29,'paypal_paylater_g3_q4_22','Install PayPal Payments','https://woocommerce.com/products/woocommerce-paypal-payments/?utm_source=inbox_note&utm_medium=product&utm_campaign=paypal_paylater_g3_q4_22','unactioned','',NULL,NULL),(79,30,'paypal_paylater_g2_q4_22','Install PayPal Payments','https://woocommerce.com/products/woocommerce-paypal-payments/?utm_source=inbox_note&utm_medium=product&utm_campaign=paypal_paylater_g2_q4_22','unactioned','',NULL,NULL),(80,31,'google_listings_ads_custom_attribute_mapping_q4_2022','Learn more','https://woocommerce.com/document/google-listings-and-ads/?utm_source=inbox_note&utm_medium=product&utm_campaign=google_listings_ads_custom_attribute_mapping_q4_2022#attribute-mapping','actioned','',NULL,NULL),(81,32,'needs-update-eway-payment-gateway-rin-action-button-2022-12-20','See available updates','https://fresh.test/wp-admin/update-core.php','unactioned','',NULL,NULL),(82,32,'needs-update-eway-payment-gateway-rin-dismiss-button-2022-12-20','Dismiss','#','actioned','',NULL,NULL),(83,33,'updated-eway-payment-gateway-rin-action-button-2022-12-20','See all updates','https://fresh.test/wp-admin/update-core.php','unactioned','',NULL,NULL),(84,33,'updated-eway-payment-gateway-rin-dismiss-button-2022-12-20','Dismiss','#','actioned','',NULL,NULL);
/*!40000 ALTER TABLE `wp_wc_admin_note_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_admin_notes`
--

DROP TABLE IF EXISTS `wp_wc_admin_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_admin_notes` (
  `note_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `locale` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `title` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `content_data` longtext COLLATE utf8mb4_unicode_520_ci,
  `status` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `source` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `date_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_reminder` datetime DEFAULT NULL,
  `is_snoozable` tinyint(1) NOT NULL DEFAULT '0',
  `layout` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `image` varchar(200) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `icon` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'info',
  PRIMARY KEY (`note_id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_admin_notes`
--

LOCK TABLES `wp_wc_admin_notes` WRITE;
/*!40000 ALTER TABLE `wp_wc_admin_notes` DISABLE KEYS */;
INSERT INTO `wp_wc_admin_notes` VALUES (1,'new_in_app_marketplace_2021','info','en_US','Customize your store with extensions','Check out our NEW Extensions tab to see our favorite extensions for customizing your store, and discover the most popular extensions in the WooCommerce Marketplace.','{}','unactioned','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(2,'wayflyer_bnpl_q4_2021','marketing','en_US','Grow your business with funding through Wayflyer','Fast, flexible financing to boost cash flow and help your business grow – one fee, no interest rates, penalties, equity, or personal guarantees. Based on your store’s performance, Wayflyer provides funding and analytical insights to invest in your business.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(3,'wc_shipping_mobile_app_usps_q4_2021','marketing','en_US','Print and manage your shipping labels with WooCommerce Shipping and the WooCommerce Mobile App','Save time by printing, purchasing, refunding, and tracking shipping labels generated by <a href=\"https://woocommerce.com/woocommerce-shipping/\">WooCommerce Shipping</a> – all directly from your mobile device!','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(4,'woocommerce-services','info','en_US','WooCommerce Shipping & Tax','WooCommerce Shipping &amp; Tax helps get your store \"ready to sell\" as quickly as possible. You create your products. We take care of tax calculation, payment processing, and shipping label printing! Learn more about the extension that you just installed.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(5,'your-first-product','info','en_US','Your first product','That’s huge! You’re well on your way to building a successful online store — now it’s time to think about how you’ll fulfill your orders.<br /><br />Read our shipping guide to learn best practices and options for putting together your shipping strategy. And for WooCommerce stores in the United States, you can print discounted shipping labels via USPS with <a href=\"https://href.li/?https://woocommerce.com/shipping\" target=\"_blank\">WooCommerce Shipping</a>.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(6,'wc-admin-optimizing-the-checkout-flow','info','en_US','Optimizing the checkout flow','It’s crucial to get your store’s checkout as smooth as possible to avoid losing sales. Let’s take a look at how you can optimize the checkout experience for your shoppers.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(7,'wc-payments-qualitative-feedback','info','en_US','WooCommerce Payments setup - let us know what you think','Congrats on enabling WooCommerce Payments for your store. Please share your feedback in this 2 minute survey to help us improve the setup process.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(8,'share-your-feedback-on-paypal','info','en_US','Share your feedback on PayPal','Share your feedback in this 2 minute survey about how we can make the process of accepting payments more useful for your store.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(9,'google_listings_and_ads_install','marketing','en_US','Drive traffic and sales with Google','Reach online shoppers to drive traffic and sales for your store by showcasing products across Google, for free or with ads.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(10,'wc-subscriptions-security-update-3-0-15','info','en_US','WooCommerce Subscriptions security update!','We recently released an important security update to WooCommerce Subscriptions. To ensure your site’s data is protected, please upgrade <strong>WooCommerce Subscriptions to version 3.0.15</strong> or later.<br /><br />Click the button below to view and update to the latest Subscriptions version, or log in to <a href=\"https://woocommerce.com/my-dashboard\">WooCommerce.com Dashboard</a> and navigate to your <strong>Downloads</strong> page.<br /><br />We recommend always using the latest version of WooCommerce Subscriptions, and other software running on your site, to ensure maximum security.<br /><br />If you have any questions we are here to help — just <a href=\"https://woocommerce.com/my-account/create-a-ticket/\">open a ticket</a>.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(11,'woocommerce-core-update-5-4-0','info','en_US','Update to WooCommerce 5.4.1 now','WooCommerce 5.4.1 addresses a checkout issue discovered in WooCommerce 5.4. We recommend upgrading to WooCommerce 5.4.1 as soon as possible.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(12,'wcpay-promo-2020-11','marketing','en_US','wcpay-promo-2020-11','wcpay-promo-2020-11','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(13,'wcpay-promo-2020-12','marketing','en_US','wcpay-promo-2020-12','wcpay-promo-2020-12','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(14,'ppxo-pps-upgrade-paypal-payments-1','info','en_US','Get the latest PayPal extension for WooCommerce','Heads up! There’s a new PayPal on the block!<br /><br />Now is a great time to upgrade to our latest <a href=\"https://woocommerce.com/products/woocommerce-paypal-payments/\" target=\"_blank\">PayPal extension</a> to continue to receive support and updates with PayPal.<br /><br />Get access to a full suite of PayPal payment methods, extensive currency and country coverage, and pay later options with the all-new PayPal extension for WooCommerce.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(15,'ppxo-pps-upgrade-paypal-payments-2','info','en_US','Upgrade your PayPal experience!','Get access to a full suite of PayPal payment methods, extensive currency and country coverage, offer subscription and recurring payments, and the new PayPal pay later options.<br /><br />Start using our <a href=\"https://woocommerce.com/products/woocommerce-paypal-payments/\" target=\"_blank\">latest PayPal today</a> to continue to receive support and updates.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(16,'woocommerce-core-sqli-july-2021-need-to-update','update','en_US','Action required: Critical vulnerabilities in WooCommerce','In response to a critical vulnerability identified on July 13, 2021, we are working with the WordPress Plugins Team to deploy software updates to stores running WooCommerce (versions 3.3 to 5.5) and the WooCommerce Blocks feature plugin (versions 2.5 to 5.5).<br /><br />Our investigation into this vulnerability is ongoing, but <strong>we wanted to let you know now about the importance of updating immediately</strong>.<br /><br />For more information on which actions you should take, as well as answers to FAQs, please urgently review our blog post detailing this issue.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(17,'woocommerce-blocks-sqli-july-2021-need-to-update','update','en_US','Action required: Critical vulnerabilities in WooCommerce Blocks','In response to a critical vulnerability identified on July 13, 2021, we are working with the WordPress Plugins Team to deploy software updates to stores running WooCommerce (versions 3.3 to 5.5) and the WooCommerce Blocks feature plugin (versions 2.5 to 5.5).<br /><br />Our investigation into this vulnerability is ongoing, but <strong>we wanted to let you know now about the importance of updating immediately</strong>.<br /><br />For more information on which actions you should take, as well as answers to FAQs, please urgently review our blog post detailing this issue.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(18,'woocommerce-core-sqli-july-2021-store-patched','update','en_US','Solved: Critical vulnerabilities patched in WooCommerce','In response to a critical vulnerability identified on July 13, 2021, we worked with the WordPress Plugins Team to deploy software updates to stores running WooCommerce (versions 3.3 to 5.5) and the WooCommerce Blocks feature plugin (versions 2.5 to 5.5).<br /><br /><strong>Your store has been updated to the latest secure version(s)</strong>. For more information and answers to FAQs, please review our blog post detailing this issue.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(19,'woocommerce-blocks-sqli-july-2021-store-patched','update','en_US','Solved: Critical vulnerabilities patched in WooCommerce Blocks','In response to a critical vulnerability identified on July 13, 2021, we worked with the WordPress Plugins Team to deploy software updates to stores running WooCommerce (versions 3.3 to 5.5) and the WooCommerce Blocks feature plugin (versions 2.5 to 5.5).<br /><br /><strong>Your store has been updated to the latest secure version(s)</strong>. For more information and answers to FAQs, please review our blog post detailing this issue.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(20,'habit-moment-survey','marketing','en_US','We’re all ears! Share your experience so far with WooCommerce','We’d love your input to shape the future of WooCommerce together. Feel free to share any feedback, ideas or suggestions that you have.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(21,'woocommerce-core-paypal-march-2022-updated','update','en_US','Security auto-update of WooCommerce','<strong>Your store has been updated to the latest secure version of WooCommerce</strong>. We worked with WordPress to deploy PayPal Standard security updates for stores running WooCommerce (version 3.5 to 6.3). It’s recommended to disable PayPal Standard, and use <a href=\"https://woocommerce.com/products/woocommerce-paypal-payments/\" target=\"_blank\">PayPal Payments</a> to accept PayPal.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(22,'woocommerce-core-paypal-march-2022-updated-nopp','update','en_US','Security auto-update of WooCommerce','<strong>Your store has been updated to the latest secure version of WooCommerce</strong>. We worked with WordPress to deploy security updates related to PayPal Standard payment gateway for stores running WooCommerce (version 3.5 to 6.3).','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(23,'pinterest_03_2022_update','marketing','en_US','Your Pinterest for WooCommerce plugin is out of date!','Update to the latest version of Pinterest for WooCommerce to continue using this plugin and keep your store connected with Pinterest. To update, visit <strong>Plugins &gt; Installed Plugins</strong>, and click on “update now” under Pinterest for WooCommerce.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(24,'store_setup_survey_survey_q2_2022','survey','en_US','How is your store setup going?','Our goal is to make sure you have all the right tools to start setting up your store in the smoothest way possible.\r\nWe’d love to know if we hit our mark and how we can improve. To collect your thoughts, we made a 2-minute survey.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(25,'wc-admin-wisepad3','marketing','en_US','Take your business on the go in Canada with WooCommerce In-Person Payments','Quickly create new orders, accept payment in person for orders placed online, and automatically sync your inventory – no matter where your business takes you. With WooCommerce In-Person Payments and the WisePad 3 card reader, you can bring the power of your store anywhere.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(26,'woocommerce-payments-august-2022-need-to-update','update','en_US','Action required: Please update WooCommerce Payments','An updated secure version of WooCommerce Payments is available – please ensure that you’re using the latest patch version. For more information on what action you need to take, please review the article below.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(27,'woocommerce-payments-august-2022-store-patched','update','en_US','WooCommerce Payments has been automatically updated','You’re now running the latest secure version of WooCommerce Payments. We’ve worked with the WordPress Plugins team to deploy a security update to stores running WooCommerce Payments (version 3.9 to 4.5). For further information, please review the article below.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(28,'woocommerce_admin_deprecation_q4_2022','info','en_US','WooCommerce Admin is part of WooCommerce!','To make sure your store continues to run smoothly, check that WooCommerce is up-to-date – at least version 6.5 – and then disable the WooCommerce Admin plugin.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(29,'paypal_paylater_g3_q4_22','marketing','en_US','Turn browsers into buyers with Pay Later','Add PayPal at checkout, plus give customers a buy now, pay later option from the name they trust. With Pay in 4 &amp; Pay Monthly, available in PayPal Payments, you get paid up front while letting customers spread their payments over time. Boost your average order value and convert more sales – at no extra cost to you.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(30,'paypal_paylater_g2_q4_22','marketing','en_US','Upgrade to PayPal Payments to offer Pay Later at checkout','PayPal Pay Later is included in PayPal Payments at no additional cost to you. Customers can spread their payments over time while you get paid up front. \r\nThere’s never been a better time to upgrade your PayPal plugin. Simply download it and connect with a PayPal Business account.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(31,'google_listings_ads_custom_attribute_mapping_q4_2022','marketing','en_US','Our latest improvement to the Google Listings & Ads extension: Attribute Mapping','You spoke, we listened. This new feature enables you to easily upload your products, customize your product attributes in one place, and target shoppers with more relevant ads. Extend how far your ad dollars go with each campaign.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(32,'needs-update-eway-payment-gateway-rin-2022-12-20','update','en_US','Security vulnerability patched in WooCommerce Eway Gateway','In response to a potential vulnerability identified in WooCommerce Eway Gateway versions 3.1.0 to 3.5.0, we’ve worked to deploy security fixes and have released an updated version.\r\nNo external exploits have been detected, but we recommend you update to your latest supported version 3.1.26, 3.2.3, 3.3.1, 3.4.6, or 3.5.1','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(33,'updated-eway-payment-gateway-rin-2022-12-20','update','en_US','WooCommerce Eway Gateway has been automatically updated','Your store is now running the latest secure version of WooCommerce Eway Gateway. We worked with the WordPress Plugins team to deploy a software update to stores running WooCommerce Eway Gateway (versions 3.1.0 to 3.5.0) in response to a security vulnerability that was discovered.','{}','pending','woocommerce.com','2023-01-11 11:30:02',NULL,0,'plain','',0,0,'info'),(34,'wc-refund-returns-page','info','en_US','Setup a Refund and Returns Policy page to boost your store\'s credibility.','We have created a sample draft Refund and Returns Policy page for you. Please have a look and update it to fit your store.','{}','unactioned','woocommerce-core','2023-01-11 11:30:05',NULL,0,'plain','',0,0,'info'),(35,'wc-admin-wc-helper-connection','info','en_US','Connect to WooCommerce.com','Connect to get important product notifications and updates.','{}','unactioned','woocommerce-admin','2023-01-11 11:30:05',NULL,0,'plain','',0,0,'info');
/*!40000 ALTER TABLE `wp_wc_admin_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_category_lookup`
--

DROP TABLE IF EXISTS `wp_wc_category_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_category_lookup` (
  `category_tree_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`category_tree_id`,`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_category_lookup`
--

LOCK TABLES `wp_wc_category_lookup` WRITE;
/*!40000 ALTER TABLE `wp_wc_category_lookup` DISABLE KEYS */;
INSERT INTO `wp_wc_category_lookup` VALUES (15,15),(16,16),(16,17),(16,18),(16,19),(17,17),(18,18),(19,19),(20,20),(21,21);
/*!40000 ALTER TABLE `wp_wc_category_lookup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_customer_lookup`
--

DROP TABLE IF EXISTS `wp_wc_customer_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_customer_lookup` (
  `customer_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `username` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `first_name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `date_last_active` timestamp NULL DEFAULT NULL,
  `date_registered` timestamp NULL DEFAULT NULL,
  `country` char(2) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `postcode` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `city` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `state` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_customer_lookup`
--

LOCK TABLES `wp_wc_customer_lookup` WRITE;
/*!40000 ALTER TABLE `wp_wc_customer_lookup` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_wc_customer_lookup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_download_log`
--

DROP TABLE IF EXISTS `wp_wc_download_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_download_log` (
  `download_log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_ip_address` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  PRIMARY KEY (`download_log_id`),
  KEY `permission_id` (`permission_id`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_download_log`
--

LOCK TABLES `wp_wc_download_log` WRITE;
/*!40000 ALTER TABLE `wp_wc_download_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_wc_download_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_order_coupon_lookup`
--

DROP TABLE IF EXISTS `wp_wc_order_coupon_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_order_coupon_lookup` (
  `order_id` bigint unsigned NOT NULL,
  `coupon_id` bigint NOT NULL,
  `date_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `discount_amount` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`order_id`,`coupon_id`),
  KEY `coupon_id` (`coupon_id`),
  KEY `date_created` (`date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_order_coupon_lookup`
--

LOCK TABLES `wp_wc_order_coupon_lookup` WRITE;
/*!40000 ALTER TABLE `wp_wc_order_coupon_lookup` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_wc_order_coupon_lookup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_order_product_lookup`
--

DROP TABLE IF EXISTS `wp_wc_order_product_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_order_product_lookup` (
  `order_item_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `variation_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `product_qty` int NOT NULL,
  `product_net_revenue` double NOT NULL DEFAULT '0',
  `product_gross_revenue` double NOT NULL DEFAULT '0',
  `coupon_amount` double NOT NULL DEFAULT '0',
  `tax_amount` double NOT NULL DEFAULT '0',
  `shipping_amount` double NOT NULL DEFAULT '0',
  `shipping_tax_amount` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  KEY `customer_id` (`customer_id`),
  KEY `date_created` (`date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_order_product_lookup`
--

LOCK TABLES `wp_wc_order_product_lookup` WRITE;
/*!40000 ALTER TABLE `wp_wc_order_product_lookup` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_wc_order_product_lookup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_order_stats`
--

DROP TABLE IF EXISTS `wp_wc_order_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_order_stats` (
  `order_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned NOT NULL DEFAULT '0',
  `date_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_created_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `num_items_sold` int NOT NULL DEFAULT '0',
  `total_sales` double NOT NULL DEFAULT '0',
  `tax_total` double NOT NULL DEFAULT '0',
  `shipping_total` double NOT NULL DEFAULT '0',
  `net_total` double NOT NULL DEFAULT '0',
  `returning_customer` tinyint(1) DEFAULT NULL,
  `status` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`order_id`),
  KEY `date_created` (`date_created`),
  KEY `customer_id` (`customer_id`),
  KEY `status` (`status`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_order_stats`
--

LOCK TABLES `wp_wc_order_stats` WRITE;
/*!40000 ALTER TABLE `wp_wc_order_stats` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_wc_order_stats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_order_tax_lookup`
--

DROP TABLE IF EXISTS `wp_wc_order_tax_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_order_tax_lookup` (
  `order_id` bigint unsigned NOT NULL,
  `tax_rate_id` bigint unsigned NOT NULL,
  `date_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `shipping_tax` double NOT NULL DEFAULT '0',
  `order_tax` double NOT NULL DEFAULT '0',
  `total_tax` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`order_id`,`tax_rate_id`),
  KEY `tax_rate_id` (`tax_rate_id`),
  KEY `date_created` (`date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_order_tax_lookup`
--

LOCK TABLES `wp_wc_order_tax_lookup` WRITE;
/*!40000 ALTER TABLE `wp_wc_order_tax_lookup` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_wc_order_tax_lookup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_product_attributes_lookup`
--

DROP TABLE IF EXISTS `wp_wc_product_attributes_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_product_attributes_lookup` (
  `product_id` bigint NOT NULL,
  `product_or_parent_id` bigint NOT NULL,
  `taxonomy` varchar(32) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `term_id` bigint NOT NULL,
  `is_variation_attribute` tinyint(1) NOT NULL,
  `in_stock` tinyint(1) NOT NULL,
  PRIMARY KEY (`product_or_parent_id`,`term_id`,`product_id`,`taxonomy`),
  KEY `is_variation_attribute_term_id` (`is_variation_attribute`,`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_product_attributes_lookup`
--

LOCK TABLES `wp_wc_product_attributes_lookup` WRITE;
/*!40000 ALTER TABLE `wp_wc_product_attributes_lookup` DISABLE KEYS */;
INSERT INTO `wp_wc_product_attributes_lookup` VALUES (31,15,'pa_color',22,1,1),(30,15,'pa_color',23,1,1),(29,15,'pa_color',24,1,1),(29,15,'pa_size',25,1,1),(30,15,'pa_size',25,1,1),(31,15,'pa_size',25,1,1),(29,15,'pa_size',26,1,1),(30,15,'pa_size',26,1,1),(31,15,'pa_size',26,1,1),(29,15,'pa_size',27,1,1),(30,15,'pa_size',27,1,1),(31,15,'pa_size',27,1,1),(34,16,'pa_color',22,1,1),(39,16,'pa_color',22,1,1),(33,16,'pa_color',23,1,1),(32,16,'pa_color',24,1,1),(17,17,'pa_color',22,0,1),(18,18,'pa_color',28,0,1),(19,19,'pa_color',24,0,1),(21,21,'pa_color',29,0,1),(25,25,'pa_color',23,0,1),(26,26,'pa_color',22,0,1),(35,35,'pa_color',28,0,1),(36,36,'pa_color',24,0,1);
/*!40000 ALTER TABLE `wp_wc_product_attributes_lookup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_product_download_directories`
--

DROP TABLE IF EXISTS `wp_wc_product_download_directories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_product_download_directories` (
  `url_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(256) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`url_id`),
  KEY `url` (`url`(191))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_product_download_directories`
--

LOCK TABLES `wp_wc_product_download_directories` WRITE;
/*!40000 ALTER TABLE `wp_wc_product_download_directories` DISABLE KEYS */;
INSERT INTO `wp_wc_product_download_directories` VALUES (1,'file:///Users/mostafasoufi/Sites/fresh/wp-content/uploads/woocommerce_uploads/',1),(2,'http://fresh.test/wp-content/uploads/woocommerce_uploads/',1),(3,'https://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2017/08/',1);
/*!40000 ALTER TABLE `wp_wc_product_download_directories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_product_meta_lookup`
--

DROP TABLE IF EXISTS `wp_wc_product_meta_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_product_meta_lookup` (
  `product_id` bigint NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `virtual` tinyint(1) DEFAULT '0',
  `downloadable` tinyint(1) DEFAULT '0',
  `min_price` decimal(19,4) DEFAULT NULL,
  `max_price` decimal(19,4) DEFAULT NULL,
  `onsale` tinyint(1) DEFAULT '0',
  `stock_quantity` double DEFAULT NULL,
  `stock_status` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT 'instock',
  `rating_count` bigint DEFAULT '0',
  `average_rating` decimal(3,2) DEFAULT '0.00',
  `total_sales` bigint DEFAULT '0',
  `tax_status` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT 'taxable',
  `tax_class` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  PRIMARY KEY (`product_id`),
  KEY `virtual` (`virtual`),
  KEY `downloadable` (`downloadable`),
  KEY `stock_status` (`stock_status`),
  KEY `stock_quantity` (`stock_quantity`),
  KEY `onsale` (`onsale`),
  KEY `min_max_price` (`min_price`,`max_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_product_meta_lookup`
--

LOCK TABLES `wp_wc_product_meta_lookup` WRITE;
/*!40000 ALTER TABLE `wp_wc_product_meta_lookup` DISABLE KEYS */;
INSERT INTO `wp_wc_product_meta_lookup` VALUES (15,'woo-vneck-tee',0,0,15.0000,20.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(16,'woo-hoodie',0,0,42.0000,45.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(17,'woo-hoodie-with-logo',0,0,45.0000,45.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(18,'woo-tshirt',0,0,18.0000,18.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(19,'woo-beanie',0,0,18.0000,18.0000,1,NULL,'instock',0,0.00,0,'taxable',''),(20,'woo-belt',0,0,55.0000,55.0000,1,NULL,'instock',0,0.00,0,'taxable',''),(21,'woo-cap',0,0,16.0000,16.0000,1,NULL,'instock',0,0.00,0,'taxable',''),(22,'woo-sunglasses',0,0,90.0000,90.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(23,'woo-hoodie-with-pocket',0,0,35.0000,35.0000,1,NULL,'instock',0,0.00,0,'taxable',''),(24,'woo-hoodie-with-zipper',0,0,45.0000,45.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(25,'woo-long-sleeve-tee',0,0,25.0000,25.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(26,'woo-polo',0,0,20.0000,20.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(27,'woo-album',1,1,15.0000,15.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(28,'woo-single',1,1,2.0000,2.0000,1,NULL,'instock',0,0.00,0,'taxable',''),(29,'woo-vneck-tee-red',0,0,20.0000,20.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(30,'woo-vneck-tee-green',0,0,20.0000,20.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(31,'woo-vneck-tee-blue',0,0,15.0000,15.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(32,'woo-hoodie-red',0,0,42.0000,42.0000,1,NULL,'instock',0,0.00,0,'taxable',''),(33,'woo-hoodie-green',0,0,45.0000,45.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(34,'woo-hoodie-blue',0,0,45.0000,45.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(35,'Woo-tshirt-logo',0,0,18.0000,18.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(36,'Woo-beanie-logo',0,0,18.0000,18.0000,1,NULL,'instock',0,0.00,0,'taxable',''),(37,'logo-collection',0,0,18.0000,45.0000,0,NULL,'instock',0,0.00,0,'taxable',''),(38,'wp-pennant',0,0,11.0500,11.0500,0,NULL,'instock',0,0.00,0,'taxable',''),(39,'woo-hoodie-blue-logo',0,0,45.0000,45.0000,0,NULL,'instock',0,0.00,0,'taxable','');
/*!40000 ALTER TABLE `wp_wc_product_meta_lookup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_rate_limits`
--

DROP TABLE IF EXISTS `wp_wc_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_rate_limits` (
  `rate_limit_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rate_limit_key` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `rate_limit_expiry` bigint unsigned NOT NULL,
  `rate_limit_remaining` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`rate_limit_id`),
  UNIQUE KEY `rate_limit_key` (`rate_limit_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_rate_limits`
--

LOCK TABLES `wp_wc_rate_limits` WRITE;
/*!40000 ALTER TABLE `wp_wc_rate_limits` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_wc_rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_reserved_stock`
--

DROP TABLE IF EXISTS `wp_wc_reserved_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_reserved_stock` (
  `order_id` bigint NOT NULL,
  `product_id` bigint NOT NULL,
  `stock_quantity` double NOT NULL DEFAULT '0',
  `timestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `expires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`order_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_reserved_stock`
--

LOCK TABLES `wp_wc_reserved_stock` WRITE;
/*!40000 ALTER TABLE `wp_wc_reserved_stock` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_wc_reserved_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_tax_rate_classes`
--

DROP TABLE IF EXISTS `wp_wc_tax_rate_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_tax_rate_classes` (
  `tax_rate_class_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `slug` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`tax_rate_class_id`),
  UNIQUE KEY `slug` (`slug`(191))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_tax_rate_classes`
--

LOCK TABLES `wp_wc_tax_rate_classes` WRITE;
/*!40000 ALTER TABLE `wp_wc_tax_rate_classes` DISABLE KEYS */;
INSERT INTO `wp_wc_tax_rate_classes` VALUES (1,'Reduced rate','reduced-rate'),(2,'Zero rate','zero-rate');
/*!40000 ALTER TABLE `wp_wc_tax_rate_classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_wc_webhooks`
--

DROP TABLE IF EXISTS `wp_wc_webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_wc_webhooks` (
  `webhook_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `delivery_url` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `secret` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `topic` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `date_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_created_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `api_version` smallint NOT NULL,
  `failure_count` smallint NOT NULL DEFAULT '0',
  `pending_delivery` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`webhook_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_wc_webhooks`
--

LOCK TABLES `wp_wc_webhooks` WRITE;
/*!40000 ALTER TABLE `wp_wc_webhooks` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_wc_webhooks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_api_keys`
--

DROP TABLE IF EXISTS `wp_woocommerce_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_api_keys` (
  `key_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `description` varchar(200) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `permissions` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `consumer_key` char(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `consumer_secret` char(43) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `nonces` longtext COLLATE utf8mb4_unicode_520_ci,
  `truncated_key` char(7) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `last_access` datetime DEFAULT NULL,
  PRIMARY KEY (`key_id`),
  KEY `consumer_key` (`consumer_key`),
  KEY `consumer_secret` (`consumer_secret`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_api_keys`
--

LOCK TABLES `wp_woocommerce_api_keys` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_api_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_api_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_attribute_taxonomies`
--

DROP TABLE IF EXISTS `wp_woocommerce_attribute_taxonomies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_attribute_taxonomies` (
  `attribute_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attribute_name` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `attribute_label` varchar(200) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `attribute_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `attribute_orderby` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `attribute_public` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`attribute_id`),
  KEY `attribute_name` (`attribute_name`(20))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_attribute_taxonomies`
--

LOCK TABLES `wp_woocommerce_attribute_taxonomies` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_attribute_taxonomies` DISABLE KEYS */;
INSERT INTO `wp_woocommerce_attribute_taxonomies` VALUES (1,'color','Color','select','menu_order',0),(2,'size','Size','select','menu_order',0);
/*!40000 ALTER TABLE `wp_woocommerce_attribute_taxonomies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_downloadable_product_permissions`
--

DROP TABLE IF EXISTS `wp_woocommerce_downloadable_product_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_downloadable_product_permissions` (
  `permission_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `download_id` varchar(36) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL DEFAULT '0',
  `order_key` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `user_email` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `downloads_remaining` varchar(9) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `access_granted` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `access_expires` datetime DEFAULT NULL,
  `download_count` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`permission_id`),
  KEY `download_order_key_product` (`product_id`,`order_id`,`order_key`(16),`download_id`),
  KEY `download_order_product` (`download_id`,`order_id`,`product_id`),
  KEY `order_id` (`order_id`),
  KEY `user_order_remaining_expires` (`user_id`,`order_id`,`downloads_remaining`,`access_expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_downloadable_product_permissions`
--

LOCK TABLES `wp_woocommerce_downloadable_product_permissions` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_downloadable_product_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_downloadable_product_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_log`
--

DROP TABLE IF EXISTS `wp_woocommerce_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_log` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL,
  `level` smallint NOT NULL,
  `source` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `message` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `context` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`log_id`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_log`
--

LOCK TABLES `wp_woocommerce_log` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_order_itemmeta`
--

DROP TABLE IF EXISTS `wp_woocommerce_order_itemmeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_order_itemmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` bigint unsigned NOT NULL,
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `order_item_id` (`order_item_id`),
  KEY `meta_key` (`meta_key`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_order_itemmeta`
--

LOCK TABLES `wp_woocommerce_order_itemmeta` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_order_itemmeta` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_order_itemmeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_order_items`
--

DROP TABLE IF EXISTS `wp_woocommerce_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_order_items` (
  `order_item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_item_name` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `order_item_type` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `order_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_order_items`
--

LOCK TABLES `wp_woocommerce_order_items` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_payment_tokenmeta`
--

DROP TABLE IF EXISTS `wp_woocommerce_payment_tokenmeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_payment_tokenmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_token_id` bigint unsigned NOT NULL,
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `payment_token_id` (`payment_token_id`),
  KEY `meta_key` (`meta_key`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_payment_tokenmeta`
--

LOCK TABLES `wp_woocommerce_payment_tokenmeta` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_payment_tokenmeta` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_payment_tokenmeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_payment_tokens`
--

DROP TABLE IF EXISTS `wp_woocommerce_payment_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_payment_tokens` (
  `token_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gateway_id` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `token` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  `type` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`token_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_payment_tokens`
--

LOCK TABLES `wp_woocommerce_payment_tokens` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_payment_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_payment_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_sessions`
--

DROP TABLE IF EXISTS `wp_woocommerce_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_sessions` (
  `session_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_key` char(32) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `session_value` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `session_expiry` bigint unsigned NOT NULL,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `session_key` (`session_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_sessions`
--

LOCK TABLES `wp_woocommerce_sessions` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_sessions` DISABLE KEYS */;
INSERT INTO `wp_woocommerce_sessions` VALUES (1,'1','a:7:{s:4:\"cart\";s:6:\"a:0:{}\";s:11:\"cart_totals\";s:367:\"a:15:{s:8:\"subtotal\";i:0;s:12:\"subtotal_tax\";i:0;s:14:\"shipping_total\";i:0;s:12:\"shipping_tax\";i:0;s:14:\"shipping_taxes\";a:0:{}s:14:\"discount_total\";i:0;s:12:\"discount_tax\";i:0;s:19:\"cart_contents_total\";i:0;s:17:\"cart_contents_tax\";i:0;s:19:\"cart_contents_taxes\";a:0:{}s:9:\"fee_total\";i:0;s:7:\"fee_tax\";i:0;s:9:\"fee_taxes\";a:0:{}s:5:\"total\";i:0;s:9:\"total_tax\";i:0;}\";s:15:\"applied_coupons\";s:6:\"a:0:{}\";s:22:\"coupon_discount_totals\";s:6:\"a:0:{}\";s:26:\"coupon_discount_tax_totals\";s:6:\"a:0:{}\";s:21:\"removed_cart_contents\";s:6:\"a:0:{}\";s:8:\"customer\";s:737:\"a:27:{s:2:\"id\";s:1:\"1\";s:13:\"date_modified\";s:0:\"\";s:8:\"postcode\";s:0:\"\";s:4:\"city\";s:0:\"\";s:9:\"address_1\";s:0:\"\";s:7:\"address\";s:0:\"\";s:9:\"address_2\";s:0:\"\";s:5:\"state\";s:2:\"CA\";s:7:\"country\";s:2:\"US\";s:17:\"shipping_postcode\";s:0:\"\";s:13:\"shipping_city\";s:0:\"\";s:18:\"shipping_address_1\";s:0:\"\";s:16:\"shipping_address\";s:0:\"\";s:18:\"shipping_address_2\";s:0:\"\";s:14:\"shipping_state\";s:2:\"CA\";s:16:\"shipping_country\";s:2:\"US\";s:13:\"is_vat_exempt\";s:0:\"\";s:19:\"calculated_shipping\";s:0:\"\";s:10:\"first_name\";s:0:\"\";s:9:\"last_name\";s:0:\"\";s:7:\"company\";s:0:\"\";s:5:\"phone\";s:0:\"\";s:5:\"email\";s:16:\"local@local.test\";s:19:\"shipping_first_name\";s:0:\"\";s:18:\"shipping_last_name\";s:0:\"\";s:16:\"shipping_company\";s:0:\"\";s:14:\"shipping_phone\";s:0:\"\";}\";}',1673609410);
/*!40000 ALTER TABLE `wp_woocommerce_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_shipping_zone_locations`
--

DROP TABLE IF EXISTS `wp_woocommerce_shipping_zone_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_shipping_zone_locations` (
  `location_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zone_id` bigint unsigned NOT NULL,
  `location_code` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `location_type` varchar(40) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`location_id`),
  KEY `location_id` (`location_id`),
  KEY `location_type_code` (`location_type`(10),`location_code`(20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_shipping_zone_locations`
--

LOCK TABLES `wp_woocommerce_shipping_zone_locations` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_shipping_zone_locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_shipping_zone_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_shipping_zone_methods`
--

DROP TABLE IF EXISTS `wp_woocommerce_shipping_zone_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_shipping_zone_methods` (
  `zone_id` bigint unsigned NOT NULL,
  `instance_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `method_id` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `method_order` bigint unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_shipping_zone_methods`
--

LOCK TABLES `wp_woocommerce_shipping_zone_methods` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_shipping_zone_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_shipping_zone_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_shipping_zones`
--

DROP TABLE IF EXISTS `wp_woocommerce_shipping_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_shipping_zones` (
  `zone_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zone_name` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `zone_order` bigint unsigned NOT NULL,
  PRIMARY KEY (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_shipping_zones`
--

LOCK TABLES `wp_woocommerce_shipping_zones` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_shipping_zones` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_shipping_zones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_tax_rate_locations`
--

DROP TABLE IF EXISTS `wp_woocommerce_tax_rate_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_tax_rate_locations` (
  `location_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `location_code` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `tax_rate_id` bigint unsigned NOT NULL,
  `location_type` varchar(40) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  PRIMARY KEY (`location_id`),
  KEY `tax_rate_id` (`tax_rate_id`),
  KEY `location_type_code` (`location_type`(10),`location_code`(20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_tax_rate_locations`
--

LOCK TABLES `wp_woocommerce_tax_rate_locations` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_tax_rate_locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_tax_rate_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_woocommerce_tax_rates`
--

DROP TABLE IF EXISTS `wp_woocommerce_tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_woocommerce_tax_rates` (
  `tax_rate_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_rate_country` varchar(2) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `tax_rate_state` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `tax_rate` varchar(8) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `tax_rate_name` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `tax_rate_priority` bigint unsigned NOT NULL,
  `tax_rate_compound` int NOT NULL DEFAULT '0',
  `tax_rate_shipping` int NOT NULL DEFAULT '1',
  `tax_rate_order` bigint unsigned NOT NULL,
  `tax_rate_class` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`tax_rate_id`),
  KEY `tax_rate_country` (`tax_rate_country`),
  KEY `tax_rate_state` (`tax_rate_state`(2)),
  KEY `tax_rate_class` (`tax_rate_class`(10)),
  KEY `tax_rate_priority` (`tax_rate_priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_woocommerce_tax_rates`
--

LOCK TABLES `wp_woocommerce_tax_rates` WRITE;
/*!40000 ALTER TABLE `wp_woocommerce_tax_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_woocommerce_tax_rates` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2023-02-28 16:42:36
