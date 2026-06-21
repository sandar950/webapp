<?php 
$page_title = __('title_3d_bet') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>
<style>
    /* ပိတ်ထားသောဂဏန်းများအတွက် Pattern CSS */
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
    
    /* Smooth Scrollbar for Grid & Tabs */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    html.dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #475569; }

    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Number Button Hover & Active States */
    .num-btn { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); transform: translateZ(0); }
    .num-btn:active:not(.bg-blocked-stripes) { transform: scale(0.92); }
    
    /* Glassmorphism Classes */
    .glass-header { backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
    .glass-footer { backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
</style>

<!-- SweetAlert2 CSS & JS (In case not loaded in header) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-slate-50 dark:bg-slate-900 transition-colors duration-300 pb-32 md:pb-10 flex flex-col relative">

    <div class="glass-header bg-primary/90 dark:bg-slate-900/80 text-white flex items-center p-4 md:p-5 sticky top-0 z-40 shadow-sm border-b border-blue-800 dark:border-slate-800 transition-colors duration-300">
        <a href="index.php" class="mr-4 text-xl w-10 h-10 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition-all active:scale-95"><i class="fas fa-arrow-left"></i></a>
        <div class="flex-1">
            <h1 class="text-lg md:text-xl font-bold tracking-wide leading-tight"><?= __('title_3d_bet') ?></h1>
            <p class="text-[10px] md:text-xs text-blue-200 dark:text-slate-400 mt-0.5"><i class="fas fa-wallet mr-1"></i> <?= number_format($user['balance']) ?> <?= __('currency') ?></p>
        </div>
    </div>

    <audio id="clickSound" src="assets/sounds/click.mp3" preload="auto"></audio>

    <!-- Open/Close Time Header Display -->
    <?php if ($active_session): ?>
    <div class="bg-white dark:bg-slate-800/50 p-4 mx-4 md:mx-8 mt-4 rounded-xl flex flex-col gap-2 border border-slate-200 dark:border-slate-700/50 shadow-sm">
        <div class="flex justify-between items-center">
            <div class="text-sm font-bold text-slate-600 dark:text-slate-300">
                <i class="far fa-calendar-alt mr-1 text-primary dark:text-blue-400"></i> <?= date('d M Y', strtotime($active_session['target_date'])) ?>
            </div>
            <div class="text-xs md:text-sm font-bold text-purple-600 dark:text-purple-400 uppercase bg-purple-50 dark:bg-purple-900/30 px-3 py-1 rounded-lg border border-purple-100 dark:border-purple-800/50">
                <i class="fas fa-dice mr-1"></i> 3D <?= __('section') ?? '' ?>
            </div>
        </div>
        <!-- ဖွင့်/ပိတ် အချိန် (နာရီ) ပြသသော နေရာ -->
        <div class="flex items-center justify-center md:justify-start text-xs font-bold text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-lg mt-1 border border-slate-100 dark:border-slate-800">
            <i class="far fa-clock mr-2 text-amber-500 text-sm"></i>
            <span class="mr-2 md:mr-4">ဖွင့်ချိန်: <span class="text-slate-700 dark:text-slate-200 text-sm"><?= date('h:i A', strtotime($active_session['open_time'])) ?></span></span>
            <span class="hidden md:inline text-slate-300 dark:text-slate-600 mr-4">|</span>
            <span>ပိတ်ချိန်: <span class="text-rose-500 dark:text-rose-400 text-sm"><?= date('h:i A', strtotime($active_session['close_time'])) ?></span></span>
        </div>
    </div>

    <!-- Countdown Timer -->
    <div class="px-4 md:px-8 mt-3 text-center">
        <div id="countdown" class="inline-block bg-gradient-to-r from-red-500 to-rose-600 text-white font-black text-lg md:text-xl px-6 py-2 rounded-full shadow-md tracking-widest transition-colors duration-300">
            <?= __('loading_time') ?>...
        </div>
    </div>

    <script>
        const closeTime = new Date('<?= date('Y-m-d H:i:s', strtotime($active_session['close_time'])) ?>').getTime();

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = closeTime - now;

            if (distance < 0) {
                // အချိန်ကျော်လွန်သွားပါက
                let countdownEl = document.getElementById('countdown');
                countdownEl.innerHTML = "<?= addslashes(__('betting_closed') ?? 'ပွဲပိတ်သွားပါပြီ') ?>";
                countdownEl.classList.remove('animate-pulse');
                countdownEl.classList.replace('from-red-500', 'from-slate-500');
                countdownEl.classList.replace('to-rose-600', 'to-slate-600');
                
                // Submit Buttons များကို Disable လုပ်ခြင်း (Frontend Validation)
                let confirmBtns = document.querySelectorAll('button[type="submit"], #mobileBtnStep3 button');
                confirmBtns.forEach(btn => {
                    btn.disabled = true;
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                    if(btn.innerHTML.includes('<?= addslashes(__('confirm')) ?>')) {
                        btn.innerHTML = '<i class="fas fa-lock mr-1"></i> <?= addslashes(__('betting_closed') ?? "ပွဲပိတ်သွားပါပြီ") ?>';
                    }
                });
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            let timeStr = days > 0 ? `${days}d ` : '';
            timeStr += `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            document.getElementById('countdown').innerHTML = `⏱️ ${timeStr}`;
            
            if(distance < 300000) document.getElementById('countdown').classList.add('animate-pulse');
        }

        setInterval(updateCountdown, 1000);
        updateCountdown();
    </script>
    <?php endif; ?>

    <div class="p-4 md:p-6 lg:p-8 flex-1 w-full mx-auto">
        
        <?php if (!empty($success_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: '<?= addslashes(__('success')) ?>',
                        text: '<?= addslashes($success_message) ?>',
                        confirmButtonColor: '#10b981',
                        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-8' }
                    });
                });
            </script>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: '<?= addslashes(__('error')) ?>',
                        text: '<?= addslashes($error_message) ?>',
                        confirmButtonColor: '#ef4444',
                        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-8' }
                    });
                });
            </script>
        <?php endif; ?>

        <form method="POST" action="" id="betForm" class="bg-white dark:bg-slate-800 md:p-8 rounded-3xl shadow-sm md:shadow-xl border border-slate-100 dark:border-slate-700/50 transition-colors duration-300 relative overflow-hidden">
            
            <!-- Stepper Navigation -->
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

            <!-- STEP 1: Choose Numbers -->
            <div id="step1" class="animate__animated animate__fadeIn">
                <div class="flex flex-wrap items-center justify-end gap-2 mb-4 px-4">
                    <!-- Audio Controls -->
                    <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-700/50 px-3 py-1.5 rounded-full border border-slate-200 dark:border-slate-600">
                        <i class="fas fa-tachometer-alt text-slate-400 text-xs"></i>
                        <select id="ttsSpeed" class="text-xs text-slate-700 dark:text-slate-200 bg-transparent focus:outline-none font-bold cursor-pointer" onchange="localStorage.setItem('ttsSpeed', this.value)">
                            <option value="0.75">0.75x</option>
                            <option value="0.9" selected>0.9x</option>
                            <option value="1.0">1.0x</option>
                            <option value="1.25">1.25x</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-700/50 px-3 py-1.5 rounded-full border border-slate-200 dark:border-slate-600">
                        <i class="fas fa-microphone-alt text-slate-400 text-xs"></i>
                        <select id="ttsVoice" class="text-xs text-slate-700 dark:text-slate-200 bg-transparent focus:outline-none font-bold cursor-pointer w-20 md:w-28 truncate" onchange="localStorage.setItem('ttsVoice', this.value)">
                            <option value="">Default</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-700/50 px-3 py-1.5 rounded-full border border-slate-200 dark:border-slate-600">
                        <i class="fas fa-volume-up text-primary dark:text-blue-400 text-xs"></i>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="enable_bet_tts" class="sr-only peer" onchange="localStorage.setItem('enable_bet_tts', this.checked)">
                            <div class="w-7 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                <!-- 3D Hundreds Tabs -->
                <div class="px-4 mb-2">
                    <div class="flex overflow-x-auto gap-2 mb-3 pb-2 custom-scrollbar">
                        <?php for($s=0; $s<10; $s++): ?>
                            <button type="button" id="tab_btn_<?= $s ?>" onclick="showSection(<?= $s ?>)" class="whitespace-nowrap px-4 py-2 rounded-xl text-xs md:text-sm font-bold border transition-all duration-300 flex-none <?= $s == 0 ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white border-transparent shadow-md' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700' ?>">
                                <?= $s ?>00 - <?= $s ?>99
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- 3D Number Grids -->
                <div class="px-4 mb-5">
                    <?php for($s=0; $s<10; $s++): ?>
                        <div id="grid_section_<?= $s ?>" class="grid grid-cols-5 sm:grid-cols-10 gap-2 md:gap-3 max-h-[45vh] md:max-h-[25rem] overflow-y-auto custom-scrollbar p-2 rounded-2xl bg-slate-50/50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800/50 shadow-inner <?= $s == 0 ? '' : 'hidden' ?>">
                            <?php for($i=0; $i<=99; $i++): 
                                $num = $s . str_pad($i, 2, '0', STR_PAD_LEFT); 
                                $is_blocked = in_array($num, $blocked_numbers_arr);
                                $current_amt = isset($amounts_3d[$num]) ? $amounts_3d[$num] : 0;
                                $percent = ($max_limit_3d > 0) ? min(100, ($current_amt / $max_limit_3d) * 100) : 0;
                                
                                $p_color = 'bg-emerald-500';
                                $b_bg = 'bg-white dark:bg-slate-800';
                                $b_border = 'border-slate-200 dark:border-slate-700';
                                
                                if ($is_blocked) {
                                    $b_bg = 'bg-blocked-stripes opacity-70';
                                } elseif ($percent >= 100) { 
                                    $p_color = 'bg-rose-500'; $b_bg = 'bg-rose-50 dark:bg-rose-900/20'; $b_border = 'border-rose-300 dark:border-rose-800/50'; 
                                }
                                elseif ($percent >= 80) { $p_color = 'bg-rose-400'; $b_bg = 'bg-rose-50/50 dark:bg-rose-900/10'; $b_border = 'border-rose-200 dark:border-rose-800/30'; }
                                elseif ($percent >= 50) { $p_color = 'bg-amber-400'; $b_bg = 'bg-amber-50/50 dark:bg-amber-900/10'; $b_border = 'border-amber-200 dark:border-amber-800/30'; }
                                
                                $remaining = max(0, $max_limit_3d - $current_amt);
                            ?>
                                <button type="button" id="btn_<?= $num ?>" data-num="<?= $num ?>" title="<?= $is_blocked ? __('blocked_number_tooltip') : __('remaining_amount_tooltip') . ' ' . number_format($remaining) . ' ' . __('currency') ?>" onclick="<?= $is_blocked ? 'showBlockedAlert()' : 'toggleNumber(\''.$num.'\')' ?>" class="num-btn <?= $b_bg ?> <?= $b_border ?> border text-slate-700 dark:text-slate-200 font-bold text-sm md:text-base pt-3 pb-4 rounded-xl shadow-sm hover:shadow-md relative overflow-hidden flex flex-col items-center justify-center group">
                                    <span class="relative z-10 pointer-events-none"><?= $num ?></span>
                                    <div class="absolute bottom-0 left-0 w-full h-1.5 bg-slate-100 dark:bg-slate-700/50 pointer-events-none">
                                        <div class="progress-bar-inner <?= $p_color ?> h-full transition-all duration-500" style="width: <?= $percent ?>%"></div>
                                    </div>
                                    <div class="absolute inset-0 bg-blue-500/5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none hidden md:block"></div>
                                </button>
                            <?php endfor; ?>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Quick Pick Actions -->
                <div class="px-4 mb-4">
                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-wider"><?= __('quick_pick_label') ?></p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="quickPick3D('triples')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95 text-indigo-600 dark:text-indigo-400"><i class="fas fa-clone mr-1"></i> <?= __('triples') ?></button>
                        <button type="button" onclick="quickPickPrompt3D('head')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95 text-emerald-600 dark:text-emerald-400"><i class="fas fa-step-backward mr-1"></i> <?= __('head') ?></button>
                        <button type="button" onclick="quickPickPrompt3D('tail')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95 text-teal-600 dark:text-teal-400"><i class="fas fa-step-forward mr-1"></i> <?= __('tail') ?></button>
                        <button type="button" onclick="quickPickPrompt3D('khway')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95 text-amber-600 dark:text-amber-400"><i class="fas fa-random mr-1"></i> <?= __('khway') ?></button>
                        <button type="button" onclick="quickPickPrompt3D('permutation')" class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm active:scale-95 text-purple-600 dark:text-purple-400"><i class="fas fa-sync-alt mr-1"></i> <?= __('permutation') ?></button>
                    </div>
                </div>

                <div class="px-4 mb-4 relative">
                    <button type="button" onclick="quickPick3D('clear')" class="absolute top-3 right-7 text-rose-400 hover:text-rose-600 transition-colors bg-slate-100 dark:bg-slate-900 rounded-full p-1"><i class="fas fa-times-circle text-lg"></i></button>
                    <textarea id="bet_number" name="bet_number" rows="2" placeholder="<?= __('selected_numbers_placeholder') ?>" 
                           class="w-full p-4 pr-12 border-2 border-transparent rounded-2xl focus:border-blue-500 dark:focus:border-blue-400 focus:ring-4 focus:ring-blue-500/20 outline-none font-mono tracking-widest text-lg md:text-xl text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-slate-900/50 shadow-inner transition-all resize-none leading-relaxed" required autocomplete="off" onkeyup="syncGrid()" onchange="syncGrid()"><?= htmlspecialchars($_POST['bet_number'] ?? '') ?></textarea>
                </div>
                
                <div class="px-4 hidden md:block mt-6">
                    <button type="button" onclick="goToStep(2)" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 rounded-2xl text-lg shadow-lg hover:shadow-indigo-500/30 hover:-translate-y-1 transition-all duration-300">
                        <?= __('continue') ?> <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: Amount -->
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

            <!-- STEP 3: Confirm -->
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
                    <input type="password" name="pin" id="confirm_pin" maxlength="6" inputmode="numeric" placeholder="••••••" class="w-full py-4 px-4 border-2 border-slate-200 dark:border-slate-600 rounded-2xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 outline-none text-center tracking-[0.5em] font-mono text-3xl shadow-inner text-primary dark:text-blue-400 bg-slate-50 dark:bg-slate-900 focus:bg-white transition-all" required oninput="playClickSound()">
                </div>

                <div class="flex gap-4 max-w-xs mx-auto hidden md:flex">
                    <button type="button" onclick="goToStep(2)" class="w-1/3 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold py-4 rounded-2xl shadow-sm transition-all">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <!-- PC Button: Uses finalSubmitCheck to prevent default if needed -->
                    <button type="submit" onclick="return finalSubmitCheck(event)" class="w-2/3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 rounded-2xl text-lg shadow-lg hover:shadow-indigo-500/30 hover:-translate-y-1 transition-all duration-300">
                        <?= __('confirm') ?>
                    </button>
                </div>
            </div>
            
        </form>
    </div>

    <!-- Mobile Bottom Action Bar -->
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
                <!-- Mobile Validation Function attached here -->
                <button type="button" onclick="submitMobileForm()" class="w-3/4 bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-bold py-3.5 rounded-2xl text-lg shadow-md active:scale-95 transition-all flex items-center justify-center">
                    <i class="fas fa-check-circle mr-1"></i> <?= __('confirm') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Core Scripts -->
    <script>
        function playClickSound() {
            const snd = document.getElementById('clickSound');
            if (snd) { snd.currentTime = 0; snd.play().catch(e => {}); }
        }
        
        function vibrateOnClick(duration = 30) {
            if (navigator.vibrate) navigator.vibrate(duration);
        }

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
                let selectedVoiceName = localStorage.getItem('ttsVoice');
                if (selectedVoiceName) {
                    let voices = window.speechSynthesis.getVoices();
                    let selectedVoice = voices.find(voice => voice.name === selectedVoiceName);
                    if (selectedVoice) utterance.voice = selectedVoice;
                }
                window.speechSynthesis.speak(utterance);
            }
        }

        function loadTTSVoices() {
            const voiceSelect = document.getElementById('ttsVoice');
            if (!voiceSelect || !('speechSynthesis' in window)) return;
            let voices = window.speechSynthesis.getVoices();
            if (voices.length === 0) return;
            
            let filteredVoices = voices.filter(voice => voice.lang.startsWith('my') || voice.lang.startsWith('en'));
            let myanmarVoice = filteredVoices.find(voice => voice.lang.startsWith('my'));
            const savedVoice = localStorage.getItem('ttsVoice');
            voiceSelect.innerHTML = ''; 
            
            if (filteredVoices.length === 0) {
                voiceSelect.innerHTML = '<option value="">Default Voice</option>'; return;
            }
            
            let isAnySelected = false;
            filteredVoices.forEach((voice) => {
                const option = document.createElement('option');
                option.value = voice.name;
                let langLabel = voice.lang.startsWith('my') ? '(မြန်မာ)' : '(English)';
                option.textContent = `${voice.name} ${langLabel}`;
                if (savedVoice && voice.name === savedVoice) { option.selected = true; isAnySelected = true; } 
                else if (!savedVoice && myanmarVoice && voice.name === myanmarVoice.name) {
                    option.selected = true; isAnySelected = true; localStorage.setItem('ttsVoice', voice.name);
                }
                voiceSelect.appendChild(option);
            });

            if (!isAnySelected && filteredVoices.length > 0) {
                voiceSelect.options[0].selected = true;
                localStorage.setItem('ttsVoice', filteredVoices[0].name);
            }
        }

        let maxLimit3D = <?= $max_limit_3d ?>;
        let currentTotals3D = <?= json_encode($amounts_3d) ?>;
        const blockedNumbers3D = <?= json_encode(array_values($blocked_numbers_arr)) ?>;
        let userBalance = <?= $user['balance'] ?>;

        document.addEventListener('DOMContentLoaded', () => {
            syncGrid();
            let ttsCb = document.getElementById('enable_bet_tts');
            if (ttsCb) ttsCb.checked = localStorage.getItem('enable_bet_tts') === 'true';
            
            const savedSpeed = localStorage.getItem('ttsSpeed');
            if (savedSpeed) {
                const speedSelect = document.getElementById('ttsSpeed');
                if (speedSelect) speedSelect.value = savedSpeed;
            }

            if ('speechSynthesis' in window && speechSynthesis.onvoiceschanged !== undefined) {
                speechSynthesis.onvoiceschanged = loadTTSVoices;
            }
            setTimeout(loadTTSVoices, 100);
        });

        // Use SweetAlert2 for Blocked Numbers
        function showBlockedAlert() {
            Swal.fire({
                icon: 'error',
                title: '<?= addslashes(__('number_blocked_title')) ?>',
                text: '<?= addslashes(__('number_blocked_3d_msg')) ?>',
                confirmButtonColor: '#ef4444',
                confirmButtonText: '<?= addslashes(__('confirm')) ?>',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 shadow-md' }
            });
        }

        function showSection(sectionIndex) {
            playClickSound();
            for(let i=0; i<10; i++) {
                document.getElementById('grid_section_' + i).classList.add('hidden');
                let tab = document.getElementById('tab_btn_' + i);
                tab.classList.remove('bg-gradient-to-r', 'from-blue-600', 'to-indigo-600', 'text-white', 'shadow-md', 'border-transparent');
                tab.classList.add('bg-white', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300', 'border-slate-200', 'dark:border-slate-700', 'hover:bg-slate-50', 'dark:hover:bg-slate-700');
            }
            document.getElementById('grid_section_' + sectionIndex).classList.remove('hidden');
            let activeTab = document.getElementById('tab_btn_' + sectionIndex);
            activeTab.classList.remove('bg-white', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300', 'border-slate-200', 'dark:border-slate-700', 'hover:bg-slate-50', 'dark:hover:bg-slate-700');
            activeTab.classList.add('bg-gradient-to-r', 'from-blue-600', 'to-indigo-600', 'text-white', 'shadow-md', 'border-transparent');
        }

        function toggleNumber(num) {
            playClickSound();
            vibrateOnClick();
            speakNumber(num);
            let currentVal = document.getElementById('bet_number').value;
            let nums = currentVal.split(/[\s,]+/).filter(n => n.match(/^[0-9]{3}$/));
            
            let index = nums.indexOf(num);
            if (index > -1) nums.splice(index, 1);
            else nums.push(num);
            
            document.getElementById('bet_number').value = [...new Set(nums)].sort().join(', ');
            syncGrid();
        }

        function syncGrid() {
            let currentVal = document.getElementById('bet_number').value;
            let nums = currentVal.split(/[\s,]+/).filter(n => n.match(/^[0-9]{3}$/));
            
            document.querySelectorAll('.num-btn').forEach(btn => {
                const num = btn.getAttribute('data-num');
                const isSelected = nums.includes(num);
                const isBlocked = blockedNumbers3D.includes(num);
                const bar = btn.querySelector('.progress-bar-inner');
                const percent = bar ? parseFloat(bar.style.width) : 0;

                btn.classList.remove(
                    'bg-gradient-to-br', 'from-blue-500', 'to-indigo-600', 'text-white', 'shadow-md', 
                    'ring-2', 'ring-blue-500', 'ring-offset-2', 'dark:ring-offset-slate-900', 'transform', 'scale-105',
                    'bg-white', 'dark:bg-slate-800', 'bg-rose-50', 'bg-amber-50', 'bg-blocked-stripes', 
                    'border-slate-200', 'border-rose-300', 'border-amber-200', 'dark:border-slate-700', 'opacity-70', 'text-slate-700', 'dark:text-slate-200'
                );

                if (isSelected) {
                    btn.classList.add('bg-gradient-to-br', 'from-blue-500', 'to-indigo-600', 'text-white', 'shadow-md', 'ring-2', 'ring-blue-500', 'ring-offset-2', 'dark:ring-offset-slate-900', 'transform', 'scale-105');
                } else if (isBlocked) {
                    btn.classList.add('bg-blocked-stripes', 'opacity-70');
                } else {
                    btn.classList.add('text-slate-700', 'dark:text-slate-200');
                    if (percent >= 100) btn.classList.add('bg-rose-50', 'border-rose-300', 'dark:bg-rose-900/20');
                    else if (percent >= 80) btn.classList.add('bg-rose-50', 'border-rose-200', 'dark:bg-rose-900/10');
                    else if (percent >= 50) btn.classList.add('bg-amber-50', 'border-amber-200', 'dark:bg-amber-900/10');
                    else btn.classList.add('bg-white', 'dark:bg-slate-800', 'border-slate-200', 'dark:border-slate-700');
                }
            });
            
            calculateTotal();
        }

        // --- Handle Stepper Transitions & Limits using SweetAlert2 ---
        function goToStep(step) {
            vibrateOnClick();
            if (step === 2) {
                let currentVal = document.getElementById('bet_number').value;
                let nums = currentVal.split(/[\s,]+/).filter(n => n.match(/^[0-9]{3}$/));
                if (nums.length === 0) {
                    Swal.fire({ 
                        icon: 'warning', 
                        title: '<?= addslashes(__('info')) ?>',
                        text: '<?= addslashes(__('please_select_numbers')) ?>', 
                        confirmButtonColor: '#f59e0b',
                        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-8' } 
                    });
                    return;
                }
            }

            if (step === 3) {
                let amount = parseInt(document.getElementById('bet_amount').value) || 0;
                if (amount < 100) {
                    Swal.fire({ 
                        icon: 'warning', 
                        title: '<?= addslashes(__('info')) ?>',
                        text: '<?= addslashes(__('min_bet_required')) ?>', 
                        confirmButtonColor: '#f59e0b',
                        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-8' } 
                    });
                    return;
                }
                
                let totalAmountNeeded = parseInt(document.getElementById('mobile_live_total_amount').innerText.replace(/,/g, '')) || 0;
                
                if (totalAmountNeeded > userBalance) {
                    let balMsg = '<?= addslashes(__('insufficient_balance_msg_1')) ?> <b>' + totalAmountNeeded.toLocaleString() + ' Ks</b> <?= addslashes(__('insufficient_balance_msg_2')) ?> <b>' + Math.floor(userBalance).toLocaleString() + ' Ks</b> <?= addslashes(__('insufficient_balance_msg_3')) ?>';
                    Swal.fire({
                        title: '<?= addslashes(__('insufficient_balance')) ?>', 
                        html: balMsg, 
                        icon: 'warning',
                        showCancelButton: true, 
                        confirmButtonText: '<?= addslashes(__('deposit')) ?>', 
                        cancelButtonText: '<?= addslashes(__('close')) ?>',
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#6b7280',
                        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6', cancelButton: 'rounded-xl px-6' }
                    }).then((result) => { if(result.isConfirmed) window.location.href = 'deposit.php'; });
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
            if (step === 3) document.getElementById('confirm_pin').focus();
            
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

        function addAmount(amount) {
            vibrateOnClick();
            let currentAmountInput = document.getElementById('bet_amount');
            let currentVal = parseInt(currentAmountInput.value) || 0;
            currentAmountInput.value = currentVal + amount;
            calculateTotal();
        }
        function setAmount(amount) {
            vibrateOnClick();
            document.getElementById('bet_amount').value = amount;
            calculateTotal();
        }

        function calculateTotal() {
            let currentVal = document.getElementById('bet_number').value;
            let amount = parseInt(document.getElementById('bet_amount').value) || 0;
            let nums = currentVal.split(/[\s,]+/).filter(n => n.match(/^[0-9]{3}$/));
            let uniqueNums = [...new Set(nums)];
            let totalAmount = uniqueNums.length * amount;
            document.getElementById('mobile_live_kwek_count').innerText = uniqueNums.length;
            document.getElementById('mobile_live_total_amount').innerText = totalAmount.toLocaleString();
        }

        // --- SweetAlert2 for Limits Check ---
        function isLimitOk() {
            let currentVal = document.getElementById('bet_number').value;
            let amount = parseInt(document.getElementById('bet_amount').value) || 0;
            let rawNums = currentVal.split(/[\s,]+/).filter(n => n.match(/^[0-9]{3}$/));
            let uniqueNums = [...new Set(rawNums)];
            
            let exceededNums = [];
            uniqueNums.forEach(num => {
                let currentTotal = currentTotals3D[num] || 0;
                if (blockedNumbers3D.includes(num)) {
                    exceededNums.push(`<span class="text-rose-500 font-bold">${num}</span> (Blocked)`);
                } else if (currentTotal + amount > maxLimit3D) {
                    let available = maxLimit3D - currentTotal;
                    exceededNums.push(`<span class="text-rose-500 font-bold">${num}</span> (Available: ${available > 0 ? available : 0} Ks)`);
                }
            });

            if (exceededNums.length > 0) {
                Swal.fire({
                    icon: 'error', 
                    title: '<?= addslashes(__('limit_exceeded')) ?>',
                    html: '<div class="text-left text-sm mt-2 bg-rose-50 p-4 rounded-2xl border border-rose-100 max-h-48 overflow-y-auto leading-loose">' + exceededNums.join("<br>") + '</div>',
                    confirmButtonColor: '#ef4444',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-8 shadow-sm' }
                });
                return false;
            }
            return true;
        }

        function handleCheckLimitsButtonClick() { 
            // isLimitOk will popup an error if limits exceeded.
            // If ok, we proceed to step 3.
            if(isLimitOk()){
                Swal.fire({ 
                    icon: 'success', 
                    text: '<?= addslashes(__('limit_ok')) ?>', 
                    timer: 1500,
                    showConfirmButton: false,
                    customClass: { popup: 'rounded-3xl' } 
                }).then(() => {
                    goToStep(3);
                });
            } 
        }
        
        // --- Mobile HTML5 Form Validation wrapper ---
        function submitMobileForm() { 
            let form = document.getElementById('betForm');
            // If the PIN field (or any other required field) is empty, this will trigger the native browser tooltip.
            if (form.reportValidity()) {
                form.submit(); 
            }
        }
        
        // --- Desktop Form Validation wrapper ---
        function finalSubmitCheck(e) { 
            if(!isLimitOk()) {
                e.preventDefault(); 
                return false;
            }
            // Add custom check for PIN length (optional enhancement)
            const pinInput = document.getElementById('confirm_pin').value;
            if(pinInput.length !== 6) {
                Swal.fire({
                    icon: 'error',
                    title: '<?= addslashes(__('error')) ?>',
                    text: 'PIN must be 6 digits.',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-8' }
                });
                e.preventDefault();
                return false;
            }
            return true; 
        }

        // --- Quick Picks using Native Prompts ---
        function quickPick3D(type) {
            playClickSound(); vibrateOnClick();
            let nums = []; let speakText = "";
            if (type === 'triples') {
                speakText = '<?= addslashes(__('triples')) ?>';
                for (let i = 0; i <= 9; i++) nums.push(i.toString() + i.toString() + i.toString());
            } else if (type === 'clear') {
                document.getElementById('bet_number').value = '';
                syncGrid(); speakNumber('<?= addslashes(__('clear')) ?>'); return;
            }
            speakNumber(speakText);
            let currentVal = document.getElementById('bet_number').value.trim();
            let newVal = nums.join(', ');
            document.getElementById('bet_number').value = currentVal ? currentVal + ', ' + newVal : newVal;
            let allNums = document.getElementById('bet_number').value.split(/[\s,]+/).filter(n => n.match(/^[0-9]{3}$/));
            document.getElementById('bet_number').value = [...new Set(allNums)].sort().join(', ');
            syncGrid();
        }

        async function quickPickPrompt3D(type) {
            playClickSound(); vibrateOnClick();
            let promptText = "";
            if (type === 'head') promptText = '<?= addslashes(__('prompt_head')) ?>';
            else if (type === 'tail') promptText = '<?= addslashes(__('prompt_tail')) ?>';
            else if (type === 'khway') promptText = '<?= addslashes(__('prompt_khway_3d')) ?>';
            else if (type === 'permutation') promptText = '<?= addslashes(__('prompt_permutation')) ?>';

            // Replacing native prompt with SweetAlert2
            const { value: input } = await Swal.fire({
                title: promptText,
                input: 'number',
                inputAttributes: {
                    autocapitalize: 'off',
                    pattern: '[0-9]*'
                },
                showCancelButton: true,
                confirmButtonText: 'OK',
                confirmButtonColor: '#3b82f6',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6', cancelButton: 'rounded-xl px-6', input: 'text-center tracking-widest text-2xl font-mono' }
            });

            if (!input || input.trim() === "") return;
            let nums = [];
            
            if (type === 'khway') {
                let digits = input.trim().replace(/[^0-9]/g, '').split('');
                if (digits.length < 3) {
                    Swal.fire({ icon: 'error', text: '<?= addslashes(__('khway_3d_min_error')) ?>', customClass: { popup: 'rounded-3xl' } });
                    return;
                }
                speakNumber('<?= addslashes(__('khway')) ?>');
                for (let i = 0; i < digits.length; i++) {
                    for (let j = 0; j < digits.length; j++) {
                        for (let k = 0; k < digits.length; k++) {
                            // Option: If you want strictly distinct numbers for khway, add conditions like `if(i!==j && j!==k && i!==k)`
                            nums.push(digits[i] + digits[j] + digits[k]);
                        }
                    }
                }
            } else if (type === 'permutation') {
                let digits = input.trim().replace(/[^0-9]/g, '');
                if (digits.length !== 3) {
                    Swal.fire({ icon: 'error', text: '<?= addslashes(__('permutation_3d_error')) ?>', customClass: { popup: 'rounded-3xl' } });
                    return;
                }
                speakNumber('<?= addslashes(__('permutation')) ?> ' + digits);
                nums = getPermutations(digits);
            } else {
                let d = parseInt(input.trim());
                if (isNaN(d) || d < 0 || d > 9) {
                    Swal.fire({ icon: 'error', text: '<?= addslashes(__('single_digit_required')) ?>', customClass: { popup: 'rounded-3xl' } });
                    return;
                }
                let digit = d.toString();
                if (type === 'head') {
                    speakNumber('<?= addslashes(__('head')) ?> ' + digit);
                    for (let i = 0; i <= 9; i++) { for (let j = 0; j <= 9; j++) nums.push(digit + i.toString() + j.toString()); }
                } else if (type === 'tail') {
                    speakNumber('<?= addslashes(__('tail')) ?> ' + digit);
                    for (let i = 0; i <= 9; i++) { for (let j = 0; j <= 9; j++) nums.push(i.toString() + j.toString() + digit); }
                }
            }
            
            let currentVal = document.getElementById('bet_number').value.trim();
            document.getElementById('bet_number').value = currentVal ? currentVal + ', ' + nums.join(', ') : nums.join(', ');
            let allNums = document.getElementById('bet_number').value.split(/[\s,]+/).filter(n => n.match(/^[0-9]{3}$/));
            document.getElementById('bet_number').value = [...new Set(allNums)].sort().join(', ');
            syncGrid();
        }

        function getPermutations(str) {
            if (str.length <= 1) return [str];
            const permutations = new Set();
            for (let i = 0; i < str.length; i++) {
                const char = str[i];
                const remainingChars = str.slice(0, i) + str.slice(i + 1);
                for (const perm of getPermutations(remainingChars)) permutations.add(char + perm);
            }
            return Array.from(permutations).sort();
        }
    </script>

    <?php if ($receipt_data): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                html: `
                <div id="receiptContent" class="text-left bg-white p-4 md:p-5" style="font-family: 'Padauk', sans-serif;">
                    <div class="text-center mb-4 border-b border-dashed border-slate-300 pb-4">
                        <div class="w-14 h-14 bg-emerald-100 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm">
                            <i class="fas fa-check text-2xl"></i>
                        </div>
                        <h2 class="font-bold text-slate-800 text-lg md:text-xl"><?= __('bet_success_title') ?></h2>
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1"><?= __('official_betting_voucher') ?></p>
                    </div>
                    <div class="space-y-3 mb-5 text-sm">
                        <div class="flex justify-between items-center"><span class="text-slate-500 font-medium"><?= __('voucher_no') ?></span><span class="font-bold font-mono tracking-wider text-blue-600">#<?= $receipt_data['voucher_id'] ?></span></div>
                        <div class="flex justify-between items-center"><span class="text-slate-500 font-medium"><?= __('date_time') ?></span><span class="font-bold text-slate-800"><?= $receipt_data['date'] ?></span></div>
                        <div class="flex justify-between items-center"><span class="text-slate-500 font-medium"><?= __('category') ?></span><span class="font-bold bg-purple-100 text-purple-700 px-2.5 py-1 rounded-md text-xs border border-purple-200"><?= $receipt_data['type'] ?></span></div>
                        <div class="flex justify-between items-center border-t border-slate-100 pt-3 mt-2"><span class="text-slate-500 font-medium"><?= __('total') ?></span><span class="font-bold text-slate-800"><?= $receipt_data['kwek_count'] ?> <?= __('kwek') ?></span></div>
                    </div>
                    <div class="mb-5">
                        <p class="text-xs text-slate-500 mb-2 font-bold uppercase tracking-wider"><?= __('betted_numbers') ?></p>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-sm font-mono tracking-widest text-slate-800 max-h-24 overflow-y-auto leading-relaxed shadow-inner"><?= htmlspecialchars($receipt_data['numbers']) ?></div>
                    </div>
                    <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100 shadow-sm text-sm">
                        <div class="flex justify-between items-center mb-2"><span class="text-slate-600 font-medium"><?= __('total_cost_label') ?></span><span class="font-bold text-slate-800"><?= number_format($receipt_data['total_amount']) ?> Ks</span></div>
                        <?php if ($receipt_data['discount_amount'] > 0): ?>
                        <div class="flex justify-between items-center mb-2"><span class="text-emerald-600 font-medium"><?= __('discount_label') ?></span><span class="font-bold text-emerald-600">- <?= number_format($receipt_data['discount_amount']) ?> Ks</span></div>
                        <?php endif; ?>
                        <div class="flex justify-between items-center border-t border-blue-200/50 pt-3 mt-2">
                            <span class="font-bold text-slate-800 uppercase tracking-wide text-xs"><?= __('net_deduction') ?></span>
                            <span class="text-xl font-black text-rose-600 tracking-tight"><?= number_format($receipt_data['net_amount']) ?> <span class="text-xs font-normal">Ks</span></span>
                        </div>
                    </div>
                </div>`,
                showConfirmButton: true, confirmButtonText: '<i class="fas fa-download mr-1.5"></i> <?= __('download_voucher') ?>', confirmButtonColor: '#2563eb',
                showCancelButton: true, cancelButtonText: '<?= __('close') ?>', cancelButtonColor: '#64748b',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 shadow-md', cancelButton: 'rounded-xl px-6 py-2.5' },
                width: window.innerWidth > 768 ? '400px' : '90%', allowOutsideClick: false
            }).then((result) => { if (result.isConfirmed) downloadReceipt(); });
        });

        function downloadReceipt() {
            const element = document.getElementById('receiptContent');
            html2canvas(element, { scale: 3, backgroundColor: '#ffffff', logging: false, useCORS: true }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Thai2D3D_Voucher_#<?= $receipt_data['voucher_id'] ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>
