<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { header("Location: index.php"); exit(); }
include 'db.php';

$msg = "";
$msg_type = "";

if (isset($_GET['action']) && $_GET['action'] == 'email' && isset($_GET['id'])) {
    $pay_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT p.*, e.name, e.email FROM payroll p JOIN employees e ON p.employee_id = e.id WHERE p.id = ?");
    $stmt->bind_param("i", $pay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $to = $data['email'];
        $month = isset($data['month']) ? $data['month'] : '';
        $year = isset($data['year']) ? $data['year'] : '';
        $amount = isset($data['net_pay']) ? $data['net_pay'] : $data['net_salary'];
        
        $subject = "Your Payslip for $month $year - NexusHR";
        $message = "Hello " . $data['name'] . ",\n\n";
        $message .= "Your payslip for the period of $month $year has been processed.\n";
        $message .= "Net Payable Amount: Rs. " . number_format((float)$amount) . "\n\n";
        $message .= "You can view and download your full detailed payslip by logging into the NexusHR portal.\n\n";
        $message .= "Best Regards,\nHR Department";
        
        require_once 'PHPMailer/src/Exception.php';
        require_once 'PHPMailer/src/PHPMailer.php';
        require_once 'PHPMailer/src/SMTP.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            //Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            //Recipients
            $mail->setFrom(SMTP_USER, 'NexusHR Payroll');
            $mail->addAddress($to, $data['name']);

            //Content
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body    = $message;

            $mail->send();
            $_SESSION['msg'] = "Payslip successfully sent to " . htmlspecialchars($data['name']) . " (" . htmlspecialchars($to) . ")";
            $_SESSION['msg_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['msg'] = "Message could not be sent. Mailer Error: " . $mail->ErrorInfo;
            $_SESSION['msg_type'] = "error";
        }
    } else {
        $_SESSION['msg'] = "Record not found.";
        $_SESSION['msg_type'] = "error";
    }
    
    $f_month = isset($_GET['filter_month']) ? urlencode($_GET['filter_month']) : '';
    $f_status = isset($_GET['filter_status']) ? urlencode($_GET['filter_status']) : '';
    header("Location: payroll_reports.php?filter_month=$f_month&filter_status=$f_status");
    exit();
} elseif (isset($_GET['action']) && $_GET['action'] == 'email_all' && isset($_GET['month'])) {
    $filter_month = $_GET['month'];
    list($y, $m) = explode('-', $filter_month);
    $month_name = date('F', mktime(0, 0, 0, $m, 10));

    $stmt = $conn->prepare("SELECT p.*, e.name, e.email FROM payroll p JOIN employees e ON p.employee_id = e.id WHERE p.month = ? AND p.year = ?");
    $stmt->bind_param("ss", $month_name, $y);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        require_once 'PHPMailer/src/Exception.php';
        require_once 'PHPMailer/src/PHPMailer.php';
        require_once 'PHPMailer/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $success_count = 0;
        $fail_count = 0;
        $error_msg = "";

        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            // Keep connection open for bulk sending
            $mail->SMTPKeepAlive = true; 
            $mail->setFrom(SMTP_USER, 'NexusHR Payroll');

            while ($data = $result->fetch_assoc()) {
                try {
                    $to = $data['email'];
                    $amount = isset($data['net_pay']) ? $data['net_pay'] : $data['net_salary'];
                    
                    $subject = "Your Payslip for $month_name $y - NexusHR";
                    $message = "Hello " . $data['name'] . ",\n\n";
                    $message .= "Your payslip for the period of $month_name $y has been processed.\n";
                    $message .= "Net Payable Amount: Rs. " . number_format((float)$amount) . "\n\n";
                    $message .= "You can view and download your full detailed payslip by logging into the NexusHR portal.\n\n";
                    $message .= "Best Regards,\nHR Department";

                    $mail->clearAllRecipients();
                    $mail->addAddress($to, $data['name']);
                    
                    $mail->isHTML(false);
                    $mail->Subject = $subject;
                    $mail->Body    = $message;

                    $mail->send();
                    $success_count++;
                } catch (\Exception $e) {
                    $fail_count++;
                    $error_msg = $mail->ErrorInfo;
                }
            }
            $mail->smtpClose();

            if ($success_count > 0 && $fail_count == 0) {
                $_SESSION['msg'] = "Successfully sent $success_count payslips to staff!";
                $_SESSION['msg_type'] = "success";
            } elseif ($success_count > 0 && $fail_count > 0) {
                $_SESSION['msg'] = "Sent $success_count payslips, but failed to send $fail_count. Last Error: $error_msg";
                $_SESSION['msg_type'] = "error";
            } else {
                $_SESSION['msg'] = "Failed to send $fail_count payslips. Error: $error_msg";
                $_SESSION['msg_type'] = "error";
            }

        } catch (\Exception $e) {
            $_SESSION['msg'] = "Bulk mailer could not connect or authenticate. Check your SMTP credentials in db.php.";
            $_SESSION['msg_type'] = "error";
        }
    } else {
        $_SESSION['msg'] = "No generated payslips found for $month_name $y to email.";
        $_SESSION['msg_type'] = "error";
    }
    
    $f_month = isset($_GET['filter_month']) ? urlencode($_GET['filter_month']) : '';
    $f_status = isset($_GET['filter_status']) ? urlencode($_GET['filter_status']) : '';
    header("Location: payroll_reports.php?filter_month=$f_month&filter_status=$f_status");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Payroll History | NexusHR</title>
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
        
        .search-container { position: relative; margin-bottom: 25px; }
        .search-icon { position: absolute; left: 18px; top: 16px; color: #94a3b8; }
        .search-input { width: 100%; padding: 15px 15px 15px 45px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 15px; font-family: 'Inter'; transition: 0.2s; box-sizing: border-box; background: #f8fafc; }
        .search-input:focus { border-color: #4361ee; outline: none; background: white; box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1); }

        table { width: 100%; border-collapse: collapse; }
        th { padding: 15px; text-align: left; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        td { padding: 18px 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; font-weight: 500; }
        
        .net-pay { color: #16a34a; font-weight: 800; font-size: 15px; }
        .btn-pdf { color: #4361ee; background: #eff6ff; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-pdf:hover { background: #dbeafe; }
        .btn-email { color: #ea4335; background: #fef2f2; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; margin-left: 5px; }
        .btn-email:hover { background: #fee2e2; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="premium-banner">
            <div class="banner-icon"><i class="fas fa-chart-line"></i></div>
            <div style="position: relative; z-index: 10;">
                <h1 style="margin: 0 0 5px 0; font-size: 26px; font-weight: 800;">Payroll History</h1>
                <p style="margin: 0; color: #94a3b8; font-size: 15px;">Search and export past salary disbursements.</p>
            </div>
        </div>

        <?php if(isset($_SESSION['msg'])): ?>
            <div class="alert alert-<?php echo $_SESSION['msg_type']; ?>">
                <i class="fas <?php echo $_SESSION['msg_type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $_SESSION['msg']; ?>
            </div>
            <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
        <?php elseif($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <i class="fas <?php echo $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <?php
            $filter_month = !empty($_GET['filter_month']) ? $_GET['filter_month'] : date('Y-m');
            $filter_status = !empty($_GET['filter_status']) ? $_GET['filter_status'] : 'all';
            ?>
            <form method="GET" style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <div style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Select Month</div>
                    <input type="month" name="filter_month" value="<?php echo htmlspecialchars($filter_month); ?>" class="search-input" style="padding: 12px 15px; margin-bottom: 0;">
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <div style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Payment Status</div>
                    <select name="filter_status" class="search-input" style="padding: 12px 15px; margin-bottom: 0; appearance: auto;">
                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Employees</option>
                        <option value="paid" <?php echo $filter_status == 'paid' ? 'selected' : ''; ?>>Paid Only</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                    </select>
                </div>
                <div style="display: flex; align-items: flex-end; gap: 10px;">
                    <button type="submit" class="btn-pdf" style="padding: 12px 25px; font-size: 14px; height: 47px; border: none; cursor: pointer; background: #1e293b; color: white;"><i class="fas fa-filter"></i> Apply Filter</button>
                    <a href="?action=email_all&month=<?php echo $filter_month; ?>&filter_month=<?php echo urlencode($filter_month); ?>&filter_status=<?php echo urlencode($filter_status); ?>" class="btn-pdf" style="padding: 12px 25px; font-size: 14px; height: 47px; border: none; cursor: pointer; background: #ea4335; color: white; display:flex; align-items:center; box-sizing: border-box;" onclick="return confirm('Are you sure you want to email payslips to ALL employees for <?php echo date('F Y', strtotime($filter_month)); ?>? This will take a few seconds.')"><i class="fas fa-paper-plane"></i> Bulk Email Payslips</a>
                </div>
            </form>

            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Quick search by Employee Name..." onkeyup="filterTable()">
            </div>

            <table id="payrollTable">
                <thead><tr><th>Employee</th><th>Role</th><th>Status</th><th>Net Paid</th><th>Action</th></tr></thead>
                <tbody>
                    <?php
                    list($y, $m) = explode('-', $filter_month);
                    $month_name = date('F', mktime(0, 0, 0, $m, 10));

                    $query = "SELECT e.id as emp_id, e.name, e.email, e.job_title as role, 
                              p.id as pay_id, p.net_pay, p.month, p.year
                              FROM employees e 
                              LEFT JOIN payroll p ON e.id = p.employee_id AND p.month = '$month_name' AND p.year = '$y'
                              WHERE e.role != 'admin'";

                    if ($filter_status === 'paid') {
                        $query .= " AND p.id IS NOT NULL";
                    } elseif ($filter_status === 'pending') {
                        $query .= " AND p.id IS NULL";
                    }
                    
                    $query .= " ORDER BY e.name ASC";

                    $pay_q = $conn->query($query);
                    
                    if($pay_q && $pay_q->num_rows > 0) {
                        while($p = $pay_q->fetch_assoc()) {
                            $is_paid = !empty($p['pay_id']);
                            
                            $amount = isset($p['net_pay']) ? $p['net_pay'] : 0;
                            
                            $status_badge = $is_paid 
                                ? "<span style='background:#dcfce7; color:#166534; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:5px;'><i class='fas fa-check-circle'></i> Paid</span>"
                                : "<span style='background:#fef2f2; color:#991b1b; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:5px;'><i class='fas fa-clock'></i> Pending</span>";
                                
                            $net_paid_display = $is_paid ? "₹".number_format((float)$amount) : "<span style='color:#94a3b8; font-weight:500;'>--</span>";
                            
                            $action_buttons = "";
                            if ($is_paid) {
                                $action_buttons = "
                                    <a href='payslip.php?id=".$p['pay_id']."' target='_blank' class='btn-pdf'><i class='fas fa-file-pdf'></i> PDF</a>
                                    <a href='?action=email&id=".$p['pay_id']."&filter_month=".urlencode($filter_month)."&filter_status=".urlencode($filter_status)."' class='btn-email' onclick='return confirm(\"Send payslip via SMTP email to ".$p['name']."?\")'><i class='fas fa-paper-plane'></i> Email</a>
                                ";
                            } else {
                                $action_buttons = "<a href='process_salary.php?employee_id=".$p['emp_id']."&payroll_month=".$filter_month."' class='btn-pdf' style='background:#f59e0b; color:white; border:none;'><i class='fas fa-calculator'></i> Run Payroll</a>";
                            }

                            echo "<tr>
                                <td style='font-weight:700; color:#0f172a;'>".$p['name']." <div style='font-size:12px; color:#94a3b8; font-weight:400;'>".$p['email']."</div></td>
                                <td style='text-transform:capitalize; color:#64748b;'>".$p['role']."</td>
                                <td>".$status_badge."</td>
                                <td class='net-pay'>".$net_paid_display."</td>
                                <td style='white-space:nowrap;'>".$action_buttons."</td>
                            </tr>";
                        }
                    } else { echo "<tr><td colspan='5' style='text-align:center; padding:40px; color:#94a3b8;'>No employees found for the selected filter.</td></tr>"; }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Simple real-time search filter
function filterTable() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let rows = document.getElementById("payrollTable").getElementsByTagName("tr");
    for (let i = 1; i < rows.length; i++) {
        let text = rows[i].innerText.toLowerCase();
        rows[i].style.display = text.includes(input) ? "" : "none";
    }
}
</script>
</body>
</html>