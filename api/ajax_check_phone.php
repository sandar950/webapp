<?php
require_once __DIR__ . '/../core/db_connect.php';

header('Content-Type: application/json');

$phone = trim($_POST['phone'] ?? '');
$response = ['exists' => false];

if (!empty($phone)) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone_number = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $response['exists'] = true;
    }
    $stmt->close();
}

echo json_encode($response);
exit();
?>