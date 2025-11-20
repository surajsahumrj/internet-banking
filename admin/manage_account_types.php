<?php
/**
 * manage_account_types.php (Admin) - Manages the types of bank accounts available (e.g., Savings, Checking, Loans).
 */

$required_role = 'Admin';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$message = '';
$message_type = '';
$account_types = [];

// 2. Handle POST Requests (Add/Update/Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $type_name = trim($_POST['type_name'] ?? '');
    $interest_rate = number_format((float)($_POST['interest_rate'] ?? 0.00), 2, '.', '');
    $type_id = (int)($_POST['type_id'] ?? 0);

    // --- ACTION A: Add New Account Type ---
    if ($action === 'add') {
        if (empty($type_name) || $interest_rate < 0) {
            $message = "Account name is required, and the interest rate must be non-negative.";
            $message_type = 'error';
        } else {
            // Check for duplicate name
            $stmt_check = $conn->prepare("SELECT type_id FROM account_types WHERE type_name = ?");
            $stmt_check->bind_param("s", $type_name);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $message = "An account type with that name already exists.";
                $message_type = 'error';
            } else {
                $stmt_insert = $conn->prepare("INSERT INTO account_types (type_name, interest_rate) VALUES (?, ?)");
                $stmt_insert->bind_param("sd", $type_name, $interest_rate);
                if ($stmt_insert->execute()) {
                    $message = "Account type **" . htmlspecialchars($type_name) . "** added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Failed to add account type: " . $conn->error;
                    $message_type = 'error';
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
        }
    }
    
    // --- ACTION B: Update Existing Account Type ---
    elseif ($action === 'update' && $type_id > 0) {
        if (empty($type_name) || $interest_rate < 0) {
            $message = "Account name and non-negative rate are required for update.";
            $message_type = 'error';
        } else {
            // Check for duplicate name (excluding the current ID)
            $stmt_check = $conn->prepare("SELECT type_id FROM account_types WHERE type_name = ? AND type_id != ?");
            $stmt_check->bind_param("si", $type_name, $type_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $message = "Another account type already uses that name.";
                $message_type = 'error';
            } else {
                $stmt_update = $conn->prepare("UPDATE account_types SET type_name = ?, interest_rate = ? WHERE type_id = ?");
                $stmt_update->bind_param("sdi", $type_name, $interest_rate, $type_id);
                if ($stmt_update->execute()) {
                    $message = "Account type **" . htmlspecialchars($type_name) . "** updated successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Failed to update account type: " . $conn->error;
                    $message_type = 'error';
                }
                $stmt_update->close();
            }
            $stmt_check->close();
        }
    }
    
    // --- ACTION C: Delete Account Type ---
    elseif ($action === 'delete' && $type_id > 0) {
        // SECURITY CHECK: Ensure no active accounts use this type before deleting
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM accounts WHERE type_id = ?");
        $stmt_check->bind_param("i", $type_id);
        $stmt_check->execute();
        $count = $stmt_check->get_result()->fetch_row()[0];
        $stmt_check->close();

        if ($count > 0) {
            $message = "Cannot delete account type: **$count** active bank accounts are currently linked to it.";
            $message_type = 'error';
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM account_types WHERE type_id = ?");
            $stmt_delete->bind_param("i", $type_id);
            if ($stmt_delete->execute()) {
                $message = "Account type deleted successfully.";
                $message_type = 'success';
            } else {
                $message = "Failed to delete account type: " . $conn->error;
                $message_type = 'error';
            }
            $stmt_delete->close();
        }
    }
}


// 3. Fetch All Account Types (always run to populate table)
try {
    $stmt_fetch = $conn->prepare("SELECT type_id, type_name, interest_rate FROM account_types ORDER BY type_id ASC");
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    while ($row = $result_fetch->fetch_assoc()) {
        $account_types[] = $row;
    }
    $stmt_fetch->close();
} catch (Exception $e) {
    $message = "Database error fetching account types.";
    $message_type = 'error';
    error_log("Manage Acc Types DB Error: " . $e->getMessage());
} finally {
    $conn->close();
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Manage Account Products</h1>
    <p>Define and configure the bank's core product offerings, such as interest rates for savings and checking accounts.</p>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="dashboard-widgets" style="grid-template-columns: 1fr 2fr;">
    
    <div class="widget">
        <h2>➕ Add New Type</h2>
        <form method="POST" action="manage_account_types.php">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label for="type_name_new">Account Type Name</label>
                <input type="text" id="type_name_new" name="type_name" required>
            </div>
            
            <div class="form-group">
                <label for="interest_rate_new">Annual Interest Rate (%)</label>
                <input type="number" step="0.01" min="0.00" id="interest_rate_new" name="interest_rate" value="0.00" required>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">Add Account Type</button>
        </form>
    </div>

    <div class="widget">
        <h2>📋 Existing Account Types</h2>
        
        <?php if (!empty($account_types)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type Name</th>
                        <th>Rate (%)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($account_types as $type): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($type['type_id']); ?></td>
                        <td><?php echo htmlspecialchars($type['type_name']); ?></td>
                        <td><?php echo htmlspecialchars($type['interest_rate']); ?>%</td>
                        <td>
                            <button type="button" class="btn-small btn-secondary" 
                                onclick="document.getElementById('edit-form-<?php echo $type['type_id']; ?>').style.display='block'; this.style.display='none';">
                                Edit
                            </button>
                            <form method="POST" action="manage_account_types.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete the <?php echo htmlspecialchars($type['type_name']); ?> type?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type_id" value="<?php echo $type['type_id']; ?>">
                                <button type="submit" class="btn-small btn-logout">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <tr id="edit-form-<?php echo $type['type_id']; ?>" style="display: none; background-color: #f9f9f9;">
                        <td colspan="4">
                            <form method="POST" action="manage_account_types.php" style="padding: 10px 0;">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="type_id" value="<?php echo $type['type_id']; ?>">
                                <input type="text" name="type_name" value="<?php echo htmlspecialchars($type['type_name']); ?>" required style="width: 30%;">
                                <input type="number" step="0.01" min="0.00" name="interest_rate" value="<?php echo htmlspecialchars($type['interest_rate']); ?>" required style="width: 30%;">
                                <button type="submit" class="btn-primary btn-small">Save</button>
                                <button type="button" class="btn-secondary btn-small" 
                                    onclick="document.getElementById('edit-form-<?php echo $type['type_id']; ?>').style.display='none'; document.querySelector('button[onclick*=\"<?php echo $type['type_id']; ?>\"]').style.display='inline-block';">
                                    Cancel
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No account types defined yet. Please use the form on the left to add one.</p>
        <?php endif; ?>
    </div>
</div>

<?php 
// NOTE: Ensure .data-table, .btn-small, .btn-secondary CSS styles are in assets/css/style.css
require_once __DIR__ . '/../includes/footer.php'; 
?>