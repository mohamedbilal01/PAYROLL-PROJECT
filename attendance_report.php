<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { header("Location: index.php"); exit(); }
include 'db.php';

$selected_month = isset($_GET['month']) ? str_pad($_GET['month'], 2, '0', STR_PAD_LEFT) : date('m');
$selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>All Attendance | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; padding: 0; background: #f8fafc; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .sidebar { position: fixed !important; left: 0; top: 0; width: 260px !important; height: 100vh; z-index: 1000; }
        .main-content { margin-left: 260px !important; padding: 40px; width: calc(100% - 260px) !important; box-sizing: border-box; }
        
        .premium-banner { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 16px; padding: 35px 40px; color: white; margin-bottom: 30px; position: relative; overflow: hidden; display: flex; align-items: center; gap: 20px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3); }
        .premium-banner::after { content: ''; position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; background: rgba(67, 97, 238, 0.15); border-radius: 50%; filter: blur(40px); }
        .banner-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 28px; color: #60a5fa; border: 1px solid rgba(255,255,255,0.1); }

        .filter-card { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 25px 30px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .select-input { padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a; outline: none; font-family: 'Inter'; cursor: pointer; min-width: 150px; }
        .select-input:focus { border-color: #4361ee; }

        .card { background: white; padding: 0; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 20px; background: #f8fafc; color: #64748b; font-size: 11px; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-weight: 800; }
        td { padding: 20px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="premium-banner">
            <div class="banner-icon"><i class="fas fa-calendar-check"></i></div>
            <div style="position: relative; z-index: 10;">
                <h1 style="margin: 0 0 5px 0; font-size: 26px; font-weight: 800;">All Attendance Records</h1>
                <p style="margin: 0; color: #94a3b8; font-size: 15px;">Monitor staff daily attendance logs, filtered by month.</p>
            </div>
        </div>

        <form method="GET" class="filter-card">
            <div style="display: flex; gap: 20px; align-items: center;">
                <div>
                    <div style="font-size:11px; font-weight:700; color:#64748b; margin-bottom:5px; text-transform:uppercase;">Month</div>
                    <select name="month" class="select-input" onchange="this.form.submit()">
                        <?php
                        for ($m = 1; $m <= 12; $m++) {
                            $m_padded = str_pad($m, 2, '0', STR_PAD_LEFT);
                            $month_name = date("F", mktime(0, 0, 0, $m, 10));
                            $sel = ($m_padded == $selected_month) ? 'selected' : '';
                            echo "<option value='$m_padded' $sel>$month_name</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <div style="font-size:11px; font-weight:700; color:#64748b; margin-bottom:5px; text-transform:uppercase;">Year</div>
                    <select name="year" class="select-input" onchange="this.form.submit()">
                        <?php
                        $curr = date('Y');
                        for($y = $curr; $y >= ($curr - 5); $y--) {
                            $sel = ($y == $selected_year) ? 'selected' : '';
                            echo "<option value='$y' $sel>$y</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </form>

        <div class="card">
            <table>
                <thead><tr><th>Date</th><th>Employee</th><th>Clock In</th><th>Clock Out</th><th>Status</th></tr></thead>
                <tbody>
                    <?php
                    $query = "SELECT a.*, e.name FROM attendance a JOIN employees e ON a.employee_id = e.id 
                              WHERE MONTH(a.date) = '$selected_month' AND YEAR(a.date) = '$selected_year' 
                              ORDER BY a.date DESC, a.clock_in DESC";
                    $res = $conn->query($query);
                    
                    if ($res && $res->num_rows > 0) {
                        while($row = $res->fetch_assoc()) {
                            $status_color = ($row['status'] == 'Present') ? '#16a34a' : (($row['status'] == 'Absent') ? '#dc2626' : '#ea580c');
                            echo "<tr>
                                <td>".date('M d, Y', strtotime($row['date']))."</td>
                                <td><b style='color:#0f172a;'>".$row['name']."</b></td>
                                <td style='color:#16a34a;'>".$row['clock_in']."</td>
                                <td style='color:#dc2626;'>".($row['clock_out'] ? $row['clock_out'] : '--:--')."</td>
                                <td style='color:$status_color;'>".$row['status']."</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center; padding:40px; color:#94a3b8;'>No attendance records found for this month.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>