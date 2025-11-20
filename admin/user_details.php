<?php
/**
 * user_details.php (Admin) - View, edit, and manage a specific user's details, role, and status.
 */

$required_role = 'Admin';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$user_id = (int)($_GET['id'] ?? 0);
$user = null;
$roles = [];
$message = '';
$message_type = '';

// Check for valid user ID; redirect to manage users if missing
if ($user_id <= 0) {
    header('Location: manage_users.php');
    exit;
}

// 2. Fetch User and Role Data
try {
    // Fetch all roles
    $stmt_roles = $conn->prepare("SELECT role_id, role_name FROM user_roles");
    $stmt_roles->execute();
    $result_roles = $stmt_roles->get_result();
    while ($row = $result_roles->fetch_assoc()) {
        $roles[$row['role_id']] = $row['role_name'];
    }
    $stmt_roles->close();

    // Fetch specific user details
    $stmt_user = $conn->prepare("SELECT u.*, r.role_name 
                                  FROM users u 
                                  JOIN user_roles r ON u.role_id = r.role_id 
                                  WHERE u.user_id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user->num_rows === 1) {
        $user = $result_user->fetch_assoc();
    } else {
        $message = "User not found.";
        $message_type = 'error';
        goto render_page;
    }
    $stmt_user->close();

} catch (Exception $e) {
    $message = "Database error fetching user details.";
    $message_type = 'error';
    error_log("User Details DB Error: " . $e->getMessage());
    goto render_page;
}


// 3. Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $user) {
    $action = $_POST['action'] ?? '';
    
    // --- ACTION A: Update Personal Details ---
    if ($action === 'update_details') {
        $first_name = trim($_POST['first_name'] ?? $user['first_name']);
        $last_name = trim($_POST['last_name'] ?? $user['last_name']);
        $email = trim($_POST['email'] ?? $user['email']);
        $phone = trim($_POST['phone'] ?? $user['phone']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Simple validation
        if (empty($first_name) || empty($last_name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please fill all required fields with valid data.";
            $message_type = 'error';
        } else {
            // Check if new email is unique (if it changed)
            if ($email !== $user['email']) {
                 $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                 $stmt_check->bind_param("si", $email, $user_id);
                 $stmt_check->execute();
                 $stmt_check->store_result();
                 if ($stmt_check->num_rows > 0) {
                     $message = "Error: The new email is already in use by another account.";
                     $message_type = 'error';
                     $stmt_check->close();
                     goto skip_update;
                 }
                 $stmt_check->close();
            }

            // Perform Update
            $stmt_update = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, is_active=? WHERE user_id=?");
            $stmt_update->bind_param("ssssii", $first_name, $last_name, $email, $phone, $is_active, $user_id);

            if ($stmt_update->execute()) {
                $message = "User details updated successfully!";
                $message_type = 'success';
                // Refresh $user data to show updated details
                $user['first_name'] = $first_name; $user['last_name'] = $last_name; 
                $user['email'] = $email; $user['phone'] = $phone; $user['is_active'] = $is_active;
            } else {
                $message = "Failed to update user details: " . $conn->error;
                $message_type = 'error';
            }
            $stmt_update->close();
        }
    }
    
    // --- ACTION B: Change User Role ---
    elseif ($action === 'change_role') {
        $new_role_id = (int)($_POST['new_role_id'] ?? 0);
        
        if (isset($roles[$new_role_id]) && $new_role_id !== $user['role_id']) {
            $stmt_role = $conn->prepare("UPDATE users SET role_id = ? WHERE user_id = ?");
            $stmt_role->bind_param("ii", $new_role_id, $user_id);
            
            if ($stmt_role->execute()) {
                $message = "User role successfully changed to " . htmlspecialchars($roles[$new_role_id]) . ".";
                $message_type = 'success';
                // Refresh user data
                $user['role_id'] = $new_role_id;
                $user['role_name'] = $roles[$new_role_id];
            } else {
                $message = "Failed to change user role: " . $conn->error;
                $message_type = 'error';
            }
            $stmt_role->close();
        } elseif ($new_role_id === $user['role_id']) {
            $message = "Role is already set to " . htmlspecialchars($user['role_name']) . ". No change necessary.";
            $message_type = 'error';
        } else {
            $message = "Invalid role selected.";
            $message_type = 'error';
        }
    }
    
    // --- ACTION C: Reset Password ---
    elseif ($action === 'reset_password') {
        $new_password = $_POST['new_password'] ?? '';
        
        if (strlen($new_password) < 8) {
            $message = "New password must be at least 8 characters long.";
            $message_type = 'error';
        } else {
            $hashed_password = hashPassword($new_password);
            
            $stmt_pwd = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt_pwd->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt_pwd->execute()) {
                $message = "Password successfully reset! User must log in with the new password.";
                $message_type = 'success';
                // Clear the password field in the POST array to prevent showing it
                unset($_POST['new_password']);
            } else {
                $message = "Failed to reset password: " . $conn->error;
                $message_type = 'error';
            }
            $stmt_pwd->close();
        }
    }
    
    skip_update: // Label for skipping update logic on error
}

render_page:
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Manage User: <?php echo htmlspecialchars($user['first_name'] ?? 'N/A') . ' ' . htmlspecialchars($user['last_name'] ?? ''); ?></h1>
    <p>Current Role: <span class="status-badge <?php echo strtolower($user['role_name'] ?? ''); ?>"><?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?></span></p>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($user): ?>
<div class="dashboard-widgets" style="grid-template-columns: 2fr 1fr;">
    <div class="widget">
        <h2>Personal and Status Details (ID: <?php echo $user_id; ?>)</h2>
        <form method="POST" action="user_details.php?id=<?php echo $user_id; ?>">
            <input type="hidden" name="action" value="update_details">

            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required 
                       value="<?php echo htmlspecialchars($user['first_name']); ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required 
                       value="<?php echo htmlspecialchars($user['last_name']); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($user['email']); ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?php echo htmlspecialchars($user['phone']); ?>">
            </div>
            <div class="form-group checkbox-group">
                <input type="checkbox" id="is_active" name="is_active" value="1" 
                       <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                <label for="is_active" style="display: inline;">Account is Active / Enabled</label>
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Update Details & Status</button>
        </form>
    </div>

    <div class="widget">
        
        <h2>Security and Role Actions</h2>
        
        <form method="POST" action="user_details.php?id=<?php echo $user_id; ?>" style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px dashed var(--border-light);">
            <input type="hidden" name="action" value="change_role">
            <h3>Change Role</h3>
            <div class="form-group">
                <label for="new_role_id">Select New Role</label>
                <select id="new_role_id" name="new_role_id" required>
                    <?php foreach ($roles as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php echo ($id == $user['role_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="background-color: var(--secondary-color);">Change Role</button>
        </form>
        
        <form method="POST" action="user_details.php?id=<?php echo $user_id; ?>">
            <input type="hidden" name="action" value="reset_password">
            <h3>Admin Password Reset</h3>
            <p style="font-size: 0.9em; color: var(--danger-color);">This forces an immediate password change.</p>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
            </div>
            <button type="submit" class="btn-primary btn-logout" style="width: 100%;">Force Reset Password</button>
        </form>

    </div>
</div>
<?php endif; ?>

<?php 
// NOTE: Remember to add specific CSS for .status-badge, .client, .staff, .admin classes 
// to assets/css/style.css for color coding the roles.
require_once __DIR__ . '/../includes/footer.php'; 
?>