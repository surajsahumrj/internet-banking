<?php
/**
 * process_transaction.php (Staff) - Page for teller deposits, withdrawals and transfers.
 * Adapted from admin/process_transaction.php but restricted to Staff role.
 */

$required_role = 'Staff';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/settings.php'; // For optional fees

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$message = '';
$message_type = '';
$accounts = [];

// 2. Fetch All Active Account Numbers and User Names for Dropdown/Search
try {
    $stmt_accs = $conn->prepare("SELECT a.account_id, a.account_number, a.current_balance, 
                                        u.first_name, u.last_name, r.role_name
                                FROM accounts a
                                JOIN users u ON a.user_id = u.user_id
                                JOIN user_roles r ON u.role_id = r.role_id
                                WHERE a.is_active = TRUE 
                                ORDER BY u.last_name, a.account_number");
    $stmt_accs->execute();
    $result_accs = $stmt_accs->get_result();
    while ($row = $result_accs->fetch_assoc()) {
        $accounts[] = $row;
    }
    $stmt_accs->close();
} catch (Exception $e) {
    $message = "Database error fetching account list.";
    $message_type = 'error';
    error_log("Staff Transaction Account Fetch Error: " . $e->getMessage());
}


// 3. Handle POST Request (Transaction Execution)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($message)) {
    $action = $_POST['action'] ?? ''; // deposit, withdraw, transfer
    $source_account_id = (int)($_POST['source_account_id'] ?? 0);
    $amount = number_format((float)($_POST['amount'] ?? 0), 2, '.', '');
    $description = trim($_POST['description'] ?? "Teller Service Transaction");
    
    // Safety check for amount
    if ($amount <= 0.00) {
        $message = "Amount must be a positive number.";
        $message_type = 'error';
        goto skip_transaction;
    }
    
    // Start transaction for atomic operations
    $conn->begin_transaction();
    $source_acc_info = null;

    // Fetch source account current state and lock the row for update
    if ($source_account_id > 0) {
        $stmt_source = $conn->prepare("SELECT account_number, current_balance FROM accounts WHERE account_id = ? FOR UPDATE");
        $stmt_source->bind_param("i", $source_account_id);
        $stmt_source->execute();
        $result_source = $stmt_source->get_result();
        
        if ($result_source->num_rows > 0) {
            $source_acc_info = $result_source->fetch_assoc();
            $source_account_number = $source_acc_info['account_number'];
            $current_balance = (float)$source_acc_info['current_balance'];
        } else {
            $message = "Source account not found or is inactive.";
            $message_type = 'error';
            goto rollback_and_skip;
        }
        $stmt_source->close();
    }


    // --- ACTION A: Deposit ---
    if ($action === 'deposit') {
        if ($source_account_id <= 0) {
             $message = "Please select an account for the deposit.";
             $message_type = 'error';
             goto rollback_and_skip;
        }
        
        $new_balance = $current_balance + $amount;

        // Update Account Balance
        $stmt_update = $conn->prepare("UPDATE accounts SET current_balance = ? WHERE account_id = ?");
        $stmt_update->bind_param("di", $new_balance, $source_account_id);
        
        // Log Transaction
        $stmt_log = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description) VALUES (?, 'Deposit', ?, ?)");
        $stmt_log->bind_param("ids", $source_account_id, $amount, $description);

        if ($stmt_update->execute() && $stmt_log->execute()) {
            $conn->commit();
            $message = formatCurrency($amount) . " successfully deposited into account $source_account_number. New balance: " . formatCurrency($new_balance) . ".";
            $message_type = 'success';
        } else {
            $conn->rollback();
            $message = "Deposit failed due to a system error: " . $conn->error;
            $message_type = 'error';
        }
        $stmt_update->close();
        $stmt_log->close();
    } 
    
    // --- ACTION B: Withdrawal ---
    elseif ($action === 'withdraw') {
        if ($source_account_id <= 0) {
             $message = "Please select an account for the withdrawal.";
             $message_type = 'error';
             goto rollback_and_skip;
        }
        
        // Check for sufficient funds
        if ($current_balance < $amount) {
            $message = "Insufficient funds in account $source_account_number (Current: " . formatCurrency($current_balance) . ").";
            $message_type = 'error';
            goto rollback_and_skip;
        }
        
        $new_balance = $current_balance - $amount;

        // Update Account Balance
        $stmt_update = $conn->prepare("UPDATE accounts SET current_balance = ? WHERE account_id = ?");
        $stmt_update->bind_param("di", $new_balance, $source_account_id);
        
        // Log Transaction (Note: Withdrawal amount is stored as positive, type indicates direction)
        $stmt_log = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description) VALUES (?, 'Withdrawal', ?, ?)");
        $stmt_log->bind_param("ids", $source_account_id, $amount, $description);

        if ($stmt_update->execute() && $stmt_log->execute()) {
            $conn->commit();
            $message = formatCurrency($amount) . " successfully withdrawn from account $source_account_number. New balance: " . formatCurrency($new_balance) . ".";
            $message_type = 'success';
        } else {
            $conn->rollback();
            $message = "Withdrawal failed due to a system error: " . $conn->error;
            $message_type = 'error';
        }
        $stmt_update->close();
        $stmt_log->close();
    }

    // --- ACTION C: Transfer ---
    elseif ($action === 'transfer') {
        $recipient_account_number = trim($_POST['recipient_account_number'] ?? '');

        if ($source_account_id <= 0 || empty($recipient_account_number)) {
            $message = "Please select both a source account and enter a recipient account number.";
            $message_type = 'error';
            goto rollback_and_skip;
        }
        
        // 3a. Find Recipient Account and lock
        $stmt_recipient = $conn->prepare("SELECT account_id, current_balance FROM accounts WHERE account_number = ? AND account_id != ? FOR UPDATE");
        $stmt_recipient->bind_param("si", $recipient_account_number, $source_account_id);
        $stmt_recipient->execute();
        $result_recipient = $stmt_recipient->get_result();

        if ($result_recipient->num_rows === 0) {
            $message = "Recipient account number is invalid or is the same as the source account.";
            $message_type = 'error';
            $stmt_recipient->close();
            goto rollback_and_skip;
        }
        $recipient_acc_info = $result_recipient->fetch_assoc();
        $recipient_account_id = $recipient_acc_info['account_id'];
        $recipient_current_balance = (float)$recipient_acc_info['current_balance'];
        $stmt_recipient->close();
        
        // 3b. Calculate Fee
        $fee_rate = (float)($SETTINGS['TRANSFER_FEE_PERCENTAGE'] ?? 0.00) / 100;
        $fee_amount = round($amount * $fee_rate, 2);
        $total_debit = $amount + $fee_amount;

        // 3c. Check Funds
        if ($current_balance < $total_debit) {
            $message = "Insufficient funds. Transfer requires " . formatCurrency($total_debit) . " (Amount + Fee). Current: " . formatCurrency($current_balance) . ".";
            $message_type = 'error';
            goto rollback_and_skip;
        }
        
        // 3d. Perform Balance Updates
        $new_source_balance = $current_balance - $total_debit;
        $new_recipient_balance = $recipient_current_balance + $amount;

        $stmt_debit = $conn->prepare("UPDATE accounts SET current_balance = ? WHERE account_id = ?");
        $stmt_debit->bind_param("di", $new_source_balance, $source_account_id);
        
        $stmt_credit = $conn->prepare("UPDATE accounts SET current_balance = ? WHERE account_id = ?");
        $stmt_credit->bind_param("di", $new_recipient_balance, $recipient_account_id);

        // 3e. Log Transactions (Debit, Fee, Credit)
        
        // Log 1: Debit from Source Account
        $stmt_log_debit = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description, recipient_account_number) VALUES (?, 'Transfer-Debit', ?, ?, ?)");
        $stmt_log_debit->bind_param("idss", $source_account_id, $total_debit, $description, $recipient_account_number);

        // Log 2: Fee (Internal bank account logging would go here, simplified to a description)
        if ($fee_amount > 0) {
            $description_fee = "Transfer Fee (" . $fee_rate * 100 . "% ) for transfer to " . $recipient_account_number;
            $stmt_log_fee = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description, recipient_account_number) VALUES (?, 'Fee', ?, ?, 'BANK_FEE')");
            $stmt_log_fee->bind_param("ids", $source_account_id, $fee_amount, $description_fee);
        }

        // Log 3: Credit to Recipient Account
        $description_credit = "Transfer received from account " . $source_account_number;
        $stmt_log_credit = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description, recipient_account_number) VALUES (?, 'Transfer-Credit', ?, ?, ?)");
        $stmt_log_credit->bind_param("idsi", $recipient_account_id, $amount, $description_credit, $source_account_number);
        
        
        // 3f. Execute all steps
        if ($stmt_debit->execute() && $stmt_credit->execute() && $stmt_log_debit->execute() && $stmt_log_credit->execute() && (!$fee_amount || $stmt_log_fee->execute())) {
            $conn->commit();
            $message = formatCurrency($amount) . " transferred from $source_account_number to $recipient_account_number. Fee: " . formatCurrency($fee_amount) . ".";
            $message_type = 'success';
        } else {
            $conn->rollback();
            $message = "Transfer failed due to a system error: " . $conn->error;
            $message_type = 'error';
        }

        $stmt_debit->close(); $stmt_credit->close(); $stmt_log_debit->close(); 
        $stmt_log_credit->close(); 
        if ($fee_amount > 0) $stmt_log_fee->close();
    }
    
    // Cleanup jump labels
    rollback_and_skip:
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    skip_transaction:
}

$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Manual Transaction Processor</h1>
    <p>Perform teller deposits, withdrawals, and transfers. All actions are logged.</p>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<section class="transaction-form-section" style="max-width: 800px; margin: 0 auto;">
    
    <div class="widget">
        <h2>Process Transaction</h2>
        <form method="POST" action="process_transaction.php" class="transaction-form">
            
            <div class="form-group">
                <label for="action">Transaction Type</label>
                <select id="action" name="action" required onchange="toggleTransferFields(this.value)">
                    <option value="">-- Select Type --</option>
                    <option value="deposit">Deposit</option>
                    <option value="withdraw">Withdrawal</option>
                    <option value="transfer">Transfer</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="source_account_id">Source / Target Account</label>
                <select id="source_account_id" name="source_account_id" required>
                    <option value="">-- Select Account --</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc['account_id']; ?>">
                            (<?php echo htmlspecialchars($acc['role_name']); ?>) <?php echo htmlspecialchars($acc['first_name'] . ' ' . $acc['last_name']); ?> | #<?php echo htmlspecialchars($acc['account_number']); ?> (<?php echo formatCurrency($acc['current_balance']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="amount">Amount (₹)</label>
                <input type="number" step="0.01" min="0.01" id="amount" name="amount" required 
                       value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
            </div>

            <div id="transfer-fields" style="display: none; border: 1px dashed #ccc; padding: 15px; margin-bottom: 20px;">
                <h3>Transfer Details</h3>
                <div class="form-group">
                    <label for="recipient_account_number">Recipient Account Number</label>
                    <input type="text" id="recipient_account_number" name="recipient_account_number" 
                           placeholder="Enter 10-digit recipient account number">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description / Teller Notes</label>
                <input type="text" id="description" name="description" required 
                       value="<?php echo htmlspecialchars($_POST['description'] ?? 'Teller Action'); ?>">
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">Execute Transaction</button>
        </form>
    </div>
</section>

<script>
    function toggleTransferFields(action) {
        const transferFields = document.getElementById('transfer-fields');
        const recipientInput = document.getElementById('recipient_account_number');
        
        if (action === 'transfer') {
            transferFields.style.display = 'block';
            recipientInput.setAttribute('required', 'required');
        } else {
            transferFields.style.display = 'none';
            recipientInput.removeAttribute('required');
            recipientInput.value = ''; // Clear value when hidden
        }
    }
    // Set initial state on load if form was previously submitted
    document.addEventListener('DOMContentLoaded', function() {
        const selectedAction = document.getElementById('action').value;
        toggleTransferFields(selectedAction);
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>