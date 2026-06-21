<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/lang/language.php';

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = __('csrf_token_mismatch');
    } else {
        $pin1 = $_POST['pin1'] ?? '';
        $pin2 = $_POST['pin2'] ?? '';
        $pin3 = $_POST['pin3'] ?? '';
        $pin4 = $_POST['pin4'] ?? '';
        $pin5 = $_POST['pin5'] ?? '';
        $pin6 = $_POST['pin6'] ?? '';

        $pin = $pin1 . $pin2 . $pin3 . $pin4 . $pin5 . $pin6;

        if (strlen($pin) !== 6 || !is_numeric($pin)) {
            $error_message = __('pin_6_digits_required');
        } else {
            $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET transaction_pin = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_pin, $user_id);
            
            if ($stmt->execute()) {
                $success_message = __('pin_set_success');
                // Optionally redirect after success
                // header("refresh:2;url=profile.php");
            } else {
                $error_message = __('system_error_try_again');
            }
            $stmt->close();
        }
    }
}

// Check if user already has a PIN
$stmt = $conn->prepare("SELECT transaction_pin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$has_pin = !empty($res['transaction_pin']);
$stmt->close();
?>

<?php 
$page_title = __('title_setup_pin');
require_once __DIR__ . '/includes/header.php'; 
?>
<audio id="clickSound" src="assets/sounds/click.mp3" preload="auto"></audio>

<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4 md:p-8 transition-colors duration-300">

    <div class="bg-white p-8 md:p-12 rounded-2xl md:rounded-3xl shadow-xl border border-gray-100 w-full max-w-sm md:max-w-md transition-all duration-300">
        
        <div class="text-center mb-6 md:mb-8">
            <div class="w-16 h-16 md:w-20 md:h-20 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 md:mb-5 shadow-sm border border-blue-100">
                <i class="fas fa-shield-alt text-3xl md:text-4xl"></i>
            </div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 tracking-wide"><?= $has_pin ? __('change_pin') : __('title_setup_pin') ?></h2>
            <p class="text-sm md:text-base text-gray-500 mt-2 md:mt-3 font-medium"><?= __('setup_pin_page_desc') ?></p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-4 md:py-5 rounded-xl relative mb-5 text-sm md:text-base text-center font-bold shadow-sm">
                <i class="fas fa-check-circle text-green-500 text-2xl md:text-3xl mb-2 block"></i>
                <?= htmlspecialchars($success_message) ?>
                <div class="mt-4">
                    <a href="profile.php" class="inline-block bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors"><?= __('back_to_profile') ?></a>
                </div>
            </div>
        <?php else: ?>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 md:py-4 rounded-xl relative mb-6 text-sm md:text-base text-center shadow-sm font-medium">
                    <i class="fas fa-exclamation-circle mr-1.5"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6 md:space-y-8">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="flex justify-center gap-2 md:gap-3" id="pin-inputs">
                    <?php for($i=1; $i<=6; $i++): ?>
                        <input type="password" name="pin<?= $i ?>" maxlength="1" pattern="[0-9]" inputmode="numeric" 
                               class="w-10 h-12 md:w-14 md:h-16 text-center text-xl md:text-3xl font-bold border-2 border-gray-200 rounded-lg md:rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none transition-all shadow-sm bg-gray-50 focus:bg-white text-primary" required
                               onkeyup="moveToNext(this, <?= $i ?>)" oninput="playClickSound()">
                    <?php endfor; ?>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3.5 md:py-4 rounded-xl text-lg md:text-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
                        <i class="fas fa-check-circle mr-1.5"></i> <?= __('confirm') ?>
                    </button>
                </div>

                <?php if ($has_pin): ?>
                    <div class="mt-4 text-center">
                        <a href="forgot_pin.php" class="inline-flex items-center text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-4 py-2 rounded-lg text-sm font-bold transition-colors">
                            <i class="fas fa-question-circle mr-2"></i> <?= __('forgot_pin') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </form>
            
            <div class="mt-6 md:mt-8 text-center border-t border-gray-100 pt-5">
                <a href="profile.php" class="text-gray-500 hover:text-primary text-sm md:text-base font-medium flex items-center justify-center transition-colors">
                    <i class="fas fa-arrow-left mr-1.5"></i> <?= __('back') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function playClickSound() {
            const snd = document.getElementById('clickSound');
            if (snd) {
                snd.currentTime = 0; // အသံကို အစမှ ပြန်စမည် (မြန်မြန်ရိုက်လျှင်လည်း အသံထွက်စေရန်)
                snd.play().catch(e => {});
            }
        }

        function moveToNext(current, index) {
            // Allow only numbers
            current.value = current.value.replace(/[^0-9]/g, '');

            if (current.value.length === 1) {
                let next = document.querySelector(`input[name=pin${index + 1}]`);
                if (next) {
                    next.focus();
                }
            } else if (current.value.length === 0 && event.key === "Backspace") {
                let prev = document.querySelector(`input[name=pin${index - 1}]`);
                if (prev) {
                    prev.focus();
                }
            }
        }
    </script>
</body>
</html>