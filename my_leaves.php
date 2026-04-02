<?php
session_start();
if (!isset($_SESSION['role'])) { header("Location: index.php"); exit(); }
include 'db.php';
$user_id = $_SESSION['user_id'];

if (isset($_POST['apply'])) {
    $type = $_POST['type']; $start = $_POST['start']; $end = $_POST['end']; $reason = $_POST['reason'];
    $conn->query("INSERT INTO leave_requests (employee_id, leave_type, reason, start_date, end_date) VALUES ('$user_id', '$type', '$reason', '$start', '$end')");
    $msg = "Request submitted successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Apply Leave</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main-content" style="padding:40px;">
        <h1 style="margin-bottom:20px;">Leave Application</h1>
        <?php if(isset($msg)) echo "<div style='background:#dcfce7; color:#166534; padding:15px; border-radius:8px; margin-bottom:20px;'>$msg</div>"; ?>
        
        <div style="background:white; padding:30px; border-radius:12px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
            <form method="POST">
                <label style="display:block; font-weight:600; margin-bottom:5px;">Leave Type</label>
                <select name="type" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; margin-bottom:15px;"><option>Sick Leave</option><option>Casual Leave</option></select>
                
                <label style="display:block; font-weight:600; margin-bottom:5px;">Dates</label>
                <div style="display:flex; gap:15px; margin-bottom:15px;">
                    <input type="date" name="start" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                    <input type="date" name="end" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                </div>
                
                <label style="display:block; font-weight:600; margin-bottom:5px;">Reason</label>
                <input type="text" name="reason" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; margin-bottom:20px;">
                
                <button name="apply" style="padding:12px 30px; background:#4361ee; color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer;">Submit Request</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>