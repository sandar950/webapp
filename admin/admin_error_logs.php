<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Main Admin သာ ကြည့်ရှုနိုင်မည်
require_main_admin();

$log_file = __DIR__ . '/errorlog.txt';
$log_contents = "";
$success_message = "";

// Clear Logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    if (file_exists($log_file)) {
        file_put_contents($log_file, ""); // Empty the file
        $success_message = __('admin_error_logs_clear_success');
        log_activity($_SESSION['user_id'], 'CLEAR_ERROR_LOGS', "Cleared system error logs.");
    }
}

if (file_exists($log_file)) {
    $log_contents = file_get_contents($log_file);
    if (empty(trim($log_contents))) {
        $log_contents = __('admin_error_logs_no_records');
    }
} else {
    $log_contents = __('admin_error_logs_no_file');
}

$page_title = __('admin_error_logs_page_title');
require_once __DIR__ . '/../includes/header.php';
?>

<body class="max-w-5xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_error_logs_header_title');
    $header_icon = "fas fa-bug text-red-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm font-bold"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
            <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                <h2 class="font-bold text-gray-800"><i class="fas fa-file-alt text-red-500 mr-2"></i> <?= __('admin_error_logs_file_title') ?></h2>
                <form method="POST" onsubmit="return confirm('<?= __('admin_error_logs_confirm_clear') ?>');">
                    <button type="submit" name="clear_logs" class="bg-red-100 text-red-700 hover:bg-red-200 px-3 py-1.5 rounded text-sm font-bold shadow-sm transition"><i class="fas fa-trash-alt mr-1"></i> <?= __('admin_error_logs_btn_clear') ?></button>
                </form>
            </div>
            <div class="p-4 bg-gray-900">
                <textarea readonly class="w-full h-[400px] md:h-[500px] bg-gray-900 text-green-400 font-mono text-sm p-2 focus:outline-none border-none resize-none leading-relaxed"><?= htmlspecialchars($log_contents) ?></textarea>
            </div>
        </div>
    </div>
</body>
</html>