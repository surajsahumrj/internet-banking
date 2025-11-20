<?php
/**
 * client_accounts.php (Admin) - Lists and manages all bank accounts belonging to a specific client user.
 * * Functionality includes opening new accounts for the client and viewing account balances.
 */

$required_role = 'Admin';

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
$account_types = [];
$message = '';
$message_type = '';

// Check for valid client ID; redirect to manage clients if missing
if ($client_id <= 0) {
    header('Location: manage_clients.php');
    exit;
}

// 2. Fetch Client Info and Account Types
try {
    // A. Fetch Client Details (must be a Client role)
    $stmt_client = $conn->prepare("SELECT u.first_name, u.last_name, r.role_name 
                                   FROM users u 
                                   JOIN user_roles r ON u.role_id = r.role_id 
                                   WHERE u.user_id = ? AND r.role_name = 'Client'");
    $stmt_client->bind_param("i", $client_id);
    $stmt_client->execute();
    $result_client = $stmt_client->get_result();

    if ($result_client->num_rows === 1) {
        $client_info = $result_client->fetch_assoc();
    } else {
        $message = "Client not found or user ID does not belong to a Client role.";
        $message_type = 'error';
        $stmt_client->close();
        goto render_page;
    }
    $stmt_client->close();

    // B. Fetch Available Account Types
    $stmt_types = $conn->prepare("SELECT type_id, type_name FROM account_types");
    $stmt_types->execute();
    $result_types = $stmt_types->get_result();
    while ($row = $result_types->fetch_assoc()) {
        $account_types[] = $row;
    }
    $stmt_types->close();

} catch (Exception $e) {
    $message = "Database error fetching client or account type data.";
    $message_type = 'error';
    error_log("Client Accounts Fetch Error: " . $e->getMessage());
}


// 3. Handle POST Request (Open New Account)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $client_info) {
    $type_id = (int)($_POST['type_id'] ?? 0);
    $initial_deposit = number_format((float)($_POST['initial_deposit'] ?? 0.00), 2, '.', '');
    
    // Simple validation
    if (!in_array($type_id, array_column($account_types, 'type_id'))) {
        $message = "Invalid account type selected.";
        $message_type = 'error';
    } elseif ($initial_deposit < 0) {
        $message = "Initial deposit cannot be negative.";
        $message_type = 'error';
    } else {
        
        $conn->begin_transaction();
        try {
            // A. Generate a Unique Account Number
            $account_number = generateUniqueAccountNumber($conn);
            
            // B. Insert New Account
            $current_date = date('Y-m-d');
            $stmt_insert = $conn->prepare("INSERT INTO accounts 
                                            (user_id, account_number, type_id, current_balance, opened_date, is_active) 
                                            VALUES (?, ?, ?, ?, ?, 1)");
            
            $stmt_insert->bind_param("isids", 
                                      $client_id, 
                                      $account_number, 
                                      $type_id, 
                                      $initial_deposit, 
                                      $current_date);
            
            if (!$stmt_insert->execute()) {
                throw new Exception("Account insertion failed: " . $conn->error);
            }
            $new_account_id = $conn->insert_id;
            
            // C. Log the Initial Deposit (Optional, but good practice)
            if ($initial_deposit > 0) {
                 $description = "Initial deposit upon account opening (Admin action)";
                 $stmt_log = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description) VALUES (?, 'Deposit', ?, ?)");
                 $stmt_log->bind_param("ids", $new_account_id, $initial_deposit, $description);
                 if (!$stmt_log->execute()) {
                    throw new Exception("Initial deposit logging failed: " . $conn->error);
                 }
                 $stmt_log->close();
            }
            
            $conn->commit();
            $message = "New bank account (#**$account_number**) opened successfully for " . htmlspecialchars($client_info['first_name']) . " with an initial deposit of " . formatCurrency($initial_deposit) . ".";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Failed to open new account: " . $e->getMessage();
            $message_type = 'error';
            error_log("Admin Open Account Error: " . $e->getMessage());
        }
        $stmt_insert->close();
    }
}


// 4. Fetch Client Accounts (Refreshed after POST or initial load)
if ($client_info) {
    try {
        $stmt_accounts = $conn->prepare("SELECT a.*, at.type_name 
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
        error_log("Existing Accounts Fetch Error: " . $e->getMessage());
    }
}

render_page:
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <?php if ($client_info): ?>
        <h1>Accounts for: <?php echo htmlspecialchars($client_info['first_name'] . ' ' . $client_info['last_name']); ?></h1>
        <p>Client ID: <?php echo $client_id; ?> | <a href="user_details.php?id=<?php echo $client_id; ?>">Edit Profile</a></p>
    <?php else: ?>
        <h1>Client Account Management</h1>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($client_info): ?>

<div class="dashboard-widgets" style="grid-template-columns: 2fr 1fr;">

    <div class="widget">
        <h2>Existing Accounts</h2>
        <?php if (!empty($client_accounts)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Account #</th>
                        <th>Type</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Opened</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($client_accounts as $acc): ?>
                    <tr class="<?php echo $acc['is_active'] ? '' : 'inactive-row'; ?>">
                        <td>**<?php echo htmlspecialchars($acc['account_number']); ?>**</td>
                        <td><?php echo htmlspecialchars($acc['type_name']); ?></td>
                        <td>**<?php echo formatCurrency($acc['current_balance']); ?>**</td>
                        <td><?php echo $acc['is_active'] ? 'Active' : 'Closed'; ?></td>
                        <td><?php echo formatDate($acc['opened_date'], 'M j, Y'); ?></td>
                        <td>
                             <a href="transactions_engine.php?acc_id=<?php echo $acc['account_id']; ?>" class="btn-small btn-secondary">View Trans.</a>
                             </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>This client currently has no bank accounts open.</p>
        <?php endif; ?>
    </div>

    <div class="widget">
        <h2>Open New Account</h2>
        <form method="POST" action="client_accounts.php?id=<?php echo $client_id; ?>">
            <div class="form-group">
                <label for="type_id">Account Type</label>
                <select id="type_id" name="type_id" required>
                    <option value="">-- Select Type --</option>
                    <?php foreach ($account_types as $type): ?>
                        <option value="<?php echo $type['type_id']; ?>">
                            <?php echo htmlspecialchars($type['type_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="initial_deposit">Initial Deposit Amount (₹)</label>
                <input type="number" step="0.01" min="0.00" id="initial_deposit" name="initial_deposit" value="0.00" required>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Confirm & Open Account</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>