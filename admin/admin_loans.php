<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';
require_permission('can_manage_transactions');

// Loans များကို သိမ်းဆည်းရန် Database Table မရှိသေးပါက အလိုအလျောက် တည်ဆောက်မည်
$check_table = $conn->query("SHOW TABLES LIKE 'loans'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE loans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'repaid') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    $loan_id = intval($_POST['loan_id'] ?? 0);
    $user_id = intval($_POST['user_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);

    if ($loan_id > 0 && $user_id > 0) {
        $conn->begin_transaction();
        try {
            if ($action == 'approve') {
                $stmt = $conn->prepare("UPDATE loans SET status = 'approved' WHERE id = ?");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();
                
                $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->bind_param("di", $amount, $user_id);
                $stmt->execute();
                
                $noti_msg = sprintf(__('admin_loan_approve_noti'), number_format($amount));
                $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                $stmt->bind_param("is", $user_id, $noti_msg);
                $stmt->execute();
                
                $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = $user_id");
                log_activity($_SESSION['user_id'], 'APPROVE_LOAN', "Approved loan ID {$loan_id} for User ID {$user_id}");
                $success_message = __('admin_loan_approve_success');
                
            } elseif ($action == 'reject') {
                $stmt = $conn->prepare("UPDATE loans SET status = 'rejected' WHERE id = ?");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();
                
                $noti_msg = sprintf(__('admin_loan_reject_noti'), number_format($amount));
                $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                $stmt->bind_param("is", $user_id, $noti_msg);
                $stmt->execute();
                
                $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = $user_id");
                log_activity($_SESSION['user_id'], 'REJECT_LOAN', "Rejected loan ID {$loan_id} for User ID {$user_id}");
                $success_message = __('admin_loan_reject_success');
                
            } elseif ($action == 'repaid') {
                // User လက်ကျန်ငွေ လုံလောက်မှု ရှိ/မရှိ စစ်ဆေးခြင်း
                $user_check = $conn->query("SELECT balance FROM users WHERE id = $user_id")->fetch_assoc();
                if ($user_check['balance'] < $amount) {
                    throw new Exception(sprintf(__('admin_loan_insufficient_bal'), number_format($user_check['balance'])));
                }
                
                // Deduct from balance
                $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->bind_param("di", $amount, $user_id);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE loans SET status = 'repaid' WHERE id = ?");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();
                
                $noti_msg = sprintf(__('admin_loan_repaid_noti'), number_format($amount));
                $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                $stmt->bind_param("is", $user_id, $noti_msg);
                $stmt->execute();
                
                $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = $user_id");
                log_activity($_SESSION['user_id'], 'REPAY_LOAN', "Marked loan ID {$loan_id} as repaid for User ID {$user_id}. Amount deducted.");
                $success_message = __('admin_loan_repaid_success');
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = __('admin_tx_error') . " " . $e->getMessage();
        }
    }
}

// Fetch Loans
$status_filter = $_GET['status'] ?? 'pending';
$where = "";
if ($status_filter == 'pending') $where = "WHERE l.status = 'pending'";
elseif ($status_filter == 'approved') $where = "WHERE l.status = 'approved'";
elseif ($status_filter == 'repaid') $where = "WHERE l.status = 'repaid'";
elseif ($status_filter == 'rejected') $where = "WHERE l.status = 'rejected'";

$query = "SELECT l.*, u.username, u.phone_number, u.balance FROM loans l JOIN users u ON l.user_id = u.id $where ORDER BY l.created_at DESC";
$result = $conn->query($query);
$loans = [];
if ($result) {
    $loans = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<?php 
$page_title = __('admin_loan_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-5xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_loan_header_title');
    $header_icon = "fas fa-hand-holding-usd text-blue-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 text-sm font-bold shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm font-bold shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="bg-white flex justify-around border-b text-sm font-bold text-gray-500 shadow-sm mb-6 rounded-xl overflow-hidden">
            <a href="?status=pending" class="py-4 w-1/4 text-center <?= $status_filter == 'pending' ? 'text-blue-600 border-b-4 border-blue-600 bg-blue-50' : 'hover:bg-gray-50' ?>"><?= __('admin_loan_tab_new') ?></a>
            <a href="?status=approved" class="py-4 w-1/4 text-center <?= $status_filter == 'approved' ? 'text-green-600 border-b-4 border-green-600 bg-green-50' : 'hover:bg-gray-50' ?>"><?= __('admin_loan_tab_approved') ?></a>
            <a href="?status=repaid" class="py-4 w-1/4 text-center <?= $status_filter == 'repaid' ? 'text-purple-600 border-b-4 border-purple-600 bg-purple-50' : 'hover:bg-gray-50' ?>"><?= __('admin_loan_tab_repaid') ?></a>
            <a href="?status=rejected" class="py-4 w-1/4 text-center <?= $status_filter == 'rejected' ? 'text-red-600 border-b-4 border-red-600 bg-red-50' : 'hover:bg-gray-50' ?>"><?= __('admin_loan_tab_rejected') ?></a>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (count($loans) > 0): ?>
                <!-- Mobile View: Cards -->
                <div class="md:hidden divide-y divide-gray-100">
                    <?php foreach ($loans as $loan): ?>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <p class="font-bold text-gray-800"><?= htmlspecialchars($loan['username']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($loan['phone_number']) ?></p>
                                    <p class="text-[10px] text-blue-600 mt-1 font-bold"><?= __('admin_loan_user_bal') ?> <?= number_format($loan['balance']) ?> Ks</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-red-600 text-lg"><?= number_format($loan['amount']) ?> Ks</p>
                                    <?php if ($loan['status'] == 'approved'): ?><span class="bg-green-100 text-green-700 text-[10px] px-2 py-1 rounded border border-green-300">Approved</span><?php elseif ($loan['status'] == 'pending'): ?><span class="bg-yellow-100 text-yellow-700 text-[10px] px-2 py-1 rounded border border-yellow-300">Pending</span><?php elseif ($loan['status'] == 'repaid'): ?><span class="bg-purple-100 text-purple-700 text-[10px] px-2 py-1 rounded border border-purple-300">Repaid</span><?php else: ?><span class="bg-red-100 text-red-700 text-[10px] px-2 py-1 rounded border border-red-300">Rejected</span><?php endif; ?>
                                </div>
                            </div>
                            <form method="POST" action="" class="flex gap-2 justify-end">
                                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>"><input type="hidden" name="user_id" value="<?= $loan['user_id'] ?>"><input type="hidden" name="amount" value="<?= $loan['amount'] ?>">
                                <?php if ($loan['status'] == 'pending'): ?>
                                    <button type="submit" name="action" value="approve" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded text-[11px] font-bold shadow-sm transition" onclick="return confirm('<?= __('admin_loan_confirm_approve') ?>');"><i class="fas fa-check mr-1"></i> <?= __('admin_loan_btn_approve') ?></button>
                                    <button type="submit" name="action" value="reject" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded text-[11px] font-bold shadow-sm transition" onclick="return confirm('<?= __('admin_loan_confirm_reject') ?>');"><i class="fas fa-times mr-1"></i> <?= __('admin_loan_btn_reject') ?></button>
                                <?php elseif ($loan['status'] == 'approved'): ?>
                                    <button type="submit" name="action" value="repaid" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded text-[11px] font-bold shadow-sm transition" onclick="return confirm('<?= sprintf(__('admin_loan_confirm_repaid'), number_format($loan['amount'])) ?>');"><i class="fas fa-undo mr-1"></i> <?= __('admin_loan_btn_repaid') ?></button>
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop View: Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="bg-blue-50 text-blue-800 font-bold border-b-2 border-blue-200">
                                <th class="px-4 py-3 whitespace-nowrap"><?= __('admin_loan_col_id') ?></th>
                                <th class="px-4 py-3 whitespace-nowrap"><?= __('admin_loan_col_user') ?></th>
                                <th class="px-4 py-3 text-right whitespace-nowrap"><?= __('admin_loan_col_amount') ?></th>
                                <th class="px-4 py-3 text-center whitespace-nowrap"><?= __('admin_loan_col_status') ?></th>
                                <th class="px-4 py-3 text-center whitespace-nowrap"><?= __('admin_loan_col_action') ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($loans as $loan): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500">#<?= $loan['id'] ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="font-bold text-gray-800"><?= htmlspecialchars($loan['username']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($loan['phone_number']) ?></p>
                                        <p class="text-[10px] text-blue-600 mt-1 font-bold"><?= __('admin_loan_user_bal') ?> <?= number_format($loan['balance']) ?> Ks</p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-red-600 whitespace-nowrap"><?= number_format($loan['amount']) ?> Ks</td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <?php if ($loan['status'] == 'approved'): ?><span class="bg-green-100 text-green-700 text-[10px] px-2 py-1 rounded border border-green-300">Approved</span><?php elseif ($loan['status'] == 'pending'): ?><span class="bg-yellow-100 text-yellow-700 text-[10px] px-2 py-1 rounded border border-yellow-300">Pending</span><?php elseif ($loan['status'] == 'repaid'): ?><span class="bg-purple-100 text-purple-700 text-[10px] px-2 py-1 rounded border border-purple-300">Repaid</span><?php else: ?><span class="bg-red-100 text-red-700 text-[10px] px-2 py-1 rounded border border-red-300">Rejected</span><?php endif; ?>
                                        <p class="text-[10px] text-gray-400 mt-1"><?= date('d-M-Y h:i A', strtotime($loan['created_at'])) ?></p>
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <form method="POST" action="" class="flex gap-2 justify-center">
                                            <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>"><input type="hidden" name="user_id" value="<?= $loan['user_id'] ?>"><input type="hidden" name="amount" value="<?= $loan['amount'] ?>">
                                            <?php if ($loan['status'] == 'pending'): ?>
                                                <button type="submit" name="action" value="approve" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded text-[11px] font-bold shadow-sm transition" onclick="return confirm('<?= __('admin_loan_confirm_approve') ?>');"><i class="fas fa-check mr-1"></i> <?= __('admin_loan_btn_approve') ?></button>
                                                <button type="submit" name="action" value="reject" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded text-[11px] font-bold shadow-sm transition" onclick="return confirm('<?= __('admin_loan_confirm_reject') ?>');"><i class="fas fa-times mr-1"></i> <?= __('admin_loan_btn_reject') ?></button>
                                            <?php elseif ($loan['status'] == 'approved'): ?>
                                                <button type="submit" name="action" value="repaid" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded text-[11px] font-bold shadow-sm transition" onclick="return confirm('<?= sprintf(__('admin_loan_confirm_repaid'), number_format($loan['amount'])) ?>');"><i class="fas fa-undo mr-1"></i> <?= __('admin_loan_btn_repaid') ?></button>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">-</span>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="px-4 py-8 text-center text-gray-500 italic">
                    <i class="fas fa-hand-holding-usd text-4xl text-gray-300 mb-3 block"></i>
                    <?= __('admin_loan_no_records') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>