<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

require_once __DIR__ . '/../core/auth_helper.php';
require_permission('can_manage_blocked_numbers');

$success_message = "";
$error_message = "";

// အချိန်အလိုက် ပိတ်ဂဏန်းများအတွက် Settings အသစ်များ မရှိသေးပါက ထည့်သွင်းပေးမည်
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('blocked_2d_morning', ''), ('blocked_2d_evening', '')");

// Form Submit လုပ်လာသောအခါ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_blocked'])) {
    $blocked_2d = $_POST['blocked_2d'] ?? '';
    $blocked_2d_morning = $_POST['blocked_2d_morning'] ?? '';
    $blocked_2d_evening = $_POST['blocked_2d_evening'] ?? '';
    $blocked_3d = $_POST['blocked_3d'] ?? '';
    
    // 2D Data များ သန့်စင်ခြင်း
    $b2d_arr = array_filter(array_map('trim', explode(',', $blocked_2d)), 'strlen');
    $b2d_arr = array_unique($b2d_arr);
    sort($b2d_arr);
    $clean_blocked_2d = implode(',', $b2d_arr);

    $b2d_m_arr = array_filter(array_map('trim', explode(',', $blocked_2d_morning)), 'strlen');
    $b2d_m_arr = array_unique($b2d_m_arr); sort($b2d_m_arr);
    $clean_blocked_2d_morning = implode(',', $b2d_m_arr);

    $b2d_e_arr = array_filter(array_map('trim', explode(',', $blocked_2d_evening)), 'strlen');
    $b2d_e_arr = array_unique($b2d_e_arr); sort($b2d_e_arr);
    $clean_blocked_2d_evening = implode(',', $b2d_e_arr);

    // 3D Data များ သန့်စင်ခြင်း
    $b3d_arr = array_filter(array_map('trim', explode(',', $blocked_3d)), 'strlen');
    $b3d_arr = array_unique($b3d_arr);
    sort($b3d_arr);
    $clean_blocked_3d = implode(',', $b3d_arr);

    // Database အတွင်းသို့ သိမ်းဆည်းခြင်း
    $stmt_2d = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'blocked_2d_numbers'");
    $stmt_2d->bind_param("s", $clean_blocked_2d);
    $stmt_2d->execute();
    $stmt_2d->close();

    $stmt_2d_m = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'blocked_2d_morning'");
    $stmt_2d_m->bind_param("s", $clean_blocked_2d_morning);
    $stmt_2d_m->execute();
    $stmt_2d_m->close();

    $stmt_2d_e = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'blocked_2d_evening'");
    $stmt_2d_e->bind_param("s", $clean_blocked_2d_evening);
    $stmt_2d_e->execute();
    $stmt_2d_e->close();

    $stmt_3d = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'blocked_3d_numbers'");
    $stmt_3d->bind_param("s", $clean_blocked_3d);
    $stmt_3d->execute();
    $stmt_3d->close();

    log_activity($_SESSION['user_id'], 'UPDATE_BLOCKED_NUMBERS', "Blocked numbers were updated.");
    $success_message = __('admin_blocked_success');
}

// Database မှ လက်ရှိ ပိတ်ထားသောဂဏန်းများကို ဆွဲထုတ်ခြင်း
$stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('blocked_2d_numbers', 'blocked_2d_morning', 'blocked_2d_evening', 'blocked_3d_numbers')");
$settings = [];
while ($row = $stmt->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$current_blocked_2d = $settings['blocked_2d_numbers'] ?? '';
$current_blocked_2d_morning = $settings['blocked_2d_morning'] ?? '';
$current_blocked_2d_evening = $settings['blocked_2d_evening'] ?? '';
$current_blocked_3d = $settings['blocked_3d_numbers'] ?? '';

$b2d_array = empty($current_blocked_2d) ? [] : explode(',', $current_blocked_2d);
$b2d_m_array = empty($current_blocked_2d_morning) ? [] : explode(',', $current_blocked_2d_morning);
$b2d_e_array = empty($current_blocked_2d_evening) ? [] : explode(',', $current_blocked_2d_evening);
$b3d_array = empty($current_blocked_3d) ? [] : explode(',', $current_blocked_3d);
?>

<?php 
$page_title = __('admin_blocked_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_blocked_header_title');
    $header_icon = "fas fa-ban";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm font-bold"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="bg-white rounded-xl shadow-md p-4 sm:p-6 border-t-4 border-red-500">
            
            <!-- 2D Blocked Numbers -->
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-dice-two text-red-500 mr-2"></i> <?= __('admin_blocked_2d_title') ?></h2>
            <input type="hidden" id="blocked_2d" name="blocked_2d" value="<?= htmlspecialchars($current_blocked_2d) ?>">
            <div class="mb-4">
                <button type="button" onclick="clear2D()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1.5 rounded text-xs font-bold mb-2 shadow-sm transition"><i class="fas fa-eraser mr-1"></i> <?= __('admin_blocked_btn_clear_all') ?></button>
                <p class="text-xs text-gray-500"><?= __('admin_blocked_2d_desc') ?></p>
            </div>
            
            <div class="grid grid-cols-5 md:grid-cols-10 gap-2 mb-8">
                <?php for($i=0; $i<=99; $i++): 
                    $num = str_pad($i, 2, '0', STR_PAD_LEFT); 
                    $is_blocked = in_array($num, $b2d_array);
                ?>
                    <button type="button" id="btn_2d_<?= $num ?>" onclick="toggle2D('<?= $num ?>')" class="py-2 rounded font-bold text-sm border transition <?= $is_blocked ? 'bg-red-500 text-white border-red-600 shadow-inner' : 'bg-gray-50 text-gray-700 border-gray-300 hover:bg-gray-200' ?>">
                        <?= $num ?>
                    </button>
                <?php endfor; ?>
            </div>

            <!-- 3D Blocked Numbers -->
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 mt-8"><i class="fas fa-dice text-red-500 mr-2"></i> <?= __('admin_blocked_3d_title') ?></h2>
            <input type="hidden" id="blocked_3d" name="blocked_3d" value="<?= htmlspecialchars($current_blocked_3d) ?>">
            
            <div class="flex flex-col sm:flex-row gap-2 mb-4">
                <input type="text" id="add_3d_input" maxlength="3" pattern="[0-9]{3}" placeholder="<?= __('admin_blocked_3d_ph') ?>" class="px-4 py-2 border border-gray-300 rounded focus:outline-none focus:border-red-500 w-full sm:w-40">
                <button type="button" onclick="add3D()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded font-bold shadow-sm transition"><i class="fas fa-plus mr-1"></i> <?= __('admin_blocked_btn_block') ?></button>
            </div>

            <div id="blocked_3d_container" class="flex flex-wrap gap-2 mb-8"></div>

            <button type="submit" name="update_blocked" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-lg shadow-md transition mt-4">
                <i class="fas fa-save mr-2"></i> <?= __('admin_blocked_btn_save') ?>
            </button>
        </form>
    </div>

    <script>
        function switch2DTab(tab) {
            ['all', 'morning', 'evening'].forEach(t => {
                document.getElementById('content_' + t).classList.add('hidden');
                document.getElementById('tab_btn_' + t).className = 'bg-gray-200 text-gray-700 hover:bg-gray-300 px-4 py-2 rounded text-sm font-bold shadow-sm transition';
            });
            document.getElementById('content_' + tab).classList.remove('hidden');
            document.getElementById('tab_btn_' + tab).className = 'bg-red-500 text-white px-4 py-2 rounded text-sm font-bold shadow-sm transition';
        }
        function toggle2D(num, tab_key, input_id) {
            let input = document.getElementById(input_id);
            let current = input.value.split(',').filter(n => n !== '');
            let btn = document.getElementById('btn_2d_' + tab_key + '_' + num);
            let index = current.indexOf(num);
            if (index > -1) { current.splice(index, 1); btn.className = 'py-2 rounded font-bold text-sm border transition bg-gray-50 text-gray-700 border-gray-300 hover:bg-gray-200'; } 
            else { current.push(num); btn.className = 'py-2 rounded font-bold text-sm border transition bg-red-500 text-white border-red-600 shadow-inner'; }
            input.value = current.join(',');
        }
        function clear2D(tab_key, input_id) {
            document.getElementById(input_id).value = '';
            for(let i=0; i<=99; i++) {
                let num = i.toString().padStart(2, '0'); let btn = document.getElementById('btn_2d_' + tab_key + '_' + num);
                if(btn) btn.className = 'py-2 rounded font-bold text-sm border transition bg-gray-50 text-gray-700 border-gray-300 hover:bg-gray-200';
            }
        }
        function render3D() {
            let input = document.getElementById('blocked_3d'); let current = input.value.split(',').filter(n => n !== ''); let container = document.getElementById('blocked_3d_container');
            if (current.length === 0) { container.innerHTML = '<p class="text-gray-400 text-sm italic"><?= __('admin_blocked_no_3d') ?></p>'; return; }
            container.innerHTML = '';
            current.sort().forEach(num => { container.innerHTML += `<div class="bg-red-100 border border-red-200 text-red-700 px-3 py-1 rounded-full flex items-center gap-2 text-sm font-bold shadow-sm"><span>${num}</span> <button type="button" onclick="remove3D('${num}')" class="text-red-500 hover:text-red-800"><i class="fas fa-times-circle"></i></button></div>`; });
        }
        function add3D() { let val = document.getElementById('add_3d_input').value.trim(); if (val.length === 3 && /^[0-9]{3}$/.test(val)) { let input = document.getElementById('blocked_3d'); let current = input.value.split(',').filter(n => n !== ''); if (!current.includes(val)) { current.push(val); input.value = current.join(','); render3D(); } document.getElementById('add_3d_input').value = ''; } else { alert("<?= __('admin_blocked_invalid_3d') ?>"); } }
        function remove3D(num) { let input = document.getElementById('blocked_3d'); let current = input.value.split(',').filter(n => n !== ''); let index = current.indexOf(num); if (index > -1) { current.splice(index, 1); input.value = current.join(','); render3D(); } }
        document.getElementById('add_3d_input').addEventListener('keypress', function(e) { if (e.key === 'Enter') { e.preventDefault(); add3D(); } });
        render3D();
    </script>
</body>
</html>