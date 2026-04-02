<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

// Security Check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { 
    header("Location: index.php"); exit(); 
}
include 'db.php';
$current_role = strtolower(trim($_SESSION['role'])); 

$msg = ""; $msg_type = "";

// Get Employee ID
if(!isset($_GET['id'])) { header("Location: manage_employees.php"); exit(); }
$emp_id = (int)$_GET['id'];

// Fetch current details
$q = $conn->query("SELECT * FROM employees WHERE id='$emp_id'");
if($q->num_rows == 0) { header("Location: manage_employees.php"); exit(); }
$e = $q->fetch_assoc();

// HANDLE UPDATE
if (isset($_POST['update_staff'])) {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $role_val = $_POST['role']; // Changed select name from role_val for consistency
    $job = $_POST['job_title'];
    $salary = $_POST['salary'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // SECURITY CHECK: HR cannot promote people to Admins
    if($current_role == 'hr' && strtolower($role_val) == 'admin') {
        $msg = "Security Alert: HR cannot assign Administrator role."; $msg_type = "error";
    } else {
        // --- 1. HANDLE IMAGE UPDATE ---
        $profile_image = $e['profile_image']; // Keep existing by default

        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $filename = $_FILES['profile_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                // Delete old image if it exists to save space
                if(!empty($e['profile_image']) && file_exists('uploads/' . $e['profile_image'])) {
                    unlink('uploads/' . $e['profile_image']);
                }
                
                // Save new image
                $profile_image = uniqid('profile_') . '.' . $ext;
                move_uploaded_file($_FILES['profile_image']['tmp_name'], 'uploads/' . $profile_image);
            } else {
                $msg = "Invalid image format. Only JPG, PNG, and WEBP allowed."; $msg_type = "error";
            }
        }

        if($msg_type != "error") {
            // --- 2. UPDATE DATABASE ---
            $sql = "UPDATE employees SET 
                    name='$name', email='$email', mobile='$mobile', 
                    role='$role_val', job_title='$job', base_salary='$salary', 
                    username='$username', password='$password',
                    profile_image='$profile_image'
                    WHERE id='$emp_id'";
            
            if($conn->query($sql)) {
                // Refresh data
                $q = $conn->query("SELECT * FROM employees WHERE id='$emp_id'");
                $e = $q->fetch_assoc();
                $msg = "Profile updated successfully!"; $msg_type = "success";
            } else {
                $msg = "Error: " . $conn->error; $msg_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Staff | NexusHR</title>
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
                <h1 class="page-title">Edit Profile</h1>
                <p class="page-subtitle">Update records for <?php echo $e['name']; ?> (ID: #<?php echo str_pad($e['id'], 3, '0', STR_PAD_LEFT); ?>)</p>
            </div>
            <a href="manage_employees.php" class="btn btn-dark"><i class="fas fa-arrow-left"></i> Back to Directory</a>
        </div>

        <?php if($msg): ?>
            <div style="padding:15px; border-radius:10px; margin-bottom:25px; font-weight:600; border: 1px solid; <?php echo ($msg_type == 'success') ? 'background:#dcfce7; color:#166534; border-color: #bbf7d0;' : 'background:#fee2e2; color:#991b1b; border-color: #fecaca;'; ?>">
                <i class="fas <?php echo ($msg_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="bento-card">
            <form method="POST" enctype="multipart/form-data">
                
                <h3 style="margin: 0 0 25px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;"><i class="fas fa-id-card" style="color: #4361ee;"></i> Profile Details</h3>

                <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 25px; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div style="width: 70px; height: 70px; flex-shrink: 0; border: 2px solid white; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); overflow: hidden;">
                        <?php
                        if (!empty($e['profile_image']) && file_exists('uploads/' . $e['profile_image'])) {
                            echo "<img src='uploads/".$e['profile_image']."' style='width:100%; height:100%; object-fit:cover;'>";
                        } else {
                            // Using the new universalFallback function from db.php
                            echo getAvatarFallback($e['name'], $e['role']);
                        }
                        ?>
                    </div>
                    <div style="flex-grow: 1;">
                        <label class="form-label">Change Profile Picture</label>
                        <input type="file" name="profile_image" accept="image/*" class="form-input" style="padding: 10px; background: white; cursor: pointer;">
                        <span style="font-size: 11px; color: #94a3b8; margin-top: 5px; display:block;">Allowed formats: JPG, PNG, WEBP. Max size: 2MB.</span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label class="form-label">System Role</label>
                        <select name="role" class="form-input" required>
                            <option value="employee" <?php if(strtolower($e['role']) == 'employee') echo 'selected'; ?>>Regular Employee (Attendance & Leaves)</option>
                            <?php if($current_role == 'admin' || $current_role == 'hr'): ?>
                                <option value="hr" <?php if(strtolower($e['role']) == 'hr') echo 'selected'; ?>>HR Manager (Manage Staff & Payroll)</option>
                            <?php endif; ?>
                            <?php if($current_role == 'admin'): ?>
                                <option value="admin" <?php if(strtolower($e['role']) == 'admin') echo 'selected'; ?>>Administrator (Full Access)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" value="<?php echo $e['name']; ?>" class="form-input" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" value="<?php echo $e['email']; ?>" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">Mobile (with Country Code)</label>
                        <input type="text" name="mobile" value="<?php echo $e['mobile']; ?>" class="form-input" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div>
                        <label class="form-label">Job Title</label>
                        <select name="job_title" class="form-input" required>
                            <?php
                            $titles = ['Intern', 'Developer', 'Designer', 'Manager', 'HR Executive', 'Administrator'];
                            foreach($titles as $t) {
                                $sel = (strtolower($e['job_title']) == strtolower($t)) ? 'selected' : '';
                                echo "<option $sel>$t</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Base Salary (₹)</label>
                        <input type="number" name="salary" value="<?php echo $e['base_salary']; ?>" class="form-input" required>
                    </div>
                </div>

                <div style="border-top: 1px dashed #cbd5e1; margin: 30px 0;"></div>
                
                <h3 style="margin: 0 0 20px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;"><i class="fas fa-lock" style="color: #4361ee;"></i> Login Credentials</h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div>
                        <label class="form-label">Username</label>
                        <input type="text" name="username" value="<?php echo $e['username']; ?>" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <input type="text" name="password" value="<?php echo $e['password']; ?>" class="form-input" required>
                    </div>
                </div>

                <button type="submit" name="update_staff" class="btn" style="background: #0f172a; color: white; width: 100%; justify-content: center; padding: 15px; font-size: 16px;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>