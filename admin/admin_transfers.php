<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_permission('can_manage_transactions');

$search_term = trim($_GET['search_term'] ?? '');
$where_clause = "1=1";
$params = [];
$types = "";

if (!empty($search_term)) {
    $search_like = "%" . $search_term . "%";
    $where_clause .= " AND (u1.username LIKE ? OR u1.phone_number LIKE ? OR u2.username LIKE ? OR u2.phone_number LIKE ?)";
    $params = [$search_like, $search_like, $search_like, $search_like];
    $types = "ssss";
}

// Pagination Setup
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get Total Rows
$count_query = "SELECT COUNT(t.id) as total_rows FROM transfers t 
                LEFT JOIN users u1 ON t.sender_id = u1.id 
                LEFT JOIN users u2 ON t.receiver_id = u2.id 
                WHERE $where_clause";
$count_stmt = $conn->prepare($count_query);
$total_rows = 0;
if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_rows = $count_stmt->get_result()->fetch_assoc()['total_rows'] ?? 0;
    $count_stmt->close();
}

$total_pages = ceil($total_rows / $limit);

// Fetch Transfers
$query = "SELECT t.id, t.amount, t.created_at, 
                 u1.username as sender_name, u1.phone_number as sender_phone,
                 u2.username as receiver_name, u2.phone_number as receiver_phone
          FROM transfers t
          LEFT JOIN users u1 ON t.sender_id = u1.id
          LEFT JOIN users u2 ON t.receiver_id = u2.id
          WHERE $where_clause
          ORDER BY t.created_at DESC
          LIMIT ?, ?";
$stmt = $conn->prepare($query);
$transfers = [];
if ($stmt) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$offset, $limit]));
    $stmt->execute();
    $transfers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<?php 
$page_title = __('admin_transfers_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-5xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_transfers_header_title');
    $header_icon = "fas fa-random text-purple-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <!-- Search Filter -->
        <div class="bg-white p-4 rounded-xl shadow-md mb-6 flex flex-col md:flex-row justify-between items-stretch md:items-center gap-4">
            <form method="GET" action="" class="flex items-center gap-2 w-full md:w-auto">
                <input type="text" name="search_term" value="<?= htmlspecialchars($search_term) ?>" placeholder="<?= __('admin_transfers_search_ph') ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-purple-500">
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition"><i class="fas fa-search"></i> <?= __('admin_users_btn_search') ?></button>
                <?php if(!empty($search_term)): ?>
                    <a href="admin_transfers.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-2 rounded-lg text-sm font-bold shadow-sm transition" title="<?= __('admin_users_btn_clear') ?>"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
            <form action="admin_export.php" method="GET" class="flex items-center gap-2 self-end">
                <input type="hidden" name="type" value="transfers">
                <select name="period" class="px-2 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-green-500">
                    <option value="all"><?= __('admin_users_period_all') ?></option>
                    <option value="today"><?= __('admin_users_period_today') ?></option>
                    <option value="this_week"><?= __('admin_users_period_week') ?></option>
                    <option value="this_month"><?= __('admin_users_period_month') ?></option>
                </select>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition whitespace-nowrap"><i class="fas fa-file-excel mr-1"></i> <?= __('admin_tx_export') ?></button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-md">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="font-bold text-gray-700"><?= __('admin_transfers_list_title') ?></h2>
                <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded font-bold"><?= sprintf(__('admin_transfers_total_count'), number_format($total_rows)) ?></span>
            </div>
            
            <?php if (count($transfers) > 0): ?>
                <!-- Mobile View: Cards -->
                <div class="md:hidden divide-y divide-gray-100">
                    <?php foreach ($transfers as $tx): ?>
                        <div class="p-4">
                            <div class="flex justify-between items-center mb-2">
                                <p class="text-base font-bold text-purple-600"><?= number_format($tx['amount']) ?> Ks</p>
                                <p class="text-[10px] text-gray-500"><i class="far fa-clock mr-1"></i> <?= date('d-M-Y h:i A', strtotime($tx['created_at'])) ?></p>
                            </div>
                            <div class="flex items-center text-sm">
                                <div class="w-1/2 pr-2 border-r border-gray-200">
                                    <p class="text-xs text-gray-500"><?= __('admin_transfers_col_sender') ?></p>
                                    <p class="font-bold text-gray-800 truncate"><?= htmlspecialchars($tx['sender_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($tx['sender_phone']) ?></p>
                                </div>
                                <div class="w-1/2 pl-2">
                                    <p class="text-xs text-gray-500"><?= __('admin_transfers_col_receiver') ?></p>
                                    <p class="font-bold text-gray-800 truncate"><?= htmlspecialchars($tx['receiver_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($tx['receiver_phone']) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop View: Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full leading-normal text-left">
                        <thead>
                            <tr class="bg-purple-50 text-purple-800 font-bold border-b-2 border-purple-200">
                                <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_dash_col_time') ?></th>
                                <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_transfers_col_sender') ?></th>
                                <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_transfers_col_receiver') ?></th>
                                <th class="px-5 py-4 text-sm whitespace-nowrap text-right"><?= __('admin_transfers_col_amount') ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($transfers as $tx): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-5 py-4 text-xs text-gray-500 whitespace-nowrap"><i class="far fa-clock mr-1"></i> <?= date('d-M-Y h:i A', strtotime($tx['created_at'])) ?></td>
                                    <td class="px-5 py-4 text-sm font-bold text-gray-800"><?= htmlspecialchars($tx['sender_name']) ?> <span class="text-xs text-gray-500 font-normal">(<?= htmlspecialchars($tx['sender_phone']) ?>)</span></td>
                                    <td class="px-5 py-4 text-sm font-bold text-gray-800"><?= htmlspecialchars($tx['receiver_name']) ?> <span class="text-xs text-gray-500 font-normal">(<?= htmlspecialchars($tx['receiver_phone']) ?>)</span></td>
                                    <td class="px-5 py-4 text-base font-bold text-purple-600 text-right whitespace-nowrap"><?= number_format($tx['amount']) ?> Ks</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="px-5 py-10 text-center text-gray-500 italic">
                    <i class="fas fa-exchange-alt text-4xl text-gray-300 mb-3 block"></i>
                    <?= __('admin_transfers_no_records') ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): 
            $pagination_params = "";
            if(!empty($search_term)) $pagination_params .= "&search_term=".urlencode($search_term);
        ?>
            <div class="flex justify-center items-center mt-6 mb-2 space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $pagination_params ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-left text-xs"></i></a>
                <?php endif; ?>
                
                <span class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold text-gray-700 shadow-sm"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $pagination_params ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 shadow-sm transition"><i class="fas fa-chevron-right text-xs"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>