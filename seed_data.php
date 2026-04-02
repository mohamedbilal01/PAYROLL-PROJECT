<?php
include 'c:/xampp/htdocs/payroll-system/db.php';
$id = 2;

$y = date('Y');
$m = date('m');
$last_m = $m == 1 ? 12 : $m - 1;
$last_y = $m == 1 ? $y - 1 : $y;
$last_month_name = date('F', mktime(0, 0, 0, $last_m, 10));
$today = date('Y-m-d');

// 1. Attendance for today and yesterday
$conn->query("INSERT IGNORE INTO attendance (employee_id, date, status, clock_in, clock_out) VALUES ($id, '$today', 'Present', '09:00:00', '17:00:00')");
$yesterday = date('Y-m-d', strtotime('-1 days'));
$conn->query("INSERT IGNORE INTO attendance (employee_id, date, status, clock_in, clock_out) VALUES ($id, '$yesterday', 'Present', '09:05:00', '17:10:00')");

// 2. Leave Request
$leave_start = date("Y-$last_m-15");
$leave_end = date("Y-$last_m-16");
$conn->query("INSERT IGNORE INTO leave_requests (employee_id, leave_type, reason, start_date, end_date, status) VALUES ($id, 'Sick Leave', 'Fever and cold', '$leave_start', '$leave_end', 'Approved')");

// 3. Payroll history
$conn->query("INSERT IGNORE INTO payroll (employee_id, month, year, basic_salary, deductions, bonus, net_pay) VALUES ($id, '$last_month_name', '$last_y', 120000, 0, 5000, 125000)");

echo "Data restored carefully for Abishaik.\n";
?>
