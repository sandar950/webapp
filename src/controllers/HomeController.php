<?php
/**
 * Controller: Handles the main user dashboard logic, balance, daily bonus, etc.
 * @version 1.7.3 (Refactored to MVC)
 */

// User Login ဝင်ထားခြင်း မရှိပါက login.php သို့ ပြန်ပို့ရန်
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; 

// CSRF Token တည်ဆောက်ခြင်း
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// User ၏ နောက်ဆုံးအသုံးပြုချိန် (last_active) ကို ၁ မိနစ်လျှင် တစ်ခါသာ Update လုပ်မည်
$update_active_stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ? AND (last_active IS NULL OR last_active < NOW() - INTERVAL 1 MINUTE)");
$update_active_stmt->bind_param("i", $user_id);
$update_active_stmt->execute();
$update_active_stmt->close();

// Online Users ရေတွက်ခြင်း
$online_count = 1;
$online_query = $conn->query("SELECT COUNT(id) as online_count FROM users WHERE last_active >= NOW() - INTERVAL 5 MINUTE");
if ($online_query) {
    $online_count = $online_query->fetch_assoc()['online_count'] ?? 1;
}

// User Data ရယူသည့် Function
function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT username, phone_number, balance, notifications, is_banned, last_bonus_date, avatar, vip_level FROM users WHERE id = ?");
    if (!$stmt) {
        error_log("Get User Data Prepare failed: " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

// Daily Bonus လုပ်ဆောင်သည့် Function
function processDailyBonus($conn, &$user_row, $user_id) {
    $today = date('Y-m-d');
    if (($user_row['last_bonus_date'] ?? null) === $today) {
        return "";
    }

    $vip_level = strtolower($user_row['vip_level'] ?? 'standard');
    $setting_key = 'daily_bonus_' . $vip_level;
    
    $bonus_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $bonus_stmt->bind_param("s", $setting_key);
    $bonus_stmt->execute();
    $bonus_res = $bonus_stmt->get_result();
    $bonus_amt = ($bonus_row = $bonus_res->fetch_assoc()) ? floatval($bonus_row['setting_value']) : 0;
    $bonus_stmt->close();

    if ($bonus_amt > 0) {
        $conn->begin_transaction();
        
        $update_stmt = $conn->prepare("UPDATE users SET balance = balance + ?, last_bonus_date = ? WHERE id = ? AND (last_bonus_date IS NULL OR last_bonus_date != ?)");
        $update_stmt->bind_param("dsis", $bonus_amt, $today, $user_id, $today);
        $update_stmt->execute();
        $affected_rows = $update_stmt->affected_rows; 
        $update_stmt->close();
        
        if ($affected_rows > 0) {
            $noti_msg = sprintf(__('daily_bonus_received_noti'), number_format($bonus_amt));
            $noti_stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
            $noti_stmt->bind_param("is", $user_id, $noti_msg);
            $noti_stmt->execute();
            $noti_stmt->close();
            
            $update_noti_stmt = $conn->prepare("UPDATE users SET notifications = notifications + 1 WHERE id = ?");
            $update_noti_stmt->bind_param("i", $user_id);
            $update_noti_stmt->execute();
            $update_noti_stmt->close();
            
            $conn->commit();

            $user_row['balance'] += $bonus_amt;
            $user_row['notifications'] += 1;
            return sprintf(__('daily_bonus_received_alert'), number_format($bonus_amt));
        } else {
            $conn->rollback();
        }
    } else {
        $update_date_stmt = $conn->prepare("UPDATE users SET last_bonus_date = ? WHERE id = ?");
        $update_date_stmt->bind_param("si", $today, $user_id);
        $update_date_stmt->execute();
        $update_date_stmt->close();
    }
    
    return "";
}

// User Data အား ပြင်ဆင်ခြင်း
$user = [
    'username' => 'Unknown User',
    'phone' => 'No Phone',
    'balance' => '0.00',
    'noti_count' => 0,
    'avatar' => ''
];
$bonus_alert = "";

$row = getUserData($conn, $user_id);

if ($row) {
    if ($row['is_banned']) {
        session_destroy();
        header("Location: login.php");
        exit();
    }

    $bonus_alert = processDailyBonus($conn, $row, $user_id);
    
    $user['username'] = $row['username'];
    $user['phone'] = $row['phone_number'];
    $user['balance'] = number_format($row['balance'], 2);
    $user['noti_count'] = $row['notifications'];
    $user['avatar'] = $row['avatar'];
} else {
    error_log("Could not retrieve user data for user_id: $user_id on index.php");
    session_destroy();
    header("Location: login.php?error=session_invalid");
    exit();
}

// Banner များ ရယူခြင်း
$banner_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('home_banner_url', 'home_banner_url_2', 'home_banner_url_3')");
$valid_banners = [];
if ($banner_stmt) {
    while ($b_row = $banner_stmt->fetch_assoc()) {
        if (!empty(trim($b_row['setting_value']))) {
            $valid_banners[] = trim($b_row['setting_value']);
        }
    }
}

// အသိပေးချက် (Announcement) ရယူခြင်း
$announce_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('announcement_text', 'announcement_image_url', 'announcement_is_active')");
$announcement = [];
if ($announce_stmt) {
    while ($a_row = $announce_stmt->fetch_assoc()) {
        $announcement[$a_row['setting_key']] = $a_row['setting_value'];
    }
}

// အရေးကြီး Notification ရယူခြင်း
$imp_noti_stmt = $conn->prepare("SELECT id, message FROM system_notifications WHERE (user_id IS NULL OR user_id = ?) AND is_important = 1 AND is_read = 0 ORDER BY created_at DESC LIMIT 1");
$imp_noti_stmt->bind_param("i", $user_id);
$imp_noti_stmt->execute();
$imp_noti_res = $imp_noti_stmt->get_result()->fetch_assoc();
$imp_noti_id = $imp_noti_res['id'] ?? 0;
$imp_noti_raw_message = $imp_noti_res['message'] ?? "";
$imp_noti_message = "";
$imp_noti_message_clean = "";
$imp_noti_html = "";

if (!empty($imp_noti_raw_message)) {
    $imp_noti_message = str_replace('{username}', $user['username'], $imp_noti_raw_message);
    $imp_noti_message_clean = strip_tags(str_replace(["\r", "\n"], ' ', preg_replace('!(https?://[a-z0-9_./?=&-]+)!i', '', $imp_noti_message)));
    $safe_message = htmlspecialchars($imp_noti_message, ENT_QUOTES, 'UTF-8');
    $url_pattern = '/(https?:\/\/[^\s<>"\'`]+)/i';
    $msg_with_links = preg_replace($url_pattern, '<a href="$1" target="_blank" class="text-blue-500 underline font-bold" rel="noopener noreferrer">$1</a>', $safe_message);
    $imp_noti_html = nl2br($msg_with_links);
}
$imp_noti_stmt->close();

?>
