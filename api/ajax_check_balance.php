<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$amount_needed = floatval($_POST['amount'] ?? 0);

$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user && $user['balance'] >= $amount_needed) {
    echo json_encode(['success' => true]);
} else {
    $balance = $user ? $user['balance'] : 0;
    echo json_encode(['success' => false, 'message' => 'သင့်လက်ကျန်ငွေမှာ ' . number_format($balance) . ' Ks သာ ရှိသဖြင့် မလုံလောက်ပါ။']);
}
exit();