-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 14, 2026 at 03:01 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sairene_tapsilugan`
--

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(20) NOT NULL,
  `reorder_level` decimal(10,2) NOT NULL DEFAULT 10.00,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `item_name`, `quantity`, `unit`, `reorder_level`, `last_updated`) VALUES
(1, 'Beef Tapa', 50.00, 'kg', 10.00, '2026-10-13 01:01:45'),
(2, 'Garlic Rice', 100.00, 'servings', 20.00, '2026-10-13 01:01:45'),
(3, 'Eggs', 200.00, 'pcs', 30.00, '2026-10-13 01:01:45'),
(4, 'Crispy Bagnet', 41.00, 'kg', 8.00, '2026-10-14 11:06:14'),
(5, 'Hungarian Sausage', 80.00, 'pcs', 15.00, '2026-10-13 01:01:45'),
(6, 'Beef Tapa', 50.00, 'kg', 10.00, '2026-10-14 10:49:56'),
(7, 'Eggs', 200.00, 'pcs', 30.00, '2026-10-14 10:49:56'),
(8, 'Garlic Rice', 100.00, 'servings', 20.00, '2026-10-14 10:49:56'),
(9, 'Plain Rice', 100.00, 'servings', 20.00, '2026-10-14 10:49:56'),
(10, 'Crispy Bagnet', 40.00, 'kg', 8.00, '2026-10-14 10:49:56'),
(11, 'Tocino', 35.00, 'kg', 8.00, '2026-10-14 10:49:56'),
(12, 'Maling', 50.00, 'pcs', 10.00, '2026-10-14 10:49:56'),
(13, 'Liempo', 40.00, 'kg', 8.00, '2026-10-14 10:49:56'),
(14, 'Hungarian Sausage', 80.00, 'pcs', 15.00, '2026-10-14 10:49:56'),
(15, 'Hotdog', 100.00, 'pcs', 20.00, '2026-10-14 10:49:56'),
(16, 'Sisig Meat', 45.00, 'kg', 10.00, '2026-10-14 10:49:56');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `image` varchar(255) DEFAULT 'logo.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `name`, `category`, `stock`, `unit`, `image`, `created_at`) VALUES
(1, 'Garlic Rice (Portions)', 'Ingredient', 92, 'cups', 'garlic_rice.png', '2026-11-09 05:33:01'),
(2, 'Eggs (Fresh Batch)', 'Ingredient', 72, 'pcs', 'egg.png', '2026-11-09 05:33:01'),
(3, 'Take-Out Boxes (Silog)', 'Container', 90, 'packs', 'logo.png', '2026-11-09 05:33:01'),
(4, 'Plastic Gravy Cups (2oz)', 'Container', 150, 'pcs', 'logo.png', '2026-11-09 05:33:01'),
(5, 'Sisig Portion', 'Ingredient', 51, 'serving', 'special_sisig.png', '2026-11-09 05:33:01'),
(6, 'Hotdog', 'Ingredient', 67, 'pcs', 'hotsilog.png', '2026-11-09 05:33:01'),
(7, 'Hungarian Sausage', 'Ingredient', 84, 'pcs', 'hungarian.png', '2026-11-09 05:33:01'),
(8, 'Maling', 'Ingredient', 68, 'pcs', 'malingsilog.png', '2026-11-09 05:33:01'),
(9, 'Tapa Portion', 'Ingredient', 73, 'serving', 'special_tapa.png', '2026-11-09 05:33:01'),
(10, 'Pork Bagnet', 'Ingredient', 83, 'pcs', 'special_bagnet.png', '2026-11-09 05:33:01'),
(11, 'Tocino Portion', 'Ingredient', 75, 'serving', 'tocilog.png', '2026-11-09 05:33:01'),
(12, 'Porkchop Portion', 'Ingredient', 72, 'serving', 'liemposilog.png', '2026-11-09 05:33:01');

-- --------------------------------------------------------

--
-- Table structure for table `kiosk_orders`
--

CREATE TABLE `kiosk_orders` (
  `id` int(11) NOT NULL,
  `kiosk_id` varchar(50) DEFAULT 'KIOSK_1',
  `customer_name` varchar(255) DEFAULT 'Walk-in Guest',
  `dine_option` enum('dine_in','take_out') DEFAULT 'dine_in',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `order_status` varchar(50) DEFAULT 'preparing',
  `created_at` datetime DEFAULT current_timestamp(),
  `inventory_deducted` int(11) DEFAULT 0,
  `food_subtotal` decimal(10,2) DEFAULT 0.00,
  `tip_amount` decimal(10,2) DEFAULT 0.00,
  `queue_number` varchar(20) NOT NULL DEFAULT '',
  `phone` varchar(20) DEFAULT '',
  `dining_style` varchar(50) DEFAULT 'Dine-In',
  `table_tag` varchar(100) DEFAULT NULL,
  `neighborhood_badge` varchar(100) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT 'paid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kiosk_orders`
--

INSERT INTO `kiosk_orders` (`id`, `kiosk_id`, `customer_name`, `dine_option`, `total_amount`, `payment_method`, `order_status`, `created_at`, `inventory_deducted`, `food_subtotal`, `tip_amount`, `queue_number`, `phone`, `dining_style`, `table_tag`, `neighborhood_badge`, `payment_status`) VALUES
(1, 'KIOSK_1', 'C', 'dine_in', 935.00, 'GCash', 'preparing', '2026-11-11 04:06:48', 1, 735.00, 200.00, 'A-001', '', 'Take-Out', '', 'Night Shift Hero', 'completed'),
(2, 'KIOSK_1', 'Navarro', 'dine_in', 290.00, 'GCash', 'preparing', '2026-11-11 10:15:53', 1, 90.00, 200.00, 'K-002', '09283661326', 'Dine-In', '', 'Night Shift Hero', 'completed'),
(3, 'KIOSK_1', 'Dicey', 'dine_in', 350.00, 'GCash', 'preparing', '2026-11-11 10:18:21', 1, 150.00, 200.00, 'K-003', '', 'Dine-In', '', 'Early Bird', 'completed'),
(4, 'KIOSK_1', 'Jeje', 'dine_in', 645.00, 'GCash', 'preparing', '2026-11-11 10:33:46', 1, 490.00, 155.00, 'K-004', '9982444613', 'Dine-In', '1', 'Local Regular', 'completed'),
(5, 'KIOSK_1', 'Grama', 'dine_in', 90.00, 'GCash', 'preparing', '2026-11-11 10:49:06', 1, 90.00, 0.00, 'K-005', '', 'Dine-In', '', 'Weekend Foodie', 'completed'),
(6, 'KIOSK_1', 'r', 'dine_in', 2875.00, 'GCash', 'preparing', '2026-11-11 10:57:22', 1, 2675.00, 200.00, 'K-006', '09686021927', 'Take-Out', '', 'Night Shift Hero', 'completed'),
(7, 'KIOSK_1', 'dw', 'dine_in', 90.00, 'GCash', 'preparing', '2026-11-11 11:23:44', 1, 90.00, 0.00, 'K-007', '09686021927', 'Dine-In', '', 'Weekend Foodie', 'paid'),
(8, 'KIOSK_1', 'C', 'dine_in', 90.00, 'GCash', 'preparing', '2026-11-11 12:53:41', 1, 90.00, 0.00, 'K-008', '', 'Dine-In', '', 'Night Shift Hero', 'paid');

-- --------------------------------------------------------

--
-- Table structure for table `kiosk_order_items`
--

CREATE TABLE `kiosk_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `egg_choice` varchar(100) DEFAULT '',
  `notes` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `product_name` varchar(150) NOT NULL DEFAULT '',
  `rice_option` varchar(100) DEFAULT NULL,
  `custom_options` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kiosk_order_items`
--

INSERT INTO `kiosk_order_items` (`id`, `order_id`, `item_name`, `egg_choice`, `notes`, `quantity`, `price`, `product_name`, `rice_option`, `custom_options`) VALUES
(1, 1, '', 'Scrambled', NULL, 3, 140.00, 'Tapa + Bagnet Silog Combo', 'Garlic Rice', 'Garlic Rice · Scrambled'),
(2, 1, '', '', NULL, 2, 130.00, 'Special Sisig Portion', '', ''),
(3, 1, '', '', NULL, 2, 20.00, 'Garlic Rice', '', ''),
(4, 1, '', '', NULL, 1, 15.00, 'Plain Rice', '', ''),
(5, 2, '', 'Sunny-side up', NULL, 1, 90.00, 'Tapsilog', 'Garlic Rice', 'Garlic Rice · Sunny-side up'),
(6, 3, '', 'Sunny-side up', NULL, 1, 90.00, 'Sisigsilog', 'Garlic Rice', 'Garlic Rice · Sunny-side up'),
(7, 3, '', '', NULL, 4, 15.00, 'Extra Egg', '', ''),
(8, 4, '', 'Sunny-side up', NULL, 1, 90.00, 'Tapsilog', 'Garlic Rice', 'Garlic Rice · Sunny-side up'),
(9, 4, '', 'Sunny-side up', NULL, 1, 90.00, 'Bagnet Silog', 'Garlic Rice', 'Garlic Rice · Sunny-side up'),
(10, 4, '', 'Sunny-side up', NULL, 1, 90.00, 'Liemposilog', 'Garlic Rice', 'Garlic Rice · Sunny-side up'),
(11, 4, '', 'Sunny-side up', NULL, 1, 90.00, 'Sisigsilog', 'Garlic Rice', 'Garlic Rice · Sunny-side up'),
(12, 4, '', '', NULL, 1, 130.00, 'Special Bagnet Portion', '', ''),
(13, 5, '', 'Sunny-side up', NULL, 1, 90.00, 'Tocilog', 'Garlic Rice', 'Garlic Rice · Sunny-side up'),
(14, 6, '', '', NULL, 4, 130.00, 'Special Tapa Portion', '', ''),
(15, 6, '', '', NULL, 4, 130.00, 'Special Bagnet Portion', '', ''),
(16, 6, '', '', NULL, 5, 130.00, 'Special Sisig Portion', '', ''),
(17, 6, '', '', NULL, 6, 130.00, 'Hungarian Portion', '', ''),
(18, 6, '', '', NULL, 5, 20.00, 'Garlic Rice', '', ''),
(19, 6, '', '', NULL, 3, 15.00, 'Plain Rice', '', ''),
(20, 6, '', '', NULL, 4, 15.00, 'Extra Egg', '', ''),
(21, 7, '', 'Sunny-side up', NULL, 1, 90.00, 'Tapsilog', 'Garlic Rice', 'Garlic Rice · Sunny-side up'),
(22, 8, '', 'Sunny-side up', NULL, 1, 90.00, 'Tapsilog', 'Garlic Rice', 'Garlic Rice · Sunny-side up');

-- --------------------------------------------------------

--
-- Table structure for table `kiosk_saved_profiles`
--

CREATE TABLE `kiosk_saved_profiles` (
  `id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `neighborhood_badge` varchar(100) DEFAULT NULL,
  `last_order_json` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kiosk_saved_profiles`
--

INSERT INTO `kiosk_saved_profiles` (`id`, `phone_number`, `customer_name`, `neighborhood_badge`, `last_order_json`, `updated_at`) VALUES
(1, '09283661326', 'Navarro', 'Night Shift Hero', '{\"items\":[{\"id\":\"tapsilog\",\"name\":\"Tapsilog\",\"price\":90,\"qty\":1,\"rice\":\"Garlic Rice\",\"egg\":\"Sunny-side up\",\"options\":\"Garlic Rice \\u00b7 Sunny-side up\"}],\"phone\":\"09283661326\",\"name\":\"Navarro\",\"dining\":\"Dine-In\",\"tableTag\":\"\",\"neighborhood\":\"Night Shift Hero\",\"food\":90,\"tip\":200,\"total\":290,\"saveOrder\":true,\"paymentMethod\":\"GCash\"}', '2026-11-11 10:15:53'),
(2, '09686021927', 'dw', 'Weekend Foodie', '{\"items\":[{\"id\":\"tapsilog\",\"name\":\"Tapsilog\",\"price\":90,\"qty\":1,\"rice\":\"Garlic Rice\",\"egg\":\"Sunny-side up\",\"options\":\"Garlic Rice \\u00b7 Sunny-side up\"}],\"phone\":\"09686021927\",\"name\":\"dw\",\"dining\":\"Dine-In\",\"tableTag\":\"\",\"neighborhood\":\"Weekend Foodie\",\"food\":90,\"tip\":0,\"total\":90,\"saveOrder\":true,\"paymentMethod\":\"GCash\"}', '2026-11-11 11:23:44');

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` enum('silog','combo','alacarte','foodpack') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`id`, `item_name`, `category`, `price`, `image_url`, `is_available`) VALUES
(0, 'Tapsilog', 'silog', 90.00, NULL, 1),
(0, 'Bagnetsilog', 'silog', 90.00, NULL, 1),
(0, 'Sisigsilog', 'silog', 90.00, NULL, 1),
(0, 'Liemposilog', 'silog', 90.00, NULL, 1),
(0, 'Malingsilog', 'silog', 90.00, NULL, 1),
(0, 'Tocilog', 'silog', 90.00, NULL, 1),
(0, 'Hungariansilog', 'silog', 90.00, NULL, 1),
(0, 'Hotdogsilog', 'silog', 80.00, NULL, 1),
(0, 'Bagnet + Hungarian Combo', 'combo', 140.00, NULL, 1),
(0, 'Tapa + Hungarian Combo', 'combo', 140.00, NULL, 1),
(0, 'Tapa + Bagnet Silog Combo', 'combo', 140.00, NULL, 1),
(0, 'Liempo + Hungarian Combo', 'combo', 140.00, NULL, 1),
(0, 'Sisig + Hungarian Combo', 'combo', 140.00, NULL, 1),
(0, 'Special Tapa Portion', 'alacarte', 130.00, NULL, 1),
(0, 'Special Bagnet Portion', 'alacarte', 130.00, NULL, 1),
(0, 'Special Sisig Portion', 'alacarte', 130.00, NULL, 1),
(0, 'Hungarian Portion', 'alacarte', 130.00, NULL, 1),
(0, 'Garlic Rice', 'alacarte', 20.00, NULL, 1),
(0, 'Plain Rice', 'alacarte', 15.00, NULL, 1),
(0, 'Extra Egg', 'alacarte', 15.00, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `delivery_area` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('GCash','Cash on Delivery') DEFAULT 'GCash',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `order_status` enum('pending','preparing','out_for_delivery','completed','cancelled') DEFAULT 'pending',
  `payment_proof` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `inventory_deducted` int(11) DEFAULT 0,
  `food_subtotal` decimal(10,2) DEFAULT 0.00,
  `tip_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `customer_name`, `phone`, `delivery_area`, `address`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `payment_proof`, `created_at`, `updated_at`, `inventory_deducted`, `food_subtotal`, `tip_amount`) VALUES
(1, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 270.00, '', 'paid', 'completed', NULL, '2026-10-23 15:19:34', '2026-11-10 19:41:02', 1, 0.00, 0.00),
(2, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 270.00, '', 'paid', 'completed', NULL, '2026-10-23 15:24:42', '2026-11-10 19:41:01', 1, 0.00, 0.00),
(3, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 90.00, 'GCash', 'paid', 'completed', NULL, '2026-10-25 17:41:27', '2026-11-10 19:41:00', 1, 0.00, 0.00),
(4, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 90.00, 'GCash', 'paid', 'completed', NULL, '2026-10-25 17:44:25', '2026-11-10 19:40:59', 1, 0.00, 0.00),
(5, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 90.00, 'GCash', 'paid', 'completed', NULL, '2026-10-25 17:45:37', '2026-11-10 19:40:59', 1, 0.00, 0.00),
(6, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 940.00, '', 'paid', 'completed', NULL, '2026-10-25 17:46:51', '2026-11-10 19:40:57', 1, 0.00, 0.00),
(7, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 745.00, 'GCash', 'paid', 'completed', NULL, '2026-10-25 17:49:25', '2026-11-10 19:40:56', 1, 0.00, 0.00),
(8, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 180.00, '', 'paid', 'completed', NULL, '2026-11-06 23:05:58', '2026-11-10 19:40:54', 1, 0.00, 0.00),
(9, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 270.00, '', 'paid', 'completed', NULL, '2026-11-09 05:01:18', '2026-11-10 19:40:53', 1, 0.00, 0.00),
(10, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 700.00, '', 'paid', 'completed', NULL, '2026-11-09 05:28:38', '2026-11-10 19:40:52', 1, 0.00, 0.00),
(11, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 310.00, 'GCash', 'paid', 'completed', NULL, '2026-11-09 05:36:30', '2026-11-10 19:40:51', 1, 0.00, 0.00),
(12, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 230.00, 'GCash', 'paid', 'completed', NULL, '2026-11-09 05:37:16', '2026-11-10 19:40:50', 1, 0.00, 0.00),
(13, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 130.00, '', 'paid', 'completed', NULL, '2026-11-09 05:37:49', '2026-11-10 19:40:49', 1, 0.00, 0.00),
(14, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 130.00, '', 'paid', 'completed', NULL, '2026-11-09 05:39:07', '2026-11-10 19:40:48', 1, 0.00, 0.00),
(15, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 280.00, '', 'paid', 'completed', NULL, '2026-11-09 06:02:06', '2026-11-10 19:40:47', 1, 0.00, 0.00),
(16, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 1980.00, '', 'paid', 'completed', NULL, '2026-11-09 06:07:05', '2026-11-10 19:40:46', 1, 0.00, 0.00),
(17, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 1980.00, '', 'paid', 'completed', NULL, '2026-11-10 08:19:16', '2026-11-10 19:40:44', 1, 0.00, 0.00),
(18, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 450.00, '', 'paid', 'completed', NULL, '2026-11-10 08:28:49', '2026-11-10 19:40:41', 1, 0.00, 0.00),
(19, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 90.00, '', 'paid', 'completed', NULL, '2026-11-10 09:36:53', '2026-11-10 19:40:31', 1, 0.00, 0.00),
(20, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 90.00, '', 'paid', 'completed', NULL, '2026-11-10 09:37:16', '2026-11-10 19:40:30', 1, 0.00, 0.00),
(21, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 90.00, '', 'paid', 'completed', NULL, '2026-11-10 09:39:23', '2026-11-10 19:40:29', 1, 0.00, 0.00),
(22, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 260.00, '', 'paid', 'completed', NULL, '2026-11-10 16:47:10', '2026-11-10 19:40:28', 1, 0.00, 0.00),
(23, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 260.00, '', 'paid', 'completed', NULL, '2026-11-10 16:48:04', '2026-11-10 19:40:28', 1, 0.00, 0.00),
(24, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 260.00, '', 'paid', 'completed', NULL, '2026-11-10 16:48:08', '2026-11-10 19:40:27', 1, 0.00, 0.00),
(25, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 170.00, '', 'paid', 'completed', NULL, '2026-11-10 17:18:56', '2026-11-10 19:40:26', 1, 0.00, 0.00),
(26, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 90.00, '', 'paid', 'completed', NULL, '2026-11-10 17:19:48', '2026-11-10 19:40:25', 1, 0.00, 0.00),
(27, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 170.00, '', 'paid', 'completed', NULL, '2026-11-10 17:29:08', '2026-11-10 19:40:24', 1, 0.00, 0.00),
(28, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 180.00, '', 'paid', 'completed', NULL, '2026-11-10 17:29:19', '2026-11-10 19:40:21', 1, 0.00, 0.00),
(29, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 180.00, '', 'paid', 'completed', NULL, '2026-11-10 17:36:05', '2026-11-10 19:40:21', 1, 0.00, 0.00),
(30, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 180.00, '', 'paid', 'completed', NULL, '2026-11-10 17:36:06', '2026-11-10 19:40:20', 1, 0.00, 0.00),
(31, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 90.00, '', 'paid', 'completed', NULL, '2026-11-10 17:36:18', '2026-11-10 19:40:19', 1, 0.00, 0.00),
(32, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 80.00, '', 'paid', 'completed', NULL, '2026-11-10 17:36:36', '2026-11-10 19:40:19', 1, 0.00, 0.00),
(33, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 80.00, '', 'paid', 'completed', NULL, '2026-11-10 17:38:05', '2026-11-10 19:40:18', 1, 80.00, 0.00),
(34, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 80.00, '', 'paid', 'completed', NULL, '2026-11-10 17:38:07', '2026-11-10 19:40:14', 1, 80.00, 0.00),
(35, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 90.00, '', 'paid', 'completed', NULL, '2026-11-10 17:38:13', '2026-11-10 19:40:11', 1, 90.00, 0.00),
(36, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 90.00, '', 'paid', 'completed', NULL, '2026-11-10 17:38:22', '2026-11-10 19:40:09', 1, 90.00, 0.00),
(37, 1, 'Carlo King', '09693542387', 'Taguig', '7105 Kasoy', 90.00, 'GCash', 'paid', 'completed', 'uploads/proof_1794336598_4030.png', '2026-11-10 18:49:58', '2026-11-10 19:28:44', 1, 0.00, 0.00),
(38, 1, 'Carlo King', '09693542387', 'Taguig', '7105 Kasoy', 90.00, 'GCash', 'paid', 'completed', 'uploads/proof_1794336636_7094.png', '2026-11-10 18:50:36', '2026-11-10 19:28:35', 1, 0.00, 0.00),
(39, 1, 'Carlo King', '09693542387', 'aknkjfsawj', '7105 Kasoy', 90.00, 'GCash', 'paid', 'completed', 'uploads/proof_1794338877_8680.png', '2026-11-10 19:27:57', '2026-11-10 19:28:26', 1, 0.00, 0.00),
(40, 1, 'Carlo King', '09693542387', 'Taguig', '7105 Kasoy', 90.00, 'GCash', 'paid', 'completed', 'uploads/proof_1794338957_6387.png', '2026-11-10 19:29:17', '2026-11-10 19:40:17', 1, 0.00, 0.00),
(41, 1, 'Carlo King', '09693542387', 'Taguig', '7105 Kasoy', 90.00, 'GCash', 'paid', 'completed', 'uploads/proof_1794339064_1495.png', '2026-11-10 19:31:04', '2026-11-10 19:40:15', 1, 0.00, 0.00),
(42, 1, 'Carlo King', '09693542387', 'Taguig', '7105 Kasoy', 90.00, 'GCash', 'paid', 'completed', 'uploads/proof_1794339714_9256.png', '2026-11-10 19:41:54', '2026-11-10 19:42:03', 1, 0.00, 0.00),
(43, 1, 'Carlo King', '09693542387', 'Taguig', '7105 Kasoy', 90.00, 'GCash', 'paid', 'completed', 'uploads/proof_1794340470_5120.png', '2026-11-10 19:54:30', '2026-11-10 19:55:18', 1, 0.00, 0.00),
(44, 1, 'Carlo King', '09693542387', 'Taguig', '7105 Kasoy', 90.00, 'GCash', 'paid', 'completed', 'uploads/proof_1794340511_6609.png', '2026-11-10 19:55:11', '2026-11-10 20:02:58', 1, 0.00, 0.00),
(45, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 150.00, '', 'paid', 'completed', NULL, '2026-11-10 20:05:14', '2026-11-10 20:06:31', 1, 150.00, 0.00),
(46, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 150.00, '', 'paid', 'completed', NULL, '2026-11-10 20:06:22', '2026-11-11 01:44:28', 1, 150.00, 0.00),
(47, NULL, 'Walk-in', '09000000000', 'Counter Dine-in / Takeout', 'Cashier POS Terminal', 150.00, '', 'paid', 'completed', NULL, '2026-11-10 20:06:24', '2026-11-11 01:44:28', 1, 150.00, 0.00),
(48, 1, 'Carlo King', '09693542387', 'Taguig', '7105 Kasoy', 90.00, 'GCash', 'paid', 'preparing', 'uploads/proof_1794341247_5912.png', '2026-11-10 20:07:27', '2026-11-11 01:44:28', 1, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `egg_choice` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `item_name`, `egg_choice`, `notes`, `quantity`, `price`) VALUES
(1, 1, 'Hungariansilog', 'No Egg', '', 3, 90.00),
(2, 2, 'Hungariansilog', 'No Egg', '', 3, 90.00),
(3, 3, 'Bagnetsilog', 'No Egg', '', 1, 90.00),
(4, 4, 'Bagnetsilog', 'No Egg', '', 1, 90.00),
(5, 5, 'Bagnetsilog', 'No Egg', '', 1, 90.00),
(6, 6, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(7, 6, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(8, 6, 'Sisigsilog', 'Sunny Side Up', '', 1, 90.00),
(9, 6, 'Liemposilog', 'Sunny Side Up', '', 1, 90.00),
(10, 6, 'Tapsilog', 'Sunny Side Up', '', 3, 90.00),
(11, 6, 'Tocilog', 'Sunny Side Up', '', 2, 90.00),
(12, 6, 'Sisig + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(13, 7, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(14, 7, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(15, 7, 'Bagnet + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(16, 7, 'Sisig + Hungarian Combo', 'Sunny Side Up', '', 2, 140.00),
(17, 7, 'Extra Egg', 'Sunny Side Up', '', 1, 15.00),
(18, 7, 'Tapa + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(19, 8, 'Hungariansilog', 'Sunny Side Up', '', 1, 90.00),
(20, 8, 'Sisigsilog', 'Sunny Side Up', '', 1, 90.00),
(21, 9, 'Bagnetsilog', 'Sunny Side Up', '', 3, 90.00),
(22, 10, 'Sisig + Hungarian Combo', 'Sunny Side Up', '', 5, 140.00),
(23, 11, 'Tapa + Bagnet Silog Combo', 'Sunny Side Up', '', 1, 140.00),
(24, 11, 'Hungariansilog', 'Sunny Side Up', '', 1, 90.00),
(25, 11, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(26, 12, 'Liempo + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(27, 12, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(28, 13, 'Special Bagnet Portion', 'Sunny Side Up', '', 1, 130.00),
(29, 14, 'Special Sisig Portion', 'Scrambled', '', 1, 130.00),
(30, 15, 'Bagnet + Hungarian Combo', 'Sunny Side Up', '', 2, 140.00),
(31, 16, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(32, 16, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(33, 16, 'Hungariansilog', 'Sunny Side Up', '', 1, 90.00),
(34, 16, 'Liemposilog', 'Sunny Side Up', '', 1, 90.00),
(35, 16, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(36, 16, 'Sisigsilog', 'Sunny Side Up', '', 1, 90.00),
(37, 16, 'Tapsilog', 'Sunny Side Up', '', 1, 90.00),
(38, 16, 'Tocilog', 'Sunny Side Up', '', 1, 90.00),
(39, 16, 'Bagnet + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(40, 16, 'Liempo + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(41, 16, 'Sisig + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(42, 16, 'Tapa + Bagnet Silog Combo', 'Sunny Side Up', '', 1, 140.00),
(43, 16, 'Tapa + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(44, 16, 'Extra Egg', 'Sunny Side Up', '', 1, 15.00),
(45, 16, 'Garlic Rice', 'Sunny Side Up', '', 1, 20.00),
(46, 16, 'Hungarian Portion', 'Sunny Side Up', '', 1, 130.00),
(47, 16, 'Plain Rice', 'Sunny Side Up', '', 1, 15.00),
(48, 16, 'Special Bagnet Portion', 'Sunny Side Up', '', 1, 130.00),
(49, 16, 'Special Sisig Portion', 'Sunny Side Up', '', 1, 130.00),
(50, 16, 'Special Tapa Portion', 'Sunny Side Up', '', 1, 130.00),
(51, 17, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(52, 17, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(53, 17, 'Hungariansilog', 'Sunny Side Up', '', 1, 90.00),
(54, 17, 'Liemposilog', 'Sunny Side Up', '', 1, 90.00),
(55, 17, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(56, 17, 'Sisigsilog', 'Sunny Side Up', '', 1, 90.00),
(57, 17, 'Tapsilog', 'Sunny Side Up', '', 1, 90.00),
(58, 17, 'Tocilog', 'Sunny Side Up', '', 1, 90.00),
(59, 17, 'Bagnet + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(60, 17, 'Liempo + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(61, 17, 'Sisig + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(62, 17, 'Tapa + Bagnet Silog Combo', 'Sunny Side Up', '', 1, 140.00),
(63, 17, 'Tapa + Hungarian Combo', 'Sunny Side Up', '', 1, 140.00),
(64, 17, 'Extra Egg', 'Sunny Side Up', '', 1, 15.00),
(65, 17, 'Garlic Rice', 'Sunny Side Up', '', 1, 20.00),
(66, 17, 'Hungarian Portion', 'Sunny Side Up', '', 1, 130.00),
(67, 17, 'Plain Rice', 'Sunny Side Up', '', 1, 15.00),
(68, 17, 'Special Bagnet Portion', 'Sunny Side Up', '', 1, 130.00),
(69, 17, 'Special Sisig Portion', 'Sunny Side Up', '', 1, 130.00),
(70, 17, 'Special Tapa Portion', 'Sunny Side Up', '', 1, 130.00),
(71, 18, 'Liemposilog', 'Sunny Side Up', '', 1, 90.00),
(72, 18, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(73, 18, 'Sisigsilog', 'Sunny Side Up', '', 1, 90.00),
(74, 18, 'Tapsilog', 'Sunny Side Up', '', 1, 90.00),
(75, 18, 'Tocilog', 'Sunny Side Up', '', 1, 90.00),
(76, 19, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(77, 20, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(78, 21, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(79, 22, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(80, 22, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(81, 22, 'Hungariansilog', 'Sunny Side Up', '', 1, 90.00),
(82, 23, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(83, 23, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(84, 23, 'Hungariansilog', 'Sunny Side Up', '', 1, 90.00),
(85, 24, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(86, 24, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(87, 24, 'Hungariansilog', 'Sunny Side Up', '', 1, 90.00),
(88, 25, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(89, 25, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(90, 26, 'Hungariansilog', 'Sunny Side Up', '', 1, 90.00),
(91, 27, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(92, 27, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(93, 28, 'Bagnetsilog', 'Sunny Side Up', '', 2, 90.00),
(94, 29, 'Bagnetsilog', 'Sunny Side Up', '', 2, 90.00),
(95, 30, 'Bagnetsilog', 'Sunny Side Up', '', 2, 90.00),
(96, 31, 'Bagnetsilog', 'Sunny Side Up', '', 1, 90.00),
(97, 32, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(98, 33, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(99, 34, 'Hotdogsilog', 'Sunny Side Up', '', 1, 80.00),
(100, 35, 'Hungariansilog', 'Sunny Side Up', '', 1, 90.00),
(101, 36, 'Hungariansilog', 'Sunny Side Up', '', 1, 90.00),
(102, 37, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(103, 38, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(104, 39, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(105, 40, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(106, 41, 'Tapsilog', 'Sunny Side Up', '', 1, 90.00),
(107, 42, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(108, 43, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(109, 44, 'Tapsilog', 'Sunny Side Up', '', 1, 90.00),
(110, 45, 'Special Bagnet Portion', 'Sunny Side Up', '', 1, 130.00),
(111, 45, 'Garlic Rice', 'Sunny Side Up', '', 1, 20.00),
(112, 46, 'Special Bagnet Portion', 'Sunny Side Up', '', 1, 130.00),
(113, 46, 'Garlic Rice', 'Sunny Side Up', '', 1, 20.00),
(114, 47, 'Special Bagnet Portion', 'Sunny Side Up', '', 1, 130.00),
(115, 47, 'Garlic Rice', 'Sunny Side Up', '', 1, 20.00),
(116, 48, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00);

-- --------------------------------------------------------

--
-- Table structure for table `tambay_caps`
--

CREATE TABLE `tambay_caps` (
  `id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','admin') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Webtools', 'webtools@testing.com', '$2y$10$SPT7sO1Cc8sv8p3ur0.QLeujakVtaROViQVf4rLoRvd.vxlGHhxvC', 'customer', '2026-11-09 05:16:25'),
(2, 'King Inamo', 'king@gmail.com', '$2y$10$wEh4vVIu1aMV0Q1Vf9uv7OmQ4rAD0RSBrXnn0r7m931w2CbMNdV5u', 'customer', '2026-10-15 05:24:02'),
(3, 'jansen', 'haj@gmail.com', '$2y$10$S1gzLcRN4b9iWJlfbzO1s.9NWHCdc4wz7qzkUN6xwoOTGiECoCfom', 'customer', '2026-11-09 07:14:36'),
(5, 'System Administrator', 'admin@sairene.com', '$2y$10$6h7W8gzv3qMP/wSu71E/TeyJ9N/3XDKiPN.yYDmBaIVxqiZbLRDay', 'admin', '2026-11-09 07:41:58'),
(6, 'kent', 'k@gmail.com', '$2y$10$4q9exzNg9oneu8o9M4dTLu6MDgxoER/XFXTx5.CoMFtyBEPHy43t2', 'customer', '2026-11-10 07:25:14'),
(7, 'Sean', 'sean@gmil.com', '$2y$10$j9ZueywP3poufrrEghSo2uqpg6ZkeSkfJhaBBK/K7.bL04eVVcIeu', 'customer', '2026-11-11 03:11:36'),
(8, 'Jan', 'jan13@gmail.com', '$2y$10$P6iyNIQX2/ox1b3pfBg0dOZRmZOmpOJHRFGPvW4ES4jhrIw7cLTj.', 'customer', '2026-11-11 04:06:16');

-- --------------------------------------------------------

--
-- Table structure for table `website_orders`
--

CREATE TABLE `website_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `delivery_area` varchar(255) DEFAULT 'Taguig',
  `address` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'GCash',
  `payment_status` varchar(50) DEFAULT 'paid',
  `order_status` varchar(50) DEFAULT 'preparing',
  `payment_proof` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `inventory_deducted` int(11) DEFAULT 0,
  `food_subtotal` decimal(10,2) DEFAULT 0.00,
  `tip_amount` decimal(10,2) DEFAULT 0.00,
  `queue_number` varchar(20) DEFAULT 'W-001'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `website_orders`
--

INSERT INTO `website_orders` (`id`, `user_id`, `customer_name`, `phone`, `delivery_area`, `address`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `payment_proof`, `created_at`, `updated_at`, `inventory_deducted`, `food_subtotal`, `tip_amount`, `queue_number`) VALUES
(1, 3, 'Sean Baltazar Aming', '09303907034', 'hja', '30 J. P. Rizal St', 90.00, 'GCash', 'paid', 'completed', 'uploads/proof_1794208631_1701.jpg', '2026-11-09 15:17:11', '2026-11-11 09:46:09', 1, 0.00, 0.00, 'W-001'),
(2, 1, 'Carlo King', '09693542387', 'Taguig', '7105 Kasoy', 280.00, 'GCash', 'paid', 'completed', 'uploads/proof_1794371080_9101.png', '2026-11-11 12:24:40', '2026-11-11 12:25:47', 1, 280.00, 0.00, 'W-001'),
(3, 1, 'Carlo King', '09693542387', 'Taguig', '7105 Kasoy', 90.00, 'GCash', 'paid', 'preparing', 'uploads/proof_1794371245_2453.png', '2026-11-11 12:27:25', NULL, 1, 90.00, 0.00, 'W-002');

-- --------------------------------------------------------

--
-- Table structure for table `website_order_items`
--

CREATE TABLE `website_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `egg_choice` varchar(100) DEFAULT '',
  `notes` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `website_order_items`
--

INSERT INTO `website_order_items` (`id`, `order_id`, `item_name`, `egg_choice`, `notes`, `quantity`, `price`) VALUES
(1, 1, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00),
(2, 2, 'Tapa + Hungarian', 'Sunny Side Up', '', 1, 140.00),
(3, 2, 'Tapa + Bagnet Silog', 'Sunny Side Up', '', 1, 140.00),
(4, 3, 'Malingsilog', 'Sunny Side Up', '', 1, 90.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kiosk_orders`
--
ALTER TABLE `kiosk_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kiosk_order_items`
--
ALTER TABLE `kiosk_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `kiosk_saved_profiles`
--
ALTER TABLE `kiosk_saved_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone_number` (`phone_number`);

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
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `tambay_caps`
--
ALTER TABLE `tambay_caps`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `website_orders`
--
ALTER TABLE `website_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `website_order_items`
--
ALTER TABLE `website_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `kiosk_orders`
--
ALTER TABLE `kiosk_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `kiosk_order_items`
--
ALTER TABLE `kiosk_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `kiosk_saved_profiles`
--
ALTER TABLE `kiosk_saved_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `tambay_caps`
--
ALTER TABLE `tambay_caps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `website_orders`
--
ALTER TABLE `website_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `website_order_items`
--
ALTER TABLE `website_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `kiosk_order_items`
--
ALTER TABLE `kiosk_order_items`
  ADD CONSTRAINT `kiosk_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `kiosk_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `website_orders`
--
ALTER TABLE `website_orders`
  ADD CONSTRAINT `website_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `website_order_items`
--
ALTER TABLE `website_order_items`
  ADD CONSTRAINT `website_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `website_orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
