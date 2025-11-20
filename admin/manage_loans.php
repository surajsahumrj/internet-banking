<?php
/**
 * manage_loans.php (Admin) - Overview and management of client loan applications.
 */

$required_role = 'Admin';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$loans = [];
$message = '';
$message_type = '';

// Default filter to show 'Pending' loans
$status_filter = $_GET['status'] ?? 'Pending';


// --- Loan Calculation Function (Helper for Approval) ---
/**
 * Calculates the fixed monthly payment for a loan using the fixed-rate amortization formula.
 * @param float $principal The loan amount.
 * @param float $annual_rate The annual interest rate (e.g., 0.05 for 5%).
 * @param int $terms_months The loan term in months.
 * @return float The calculated fixed monthly payment.
 */
function calculateMonthlyPayment(float $principal, float $annual_rate, int $terms_months): float {
    if ($annual_rate <= 0) {
        // Simple division if interest-free
        return round($principal / $terms_months, 2);
    }
    
    $monthly_rate = $annual_rate / 12;
    // Amortization formula: M = P [ i(1 + i)^n ] / [ (1 + i)^n – 1]
    $monthly_payment = $principal * (
        ($monthly_rate * pow((1 + $monthly_rate), $terms_months)) / 
        (pow((1 + $monthly_rate), $terms_months) - 1)
    );
    
    return round($monthly_payment, 2);
}
// --------------------------------------------------------


// 2. Handle POST Request (Approve/Reject Loan)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? ''; // approve, reject
    $loan_id = (int)($_POST['loan_id'] ?? 0);

    if ($loan_id > 0) {
        
        $conn->begin_transaction();
        try {
            // A. Fetch loan details and lock the row
            $stmt_loan = $conn->prepare("SELECT user_id, amount_requested, term_months, interest_rate, status FROM loans WHERE loan_id = ? FOR UPDATE");
            $stmt_loan->bind_param("i", $loan_id);
            $stmt_loan->execute();
            $result_loan = $stmt_loan->get_result();
            if ($result_loan->num_rows === 0) {
                throw new Exception("Loan ID not found.");
            }
            $loan_data = $result_loan->fetch_assoc();
            $stmt_loan->close();
            
            if ($loan_data['status'] !== 'Pending') {
                throw new Exception("Loan is not in 'Pending' status. Cannot process.");
            }

            if ($action === 'approve') {
                
                // B. Calculate Monthly Payment
                $annual_rate = (float)($loan_data['interest_rate'] / 100);
                $monthly_payment = calculateMonthlyPayment(
                    (float)$loan_data['amount_requested'], 
                    $annual_rate, 
                    $loan_data['term_months']
                );
                
                // C. Generate Loan Account Number (if not already done on application)
                $loan_account_number = generateUniqueAccountNumber($conn); 
                
                // D. Update Loan Status and Financials
                $stmt_update = $conn->prepare("UPDATE loans SET 
                                                status = 'Active', 
                                                approval_date = NOW(), 
                                                loan_account_number = ?, 
                                                monthly_payment = ? 
                                                WHERE loan_id = ?");
                $stmt_update->bind_param("sdi", $loan_account_number, $monthly_payment, $loan_id);
                if (!$stmt_update->execute()) {
                    throw new Exception("Failed to update loan status.");
                }
                $stmt_update->close();
                
                // E. Credit the Loan Amount to the Client's Primary Account (Simplification)
                // In a real system, the client would choose which account to credit, 
                // but here we find their first available account.
                $stmt_client_acc = $conn->prepare("SELECT account_id FROM accounts WHERE user_id = ? AND is_active = TRUE LIMIT 1 FOR UPDATE");
                $stmt_client_acc->bind_param("i", $loan_data['user_id']);
                $stmt_client_acc->execute();
                $result_client_acc = $stmt_client_acc->get_result();
                
                if ($result_client_acc->num_rows > 0) {
                    $client_acc = $result_client_acc->fetch_assoc();
                    $client_account_id = $client_acc['account_id'];
                    $amount = (float)$loan_data['amount_requested'];
                    
                    // Update client account balance
                    $stmt_credit_bal = $conn->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE account_id = ?");
                    $stmt_credit_bal->bind_param("di", $amount, $client_account_id);
                    $stmt_credit_bal->execute();

                    // Log the transaction
                    $description = "Loan disbursement (Loan ID: $loan_id, Account: $loan_account_number)";
                    $stmt_log = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description) VALUES (?, 'Loan Disbursement', ?, ?)");
                    $stmt_log->bind_param("ids", $client_account_id, $amount, $description);
                    $stmt_log->execute();
                    $stmt_log->close();
                    
                    $message = "Loan $loan_id Approved! Funds disbursed to client's account. Monthly Payment: " . formatCurrency($monthly_payment) . ".";
                    $message_type = 'success';
                    
                } else {
                    // This happens if the user has no active account to receive the funds.
                    throw new Exception("Client has no active account to receive the loan funds.");
                }
                $stmt_client_acc->close();

            } elseif ($action === 'reject') {
                // F. Reject Loan
                $stmt_update = $conn->prepare("UPDATE loans SET status = 'Rejected' WHERE loan_id = ?");
                $stmt_update->bind_param("i", $loan_id);
                if (!$stmt_update->execute()) {
                    throw new Exception("Failed to reject loan.");
                }
                $message = "Loan $loan_id rejected successfully.";
                $message_type = 'success';
                $stmt_update->close();
            }
            
            $conn->commit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error processing loan action: " . $e->getMessage();
            $message_type = 'error';
            error_log("Loan Processing Error: " . $e->getMessage());
        }
    }
}


// 3. Fetch Loans based on Filter
try {
    $sql = "SELECT l.*, u.first_name, u.last_name, u.email 
            FROM loans l 
            JOIN users u ON l.user_id = u.user_id 
            WHERE 1=1 ";
    $params = [];
    $types = "";

    // Apply status filter
    if ($status_filter !== 'All') {
        $sql .= " AND l.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }

    $sql .= " ORDER BY l.application_date DESC";

    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    $message = "Database error fetching loan applications.";
    $message_type = 'error';
    error_log("Loan Fetch Error: " . $e->getMessage());
} finally {
    $conn->close();
}

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Loan Application Management</h1>
    <p>Review and process pending client loan applications. Current Filter: <?php echo htmlspecialchars($status_filter); ?></p>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="filter-section">
    <a href="manage_loans.php?status=Pending" class="btn-secondary <?php echo $status_filter === 'Pending' ? 'active' : ''; ?>">Pending Applications</a>
    <a href="manage_loans.php?status=Active" class="btn-secondary <?php echo $status_filter === 'Active' ? 'active' : ''; ?>">Active Loans</a>
    <a href="manage_loans.php?status=Rejected" class="btn-secondary <?php echo $status_filter === 'Rejected' ? 'active' : ''; ?>">Rejected Loans</a>
    <a href="manage_loans.php?status=All" class="btn-secondary <?php echo $status_filter === 'All' ? 'active' : ''; ?>">All Loans</a>
</div>
<hr>

<section class="loan-list-section">
    <?php if (!empty($loans)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Client Name</th>
                <th>Requested Amount</th>
                <th>Terms (Yrs) / Rate</th>
                <th>Monthly Pmt.</th>
                <th>Status</th>
                <th>Applied Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($loans as $loan): ?>
            <tr>
                <td><?php echo htmlspecialchars($loan['loan_id']); ?></td>
                <td><a href="user_details.php?id=<?php echo $loan['user_id']; ?>"><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></a></td>
                <td><?php echo formatCurrency($loan['amount_requested']); ?></td>
                <td><?php echo $loan['term_months'] / 12; ?> Yrs @ <?php echo $loan['interest_rate']; ?>%</td>
                <td><?php echo $loan['monthly_payment'] ? formatCurrency($loan['monthly_payment']) : 'N/A'; ?></td>
                <td>
                    <span class="status-badge status-<?php echo strtolower($loan['status']); ?>"><?php echo htmlspecialchars($loan['status']); ?></span>
                </td>
                <td><?php echo formatDate($loan['application_date'], 'M j, Y'); ?></td>
                <td>
                    <?php if ($loan['status'] === 'Pending'): ?>
                        <form method="POST" action="manage_loans.php?status=Pending" style="display: inline;" onsubmit="return confirm('APPROVE loan ID <?php echo $loan['loan_id']; ?>? This will disburse funds.');">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="loan_id" value="<?php echo $loan['loan_id']; ?>">
                            <button type="submit" class="btn-small btn-primary">Approve</button>
                        </form>
                        <form method="POST" action="manage_loans.php?status=Pending" style="display: inline;" onsubmit="return confirm('REJECT loan ID <?php echo $loan['loan_id']; ?>?');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="loan_id" value="<?php echo $loan['loan_id']; ?>">
                            <button type="submit" class="btn-small btn-logout">Reject</button>
                        </form>
                    <?php else: ?>
                        <span style="color: var(--secondary-color);">Action Complete</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="message-box">No <?php echo htmlspecialchars($status_filter); ?> loan applications found.</div>
    <?php endif; ?>
</section>

<?php 
// NOTE: Ensure status-pending, status-active, status-rejected CSS styles are in assets/css/style.css
require_once __DIR__ . '/../includes/footer.php'; 
?>