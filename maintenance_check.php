<?php
// database connection and site settings fetching logic here
$is_maintenance = $db->query("SELECT value FROM settings WHERE key_name = 'maintenance_mode'")->fetch_column();

if ($is_maintenance == 1) {
    // အကယ်၍ admin session မရှိရင် maintenance page ပြမယ်
    if (!isset($_SESSION['admin_id'])) {
        header('Content-Type: text/html; charset=utf-8');
        echo "<h1>Website ခေတ္တပြုပြင်နေပါသည်။</h1>";
        echo "<p>မကြာမီ ပြန်လည်ဖွင့်လှစ်ပေးပါမည်။ ကျေးဇူးတင်ပါသည်။</p>";
        exit();
    }
}
?>