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

// User အမည်ကို {username} ဖြင့် အစားထိုးရန်အတွက် ဆွဲထုတ်ခြင်း
$user_stmt = $conn->prepare("SELECT username, notifications FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result()->fetch_assoc();
$current_username = $user_res['username'] ?? '';
$current_user_noti_count = $user_res['notifications'] ?? 0;
$user_stmt->close();

// ဤစာမျက်နှာသို့ ရောက်ရှိလာပါက Noti Badge ကို အလိုအလျောက် ရှင်းလင်းမည်
if ($current_user_noti_count > 0) { 
    $stmt_clear_badge = $conn->prepare("UPDATE users SET notifications = 0 WHERE id = ?");
    $stmt_clear_badge->bind_param("i", $user_id);
    $stmt_clear_badge->execute();
    $stmt_clear_badge->close();
}

// လွန်ခဲ့သော (၁၄) ရက်ထက် ကျော်လွန်နေသော အသိပေးချက်အဟောင်းများကို အလိုအလျောက် ဖျက်သိမ်းမည် (Auto Cleanup)
$stmt_cleanup = $conn->prepare("DELETE FROM system_notifications WHERE user_id = ? AND created_at < NOW() - INTERVAL 14 DAY");
$stmt_cleanup->bind_param("i", $user_id);
$stmt_cleanup->execute();
$stmt_cleanup->close();

$success_message = "";
// အားလုံးကို ဖတ်ပြီးသားအဖြစ် သတ်မှတ်ရန် (Mark all as read)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $imp = $_POST['important_only'] ?? '';
    $w_img = $_POST['with_images'] ?? '';
    
    $params = [$user_id];
    $types = "i";
    $sql = "UPDATE system_notifications SET is_read = 1 WHERE (user_id = ? OR user_id IS NULL)";

    if (!empty($start)) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $start;
        $types .= "s";
    }
    if (!empty($end)) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $end;
        $types .= "s";
    }
    if ($imp == '1') {
        $sql .= " AND is_important = 1";
    }
    if ($w_img == '1') {
        $sql .= " AND (image_url IS NOT NULL AND image_url != '')";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }
    
    // Filter မရှိမှသာ Noti Badge ကို ၀ သို့ ချမည်
    if (empty($start) && empty($end) && $imp != '1' && $w_img != '1') {
        $stmt_noti = $conn->prepare("UPDATE users SET notifications = 0 WHERE id = ?");
        $stmt_noti->bind_param("i", $user_id);
        $stmt_noti->execute();
        $stmt_noti->close();
    }
    $success_message = __('noti_all_read_success');
    // After marking all as read, update the current_user_noti_count for immediate display
    $current_user_noti_count = 0;
}

// အားလုံးကို ဖျက်ပစ်ရန် (Delete All)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all') {
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $imp = $_POST['important_only'] ?? '';
    $w_img = $_POST['with_images'] ?? '';

    $params = [$user_id];
    $types = "i";
    $sql = "DELETE FROM system_notifications WHERE user_id = ?";

    if (!empty($start)) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $start;
        $types .= "s";
    }
    if (!empty($end)) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $end;
        $types .= "s";
    }
    if ($imp == '1') {
        $sql .= " AND is_important = 1";
    }
    if ($w_img == '1') {
        $sql .= " AND (image_url IS NOT NULL AND image_url != '')";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }
    
    if (empty($start) && empty($end) && $imp != '1' && $w_img != '1') {
        $stmt_noti = $conn->prepare("UPDATE users SET notifications = 0 WHERE id = ?");
        $stmt_noti->bind_param("i", $user_id);
        $stmt_noti->execute();
        $stmt_noti->close();
    }
    $success_message = __('noti_all_deleted_success');
    // After deleting all, update the current_user_noti_count for immediate display
    $current_user_noti_count = 0;
}

// တစ်ခုချင်းစီ ဖျက်ရန် (Delete Single)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_single') {
    $noti_id = intval($_POST['noti_id'] ?? 0);
    if ($noti_id > 0) {
        $stmt = $conn->prepare("DELETE FROM system_notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $noti_id, $user_id);
        $stmt->execute();
        $stmt->close();
        $success_message = __('noti_single_deleted_success');
        // After deleting a single, update the current_user_noti_count for immediate display
        $current_user_noti_count = max(0, $current_user_noti_count - 1);
    }
}

// AJAX: တစ်ခုချင်းစီ ဖျက်ရန် (Delete Single)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajax_delete') {
    header('Content-Type: application/json');
    $noti_id = intval($_POST['noti_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM system_notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $noti_id, $user_id);
    $res = $stmt->execute(); // $res will be true/false based on query success
    $rows_affected = $stmt->affected_rows; // Check if any row was actually deleted
    $stmt->close();

    $new_noti_count = 0;
    if ($res && $rows_affected > 0) {
        $update_stmt = $conn->prepare("UPDATE users SET notifications = GREATEST(0, notifications - 1) WHERE id = ?");
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $count_stmt = $conn->prepare("SELECT notifications FROM users WHERE id = ?");
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $new_noti_count = $count_stmt->get_result()->fetch_assoc()['notifications'] ?? 0;
        $count_stmt->close();
    }
    echo json_encode(['success' => $res, 'new_noti_count' => $new_noti_count]);
    exit();
}

// AJAX: တစ်ခုချင်းစီ ဖတ်ပြီးသားလုပ်ရန် (Mark as Read)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    header('Content-Type: application/json');
    $noti_id = intval($_POST['noti_id'] ?? 0);
    
    $conn->begin_transaction();
    try {
        // 1. Mark the specific notification as read
        $stmt = $conn->prepare("UPDATE system_notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL) AND is_read = 0");
        $stmt->bind_param("ii", $noti_id, $user_id);
        $stmt->execute();
        $rows_affected = $stmt->affected_rows; // Check if any row was actually updated
        $stmt->close();

        $new_noti_count = $current_user_noti_count; // Default to current count
        if ($rows_affected > 0) {
            // 2. Decrement user's unread notification count if a notification was marked as read
            $update_stmt = $conn->prepare("UPDATE users SET notifications = GREATEST(0, notifications - 1) WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // 3. Get the updated notification count
            $count_stmt = $conn->prepare("SELECT notifications FROM users WHERE id = ?");
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $new_noti_count = $count_stmt->get_result()->fetch_assoc()['notifications'] ?? 0;
            $count_stmt->close();
        } else {
            // If no rows were affected, it means it was already read or not found/owned by user
            // Still get the current count to send back
            $count_stmt = $conn->prepare("SELECT notifications FROM users WHERE id = ?");
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $new_noti_count = $count_stmt->get_result()->fetch_assoc()['notifications'] ?? 0;
            $count_stmt->close();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'new_noti_count' => $new_noti_count]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// AJAX: အစီအစဉ်အသစ်ကို သိမ်းဆည်းရန် (Update Order)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order') {
    header('Content-Type: application/json');
    $order_data = json_decode($_POST['order'], true);
    $success = true;
    foreach ($order_data as $item) {
        $stmt = $conn->prepare("UPDATE system_notifications SET sort_order = ? WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
        $stmt->bind_param("iii", $item['sort_order'], $item['id'], $user_id);
        if (!$stmt->execute()) { $success = false; }
        $stmt->close();
    }
    echo json_encode(['success' => $success]);
    exit();
}

// Filter: Setup
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] == '1';
$important_only = isset($_GET['important_only']) && $_GET['important_only'] == '1';
$with_images = isset($_GET['with_images']) && $_GET['with_images'] == '1';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$filter_sql = "";
$filter_params = [];
$filter_types = "";

if ($unread_only) {
    $filter_sql .= " AND is_read = 0";
}
if ($important_only) {
    $filter_sql .= " AND is_important = 1";
}
if ($with_images) {
    $filter_sql .= " AND (image_url IS NOT NULL AND image_url != '')";
}
if (!empty($start_date)) {
    $filter_sql .= " AND DATE(created_at) >= ?";
    $filter_params[] = $start_date;
    $filter_types .= "s";
}
if (!empty($end_date)) {
    $filter_sql .= " AND DATE(created_at) <= ?";
    $filter_params[] = $end_date;
    $filter_types .= "s";
}

// Build URL parameters for pagination links
$url_params = http_build_query(array_filter([
    'unread_only' => $_GET['unread_only'] ?? null,
    'important_only' => $_GET['important_only'] ?? null,
    'with_images' => $_GET['with_images'] ?? null,
    'start_date' => $_GET['start_date'] ?? null,
    'end_date' => $_GET['end_date'] ?? null,
]));
$filter_param = !empty($url_params) ? "&" . $url_params : "";

// Pagination Setup
$limit = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Get Total Rows for Pagination
$count_sql = "SELECT COUNT(id) as total_rows FROM system_notifications WHERE (user_id IS NULL OR user_id = ?) " . $filter_sql;
$count_stmt = $conn->prepare($count_sql);
$all_count_params = array_merge([$user_id], $filter_params);
$all_count_types = "i" . $filter_types;
$count_stmt->bind_param($all_count_types, ...$all_count_params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total_rows'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_rows / $limit);

// Fetch notifications from the database
$data_sql = "SELECT * FROM system_notifications WHERE (user_id IS NULL OR user_id = ?) " . $filter_sql . " ORDER BY sort_order ASC, created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($data_sql);
$all_data_params = array_merge([$user_id], $filter_params, [$offset, $limit]);
$all_data_types = "i" . $filter_types . "ii";
$stmt->bind_param($all_data_types, ...$all_data_params);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php 
$page_title = __('title_notifications') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>
<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24 flex flex-col">
    <style>
        /* Collapse Animation Styles */
        .notification-wrapper { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; max-height: 1000px; }
        .notification-wrapper.collapsed { max-height: 0 !important; opacity: 0 !important; margin-top: 0 !important; margin-bottom: 0 !important; padding-top: 0 !important; padding-bottom: 0 !important; }

        /* Swipe Smoothness: Enable Hardware Acceleration */
        .notification-card { will-change: transform; backface-visibility: hidden; perspective: 1000px; transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease; }

        /* Background Icon Initial State */
        .swipe-icon { transform: scale(0.6); opacity: 0; transition: transform 0.1s ease, opacity 0.1s ease; }

        /* Drag and Drop Styles */
        .drag-handle { cursor: grab; padding: 10px 5px; color: #cbd5e1; display: flex; align-items: center; justify-content: center; }
        .drag-handle:active { cursor: grabbing; }
        .sortable-ghost { opacity: 0.4; background: #ebf5ff; border: 2px dashed #3b82f6; }

        /* Undo Progress Bar Animation */
        .undo-progress-bar { height: 3px; background: #fac215; position: absolute; bottom: 0; left: 0; width: 100%; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; animation: undo-timer 10s linear forwards; }
        @keyframes undo-timer { from { width: 100%; } to { width: 0%; } }
    </style>

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md relative w-full">
        <a href="index.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center tracking-wide"><?= __('title_notifications') ?></h1>
        <?php 
            // Fetch current user's notification count for the header badge
            $user_noti_count_stmt = $conn->prepare("SELECT notifications FROM users WHERE id = ?");
            $user_noti_count_stmt->bind_param("i", $user_id);
            $user_noti_count_stmt->execute();
            $current_user_noti_count = $user_noti_count_stmt->get_result()->fetch_assoc()['notifications'] ?? 0;
            $user_noti_count_stmt->close();
            $params = $_GET;
            $params['unread_only'] = $unread_only ? '0' : '1';
            unset($params['page']); // Filter ပြောင်းလျှင် ပထမစာမျက်နှာသို့ ပြန်ပို့မည်
            $toggle_unread_url = "?" . http_build_query($params);
        ?>
        <div class="flex items-center gap-1 md:gap-3">
            <a href="<?= $toggle_unread_url ?>" class="p-2 md:p-2.5 <?= $unread_only ? 'text-yellow-400 bg-white/10 rounded-full' : 'text-white' ?> hover:text-gray-200 hover:bg-white/10 rounded-full transition-all" title="<?= $unread_only ? __('show_all') : __('show_unread_only') ?>">
                <i class="fas <?= $unread_only ? 'fa-eye-slash' : 'fa-eye' ?> text-lg md:text-xl"></i>
            </a>
            <button type="button" onclick="document.getElementById('filterSection').classList.toggle('hidden')" class="p-2 md:p-2.5 <?= ($unread_only || $important_only || $with_images || !empty($start_date) || !empty($end_date)) ? 'text-yellow-400 bg-white/10 rounded-full' : 'text-white' ?> hover:text-gray-200 hover:bg-white/10 rounded-full transition-all" title="<?= __('filter') ?>">
                <i class="fas fa-filter text-lg md:text-xl"></i>
            </button>
            <form method="POST" action="" class="flex-none">
                <input type="hidden" name="action" value="mark_all_read">
                <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                <input type="hidden" name="important_only" value="<?= $important_only ? '1' : '0' ?>">
                <input type="hidden" name="with_images" value="<?= $with_images ? '1' : '0' ?>">
                <button type="submit" class="text-white hover:text-gray-200 hover:bg-white/10 rounded-full transition-all p-2 md:p-2.5" title="<?= __('mark_all_read') ?>">
                    <i class="fas fa-check-double text-lg md:text-xl"></i>
                </button>
            </form>
            <form method="POST" action="" class="flex-none" onsubmit="return confirm('<?= addslashes(__('confirm_delete_all_noti')) ?>');">
                <input type="hidden" name="action" value="delete_all">
                <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                <input type="hidden" name="important_only" value="<?= $important_only ? '1' : '0' ?>">
                <input type="hidden" name="with_images" value="<?= $with_images ? '1' : '0' ?>">
                <button type="submit" class="text-white hover:text-red-300 hover:bg-white/10 rounded-full transition-all p-2 md:p-2.5" title="<?= __('delete_all') ?>">
                    <i class="fas fa-trash-alt text-lg md:text-xl"></i>
                </button>
            </form>
        </div>
    </div>

    <div id="filterSection" class="<?= ($unread_only || !empty($start_date) || !empty($end_date)) ? '' : 'hidden' ?> bg-white p-4 md:p-6 border-b shadow-inner md:rounded-b-2xl">
        <form method="GET" action="" class="space-y-4 md:space-y-5 max-w-3xl mx-auto">
            <div class="flex flex-col md:flex-row gap-3 md:gap-4">
                <div class="flex-1">
                    <label class="block text-xs md:text-sm font-bold text-gray-500 mb-1.5"><?= __('start_date') ?></label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="w-full px-3 md:px-4 py-2.5 md:py-3 border border-gray-300 rounded-xl text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-primary transition-all text-gray-700">
                </div>
                <div class="flex-1">
                    <label class="block text-xs md:text-sm font-bold text-gray-500 mb-1.5"><?= __('end_date') ?></label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="w-full px-3 md:px-4 py-2.5 md:py-3 border border-gray-300 rounded-xl text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-primary transition-all text-gray-700">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-2 mb-3 bg-gray-50 p-3 md:p-4 rounded-xl border border-gray-100">
                <div class="flex flex-wrap gap-4 md:gap-6">
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="unread_only" value="1" <?= $unread_only ? 'checked' : '' ?> class="w-4 h-4 md:w-5 md:h-5 text-primary border-gray-300 rounded focus:ring-primary">
                        <span class="ml-2 text-xs md:text-sm font-bold text-gray-600 group-hover:text-primary transition-colors"><?= __('unread_only') ?></span>
                    </label>
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="important_only" value="1" <?= $important_only ? 'checked' : '' ?> class="w-4 h-4 md:w-5 md:h-5 text-red-500 border-gray-300 rounded focus:ring-red-500">
                        <span class="ml-2 text-xs md:text-sm font-bold text-red-600 group-hover:text-red-700 transition-colors"><?= __('important_only') ?></span>
                    </label>
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="with_images" value="1" <?= $with_images ? 'checked' : '' ?> class="w-4 h-4 md:w-5 md:h-5 text-blue-500 border-gray-300 rounded focus:ring-blue-500">
                        <span class="ml-2 text-xs md:text-sm font-bold text-blue-600 group-hover:text-blue-700 transition-colors"><?= __('with_images_only') ?></span>
                    </label>
                </div>
            </div>
            <div class="flex flex-col md:flex-row items-center justify-between border-t border-gray-100 pt-4 gap-4">
                <div class="flex gap-2 w-full md:w-auto">
                    <button type="button" onclick="setQuickDate('<?= date('Y-m-d') ?>')" class="flex-1 md:flex-none text-xs md:text-sm bg-gray-100 px-3 md:px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-200 hover:shadow-sm font-medium transition-all"><?= __('today') ?></button>
                    <button type="button" onclick="setQuickDate('<?= date('Y-m-d', strtotime('-1 day')) ?>')" class="flex-1 md:flex-none text-xs md:text-sm bg-gray-100 px-3 md:px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-200 hover:shadow-sm font-medium transition-all"><?= __('yesterday') ?></button>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto justify-end">
                    <a href="notifications.php" class="text-xs md:text-sm text-gray-500 hover:text-red-500 py-2 px-3 font-bold transition-colors"><?= __('clear_filter') ?></a>
                    <button type="submit" class="bg-primary hover:bg-blue-800 text-white py-2 md:py-2.5 px-6 md:px-8 rounded-xl text-sm md:text-base font-bold shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300"><i class="fas fa-search mr-1.5"></i> <?= __('search') ?></button>
                </div>
            </div>
        </form>
    </div>

    <div class="p-4 md:p-8 flex-1 w-full mx-auto max-w-4xl">
        <?php if ($success_message): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 md:px-6 py-3 md:py-4 rounded-xl mb-5 text-sm md:text-base font-bold text-center shadow-sm flex justify-center items-center gap-2"><i class="fas fa-check-circle"></i> <?= $success_message ?></div><?php endif; ?>

        <?php if (count($notifications) > 0): ?>
            <?php
                // စာမျက်နှာထဲတွင် မဖတ်ရသေးသော Noti များ ရှိ/မရှိ စစ်ဆေးခြင်း
                $unread_check_stmt = $conn->prepare("SELECT COUNT(id) as unread_count FROM system_notifications WHERE (user_id IS NULL OR user_id = ?) AND is_read = 0");
                $unread_check_stmt->bind_param("i", $user_id);
                $unread_check_stmt->execute();
                $has_unread = $unread_check_stmt->get_result()->fetch_assoc()['unread_count'] > 0;
                $unread_check_stmt->close();
            ?>
            <div class="flex flex-col md:flex-row justify-between md:items-center mb-5 md:mb-6 gap-3 md:gap-4 bg-white p-3 md:p-4 rounded-2xl border border-gray-100 shadow-sm">
                <div class="flex flex-wrap items-center gap-2 md:gap-3">
                    <div class="flex items-center gap-1.5 bg-gray-50 hover:bg-gray-100 px-3 md:px-4 py-2 rounded-xl border border-gray-200 transition-colors">
                        <i class="fas fa-tachometer-alt text-gray-400 text-xs md:text-sm"></i>
                        <select id="ttsSpeed" class="text-xs md:text-sm text-gray-700 bg-transparent focus:outline-none font-bold cursor-pointer" onchange="localStorage.setItem('ttsSpeed', this.value)">
                            <option value="0.5">0.5x</option>
                            <option value="0.75">0.75x</option>
                            <option value="0.9" selected>0.9x <?= __('normal_speed') ?></option>
                            <option value="1.0">1.0x</option>
                            <option value="1.25">1.25x</option>
                            <option value="1.5">1.5x</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-1.5 bg-gray-50 hover:bg-gray-100 px-3 md:px-4 py-2 rounded-xl border border-gray-200 transition-colors hidden sm:flex">
                        <i class="fas fa-microphone-alt text-gray-400 text-xs md:text-sm"></i>
                        <select id="ttsVoice" class="text-xs md:text-sm text-gray-700 bg-transparent focus:outline-none font-bold cursor-pointer w-24 md:w-32 truncate" onchange="localStorage.setItem('ttsVoice', this.value)">
                            <option value="">Default Voice</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 bg-gray-50 hover:bg-blue-50 px-3 md:px-4 py-2 rounded-xl border border-gray-200 cursor-pointer transition-colors group">
                        <input type="checkbox" id="ttsAutoPlay" class="w-4 h-4 md:w-5 md:h-5 text-primary border-gray-300 rounded focus:ring-primary" onchange="localStorage.setItem('ttsAutoPlay', this.checked)">
                        <span class="text-xs md:text-sm text-gray-600 font-bold group-hover:text-blue-700 transition-colors">Auto-play</span>
                    </label>
                    <button type="button" onclick="playAllUnread()" class="text-xs md:text-sm bg-blue-50 px-3 md:px-5 py-2 rounded-xl border border-blue-200 text-blue-700 hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all font-bold flex items-center gap-1.5 shadow-sm" title="<?= __('read_all_unread_tooltip') ?>">
                        <i class="fas fa-play-circle text-sm md:text-base"></i> <span class="hidden sm:inline"><?= __('read_aloud') ?></span>
                    </button>
                </div>
                <?php if ($has_unread): ?>
                <div class="animate__animated animate__fadeIn flex-shrink-0 w-full md:w-auto">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="mark_all_read">
                        <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        <input type="hidden" name="important_only" value="<?= $important_only ? '1' : '0' ?>">
                        <input type="hidden" name="with_images" value="<?= $with_images ? '1' : '0' ?>">
                        <button type="submit" class="w-full md:w-auto text-xs md:text-sm bg-green-50 text-green-700 border border-green-200 hover:bg-green-600 hover:text-white hover:border-green-600 font-bold py-2.5 md:py-2.5 px-4 md:px-5 rounded-xl transition-all shadow-sm flex items-center justify-center gap-1.5 active:scale-95" title="<?= __('mark_all_read_tooltip') ?>">
                            <i class="fas fa-check-double text-sm md:text-base"></i> <?= __('mark_all_read_btn') ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="space-y-3 md:space-y-4" id="notificationList">
                <?php foreach ($notifications as $noti): ?>
                    <div class="notification-wrapper relative" data-id="<?= $noti['id'] ?>">
                        <div class="absolute inset-0 rounded-xl md:rounded-2xl overflow-hidden flex justify-between items-center z-0">
                            <div id="swipeReadBg" class="absolute inset-y-0 left-0 w-1/2 flex items-center pl-6 md:pl-8 text-white bg-transparent transition-colors duration-200">
                                <i class="fas fa-check-double text-2xl md:text-3xl swipe-icon"></i>
                            </div>
                            <div id="swipeDeleteBg" class="absolute inset-y-0 right-0 w-1/2 flex items-center justify-end pr-6 md:pr-8 text-white bg-transparent transition-colors duration-200">
                                <i class="fas fa-trash-alt text-2xl md:text-3xl swipe-icon"></i>
                            </div>
                        </div>

                        <div class="notification-card bg-white p-4 md:p-6 rounded-xl md:rounded-2xl shadow-sm border border-l-4 <?= (isset($noti['is_important']) && $noti['is_important']) ? 'border-l-red-500 bg-red-50/50 shadow-md' : ((isset($noti['is_read']) && $noti['is_read']) ? 'border-gray-200 border-l-gray-300 opacity-70' : 'border-l-primary hover:shadow-md') ?> group transition-all duration-300 relative z-10 flex gap-3 md:gap-4"
                             data-noti-id="<?= $noti['id'] ?>"
                             data-is-read="<?= (isset($noti['is_read']) && $noti['is_read']) ? '1' : '0' ?>"
                             data-can-delete="<?= ($noti['user_id'] !== NULL) ? 'true' : 'false' ?>">
                            
                            <div class="drag-handle shrink-0 self-center hidden sm:flex text-gray-300 hover:text-gray-400 cursor-grab md:pr-2">
                                <i class="fas fa-grip-vertical text-lg md:text-xl"></i>
                            </div>

                            <div class="flex-1 min-w-0">
                                <?php if (isset($noti['is_important']) && $noti['is_important']): ?>
                                    <span class="absolute top-3 right-3 md:top-4 md:right-4 text-red-700 text-[10px] md:text-xs font-black px-2.5 py-1 bg-red-100 rounded-md border border-red-200 animate-pulse shadow-sm flex items-center gap-1"><i class="fas fa-exclamation-triangle"></i> <?= __('important') ?></span>
                                <?php endif; ?>

                                <?php if (!empty($noti['image_url']) && file_exists($noti['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($noti['image_url']) ?>" alt="Notification Image" class="w-full md:max-w-sm h-auto rounded-lg md:rounded-xl mb-3 md:mb-4 object-cover shadow-sm border border-gray-100 hover:opacity-90 cursor-pointer transition-opacity" onclick="showImageModal('<?= htmlspecialchars($noti['image_url']) ?>')">
                                <?php endif; ?>
                                <div class="flex justify-between items-start gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="<?= (isset($noti['is_important']) && $noti['is_important']) ? 'text-red-900 font-bold' : 'text-gray-800' ?> text-sm md:text-base leading-relaxed mb-3 pr-16 md:pr-20">
                                            <?php 
                                                // User အမည်ကို {username} ဖြင့် အစားထိုးခြင်း
                                                $raw_message = $noti['message'];
                                                $personalized_message = str_replace('{username}', $current_username, $raw_message);
                                                
                                                // XSS ကာကွယ်ရန် Message ကို အရင်ဆုံး escape လုပ်ပါမည်။
                                                $safe_message = htmlspecialchars($personalized_message, ENT_QUOTES, 'UTF-8');
                                                // ထို့နောက် လင့်ခ်များကို ရှာဖွေပြီး နှိပ်နိုင်သော <a> tag များအဖြစ် ပြောင်းလဲပါမည်။
                                                $url_pattern = '/(https?:\/\/[^\s<>"\'`]+)/i';
                                                $msg_with_links = preg_replace($url_pattern, '<a href="$1" target="_blank" class="text-blue-600 hover:text-blue-800 underline font-bold transition-colors" rel="noopener noreferrer">$1</a>', $safe_message);
                                                echo nl2br($msg_with_links);
                                            ?>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3 mt-2 border-t border-gray-100/50 pt-2">
                                            <p class="text-[10px] md:text-xs text-gray-400 font-medium flex items-center gap-1.5"><i class="far fa-clock"></i> <?= date('d-M-Y h:i A', strtotime($noti['created_at'])) ?></p>
                                            <button type="button" onclick="speakText(this, '<?= htmlspecialchars(strip_tags(str_replace(["\r", "\n"], ' ', $personalized_message)), ENT_QUOTES, 'UTF-8') ?>')" class="text-[10px] md:text-xs text-blue-600 hover:text-white bg-blue-50 hover:bg-blue-600 font-bold transition-all flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-blue-200 hover:border-blue-600 shadow-sm" title="<?= __('listen') ?>">
                                                <i class="fas fa-volume-up"></i> <?= __('listen') ?>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <?php if ($noti['user_id'] !== NULL): ?>
                                        <div class="flex-none hidden md:flex items-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                            <button type="button" onclick="confirmDelete(<?= $noti['id'] ?>, this.closest('.notification-wrapper'))" class="text-gray-400 hover:text-red-500 bg-gray-50 hover:bg-red-50 p-2.5 rounded-full border border-gray-200 hover:border-red-200 transition-all shadow-sm group/btn" title="<?= __('delete') ?>">
                                                <i class="fas fa-trash-alt text-base group-hover/btn:scale-110 transition-transform"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl md:rounded-3xl shadow-sm border border-gray-100 p-12 md:p-20 text-center mt-6 max-w-2xl mx-auto flex flex-col items-center justify-center">
                <div class="w-24 h-24 md:w-32 md:h-32 bg-gray-50 rounded-full flex items-center justify-center mb-5 md:mb-6 shadow-inner border border-gray-100">
                    <i class="fas fa-bell-slash text-4xl md:text-6xl text-gray-300 block animate-pulse"></i>
                </div>
                <p class="text-gray-500 text-base md:text-lg font-bold"><?= __('no_new_notifications') ?></p>
                <p class="text-gray-400 text-sm mt-2"><?= __('notification_desc') ?></p>
            </div>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center items-center mt-8 md:mt-10 mb-4 space-x-2 md:space-x-3">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $filter_param ?>" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-gray-600 hover:bg-gray-50 hover:text-primary hover:border-primary shadow-sm transition-all duration-200"><i class="fas fa-chevron-left text-xs md:text-sm"></i></a>
                <?php endif; ?>
                
                <span class="px-4 md:px-6 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-sm md:text-base font-bold text-gray-700 shadow-sm"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $filter_param ?>" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-gray-600 hover:bg-gray-50 hover:text-primary hover:border-primary shadow-sm transition-all duration-200"><i class="fas fa-chevron-right text-xs md:text-sm"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <script>
        // Undo Logic အတွက် Global Variables များ
        let undoTimeout = null;
        let pendingNotiId = null;
        let pendingWrapper = null;

        // ပုံကို အကြီးချဲ့ကြည့်ရန် Modal Function
        function showImageModal(url) {
            Swal.fire({
                imageUrl: url,
                imageAlt: 'Notification Image',
                showConfirmButton: false,
                showCloseButton: true,
                background: 'transparent',
                backdrop: 'rgba(0,0,0,0.9)',
                width: 'auto',
                customClass: { image: 'rounded-xl max-h-[85vh] object-contain shadow-2xl' }
            });
        }

        // AJAX ဖြင့် ဖတ်ပြီးသားမှတ်သားရန် Function
        function markAsRead(event, card, notiId, force = false) {
            // Delete ခလုတ်ကို နှိပ်လျှင် (သို့မဟုတ်) Swipe လုပ်နေလျှင် markAsRead မလုပ်ရန်
            if (!force && (window.isSwiping || (event && (event.target.closest('button') || event.target.closest('form'))))) return;
            
            // ဖတ်ပြီးသားဖြစ်နေလျှင် သို့မဟုတ် လက်ရှိ လုပ်ဆောင်နေလျှင် ထပ်မလုပ်ရန်
            if (card.dataset.isRead === '1' || card.classList.contains('processing')) return;

            card.classList.add('processing'); // Double-click ကာကွယ်ရန်

            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('noti_id', notiId);

            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                card.classList.remove('processing');
                if (data.success) {
                    // UI ပြောင်းလဲခြင်း: Unread Indicators (အပြာရောင်/အနီရောင် ဘောင်များ) အားလုံးဖယ်ရှားမည်
                    card.classList.remove('border-l-primary', 'border-l-red-500', 'bg-red-50/50', 'shadow-md', 'hover:shadow-md');
                    card.classList.add('border-gray-200', 'border-l-gray-300', 'opacity-70');
                    
                    // အရေးကြီး Noti ဖြစ်ပါက Pulse animation ကိုပါ ရပ်တန့်မည်
                    const urgentBadge = card.querySelector('.animate-pulse');
                    if (urgentBadge) urgentBadge.classList.remove('animate-pulse');

                    card.dataset.isRead = '1';
                    if (navigator.vibrate) navigator.vibrate(40);
                    
                    // Update Header Badge
                    const notiBadge = document.getElementById('headerNotiBadge');
                    if (notiBadge) {
                        if (data.new_noti_count > 0) {
                            notiBadge.textContent = data.new_noti_count;
                        } else {
                            notiBadge.classList.add('hidden');
                        }
                    }
                }
            })
            .catch(err => {
                card.classList.remove('processing');
                console.error('Error:', err);
            });
        }

        // Full Swipe အတွက် Snackbar မပြဘဲ တိုက်ရိုက်ဖျက်မည့် Function
        function directDeleteAJAX(notiId, wrapper) {
            if (undoTimeout) finalizeDelete(); // အရင်ရှိနေသော pending ကို အပြီးသတ်မည်

            wrapper.style.maxHeight = wrapper.offsetHeight + 'px';
            setTimeout(() => {
                wrapper.classList.add('collapsed');
            }, 10);

            // Animation ပြသပြီးသည်နှင့် Snackbar မပြဘဲ AJAX ချက်ချင်းပို့မည်
            setTimeout(() => {
                const formData = new FormData();
                formData.append('action', 'ajax_delete');
                formData.append('noti_id', notiId);

                fetch('notifications.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        wrapper.remove();
                        
                        // Header မှ Notification Badge Count ကို Update လုပ်မည်
                        const notiBadge = document.getElementById('headerNotiBadge');
                        if (notiBadge) {
                            if (data.new_noti_count > 0) {
                                notiBadge.textContent = data.new_noti_count;
                                notiBadge.classList.remove('hidden');
                            } else {
                                notiBadge.classList.add('hidden');
                            }
                        }

                        if (document.querySelectorAll('.notification-wrapper').length === 0) location.reload();
                    }
                })
                .catch(err => console.error('Error:', err));
            }, 400);
        }

        // AJAX ဖြင့် ဖျက်သိမ်းရန် Function (Undo Snackbar နှင့်အတူ)
        function deleteNotificationAJAX(notiId, wrapper) {
            // ၁။ အရင် pending ဖြစ်နေသည်များကို အပြီးသတ်ဖျက်မည်
            if (undoTimeout) finalizeDelete();

            // Animation အလုပ်လုပ်နိုင်ရန် လက်ရှိ height ကို အရင်သတ်မှတ်ပေးမည်
            wrapper.style.maxHeight = wrapper.offsetHeight + 'px';
            
            // ၂။ UI မှ ခေတ္တဖျောက်ထားမည်
            setTimeout(() => {
                wrapper.classList.add('collapsed');
            }, 10);

            // ၃။ Snackbar ပြသရန်အတွက် အချက်အလက်များ သိမ်းမည်
            pendingNotiId = notiId;
            pendingWrapper = wrapper;

            // ၄။ Snackbar ကို ဖော်မည်
            showUndoSnackbar();

            // ၅.၁။ Snackbar ပေါ်လာသည်နှင့် ဖုန်းကို အဆက်မပြတ် တုန်ခါစေမည်
            if (navigator.vibrate) {
                const vibrateDuration = 10000; // Snackbar duration (10 seconds)
                const segmentDuration = 50; // 50ms vibrate, 50ms pause
                const pattern = [];
                for (let i = 0; i < vibrateDuration / (segmentDuration * 2); i++) {
                    pattern.push(segmentDuration); // Vibrate
                    pattern.push(segmentDuration); // Pause
                }
                navigator.vibrate(pattern);
            }

            // ၅။ ၅ စက္ကန့်ကြာလျှင် အလိုအလျောက် အပြီးတိုင်ဖျက်မည်
            undoTimeout = setTimeout(() => {
                finalizeDelete();
            }, 5000);
        }

        function showUndoSnackbar() {
            const existing = document.getElementById('undoSnackbar');
            if (existing) existing.remove();

            const snackbar = document.createElement('div');
            snackbar.id = 'undoSnackbar';
            snackbar.className = 'fixed bottom-24 md:bottom-10 left-1/2 -translate-x-1/2 z-[70] w-[90%] md:w-auto md:min-w-[350px] max-w-sm md:max-w-md bg-gray-800 text-white px-5 md:px-6 py-4 rounded-2xl shadow-2xl flex justify-between items-center animate__animated animate__fadeInUp overflow-hidden border border-gray-700';
            snackbar.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center text-red-400">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <span class="text-sm md:text-base font-bold"><?= addslashes(__('deleted_successfully')) ?></span>
                </div>
                <button onclick="undoDelete()" class="text-yellow-400 font-black text-sm md:text-base uppercase tracking-widest hover:text-yellow-300 hover:bg-white/10 px-3 py-1.5 rounded-lg transition-colors ml-4">Undo</button>
                <div class="undo-progress-bar"></div>
            `;
            document.body.appendChild(snackbar);
        }

        function undoDelete() {
            if (undoTimeout) {
                clearTimeout(undoTimeout);
                undoTimeout = null;
            }
            if (pendingWrapper) {
                pendingWrapper.classList.remove('collapsed');
            }
            removeSnackbar();
        }

        function removeSnackbar() {
            const snackbar = document.getElementById('undoSnackbar');
            if (snackbar) {
                snackbar.classList.replace('animate__fadeInUp', 'animate__fadeOutDown');
                setTimeout(() => snackbar.remove(), 500);
            }
        }

        function finalizeDelete() {
            if (!pendingNotiId) return;
            
            const currentId = pendingNotiId;
            const currentWrapper = pendingWrapper;
            
            clearTimeout(undoTimeout);
            undoTimeout = null;
            removeSnackbar();

            setTimeout(() => {
                const formData = new FormData();
                formData.append('action', 'ajax_delete');
                formData.append('noti_id', currentId);

                fetch('notifications.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentWrapper.remove();
                        
                        // Header မှ Notification Badge Count ကို Update လုပ်မည်
                        const notiBadge = document.getElementById('headerNotiBadge');
                        if (notiBadge) {
                            if (data.new_noti_count > 0) {
                                notiBadge.textContent = data.new_noti_count;
                                notiBadge.classList.remove('hidden');
                            } else {
                                notiBadge.classList.add('hidden');
                            }
                        }

                        if (document.querySelectorAll('.notification-wrapper').length === 0) {
                            location.reload(); // အားလုံးကုန်သွားပါက Empty state ပြရန် Refresh လုပ်မည်
                        }
                    }
                })
                .catch(err => console.error('Error:', err));
            }, 100);
        }

        function confirmDelete(notiId, wrapper) {
            Swal.fire({
                title: '<?= addslashes(__('confirm_delete_title')) ?>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<?= addslashes(__('delete_btn')) ?>',
                cancelButtonText: '<?= addslashes(__('cancel_btn')) ?>',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-xl px-5 py-2.5',
                    cancelButton: 'rounded-xl px-5 py-2.5'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteNotificationAJAX(notiId, wrapper);
                }
            });
        }

        // ==========================================
        // TEXT TO SPEECH (TTS) LOGIC (UPDATED)
        // ==========================================
        let currentUtterance = null;
        let speakingBtn = null;

        // Load Voices Function (Filtered for EN & MM)
        function loadTTSVoices() {
            const voiceSelect = document.getElementById('ttsVoice');
            if (!voiceSelect) return;
            let voices = window.speechSynthesis.getVoices();
            if (voices.length === 0) return;
            
            // မြန်မာ (my) နှင့် အင်္ဂလိပ် (en) အသံများကိုသာ စစ်ထုတ်မည်
            let filteredVoices = voices.filter(voice => voice.lang.startsWith('my') || voice.lang.startsWith('en'));
            
            // မြန်မာအသံ ပါ/မပါ ရှာဖွေမည်
            let myanmarVoice = filteredVoices.find(voice => voice.lang.startsWith('my'));
            
            const savedVoice = localStorage.getItem('ttsVoice');
            voiceSelect.innerHTML = ''; 
            
            if (filteredVoices.length === 0) {
                voiceSelect.innerHTML = '<option value="">Default Voice</option>';
                return;
            }
            
            let isAnySelected = false;

            filteredVoices.forEach((voice) => {
                const option = document.createElement('option');
                option.value = voice.name;
                let langLabel = voice.lang.startsWith('my') ? '(မြန်မာ)' : '(English)';
                option.textContent = `${voice.name} ${langLabel}`;
                
                // ၁။ User သိမ်းထားတဲ့ အသံရှိရင် အဲ့ဒါကို ရွေးမယ်
                if (savedVoice && voice.name === savedVoice) {
                    option.selected = true;
                    isAnySelected = true;
                } 
                // ၂။ မရှိခဲ့ရင် မြန်မာအသံကို Default အနေနဲ့ ရွေးမယ်
                else if (!savedVoice && myanmarVoice && voice.name === myanmarVoice.name) {
                    option.selected = true;
                    isAnySelected = true;
                    localStorage.setItem('ttsVoice', voice.name);
                }
                
                voiceSelect.appendChild(option);
            });

            // သိမ်းထားတဲ့အသံလည်းမရှိ၊ မြန်မာအသံလည်း စက်ထဲမှာမရှိရင် ပထမဆုံးအသံကို Default အဖြစ်သတ်မှတ်မယ်
            if (!isAnySelected && filteredVoices.length > 0) {
                voiceSelect.options[0].selected = true;
                localStorage.setItem('ttsVoice', filteredVoices[0].name);
            }
        }

        if ('speechSynthesis' in window) {
            if (speechSynthesis.onvoiceschanged !== undefined) {
                speechSynthesis.onvoiceschanged = loadTTSVoices;
            }
            setTimeout(loadTTSVoices, 100);
        }

        function speakText(btn, text) {
            if (!('speechSynthesis' in window)) {
                Swal.fire({ icon: 'info', title: '<?= addslashes(__('unsupported')) ?>', text: '<?= addslashes(__('tts_unsupported_msg')) ?>', confirmButtonColor: '#1a428a' });
                return;
            }

            // ဖွင့်ထားတာရှိရင် အရင်ပိတ်မယ်
            if (window.speechSynthesis.speaking) {
                window.speechSynthesis.cancel();
                if (speakingBtn) {
                    speakingBtn.innerHTML = '<i class="fas fa-volume-up text-xs md:text-sm"></i> <span class="hidden sm:inline"><?= addslashes(__('listen')) ?></span>';
                    speakingBtn.classList.remove('text-white', 'bg-blue-600', 'animate-pulse', 'border-blue-600');
                    speakingBtn.classList.add('text-blue-600', 'bg-blue-50');
                }
                if (speakingBtn === btn) {
                    speakingBtn = null;
                    return; // အတူတူပဲနှိပ်ရင် ပိတ်ရုံသာ လုပ်မည်
                }
            }

            // အသစ်စတင်ဖတ်မည်
            currentUtterance = new SpeechSynthesisUtterance(text);
            
            let speedRate = parseFloat(document.getElementById('ttsSpeed') ? document.getElementById('ttsSpeed').value : (localStorage.getItem('ttsSpeed') || 0.9));
            currentUtterance.rate = speedRate; 

            let selectedVoiceName = document.getElementById('ttsVoice') ? document.getElementById('ttsVoice').value : localStorage.getItem('ttsVoice');
            let voices = window.speechSynthesis.getVoices();
            let selectedVoice = voices.find(voice => voice.name === selectedVoiceName);
            
            // ရွေးချယ်ထားသောအသံပေါ်မူတည်ပြီး ဘာသာစကားသတ်မှတ်မည်
            if (selectedVoice) {
                currentUtterance.voice = selectedVoice;
                currentUtterance.lang = selectedVoice.lang;
            } else {
                currentUtterance.lang = 'my-MM'; // Fallback
            }

            currentUtterance.onstart = function() {
                speakingBtn = btn;
                btn.innerHTML = '<i class="fas fa-stop-circle text-xs md:text-sm"></i> <span class="hidden sm:inline"><?= addslashes(__('stop')) ?></span>';
                btn.classList.remove('text-blue-600', 'bg-blue-50');
                btn.classList.add('text-white', 'bg-blue-600', 'animate-pulse', 'border-blue-600');
            };

            currentUtterance.onend = currentUtterance.onerror = function() {
                if (btn === speakingBtn) {
                    btn.innerHTML = '<i class="fas fa-volume-up text-xs md:text-sm"></i> <span class="hidden sm:inline"><?= addslashes(__('listen')) ?></span>';
                    btn.classList.remove('text-white', 'bg-blue-600', 'animate-pulse', 'border-blue-600');
                    btn.classList.add('text-blue-600', 'bg-blue-50');
                    speakingBtn = null;
                }
            };
            window.speechSynthesis.speak(currentUtterance);
        }

        // မဖတ်ရသေးသော Noti များ အားလုံးကို ဆက်တိုက်ဖတ်ပြမည့် Function
        function playAllUnread(isAuto = false) {
            if (!('speechSynthesis' in window)) {
                if (!isAuto) Swal.fire({ icon: 'info', title: '<?= addslashes(__('unsupported')) ?>', text: '<?= addslashes(__('tts_unsupported_msg')) ?>', confirmButtonColor: '#1a428a' });
                return;
            }

            const unreadCards = document.querySelectorAll('.notification-card[data-is-read="0"]');
            if (unreadCards.length === 0) {
                if (!isAuto) Swal.fire({ icon: 'info', text: '<?= addslashes(__('no_unread_noti_to_read')) ?>', confirmButtonColor: '#1a428a' });
                return;
            }

            window.speechSynthesis.cancel(); // Stop any current speech

            let speedRate = parseFloat(document.getElementById('ttsSpeed') ? document.getElementById('ttsSpeed').value : (localStorage.getItem('ttsSpeed') || 0.9));
            let selectedVoiceName = document.getElementById('ttsVoice') ? document.getElementById('ttsVoice').value : localStorage.getItem('ttsVoice');
            let voices = window.speechSynthesis.getVoices();
            let selectedVoice = voices.find(voice => voice.name === selectedVoiceName);

            unreadCards.forEach((card) => {
                let btn = card.querySelector('button[onclick^="speakText"]');
                if (btn) {
                    let match = btn.getAttribute('onclick').match(/speakText\(this,\s*'([^']+)'\)/);
                    if (match && match[1]) {
                        let text = match[1];
                        let utterance = new SpeechSynthesisUtterance(text);
                        
                        utterance.rate = speedRate;
                        if (selectedVoice) {
                            utterance.voice = selectedVoice;
                            utterance.lang = selectedVoice.lang;
                        } else {
                            utterance.lang = 'my-MM';
                        }
                        
                        utterance.onstart = function() {
                            card.classList.add('ring-2', 'ring-blue-400', 'bg-blue-50');
                            card.scrollIntoView({ behavior: 'smooth', block: 'center' }); // ဖတ်နေသည့် နေရာသို့ Auto Scroll လုပ်မည်
                        };
                        utterance.onend = function() {
                            card.classList.remove('ring-2', 'ring-blue-400', 'bg-blue-50');
                        };
                        utterance.onerror = function() {
                            card.classList.remove('ring-2', 'ring-blue-400', 'bg-blue-50');
                        };
                        
                        window.speechSynthesis.speak(utterance);
                    }
                }
            });
        }

        // ==========================================
        // Event Listeners (Swipe & Defaults)
        // ==========================================
        document.addEventListener('DOMContentLoaded', () => {
            // Load TTS Speed from LocalStorage
            const savedSpeed = localStorage.getItem('ttsSpeed');
            if (savedSpeed) {
                const speedSelect = document.getElementById('ttsSpeed');
                if (speedSelect) speedSelect.value = savedSpeed;
            }

            // Load TTS AutoPlay from LocalStorage
            const savedAutoPlay = localStorage.getItem('ttsAutoPlay');
            if (savedAutoPlay === 'true') {
                const autoPlayCb = document.getElementById('ttsAutoPlay');
                if (autoPlayCb) autoPlayCb.checked = true;
                
                // Attempt to auto-play after voices are loaded
                setTimeout(() => { playAllUnread(true); }, 1000);
                
                // Fallback for strict browser autoplay policies
                const playOnInteraction = () => {
                    if (document.getElementById('ttsAutoPlay').checked && !window.speechSynthesis.speaking) {
                        playAllUnread(true);
                    }
                    document.removeEventListener('click', playOnInteraction);
                };
                document.addEventListener('click', playOnInteraction);
            }

            const cards = document.querySelectorAll('.notification-card');
            let startX = 0;
            let currentX = 0;
            let thresholdVibrated = false;
            let fullThresholdVibrated = false;
            const threshold = -100; // ဘယ်ဘက်သို့ ၁၀၀px ဆွဲလျှင် ဖျက်မည်
            const fullThreshold = -250; // ဘယ်ဘက်သို့ ၂၅၀px ဆွဲလျှင် Snackbar မပြဘဲ ချက်ချင်းဖျက်မည်

            cards.forEach(card => {

                // Desktop click-to-read binding
                card.addEventListener('click', (e) => {
                    if (e.target.closest('button') || e.target.closest('a') || e.target.closest('form')) return;
                    markAsRead(e, card, card.dataset.notiId);
                });

                card.addEventListener('touchstart', (e) => {
                    startX = e.touches[0].clientX;
                    window.isSwiping = false;
                    thresholdVibrated = false;
                    fullThresholdVibrated = false;
                    card.style.transition = 'none';
                }, { passive: true });

                card.addEventListener('touchmove', (e) => {
                    let touchX = e.touches[0].clientX;
                    if (Math.abs(touchX - startX) > 10) window.isSwiping = true;
                    currentX = touchX - startX;
                    
                    const wrapper = card.closest('.notification-wrapper');
                    const swipeReadBg = wrapper.querySelector('#swipeReadBg');
                    const swipeDeleteBg = wrapper.querySelector('#swipeDeleteBg');
                    const iconRead = wrapper.querySelector('.fa-check-double');
                    const iconDelete = wrapper.querySelector('.fa-trash-alt');

                    // ဘယ်ဘက်သို့ဆွဲခြင်း (Delete)
                    if (currentX < 0 && card.dataset.canDelete === 'true') {
                        // Icon Animation (Scale and Opacity)
                        let progress = Math.min(Math.abs(currentX) / 100, 1.2);
                        iconDelete.style.transform = `scale(${0.6 + (progress * 0.6)})`;
                        // Dynamic Background Color (Red)
                        let redOpacity = Math.min(Math.abs(currentX) / 100, 1); // 100px ဆွဲရင် opacity 1 ဖြစ်မည်
                        
                        // Full Swipe Threshold သတိပေးချက်
                        if (currentX < fullThreshold && !fullThresholdVibrated) {
                            if (navigator.vibrate) navigator.vibrate([40, 30, 40]);
                            fullThresholdVibrated = true;
                            redOpacity = 1; // အရောင်ကို အပြည့်ပေးမည်
                        }

                        swipeDeleteBg.style.backgroundColor = `rgba(239, 68, 68, ${redOpacity})`; // Tailwind red-500 (239, 68, 68)
                        iconDelete.style.opacity = Math.min(Math.abs(currentX) / 50, 1);

                        // Threshold ကျော်သွားပါက အနည်းငယ် တုန်ခါပေးမည် (Delete)
                        if (currentX < threshold && !thresholdVibrated) {
                            if (navigator.vibrate) navigator.vibrate(30);
                            thresholdVibrated = true;
                        } else if (currentX > threshold) {
                            thresholdVibrated = false;
                        }
                        let translate = Math.max(currentX, -150);
                        card.style.transform = `translateX(${translate}px)`;
                    } 
                    // ညာဘက်သို့ဆွဲခြင်း (Mark as Read)
                    else if (currentX > 0 && card.dataset.isRead === '0') {
                        // Icon Animation (Scale and Opacity)
                        let progress = Math.min(currentX / 100, 1.2);
                        // Dynamic Background Color (Blue)
                        let blueOpacity = Math.min(currentX / 100, 1); // 100px ဆွဲရင် opacity 1 ဖြစ်မည်
                        swipeReadBg.style.backgroundColor = `rgba(59, 130, 246, ${blueOpacity})`; // Tailwind blue-500 (59, 130, 246)
                        iconRead.style.transform = `scale(${0.6 + (progress * 0.6)})`;
                        iconRead.style.opacity = Math.min(currentX / 50, 1);

                        if (currentX > 100 && !thresholdVibrated) {
                            if (navigator.vibrate) navigator.vibrate(30);
                            thresholdVibrated = true;
                        } else if (currentX < 100) {
                            thresholdVibrated = false;
                        }
                        let translate = Math.min(currentX, 150);
                        card.style.transform = `translateX(${translate}px)`;
                    }
                }, { passive: true });

                card.addEventListener('touchend', () => {
                    // Snap back animation with bouncy cubic-bezier
                    card.style.transition = 'transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                    const notiId = card.dataset.notiId;
                    const wrapper = card.closest('.notification-wrapper');

                    const swipeReadBg = wrapper.querySelector('#swipeReadBg');
                    const swipeDeleteBg = wrapper.querySelector('#swipeDeleteBg');
                    // Reset background icons state
                    wrapper.querySelectorAll('.swipe-icon').forEach(icon => {
                        icon.style.transform = 'scale(0.6)';
                        icon.style.opacity = '0';
                    });
                    
                    if (currentX < fullThreshold && card.dataset.canDelete === 'true') {
                        // ၁။ Full Swipe: Snackbar မပြဘဲ ချက်ချင်းဖျက်မည်
                        swipeDeleteBg.style.backgroundColor = 'transparent';
                        card.style.transform = 'translateX(-120%)';
                        directDeleteAJAX(notiId, wrapper);
                    } else if (currentX < threshold && card.dataset.canDelete === 'true') {
                        // ၂။ Normal Swipe: အတည်ပြုချက်တောင်းပြီး Snackbar ပြမည်
                        // Reset background color for delete action
                        swipeDeleteBg.style.backgroundColor = 'transparent';

                        // Threshold ကျော်သွားပါက အပြင်ကို လွှင့်ထုတ်ပြီး ဖျက်မည်
                        card.style.transform = 'translateX(-120%)';
                        
                        // အတည်ပြုချက်တောင်းရန် (SweetAlert သုံးထားသည်)
                        setTimeout(() => {
                            Swal.fire({
                                title: '<?= addslashes(__('confirm_delete_title')) ?>',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#d33',
                                cancelButtonColor: '#6b7280',
                                confirmButtonText: '<?= addslashes(__('delete_btn')) ?>',
                                cancelButtonText: '<?= addslashes(__('cancel_btn')) ?>',
                                customClass: {
                                    popup: 'rounded-2xl',
                                    confirmButton: 'rounded-xl px-5 py-2.5',
                                    cancelButton: 'rounded-xl px-5 py-2.5'
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    deleteNotificationAJAX(notiId, card.closest('.notification-wrapper'));
                                } else {
                                    card.style.transform = 'translateX(0)';
                                }
                            });
                        }, 200);
                    } else if (currentX > 100 && card.dataset.isRead === '0') {
                        // Reset background color for read action
                        swipeReadBg.style.backgroundColor = 'transparent';

                        // ညာဘက်သို့ Threshold ကျော်ပါက (Full Swipe) Snackbar မပြဘဲ ချက်ချင်း 'ဖတ်ပြီးသား' လုပ်မည်
                        card.style.transform = 'translateX(100%)'; // Screen အပြင်သို့ လွှင့်ထုတ်မည်
                        setTimeout(() => {
                            markAsRead(null, card, notiId, true); // AJAX ဖြင့် ဖတ်ပြီးသားလုပ်မည်
                            setTimeout(() => { card.style.transform = 'translateX(0)'; }, 100); // ချက်ချင်း မူလနေရာသို့ ပြန်ကပ်မည်
                        }, 150);
                    } else {
                        // Threshold မပြည့်ပါက မူလနေရာသို့ ပြန်သွားမည်
                        swipeReadBg.style.backgroundColor = 'transparent';
                        swipeDeleteBg.style.backgroundColor = 'transparent';
                        card.style.transform = 'translateX(0)';
                    }
                    currentX = 0;
                });
            });
        });

        // စာမျက်နှာမှ ထွက်သွားပါက အသံထွက်နေခြင်းကို အလိုအလျောက် ရပ်မည်
        window.addEventListener('beforeunload', function() {
            if (window.speechSynthesis) window.speechSynthesis.cancel();
        });

        // ရက်စွဲ အမြန်ရွေးချယ်ရန် Function
        function setQuickDate(date) {
            document.querySelector('input[name="start_date"]').value = date;
            document.querySelector('input[name="end_date"]').value = date;
            document.querySelector('#filterSection form').submit();
        }
    </script>
</body>
</html>
