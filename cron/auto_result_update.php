<?php
/**
 * ဤ Script သည် Cron Job ဖြင့် နောက်ကွယ်မှ အလိုအလျောက် အလုပ်လုပ်ရန် ရည်ရွယ်ပါသည်။
 * ဥပမာ - နေ့စဉ် မနက် 12:01 PM နှင့် ညနေ 4:31 PM များတွင် Run ရန် သတ်မှတ်နိုင်သည်။
 */

require_once __DIR__ . '/../core/db_connect.php';

// Database မှ API URLs များကို ယူမည်
$settings_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('live_2d_api_url', 'live_3d_api_url')");
$api_urls = [];
while ($row = $settings_stmt->fetch_assoc()) {
    $api_urls[$row['setting_key']] = $row['setting_value'];
}

$api_url_2d = $api_urls['live_2d_api_url'] ?? '';
$api_url_3d = $api_urls['live_3d_api_url'] ?? '';

$output_log = "Auto Result Update Started at " . date('Y-m-d H:i:s') . "\n";
$target_date = date('Y-m-d');
$current_time = date('H:i');
$section_2d = ($current_time < '14:00') ? 'morning' : 'evening';

// --- 2D Result Fetching ---
if (!empty($api_url_2d)) {
    $ch = curl_init($api_url_2d);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response_2d = curl_exec($ch);
    $http_code_2d = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code_2d === 200 && $response_2d) {
        $data_2d = json_decode($response_2d, true);
        $winning_2d = null;
        
        // ဘုံအသုံးများသော API Format များကို စစ်ဆေးခြင်း
        if (isset($data_2d['live']['twod'])) {
            $winning_2d = $data_2d['live']['twod'];
        } elseif (isset($data_2d['2d_result'])) {
            $winning_2d = $data_2d['2d_result'];
        } elseif (isset($data_2d['result'][0]['twod'])) {
            $winning_2d = $data_2d['result'][0]['twod'];
        }

        if (!empty($winning_2d) && preg_match('/^[0-9]{2}$/', $winning_2d)) {
            $output_log .= "2D Result Found: {$winning_2d}\n";
            $output_log .= process_winning_bets($conn, $winning_2d, 2, 80, $section_2d, $target_date);
            send_telegram_result($conn, '2D', $winning_2d, 80);
        } else {
            $output_log .= "2D API Success, but no valid 2D result found in response.\n";
        }
    } else {
        $output_log .= "2D API ချိတ်ဆက်မှု အဆင်မပြေပါ။ HTTP Code: {$http_code_2d}\n";
    }
} else {
    $output_log .= "2D API URL မသတ်မှတ်ထားပါ။\n";
}

// --- 3D Result Fetching ---
if (!empty($api_url_3d)) {
    $ch = curl_init($api_url_3d);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response_3d = curl_exec($ch);
    $http_code_3d = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code_3d === 200 && $response_3d) {
        $data_3d = json_decode($response_3d, true);
        $winning_3d = null;

        if (isset($data_3d['3d_result'])) {
            $winning_3d = $data_3d['3d_result'];
        } elseif (isset($data_3d['live']['threed'])) {
            $winning_3d = $data_3d['live']['threed'];
        }

        if (!empty($winning_3d) && preg_match('/^[0-9]{3}$/', $winning_3d)) {
            $output_log .= "3D Result Found: {$winning_3d}\n";
            $output_log .= process_winning_bets($conn, $winning_3d, 3, 500, '3d', $target_date);
            send_telegram_result($conn, '3D', $winning_3d, 500);
        } else {
            $output_log .= "3D API Success, but no valid 3D result found in response.\n";
        }
    } else {
        $output_log .= "3D API ချိတ်ဆက်မှု အဆင်မပြေပါ။ HTTP Code: {$http_code_3d}\n";
    }
} else {
    $output_log .= "3D API URL မသတ်မှတ်ထားပါ။\n";
}

echo nl2br(htmlspecialchars($output_log));

/**
 * ဂဏန်းအဖြေကို History တွင်မှတ်၍ Telegram သို့ Auto Post တင်မည့် Function
 */
function send_telegram_result($conn, $type, $number, $multiplier) {
    // အဖြေထပ်ခါထပ်ခါ မပို့မိစေရန် ဒီနေ့အတွက် အဆိုပါအဖြေ ကြေညာပြီးပြီလား စစ်ဆေးမည်
    $check_stmt = $conn->prepare("SELECT id FROM result_history WHERE type = ? AND result_number = ? AND DATE(created_at) = CURDATE()");
    $check_stmt->bind_param("ss", $type, $number);
    $check_stmt->execute();
    $res = $check_stmt->get_result();
    $check_stmt->close();

    if ($res->num_rows == 0) {
        $result_stmt = $conn->prepare("INSERT INTO result_history (result_number, type) VALUES (?, ?)");
        $result_stmt->bind_param("ss", $number, $type);
        $result_stmt->execute();
        $result_stmt->close();

        $tg_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('telegram_bot_token', 'telegram_channel_id')");
        $tg_settings = [];
        while ($tg_row = $tg_stmt->fetch_assoc()) {
            $tg_settings[$tg_row['setting_key']] = $tg_row['setting_value'];
        }
        $bot_token = $tg_settings['telegram_bot_token'] ?? '';
        $channel_id = $tg_settings['telegram_channel_id'] ?? '';

        if (!empty($bot_token) && !empty($channel_id)) {
            $telegram_msg = "📢 *{$type} အဖြေထွက်ပါပြီ*\n\n🎯 ပေါက်ဂဏန်း: *{$number}*\n💸 အဆ (Multiplier): *{$multiplier} ဆ*\n\n🎉 အနိုင်ရသူများအားလုံး ဂုဏ်ယူပါသည်။";
            $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
            $telegram_data = ['chat_id' => $channel_id, 'text' => $telegram_msg, 'parse_mode' => 'Markdown'];

            $ch = curl_init($telegram_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($telegram_data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

/**
 * ဂဏန်းအဖြေတိုက်စစ်ပြီး လျော်ကြေးရှင်းပေးမည့် Function
 */
function process_winning_bets($conn, $winning_number, $num_length, $multiplier, $section, $target_date) {
    $log = "";
    $conn->begin_transaction();
    try {
        // Pending ဖြစ်နေသော သက်ဆိုင်ရာ မှတ်တမ်းအားလုံးကို ယူမည်
        $stmt = $conn->prepare("SELECT id, user_id, bet_number, amount FROM bets WHERE status = 'pending' AND LENGTH(bet_number) = ? AND bet_section = ? AND target_date = ? FOR UPDATE");
        $stmt->bind_param("iss", $num_length, $section, $target_date);
        $stmt->execute();
        $pending_bets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (count($pending_bets) > 0) {
            $win_stmt = $conn->prepare("UPDATE bets SET status = 'win' WHERE id = ?");
            $lose_stmt = $conn->prepare("UPDATE bets SET status = 'lose' WHERE id = ?");
            $reward_stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $noti_stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
            $update_noti_stmt = $conn->prepare("UPDATE users SET notifications = notifications + 1 WHERE id = ?");

            foreach ($pending_bets as $bet) {
                if ($bet['bet_number'] === $winning_number) {
                    // ပေါက်ပါက
                    $win_stmt->bind_param("i", $bet['id']);
                    $win_stmt->execute();

                    $reward = $bet['amount'] * $multiplier;
                    $reward_stmt->bind_param("di", $reward, $bet['user_id']);
                    $reward_stmt->execute();

                    // ပေါက်သူထံသို့ Notification ပို့မည်
                    $noti_msg = "🎉 ဂုဏ်ယူပါသည်။ သင်ထိုးထားသော [{$bet['bet_number']}] သည် ပေါက်ဂဏန်းဖြစ်သဖြင့် လျော်ကြေးငွေ " . number_format($reward) . " Ks ရရှိပါသည်။";
                    $noti_stmt->bind_param("is", $bet['user_id'], $noti_msg);
                    $noti_stmt->execute();
                    $update_noti_stmt->bind_param("i", $bet['user_id']);
                    $update_noti_stmt->execute();
                } else {
                    // မပေါက်ပါက
                    $lose_stmt->bind_param("i", $bet['id']);
                    $lose_stmt->execute();
                }
            }
            
            $conn->commit();

            // Close the session in betting_sessions
            $close_session_stmt = $conn->prepare("UPDATE betting_sessions SET status = 'closed' WHERE game_type = ? AND section = ? AND target_date = ?");
            $game_type = $num_length == 2 ? '2d' : '3d';
            $close_session_stmt->bind_param("sss", $game_type, $section, $target_date);
            $close_session_stmt->execute();
            $close_session_stmt->close();

            $log = "{$num_length}D အဖြေ [{$winning_number}] အတွက် ထိုးကြေးမှတ်တမ်းပေါင်း " . count($pending_bets) . " ခုအား အလိုအလျောက် ရှင်းလင်းပေးပြီးပါပြီ။\n";
        } else {
            $conn->rollback();
            $log = "{$num_length}D အတွက် စစ်ဆေးရန် Pending ထိုးကြေး မရှိပါ။\n";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $log = "{$num_length}D တွက်ချက်ရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $e->getMessage() . "\n";
    }
    return $log;
}
?>