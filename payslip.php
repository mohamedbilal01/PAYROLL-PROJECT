<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include 'db.php';

// 1. Security Check
if (!isset($_SESSION['role'])) { header("Location: index.php"); exit(); }

// 2. Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) { die("Invalid Payslip Request"); }
$payslip_id = (int)$_GET['id'];

// 3. Fetch Data
$q = $conn->query("
    SELECT p.*, e.name, e.job_title, e.email, e.mobile 
    FROM payroll p 
    JOIN employees e ON p.employee_id = e.id 
    WHERE p.id = '$payslip_id'
");

if($q->num_rows == 0) { die("Payslip not found."); }
$data = $q->fetch_assoc();

// 4. Access Control
$current_role = strtolower($_SESSION['role']);
$current_user = $_SESSION['user_id'];

if ($current_role != 'admin' && $current_role != 'hr' && $data['employee_id'] != $current_user) {
    die("⛔ Access Denied.");
}

// 5. REVERSE-ENGINEER THE ATTENDANCE DAYS
// Figure out total working days for this specific month (Mon-Fri)
$y = $data['year'];
$m = date('m', strtotime($data['month']));
$working_days = 0;
$start_date = new DateTime("$y-$m-01");
$end_date = new DateTime("$y-$m-" . $start_date->format('t'));

while ($start_date <= $end_date) {
    if ($start_date->format('N') < 6) { $working_days++; }
    $start_date->modify('+1 day');
}

// Calculate missed days based on the saved deduction money
$per_day_pay = ($working_days > 0) ? ($data['basic_salary'] / $working_days) : 0;
$missed_days = ($per_day_pay > 0) ? round($data['deductions'] / $per_day_pay, 1) : 0;
$paid_days = $working_days - $missed_days;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Payslip #<?php echo str_pad($data['id'], 6, '0', STR_PAD_LEFT); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #525659; margin: 0; padding: 40px; font-family: 'Inter', sans-serif; -webkit-print-color-adjust: exact; }
        
        .page-container { background: white; width: 210mm; margin: 0 auto; padding: 15mm 20mm; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border-radius: 5px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; }
        .brand h1 { margin: 0; color: #0f172a; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; display: flex; align-items: center; gap: 6px; }
        .brand span { color: #4361ee; }
        .company-info { text-align: right; font-size: 11px; color: #64748b; line-height: 1.6; }
        .company-name { font-weight: 700; color: #0f172a; font-size: 12px; text-transform: uppercase; }

        .doc-title { text-align: center; margin-bottom: 30px; }
        .doc-title span { background: #eff6ff; color: #1e40af; padding: 8px 20px; border-radius: 6px; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; border: 1px solid #dbeafe; }

        .emp-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin-bottom: 40px; }
        .field-group { display: flex; flex-direction: column; }
        .label { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 4px; letter-spacing: 0.5px; }
        .value { font-size: 14px; font-weight: 700; color: #0f172a; }

        /* Added special styling for the new attendance summary box */
        .attendance-summary { display: flex; gap: 20px; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #cbd5e1; grid-column: span 2; }
        .att-pill { background: white; border: 1px solid #e2e8f0; padding: 8px 15px; border-radius: 6px; font-size: 12px; font-weight: 700; color: #334155; }
        .att-pill span { color: #4361ee; margin-left: 5px; font-size: 13px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { text-align: left; background: #0f172a; color: white; padding: 12px 15px; font-size: 11px; text-transform: uppercase; font-weight: 600; }
        th:last-child { text-align: right; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; }
        td:last-child { text-align: right; font-family: 'Inter', monospace; font-weight: 600; color: #0f172a; }
        tr:nth-child(even) td { background-color: #f8fafc; }

        .summary-box { display: flex; justify-content: flex-end; margin-top: 20px; }
        .total-wrapper { width: 280px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .sub-row { display: flex; justify-content: space-between; padding: 10px 15px; font-size: 12px; color: #64748b; border-bottom: 1px solid #e2e8f0; }
        .sub-val { font-weight: 600; color: #334155; }
        .grand-total { background: #4361ee; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
        .grand-label { font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .grand-amount { font-size: 18px; font-weight: 800; }

        .footer { margin-top: 50px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        .footer p { font-size: 10px; color: #94a3b8; margin: 3px 0; }

        .actions { position: fixed; bottom: 30px; right: 30px; display: flex; gap: 10px; z-index: 100; }
        .btn { padding: 12px 24px; border: none; border-radius: 50px; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); transition: transform 0.2s; text-decoration: none; }
        .btn:hover { transform: translateY(-2px); }
        .btn-print { background: #0f172a; color: white; }
        .btn-close { background: white; color: #ef4444; border: 2px solid #fee2e2; }

        @media print {
            body { background: white; padding: 0; }
            .page-container { box-shadow: none; margin: 0; width: 100%; border-radius: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>

    <div class="page-container">
        <div class="header">
            <div class="brand">
                <h1>Nexus <span>HR</span></h1>
            </div>
            <div class="company-info">
                <div class="company-name">NexusHR Tech Pvt Ltd</div>
                123 Innovation Park, Tech City<br>
                Chennai, TN - 600001<br>
                contact@nexushr.com
            </div>
        </div>

        <div class="doc-title">
            <span>Salary Slip / <?php echo date('M Y', strtotime($data['year'].'-'.$data['month'].'-01')); ?></span>
        </div>

        <div class="emp-grid">
            <div class="field-group">
                <span class="label">Employee Name</span>
                <span class="value"><?php echo $data['name']; ?></span>
            </div>
            <div class="field-group">
                <span class="label">Payslip ID</span>
                <span class="value">#PAY-<?php echo str_pad($data['id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="field-group" style="margin-top: 15px;">
                <span class="label">Designation</span>
                <span class="value"><?php echo $data['job_title']; ?></span>
            </div>
            <div class="field-group" style="margin-top: 15px;">
                <span class="label">Payment Date</span>
                <span class="value"><?php echo date('d M Y', strtotime($data['processed_on'])); ?></span>
            </div>

            <div class="attendance-summary">
                <div class="att-pill">Total Working Days: <span><?php echo $working_days; ?></span></div>
                <div class="att-pill" style="border-left: 4px solid #16a34a;">Paid Days: <span style="color:#16a34a;"><?php echo $paid_days; ?></span></div>
                <div class="att-pill" style="border-left: 4px solid #ef4444;">Loss of Pay (LOP): <span style="color:#ef4444;"><?php echo $missed_days; ?></span></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        Basic Salary 
                        <span style="color:#94a3b8; font-size:11px; display:block; margin-top:3px;">
                            (Based on full <?php echo $working_days; ?> working days)
                        </span>
                    </td>
                    <td><span style="color:#16a34a; font-weight:600; font-size:11px; background:#dcfce7; padding:2px 8px; border-radius:10px;">EARNING</span></td>
                    <td>₹<?php echo number_format($data['basic_salary'], 2); ?></td>
                </tr>
                <tr>
                    <td>Performance Bonus</td>
                    <td><span style="color:#16a34a; font-weight:600; font-size:11px; background:#dcfce7; padding:2px 8px; border-radius:10px;">EARNING</span></td>
                    <td>₹<?php echo number_format($data['bonus'], 2); ?></td>
                </tr>
                <tr>
                    <td>
                        Unpaid Leave Deductions
                        <span style="color:#ef4444; font-size:11px; display:block; margin-top:3px;">
                            (Loss of Pay for <?php echo $missed_days; ?> absent days)
                        </span>
                    </td>
                    <td><span style="color:#dc2626; font-weight:600; font-size:11px; background:#fee2e2; padding:2px 8px; border-radius:10px;">DEDUCTION</span></td>
                    <td style="color:#dc2626;">- ₹<?php echo number_format($data['deductions'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="total-wrapper">
                <div class="sub-row">
                    <span>Total Earnings</span>
                    <span class="sub-val">₹<?php echo number_format($data['basic_salary'] + $data['bonus'], 2); ?></span>
                </div>
                <div class="sub-row">
                    <span>Total Deductions</span>
                    <span class="sub-val" style="color:#ef4444;">- ₹<?php echo number_format($data['deductions'], 2); ?></span>
                </div>
                <div class="grand-total">
                    <span class="grand-label">Net Pay</span>
                    <span class="grand-amount">₹<?php echo number_format($data['net_pay'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Generated by NexusHR System on <?php echo date('d-m-Y H:i A'); ?></p>
            <p>This is a computer-generated document. No signature required.</p>
        </div>
    </div>

    <div class="actions">
        <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> Print Payslip</button>
        <button onclick="window.close()" class="btn btn-close"><i class="fas fa-times"></i> Close</button>
    </div>

</body>
</html>