<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['role'])) { header("Location: index.php"); exit(); }
include 'db.php';

$user_id = $_SESSION['user_id'];

// Default to last year if no year is selected
$view_year = isset($_GET['year']) ? (int)$_GET['year'] : (date('Y') - 1);

$monthly_data = [];
$total_present_all = 0;
$total_working_all = 0;

// Calculate for all 12 months of the selected past year
for ($m = 1; $m <= 12; $m++) { 
    $month_short = date('M', mktime(0, 0, 0, $m, 10));

    // Get Present Days
    $q_present = $conn->query("SELECT COUNT(id) as c FROM attendance WHERE employee_id='$user_id' AND YEAR(date)='$view_year' AND MONTH(date)='$m' AND status='Present'");
    $present_days = $q_present->fetch_assoc()['c'] ?? 0;

    // Calculate Working Days (Mon-Fri) for the whole month
    $working_days = 0;
    $start_date = new DateTime("$view_year-$m-01");
    $end_date = new DateTime("$view_year-$m-" . $start_date->format('t'));
    
    while ($start_date <= $end_date) {
        if ($start_date->format('N') < 6) { $working_days++; } // 1-5 are weekdays
        $start_date->modify('+1 day');
    }

    // Only add months where there were actually working days to display
    if ($working_days > 0) {
        $monthly_data[] = [
            'year' => $view_year, 'month' => $month_short,
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
    <title>Past Attendance | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css"> 
    
    <style>
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .sidebar { position: fixed !important; left: 0; top: 0; width: 260px !important; height: 100vh; z-index: 1000; }
        .main-content { margin-left: 260px !important; padding: 40px; width: calc(100% - 260px) !important; box-sizing: border-box; min-height: 100vh; }
        
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px 30px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .year-select { padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 700; color: #0f172a; outline: none; font-family: 'Inter'; cursor: pointer; }
        
        .layout-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; align-items: start; }
        .term-card { background: white; border-radius: 20px; padding: 40px 30px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; }
        
        .donut-ring { width: 180px; height: 180px; border-radius: 50%; background: conic-gradient(#4361ee <?php echo $overall_percentage; ?>%, #f1f5f9 0); display: flex; align-items: center; justify-content: center; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .donut-hole { width: 130px; height: 130px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; color: #0f172a; box-shadow: inset 0 2px 5px rgba(0,0,0,0.02); }
        
        .term-label { color: #94a3b8; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .term-dates { color: #0f172a; font-size: 16px; font-weight: 800; margin-top: 5px; }

        .table-card { background: white; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: hidden; }
        .term-table { width: 100%; border-collapse: collapse; }
        .term-table th { background: #f8fafc; padding: 20px; text-align: center; font-size: 13px; font-weight: 700; color: #64748b; border-bottom: 2px solid #f1f5f9; text-transform:uppercase; }
        .term-table td { padding: 20px; text-align: center; font-size: 15px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f8fafc; }
        .total-row td { background: #f8fafc; font-weight: 800; border-top: 2px solid #e2e8f0; font-size: 16px; }

        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 8px 15px; color: #4361ee; text-decoration: none; font-weight: 700; font-size: 14px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <a href="my_attendance.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Current Year</a>

        <div class="header-bar">
            <div>
                <h1 style="color:#0f172a; margin: 0 0 5px 0;">Attendance History</h1>
                <p style="color:#64748b; margin: 0;">Review your records from previous years.</p>
            </div>
            
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <span style="color: #64748b; font-weight: 600; font-size: 13px; text-transform: uppercase;">Select Year:</span>
                <select name="year" class="year-select" onchange="this.form.submit()">
                    <?php
                    // Create dropdown for the last 5 years
                    $curr = date('Y');
                    for($y = $curr; $y >= ($curr - 5); $y--) {
                        $selected = ($y == $view_year) ? 'selected' : '';
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                </select>
            </form>
        </div>

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
                            echo "<tr><td colspan='5' style='padding:40px; color:#94a3b8;'>No attendance records found for $view_year.</td></tr>";
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

    </div>
</div>

</body>
</html>