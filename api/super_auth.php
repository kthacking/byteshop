<?php
/**
 * ByteShop - Super Authentication API
 * Handles Admin and Shop Owner registration ONLY
 * 
 * IMPORTANT: This file should be protected or removed in production
 */

session_start();
require_once '../config/db.php';
require_once '../includes/session.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../super_register.php');
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// ============================================
// SUPER REGISTRATION HANDLER (ADMIN & SHOP OWNER ONLY)
// ============================================
if ($action === 'super_register') {
    
    $role = isset($_POST['role']) ? clean_input($_POST['role']) : '';
    $name = isset($_POST['name']) ? clean_input($_POST['name']) : '';
    $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? clean_input($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // ============================================
    // VALIDATION CHECKS
    // ============================================

    // Validate role (ONLY admin or shop_owner allowed)
    if (!in_array($role, ['admin', 'shop_owner'], true)) {
        header('Location: ../super_register.php?error=invalid_role');
        exit;
    }

    // Validate required fields
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        header('Location: ../super_register.php?error=empty');
        exit;
    }

    // Validate name length
    if (strlen($name) < 3) {
        header('Location: ../super_register.php?error=short_name');
        exit;
    }

    // Validate name contains only letters and spaces
    if (!preg_match("/^[a-zA-Z ]+$/", $name)) {
        header('Location: ../super_register.php?error=invalid_name');
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../super_register.php?error=invalid_email');
        exit;
    }

    // Validate password length
    if (strlen($password) < 6) {
        header('Location: ../super_register.php?error=weak_password');
        exit;
    }

    // Check password match
    if ($password !== $confirm_password) {
        header('Location: ../super_register.php?error=password_mismatch');
        exit;
    }

    // Validate phone number format (optional but if provided must be valid)
    if (!empty($phone)) {
        $phone = preg_replace('/[^0-9]/', '', $phone); // Remove non-numeric
        if (strlen($phone) < 10) {
            header('Location: ../super_register.php?error=invalid_phone');
            exit;
        }
    }

    // ============================================
    // DATABASE OPERATIONS
    // ============================================

    try {
        // Check if email already exists in database
        $stmt = $pdo->prepare("SELECT user_id, email, role FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch();
        
        if ($existing_user) {
            // Email already registered
            header('Location: ../super_register.php?error=email_exists&role=' . $existing_user['role']);
            exit;
        }

        // Check if admin already exists (optional security - only one admin)
        if ($role === 'admin') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
            $stmt->execute();
            $result = $stmt->fetch();
            
            // Uncomment this block if you want to restrict to single admin
            /*
            if ($result['admin_count'] > 0) {
                header('Location: ../super_register.php?error=admin_exists');
                exit;
            }
            */
        }

        // Hash password using MD5 (for college project simplicity)
        // In production, use: password_hash($password, PASSWORD_BCRYPT)
        $hashed_password = md5($password);

        // Prepare INSERT statement
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        // Execute insertion
        $result = $stmt->execute([
            $name,
            $email,
            $phone,
            $hashed_password,
            $role
        ]);

        if ($result) {
            // Get the newly created user ID
            $new_user_id = $pdo->lastInsertId();
            
            // Log the registration (optional - for admin monitoring)
            error_log("New {$role} registered: {$email} (ID: {$new_user_id})");
            
            // Registration successful - redirect with success message
            header('Location: ../super_register.php?success=registered&role=' . $role);
            exit;
        } else {
            // Insert failed
            header('Location: ../super_register.php?error=insert_failed');
            exit;
        }

    } catch(PDOException $e) {
        // Database error occurred
        error_log("Super Registration Database Error: " . $e->getMessage());
        error_log("Failed for email: " . $email . " | Role: " . $role);
        
        header('Location: ../super_register.php?error=server');
        exit;
    }
}

// ============================================
// INVALID ACTION
// ============================================
else {
    header('Location: ../super_register.php?error=invalid_action');
    exit;
}
?>