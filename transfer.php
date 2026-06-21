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

// CSRF Token တည်ဆောက်ခြင်း
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// လက်ရှိ User ၏ လက်ကျန်ငွေနှင့် အချက်အလက်များကို ဆွဲထုတ်ခြင်း
$stmt = $conn->prepare("SELECT balance, phone_number, username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Form Submit လုပ်လာသောအခါ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Token စစ်ဆေးခြင်း
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "လုံခြုံရေးအရ အမှားအယွင်းဖြစ်ပေါ်နေပါသည်။ (CSRF Token Mismatch) ကျေးဇူးပြု၍ Page ကို Refresh လုပ်ပြီး ထပ်မံကြိုးစားပါ။";
    } else {
        $receiver_phone = trim($_POST['receiver_phone'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);

        if (empty($receiver_phone) || $amount < 100) {
            $error_message = __('transfer_min_amount_error');
        } elseif ($receiver_phone === $user['phone_number']) {
            $error_message = __('transfer_self_error');
        } elseif ($user['balance'] < $amount) {
            $error_message = sprintf(__('transfer_insufficient_balance'), number_format($user['balance']));
        } else {
            // ငွေလက်ခံမည့်သူ (Receiver) အကောင့်ရှိမရှိ ရှာဖွေခြင်း
            $stmt = $conn->prepare("SELECT id, username FROM users WHERE phone_number = ?");
            $stmt->bind_param("s", $receiver_phone);
            $stmt->execute();
            $receiver_res = $stmt->get_result();
            
            if ($receiver_res->num_rows > 0) {
                $receiver = $receiver_res->fetch_assoc();
                $receiver_id = $receiver['id'];

                $conn->begin_transaction();
                try {
                    // ၁။ လွှဲပို့သူ (Sender) ထံမှ ငွေနှုတ်မည်
                    $stmt1 = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $stmt1->bind_param("di", $amount, $user_id);
                    $stmt1->execute();

                    // ၂။ လက်ခံမည့်သူ (Receiver) ထံသို့ ငွေပေါင်းထည့်မည်
                    $stmt2 = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt2->bind_param("di", $amount, $receiver_id);
                    $stmt2->execute();

                    // ၃။ မှတ်တမ်းသိမ်းဆည်းမည်
                    $stmt3 = $conn->prepare("INSERT INTO transfers (sender_id, receiver_id, amount) VALUES (?, ?, ?)");
                    $stmt3->bind_param("iid", $user_id, $receiver_id, $amount);
                    $stmt3->execute();

                    // ၄။ နှစ်ဦးလုံးထံသို့ Notification ပို့ပေးမည်
                    $sender_msg = sprintf(__('transfer_sender_noti'), htmlspecialchars($receiver['username']), htmlspecialchars($receiver_phone), number_format($amount));
                    $receiver_msg = sprintf(__('transfer_receiver_noti'), htmlspecialchars($user['username']), htmlspecialchars($user['phone_number']), number_format($amount));

                    $stmt4 = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?), (?, ?)");
                    $stmt4->bind_param("isis", $user_id, $sender_msg, $receiver_id, $receiver_msg);
                    $stmt4->execute();

                    $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id IN ($user_id, $receiver_id)");

                    $conn->commit();
                    $user['balance'] -= $amount; // UI တွင် ချက်ချင်းပြောင်းလဲရန်
                    $success_message = sprintf(__('transfer_success_msg'), htmlspecialchars($receiver['username']), htmlspecialchars($receiver_phone), number_format($amount));
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = __('system_error_try_again');
                }
            } else {
                $error_message = __('transfer_account_not_found');
            }
            $stmt->close();
        }
    }
}
?>

<?php 
$page_title = __('title_transfer') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24 flex flex-col">

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="index.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center tracking-wide"><?= __('title_transfer') ?></h1>
        <a href="transaction_history.php?type=transfer" class="ml-4 text-lg md:text-xl w-6 md:w-10 text-right hover:scale-110 transition-transform" title="<?= __('view_history') ?>"><i class="fas fa-history"></i></a>
    </div>

    <div class="px-4 md:px-8 mt-4 md:mt-6 max-w-2xl mx-auto w-full">
        <div class="bg-white p-5 md:p-6 shadow-sm md:shadow-md border-b md:border border-gray-100 md:rounded-2xl flex flex-col md:flex-row md:justify-between md:items-center">
            <p class="text-gray-500 text-sm md:text-base font-bold mb-1 md:mb-0 uppercase tracking-wide flex items-center"><i class="fas fa-wallet text-primary mr-2"></i> <?= __('balance') ?></p>
            <p class="text-3xl md:text-4xl font-bold text-primary tracking-tight"><?= number_format($user['balance'], 2) ?> <span class="text-base md:text-lg font-normal text-gray-400"><?= __('currency') ?></span></p>
        </div>
    </div>

    <div class="p-4 md:p-8 pt-2 md:pt-4 max-w-2xl mx-auto w-full">
        
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl relative mb-5 text-sm md:text-base font-bold shadow-sm flex items-center text-center justify-center">
                <i class="fas fa-check-circle text-green-500 text-xl mr-2"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl relative mb-5 text-sm md:text-base font-bold shadow-sm flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="bg-white p-6 md:p-10 rounded-2xl md:rounded-3xl shadow-lg border border-gray-100 border-t-4 border-t-purple-500">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="text-center mb-6 md:mb-8">
                <div class="w-16 h-16 md:w-20 md:h-20 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center mx-auto mb-3 md:mb-4 shadow-sm border border-purple-200">
                    <i class="fas fa-exchange-alt text-3xl md:text-4xl"></i>
                </div>
                <p class="text-gray-600 text-sm md:text-base font-bold tracking-wide"><?= __('transfer_subtitle') ?></p>
            </div>
            
            <div class="mb-5 md:mb-6">
                <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 md:mb-3"><i class="fas fa-user text-purple-500 mr-1.5"></i> <?= __('receiver_phone_label') ?></label>
                <input type="text" name="receiver_phone" placeholder="<?= __('example_phone') ?>" class="w-full py-3.5 md:py-4 px-4 md:px-5 border border-gray-300 rounded-xl focus:border-purple-500 focus:ring focus:ring-purple-100 focus:outline-none text-sm md:text-base text-gray-700 transition-all font-bold" required>
            </div>
            
            <div class="mb-6 md:mb-8">
                <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 md:mb-3"><i class="fas fa-money-bill-wave text-purple-500 mr-1.5"></i> <?= __('transfer_amount_label') ?></label>
                <input type="number" name="amount" min="100" placeholder="<?= __('min_transfer_placeholder') ?>" class="w-full py-3.5 md:py-4 px-4 md:px-5 border border-gray-300 rounded-xl focus:border-purple-500 focus:ring focus:ring-purple-100 focus:outline-none text-lg md:text-xl font-bold transition-all text-primary" required>
            </div>
            
            <button type="button" id="btnTransfer" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3.5 md:py-4 rounded-xl text-lg md:text-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
                <?= __('btn_transfer') ?> <i class="fas fa-paper-plane ml-1.5"></i>
            </button>
        </form>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
document.getElementById('btnTransfer').addEventListener('click', function(e) {
    const form = this.closest('form');
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '<?= __('confirm_transfer_title') ?>',
            text: "<?= __('confirm_transfer_text') ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#9333ea',
            cancelButtonColor: '#d33',
            confirmButtonText: '<i class="fas fa-check-circle mr-1"></i> <?= __('btn_transfer_confirm') ?>',
            cancelButtonText: '<i class="fas fa-times-circle mr-1"></i> <?= __('cancel') ?>',
            customClass: {
                popup: 'rounded-2xl',
                confirmButton: 'rounded-xl px-5 py-2.5 shadow-md',
                cancelButton: 'rounded-xl px-5 py-2.5 shadow-sm'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    } else {
        if(confirm("<?= __('confirm_transfer_text') ?>")) {
            form.submit();
        }
    }
});
</script>
</body>
</html>