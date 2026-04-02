<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

// Security Check: Only Admin and HR can access this page
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { 
    header("Location: index.php"); exit(); 
}
include 'db.php';

$msg = ""; $msg_type = "";

// --- BACKEND LOGIC: HANDLE APPROVE / REJECT ---
if (isset($_POST['update_request'])) {
    $req_id = (int)$_POST['request_id'];
    $new_status = $_POST['update_request']; // Will be either 'Approved' or 'Rejected'
    
    $update_sql = "UPDATE leave_requests SET status='$new_status' WHERE id='$req_id'";
    if ($conn->query($update_sql)) {
        $msg = "Request successfully marked as " . strtoupper($new_status) . "!";
        $msg_type = "success";
    } else {
        $msg = "Error updating request: " . $conn->error;
        $msg_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Approval Queue | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css"> </head>
<body>

<div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">Approval Queue</h1>
                <p class="page-subtitle">Manage leave applications and payment permissions.</p>
            </div>
        </div>

        <?php if($msg): ?>
            <div style="padding:15px; border-radius:10px; margin-bottom:25px; font-weight:600; border: 1px solid; <?php echo ($msg_type == 'success') ? 'background:#dcfce7; color:#166534; border-color: #bbf7d0;' : 'background:#fee2e2; color:#991b1b; border-color: #fecaca;'; ?>">
                <i class="fas <?php echo ($msg_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="bento-card" style="margin-bottom: 30px;">
            <h3 style="margin: 0 0 20px 0; font-size: 16px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-hourglass-half" style="color: #f59e0b;"></i> Pending Requests
            </h3>
            
            <table class="bento-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Reason</th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $pending_q = $conn->query("
                        SELECT l.*, e.name 
                        FROM leave_requests l 
                        JOIN employees e ON l.employee_id = e.id 
                        WHERE l.status='Pending' 
                        ORDER BY l.start_date ASC
                    ");

                    if($pending_q->num_rows > 0) {
                        while($row = $pending_q->fetch_assoc()) {
                            $dates = ($row['start_date'] == $row['end_date']) 
                                ? date('M d', strtotime($row['start_date'])) 
                                : date('M d', strtotime($row['start_date'])) . ' to ' . date('M d', strtotime($row['end_date']));

                            echo "<tr>
                                <td style='font-weight:700;'>".$row['name']."</td>
                                <td>".$row['leave_type']."</td>
                                <td>$dates</td>
                                <td style='max-width:250px; color:#64748b; font-size:13px;'>".$row['reason']."</td>
                                <td style='text-align: center;'>
                                    <form method='POST' style='display:inline-flex; gap:10px; margin:0;'>
                                        <input type='hidden' name='request_id' value='".$row['id']."'>
                                        <button type='submit' name='update_request' value='Approved' class='btn' style='background:#dcfce7; color:#166534; padding:8px 15px; font-size:12px;'>
                                            <i class='fas fa-check'></i> Approve
                                        </button>
                                        <button type='submit' name='update_request' value='Rejected' class='btn' style='background:#fee2e2; color:#991b1b; padding:8px 15px; font-size:12px;'>
                                            <i class='fas fa-times'></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center; padding:40px; color:#94a3b8;'>No pending requests! 🎉</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="bento-card">
            <h3 style="margin: 0 0 20px 0; font-size: 16px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-history" style="color: #4361ee;"></i> Recent History
            </h3>
            
            <table class="bento-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Dates</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $history_q = $conn->query("
                        SELECT l.*, e.name 
                        FROM leave_requests l 
                        JOIN employees e ON l.employee_id = e.id 
                        WHERE l.status!='Pending' 
                        ORDER BY l.id DESC LIMIT 10
                    ");

                    if($history_q->num_rows > 0) {
                        while($row = $history_q->fetch_assoc()) {
                            $status_class = ($row['status'] == 'Approved') ? 'badge-success' : 'badge-danger';
                            $dates = ($row['start_date'] == $row['end_date']) 
                                ? date('M d, Y', strtotime($row['start_date'])) 
                                : date('M d', strtotime($row['start_date'])) . ' - ' . date('M d, Y', strtotime($row['end_date']));

                            echo "<tr>
                                <td style='font-weight:600;'>".$row['name']."</td>
                                <td>".$row['leave_type']."</td>
                                <td><span class='badge $status_class'>".$row['status']."</span></td>
                                <td style='color:#64748b;'>$dates</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' style='text-align:center; padding:30px; color:#94a3b8;'>No history found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

</body>
</html>