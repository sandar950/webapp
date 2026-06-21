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

// ဘောင်ချာဖျက်သိမ်းခွင့် အချိန် (မိနစ်) ကို ဆွဲထုတ်ခြင်း
$setting_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'bet_cancel_time_limit'");
$setting_row = $setting_stmt->fetch_assoc();
$cancel_limit_minutes = $setting_row ? intval($setting_row['setting_value']) : 10;
$cancel_limit_seconds = $cancel_limit_minutes * 60;

// AJAX: ဘောင်ချာအသေးစိတ်ကို ရယူရန်
if (isset($_GET['action']) && $_GET['action'] === 'get_voucher_details') {
    header('Content-Type: application/json');
    $created_at = $_GET['created_at'] ?? '';

    if (empty($created_at)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit();
    }

    $details_stmt = $conn->prepare("
        SELECT bet_number, amount, discount_amount, odds, status 
        FROM bets 
        WHERE user_id = ? AND created_at = ? 
        ORDER BY bet_number ASC
    ");
    $details_stmt->bind_param("is", $user_id, $created_at);
    $details_stmt->execute();
    $details_result = $details_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $details_stmt->close();

    echo json_encode(['success' => true, 'details' => $details_result]);
    exit();
}


// Cancel Button နှိပ်လိုက်သောအခါ လုပ်ဆောင်မည့် အပိုင်း
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_bet') {
    $voucher_id_to_cancel = $_POST['voucher_id'] ?? '';
    $created_at_to_cancel = $_POST['created_at'] ?? '';

    if (!empty($voucher_id_to_cancel) && !empty($created_at_to_cancel)) {
        $time_diff = time() - strtotime($created_at_to_cancel);
        if ($cancel_limit_seconds > 0 && $time_diff <= $cancel_limit_seconds) { 
            $conn->begin_transaction();
            try {
                // Use voucher_id for more precise targeting
                $voucher_id_like = substr($voucher_id_to_cancel, 0, 8) . '%';
                $stmt = $conn->prepare("SELECT SUM(amount - IFNULL(discount_amount, 0)) as refund_amount, GROUP_CONCAT(id) as bet_ids FROM bets WHERE user_id = ? AND created_at = ? AND status = 'pending' AND MD5(CONCAT(created_at, user_id)) LIKE ? FOR UPDATE");
                $stmt->bind_param("iss", $user_id, $created_at_to_cancel, $voucher_id_like);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $refund_amount = floatval($res['refund_amount']);
                $bet_ids_to_delete = $res['bet_ids'];
                $stmt->close();

                if ($refund_amount > 0) {
                    // မသမာမှုမရှိစေရန် - ဤဘောင်ချာအတွက် ပေးထားခဲ့သော ကော်မရှင်များကိုပါ ပြန်လည်ရုပ်သိမ်းမည် (Reverse Commission)
                    $comm_stmt = $conn->prepare("SELECT id, referrer_id, amount FROM commissions WHERE referred_user_id = ? AND created_at >= ? - INTERVAL 2 SECOND AND created_at <= ? + INTERVAL 2 SECOND FOR UPDATE");
                    $comm_stmt->bind_param("iss", $user_id, $created_at_to_cancel, $created_at_to_cancel);
                    $comm_stmt->execute();
                    $comm_res = $comm_stmt->get_result();
                    $update_user_balance_stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $delete_commission_stmt = $conn->prepare("DELETE FROM commissions WHERE id = ?");

                    while ($comm = $comm_res->fetch_assoc()) {
                        $update_user_balance_stmt->bind_param("di", $comm['amount'], $comm['referrer_id']);
                        $update_user_balance_stmt->execute();
                        
                        $delete_commission_stmt->bind_param("i", $comm['id']);
                        $delete_commission_stmt->execute();
                    }
                    $update_user_balance_stmt->close();
                    $delete_commission_stmt->close();
                    $comm_stmt->close();

                    // User ထံသို့ ထိုးကြေးငွေ ပြန်အမ်းမည်
                    $refund_stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $refund_stmt->bind_param("di", $refund_amount, $user_id);
                    $refund_stmt->execute();
                    $refund_stmt->close();

                    // bets table မှ မှတ်တမ်းများကို ဖျက်သိမ်းမည်
                    if (!empty($bet_ids_to_delete)) {
                        $bet_id_array = array_map('intval', explode(',', $bet_ids_to_delete));
                        $placeholders = implode(',', array_fill(0, count($bet_id_array), '?'));
                        $types = str_repeat('i', count($bet_id_array));
                        
                        $delete_bets_stmt = $conn->prepare("DELETE FROM bets WHERE id IN ($placeholders)");
                        $delete_bets_stmt->bind_param($types, ...$bet_id_array);
                        $delete_bets_stmt->execute();
                        $delete_bets_stmt->close();
                    }

                    $conn->commit();
                    $success_message = sprintf(__('cancel_bet_success'), number_format($refund_amount));
                } else {
                    $conn->rollback();
                    $error_message = __('cancel_bet_no_record');
                }
            } catch(Exception $e) {
                $conn->rollback();
                $error_message = __('system_error_try_again') . " " . $e->getMessage();
            }
        } else {
            if ($cancel_limit_seconds == 0) {
                $error_message = __('cancel_bet_disabled');
            } else {
                $error_message = sprintf(__('cancel_bet_timeout'), $cancel_limit_minutes);
            }
        }
    }
}

// URL မှတစ်ဆင့် Filter လုပ်မည့် အမျိုးအစားကို ရယူခြင်း (Default အနေဖြင့် 'all' ဟု သတ်မှတ်မည်)
$filter = $_GET['filter'] ?? 'all';
$search_number = trim($_GET['search_number'] ?? '');
$search_date = trim($_GET['search_date'] ?? '');

// Pagination Setup
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$filter_sql = "";
$types = "i";
$params = [$user_id];

if ($filter === 'win') {
    $filter_sql .= " AND status = 'win'";
} elseif ($filter === 'pending') {
    $filter_sql .= " AND status = 'pending'";
} elseif ($filter === 'lose') {
    $filter_sql .= " AND status = 'lose'";
}

if (!empty($search_number)) {
    $filter_sql .= " AND bet_number = ?";
    $types .= "s";
    $params[] = $search_number;
}

if (!empty($search_date)) {
    $filter_sql .= " AND DATE(created_at) = ?";
    $types .= "s";
    $params[] = $search_date;
}

// Get Total Rows for Pagination
$count_query = "SELECT COUNT(DISTINCT created_at) as total_rows FROM bets WHERE user_id = ?" . $filter_sql;
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();
$total_rows = $count_res['total_rows'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_rows / $limit);

// Main Query with Pagination
$query = "SELECT 
            created_at, 
            COUNT(id) as total_kwek, 
            SUM(amount - IFNULL(discount_amount, 0)) as total_amount,
            GROUP_CONCAT(bet_number SEPARATOR ', ') as bet_numbers,
            SUM(CASE WHEN status = 'win' THEN 1 ELSE 0 END) as win_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
            , MD5(CONCAT(created_at, user_id)) as voucher_id_hash
          FROM bets WHERE user_id = ?" . $filter_sql . " 
          GROUP BY created_at ORDER BY created_at DESC LIMIT ?, ?";

// Add LIMIT params
$types .= "ii";
$params[] = $offset;
$params[] = $limit;

// လက်ရှိ User ၏ ထိုးမှတ်တမ်းများကို အသစ်မှ အဟောင်းသို့ အစဉ်လိုက်ဆွဲထုတ်ခြင်း
$stmt = $conn->prepare($query);
// ပြောင်းလဲသွားသော parameter အရေအတွက်ပေါ်မူတည်၍ အလိုအလျောက် bind လုပ်ပေးခြင်း
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$bets = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// နေ့စဉ် အရှုံး/အမြတ် အကျဉ်းချုပ် တွက်ချက်ခြင်း
$summary_date = !empty($search_date) ? $search_date : date('Y-m-d');
$summary_stmt = $conn->prepare("
    SELECT 
        COUNT(id) as total_tickets,
        SUM(amount - IFNULL(discount_amount, 0)) as total_bet_amount,
        SUM(CASE WHEN status = 'win' THEN 1 ELSE 0 END) as win_tickets,
        SUM(CASE WHEN status = 'lose' THEN 1 ELSE 0 END) as lose_tickets,
        SUM(CASE WHEN status = 'win' AND LENGTH(bet_number) = 2 THEN amount * IFNULL(odds, 80) 
                 WHEN status = 'win' AND LENGTH(bet_number) = 3 THEN amount * IFNULL(odds, 500) 
                 ELSE 0 END) as total_win
    FROM bets 
    WHERE user_id = ? AND DATE(created_at) = ?
");
$summary_stmt->bind_param("is", $user_id, $summary_date);
$summary_stmt->execute();
$summary_res = $summary_stmt->get_result()->fetch_assoc();
$daily_bet = floatval($summary_res['total_bet_amount'] ?? 0);
$daily_win = floatval($summary_res['total_win'] ?? 0);
$daily_profit = $daily_win - $daily_bet;
$daily_total_tickets = intval($summary_res['total_tickets'] ?? 0);
$daily_win_tickets = intval($summary_res['win_tickets'] ?? 0);
$daily_lose_tickets = intval($summary_res['lose_tickets'] ?? 0);
$summary_stmt->close();
?>

<?php 
$page_title = __('title_bet_history') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24">

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="index.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center pr-6 md:pr-10 tracking-wide"><?= __('title_bet_history') ?></h1>
    </div>

    <div class="max-w-4xl mx-auto md:mt-4">

        <?php if (!empty($success_message)): ?>
            <div class="px-4 mt-4">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 md:py-4 rounded-xl relative text-sm md:text-base font-medium shadow-sm"><?= htmlspecialchars($success_message) ?></div>
            </div>
            <audio id="cancelSuccessSound" src="assets/sounds/notification.mp3" autoplay></audio>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var snd = document.getElementById('cancelSuccessSound');
                    if (snd) {
                        snd.play().catch(e => console.log("Autoplay prevented by browser."));
                    }
                    if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
                });
            </script>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="px-4 mt-4">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 md:py-4 rounded-xl relative text-sm md:text-base font-medium shadow-sm"><?= htmlspecialchars($error_message) ?></div>
            </div>
        <?php endif; ?>

        <div class="px-4 mt-4">
            <div class="bg-white rounded-2xl shadow-md p-4 md:p-6 border-t-4 hover:shadow-lg transition-shadow <?= $daily_profit >= 0 ? 'border-green-500' : 'border-red-500' ?>">
                <h3 class="font-bold text-gray-700 mb-4 md:mb-5 border-b pb-3 text-sm md:text-base flex items-center">
                    <i class="fas fa-chart-pie mr-2 md:mr-3 text-lg <?= $daily_profit >= 0 ? 'text-green-500' : 'text-red-500' ?>"></i> 
                    <?= ($summary_date == date('Y-m-d') ? __('today') : date('d-M-Y', strtotime($summary_date))) . __('daily_summary_suffix') ?>
                </h3>
                <div class="grid grid-cols-3 gap-4 md:gap-8 text-center mb-5 md:mb-6">
                    <div class="bg-blue-50/50 p-2 md:p-4 rounded-xl">
                        <p class="text-xs md:text-sm text-gray-500 font-medium">ထိုးကွက်</p>
                        <p class="font-bold text-blue-600 text-lg md:text-2xl mt-1"><?= number_format($daily_total_tickets) ?></p>
                    </div>
                    <div class="bg-green-50/50 p-2 md:p-4 rounded-xl">
                        <p class="text-xs md:text-sm text-gray-500 font-medium">ပေါက်ကွက်</p>
                        <p class="font-bold text-green-600 text-lg md:text-2xl mt-1"><?= number_format($daily_win_tickets) ?></p>
                    </div>
                    <div class="bg-red-50/50 p-2 md:p-4 rounded-xl">
                        <p class="text-xs md:text-sm text-gray-500 font-medium">ရှုံးကွက်</p>
                        <p class="font-bold text-red-500 text-lg md:text-2xl mt-1"><?= number_format($daily_lose_tickets) ?></p>
                    </div>
                </div>
                
                <div class="bg-gray-50 p-3 md:p-5 rounded-xl border border-gray-200 space-y-2 md:space-y-3 shadow-inner">
                    <div class="flex justify-between items-center">
                        <span class="text-sm md:text-base text-gray-500 font-medium"><?= __('total_bet_amount') ?></span>
                        <span class="text-sm md:text-base font-bold text-red-500">- <?= number_format($daily_bet) ?> <?= __('currency') ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm md:text-base text-gray-500 font-medium"><?= __('total_win_amount') ?></span>
                        <span class="text-sm md:text-base font-bold text-green-600">+ <?= number_format($daily_win) ?> <?= __('currency') ?></span>
                    </div>
                    <div class="flex justify-between items-center border-t border-gray-200 pt-3 md:pt-4 mt-3 md:mt-4">
                        <span class="text-sm md:text-base font-bold text-gray-700 uppercase tracking-wide"><?= __('net_profit_loss') ?></span>
                        <span class="text-base md:text-xl font-bold <?= $daily_profit >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $daily_profit > 0 ? '+' : '' ?><?= number_format($daily_profit) ?> <?= __('currency') ?>
                        </span>
                    </div>
                    <div class="mt-4 md:mt-5 text-center border-t border-gray-100 pt-4 md:pt-5">
                        <a href="export_history.php?date=<?= htmlspecialchars($summary_date) ?>" class="inline-block bg-white border border-gray-200 text-gray-700 hover:bg-gray-100 hover:border-gray-300 text-xs md:text-sm font-bold py-2 md:py-2.5 px-4 md:px-6 rounded-lg shadow-sm transition-all duration-300 hover:-translate-y-0.5">
                            <i class="fas fa-file-excel mr-1.5 text-green-600"></i> <?= __('download_daily_history') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-4 mt-4">
            <div class="bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-100">
                <form method="GET" action="" class="flex flex-col md:flex-row gap-3 md:gap-4 items-end">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    
                    <div class="flex gap-2 w-full md:w-2/3">
                        <div class="w-1/2">
                            <label class="block text-xs font-bold text-gray-500 mb-1 ml-1"><?= __('search_number_placeholder') ?></label>
                            <input type="text" name="search_number" value="<?= htmlspecialchars($search_number) ?>" placeholder="ဥပမာ - 99" maxlength="3" class="w-full px-3 py-2.5 md:py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        </div>
                        <div class="w-1/2">
                            <label class="block text-xs font-bold text-gray-500 mb-1 ml-1">ရက်စွဲ</label>
                            <input type="date" name="search_date" value="<?= htmlspecialchars($search_date) ?>" class="w-full px-3 py-2.5 md:py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all text-gray-700">
                        </div>
                    </div>

                    <div class="flex gap-2 w-full md:w-1/3">
                        <button type="submit" class="w-2/3 bg-primary text-white rounded-xl text-sm md:text-base py-2.5 md:py-3 font-bold shadow-md hover:bg-blue-800 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300">
                            <i class="fas fa-search mr-1.5"></i> <?= __('search') ?>
                        </button>
                        <a href="bet_history.php" class="w-1/3 bg-gray-200 text-gray-700 rounded-xl text-sm md:text-base py-2.5 md:py-3 font-bold shadow-sm hover:bg-gray-300 transition-colors text-center flex justify-center items-center">
                            <i class="fas fa-sync-alt md:hidden"></i><span class="hidden md:inline"><?= __('clear_filter') ?></span>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <?php 
        // Filter Tab များ နှိပ်သည့်အခါ ရှာဖွေထားသော အချက်အလက်များ မပျောက်သွားစေရန်
        $search_params = "";
        if(!empty($search_number)) $search_params .= "&search_number=".urlencode($search_number);
        if(!empty($search_date)) $search_params .= "&search_date=".urlencode($search_date);
        ?>

        <div class="px-4 mt-4">
            <div class="bg-white flex justify-around text-sm md:text-base font-bold text-gray-500 shadow-sm rounded-xl overflow-hidden border border-gray-100">
                <a href="?filter=all<?= $search_params ?>" class="py-3 md:py-4 w-1/4 text-center transition-colors <?= $filter == 'all' ? 'text-primary border-b-2 border-primary bg-blue-50/50' : 'hover:bg-gray-50 hover:text-primary' ?>"><?= __('all') ?></a>
                <a href="?filter=win<?= $search_params ?>" class="py-3 md:py-4 w-1/4 text-center transition-colors <?= $filter == 'win' ? 'text-green-600 border-b-2 border-green-600 bg-green-50/50' : 'hover:bg-gray-50 hover:text-green-600' ?>"><?= __('winning_numbers') ?></a>
                <a href="?filter=pending<?= $search_params ?>" class="py-3 md:py-4 w-1/4 text-center transition-colors <?= $filter == 'pending' ? 'text-yellow-600 border-b-2 border-yellow-600 bg-yellow-50/50' : 'hover:bg-gray-50 hover:text-yellow-600' ?>"><?= __('pending_bets') ?></a>
                <a href="?filter=lose<?= $search_params ?>" class="py-3 md:py-4 w-1/4 text-center transition-colors <?= $filter == 'lose' ? 'text-red-500 border-b-2 border-red-500 bg-red-50/50' : 'hover:bg-gray-50 hover:text-red-500' ?>"><?= __('losing_numbers') ?></a>
            </div>
        </div>

        <div class="p-4">
            <?php if (count($bets) > 0): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden md:bg-transparent md:border-none md:shadow-none md:space-y-3">
                    <?php foreach ($bets as $bet): ?>
                        <?php 
                            // အချိန်နှင့် User ID ကိုပေါင်း၍ တမူထူးခြားသော Voucher ID အတို ဖန်တီးခြင်း
                            $voucher_id = strtoupper(substr($bet['voucher_id_hash'], 0, 8)); 
                        ?>
                        <div id="voucher_<?= $voucher_id ?>" class="border-b border-gray-100 last:border-b-0 p-4 md:p-5 flex justify-between items-center hover:bg-gray-50 bg-white md:rounded-xl md:border md:shadow-sm transition-all duration-200">
                            <div class="flex-1 pr-3">
                                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                    <span class="bg-gray-200 text-gray-700 text-[10px] md:text-xs px-2 md:px-2.5 py-0.5 md:py-1 rounded-md font-mono font-bold tracking-wider">#<?= $voucher_id ?></span>
                                    <p class="text-xs md:text-sm text-gray-500 font-medium"><i class="far fa-clock mr-1"></i> <?= date('d-M-Y h:i A', strtotime($bet['created_at'])) ?></p>
                                    
                                    <div class="flex items-center ml-1 md:ml-2 border-l border-gray-300 pl-2">
                                        <button onclick="showVoucherDetails('<?= $voucher_id ?>', '<?= $bet['created_at'] ?>')" class="text-blue-500 hover:text-blue-700 hover:bg-blue-50 p-1.5 rounded-md transition-colors" title="<?= __('view_details') ?>"><i class="fas fa-eye text-sm md:text-base"></i></button>
                                        <button onclick="downloadVoucher('<?= $voucher_id ?>')" class="text-gray-400 hover:text-blue-700 hover:bg-blue-50 p-1.5 rounded-md transition-colors ml-1 download-btn" title="<?= __('download_voucher') ?>"><i class="fas fa-download text-sm md:text-base"></i></button>
                                    </div>
                                </div>
                                <p class="font-bold text-lg md:text-xl text-gray-800">
                                    <span class="text-primary text-xl md:text-2xl mr-1"><?= htmlspecialchars($bet['total_kwek']) ?></span> <?= __('kwek') ?>
                                </p>
                                <p class="text-xs md:text-sm text-gray-500 mt-1 max-w-[220px] md:max-w-md break-words leading-relaxed font-medium">
                                    [<?= htmlspecialchars($bet['bet_numbers']) ?>]
                                </p>
                            </div>
                            <div class="text-right flex flex-col items-end">
                                <p class="text-xs md:text-sm text-gray-500 mb-1 font-medium"><?= __('total') ?></p>
                                <p class="font-bold text-red-600 text-base md:text-xl mb-2"><?= number_format($bet['total_amount']) ?> <span class="text-xs md:text-sm font-normal"><?= __('currency') ?></span></p>
                                
                                <?php if ($bet['win_count'] > 0): ?>
                                    <span class="bg-green-100 text-green-700 text-[10px] md:text-xs px-2.5 md:px-3 py-1 md:py-1.5 rounded-md border border-green-300 font-bold tracking-wide shadow-sm"><?= __('status_win') ?></span>
                                <?php elseif ($bet['pending_count'] > 0): ?>
                                    <span class="bg-yellow-100 text-yellow-700 text-[10px] md:text-xs px-2.5 md:px-3 py-1 md:py-1.5 rounded-md border border-yellow-300 font-bold tracking-wide shadow-sm"><?= __('status_pending') ?></span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-700 text-[10px] md:text-xs px-2.5 md:px-3 py-1 md:py-1.5 rounded-md border border-red-300 font-bold tracking-wide shadow-sm"><?= __('status_lose') ?></span>
                                <?php endif; ?>
                                
                                <?php // သတ်မှတ်ထားသော မိနစ်အတွင်းရှိသော pending ဘောင်ချာများကိုသာ Cancel ခလုတ်ပြမည် 
                                if ($cancel_limit_seconds > 0 && $bet['pending_count'] > 0 && $bet['win_count'] == 0 && (time() - strtotime($bet['created_at'])) <= $cancel_limit_seconds): ?>
                                    <form method="POST" class="mt-3" onsubmit="confirmCancel(event)">
                                        <input type="hidden" name="action" value="cancel_bet">
                                        <input type="hidden" name="voucher_id" value="<?= $voucher_id ?>">
                                        <input type="hidden" name="created_at" value="<?= $bet['created_at'] ?>">
                                        <button type="submit" class="bg-red-50 text-red-600 hover:bg-red-500 hover:text-white border border-red-200 hover:border-red-500 text-[10px] md:text-xs font-bold px-3 py-1.5 rounded-md shadow-sm transition-all duration-300">
                                            <i class="fas fa-undo mr-1"></i> <?= __('btn_cancel_bet') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-2xl shadow-md p-10 md:p-16 text-center mt-4 border border-gray-100">
                    <div class="text-gray-200 mb-5 animate-pulse">
                        <i class="fas fa-receipt text-7xl md:text-8xl"></i>
                    </div>
                    <p class="text-gray-500 font-bold text-sm md:text-lg mb-6"><?= __('no_records_found') ?></p>
                    <a href="2d_bet.php" class="inline-block bg-primary text-white px-8 md:px-10 py-3 md:py-3.5 rounded-xl text-sm md:text-base font-bold shadow-md hover:bg-blue-800 hover:shadow-lg transition-all hover:-translate-y-0.5">
                        <i class="fas fa-play-circle mr-2"></i> <?= __('go_to_2d_bet') ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center mt-8 md:mt-10 mb-4 space-x-2 md:space-x-3">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&filter=<?= htmlspecialchars($filter) ?><?= $search_params ?>" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-gray-600 hover:bg-gray-50 hover:text-primary hover:border-primary shadow-sm transition-all"><i class="fas fa-chevron-left text-xs md:text-sm"></i></a>
                    <?php endif; ?>
                    
                    <span class="px-4 md:px-6 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-sm md:text-base font-bold text-gray-700 shadow-sm"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&filter=<?= htmlspecialchars($filter) ?><?= $search_params ?>" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-gray-600 hover:bg-gray-50 hover:text-primary hover:border-primary shadow-sm transition-all"><i class="fas fa-chevron-right text-xs md:text-sm"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div> <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function confirmCancel(event) {
            event.preventDefault(); // Stop the form from submitting immediately
            const form = event.target;

            Swal.fire({
                title: '<?= __('confirm_cancel_bet') ?>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<?= __('delete') ?>',
                cancelButtonText: '<?= __('cancel') ?>',
                customClass: { popup: 'rounded-2xl' }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // If confirmed, submit the form
                }
            });
        }

        function showVoucherDetails(voucherId, createdAt) {
            Swal.fire({
                title: '<?= __('loading_details') ?>',
                text: 'ခေတ္တစောင့်ဆိုင်းပါ...',
                allowOutsideClick: false,
                customClass: { popup: 'rounded-2xl' },
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`bet_history.php?action=get_voucher_details&created_at=${encodeURIComponent(createdAt)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let detailsHtml = `
                            <div class="text-left max-h-80 md:max-h-96 overflow-y-auto p-2 bg-gray-50 rounded-xl border border-gray-200">
                                <table class="w-full text-sm md:text-base">
                                    <thead class="sticky top-0 bg-gray-100/90 backdrop-blur-sm z-10 shadow-sm">
                                        <tr>
                                            <th class="p-2 md:p-3 border-b-2 border-gray-200 font-bold text-gray-600 rounded-tl-lg">ဂဏန်း</th>
                                            <th class="p-2 md:p-3 border-b-2 border-gray-200 font-bold text-gray-600 text-right">ထိုးကြေး</th>
                                            <th class="p-2 md:p-3 border-b-2 border-gray-200 font-bold text-gray-600 text-right">ပေါက်ကြေး</th>
                                            <th class="p-2 md:p-3 border-b-2 border-gray-200 font-bold text-gray-600 text-center rounded-tr-lg">အခြေအနေ</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                        `;

                        let totalBet = 0;
                        let totalWin = 0;

                        data.details.forEach(bet => {
                            let statusClass = '';
                            let statusText = '';
                            let netAmount = bet.amount - bet.discount_amount;
                            totalBet += netAmount;

                            switch(bet.status) {
                                case 'win':
                                    statusClass = 'bg-green-100 text-green-700 border-green-200';
                                    statusText = '<?= __('status_win') ?>';
                                    let payout = bet.amount * bet.odds;
                                    totalWin += payout;
                                    break;
                                case 'lose':
                                    statusClass = 'bg-red-100 text-red-700 border-red-200';
                                    statusText = '<?= __('status_lose') ?>';
                                    break;
                                default:
                                    statusClass = 'bg-yellow-100 text-yellow-700 border-yellow-200';
                                    statusText = '<?= __('status_pending') ?>';
                            }

                            detailsHtml += `
                                <tr class="hover:bg-white transition-colors">
                                    <td class="p-2 md:p-3 text-center font-bold text-blue-700 text-base md:text-lg">${bet.bet_number}</td>
                                    <td class="p-2 md:p-3 text-right text-gray-700 font-medium">${Number(bet.amount).toLocaleString()}</td>
                                    <td class="p-2 md:p-3 text-right text-gray-700 font-medium">${Number(bet.odds).toLocaleString()}</td>
                                    <td class="p-2 md:p-3 text-center"><span class="px-2.5 py-1 text-[10px] md:text-xs font-bold rounded-md border ${statusClass} shadow-sm">${statusText}</span></td>
                                </tr>
                            `;
                        });

                        detailsHtml += `
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-5 text-sm md:text-base space-y-2 text-right font-bold bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                                <div class="flex justify-between items-center"><span class="text-gray-500 uppercase tracking-wide text-xs md:text-sm">စုစုပေါင်းထိုးကြေး</span> <span class="text-red-600 text-lg md:text-xl">${totalBet.toLocaleString()} <span class="text-xs md:text-sm font-normal">Ks</span></span></div>
                                <div class="flex justify-between items-center border-t border-gray-100 pt-2"><span class="text-gray-500 uppercase tracking-wide text-xs md:text-sm">စုစုပေါင်းပေါက်ငွေ</span> <span class="text-green-600 text-lg md:text-xl">${totalWin.toLocaleString()} <span class="text-xs md:text-sm font-normal">Ks</span></span></div>
                            </div>
                        `;

                        Swal.fire({
                            title: `ဘောင်ချာ <span class="font-mono bg-gray-100 px-2 py-1 rounded text-gray-700 ml-2">#${voucherId}</span>`,
                            html: detailsHtml,
                            confirmButtonText: '<i class="fas fa-check mr-1"></i> <?= __('close') ?>',
                            confirmButtonColor: '#1a428a',
                            width: window.innerWidth > 768 ? '600px' : '95%',
                            customClass: {
                                title: 'text-primary font-bold text-xl md:text-2xl flex items-center justify-center',
                                popup: 'rounded-2xl',
                                confirmButton: 'rounded-xl px-6 py-2.5'
                            }
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Could not fetch details.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error fetching voucher details:', error);
                    Swal.fire('Error', 'An error occurred while fetching details.', 'error');
                });
        }

        function downloadVoucher(voucherId) {
            const element = document.getElementById('voucher_' + voucherId);
            
            const wrapper = document.createElement('div');
            wrapper.style.padding = '20px';
            wrapper.style.backgroundColor = '#f8fafc'; // light slate background
            wrapper.style.width = '400px';
            wrapper.style.position = 'fixed';
            wrapper.style.left = '-9999px';
            wrapper.style.top = '0';
            wrapper.style.zIndex = '-1';
            
            const header = document.createElement('div');
            header.style.textAlign = 'center';
            header.style.marginBottom = '20px';
            header.innerHTML = '<h2 style="font-weight:900; color:#1a428a; font-size:24px; margin:0; letter-spacing: 1px;">Thai 2D3D</h2><p style="color:#64748b; font-size:13px; margin-top:4px; font-weight:bold;"><?= __('official_betting_voucher') ?></p><div style="border-bottom:2px dashed #cbd5e1; margin-top:15px; width: 100%;"></div>';
            
            const clone = element.cloneNode(true);
            clone.style.backgroundColor = '#ffffff';
            clone.style.borderRadius = '16px';
            clone.style.padding = '24px';
            clone.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)';
            clone.style.border = '1px solid #e2e8f0';
            
            // Remove the new details button from the clone
            clone.querySelector('button[onclick^="showVoucherDetails"]').remove();
            const downloadBtns = clone.querySelectorAll('.download-btn');
            downloadBtns.forEach(btn => btn.remove());
            const actionForms = clone.querySelectorAll('form');
            actionForms.forEach(form => form.remove());
            
            wrapper.appendChild(header);
            wrapper.appendChild(clone);
            
            const footer = document.createElement('div');
            footer.style.textAlign = 'center';
            footer.style.marginTop = '20px';
            footer.innerHTML = '<div style="border-top:2px dashed #cbd5e1; margin-bottom:15px; width: 100%;"></div><p style="color:#94a3b8; font-size:11px; margin:0;">Thank you for playing with us!</p>';
            wrapper.appendChild(footer);

            document.body.appendChild(wrapper);

            html2canvas(wrapper, {
                scale: 2,
                backgroundColor: '#f8fafc',
                logging: false
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Thai2D3D_Voucher_' + voucherId + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                document.body.removeChild(wrapper);
            }).catch(err => {
                console.error('Error generating voucher image:', err);
                if(document.body.contains(wrapper)) document.body.removeChild(wrapper);
                alert("<?= __('voucher_save_error') ?>");
            });
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>