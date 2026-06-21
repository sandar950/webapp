<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_api'])) {
    $new_live_2d_api_url = trim($_POST['live_2d_api_url'] ?? '');
    $new_live_3d_api_url = trim($_POST['live_3d_api_url'] ?? '');
    $new_enable_dynamic_odds = isset($_POST['enable_dynamic_odds']) ? '1' : '0';
    $new_dynamic_odds_threshold = floatval($_POST['dynamic_odds_threshold'] ?? 80);

    $stmt_2d_api = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'live_2d_api_url'");
    $stmt_2d_api->bind_param("s", $new_live_2d_api_url);
    $stmt_2d_api->execute(); $stmt_2d_api->close();

    $stmt_3d_api = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'live_3d_api_url'");
    $stmt_3d_api->bind_param("s", $new_live_3d_api_url);
    $stmt_3d_api->execute(); $stmt_3d_api->close();

    $stmt_dyn_odds = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'enable_dynamic_odds'");
    $stmt_dyn_odds->bind_param("s", $new_enable_dynamic_odds);
    $stmt_dyn_odds->execute(); $stmt_dyn_odds->close();

    $stmt_dyn_thr = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'dynamic_odds_threshold'");
    $stmt_dyn_thr->bind_param("d", $new_dynamic_odds_threshold);
    $stmt_dyn_thr->execute(); $stmt_dyn_thr->close();

    $success_message = "API နှင့် အဆလျှော့စနစ် ဆက်တင်များကို အောင်မြင်စွာ ပြင်ဆင်ပြီးပါပြီ။";
    log_activity($_SESSION['user_id'], 'UPDATE_API_SETTINGS', "Live API and Dynamic Odds settings were updated.");
}

$setting_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('live_2d_api_url', 'live_3d_api_url', 'enable_dynamic_odds', 'dynamic_odds_threshold')");
$settings = [];
while ($row = $setting_stmt->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$current_live_2d_api_url = $settings['live_2d_api_url'] ?? '';
$current_live_3d_api_url = $settings['live_3d_api_url'] ?? '';
$current_enable_dynamic_odds = $settings['enable_dynamic_odds'] ?? '1';
$current_dynamic_odds_threshold = floatval($settings['dynamic_odds_threshold'] ?? 80);
?>

<?php 
$page_title = "Admin - API Settings";
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">
    <?php
    $header_title = "API & Auto Result";
    $header_icon = "fas fa-plug text-green-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
            <form method="POST" action="">
                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Live API URLs (Auto Result)</h2>
                <div class="mb-5 bg-green-50 p-4 rounded-lg border border-green-100"><div class="mb-3"><label class="block text-gray-700 text-sm font-bold mb-1">2D API URL</label><input type="text" name="live_2d_api_url" value="<?= htmlspecialchars($current_live_2d_api_url) ?>" placeholder="https://api.example.com/2d/latest" class="w-full py-2 px-3 border rounded focus:border-blue-500 focus:outline-none"><p class="text-[10px] text-gray-500 mt-1">ဥပမာ: https://api.thaistock2d.com/live</p></div><div><label class="block text-gray-700 text-sm font-bold mb-1">3D API URL</label><input type="text" name="live_3d_api_url" value="<?= htmlspecialchars($current_live_3d_api_url) ?>" placeholder="https://api.example.com/3d/latest" class="w-full py-2 px-3 border rounded focus:border-blue-500 focus:outline-none"></div></div>

                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 mt-8">Risk Management (အလိုအလျောက် အဆလျှော့စနစ်)</h2>
                <div class="mb-5 bg-red-50 p-4 rounded-lg border border-red-100"><div class="mb-3 flex items-center"><input type="checkbox" id="enable_dynamic_odds" name="enable_dynamic_odds" value="1" <?= $current_enable_dynamic_odds == '1' ? 'checked' : '' ?> class="w-5 h-5 text-red-600 border-gray-300 rounded focus:ring-red-500"><label for="enable_dynamic_odds" class="ml-2 block text-gray-700 text-sm font-bold">Dynamic Odds စနစ်ဖွင့်မည်</label></div><div><label class="block text-gray-700 text-sm font-bold mb-1">အဆလျှော့မည့် ရာခိုင်နှုန်း (Threshold %)</label><input type="number" name="dynamic_odds_threshold" value="<?= htmlspecialchars($current_dynamic_odds_threshold) ?>" min="1" max="100" class="w-full py-2 px-3 border rounded focus:border-blue-500 focus:outline-none"><p class="text-[10px] text-gray-500 mt-1">Limit ၏ ဘယ်နှစ်ရာခိုင်နှုန်းကျော်သွားပါက ဆုကြေး (Odds) ကို အလိုအလျောက် လျှော့ချပေးမလဲ သတ်မှတ်ပါ။ (ဥပမာ: 80% ကျော်လျှင် ဆုကြေး တစ်ဝက်သာ ရမည်)</p></div></div>

                <button type="submit" name="update_api" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition mt-4 w-full"><i class="fas fa-save mr-2"></i> သိမ်းဆည်းမည်</button>
            </form>
        </div>
    </div>
</body>
</html>