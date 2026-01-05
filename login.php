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

.login-container {
    background: rgba(26, 26, 26, 0.8);
    backdrop-filter: blur(10px);
    padding: 36px;
    border-radius: 14px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
    width: 100%;
    max-width: 360px;
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
    filter: drop-shadow(0 2px 6px rgba(255, 107, 53, 0.4));
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

input[type="email"],
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

input[type="email"]::placeholder,
input[type="password"]::placeholder {
    color: #666;
}

input[type="email"]:focus,
input[type="password"]:focus {
    outline: none;
    border-color: #ff6b35;
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
}

.btn-login {
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

.btn-login:hover {
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

.alert-success {
    background: rgba(0, 212, 170, 0.15);
    color: #00d4aa;
    border-color: rgba(0, 212, 170, 0.3);
}

.links {
    text-align: center;
    margin-top: 18px;
}

.links a {
    color: #ff6b35;
    text-decoration: none;
    font-size: 12.6px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.links a:hover {
    text-decoration: underline;
    color: #f7931e;
}

.divider {
    text-align: center;
    margin: 18px 0;
    color: #777;
    font-size: 12.6px;
}

.demo-credentials {
    background: rgba(255, 255, 255, 0.05);
    padding: 13.5px;
    border-radius: 7.2px;
    margin-top: 18px;
    font-size: 11.7px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.demo-credentials h4 {
    margin-bottom: 9px;
    color: #ffffff;
    font-size: 12.6px;
    font-weight: 700;
}

.demo-credentials p {
    margin: 4.5px 0;
    color: #a0a0a0;
    font-size: 11.7px;
}

.demo-credentials strong {
    background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 600;
}
</style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🛒 ByteShop</h1>
            <p>Multi-Vendor E-Commerce Platform</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
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
            </div>
        <?php endif; ?>

        <?php if ($success === 'registered'): ?>
            <div class="alert alert-success">
                Registration successful! Please login.
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="api/auth.php">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <div class="divider">OR</div>

        <div class="links">
            <p>Don't have an account? <a href="register.php">Register as Customer</a></p><br>
            <p> <a href="super_register.php">Register as admin / owner</a></p>
        </div>

        <!-- Demo Credentials -->
        <!-- <div class="demo-credentials">
            <h4>🔑 Demo Credentials:</h4>
            <p><strong>Admin:</strong> admin@byteshop.com / admin123</p>
            <p><strong>Shop Owner:</strong> john@shop.com / pass123</p>
            <p><strong>Customer:</strong> alice@mail.com / pass123</p>
        </div> -->
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