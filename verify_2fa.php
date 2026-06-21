<?php
session_start();

// Ensure the user came from the login page
if (!isset($_SESSION['temp_2fa_user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/core/GoogleAuthenticator.php';
require_once __DIR__ . '/lang/language.php';

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = $_POST['2fa_code'] ?? '';

    if (!empty($code)) {
        $user_id = $_SESSION['temp_2fa_user_id'];
        
        $stmt = $conn->prepare("SELECT google2fa_secret FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && !empty($user['google2fa_secret'])) {
            $ga = new PHPGangsta_GoogleAuthenticator();
            $checkResult = $ga->verifyCode($user['google2fa_secret'], $code, 2); // 2 = 2*30sec clock tolerance

            if ($checkResult) {
                // Clear failed login attempts for this user now that 2FA is successful
                clear_failed_logins($_SERVER['REMOTE_ADDR'], $_SESSION['temp_2fa_phone']);

                // Success, set final session variables
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['user_id'] = $_SESSION['temp_2fa_user_id'];
                $_SESSION['username'] = $_SESSION['temp_2fa_username'];
                $_SESSION['role'] = $_SESSION['temp_2fa_role'];
                $_SESSION['login_time'] = time();
                
                // Clear temp session
                unset($_SESSION['temp_2fa_user_id'], $_SESSION['temp_2fa_username'], $_SESSION['temp_2fa_role'], $_SESSION['temp_2fa_phone']);
                unset($_SESSION['permissions']);
                
                header("Location: index.php");
                exit();
            } else {
                $error_message = __('2fa_code_invalid');
            }
        } else {
             $error_message = __('2fa_not_enabled');
        }
    } else {
        $error_message = __('2fa_enter_code_prompt');
    }
}
?>

<?php 
$page_title = __('verify_2fa_page_title');
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="max-w-md mx-auto relative min-h-screen bg-gray-100 shadow-xl flex items-center justify-center p-4">

    <div class="bg-white w-full rounded-2xl shadow-lg p-6">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-500 text-white rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-shield-alt text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800"><?= __('verify_2fa_title') ?></h1>
            <p class="text-gray-500 text-sm mt-2"><?= __('verify_2fa_subtitle') ?></p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="2fa_code"><?= __('6_digit_code_label') ?></label>
                <input class="shadow appearance-none border rounded w-full py-3 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 text-center text-xl tracking-widest font-mono" id="2fa_code" name="2fa_code" type="text" placeholder="------" maxlength="6" pattern="\d{6}" required autocomplete="off">
            </div>
            <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded w-full focus:outline-none focus:shadow-outline transition duration-200" type="submit">
                <?= __('confirm_button') ?>
            </button>
            <div class="text-center mt-4">
                <a href="login.php" class="text-gray-500 text-sm hover:underline"><?= __('back_to_login') ?></a>
            </div>
        </form>
    </div>

</body>
</html>