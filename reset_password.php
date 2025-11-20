<?php
/**
 * reset_password.php - Allows users to set a new password using a valid reset token.
 */

require_once __DIR__ . '/includes/sessions.php'; // For session_start()
require_once __DIR__ . '/config/db_config.php'; // For connectDB()
require_once __DIR__ . '/includes/functions.php'; // For hashPassword()

// If logged in, redirect away
if (isset($_SESSION['user_id'])) {
    header('Location: client/dashboard.php');
    exit;
}

$token = $_GET['token'] ?? '';
$email = null;
$error_message = '';
$success_message = '';
$show_form = false;

// Ensure a CSRF token exists for the form
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// ------------------------------------------------------------------
// 1. Initial Token Validation Check (GET Request)
// ------------------------------------------------------------------
if (empty($token)) {
    $error_message = "Error: Password reset token is missing. Please use the link sent to your email.";
} else {
    $conn = connectDB();
    
    // Check if token is valid and not expired
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Token is valid and not expired
        $row = $result->fetch_assoc();
        $email = $row['email'];
        $show_form = true; // Show the password input form
    } else {
        $error_message = "Error: The reset link is invalid or has expired. Please request a new one.";
        // Delete invalid/expired token immediately
        $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt_delete->bind_param("s", $token);
        $stmt_delete->execute();
        $stmt_delete->close();
    }
    $stmt->close();
    $conn->close();
}


// ------------------------------------------------------------------
// 2. Handle Password Submission (POST Request)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $show_form) {

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid form submission (CSRF). Please try again.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error_message = "Password must be at least 8 characters long.";
        } else {
            // Strength checks
            $pw_errors = [];
            if (!preg_match('/[A-Z]/', $password)) { $pw_errors[] = 'one uppercase letter'; }
            if (!preg_match('/[a-z]/', $password)) { $pw_errors[] = 'one lowercase letter'; }
            if (!preg_match('/[0-9]/', $password)) { $pw_errors[] = 'one digit'; }
            if (!preg_match('/[\W_]/', $password)) { $pw_errors[] = 'one special character'; }
            if (!empty($pw_errors)) {
                $error_message = 'Password must contain at least ' . implode(', ', $pw_errors) . '.';
            }
        }

        // Re-validate token server-side before making changes (in case it expired between GET and POST)
        if (empty($error_message)) {
            $conn = connectDB();
            $stmt_check = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
            $stmt_check->bind_param("s", $token);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows !== 1) {
                $error_message = "The reset link is invalid or has expired. Please request a new one.";
            } else {
                $row = $result_check->fetch_assoc();
                $reset_email = $_POST['email_hidden'] ?? $email ?? $row['email'];

                // Ensure supplied hidden email matches token email
                if ($reset_email !== $row['email']) {
                    $error_message = "Email mismatch for this reset token.";
                }
            }

            $stmt_check->close();
        }

        // If still ok, update password and delete token inside transaction
        if (empty($error_message)) {
            $hashed_password = hashPassword($password);
            $conn->begin_transaction();
            try {
                $stmt_update = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                $stmt_update->bind_param("ss", $hashed_password, $reset_email);
                $stmt_update->execute();

                $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt_delete->bind_param("s", $token);
                $stmt_delete->execute();

                $conn->commit();

                $success_message = "Your password has been successfully reset! You can now log in with your new password.";
                $show_form = false; // Hide the form after successful reset
                // regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to update password. Please try the reset process again.";
                error_log("Password reset failed: " . $e->getMessage());
            }

            if (isset($stmt_update) && is_object($stmt_update)) { $stmt_update->close(); }
            if (isset($stmt_delete) && is_object($stmt_delete)) { $stmt_delete->close(); }
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SecureBank</title>
    <link rel="stylesheet" href="assets/css/style.css"> 
</head>
<body>
    <div class="login-container">
        <h1>Reset Your Password</h1>
        
        <?php if ($error_message): ?>
            <div class="message-box error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="message-box success-message">
                <?php echo htmlspecialchars($success_message); ?>
                <br><a href="login.php">Click here to log in.</a>
            </div>
        <?php endif; ?>

        <?php if ($show_form): ?>
            <p>Please enter and confirm your new password below.</p>
            <form method="POST" action="reset_password.php?token=<?php echo urlencode($token); ?>" id="resetForm">

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="email_hidden" value="<?php echo htmlspecialchars($email); ?>">

                <div class="form-group">
                    <label for="password">New Password (Min 8 characters)</label>
                    <input type="password" id="password" name="password" required>
                    <div class="form-error" id="password_error" style="color: #7f1d1d; font-size: 13px; margin-top:6px;"></div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <div class="form-error" id="confirm_error" style="color: #7f1d1d; font-size: 13px; margin-top:6px;"></div>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Set New Password</button>
            </form>
        <?php endif; ?>
        
        <p class="links" style="margin-top: 20px;">
            <a href="login.php">Back to Login</a>
        </p>
    </div>
</body>
</html>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('resetForm');
    if (!form) return;

    const pwd = document.getElementById('password');
    const cpwd = document.getElementById('confirm_password');
    const passErr = document.getElementById('password_error');
    const confErr = document.getElementById('confirm_error');

    function validatePass() {
        const val = pwd.value || '';
        const errors = [];
        if (val.length < 8) errors.push('at least 8 characters');
        if (!/[A-Z]/.test(val)) errors.push('an uppercase letter');
        if (!/[a-z]/.test(val)) errors.push('a lowercase letter');
        if (!/[0-9]/.test(val)) errors.push('a digit');
        if (!/[^A-Za-z0-9]/.test(val)) errors.push('a special character');
        passErr.textContent = errors.length ? 'Password must include ' + errors.join(', ') + '.' : '';
        return errors.length === 0;
    }

    function validateConfirm() {
        confErr.textContent = (pwd.value && cpwd.value && pwd.value !== cpwd.value) ? 'Passwords do not match.' : '';
        return pwd.value === cpwd.value;
    }

    pwd.addEventListener('input', function(){ validatePass(); validateConfirm(); });
    cpwd.addEventListener('input', validateConfirm);

    form.addEventListener('submit', function(e){
        passErr.textContent = '';
        confErr.textContent = '';
        const ok1 = validatePass();
        const ok2 = validateConfirm();
        if (!ok1 || !ok2) {
            e.preventDefault();
        }
    });
});
</script>