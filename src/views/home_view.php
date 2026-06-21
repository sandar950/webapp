<?php 
// တိုက်ရိုက်ခေါ်ယူခြင်းကို တားဆီးရန်
if (!isset($user)) { exit('Direct access not permitted.'); }
?>

<body class="w-full lg:max-w-5xl md:max-w-3xl mx-auto relative min-h-screen pb-20 bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300">

    <div class="bg-primary text-white rounded-b-3xl md:rounded-b-[3rem] pb-12 md:pb-16 shadow-lg">
        <div class="flex justify-between items-center p-4 md:p-6 md:px-8">
            <button class="bg-yellow-500 hover:bg-yellow-400 text-white px-3 md:px-4 py-1.5 md:py-2 rounded flex items-center font-bold text-sm md:text-base shadow transition-colors">
                <?= __('app_download_btn') ?> <i class="fas fa-download ml-2"></i>
            </button>
            <div class="text-center">
                <h1 class="text-xl md:text-2xl font-bold leading-tight tracking-wider">Thai 2D3D</h1>
                <span class="text-[10px] md:text-xs bg-green-500/20 text-green-100 px-2.5 py-0.5 rounded-full inline-flex items-center mt-1 border border-green-500/30">
                    <i class="fas fa-circle text-[8px] md:text-[10px] text-green-400 mr-1.5 animate-pulse"></i> <?= __('online') ?>: <?= $online_count ?>
                </span>
            </div>
            <div class="flex items-center">
                <button id="userThemeToggle" class="mr-4 md:mr-6 text-gray-200 hover:text-white transition-transform hover:rotate-12">
                    <i class="fas fa-moon text-xl md:text-2xl"></i>
                </button>
                <button class="mr-4 md:mr-6 hover:rotate-180 transition-transform duration-500"><i class="fas fa-sync-alt text-xl md:text-2xl text-gray-200 hover:text-white"></i></button>
                <a href="logout.php" title="<?= __('logout_tooltip') ?>"><i class="fas fa-sign-out-alt text-xl md:text-2xl text-red-300 hover:text-red-400 transition-colors"></i></a>
            </div>
        </div>

        <div class="flex justify-between items-center px-4 md:px-8 mt-2 md:mt-4">
            <div class="flex items-center space-x-3 md:space-x-4">
                <div class="w-12 h-12 md:w-16 md:h-16 bg-transparent border-2 border-white rounded-full flex items-center justify-center overflow-hidden bg-primary shadow-sm">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-user text-2xl md:text-3xl"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h2 class="font-bold text-lg md:text-xl"><?= htmlspecialchars($user['username']) ?></h2>
                    <p class="text-xs md:text-sm opacity-80"><?= htmlspecialchars($user['phone']) ?></p>
                </div>
            </div>
            <div class="flex items-center space-x-4 md:space-x-6">
                <div class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-red-500 overflow-hidden border border-white flex flex-col relative shadow-inner">
                    <div class="h-1/3 bg-yellow-400"></div>
                    <div class="h-1/3 bg-green-500"></div>
                    <div class="h-1/3 bg-red-500"></div>
                    <i class="fas fa-star text-white absolute text-[8px] md:text-[10px] top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"></i>
                </div>
                <a href="notifications.php" class="relative block hover:scale-110 transition-transform">
                    <i class="fas fa-bell text-2xl md:text-3xl"></i>
                    <span id="headerNotiBadge" class="absolute -top-1.5 -right-2 md:-right-2.5 bg-red-600 text-white text-[10px] md:text-xs px-1.5 md:px-2 rounded-full border border-primary <?= $user['noti_count'] > 0 ? '' : 'hidden' ?>"><?= htmlspecialchars($user['noti_count']) ?></span>
                </a>
            </div>
        </div>
    </div>

    <div class="px-4 md:px-8 -mt-8 md:-mt-10 relative z-10">
        <div class="bg-white rounded-2xl shadow-xl p-5 md:p-6 border border-gray-100">
            <div class="flex justify-between items-center border-b border-gray-100 pb-4 mb-4">
                <div>
                   <p class="text-primary font-bold text-sm md:text-base flex items-center mb-1">
                    <?= __('balance') ?> (<?= __('currency') ?>) 
                    <i id="toggleBalanceBtn" class="fas fa-eye ml-2 text-gray-400 cursor-pointer hover:text-gray-600 transition-colors"></i>
                </p>
                <p id="balanceAmount" data-balance="<?= htmlspecialchars($user['balance']) ?>" class="text-primary font-bold text-2xl md:text-3xl tracking-tight">
                    <?= htmlspecialchars($user['balance']) ?>
                </p>
                </div>
                <div class="bg-blue-50 p-3 md:p-4 rounded-full">
                    <i class="fas fa-wallet text-primary text-2xl md:text-3xl opacity-80"></i>
                </div>
            </div>
            
            <div class="grid grid-cols-4 gap-2 md:gap-6 text-center mt-2">
                <a href="deposit.php" class="text-primary font-bold text-xs md:text-sm hover:text-blue-700 flex flex-col items-center group">
                    <div class="bg-blue-50 group-hover:bg-blue-100 p-2 md:p-3 rounded-full mb-2 transition-colors">
                        <i class="fas fa-plus-circle text-xl md:text-2xl"></i>
                    </div>
                    <?= __('deposit') ?>
                </a>
                <a href="withdraw.php" class="text-primary font-bold text-xs md:text-sm hover:text-blue-700 flex flex-col items-center group">
                    <div class="bg-blue-50 group-hover:bg-blue-100 p-2 md:p-3 rounded-full mb-2 transition-colors">
                        <i class="fas fa-minus-circle text-xl md:text-2xl"></i>
                    </div>
                    <?= __('withdraw') ?>
                </a>
                <a href="transfer.php" class="text-purple-600 font-bold text-xs md:text-sm hover:text-purple-800 flex flex-col items-center group">
                    <div class="bg-purple-50 group-hover:bg-purple-100 p-2 md:p-3 rounded-full mb-2 transition-colors">
                        <i class="fas fa-exchange-alt text-xl md:text-2xl"></i>
                    </div>
                    <?= __('transfer') ?>
                </a>
                <a href="loan.php" class="text-blue-500 font-bold text-xs md:text-sm hover:text-blue-700 flex flex-col items-center group">
                    <div class="bg-blue-50 group-hover:bg-blue-100 p-2 md:p-3 rounded-full mb-2 transition-colors">
                        <i class="fas fa-hand-holding-usd text-xl md:text-2xl"></i>
                    </div>
                    <?= __('loan') ?>
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($bonus_alert)): ?>
        <div class="px-4 md:px-8 mt-5 relative z-10">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 md:py-4 rounded-xl text-sm md:text-base font-bold shadow-sm text-center animate-pulse flex justify-center items-center">
                <i class="fas fa-gift mr-2 text-lg"></i> <?= htmlspecialchars($bonus_alert) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="px-4 md:px-8 mt-5 relative z-10">
        <div class="bg-white rounded-full shadow-sm border border-gray-200 p-1 md:p-1.5 flex items-center overflow-hidden">
            <div class="bg-primary text-white w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center flex-shrink-0 z-10 shadow-sm">
                <i class="fas fa-bullhorn text-xs md:text-sm animate-pulse"></i>
            </div>
            <div class="flex-1 overflow-hidden px-2 md:px-4 flex items-center">
                <marquee behavior="scroll" direction="left" scrollamount="4" class="text-sm md:text-base font-bold text-gray-700 mt-1 md:mt-0">
                    <?= __('welcome_marquee') ?>
                </marquee>
            </div>
        </div>
    </div>

    <div class="mt-6 px-4 md:px-8">
        <?php if (count($valid_banners) > 0): ?>
            <style>
                .hide-scrollbar::-webkit-scrollbar { display: none; }
                .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            </style>
            
            <?php 
                $grid_class = "md:grid-cols-3";
                if(count($valid_banners) == 1) $grid_class = "md:grid-cols-1";
                if(count($valid_banners) == 2) $grid_class = "md:grid-cols-2";
            ?>
            
            <div id="bannerContainer" class="flex md:grid <?= $grid_class ?> overflow-x-auto md:overflow-visible snap-x snap-mandatory hide-scrollbar gap-4 pb-2 md:pb-0 scroll-smooth">
                <?php foreach ($valid_banners as $b_url): ?>
                    <div class="banner-slide w-full md:w-auto flex-none snap-center h-40 md:h-48 lg:h-56 rounded-xl overflow-hidden relative shadow-md hover:shadow-xl transition-shadow duration-300">
                        <img src="<?= htmlspecialchars($b_url) ?>" alt="Home Banner" class="w-full h-full object-cover">
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if(count($valid_banners) > 1): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const container = document.getElementById('bannerContainer');
                    const slides = document.querySelectorAll('.banner-slide');
                    
                    if(window.innerWidth < 768 && slides.length > 1) { 
                        let currentIndex = 0;
                        setInterval(() => {
                            currentIndex = (currentIndex + 1) % slides.length;
                            const slideWidth = slides[0].offsetWidth + 16;
                            container.scrollTo({
                                left: currentIndex * slideWidth,
                                behavior: 'smooth'
                            });
                        }, 3000);
                    }
                });
            </script>
            <?php endif; ?>

        <?php else: ?>
            <div class="w-full h-40 md:h-48 lg:h-56 bg-gray-800 rounded-xl overflow-hidden relative flex items-center justify-center text-white text-center px-4 shadow-md" style="background-image: linear-gradient(to right, #2b1055, #7597de);">
                 <p class="text-sm md:text-lg font-bold"><?= __('banner_placeholder') ?></p>
            </div>
        <?php endif; ?>
    </div>


    <div class="grid grid-cols-2 gap-4 md:gap-8 px-4 md:px-8 mt-6 md:mt-8">
        <a href="2d_bet.php" class="group bg-primary text-white py-5 md:py-8 rounded-2xl shadow-lg relative overflow-hidden font-bold text-xl md:text-3xl border border-blue-800 flex justify-center items-center hover:-translate-y-1 hover:shadow-2xl transition-all duration-300">
            <?= __('title_2d_bet') ?>
            <div class="absolute top-0 right-0 w-10 h-10 md:w-16 md:h-16 bg-yellow-500 opacity-20 rounded-bl-full group-hover:scale-110 transition-transform"></div>
            <i class="fas fa-play-circle absolute bottom-[-10px] left-[-10px] text-5xl md:text-7xl opacity-10 group-hover:rotate-12 transition-transform"></i>
        </a>
        <a href="3d_bet.php" class="group bg-primary text-white py-5 md:py-8 rounded-2xl shadow-lg relative overflow-hidden font-bold text-xl md:text-3xl border border-blue-800 flex justify-center items-center hover:-translate-y-1 hover:shadow-2xl transition-all duration-300">
            <?= __('title_3d_bet') ?>
            <div class="absolute top-0 right-0 w-10 h-10 md:w-16 md:h-16 bg-yellow-500 opacity-20 rounded-bl-full group-hover:scale-110 transition-transform"></div>
            <i class="fas fa-dice absolute bottom-[-10px] left-[-10px] text-5xl md:text-7xl opacity-10 group-hover:rotate-12 transition-transform"></i>
        </a>
    </div>

    <div class="text-center mt-8 md:mt-12 mb-6">
        <p class="text-red-500 text-sm md:text-base font-semibold"><?= __('version') ?>: 1.7.3</p>
    </div>

    <?php if (($announcement['announcement_is_active'] ?? '0') === '1' && (!empty($announcement['announcement_text']) || !empty($announcement['announcement_image_url']))): ?>
        <div id="announcementModal" class="fixed inset-0 bg-black bg-opacity-75 z-[60] flex items-center justify-center p-4 hidden">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-[slide-in_0.3s_ease-out] relative">
                <button onclick="closeAnnouncement()" class="absolute top-3 right-3 w-8 h-8 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-full flex items-center justify-center transition z-10">
                    <i class="fas fa-times"></i>
                </button>
                <div class="p-6 md:p-8 pb-4 text-center">
                    <h2 class="text-xl md:text-2xl font-bold text-primary mb-4"><i class="fas fa-bullhorn text-red-500 mr-2 animate-bounce"></i> <?= __('special_announcement') ?></h2>
                    <?php if (!empty($announcement['announcement_image_url'])): ?>
                        <img src="<?= htmlspecialchars($announcement['announcement_image_url']) ?>" alt="Announcement" class="w-full h-auto rounded-lg mb-5 object-cover max-h-64 mx-auto shadow-sm">
                    <?php endif; ?>
                    <?php if (!empty($announcement['announcement_text'])): ?>
                        <div class="text-sm md:text-base text-gray-700 leading-relaxed text-left bg-gray-50 p-4 rounded-xl border border-gray-200">
                            <?= nl2br(htmlspecialchars($announcement['announcement_text'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="px-6 md:px-8 pb-6 text-center mt-2">
                    <button onclick="closeAnnouncement()" class="bg-primary hover:bg-blue-800 text-white font-bold py-2.5 px-8 rounded-xl shadow-md transition w-full md:w-auto">
                        <?= __('i_have_read') ?>
                    </button>
                </div>
            </div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (!sessionStorage.getItem('announcement_seen')) {
                    document.getElementById('announcementModal').classList.remove('hidden');
                }
            });

            function closeAnnouncement() {
                document.getElementById('announcementModal').classList.add('hidden');
                sessionStorage.setItem('announcement_seen', 'true');
            }
        </script>
    <?php endif; ?>

    <?php if (!empty($imp_noti_message)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const notiId = <?= $imp_noti_id ?>;
                if (!sessionStorage.getItem('important_noti_shown_' + notiId)) {
                    sessionStorage.setItem('important_noti_shown_' + notiId, 'true');
                    
                    let textToSpeak = '<?= __('urgent_notification_tts') ?>';
                    
                    Swal.fire({
                        title: '🚨 <?= __('urgent_notification') ?>',
                        html: '<div class="text-sm md:text-base text-left text-gray-700 leading-relaxed mb-3"><?= addslashes($imp_noti_html) ?></div>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        confirmButtonText: '<i class="fas fa-check-double mr-1"></i> <?= __('i_have_read') ?>',
                        cancelButtonText: '<i class="fas fa-stop-circle mr-1"></i> <?= __('stop_sound') ?>',
                        cancelButtonColor: '#6b7280',
                        allowOutsideClick: false,
                        customClass: {
                            popup: 'rounded-2xl'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            let formData = new FormData();
                            formData.append('action', 'mark_read');
                            formData.append('noti_id', notiId);
                            formData.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'); 
                            
                            fetch('notifications.php', { method: 'POST', body: formData })
                            .then(() => {
                                let badge = document.getElementById('headerNotiBadge');
                                if (badge) {
                                    let count = parseInt(badge.textContent) || 0;
                                    if (count > 0) {
                                        badge.textContent = count - 1;
                                        if (count - 1 === 0) badge.classList.add('hidden');
                                    }
                                }
                            });
                        }
                        if (window.speechSynthesis) window.speechSynthesis.cancel();
                    });
                    
                    const playAlert = () => {
                        if ('speechSynthesis' in window && !window.speechSynthesis.speaking) {
                            let utterance = new SpeechSynthesisUtterance(textToSpeak);
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
                    };

                    setTimeout(playAlert, 800);
                    
                    document.addEventListener('click', function onClickOnce() {
                        playAlert();
                        document.removeEventListener('click', onClickOnce);
                    }, { once: true });
                }
            });
        </script>
    <?php endif; ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBalanceBtn = document.getElementById('toggleBalanceBtn');
        const balanceAmount = document.getElementById('balanceAmount');
        
        if (toggleBalanceBtn && balanceAmount) {
            const actualBalance = balanceAmount.getAttribute('data-balance');
            
            if (localStorage.getItem('hide_balance') === 'true') {
                toggleBalanceBtn.classList.replace('fa-eye', 'fa-eye-slash');
                balanceAmount.textContent = '*****';
            }

            toggleBalanceBtn.addEventListener('click', function() {
                if (this.classList.contains('fa-eye')) {
                    this.classList.replace('fa-eye', 'fa-eye-slash');
                    balanceAmount.textContent = '*****';
                    localStorage.setItem('hide_balance', 'true');
                } else {
                    this.classList.replace('fa-eye-slash', 'fa-eye');
                    balanceAmount.textContent = actualBalance;
                    localStorage.setItem('hide_balance', 'false');
                }
            });
        }

        const themeToggleBtn = document.getElementById('userThemeToggle');
        if (themeToggleBtn) {
            const themeIcon = themeToggleBtn.querySelector('i');
            const htmlElement = document.documentElement;

            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                htmlElement.classList.add('dark');
                if(themeIcon) themeIcon.classList.replace('fa-moon', 'fa-sun');
            } else {
                htmlElement.classList.remove('dark');
                if(themeIcon) themeIcon.classList.replace('fa-sun', 'fa-moon');
            }

            themeToggleBtn.addEventListener('click', function() {
                htmlElement.classList.toggle('dark');
                
                if (htmlElement.classList.contains('dark')) {
                    localStorage.setItem('theme', 'dark');
                    if(themeIcon) themeIcon.classList.replace('fa-moon', 'fa-sun');
                } else {
                    localStorage.setItem('theme', 'light');
                    if(themeIcon) themeIcon.classList.replace('fa-sun', 'fa-moon');
                }
            });
        }
    });
</script>

