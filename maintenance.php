<?php
require_once __DIR__ . '/core/db_connect.php';

// Maintenance mode စစ်ဆေးခြင်း
$m_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
$is_maintenance = ($m_stmt && $m_row = $m_stmt->fetch_assoc() && $m_row['setting_value'] === '1');

if (!$is_maintenance) {
    // Maintenance mode ပိတ်ထားပါက index သို့ ပြန်ပို့မည်
    header("Location: index.php");
    exit();
}

// ကြေညာမည့်စာသား ရယူခြင်း
$msg_stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_message'");
$maintenance_message = ($msg_stmt && $msg_row = $msg_stmt->fetch_assoc()) ? $msg_row['setting_value'] : "ဆာဗာပြုပြင်ထိန်းသိမ်းမှုများ ပြုလုပ်နေပါသည်။ ခေတ္တစောင့်ဆိုင်းပေးပါ။";
?>
<!DOCTYPE html>
<html lang="my">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Thai 2D3D</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Padauk', sans-serif; background-color: #f3f4f6; }
        .bg-primary { background-color: #1a428a; }
        .text-primary { color: #1a428a; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-red-500"></div>
        
        <div class="w-24 h-24 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6 border-4 border-red-100">
            <i class="fas fa-tools text-4xl text-red-500 animate-pulse"></i>
        </div>
        
        <h1 class="text-2xl font-bold text-gray-800 mb-3">Under Maintenance</h1>
        <h2 class="text-lg font-bold text-red-600 mb-4">ပြုပြင်ထိန်းသိမ်းနေပါသည်</h2>
        
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-sm text-gray-700 leading-relaxed mb-8 shadow-inner">
            <?= nl2br(htmlspecialchars($maintenance_message)) ?>
        </div>
        
        <button onclick="location.reload()" class="bg-primary hover:bg-blue-800 text-white font-bold py-3 px-8 rounded-lg shadow-sm transition"><i class="fas fa-sync-alt mr-2"></i> ပြန်လည်စစ်ဆေးမည်</button>
    </div>

</body>
</html>