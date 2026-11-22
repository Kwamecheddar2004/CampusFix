-- ==============================================================================
-- CampusFix – Campus Maintenance and IT Fault Reporting & Management System
-- Database Schema and Demonstration Seed Data
-- Target Database: MySQL 8.0+ / MariaDB 10.4+ (XAMPP Environment)
-- Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Version: 1.0.0 (Stage 1 Architecture)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `campusfix` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `campusfix`;

-- ------------------------------------------------------------------------------
-- 1. Table: users
-- Core entity for all system actors (Students/Reporters, Technicians, Administrators)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_code` VARCHAR(30) NOT NULL COMMENT 'Unique identifier (e.g., ADM-2026-001, TECH-2026-001, STU-2026-101)',
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(25) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'technician', 'admin') NOT NULL DEFAULT 'student',
  `department` VARCHAR(100) DEFAULT NULL COMMENT 'Academic department or technical unit',
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `avatar` VARCHAR(255) DEFAULT NULL COMMENT 'Path to profile image or avatar',
  `reset_token` VARCHAR(100) DEFAULT NULL,
  `reset_token_expires_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_user_code` (`user_code`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Users table holding authentication and role credentials';

-- ------------------------------------------------------------------------------
-- 2. Table: categories
-- Fault classification (Wi-Fi, Electrical, Plumbing, AV Systems, etc.)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `department_in_charge` VARCHAR(100) DEFAULT 'General Services',
  `icon` VARCHAR(50) DEFAULT 'bi-gear' COMMENT 'Bootstrap Icon class name',
  `color_code` VARCHAR(20) DEFAULT '#0d6efd' COMMENT 'Hex color code for UI badges',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_name` (`name`),
  UNIQUE KEY `uk_categories_slug` (`slug`),
  KEY `idx_categories_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ticket categories and department classifications';

-- ------------------------------------------------------------------------------
-- 3. Table: campus_locations
-- Standardized campus locations (Buildings, Floors, Rooms) for accurate pinpointing
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `campus_locations`;
CREATE TABLE `campus_locations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `building_name` VARCHAR(100) NOT NULL,
  `building_code` VARCHAR(20) NOT NULL,
  `floor` VARCHAR(50) NOT NULL COMMENT 'Ground Floor, 1st Floor, 2nd Floor, etc.',
  `room_number` VARCHAR(50) DEFAULT NULL COMMENT 'Room CS-101, Reading Hall A, etc.',
  `area_description` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_locations_building_code` (`building_code`),
  KEY `idx_locations_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Campus physical infrastructure and location registry';

-- ------------------------------------------------------------------------------
-- 4. Table: tickets
-- Central entity for fault incidents, status tracking, and resolution details
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(30) NOT NULL COMMENT 'Human-readable unique ID, e.g., CF-2026-0001',
  `reporter_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `location_id` INT UNSIGNED NOT NULL,
  `location_details` VARCHAR(255) DEFAULT NULL COMMENT 'Extra specific notes e.g. Near 3rd window from left',
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
  `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
  `status` ENUM('pending', 'assigned', 'in_progress', 'resolved', 'closed', 'reopened', 'cancelled') NOT NULL DEFAULT 'pending',
  `assigned_to` INT UNSIGNED DEFAULT NULL COMMENT 'Technician currently assigned',
  `assigned_at` DATETIME DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `resolution_notes` TEXT DEFAULT NULL COMMENT 'Summary of repair work done by technician',
  `satisfaction_rating` TINYINT UNSIGNED DEFAULT NULL COMMENT '1 to 5 star rating by student reporter upon resolution',
  `satisfaction_feedback` TEXT DEFAULT NULL,
  `billing_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Repair/Material surcharge if billable',
  `billing_description` VARCHAR(255) DEFAULT NULL COMMENT 'Reason for surcharge (e.g. Replacement Lamp/Cable)',
  `payment_status` ENUM('not_required', 'pending', 'paid', 'waived') NOT NULL DEFAULT 'not_required',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tickets_ticket_number` (`ticket_number`),
  KEY `idx_tickets_reporter` (`reporter_id`),
  KEY `idx_tickets_assigned_to` (`assigned_to`),
  KEY `idx_tickets_category` (`category_id`),
  KEY `idx_tickets_location` (`location_id`),
  KEY `idx_tickets_status` (`status`),
  KEY `idx_tickets_payment_status` (`payment_status`),
  KEY `idx_tickets_severity` (`severity`),
  KEY `idx_tickets_priority` (`priority`),
  KEY `idx_tickets_created_at` (`created_at`),
  CONSTRAINT `fk_tickets_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_location` FOREIGN KEY (`location_id`) REFERENCES `campus_locations` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master tickets table storing fault reports';

-- ------------------------------------------------------------------------------
-- 5. Table: ticket_images
-- Photos uploaded by reporters or technicians showing initial fault or resolved state
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_images`;
CREATE TABLE `ticket_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL COMMENT 'Size in bytes',
  `file_type` VARCHAR(50) NOT NULL COMMENT 'MIME type e.g. image/jpeg',
  `uploaded_by` INT UNSIGNED NOT NULL,
  `image_type` ENUM('fault', 'resolution') NOT NULL DEFAULT 'fault',
  `caption` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_images_ticket` (`ticket_id`),
  KEY `idx_ticket_images_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_images_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_images_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fault proof and repair verification images';

-- ------------------------------------------------------------------------------
-- 6. Table: ticket_assignments
-- Work dispatch records and technician assignment logs
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_assignments`;
CREATE TABLE `ticket_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `technician_id` INT UNSIGNED NOT NULL,
  `assigned_by` INT UNSIGNED NOT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  `status` ENUM('active', 'completed', 'reassigned', 'declined') NOT NULL DEFAULT 'active',
  `instructions` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assignments_ticket` (`ticket_id`),
  KEY `idx_assignments_technician` (`technician_id`),
  KEY `idx_assignments_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_assignments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assignments_tech` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_assignments_admin` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assignment history and technician dispatch logs';

-- ------------------------------------------------------------------------------
-- 7. Table: ticket_status_history
-- Full audit trail of every status transition with timestamps and author
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_status_history`;
CREATE TABLE `ticket_status_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `old_status` VARCHAR(30) DEFAULT NULL,
  `new_status` VARCHAR(30) NOT NULL,
  `changed_by` INT UNSIGNED NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_history_ticket` (`ticket_id`),
  KEY `idx_status_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_status_history_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_status_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable audit trail of status lifecycle transitions';

-- ------------------------------------------------------------------------------
-- 8. Table: ticket_comments
-- Communication thread between reporter, technician, and administrator
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_comments`;
CREATE TABLE `ticket_comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `is_internal` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = internal staff note, 0 = visible to reporter',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comments_ticket` (`ticket_id`),
  KEY `idx_comments_user` (`user_id`),
  CONSTRAINT `fk_comments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conversational notes and investigation updates';

-- ------------------------------------------------------------------------------
-- 9. Table: notifications
-- Notification dispatch queue and user alert mailbox (In-app, Email, SMS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `ticket_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `channel` ENUM('in_app', 'email', 'sms') NOT NULL DEFAULT 'in_app',
  `status` ENUM('pending', 'sent', 'failed', 'mock_delivered') NOT NULL DEFAULT 'pending',
  `error_message` TEXT DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`, `is_read`),
  KEY `idx_notifications_ticket` (`ticket_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User notification delivery queue and logs';

-- ------------------------------------------------------------------------------
-- 10. Table: payments
-- Payment transactions for damage surcharges, materials and repair contributions
-- Supports Paystack Sandbox, Mobile Money, and card sandbox settlements
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_reference` VARCHAR(60) NOT NULL COMMENT 'Unique payment ref e.g. CF-PAY-2026-0001',
  `receipt_number` VARCHAR(60) NOT NULL COMMENT 'Official printable receipt number e.g. RCP-2026-0001',
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'Student/Reporter payer',
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'GHS',
  `gateway` VARCHAR(40) NOT NULL DEFAULT 'paystack_sandbox' COMMENT 'paystack_sandbox, momo_mock, manual_receipt',
  `payment_method` VARCHAR(30) NOT NULL DEFAULT 'mobile_money' COMMENT 'mobile_money, card, bank_transfer',
  `payment_channel` VARCHAR(50) DEFAULT NULL COMMENT 'MTN MoMo, Telecel Cash, AT Money, Visa, Mastercard',
  `payer_phone` VARCHAR(30) DEFAULT NULL,
  `payer_email` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending', 'success', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  `gateway_response` TEXT DEFAULT NULL COMMENT 'Raw gateway verification payload or mock transaction ID',
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payments_reference` (`payment_reference`),
  UNIQUE KEY `uk_payments_receipt` (`receipt_number`),
  KEY `idx_payments_ticket` (`ticket_id`),
  KEY `idx_payments_user` (`user_id`),
  KEY `idx_payments_status` (`status`),
  CONSTRAINT `fk_payments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment transaction records and receipts';

-- ------------------------------------------------------------------------------
-- 11. Table: activity_logs
-- System-wide audit log recording actions, IP addresses, and events
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL COMMENT 'e.g., ticket_created, status_updated, user_login',
  `entity_type` VARCHAR(50) NOT NULL COMMENT 'e.g., ticket, user, category, location',
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `details` JSON DEFAULT NULL COMMENT 'Structured event metadata',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_action` (`action`),
  KEY `idx_activity_created_at` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit logs for security and compliance';

-- ------------------------------------------------------------------------------
-- 11. Table: system_settings
-- System configuration parameters, SLA thresholds, and SMTP/SMS settings
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'general, mail, sms, sla, security',
  `description` VARCHAR(255) DEFAULT NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether exposed to frontend JS',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Key-value system configurations';

SET FOREIGN_KEY_CHECKS = 1;


-- ==============================================================================
-- DEMONSTRATION SEED DATA
-- Default accounts, categories, locations, sample tickets, history & settings
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Seed Users (Bcrypt Passwords: Admin@123, Tech@123, Student@123)
-- ------------------------------------------------------------------------------
INSERT INTO `users` (`id`, `user_code`, `full_name`, `email`, `phone`, `password_hash`, `role`, `department`, `status`, `avatar`) VALUES
-- 1 Administrator
(1, 'ADM-2026-001', 'Admin Kwame Mensah', 'admin@campusfix.edu', '+233201112233', '$2y$10$T.RYWzm2aUuutzO/JjD9Veivv3ASMpDAibqTtG0OwcF8vS2ufnUFm', 'admin', 'IT & Estate Management', 'active', 'assets/images/avatars/admin.png'),
-- 3 Technicians
(2, 'TECH-2026-001', 'Samuel Osei', 'samuel.it@campusfix.edu', '+233242223344', '$2y$10$F2CKsIm8k5.VhR8d7blQ.ufNotKZtmCayjGSGH3HMdQd.Sk.FlMiq', 'technician', 'IT Directorate & Networking', 'active', 'assets/images/avatars/tech1.png'),
(3, 'TECH-2026-002', 'Akua Mensah-Bonsu', 'akua.electric@campusfix.edu', '+233243334455', '$2y$10$F2CKsIm8k5.VhR8d7blQ.ufNotKZtmCayjGSGH3HMdQd.Sk.FlMiq', 'technician', 'Physical Works - Electrical', 'active', 'assets/images/avatars/tech2.png'),
(4, 'TECH-2026-003', 'David Appiah', 'david.plumber@campusfix.edu', '+233244445566', '$2y$10$F2CKsIm8k5.VhR8d7blQ.ufNotKZtmCayjGSGH3HMdQd.Sk.FlMiq', 'technician', 'Physical Works - Plumbing', 'active', 'assets/images/avatars/tech3.png'),
-- 3 Students / Reporters
(5, 'STU-2026-101', 'Emmanuel Frimpong', 'emmanuel.stu@campusfix.edu', '+233265556677', '$2y$10$o5kJqtRddkTyvJ.Po9Dey.r0sj2dwuO5HthPuQ1Wne5u/sK6p/D.6', 'student', 'Computer Science Dept', 'active', 'assets/images/avatars/stu1.png'),
(6, 'STU-2026-102', 'Grace Ansah', 'grace.stu@campusfix.edu', '+233266667788', '$2y$10$o5kJqtRddkTyvJ.Po9Dey.r0sj2dwuO5HthPuQ1Wne5u/sK6p/D.6', 'student', 'Electrical Engineering', 'active', 'assets/images/avatars/stu2.png'),
(7, 'STU-2026-103', 'Michael Boateng', 'michael.stu@campusfix.edu', '+233267778899', '$2y$10$o5kJqtRddkTyvJ.Po9Dey.r0sj2dwuO5HthPuQ1Wne5u/sK6p/D.6', 'student', 'Business Administration', 'active', 'assets/images/avatars/stu3.png');

-- ------------------------------------------------------------------------------
-- Seed Categories
-- ------------------------------------------------------------------------------
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `department_in_charge`, `icon`, `color_code`, `is_active`) VALUES
(1, 'Wi-Fi & Internet Connectivity', 'wifi-connectivity', 'Campus Wi-Fi access points, router drops, LAN Ethernet cables, DNS issues', 'IT Directorate', 'bi-wifi', '#0d6efd', 1),
(2, 'Computer Hardware & Lab PCs', 'computer-hardware', 'Damaged desktop PCs, failing hard drives, keyboards, mice, power supplies', 'IT Directorate', 'bi-pc-display', '#6f42c1', 1),
(3, 'Projectors & Audio-Visual', 'projectors-av', 'Lecture room projectors, broken HDMI/VGA cables, sound amplifiers, microphones', 'AV & Media Unit', 'bi-projector', '#fd7e14', 1),
(4, 'Printers, Scanners & Copiers', 'printers-copiers', 'Paper jams, toner replacement, network print queue errors, scanner defects', 'IT Directorate', 'bi-printer', '#20c997', 1),
(5, 'Electrical & Power Outlets', 'electrical-power', 'Sparks from sockets, tripping breakers, dead wall outlets, dangerous wiring', 'Physical Works - Electrical', 'bi-lightning-charge', '#dc3545', 1),
(6, 'Lighting & Ceiling Fixtures', 'lighting-fixtures', 'Flickering fluorescent tubes, broken switches, dark corridors, stadium lights', 'Physical Works - Electrical', 'bi-lightbulb', '#ffc107', 1),
(7, 'Plumbing, Washrooms & Water', 'plumbing-water', 'Burst pipes, running toilets, clogged sinks, water shortage, leaking valves', 'Physical Works - Plumbing', 'bi-droplet', '#0dcaf0', 1),
(8, 'Classroom Furniture & Structural', 'furniture-structural', 'Broken lecture seats, cracked whiteboards, stuck door locks, broken windows', 'Estate & Maintenance', 'bi-door-closed', '#6c757d', 1);

-- ------------------------------------------------------------------------------
-- Seed Campus Locations
-- ------------------------------------------------------------------------------
INSERT INTO `campus_locations` (`id`, `building_name`, `building_code`, `floor`, `room_number`, `area_description`, `is_active`) VALUES
(1, 'Department of Computer Science', 'CS-BLOCK', 'Ground Floor', 'CS Lab 1', 'Main software engineering lab with 50 desktop stations', 1),
(2, 'Department of Computer Science', 'CS-BLOCK', '1st Floor', 'Room CS-204', 'Lecture auditorium with motorized projection screen', 1),
(3, 'Balme Central Library', 'LIB-MAIN', 'Ground Floor', 'Reading Hall A', 'Main quiet reading area with 120 charging desks', 1),
(4, 'Balme Central Library', 'LIB-MAIN', '1st Floor', 'Digital E-Library', 'Research computer lab and network printing station', 1),
(5, 'School of Engineering', 'ENG-BLDG', '2nd Floor', 'Embedded Systems Lab', 'Robotics and circuit testing lab (Room ENG-215)', 1),
(6, 'Great Hall & Auditorium', 'GH-AUD', 'Ground Floor', 'Main Auditorium', 'University central auditorium and stage area', 1),
(7, 'Commonwealth Hall (Residence)', 'HALL-CW', '2nd Floor', 'Room B-210', 'Student residential room and corridor washrooms', 1),
(8, 'ICT Directorate Annex', 'ICT-CTR', 'Ground Floor', 'Server Room Annex', 'Campus network operations gateway and distribution hub', 1);

-- ------------------------------------------------------------------------------
-- Seed Tickets (Covering all real-world lifecycle statuses & billing surcharges)
-- ------------------------------------------------------------------------------
INSERT INTO `tickets` (`id`, `ticket_number`, `reporter_id`, `category_id`, `location_id`, `location_details`, `title`, `description`, `severity`, `priority`, `status`, `assigned_to`, `assigned_at`, `resolved_at`, `closed_at`, `resolution_notes`, `satisfaction_rating`, `satisfaction_feedback`, `billing_amount`, `billing_description`, `payment_status`, `created_at`) VALUES
-- Ticket 1: Resolved & Confirmed (No surcharge)
(1, 'CF-2026-0001', 5, 1, 1, 'Near workstation 12 to 18 on the left row', 'Campus Wi-Fi AP constantly disconnecting in CS Lab 1', 'The Cisco Access Point AP-CS1 blinks amber and drops client connections every 5 minutes during lab sessions.', 'high', 'high', 'resolved', 2, '2026-11-15 09:30:00', '2026-11-15 14:45:00', '2026-11-16 10:00:00', 'Firmware updated on AP-CS1, PoE switch port recycled and static IP binding re-configured. Tested with 35 concurrent clients with 0% packet loss.', 5, 'Great job! The Wi-Fi is super fast and stable now during afternoon practicals.', 0.00, NULL, 'not_required', '2026-11-15 08:15:00'),

-- Ticket 2: In Progress
(2, 'CF-2026-0002', 6, 3, 2, 'Ceiling mount above the podium', 'Epson ceiling projector flickering green and turning off', 'Projector lamp flickers green for 2 minutes then overheats and shuts down with an orange indicator lamp.', 'medium', 'medium', 'in_progress', 2, '2026-11-18 10:00:00', NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 'not_required', '2026-11-18 09:00:00'),

-- Ticket 3: Assigned (Pending Payment Surcharge for Demonstration)
(3, 'CF-2026-0003', 5, 5, 3, 'Wall column adjacent to study carrels 24-28', 'Sparks and burning smell from 3-pin power outlet', 'When students plug laptop chargers into socket #4 on column C, small sparks fly out and there is a burning plastic odor.', 'critical', 'urgent', 'assigned', 3, '2026-11-20 11:30:00', NULL, NULL, NULL, NULL, NULL, 45.00, 'Safety Breaker & Double Switched Socket Replacement Material', 'pending', '2026-11-20 11:05:00'),

-- Ticket 4: Pending (New report awaiting triage)
(4, 'CF-2026-0004', 7, 7, 7, '2nd Floor Male Washroom, cubicle 3', 'High-pressure water leakage from cistern supply valve', 'The flexible hose under the cistern burst and water is flooding the washroom floor and seeping into the hallway.', 'high', 'urgent', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 'not_required', '2026-11-21 07:45:00'),

-- Ticket 5: Reopened (Previous repair was incomplete)
(5, 'CF-2026-0005', 6, 6, 5, 'Overhead row 2 above soldering benches', 'Fluorescent tubes buzzing loudly and strobing uncontrollably', 'The ballast was replaced last week but the light started strobing and buzzing again today during lab experiment.', 'low', 'medium', 'reopened', 3, '2026-11-19 14:00:00', NULL, NULL, 'Previous technician replaced starter only. Ballast transformer is vibrating and needs full replacement unit.', NULL, NULL, 0.00, NULL, 'not_required', '2026-11-12 16:20:00'),

-- Ticket 6: Closed (Paid Surcharge with Verified Paystack Sandbox Receipt)
(6, 'CF-2026-0006', 7, 4, 4, 'Next to librarian front desk', 'HP LaserJet M605 paper feed roller slipping and jamming', 'Printer displays Error 13.00.00 Paper Jam. Paper tray 2 feed roller is worn out and fails to pick sheets.', 'medium', 'medium', 'closed', 2, '2026-11-10 13:00:00', '2026-11-11 11:30:00', '2026-11-12 09:00:00', 'Replaced pickup roller and separation pad (Part #RM2-6303). Cleaned paper path sensors. Successfully test printed 100 duplex pages.', 4, 'Fixed on time. Thank you.', 75.00, 'Replacement HP LaserJet Pickup Roller & Separation Pad (Part #RM2-6303)', 'paid', '2026-11-10 11:15:00');

-- ------------------------------------------------------------------------------
-- Seed Ticket Assignments
-- ------------------------------------------------------------------------------
INSERT INTO `ticket_assignments` (`id`, `ticket_id`, `technician_id`, `assigned_by`, `assigned_at`, `completed_at`, `status`, `instructions`) VALUES
(1, 1, 2, 1, '2026-11-15 09:30:00', '2026-11-15 14:45:00', 'completed', 'Check PoE switch port and reset AP firmware.'),
(2, 2, 2, 1, '2026-11-18 10:00:00', NULL, 'active', 'Inspect air intake filter and replace projector lamp if runtime > 3000 hours.'),
(3, 3, 3, 1, '2026-11-20 11:30:00', NULL, 'active', 'CRITICAL SAFETY: Isolate breaker immediately before opening socket plate!'),
(4, 5, 3, 1, '2026-11-19 14:00:00', NULL, 'active', 'Reopened report. Replace full electronic ballast fixture rather than starter.'),
(5, 6, 2, 1, '2026-11-10 13:00:00', '2026-11-11 11:30:00', 'completed', 'Collect replacement roller kit from ICT stores.');

-- ------------------------------------------------------------------------------
-- Seed Ticket Status History (Audit trail of transitions)
-- ------------------------------------------------------------------------------
INSERT INTO `ticket_status_history` (`id`, `ticket_id`, `old_status`, `new_status`, `changed_by`, `notes`, `created_at`) VALUES
-- Ticket 1 Transitions
(1, 1, NULL, 'pending', 5, 'Fault report submitted by student.', '2026-11-15 08:15:00'),
(2, 1, 'pending', 'assigned', 1, 'Assigned to Technician Samuel Osei.', '2026-11-15 09:30:00'),
(3, 1, 'assigned', 'in_progress', 2, 'Technician began diagnostics on PoE switch.', '2026-11-15 11:00:00'),
(4, 1, 'in_progress', 'resolved', 2, 'AP firmware flashed and stress tested.', '2026-11-15 14:45:00'),
(5, 1, 'resolved', 'closed', 5, 'Student verified fix and submitted 5-star rating.', '2026-11-16 10:00:00'),

-- Ticket 2 Transitions
(6, 2, NULL, 'pending', 6, 'Fault report submitted by student.', '2026-11-18 09:00:00'),
(7, 2, 'pending', 'assigned', 1, 'Assigned to Technician Samuel Osei.', '2026-11-18 10:00:00'),
(8, 2, 'assigned', 'in_progress', 2, 'Inspected filter; replacement lamp ordered from store.', '2026-11-18 14:15:00'),

-- Ticket 3 Transitions
(9, 3, NULL, 'pending', 5, 'Urgent safety hazard reported.', '2026-11-20 11:05:00'),
(10, 3, 'pending', 'assigned', 1, 'Priority escalated to Urgent; assigned to Akua Mensah.', '2026-11-20 11:30:00'),

-- Ticket 4 Transitions
(11, 4, NULL, 'pending', 7, 'Plumbing fault submitted by student.', '2026-11-21 07:45:00'),

-- Ticket 5 Transitions
(12, 5, NULL, 'pending', 6, 'Initial report of buzzing light submitted.', '2026-11-12 16:20:00'),
(13, 5, 'pending', 'assigned', 1, 'Assigned to Akua Mensah.', '2026-11-13 09:00:00'),
(14, 5, 'assigned', 'resolved', 3, 'Starter replaced.', '2026-11-14 15:00:00'),
(15, 5, 'resolved', 'reopened', 6, 'Issue recurred. Buzzing sound and strobing persists.', '2026-11-19 13:30:00'),
(16, 5, 'reopened', 'assigned', 1, 'Re-assigned to Akua Mensah for ballast replacement.', '2026-11-19 14:00:00');

-- ------------------------------------------------------------------------------
-- Seed Ticket Comments & Internal Notes
-- ------------------------------------------------------------------------------
INSERT INTO `ticket_comments` (`id`, `ticket_id`, `user_id`, `comment`, `is_internal`, `created_at`) VALUES
(1, 1, 2, 'Connected console cable to AP-CS1. Found kernel memory panic in syslogs due to outdated 8.2 firmware.', 1, '2026-11-15 11:30:00'),
(2, 1, 2, 'We have flashed firmware to build 8.5.182.0. Please test connections.', 0, '2026-11-15 14:50:00'),
(3, 2, 2, 'Air filter is severely clogged with dust. Cleaned filter; testing if lamp cooling improves.', 1, '2026-11-18 14:30:00'),
(4, 3, 1, 'Safety tape placed around Column C socket. Main circuit breaker sub-panel Q3 switched OFF.', 1, '2026-11-20 11:35:00'),
(5, 5, 6, 'The light is still strobing badly and makes a high pitched humming sound that disturbs class.', 0, '2026-11-19 13:32:00');

-- ------------------------------------------------------------------------------
-- Seed Notifications
-- ------------------------------------------------------------------------------
INSERT INTO `notifications` (`id`, `user_id`, `ticket_id`, `title`, `message`, `channel`, `status`, `is_read`, `read_at`, `created_at`) VALUES
(1, 5, 1, 'Ticket Assigned', 'Your ticket CF-2026-0001 has been assigned to Technician Samuel Osei.', 'in_app', 'sent', 1, '2026-11-15 09:35:00', '2026-11-15 09:30:00'),
(2, 5, 1, 'Ticket Resolved', 'Technician Samuel Osei marked ticket CF-2026-0001 as Resolved. Please confirm.', 'email', 'sent', 1, '2026-11-15 15:00:00', '2026-11-15 14:46:00'),
(3, 2, 2, 'New Job Assigned', 'Admin assigned you ticket CF-2026-0002: Epson ceiling projector flickering.', 'in_app', 'sent', 1, '2026-11-18 10:05:00', '2026-11-18 10:00:00'),
(4, 3, 3, 'URGENT: Electrical Hazard Assigned', 'Ticket CF-2026-0003: Sparks from power outlet in Balme Library.', 'sms', 'mock_delivered', 0, NULL, '2026-11-20 11:30:00'),
(5, 1, 4, 'New Fault Reported', 'Student Michael Boateng reported a water leakage in Commonwealth Hall (CF-2026-0004).', 'in_app', 'sent', 0, NULL, '2026-11-21 07:45:00'),
(6, 7, 6, 'Payment Receipt Confirmed', 'Payment of GH₵ 75.00 for Ticket CF-2026-0006 confirmed via Paystack Sandbox (Ref: CF-PAY-2026-0001).', 'in_app', 'sent', 1, '2026-11-10 12:35:00', '2026-11-10 12:30:00');

-- ------------------------------------------------------------------------------
-- Seed Payments (Paystack Sandbox & Mobile Money Settlements)
-- ------------------------------------------------------------------------------
INSERT INTO `payments` (`id`, `payment_reference`, `receipt_number`, `ticket_id`, `user_id`, `amount`, `currency`, `gateway`, `payment_method`, `payment_channel`, `payer_phone`, `payer_email`, `status`, `gateway_response`, `paid_at`, `created_at`) VALUES
(1, 'CF-PAY-2026-0001', 'RCP-2026-0001', 6, 7, 75.00, 'GHS', 'paystack_sandbox', 'mobile_money', 'MTN MoMo', '+233267778899', 'michael.stu@campusfix.edu', 'success', '{"status":"success","reference":"CF-PAY-2026-0001","amount":7500,"currency":"GHS","channel":"mobile_money","bank":"MTN MoMo","simulation":true}', '2026-11-10 12:30:00', '2026-11-10 12:28:00');

-- ------------------------------------------------------------------------------
-- Seed System Settings
-- ------------------------------------------------------------------------------
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `description`, `is_public`) VALUES
(1, 'app_name', 'CampusFix', 'general', 'Platform Name displayed in header and emails', 1),
(2, 'app_tagline', 'Campus Maintenance & IT Fault Reporting System', 'general', 'System Subtitle', 1),
(3, 'institution_name', 'University Campus Helpdesk', 'general', 'Host University or College name', 1),
(4, 'ticket_prefix', 'CF', 'general', 'Prefix used in ticket number generation', 0),
(5, 'sla_urgent_hours', '4', 'sla', 'Target resolution window for urgent tickets (hours)', 0),
(6, 'sla_high_hours', '12', 'sla', 'Target resolution window for high severity tickets (hours)', 0),
(7, 'sla_medium_hours', '24', 'sla', 'Target resolution window for medium severity tickets (hours)', 0),
(8, 'sla_low_hours', '72', 'sla', 'Target resolution window for low severity tickets (hours)', 0),
(9, 'mail_mailer', 'smtp', 'mail', 'Email transport driver (smtp or mail)', 0),
(10, 'smtp_host', 'smtp.gmail.com', 'mail', 'Outgoing SMTP server hostname', 0),
(11, 'smtp_port', '587', 'mail', 'Outgoing SMTP server port (587 TLS or 465 SSL)', 0),
(12, 'smtp_user', 'campusfix.alerts@gmail.com', 'mail', 'SMTP authentication username/email', 0),
(13, 'smtp_pass', '', 'mail', 'SMTP authentication password or App Password', 0),
(14, 'smtp_encryption', 'tls', 'mail', 'SMTP transport encryption protocol', 0),
(15, 'mail_from_address', 'campusfix.alerts@gmail.com', 'mail', 'Sender email address in outgoing notifications', 0),
(16, 'mail_from_name', 'CampusFix Helpdesk Alerts', 'mail', 'Display sender name in outgoing notifications', 1),
(17, 'mail_mock_mode', '1', 'mail', 'If 1, logs email payload without requiring live SMTP credentials', 0),
(18, 'sms_enabled', '1', 'sms', 'Global switch to enable SMS notification delivery', 0),
(19, 'sms_mock_mode', '1', 'sms', 'If 1, simulates SMS dispatch to console/db without sending actual carrier SMS', 0),
(20, 'sms_provider', 'hubtel', 'sms', 'SMS API gateway provider name (e.g. hubtel, twilio, arkesel)', 0),
(21, 'sms_api_key', '', 'sms', 'API key for third-party SMS gateway', 0),
(22, 'sms_sender_id', 'CampusFix', 'sms', 'Sender alphanumeric ID on mobile phones', 1),
(23, 'max_upload_size_mb', '5', 'security', 'Maximum image upload size in megabytes per file', 1),
(24, 'allowed_image_types', 'jpg,jpeg,png,webp', 'security', 'Comma-separated allowed image extensions', 1),
(25, 'payment_gateway', 'paystack', 'payment', 'Default payment gateway provider (paystack, stripe, mock)', 0),
(26, 'payment_sandbox_mode', '1', 'payment', 'If 1, operates in sandbox/test simulation without charging real money', 1),
(27, 'paystack_public_key', 'pk_test_sample_campusfix_key', 'payment', 'Paystack public test key', 1),
(28, 'paystack_secret_key', 'sk_test_sample_campusfix_secret', 'payment', 'Paystack secret test key', 0),
(29, 'currency_code', 'GHS', 'payment', 'Currency ISO code', 1),
(30, 'currency_symbol', 'GH₵', 'payment', 'Currency display symbol', 1),
(31, 'billing_enabled', '1', 'payment', 'Global toggle to enable maintenance surcharges and payments', 1),
(32, 'paystack_payment_link', 'https://paystack.shop/pay/0fwhj--t6n', 'payment', 'Direct Paystack payment storefront URL', 1);

-- ------------------------------------------------------------------------------
-- Seed Activity Logs (Sample historical audit records)
-- ------------------------------------------------------------------------------
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
(1, 1, 'system_initialized', 'system', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '{"event": "Database schema created and seed data initialized"}', '2026-11-21 12:00:00'),
(2, 5, 'ticket_created', 'ticket', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '{"ticket_number": "CF-2026-0001", "category": "Wi-Fi & Internet Connectivity"}', '2026-11-15 08:15:00'),
(3, 1, 'ticket_assigned', 'ticket', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '{"ticket_number": "CF-2026-0001", "technician_id": 2}', '2026-11-15 09:30:00'),
(4, 2, 'ticket_resolved', 'ticket', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '{"ticket_number": "CF-2026-0001", "status": "resolved"}', '2026-11-15 14:45:00'),
(5, 5, 'ticket_closed', 'ticket', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '{"ticket_number": "CF-2026-0001", "rating": 5}', '2026-11-16 10:00:00');
