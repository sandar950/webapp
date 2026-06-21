<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

// အသစ်ထပ်တိုးထားသော Setting နှင့် Column များကို Database တွင် အလိုအလျောက် ထည့်သွင်းပေးရန်
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('withdrawal_fee_percent', '0')");
$check_fee_col = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'fee_amount'");
if ($check_fee_col && $check_fee_col->num_rows == 0) {
    $conn->query("ALTER TABLE withdrawals ADD fee_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER amount");
}
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('telegram_bot_token', '')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('telegram_channel_id', '')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('bet_cancel_time_limit', '10')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('home_banner_url_2', '')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('home_banner_url_3', '')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('live_2d_api_url', '')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('live_3d_api_url', '')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('enable_dynamic_odds', '1')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('dynamic_odds_threshold', '80')");

// MLM Settings
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('mlm_level_1_percent', '3')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('mlm_level_2_percent', '1.5')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('mlm_level_3_percent', '0.5')");

// VIP & Cashback Settings
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('vip_bronze_threshold', '100000')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('vip_silver_threshold', '500000')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('vip_gold_threshold', '2000000')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('vip_diamond_threshold', '5000000')");

$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('cashback_standard_percent', '0')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('cashback_bronze_percent', '3')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('cashback_silver_percent', '5')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('cashback_gold_percent', '8')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('cashback_diamond_percent', '10')");

// VIP Daily Bonus Settings
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('daily_bonus_standard', '500')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('daily_bonus_bronze', '1000')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('daily_bonus_silver', '2000')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('daily_bonus_gold', '5000')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('daily_bonus_diamond', '10000')");

// Customer Service Settings
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('cs_messenger_link', '')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('cs_telegram_link', '')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('cs_viber_link', '')");

// Pop-up Announcement Settings
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('announcement_text', '')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('announcement_image_url', '')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('announcement_is_active', '0')");

// Maintenance Mode Settings
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('maintenance_mode', '0')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('maintenance_message', 'ဆာဗာပြုပြင်ထိန်းသိမ်းမှုများ ပြုလုပ်နေပါသည်။ ခေတ္တစောင့်ဆိုင်းပေးပါ။')");

// Session Timeout Setting
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('session_timeout_minutes', '30')");

// Check and add column for odds in bets table
$check_odds_col = $conn->query("SHOW COLUMNS FROM bets LIKE 'odds'");
if ($check_odds_col && $check_odds_col->num_rows == 0) {
    $conn->query("ALTER TABLE bets ADD odds DECIMAL(10, 2) DEFAULT NULL AFTER amount");
}

// Check and add column for vip_level in users table
$check_vip_col = $conn->query("SHOW COLUMNS FROM users LIKE 'vip_level'");
if ($check_vip_col && $check_vip_col->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD vip_level VARCHAR(20) DEFAULT 'Standard' AFTER balance");
}

// Check and add column for transaction_pin in users table
$check_pin_col = $conn->query("SHOW COLUMNS FROM users LIKE 'transaction_pin'");
if ($check_pin_col && $check_pin_col->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD transaction_pin VARCHAR(255) DEFAULT NULL AFTER password");
}

// Form Submit လုပ်လာသောအခါ Update လုပ်ခြင်း
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_limit'])) {
    $new_kbz_acc = trim($_POST['kbz_pay_account'] ?? '');
    $new_kbz_name = trim($_POST['kbz_pay_name'] ?? '');
    $new_kbz_qr = trim($_POST['kbz_pay_qr_url'] ?? '');
    $new_wave_acc = trim($_POST['wave_pay_account'] ?? '');
    $new_wave_name = trim($_POST['wave_pay_name'] ?? '');
    $new_wave_qr = trim($_POST['wave_pay_qr_url'] ?? '');
    $new_comm_percent = floatval($_POST['referral_commission_percent'] ?? 0);
    $new_daily_bonus = floatval($_POST['daily_bonus_amount'] ?? 0);
    $new_db_standard = floatval($_POST['daily_bonus_standard'] ?? 500);
    $new_db_bronze = floatval($_POST['daily_bonus_bronze'] ?? 1000);
    $new_db_silver = floatval($_POST['daily_bonus_silver'] ?? 2000);
    $new_db_gold = floatval($_POST['daily_bonus_gold'] ?? 5000);
    $new_db_diamond = floatval($_POST['daily_bonus_diamond'] ?? 10000);
    $new_bet_discount = floatval($_POST['bet_discount_percent'] ?? 0);
    $new_registration_fee = floatval($_POST['registration_fee'] ?? 0);
    $new_cs_messenger = trim($_POST['cs_messenger_link'] ?? '');
    $new_cs_telegram = trim($_POST['cs_telegram_link'] ?? '');
    $new_cs_viber = trim($_POST['cs_viber_link'] ?? '');
    $new_maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
    $new_maintenance_message = trim($_POST['maintenance_message'] ?? '');
    $new_session_timeout = intval($_POST['session_timeout_minutes'] ?? 30);

        // Payment Accounts Update
        $stmt_kbz = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'kbz_pay_account'");
        $stmt_kbz->bind_param("s", $new_kbz_acc);
        $stmt_kbz->execute(); $stmt_kbz->close();
        
        $stmt_kbz_n = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'kbz_pay_name'");
        $stmt_kbz_n->bind_param("s", $new_kbz_name);
        $stmt_kbz_n->execute(); $stmt_kbz_n->close();
        
        $stmt_kbz_qr = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'kbz_pay_qr_url'");
        $stmt_kbz_qr->bind_param("s", $new_kbz_qr);
        $stmt_kbz_qr->execute(); $stmt_kbz_qr->close();
        
        $stmt_wave = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'wave_pay_account'");
        $stmt_wave->bind_param("s", $new_wave_acc);
        $stmt_wave->execute(); $stmt_wave->close();
        
        $stmt_wave_n = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'wave_pay_name'");
        $stmt_wave_n->bind_param("s", $new_wave_name);
        $stmt_wave_n->execute(); $stmt_wave_n->close();

        $stmt_wave_qr = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'wave_pay_qr_url'");
        $stmt_wave_qr->bind_param("s", $new_wave_qr);
        $stmt_wave_qr->execute(); $stmt_wave_qr->close();

        // Commission Percent Update
        $stmt_comm = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'referral_commission_percent'");
        $stmt_comm->bind_param("d", $new_comm_percent);
        $stmt_comm->execute(); $stmt_comm->close();

        // Daily Bonus Update
        $stmt_bonus = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'daily_bonus_amount'");
        $stmt_bonus->bind_param("d", $new_daily_bonus);
        $stmt_bonus->execute(); $stmt_bonus->close();

        $stmt_db_std = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'daily_bonus_standard'");
        $stmt_db_std->bind_param("d", $new_db_standard);
        $stmt_db_std->execute(); $stmt_db_std->close();

        $stmt_db_brz = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'daily_bonus_bronze'");
        $stmt_db_brz->bind_param("d", $new_db_bronze);
        $stmt_db_brz->execute(); $stmt_db_brz->close();

        $stmt_db_slv = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'daily_bonus_silver'");
        $stmt_db_slv->bind_param("d", $new_db_silver);
        $stmt_db_slv->execute(); $stmt_db_slv->close();

        $stmt_db_gld = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'daily_bonus_gold'");
        $stmt_db_gld->bind_param("d", $new_db_gld);
        $stmt_db_gld->execute(); $stmt_db_gld->close();

        $stmt_db_dia = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'daily_bonus_diamond'");
        $stmt_db_dia->bind_param("d", $new_db_diamond);
        $stmt_db_dia->execute(); $stmt_db_dia->close();

        // Bet Discount Update
        $stmt_disc = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'bet_discount_percent'");
        $stmt_disc->bind_param("d", $new_bet_discount);
        $stmt_disc->execute(); $stmt_disc->close();

        // Registration Fee Update
        $stmt_reg = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'registration_fee'");
        $stmt_reg->bind_param("d", $new_registration_fee);
        $stmt_reg->execute(); $stmt_reg->close();

        // Customer Service Updates
        $stmt_cs_m = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'cs_messenger_link'");
        $stmt_cs_m->bind_param("s", $new_cs_messenger);
        $stmt_cs_m->execute(); $stmt_cs_m->close();

        $stmt_cs_t = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'cs_telegram_link'");
        $stmt_cs_t->bind_param("s", $new_cs_telegram);
        $stmt_cs_t->execute(); $stmt_cs_t->close();

        $stmt_cs_v = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'cs_viber_link'");
        $stmt_cs_v->bind_param("s", $new_cs_viber);
        $stmt_cs_v->execute(); $stmt_cs_v->close();

        // Maintenance Mode Updates
        $stmt_main_mode = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
        $stmt_main_mode->bind_param("s", $new_maintenance_mode);
        $stmt_main_mode->execute(); $stmt_main_mode->close();

        $stmt_main_msg = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_message'");
        $stmt_main_msg->bind_param("s", $new_maintenance_message);
        $stmt_main_msg->execute(); $stmt_main_msg->close();

        // Session Timeout Update
        $stmt_session = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'session_timeout_minutes'");
        $stmt_session->bind_param("i", $new_session_timeout);
        $stmt_session->execute(); $stmt_session->close();

        $success_message = __('settings_updated_successfully');
        log_activity($_SESSION['user_id'], 'UPDATE_SETTINGS', "System settings were updated.");
}

// လက်ရှိ Limits များကို Database မှ ဆွဲထုတ်ခြင်း
$limits = [
    'max_limit_per_number' => 20000,
    'max_limit_per_3d_number' => 10000,
    'daily_bonus_amount' => 500,
    'daily_bonus_standard' => 500,
    'daily_bonus_bronze' => 1000,
    'daily_bonus_silver' => 2000,
    'daily_bonus_gold' => 5000,
    'daily_bonus_diamond' => 10000,
    'bet_discount_percent' => 0,
    'registration_fee' => 0,
    'blocked_2d_numbers' => '',
    'blocked_3d_numbers' => '',
    'min_deposit' => 1000,
    'max_deposit' => 1000000,
    'min_withdraw' => 1000,
        'max_withdraw' => 1000000,
        'withdrawal_fee_percent' => 0,
        'telegram_bot_token' => '',
        'telegram_channel_id' => '',
        'bet_cancel_time_limit' => 10,
        'live_2d_api_url' => '',
        'live_3d_api_url' => '',
        'enable_dynamic_odds' => '1',
        'dynamic_odds_threshold' => 80,
        'cs_messenger_link' => '',
        'cs_telegram_link' => '',
        'cs_viber_link' => '',
        'maintenance_mode' => '0',
        'maintenance_message' => '',
        'session_timeout_minutes' => 30
];
    $setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('max_limit_per_number', 'max_limit_per_3d_number', 'daily_bonus_amount', 'daily_bonus_standard', 'daily_bonus_bronze', 'daily_bonus_silver', 'daily_bonus_gold', 'daily_bonus_diamond', 'bet_discount_percent', 'registration_fee', 'bet_cancel_time_limit', 'cs_messenger_link', 'cs_telegram_link', 'cs_viber_link', 'maintenance_mode', 'maintenance_message', 'session_timeout_minutes')");
while ($row = $setting_stmt->fetch_assoc()) {
        $limits[$row['setting_key']] = in_array($row['setting_key'], ['max_limit_per_number', 'max_limit_per_3d_number', 'daily_bonus_amount', 'daily_bonus_standard', 'daily_bonus_bronze', 'daily_bonus_silver', 'daily_bonus_gold', 'daily_bonus_diamond', 'bet_discount_percent', 'registration_fee', 'bet_cancel_time_limit', 'session_timeout_minutes']) ? floatval($row['setting_value']) : $row['setting_value'];
}
$current_daily_bonus = $limits['daily_bonus_amount'];
$current_bet_discount = $limits['bet_discount_percent'];
$current_registration_fee = $limits['registration_fee'];
$current_cs_messenger = $limits['cs_messenger_link'];
$current_cs_telegram = $limits['cs_telegram_link'];
$current_cs_viber = $limits['cs_viber_link'];
$current_maintenance_mode = $limits['maintenance_mode'];
$current_maintenance_message = $limits['maintenance_message'];
?>

<?php 
$page_title = __('admin_settings') . " - Thai 2D3D";
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('system_settings');
    $header_icon = "fas fa-cog";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <form method="POST" action="" enctype="multipart/form-data">

                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 mt-8"><?= __('daily_bonus') ?></h2>
                <div class="mb-5 bg-green-50 p-4 rounded-lg border border-green-200">
                    <p class="text-xs text-green-800 mb-4 font-bold"><?= __('daily_bonus_desc') ?></p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 border-b border-green-200 pb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1"><?= __('standard') ?></label>
                            <input type="number" name="daily_bonus_standard" value="<?= htmlspecialchars($limits['daily_bonus_standard'] ?? 500) ?>" min="0" class="w-full py-2 px-3 border rounded focus:border-green-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1 text-orange-600"><?= __('bronze_level') ?></label>
                            <input type="number" name="daily_bonus_bronze" value="<?= htmlspecialchars($limits['daily_bonus_bronze'] ?? 1000) ?>" min="0" class="w-full py-2 px-3 border rounded focus:border-green-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1 text-gray-500"><?= __('silver_level') ?></label>
                            <input type="number" name="daily_bonus_silver" value="<?= htmlspecialchars($limits['daily_bonus_silver'] ?? 2000) ?>" min="0" class="w-full py-2 px-3 border rounded focus:border-green-500 focus:outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1 text-yellow-600"><?= __('gold_level') ?></label>
                            <input type="number" name="daily_bonus_gold" value="<?= htmlspecialchars($limits['daily_bonus_gold'] ?? 5000) ?>" min="0" class="w-full py-2 px-3 border rounded focus:border-green-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1 text-blue-600"><?= __('diamond_level') ?></label>
                            <input type="number" name="daily_bonus_diamond" value="<?= htmlspecialchars($limits['daily_bonus_diamond'] ?? 10000) ?>" min="0" class="w-full py-2 px-3 border rounded focus:border-green-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 mt-8"><?= __('admin_session_timeout_title') ?></h2>
                <div class="mb-5 bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                    <p class="text-xs text-yellow-800 mb-3 font-bold"><?= __('admin_session_timeout_desc') ?></p>
                    <input type="number" name="session_timeout_minutes" value="<?= htmlspecialchars($limits['session_timeout_minutes'] ?? '30') ?>" min="1" class="w-full py-2 px-3 border rounded focus:border-yellow-500 focus:outline-none">
                </div>

                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 mt-8"><?= __('maintenance_mode_title') ?></h2>
                <div class="mb-5 bg-red-50 p-4 rounded-lg border border-red-100">
                    <div class="mb-3 flex items-center">
                        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?= $current_maintenance_mode == '1' ? 'checked' : '' ?> class="w-5 h-5 text-red-600 border-gray-300 rounded focus:ring-red-500">
                        <label for="maintenance_mode" class="ml-2 block text-gray-700 text-sm font-bold"><?= __('enable_maintenance_mode') ?></label>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1"><?= __('maintenance_message_label') ?></label>
                        <textarea name="maintenance_message" rows="2" placeholder="<?= __('maintenance_message_placeholder') ?>" class="w-full py-2 px-3 border rounded focus:border-red-500 focus:outline-none"><?= htmlspecialchars($current_maintenance_message) ?></textarea>
                    </div>
                </div>

                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 mt-8"><?= __('customer_service') ?></h2>
                <div class="mb-5 bg-teal-50 p-4 rounded-lg border border-teal-100">
                    <p class="text-xs text-teal-800 mb-3 font-bold"><?= __('customer_service_desc') ?></p>
                    <div class="mb-3">
                        <label class="block text-gray-700 text-sm font-bold mb-1"><i class="fab fa-facebook-messenger text-blue-600 mr-1"></i> <?= __('messenger_link') ?></label>
                        <input type="text" name="cs_messenger_link" value="<?= htmlspecialchars($limits['cs_messenger_link'] ?? '') ?>" placeholder="m.me/yourpage" class="w-full py-2 px-3 border rounded focus:border-teal-500 focus:outline-none">
                    </div>
                    <div class="mb-3">
                        <label class="block text-gray-700 text-sm font-bold mb-1"><i class="fab fa-telegram text-blue-400 mr-1"></i> <?= __('telegram_link') ?></label>
                        <input type="text" name="cs_telegram_link" value="<?= htmlspecialchars($limits['cs_telegram_link'] ?? '') ?>" placeholder="t.me/yourusername" class="w-full py-2 px-3 border rounded focus:border-teal-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1"><i class="fab fa-viber text-purple-600 mr-1"></i> <?= __('viber_link') ?></label>
                        <input type="text" name="cs_viber_link" value="<?= htmlspecialchars($limits['cs_viber_link'] ?? '') ?>" placeholder="viber://chat?number=..." class="w-full py-2 px-3 border rounded focus:border-teal-500 focus:outline-none">
                    </div>
                </div>

                <button type="submit" name="update_limit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition">
                    <i class="fas fa-save mr-2"></i> <?= __('save') ?>
                </button>
            </form>

            <!-- Database Backup Section -->
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 mt-8"><?= __('database_backup') ?></h2>
            <div class="bg-green-50 p-4 rounded-lg border border-green-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div>
                    <h3 class="font-bold text-green-800 mb-1"><i class="fas fa-database mr-1"></i> <?= __('backup_database') ?></h3>
                    <p class="text-sm text-gray-600"><?= __('backup_desc') ?></p>
                </div>
                <a href="admin_backup.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition whitespace-nowrap" onclick="return confirm('<?= __('backup_confirm') ?>');">
                    <i class="fas fa-download mr-1"></i> <?= __('do_backup') ?>
                </a>
            </div>
        </div>
    </div>
</body>
</html>