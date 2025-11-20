<?php
/**
 * manage_loans.php (Staff) - Overview of client loan applications and active loans.
 * * Staff can view details and status but cannot typically perform final approvals.
 */

$required_role = 'Staff';

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


// 2. Handle POST Request (Minimal Staff Actions - e.g., Mark as Reviewed)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? ''; 
    $loan_id = (int)($_POST['loan_id'] ?? 0);
    
    // Staff typically notify the review team, they don't approve/reject
    if ($action === 'notify_review' && $loan_id > 0) {
        // In a real system, this would update a 'staff_reviewed' flag or trigger an internal email.
        // For this code base, we'll log a simulated success message.
        $message = "Loan application **$loan_id** marked for immediate management review.";
        $message_type = 'success';
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
    error_log("Staff Loan Fetch Error: " . $e->getMessage());
} finally {
    $conn->close();
}

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Loan Application Overview</h1>
    <p>View the status of loan applications. Current Filter: **<?php echo htmlspecialchars($status_filter); ?>**</p>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="filter-section">
    <a href="manage_loans.php?status=Pending" class="btn-secondary <?php echo $status_filter === 'Pending' ? 'active-filter' : ''; ?>">Pending Applications</a>
    <a href="manage_loans.php?status=Active" class="btn-secondary <?php echo $status_filter === 'Active' ? 'active-filter' : ''; ?>">Active Loans</a>
    <a href="manage_loans.php?status=Rejected" class="btn-secondary <?php echo $status_filter === 'Rejected' ? 'active-filter' : ''; ?>">Rejected Loans</a>
    <a href="manage_loans.php?status=All" class="btn-secondary <?php echo $status_filter === 'All' ? 'active-filter' : ''; ?>">All Loans</a>
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
                <th>Staff Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($loans as $loan): ?>
            <tr>
                <td><?php echo htmlspecialchars($loan['loan_id']); ?></td>
                <td><a href="client_details.php?id=<?php echo $loan['user_id']; ?>"><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></a></td>
                <td>**<?php echo formatCurrency($loan['amount_requested']); ?>**</td>
                <td><?php echo $loan['term_months'] / 12; ?> Yrs @ <?php echo $loan['interest_rate']; ?>%</td>
                <td><?php echo $loan['monthly_payment'] ? formatCurrency($loan['monthly_payment']) : 'N/A'; ?></td>
                <td>
                    <span class="status-badge status-<?php echo strtolower($loan['status']); ?>"><?php echo htmlspecialchars($loan['status']); ?></span>
                </td>
                <td><?php echo formatDate($loan['application_date'], 'M j, Y'); ?></td>
                <td>
                    <?php if ($loan['status'] === 'Pending'): ?>
                        <form method="POST" action="manage_loans.php?status=Pending" style="display: inline;" onsubmit="return confirm('Confirm marking this loan for review?');">
                            <input type="hidden" name="action" value="notify_review">
                            <input type="hidden" name="loan_id" value="<?php echo $loan['loan_id']; ?>">
                            <button type="submit" class="btn-small btn-secondary">Flag for Review</button>
                        </form>
                    <?php else: ?>
                        <span style="color: var(--secondary-color);">Reviewed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="message-box">No **<?php echo htmlspecialchars($status_filter); ?>** loan applications found.</div>
    <?php endif; ?>
</section>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>