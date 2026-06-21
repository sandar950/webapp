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

// စုစုပေါင်း ရရှိထားသော ကော်မရှင်ကို တွက်ချက်ခြင်း
$stmt_total = $conn->prepare("SELECT SUM(amount) as total_comm FROM commissions WHERE referrer_id = ?");
$stmt_total->bind_param("i", $user_id);
$stmt_total->execute();
$total_res = $stmt_total->get_result()->fetch_assoc();
$total_commission = $total_res['total_comm'] ?? 0;
$stmt_total->close();

// Pagination Setup
$limit = 15; // တစ်မျက်နှာတွင် ၁၅ ခုပြသမည်
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get Total Rows for Pagination
$count_stmt = $conn->prepare("SELECT COUNT(id) as total_rows FROM commissions WHERE referrer_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();
$total_rows = $count_res['total_rows'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_rows / $limit);

// ကော်မရှင်မှတ်တမ်းများကို Database မှ ဆွဲထုတ်ခြင်း (သူငယ်ချင်းအမည်ပါ ပူးတွဲယူမည်)
$query = "SELECT c.amount, c.description, c.created_at, u.username as friend_name 
          FROM commissions c 
          JOIN users u ON c.referred_user_id = u.id 
          WHERE c.referrer_id = ? 
          ORDER BY c.created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $user_id, $offset, $limit);
$stmt->execute();
$commissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php 
$page_title = __('title_commission_history') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24">

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="profile.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center pr-6 md:pr-10 tracking-wide"><?= __('title_commission_history') ?></h1>
    </div>

    <div class="p-4 md:p-8 max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow p-5 md:p-8 mb-5 md:mb-8 border border-gray-100 border-l-4 border-l-green-500 flex justify-between items-center relative overflow-hidden group">
            <div class="z-10">
                <p class="text-sm md:text-base text-gray-500 mb-1 md:mb-2 uppercase font-bold tracking-wide"><?= __('total_commission_received') ?></p>
                <p class="text-2xl md:text-4xl font-bold text-green-600 tracking-tight">+ <?= number_format($total_commission, 2) ?> <span class="text-sm md:text-xl font-normal text-gray-400">Ks</span></p>
            </div>
            <div class="text-green-100 text-6xl md:text-8xl absolute right-4 md:right-8 top-1/2 transform -translate-y-1/2 opacity-60 group-hover:scale-110 transition-transform duration-500"><i class="fas fa-hand-holding-usd"></i></div>
        </div>

        <?php if (count($commissions) > 0): ?>
            <div class="space-y-3 md:space-y-4">
                <?php foreach ($commissions as $comm): ?>
                    <div class="bg-white p-4 md:p-6 rounded-xl md:rounded-2xl shadow-sm hover:shadow-md border border-gray-100 flex justify-between items-center transition-all duration-300 group hover:-translate-y-0.5">
                        <div class="flex-1 pr-4">
                            <p class="text-gray-800 text-sm md:text-lg font-bold mb-1.5 group-hover:text-primary transition-colors">
                                <i class="fas fa-user-friends text-blue-500 mr-1.5 md:mr-2"></i> <?= htmlspecialchars($comm['friend_name']) ?>
                            </p>
                            <p class="text-[10px] md:text-xs text-gray-500 font-medium">
                                <i class="far fa-clock mr-1"></i> <?= date('d-M-Y h:i A', strtotime($comm['created_at'])) ?> 
                                <span class="hidden md:inline-block ml-2 px-2 py-0.5 bg-gray-50 text-gray-600 border border-gray-100 rounded-md"><?= htmlspecialchars($comm['description']) ?></span>
                            </p>
                            <p class="md:hidden text-[10px] text-gray-400 mt-1"><?= htmlspecialchars($comm['description']) ?></p>
                        </div>
                        <div class="text-right whitespace-nowrap">
                            <p class="font-bold text-green-600 text-base md:text-xl">+ <?= number_format($comm['amount']) ?> <span class="text-xs md:text-sm font-normal text-green-600/80">Ks</span></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-sm p-10 md:p-16 text-center mt-4 md:mt-8 border border-gray-100 max-w-2xl mx-auto">
                <i class="fas fa-box-open text-6xl md:text-7xl text-gray-200 mb-4 md:mb-6 block animate-pulse"></i>
                <p class="text-gray-500 text-sm md:text-lg font-medium"><?= __('no_commission_records') ?></p>
            </div>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center items-center mt-8 md:mt-10 mb-4 space-x-2 md:space-x-3">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-gray-600 hover:bg-gray-50 hover:text-primary hover:border-primary shadow-sm transition-all"><i class="fas fa-chevron-left text-xs md:text-sm"></i></a>
                <?php endif; ?>
                
                <span class="px-4 md:px-6 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-sm md:text-base font-bold text-gray-700 shadow-sm"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-gray-300 rounded-lg md:rounded-xl text-gray-600 hover:bg-gray-50 hover:text-primary hover:border-primary shadow-sm transition-all"><i class="fas fa-chevron-right text-xs md:text-sm"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>