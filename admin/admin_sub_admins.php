<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

// Sub-Admin အသစ်ထည့်ရန်
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_sub_admin'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_phone = trim($_POST['new_phone'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if (!empty($new_username) && !empty($new_phone) && strlen($new_password) >= 6) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE phone_number = ?");
        $check_stmt->bind_param("s", $new_phone);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error_message = __('admin_sub_phone_exists');
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $insert_stmt = $conn->prepare("INSERT INTO users (username, phone_number, password, role, verification_status) VALUES (?, ?, ?, 'sub_admin', 'approved')");
            $insert_stmt->bind_param("sss", $new_username, $new_phone, $hashed_password);

            if ($insert_stmt->execute()) {
                $new_user_id = $insert_stmt->insert_id;
                // Default permissions ထည့်ပေးမည်
                $perm_stmt = $conn->prepare("INSERT INTO sub_admin_permissions (user_id) VALUES (?)");
                $perm_stmt->bind_param("i", $new_user_id);
                $perm_stmt->execute();
                log_activity($_SESSION['user_id'], 'ADD_SUB_ADMIN', "Created new Sub-Admin '{$new_username}' (Phone: {$new_phone})");
                $success_message = sprintf(__('admin_sub_add_success'), $new_username);
                $perm_stmt->close();
            } else {
                $error_message = __('admin_sub_add_error');
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    } else {
        $error_message = __('admin_sub_fill_all');
    }
}

// Sub-Admin ကို ဖျက်ရန်
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_sub_admin'])) {
    $target_user_id = intval($_POST['target_user_id'] ?? 0);

    if ($target_user_id > 1) { // Main Admin ကို ဖျက်လို့မရအောင် ကာကွယ်ခြင်း
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'sub_admin'");
        $stmt->bind_param("i", $target_user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            log_activity($_SESSION['user_id'], 'DELETE_SUB_ADMIN', "Deleted Sub-Admin ID: {$target_user_id}");
            $success_message = __('admin_sub_delete_success');
        } else {
            $error_message = __('admin_sub_delete_error');
        }
        $stmt->close();
    } else {
        $error_message = __('admin_sub_cannot_delete_main');
    }
}

// Sub-Admin အားလုံးကို Database မှ ဆွဲထုတ်ခြင်း
$sub_admins = $conn->query("SELECT id, username, phone_number, created_at FROM users WHERE role = 'sub_admin' ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
?>

<?php 
$page_title = __('admin_sub_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_sub_header_title');
    $header_icon = "fas fa-user-shield";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Add New Sub-Admin Form -->
        <form method="POST" action="" class="bg-white p-4 sm:p-6 rounded-xl shadow-md border-t-4 border-primary mb-8">
            <h2 class="font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-user-plus text-primary mr-2"></i> <?= __('admin_sub_add_new_title') ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2"><?= __('admin_sub_label_name') ?></label>
                    <input type="text" name="new_username" placeholder="<?= __('admin_sub_ph_name') ?>" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:border-blue-500" required>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2"><?= __('admin_sub_label_phone') ?></label>
                    <input type="text" name="new_phone" placeholder="09xxxxxxxxx" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:border-blue-500" required>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2"><?= __('admin_sub_label_password') ?></label>
                    <input type="text" name="new_password" placeholder="<?= __('admin_sub_ph_password') ?>" minlength="6" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:border-blue-500" required>
                </div>
            </div>
            <div class="text-right">
                <button type="submit" name="add_sub_admin" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg text-sm font-bold shadow-sm transition"><i class="fas fa-save mr-1"></i> <?= __('admin_sub_btn_create') ?></button>
            </div>
        </form>

        <!-- Sub-Admins List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="font-bold text-gray-700"><?= sprintf(__('admin_sub_list_title'), count($sub_admins)) ?></h2>
            </div>
            <?php if (count($sub_admins) > 0): ?>
                <!-- Mobile View: Cards -->
                <div class="md:hidden divide-y divide-gray-100">
                    <?php foreach ($sub_admins as $u): ?>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <p class="font-bold text-gray-800"><?= htmlspecialchars($u['username']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($u['phone_number']) ?></p>
                                </div>
                                <span class="text-[10px] bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded border border-yellow-200 font-bold"><?= __('admin_sub_badge') ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <p class="text-[10px] text-gray-400"><i class="far fa-clock mr-1"></i> <?= date('d-M-Y h:i A', strtotime($u['created_at'])) ?></p>
                                <form method="POST" action="" class="inline-block" onsubmit="return confirm('<?= __('admin_sub_confirm_delete') ?>');">
                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="delete_sub_admin" class="bg-red-100 text-red-700 hover:bg-red-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition"><i class="fas fa-trash mr-1"></i> <?= __('admin_sub_btn_delete') ?></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop View: Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full leading-normal text-left">
                        <thead>
                            <tr class="bg-blue-50 text-blue-800 font-bold border-b-2 border-blue-200">
                                <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_sub_col_id') ?></th>
                                <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_sub_label_name') ?></th>
                                <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_sub_label_phone') ?></th>
                                <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_sub_col_date') ?></th>
                                <th class="px-5 py-4 text-sm whitespace-nowrap text-center"><?= __('admin_sub_col_action') ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($sub_admins as $u): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-5 py-4 text-sm text-gray-600 font-bold">#<?= $u['id'] ?></td>
                                    <td class="px-5 py-4 text-sm text-gray-800 font-bold"><?= htmlspecialchars($u['username']) ?> <span class="ml-2 text-[10px] bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded border border-yellow-200"><?= __('admin_sub_badge') ?></span></td>
                                    <td class="px-5 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['phone_number']) ?></td>
                                    <td class="px-5 py-4 text-xs text-gray-500"><?= date('d-M-Y h:i A', strtotime($u['created_at'])) ?></td>
                                    <td class="px-5 py-3 whitespace-nowrap text-center">
                                        <form method="POST" action="" class="inline-block" onsubmit="return confirm('<?= __('admin_sub_confirm_delete') ?>');"><input type="hidden" name="target_user_id" value="<?= $u['id'] ?>"><button type="submit" name="delete_sub_admin" class="bg-red-100 text-red-700 hover:bg-red-200 px-3 py-2 rounded-lg text-sm font-bold shadow-sm transition"><i class="fas fa-trash mr-1"></i> <?= __('admin_sub_btn_delete') ?></button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="px-5 py-8 text-center text-gray-500 italic">
                    <i class="fas fa-user-shield text-4xl text-gray-300 mb-3 block"></i>
                    <?= __('admin_sub_no_records') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>