<?php
$servername = "localhost";
$username = "root";
$password = ""; // Default XAMPP password is empty
$dbname = "payroll_system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SMTP Configuration for Email Sending
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'md2631619@gmail.com'); 
define('SMTP_PASS', 'nrerlpdkqnnrzigc'); 
define('SMTP_PORT', 587);

// NEXUS HR : MASTERS fallbacks SYSTEM
// Returns a beautiful circular fallback with initials based on the role color
function getAvatarFallback($name, $role) {
    $words = explode(" ", $name);
    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
    
    $role = strtolower(trim($role));
    $av_bg = '#e0e7ff'; $av_col = '#3730a3'; // Default Employee (Blue)
    
    if($role == 'admin' || $role == 'administrator') { 
        $av_bg = '#dcfce7'; $av_col = '#166534'; // Admin (Green)
    } elseif($role == 'hr' || $role == 'hr manager') { 
        $av_bg = '#fef08a'; $av_col = '#854d0e'; // HR (Yellow)
    }
    
    return "<div style='width:100%; height:100%; border-radius:12px; display:flex; align-items:center; justify-content:center; font-weight:700; background:$av_bg; color:$av_col;'>$initials</div>";
}
?>