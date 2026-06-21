<?php
session_start();
require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/lang/language.php';

// Login ဝင်ထားခြင်း မရှိပါက ပြန်ပို့ရန်
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$date = $_GET['date'] ?? '';

$filename = "my_bet_history";
$filename .= !empty($date) ? "_" . $date : "_all";
$filename .= ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// Excel တွင် မြန်မာစာ မှန်ကန်စွာပေါ်စေရန် UTF-8 BOM ထည့်သွင်းခြင်း
fputs($output, "\xEF\xBB\xBF");

if (!empty($date)) {
    fputcsv($output, [__('export_hist_date_label'), date('d-M-Y', strtotime($date))]);
    fputcsv($output, []);
}

fputcsv($output, [__('export_hist_col_datetime'), __('export_hist_col_bet_number'), __('export_hist_col_bet_amount'), __('export_hist_col_discount'), __('export_hist_col_status')]);

$query = "SELECT created_at, bet_number, amount, discount_amount, status FROM bets WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($date)) {
    $query .= " AND DATE(created_at) = ?";
    $params[] = $date;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$total_bet = 0;
$total_win = 0;

while ($row = $result->fetch_assoc()) {
    $status_mm = __('admin_export_status_pending');
    $payout = 0;
    
    $net_amount = $row['amount'] - ($row['discount_amount'] ?? 0);
    $total_bet += $net_amount;

    if ($row['status'] == 'win') {
        $status_mm = __('admin_export_status_win');
        $multiplier = (strlen($row['bet_number']) == 3) ? 500 : 80;
        $payout = $row['amount'] * $multiplier;
        $total_win += $payout;
    } elseif ($row['status'] == 'lose') {
        $status_mm = __('admin_export_status_lose');
    }
    
    fputcsv($output, [
        date('d-M-Y h:i A', strtotime($row['created_at'])),
        $row['bet_number'],
        $row['amount'],
        $row['discount_amount'] ?? 0,
        $status_mm
    ]);
}

fputcsv($output, []); // အောက်ခြေတွင် တစ်ကြောင်းခြားရန်
fputcsv($output, [__('export_hist_total_bet'), $total_bet]);
fputcsv($output, [__('export_hist_total_win'), $total_win]);
fputcsv($output, [__('export_hist_net_amount'), $total_win - $total_bet]);

fclose($output);
exit();
?>