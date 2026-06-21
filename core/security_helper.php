<?php
// security_helper.php

// Required DB connection to be available globally or passed in
require_once __DIR__ . '/db_connect.php';

/**
 * Checks if the user's IP is rate-limited for login attempts.
 * Limit is set to 5 attempts per 15 minutes.
 */
function check_login_rate_limit($ip_address, $phone_number) {
    global $conn;
    $time_limit = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    $stmt = $conn->prepare("SELECT attempts FROM login_attempts WHERE ip_address = ? AND phone_number = ? AND last_attempt > ?");
    $stmt->bind_param("sss", $ip_address, $phone_number, $time_limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['attempts'] >= 5) {
            return false; // Rate limited
        }
    }
    return true; // OK to proceed
}

/**
 * Records a failed login attempt.
 */
function record_failed_login($ip_address, $phone_number) {
    global $conn;
    $current_time = date('Y-m-d H:i:s');
    
    // Check if a record exists
    $stmt = $conn->prepare("SELECT id, attempts FROM login_attempts WHERE ip_address = ? AND phone_number = ?");
    $stmt->bind_param("ss", $ip_address, $phone_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Update existing
        $new_attempts = $row['attempts'] + 1;
        $update_stmt = $conn->prepare("UPDATE login_attempts SET attempts = ?, last_attempt = ? WHERE id = ?");
        $update_stmt->bind_param("isi", $new_attempts, $current_time, $row['id']);
        $update_stmt->execute();
    } else {
        // Insert new
        $insert_stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, phone_number, attempts, last_attempt) VALUES (?, ?, 1, ?)");
        $insert_stmt->bind_param("sss", $ip_address, $phone_number, $current_time);
        $insert_stmt->execute();
    }
}

/**
 * Checks if the user's PIN is rate-limited.
 * Limit is set to 5 attempts per 15 minutes.
 */
function check_pin_rate_limit($user_id) {
    global $conn;
    $time_limit = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    $stmt = $conn->prepare("SELECT attempts FROM pin_attempts WHERE user_id = ? AND last_attempt > ?");
    $stmt->bind_param("is", $user_id, $time_limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['attempts'] >= 5) return false; // Locked
    }
    return true; // OK
}

/**
 * Records a failed PIN attempt for a user.
 */
function record_failed_pin($user_id) {
    global $conn;
    $current_time = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("INSERT INTO pin_attempts (user_id, attempts, last_attempt) VALUES (?, 1, ?) 
                            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = ?");
    $stmt->bind_param("isss", $user_id, $current_time, $current_time);
    $stmt->execute();
    $stmt->close();
}

/**
 * Clears failed PIN attempts after a successful verification.
 */
function clear_failed_pins($user_id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM pin_attempts WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Clears failed login attempts after a successful login.
 */
function clear_failed_logins($ip_address, $phone_number) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND phone_number = ?");
    $stmt->bind_param("ss", $ip_address, $phone_number);
    $stmt->execute();
}

/**
 * Sends an urgent security alert via Telegram.
 */
function send_security_alert_to_telegram($message) {
    global $conn;
    
    $bot_token = '';
    $chat_id = '';
    
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('telegram_bot_token', 'telegram_alert_chat_id')");
    while ($row = $stmt->fetch_assoc()) {
        if ($row['setting_key'] === 'telegram_bot_token') $bot_token = trim($row['setting_value']);
        if ($row['setting_key'] === 'telegram_alert_chat_id') $chat_id = trim($row['setting_value']);
    }
    
    // Fallback to standard channel if alert chat ID is not set
    if (empty($chat_id)) {
        $stmt_channel = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_channel_id'");
        if ($row = $stmt_channel->fetch_assoc()) {
            $chat_id = trim($row['setting_value']);
        }
    }

    if (empty($bot_token) || empty($chat_id)) {
        return false;
    }

    $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => "🚨 *SECURITY ALERT*\n\n" . $message,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}
?>