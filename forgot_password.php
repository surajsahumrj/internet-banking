<?php
/**
 * forgot_password.php - Initiates the Password Reset Process
 * * Takes the user's email, validates it, generates a secure token, 
 * * and simulates sending an email with a reset link.
 */

require_once __DIR__ . '/includes/sessions.php'; // For session_start()
require_once __DIR__ . '/config/db_config.php'; // For connectDB()
require_once __DIR__ . '/includes/functions.php'; // For generateSecureToken()

// If logged in, redirect away
if (isset($_SESSION['user_id'])) {
    header('Location: client/dashboard.php');
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = connectDB();
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = 'error';
    } else {
        
        // 1. Check if the email exists in the users table
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND is_active = TRUE");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            
            // 2. Generate Token and Expiry Time
            $token = generateSecureToken(64);
            // Set token to expire in 1 hour (3600 seconds)
            $expiry_time = date('Y-m-d H:i:s', time() + 3600); 

            // 3. Clear any existing tokens for this email (optional, but cleaner)
            $stmt_clear = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt_clear->bind_param("s", $email);
            $stmt_clear->execute();
            $stmt_clear->close();
            
            // 4. Insert the new token into the password_resets table
            $stmt_insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $email, $token, $expiry_time);
            
            if ($stmt_insert->execute()) {
                
                // 5. Success: Simulate Email Sending
                
                // IMPORTANT: In a real application, you would use a PHP mailing library (like PHPMailer) 
                // and a real SMTP server here.
                
                // Construct the reset link:
                // Note: The base URL ('http://localhost/...') needs to be adjusted based on your XAMPP setup
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . urlencode($token);
                
                // --- Simulation Message ---
                $message = "A password reset link has been sent to your email address (" . htmlspecialchars($email) . "). Please check your inbox and spam folder.";
                $message_type = 'success';
                
                // Debug/Local Test: Output the link so you can click it in XAMPP
                error_log("Password Reset Link for $email: " . $reset_link);
                // --------------------------
                
            } else {
                $message = "Could not generate reset token. Please try again.";
                $message_type = 'error';
            }
            $stmt_insert->close();
            
        } else {
            // Security Best Practice: Always show a generic success message 
            // even if the email doesn't exist to prevent enumeration attacks.
            $message = "If an account exists for that email, a password reset link has been sent.";
            $message_type = 'success';
        }

        $stmt_check->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SecureBank</title>
    <link rel="stylesheet" href="assets/css/style.css"> 
</head>
<body>
    <div class="login-container">
        <h1>Forgot Your Password?</h1>
        <p>Enter your email address to receive a password reset link.</p>

        <?php if ($message): ?>
            <div class="message-box <?php echo $message_type == 'error' ? 'error-message' : 'success-message'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autocomplete="email" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn-primary" style="width: 100%;">Send Reset Link</button>
        </form>
        
        <p class="links" style="margin-top: 20px;">
            <a href="login.php">Back to Login</a>
        </p>
    </div>
</body>
</html>