<?php
/**
 * dashboard.php (Staff) - The main operational dashboard for bank staff/tellers.
 * * Focuses on quick access to client management and pending tasks.
 */

// Define the required authorization role
$required_role = 'Staff';

// Include core files
// NOTE: We use '../' to step up one directory level from /staff/ to the root
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check (CRUCIAL SECURITY STEP)
checkAuth($required_role);

$conn = connectDB();
$staff_name = 'Staff Member'; // Placeholder name
$kpi_data = [
    'new_client_signups' => 0, // Clients registered but not yet assigned an account
    'pending_account_openings' => 0, // Accounts requested by clients/staff (pending)
    'recent_deposits_count' => 0,
    'recent_withdrawals_count' => 0,
];

// 2. Fetch Dashboard Key Operational Indicators (KOIs)
try {
    
    // A. Get Staff User's Name
    $stmt_name = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt_name->bind_param("i", $_SESSION['user_id']);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    if($result_name->num_rows > 0) {
        $user_info = $result_name->fetch_assoc();
        $staff_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
    }
    $stmt_name->close();

    // B. Get New Client Signups (Clients without any active bank account)
    // This is a complex query to count clients that exist in `users` but have no entries in `accounts`
    $stmt_new_clients = $conn->prepare("SELECT COUNT(u.user_id) 
                                        FROM users u
                                        JOIN user_roles r ON u.role_id = r.role_id
                                        LEFT JOIN accounts a ON u.user_id = a.user_id
                                        WHERE r.role_name = 'Client' AND a.account_id IS NULL");
    $stmt_new_clients->execute();
    $kpi_data['new_client_signups'] = $stmt_new_clients->get_result()->fetch_row()[0];
    $stmt_new_clients->close();

    // C. Get Recent Transaction Counts (e.g., last 24 hours)
    $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
    
    $stmt_deposits = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE transaction_type = 'Deposit' AND transaction_date >= ?");
    $stmt_deposits->bind_param("s", $yesterday);
    $stmt_deposits->execute();
    $kpi_data['recent_deposits_count'] = $stmt_deposits->get_result()->fetch_row()[0];
    $stmt_deposits->close();
    
    $stmt_withdrawals = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE transaction_type = 'Withdrawal' AND transaction_date >= ?");
    $stmt_withdrawals->bind_param("s", $yesterday);
    $stmt_withdrawals->execute();
    $kpi_data['recent_withdrawals_count'] = $stmt_withdrawals->get_result()->fetch_row()[0];
    $stmt_withdrawals->close();
    
    // D. Pending loan applications (live)
    // Count loan applications with status = 'Pending' and show that as "Pending Applications"
    $stmt_loans = $conn->prepare("SELECT COUNT(*) FROM loans WHERE status = 'Pending'");
    if ($stmt_loans) {
        $stmt_loans->execute();
        $kpi_data['pending_account_openings'] = (int)$stmt_loans->get_result()->fetch_row()[0];
        $stmt_loans->close();
    } else {
        // If the loans table doesn't exist or query fails, fall back to 0
        $kpi_data['pending_account_openings'] = 0;
    }
    

} catch (Exception $e) {
    // Log the error and use default zero values
    error_log("Staff Dashboard DB Error: " . $e->getMessage());
} finally {
    $conn->close();
}

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Staff Dashboard</h1>
    <p>Welcome, <strong><?php echo $staff_name; ?></strong>. Focus on client service and operational efficiency.</p>
</div>

<section class="dashboard-section">
    <h2 style="margin-bottom: var(--spacing-lg); display: flex; align-items: center; gap: var(--spacing-md);">
        <span style="font-size: 24px;">🎯</span>
        <span>Service Priorities</span>
    </h2>
    <div class="dashboard-widgets">
        
        <div class="widget kpi-card">
            <h3>New Client Signups</h3>
            <p class="kpi-value danger-text"><?php echo number_format($kpi_data['new_client_signups']); ?></p>
            <p style="font-size: var(--font-size-xs); color: var(--text-muted); margin-bottom: var(--spacing-md);">Awaiting first account opening</p>
            <a href="pending_accounts.php" class="widget-link">Process Clients →</a>
        </div>
        
        <div class="widget kpi-card">
            <h3>Pending Applications</h3>
            <p class="kpi-value"><?php echo number_format($kpi_data['pending_account_openings']); ?></p>
            <p style="font-size: var(--font-size-xs); color: var(--text-muted); margin-bottom: var(--spacing-md);">Accounts/Services needing review</p>
            <a href="manage_loans.php" class="widget-link">Review Applications →</a>
        </div>
        
        <div class="widget kpi-card">
            <h3>Deposits (Past 24H)</h3>
            <p class="kpi-value success-color"><?php echo number_format($kpi_data['recent_deposits_count']); ?></p>
            <p style="font-size: var(--font-size-xs); color: var(--text-muted); margin-bottom: var(--spacing-md);">Total deposits processed</p>
            <a href="view_transactions.php?type=Deposit" class="widget-link">View Logs →</a>
        </div>
        
        <div class="widget kpi-card">
            <h3>Withdrawals (Past 24H)</h3>
            <p class="kpi-value"><?php echo number_format($kpi_data['recent_withdrawals_count']); ?></p>
            <p style="font-size: var(--font-size-xs); color: var(--text-muted); margin-bottom: var(--spacing-md);">Total withdrawals processed</p>
            <a href="view_transactions.php?type=Withdrawal" class="widget-link">View Logs →</a>
        </div>
        
    </div>
</section>

<section class="dashboard-section">
    <h2 style="margin-bottom: var(--spacing-lg); display: flex; align-items: center; gap: var(--spacing-md);">
        <span style="font-size: 24px;">⚡</span>
        <span>Quick Access</span>
    </h2>
    <div class="dashboard-widgets">
        
        <div class="widget quick-action-card">
            <h3 style="margin-bottom: var(--spacing-lg); padding-bottom: var(--spacing-md); border-bottom: 1px solid var(--border-color);">Client Services</h3>
            <a href="manage_clients.php" class="btn-primary btn-block">👥 Search Clients</a>
        </div>
        
        <div class="widget quick-action-card">
            <h3 style="margin-bottom: var(--spacing-lg); padding-bottom: var(--spacing-md); border-bottom: 1px solid var(--border-color);">Teller Window</h3>
            <a href="process_transaction.php" class="btn-primary btn-block">💳 Process Transaction</a>
        </div>
        
        <div class="widget quick-action-card">
            <h3 style="margin-bottom: var(--spacing-lg); padding-bottom: var(--spacing-md); border-bottom: 1px solid var(--border-color);">New Client Intake</h3>
            <a href="add_client.php" class="btn-primary btn-block">➕ Add Client</a>
        </div>
        
        <div class="widget quick-action-card">
            <h3 style="margin-bottom: var(--spacing-lg); padding-bottom: var(--spacing-md); border-bottom: 1px solid var(--border-color);">Balance Enquiry</h3>
            <a href="balance_enquiry.php" class="btn-primary btn-block">🔍 Check Balance</a>
        </div>
        
    </div>
</section>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>