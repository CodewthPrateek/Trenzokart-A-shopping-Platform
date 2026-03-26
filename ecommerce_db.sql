-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql210.byetcluster.com
-- Generation Time: Mar 26, 2026 at 10:36 AM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41395047_ecommerce_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'Prateek Verma', '$2y$10$TUaX/lIi/gFxZlW6hl0rFeoymKfFiPWYfsjYjJ/mwBTJHrPRg.gDe', '2026-03-10 16:20:17');

-- --------------------------------------------------------

--
-- Table structure for table `assistants`
--

CREATE TABLE `assistants` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `special_id` varchar(20) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_deductions`
--

CREATE TABLE `commission_deductions` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `month` varchar(7) NOT NULL,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `commission_rate` decimal(5,2) DEFAULT 10.00,
  `commission_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','deducted') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commission_deductions`
--

INSERT INTO `commission_deductions` (`id`, `vendor_id`, `month`, `total_revenue`, `commission_rate`, `commission_amount`, `status`, `created_at`) VALUES
(1, 1, '2026-03', '142122.00', '10.00', '14212.20', 'deducted', '2026-03-15 06:15:46');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_boys`
--

CREATE TABLE `delivery_boys` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `company` varchar(100) DEFAULT NULL,
  `vehicle_no` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_boys`
--

INSERT INTO `delivery_boys` (`id`, `name`, `phone`, `email`, `password`, `vendor_id`, `status`, `created_at`, `company`, `vehicle_no`) VALUES
(1, 'Prashant Lodhi', '9389941670', 'prashnat@gmail.com', '$2y$10$RsJepGAJO/ue/oqkD9DtO.JaI5VssMrF98UZj6mw9Ajz3On7YdLZa', 1, 'active', '2026-03-14 08:19:14', NULL, NULL),
(2, 'Prashant Lodhi', '7983041855', '', '$2y$10$guAFSdWOIFVpHrJiUZu1O.yEHVVVgh17kZvFwVMyVIAXvpAR5kfCq', NULL, 'active', '2026-03-23 07:48:40', 'Delhivery', 'UP 13 BF 4035'),
(3, 'Bhupesh Bhardwaj', '7983041822', '', '$2y$10$ufcIWERV.nmM9dMUqenQJOolvDQydZ/FglDwq0TPuQ8vzeQd3JgLi', NULL, 'active', '2026-03-23 08:12:20', 'Xpressbees', 'UP13CL1445');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` enum('broadcast','targeted','sale','policy','warning','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `vendor_id` int(11) DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `valid_until` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `priority`, `vendor_id`, `is_pinned`, `valid_until`, `created_by`, `created_at`) VALUES
(1, 'broadcast', 'Diwali Sale', 'hello venondrs here i am going to tell you diwaaliu isale started now', 'urgent', NULL, 0, '2026-04-20', 1, '2026-03-15 06:31:53');

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_reads`
--

INSERT INTO `notification_reads` (`id`, `notification_id`, `vendor_id`, `read_at`) VALUES
(1, 1, 1, '2026-03-15 06:35:54');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(20) DEFAULT 'cod',
  `addressed` text DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `delivery_boy_id` int(11) DEFAULT NULL,
  `courier_name` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `pickup_at` datetime DEFAULT NULL,
  `pickup_confirmed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `full_name`, `phone`, `address`, `city`, `state`, `pincode`, `total_amount`, `status`, `created_at`, `payment_method`, `addressed`, `confirmed_at`, `dispatched_at`, `shipped_at`, `delivered_at`, `vendor_id`, `delivery_boy_id`, `courier_name`, `tracking_number`, `pickup_at`, `pickup_confirmed`) VALUES
(1, 1, NULL, NULL, 'Prateek Verma, kazipura, Bsr, Uttar Pradesh - 203001', NULL, NULL, NULL, '6194.00', 'cancelled', '2026-03-09 16:30:25', 'cod', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0),
(2, 2, NULL, NULL, 'Prashnat Kumar, bhalimpura, Bsr, Uttar Pradesh - 203001', NULL, NULL, NULL, '12263.00', 'cancelled', '2026-03-10 13:09:53', 'cod', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0),
(3, 2, NULL, NULL, 'Prashnat Kumar, ladak, BSR, Maharashtra - 203001', NULL, NULL, NULL, '3009.00', 'cancelled', '2026-03-10 13:14:20', 'upi', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0),
(4, 1, NULL, NULL, 'Prateek Verma, efgdgggd, fg, Uttar Pradesh - 203001', NULL, NULL, NULL, '3009.00', 'cancelled', '2026-03-10 15:56:54', 'card', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0),
(5, 4, NULL, NULL, 'Tushar verma, Pakistan, lahore, Other - 203001', NULL, NULL, NULL, '6158.00', 'cancelled', '2026-03-10 16:12:04', 'card', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0),
(6, 4, NULL, NULL, 'Tushar verma, lashore, ism, Other - 203001', NULL, NULL, NULL, '7255.00', 'cancelled', '2026-03-10 16:33:20', 'cod', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0),
(7, 1, NULL, NULL, 'Prateek Verma, kazipura bulandshahr, bulandshahr, Uttar Pradesh - 203001', NULL, NULL, NULL, '131622.00', 'delivered', '2026-03-11 04:32:51', 'cod', NULL, '2026-03-11 04:41:02', NULL, '2026-03-11 05:39:00', '2026-03-11 05:39:06', 1, NULL, NULL, NULL, NULL, 0),
(8, 1, NULL, NULL, 'Prateek Verma, asdf, dgfs, Telangana - 203001', NULL, NULL, NULL, '1500.00', 'delivered', '2026-03-12 15:30:37', 'cod', NULL, '2026-03-12 15:31:31', NULL, '2026-03-14 06:00:41', '2026-03-14 06:00:46', 1, NULL, NULL, NULL, NULL, 0),
(9, 1, NULL, NULL, 'Prateek Verma, hajsghja, bnser, Uttar Pradesh - 203001', NULL, NULL, NULL, '4500.00', 'delivered', '2026-03-13 16:12:02', 'cod', NULL, '2026-03-13 16:13:15', NULL, '2026-03-13 16:13:26', '2026-03-13 16:13:39', 1, NULL, NULL, NULL, NULL, 0),
(10, 1, NULL, NULL, 'Prateek Verma, assddf, vbds, Uttar Pradesh - 203001', NULL, NULL, NULL, '1500.00', 'delivered', '2026-03-14 06:06:51', 'cod', NULL, '2026-03-14 06:20:15', NULL, '2026-03-14 06:21:40', '2026-03-14 06:21:46', 1, NULL, NULL, NULL, NULL, 0),
(11, 1, NULL, NULL, 'Prateek Verma, dfeges, bul, Uttar Pradesh - 203001', NULL, NULL, NULL, '11993.00', 'cancelled', '2026-03-14 08:53:21', 'cod', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0),
(12, 1, NULL, NULL, 'Prateek Verma, dfkojsr, bul, Uttar Pradesh - 203001', NULL, NULL, NULL, '9000.00', 'cancelled', '2026-03-14 08:55:00', 'cod', NULL, '2026-03-14 16:05:37', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0),
(13, 1, 'Prateek Verma', '6398667276', 'Prateek Verma, kazipura bulandshahr , bulandshahr, Uttar Pradesh - 203001', NULL, NULL, NULL, '3000.00', 'delivered', '2026-03-14 16:15:29', 'cod', NULL, '2026-03-15 04:50:46', NULL, '2026-03-15 04:50:52', '2026-03-15 04:50:56', 1, NULL, NULL, NULL, NULL, 0),
(14, 1, 'Prateek Verma', '6398667276', 'Prateek Verma, kazipura, bulandshahr, Uttar Pradesh - 203001', NULL, NULL, NULL, '5030.00', 'delivered', '2026-03-15 16:52:16', 'cod', NULL, '2026-03-15 16:57:55', NULL, '2026-03-22 04:57:04', '2026-03-22 12:21:53', 1, 2, NULL, NULL, '2026-03-23 00:54:28', 1),
(15, 1, 'Prateek Verma', '6398667276', 'Prateek Verma, kazipura, bulandshahr, Uttar Pradesh - 203001', NULL, NULL, NULL, '3710.00', 'delivered', '2026-03-22 08:05:15', 'cod', NULL, '2026-03-22 12:21:58', '2026-03-22 05:22:03', NULL, '2026-03-23 07:54:52', 1, 2, NULL, NULL, '2026-03-23 00:52:13', 1),
(16, 1, 'Prateek Verma', '6398667276', 'Prateek Verma, kazipura, bulandshahr, Uttar Pradesh - 203001', NULL, NULL, NULL, '4500.00', 'delivered', '2026-03-22 12:03:41', 'cod', NULL, '2026-03-22 12:05:07', '2026-03-22 05:15:12', '2026-03-22 12:21:32', '2026-03-22 12:21:38', 1, NULL, 'Delhivery', 'DL52277241IN', NULL, 0),
(17, 1, 'Prateek Verma', '6398667276', 'Prateek Verma, kazipura, bulandshahr, Uttar Pradesh - 203001', NULL, NULL, NULL, '1500.00', 'delivered', '2026-03-23 08:13:49', 'cod', NULL, '2026-03-23 08:17:26', '2026-03-23 01:17:33', '2026-03-23 15:58:59', '2026-03-23 15:59:07', 1, 3, 'Bluedart', 'BD423496795', NULL, 0),
(18, 1, 'Prateek Verma', '6398667276', 'Prateek Verma, kazipura, bulandshahr, Uttar Pradesh - 203001', NULL, NULL, NULL, '2030.00', 'delivered', '2026-03-23 08:20:20', 'cod', NULL, '2026-03-23 08:23:14', '2026-03-23 01:23:22', '2026-03-23 08:28:04', '2026-03-23 08:28:50', 3, 3, 'Xpressbees', 'XB63802976IN', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 13, 13, 2, '1500.00'),
(2, 14, 13, 3, '1500.00'),
(3, 14, 11, 1, '380.00'),
(4, 14, 12, 1, '150.00'),
(5, 15, 13, 2, '1500.00'),
(6, 15, 12, 1, '150.00'),
(7, 15, 11, 1, '380.00'),
(8, 15, 10, 1, '180.00'),
(9, 16, 13, 3, '1500.00'),
(10, 17, 13, 1, '1500.00'),
(11, 18, 11, 1, '380.00'),
(12, 18, 12, 1, '150.00'),
(13, 18, 13, 1, '1500.00');

-- --------------------------------------------------------

--
-- Table structure for table `order_tracking`
--

CREATE TABLE `order_tracking` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(100) NOT NULL,
  `location` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `updated_by` enum('vendor','delivery_boy','system') DEFAULT 'system',
  `updated_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_tracking`
--

INSERT INTO `order_tracking` (`id`, `order_id`, `status`, `location`, `description`, `updated_by`, `updated_by_id`, `created_at`) VALUES
(1, 15, 'Picked Up', 'Delivery Boy', 'Package picked up by delivery boy â€” Prashant Lodhi', 'delivery_boy', 2, '2026-03-23 07:52:13'),
(2, 15, 'Picked Up', 'Bsr', '', 'delivery_boy', 2, '2026-03-23 07:53:05'),
(3, 14, 'Picked Up', 'Delivery Boy', 'Package picked up by delivery boy â€” Prashant Lodhi', 'delivery_boy', 2, '2026-03-23 07:54:28'),
(4, 15, 'Picked Up', 'Bse', '', 'delivery_boy', 2, '2026-03-23 07:54:52'),
(5, 15, 'Delivered', 'Bse', 'Package successfully delivered âœ…', 'delivery_boy', 2, '2026-03-23 07:54:52'),
(6, 17, 'Picked Up', 'Bulandshahar', '', 'delivery_boy', 3, '2026-03-23 08:14:22'),
(7, 17, 'Picked Up', 'Bulandshahar', '', 'delivery_boy', 3, '2026-03-23 08:18:46'),
(8, 17, 'Picked Up', 'Bulandshahar', '', 'delivery_boy', 3, '2026-03-23 08:20:26'),
(9, 17, 'Picked Up', 'Bulandshahar', '', 'delivery_boy', 3, '2026-03-23 08:23:46'),
(10, 18, 'Picked Up', 'Rajdarbar', '', 'delivery_boy', 3, '2026-03-23 08:24:11'),
(11, 18, 'In Transit', 'Bhoor', '', 'delivery_boy', 3, '2026-03-23 08:24:32'),
(12, 18, 'At Hub', 'Thesil', '', 'delivery_boy', 3, '2026-03-23 08:25:07'),
(13, 18, 'Out for Delivery', 'Saina adda', '', 'delivery_boy', 3, '2026-03-23 08:26:31'),
(14, 18, 'Delivery Attempted', 'Bre', '', 'delivery_boy', 3, '2026-03-23 08:41:39'),
(15, 18, 'Delivery Attempted', 'Bre', '', 'delivery_boy', 3, '2026-03-23 08:53:59'),
(16, 17, 'In Transit', 'Lucknow', '', 'delivery_boy', 3, '2026-03-23 09:00:37'),
(17, 17, 'In Transit', 'Lucknow', '', 'delivery_boy', 3, '2026-03-23 09:03:21'),
(18, 17, 'At Hub', 'Sharna', '', 'delivery_boy', 3, '2026-03-23 09:03:31');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `vendor_id` int(11) DEFAULT NULL,
  `vendor_commission` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `category`, `image`, `stock`, `vendor_id`, `vendor_commission`) VALUES
(1, 'Cotton T-Shirt', 'Comfortable everyday t-shirt', '299.00', 'Clothes', 'uploads/products/product_1774288455_1_903.jpg', 50, 1, '0.00'),
(2, 'Denim Jeans', 'Stylish slim fit jeans', '999.00', 'Clothes', 'uploads/products/product_1774289064_1_956.jpg', 30, 1, '0.00'),
(3, 'Floral Dress', 'Beautiful floral summer dress', '799.00', 'Clothes', 'uploads/products/product_1773737137_138.jpg', 25, 1, '0.00'),
(4, 'Casual Hoodie', 'Warm and comfortable hoodie', '1299.00', 'Clothes', 'uploads/products/product_1773737048_441.jpg', 40, 1, '0.00'),
(5, 'Wireless Earphones', 'High quality sound earphones', '1499.00', 'Electronics', 'uploads/products/product_1773741282_900.jpg', 20, 2, '0.00'),
(6, 'Smart Watch', 'Feature rich smart watch', '2999.00', 'Electronics', 'uploads/products/product_1773741080_458.jpg', 15, 2, '0.00'),
(7, 'Phone Charger', 'Fast charging USB-C charger', '499.00', 'Electronics', 'uploads/products/product_1773740908_695.jpg', 60, 2, '0.00'),
(8, 'Bluetooth Speaker', 'Portable wireless speaker', '1999.00', 'Electronics', 'uploads/products/product_1773741203_536.jpg', 18, 2, '0.00'),
(9, 'India Gate Basmati Rice 5kg', 'India Gate Premium basmati rice', '450.00', 'Grocery', 'uploads/products/product_1774290091_1_877.jpg,uploads/products/product_1774290091_2_941.jpg,uploads/products/product_1774290091_3_869.jpg', 100, 3, '0.00'),
(10, 'Cooking Oil 1L', 'Fortune mustard oil', '200.00', 'Grocery', 'uploads/products/product_1774289896_1_135.jpg,uploads/products/product_1774289896_2_856.jpg,uploads/products/product_1774289896_3_630.jpg', 80, 3, '0.00'),
(11, 'Atta 10kg', 'Whole wheat flour', '680.00', 'Grocery', 'uploads/products/product_1774289635_1_729.jpg,uploads/products/product_1774289635_2_257.jpg,uploads/products/product_1774289635_3_559.jpg', 90, 3, '0.00'),
(12, 'Masoor Dal', 'Masoor dal premium quality', '150.00', 'Grocery', 'uploads/products/product_1774289509_1_613.jpg,uploads/products/product_1774289509_2_461.jpg', 70, 3, '0.00'),
(13, 'Cotton Shirt', 'Premium quality cotton shirt made from soft, breathable fabric for all-day comfort. Designed with a modern fit and durable stitching, making it perfect for casual as well as semi-formal wear. Lightweight, skin-friendly, and easy to maintain. Ideal for daily use in all seasons.', '1500.00', 'Clothes', 'uploads/products/product_1773415472_817.jpg', 15, 1, '0.00'),
(14, 'Tata Salt', 'Tata Salt is a trusted iodized salt brand in India, known for its purity and quality.\r\nIt is popularly called â€œDesh Ka Namakâ€ and helps provide essential iodine for a healthy life.', '30.00', 'Grocery', 'uploads/products/product_1774290329_1_648.jpg,uploads/products/product_1774290329_2_264.jpg,uploads/products/product_1774290329_3_761.jpg', 50, 3, '0.00'),
(15, 'Coca Cola Carbonated Soft Drink Can 300 ml', 'Coca-Cola Carbonated Soft Drink Can (300 ml) is a refreshing beverage known for its classic taste and fizzy sensation.', '30.00', 'Food & Beverages', 'uploads/products/product_1774290672_1_627.jpg,uploads/products/product_1774290672_2_942.jpg,uploads/products/product_1774290672_3_331.jpg', 100, 5, '0.00'),
(16, 'Sprite 300ml', 'Sprite Refreshing drink', '30.00', 'Food & Beverages', 'uploads/products/product_1774290817_1_908.jpg', 200, 5, '0.00'),
(17, 'Red  Bull 250  ml', 'Red Bull a enery drink which give you a wings', '130.00', 'Food & Beverages', 'uploads/products/product_1774291095_2_847.jpg,uploads/products/product_1774291095_3_106.jpg', 200, 5, '0.00'),
(18, 'Trenzonic V1 headphones', 'Feature,Details\r\nMaterial,Reinforced Metal & Premium Foam\r\nBattery Life,Up to 40 Hours\r\nCharging,Type-C Fast Charge\r\nStyle,Over-Ear Wireless', '1500.00', 'Electronics', 'uploads/products/product_1774527799_1_823.jpg,uploads/products/product_1774527799_2_946.jpg,uploads/products/product_1774527799_3_776.jpg,uploads/products/product_1774527799_4_660.jpg', 20, 6, '0.00');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `comment` text NOT NULL,
  `media` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `user_id`, `rating`, `comment`, `media`, `created_at`, `updated_at`) VALUES
(1, 13, 1, 4, 'value for money', '', '2026-03-23 10:52:12', '2026-03-23 10:52:12');

-- --------------------------------------------------------

--
-- Table structure for table `return_requests`
--

CREATE TABLE `return_requests` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pickup_completed` tinyint(1) DEFAULT 0,
  `pickup_at` timestamp NULL DEFAULT NULL,
  `refund_method` varchar(20) DEFAULT NULL,
  `refund_upi` varchar(100) DEFAULT NULL,
  `refund_bank_name` varchar(100) DEFAULT NULL,
  `refund_account_no` varchar(50) DEFAULT NULL,
  `refund_ifsc` varchar(20) DEFAULT NULL,
  `refund_holder` varchar(100) DEFAULT NULL,
  `refund_status` varchar(20) DEFAULT NULL,
  `refund_at` datetime DEFAULT NULL,
  `handled_by` varchar(50) DEFAULT NULL,
  `handler_id` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_requests`
--

INSERT INTO `return_requests` (`id`, `order_id`, `user_id`, `reason`, `description`, `status`, `created_at`, `pickup_completed`, `pickup_at`, `refund_method`, `refund_upi`, `refund_bank_name`, `refund_account_no`, `refund_ifsc`, `refund_holder`, `refund_status`, `refund_at`, `handled_by`, `handler_id`, `updated_at`) VALUES
(1, 13, 1, 'Size/fit issue', 'small Size', 'approved', '2026-03-15 05:14:06', 1, '2026-03-15 06:27:31', 'upi', 'Prateek@ybl', NULL, NULL, NULL, NULL, 'processed', '2026-03-23 00:07:42', NULL, NULL, '2026-03-23 00:07:42'),
(2, 16, 1, 'Damaged/defective', '', 'approved', '2026-03-22 16:38:21', 1, '2026-03-22 16:39:34', 'upi', 'prateek@ybll', NULL, NULL, NULL, NULL, 'processed', '2026-03-23 00:02:22', NULL, NULL, '2026-03-23 00:02:22'),
(3, 14, 1, 'Wrong product', '', 'pending', '2026-03-23 07:13:30', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `phone`) VALUES
(1, 'Prateek Verma', 'prateekverma6789@gmail.com', '$2y$10$BadiPNJ86DzoAGFj80utJuNUHNvnkccYOY6QZNS9VWb3IWWoQm6.m', '2026-03-09 14:23:35', '6398667276'),
(2, 'Prashnat Kumar', 'admin@gmail.com', '$2y$10$h55pdIwiw4VTpwEN0BfKCuoFKc4G1LqJe/GhJ6MHlZoGOJRrSxnUC', '2026-03-10 13:06:17', NULL),
(3, 'rahul Kumar', 'rahu@gmail.com', '$2y$10$RmuUM5U7uVhwKI.0RCJcwuP808V/b4tFXwaRxFI/Rx2CJp3HxaPRK', '2026-03-10 14:40:03', NULL),
(4, 'Tushar verma', 'tushar@gmail.com', '$2y$10$PVd4c0CMZu.7wTnh8YF7ROHuA/AAylYT8HqXztf2WlZi0lrugHT/6', '2026-03-10 16:10:28', NULL),
(5, 'Prashant Rajput', 'itzprashantrajput@gmail.com', '$2y$10$Fd33tTuMgH1vwB5ANBqbLeZryPXDlx4TVim77qPpjQ9YWwlMcVNW2', '2026-03-17 10:34:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `label` varchar(50) DEFAULT 'Home',
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `address1` varchar(200) NOT NULL,
  `address2` varchar(200) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `pincode` varchar(10) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`id`, `user_id`, `label`, `full_name`, `phone`, `address1`, `address2`, `city`, `state`, `pincode`, `is_default`, `created_at`) VALUES
(1, 1, 'Home', 'Prateek Verma', '6398667276', 'kazipura', '', 'bulandshahr', 'Uttar Pradesh', '203001', 1, '2026-03-14 16:19:20');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `commission` decimal(5,2) DEFAULT 10.00,
  `status` enum('pending','approved','blocked') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `name`, `email`, `password`, `shop_name`, `phone`, `address`, `commission`, `status`, `created_at`) VALUES
(1, 'Parth Mittal', 'Parthmittal999@gmail.com', '$2y$10$mlPu4fO6ZImcGUnmN2fzkOvxXdpNSV2lDQLBhkiq8rwIfPae/17Mu', 'Riyanshi Garments', '9389941670', 'Amber hall bulandshahr', '10.00', 'approved', '2026-03-11 06:01:40'),
(2, 'Prashant', 'prashant2@gmail.com', '$2y$10$yOcQU/uWjxquoA45I.sGaunN2x89RtTUKNwCyZNirUji53iaTGub6', 'Prsahant Electronics', '9389941670', 'bulandshahr', '10.00', 'approved', '2026-03-14 17:05:13'),
(3, 'Pulkit Verma', 'pulkit63@gmail.com', '$2y$10$BaQpLea9F3ZHGXlsofZ0f.UB3ACD8Bgwm6JkLCfaIvOploAdKytCK', 'Pulkit Grocery Items', '8650788036', 'Bulandshahr Kazipura', '10.00', 'approved', '2026-03-14 17:07:38'),
(4, 'Prateek Verma', 'prateek@gmail.com', '$2y$10$2xlOcx.0KJCKsL0AqxJTX.mPaKOO4KZnvq3RvHe/D68taDTr1kNcG', 'Sunglasses house', '8650788036', 'Bst', '10.00', 'approved', '2026-03-22 12:58:04'),
(5, 'Bhupesh Bhardwaj', 'bhupesh7983@gmail.com', '$2y$10$UtI.oeVMjp7L.mjfGR.iXuX8v8wVjRMA9ZtncfwmoPW5l/Icmndc6', 'Cold Drinks Cafe', '7983041822', 'sikri bulandshahr', '10.00', 'approved', '2026-03-23 18:27:08'),
(6, 'Deepanshu Kumar', 'deepanshu12@gmail.com', '$2y$10$OyEP2BVWUXNpgzyKs4Jsa.R28Khn/6UTa/m3ap4iuJwG/zc39eFd6', 'Electronics Hub', '9412891428', 'siana Addas bulandshahr', '10.00', 'approved', '2026-03-26 12:09:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `assistants`
--
ALTER TABLE `assistants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `special_id` (`special_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `commission_deductions`
--
ALTER TABLE `commission_deductions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `delivery_boys`
--
ALTER TABLE `delivery_boys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_read` (`notification_id`,`vendor_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`product_id`,`user_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `return_requests`
--
ALTER TABLE `return_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `assistants`
--
ALTER TABLE `assistants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `commission_deductions`
--
ALTER TABLE `commission_deductions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `delivery_boys`
--
ALTER TABLE `delivery_boys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notification_reads`
--
ALTER TABLE `notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `order_tracking`
--
ALTER TABLE `order_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `return_requests`
--
ALTER TABLE `return_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
