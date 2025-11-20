<?php
/**
 * transactions_engine.php - Central tool for viewing transaction history for a single account.
 * * This serves as the audit engine for Staff and Admin roles.
 */

// NOTE: This page should ideally be protected by requiring EITHER Admin or Staff role.
// We will use a generalized check here.
$required_roles = ['Admin', 'Staff'];

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_roles);

$conn = connectDB();
$transactions = [];
$account_info = null;
$message = '';
$message_type = '';

// Get the account ID from the URL or form
$selected_account_id = (int)($_POST['account_id'] ?? ($_GET['acc_id'] ?? 0));
$start_date = $_POST['start_date'] ?? date('Y-m-01', strtotime('-3 months')); 
$end_date = $_POST['end_date'] ?? date('Y-m-d'); 

// 2. Fetch all accounts for the dropdown (Staff/Admin view all clients)
$all_client_accounts = [];
try {
    $stmt_role = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client'");
    $stmt_role->execute();
    $client_role_id = $stmt_role->get_result()->fetch_assoc()['role_id'] ?? 0;
    $stmt_role->close();
    
    $stmt_accs = $conn->prepare("SELECT a.account_id, a.account_number, a.current_balance, at.type_name,
                                        u.first_name, u.last_name
                                FROM accounts a
                                JOIN users u ON a.user_id = u.user_id
                                JOIN account_types at ON a.type_id = at.type_id
                                WHERE u.role_id = ?
                                ORDER BY u.last_name, a.account_number");
    $stmt_accs->bind_param("i", $client_role_id);
    $stmt_accs->execute();
    $result_accs = $stmt_accs->get_result();
    while ($row = $result_accs->fetch_assoc()) {
        $all_client_accounts[$row['account_id']] = $row;
    }
    $stmt_accs->close();
} catch (Exception $e) {
    $message = "Database error fetching account list.";
    $message_type = 'error';
    error_log("Engine Account Fetch Error: " . $e->getMessage());
    goto render_page;
}


// 3. Fetch Transactions if a valid account is selected
if ($selected_account_id > 0 && isset($all_client_accounts[$selected_account_id])) {
    $account_info = $all_client_accounts[$selected_account_id];
    
    // Validate dates and format for SQL
    if (!strtotime($start_date) || !strtotime($end_date)) {
        $message = "Invalid date format.";
        $message_type = 'error';
        goto render_page;
    }
    
    $end_datetime = $end_date . ' 23:59:59';
    $start_datetime = $start_date . ' 00:00:00';

    try {
        $sql = "SELECT transaction_id, transaction_type, amount, description, transaction_date, status 
                FROM transactions 
                WHERE account_id = ? 
                AND transaction_date BETWEEN ? AND ? 
                ORDER BY transaction_date DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $selected_account_id, $start_datetime, $end_datetime);
        $stmt->execute();
        $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } catch (Exception $e) {
        $message = "Database error fetching transaction history.";
        $message_type = 'error';
        error_log("Engine History Fetch Error: " . $e->getMessage());
    }
} else if ($selected_account_id > 0) {
     $message = "The selected account is invalid or inactive.";
     $message_type = 'error';
}

render_page:
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Transaction Audit Engine</h1>
    <p>View detailed transaction records for any client account. For official auditing purposes.</p>
</div>

<div class="filter-section" style=" margin: 0 auto 20px;">
    <h2>Audit Criteria</h2>
    <form method="POST" action="transactions_engine.php" class="search-form" style="display: flex; gap: 10px; align-items: flex-end;">
        
        <div class="form-group" style="flex-grow: 2;">
            <label for="account_id">Client Account</label>
            <select id="account_id" name="account_id" required>
                <option value="">-- Select Client Account to Audit --</option>
                <?php foreach ($all_client_accounts as $acc): ?>
                    <option value="<?php echo $acc['account_id']; ?>" 
                            <?php echo $acc['account_id'] == $selected_account_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($acc['first_name'] . ' ' . $acc['last_name']); ?> | #<?php echo htmlspecialchars($acc['account_number']); ?> (<?php echo htmlspecialchars($acc['type_name']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="flex-grow: 1;">
            <label for="start_date">From Date</label>
            <input type="date" id="start_date" name="start_date" required 
                   value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        
        <div class="form-group" style="flex-grow: 1;">
            <label for="end_date">To Date</label>
            <input type="date" id="end_date" name="end_date" required 
                   value="<?php echo htmlspecialchars($end_date); ?>">
        </div>

        <button type="submit" class="btn-primary" style="margin-bottom: 2px;">Run Audit</button>
    </form>
</div>
<hr>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo nl2br(htmlspecialchars($message)); ?>
    </div>
<?php endif; ?>

<?php if ($account_info): ?>
<section class="transaction-history-results">
    <h2>Audit Results for Account #**<?php echo htmlspecialchars($account_info['account_number']); ?>**
        <small style="font-weight: normal; color: var(--secondary-color);"> (Client: <?php echo htmlspecialchars($account_info['first_name'] . ' ' . $account_info['last_name']); ?>)</small>
    </h2>
    <p>Current Balance: **<?php echo formatCurrency($account_info['current_balance']); ?>** | Period: <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?></p>
    
    <?php if (!empty($transactions)): ?>
    <table class="data-table transaction-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Txn ID</th>
                <th>Date/Time</th>
                <th>Type</th>
                <th>Description</th>
                <th>Debit (Out)</th>
                <th>Credit (In)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Define transaction types that result in a debit (money leaving the account)
            $debit_types = ['Withdrawal', 'Transfer-Debit', 'Fee', 'Loan Payment'];

            foreach ($transactions as $txn): 
                $is_debit = in_array($txn['transaction_type'], $debit_types);
                $amount = (float)$txn['amount'];
                
                $debit_display = $is_debit ? formatCurrency($amount) : '';
                $credit_display = !$is_debit ? formatCurrency($amount) : '';
            ?>
            <tr class="<?php echo $is_debit ? 'txn-debit' : 'txn-credit'; ?>">
                <td><?php echo htmlspecialchars($txn['transaction_id']); ?></td>
                <td><?php echo formatDate($txn['transaction_date']); ?></td>
                <td><span class="status-badge status-<?php echo strtolower(str_replace(['-', ' '], '', $txn['transaction_type'])); ?>"><?php echo htmlspecialchars($txn['transaction_type']); ?></span></td>
                <td><?php echo htmlspecialchars($txn['description']); ?></td>
                <td style="color: var(--danger-color); font-weight: bold;"><?php echo $debit_display; ?></td>
                <td style="color: var(--success-color); font-weight: bold;"><?php echo $credit_display; ?></td>
                <td><?php echo htmlspecialchars($txn['status']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="message-box">No transactions found for this account in the selected audit period.</div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>