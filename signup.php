<?php
/**
 * signup.php - Client Self-Registration Page
 * * Allows new clients to create a user account.
 * * NOTE: This page does NOT automatically open a bank account yet; 
 * * that process is typically handled by staff/admin approval later.
 */

// Include required core files
require_once __DIR__ . '/includes/sessions.php'; // For session_start()
require_once __DIR__ . '/config/db_config.php'; // For connectDB()
require_once __DIR__ . '/includes/functions.php'; // For hashPassword()

// If user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: client/dashboard.php');
    exit;
}

$error_message = '';
$success_message = '';

// Ensure a CSRF token exists for the form
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // Fallback if random_bytes is not available
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Quick CSRF check before touching DB
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid form submission (CSRF token). Please refresh the page and try again.";
    } else {
        // 1. Sanitize and Validate Input (server-side)
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Basic required fields
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
            $error_message = "All fields are required.";
        }

        // Normalize and simple validations
        $first_name = strip_tags($first_name);
        $last_name = strip_tags($last_name);
        $email = strtolower($email);

        // Name: allow unicode letters, spaces, hyphens and apostrophes, 2-50 chars
        if (empty($error_message) && !preg_match("/^[\p{L} '\-]{2,50}$/u", $first_name)) {
            $error_message = "First name contains invalid characters or is too short.";
        }
        if (empty($error_message) && !preg_match("/^[\p{L} '\-]{2,50}$/u", $last_name)) {
            $error_message = "Last name contains invalid characters or is too short.";
        }

        // Email validation
        if (empty($error_message) && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254)) {
            $error_message = "Invalid email address.";
        }

        // Phone (optional) - allow digits, spaces, parentheses, +, -, 7-20 chars
        if (empty($error_message) && !empty($phone) && !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) {
            $error_message = "Phone number has invalid characters.";
        }

        // Password checks
        if (empty($error_message) && $password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        }
        if (empty($error_message) && strlen($password) < 8) {
            $error_message = "Password must be at least 8 characters long.";
        }
        if (empty($error_message)) {
            $pw_errors = [];
            if (!preg_match('/[A-Z]/', $password)) { $pw_errors[] = 'one uppercase letter'; }
            if (!preg_match('/[a-z]/', $password)) { $pw_errors[] = 'one lowercase letter'; }
            if (!preg_match('/[0-9]/', $password)) { $pw_errors[] = 'one digit'; }
            if (!preg_match('/[\W_]/', $password)) { $pw_errors[] = 'one special character'; }
            if (!empty($pw_errors)) {
                $error_message = 'Password must contain at least ' . implode(', ', $pw_errors) . '.';
            }
        }

        // Proceed to DB only if validation passed
        if (empty($error_message)) {
            // 2. Establish Database Connection
            $conn = connectDB();

            // Begin transaction for user creation
            $conn->begin_transaction();

            // 3. Check if email already exists
            $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error_message = "This email address is already registered.";
            }

            if (empty($error_message)) {
                // 4. Get the 'Client' Role ID
                $stmt_role = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client'");
                $stmt_role->execute();
                $result_role = $stmt_role->get_result();

                if ($result_role->num_rows === 0) {
                    $error_message = "System error: Client role not found.";
                } else {
                    $role = $result_role->fetch_assoc();
                    $client_role_id = $role['role_id'];

                    // 5. Hash Password
                    $hashed_password = hashPassword($password);

                    // 6. Allocate client-specific user_id and insert within a transaction
                    try {
                        $new_user_id = getNextUserId($conn, $client_role_id);

                        $stmt_insert = $conn->prepare("INSERT INTO users 
                                                    (user_id, role_id, first_name, last_name, email, phone, password_hash) 
                                                    VALUES (?, ?, ?, ?, ?, ?, ?)");

                        $stmt_insert->bind_param("iisssss", 
                                                  $new_user_id,
                                                  $client_role_id, 
                                                  $first_name, 
                                                  $last_name, 
                                                  $email, 
                                                  $phone, 
                                                  $hashed_password);

                        if ($stmt_insert->execute()) {
                            $conn->commit();
                            $success_message = "Registration successful! You can now log in. (ID: $new_user_id)";
                            // Clear post data to prevent form resubmission
                            $_POST = array();
                            // regenerate CSRF token for safety
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        } else {
                            $conn->rollback();
                            $error_message = "Registration failed. Please try again. Error: " . $conn->error;
                        }
                        $stmt_insert->close();
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_message = "Registration failed: " . $e->getMessage();
                    }
                }
                $stmt_role->close();
            }
            $stmt_check->close();
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
    <title>Client Sign Up - SecureBank</title>
    <link rel="stylesheet" href="assets/css/style.css"> 
</head>
<body>
    <div class="login-container" style="max-width: 500px;">
        <h1>Client Registration</h1>
        <p>Open your online banking account today.</p>

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

        <?php if (!$success_message): ?>
        <form method="POST" action="signup.php" id="signupForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone Number (Optional)</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password (Min 8 characters)</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn-primary" style="width: 100%;">Register Account</button>
        </form>
        <?php endif; ?>

        <p class="links" style="margin-top: 20px;">
            <a href="login.php">Already have an account? Log In</a>
        </p>
    </div>
</body>
<script>
// Minimal client-side validation to improve UX
document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('signupForm');
    if (!form) return;

    form.addEventListener('submit', function(e){
        const pwd = form.querySelector('#password').value || '';
        const cpwd = form.querySelector('#confirm_password').value || '';
        const phone = form.querySelector('#phone').value || '';
        const email = form.querySelector('#email').value || '';

        if (pwd !== cpwd) {
            alert('Passwords do not match.');
            e.preventDefault();
            return;
        }
        if (pwd.length < 8) {
            alert('Password must be at least 8 characters long.');
            e.preventDefault();
            return;
        }
        // basic strength: uppercase, lowercase, digit, special
        const strengthChecks = [/[A-Z]/, /[a-z]/, /[0-9]/, /[^A-Za-z0-9]/];
        const missing = strengthChecks.filter(rx => !rx.test(pwd));
        if (missing.length > 0) {
            alert('Password should include uppercase, lowercase, digit and special character.');
            e.preventDefault();
            return;
        }
        if (phone && !/^[0-9+()\-\s]{7,20}$/.test(phone)) {
            alert('Phone number has invalid characters.');
            e.preventDefault();
            return;
        }
        // simple email validation
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
            alert('Please enter a valid email address.');
            e.preventDefault();
            return;
        }
    });
});
</script>
</html>