<?php
session_start();

// Login ဝင်ထားခြင်း မရှိပါက login.php သို့ ပြန်ပို့ရန်
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/lang/language.php';
$user_id = $_SESSION['user_id'];

// လက်ရှိ User ၏ Referral Code ကို ဆွဲထုတ်ခြင်း
$stmt = $conn->prepare("SELECT referral_code FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ဖိတ်ခေါ်လင့်ခ် နှင့် QR Code URL တည်ဆောက်ခြင်း
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$uri_parts = explode('/', $_SERVER['REQUEST_URI']);
array_pop($uri_parts); // Remove referral.php
$base_url = $protocol . "://" . $host . implode('/', $uri_parts);
$referral_code = $user_data['referral_code'];
$referral_link = $base_url . "/register.php?ref=" . $referral_code;
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($referral_link);
?>

<?php 
$page_title = __('referral_code') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>

<!-- Responsive Main Container -->
<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24">

    <!-- Header -->
    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="profile.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center pr-6 md:pr-10 tracking-wide"><?= __('referral_code') ?></h1>
    </div>

    <div class="p-4 md:p-8 max-w-4xl mx-auto">
        <!-- Desktop layout: Side by side using Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-8">
            
            <!-- QR Code Section -->
            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-md border border-gray-100 flex flex-col items-center justify-center hover:shadow-lg transition-shadow duration-300 h-full">
                <h2 class="text-primary font-bold text-lg md:text-xl mb-4 md:mb-6"><i class="fas fa-qrcode mr-2"></i><?= __('invite_friends_title') ?></h2>
                <div class="bg-white p-3 md:p-4 rounded-2xl shadow-inner border border-gray-100 mb-5 md:mb-6 group cursor-pointer hover:border-blue-300 transition-colors">
                    <img src="<?= htmlspecialchars($qr_code_url) ?>" alt="Referral QR Code" class="w-48 h-48 md:w-56 md:h-56 object-contain group-hover:scale-105 transition-transform duration-300">
                </div>
                <p class="text-sm md:text-base text-gray-600 text-center font-medium"><?= __('scan_qr_instruction') ?></p>
            </div>

            <!-- Referral Code Section -->
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-5 md:p-8 rounded-2xl shadow-md border border-blue-100 flex flex-col justify-center h-full">
                
                <label class="block text-blue-800 text-sm md:text-base font-bold mb-2 md:mb-3"><i class="fas fa-share-alt mr-1.5"></i> <?= __('your_referral_code_label') ?></label>
                <div class="flex mb-5 md:mb-6 shadow-sm rounded-lg md:rounded-xl overflow-hidden">
                    <input type="text" id="myRefCode" value="<?= htmlspecialchars($referral_code) ?>" class="w-full py-3 md:py-4 px-4 border-y border-l border-blue-200 bg-white text-primary font-black tracking-[0.25em] text-center text-lg md:text-2xl focus:outline-none" readonly>
                    <button type="button" onclick="copyRef('myRefCode')" class="bg-primary hover:bg-blue-800 transition-colors text-white px-5 md:px-6 font-bold flex items-center justify-center border-y border-r border-primary"><i class="fas fa-copy md:text-xl"></i></button>
                </div>
                
                <label class="block text-blue-800 text-sm md:text-base font-bold mb-2 md:mb-3"><i class="fas fa-link mr-1.5"></i> <?= __('your_referral_link_label') ?></label>
                <div class="flex shadow-sm rounded-lg md:rounded-xl overflow-hidden">
                    <input type="text" id="myRefLink" value="<?= htmlspecialchars($referral_link) ?>" class="w-full py-2.5 md:py-3.5 px-3 md:px-4 border-y border-l border-blue-200 bg-white text-gray-600 text-xs md:text-sm text-center focus:outline-none font-medium" readonly>
                    <button type="button" onclick="copyRef('myRefLink')" class="bg-primary hover:bg-blue-800 transition-colors text-white px-4 md:px-5 font-bold flex items-center justify-center border-y border-r border-primary"><i class="fas fa-copy md:text-lg"></i></button>
                </div>

                <div class="mt-6 md:mt-8 bg-white/60 p-4 rounded-xl border border-blue-100">
                    <p class="text-xs md:text-sm text-gray-600 text-center font-medium leading-relaxed mb-4">
                        <i class="fas fa-info-circle text-blue-500 mr-1.5"></i> <?= __('referral_commission_info') ?>
                    </p>
                    <div class="text-center border-t border-blue-200/50 pt-4">
                        <a href="commissions_history.php" class="inline-flex items-center justify-center bg-white text-blue-700 hover:bg-blue-50 hover:text-blue-800 text-sm md:text-base font-bold py-2.5 md:py-3 px-5 md:px-6 rounded-xl shadow-sm border border-blue-200 transition-all hover:-translate-y-0.5">
                            <i class="fas fa-history mr-2"></i> <?= __('commission_history_link') ?>
                        </a>
                    </div>
                </div>
            </div>
            
        </div>
        
        <script>
            function copyRef(elementId) {
                var copyText = document.getElementById(elementId);
                copyText.select(); 
                copyText.setSelectionRange(0, 99999);
                
                // Copy to clipboard
                navigator.clipboard.writeText(copyText.value).then(() => {
                    // SweetAlert2 notification instead of basic alert
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '<?= __('success') ?? "အောင်မြင်ပါသည်" ?>',
                            text: '<?= __('copied_successfully_alert') ?>',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            customClass: { popup: 'rounded-2xl' }
                        });
                    } else {
                        alert("<?= __('copied_successfully_alert') ?>");
                    }
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                });
            }
        </script>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>