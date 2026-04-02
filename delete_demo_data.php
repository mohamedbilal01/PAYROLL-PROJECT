<?php
include 'c:/xampp/htdocs/payroll-system/db.php';
$conn->query("DELETE FROM attendance WHERE employee_id > 19");
$conn->query("DELETE FROM leave_requests WHERE employee_id > 19");
$conn->query("DELETE FROM payroll WHERE employee_id > 19");
$res = $conn->query("DELETE FROM employees WHERE id > 19");
if ($res) {
    echo "Successfully deleted all demo data generated after Harvey Specter (ID > 19).\n";
} else {
    echo "Error deleting: " . $conn->error . "\n";
}
?>
