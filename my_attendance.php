<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['role'])) { header("Location: index.php"); exit(); }
include 'db.php';

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$current_time = date('H:i:s');

// --- CONFIGURATION ---
$work_start = "08:00:00"; // Changed to 8:00 AM for testing
$work_end   = "18:00:00"; 

$msg = ""; $msg_type = "";

// --- HANDLE CLOCK ACTIONS ---
$chk = $conn->query("SELECT * FROM attendance WHERE employee_id='$user_id' AND date='$today'");
$att = $chk->fetch_assoc();
$is_clocked_in = ($att && $att['clock_in'] != '' && $att['clock_out'] == '');
$is_day_complete = ($att && $att['clock_out'] != '');

// AJAX Endpoint for Clock Actions
if (isset($_POST['ajax_clock_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_clock_action'];
    
    // Safety check shift hours
    if ($action == 'in') {
        if ($is_clocked_in || $is_day_complete) {
            echo json_encode(['success' => false, 'message' => 'Action invalid: Already clocked in or day complete.']);
            exit();
        }
        if ($current_time < $work_start || $current_time > $work_end) {
            echo json_encode(['success' => false, 'message' => 'Error: Shift hours are 08:00 AM - 06:00 PM.']);
            exit();
        }
        $conn->query("INSERT INTO attendance (employee_id, date, clock_in, status) VALUES ('$user_id', '$today', '$current_time', 'Present')");
        // Return active month name to dynamically update DOM
        echo json_encode(['success' => true, 'message' => 'Clocked In Successfully!', 'action' => 'in', 'month' => date('M')]);
        exit();
    } elseif ($action == 'out') {
        if (!$is_clocked_in) {
            echo json_encode(['success' => false, 'message' => 'Not clocked in yet.']);
            exit();
        }
        $conn->query("UPDATE attendance SET clock_out='$current_time' WHERE employee_id='$user_id' AND date='$today'");
        echo json_encode(['success' => true, 'message' => 'Clocked Out Successfully!', 'action' => 'out']);
        exit();
    }
}

// --- CALCULATE CURRENT YEAR DATA (Jan to Current Month) ---
$monthly_data = [];
$total_present_all = 0;
$total_working_all = 0;

$current_year = date('Y');
$current_month = date('n');

for ($m = 1; $m <= $current_month; $m++) { 
    $month_short = date('M', mktime(0, 0, 0, $m, 10));

    // Get Present Days
    $q_present = $conn->query("SELECT COUNT(id) as c FROM attendance WHERE employee_id='$user_id' AND YEAR(date)='$current_year' AND MONTH(date)='$m' AND status='Present'");
    $present_days = $q_present->fetch_assoc()['c'] ?? 0;

    // Calculate Working Days (Mon-Fri)
    $working_days = 0;
    $start_date = new DateTime("$current_year-$m-01");
    $end_date = new DateTime("$current_year-$m-" . $start_date->format('t'));
    $today_obj = new DateTime();
    
    // Cap the end date to today if we are calculating the current month
    if ($start_date->format('Y-m') == $today_obj->format('Y-m')) { $end_date = $today_obj; }
    
    if ($start_date <= $today_obj) {
        while ($start_date <= $end_date) {
            if ($start_date->format('N') < 6) { $working_days++; } // 1-5 are weekdays
            $start_date->modify('+1 day');
        }
    }

    if ($working_days > 0) {
        $monthly_data[] = [
            'year' => $current_year, 'month' => $month_short,
            'present' => $present_days, 'total' => $working_days
        ];
        $total_present_all += $present_days;
        $total_working_all += $working_days;
    }
}

$overall_percentage = ($total_working_all > 0) ? round(($total_present_all / $total_working_all) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Attendance | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css"> 
    
    <style>
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .sidebar { position: fixed !important; left: 0; top: 0; width: 260px !important; height: 100vh; z-index: 1000; }
        .main-content { margin-left: 260px !important; padding: 40px; width: calc(100% - 260px) !important; box-sizing: border-box; min-height: 100vh; }
        
        /* Clock Card */
        .clock-card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; text-align: center; margin-bottom: 30px; }
        .clock-display { font-size: 42px; font-weight: 800; color: #0f172a; margin: 10px 0; font-variant-numeric: tabular-nums; }
        .btn-action { width: 100%; max-width: 250px; padding: 14px; border-radius: 10px; border: none; font-size: 15px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-in { background: #4361ee; color: white; }
        .btn-out { background: #ef4444; color: white; }
        .btn-disabled { background: #cbd5e1; color: #64748b; cursor: not-allowed; }

        /* Termly UI Styles */
        .layout-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; align-items: start; }
        .term-card { background: white; border-radius: 20px; padding: 40px 30px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; }
        
        .donut-ring { width: 180px; height: 180px; border-radius: 50%; background: conic-gradient(#22c55e <?php echo $overall_percentage; ?>%, #f1f5f9 0); display: flex; align-items: center; justify-content: center; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .donut-hole { width: 130px; height: 130px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; color: #0f172a; box-shadow: inset 0 2px 5px rgba(0,0,0,0.02); }
        
        .term-label { color: #94a3b8; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .term-dates { color: #0f172a; font-size: 16px; font-weight: 800; margin-top: 5px; }

        .table-card { background: white; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: hidden; }
        .term-table { width: 100%; border-collapse: collapse; }
        .term-table th { background: #f8fafc; padding: 20px; text-align: center; font-size: 13px; font-weight: 700; color: #64748b; border-bottom: 2px solid #f1f5f9; text-transform:uppercase; }
        .term-table td { padding: 20px; text-align: center; font-size: 15px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f8fafc; }
        .total-row td { background: #f8fafc; font-weight: 800; border-top: 2px solid #e2e8f0; font-size: 16px; }

        .btn-history { display: inline-flex; align-items: center; gap: 8px; padding: 8px 15px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; transition: 0.2s; border: 1px solid #e2e8f0; }
        .btn-history:hover { background: #e2e8f0; color: #0f172a; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h1 style="color:#0f172a; margin: 0 0 5px 0;">My Attendance</h1>
                <p style="color:#64748b; margin: 0;">Daily clock-in and <?php echo $current_year; ?> performance.</p>
            </div>
            <a href="past_attendance.php" class="btn-history"><i class="fas fa-history"></i> View Previous Years</a>
        </div>

        <div id="ajax-msg" style="display:none; padding:15px; margin-bottom:20px; border-radius:8px; font-weight:600;"></div>
        <?php if($msg): ?>
            <div style="padding:15px; border-radius:8px; margin-bottom:20px; font-weight:600; <?php echo ($msg_type == 'success') ? 'background:#dcfce7; color:#166534;' : 'background:#fee2e2; color:#991b1b;'; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="clock-card">
            <div style="font-size: 13px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Current Time</div>
            <div class="clock-display" id="digital-clock">00:00:00</div>
            <div style="font-size: 15px; font-weight: 600; color: #64748b; margin-bottom: 20px;"><?php echo date('l, d F Y'); ?></div>

            <div id="clock-button-container">
                <?php if ($is_day_complete): ?>
                    <button type="button" class="btn-action btn-disabled" disabled><i class="fas fa-check-circle"></i> Workday Complete</button>
                <?php elseif ($is_clocked_in): ?>
                    <button type="button" onclick="handleClockAction('out')" class="btn-action btn-out" id="action-btn"><i class="fas fa-sign-out-alt"></i> Clock Out</button>
                <?php else: ?>
                    <?php if ($current_time >= $work_start && $current_time <= $work_end): ?>
                        <button type="button" onclick="handleClockAction('in')" class="btn-action btn-in" id="action-btn"><i class="fas fa-sign-in-alt"></i> Clock In</button>
                    <?php else: ?>
                        <button type="button" class="btn-action btn-disabled" disabled><i class="fas fa-lock"></i> Outside Shift Hours</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="layout-grid">
            <div class="term-card">
                <div class="donut-ring"><div class="donut-hole"><?php echo $overall_percentage; ?>%</div></div>
                <div class="term-label">Year Overview</div>
                <div class="term-dates">Jan <?php echo $current_year; ?> To Present</div>
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
                                <td style='color:#16a34a;'>".$row['present'].".0</td>
                                <td>".$row['total'].".0</td>
                            </tr>";
                            $si++;
                        }
                        if (empty($monthly_data)) {
                            echo "<tr><td colspan='5' style='color:#94a3b8;'>No records found for this year yet.</td></tr>";
                        }
                        ?>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: left; padding-left: 30px;">Total</td>
                            <td style="color:#16a34a;"><?php echo $total_present_all; ?>.0</td>
                            <td><?php echo $total_working_all; ?>.0</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function updateClock() { document.getElementById('digital-clock').innerText = new Date().toLocaleTimeString('en-US', { hour12: false }); }
    setInterval(updateClock, 1000); updateClock();

    function handleClockAction(action) {
        let btn = document.getElementById('action-btn');
        if(!btn) return;
        
        let originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;

        let formData = new FormData();
        formData.append('ajax_clock_action', action);

        fetch('my_attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                showActionMsg(data.message, 'success');
                
                // Update Button State Dynamically
                let container = document.getElementById('clock-button-container');
                if(data.action === 'in') {
                    // Update to Clock Out button
                    container.innerHTML = `<button type="button" onclick="handleClockAction('out')" class="btn-action btn-out" id="action-btn"><i class="fas fa-sign-out-alt"></i> Clock Out</button>`;
                    
                    // Increment the table's Present stat dynamically to avoid reload
                    updateTableData(data.month);
                } else if(data.action === 'out') {
                    // Complete day
                    container.innerHTML = `<button type="button" class="btn-action btn-disabled" disabled><i class="fas fa-check-circle"></i> Workday Complete</button>`;
                }
            } else {
                showActionMsg(data.message, 'error');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        })
        .catch(err => {
            showActionMsg('Network Error. Please try again.', 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }

    function showActionMsg(msg, type) {
        let notif = document.getElementById('ajax-msg');
        if (!notif) return;
        notif.style.display = 'block';
        notif.innerHTML = msg;
        if (type === 'success') {
            notif.style.background = '#dcfce7'; 
            notif.style.color = '#166534';
        } else {
            notif.style.background = '#fee2e2'; 
            notif.style.color = '#991b1b';
        }
        setTimeout(() => { notif.style.display = 'none'; }, 5000);
    }

    function updateTableData(monthShort) {
        let rows = document.querySelectorAll('.term-table tbody tr');
        let updated = false;
        
        // Update the row for the correct month
        for (let row of rows) {
            if (!row.classList.contains('total-row') && row.cells.length >= 4) {
                if (row.cells[2].innerText.trim() === monthShort) {
                    let presentCell = row.cells[3];
                    let currentPresent = parseFloat(presentCell.innerText) || 0;
                    presentCell.innerText = (currentPresent + 1).toFixed(1);
                    updated = true;
                    break;
                }
            }
        }
        
        // Update the grand Total row
        if (updated) {
            let totalRow = document.querySelector('.total-row');
            if (totalRow && totalRow.cells.length >= 2) {
                let totalPresentCell = totalRow.cells[totalRow.cells.length - 2]; 
                let currentTotal = parseFloat(totalPresentCell.innerText) || 0;
                totalPresentCell.innerText = (currentTotal + 1).toFixed(1);
            }
        }
    }
</script>
</body>
</html>