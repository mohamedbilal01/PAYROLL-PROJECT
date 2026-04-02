<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

// Only allow Admin or HR
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { 
    header("Location: index.php"); exit(); 
}
include 'db.php';

// Get filter values from dropdowns (Default to current year and no employee)
$view_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$view_month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$selected_emp = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

// Fetch employee list for the dropdown
$emp_list_query = $conn->query("SELECT id, name, job_title FROM employees WHERE role != 'admin' ORDER BY name ASC");

// If no employee is selected yet but we have employees, default to the first one
if ($selected_emp == 0 && $emp_list_query && $emp_list_query->num_rows > 0) {
    $first_emp = $emp_list_query->fetch_assoc();
    $selected_emp = $first_emp['id'];
    $emp_list_query->data_seek(0); // Reset the pointer for the dropdown loop
}

$monthly_data = [];
$total_present_all = 0;
$total_working_all = 0;

if ($selected_emp > 0) {
    // Calculate for selected month or all 12 months
    $start_m = ($view_month > 0) ? $view_month : 1;
    $end_m = ($view_month > 0) ? $view_month : 12;
    for ($m = $start_m; $m <= $end_m; $m++) { 
        $month_short = date('M', mktime(0, 0, 0, $m, 10));

        // Get Present Days
        $q_present = $conn->query("SELECT COUNT(id) as c FROM attendance WHERE employee_id='$selected_emp' AND YEAR(date)='$view_year' AND MONTH(date)='$m' AND status='Present'");
        $present_days = $q_present->fetch_assoc()['c'] ?? 0;

        // Calculate Working Days (Mon-Fri)
        $working_days = 0;
        $start_date = new DateTime("$view_year-$m-01");
        $end_date = new DateTime("$view_year-$m-" . $start_date->format('t'));
        $today_obj = new DateTime();
        
        // Stop calculating future working days if we are looking at the current year
        if ($start_date > $today_obj && $view_year == date('Y')) {
            $working_days = 0; 
        } else {
            if ($start_date->format('Y-m') == $today_obj->format('Y-m')) {
                $end_date = clone $today_obj; // Cap to today for current month
            }
            while ($start_date <= $end_date) {
                if ($start_date->format('N') < 6) { $working_days++; } // 1-5 are weekdays
                $start_date->modify('+1 day');
            }
        }

        // Only display months that have actual working days
        if ($working_days > 0) {
            $monthly_data[] = [
                'year' => $view_year, 'month' => $month_short,
                'present' => $present_days, 'total' => $working_days
            ];
            $total_present_all += $present_days;
            $total_working_all += $working_days;
        }
    }
}

$overall_percentage = ($total_working_all > 0) ? round(($total_present_all / $total_working_all) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Staff History | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css"> 
    
    <style>
        body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .sidebar { position: fixed !important; left: 0; top: 0; width: 260px !important; height: 100vh; z-index: 1000; }
        .main-content { margin-left: 260px !important; padding: 40px; width: calc(100% - 260px) !important; box-sizing: border-box; min-height: 100vh; }
        
        /* Premium Banner */
        .premium-banner { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 16px; padding: 35px 40px; color: white; margin-bottom: 30px; position: relative; overflow: hidden; display: flex; align-items: center; gap: 20px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3); }
        .premium-banner::after { content: ''; position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; background: rgba(67, 97, 238, 0.15); border-radius: 50%; filter: blur(40px); }
        .banner-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 28px; color: #60a5fa; border: 1px solid rgba(255,255,255,0.1); }

        .filter-card { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 25px 30px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .select-input { padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a; outline: none; font-family: 'Inter'; cursor: pointer; min-width: 200px; }
        .select-input:focus { border-color: #4361ee; }

        .layout-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; align-items: start; }
        .term-card { background: white; border-radius: 20px; padding: 40px 30px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; flex-direction: column; align-items: center; }
        
        .donut-ring { width: 180px; height: 180px; border-radius: 50%; background: conic-gradient(#4361ee <?php echo $overall_percentage; ?>%, #f1f5f9 0); display: flex; align-items: center; justify-content: center; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .donut-hole { width: 130px; height: 130px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; color: #0f172a; box-shadow: inset 0 2px 5px rgba(0,0,0,0.02); }
        
        .term-label { color: #94a3b8; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .term-dates { color: #0f172a; font-size: 16px; font-weight: 800; margin-top: 5px; }

        .table-card { background: white; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden; }
        .term-table { width: 100%; border-collapse: collapse; }
        .term-table th { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; font-weight: 800; color: #64748b; border-bottom: 2px solid #e2e8f0; text-transform:uppercase; }
        .term-table td { padding: 20px; text-align: center; font-size: 15px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f8fafc; }
        .total-row td { background: #f8fafc; font-weight: 800; border-top: 2px solid #e2e8f0; font-size: 16px; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <div class="premium-banner">
            <div class="banner-icon"><i class="fas fa-chart-pie"></i></div>
            <div style="position: relative; z-index: 10;">
                <h1 style="margin: 0 0 5px 0; font-size: 26px; font-weight: 800;">Staff Attendance History</h1>
                <p style="margin: 0; color: #94a3b8; font-size: 15px;">Review yearly performance metrics and monthly breakdowns for any employee.</p>
            </div>
        </div>

        <form method="GET" class="filter-card">
            <div style="display: flex; gap: 20px; align-items: center;">
                <div>
                    <div style="font-size:11px; font-weight:700; color:#64748b; margin-bottom:5px; text-transform:uppercase;">Select Employee</div>
                    <select name="employee_id" class="select-input" onchange="this.form.submit()">
                        <?php
                        if($emp_list_query && $emp_list_query->num_rows > 0) {
                            while($e = $emp_list_query->fetch_assoc()) {
                                $sel = ($e['id'] == $selected_emp) ? 'selected' : '';
                                echo "<option value='".$e['id']."' $sel>".$e['name']." (".$e['job_title'].")</option>";
                            }
                        } else {
                            echo "<option disabled>No employees found</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <div style="font-size:11px; font-weight:700; color:#64748b; margin-bottom:5px; text-transform:uppercase;">Select Year</div>
                    <select name="year" class="select-input" style="min-width: 120px;" onchange="this.form.submit()">
                        <?php
                        $curr = date('Y');
                        for($y = $curr; $y >= ($curr - 5); $y--) {
                            $sel = ($y == $view_year) ? 'selected' : '';
                            echo "<option value='$y' $sel>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <div style="font-size:11px; font-weight:700; color:#64748b; margin-bottom:5px; text-transform:uppercase;">Select Month</div>
                    <select name="month" class="select-input" style="min-width: 150px;" onchange="this.form.submit()">
                        <option value="0" <?php echo ($view_month == 0) ? 'selected' : ''; ?>>All Months</option>
                        <?php
                        for ($m = 1; $m <= 12; $m++) {
                            $m_name = date("F", mktime(0, 0, 0, $m, 10));
                            $sel = ($view_month == $m) ? 'selected' : '';
                            echo "<option value='$m' $sel>$m_name</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </form>

        <?php if ($selected_emp > 0): ?>
            <div class="layout-grid">
                <div class="term-card">
                    <div class="donut-ring"><div class="donut-hole"><?php echo $overall_percentage; ?>%</div></div>
                    <div class="term-label">Year Overview</div>
                    <div class="term-dates">Jan <?php echo $view_year; ?> - Dec <?php echo $view_year; ?></div>
                </div>

                <div class="table-card">
                    <table class="term-table">
                        <thead><tr><th>SI</th><th>Year</th><th>Month</th><th>Attendance</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php
                            $si = 1;
                            foreach($monthly_data as $row) {
                                echo "<tr>
                                    <td style='color:#64748b;'>$si</td>
                                    <td>".$row['year']."</td>
                                    <td>".$row['month']."</td>
                                    <td style='color:#4361ee;'>".$row['present'].".0</td>
                                    <td>".$row['total'].".0</td>
                                </tr>";
                                $si++;
                            }
                            
                            if (empty($monthly_data)) {
                                echo "<tr><td colspan='5' style='padding:40px; color:#94a3b8;'>No attendance records found for this period.</td></tr>";
                            }
                            ?>
                            <tr class="total-row">
                                <td colspan="3" style="text-align: left; padding-left: 30px;">Total</td>
                                <td style="color:#4361ee;"><?php echo $total_present_all; ?>.0</td>
                                <td><?php echo $total_working_all; ?>.0</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($view_month > 0): ?>
            <div class="table-card" style="margin-top: 30px;">
                <div style="padding: 20px; text-align: center; border-bottom: 2px solid #e2e8f0; font-weight: 800; color: #0f172a; font-size: 16px; background: #f8fafc; text-transform: uppercase;">
                    Daily Attendance (<?php echo date("F Y", mktime(0,0,0,$view_month, 10, $view_year)); ?>)
                </div>
                <table class="term-table">
                    <thead><tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php
                        $daily_q = $conn->query("SELECT * FROM attendance WHERE employee_id='$selected_emp' AND YEAR(date)='$view_year' AND MONTH(date)='$view_month' ORDER BY date ASC");
                        if ($daily_q && $daily_q->num_rows > 0) {
                            while($d = $daily_q->fetch_assoc()) {
                                $s_color = ($d['status'] == 'Present') ? '#16a34a' : (($d['status'] == 'Absent') ? '#dc2626' : '#ea580c');
                                echo "<tr>
                                    <td>".date('d M Y', strtotime($d['date']))."</td>
                                    <td style='color:#16a34a;'>".$d['clock_in']."</td>
                                    <td style='color:#dc2626;'>".($d['clock_out'] ? $d['clock_out'] : '--:--')."</td>
                                    <td style='color:$s_color; font-weight:800;'>".$d['status']."</td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' style='padding:40px; color:#94a3b8;'>No daily records for this month.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

</body>
</html>