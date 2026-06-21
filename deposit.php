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
$step = 1; // Initial step

// CSRF Token တည်ဆောက်ခြင်း
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$settings_query = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('min_deposit', 'max_deposit', 'telegram_bot_token', 'telegram_channel_id')");
$pay_settings = [];
while ($row = $settings_query->fetch_assoc()) {
    $pay_settings[$row['setting_key']] = $row['setting_value'];
}
$min_deposit = isset($pay_settings['min_deposit']) ? floatval($pay_settings['min_deposit']) : 1000;
$max_deposit = isset($pay_settings['max_deposit']) ? floatval($pay_settings['max_deposit']) : 1000000;

$acc_stmt = $conn->query("SELECT * FROM payment_accounts WHERE is_active = 1 ORDER BY sort_order ASC");
$payment_accounts = $acc_stmt ? $acc_stmt->fetch_all(MYSQLI_ASSOC) : [];

// Form Submit လုပ်လာသောအခါ
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    // CSRF Token စစ်ဆေးခြင်း
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "လုံခြုံရေးအရ အမှားအယွင်းဖြစ်ပေါ်နေပါသည်။ (CSRF Token Mismatch) ကျေးဇူးပြု၍ Page ကို Refresh လုပ်ပြီး ထပ်မံကြိုးစားပါ။";
    } else {
        $action = $_POST['action'];

        if ($action === 'step1') {
            $amount = floatval($_POST['amount'] ?? 0);
            $payment_method = trim($_POST['payment_method'] ?? '');

            if ($amount < $min_deposit || $amount > $max_deposit) {
                $error_message = __('deposit_amount_must_be_between') . number_format($min_deposit) . __('and') . number_format($max_deposit) . __('must_be_within');
            } elseif (empty($payment_method)) {
                $error_message = __('please_select_payment_method');
            } else {
                $_SESSION['deposit_data'] = [
                    'amount' => $amount,
                    'payment_method' => $payment_method
                ];
                $step = 2; // Proceed to next step
            }
        } elseif ($action === 'step2') {
            if (isset($_POST['back'])) {
                $step = 1;
            } elseif (!isset($_SESSION['deposit_data'])) {
                $error_message = __('session_expired_error');
                $step = 1;
            } else {
                $amount = $_SESSION['deposit_data']['amount'];
                $payment_method = $_SESSION['deposit_data']['payment_method'];
                $transaction_id = trim($_POST['transaction_id'] ?? '');

                if (empty($transaction_id)) {
                    $error_message = __('transaction_id_required');
                    $step = 2;
                } else {
                    $slip_image_url = null;
                    if (isset($_FILES['slip_image']) && $_FILES['slip_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = 'uploads/slips/';
                        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                        $ext = strtolower(pathinfo($_FILES['slip_image']['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $new_filename = 'slip_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $ext;
                            $upload_path = $upload_dir . $new_filename;
                            require_once __DIR__ . '/core/image_helper.php';
                            if (compressImage($_FILES['slip_image']['tmp_name'], $upload_path, 60)) {
                                $slip_image_url = $upload_path;
                            }
                        }
                    }
            
                    if (empty($slip_image_url)) {
                        $error_message = __('slip_upload_error');
                        $step = 2;
                    } else {
                        // Auto-Approve စစ်ဆေးခြင်း
                        $is_auto_approved = false;
                        $pre_approved_id = 0;
                        $check_auto = $conn->prepare("SELECT id, amount FROM pre_approved_transactions WHERE payment_method = ? AND transaction_id = ? AND status = 'pending'");
                        $check_auto->bind_param("ss", $payment_method, $transaction_id);
                        $check_auto->execute();
                        $res_auto = $check_auto->get_result();
                        if ($row_auto = $res_auto->fetch_assoc()) {
                            if (floatval($row_auto['amount']) == $amount) {
                                $is_auto_approved = true;
                                $pre_approved_id = $row_auto['id'];
                            }
                        }
                        $check_auto->close();

                        $initial_status = $is_auto_approved ? 'approved' : 'pending';
                        $stmt = $conn->prepare("INSERT INTO deposits (user_id, amount, payment_method, transaction_id, slip_image_url, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("idssss", $user_id, $amount, $payment_method, $transaction_id, $slip_image_url, $initial_status);
                        
                        if ($stmt->execute()) {
                            if ($is_auto_approved) {
                                // Auto-Approve ဖြစ်ပါက User ၏ Balance သို့ ငွေချက်ချင်းပေါင်းထည့်မည်
                                $upd_pre = $conn->prepare("UPDATE pre_approved_transactions SET status = 'used' WHERE id = ?");
                                $upd_pre->bind_param("i", $pre_approved_id);
                                $upd_pre->execute();
                                $upd_pre->close();

                                $upd_user = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                                $upd_user->bind_param("di", $amount, $user_id);
                                $upd_user->execute();
                                $upd_user->close();
                                
                                $noti_msg = str_replace('%amount%', number_format($amount), __('deposit_auto_approved_noti'));
                                $stmt_noti = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                                $stmt_noti->bind_param("is", $user_id, $noti_msg);
                                $stmt_noti->execute();
                                $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = $user_id");
                                
                                $success_message = __('deposit_auto_approved_success');
                            } else {
                                // Pending အနေဖြင့်သာ ဝင်မည်ဆိုပါက Admin ထံ Notification ပို့မည်
                                $bot_token = $pay_settings['telegram_bot_token'] ?? '';
                                $admin_chat_id = $pay_settings['telegram_channel_id'] ?? '';
                                if (!empty($bot_token) && !empty($admin_chat_id)) {
                                    $telegram_msg = __('admin_deposit_noti_title') . "\n\n" .
                                                    __('admin_deposit_noti_user_id') . " `" . $user_id . "`\n" .
                                                    __('admin_deposit_noti_amount') . " *" . number_format($amount) . "* " . __('currency') . "\n" .
                                                    __('admin_deposit_noti_method') . " " . $payment_method . "\n" .
                                                    __('admin_deposit_noti_trx_id') . " `" . $transaction_id . "`\n" .
                                                    __('admin_deposit_noti_time') . " " . date('Y-m-d h:i:s A') . "\n" .
                                                    __('admin_deposit_noti_slip_info');
                                    $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
                                    $ch = curl_init($telegram_url); 
                                    // ပြင်ဆင်ချက်: Timeout ကို 1.5s သို့ ပြောင်းလဲထားပါသည်
                                    curl_setopt_array($ch, [
                                        CURLOPT_URL => $telegram_url, 
                                        CURLOPT_POST => TRUE, 
                                        CURLOPT_RETURNTRANSFER => TRUE, 
                                        CURLOPT_TIMEOUT_MS => 1500, 
                                        CURLOPT_NOSIGNAL => 1, 
                                        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $admin_chat_id, 'text' => $telegram_msg, 'parse_mode' => 'Markdown', 'disable_notification' => false])
                                    ]); 
                                    curl_exec($ch); 
                                    curl_close($ch);
                                }
                                $success_message = __('deposit_request_successful');
                            }
                            $step = 3;
                            unset($_SESSION['deposit_data']);
                        } else {
                            $error_message = __('system_error_try_again');
                            $step = 2;
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}
?>

<?php 
$page_title = __('title_deposit') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24">

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-10 shadow-md">
        <a href="index.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center tracking-wide"><?= __('title_deposit') ?></h1>
        <a href="transaction_history.php?type=deposit" class="ml-4 text-lg md:text-xl w-6 md:w-10 text-right hover:scale-110 transition-transform" title="<?= __('view_history') ?>"><i class="fas fa-history"></i></a>
    </div>

    <div class="p-4 md:p-8 max-w-2xl mx-auto">
        <?php if ($step === 3 && !empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 md:py-4 rounded-xl relative mb-5 text-sm md:text-base font-medium shadow-sm">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message) && $step !== 3): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 md:py-4 rounded-xl relative mb-5 text-sm md:text-base font-medium shadow-sm">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST" action="" class="bg-white p-6 md:p-10 rounded-2xl shadow-xl border-t-4 border-blue-500">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="step1">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-5 md:mb-6 border-b pb-3"><i class="fas fa-wallet text-blue-500 mr-2"></i> <?= __('choose_payment_method') ?></h2>

                <?php if (count($payment_accounts) > 0): ?>
                    <div class="grid grid-cols-2 md:grid-cols-2 gap-3 md:gap-5 mb-6">
                        <?php foreach($payment_accounts as $acc): ?>
                        <label class="cursor-pointer relative group">
                            <input type="radio" name="payment_method" value="<?= htmlspecialchars($acc['payment_method']) ?>" class="peer hidden" required <?= (isset($_SESSION['deposit_data']['payment_method']) && $_SESSION['deposit_data']['payment_method'] === $acc['payment_method']) ? 'checked' : '' ?>>
                            <div class="border border-gray-200 rounded-xl p-3 md:p-4 flex flex-col items-center gap-2 group-hover:bg-blue-50 group-hover:border-blue-300 peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:shadow-md transition-all duration-300">
                                <?php if(!empty($acc['logo_url'])): ?>
                                    <img src="<?= htmlspecialchars($acc['logo_url']) ?>" class="w-10 h-10 md:w-12 md:h-12 object-cover rounded-full shadow-sm group-hover:scale-105 transition-transform">
                                <?php else: ?>
                                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-blue-100 text-blue-500 flex items-center justify-center text-lg md:text-xl group-hover:scale-105 transition-transform"><i class="fas fa-university"></i></div>
                                <?php endif; ?>
                                <span class="text-sm md:text-base font-bold text-gray-700 text-center"><?= htmlspecialchars($acc['payment_method']) ?></span>
                                <div class="absolute top-2 right-2 text-blue-500 hidden peer-checked:block"><i class="fas fa-check-circle md:text-lg"></i></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-6 md:mb-8">
                        <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 md:mb-3"><?= __('deposit_amount_ks') ?></label>
                        <input type="number" name="amount" min="<?= $min_deposit ?>" max="<?= $max_deposit ?>" value="<?= htmlspecialchars($_SESSION['deposit_data']['amount'] ?? '') ?>" placeholder="<?= str_replace('%amount%', number_format($min_deposit), __('min_deposit_placeholder')) ?>" class="w-full py-3 md:py-4 px-4 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-200 focus:outline-none text-lg md:text-xl font-bold transition-all" required>
                    </div>

                    <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3 md:py-4 rounded-xl text-lg md:text-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition duration-300">
                        <?= __('continue') ?> <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                <?php else: ?>
                    <p class="text-sm md:text-base text-red-500 italic text-center py-6 md:py-8"><?= __('no_payment_accounts_available') ?></p>
                <?php endif; ?>
            </form>

        <?php elseif ($step === 2): 
            $selected_account = null;
            foreach ($payment_accounts as $acc) {
                if ($acc['payment_method'] === $_SESSION['deposit_data']['payment_method']) {
                    $selected_account = $acc; break;
                }
            }
        ?>
            <form method="POST" action="" enctype="multipart/form-data" class="bg-white p-6 md:p-10 rounded-2xl shadow-xl border-t-4 border-green-500">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="step2">
                
                <div class="text-center mb-5 md:mb-6 border-b border-gray-100 pb-5 md:pb-6">
                    <p class="text-sm md:text-base text-gray-500 font-bold mb-1 md:mb-2"><?= __('your_deposit_amount') ?></p>
                    <p class="text-3xl md:text-4xl font-bold text-green-600 tracking-tight"><?= number_format($_SESSION['deposit_data']['amount']) ?> <span class="text-xl md:text-2xl"><?= __('currency') ?></span></p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 md:p-6 mb-6 text-center relative overflow-hidden shadow-inner">
                    <div class="absolute top-0 left-0 w-full h-1.5 bg-blue-500"></div>
                    <p class="text-xs md:text-sm text-blue-800 font-bold mb-3 md:mb-4 uppercase tracking-widest"><?= __('transfer_to_account') ?></p>
                    
                    <div class="flex justify-center items-center gap-2 md:gap-3 mb-2 md:mb-3">
                        <?php if(!empty($selected_account['logo_url'])): ?>
                            <img src="<?= htmlspecialchars($selected_account['logo_url']) ?>" class="w-7 h-7 md:w-8 md:h-8 rounded-full object-cover shadow-sm">
                        <?php endif; ?>
                        <span class="font-bold text-gray-800 md:text-lg"><?= htmlspecialchars($selected_account['payment_method']) ?></span>
                    </div>
                    
                    <p class="text-2xl md:text-3xl font-bold text-primary tracking-wider mb-2" id="accNumber"><?= htmlspecialchars($selected_account['account_number']) ?></p>
                    <button type="button" onclick="copyAccNumber()" class="text-xs md:text-sm bg-white border border-gray-300 px-4 py-1.5 rounded-md shadow-sm hover:bg-gray-100 hover:text-blue-600 transition-colors text-gray-600 mb-2 md:mb-3"><i class="fas fa-copy mr-1.5"></i> <?= __('copy') ?></button>
                    
                    <p class="text-sm md:text-base text-gray-600 mt-2 font-bold"><?= htmlspecialchars($selected_account['account_name']) ?></p>

                    <?php if (!empty($selected_account['qr_image_url'])): ?>
                        <div class="mt-5 border-t border-blue-200 pt-5 flex justify-center">
                            <img src="<?= htmlspecialchars($selected_account['qr_image_url']) ?>" alt="QR" class="w-32 h-32 md:w-40 md:h-40 rounded-xl border-2 border-gray-300 shadow-md object-cover hover:scale-105 transition-transform">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-5 md:mb-6">
                    <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 md:mb-3"><?= __('transaction_id') ?></label>
                    <input type="text" name="transaction_id" placeholder="<?= __('transaction_id_placeholder') ?>" class="w-full py-3 md:py-4 px-4 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-200 focus:outline-none transition-all md:text-lg" required>
                    <p class="text-[10px] md:text-xs text-gray-500 mt-1.5 font-medium"><?= __('transaction_id_help') ?></p>
                </div>
                
                <div class="mb-6 md:mb-8">
                    <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 md:mb-3"><?= __('upload_slip_image') ?></label>
                    <input type="file" name="slip_image" accept="image/png, image/jpeg, image/jpg, image/webp" class="w-full text-sm md:text-base text-gray-500 file:mr-4 file:py-2.5 file:px-5 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors cursor-pointer" required>
                </div>

                <div class="flex gap-3 md:gap-4">
                    <button type="submit" name="back" value="1" formnovalidate class="w-1/3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 md:py-4 rounded-xl shadow-sm hover:shadow-md transition duration-300 text-sm md:text-base">
                        <i class="fas fa-arrow-left mr-1"></i> <?= __('back') ?>
                    </button>
                    <button type="submit" class="w-2/3 bg-green-600 hover:bg-green-700 text-white font-bold py-3 md:py-4 rounded-xl text-lg md:text-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition duration-300">
                        <?= __('confirm') ?> <i class="fas fa-check-circle ml-1"></i>
                    </button>
                </div>
                <script>
                    function copyAccNumber() {
                        var copyText = document.getElementById("accNumber").innerText;
                        navigator.clipboard.writeText(copyText);
                        Swal.fire({
                            icon: 'success',
                            title: '<?= __('success') ?>',
                            text: '<?= __('account_copied_success') ?>',
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            customClass: {
                                popup: 'rounded-2xl'
                            }
                        });
                    }
                </script>
            </form>

        <?php elseif ($step === 3): ?>
            <div class="bg-white p-8 md:p-12 rounded-2xl shadow-xl text-center">
                <div class="text-green-500 text-6xl md:text-7xl mb-5 animate-bounce"><i class="fas fa-check-circle"></i></div>
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3"><?= __('success') ?></h2>
                <p class="text-sm md:text-base text-gray-600 mb-8 leading-relaxed font-medium"><?= htmlspecialchars($success_message) ?></p>
                <a href="index.php" class="inline-block w-full md:w-auto md:px-12 bg-primary hover:bg-blue-800 text-white font-bold py-3 md:py-4 rounded-xl text-lg shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition duration-300"><?= __('back_to_home') ?></a>
            </div>

            <audio id="depositSuccessSound" src="assets/sounds/notification.mp3" autoplay></audio>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var snd = document.getElementById('depositSuccessSound');
                    if (snd) {
                        snd.play().catch(function(e) {
                            console.log("Autoplay prevented by browser.");
                        });
                    }
                    if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
                });
            </script>
        <?php endif; ?>
    </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>