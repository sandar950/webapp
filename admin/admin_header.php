<?php
// ခေါင်းစဉ်နှင့် အိုင်ကွန်ကို မူလတန်ဖိုးများ သတ်မှတ်ထားခြင်း
require_once __DIR__ . '/../core/auth_helper.php';

$header_title = $header_title ?? 'Admin Control Panel';
$header_icon = $header_icon ?? 'fas fa-cogs';

/**
 * Fetches all pending counts for admin badges in a single query.
 * @param mysqli $conn The database connection object.
 * @return array An associative array of pending counts.
 */
function get_all_pending_counts($conn) {
    $counts = [
        'deposits' => 0, 'withdrawals' => 0, 'support' => 0,
        'verifications' => 0, 'loans' => 0, 'total_tx' => 0
    ];

    if (!$conn) return $counts;

    $query = "
        SELECT 
            (SELECT COUNT(id) FROM deposits WHERE status = 'pending') as pending_deposits,
            (SELECT COUNT(id) FROM withdrawals WHERE status = 'pending') as pending_withdrawals,
            (SELECT COUNT(id) FROM support_messages WHERE status = 'pending') as pending_support,
            (SELECT COUNT(id) FROM users WHERE verification_status = 'pending') as pending_verifications,
            (SELECT COUNT(id) FROM loans WHERE status = 'pending') as pending_loans
    ";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $counts['deposits'] = (int)($row['pending_deposits'] ?? 0);
        $counts['withdrawals'] = (int)($row['pending_withdrawals'] ?? 0);
        $counts['support'] = (int)($row['pending_support'] ?? 0);
        $counts['verifications'] = (int)($row['pending_verifications'] ?? 0);
        $counts['loans'] = (int)($row['pending_loans'] ?? 0);
        $counts['total_tx'] = $counts['deposits'] + $counts['withdrawals'];
    }
    return $counts;
}

$pending_counts = get_all_pending_counts($conn);
?>

<style>
    /* Dropdown Animation CSS */
    .admin-dropdown-menu {
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: none;
    }
    .admin-dropdown-menu.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        pointer-events: auto;
    }

    /* Dark Mode Global Overrides */
    html.dark body { background-color: #111827 !important; color: #f9fafb !important; }
    html.dark .bg-white { background-color: #1f2937 !important; border-color: #374151 !important; }
    html.dark .bg-gray-50, html.dark .bg-gray-100, html.dark .bg-gray-200 { background-color: #374151 !important; color: #f9fafb !important; border-color: #4b5563 !important; }
    html.dark .text-gray-500, html.dark .text-gray-600, html.dark .text-gray-700, html.dark .text-gray-800, html.dark .text-gray-900 { color: #d1d5db !important; }
    html.dark .border-gray-100, html.dark .border-gray-200, html.dark .border-gray-300 { border-color: #374151 !important; }
    
    /* Colored background overrides for buttons/labels */
    html.dark .bg-red-50 { background-color: rgba(239, 68, 68, 0.15) !important; color: #fca5a5 !important; border-color: rgba(239, 68, 68, 0.3) !important; }
    html.dark .bg-green-50 { background-color: rgba(34, 197, 94, 0.15) !important; color: #86efac !important; border-color: rgba(34, 197, 94, 0.3) !important; }
    html.dark .bg-blue-50 { background-color: rgba(59, 130, 246, 0.15) !important; color: #93c5fd !important; border-color: rgba(59, 130, 246, 0.3) !important; }
    html.dark .bg-purple-50 { background-color: rgba(168, 85, 247, 0.15) !important; color: #d8b4fe !important; border-color: rgba(168, 85, 247, 0.3) !important; }
    html.dark .bg-orange-50 { background-color: rgba(249, 115, 22, 0.15) !important; color: #fdba74 !important; border-color: rgba(249, 115, 22, 0.3) !important; }
    html.dark .bg-yellow-50 { background-color: rgba(234, 179, 8, 0.15) !important; color: #fde047 !important; border-color: rgba(234, 179, 8, 0.3) !important; }
    html.dark .bg-emerald-50 { background-color: rgba(16, 185, 129, 0.15) !important; color: #6ee7b7 !important; border-color: rgba(16, 185, 129, 0.3) !important; }
    
    /* Table specific overrides */
    html.dark table { color: #d1d5db !important; }
    html.dark thead { background-color: #374151 !important; color: #f9fafb !important; border-bottom: 1px solid #4b5563 !important; }
    html.dark thead th { color: #f9fafb !important; }
    html.dark tbody tr { border-bottom-color: #374151 !important; }
    html.dark tbody tr:hover { background-color: #374151 !important; }
    html.dark .divide-y > :not([hidden]) ~ :not([hidden]) { border-color: #374151 !important; }
    
    /* Form inputs in dark mode */
    html.dark input[type="text"], 
    html.dark input[type="number"], 
    html.dark input[type="password"], 
    html.dark input[type="date"],
    html.dark select, 
    html.dark textarea { 
        background-color: #374151 !important; 
        color: #f9fafb !important; 
        border-color: #4b5563 !important; 
    }
    
    /* Modals & Dialogs */
    html.dark .fixed > .bg-white { background-color: #1f2937 !important; border-color: #374151 !important; }
    
    /* Text color classes override to be readable */
    html.dark .text-blue-600, html.dark .text-blue-700 { color: #60a5fa !important; }
    html.dark .text-red-600, html.dark .text-red-700 { color: #f87171 !important; }
    html.dark .text-green-600, html.dark .text-green-700 { color: #4ade80 !important; }
    html.dark .text-purple-600, html.dark .text-purple-700 { color: #c084fc !important; }
    html.dark .text-orange-600, html.dark .text-orange-700 { color: #fb923c !important; }
    
    /* Hover effects for custom backgrounds */
    html.dark .hover\:bg-gray-50:hover, 
    html.dark .hover\:bg-gray-100:hover,
    html.dark .hover\:bg-blue-50:hover,
    html.dark .hover\:bg-red-50:hover,
    html.dark .hover\:bg-green-50:hover,
    html.dark .hover\:bg-purple-50:hover,
    html.dark .hover\:bg-yellow-50:hover {
        background-color: #4b5563 !important;
    }
</style>

<div class="bg-gradient-to-r from-[#1a428a] to-blue-800 text-white px-2 py-3 sm:p-4 sticky top-0 z-40 shadow-lg flex justify-between items-center gap-2">
    <h1 class="text-base md:text-xl font-bold flex-shrink-0 truncate"><i class="<?= htmlspecialchars($header_icon) ?> mr-2"></i> <?= htmlspecialchars($header_title) ?></h1>
    <div class="flex items-center gap-2 sm:gap-4">
        <button id="themeToggleBtn" class="text-gray-300 hover:text-white transition" title="<?= __('admin_dark_mode_toggle') ?>">
            <i class="fas fa-moon text-lg"></i>
        </button>
        <button id="soundToggleBtn" class="text-gray-300 hover:text-white transition" title="<?= __('admin_sound_alert_toggle') ?>">
            <i class="fas fa-bell text-lg"></i>
        </button>
        <a href="index.php" class="bg-white/10 hover:bg-white/20 px-3 py-2 sm:px-4 rounded-lg text-sm font-bold backdrop-blur-sm border border-white/20 transition-all shadow-sm flex items-center gap-1.5"><i class="fas fa-home"></i> <span class="hidden sm:inline"><?= __('admin_home') ?></span></a>
    </div>
</div>

<div class="bg-white shadow-md border-b border-gray-100 px-2 py-2 sm:px-4 sm:py-3 flex flex-wrap gap-2 sm:gap-3 items-center justify-center sm:justify-start mb-6 text-sm font-bold relative z-30">
    <a href="admin_dashboard.php" class="bg-gray-50 text-gray-700 hover:bg-blue-50 hover:text-blue-600 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl border border-gray-200 hover:border-blue-300 transition-all shadow-sm whitespace-nowrap"><i class="fas fa-tachometer-alt mr-1 text-blue-500"></i> <?= __('admin_dashboard') ?></a>
    <a href="admin_declare_result.php" class="bg-red-50 text-red-700 hover:bg-red-100 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl border border-red-200 hover:border-red-300 transition-all shadow-sm whitespace-nowrap"><i class="fas fa-bullhorn mr-1 text-red-500"></i> <?= __('admin_declare_result') ?></a>

    <div class="relative dropdown-container">
        <button id="financeMenuBtn" onclick="toggleDropdown('financeMenu')" class="bg-green-50 text-green-700 hover:bg-green-100 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl border border-green-200 hover:border-green-300 transition-all shadow-sm flex items-center relative whitespace-nowrap">
            <i class="fas fa-wallet mr-1 text-green-600"></i> <?= __('admin_finance_manage') ?>
            <?php if($pending_counts['total_tx'] > 0 || $pending_counts['loans'] > 0): ?>
                <span class="parent-badge absolute -top-1 -right-1 flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span></span>
            <?php endif; ?>
            <i class="fas fa-chevron-down ml-2 text-xs"></i>
        </button>
        <div id="financeMenu" class="admin-dropdown admin-dropdown-menu absolute left-0 mt-3 w-60 bg-white rounded-2xl shadow-2xl border border-gray-100 py-2 flex flex-col z-50">
            <a id="txMenuLink" href="admin_transactions.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-blue-50 rounded-xl text-gray-700 hover:text-blue-700 flex items-center relative transition-colors">
                <i class="fas fa-exchange-alt w-5 text-center mr-2 text-blue-500"></i> <?= __('admin_deposit_withdraw') ?>
                <?php if($pending_counts['total_tx'] > 0): ?><span id="txMenuBadge" class="ml-auto bg-red-500 text-white text-[10px] px-1.5 rounded-full shadow-sm"><?= $pending_counts['total_tx'] ?></span><?php endif; ?>
            </a>
            <a href="admin_auto_approve.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-green-50 rounded-xl text-gray-700 hover:text-green-700 flex items-center transition-colors">
                <i class="fas fa-robot w-5 text-center mr-2 text-green-500"></i> <?= __('admin_auto_approve') ?>
            </a>
            <a href="admin_transfers.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-purple-50 rounded-xl text-gray-700 hover:text-purple-700 flex items-center transition-colors">
                <i class="fas fa-random w-5 text-center mr-2 text-purple-500"></i> <?= __('admin_transfer_history') ?>
            </a>
            <a href="admin_loans.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-green-50 rounded-xl text-gray-700 hover:text-green-700 flex items-center relative transition-colors">
                <i class="fas fa-hand-holding-usd w-5 text-center mr-2 text-green-500"></i> <?= __('admin_manage_loans') ?>
                <?php if($pending_counts['loans'] > 0): ?><span class="ml-auto bg-red-500 text-white text-[10px] px-1.5 rounded-full shadow-sm"><?= $pending_counts['loans'] ?></span><?php endif; ?>
            </a>
            <a href="admin_commissions_overview.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-emerald-50 rounded-xl text-gray-700 hover:text-emerald-700 flex items-center transition-colors">
                <i class="fas fa-hand-holding-usd w-5 text-center mr-2 text-emerald-500"></i> <?= __('admin_commissions') ?>
            </a>
            <div class="h-px bg-gray-100 my-1 mx-3"></div>
            <a href="admin_reports.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-orange-50 rounded-xl text-gray-700 hover:text-orange-700 flex items-center transition-colors">
                <i class="fas fa-chart-line w-5 text-center mr-2 text-orange-500"></i> <?= __('admin_profit_loss') ?>
            </a>
        </div>
    </div>

    <div class="relative dropdown-container">
        <button onclick="toggleDropdown('numberMenu')" class="bg-orange-50 text-orange-700 hover:bg-orange-100 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl border border-orange-200 hover:border-orange-300 transition-all shadow-sm flex items-center whitespace-nowrap">
            <i class="fas fa-dice mr-1 text-orange-600"></i> <?= __('admin_numbers') ?> <i class="fas fa-chevron-down ml-2 text-xs"></i>
        </button>
        <div id="numberMenu" class="admin-dropdown admin-dropdown-menu absolute left-0 sm:left-auto mt-3 w-60 bg-white rounded-2xl shadow-2xl border border-gray-100 py-2 flex flex-col z-50">
            <a href="admin_ledger.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-red-50 rounded-xl text-gray-700 hover:text-red-700 flex items-center transition-colors">
                <i class="fas fa-fire w-5 text-center mr-2 text-red-500"></i> <?= __('admin_hot_numbers') ?>
            </a>
            <a href="admin_blocked_numbers.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-100 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-ban w-5 text-center mr-2 text-gray-700"></i> <?= __('admin_blocked_numbers') ?>
            </a>
            <a href="admin_results_history.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-blue-50 rounded-xl text-gray-700 hover:text-blue-700 flex items-center transition-colors">
                <i class="fas fa-history w-5 text-center mr-2 text-gray-500"></i> <?= __('admin_result_history') ?>
            </a>
        </div>
    </div>

    <div class="relative dropdown-container">
        <button id="userMenuBtn" onclick="toggleDropdown('userMenu')" class="bg-purple-50 text-purple-700 hover:bg-purple-100 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl border border-purple-200 hover:border-purple-300 transition-all shadow-sm flex items-center relative whitespace-nowrap">
            <i class="fas fa-users mr-1 text-purple-600"></i> <?= __('admin_users_support') ?>
            <?php if($pending_counts['verifications'] > 0 || $pending_counts['support'] > 0): ?>
                <span class="parent-badge absolute -top-1 -right-1 flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span></span>
            <?php endif; ?>
            <i class="fas fa-chevron-down ml-2 text-xs"></i>
        </button>
        <div id="userMenu" class="admin-dropdown admin-dropdown-menu absolute left-0 sm:left-auto mt-3 w-60 bg-white rounded-2xl shadow-2xl border border-gray-100 py-2 flex flex-col z-50">
            <a id="usersMenuLink" href="admin_users.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-purple-50 rounded-xl text-gray-700 hover:text-purple-700 flex items-center relative transition-colors">
                <i class="fas fa-users-cog w-5 text-center mr-2 text-purple-500"></i> <?= __('admin_manage_users') ?>
                <?php if($pending_counts['verifications'] > 0): ?><span id="usersMenuBadge" class="ml-auto bg-red-500 text-white text-[10px] px-1.5 rounded-full shadow-sm"><?= $pending_counts['verifications'] ?></span><?php endif; ?>
            </a>
            <a href="admin_online_users.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-green-50 rounded-xl text-gray-700 hover:text-green-700 flex items-center transition-colors">
                <i class="fas fa-signal w-5 text-center mr-2 text-green-500"></i> <?= __('admin_online_users') ?>
            </a>
            <a href="admin_banned_users.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-red-50 rounded-xl text-gray-700 hover:text-red-700 flex items-center transition-colors">
                <i class="fas fa-user-slash w-5 text-center mr-2 text-red-500"></i> ပိတ်ပင်ထားသော Users
            </a>
            <a id="supportMenuLink" href="admin_support.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-blue-50 rounded-xl text-gray-700 hover:text-blue-700 flex items-center relative transition-colors">
                <i class="fas fa-headset w-5 text-center mr-2 text-blue-500"></i> <?= __('admin_support_message') ?>
                <?php if($pending_counts['support'] > 0): ?><span id="supportMenuBadge" class="ml-auto bg-red-500 text-white text-[10px] px-1.5 rounded-full shadow-sm"><?= $pending_counts['support'] ?></span><?php endif; ?>
            </a>
            <a href="admin_notifications.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-yellow-50 rounded-xl text-gray-700 hover:text-yellow-700 flex items-center transition-colors">
                <i class="fas fa-bell w-5 text-center mr-2 text-yellow-500"></i> <?= __('admin_send_noti') ?>
            </a>
        </div>
    </div>

    <div class="relative dropdown-container">
        <button onclick="toggleDropdown('settingsMenu')" class="bg-gray-100 text-gray-800 hover:bg-gray-200 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl border border-gray-300 hover:border-gray-400 transition-all shadow-sm flex items-center whitespace-nowrap">
            <i class="fas fa-cog mr-1 text-gray-600"></i> <?= __('admin_settings') ?> <i class="fas fa-chevron-down ml-2 text-xs"></i>
        </button>
        <div id="settingsMenu" class="admin-dropdown admin-dropdown-menu absolute right-0 mt-3 w-64 bg-white rounded-2xl shadow-2xl border border-gray-100 py-2 flex flex-col z-50">
            <a href="admin_session_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-blue-50 rounded-xl text-gray-700 hover:text-blue-900 flex items-center transition-colors font-bold border border-transparent hover:border-blue-100">
                <i class="fas fa-clock w-5 text-center mr-2 text-blue-500"></i> <?= __('admin_manage_sessions') ?>
            </a>
            <div class="h-px bg-gray-100 my-1 mx-3"></div>
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin_sub_admins.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-user-shield w-5 text-center mr-2 text-purple-500"></i> <?= __('admin_create_sub_admins') ?>
            </a>
            <?php endif; ?>
            <a href="admin_limit_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-sliders-h w-5 text-center mr-2 text-blue-500"></i> <?= __('admin_set_limits') ?>
            </a>
            <a href="admin_finance_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-money-bill-wave w-5 text-center mr-2 text-green-500"></i> <?= __('admin_finance_limits') ?>
            </a>
            <a href="admin_payment_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-wallet w-5 text-center mr-2 text-blue-500"></i> <?= __('admin_payment_accounts') ?>
            </a>
            <a href="admin_banner_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-images w-5 text-center mr-2 text-pink-500"></i> <?= __('admin_set_banners') ?>
            </a>
            <a href="admin_announcement_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-bullhorn w-5 text-center mr-2 text-purple-500"></i> <?= __('admin_popup_announcement') ?>
            </a>
            <a href="admin_telegram_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fab fa-telegram w-5 text-center mr-2 text-blue-500"></i> <?= __('admin_connect_telegram') ?>
            </a>
            <a href="admin_api_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-plug w-5 text-center mr-2 text-green-500"></i> <?= __('admin_api_auto_result') ?>
            </a>
            <a href="admin_referral_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-share-alt w-5 text-center mr-2 text-indigo-500"></i> <?= __('admin_referral_commission') ?>
            </a>
            <a href="admin_vip_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-crown w-5 text-center mr-2 text-yellow-500"></i> <?= __('admin_vip_cashback') ?>
            </a>
            
            <a href="admin_seo.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-search-location w-5 text-center mr-2 text-blue-500"></i> <?= __('admin_seo_settings') ?? 'SEO ထိန်းချုပ်စနစ်' ?>
            </a>
            
            <a href="admin_settings.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-gray-50 rounded-xl text-gray-700 hover:text-gray-900 flex items-center transition-colors">
                <i class="fas fa-cog w-5 text-center mr-2 text-gray-500"></i> <?= __('admin_general_settings') ?>
            </a>
            <div class="h-px bg-gray-100 my-1 mx-3"></div>
            <a href="admin_health_check.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-red-50 rounded-xl text-gray-700 hover:text-red-700 flex items-center transition-colors">
                <i class="fas fa-heartbeat w-5 text-center mr-2 text-red-500"></i> <?= __('admin_system_health') ?>
            </a>
            <a href="admin_activity_log.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-blue-50 rounded-xl text-gray-700 hover:text-blue-700 flex items-center transition-colors">
                <i class="fas fa-clipboard-list w-5 text-center mr-2 text-blue-500"></i> <?= __('admin_activity_logs') ?>
            </a>
            <a href="admin_error_logs.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-red-50 rounded-xl text-gray-700 hover:text-red-700 flex items-center transition-colors">
                <i class="fas fa-bug w-5 text-center mr-2 text-red-500"></i> <?= __('admin_error_logs') ?>
            </a>
            <div class="h-px bg-gray-100 my-1 mx-3"></div>
            <a href="admin_guides.php" class="mx-2 my-1 px-3 py-2.5 hover:bg-yellow-50 rounded-xl text-gray-700 hover:text-yellow-700 flex items-center transition-colors">
                <i class="fas fa-book-reader w-5 text-center mr-2 text-yellow-600"></i> <?= __('admin_guides') ?>
            </a>
        </div>
    </div>
</div>

<audio id="txAlertSound" src="../assets/sounds/notification.mp3" preload="auto"></audio>

<div id="toast-container" class="fixed top-20 right-5 z-50 space-y-2"></div>

<style>
    .toast {
        animation: slide-in 0.5s forwards, fade-out 0.5s 4.5s forwards;
    }
    @keyframes slide-in {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fade-out {
        from { opacity: 1; }
        to { opacity: 0; transform: translateX(100%); }
    }
</style>

<script>
// Theme Initialization (Run immediately to prevent FOUC)
(function() {
    let currentTheme = localStorage.getItem('admin_theme');
    if (!currentTheme) {
        currentTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    if (currentTheme === 'dark') {
        document.documentElement.classList.add('dark');
    }
})();

// Dropdown Toggles Function (Global Scope)
function toggleDropdown(id) {
    document.querySelectorAll('.admin-dropdown').forEach(el => {
        if(el.id !== id) el.classList.remove('show');
        else el.classList.toggle('show');
    });
}

// Outside Click to close Dropdowns
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown-container')) {
        document.querySelectorAll('.admin-dropdown').forEach(el => el.classList.remove('show'));
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeIcon = themeToggleBtn.querySelector('i');
    
    function updateThemeUI() {
        if (document.documentElement.classList.contains('dark')) {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
            themeToggleBtn.title = "<?= __('admin_js_switch_light') ?>";
        } else {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
            themeToggleBtn.title = "<?= __('admin_js_switch_dark') ?>";
        }
    }
    updateThemeUI();

    themeToggleBtn.addEventListener('click', () => {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('admin_theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('admin_theme', 'dark');
        }
        updateThemeUI();
    });

    const soundToggleBtn = document.getElementById('soundToggleBtn');
    const soundIcon = soundToggleBtn.querySelector('i');
    const txAlertSound = document.getElementById('txAlertSound');
    const txMenuLink = document.getElementById('txMenuLink');

    // 1. Sound Toggle Logic
    let soundEnabled = localStorage.getItem('admin_sound_enabled') !== 'false'; // Default to true

    function updateSoundIcon() {
        if (soundEnabled) {
            soundIcon.classList.remove('fa-bell-slash');
            soundIcon.classList.add('fa-bell');
            soundToggleBtn.title = "<?= __('admin_js_sound_on') ?>";
        } else {
            soundIcon.classList.remove('fa-bell');
            soundIcon.classList.add('fa-bell-slash');
            soundToggleBtn.title = "<?= __('admin_js_sound_off') ?>";
        }
    }

    soundToggleBtn.addEventListener('click', () => {
        soundEnabled = !soundEnabled;
        localStorage.setItem('admin_sound_enabled', soundEnabled);
        updateSoundIcon();
        if (soundEnabled) {
            showToast('success', '<?= __('admin_js_sound_enabled') ?>');
            txAlertSound.play().catch(e => {});
        } else {
            showToast('error', '<?= __('admin_js_sound_disabled') ?>');
        }
    });

    // 2. Toast Notification Logic
    function showToast(type, message) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        let iconClass = 'fa-info-circle';
        let colorClass = 'gray';

        switch(type) {
            case 'success': iconClass = 'fa-check-circle'; colorClass = 'green'; break;
            case 'error': iconClass = 'fa-times-circle'; colorClass = 'red'; break;
            case 'deposit': iconClass = 'fa-arrow-down'; colorClass = 'blue'; break;
            case 'withdrawal': iconClass = 'fa-arrow-up'; colorClass = 'red'; break;
            case 'message': iconClass = 'fa-envelope'; colorClass = 'purple'; break;
            case 'user': iconClass = 'fa-user-plus'; colorClass = 'teal'; break;
        }

        toast.className = `toast bg-${colorClass}-500 text-white p-4 rounded-lg shadow-lg flex items-center gap-3`;
        toast.innerHTML = `<i class="fas ${iconClass}"></i> <p>${message}</p>`;
        
        container.appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }

    // 3. Background Checker Logic
    function checkNewTransactions() {
        fetch('admin_check_new_tx.php?t=' + new Date().getTime()) // Prevent caching
            .then(response => response.json())
            .then(data => {
                let fetchedTotal = parseInt(data.total || 0);
                let fetchedDeposits = parseInt(data.deposits || 0);
                let fetchedWithdrawals = parseInt(data.withdrawals || 0);
                let fetchedMessages = parseInt(data.messages || 0);
                let fetchedVerifications = parseInt(data.verifications || 0);

                let savedTotal = parseInt(localStorage.getItem('admin_tx_total') || 0);
                let savedDeposits = parseInt(localStorage.getItem('admin_tx_deposits') || 0);
                let savedWithdrawals = parseInt(localStorage.getItem('admin_tx_withdrawals') || 0);
                let savedMessages = parseInt(localStorage.getItem('admin_tx_messages') || 0);
                let savedVerifications = parseInt(localStorage.getItem('admin_tx_verifications') || 0);

                let newAlert = false;
                
                if (fetchedTotal > savedTotal) {
                    newAlert = true;
                    if (fetchedDeposits > savedDeposits) showToast('deposit', `<?= __('admin_js_new_deposit') ?>`.replace('{count}', fetchedDeposits - savedDeposits));
                    if (fetchedWithdrawals > savedWithdrawals) showToast('withdrawal', `<?= __('admin_js_new_withdrawal') ?>`.replace('{count}', fetchedWithdrawals - savedWithdrawals));
                }
                
                if (fetchedMessages > savedMessages) {
                    newAlert = true;
                    showToast('message', `<?= __('admin_js_new_message') ?>`.replace('{count}', fetchedMessages - savedMessages));
                }
                
                if (fetchedVerifications > savedVerifications) {
                    newAlert = true;
                    showToast('user', `<?= __('admin_js_new_user') ?>`.replace('{count}', fetchedVerifications - savedVerifications));
                }
                
                if (newAlert && soundEnabled) {
                    txAlertSound.play().catch(e => console.log("Audio play prevented by browser."));
                }
                
                localStorage.setItem('admin_tx_total', fetchedTotal);
                localStorage.setItem('admin_tx_deposits', fetchedDeposits);
                localStorage.setItem('admin_tx_withdrawals', fetchedWithdrawals);
                localStorage.setItem('admin_tx_messages', fetchedMessages);
                localStorage.setItem('admin_tx_verifications', fetchedVerifications);

                let badge = document.getElementById('txMenuBadge');
                if (fetchedTotal > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.id = 'txMenuBadge';
                        badge.className = 'absolute -top-1 -right-1 bg-red-500 text-white text-[10px] px-1.5 rounded-full shadow-sm';
                        txMenuLink.appendChild(badge);
                    }
                    badge.textContent = fetchedTotal;
                } else if (badge) {
                    badge.remove();
                }
                
                let supportLink = document.getElementById('supportMenuLink');
                if (supportLink) {
                    let supportBadge = document.getElementById('supportMenuBadge');
                    if (fetchedMessages > 0) {
                        if (!supportBadge) {
                            supportBadge = document.createElement('span');
                            supportBadge.id = 'supportMenuBadge';
                            supportBadge.className = 'absolute -top-1 -right-1 bg-red-500 text-white text-[10px] px-1.5 rounded-full shadow-sm';
                            supportLink.appendChild(supportBadge);
                        }
                        supportBadge.textContent = fetchedMessages;
                    } else if (supportBadge) {
                        supportBadge.remove();
                    }
                }
                
                let usersLink = document.getElementById('usersMenuLink');
                if (usersLink) {
                    let usersBadge = document.getElementById('usersMenuBadge');
                    if (fetchedVerifications > 0) {
                        if (!usersBadge) {
                            usersBadge = document.createElement('span');
                            usersBadge.id = 'usersMenuBadge';
                            usersBadge.className = 'absolute -top-1 -right-1 bg-red-500 text-white text-[10px] px-1.5 rounded-full shadow-sm';
                            usersLink.appendChild(usersBadge);
                        }
                        usersBadge.textContent = fetchedVerifications;
                    } else if (usersBadge) {
                        usersBadge.remove();
                    }
                }
            })
            .catch(err => console.error('Transaction check failed:', err));
    }

    // Initial setup
    updateSoundIcon();
    localStorage.setItem('admin_tx_total', <?= $pending_counts['total_tx'] ?>);
    localStorage.setItem('admin_tx_deposits', <?= $pending_counts['deposits'] ?>);
    localStorage.setItem('admin_tx_withdrawals', <?= $pending_counts['withdrawals'] ?>);
    localStorage.setItem('admin_tx_messages', <?= $pending_counts['support'] ?>);
    localStorage.setItem('admin_tx_verifications', <?= $pending_counts['verifications'] ?>);
    setInterval(checkNewTransactions, 15000);
});
</script>
