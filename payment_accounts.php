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
$success_message = "";
$error_message = "";

// Admin မှ ဖွင့်ပေးထားသော (Active ဖြစ်သော) ငွေထုတ်နည်းလမ်းများကို အစီအစဉ်အတိုင်း (sort_order) ဆွဲထုတ်မည်
$methods_stmt = $conn->query("SELECT DISTINCT payment_method, logo_url FROM payment_accounts WHERE is_active = 1 ORDER BY sort_order ASC");
$active_methods = [];
if ($methods_stmt) {
    while ($row = $methods_stmt->fetch_assoc()) {
        $active_methods[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payment_info = [];
    
    // Check if accounts is submitted
    if (isset($_POST['accounts']) && is_array($_POST['accounts'])) {
        foreach ($_POST['accounts'] as $method_base64 => $data) {
            $method_name = base64_decode($method_base64);
            $acc_num = trim($data['number'] ?? '');
            $acc_name = trim($data['name'] ?? '');
            $payment_info[$method_name] = [
                'number' => $acc_num,
                'name' => $acc_name
            ];
        }
    }
    
    // Legacy support အတွက် KBZ နှင့် Wave ကို သီးသန့်ထုတ်ယူမည်
    $kbz_pay = $payment_info['KBZ Pay']['number'] ?? ($payment_info['KPay']['number'] ?? '');
    $kbz_name = $payment_info['KBZ Pay']['name'] ?? ($payment_info['KPay']['name'] ?? '');
    $wave_pay = $payment_info['Wave Pay']['number'] ?? ($payment_info['WavePay']['number'] ?? '');
    $wave_name = $payment_info['Wave Pay']['name'] ?? ($payment_info['WavePay']['name'] ?? '');
    
    $json_data = json_encode($payment_info, JSON_UNESCAPED_UNICODE);

    $stmt = $conn->prepare("UPDATE users SET kbz_pay_number = ?, kbz_pay_name = ?, wave_pay_number = ?, wave_pay_name = ?, payment_info_json = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $kbz_pay, $kbz_name, $wave_pay, $wave_name, $json_data, $user_id);
    
    if ($stmt->execute()) {
        $success_message = __('payment_accounts_saved_success');
    } else {
        $error_message = __('payment_accounts_save_error');
    }
    $stmt->close();
}

// လက်ရှိ User ၏ အချက်အလက်များကို Database မှ ဆွဲထုတ်ခြင်း
$stmt = $conn->prepare("SELECT kbz_pay_number, kbz_pay_name, wave_pay_number, wave_pay_name, payment_info_json FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$saved_info = [];
if (!empty($user_data['payment_info_json'])) {
    $saved_info = json_decode($user_data['payment_info_json'], true);
} else {
    // Legacy Support (ယခင်သိမ်းထားသော ဒေတာများရှိခဲ့လျှင်)
    if (!empty($user_data['kbz_pay_number'])) {
        $saved_info['KBZ Pay'] = ['number' => $user_data['kbz_pay_number'], 'name' => $user_data['kbz_pay_name']];
    }
    if (!empty($user_data['wave_pay_number'])) {
        $saved_info['Wave Pay'] = ['number' => $user_data['wave_pay_number'], 'name' => $user_data['wave_pay_name']];
    }
}
?>

<?php 
$page_title = __('title_payment_accounts') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>
<body class="w-full md:max-w-3xl lg:max-w-4xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24">

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="profile.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center pr-6 md:pr-10 tracking-wide"><?= __('title_payment_accounts') ?></h1>
    </div>

    <div class="p-4 md:p-8 max-w-3xl mx-auto">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 md:py-4 rounded-xl relative mb-5 text-sm md:text-base font-medium shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 md:py-4 rounded-xl relative mb-5 text-sm md:text-base font-medium shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="bg-white p-6 md:p-10 rounded-2xl shadow-md md:shadow-xl border border-gray-100">
            <p class="text-sm md:text-base font-bold text-primary mb-5 md:mb-6 border-b pb-3"><i class="fas fa-wallet mr-1.5 md:text-lg"></i> <?= __('edit_payment_accounts') ?></p>
            
            <?php if (count($active_methods) > 0): ?>
                <?php foreach ($active_methods as $index => $m): 
                    $method_name = $m['payment_method'];
                    $b64_method = base64_encode($method_name);
                    $num_val = $saved_info[$method_name]['number'] ?? '';
                    $name_val = $saved_info[$method_name]['name'] ?? '';
                    $logo = $m['logo_url'] ?? '';
                ?>
                <div class="mb-5 md:mb-8 <?= $index > 0 ? 'border-t border-gray-100 pt-5 md:pt-8' : '' ?> group">
                    <div class="flex items-center gap-2.5 md:gap-3 mb-4">
                        <?php if(!empty($logo)): ?>
                            <img src="<?= htmlspecialchars($logo) ?>" class="w-7 h-7 md:w-10 md:h-10 rounded-full object-cover shadow-sm border border-gray-200 group-hover:scale-110 transition-transform">
                        <?php else: ?>
                            <div class="w-7 h-7 md:w-10 md:h-10 rounded-full bg-blue-100 text-blue-500 flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform"><i class="fas fa-university text-xs md:text-base"></i></div>
                        <?php endif; ?>
                        <h3 class="font-bold text-gray-800 text-sm md:text-lg"><?= htmlspecialchars($method_name) ?></h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                        <div>
                            <label class="block text-gray-700 text-xs md:text-sm font-bold mb-1.5 md:mb-2"><?= htmlspecialchars($method_name) ?> <?= ($_SESSION['lang'] ?? 'mm') == 'en' ? 'Number' : 'ဖုန်းနံပါတ်/အကောင့်နံပါတ်' ?></label>
                            <input type="text" name="accounts[<?= $b64_method ?>][number]" value="<?= htmlspecialchars($num_val) ?>" placeholder="e.g. 09xxxxxxxxx" class="w-full py-2.5 md:py-3.5 px-3 md:px-4 border border-gray-300 rounded-lg md:rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none text-gray-700 text-sm md:text-base transition-all">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs md:text-sm font-bold mb-1.5 md:mb-2"><?= htmlspecialchars($method_name) ?> <?= ($_SESSION['lang'] ?? 'mm') == 'en' ? 'Account Name' : 'အကောင့်ပိုင်ရှင်အမည်' ?></label>
                            <input type="text" name="accounts[<?= $b64_method ?>][name]" value="<?= htmlspecialchars($name_val) ?>" placeholder="e.g. U Mya" class="w-full py-2.5 md:py-3.5 px-3 md:px-4 border border-gray-300 rounded-lg md:rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-100 focus:outline-none text-gray-700 text-sm md:text-base transition-all">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 md:py-12 text-gray-500 italic bg-gray-50 rounded-xl border border-gray-100">
                    <i class="fas fa-wallet text-4xl md:text-5xl mb-3 text-gray-300 block"></i>
                    ငွေထုတ်ရန် နည်းလမ်းများ မရှိသေးပါ။
                </div>
            <?php endif; ?>

            <div class="mt-2 md:mt-4 mb-6 md:mb-8 bg-blue-50/50 p-3 md:p-4 rounded-xl border border-blue-100 flex items-start gap-2 md:gap-3">
                <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                <p class="text-[10px] md:text-xs text-gray-600 font-medium leading-relaxed"><?= __('auto_fill_withdraw_notice') ?></p>
            </div>
            
            <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3 md:py-4 rounded-xl md:rounded-2xl text-lg md:text-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
                <i class="fas fa-save mr-1.5"></i> <?= __('save') ?>
            </button>
        </form>
    </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>