<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['role'])) { header("Location: index.php"); exit(); }
include 'db.php';

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// --- HANDLE LEAVE SUBMISSION ---
if (isset($_POST['apply_leave'])) {
    $type = $_POST['leave_type'];
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $reason = $_POST['reason'];

    // Basic Validation
    if ($start > $end) {
        $msg = "Error: Start date cannot be after End date.";
        $msg_type = "error";
    } else {
        // Insert into DB
        $stmt = $conn->prepare("INSERT INTO leave_requests (employee_id, leave_type, reason, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("issss", $user_id, $type, $reason, $start, $end);
        
        if ($stmt->execute()) {
            $msg = "Leave request submitted successfully!";
            $msg_type = "success";
        } else {
            $msg = "Error submitting request.";
            $msg_type = "error";
        }
    }
}

// Fetch Leave Balance (Mock Logic - You can connect this to real DB columns later)
$taken_casual = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE employee_id='$user_id' AND leave_type='Casual Leave' AND status='Approved'")->fetch_assoc()['c'] ?? 0;
$taken_sick = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE employee_id='$user_id' AND leave_type='Sick Leave' AND status='Approved'")->fetch_assoc()['c'] ?? 0;
$bal_casual = 12 - $taken_casual;
$bal_sick = 7 - $taken_sick;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Apply Leave | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        /* --- LAYOUT FIX --- */
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .sidebar { position: fixed !important; left: 0; top: 0; width: 260px !important; height: 100vh; z-index: 1000; }
        .main-content { margin-left: 260px !important; padding: 40px; width: calc(100% - 260px) !important; box-sizing: border-box; min-height: 100vh; }
        @media (max-width: 900px) { .sidebar { width: 70px !important; } .main-content { margin-left: 70px !important; width: calc(100% - 70px) !important; } }

        /* Page Components */
        .grid-container { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; }
        .card { background: white; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        
        .balance-box { display: flex; gap: 15px; margin-bottom: 25px; }
        .bal-card { flex: 1; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0; }
        .bal-card h3 { margin: 5px 0 0; font-size: 20px; color: #0f172a; }
        .bal-card span { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        
        .btn-submit { width: 100%; padding: 14px; background: #0f172a; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }

        /* Table Styles */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8fafc; color: #64748b; font-size: 11px; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; }
        td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .pending { background: #fff7ed; color: #c2410c; }
        .approved { background: #f0fdf4; color: #15803d; }
        .rejected { background: #fef2f2; color: #991b1b; }

        @media (max-width: 1000px) { .grid-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1 style="color:#0f172a; margin-bottom: 10px;">Apply for Leave</h1>
        <p style="color:#64748b; margin-bottom: 30px;">Request time off and track your application status.</p>

        <?php if($msg): ?>
            <div style="padding:15px; margin-bottom:20px; border-radius:8px; font-weight:600; background:<?php echo ($msg_type=='success')?'#dcfce7':'#fee2e2'; ?>; color:<?php echo ($msg_type=='success')?'#166534':'#991b1b'; ?>;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="grid-container">
            
            <div class="card">
                <div class="balance-box">
                    <div class="bal-card" style="background:#eff6ff; border-color:#bfdbfe;">
                        <span>Casual Leave</span>
                        <h3><?php echo $bal_casual; ?></h3>
                    </div>
                    <div class="bal-card" style="background:#fffbeb; border-color:#fde68a;">
                        <span>Sick Leave</span>
                        <h3><?php echo $bal_sick; ?></h3>
                    </div>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Leave Type</label>
                        <select name="leave_type" class="form-input" required>
                            <option>Casual Leave</option>
                            <option>Sick Leave</option>
                            <option>Emergency Leave</option>
                        </select>
                    </div>

                    <div style="display:flex; gap:15px;">
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">From Date</label>
                            <input type="date" name="start_date" class="form-input" required>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">To Date</label>
                            <input type="date" name="end_date" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" rows="3" class="form-input" placeholder="e.g. Doctor appointment, Family function..." required></textarea>
                    </div>

                    <button type="submit" name="apply_leave" class="btn-submit">Submit Request</button>
                </form>
            </div>

            <div class="card">
                <h3 style="margin-top:0; color:#0f172a; margin-bottom:20px;">My Leave History</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Date(s)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch only THIS user's leave requests
                        $q = $conn->query("SELECT * FROM leave_requests WHERE employee_id='$user_id' AND leave_type != 'Permission' ORDER BY id DESC LIMIT 10");
                        
                        if($q->num_rows > 0) {
                            while($row = $q->fetch_assoc()) {
                                $status_cls = strtolower($row['status']);
                                $dates = date('M d', strtotime($row['start_date']));
                                if($row['start_date'] != $row['end_date']) {
                                    $dates .= " - " . date('M d', strtotime($row['end_date']));
                                }

                                echo "<tr>
                                    <td>".$row['leave_type']."</td>
                                    <td>$dates</td>
                                    <td><span class='badge $status_cls'>".$row['status']."</span></td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' style='text-align:center; padding:30px; color:#94a3b8;'>No leave history found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

</body>
</html>