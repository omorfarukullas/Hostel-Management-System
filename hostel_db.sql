-- ============================================================
-- Hostel Management System — Database Setup
-- Database: hostel_db
-- MySQL 8+
-- ============================================================

CREATE DATABASE IF NOT EXISTS hostel_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hostel_db;

-- ============================================================
-- 1. USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name       VARCHAR(150)     NOT NULL,
    email      VARCHAR(150)     NOT NULL,
    password   VARCHAR(255)     NOT NULL,
    role       ENUM('admin','warden','student') NOT NULL DEFAULT 'student',
    phone      VARCHAR(20)      DEFAULT NULL,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. ROOMS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS rooms (
    room_id      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    room_number  VARCHAR(20)    NOT NULL,
    floor        TINYINT        NOT NULL DEFAULT 1,
    type         ENUM('single','double','triple','dormitory') NOT NULL DEFAULT 'single',
    capacity     TINYINT        NOT NULL DEFAULT 1,
    occupied     TINYINT        NOT NULL DEFAULT 0,
    monthly_fee  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    status       ENUM('available','full','maintenance') NOT NULL DEFAULT 'available',
    amenities    TEXT           DEFAULT NULL,
    created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (room_id),
    UNIQUE KEY uq_rooms_number (room_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. STUDENTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    student_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED DEFAULT NULL,
    student_code    VARCHAR(20)  NOT NULL,
    name            VARCHAR(150) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    phone           VARCHAR(20)  DEFAULT NULL,
    dob             DATE         DEFAULT NULL,
    gender          ENUM('male','female','other') NOT NULL DEFAULT 'male',
    address         TEXT         DEFAULT NULL,
    guardian_name   VARCHAR(150) DEFAULT NULL,
    guardian_phone  VARCHAR(20)  DEFAULT NULL,
    room_id         INT UNSIGNED DEFAULT NULL,
    check_in_date   DATE         DEFAULT NULL,
    check_out_date  DATE         DEFAULT NULL,
    photo           VARCHAR(255) DEFAULT NULL,
    status          ENUM('active','checked_out','suspended') NOT NULL DEFAULT 'active',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_student_code (student_code),
    CONSTRAINT fk_students_user   FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_students_room   FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. FEES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS fees (
    fee_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NOT NULL,
    room_id         INT UNSIGNED DEFAULT NULL,
    amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fee_month       VARCHAR(20)  NOT NULL,
    fee_year        YEAR         NOT NULL,
    payment_date    DATE         DEFAULT NULL,
    payment_method  ENUM('cash','bank_transfer','online') NOT NULL DEFAULT 'cash',
    status          ENUM('paid','unpaid','partial') NOT NULL DEFAULT 'unpaid',
    remarks         TEXT         DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (fee_id),
    CONSTRAINT fk_fees_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fees_room    FOREIGN KEY (room_id)    REFERENCES rooms(room_id)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. BOOKINGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS bookings (
    booking_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id     INT UNSIGNED NOT NULL,
    room_id        INT UNSIGNED NOT NULL,
    booking_date   DATE         NOT NULL DEFAULT (CURDATE()),
    check_in_date  DATE         DEFAULT NULL,
    check_out_date DATE         DEFAULT NULL,
    status         ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
    remarks        TEXT         DEFAULT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (booking_id),
    CONSTRAINT fk_bookings_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_room    FOREIGN KEY (room_id)    REFERENCES rooms(room_id)    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. COMPLAINTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS complaints (
    complaint_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT         NOT NULL,
    category        ENUM('maintenance','food','security','cleanliness','other') NOT NULL DEFAULT 'other',
    priority        ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    status          ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    admin_response  TEXT         DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at     DATETIME     DEFAULT NULL,
    PRIMARY KEY (complaint_id),
    CONSTRAINT fk_complaints_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. NOTICES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS notices (
    notice_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(255) NOT NULL,
    content     TEXT         NOT NULL,
    posted_by   INT UNSIGNED DEFAULT NULL,
    target_role ENUM('all','student','warden') NOT NULL DEFAULT 'all',
    is_pinned   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME     DEFAULT NULL,
    PRIMARY KEY (notice_id),
    CONSTRAINT fk_notices_user FOREIGN KEY (posted_by) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. VISITORS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS visitors (
    visitor_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id    INT UNSIGNED NOT NULL,
    visitor_name  VARCHAR(150) NOT NULL,
    visitor_phone VARCHAR(20)  DEFAULT NULL,
    relation      VARCHAR(80)  DEFAULT NULL,
    purpose       TEXT         DEFAULT NULL,
    check_in      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    check_out     DATETIME     DEFAULT NULL,
    approved_by   INT UNSIGNED DEFAULT NULL,
    status        ENUM('pending','approved','denied','checked_out') NOT NULL DEFAULT 'pending',
    PRIMARY KEY (visitor_id),
    CONSTRAINT fk_visitors_student  FOREIGN KEY (student_id)  REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_visitors_approver FOREIGN KEY (approved_by) REFERENCES users(user_id)       ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user  (password: Admin@123)
INSERT INTO users (name, email, password, role, phone, status) VALUES
('Admin', 'admin@hostel.com', '$2y$12$UAKyZct5so1Mf3Sb1xiNyeNkIOdmhM0Vb2l3FgKZxdV1fim69J1Fi', 'admin', '01700000000', 'active');

-- Sample Rooms (6 rooms across 3 floors)
INSERT INTO rooms (room_number, floor, type, capacity, occupied, monthly_fee, status, amenities) VALUES
('101', 1, 'single',    1, 0,  3000.00, 'available', 'AC, Attached Bathroom, WiFi'),
('102', 1, 'double',    2, 0,  2500.00, 'available', 'Fan, Shared Bathroom, WiFi'),
('201', 2, 'double',    2, 0,  2800.00, 'available', 'AC, Attached Bathroom, WiFi, Balcony'),
('202', 2, 'triple',    3, 0,  2000.00, 'available', 'Fan, Shared Bathroom'),
('301', 3, 'single',    1, 0,  3500.00, 'available', 'AC, Attached Bathroom, WiFi, City View'),
('302', 3, 'dormitory', 6, 0,  1200.00, 'available', 'Fan, Shared Bathroom, Locker');
