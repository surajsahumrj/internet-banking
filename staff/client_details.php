<?php
/**
 * client_details.php (Staff) - View and edit a specific client's profile and list their bank accounts.
 * * Similar to admin/user_details.php but restricted to only 'Client' user data.
 */

$required_role = 'Staff';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$client_id = (int)($_GET['id'] ?? 0);
$client_info = null;
$client_accounts = [];
$message = '';
$message_type = '';
$client_role_id = null;

// Check for valid client ID
if ($client_id <= 0) {
    $message = "Invalid Client ID provided.";
    $message_type = 'error';
    goto render_page;
}

// 2. Pre-fetch Client Role ID and Check User Role
try {
    $stmt_role = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client'");
    $stmt_role->execute();
    $result_role = $stmt_role->get_result();
    if ($result_role->num_rows > 0) {
        $client_role_id = $result_role->fetch_assoc()['role_id'];
    }
    $stmt_role->close();

    // Fetch specific user details, ensuring they are a Client
    $stmt_user = $conn->prepare("SELECT user_id, first_name, last_name, email, phone, is_active, created_at
                                  FROM users 
                                  WHERE user_id = ? AND role_id = ?");
    $stmt_user->bind_param("ii", $client_id, $client_role_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user->num_rows === 1) {
        $client_info = $result_user->fetch_assoc();
    } else {
        $message = "Client profile not found or ID is incorrect.";
        $message_type = 'error';
        $stmt_user->close();
        goto render_page;
    }
    $stmt_user->close();

} catch (Exception $e) {
    $message = "Database error fetching client details.";
    $message_type = 'error';
    error_log("Staff Client Details DB Error: " . $e->getMessage());
    goto render_page;
}

// 3. Handle POST Requests (Update Client Details)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $client_info) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_details') {
        $first_name = trim($_POST['first_name'] ?? $client_info['first_name']);
        $last_name = trim($_POST['last_name'] ?? $client_info['last_name']);
        $email = trim($_POST['email'] ?? $client_info['email']);
        $phone = trim($_POST['phone'] ?? $client_info['phone']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validation and Unique Email Check (similar to Admin version)
        if (empty($first_name) || empty($last_name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please fill all required fields with valid data.";
            $message_type = 'error';
        } else {
            // Check if new email is unique (if it changed)
            if ($email !== $client_info['email']) {
                 $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                 $stmt_check->bind_param("si", $email, $client_id);
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
            $stmt_update->bind_param("ssssii", $first_name, $last_name, $email, $phone, $is_active, $client_id);

            if ($stmt_update->execute()) {
                $message = "Client details updated successfully!";
                $message_type = 'success';
                // Refresh $client_info data
                $client_info['first_name'] = $first_name; $client_info['last_name'] = $last_name; 
                $client_info['email'] = $email; $client_info['phone'] = $phone; $client_info['is_active'] = $is_active;
            } else {
                $message = "Failed to update client details: " . $conn->error;
                $message_type = 'error';
            }
            $stmt_update->close();
        }
    }
    
    skip_update: // Label for skipping update logic on error
}

// 4. Fetch Client's Bank Accounts
if ($client_info) {
    try {
        $stmt_accounts = $conn->prepare("SELECT a.account_id, a.account_number, a.current_balance, a.is_active, a.opened_date, at.type_name 
                                        FROM accounts a 
                                        JOIN account_types at ON a.type_id = at.type_id
                                        WHERE a.user_id = ? 
                                        ORDER BY a.account_number");
        $stmt_accounts->bind_param("i", $client_id);
        $stmt_accounts->execute();
        $client_accounts = $stmt_accounts->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_accounts->close();
    } catch (Exception $e) {
        $message = "Error fetching client's existing accounts.";
        $message_type = 'error';
        error_log("Client Accounts Fetch Error: " . $e->getMessage());
    }
}

render_page:
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <?php if ($client_info): ?>
        <h1>Client Profile: <?php echo htmlspecialchars($client_info['first_name'] . ' ' . $client_info['last_name']); ?></h1>
        <p>Client ID: **<?php echo $client_id; ?>** | Joined: <?php echo formatDate($client_info['created_at'], 'M j, Y'); ?></p>
        <a href="manage_clients.php" class="btn-secondary" style="float: right;">&larr; Back to Client Search</a>
    <?php else: ?>
        <h1>Client Management</h1>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($client_info): ?>
<div class="dashboard-widgets" style="grid-template-columns: 1fr;">
    
    <div class="widget">
        <h2>Client Profile & Status</h2>
        <form method="POST" action="client_details.php?id=<?php echo $client_id; ?>">
            <input type="hidden" name="action" value="update_details">

            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required 
                       value="<?php echo htmlspecialchars($client_info['first_name']); ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required 
                       value="<?php echo htmlspecialchars($client_info['last_name']); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email Address (Login ID)</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($client_info['email']); ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?php echo htmlspecialchars($client_info['phone']); ?>">
            </div>
            <div class="form-group checkbox-group">
                <input type="checkbox" id="is_active" name="is_active" value="1" 
                       <?php echo $client_info['is_active'] ? 'checked' : ''; ?>>
                <label for="is_active" style="display: inline;">Client Account Active / Enabled</label>
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Update Client Details</button>
        </form>
    </div>

    <div class="widget" style="margin-top: 20px;">
        <h2>Bank Accounts Overview
            <a href="open_account.php?client_id=<?php echo $client_id; ?>" class="btn-small btn-primary" style="float: right;">+ Open New Account</a>
        </h2>
        
        <?php if (!empty($client_accounts)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Account #</th>
                        <th>Type</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $total_balance = 0; ?>
                    <?php foreach ($client_accounts as $acc): ?>
                    <tr class="<?php echo $acc['is_active'] ? '' : 'inactive-row'; ?>">
                        <td>**<?php echo htmlspecialchars($acc['account_number']); ?>**</td>
                        <td><?php echo htmlspecialchars($acc['type_name']); ?></td>
                        <td>**<?php echo formatCurrency($acc['current_balance']); ?>**</td>
                        <td>
                            <span class="status-badge <?php echo $acc['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $acc['is_active'] ? 'Active' : 'Closed'; ?>
                            </span>
                        </td>
                        <td>
                             <a href="process_transaction.php?acc_id=<?php echo $acc['account_id']; ?>" class="btn-small btn-view">Teller Txn</a>
                             <a href="view_transactions.php?acc_id=<?php echo $acc['account_id']; ?>" class="btn-small btn-secondary">History</a>
                        </td>
                    </tr>
                    <?php if ($acc['is_active']) $total_balance += (float)$acc['current_balance']; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align: right; font-weight: bold;">TOTAL ACTIVE BALANCE:</td>
                        <td style="font-weight: bold;"><?php echo formatCurrency($total_balance); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <div class="message-box">This client currently has no bank accounts open.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>