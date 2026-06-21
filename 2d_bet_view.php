<?php 
$page_title = __('title_2d_bet') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>
<style>
    /* ပိတ်ထားသောဂဏန်းများအတွက် Pattern CSS (အဆင့်မြှင့်ထားသည်) */
    .bg-blocked-stripes {
        background: repeating-linear-gradient(45deg, rgba(0,0,0,0.03), rgba(0,0,0,0.03) 10px, rgba(0,0,0,0.08) 10px, rgba(0,0,0,0.08) 20px) !important;
        color: #94a3b8 !important; 
        border-color: #e2e8f0 !important;
        cursor: not-allowed !important;
    }
    html.dark .bg-blocked-stripes, body.html-dark .bg-blocked-stripes {
        background: repeating-linear-gradient(45deg, rgba(0,0,0,0.2), rgba(0,0,0,0.2) 10px, rgba(255,255,255,0.03) 10px, rgba(255,255,255,0.03) 20px) !important;
        color: #475569 !important; 
        border-color: #334155 !important;
    }
    
    /* Smooth Scrollbar for Grid */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    html.dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #475569; }

    /* Number Button Hover & Active States */
    .num-btn { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); transform: translateZ(0); }
    .num-btn:active:not(.bg-blocked-stripes) { transform: scale(0.92); }
    
    /* Glassmorphism Classes */
    .glass-header { backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
    .glass-footer { backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
</style>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-slate-50 dark:bg-slate-900 transition-colors duration-300 pb-32 md:pb-10 flex flex-col relative">

    <div class="glass-header bg-primary/90 dark:bg-slate-900/80 text-white flex items-center p-4 md:p-5 sticky top-0 z-40 shadow-sm border-b border-blue-800 dark:border-slate-800 transition-colors duration-300">
        <a href="index.php" class="mr-4 text-xl w-10 h-10 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-all active:scale-95"><i class="fas fa-arrow-left"></i></a>
        <div class="flex-1">
            <h1 class="text-lg md:text-xl font-bold tracking-wide leading-tight"><?= __('title_2d_bet') ?></h1>
            <p class="text-[10px] md:text-xs text-blue-200 dark:text-slate-400 mt-0.5"><i class="fas fa-wallet mr-1"></i> <?= number_format($user['balance']) ?> <?= __('currency') ?></p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800/50 p-3 mx-4 md:mx-8 mt-4 rounded-xl flex justify-between items-center border border-slate-200 dark:border-slate-700/50 shadow-sm">
        <div class="text-xs md:text-sm font-bold text-slate-600 dark:text-slate-300">
            <i class="far fa-calendar-alt mr-1"></i> <?= $active_session['target_date'] ?>
        </div>
        <div class="text-xs md:text-sm font-bold text-primary dark:text-blue-400 uppercase">
            <i class="fas <?= $is_morning ? 'fa-sun text-amber-500' : 'fa-moon text-indigo-500' ?> mr-1"></i> 
            <?= $is_morning ? __('morning_section') : __('evening_section') ?>
        </div>
    </div>

    <div class="px-4 md:px-8 mt-3 text-center">
        <div id="countdown" class="inline-block bg-gradient-to-r from-red-500 to-rose-600 text-white font-black text-lg md:text-xl px-6 py-2 rounded-full shadow-md tracking-widest">
            <?= __('loading_time') ?>...
        </div>
    </div>

    <script>
        const closeTime = new Date('<?= date('Y-m-d H:i:s', strtotime($active_session['close_time'] ?? 'now')) ?>').getTime();

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = closeTime - now;

            if (distance < 0) {
                document.getElementById('countdown').innerHTML = "<?= __('betting_closed') ?>";
                document.getElementById('countdown').classList.remove('animate-pulse');
                return;
            }

            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById('countdown').innerHTML = 
                `⏱️ ${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if(distance < 300000) { // အချိန် ၅ မိနစ်အောက်ရောက်ရင် Pulse အလုပ်လုပ်မယ်
                document.getElementById('countdown').classList.add('animate-pulse');
            }
        }

        setInterval(updateCountdown, 1000);
        updateCountdown();
    </script>

    <div class="p-4 md:p-6 lg:p-8 flex-1 w-full mx-auto">
        
        <?php if (!empty($success_message)): ?>
            <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-400 px-5 py-4 rounded-2xl relative mb-6 text-sm md:text-base font-bold shadow-sm flex items-center animate__animated animate__fadeInDown">
                <i class="fas fa-check-circle text-emerald-500 dark:text-emerald-400 text-2xl mr-3"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800/50 text-rose-700 dark:text-rose-400 px-5 py-4 rounded-2xl relative mb-6 text-sm md:text-base font-medium shadow-sm flex items-center animate__animated animate__shakeX">
                <i class="fas fa-exclamation-circle text-rose-500 dark:text-rose-400 text-2xl mr-3"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="betForm" class="bg-white dark:bg-slate-800 md:p-8 rounded-3xl shadow-sm md:shadow-xl border border-slate-100 dark:border-slate-700/50 transition-colors duration-300 relative overflow-hidden">
            
            <div class="flex justify-between items-center mb-8 px-4 md:px-12 relative pt-4 md:pt-0">
                <div class="absolute left-10 right-10 top-1/2 transform -translate-y-1/2 h-1 bg-slate-100 dark:bg-slate-700 z-0 rounded-full"></div>
                <div class="absolute left-10 top-1/2 transform -translate-y-1/2 h-1 bg-gradient-to-r from-blue-500 to-indigo-500 z-0 transition-all duration-700 ease-in-out rounded-full" id="stepProgressBar" style="width: 0%;"></div>
                
                <div class="relative z-10 flex flex-col items-center group cursor-pointer" onclick="goToStep(1)">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-base shadow-lg transition-transform group-hover:scale-110" id="stepCircle1">1</div>
                    <span class="text-[10px] md:text-xs mt-2 font-bold text-blue-600 dark:text-blue-400 transition-colors" id="stepText1"><?= __('step_choose_number') ?></span>
                </div>
                <div class="relative z-10 flex flex-col items-center group cursor-pointer" onclick="goToStep(2)">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-slate-50 dark:bg-slate-800 text-slate-400 border-2 border-slate-200 dark:border-slate-600 flex items-center justify-center font-bold text-base shadow-sm transition-all duration-300" id="stepCircle2">2</div>
                    <span class="text-[10px] md:text-xs mt-2 font-bold text-slate-400 transition-colors" id="stepText2"><?= __('step_bet_amount') ?></span>
                </div>
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-slate-50 dark:bg-slate-800 text-slate-400 border-2 border-slate-200 dark:border-slate-600 flex items-center justify-center font-bold text-base shadow-sm transition-all duration-300" id="stepCircle3">3</div>
                    <span class="text-[10px] md:text-xs mt-2 font-bold text-slate-400 transition-colors" id="stepText3"><?= __('step_confirm') ?></span>
                </div>
            </div>

            <div id="step1" class="animate__animated animate__fadeIn">
                <div class="flex items-center justify-end gap-3 mb-4 px-4">
                    <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-700/50 px-3 py-1.5 rounded-full border border-slate-200 dark:border-slate-600">
                        <i class="fas fa-volume-up text-primary dark:text-blue-400 text-xs"></i>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="enable_bet_tts" class="sr-only peer" onchange="localStorage.setItem('enable_bet_tts', this.checked)">
                            <div class="w-7 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                <div class="px-4 mb-5">
                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-wider"><?= __('quick_pick_label') ?></p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="quickPick('all')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95"><i class="fas fa-list-ol text-blue-500 mr-1"></i> <?= __('all_00_99') ?></button>
                        <button type="button" onclick="quickPick('even_even')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95"><i class="fas fa-th-large text-purple-500 mr-1"></i> <?= __('even_even') ?></button>
                        <button type="button" onclick="quickPick('odd_odd')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95"><i class="fas fa-th text-pink-500 mr-1"></i> <?= __('odd_odd') ?></button>
                        <button type="button" onclick="quickPick('doubles')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95"><i class="fas fa-clone text-orange-500 mr-1"></i> <?= __('doubles') ?></button>
                        <button type="button" onclick="quickPickPrompt('round')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95"><i class="fas fa-sync text-teal-500 mr-1"></i> <?= __('round') ?></button>
                        <button type="button" onclick="quickPickPrompt('khway')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95"><i class="fas fa-random text-indigo-500 mr-1"></i> <?= __('khway') ?></button>
                        
                        <label class="flex items-center gap-1.5 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 shadow-sm cursor-pointer ml-auto">
                            <input type="checkbox" id="is_reverse" name="is_reverse" value="1" class="w-3.5 h-3.5 text-primary border-slate-300 rounded focus:ring-primary" onchange="calculateTotal()" <?= isset($_POST['is_reverse']) ? 'checked' : '' ?>>
                            <span><?= __('reverse_r') ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="px-4 mb-6">
                    <div class="grid grid-cols-5 sm:grid-cols-10 gap-2 md:gap-3 max-h-[50vh] md:max-h-[28rem] overflow-y-auto custom-scrollbar p-2 rounded-2xl bg-slate-50/50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800/50 shadow-inner">
                        <?php for($i=0; $i<=99; $i++): 
                            $num = str_pad($i, 2, '0', STR_PAD_LEFT); 
                            $is_blocked = in_array($num, $blocked_numbers_arr);
                            $current_amt = isset($grid_amounts[$num]) ? $grid_amounts[$num] : 0;
                            $percent = ($grid_max_limit > 0) ? min(100, ($current_amt / $grid_max_limit) * 100) : 0;
                            
                            $p_color = 'bg-emerald-500';
                            $b_bg = 'bg-white dark:bg-slate-800';
                            $b_border = 'border-slate-200 dark:border-slate-700';
                            $tail_colors = ['text-blue-600 dark:text-blue-400', 'text-emerald-600 dark:text-emerald-400', 'text-purple-600 dark:text-purple-400', 'text-amber-600 dark:text-amber-400', 'text-teal-600 dark:text-teal-400', 'text-pink-600 dark:text-pink-400', 'text-indigo-600 dark:text-indigo-400', 'text-rose-600 dark:text-rose-400', 'text-cyan-600 dark:text-cyan-400', 'text-yellow-600 dark:text-yellow-400'];
                            $type_color = $tail_colors[intval($num[1])];
                            
                            if ($is_blocked) {
                                $b_bg = 'bg-blocked-stripes opacity-70';
                            } elseif ($percent >= 100) { 
                                $p_color = 'bg-rose-500'; $b_bg = 'bg-rose-50 dark:bg-rose-900/20'; $b_border = 'border-rose-300 dark:border-rose-800/50'; 
                            }
                            elseif ($percent >= 80) { $p_color = 'bg-rose-400'; $b_bg = 'bg-rose-50/50 dark:bg-rose-900/10'; $b_border = 'border-rose-200 dark:border-rose-800/30'; }
                            elseif ($percent >= 50) { $p_color = 'bg-amber-400'; $b_bg = 'bg-amber-50/50 dark:bg-amber-900/10'; $b_border = 'border-amber-200 dark:border-amber-800/30'; }
                            
                            $remaining = max(0, $grid_max_limit - $current_amt);
                        ?>
                            <button type="button" id="btn_<?= $num ?>" data-num="<?= $num ?>" title="<?= $is_blocked ? __('blocked_number_tooltip') : __('remaining_amount_tooltip') . ' ' . number_format($remaining) . ' ' . __('currency') ?>" onclick="<?= $is_blocked ? 'showBlockedAlert()' : 'toggleNumber(\''.$num.'\')' ?>" class="num-btn <?= $b_bg ?> <?= $b_border ?> border <?= $type_color ?> font-bold text-base md:text-lg pt-3 pb-4 rounded-xl shadow-sm hover:shadow-md relative overflow-hidden flex flex-col items-center justify-center group">
                                <span class="relative z-10 pointer-events-none"><?= $num ?></span>
                                <div class="absolute bottom-0 left-0 w-full h-1.5 bg-slate-100 dark:bg-slate-700/50 pointer-events-none">
                                    <div class="progress-bar-inner <?= $p_color ?> h-full transition-all duration-500" style="width: <?= $percent ?>%"></div>
                                </div>
                                <div class="absolute inset-0 bg-blue-500/5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none hidden md:block"></div>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="px-4 mb-4 relative">
                    <button type="button" onclick="quickPick('clear')" class="absolute top-3 right-7 text-rose-400 hover:text-rose-600 transition-colors bg-slate-100 dark:bg-slate-900 rounded-full p-1"><i class="fas fa-times-circle text-lg"></i></button>
                    <textarea id="bet_number" name="bet_number" rows="2" placeholder="<?= __('selected_numbers_placeholder') ?>" 
                           class="w-full p-4 pr-12 border-2 border-transparent rounded-2xl focus:border-blue-500 dark:focus:border-blue-400 focus:ring-4 focus:ring-blue-500/20 outline-none font-mono tracking-widest text-lg md:text-xl text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-slate-900/50 shadow-inner transition-all resize-none leading-relaxed" required autocomplete="off" onkeyup="syncGrid()" onchange="syncGrid()"><?= htmlspecialchars($_POST['bet_number'] ?? '') ?></textarea>
                </div>
                
                <div class="px-4 hidden md:block mt-6">
                    <button type="button" onclick="goToStep(2)" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 rounded-2xl text-lg shadow-lg hover:shadow-indigo-500/30 hover:-translate-y-1 transition-all duration-300">
                        <?= __('continue') ?> <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>

            <div id="step2" class="hidden animate__animated animate__fadeIn px-4">
                <div class="mb-8">
                    <label class="block text-slate-800 dark:text-slate-200 text-lg font-bold mb-4 text-center"><?= __('bet_amount') ?> (Ks)</label>
                    <div class="relative max-w-sm mx-auto">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-coins text-amber-500 text-xl"></i>
                        </div>
                        <input type="number" id="bet_amount" name="bet_amount" min="100" placeholder="100" 
                               class="w-full pl-12 pr-4 py-4 md:py-5 border-2 border-slate-200 dark:border-slate-600 rounded-2xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 outline-none text-2xl font-black text-center text-primary dark:text-blue-400 bg-white dark:bg-slate-800 shadow-sm transition-all" required oninput="calculateTotal()" value="<?= htmlspecialchars($_POST['bet_amount'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-10 max-w-md mx-auto">
                    <div class="grid grid-cols-3 gap-3 md:gap-4">
                        <button type="button" onclick="addAmount(100)" class="bg-slate-50 dark:bg-slate-700/50 hover:bg-blue-50 dark:hover:bg-blue-900/30 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 py-3 rounded-xl text-base font-bold shadow-sm transition-all active:scale-95">+100</button>
                        <button type="button" onclick="addAmount(500)" class="bg-slate-50 dark:bg-slate-700/50 hover:bg-blue-50 dark:hover:bg-blue-900/30 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 py-3 rounded-xl text-base font-bold shadow-sm transition-all active:scale-95">+500</button>
                        <button type="button" onclick="addAmount(1000)" class="bg-slate-50 dark:bg-slate-700/50 hover:bg-blue-50 dark:hover:bg-blue-900/30 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 py-3 rounded-xl text-base font-bold shadow-sm transition-all active:scale-95">+1,000</button>
                        <button type="button" onclick="addAmount(5000)" class="bg-slate-50 dark:bg-slate-700/50 hover:bg-blue-50 dark:hover:bg-blue-900/30 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 py-3 rounded-xl text-base font-bold shadow-sm transition-all active:scale-95">+5,000</button>
                        <button type="button" onclick="addAmount(10000)" class="bg-slate-50 dark:bg-slate-700/50 hover:bg-blue-50 dark:hover:bg-blue-900/30 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 py-3 rounded-xl text-base font-bold shadow-sm transition-all active:scale-95">+10,000</button>
                        <button type="button" onclick="setAmount('')" class="bg-rose-50 dark:bg-rose-900/20 hover:bg-rose-100 dark:hover:bg-rose-900/40 border border-rose-200 dark:border-rose-800 text-rose-600 dark:text-rose-400 py-3 rounded-xl text-base font-bold shadow-sm transition-all active:scale-95"><i class="fas fa-eraser"></i></button>
                    </div>
                </div>

                <div class="flex gap-4 hidden md:flex mt-8 max-w-md mx-auto">
                    <button type="button" onclick="goToStep(1)" class="w-1/3 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold py-4 rounded-2xl shadow-sm transition-all text-lg">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <button type="button" onclick="handleCheckLimitsButtonClick()" class="w-2/3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold py-4 rounded-2xl text-lg shadow-lg hover:shadow-orange-500/30 hover:-translate-y-1 transition-all duration-300 flex justify-center items-center gap-2">
                        <i class="fas fa-check-circle"></i> <?= __('check_limits') ?>
                    </button>
                </div>
            </div>

            <div id="step3" class="hidden animate__animated animate__fadeIn px-4">
                <div class="bg-gradient-to-b from-blue-50 to-white dark:from-slate-800 dark:to-slate-800 border border-blue-100 dark:border-slate-700 rounded-3xl p-8 mb-8 text-center shadow-lg relative overflow-hidden max-w-md mx-auto">
                    <div class="w-20 h-20 bg-blue-100 dark:bg-blue-900/50 rounded-full flex items-center justify-center mx-auto mb-5 shadow-inner">
                        <i class="fas fa-fingerprint text-blue-600 dark:text-blue-400 text-4xl"></i>
                    </div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-xl mb-3"><?= __('ready_to_confirm') ?? 'အတည်ပြုရန် အသင့်ဖြစ်ပါပြီ' ?></h3>
                    <p class="text-slate-500 dark:text-slate-400 font-medium leading-relaxed">
                        <?= __('confirm_pin_msg_1') ?> <br>
                        <strong id="final_total_amount" class="text-rose-500 dark:text-rose-400 text-3xl font-black block my-2">0</strong> 
                        <?= __('confirm_pin_msg_2') ?>
                    </p>
                </div>

                <div class="mb-10 max-w-xs mx-auto">
                    <input type="password" name="pin" maxlength="6" inputmode="numeric" placeholder="••••••" class="w-full py-4 px-4 border-2 border-slate-200 dark:border-slate-600 rounded-2xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 outline-none text-center tracking-[0.5em] font-mono text-3xl shadow-inner text-primary dark:text-blue-400 bg-slate-50 dark:bg-slate-900 focus:bg-white transition-all" required>
                </div>

                <div class="flex gap-4 max-w-xs mx-auto hidden md:flex">
                    <button type="button" onclick="goToStep(2)" class="w-1/3 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold py-4 rounded-2xl shadow-sm transition-all">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <button type="submit" class="w-2/3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 rounded-2xl text-lg shadow-lg hover:shadow-indigo-500/30 hover:-translate-y-1 transition-all duration-300">
                        <?= __('confirm') ?>
                    </button>
                </div>
            </div>
            
        </form>
    </div>

    <div id="mobileBottomBar" class="md:hidden fixed bottom-0 left-0 right-0 glass-footer bg-white/80 dark:bg-slate-900/80 border-t border-slate-200 dark:border-slate-800 p-4 z-50 shadow-[0_-10px_40px_rgba(0,0,0,0.1)] transition-transform duration-300">
        <div class="flex justify-between items-center mb-3 px-2">
            <div>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider mb-0.5"><?= __('selected_kwek') ?></p>
                <p class="font-black text-blue-600 dark:text-blue-400 text-xl"><span id="mobile_live_kwek_count">0</span> <span class="text-xs font-bold text-slate-400">ကွက်</span></p>
            </div>
            <div class="text-right">
                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider mb-0.5"><?= __('total_cost_label') ?></p>
                <p class="font-black text-rose-500 dark:text-rose-400 text-xl"><span id="mobile_live_total_amount">0</span> <span class="text-xs font-bold text-slate-400">Ks</span></p>
            </div>
        </div>
        
        <div id="mobileActionBtnContainer">
            <button type="button" onclick="goToStep(2)" id="mobileBtnStep1" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold py-3.5 rounded-2xl text-lg shadow-md active:scale-95 transition-all flex justify-center items-center gap-2">
                <?= __('continue') ?> <i class="fas fa-arrow-right"></i>
            </button>
            <div id="mobileBtnStep2" class="hidden flex gap-3">
                <button type="button" onclick="goToStep(1)" class="w-1/4 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold py-3.5 rounded-2xl shadow-sm active:scale-95 transition-all">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <button type="button" onclick="handleCheckLimitsButtonClick()" class="w-3/4 bg-gradient-to-r from-amber-500 to-orange-500 text-white font-bold py-3.5 rounded-2xl text-lg shadow-md active:scale-95 transition-all">
                    <?= __('check_limits') ?>
                </button>
            </div>
            <div id="mobileBtnStep3" class="hidden flex gap-3">
                <button type="button" onclick="goToStep(2)" class="w-1/4 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold py-3.5 rounded-2xl shadow-sm active:scale-95 transition-all">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <button type="button" onclick="submitMobileForm()" class="w-3/4 bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-bold py-3.5 rounded-2xl text-lg shadow-md active:scale-95 transition-all">
                    <i class="fas fa-check-circle mr-1"></i> <?= __('confirm') ?>
                </button>
            </div>
        </div>
    </div>

    <script>
        let maxLimit2D = <?= $grid_max_limit ?>;
        let currentTotals2D = <?= json_encode($grid_amounts) ?>;
        const blockedNumbers2D = <?= json_encode(array_values($blocked_numbers_arr)) ?>;
        let userBalance = <?= $user['balance'] ?>;

        function convertToBurmese(numStr) {
            const burmeseNumbers = ['၀', '၁', '၂', '၃', '၄', '၅', '၆', '၇', '၈', '၉'];
            return numStr.split('').map(char => (char >= '0' && char <= '9') ? burmeseNumbers[parseInt(char)] : char).join('');
        }

        function speakNumber(text) {
            let ttsCb = document.getElementById('enable_bet_tts');
            if (ttsCb && ttsCb.checked && 'speechSynthesis' in window) {
                window.speechSynthesis.cancel();
                let speechText = text.replace(/[0-9]/g, function(match) { return convertToBurmese(match); });
                let utterance = new SpeechSynthesisUtterance(speechText);
                utterance.lang = 'my-MM';
                utterance.rate = parseFloat(localStorage.getItem('ttsSpeed') || 0.9);
                window.speechSynthesis.speak(utterance);
            }
        }

        function vibrate() {
            if (navigator.vibrate) navigator.vibrate(30);
        }

        document.addEventListener('DOMContentLoaded', () => {
            syncGrid();
            let ttsCb = document.getElementById('enable_bet_tts');
            if (ttsCb) ttsCb.checked = localStorage.getItem('enable_bet_tts') === 'true';
        });

        function toggleNumber(num) {
            vibrate();
            speakNumber(num);
            let currentVal = document.getElementById('bet_number').value;
            let nums = currentVal.split(/[\s,]+/).filter(n => n.match(/^[0-9]{2}$/));
            
            let index = nums.indexOf(num);
            if (index > -1) nums.splice(index, 1);
            else nums.push(num);
            
            document.getElementById('bet_number').value = [...new Set(nums)].sort().join(', ');
            syncGrid();
        }

        function syncGrid() {
            let currentVal = document.getElementById('bet_number').value;
            let nums = currentVal.split(/[\s,]+/).filter(n => n.match(/^[0-9]{2}$/));

            document.querySelectorAll('.num-btn').forEach(btn => {
                const num = btn.getAttribute('data-num');
                const isSelected = nums.includes(num);
                const isBlocked = blockedNumbers2D.includes(num);
                const bar = btn.querySelector('.progress-bar-inner');
                const percent = bar ? parseFloat(bar.style.width) : 0;

                btn.classList.remove(
                    'bg-gradient-to-br', 'from-blue-500', 'to-indigo-600', 'text-white', 'shadow-md', 
                    'ring-2', 'ring-blue-500', 'ring-offset-2', 'dark:ring-offset-slate-900', 'transform', 'scale-105',
                    'bg-white', 'dark:bg-slate-800', 'bg-rose-50', 'bg-amber-50', 'bg-blocked-stripes', 
                    'border-slate-200', 'border-rose-300', 'border-amber-200', 'dark:border-slate-700', 'opacity-70'
                );

                if (isSelected) {
                    btn.classList.add('bg-gradient-to-br', 'from-blue-500', 'to-indigo-600', 'text-white', 'shadow-md', 'ring-2', 'ring-blue-500', 'ring-offset-2', 'dark:ring-offset-slate-900', 'transform', 'scale-105');
                } else if (isBlocked) {
                    btn.classList.add('bg-blocked-stripes', 'opacity-70');
                } else {
                    if (percent >= 100) btn.classList.add('bg-rose-50', 'border-rose-300', 'dark:bg-rose-900/20');
                    else if (percent >= 80) btn.classList.add('bg-rose-50', 'border-rose-200', 'dark:bg-rose-900/10');
                    else if (percent >= 50) btn.classList.add('bg-amber-50', 'border-amber-200', 'dark:bg-amber-900/10');
                    else btn.classList.add('bg-white', 'dark:bg-slate-800', 'border-slate-200', 'dark:border-slate-700');
                }
            });
            calculateTotal();
        }

        function quickPick(type) {
            vibrate();
            let nums = [];
            if (type === 'all') {
                for (let i = 0; i <= 99; i++) nums.push(i.toString().padStart(2, '0'));
            } else if (type === 'even_even') {
                for (let i = 0; i <= 8; i += 2) {
                    for (let j = 0; j <= 8; j += 2) nums.push(i.toString() + j.toString());
                }
            } else if (type === 'odd_odd') {
                for (let i = 1; i <= 9; i += 2) {
                    for (let j = 1; j <= 9; j += 2) nums.push(i.toString() + j.toString());
                }
            } else if (type === 'doubles') {
                for (let i = 0; i <= 9; i++) nums.push(i.toString() + i.toString());
            } else if (type === 'clear') {
                document.getElementById('bet_number').value = '';
                syncGrid();
                return;
            }
            
            let currentVal = document.getElementById('bet_number').value.trim();
            let newVal = nums.join(', ');
            document.getElementById('bet_number').value = currentVal ? currentVal + ', ' + newVal : newVal;
            
            let allNums = document.getElementById('bet_number').value.split(/[\s,]+/).filter(n => n.match(/^[0-9]{2}$/));
            document.getElementById('bet_number').value = [...new Set(allNums)].sort().join(', ');
            syncGrid();
        }

        function quickPickPrompt(type) {
            vibrate();
            let promptText = type === 'round' ? 'ပတ်လည်အတွက် ဂဏန်း (၀-၉) တစ်လုံး ရိုက်ထည့်ပါ:' : 'ခွေမည့် ဂဏန်းများ ရိုက်ထည့်ပါ (ဥပမာ - 123):';
            let input = prompt(promptText);
            if (!input) return;

            let nums = [];
            if (type === 'khway') {
                let digits = input.trim().replace(/[^0-9]/g, '').split('');
                if (digits.length < 2) return;
                for (let i = 0; i < digits.length; i++) {
                    for (let j = 0; j < digits.length; j++) {
                        if (i !== j) nums.push(digits[i] + digits[j]);
                    }
                }
            } else if (type === 'round') {
                let d = parseInt(input.trim());
                if (isNaN(d) || d < 0 || d > 9) return;
                let digit = d.toString();
                for (let i = 0; i <= 9; i++) {
                    nums.push(digit + i.toString());
                    nums.push(i.toString() + digit);
                }
            }
            
            nums = [...new Set(nums)].sort();
            let currentVal = document.getElementById('bet_number').value.trim();
            document.getElementById('bet_number').value = currentVal ? currentVal + ', ' + nums.join(', ') : nums.join(', ');
            
            let allNums = document.getElementById('bet_number').value.split(/[\s,]+/).filter(n => n.match(/^[0-9]{2}$/));
            document.getElementById('bet_number').value = [...new Set(allNums)].sort().join(', ');
            syncGrid();
        }

        function addAmount(amount) {
            vibrate();
            let currentAmountInput = document.getElementById('bet_amount');
            let currentVal = parseInt(currentAmountInput.value) || 0;
            currentAmountInput.value = currentVal + amount;
            calculateTotal();
        }
        function setAmount(amount) {
            vibrate();
            document.getElementById('bet_amount').value = amount;
            calculateTotal();
        }

        function calculateTotal() {
            let currentVal = document.getElementById('bet_number').value;
            let amount = parseInt(document.getElementById('bet_amount').value) || 0;
            let isReverse = document.getElementById('is_reverse').checked;

            let rawNums = currentVal.split(/[\s,]+/);
            let validNums = [];

            rawNums.forEach(num => {
                if (num.match(/^[0-9]{2}$/)) {
                    validNums.push(num);
                    if (isReverse && num[0] !== num[1]) {
                        validNums.push(num[1] + num[0]);
                    }
                }
            });

            let uniqueNums = [...new Set(validNums)];
            let totalKwek = uniqueNums.length;
            let totalAmount = totalKwek * amount;

            const kwekEl = document.getElementById('live_kwek_count');
            const amtEl = document.getElementById('live_total_amount');
            if(kwekEl) kwekEl.innerText = totalKwek;
            if(amtEl) amtEl.innerText = totalAmount.toLocaleString();

            document.getElementById('mobile_live_kwek_count').innerText = totalKwek;
            document.getElementById('mobile_live_total_amount').innerText = totalAmount.toLocaleString();
        }

        function goToStep(step) {
            vibrate();
            if (step === 2) {
                let currentVal = document.getElementById('bet_number').value;
                let nums = currentVal.split(/[\s,]+/).filter(n => n.match(/^[0-9]{2}$/));
                if (nums.length === 0) {
                    Swal.fire({ icon: 'warning', text: '<?= addslashes(__('please_select_numbers')) ?>', customClass: { popup: 'rounded-3xl' } });
                    return;
                }
            }

            if (step === 3) {
                let amount = parseInt(document.getElementById('bet_amount').value) || 0;
                if (amount < 100) {
                    Swal.fire({ icon: 'warning', text: '<?= addslashes(__('min_bet_required')) ?>', customClass: { popup: 'rounded-3xl' } });
                    return;
                }
                
                let totalAmountNeeded = parseInt(document.getElementById('mobile_live_total_amount').innerText.replace(/,/g, '')) || 0;
                
                if (totalAmountNeeded > userBalance) {
                    Swal.fire({
                        title: '<?= addslashes(__('insufficient_balance')) ?>',
                        text: '<?= addslashes(__('insufficient_balance_deposit')) ?>',
                        icon: 'warning',
                        confirmButtonText: 'OK',
                        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-8' }
                    });
                    return;
                }

                if (!isLimitOk()) return;
                document.getElementById('final_total_amount').innerText = totalAmountNeeded.toLocaleString();
            }

            document.getElementById('step1').classList.add('hidden');
            document.getElementById('step2').classList.add('hidden');
            document.getElementById('step3').classList.add('hidden');
            document.getElementById('step' + step).classList.remove('hidden');

            updateStepUI(step);
            updateMobileBottomBarUI(step);
            
            if (step === 2) document.getElementById('bet_amount').focus();
            if (step === 3) document.querySelector('input[name="pin"]').focus();
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateStepUI(step) {
            for (let i = 1; i <= 3; i++) {
                let circle = document.getElementById('stepCircle' + i);
                let text = document.getElementById('stepText' + i);
                if (i <= step) {
                    circle.className = "w-10 h-10 md:w-12 md:h-12 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-base shadow-lg transition-transform scale-110";
                    text.className = "text-[10px] md:text-xs mt-2 font-bold text-blue-600 dark:text-blue-400 transition-colors";
                } else {
                    circle.className = "w-10 h-10 md:w-12 md:h-12 rounded-full bg-slate-50 dark:bg-slate-800 text-slate-400 border-2 border-slate-200 dark:border-slate-600 flex items-center justify-center font-bold text-base shadow-sm transition-all duration-300";
                    text.className = "text-[10px] md:text-xs mt-2 font-bold text-slate-400 transition-colors";
                }
            }
            
            let bar = document.getElementById('stepProgressBar');
            if (step === 1) bar.style.width = '0%';
            else if (step === 2) bar.style.width = '50%';
            else if (step === 3) bar.style.width = '100%';
        }

        function updateMobileBottomBarUI(step) {
            document.getElementById('mobileBtnStep1').classList.add('hidden');
            document.getElementById('mobileBtnStep2').classList.add('hidden');
            document.getElementById('mobileBtnStep3').classList.add('hidden');
            document.getElementById('mobileBtnStep' + step).classList.remove('hidden');
            document.getElementById('mobileBtnStep' + step).classList.add('flex');
        }

        function isLimitOk() {
            let currentVal = document.getElementById('bet_number').value;
            let amount = parseInt(document.getElementById('bet_amount').value) || 0;
            let isReverse = document.getElementById('is_reverse').checked;

            let rawNums = currentVal.split(/[\s,]+/);
            let validNums = [];
            rawNums.forEach(num => {
                if (num.match(/^[0-9]{2}$/)) {
                    validNums.push(num);
                    if (isReverse && num[0] !== num[1]) validNums.push(num[1] + num[0]);
                }
            });

            let uniqueNums = [...new Set(validNums)];
            let exceededNums = [];
            uniqueNums.forEach(num => {
                let currentTotal = currentTotals2D[num] || 0;
                if (blockedNumbers2D.includes(num)) {
                    exceededNums.push(`<span class="text-rose-500 font-bold">${num}</span> (Blocked)`);
                } else if (currentTotal + amount > maxLimit2D) {
                    let available = maxLimit2D - currentTotal;
                    exceededNums.push(`<span class="text-rose-500 font-bold">${num}</span> (Available: ${available > 0 ? available : 0} Ks)`);
                }
            });

            if (exceededNums.length > 0) {
                Swal.fire({
                    icon: 'error',
                    title: '<?= addslashes(__('limit_exceeded')) ?>',
                    html: '<div class="text-left text-sm mt-2 bg-rose-50 p-4 rounded-2xl border border-rose-100 max-h-48 overflow-y-auto leading-loose">' + exceededNums.join("<br>") + '</div>',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-8' }
                });
                return false;
            }
            return true;
        }

        function handleCheckLimitsButtonClick() {
            goToStep(3);
        }

        // --- အသစ်ထည့်သွင်းထားသော Mobile Validation Function ---
        function submitMobileForm() {
            let form = document.getElementById('betForm');
            if (form.reportValidity()) { 
                form.submit();
            }
        }
    </script>
</body>
</html>
