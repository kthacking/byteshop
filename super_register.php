<?php
/**
 * ByteShop - Super Role Registration Page
 * For registering Admin and Shop Owner accounts
 * 
 * IMPORTANT: This page should be protected or removed in production
 * Only use during development/testing
 */
session_start();
require_once 'includes/session.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect_to_dashboard();
}

// Get error/success message if any
$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Registration - ByteShop</title>
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

        /* Decorative Elements */
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
            font-size: 64px;
            line-height: 1.1;
            font-weight: 800;
            margin-bottom: 24px;
            color: #111;
            letter-spacing: -2px;
        }

        .visual-side p {
            font-size: 18px;
            line-height: 1.6;
            color: #555;
            max-width: 500px;
            font-weight: 400;
        }

        .highlight-text {
            background: linear-gradient(120deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Right Side - Form */
        .form-side {
            flex: 0 0 550px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 60px;
            border-left: 1px solid rgba(0,0,0,0.05);
            position: relative;
            z-index: 5;
            box-shadow: -10px 0 30px rgba(0,0,0,0.02);
            overflow-y: auto;
        }

        .login-header {
            margin-bottom: 32px;
        }

        .logo {
            font-size: 24px;
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
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1a1a1a;
            letter-spacing: -1px;
        }

        .login-header p {
            color: #888;
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            background: #f9fafb;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            color: #1f2937;
        }

        input::placeholder {
            color: #9ca3af;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 75, 43, 0.1);
        }

        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .role-option input[type="radio"] {
            display: none;
        }

        .role-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f9fafb;
            text-align: center;
            height: 100%;
        }

        .role-card:hover {
            border-color: var(--primary);
            background: white;
            transform: translateY(-2px);
        }

        .role-option input[type="radio"]:checked + .role-card {
            border-color: var(--primary);
            background: #FFF5F5;
            box-shadow: 0 4px 12px rgba(255, 75, 43, 0.15);
        }

        .role-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .role-title {
            font-weight: 700;
            font-size: 14px;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .role-desc {
            font-size: 11px;
            color: #6b7280;
        }
        
        .warning-box {
            background: #fff7ed;
            border: 1px solid #ffedd5;
            color: #c2410c;
            padding: 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        
        .warning-box strong {
            display: block;
            margin-bottom: 4px;
            color: #ea580c;
        }
        
        .warning-box a {
            color: #ea580c;
            text-decoration: underline;
        }

        .password-strength {
            font-size: 12px;
            margin-top: 6px;
            min-height: 18px;
            font-weight: 500;
        }

        .btn-register {
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

        .btn-register:hover {
            background: #1f2937;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .alert {
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            line-height: 1.5;
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

        .links {
            text-align: center;
            font-size: 15px;
            color: #6b7280;
            margin-top: 30px;
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
            .visual-side h1 { font-size: 48px; }
            .form-side { flex: 0 0 500px; padding: 40px; }
        }

        @media (max-width: 900px) {
            .main-container {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
                overflow-y: auto;
            }
            
            body { 
                overflow-y: auto; 
                height: auto;
                padding-bottom: 20px;
            }
            
            .visual-side {
                padding: 60px 30px;
                min-height: 350px;
                flex: none;
            }
            
            .content-box { margin-left: 0; text-align: center; margin: 0 auto; }
            .visual-side h1 { font-size: 40px; }
            .badge-pill { margin-bottom: 24px; }
            
            .form-side {
                flex: 1;
                width: 100%;
                padding: 40px 20px;
                border-left: none;
                border-top: 1px solid #eee;
                box-shadow: none;
            }
            
            .form-row { flex-direction: column; gap: 0; }
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
                <div class="badge-pill">Build</div>
                <h1>Empower Your<br><span class="highlight-text">Commerce Vision.</span></h1>
                <p>Join as a Shop Owner to sell your products, or as an Admin to manage the entire ecosystem. The platform for serious business.</p>
            </div>
        </div>

        <!-- Form Side -->
        <div class="form-side">
            <div class="login-header">
                <div class="logo">
                     👑 Market<span>X</span>
                </div>
                <h2>Super Registration</h2>
                <p>Create an Admin or Shop Owner account.</p>
            </div>
            
            <div class="warning-box">
                <strong>⚠️ SPECIAL ACCESS AREA</strong>
                This page is for creating managing accounts. For regular customer registration, please use the <a href="register.php">standard registration page</a>.
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span>
                    <?php
                    switch($error) {
                        case 'empty':
                            echo 'Please fill in all required fields!';
                            break;
                        case 'email_exists':
                            $existing_role = isset($_GET['role']) ? $_GET['role'] : 'unknown';
                            echo "This email is already registered as {$existing_role}!";
                            break;
                        case 'password_mismatch':
                            echo 'Passwords do not match!';
                            break;
                        case 'invalid_email':
                            echo 'Please enter a valid email address!';
                            break;
                        case 'weak_password':
                            echo 'Password must be at least 6 characters!';
                            break;
                        case 'invalid_role':
                            echo 'Please select a valid role (Admin or Shop Owner)!';
                            break;
                        case 'short_name':
                            echo 'Name must be at least 3 characters long!';
                            break;
                        case 'invalid_name':
                            echo 'Name should contain only letters and spaces!';
                            break;
                        case 'invalid_phone':
                            echo 'Please enter a valid 10-digit phone number!';
                            break;
                        case 'admin_exists':
                            echo 'An admin account already exists in the system!';
                            break;
                        case 'insert_failed':
                            echo 'Failed to create account. Please try again.';
                            break;
                        case 'server':
                            echo 'Server error occurred. Please contact support.';
                            break;
                        case 'invalid_action':
                            echo 'Invalid request. Please try again.';
                            break;
                        default:
                            echo 'Registration failed. Please try again.';
                    }
                    ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($success === 'registered'): ?>
                <div class="alert alert-success">
                    <?php
                    $registered_role = isset($_GET['role']) ? ucfirst(str_replace('_', ' ', $_GET['role'])) : 'Account';
                    echo "✅ {$registered_role} account created successfully! You can now <a href='login.php' style='color:inherit; text-decoration:underline;'>login here</a>.";
                    ?>
                </div>
            <?php endif; ?>

            <form id="registerForm" method="POST" action="api/super_auth.php">
                <input type="hidden" name="action" value="super_register">
                
                <!-- Role Selection -->
                <div class="form-group">
                    <label>Select Role</label>
                    <div class="role-selector">
                        <div class="role-option">
                            <input type="radio" id="role_admin" name="role" value="admin" required>
                            <label for="role_admin" class="role-card">
                                <div class="role-icon">👨‍💼</div>
                                <div class="role-title">Admin</div>
                                <div class="role-desc">Full system access</div>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="role_owner" name="role" value="shop_owner" required>
                            <label for="role_owner" class="role-card">
                                <div class="role-icon">🏪</div>
                                <div class="role-title">Shop Owner</div>
                                <div class="role-desc">Manage your market</div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter your full name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="+1 (555) 000-0000">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Min 6 characters" required minlength="6">
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required minlength="6">
                    </div>
                </div>

                <button type="submit" class="btn-register">Create Account</button>
            </form>
            
            <div class="links">
                <p>Already have an account? <a href="login.php">Login Here</a></p>
            </div>
        </div>
    </div>

    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                return;
            }
            
            if (password.length < 6) {
                strengthDiv.textContent = '❌ Too short';
                strengthDiv.style.color = '#ff4757';
            } else if (password.length < 8) {
                strengthDiv.textContent = '⚠️ Weak password';
                strengthDiv.style.color = '#f7931e';
            } else {
                strengthDiv.textContent = '✅ Good password';
                strengthDiv.style.color = '#00d4aa';
            }
        });

        // Real-time password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = '#00d4aa';
                } else {
                    this.style.borderColor = '#ff4757';
                }
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const role = document.querySelector('input[name="role"]:checked');
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Check role selection
            if (!role) {
                e.preventDefault();
                alert('Please select a role (Admin or Shop Owner)!');
                return;
            }

            // Check empty fields
            if (!name || !email || !password || !confirmPassword) {
                e.preventDefault();
                alert('Please fill in all required fields!');
                return;
            }

            // Check name length
            if (name.length < 3) {
                e.preventDefault();
                alert('Name must be at least 3 characters long!');
                return;
            }

            // Check email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address!');
                return;
            }

            // Check password length
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return;
            }

            // Check password match
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
        });
    </script>
</body>
</html>


