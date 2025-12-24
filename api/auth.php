<?php
/**
 * ByteShop - Authentication API
 * Handles login and registration requests
 */

session_start();
require_once '../config/db.php';
require_once '../includes/session.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// ============================================
// LOGIN HANDLER
// ============================================
if ($action === 'login') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($email) || empty($password)) {
        header('Location: ../login.php?error=empty');
        exit;
    }

    try {
        // Check if user exists
        $stmt = $pdo->prepare("
            SELECT user_id, name, email, password, role, status 
            FROM users 
            WHERE email = ? 
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify password (using MD5 for college project)
        if ($user && md5($password) === $user['password']) {
            
            // Check if account is active
            if ($user['status'] !== 'active') {
                header('Location: ../login.php?error=inactive');
                exit;
            }

            // Set session
            set_user_session($user);

            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: ../admin/index.php');
                    break;
                case 'shop_owner':
                    header('Location: ../shop_owner/index.php');
                    break;
                case 'customer':
                    header('Location: ../customer/index.php');
                    break;
                default:
                    header('Location: ../login.php?error=invalid');
            }
            exit;

        } else {
            // Invalid credentials
            header('Location: ../login.php?error=invalid');
            exit;
        }

    } catch(PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        header('Location: ../login.php?error=server');
        exit;
    }
}


// ============================================
// REGISTRATION HANDLER (CUSTOMER ONLY)
// ============================================
elseif ($action === 'register') {
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate required fields
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        header('Location: ../register.php?error=empty');
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../register.php?error=invalid_email');
        exit;
    }

    // Validate password length
    if (strlen($password) < 6) {
        header('Location: ../register.php?error=weak_password');
        exit;
    }

    // Check password match
    if ($password !== $confirm_password) {
        header('Location: ../register.php?error=password_mismatch');
        exit;
    }

    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            header('Location: ../register.php?error=email_exists');
            exit;
        }

        // Hash password (MD5 for college project)
        $hashed_password = md5($password);

        // Insert new customer
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, role, status) 
            VALUES (?, ?, ?, ?, 'customer', 'active')
        ");
        
        $stmt->execute([$name, $email, $phone, $hashed_password]);

        // Registration successful
        header('Location: ../login.php?success=registered');
        exit;

    } catch(PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        header('Location: ../register.php?error=server');
        exit;
    }
}


// ============================================
// INVALID ACTION
// ============================================
else {
    header('Location: ../login.php');
    exit;
}



?>

