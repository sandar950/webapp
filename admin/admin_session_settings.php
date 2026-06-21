<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_main_admin();

// Handle messages from Session (Post-Redirect-Get Pattern)
$success_message = $_SESSION['success_message'] ?? "";
$error_message = $_SESSION['error_message'] ?? "";
unset($_SESSION['success_message'], $_SESSION['error_message']);

/**
 * Helper function for redirection with messages
 */
function redirectWithMessage($success = "", $error = "") {
    if ($success) $_SESSION['success_message'] = $success;
    if ($error) $_SESSION['error_message'] = $error;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle Form Submission (Create or Update Session)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_session') {
        $game_type = $_POST['game_type'] ?? '';
        $section = $_POST['section'] ?? '';
        $target_date = $_POST['target_date'] ?? '';
        $open_date = $_POST['open_date'] ?? '';
        $open_time = $_POST['open_time'] ?? '';
        $close_date = $_POST['close_date'] ?? '';
        $close_time = $_POST['close_time'] ?? '';

        if (!empty($game_type) && !empty($section) && !empty($target_date) && !empty($open_date) && !empty($open_time) && !empty($close_date) && !empty($close_time)) {
            // Combine date and time
            $open_dt = $open_date . ' ' . $open_time . ':00';
            $close_dt = $close_date . ' ' . $close_time . ':00';

            if (strtotime($close_dt) <= strtotime($open_dt)) {
                redirectWithMessage("", __('admin_sessions_err_close_time'));
            } elseif (strtotime($open_dt) < strtotime(date('Y-m-d H:i:s'))) {
                redirectWithMessage("", __('admin_sessions_err_past_time'));
            } else {
                $stmt = $conn->prepare("INSERT INTO betting_sessions (game_type, section, target_date, open_time, close_time) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $game_type, $section, $target_date, $open_dt, $close_dt);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    redirectWithMessage(__('admin_sessions_success_add'), "");
                } else {
                    $err = ($conn->errno == 1062) ? __('admin_sessions_err_dup') : __('admin_tx_error') . " " . $conn->error;
                    $stmt->close();
                    redirectWithMessage("", $err);
                }
            }
        } else {
            redirectWithMessage("", __('admin_sessions_fill_all'));
        }
    } elseif ($_POST['action'] === 'close_session') {
        $session_id = intval($_POST['session_id']);
        $stmt = $conn->prepare("UPDATE betting_sessions SET status = 'closed' WHERE id = ?");
        $stmt->bind_param("i", $session_id);
        if ($stmt->execute()) {
            $stmt->close();
            redirectWithMessage(__('admin_sessions_success_close'), "");
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'delete_session') {
        $session_id = intval($_POST['session_id']);
        $stmt = $conn->prepare("DELETE FROM betting_sessions WHERE id = ? AND status = 'active'");
        $stmt->bind_param("i", $session_id);
        if ($stmt->execute()) {
            $stmt->close();
            redirectWithMessage(__('admin_sessions_success_delete'), "");
        }
        $stmt->close();
    }
}

// Fetch upcoming and active sessions
$sessions_stmt = $conn->query("SELECT * FROM betting_sessions ORDER BY target_date DESC, section ASC LIMIT 50");
$sessions = [];
if ($sessions_stmt) {
    while ($row = $sessions_stmt->fetch_assoc()) {
        $sessions[] = $row;
    }
}
?>

<?php 
$page_title = __('admin_sessions_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_sessions_header_title');
    $header_icon = "fas fa-clock";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Add New Session Form -->
        <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-plus-circle text-blue-500 mr-2"></i> <?= __('admin_sessions_add_title') ?></h2>
            <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="add_session">
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2"><?= __('admin_sessions_game_type') ?></label>
                    <select name="game_type" id="game_type" class="w-full border rounded-lg p-3 bg-gray-50 focus:border-blue-500 focus:outline-none" required onchange="updateSectionOptions()">
                        <option value="2d"><?= __('admin_sessions_2d') ?></option>
                        <option value="3d"><?= __('admin_sessions_3d') ?></option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2"><?= __('admin_sessions_section') ?></label>
                    <select name="section" id="section" class="w-full border rounded-lg p-3 bg-gray-50 focus:border-blue-500 focus:outline-none" required>
                        <option value="morning"><?= __('admin_sessions_morning') ?></option>
                        <option value="evening"><?= __('admin_sessions_evening') ?></option>
                        <!-- Options updated dynamically via JS -->
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2"><?= __('admin_sessions_target_date') ?></label>
                    <input type="date" name="target_date" id="target_date" class="w-full border rounded-lg p-3 bg-gray-50 focus:border-blue-500 focus:outline-none" value="<?= date('Y-m-d') ?>" required onchange="syncDates(this.value)">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:col-span-2">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block text-gray-700 text-sm font-bold mb-1 col-span-2"><?= __('admin_sessions_open_time') ?></label>
                        <input type="date" name="open_date" id="open_date" class="border rounded-lg p-3 bg-gray-50 focus:border-blue-500 focus:outline-none" value="<?= date('Y-m-d') ?>" required>
                        <input type="time" name="open_time" class="w-full border rounded-lg p-3 bg-gray-50 focus:border-blue-500 focus:outline-none" required>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block text-gray-700 text-sm font-bold mb-1 col-span-2"><?= __('admin_sessions_close_time') ?></label>
                        <input type="date" name="close_date" id="close_date" class="border rounded-lg p-3 bg-gray-50 focus:border-blue-500 focus:outline-none" value="<?= date('Y-m-d') ?>" required>
                        <input type="time" name="close_time" class="w-full border rounded-lg p-3 bg-gray-50 focus:border-blue-500 focus:outline-none" required>
                    </div>
                </div>

                <div class="md:col-span-2 mt-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-sm transition"><i class="fas fa-save mr-2"></i> <?= __('admin_sessions_btn_create') ?></button>
                </div>
            </form>
        </div>

        <!-- Sessions List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <h2 class="font-bold text-gray-800"><i class="fas fa-list text-gray-500 mr-2"></i> <?= __('admin_sessions_list_title') ?></h2>
            </div>
            <?php if (count($sessions) > 0): ?>
                <!-- Mobile View: Cards -->
                <div class="md:hidden divide-y divide-gray-100">
                    <?php foreach ($sessions as $s): ?>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <p class="font-bold text-gray-800"><?= htmlspecialchars($s['target_date']) ?></p>
                                    <p class="text-sm text-gray-500"><?= strtoupper($s['game_type']) ?> - <?= ucfirst($s['section']) ?></p>
                                </div>
                                <?php if ($s['status'] == 'active'): ?><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"><?= __('admin_sessions_status_active') ?></span><?php else: ?><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800"><?= __('admin_sessions_status_closed') ?></span><?php endif; ?>
                            </div>
                            <div class="text-xs space-y-1 text-gray-600 mb-3">
                                <div class="flex justify-between"><span><?= __('admin_sessions_col_open') ?>:</span> <span class="font-bold"><?= date('d-M-y h:i A', strtotime($s['open_time'])) ?></span></div>
                                <div class="flex justify-between"><span><?= __('admin_sessions_col_close') ?>:</span> <span class="font-bold text-red-600"><?= date('d-M-y h:i A', strtotime($s['close_time'])) ?></span></div>
                            </div>
                            <?php if ($s['status'] == 'active'): ?>
                                <div class="flex gap-2 justify-end">
                                    <form method="POST" action="" class="inline" onsubmit="return confirm('<?= __('admin_sessions_confirm_force_close') ?>');"><input type="hidden" name="action" value="close_session"><input type="hidden" name="session_id" value="<?= $s['id'] ?>"><button type="submit" class="text-yellow-600 hover:text-yellow-900 bg-yellow-50 px-3 py-1 rounded text-xs border border-yellow-200"><?= __('admin_sessions_btn_force_close') ?></button></form>
                                    <form method="POST" action="" class="inline" onsubmit="return confirm('<?= __('admin_sessions_confirm_delete') ?>');"><input type="hidden" name="action" value="delete_session"><input type="hidden" name="session_id" value="<?= $s['id'] ?>"><button type="submit" class="text-red-600 hover:text-red-900 bg-red-50 px-3 py-1 rounded text-xs border border-red-200"><i class="fas fa-trash"></i></button></form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop View: Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('admin_sessions_col_date_sec') ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('admin_sessions_col_open') ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('admin_sessions_col_close') ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('admin_sessions_col_status') ?></th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('admin_sessions_col_action') ?></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sessions as $s): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="font-bold text-gray-800"><?= htmlspecialchars($s['target_date']) ?></div><div class="text-sm text-gray-500"><?= strtoupper($s['game_type']) ?> - <?= ucfirst($s['section']) ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= date('d-M-y h:i A', strtotime($s['open_time'])) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-red-600"><?= date('d-M-y h:i A', strtotime($s['close_time'])) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php if ($s['status'] == 'active'): ?><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"><?= __('admin_sessions_status_active') ?></span><?php else: ?><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800"><?= __('admin_sessions_status_closed') ?></span><?php endif; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if ($s['status'] == 'active'): ?>
                                            <form method="POST" action="" class="inline" onsubmit="return confirm('<?= __('admin_sessions_confirm_force_close') ?>');"><input type="hidden" name="action" value="close_session"><input type="hidden" name="session_id" value="<?= $s['id'] ?>"><button type="submit" class="text-yellow-600 hover:text-yellow-900 bg-yellow-50 px-3 py-1 rounded text-xs border border-yellow-200 mr-2"><?= __('admin_sessions_btn_force_close') ?></button></form>
                                            <form method="POST" action="" class="inline" onsubmit="return confirm('<?= __('admin_sessions_confirm_delete') ?>');"><input type="hidden" name="action" value="delete_session"><input type="hidden" name="session_id" value="<?= $s['id'] ?>"><button type="submit" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button></form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="px-6 py-10 text-center text-gray-500 italic">
                    <i class="fas fa-clock text-4xl text-gray-300 mb-3 block"></i>
                    <?= __('admin_sessions_no_records') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function syncDates(val) {
            document.getElementById('open_date').value = val;
            document.getElementById('close_date').value = val;
        }

        function updateSectionOptions() {
            var gameType = document.getElementById('game_type').value;
            var sectionSelect = document.getElementById('section');
            sectionSelect.innerHTML = '';
            
            if (gameType === '2d') {
                sectionSelect.options.add(new Option('<?= __('admin_sessions_morning') ?>', 'morning'));
                sectionSelect.options.add(new Option('<?= __('admin_sessions_evening') ?>', 'evening'));
            } else {
                sectionSelect.options.add(new Option('<?= __('admin_sessions_3d') ?>', '3d'));
            }
        }
        // Initialize on load
        document.addEventListener('DOMContentLoaded', updateSectionOptions);
    </script>
</body>
</html>