# CIRMS – Campus Cyber Incident Reporting & Management System

## Project Overview

CIRMS is a secure, web-based platform built with **PHP 8.x + MySQL + HTML/CSS/JavaScript**.  
It allows students, staff, and IT administrators to report, track, manage, and analyse cybersecurity incidents.

---

## Folder Structure

```
cirms/
│
├── config/
│   ├── config.php          ← App settings (DB, SMTP, SLA, upload limits)
│   └── database.php        ← PDO singleton – call db() anywhere
│
├── includes/
│   ├── functions.php       ← All shared helpers (auth, CSRF, audit, flash, escape)
│   ├── header.php          ← HTML <head>, navbar, flash messages
│   └── footer.php          ← Scripts, footer HTML
│
├── public/                 ← Web root (point Apache/Nginx here)
│   ├── login.php           ← Login page
│   ├── dashboard.php       ← Main dashboard (all roles)
│   │
│   ├── incidents/
│   │   ├── report.php      ← Submit new incident (any authenticated user)
│   │   ├── list.php        ← All incidents list with filters (officer/admin)
│   │   ├── view.php        ← Single incident detail + notes + status update
│   │   └── my-reports.php  ← Reporter's own incidents
│   │
│   ├── auth/
│   │   ├── register.php    ← Self-registration with institutional email
│   │   └── logout.php      ← Secure session destruction
│   │
│   ├── analytics/
│   │   ├── overview.php    ← Charts: trends, category, severity, status (admin)
│   │   ├── trends.php      ← Detailed trend report
│   │   └── export.php      ← CSV export of incident data
│   │
│   ├── users/
│   │   └── list.php        ← User management (admin)
│   │
│   ├── audit/
│   │   └── log.php         ← Immutable audit log viewer (admin)
│   │
│   ├── settings/
│   │   └── index.php       ← System settings editor (admin)
│   │
│   ├── css/
│   │   └── cirms.css       ← All custom styles
│   │
│   └── js/
│       └── cirms.js        ← Client-side validation + UI helpers
│
├── modules/
│   ├── notifications/
│   │   └── mailer.php      ← Email sending via PHPMailer or mail()
│   ├── incidents/
│   │   └── download.php    ← Secure file attachment download
│   ├── auth/               ← Auth business logic (future)
│   ├── analytics/          ← Analytics queries (future)
│   └── audit/              ← Audit helper functions (future)
│
├── database/
│   └── schema.sql          ← Complete MySQL schema + seed data
│
├── storage/
│   └── uploads/            ← Uploaded attachments (outside web root)
│
└── docs/
    └── README.md           ← This file
```

---

## Quick Start

### 1. Requirements
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache or Nginx web server
- Composer (optional, for PHPMailer)

### 2. Database Setup
```bash
mysql -u root -p < database/schema.sql
```

On **XAMPP**, start MySQL, open phpMyAdmin, create/import using `database/schema.sql`, then ensure `config/config.php` database settings match your MySQL user. Defaults use **`root`** with an **empty password** (typical XAMPP). For a dedicated DB user, run `database/grants_cirms_user.sql` (edit the password first) and copy `config/config.local.example.php` to `config/config.local.php` with that user and password.

### 3. Configuration
Edit `config/config.php` (or add `config/config.local.php` from the example for DB credentials only):
- Database: defaults are XAMPP-friendly; override `user` / `pass` via `config.local.php` if needed
- Set `$app_url_manual` to your full site URL in production (or leave empty for auto-detect under `/public/`)
- Configure SMTP settings for email notifications
- Set `APP_ENV` to `'production'` when live

### 4. Web Server – Apache

Point `DocumentRoot` to the `cirms/` root **not** to `public/`.  
Add this `.htaccess` in the `cirms/` root:

```apache
Options -Indexes
RewriteEngine On

# Block access to sensitive directories
RewriteRule ^(config|includes|modules|database|storage)/ - [F,L]

# Route everything to public/
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ public/$1 [L]
```

### 5. Install PHPMailer (Optional but recommended)
```bash
composer require phpmailer/phpmailer
```

### 6. Create Storage Directory
```bash
mkdir -p storage/uploads
chmod 750 storage/uploads
```

### 7. Create First Admin User
Run this SQL after setup:

```sql
INSERT INTO users (full_name, email, password, role)
VALUES (
    'IT Administrator',
    'admin@university.ac.tz',
    '$2y$12$REPLACE_WITH_BCRYPT_HASH',
    'admin'
);
```

Generate the bcrypt hash with PHP:
```php
echo password_hash('your_password', PASSWORD_BCRYPT, ['cost' => 12]);
```

---

## User Roles

| Role      | Can Do |
|-----------|--------|
| Reporter  | Submit incidents, view own reports, receive status notifications |
| Officer   | View all incidents, update status, add internal notes, assign incidents |
| Admin     | Everything + user management, analytics, audit log, system settings |

---

## Security Checklist (Pre-deployment)

- [ ] Change default SMTP password in `config.php`
- [ ] Set `APP_ENV` to `'production'`
- [ ] Enable HTTPS and set `APP_URL` to `https://...`
- [ ] Ensure `storage/uploads/` is NOT accessible via the web
- [ ] Run `chmod 750 storage/uploads`
- [ ] Test that `config/` and `includes/` return 403 from browser
- [ ] Set up MySQL user with minimal privileges (SELECT, INSERT, UPDATE on `cirms` only)
- [ ] Configure daily database backups

---

## Technology Stack

| Layer       | Technology |
|-------------|------------|
| Language    | PHP 8.x |
| Database    | MySQL 8.x with PDO prepared statements |
| Frontend    | HTML5 + CSS3 + Bootstrap 5.3 + Chart.js 4 |
| Icons       | Bootstrap Icons |
| Fonts       | Google Fonts (Space Mono + DM Sans) |
| Email       | PHPMailer + SMTP |
| Security    | bcrypt, CSRF tokens, CSP headers, session regeneration |

---

## Frameworks & Standards

- **NIST SP 800-61** – Incident response lifecycle (Prepare, Detect, Respond, Post-Incident)
- **ISO/IEC 27035** – Information security incident management
- **OWASP Top 10** – All inputs validated/escaped, PDO prepared statements, CSRF protection

---

*CIRMS v1.0.0 – Final Year Project by Elisha | Department of Computer Science & IT*
