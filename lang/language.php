<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ၁။ User မှ Manual ဘာသာစကား ပြောင်းလဲခြင်းကို လက်ခံရန် (?lang=en သို့မဟုတ် ?lang=mm ဖြင့်ခေါ်လျှင်)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'mm'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// ၂။ Auto Language Detection (ပထမဆုံးအကြိမ် ဝင်လာချိန်တွင်သာ စစ်ဆေးမည်)
if (!isset($_SESSION['lang'])) {
    // Browser ၏ အဓိက Language ကို စစ်ဆေးခြင်း
    $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2);
    
    // မြန်မာဘာသာ ('my') ဖြစ်ပါက 'mm' ကို ရွေးပေးမည်၊ မဟုတ်ပါက 'en' ကို ရွေးပေးမည်
    if ($browser_lang === 'my') {
        $_SESSION['lang'] = 'mm';
    } else {
        $_SESSION['lang'] = 'en'; // အခြားနိုင်ငံများအတွက် အင်္ဂလိပ်စာ Default ထားမည်
    }
}

// ၃။ သတ်မှတ်ထားသော ဘာသာစကားဖိုင်ကို ဆွဲတင်ခြင်း
$current_lang = $_SESSION['lang'];
$lang_file = __DIR__ . "/{$current_lang}.php";

if (file_exists($lang_file)) {
    $translations = include($lang_file);
} elseif (file_exists(__DIR__ . "/mm.php")) {
    $translations = include(__DIR__ . "/mm.php"); // ဖိုင်မရှိလျှင် မြန်မာစာကို Default ထားမည်
} else {
    $translations = []; // Language file မရှိပါက Error မတက်စေရန်
}

// ၄။ ဘာသာပြန်ပေးမည့် Helper Function (အခြားဖိုင်များတွင် ခေါ်သုံးရန်)
function __($key) {
    global $translations;
    return $translations[$key] ?? $key; // စာသားမရှိလျှင် Key အမည်ကိုသာ ပြန်ပြပေးမည်
}