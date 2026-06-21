<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_permission('can_manage_transactions');

$message = "";

// Withdrawals Table သို့ fee_amount Column မရှိသေးပါက အလိုအလျောက် ထည့်သွင်းပေးမည်
$check_fee_col = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'fee_amount'");
if ($check_fee_col && $check_fee_col->num_rows == 0) {
    $conn->query("ALTER TABLE withdrawals ADD fee_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER amount");
}
$check_reason_col = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'reject_reason'");
if ($check_reason_col && $check_reason_col->num_rows == 0) {
    $conn->query("ALTER TABLE withdrawals ADD reject_reason VARCHAR(255) NULL AFTER status");
}
$check_dep_reason_col = $conn->query("SHOW COLUMNS FROM deposits LIKE 'reject_reason'");
if ($check_dep_reason_col && $check_dep_reason_col->num_rows == 0) {
    $conn->query("ALTER TABLE deposits ADD reject_reason VARCHAR(255) NULL AFTER status");
}
$check_dep_slip_col = $conn->query("SHOW COLUMNS FROM deposits LIKE 'slip_image_url'");
if ($check_dep_slip_col && $check_dep_slip_col->num_rows == 0) {
    $conn->query("ALTER TABLE deposits ADD slip_image_url VARCHAR(255) NULL AFTER transaction_id");
}

// Approve သို့မဟုတ် Reject ခလုတ်နှိပ်လိုက်သောအခါ လုပ်ဆောင်မည့် အပိုင်း
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    $req_id = intval($_POST['request_id'] ?? 0);

    if ($req_id > 0 && in_array($action, ['approve', 'reject']) && in_array($type, ['deposit', 'withdrawal'])) {
        $conn->begin_transaction();
        try {
            if ($type == 'deposit') {
                // ငွေဖြည့် တောင်းဆိုချက်ကို ဆွဲထုတ်ခြင်း
                $stmt = $conn->prepare("SELECT user_id, amount, status FROM deposits WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $req_id);
                $stmt->execute();
                $dep = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($dep && $dep['status'] == 'pending') {
                    if ($action == 'approve') {
                        // ၁။ Status ကို approved ပြောင်းမည်
                        $stmt = $conn->prepare("UPDATE deposits SET status = 'approved' WHERE id = ?");
                        $stmt->bind_param("i", $req_id);
                        $stmt->execute();
                        $stmt->close();

                        // ၂။ User ၏ Balance ထဲသို့ ငွေပေါင်းထည့်ပေးမည်
                        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->bind_param("di", $dep['amount'], $dep['user_id']);
                        $stmt->execute();
                        $stmt->close();

                        log_activity($_SESSION['user_id'], 'APPROVE_DEPOSIT', "Approved deposit ID: {$req_id} for User ID: {$dep['user_id']}, Amount: " . number_format($dep['amount']));
                        $message = __('admin_tx_dep_approve_success');

                        // User ထံ Notification ပို့မည်
                        $noti_msg = sprintf(__('admin_tx_dep_approve_noti'), number_format($dep['amount']));
                        $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                        $stmt->bind_param("is", $dep['user_id'], $noti_msg);
                        $stmt->execute();
                        $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = " . $dep['user_id']);
                    } else {
                        $reject_reason = trim($_POST['reject_reason'] ?? '');
                        // Status ကို သာ rejected ပြောင်းမည် (Balance ပြင်စရာမလိုပါ)
                        $stmt = $conn->prepare("UPDATE deposits SET status = 'rejected', reject_reason = ? WHERE id = ?");
                        $stmt->bind_param("si", $reject_reason, $req_id);
                        $stmt->execute();
                        $stmt->close();

                        log_activity($_SESSION['user_id'], 'REJECT_DEPOSIT', "Rejected deposit ID: {$req_id} for User ID: {$dep['user_id']}. Reason: {$reject_reason}");
                        $message = __('admin_tx_dep_reject_success');

                        // User ထံ Notification ပို့မည်
                        $reason_text = !empty($reject_reason) ? "\n" . __('admin_tx_reason') . " " . $reject_reason : "";
                        $noti_msg = sprintf(__('admin_tx_dep_reject_noti'), number_format($dep['amount'])) . $reason_text;
                        $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                        $stmt->bind_param("is", $dep['user_id'], $noti_msg);
                        $stmt->execute();
                        $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = " . $dep['user_id']);
                    }
                }
            } elseif ($type == 'withdrawal') {
                // ငွေထုတ် တောင်းဆိုချက်ကို ဆွဲထုတ်ခြင်း
                $stmt = $conn->prepare("SELECT user_id, amount, status FROM withdrawals WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $req_id);
                $stmt->execute();
                $with = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($with && $with['status'] == 'pending') {
                    if ($action == 'approve') {
                        $admin_payment_account = trim($_POST['admin_payment_account'] ?? '');
                        // Status ကို approved ပြောင်းမည် (Balance ထဲမှ ကြိုတင်နှုတ်ထားပြီးဖြစ်၍ ထပ်နှုတ်ရန် မလိုပါ)
                        $stmt = $conn->prepare("UPDATE withdrawals SET status = 'approved', admin_payment_account = ? WHERE id = ?");
                        $stmt->bind_param("si", $admin_payment_account, $req_id);
                        $stmt->execute();
                        $stmt->close();

                        log_activity($_SESSION['user_id'], 'APPROVE_WITHDRAWAL', "Approved withdrawal ID: {$req_id} for User ID: {$with['user_id']}, Amount: " . number_format($with['amount']));
                        $message = __('admin_tx_with_approve_success');

                        // User ထံ Notification ပို့မည်
                        $noti_msg = sprintf(__('admin_tx_with_approve_noti'), number_format($with['amount']));
                        $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                        $stmt->bind_param("is", $with['user_id'], $noti_msg);
                        $stmt->execute();
                        $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = " . $with['user_id']);
                    } else {
                        $reject_reason = trim($_POST['reject_reason'] ?? '');
                        // ၁။ Status ကို rejected ပြောင်းမည်
                        $stmt = $conn->prepare("UPDATE withdrawals SET status = 'rejected', reject_reason = ? WHERE id = ?");
                        $stmt->bind_param("si", $reject_reason, $req_id);
                        $stmt->execute();
                        $stmt->close();

                        // ၂။ (အရေးကြီး) ငြင်းပယ်လိုက်သဖြင့် ကြိုတင်နှုတ်ထားသောငွေကို User ထံ ပြန်ပေါင်းထည့်ပေးရမည် (Refund)
                        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->bind_param("di", $with['amount'], $with['user_id']);
                        $stmt->execute();
                        $stmt->close();

                        log_activity($_SESSION['user_id'], 'REJECT_WITHDRAWAL', "Rejected withdrawal ID: {$req_id} for User ID: {$with['user_id']}. Amount refunded. Reason: {$reject_reason}");
                        $message = __('admin_tx_with_reject_success');

                        // User ထံ Notification ပို့မည်
                        $reason_text = !empty($reject_reason) ? "\n" . __('admin_tx_reason') . " " . $reject_reason : "";
                        $noti_msg = sprintf(__('admin_tx_with_reject_noti'), number_format($with['amount'])) . $reason_text;
                        $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                        $stmt->bind_param("is", $with['user_id'], $noti_msg);
                        $stmt->execute();
                        $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = " . $with['user_id']);
                    }
                }
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = __('admin_tx_error') . ": " . $e->getMessage();
        }
    }
}

// Search filter
$search_term = trim($_GET['search_term'] ?? '');
$where_d = "d.status = 'pending'";
$where_w = "w.status = 'pending'";

if (!empty($search_term)) {
    $safe_search = $conn->real_escape_string($search_term);
    $where_d .= " AND (u.username LIKE '%$safe_search%' OR u.phone_number LIKE '%$safe_search%' OR d.transaction_id LIKE '%$safe_search%')";
    $where_w .= " AND (u.username LIKE '%$safe_search%' OR u.phone_number LIKE '%$safe_search%' OR w.account_number LIKE '%$safe_search%')";
}

// ဆိုင်းငံ့နေသော (Pending) ငွေဖြည့်တောင်းဆိုမှုများကို Database မှ ခေါ်ယူခြင်း
$pending_deposits = $conn->query("SELECT d.*, u.username, u.phone_number FROM deposits d JOIN users u ON d.user_id = u.id WHERE $where_d ORDER BY d.created_at ASC")->fetch_all(MYSQLI_ASSOC);

// ဆိုင်းငံ့နေသော (Pending) ငွေထုတ်တောင်းဆိုမှုများကို Database မှ ခေါ်ယူခြင်း
$pending_withdraws = $conn->query("SELECT w.*, u.username, u.phone_number FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE $where_w ORDER BY w.created_at ASC")->fetch_all(MYSQLI_ASSOC);
?>

<?php 
$page_title = __('admin_tx_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_control_panel');
    $header_icon = "fas fa-cogs";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($message)): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4 font-bold text-sm text-center shadow-sm">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

    <!-- Search Bar -->
    <div class="mb-6 bg-white p-4 rounded-xl shadow-md flex flex-col sm:flex-row justify-between items-center gap-4">
        <h2 class="font-bold text-gray-700 hidden sm:block"><i class="fas fa-search mr-2"></i> <?= __('admin_tx_search') ?></h2>
        <div class="relative w-full sm:max-w-sm">
            <input type="text" id="txSearchInput" value="<?= htmlspecialchars($search_term) ?>" oninput="liveTxSearch()" placeholder="<?= __('admin_tx_search_placeholder') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
            <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
        </div>
    </div>

    <div id="txListsContainer">
        <!-- Deposits Section -->
        <div class="mb-8" id="deposits-section">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end border-b-2 border-green-500 pb-2 mb-4 gap-2">
                <h2 class="text-lg font-bold text-green-700"><i class="fas fa-arrow-down mr-2"></i> <?= __('admin_tx_deposits_title') ?></h2>
                <form action="admin_export.php" method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="type" value="deposits">
                    <select name="period" class="text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:border-green-500">
                        <option value="all"><?= __('admin_tx_period_all') ?></option>
                        <option value="today"><?= __('admin_tx_period_today') ?></option>
                        <option value="this_week"><?= __('admin_tx_period_week') ?></option>
                        <option value="this_month"><?= __('admin_tx_period_month') ?></option>
                    </select>
                    <button type="submit" class="text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded shadow-sm transition"><i class="fas fa-file-excel mr-1"></i> <?= __('admin_tx_export') ?></button>
                </form>
            </div>
            
            <?php if (count($pending_deposits) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($pending_deposits as $dep): ?>
                        <div class="bg-white p-4 rounded-xl shadow-md border-l-4 border-green-500 animate__animated animate__fadeInUp">
                            <div class="flex flex-col sm:flex-row justify-between items-start mb-2 gap-2">
                                <div>
                                    <p class="font-bold text-gray-800"><?= htmlspecialchars($dep['username']) ?> <span class="text-xs text-gray-500 font-normal">(<?= htmlspecialchars($dep['phone_number']) ?> <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($dep['phone_number']) ?>', this)" class="text-gray-400 hover:text-blue-500" title="<?= __('admin_tx_copy_phone') ?>"><i class="fas fa-copy"></i></button>)</span></p>
                                    <p class="text-sm text-gray-600"><?= __('trx_id_label') ?> <span class="font-bold text-blue-600"><?= htmlspecialchars($dep['transaction_id']) ?></span> <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($dep['transaction_id']) ?>', this)" class="text-gray-400 hover:text-blue-500 ml-1" title="<?= __('admin_tx_copy_trx') ?>"><i class="fas fa-copy"></i></button> (<?= htmlspecialchars($dep['payment_method']) ?>)</p>
                                    <p class="text-xs text-gray-400 mt-1"><?= date('d-M-Y h:i A', strtotime($dep['created_at'])) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-xl text-green-600">+<?= number_format($dep['amount']) ?> <button type="button" onclick="copyToClipboard('<?= $dep['amount'] ?>', this)" class="text-gray-400 hover:text-blue-500 text-sm ml-1" title="<?= __('admin_tx_copy_amt') ?>"><i class="fas fa-copy"></i></button></p>
                                    <?php if (!empty($dep['slip_image_url']) && file_exists('../' . $dep['slip_image_url'])): ?>
                                        <a href="../<?= htmlspecialchars($dep['slip_image_url']) ?>" target="_blank" class="inline-block mt-2 px-2 py-1 bg-blue-50 text-blue-600 rounded text-xs font-bold border border-blue-200 hover:bg-blue-100 transition shadow-sm" title="<?= __('admin_tx_slip') ?>">
                                            <i class="fas fa-image mr-1"></i> <?= __('admin_tx_slip') ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="POST" class="mt-3 flex flex-col space-y-2">
                                <input type="hidden" name="type" value="deposit">
                                <input type="hidden" name="request_id" value="<?= $dep['id'] ?>">
                                <input type="text" name="reject_reason" placeholder="<?= __('admin_tx_reject_reason_ph') ?>" class="w-full py-2 px-3 border border-gray-300 rounded text-sm focus:outline-none focus:border-red-500">
                                <div class="flex space-x-2">
                                    <button type="submit" name="action" value="approve" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded text-sm font-bold shadow-sm transition"><?= __('admin_tx_btn_approve') ?></button>
                                    <button type="submit" name="action" value="reject" class="flex-1 bg-red-500 hover:bg-red-600 text-white py-2 rounded text-sm font-bold shadow-sm transition" onclick="return confirm('<?= __('admin_tx_confirm_reject') ?>');"><?= __('admin_tx_btn_reject') ?></button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm italic bg-white p-4 rounded shadow-sm"><?= __('admin_tx_no_deposits') ?></p>
            <?php endif; ?>
        </div>

        <!-- Withdrawals Section -->
        <div id="withdrawals-section">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end border-b-2 border-red-500 pb-2 mb-4 gap-2">
                <h2 class="text-lg font-bold text-red-700"><i class="fas fa-arrow-up mr-2"></i> <?= __('admin_tx_withdrawals_title') ?></h2>
                <form action="admin_export.php" method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="type" value="withdrawals">
                    <select name="period" class="text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:border-green-500">
                        <option value="all"><?= __('admin_tx_period_all') ?></option>
                        <option value="today"><?= __('admin_tx_period_today') ?></option>
                        <option value="this_week"><?= __('admin_tx_period_week') ?></option>
                        <option value="this_month"><?= __('admin_tx_period_month') ?></option>
                    </select>
                    <button type="submit" class="text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded shadow-sm transition"><i class="fas fa-file-excel mr-1"></i> <?= __('admin_tx_export') ?></button>
                </form>
            </div>
            
            <?php if (count($pending_withdraws) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($pending_withdraws as $with): ?>
                        <div class="bg-white p-4 rounded-xl shadow-md border-l-4 border-red-500 animate__animated animate__fadeInUp">
                            <div class="flex flex-col sm:flex-row justify-between items-start mb-2 gap-2">
                                <div>
                                    <p class="font-bold text-gray-800"><?= htmlspecialchars($with['username']) ?> <span class="text-xs text-gray-500 font-normal">(<?= htmlspecialchars($with['phone_number']) ?> <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($with['phone_number']) ?>', this)" class="text-gray-400 hover:text-blue-500" title="<?= __('admin_tx_copy_phone') ?>"><i class="fas fa-copy"></i></button>)</span></p>
                                    <p class="text-sm text-gray-600"><?= __('acc_no_label') ?> <span class="font-bold text-blue-600"><?= htmlspecialchars($with['account_number']) ?></span> <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($with['account_number']) ?>', this)" class="text-gray-400 hover:text-blue-500 ml-1" title="<?= __('admin_tx_copy_acc') ?>"><i class="fas fa-copy"></i></button> (<?= htmlspecialchars($with['payment_method']) ?>)</p>
                                    <p class="text-xs text-gray-400 mt-1"><?= date('d-M-Y h:i A', strtotime($with['created_at'])) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-xl text-red-600">-<?= number_format($with['amount']) ?> <button type="button" onclick="copyToClipboard('<?= $with['amount'] ?>', this)" class="text-gray-400 hover:text-blue-500 text-sm ml-1" title="<?= __('admin_tx_copy_amt') ?>"><i class="fas fa-copy"></i></button></p>
                                    <?php if(isset($with['fee_amount']) && $with['fee_amount'] > 0): ?>
                                        <p class="text-xs text-gray-500 mt-1"><?= __('admin_export_fee') ?>: <span class="text-red-500">-<?= number_format($with['fee_amount']) ?></span></p>
                                        <p class="text-sm font-bold text-green-600 mt-1 border-t pt-1"><?= __('admin_export_net') ?>: <?= number_format($with['amount'] - $with['fee_amount']) ?> <button type="button" onclick="copyToClipboard('<?= $with['amount'] - $with['fee_amount'] ?>', this)" class="text-gray-400 hover:text-blue-500 text-sm ml-1" title="<?= __('admin_tx_copy_net') ?>"><i class="fas fa-copy"></i></button></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="POST" class="mt-3 flex flex-col space-y-2">
                                <input type="hidden" name="type" value="withdrawal">
                                <input type="hidden" name="request_id" value="<?= $with['id'] ?>">
                                <input type="text" name="admin_payment_account" placeholder="<?= __('admin_tx_admin_acc_ph') ?>" class="w-full py-2 px-3 border border-gray-300 rounded text-sm focus:outline-none focus:border-blue-500">
                                <input type="text" name="reject_reason" placeholder="<?= __('admin_tx_reject_reason_ph') ?>" class="w-full py-2 px-3 border border-gray-300 rounded text-sm focus:outline-none focus:border-red-500">
                                <div class="flex space-x-2">
                                    <button type="submit" name="action" value="approve" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded text-sm font-bold shadow-sm transition" onclick="if(this.form.admin_payment_account.value.trim() === ''){alert('<?= __('admin_tx_alert_admin_acc') ?>'); return false;} return confirm('<?= __('admin_tx_confirm_approve_with') ?>');"><?= __('admin_tx_btn_approve_with') ?></button>
                                    <button type="submit" name="action" value="reject" class="flex-1 bg-red-500 hover:bg-red-600 text-white py-2 rounded text-sm font-bold shadow-sm transition" onclick="return confirm('<?= __('admin_tx_confirm_reject_with') ?>');"><?= __('admin_tx_btn_reject_with') ?></button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm italic bg-white p-4 rounded shadow-sm"><?= __('admin_tx_no_withdrawals') ?></p>
            <?php endif; ?>
        </div>
    </div>
    </div>

<script>
    let txSearchTimeout;
    function liveTxSearch() {
        clearTimeout(txSearchTimeout);
        txSearchTimeout = setTimeout(() => {
            let searchTerm = document.getElementById('txSearchInput').value;
            let url = `admin_transactions.php?search_term=${encodeURIComponent(searchTerm)}`;
            
            let container = document.getElementById('txListsContainer');
            if (container) container.style.opacity = '0.5';
            
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    let parser = new DOMParser();
                    let doc = parser.parseFromString(html, 'text/html');
                    let newContainer = doc.getElementById('txListsContainer');
                    
                    if (newContainer && container) {
                        container.innerHTML = newContainer.innerHTML;
                    }
                    if (container) container.style.opacity = '1';
                })
                .catch(err => console.error('Search error:', err));
        }, 300);
    }

    // Clipboard သို့ Copy ကူးရန် Function (HTTPS မဟုတ်သော Localhost များအတွက်ပါ အလုပ်လုပ်စေရန် Fallback ပါဝင်သည်)
    function copyToClipboard(text, btn) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                showCopySuccess(btn);
            });
        } else {
            let textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try { document.execCommand('copy'); showCopySuccess(btn); } 
            catch (err) { console.error('Fallback: Oops, unable to copy', err); }
            document.body.removeChild(textArea);
        }
    }

    function showCopySuccess(btn) {
        let originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-green-500"></i>';
        setTimeout(() => { btn.innerHTML = originalHTML; }, 1500);
    }
</script>
</body>
</html>