-- ============================================================
-- IRS Migration: Replace CIRMS categories with IAA student ICT categories
-- Run this once on the existing database to update categories.
-- ============================================================

-- Remove old cybersecurity categories
TRUNCATE TABLE categories;

-- Insert IAA student ICT incident categories
INSERT INTO categories (name, description, is_active) VALUES
('Account and Authentication Issues',      'Login problems, password resets, account lockouts, 2FA issues, and session problems for ISMS and eLearning portals', 1),
('Course Access Problems',                 'Missing enrolled courses, permission denied errors, and course assignment issues on the eLearning platform (Moodle)', 1),
('Assignment and Submission Issues',       'File upload failures, submission visibility problems, file format/size rejections, and duplicate submissions on eLearning', 1),
('Online Quiz and Examination Issues',     'Quiz loading failures, unexpected auto-submissions, timer malfunctions, browser crashes, and network interruptions during online assessments', 1),
('Registration and Academic Record Issues','Semester/course registration errors, missing grades or results, GPA/CGPA calculation errors, transcript problems, and timetable issues in ISMS', 1),
('Fee Payment and Financial Issues',       'Unprocessed payments, invoice/receipt generation failures, financial clearance problems, and incorrect fee balances in ISMS', 1),
('System Performance and Availability',   'ISMS or eLearning portal slow loading, inaccessible systems, frequent downtime, server timeout errors, and Error 500 issues', 1),
('Mobile and Device Compatibility',        'Portal not working on smartphones, mobile upload failures, browser compatibility issues, and broken layouts on mobile devices', 1),
('Email and Notification Issues',          'Missing system notifications, absent assignment alerts, password reset emails not received, and delayed communication messages', 1),
('User Profile and Data Issues',           'Incorrect student profile information, inability to update profile, missing passport/photo upload, and wrong programme or semester details', 1),
('Connectivity and Access Issues',         'VPN or campus network access problems, slow internet affecting uploads, Wi-Fi authentication failure, and access denied from external network', 1);
