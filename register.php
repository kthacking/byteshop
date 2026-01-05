<?php
/**
 * ByteShop - Customer Registration Page
 */
session_start();
require_once 'includes/session.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect_to_dashboard();
}

// Get error message if any
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ByteShop</title>
  <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 18px;
        }

        .register-container {
            background: rgba(26, 26, 26, 0.9);
            backdrop-filter: blur(10px);
            padding: 36px;
            border-radius: 14px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            width: 100%;
            max-width: 405px;
            border: 1px solid rgba(255, 107, 53, 0.2);
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
            font-size: 28.8px;
            margin-bottom: 4.5px;
            font-weight: 800;
            filter: drop-shadow(0 2px 8px rgba(255, 107, 53, 0.3));
        }

        .logo p {
            color: #a0a0a0;
            font-size: 12.6px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7.2px;
            color: #b0b0b0;
            font-weight: 600;
            font-size: 12.6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"] {
            width: 100%;
            padding: 10.8px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 7.2px;
            font-size: 12.6px;
            transition: all 0.3s ease;
            color: #e0e0e0;
        }

        input::placeholder {
            color: #666;
        }

        input:focus {
            outline: none;
            border-color: #ff6b35;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .password-strength {
            font-size: 10.8px;
            margin-top: 4.5px;
            color: #a0a0a0;
            font-weight: 500;
        }

        .btn-register {
            width: 100%;
            padding: 10.8px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border: none;
            border-radius: 7.2px;
            font-size: 14.4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.5);
        }

        .alert {
            padding: 10.8px;
            border-radius: 7.2px;
            margin-bottom: 18px;
            font-size: 12.6px;
            border: 1px solid;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border-color: rgba(255, 71, 87, 0.3);
        }

        .links {
            text-align: center;
            margin-top: 18px;
        }

        .links p {
            color: #a0a0a0;
            font-size: 12.6px;
        }

        .links a {
            color: #ff6b35;
            text-decoration: none;
            font-size: 12.6px;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: #f7931e;
            text-decoration: underline;
        }

        .info-box {
            background: rgba(59, 130, 246, 0.15);
            padding: 13.5px;
            border-radius: 7.2px;
            margin-bottom: 18px;
            font-size: 11.7px;
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            font-weight: 500;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 13.5px;
        }

        @media (max-width: 480px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .register-container {
                padding: 27px;
            }

            .logo h1 {
                font-size: 25.2px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>🛒 ByteShop</h1>
            <p>Create Your Customer Account</p>
        </div>

        <div class="info-box">
            ℹ️ Register as a customer to start shopping from multiple vendors!
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php
                switch($error) {
                    case 'empty':
                        echo 'Please fill in all required fields!';
                        break;
                    case 'email_exists':
                        echo 'This email is already registered!';
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
                    default:
                        echo 'Registration failed. Please try again.';
                }
                ?>
            </div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="api/auth.php">
            <input type="hidden" name="action" value="register">
            
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" placeholder="Optional">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required minlength="6">
                    <div class="password-strength" id="passwordStrength"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
            </div>

            <button type="submit" class="btn-register">Create Account</button>
        </form>

        <div class="links">
            <p>Already have an account? <a href="login.php">Login Here</a></p>
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
                strengthDiv.style.color = '#ff4757';
            } else if (password.length < 8) {
                strengthDiv.textContent = '⚠️ Weak password';
                strengthDiv.style.color = '#f7931e';
            } else {
                strengthDiv.textContent = '✅ Good password';
                strengthDiv.style.color = '#00d4aa';
            }
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Check empty fields
            if (!name || !email || !password || !confirmPassword) {
                e.preventDefault();
                alert('Please fill in all required fields!');
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

            // Check email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address!');
                return;
            }
        });
    </script>
</body>
</html>