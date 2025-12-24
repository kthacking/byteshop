<?php
/**
 * ByteShop - Logout Handler
 * Destroys user session and redirects to login
 */

session_start();
require_once 'includes/session.php';

// Destroy the session
destroy_session();

// Redirect to login page
header('Location: login.php?success=logout');
exit;
?>