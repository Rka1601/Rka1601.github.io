<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    header("Location: " . SITE_URL . "/admin/dashboard.php");
    exit();
} else {
    header("Location: " . SITE_URL . "/login.php");
    exit();
}
?>