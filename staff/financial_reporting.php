<?php
/**
 * pages_financial_reporting.php (Staff) - Simplified overview of transaction volumes (Deposits, Withdrawals, Transfers).
 */

$required_role = 'Staff';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$message = '';
$message_type = '';
$report_data = [];
$total_transactions = 0;
$total_volume = 0.00;

// Default report parameters
$report_type = $_GET['type'] ?? 'All'; // 'All', 'Deposit', 'Withdrawal', 'Transfer'
$start_date = $_GET['start_date'] ?? date('Y-m-d'); // Default to Today
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to Today

// 2. Handle Report Generation
if (!empty($start_date) && !empty($end_date)) {
    
    // Adjust dates for full day coverage
    $end_datetime = $end_date . ' 23:59:59';
    $start_datetime = $start_date . ' 00:00:00';

    // Base transaction types to include
    $valid_types = ['Deposit', 'Withdrawal', 'Transfer-Debit', 'Transfer-Credit', 'Fee', 'Loan Disbursement'];
    $transaction_types = [];
    
    // Determine which types to fetch based on the filter
    if ($report_type === 'Deposit') {
        $transaction_types = ['Deposit'];
    } elseif ($report_type === 'Withdrawal') {
        $transaction_types = ['Withdrawal'];
    } elseif ($report_type === 'Transfer') {
        $transaction_types = ['Transfer-Debit', 'Transfer-Credit']; // Focus on transfer movements
    } else {
        $transaction_types = $valid_types; // All types
    }
    
    $type_placeholders = implode(',', array_fill(0, count($transaction_types), '?'));
    $report_label = htmlspecialchars($report_type);

    // 3. Fetch Aggregate Data
    try {
        // Query to fetch the sum and count of transactions within the date range
        $sql = "SELECT COUNT(transaction_id) AS total_txns, SUM(amount) AS total_vol
                FROM transactions
                WHERE transaction_type IN ($type_placeholders) 
                AND transaction_date BETWEEN ? AND ?";
        
        $params = array_merge($transaction_types, [$start_datetime, $end_datetime]);
        $types = str_repeat('s', count($transaction_types)) . 'ss';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $total_transactions = (int)$row['total_txns'];
            $total_volume = (float)$row['total_vol'];
        }
        $stmt->close();
        
        $message = "Report generated successfully for **$report_label** transactions.";
        $message_type = 'success';
        
    } catch (Exception $e) {
        $message = "Database error generating report.";
        $message_type = 'error';
        error_log("Staff Reporting DB Error: " . $e->getMessage());
    }
} else {
    $message = "Please select a valid date range.";
    $message_type = 'error';
}


// 4. Optionally fetch a list of recent transactions (e.g., last 10) for overview
if ($total_transactions > 0) {
    try {
        $sql_recent = "SELECT t.transaction_date, t.transaction_type, t.amount, t.description, a.account_number 
                       FROM transactions t
                       JOIN accounts a ON t.account_id = a.account_id
                       WHERE t.transaction_type IN ($type_placeholders) 
                       AND t.transaction_date BETWEEN ? AND ? 
                       ORDER BY t.transaction_date DESC 
                       LIMIT 10";
                       
        $stmt_recent = $conn->prepare($sql_recent);
        $stmt_recent->bind_param($types, ...$params);
        $stmt_recent->execute();
        $report_data = $stmt_recent->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_recent->close();
        
    } catch (Exception $e) {
         error_log("Staff Reporting Recent Fetch Error: " . $e->getMessage());
    }
}


$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Staff Financial Reporting</h1>
    <p>View aggregated transaction volumes processed by the system within a given date range.</p>
</div>

<section class="report-controls" style="max-width: 900px; margin: 0 auto 20px;">
    <h2>Generate Report</h2>
    <form method="GET" action="pages_financial_reporting.php" class="search-form" style="display: flex; gap: 10px; align-items: flex-end;">
        
        <div class="form-group" style="flex-grow: 1;">
            <label for="type">Transaction Filter</label>
            <select id="type" name="type" required>
                <option value="All" <?php echo $report_type == 'All' ? 'selected' : ''; ?>>All Transactions</option>
                <option value="Deposit" <?php echo $report_type == 'Deposit' ? 'selected' : ''; ?>>Deposits Only</option>
                <option value="Withdrawal" <?php echo $report_type == 'Withdrawal' ? 'selected' : ''; ?>>Withdrawals Only</option>
                <option value="Transfer" <?php echo $report_type == 'Transfer' ? 'selected' : ''; ?>>Transfers Only</option>
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

        <button type="submit" class="btn-primary" style="margin-bottom: 2px;">Generate Report</button>
    </form>
</section>
<hr>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo nl2br(htmlspecialchars($message)); ?>
    </div>
<?php endif; ?>

<?php if ($total_transactions > 0): ?>
<section class="report-results">
    <div class="report-summary" style="max-width:980px; margin:0 auto 18px;">
        <div style="display:flex; gap:20px; align-items:stretch;">
            <div class="kpi-card" style="flex:1;">
                <h3 style="margin:0 0 8px 0;">Total Transactions</h3>
                <p class="kpi-value"><?php echo number_format($total_transactions); ?></p>
                <p style="color:var(--secondary-color); margin-top:8px; font-size:0.95em;">Reporting Period:<br><?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?></p>
            </div>

            <div class="kpi-card primary-kpi" style="flex:1;">
                <h3 style="margin:0 0 8px 0;">Total Financial Volume</h3>
                <p class="kpi-value"><?php echo formatCurrency($total_volume); ?></p>
            </div>
        </div>
    </div>

    <?php if (!empty($report_data)): ?>
    <h3 style="margin-top: 30px;">Last 10 Relevant Transactions</h3>
    <table class="data-table transaction-table" style="margin-top: 10px;">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Type</th>
                <th>Account #</th>
                <th>Amount</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_data as $txn): ?>
            <tr>
                <td><?php echo formatDate($txn['transaction_date']); ?></td>
                <td><?php echo htmlspecialchars($txn['transaction_type']); ?></td>
                <td><?php echo htmlspecialchars($txn['account_number']); ?></td>
                <td>**<?php echo formatCurrency($txn['amount']); ?>**</td>
                <td><?php echo htmlspecialchars($txn['description']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>