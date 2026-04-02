<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['role'])) { header("Location: index.php"); exit(); }
include 'db.php';

$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Payslips | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        /* --- LAYOUT FIX --- */
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .sidebar { position: fixed !important; left: 0; top: 0; width: 260px !important; height: 100vh; z-index: 1000; }
        .main-content { margin-left: 260px !important; padding: 40px; width: calc(100% - 260px) !important; box-sizing: border-box; min-height: 100vh; }
        @media (max-width: 900px) { .sidebar { width: 70px !important; } .main-content { margin-left: 70px !important; width: calc(100% - 70px) !important; } }

        /* Card & Table */
        .card { background: white; border-radius: 12px; padding: 30px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; font-size: 11px; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 20px 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; }
        
        .badge-paid { background: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .btn-pdf { color: #4361ee; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-pdf:hover { color: #3a0ca3; text-decoration: underline; }
        .text-income { color: #15803d; font-weight: 600; }
        .text-deduct { color: #b91c1c; font-weight: 600; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1 style="color:#0f172a; margin-bottom: 10px;">My Payslips</h1>
        <p style="color:#64748b; margin-bottom: 30px;">View and download your monthly salary statements.</p>

        <div class="card">
            <table border="0">
                <thead>
                    <tr>
                        <th width="25%">Pay Period</th>
                        <th width="15%">Base Salary</th>
                        <th width="15%">Bonus</th>
                        <th width="15%">Deductions</th>
                        <th width="15%">Net Pay</th>
                        <th width="10%">Status</th>
                        <th width="10%">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $q = $conn->query("SELECT * FROM payroll WHERE employee_id='$user_id' ORDER BY id DESC");
                    
                    if($q->num_rows > 0) {
                        while($row = $q->fetch_assoc()) {
                            // SAFETY CHECK: Use '0' if column is missing or empty
                            $base   = number_format($row['base_salary'] ?? 0);
                            $bonus  = number_format($row['bonus'] ?? 0);
                            $deduct = number_format($row['deductions'] ?? 0);
                            $net    = number_format($row['net_pay'] ?? 0);
                            
                            echo "<tr>
                                <td>
                                    <div style='font-weight:700; color:#0f172a;'>".$row['month']." ".$row['year']."</div>
                                    <div style='font-size:12px; color:#94a3b8;'>Salary ID: #".$row['id']."</div>
                                </td>
                                <td>₹$base</td>
                                <td class='text-income'>+ ₹$bonus</td>
                                <td class='text-deduct'>- ₹$deduct</td>
                                <td style='font-weight:700; font-size:16px; color:#0f172a;'>₹$net</td>
                                <td><span class='badge-paid'>PAID</span></td>
                                <td>
                                    <a href='payslip.php?id=".$row['id']."' target='_blank' class='btn-pdf'>
                                        <i class='fas fa-download'></i> PDF
                                    </a>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align:center; padding:40px; color:#94a3b8;'>No payslips generated yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>