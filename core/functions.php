<?php
/**
 * Multi-level Referral Commission ကို တွက်ချက်ပြီး balance ထည့်သွင်းပေးသည့် function
 */
function process_mlm_commission($conn, $user_id, $username, $referred_by, $total_bet_amount, $game_label) {
    if (empty($referred_by)) return;

    // Load MLM Settings from database
    $mlm_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('mlm_level_1_percent', 'mlm_level_2_percent', 'mlm_level_3_percent', 'referral_commission_percent')");
    $mlm_settings = [];
    if ($mlm_stmt) {
        while ($m_row = $mlm_stmt->fetch_assoc()) {
            $mlm_settings[$m_row['setting_key']] = floatval($m_row['setting_value']);
        }
    }
    
    $level_1_percent = $mlm_settings['mlm_level_1_percent'] ?? ($mlm_settings['referral_commission_percent'] ?? 5);
    $level_2_percent = $mlm_settings['mlm_level_2_percent'] ?? 0;
    $level_3_percent = $mlm_settings['mlm_level_3_percent'] ?? 0;

    // Commission ပေးရန်နှင့် log မှတ်ရန် internal logic
    $apply_commission = function($ref_id, $percent, $level_name) use ($conn, $total_bet_amount, $user_id, $username, $game_label) {
        if ($percent > 0 && $ref_id) {
            $commission_amount = $total_bet_amount * ($percent / 100);
            if ($commission_amount > 0) {
                // balance တိုးပေးခြင်း
                $comm_update = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $comm_update->bind_param("di", $commission_amount, $ref_id);
                $comm_update->execute();
                $comm_update->close();
                
                // log မှတ်တမ်းသွင်းခြင်း
                $comm_log = $conn->prepare("INSERT INTO commissions (referrer_id, referred_user_id, amount, description) VALUES (?, ?, ?, ?)");
                $desc = "{$game_label} Commission (Level {$level_name}) from " . $username;
                $comm_log->bind_param("iids", $ref_id, $user_id, $commission_amount, $desc);
                $comm_log->execute();
                $comm_log->close();
            }
        }
    };

    // Level 1 (Direct Referrer)
    $apply_commission($referred_by, $level_1_percent, '1');

    // Level 2 စစ်ဆေးခြင်း
    if ($level_2_percent > 0 || $level_3_percent > 0) {
        $stmt_l2 = $conn->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt_l2->bind_param("i", $referred_by);
        $stmt_l2->execute();
        $res_l2 = $stmt_l2->get_result()->fetch_assoc();
        $stmt_l2->close();
        
        $referrer_2 = $res_l2['referred_by'] ?? null;
        if ($referrer_2) {
            $apply_commission($referrer_2, $level_2_percent, '2');
            
            // Level 3 စစ်ဆေးခြင်း
            if ($level_3_percent > 0) {
                $stmt_l3 = $conn->prepare("SELECT referred_by FROM users WHERE id = ?");
                $stmt_l3->bind_param("i", $referrer_2);
                $stmt_l3->execute();
                $res_l3 = $stmt_l3->get_result()->fetch_assoc();
                $stmt_l3->close();

                $referrer_3 = $res_l3['referred_by'] ?? null;
                if ($referrer_3) {
                    $apply_commission($referrer_3, $level_3_percent, '3');
                }
            }
        }
    }
}

// --- Refactored 2D Bet Submission Functions ---

/**
 * Validates the initial bet request conditions.
 * @return string|null Returns an error message string if validation fails, otherwise null.
 */
function validate_bet_request($user, $active_session, $input_pin) {
    if (!$active_session) {
        return "ယခုအချိန်တွင် ထိုးကြေးလက်ခံခြင်း ပိတ်ထားပါသည်။";
    }

    // Rate limiting
    if (!isset($_SESSION['bet_requests'])) $_SESSION['bet_requests'] = [];
    $_SESSION['bet_requests'] = array_filter($_SESSION['bet_requests'], fn($time) => $time > (time() - 60));
    if (count($_SESSION['bet_requests']) >= 10) {
        return "ခဏအတွင်း ထိုးထားသော အကြိမ်ရေများလွန်းပါသည်။ ၁ မိနစ်ခန့် စောင့်ဆိုင်းပြီးမှ ထပ်မံကြိုးစားပါ။";
    }

    if (empty($user['transaction_pin'])) {
        return "လုံခြုံရေး PIN သတ်မှတ်ထားခြင်း မရှိသေးပါ။ Profile တွင် အရင်သတ်မှတ်ပါ။";
    }
    if (!password_verify($input_pin, $user['transaction_pin'])) {
        return "လုံခြုံရေး PIN မှားယွင်းနေပါသည်။";
    }
    
    return null; // All checks passed
}

/**
 * Parses and validates the bet numbers from user input.
 * @return array Returns a unique array of valid 2-digit numbers.
 */
function parse_and_validate_bet_numbers($bet_number_input, $is_reverse) {
    $raw_numbers = preg_split('/[\s,]+/', trim($bet_number_input));
    $valid_numbers = [];
    foreach ($raw_numbers as $num) {
        if (preg_match('/^[0-9]{2}$/', $num)) {
            $valid_numbers[] = $num;
            if ($is_reverse && $num[0] !== $num[1]) {
                $valid_numbers[] = strrev($num);
            }
        }
    }
    return array_values(array_unique($valid_numbers));
}

/**
 * Calculates total cost, discount, and net deduction for the bet.
 * @return array An array containing total, discount, and net amounts.
 */
function calculate_bet_cost($conn, $bet_amount, $num_bets) {
    $total_amount_needed = $bet_amount * $num_bets;
    
    $discount_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'bet_discount_percent'");
    $discount_percent = $discount_stmt ? floatval($discount_stmt->fetch_assoc()['setting_value']) : 0;
    
    $discount_amount = ($discount_percent > 0) ? ($total_amount_needed * ($discount_percent / 100)) : 0;
    $actual_deduction = $total_amount_needed - $discount_amount;

    return [
        'total' => $total_amount_needed,
        'discount' => $discount_amount,
        'net' => $actual_deduction
    ];
}

/**
 * Checks if any of the bet numbers are in the blocked list.
 * @return string|null An error message if a number is blocked, otherwise null.
 */
function check_blocked_numbers($numbers_to_bet, $blocked_numbers_arr) {
    foreach ($numbers_to_bet as $num) {
        if (in_array($num, $blocked_numbers_arr)) {
            return "ဂဏန်း [{$num}] သည် ယနေ့အတွက် ပိတ်ထားသော ဂဏန်းဖြစ်သဖြင့် ထိုး၍မရပါ။";
        }
    }
    return null;
}

/**
 * Checks betting limits and determines odds for each number.
 * @return array An array containing a potential error message and the odds for each bet.
 */
function check_betting_limits($conn, $numbers_to_bet, $bet_amount, $active_session) {
    // Fetch settings
    $setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('max_limit_per_number', 'enable_dynamic_odds', 'dynamic_odds_threshold')");
    $settings = [];
    while ($setting_row = $setting_stmt->fetch_assoc()) {
        $settings[$setting_row['setting_key']] = $setting_row['setting_value'];
    }
    $max_limit_per_number = floatval($settings['max_limit_per_number'] ?? 20000);
    $enable_dynamic_odds = ($settings['enable_dynamic_odds'] ?? '1') === '1';
    $dynamic_odds_threshold = floatval($settings['dynamic_odds_threshold'] ?? 80);

    // Fetch current bet totals for the numbers
    $current_totals = [];
    if (count($numbers_to_bet) > 0) {
        $placeholders = implode(',', array_fill(0, count($numbers_to_bet), '?'));
        $types = str_repeat('s', count($numbers_to_bet));
        $limit_stmt = $conn->prepare("SELECT bet_number, SUM(amount) AS total_betted FROM bets WHERE bet_number IN ($placeholders) AND status = 'pending' AND target_date = ? AND bet_section = ? GROUP BY bet_number");
        $params = array_merge($numbers_to_bet, [$active_session['target_date'], $active_session['section']]);
        $limit_stmt->bind_param($types . "ss", ...$params);
        $limit_stmt->execute();
        $result = $limit_stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $current_totals[$row['bet_number']] = $row['total_betted'];
        }
        $limit_stmt->close();
    }

    // Check limits and calculate odds for each number
    $bets_with_odds = [];
    foreach ($numbers_to_bet as $num) {
        $current_total = $current_totals[$num] ?? 0;
        $projected_total = $current_total + $bet_amount;
        $odds = 80;

        if ($projected_total > $max_limit_per_number) {
            $available_amount = max(0, $max_limit_per_number - $current_total);
            return [
                'error' => "ဂဏန်း [{$num}] အတွက် အများဆုံး " . number_format($available_amount) . " ကျပ် ဖိုးသာ ထိုးလို့ရပါတော့မည်။",
                'bets_with_odds' => []
            ];
        }

        if ($enable_dynamic_odds) {
            $threshold_amount = $max_limit_per_number * ($dynamic_odds_threshold / 100);
            if ($projected_total > $threshold_amount) {
                $odds = 40;
            }
        }
        $bets_with_odds[$num] = $odds;
    }

    return ['error' => null, 'bets_with_odds' => $bets_with_odds];
}

/**
 * Executes the database transaction to place the bets.
 * @return array An array containing the result of the transaction.
 */
function execute_bet_transaction($conn, &$user, $bet_data) {
    $conn->begin_transaction();
    try {
        // 1. Deduct balance from user
        $update_stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $update_stmt->bind_param("di", $bet_data['net_amount'], $user['id']);
        $update_stmt->execute();
        $update_stmt->close();

        // 2. Insert bets
        $discount_per_bet = ($bet_data['discount_amount'] > 0 && count($bet_data['numbers']) > 0) 
                            ? ($bet_data['discount_amount'] / count($bet_data['numbers'])) 
                            : 0;
        
        $insert_stmt = $conn->prepare("INSERT INTO bets (user_id, bet_number, amount, bet_section, target_date, discount_amount, odds) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($bet_data['numbers'] as $num) {
            $odds = $bet_data['odds'][$num];
            $insert_stmt->bind_param("isdssdd", $user['id'], $num, $bet_data['bet_amount'], $bet_data['session']['section'], $bet_data['session']['target_date'], $discount_per_bet, $odds);
            $insert_stmt->execute();
        }
        $insert_stmt->close();

        // 3. Process MLM commission
        process_mlm_commission($conn, $user['id'], $user['username'], $user['referred_by'], $bet_data['total_amount'], '2D');
        
        $conn->commit();

        // Update user balance in the session/local variable
        $user['balance'] -= $bet_data['net_amount'];

        return ['success' => true, 'message' => "စုစုပေါင်း " . count($bet_data['numbers']) . " ကွက်အား တစ်ကွက်လျှင် {$bet_data['bet_amount']} ဖြင့် အောင်မြင်စွာ ထိုးပြီးပါပြီ။"];

    } catch (Exception $e) {
        $conn->rollback();
        // Log the actual error for admins
        error_log("Bet transaction failed for user {$user['id']}: " . $e->getMessage());
        return ['success' => false, 'message' => "စနစ်ချို့ယွင်းမှုဖြစ်ပေါ်နေပါသည်။ ခေတ္တစောင့်ဆိုင်းပြီး ထပ်မံကြိုးစားပါ။"];
    }
}


/**
 * Main handler for a 2D bet submission.
 * This function now orchestrates calls to smaller, focused functions.
 */
function handle_2d_bet_submission($conn, &$user, $active_session, $blocked_numbers_arr) {
    $result = ['success' => false, 'message' => '', 'receipt_data' => null];

    // Arguments from POST request
    $bet_number_input = $_POST['bet_number'] ?? '';
    $bet_amount = (int)($_POST['bet_amount'] ?? 0);
    $input_pin = $_POST['pin'] ?? '';
    $is_reverse = isset($_POST['is_reverse']);

    // 1. Initial Validation (Session, Rate Limit, PIN)
    if ($error = validate_bet_request($user, $active_session, $input_pin)) {
        $result['message'] = $error;
        return $result;
    }
    $_SESSION['bet_requests'][] = time(); // Record request time after validation passes

    // 2. Parse and Validate Bet Numbers
    $numbers_to_bet = parse_and_validate_bet_numbers($bet_number_input, $is_reverse);
    if (empty($numbers_to_bet) || $bet_amount <= 0) {
        $result['message'] = "ကျေးဇူးပြု၍ ၂ လုံးဂဏန်းများကို မှန်ကန်စွာ ထည့်သွင်းပြီး ထိုးကြေးကို သတ်မှတ်ပါ။";
        return $result;
    }

    // 3. Calculate Cost
    $cost = calculate_bet_cost($conn, $bet_amount, count($numbers_to_bet));

    // 4. Check Blocked Numbers
    if ($error = check_blocked_numbers($numbers_to_bet, $blocked_numbers_arr)) {
        $result['message'] = $error;
        return $result;
    }

    // 5. Check User Balance
    if ($user['balance'] < $cost['net']) {
        $result['message'] = "လက်ကျန်ငွေ မလုံလောက်ပါ။ ကျေးဇူးပြု၍ ငွေဖြည့်ပါ။ (အာပူး ရွေးချယ်ထားပါက ကျသင့်ငွေ ၂ ဆ ဖြစ်ပါမည်)";
        return $result;
    }

    // 6. Check Betting Limits and Get Odds
    $limit_check = check_betting_limits($conn, $numbers_to_bet, $bet_amount, $active_session);
    if ($limit_check['error']) {
        $result['message'] = $limit_check['error'];
        return $result;
    }
    $bets_with_odds = $limit_check['bets_with_odds'];

    // 7. Execute Transaction
    $bet_data = [
        'numbers' => $numbers_to_bet,
        'bet_amount' => $bet_amount,
        'total_amount' => $cost['total'],
        'discount_amount' => $cost['discount'],
        'net_amount' => $cost['net'],
        'odds' => $bets_with_odds,
        'session' => $active_session,
    ];
    
    $transaction_result = execute_bet_transaction($conn, $user, $bet_data);

    // 8. Finalize Result
    $result['success'] = $transaction_result['success'];
    $result['message'] = $transaction_result['message'];

    if ($transaction_result['success']) {
        $result['receipt_data'] = array_merge($result['receipt_data'] ?? [], [
            'type' => '2D', 
            'kwek_count' => count($numbers_to_bet), 
            'bet_amount' => $bet_amount, 
            'total_amount' => $cost['total'], 
            'discount_amount' => $cost['discount'], 
            'net_amount' => $cost['net'], 
            'numbers' => implode(", ", $numbers_to_bet), 
            'date' => date('d-M-Y h:i A'), 
            'voucher_id' => strtoupper(substr(md5(time() . $user['id']), 0, 8))
        ]);
    }
    
    return $result;
}