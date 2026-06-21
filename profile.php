<?php
session_start();

// Login ဝင်ထားခြင်း မရှိပါက login.php သို့ ပြန်ပို့ရန်
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/lang/language.php';

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Form မှ Data များ Submit လုပ်လာသောအခါ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');

    if (empty($username) || empty($phone_number)) {
        $error_message .= __('name_phone_required');
    } else {
        // ဖုန်းနံပါတ်သည် အခြားအကောင့်တွင် အသုံးပြုထားခြင်း ရှိ/မရှိ စစ်ဆေးခြင်း
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE phone_number = ? AND id != ?");
        $check_stmt->bind_param("si", $phone_number, $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error_message .= __('phone_already_used');
        }
        $check_stmt->close();

        // အမှားအယွင်းမရှိမှသာ Database ကို Update လုပ်မည်
        if (empty($error_message)) {
            // Update လုပ်မည့် Query နှင့် Parameters များကို တည်ဆောက်ခြင်း
            $sql_parts = ["username = ?", "telegram_chat_id = ?", "phone_number = ?"];
            $types = "sss";
            $params = [$username, $telegram_chat_id, $phone_number];
            
            $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
            $types .= "i";
            $params[] = $user_id;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $_SESSION['username'] = $username;
                $success_message .= __('profile_updated_successfully');

                // Telegram ID ထည့်သွင်းထားပါက Test Message ပို့စမ်းသပ်မည်
                if (!empty($telegram_chat_id)) {
                    $tg_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_bot_token'");
                    $bot_token = $tg_stmt->fetch_assoc()['setting_value'] ?? '';

                    if (!empty($bot_token)) {
                        $telegram_msg = str_replace('{username}', $username, __('telegram_test_message_text'));
                        
                        $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
                        $telegram_data = [
                            'chat_id' => $telegram_chat_id,
                            'text' => $telegram_msg,
                            'parse_mode' => 'Markdown'
                        ];

                        $ch = curl_init($telegram_url);
                        curl_setopt_array($ch, [CURLOPT_URL => $telegram_url, CURLOPT_POST => TRUE, CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 3, CURLOPT_POSTFIELDS => http_build_query($telegram_data)]);
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($http_code == 200) {
                            $success_message .= "<br><span class='block mt-2'><i class='fab fa-telegram mr-1'></i> " . __('telegram_test_message_sent') . "</span>";
                        } else {
                            $error_message .= "<br><span class='block mt-2'><i class='fas fa-exclamation-triangle mr-1'></i> " . __('telegram_test_message_failed') . "</span>";
                        }
                    }
                }
            } else {
                $error_message .= __('update_error');
            }
            $stmt->close();
        }
    }
}

// လက်ရှိ User ၏ အချက်အလက်များကို Database မှ ဆွဲထုတ်ခြင်း (Form တွင် မူလအချက်အလက်ပြသရန်)
$stmt = $conn->prepare("SELECT username, phone_number, referral_code, avatar, kbz_pay_number, wave_pay_number, vip_level, telegram_chat_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// တစ်သက်တာ အရှုံး/အမြတ် တွက်ချက်ခြင်း (Lifetime Summary)
$lifetime_stmt = $conn->prepare("
    SELECT 
        SUM(amount - IFNULL(discount_amount, 0)) as total_bet,
        SUM(CASE WHEN status = 'win' AND LENGTH(bet_number) = 2 THEN amount * IFNULL(odds, 80) 
                 WHEN status = 'win' AND LENGTH(bet_number) = 3 THEN amount * IFNULL(odds, 500) 
                 ELSE 0 END) as total_win
    FROM bets 
    WHERE user_id = ?
");
$lifetime_stmt->bind_param("i", $user_id);
$lifetime_stmt->execute();
$lifetime_res = $lifetime_stmt->get_result()->fetch_assoc();
$lifetime_bet = floatval($lifetime_res['total_bet'] ?? 0);
$lifetime_win = floatval($lifetime_res['total_win'] ?? 0);
$lifetime_profit = $lifetime_win - $lifetime_bet;
$lifetime_stmt->close();

// Get VIP Settings to show progress
$vip_settings_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'vip_%'");
$vip_thresholds = [];
while ($row = $vip_settings_stmt->fetch_assoc()) {
    $vip_thresholds[$row['setting_key']] = floatval($row['setting_value']);
}

$next_level = 'Bronze';
$next_threshold = $vip_thresholds['vip_bronze_threshold'] ?? 100000;
$current_level = $user_data['vip_level'] ?? 'Standard';

if ($current_level === 'Bronze') {
    $next_level = 'Silver';
    $next_threshold = $vip_thresholds['vip_silver_threshold'] ?? 500000;
} elseif ($current_level === 'Silver') {
    $next_level = 'Gold';
    $next_threshold = $vip_thresholds['vip_gold_threshold'] ?? 2000000;
} elseif ($current_level === 'Gold') {
    $next_level = 'Diamond';
    $next_threshold = $vip_thresholds['vip_diamond_threshold'] ?? 5000000;
} elseif ($current_level === 'Diamond') {
    $next_level = 'Max';
    $next_threshold = $lifetime_bet; // Maxed out
}

$progress_percent = min(100, ($lifetime_bet / $next_threshold) * 100);
if ($next_level === 'Max') $progress_percent = 100;
?>

<?php 
$page_title = __('edit_profile') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="w-full md:max-w-4xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24">

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="index.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center pr-6 md:pr-10 tracking-wide"><?= __('edit_profile') ?></h1>
    </div>

    <div class="p-4 md:p-8">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 md:py-4 rounded-xl relative mb-5 text-sm md:text-base font-medium shadow-sm"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 md:py-4 rounded-xl relative mb-5 text-sm md:text-base font-medium shadow-sm"><?= $error_message ?></div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 md:gap-6">
            
            <div class="md:col-span-7 lg:col-span-8 flex flex-col gap-4 md:gap-6">
                
                <div class="bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 rounded-2xl p-5 md:p-6 shadow-lg text-white transform hover:scale-[1.02] transition-transform duration-300">
                    <div class="flex justify-between items-center mb-4">
                        <div class="font-bold text-lg md:text-xl flex items-center"><i class="fas fa-crown mr-2.5 text-2xl drop-shadow-md"></i><?= htmlspecialchars($current_level) ?> <?= __('level') ?></div>
                        <div class="text-xs md:text-sm bg-black bg-opacity-25 px-3 py-1.5 rounded-lg font-medium backdrop-blur-sm shadow-inner"><?= __('total_bets') ?> <span class="font-bold ml-1"><?= number_format($lifetime_bet) ?></span></div>
                    </div>
                    
                    <?php if ($next_level !== 'Max'): ?>
                    <div class="text-sm md:text-base font-bold mb-2">
                        <?= __('next_level') ?> <?= $next_level ?> <span class="text-yellow-100 font-normal text-xs md:text-sm ml-1">(<?= number_format($next_threshold) ?> <?= __('currency') ?>)</span>
                    </div>
                    <div class="w-full bg-yellow-300/40 rounded-full h-2.5 md:h-3 mb-2 shadow-inner overflow-hidden">
                        <div class="bg-white h-2.5 md:h-3 rounded-full shadow-md relative" style="width: <?= $progress_percent ?>%">
                            <div class="absolute top-0 right-0 bottom-0 w-4 bg-white/50 blur-[2px]"></div>
                        </div>
                    </div>
                    <div class="text-[10px] md:text-xs text-right text-yellow-50 font-medium">
                        <?= number_format($next_threshold - $lifetime_bet) ?> <?= __('needed_for_next_level') ?>
                    </div>
                    <?php else: ?>
                    <div class="text-sm md:text-base font-bold text-center mt-3 bg-white/20 py-2 rounded-lg backdrop-blur-sm">
                        <i class="fas fa-star text-yellow-200 mr-1"></i> <?= __('max_vip_reached') ?> <i class="fas fa-star text-yellow-200 ml-1"></i>
                    </div>
                    <?php endif; ?>
                </div>

                <form method="POST" action="" class="bg-white p-6 md:p-8 rounded-2xl shadow-md border border-gray-100">
                    
                    <div class="text-center mb-8">
                        <div class="relative w-24 h-24 md:w-32 md:h-32 mx-auto mb-3">
                            <?php if (!empty($user_data['avatar'])): ?>
                                <img src="<?= htmlspecialchars($user_data['avatar']) ?>" alt="Avatar" class="w-full h-full rounded-full object-cover shadow-lg border-4 border-white">
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-primary to-blue-600 text-white rounded-full flex items-center justify-center shadow-lg border-4 border-white">
                                    <i class="fas fa-user text-4xl md:text-5xl"></i>
                                </div>
                            <?php endif; ?>
                            
                            <a href="update_avatar.php" class="absolute bottom-0 right-0 md:bottom-1 md:right-1 bg-blue-600 text-white w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center cursor-pointer shadow-md hover:bg-blue-700 hover:scale-110 transition-all border-2 border-white">
                                <i class="fas fa-camera text-sm md:text-base"></i>
                            </a>
                        </div>
                        <p class="text-xs md:text-sm text-gray-500 font-bold"><?= __('change_profile_picture') ?></p>
                    </div>

                    <div class="mb-5 md:mb-6">
                        <label class="block text-gray-700 text-sm md:text-base font-bold mb-2"><?= __('phone_number') ?></label>
                        <input type="text" name="phone_number" value="<?= htmlspecialchars($user_data['phone_number']) ?>" class="w-full py-3 md:py-4 px-4 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none text-gray-700 font-bold transition-all text-sm md:text-base" required>
                    </div>
                    
                    <div class="mb-5 md:mb-6">
                        <label class="block text-gray-700 text-sm md:text-base font-bold mb-2"><?= __('username') ?></label>
                        <input type="text" name="username" value="<?= htmlspecialchars($user_data['username']) ?>" class="w-full py-3 md:py-4 px-4 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none text-gray-700 font-bold transition-all text-sm md:text-base" required>
                    </div>

                    <div class="mb-6 md:mb-8">
                        <label class="block text-gray-700 text-sm md:text-base font-bold mb-2"><i class="fab fa-telegram text-blue-500 mr-1"></i> <?= __('telegram_chat_id_optional') ?></label>
                        <input type="text" name="telegram_chat_id" value="<?= htmlspecialchars($user_data['telegram_chat_id'] ?? '') ?>" placeholder="<?= __('telegram_chat_id_placeholder') ?>" class="w-full py-3 md:py-4 px-4 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none text-gray-700 font-mono transition-all text-sm md:text-base">
                        <p class="text-[10px] md:text-xs text-gray-500 mt-1.5 font-medium"><?= __('telegram_chat_id_help') ?></p>
                    </div>

                    <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3 md:py-4 rounded-xl text-lg md:text-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
                        <i class="fas fa-save mr-1.5"></i> <?= __('save') ?>
                    </button>
                </form>

                <div class="bg-white p-5 md:p-6 rounded-2xl shadow-md border border-gray-100 border-l-4 <?= $lifetime_profit >= 0 ? 'border-green-500' : 'border-red-500' ?>">
                    <h3 class="font-bold text-gray-700 mb-3 md:mb-4 border-b pb-3 text-sm md:text-base flex items-center">
                        <i class="fas fa-chart-line mr-2 <?= $lifetime_profit >= 0 ? 'text-green-500' : 'text-red-500' ?> text-lg"></i> 
                        <?= __('lifetime_summary') ?>
                    </h3>
                    <div class="space-y-2 md:space-y-3 bg-gray-50/50 p-3 md:p-4 rounded-xl">
                        <div class="flex justify-between items-center">
                            <span class="text-sm md:text-base text-gray-500 font-medium"><?= __('total_bet_amount') ?></span>
                            <span class="text-sm md:text-base font-bold text-red-500">- <?= number_format($lifetime_bet) ?> <?= __('currency') ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm md:text-base text-gray-500 font-medium"><?= __('total_win_amount') ?></span>
                            <span class="text-sm md:text-base font-bold text-green-600">+ <?= number_format($lifetime_win) ?> <?= __('currency') ?></span>
                        </div>
                        <div class="flex justify-between items-center border-t border-gray-200 pt-3 md:pt-4 mt-1 md:mt-2">
                            <span class="text-sm md:text-base font-bold text-gray-700 uppercase tracking-wide"><?= __('net_profit_loss') ?></span>
                            <span class="text-base md:text-xl font-bold <?= $lifetime_profit >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $lifetime_profit > 0 ? '+' : '' ?><?= number_format($lifetime_profit) ?> <?= __('currency') ?>
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-5 text-center border-t border-gray-100 pt-4 md:pt-5">
                        <a href="export_history.php" class="inline-block bg-white border border-gray-200 text-gray-700 hover:bg-gray-100 hover:border-gray-300 text-xs md:text-sm font-bold py-2 md:py-2.5 px-5 md:px-6 rounded-lg shadow-sm transition-all duration-300 hover:-translate-y-0.5">
                            <i class="fas fa-file-excel mr-1.5 text-green-600"></i> <?= __('download_all_history') ?>
                        </a>
                    </div>
                </div>

            </div>

            <div class="md:col-span-5 lg:col-span-4 flex flex-col gap-3 md:gap-4 mt-2 md:mt-0">
                
                <h3 class="font-bold text-gray-800 text-sm md:text-base px-2 hidden md:block border-b border-gray-200 pb-2 mb-2"><?= __('account_settings') ?? 'Account Settings' ?></h3>

                <a href="payment_accounts.php" class="group bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center hover:bg-blue-50/50 hover:border-blue-100 hover:shadow-md transition-all duration-300">
                    <div>
                        <p class="font-bold text-gray-800 md:text-lg group-hover:text-primary transition-colors"><i class="fas fa-wallet text-primary mr-2 md:mr-3"></i> <?= __('withdrawal_accounts') ?></p>
                        <p class="text-xs md:text-sm text-gray-500 mt-1 md:mt-1.5 ml-6 md:ml-8"><?= __('withdrawal_accounts_desc') ?></p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-primary group-hover:translate-x-1 transition-all"></i>
                </a>

                <a href="referral.php" class="group bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center hover:bg-blue-50/50 hover:border-blue-100 hover:shadow-md transition-all duration-300">
                    <div>
                        <p class="font-bold text-gray-800 md:text-lg group-hover:text-primary transition-colors"><i class="fas fa-share-alt text-primary mr-2 md:mr-3"></i> <?= __('referral_code') ?></p>
                        <p class="text-xs md:text-sm text-gray-500 mt-1 md:mt-1.5 ml-6 md:ml-8"><?= __('referral_code_desc') ?></p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-primary group-hover:translate-x-1 transition-all"></i>
                </a>

                <a href="setup_pin.php" class="group bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center hover:bg-blue-50/50 hover:border-blue-100 hover:shadow-md transition-all duration-300">
                    <div>
                        <p class="font-bold text-gray-800 md:text-lg group-hover:text-primary transition-colors"><i class="fas fa-shield-alt text-primary mr-2 md:mr-3"></i> <?= __('setup_security_pin') ?></p>
                        <p class="text-xs md:text-sm text-gray-500 mt-1 md:mt-1.5 ml-6 md:ml-8"><?= __('setup_security_pin_desc') ?></p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-primary group-hover:translate-x-1 transition-all"></i>
                </a>

                <a href="change_password.php" class="group bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center hover:bg-blue-50/50 hover:border-blue-100 hover:shadow-md transition-all duration-300">
                    <div>
                        <p class="font-bold text-gray-800 md:text-lg group-hover:text-primary transition-colors"><i class="fas fa-lock text-primary mr-2 md:mr-3"></i> <?= __('change_password') ?></p>
                        <p class="text-xs md:text-sm text-gray-500 mt-1 md:mt-1.5 ml-6 md:ml-8"><?= __('change_password_desc') ?></p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-primary group-hover:translate-x-1 transition-all"></i>
                </a>

                <a href="setup_2fa.php" class="group bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center hover:bg-green-50/50 hover:border-green-100 hover:shadow-md transition-all duration-300">
                    <div>
                        <p class="font-bold text-gray-800 md:text-lg group-hover:text-green-600 transition-colors"><i class="fas fa-shield-check text-green-500 mr-2 md:mr-3"></i> <?= __('2fa_security') ?></p>
                        <p class="text-xs md:text-sm text-gray-500 mt-1 md:mt-1.5 ml-6 md:ml-8"><?= __('2fa_security_desc') ?></p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-green-500 group-hover:translate-x-1 transition-all"></i>
                </a>

                <a href="transaction_history.php" class="group bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center hover:bg-blue-50/50 hover:border-blue-100 hover:shadow-md transition-all duration-300">
                    <div>
                        <p class="font-bold text-gray-800 md:text-lg group-hover:text-primary transition-colors"><i class="fas fa-exchange-alt text-primary mr-2 md:mr-3"></i> <?= __('tx_history') ?></p>
                        <p class="text-xs md:text-sm text-gray-500 mt-1 md:mt-1.5 ml-6 md:ml-8"><?= __('tx_history_desc') ?></p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-primary group-hover:translate-x-1 transition-all"></i>
                </a>

                <a href="support.php" class="group bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center hover:bg-blue-50/50 hover:border-blue-100 hover:shadow-md transition-all duration-300 mb-2 md:mb-0">
                    <div>
                        <p class="font-bold text-gray-800 md:text-lg group-hover:text-primary transition-colors"><i class="fas fa-headset text-primary mr-2 md:mr-3"></i> <?= __('support_contact') ?></p>
                        <p class="text-xs md:text-sm text-gray-500 mt-1 md:mt-1.5 ml-6 md:ml-8"><?= __('support_contact_desc') ?></p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-primary group-hover:translate-x-1 transition-all"></i>
                </a>

            </div>
        </div> </div> <?php require_once __DIR__ . '/includes/footer.php'; ?>