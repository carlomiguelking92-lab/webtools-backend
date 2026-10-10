-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2026 at 02:59 PM
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
-- Database: `saitephen_tapsilogan`
--

-- --------------------------------------------------------

--
-- Table structure for table `delivery_zones`
--

CREATE TABLE `delivery_zones` (
  `id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `is_within_range` tinyint(1) DEFAULT 1,
  `delivery_fee` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_zones`
--

INSERT INTO `delivery_zones` (`id`, `location_name`, `is_within_range`, `delivery_fee`) VALUES
(1, 'Zone 1 - Main Branch Area', 1, 30.00),
(2, 'Zone 2 - Business District', 1, 40.00),
(3, 'Zone 3 - Nearby Suburbs', 1, 50.00),
(4, 'Out of Delivery Range', 0, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `foodpack_options`
--

CREATE TABLE `foodpack_options` (
  `id` int(11) NOT NULL,
  `category` enum('main_dish','rice','drink','side') NOT NULL,
  `option_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `foodpack_options`
--

INSERT INTO `foodpack_options` (`id`, `category`, `option_name`, `price`) VALUES
(1, 'main_dish', 'Beef Tapa', 120.00),
(2, 'main_dish', 'Pork Tocino', 100.00),
(3, 'main_dish', 'Pork Liempo', 130.00),
(4, 'main_dish', 'Chicken Inasal', 110.00),
(5, 'rice', 'Garlic Fried Rice', 25.00),
(6, 'rice', 'Plain White Rice', 20.00),
(7, 'rice', 'Java Rice', 30.00),
(8, 'drink', 'Iced Tea (12oz)', 25.00),
(9, 'drink', 'Bottled Water', 20.00),
(10, 'drink', 'Canned Soda', 35.00),
(11, 'side', 'Atchara', 15.00),
(12, 'side', 'Extra Fried Egg', 15.00);

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) DEFAULT 'Silog',
  `is_available` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`id`, `product_name`, `price`, `category`, `is_available`) VALUES
(1, 'Tapsilog (Beef Tapa + Rice + Egg)', 120.00, 'Silog', 1),
(2, 'Tocilog (Pork Tocino + Rice + Egg)', 100.00, 'Silog', 1),
(3, 'Bangsilog (Daing na Bangus + Rice + Egg)', 110.00, 'Silog', 1),
(4, 'Chicksilog (Fried Chicken + Rice + Egg)', 115.00, 'Silog', 1),
(5, 'Hotsilog (Hotdog + Rice + Egg)', 85.00, 'Silog', 1);

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES
(1, 'business_name', 'Tambay Caps Tapsilogan'),
(2, 'logo_path', 'assets/images/logo.png'),
(3, 'business_hours', 'Open 24/7');

-- --------------------------------------------------------

--
-- Table structure for table `tambay_caps`
--

CREATE TABLE `tambay_caps` (
  `id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `image_url` varchar(255) DEFAULT 'assets/images/default-cap.jpg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tambay_caps`
--

INSERT INTO `tambay_caps` (`id`, `product_name`, `price`, `description`, `stock`, `image_url`) VALUES
(1, 'Classic Tambay Snapback', 450.00, 'Original embroidered snapback cap for daily wear.', 25, 'assets/images/default-cap.jpg'),
(2, 'Tapsilogan Limited Edition Cap', 550.00, 'Official collaboration cap with custom side patch.', 10, 'assets/images/default-cap.jpg'),
(3, 'Streetwear Dad Hat', 400.00, 'Unstructured low-profile cap with adjustable strap.', 15, 'assets/images/default-cap.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `foodpack_options`
--
ALTER TABLE `foodpack_options`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tambay_caps`
--
ALTER TABLE `tambay_caps`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `foodpack_options`
--
ALTER TABLE `foodpack_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tambay_caps`
--
ALTER TABLE `tambay_caps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
