<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_referral'])) {
    $new_mlm_level_1 = floatval($_POST['mlm_level_1_percent'] ?? 3);
    $new_mlm_level_2 = floatval($_POST['mlm_level_2_percent'] ?? 1.5);
    $new_mlm_level_3 = floatval($_POST['mlm_level_3_percent'] ?? 0.5);

    $stmt_mlm_1 = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'mlm_level_1_percent'");
    $stmt_mlm_1->bind_param("d", $new_mlm_level_1);
    $stmt_mlm_1->execute(); $stmt_mlm_1->close();

    $stmt_mlm_2 = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'mlm_level_2_percent'");
    $stmt_mlm_2->bind_param("d", $new_mlm_level_2);
    $stmt_mlm_2->execute(); $stmt_mlm_2->close();

    $stmt_mlm_3 = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'mlm_level_3_percent'");
    $stmt_mlm_3->bind_param("d", $new_mlm_level_3);
    $stmt_mlm_3->execute(); $stmt_mlm_3->close();
    
    // Compatibility အတွက် referral_commission_percent ကိုပါ level 1 နှုန်းဖြင့် အလိုအလျောက် Update လုပ်ပေးမည်
    $stmt_comm = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'referral_commission_percent'");
    $stmt_comm->bind_param("d", $new_mlm_level_1);
    $stmt_comm->execute(); $stmt_comm->close();

    $success_message = __('admin_referral_success');
    log_activity($_SESSION['user_id'], 'UPDATE_REFERRAL_SETTINGS', "Multi-level Referral settings were updated.");
}

$setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('mlm_level_1_percent', 'mlm_level_2_percent', 'mlm_level_3_percent')");
$settings = [];
while ($row = $setting_stmt->fetch_assoc()) {
    $settings[$row['setting_key']] = floatval($row['setting_value']);
}
$current_mlm_level_1 = $settings['mlm_level_1_percent'] ?? 3;
$current_mlm_level_2 = $settings['mlm_level_2_percent'] ?? 1.5;
$current_mlm_level_3 = $settings['mlm_level_3_percent'] ?? 0.5;
?>

<?php 
$page_title = __('admin_referral_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">
    <?php
    $header_title = __('admin_referral_header_title');
    $header_icon = "fas fa-share-alt text-indigo-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <form method="POST" action="">
                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><?= __('admin_referral_section_title') ?></h2>
                <div class="mb-5 bg-indigo-50 p-4 rounded-lg border border-indigo-100"><p class="text-xs text-indigo-800 mb-3 font-bold"><?= __('admin_referral_desc') ?></p><div class="grid grid-cols-1 md:grid-cols-3 gap-4"><div><label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_referral_level_1') ?></label><input type="number" name="mlm_level_1_percent" value="<?= htmlspecialchars($current_mlm_level_1) ?>" step="0.1" min="0" max="100" class="w-full py-2 px-3 border rounded-lg focus:border-indigo-500 focus:outline-none"></div><div><label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_referral_level_2') ?></label><input type="number" name="mlm_level_2_percent" value="<?= htmlspecialchars($current_mlm_level_2) ?>" step="0.1" min="0" max="100" class="w-full py-2 px-3 border rounded-lg focus:border-indigo-500 focus:outline-none"></div><div><label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_referral_level_3') ?></label><input type="number" name="mlm_level_3_percent" value="<?= htmlspecialchars($current_mlm_level_3) ?>" step="0.1" min="0" max="100" class="w-full py-2 px-3 border rounded-lg focus:border-indigo-500 focus:outline-none"></div></div></div>

                <button type="submit" name="update_referral" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition w-full"><i class="fas fa-save mr-2"></i> <?= __('admin_referral_btn_save') ?></button>
            </form>
        </div>
    </div>
</body>
</html>