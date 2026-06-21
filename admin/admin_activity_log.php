<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Main Admin (role='admin') သာ ဝင်ခွင့်ပြုမည်
require_main_admin();

// Filters
$filter_admin_id = intval($_GET['admin_id'] ?? 0);
$filter_action = trim($_GET['action_type'] ?? '');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clause = " WHERE 1=1 ";
$params = [];
$types = "";

if ($filter_admin_id > 0) {
    $where_clause .= " AND l.admin_id = ? ";
    $params[] = $filter_admin_id;
    $types .= "i";
}
if (!empty($start_date)) {
    $where_clause .= " AND DATE(l.created_at) >= ? ";
    $params[] = $start_date;
    $types .= "s";
}
if (!empty($end_date)) {
    $where_clause .= " AND DATE(l.created_at) <= ? ";
    $params[] = $end_date;
    $types .= "s";
}
if (!empty($filter_action)) {
    $where_clause .= " AND l.action = ? ";
    $params[] = $filter_action;
    $types .= "s";
}

// Pagination Setup
$limit = 25;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get Total Rows for Pagination
$count_query = "SELECT COUNT(l.id) as total_rows FROM admin_activity_logs l" . $where_clause;
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total_rows'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_rows / $limit);

// Get logs
$query = "
    SELECT l.id, l.action, l.description, l.ip_address, l.created_at, u.username as admin_name 
    FROM admin_activity_logs l
    JOIN users u ON l.admin_id = u.id
    $where_clause
    ORDER BY l.created_at DESC 
    LIMIT ?, ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param($types . "ii", ...array_merge($params, [$offset, $limit]));
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all admins for filter dropdown
$admins = $conn->query("SELECT id, username FROM users WHERE role IN ('admin', 'sub_admin') ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);

// Get distinct actions for filter dropdown
$available_actions = $conn->query("SELECT DISTINCT action FROM admin_activity_logs ORDER BY action ASC")->fetch_all(MYSQLI_ASSOC);

// Export URL အတွက် Parameters များ တည်ဆောက်ခြင်း
$export_params = http_build_query([
    'type' => 'activity_logs',
    'admin_id' => $filter_admin_id,
    'action_type' => $filter_action,
    'start_date' => $start_date,
    'end_date' => $end_date
]);
?>

<?php 
$page_title = __('admin_activity_log_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-6xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_activity_log_header_title');
    $header_icon = "fas fa-clipboard-list";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <!-- Filter Form -->
        <div class="bg-white p-4 rounded-xl shadow-md mb-6">
            <form method="GET" action="" class="flex flex-col md:flex-row md:flex-wrap items-stretch md:items-center gap-4">
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 md:flex md:items-center gap-4">
                    <div>
                    <label class="text-sm font-bold text-gray-600"><?= __('admin_activity_log_filter_admin') ?></label>
                    <select name="admin_id" class="px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:border-blue-500">
                        <option value="0"><?= __('admin_activity_log_all_admins') ?></option>
                        <?php foreach($admins as $admin): ?>
                            <option value="<?= $admin['id'] ?>" <?= $filter_admin_id == $admin['id'] ? 'selected' : '' ?>><?= htmlspecialchars($admin['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    </div>
                    <div>
                    <label class="text-sm font-bold text-gray-600"><?= __('admin_activity_log_filter_action') ?></label>
                    <select name="action_type" class="px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:border-blue-500">
                        <option value=""><?= __('admin_activity_log_all_actions') ?></option>
                        <?php foreach($available_actions as $act): ?>
                            <option value="<?= htmlspecialchars($act['action']) ?>" <?= $filter_action === $act['action'] ? 'selected' : '' ?>><?= htmlspecialchars($act['action']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    </div>
                </div>
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 md:flex md:items-center gap-4">
                    <div>
                    <label class="text-sm font-bold text-gray-600"><?= __('admin_activity_log_filter_date') ?></label>
                        <div class="flex items-center gap-2">
                            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:border-blue-500">
                            <span class="text-gray-500 text-sm hidden sm:inline"><?= __('admin_users_to') ?></span>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:border-blue-500">
                        </div>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm font-bold shadow-sm transition"><i class="fas fa-filter"></i></button>
                        <a href="admin_activity_log.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-1.5 rounded text-sm font-bold shadow-sm transition" title="<?= __('admin_users_btn_clear') ?>"><i class="fas fa-times"></i></a>
                        <a href="admin_export.php?<?= htmlspecialchars($export_params) ?>" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-sm font-bold shadow-sm transition"><i class="fas fa-file-excel"></i></a>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (count($logs) > 0): ?>
                <!-- Mobile View: Cards -->
                <div class="md:hidden divide-y divide-gray-100">
                    <?php foreach ($logs as $log): ?>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <p class="font-bold text-gray-800"><?= htmlspecialchars($log['admin_name']) ?></p>
                                    <p class="text-xs text-purple-700 font-mono mt-1"><?= htmlspecialchars($log['action']) ?></p>
                                </div>
                                <p class="text-[10px] text-gray-500 text-right whitespace-nowrap"><?= date('d-M-y h:i A', strtotime($log['created_at'])) ?></p>
                            </div>
                            <p class="text-sm text-gray-600 mb-2 bg-gray-50 p-2 rounded border border-gray-200"><?= nl2br(htmlspecialchars($log['description'])) ?></p>
                            <p class="text-xs text-gray-400 text-right font-mono">IP: <?= htmlspecialchars($log['ip_address']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop View: Table -->
                <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full leading-normal text-left">
                    <thead>
                        <tr class="bg-blue-50 text-blue-800 font-bold border-b-2 border-blue-200">
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_activity_log_col_time') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_activity_log_col_admin') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_activity_log_col_action') ?></th>
                            <th class="px-5 py-4 text-sm"><?= __('admin_activity_log_col_desc') ?></th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap"><?= __('admin_activity_log_col_ip') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($logs as $log): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50 transition">
                                <td class="px-5 py-4 text-xs text-gray-600 whitespace-nowrap"><?= date('d-M-Y h:i:s A', strtotime($log['created_at'])) ?></td>
                                <td class="px-5 py-4 text-sm font-bold text-gray-800"><?= htmlspecialchars($log['admin_name']) ?></td>
                                <td class="px-5 py-4 text-sm font-mono text-purple-700"><?= htmlspecialchars($log['action']) ?></td>
                                <td class="px-5 py-4 text-sm text-gray-700"><?= nl2br(htmlspecialchars($log['description'])) ?></td>
                                <td class="px-5 py-4 text-sm text-gray-500"><?= htmlspecialchars($log['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div class="px-5 py-10 text-center text-gray-500 italic"><i class="fas fa-clipboard-list text-4xl text-gray-300 mb-3 block"></i><?= __('admin_activity_log_no_records') ?></div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): 
            $pagination_params = "";
            if($filter_admin_id > 0) $pagination_params .= "&admin_id=".urlencode($filter_admin_id);
            if(!empty($filter_action)) $pagination_params .= "&action_type=".urlencode($filter_action);
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