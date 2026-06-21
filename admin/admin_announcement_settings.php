<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_announcement'])) {
    $new_announcement_text = trim($_POST['announcement_text'] ?? '');
    $new_announcement_image_url = trim($_POST['announcement_image_url'] ?? '');
    $new_announcement_is_active = isset($_POST['announcement_is_active']) ? '1' : '0';

    if (isset($_FILES['announcement_image_file']) && $_FILES['announcement_image_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['announcement_image_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $new_filename = 'announce_' . time() . '.' . $ext;
            require_once __DIR__ . '/../core/image_helper.php';
            if (compressImage($_FILES['announcement_image_file']['tmp_name'], $upload_dir . $new_filename, 60)) {
                $new_announcement_image_url = $upload_dir . $new_filename;
            }
        }
    }

    $stmt_ann_txt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'announcement_text'");
    $stmt_ann_txt->bind_param("s", $new_announcement_text);
    $stmt_ann_txt->execute(); $stmt_ann_txt->close();
    
    $stmt_ann_img = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'announcement_image_url'");
    $stmt_ann_img->bind_param("s", $new_announcement_image_url);
    $stmt_ann_img->execute(); $stmt_ann_img->close();
    
    $stmt_ann_act = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'announcement_is_active'");
    $stmt_ann_act->bind_param("s", $new_announcement_is_active);
    $stmt_ann_act->execute(); $stmt_ann_act->close();

    $success_message = __('admin_announce_success');
    log_activity($_SESSION['user_id'], 'UPDATE_ANNOUNCEMENT', "Announcement settings were updated.");
}

$setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('announcement_text', 'announcement_image_url', 'announcement_is_active')");
$settings = [];
while ($row = $setting_stmt->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$current_announcement_text = $settings['announcement_text'] ?? '';
$current_announcement_image_url = $settings['announcement_image_url'] ?? '';
$current_announcement_is_active = $settings['announcement_is_active'] ?? '0';
?>

<?php 
$page_title = __('admin_announce_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">
    <?php
    $header_title = __('admin_announce_header_title');
    $header_icon = "fas fa-bullhorn text-purple-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <form method="POST" action="" enctype="multipart/form-data">
                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><?= __('admin_announce_section_title') ?></h2>
                <div class="mb-5 bg-purple-50 p-4 rounded-lg border border-purple-100">
                    <div class="mb-3 flex items-center"><input type="checkbox" id="announcement_is_active" name="announcement_is_active" value="1" <?= $current_announcement_is_active == '1' ? 'checked' : '' ?> class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500"><label for="announcement_is_active" class="ml-2 block text-gray-700 text-sm font-bold"><?= __('admin_announce_enable') ?></label></div>
                    <div class="mb-3"><label class="block text-gray-700 text-sm font-bold mb-1"><?= __('admin_announce_text') ?></label><textarea name="announcement_text" rows="3" placeholder="<?= __('admin_announce_text_ph') ?>" class="w-full py-2 px-3 border rounded focus:border-purple-500 focus:outline-none"><?= htmlspecialchars($current_announcement_text) ?></textarea></div>
                    <div><label class="block text-gray-700 text-sm font-bold mb-2"><?= __('admin_announce_image') ?></label><div class="flex flex-col gap-2"><input type="text" name="announcement_image_url" value="<?= htmlspecialchars($current_announcement_image_url) ?>" placeholder="<?= __('admin_banner_url_ph') ?>" class="w-full py-2 px-3 border rounded-lg focus:border-purple-500 focus:outline-none"><div class="flex items-center gap-2"><span class="text-xs text-gray-500 font-bold"><?= __('admin_banner_or') ?></span><input type="file" name="announcement_image_file" accept="image/png, image/jpeg, image/jpg, image/webp" class="text-sm text-gray-500 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-purple-100 file:text-purple-700 hover:file:bg-purple-200"></div></div></div>
                </div>

                <button type="submit" name="update_announcement" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition mt-4 w-full"><i class="fas fa-save mr-2"></i> <?= __('admin_announce_btn_save') ?></button>
            </form>
        </div>
    </div>
</body>
</html>