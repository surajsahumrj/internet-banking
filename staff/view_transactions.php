<?php
/**
 * view_transactions.php (Staff) - Allows staff to view and audit the transaction history for a client account.
 */

$required_role = 'Staff';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$accounts = [];
$transactions = [];
$account_info = null;
$message = '';
$message_type = '';
$auto_print = false; // Flag for PDF print

// Get the pre-selected account ID and date range from the URL parameters (using GET)
$selected_account_id = (int)($_GET['acc_id'] ?? 0); 
$start_date = $_GET['start_date'] ?? date('Y-m-01'); 
$end_date = $_GET['end_date'] ?? date('Y-m-d'); 

// 2. Fetch All Active Client Accounts for the Dropdown
try {
    // Get Client Role ID
    $stmt_role = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client'");
    $stmt_role->execute();
    $client_role_id = $stmt_role->get_result()->fetch_assoc()['role_id'] ?? 0;
    $stmt_role->close();
    
    // Fetch active accounts belonging to clients
    $stmt_accs = $conn->prepare("SELECT a.account_id, a.account_number, a.current_balance, at.type_name,
                                    u.first_name, u.last_name, u.user_id
                                FROM accounts a
                                JOIN users u ON a.user_id = u.user_id
                                JOIN account_types at ON a.type_id = at.type_id
                                WHERE u.role_id = ?
                                ORDER BY u.last_name, a.account_number");
    $stmt_accs->bind_param("i", $client_role_id);
    $stmt_accs->execute();
    $result_accs = $stmt_accs->get_result();
    while ($row = $result_accs->fetch_assoc()) {
        $accounts[$row['account_id']] = $row;
    }
    $stmt_accs->close();
} catch (Exception $e) {
    $message = "Database error fetching account list.";
    $message_type = 'error';
    error_log("Transaction View Account Fetch Error: " . $e->getMessage());
    goto render_page;
}


// 3. Fetch Transactions if a valid account is selected
if ($selected_account_id > 0 && isset($accounts[$selected_account_id])) {
    $account_info = $accounts[$selected_account_id];
    
    // Validate dates
    if (!strtotime($start_date) || !strtotime($end_date)) {
        $message = "Invalid date format.";
        $message_type = 'error';
        goto render_page;
    }
    
    // Adjust end date to include the whole day (up to 23:59:59)
    $end_datetime = $end_date . ' 23:59:59';
    $start_datetime = $start_date . ' 00:00:00';

    try {
        $sql = "SELECT transaction_id, transaction_type, amount, description, transaction_date 
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
        error_log("Transaction History Fetch Error: " . $e->getMessage());
    }
} else if ($selected_account_id > 0 && !isset($accounts[$selected_account_id])) {
      $message = "The selected account is invalid or was closed.";
      $message_type = 'error';
}

// 4. Handle Export Request (must be placed before any output)

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && !empty($transactions) && $account_info) {
    header('Content-Type: text/csv; charset=utf-8');
    $filename = 'txn_history_' . htmlspecialchars($account_info['account_number']) . '_' . date('Ymd') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Txn ID', 'Date/Time', 'Type', 'Debit', 'Credit', 'Description']);
    
    foreach ($transactions as $t) {
        $is_debit = in_array($t['transaction_type'], ['Withdrawal', 'Transfer-Debit', 'Fee']);
        $amount = (float)$t['amount'];
        // Format for CSV without currency symbol
        $debit_csv = $is_debit ? number_format($amount, 2, '.', '') : '';
        $credit_csv = !$is_debit ? number_format($amount, 2, '.', '') : '';
        
        fputcsv($output, [
            $t['transaction_id'],
            $t['transaction_date'],
            $t['transaction_type'],
            $debit_csv,
            $credit_csv,
            $t['description']
        ]);
    }
    
    fclose($output);
    $conn->close();
    exit;
}

// PDF Export (using browser print)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $auto_print = true;
}

render_page:
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Account Transaction History</h1>
    <p>Audit the financial activity of a client account over a time period.</p>
</div>

<div class="filter-section" style="margin: 0 auto 20px;">
    <h2>Select Account & Period</h2>
    
    <form method="GET" action="view_transactions.php" class="search-form" style="display: flex; gap: 10px; align-items: flex-end;">
        
        <div class="form-group" style="flex-grow: 2;">
            <label for="account_id">Client Account</label>
            <select id="account_id" name="acc_id" required> 
                <option value="">-- Select Client Account --</option>
                <?php foreach ($accounts as $acc): ?>
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

        <div style="display: flex; gap: 5px; margin-bottom: 2px;">
            <button type="submit" name="action" value="view" class="btn-primary">View History</button>
            
            <?php if ($selected_account_id > 0): ?>
                <button type="submit" name="export" value="csv" class="btn-secondary">Export CSV</button>
                <button type="submit" name="export" value="pdf" class="btn-secondary">Export PDF</button>
            <?php endif; ?>
        </div>
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
    <h2>History for Account #**<?php echo htmlspecialchars($account_info['account_number']); ?>** <small style="font-weight: normal; color: var(--secondary-color);"> (<?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?>)</small>
    </h2>
    <p>Client: <a href="client_details.php?id=<?php echo $account_info['user_id']; ?>"><?php echo htmlspecialchars($account_info['first_name'] . ' ' . $account_info['last_name']); ?></a> | Current Balance: **<?php echo formatCurrency($account_info['current_balance']); ?>**</p>
    
    <?php if (!empty($transactions)): ?>
    <table class="data-table transaction-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Type</th>
                <th>Description</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Txn ID</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            foreach ($transactions as $txn): 
                $is_debit = in_array($txn['transaction_type'], ['Withdrawal', 'Transfer-Debit', 'Fee']);
                $amount = (float)$txn['amount'];
                $debit_display = $is_debit ? formatCurrency($amount) : '';
                $credit_display = !$is_debit ? formatCurrency($amount) : '';
            ?>
            <tr class="<?php echo $is_debit ? 'txn-debit' : 'txn-credit'; ?>">
                <td><?php echo formatDate($txn['transaction_date']); ?></td>
                <td><?php echo htmlspecialchars($txn['transaction_type']); ?></td>
                <td><?php echo htmlspecialchars($txn['description']); ?></td>
                <td style="color: var(--danger-color); font-weight: bold;"><?php echo $debit_display; ?></td>
                <td style="color: var(--success-color); font-weight: bold;"><?php echo $credit_display; ?></td>
                <td><?php echo htmlspecialchars($txn['transaction_id']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="message-box">No transactions found for this account in the selected date range.</div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php 
// NOTE: You would add custom CSS to assets/css/style.css for txn-debit and txn-credit to style rows.
require_once __DIR__ . '/../includes/footer.php'; 
?>

<?php if (!empty($auto_print)): ?>
<script>
    // Auto-open the print dialog for PDF export (using browser functionality)
    window.addEventListener('load', function(){
        setTimeout(function(){
            try { window.print(); } catch(e) { console.warn('Print failed', e); }
        }, 300); // Small delay allows the page to fully render before printing
    });
</script>
<?php endif; ?>