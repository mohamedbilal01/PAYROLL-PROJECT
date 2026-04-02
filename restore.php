<?php
include 'c:/xampp/htdocs/payroll-system/db.php';
$conn->query("INSERT INTO employees (id, name, username, email, role, job_title, base_salary, password) VALUES (2, 'abishaik', 'abishaik', 'md123@gmail.com', 'hr', 'HR', 120000, 'abishaik123') ON DUPLICATE KEY UPDATE name='abishaik'");
echo "Restored abishaik\n";
?>
