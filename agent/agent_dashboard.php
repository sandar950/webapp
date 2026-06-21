<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../lang/language.php';

// Agent (ကိုယ်စားလှယ်) သို့မဟုတ် Admin သာ ဝင်ခွင့်ပြုမည်
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$is_agent = isset($_SESSION['role']) && $_SESSION['role'] === 'agent';

if (!$is_agent && !$is_admin) {
    die("<h2 style='text-align:center; margin-top:50px;'>Access Denied. ကိုယ်စားလှယ် (Agent) သို့မဟုတ် Admin အကောင့်ဖြင့်သာ ဝင်ရောက်ခွင့်ရှိသည်။</h2>");
}

$agent_id = $_SESSION['user_id'];
// Admin ဝင်ကြည့်ပါက သီးသန့် Agent ID ကို ရွေးချယ်ခွင့်ပေးမည် (ဥပမာ - agent_dashboard.php?agent_id=5)
if ($is_admin && isset($_GET['agent_id'])) {
    $agent_id = intval($_GET['agent_id']);
}

// Database တွင် Agent အတွက် လိုအပ်သော Column များ မရှိသေးပါက အလိုအလျောက် ထည့်သွင်းပေးမည်
$check_agent_cols = $conn->query("SHOW COLUMNS FROM users LIKE 'agent_commission_percent'");
if ($check_agent_cols && $check_agent_cols->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD agent_commission_percent DECIMAL(5, 2) DEFAULT 0.00");
    $conn->query("ALTER TABLE users ADD agent_share_percent DECIMAL(5, 2) DEFAULT 0.00");
}

// Agent ၏ အချက်အလက်များကို ဆွဲထုတ်မည်
$stmt = $conn->prepare("SELECT username, balance, agent_commission_percent, agent_share_percent, referral_code FROM users WHERE id = ?");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$agent_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ဖိတ်ခေါ်လင့်ခ် တည်ဆောက်ခြင်း
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$uri_parts = explode('/', $_SERVER['REQUEST_URI']);
array_pop($uri_parts);
$base_url = $protocol . "://" . $host . implode('/', $uri_parts);
$referral_link = $base_url . "/register.php?ref=" . ($agent_data['referral_code'] ?? '');

// Agent အောက်ရှိ User များ၏ အချက်အလက် (Downlines)
$stmt_users = $conn->prepare("SELECT COUNT(id) as total_users, SUM(balance) as total_user_balance FROM users WHERE referred_by = ?");
$stmt_users->bind_param("i", $agent_id);
$stmt_users->execute();
$user_stats = $stmt_users->get_result()->fetch_assoc();
$stmt_users->close();

// ယနေ့အတွက် Downline များ၏ ထိုးကြေးနှင့် လျော်ကြေး
$query_bets = "SELECT 
    SUM(b.amount - IFNULL(b.discount_amount, 0)) as today_turnover,
    SUM(CASE WHEN b.status = 'win' THEN b.amount * IFNULL(b.odds, IF(LENGTH(b.bet_number) = 2, 80, 500)) ELSE 0 END) as today_payout
    FROM bets b 
    JOIN users u ON b.user_id = u.id 
    WHERE u.referred_by = ? AND DATE(b.created_at) = CURDATE()";
$stmt_bets = $conn->prepare($query_bets);
$stmt_bets->bind_param("i", $agent_id);
$stmt_bets->execute();
$bet_stats = $stmt_bets->get_result()->fetch_assoc();
$stmt_bets->close();

// နောက်ဆုံးဝင်လာသော User များ
$query_recent_users = "SELECT id, username, phone_number, balance, created_at FROM users WHERE referred_by = ? ORDER BY created_at DESC LIMIT 5";
$stmt_recent = $conn->prepare($query_recent_users);
$stmt_recent->bind_param("i", $agent_id);
$stmt_recent->execute();
$recent_users = $stmt_recent->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_recent->close();

// ၇ ရက်အတွင်း အရောင်းအဝယ် (Chart အတွက်)
$chart_data_query = "SELECT 
    DATE(b.created_at) as date, 
    SUM(b.amount - IFNULL(b.discount_amount, 0)) as income, 
    SUM(CASE WHEN b.status = 'win' THEN b.amount * IFNULL(b.odds, IF(LENGTH(b.bet_number) = 2, 80, 500)) ELSE 0 END) as payout
    FROM bets b
    JOIN users u ON b.user_id = u.id
    WHERE u.referred_by = ? AND b.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(b.created_at)
    ORDER BY DATE(b.created_at) ASC";
$stmt_chart = $conn->prepare($chart_data_query);
$stmt_chart->bind_param("i", $agent_id);
$stmt_chart->execute();
$chart_result = $stmt_chart->get_result();
$chart_labels = [];
$chart_incomes = [];
$chart_payouts = [];
if ($chart_result) {
    while ($row = $chart_result->fetch_assoc()) {
        $chart_labels[] = date('d-M', strtotime($row['date']));
        $chart_incomes[] = floatval($row['income']);
        $chart_payouts[] = floatval($row['payout']);
    }
}
$stmt_chart->close();

$page_title = "Agent Dashboard - Thai 2D3D";
require_once __DIR__ . '/../includes/header.php';
?>
<body class="bg-gray-100 pb-20 font-sans">
    <!-- Agent Header -->
    <div class="bg-gradient-to-r from-purple-800 to-indigo-900 text-white p-4 sticky top-0 z-40 shadow-lg flex justify-between items-center">
        <h1 class="text-xl font-bold flex-shrink-0"><i class="fas fa-user-tie mr-2"></i> Agent Panel <?= $is_admin ? '<span class="text-xs bg-red-500 px-2 py-1 rounded ml-2">(Admin View)</span>' : '' ?></h1>
        <?php if ($is_admin): ?>
            <a href="../admin/admin_dashboard.php" class="bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg text-sm font-bold backdrop-blur-sm border border-white/20 transition-all shadow-sm"><i class="fas fa-arrow-left mr-1"></i> Admin သို့ပြန်သွားမည်</a>
        <?php else: ?>
            <a href="logout.php" class="bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg text-sm font-bold backdrop-blur-sm border border-white/20 transition-all shadow-sm"><i class="fas fa-sign-out-alt mr-1"></i> ထွက်မည်</a>
        <?php endif; ?>
    </div>

    <div class="max-w-5xl mx-auto p-4 md:p-6 mt-2">
        <!-- Welcome Card -->
        <div class="bg-white rounded-2xl shadow-md p-5 mb-6 border-l-4 border-indigo-500 flex flex-col md:flex-row justify-between md:items-center gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">မင်္ဂလာပါ, <?= htmlspecialchars($agent_data['username']) ?></h2>
                <p class="text-gray-500 text-sm mt-1">သင်၏ ကိုယ်စားလှယ် (Agent) အချက်အလက်များ</p>
            </div>
            <div class="flex gap-4">
                <div class="bg-indigo-50 px-4 py-2 rounded-xl border border-indigo-100 text-center">
                    <p class="text-xs text-indigo-800 font-bold mb-1">ကော်မရှင်</p>
                    <p class="text-lg font-bold text-indigo-600"><?= floatval($agent_data['agent_commission_percent']) ?> %</p>
                </div>
                <div class="bg-purple-50 px-4 py-2 rounded-xl border border-purple-100 text-center">
                    <p class="text-xs text-purple-800 font-bold mb-1">ဒိုင်ရှယ်ယာ</p>
                    <p class="text-lg font-bold text-purple-600"><?= floatval($agent_data['agent_share_percent']) ?> %</p>
                </div>
            </div>
        </div>

        <!-- Referral Link -->
        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 mb-6 flex flex-col md:flex-row justify-between items-center gap-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-200 text-indigo-700 rounded-full flex items-center justify-center text-lg shrink-0"><i class="fas fa-link"></i></div>
                <div>
                    <p class="text-sm font-bold text-indigo-900 mb-1">သင်၏ ဖိတ်ခေါ်လင့်ခ် (Referral Link)</p>
                    <p class="text-xs text-indigo-700 break-all" id="agentRefLink"><?= htmlspecialchars($referral_link) ?></p>
                </div>
            </div>
            <button onclick="copyAgentRef()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow transition shrink-0 whitespace-nowrap">
                <i class="fas fa-copy mr-1"></i> Copy ကူးမည်
            </button>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 mb-1 font-bold">သင်၏ လက်ကျန်ငွေ (Credit)</p>
                    <p class="text-2xl font-bold text-green-600"><?= number_format($agent_data['balance']) ?> <span class="text-sm text-gray-400">Ks</span></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-green-100 text-green-500 flex items-center justify-center text-xl"><i class="fas fa-wallet"></i></div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 mb-1 font-bold">သင်၏ User အရေအတွက်</p>
                    <p class="text-2xl font-bold text-blue-600"><?= number_format($user_stats['total_users']) ?> <span class="text-sm text-gray-400">ဦး</span></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-500 flex items-center justify-center text-xl"><i class="fas fa-users"></i></div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 mb-1 font-bold">User များ၏ လက်ကျန်ငွေ</p>
                    <p class="text-2xl font-bold text-orange-500"><?= number_format($user_stats['total_user_balance']) ?> <span class="text-sm text-gray-400">Ks</span></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-500 flex items-center justify-center text-xl"><i class="fas fa-coins"></i></div>
            </div>
        </div>

        <!-- Today's Turnover & Payout -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <div class="bg-gradient-to-br from-blue-500 to-cyan-500 rounded-2xl shadow-md p-6 text-white relative overflow-hidden">
                <i class="fas fa-chart-line absolute -right-4 -top-4 text-white/10 text-8xl"></i>
                <div class="relative z-10">
                    <p class="text-blue-100 text-sm font-medium mb-1">ယနေ့ User များ၏ စုစုပေါင်း ထိုးကြေး</p>
                    <p class="text-3xl font-bold">+ <?= number_format($bet_stats['today_turnover'] ?? 0) ?> Ks</p>
                    <?php if ($agent_data['agent_commission_percent'] > 0): $est_comm = ($bet_stats['today_turnover'] ?? 0) * ($agent_data['agent_commission_percent'] / 100); ?>
                        <p class="mt-2 text-xs bg-white/20 inline-block px-2 py-1 rounded">ခန့်မှန်း ကော်မရှင်ရငွေ: <?= number_format($est_comm) ?> Ks</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl shadow-md p-6 text-white relative overflow-hidden">
                <i class="fas fa-hand-holding-usd absolute -right-4 -top-4 text-white/10 text-8xl"></i>
                <div class="relative z-10">
                    <p class="text-rose-100 text-sm font-medium mb-1">ယနေ့ User များအား ပေးရသော လျော်ငွေ</p>
                    <p class="text-3xl font-bold">- <?= number_format($bet_stats['today_payout'] ?? 0) ?> Ks</p>
                    <?php if ($agent_data['agent_share_percent'] > 0): 
                        $agent_share_profit = (($bet_stats['today_turnover'] ?? 0) - ($bet_stats['today_payout'] ?? 0)) * ($agent_data['agent_share_percent'] / 100);
                    ?>
                        <p class="mt-2 text-xs <?= $agent_share_profit >= 0 ? 'bg-green-500/50' : 'bg-black/20' ?> inline-block px-2 py-1 rounded">ဒိုင်ရှယ်ယာ အရှုံး/အမြတ်: <?= $agent_share_profit > 0 ? '+' : '' ?><?= number_format($agent_share_profit) ?> Ks</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="mb-8">
            <h3 class="font-bold text-gray-700 mb-3"><i class="fas fa-bolt text-yellow-500 mr-2"></i> အမြန်လုပ်ဆောင်ရန် (Quick Actions)</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <a href="agent_users.php?action=add" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col items-center justify-center gap-2 hover:bg-indigo-50 hover:border-indigo-200 transition group">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center group-hover:scale-110 transition"><i class="fas fa-user-plus text-lg"></i></div>
                    <span class="text-xs font-bold text-gray-700 group-hover:text-indigo-700">အကောင့်သစ်ဖွင့်မည်</span>
                </a>
                <a href="agent_transfer.php" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col items-center justify-center gap-2 hover:bg-green-50 hover:border-green-200 transition group">
                    <div class="w-10 h-10 rounded-full bg-green-100 text-green-600 flex items-center justify-center group-hover:scale-110 transition"><i class="fas fa-exchange-alt text-lg"></i></div>
                    <span class="text-xs font-bold text-gray-700 group-hover:text-green-700">ငွေသွင်း/ထုတ်ပေးမည်</span>
                </a>
                <a href="agent_history.php" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col items-center justify-center gap-2 hover:bg-blue-50 hover:border-blue-200 transition group">
                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center group-hover:scale-110 transition"><i class="fas fa-history text-lg"></i></div>
                    <span class="text-xs font-bold text-gray-700 group-hover:text-blue-700">ထိုးကြေးမှတ်တမ်းများ</span>
                </a>
                <a href="agent_commissions.php" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col items-center justify-center gap-2 hover:bg-orange-50 hover:border-orange-200 transition group">
                    <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center group-hover:scale-110 transition"><i class="fas fa-hand-holding-usd text-lg"></i></div>
                    <span class="text-xs font-bold text-gray-700 group-hover:text-orange-700">ကော်မရှင်မှတ်တမ်း</span>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Chart Section -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-5 border border-gray-100 flex flex-col">
                <h3 class="font-bold text-gray-700 mb-4"><i class="fas fa-chart-bar text-blue-500 mr-2"></i> ၇ ရက်အတွင်း အရောင်းအဝယ်ဂရပ် (Turnover vs Payout)</h3>
                <div class="relative flex-1 min-h-[300px] w-full">
                    <canvas id="agentChart"></canvas>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="lg:col-span-1 bg-white rounded-xl shadow-md p-5 border border-gray-100">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h3 class="font-bold text-gray-700"><i class="fas fa-user-clock text-indigo-500 mr-2"></i> နောက်ဆုံးဝင်လာသော User များ</h3>
                    <a href="agent_users.php" class="text-sm text-indigo-600 hover:underline font-bold">အားလုံးကြည့်မည်</a>
                </div>
                <div class="space-y-3">
                    <?php if (count($recent_users) > 0): ?>
                        <?php foreach ($recent_users as $user): ?>
                            <div class="flex items-center p-2 rounded-lg hover:bg-gray-50 border border-gray-50 transition">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3 text-indigo-500 font-bold shadow-sm">
                                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                </div>
                                <div class="flex-1">
                                    <p class="font-bold text-sm text-gray-800"><?= htmlspecialchars($user['username']) ?></p>
                                    <p class="text-[10px] text-gray-500"><i class="fas fa-phone-alt text-[8px] mr-1"></i><?= htmlspecialchars($user['phone_number']) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-sm text-green-600"><?= number_format($user['balance']) ?> Ks</p>
                                    <p class="text-[10px] text-gray-400"><i class="far fa-clock mr-1"></i><?= date('d-M h:i A', strtotime($user['created_at'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 italic text-center py-4">User အသစ် မရှိသေးပါ။</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation (Visible only on small screens) -->
    <div class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 shadow-[0_-5px_10px_rgba(0,0,0,0.05)] z-50 flex justify-around items-center pb-safe">
        <a href="agent_dashboard.php<?= $is_admin && isset($_GET['agent_id']) ? '?agent_id='.$agent_id : '' ?>" class="flex flex-col items-center py-2 px-4 text-indigo-600">
            <i class="fas fa-home text-xl mb-1"></i>
            <span class="text-[10px] font-bold">ပင်မ</span>
        </a>
        <a href="agent_users.php<?= $is_admin && isset($_GET['agent_id']) ? '?agent_id='.$agent_id : '' ?>" class="flex flex-col items-center py-2 px-4 text-gray-400 hover:text-indigo-600 transition">
            <i class="fas fa-users text-xl mb-1"></i>
            <span class="text-[10px] font-bold">Users</span>
        </a>
        <a href="agent_transfer.php<?= $is_admin && isset($_GET['agent_id']) ? '?agent_id='.$agent_id : '' ?>" class="flex flex-col items-center py-2 px-4 text-gray-400 hover:text-indigo-600 transition">
            <i class="fas fa-exchange-alt text-xl mb-1"></i>
            <span class="text-[10px] font-bold">ငွေသွင်း/ထုတ်</span>
        </a>
    </div>

    <script>
        function copyAgentRef() {
            var copyText = document.getElementById("agentRefLink").innerText;
            navigator.clipboard.writeText(copyText).then(function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Copy ကူးပြီးပါပြီ',
                    text: 'ဖိတ်ခေါ်လင့်ခ်ကို သူငယ်ချင်းများထံ ပေးပို့နိုင်ပါပြီ။',
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('agentChart').getContext('2d');
        const agentChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [
                    {
                        label: 'စုစုပေါင်း ထိုးကြေး (Turnover)',
                        data: <?= json_encode($chart_incomes) ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)', // Tailwind blue-500
                        borderRadius: 4
                    },
                    {
                        label: 'ပေးရသော လျော်ငွေ (Payout)',
                        data: <?= json_encode($chart_payouts) ?>,
                        backgroundColor: 'rgba(239, 68, 68, 0.8)', // Tailwind red-500
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(value) { return value.toLocaleString() + ' Ks'; } } }
                }
            }
        });
    </script>
</body>
</html>