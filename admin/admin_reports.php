<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Sub-Admin အပါအဝင် ခွင့်ပြုချက်ရှိသူများ ဝင်ခွင့်ပြုမည်
require_permission('can_view_reports');

// Pagination Setup
$limit = 15; // တစ်မျက်နှာတွင် ၁၅ ရက်စာပြသမည်
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get Total Rows for Pagination
$count_result = $conn->query("SELECT COUNT(DISTINCT DATE(created_at)) as total_rows FROM bets");
$count_res = $count_result->fetch_assoc();
$total_rows = $count_res['total_rows'] ?? 0;

$total_pages = ceil($total_rows / $limit);

// နေ့စဉ် အမြတ်/အရှုံး တွက်ချက်ရန် Database မှ Data များကို ဆွဲထုတ်ခြင်း
// မှတ်ချက် - လျော်ကြေး (Payout) ကို 2D ၈၀ ဆ၊ 3D ၅၀၀ ဆ ဖြင့် တွက်ချက်ထားပါသည်။
$query = "SELECT 
            DATE(created_at) as report_date,
            COUNT(id) as total_tickets,
            SUM(amount) as total_income,
            SUM(CASE WHEN status = 'win' AND LENGTH(bet_number) = 2 THEN amount * 80 
                     WHEN status = 'win' AND LENGTH(bet_number) = 3 THEN amount * 500 
                     ELSE 0 END) as total_payout,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
            (SELECT SUM(amount) FROM loans WHERE status = 'approved' AND DATE(updated_at) = DATE(bets.created_at)) as loans_given,
            (SELECT SUM(amount) FROM loans WHERE status = 'repaid' AND DATE(updated_at) = DATE(bets.created_at)) as loans_repaid
          FROM bets
          GROUP BY DATE(created_at)
          ORDER BY report_date DESC LIMIT ?, ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();
$reports = [];
if ($result) {
    $reports = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();
?>

<?php 
$page_title = __('admin_reports_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_reports_header_title');
    $header_icon = "fas fa-chart-line";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <!-- Desktop View: Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full leading-normal text-left">
                    <thead>
                        <tr class="bg-blue-50 text-blue-800 font-bold border-b-2 border-blue-200">
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_reports_date') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_reports_tickets') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap text-right"><?= __('admin_reports_income') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap text-right"><?= __('admin_reports_payout') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap text-right"><?= __('admin_reports_loans') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap text-right"><?= __('admin_reports_net_profit') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($reports) > 0): ?>
                            <?php foreach ($reports as $row): 
                                $income = $row['total_income'];
                                $payout = $row['total_payout'];
                                $loans_given = $row['loans_given'] ?? 0;
                                $loans_repaid = $row['loans_repaid'] ?? 0;
                                $net_profit = $income - $payout - $loans_given + $loans_repaid;
                                $pending = $row['pending_amount'];
                            ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-5 py-4 text-sm font-bold text-gray-700 whitespace-nowrap">
                                    <?= date('d-M-Y', strtotime($row['report_date'])) ?>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-600">
                                    <?= number_format($row['total_tickets']) ?> <?= __('admin_reports_tickets_unit') ?>
                                    <?php if ($pending > 0): ?>
                                        <br><span class="text-[10px] text-yellow-600">(<?= __('admin_reports_pending_amount') ?><?= number_format($pending) ?> <?= __('currency') ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4 text-sm font-bold text-blue-600 text-right">
                                    + <?= number_format($income) ?> <?= __('currency') ?>
                                </td>
                                <td class="px-5 py-4 text-sm font-bold text-red-500 text-right">
                                    - <?= number_format($payout) ?> <?= __('currency') ?>
                                </td>
                            <td class="px-5 py-4 text-sm text-right whitespace-nowrap">
                                <?php if ($loans_given > 0): ?>
                                    <p class="text-red-500 font-bold">- <?= number_format($loans_given) ?> <?= __('currency') ?></p>
                                <?php endif; ?>
                                <?php if ($loans_repaid > 0): ?>
                                    <p class="text-green-600 font-bold">+ <?= number_format($loans_repaid) ?> <?= __('currency') ?></p>
                                <?php endif; ?>
                                <?php if ($loans_given == 0 && $loans_repaid == 0): ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                                <td class="px-5 py-4 text-base font-bold text-right whitespace-nowrap">
                                    <?php if ($net_profit > 0): ?>
                                        <span class="text-green-600 bg-green-100 px-2 py-1 rounded shadow-sm">+ <?= number_format($net_profit) ?> <?= __('currency') ?></span>
                                    <?php elseif ($net_profit < 0): ?>
                                        <span class="text-red-600 bg-red-100 px-2 py-1 rounded shadow-sm"><?= number_format($net_profit) ?> <?= __('currency') ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-600">0 <?= __('currency') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-gray-500 font-bold italic">
                                    <i class="fas fa-box-open text-3xl mb-3 text-gray-300 block"></i>
                                    <?= __('admin_reports_no_records') ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View: Cards -->
            <div class="md:hidden divide-y divide-gray-100">
                <?php if (count($reports) > 0): ?>
                    <?php foreach ($reports as $row): 
                        $income = $row['total_income'];
                        $payout = $row['total_payout'];
                        $loans_given = $row['loans_given'] ?? 0;
                        $loans_repaid = $row['loans_repaid'] ?? 0;
                        $net_profit = $income - $payout - $loans_given + $loans_repaid;
                        $pending = $row['pending_amount'];
                    ?>
                    <div class="p-4">
                        <div class="flex justify-between items-center mb-3">
                            <p class="font-bold text-gray-700"><?= date('d-M-Y', strtotime($row['report_date'])) ?></p>
                            <div class="text-right">
                                <?php if ($net_profit > 0): ?>
                                    <span class="text-green-600 bg-green-100 px-2 py-1 rounded shadow-sm font-bold text-sm">+ <?= number_format($net_profit) ?> Ks</span>
                                <?php elseif ($net_profit < 0): ?>
                                    <span class="text-red-600 bg-red-100 px-2 py-1 rounded shadow-sm font-bold text-sm"><?= number_format($net_profit) ?> Ks</span>
                                <?php else: ?>
                                    <span class="text-gray-600 bg-gray-100 px-2 py-1 rounded shadow-sm font-bold text-sm">0 Ks</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-xs space-y-1 text-gray-600">
                            <div class="flex justify-between"><span><?= __('admin_reports_income') ?>:</span> <span class="font-bold text-blue-600">+ <?= number_format($income) ?> Ks</span></div>
                            <div class="flex justify-between"><span><?= __('admin_reports_payout') ?>:</span> <span class="font-bold text-red-500">- <?= number_format($payout) ?> Ks</span></div>
                            <?php if ($loans_given > 0 || $loans_repaid > 0): ?>
                                <div class="flex justify-between"><span><?= __('admin_reports_loans') ?>:</span> <span class="font-bold"><span class="text-red-500">-<?= number_format($loans_given) ?></span> / <span class="text-green-600">+<?= number_format($loans_repaid) ?></span></span></div>
                            <?php endif; ?>
                            <div class="flex justify-between"><span><?= __('admin_reports_tickets') ?>:</span> <span class="font-bold"><?= number_format($row['total_tickets']) ?></span></div>
                            <?php if ($pending > 0): ?>
                                <div class="flex justify-between"><span><?= __('status_pending') ?>:</span> <span class="font-bold text-yellow-600"><?= number_format($pending) ?> Ks</span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="bg-gray-50 p-4 text-xs text-gray-500 border-t">
                <?= __('admin_reports_footer_note') ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center items-center mt-6 mb-2 space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-left text-xs"></i></a>
                <?php endif; ?>
                
                <span class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold text-gray-700 shadow-sm"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-right text-xs"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>