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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 36px;
            margin-bottom: 5px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            color: #856404;
            font-size: 13px;
            line-height: 1.6;
        }

        .warning-box strong {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        label .required {
            color: #f5576c;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #f5576c;
            box-shadow: 0 0 0 3px rgba(245, 87, 108, 0.1);
        }

        select {
            cursor: pointer;
            background: white;
        }

        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .role-option {
            position: relative;
        }

        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .role-card {
            padding: 20px;
            border: 3px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .role-card:hover {
            border-color: #f5576c;
            transform: translateY(-2px);
        }

        .role-option input[type="radio"]:checked + .role-card {
            border-color: #f5576c;
            background: linear-gradient(135deg, #f093fb20 0%, #f5576c20 100%);
        }

        .role-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .role-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .role-desc {
            font-size: 12px;
            color: #666;
        }

        .password-strength {
            font-size: 12px;
            margin-top: 5px;
            font-weight: 500;
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(245, 87, 108, 0.4);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 2px solid #fcc;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .links a {
            color: #f5576c;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: opacity 0.3s;
        }

        .links a:hover {
            opacity: 0.7;
        }

        .links p {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 580px) {
            .register-container {
                padding: 30px 20px;
            }

            .role-selector,
            .form-row {
                grid-template-columns: 1fr;
            }

            .logo h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>👑 ByteShop</h1>
            <p>Super Role Registration</p>
        </div>

        <div class="warning-box">
            <strong>⚠️ ADMIN/OWNER REGISTRATION</strong>
            This page is for creating Admin and Shop Owner accounts. For customer registration, please use the <a href="register.php" style="color: #856404; text-decoration: underline;">regular registration page</a>.
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php
                switch($error) {
                    case 'empty':
                        echo '❌ Please fill in all required fields!';
                        break;
                    case 'email_exists':
                        echo '❌ This email is already registered!';
                        break;
                    case 'password_mismatch':
                        echo '❌ Passwords do not match!';
                        break;
                    case 'invalid_email':
                        echo '❌ Please enter a valid email address!';
                        break;
                    case 'weak_password':
                        echo '❌ Password must be at least 6 characters!';
                        break;
                    case 'invalid_role':
                        echo '❌ Please select a valid role!';
                        break;
                    case 'server':
                        echo '❌ Server error. Please try again later.';
                        break;
                    default:
                        echo '❌ Registration failed. Please try again.';
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if ($success === 'registered'): ?>
            <div class="alert alert-success">
                ✅ Account created successfully! You can now <a href="login.php" style="color: #155724; text-decoration: underline; font-weight: bold;">login here</a>.
            </div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="api/super_auth.php">
            <input type="hidden" name="action" value="super_register">
            
            <!-- Role Selection -->
            <div class="form-group">
                <label>Select Role <span class="required">*</span></label>
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
                <label for="name">Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" placeholder="Enter your full name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" placeholder="your@email.com" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" placeholder="+91 98765 43210 (Optional)">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" placeholder="Min 6 characters" required minlength="6">
                    <div class="password-strength" id="passwordStrength"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required minlength="6">
                </div>
            </div>

            <button type="submit" class="btn-register">Create Account</button>
        </form>

        <div class="links">
            <p>Already have an account?</p>
            <a href="login.php">← Back to Login</a>
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
                strengthDiv.textContent = '❌ Too short (min 6 characters)';
                strengthDiv.style.color = '#c33';
            } else if (password.length < 8) {
                strengthDiv.textContent = '⚠️ Weak password';
                strengthDiv.style.color = '#f90';
            } else if (password.length < 10) {
                strengthDiv.textContent = '✅ Good password';
                strengthDiv.style.color = '#3c3';
            } else {
                strengthDiv.textContent = '🔥 Strong password';
                strengthDiv.style.color = '#27ae60';
            }
        });

        // Real-time password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = '#27ae60';
                } else {
                    this.style.borderColor = '#e74c3c';
                }
            } else {
                this.style.borderColor = '#e0e0e0';
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
                alert('❌ Please select a role (Admin or Shop Owner)!');
                return;
            }

            // Check empty fields
            if (!name || !email || !password || !confirmPassword) {
                e.preventDefault();
                alert('❌ Please fill in all required fields!');
                return;
            }

            // Check name length
            if (name.length < 3) {
                e.preventDefault();
                alert('❌ Name must be at least 3 characters long!');
                return;
            }

            // Check email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('❌ Please enter a valid email address!');
                return;
            }

            // Check password length
            if (password.length < 6) {
                e.preventDefault();
                alert('❌ Password must be at least 6 characters long!');
                return;
            }

            // Check password match
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('❌ Passwords do not match!');
                return;
            }
        });

        // Add visual feedback on role selection
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.role-card').forEach(card => {
                    card.style.transform = 'scale(1)';
                });
                this.nextElementSibling.style.transform = 'scale(1.05)';
            });
        });
    </script>
    <?php if ($error): ?>
    <div class="alert alert-error">
        <?php
        switch($error) {
            case 'empty':
                echo '❌ Please fill in all required fields!';
                break;
            case 'email_exists':
                $existing_role = isset($_GET['role']) ? $_GET['role'] : 'unknown';
                echo "❌ This email is already registered as {$existing_role}!";
                break;
            case 'password_mismatch':
                echo '❌ Passwords do not match!';
                break;
            case 'invalid_email':
                echo '❌ Please enter a valid email address!';
                break;
            case 'weak_password':
                echo '❌ Password must be at least 6 characters!';
                break;
            case 'invalid_role':
                echo '❌ Please select a valid role (Admin or Shop Owner)!';
                break;
            case 'short_name':
                echo '❌ Name must be at least 3 characters long!';
                break;
            case 'invalid_name':
                echo '❌ Name should contain only letters and spaces!';
                break;
            case 'invalid_phone':
                echo '❌ Please enter a valid 10-digit phone number!';
                break;
            case 'admin_exists':
                echo '❌ An admin account already exists in the system!';
                break;
            case 'insert_failed':
                echo '❌ Failed to create account. Please try again.';
                break;
            case 'server':
                echo '❌ Server error occurred. Please contact support.';
                break;
            case 'invalid_action':
                echo '❌ Invalid request. Please try again.';
                break;
            default:
                echo '❌ Registration failed. Please try again.';
        }
        ?>
    </div>
<?php endif; ?>

<?php if ($success === 'registered'): ?>
    <div class="alert alert-success">
        <?php
        $registered_role = isset($_GET['role']) ? ucfirst(str_replace('_', ' ', $_GET['role'])) : 'Account';
        echo "✅ {$registered_role} account created successfully! You can now <a href='login.php' style='color: #155724; text-decoration: underline; font-weight: bold;'>login here</a>.";
        ?>
    </div>
<?php endif; ?>
</body>
</html>