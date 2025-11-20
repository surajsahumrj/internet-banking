<?php
/**
 * profile.php (Client) - Allows the logged-in client to view and update their personal details.
 */

$required_role = 'Client';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$client_id = $_SESSION['user_id'];
$client_info = null;
$message = '';
$message_type = '';


// 2. Fetch Client Info (Initial Load and after POST)
try {
    $stmt_user = $conn->prepare("SELECT first_name, last_name, email, phone, created_at
                                  FROM users 
                                  WHERE user_id = ?");
    $stmt_user->bind_param("i", $client_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user->num_rows === 1) {
        $client_info = $result_user->fetch_assoc();
    } else {
        $message = "Error: Your user profile could not be retrieved.";
        $message_type = 'error';
        goto render_page;
    }
    $stmt_user->close();

} catch (Exception $e) {
    $message = "Database error fetching profile details.";
    $message_type = 'error';
    error_log("Client Profile Fetch DB Error: " . $e->getMessage());
    goto render_page;
}

// 3. Handle POST Request (Update Details)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $client_info) {
    
    // Sanitize inputs
    $first_name = trim($_POST['first_name'] ?? $client_info['first_name']);
    $last_name = trim($_POST['last_name'] ?? $client_info['last_name']);
    $email = trim($_POST['email'] ?? $client_info['email']);
    $phone = trim($_POST['phone'] ?? $client_info['phone']);

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please fill all required fields with valid data.";
        $message_type = 'error';
    } else {
        // Check for Email Uniqueness (if email changed)
        if ($email !== $client_info['email']) {
             $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
             $stmt_check->bind_param("si", $email, $client_id);
             $stmt_check->execute();
             $stmt_check->store_result();
             if ($stmt_check->num_rows > 0) {
                 $message = "Error: The new email is already linked to another account.";
                 $message_type = 'error';
                 $stmt_check->close();
                 goto skip_update;
             }
             $stmt_check->close();
        }

        // Perform Update
        $stmt_update = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE user_id=?");
        $stmt_update->bind_param("ssssi", $first_name, $last_name, $email, $phone, $client_id);

        if ($stmt_update->execute()) {
            $message = "Your profile details have been updated successfully!";
            $message_type = 'success';
            
            // Refresh local $client_info data
            $client_info['first_name'] = $first_name; $client_info['last_name'] = $last_name; 
            $client_info['email'] = $email; $client_info['phone'] = $phone;
        } else {
            $message = "Failed to update profile details: " . $conn->error;
            $message_type = 'error';
        }
        $stmt_update->close();
    }
    
    skip_update:
}

render_page:
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>My Profile</h1>
    <p>View and update your personal information.</p>
</div>

<div class="user-form-container" style="max-width: 550px; margin: 0 auto;">

    <?php if ($message): ?>
        <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($client_info): ?>
    <div class="widget">
        <h2>Personal Details</h2>
        <form method="POST" action="profile.php">
            
            <div class="form-group">
                <label for="first_name">First Name <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" required 
                       value="<?php echo htmlspecialchars($client_info['first_name']); ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name" required 
                       value="<?php echo htmlspecialchars($client_info['last_name']); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email Address (Login ID) <span class="required">*</span></label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($client_info['email']); ?>">
                <p style="font-size: 0.8em; color: var(--danger-color); margin-top: 5px;">Changing your email will change your login ID.</p>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?php echo htmlspecialchars($client_info['phone']); ?>">
            </div>
            
            <p style="font-size: 0.9em; color: var(--secondary-color); margin-bottom: 15px;">
                Account created on: **<?php echo formatDate($client_info['created_at'], 'F j, Y'); ?>**
            </p>

            <button type="submit" class="btn-primary" style="width: 100%;">Save Profile Changes</button>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="widget" style="margin-top: 20px;">
        <h2>Security</h2>
        <a href="change_password.php" class="btn-secondary" style="width: 100%; display: block; text-align: center;">Change My Password</a>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>