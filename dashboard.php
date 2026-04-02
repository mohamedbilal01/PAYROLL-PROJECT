<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['role'])) { header("Location: index.php"); exit(); }
include 'db.php';

$role = strtolower(trim($_SESSION['role']));
$user_name = $_SESSION['user_name'];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$today = date('Y-m-d');
$current_time = date('H:i:s');

// --- CONFIGURATION ---
$work_start = "08:00:00"; // Changed to 8:00 AM for testing
$work_end   = "18:00:00"; 

function isAdmin($r) { return in_array($r, ['admin', 'administrator', 'hr']); }

// --- LOGIC HANDLERS ---

// 1. Clock In / Out
if (isset($_POST['attendance_action'])) {
    $check_att = $conn->query("SELECT * FROM attendance WHERE employee_id='$user_id' AND date='$today'");
    $att_row = $check_att->fetch_assoc();
    
    $has_in = ($att_row && !empty($att_row['clock_in']) && $att_row['clock_in'] != '00:00:00');
    $has_out = ($att_row && !empty($att_row['clock_out']) && $att_row['clock_out'] != '00:00:00');
    $is_clocked_in = ($has_in && !$has_out);
    
    if (!$is_clocked_in && ($current_time < $work_start || $current_time > $work_end)) {
        $_SESSION['error_msg'] = "Shift Closed: You can only Clock In between 08:00 AM and 06:00 PM.";
    } else {
        if (!$att_row) {
            $conn->query("INSERT INTO attendance (employee_id, date, clock_in, status) VALUES ('$user_id', '$today', '$current_time', 'Present')");
            $_SESSION['success_msg'] = "Clocked In Successfully!";
        } else {
            $conn->query("UPDATE attendance SET clock_out='$current_time' WHERE employee_id='$user_id' AND date='$today'");
            $_SESSION['success_msg'] = "Clocked Out Successfully!";
        }
    }
    header("Location: dashboard.php"); exit();
}

// 2. Request Short-Hours Permission
if (isset($_POST['request_permission'])) {
    $reason = "Clocked out early. Requesting permission for full-day pay.";
    $conn->query("INSERT INTO leave_requests (employee_id, leave_type, reason, start_date, end_date, status) VALUES ('$user_id', 'Permission', '$reason', '$today', '$today', 'Pending')");
    $_SESSION['success_msg'] = "Permission request sent to HR.";
    header("Location: dashboard.php"); exit();
}

// 3. Apply / Update Leaves
if (isset($_POST['apply_leave'])) {
    $type = $_POST['leave_type']; $start = $_POST['start_date']; $end = $_POST['end_date']; $reason = $_POST['reason'];
    $conn->query("INSERT INTO leave_requests (employee_id, leave_type, reason, start_date, end_date) VALUES ('$user_id', '$type', '$reason', '$start', '$end')");
    $_SESSION['success_msg'] = "Leave request submitted.";
    header("Location: dashboard.php"); exit();
}

if (isset($_POST['update_leave'])) {
    $conn->query("UPDATE leave_requests SET status='".$_POST['status']."' WHERE id='".$_POST['req_id']."'");
}

// --- DATA FETCHING ---
$emp_count = 0; $total_paid = 0; $pending_leaves = 0;
$chart_labels = ['No Data']; $chart_data = [0];
$my_total = 0; $last_pay = 0; 

// Attendance Flags
$is_clocked_in = false; $is_done_today = false;
$needs_permission = false; $permission_requested = false; $perm_status = '';
$bal_casual = 12; $bal_sick = 7; 

if (isAdmin($role)) {
    $emp_count = $conn->query("SELECT COUNT(*) as total FROM employees")->fetch_assoc()['total'];
    $total_paid = $conn->query("SELECT SUM(net_pay) as total FROM payroll")->fetch_assoc()['total'] ?? 0;
    $pending_leaves = $conn->query("SELECT COUNT(*) as total FROM leave_requests WHERE status='Pending'")->fetch_assoc()['total'];
    
    $chart_res = $conn->query("
        SELECT month, year, SUM(net_pay) as total 
        FROM payroll 
        GROUP BY year, month 
        ORDER BY MAX(processed_on) ASC 
        LIMIT 6
    ");
    
    if($chart_res && $chart_res->num_rows > 0) {
        $chart_labels = []; $chart_data = [];
        while($c = $chart_res->fetch_assoc()){ 
            $chart_labels[] = substr($c['month'], 0, 3) . " " . $c['year']; 
            $chart_data[] = (float)$c['total']; 
        }
    }
}

if (!isAdmin($role) || $role == 'hr') {
    $last_pay = $conn->query("SELECT net_pay FROM payroll WHERE employee_id='$user_id' ORDER BY id DESC LIMIT 1")->fetch_assoc()['net_pay'] ?? 0;
    
    // FETCH ATTENDANCE & CALCULATE HOURS
    $att = $conn->query("SELECT * FROM attendance WHERE employee_id='$user_id' AND date='$today'")->fetch_assoc();
    if ($att) {
        $has_in = !empty($att['clock_in']) && $att['clock_in'] != '00:00:00';
        $has_out = !empty($att['clock_out']) && $att['clock_out'] != '00:00:00';
        
        if ($has_in && !$has_out) $is_clocked_in = true;
        
        if ($has_in && $has_out) {
            $is_done_today = true;
            
            // MATH: Calculate total hours worked today
            $in_time = strtotime($att['clock_in']);
            $out_time = strtotime($att['clock_out']);
            $hours_worked = ($out_time - $in_time) / 3600;
            
            // If less than 4 hours, trigger the Permission warning
            if ($hours_worked < 4) {
                $needs_permission = true;
                
                // Check if they already clicked the request button
                $check_p = $conn->query("SELECT status FROM leave_requests WHERE employee_id='$user_id' AND start_date='$today' AND leave_type='Permission'");
                if($check_p->num_rows > 0) {
                    $permission_requested = true;
                    $perm_status = $check_p->fetch_assoc()['status'];
                }
            }
        }
    }

    $taken_casual = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE employee_id='$user_id' AND leave_type='Casual Leave' AND status='Approved'")->fetch_assoc()['c'] ?? 0;
    $taken_sick = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE employee_id='$user_id' AND leave_type='Sick Leave' AND status='Approved'")->fetch_assoc()['c'] ?? 0;
    $bal_casual = 12 - $taken_casual;
    $bal_sick = 7 - $taken_sick;
}

$msg = isset($_SESSION['success_msg']) ? $_SESSION['success_msg'] : (isset($_SESSION['error_msg']) ? $_SESSION['error_msg'] : '');
$msg_type = isset($_SESSION['success_msg']) ? 'success' : (isset($_SESSION['error_msg']) ? 'error' : '');
unset($_SESSION['success_msg']); unset($_SESSION['error_msg']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        :root {
            --bg-color: #f8fafc;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --primary: #4361ee;
        }

        body { margin: 0; background-color: var(--bg-color); font-family: 'Inter', sans-serif; overflow-x: hidden; }
        
        .sidebar { position: fixed !important; left: 0; top: 0; width: 260px !important; height: 100vh; z-index: 1000; overflow-y: auto; }
        .sidebar::-webkit-scrollbar { width: 0 !important; }
        .sidebar { -ms-overflow-style: none; scrollbar-width: none; }
        .main-content { margin-left: 260px !important; padding: 40px; width: calc(100% - 260px) !important; box-sizing: border-box; }

        .dash-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 35px; }
        .header-titles h1 { margin: 0 0 5px 0; font-size: 28px; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; }
        .header-titles p { margin: 0; color: var(--text-muted); font-size: 15px; }
        
        .header-action { background: var(--surface); padding: 12px 20px; border-radius: 16px; display: flex; align-items: center; gap: 20px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .time-block { text-align: right; }
        .digital-clock { font-size: 22px; font-weight: 800; color: var(--text-main); font-variant-numeric: tabular-nums; line-height: 1; margin-bottom: 4px; }
        .date-text { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .btn-clock { padding: 10px 20px; border-radius: 10px; border: none; font-weight: 700; font-size: 14px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .clock-in { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3); }
        .clock-in:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(67, 97, 238, 0.4); }
        .clock-out { background: #ef4444; color: white; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }
        .clock-out:hover { transform: translateY(-2px); }
        .clock-done { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
        .clock-warn { background: #f59e0b; color: white; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
        .clock-warn:hover { transform: translateY(-2px); }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 35px; }
        .stat-card { background: var(--surface); padding: 25px; border-radius: 20px; position: relative; overflow: hidden; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); transition: 0.3s; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -8px rgba(0,0,0,0.08); }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; }
        .card-blue::before { background: var(--primary); }
        .card-yellow::before { background: #f59e0b; }
        .card-green::before { background: #10b981; }
        
        .stat-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .stat-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .icon-blue { background: #eff6ff; color: var(--primary); }
        .icon-yellow { background: #fffbeb; color: #f59e0b; }
        .icon-green { background: #f0fdf4; color: #10b981; }
        
        .stat-value { font-size: 32px; font-weight: 800; color: var(--text-main); margin: 0 0 5px 0; }
        .stat-label { font-size: 14px; font-weight: 600; color: var(--text-muted); margin: 0; }

        .bento-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        .bento-box { background: var(--surface); border-radius: 20px; padding: 30px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .box-title { font-size: 15px; font-weight: 800; color: var(--text-main); margin: 0 0 25px 0; display: flex; align-items: center; gap: 8px; }
        .box-title i { color: var(--primary); }

        .queue-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8fafc; border-radius: 12px; margin-bottom: 12px; border: 1px solid #f1f5f9; transition: 0.2s; }
        .queue-item:hover { background: white; border-color: #cbd5e1; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
        .queue-info h4 { margin: 0 0 4px 0; font-size: 14px; color: var(--text-main); font-weight: 700; }
        .queue-info p { margin: 0; font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .btn-approve { background: #dcfce7; color: #166534; border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 12px; transition: 0.2s; }
        .btn-approve:hover { background: #bbf7d0; }

        .alert-box { padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px 15px; font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; border-bottom: 2px solid var(--border); }
        td { padding: 15px; font-size: 14px; color: #334155; border-bottom: 1px solid #f1f5f9; font-weight: 500; }
        .btn-pdf { color: var(--primary); text-decoration: none; font-weight: 700; font-size: 13px; background: #eff6ff; padding: 6px 12px; border-radius: 6px; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <div class="dash-header">
            <div class="header-titles">
                <?php if(isAdmin($role)): ?>
                    <h1><?php echo ($role == 'hr') ? 'HR Workspace' : 'System Administration'; ?></h1>
                    <p>Performance metrics and operational overview.</p>
                <?php else: ?>
                    <h1>Welcome back, <?php echo explode(' ', trim($user_name))[0]; ?></h1>
                    <p>Here is your daily workflow overview.</p>
                <?php endif; ?>
            </div>

            <?php if(!isAdmin($role) || $role == 'hr'): ?>
                <div class="header-action">
                    <div class="time-block">
                        <div class="digital-clock" id="clock">00:00:00</div>
                        <div class="date-text"><?php echo date('D, M d'); ?></div>
                    </div>
                    <form method="POST" style="margin: 0;">
                        <?php if($is_done_today): ?> 
                            <?php if($needs_permission): ?>
                                <?php if($permission_requested): ?>
                                    <button type="button" disabled class="btn-clock clock-done"><i class="fas fa-clock"></i> <?php echo strtoupper($perm_status); ?></button>
                                <?php else: ?>
                                    <button type="submit" name="request_permission" class="btn-clock clock-warn"><i class="fas fa-hand-paper"></i> Ask Permission</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button type="button" disabled class="btn-clock clock-done"><i class="fas fa-check-circle"></i> Done</button>
                            <?php endif; ?>
                        <?php elseif($is_clocked_in): ?> 
                            <button type="submit" name="attendance_action" class="btn-clock clock-out">Clock Out</button>
                        <?php else: ?> 
                            <?php if($current_time >= $work_start && $current_time <= $work_end): ?> 
                                <button type="submit" name="attendance_action" class="btn-clock clock-in">Clock In</button>
                            <?php else: ?> 
                                <button type="button" disabled class="btn-clock clock-done">Closed</button> 
                            <?php endif; ?>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if($msg): ?>
            <div class="alert-box <?php echo ($msg_type == 'success') ? 'alert-success' : 'alert-error'; ?>">
                <i class="fas fa-info-circle"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <?php if(isAdmin($role)): ?>
            <div class="stats-grid">
                <div class="stat-card card-blue">
                    <div class="stat-header"><div class="stat-icon icon-blue"><i class="fas fa-users"></i></div></div>
                    <h3 class="stat-value"><?php echo $emp_count; ?></h3>
                    <p class="stat-label">Active Staff Members</p>
                </div>
                <div class="stat-card card-yellow">
                    <div class="stat-header"><div class="stat-icon icon-yellow"><i class="fas fa-file-signature"></i></div></div>
                    <h3 class="stat-value"><?php echo $pending_leaves; ?></h3>
                    <p class="stat-label">Pending Leave Requests</p>
                </div>
                <div class="stat-card card-green">
                    <div class="stat-header"><div class="stat-icon icon-green"><i class="fas fa-money-bill-wave"></i></div></div>
                    <h3 class="stat-value">₹<?php echo number_format($total_paid); ?></h3>
                    <p class="stat-label">Total Salary Distributed</p>
                </div>
            </div>

            <div class="bento-grid">
                <div class="bento-box">
                    <h3 class="box-title"><i class="fas fa-chart-line"></i> Financial Analytics</h3>
                    <div style="height: 320px; width: 100%;"><canvas id="payrollChart"></canvas></div>
                </div>
                <div class="bento-box">
                    <h3 class="box-title"><i class="fas fa-inbox"></i> Action Required</h3>
                    <?php
                    $res = $conn->query("SELECT l.*, e.name FROM leave_requests l JOIN employees e ON l.employee_id = e.id WHERE l.status='Pending' LIMIT 4");
                    if($res->num_rows > 0) {
                        while($row = $res->fetch_assoc()){
                            echo "<div class='queue-item'>
                                <div class='queue-info'>
                                    <h4>".$row['name']."</h4>
                                    <p>".$row['leave_type']."</p>
                                </div>
                                <form method='POST' style='margin:0;'>
                                    <input type='hidden' name='req_id' value='".$row['id']."'>
                                    <button name='update_leave' value='Approved' class='btn-approve'>Approve</button>
                                </form>
                            </div>";
                        }
                    } else { echo "<div style='text-align:center; padding:40px 20px; color:#94a3b8; font-size:14px;'><i class='fas fa-check-circle' style='font-size:32px; color:#cbd5e1; margin-bottom:10px; display:block;'></i> Inbox Zero</div>"; }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if(!isAdmin($role)): ?>
            <div class="stats-grid">
                <div class="stat-card card-blue">
                    <div class="stat-header"><div class="stat-icon icon-blue"><i class="fas fa-umbrella-beach"></i></div></div>
                    <h3 class="stat-value"><?php echo $bal_casual; ?></h3>
                    <p class="stat-label">Casual Leaves Remaining</p>
                </div>
                <div class="stat-card card-yellow">
                    <div class="stat-header"><div class="stat-icon icon-yellow"><i class="fas fa-procedures"></i></div></div>
                    <h3 class="stat-value"><?php echo $bal_sick; ?></h3>
                    <p class="stat-label">Sick Leaves Remaining</p>
                </div>
                <div class="stat-card card-green">
                    <div class="stat-header"><div class="stat-icon icon-green"><i class="fas fa-wallet"></i></div></div>
                    <h3 class="stat-value">₹<?php echo number_format($last_pay); ?></h3>
                    <p class="stat-label">Last Net Pay</p>
                </div>
            </div>
            
            <div class="bento-grid" style="grid-template-columns: 1fr;">
                <div class="bento-box">
                    <h3 class="box-title"><i class="fas fa-file-invoice-dollar"></i> Recent Disbursals</h3>
                    <table>
                        <thead><tr><th>Period</th><th>Amount</th><th>Status</th><th>Document</th></tr></thead>
                        <tbody>
                            <?php
                            $ps = $conn->query("SELECT * FROM payroll WHERE employee_id='$user_id' ORDER BY id DESC LIMIT 4");
                            if($ps->num_rows > 0) { while($p = $ps->fetch_assoc()){ echo "<tr><td style='font-weight:700;'>".$p['month']." ".$p['year']."</td><td>₹".number_format($p['net_pay'])."</td><td><span style='background:#dcfce7; color:#166534; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700;'>PAID</span></td><td><a href='payslip.php?id=".$p['id']."' target='_blank' class='btn-pdf'><i class='fas fa-download'></i></a></td></tr>"; } } else { echo "<tr><td colspan='4' style='text-align:center; padding:30px; color:#94a3b8;'>No payslips found</td></tr>"; }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Live Clock Logic
    function updateClock() { 
        const t = new Date().toLocaleTimeString('en-US', { hour12: false });
        if(document.getElementById('clock')) document.getElementById('clock').innerText = t; 
    }
    setInterval(updateClock, 1000); updateClock(); 

    // Modern Gradient Chart Logic
    <?php if(isAdmin($role)): ?>
    window.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('payrollChart').getContext('2d');
        let gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(67, 97, 238, 0.9)');
        gradient.addColorStop(1, 'rgba(67, 97, 238, 0.1)');

        new Chart(ctx, {
            type: 'bar',
            data: { 
                labels: <?php echo json_encode($chart_labels); ?>, 
                datasets: [{ 
                    label: 'Paid (₹)', 
                    data: <?php echo json_encode($chart_data); ?>, 
                    backgroundColor: gradient, 
                    borderRadius: 8, 
                    borderSkipped: false,
                    barThickness: 35 
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } },
                scales: { 
                    y: { beginAtZero: true, grid: { color: '#f1f5f9', drawBorder: false }, border: { display: false } }, 
                    x: { grid: { display: false }, border: { display: false } } 
                } 
            }
        });
    });
    <?php endif; ?>
</script>
</body>
</html>