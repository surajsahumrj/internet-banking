<?php
/**
 * dashboard.php (Admin) - The main control panel for administrators.
 * * This page displays system key performance indicators (KPIs) and provides navigation
 * * shortcuts to major administrative tasks.
 */

// Define the required authorization role
$required_role = 'Admin';

// Include core files
// NOTE: We use '../' to step up one directory level from /admin/ to the root
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check (CRUCIAL SECURITY STEP)
// This will redirect non-Admin users or logged-out users away.
checkAuth($required_role);

$conn = connectDB();
$admin_name = 'Admin User'; // Placeholder name
$kpi_data = [
    'total_clients' => 0,
    'total_staff' => 0,
    'total_accounts' => 0,
    'total_balance' => 0.00,
    'pending_loans' => 0,
];

// 2. Fetch Dashboard Key Performance Indicators (KPIs)
try {
    // A. Get Total Clients
    $stmt_clients = $conn->prepare("SELECT COUNT(user_id) FROM users WHERE role_id = (SELECT role_id FROM user_roles WHERE role_name = 'Client')");
    $stmt_clients->execute();
    $kpi_data['total_clients'] = $stmt_clients->get_result()->fetch_row()[0];
    $stmt_clients->close();
    
    // B. Get Total Staff
    $stmt_staff = $conn->prepare("SELECT COUNT(user_id) FROM users WHERE role_id = (SELECT role_id FROM user_roles WHERE role_name = 'Staff')");
    $stmt_staff->execute();
    $kpi_data['total_staff'] = $stmt_staff->get_result()->fetch_row()[0];
    $stmt_staff->close();
    
    // C. Get Total Bank Accounts
    $stmt_accs = $conn->prepare("SELECT COUNT(account_id) FROM accounts");
    $stmt_accs->execute();
    $kpi_data['total_accounts'] = $stmt_accs->get_result()->fetch_row()[0];
    $stmt_accs->close();
    
    // D. Get Total System Balance (Sum of all active accounts)
    $stmt_balance = $conn->prepare("SELECT SUM(current_balance) FROM accounts WHERE is_active = TRUE");
    $stmt_balance->execute();
    $kpi_data['total_balance'] = $stmt_balance->get_result()->fetch_row()[0] ?? 0.00;
    $stmt_balance->close();

    // E. Get Pending Loans (count loans with status 'Pending')
    try {
        $stmt_loans = $conn->prepare("SELECT COUNT(loan_id) FROM loans WHERE status = 'Pending'");
        $stmt_loans->execute();
        $kpi_data['pending_loans'] = (int)$stmt_loans->get_result()->fetch_row()[0];
        $stmt_loans->close();
    } catch (Exception $e) {
        // If the loans table doesn't exist or another error occurs, keep default 0 and log.
        $kpi_data['pending_loans'] = 0;
        error_log('Dashboard pending loans query failed: ' . $e->getMessage());
    }
    
    // F. Get Admin User's Name
    $stmt_name = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt_name->bind_param("i", $_SESSION['user_id']);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    if($result_name->num_rows > 0) {
        $user_info = $result_name->fetch_assoc();
        $admin_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
    }
    $stmt_name->close();

} catch (Exception $e) {
    // Log the error and use default zero values
    error_log("Admin Dashboard DB Error: " . $e->getMessage());
} finally {
    $conn->close();
}

?>

<?php require_once __DIR__ . '/../includes/header.php'; // Includes the starting HTML and Navigation ?>

<div class="page-title">
    <h1>Admin Dashboard</h1>
    <p>Welcome back, <strong><?php echo $admin_name; ?></strong>. Here is your system overview and quick actions.</p>
</div>

<section class="dashboard-section">
    <h2 style="margin-bottom: var(--spacing-lg); display: flex; align-items: center; gap: var(--spacing-md);">
        <span style="font-size: 24px;">📊</span>
        <span>System Overview</span>
    </h2>
    <div class="dashboard-widgets">
        
        <div class="widget kpi-card">
            <h3>Total Clients</h3>
            <p class="kpi-value"><?php echo number_format($kpi_data['total_clients']); ?></p>
            <a href="manage_users.php?role=client" class="widget-link">View All Clients →</a>
        </div>
        
        <div class="widget kpi-card">
            <h3>Total Staff</h3>
            <p class="kpi-value"><?php echo number_format($kpi_data['total_staff']); ?></p>
            <a href="manage_users.php?role=staff" class="widget-link">View All Staff →</a>
        </div>
        
        <div class="widget kpi-card primary-kpi">
            <h3>Total Balance Held</h3>
            <p class="kpi-value"><?php echo formatCurrency($kpi_data['total_balance']); ?></p>
            <a href="financial_reports.php" class="widget-link">View Reports →</a>
        </div>
        
        <div class="widget kpi-card">
            <h3>Pending Loans</h3>
            <p class="kpi-value danger-text"><?php echo number_format($kpi_data['pending_loans']); ?></p>
            <a href="manage_loans.php" class="widget-link">Review Applications →</a>
        </div>
        
    </div>
</section>

<section class="dashboard-section">
    <h2 style="margin-bottom: var(--spacing-lg); display: flex; align-items: center; gap: var(--spacing-md);">
        <span style="font-size: 24px;">⚡</span>
        <span>Quick Actions</span>
    </h2>
    <div class="dashboard-widgets">
        
        <div class="widget quick-action-card">
            <h3 style="margin-bottom: var(--spacing-lg); padding-bottom: var(--spacing-md); border-bottom: 1px solid var(--border-color);">User Management</h3>
            <ul>
                <li><a href="add_user.php">➕ Add New Staff/Client</a></li>
                <li><a href="manage_users.php">👥 Search/Edit Users</a></li>
            </ul>
        </div>
        
        <div class="widget quick-action-card">
            <h3 style="margin-bottom: var(--spacing-lg); padding-bottom: var(--spacing-md); border-bottom: 1px solid var(--border-color);">System Configuration</h3>
            <ul>
                <li><a href="manage_account_types.php">⚙️ Manage Account Types</a></li>
                <li><a href="system_settings.php">🔧 Edit System Settings</a></li>
            </ul>
        </div>

        <div class="widget quick-action-card">
            <h3 style="margin-bottom: var(--spacing-lg); padding-bottom: var(--spacing-md); border-bottom: 1px solid var(--border-color);">Reporting & Audit</h3>
            <ul>
                <li><a href="financial_reports.php">📈 Financial Reports</a></li>
                <li><a href="process_transaction.php">📋 Transaction Audit</a></li>
            </ul>
        </div>
        
    </div>
</section>

<?php 
require_once __DIR__ . '/../includes/footer.php'; // Includes the closing HTML and scripts 
?>