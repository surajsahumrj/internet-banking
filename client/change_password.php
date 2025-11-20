<?php
/**
 * change_password.php (Client) - Allows the client to securely change their login password.
 * * Requires validation of the existing password.
 */

$required_role = 'Client';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php'; // For hashPassword()

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$client_id = $_SESSION['user_id'];
$message = '';
$message_type = '';


// 2. Handle POST Request (Password Change)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // A. Basic Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "All password fields are required.";
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirmation do not match.";
        $message_type = 'error';
    } elseif (strlen($new_password) < 8) {
        $message = "New password must be at least 8 characters long.";
        $message_type = 'error';
    } else {
        
        // B. Fetch current password hash for verification
        $stmt_fetch = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt_fetch->bind_param("i", $client_id);
        $stmt_fetch->execute();
        $result = $stmt_fetch->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stored_hash = $user['password_hash'];

            // C. Verify CURRENT password
            if (password_verify($current_password, $stored_hash)) {
                
                // D. Hash the NEW password
                $hashed_new_password = hashPassword($new_password);
                
                // E. Update the database
                $stmt_update = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt_update->bind_param("si", $hashed_new_password, $client_id);

                if ($stmt_update->execute()) {
                    $message = "Your password has been changed successfully! You will use this new password next time you log in.";
                    $message_type = 'success';
                    // Clear fields after success
                    $_POST = array(); 
                } else {
                    $message = "Password update failed due to a system error: " . $conn->error;
                    $message_type = 'error';
                }
                $stmt_update->close();
                
            } else {
                $message = "The current password you entered is incorrect.";
                $message_type = 'error';
            }
        } else {
            $message = "User account error. Please contact support.";
            $message_type = 'error';
        }
        $stmt_fetch->close();
    }
}

$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Change Password</h1>
    <p>For security, you must provide your current password to set a new one.</p>
</div>

<div class="user-form-container" style="max-width: 550px; margin: 0 auto;">

    <?php if ($message): ?>
        <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="widget">
        <h2>Update Security Credentials</h2>
        <form method="POST" action="change_password.php">
            
            <div class="form-group">
                <label for="current_password">Current Password <span class="required">*</span></label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            </div>
            
            <hr>

            <div class="form-group">
                <label for="new_password">New Password (Min 8 characters) <span class="required">*</span></label>
                <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%;">Change Password</button>
        </form>
    </div>
    
    <p class="links" style="margin-top: 20px; text-align: center;">
        <a href="profile.php">&larr; Back to Profile</a>
    </p>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>