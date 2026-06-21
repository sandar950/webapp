<?php
// core/auth_check.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login ဝင်ထားခြင်း မရှိပါက login.php သို့ ပြန်ပို့ရန်
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- Security & Session Management Enhancements ---

// 1. Inactivity Timeout (အသုံးမပြုဘဲ အချိန်ကြာမှ Logout လုပ်မည်)
require_once __DIR__ . '/db_connect.php';
$timeout_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'session_timeout_minutes'");
$session_timeout_minutes = ($timeout_stmt && $row = $timeout_stmt->fetch_assoc()) ? intval($row['setting_value']) : 30;
$session_timeout_seconds = $session_timeout_minutes * 60;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_timeout_seconds) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time(); // User ၏ နောက်ဆုံးလှုပ်ရှားချိန်ကို အမြဲ Update လုပ်မည်

// 2. Session Regeneration (Session Hijacking ကာကွယ်ရန်)
// Session စတင်ပြီး မိနစ် ၃၀ တိုင်းတွင် Session ID အသစ် ထုတ်ပေးမည်
if (!isset($_SESSION['session_regenerate_time'])) {
    $_SESSION['session_regenerate_time'] = time();
}

if (time() - $_SESSION['session_regenerate_time'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['session_regenerate_time'] = time();
}

// 3. အကောင့် ပိတ်ခံထားရခြင်း ရှိ/မရှိ စစ်ဆေးခြင်း
$check_ban_stmt = $conn->prepare("SELECT is_banned FROM users WHERE id = ?");
$check_ban_stmt->bind_param("i", $_SESSION['user_id']);
$check_ban_stmt->execute();
if ($check_ban_stmt->get_result()->fetch_assoc()['is_banned'] ?? false) {
    session_destroy();
    header("Location: login.php?banned=1");
    exit();
}
$check_ban_stmt->close();
?>