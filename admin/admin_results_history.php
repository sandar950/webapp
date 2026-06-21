<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin(); // Main Admin (User ID 1) သာ ဝင်ခွင့်ပြုမည်

// Date Filter
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$where_clause = " WHERE 1=1 ";
$params = [];
$types = "";

if (!empty($start_date)) {
    $where_clause .= " AND DATE(created_at) >= ? ";
    $params[] = $start_date;
    $types .= "s";
}
if (!empty($end_date)) {
    $where_clause .= " AND DATE(created_at) <= ? ";
    $params[] = $end_date;
    $types .= "s";
}

// Pagination Setup
$limit = 20; // တစ်မျက်နှာတွင် မှတ်တမ်း ၂၀ ပြသမည်
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get Total Rows for Pagination
$count_stmt = $conn->prepare("SELECT COUNT(id) as total_rows FROM result_history" . $where_clause);
if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();
$total_rows = $count_res['total_rows'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_rows / $limit);

// ပေါက်ဂဏန်း မှတ်တမ်းများကို ဆွဲထုတ်ခြင်း
$query = "SELECT result_number, type, created_at FROM result_history" . $where_clause . " ORDER BY created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param($types . "ii", ...array_merge($params, [$offset, $limit]));
$stmt->execute();
$result = $stmt->get_result();
$history = [];
if ($result) {
    $history = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();
?>

<?php 
$page_title = __('admin_result_history_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-3xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_result_history_title');
    $header_icon = "fas fa-history";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <!-- Filter Form -->
        <div class="bg-white p-4 rounded-xl shadow-md mb-6">
            <form method="GET" action="" class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2">
                <label class="text-sm font-bold text-gray-600"><?= __('admin_res_search_date') ?></label>
                <div class="flex-1 grid grid-cols-2 gap-2">
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:border-blue-500">
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm font-bold shadow-sm transition"><i class="fas fa-search"></i></button>
                <?php if(!empty($start_date) || !empty($end_date)): ?>
                    <a href="admin_results_history.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-1.5 rounded text-sm font-bold shadow-sm transition" title="<?= __('admin_users_btn_clear') ?>"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (count($history) > 0): ?>
                <!-- Mobile View: Cards -->
                <div class="md:hidden divide-y divide-gray-100">
                    <?php foreach ($history as $row): ?>
                        <div class="p-4 flex justify-between items-center">
                            <div>
                                <?php if ($row['type'] == '2D'): ?><span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full border border-blue-200 text-xs font-bold">2D</span><?php else: ?><span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full border border-purple-200 text-xs font-bold">3D</span><?php endif; ?>
                                <p class="text-[10px] text-gray-500 mt-2"><i class="far fa-clock mr-1"></i> <?= date('d-M-Y h:i A', strtotime($row['created_at'])) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500 mb-1"><?= __('admin_res_winning_number') ?></p>
                                <p class="text-3xl font-bold text-red-600 tracking-widest"><?= htmlspecialchars($row['result_number']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop View: Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full leading-normal text-left">
                        <thead>
                            <tr class="bg-blue-50 text-blue-800 font-bold border-b-2 border-blue-200">
                                <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_dash_col_time') ?></th>
                                <th class="px-5 py-4 text-sm whitespace-nowrap text-center"><?= __('admin_dash_col_type') ?></th>
                                <th class="px-5 py-4 text-sm whitespace-nowrap text-center"><?= __('admin_res_winning_number') ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($history as $row): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-5 py-4 text-sm text-gray-600 whitespace-nowrap"><i class="far fa-clock mr-1 text-gray-400"></i> <?= date('d-M-Y h:i A', strtotime($row['created_at'])) ?></td>
                                    <td class="px-5 py-4 text-sm font-bold text-center"><?php if ($row['type'] == '2D'): ?><span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full border border-blue-200">2D</span><?php else: ?><span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full border border-purple-200">3D</span><?php endif; ?></td>
                                    <td class="px-5 py-4 text-2xl font-bold text-red-600 text-center tracking-widest"><?= htmlspecialchars($row['result_number']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="px-5 py-10 text-center text-gray-500 font-bold italic">
                    <i class="fas fa-box-open text-3xl mb-3 text-gray-300 block"></i>
                    <?= __('admin_res_no_records') ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): 
            $pagination_params = "";
            if(!empty($start_date)) $pagination_params .= "&start_date=".urlencode($start_date);
            if(!empty($end_date)) $pagination_params .= "&end_date=".urlencode($end_date);
        ?>
            <div class="flex justify-center items-center mt-6 mb-2 space-x-2">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?><?= $pagination_params ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-left text-xs"></i></a><?php endif; ?>
                <span class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold text-gray-700 shadow-sm"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?><a href="?page=<?= $page + 1 ?><?= $pagination_params ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-right text-xs"></i></a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>