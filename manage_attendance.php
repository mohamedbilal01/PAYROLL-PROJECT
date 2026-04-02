<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { header("Location: index.php"); exit(); }
include 'db.php';

$view_date = isset($_POST['view_date']) ? $_POST['view_date'] : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>All Attendance | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background-color: #f1f5f9; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .sidebar { position: fixed !important; left: 0; top: 0; width: 260px !important; height: 100vh; z-index: 1000; }
        .main-content { margin-left: 260px !important; padding: 40px; width: calc(100% - 260px) !important; box-sizing: border-box; }
        
        .premium-banner { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 16px; padding: 35px 40px; color: white; margin-bottom: 30px; position: relative; overflow: hidden; display: flex; align-items: center; gap: 20px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3); }
        .premium-banner::after { content: ''; position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; background: rgba(67, 97, 238, 0.15); border-radius: 50%; filter: blur(40px); }
        .banner-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 28px; color: #60a5fa; border: 1px solid rgba(255,255,255,0.1); }
        
        .card { background: white; border-radius: 16px; padding: 30px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        
        .filter-bar { display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .date-input { padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-weight: 600; color: #0f172a; outline: none; }
        .btn-filter { padding: 10px 20px; background: #4361ee; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-filter:hover { background: #3a56d4; }

        table { width: 100%; border-collapse: collapse; }
        th { padding: 15px; text-align: left; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        td { padding: 18px 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; }
        
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-present { background: #dcfce7; color: #166534; }
        .badge-absent { background: #fee2e2; color: #991b1b; }
        .time-txt { font-family: monospace; font-size: 15px; font-weight: 600; color: #0f172a; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="premium-banner">
            <div class="banner-icon"><i class="fas fa-calendar-check"></i></div>
            <div style="position: relative; z-index: 10;">
                <h1 style="margin: 0 0 5px 0; font-size: 26px; font-weight: 800;">Attendance Monitor</h1>
                <p style="margin: 0; color: #94a3b8; font-size: 15px;">Daily attendance records and timestamps for all staff.</p>
            </div>
        </div>

        <div class="card">
            <form method="POST" class="filter-bar">
                <div style="font-weight: 600; color: #475569;">Viewing Date:</div>
                <div style="display:flex; gap:15px;">
                    <input type="date" name="view_date" class="date-input" value="<?php echo $view_date; ?>">
                    <button type="submit" class="btn-filter">Load Records</button>
                </div>
            </form>

            <table>
                <thead><tr><th>Employee Name</th><th>Role</th><th>Clock In</th><th>Clock Out</th><th>Status</th></tr></thead>
                <tbody>
                    <?php
                    $emps = $conn->query("SELECT id, name, role FROM employees WHERE role != 'admin' ORDER BY name ASC");
                    while($e = $emps->fetch_assoc()) {
                        $att_q = $conn->query("SELECT * FROM attendance WHERE employee_id='".$e['id']."' AND date='$view_date'");
                        $att = $att_q->fetch_assoc();
                        
                        $in = ($att && $att['clock_in']) ? date('h:i A', strtotime($att['clock_in'])) : '-';
                        $out = ($att && $att['clock_out']) ? date('h:i A', strtotime($att['clock_out'])) : '-';
                        
                        $status = 'Absent'; $badge = 'badge-absent';
                        if($att && $att['status'] == 'Present') { $status = 'Present'; $badge = 'badge-present'; }

                        echo "<tr>
                            <td style='font-weight:700; color:#0f172a;'>".$e['name']."</td>
                            <td style='text-transform:capitalize; color:#64748b;'>".$e['role']."</td>
                            <td class='time-txt' style='color:#16a34a;'>$in</td>
                            <td class='time-txt' style='color:#dc2626;'>$out</td>
                            <td><span class='badge $badge'>$status</span></td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>