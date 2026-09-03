-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sapienstech_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `sapienstech_db`
--

/*!40000 DROP DATABASE IF EXISTS `sapienstech_db`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `sapienstech_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `sapienstech_db`;

--
-- Table structure for table `answers`
--

DROP TABLE IF EXISTS `answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `answer_text` text NOT NULL,
  `answer_image_path` varchar(255) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `answers_tenant_id_index` (`tenant_id`),
  KEY `answers_question_id_index` (`question_id`),
  CONSTRAINT `answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `answers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `answers`
--

LOCK TABLES `answers` WRITE;
/*!40000 ALTER TABLE `answers` DISABLE KEYS */;
INSERT INTO `answers` VALUES (1,1,1,'شدید برخلاف محدود، وجود یون K در انجام روند انعقاد خون لازم است',NULL,0,'2026-09-02 11:41:57','2026-09-02 11:41:57'),(2,1,1,'محدود برخلاف شدید، یاخته‌های خونی با اتصال به هم، درپوش ایجاد می‌کنند',NULL,0,'2026-09-02 11:41:57','2026-09-02 11:41:57'),(3,1,1,'شدید همانند محدود، تشکیل لختهٔ خون در محل آسیب‌دیده، مشاهده می‌شود',NULL,0,'2026-09-02 11:41:57','2026-09-02 11:41:57'),(4,1,1,'محدود همانند شدید، قطعاتی بی‌رنگ و کوچک‌تر از گویچه‌های خون نقش دارند',NULL,0,'2026-09-02 11:41:57','2026-09-02 11:41:57'),(5,1,2,'هنگام باز شدن قفسهٔ سینه، فشاری مکشی درون آن‌ها به‌وجود می‌آید.',NULL,0,'2026-09-02 11:41:57','2026-09-02 11:41:57'),(6,1,2,'هنگام انقباض ماهیچه‌های صافِ دیواره، مقاومت آن‌ها کاهش پیدا می‌کند.',NULL,0,'2026-09-02 11:41:57','2026-09-02 11:41:57'),(7,1,2,'نسبت به رگ دریافت‌کنندهٔ خون از بطن چپ، رشته‌های کشسان کمتری دارند.',NULL,0,'2026-09-02 11:41:57','2026-09-02 11:41:57'),(8,1,2,'جریان خونِ کُند درون آن‌ها، امکان تبادل مناسب مواد بین خون و بافت را فراهم می‌کند.',NULL,0,'2026-09-02 11:41:57','2026-09-02 11:41:57'),(9,1,3,'فشار زیادِ واردشده از سوی قلب را تحمل می‌کنند و بسیاری از آن‌ها دریچه‌هایی دارند.',NULL,0,'2026-09-02 11:41:58','2026-09-02 11:41:58'),(10,1,3,'بیشتر حجم خون را درون خود جای می‌دهند و شبکهٔ وسیعی را در بافت‌ها ایجاد می‌کنند.',NULL,0,'2026-09-02 11:41:58','2026-09-02 11:41:58'),(11,1,3,'حلقهٔ ماهیچه‌ای در ابتدای بعضی از آن‌ها وجود دارد و تنظیم اصلی جریان خون بافت را انجام می‌دهند.',NULL,0,'2026-09-02 11:41:58','2026-09-02 11:41:58'),(12,1,3,'باعث پیوستگی جریان خون هنگام استراحت قلب می‌شوند و افزایش $CO_2$ باعث گشاد شدن آن‌ها می‌شود.',NULL,0,'2026-09-02 11:41:58','2026-09-02 11:41:58'),(13,1,4,'همهٔ آن‌ها، با حضور در ابتدای سرخرگ‌های خروجی از بطن‌ها، مانع از بازگشت خون به بطن‌ها می‌شوند.',NULL,0,'2026-09-02 11:42:00','2026-09-02 11:42:00'),(14,1,4,'همهٔ آن‌ها، در هنگام انقباض بطن‌ها، از بازگشت خونِ درونِ بطن‌ها به دهلیزها جلوگیری می‌کنند.',NULL,0,'2026-09-02 11:42:00','2026-09-02 11:42:00'),(15,1,4,'فقط یکی از آن‌ها، توسط سه قطعهٔ حاوی بافت پوششیِ چین‌خورده، ساخته شده است.',NULL,0,'2026-09-02 11:42:00','2026-09-02 11:42:00'),(16,1,4,'فقط یکی از آن‌ها، پایینی‌ترین دریچهٔ قلب محسوب می‌شود.',NULL,0,'2026-09-02 11:42:00','2026-09-02 11:42:00'),(17,1,5,'وجه تمایز یاخته‌ای با هستهٔ تکیِ گرد و یاخته‌ای با هستهٔ تکی بیضی، اندازهٔ دانه‌های سیتوپلاسمی است.',NULL,0,'2026-09-02 11:42:00','2026-09-02 11:42:00'),(18,1,5,'وجه تمایز یاخته‌ای با هستهٔ چند قسمتی و یاخته‌ای با هستهٔ دو قسمتیِ دمبلی، اندازهٔ دانه‌های سیتوپلاسمی است.',NULL,0,'2026-09-02 11:42:00','2026-09-02 11:42:00'),(19,1,5,'وجه تشابه یاخته‌ای با هستهٔ لوبیایی و یاخته‌ای با هستهٔ دو قسمتیِ رویِ هم افتاده، عدم وجود دانه‌های سیتوپلاسمی است.',NULL,0,'2026-09-02 11:42:00','2026-09-02 11:42:00'),(20,1,5,'وجه تشابه یاخته‌ای با هستهٔ دو قسمتیِ رویِ هم افتاده و یاخته‌ای با هستهٔ چند قسمتی، تیرگیِ دانه‌های سیتوپلاسمی است.',NULL,0,'2026-09-02 11:42:00','2026-09-02 11:42:00'),(21,1,6,'کاهش ساخت پروتئین در کبد - افزایش بیش از حد در مقدار ترشح هورمون ضدادراری از هیپوفیز',NULL,0,'2026-09-02 11:42:03','2026-09-02 11:42:03'),(22,1,6,'تخریب غشای پایه در مویرگ‌های کلیه - افزایش مقدار چربی واردشده به مویرگ‌های لنفی در روده',NULL,0,'2026-09-02 11:42:03','2026-09-02 11:42:03'),(23,1,6,'نزدیک‌تر شدن محل برابری فشار خون و فشار اسمزی، به انتهای سرخرگی مویرگ - مصرف دخانیات',NULL,0,'2026-09-02 11:42:03','2026-09-02 11:42:03'),(24,1,6,'کاهش آلبومین خون - افزایش حجم خونی که در هر انقباض بطنی از یک بطن خارج و وارد سرخرگ می‌شود',NULL,0,'2026-09-02 11:42:03','2026-09-02 11:42:03'),(25,1,7,'در فاصله بین نقاط C و D، پیام الکتریکی ابتدا در دیواره بین بطن‌ها و سپس در دیواره‌های خارجی بطن‌ها انتشار می‌یابد.',NULL,0,'2026-09-02 11:42:04','2026-09-02 11:42:04'),(26,1,7,'در فاصله بین نقاط B و E، ابتدا پیام الکتریکی به گره دهلیزی-بطنی رسیده و سپس میزان کشیدگی طناب‌های ارتجاعی افزایش می‌یابد.',NULL,0,'2026-09-02 11:42:04','2026-09-02 11:42:04'),(27,1,7,'در فاصله بین نقاط A و C، ابتدا حجم خون موجود در بطن‌ها به حداکثر رسیده و سپس پیام تحریک در دیواره دهلیز چپ گسترش می‌یابد.',NULL,0,'2026-09-02 11:42:04','2026-09-02 11:42:04'),(28,1,7,'در فاصله بین نقاط D و E، ابتدا حجم خون موجود در دهلیزها به حداکثر رسیده و سپس قطعات دریچه‌های سینی بسته می‌شوند.',NULL,0,'2026-09-02 11:42:04','2026-09-02 11:42:04'),(29,1,8,'در مجاورت کشاله ران چپ نسبت به زانوی چپ، تعداد بیشتری گره لنفی مشاهده می‌شود.',NULL,0,'2026-09-02 11:42:05','2026-09-02 11:42:05'),(30,1,8,'تیموس از دو قسمت نامتقارن تشکیل شده که پایین‌ترین بخش آن از بالاترین بخش قلب، بالاتر است.',NULL,0,'2026-09-02 11:42:05','2026-09-02 11:42:05'),(31,1,8,'همه اندام‌هایی از این دستگاه که با لوله گوارش مجاورت دارند، لنف خود را به مجرای لنفی نازک‌تر تخلیه می‌کنند.',NULL,0,'2026-09-02 11:42:05','2026-09-02 11:42:05'),(32,1,8,'محل اتصال مجرای لنفی راست به سیاهرگ زیرترقوه‌ای، نسبت به محل اتصال بزرگ سیاهرگ زیرین به قلب، پایین‌تر است.',NULL,0,'2026-09-02 11:42:05','2026-09-02 11:42:05'),(33,1,9,'۴',NULL,0,'2026-09-02 11:42:06','2026-09-02 11:42:06'),(34,1,9,'۳',NULL,0,'2026-09-02 11:42:06','2026-09-02 11:42:06'),(35,1,9,'۲',NULL,0,'2026-09-02 11:42:06','2026-09-02 11:42:06'),(36,1,9,'۱',NULL,0,'2026-09-02 11:42:06','2026-09-02 11:42:06'),(37,1,10,'به هنگام کاهش مقدار اکسیژن خون، ترشح هورمون اریتروپویتین از اندام دارای مویرگ ناپیوسته و منفذدار، آغاز می‌شود.',NULL,0,'2026-09-02 11:42:06','2026-09-02 11:42:06'),(38,1,10,'تنظیم میزان گویچه‌های قرمز در بدن برعهده آهن موجود در موادی مانند سبزیجات با برگ سبز تیره و گوشت قرمز است.',NULL,0,'2026-09-02 11:42:06','2026-09-02 11:42:06'),(39,1,10,'ویتامینی که به مقدار فراوان در حبوبات وجود دارد، برای تقسیم طبیعی یاخته‌ها در مغز استخوان و مناطق دیگر مورد نیاز است.',NULL,0,'2026-09-02 11:42:06','2026-09-02 11:42:06'),(40,1,10,'ویتامینی که فقط در غذاهای جانوری یافت می‌شود، برای کارکرد صحیح خود به وجود ویتامینی دیگر از خانواده B وابسته است.',NULL,0,'2026-09-02 11:42:06','2026-09-02 11:42:06'),(41,1,11,'بخشی که حجم کمتری از خون را تشکیل می‌دهد، تنها بخش مؤثر در دفاع از بدن در برابر عوامل خارجی است.',NULL,0,'2026-09-02 11:42:08','2026-09-02 11:42:08'),(42,1,11,'بخشی که حالت مایع دارد، در حفظ فشار اسمزی خون و همچنین جلوگیری از هدر رفتن گویچه‌های قرمز نقش دارد.',NULL,0,'2026-09-02 11:42:08','2026-09-02 11:42:08'),(43,1,11,'بخشی که پس از گریزانه کردن خون در بالای لوله قرار می‌گیرد، به‌دلیل محتویات خود، ظاهر قرمزرنگی به خون می‌دهد.',NULL,0,'2026-09-02 11:42:08','2026-09-02 11:42:08'),(44,1,11,'بخشی که عمدتاً از آب تشکیل شده است، به تنظیم دمای بدن و ایجاد دماهای متفاوت در نواحی مختلف بدن کمک می‌کند.',NULL,0,'2026-09-02 11:42:08','2026-09-02 11:42:08'),(45,1,12,'دریچه‌های قرار گرفته بین دهلیز و بطن، به‌منظور عبور خون باز می‌شوند.',NULL,0,'2026-09-02 11:42:09','2026-09-02 11:42:09'),(46,1,12,'تعداد یاخته‌های خونی موجود در فضای درون بطن‌ها افزایش پیدا می‌کند.',NULL,0,'2026-09-02 11:42:09','2026-09-02 11:42:09'),(47,1,12,'تعدادی از یاخته‌های ماهیچهٔ قلب منقبض شده و میزان مصرف انرژی در آن‌ها افزایش می‌یابد.',NULL,0,'2026-09-02 11:42:09','2026-09-02 11:42:09'),(48,1,12,'وضعیت دریچه‌های سینی از نظر باز یا بسته بودن، مشابه با مرحلهٔ بعدی می‌شود.',NULL,0,'2026-09-02 11:42:09','2026-09-02 11:42:09'),(49,1,13,'رگ «۲» بین دو بطن به سمتِ پایین قلب حرکت کرده و دیوارهٔ بین دو بطن را نیز اکسیژن‌رسانی می‌کند.',NULL,0,'2026-09-02 11:42:10','2026-09-02 11:42:10'),(50,1,13,'رگ «۳» شاخه‌هایی ایجاد می‌کند که خونِ پراکسیژن درون خود را به سمتِ رگ «۱» حرکت می‌دهند.',NULL,0,'2026-09-02 11:42:10','2026-09-02 11:42:10'),(51,1,13,'رگ «۱» در فاصلهٔ بین دهلیز و بطنی قرار دارد که دریچهٔ موجود در بین آن‌ها، سه قطعهٔ آویخته دارد.',NULL,0,'2026-09-02 11:42:10','2026-09-02 11:42:10'),(52,1,13,'رگ «۴» روی سطح خارجی پیراشامهٔ پوشانندهٔ بطنی با دیوارهٔ قطورتر، به طرف پایین حرکت می‌کند.',NULL,0,'2026-09-02 11:42:10','2026-09-02 11:42:10'),(53,1,14,'ماهیچهٔ ضخیم‌تری دارد، خونِ دارای اکسیژن را پس از عبور از دریچهٔ دولختی دریافت می‌کند',NULL,0,'2026-09-02 11:42:10','2026-09-02 11:42:10'),(54,1,14,'در بین دو دریچهٔ سه قطعه‌ای قرار دارد، دارای بیشترین طناب ارتجاعی متصل به دریچه است',NULL,0,'2026-09-02 11:42:10','2026-09-02 11:42:10'),(55,1,14,'محل قرارگیری گره‌های شبکهٔ هادی است، با دو منفذ سیاهرگی در ارتباط است',NULL,0,'2026-09-02 11:42:10','2026-09-02 11:42:10'),(56,1,14,'خون روشن شش‌ها را دریافت می‌کند، با چهار منفذ سیاهرگی در ارتباط است',NULL,0,'2026-09-02 11:42:10','2026-09-02 11:42:10'),(57,1,15,'وجه اشتراک آن‌ها، کمک به حرکت خون در سیاهرگ‌های ناحیهٔ شکم است.',NULL,0,'2026-09-02 11:42:11','2026-09-02 11:42:11'),(58,1,15,'وجه تفاوت آن‌ها، کاهش فشار در سیاهرگ به منظور ایجاد فشار مکشی است.',NULL,0,'2026-09-02 11:42:11','2026-09-02 11:42:11'),(59,1,15,'وجه اشتراک آن‌ها، یک‌طرفه کردن جریان خون در سیاهرگ‌های گردن است.',NULL,0,'2026-09-02 11:42:11','2026-09-02 11:42:11'),(60,1,15,'وجه تفاوت آن‌ها، نیاز به تغییر در وضعیت میان‌بند برای ایفای نقش خود است.',NULL,0,'2026-09-02 11:42:11','2026-09-02 11:42:11'),(61,1,16,'در نواحی پایین‌تر از ماهیچهٔ میان‌بند (دیافراگم) واقع شده‌اند.',NULL,0,'2026-09-02 11:42:13','2026-09-02 11:42:13'),(62,1,16,'یاخته‌های سازندهٔ آن قادر به تولید مولکول‌های کلسترول هستند.',NULL,0,'2026-09-02 11:42:13','2026-09-02 11:42:13'),(63,1,16,'خون خارج‌شده از آن‌ها به درون سیاهرگ فوق کبدی وارد می‌شود.',NULL,0,'2026-09-02 11:42:13','2026-09-02 11:42:13'),(64,1,16,'در تصفیه و بازگرداندن مواد خارج‌شده از مویرگ‌ها به خون، نقش دارند.',NULL,0,'2026-09-02 11:42:13','2026-09-02 11:42:13'),(65,1,17,'فرصت دهلیزها برای انقباض و وارد کردن خون به بطن‌ها',NULL,0,'2026-09-02 11:42:14','2026-09-02 11:42:14'),(66,1,17,'مقدار انرژی مصرف شده توسط یاخته‌های ماهیچه‌ای بطن',NULL,0,'2026-09-02 11:42:14','2026-09-02 11:42:14'),(67,1,17,'مدت زمان عبور خون از فاصلهٔ بین قطعات دریچهٔ سه لختی',NULL,0,'2026-09-02 11:42:14','2026-09-02 11:42:14'),(68,1,17,'مدت زمان باز بودن دریچه‌های سینی سرخ‌رگ ششی و آئورتی',NULL,0,'2026-09-02 11:42:14','2026-09-02 11:42:14'),(69,1,18,'ابتدا آنزیم پروترومبیناز ترشح شده و سپس پروترومبین فعال می‌شود.',NULL,0,'2026-09-02 11:42:14','2026-09-02 11:42:14'),(70,1,18,'گرده‌ها دور هم جمع شده و با چسبیدن به یکدیگر، درپوشی ایجاد می‌کنند.',NULL,0,'2026-09-02 11:42:14','2026-09-02 11:42:14'),(71,1,18,'طی تشکیل لخته، وجود کلسیم برای تبدیل فیبرین به فیبرینوژن لازم است.',NULL,0,'2026-09-02 11:42:14','2026-09-02 11:42:14'),(72,1,18,'رشته‌های پروتئینیِ محلول در خون، نقش اصلی را در تشکیل لخته ایفا می‌کنند.',NULL,0,'2026-09-02 11:42:14','2026-09-02 11:42:14'),(73,1,19,'۱',NULL,0,'2026-09-02 11:42:15','2026-09-02 11:42:15'),(74,1,19,'۲',NULL,0,'2026-09-02 11:42:15','2026-09-02 11:42:15'),(75,1,19,'۳',NULL,0,'2026-09-02 11:42:15','2026-09-02 11:42:15'),(76,1,19,'۴',NULL,0,'2026-09-02 11:42:15','2026-09-02 11:42:15'),(77,1,20,'فقط در دوران جنینی به تولید گویچه‌های قرمز می‌پردازد - قادر به ذخیرهٔ آهن آزادشده از گویچه‌های قرمز تخریب شده است',NULL,0,'2026-09-02 11:42:15','2026-09-02 11:42:15'),(78,1,20,'خون خارج‌شده از خود را توسط سیاهرگی به سیاهرگ باب وارد می‌کند - در گوارش مولکول‌های غذایی نقش ایفا می‌کند',NULL,0,'2026-09-02 11:42:15','2026-09-02 11:42:15'),(79,1,20,'در تنظیم سرعت تولید گویچه‌های قرمز نقش دارد - به کمک مویرگ‌های ناپیوستهٔ خود به تبادل مواد می‌پردازد',NULL,0,'2026-09-02 11:42:15','2026-09-02 11:42:15'),(80,1,20,'در تنظیمِ عصبیِ تنفس نقش دارد - دارای مرکزی جهت هماهنگی میان اعصاب افزاینده یا کاهندهٔ فعالیت قلب است',NULL,0,'2026-09-02 11:42:15','2026-09-02 11:42:15'),(81,1,21,'میزان تجمع گره‌های لنفی در کشاله ران در مقایسه با زانو، بیشتر است.',NULL,0,'2026-09-02 11:42:17','2026-09-02 11:42:17'),(82,1,21,'تنها رگ حاوی لنف که از پشت قلب عبور می‌کند، مجرای لنفی دارای قطر بیشتر است.',NULL,0,'2026-09-02 11:42:17','2026-09-02 11:42:17'),(83,1,21,'سیاهرگ‌های زیرترقوه‌ای راست و چپ، تقریباً در محل قوس سرخرگ آئورت به هم می‌پیوندند.',NULL,0,'2026-09-02 11:42:17','2026-09-02 11:42:17'),(84,1,21,'لنف خارج‌شده از اندام لنفی مرتبط با لوله گوارش، به مجرای لنفی سمت مخالف خود می‌ریزد.',NULL,0,'2026-09-02 11:42:17','2026-09-02 11:42:17'),(85,1,22,'بخش «۲» در انتهای طولانی‌ترین موج نوار قلب (از نظر زمانی)، منقبض می‌شود.',NULL,0,'2026-09-02 11:42:18','2026-09-02 11:42:18'),(86,1,22,'بخش «۴»، بیرونی‌ترین لایهٔ دیوارهٔ قلب و نزدیک‌ترین بخش به تیموس است.',NULL,0,'2026-09-02 11:42:18','2026-09-02 11:42:18'),(87,1,22,'در قسمتی از بخش «۴» برخلاف بخش «۲»، مقدار فراوانی رشته‌های کلاژن وجود دارد.',NULL,0,'2026-09-02 11:42:18','2026-09-02 11:42:18'),(88,1,22,'بخش «۱» و «۳»، شامل بافت پوششی چسبیده به بافت پیوندی و در تماس با نوعی مایع هستند.',NULL,0,'2026-09-02 11:42:18','2026-09-02 11:42:18'),(89,1,23,'هنگام انتشار پیام در بخش «۴»، صدایی گنگ و قوی از قلب شنیده می‌شود.',NULL,0,'2026-09-02 11:42:19','2026-09-02 11:42:19'),(90,1,23,'در بخش «۵» برخلاف بخش «۱»، دسته‌ای از تارهای تخصص‌یافته وجود دارند.',NULL,0,'2026-09-02 11:42:19','2026-09-02 11:42:19'),(91,1,23,'در بخش «۲» برخلاف بخش «۳»، یاخته‌های اصلی شبکه هادی در دیوارهٔ پشتی قرار دارند.',NULL,0,'2026-09-02 11:42:19','2026-09-02 11:42:19'),(92,1,23,'بلافاصله پس از رسیدن پیام به بخش «۳»، جریان الکتریکی می‌تواند به سمت بخش «۵» منتشر شود.',NULL,0,'2026-09-02 11:42:19','2026-09-02 11:42:19'),(93,1,24,'وارد بزرگ‌ترین شش می‌شود.',NULL,0,'2026-09-02 11:42:19','2026-09-02 11:42:19'),(94,1,24,'از جلوی بخشی از سرخرگ آئورت عبور می‌کند.',NULL,0,'2026-09-02 11:42:19','2026-09-02 11:42:19'),(95,1,24,'فشار خون بیشتری نسبت به هر رگ بزرگ مجاور خود دارد.',NULL,0,'2026-09-02 11:42:19','2026-09-02 11:42:19'),(96,1,24,'محل اتصال آن به تنهٔ اصلی سرخرگ ششی، پایین‌تر از دهلیز چپ است.',NULL,0,'2026-09-02 11:42:19','2026-09-02 11:42:19'),(97,1,25,'پایین‌ترین دریچه، در مجاورت یکی از گره‌های شبکهٔ هادی قرار دارد.',NULL,0,'2026-09-02 11:42:22','2026-09-02 11:42:22'),(98,1,25,'بالاترین دریچه، از بازگشت خون غنی از اکسیژن به بطن جلوگیری می‌کند.',NULL,0,'2026-09-02 11:42:22','2026-09-02 11:42:22'),(99,1,25,'دریچه‌ای که بزرگ‌ترین اندازه را دارد، تحت تأثیر بیشترین فشار خون در قلب، بسته می‌شود.',NULL,0,'2026-09-02 11:42:22','2026-09-02 11:42:22'),(100,1,25,'دریچه‌ای که در نزدیکی همهٔ دریچه‌های دیگر قرار دارد، از دو قطعهٔ آویخته تشکیل شده است.',NULL,0,'2026-09-02 11:42:22','2026-09-02 11:42:22'),(101,1,26,'۱',NULL,0,'2026-09-02 11:42:23','2026-09-02 11:42:23'),(102,1,26,'۲',NULL,0,'2026-09-02 11:42:23','2026-09-02 11:42:23'),(103,1,26,'۳',NULL,0,'2026-09-02 11:42:23','2026-09-02 11:42:23'),(104,1,26,'۴',NULL,0,'2026-09-02 11:42:23','2026-09-02 11:42:23'),(105,1,27,'به رشته‌های پروتئینی ضخیم متصل نشده‌اند.',NULL,0,'2026-09-02 11:42:24','2026-09-02 11:42:24'),(106,1,27,'برای تحریک خودبه‌خودی قلب اختصاص یافته‌اند.',NULL,0,'2026-09-02 11:42:24','2026-09-02 11:42:24'),(107,1,27,'دناهای خطی خود را در دو هسته نگه‌داری می‌کنند.',NULL,0,'2026-09-02 11:42:24','2026-09-02 11:42:24'),(108,1,27,'می‌توانند پیام انقباض و استراحت را به‌سرعت انتقال دهند.',NULL,0,'2026-09-02 11:42:24','2026-09-02 11:42:24'),(109,1,28,'در فاصلهٔ بین شروع تا پایان موج T، تارهای ماهیچه‌ای بطن‌ها منقبض نمی‌شوند و دریچه‌های سینی بسته می‌شوند.',NULL,0,'2026-09-02 11:42:25','2026-09-02 11:42:25'),(110,1,28,'در فاصلهٔ بین شروع تا پایان موج P، ابتدا جریان الکتریکی در سراسر دهلیزها منتشر شده و سپس انقباض دهلیز آغاز می‌شود.',NULL,0,'2026-09-02 11:42:25','2026-09-02 11:42:25'),(111,1,28,'در فاصلهٔ بین پایان موج T تا پایان موج P، دریچهٔ دولختی باز می‌شود و جریان الکتریکی از گره دهلیزی - بطنی خارج می‌شود.',NULL,0,'2026-09-02 11:42:25','2026-09-02 11:42:25'),(112,1,28,'در فاصلهٔ بین شروع تا پایان موج QRS، ابتدا بیشترین حجم خون در بطن‌ها جمع شده و سپس صدای اول قلب شنیده می‌شود.',NULL,0,'2026-09-02 11:42:25','2026-09-02 11:42:25'),(113,1,29,'«الف» و «ب»',NULL,0,'2026-09-02 11:42:28','2026-09-02 11:42:28'),(114,1,29,'«الف»، «ب» و «ج»',NULL,0,'2026-09-02 11:42:28','2026-09-02 11:42:28'),(115,1,29,'«ج»',NULL,0,'2026-09-02 11:42:28','2026-09-02 11:42:28'),(116,1,29,'«الف»، «ب»، «ج» و «د»',NULL,0,'2026-09-02 11:42:28','2026-09-02 11:42:28'),(117,1,30,'دو بطن، سهم یکسانی در تشکیل سطح جلویی قلب دارند.',NULL,0,'2026-09-02 11:42:29','2026-09-02 11:42:29'),(118,1,30,'هر دو بطن، با دو مجموعهٔ سه‌تایی از قطعات چین‌خوردهٔ بافت پوششی در تماس هستند.',NULL,0,'2026-09-02 11:42:29','2026-09-02 11:42:29'),(119,1,30,'در سطح داخلی هر دو بطن، سه برجستگی ماهیچه‌ای به طناب‌های ارتجاعی متصل شده‌اند.',NULL,0,'2026-09-02 11:42:29','2026-09-02 11:42:29'),(120,1,30,'در فاصلهٔ بین برون‌شامه و لایهٔ ماهیچه‌ای دیوارهٔ قلب، سرخرگ‌های تاجی (کرونری) قرار دارند.',NULL,0,'2026-09-02 11:42:29','2026-09-02 11:42:29');
/*!40000 ALTER TABLE `answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attempt_answers`
--

DROP TABLE IF EXISTS `attempt_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attempt_answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `attempt_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `chosen_answer_id` bigint(20) unsigned DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `attempt_answers_attempt_id_question_id_unique` (`attempt_id`,`question_id`),
  KEY `attempt_answers_tenant_id_index` (`tenant_id`),
  KEY `attempt_answers_student_id_index` (`student_id`),
  KEY `attempt_answers_question_id_index` (`question_id`),
  KEY `attempt_answers_chosen_answer_id_index` (`chosen_answer_id`),
  CONSTRAINT `attempt_answers_attempt_id_foreign` FOREIGN KEY (`attempt_id`) REFERENCES `student_test_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attempt_answers_chosen_answer_id_foreign` FOREIGN KEY (`chosen_answer_id`) REFERENCES `answers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attempt_answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attempt_answers_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attempt_answers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attempt_answers`
--

LOCK TABLES `attempt_answers` WRITE;
/*!40000 ALTER TABLE `attempt_answers` DISABLE KEYS */;
INSERT INTO `attempt_answers` VALUES (1,1,14,43,30,117,0,'2026-09-02 20:47:19'),(2,1,14,43,2,6,0,'2026-09-02 20:47:19'),(3,1,14,43,16,63,0,'2026-09-02 20:47:19'),(4,1,14,43,17,66,0,'2026-09-02 20:47:19'),(5,1,14,43,21,82,0,'2026-09-02 20:47:19'),(6,1,14,43,20,78,0,'2026-09-02 20:47:19'),(7,1,14,43,15,58,0,'2026-09-02 20:47:19'),(8,1,14,43,19,74,0,'2026-09-02 20:47:19'),(9,1,14,43,23,89,0,'2026-09-02 20:47:19'),(10,1,14,43,25,98,0,'2026-09-02 20:47:19'),(11,1,14,43,7,25,0,'2026-09-02 20:47:19'),(12,1,14,43,8,29,0,'2026-09-02 20:47:19'),(13,1,14,43,9,36,0,'2026-09-02 20:47:19'),(14,1,14,43,1,2,0,'2026-09-02 20:47:19'),(15,1,14,43,3,9,0,'2026-09-02 20:47:19'),(16,1,14,43,4,14,0,'2026-09-02 20:47:19'),(17,1,14,43,5,18,0,'2026-09-02 20:47:19'),(18,1,14,43,6,21,0,'2026-09-02 20:47:19');
/*!40000 ALTER TABLE `attempt_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_post_images`
--

DROP TABLE IF EXISTS `blog_post_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_post_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `post_id` bigint(20) unsigned NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `blog_post_images_tenant_id_index` (`tenant_id`),
  KEY `blog_post_images_post_id_index` (`post_id`),
  CONSTRAINT `blog_post_images_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blog_post_images_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_post_images`
--

LOCK TABLES `blog_post_images` WRITE;
/*!40000 ALTER TABLE `blog_post_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_post_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_posts`
--

DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `consultant_id` bigint(20) unsigned NOT NULL COMMENT 'references users.id',
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `visibility` enum('public','students_only') NOT NULL DEFAULT 'public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `blog_posts_tenant_id_index` (`tenant_id`),
  KEY `blog_posts_consultant_id_index` (`consultant_id`),
  CONSTRAINT `blog_posts_consultant_id_foreign` FOREIGN KEY (`consultant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blog_posts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
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
-- Table structure for table `books`
--

DROP TABLE IF EXISTS `books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `books` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `pdf_file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `books_tenant_id_index` (`tenant_id`),
  CONSTRAINT `books_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `books`
--

LOCK TABLES `books` WRITE;
/*!40000 ALTER TABLE `books` DISABLE KEYS */;
/*!40000 ALTER TABLE `books` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chapters`
--

DROP TABLE IF EXISTS `chapters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chapters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `book_id` bigint(20) unsigned NOT NULL,
  `chapter_number` int(11) NOT NULL,
  `chapter_title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `chapters_tenant_id_index` (`tenant_id`),
  KEY `chapters_book_id_index` (`book_id`),
  CONSTRAINT `chapters_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chapters_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chapters`
--

LOCK TABLES `chapters` WRITE;
/*!40000 ALTER TABLE `chapters` DISABLE KEYS */;
/*!40000 ALTER TABLE `chapters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consultant_student_assignments`
--

DROP TABLE IF EXISTS `consultant_student_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consultant_student_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'the consultant/staff member',
  `student_id` bigint(20) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `csa_user_id_student_id_unique` (`user_id`,`student_id`),
  KEY `csa_tenant_id_index` (`tenant_id`),
  KEY `csa_student_id_index` (`student_id`),
  CONSTRAINT `csa_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `csa_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `csa_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultant_student_assignments`
--

LOCK TABLES `consultant_student_assignments` WRITE;
/*!40000 ALTER TABLE `consultant_student_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `consultant_student_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contents`
--

DROP TABLE IF EXISTS `contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `key` varchar(100) NOT NULL COMMENT 'e.g. homepage, about, contact',
  `title` varchar(255) DEFAULT NULL,
  `body` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `contents_tenant_id_key_unique` (`tenant_id`,`key`),
  CONSTRAINT `contents_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contents`
--

LOCK TABLES `contents` WRITE;
/*!40000 ALTER TABLE `contents` DISABLE KEYS */;
/*!40000 ALTER TABLE `contents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domains`
--

DROP TABLE IF EXISTS `domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `domains` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `domain` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `domains_domain_unique` (`domain`),
  KEY `domains_tenant_id_index` (`tenant_id`),
  CONSTRAINT `domains_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domains`
--

LOCK TABLES `domains` WRITE;
/*!40000 ALTER TABLE `domains` DISABLE KEYS */;
INSERT INTO `domains` VALUES (1,1,'tenant1.sapienstech.local',1,NULL,'2026-08-26 16:46:41','2026-08-26 16:46:41');
/*!40000 ALTER TABLE `domains` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_comments`
--

DROP TABLE IF EXISTS `event_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `event_id` bigint(20) unsigned NOT NULL,
  `commenter_type` enum('user','student') NOT NULL,
  `commenter_user_id` bigint(20) unsigned DEFAULT NULL,
  `commenter_student_id` bigint(20) unsigned DEFAULT NULL,
  `comment_text` text NOT NULL,
  `commented_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_comments_tenant_id_index` (`tenant_id`),
  KEY `event_comments_event_id_index` (`event_id`),
  KEY `event_comments_commenter_user_id_index` (`commenter_user_id`),
  KEY `event_comments_commenter_student_id_index` (`commenter_student_id`),
  CONSTRAINT `event_comments_commenter_student_id_foreign` FOREIGN KEY (`commenter_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  CONSTRAINT `event_comments_commenter_user_id_foreign` FOREIGN KEY (`commenter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `event_comments_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `schedule_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_comments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_comments`
--

LOCK TABLES `event_comments` WRITE;
/*!40000 ALTER TABLE `event_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `features`
--

DROP TABLE IF EXISTS `features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `features` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `key` varchar(100) NOT NULL COMMENT 'e.g. blog, student_evaluation, chat, ai_assistant',
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `features_tenant_id_key_unique` (`tenant_id`,`key`),
  CONSTRAINT `features_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `features`
--

LOCK TABLES `features` WRITE;
/*!40000 ALTER TABLE `features` DISABLE KEYS */;
/*!40000 ALTER TABLE `features` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_comments`
--

DROP TABLE IF EXISTS `item_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `commenter_type` enum('user','student') NOT NULL,
  `commenter_user_id` bigint(20) unsigned DEFAULT NULL,
  `commenter_student_id` bigint(20) unsigned DEFAULT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `item_comments_tenant_id_index` (`tenant_id`),
  KEY `item_comments_item_id_index` (`item_id`),
  KEY `item_comments_commenter_user_id_index` (`commenter_user_id`),
  KEY `item_comments_commenter_student_id_index` (`commenter_student_id`),
  CONSTRAINT `item_comments_commenter_student_id_foreign` FOREIGN KEY (`commenter_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  CONSTRAINT `item_comments_commenter_user_id_foreign` FOREIGN KEY (`commenter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `item_comments_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `schedule_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `item_comments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_comments`
--

LOCK TABLES `item_comments` WRITE;
/*!40000 ALTER TABLE `item_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `item_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2026_08_27_172316_create_sessions_table',1),(2,'2026_08_30_000001_add_avatar_to_students_table',1),(3,'2026_09_02_122325_add_exam_metadata_columns',2),(4,'2026_09_02_130742_add_supabase_import_support',3),(5,'2026_09_02_150627_add_question_image_bbox',4),(6,'2026_09_02_151122_widen_questions_supabase_id',5),(7,'2026_09_02_163937_add_exam_card_fields',6);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `supabase_id` varchar(64) DEFAULT NULL,
  `chapter_id` bigint(20) unsigned DEFAULT NULL,
  `topic_id` bigint(20) unsigned DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_image_path` varchar(255) DEFAULT NULL,
  `question_image_bbox` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`question_image_bbox`)),
  `solution_text` text DEFAULT NULL,
  `solution_image_path` varchar(255) DEFAULT NULL,
  `question_number_in_book` varchar(50) DEFAULT NULL,
  `difficulty` enum('Easy','Medium','Hard') DEFAULT NULL,
  `question_type` varchar(50) NOT NULL DEFAULT 'multiple_choice',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `questions_supabase_id_unique` (`supabase_id`),
  KEY `questions_tenant_id_index` (`tenant_id`),
  KEY `questions_chapter_id_index` (`chapter_id`),
  KEY `questions_topic_id_index` (`topic_id`),
  CONSTRAINT `questions_chapter_id_foreign` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `questions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `questions_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES (1,1,'d4f327828c6d029651771889a01d52a6d28e2acb-q2',NULL,NULL,'در کدام مورد، مقایسهٔ دُرُستی بین روش‌های جلوگیری از هدر رفتن خون انجام شده است؟\n«به‌منظور جلوگیری از خون‌ریزی‌های .................... .»','pages/d4f327828c6d029651771889a01d52a6d28e2acb.jpg',NULL,NULL,NULL,'2','Medium','multiple_choice','2026-09-02 11:41:57','2026-09-02 11:41:57'),(2,1,'d4f327828c6d029651771889a01d52a6d28e2acb-q3',NULL,NULL,'دسته‌ای از رگ‌ها ساختار خاصی دارند که باعث می‌شود با ورود خون، قطر آن‌ها تغییر زیادی نکند و در برابر جریان خون مقاومت کنند. کدام عبارت، در ارتباط با این رگ‌ها صحیح است؟','pages/d4f327828c6d029651771889a01d52a6d28e2acb.jpg',NULL,NULL,NULL,'3','Medium','multiple_choice','2026-09-02 11:41:57','2026-09-02 11:41:57'),(3,1,'d4f327828c6d029651771889a01d52a6d28e2acb-q5',NULL,NULL,'در دستگاه گردش خون انسان، سه نوع رگ در شبکه‌ای مرتبط به هم وجود دارد. در کدام عبارت، هر دو مورد، مربوط به یک نوع از این رگ‌ها هستند؟','pages/d4f327828c6d029651771889a01d52a6d28e2acb.jpg',NULL,NULL,NULL,'5','Medium','multiple_choice','2026-09-02 11:41:58','2026-09-02 11:41:58'),(4,1,'d4f327828c6d029651771889a01d52a6d28e2acb-q1',NULL,NULL,'در خصوص دریچه‌هایی که باعث ایجاد صدای واضح و کوتاه‌ترِ قلب می‌شوند، کدام مورد درست است؟','pages/d4f327828c6d029651771889a01d52a6d28e2acb.jpg',NULL,NULL,NULL,'1','Medium','multiple_choice','2026-09-02 11:42:00','2026-09-02 11:42:00'),(5,1,'d4f327828c6d029651771889a01d52a6d28e2acb-q4',NULL,NULL,'در ارتباط با مقایسه میان انواع مختلف یاخته‌های خونی سفید، کدام مورد صحیح است؟','pages/d4f327828c6d029651771889a01d52a6d28e2acb.jpg',NULL,NULL,NULL,'4','Medium','multiple_choice','2026-09-02 11:42:00','2026-09-02 11:42:00'),(6,1,'063995be0b88fba3cb47d3f256f4d94b68fb9927-q6',NULL,NULL,'دو مورد ذکر شده در کدام گزینه، تأثیر متفاوتی بر کاهش یا افزایش احتمال بروز خیز (ادم) دارند؟','pages/063995be0b88fba3cb47d3f256f4d94b68fb9927.jpg',NULL,NULL,NULL,'6','Hard','multiple_choice','2026-09-02 11:42:03','2026-09-02 11:42:03'),(7,1,'063995be0b88fba3cb47d3f256f4d94b68fb9927-q8',NULL,NULL,'مطابق شکل زیر که نوار قلب فردی سالم را نشان می‌دهد، کدام مورد، درست است؟','pages/063995be0b88fba3cb47d3f256f4d94b68fb9927.jpg','{\"x\":351,\"y\":64,\"w\":91,\"h\":207,\"pw\":1408,\"ph\":1988}',NULL,NULL,'8','Medium','multiple_choice','2026-09-02 11:42:04','2026-09-02 11:42:04'),(8,1,'063995be0b88fba3cb47d3f256f4d94b68fb9927-q10',NULL,NULL,'به‌طور طبیعی در خصوص دستگاه لنفی یک فرد بالغ، کدام مورد درست است؟','pages/063995be0b88fba3cb47d3f256f4d94b68fb9927.jpg',NULL,NULL,NULL,'10','Hard','multiple_choice','2026-09-02 11:42:05','2026-09-02 11:42:05'),(9,1,'063995be0b88fba3cb47d3f256f4d94b68fb9927-q7',NULL,NULL,'چند مورد از موارد زیر، در خصوص ساختار بافتی قلب صحیح است؟\nالف: درون‌شامه برخلاف لایۀ میانی، در استحکام دریچه‌های قلبی نقش دارد.\nب: لایه میانی همانند درون‌شامه، یاخته‌هایی در تماس با رشته‌های پروتئینی دارد.\nج: هر بافت پیوندی متراکم موجود در لایه‌های قلب، باعث استحکام دریچه‌های قلبی می‌شود.\nد: تعداد یاخته‌های ماهیچه‌ای متصل به رشته‌های کلاژن، بیشتر از یاخته‌های ماهیچه‌ای فاقد این اتصال است.','pages/063995be0b88fba3cb47d3f256f4d94b68fb9927.jpg',NULL,NULL,NULL,'7','Medium','multiple_choice','2026-09-02 11:42:06','2026-09-02 11:42:06'),(10,1,'063995be0b88fba3cb47d3f256f4d94b68fb9927-q9',NULL,NULL,'در خصوص عوامل مورد نیاز برای تولید گویچه‌های قرمز در فرد بالغ، کدام مورد درست است؟','pages/063995be0b88fba3cb47d3f256f4d94b68fb9927.jpg',NULL,NULL,NULL,'9','Medium','multiple_choice','2026-09-02 11:42:06','2026-09-02 11:42:06'),(11,1,'ba8a6e4258c159f183294624133289e14f3e9684-q12',NULL,NULL,'خون، نوعی بافت پیوندی است که به‌طور منظم در رگ‌های خونی جریان دارد و دارای دو بخش است. کدام مورد در خصوص بخش‌های خون در فردی سالم، درست است؟','pages/ba8a6e4258c159f183294624133289e14f3e9684.jpg',NULL,NULL,NULL,'12','Medium','multiple_choice','2026-09-02 11:42:08','2026-09-02 11:42:08'),(12,1,'ba8a6e4258c159f183294624133289e14f3e9684-q15',NULL,NULL,'در هر مرحلهٔ چرخهٔ قلبی که پیام انقباض از گره‌های شبکهٔ هادی قلب خارج می‌شود، چه اتفاقی رخ می‌دهد؟','pages/ba8a6e4258c159f183294624133289e14f3e9684.jpg',NULL,NULL,NULL,'15','Medium','multiple_choice','2026-09-02 11:42:09','2026-09-02 11:42:09'),(13,1,'ba8a6e4258c159f183294624133289e14f3e9684-q13',NULL,NULL,'کدام مورد، در ارتباط با شکل زیر که نمایی از سرخرگ‌های تاجی قلب را نشان می‌دهد، صحیح است؟','pages/ba8a6e4258c159f183294624133289e14f3e9684.jpg','{\"x\":385,\"y\":65,\"w\":125,\"h\":155,\"pw\":1408,\"ph\":1988}',NULL,NULL,'13','Hard','multiple_choice','2026-09-02 11:42:10','2026-09-02 11:42:10'),(14,1,'ba8a6e4258c159f183294624133289e14f3e9684-q14',NULL,NULL,'در خصوص مقایسهٔ حفرات قلب انسان، کدام گزینه، برای تکمیل عبارت زیر نامناسب است؟\n«به‌طور معمول در بدن انسان، حفره‌ای از قلب که ................... .»','pages/ba8a6e4258c159f183294624133289e14f3e9684.jpg',NULL,NULL,NULL,'14','Medium','multiple_choice','2026-09-02 11:42:10','2026-09-02 11:42:10'),(15,1,'ba8a6e4258c159f183294624133289e14f3e9684-q11',NULL,NULL,'به علت کاهش شدید فشار خون و جهت حرکت خون در سیاهرگ‌ها که در بیشتر آن‌ها به سمت بالا است، لازم است عواملی به جریان خون در سیاهرگ‌ها کمک کنند. کدام مورد، در ارتباط با دو عاملی که به کمک یکدیگر، تأثیر نهایی خود را اعمال می‌کنند، صحیح است؟','pages/ba8a6e4258c159f183294624133289e14f3e9684.jpg',NULL,NULL,NULL,'11','Medium','multiple_choice','2026-09-02 11:42:11','2026-09-02 11:42:11'),(16,1,'2e4978ae7e054a8aa3319857383d043db3bedebc-q20',NULL,NULL,'کدام مورد، فقط دربارهٔ بعضی از اندام‌های بدن انسان که در تخریب یاخته‌های خونی قرمز آسیب‌دیده نقش دارند، درست است؟','pages/2e4978ae7e054a8aa3319857383d043db3bedebc.jpg',NULL,NULL,NULL,'20','Medium','multiple_choice','2026-09-02 11:42:13','2026-09-02 11:42:13'),(17,1,'2e4978ae7e054a8aa3319857383d043db3bedebc-q19',NULL,NULL,'در شکل زیر نوار قلب «۱» متعلق به شخصی سالم است. در حالتی که نوار قلب «۱» به نوار قلب «۲» تبدیل شده، کدام مورد در یک چرخهٔ قلبی، کاهش پیدا کرده است؟','pages/2e4978ae7e054a8aa3319857383d043db3bedebc.jpg','{\"x\":500,\"y\":80,\"w\":115,\"h\":340,\"pw\":1408,\"ph\":1988}',NULL,NULL,'19','Hard','multiple_choice','2026-09-02 11:42:14','2026-09-02 11:42:14'),(18,1,'2e4978ae7e054a8aa3319857383d043db3bedebc-q18',NULL,NULL,'کدام مورد، دربارهٔ فرایند تشکیل لخته صحیح است؟','pages/2e4978ae7e054a8aa3319857383d043db3bedebc.jpg',NULL,NULL,NULL,'18','Medium','multiple_choice','2026-09-02 11:42:14','2026-09-02 11:42:14'),(19,1,'2e4978ae7e054a8aa3319857383d043db3bedebc-q17',NULL,NULL,'با توجه به مطالب کتاب درسی در یک انسان سالم و بالغ، چند مورد زیر نادرست است؟\nالف: یاختهٔ (ب) نوعی یاختهٔ ایمنی است که در حال بیگانه خواری عامل بیگانه می‌باشد.\nب: فرایند نشان داده شده در یاختهٔ (ب)، در پلاسما به وقوع می‌پیوندد.\nج: یاختهٔ (الف)، در تشکیل بخش یاخته‌ای خون فاقد نقش است.\nد: در مسیر تولید یاختهٔ (الف)، دو نوع یاختهٔ بنیادی فعالیت دارند.','pages/2e4978ae7e054a8aa3319857383d043db3bedebc.jpg','{\"x\":200,\"y\":70,\"w\":85,\"h\":170,\"pw\":1408,\"ph\":1988}',NULL,NULL,'17','Hard','multiple_choice','2026-09-02 11:42:15','2026-09-02 11:42:15'),(20,1,'2e4978ae7e054a8aa3319857383d043db3bedebc-q16',NULL,NULL,'کدام مورد، برای تکمیل عبارت زیر مناسب است؟\n«هر اندامی از بدن یک فرد سالم که ............................. به طور حتم، .............................»','pages/2e4978ae7e054a8aa3319857383d043db3bedebc.jpg',NULL,NULL,NULL,'16','Hard','multiple_choice','2026-09-02 11:42:15','2026-09-02 11:42:15'),(21,1,'61db801b68699d28b22bbfdd122a011b4e65b229-q21',NULL,NULL,'در ارتباط با ساختار بدن یک انسان سالم و بالغ، کدام مورد نادرست است؟','pages/61db801b68699d28b22bbfdd122a011b4e65b229.jpg',NULL,NULL,NULL,'21','Hard','multiple_choice','2026-09-02 11:42:17','2026-09-02 11:42:17'),(22,1,'61db801b68699d28b22bbfdd122a011b4e65b229-q22',NULL,NULL,'با توجه به شکل مقابل که ساختار بافتی بخشی از بدن انسان را نشان می‌دهد، کدام مورد درست است؟','pages/61db801b68699d28b22bbfdd122a011b4e65b229.jpg','{\"x\":195,\"y\":110,\"w\":190,\"h\":230,\"pw\":1408,\"ph\":1988}',NULL,NULL,'22','Medium','multiple_choice','2026-09-02 11:42:18','2026-09-02 11:42:18'),(23,1,'61db801b68699d28b22bbfdd122a011b4e65b229-q24',NULL,NULL,'با توجه به شکل زیر که مربوط به نمایی از برش طولی قلب انسان می‌باشد، کدام مورد درست است؟','pages/61db801b68699d28b22bbfdd122a011b4e65b229.jpg','{\"x\":595,\"y\":100,\"w\":135,\"h\":170,\"pw\":1408,\"ph\":1988}',NULL,NULL,'24','Medium','multiple_choice','2026-09-02 11:42:19','2026-09-02 11:42:19'),(24,1,'61db801b68699d28b22bbfdd122a011b4e65b229-q23',NULL,NULL,'کدام مورد، مشخصهٔ شاخه‌ای از سرخرگِ شُشی انسان است که طول کمتری دارد؟','pages/61db801b68699d28b22bbfdd122a011b4e65b229.jpg',NULL,NULL,NULL,'23','Medium','multiple_choice','2026-09-02 11:42:19','2026-09-02 11:42:19'),(25,1,'dc8eb0888994b8fc9bbec654673471c6b798b1e0-q25',NULL,NULL,'با توجه به مطالب کتاب درسی دربارهٔ دریچه‌های قلب انسان، کدام مورد درست است؟','pages/dc8eb0888994b8fc9bbec654673471c6b798b1e0.jpg',NULL,NULL,NULL,'25','Medium','multiple_choice','2026-09-02 11:42:22','2026-09-02 11:42:22'),(26,1,'dc8eb0888994b8fc9bbec654673471c6b798b1e0-q28',NULL,NULL,'چند مورد، مشخصهٔ نزدیک‌ترین انشعاب سرخرگ تاجی (کرونری) به دریچهٔ سینی سرخرگ ششی است؟\nالف: به سمت سطح پشتی قلب می‌رود.\nب: در خون‌رسانی به بطن چپ نقش دارد.\nج: مسیری مستقیم را در کنار نوعی سیاهرگ طی می‌کند.\nد: منفذ مرتبط با آن در سرخرگ آئورت، عقب‌تر از منفذ مشابه در سمت مقابل است.','pages/dc8eb0888994b8fc9bbec654673471c6b798b1e0.jpg',NULL,NULL,NULL,'28','Medium','multiple_choice','2026-09-02 11:42:23','2026-09-02 11:42:23'),(27,1,'dc8eb0888994b8fc9bbec654673471c6b798b1e0-q26',NULL,NULL,'کدام ویژگی، دربارهٔ تعداد بیشتری از یاخته‌های ضخیم‌ترین لایهٔ دیوارهٔ قلب صادق است؟','pages/dc8eb0888994b8fc9bbec654673471c6b798b1e0.jpg',NULL,NULL,NULL,'26','Medium','multiple_choice','2026-09-02 11:42:24','2026-09-02 11:42:24'),(28,1,'dc8eb0888994b8fc9bbec654673471c6b798b1e0-q27',NULL,NULL,'با توجه به نوار قلب تهیه‌شده از یک فردِ سالم در حالت استراحت، کدام عبارت درست است؟','pages/dc8eb0888994b8fc9bbec654673471c6b798b1e0.jpg',NULL,NULL,NULL,'27','Medium','multiple_choice','2026-09-02 11:42:25','2026-09-02 11:42:25'),(29,1,'c698fa0dc67b46add2091ff01b043ebb738b61e3-q32',NULL,NULL,'کدام مورد یا موارد، دربارهٔ همهٔ صداهایی که از قلب یک فرد بالغ شنیده می‌شوند، به‌طور حتم درست است؟\nالف: در اثر بسته‌شدن همزمان دو دریچهٔ قلبی ایجاد می‌شوند.\nب: پس از پایان مرحلهٔ انقباض قسمتی از قلب شنیده می‌شوند.\nج: با قرار دادن گوشی پزشکی روی قفسهٔ سینه شنیده می‌شوند.\nد: با افزایش تعداد ضربان قلب، فاصلهٔ بین صداها نیز افزایش می‌یابد.','pages/c698fa0dc67b46add2091ff01b043ebb738b61e3.jpg',NULL,NULL,NULL,'32','Medium','multiple_choice','2026-09-02 11:42:28','2026-09-02 11:42:28'),(30,1,'c698fa0dc67b46add2091ff01b043ebb738b61e3-q31',NULL,NULL,'کدام ویژگی، دربارهٔ بطن‌های قلب انسان صادق است؟','pages/c698fa0dc67b46add2091ff01b043ebb738b61e3.jpg',NULL,NULL,NULL,'31','Hard','multiple_choice','2026-09-02 11:42:29','2026-09-02 11:42:29');
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule_events`
--

DROP TABLE IF EXISTS `schedule_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '1=Monday, 7=Sunday',
  `event_color` varchar(7) NOT NULL DEFAULT '#007bff',
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `schedule_events_tenant_id_index` (`tenant_id`),
  KEY `schedule_events_user_id_index` (`user_id`),
  CONSTRAINT `schedule_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `schedule_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule_events`
--

LOCK TABLES `schedule_events` WRITE;
/*!40000 ALTER TABLE `schedule_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedule_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule_items`
--

DROP TABLE IF EXISTS `schedule_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `week_start_date` date NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `color` varchar(50) NOT NULL,
  `item_type` enum('consultant_event','student_personal_block') NOT NULL,
  `created_by_type` enum('user','student') NOT NULL,
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_student_id` bigint(20) unsigned DEFAULT NULL,
  `link_url` varchar(2083) DEFAULT NULL,
  `book_name` varchar(255) DEFAULT NULL,
  `test_count` int(11) DEFAULT NULL,
  `page_count` int(11) DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completion_timestamp` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `schedule_items_tenant_id_index` (`tenant_id`),
  KEY `schedule_items_student_id_index` (`student_id`),
  KEY `schedule_items_created_by_user_id_index` (`created_by_user_id`),
  KEY `schedule_items_created_by_student_id_index` (`created_by_student_id`),
  CONSTRAINT `schedule_items_created_by_student_id_foreign` FOREIGN KEY (`created_by_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  CONSTRAINT `schedule_items_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `schedule_items_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedule_items_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule_items`
--

LOCK TABLES `schedule_items` WRITE;
/*!40000 ALTER TABLE `schedule_items` DISABLE KEYS */;
INSERT INTO `schedule_items` VALUES (1,1,48,'2026-08-29','as',NULL,'2026-08-29 07:15:00','2026-08-29 08:45:00','#3b82f6','consultant_event','user',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,'2026-08-30 17:28:31','2026-08-30 17:28:31'),(2,1,48,'2026-08-29','جق','بسیتنالبعهشبلشبدصش','2026-08-31 07:30:00','2026-08-31 11:45:00','#ec4899','consultant_event','user',NULL,NULL,NULL,'منسابمشنباسمباع',12,21,0,NULL,'2026-08-30 09:45:20','2026-08-30 09:45:20'),(3,1,48,'2026-08-29','جق','بسیتنالبعهشبلشبدصش','2026-08-31 07:30:00','2026-08-31 11:45:00','#ec4899','consultant_event','user',NULL,NULL,NULL,'منسابمشنباسمباع',12,21,0,NULL,'2026-08-30 09:45:21','2026-08-30 09:45:21');
/*!40000 ALTER TABLE `schedule_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_assigned_quizzes`
--

DROP TABLE IF EXISTS `student_assigned_quizzes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_assigned_quizzes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `test_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `assigned_by_user_id` bigint(20) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_at` datetime DEFAULT NULL,
  `status` enum('scheduled','in_progress','grading','completed','missed') NOT NULL DEFAULT 'scheduled',
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `saq_tenant_id_index` (`tenant_id`),
  KEY `saq_test_id_index` (`test_id`),
  KEY `saq_student_id_index` (`student_id`),
  KEY `saq_assigned_by_user_id_index` (`assigned_by_user_id`),
  CONSTRAINT `saq_assigned_by_user_id_foreign` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saq_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saq_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `saq_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_assigned_quizzes`
--

LOCK TABLES `student_assigned_quizzes` WRITE;
/*!40000 ALTER TABLE `student_assigned_quizzes` DISABLE KEYS */;
INSERT INTO `student_assigned_quizzes` VALUES (1,1,1,48,1,'2026-09-02 13:19:43','2026-06-02 10:00:00','completed',1,'2026-09-02 13:19:43','2026-09-02 15:10:28'),(2,1,1,40,1,'2026-09-02 15:10:28','2026-06-02 10:00:00','completed',1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(3,1,1,44,1,'2026-09-02 15:10:28','2026-06-02 10:00:00','missed',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(4,1,2,48,1,'2026-09-02 15:10:28','2026-07-24 09:00:00','completed',1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(5,1,2,41,1,'2026-09-02 15:10:28','2026-07-24 09:00:00','completed',1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(6,1,2,45,1,'2026-09-02 15:10:28','2026-07-24 09:00:00','grading',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(7,1,3,48,1,'2026-09-02 15:10:28','2026-08-11 08:00:00','completed',1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(8,1,3,42,1,'2026-09-02 15:10:28','2026-08-11 08:00:00','completed',1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(9,1,3,49,1,'2026-09-02 15:10:28','2026-08-11 08:00:00','completed',1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(10,1,4,48,1,'2026-09-02 15:10:28','2026-08-22 18:00:00','grading',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(11,1,4,43,1,'2026-09-02 15:10:28','2026-08-22 18:00:00','grading',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(12,1,4,46,1,'2026-09-02 15:10:28','2026-08-22 18:00:00','completed',1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(13,1,5,48,1,'2026-09-02 15:10:28','2026-08-31 11:00:00','missed',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(14,1,5,40,1,'2026-09-02 15:10:28','2026-08-31 11:00:00','missed',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(15,1,6,48,1,'2026-09-02 15:10:28','2026-09-19 10:30:00','scheduled',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(16,1,6,41,1,'2026-09-02 15:10:28','2026-09-19 10:30:00','scheduled',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(17,1,6,44,1,'2026-09-02 15:10:28','2026-09-19 10:30:00','scheduled',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(18,1,6,50,1,'2026-09-02 15:10:28','2026-09-19 10:30:00','scheduled',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(19,1,7,48,1,'2026-09-02 15:10:28','2026-09-05 08:00:00','scheduled',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(20,1,7,47,1,'2026-09-02 15:10:28','2026-09-05 08:00:00','in_progress',0,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(21,1,8,45,1,'2026-09-02 15:10:28','2026-05-10 09:30:00','completed',1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(22,1,8,51,1,'2026-09-02 15:10:28','2026-05-10 09:30:00','completed',1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(23,1,9,42,1,'2026-09-02 15:10:29','2026-08-23 14:00:00','completed',1,'2026-09-02 15:10:29','2026-09-02 15:10:29'),(24,1,9,52,1,'2026-09-02 15:10:29','2026-10-11 10:00:00','scheduled',0,'2026-09-02 15:10:29','2026-09-02 15:10:29'),(25,1,10,46,1,'2026-09-02 15:10:29','2026-08-29 11:00:00','completed',1,'2026-09-02 15:10:29','2026-09-02 15:10:29'),(26,1,10,53,1,'2026-09-02 15:10:29','2026-08-29 11:00:00','grading',0,'2026-09-02 15:10:29','2026-09-02 15:10:29'),(27,1,10,48,1,'2026-09-02 15:10:29','2026-09-16 09:00:00','scheduled',0,'2026-09-02 15:10:29','2026-09-02 15:10:29'),(28,1,11,43,1,'2026-09-02 17:13:45','2026-09-11 08:30:00','completed',1,'2026-09-02 17:13:45','2026-09-02 17:17:19');
/*!40000 ALTER TABLE `student_assigned_quizzes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_book_permissions`
--

DROP TABLE IF EXISTS `student_book_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_book_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `book_id` bigint(20) unsigned NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sbp_student_id_book_id_unique` (`student_id`,`book_id`),
  KEY `sbp_tenant_id_index` (`tenant_id`),
  KEY `sbp_book_id_index` (`book_id`),
  CONSTRAINT `sbp_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sbp_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sbp_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_book_permissions`
--

LOCK TABLES `student_book_permissions` WRITE;
/*!40000 ALTER TABLE `student_book_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_book_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_flagged_questions`
--

DROP TABLE IF EXISTS `student_flagged_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_flagged_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sfq_student_id_question_id_unique` (`student_id`,`question_id`),
  KEY `sfq_tenant_id_index` (`tenant_id`),
  KEY `sfq_question_id_index` (`question_id`),
  CONSTRAINT `sfq_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sfq_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sfq_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_flagged_questions`
--

LOCK TABLES `student_flagged_questions` WRITE;
/*!40000 ALTER TABLE `student_flagged_questions` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_flagged_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_test_attempts`
--

DROP TABLE IF EXISTS `student_test_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_test_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `test_id` bigint(20) unsigned NOT NULL,
  `status` enum('in_progress','completed','abandoned','expired') NOT NULL DEFAULT 'in_progress',
  `started_at` timestamp NULL DEFAULT NULL,
  `score_raw` decimal(6,2) DEFAULT NULL,
  `score_simple_percent` decimal(5,2) DEFAULT NULL,
  `score_negative_percent` decimal(5,2) DEFAULT NULL,
  `time_taken_seconds` int(11) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sta_tenant_id_index` (`tenant_id`),
  KEY `sta_assignment_id_index` (`assignment_id`),
  KEY `sta_student_id_index` (`student_id`),
  KEY `sta_test_id_index` (`test_id`),
  CONSTRAINT `sta_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `student_assigned_quizzes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sta_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sta_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `sta_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_test_attempts`
--

LOCK TABLES `student_test_attempts` WRITE;
/*!40000 ALTER TABLE `student_test_attempts` DISABLE KEYS */;
INSERT INTO `student_test_attempts` VALUES (1,1,1,48,1,'completed','2026-06-02 06:30:00',17.50,87.50,87.50,4590,'2026-06-02 07:46:30','2026-09-02 15:10:28','2026-09-02 15:10:28'),(2,1,2,40,1,'completed','2026-06-02 06:30:00',15.75,78.75,78.75,4590,'2026-06-02 07:46:30','2026-09-02 15:10:28','2026-09-02 15:10:28'),(3,1,4,48,2,'completed','2026-07-24 05:30:00',12.25,61.25,61.25,1530,'2026-07-24 05:55:30','2026-09-02 15:10:28','2026-09-02 15:10:28'),(4,1,5,41,2,'completed','2026-07-24 05:30:00',14.00,70.00,70.00,1530,'2026-07-24 05:55:30','2026-09-02 15:10:28','2026-09-02 15:10:28'),(5,1,7,48,3,'completed','2026-08-11 04:30:00',68.00,68.00,68.00,9180,'2026-08-11 07:03:00','2026-09-02 15:10:28','2026-09-02 15:10:28'),(6,1,8,42,3,'completed','2026-08-11 04:30:00',74.00,74.00,74.00,9180,'2026-08-11 07:03:00','2026-09-02 15:10:28','2026-09-02 15:10:28'),(7,1,9,49,3,'completed','2026-08-11 04:30:00',61.00,61.00,61.00,9180,'2026-08-11 07:03:00','2026-09-02 15:10:28','2026-09-02 15:10:28'),(8,1,12,46,4,'completed','2026-08-22 14:30:00',8.50,85.00,85.00,1020,'2026-08-22 14:47:00','2026-09-02 15:10:28','2026-09-02 15:10:28'),(9,1,21,45,8,'completed','2026-05-10 06:00:00',16.00,80.00,80.00,1275,'2026-05-10 06:21:15','2026-09-02 15:10:28','2026-09-02 15:10:28'),(10,1,22,51,8,'completed','2026-05-10 06:00:00',12.50,62.50,62.50,1275,'2026-05-10 06:21:15','2026-09-02 15:10:28','2026-09-02 15:10:28'),(11,1,23,42,9,'completed','2026-08-23 10:30:00',18.25,91.25,91.25,5100,'2026-08-23 11:55:00','2026-09-02 15:10:29','2026-09-02 15:10:29'),(12,1,25,46,10,'completed','2026-08-29 07:30:00',19.00,95.00,95.00,1020,'2026-08-29 07:47:00','2026-09-02 15:10:29','2026-09-02 15:10:29'),(14,1,28,43,11,'completed','2026-09-02 17:16:46',0.00,0.00,0.00,33,'2026-09-02 17:17:19','2026-09-02 17:17:19','2026-09-02 17:17:19');
/*!40000 ALTER TABLE `student_test_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_weekly_settings`
--

DROP TABLE IF EXISTS `student_weekly_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_weekly_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `week_start_date` date NOT NULL,
  `display_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`display_config`)),
  `last_modified_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sws_student_id_week_start_date_unique` (`student_id`,`week_start_date`),
  KEY `sws_tenant_id_index` (`tenant_id`),
  KEY `sws_last_modified_by_user_id_index` (`last_modified_by_user_id`),
  CONSTRAINT `sws_last_modified_by_user_id_foreign` FOREIGN KEY (`last_modified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sws_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sws_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_weekly_settings`
--

LOCK TABLES `student_weekly_settings` WRITE;
/*!40000 ALTER TABLE `student_weekly_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_weekly_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `grade` varchar(50) DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `major` varchar(50) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `students_tenant_id_email_unique` (`tenant_id`,`email`),
  CONSTRAINT `students_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (40,1,'مازیار علی‌پور','mahmoudieh.simin@example.net','$2y$12$AzNN76wUiZZd96f.6rT54eodXo7x.1vy7OKR1GbQ32f2pd0FUoXyC','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-03 02:41:18'),(41,1,'سارا عطایی','dhusseini@example.net','$2y$12$t2oRNd9dA.oz9cNVavj6dOvckm014a9GbZ..hOpumkG74Z98fDEOi','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:45:04'),(42,1,'پارسا داودی‌پناه','yaghoub78@example.net','$2y$12$4ePhki0Y5FMB9RU.DH2.gOqYjLudApitMy5iWfKCoWAvnjTz50z.i','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:50:27'),(43,1,'آرمین نصرتی','laleh63@example.net','$2y$12$u2n8f./xyPu5q0O5p3XLFOBZhWA0mpKqVPjc0mlHhvKtJHQbtqE0e','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:45:36'),(44,1,'محدثه پناهی','yrahmani@example.com','$2y$12$mAsDsH4Zi3i8/yW7oi7qfu/8hApcYngVSuQePEKXhSrsEYrq5qNAq','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:45:52'),(45,1,'پریسا ابراهیمی','mhusseini@example.org','$2y$12$pH6ytrt6pkVzpur3dzh5dOpYSuhzebGTR3nS/e6kVxevsxQf03bJy','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:46:05'),(46,1,'فرامرز اصلانی','salehi.anousheh@example.net','$2y$12$vMzthZH9Gz8j/LBdacwB0.ZYtLAIGjfEx4W8xmtF0PXptN9jErE8u','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:46:14'),(47,1,'تینا طاهری','namazi.mahshid@example.net','$2y$12$LNBLB/H.AqTeZ0CFKjkEkuqujwIMkyF0AeM5yBm6BSTVK1GLR9yNu','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:46:49'),(48,1,'مانی ولایتی','khorsandi.mohammad@example.net','$2y$12$.enRFrABHN54pFa5BIsEc.jmIeOPIaX2GBRZjnTsg2sj4kDCUhGa6','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:47:12'),(49,1,'محمدرضا جنت‌فریدونی','lmokri@example.org','$2y$12$s/rESs4aKb7N7eXTowhKSO9jR5zpXB/8FNRjBXyZXfC5feYw9hbO2','دوازدهم','پسر','انسانی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:47:26'),(50,1,'امیرحسین بخشی','hijazi.saman@example.org','$2y$12$7iAnReiTA5JP.nvTh.8N3.m9RyQk6nuvfgBlr4szIpwAAZboh/Ji.','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:47:38'),(51,1,'سینا رحمانی','salemi.kourosh@example.com','$2y$12$DVTBjILimVUzUL0/L7wUheWhDcboPyYxJNqn3xf5Wiv0TDkuEFQkO','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:47:48'),(52,1,'امیررضا معمار','farnaz.mahmoudi@example.org','$2y$12$x3t19Lt8iW8rAvlLqy14euJotoQmrM2IYaP7HuEUp6T8pjHdwZQ/m','دهم','پسر','ریاضی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:48:02'),(53,1,'فاطمه افضلی','ljamshidi@example.com','$2y$12$u3h8r3y6ZX0R5P7EdLe9/eF73bqKGTpRPyQTZZOTA9Js.nMtSuwXS','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:48:18'),(54,1,'آنیتا سوداگری','mtalebi@example.net','$2y$12$8qkucdBYcy5K9sN3TX/0s.xnrKGeIRLWwi91P747ZUSok/hj4vawe','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:48:36'),(55,1,'باران دولت‌شاهی','bnamdar@example.com','$2y$12$g5qAK5XuQJj0o8HEG3K6oei/nZ0c9WTtIZvTJtsTU9KWsUFXQ2qLK','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:48:56'),(56,1,'متین ستوده','shahin.ahmadi@example.org','$2y$12$IK9DJrE3Y7Mjd6fGA.7cfu6/dFRWOfBXVkeL6NUCNmig59CZtlGJG','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:49:08'),(57,1,'سیامک آقایی','meysam.asadi@example.com','$2y$12$C5SjPbNbaWTl1yRmtO8TguL5YM0UL/DRfN5UXfOmtdAv6FkDnQrm6','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:49:19'),(58,1,'آتنا مدرسی','farhad99@example.org','$2y$12$aisYm3lWOi0E852ZtrmZCesnXrgBasM23OeuTGAtW1ZYkGCt4nRKW','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:49:28'),(59,1,'منوچهر زندی','manuchehr.zandi@example.org','$2y$12$veL/rSHjSb79/vYscAhHmeZ.HKFIS0z0Pbbohn6FlvHgpO5zFAu2y','یازدهم','دختر','تجربی',NULL,NULL,NULL,'2026-08-28 17:46:13','2026-09-01 11:49:51');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `status` enum('trial','active','suspended') NOT NULL DEFAULT 'trial',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'Tenant One','tenant-one','active','2026-08-26 16:46:40','2026-08-26 16:46:40');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `test_questions`
--

DROP TABLE IF EXISTS `test_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `test_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `points` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `test_questions_test_id_question_id_unique` (`test_id`,`question_id`),
  KEY `test_questions_tenant_id_index` (`tenant_id`),
  KEY `test_questions_question_id_index` (`question_id`),
  CONSTRAINT `test_questions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `test_questions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `test_questions_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `test_questions`
--

LOCK TABLES `test_questions` WRITE;
/*!40000 ALTER TABLE `test_questions` DISABLE KEYS */;
INSERT INTO `test_questions` VALUES (1,1,11,30,1,1.11),(2,1,11,2,2,1.11),(3,1,11,16,3,1.11),(4,1,11,17,4,1.11),(5,1,11,21,5,1.11),(6,1,11,20,6,1.11),(7,1,11,15,7,1.11),(8,1,11,19,8,1.11),(9,1,11,23,9,1.11),(10,1,11,25,10,1.11),(11,1,11,7,11,1.11),(12,1,11,8,12,1.11),(13,1,11,9,13,1.11),(14,1,11,1,14,1.11),(15,1,11,3,15,1.11),(16,1,11,4,16,1.11),(17,1,11,5,17,1.11),(18,1,11,6,18,1.11);
/*!40000 ALTER TABLE `test_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tests`
--

DROP TABLE IF EXISTS `tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `test_title` varchar(255) NOT NULL,
  `lesson` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `exam_type` enum('quiz','comprehensive','progress','mock','online_quiz','single_lesson') NOT NULL DEFAULT 'quiz',
  `time_limit_minutes` int(11) DEFAULT NULL,
  `total_marks` decimal(6,2) NOT NULL DEFAULT 20.00,
  `question_count` smallint(5) unsigned DEFAULT NULL,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tests_tenant_id_index` (`tenant_id`),
  KEY `tests_created_by_user_id_index` (`created_by_user_id`),
  CONSTRAINT `tests_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tests_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tests`
--

LOCK TABLES `tests` WRITE;
/*!40000 ALTER TABLE `tests` DISABLE KEYS */;
INSERT INTO `tests` VALUES (1,1,'آزمون جامع ریاضیات — نوبت اول','ریاضی','پوشش کامل فصل‌های تابع، حد و پیوستگی با تمرکز بر مسائل ترکیبی و زمان‌دار.','comprehensive',90,20.00,45,1,'2026-09-02 13:15:55','2026-09-02 13:15:55'),(2,1,'کوئیز فصلی فیزیک (حرکت‌شناسی)','فیزیک','ارزشیابی کوتاه پایان فصل حرکت‌شناسی؛ تمرین سرعت متوسط و شتاب.','quiz',30,20.00,12,1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(3,1,'آزمون آزمایشی ۱۲ — شبیه‌سازی کنکور','علوم تجربی + ریاضی','شبیه‌سازی کامل شرایط کنکور با تحلیل تفکیکی دروس و تراز تخمینی.','comprehensive',180,100.00,60,1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(4,1,'کوئیز آنلاین شیمی (دوره‌های عناصر)','شیمی','کوئیز کوتاه آنلاین برای مرور ترندهای دوره تناوبی.','quiz',20,10.00,8,1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(5,1,'امتحان میان‌ترم ادبیات فارسی','ادبیات فارسی','میان‌ترم حضوری شامل درک مطلب، آرایه‌ها و املا.','comprehensive',75,20.00,20,1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(6,1,'آزمون جامع زیست‌شناسی (سلول و ژنتیک)','زیست‌شناسی','جمع‌بندی دو فصل سلول و ژنتیک پیش از امتحان نهایی.','comprehensive',90,20.00,35,1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(7,1,'کوئیز مروری فرمول‌های فیزیک','فیزیک','مرور سریع فرمول‌های کلیدی قبل از آزمون آزمایشی بعدی.','quiz',15,10.00,10,1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(8,1,'کوئیز سریع عربی (ترجمه و آرایه)','عربی','سنجش سرعت و دقت در ترجمه و شناسایی آرایه‌های ادبی.','quiz',25,20.00,15,1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(9,1,'آزمون جامع شیمی آلی','شیمی','ترکیبات آلی، واکنش‌ها و نام‌گذاری؛ همراه با مسائل استوکیومتری.','comprehensive',100,20.00,40,1,'2026-09-02 15:10:28','2026-09-02 15:10:28'),(10,1,'کوئیز دین و زندگی (درس ۵ تا ۷)','دین و زندگی','پرسش‌های کوتاه مفهومی از سه درس اخیر کتاب.','quiz',20,20.00,10,1,'2026-09-02 15:10:29','2026-09-02 15:10:29'),(11,1,'آزمون آزمایشی','زیست‌شناسی',NULL,'comprehensive',90,20.00,18,1,'2026-09-02 17:13:45','2026-09-02 17:13:45');
/*!40000 ALTER TABLE `tests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topics`
--

DROP TABLE IF EXISTS `topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `topics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `chapter_id` bigint(20) unsigned NOT NULL,
  `topic_title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `topics_tenant_id_index` (`tenant_id`),
  KEY `topics_chapter_id_index` (`chapter_id`),
  CONSTRAINT `topics_chapter_id_foreign` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topics_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topics`
--

LOCK TABLES `topics` WRITE;
/*!40000 ALTER TABLE `topics` DISABLE KEYS */;
/*!40000 ALTER TABLE `topics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL only for platform_admin',
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('platform_admin','tenant_admin','consultant_staff') NOT NULL DEFAULT 'consultant_staff',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_tenant_id_email_unique` (`tenant_id`,`email`),
  CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'Tenant Admin','admin@tenant1.test','$2y$12$mDPcZoXsHiAaM5E4XZz.NedogrWr7h9s7ibIWqOZvWQHvGO9s/mXq','tenant_admin',NULL,'p8zT8GdwBRS9G3MpHsA7tVs3bhPm0dsdeoICXAaxvk56cttJ8pgwZhaIiI2M','2026-09-02 08:26:04','2026-09-02 11:58:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `website_configs`
--

DROP TABLE IF EXISTS `website_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `website_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `theme` varchar(100) NOT NULL DEFAULT 'default',
  `logo_path` varchar(255) DEFAULT NULL,
  `favicon_path` varchar(255) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT NULL,
  `secondary_color` varchar(7) DEFAULT NULL,
  `font` varchar(100) DEFAULT NULL,
  `layout_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'header/footer/nav structure' CHECK (json_valid(`layout_config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `website_configs_tenant_id_unique` (`tenant_id`),
  CONSTRAINT `website_configs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `website_configs`
--

LOCK TABLES `website_configs` WRITE;
/*!40000 ALTER TABLE `website_configs` DISABLE KEYS */;
/*!40000 ALTER TABLE `website_configs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'sapienstech_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-03 13:49:17
