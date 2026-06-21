<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';
require_once __DIR__ . '/../lang/language.php';

// Admin (User ID 1) သာ ဝင်ခွင့်ပြုမည်
require_main_admin();

// Fetch guides dynamically from the database
$guides_stmt = $conn->query("SELECT guide_key, icon_class FROM guides WHERE is_active = 1 ORDER BY sort_order ASC");
$guides = [];
if ($guides_stmt) {
    $guides = $guides_stmt->fetch_all(MYSQLI_ASSOC);
}

$page_title = __('admin_guides') . " - Admin";
require_once __DIR__ . '/../includes/header.php'; 
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
        padding-left: 1.5rem;
    }
    .guide-list li::before {
        content: '\f101';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        left: 0;
        top: 2px;
        color: #1a428a;
    }
</style>

<body class="max-w-4xl mx-auto min-h-screen bg-gray-100 shadow-xl pb-10">

    <?php
    $header_title = __('admin_guides');
    $header_icon = "fas fa-book-reader";
    require_once __DIR__ . '/admin_header.php';
    ?>

    <div class="p-4 md:p-6 pt-0">
        <div class="space-y-3">
            <?php foreach ($guides as $guide): ?>
                <div class="accordion-item bg-white rounded-xl shadow-sm overflow-hidden">
                    <button class="accordion-header w-full text-left p-4 flex justify-between items-center font-bold text-gray-800 hover:bg-gray-50 focus:outline-none">
                        <span class="flex items-center"><i class="<?= htmlspecialchars($guide['icon_class']) ?> mr-3 text-lg"></i> <?= __("guide_{$guide['guide_key']}_title") ?></span>
                        <i class="fas fa-chevron-down transition-transform"></i>
                    </button>
                    <div class="accordion-content">
                        <div class="p-4 border-t border-gray-200 text-sm text-gray-700 leading-relaxed">
                            <?= __("guide_{$guide['guide_key']}_content") ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        document.querySelectorAll('.accordion-header').forEach(header => {
            header.addEventListener('click', () => {
                const content = header.nextElementSibling;
                const icon = header.querySelector('i.fa-chevron-down');
                const isAlreadyOpen = content.style.maxHeight;
                document.querySelectorAll('.accordion-content').forEach(c => {
                    c.style.maxHeight = null;
                    c.previousElementSibling.querySelector('i.fa-chevron-down').classList.remove('rotate-180');
                });
                if (!isAlreadyOpen) {
                    content.style.maxHeight = content.scrollHeight + "px";
                    icon.classList.add('rotate-180');
                }
            });
        });
    </script>

</body></html>