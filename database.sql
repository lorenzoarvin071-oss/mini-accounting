-- Mini Accounting System: import this file in phpMyAdmin (Import tab) or run: mysql -u root < database.sql
CREATE DATABASE IF NOT EXISTS mini_accounting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mini_accounting;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(40) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(80) NOT NULL,
  role ENUM('treasurer','officer','adviser','member') NOT NULL,
  member_id INT NULL,
  INDEX (member_id)
);

CREATE TABLE IF NOT EXISTS members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_code VARCHAR(20) NOT NULL UNIQUE,
  student_id VARCHAR(30) NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  course VARCHAR(50) DEFAULT '',
  year_level VARCHAR(20) DEFAULT '',
  contact VARCHAR(80) DEFAULT '',
  fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- One ledger for all money movements. Add new transaction types here later.
CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(20) NOT NULL UNIQUE,
  type ENUM('PAYMENT','ADVANCE','EXPENSE','RETURN','REIMBURSE') NOT NULL,
  tx_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  member_id INT NULL,
  advance_ref VARCHAR(20) NULL,
  description VARCHAR(200) DEFAULT '',
  category VARCHAR(50) DEFAULT '',
  person VARCHAR(100) DEFAULT '',
  receipt_ref VARCHAR(60) DEFAULT '',
  method VARCHAR(30) DEFAULT '',
  voided TINYINT(1) NOT NULL DEFAULT 0,
  void_reason VARCHAR(200) DEFAULT '',
  created_by VARCHAR(40) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (type), INDEX (member_id), INDEX (advance_ref),
  FOREIGN KEY (member_id) REFERENCES members(id)
);

CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(40) NOT NULL,
  action VARCHAR(30) NOT NULL,
  details VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS counters (name VARCHAR(10) PRIMARY KEY, value INT NOT NULL);
CREATE TABLE IF NOT EXISTS settings (k VARCHAR(40) PRIMARY KEY, v VARCHAR(255) NOT NULL);
INSERT IGNORE INTO settings (k, v) VALUES ('org_name', 'My Student Organization'), ('default_fee', '100');
-- Default users are created automatically on first visit (see includes/bootstrap.php).
