<?php
/**
 * logout.php - Session Termination Handler
 * * Cleans up the session data and redirects the user to the login page.
 */

// Include the sessions file where the logout function is defined
// NOTE: sessions.php implicitly calls session_start()
require_once __DIR__ . '/includes/sessions.php';
require_once __DIR__ . '/includes/functions.php'; // Included for completeness, though not strictly needed here

// ------------------------------------------------------------------
// 1. Execute Logout
// ------------------------------------------------------------------

// Call the function to destroy the session and handle redirection
logoutUser();

// The script will halt here due to the exit; within logoutUser()
?>