<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';

header('Content-Type: application/json');

// Admin သို့မဟုတ် Sub-Admin သာ စစ်ဆေးခွင့်ရှိသည်
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'sub_admin'])) {
    echo json_encode(['total' => 0, 'deposits' => 0, 'withdrawals' => 0, 'messages' => 0, 'verifications' => 0]);
    exit();
}

$dep_res = $conn->query("SELECT COUNT(id) as c FROM deposits WHERE status = 'pending'");
$with_res = $conn->query("SELECT COUNT(id) as c FROM withdrawals WHERE status = 'pending'");
$msg_res = $conn->query("SELECT COUNT(id) as c FROM support_messages WHERE status = 'pending'");
$ver_res = $conn->query("SELECT COUNT(id) as c FROM users WHERE verification_status = 'pending'");

$dep_count = $dep_res ? $dep_res->fetch_assoc()['c'] : 0;
$with_count = $with_res ? $with_res->fetch_assoc()['c'] : 0;
$msg_count = $msg_res ? $msg_res->fetch_assoc()['c'] : 0;
$ver_count = $ver_res ? $ver_res->fetch_assoc()['c'] : 0;
$total_count = $dep_count + $with_count;

echo json_encode(['total' => $total_count, 'deposits' => $dep_count, 'withdrawals' => $with_count, 'messages' => $msg_count, 'verifications' => $ver_count]);
?>