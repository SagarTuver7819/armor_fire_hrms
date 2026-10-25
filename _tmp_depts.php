<?php
require_once __DIR__ . '/config/database.php';
$conn = getDBConnection();
echo "=== departments ===\n";
$res = $conn->query("SHOW COLUMNS FROM departments");
while ($r = $res->fetch_assoc()) echo $r['Field'], "\n";
$res = $conn->query("SELECT id, department_name, status FROM departments ORDER BY sort_order, department_name");
while ($r = $res->fetch_assoc()) echo json_encode($r), "\n";
echo "=== sub_departments sample ===\n";
$res = $conn->query("SELECT s.id, s.department_id, d.department_name, s.name FROM sub_departments s LEFT JOIN departments d ON d.id=s.department_id WHERE s.status=1 ORDER BY d.department_name, s.name LIMIT 80");
while ($r = $res->fetch_assoc()) echo json_encode($r), "\n";
$conn->close();
