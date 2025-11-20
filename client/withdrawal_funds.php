<?php
/**
 * withdrawal_funds.php (Client) - Allows the client to request a withdrawal from their account.
 * * Debits the client's account and logs the request for staff processing (e.g., E-transfer, Cheque).
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

// 2. Fetch Client's Active Accounts (Source Accounts)
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
        $message = "You have no active accounts available for withdrawal.";
        $message_type = 'error';
        goto render_page;
    }

} catch (Exception $e) {
    $message = "Database error fetching account list.";
    $message_type = 'error';
    error_log("Client Withdrawal Accounts Fetch Error: " . $e->getMessage());
    goto render_page;
}


// 3. Handle POST Request (Withdrawal Execution)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $account_id = (int)($_POST['account_id'] ?? 0);
    $amount = number_format((float)($_POST['amount'] ?? 0), 2, '.', '');
    $withdrawal_method = trim($_POST['withdrawal_method'] ?? '');
    $recipient_info = trim($_POST['recipient_info'] ?? 'N/A');
    
    // Find selected account info for balance check
    $source_acc_info = array_filter($client_accounts, fn($acc) => $acc['account_id'] == $account_id);
    $source_acc_info = $source_acc_info ? reset($source_acc_info) : null;

    // Validation
    if ($amount <= 0.00) {
        $message = "Withdrawal amount must be greater than zero.";
        $message_type = 'error';
        goto skip_transaction;
    }
    if (!$source_acc_info) {
        $message = "Invalid source account selected.";
        $message_type = 'error';
        goto skip_transaction;
    }
    
    $current_balance = (float)$source_acc_info['current_balance'];

    // Check for sufficient funds
    if ($current_balance < $amount) {
        $message = "Insufficient funds. Current balance: " . formatCurrency($current_balance) . ".";
        $message_type = 'error';
        goto skip_transaction;
    }

    $conn->begin_transaction();
    
    try {
        // A. Update Account Balance (Debit the account)
        $stmt_update = $conn->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE account_id = ?");
        $stmt_update->bind_param("di", $amount, $account_id);
        
        if (!$stmt_update->execute()) {
             throw new Exception("Account balance update failed.");
        }
        
        // B. Log Transaction
        $description = "Withdrawal request via {$withdrawal_method}. Recipient: " . htmlspecialchars($recipient_info);
        
        // Use 'Withdrawal' type, but perhaps 'Withdrawal Request' in a complex system
        $stmt_log = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description, status) VALUES (?, 'Withdrawal', ?, ?, 'Pending Dispatch')"); 
        $stmt_log->bind_param("ids", $account_id, $amount, $description);

        if (!$stmt_log->execute()) {
             throw new Exception("Transaction logging failed.");
        }
        
        $conn->commit();
        $message = formatCurrency($amount) . " withdrawal request submitted successfully! Funds have been reserved/debited from your account.";
        $message_type = 'success';
        
        // Redirect to prevent form resubmission and show success
        header("Location: withdrawal_funds.php?msg=success");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Withdrawal failed due to a system error: " . $e->getMessage();
        $message_type = 'error';
        error_log("Client Withdrawal Error: " . $e->getMessage());
    }

    skip_transaction:
}

// Check for success message after redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'success') {
    $message = "Withdrawal request submitted! The amount has been debited. Please allow 1-2 business days for processing/delivery.";
    $message_type = 'success';
}

render_page:
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Request Withdrawal</h1>
    <p>Initiate a withdrawal from your account via e-transfer or other method.</p>
</div>

<div class="user-form-container" style="max-width: 550px; margin: 0 auto;">

    <?php if ($message): ?>
        <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="widget">
        <h2>Withdrawal Details</h2>
        <form method="POST" action="withdrawal_funds.php">
            
            <div class="form-group">
                <label for="account_id">From Account <span class="required">*</span></label>
                <select id="account_id" name="account_id" required>
                    <option value="">-- Select Account to Debit --</option>
                    <?php foreach ($client_accounts as $acc): ?>
                        <option value="<?php echo $acc['account_id']; ?>"
                                <?php echo ($acc['account_id'] == ($_POST['account_id'] ?? 0)) ? 'selected' : ''; ?>>
                            #<?php echo htmlspecialchars($acc['account_number']); ?> (<?php echo htmlspecialchars($acc['type_name']); ?>) - Balance: <?php echo formatCurrency($acc['current_balance']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="amount">Withdrawal Amount (₹) <span class="required">*</span></label>
                <input type="number" step="0.01" min="1.00" id="amount" name="amount" required 
                       value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="withdrawal_method">Method / Purpose <span class="required">*</span></label>
                <select id="withdrawal_method" name="withdrawal_method" required onchange="toggleRecipientField(this.value)">
                    <option value="">-- Select Method --</option>
                    <option value="E-Transfer" <?php echo ($_POST['withdrawal_method'] ?? '') == 'E-Transfer' ? 'selected' : ''; ?>>E-Transfer/Interbank</option>
                    <option value="Cheque" <?php echo ($_POST['withdrawal_method'] ?? '') == 'Cheque' ? 'selected' : ''; ?>>Mail Cheque</option>
                    <option value="ATM" <?php echo ($_POST['withdrawal_method'] ?? '') == 'ATM' ? 'selected' : ''; ?>>ATM (Local Pickup)</option>
                    <option value="Other" <?php echo ($_POST['withdrawal_method'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group" id="recipient_info_group" style="display: <?php echo in_array(($_POST['withdrawal_method'] ?? ''), ['E-Transfer', 'Cheque']) ? 'block' : 'none'; ?>;">
                <label for="recipient_info">Recipient Email/Address <span class="required">*</span></label>
                <input type="text" id="recipient_info" name="recipient_info" 
                       value="<?php echo htmlspecialchars($_POST['recipient_info'] ?? ''); ?>" placeholder="Email for E-Transfer or Mailing Address">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%;">Submit Withdrawal Request</button>
        </form>
    </div>
</div>

<script>
    function toggleRecipientField(method) {
        const group = document.getElementById('recipient_info_group');
        const input = document.getElementById('recipient_info');
        
        if (method === 'E-Transfer' || method === 'Cheque' || method === 'Other') {
            group.style.display = 'block';
            input.setAttribute('required', 'required');
        } else {
            group.style.display = 'none';
            input.removeAttribute('required');
        }
    }
    
    // Set initial state on load
    document.addEventListener('DOMContentLoaded', function() {
        const initialMethod = document.getElementById('withdrawal_method').value;
        toggleRecipientField(initialMethod);
    });
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>