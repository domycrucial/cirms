<?php
// ============================================================
// CIRMS – Root redirect
// public/index.php
// ============================================================

require_once __DIR__ . '/../includes/functions.php';
session_start_secure();

if (is_logged_in()) {
    redirect('/public/dashboard.php');
} else {
    redirect('/public/login.php');
}
