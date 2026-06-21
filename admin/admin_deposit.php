<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// ဤလုပ်ဆောင်ချက်အတွက် Transactions စီမံခွင့် ရှိရန်လိုသည်
require_permission('can_manage_transactions');

$success_message = "";
$error_message = "";

$target_user_id = intval($_GET['user_id'] ?? 0);

if ($target_user_id <= 0) {
    die("<h2 style='text-align:center; margin-top:50px;'>" . __('admin_user_hist_invalid_id') . "</h2>");
}

$stmt = $conn->prepare("SELECT username, phone_number, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$target_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$target_user) {
    die("<h2 style='text-align:center; margin-top:50px;'>" . __('admin_user_hist_not_found') . "</h2>");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_deposit'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($amount > 0) {
        $conn->begin_transaction();
        try {
            // ၁။ User ၏ Balance သို့ ငွေထည့်မည်
            $update_stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $update_stmt->bind_param("di", $amount, $target_user_id);
            $update_stmt->execute();
            $update_stmt->close();

            // ၂။ Deposits Table တွင် မှတ်တမ်းတင်မည် (Transaction History တွင် ပေါ်စေရန်)
            $payment_method = 'Admin Transfer';
            $transaction_id = !empty($note) ? "Admin: " . substr($note, 0, 30) : "Admin_" . time();
            
            $insert_stmt = $conn->prepare("INSERT INTO deposits (user_id, amount, payment_method, transaction_id, status) VALUES (?, ?, ?, ?, 'approved')");
            $insert_stmt->bind_param("idss", $target_user_id, $amount, $payment_method, $transaction_id);
            $insert_stmt->execute();
            $insert_stmt->close();

            // ၃။ User ထံ Notification ပို့မည်
            $noti_msg = sprintf(__('admin_deposit_noti_msg'), number_format($amount));
            $noti_stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
            $noti_stmt->bind_param("is", $target_user_id, $noti_msg);
            $noti_stmt->execute();
            $noti_stmt->close();
            
            $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = $target_user_id");

            $conn->commit();
            log_activity($_SESSION['user_id'], 'ADMIN_DEPOSIT', "Deposited " . number_format($amount) . " to User ID: {$target_user_id}. Note: {$note}");
            
            $success_message = sprintf(__('admin_deposit_success'), number_format($amount));
            $target_user['balance'] += $amount; 
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = __('admin_tx_error') . " " . $e->getMessage();
        }
    } else {
        $error_message = __('admin_users_invalid_amount');
    }
}
?>

<?php 
$page_title = __('admin_deposit_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_deposit_header_title');
    $header_icon = "fas fa-donate";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-6 pt-0">
        <div class="flex justify-between items-center border-b pb-3 mb-6 mt-4">
            <a href="admin_users.php" class="text-blue-600 hover:underline text-sm font-bold"><i class="fas fa-arrow-left mr-1"></i> <?= __('back') ?></a>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 shadow-sm text-sm font-bold"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 shadow-sm text-sm font-bold"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- User Info Card -->
        <div class="bg-pink-50 border-l-4 border-pink-500 p-4 mb-6 rounded shadow-sm flex justify-between items-center">
            <div>
                <h2 class="font-bold text-pink-800 text-lg mb-1"><?= htmlspecialchars($target_user['username']) ?></h2>
                <p class="text-gray-600 text-sm mb-1"><i class="fas fa-phone-alt mr-1"></i> <?= htmlspecialchars($target_user['phone_number']) ?></p>
                <p class="text-gray-600 text-sm"><i class="fas fa-wallet mr-1"></i> <?= __('admin_user_hist_balance') ?> <span class="font-bold text-pink-700"><?= number_format($target_user['balance'], 2) ?> <?= __('currency') ?></span></p>
            </div>
        </div>

        <form method="POST" action="" class="bg-white p-5 rounded-xl shadow-md border-t-4 border-pink-500">
            <div class="mb-4"><label class="block text-gray-700 font-bold mb-2"><?= __('admin_deposit_amount_label') ?></label><input type="number" name="amount" min="1" class="w-full py-3 px-4 border rounded-lg focus:border-pink-500 focus:outline-none text-lg font-bold text-gray-700" placeholder="<?= __('admin_deposit_amount_ph') ?>" required></div>
            <div class="mb-6"><label class="block text-gray-700 font-bold mb-2"><?= __('admin_deposit_note_label') ?></label><input type="text" name="note" class="w-full py-3 px-4 border rounded-lg focus:border-pink-500 focus:outline-none text-sm text-gray-700" placeholder="<?= __('admin_deposit_note_ph') ?>"></div>
            <button type="submit" name="admin_deposit" class="bg-pink-600 hover:bg-pink-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition w-full md:w-auto" onclick="return confirm('<?= __('admin_deposit_confirm') ?>');"><i class="fas fa-paper-plane mr-2"></i> <?= __('admin_deposit_btn') ?></button>
        </form>

        <?php if (!empty($success_message)): ?>
            <audio id="adminDepositSound" src="../assets/sounds/notification.mp3" autoplay></audio>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var snd = document.getElementById('adminDepositSound');
                    if (snd) { snd.play().catch(e => {}); }
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>