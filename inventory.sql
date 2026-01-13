-- Inventory management schema
--
-- This SQL file defines the four tables used by the mini inventory
-- application.  It is loaded automatically by the MySQL container if
-- mounted into `/docker-entrypoint-initdb.d/`.

-- create database if not exists
CREATE DATABASE IF NOT EXISTS `sqldb`;
USE `sqldb`;

-- Suppliers table
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `contact` VARCHAR(191) DEFAULT NULL,
  `address` VARCHAR(191) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products table
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 0,
  `supplier_id` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Purchases table
DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `purchase_date` DATE NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_purchases_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_purchases_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Orders table
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `order_date` DATE NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some sample suppliers
INSERT INTO `suppliers` (`name`, `contact`, `address`) VALUES
('Acme Supplies', 'info@acme.com', '123 Main St'),
('Global Wholesale', 'contact@globalwholesale.com', '456 Market Rd');

-- Insert some sample products
INSERT INTO `products` (`name`, `description`, `price`, `quantity`, `supplier_id`) VALUES
('Widget A', 'Standard widget', 9.99, 100, 1),
('Widget B', 'Advanced widget', 14.50, 50, 1),
('Gadget C', 'Multi-purpose gadget', 24.00, 75, 2);

-- Insert some sample purchases
INSERT INTO `purchases` (`product_id`, `supplier_id`, `quantity`, `purchase_date`) VALUES
(1, 1, 50, '2025-01-01'),
(3, 2, 20, '2025-01-05');

-- Insert some sample orders
INSERT INTO `orders` (`product_id`, `quantity`, `order_date`, `status`) VALUES
(2, 10, '2025-01-10', 'pending'),
(3, 5,  '2025-01-12', 'completed');