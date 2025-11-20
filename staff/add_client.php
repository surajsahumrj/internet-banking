<?php
/**
 * add_client.php (Staff) - Page for staff to manually register a new client user.
 * * Automatically assigns the 'Client' role.
 */

$required_role = 'Staff';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$client_role_id = null;
$error_message = '';
$success_message = '';
$client_role_name = 'Client';

// 2. Fetch the Client Role ID
try {
    $stmt_role_id = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client'");
    $stmt_role_id->execute();
    $result_role_id = $stmt_role_id->get_result();
    if ($result_role_id->num_rows > 0) {
        $client_role_id = $result_role_id->fetch_assoc()['role_id'];
    } else {
        $error_message = "System Error: Client role definition not found. Cannot proceed.";
    }
    $stmt_role_id->close();
} catch (Exception $e) {
    $error_message = "Database error fetching client role ID.";
    error_log("Staff Add Client Role ID Error: " . $e->getMessage());
}


// 3. Handle POST Request (Form Submission)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error_message) && $client_role_id) {
    
    // Sanitize and validate inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // A. Basic Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error_message = "All fields marked with * must be filled.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif (strlen($password) < 8) {
        $error_message = "Initial password must be at least 8 characters long.";
    } else {
        
        // B. Check for Email Uniqueness
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $error_message = "This email address is already registered in the system.";
        } else {
            
            // C. Hash Password
            $hashed_password = hashPassword($password);

            // D. Allocate client-specific user_id and insert within a transaction
            try {
                $new_client_id = getNextUserId($conn, $client_role_id);

                $stmt_insert = $conn->prepare("INSERT INTO users 
                                            (user_id, role_id, first_name, last_name, email, phone, password_hash, is_active) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt_insert->bind_param("iisssssi", 
                                          $new_client_id,
                                          $client_role_id, 
                                          $first_name, 
                                          $last_name, 
                                          $email, 
                                          $phone, 
                                          $hashed_password,
                                          $is_active);

                if ($stmt_insert->execute()) {
                    $conn->commit();
                    $success_message = "New Client **" . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . "** (ID: $new_client_id) created successfully!";
                    
                    // Clear post data after success
                    $_POST = array(); 
                } else {
                    $conn->rollback();
                    $error_message = "Client creation failed. Database error: " . $conn->error;
                }
                $stmt_insert->close();
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Client creation failed: " . $e->getMessage();
            }
        }
        $stmt_check->close();
    }
}
$conn->close();

// Reuse posted values or set defaults for the form
$default_is_active = isset($_POST['is_active']) ? 'checked' : 'checked'; // Default to active
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Manually Register New Client</h1>
    <p>Create a user profile for a new bank client. Note: The client must be assigned a bank account separately.</p>
</div>

<div class="user-form-container" style="max-width: 600px; margin: 0 auto;">

    <?php if ($error_message): ?>
        <div class="message-box error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="message-box success-message">
            <?php echo htmlspecialchars($success_message); ?>
            <p><a href="client_details.php?id=<?php echo $new_client_id ?? ''; ?>">Proceed to Client Details / Open Account</a></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="add_client.php">
        
        <h2>Client Information</h2>
        <p style="font-size: 0.9em; color: var(--secondary-color);">Creating user role: **Client**</p>

        <div class="form-group">
            <label for="first_name">First Name <span class="required">*</span></label>
            <input type="text" id="first_name" name="first_name" required 
                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="last_name">Last Name <span class="required">*</span></label>
            <input type="text" id="last_name" name="last_name" required 
                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="email">Email Address (Login ID) <span class="required">*</span></label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" 
                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>

        <h2>Security & Status</h2>
        <div class="form-group">
            <label for="password">Initial Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" required minlength="8">
        </div>
        
        <div class="form-group checkbox-group">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $default_is_active; ?>>
            <label for="is_active" style="display: inline;">Account Active?</label>
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; margin-top: 20px;">Create Client Profile</button>
    </form>
</div>

<?php 
// NOTE: Ensure all necessary CSS styles are in assets/css/style.css
require_once __DIR__ . '/../includes/footer.php'; 
?>