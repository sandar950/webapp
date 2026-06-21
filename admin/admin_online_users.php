<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

require_main_admin(); // Main Admin (User ID 1) သာ ဝင်ခွင့်ပြုမည်

// လွန်ခဲ့သော ၅ မိနစ်အတွင်း အသုံးပြုခဲ့သော User များကို ဆွဲထုတ်မည် (Online Users)
$query = "SELECT id, username, phone_number, balance, last_active FROM users WHERE last_active >= NOW() - INTERVAL 5 MINUTE ORDER BY last_active DESC";
$result = $conn->query($query);
$online_users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

?>

<?php 
$page_title = "Admin - Online Users";
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-5xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = "Online ရောက်နေသူများ";
    $header_icon = "fas fa-signal text-green-500";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-6 pt-0">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                <h2 class="font-bold text-gray-700">လက်ရှိ Online အရေအတွက် - <span class="text-green-600"><?= count($online_users) ?></span> ဦး</h2>
                <button onclick="location.reload();" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm font-bold shadow-sm transition"><i class="fas fa-sync-alt mr-1"></i> Refresh</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal text-left">
                    <thead>
                        <tr class="bg-blue-50 text-blue-800 font-bold border-b-2 border-blue-200">
                            <th class="px-5 py-4 text-sm whitespace-nowrap">ID</th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap">အမည်</th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap">ဖုန်းနံပါတ်</th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap">လက်ကျန်ငွေ</th>
                            <th class="px-5 py-4 text-sm whitespace-nowrap">နောက်ဆုံးလှုပ်ရှားမှု</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($online_users) > 0): ?>
                            <?php foreach ($online_users as $u): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50 transition duration-150">
                                <td class="px-5 py-4 text-sm text-gray-600 font-bold">#<?= $u['id'] ?></td>
                                <td class="px-5 py-4 text-sm text-gray-800 font-bold flex items-center">
                                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                                    <?= htmlspecialchars($u['username']) ?>
                                    <?php if($u['id'] == 1) echo '<span class="ml-2 text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded border border-red-200">Admin</span>'; ?>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['phone_number']) ?></td>
                                <td class="px-5 py-4 text-sm font-bold text-blue-600"><?= number_format($u['balance'], 2) ?> Ks</td>
                                <td class="px-5 py-4 text-xs text-gray-500"><i class="far fa-clock mr-1"></i> <?= date('d-M-Y h:i:s A', strtotime($u['last_active'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-gray-500 italic">
                                    <i class="fas fa-user-slash text-3xl mb-3 text-gray-300 block"></i>
                                    လက်ရှိ Online ရောက်နေသူ မရှိပါ။
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>