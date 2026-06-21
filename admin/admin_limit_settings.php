<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_limit'])) {
    $new_limit = floatval($_POST['max_limit'] ?? 0);
    $new_limit_3d = floatval($_POST['max_limit_3d'] ?? 0);
    $new_cancel_limit = intval($_POST['bet_cancel_time_limit'] ?? 10);

    if ($new_limit > 0 && $new_limit_3d > 0) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'max_limit_per_number'");
        $stmt->bind_param("d", $new_limit);
        $stmt->execute();
        $stmt->close();
        
        $stmt_3d = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'max_limit_per_3d_number'");
        $stmt_3d->bind_param("d", $new_limit_3d);
        $stmt_3d->execute();
        $stmt_3d->close();

        $stmt_cancel = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'bet_cancel_time_limit'");
        $stmt_cancel->bind_param("i", $new_cancel_limit);
        $stmt_cancel->execute();
        $stmt_cancel->close();

        $success_message = __('admin_limit_success');
        log_activity($_SESSION['user_id'], 'UPDATE_LIMITS', "Bet limits were updated.");
    } else {
        $error_message = __('admin_limit_error_amount');
    }
}

$limits = [
    'max_limit_per_number' => 20000,
    'max_limit_per_3d_number' => 10000,
    'bet_cancel_time_limit' => 10
];

$setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('max_limit_per_number', 'max_limit_per_3d_number', 'bet_cancel_time_limit')");
if ($setting_stmt) {
    while ($row = $setting_stmt->fetch_assoc()) {
        $limits[$row['setting_key']] = floatval($row['setting_value']);
    }
}

$current_limit = $limits['max_limit_per_number'];
$current_limit_3d = $limits['max_limit_per_3d_number'];
$current_cancel_limit = $limits['bet_cancel_time_limit'];
?>

<?php 
$page_title = __('admin_limit_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_limit_header_title');
    $header_icon = "fas fa-sliders-h text-blue-500";
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
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><?= __('admin_limit_section_title') ?></h2>
            
            <form method="POST" action="">
                <div class="mb-5">
                    <label class="block text-gray-700 font-bold mb-2"><?= __('admin_limit_2d_max') ?></label>
                    <input type="number" name="max_limit" value="<?= htmlspecialchars($current_limit) ?>" min="1000" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none bg-gray-50" required>
                </div>
                
                <div class="mb-5">
                    <label class="block text-gray-700 font-bold mb-2"><?= __('admin_limit_3d_max') ?></label>
                    <input type="number" name="max_limit_3d" value="<?= htmlspecialchars($current_limit_3d) ?>" min="1000" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none bg-gray-50" required>
                    <p class="text-xs text-gray-500 mt-2"><?= __('admin_limit_note_full') ?></p>
                </div>

                <div class="mb-5 border-t pt-4">
                    <label class="block text-gray-700 font-bold mb-2"><?= __('admin_limit_cancel_time') ?></label>
                    <input type="number" name="bet_cancel_time_limit" value="<?= htmlspecialchars($current_cancel_limit) ?>" min="0" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none bg-gray-50" required>
                    <p class="text-xs text-gray-500 mt-2"><?= __('admin_limit_cancel_time_note') ?></p>
                </div>

                <button type="submit" name="update_limit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition mt-4 w-full">
                    <i class="fas fa-save mr-2"></i> <?= __('admin_limit_btn_save') ?>
                </button>
            </form>
        </div>
    </div>
</body>
</html>