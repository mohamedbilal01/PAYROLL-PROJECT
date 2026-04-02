<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { header("Location: index.php"); exit(); }
include 'db.php';

// Handle Delete
if(isset($_POST['delete_emp'])) {
    $id = $_POST['emp_id'];
    $conn->query("DELETE FROM employees WHERE id='$id'");
    $conn->query("DELETE FROM attendance WHERE employee_id='$id'");
    $conn->query("DELETE FROM payroll WHERE employee_id='$id'");
    $msg = "Employee deleted successfully.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Staff | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; padding: 0; background: #f8fafc; font-family: 'Inter', sans-serif; }
        .sidebar { position: fixed !important; left: 0; top: 0; width: 260px !important; height: 100vh; z-index: 1000; }
        .main-content { margin-left: 260px !important; padding: 40px; width: calc(100% - 260px) !important; box-sizing: border-box; }
        .card { background: white; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; font-size: 11px; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; }
        .btn-del { color: #ef4444; border: none; background: none; cursor: pointer; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <h1>Manage Staff</h1>
        <div class="card">
            <table>
                <thead><tr><th>Name</th><th>Role</th><th>Job Title</th><th>Email</th><th>Action</th></tr></thead>
                <tbody>
                    <?php
                    $res = $conn->query("SELECT * FROM employees ORDER BY id DESC");
                    while($row = $res->fetch_assoc()) {
                        echo "<tr>
                            <td><b>".$row['name']."</b><br><span style='font-size:12px; color:#64748b;'>@".$row['username']."</span></td>
                            <td>".strtoupper($row['role'])."</td>
                            <td>".$row['job_title']."</td>
                            <td>".$row['email']."</td>
                            <td>
                                <form method='POST' onsubmit='return confirm(\"Are you sure?\");'>
                                    <input type='hidden' name='emp_id' value='".$row['id']."'>
                                    <button type='submit' name='delete_emp' class='btn-del'>Delete</button>
                                </form>
                            </td>
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