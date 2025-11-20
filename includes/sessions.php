<?php
/**
 * sessions.php - Session Management and Authentication
 * * * Handles the starting of a secure session and provides functions
 * for checking user authentication and authorization based on roles.
 */

// ------------------------------------------------------------------
// 1. Session Setup
// ------------------------------------------------------------------

// Start the session securely
// Note: It's best practice to call this at the very beginning of any script 
// that uses sessions, and before any HTML output.
session_start();


// ------------------------------------------------------------------
// 2. Authentication and Authorization Function
// ------------------------------------------------------------------

/**
 * Checks if a user is logged in and has the required role(s) to view the page.
 * * @param array|string $required_roles Array of valid role names (e.g., ['Admin', 'Staff']) or a single role string.
 * @param string $redirect_path The path to redirect to if authorization fails (defaults to /login.php).
 */
function checkAuth($required_roles, $redirect_path = 'login.php') {
    
    // Ensure the required roles input is an array for consistent checking
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    // Compute application base path so redirects work from subfolders
    $app_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $app_base = $app_base === '/' ? '' : $app_base;

    // Check 1: User Logged In?
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        // If not logged in, redirect to the main login page (respecting app base)
        $loc = $app_base . '/' . ltrim($redirect_path, '/');
        header('Location: ' . $loc);
        exit;
    }

    // Check 2: User Authorized? (Role Check)
    $user_role = $_SESSION['user_role'];
    
    // Check if the user's role is NOT found in the list of required roles
    if (!in_array($user_role, $required_roles)) {
        
        // --- Redirect Logic for Unauthorized Access ---
        // Redirect unauthorized users back to their own respective dashboard
        if ($user_role == 'Client') {
            $loc = $app_base . '/client/dashboard.php';
        } elseif ($user_role == 'Staff') {
            $loc = $app_base . '/staff/dashboard.php';
        } elseif ($user_role == 'Admin') {
            $loc = $app_base . '/admin/dashboard.php';
        } else {
            // Default redirect for unknown/malformed roles
            $loc = $app_base . '/' . ltrim($redirect_path, '/');
        }
        header('Location: ' . $loc);
        exit;
    }
    
    // If both checks pass, the script continues (user is logged in and authorized)
}

// ------------------------------------------------------------------
// 3. Logout Function (Optional, but useful)
// ------------------------------------------------------------------

/**
 * Destroys the current session and redirects the user to the login page.
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session
    session_destroy();

    // Redirect to the login page (respecting application base path)
    $app_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $app_base = $app_base === '/' ? '' : $app_base;
    header('Location: ' . $app_base . '/login.php');
    exit;
}
?>