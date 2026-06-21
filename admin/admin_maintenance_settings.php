<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_maintenance'])) {
    $new_maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
    $new_maintenance_message = trim($_POST['maintenance_message'] ?? '');

    $stmt_main_mode = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
    $stmt_main_mode->bind_param("s", $new_maintenance_mode);
    $stmt_main_mode->execute(); $stmt_main_mode->close();

    $stmt_main_msg = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_message'");
    $stmt_main_msg->bind_param("s", $new_maintenance_message);
    $stmt_main_msg->execute(); $stmt_main_msg->close();

    $success_message = __('admin_maintenance_success');
    log_activity($_SESSION['user_id'], 'UPDATE_MAINTENANCE_MODE', "Maintenance mode settings were updated.");
}

$setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('maintenance_mode', 'maintenance_message')");
$settings = [];
while ($row = $setting_stmt->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$current_maintenance_mode = $settings['maintenance_mode'] ?? '0';
$current_maintenance_message = $settings['maintenance_message'] ?? __('admin_maintenance_default_msg');
?>

<?php 
$page_title = __('admin_maintenance_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">
    <?php
    $header_title = __('admin_maintenance_header_title');
    $header_icon = "fas fa-tools text-red-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-6">
            <form method="POST" action="">
                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><?= __('admin_maintenance_section_title') ?></h2>
                <div class="mb-5 bg-red-50 p-4 rounded-lg border border-red-100">
                    <div class="mb-3 flex items-center">
                        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?= $current_maintenance_mode == '1' ? 'checked' : '' ?> class="w-5 h-5 text-red-600 border-gray-300 rounded focus:ring-red-500">
                        <label for="maintenance_mode" class="ml-2 block text-gray-700 text-sm font-bold"><?= __('admin_maintenance_enable') ?></label>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_maintenance_msg_label') ?></label>
                        <textarea name="maintenance_message" rows="2" placeholder="<?= __('admin_maintenance_default_msg') ?>" class="w-full py-2 px-3 border rounded focus:border-red-500 focus:outline-none"><?= htmlspecialchars($current_maintenance_message) ?></textarea>
                    </div>
                </div>

                <button type="submit" name="update_maintenance" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition mt-4 w-full">
                    <i class="fas fa-save mr-2"></i> <?= __('admin_maintenance_btn_save') ?>
                </button>
            </form>
        </div>
    </div>
</body>
</html>