<?php
session_start();
include 'db.php';

$msg = "";

if (isset($_POST['login'])) {
    $role = $_POST['role'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 1. Check if user exists in Employees table
    // (Assuming your table has columns: username, password, role)
    $stmt = $conn->prepare("SELECT id, name, password, role, job_title FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // 2. Verify Password (Simple string compare for now, upgrade to password_verify later)
        if ($password == $row['password']) {
            
            // 3. Verify Role Match
            // (Converts database role to lowercase to ensure match with dropdown)
            $db_role = strtolower(trim($row['role'])); 
            $login_role = strtolower(trim($role));

            // Allow 'admin' to log in even if they selected 'hr' in dropdown (optional flexibility)
            // But strictly, we check if they match:
            if ($db_role == $login_role || ($db_role == 'admin' && $login_role == 'administrator')) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['name'];
                $_SESSION['role'] = $db_role;
                $_SESSION['job_title'] = $row['job_title'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                $msg = "Access Denied: You do not have $role permissions.";
            }
        } else {
            $msg = "Invalid Password.";
        }
    } else {
        $msg = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login | NexusHR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- RESET & VARIABLES --- */
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --secondary: #0ea5e9;
            --dark: #0f172a;
            --text-light: #64748b;
        }

        * { box-sizing: border-box; outline: none; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            position: relative;
            overflow: hidden;
        }

        /* --- ANIMATED BACKGROUND BLOBS --- */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 1;
            opacity: 0.6;
            animation: float 20s infinite ease-in-out;
        }
        .blob-1 {
            width: 400px; height: 400px;
            background: rgba(79, 70, 229, 0.4); /* Indigo */
            top: -100px; left: -100px;
        }
        .blob-2 {
            width: 500px; height: 500px;
            background: rgba(14, 165, 233, 0.3); /* Sky Blue */
            bottom: -150px; right: -100px;
            animation-delay: -5s;
        }
        .blob-3 {
            width: 300px; height: 300px;
            background: rgba(16, 185, 129, 0.2); /* Emerald */
            top: 40%; left: 60%;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }

        /* --- LAYOUT WRAPPER --- */
        .login-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 20px;
            z-index: 10;
            position: relative;
        }

        /* --- PREMIUM GLASSMORPHIC CARD --- */
        .login-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255,255,255,0.1);
            text-align: center;
            transform: translateY(0);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .login-card:hover {
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255,255,255,0.2);
        }

        /* --- LOGO AREA --- */
        .logo-box {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            font-size: 28px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 16px;
            margin: 0 auto 24px auto;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.5);
        }
        
        h2 { margin: 0 0 8px 0; color: #ffffff; font-weight: 800; font-size: 26px; letter-spacing: -0.5px; }
        p { margin: 0 0 32px 0; color: #cbd5e1; font-size: 15px; }

        /* --- FORM STYLING --- */
        .form-group { text-align: left; margin-bottom: 22px; }
        .label { display: block; font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .input-box {
            position: relative;
        }
        .input-box i {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: #64748b; font-size: 16px; transition: 0.3s;
        }
        
        .form-input { 
            width: 100%; 
            padding: 14px 16px 14px 45px;
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px; 
            font-size: 15px; 
            font-weight: 500; 
            color: #ffffff;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }
        
        .form-input::placeholder { color: #64748b; }

        .form-input:focus { 
            border-color: var(--primary); 
            background: rgba(15, 23, 42, 0.6); 
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15); 
        }
        .form-input:focus + i { color: var(--primary); }

        select.form-input { cursor: pointer; padding-left: 16px; appearance: none; }
        .select-wrapper { position: relative; }
        .select-wrapper::after {
            content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            color: #64748b; pointer-events: none;
        }
        select.form-input option { background: var(--dark); color: white; }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(79, 70, 229, 0.5);
            background: linear-gradient(135deg, var(--primary-hover), var(--secondary));
        }

        /* --- ERROR MESSAGE --- */
        .alert {
            background: rgba(239, 68, 68, 0.1); color: #fca5a5;
            padding: 12px 16px; border-radius: 10px;
            font-size: 13px; font-weight: 600;
            margin-bottom: 25px;
            border: 1px solid rgba(239, 68, 68, 0.2);
            display: flex; align-items: center; gap: 10px;
        }

        /* --- BOLD & DARK FOOTER --- */
        .footer {
            margin-top: 40px;
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            text-align: center;
        }
        .footer span {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: #ffffff;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        /* Responsive Fixes */
        @media (max-width: 480px) {
            .login-card { padding: 30px 20px; }
            h2 { font-size: 24px; }
        }

    </style>
</head>
<body>
    <!-- Animated background layers -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="logo-box">
                <i class="fas fa-cube"></i>
            </div>
            <h2>Welcome Back</h2>
            <p>Sign in to continue to NexusHR</p>

            <?php if($msg): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                
                <div class="form-group">
                    <label class="label">Login As</label>
                    <div class="select-wrapper">
                        <select name="role" class="form-input">
                            <option value="Administrator">Administrator</option>
                            <option value="HR">HR Manager</option>
                            <option value="Employee">Regular Employee</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="label">Username</label>
                    <div class="input-box">
                        <input type="text" name="username" class="form-input" placeholder="e.g. admin" required>
                        <i class="fas fa-user"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="label">Password</label>
                    <div class="input-box">
                        <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                        <i class="fas fa-lock"></i>
                    </div>
                </div>

                <button type="submit" name="login" class="btn-login">Sign In</button>
            </form>

        </div>

        <div class="footer" style="text-align: center;">
            Developed by
            <span>S.Mohamed Bilal</span>
        </div>
    </div>

</body>
</html>