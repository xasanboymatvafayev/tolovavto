-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: localhost    Database: apibot
-- ------------------------------------------------------
-- Server version	8.0.43-0ubuntu0.22.04.1

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
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `telegram_id` bigint NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,7678663640,'..');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `channels`
--

DROP TABLE IF EXISTS `channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `channels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `channelID` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `link` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `channels`
--

LOCK TABLES `channels` WRITE;
/*!40000 ALTER TABLE `channels` DISABLE KEYS */;
INSERT INTO `channels` VALUES (1,'-1002805854852',NULL,'https://t.me/YuksalPay','lock'),(3,'-1001863842352',NULL,'https://t.me/+r_voZrpPRe42ODMy','request');
/*!40000 ALTER TABLE `channels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `checkout`
--

DROP TABLE IF EXISTS `checkout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checkout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shop_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shop_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amount` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `over` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=153 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `checkout`
--

LOCK TABLES `checkout` WRITE;
/*!40000 ALTER TABLE `checkout` DISABLE KEYS */;
INSERT INTO `checkout` VALUES (1,'h5sy2eqgsk','983416','C79A5HKE18','1000','cancel','0','2025-08-09'),(2,'nf2ep99ihf','983416','C79A5HKE18','1161','cancel','0','2025-08-09'),(3,'esu5x5digl','983416','C79A5HKE18','4000','cancel','0','2025-08-09'),(4,'t7wwpqwlpb','983416','C79A5HKE18','1000','cancel','0','2025-08-09'),(5,'n1jdjs8r4p','983416','C79A5HKE18','1000','cancel','0','2025-08-09'),(6,'irqpm35gr4','983416','C79A5HKE18','1001','cancel','0','2025-08-09'),(7,'1jloituh60','983416','C79A5HKE18','1000','cancel','0','2025-08-10'),(8,'f349s9s4hg','983416','C79A5HKE18','1001','cancel','0','2025-08-10'),(9,'x8us76orvo','983416','C79A5HKE18','10000','cancel','0','2025-08-10'),(10,'clefm5dfw1','983416','C79A5HKE18','1000','cancel','0','2025-08-11'),(11,'01z2080td2','563789','9EI6SD64BU','1000','cancel','0','2025-08-11'),(12,'zw2safj7km','563789','9EI6SD64BU','1133','cancel','0','2025-08-11'),(13,'0hxv6zugc1','563789','9EI6SD64BU','1000','cancel','0','2025-08-11'),(14,'xduq91cvhq','563789','9EI6SD64BU','1000','cancel','0','2025-08-11'),(15,'4z8iq751h0','563789','9EI6SD64BU','1000','cancel','0','2025-08-11'),(16,'yskxbyuek0','563789','9EI6SD64BU','1000','cancel','0','2025-08-11'),(17,'s0vgnfw1hc','563789','9EI6SD64BU','1000','paid','4','2025-08-11'),(18,'cvpfs0w4mx','983416','C79A5HKE18','1000','paid','5','2025-08-11'),(19,'qoz76jpcgp','983416','C79A5HKE18','5000','cancel','0','2025-08-11'),(20,'fb8805b1an','563789','9EI6SD64BU','1000000','cancel','0','2025-08-12'),(21,'dqm0ve8e0n','563789','9EI6SD64BU','100000','cancel','0','2025-08-12'),(22,'kefljw3jpl','983416','C79A5HKE18','100000','cancel','0','2025-08-12'),(23,'7yn2ox3nxo','435500','911INT6R27','1000','cancel','0','2025-08-12'),(24,'i80l0g59xh','983416','C79A5HKE18','1000','paid','5','2025-08-12'),(25,'w5n5lf2ci8','435500','911INT6R27','1000','cancel','0','2025-08-12'),(26,'xyuaqht9bf','983416','C79A5HKE18','1230','cancel','0','2025-08-12'),(27,'ikxtpu8nw2','435500','911INT6R27','1010','cancel','0','2025-08-12'),(28,'8di842j4ft','435500','911INT6R27','1000','cancel','0','2025-08-12'),(29,'1hq4p7qqmv','435500','911INT6R27','1111','cancel','0','2025-08-12'),(30,'39dtz28yc1','983416','C79A5HKE18','1000','cancel','0','2025-08-12'),(31,'jswkusyvw0','983416','C79A5HKE18','1000','cancel','0','2025-08-13'),(32,'yo6frtiegb','983416','C79A5HKE18','1011','cancel','0','2025-08-13'),(33,'su7ce4ito3','494694','6GBJ9M5NYJ','12000','cancel','0','2025-08-13'),(34,'0wpuuabjyt','494694','6GBJ9M5NYJ','50000','cancel','0','2025-08-13'),(35,'b7w0prm2fn','494694','6GBJ9M5NYJ','12000','cancel','0','2025-08-13'),(36,'111au5l4wj','983416','C79A5HKE18','1000','cancel','0','2025-08-13'),(37,'zulwzyhejs','983416','C79A5HKE18','1000','cancel','0','2025-08-13'),(38,'77d5zipy2k','983416','C79A5HKE18','1900','cancel','0','2025-08-13'),(39,'egwegxvq5u','983416','C79A5HKE18','19000','cancel','0','2025-08-13'),(40,'09p38ke0js','435500','911INT6R27','1800','cancel','0','2025-08-13'),(41,'bapjvdr8xc','435500','911INT6R27','5000','cancel','0','2025-08-13'),(42,'3o7ptvqkg2','435500','911INT6R27','1000','cancel','0','2025-08-13'),(43,'kegm5d9zl7','983416','C79A5HKE18','1000','cancel','0','2025-08-13'),(44,'8oz9xpnyqi','983416','C79A5HKE18','1000','cancel','0','2025-08-13'),(45,'8le919karl','435500','911INT6R27','1012','cancel','0','2025-08-13'),(46,'e9ay3j6v1p','494694','6GBJ9M5NYJ','24000','cancel','0','2025-08-14'),(47,'ent6d9i2u3','494694','6GBJ9M5NYJ','18000','cancel','0','2025-08-14'),(48,'hyrcqonpp4','983416','C79A5HKE18','1000','cancel','0','2025-08-14'),(49,'j9k6arr8ln','494694','6GBJ9M5NYJ','1200000','cancel','0','2025-08-14'),(50,'grdcakbwuw','494694','6GBJ9M5NYJ','24000','cancel','0','2025-08-14'),(51,'gmpj3n6d0z','494694','6GBJ9M5NYJ','24000','cancel','0','2025-08-14'),(52,'z2eym26tx4','494694','6GBJ9M5NYJ','24000','cancel','0','2025-08-14'),(53,'fss81wwqd4','941431','2AIVGYLPR6','651','cancel','0','2025-08-15'),(54,'82gjj0uwg4','941431','2AIVGYLPR6','254','cancel','0','2025-08-15'),(55,'gb1ijue8ud','941431','2AIVGYLPR6','12355','cancel','0','2025-08-15'),(56,'rqkarrgnlm','941431','2AIVGYLPR6','12487','cancel','0','2025-08-15'),(57,'b7b4nnnh65','941431','2AIVGYLPR6','12200','cancel','0','2025-08-15'),(58,'24fdb5kruy','941431','2AIVGYLPR6','12341','cancel','0','2025-08-15'),(59,'r8na7kf6wx','941431','2AIVGYLPR6','120266','cancel','0','2025-08-15'),(60,'g6yp6vauge','941431','2AIVGYLPR6','12355','cancel','0','2025-08-15'),(61,'wytjk2bufh','941431','2AIVGYLPR6','12031','cancel','0','2025-08-15'),(62,'7jagu9tboi','941431','2AIVGYLPR6','5085','cancel','0','2025-08-15'),(63,'sg79ruqb78','941431','2AIVGYLPR6','5078','cancel','0','2025-08-15'),(64,'opvnuwe95h','941431','2AIVGYLPR6','5070','cancel','0','2025-08-15'),(65,'b6wzb4lgi4','941431','2AIVGYLPR6','12023','cancel','0','2025-08-15'),(66,'hhcbu07g61','941431','2AIVGYLPR6','12006','cancel','0','2025-08-15'),(67,'7gndsy7wlx','941431','2AIVGYLPR6','12081','paid','2','2025-08-15'),(68,'qj46hvr138','941431','2AIVGYLPR6','12087','cancel','0','2025-08-15'),(69,'34k707eadq','941431','2AIVGYLPR6','12098','cancel','0','2025-08-15'),(70,'ihzwiby5lh','941431','2AIVGYLPR6','12023','cancel','0','2025-08-15'),(71,'r936c1flq2','941431','2AIVGYLPR6','24008','cancel','0','2025-08-15'),(72,'g5hi1zgdr5','941431','2AIVGYLPR6','12003','cancel','0','2025-08-15'),(73,'anzax9m2jb','941431','2AIVGYLPR6','12025','cancel','0','2025-08-15'),(74,'vaqmorsppv','941431','2AIVGYLPR6','12090','cancel','0','2025-08-15'),(75,'scc9e4y0ix','941431','2AIVGYLPR6','12011','cancel','0','2025-08-15'),(76,'9ezgcjxwgx','941431','2AIVGYLPR6','24082','cancel','0','2025-08-15'),(77,'w5brm5b4a0','983416','C79A5HKE18','5000','paid','3','2025-08-15'),(78,'1e0vqclfjd','983416','C79A5HKE18','1000','paid','2','2025-08-15'),(79,'xeaoo6sdno','983416','C79A5HKE18','7000','paid','4','2025-08-15'),(80,'rvc32o11ob','941431','2AIVGYLPR6','12014','cancel','0','2025-08-15'),(81,'e98vnm7hdi','941431','2AIVGYLPR6','240036','cancel','0','2025-08-15'),(82,'22h0yrl5ah','941431','2AIVGYLPR6','12041','cancel','0','2025-08-15'),(83,'3ntpf1dwum','941431','2AIVGYLPR6','12099','cancel','0','2025-08-15'),(84,'ygrbvrsrb5','983416','C79A5HKE18','1000','cancel','0','2025-08-15'),(85,'8wtzkbxfjx','983416','C79A5HKE18','1000','cancel','0','2025-08-15'),(86,'3zt9k6y43j','983416','C79A5HKE18','1000','cancel','0','2025-08-15'),(87,'dbo9b1vytp','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(88,'vrp3am4zvc','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(89,'uskz5bgyoh','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(90,'8cvoubj4zk','941431','2AIVGYLPR6','12070','cancel','0','2025-08-15'),(91,'htgaot0f51','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(92,'gk338opdrh','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(93,'n0xc2snec2','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(94,'6dj8vf3jki','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(95,'yybgk670ed','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(96,'ism3m3kfbc','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(97,'bopfce7jrs','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(98,'zoq1k5apfn','527119','BFSGFFTVBC','1000','cancel','0','2025-08-15'),(99,'tdpzjoqs7f','478701','E07B6J5ZGI','1000','paid','3','2025-08-15'),(100,'qpt5k01dwq','478701','E07B6J5ZGI','1000','paid','3','2025-08-15'),(101,'v6bi0z12qs','478701','E07B6J5ZGI','1000','cancel','0','2025-08-15'),(102,'rlh0aacicd','478701','E07B6J5ZGI','1000','cancel','0','2025-08-15'),(103,'p6r7re4amc','478701','E07B6J5ZGI','1000','paid','2','2025-08-15'),(104,'f626oc2k7z','478701','E07B6J5ZGI','1000','cancel','0','2025-08-15'),(105,'vbfu4twk30','941431','2AIVGYLPR6','12084','cancel','0','2025-08-15'),(106,'wcynuod2my','435500','911INT6R27','8000','cancel','0','2025-08-15'),(107,'c451g0u34w','788155','3T0BZV07K3','5000','cancel','0','2025-08-16'),(108,'x6pibpr3h3','788155','3T0BZV07K3','1000','cancel','0','2025-08-16'),(109,'8jisgl32gy','478701','E07B6J5ZGI','1000','cancel','0','2025-08-16'),(110,'q5a88stg1l','478701','E07B6J5ZGI','1000','cancel','0','2025-08-16'),(111,'ubn8nsisu6','478701','E07B6J5ZGI','1000','cancel','0','2025-08-16'),(112,'ha7l599v88','478701','E07B6J5ZGI','2000','cancel','0','2025-08-16'),(113,'hpxpvgdc1x','788155','3T0BZV07K3','5000','cancel','0','2025-08-16'),(114,'0kmcj0rmc3','478701','E07B6J5ZGI','1000','cancel','0','2025-08-16'),(115,'0jxsvshf10','478701','E07B6J5ZGI','1000','cancel','0','2025-08-16'),(116,'6j44juiv46','717065','FR6E97X77M','1000','cancel','0','2025-08-16'),(117,'p49nqfclp8','478701','E07B6J5ZGI','10000','cancel','0','2025-08-16'),(118,'2sqbnqm409','478701','E07B6J5ZGI','1000','cancel','0','2025-08-16'),(119,'hv8x8h1aan','478701','E07B6J5ZGI','1000','cancel','0','2025-08-16'),(120,'rttn5oxvmj','478701','E07B6J5ZGI','1000','cancel','0','2025-08-16'),(121,'y5r7c5frao','478701','E07B6J5ZGI','1000','cancel','0','2025-08-16'),(122,'zwu9l7sizf','478701','E07B6J5ZGI','1000','cancel','0','2025-08-16'),(123,'uzx9ucjywt','983416','C79A5HKE18','1000','cancel','0','2025-08-16'),(124,'2lnol8v4yj','478701','C2KTRF7DDY','1000','cancel','0','2025-08-16'),(125,'ug76nv21lh','717065','FR6E97X77M','1000','cancel','0','2025-08-16'),(126,'cmk557tekn','717065','FR6E97X77M','1200','cancel','0','2025-08-16'),(127,'o3va9tw2iu','717065','FR6E97X77M','1000','cancel','0','2025-08-16'),(128,'dlcz9hlkr3','788155','3T0BZV07K3','1000','cancel','0','2025-08-16'),(129,'nbe2b3ku01','717065','FR6E97X77M','1000','cancel','0','2025-08-16'),(130,'20epis9g0h','882500','R0LIDU3I48','1100','cancel','0','2025-08-16'),(131,'hc1m3c9sgn','717065','FR6E97X77M','10000','cancel','0','2025-08-16'),(132,'ie7foi0wj5','788155','3T0BZV07K3','1000','cancel','0','2025-08-16'),(133,'x18plmdlk8','983416','C79A5HKE18','1000','cancel','0','2025-08-16'),(134,'mqecwglx7q','788155','3T0BZV07K3','2000','cancel','0','2025-08-16'),(135,'2rmrd2266d','882500','R0LIDU3I48','1000','cancel','0','2025-08-17'),(136,'nqlwnbobob','788155','3T0BZV07K3','1000','cancel','0','2025-08-17'),(137,'3fzildpkyx','788155','3T0BZV07K3','15000','cancel','0','2025-08-17'),(138,'aypluqh8pt','882500','R0LIDU3I48','10003','cancel','0','2025-08-17'),(139,'lnpaeafmrl','717065','FR6E97X77M','1000','cancel','0','2025-08-17'),(140,'6v1hvu6nuz','788155','3T0BZV07K3','1000','cancel','0','2025-08-17'),(141,'4117ef4mtq','717065','FR6E97X77M','1000','cancel','0','2025-08-17'),(142,'hxpadhi682','717065','FR6E97X77M','1001','cancel','0','2025-08-17'),(143,'qxs0ny3a0t','882500','R0LIDU3I48','1100','cancel','0','2025-08-17'),(144,'zd2a7mqzfo','983416','C79A5HKE18','1000','cancel','0','2025-08-17'),(145,'g074e1zm9a','882500','R0LIDU3I48','11111095432','cancel','0','2025-08-17'),(146,'568tvmthrr','882500','R0LIDU3I48','1000','cancel','0','2025-08-17'),(147,'5kk4oifhn9','882500','R0LIDU3I48','1000','cancel','0','2025-08-17'),(148,'opemc51mqd','717065','FR6E97X77M','5555','cancel','0','2025-08-17'),(149,'ul1eaqs6k9','717065','FR6E97X77M','1066','cancel','0','2025-08-17'),(150,'o8dzv9gchb','717065','FR6E97X77M','2900','cancel','0','2025-08-17'),(151,'b2jaepiztd','788155','3T0BZV07K3','15000','cancel','0','2025-08-17'),(152,'x8dujy3bbk','788155','3T0BZV07K3','15000','cancel','0','2025-08-17');
/*!40000 ALTER TABLE `checkout` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amount` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `over` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,'5337551034','5000','cancel','0','23:55:34 | 2025-08-08');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `send`
--

DROP TABLE IF EXISTS `send`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `send` (
  `send_id` int NOT NULL AUTO_INCREMENT,
  `time1` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `time2` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `start_id` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stop_id` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `admin_id` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `message_id` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reply_markup` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `step` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `time3` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `time4` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `time5` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`send_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `send`
--

LOCK TABLES `send` WRITE;
/*!40000 ALTER TABLE `send` DISABLE KEYS */;
INSERT INTO `send` VALUES (2,'22:17','22:18','0','1997962908','5337551034','2819','bnVsbA==','send',NULL,NULL,NULL);
/*!40000 ALTER TABLE `send` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shops`
--

DROP TABLE IF EXISTS `shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shops` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shop_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shop_info` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shop_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shop_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shop_address` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shop_balance` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `month_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `over_day` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shops`
--

LOCK TABLES `shops` WRITE;
/*!40000 ALTER TABLE `shops` DISABLE KEYS */;
INSERT INTO `shops` VALUES (1,'')
/*!40000 ALTER TABLE `shops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `balance` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deposit` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `time` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `step` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'5337551034','1','1','08.08.2025','23:54','member','qosh'),(2,'7678663640','5000','0','08.08.2025','23:54','member','null'),(3,'5563656135','826222762','826297762','09.08.2025','00:35','member','null'),(4,'7074605949','0','0','09.08.2025','14:11','member','null'),(5,'6212122130','0','0','09.08.2025','14:11','member','null'),(6,'7830143866','5000','20000','09.08.2025','14:14','member','null'),(7,'1961993468','0','0','09.08.2025','20:07','member','null'),(8,'7495301034','0','15000','09.08.2025','20:17','member','null'),(9,'7611809524','0','0','09.08.2025','20:48','member','null'),(10,'7006961341','0','15000','09.08.2025','21:11','member','null'),(11,'8379745575','0','0','10.08.2025','16:15','member','null'),(12,'826214942','0','0','10.08.2025','17:12','member','null'),(13,'7938168138','0','0','10.08.2025','20:07','member','null'),(14,'7829092154','0','0','10.08.2025','22:27','member','null'),(15,'6596335445','0','0','10.08.2025','22:29','member',NULL),(16,'1306019543','0','0','10.08.2025','22:37','member','null'),(17,'7386328037','0','0','10.08.2025','22:39','member','add_kassa_info-cHpkZXMga2Fzc2E=-@testuchun'),(18,'5119543327','0','0','10.08.2025','22:39','member','null'),(19,'1174393071','0','0','10.08.2025','22:44','member','null'),(20,'6102003176','0','0','10.08.2025','23:00','member','null'),(21,'7651652674','0','0','11.08.2025','10:02','member','null'),(22,'7805306766','0','15000','11.08.2025','10:25','member','null'),(23,'6570342010','0','0','11.08.2025','18:20','member','null'),(24,'6781285359','0','0','11.08.2025','20:25','member','null'),(25,'1881974412','0','0','11.08.2025','22:58','member','null'),(26,'7936640055','0','0','12.08.2025','11:15','member','null'),(27,'7403473629','0','0','13.08.2025','19:24','member','null'),(28,'7595249138','0','0','13.08.2025','20:32','member','null'),(29,'8071558887','0','0','13.08.2025','21:19','member',NULL),(30,'7744168278','0','0','13.08.2025','23:50','member','null'),(31,'6216912354','0','0','14.08.2025','07:10','member','null'),(32,'323220633','0','15000','14.08.2025','08:48','member','null'),(33,'5721464933','0','30000','14.08.2025','09:24','member','null'),(34,'5504419408','0','0','14.08.2025','19:56','member',NULL),(35,'5958239714','0','0','15.08.2025','14:28','member','null'),(36,'5383623467','0','0','15.08.2025','15:08','member','null'),(37,'7432599765','0','15000','15.08.2025','15:08','member','null'),(38,'6909171331','0','15000','15.08.2025','15:10','member','null'),(39,'998588038','0','0','15.08.2025','16:18','member','null'),(40,'5780787848','0','0','15.08.2025','17:06','member','null'),(41,'7908103990','0','0','15.08.2025','18:14','member','null'),(42,'7608526466','0','0','15.08.2025','19:13','member',NULL),(43,'7692358969','0','0','15.08.2025','20:48','member',NULL),(44,'5314099455','0','0','15.08.2025','20:49','member','null'),(45,'7460485409','136788754307596','136788754322596','15.08.2025','21:04','member','null'),(46,'6053945157','0','0','15.08.2025','21:07','member','null'),(47,'8475071379','10000','25000','16.08.2025','00:25','member','null'),(48,'7962134240','0','0','16.08.2025','01:33','member','add_kassa_address-CLICK'),(49,'7858432033','0','0','16.08.2025','09:52','member','null'),(50,'5536950091','0','0','16.08.2025','20:27','member',NULL),(51,'420831885','0','0','16.08.2025','20:36','member',NULL),(52,'2134380527','0','0','16.08.2025','20:49','member',NULL),(53,'7204584902','0','0','16.08.2025','20:49','member',NULL),(54,'6087884705','0','0','16.08.2025','20:51','member',NULL),(55,'1997962908','0','0','16.08.2025','21:17','member',NULL),(56,'8221297293','0','0','16.08.2025','22:18','member','null'),(57,'1374051648','0','0','16.08.2025','22:20','member',NULL),(58,'7132566024','0','0','16.08.2025','22:20','member',NULL),(59,'-1002097227394','0','0','16.08.2025','22:24','member',NULL),(60,'1763228347','0','0','16.08.2025','22:48','member','null'),(61,'6268311864','0','0','17.08.2025','00:29','member','null'),(62,'1343875572','0','0','17.08.2025','11:09','member','add_kassa');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-17  9:43:52
