<?php
/**
 * This script should be run via Cron Job every 5 or 10 minutes.
 */
require_once __DIR__ . '/../core/db_connect.php';

// 1. Get Telegram Settings from database
$settings_res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('telegram_bot_token', 'telegram_channel_id')");
$settings = [];
while ($row = $settings_res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$bot_token = $settings['telegram_bot_token'] ?? '';
$chat_id = $settings['telegram_channel_id'] ?? '';

if (empty($bot_token) || empty($chat_id)) {
    exit("Telegram config not set.");
}

// 2. Find active 3D sessions closing in the next 30 minutes that haven't notified admin yet
$now = date('Y-m-d H:i:s');
$warning_threshold = date('Y-m-d H:i:s', strtotime('+30 minutes'));

$query = "SELECT * FROM betting_sessions WHERE game_type = '3d' AND status = 'active' AND admin_notified = 0 AND close_time <= ? AND close_time > ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $warning_threshold, $now);
$stmt->execute();
$sessions = $stmt->get_result();

while ($s = $sessions->fetch_assoc()) {
    $msg = "⚠️ *3D Session Closing Alert*\n\n";
    $msg .= "ပွဲစဉ်: " . strtoupper($s['game_type']) . " (" . ucfirst($s['section']) . ")\n";
    $msg .= "ထွက်မည့်ရက်: " . $s['target_date'] . "\n";
    $msg .= "ပိတ်မည့်အချိန်: " . date('h:i A', strtotime($s['close_time'])) . "\n\n";
    $msg .= "သတိပေးချက်: နောက်ထပ် မိနစ် ၃၀ အတွင်း ပွဲစဉ်ပိတ်ပါတော့မည်။";

    // Send to Telegram
    $url = "https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($msg) . "&parse_mode=Markdown";
    @file_get_contents($url);

    // 3. Mark as notified to avoid duplicate alerts
    $update_stmt = $conn->prepare("UPDATE betting_sessions SET admin_notified = 1 WHERE id = ?");
    $update_stmt->bind_param("i", $s['id']);
    $update_stmt->execute();
    $update_stmt->close();
}

$stmt->close();
$conn->close();