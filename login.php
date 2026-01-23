<?php
/**
 * ByteShop - Login Page
 */
session_start();
require_once 'includes/session.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect_to_dashboard();
}

// Get error message if any
$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ByteShop</title>
    <style>
        /* CSS reset and fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        :root {
            --primary: #FF4B2B; /* Vibrant Red/Orange */
            --secondary: #FF416C;
            --text-dark: #1F2937;
            --text-light: #6B7280;
            --white: #FFFFFF;
            --bg-light: #F9FAFB;
            --grid-color: rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            background-image: 
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 40px 40px;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            overflow: hidden;
        }

        .main-container {
            display: flex;
            width: 100%;
            height: 100vh;
            background: var(--white);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* Left Side - Visual/Text */
        .visual-side {
            flex: 1;
            background: #f8f9fa;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            overflow: hidden;
        }
        
        /* Grid pattern */
        .visual-side::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                linear-gradient(rgba(0,0,0,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: 0;
        }

        /* Decorative Elements to match "compass/pen" tech feel */
        .decoration-circle {
            position: absolute;
            width: 600px;
            height: 600px;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 50%;
            top: 50%;
            right: -200px;
            transform: translateY(-50%);
            z-index: 0;
        }
        
        .decoration-line {
            position: absolute;
            height: 1px;
            background: rgba(0,0,0,0.08);
            width: 100%;
            top: 30%;
            left: 0;
        }

        .content-box {
            position: relative;
            z-index: 2;
            max-width: 600px;
            margin-left: 10%;
        }

        .badge-pill {
            display: inline-block;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
            padding: 8px 24px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 32px;
            box-shadow: 0 4px 15px rgba(255, 75, 43, 0.3);
        }

        .visual-side h1 {
            font-size: 72px;
            line-height: 1.05;
            font-weight: 800;
            margin-bottom: 24px;
            color: #111;
            letter-spacing: -2.5px;
        }

        .visual-side p {
            font-size: 20px;
            line-height: 1.6;
            color: #555;
            max-width: 500px;
            font-weight: 400;
        }

        .highlight-text {
            /* color: var(--primary); */ 
            background: linear-gradient(120deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Right Side - Login Form */
        .form-side {
            flex: 0 0 550px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 80px 60px;
            border-left: 1px solid rgba(0,0,0,0.05);
            position: relative;
            z-index: 5;
            box-shadow: -10px 0 30px rgba(0,0,0,0.02);
        }

        .login-header {
            margin-bottom: 48px;
        }

        .logo {
            font-size: 28px;
            font-weight: 800;
            color: #111;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo span {
            color: var(--primary);
        }

        .login-header h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1a1a1a;
            letter-spacing: -1px;
        }

        .login-header p {
            color: #888;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            background: #f9fafb;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            color: #1f2937;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: #9ca3af;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 75, 43, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 18px;
            background: #000;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            background: #1f2937;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .alert {
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }

        .alert-success {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 40px 0;
            color: #9ca3af;
            font-size: 13px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }
        
        .divider span {
            padding: 0 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 12px;
        }

        .links {
            text-align: center;
            font-size: 15px;
            color: #6b7280;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .links a {
            color: #111;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .links a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 1100px) {
            .visual-side h1 { font-size: 56px; }
            .form-side { flex: 0 0 450px; padding: 60px 40px; }
        }

        @media (max-width: 900px) {
            .main-container {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }
            
            .visual-side {
                padding: 60px 30px;
                min-height: 400px;
                background-position: center;
            }
            
            .content-box { margin-left: 0; text-align: center; margin: 0 auto; }
            .visual-side h1 { font-size: 48px; }
            .badge-pill { margin-bottom: 24px; }
            
            .form-side {
                flex: 1;
                width: 100%;
                padding: 50px 25px;
                border-left: none;
                border-top: 1px solid #eee;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Visual Side -->
        <div class="visual-side">
            <div class="decoration-circle"></div>
            <div class="decoration-line"></div>
            
            <div class="content-box">
                <div class="badge-pill">Appreciation</div>
                <h1>You Didn't Just <br>Join. You <br><span class="highlight-text">Believed.</span></h1>
                <p>To every creative who took the leap, we see your courage, we value your trust. That's the real essence of design, progress over perfection.</p>
            </div>
        </div>

        <!-- Form Side -->
        <div class="form-side">
            <div class="login-header">
                <div class="logo">
                     🛒 Market<span>X</span>
                </div>
                <h2>Welcome Back</h2>
                <p>Enter your credentials to access your account.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>
                    <?php
                    switch($error) {
                        case 'invalid':
                            echo 'Invalid email or password!';
                            break;
                        case 'empty':
                            echo 'Please fill in all fields!';
                            break;
                        case 'unauthorized':
                            echo 'You are not authorized to access that page!';
                            break;
                        default:
                            echo 'An error occurred. Please try again.';
                    }
                    ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($success === 'registered'): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span>Registration successful! Please login.</span>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="api/auth.php">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="name@company.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-login">Sign In</button>
            </form>

            <div class="divider"><span>OR</span></div>

            <div class="links">
                <div>Don't have an account? <a href="register.php">Create Customer Account</a></div>
                <div><a href="super_register.php" style="color: #6b7280; font-weight: 500;">Register as Admin / Shop Owner</a></div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields!');
            }
        });
    </script>
</body>
</html>