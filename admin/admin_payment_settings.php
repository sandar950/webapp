<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

// Table ရှိ/မရှိ စစ်ဆေးပြီး မရှိပါက တည်ဆောက်၍ Migrate လုပ်မည်
$check_table = $conn->query("SHOW TABLES LIKE 'payment_accounts'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE payment_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_method VARCHAR(50) NOT NULL,
        account_name VARCHAR(100) NOT NULL,
        account_number VARCHAR(50) NOT NULL,
        logo_url VARCHAR(255) NULL,
        qr_image_url VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Migrate old settings
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('kbz_pay_account','kbz_pay_name','kbz_pay_qr_url','wave_pay_account','wave_pay_name','wave_pay_qr_url')");
    $sets = [];
    if ($stmt) {
        while($r = $stmt->fetch_assoc()) $sets[$r['setting_key']] = $r['setting_value'];
    }
    
    $kbz_acc = $sets['kbz_pay_account'] ?? ''; $kbz_name = $sets['kbz_pay_name'] ?? ''; $kbz_qr = $sets['kbz_pay_qr_url'] ?? '';
    if(!empty($kbz_acc)) $conn->query("INSERT INTO payment_accounts (payment_method, account_name, account_number, qr_image_url) VALUES ('KBZ Pay', '".$conn->real_escape_string($kbz_name)."', '".$conn->real_escape_string($kbz_acc)."', '".$conn->real_escape_string($kbz_qr)."')");
    
    $wave_acc = $sets['wave_pay_account'] ?? ''; $wave_name = $sets['wave_pay_name'] ?? ''; $wave_qr = $sets['wave_pay_qr_url'] ?? '';
    if(!empty($wave_acc)) $conn->query("INSERT INTO payment_accounts (payment_method, account_name, account_number, qr_image_url) VALUES ('Wave Pay', '".$conn->real_escape_string($wave_name)."', '".$conn->real_escape_string($wave_acc)."', '".$conn->real_escape_string($wave_qr)."')");
}

// Update Schema for existing tables
$check_logo_col = $conn->query("SHOW COLUMNS FROM payment_accounts LIKE 'logo_url'");
if ($check_logo_col && $check_logo_col->num_rows == 0) {
    $conn->query("ALTER TABLE payment_accounts ADD logo_url VARCHAR(255) NULL AFTER account_number");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // AJAX ဖြင့် အစီအစဉ် (Sort Order) ပြောင်းလဲရန် တောင်းဆိုလာသောအခါ
    if (isset($_POST['action']) && $_POST['action'] === 'update_order') {
        $order = json_decode($_POST['order'], true);
        if (is_array($order)) {
            $stmt = $conn->prepare("UPDATE payment_accounts SET sort_order = ? WHERE id = ?");
            foreach ($order as $index => $id) {
                $sort = $index + 1;
                $id = intval($id);
                $stmt->bind_param("ii", $sort, $id);
                $stmt->execute();
            }
            $stmt->close();
            echo json_encode(['success' => true]);
            exit();
        }
    }

    if (isset($_POST['add_account'])) {
        $method = trim($_POST['payment_method'] ?? '');
        $name = trim($_POST['account_name'] ?? '');
        $number = trim($_POST['account_number'] ?? '');
        $logo_url = trim($_POST['logo_url'] ?? '');
        $qr_url = trim($_POST['qr_url'] ?? '');

        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/payments/logos/';
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $new_filename = 'logo_' . time() . '_' . uniqid() . '.' . $ext;
                require_once __DIR__ . '/../core/image_helper.php';
                if (compressImage($_FILES['logo_file']['tmp_name'], $upload_dir . $new_filename, 60)) {
                    $logo_url = $upload_dir . $new_filename;
                }
            }
        }

        if (isset($_FILES['qr_file']) && $_FILES['qr_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/payments/';
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['qr_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $new_filename = 'qr_' . time() . '_' . uniqid() . '.' . $ext;
                require_once __DIR__ . '/../core/image_helper.php';
                if (compressImage($_FILES['qr_file']['tmp_name'], $upload_dir . $new_filename, 60)) {
                    $qr_url = $upload_dir . $new_filename;
                }
            }
        }

        $logo_url = !empty($logo_url) ? $logo_url : null;
        $qr_url = !empty($qr_url) ? $qr_url : null;

        if (!empty($method) && !empty($name) && !empty($number)) {
            $stmt = $conn->prepare("INSERT INTO payment_accounts (payment_method, account_name, account_number, logo_url, qr_image_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $method, $name, $number, $logo_url, $qr_url);
            if($stmt->execute()) {
                $success_message = __('admin_payment_add_success');
                log_activity($_SESSION['user_id'], 'ADD_PAYMENT_ACCOUNT', "Added payment account: $method - $number");
            } else {
                $error_message = __('admin_payment_add_error');
            }
            $stmt->close();
        } else {
            $error_message = __('admin_payment_fill_all');
        }
    } elseif (isset($_POST['toggle_status'])) {
        $id = intval($_POST['account_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        
        $stmt = $conn->prepare("UPDATE payment_accounts SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        if($stmt->execute()) {
            $success_message = __('admin_payment_status_success');
        }
        $stmt->close();
    } elseif (isset($_POST['delete_account'])) {
        $id = intval($_POST['account_id']);
        
        $img_stmt = $conn->prepare("SELECT logo_url, qr_image_url FROM payment_accounts WHERE id = ?");
        $img_stmt->bind_param("i", $id);
        $img_stmt->execute();
        $res = $img_stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['logo_url']) && file_exists($row['logo_url'])) @unlink($row['logo_url']);
            if (!empty($row['qr_image_url']) && file_exists($row['qr_image_url'])) @unlink($row['qr_image_url']);
        }
        $img_stmt->close();

        $stmt = $conn->prepare("DELETE FROM payment_accounts WHERE id = ?");
        $stmt->bind_param("i", $id);
        if($stmt->execute()) {
            $success_message = __('admin_payment_delete_success');
            log_activity($_SESSION['user_id'], 'DELETE_PAYMENT_ACCOUNT', "Deleted payment account ID: $id");
        }
        $stmt->close();
    }
}

$accounts = $conn->query("SELECT * FROM payment_accounts ORDER BY sort_order ASC, created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<?php 
$page_title = __('admin_payment_accounts') . " - Admin";
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_payment_accounts');
    $header_icon = "fas fa-wallet text-blue-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Add New Account Form -->
        <form method="POST" action="" enctype="multipart/form-data" class="bg-white p-4 sm:p-6 rounded-xl shadow-md mb-8 border-t-4 border-blue-500">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-plus-circle text-blue-500 mr-2"></i><?= __('admin_payment_add_new') ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_payment_method') ?></label>
                    <input type="text" name="payment_method" class="w-full py-2 px-3 border rounded focus:border-blue-500 focus:outline-none" required>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_payment_account_no') ?></label>
                    <input type="text" name="account_number" class="w-full py-2 px-3 border rounded focus:border-blue-500 focus:outline-none" required>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_payment_account_name') ?></label>
                    <input type="text" name="account_name" class="w-full py-2 px-3 border rounded focus:border-blue-500 focus:outline-none" required>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_payment_logo') ?></label>
                    <div class="flex flex-col gap-2">
                        <input type="text" name="logo_url" placeholder="URL (https://...)" class="w-full py-2 px-3 border rounded-lg focus:border-blue-500 focus:outline-none">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 font-bold"><?= __('admin_payment_or') ?></span>
                            <input type="file" name="logo_file" accept="image/png, image/jpeg, image/jpg, image/webp" class="w-full text-sm text-gray-500 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_payment_qr') ?></label>
                    <div class="flex flex-col gap-2">
                        <input type="text" name="qr_url" placeholder="URL (https://...)" class="w-full py-2 px-3 border rounded-lg focus:border-blue-500 focus:outline-none">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 font-bold"><?= __('admin_payment_or') ?></span>
                            <input type="file" name="qr_file" accept="image/png, image/jpeg, image/jpg, image/webp" class="w-full text-sm text-gray-500 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" name="add_account" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg text-sm shadow-sm transition"><i class="fas fa-save mr-2"></i> <?= __('admin_payment_btn_add') ?></button>
        </form>

        <!-- Existing Accounts List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                <h2 class="font-bold text-gray-700"><i class="fas fa-list text-purple-500 mr-2"></i> <?= __('admin_payment_current_list') ?></h2>
            </div>
            <?php if (count($accounts) > 0): ?>
                <!-- Mobile View: Cards -->
                <div id="sortable-list-mobile" class="md:hidden divide-y divide-gray-100">
                    <?php foreach($accounts as $acc): ?>
                        <div class="p-4 flex items-center gap-3" data-id="<?= $acc['id'] ?>">
                            <div class="cursor-move text-gray-400 hover:text-gray-600"><i class="fas fa-grip-vertical"></i></div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center gap-2">
                                        <?php if(!empty($acc['logo_url'])): ?><img src="<?= htmlspecialchars($acc['logo_url']) ?>" class="w-6 h-6 rounded-full object-cover border shadow-sm"><?php else: ?><div class="w-6 h-6 rounded-full bg-blue-100 text-blue-500 flex items-center justify-center shadow-sm"><i class="fas fa-university text-xs"></i></div><?php endif; ?>
                                        <span class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($acc['payment_method']) ?></span>
                                    </div>
                                    <form method="POST"><input type="hidden" name="account_id" value="<?= $acc['id'] ?>"><input type="hidden" name="current_status" value="<?= $acc['is_active'] ?>"><button type="submit" name="toggle_status" class="px-2 py-0.5 rounded-full text-[10px] font-bold transition <?= $acc['is_active'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-red-100 text-red-700 hover:bg-red-200' ?>"><?= $acc['is_active'] ? __('admin_payment_active') : __('admin_payment_inactive') ?></button></form>
                                </div>
                                <p class="font-bold text-blue-600 tracking-wider text-sm"><?= htmlspecialchars($acc['account_number']) ?></p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($acc['account_name']) ?></p>
                            </div>
                            <div class="flex justify-end items-center gap-2 mt-2">
                                <?php if(!empty($acc['qr_image_url'])): ?><img src="<?= htmlspecialchars($acc['qr_image_url']) ?>" class="w-8 h-8 object-cover rounded border cursor-pointer" onclick="window.open(this.src)"><?php endif; ?>
                                <form method="POST" onsubmit="return confirm('<?= __('admin_payment_confirm_delete') ?>');"><input type="hidden" name="account_id" value="<?= $acc['id'] ?>"><button type="submit" name="delete_account" class="text-red-500 hover:text-red-700 p-1"><i class="fas fa-trash-alt"></i></button></form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop View: Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full leading-normal text-left">
                        <thead>
                            <tr class="bg-blue-50 text-blue-800 font-bold border-b-2 border-blue-200">
                                <th class="px-3 py-3 w-10 text-center"></th>
                                <th class="px-5 py-3 text-sm"><?= __('admin_payment_col_method') ?></th>
                                <th class="px-5 py-3 text-sm"><?= __('admin_payment_col_info') ?></th>
                                <th class="px-5 py-3 text-sm text-center"><?= __('admin_payment_col_qr') ?></th>
                                <th class="px-5 py-3 text-sm text-center"><?= __('admin_payment_col_status') ?></th>
                                <th class="px-5 py-3 text-sm text-center"><?= __('admin_payment_col_action') ?></th>
                            </tr>
                        </thead>
                        <tbody id="sortable-list">
                            <?php foreach($accounts as $acc): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition" data-id="<?= $acc['id'] ?>">
                                    <td class="px-3 py-3 text-center cursor-move text-gray-400 hover:text-gray-600"><i class="fas fa-grip-vertical"></i></td>
                                    <td class="px-5 py-3 font-bold text-gray-800">
                                        <div class="flex items-center gap-2">
                                            <?php if(!empty($acc['logo_url'])): ?><img src="<?= htmlspecialchars($acc['logo_url']) ?>" class="w-8 h-8 rounded-full object-cover border shadow-sm"><?php else: ?><div class="w-8 h-8 rounded-full bg-blue-100 text-blue-500 flex items-center justify-center shadow-sm"><i class="fas fa-university text-xs"></i></div><?php endif; ?>
                                            <?= htmlspecialchars($acc['payment_method']) ?>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3"><p class="font-bold text-blue-600 tracking-wider"><?= htmlspecialchars($acc['account_number']) ?></p><p class="text-xs text-gray-500"><?= htmlspecialchars($acc['account_name']) ?></p></td>
                                    <td class="px-5 py-3 text-center"><?php if(!empty($acc['qr_image_url'])): ?><img src="<?= htmlspecialchars($acc['qr_image_url']) ?>" class="w-10 h-10 object-cover rounded border mx-auto cursor-pointer" onclick="window.open(this.src)"><?php else: ?><span class="text-gray-400 text-xs">-</span><?php endif; ?></td>
                                    <td class="px-5 py-3 text-center"><form method="POST"><input type="hidden" name="account_id" value="<?= $acc['id'] ?>"><input type="hidden" name="current_status" value="<?= $acc['is_active'] ?>"><button type="submit" name="toggle_status" class="px-3 py-1 rounded-full text-xs font-bold transition <?= $acc['is_active'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-red-100 text-red-700 hover:bg-red-200' ?>"><?= $acc['is_active'] ? '<i class="fas fa-check-circle mr-1"></i> '.__('admin_payment_active') : '<i class="fas fa-times-circle mr-1"></i> '.__('admin_payment_inactive') ?></button></form></td>
                                    <td class="px-5 py-3 text-center"><form method="POST" onsubmit="return confirm('<?= __('admin_payment_confirm_delete') ?>');"><input type="hidden" name="account_id" value="<?= $acc['id'] ?>"><button type="submit" name="delete_account" class="text-red-500 hover:text-red-900 p-1"><i class="fas fa-trash-alt"></i></button></form></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="px-5 py-8 text-center text-gray-500 italic">
                    <i class="fas fa-wallet text-4xl text-gray-300 mb-3 block"></i>
                    <?= __('admin_payment_no_accounts') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SortableJS (Drag and Drop စနစ်အတွက်) -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('sortable-list');
            var el_mobile = document.getElementById('sortable-list-mobile');

            function saveOrder(listElement) {
                var order = [];
                listElement.querySelectorAll('[data-id]').forEach(function(row, index) {
                    order.push(row.getAttribute('data-id'));
                });
                
                var formData = new FormData();
                formData.append('action', 'update_order');
                formData.append('order', JSON.stringify(order));
                
                fetch('admin_payment_settings.php', { method: 'POST', body: formData })
                    .catch(err => console.error('Order update failed', err));
            }

            if (el) {
                Sortable.create(el, {
                    handle: '.cursor-move',
                    animation: 150,
                    ghostClass: 'bg-blue-50', // ဆွဲရွှေ့နေစဉ် ပြသမည့် နောက်ခံအရောင်
                    onEnd: () => saveOrder(el)
                });
            }
            if (el_mobile) {
                Sortable.create(el_mobile, { handle: '.cursor-move', animation: 150, ghostClass: 'bg-blue-50', onEnd: () => saveOrder(el_mobile) });
            }
        });
    </script>
</body>
</html>