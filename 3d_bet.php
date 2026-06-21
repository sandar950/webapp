<?php
require_once __DIR__ . '/core/auth_check.php';

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/lang/language.php';

// လက်ရှိ User ၏ လက်ကျန်ငွေကို ဆွဲထုတ်ခြင်း
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, balance, referred_by, transaction_pin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$success_message = "";
$error_message = "";
$receipt_data = null;

// Database မှ ပိတ်ထားသော 3D ဂဏန်းများကို ဆွဲထုတ်ခြင်း
date_default_timezone_set('Asia/Yangon');

$blocked_stmt_init = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'blocked_3d_numbers'");
$blocked_row_init = $blocked_stmt_init->fetch_assoc();
$blocked_numbers_str_init = $blocked_row_init ? trim($blocked_row_init['setting_value']) : '';
$blocked_numbers_arr = array_filter(array_map('trim', explode(',', $blocked_numbers_str_init)), 'strlen');

// Rate limiting for betting (max 10 requests per minute per user session)
if (!isset($_SESSION['bet_requests'])) {
    $_SESSION['bet_requests'] = [];
}
$_SESSION['bet_requests'] = array_filter($_SESSION['bet_requests'], function($time) {
    return $time > (time() - 60);
});

// Session Check (open_time နှင့် close_time ကိုပါ ထည့်သွင်းဆွဲထုတ်ထားသည်)
$session_query = $conn->query("SELECT id, section, target_date, open_time, close_time FROM betting_sessions WHERE game_type = '3d' AND status = 'active' AND NOW() BETWEEN open_time AND close_time ORDER BY close_time ASC LIMIT 1");
$active_session = $session_query->fetch_assoc();

if (!$active_session) {
    $error_message = __('betting_closed_error');
}

// Form Submit လုပ်လာသောအခါ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$active_session) {
        $error_message = __('betting_closed_error');
    } elseif (count($_SESSION['bet_requests']) >= 10) {
        $error_message = __('rate_limit_exceeded_bet');
    } else {
        $_SESSION['bet_requests'][] = time();
        
        $bet_number_input = $_POST['bet_number'] ?? '';
        $bet_amount = $_POST['bet_amount'] ?? 0;
        $input_pin = $_POST['pin'] ?? '';

        if (empty($user['transaction_pin'])) {
            $error_message = __('pin_not_set_error');
        } elseif (!password_verify($input_pin, $user['transaction_pin'])) {
            $error_message = __('invalid_pin');
        } else {
            $raw_numbers = preg_split('/[\s,]+/', trim($bet_number_input));
            $valid_numbers = [];
            
            foreach ($raw_numbers as $num) {
                if (preg_match('/^[0-9]{3}$/', $num)) {
                    $valid_numbers[] = $num;
                }
            }
            $numbers_to_bet = array_values(array_unique($valid_numbers));

            if (count($numbers_to_bet) > 0 && is_numeric($bet_amount) && $bet_amount > 0) {
                $total_amount_needed = $bet_amount * count($numbers_to_bet);
                
                $discount_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'bet_discount_percent'");
                $discount_row = $discount_stmt->fetch_assoc();
                $discount_percent = $discount_row ? floatval($discount_row['setting_value']) : 0;
                
                $discount_amount = ($discount_percent > 0) ? ($total_amount_needed * ($discount_percent / 100)) : 0;
                $actual_deduction = $total_amount_needed - $discount_amount;

                $blocked_num = '';
                foreach ($numbers_to_bet as $num) {
                    if (in_array($num, $blocked_numbers_arr)) {
                        $blocked_num = $num;
                        break;
                    }
                }

                if ($blocked_num !== '') {
                    $error_message = sprintf(__('number_blocked_error'), $blocked_num);
                } else {
                    
                    // Transaction စတင်ခြင်း (Race condition ကာကွယ်ရန်)
                    $conn->begin_transaction();
                    try {
                        // ငွေမဖြတ်မီ အချိန်ကျော်သွားခြင်း ရှိမရှိ (Strict Time Check) အရင်စစ်ဆေးပါမည်
                        $check_time_stmt = $conn->prepare("SELECT id FROM betting_sessions WHERE id = ? AND NOW() BETWEEN open_time AND close_time");
                        $check_time_stmt->bind_param("i", $active_session['id']);
                        $check_time_stmt->execute();
                        $is_valid_time = $check_time_stmt->get_result()->num_rows > 0;
                        $check_time_stmt->close();

                        if (!$is_valid_time) {
                            throw new Exception(__('betting_closed_error') ?? 'ပွဲပိတ်သွားပါပြီ။ ထိုးကြေးလက်မခံတော့ပါ။');
                        }

                        // ၁။ User Balance ကို FOR UPDATE ဖြင့် Lock ချ၍ ဆွဲထုတ်စစ်ဆေးခြင်း
                        $stmt_lock = $conn->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
                        $stmt_lock->bind_param("i", $user_id);
                        $stmt_lock->execute();
                        $locked_user = $stmt_lock->get_result()->fetch_assoc();
                        $stmt_lock->close();

                        if ($locked_user['balance'] < $actual_deduction) {
                            throw new Exception(__('insufficient_balance_deposit'));
                        }

                        $setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('max_limit_per_3d_number', 'enable_dynamic_odds', 'dynamic_odds_threshold')");
                        $settings = [];
                        while ($setting_row = $setting_stmt->fetch_assoc()) {
                            $settings[$setting_row['setting_key']] = $setting_row['setting_value'];
                        }
                        $max_limit_per_number = floatval($settings['max_limit_per_3d_number'] ?? 10000);
                        $enable_dynamic_odds = ($settings['enable_dynamic_odds'] ?? '1') === '1';
                        $dynamic_odds_threshold = floatval($settings['dynamic_odds_threshold'] ?? 80);

                        $bets_with_odds = [];
                        $current_totals = [];

                        // ၂။ Bets Limit ကို FOR UPDATE ဖြင့် Lock ချ၍ ဆွဲထုတ်စစ်ဆေးခြင်း
                        if (count($numbers_to_bet) > 0) {
                            $placeholders = implode(',', array_fill(0, count($numbers_to_bet), '?'));
                            $types = str_repeat('s', count($numbers_to_bet));
                            $limit_stmt = $conn->prepare("SELECT bet_number, SUM(amount) AS total_betted FROM bets WHERE bet_number IN ($placeholders) AND status = 'pending' AND target_date = ? AND bet_section = '3d' GROUP BY bet_number FOR UPDATE");
                            $params = array_merge($numbers_to_bet, [$active_session['target_date']]);
                            $limit_stmt->bind_param($types . "s", ...$params);
                            $limit_stmt->execute();
                            $limit_result = $limit_stmt->get_result();
                            while ($row = $limit_result->fetch_assoc()) {
                                $current_totals[$row['bet_number']] = $row['total_betted'];
                            }
                            $limit_stmt->close();
                        }

                        foreach ($numbers_to_bet as $num) {
                            $current_total = $current_totals[$num] ?? 0;
                            $projected_total = $current_total + $bet_amount;
                            $odds = 500; 
                            
                            if ($projected_total > $max_limit_per_number) {
                                $available_amount = $max_limit_per_number - $current_total;
                                if ($available_amount > 0) {
                                    throw new Exception(sprintf(__('number_limit_remaining_error'), $num, number_format($available_amount)));
                                } else {
                                    throw new Exception(sprintf(__('number_limit_reached_error'), $num));
                                }
                            } else if ($enable_dynamic_odds) {
                                $threshold_amount = $max_limit_per_number * ($dynamic_odds_threshold / 100);
                                if ($projected_total > $threshold_amount) {
                                    $odds = 250; 
                                }
                            }
                            $bets_with_odds[$num] = $odds;
                        }

                        // ၃။ User ၏ balance ထဲမှ ငွေနှုတ်ခြင်း (Deduction System)
                        $update_stmt = $conn->prepare("UPDATE users SET balance = balance - ?, lifetime_bet = lifetime_bet + ? WHERE id = ?");
                        $update_stmt->bind_param("ddi", $actual_deduction, $total_amount_needed, $user_id);
                        $update_stmt->execute();
                        $update_stmt->close();

                        // ၄။ bets table သို့ ထည့်သွင်းခြင်း
                        $target_date = $active_session['target_date'];
                        $bet_section = '3d';
                        $discount_per_bet = ($discount_amount > 0 && count($numbers_to_bet) > 0) ? ($discount_amount / count($numbers_to_bet)) : 0;
                        
                        $insert_stmt = $conn->prepare("INSERT INTO bets (user_id, bet_number, amount, bet_section, target_date, discount_amount, odds) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        foreach ($numbers_to_bet as $num) {
                            $current_odds = $bets_with_odds[$num];
                            $insert_stmt->bind_param("isdssdd", $user_id, $num, $bet_amount, $bet_section, $target_date, $discount_per_bet, $current_odds);
                            $insert_stmt->execute();
                        }
                        $insert_stmt->close();

                        // ၅။ Referral Commission
                        process_mlm_commission($conn, $user_id, $user['username'], $user['referred_by'], $total_amount_needed, '3D');

                        $conn->commit(); 
                        $user['balance'] -= $actual_deduction; 
                        
                        $num_str = implode(", ", $numbers_to_bet);
                        if (strlen($num_str) > 40) $num_str = substr($num_str, 0, 40) . "..."; 
                        
                        // XSS Protection ထည့်သွင်းထားသည်
                        if ($discount_amount > 0) {
                            $success_message = sprintf(__('bet_success_discount'), count($numbers_to_bet), $bet_amount, number_format($discount_amount), number_format($actual_deduction), htmlspecialchars($num_str));
                        } else {
                            $success_message = sprintf(__('bet_success'), count($numbers_to_bet), $bet_amount, htmlspecialchars($num_str));
                        }

                        $receipt_data = [
                            'type' => '3D',
                            'kwek_count' => count($numbers_to_bet),
                            'bet_amount' => $bet_amount,
                            'total_amount' => $total_amount_needed,
                            'discount_amount' => $discount_amount,
                            'net_amount' => $actual_deduction,
                            'numbers' => implode(", ", $numbers_to_bet),
                            'date' => date('d-M-Y h:i A'),
                            'voucher_id' => strtoupper(substr(md5(time() . $user_id), 0, 8))
                        ];

                    } catch (Exception $e) {
                        $conn->rollback(); 
                        $error_message = $e->getMessage();
                        if (strpos($error_message, 'Deadlock') !== false) {
                            $error_message = __('system_error_try_again');
                        }
                    }
                }
            } else {
                $error_message = __('invalid_3d_numbers');
            }
        }
    }
}

// --- Prepare Data for View (MVC Structure) ---
$limit_3d_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'max_limit_per_3d_number'");
$limit_3d_row = $limit_3d_stmt->fetch_assoc();
$max_limit_3d = $limit_3d_row ? floatval($limit_3d_row['setting_value']) : 10000;

$amounts_3d = [];
$amounts_stmt = $conn->query("SELECT bet_number, SUM(amount) as total FROM bets WHERE status = 'pending' AND LENGTH(bet_number) = 3 GROUP BY bet_number");
if ($amounts_stmt) {
    while ($g_row = $amounts_stmt->fetch_assoc()) {
        $amounts_3d[$g_row['bet_number']] = floatval($g_row['total']);
    }
}

require_once __DIR__ . '/3d_bet_view.php'; 
?>
