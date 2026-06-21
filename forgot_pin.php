<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/core/security_helper.php';

$user_id = $_SESSION['user_id'];
$error_message = "";
$success_message = "";
$step = 1; // 1: Password, 2: OTP, 3: New PIN

$stmt = $conn->prepare("SELECT password, username, phone_number, telegram_chat_id, transaction_pin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'verify_password') {
        $password = $_POST['login_password'] ?? '';
        if (password_verify($password, $user['password'])) {
            $bot_token = '';
            $tg_set_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_bot_token'");
            if ($tg_set_stmt) $bot_token = $tg_set_stmt->fetch_assoc()['setting_value'] ?? '';

            if (!empty($user['telegram_chat_id']) && !empty($bot_token)) {
                $otp = rand(100000, 999999);
                $_SESSION['pin_reset_otp'] = (string)$otp;
                $_SESSION['pin_reset_otp_expiry'] = time() + 300;
                
                $telegram_msg = "🔐 *PIN ပြန်လည်သတ်မှတ်ခြင်း*\n\nသင့်အကောင့် (" . $user['username'] . ") အတွက် လုံခြုံရေးကုဒ် (OTP) မှာ: *{$otp}* ဖြစ်ပါသည်။";
                $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
                $telegram_data = ['chat_id' => $user['telegram_chat_id'], 'text' => $telegram_msg, 'parse_mode' => 'Markdown'];

                $ch = curl_init($telegram_url);
                curl_setopt_array($ch, [CURLOPT_URL => $telegram_url, CURLOPT_POST => TRUE, CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 3, CURLOPT_POSTFIELDS => http_build_query($telegram_data)]);
                curl_exec($ch);
                curl_close($ch);
                
                $step = 2;
                $success_message = "သင့် Telegram သို့ OTP ပို့ပေးထားပါသည်။";
            } else {
                $step = 3;
            }
        } else {
            $error_message = "အကောင့်စကားဝှက် မှားယွင်းနေပါသည်။";
        }
    } elseif ($action === 'verify_otp') {
        $entered_otp = $_POST['otp'] ?? '';
        if (isset($_SESSION['pin_reset_otp']) && time() < $_SESSION['pin_reset_otp_expiry'] && $entered_otp === $_SESSION['pin_reset_otp']) {
            unset($_SESSION['pin_reset_otp'], $_SESSION['pin_reset_otp_expiry']);
            $step = 3;
        } else {
            $error_message = "OTP ကုဒ် မှားယွင်းနေပါသည် သို့မဟုတ် သက်တမ်းကုန်သွားပါပြီ။";
            $step = 2;
        }
    } elseif ($action === 'reset_pin') {
        $pin = ($_POST['pin1']??'').($_POST['pin2']??'').($_POST['pin3']??'').($_POST['pin4']??'').($_POST['pin5']??'').($_POST['pin6']??'');
        $confirm_pin = ($_POST['confirm_pin1']??'').($_POST['confirm_pin2']??'').($_POST['confirm_pin3']??'').($_POST['confirm_pin4']??'').($_POST['confirm_pin5']??'').($_POST['confirm_pin6']??'');

        if (strlen($pin) !== 6 || !is_numeric($pin)) {
            $error_message = "PIN ဂဏန်း ၆ လုံး ပြည့်အောင် ထည့်ပါ။";
            $step = 3;
        } elseif ($pin !== $confirm_pin) {
            $error_message = "PIN နံပါတ်နှစ်ခု တူညီမှုမရှိပါ။";
            $step = 3;
        } elseif (!empty($user['transaction_pin']) && password_verify($pin, $user['transaction_pin'])) {
            $error_message = "PIN အသစ်သည် PIN ဟောင်းနှင့် မတူရပါ။ ကျေးဇူးပြု၍ အခြားဂဏန်းကို ရွေးချယ်ပါ။";
            $step = 3;
        } elseif (password_verify($pin, $user['password'])) {
            $error_message = "PIN အသစ်သည် အကောင့်စကားဝှက် (Login Password) နှင့် မတူရပါ။";
            $step = 3;
        } else {
            $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET transaction_pin = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_pin, $user_id);
            if ($stmt->execute()) {
                $success_message = "လုံခြုံရေး PIN ကို အောင်မြင်စွာ ပြန်လည်သတ်မှတ်ပြီးပါပြီ။";
                $step = 4;

                // In-app Notification သိမ်းဆည်းမည်
                $noti_msg = "🔐 သင့်အကောင့်၏ လုံခြုံရေး PIN ကို အောင်မြင်စွာ ပြန်လည်သတ်မှတ်ပြီးပါပြီ။";
                $stmt_noti = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                $stmt_noti->bind_param("is", $user_id, $noti_msg);
                $stmt_noti->execute();
                $stmt_noti->close();
                $conn->query("UPDATE users SET notifications = notifications + 1 WHERE id = $user_id");

                // User ဆီသို့ Telegram မှ အောင်မြင်ကြောင်း Message ပို့ရန်
                if (!empty($user['telegram_chat_id'])) {
                    $tg_set_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_bot_token'");
                    $bot_token = ($tg_set_stmt) ? $tg_set_stmt->fetch_assoc()['setting_value'] ?? '' : '';

                    if (!empty($bot_token)) {
                        $user_tg_msg = "✅ *PIN Reset အောင်မြင်ပါသည်*\n\nသင့်အကောင့်အတွက် လုံခြုံရေး PIN ကို အောင်မြင်စွာ ပြန်လည်သတ်မှတ်ပြီးပါပြီ။";
                        $user_tg_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
                        $user_tg_data = ['chat_id' => $user['telegram_chat_id'], 'text' => $user_tg_msg, 'parse_mode' => 'Markdown'];

                        $ch_user = curl_init($user_tg_url);
                        curl_setopt_array($ch_user, [CURLOPT_URL => $user_tg_url, CURLOPT_POST => TRUE, CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 3, CURLOPT_POSTFIELDS => http_build_query($user_tg_data)]);
                        curl_exec($ch_user);
                        curl_close($ch_user);
                    }
                }

                // Admin ထံသို့ Telegram မှ Security Alert ပို့ရန်
                $alert_msg = "🔐 *PIN Reset Notification*\n\nUser: *{$user['username']}*\nPhone: `{$user['phone_number']}`\nStatus: PIN successfully reset via identity verification.";
                send_security_alert_to_telegram($alert_msg);
            } else {
                $error_message = "အမှားအယွင်းဖြစ်ပေါ်နေပါသည်။";
                $step = 3;
            }
            $stmt->close();
    }
}

$page_title = "Reset PIN - Thai 2D3D";
require_once __DIR__ . '/includes/header.php';
?>
<audio id="clickSound" src="assets/sounds/click.mp3" preload="auto"></audio>
<body class="max-w-md mx-auto min-h-screen bg-gray-100 shadow-xl flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-undo-alt text-3xl"></i></div>
            <h2 class="text-2xl font-bold text-gray-800">PIN ပြန်ယူမည်</h2>
            <p class="text-sm text-gray-500 mt-2">PIN မေ့သွားပါက ဤနေရာတွင် အသစ်ပြန်လည် သတ်မှတ်နိုင်ပါသည်။</p>
        </div>
        <?php if ($error_message): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm text-center font-bold shadow-sm"><?= $error_message ?></div><?php endif; ?>
        <?php if ($success_message && $step != 4): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm text-center font-bold shadow-sm"><?= $success_message ?></div><?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="verify_password">
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">အကောင့်စကားဝှက် (Login Password)</label>
                    <input type="password" name="login_password" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none" placeholder="••••••••" required>
                </div>
                <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-lg shadow-lg">အတည်ပြုမည်</button>
            </form>
        <?php elseif ($step === 2): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="verify_otp">
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Telegram OTP</label>
                    <input type="text" name="otp" class="w-full py-3 px-4 border rounded-lg text-center tracking-widest font-mono text-xl" maxlength="6" required placeholder="------">
                </div>
                <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-lg shadow-lg">OTP စစ်ဆေးမည်</button>
            </form>
        <?php elseif ($step === 3): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_pin">
                <label class="block text-gray-700 text-sm text-center font-bold mb-2">PIN အသစ် သတ်မှတ်ပါ</label>
                <div class="flex justify-center gap-2 mb-6">
                    <?php for($i=1; $i<=6; $i++): ?>
                        <input type="password" name="pin<?= $i ?>" maxlength="1" pattern="[0-9]" inputmode="numeric" class="w-10 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none" required onkeydown="handleKeyDown(event, <?= $i ?>, 'pin')" oninput="handleInput(this, <?= $i ?>, 'pin')">
                    <?php endfor; ?>
                </div>

                <label class="block text-gray-700 text-sm text-center font-bold mb-2">PIN ကို ထပ်မံရိုက်ထည့်ပါ (Confirm)</label>
                <div class="flex justify-center gap-2 mb-8">
                    <?php for($i=1; $i<=6; $i++): ?>
                        <input type="password" name="confirm_pin<?= $i ?>" maxlength="1" pattern="[0-9]" inputmode="numeric" class="w-10 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none" required onkeydown="handleKeyDown(event, <?= $i ?>, 'confirm_pin')" oninput="handleInput(this, <?= $i ?>, 'confirm_pin')">
                    <?php endfor; ?>
                </div>

                <button type="submit" class="w-full bg-green-600 text-white font-bold py-3 rounded-lg shadow-lg">PIN အသစ် သိမ်းမည်</button>
            </form>
        <?php elseif ($step === 4): ?>
            <div class="text-center">
                <div class="text-green-500 text-5xl mb-4"><i class="fas fa-check-circle"></i></div>
                <p class="font-bold text-gray-800 mb-6"><?= $success_message ?></p>
                <a href="profile.php" class="inline-block bg-primary text-white px-8 py-3 rounded-lg font-bold shadow-md w-full">Profile သို့ ပြန်သွားမည်</a>
            </div>
        <?php endif; ?>
        <div class="mt-4 text-center"><a href="profile.php" class="text-gray-500 text-sm underline">နောက်သို့ဆုတ်မည်</a></div>
    </div>
    <script>
        function playClickSound() {
            const snd = document.getElementById('clickSound');
            if (snd) { snd.currentTime = 0; snd.play().catch(e => {}); }
        }

        function handleInput(el, index, prefix) {
            // Regex ဖြင့် ဂဏန်းမဟုတ်သည်များကို ချက်ချင်းဖယ်ရှားမည် (ပိုမိုလုံခြုံသည်)
            el.value = el.value.replace(/[^0-9]/g, '');
            
            if (el.value.length > 0) {
                playClickSound();
                let next = document.querySelector(`input[name=${prefix}${index + 1}]`);
                if (next) next.focus();
            }
        }

        function handleKeyDown(e, index, prefix) {
            if (e.key === "Backspace" && e.target.value.length === 0) {
                let prev = document.querySelector(`input[name=${prefix}${index - 1}]`);
                if (prev) prev.focus();
            }
        }
    </script>
</body>
</html>