<?php
/**
 * ByteShop - Session Management
 * 
 * Handles user sessions and role-based access control
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Get current user ID
function get_user_id() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Get current user role
function get_user_role() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Get current user name
function get_user_name() {
    return isset($_SESSION['name']) ? $_SESSION['name'] : null;
}

// Check if user is admin
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is shop owner
function is_shop_owner() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'shop_owner';
}

// Check if user is customer
function is_customer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

// Redirect to login if not logged in
function require_login() {
    if (!is_logged_in()) {
        header('Location: /byteshop/login.php');
        exit;
    }
}

// Require specific role
function require_role($role) {
    require_login();
    if ($_SESSION['role'] !== $role) {
        header('Location: /byteshop/login.php?error=unauthorized');
        exit;
    }
}

// Require admin role
function require_admin() {
    require_role('admin');
}

// Require shop owner role
function require_shop_owner() {
    require_role('shop_owner');
}

// Require customer role
function require_customer() {
    require_role('customer');
}

// Redirect to appropriate dashboard based on role
function redirect_to_dashboard() {
    if (!is_logged_in()) {
        header('Location: /byteshop/login.php');
        exit;
    }
    
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /byteshop/admin/index.php');
            break;
        case 'shop_owner':
            header('Location: /byteshop/shop_owner/index.php');
            break;
        case 'customer':
            header('Location: /byteshop/customer/index.php');
            break;
        default:
            header('Location: /byteshop/login.php');
    }
    exit;
}

// Set user session
function set_user_session($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
}

// Destroy user session
function destroy_session() {
    session_unset();
    session_destroy();
}
?>