<?php
// စကားဝှက် စစ်ဆေးခြင်း
$setup_password = '88888888';
if (!isset($_POST['password']) || $_POST['password'] !== $setup_password) {
?>
<!DOCTYPE html>
<html lang="my">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Setup - Thai 2D3D</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white max-w-md w-full rounded-2xl shadow-xl p-8 text-center border-t-4 border-blue-600">
        <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-lock text-3xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Setup လုံခြုံရေး</h2>
        <p class="text-sm text-gray-500 mb-6">စနစ်ကို တည်ဆောက်ရန် စကားဝှက် လိုအပ်ပါသည်။</p>
        <?php if (isset($_POST['password'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded relative mb-4 text-sm font-bold">စကားဝှက် မှားယွင်းနေပါသည်။</div>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="password" required placeholder="စကားဝှက် (Password)" class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 mb-4 text-center tracking-widest text-lg font-mono">
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 shadow-md flex justify-center items-center">
                <i class="fas fa-check-circle mr-2"></i> အတည်ပြု၍ စတင်မည်
            </button>
        </form>
    </div>
</body>
</html>
<?php
    exit();
}

?>
<!DOCTYPE html>
<html lang="my">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Running Setup - Thai 2D3D</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .log-container p { margin-bottom: 0.5rem; padding: 0.5rem; border-radius: 0.375rem; background-color: #f9fafb; border: 1px solid #e5e7eb; font-size: 0.875rem; color: #374151; }
        .log-container p.success { background-color: #ecfdf5; border-color: #a7f3d0; color: #047857; }
        .log-container p.error { background-color: #fef2f2; border-color: #fecaca; color: #b91c1c; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen py-10 px-4">
    <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-blue-600 p-6 text-center text-white">
            <i class="fas fa-cogs text-4xl mb-3"></i>
            <h1 class="text-2xl font-bold">System Installation Process</h1>
            <p class="text-blue-100 text-sm mt-1">Database နှင့် လိုအပ်သောဖိုင်များ တည်ဆောက်ခြင်း</p>
        </div>
        <div class="p-6">
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded shadow-sm">
                <h2 class="font-bold text-red-800 flex items-center"><i class="fas fa-shield-alt mr-2"></i> လုံခြုံရေး အဆင့်မြှင့်တင်မှု</h2>
                <p class="text-sm text-red-700 mt-1">လုပ်ငန်းစဉ် ပြီးဆုံးပါက ဤ <strong>setup.php</strong> ဖိုင်သည် လုံခြုံရေးအရ အလိုအလျောက် ပျက်သွားမည် ဖြစ်ပါသည်။</p>
            </div>
            <div class="log-container h-[400px] overflow-y-auto pr-2 space-y-2 font-mono">
<?php
ob_implicit_flush(true);
if (ob_get_level() > 0) { ob_end_flush(); }


// MySQL Server အချက်အလက်များ (မိမိစက်နှင့် ကိုက်ညီအောင် ပြင်ဆင်ပါ)
$servername = "localhost";
$username = "root";     // XAMPP/WAMP တွင် များသောအားဖြင့် root ဖြစ်သည်
$password = "";         // XAMPP/WAMP တွင် များသောအားဖြင့် password မရှိပါ

// ၁။ MySQL Server သို့ ချိတ်ဆက်ခြင်း (Database နာမည်မပါဘဲ ချိတ်ဆက်သည်)
$conn = new mysqli($servername, $username, $password);

// ချိတ်ဆက်မှု အောင်မြင်ခြင်း ရှိ/မရှိ စစ်ဆေးခြင်း
if ($conn->connect_error) {
    die("MySQL သို့ ချိတ်ဆက်ရာတွင် အဆင်မပြေပါ: " . $conn->connect_error);
}
echo "<p class='success'><b>MySQL သို့ အောင်မြင်စွာ ချိတ်ဆက်ပြီးပါပြီ။</b></p>";

// မြန်မာစာနှင့် အခြား Unicode စာလုံးများ မှန်ကန်စွာ သိမ်းဆည်းနိုင်ရန် utf8mb4 ကို သတ်မှတ်ခြင်း
$conn->set_charset("utf8mb4");

// ၂။ Database အလိုအလျောက် တည်ဆောက်ခြင်း
$dbname = "thai_2d3d_db";
$sql_create_db = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql_create_db) === TRUE) {
    echo "<p>Database '$dbname' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Database ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ဖန်တီးလိုက်သော Database ကို ရွေးချယ်အသုံးပြုခြင်း
$conn->select_db($dbname);

// Helper function: Column ရှိ/မရှိ စစ်ဆေးပြီး မရှိပါက ထည့်သွင်းရန်
function addColumnIfNotExists($conn, $table, $column, $definition, $after_column = "") {
    $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $conn->query($query);
    if ($result->num_rows == 0) {
        $after = !empty($after_column) ? " AFTER `$after_column`" : "";
        $sql = "ALTER TABLE `$table` ADD `$column` $definition $after";
        if ($conn->query($sql)) {
        echo "<p class='success'>Table '$table' သို့ '$column' column ထပ်မံဖြည့်စွက်ပြီးပါပြီ။</p>";
            return true;
        }
    }
    return false;
}

// ၃။ Users Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_table = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_create_table) === TRUE) {
    echo "<p class='success'>Table 'users' အဆင်သင့်ဖြစ်ပါပြီ။</p>";
} else {
    die("<p class='error'>Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// Column များကို စစ်ဆေးပြီး လိုအပ်ပါက ဖြည့်စွက်ခြင်း
if (addColumnIfNotExists($conn, 'users', 'password', "VARCHAR(255) NOT NULL", 'phone_number')) {
    $default_hash = password_hash('123456', PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password = '$default_hash'");
}

addColumnIfNotExists($conn, 'users', 'avatar', "VARCHAR(255) NULL", 'password');
addColumnIfNotExists($conn, 'users', 'balance', "DECIMAL(10, 2) DEFAULT 0.00", 'avatar');
addColumnIfNotExists($conn, 'users', 'role', "ENUM('user', 'sub_admin', 'admin') NOT NULL DEFAULT 'user'", 'balance');
addColumnIfNotExists($conn, 'users', 'kbz_pay_number', "VARCHAR(50) NULL", 'balance');
addColumnIfNotExists($conn, 'users', 'kbz_pay_name', "VARCHAR(100) NULL", 'kbz_pay_number');
addColumnIfNotExists($conn, 'users', 'wave_pay_number', "VARCHAR(50) NULL", 'kbz_pay_name');
addColumnIfNotExists($conn, 'users', 'wave_pay_name', "VARCHAR(100) NULL", 'wave_pay_number');
addColumnIfNotExists($conn, 'users', 'transaction_pin', "VARCHAR(255) DEFAULT NULL", 'password');
addColumnIfNotExists($conn, 'users', 'vip_level', "VARCHAR(20) DEFAULT 'Standard'", 'balance');
addColumnIfNotExists($conn, 'users', 'lifetime_bet', "DECIMAL(15, 2) DEFAULT 0.00", 'vip_level');
addColumnIfNotExists($conn, 'users', 'is_banned', "BOOLEAN DEFAULT FALSE");
addColumnIfNotExists($conn, 'users', 'notifications', "INT DEFAULT 0");
addColumnIfNotExists($conn, 'users', 'verification_status', "ENUM('pending', 'approved', 'rejected') DEFAULT 'approved'", 'is_banned');
addColumnIfNotExists($conn, 'users', 'last_bonus_date', "DATE NULL", 'is_banned');
addColumnIfNotExists($conn, 'users', 'last_active', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP", 'last_bonus_date');
addColumnIfNotExists($conn, 'users', 'telegram_chat_id', "VARCHAR(50) NULL", 'last_active');

if (addColumnIfNotExists($conn, 'users', 'referral_code', "VARCHAR(20) UNIQUE NULL", 'phone_number')) {
    addColumnIfNotExists($conn, 'users', 'referred_by', "INT NULL", 'referral_code');
    // ရှိပြီးသား User များအတွက် ဖိတ်ခေါ်ကုဒ် (Referral Code) အလိုအလျောက် ထုတ်ပေးမည်
    $users_res = $conn->query("SELECT id FROM users");
    while($u = $users_res->fetch_assoc()) {
        $code = strtoupper(substr(md5(uniqid() . $u['id']), 0, 6));
        $conn->query("UPDATE users SET referral_code = '$code' WHERE id = " . $u['id']);
    }
}

// ၂၂။ Betting Sessions Table တည်ဆောက်ခြင်း (သို့) Update လုပ်ခြင်း
$sql_create_sessions = "CREATE TABLE IF NOT EXISTS betting_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_type ENUM('2d', '3d') NOT NULL,
    section VARCHAR(50) NOT NULL,
    target_date DATE NOT NULL,
    open_time DATETIME NOT NULL,
    close_time DATETIME NOT NULL,
    status ENUM('active', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_session (game_type, section, target_date)
)";
if ($conn->query($sql_create_sessions) === TRUE) {
    echo "<p>Table 'betting_sessions' အဆင်သင့်ဖြစ်ပါပြီ။</p>";
}

addColumnIfNotExists($conn, 'betting_sessions', 'admin_notified', "BOOLEAN DEFAULT FALSE", 'status');

// ၄။ Admin အကောင့် (ID 1) ရှိ/မရှိ စစ်ဆေးပြီး မရှိပါက အလိုအလျောက် ထည့်သွင်းခြင်း
$check_admin = $conn->query("SELECT id FROM users WHERE id = 1 OR phone_number = '09000000001'");
if ($check_admin->num_rows == 0) {
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT); // အရေးကြီး: Setup ပြီးလျှင် ဤစကားဝှက်ကို ချက်ချင်းပြောင်းပါ
    $admin_ref_code = strtoupper(substr(md5(uniqid() . 'admin'), 0, 6));
    
    $stmt = $conn->prepare("INSERT INTO users (id, username, phone_number, password, referral_code, role) VALUES (1, 'Admin', '09000000001', ?, ?, 'admin')");
    $stmt->bind_param("ss", $admin_pass, $admin_ref_code);
    if ($stmt->execute()) {
        echo "<p class='success'>Admin အကောင့်ကို အောင်မြင်စွာ ထည့်သွင်းပြီးပါပြီ။ (Phone: 09000000001, Pass: admin123)</p>";
    }
} else {
    echo "<p>Admin အကောင့် ထည့်သွင်းထားပြီးသား ဖြစ်ပါသည်။</p>";
}

// ၄ (က)။ နမူနာ User အကောင့် ရှိ/မရှိ စစ်ဆေးပြီး မရှိပါက ထည့်သွင်းမည်
$check_user = $conn->query("SELECT id FROM users WHERE phone_number = '09123456789'");
if ($check_user->num_rows == 0) {
    $user_pass = password_hash('123456', PASSWORD_DEFAULT);
    $user_ref_code = strtoupper(substr(md5(uniqid() . 'user'), 0, 6));
    $sql_insert_user = "INSERT INTO users (username, phone_number, password, referral_code, balance) 
                        VALUES ('Sample User', '09123456789', '$user_pass', '$user_ref_code', 5000.00)";
    
    if ($conn->query($sql_insert_user) === TRUE) {
        echo "<p>နမူနာ User Data ကို အောင်မြင်စွာ ထည့်သွင်းပြီးပါပြီ။</p>";
    } else {
        echo "<p class='error'>Data ထည့်သွင်းရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>";
    }
} else {
    echo "<p>နမူနာ User Data များ ရေးသွင်းထားပြီးသား ဖြစ်ပါသည်။</p>";
}

// ၅။ Bets Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_bets = "CREATE TABLE IF NOT EXISTS bets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bet_number VARCHAR(10) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    odds INT NULL DEFAULT NULL,
    bet_section ENUM('morning', 'evening', '3d') NOT NULL,
    target_date DATE NOT NULL,
    status ENUM('pending', 'win', 'lose') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_bets) === TRUE) {
    echo "<p>Table 'bets' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Bets Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// (အရေးကြီး) ယခင်က bets table တည်ဆောက်ထားပြီးပါက status column ထပ်ထည့်ပေးရန်
$check_bet_status = $conn->query("SHOW COLUMNS FROM bets LIKE 'status'");
if ($check_bet_status && $check_bet_status->num_rows == 0) {
    $conn->query("ALTER TABLE bets ADD status ENUM('pending', 'win', 'lose') DEFAULT 'pending' AFTER amount");
    echo "<p class='success'>Table 'bets' သို့ status column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
}

// (အရေးကြီး) discount အတွက် column ထပ်ထည့်ပေးရန်
$check_bet_discount = $conn->query("SHOW COLUMNS FROM bets LIKE 'discount_amount'");
if ($check_bet_discount && $check_bet_discount->num_rows == 0) {
    $conn->query("ALTER TABLE bets ADD discount_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER amount");
    echo "<p class='success'>Table 'bets' သို့ discount_amount column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
}

// (အရေးကြီး) odds အတွက် column ထပ်ထည့်ပေးရန်
$check_bet_odds = $conn->query("SHOW COLUMNS FROM bets LIKE 'odds'");
if ($check_bet_odds && $check_bet_odds->num_rows == 0) {
    $conn->query("ALTER TABLE bets ADD odds INT NULL DEFAULT NULL AFTER discount_amount");
    echo "<p class='success'>Table 'bets' သို့ odds column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
}

$check_bet_meta = $conn->query("SHOW COLUMNS FROM bets LIKE 'bet_section'");
if ($check_bet_meta && $check_bet_meta->num_rows == 0) {
    $conn->query("ALTER TABLE bets ADD bet_section ENUM('morning', 'evening', '3d') NOT NULL AFTER odds");
    $conn->query("ALTER TABLE bets ADD target_date DATE NOT NULL AFTER bet_section");
    echo "<p class='success'>Table 'bets' သို့ bet_section နှင့် target_date columns ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
}

// ၆။ Deposits Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_deposits = "CREATE TABLE IF NOT EXISTS deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(50) NOT NULL,
    slip_image_url VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reject_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_deposits) === TRUE) {
    echo "<p>Table 'deposits' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Deposits Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// (အရေးကြီး) ယခင်က deposits table တည်ဆောက်ထားပြီးပါက reject_reason column ထပ်ထည့်ပေးရန်
$check_dep_reject_reason = $conn->query("SHOW COLUMNS FROM deposits LIKE 'reject_reason'");
if ($check_dep_reject_reason && $check_dep_reject_reason->num_rows == 0) {
    $conn->query("ALTER TABLE deposits ADD reject_reason VARCHAR(255) NULL AFTER status");
    echo "<p class='success'>Table 'deposits' သို့ reject_reason column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
}

// (အရေးကြီး) ယခင်က deposits table တည်ဆောက်ထားပြီးပါက slip_image_url column ထပ်ထည့်ပေးရန်
$check_dep_slip_col = $conn->query("SHOW COLUMNS FROM deposits LIKE 'slip_image_url'");
if ($check_dep_slip_col && $check_dep_slip_col->num_rows == 0) {
    $conn->query("ALTER TABLE deposits ADD slip_image_url VARCHAR(255) NULL AFTER transaction_id");
    echo "<p class='success'>Table 'deposits' သို့ slip_image_url column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
}

// (အရေးကြီး) ယခင်က deposits table တည်ဆောက်ထားပြီးပါက payment_account_id column ထပ်ထည့်ပေးရန်
$check_dep_acc_id = $conn->query("SHOW COLUMNS FROM deposits LIKE 'payment_account_id'");
if ($check_dep_acc_id && $check_dep_acc_id->num_rows == 0) {
    $conn->query("ALTER TABLE deposits ADD payment_account_id INT NULL AFTER payment_method");
    echo "<p class='success'>Table 'deposits' သို့ payment_account_id column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
}

// ၇။ Withdrawals Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_withdraws = "CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    admin_payment_account VARCHAR(100) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reject_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_withdraws) === TRUE) {
    echo "<p>Table 'withdrawals' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Withdrawals Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// (အရေးကြီး) ယခင်က withdrawals table တည်ဆောက်ထားပြီးပါက admin_payment_account column ထပ်ထည့်ပေးရန်
$check_admin_acc = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'admin_payment_account'");
if ($check_admin_acc && $check_admin_acc->num_rows == 0) {
    $conn->query("ALTER TABLE withdrawals ADD admin_payment_account VARCHAR(100) NULL AFTER account_number");
    echo "<p class='success'>Table 'withdrawals' သို့ admin_payment_account column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
}

// (အရေးကြီး) ယခင်က withdrawals table တည်ဆောက်ထားပြီးပါက fee_amount column ထပ်ထည့်ပေးရန်
$check_fee_amount = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'fee_amount'");
if ($check_fee_amount && $check_fee_amount->num_rows == 0) {
    $conn->query("ALTER TABLE withdrawals ADD fee_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER amount");
    echo "<p class='success'>Table 'withdrawals' သို့ fee_amount column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
}

// (အရေးကြီး) ယခင်က withdrawals table တည်ဆောက်ထားပြီးပါက reject_reason column ထပ်ထည့်ပေးရန်
$check_reject_reason = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'reject_reason'");
if ($check_reject_reason && $check_reject_reason->num_rows == 0) {
    $conn->query("ALTER TABLE withdrawals ADD reject_reason VARCHAR(255) NULL AFTER status");
    echo "<p class='success'>Table 'withdrawals' သို့ reject_reason column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
}

// ၈။ Settings Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_settings = "CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if ($conn->query($sql_create_settings) === TRUE) {
    echo "<p>Table 'settings' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
    // Default Settings များကို Array ဖြင့် သတ်မှတ်ပြီး Loop ပတ်၍ ထည့်သွင်းခြင်း
    $default_settings = [
        'max_limit_per_number' => '20000',
        'max_limit_per_3d_number' => '10000',
        'home_banner_url' => '',
        'home_banner_url_2' => '',
        'home_banner_url_3' => '',
        'daily_bonus_amount' => '500',
        'daily_bonus_standard' => '500',
        'daily_bonus_bronze' => '1000',
        'daily_bonus_silver' => '2000',
        'daily_bonus_gold' => '5000',
        'daily_bonus_diamond' => '10000',
        'bet_discount_percent' => '0',
        'registration_fee' => '0',
        'blocked_2d_numbers' => '',
        'blocked_2d_morning' => '',
        'blocked_2d_evening' => '',
        'blocked_3d_numbers' => '',
        'kbz_pay_account' => '09 123 456 789',
        'kbz_pay_name' => 'U Tun Tun',
        'kbz_pay_qr_url' => '',
        'wave_pay_account' => '09 987 654 321',
        'wave_pay_name' => 'Daw Mya',
        'wave_pay_qr_url' => '',
        'referral_commission_percent' => '5',
        'mlm_level_1_percent' => '3',
        'mlm_level_2_percent' => '1.5',
        'mlm_level_3_percent' => '0.5',
        'vip_bronze_threshold' => '100000',
        'vip_silver_threshold' => '500000',
        'vip_gold_threshold' => '2000000',
        'vip_diamond_threshold' => '5000000',
        'cashback_standard_percent' => '0',
        'cashback_bronze_percent' => '3',
        'cashback_silver_percent' => '5',
        'cashback_gold_percent' => '8',
        'cashback_diamond_percent' => '10',
        'min_deposit' => '1000',
        'max_deposit' => '1000000',
        'min_withdraw' => '1000',
        'max_withdraw' => '1000000',
        'withdrawal_fee_percent' => '0',
        'telegram_bot_token' => '',
        'telegram_channel_id' => '',
        'bet_cancel_time_limit' => '10',
        'announcement_text' => '',
        'announcement_image_url' => '',
        'announcement_is_active' => '0',
        'maintenance_mode' => '0',
        'maintenance_message' => 'ဆာဗာပြုပြင်ထိန်းသိမ်းမှုများ ပြုလုပ်နေပါသည်။ ခေတ္တစောင့်ဆိုင်းပေးပါ။',
        'cs_messenger_link' => '',
        'cs_telegram_link' => '',
        'cs_viber_link' => '',
        'live_2d_api_url' => '',
        'live_3d_api_url' => '',
        'enable_dynamic_odds' => '1',
        'dynamic_odds_threshold' => '80'
    ];

    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($default_settings as $key => $value) {
        $check_setting = $conn->query("SELECT id FROM settings WHERE setting_key = '$key'");
        if ($check_setting->num_rows == 0) {
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }
    }
    $stmt->close();
    echo "<p class='success'>Default Settings များကို အောင်မြင်စွာ ထည့်သွင်းပြီးပါပြီ。</p>";
} else {
    die("<p class='error'>Settings Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၉။ System Notifications Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_notifications = "CREATE TABLE IF NOT EXISTS system_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    image_url VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_notifications) === TRUE) {
    // ယခင်က table တည်ဆောက်ထားပြီးပါက user_id column ထပ်ထည့်ပေးရန်
    $check_column = $conn->query("SHOW COLUMNS FROM system_notifications LIKE 'user_id'");
    if ($check_column && $check_column->num_rows == 0) {
        $conn->query("ALTER TABLE system_notifications ADD user_id INT DEFAULT NULL AFTER id");
        $conn->query("ALTER TABLE system_notifications ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    }

    // (အရေးကြီး) ယခင်က table တည်ဆောက်ထားပြီးပါက image_url column ထပ်ထည့်ပေးရန်
    $check_img_column = $conn->query("SHOW COLUMNS FROM system_notifications LIKE 'image_url'");
    if ($check_img_column && $check_img_column->num_rows == 0) {
        $conn->query("ALTER TABLE system_notifications ADD image_url VARCHAR(255) NULL AFTER message");
    echo "<p class='success'>Table 'system_notifications' သို့ image_url column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
    }
    echo "<p>Table 'system_notifications' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Notifications Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၁၀။ Commissions Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_commissions = "CREATE TABLE IF NOT EXISTS commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referred_user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_commissions) === TRUE) {
    echo "<p>Table 'commissions' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Commissions Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၁၁။ Support Messages Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_support = "CREATE TABLE IF NOT EXISTS support_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NULL,
    attachment_url VARCHAR(255) NULL,
    admin_reply TEXT NULL,
    admin_attachment_url VARCHAR(255) NULL,
    status ENUM('pending', 'replied') DEFAULT 'pending',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_support) === TRUE) {
    // (အရေးကြီး) ယခင်က table တည်ဆောက်ထားပြီးပါက attachment_url column ထပ်ထည့်ပေးရန်
    $check_attach_col = $conn->query("SHOW COLUMNS FROM support_messages LIKE 'attachment_url'");
    if ($check_attach_col && $check_attach_col->num_rows == 0) {
        $conn->query("ALTER TABLE support_messages ADD attachment_url VARCHAR(255) NULL AFTER message");
        $conn->query("ALTER TABLE support_messages MODIFY message TEXT NULL");
    echo "<p class='success'>Table 'support_messages' သို့ attachment_url column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
    }
    // (အရေးကြီး) ယခင်က table တည်ဆောက်ထားပြီးပါက admin_attachment_url column ထပ်ထည့်ပေးရန်
    $check_admin_attach_col = $conn->query("SHOW COLUMNS FROM support_messages LIKE 'admin_attachment_url'");
    if ($check_admin_attach_col && $check_admin_attach_col->num_rows == 0) {
        $conn->query("ALTER TABLE support_messages ADD admin_attachment_url VARCHAR(255) NULL AFTER admin_reply");
    echo "<p class='success'>Table 'support_messages' သို့ admin_attachment_url column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
    }
    // (အရေးကြီး) ယခင်က table တည်ဆောက်ထားပြီးပါက is_read column ထပ်ထည့်ပေးရန်
    $check_read_col = $conn->query("SHOW COLUMNS FROM support_messages LIKE 'is_read'");
    if ($check_read_col && $check_read_col->num_rows == 0) {
        $conn->query("ALTER TABLE support_messages ADD is_read BOOLEAN DEFAULT FALSE AFTER status");
    echo "<p class='success'>Table 'support_messages' သို့ is_read column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
    }
    echo "<p>Table 'support_messages' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Support Messages Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၁၂။ Result History Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_results = "CREATE TABLE IF NOT EXISTS result_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_number VARCHAR(10) NOT NULL,
    type ENUM('2D', '3D') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_create_results) === TRUE) {
    echo "<p>Table 'result_history' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Result History Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၁၃။ Sub-Admin Permissions Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_permissions = "CREATE TABLE IF NOT EXISTS sub_admin_permissions (
    user_id INT PRIMARY KEY,
    can_declare_result BOOLEAN NOT NULL DEFAULT FALSE,
    can_manage_transactions BOOLEAN NOT NULL DEFAULT FALSE,
    can_manage_users BOOLEAN NOT NULL DEFAULT FALSE,
    can_view_reports BOOLEAN NOT NULL DEFAULT FALSE,
    can_manage_blocked_numbers BOOLEAN NOT NULL DEFAULT FALSE,
    can_send_notifications BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_permissions) === TRUE) {
    echo "<p>Table 'sub_admin_permissions' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Sub-Admin Permissions Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၁၄။ Admin Activity Log Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_logs = "CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_logs) === TRUE) {
    echo "<p>Table 'admin_activity_logs' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Admin Activity Logs Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၁၅။ Transfers Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_transfers = "CREATE TABLE IF NOT EXISTS transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_transfers) === TRUE) {
    echo "<p>Table 'transfers' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Transfers Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၁၆။ Loans Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_loans = "CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'repaid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_loans) === TRUE) {
    echo "<p>Table 'loans' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Loans Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၁၇။ Pre-Approved Transactions Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_pre_approved = "CREATE TABLE IF NOT EXISTS pre_approved_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_method VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'used') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_create_pre_approved) === TRUE) {
    echo "<p>Table 'pre_approved_transactions' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Pre-Approved Transactions Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၁၈။ Payment Accounts Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_payment_accounts = "CREATE TABLE IF NOT EXISTS payment_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_method VARCHAR(50) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    logo_url VARCHAR(255) NULL,
    qr_image_url VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_create_payment_accounts) === TRUE) {
    echo "<p>Table 'payment_accounts' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
    
    // (အရေးကြီး) အကောင့်များကို အစီအစဉ်ပြောင်းနိုင်ရန် sort_order ထပ်ထည့်ပေးရန်
    $check_sort_order = $conn->query("SHOW COLUMNS FROM payment_accounts LIKE 'sort_order'");
    if ($check_sort_order && $check_sort_order->num_rows == 0) {
        $conn->query("ALTER TABLE payment_accounts ADD sort_order INT DEFAULT 0 AFTER is_active");
    echo "<p class='success'>Table 'payment_accounts' သို့ sort_order column ထပ်မံဖြည့်စွက်ပြီးပါပြီ。</p>";
    }

    // Default Payment Accounts များကို ထည့်သွင်းခြင်း (မရှိသေးပါက)
    $check_pay_acc = $conn->query("SELECT id FROM payment_accounts LIMIT 1");
    if ($check_pay_acc && $check_pay_acc->num_rows == 0) {
        $conn->query("INSERT INTO payment_accounts (payment_method, account_name, account_number) VALUES ('KBZ Pay', 'U Tun Tun', '09 123 456 789')");
        $conn->query("INSERT INTO payment_accounts (payment_method, account_name, account_number) VALUES ('Wave Pay', 'Daw Mya', '09 987 654 321')");
    echo "<p class='success'>Default Payment Accounts များကို အောင်မြင်စွာ ထည့်သွင်းပြီးပါပြီ。</p>";
    }
} else {
    die("<p class='error'>Payment Accounts Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၁၉။ Bonus History Table အလိုအလျောက် တည်ဆောက်ခြင်း
$sql_create_bonus = "CREATE TABLE IF NOT EXISTS bonus_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bonus_type VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_bonus) === TRUE) {
    echo "<p>Table 'bonus_history' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ (သို့) ရှိပြီးသားဖြစ်ပါသည်။</p>";
} else {
    die("<p class='error'>Bonus History Table ဖန်တီးရာတွင် အမှားအယွင်းဖြစ်နေပါသည်: " . $conn->error . "</p>");
}

// ၂၀။ Login Attempts Table တည်ဆောက်ခြင်း
$sql_create_login_attempts = "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql_create_login_attempts);

// ၂၁။ PIN Attempts Table တည်ဆောက်ခြင်း
$sql_create_pin_attempts = "CREATE TABLE IF NOT EXISTS pin_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql_create_pin_attempts) === TRUE) {
    echo "<p class='success'>Table 'pin_attempts' ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ။</p>";
}

    // လိုအပ်သော Folders များ တည်ဆောက်ခြင်း
    $dirs = ['uploads/avatars', 'uploads/slips', 'uploads/payments', 'uploads/notifications', 'uploads/support', 'uploads/banners', 'logs', 'backups'];
    foreach ($dirs as $dir) {
        if (!is_dir(__DIR__ . '/' . $dir)) {
            @mkdir(__DIR__ . '/' . $dir, 0777, true);
            echo "<p class='success'><i class='fas fa-folder-plus mr-2'></i> Directory '{$dir}' အား ဖန်တီးပြီးပါပြီ။</p>";
        }
    }

    echo "</div>"; // close log-container
    echo "<div class='mt-6 p-5 bg-green-50 rounded-xl border border-green-200 text-center shadow-sm'>";
    echo "<h3 class='text-xl font-bold text-green-700 mb-2'><i class='fas fa-check-circle mr-2'></i> Setup လုပ်ငန်းစဉ် အားလုံး အောင်မြင်စွာ ပြီးဆုံးပါပြီ။</h3>";
    echo "<p class='text-green-600 text-sm mb-5'>လုံခြုံရေးအရ ဤ <strong>setup.php</strong> ဖိုင်အား အလိုအလျောက် ဖျက်သိမ်းလိုက်ပါပြီ။</p>";
    echo "<a href='index.php' class='inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg transition shadow-md'><i class='fas fa-rocket mr-2'></i> ပင်မစာမျက်နှာသို့ သွားမည်</a>";
    echo "</div>";

    echo "</div></div></body></html>";

// Connection ကို ပိတ်ပါ
$conn->close();

// လုံခြုံရေးအရ မိမိကိုယ်ကို ဖျက်သိမ်းခြင်း (Self-Destruct)
@unlink(__FILE__);
?>