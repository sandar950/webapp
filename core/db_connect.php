<?php
// Database အချက်အလက်များ
require_once __DIR__ . '/config.php';
$servername = DB_HOST;
$username_db = DB_USER;
$password_db = DB_PASS;
$dbname = DB_NAME;

// PHP ၏ အချိန်ကို မြန်မာစံတော်ချိန် (Yangon) သတ်မှတ်ရန်
date_default_timezone_set('Asia/Yangon');

// --- Global Error Logging System ---
// သာမန် User များအား Error မပြဘဲ errorlog.txt ထဲတွင်သာ မှတ်သားထားမည်
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/errorlog.txt');

// Uncaught Exceptions များကို ဖမ်းယူမှတ်သားရန်
set_exception_handler(function($e) {
    $log_msg = "[" . date("Y-m-d H:i:s") . "] Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    error_log($log_msg, 3, dirname(__DIR__) . '/logs/errorlog.txt');
});

// Errors များကို ဖမ်းယူမှတ်သားရန်
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    $log_msg = "[" . date("Y-m-d H:i:s") . "] Error: $errstr in $errfile on line $errline\n";
    error_log($log_msg, 3, dirname(__DIR__) . '/logs/errorlog.txt');
    return true;
});
// -----------------------------------

// Database သို့ ချိတ်ဆက်ခြင်း
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// ချိတ်ဆက်မှု အဆင်မပြေပါက ရပ်တန့်ရန်
if ($conn->connect_error) {
    // Log the error to a file for debugging
    $error_log_message = "[" . date("Y-m-d H:i:s") . "] Database Connection Failed: " . $conn->connect_error . "\n";
    // The 3rd parameter '3' means append to the specified file.
    error_log($error_log_message, 3, dirname(__DIR__) . '/logs/errorlog.txt');

    // Show a generic error message to the user and stop the script
    $db_error_msg = function_exists('__') ? __('db_connection_error') : "စနစ်တွင် ယာယီအမှားအယွင်း ဖြစ်ပေါ်နေပါသည်။ ခေတ္တစောင့်ဆိုင်းပြီး ထပ်မံကြိုးစားပါ။";
    die($db_error_msg);
}

// မြန်မာစာ (Unicode) အမှားအယွင်းမရှိစေရန် သတ်မှတ်ခြင်း
$conn->set_charset("utf8mb4");

// Database ၏ အချိန်ကိုပါ မြန်မာစံတော်ချိန် (+06:30) အဖြစ် သတ်မှတ်ရန်
$conn->query("SET time_zone = '+06:30'");

// Maintenance Mode စစ်ဆေးခြင်း
$current_script = basename($_SERVER['PHP_SELF']);
$allowed_scripts = ['maintenance.php', 'login.php', 'logout.php']; // ဤဖိုင်များတွင် Maintenance Mode အလုပ်မလုပ်ပါ

if (!in_array($current_script, $allowed_scripts)) {
    $m_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
    if ($m_stmt && $m_stmt->num_rows > 0) {
        $m_row = $m_stmt->fetch_assoc();
        if ($m_row['setting_value'] === '1') {
            // Session ဖွင့်ထားခြင်း မရှိသေးပါက ဖွင့်မည် (Role စစ်ဆေးရန်)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Admin / Sub-Admin မဟုတ်ပါက (သို့မဟုတ်) Login မဝင်ထားသော User များဆိုလျှင် Maintenance Page သို့ ပို့မည်
            $is_admin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'sub_admin']);
            
            if (!$is_admin) {
                header("Location: maintenance.php");
                exit();
            }
        }
    }
}
?>