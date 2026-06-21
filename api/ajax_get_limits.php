<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$type = $_GET['type'] ?? '2d';
$len = ($type == '3d') ? 3 : 2;

// အသက်ဝင်နေသော Session အချက်အလက်ကို ယူမည်
$session_query = $conn->prepare("SELECT section, target_date FROM betting_sessions WHERE game_type = ? AND status = 'active' AND NOW() BETWEEN open_time AND close_time ORDER BY close_time ASC LIMIT 1");
$session_query->bind_param("s", $type);
$session_query->execute();
$active_session = $session_query->get_result()->fetch_assoc();
$session_query->close();

if (!$active_session) {
    echo json_encode(['error' => 'No active session', 'totals' => []]);
    exit();
}

// Limit ပမာဏကို ဆွဲထုတ်ခြင်း
$setting_key = ($type == '3d') ? 'max_limit_per_3d_number' : 'max_limit_per_number';
$limit_res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$setting_key'");
$max_limit = floatval($limit_res->fetch_assoc()['setting_value'] ?? 20000);

// ဂဏန်းအလိုက် စုစုပေါင်း Pending ထိုးကြေးများကို ယူမည်
$totals = [];
$stmt = $conn->prepare("SELECT bet_number, SUM(amount) as total FROM bets WHERE status = 'pending' AND LENGTH(bet_number) = ? AND target_date = ? AND bet_section = ? GROUP BY bet_number");
$stmt->bind_param("iss", $len, $active_session['target_date'], $active_session['section']);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $totals[$row['bet_number']] = floatval($row['total']);
}
$stmt->close();

echo json_encode(['max_limit' => $max_limit, 'totals' => $totals]);