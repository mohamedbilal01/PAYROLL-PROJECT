<?php
include 'c:/xampp/htdocs/payroll-system/db.php';
$res = $conn->query('SELECT id, name, role FROM employees');
while($row = $res->fetch_assoc()) { print_r($row); echo "\n"; }
?>
