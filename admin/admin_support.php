<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_admin_login(); // All admins can view/reply to support

$success_message = "";
$error_message = "";

// အကြောင်းပြန် Form Submit လုပ်သောအခါ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_msg'])) {
    $msg_id = intval($_POST['msg_id'] ?? 0);
    $reply_text = trim($_POST['admin_reply'] ?? '');
    $user_id = intval($_POST['user_id'] ?? 0);
    $admin_attachment_url = null;

    // Handle file upload
    if (isset($_FILES['admin_attachment']) && $_FILES['admin_attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/support/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }
        $file_info = pathinfo($_FILES['admin_attachment']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed_exts) && $_FILES['admin_attachment']['size'] <= 5 * 1024 * 1024) { // 5MB limit
            $new_filename = 'admin_support_' . $user_id . '_' . time() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            $db_path = 'uploads/support/' . $new_filename;
            require_once __DIR__ . '/../core/image_helper.php';
            if (compressImage($_FILES['admin_attachment']['tmp_name'], $upload_path, 60)) {
                $admin_attachment_url = $db_path;
            }
        } else {
            $error_message = "5MB ထက်မကြီးသော JPG, PNG, GIF, WEBP ပုံများကိုသာ လက်ခံပါသည်။";
        }
    }

    // Get Target User Telegram ID
    $target_user_telegram = "";
    $tg_user_stmt = $conn->prepare("SELECT telegram_chat_id FROM users WHERE id = ?");
    $tg_user_stmt->bind_param("i", $user_id);
    $tg_user_stmt->execute();
    $tg_res = $tg_user_stmt->get_result();
    if ($tg_res->num_rows > 0) $target_user_telegram = $tg_res->fetch_assoc()['telegram_chat_id'];
    $tg_user_stmt->close();

    if ($msg_id > 0 && (!empty($reply_text) || !empty($admin_attachment_url)) && empty($error_message)) {
        $conn->begin_transaction();
        try {
            // is_read Column ရှိမရှိ အရင်စစ်ဆေးမည်
            $check_read_col = $conn->query("SHOW COLUMNS FROM support_messages LIKE 'is_read'");
            if ($check_read_col && $check_read_col->num_rows == 0) {
                $conn->query("ALTER TABLE support_messages ADD is_read BOOLEAN DEFAULT FALSE AFTER status");
            }

            $stmt = $conn->prepare("UPDATE support_messages SET admin_reply = ?, admin_attachment_url = ?, status = 'replied', is_read = 0 WHERE id = ?");
            $stmt->bind_param("ssi", $reply_text, $admin_attachment_url, $msg_id);
            $stmt->execute();
            $stmt->close();

            // User အား Notification လှမ်းပို့ပေးမည်
            $noti_msg = "💬 သင့်၏ Support Message ကို Admin မှ ပြန်လည်ဖြေကြားထားပါသည်။";
            $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $noti_msg);
            $stmt->execute();
            $stmt->close();
            
            $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = $user_id");

            // --- Telegram သို့ တိုက်ရိုက် (DM) ပို့မည် ---
            if (!empty($target_user_telegram)) {
                $tg_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_bot_token'");
                $bot_token = $tg_stmt->fetch_assoc()['setting_value'] ?? '';

                if (!empty($bot_token)) {
                    $telegram_msg = "💬 *Support: Admin မှ သင့်ထံသို့ ပြန်လည်ဖြေကြားထားပါသည်။*\n\n";
                    $telegram_msg .= "📝 အကြောင်းပြန်ချက်: \n" . $reply_text . "\n\n";
                    $host = $_SERVER['HTTP_HOST'];
                    $telegram_msg .= "Website တွင် ဝင်ရောက်ဖတ်ရှုရန် - [Login ဝင်ရန်](http://{$host}/login.php)";

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

            $conn->commit();
            log_activity($_SESSION['user_id'], 'REPLY_SUPPORT', "Replied to support message ID: {$msg_id} for User ID: {$user_id}");
            $success_message = "အကြောင်းပြန်ချက်ကို အောင်မြင်စွာ ပို့ဆောင်ပြီးပါပြီ။";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "အမှားအယွင်းဖြစ်ပေါ်နေပါသည်။ " . $e->getMessage();
        }
    } elseif (empty($error_message)) {
        $error_message = "ကျေးဇူးပြု၍ ပြန်လည်ဖြေကြားမည့် စာသားကို ရေးသားပါ သို့မဟုတ် ပုံထည့်သွင်းပါ။";
    }
}

// မက်ဆေ့ချ်များအားလုံးကို ဆွဲထုတ်မည် (New မှတ်တမ်းများကို အပေါ်ဆုံးတွင် ပြမည်)
$query = "SELECT s.*, u.username, u.phone_number 
          FROM support_messages s 
          JOIN users u ON s.user_id = u.id 
          ORDER BY s.status ASC, s.created_at DESC";
$result = $conn->query($query);
$messages = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$pending_support = 0;
foreach($messages as $m) {
    if($m['status'] == 'pending') $pending_support++;
}
?>

<?php 
$page_title = "Admin - Support Messages";
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = "Support & Messages";
    $header_icon = "fas fa-headset";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 shadow-sm text-sm font-bold"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 shadow-sm text-sm font-bold"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="flex flex-wrap justify-between items-center border-b-2 border-blue-500 pb-2 mb-6 mt-4 gap-2">
            <h2 class="text-lg font-bold text-gray-700"><i class="fas fa-inbox mr-1"></i> ဝင်ရောက်လာသော မက်ဆေ့ချ်များ</h2>
            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-bold">New: <?= $pending_support ?></span>
        </div>

        <?php if (count($messages) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($messages as $msg): ?>
                    <div class="bg-white rounded-xl shadow-md overflow-hidden border <?= $msg['status'] == 'pending' ? 'border-l-4 border-yellow-500' : 'border-gray-200 opacity-80' ?>">
                        <div class="p-4 bg-gray-50 border-b flex flex-wrap justify-between items-center gap-2">
                            <div>
                                <p class="font-bold text-gray-800"><i class="fas fa-user-circle text-primary mr-1"></i> <?= htmlspecialchars($msg['username']) ?> <span class="text-xs text-gray-500 font-normal">(<?= htmlspecialchars($msg['phone_number']) ?>)</span></p>
                                <p class="text-[10px] text-gray-500 mt-1"><i class="far fa-clock"></i> <?= date('d-M-Y h:i A', strtotime($msg['created_at'])) ?></p>
                            </div>
                            <?php if ($msg['status'] == 'pending'): ?>
                                <span class="bg-yellow-100 text-yellow-700 text-xs px-2 py-1 rounded border border-yellow-200">New</span>
                            <?php else: ?>
                                <span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded border border-green-200"><i class="fas fa-check"></i> Replied</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <div class="bg-gray-100 p-3 rounded-lg border border-gray-200 mb-4">
                                <?php if (!empty($msg['attachment_url']) && file_exists('../' . ltrim($msg['attachment_url'], '../'))): ?>
                                    <a href="../<?= ltrim(htmlspecialchars($msg['attachment_url']), '../') ?>" target="_blank" class="block mb-2">
                                        <img src="../<?= ltrim(htmlspecialchars($msg['attachment_url']), '../') ?>" class="rounded-lg max-w-full h-auto" style="max-height: 250px;">
                                    </a>
                                <?php endif; ?>
                                <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                            </div>
                            
                            <?php if ($msg['status'] == 'pending'): ?>
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
                                    <input type="hidden" name="user_id" value="<?= $msg['user_id'] ?>">
                                    <textarea name="admin_reply" rows="2" class="w-full py-2 px-3 border rounded-lg focus:border-blue-500 focus:outline-none text-sm mb-2" placeholder="အကြောင်းပြန်စာ ရေးရန်..."></textarea>
                                    <div class="mb-2">
                                        <label for="admin_attachment_<?= $msg['id'] ?>" class="cursor-pointer text-sm text-blue-600 hover:underline"><i class="fas fa-paperclip"></i> ပုံထည့်ရန်</label>
                                        <input type="file" id="admin_attachment_<?= $msg['id'] ?>" name="admin_attachment" accept="image/*" class="hidden" onchange="document.getElementById('file_name_<?= $msg['id'] ?>').textContent = this.files[0].name">
                                        <span id="file_name_<?= $msg['id'] ?>" class="text-xs text-gray-500 ml-2"></span>
                                    </div>
                                    <button type="submit" name="reply_msg" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow-sm text-sm transition"><i class="fas fa-reply mr-1"></i> အကြောင်းပြန်မည်</button>
                                </form>
                            <?php else: ?>
                                <div class="bg-blue-50 border border-blue-100 p-3 rounded-lg">
                                    <p class="text-xs font-bold text-blue-800 mb-1"><i class="fas fa-headset mr-1"></i> သင်၏ အကြောင်းပြန်ချက်:</p>
                                <?php if (!empty($msg['admin_attachment_url']) && file_exists('../' . ltrim($msg['admin_attachment_url'], '../'))): ?>
                                    <a href="../<?= ltrim(htmlspecialchars($msg['admin_attachment_url']), '../') ?>" target="_blank" class="block mb-2">
                                        <img src="../<?= ltrim(htmlspecialchars($msg['admin_attachment_url']), '../') ?>" class="rounded-lg max-w-full h-auto" style="max-height: 150px;">
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($msg['admin_reply'])): ?>
                                        <p class="text-sm text-blue-900"><?= nl2br(htmlspecialchars($msg['admin_reply'])) ?></p>
                                    <?php endif; ?>
                                    <div class="text-right mt-2 border-t border-blue-100 pt-1">
                                        <?php if (isset($msg['is_read']) && $msg['is_read']): ?>
                                            <span class="text-[10px] text-blue-600 font-bold" title="User မှ ဖတ်ပြီးပါပြီ"><i class="fas fa-check-double mr-1"></i>Seen</span>
                                        <?php else: ?>
                                            <span class="text-[10px] text-gray-500 font-bold" title="User ထံ ပို့ပြီးပါပြီ"><i class="fas fa-check mr-1"></i>Delivered</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-md p-10 text-center">
                <i class="fas fa-envelope-open-text text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-gray-500 text-sm">မက်ဆေ့ချ်များ မရှိသေးပါ။</p>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>