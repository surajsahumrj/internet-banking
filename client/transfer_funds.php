<?php
/**
 * transfer_funds.php (Client) - Allows the client to transfer funds between their own accounts or to other accounts.
 */

$required_role = 'Client';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/settings.php'; // For accessing system settings like fees

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$client_id = $_SESSION['user_id'];
$client_accounts = [];
$message = '';
$message_type = '';
$preselect_account_id = (int)($_GET['source_acc'] ?? 0);

// Use defined system settings
$fee_rate = (float)($SETTINGS['TRANSFER_FEE_PERCENTAGE'] ?? 0.00) / 100;


// 2. Fetch Client's Active Accounts
try {
    $stmt_accounts = $conn->prepare("SELECT a.account_id, a.account_number, a.current_balance, at.type_name 
                                    FROM accounts a 
                                    JOIN account_types at ON a.type_id = at.type_id
                                    WHERE a.user_id = ? AND a.is_active = TRUE
                                    ORDER BY a.account_number ASC");
    $stmt_accounts->bind_param("i", $client_id);
    $stmt_accounts->execute();
    $client_accounts = $stmt_accounts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_accounts->close();

    if (empty($client_accounts)) {
        $message = "You have no active accounts available to initiate a transfer.";
        $message_type = 'error';
        goto render_page;
    }

} catch (Exception $e) {
    $message = "Database error fetching your accounts.";
    $message_type = 'error';
    error_log("Client Transfer Accounts Fetch Error: " . $e->getMessage());
    goto render_page;
}


// 3. Handle POST Request (Transfer Execution)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $source_account_id = (int)($_POST['source_account_id'] ?? 0);
    $recipient_account_number = trim($_POST['recipient_account_number'] ?? '');
    $amount = number_format((float)($_POST['amount'] ?? 0), 2, '.', '');
    $description = trim($_POST['description'] ?? "Client initiated transfer");
    
    // Safety checks
    if ($amount <= 0.00) {
        $message = "Amount must be a positive number.";
        $message_type = 'error';
        goto skip_transaction;
    }
    if (empty($recipient_account_number)) {
        $message = "Recipient account number is required.";
        $message_type = 'error';
        goto skip_transaction;
    }
    
    // Find source account info from the list
    $source_acc_info = array_filter($client_accounts, fn($acc) => $acc['account_id'] == $source_account_id);
    $source_acc_info = $source_acc_info ? reset($source_acc_info) : null;

    if (!$source_acc_info) {
        $message = "Invalid source account selected.";
        $message_type = 'error';
        goto skip_transaction;
    }
    
    $current_balance = (float)$source_acc_info['current_balance'];

    // Start transaction for atomic operations
    $conn->begin_transaction();

    try {
        // A. Find Recipient Account and lock the rows
        $stmt_recipient = $conn->prepare("SELECT account_id, user_id, current_balance, account_number 
                                          FROM accounts 
                                          WHERE account_number = ? AND is_active = TRUE FOR UPDATE");
        $stmt_recipient->bind_param("s", $recipient_account_number);
        $stmt_recipient->execute();
        $result_recipient = $stmt_recipient->get_result();

        if ($result_recipient->num_rows === 0) {
            throw new Exception("Recipient account number is invalid or inactive.");
        }
        $recipient_acc_info = $result_recipient->fetch_assoc();
        $recipient_account_id = $recipient_acc_info['account_id'];
        $recipient_current_balance = (float)$recipient_acc_info['current_balance'];
        $stmt_recipient->close();

        // B. Prevent Transfer to Self (same account)
        if ($recipient_account_id == $source_account_id) {
            throw new Exception("Cannot transfer funds to the same source account.");
        }

        // C. Calculate Fee and Total Debit
        $fee_amount = round($amount * $fee_rate, 2);
        $total_debit = $amount + $fee_amount;

        // D. Check Funds (Locking is implicitly handled by the FOR UPDATE clause above)
        if ($current_balance < $total_debit) {
            throw new Exception("Insufficient funds. Transfer requires " . formatCurrency($total_debit) . " (Amount + Fee).");
        }
        
        // E. Perform Balance Updates
        $new_source_balance = $current_balance - $total_debit;
        $new_recipient_balance = $recipient_current_balance + $amount;

        $stmt_debit = $conn->prepare("UPDATE accounts SET current_balance = ? WHERE account_id = ?");
        $stmt_debit->bind_param("di", $new_source_balance, $source_account_id);
        if (!$stmt_debit->execute()) { throw new Exception("Debit update failed."); }
        
        $stmt_credit = $conn->prepare("UPDATE accounts SET current_balance = ? WHERE account_id = ?");
        $stmt_credit->bind_param("di", $new_recipient_balance, $recipient_account_id);
        if (!$stmt_credit->execute()) { throw new Exception("Credit update failed."); }

        // F. Log Transactions
        
        // Log 1: Debit from Source Account
        $description_debit = $description . " (To: " . $recipient_account_number . ")";
        $stmt_log_debit = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description, recipient_account_number) VALUES (?, 'Transfer-Debit', ?, ?, ?)");
        $stmt_log_debit->bind_param("idss", $source_account_id, $total_debit, $description_debit, $recipient_account_number);
        if (!$stmt_log_debit->execute()) { throw new Exception("Debit log failed."); }

        // Log 2: Fee (Internal)
        if ($fee_amount > 0) {
            $description_fee = "Transfer Fee (" . $fee_rate * 100 . "%) for transfer to " . $recipient_account_number;
            $stmt_log_fee = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description, recipient_account_number) VALUES (?, 'Fee', ?, ?, 'BANK_FEE')");
            $stmt_log_fee->bind_param("ids", $source_account_id, $fee_amount, $description_fee);
            if (!$stmt_log_fee->execute()) { throw new Exception("Fee log failed."); }
            $stmt_log_fee->close();
        }

        // Log 3: Credit to Recipient Account
        $description_credit = "Transfer received from client account " . $source_acc_info['account_number'];
        $stmt_log_credit = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description, recipient_account_number) VALUES (?, 'Transfer-Credit', ?, ?, ?)");
        $stmt_log_credit->bind_param("idsi", $recipient_account_id, $amount, $description_credit, $source_acc_info['account_number']);
        if (!$stmt_log_credit->execute()) { throw new Exception("Credit log failed."); }
        
        // G. Commit Transaction
        $conn->commit();
        
        // Success message and redirect
        header("Location: dashboard.php?msg=transfer_success");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Transfer failed: " . $e->getMessage();
        $message_type = 'error';
        error_log("Client Transfer Error: " . $e->getMessage());
    }
    
    skip_transaction:
}

render_page:
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Transfer Funds</h1>
    <p>Move money between your accounts or to other SecureBank clients.</p>
</div>

<div class="user-form-container" style="max-width: 600px; margin: 0 auto;">

    <?php if ($message): ?>
        <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="widget">
        <h2>Initiate Transfer</h2>
        <form method="POST" action="transfer_funds.php">
            
            <div class="form-group">
                <label for="source_account_id">From Account <span class="required">*</span></label>
                <select id="source_account_id" name="source_account_id" required>
                    <option value="">-- Select Source Account --</option>
                    <?php foreach ($client_accounts as $acc): ?>
                        <option value="<?php echo $acc['account_id']; ?>" 
                                data-balance="<?php echo htmlspecialchars($acc['current_balance']); ?>"
                                <?php echo ($acc['account_id'] == $preselect_account_id || $acc['account_id'] == ($_POST['source_account_id'] ?? 0)) ? 'selected' : ''; ?>>
                            #<?php echo htmlspecialchars($acc['account_number']); ?> (<?php echo htmlspecialchars($acc['type_name']); ?>) - Balance: <?php echo formatCurrency($acc['current_balance']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="recipient_account_number">To Account Number <span class="required">*</span></label>
                <input type="text" id="recipient_account_number" name="recipient_account_number" required 
                       value="<?php echo htmlspecialchars($_POST['recipient_account_number'] ?? ''); ?>">
                <p style="font-size: 0.8em; color: var(--secondary-color); margin-top: 5px;">Must be a valid 10-digit SecureBank account number.</p>
            </div>

            <div class="form-group">
                <label for="amount">Amount (₹) <span class="required">*</span></label>
                <input type="number" step="0.01" min="0.01" id="amount" name="amount" required 
                       value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" oninput="calculateFee()">
            </div>
            
            <div style="padding: 10px; border: 1px dashed var(--border-light); margin-bottom: 20px;">
                <p>Transfer Fee: <span id="fee_display" style="font-weight: bold; color: var(--danger-color);"><?php echo formatCurrency(0.00); ?></span> (<?php echo $fee_rate * 100; ?>%)</p>
                <p>Total Debit (Amount + Fee): <span id="total_debit_display" style="font-weight: bold;"></span></p>
                <input type="hidden" id="fee_rate_hidden" value="<?php echo $fee_rate; ?>">
            </div>

            <div class="form-group">
                <label for="description">Description (Optional)</label>
                <input type="text" id="description" name="description" 
                       value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>">
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">Confirm & Transfer</button>
        </form>
    </div>
</div>

<script>
    function formatCurrencyJS(value) {
        return '₹' + parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function calculateFee() {
        const amountInput = document.getElementById('amount');
        const feeRateHidden = document.getElementById('fee_rate_hidden');
        const feeDisplay = document.getElementById('fee_display');
        const totalDebitDisplay = document.getElementById('total_debit_display');

        let amount = parseFloat(amountInput.value) || 0;
        let feeRate = parseFloat(feeRateHidden.value) || 0;

        if (amount <= 0) {
            feeDisplay.textContent = formatCurrencyJS(0);
            totalDebitDisplay.textContent = formatCurrencyJS(0);
            return;
        }

        let fee = Math.round(amount * feeRate * 100) / 100; // Round to 2 decimal places
        let totalDebit = amount + fee;

        feeDisplay.textContent = formatCurrencyJS(fee);
        totalDebitDisplay.textContent = formatCurrencyJS(totalDebit);
    }
    
    // Calculate fee on load
    document.addEventListener('DOMContentLoaded', calculateFee);
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>