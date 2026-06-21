<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_permission('can_manage_users');

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

// Pagination Setup
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get Total Rows for Pagination
$count_stmt = $conn->prepare("SELECT COUNT(id) as total_rows FROM users WHERE referred_by = ?");
$count_stmt->bind_param("i", $target_user_id);
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();
$total_rows = $count_res['total_rows'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_rows / $limit);

// ဖိတ်ခေါ်ထားသော သူငယ်ချင်းစာရင်းကို ဆွဲထုတ်ခြင်း (သူတို့ဆီကရခဲ့တဲ့ ကော်မရှင်ကိုပါ ပေါင်းထည့်တွက်ချက်မည်)
$query = "SELECT u.id, u.username, u.phone_number, u.created_at, 
          (SELECT SUM(amount) FROM commissions WHERE referred_user_id = u.id AND referrer_id = ?) as generated_commission 
          FROM users u 
          WHERE u.referred_by = ? 
          ORDER BY u.created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $target_user_id, $target_user_id, $offset, $limit);
$stmt->execute();
$referrals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php 
$page_title = __('admin_user_referrals_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_user_referrals_header_title');
    $header_icon = "fas fa-users text-orange-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-6 pt-0">
        <div class="flex justify-between items-center border-b pb-3 mb-6 mt-4">
            <a href="admin_users.php" class="text-blue-600 hover:underline text-sm font-bold"><i class="fas fa-arrow-left mr-1"></i> <?= __('admin_user_referrals_back_to_users') ?></a>
            <a href="admin_export.php?type=user_referrals&user_id=<?= $target_user_id ?>" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-sm font-bold shadow-sm transition">
                <i class="fas fa-file-excel mr-1"></i> Export
            </a>
        </div>

        <!-- User Info Card -->
        <div class="bg-orange-50 border-l-4 border-orange-500 p-4 mb-6 rounded shadow-sm flex justify-between items-center">
            <div>
                <h2 class="font-bold text-orange-800 text-lg mb-1"><?= htmlspecialchars($target_user['username']) ?></h2>
                <p class="text-gray-600 text-sm mb-1"><i class="fas fa-phone-alt mr-1"></i> <?= htmlspecialchars($target_user['phone_number']) ?></p>
            </div>
            <div class="text-right">
                <p class="text-sm text-orange-600 font-bold mb-1"><?= __('admin_user_referrals_total_invited') ?></p>
                <p class="text-2xl font-bold text-orange-600"><?= number_format($total_rows) ?> <?= __('unit_users') ?></p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="font-bold text-gray-700"><?= __('admin_user_referrals_friend_list') ?></h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal text-left">
                    <thead>
                        <tr class="bg-orange-50 text-orange-800 font-bold border-b-2 border-orange-200">
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_users_col_id') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_users_col_name') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_users_label_phone') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap text-right"><?= __('admin_export_given_comm') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap text-right"><?= __('admin_users_col_date') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($referrals) > 0): ?>
                            <?php foreach ($referrals as $ref): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50 transition duration-150">
                                <td class="px-5 py-4 text-sm text-gray-600 font-bold">#<?= $ref['id'] ?></td>
                                <td class="px-5 py-4 text-sm text-gray-800 font-bold"><?= htmlspecialchars($ref['username']) ?></td>
                                <td class="px-5 py-4 text-sm text-gray-600"><?= htmlspecialchars($ref['phone_number']) ?></td>
                                <td class="px-5 py-4 text-sm font-bold text-green-600 text-right">+ <?= number_format($ref['generated_commission'] ?? 0) ?> Ks</td>
                                <td class="px-5 py-4 text-xs text-gray-500 text-right"><?= date('d-M-Y h:i A', strtotime($ref['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-gray-500 italic">
                                    <i class="fas fa-users-slash text-4xl text-gray-300 mb-3 block"></i>
                                    <?= __('admin_user_referrals_no_records') ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
    </div>
</body>
</html>