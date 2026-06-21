<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Admin (User ID 1) သာ ဝင်ခွင့်ပြုမည်
require_main_admin();

$target_user_id = intval($_GET['user_id'] ?? 0);

if ($target_user_id <= 0) {
    die("<h2 style='text-align:center; margin-top:50px;'>" . __('admin_user_hist_invalid_id') . "</h2>");
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

// စုစုပေါင်း ရရှိထားသော ကော်မရှင်ကို တွက်ချက်ခြင်း
$stmt_total = $conn->prepare("SELECT SUM(amount) as total_comm FROM commissions WHERE referrer_id = ?");
$stmt_total->bind_param("i", $target_user_id);
$stmt_total->execute();
$total_res = $stmt_total->get_result()->fetch_assoc();
$total_commission = $total_res['total_comm'] ?? 0;
$stmt_total->close();

// Pagination Setup
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get Total Rows for Pagination
$count_stmt = $conn->prepare("SELECT COUNT(id) as total_rows FROM commissions WHERE referrer_id = ?");
$count_stmt->bind_param("i", $target_user_id);
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();
$total_rows = $count_res['total_rows'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_rows / $limit);

// ကော်မရှင်မှတ်တမ်းများကို ဆွဲထုတ်ခြင်း (သူငယ်ချင်းအမည်ပါ ပူးတွဲယူမည်)
$query = "SELECT c.amount, c.description, c.created_at, u.username as friend_name 
          FROM commissions c 
          JOIN users u ON c.referred_user_id = u.id 
          WHERE c.referrer_id = ? 
          ORDER BY c.created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $target_user_id, $offset, $limit);
$stmt->execute();
$commissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php 
$page_title = __('admin_user_commissions_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_user_commissions_header_title');
    $header_icon = "fas fa-hand-holding-usd";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-6 pt-0">
        <!-- User Info Card -->
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 mb-6 rounded shadow-sm flex justify-between items-center">
            <div>
                <h2 class="font-bold text-emerald-800 text-lg mb-1"><?= htmlspecialchars($target_user['username']) ?></h2>
                <p class="text-gray-600 text-sm mb-1"><i class="fas fa-phone-alt mr-1"></i> <?= htmlspecialchars($target_user['phone_number']) ?></p>
                <p class="text-gray-600 text-sm"><i class="fas fa-wallet mr-1"></i> <?= __('admin_user_hist_balance') ?> <span class="font-bold text-emerald-700"><?= number_format($target_user['balance'], 2) ?> <?= __('currency') ?></span></p>
            </div>
            <div class="text-right">
                <p class="text-sm text-emerald-600 font-bold mb-1"><?= __('admin_user_commissions_total_earned') ?></p>
                <p class="text-2xl font-bold text-emerald-600">+ <?= number_format($total_commission) ?> Ks</p>
            </div>
        </div>

        <?php if (count($commissions) > 0): ?>
            <div class="space-y-3">
                <?php foreach ($commissions as $comm): ?>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex justify-between items-center hover:bg-gray-50 transition">
                        <div>
                            <p class="text-gray-800 text-sm font-bold mb-1"><i class="fas fa-user-friends text-blue-500 mr-1"></i> <?= htmlspecialchars($comm['friend_name']) ?></p>
                            <p class="text-[10px] text-gray-400"><i class="far fa-clock"></i> <?= date('d-M-Y h:i A', strtotime($comm['created_at'])) ?> (<?= htmlspecialchars($comm['description']) ?>)</p>
                        </div>
                        <p class="font-bold text-green-600 text-base">+ <?= number_format($comm['amount']) ?> Ks</p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center mt-6 mb-2 space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?user_id=<?= $target_user_id ?>&page=<?= $page - 1 ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-left text-xs"></i></a>
                    <?php endif; ?>
                    <span class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold text-gray-700 shadow-sm"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                    <?php if ($page < $total_pages): ?>
                        <a href="?user_id=<?= $target_user_id ?>&page=<?= $page + 1 ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-right text-xs"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-md p-10 text-center">
                <i class="fas fa-box-open text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-gray-500 text-sm"><?= __('admin_commissions_no_records') ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>