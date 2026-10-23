-- ==========================================
-- Standalone Kiosk Database Schema
-- ==========================================
​CREATE DATABASE IF NOT EXISTS sairene_kiosk_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sairene_kiosk_db;
​-- 1. Kiosk Orders Table
CREATE TABLE IF NOT EXISTS kiosk_orders (
id INT AUTO_INCREMENT PRIMARY KEY,
queue_number VARCHAR(20) NOT NULL,
customer_name VARCHAR(100) NOT NULL,
phone VARCHAR(20) DEFAULT NULL,
dining_style VARCHAR(50) NOT NULL DEFAULT 'Dine-In',
table_tag VARCHAR(100) DEFAULT NULL,
neighborhood_badge VARCHAR(100) DEFAULT 'Night Shift Hero',
food_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
tip_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
payment_method VARCHAR(50) DEFAULT 'GCash',
payment_status VARCHAR(50) DEFAULT 'paid',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
​-- 2. Kiosk Order Items Table
CREATE TABLE IF NOT EXISTS kiosk_order_items (
id INT AUTO_INCREMENT PRIMARY KEY,
order_id INT NOT NULL,
product_name VARCHAR(150) NOT NULL,
rice_option VARCHAR(50) DEFAULT 'Garlic Rice',
egg_choice VARCHAR(50) DEFAULT 'Sunny-side up',
custom_options VARCHAR(255) DEFAULT NULL,
quantity INT NOT NULL DEFAULT 1,
price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
FOREIGN KEY (order_id) REFERENCES kiosk_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
​-- 3. Kiosk Saved Profiles Table (for Reorders)
CREATE TABLE IF NOT EXISTS kiosk_saved_profiles (
id INT AUTO_INCREMENT PRIMARY KEY,
phone_number VARCHAR(20) NOT NULL UNIQUE,
customer_name VARCHAR(100) NOT NULL,
neighborhood_badge VARCHAR(100) DEFAULT 'Night Shift Hero',
last_order_json LONGTEXT NOT NULL,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
