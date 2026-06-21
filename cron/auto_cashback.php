<?php
/**
 * ဤ Script သည် Cron Job ဖြင့် အပတ်စဉ် တနင်္ဂနွေနေ့ ည (သို့မဟုတ် တနင်္လာနေ့ မနက်စောစော) တွင် 
 * အလိုအလျောက် အလုပ်လုပ်ရန် ရည်ရွယ်ပါသည်။
 * 
 * တွက်ချက်ပုံ: 
 * ပြီးခဲ့သော (၇) ရက်အတွင်း User ၏ စုစုပေါင်းရှုံးငွေ (Net Loss) ကို တွက်ချက်မည်။
 * Net Loss = (Total Bets) - (Total Wins)
 * ရှုံးငွေရှိပါက ၎င်း၏ VIP Level အလိုက် Cashback ပြန်လည်ထည့်သွင်းပေးမည်။
 */

require_once __DIR__ . '/../core/db_connect.php';

$output_log = "Cashback Processing Started at " . date('Y-m-d H:i:s') . "\n";

// 1. VIP Settings များကို ဆွဲထုတ်ခြင်း
$settings_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'vip_%' OR setting_key LIKE 'cashback_%'");
$settings = [];
while ($row = $settings_stmt->fetch_assoc()) {
    $settings[$row['setting_key']] = floatval($row['setting_value']);
}

// 2. အချိန်ကာလ သတ်မှတ်ခြင်း (ပြီးခဲ့သော (၇) ရက်)
$end_date = date('Y-m-d H:i:s');
$start_date = date('Y-m-d H:i:s', strtotime('-7 days'));

$output_log .= "Period: $start_date to $end_date\n";

// 3. User များ၏ ၇ ရက်အတွင်း အရောင်းအဝယ်များကို တွက်ချက်ခြင်း
$query = "
    SELECT 
        u.id, 
        u.username,
        u.vip_level,
        COALESCE(SUM(b.amount), 0) AS total_bet_7d,
        COALESCE(SUM(CASE WHEN b.is_win = 1 THEN b.amount * b.odds ELSE 0 END), 0) AS total_win_7d
    FROM users u
    LEFT JOIN bets b ON u.id = b.user_id AND b.created_at >= '$start_date' AND b.created_at <= '$end_date'
    WHERE u.role = 'user'
    GROUP BY u.id
";
$users_result = $conn->query($query);

$conn->begin_transaction();
try {
    while ($user = $users_result->fetch_assoc()) {
        $user_id = $user['id'];
        $net_loss = $user['total_bet_7d'] - $user['total_win_7d'];
        
        // 4. Update VIP Level based on lifetime total bets
        $lifetime_stmt = $conn->prepare("SELECT SUM(amount) as lifetime_bets FROM bets WHERE user_id = ?");
        $lifetime_stmt->bind_param("i", $user_id);
        $lifetime_stmt->execute();
        $lifetime_res = $lifetime_stmt->get_result()->fetch_assoc();
        $lifetime_bets = $lifetime_res['lifetime_bets'] ?? 0;
        $lifetime_stmt->close();
        
        $new_vip_level = 'Standard';
        $cashback_percent = 0;
        
        if ($lifetime_bets >= ($settings['vip_diamond_threshold'] ?? 5000000)) {
            $new_vip_level = 'Diamond';
            $cashback_percent = $settings['cashback_diamond_percent'] ?? 10;
        } elseif ($lifetime_bets >= ($settings['vip_gold_threshold'] ?? 2000000)) {
            $new_vip_level = 'Gold';
            $cashback_percent = $settings['cashback_gold_percent'] ?? 8;
        } elseif ($lifetime_bets >= ($settings['vip_silver_threshold'] ?? 500000)) {
            $new_vip_level = 'Silver';
            $cashback_percent = $settings['cashback_silver_percent'] ?? 5;
        } elseif ($lifetime_bets >= ($settings['vip_bronze_threshold'] ?? 100000)) {
            $new_vip_level = 'Bronze';
            $cashback_percent = $settings['cashback_bronze_percent'] ?? 3;
        }

        // Update VIP level if changed
        if ($new_vip_level !== $user['vip_level']) {
            $update_vip = $conn->prepare("UPDATE users SET vip_level = ? WHERE id = ?");
            $update_vip->bind_param("si", $new_vip_level, $user_id);
            $update_vip->execute();
            $update_vip->close();
            $output_log .= "User ID {$user_id} upgraded to VIP Level: {$new_vip_level}\n";
        }

        // 5. Calculate and Process Cashback if there is a net loss
        if ($net_loss > 0 && $cashback_percent > 0) {
            $cashback_amount = $net_loss * ($cashback_percent / 100);
            
            // Add balance
            $add_bal = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $add_bal->bind_param("di", $cashback_amount, $user_id);
            $add_bal->execute();
            $add_bal->close();

            // Record transaction
            $tx_type = 'cashback';
            $status = 'approved';
            $desc = "Weekly {$cashback_percent}% Cashback for {$new_vip_level} level.";
            $tx_stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, status, description) VALUES (?, ?, ?, ?, ?)");
            $tx_stmt->bind_param("isdss", $user_id, $tx_type, $cashback_amount, $status, $desc);
            $tx_stmt->execute();
            $tx_stmt->close();
            
            $output_log .= "User ID {$user_id} received cashback: {$cashback_amount} Ks (Net Loss: {$net_loss})\n";
        }
    }
    $conn->commit();
    $output_log .= "Cashback processing completed successfully.\n";
} catch (Exception $e) {
    $conn->rollback();
    $output_log .= "Error occurred: " . $e->getMessage() . "\n";
}

echo nl2br(htmlspecialchars($output_log));
?>