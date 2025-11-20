<?php
/**
 * add_user.php (Admin) - Page to manually register new users (Client, Staff, Admin).
 * * Admins can set the role and initial password for the user.
 */

$required_role = 'Admin';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$error_message = '';
$success_message = '';
$roles = [];

// 2. Fetch all available User Roles from the database
$conn = connectDB();
try {
    $stmt_roles = $conn->prepare("SELECT role_id, role_name FROM user_roles");
    $stmt_roles->execute();
    $result_roles = $stmt_roles->get_result();
    
    while ($row = $result_roles->fetch_assoc()) {
        // Store roles as [role_id => role_name]
        $roles[$row['role_id']] = $row['role_name'];
    }
    $stmt_roles->close();
} catch (Exception $e) {
    $error_message = "Database error fetching user roles.";
    error_log("Add User Roles DB Error: " . $e->getMessage());
}


// 3. Handle POST Request (Form Submission)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error_message)) {
    
    // Sanitize and validate inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // A. Basic Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || !isset($roles[$role_id])) {
        $error_message = "All required fields must be filled and a valid role must be selected.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
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

            // D. Allocate role-specific user_id and insert within a transaction
            try {
                $new_user_id = getNextUserId($conn, $role_id);

                $stmt_insert = $conn->prepare("INSERT INTO users 
                                            (user_id, role_id, first_name, last_name, email, phone, password_hash, is_active) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt_insert->bind_param("iisssssi", 
                                          $new_user_id,
                                          $role_id, 
                                          $first_name, 
                                          $last_name, 
                                          $email, 
                                          $phone, 
                                          $hashed_password,
                                          $is_active);

                if ($stmt_insert->execute()) {
                    $conn->commit();
                    $success_message = "New " . htmlspecialchars($roles[$role_id]) . " user '" . htmlspecialchars($first_name) . "' created successfully! (ID: $new_user_id)";
                    // Clear post data after success
                    $_POST = array(); 
                } else {
                    $conn->rollback();
                    $error_message = "User creation failed. Database error: " . $conn->error;
                }
                $stmt_insert->close();
            } catch (Exception $e) {
                // If getNextUserId threw, rollback and set error
                $conn->rollback();
                $error_message = "User creation failed: " . $e->getMessage();
            }
        }
        $stmt_check->close();
    }
}
$conn->close();

// Reuse posted values or set defaults for the form
$default_role_id = $_POST['role_id'] ?? 3; // Default to Client
$default_is_active = isset($_POST['is_active']) ? 'checked' : 'checked'; // Default to active
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Add New System User</h1>
    <p>Use this form to register new staff, admin, or client accounts manually.</p>
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
            <p><a href="manage_users.php?role=<?php echo strtolower($roles[$role_id] ?? 'client'); ?>">View all <?php echo htmlspecialchars($roles[$role_id] ?? 'Client'); ?>s</a></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="add_user.php">
        
        <h2>User Information</h2>
        <div class="form-group">
            <label for="role_id">User Role <span class="required">*</span></label>
            <select id="role_id" name="role_id" required>
                <?php foreach ($roles as $id => $name): ?>
                    <option value="<?php echo $id; ?>" <?php echo ($id == $default_role_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

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

        <h2>Security</h2>
        <div class="form-group">
            <label for="password">Initial Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" required minlength="8">
        </div>
        
        <div class="form-group checkbox-group">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $default_is_active; ?>>
            <label for="is_active" style="display: inline;">Account Active?</label>
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; margin-top: 20px;">Create User Account</button>
    </form>
</div>

<?php 
// NOTE: Add .required { color: var(--danger-color); } to style.css
require_once __DIR__ . '/../includes/footer.php'; 
?>