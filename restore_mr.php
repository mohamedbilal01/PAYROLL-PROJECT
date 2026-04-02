<?php
include 'c:/xampp/htdocs/payroll-system/db.php';
$conn->query("INSERT IGNORE INTO employees (id, name, email, role, job_title, base_salary, username, password) VALUES (18, 'Mike Ross', 'mike.ross@nexushr.com', 'employee', 'Junior Associate', 45000, 'mikeross', 'mike123')");
echo "Restored Mike Ross.\n";
?>
