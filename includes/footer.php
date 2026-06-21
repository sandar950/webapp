<?php
$base_url = '';
if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/agent/') !== false) {
    $base_url = '../';
}

// လက်ရှိရောက်ရှိနေသော စာမျက်နှာနာမည်ကို ယူခြင်း
if (file_exists(dirname(__DIR__) . '/lang/language.php')) {
    require_once dirname(__DIR__) . '/lang/language.php';
}

$current_page = basename($_SERVER['PHP_SELF']);

// CS Links များကို Database မှ ဆွဲယူခြင်း
require_once dirname(__DIR__) . '/core/db_connect.php'; 
$cs_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('cs_messenger_link', 'cs_telegram_link', 'cs_viber_link')");
$cs_links = [];
if ($cs_stmt) {
    while ($row = $cs_stmt->fetch_assoc()) {
        $cs_links[$row['setting_key']] = $row['setting_value'];
    }
}
?>

    <div id="floatingCSButtons" class="fixed bottom-24 right-4 md:bottom-28 md:right-8 lg:right-10 flex flex-col gap-3 md:gap-4 z-40 transition-transform duration-500 transform translate-y-0">
        <?php if (!empty($cs_links['cs_messenger_link'])): ?>
            <a href="https://<?= htmlspecialchars(ltrim($cs_links['cs_messenger_link'], 'https://')) ?>" target="_blank" class="w-12 h-12 md:w-14 md:h-14 bg-blue-500 rounded-full flex items-center justify-center text-white shadow-lg hover:scale-110 transition-transform">
                <i class="fab fa-facebook-messenger text-2xl md:text-3xl"></i>
            </a>
        <?php endif; ?>
        
        <?php if (!empty($cs_links['cs_telegram_link'])): ?>
            <a href="https://<?= htmlspecialchars(ltrim($cs_links['cs_telegram_link'], 'https://')) ?>" target="_blank" class="w-12 h-12 md:w-14 md:h-14 bg-blue-400 rounded-full flex items-center justify-center text-white shadow-lg hover:scale-110 transition-transform">
                <i class="fab fa-telegram-plane text-2xl md:text-3xl"></i>
            </a>
        <?php endif; ?>

        <?php if (!empty($cs_links['cs_viber_link'])): ?>
            <a href="<?= htmlspecialchars($cs_links['cs_viber_link']) ?>" target="_blank" class="w-12 h-12 md:w-14 md:h-14 bg-purple-500 rounded-full flex items-center justify-center text-white shadow-lg hover:scale-110 transition-transform">
                <i class="fab fa-viber text-2xl md:text-3xl"></i>
            </a>
        <?php endif; ?>
    </div>

    <div id="bottomNavBar" class="fixed bottom-0 left-0 right-0 mx-auto w-full max-w-md md:max-w-3xl lg:max-w-5xl bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 md:border-x md:rounded-t-3xl flex justify-around py-2 md:py-3 shadow-[0_-2px_10px_rgba(0,0,0,0.05)] md:shadow-[0_-5px_25px_rgba(0,0,0,0.1)] z-50 transition-transform duration-500 transform translate-y-0">
        <a href="<?= $base_url ?>index.php" class="flex flex-col items-center bottom-nav-icon group <?= $current_page == 'index.php' ? 'active text-primary dark:text-blue-400' : 'text-gray-500 hover:text-primary dark:hover:text-blue-300' ?> transition-colors">
            <i class="fas fa-home mb-1 md:mb-1.5 text-lg md:text-xl group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] md:text-xs font-medium"><?= __('nav_home') ?></span>
        </a>
        <a href="<?= $base_url ?>deposit.php" class="flex flex-col items-center bottom-nav-icon group <?= in_array($current_page, ['deposit.php', 'withdraw.php']) ? 'active text-primary dark:text-blue-400' : 'text-gray-500 hover:text-primary dark:hover:text-blue-300' ?> transition-colors">
            <i class="fas fa-wallet mb-1 md:mb-1.5 text-lg md:text-xl group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] md:text-xs font-medium"><?= __('nav_wallet') ?></span>
        </a>
        <a href="<?= $base_url ?>bet_history.php" class="flex flex-col items-center bottom-nav-icon group <?= $current_page == 'bet_history.php' ? 'active text-primary dark:text-blue-400' : 'text-gray-500 hover:text-primary dark:hover:text-blue-300' ?> transition-colors">
            <i class="fas fa-gift mb-1 md:mb-1.5 text-lg md:text-xl group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] md:text-xs font-medium"><?= __('nav_history') ?></span>
        </a>
        <a href="<?= $base_url ?>guides.php" class="flex flex-col items-center bottom-nav-icon group <?= $current_page == 'guides.php' ? 'active text-primary dark:text-blue-400' : 'text-gray-500 hover:text-primary dark:hover:text-blue-300' ?> transition-colors">
            <i class="fas fa-book-open mb-1 md:mb-1.5 text-lg md:text-xl group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] md:text-xs font-medium"><?= __('nav_guide') ?></span>
        </a>
        <a href="<?= $base_url ?>profile.php" class="flex flex-col items-center bottom-nav-icon group <?= in_array($current_page, ['profile.php', 'commissions_history.php']) ? 'active text-primary dark:text-blue-400' : 'text-gray-500 hover:text-primary dark:hover:text-blue-300' ?> transition-colors">
            <i class="fas fa-user-circle mb-1 md:mb-1.5 text-lg md:text-xl group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] md:text-xs font-medium"><?= __('nav_profile') ?></span>
        </a>
    </div>

    <audio id="celebrationSound" src="<?= $base_url ?>assets/sounds/celebration.mp3" preload="auto"></audio>
    <audio id="fireworksSound" src="<?= $base_url ?>assets/sounds/fireworks.mp3" preload="auto"></audio>
    <audio id="notiSound" src="<?= $base_url ?>assets/sounds/notification.mp3" preload="auto"></audio>

    <script>
    <?php
    if (isset($_SESSION['confetti_winning_number'])) {
        echo "document.getElementById('celebrationSound').play().catch(e => console.log('Celebration sound play prevented:', e));\n";
        echo "document.getElementById('fireworksSound').play().catch(e => console.log('Fireworks sound play prevented:', e));\n";
        echo "if (navigator.vibrate) navigator.vibrate([300, 100, 300, 100, 600]);\n";
        
        echo "var winningNumber = '" . htmlspecialchars($_SESSION['confetti_winning_number']) . "';\n";
        echo "var congrats = document.createElement('div');\n";
        echo "congrats.className = 'fixed inset-0 flex items-center justify-center z-[9999] pointer-events-none animate__animated animate__zoomInDown';\n";
        echo "congrats.innerHTML = '<div class=\"text-center\"><h1 class=\"text-5xl md:text-7xl font-black text-yellow-400 italic tracking-tighter uppercase mb-4\" style=\"text-shadow: 3px 3px 0 #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 0 10px 30px rgba(0,0,0,0.5);\">" . addslashes(__('congratulations')) . "</h1><div class=\"inline-block bg-black/60 backdrop-blur-xl px-12 py-6 rounded-[40px] border-4 border-yellow-400 shadow-[0_0_50px_rgba(250,204,21,0.4)] animate__animated animate__pulse animate__infinite\"><span class=\"text-8xl md:text-[12rem] font-black text-transparent bg-clip-text bg-gradient-to-b from-yellow-100 via-yellow-400 to-yellow-600 tracking-widest drop-shadow-[0_0_20px_rgba(250,204,21,1)]\">' + winningNumber + '</span></div></div>';\n";
        echo "document.body.appendChild(congrats);\n";

        echo "var duration = 4 * 1000;\n";
        echo "var end = Date.now() + duration;\n";
        echo "(function frame() {\n";
        echo "  confetti({ particleCount: 3, angle: 60, spread: 55, origin: { x: 0, y: 0.8 }, zIndex: 10000, colors: ['#FFD700', '#C0C0C0'], shapes: ['star'], gravity: 0.3, decay: 0.98, scalar: 4, drift: 0.5 });\n";
        echo "  confetti({ particleCount: 3, angle: 120, spread: 55, origin: { x: 1, y: 0.8 }, zIndex: 10000, colors: ['#FFD700', '#C0C0C0'], shapes: ['star'], gravity: 0.3, decay: 0.98, scalar: 4, drift: 0.5 });\n";
        echo "  if (Date.now() < end) requestAnimationFrame(frame);\n";
        echo "}());\n";

        echo "setTimeout(function() {\n";
        echo "  congrats.classList.remove('animate__zoomInDown');\n";
        echo "  congrats.classList.add('animate__fadeOut');\n";
        echo "  setTimeout(function() { congrats.remove(); }, 1000);\n";
        echo "}, duration);\n";

        unset($_SESSION['confetti_winning_number']);
        unset($_SESSION['confetti_message']);
    }

    $msg = $success_message ?? $_SESSION['success'] ?? "";
    $err = $error_message ?? $_SESSION['error'] ?? "";
    
    unset($_SESSION['success'], $_SESSION['error']);

    if ($msg): ?>
        var notiSnd = document.getElementById('notiSound');
        if (notiSnd) {
            notiSnd.play().catch(e => console.log('Audio autoplay prevented'));
        }

        Swal.fire({
            icon: 'success',
            title: '<?= __('success') ?>',
            html: '<?= addslashes($msg) ?>',
            confirmButtonColor: '#1a428a'
        });
    <?php elseif ($err): ?>
        if (navigator.vibrate && "<?= addslashes($err) ?>".includes("PIN")) {
            navigator.vibrate([200, 100, 200]);
        }

        Swal.fire({
            icon: 'error',
            title: '<?= __('error') ?>',
            html: '<?= addslashes($err) ?>',
            confirmButtonColor: '#1a428a'
        });
    <?php endif; ?>

    let isScrolling;
    let lastScrollTop = 0;
    const csButtons = document.getElementById('floatingCSButtons');
    const bottomNav = document.getElementById('bottomNavBar');

    if (csButtons || bottomNav) {
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            const isAtBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 5);
            if (isAtBottom) {
                if(csButtons) { csButtons.style.transform = 'translateY(0)'; csButtons.style.pointerEvents = 'auto'; } 
                if(bottomNav) { bottomNav.style.transform = 'translateY(0)'; } 
                lastScrollTop = scrollTop; 
                return;
            }

            if (scrollTop > lastScrollTop && scrollTop > 50) {
                if(csButtons) { csButtons.style.transform = 'translateY(150%)'; csButtons.style.pointerEvents = 'none'; } 
                if(bottomNav) { bottomNav.style.transform = 'translateY(150%)'; } 
            } else {
                if(csButtons) { csButtons.style.transform = 'translateY(0)'; csButtons.style.pointerEvents = 'auto'; } 
                if(bottomNav) { bottomNav.style.transform = 'translateY(0)'; } 
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop; 
        }, false);
    }

    const themeBtn = document.getElementById('userThemeToggle');
    if (themeBtn) {
        const themeIcon = themeBtn.querySelector('i');
        
        function updateIcon() {
            if (document.documentElement.classList.contains('dark')) {
                themeIcon.classList.replace('fa-moon', 'fa-sun');
            } else {
                themeIcon.classList.replace('fa-sun', 'fa-moon');
            }
        }
        updateIcon();

        themeBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('user_theme', isDark ? 'dark' : 'light');
            updateIcon();
            if (navigator.vibrate) navigator.vibrate(40);
        });
    }

    <?php if (isset($_SESSION['user_id'])): ?>
    const notiLocalKey = 'lastNotiId_' + <?= $_SESSION['user_id'] ?>;
    
    function checkUserNotifications() {
        fetch('<?= $base_url ?>ajax_check_user_noti.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let badge = document.getElementById('headerNotiBadge');
                    if (badge) {
                        if (data.unread_count > 0) {
                            badge.textContent = data.unread_count;
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }

                    if (data.latest_id > 0) {
                        let lastNotiId = localStorage.getItem(notiLocalKey);
                        
                        if (!lastNotiId) {
                            localStorage.setItem(notiLocalKey, data.latest_id);
                        } else if (data.latest_id > parseInt(lastNotiId)) {
                            localStorage.setItem(notiLocalKey, data.latest_id);
                            
                            let notiSnd = document.getElementById('notiSound');
                            if (notiSnd) notiSnd.play().catch(e => console.log('Audio autoplay prevented'));
                            
                            if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
                            
                            if (data.latest_msg) {
                                let swalTitle = data.is_important ? '🚨 <?= __('urgent_notification') ?>' : '<?= __('new_notification') ?>';
                                let swalIcon = data.is_important ? 'warning' : 'info';
                                let swalColor = data.is_important ? '#dc2626' : '#1a428a'; 

                                if ('speechSynthesis' in window && !window.speechSynthesis.speaking) {
                                    let ttsText = data.is_important ? "<?= __('urgent_notification_tts') ?>" : "<?= __('new_notification_tts') ?>";
                                    let utterance = new SpeechSynthesisUtterance(ttsText);
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

                                Swal.fire({ 
                                    icon: swalIcon, 
                                    title: swalTitle, 
                                    text: data.latest_msg, 
                                    showCancelButton: true,
                                    confirmButtonText: '<?= __('view') ?>',
                                    cancelButtonText: '<i class="fas fa-stop-circle mr-1"></i> <?= __('stop_sound_close') ?>',
                                    confirmButtonColor: swalColor 
                                }).then((result) => {
                                    let formData = new FormData();
                                    formData.append('action', 'clear_badge');
                                    fetch('<?= $base_url ?>ajax_check_user_noti.php', { method: 'POST', body: formData });
                                    
                                    let badge = document.getElementById('headerNotiBadge');
                                    if (badge) { badge.classList.add('hidden'); badge.textContent = '0'; }

                                    if (result.isConfirmed) {
                                        window.location.href = '<?= $base_url ?>notifications.php';
                                    }
                                    
                                    if (window.speechSynthesis) window.speechSynthesis.cancel();
                                });
                            }
                        }
                    }
                }
            })
            .catch(e => console.error('Notification check failed:', e));
    }
    
    // Server ဝန်ပိမှုသက်သာစေရန် ၁၀ စက္ကန့်မှ စက္ကန့် ၆၀ (၁ မိနစ်) သို့ ပြောင်းလဲထားပါသည်
    setInterval(checkUserNotifications, 60000);
    <?php endif; ?>
    </script>
</body>
</html>
