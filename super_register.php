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
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 18px;
    }

    .register-container {
        background: linear-gradient(145deg, #1a1a1a 0%, #0f0f0f 100%);
        padding: 36px;
        border-radius: 13.5px;
        box-shadow: 0 13.5px 31.5px rgba(0,0,0,0.6);
        border: 1px solid rgba(255, 107, 53, 0.2);
        width: 100%;
        max-width: 450px;
    }

    .logo {
        text-align: center;
        margin-bottom: 27px;
    }

    .logo h1 {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-size: 32.4px;
        margin-bottom: 4.5px;
        font-weight: 800;
        text-shadow: 0 0 27px rgba(255, 107, 53, 0.3);
    }

    .logo p {
        color: #999;
        font-size: 12.6px;
        font-weight: 500;
        letter-spacing: 0.45px;
    }

    .warning-box {
        background: rgba(255, 107, 53, 0.1);
        border: 1.8px solid rgba(255, 107, 53, 0.4);
        padding: 13.5px;
        border-radius: 7.2px;
        margin-bottom: 22.5px;
        color: #ff9966;
        font-size: 11.7px;
        line-height: 1.44;
        backdrop-filter: blur(9px);
    }

    .warning-box strong {
        display: block;
        margin-bottom: 4.5px;
        font-size: 12.6px;
        color: #ff6b35;
    }

    .warning-box a {
        color: #ff6b35;
        text-decoration: underline;
    }

    .form-group {
        margin-bottom: 18px;
    }

    label {
        display: block;
        margin-bottom: 7.2px;
        color: #e0e0e0;
        font-weight: 600;
        font-size: 12.6px;
        letter-spacing: 0.27px;
    }

    label .required {
        color: #ff6b35;
    }

    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="password"],
    select {
        width: 100%;
        padding: 10.8px 13.5px;
        border: 1.8px solid rgba(255, 107, 53, 0.2);
        border-radius: 7.2px;
        font-size: 12.6px;
        transition: all 0.3s;
        background: rgba(26, 26, 26, 0.6);
        color: #e0e0e0;
        backdrop-filter: blur(9px);
    }

    input::placeholder {
        color: #666;
    }

    input:focus,
    select:focus {
        outline: none;
        border-color: #ff6b35;
        box-shadow: 0 0 0 2.7px rgba(255, 107, 53, 0.2);
        background: rgba(26, 26, 26, 0.8);
    }

    select {
        cursor: pointer;
    }

    .role-selector {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 13.5px;
        margin-bottom: 18px;
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
        padding: 18px;
        border: 2.7px solid rgba(255, 107, 53, 0.3);
        border-radius: 9px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: rgba(26, 26, 26, 0.4);
        backdrop-filter: blur(9px);
    }

    .role-card:hover {
        border-color: #ff6b35;
        transform: translateY(-1.8px);
        box-shadow: 0 4.5px 18px rgba(255, 107, 53, 0.3);
        background: rgba(26, 26, 26, 0.6);
    }

    .role-option input[type="radio"]:checked + .role-card {
        border-color: #ff6b35;
        background: linear-gradient(135deg, rgba(255, 107, 53, 0.2) 0%, rgba(247, 147, 30, 0.2) 100%);
        box-shadow: 0 0 18px rgba(255, 107, 53, 0.4);
    }

    .role-icon {
        font-size: 36px;
        margin-bottom: 9px;
        filter: drop-shadow(0 0 9px rgba(255, 107, 53, 0.3));
    }

    .role-title {
        font-weight: 600;
        color: #e0e0e0;
        margin-bottom: 4.5px;
        font-size: 13.5px;
    }

    .role-desc {
        font-size: 10.8px;
        color: #999;
    }

    .password-strength {
        font-size: 10.8px;
        margin-top: 4.5px;
        font-weight: 500;
    }

    .btn-register {
        width: 100%;
        padding: 12.6px;
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        border: none;
        border-radius: 7.2px;
        font-size: 14.4px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        text-transform: uppercase;
        letter-spacing: 0.45px;
        box-shadow: 0 3.6px 18px rgba(255, 107, 53, 0.4);
    }

    .btn-register:hover {
        transform: translateY(-1.8px);
        box-shadow: 0 5.4px 27px rgba(255, 107, 53, 0.6);
    }

    .btn-register:active {
        transform: translateY(0);
    }

    .alert {
        padding: 13.5px;
        border-radius: 7.2px;
        margin-bottom: 18px;
        font-size: 12.6px;
        font-weight: 500;
        backdrop-filter: blur(9px);
    }

    .alert-error {
        background: rgba(255, 60, 60, 0.15);
        color: #ff6666;
        border: 1.8px solid rgba(255, 60, 60, 0.4);
    }

    .alert-success {
        background: rgba(76, 175, 80, 0.15);
        color: #66dd88;
        border: 1.8px solid rgba(76, 175, 80, 0.4);
    }

    .alert a {
        color: inherit;
        text-decoration: underline;
        font-weight: bold;
    }

    .links {
        text-align: center;
        margin-top: 22.5px;
        padding-top: 18px;
        border-top: 1.8px solid rgba(255, 107, 53, 0.2);
    }

    .links a {
        color: #ff6b35;
        text-decoration: none;
        font-size: 12.6px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .links a:hover {
        opacity: 0.7;
        text-shadow: 0 0 9px rgba(255, 107, 53, 0.5);
    }

    .links p {
        color: #999;
        font-size: 12.6px;
        margin-bottom: 7.2px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 13.5px;
    }

    /* Scrollbar styling for dark theme */
    ::-webkit-scrollbar {
        width: 9px;
    }

    ::-webkit-scrollbar-track {
        background: #0a0a0a;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        border-radius: 4.5px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #ff6b35;
    }

    @media (max-width: 580px) {
        .register-container {
            padding: 27px 18px;
        }

        .role-selector,
        .form-row {
            grid-template-columns: 1fr;
        }

        .logo h1 {
            font-size: 25.2px;
        }

        body {
            padding: 13.5px;
        }
    }
</style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>👑 MARKET  X</h1>
            <p>Super Role Registration</p>
        </div>

        <div class="warning-box">
            <strong>⚠️ ADMIN/OWNER REGISTRATION</strong>
            This page is for creating Admin and Shop Owner accounts. For customer registration, please use the <a href="register.php">regular registration page</a>.
        </div>

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
                echo "✅ {$registered_role} account created successfully! You can now <a href='login.php'>login here</a>.";
                ?>
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
                strengthDiv.style.color = '#ff6666';
            } else if (password.length < 8) {
                strengthDiv.textContent = '⚠️ Weak password';
                strengthDiv.style.color = '#ffaa33';
            } else if (password.length < 10) {
                strengthDiv.textContent = '✅ Good password';
                strengthDiv.style.color = '#66dd88';
            } else {
                strengthDiv.textContent = '🔥 Strong password';
                strengthDiv.style.color = '#44cc77';
            }
        });

        // Real-time password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = '#44cc77';
                } else {
                    this.style.borderColor = '#ff6666';
                }
            } else {
                this.style.borderColor = 'rgba(255, 107, 53, 0.2)';
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
                this.nextElementSibling.style.transform = 'scale(1.045)';
            });
        });
    </script>
</body>
</html>


