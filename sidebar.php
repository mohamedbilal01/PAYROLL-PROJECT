<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$current_page = basename($_SERVER['PHP_SELF']); 
if(!isset($conn)) { include 'db.php'; }

$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest';

// BUG FIX: Force the role to strict lowercase right here so no page ever fails the check
$role = '';
if(isset($_SESSION['role'])) {
    $role = strtolower(trim($_SESSION['role']));
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// --- PROFILE PICTURE & FRESH DATA LOGIC ---
$user_pic = 'default.png';
$display_role = ucfirst($role);

if($user_id > 0) {
    $q_data = $conn->query("SELECT name, role, job_title, profile_image FROM employees WHERE id='$user_id'");
    if($q_data && $q_data->num_rows > 0) {
        $r_data = $q_data->fetch_assoc();
        if(!empty($r_data['profile_image'])) { $user_pic = $r_data['profile_image']; }
        
        $user_name = $r_data['name'];
        $role = strtolower(trim($r_data['role']));
        $display_role = !empty($r_data['job_title']) ? strtoupper($r_data['job_title']) : strtoupper($role);
        
        // Update session so it stays fresh across pages
        $_SESSION['user_name'] = $user_name;
        $_SESSION['role'] = $role;
        $_SESSION['job_title'] = $r_data['job_title'];
    }
}
$pic_path = "uploads/" . $user_pic;
if (!file_exists($pic_path)) { 
    $pic_path = "https://ui-avatars.com/api/?name=".urlencode($user_name)."&background=random&color=fff"; 
}
?>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="sidebar">
    
    <div class="sidebar-brand">
        <div class="logo-icon">
            <i class="fas fa-cube"></i>
        </div>
        <h2>NexusHR</h2>
    </div>

    <div class="sidebar-user">
        <img src="<?php echo htmlspecialchars($pic_path); ?>" class="user-avatar" alt="User">
        <div class="user-details">
            <h4><?php echo htmlspecialchars($user_name); ?></h4>
            <span><?php echo htmlspecialchars($display_role); ?></span>
        </div>
    </div>

    <div class="sidebar-nav">
        <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>

        <?php if($role == 'admin' || $role == 'hr'): ?>
            <div class="nav-section">Administration</div>
            
            <a href="add_employee.php" class="nav-link <?php echo $current_page == 'add_employee.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i> <span>Add Employee</span>
            </a>
            
            <a href="manage_employees.php" class="nav-link <?php echo $current_page == 'manage_employees.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> <span>Manage Staff</span>
            </a>
            
            <a href="manage_requests.php" class="nav-link <?php echo $current_page == 'manage_requests.php' ? 'active' : ''; ?>">
                <i class="fas fa-check-double"></i> <span>Approval Queue</span>
            </a>
            
            <a href="manage_attendance.php" class="nav-link <?php echo $current_page == 'manage_attendance.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> <span>All Attendance</span>
            </a>
            
            <a href="staff_attendance_history.php" class="nav-link <?php echo $current_page == 'staff_attendance_history.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> <span>Staff History</span>
            </a>
            
            <a href="payroll_reports.php" class="nav-link <?php echo $current_page == 'payroll_reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> <span>Reports</span>
            </a>
            
            <a href="process_salary.php" class="nav-link <?php echo $current_page == 'process_salary.php' ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i> <span>Run Payroll</span>
            </a>
        <?php endif; ?>

        <?php if($role == 'hr' || $role == 'employee'): ?>
            <div class="nav-section">My Workspace</div>
            
            <a href="my_attendance.php" class="nav-link <?php echo $current_page == 'my_attendance.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> <span>My Attendance</span>
            </a>
            
            <a href="apply_leave.php" class="nav-link <?php echo $current_page == 'apply_leave.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-minus"></i> <span>Apply Leave</span>
            </a>
            
            <a href="my_payslips.php" class="nav-link <?php echo $current_page == 'my_payslips.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i> <span>My Payslips</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link logout-link">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const toggleBtn = document.getElementById('mobile-toggle-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (toggleBtn && sidebar && overlay) {
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            }
            
            toggleBtn.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);
        }
    });
</script>