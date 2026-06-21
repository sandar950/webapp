<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_finance_settings'])) {
    $new_min_dep = floatval($_POST['min_deposit'] ?? 1000);
    $new_max_dep = floatval($_POST['max_deposit'] ?? 1000000);
    $new_min_with = floatval($_POST['min_withdraw'] ?? 1000);
    $new_max_with = floatval($_POST['max_withdraw'] ?? 1000000);
    $new_with_fee = floatval($_POST['withdrawal_fee_percent'] ?? 0);

    if ($new_min_dep >= 0 && $new_max_dep >= 0 && $new_min_with >= 0 && $new_max_with >= 0 && $new_with_fee >= 0) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        
        $settings_to_update = [
            'min_deposit' => $new_min_dep,
            'max_deposit' => $new_max_dep,
            'min_withdraw' => $new_min_with,
            'max_withdraw' => $new_max_with,
            'withdrawal_fee_percent' => $new_with_fee
        ];

        foreach($settings_to_update as $key => $val) {
            $stmt->bind_param("ds", $val, $key);
            $stmt->execute();
        }
        $stmt->close();

        $success_message = __('admin_finance_success');
        log_activity($_SESSION['user_id'], 'UPDATE_FINANCE_SETTINGS', "Finance settings updated.");
    } else {
        $error_message = __('admin_finance_error_amount');
    }
}

$setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('min_deposit', 'max_deposit', 'min_withdraw', 'max_withdraw', 'withdrawal_fee_percent')");
$limits = [];
if ($setting_stmt) {
    while ($row = $setting_stmt->fetch_assoc()) {
        $limits[$row['setting_key']] = floatval($row['setting_value']);
    }
}

$current_min_dep = $limits['min_deposit'] ?? 1000;
$current_max_dep = $limits['max_deposit'] ?? 1000000;
$current_min_with = $limits['min_withdraw'] ?? 1000;
$current_max_with = $limits['max_withdraw'] ?? 1000000;
$current_with_fee = $limits['withdrawal_fee_percent'] ?? 0;
?>

<?php 
$page_title = __('admin_finance_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>
<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">
    <?php
    $header_title = __('admin_finance_header_title');
    $header_icon = "fas fa-money-bill-wave text-green-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <form method="POST" action="">
                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><?= __('admin_finance_section_title') ?></h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                    <div><label class="block text-gray-700 font-bold mb-2"><?= __('admin_finance_min_dep') ?></label><input type="number" name="min_deposit" value="<?= htmlspecialchars($current_min_dep) ?>" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none bg-gray-50" required></div>
                    <div><label class="block text-gray-700 font-bold mb-2"><?= __('admin_finance_max_dep') ?></label><input type="number" name="max_deposit" value="<?= htmlspecialchars($current_max_dep) ?>" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none bg-gray-50" required></div>
                    <div><label class="block text-gray-700 font-bold mb-2"><?= __('admin_finance_min_with') ?></label><input type="number" name="min_withdraw" value="<?= htmlspecialchars($current_min_with) ?>" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none bg-gray-50" required></div>
                    <div><label class="block text-gray-700 font-bold mb-2"><?= __('admin_finance_max_with') ?></label><input type="number" name="max_withdraw" value="<?= htmlspecialchars($current_max_with) ?>" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none bg-gray-50" required></div>
                    <div><label class="block text-gray-700 font-bold mb-2"><?= __('admin_finance_with_fee') ?></label><input type="number" name="withdrawal_fee_percent" value="<?= htmlspecialchars($current_with_fee) ?>" step="0.1" min="0" max="100" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none bg-gray-50" required></div>
                </div>
                <button type="submit" name="update_finance_settings" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition mt-4 w-full">
                    <i class="fas fa-save mr-2"></i> <?= __('admin_finance_btn_save') ?>
                </button>
            </form>
        </div>
    </div>
</body>
</html>