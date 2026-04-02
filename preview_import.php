<?php
include 'c:/xampp/htdocs/payroll-system/db.php';

$first_names = ["James", "Mary", "Michael", "Patricia", "Robert", "Jennifer", "John", "Linda", "David", "Elizabeth", "William", "Barbara", "Richard", "Susan", "Joseph"];
$last_names = ["Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez", "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson"];
$roles = ["employee", "employee", "employee", "employee", "hr"];
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
    $username = strtolower(substr($fname, 0, 1) . $lname) . rand(1, 999);
    $password = "Pass@" . rand(1000, 9999);
    
    // Check unique username
    $check = $conn->query("SELECT id FROM employees WHERE username='$username'");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO employees (name, email, mobile, role, job_title, base_salary, username, password) VALUES ('$fullname', '$email', '$mobile', '$role', '$title', '$salary', '$username', '$password')");
    }
}
echo "Import simulated successfully.";
?>
