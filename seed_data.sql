-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: users_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `faculty`
--

LOCK TABLES `faculty` WRITE;
/*!40000 ALTER TABLE `faculty` DISABLE KEYS */;
INSERT INTO `faculty` VALUES (1,'Education'),(2,'Management'),(3,'Science and Technology'),(4,'Humanities'),(5,'Law'),(6,'Engineering');
/*!40000 ALTER TABLE `faculty` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `course`
--

LOCK TABLES `course` WRITE;
/*!40000 ALTER TABLE `course` DISABLE KEYS */;
INSERT INTO `course` VALUES (1,'B.Ed1',1,'Semester','4 Years'),(2,'BICTE',1,'Semester','4 Years'),(3,'BBA',2,'Semester','4 Years'),(4,'BBS',2,'Year','4 Years'),(5,'BBM',2,NULL,'4 Years'),(6,'BSc.CSIT',3,'Semester','4 Years'),(7,'BIT',3,'Semester','4 Years'),(8,'BE Computer',3,NULL,'4 Years'),(9,'BA',4,'Year','4 Years'),(10,'Mass Communication',4,'Year','4 Years'),(11,'BALLB',5,'Year',NULL),(12,'LLB',5,'Year',NULL),(13,'BE Computer Engineering',6,'Semester',NULL),(14,'Arts',4,'','4 Years'),(15,'B.Ed Maths',1,'Semester','4 Years'),(16,'B.Ed Science',1,NULL,'3 Years'),(17,'BE Chemical Engineering',6,'Semester',NULL),(18,'Mechanical Engineering',6,'Semester',NULL),(19,'MBA',2,'Semester','4 Years'),(21,'BIM',2,'Year','4 Years');
/*!40000 ALTER TABLE `course` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-02 15:09:36
