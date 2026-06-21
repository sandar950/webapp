<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_vip_settings'])) {
    $updates = [
        'vip_bronze_threshold' => floatval($_POST['vip_bronze_threshold'] ?? 100000),
        'vip_silver_threshold' => floatval($_POST['vip_silver_threshold'] ?? 500000),
        'vip_gold_threshold' => floatval($_POST['vip_gold_threshold'] ?? 2000000),
        'vip_diamond_threshold' => floatval($_POST['vip_diamond_threshold'] ?? 5000000),
        'cashback_bronze_percent' => floatval($_POST['cashback_bronze_percent'] ?? 3),
        'cashback_silver_percent' => floatval($_POST['cashback_silver_percent'] ?? 5),
        'cashback_gold_percent' => floatval($_POST['cashback_gold_percent'] ?? 8),
        'cashback_diamond_percent' => floatval($_POST['cashback_diamond_percent'] ?? 10)
    ];

    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    foreach($updates as $key => $val) {
        $stmt->bind_param("ds", $val, $key);
        $stmt->execute();
    }
    $stmt->close();

    $success_message = __('admin_vip_success');
    log_activity($_SESSION['user_id'], 'UPDATE_VIP_SETTINGS', "VIP & Cashback settings were updated.");
}

$setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'vip_%' OR setting_key LIKE 'cashback_%'");
$limits = [];
while ($row = $setting_stmt->fetch_assoc()) {
    $limits[$row['setting_key']] = floatval($row['setting_value']);
}
?>

<?php 
$page_title = __('admin_vip_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">
    <?php
    $header_title = __('admin_vip_header_title');
    $header_icon = "fas fa-crown text-yellow-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <form method="POST" action="">
                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><?= __('admin_vip_section_title') ?></h2>
                <div class="mb-5 bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                    <p class="text-xs text-yellow-800 mb-4 font-bold"><?= __('admin_vip_desc') ?></p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 border-b border-yellow-200 pb-4">
                        <div><label class="block text-gray-700 text-sm font-bold mb-1 text-orange-600"><?= sprintf(__('admin_vip_req_bet'), 'Bronze') ?></label><input type="number" name="vip_bronze_threshold" value="<?= htmlspecialchars($limits['vip_bronze_threshold'] ?? 100000) ?>" min="0" class="w-full py-2 px-3 border rounded focus:border-yellow-500 focus:outline-none"></div>
                        <div><label class="block text-gray-700 text-sm font-bold mb-1 text-orange-600"><?= sprintf(__('admin_vip_cashback_pct'), 'Bronze') ?></label><input type="number" name="cashback_bronze_percent" value="<?= htmlspecialchars($limits['cashback_bronze_percent'] ?? 3) ?>" step="0.1" min="0" max="100" class="w-full py-2 px-3 border rounded focus:border-yellow-500 focus:outline-none"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 border-b border-yellow-200 pb-4">
                        <div><label class="block text-gray-700 text-sm font-bold mb-1 text-gray-500"><?= sprintf(__('admin_vip_req_bet'), 'Silver') ?></label><input type="number" name="vip_silver_threshold" value="<?= htmlspecialchars($limits['vip_silver_threshold'] ?? 500000) ?>" min="0" class="w-full py-2 px-3 border rounded focus:border-yellow-500 focus:outline-none"></div>
                        <div><label class="block text-gray-700 text-sm font-bold mb-1 text-gray-500"><?= sprintf(__('admin_vip_cashback_pct'), 'Silver') ?></label><input type="number" name="cashback_silver_percent" value="<?= htmlspecialchars($limits['cashback_silver_percent'] ?? 5) ?>" step="0.1" min="0" max="100" class="w-full py-2 px-3 border rounded focus:border-yellow-500 focus:outline-none"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 border-b border-yellow-200 pb-4">
                        <div><label class="block text-gray-700 text-sm font-bold mb-1 text-yellow-600"><?= sprintf(__('admin_vip_req_bet'), 'Gold') ?></label><input type="number" name="vip_gold_threshold" value="<?= htmlspecialchars($limits['vip_gold_threshold'] ?? 2000000) ?>" min="0" class="w-full py-2 px-3 border rounded focus:border-yellow-500 focus:outline-none"></div>
                        <div><label class="block text-gray-700 text-sm font-bold mb-1 text-yellow-600"><?= sprintf(__('admin_vip_cashback_pct'), 'Gold') ?></label><input type="number" name="cashback_gold_percent" value="<?= htmlspecialchars($limits['cashback_gold_percent'] ?? 8) ?>" step="0.1" min="0" max="100" class="w-full py-2 px-3 border rounded focus:border-yellow-500 focus:outline-none"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block text-gray-700 text-sm font-bold mb-1 text-blue-600"><?= sprintf(__('admin_vip_req_bet'), 'Diamond') ?></label><input type="number" name="vip_diamond_threshold" value="<?= htmlspecialchars($limits['vip_diamond_threshold'] ?? 5000000) ?>" min="0" class="w-full py-2 px-3 border rounded focus:border-yellow-500 focus:outline-none"></div>
                        <div><label class="block text-gray-700 text-sm font-bold mb-1 text-blue-600"><?= sprintf(__('admin_vip_cashback_pct'), 'Diamond') ?></label><input type="number" name="cashback_diamond_percent" value="<?= htmlspecialchars($limits['cashback_diamond_percent'] ?? 10) ?>" step="0.1" min="0" max="100" class="w-full py-2 px-3 border rounded focus:border-yellow-500 focus:outline-none"></div>
                    </div>
                </div>

                <button type="submit" name="update_vip_settings" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition w-full"><i class="fas fa-save mr-2"></i> <?= __('admin_vip_btn_save') ?></button>
            </form>
        </div>
    </div>
</body>
</html>