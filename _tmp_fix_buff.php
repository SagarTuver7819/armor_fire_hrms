<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/department_helper.php';

$conn = getDBConnection();
ensureCanonicalDepartments($conn);
$n = syncDepartmentIcons($conn);

$res = $conn->query("SELECT id, department_name, icon_class FROM departments WHERE UPPER(department_name) LIKE '%BUFF%'");
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['department_name'] . ' | ' . $row['icon_class'] . PHP_EOL;
}
echo 'synced=' . $n . PHP_EOL;
$conn->close();
