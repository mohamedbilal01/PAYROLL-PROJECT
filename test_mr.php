<?php
include 'c:/xampp/htdocs/payroll-system/db.php';
$del_id = 18; // Mike Ross
$check = $conn->query("SELECT role FROM employees WHERE id='$del_id'");
if ($check && $check->num_rows > 0) {
    $emp = $check->fetch_assoc();
    if (strtolower(trim($emp['role'])) != 'admin') {
        if (!$conn->query("DELETE FROM attendance WHERE employee_id='$del_id'")) echo "Fail attendance: " . $conn->error . "\n";
        if (!$conn->query("DELETE FROM leave_requests WHERE employee_id='$del_id'")) echo "Fail leaves: " . $conn->error . "\n";
        if (!$conn->query("DELETE FROM payroll WHERE employee_id='$del_id'")) echo "Fail payroll: " . $conn->error . "\n";
        if (!$conn->query("DELETE FROM employees WHERE id='$del_id'")) {
            echo "Fail employees: " . $conn->error . "\n";
        } else {
            echo "Successfully deleted Mike Ross.\n";
        }
    } else {
        echo "Is admin.\n";
    }
} else {
    echo "Not found.\n";
}
?>
