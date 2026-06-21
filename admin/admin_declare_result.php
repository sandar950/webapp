<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_permission('can_declare_result');

$success_message = "";
$error_message = "";

// လက်ရှိ အဖြေစောင့်ဆိုင်းနေသော 2D ထိုးကြေး စုစုပေါင်းကို ဆွဲထုတ်ခြင်း (Today)
$stmt_2d = $conn->query("SELECT bet_section, COUNT(*) as total_bets, SUM(amount) as total_amount FROM bets WHERE status = 'pending' AND LENGTH(bet_number) = 2 AND target_date = CURDATE() GROUP BY bet_section");
$pending_2d = ['morning' => ['total_bets' => 0, 'total_amount' => 0], 'evening' => ['total_bets' => 0, 'total_amount' => 0]];
while ($row = $stmt_2d->fetch_assoc()) {
    if (isset($pending_2d[$row['bet_section']])) {
        $pending_2d[$row['bet_section']] = $row;
    }
}

// လက်ရှိ အဖြေစောင့်ဆိုင်းနေသော 3D ထိုးကြေး စုစုပေါင်းကို ဆွဲထုတ်ခြင်း (Today)
$stmt_3d = $conn->query("SELECT COUNT(*) as total_bets, SUM(amount) as total_amount FROM bets WHERE status = 'pending' AND LENGTH(bet_number) = 3 AND target_date = CURDATE()");
$pending_3d = $stmt_3d->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $winning_number = trim($_POST['winning_number'] ?? '');
    $multiplier = intval($_POST['multiplier'] ?? 80);
    $bet_section = $_POST['bet_section'] ?? '';
    $target_date = $_POST['target_date'] ?? date('Y-m-d');

    // ၂ လုံး သို့မဟုတ် ၃ လုံး လက်ခံမည်
    if (preg_match('/^[0-9]{2,3}$/', $winning_number) && $multiplier > 0 && in_array($bet_section, ['morning', 'evening', '3d'])) {
        $conn->begin_transaction();
        try {
            $num_length = strlen($winning_number);
            
            // သက်ဆိုင်ရာ Pending ဖြစ်နေသော မှတ်တမ်းအားလုံးကို ယူမည်
            $stmt = $conn->prepare("SELECT id, user_id, bet_number, amount, odds FROM bets WHERE status = 'pending' AND LENGTH(bet_number) = ? AND bet_section = ? AND target_date = ? FOR UPDATE");
            $stmt->bind_param("iss", $num_length, $bet_section, $target_date);
            $stmt->execute();
            $pending_bets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (count($pending_bets) > 0) {
                $total_winners = 0;
                $total_payout = 0;

                $win_stmt = $conn->prepare("UPDATE bets SET status = 'win' WHERE id = ?");
                $lose_stmt = $conn->prepare("UPDATE bets SET status = 'lose' WHERE id = ?");
                $reward_stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $noti_stmt = $conn->prepare("INSERT INTO system_notifications (user_id, message) VALUES (?, ?)");
                $update_noti_stmt = $conn->prepare("UPDATE users SET notifications = notifications + 1 WHERE id = ?");

                foreach ($pending_bets as $bet) {
                    if ($bet['bet_number'] === $winning_number) {
                        // ပေါက်ပါက status ကို win ပြောင်းပြီး၊ balance သို့ လျော်ကြေးပေါင်းထည့်မည်
                        $win_stmt->bind_param("i", $bet['id']);
                        $win_stmt->execute();

                        $reward = $bet['amount'] * ($bet['odds'] ?? $multiplier); // Use bet-specific odds if available, otherwise fallback to global multiplier
                        $reward_stmt->bind_param("di", $reward, $bet['user_id']);
                        $reward_stmt->execute();

                        // ပေါက်သူထံသို့ Notification ပို့မည်
                        $noti_msg = sprintf(__('admin_declare_win_noti'), $bet['bet_number'], number_format($reward));
                        $noti_stmt->bind_param("is", $bet['user_id'], $noti_msg);
                        $noti_stmt->execute();
                        $update_noti_stmt->bind_param("i", $bet['user_id']);
                        $update_noti_stmt->execute();

                        $total_winners++;
                        $total_payout += $reward;
                    } else {
                        // မပေါက်ပါက status ကို lose ပြောင်းမည်
                        $lose_stmt->bind_param("i", $bet['id']);
                        $lose_stmt->execute();
                    }
                }
                
                // Close the session in betting_sessions
                $close_session_stmt = $conn->prepare("UPDATE betting_sessions SET status = 'closed' WHERE game_type = ? AND section = ? AND target_date = ?");
                $game_type = $num_length == 2 ? '2d' : '3d';
                $close_session_stmt->bind_param("sss", $game_type, $bet_section, $target_date);
                $close_session_stmt->execute();
                $close_session_stmt->close();

                $conn->commit();

                // Log the activity
                log_activity($_SESSION['user_id'], 'DECLARE_RESULT', "Declared {$result_type} result: {$winning_number}. Winners: {$total_winners}, Payout: " . number_format($total_payout));

                // ပေါက်ဂဏန်းကို မှတ်တမ်းတင်မည်
                $result_type = $num_length == 2 ? '2D' : '3D';
                $result_stmt = $conn->prepare("INSERT INTO result_history (result_number, type) VALUES (?, ?)");
                $result_stmt->bind_param("ss", $winning_number, $result_type);
                $result_stmt->execute();
                $result_stmt->close();

                // Telegram Channel သို့ Notification ပို့မည်
                $tg_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('telegram_bot_token', 'telegram_channel_id')");
                $tg_settings = [];
                while ($tg_row = $tg_stmt->fetch_assoc()) {
                    $tg_settings[$tg_row['setting_key']] = $tg_row['setting_value'];
                }
                $bot_token = $tg_settings['telegram_bot_token'] ?? '';
                $channel_id = $tg_settings['telegram_channel_id'] ?? '';

                if (!empty($bot_token) && !empty($channel_id)) {
                    $telegram_msg = sprintf(__('admin_declare_tg_msg'), $result_type, $winning_number, $multiplier);

                    $telegram_url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
                    $telegram_data = ['chat_id' => $channel_id, 'text' => $telegram_msg, 'parse_mode' => 'Markdown'];

                    $ch = curl_init($telegram_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($telegram_data));
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_exec($ch);
                    curl_close($ch);
                }

                $success_message = sprintf(__('admin_declare_success'), $winning_number, $total_winners, number_format($total_payout));
                // Set session variables for confetti effect in footer
                $_SESSION['confetti_winning_number'] = $winning_number;
                $_SESSION['confetti_message'] = $success_message; // Use the detailed success message for the confetti popup
                
                // အသစ်ပြန်ဖြစ်သွားစေရန် Refresh လုပ်ခြင်း
                if ($num_length == 2) {
                    $pending_2d['total_bets'] = 0;
                    $pending_2d['total_amount'] = 0;
                } else {
                    $pending_3d['total_bets'] = 0;
                    $pending_3d['total_amount'] = 0;
                }
            } else {
                $error_message = __('admin_declare_no_pending');
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = __('admin_tx_error') . " " . $e->getMessage();
        }
    } else {
        $error_message = __('admin_declare_invalid_input');
    }
}
?>

<?php 
$page_title = __('admin_declare_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_declare_header_title');
    $header_icon = "fas fa-bullhorn";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg shadow-sm">
                <h2 class="font-bold text-blue-800 mb-2"><?= __('admin_declare_2d_morning') ?></h2>
                <p class="text-gray-700"><?= __('admin_declare_tickets') ?><span class="font-bold"><?= number_format($pending_2d['morning']['total_bets'] ?? 0) ?></span></p>
                <p class="text-gray-700"><?= __('admin_declare_total_amount') ?><span class="font-bold text-red-600"><?= number_format($pending_2d['morning']['total_amount'] ?? 0) ?></span> Ks</p>
            </div>
            <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 rounded-lg shadow-sm">
                <h2 class="font-bold text-indigo-800 mb-2"><?= __('admin_declare_2d_evening') ?></h2>
                <p class="text-gray-700"><?= __('admin_declare_tickets') ?><span class="font-bold"><?= number_format($pending_2d['evening']['total_bets'] ?? 0) ?></span></p>
                <p class="text-gray-700"><?= __('admin_declare_total_amount') ?><span class="font-bold text-red-600"><?= number_format($pending_2d['evening']['total_amount'] ?? 0) ?></span> Ks</p>
            </div>
            <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded-lg shadow-sm">
                <h2 class="font-bold text-purple-800 mb-2"><?= __('admin_declare_3d_pending') ?></h2>
                <p class="text-gray-700"><?= __('admin_declare_tickets') ?><span class="font-bold"><?= number_format($pending_3d['total_bets'] ?? 0) ?></span></p>
                <p class="text-gray-700"><?= __('admin_declare_total_amount') ?><span class="font-bold text-red-600"><?= number_format($pending_3d['total_amount'] ?? 0) ?></span> Ks</p>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="bg-white p-4 sm:p-6 rounded-xl shadow-md border-t-4 border-primary">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2"><?= __('admin_declare_section') ?></label>
                    <select name="bet_section" id="bet_section" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none" required>
                        <option value="morning"><?= __('admin_declare_opt_morning') ?></option>
                        <option value="evening"><?= __('admin_declare_opt_evening') ?></option>
                        <option value="3d"><?= __('admin_declare_opt_3d') ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2"><?= __('admin_declare_date') ?></label>
                    <input type="date" name="target_date" value="<?= date('Y-m-d') ?>" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none" required>
                </div>
            </div>
            <div class="mb-5">
                <label class="block text-gray-700 font-bold mb-2 text-lg"><?= __('admin_declare_winning_number') ?></label>
                <input type="text" id="winning_number" name="winning_number" pattern="[0-9]{2,3}" maxlength="3" placeholder="<?= __('admin_declare_winning_number_ph') ?>" class="w-full text-center text-3xl sm:text-4xl tracking-widest font-bold py-4 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none" required autocomplete="off" oninput="updateMultiplier()">
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2"><?= __('admin_declare_multiplier') ?></label>
                <input type="number" id="multiplier" name="multiplier" value="80" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none bg-gray-50" required>
                <p class="text-xs text-gray-500 mt-1"><?= __('admin_declare_multiplier_help') ?></p>
            </div>
            <button type="button" id="btnDeclare" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 rounded-lg text-lg shadow-lg transition duration-200">
                <?= __('admin_declare_btn') ?>
            </button>
        </form>
    </div>

    <script>
        // ဂဏန်းရိုက်ထည့်သည့်အခါ အဆ (Multiplier) နှင့် Section ကို အလိုအလျောက် ပြောင်းပေးခြင်း
        function updateMultiplier() {
            var num = document.getElementById('winning_number').value;
            var mult = document.getElementById('multiplier');
            var sec = document.getElementById('bet_section');
            if (num.length === 3) {
                mult.value = 500;
                sec.value = '3d';
            } else {
                mult.value = 80;
                if(sec.value === '3d') sec.value = 'morning';
            }
        }

        document.getElementById('btnDeclare').addEventListener('click', function() {
            const form = this.closest('form');
            Swal.fire({
                title: '<?= __('admin_declare_confirm_title') ?>',
                text: "<?= __('admin_declare_confirm_text') ?>",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<?= __('admin_declare_confirm_btn') ?>',
                cancelButtonText: '<?= __('admin_declare_cancel_btn') ?>'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    </script>
</body>
</html>