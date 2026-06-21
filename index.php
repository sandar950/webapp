<?php
session_start();
require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/lang/language.php';
require_once __DIR__ . '/src/controllers/HomeController.php';
$page_title = __('home_page_title');
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/src/views/home_view.php';
require_once __DIR__ . '/includes/footer.php';
?>
