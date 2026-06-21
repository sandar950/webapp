<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_permission('can_send_notifications');

$success_message = "";
$error_message = "";

$target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$target_user_name = "";
$target_user_telegram = "";

if ($target_user_id > 0) {
    $stmt = $conn->prepare("SELECT username, telegram_chat_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $u = $res->fetch_assoc();
        $target_user_name = $u['username'];
        $target_user_telegram = $u['telegram_chat_id'];
    } else {
        $target_user_id = 0; // Invalid user
    }
    $stmt->close();
}

// Date Filter
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clause = "";
$params = [];
$types = "";

if (!empty($start_date)) { $where_clause .= " AND DATE(created_at) >= ? "; $params[] = $start_date; $types .= "s"; }
if (!empty($end_date)) { $where_clause .= " AND DATE(created_at) <= ? "; $params[] = $end_date; $types .= "s"; }

// Form Submit လုပ်၍ Noti ပို့သောအခါ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_noti'])) {
    $message_text = trim($_POST['message'] ?? '');
    $post_target_user = intval($_POST['target_user_id'] ?? 0);
    $is_important = isset($_POST['is_important']) && $_POST['is_important'] == '1';
    $image_url = null;

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/notifications/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_info = pathinfo($_FILES['image']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($ext, $allowed_exts)) {
            $new_filename = 'noti_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            require_once __DIR__ . '/../core/image_helper.php';
        if (compressImage($_FILES['image']['tmp_name'], $upload_path, 60)) {
                $image_url = $upload_path;
            } else {
                $error_message = "ပုံတင်ရာတွင် အမှားအယွင်းဖြစ်ပေါ်ခဲ့ပါသည်။";
            }
        } else {
            $error_message = "JPG, JPEG, PNG, GIF, WEBP ပုံများကိုသာ လက်ခံပါသည်။";
        }
    }
    
    if (!empty($message_text) && empty($error_message)) {
        $conn->begin_transaction();
        try {
            if ($post_target_user > 0) {
                // ၁။ သီးသန့် User အတွက် အသိပေးစာကို သိမ်းမည်
                $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message, image_url, is_important) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $post_target_user, $message_text, $image_url, $is_important);
                $stmt->execute();
                $stmt->close();
                
                // ၂။ သက်ဆိုင်ရာ User ၏ noti count ကို 1 တိုးပေးမည်
                $stmt = $conn->prepare("UPDATE users SET notifications = notifications + 1 WHERE id = ?");
                $stmt->bind_param("i", $post_target_user);
                $stmt->execute();
                $stmt->close();
                log_activity($_SESSION['user_id'], 'SEND_NOTIFICATION_SINGLE', "Sent a notification to User ID: {$post_target_user}");
            
                // --- Telegram သို့ တိုက်ရိုက် (DM) ပို့မည် ---
                if (!empty($target_user_telegram)) {
                    $tg_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_bot_token'");
                    $bot_token = $tg_stmt->fetch_assoc()['setting_value'] ?? '';

                    if (!empty($bot_token)) {
                        $emoji = $is_important ? "🚨 *အရေးကြီးအသိပေးချက်*" : "🔔 *အသိပေးချက်*";
                        
                        // User အမည်ဖြင့် Message ကို Personalize လုပ်မည်
                        $personalized_telegram_msg = str_replace('{username}', $target_user_name, $message_text);
                        $telegram_msg = $emoji . "\n\n" . $personalized_telegram_msg;

                        $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
                        $telegram_data = [
                            'chat_id' => $target_user_telegram,
                            'text' => $telegram_msg,
                            'parse_mode' => 'Markdown'
                        ];

                        $ch = curl_init($telegram_url);
                        curl_setopt_array($ch, [CURLOPT_URL => $telegram_url, CURLOPT_POST => TRUE, CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 3, CURLOPT_POSTFIELDS => http_build_query($telegram_data)]);
                        curl_exec($ch);
                        curl_close($ch);
                    }
                }
                
                $success_message = "အသိပေးချက် (Notification) ကို သက်ဆိုင်ရာ User ထံသို့ အောင်မြင်စွာ ပို့ဆောင်ပြီးပါပြီ။";
            } else {
                // ၁။ အားလုံးအတွက် အသိပေးစာကို သိမ်းမည် (user_id = NULL)
                $stmt = $conn->prepare("INSERT INTO system_notifications (message, image_url, is_important) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $message_text, $image_url, $is_important);
                $stmt->execute();
                $stmt->close();
                
                // ၂။ User အားလုံး၏ noti count ကို 1 တိုးပေးမည်
                $conn->query("UPDATE users SET notifications = notifications + 1");
                log_activity($_SESSION['user_id'], 'SEND_NOTIFICATION_ALL', "Sent a notification to all users.");
            
                // --- Telegram Channel သို့ ပို့မည် ---
                $tg_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('telegram_bot_token', 'telegram_channel_id')");
                $tg_settings = [];
                while ($tg_row = $tg_stmt->fetch_assoc()) {
                    $tg_settings[$tg_row['setting_key']] = $tg_row['setting_value'];
                }
                $bot_token = $tg_settings['telegram_bot_token'] ?? '';
                $channel_id = $tg_settings['telegram_channel_id'] ?? '';

                if (!empty($bot_token) && !empty($channel_id)) {
                    $emoji = $is_important ? "🚨 *အရေးကြီးအသိပေးချက်*" : "🔔 *အသိပေးချက်*";
                    $telegram_msg = $emoji . "\n\n" . $message_text;
                    $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
                    $telegram_data = ['chat_id' => $channel_id, 'text' => $telegram_msg, 'parse_mode' => 'Markdown'];
                    
                    $ch = curl_init($telegram_url);
                    curl_setopt_array($ch, [CURLOPT_URL => $telegram_url, CURLOPT_POST => TRUE, CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 3, CURLOPT_POSTFIELDS => http_build_query($telegram_data)]);
                    curl_exec($ch);
                    curl_close($ch);
                }
                
                $success_message = "အသိပေးချက် (Notification) ကို User အားလုံးထံသို့ အောင်မြင်စွာ ပို့ဆောင်ပြီးပါပြီ။";
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "အမှားအယွင်းဖြစ်ပေါ်နေပါသည်။ " . $e->getMessage();
        }
    } else {
        $error_message = "ကျေးဇူးပြု၍ အသိပေးစာ ရေးသားပါ။";
    }
}

// ယခင်က ပို့ထားသော အသိပေးစာများကို ဆွဲထုတ်ခြင်း (အားလုံး သို့မဟုတ် သီးသန့်)
$noti_query_base = "SELECT * FROM system_notifications ";
if ($target_user_id > 0) {
    $noti_query = $noti_query_base . " WHERE user_id = ? " . $where_clause . " ORDER BY created_at DESC LIMIT 20";
    $stmt = $conn->prepare($noti_query);
    if (!empty($params)) {
        $stmt->bind_param("i" . $types, $target_user_id, ...$params);
    } else {
        $stmt->bind_param("i", $target_user_id);
    }
    $stmt->execute();
    $recent_notis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $noti_query = $noti_query_base . " WHERE user_id IS NULL " . $where_clause . " ORDER BY created_at DESC LIMIT 20";
    $stmt = $conn->prepare($noti_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $recent_notis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<?php 
$page_title = "Admin - Send Notifications";
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = "အသိပေးစာ ပို့မည်";
    $header_icon = "fas fa-bell";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div id="filterSection" class="<?= (!empty($start_date) || !empty($end_date) || $unread_only || $important_only || $with_images) ? '' : 'hidden' ?> bg-white p-4 rounded-xl shadow-md mb-6 border-t-4 border-gray-300">
            <form method="GET" action="" class="space-y-3">
                <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">မှ (Start Date)</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-primary">
                    </div>
                    <div class="flex-1">
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">ထိ (End Date)</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-primary">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-2 mb-3">
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center cursor-pointer"><input type="checkbox" name="unread_only" value="1" <?= $unread_only ? 'checked' : '' ?> class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"><span class="ml-2 text-[11px] font-bold text-gray-600">မဖတ်ရသေးသည်များ</span></label>
                        <label class="flex items-center cursor-pointer"><input type="checkbox" name="important_only" value="1" <?= $important_only ? 'checked' : '' ?> class="w-4 h-4 text-red-500 border-gray-300 rounded focus:ring-red-500"><span class="ml-2 text-[11px] font-bold text-red-600">အရေးကြီးသီးသန့်</span></label>
                        <label class="flex items-center cursor-pointer"><input type="checkbox" name="with_images" value="1" <?= $with_images ? 'checked' : '' ?> class="w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-500"><span class="ml-2 text-[11px] font-bold text-blue-600">ပုံပါသည်များသာ</span></label>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t pt-3">
                    <div class="flex gap-2">
                        <button type="button" onclick="setQuickDate('<?= date('Y-m-d') ?>')" class="text-[10px] bg-gray-100 px-2 py-1 rounded border hover:bg-gray-200 transition">ယနေ့</button>
                        <button type="button" onclick="setQuickDate('<?= date('Y-m-d', strtotime('-1 day')) ?>')" class="text-[10px] bg-gray-100 px-2 py-1 rounded border hover:bg-gray-200 transition">မနေ့က</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="admin_notifications.php<?= $target_user_id > 0 ? '?user_id=' . $target_user_id : '' ?>" class="text-xs text-gray-500 hover:text-red-500 py-2 px-3 font-bold">ရှင်းမည်</a>
                        <button type="submit" class="bg-primary text-white py-1.5 px-6 rounded-lg text-xs font-bold shadow-sm">ရှာမည်</button>
                    </div>
                </div>
            </form>
        </div>

        <form method="POST" action="" class="bg-white p-4 sm:p-6 rounded-xl shadow-md mb-8" enctype="multipart/form-data">
            <input type="hidden" name="target_user_id" value="<?= $target_user_id ?>">
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">
                    <?= $target_user_id > 0 ? htmlspecialchars($target_user_name) . " ထံသို့ ပို့မည့် အသိပေးစာ" : "User များအားလုံးထံ ပို့မည့် အသိပေးစာ" ?>
                </label>
                <textarea name="message" rows="4" class="w-full p-3 border rounded-lg focus:border-blue-500 focus:outline-none" placeholder="အသိပေးချက် ရေးသားရန်..." required></textarea>
                <p class="text-xs text-gray-500 mt-1">User ၏အမည်ကို ထည့်သွင်းလိုပါက <code class="bg-gray-200 text-red-500 px-1 rounded">{username}</code> ဟု အသုံးပြုပါ။</p>
            </div>
            <div class="mb-4 flex items-center">
                <input type="checkbox" id="is_important" name="is_important" value="1" class="w-5 h-5 text-red-600 border-gray-300 rounded focus:ring-red-500">
                <label for="is_important" class="ml-2 block text-red-600 font-bold text-sm">အရေးကြီး Noti အဖြစ် သတ်မှတ်မည် (Urgent Alert)</label>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">ပုံ ပူးတွဲပို့ရန် (ရွေးချယ်နိုင်သည်)</label>
                <input type="file" name="image" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </div>
            <button type="submit" name="send_noti" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition w-full">
                <i class="fas fa-paper-plane mr-2"></i> <?= $target_user_id > 0 ? "ပို့မည်" : "အားလုံးထံသို့ ပို့မည်" ?>
            </button>
            <?php if ($target_user_id > 0): ?>
                <div class="mt-4 text-center">
                    <a href="admin_notifications.php" class="text-blue-500 hover:underline text-sm"><i class="fas fa-users mr-1"></i> အားလုံးထံသို့ ပို့ရန် ပြောင်းမည်</a>
                </div>
            <?php endif; ?>
        </form>

        <h2 class="text-lg font-bold text-gray-700 mb-4 border-b pb-2">
            <?= $target_user_id > 0 ? "ယခင်ပို့ထားသော သီးသန့် အသိပေးစာများ" : "ယခင်ပို့ထားသော အများပြည်သူဆိုင်ရာ အသိပေးစာများ" ?>
        </h2>
        <div class="space-y-3">
            <?php foreach ($recent_notis as $noti): ?>
                <div class="bg-white p-4 rounded shadow-sm border-l-4 border-blue-500">
                    <?php if (!empty($noti['image_url']) && file_exists($noti['image_url'])): ?>
                        <img src="<?= htmlspecialchars($noti['image_url']) ?>" alt="Notification Image" class="w-48 h-auto rounded-md mb-2 object-cover">
                    <?php endif; ?>
                    <p class="text-gray-800 text-sm mb-2"><?= nl2br(htmlspecialchars($noti['message'])) ?></p>
                    <p class="text-[10px] text-gray-500"><i class="far fa-clock"></i> <?= date('d-M-Y h:i A', strtotime($noti['created_at'])) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>