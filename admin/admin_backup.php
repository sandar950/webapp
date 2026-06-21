<?php
session_start();
require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/auth_helper.php';

// Main Admin သာ ဝင်ခွင့်ပြုမည်
require_main_admin();

// Database ကြီးမားပါက Time Out မဖြစ်စေရန် သတ်မှတ်ခြင်း
set_time_limit(0);

// Backup ယူကြောင်း မှတ်တမ်းတင်မည်
log_activity($_SESSION['user_id'], 'DATABASE_BACKUP', 'Downloaded database backup.');

$filename = "thai2d3d_backup_" . date("Y-m-d_H-i-s") . ".sql";

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Output ကို Variable ထဲမသိမ်းဘဲ တိုက်ရိုက် Stream လုပ်မည် (Memory Limit မပြည့်စေရန်)
$output = fopen('php://output', 'w');

fwrite($output, "-- Thai 2D3D Database Backup\n");
fwrite($output, "-- Generated: " . date("Y-m-d H:i:s") . "\n\n");
fwrite($output, "SET NAMES utf8mb4;\n");
fwrite($output, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    $result = $conn->query("SELECT * FROM `$table`");
    $num_fields = $result->field_count;

    fwrite($output, "DROP TABLE IF EXISTS `$table`;\n");
    $row2 = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
    fwrite($output, $row2[1] . ";\n\n");

    while ($row = $result->fetch_row()) {
        $insert_query = "INSERT INTO `$table` VALUES(";
        for ($j = 0; $j < $num_fields; $j++) {
            if (isset($row[$j])) {
                $escaped = $conn->real_escape_string($row[$j]);
                $insert_query .= "'" . $escaped . "'";
            } else {
                $insert_query .= 'NULL';
            }
            if ($j < ($num_fields - 1)) { $insert_query .= ','; }
        }
        $insert_query .= ");\n";
        fwrite($output, $insert_query);
    }
    fwrite($output, "\n\n");
}

fwrite($output, "SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($output);
exit();
?>