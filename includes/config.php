<?php
date_default_timezone_set('Asia/Barnaul');
define('BASE_DIR', __DIR__);
session_start();

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'reso_med');

// Настройки сайта
define('SITE_NAME', 'РЕСО-МЕД');
define('SITE_URL', 'http://reso-med:81');
?>