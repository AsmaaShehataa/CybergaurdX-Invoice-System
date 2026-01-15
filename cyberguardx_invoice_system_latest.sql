-- MySQL dump 10.13  Distrib 9.3.0, for macos15.2 (arm64)
-- 
-- CyberguardX_invoice_system_latest.sql
-- Host: localhost    Database: CyberguardX_invoice_system
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
-- Current Database: `CyberguardX_invoice_system`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `CyberguardX_invoice_system` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `CyberguardX_invoice_system`;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text,
  `tax_number` varchar(100) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,'PHP Test Client','php@test.com',NULL,NULL,NULL,1,'2026-01-06 11:11:25'),(2,'Asmaa Shehata','asmaa_shihata@yahoo.com','+201024302020','Egypt, Alexandria','',1,'2026-01-06 14:19:43'),(10,'Abderlahman Mohamed Ali','adiab@gmail.com','01550337692','Egypt, Alexandria',NULL,1,'2026-01-08 13:04:55'),(11,'Mona Ahmed','mmohamed@gmail.com','+201022228556','Egypt, Alexandria',NULL,4,'2026-01-13 12:35:54'),(12,'Asmaa Shehata','ashihata@lynks.com','+201022228556','Egypt, Alexandria',NULL,1,'2026-01-13 16:49:47');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `item_type` enum('positive','negative') DEFAULT 'positive',
  `description` text NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT INTO `invoice_items` VALUES (12,12,'positive','Defense Diploma',1.00,18000.00,18000.00),(13,13,'positive','CybergaurdX Diploma',1.00,18000.00,18000.00),(14,17,'positive','CybergaurdX Diploma',1.00,18000.00,18000.00);
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `user_id` int NOT NULL,
  `client_id` int NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `net` decimal(10,2) DEFAULT '0.00',
  `vat` decimal(10,2) DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL,
  `payment_amount` decimal(10,2) DEFAULT '0.00',
  `balance` decimal(10,2) DEFAULT '0.00',
  `offer_type` enum('none','percent','fixed') DEFAULT 'none',
  `offer_value` decimal(10,2) DEFAULT '0.00',
  `status` enum('draft','sent','paid','cancelled') DEFAULT 'draft',
  `notes` text,
  `terms_conditions` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `user_id` (`user_id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (12,'INV-202601-001',4,11,'2026-01-13','2026-02-12',18000.00,7200.00,10800.00,1512.00,12312.00,2000.00,10312.00,'percent',40.00,'draft','','','2026-01-13 12:35:54'),(13,'INV-202601-013',1,10,'2026-01-13','2026-02-12',18000.00,7200.00,10800.00,1512.00,12312.00,2000.00,10312.00,'percent',40.00,'draft','','','2026-01-13 16:59:30'),(14,'INV-20260113170503102',1,12,'2026-01-13','2026-02-12',18000.00,7200.00,10800.00,1512.00,12312.00,1999.98,10312.02,'percent',40.00,'draft',NULL,NULL,'2026-01-13 17:05:03'),(15,'INV-20260113170555852',1,12,'2026-01-13','2026-02-12',18000.00,7200.00,10800.00,1512.00,12312.00,1999.98,10312.02,'percent',40.00,'draft',NULL,NULL,'2026-01-13 17:05:55'),(16,'INV-20260113170608937',1,12,'2026-01-13','2026-02-12',18000.00,7200.00,10800.00,1512.00,12312.00,1999.98,10312.02,'percent',40.00,'draft',NULL,NULL,'2026-01-13 17:06:08'),(17,'INV-202601-017',1,10,'2026-01-13','2026-02-12',18000.00,7200.00,10800.00,1512.00,12312.00,2000.00,10312.00,'percent',40.00,'draft','','','2026-01-13 17:24:20');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('sales','admin') DEFAULT 'sales',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'CyberguardX','$2y$12$sDDeSbPUctHeqZEToAMyC.ji2dLw6zuKevMFWoznA35oFcM9waCVm','System Administrator','admin',1,'2026-01-06 10:57:34'),(4,'AyaSalesRep','$2y$12$7MkT/Zjsac0IWevpuXwOaOT2dNXqqqYUsWlbCEffH8tM59dPLNfJS','Aya Ibrahim','sales',1,'2026-01-13 10:22:37');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'CyberguardX_invoice_system'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-14 11:55:06
