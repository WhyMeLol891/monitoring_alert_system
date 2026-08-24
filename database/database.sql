-- Website Monitoring System Database Schema
-- Compatible with MySQL 5.7+ / 8.0+ and MariaDB (XAMPP / cPanel)

CREATE DATABASE IF NOT EXISTS `db_website_monitor` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_website_monitor`;

-- 1. Admins Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Websites Table
CREATE TABLE IF NOT EXISTS `websites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `url` VARCHAR(255) NOT NULL,
    `monitoring_interval` INT NOT NULL DEFAULT 5 COMMENT 'Interval in minutes',
    `slow_threshold` INT NOT NULL DEFAULT 3000 COMMENT 'Slow response threshold in milliseconds',
    `enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Enabled, 0 = Disabled',
    `current_status` ENUM('UP', 'DOWN', 'SLOW', 'PENDING') NOT NULL DEFAULT 'PENDING',
    `last_checked` DATETIME DEFAULT NULL,
    `response_time` INT DEFAULT NULL COMMENT 'Response time in milliseconds',
    `http_status_code` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_enabled_status` (`enabled`, `current_status`),
    INDEX `idx_last_checked` (`last_checked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Monitoring Logs Table (Kept for 90 days)
CREATE TABLE IF NOT EXISTS `monitoring_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `website_id` INT NOT NULL,
    `status` ENUM('UP', 'DOWN', 'SLOW') NOT NULL,
    `response_time` INT NOT NULL COMMENT 'Response time in milliseconds',
    `http_status_code` INT DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_website_checked` (`website_id`, `checked_at`),
    INDEX `idx_checked_at` (`checked_at`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_logs_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Incidents Table
CREATE TABLE IF NOT EXISTS `incidents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `website_id` INT NOT NULL,
    `previous_status` VARCHAR(20) DEFAULT NULL,
    `current_status` VARCHAR(20) NOT NULL,
    `response_time` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` DATETIME DEFAULT NULL,
    INDEX `idx_website_incidents` (`website_id`, `created_at`),
    INDEX `idx_resolved_at` (`resolved_at`),
    CONSTRAINT `fk_incidents_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Telegram Configuration Table
CREATE TABLE IF NOT EXISTS `telegram_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bot_token` VARCHAR(255) NOT NULL DEFAULT '',
    `chat_id` VARCHAR(100) NOT NULL DEFAULT '',
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Admin User (admin / admin123)
INSERT INTO `admins` (`username`, `password`, `created_at`) 
VALUES ('admin', '$2y$12$TIViv9anuNFylLrN.3DlHehw7jGrNyeoRQ9PmihL66RA0/9X7gFRi', NOW())
ON DUPLICATE KEY UPDATE `username` = `username`;

-- Default Telegram Config row
INSERT INTO `telegram_config` (`id`, `bot_token`, `chat_id`, `enabled`, `updated_at`)
VALUES (1, '', '', 0, NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Initial sample websites for convenience (User can edit or delete)
INSERT INTO `websites` (`name`, `url`, `monitoring_interval`, `slow_threshold`, `enabled`, `current_status`, `created_at`)
VALUES 
('Google Search', 'https://www.google.com', 5, 3000, 1, 'PENDING', NOW()),
('Cloudflare DNS', 'https://1.1.1.1', 5, 2000, 1, 'PENDING', NOW()),
('GitHub', 'https://github.com', 5, 3500, 1, 'PENDING', NOW())
ON DUPLICATE KEY UPDATE `name` = `name`;
