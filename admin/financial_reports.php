<?php
/**
 * financial_reports.php (Admin) - Comprehensive page for generating financial reports (Deposits, Withdrawals, Transfers).
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
$transactions = [];
$report_title = '';
$total_amount = 0.00;

// Default report parameters
$report_type = $_GET['type'] ?? 'Deposit'; // Default to Deposit report
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to 1st of the month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to today

// 2. Handle Report Generation (Always happens on load with parameters)
if (!empty($report_type) && !empty($start_date) && !empty($end_date)) {
    
    // Ensure date formats are valid
    if (!strtotime($start_date) || !strtotime($end_date)) {
        $message = "Invalid date format.";
        $message_type = 'error';
        goto render_page;
    }
    
    // Adjust end date to include the whole day (up to 23:59:59)
    $end_datetime = $end_date . ' 23:59:59';
    $start_datetime = $start_date . ' 00:00:00';

    // Transaction types to search for based on the report type
    $transaction_types = [];
    $report_title = htmlspecialchars($report_type) . " Report";

    switch ($report_type) {
        case 'Deposit':
            $transaction_types = ['Deposit'];
            break;
        case 'Withdrawal':
            $transaction_types = ['Withdrawal'];
            break;
        case 'Transfer':
            // Include both debit and credit logs for a full transfer report
            $transaction_types = ['Transfer-Debit', 'Transfer-Credit'];
            break;
        default:
            $message = "Invalid report type selected.";
            $message_type = 'error';
            goto render_page;
    }

    // Convert array of types into a comma-separated string for the SQL IN clause
    $type_placeholders = implode(',', array_fill(0, count($transaction_types), '?'));

    // 3. Fetch Transactions
    try {
        $sql = "SELECT t.*, a.account_number, u.first_name, u.last_name 
                FROM transactions t
                JOIN accounts a ON t.account_id = a.account_id
                JOIN users u ON a.user_id = u.user_id
                WHERE t.transaction_type IN ($type_placeholders) 
                AND t.transaction_date BETWEEN ? AND ? 
                ORDER BY t.transaction_date DESC";
        
        // Prepare parameters: first the transaction types, then the dates
        $params = array_merge($transaction_types, [$start_datetime, $end_datetime]);
        $types = str_repeat('s', count($transaction_types)) . 'ss';

        $stmt = $conn->prepare($sql);
        
        // Dynamically bind parameters
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // 4. Calculate Total Amount
        foreach ($transactions as $t) {
            // Note: For Withdrawals and Debits, we usually report the absolute value
            $total_amount += (float)$t['amount'];
        }
        
    } catch (Exception $e) {
        $message = "Database error fetching transactions: " . $e->getMessage();
        $message_type = 'error';
        error_log("Financial Report DB Error: " . $e->getMessage());
    }
}

    // Export: CSV
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        // send CSV headers and stream the transactions
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="report_' . strtolower($report_type) . '_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Txn ID','Date/Time','Type','Account #','Client Name','Amount','Description']);
        foreach ($transactions as $t) {
            fputcsv($output, [
                $t['transaction_id'],
                $t['transaction_date'],
                $t['transaction_type'],
                $t['account_number'],
                $t['first_name'] . ' ' . $t['last_name'],
                $t['amount'],
                $t['description']
            ]);
        }
        fclose($output);
        $conn->close();
        exit;
    }

    // Export: PDF (simple print-friendly output via browser print)
    $auto_print = false;
    if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
        $auto_print = true;
    }

    render_page:
    $conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Financial Reporting</h1>
    <p>Generate detailed reports for Deposits, Withdrawals, and Transfers over a specified date range.</p>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<section class="report-controls">
    <h2>Generate Report</h2>
    <form method="GET" action="financial_reports.php" style="width:100%;">
        <div class="report-grid">
            <div class="form-group">
                <label for="type">Report Type</label>
                <select id="type" name="type" required class="form-control">
                    <option value="Deposit" <?php echo $report_type == 'Deposit' ? 'selected' : ''; ?>>Deposits</option>
                    <option value="Withdrawal" <?php echo $report_type == 'Withdrawal' ? 'selected' : ''; ?>>Withdrawals</option>
                    <option value="Transfer" <?php echo $report_type == 'Transfer' ? 'selected' : ''; ?>>Transfers (Debit/Credit)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" required value="<?php echo htmlspecialchars($start_date); ?>" class="form-control">
            </div>

            <div class="form-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" required value="<?php echo htmlspecialchars($end_date); ?>" class="form-control">
            </div>

            <div class="report-actions">
                <button type="submit" name="action" value="view" class="btn-primary">View Report</button>
                <button type="submit" name="export" value="csv" class="btn-secondary">Export CSV</button>
                <button type="submit" name="export" value="pdf" class="btn-secondary">Export PDF</button>
            </div>
        </div>
    </form>
</section>

<hr>

<?php if (!empty($transactions)): ?>
<section class="report-results">
    <div class="report-summary">
        <div class="report-summary-inner">
            <div class="kpi-card" style="flex:1;">
                <h2 style="margin:0;"><?php echo $report_title; ?> Summary</h2>
                <div class="text-muted" style="margin-top:6px;">(<?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?>)</div>
                <div style="margin-top:8px; color: #666; font-size: 0.95em;"><?php echo count($transactions); ?> transactions recorded.</div>
            </div>

            <div class="kpi-card primary-kpi" style="flex:1; text-align:center;">
                <h3 style="margin-top:0;">Total <?php echo htmlspecialchars($report_type); ?> Volume</h3>
                <p class="kpi-value"><?php echo formatCurrency($total_amount); ?></p>
            </div>
        </div>
    </div>

    <table class="data-table transaction-table">
        <thead>
            <tr>
                <th>Txn ID</th>
                <th>Date/Time</th>
                <th>Type</th>
                <th>Account #</th>
                <th>Client Name</th>
                <th>Amount</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $txn): ?>
            <tr>
                <td><?php echo htmlspecialchars($txn['transaction_id']); ?></td>
                <td><?php echo formatDate($txn['transaction_date']); ?></td>
                <td><?php echo htmlspecialchars($txn['transaction_type']); ?></td>
                <td><?php echo htmlspecialchars($txn['account_number']); ?></td>
                <td><?php echo htmlspecialchars($txn['first_name'] . ' ' . $txn['last_name']); ?></td>
                <td>**<?php echo formatCurrency($txn['amount']); ?>**</td>
                <td><?php echo htmlspecialchars($txn['description']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php elseif (empty($message)): ?>
    <div class="message-box">No **<?php echo htmlspecialchars($report_type); ?>** transactions found in the specified date range.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php if (!empty($auto_print)): ?>
<script>
    // Auto-open the print dialog for PDF export, delay slightly to allow styles to apply
    window.addEventListener('load', function(){
        setTimeout(function(){
            try { window.print(); } catch(e) { console.warn('Print failed', e); }
        }, 300);
    });
</script>
<?php endif; ?>