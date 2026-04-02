<?php
include 'c:/xampp/htdocs/payroll-system/db.php';
$conn->query("INSERT IGNORE INTO employees (id, name, email, role, job_title, base_salary, username, password) VALUES (19, 'Harvey Specter', 'harvey.specter@nexushr.com', 'hr', 'HR Manager', 90000, 'harvey', 'harvey123')");
?>
