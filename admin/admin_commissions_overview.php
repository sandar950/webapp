<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Admin (User ID 1) သာ ဝင်ခွင့်ပြုမည်
require_main_admin();

// အခြေခံ ကိန်းဂဏန်းများ တွက်ချက်ခြင်း (Stats)
$stats = [
    'total' => 0,
    'today' => 0,
    'this_month' => 0
];

// စုစုပေါင်း
$res = $conn->query("SELECT SUM(amount) as s FROM commissions");
if ($row = $res->fetch_assoc()) $stats['total'] = $row['s'] ?? 0;

// ယနေ့
$res = $conn->query("SELECT SUM(amount) as s FROM commissions WHERE DATE(created_at) = CURDATE()");
if ($row = $res->fetch_assoc()) $stats['today'] = $row['s'] ?? 0;

// ယခုလ
$res = $conn->query("SELECT SUM(amount) as s FROM commissions WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
if ($row = $res->fetch_assoc()) $stats['this_month'] = $row['s'] ?? 0;

// ကော်မရှင်အများဆုံး ရရှိထားသော User (Top 5)
$top_earners = [];
$res = $conn->query("
    SELECT u.id, u.username, u.phone_number, SUM(c.amount) as total_earned 
    FROM commissions c 
    JOIN users u ON c.referrer_id = u.id 
    GROUP BY c.referrer_id 
    ORDER BY total_earned DESC 
    LIMIT 5
");
if ($res) {
    $top_earners = $res->fetch_all(MYSQLI_ASSOC);
}

// နောက်ဆုံး ကော်မရှင်မှတ်တမ်းများအတွက် Pagination
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$count_res = $conn->query("SELECT COUNT(id) as c FROM commissions");
$total_rows = $count_res->fetch_assoc()['c'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// နောက်ဆုံးဝင်ရောက်ခဲ့သော ကော်မရှင်မှတ်တမ်းများကို ဆွဲထုတ်ခြင်း
$stmt = $conn->prepare("
    SELECT c.amount, c.description, c.created_at, u1.username as referrer_name, u2.username as referred_name 
    FROM commissions c 
    JOIN users u1 ON c.referrer_id = u1.id 
    JOIN users u2 ON c.referred_user_id = u2.id 
    ORDER BY c.created_at DESC 
    LIMIT ?, ?
");
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$recent_commissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php 
$page_title = __('admin_commissions_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-5xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_commissions_header_title');
    $header_icon = "fas fa-hand-holding-usd text-emerald-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-md p-5 border-t-4 border-emerald-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-500 mb-1 font-bold"><?= __('admin_commissions_today') ?></p>
                        <p class="text-2xl font-bold text-emerald-600"><?= number_format($stats['today']) ?> Ks</p>
                    </div>
                    <i class="fas fa-calendar-day text-3xl text-emerald-100"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-5 border-t-4 border-emerald-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-500 mb-1 font-bold"><?= __('admin_commissions_month') ?></p>
                        <p class="text-2xl font-bold text-emerald-600"><?= number_format($stats['this_month']) ?> Ks</p>
                    </div>
                    <i class="fas fa-calendar-alt text-3xl text-emerald-100"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-5 border-t-4 border-blue-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-500 mb-1 font-bold"><?= __('admin_commissions_total') ?></p>
                        <p class="text-2xl font-bold text-blue-600"><?= number_format($stats['total']) ?> Ks</p>
                    </div>
                    <i class="fas fa-hand-holding-usd text-3xl text-blue-100"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Top Earners Table -->
            <div class="lg:col-span-1 bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
                <div class="p-4 border-b bg-emerald-50">
                    <h3 class="font-bold text-emerald-800"><i class="fas fa-trophy text-yellow-500 mr-2"></i> <?= __('admin_commissions_top_earners') ?></h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php if(count($top_earners) > 0): ?>
                        <?php foreach($top_earners as $index => $earner): ?>
                            <div class="p-4 hover:bg-gray-50 transition flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="font-bold text-gray-400">#<?= $index + 1 ?></span>
                                    <div>
                                        <p class="font-bold text-sm text-gray-800 truncate max-w-[120px]"><?= htmlspecialchars($earner['username']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($earner['phone_number']) ?></p>
                                    </div>
                                </div>
                                <p class="font-bold text-emerald-600 text-sm"><?= number_format($earner['total_earned']) ?> Ks</p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="p-6 text-center text-gray-500 italic text-sm"><?= __('admin_dash_no_records') ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Recent Commissions -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-5 border border-gray-100">
                <h3 class="font-bold text-gray-700 mb-4 border-b pb-2"><i class="fas fa-history text-blue-500 mr-2"></i> <?= __('admin_commissions_recent_title') ?></h3>
                
                <?php if (count($recent_commissions) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_commissions as $comm): ?>
                            <div class="border border-gray-100 p-3 rounded-lg hover:bg-gray-50 flex flex-wrap justify-between items-center gap-2">
                                <div>
                                    <p class="text-sm text-gray-800 font-bold"><i class="fas fa-user text-primary mr-1"></i> <?= htmlspecialchars($comm['referrer_name']) ?> <span class="text-gray-400 font-normal mx-1"><?= __('admin_commissions_received') ?> </span> <i class="fas fa-arrow-right text-gray-300 text-xs mx-1"></i> <i class="fas fa-user-friends text-blue-400 mr-1 text-xs"></i><span class="text-xs"><?= htmlspecialchars($comm['referred_name']) ?> <span class="text-gray-400"><?= __('admin_commissions_from') ?></span></span></p>
                                    <p class="text-[10px] text-gray-400 mt-1"><i class="far fa-clock"></i> <?= date('d-M-Y h:i A', strtotime($comm['created_at'])) ?> <span class="text-gray-300 mx-1">|</span> <?= htmlspecialchars($comm['description']) ?></p>
                                </div>
                                <p class="font-bold text-emerald-600 whitespace-nowrap">+ <?= number_format($comm['amount']) ?> Ks</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="flex justify-center items-center mt-6 space-x-2 border-t pt-4">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-left text-xs"></i></a>
                            <?php endif; ?>
                            <span class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold text-gray-700 shadow-sm"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-right text-xs"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="p-10 text-center text-gray-500 italic">
                        <i class="fas fa-box-open text-3xl mb-3 text-gray-300 block"></i>
                        <?= __('admin_commissions_no_records') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>