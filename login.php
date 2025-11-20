<?php
/**
 * login.php - Handles User Authentication
 * * This is the unified entry point for all users (Admin, Staff, Client).
 * * It uses sessions.php for session control and db_config.php for database interaction.
 */

// Include required core files
require_once __DIR__ . '/includes/sessions.php'; // For session_start() and session control
require_once __DIR__ . '/config/db_config.php'; // For the connectDB() function

// Check if user is already logged in. If so, redirect them to their dashboard immediately.
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'Admin') {
        header('Location: admin/dashboard.php');
    } elseif ($_SESSION['user_role'] == 'Staff') {
        header('Location: staff/dashboard.php');
    } else {
        header('Location: client/dashboard.php');
    }
    exit;
}

$error_message = ''; // Variable to hold login error messages

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Establish Database Connection
    $conn = connectDB();
    
    // 2. Sanitize and Validate Input
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // Do not sanitize password until after hashing

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        // 3. Prepare SQL Query to fetch user and role
        // We use a prepared statement to prevent SQL injection.
        $stmt = $conn->prepare("SELECT 
                                    u.user_id, 
                                    u.password_hash, 
                                    r.role_name 
                                FROM 
                                    users u 
                                JOIN 
                                    user_roles r ON u.role_id = r.role_id 
                                WHERE 
                                    u.email = ? AND u.is_active = TRUE");
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // 4. Verify Password Hash
            if (password_verify($password, $user['password_hash'])) {
                
                // 5. Success: Set Session Variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_role'] = $user['role_name'];
                
                // 6. Redirect based on Role
                if ($user['role_name'] == 'Admin') {
                    header('Location: admin/dashboard.php');
                } elseif ($user['role_name'] == 'Staff') {
                    header('Location: staff/dashboard.php');
                } else {
                    header('Location: client/dashboard.php');
                }
                exit;

            } else {
                // Password mismatch
                $error_message = "Invalid email or password.";
            }
        } else {
            // No user found or multiple users found (shouldn't happen with UNIQUE email constraint)
            $error_message = "Invalid email or password.";
        }

        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureBank - Login</title>
    <link rel="stylesheet" href="assets/css/style.css"> 
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-lg">
            <h1 style="margin-bottom: 8px;">Welcome to SecureBank</h1>
            <p style="color: var(--text-light); margin-bottom: 0;">Secure Online Banking Portal</p>
        </div>

        <?php if ($error_message): ?>
            <div class="message-box error-message">
                <span style="font-weight: 600;">⚠ Error:</span>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Email Address<span class="required">*</span></label>
                <input type="email" id="email" name="email" placeholder="your.email@example.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Password<span class="required">*</span></label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-primary btn-block" style="margin-top: var(--spacing-lg);">Log In</button>
        </form>
        
        <div style="margin-top: var(--spacing-lg); text-align: center; border-top: 1px solid var(--border-color); padding-top: var(--spacing-lg);">
            <p style="margin-bottom: var(--spacing-md); font-size: var(--font-size-sm);">
                <a href="forgot_password.php" style="color: var(--primary-color); font-weight: 600;">Forgot Password?</a>
            </p>
            <p style="margin-bottom: var(--spacing-md); color: var(--text-light); font-size: var(--font-size-sm);">
                New to SecureBank? 
                <a href="signup.php" style="color: var(--primary-color); font-weight: 600;">Sign up here</a>
            </p>
            <p style="margin-bottom: 0; color: var(--text-light); font-size: var(--font-size-sm);">
                Back to 
                <a href="index.php" style="color: var(--primary-color); font-weight: 600;">Homepage</a>
            </p>
        </div>
    </div>
</body>
</html>