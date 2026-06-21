<?php
session_start();

// Login ဝင်ထားခြင်း မရှိပါက login.php သို့ ပြန်ပို့ရန်
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/lang/language.php';

// Loans များကို သိမ်းဆည်းရန် Database Table မရှိသေးပါက အလိုအလျောက် တည်ဆောက်မည်
$check_table = $conn->query("SHOW TABLES LIKE 'loans'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE loans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'repaid') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// လက်ရှိ User တွင် ဆိုင်းငံ့/ပြန်ဆပ်ရန်ကျန်သော ချေးငွေ ရှိ/မရှိ စစ်ဆေးခြင်း
$stmt = $conn->prepare("SELECT id, amount, status FROM loans WHERE user_id = ? AND status IN ('pending', 'approved')");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_loan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_loan'])) {
    $amount = floatval($_POST['amount'] ?? 0);

    if ($active_loan) {
        $error_message = __('loan_pending_error');
    } elseif ($amount < 1000) {
        $error_message = __('loan_min_amount_error');
    } else {
        $stmt = $conn->prepare("INSERT INTO loans (user_id, amount, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("id", $user_id, $amount);
        if ($stmt->execute()) {
            $success_message = str_replace('%amount%', number_format($amount), __('loan_request_success'));
            $active_loan = ['amount' => $amount, 'status' => 'pending'];
        } else {
            $error_message = __('system_error_try_again');
        }
        $stmt->close();
    }
}

// ချေးငွေမှတ်တမ်းများ
$history_stmt = $conn->prepare("SELECT amount, status, created_at FROM loans WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();
?>

<?php 
$page_title = __('title_loan') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="max-w-md mx-auto min-h-screen bg-gray-100 shadow-xl pb-6">

    <div class="bg-primary text-white flex items-center p-4 sticky top-0 z-10 shadow-md">
        <a href="index.php" class="mr-4 text-xl w-6"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl font-bold flex-1 text-center"><?= __('title_loan') ?></h1>
        <div class="w-6"></div>
    </div>

    <div class="p-4">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 text-sm font-bold shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm font-bold shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if ($active_loan): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded shadow-sm">
                <h3 class="font-bold text-yellow-800 mb-2"><i class="fas fa-exclamation-circle mr-1"></i> <?= __('notice') ?></h3>
                <p class="text-sm text-gray-700 mb-1"><?= str_replace('%amount%', number_format($active_loan['amount']), __('current_loan_amount')) ?></p>
                <p class="text-sm text-gray-700"><?= __('status') ?> 
                    <?php if ($active_loan['status'] == 'pending'): ?>
                        <span class="text-yellow-600 font-bold"><?= __('status_pending_full') ?></span>
                    <?php else: ?>
                        <span class="text-green-600 font-bold"><?= __('status_approved_full') ?></span>
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <form method="POST" action="" class="bg-white p-6 rounded-xl shadow-md border-t-4 border-blue-500 mb-6">
                <div class="text-center mb-6">
                    <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-2"><i class="fas fa-hand-holding-usd text-2xl"></i></div>
                    <p class="text-gray-600 text-sm font-bold"><?= __('request_loan_from_admin') ?></p>
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2"><?= __('loan_amount_ks') ?></label>
                    <input type="number" name="amount" min="1000" placeholder="<?= __('min_loan_placeholder') ?>" class="w-full py-3 px-4 border rounded-lg focus:border-blue-500 focus:outline-none" required>
                </div>
                <button type="submit" name="request_loan" id="btnLoan" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg text-lg shadow-lg transition duration-200">
                    <i class="fas fa-paper-plane mr-1"></i> <?= __('request_btn') ?>
                </button>
            </form>
        <?php endif; ?>

        <h2 class="font-bold text-gray-700 mb-3 text-sm border-b pb-2"><?= __('loan_history') ?></h2>
        <?php if (count($history) > 0): ?>
            <div class="space-y-3">
                <?php foreach ($history as $h): ?>
                    <div class="bg-white p-4 rounded shadow-sm border border-gray-100 flex justify-between items-center">
                        <div>
                            <p class="font-bold text-gray-800"><?= number_format($h['amount']) ?> Ks</p>
                            <p class="text-[10px] text-gray-400 mt-1"><i class="far fa-clock mr-1"></i><?= date('d-M-Y h:i A', strtotime($h['created_at'])) ?></p>
                        </div>
                        <div>
                            <?php if ($h['status'] == 'approved'): ?>
                                <span class="bg-green-100 text-green-700 text-[10px] px-2 py-1 rounded border border-green-300"><?= __('approved') ?></span>
                            <?php elseif ($h['status'] == 'pending'): ?>
                                <span class="bg-yellow-100 text-yellow-700 text-[10px] px-2 py-1 rounded border border-yellow-300"><?= __('pending') ?></span>
                            <?php elseif ($h['status'] == 'repaid'): ?>
                                <span class="bg-blue-100 text-blue-700 text-[10px] px-2 py-1 rounded border border-blue-300"><?= __('repaid') ?></span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-700 text-[10px] px-2 py-1 rounded border border-red-300"><?= __('rejected') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-500 text-sm py-4 italic"><?= __('no_records_found') ?></p>
        <?php endif; ?>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
const btnLoan = document.getElementById('btnLoan');
if (btnLoan) {
btnLoan.addEventListener('click', function(e) {
    e.preventDefault();
    const form = this.closest('form');
    Swal.fire({
        title: '<?= __('confirm_loan_request_title') ?>',
        text: "<?= __('confirm_loan_request_text') ?>",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb', // blue-600
        cancelButtonColor: '#6b7280',  // gray-500
        confirmButtonText: '<?= __('confirm_yes') ?>',
        cancelButtonText: '<?= __('cancel') ?>'
    }).then((result) => {
        if (result.isConfirmed) {
            // Add a hidden input to simulate the button click so PHP knows it was requested
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'request_loan';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        }
    });
});
}
</script>
</body>
</html>