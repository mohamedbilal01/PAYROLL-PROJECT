<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { 
    header("Location: index.php"); exit(); 
}
include 'db.php';

// Normalize Role
$current_role = strtolower(trim($_SESSION['role'])); 

$msg = "";
$msg_type = "";
$show_share = false;
$share_data = [];

if (isset($_POST['add_staff'])) {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $role_val = $_POST['role']; 
    $job = $_POST['job_title'];
    $salary = $_POST['salary'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // --- IMAGE UPLOAD LOGIC ---
    $profile_image = NULL;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // Generate a unique name so files don't overwrite each other
            $profile_image = uniqid('profile_') . '.' . $ext;
            move_uploaded_file($_FILES['profile_image']['tmp_name'], 'uploads/' . $profile_image);
        } else {
            $msg = "Invalid image format. Only JPG, PNG, and WEBP are allowed.";
            $msg_type = "error";
        }
    }

    if ($msg_type != "error") { // Only proceed if image upload didn't fail
        // SECURITY CHECK: HR cannot create Admins.
        if($current_role == 'hr' && $role_val == 'admin') {
            $msg = "Security Alert: HR cannot create Administrators.";
            $msg_type = "error";
        } else {
            $check = $conn->query("SELECT id FROM employees WHERE username='$username'");
            if($check->num_rows > 0) {
                $msg = "Error: Username '$username' is already taken.";
                $msg_type = "error";
            } else {
                // Include profile_image in the insert query
                $sql = "INSERT INTO employees (name, email, mobile, role, job_title, base_salary, username, password, profile_image) 
                        VALUES ('$name', '$email', '$mobile', '$role_val', '$job', '$salary', '$username', '$password', '$profile_image')";
                
                if($conn->query($sql)) {
                    $msg = "New Staff added successfully!";
                    $msg_type = "success";
                    $show_share = true;

                    // Whatsapp & Email Links
                    $clean_mobile = preg_replace('/[^0-9]/', '', $mobile);
                    $raw_msg = "Welcome to NexusHR, *$name*!\n\nHere are your login details:\n👤 *Username:* $username\n🔑 *Password:* $password";
                    $encoded_msg = rawurlencode($raw_msg);
                    $wa_link = "https://api.whatsapp.com/send?phone={$clean_mobile}&text={$encoded_msg}";
                    $mail_subject = "Welcome to NexusHR - Login Details";
                    $email_link = "https://mail.google.com/mail/?view=cm&fs=1&to=$email&su=" . rawurlencode($mail_subject) . "&body=" . $encoded_msg;
                    
                    $share_data = ['wa' => $wa_link, 'email' => $email_link, 'user' => $username, 'pass' => $password];
                } else {
                    $msg = "Database Error: " . $conn->error;
                    $msg_type = "error";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Staff | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">Add New Staff</h1>
                <p class="page-subtitle">Create accounts for Interns, Engineers, and HRs.</p>
            </div>
            <a href="manage_employees.php" class="btn btn-dark"><i class="fas fa-arrow-left"></i> Back to Directory</a>
        </div>

        <?php if($show_share): ?>
            <div class="bento-card" style="background: #f0fdf4; border-color: #bbf7d0; margin-bottom: 30px;">
                <div style="color: #15803d; font-weight: 800; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle"></i> Staff Added Successfully!
                </div>
                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px dashed #16a34a; margin-bottom: 20px; font-family: monospace; color: #15803d; font-size: 15px;">
                    <strong>Username:</strong> <?php echo $share_data['user']; ?><br>
                    <strong>Password:</strong> <?php echo $share_data['pass']; ?>
                </div>
                <div style="display:flex; gap:15px;">
                    <a href="<?php echo $share_data['wa']; ?>" target="_blank" class="btn" style="background: #25D366; color: white;"><i class="fab fa-whatsapp"></i> Share on WhatsApp</a>
                    <a href="<?php echo $share_data['email']; ?>" target="_blank" class="btn" style="background: #ea4335; color: white;"><i class="fas fa-envelope"></i> Send via Email</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if($msg && $msg_type == 'error'): ?>
            <div style="padding:15px; background:#fee2e2; color:#991b1b; border-radius:10px; margin-bottom:25px; font-weight:600; border: 1px solid #fecaca;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="bento-card">
            <form method="POST" enctype="multipart/form-data">
                
                <h3 style="margin: 0 0 20px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;"><i class="fas fa-id-card" style="color: #4361ee;"></i> Profile Details</h3>

                <div style="margin-bottom: 20px;">
                    <label class="form-label">Profile Picture (Optional)</label>
                    <input type="file" name="profile_image" accept="image/*" class="form-input" style="padding: 10px; background: white; cursor: pointer;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label class="form-label">System Role</label>
                        <select name="role" class="form-input" required>
                            <option value="employee">Regular Employee (Attendance & Leaves)</option>
                            <?php if($current_role == 'admin' || $current_role == 'hr'): ?>
                                <option value="hr">HR Manager (Manage Staff & Payroll)</option>
                            <?php endif; ?>
                            <?php if($current_role == 'admin'): ?>
                                <option value="admin">Administrator (Full Access)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" placeholder="e.g. Rahul Sharma" class="form-input" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" placeholder="rahul@nexushr.com" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">Mobile (with Country Code)</label>
                        <input type="text" name="mobile" placeholder="e.g. 919876543210" class="form-input" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div>
                        <label class="form-label">Job Title</label>
                        <select name="job_title" class="form-input" required>
                            <option>Intern</option><option>Developer</option><option>Designer</option><option>Manager</option><option>HR Executive</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Base Salary (₹)</label>
                        <input type="number" name="salary" placeholder="25000" class="form-input" required>
                    </div>
                </div>

                <div style="border-top: 1px dashed #cbd5e1; margin: 30px 0;"></div>
                
                <h3 style="margin: 0 0 20px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;"><i class="fas fa-lock" style="color: #4361ee;"></i> Login Credentials</h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div>
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <input type="text" name="password" class="form-input" required>
                    </div>
                </div>

                <button type="submit" name="add_staff" class="btn" style="background: #0f172a; color: white; width: 100%; justify-content: center; padding: 15px; font-size: 16px;">
                    <i class="fas fa-plus-circle"></i> Create Account
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>