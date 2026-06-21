<?php
require_once __DIR__ . '/core/auth_check.php';
require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/security_helper.php';
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

date_default_timezone_set('Asia/Yangon');

if (!isset($_SESSION['bet_requests'])) {
    $_SESSION['bet_requests'] = [];
}
$_SESSION['bet_requests'] = array_filter($_SESSION['bet_requests'], function($time) {
    return $time > (time() - 60);
});

// Session Check
$session_query = $conn->query("SELECT id, section, target_date FROM betting_sessions WHERE game_type = '2d' AND status = 'active' AND NOW() BETWEEN open_time AND close_time ORDER BY close_time ASC LIMIT 1");
$active_session = $session_query->fetch_assoc();

if (!$active_session) {
    $error_message = __('betting_closed_error');
    $is_morning = true; 
} else {
    $is_morning = ($active_session['section'] === 'morning');
}

$blocked_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('blocked_2d_numbers', 'blocked_2d_morning', 'blocked_2d_evening')");
$blocked_data = [];
while ($blocked_row = $blocked_stmt->fetch_assoc()) {
    $blocked_data[$blocked_row['setting_key']] = trim($blocked_row['setting_value'] ?? '');
}
$blocked_all = array_filter(explode(',', $blocked_data['blocked_2d_numbers'] ?? ''), 'strlen');
$blocked_morning = array_filter(explode(',', $blocked_data['blocked_2d_morning'] ?? ''), 'strlen');
$blocked_evening = array_filter(explode(',', $blocked_data['blocked_2d_evening'] ?? ''), 'strlen');
$active_blocked = array_merge($blocked_all, $is_morning ? $blocked_morning : $blocked_evening);
$blocked_numbers_arr = array_unique(array_map('trim', $active_blocked));

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
        $is_reverse = isset($_POST['is_reverse']) ? true : false;

        if (empty($user['transaction_pin'])) {
            $error_message = __('pin_not_set_error');
        } elseif (!password_verify($input_pin, $user['transaction_pin'])) {
            $error_message = __('invalid_pin');
        } else {
            $raw_numbers = preg_split('/[\s,]+/', trim($bet_number_input));
            $valid_numbers = [];
            
            foreach ($raw_numbers as $num) {
                if (preg_match('/^[0-9]{2}$/', $num)) {
                    $valid_numbers[] = $num;
                    if ($is_reverse && $num[0] !== $num[1]) {
                        $valid_numbers[] = $num[1] . $num[0];
                    }
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
                    $error_message = sprintf(__('number_blocked_error_2d'), htmlspecialchars($blocked_num));
                } else {
                    $conn->begin_transaction();
                    try {
                        $stmt_lock = $conn->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
                        $stmt_lock->bind_param("i", $user_id);
                        $stmt_lock->execute();
                        $locked_user = $stmt_lock->get_result()->fetch_assoc();
                        $stmt_lock->close();

                        if ($locked_user['balance'] < $actual_deduction) {
                            throw new Exception(__('insufficient_balance_deposit'));
                        }

                        $setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('max_limit_per_number', 'enable_dynamic_odds', 'dynamic_odds_threshold')");
                        $settings = [];
                        while ($setting_row = $setting_stmt->fetch_assoc()) {
                            $settings[$setting_row['setting_key']] = $setting_row['setting_value'];
                        }
                        $max_limit_per_number = floatval($settings['max_limit_per_number'] ?? 20000);
                        $enable_dynamic_odds = ($settings['enable_dynamic_odds'] ?? '1') === '1';
                        $dynamic_odds_threshold = floatval($settings['dynamic_odds_threshold'] ?? 80);

                        $bets_with_odds = [];
                        $current_totals = [];

                        if (count($numbers_to_bet) > 0) {
                            $placeholders = implode(',', array_fill(0, count($numbers_to_bet), '?'));
                            $types = str_repeat('s', count($numbers_to_bet));
                            $limit_stmt = $conn->prepare("SELECT bet_number, SUM(amount) AS total_betted FROM bets WHERE bet_number IN ($placeholders) AND status = 'pending' AND target_date = ? AND bet_section = ? GROUP BY bet_number FOR UPDATE");
                            $params = array_merge($numbers_to_bet, [$active_session['target_date'], $active_session['section']]);
                            $limit_stmt->bind_param($types . "ss", ...$params);
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
                            $odds = 80; 
                            
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
                                    $odds = 40; 
                                }
                            }
                            $bets_with_odds[$num] = $odds;
                        }

                        $update_stmt = $conn->prepare("UPDATE users SET balance = balance - ?, lifetime_bet = lifetime_bet + ? WHERE id = ?");
                        $update_stmt->bind_param("ddi", $actual_deduction, $total_amount_needed, $user_id);
                        $update_stmt->execute();
                        $update_stmt->close();

                        $target_date = $active_session['target_date'];
                        $bet_section = $active_session['section'];
                        $discount_per_bet = ($discount_amount > 0 && count($numbers_to_bet) > 0) ? ($discount_amount / count($numbers_to_bet)) : 0;
                        
                        $insert_stmt = $conn->prepare("INSERT INTO bets (user_id, bet_number, amount, bet_section, target_date, discount_amount, odds) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        foreach ($numbers_to_bet as $num) {
                            $current_odds = $bets_with_odds[$num];
                            $insert_stmt->bind_param("isdssdd", $user_id, $num, $bet_amount, $bet_section, $target_date, $discount_per_bet, $current_odds);
                            $insert_stmt->execute();
                        }
                        $insert_stmt->close();

                        process_mlm_commission($conn, $user_id, $user['username'], $user['referred_by'], $total_amount_needed, '2D');

                        $conn->commit(); 
                        $user['balance'] -= $actual_deduction; 

                        $num_str = implode(", ", $numbers_to_bet);
                        if (strlen($num_str) > 40) $num_str = substr($num_str, 0, 40) . "..."; 
                        
                        if ($discount_amount > 0) {
                            $success_message = sprintf(__('bet_success_discount'), count($numbers_to_bet), $bet_amount, number_format($discount_amount), number_format($actual_deduction), htmlspecialchars($num_str));
                        } else {
                            $success_message = sprintf(__('bet_success'), count($numbers_to_bet), $bet_amount, htmlspecialchars($num_str));
                        }

                        $receipt_data = [
                            'type' => '2D',
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
                $error_message = __('invalid_2d_numbers');
            }
        }
    }
}

$grid_limit_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'max_limit_per_number'");
$grid_limit_row = $grid_limit_stmt->fetch_assoc();
$grid_max_limit = $grid_limit_row ? floatval($grid_limit_row['setting_value']) : 20000;

$grid_amounts = [];
$grid_amounts_stmt = $conn->query("SELECT bet_number, SUM(amount) as total FROM bets WHERE status = 'pending' AND LENGTH(bet_number) = 2 GROUP BY bet_number");
if ($grid_amounts_stmt) {
    while ($g_row = $grid_amounts_stmt->fetch_assoc()) {
        $grid_amounts[$g_row['bet_number']] = floatval($g_row['total']);
    }
}

require_once __DIR__ . '/2d_bet_view.php'; 
?>
