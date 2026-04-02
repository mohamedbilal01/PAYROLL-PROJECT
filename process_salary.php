<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

// Ensure only Admin or HR can access
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { 
    header("Location: index.php"); exit(); 
}
include 'db.php';

$msg = "";
$msg_type = "";

// --- THE SMART PAYROLL MATH ENGINE ---
if (isset($_POST['generate_payroll'])) {
    $post_emp_id = $_POST['employee_id'];
    $payroll_month = $_POST['payroll_month']; 
    $bonus = (float)$_POST['bonus'];

    list($y, $m) = explode('-', $payroll_month);
    $month_name = date('F', mktime(0, 0, 0, $m, 10));

    $emp_list = [];
    if ($post_emp_id === 'all') {
        $emps = $conn->query("SELECT id FROM employees WHERE role != 'admin'");
        while($e = $emps->fetch_assoc()) {
            $emp_list[] = $e['id'];
        }
    } else {
        $emp_list[] = (int)$post_emp_id;
    }

    $success_count = 0;
    $error_count = 0;
    $already_count = 0;
    $last_success_msg = "";

    foreach($emp_list as $emp_id) {
        // 1. Get Employee Base Salary
        $emp_q = $conn->query("SELECT name, base_salary FROM employees WHERE id='$emp_id'");
        if(!$emp_q || $emp_q->num_rows == 0) continue;
        $emp_data = $emp_q->fetch_assoc();
        $base_salary = (float)$emp_data['base_salary'];

        // Check if payroll was already run
        $check = $conn->query("SELECT id FROM payroll WHERE employee_id='$emp_id' AND month='$month_name' AND year='$y'");
        
        if ($check->num_rows > 0) {
            $already_count++;
        } else {
            // 2. Fetch all their Attendance WITH TIMES
            $attendance_data = [];
            $att_q = $conn->query("SELECT date, clock_in, clock_out FROM attendance WHERE employee_id='$emp_id' AND YEAR(date)='$y' AND MONTH(date)='$m' AND status='Present'");
            while($row = $att_q->fetch_assoc()) { 
                $attendance_data[$row['date']] = [
                    'in' => $row['clock_in'], 
                    'out' => $row['clock_out']
                ]; 
            }

            // 3. Fetch all their Approved Leave ranges
            $leaves = [];
            $leave_q = $conn->query("SELECT start_date, end_date FROM leave_requests WHERE employee_id='$emp_id' AND status='Approved'");
            while($row = $leave_q->fetch_assoc()) {
                $leaves[] = ['start' => $row['start_date'], 'end' => $row['end_date']];
            }

            // 4. Loop through the month day-by-day
            $working_days = 0;
            $paid_days = 0.0; // Changed to decimal for Half-Pay
            
            $start_date = new DateTime("$y-$m-01");
            $end_date = new DateTime("$y-$m-" . $start_date->format('t'));
            $current_date = clone $start_date;
            
            while ($current_date <= $end_date) {
                if ($current_date->format('N') < 6) { // Monday - Friday
                    $working_days++;
                    $date_str = $current_date->format('Y-m-d');
                    
                    // HOURLY LOGIC CHECK
                    if (array_key_exists($date_str, $attendance_data)) {
                        $in = $attendance_data[$date_str]['in'];
                        $out = $attendance_data[$date_str]['out'];
                        
                        if (!empty($in) && !empty($out)) {
                            // Calculate total hours
                            $hours = (strtotime($out) - strtotime($in)) / 3600;
                            
                            if ($hours >= 6) {
                                $paid_days += 1.0;   // Full Pay
                            } elseif ($hours >= 4) {
                                $paid_days += 0.5;   // Half Pay (4 to 5.99 hours)
                            } else {
                                $paid_days += 0.0;   // No Pay (Less than 4 hours)
                            }
                        } else {
                            // If they forgot to clock out, they get 0 pay for the day until HR fixes it
                            $paid_days += 0.0; 
                        }
                    } else {
                        // Were they on an APPROVED LEAVE?
                        $on_leave = false;
                        foreach ($leaves as $l) {
                            if ($date_str >= $l['start'] && $date_str <= $l['end']) {
                                $on_leave = true; break;
                            }
                        }
                        if ($on_leave) { $paid_days += 1.0; } // Leaves count as a full paid day
                    }
                }
                $current_date->modify('+1 day');
            }

            // 5. Final Money Calculation
            if ($working_days > 0) {
                $per_day_pay = $base_salary / $working_days;
                $earned_salary = $per_day_pay * $paid_days;
                
                $deductions = $base_salary - $earned_salary;
                $net_pay = $earned_salary + $bonus;

                // Save to Database
                $ins = "INSERT INTO payroll (employee_id, month, year, basic_salary, deductions, bonus, net_pay) 
                        VALUES ('$emp_id', '$month_name', '$y', '$base_salary', '$deductions', '$bonus', '$net_pay')";
                
                if ($conn->query($ins)) {
                    $success_count++;
                    $last_success_msg = $emp_data['name']." earned $paid_days out of $working_days days. Net Pay: ₹" . number_format($net_pay, 2);
                } else {
                    $error_count++;
                }
            } else {
                $error_count++;
            }
        }
    }

    if ($post_emp_id === 'all') {
        if ($success_count > 0) {
            $msg = "Bulk Payroll Success: Processed exactly $success_count employees.";
            if ($already_count > 0) $msg .= " ($already_count were already paid).";
            if ($error_count > 0) $msg .= " ($error_count errors).";
            $msg_type = "success";
        } elseif ($already_count > 0) {
            $msg = "No new payroll generated. $already_count employees were already paid for $month_name $y.";
            $msg_type = "warning";
        } else {
            $msg = "No eligible working days found or database error occurred.";
            $msg_type = "error";
        }
    } else {
        if ($already_count > 0) {
            $msg = "Error: Payroll already generated for this employee for $month_name $y.";
            $msg_type = "error";
        } elseif ($success_count > 0) {
            $msg = "Success! " . $last_success_msg;
            $msg_type = "success";
        } else {
            $msg = "Error: Failed to process payroll (perhaps zero working days in month).";
            $msg_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Run Payroll | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .sidebar { position: fixed !important; left: 0; top: 0; width: 260px !important; height: 100vh; z-index: 1000; }
        .main-content { margin-left: 260px !important; padding: 40px; width: calc(100% - 260px) !important; box-sizing: border-box; min-height: 100vh; }

        .payroll-banner { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 16px; padding: 35px 40px; color: white; margin-bottom: 30px; position: relative; overflow: hidden; display: flex; align-items: center; gap: 20px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3); }
        .payroll-banner::after { content: ''; position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; background: rgba(67, 97, 238, 0.15); border-radius: 50%; filter: blur(40px); }
        .banner-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 28px; color: #60a5fa; border: 1px solid rgba(255,255,255,0.1); }
        
        .grid-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .form-card { background: white; padding: 40px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .form-group { margin-bottom: 25px; }
        .form-label { display: block; font-size: 12px; font-weight: 800; color: #64748b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .form-input { width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 15px; font-weight: 500; color: #334155; box-sizing: border-box; transition: 0.2s; background: #f8fafc; }
        .form-input:focus { border-color: #4361ee; background: white; outline: none; box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1); }
        
        .btn-calc { width: 100%; padding: 16px; background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%); color: white; border: none; border-radius: 10px; font-weight: 700; font-size: 16px; cursor: pointer; margin-top: 15px; display: flex; justify-content: center; align-items: center; gap: 10px; transition: 0.3s; box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3); }
        .btn-calc:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4); }

        .info-card { background: linear-gradient(to bottom, #ffffff, #f8fafc); padding: 35px; border-radius: 16px; border: 1px dashed #cbd5e1; height: fit-content; }
        .info-card h3 { margin: 0 0 20px 0; color: #0f172a; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .checklist-item { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 15px; color: #475569; font-size: 14px; line-height: 1.5; }
        .checklist-item i { color: #10b981; font-size: 16px; margin-top: 2px; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <div class="payroll-banner">
            <div class="banner-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div style="position: relative; z-index: 10;">
                <h1 style="margin: 0 0 5px 0; font-size: 26px; font-weight: 800;">Smart Payroll Processor</h1>
                <p style="margin: 0; color: #94a3b8; font-size: 15px;">Generate automated salary slips based on attendance and leave records.</p>
            </div>
        </div>

        <?php if($msg): ?>
            <div style="padding:20px; border-radius:12px; margin-bottom:30px; font-weight:600; font-size: 15px; display: flex; align-items: center; gap: 10px; <?php echo ($msg_type == 'success') ? 'background:#ecfdf5; color:#065f46; border: 1px solid #a7f3d0;' : 'background:#fef2f2; color:#991b1b; border: 1px solid #fecaca;'; ?>">
                <i class="fas <?php echo ($msg_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>" style="font-size: 20px;"></i>
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="grid-layout">
            
            <div class="form-card">
                <form action="process_salary.php" method="POST">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Select Employee</label>
                            <select name="employee_id" class="form-input" required>
                                <option value="" disabled selected>-- Choose Staff Member --</option>
                                <option value="all" style="font-weight: 800; color: #4361ee;">** All Employees (Bulk Processing) **</option>
                                <?php
                                $emps = $conn->query("SELECT id, name, job_title FROM employees WHERE role != 'admin' ORDER BY name ASC");
                                while($e = $emps->fetch_assoc()) {
                                    echo "<option value='".$e['id']."'>".$e['name']." (".$e['job_title'].")</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payroll Month</label>
                            <input type="month" name="payroll_month" class="form-input" value="<?php echo date('Y-m'); ?>" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label class="form-label">Additional Bonus (₹)</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 16px; top: 14px; color: #94a3b8; font-weight: 600;">₹</span>
                            <input type="number" name="bonus" value="0" min="0" class="form-input" style="padding-left: 35px;">
                        </div>
                    </div>

                    <button type="submit" name="generate_payroll" class="btn-calc">
                        <i class="fas fa-calculator"></i> Calculate & Generate Salary
                    </button>
                </form>
            </div>

            <div class="info-card">
                <h3><i class="fas fa-clipboard-check" style="color:#4361ee;"></i> Pre-Payroll Checklist</h3>
                <div class="checklist-item"><i class="fas fa-check-circle"></i><div><strong>Verify Attendance</strong><br>Ensure all clock-ins and clock-outs are logged.</div></div>
                <div class="checklist-item"><i class="fas fa-check-circle"></i><div><strong>Clear Approval Queue</strong><br>Approve or reject any pending leave requests first.</div></div>
                <div class="checklist-item"><i class="fas fa-check-circle"></i><div><strong>Automated Deductions</strong><br>Less than 4 hours = 0 Pay.<br>4 to 5.99 hours = Half Pay.<br>6+ hours = Full Pay.</div></div>
            </div>

        </div>
    </div>
</div>

</body>
</html>