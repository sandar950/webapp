<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Main Admin (role='admin') သာ ဝင်ခွင့်ပြုမည်
require_main_admin();

$success_message = "";
$error_message = "";

// Permissions များ၏ အမည်များနှင့် key များ
$permissions_map = [
    'can_declare_result' => 'အဖြေကြေညာခွင့်',
    'can_manage_transactions' => 'ငွေသွင်း/ထုတ် စီမံခွင့်',
    'can_manage_users' => 'User များ စီမံခွင့်',
    'can_view_reports' => 'Report များ ကြည့်ရှုခွင့်',
    'can_manage_blocked_numbers' => 'ပိတ်ဂဏန်းများ စီမံခွင့်',
    'can_send_notifications' => 'Notification ပို့ခွင့်',
];

// Form Submit လုပ်လာသောအခါ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_permissions'])) {
    $permissions_data = $_POST['permissions'] ?? [];
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("
            INSERT INTO sub_admin_permissions (user_id, can_declare_result, can_manage_transactions, can_manage_users, can_view_reports, can_manage_blocked_numbers, can_send_notifications) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            can_declare_result = VALUES(can_declare_result),
            can_manage_transactions = VALUES(can_manage_transactions),
            can_manage_users = VALUES(can_manage_users),
            can_view_reports = VALUES(can_view_reports),
            can_manage_blocked_numbers = VALUES(can_manage_blocked_numbers),
            can_send_notifications = VALUES(can_send_notifications)
        ");

        $target_user_id = intval($_POST['user_id']);

        // Initialize all permissions to false (0)
        $p_declare = 0;
        $p_tx = 0;
        $p_users = 0;
        $p_reports = 0;
        $p_blocked = 0;
        $p_noti = 0;

        // Set submitted permissions to true (1)
        if (!empty($permissions_data[$target_user_id])) {
            foreach ($permissions_data[$target_user_id] as $key => $value) {
                if ($key === 'can_declare_result') $p_declare = 1;
                if ($key === 'can_manage_transactions') $p_tx = 1;
                if ($key === 'can_manage_users') $p_users = 1;
                if ($key === 'can_view_reports') $p_reports = 1;
                if ($key === 'can_manage_blocked_numbers') $p_blocked = 1;
                if ($key === 'can_send_notifications') $p_noti = 1;
            }
        }

        $stmt->bind_param("iiiiiii", $target_user_id, $p_declare, $p_tx, $p_users, $p_reports, $p_blocked, $p_noti);
        $stmt->execute();

        $conn->commit();
        log_activity($_SESSION['user_id'], 'UPDATE_PERMISSIONS', "Updated permissions for Sub-Admin ID: {$target_user_id}");
        $success_message = "ခွင့်ပြုချက်များကို အောင်မြင်စွာ သိမ်းဆည်းပြီးပါပြီ။";

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "သိမ်းဆည်းရာတွင် အမှားအယွင်းဖြစ်ပေါ်နေပါသည်။ " . $e->getMessage();
    }
}

// Sub-Admin အားလုံးနှင့် သူတို့၏ permissions များကို ဆွဲထုတ်ခြင်း
$query = "
    SELECT u.id, u.username, u.phone_number, p.* 
    FROM users u
    LEFT JOIN sub_admin_permissions p ON u.id = p.user_id
    WHERE u.role = 'sub_admin'
    ORDER BY u.id ASC
";
$sub_admins = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
?>

<?php 
$page_title = "Admin - Permissions Management";
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-5xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = "Sub-Admin Permissions";
    $header_icon = "fas fa-tasks";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-6 pt-0">
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 text-sm shadow-sm"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="font-bold text-gray-700">Sub-Admin များ၏ ခွင့်ပြုချက်များ</h2>
            </div>
            <div class="overflow-x-auto">
                <?php if (count($sub_admins) > 0): ?>
                    <?php foreach ($sub_admins as $admin): ?>
                        <form method="POST" action="" class="border-b last:border-b-0">
                            <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                            <div class="p-4 bg-blue-50 flex justify-between items-center">
                                <div>
                                    <p class="font-bold text-blue-800"><?= htmlspecialchars($admin['username']) ?></p>
                                    <p class="text-xs text-gray-600"><?= htmlspecialchars($admin['phone_number']) ?></p>
                                </div>
                                <button type="submit" name="save_permissions" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition"><i class="fas fa-save mr-1"></i> Save</button>
                            </div>
                            <div class="p-4 grid grid-cols-2 md:grid-cols-3 gap-4">
                                <?php foreach ($permissions_map as $key => $label): ?>
                                    <label class="flex items-center space-x-3 bg-gray-50 p-3 rounded-lg border border-gray-200 hover:bg-gray-100 cursor-pointer">
                                        <input type="checkbox" name="permissions[<?= $admin['id'] ?>][<?= $key ?>]" value="1" 
                                               class="w-5 h-5 text-primary rounded focus:ring-primary"
                                               <?= (isset($admin[$key]) && $admin[$key] == 1) ? 'checked' : '' ?>>
                                        <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($label) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="p-8 text-center text-gray-500 italic">Sub-Admin အကောင့် မရှိသေးပါ။</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>