<?php
$base_url = '';
if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/agent/') !== false) {
    $base_url = '../';
}

if (file_exists(dirname(__DIR__) . '/lang/language.php')) {
    require_once dirname(__DIR__) . '/lang/language.php';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'mm') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a428a">
    <link rel="manifest" href="manifest.json">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Thai 2D3D' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php 
       // db_connect မပါသေးပါက ထည့်ပေးရန်လိုအပ်ပါသည် (index.php တွင် ပါပြီးသားဖြစ်၍ ပြဿနာမရှိပါ)
       require_once __DIR__ . '/seo.php'; 
    ?>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
  <link rel="apple-touch-icon" sizes="180x180" href="https://file.thai2d3dgame.com/files/notificationImages/all/allNotiNew.png">
  <link rel="icon" type="image/png" href="https://file.thai2d3dgame.com/files/notificationImages/all/allNotiNew.png">


    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Padauk', sans-serif; background-color: #f3f4f6; transition: background-color 0.3s ease; }
        .bg-primary { background-color: #1a428a; }
        .text-gold { color: #ffd700; }
        .bottom-nav-icon { font-size: 1.25rem; color: #6b7280; }
        .bottom-nav-icon.active { color: #1a428a; }

        /* Dark Mode Global Overrides */
        html.dark body { background-color: #111827 !important; color: #f9fafb !important; }
        html.dark .bg-white { background-color: #1f2937 !important; border-color: #374151 !important; }
        html.dark .bg-gray-100 { background-color: #111827 !important; }
        html.dark .bg-gray-50 { background-color: #374151 !important; color: #f9fafb !important; }
        html.dark .text-gray-500, html.dark .text-gray-600, html.dark .text-gray-700, html.dark .text-gray-800 { color: #d1d5db !important; }
        html.dark .border-gray-100, html.dark .border-gray-200, html.dark .border-gray-300 { border-color: #374151 !important; }
        
        /* Nav & Cards Overrides */
        html.dark #bottomNavBar { background-color: #1f2937 !important; border-top-color: #374151 !important; }
        html.dark .bottom-nav-icon.active { color: #60a5fa !important; }
        html.dark input, html.dark textarea, html.dark select { 
            background-color: #374151 !important; 
            color: #f9fafb !important; 
            border-color: #4b5563 !important; 
        }
        html.dark .bg-blue-50 { background-color: rgba(59, 130, 246, 0.15) !important; color: #93c5fd !important; }
    </style>
    <script>
        // Theme Initialization (Run immediately to prevent flash of white)
        (function() {
            const savedTheme = localStorage.getItem('user_theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                .then(registration => {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                })
                .catch(error => {
                    console.log('ServiceWorker registration failed: ', error);
                });
            });
        }

        // Request Notification Permission
        function requestNotificationPermission() {
            if ('Notification' in window) {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        console.log('Notification permission granted.');
                    }
                });
            }
        }
        
        // Auto request on load if not determined
        if ('Notification' in window && Notification.permission === 'default') {
            setTimeout(requestNotificationPermission, 3000);
        }

        // Floating Language & Theme Switcher (Responsive Updated)
        window.addEventListener('DOMContentLoaded', () => {
            const floatingUI = document.createElement('div');
            // Added flex layout to hold both theme and language toggles
            floatingUI.className = 'fixed top-24 right-4 md:top-8 md:right-8 lg:right-10 z-50 flex flex-col md:flex-row gap-1 md:gap-2 bg-white/90 dark:bg-gray-800/90 backdrop-blur shadow-lg p-1 md:px-2 md:py-1.5 rounded-full md:rounded-2xl border border-gray-200 dark:border-gray-700 transition-all duration-300 items-center';
            
            floatingUI.innerHTML = `
                <button id="floatingThemeToggle" title="Toggle Theme" class="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center rounded-full md:rounded-xl text-sm md:text-base transition-all duration-300 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:scale-105">
                    <i class="fas fa-moon dark:hidden"></i>
                    <i class="fas fa-sun hidden dark:inline-block text-yellow-400"></i>
                </button>
                
                <div class="w-6 h-px md:w-px md:h-6 bg-gray-300 dark:bg-gray-600"></div>

                <a href="?lang=mm" title="မြန်မာ" class="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center rounded-full md:rounded-xl text-[10px] md:text-xs font-bold transition-all duration-300 <?= ($_SESSION['lang'] ?? 'mm') === 'mm' ? 'bg-primary text-white shadow-md transform scale-105' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:scale-105' ?>">MM</a>
                <a href="?lang=en" title="English" class="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center rounded-full md:rounded-xl text-[10px] md:text-xs font-bold transition-all duration-300 <?= ($_SESSION['lang'] ?? 'mm') === 'en' ? 'bg-primary text-white shadow-md transform scale-105' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:scale-105' ?>">EN</a>
            `;
            document.body.appendChild(floatingUI);

            // Theme Toggle Click Listener
            const themeBtn = document.getElementById('floatingThemeToggle');
            themeBtn.addEventListener('click', () => {
                document.documentElement.classList.toggle('dark');
                const isDark = document.documentElement.classList.contains('dark');
                localStorage.setItem('user_theme', isDark ? 'dark' : 'light');
                if (navigator.vibrate) navigator.vibrate(40);
            });
        });
    </script>
</head>