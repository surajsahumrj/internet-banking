<?php
/**
 * deposit_funds.php (Client) - Simulates a deposit from an external source (e.g., payment gateway or e-transfer).
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
$client_accounts = [];
$message = '';
$message_type = '';

// 2. Fetch Client's Active Accounts
try {
    $stmt_accounts = $conn->prepare("SELECT a.account_id, a.account_number, at.type_name, a.current_balance
                                    FROM accounts a 
                                    JOIN account_types at ON a.type_id = at.type_id
                                    WHERE a.user_id = ? AND a.is_active = TRUE
                                    ORDER BY a.account_number ASC");
    $stmt_accounts->bind_param("i", $client_id);
    $stmt_accounts->execute();
    $client_accounts = $stmt_accounts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_accounts->close();

    if (empty($client_accounts)) {
        $message = "You have no active accounts to receive a deposit.";
        $message_type = 'error';
        goto render_page;
    }

} catch (Exception $e) {
    $message = "Database error fetching account list.";
    $message_type = 'error';
    error_log("Client Deposit Accounts Fetch Error: " . $e->getMessage());
    goto render_page;
}


// 3. Handle POST Request (Simulated Deposit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $account_id = (int)($_POST['account_id'] ?? 0);
    $amount = number_format((float)($_POST['amount'] ?? 0), 2, '.', '');
    $source_name = trim($_POST['source_name'] ?? 'External Payment Source');
    
    // Validation
    if ($amount <= 0.00) {
        $message = "Deposit amount must be greater than zero.";
        $message_type = 'error';
        goto skip_transaction;
    }
    if ($account_id <= 0 || !in_array($account_id, array_column($client_accounts, 'account_id'))) {
        $message = "Invalid or inactive destination account selected.";
        $message_type = 'error';
        goto skip_transaction;
    }

    $conn->begin_transaction();
    
    try {
        // A. Update Account Balance (FOR UPDATE lock is not strictly needed here as it's a credit, 
        // but it's good practice to ensure atomicity.)
        $stmt_update = $conn->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE account_id = ?");
        $stmt_update->bind_param("di", $amount, $account_id);
        
        if (!$stmt_update->execute()) {
             throw new Exception("Account balance update failed.");
        }
        
        // B. Log Transaction
        $description = "Deposit received from: " . htmlspecialchars($source_name);
        $stmt_log = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description) VALUES (?, 'Deposit', ?, ?)");
        $stmt_log->bind_param("ids", $account_id, $amount, $description);

        if (!$stmt_log->execute()) {
             throw new Exception("Transaction logging failed.");
        }
        
        $conn->commit();
        $message = formatCurrency($amount) . " successfully credited to your account. Your deposit is complete!";
        $message_type = 'success';
        
        // Refresh account list to show new balance
        header("Location: deposit_funds.php?msg=success&acc_id=$account_id");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Deposit failed due to a system error: " . $e->getMessage();
        $message_type = 'error';
        error_log("Client Deposit Error: " . $e->getMessage());
    }

    skip_transaction:
}

// Check for success message after redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'success') {
    $message = "Deposit successfully completed! Check your history for details.";
    $message_type = 'success';
}

render_page:
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Deposit Funds</h1>
    <p>Simulate depositing money from an external bank or payment source into your SecureBank account.</p>
</div>

<div class="user-form-container" style="max-width: 550px; margin: 0 auto;">

    <?php if ($message): ?>
        <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="widget">
        <h2>Simulated External Payment</h2>
        <p style="color: var(--secondary-color); font-size: 0.9em; margin-bottom: 20px;">
            In a live system, this would securely authenticate with the source bank.
        </p>
        <form method="POST" action="deposit_funds.php">
            
            <div class="form-group">
                <label for="account_id">Destination Account <span class="required">*</span></label>
                <select id="account_id" name="account_id" required>
                    <option value="">-- Select Account to Credit --</option>
                    <?php foreach ($client_accounts as $acc): ?>
                        <option value="<?php echo $acc['account_id']; ?>"
                                <?php echo ($acc['account_id'] == ($_GET['acc_id'] ?? 0) || $acc['account_id'] == ($_POST['account_id'] ?? 0)) ? 'selected' : ''; ?>>
                            #<?php echo htmlspecialchars($acc['account_number']); ?> (<?php echo htmlspecialchars($acc['type_name']); ?>) - Balance: <?php echo formatCurrency($acc['current_balance']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="source_name">External Source Name (e.g., PayPal, Another Bank)</label>
                <input type="text" id="source_name" name="source_name" required 
                       value="<?php echo htmlspecialchars($_POST['source_name'] ?? 'External Bank'); ?>">
            </div>

            <div class="form-group">
                <label for="amount">Deposit Amount (₹) <span class="required">*</span></label>
                <input type="number" step="0.01" min="1.00" id="amount" name="amount" required 
                       value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">Complete Deposit</button>
        </form>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>