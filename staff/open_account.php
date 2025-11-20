<?php
/**
 * open_account.php (Staff) - Dedicated page for staff to open a new bank account for an existing client.
 */

$required_role = 'Staff';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$client_id = (int)($_GET['client_id'] ?? 0);
$client_info = null;
$account_types = [];
$message = '';
$message_type = '';
$client_role_id = null;

// Check for valid client ID
if ($client_id <= 0) {
    $message = "Invalid Client ID provided. Please select a client first.";
    $message_type = 'error';
    goto render_page;
}

// 2. Pre-fetch Client Info and Account Types
try {
    // Get Client Role ID
    $stmt_role = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client'");
    $stmt_role->execute();
    $client_role_id = $stmt_role->get_result()->fetch_assoc()['role_id'] ?? 0;
    $stmt_role->close();

    // A. Fetch Client Details (must be a Client role)
    $stmt_client = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ? AND role_id = ?");
    $stmt_client->bind_param("ii", $client_id, $client_role_id);
    $stmt_client->execute();
    $result_client = $stmt_client->get_result();

    if ($result_client->num_rows === 1) {
        $client_info = $result_client->fetch_assoc();
    } else {
        $message = "Client not found or ID is incorrect.";
        $message_type = 'error';
        $stmt_client->close();
        goto render_page;
    }
    $stmt_client->close();

    // B. Fetch Available Account Types
    $stmt_types = $conn->prepare("SELECT type_id, type_name FROM account_types WHERE type_name != 'Loan'"); // Exclude Loan type here
    $stmt_types->execute();
    $result_types = $stmt_types->get_result();
    while ($row = $result_types->fetch_assoc()) {
        $account_types[] = $row;
    }
    $stmt_types->close();

} catch (Exception $e) {
    $message = "Database error fetching client or account type data.";
    $message_type = 'error';
    error_log("Staff Open Account Fetch Error: " . $e->getMessage());
    goto render_page;
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
            // A. Generate a Unique Account Number (from functions.php)
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
            
            // C. Log the Initial Deposit (Teller Service)
            if ($initial_deposit > 0) {
                 $description = "Initial deposit upon account opening (Staff action)";
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
            
            // Redirect to client details page to see the new account
            header("Location: client_details.php?id=$client_id&msg=success_open");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Failed to open new account: " . $e->getMessage();
            $message_type = 'error';
            error_log("Staff Open Account Error: " . $e->getMessage());
        }
        $stmt_insert->close();
    }
}

render_page:
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <?php if ($client_info): ?>
        <h1>Open New Account for Client</h1>
        <p>Client: **<?php echo htmlspecialchars($client_info['first_name'] . ' ' . $client_info['last_name']); ?>** (ID: <?php echo $client_id; ?>)</p>
        <a href="client_details.php?id=<?php echo $client_id; ?>" class="btn-secondary" style="float: right;">&larr; Back to Client Profile</a>
    <?php else: ?>
        <h1>Account Opening</h1>
    <?php endif; ?>
</div>

<div class="user-form-container" style="max-width: 500px; margin: 0 auto;">
    <?php if ($message): ?>
        <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($client_info): ?>
    <div class="widget">
        <h2>Account Details (Teller Service)</h2>
        <form method="POST" action="open_account.php?client_id=<?php echo $client_id; ?>">
            
            <div class="form-group">
                <label for="type_id">Account Type <span class="required">*</span></label>
                <select id="type_id" name="type_id" required>
                    <option value="">-- Select Account Product --</option>
                    <?php foreach ($account_types as $type): ?>
                        <option value="<?php echo $type['type_id']; ?>">
                            <?php echo htmlspecialchars($type['type_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="initial_deposit">Initial Deposit Amount (₹)</label>
                <input type="number" step="0.01" min="0.00" id="initial_deposit" name="initial_deposit" 
                       value="<?php echo htmlspecialchars($_POST['initial_deposit'] ?? '0.00'); ?>" required>
                <p style="font-size: 0.8em; color: var(--secondary-color); margin-top: 5px;">This amount will be deposited into the new account.</p>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Confirm & Open New Account</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>