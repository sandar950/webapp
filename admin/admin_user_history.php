<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_permission('can_manage_users');

$target_user_id = intval($_GET['user_id'] ?? 0);

if ($target_user_id <= 0) {
    die("<h2 style='text-align:center; margin-top:50px;'>" . __('admin_user_hist_invalid_id') . "</h2>");
}

$success_message = "";
$error_message = "";

// ဘောင်ချာ ဖျက်သိမ်းပြီး ငွေပြန်အမ်းရန် (Refund)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'refund') {
    $refund_time = $_POST['created_at'] ?? '';
    $refund_user_id = intval($_POST['user_id'] ?? 0);

    if (!empty($refund_time) && $refund_user_id > 0) {
        $conn->begin_transaction();
        try {
            // ဖျက်သိမ်းမည့် ဘောင်ချာထဲမှ Pending ဖြစ်နေသော စုစုပေါင်း ငွေပမာဏကို ရှာခြင်း
            $stmt = $conn->prepare("SELECT SUM(amount - IFNULL(discount_amount, 0)) as refund_amount FROM bets WHERE user_id = ? AND created_at = ? AND status = 'pending' FOR UPDATE");
            $stmt->bind_param("is", $refund_user_id, $refund_time);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $refund_amount = floatval($res['refund_amount']);
            $stmt->close();

            if ($refund_amount > 0) {
                // Refund လုပ်သည့်အခါ မူလကပေးထားခဲ့သော ကော်မရှင်များကိုပါ ပြန်လည်ရုပ်သိမ်းမည် (Reverse Commission)
                $comm_stmt = $conn->prepare("SELECT id, referrer_id, amount FROM commissions WHERE referred_user_id = ? AND created_at >= ? - INTERVAL 2 SECOND AND created_at <= ? + INTERVAL 2 SECOND FOR UPDATE");
                $comm_stmt->bind_param("iss", $refund_user_id, $refund_time, $refund_time);
                $comm_stmt->execute();
                $comm_res = $comm_stmt->get_result();
                while ($comm = $comm_res->fetch_assoc()) {
                    $conn->query("UPDATE users SET balance = balance - {$comm['amount']} WHERE id = {$comm['referrer_id']}");
                    $conn->query("DELETE FROM commissions WHERE id = {$comm['id']}");
                }
                $comm_stmt->close();

                // ၁။ User ၏ Balance သို့ ငွေပြန်အမ်းမည်
                $conn->query("UPDATE users SET balance = balance + $refund_amount WHERE id = $refund_user_id");

                // ၂။ bets table မှ မှတ်တမ်းများကို ဖျက်သိမ်းမည်
                $stmt = $conn->prepare("DELETE FROM bets WHERE user_id = ? AND created_at = ? AND status = 'pending'");
                $stmt->bind_param("is", $refund_user_id, $refund_time);
                $stmt->execute();
                $stmt->close();

                // User ထံ Notification ပို့မည်
                $noti_msg = sprintf(__('admin_user_hist_refund_noti'), number_format($refund_amount));
                $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                $stmt->bind_param("is", $refund_user_id, $noti_msg);
                $stmt->execute();
                $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = $refund_user_id");

                $conn->commit();
                log_activity($_SESSION['user_id'], 'REFUND_BET', "Refunded bet voucher (Time: {$refund_time}) for User ID: {$refund_user_id}. Amount: " . number_format($refund_amount));
                $success_message = sprintf(__('admin_user_hist_refund_success'), number_format($refund_amount));
            } else {
                $conn->rollback();
                $error_message = __('admin_user_hist_no_pending');
            }
        } catch(Exception $e) {
            $conn->rollback();
            $error_message = __('admin_tx_error') . " " . $e->getMessage();
        }
    }
}

// User ၏ အချက်အလက်ကို ဆွဲထုတ်ခြင်း
$stmt = $conn->prepare("SELECT username, phone_number, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$target_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$target_user) {
    die("<h2 style='text-align:center; margin-top:50px;'>" . __('admin_user_hist_not_found') . "</h2>");
}

// 2D သို့မဟုတ် 3D Filter
$type = $_GET['type'] ?? 'all';
$query_condition = "";
if ($type === '2d') {
    $query_condition = " AND LENGTH(bet_number) = 2 ";
} elseif ($type === '3d') {
    $query_condition = " AND LENGTH(bet_number) = 3 ";
}

// Pagination Setup
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get Total Rows for Pagination
$count_query = "SELECT COUNT(DISTINCT created_at) as total_rows FROM bets WHERE user_id = ?" . $query_condition;
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $target_user_id);
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();
$total_rows = $count_res['total_rows'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_rows / $limit);

// User ထိုးထားသော မှတ်တမ်းများကို ဆွဲထုတ်ခြင်း (Voucher ပုံစံ)
$query = "SELECT 
            created_at, 
            COUNT(id) as total_kwek, 
            SUM(amount - IFNULL(discount_amount, 0)) as total_amount,
            GROUP_CONCAT(bet_number SEPARATOR ', ') as bet_numbers,
            SUM(CASE WHEN status = 'win' THEN 1 ELSE 0 END) as win_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
          FROM bets WHERE user_id = ?" . $query_condition . "
          GROUP BY created_at ORDER BY created_at DESC LIMIT ?, ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $target_user_id, $offset, $limit);
$stmt->execute();
$bets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php 
$page_title = __('admin_user_hist_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_user_hist_header_title');
    $header_icon = "fas fa-list-alt";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <style>
        @media print {
            body > div.bg-gray-800 { display: none !important; }
            body > div.bg-white.shadow-sm.border-b { display: none !important; }
            .shadow-md, .shadow-xl { box-shadow: none !important; }
            body { background-color: white !important; }
        }
    </style>

    <div class="p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- User Info Card -->
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded shadow-sm">
            <h2 class="font-bold text-blue-800 text-lg mb-1"><?= htmlspecialchars($target_user['username']) ?></h2>
            <p class="text-gray-600 text-sm mb-1"><i class="fas fa-phone-alt mr-1"></i> <?= htmlspecialchars($target_user['phone_number']) ?></p>
            <p class="text-gray-600 text-sm"><i class="fas fa-wallet mr-1"></i> <?= __('admin_user_hist_balance') ?> <span class="font-bold text-blue-700"><?= number_format($target_user['balance'], 2) ?> <?= __('currency') ?></span></p>
        </div>

        <!-- Filter Tabs -->
        <div class="bg-white flex justify-around border-b text-sm font-bold text-gray-500 shadow-sm mb-4 rounded-xl overflow-hidden print:hidden">
            <a href="?user_id=<?= $target_user_id ?>&type=all" class="py-4 w-1/3 text-center <?= $type == 'all' ? 'text-blue-600 border-b-4 border-blue-600 bg-blue-50' : 'hover:bg-gray-50' ?>"><?= __('admin_user_hist_tab_all') ?></a>
            <a href="?user_id=<?= $target_user_id ?>&type=2d" class="py-4 w-1/3 text-center <?= $type == '2d' ? 'text-blue-600 border-b-4 border-blue-600 bg-blue-50' : 'hover:bg-gray-50' ?>"><?= __('admin_user_hist_tab_2d') ?></a>
            <a href="?user_id=<?= $target_user_id ?>&type=3d" class="py-4 w-1/3 text-center <?= $type == '3d' ? 'text-blue-600 border-b-4 border-blue-600 bg-blue-50' : 'hover:bg-gray-50' ?>"><?= __('admin_user_hist_tab_3d') ?></a>
        </div>

        <div class="flex justify-between items-center mb-4 print:hidden">
            <h3 class="font-bold text-gray-700"><?= __('admin_user_hist_bet_history') ?> (<?= strtoupper($type) ?>)</h3>
            <button onclick="window.print()" class="bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition">
                <i class="fas fa-print mr-1"></i> <?= __('admin_user_hist_print') ?>
            </button>
        </div>

        <!-- Print Header (Hidden on screen) -->
        <div class="hidden print:block text-center mb-6 border-b pb-4">
            <h2 class="text-2xl font-bold text-gray-800 mb-1"><?= __('admin_user_hist_print_title') ?></h2>
            <p class="text-gray-600 text-sm"><?= __('admin_user_hist_print_user') ?><?= htmlspecialchars($target_user['username']) ?> (<?= htmlspecialchars($target_user['phone_number']) ?>)</p>
            <p class="text-gray-600 text-sm"><?= __('admin_user_hist_print_category') ?><?= strtoupper($type) ?> | <?= __('admin_user_hist_print_date') ?><?= date('d-M-Y h:i A') ?></p>
        </div>

        <?php if (count($bets) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($bets as $bet): ?>
                    <?php $voucher_id = strtoupper(substr(md5($bet['created_at'] . $target_user_id), 0, 8)); ?>
                    <div id="voucher_<?= $voucher_id ?>" class="bg-white rounded-xl shadow-md p-4 border-l-4 print:border print:shadow-none print:mb-4 <?= $bet['win_count'] > 0 ? 'border-green-500' : ($bet['pending_count'] > 0 ? 'border-yellow-500' : 'border-red-500') ?>">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="bg-gray-200 text-gray-700 text-[10px] px-2 py-0.5 rounded font-mono">#<?= $voucher_id ?></span>
                                    <p class="text-xs text-gray-500"><i class="far fa-clock mr-1"></i> <?= date('d-M-Y h:i A', strtotime($bet['created_at'])) ?></p>
                                    <button onclick="downloadVoucher('<?= $voucher_id ?>')" class="text-blue-500 hover:text-blue-700 ml-2 download-btn print:hidden" title="<?= __('download_voucher') ?>"><i class="fas fa-download"></i></button>
                                </div>
                                <p class="font-bold text-gray-800 mt-2">
                                    <span class="text-blue-600 text-lg"><?= htmlspecialchars($bet['total_kwek']) ?></span> <?= __('kwek') ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1 max-w-[250px] break-words leading-relaxed">
                                    [<?= htmlspecialchars($bet['bet_numbers']) ?>]
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500 mb-1"><?= __('admin_user_hist_total_bet') ?></p>
                                <p class="font-bold text-red-600 mb-2"><?= number_format($bet['total_amount']) ?> <span class="text-xs font-normal"><?= __('currency') ?></span></p>
                                <?php if ($bet['win_count'] > 0): ?>
                                    <span class="bg-green-100 text-green-700 text-[10px] px-2 py-1 rounded border border-green-300"><?= __('status_win') ?></span>
                                <?php elseif ($bet['pending_count'] > 0): ?>
                                    <span class="bg-yellow-100 text-yellow-700 text-[10px] px-2 py-1 rounded border border-yellow-300"><?= __('status_pending') ?></span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-700 text-[10px] px-2 py-1 rounded border border-red-300"><?= __('status_lose') ?></span>
                                <?php endif; ?>
                                
                                <?php if ($bet['pending_count'] > 0 && $bet['win_count'] == 0): ?>
                                    <form method="POST" class="mt-2 print:hidden">
                                        <input type="hidden" name="action" value="refund">
                                        <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                                        <input type="hidden" name="created_at" value="<?= $bet['created_at'] ?>">
                                        <button type="submit" onclick="return confirm('<?= __('admin_user_hist_confirm_refund') ?>');" class="bg-red-500 hover:bg-red-600 text-white text-[10px] px-3 py-1.5 rounded shadow-sm transition"><i class="fas fa-undo mr-1"></i> <?= __('admin_user_hist_btn_refund') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center mt-6 mb-2 space-x-2 print:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?user_id=<?= $target_user_id ?>&type=<?= htmlspecialchars($type) ?>&page=<?= $page - 1 ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-left text-xs"></i></a>
                    <?php endif; ?>
                    
                    <span class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold text-gray-700 shadow-sm"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?user_id=<?= $target_user_id ?>&type=<?= htmlspecialchars($type) ?>&page=<?= $page + 1 ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-right text-xs"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-md p-10 text-center">
                <i class="fas fa-receipt text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-gray-500 italic text-sm"><?= __('admin_user_hist_no_records') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- html2canvas library for downloading vouchers -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function downloadVoucher(voucherId) {
            const element = document.getElementById('voucher_' + voucherId);
            
            const wrapper = document.createElement('div');
            wrapper.style.padding = '20px';
            wrapper.style.backgroundColor = '#f3f4f6';
            wrapper.style.width = '400px';
            wrapper.style.position = 'fixed';
            wrapper.style.left = '-9999px';
            wrapper.style.top = '0';
            wrapper.style.zIndex = '-1';
            
            const header = document.createElement('div');
            header.style.textAlign = 'center';
            header.style.marginBottom = '15px';
            header.innerHTML = '<h2 style="font-weight:bold; color:#1a428a; font-size:20px; margin:0;">Thai 2D3D</h2><p style="color:#6b7280; font-size:12px; margin-top:2px;">Official Betting Voucher</p><div style="border-bottom:2px dashed #cbd5e1; margin-top:10px;"></div>';
            
            const clone = element.cloneNode(true);
            clone.style.backgroundColor = '#ffffff';
            clone.style.borderRadius = '12px';
            clone.style.padding = '20px';
            clone.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
            
            const downloadBtns = clone.querySelectorAll('.download-btn');
            downloadBtns.forEach(btn => btn.remove());
            const actionForms = clone.querySelectorAll('form');
            actionForms.forEach(form => form.remove());
            
            wrapper.appendChild(header);
            wrapper.appendChild(clone);
            document.body.appendChild(wrapper);

            html2canvas(wrapper, {
                scale: 2,
                backgroundColor: '#f3f4f6',
                logging: false
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Thai2D3D_Voucher_' + voucherId + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                document.body.removeChild(wrapper);
            }).catch(err => {
                console.error('Error generating voucher image:', err);
                if(document.body.contains(wrapper)) document.body.removeChild(wrapper);
                alert("<?= __('voucher_save_error') ?>");
            });
        }
    </script>
</body>
</html>