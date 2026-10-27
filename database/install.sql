-- Importer ce fichier depuis l'accueil phpMyAdmin (onglet Importer), ou via mysql CLI.
-- Compatible MySQL 5.7+/8 et MariaDB 10.4+ (XAMPP).
CREATE DATABASE IF NOT EXISTS `meeting_room_reservation` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `meeting_room_reservation`;

CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(190) DEFAULT NULL,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','user') NOT NULL DEFAULT 'user',
 legacy_post VARCHAR(40) NOT NULL DEFAULT '',
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 description TEXT,
 capacity INT UNSIGNED DEFAULT NULL,
 location VARCHAR(150) NOT NULL DEFAULT '',
 equipment TEXT,
 image VARCHAR(255) DEFAULT NULL,
 status ENUM('active','maintenance','archived') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservations (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 room_id INT UNSIGNED NOT NULL,
 title VARCHAR(190) NOT NULL,
 description TEXT,
 booking_date DATE NOT NULL,
 start_time TIME NOT NULL,
 end_time TIME NOT NULL,
 attendees INT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'approved',
 remarks TEXT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_room_slot (room_id, booking_date, start_time, end_time, status),
 INDEX idx_user_date (user_id, booking_date),
 CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_res_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS material_reports (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 room_id INT UNSIGNED NOT NULL,
 missing_item VARCHAR(190) NOT NULL,
 description TEXT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_material_room (room_id),
 CONSTRAINT fk_mat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_mat_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 message VARCHAR(255) NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_notice_user (user_id, is_read, created_at),
 CONSTRAINT fk_notice_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED DEFAULT NULL,
 action VARCHAR(90) NOT NULL,
 details VARCHAR(255) NOT NULL DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_activity_date (created_at),
 CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
 setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
 setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO settings (setting_key,setting_value) VALUES ('approval_required','0'),('edit_cutoff_hours','0')
 ON DUPLICATE KEY UPDATE setting_value=setting_value;

CREATE TABLE IF NOT EXISTS login_attempts (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 attempt_key CHAR(64) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_attempt_key (attempt_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
