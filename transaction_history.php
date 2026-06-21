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

// URL မှတစ်ဆင့် deposit လား withdrawal လား ဆိုတာ ရယူမည် (Default အနေဖြင့် deposit ပြမည်)
$type = $_GET['type'] ?? 'deposit'; 

// Pagination Setup
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get Total Rows
$total_rows = 0;
if ($type == 'transfer') {
    $count_stmt = $conn->prepare("SELECT COUNT(id) as total_rows FROM transfers WHERE sender_id = ? OR receiver_id = ?");
    $count_stmt->bind_param("ii", $user_id, $user_id);
    $count_stmt->execute();
    $total_rows = $count_stmt->get_result()->fetch_assoc()['total_rows'] ?? 0;
    $count_stmt->close();
} else {
    $table_name = ($type == 'deposit') ? 'deposits' : 'withdrawals';
    $count_stmt = $conn->prepare("SELECT COUNT(id) as total_rows FROM $table_name WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $total_rows = $count_stmt->get_result()->fetch_assoc()['total_rows'] ?? 0;
    $count_stmt->close();
}

$total_pages = ceil($total_rows / $limit);

$records = [];
if ($type == 'deposit') {
    $stmt = $conn->prepare("SELECT amount, payment_method, transaction_id as ref_no, status, reject_reason, created_at FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT ?, ?");
    $stmt->bind_param("iii", $user_id, $offset, $limit);
} elseif ($type == 'withdrawal') {
    $stmt = $conn->prepare("SELECT amount, fee_amount, payment_method, account_number as ref_no, status, reject_reason, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT ?, ?");
    $stmt->bind_param("iii", $user_id, $offset, $limit);
} else {
    $stmt = $conn->prepare("
        SELECT t.amount, t.created_at, u.username as other_user, u.phone_number as other_phone, t.id as ref_no, 
               IF(t.sender_id = ?, 'sent', 'received') as transfer_type, 'approved' as status
        FROM transfers t 
        LEFT JOIN users u ON (IF(t.sender_id = ?, t.receiver_id, t.sender_id) = u.id)
        WHERE t.sender_id = ? OR t.receiver_id = ? 
        ORDER BY t.created_at DESC LIMIT ?, ?
    ");
    $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $offset, $limit);
}

$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php 
$page_title = __('tx_history') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24">

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="profile.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center pr-6 md:pr-10 tracking-wide"><?= __('tx_history') ?></h1>
    </div>

    <div class="bg-white flex justify-around border-b text-sm md:text-base font-bold text-gray-500 shadow-sm mb-4 md:mb-6 rounded-b-xl md:rounded-none">
        <a href="?type=deposit" class="py-4 md:py-5 w-1/3 text-center transition-colors duration-300 <?= $type == 'deposit' ? 'text-primary border-b-2 border-primary bg-blue-50/50' : 'hover:text-primary hover:bg-gray-50' ?>">
            <i class="fas fa-arrow-down mr-1 md:mr-2"></i> <?= __('tx_deposit') ?>
        </a>
        <a href="?type=withdrawal" class="py-4 md:py-5 w-1/3 text-center transition-colors duration-300 <?= $type == 'withdrawal' ? 'text-red-500 border-b-2 border-red-500 bg-red-50/50' : 'hover:text-red-500 hover:bg-gray-50' ?>">
            <i class="fas fa-arrow-up mr-1 md:mr-2"></i> <?= __('tx_withdraw') ?>
        </a>
        <a href="?type=transfer" class="py-4 md:py-5 w-1/3 text-center transition-colors duration-300 <?= $type == 'transfer' ? 'text-purple-600 border-b-2 border-purple-600 bg-purple-50/50' : 'hover:text-purple-600 hover:bg-gray-50' ?>">
            <i class="fas fa-exchange-alt mr-1 md:mr-2"></i> <?= __('transfer') ?>
        </a>
    </div>

    <div class="p-4 md:p-8 pt-0 md:pt-0 max-w-4xl mx-auto">
        <?php if (count($records) > 0): ?>
            <div class="space-y-3 md:space-y-4">
                <?php foreach ($records as $rec): ?>
                    <?php
                    // Responsive Status HTML Badges Update
                    if ($type == 'transfer') {
                        $is_sent = ($rec['transfer_type'] == 'sent');
                        $title = $is_sent ? __('tx_sent') : __('tx_received');
                        $ref_text = ($is_sent ? __('to_label') : __('from_label')) . htmlspecialchars($rec['other_user']) . ' (' . htmlspecialchars($rec['other_phone']) . ')';
                        $sign = $is_sent ? '-' : '+';
                        $color = $is_sent ? 'text-red-600' : 'text-green-600';
                        $status_html = '<span class="bg-green-100 text-green-700 text-[10px] md:text-xs px-2 md:px-3 py-0.5 md:py-1 rounded-md border border-green-300 font-medium">' . __('status_success') . '</span>';
                    } else {
                        $title = ($type == 'deposit') ? __('tx_deposit') : __('tx_withdraw');
                        $title .= ' (' . htmlspecialchars($rec['payment_method']) . ')';
                        $ref_text = ($type == 'deposit' ? __('trx_id_label') : __('acc_no_label')) . '<span class="font-mono text-blue-600 ml-1">' . htmlspecialchars($rec['ref_no']) . '</span>';
                        $sign = ($type == 'deposit') ? '+' : '-';
                        $color = ($type == 'deposit') ? 'text-green-600' : 'text-red-600';
                        if ($rec['status'] == 'approved') {
                            $status_html = '<span class="bg-green-100 text-green-700 text-[10px] md:text-xs px-2 md:px-3 py-0.5 md:py-1 rounded-md border border-green-300 font-medium">' . __('status_success') . '</span>';
                        } elseif ($rec['status'] == 'pending') {
                            $status_html = '<span class="bg-yellow-100 text-yellow-700 text-[10px] md:text-xs px-2 md:px-3 py-0.5 md:py-1 rounded-md border border-yellow-300 font-medium">' . __('status_pending') . '</span>';
                        } else {
                            $status_html = '<span class="bg-red-100 text-red-700 text-[10px] md:text-xs px-2 md:px-3 py-0.5 md:py-1 rounded-md border border-red-300 font-medium">' . __('rejected') . '</span>';
                            if (($type == 'withdrawal' || $type == 'deposit') && !empty($rec['reject_reason'])) {
                                $status_html .= "<span class='text-[10px] md:text-xs text-red-500 font-normal mt-1.5 block leading-tight'>" . __('reason') . " " . htmlspecialchars($rec['reject_reason']) . "</span>";
                            }
                        }
                    }

                    $amount_display = number_format($rec['amount']);
                    ?>
                    <div class="bg-white p-4 md:p-6 rounded-xl md:rounded-2xl shadow-sm border border-gray-100 flex justify-between items-center hover:shadow-md transition-all duration-300 group">
                        <div class="flex-1 pr-4">
                            <p class="text-gray-800 text-sm md:text-lg font-bold mb-1 md:mb-1.5 group-hover:text-primary transition-colors">
                                <?= $title ?>
                            </p>
                            <p class="text-xs md:text-sm text-gray-500 mb-1 md:mb-2">
                                <?= $ref_text ?>
                            </p>
                            <p class="text-[10px] md:text-xs text-gray-400 font-medium"><i class="far fa-clock mr-1"></i> <?= date('d-M-Y h:i A', strtotime($rec['created_at'])) ?></p>
                        </div>
                        <div class="text-right whitespace-nowrap">
                            <p class="font-bold text-base md:text-xl mb-1.5 <?= $color ?>">
                                <?= $sign ?> <?= $amount_display ?>
                            </p>
                            <?= $status_html ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl md:rounded-2xl shadow-md p-10 md:p-16 text-center mt-4 md:mt-8 max-w-2xl mx-auto">
                <i class="fas fa-file-invoice-dollar text-5xl md:text-7xl text-gray-200 mb-4 md:mb-6 block animate-pulse"></i>
                <p class="text-gray-500 text-sm md:text-lg font-medium mt-3"><?= __('no_records_found') ?></p>
            </div>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center items-center mt-8 md:mt-10 mb-4 space-x-2 md:space-x-3">
                <?php if ($page > 1): ?>
                    <a href="?type=<?= htmlspecialchars($type) ?>&page=<?= $page - 1 ?>" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-gray-600 hover:bg-gray-50 hover:text-primary hover:border-primary shadow-sm transition-all duration-200"><i class="fas fa-chevron-left text-xs md:text-sm"></i></a>
                <?php endif; ?>
                
                <span class="px-4 md:px-6 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-sm md:text-base font-bold text-gray-700 shadow-sm"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?type=<?= htmlspecialchars($type) ?>&page=<?= $page + 1 ?>" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-gray-600 hover:bg-gray-50 hover:text-primary hover:border-primary shadow-sm transition-all duration-200"><i class="fas fa-chevron-right text-xs md:text-sm"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>