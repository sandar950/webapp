<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Admin (User ID 1) သာ ဝင်ခွင့်ပြုမည်
require_main_admin();

// 2D သို့မဟုတ် 3D ရွေးချယ်မှု (Default အနေဖြင့် 2D ပြသမည်)
$type = isset($_GET['type']) && $_GET['type'] == '3d' ? 3 : 2;

// Pending ဖြစ်နေသော ဂဏန်းများကို စုစုပေါင်းထိုးကြေးအများဆုံးမှ အနည်းဆုံးသို့ အစဉ်လိုက်ဆွဲထုတ်ခြင်း
$query = "SELECT bet_number, SUM(amount) as total_amount, COUNT(id) as bet_count 
          FROM bets 
          WHERE status = 'pending' AND LENGTH(bet_number) = ? 
          GROUP BY bet_number 
          ORDER BY total_amount DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $type);
$stmt->execute();
$hot_numbers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Limit ပမာဏ ဆွဲထုတ်ခြင်း (Progress Bar တွက်ရန်)
$setting_key = $type == 3 ? 'max_limit_per_3d_number' : 'max_limit_per_number';
$setting_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$setting_key'");
$setting_row = $setting_stmt->fetch_assoc();
$max_limit = $setting_row ? floatval($setting_row['setting_value']) : ($type == 3 ? 10000 : 20000);
?>

<?php 
$page_title = __('admin_ledger_page_title');
require_once __DIR__ . '/../includes/header.php'; 
?>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_ledger_header_title');
    $header_icon = "fas fa-fire text-red-500";
    require_once __DIR__ . '/admin_header.php';
    ?>
    
    <div class="p-4 md:p-6 pt-0">
        <!-- Tabs -->
        <div class="bg-white flex justify-around border-b text-sm font-bold text-gray-500 shadow-sm mb-6 rounded-t-xl overflow-hidden">
            <a href="?type=2d" class="py-4 w-1/2 text-center <?= $type == 2 ? 'text-red-600 border-b-4 border-red-600 bg-red-50' : 'hover:text-gray-700' ?>"><i class="fas fa-dice-two mr-1"></i> <?= __('admin_ledger_tab_2d') ?></a>
            <a href="?type=3d" class="py-4 w-1/2 text-center <?= $type == 3 ? 'text-red-600 border-b-4 border-red-600 bg-red-50' : 'hover:text-gray-700' ?>"><i class="fas fa-dice mr-1"></i> <?= __('admin_ledger_tab_3d') ?></a>
        </div>

        <div class="mb-4 flex flex-col md:flex-row justify-between items-stretch md:items-center text-sm text-gray-600 gap-4 bg-white p-4 rounded-b-xl shadow-md">
            <div>
                <p><?= __('admin_ledger_current_limit') ?> <strong class="text-blue-600"><?= number_format($max_limit) ?> Ks</strong></p>
                <p><?= __('admin_ledger_total_numbers') ?> <strong><?= count($hot_numbers) ?></strong> <?= __('admin_ledger_kwek') ?></p>
            </div>
            
            <!-- Search Box -->
            <div class="w-full md:w-1/3 relative">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text" id="searchInput" onkeyup="searchTable()" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500" placeholder="<?= __('admin_ledger_search_placeholder') ?>">
            </div>
            
            <form action="admin_export.php" method="GET" class="flex items-center gap-2 self-end">
                <input type="hidden" name="type" value="bets">
                <select name="period" class="px-2 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-green-500">
                    <option value="all"><?= __('admin_users_period_all') ?></option>
                    <option value="today"><?= __('admin_users_period_today') ?></option>
                    <option value="this_week"><?= __('admin_users_period_week') ?></option>
                    <option value="this_month"><?= __('admin_users_period_month') ?></option>
                </select>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition whitespace-nowrap"><i class="fas fa-file-excel mr-1"></i> <?= __('admin_ledger_export') ?></button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (count($hot_numbers) > 0): ?>
                <!-- Mobile View: Cards -->
                <div id="ledgerCards" class="md:hidden divide-y divide-gray-100">
                    <?php foreach ($hot_numbers as $index => $row): 
                        $percentage = min(100, ($row['total_amount'] / $max_limit) * 100);
                        $bar_color = $percentage > 80 ? 'bg-red-500' : ($percentage > 50 ? 'bg-yellow-500' : 'bg-green-500');
                    ?>
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <p class="text-xl font-bold text-gray-800 tracking-wider"><?= htmlspecialchars($row['bet_number']) ?></p>
                                <p class="text-xs text-gray-500"><?= number_format($row['bet_count']) ?> <?= __('admin_ledger_count_unit') ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-base font-bold text-red-600"><?= number_format($row['total_amount']) ?> Ks</p>
                                <?php if ($percentage >= 100): ?>
                                    <span class="text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full border border-red-300 align-middle">Full</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="<?= $bar_color ?> h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop View: Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table id="ledgerTable" class="min-w-full leading-normal text-left">
                        <thead>
                            <tr class="bg-red-50 text-red-800 font-bold border-b-2 border-red-200">
                                <th class="px-5 py-3 text-sm"><?= __('admin_ledger_col_no') ?></th>
                                <th class="px-5 py-3 text-sm"><?= __('admin_ledger_col_number') ?></th>
                                <th class="px-5 py-3 text-sm text-center"><?= __('admin_ledger_col_count') ?></th>
                                <th class="px-5 py-3 text-sm text-right"><?= __('admin_ledger_col_total_amount') ?></th>
                                <th class="px-5 py-3 text-sm w-1/3"><?= __('admin_ledger_col_limit_status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hot_numbers as $index => $row): 
                                $percentage = min(100, ($row['total_amount'] / $max_limit) * 100);
                                $bar_color = $percentage > 80 ? 'bg-red-500' : ($percentage > 50 ? 'bg-yellow-500' : 'bg-green-500');
                            ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                                <td class="px-5 py-3 text-sm text-gray-500"><?= $index + 1 ?></td>
                                <td class="px-5 py-3 text-xl font-bold text-gray-800 tracking-wider">
                                    <?= htmlspecialchars($row['bet_number']) ?>
                                    <?php if ($percentage >= 100): ?>
                                        <span class="ml-2 text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full border border-red-300 align-middle">Full</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 text-center"><?= number_format($row['bet_count']) ?> <?= __('admin_ledger_count_unit') ?></td>
                                <td class="px-5 py-3 text-base font-bold text-red-600 text-right"><?= number_format($row['total_amount']) ?> Ks</td>
                                <td class="px-5 py-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 mb-1">
                                        <div class="<?= $bar_color ?> h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <p class="text-[10px] text-gray-500 text-right"><?= number_format($percentage, 1) ?>%</p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="px-5 py-8 text-center text-gray-500 italic">
                    <i class="fas fa-check-circle text-2xl text-green-400 block mb-2"></i>
                    <?= __('admin_ledger_no_records') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function searchTable() {
            let input = document.getElementById("searchInput");
            let filter = input.value.toUpperCase();
            let table = document.getElementById("ledgerTable");
            let cards = document.getElementById("ledgerCards");

            // For Table View
            if (table) {
                let tr = table.getElementsByTagName("tr");
                for (let i = 1; i < tr.length; i++) {
                    let td = tr[i].getElementsByTagName("td")[1]; // ဂဏန်း ကော်လံ
                    if (td) {
                        let txtValue = td.textContent || td.innerText;
                        tr[i].style.display = txtValue.trim().toUpperCase().indexOf(filter) > -1 ? "" : "none";
                    }
                }
            }
            // For Card View
            if (cards) {
                let cardItems = cards.children;
                for (let i = 0; i < cardItems.length; i++) {
                    let txtValue = cardItems[i].textContent || cardItems[i].innerText;
                    if (txtValue.trim().toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
    </script>
</body>
</html>