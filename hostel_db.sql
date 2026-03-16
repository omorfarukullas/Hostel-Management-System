-- ============================================================
-- Hostel Management System — Full Database Setup (v2)
-- Database: hostel_db  |  MySQL 8+
-- Drop & recreate all tables for a fresh install.
-- ============================================================

CREATE DATABASE IF NOT EXISTS hostel_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hostel_db;

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id       INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150)     NOT NULL,
    email         VARCHAR(150)     NOT NULL,
    password      VARCHAR(255)     NOT NULL,
    role          ENUM('admin','supervisor','student') NOT NULL DEFAULT 'student',
    phone         VARCHAR(20)      DEFAULT NULL,
    profile_photo VARCHAR(255)     DEFAULT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. SUPERVISORS  (extended profile for supervisor users)
-- ============================================================
CREATE TABLE IF NOT EXISTS supervisors (
    supervisor_id  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED  NOT NULL,
    block_assigned VARCHAR(80)   DEFAULT NULL COMMENT 'e.g. Block A, Floor 2',
    department     VARCHAR(150)  DEFAULT NULL,
    joined_date    DATE          DEFAULT NULL,
    notes          TEXT          DEFAULT NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (supervisor_id),
    UNIQUE KEY uq_supervisor_user (user_id),
    CONSTRAINT fk_supervisors_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. ROOMS
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
-- 4. STUDENTS
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
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(user_id)     ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_students_room FOREIGN KEY (room_id) REFERENCES rooms(room_id)     ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. ADMISSION REQUESTS  (student self-registration → admin approval)
-- ============================================================
CREATE TABLE IF NOT EXISTS admission_requests (
    request_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150)  NOT NULL,
    email           VARCHAR(150)  NOT NULL,
    password_hash   VARCHAR(255)  NOT NULL,
    phone           VARCHAR(20)   DEFAULT NULL,
    dob             DATE          DEFAULT NULL,
    gender          ENUM('male','female','other') NOT NULL DEFAULT 'male',
    guardian_name   VARCHAR(150)  DEFAULT NULL,
    guardian_phone  VARCHAR(20)   DEFAULT NULL,
    address         TEXT          DEFAULT NULL,
    room_preference VARCHAR(80)   DEFAULT NULL COMMENT 'e.g. single, double',
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reject_reason   TEXT          DEFAULT NULL,
    requested_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by     INT UNSIGNED  DEFAULT NULL,
    reviewed_at     DATETIME      DEFAULT NULL,
    PRIMARY KEY (request_id),
    UNIQUE KEY uq_admission_email (email),
    CONSTRAINT fk_admission_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. FEES
-- ============================================================
CREATE TABLE IF NOT EXISTS fees (
    fee_id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED  NOT NULL,
    room_id         INT UNSIGNED  DEFAULT NULL,
    amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fee_month       VARCHAR(20)   NOT NULL,
    fee_year        YEAR          NOT NULL,
    payment_date    DATE          DEFAULT NULL,
    payment_method  ENUM('cash','bank_transfer','online') NOT NULL DEFAULT 'cash',
    status          ENUM('paid','unpaid','partial') NOT NULL DEFAULT 'unpaid',
    remarks         TEXT          DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (fee_id),
    CONSTRAINT fk_fees_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_fees_room    FOREIGN KEY (room_id)    REFERENCES rooms(room_id)       ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. BOOKINGS
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
    CONSTRAINT fk_bookings_room    FOREIGN KEY (room_id)    REFERENCES rooms(room_id)       ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. ROOM CHANGE REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS room_change_requests (
    request_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id        INT UNSIGNED NOT NULL,
    current_room_id   INT UNSIGNED DEFAULT NULL,
    requested_room_id INT UNSIGNED DEFAULT NULL,
    reason            TEXT         NOT NULL,
    status            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    decided_by        INT UNSIGNED DEFAULT NULL COMMENT 'supervisor user_id',
    reject_reason     TEXT         DEFAULT NULL,
    decided_at        DATETIME     DEFAULT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    CONSTRAINT fk_rcr_student       FOREIGN KEY (student_id)        REFERENCES students(student_id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_rcr_current_room  FOREIGN KEY (current_room_id)   REFERENCES rooms(room_id)       ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_rcr_req_room      FOREIGN KEY (requested_room_id) REFERENCES rooms(room_id)       ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_rcr_decided_by    FOREIGN KEY (decided_by)        REFERENCES users(user_id)       ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. COMPLAINTS
-- ============================================================
CREATE TABLE IF NOT EXISTS complaints (
    complaint_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id     INT UNSIGNED NOT NULL,
    title          VARCHAR(255) NOT NULL,
    description    TEXT         NOT NULL,
    photo          VARCHAR(255) DEFAULT NULL,
    assigned_to    INT UNSIGNED DEFAULT NULL COMMENT 'supervisor user_id',
    category       ENUM('maintenance','plumbing','electricity','cleaning','noise',
                        'food','security','cleanliness','other') NOT NULL DEFAULT 'other',
    priority       ENUM('low','medium','high')                    NOT NULL DEFAULT 'low',
    status         ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    admin_response TEXT         DEFAULT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at    DATETIME     DEFAULT NULL,
    PRIMARY KEY (complaint_id),
    CONSTRAINT fk_complaints_student    FOREIGN KEY (student_id)  REFERENCES students(student_id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_complaints_supervisor FOREIGN KEY (assigned_to) REFERENCES users(user_id)       ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. TASKS  (admin → supervisor)
-- ============================================================
CREATE TABLE IF NOT EXISTS tasks (
    task_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title        VARCHAR(255) NOT NULL,
    description  TEXT         DEFAULT NULL,
    assigned_to  INT UNSIGNED NOT NULL COMMENT 'supervisor user_id',
    assigned_by  INT UNSIGNED NOT NULL COMMENT 'admin user_id',
    due_date     DATE         DEFAULT NULL,
    priority     ENUM('low','medium','high')                    NOT NULL DEFAULT 'medium',
    status       ENUM('pending','in_progress','done','cancelled') NOT NULL DEFAULT 'pending',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id),
    CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. REPAIR COSTS
-- ============================================================
CREATE TABLE IF NOT EXISTS repair_costs (
    cost_id       INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    complaint_id  INT UNSIGNED   DEFAULT NULL,
    title         VARCHAR(255)   NOT NULL,
    description   TEXT           DEFAULT NULL,
    amount        DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    vendor_name   VARCHAR(150)   DEFAULT NULL,
    repair_date   DATE           NOT NULL,
    receipt_photo VARCHAR(255)   DEFAULT NULL,
    created_by    INT UNSIGNED   NOT NULL COMMENT 'admin user_id',
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cost_id),
    CONSTRAINT fk_rc_complaint  FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_rc_created_by FOREIGN KEY (created_by)   REFERENCES users(user_id)           ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. NOTICES
-- ============================================================
CREATE TABLE IF NOT EXISTS notices (
    notice_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title          VARCHAR(255) NOT NULL,
    content        TEXT         NOT NULL,
    posted_by      INT UNSIGNED DEFAULT NULL,
    posted_by_role ENUM('admin','supervisor') NOT NULL DEFAULT 'admin',
    target_role    ENUM('all','student','supervisor') NOT NULL DEFAULT 'all',
    is_pinned      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at     DATETIME     DEFAULT NULL,
    PRIMARY KEY (notice_id),
    CONSTRAINT fk_notices_user FOREIGN KEY (posted_by) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. CHAT MESSAGES  (student ↔ supervisor)
-- ============================================================
CREATE TABLE IF NOT EXISTS chat_messages (
    message_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id    INT UNSIGNED NOT NULL,
    supervisor_id INT UNSIGNED NOT NULL COMMENT 'supervisor user_id',
    sender_role   ENUM('student','supervisor') NOT NULL,
    message       TEXT         NOT NULL,
    is_read       TINYINT(1)   NOT NULL DEFAULT 0,
    sent_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id),
    INDEX idx_chat_student    (student_id),
    INDEX idx_chat_supervisor (supervisor_id),
    INDEX idx_chat_sent_at    (sent_at),
    CONSTRAINT fk_chat_student    FOREIGN KEY (student_id)    REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_chat_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(user_id)       ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user  (password: Admin@123)
INSERT INTO users (name, email, password, role, phone, status) VALUES
('Admin', 'admin@hostel.com', '$2y$12$UAKyZct5so1Mf3Sb1xiNyeNkIOdmhM0Vb2l3FgKZxdV1fim69J1Fi', 'admin', '01700000000', 'active');

-- Sample Rooms
INSERT INTO rooms (room_number, floor, type, capacity, occupied, monthly_fee, status, amenities) VALUES
('101', 1, 'single',    1, 0, 3000.00, 'available', 'AC, Attached Bathroom, WiFi'),
('102', 1, 'double',    2, 0, 2500.00, 'available', 'Fan, Shared Bathroom, WiFi'),
('201', 2, 'double',    2, 0, 2800.00, 'available', 'AC, Attached Bathroom, WiFi, Balcony'),
('202', 2, 'triple',    3, 0, 2000.00, 'available', 'Fan, Shared Bathroom'),
('301', 3, 'single',    1, 0, 3500.00, 'available', 'AC, Attached Bathroom, WiFi, City View'),
('302', 3, 'dormitory', 6, 0, 1200.00, 'available', 'Fan, Shared Bathroom, Locker');
