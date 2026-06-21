<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Manage Users လုပ်ပိုင်ခွင့်ရှိသူများသာ ဝင်ရောက်နိုင်မည်
require_permission('can_manage_users');

$success_message = "";
$error_message = "";

// Unban လုပ်ရန် Form Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unban_user'])) {
    $target_user_id = intval($_POST['target_user_id'] ?? 0);

    if ($target_user_id > 1) { 
        $stmt = $conn->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
        $stmt->bind_param("i", $target_user_id);
        if ($stmt->execute()) {
            log_activity($_SESSION['user_id'], 'UNBAN_USER', "Unbanned User ID: {$target_user_id}");
            $success_message = sprintf(__('admin_users_ban_success'), $target_user_id, __('admin_users_unban'));
        } else {
            $error_message = __('admin_tx_error');
        }
        $stmt->close();
    }
}

// Banned Users များကို ဆွဲထုတ်ခြင်း
$query = "SELECT id, username, phone_number, balance, created_at FROM users WHERE is_banned = 1 ORDER BY id DESC";
$result = $conn->query($query);
$banned_users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<?php 
$page_title = "Admin - Banned Users";
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-5xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">
    <?php
    $header_title = "ပိတ်ပင်ထားသော User များ";
    $header_icon = "fas fa-user-slash text-red-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-6 pt-0">
        <div class="flex justify-between items-center border-b pb-3 mb-6 mt-4">
            <a href="admin_users.php" class="text-blue-600 hover:underline text-sm font-bold"><i class="fas fa-arrow-left mr-1"></i> Users စာရင်းသို့ ပြန်သွားမည်</a>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 shadow-sm text-sm font-bold"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 shadow-sm text-sm font-bold"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                <h2 class="font-bold text-gray-700"><i class="fas fa-ban text-red-500 mr-2"></i> Banned Users စာရင်း (<?= count($banned_users) ?> ဦး)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal text-left">
                    <thead>
                        <tr class="bg-red-50 text-red-800 font-bold border-b-2 border-red-200">
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_users_col_id') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_users_col_name') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_users_label_phone') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('balance') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap text-center"><?= __('admin_users_col_action') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($banned_users) > 0): ?>
                            <?php foreach ($banned_users as $u): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50 transition">
                                <td class="px-5 py-4 text-sm text-gray-600 font-bold">#<?= $u['id'] ?></td>
                                <td class="px-5 py-4 text-sm text-gray-800 font-bold"><?= htmlspecialchars($u['username']) ?></td>
                                <td class="px-5 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['phone_number']) ?></td>
                                <td class="px-5 py-4 text-sm font-bold text-blue-600"><?= number_format($u['balance'], 2) ?> <?= __('currency') ?></td>
                                <td class="px-5 py-3 whitespace-nowrap text-center">
                                    <form method="POST" action="" class="inline-block">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="unban_user" class="bg-green-100 text-green-700 hover:bg-green-200 px-3 py-2 rounded-lg text-sm font-bold shadow-sm transition" onclick="return confirm('<?= __('admin_users_confirm_unban') ?>');">
                                            <i class="fas fa-unlock mr-1"></i> <?= __('admin_users_unban') ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-gray-500 italic">
                                    <i class="fas fa-user-check text-3xl mb-3 text-gray-300 block"></i>
                                    ပိတ်ပင်ထားသော User မရှိသေးပါ။
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>