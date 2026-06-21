<?php
session_start();

// Login ဝင်ထားခြင်း မရှိပါက login.php သို့ ပြန်ပို့ရန်
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/lang/language.php';
require_once __DIR__ . '/core/security_helper.php';

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";
$step = 1; // Initial step

// CSRF Token တည်ဆောက်ခြင်း
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// လက်ရှိ User ၏ လက်ကျန်ငွေကို ဆွဲထုတ်ခြင်း
$stmt = $conn->prepare("SELECT balance, kbz_pay_number, kbz_pay_name, wave_pay_number, wave_pay_name, payment_info_json, transaction_pin, telegram_chat_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$has_pin = !empty($user['transaction_pin']);

$saved_info = [];
if (!empty($user['payment_info_json'])) {
    $saved_info = json_decode($user['payment_info_json'], true);
} else {
    if (!empty($user['kbz_pay_number'])) {
        $saved_info['KBZ Pay'] = ['number' => $user['kbz_pay_number'], 'name' => $user['kbz_pay_name']];
    }
    if (!empty($user['wave_pay_number'])) {
        $saved_info['Wave Pay'] = ['number' => $user['wave_pay_number'], 'name' => $user['wave_pay_name']];
    }
}

// Withdraw limits များကို Database မှ ဆွဲထုတ်ခြင်း
$settings_query = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('min_withdraw', 'max_withdraw', 'withdrawal_fee_percent')");
$pay_settings = [];
while ($row = $settings_query->fetch_assoc()) {
    $pay_settings[$row['setting_key']] = $row['setting_value'];
}
$min_withdraw = isset($pay_settings['min_withdraw']) ? floatval($pay_settings['min_withdraw']) : 1000;
$max_withdraw = isset($pay_settings['max_withdraw']) ? floatval($pay_settings['max_withdraw']) : 1000000;
$withdrawal_fee_percent = isset($pay_settings['withdrawal_fee_percent']) ? floatval($pay_settings['withdrawal_fee_percent']) : 0;

// Fetch Active Payment Methods
$active_methods_data = [];
$methods_stmt = @$conn->query("SELECT payment_method, MAX(logo_url) as logo_url FROM payment_accounts WHERE is_active = 1 GROUP BY payment_method ORDER BY MIN(sort_order) ASC");
if ($methods_stmt && $methods_stmt->num_rows > 0) {
    while($r = $methods_stmt->fetch_assoc()) {
        $active_methods_data[] = [
            'method' => $r['payment_method'],
            'logo' => $r['logo_url']
        ];
    }
} else {
    $active_methods_data = [
        ['method' => 'KBZ Pay', 'logo' => ''],
        ['method' => 'Wave Pay', 'logo' => '']
    ];
}

// Form Submit လုပ်လာသောအခါ
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    // CSRF Token စစ်ဆေးခြင်း
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "လုံခြုံရေးအရ အမှားအယွင်းဖြစ်ပေါ်နေပါသည်။ (CSRF Token Mismatch) ကျေးဇူးပြု၍ Page ကို Refresh လုပ်ပြီး ထပ်မံကြိုးစားပါ။";
    } else {
        $action = $_POST['action'];

        if ($action === 'request') {
            $amount = floatval($_POST['amount'] ?? 0);
            $payment_method = trim($_POST['payment_method'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');

            if ($amount < $min_withdraw || $amount > $max_withdraw) {
                $error_message = __('withdraw_amount_must_be_between') . number_format($min_withdraw) . __('and') . number_format($max_withdraw) . __('must_be_within');
            } elseif ($user['balance'] < $amount) {
                $error_message = __('insufficient_balance');
            } elseif (empty($payment_method) || empty($account_number)) {
                $error_message = __('please_fill_all_fields');
            } else {
                // Store withdrawal details in session
                $_SESSION['withdrawal_data'] = [
                    'amount' => $amount,
                    'payment_method' => $payment_method,
                    'account_number' => $account_number
                ];

                $bot_token = $pay_settings['telegram_bot_token'] ?? '';

                if (!empty($user['telegram_chat_id']) && !empty($bot_token)) {
                    // Telegram ချိတ်ဆက်ထားပါက OTP ပို့မည်
                    $otp = rand(100000, 999999);
                    $_SESSION['withdrawal_otp'] = (string)$otp;
                    $_SESSION['withdrawal_otp_expiry'] = time() + 300; // 5 mins

                    $telegram_msg = str_replace('{otp}', $otp, __('telegram_otp_message_withdraw'));
                    $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
                    $telegram_data = ['chat_id' => $user['telegram_chat_id'], 'text' => $telegram_msg, 'parse_mode' => 'Markdown'];

                    $ch = curl_init($telegram_url);
                    // ပြင်ဆင်ချက်: Timeout ကို 1.5s သို့ ပြောင်းလဲထားပါသည်
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $telegram_url, 
                        CURLOPT_POST => TRUE, 
                        CURLOPT_RETURNTRANSFER => TRUE, 
                        CURLOPT_TIMEOUT_MS => 1500, 
                        CURLOPT_NOSIGNAL => 1, 
                        CURLOPT_POSTFIELDS => http_build_query($telegram_data)
                    ]);
                    curl_exec($ch);
                    curl_close($ch);

                    $step = 2; // OTP step
                    $success_message = __('otp_sent_to_telegram');
                } elseif ($has_pin) {
                    // Telegram မရှိပါက PIN သို့ သွားမည်
                    $step = 3; // PIN step
                } else {
                    $error_message = __('pin_not_set_error');
                    unset($_SESSION['withdrawal_data']);
                }
            }
        } elseif ($action === 'verify_otp' || $action === 'verify_pin') {
            if (!isset($_SESSION['withdrawal_data'])) {
                $error_message = __('session_expired_error');
                $step = 1;
            } else {
                $is_verified = false;
                if ($action === 'verify_otp') {
                    $entered_otp = trim($_POST['otp'] ?? '');
                    if (isset($_SESSION['withdrawal_otp']) && time() < $_SESSION['withdrawal_otp_expiry'] && $entered_otp === $_SESSION['withdrawal_otp']) {
                        $is_verified = true;
                        unset($_SESSION['withdrawal_otp'], $_SESSION['withdrawal_otp_expiry']);
                    } else {
                        $error_message = __('otp_invalid_or_expired');
                        $step = 2;
                    }
                } else { // verify_pin
                    $entered_pin = trim($_POST['transaction_pin'] ?? '');
                    if (!$has_pin) {
                        $error_message = __('pin_not_set');
                    } elseif (!check_pin_rate_limit($user_id)) {
                        $error_message = __('pin_rate_limit_exceeded');
                    } elseif (password_verify($entered_pin, $user['transaction_pin'])) {
                        $is_verified = true;
                        clear_failed_pins($user_id);
                    } else {
                        record_failed_pin($user_id);
                        $error_message = __('invalid_pin');
                        $step = 3;
                    }
                }

                if ($is_verified) {
                    $w_data = $_SESSION['withdrawal_data'];
                    $amount = $w_data['amount'];
                    $payment_method = $w_data['payment_method'];
                    $account_number = $w_data['account_number'];

                    $conn->begin_transaction();
                    try {
                        $fee_amount = $amount * ($withdrawal_fee_percent / 100);
                        $net_amount = $amount - $fee_amount;

                        $update_stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                        $update_stmt->bind_param("di", $amount, $user_id);
                        $update_stmt->execute();
                        $update_stmt->close();

                        $insert_stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, fee_amount, payment_method, account_number) VALUES (?, ?, ?, ?, ?)");
                        $insert_stmt->bind_param("iddss", $user_id, $amount, $fee_amount, $payment_method, $account_number);
                        $insert_stmt->execute();
                        $insert_stmt->close();

                        $conn->commit();

                        // --- Telegram Bot သို့ Admin ထံ Notification ပို့ရန် ---
                        $bot_token = $pay_settings['telegram_bot_token'] ?? '';
                        $admin_chat_id = $pay_settings['telegram_channel_id'] ?? '';

                        if (!empty($bot_token) && !empty($admin_chat_id)) {
                            $telegram_msg = __('admin_withdraw_noti_title') . "\n\n";
                            $telegram_msg .= __('admin_withdraw_noti_user_id') . " `" . $user_id . "`\n";
                            $telegram_msg .= __('admin_withdraw_noti_amount') . " *" . number_format($amount) . "* " . __('currency') . "\n";
                            if ($fee_amount > 0) {
                                $telegram_msg .= __('admin_withdraw_noti_fee') . " *" . number_format($fee_amount) . "* " . __('currency') . "\n";
                                $telegram_msg .= __('admin_withdraw_noti_net') . " *" . number_format($net_amount) . "* " . __('currency') . "\n";
                            }
                            $telegram_msg .= __('admin_withdraw_noti_method') . " " . $payment_method . "\n";
                            $telegram_msg .= __('admin_withdraw_noti_account') . " `" . $account_number . "`\n";
                            $telegram_msg .= __('admin_withdraw_noti_time') . " " . date('Y-m-d h:i:s A') . "\n";

                            $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
                            $telegram_data = ['chat_id' => $admin_chat_id, 'text' => $telegram_msg, 'parse_mode' => 'Markdown', 'disable_notification' => false];

                            $ch = curl_init($telegram_url);
                            // ပြင်ဆင်ချက်: Timeout ကို 1.5s သို့ ပြောင်းလဲထားပါသည်
                            curl_setopt_array($ch, [
                                CURLOPT_URL => $telegram_url, 
                                CURLOPT_POST => TRUE, 
                                CURLOPT_RETURNTRANSFER => TRUE, 
                                CURLOPT_TIMEOUT_MS => 1500, 
                                CURLOPT_NOSIGNAL => 1, 
                                CURLOPT_POSTFIELDS => http_build_query($telegram_data)
                            ]);
                            curl_exec($ch);
                            curl_close($ch);
                        }

                        $success_message = __('withdraw_request_successful');
                        $step = 4;
                        unset($_SESSION['withdrawal_data']);
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_message = __('system_error_try_again');
                        $step = 1;
                        unset($_SESSION['withdrawal_data']);
                    }
                }
            }
        }
    }
}
?>

<?php 
$page_title = __('title_withdraw') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24">

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="index.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center tracking-wide"><?= __('title_withdraw') ?></h1>
        <a href="transaction_history.php?type=withdrawal" class="ml-4 text-lg md:text-xl w-6 md:w-10 text-right hover:scale-110 transition-transform" title="<?= __('view_history') ?>"><i class="fas fa-history"></i></a>
    </div>

    <div class="px-4 md:px-8 mt-4 md:mt-6 max-w-2xl mx-auto">
        <div class="bg-white p-5 md:p-6 shadow-sm md:shadow-md border-b md:border border-gray-100 md:rounded-2xl flex flex-col md:flex-row md:justify-between md:items-center">
            <p class="text-gray-500 text-sm md:text-base font-bold mb-1 md:mb-0 uppercase tracking-wide flex items-center"><i class="fas fa-wallet text-primary mr-2"></i> <?= __('balance') ?></p>
            <p class="text-3xl md:text-4xl font-bold text-primary tracking-tight"><?= number_format($user['balance'], 2) ?> <span class="text-base md:text-lg font-normal text-gray-400"><?= __('currency') ?></span></p>
        </div>
    </div>

    <div class="p-4 md:p-8 pt-2 md:pt-4 max-w-2xl mx-auto w-full">
        <?php if ($step === 4 && !empty($success_message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl relative mb-5 text-sm md:text-base font-bold shadow-sm text-center">
                <i class="fas fa-check-circle text-green-500 text-2xl mb-2 block"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message) && $step !== 4): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl relative mb-5 text-sm md:text-base font-medium shadow-sm flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <form method="POST" action="" class="bg-white p-6 md:p-10 rounded-2xl md:rounded-3xl shadow-lg border border-gray-100 border-t-4 border-t-primary">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="request">
            
            <div class="mb-6 md:mb-8">
                <label class="block text-gray-700 text-sm md:text-base font-bold mb-3 md:mb-4"><i class="fas fa-university text-primary mr-1.5"></i> <?= __('withdrawal_method') ?></label>
                <?php if (count($active_methods_data) > 0): ?>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4">
                        <?php foreach($active_methods_data as $method): ?>
                        <label class="cursor-pointer relative group">
                            <input type="radio" name="payment_method" value="<?= htmlspecialchars($method['method']) ?>" class="peer hidden" required onchange="autofillAccountNumber(this.value)" <?= (isset($_SESSION['withdrawal_data']['payment_method']) && $_SESSION['withdrawal_data']['payment_method'] === $method['method']) ? 'checked' : '' ?>>
                            <div class="border border-gray-200 rounded-xl p-3 md:p-4 flex flex-col items-center gap-2 group-hover:bg-blue-50 group-hover:border-blue-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:shadow-md transition-all duration-300">
                                <?php if(!empty($method['logo'])): ?>
                                    <img src="<?= htmlspecialchars($method['logo']) ?>" class="w-10 h-10 md:w-12 md:h-12 object-cover rounded-full shadow-sm group-hover:scale-105 transition-transform">
                                <?php else: ?>
                                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-blue-100 text-blue-500 flex items-center justify-center group-hover:scale-105 transition-transform"><i class="fas fa-university md:text-lg"></i></div>
                                <?php endif; ?>
                                <span class="text-sm md:text-base font-bold text-gray-700 text-center"><?= htmlspecialchars($method['method']) ?></span>
                                <div class="absolute top-2 right-2 text-blue-500 hidden peer-checked:block"><i class="fas fa-check-circle md:text-lg"></i></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm md:text-base text-red-500 italic text-center py-5 md:py-6 bg-red-50 rounded-xl"><?= __('no_withdrawal_methods_available') ?></p>
                <?php endif; ?>
            </div>

            <div class="mb-6 md:mb-8">
                <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 md:mb-3"><?= __('withdrawal_account_number') ?></label>
                <input type="text" name="account_number" placeholder="<?= __('example_phone') ?>" class="w-full py-3.5 md:py-4 px-4 md:px-5 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none text-sm md:text-base text-gray-700 transition-all font-bold" required>
            </div>
            
            <div class="mb-8 md:mb-10">
                <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 md:mb-3"><?= __('withdraw_amount_ks') ?></label>
                <input type="number" id="amount" name="amount" min="<?= $min_withdraw ?>" max="<?= $max_withdraw ?>" placeholder="<?= str_replace('%amount%', number_format($min_withdraw), __('min_withdraw_placeholder')) ?>" class="w-full py-3.5 md:py-4 px-4 md:px-5 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none text-lg md:text-xl font-bold transition-all text-primary" required oninput="calculateWithdrawal()">
                
                <?php if ($withdrawal_fee_percent > 0): ?>
                    <div class="bg-red-50 p-4 mt-4 rounded-xl border border-red-100 text-sm md:text-base shadow-inner">
                        <div class="flex justify-between text-red-600 mb-2 items-center">
                            <span class="font-medium"><?= str_replace('%percent%', $withdrawal_fee_percent, __('withdrawal_fee_percent_label')) ?></span>
                            <span id="fee_display" class="font-bold text-lg md:text-xl">0 <?= __('currency') ?></span>
                        </div>
                        <div class="flex justify-between text-green-700 font-bold border-t border-red-200/50 pt-3 mt-2 items-center">
                            <span><?= __('net_amount_to_receive') ?></span>
                            <span id="net_display" class="text-xl md:text-2xl tracking-tight">0 <?= __('currency') ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3.5 md:py-4 rounded-xl text-lg md:text-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
                <?= __('continue') ?> <i class="fas fa-arrow-right ml-1.5"></i>
            </button>
        </form>

        <?php elseif ($step === 2): // OTP Verification Step ?>
            <form method="POST" action="" class="bg-white p-6 md:p-10 rounded-2xl md:rounded-3xl shadow-lg border border-gray-100 max-w-md mx-auto">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="verify_otp">
                <div class="text-center mb-6 md:mb-8">
                    <div class="w-16 h-16 md:w-20 md:h-20 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm border border-blue-100"><i class="fab fa-telegram-plane text-3xl md:text-4xl"></i></div>
                    <p class="text-sm md:text-base text-gray-600 font-medium"><?= __('enter_otp_from_telegram') ?></p>
                </div>
                <div class="mb-6 md:mb-8">
                    <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 md:mb-3"><?= __('otp_code') ?></label>
                    <input class="w-full py-3.5 md:py-4 px-4 border border-blue-200 bg-blue-50/50 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none text-center tracking-[0.5em] text-xl md:text-2xl font-bold font-mono transition-all text-primary" name="otp" type="text" placeholder="------" maxlength="6" required autocomplete="off">
                </div>
                <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3.5 md:py-4 px-4 rounded-xl text-lg md:text-xl shadow-md hover:shadow-lg transition-all duration-300 hover:-translate-y-0.5 mb-4">
                    <i class="fas fa-check-circle mr-1.5"></i> <?= __('confirm') ?>
                </button>
                <div class="text-center mt-5 md:mt-6 border-t border-gray-100 pt-4">
                    <a href="withdraw.php" class="inline-flex items-center text-primary text-sm md:text-base font-bold hover:underline transition-colors"><i class="fas fa-redo-alt mr-1.5"></i> <?= __('start_over') ?></a>
                </div>
            </form>

        <?php elseif ($step === 3): // PIN Verification Step ?>
            <form method="POST" action="" class="bg-white p-6 md:p-10 rounded-2xl md:rounded-3xl shadow-lg border border-gray-100 max-w-md mx-auto">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="verify_pin">
                <div class="text-center mb-6 md:mb-8">
                    <div class="w-16 h-16 md:w-20 md:h-20 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm border border-blue-100"><i class="fas fa-shield-alt text-3xl md:text-4xl"></i></div>
                    <p class="text-sm md:text-base text-gray-600 font-medium"><?= __('enter_pin_for_withdrawal') ?></p>
                </div>
                <div class="mb-6 md:mb-8">
                    <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 md:mb-3"><?= __('security_pin') ?></label>
                    <input class="w-full py-3.5 md:py-4 px-4 border border-blue-200 bg-blue-50/50 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none text-center tracking-[0.5em] text-xl md:text-2xl font-bold font-mono transition-all text-primary" name="transaction_pin" type="password" placeholder="••••••" maxlength="6" required autocomplete="off">
                </div>
                <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3.5 md:py-4 px-4 rounded-xl text-lg md:text-xl shadow-md hover:shadow-lg transition-all duration-300 hover:-translate-y-0.5 mb-4">
                    <i class="fas fa-check-circle mr-1.5"></i> <?= __('confirm') ?>
                </button>
                <div class="flex justify-between items-center mt-5 md:mt-6 border-t border-gray-100 pt-4 px-2">
                    <a href="withdraw.php" class="text-primary text-sm md:text-base font-bold hover:underline"><i class="fas fa-redo-alt mr-1"></i> <?= __('start_over') ?></a>
                    <a href="forgot_pin.php" class="text-red-500 text-sm md:text-base font-bold hover:underline"><i class="fas fa-question-circle mr-1"></i> <?= __('forgot_pin') ?></a>
                </div>
            </form>

        <?php elseif ($step === 4): // Success Step ?>
            <div class="text-center bg-white p-8 md:p-12 rounded-2xl md:rounded-3xl shadow-lg border border-gray-100 max-w-lg mx-auto">
                <div class="text-green-500 text-6xl md:text-7xl mb-5 md:mb-6 animate-bounce"><i class="fas fa-check-circle"></i></div>
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3 md:mb-4"><?= __('success') ?></h2>
                <p class="text-sm md:text-base text-gray-600 mb-8 md:mb-10 font-medium leading-relaxed"><?= htmlspecialchars($success_message) ?></p>
                <a href="index.php" class="bg-primary hover:bg-blue-800 text-white font-bold py-3.5 md:py-4 px-8 rounded-xl w-full inline-block shadow-md hover:shadow-lg transition-all duration-300 hover:-translate-y-0.5 text-lg md:text-xl">
                    <i class="fas fa-home mr-1.5"></i> <?= __('back_to_home') ?>
                </a>
            </div>
            
            <audio id="successSound" src="assets/sounds/notification.mp3" autoplay></audio>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var snd = document.getElementById('successSound');
                    if (snd) { snd.play().catch(e => console.log("Autoplay prevented.")); }
                    if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
                });
            </script>
        <?php endif; ?>
    </div>

    <script>
        const withdrawalFeePercent = <?= $withdrawal_fee_percent ?>;
        const userAccounts = {
            <?php
            $acc_js = [];
            foreach ($active_methods_data as $method) {
                $m_name = $method['method'];
                $m_full = trim(($saved_info[$m_name]['number'] ?? '') . (!empty($saved_info[$m_name]['name']) ? ' (' . $saved_info[$m_name]['name'] . ')' : ''));
                $acc_js[] = "'" . addslashes($m_name) . "': '" . addslashes($m_full) . "'";
            }
            echo implode(",\n            ", $acc_js);
            ?>
        };

        function autofillAccountNumber(paymentMethod) {
            const accountNumberInput = document.querySelector('input[name="account_number"]');
            if (accountNumberInput && userAccounts[paymentMethod]) {
                accountNumberInput.value = userAccounts[paymentMethod];
            }
        }

        function calculateWithdrawal() {
            if (withdrawalFeePercent <= 0) return;
            const amountInput = document.querySelector('input[name="amount"]');
            let amount = parseFloat(amountInput.value) || 0;
            let fee = amount * (withdrawalFeePercent / 100);
            let net = amount - fee;
            
            const feeDisplay = document.getElementById('fee_display');
            const netDisplay = document.getElementById('net_display');
            
            if (feeDisplay) feeDisplay.innerText = fee.toLocaleString() + ' <?= __('currency') ?>';
            if (netDisplay) netDisplay.innerText = net.toLocaleString() + ' <?= __('currency') ?>';
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>