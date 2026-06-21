<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/core/GoogleAuthenticator.php';
require_once __DIR__ . '/lang/language.php';

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Get current 2FA status
$stmt = $conn->prepare("SELECT password, google2fa_secret FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$is_2fa_enabled = !empty($user['google2fa_secret']);
$ga = new PHPGangsta_GoogleAuthenticator();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['enable_2fa'])) {
        $secret = $_POST['secret'] ?? '';
        $code = $_POST['2fa_code'] ?? '';

        if (!empty($secret) && !empty($code)) {
            $checkResult = $ga->verifyCode($secret, $code, 2); 
            if ($checkResult) {
                $update_stmt = $conn->prepare("UPDATE users SET google2fa_secret = ? WHERE id = ?");
                $update_stmt->bind_param("si", $secret, $user_id);
                if ($update_stmt->execute()) {
                    $is_2fa_enabled = true;
                    $success_message = __('2fa_enabled_success');
                } else {
                    $error_message = __('system_error_try_again');
                }
            } else {
                $error_message = __('2fa_code_invalid');
            }
        }
    } elseif (isset($_POST['disable_2fa'])) {
        $password = $_POST['password'] ?? '';
        if (empty($password)) {
            $error_message = __('password_required_to_disable_2fa');
        } elseif (password_verify($password, $user['password'])) {
            $update_stmt = $conn->prepare("UPDATE users SET google2fa_secret = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            if ($update_stmt->execute()) {
                $is_2fa_enabled = false;
                $success_message = __('2fa_disabled_success');
            } else {
                $error_message = __('system_error_try_again');
            }
        } else {
            $error_message = __('incorrect_password');
        }
    }
}

// Generate new secret for setup
$new_secret = "";
$qrCodeUrl = "";
if (!$is_2fa_enabled) {
    $new_secret = $ga->createSecret();
    $qrCodeUrl = $ga->getQRCodeGoogleUrl('Thai2D3D', $new_secret);
}
?>

<?php 
$page_title = __('2fa_page_title');
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24">
    
    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="profile.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center pr-6 md:pr-10 tracking-wide"><?= __('2fa_header_title') ?></h1>
    </div>

    <div class="p-4 md:p-8 w-full mx-auto">
        
        <?php if (!empty($success_message)): ?>
            <div class="max-w-3xl mx-auto bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl relative mb-5 text-sm md:text-base font-bold shadow-sm flex items-center" role="alert">
                <i class="fas fa-check-circle text-green-500 text-xl md:text-2xl mr-3"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="max-w-3xl mx-auto bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl relative mb-5 text-sm md:text-base font-medium shadow-sm flex items-center" role="alert">
                <i class="fas fa-exclamation-circle text-red-500 text-xl md:text-2xl mr-3"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($is_2fa_enabled): ?>
            <div class="bg-white rounded-2xl md:rounded-3xl shadow-lg border border-gray-100 p-6 md:p-10 max-w-md mx-auto text-center transition-all">
                <div class="w-16 h-16 md:w-20 md:h-20 bg-green-50 border border-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4 md:mb-6 shadow-sm">
                    <i class="fas fa-shield-check text-3xl md:text-4xl"></i>
                </div>
                <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-2 md:mb-3 tracking-wide"><?= __('2fa_status_enabled') ?></h2>
                <p class="text-gray-500 text-sm md:text-base mb-6 md:mb-8 font-medium"><?= __('2fa_status_enabled_desc') ?></p>
                
                <form method="POST" action="" onsubmit="return confirm('<?= __('2fa_confirm_disable') ?>');" class="space-y-5 md:space-y-6">
                    <div>
                        <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 text-left" for="password"><?= __('enter_password_to_disable') ?></label>
                        <input class="w-full py-3 md:py-4 px-4 border border-gray-300 rounded-xl focus:border-red-500 focus:ring focus:ring-red-100 focus:outline-none text-gray-700 transition-all text-sm md:text-base" id="password" name="password" type="password" placeholder="••••••••" required>
                    </div>
                    <button type="submit" name="disable_2fa" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3.5 md:py-4 px-4 rounded-xl text-lg md:text-xl shadow-md hover:shadow-lg transition-all duration-300 hover:-translate-y-0.5">
                        <i class="fas fa-times-circle mr-1.5"></i> <?= __('2fa_disable_button') ?>
                    </button>
                </form>
            </div>
            
        <?php else: ?>
            <div class="bg-white rounded-2xl md:rounded-3xl shadow-lg border border-gray-100 p-6 md:p-10 max-w-3xl mx-auto transition-all">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-10 items-center">
                    
                    <div class="flex flex-col justify-center items-center bg-gray-50 p-6 md:p-8 rounded-2xl border border-gray-200 h-full group">
                        <div class="bg-white p-3 rounded-2xl shadow-sm border border-gray-100 group-hover:border-blue-300 transition-colors">
                            <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="QR Code" class="w-48 h-48 md:w-56 md:h-56 object-contain group-hover:scale-105 transition-transform duration-300">
                        </div>
                        <div class="mt-5 text-center w-full">
                            <p class="text-xs md:text-sm text-gray-500 mb-2 font-bold uppercase tracking-wider"><?= __('2fa_setup_key') ?></p>
                            <div class="bg-white border border-gray-200 px-3 md:px-4 py-2 md:py-2.5 rounded-lg text-sm md:text-base font-bold text-gray-800 tracking-[0.15em] font-mono shadow-inner overflow-hidden text-ellipsis whitespace-nowrap" title="<?= htmlspecialchars($new_secret) ?>">
                                <?= htmlspecialchars($new_secret) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col justify-center h-full">
                        <div class="mb-6 md:mb-8 text-center md:text-left">
                            <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-3 flex items-center justify-center md:justify-start">
                                <i class="fas fa-shield-alt text-blue-500 mr-2 md:mr-3"></i> <?= __('2fa_enable_title') ?>
                            </h2>
                            <p class="text-gray-600 text-sm md:text-base leading-relaxed font-medium">
                                <?= __('2fa_enable_desc') ?>
                            </p>
                        </div>
                        
                        <form method="POST" action="" class="w-full">
                            <input type="hidden" name="secret" value="<?= htmlspecialchars($new_secret) ?>">
                            <div class="mb-6 md:mb-8">
                                <label class="block text-gray-700 text-sm md:text-base font-bold mb-3 md:mb-4 text-center md:text-left" for="2fa_code"><?= __('2fa_enter_code_label') ?></label>
                                <input class="w-full py-3.5 md:py-4 px-4 border border-blue-200 bg-blue-50/30 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none focus:bg-white text-center tracking-[0.5em] font-mono text-xl md:text-2xl font-bold transition-all shadow-inner text-primary" id="2fa_code" name="2fa_code" type="text" placeholder="------" maxlength="6" required autocomplete="off">
                            </div>
                            <button type="submit" name="enable_2fa" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3.5 md:py-4 px-4 rounded-xl text-lg md:text-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-0.5">
                                <i class="fas fa-check-circle mr-1.5"></i> <?= __('2fa_enable_button') ?>
                            </button>
                        </form>
                    </div>

                </div> </div>
        <?php endif; ?>
        
    </div>

</body>
</html>