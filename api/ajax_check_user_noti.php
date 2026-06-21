<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$latest_id = 0;
$latest_msg = "";

// User အတွက် သီးသန့်ဖြစ်စေ၊ အများပြည်သူ (NULL) ဖြစ်စေ နောက်ဆုံး Noti ကို ဆွဲထုတ်မည်
$stmt = $conn->prepare("SELECT id, message FROM system_notifications WHERE user_id = ? OR user_id IS NULL ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res) {
    $latest_id = intval($res['id']);
    // HTML tags များကို ဖယ်ရှားပြီး စာသားသီးသန့် (Plain Text) ကိုသာ ယူမည်
    $latest_msg = strip_tags($res['message']); 
}
$stmt->close();

// User ၏ လက်ရှိ မဖတ်ရသေးသော Noti အရေအတွက်ကိုပါ ဆွဲထုတ်မည်
$count_stmt = $conn->prepare("SELECT notifications FROM users WHERE id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$unread_count = $count_stmt->get_result()->fetch_assoc()['notifications'] ?? 0;
$count_stmt->close();

echo json_encode(['success' => true, 'latest_id' => $latest_id, 'latest_msg' => $latest_msg, 'unread_count' => $unread_count, 'is_important' => $is_important]);
exit();
?>