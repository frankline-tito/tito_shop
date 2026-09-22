-- Tito Venture Shop - MIGRATION for a database you already set up.
-- Run this ONCE in phpMyAdmin (SQL tab) against your existing shop_system database.
-- If a line errors with "column already exists", you already ran it - skip that line and continue with the rest.

ALTER TABLE products ADD COLUMN barcode VARCHAR(50) NULL UNIQUE AFTER name;

ALTER TABLE sales MODIFY payment_method ENUM('cash','mpesa','bank') NOT NULL;
ALTER TABLE sales ADD COLUMN bank_reference VARCHAR(20) NULL UNIQUE AFTER mpesa_code;

ALTER TABLE users ADD COLUMN status ENUM('active','pending') NOT NULL DEFAULT 'active' AFTER role;
ALTER TABLE users ADD COLUMN security_question VARCHAR(255) NULL AFTER status;
ALTER TABLE users ADD COLUMN security_answer_hash VARCHAR(255) NULL AFTER security_question;

CREATE TABLE IF NOT EXISTS settings (
  id INT PRIMARY KEY,
  mpesa_number VARCHAR(20) NULL,
  bank_name VARCHAR(100) NULL,
  bank_account_name VARCHAR(100) NULL,
  bank_account_number VARCHAR(40) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL UNIQUE,
  email VARCHAR(150) NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  delivery_phone VARCHAR(20) NOT NULL,
  delivery_address VARCHAR(255) NOT NULL,
  payment_method ENUM('cash','mpesa','bank') NOT NULL,
  payment_reference VARCHAR(20) NULL,
  status ENUM('pending','confirmed','delivered','cancelled') NOT NULL DEFAULT 'pending',
  total DECIMAL(12,2) NOT NULL,
  confirmed_sale_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_orders_sale FOREIGN KEY (confirmed_sale_id) REFERENCES sales(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_oitems_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oitems_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;
