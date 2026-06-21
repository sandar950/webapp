<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Admin အားလုံး ဝင်ခွင့်ရှိသည်
require_admin_login();

$success_message = "";
$error_message = "";

$target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$target_user_name = "";
$target_user_telegram = "";

if ($target_user_id > 0) {
    $stmt = $conn->prepare("SELECT username, phone_number, telegram_chat_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $u = $res->fetch_assoc();
        $target_user_name = $u['username'] . " (" . $u['phone_number'] . ")";
        $target_user_telegram = $u['telegram_chat_id'];
    } else {
        $target_user_id = 0;
    }
    $stmt->close();
} else {
    die("<h2 style='text-align:center; margin-top:50px;'>User ID မပါဝင်ပါ။</h2>");
}

// Form Submit (မက်ဆေ့ချ်ပို့သောအခါ)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_msg'])) {
    $admin_reply = trim($_POST['message'] ?? '');
    
    if (!empty($admin_reply)) {
        $conn->begin_transaction();
        try {
            // user_id ဖြင့် support_messages ထဲသို့ ထည့်မည်။
            // message ကို အလွတ်ထားပြီး admin_reply တွင်သာ Admin ၏စာကိုထည့်မည်။ status ကို 'replied' ဟုထားမည်။
            $stmt = $conn->prepare("INSERT INTO support_messages (user_id, message, admin_reply, status) VALUES (?, '', ?, 'replied')");
            $stmt->bind_param("is", $target_user_id, $admin_reply);
            $stmt->execute();
            $stmt->close();

            // User အား ဝင်ဖတ်ရန် Notification တစ်ခုပို့ပေးမည်
            $noti_msg = "💬 Admin မှ သင့်ထံသို့ Direct Message ပေးပို့ထားပါသည်။ 'ဆက်သွယ်ရန် (Support)' တွင် ဝင်ရောက်ဖတ်ရှုပါ။";
            $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
            $stmt->bind_param("is", $target_user_id, $noti_msg);
            $stmt->execute();
            $stmt->close();

            $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = $target_user_id");

            $conn->commit();
            log_activity($_SESSION['user_id'], 'SEND_DIRECT_MESSAGE', "Sent a direct message to User ID: {$target_user_id}");
            
            $success_message = "Message ကို အောင်မြင်စွာ ပို့ဆောင်ပြီးပါပြီ။";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "အမှားအယွင်းဖြစ်ပေါ်နေပါသည်။ " . $e->getMessage();
        }
    } elseif (empty($error_message)) {
        $error_message = "ကျေးဇူးပြု၍ မက်ဆေ့ချ် ရေးသားပါ သို့မဟုတ် ပုံထည့်သွင်းပါ။";
    }
}

// ယခင်သမိုင်းကြောင်းများ ဆွဲထုတ်မည်
$history_query = "SELECT message, admin_reply, admin_attachment_url, status, is_read, created_at FROM support_messages WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php 
$page_title = "Admin - Send Direct Message";
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = "Direct Message ပို့မည်";
    $header_icon = "fas fa-comment-dots";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-6 pt-0">
        <div class="flex justify-between items-center border-b pb-3 mb-6 mt-4">
            <a href="admin_users.php" class="text-blue-600 hover:underline text-sm font-bold"><i class="fas fa-arrow-left mr-1"></i> နောက်သို့</a>
            <h2 class="text-lg font-bold text-gray-700">User: <span class="text-primary"><?= htmlspecialchars($target_user_name) ?></span></h2>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 shadow-sm text-sm font-bold"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 shadow-sm text-sm font-bold"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="bg-white p-5 rounded-xl shadow-md mb-8 border-t-4 border-blue-500">
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Message ရေးသားရန်</label>
                <textarea name="message" rows="4" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none" placeholder="User ထံသို့ တိုက်ရိုက်ပြောလိုသော စာသားကို ရေးပါ..."></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">ပုံ ပူးတွဲပို့ရန် (ရွေးချယ်နိုင်သည်)</label>
                <label for="admin_attachment" class="w-full flex items-center justify-center gap-2 bg-gray-50 hover:bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg p-3 cursor-pointer transition">
                    <i class="fas fa-paperclip text-gray-500"></i>
                    <span class="text-sm text-gray-600 font-bold">ပုံရွေးချယ်ရန်</span>
                </label>
                <input type="file" id="admin_attachment" name="admin_attachment" accept="image/*" class="hidden" onchange="document.getElementById('file-chosen').textContent = this.files[0] ? this.files[0].name : ''">
                <p id="file-chosen" class="text-xs text-center text-gray-500 mt-2"></p>
            </div>
            <button type="submit" name="send_msg" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-sm shadow-sm transition w-full md:w-auto">
                <i class="fas fa-paper-plane mr-2"></i> မက်ဆေ့ချ် ပို့မည်
            </button>
        </form>
    </div>

</body>
</html>