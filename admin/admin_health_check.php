<?php
session_start();
require_once '../core/db_connect.php';
require_once '../core/auth_helper.php';

// Main Admin (role='admin') သာ ဝင်ခွင့်ပြုမည်
require_main_admin();

// CSRF Token တည်ဆောက်ခြင်း (လုံခြုံရေးအတွက်)
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$results = [];
$success_message = "";
$error_message = "";

// --- Auto-Fix Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // CSRF Token စစ်ဆေးခြင်း
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
            throw new Exception("လုံခြုံရေးအရ အမှားအယွင်းဖြစ်ပေါ်နေပါသည်။ (CSRF Token Mismatch)");
        }

        $action = $_POST['action'];
        
        if ($action === 'create_upload_dirs') {
            $dirs = ['uploads', '../uploads/avatars', '../uploads/slips', '../uploads/notifications'];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) {
                    if (!@mkdir($dir, 0777, true)) {
                        throw new Exception(sprintf(__('admin_health_upload_dir_error') ?? 'Cannot create directory %s', $dir));
                    }
                }
            }
            $success_message = __('admin_health_upload_dir_success') ?? 'Directories created successfully.';
            
        } elseif ($action === 'run_sql_fix') {
            $sql_query = $_POST['sql_query'] ?? '';
            if (!empty($sql_query)) {
                if ($conn->multi_query($sql_query)) {
                    // To clear results from multi_query
                    while ($conn->more_results() && $conn->next_result()) {
                        if ($res = $conn->store_result()) {
                            $res->free();
                        }
                    }
                    $success_message = __('admin_health_db_fix_success') ?? "Database ကို အောင်မြင်စွာ ပြုပြင်ပြီးပါပြီ။";
                    // Note: log_activity function should be defined in your codebase
                    if (function_exists('log_activity')) {
                        log_activity($_SESSION['user_id'] ?? 0, 'DB_SCHEMA_FIX', "Executed SQL fix from Health Check page.");
                    }
                } else {
                    throw new Exception("SQL Error: " . $conn->error);
                }
            }
        }
        // Refresh the page to see updated status
        header("Location: admin_health_check.php?success=" . urlencode($success_message));
        exit();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// --- Check Functions ---
function add_check(&$results, $category, $title, $status, $message, $fix_action = null, $fix_data = null) {
    $results[$category][] = [
        'title' => $title,
        'status' => $status, // 'ok', 'warning', 'error', 'fix'
        'message' => $message,
        'fix_action' => $fix_action,
        'fix_data' => $fix_data
    ];
}

// 1. Environment Checks
$php_version = phpversion();
if (version_compare($php_version, '7.4.0', '>=')) {
    add_check($results, 'environment', 'PHP Version', 'ok', sprintf(__('admin_health_php_ver_ok') ?? 'PHP version is %s.', $php_version));
} else {
    add_check($results, 'environment', 'PHP Version', 'error', sprintf(__('admin_health_php_ver_error') ?? 'PHP version %s is too old.', $php_version));
}

$extensions = ['mysqli', 'curl', 'gd', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        add_check($results, 'environment', "PHP Extension: {$ext}", 'ok', sprintf(__('admin_health_ext_ok') ?? 'Extension %s is loaded.', $ext));
    } else {
        add_check($results, 'environment', "PHP Extension: {$ext}", 'error', sprintf(__('admin_health_ext_error') ?? 'Extension %s is missing.', $ext));
    }
}

// 2. Filesystem Checks
if (file_exists('../setup.php')) {
    add_check($results, 'filesystem', 'Security Risk: setup.php', 'error', __('admin_health_setup_exists') ?? 'setup.php exists. Please delete it.');
} else {
    add_check($results, 'filesystem', 'Security: setup.php', 'ok', __('admin_health_setup_deleted') ?? 'setup.php is correctly deleted.');
}

$writable_dirs = ['uploads', '../uploads/avatars', '../uploads/slips', '../uploads/notifications'];
$all_dirs_ok = true;
foreach ($writable_dirs as $dir) {
    if (is_dir($dir) && is_writable($dir)) {
        add_check($results, 'filesystem', "Directory: {$dir}", 'ok', sprintf(__('admin_health_dir_ok') ?? 'Directory %s is writable.', $dir));
    } else {
        $all_dirs_ok = false;
        if (!is_dir($dir)) {
            add_check($results, 'filesystem', "Directory: {$dir}", 'error', sprintf(__('admin_health_dir_not_exist') ?? 'Directory %s does not exist.', $dir));
        } elseif (!is_writable($dir)) {
            add_check($results, 'filesystem', "Directory: {$dir}", 'error', sprintf(__('admin_health_dir_not_writable') ?? 'Directory %s is not writable.', $dir));
        }
    }
}
if (!$all_dirs_ok) {
    add_check($results, 'filesystem', __('admin_health_fix_dirs') ?? 'Fix Directories', 'fix', __('admin_health_dir_fix_msg') ?? 'Create missing directories.', 'create_upload_dirs');
}

// 3. Database Checks
$db_connected = false;
if ($conn && !$conn->connect_error) {
    // Attempting to get db name via query if not set
    $res_db = $conn->query("SELECT DATABASE()");
    $dbname = ($res_db && $row = $res_db->fetch_row()) ? $row[0] : 'Unknown';
    add_check($results, 'database', 'Database Connection', 'ok', sprintf(__('admin_health_db_conn_ok') ?? 'Connected to database.', $dbname));
    $db_connected = true;
} else {
    add_check($results, 'database', 'Database Connection', 'error', sprintf(__('admin_health_db_conn_error') ?? 'Database connection failed: %s', ($conn->connect_error ?? 'Unknown error')));
}

if ($db_connected) {
    $schema = [
        'users' => ['id', 'username', 'phone_number', 'password', 'referral_code', 'referred_by', 'avatar', 'balance', 'kbz_pay_number', 'wave_pay_number', 'kbz_pay_name', 'wave_pay_name', 'payment_info_json', 'role', 'notifications', 'is_banned', 'verification_status', 'last_bonus_date', 'last_active', 'telegram_chat_id', 'transaction_pin', 'vip_level', 'lifetime_bet', 'agent_commission_percent', 'agent_share_percent', 'last_login_ip'],
        'bets' => ['id', 'user_id', 'bet_number', 'amount', 'discount_amount', 'odds', 'status', 'created_at'],
        'deposits' => ['id', 'user_id', 'amount', 'payment_method', 'payment_account_id', 'transaction_id', 'slip_image_url', 'status', 'reject_reason', 'created_at'],
        'withdrawals' => ['id', 'user_id', 'amount', 'fee_amount', 'payment_method', 'account_number', 'admin_payment_account', 'status', 'reject_reason', 'created_at'],
        'settings' => ['id', 'setting_key', 'setting_value'],
        'system_notifications' => ['id', 'user_id', 'message', 'image_url', 'is_read', 'is_important', 'sort_order', 'created_at'],
        'commissions' => ['id', 'referrer_id', 'referred_user_id', 'amount', 'description', 'created_at'],
        'support_messages' => ['id', 'user_id', 'message', 'attachment_url', 'admin_reply', 'admin_attachment_url', 'status', 'is_read', 'created_at'],
        'result_history' => ['id', 'result_number', 'type', 'created_at'],
        'sub_admin_permissions' => ['user_id', 'can_declare_result', 'can_manage_transactions', 'can_manage_users', 'can_view_reports', 'can_manage_blocked_numbers', 'can_send_notifications'],
        'admin_activity_logs' => ['id', 'admin_id', 'action', 'description', 'ip_address', 'created_at'],
        'transfers' => ['id', 'sender_id', 'receiver_id', 'amount', 'created_at'],
        'loans' => ['id', 'user_id', 'amount', 'status', 'created_at', 'updated_at'],
        'payment_accounts' => ['id', 'payment_method', 'account_name', 'account_number', 'logo_url', 'qr_image_url', 'is_active', 'sort_order', 'created_at'],
        'pre_approved_transactions' => ['id', 'payment_method', 'transaction_id', 'amount', 'status', 'created_at'],
        'bonus_history' => ['id', 'user_id', 'bonus_type', 'amount', 'description', 'created_at'],
    ];

    $sql_fixes = [];
    $tables_res = $conn->query("SHOW TABLES");
    $existing_tables = [];
    while ($row = $tables_res->fetch_row()) { $existing_tables[] = $row[0]; }

    // --- Performance Index Check ---
    if (in_array('bets', $existing_tables)) {
        $idx_res = $conn->query("SHOW INDEX FROM `bets` WHERE Key_name = 'idx_bet_limit_calc'");
        if ($idx_res && $idx_res->num_rows == 0) {
            $sql_fix_index = "ALTER TABLE `bets` ADD INDEX `idx_bet_limit_calc` (`bet_number`, `status`, `target_date`, `bet_section`);";
            add_check($results, 'performance', 'Database Index (bets table)', 'warning', __('admin_health_missing_index') ?? 'Limit တွက်ချက်မှု မြန်ဆန်စေရန် Index လိုအပ်နေပါသည်။', 'run_sql_fix', $sql_fix_index);
        } else {
            add_check($results, 'performance', 'Database Index (bets table)', 'ok', __('admin_health_index_ok') ?? 'Performance Index ထည့်သွင်းထားပြီးပါပြီ။');
        }
    }

    foreach ($schema as $table_name => $columns) {
        if (in_array($table_name, $existing_tables)) {
            $cols_res = $conn->query("SHOW COLUMNS FROM `{$table_name}`");
            $existing_columns = [];
            while ($row = $cols_res->fetch_assoc()) { $existing_columns[] = $row['Field']; }
            $missing_columns = array_diff($columns, $existing_columns);
            
            if (empty($missing_columns)) {
                add_check($results, 'database', "Table: `{$table_name}`", 'ok', __('admin_health_table_ok') ?? 'Table is up to date.');
            } else {
                $msg = sprintf(__('admin_health_table_missing_cols') ?? 'Table %s is missing columns: %s', $table_name, implode(', ', $missing_columns));
                add_check($results, 'database', "Table: `{$table_name}`", 'error', $msg);
                
                foreach($missing_columns as $mc) {
                    if ($table_name == 'users' && $mc == 'transaction_pin') $sql_fixes[] = "ALTER TABLE `users` ADD `transaction_pin` VARCHAR(255) NULL AFTER `last_active`;";
                    if ($table_name == 'users' && $mc == 'vip_level') $sql_fixes[] = "ALTER TABLE `users` ADD `vip_level` VARCHAR(50) DEFAULT 'Standard' AFTER `transaction_pin`;";
                    if ($table_name == 'users' && $mc == 'lifetime_bet') $sql_fixes[] = "ALTER TABLE `users` ADD `lifetime_bet` DECIMAL(15, 2) DEFAULT 0.00 AFTER `vip_level`;";
                    if ($table_name == 'users' && $mc == 'agent_commission_percent') $sql_fixes[] = "ALTER TABLE `users` ADD `agent_commission_percent` DECIMAL(5, 2) DEFAULT 0.00 AFTER `lifetime_bet`;";
                    if ($table_name == 'users' && $mc == 'agent_share_percent') $sql_fixes[] = "ALTER TABLE `users` ADD `agent_share_percent` DECIMAL(5, 2) DEFAULT 0.00 AFTER `agent_commission_percent`;";
                    if ($table_name == 'users' && $mc == 'telegram_chat_id') $sql_fixes[] = "ALTER TABLE `users` ADD `telegram_chat_id` VARCHAR(50) NULL AFTER `last_active`;";
                    if ($table_name == 'users' && $mc == 'last_login_ip') $sql_fixes[] = "ALTER TABLE `users` ADD `last_login_ip` VARCHAR(45) NULL AFTER `last_active`;";
                    if ($table_name == 'users' && $mc == 'kbz_pay_name') $sql_fixes[] = "ALTER TABLE `users` ADD `kbz_pay_name` VARCHAR(100) NULL AFTER `kbz_pay_number`;";
                    if ($table_name == 'users' && $mc == 'wave_pay_name') $sql_fixes[] = "ALTER TABLE `users` ADD `wave_pay_name` VARCHAR(100) NULL AFTER `wave_pay_number`;";
                    if ($table_name == 'users' && $mc == 'payment_info_json') $sql_fixes[] = "ALTER TABLE `users` ADD `payment_info_json` TEXT NULL AFTER `wave_pay_name`;";
                    
                    if ($table_name == 'bets' && $mc == 'odds') $sql_fixes[] = "ALTER TABLE `bets` ADD `odds` INT NULL DEFAULT NULL AFTER `discount_amount`;";
                    
                    if ($table_name == 'deposits' && $mc == 'slip_image_url') $sql_fixes[] = "ALTER TABLE `deposits` ADD `slip_image_url` VARCHAR(255) NULL AFTER `transaction_id`;";
                    if ($table_name == 'deposits' && $mc == 'payment_account_id') $sql_fixes[] = "ALTER TABLE `deposits` ADD `payment_account_id` INT NULL AFTER `payment_method`;";
                    
                    if ($table_name == 'withdrawals' && $mc == 'fee_amount') $sql_fixes[] = "ALTER TABLE `withdrawals` ADD `fee_amount` DECIMAL(10, 2) DEFAULT 0.00 AFTER `amount`;";
                    
                    if ($table_name == 'support_messages' && $mc == 'attachment_url') $sql_fixes[] = "ALTER TABLE `support_messages` ADD `attachment_url` VARCHAR(255) NULL AFTER `message`; ALTER TABLE `support_messages` MODIFY `message` TEXT NULL;";
                    if ($table_name == 'support_messages' && $mc == 'admin_attachment_url') $sql_fixes[] = "ALTER TABLE `support_messages` ADD `admin_attachment_url` VARCHAR(255) NULL AFTER `admin_reply`;";
                    if ($table_name == 'support_messages' && $mc == 'is_read') $sql_fixes[] = "ALTER TABLE `support_messages` ADD `is_read` BOOLEAN DEFAULT FALSE AFTER `status`;";
                    
                    if ($table_name == 'system_notifications' && $mc == 'is_read') $sql_fixes[] = "ALTER TABLE `system_notifications` ADD `is_read` BOOLEAN DEFAULT FALSE AFTER `image_url`;";
                    if ($table_name == 'system_notifications' && $mc == 'is_important') $sql_fixes[] = "ALTER TABLE `system_notifications` ADD `is_important` BOOLEAN DEFAULT FALSE AFTER `is_read`;";
                    if ($table_name == 'system_notifications' && $mc == 'sort_order') $sql_fixes[] = "ALTER TABLE `system_notifications` ADD `sort_order` INT DEFAULT 0 AFTER `is_important`;";
                    
                    if ($table_name == 'payment_accounts' && $mc == 'sort_order') $sql_fixes[] = "ALTER TABLE `payment_accounts` ADD `sort_order` INT DEFAULT 0 AFTER `is_active`;";
                }
            }
        } else {
            add_check($results, 'database', "Table: `{$table_name}`", 'error', sprintf(__('admin_health_table_not_exist') ?? 'Table %s does not exist.', $table_name));
            
            if ($table_name == 'transfers') {
                $sql_fixes[] = "CREATE TABLE transfers (id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NOT NULL, receiver_id INT NOT NULL, amount DECIMAL(10, 2) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE);";
            } elseif ($table_name == 'payment_accounts') {
                $sql_fixes[] = "CREATE TABLE payment_accounts (id INT AUTO_INCREMENT PRIMARY KEY, payment_method VARCHAR(50) NOT NULL, account_name VARCHAR(100) NOT NULL, account_number VARCHAR(50) NOT NULL, logo_url VARCHAR(255) NULL, qr_image_url VARCHAR(255) NULL, is_active TINYINT(1) DEFAULT 1, sort_order INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);";
            } elseif ($table_name == 'pre_approved_transactions') {
                $sql_fixes[] = "CREATE TABLE pre_approved_transactions (id INT AUTO_INCREMENT PRIMARY KEY, payment_method VARCHAR(50) NOT NULL, transaction_id VARCHAR(50) NOT NULL, amount DECIMAL(10, 2) NOT NULL, status ENUM('pending', 'used') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);";
            } else {
                $sql_fixes[] = "-- Table '{$table_name}' is missing. Please run setup.php or restore from a backup.";
            }
        }
    }

    if (!empty($sql_fixes)) {
        $full_sql_fix = implode("\n", $sql_fixes);
        add_check($results, 'database', __('admin_health_fix_db') ?? 'Fix Database Schema', 'fix', __('admin_health_db_fix_msg') ?? 'Apply missing columns and tables.', 'run_sql_fix', $full_sql_fix);
    }

    $admin_res = $conn->query("SELECT id FROM users WHERE id = 1 AND role = 'admin'");
    if ($admin_res && $admin_res->num_rows > 0) {
        add_check($results, 'database', 'Main Admin Account', 'ok', __('admin_health_admin_ok') ?? 'Main admin account exists.');
    } else {
        add_check($results, 'database', 'Main Admin Account', 'error', __('admin_health_admin_error') ?? 'Main admin account is missing.');
    }
}

$page_title = __('admin_health_page_title') ?? 'System Health Check';
require_once '../includes/header.php';
?>

<body class="max-w-5xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10 transition-all duration-300">

    <?php
    $header_title = __('admin_health_header_title') ?? 'System Health Check';
    $header_icon = "fas fa-heartbeat";
    require_once 'admin_header.php';
    ?>

    <div class="p-4 md:p-8 pt-0 mt-4 md:mt-6 max-w-4xl mx-auto">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl relative mb-6 text-sm md:text-base shadow-sm font-bold flex items-center">
                <i class="fas fa-check-circle mr-2 text-green-500 text-xl"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl relative mb-6 text-sm md:text-base shadow-sm font-bold flex items-center">
                <i class="fas fa-exclamation-circle mr-2 text-red-500 text-xl"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php foreach ($results as $category => $checks): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6 md:mb-8 overflow-hidden">
                <h2 class="text-base md:text-lg font-bold text-gray-800 p-5 bg-gray-50 border-b border-gray-100 capitalize flex items-center">
                    <i class="fas fa-cogs mr-2 text-primary"></i> <?= str_replace('_', ' ', $category) ?>
                </h2>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($checks as $check): ?>
                        <?php
                        $status_icon = '';
                        $status_color = '';
                        switch ($check['status']) {
                            case 'ok':
                                $status_icon = 'fa-check-circle';
                                $status_color = 'text-green-500';
                                break;
                            case 'warning':
                                $status_icon = 'fa-exclamation-triangle';
                                $status_color = 'text-yellow-500';
                                break;
                            case 'error':
                                $status_icon = 'fa-times-circle';
                                $status_color = 'text-red-500';
                                break;
                            case 'fix':
                                $status_icon = 'fa-wrench';
                                $status_color = 'text-blue-500';
                                break;
                        }
                        ?>
                        <div class="p-5 flex flex-col md:flex-row md:items-center gap-4 hover:bg-gray-50/50 transition-colors">
                            <div class="flex-shrink-0 w-full md:w-1/3 flex items-center">
                                <i class="fas <?= $status_icon ?> <?= $status_color ?> mr-3 text-lg md:text-xl"></i>
                                <span class="font-bold text-gray-700 text-sm md:text-base"><?= htmlspecialchars($check['title']) ?></span>
                            </div>
                            <div class="flex-1 text-sm md:text-base text-gray-600 font-medium">
                                <p><?= htmlspecialchars($check['message']) ?></p>
                            </div>
                            <?php if ($check['fix_action']): ?>
                                <div class="flex-shrink-0 mt-3 md:mt-0">
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>">
                                        <input type="hidden" name="action" value="<?= htmlspecialchars($check['fix_action']) ?>">
                                        <?php if ($check['fix_data']): ?>
                                            <input type="hidden" name="sql_query" value="<?= htmlspecialchars($check['fix_data']) ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md hover:shadow-lg transition-all hover:-translate-y-0.5 flex justify-center items-center gap-2" onclick="return confirm('<?= __('admin_health_confirm_fix') ?? 'ပြုပြင်ရန် သေချာပါသလား?' ?>');">
                                            <i class="fas fa-magic"></i> <?= __('admin_health_btn_fix') ?? 'Fix Issue' ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>