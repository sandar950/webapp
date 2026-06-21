<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_telegram'])) {
    $new_telegram_bot = trim($_POST['telegram_bot_token'] ?? '');
    $new_telegram_channel = trim($_POST['telegram_channel_id'] ?? '');

    $stmt_tg_bot = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'telegram_bot_token'");
    $stmt_tg_bot->bind_param("s", $new_telegram_bot);
    $stmt_tg_bot->execute(); $stmt_tg_bot->close();

    $stmt_tg_ch = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'telegram_channel_id'");
    $stmt_tg_ch->bind_param("s", $new_telegram_channel);
    $stmt_tg_ch->execute(); $stmt_tg_ch->close();

    $success_message = __('admin_telegram_success');
    log_activity($_SESSION['user_id'], 'UPDATE_TELEGRAM_SETTINGS', "Telegram settings were updated.");
}

$setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('telegram_bot_token', 'telegram_channel_id')");
$settings = [];
while ($row = $setting_stmt->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$current_telegram_bot = $settings['telegram_bot_token'] ?? '';
$current_telegram_channel = $settings['telegram_channel_id'] ?? '';
?>

<?php 
$page_title = __('admin_telegram_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">
    <?php
    $header_title = __('admin_telegram_header_title');
    $header_icon = "fab fa-telegram text-blue-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <form method="POST" action="">
                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><?= __('admin_telegram_channel_title') ?></h2>
                <div class="mb-5 bg-purple-50 p-4 rounded-lg border border-purple-100">
                    <div class="mb-3"><label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_telegram_bot_token') ?></label><input type="text" name="telegram_bot_token" value="<?= htmlspecialchars($current_telegram_bot) ?>" placeholder="<?= __('admin_telegram_bot_token_ph') ?>" class="w-full py-2 px-3 border rounded focus:border-blue-500 focus:outline-none"></div>
                    <div><label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_telegram_channel_id') ?></label><input type="text" name="telegram_channel_id" value="<?= htmlspecialchars($current_telegram_channel) ?>" placeholder="<?= __('admin_telegram_channel_id_ph') ?>" class="w-full py-2 px-3 border rounded focus:border-blue-500 focus:outline-none"><p class="text-[10px] text-gray-500 mt-1"><?= __('admin_telegram_help_text') ?></p></div>
                </div>
                <button type="submit" name="update_telegram" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition mt-4 w-full"><i class="fas fa-save mr-2"></i> <?= __('admin_telegram_btn_save') ?></button>
            </form>
        </div>
    </div>
</body>
</html>