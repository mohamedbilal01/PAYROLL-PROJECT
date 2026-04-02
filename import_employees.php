<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { 
    header("Location: index.php"); exit(); 
}
include 'db.php';

$current_role = strtolower(trim($_SESSION['role'])); 

$msg = "";
$msg_type = "";
$success_count = 0;
$error_count = 0;
$error_details = [];

if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $filename = $_FILES['csv_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if ($ext == 'csv') {
            $file = fopen($_FILES['csv_file']['tmp_name'], "r");
            
            // Skip the first row (header)
            $header = fgetcsv($file);
            
            while (($data = fgetcsv($file, 10000, ",")) !== FALSE) {
                // Ensure the row has enough columns (expecting 8 based on sample)
                if (count($data) >= 8) {
                    $name = $conn->real_escape_string(trim($data[0]));
                    $email = $conn->real_escape_string(trim($data[1]));
                    $mobile = $conn->real_escape_string(trim($data[2]));
                    $role_val = strtolower($conn->real_escape_string(trim($data[3])));
                    $job = $conn->real_escape_string(trim($data[4]));
                    $salary = (int)trim($data[5]);
                    $username = $conn->real_escape_string(trim($data[6]));
                    $password = $conn->real_escape_string(trim($data[7])); // Storing as plaintext based on add_employee.php

                    // Validate role for HR
                    if ($current_role == 'hr' && $role_val == 'admin') {
                        $error_count++;
                        $error_details[] = "Row for $name: HR cannot create Administrators.";
                        continue;
                    }

                    // Check if username exists
                    $check = $conn->query("SELECT id FROM employees WHERE username='$username'");
                    if ($check->num_rows > 0) {
                        $error_count++;
                        $error_details[] = "Row for $name: Username '$username' is already taken.";
                        continue;
                    }
                    
                    // Simple validation
                    if(empty($name) || empty($email) || empty($username) || empty($password)) {
                        $error_count++;
                        $error_details[] = "Row for $name: Missing required fields.";
                        continue;
                    }

                    $sql = "INSERT INTO employees (name, email, mobile, role, job_title, base_salary, username, password, is_imported) 
                            VALUES ('$name', '$email', '$mobile', '$role_val', '$job', '$salary', '$username', '$password', 1)";
                    
                    if ($conn->query($sql)) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $error_details[] = "Row for $name: DB Error - " . $conn->error;
                    }
                }
            }
            fclose($file);
            
            if ($success_count > 0) {
                $msg = "Successfully imported $success_count staff members.";
                $msg_type = "success";
            }
            if ($error_count > 0) {
                if(empty($msg)) $msg = "Import finished with errors.";
                else $msg .= " However, $error_count records failed.";
                $msg_type = ($success_count > 0) ? "warning" : "error";
            }
            
        } else {
            $msg = "Invalid file format. Please upload a CSV file.";
            $msg_type = "error";
        }
    } else {
        $msg = "Please select a valid file.";
        $msg_type = "error";
    }
}

// Handle Sample CSV Download (Blank Template)
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="NexusHR_Blank_Template.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Full Name', 'Email Address', 'Mobile Number', 'Role (employee/hr/admin)', 'Job Title', 'Base Salary (Numeric)', 'Username', 'Password']);
    fclose($output);
    exit();
}

// Handle Delete Imported Data
if (isset($_GET['delete_imported'])) {
    $del_count = 0;
    $imported_emps = $conn->query("SELECT id FROM employees WHERE is_imported=1");
    if ($imported_emps && $imported_emps->num_rows > 0) {
        while($emp = $imported_emps->fetch_assoc()) {
            $del_id = $emp['id'];
            $conn->query("DELETE FROM attendance WHERE employee_id='$del_id'");
            $conn->query("DELETE FROM leave_requests WHERE employee_id='$del_id'");
            $conn->query("DELETE FROM payroll WHERE employee_id='$del_id'");
            // We do not delete from other tables like users if they don't exist
        }
        if($conn->query("DELETE FROM employees WHERE is_imported=1")) {
            $del_count = $imported_emps->num_rows;
            $msg = "Successfully deleted $del_count imported staff members.";
            $msg_type = "success";
        } else {
            $msg = "Failed to delete imported data: " . $conn->error;
            $msg_type = "error";
        }
    } else {
        $msg = "No imported data found to delete.";
        $msg_type = "warning";
    }
}

// Handle Auto-Generated Demo Data Download
if (isset($_GET['download_demo'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="NexusHR_Demo_Employees.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, ['Full Name', 'Email Address', 'Mobile Number', 'Role (employee/hr/admin)', 'Job Title', 'Base Salary (Numeric)', 'Username', 'Password']);
    
    $first_names = ["James", "Mary", "Michael", "Patricia", "Robert", "Jennifer", "John", "Linda", "David", "Elizabeth", "William", "Barbara", "Richard", "Susan", "Joseph", "Jessica", "Thomas", "Sarah", "Charles", "Karen", "Christopher", "Lisa", "Daniel", "Nancy", "Matthew"];
    $last_names = ["Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez", "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson", "Thomas", "Taylor", "Moore", "Jackson", "Martin", "Lee", "Perez", "Thompson", "White", "Harris"];
    $roles = ["employee", "employee", "employee", "employee", "hr"]; // mostly employees, some hr
    $jobs = ["Software Engineer", "Marketing Specialist", "Sales Representative", "Financial Analyst", "Operations Coordinator", "Product Designer", "Customer Support", "Project Manager"];
    
    for ($i = 0; $i < 25; $i++) {
        $fname = $first_names[array_rand($first_names)];
        $lname = $last_names[array_rand($last_names)];
        $fullname = "$fname $lname";
        $email = strtolower($fname . "." . $lname . "@nexushr.com");
        $mobile = "9" . rand(100000000, 999999999);
        $role = $roles[array_rand($roles)];
        $title = ($role == 'hr') ? "HR Associate" : $jobs[array_rand($jobs)];
        $salary = rand(40, 150) * 1000;
        $username = strtolower(substr($fname, 0, 1) . $lname) . rand(1, 99);
        $password = "Pass@" . rand(1000, 9999);
        
        fputcsv($output, [$fullname, $email, $mobile, $role, $title, $salary, $username, $password]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Import Staff | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .premium-banner { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 16px; padding: 35px 40px; color: white; margin-bottom: 30px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between; gap: 20px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3); }
        .premium-banner::after { content: ''; position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; background: rgba(67, 97, 238, 0.15); border-radius: 50%; filter: blur(40px); }
        .banner-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 28px; color: #60a5fa; border: 1px solid rgba(255,255,255,0.1); }
        
        .upload-zone { border: 2px dashed #cbd5e1; padding: 50px 30px; border-radius: 16px; text-align: center; margin-bottom: 25px; background: #f8fafc; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
        .upload-zone:hover { border-color: #4361ee; background: #eff6ff; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(67, 97, 238, 0.1); }
        .upload-zone:active { transform: translateY(0); }
        .upload-zone i { font-size: 54px; color: #94a3b8; margin-bottom: 20px; transition: 0.3s; }
        .upload-zone:hover i { color: #4361ee; transform: scale(1.05); }
        
        /* Hide the native file input cleanly */
        input[type="file"] { opacity: 0; position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer; }
        
        .file-name-display { display: none; margin-top: 15px; font-weight: 700; color: #0f172a; background: white; padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        
        .btn-upload { background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%); color: white; width: 100%; justify-content: center; padding: 16px; font-size: 16px; font-weight: 700; border-radius: 12px; border: none; cursor: pointer; box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3); transition: 0.3s; }
        .btn-upload:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4); }
        
        .notes-card { background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; border-left: 4px solid #4361ee; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .notes-card h4 { margin: 0 0 15px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        .notes-list { margin: 0; padding-left: 0; list-style: none; }
        .notes-list li { position: relative; padding-left: 24px; font-size: 14px; color: #475569; margin-bottom: 12px; line-height: 1.6; }
        .notes-list li::before { content: '\f058'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; left: 0; color: #10b981; }
        .notes-list li code { background: #f1f5f9; color: #0f172a; padding: 2px 6px; border-radius: 4px; font-weight: 600; font-size: 12px; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <div class="premium-banner">
            <div style="display:flex; align-items:center; gap:20px; position:relative; z-index:10;">
                <div class="banner-icon"><i class="fas fa-users-cog"></i></div>
                <div>
                    <h1 style="margin: 0 0 5px 0; font-size: 26px; font-weight: 800;">Bulk Import Staff</h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 15px;">Upload a CSV file to seamlessly onboard multiple employees at once.</p>
                </div>
            </div>
            <a href="manage_employees.php" class="btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px); z-index:10;">
                <i class="fas fa-arrow-left"></i> Back to Directory
            </a>
        </div>

        <?php if($msg): ?>
            <div style="padding:16px 20px; border-radius:12px; margin-bottom:30px; font-weight:600; font-size: 15px; display:flex; align-items:center; gap:12px; 
                <?php echo ($msg_type == 'success') ? 'background:#ecfdf5; color:#065f46; border: 1px solid #a7f3d0;' : 
                (($msg_type == 'error') ? 'background:#fef2f2; color:#991b1b; border: 1px solid #fecaca;' : 'background:#fefce8; color:#a16207; border: 1px solid #fef08a;'); ?>">
                <i class="fas <?php echo ($msg_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>" style="font-size: 20px;"></i> 
                <?php echo $msg; ?>
            </div>
            
            <?php if(!empty($error_details)): ?>
                <div class="notes-card" style="border-left-color: #ef4444; background: #fff1f2; margin-bottom: 30px;">
                    <h4 style="color: #991b1b;"><i class="fas fa-times-circle"></i> Error Details</h4>
                    <ul class="notes-list" style="margin-bottom: 0;">
                        <?php foreach($error_details as $err): ?>
                            <li><i class="fas fa-bug" style="color: #ef4444; position:absolute; left:0; top:4px;"></i> <?php echo $err; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
            
            <!-- Left Side: Upload Zone -->
            <div class="bento-card" style="padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); height: fit-content;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px;">
                    <h3 style="margin: 0; color: #0f172a; font-weight: 800; font-size: 20px;">Upload File</h3>
                    <div style="display:flex; gap: 10px;">
                        <a href="?delete_imported=1" class="btn" style="background: #ef4444; color: white; font-weight: 700; font-size: 13px; padding: 8px 16px; border-radius: 8px; border:none; box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);" onclick="return confirm('Are you sure you want to permanently delete ALL imported staff data? This cannot be undone.');">
                            <i class="fas fa-trash-alt"></i> Delete Imported
                        </a>
                        <a href="?download_demo=1" class="btn" style="background: #10b981; color: white; font-weight: 700; font-size: 13px; padding: 8px 16px; border-radius: 8px; border:none; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);">
                            <i class="fas fa-magic"></i> Demo Data
                        </a>
                        <a href="?download_sample=1" class="btn btn-dark" style="font-weight: 700; font-size: 13px; padding: 8px 16px; border-radius: 8px;">
                            <i class="fas fa-file-excel"></i> Blank Template
                        </a>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-zone" id="dropZone">
                        <input type="file" name="csv_file" id="csvFileInput" accept=".csv" required>
                        <i class="fas fa-cloud-upload-alt" id="uploadIcon"></i>
                        <h4 style="margin: 0 0 8px 0; color: #1e293b; font-size: 18px; font-weight: 700;">Drag and drop your CSV here</h4>
                        <p style="margin: 0; color: #64748b; font-size: 14px;">or click anywhere in this box to browse</p>
                        
                        <div id="fileDisplay" class="file-name-display" style="display:none;">
                            <i class="fas fa-file-csv" style="color: #10b981; font-size: 18px; margin: 0;"></i>
                            <span id="fileNameText">filename.csv</span>
                        </div>
                    </div>

                    <button type="submit" name="import_csv" class="btn-upload">
                        <i class="fas fa-rocket"></i> Import Staff Data
                    </button>
                </form>
            </div>
            
            <!-- Right Side: Instructions -->
            <div>
                <div class="notes-card" style="margin-bottom: 20px;">
                    <h4><i class="fas fa-info-circle" style="color:#4361ee;"></i> Import Guidelines</h4>
                    <ul class="notes-list">
                        <li>The first row of the CSV is exactly considered a header and will be skipped.</li>
                        <li><strong>Role</strong> must be exactly one of: <code>employee</code>, <code>hr</code>, or <code>admin</code>.</li>
                        <li><strong>Username</strong> must be unique across the entire database.</li>
                        <li>HR accounts are restricted from creating <code>admin</code> roles via CSV.</li>
                        <li>Passwords should be provided in standard plain text.</li>
                    </ul>
                </div>
                
                <div class="notes-card" style="border-left-color: #f59e0b; background: linear-gradient(to right, #fffbeb, white);">
                    <h4 style="color:#b45309;"><i class="fas fa-lightbulb" style="color:#f59e0b;"></i> Pro Tip</h4>
                    <p style="margin:0; font-size:14px; color:#78350f; line-height: 1.6;">Use the <strong>Excel/CSV Template</strong> provided. It contains a securely embedded UTF-8 BOM ensuring all data opens flawlessly in Microsoft Excel.</p>
                </div>
            </div>

        </div>
        
    </div>
</div>

<script>
    // Make the upload zone interactive
    const fileInput = document.getElementById('csvFileInput');
    const fileDisplay = document.getElementById('fileDisplay');
    const fileNameText = document.getElementById('fileNameText');
    const uploadIcon = document.getElementById('uploadIcon');
    const dropZone = document.getElementById('dropZone');

    fileInput.addEventListener('change', function(e) {
        if(e.target.files.length > 0) {
            fileNameText.textContent = e.target.files[0].name;
            fileDisplay.style.display = 'inline-flex';
            uploadIcon.style.display = 'none';
            dropZone.style.background = '#f0fdf4';
            dropZone.style.borderColor = '#4ade80';
        } else {
            fileDisplay.style.display = 'none';
            uploadIcon.style.display = 'block';
            dropZone.style.background = '#f8fafc';
            dropZone.style.borderColor = '#cbd5e1';
        }
    });
</script>

</body>
</html>
