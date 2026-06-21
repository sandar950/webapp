<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/lang/language.php';

$user_id = $_SESSION['user_id'];
$user_avatar_stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
$user_avatar_stmt->bind_param("i", $user_id);
$user_avatar_stmt->execute();
$user_avatar_res = $user_avatar_stmt->get_result()->fetch_assoc();
$user_avatar = $user_avatar_res['avatar'] ?? null;
$user_avatar_stmt->close();

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_msg'])) {
        $msg_id = intval($_POST['msg_id'] ?? 0);
        if ($msg_id > 0) {
            // မိမိ၏ မက်ဆေ့ချ်ကိုသာ ဖျက်ခွင့်ပြုမည်
            $del_stmt = $conn->prepare("DELETE FROM support_messages WHERE id = ? AND user_id = ?");
            $del_stmt->bind_param("ii", $msg_id, $user_id);
            if ($del_stmt->execute()) {
                $success_message = __('support_msg_deleted_success');
            } else {
                $error_message = __('support_msg_delete_error');
            }
            $del_stmt->close();
        }
    } elseif (isset($_POST['message'])) {
        $message = trim($_POST['message']);
        $attachment_url = null;

        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/support/';
            if (!is_dir($upload_dir)) {
                if (!@mkdir($upload_dir, 0777, true)) {
                    $error_message = __('support_upload_dir_error');
                }
            }
            
            if (empty($error_message)) {
                $file_info = pathinfo($_FILES['attachment']['name']);
                $ext = strtolower($file_info['extension']);
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($ext, $allowed_exts) && $_FILES['attachment']['size'] <= 5 * 1024 * 1024) { // 5MB limit
                    $new_filename = 'support_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_path = $upload_dir . $new_filename;
                    require_once __DIR__ . '/core/image_helper.php';
                    if (compressImage($_FILES['attachment']['tmp_name'], $upload_path, 60)) {
                        $attachment_url = $upload_path;
                    }
                } else {
                    $error_message = __('support_image_size_error');
                }
            }
        }

        if (!empty($message) || !empty($attachment_url)) {
            // Database သို့ သိမ်းဆည်းမည်
            $stmt = $conn->prepare("INSERT INTO support_messages (user_id, message, attachment_url) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $message, $attachment_url);
        
        if ($stmt->execute()) {
            // Telegram သို့ Notification ပို့မည် 
            $tg_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('telegram_bot_token', 'telegram_channel_id')");
            $tg_settings = [];
            if ($tg_stmt) {
                while ($tg_row = $tg_stmt->fetch_assoc()) {
                    $tg_settings[$tg_row['setting_key']] = $tg_row['setting_value'];
                }
            }
            $bot_token = $tg_settings['telegram_bot_token'] ?? '';
            $admin_chat_id = $tg_settings['telegram_channel_id'] ?? '';

            if (!empty($bot_token) && !empty($admin_chat_id)) {
                $telegram_msg = __('admin_support_noti_title') . "\n\n";
                $telegram_msg .= __('admin_support_noti_user_id') . " `" . $user_id . "`\n";
                if ($attachment_url) $telegram_msg .= __('admin_support_noti_attachment') . "\n";
                $telegram_msg .= __('admin_support_noti_message') . "\n" . $message . "\n\n";

                $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
                $telegram_data = [
                    'chat_id' => $admin_chat_id,
                    'text' => $telegram_msg,
                    'parse_mode' => 'Markdown'
                ];

                $ch = curl_init($telegram_url);
                // ပြင်ဆင်ချက်: Timeout ကို 1.5s သို့ ပြောင်းလဲထားပါသည်
                curl_setopt_array($ch, [
                    CURLOPT_URL => $telegram_url, 
                    CURLOPT_POST => TRUE, 
                    CURLOPT_RETURNTRANSFER => TRUE, 
                    CURLOPT_TIMEOUT_MS => 1500, 
                    CURLOPT_NOSIGNAL => 1, 
                    CURLOPT_POSTFIELDS => http_build_query($telegram_data)
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
            
            $success_message = __('support_msg_sent_success');
        } else {
            $error_message = __('system_error_try_again');
        }
        $stmt->close();
    } elseif (empty($error_message)) {
        $error_message = __('support_empty_msg_error');
    }
}
}

// is_read Column မရှိသေးပါက အလိုအလျောက် ထည့်သွင်းပေးမည်
$check_read_col = $conn->query("SHOW COLUMNS FROM support_messages LIKE 'is_read'");
if ($check_read_col && $check_read_col->num_rows == 0) {
    $conn->query("ALTER TABLE support_messages ADD is_read BOOLEAN DEFAULT FALSE AFTER status");
}

// ယခင်ပို့ထားသော မက်ဆေ့ချ်များကို ဆွဲထုတ်မည်
$stmt = $conn->prepare("SELECT id, message, attachment_url, admin_reply, admin_attachment_url, status, is_read, created_at FROM support_messages WHERE user_id = ? ORDER BY created_at ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// မဖတ်ရသေးသော စာများရှိပါက ဖတ်ပြီးအဖြစ် သတ်မှတ်မည်
$conn->query("UPDATE support_messages SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");
?>

<?php 
$page_title = __('support_page_title');
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="w-full md:max-w-3xl lg:max-w-4xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24 flex flex-col">

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="profile.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center pr-6 md:pr-10 tracking-wide"><?= __('support_title') ?></h1>
    </div>

    <div class="p-4 md:p-8 flex-1 flex flex-col max-w-3xl mx-auto w-full">
        <div class="text-center mb-6 md:mb-8 mt-2 md:mt-4">
            <div class="w-16 h-16 md:w-20 md:h-20 bg-blue-100 text-primary rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm border-4 border-white"><i class="fas fa-headset text-3xl md:text-4xl"></i></div>
            <p class="text-gray-700 font-bold md:text-lg"><?= __('need_help_title') ?></p>
            <p class="text-xs md:text-sm text-gray-500 mt-1.5 md:mt-2 font-medium"><?= __('need_help_desc') ?></p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 md:py-4 rounded-xl relative mb-5 text-sm md:text-base shadow-sm font-medium flex items-center">
                <i class="fas fa-check-circle mr-2 text-green-500 text-lg"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 md:py-4 rounded-xl relative mb-5 text-sm md:text-base shadow-sm font-medium flex items-center">
                <i class="fas fa-exclamation-circle mr-2 text-red-500 text-lg"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="bg-white p-5 md:p-8 rounded-xl md:rounded-2xl shadow-sm md:shadow-md mb-6 md:mb-8 border border-gray-200">
            <div class="mb-4 md:mb-5">
                <textarea name="message" rows="3" class="w-full py-3 md:py-4 px-4 border border-gray-200 rounded-lg md:rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none bg-gray-50 text-gray-700 text-sm md:text-base transition-all" placeholder="<?= __('support_textarea_placeholder') ?>"></textarea>
            </div>
            <div class="mb-5 md:mb-6">
                <label for="attachment" class="w-full flex items-center justify-center gap-2 bg-gray-50 hover:bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg md:rounded-xl p-3 md:p-4 cursor-pointer transition-all duration-300 group">
                    <i class="fas fa-image text-gray-400 group-hover:text-blue-500 text-lg md:text-xl transition-colors"></i>
                    <span class="text-sm md:text-base text-gray-600 font-bold group-hover:text-blue-600 transition-colors"><?= __('support_attach_image') ?></span>
                </label>
                <input type="file" id="attachment" name="attachment" accept="image/*" class="hidden">
                <p id="file-chosen" class="text-xs md:text-sm text-center text-gray-500 mt-2.5 font-medium"></p>
            </div>

            <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3.5 md:py-4 rounded-lg md:rounded-xl text-base md:text-lg shadow-md hover:shadow-lg transition-all duration-300 hover:-translate-y-0.5"><i class="fas fa-paper-plane mr-2"></i> <?= __('support_send_btn') ?></button>
        </form>

        <?php if (count($history) > 0): ?>
            <div class="space-y-4 md:space-y-6">
                <?php foreach ($history as $msg): ?>
                    
                    <?php if (!empty(trim($msg['message'])) || !empty($msg['attachment_url'])): ?>
                        <div class="flex items-end gap-2 md:gap-3">
                            <?php if (!empty($user_avatar)): ?>
                                <img src="<?= htmlspecialchars($user_avatar) ?>" class="w-8 h-8 md:w-10 md:h-10 rounded-full object-cover shrink-0 shadow-sm border border-gray-200">
                            <?php else: ?>
                                <div class="w-8 h-8 md:w-10 md:h-10 bg-gray-200 rounded-full flex justify-center items-center shrink-0 shadow-sm border border-gray-300"><i class="fas fa-user text-gray-500 text-sm md:text-base"></i></div>
                            <?php endif; ?>
                            
                            <div class="bg-white border border-gray-200 p-3 md:p-4 rounded-tr-xl rounded-bl-xl rounded-br-xl md:rounded-tr-2xl md:rounded-bl-2xl md:rounded-br-2xl max-w-[85%] md:max-w-[70%] shadow-sm relative group">
                                <?php if (!empty($msg['attachment_url']) && file_exists($msg['attachment_url'])): ?>
                                    <a href="<?= htmlspecialchars($msg['attachment_url']) ?>" target="_blank" class="block mb-2 overflow-hidden rounded-lg">
                                        <img src="<?= htmlspecialchars($msg['attachment_url']) ?>" class="w-full h-auto object-cover hover:scale-105 transition-transform duration-300" style="max-height: 250px;">
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty(trim($msg['message']))): ?>
                                    <p class="text-sm md:text-base text-gray-800 px-1 leading-relaxed"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <form method="POST" action="" class="shrink-0 mb-1 opacity-0 hover:opacity-100 transition-opacity" onsubmit="return confirm('<?= __('confirm_delete_msg') ?>');">
                                <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
                                <button type="submit" name="delete_msg" class="text-gray-400 hover:text-red-500 p-1.5 md:p-2 bg-gray-100 hover:bg-red-50 rounded-full transition-colors" title="<?= __('delete_msg_tooltip') ?>"><i class="fas fa-trash-alt text-xs md:text-sm"></i></button>
                            </form>
                        </div>
                        <p class="text-[10px] md:text-xs text-gray-400 mt-1 ml-11 md:ml-14 font-medium"><i class="far fa-clock mr-1"></i> <?= date('d-M-Y h:i A', strtotime($msg['created_at'])) ?></p>
                    <?php endif; ?>

                    <div class="mt-2 md:mt-3">
                    <?php if ($msg['status'] === 'replied' && (!empty($msg['admin_reply']) || !empty($msg['admin_attachment_url']))): ?>
                        <div class="flex items-end gap-2 md:gap-3 flex-row-reverse">
                            <div class="w-8 h-8 md:w-10 md:h-10 bg-primary rounded-full flex justify-center items-center shrink-0 shadow-sm border border-blue-400"><i class="fas fa-headset text-white text-sm md:text-base"></i></div>
                            <div class="bg-primary text-white p-3 md:p-4 rounded-tl-xl rounded-bl-xl rounded-br-xl md:rounded-tl-2xl md:rounded-bl-2xl md:rounded-br-2xl max-w-[85%] md:max-w-[70%] relative shadow-md">
                                <?php if (!$msg['is_read']): ?>
                                    <span class="absolute -top-2 -left-2 bg-red-500 text-white text-[9px] md:text-[10px] font-bold px-2 py-0.5 rounded shadow-sm animate-pulse tracking-wide">NEW</span>
                                <?php endif; ?>
                                <?php if (!empty($msg['admin_attachment_url']) && file_exists($msg['admin_attachment_url'])): ?>
                                    <a href="<?= htmlspecialchars($msg['admin_attachment_url']) ?>" target="_blank" class="block mb-2 overflow-hidden rounded-lg">
                                        <img src="<?= htmlspecialchars($msg['admin_attachment_url']) ?>" class="w-full h-auto object-cover hover:scale-105 transition-transform duration-300" style="max-height: 250px;">
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty(trim($msg['admin_reply']))): ?>
                                    <p class="text-sm md:text-base px-1 leading-relaxed"><?= nl2br(htmlspecialchars($msg['admin_reply'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="text-[10px] md:text-xs text-gray-400 mt-1 mr-11 md:mr-14 text-right font-medium"><i class="fas fa-check-double text-blue-500 mr-1"></i> <?= __('admin_reply_label') ?></p>
                    
                    <?php elseif (empty(trim($msg['message'])) && empty($msg['attachment_url']) && (!empty($msg['admin_reply']) || !empty($msg['admin_attachment_url']))): ?>
                        <div class="flex items-end gap-2 md:gap-3 flex-row-reverse">
                            <div class="w-8 h-8 md:w-10 md:h-10 bg-primary rounded-full flex justify-center items-center shrink-0 shadow-sm border border-blue-400"><i class="fas fa-headset text-white text-sm md:text-base"></i></div>
                            <div class="bg-primary text-white p-3 md:p-4 rounded-tl-xl rounded-bl-xl rounded-br-xl md:rounded-tl-2xl md:rounded-bl-2xl md:rounded-br-2xl max-w-[85%] md:max-w-[70%] relative shadow-md">
                                <?php if (!$msg['is_read']): ?>
                                    <span class="absolute -top-2 -left-2 bg-red-500 text-white text-[9px] md:text-[10px] font-bold px-2 py-0.5 rounded shadow-sm animate-pulse tracking-wide">NEW</span>
                                <?php endif; ?>
                                <?php if (!empty($msg['admin_attachment_url']) && file_exists($msg['admin_attachment_url'])): ?>
                                    <a href="<?= htmlspecialchars($msg['admin_attachment_url']) ?>" target="_blank" class="block mb-2 overflow-hidden rounded-lg">
                                        <img src="<?= htmlspecialchars($msg['admin_attachment_url']) ?>" class="w-full h-auto object-cover hover:scale-105 transition-transform duration-300" style="max-height: 250px;">
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty(trim($msg['admin_reply']))): ?>
                                    <p class="text-sm md:text-base px-1 leading-relaxed"><?= nl2br(htmlspecialchars($msg['admin_reply'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="text-[10px] md:text-xs text-gray-400 mt-1 mr-11 md:mr-14 text-right font-medium"><?= __('admin_label') ?> • <?= date('d-M-Y h:i A', strtotime($msg['created_at'])) ?></p>
                    <?php endif; ?>
                    </div>
                    
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const attachmentInput = document.getElementById('attachment');
        const fileChosen = document.getElementById('file-chosen');

        attachmentInput.addEventListener('change', function(){
            if (this.files.length > 0) {
                fileChosen.textContent = this.files[0].name;
                // Add a visual indicator that a file is selected
                document.querySelector('label[for="attachment"]').classList.add('border-blue-400', 'bg-blue-50');
            } else {
                fileChosen.textContent = '';
                document.querySelector('label[for="attachment"]').classList.remove('border-blue-400', 'bg-blue-50');
            }
        });
        
        // Auto scroll to bottom of chat
        window.onload = function() {
            window.scrollTo(0, document.body.scrollHeight);
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>