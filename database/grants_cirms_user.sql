-- ============================================================
-- Optional: dedicated MySQL user for CIRMS (production-style)
-- Run as MySQL root AFTER importing schema.sql
-- ============================================================

CREATE USER IF NOT EXISTS 'cirms_user'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';
GRANT ALL PRIVILEGES ON cirms.* TO 'cirms_user'@'localhost';
FLUSH PRIVILEGES;

-- Then in config/config.local.php return:
--   ['user' => 'cirms_user', 'pass' => 'CHANGE_THIS_PASSWORD']
