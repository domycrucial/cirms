-- ============================================================
-- IRS - Institute of Accountancy Arusha Reporting System
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS schema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE schema;

-- ============================================================
-- USERS TABLE
-- Stores all system users: reporters, IT officers, admins
-- ============================================================
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150) NOT NULL,
    email       VARCHAR(200) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,          -- bcrypt hashed
    role        ENUM('reporter','officer','admin') NOT NULL DEFAULT 'reporter',
    department  VARCHAR(150),
    phone       VARCHAR(20),
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- INCIDENT CATEGORIES TABLE
-- Admin-configurable list of incident types
-- ============================================================
CREATE TABLE categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Seed IAA student ICT incident categories
INSERT INTO categories (name, description) VALUES
('Account and Authentication Issues',      'Login problems, password resets, account lockouts, 2FA issues, and session problems for ISMS and eLearning portals'),
('Course Access Problems',                 'Missing enrolled courses, permission denied errors, and course assignment issues on the eLearning platform (Moodle)'),
('Assignment and Submission Issues',       'File upload failures, submission visibility problems, file format/size rejections, and duplicate submissions on eLearning'),
('Online Quiz and Examination Issues',     'Quiz loading failures, unexpected auto-submissions, timer malfunctions, browser crashes, and network interruptions during online assessments'),
('Registration and Academic Record Issues','Semester/course registration errors, missing grades or results, GPA/CGPA calculation errors, transcript problems, and timetable issues in ISMS'),
('Fee Payment and Financial Issues',       'Unprocessed payments, invoice/receipt generation failures, financial clearance problems, and incorrect fee balances in ISMS'),
('System Performance and Availability',   'ISMS or eLearning portal slow loading, inaccessible systems, frequent downtime, server timeout errors, and Error 500 issues'),
('Mobile and Device Compatibility',        'Portal not working on smartphones, mobile upload failures, browser compatibility issues, and broken layouts on mobile devices'),
('Email and Notification Issues',          'Missing system notifications, absent assignment alerts, password reset emails not received, and delayed communication messages'),
('User Profile and Data Issues',           'Incorrect student profile information, inability to update profile, missing passport/photo upload, and wrong programme or semester details'),
('Connectivity and Access Issues',         'VPN or campus network access problems, slow internet affecting uploads, Wi-Fi authentication failure, and access denied from external network');

-- ============================================================
-- INCIDENTS TABLE
-- Core table: every reported cybersecurity incident
-- ============================================================
CREATE TABLE incidents (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(20) NOT NULL UNIQUE,    -- e.g. INC-2025-0042
    reporter_id     INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED NOT NULL,
    severity        ENUM('Low','Medium','High','Critical') NOT NULL,
    status          ENUM('New','Acknowledged','In Progress','Resolved','Closed') NOT NULL DEFAULT 'New',
    title           VARCHAR(255) NOT NULL,
    description     TEXT NOT NULL,
    affected_system VARCHAR(200),
    is_ongoing      TINYINT(1) NOT NULL DEFAULT 0,
    incident_time   DATETIME NOT NULL,              -- when incident occurred
    assigned_to     INT UNSIGNED,                   -- officer assigned
    sla_deadline    DATETIME,                       -- calculated from severity
    resolved_at     DATETIME,
    closed_at       DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (reporter_id)  REFERENCES users(id),
    FOREIGN KEY (category_id)  REFERENCES categories(id),
    FOREIGN KEY (assigned_to)  REFERENCES users(id)
);

-- ============================================================
-- INCIDENT ATTACHMENTS TABLE
-- Files uploaded alongside an incident report
-- ============================================================
CREATE TABLE attachments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    incident_id INT UNSIGNED NOT NULL,
    filename    VARCHAR(255) NOT NULL,              -- stored filename (renamed)
    original    VARCHAR(255) NOT NULL,              -- original upload name
    mime_type   VARCHAR(100) NOT NULL,
    size_bytes  INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (incident_id)  REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by)  REFERENCES users(id)
);

-- ============================================================
-- INCIDENT NOTES TABLE
-- Internal communication between IT staff (not visible to reporters)
-- Also used for status-update comments visible to reporter
-- ============================================================
CREATE TABLE notes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    incident_id INT UNSIGNED NOT NULL,
    author_id   INT UNSIGNED NOT NULL,
    body        TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 1,      -- 1 = IT-only, 0 = visible to reporter
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id)   REFERENCES users(id)
);

-- ============================================================
-- AUDIT LOG TABLE
-- Immutable record of all significant system actions
-- ============================================================
CREATE TABLE audit_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,                       -- NULL = system action
    action      VARCHAR(100) NOT NULL,              -- e.g. 'incident.status_changed'
    target_type VARCHAR(50),                        -- e.g. 'incident'
    target_id   INT UNSIGNED,
    details     JSON,                               -- before/after values, extra context
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(255),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- NOTIFICATIONS TABLE
-- Tracks email notifications sent by the system
-- ============================================================
CREATE TABLE notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    incident_id INT UNSIGNED,
    type        VARCHAR(80) NOT NULL,               -- e.g. 'incident.submitted', 'status.changed'
    subject     VARCHAR(255) NOT NULL,
    sent_at     DATETIME,
    status      ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    error_msg   TEXT,

    FOREIGN KEY (user_id)     REFERENCES users(id),
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL
);

-- ============================================================
-- SETTINGS TABLE
-- Key-value store for system configuration
-- ============================================================
CREATE TABLE settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    description   VARCHAR(255),
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value, description) VALUES
('sla_low_hours',      '72',                  'SLA hours for Low severity incidents'),
('sla_medium_hours',   '24',                  'SLA hours for Medium severity incidents'),
('sla_high_hours',     '8',                   'SLA hours for High severity incidents'),
('sla_critical_hours', '2',                   'SLA hours for Critical severity incidents'),
('smtp_host',          'smtp.university.ac',  'SMTP server hostname'),
('smtp_port',          '587',                 'SMTP server port'),
('smtp_user',          '',                    'SMTP username'),
('smtp_pass',          '',                    'SMTP password (encrypted)'),
('notify_email',       'itsec@university.ac', 'Primary IT security notification email'),
('max_upload_mb',      '10',                  'Maximum file upload size in MB'),
('session_timeout',    '1800',               'Session timeout in seconds (30 min)');

-- ============================================================
-- INDEXES for performance
-- ============================================================
CREATE INDEX idx_incidents_status     ON incidents(status);
CREATE INDEX idx_incidents_severity   ON incidents(severity);
CREATE INDEX idx_incidents_reporter   ON incidents(reporter_id);
CREATE INDEX idx_incidents_assigned   ON incidents(assigned_to);
CREATE INDEX idx_incidents_created    ON incidents(created_at);
CREATE INDEX idx_audit_user           ON audit_log(user_id);
CREATE INDEX idx_audit_created        ON audit_log(created_at);
CREATE INDEX idx_notes_incident       ON notes(incident_id);
