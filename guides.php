<?php
session_start();

require_once __DIR__ . '/lang/language.php';

// Login ဝင်ထားခြင်း မရှိပါက login.php သို့ ပြန်ပို့ရန်
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/core/db_connect.php';

// Fetch guides dynamically from the database
$guides_stmt = $conn->query("SELECT guide_key, icon_class FROM guides WHERE is_active = 1 ORDER BY sort_order ASC");
$guides = [];
if ($guides_stmt) {
    $guides = $guides_stmt->fetch_all(MYSQLI_ASSOC);
}

?>

<?php 
$page_title = __('title_user_guide') . " - Thai 2D3D";
require_once __DIR__ . '/includes/header.php'; 
?>

<style>
    .accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
    }
    .rotate-180 {
        transform: rotate(180deg);
    }
    .guide-list li {
        position: relative;
        padding-left: 1.5rem; /* Space for the icon */
    }
    .guide-list li::before {
        content: '\f101'; /* Font Awesome icon code for chevron-right */
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        left: 0;
        top: 2px;
        color: #1a428a; /* primary color */
    }
</style>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24">

    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md">
        <a href="index.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center pr-6 md:pr-10 tracking-wide"><?= __('title_user_guide') ?></h1>
    </div>

    <div class="p-4 md:p-8 max-w-4xl mx-auto">
        <div class="mb-6 md:mb-8 relative max-w-2xl mx-auto">
            <input type="text" id="searchGuide" onkeyup="filterGuides()" placeholder="<?= __('search_placeholder') ?>" 
                   class="w-full pl-10 md:pl-12 pr-4 py-3 md:py-4 rounded-xl md:rounded-2xl border border-gray-200 shadow-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-blue-100 transition-all text-sm md:text-base">
            <i class="fas fa-search absolute left-4 md:left-5 top-3.5 md:top-4 text-gray-400 md:text-lg"></i>
        </div>

        <div class="space-y-3 md:space-y-4" id="guideContainer">
            <div id="noResultsMessage" class="hidden text-center text-gray-500 py-8 md:py-12 animate__animated animate__fadeIn">
                <i class="fas fa-search-minus text-4xl md:text-5xl text-gray-300 mb-3 block"></i>
                <span class="md:text-lg"><?= __('no_results') ?></span>
            </div>

            <?php foreach ($guides as $index => $guide): ?>
                <div class="accordion-item bg-white rounded-xl md:rounded-2xl shadow-sm hover:shadow-md border border-gray-100 overflow-hidden animate__animated animate__fadeInUp transition-shadow duration-300" style="animation-delay: <?= ($index + 1) * 0.05 ?>s;">
                    <button class="accordion-header w-full text-left p-4 md:p-5 flex justify-between items-center font-bold text-gray-800 hover:text-primary hover:bg-blue-50/50 focus:outline-none transition-colors">
                        <span class="flex items-center text-sm md:text-base"><i class="<?= htmlspecialchars($guide['icon_class']) ?> mr-3 text-lg md:text-xl text-primary opacity-80"></i> <?= __("guide_{$guide['guide_key']}_title") ?></span>
                        <i class="fas fa-chevron-down text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div class="accordion-content">
                        <div class="p-4 md:p-6 border-t border-gray-100 text-sm md:text-base text-gray-600 leading-relaxed bg-gray-50/50">
                            <?= __("guide_{$guide['guide_key']}_content") ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
        
        <div class="mt-8 md:mt-12 bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 rounded-2xl md:rounded-3xl p-6 md:p-10 text-center shadow-sm mb-4">
            <div class="w-14 h-14 md:w-16 md:h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-md text-primary hover:scale-110 transition-transform duration-300">
                <i class="fas fa-headset text-2xl md:text-3xl"></i>
            </div>
            <h3 class="font-bold text-gray-800 text-base md:text-xl mb-2"><?= __('guide_contact_title') ?></h3>
            <p class="text-sm md:text-base text-gray-500 mb-6 max-w-lg mx-auto"><?= __('guide_contact_desc') ?></p>
            <a href="support.php" class="inline-block bg-primary hover:bg-blue-800 hover:-translate-y-0.5 text-white font-bold py-2.5 md:py-3 px-8 md:px-10 rounded-xl text-sm md:text-base transition-all duration-300 shadow-md hover:shadow-lg">
                <?= __('contact_admin') ?>
            </a>
        </div>
    </div>

    <script>
        function vibrateOnClick(duration = 20) {
            if (navigator.vibrate) {
                navigator.vibrate(duration);
            }
        }

        document.querySelectorAll('.accordion-header').forEach(header => {
            header.addEventListener('click', () => {
                vibrateOnClick();
                const content = header.nextElementSibling;
                const icon = header.querySelector('i.fa-chevron-down');

                // Check if the current accordion is already open
                const isAlreadyOpen = content.style.maxHeight;

                // Close all accordions
                document.querySelectorAll('.accordion-content').forEach(c => {
                    c.style.maxHeight = null;
                    c.previousElementSibling.querySelector('i.fa-chevron-down').classList.remove('rotate-180');
                });

                // If it wasn't open, open it
                if (!isAlreadyOpen) {
                    content.style.maxHeight = content.scrollHeight + "px";
                    icon.classList.add('rotate-180');
                }
            });
        });

        // Search Filter Function
        function filterGuides() {
            let input = document.getElementById('searchGuide');
            let filter = input.value.toLowerCase();
            let items = document.querySelectorAll('#guideContainer .accordion-item');
            let hasVisibleItems = false;

            items.forEach(item => {
                let textContent = item.textContent || item.innerText;
                if (textContent.toLowerCase().indexOf(filter) > -1) {
                    item.style.display = "";
                    hasVisibleItems = true;
                } else {
                    item.style.display = "none";
                }
            });
            
            let noResultsMessage = document.getElementById('noResultsMessage');
            if (!hasVisibleItems && filter !== "") {
                noResultsMessage.classList.remove('hidden');
            } else {
                noResultsMessage.classList.add('hidden');
            }
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>