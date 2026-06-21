<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';
require_permission('can_manage_transactions');

// Create table if not exists
$check_table = $conn->query("SHOW TABLES LIKE 'pre_approved_transactions'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE pre_approved_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_method VARCHAR(50) NOT NULL,
        transaction_id VARCHAR(50) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'used') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_pre_approve'])) {
        $payment_method = trim($_POST['payment_method'] ?? '');
        $transaction_id = trim($_POST['transaction_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);

        if (!empty($payment_method) && !empty($transaction_id) && $amount > 0) {
            $stmt = $conn->prepare("INSERT INTO pre_approved_transactions (payment_method, transaction_id, amount) VALUES (?, ?, ?)");
            $stmt->bind_param("ssd", $payment_method, $transaction_id, $amount);
            if ($stmt->execute()) {
                $success_message = __('admin_auto_add_success');
                log_activity($_SESSION['user_id'], 'ADD_AUTO_APPROVE', "Added auto-approve: {$payment_method} - {$transaction_id} - {$amount}");
            } else {
                $error_message = __('admin_auto_add_error');
            }
            $stmt->close();
        } else {
            $error_message = __('admin_auto_fill_all');
        }
    } elseif (isset($_POST['delete_pre_approve'])) {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM pre_approved_transactions WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = __('admin_auto_delete_success');
        }
        $stmt->close();
    }
}

// Fetch payment methods for dropdown
$acc_stmt = $conn->query("SELECT DISTINCT payment_method FROM payment_accounts WHERE is_active = 1");
$active_methods = [];
if ($acc_stmt) {
    while($r = $acc_stmt->fetch_assoc()) $active_methods[] = $r['payment_method'];
}

// Fetch pending pre-approved
$pending_list = $conn->query("SELECT * FROM pre_approved_transactions WHERE status = 'pending' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch used pre-approved (Limit 50)
$used_list = $conn->query("SELECT * FROM pre_approved_transactions WHERE status = 'used' ORDER BY created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
?>

<?php 
$page_title = __('admin_auto_approve_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_auto_approve_header_title');
    $header_icon = "fas fa-robot text-green-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 shadow-sm text-sm font-bold"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 shadow-sm text-sm font-bold"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="bg-white p-4 sm:p-6 rounded-xl shadow-md border-t-4 border-green-500 mb-6">
            <h2 class="font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-plus-circle text-green-500 mr-2"></i> <?= __('admin_auto_add_new_title') ?></h2>
            <p class="text-sm text-gray-600 mb-4"><?= __('admin_auto_add_new_desc') ?></p>
            
            <form method="POST" action="" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div><label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_auto_payment_method') ?></label><select name="payment_method" class="w-full py-2 px-3 border rounded focus:border-green-500 focus:outline-none bg-white" required><option value="" disabled selected><?= __('admin_auto_select') ?></option><?php foreach($active_methods as $method): ?><option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_auto_trx_id') ?></label><input type="text" name="transaction_id" placeholder="<?= __('admin_auto_trx_id_ph') ?>" class="w-full py-2 px-3 border rounded focus:border-green-500 focus:outline-none" required></div>
                <div><label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_auto_amount') ?></label><input type="number" name="amount" min="1" placeholder="<?= __('admin_auto_amount_ph') ?>" class="w-full py-2 px-3 border rounded focus:border-green-500 focus:outline-none" required></div>
                <div class="flex items-end sm:col-span-2 md:col-span-1"><button type="submit" name="add_pre_approve" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow-sm transition"><i class="fas fa-save mr-1"></i> <?= __('admin_auto_btn_add') ?></button></div>
            </form>
        </div>

        <!-- Pending List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
            <div class="p-4 border-b bg-yellow-50"><h2 class="font-bold text-gray-700"><i class="fas fa-hourglass-half text-yellow-500 mr-2"></i> <?= __('admin_auto_pending_title') ?></h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal text-left text-sm">
                    <thead><tr class="bg-gray-50 text-gray-600 border-b"><th class="px-4 py-3 font-bold whitespace-nowrap"><?= __('admin_auto_col_payment') ?></th><th class="px-4 py-3 font-bold whitespace-nowrap"><?= __('admin_auto_col_trx_id') ?></th><th class="px-4 py-3 font-bold text-right whitespace-nowrap"><?= __('admin_auto_col_amount') ?></th><th class="px-4 py-3 font-bold text-center whitespace-nowrap"><?= __('admin_auto_col_time') ?></th><th class="px-4 py-3 font-bold text-center whitespace-nowrap"><?= __('admin_auto_col_action') ?></th></tr></thead>
                    <tbody class="divide-y">
                        <?php if (count($pending_list) > 0): ?>
                            <?php foreach ($pending_list as $row): ?>
                                <tr class="hover:bg-gray-50 transition"><td class="px-4 py-3"><?= htmlspecialchars($row['payment_method']) ?></td><td class="px-4 py-3 font-bold text-blue-600"><?= htmlspecialchars($row['transaction_id']) ?></td><td class="px-4 py-3 text-right font-bold text-green-600"><?= number_format($row['amount']) ?></td><td class="px-4 py-3 text-center text-gray-500 text-xs whitespace-nowrap"><?= date('d-M-Y h:i A', strtotime($row['created_at'])) ?></td><td class="px-4 py-3 text-center"><form method="POST" action="" onsubmit="return confirm('<?= __('admin_auto_confirm_delete') ?>');"><input type="hidden" name="id" value="<?= $row['id'] ?>"><button type="submit" name="delete_pre_approve" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button></form></td></tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500 italic"><?= __('admin_auto_no_records') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Used List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50"><h2 class="font-bold text-gray-700"><i class="fas fa-check-circle text-green-500 mr-2"></i> <?= __('admin_auto_used_title') ?></h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal text-left text-sm">
                    <thead><tr class="bg-gray-50 text-gray-600 border-b"><th class="px-4 py-3 font-bold whitespace-nowrap"><?= __('admin_auto_col_payment') ?></th><th class="px-4 py-3 font-bold whitespace-nowrap"><?= __('admin_auto_col_trx_id') ?></th><th class="px-4 py-3 font-bold text-right whitespace-nowrap"><?= __('admin_auto_col_amount') ?></th><th class="px-4 py-3 font-bold text-center whitespace-nowrap"><?= __('admin_auto_col_time') ?></th></tr></thead>
                    <tbody class="divide-y">
                        <?php if (count($used_list) > 0): ?>
                            <?php foreach ($used_list as $row): ?>
                                <tr class="hover:bg-gray-50 transition bg-green-50/30"><td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($row['payment_method']) ?></td><td class="px-4 py-3 font-bold text-gray-600"><?= htmlspecialchars($row['transaction_id']) ?></td><td class="px-4 py-3 text-right font-bold text-gray-600"><?= number_format($row['amount']) ?></td><td class="px-4 py-3 text-center text-gray-500 text-xs whitespace-nowrap"><?= date('d-M-Y h:i A', strtotime($row['created_at'])) ?></td></tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500 italic"><?= __('admin_auto_no_records') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>