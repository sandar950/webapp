<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_banners'])) {
    $new_banner_url = trim($_POST['home_banner_url'] ?? '');
    $new_banner_url_2 = trim($_POST['home_banner_url_2'] ?? '');
    $new_banner_url_3 = trim($_POST['home_banner_url_3'] ?? '');

    // Handle file uploads
    $upload_dir = '../uploads/banners/';
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

    for ($i = 1; $i <= 3; $i++) {
        $file_key = $i == 1 ? 'home_banner_file' : "home_banner_file_{$i}";
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $new_filename = 'banner_' . $i . '_' . time() . '.' . $ext;
                require_once __DIR__ . '/../core/image_helper.php';
                if (compressImage($_FILES[$file_key]['tmp_name'], $upload_dir . $new_filename, 60)) {
                    if ($i == 1) $new_banner_url = $upload_dir . $new_filename;
                    if ($i == 2) $new_banner_url_2 = $upload_dir . $new_filename;
                    if ($i == 3) $new_banner_url_3 = $upload_dir . $new_filename;
                }
            }
        }
    }

    $stmt_banner = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'home_banner_url'");
    $stmt_banner->bind_param("s", $new_banner_url);
    $stmt_banner->execute(); $stmt_banner->close();
    
    $stmt_banner_2 = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'home_banner_url_2'");
    $stmt_banner_2->bind_param("s", $new_banner_url_2);
    $stmt_banner_2->execute(); $stmt_banner_2->close();

    $stmt_banner_3 = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'home_banner_url_3'");
    $stmt_banner_3->bind_param("s", $new_banner_url_3);
    $stmt_banner_3->execute(); $stmt_banner_3->close();

    $success_message = __('admin_banner_success');
    log_activity($_SESSION['user_id'], 'UPDATE_BANNERS', "Home banners were updated.");
}

$setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('home_banner_url', 'home_banner_url_2', 'home_banner_url_3')");
$banners = [];
while ($row = $setting_stmt->fetch_assoc()) {
    $banners[$row['setting_key']] = $row['setting_value'];
}
$current_banner_url = $banners['home_banner_url'] ?? '';
$current_banner_url_2 = $banners['home_banner_url_2'] ?? '';
$current_banner_url_3 = $banners['home_banner_url_3'] ?? '';
?>

<?php 
$page_title = __('admin_banner_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">
    <?php
    $header_title = __('admin_banner_header_title');
    $header_icon = "fas fa-images text-pink-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <form method="POST" action="" enctype="multipart/form-data">
                <label class="block text-gray-700 font-bold mb-2"><?= __('admin_banner_main_title') ?></label>
                <div class="space-y-4">
                    <?php for($i=1; $i<=3; $i++): 
                        $current_val = $i == 1 ? $current_banner_url : ($i == 2 ? $current_banner_url_2 : $current_banner_url_3);
                        $input_name = $i == 1 ? 'home_banner_url' : "home_banner_url_{$i}";
                        $file_name = $i == 1 ? 'home_banner_file' : "home_banner_file_{$i}";
                    ?>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <label class="block text-sm font-bold text-gray-700 mb-2"><?= __('admin_banner_label') ?> <?= $i ?></label>
                        <div class="flex flex-col gap-2">
                            <input type="text" name="<?= $input_name ?>" value="<?= htmlspecialchars($current_val) ?>" placeholder="<?= __('admin_banner_url_ph') ?>" class="w-full py-2 px-3 border rounded-lg focus:border-blue-500 focus:outline-none">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-500 font-bold"><?= __('admin_banner_or') ?></span>
                                <input type="file" name="<?= $file_name ?>" accept="image/png, image/jpeg, image/jpg, image/webp" class="text-sm text-gray-500 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                            </div>
                        </div>
                        <?php if(!empty($current_val)): ?>
                            <img src="<?= htmlspecialchars($current_val) ?>" class="mt-2 h-16 w-auto rounded border border-gray-300 object-cover">
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
                <p class="text-[10px] text-gray-500 mt-2"><?= __('admin_banner_help_text') ?></p>

                <button type="submit" name="update_banners" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition mt-4 w-full">
                    <i class="fas fa-save mr-2"></i> <?= __('admin_banner_btn_save') ?>
                </button>
            </form>
        </div>
    </div>

</body>
</html>