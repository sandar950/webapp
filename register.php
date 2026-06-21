<?php
session_start();

// Login ဝင်ပြီးသား User ဖြစ်နေပါက index.php သို့ တန်းသွားစေရန်
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/lang/language.php';
require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/core/security_helper.php';

$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $referral_code_input = trim($_POST['referral_code'] ?? '');
    $referred_by = null;
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // --- Anti-Spam / Rate Limit (IP ဖြင့် စစ်ဆေးခြင်း) ---
    $check_reg_table = $conn->query("SHOW TABLES LIKE 'registration_attempts'");
    if ($check_reg_table->num_rows == 0) {
        $conn->query("CREATE TABLE registration_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(ip_address)
        )");
    }

    $time_limit = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $spam_stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM registration_attempts WHERE ip_address = ? AND created_at > ?");
    $spam_stmt->bind_param("ss", $ip_address, $time_limit);
    $spam_stmt->execute();
    $spam_result = $spam_stmt->get_result()->fetch_assoc();
    $spam_stmt->close();

    if ($spam_result['attempts'] >= 5) {
        $error_message = __('spam_registration_error');
    } elseif (empty($username) || empty($phone) || empty($password)) {
        $error_message = __('register_empty_fields');
    } elseif (!preg_match('/^[0-9]{9,15}$/', $phone)) {
        $error_message = __('invalid_phone_format');
    } elseif (strlen($password) < 6) {
        $error_message = __('password_length_error');
    } else {
        // ဖုန်းနံပါတ် ရှိ/မရှိ စစ်ဆေးခြင်း
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error_message = __('phone_already_registered');
        } else {
            // Referral Code စစ်ဆေးခြင်း
            if (!empty($referral_code_input)) {
                $ref_stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
                $ref_stmt->bind_param("s", $referral_code_input);
                $ref_stmt->execute();
                $ref_result = $ref_stmt->get_result();
                if ($ref_row = $ref_result->fetch_assoc()) {
                    $referred_by = $ref_row['id'];
                } else {
                    $error_message = __('invalid_referral_code');
                }
                $ref_stmt->close();
            }

            if (empty($error_message)) {
                // Generate a unique 6-character referral code for the new user
                $new_referral_code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $insert_stmt = $conn->prepare("INSERT INTO users (username, phone_number, password, referral_code, referred_by, last_login_ip) VALUES (?, ?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("ssssis", $username, $phone, $hashed_password, $new_referral_code, $referred_by, $ip_address);

                if ($insert_stmt->execute()) {
                    // Log the registration attempt
                    $log_stmt = $conn->prepare("INSERT INTO registration_attempts (ip_address) VALUES (?)");
                    $log_stmt->bind_param("s", $ip_address);
                    $log_stmt->execute();
                    $log_stmt->close();

                    $success_message = __('register_success');
                } else {
                    $error_message = __('system_error_try_again');
                }
                $insert_stmt->close();
            }
        }
        $stmt->close();
    }
}
?>

<?php 
$page_title = __('login_page_title');
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4 md:p-8">

    <!-- Decorative Background Circles -->
    <div class="fixed top-[-10%] left-[-10%] w-96 h-96 bg-blue-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob"></div>
    <div class="fixed top-[-10%] right-[-10%] w-96 h-96 bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000"></div>
    <div class="fixed bottom-[-20%] left-[20%] w-96 h-96 bg-pink-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-4000"></div>

    <div class="w-full max-w-md lg:max-w-5xl bg-white rounded-3xl shadow-2xl overflow-hidden relative z-10 animate__animated animate__fadeInUp flex flex-col lg:flex-row">
        
        <!-- Left Side: Branding / Features (Desktop Only) -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary to-blue-900 p-12 flex-col justify-center text-white relative overflow-hidden">
            <div class="absolute top-10 left-10 w-32 h-32 bg-white opacity-5 rounded-full blur-2xl"></div>
            <div class="absolute bottom-10 right-10 w-48 h-48 bg-blue-400 opacity-20 rounded-full blur-3xl"></div>
            
            <div class="relative z-10">
                <div class="w-20 h-20 glass-panel rounded-2xl flex items-center justify-center mb-8 border border-white/20 shadow-lg">
                    <i class="fas fa-rocket text-4xl text-yellow-400"></i>
                </div>
                <h2 class="text-4xl lg:text-5xl font-extrabold mb-6 leading-tight"><?= __('welcome_to_thai2d3d') ?> <br><span class="text-yellow-400"><?= __('welcome_highlight') ?></span></h2>
                <p class="text-blue-100 text-lg mb-8 leading-relaxed opacity-90">
                    <?= __('register_hero_subtitle') ?>
                </p>
                
                <ul class="space-y-5">
                    <li class="flex items-center text-blue-50 text-lg">
                        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center mr-4">
                            <i class="fas fa-shield-alt text-green-400"></i>
                        </div>
                        <?= __('feature_secure_payment') ?>
                    </li>
                    <li class="flex items-center text-blue-50 text-lg">
                        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center mr-4">
                            <i class="fas fa-bolt text-yellow-400"></i>
                        </div>
                        <?= __('feature_realtime_result') ?>
                    </li>
                    <li class="flex items-center text-blue-50 text-lg">
                        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center mr-4">
                            <i class="fas fa-headset text-pink-400"></i>
                        </div>
                        <?= __('feature_24_7_support') ?>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right Side: Registration Form -->
        <div class="w-full lg:w-1/2 flex flex-col justify-center bg-white relative">
            
            <!-- Mobile Header (Hidden on Desktop) -->
            <div class="bg-primary p-6 md:p-8 text-center text-white relative overflow-hidden lg:hidden rounded-b-[2.5rem] shadow-sm">
                <div class="absolute inset-0 bg-[url('assets/images/pattern.png')] opacity-10 mix-blend-overlay"></div>
                <div class="w-16 h-16 mx-auto bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center mb-3 shadow-lg border border-white/30">
                    <i class="fas fa-user-plus text-2xl text-white"></i>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-wider"><?= __('register_title') ?></h1>
                <p class="text-blue-100 text-sm md:text-base mt-1.5 opacity-90"><?= __('register_subtitle') ?></p>
            </div>

            <!-- Form Section -->
            <div class="p-6 md:p-8 lg:p-12 lg:px-14">
                
                <!-- Desktop Form Title (Hidden on Mobile) -->
                <div class="hidden lg:block mb-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-50 text-primary rounded-full mb-4 shadow-sm border border-blue-100">
                        <i class="fas fa-user-plus text-2xl"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-800 mb-2"><?= __('register_title') ?></h3>
                    <p class="text-gray-500"><?= __('register_subtitle') ?></p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r-lg mb-6 text-sm md:text-base font-bold flex items-center shadow-sm animate__animated animate__shakeX">
                        <i class="fas fa-exclamation-circle text-lg md:text-xl mr-3"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-r-lg mb-6 text-sm md:text-base font-bold flex items-center shadow-sm">
                        <i class="fas fa-check-circle text-lg md:text-xl mr-3"></i> <?= htmlspecialchars($success_message) ?>
                    </div>
                    <script>
                        setTimeout(function() { window.location.href = "login.php"; }, 2000);
                    </script>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm" class="space-y-5 md:space-y-6">
                    <div>
                        <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 ml-1"><i class="fas fa-user text-primary w-5 text-center mr-1"></i> <?= __('username_label') ?></label>
                        <input type="text" id="username" name="username" class="w-full px-4 md:px-5 py-3 md:py-4 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm md:text-base transition-all bg-gray-50 focus:bg-white" placeholder="<?= __('username_placeholder') ?>" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 ml-1"><i class="fas fa-phone-alt text-primary w-5 text-center mr-1"></i> <?= __('phone_label') ?></label>
                        <input type="tel" id="phone" name="phone" class="w-full px-4 md:px-5 py-3 md:py-4 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm md:text-base transition-all bg-gray-50 focus:bg-white font-mono" placeholder="09xxxxxxxxx" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 ml-1"><i class="fas fa-lock text-primary w-5 text-center mr-1"></i> <?= __('password_label') ?></label>
                        <div class="relative">
                            <input type="password" id="password" name="password" class="w-full px-4 md:px-5 py-3 md:py-4 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm md:text-base transition-all bg-gray-50 focus:bg-white" placeholder="<?= __('password_placeholder') ?>" required>
                            <span class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-gray-400 hover:text-gray-600 transition-colors" onclick="togglePasswordVisibility('password', 'eyeIcon1')">
                                <i class="fas fa-eye" id="eyeIcon1"></i>
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 ml-1"><i class="fas fa-info-circle mr-1"></i> <?= __('password_help') ?></p>
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm md:text-base font-bold mb-2 ml-1"><i class="fas fa-user-friends text-primary w-5 text-center mr-1"></i> <?= __('referral_code_label') ?> <span class="text-xs md:text-sm font-normal text-gray-400">(<?= __('optional') ?>)</span></label>
                        <input type="text" id="referral_code" name="referral_code" class="w-full px-4 md:px-5 py-3 md:py-4 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm md:text-base transition-all bg-gray-50 focus:bg-white font-mono uppercase tracking-wider" placeholder="<?= __('referral_code_placeholder') ?>" value="<?= htmlspecialchars($_GET['ref'] ?? $_POST['referral_code'] ?? '') ?>">
                    </div>

                    <button type="button" onclick="validateForm()" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3.5 md:py-4 rounded-xl text-base md:text-lg shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 mt-4">
                        <?= __('register_btn') ?>
                    </button>
                </form>

                <div class="mt-8 text-center border-t border-gray-100 pt-6">
                    <p class="text-sm md:text-base text-gray-600">
                        <?= __('already_have_account') ?> <a href="login.php" class="font-bold text-blue-600 hover:text-blue-800 hover:underline transition-colors"><?= __('login_link') ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Translations for JavaScript -->
    <script>
        const translations = {
            invalid_phone_title: '<?= addslashes(__('invalid_phone_format')) ?>',
            invalid_phone_text: '<?= addslashes(__('invalid_phone_text')) ?>',
            password_length_title: '<?= addslashes(__('password_length_error')) ?>',
            password_length_text: '<?= addslashes(__('password_help')) ?>',
            checking_title: '<?= addslashes(__('checking')) ?>',
            checking_text: '<?= addslashes(__('checking_phone')) ?>',
            error_title: '<?= addslashes(__('error')) ?>',
            phone_already_registered: '<?= addslashes(__('phone_already_registered')) ?>'
        };

        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        // Form ကို Submit မလုပ်ခင် Client-side မှာ စစ်ဆေးခြင်း
        function validateForm() {
            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('password').value;

            // ဖုန်းနံပါတ် ပုံစံစစ်ဆေးခြင်း (ဂဏန်းချည်းပဲ၊ အနည်းဆုံး ၉ လုံး)
            const phoneRegex = /^[0-9]{9,15}$/;
            if (!phoneRegex.test(phone)) {
                Swal.fire({ icon: 'warning', title: translations.invalid_phone_title, text: translations.invalid_phone_text });
                return;
            }

            // စကားဝှက် အရှည်စစ်ဆေးခြင်း
            if (password.length < 6) {
                Swal.fire({ icon: 'warning', title: translations.password_length_title, text: translations.password_length_text });
                return;
            }

            // --- SweetAlert2 Loading Spinner စတင်ပြသခြင်း ---
            Swal.fire({
                title: translations.checking_title,
                text: translations.checking_text,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading(); // Spinner ကို ပြသခြင်း
                }
            });

            // AJAX ဖြင့် Background တွင် ဖုန်းနံပါတ် စစ်ဆေးခြင်း
            const formData = new FormData();
            formData.append('phone', phone);

            fetch('ajax_check_phone.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    // ဖုန်းနံပါတ် ရှိနှင့်ပြီးသားဖြစ်ပါက Error ပြမည်
                    Swal.fire({
                        icon: 'error',
                        title: translations.error_title,
                        text: translations.phone_already_registered
                    });
                } else {
                    // အဆင်ပြေပါက Spinner ကိုပိတ်ပြီး Form ကို အမှန်တကယ် Submit လုပ်မည်
                    Swal.close();
                    document.getElementById('registerForm').submit();
                }
            })
            .catch(error => {
                // AJAX Error ဖြစ်ခဲ့လျှင် (ဥပမာ Network မကောင်းလျှင်) Form ကို ရိုးရိုးပဲ Submit လိုက်မည်။
                // Backend မှ ဆက်လက်စစ်ဆေးပေးပါမည်။
                Swal.close();
                document.getElementById('registerForm').submit();
            });
        }
    </script>
</body>
</html>
