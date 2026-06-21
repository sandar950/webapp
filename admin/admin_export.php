<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Admin (User ID 1) သာ ဝင်ခွင့်ပြုမည်
require_main_admin();

// Withdrawals Table သို့ fee_amount Column မရှိသေးပါက အလိုအလျောက် ထည့်သွင်းပေးမည်
$check_fee_col = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'fee_amount'");
if ($check_fee_col && $check_fee_col->num_rows == 0) {
    $conn->query("ALTER TABLE withdrawals ADD fee_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER amount");
}
$check_reason_col = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'reject_reason'");
if ($check_reason_col && $check_reason_col->num_rows == 0) {
    $conn->query("ALTER TABLE withdrawals ADD reject_reason VARCHAR(255) NULL AFTER status");
}
$check_dep_reason_col = $conn->query("SHOW COLUMNS FROM deposits LIKE 'reject_reason'");
if ($check_dep_reason_col && $check_dep_reason_col->num_rows == 0) {
    $conn->query("ALTER TABLE deposits ADD reject_reason VARCHAR(255) NULL AFTER status");
}

// မည်သည့်အချက်အလက်ကို Export ထုတ်မည်ကို ဆုံးဖြတ်ခြင်း (Default: users)
$type = $_GET['type'] ?? 'users';
$period = $_GET['period'] ?? 'all';
$filename = "export_{$type}_{$period}_" . date('Y-m-d_His') . ".csv";

function getExportDateCondition($alias = '') {
    global $period;
    $col = $alias ? "{$alias}.created_at" : "created_at";
    switch ($period) {
        case 'today': return " DATE($col) = CURDATE() ";
        case 'this_week': return " YEARWEEK($col, 1) = YEARWEEK(CURDATE(), 1) ";
        case 'this_month': return " MONTH($col) = MONTH(CURDATE()) AND YEAR($col) = YEAR(CURDATE()) ";
        default: return " 1=1 ";
    }
}

// CSV အဖြစ် Download လုပ်ရန် Headers များ သတ်မှတ်ခြင်း
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Output stream ကို ဖွင့်ခြင်း
$output = fopen('php://output', 'w');

// Excel တွင် မြန်မာစာ မှန်ကန်စွာပေါ်စေရန် UTF-8 BOM ထည့်သွင်းခြင်း
fputs($output, "\xEF\xBB\xBF");

if ($type === 'users') {
    // CSV ၏ ခေါင်းစဉ် (Headers) များ
    fputcsv($output, [__('admin_export_id'), __('admin_export_user_name'), __('admin_export_user_phone'), __('admin_export_user_balance'), __('admin_export_user_banned'), __('admin_export_user_reg_date')]);
    
    // Database မှ User အားလုံးကို ဆွဲထုတ်ခြင်း
    $cond = getExportDateCondition();
    $query = "SELECT id, username, phone_number, balance, is_banned, created_at FROM users WHERE $cond ORDER BY id ASC";
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        // is_banned ကို 0/1 အစား Multi-language ဖြင့် ပြသရန်
        $row['is_banned'] = $row['is_banned'] ? __('admin_export_yes') : __('admin_export_no');
        fputcsv($output, $row);
    }
}

elseif ($type === 'deposits') {
    // CSV ၏ ခေါင်းစဉ် (Headers) များ
    fputcsv($output, [__('admin_export_id'), __('admin_export_user_id'), __('admin_export_user_name'), __('admin_export_user_phone'), __('admin_export_amount'), __('admin_export_payment_method'), __('admin_export_trx_id'), __('admin_export_status'), __('admin_export_reject_reason'), __('admin_export_time')]);
    
    // Deposits အား Users နှင့် ချိတ်ဆက်၍ ဆွဲထုတ်ခြင်း
    $cond = getExportDateCondition('d');
    $query = "SELECT d.id, d.user_id, u.username, u.phone_number, d.amount, d.payment_method, d.transaction_id, d.status, d.reject_reason, d.created_at 
              FROM deposits d JOIN users u ON d.user_id = u.id WHERE $cond ORDER BY d.id DESC";
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $row['status'] = ucfirst($row['status']); // pending -> Pending
        fputcsv($output, $row);
    }
}

elseif ($type === 'withdrawals') {
    // CSV ၏ ခေါင်းစဉ် (Headers) များ
    fputcsv($output, [__('admin_export_id'), __('admin_export_user_id'), __('admin_export_user_name'), __('admin_export_user_phone'), __('admin_export_req_amount'), __('admin_export_fee'), __('admin_export_net'), __('admin_export_with_method'), __('admin_export_acc_no'), __('admin_export_admin_acc'), __('admin_export_status'), __('admin_export_reject_reason'), __('admin_export_time')]);
    
    // Withdrawals အား Users နှင့် ချိတ်ဆက်၍ ဆွဲထုတ်ခြင်း
    $cond = getExportDateCondition('w');
    $query = "SELECT w.id, w.user_id, u.username, u.phone_number, w.amount, w.fee_amount, w.payment_method, w.account_number, w.admin_payment_account, w.status, w.reject_reason, w.created_at 
              FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE $cond ORDER BY w.id DESC";
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $row['status'] = ucfirst($row['status']);
        $net = $row['amount'] - ($row['fee_amount'] ?? 0);
        
        $export_row = [
            $row['id'],
            $row['user_id'],
            $row['username'],
            $row['phone_number'],
            $row['amount'],
            $row['fee_amount'] ?? 0,
            $net,
            $row['payment_method'],
            $row['account_number'],
            $row['admin_payment_account'],
            $row['status'],
            $row['created_at']
        ];
        fputcsv($output, $export_row);
    }
}

elseif ($type === 'bets') {
    // CSV ၏ ခေါင်းစဉ် (Headers) များ
    fputcsv($output, [__('admin_export_id'), __('admin_export_user_id'), __('admin_export_user_name'), __('admin_export_user_phone'), __('admin_export_bet_number'), __('admin_export_bet_amount'), __('admin_export_discount'), __('admin_export_net'), __('admin_export_odds'), __('admin_export_status'), __('admin_export_time')]);
    
    // Bets အား Users နှင့် ချိတ်ဆက်၍ ဆွဲထုတ်ခြင်း
    $cond = getExportDateCondition('b');
    $query = "SELECT b.id, b.user_id, u.username, u.phone_number, b.bet_number, b.amount, b.discount_amount, b.odds, b.status, b.created_at 
              FROM bets b JOIN users u ON b.user_id = u.id WHERE $cond ORDER BY b.id DESC";
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $status_mm = __('admin_export_status_pending');
        if ($row['status'] == 'win') {
            $status_mm = __('admin_export_status_win');
        } elseif ($row['status'] == 'lose') {
            $status_mm = __('admin_export_status_lose');
        }
        
        $net_amount = $row['amount'] - ($row['discount_amount'] ?? 0);

        $export_row = [
            $row['id'], $row['user_id'], $row['username'], $row['phone_number'],
            $row['bet_number'], $row['amount'], $row['discount_amount'] ?? 0,
            $net_amount, $row['odds'], $status_mm, $row['created_at']
        ];
        fputcsv($output, $export_row);
    }
}

elseif ($type === 'transfers') {
    // CSV ၏ ခေါင်းစဉ် (Headers) များ
    fputcsv($output, [__('admin_export_id'), __('admin_export_sender_name'), __('admin_export_sender_phone'), __('admin_export_receiver_name'), __('admin_export_receiver_phone'), __('admin_export_amount'), __('admin_export_time')]);
    
    // Transfers အား Users နှင့် ချိတ်ဆက်၍ ဆွဲထုတ်ခြင်း
    $cond = getExportDateCondition('t');
    $query = "SELECT t.id, u1.username as sender_name, u1.phone_number as sender_phone, 
                     u2.username as receiver_name, u2.phone_number as receiver_phone, 
                     t.amount, t.created_at 
              FROM transfers t 
              LEFT JOIN users u1 ON t.sender_id = u1.id 
              LEFT JOIN users u2 ON t.receiver_id = u2.id 
              WHERE $cond ORDER BY t.id DESC";
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

elseif ($type === 'commissions') {
    // CSV ၏ ခေါင်းစဉ် (Headers) များ
    fputcsv($output, [__('admin_export_id'), __('admin_export_referrer'), __('admin_export_referred'), __('admin_export_comm_amount'), __('admin_export_desc'), __('admin_export_time')]);
    
    // Commissions အား Users နှင့် ချိတ်ဆက်၍ ဆွဲထုတ်ခြင်း
    $cond = getExportDateCondition('c');
    $query = "SELECT c.id, u1.username as referrer_name, u2.username as referred_name, c.amount, c.description, c.created_at 
              FROM commissions c 
              JOIN users u1 ON c.referrer_id = u1.id 
              JOIN users u2 ON c.referred_user_id = u2.id 
              WHERE $cond ORDER BY c.id DESC";
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

elseif ($type === 'user_referrals') {
    $target_user_id = intval($_GET['user_id'] ?? 0);
    if ($target_user_id > 0) {
        // CSV ၏ ခေါင်းစဉ် (Headers) များ
        fputcsv($output, [__('admin_export_id'), __('admin_export_user_name'), __('admin_export_user_phone'), __('admin_export_given_comm'), __('admin_export_user_reg_date')]);
        
        $query = "SELECT u.id, u.username, u.phone_number, 
                  (SELECT SUM(amount) FROM commissions WHERE referred_user_id = u.id AND referrer_id = ?) as generated_commission,
                  u.created_at
                  FROM users u 
                  WHERE u.referred_by = ? 
                  ORDER BY u.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $target_user_id, $target_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [$row['id'], $row['username'], $row['phone_number'], $row['generated_commission'] ?? 0, date('d-M-Y h:i A', strtotime($row['created_at']))]);
        }
    }
}

elseif ($type === 'activity_logs') {
    // CSV ၏ ခေါင်းစဉ် (Headers) များ
    fputcsv($output, [__('admin_export_id'), __('admin_export_timestamp'), __('admin_export_admin_name'), __('admin_export_action'), __('admin_export_desc'), __('admin_export_ip')]);
    
    $where_clause = " WHERE 1=1 ";
    
    $filter_admin_id = intval($_GET['admin_id'] ?? 0);
    $filter_action = trim($_GET['action_type'] ?? '');
    $start_date = trim($_GET['start_date'] ?? '');
    $end_date = trim($_GET['end_date'] ?? '');

    if ($filter_admin_id > 0) {
        $where_clause .= " AND l.admin_id = " . $filter_admin_id;
    }
    if (!empty($start_date)) {
        $safe_start = $conn->real_escape_string($start_date);
        $where_clause .= " AND DATE(l.created_at) >= '$safe_start' ";
    }
    if (!empty($end_date)) {
        $safe_end = $conn->real_escape_string($end_date);
        $where_clause .= " AND DATE(l.created_at) <= '$safe_end' ";
    }
    if (!empty($filter_action)) {
        $safe_action = $conn->real_escape_string($filter_action);
        $where_clause .= " AND l.action = '$safe_action' ";
    }

    $query = "SELECT l.id, l.created_at, u.username as admin_name, l.action, l.description, l.ip_address 
              FROM admin_activity_logs l
              JOIN users u ON l.admin_id = u.id 
              $where_clause 
              ORDER BY l.id DESC";
              
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
    }
}

fclose($output);
exit();
?>