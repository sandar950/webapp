<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_admin_login();

$stats = [
    'total_users' => 0,
    'today_users' => 0,
    'this_month_users' => 0,
    'total_balance' => 0,
    'total_income' => 0,
    'total_payout' => 0,
    'today_income' => 0,
    'today_payout' => 0,
    'this_month_income' => 0,
    'this_month_payout' => 0,
    'pending_bets_amount' => 0,
    'pending_deposits' => 0,
    'pending_withdrawals' => 0,
    'outstanding_loans' => 0,
    'total_loans_given' => 0,
    'total_loans_repaid' => 0,
    'today_loans_given' => 0,
    'today_loans_repaid' => 0,
    'this_month_loans_given' => 0,
    'this_month_loans_repaid' => 0,
];

$search_tx = '';
$search_bet = '';

/**
 * Fetches recent transactions with an optional search term.
 * @param mysqli $conn The database connection.
 * @param string $search_term The term to search for.
 * @return array The list of recent transactions.
 */
function get_recent_transactions($conn, $search_term = '') {
    $tx_cond_d = "1=1";
    $tx_cond_w = "1=1";
    $tx_types = '';
    $params = [];
    if (!empty($search_term)) {
        $like_term = "%" . $search_term . "%";
        $tx_cond_d = "(u.username LIKE ? OR u.phone_number LIKE ? OR d.transaction_id LIKE ?)";
        $tx_cond_w = "(u.username LIKE ? OR u.phone_number LIKE ? OR w.account_number LIKE ?)";
        $tx_types = "ssssss";
        $params = [$like_term, $like_term, $like_term, $like_term, $like_term, $like_term];
    }
    $query = "
        SELECT 'deposit' as type, d.amount, d.status, d.created_at, u.username 
        FROM deposits d JOIN users u ON d.user_id = u.id WHERE $tx_cond_d
        UNION ALL
        SELECT 'withdrawal' as type, w.amount, w.status, w.created_at, u.username 
        FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE $tx_cond_w
        ORDER BY created_at DESC LIMIT 10
    ";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($search_term)) {
            $stmt->bind_param($tx_types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

/**
 * Fetches recent bets with an optional search term.
 * @param mysqli $conn The database connection.
 * @param string $search_term The term to search for.
 * @return array The list of recent bets.
 */
function get_recent_bets($conn, $search_term = '') {
    $bet_cond = "1=1";
    $bet_types = '';
    $params = [];
    if (!empty($search_term)) {
        $like_term = "%" . $search_term . "%";
        $bet_cond = "(u.username LIKE ? OR u.phone_number LIKE ? OR b.bet_number LIKE ?)";
        $bet_types = "sss";
        $params = [$like_term, $like_term, $like_term];
    }
    $query = "SELECT b.id, b.bet_number, b.amount, b.status, b.created_at, u.username 
              FROM bets b JOIN users u ON b.user_id = u.id 
              WHERE $bet_cond
              ORDER BY b.created_at DESC LIMIT 10";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($search_term)) {
            $stmt->bind_param($bet_types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

// --- AJAX Handler for Live Search ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (isset($_GET['search_tx'])) {
        $search_tx = trim($_GET['search_tx']);
        $recent_transactions = get_recent_transactions($conn, $search_tx);
        
        if (count($recent_transactions) > 0):
            foreach ($recent_transactions as $tx): ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <?php if ($tx['type'] == 'deposit'): ?>
                            <span class="text-green-600 font-bold"><i class="fas fa-arrow-down mr-1"></i> <?= __('admin_dash_type_deposit') ?></span>
                        <?php else: ?>
                            <span class="text-red-600 font-bold"><i class="fas fa-arrow-up mr-1"></i> <?= __('admin_dash_type_withdraw') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-bold whitespace-nowrap"><?= htmlspecialchars($tx['username']) ?></td>
                    <td class="px-4 py-3 text-right font-bold whitespace-nowrap <?= $tx['type'] == 'deposit' ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $tx['type'] == 'deposit' ? '+' : '-' ?><?= number_format($tx['amount']) ?> Ks
                    </td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <?php if ($tx['status'] == 'approved'): ?>
                            <span class="bg-green-100 text-green-700 text-[10px] px-2 py-1 rounded border border-green-300"><?= __('admin_dash_status_success') ?></span>
                        <?php elseif ($tx['status'] == 'pending'): ?>
                            <span class="bg-yellow-100 text-yellow-700 text-[10px] px-2 py-1 rounded border border-yellow-300"><?= __('admin_dash_status_pending') ?></span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 text-[10px] px-2 py-1 rounded border border-red-300"><?= __('admin_dash_status_rejected') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right text-xs text-gray-500 whitespace-nowrap">
                        <?= date('d-M-Y h:i A', strtotime($tx['created_at'])) ?>
                    </td>
                </tr>
            <?php endforeach;
        else: ?>
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-500 italic"><?= __('admin_dash_no_records') ?></td>
            </tr>
        <?php endif;
        exit();
    }

    if (isset($_GET['search_bet'])) {
        $search_bet = trim($_GET['search_bet']);
        $recent_bets = get_recent_bets($conn, $search_bet);

        if (count($recent_bets) > 0):
            foreach ($recent_bets as $bet): ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-bold whitespace-nowrap"><?= htmlspecialchars($bet['username']) ?></td>
                    <td class="px-4 py-3 text-center font-bold text-blue-600 whitespace-nowrap tracking-wider"><?= htmlspecialchars($bet['bet_number']) ?></td>
                    <td class="px-4 py-3 text-right font-bold text-red-600 whitespace-nowrap"><?= number_format($bet['amount']) ?> Ks</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <?php if ($bet['status'] == 'win'): ?>
                            <span class="bg-green-100 text-green-700 text-[10px] px-2 py-1 rounded border border-green-300"><?= __('admin_dash_status_win') ?></span>
                        <?php elseif ($bet['status'] == 'pending'): ?>
                            <span class="bg-yellow-100 text-yellow-700 text-[10px] px-2 py-1 rounded border border-yellow-300"><?= __('admin_dash_status_pending') ?></span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 text-[10px] px-2 py-1 rounded border border-red-300"><?= __('admin_dash_status_lose') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right text-xs text-gray-500 whitespace-nowrap">
                        <?= date('d-M-Y h:i A', strtotime($bet['created_at'])) ?>
                    </td>
                </tr>
            <?php endforeach;
        else: ?>
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-500 italic"><?= __('admin_dash_no_records') ?></td>
            </tr>
        <?php endif;
        exit();
    }
}

    // ၁။ User Stats နှင့် Pending Counts - Consolidated Query
    $user_stats_query = "
        SELECT 
            COUNT(id) as total_users, 
            SUM(balance) as total_balance,
            SUM(CASE WHEN created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY THEN 1 ELSE 0 END) as today_users,
            SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH THEN 1 ELSE 0 END) as this_month_users,
            (SELECT COUNT(id) FROM deposits WHERE status = 'pending') as pending_deposits,
            (SELECT COUNT(id) FROM withdrawals WHERE status = 'pending') as pending_withdrawals
        FROM users
    ";
    $res = $conn->query($user_stats_query);
    if ($res && $row = $res->fetch_assoc()) {
        $stats['total_users'] = $row['total_users'] ?? 0;
        $stats['total_balance'] = $row['total_balance'] ?? 0;
        $stats['today_users'] = $row['today_users'] ?? 0;
        $stats['this_month_users'] = $row['this_month_users'] ?? 0;
        $stats['pending_deposits'] = $row['pending_deposits'] ?? 0;
        $stats['pending_withdrawals'] = $row['pending_withdrawals'] ?? 0;
    }

    // ၂။ Bets Stats (Income, Payout, Pending for Total, Today, This Month) - Consolidated Query
    $payout_logic = "CASE WHEN status = 'win' THEN amount * IFNULL(odds, IF(LENGTH(bet_number) = 2, 80, 500)) ELSE 0 END";
    $income_logic = "amount - IFNULL(discount_amount, 0)";
    $bets_stats_query = "
        SELECT
            SUM($income_logic) as total_income,
            SUM($payout_logic) as total_payout,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_bets_amount,
            SUM(CASE WHEN created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY THEN $income_logic ELSE 0 END) as today_income,
            SUM(CASE WHEN created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY THEN $payout_logic ELSE 0 END) as today_payout,
            SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH THEN $income_logic ELSE 0 END) as this_month_income,
            SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH THEN $payout_logic ELSE 0 END) as this_month_payout
        FROM bets
    ";
    $res = $conn->query($bets_stats_query);
    if ($res && $row = $res->fetch_assoc()) {
        $stats['total_income'] = $row['total_income'] ?? 0;
        $stats['total_payout'] = $row['total_payout'] ?? 0;
        $stats['pending_bets_amount'] = $row['pending_bets_amount'] ?? 0;
        $stats['today_income'] = $row['today_income'] ?? 0;
        $stats['today_payout'] = $row['today_payout'] ?? 0;
        $stats['this_month_income'] = $row['this_month_income'] ?? 0;
        $stats['this_month_payout'] = $row['this_month_payout'] ?? 0;
    }

    // ၃။ ချေးငွေ အဝင်/အထွက် အခြေအနေများ (Loans Cashflow) - Consolidated Query
    $loans_stats_query = "
        SELECT
            SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as outstanding_loans,
            SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_loans_given,
            SUM(CASE WHEN status = 'repaid' THEN amount ELSE 0 END) as total_loans_repaid,
            SUM(CASE WHEN status = 'approved' AND updated_at >= CURDATE() AND updated_at < CURDATE() + INTERVAL 1 DAY THEN amount ELSE 0 END) as today_loans_given,
            SUM(CASE WHEN status = 'repaid' AND updated_at >= CURDATE() AND updated_at < CURDATE() + INTERVAL 1 DAY THEN amount ELSE 0 END) as today_loans_repaid,
            SUM(CASE WHEN status = 'approved' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND updated_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH THEN amount ELSE 0 END) as this_month_loans_given,
            SUM(CASE WHEN status = 'repaid' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND updated_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH THEN amount ELSE 0 END) as this_month_loans_repaid
        FROM loans
    ";
    $res = $conn->query($loans_stats_query);
    if ($res && $row = $res->fetch_assoc()) { 
        $stats['outstanding_loans'] = $row['outstanding_loans'] ?? 0;
        $stats['total_loans_given'] = $row['total_loans_given'] ?? 0; 
        $stats['total_loans_repaid'] = $row['total_loans_repaid'] ?? 0; 
        $stats['today_loans_given'] = $row['today_loans_given'] ?? 0; 
        $stats['today_loans_repaid'] = $row['today_loans_repaid'] ?? 0; 
        $stats['this_month_loans_given'] = $row['this_month_loans_given'] ?? 0; 
        $stats['this_month_loans_repaid'] = $row['this_month_loans_repaid'] ?? 0; 
    }

    // အမြတ်/အရှုံး တွက်ချက်ခြင်း (Net Cashflow) - ရငွေ - လျော်ငွေ - ထုတ်ချေးငွေ + ပြန်ဆပ်ငွေ
    $total_profit = $stats['total_income'] - $stats['total_payout'] - $stats['total_loans_given'] + $stats['total_loans_repaid'];
    $today_profit = $stats['today_income'] - $stats['today_payout'] - $stats['today_loans_given'] + $stats['today_loans_repaid'];
    $this_month_profit = $stats['this_month_income'] - $stats['this_month_payout'] - $stats['this_month_loans_given'] + $stats['this_month_loans_repaid'];

    // ရာခိုင်နှုန်း တွက်ချက်ခြင်း (Profit Margin)
    $total_profit_percent = $stats['total_income'] > 0 ? ($total_profit / $stats['total_income']) * 100 : 0;
    $today_profit_percent = $stats['today_income'] > 0 ? ($today_profit / $stats['today_income']) * 100 : 0;
    $this_month_profit_percent = $stats['this_month_income'] > 0 ? ($this_month_profit / $stats['this_month_income']) * 100 : 0;

    // ယခုလ၏ နေ့စဉ် ထိုးကြေးနှင့် လျော်ကြေး (Chart အတွက်)
    $chart_data_query = "SELECT 
        DATE(created_at) as date, 
        SUM(amount - IFNULL(discount_amount, 0)) as income, 
        SUM(CASE WHEN status = 'win' THEN amount * IFNULL(odds, IF(LENGTH(bet_number) = 2, 80, 500)) ELSE 0 END) as payout
        FROM bets 
        WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC";
    $chart_result = $conn->query($chart_data_query);
    $chart_labels = [];
    $chart_incomes = [];
    $chart_payouts = [];
    if ($chart_result) {
        while ($row = $chart_result->fetch_assoc()) {
            $chart_labels[] = date('d-M', strtotime($row['date']));
            $chart_incomes[] = floatval($row['income']);
            $chart_payouts[] = floatval($row['payout']);
        }
    }

    // နောက်ဆုံးဝင်ရောက်လာသော User ၅ ယောက်
    $recent_users_query = "SELECT id, username, phone_number, created_at FROM users ORDER BY created_at DESC LIMIT 5";
    $recent_users_result = $conn->query($recent_users_query);
    $recent_users = [];
    if ($recent_users_result) {
        while ($row = $recent_users_result->fetch_assoc()) {
            $recent_users[] = $row;
        }
    }

    // ယခုလအတွင်း အများဆုံး ထိုးကြေးထည့်ထားသော User (Top 5)
    $top_bettors_query = "
        SELECT u.id, u.username, u.phone_number, SUM(b.amount - IFNULL(b.discount_amount, 0)) as total_betted 
        FROM bets b 
        JOIN users u ON b.user_id = u.id 
        WHERE b.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND b.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH
        GROUP BY u.id, u.username, u.phone_number 
        ORDER BY total_betted DESC 
        LIMIT 5
    ";
    $top_bettors_result = $conn->query($top_bettors_query);
    $top_bettors = [];
    if ($top_bettors_result) {
        while ($row = $top_bettors_result->fetch_assoc()) {
            $top_bettors[] = $row;
        }
    }

// Initial Data Load for Tables
$recent_transactions = get_recent_transactions($conn);
$recent_bets = get_recent_bets($conn);

?>

<?php 
$page_title = __('admin_dashboard_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-6xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_dashboard_header_title');
    $header_icon = "fas fa-tachometer-alt";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <!-- Quick Links (System Logs) -->
        <div class="flex flex-wrap gap-2 mb-6">
            <a href="admin_health_check.php" class="bg-white border border-gray-200 text-gray-700 hover:text-red-600 hover:border-red-300 px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition flex items-center">
                <i class="fas fa-heartbeat text-red-500 mr-2"></i> <span class="hidden sm:inline"><?= __('admin_system_health') ?></span>
            </a>
            <a href="admin_activity_log.php" class="bg-white border border-gray-200 text-gray-700 hover:text-blue-600 hover:border-blue-300 px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition flex items-center">
                <i class="fas fa-clipboard-list text-blue-500 mr-2"></i> <?= __('admin_activity_logs') ?>
            </a>
            <a href="admin_error_logs.php" class="bg-white border border-red-200 text-red-700 hover:bg-red-50 px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition flex items-center">
                <i class="fas fa-bug text-red-500 mr-2"></i> <?= __('admin_error_logs') ?>
            </a>
        </div>
        
        <h2 class="text-lg font-bold text-gray-800 mb-4 border-l-4 border-blue-500 pl-3"><?= __('admin_today_status_title') ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="relative bg-gradient-to-br from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas fa-user-plus text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start">
                    <div>
                        <p class="text-indigo-100 text-sm font-medium mb-1"><?= __('admin_today_new_users') ?></p>
                        <p class="text-3xl font-extrabold text-white">+ <?= number_format($stats['today_users']) ?> <span class="text-base font-normal text-indigo-200"><?= __('unit_users') ?></span></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30">
                        <i class="fas fa-user-plus"></i>
                    </div>
                </div>
            </div>
            <div class="relative bg-gradient-to-br from-blue-500 to-cyan-500 rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas fa-hand-holding-usd text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start">
                    <div>
                        <p class="text-blue-100 text-sm font-medium mb-1"><?= __('admin_today_bet_income') ?></p>
                        <p class="text-3xl font-extrabold text-white">+ <?= number_format($stats['today_income']) ?> <span class="text-base font-normal text-blue-200"><?= __('currency') ?></span></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
            </div>
            <div class="relative bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas fa-money-bill-wave text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start">
                    <div>
                        <p class="text-rose-100 text-sm font-medium mb-1"><?= __('admin_today_payout') ?></p>
                        <p class="text-3xl font-extrabold text-white">- <?= number_format($stats['today_payout']) ?> <span class="text-base font-normal text-rose-200"><?= __('currency') ?></span></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
            <div class="relative bg-gradient-to-br <?= $today_profit >= 0 ? 'from-emerald-500 to-teal-600' : 'from-red-500 to-rose-600' ?> rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl flex flex-col justify-between">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas <?= $today_profit >= 0 ? 'fa-chart-line' : 'fa-chart-line fa-flip-vertical' ?> text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start w-full">
                    <div>
                        <p class="text-white/80 text-sm font-medium mb-1" title="<?= __('admin_profit_loss_tooltip') ?>"><?= __('admin_today_profit_loss') ?></p>
                        <p class="text-3xl font-extrabold text-white">
                            <?= $today_profit > 0 ? '+' : '' ?><?= number_format($today_profit) ?> <span class="text-base font-normal text-white/70"><?= __('currency') ?></span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30 flex-shrink-0">
                        <i class="fas <?= $today_profit >= 0 ? 'fa-chart-line' : 'fa-chart-line fa-flip-vertical' ?>"></i>
                    </div>
                </div>
                <div class="relative z-10 mt-3 flex justify-between items-end w-full">
                    <?php if ($stats['today_loans_given'] > 0 || $stats['today_loans_repaid'] > 0): ?>
                        <p class="text-[11px] text-white/70 font-medium bg-black/10 px-2 py-1 rounded"><?= __('admin_loans') ?> <span class="text-red-200">-<?= number_format($stats['today_loans_given']) ?></span> | <span class="text-green-200">+<?= number_format($stats['today_loans_repaid']) ?></span></p>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <?php if ($stats['today_income'] > 0): ?>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-white/20 text-white backdrop-blur-sm border border-white/30 shadow-sm">
                            <?= $today_profit > 0 ? '+' : '' ?><?= number_format($today_profit_percent, 1) ?>%
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h2 class="text-lg font-bold text-gray-800 mb-4 border-l-4 border-green-500 pl-3"><?= __('admin_this_month_status_title') ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="relative bg-gradient-to-br from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas fa-users text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start">
                    <div>
                        <p class="text-indigo-100 text-sm font-medium mb-1"><?= __('admin_this_month_new_users') ?></p>
                        <p class="text-3xl font-extrabold text-white">+ <?= number_format($stats['this_month_users']) ?> <span class="text-base font-normal text-indigo-200"><?= __('unit_users') ?></span></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="relative bg-gradient-to-br from-blue-500 to-cyan-500 rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas fa-wallet text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start">
                    <div>
                        <p class="text-blue-100 text-sm font-medium mb-1"><?= __('admin_this_month_bet_income') ?></p>
                        <p class="text-3xl font-extrabold text-white">+ <?= number_format($stats['this_month_income']) ?> <span class="text-base font-normal text-blue-200"><?= __('currency') ?></span></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
            </div>
            <div class="relative bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas fa-hand-holding-usd text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start">
                    <div>
                        <p class="text-rose-100 text-sm font-medium mb-1"><?= __('admin_this_month_payout') ?></p>
                        <p class="text-3xl font-extrabold text-white">- <?= number_format($stats['this_month_payout']) ?> <span class="text-base font-normal text-rose-200"><?= __('currency') ?></span></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
            </div>
            <div class="relative bg-gradient-to-br <?= $this_month_profit >= 0 ? 'from-emerald-500 to-teal-600' : 'from-red-500 to-rose-600' ?> rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl flex flex-col justify-between">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas <?= $this_month_profit >= 0 ? 'fa-chart-pie' : 'fa-chart-pie' ?> text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start w-full">
                    <div>
                        <p class="text-white/80 text-sm font-medium mb-1" title="<?= __('admin_profit_loss_tooltip') ?>"><?= __('admin_this_month_profit_loss') ?></p>
                        <p class="text-3xl font-extrabold text-white">
                            <?= $this_month_profit > 0 ? '+' : '' ?><?= number_format($this_month_profit) ?> <span class="text-base font-normal text-white/70"><?= __('currency') ?></span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30 flex-shrink-0">
                        <i class="fas <?= $this_month_profit >= 0 ? 'fa-chart-pie' : 'fa-chart-pie' ?>"></i>
                    </div>
                </div>
                <div class="relative z-10 mt-3 flex justify-between items-end w-full">
                    <?php if ($stats['this_month_loans_given'] > 0 || $stats['this_month_loans_repaid'] > 0): ?>
                        <p class="text-[11px] text-white/70 font-medium bg-black/10 px-2 py-1 rounded"><?= __('admin_loans') ?> <span class="text-red-200">-<?= number_format($stats['this_month_loans_given']) ?></span> | <span class="text-green-200">+<?= number_format($stats['this_month_loans_repaid']) ?></span></p>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <?php if ($stats['this_month_income'] > 0): ?>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-white/20 text-white backdrop-blur-sm border border-white/30 shadow-sm">
                            <?= $this_month_profit > 0 ? '+' : '' ?><?= number_format($this_month_profit_percent, 1) ?>%
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Chart & Recent Users Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Chart Section -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-5 border border-gray-100 flex flex-col">
                <h3 class="font-bold text-gray-700 mb-4"><i class="fas fa-chart-area text-blue-500 mr-2"></i> <?= __('admin_monthly_profit_loss_chart') ?></h3>
                <div class="relative flex-1 min-h-[320px] w-full">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <!-- Sidebar Section (Top Bettors & Recent Users) -->
            <div class="lg:col-span-1 flex flex-col gap-6">
                <!-- Top Bettors Section -->
                <div class="bg-white rounded-xl shadow-md p-5 border border-gray-100 flex-1">
                    <h3 class="font-bold text-gray-700 mb-4"><i class="fas fa-trophy text-yellow-500 mr-2"></i> <?= __('admin_top_users_this_month') ?></h3>
                    <div class="space-y-3">
                        <?php if (count($top_bettors) > 0): ?>
                            <?php foreach ($top_bettors as $index => $user): ?>
                                <a href="admin_user_history.php?user_id=<?= $user['id'] ?>" class="flex items-center p-2 rounded-lg hover:bg-gray-50 border border-gray-50">
                                    <div class="w-8 h-8 rounded-full <?= $index == 0 ? 'bg-yellow-100 text-yellow-600' : ($index == 1 ? 'bg-gray-200 text-gray-600' : ($index == 2 ? 'bg-orange-100 text-orange-600' : 'bg-blue-50 text-blue-500')) ?> flex items-center justify-center mr-3 font-bold text-xs shadow-sm">#<?= $index + 1 ?></div>
                                    <div class="flex-1">
                                        <p class="font-bold text-sm text-gray-800"><?= htmlspecialchars($user['username']) ?></p>
                                        <p class="text-[10px] text-gray-500"><?= htmlspecialchars($user['phone_number']) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-sm text-red-600"><?= number_format($user['total_betted']) ?> <?= __('currency') ?></p>
                                        <p class="text-[10px] text-gray-400"><?= __('currency') ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?> <p class="text-sm text-gray-500 italic text-center py-4"><?= __('no_records_found') ?></p> <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Users Section -->
                <div class="bg-white rounded-xl shadow-md p-5 border border-gray-100 flex-1">
                    <h3 class="font-bold text-gray-700 mb-4"><i class="fas fa-user-clock text-purple-500 mr-2"></i> <?= __('admin_recent_users') ?></h3>
                    <div class="space-y-3">
                        <?php if (count($recent_users) > 0): ?>
                            <?php foreach ($recent_users as $user): ?>
                                <a href="admin_user_history.php?user_id=<?= $user['id'] ?>" class="flex items-center p-2 rounded-lg hover:bg-gray-50 border border-gray-50">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center mr-3 text-gray-500"><i class="fas fa-user text-xs"></i></div>
                                    <div class="flex-1">
                                        <p class="font-bold text-sm text-gray-800"><?= htmlspecialchars($user['username']) ?></p>
                                        <p class="text-[10px] text-gray-500"><?= htmlspecialchars($user['phone_number']) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] text-gray-400"><i class="far fa-clock"></i> <?= date('d-M h:i A', strtotime($user['created_at'])) ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?> <p class="text-sm text-gray-500 italic text-center py-4"><?= __('admin_dash_no_new_users') ?></p> <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions Section -->
        <div class="bg-white rounded-xl shadow-md p-5 mb-8 border border-gray-100">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                <h3 class="font-bold text-gray-700"><i class="fas fa-exchange-alt text-green-500 mr-2"></i> <?= __('admin_dash_recent_tx') ?></h3>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    <div class="relative" role="search">
                        <input type="text" id="txSearchInput" value="<?= htmlspecialchars($search_tx ?? '') ?>" oninput="liveTxSearch()" placeholder="<?= __('admin_search_name_phone_placeholder') ?>" class="w-full sm:w-48 px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:border-blue-500">
                        <i class="fas fa-search absolute right-2.5 top-2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                    <a href="admin_transactions.php" class="text-sm text-blue-600 hover:underline font-bold whitespace-nowrap"><?= __('admin_dash_view_all') ?></a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 border-b">
                            <th class="px-4 py-3 font-bold whitespace-nowrap"><?= __('admin_dash_col_type') ?></th>
                            <th class="px-4 py-3 font-bold whitespace-nowrap"><?= __('admin_username_user') ?></th>
                            <th class="px-4 py-3 font-bold text-right whitespace-nowrap"><?= __('admin_dash_col_amount') ?></th>
                            <th class="px-4 py-3 font-bold text-center whitespace-nowrap"><?= __('admin_dash_col_status') ?></th>
                            <th class="px-4 py-3 font-bold text-right whitespace-nowrap"><?= __('admin_dash_col_time') ?></th>
                        </tr>
                    </thead>
                    <tbody id="txTableBody" class="text-gray-700 divide-y">
                        <?php if (count($recent_transactions) > 0): ?>
                            <?php foreach ($recent_transactions as $tx): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($tx['type'] == 'deposit'): ?>
                                            <span class="text-green-600 font-bold"><i class="fas fa-arrow-down mr-1"></i> <?= __('admin_dash_type_deposit') ?></span>
                                        <?php else: ?>
                                            <span class="text-red-600 font-bold"><i class="fas fa-arrow-up mr-1"></i> <?= __('admin_dash_type_withdraw') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 font-bold whitespace-nowrap"><?= htmlspecialchars($tx['username']) ?></td>
                                    <td class="px-4 py-3 text-right font-bold whitespace-nowrap <?= $tx['type'] == 'deposit' ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $tx['type'] == 'deposit' ? '+' : '-' ?><?= number_format($tx['amount']) ?> Ks
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <?php if ($tx['status'] == 'approved'): ?>
                                            <span class="bg-green-100 text-green-700 text-[10px] px-2 py-1 rounded border border-green-300"><?= __('admin_dash_status_success') ?></span>
                                        <?php elseif ($tx['status'] == 'pending'): ?>
                                            <span class="bg-yellow-100 text-yellow-700 text-[10px] px-2 py-1 rounded border border-yellow-300"><?= __('admin_dash_status_pending') ?></span>
                                        <?php else: ?>
                                            <span class="bg-red-100 text-red-700 text-[10px] px-2 py-1 rounded border border-red-300"><?= __('admin_dash_status_rejected') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs text-gray-500 whitespace-nowrap">
                                        <?= date('d-M-Y h:i A', strtotime($tx['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500 italic"><?= __('admin_dash_no_records') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Bets Section -->
        <div class="bg-white rounded-xl shadow-md p-5 mb-8 border border-gray-100">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                <h3 class="font-bold text-gray-700"><i class="fas fa-dice text-blue-500 mr-2"></i> <?= __('admin_dash_recent_bets') ?></h3>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    <div class="relative" role="search">
                        <input type="text" id="betSearchInput" value="<?= htmlspecialchars($search_bet ?? '') ?>" oninput="liveBetSearch()" placeholder="<?= __('admin_search_name_phone_number_placeholder') ?>" class="w-full sm:w-48 px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:border-blue-500">
                        <i class="fas fa-search absolute right-2.5 top-2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 border-b">
                            <th class="px-4 py-3 font-bold whitespace-nowrap"><?= __('admin_username_user') ?></th>
                            <th class="px-4 py-3 font-bold text-center whitespace-nowrap"><?= __('admin_dash_col_number') ?></th>
                            <th class="px-4 py-3 font-bold text-right whitespace-nowrap"><?= __('admin_dash_col_amount') ?></th>
                            <th class="px-4 py-3 font-bold text-center whitespace-nowrap"><?= __('admin_dash_col_status') ?></th>
                            <th class="px-4 py-3 font-bold text-right whitespace-nowrap"><?= __('admin_dash_col_time') ?></th>
                        </tr>
                    </thead>
                    <tbody id="betTableBody" class="text-gray-700 divide-y">
                        <?php if (count($recent_bets) > 0): ?>
                            <?php foreach ($recent_bets as $bet): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 font-bold whitespace-nowrap"><?= htmlspecialchars($bet['username']) ?></td>
                                    <td class="px-4 py-3 text-center font-bold text-blue-600 whitespace-nowrap tracking-wider"><?= htmlspecialchars($bet['bet_number']) ?></td>
                                    <td class="px-4 py-3 text-right font-bold text-red-600 whitespace-nowrap"><?= number_format($bet['amount']) ?> Ks</td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <?php if ($bet['status'] == 'win'): ?>
                                            <span class="bg-green-100 text-green-700 text-[10px] px-2 py-1 rounded border border-green-300"><?= __('admin_dash_status_win') ?></span>
                                        <?php elseif ($bet['status'] == 'pending'): ?>
                                            <span class="bg-yellow-100 text-yellow-700 text-[10px] px-2 py-1 rounded border border-yellow-300"><?= __('admin_dash_status_pending') ?></span>
                                        <?php else: ?>
                                            <span class="bg-red-100 text-red-700 text-[10px] px-2 py-1 rounded border border-red-300"><?= __('admin_dash_status_lose') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs text-gray-500 whitespace-nowrap">
                                        <?= date('d-M-Y h:i A', strtotime($bet['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500 italic"><?= __('admin_dash_no_records') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <h2 class="text-lg font-bold text-gray-800 mb-4 border-l-4 border-purple-500 pl-3"><?= __('admin_dash_overall_status') ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="relative bg-gradient-to-br from-gray-700 to-gray-900 rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl">
                <div class="absolute -right-6 -top-6 text-white/5">
                    <i class="fas fa-users-cog text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start">
                    <div>
                        <p class="text-gray-300 text-sm font-medium mb-1"><?= __('admin_dash_total_users') ?></p>
                        <p class="text-3xl font-extrabold text-white"><?= number_format($stats['total_users']) ?> <span class="text-base font-normal text-gray-400"><?= __('admin_dash_users_unit') ?></span></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/10 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/20">
                        <i class="fas fa-users-cog"></i>
                    </div>
                </div>
            </div>
            <div class="relative bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl flex flex-col justify-between">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas fa-coins text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start w-full">
                    <div>
                        <p class="text-amber-100 text-sm font-medium mb-1"><?= __('admin_dash_total_balance') ?></p>
                        <p class="text-3xl font-extrabold text-white"><?= number_format($stats['total_balance']) ?> <span class="text-base font-normal text-amber-200">Ks</span></p>
                        <p class="text-[11px] text-amber-200 mt-1 font-medium"><?= __('admin_dash_liability') ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30 flex-shrink-0">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="relative z-10 mt-3 pt-3 border-t border-white/20 flex justify-between items-center w-full">
                    <p class="text-xs text-amber-100 font-medium"><?= __('admin_outstanding_loans') ?></p>
                    <p class="text-sm font-bold text-white"><?= number_format($stats['outstanding_loans'] ?? 0) ?> <span class="text-[10px] font-normal text-amber-200">Ks</span></p>
                </div>
            </div>
            <div class="relative bg-gradient-to-br from-fuchsia-500 to-purple-600 rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas fa-hourglass-half text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start">
                    <div>
                        <p class="text-fuchsia-100 text-sm font-medium mb-1"><?= __('admin_dash_pending_bets') ?></p>
                        <p class="text-3xl font-extrabold text-white"><?= number_format($stats['pending_bets_amount']) ?> <span class="text-base font-normal text-fuchsia-200">Ks</span></p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>
            <div class="relative bg-gradient-to-br <?= $total_profit >= 0 ? 'from-emerald-500 to-teal-600' : 'from-red-500 to-rose-600' ?> rounded-2xl shadow-lg p-6 overflow-hidden transition-all hover:-translate-y-1 hover:shadow-xl flex flex-col justify-between">
                <div class="absolute -right-6 -top-6 text-white/10">
                    <i class="fas fa-gem text-8xl"></i>
                </div>
                <div class="relative z-10 flex flex-wrap justify-between items-start w-full">
                    <div>
                        <p class="text-white/80 text-sm font-medium mb-1"><?= __('admin_dash_net_profit') ?></p>
                        <p class="text-3xl font-extrabold text-white">
                            <?= $total_profit > 0 ? '+' : '' ?><?= number_format($total_profit) ?> <span class="text-base font-normal text-white/70">Ks</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl shadow-inner border border-white/30 flex-shrink-0">
                        <i class="fas fa-gem"></i>
                    </div>
                </div>
                <div class="relative z-10 mt-3 flex justify-end w-full">
                    <?php if ($stats['total_income'] > 0): ?>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-white/20 text-white backdrop-blur-sm border border-white/30 shadow-sm">
                            <?= $total_profit > 0 ? '+' : '' ?><?= number_format($total_profit_percent, 1) ?>%
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if($stats['pending_deposits'] > 0 || $stats['pending_withdrawals'] > 0): ?>
            <div class="bg-orange-50 border border-orange-200 rounded-xl p-5 mb-8 flex justify-between items-center shadow-sm">
                <div>
                    <h3 class="font-bold text-orange-800 mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> <?= __('admin_important_alert') ?></h3>
                    <p class="text-sm text-orange-700"><?= __('admin_deposit_requests') ?> (<?= $stats['pending_deposits'] ?>) <?= __('admin_and') ?> <?= __('admin_withdrawal_requests') ?> (<?= $stats['pending_withdrawals'] ?>) <?= __('admin_to_review') ?></p>
                </div>
                <a href="admin_transactions.php" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded shadow text-sm font-bold transition"><?= __('admin_go_to_review') ?></a>
            </div>
        <?php endif; ?>

    </div>

    <!-- Chart.js ဖြင့် ဂရပ်ဆွဲခြင်း -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [
                    {
                        label: '<?= __('admin_chart_income') ?>',
                        data: <?= json_encode($chart_incomes) ?>,
                        borderColor: 'rgb(59, 130, 246)', // Tailwind blue-500
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: '<?= __('admin_chart_payout') ?>',
                        data: <?= json_encode($chart_payouts) ?>,
                        borderColor: 'rgb(239, 68, 68)', // Tailwind red-500
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' <?= __('currency') ?>';
                            }
                        }
                    }
                }
            }
        });

    // AJAX Search for Transactions Table
    let txSearchTimeout;
    function liveTxSearch() {
        clearTimeout(txSearchTimeout);
        txSearchTimeout = setTimeout(() => {
            let searchTerm = document.getElementById('txSearchInput').value;
            let url = `admin_dashboard.php?search_tx=${encodeURIComponent(searchTerm)}&ajax=1`;
            
            let tbody = document.getElementById('txTableBody');
            if (tbody) tbody.style.opacity = '0.5';
            
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    if (tbody) {
                        tbody.innerHTML = html;
                    }
                    if (tbody) tbody.style.opacity = '1';
                })
                .catch(err => console.error('Search error:', err));
        }, 300);
    }

    // AJAX Search for Recent Bets Table
    let betSearchTimeout;
    function liveBetSearch() {
        clearTimeout(betSearchTimeout);
        betSearchTimeout = setTimeout(() => {
            let searchTerm = document.getElementById('betSearchInput').value;
            let url = `admin_dashboard.php?search_bet=${encodeURIComponent(searchTerm)}&ajax=1`;
            
            let tbody = document.getElementById('betTableBody');
            if (tbody) tbody.style.opacity = '0.5';
            
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    if (tbody) {
                        tbody.innerHTML = html;
                    }
                    if (tbody) tbody.style.opacity = '1';
                })
                .catch(err => console.error('Search error:', err));
        }, 300);
    }
    </script>
</body>
</html>