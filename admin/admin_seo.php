<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';
require_once __DIR__ . '/../lang/language.php';

require_main_admin();

// CSRF Token
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = "";
$error_message = "";

// 1. Global SEO Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_global_seo'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        $error_message = __('csrf_error');
    } else {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        
        $keys = [
            'seo_title_mm' => trim($_POST['global_title_mm'] ?? ''), 
            'seo_title_en' => trim($_POST['global_title_en'] ?? ''), 
            'seo_description_mm' => trim($_POST['global_desc_mm'] ?? ''), 
            'seo_description_en' => trim($_POST['global_desc_en'] ?? ''), 
            'seo_keywords_mm' => trim($_POST['global_keys_mm'] ?? ''), 
            'seo_keywords_en' => trim($_POST['global_keys_en'] ?? ''), 
            'seo_image_url' => trim($_POST['global_image'] ?? '')
        ];
        
        foreach ($keys as $key => $val) {
            $stmt->bind_param("sss", $key, $val, $val);
            $stmt->execute();
        }
        $stmt->close();
        $success_message = __('global_seo_success');
    }
}

// 2. Page Specific SEO Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_page_seo'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        $error_message = __('csrf_error');
    } else {
        $page_name = trim($_POST['page_name'] ?? '');
        $p_title_mm = trim($_POST['page_title_mm'] ?? '');
        $p_title_en = trim($_POST['page_title_en'] ?? '');
        $p_desc_mm = trim($_POST['page_desc_mm'] ?? '');
        $p_desc_en = trim($_POST['page_desc_en'] ?? '');
        $p_keys_mm = trim($_POST['page_keys_mm'] ?? '');
        $p_keys_en = trim($_POST['page_keys_en'] ?? '');
        $p_image = trim($_POST['page_image'] ?? '');

        if(!empty($page_name)) {
            $stmt = $conn->prepare("INSERT INTO page_seo (page_name, seo_title_mm, seo_title_en, seo_description_mm, seo_description_en, seo_keywords_mm, seo_keywords_en, seo_image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE seo_title_mm=?, seo_title_en=?, seo_description_mm=?, seo_description_en=?, seo_keywords_mm=?, seo_keywords_en=?, seo_image_url=?");
            
            $stmt->bind_param("sssssssssssssss", 
                $page_name, 
                $p_title_mm, $p_title_en, $p_desc_mm, $p_desc_en, $p_keys_mm, $p_keys_en, $p_image,
                $p_title_mm, $p_title_en, $p_desc_mm, $p_desc_en, $p_keys_mm, $p_keys_en, $p_image
            );
            
            if ($stmt->execute()) {
                $success_message = sprintf(__('page_seo_success'), htmlspecialchars($page_name));
            } else {
                $error_message = "Error saving page SEO.";
            }
            $stmt->close();
        }
    }
}

// 3. Delete Page SEO
if (isset($_GET['delete_page'])) {
    $del_page = $_GET['delete_page'];
    $del_stmt = $conn->prepare("DELETE FROM page_seo WHERE page_name = ?");
    $del_stmt->bind_param("s", $del_page);
    $del_stmt->execute();
    $del_stmt->close();
    header("Location: admin_seo.php?msg=deleted");
    exit();
}
if(isset($_GET['msg']) && $_GET['msg'] == 'deleted') $success_message = __('page_seo_deleted');

// Global SEO data ဆွဲထုတ်ခြင်း
$setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'seo_%'");
$current_seo = [];
while ($row = $setting_stmt->fetch_assoc()) {
    $current_seo[$row['setting_key']] = $row['setting_value'];
}

// Page SEO data များ ဆွဲထုတ်ခြင်း
$page_seo_list = [];
$p_stmt = $conn->query("SELECT * FROM page_seo ORDER BY page_name ASC");
while ($p_row = $p_stmt->fetch_assoc()) {
    $page_seo_list[] = $p_row;
}
?>

<?php 
$page_title = __('admin_seo_page_title');
require_once '../includes/header.php'; 
require_once 'admin_header.php'; // Includes the dark mode logic and sidebar
?>

<body class="bg-gray-100 dark:bg-gray-900 min-h-screen pb-10 transition-colors duration-300">
    <div class="max-w-5xl mx-auto p-4 md:p-8">
        
        <div class="flex items-center mb-6">
            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 rounded-xl flex items-center justify-center text-xl mr-4 shadow-sm border border-blue-200 dark:border-blue-800">
                <i class="fas fa-language"></i>
            </div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white"><?= __('admin_seo_header') ?></h2>
        </div>
        
        <?php if($success_message): ?>
            <div class="bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-800 text-green-700 dark:text-green-400 p-4 rounded-xl mb-6 font-bold flex items-center shadow-sm animate__animated animate__fadeIn"><i class="fas fa-check-circle mr-2 text-xl"></i> <?= $success_message ?></div>
        <?php endif; ?>
        <?php if($error_message): ?>
            <div class="bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-800 text-red-700 dark:text-red-400 p-4 rounded-xl mb-6 font-bold flex items-center shadow-sm animate__animated animate__fadeIn"><i class="fas fa-exclamation-circle mr-2 text-xl"></i> <?= $error_message ?></div>
        <?php endif; ?>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 md:p-8 mb-8 border-t-4 border-blue-600 transition-colors duration-300">
            <h3 class="text-lg font-bold mb-5 border-b border-gray-100 dark:border-gray-700 pb-3 text-gray-700 dark:text-gray-200"><i class="fas fa-globe mr-2 text-blue-500"></i><?= __('global_seo_section') ?></h3>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 mb-6">
                    <div class="bg-blue-50/50 dark:bg-gray-700/50 p-5 rounded-xl border border-blue-100 dark:border-gray-600 shadow-inner">
                        <p class="font-bold text-blue-800 dark:text-blue-300 mb-4 flex items-center"><i class="fas fa-flag mr-2 text-blue-500"></i> Myanmar (မြန်မာ)</p>
                        <div class="mb-4">
                            <label class="block text-xs font-bold mb-1.5 text-gray-600 dark:text-gray-300"><?= __('website_title') ?></label>
                            <input type="text" name="global_title_mm" value="<?= htmlspecialchars($current_seo['seo_title_mm'] ?? '') ?>" class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-white transition-shadow shadow-sm" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-bold mb-1.5 text-gray-600 dark:text-gray-300"><?= __('keywords') ?></label>
                            <input type="text" name="global_keys_mm" value="<?= htmlspecialchars($current_seo['seo_keywords_mm'] ?? '') ?>" class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-white transition-shadow shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1.5 text-gray-600 dark:text-gray-300"><?= __('description') ?></label>
                            <textarea name="global_desc_mm" rows="3" class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-white transition-shadow shadow-sm"><?= htmlspecialchars($current_seo['seo_description_mm'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 p-5 rounded-xl border border-gray-200 dark:border-gray-600 shadow-inner">
                        <p class="font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center"><i class="fas fa-globe-americas mr-2 text-gray-500"></i> English</p>
                        <div class="mb-4">
                            <label class="block text-xs font-bold mb-1.5 text-gray-600 dark:text-gray-300"><?= __('website_title') ?></label>
                            <input type="text" name="global_title_en" value="<?= htmlspecialchars($current_seo['seo_title_en'] ?? '') ?>" class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 dark:text-white transition-shadow shadow-sm" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-bold mb-1.5 text-gray-600 dark:text-gray-300"><?= __('keywords') ?></label>
                            <input type="text" name="global_keys_en" value="<?= htmlspecialchars($current_seo['seo_keywords_en'] ?? '') ?>" class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 dark:text-white transition-shadow shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1.5 text-gray-600 dark:text-gray-300"><?= __('description') ?></label>
                            <textarea name="global_desc_en" rows="3" class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 dark:text-white transition-shadow shadow-sm"><?= htmlspecialchars($current_seo['seo_description_en'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="mb-6 bg-blue-50/30 dark:bg-gray-700/30 p-5 rounded-xl border border-blue-50 dark:border-gray-600">
                    <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300"><i class="fas fa-image mr-1 text-blue-500"></i> <?= __('default_image_url') ?></label>
                    <input type="text" name="global_image" value="<?= htmlspecialchars($current_seo['seo_image_url'] ?? '') ?>" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-white shadow-sm" placeholder="e.g. assets/images/banner.jpg">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Facebook, Viber တို့တွင် လင့်ခ်ရှယ်ပါက ပေါ်လာမည့် ပုံဖြစ်ပါသည်။</p>
                </div>

                <button type="submit" name="update_global_seo" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 flex items-center">
                    <i class="fas fa-save mr-2"></i> <?= __('save_global_seo') ?>
                </button>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-6 md:p-8 border-t-4 border-purple-600 transition-colors duration-300">
            <h3 class="text-lg font-bold mb-5 border-b border-gray-100 dark:border-gray-700 pb-3 text-gray-700 dark:text-gray-200"><i class="fas fa-file-alt mr-2 text-purple-500"></i><?= __('page_specific_seo_section') ?></h3>
            
            <form method="POST" action="" class="mb-10 bg-purple-50/50 dark:bg-gray-700/50 p-5 md:p-6 rounded-2xl border border-purple-100 dark:border-gray-600 shadow-inner">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>">
                <p class="text-base font-bold text-purple-700 dark:text-purple-400 mb-5 flex items-center"><i class="fas fa-plus-circle mr-2"></i> <?= __('add_edit_page_seo') ?></p>
                
                <datalist id="commonPages">
                    <option value="index.php">
                    <option value="2d_bet.php">
                    <option value="3d_bet.php">
                    <option value="deposit.php">
                    <option value="withdraw.php">
                    <option value="login.php">
                    <option value="register.php">
                </datalist>

                <div class="mb-6">
                    <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300"><?= __('page_name_label') ?></label>
                    <div class="relative w-full md:w-1/2">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-file-code text-gray-400"></i>
                        </div>
                        <input type="text" list="commonPages" name="page_name" placeholder="e.g. index.php" class="w-full pl-10 pr-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 dark:text-white shadow-sm" required autocomplete="off">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 mb-6">
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-600 shadow-sm">
                        <p class="font-bold text-purple-700 dark:text-purple-400 mb-4 border-b border-gray-100 dark:border-gray-700 pb-2 text-sm"><i class="fas fa-flag mr-1"></i> Myanmar (မြန်မာ)</p>
                        
                        <div class="mb-3">
                            <label class="block text-xs font-bold mb-1 text-gray-600 dark:text-gray-400">Title (MM)</label>
                            <input type="text" name="page_title_mm" placeholder="e.g. ပင်မ - Thai 2D3D" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 bg-transparent dark:text-white">
                        </div>
                        
                        <div class="mb-3">
                            <label class="block text-xs font-bold mb-1 text-gray-600 dark:text-gray-400">Keywords (MM)</label>
                            <input type="text" name="page_keys_mm" placeholder="e.g. 2d, 3d, myanmar 2d" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 bg-transparent dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold mb-1 text-gray-600 dark:text-gray-400">Description (MM)</label>
                            <textarea name="page_desc_mm" rows="2" placeholder="Description..." class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 bg-transparent dark:text-white"></textarea>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-600 shadow-sm">
                        <p class="font-bold text-gray-700 dark:text-gray-300 mb-4 border-b border-gray-100 dark:border-gray-700 pb-2 text-sm"><i class="fas fa-globe-americas mr-1"></i> English</p>
                        
                        <div class="mb-3">
                            <label class="block text-xs font-bold mb-1 text-gray-600 dark:text-gray-400">Title (EN)</label>
                            <input type="text" name="page_title_en" placeholder="e.g. Home - Thai 2D3D" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 bg-transparent dark:text-white">
                        </div>
                        
                        <div class="mb-3">
                            <label class="block text-xs font-bold mb-1 text-gray-600 dark:text-gray-400">Keywords (EN)</label>
                            <input type="text" name="page_keys_en" placeholder="e.g. 2d, 3d, thai 2d" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 bg-transparent dark:text-white">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold mb-1 text-gray-600 dark:text-gray-400">Description (EN)</label>
                            <textarea name="page_desc_en" rows="2" placeholder="Description..." class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 bg-transparent dark:text-white"></textarea>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300"><i class="fas fa-image mr-1 text-purple-500"></i> <?= __('share_image_url') ?></label>
                    <input type="text" name="page_image" placeholder="e.g. assets/images/home-banner.jpg" class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 dark:text-white shadow-sm">
                </div>

                <button type="submit" name="update_page_seo" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-8 rounded-xl shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 flex items-center">
                    <i class="fas fa-plus mr-2"></i> <?= __('save_page_seo') ?>
                </button>
            </form>

            <div class="mt-8">
                <h4 class="text-base font-bold text-gray-700 dark:text-gray-200 mb-4"><i class="fas fa-list-alt mr-2 text-gray-400"></i> သိမ်းဆည်းထားသော စာမျက်နှာများ</h4>
                <?php if(count($page_seo_list) > 0): ?>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm border-b border-gray-200 dark:border-gray-600">
                                    <th class="p-4 font-bold"><?= __('table_page_name') ?></th>
                                    <th class="p-4 font-bold"><?= __('table_title_mm') ?></th>
                                    <th class="p-4 font-bold hidden md:table-cell"><?= __('table_title_en') ?></th>
                                    <th class="p-4 font-bold text-center"><?= __('table_action') ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                <?php foreach($page_seo_list as $p): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors text-sm text-gray-600 dark:text-gray-300">
                                        <td class="p-4 font-bold text-blue-600 dark:text-blue-400">
                                            <i class="fas fa-link text-gray-400 mr-2 text-xs"></i><?= htmlspecialchars($p['page_name'] ?? '') ?>
                                        </td>
                                        <td class="p-4"><?= htmlspecialchars($p['seo_title_mm'] ?? '') ?></td>
                                        <td class="p-4 hidden md:table-cell"><?= htmlspecialchars($p['seo_title_en'] ?? '') ?></td>
                                        <td class="p-4 text-center">
                                            <a href="?delete_page=<?= urlencode($p['page_name'] ?? '') ?>" onclick="return confirm('<?= addslashes(__('confirm_delete_page_seo')) ?>');" class="inline-flex items-center justify-center w-8 h-8 text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:hover:bg-red-900/50 rounded-lg transition-colors" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-8 text-center border border-gray-200 dark:border-gray-700 border-dashed">
                        <i class="fas fa-folder-open text-4xl text-gray-300 dark:text-gray-500 mb-3"></i>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-medium"><?= __('no_page_seo_records') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
