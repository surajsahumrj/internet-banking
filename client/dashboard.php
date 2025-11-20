<?php
/**
 * dashboard.php (Client) - The main overview page for the end-user internet banking experience.
 * * Displays account summaries and quick access links.
 */

// Define the required authorization role
$required_role = 'Client';

// Include core files
// NOTE: We use '../' to step up one directory level from /client/ to the root
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check (CRUCIAL SECURITY STEP)
checkAuth($required_role);

$conn = connectDB();
$client_id = $_SESSION['user_id'];
$client_name = 'Client User'; // Default Placeholder
$client_accounts = [];
$total_active_balance = 0.00;
$message = '';
$message_type = '';

// 2. Fetch Client Info and Accounts
try {
    // A. Fetch Client Name
    $stmt_name = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt_name->bind_param("i", $client_id);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    if($result_name->num_rows > 0) {
        $user_info = $result_name->fetch_assoc();
        $client_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
    }
    $stmt_name->close();

    // B. Fetch Client Accounts
    $stmt_accounts = $conn->prepare("SELECT a.account_id, a.account_number, a.current_balance, a.is_active, at.type_name 
                                    FROM accounts a 
                                    JOIN account_types at ON a.type_id = at.type_id
                                    WHERE a.user_id = ? 
                                    ORDER BY a.account_id ASC");
    $stmt_accounts->bind_param("i", $client_id);
    $stmt_accounts->execute();
    $client_accounts = $stmt_accounts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_accounts->close();

    // Calculate total active balance
    foreach ($client_accounts as $acc) {
        if ($acc['is_active']) {
            $total_active_balance += (float)$acc['current_balance'];
        }
    }

} catch (Exception $e) {
    $message = "Database error fetching client data: " . $e->getMessage();
    $message_type = 'error';
    error_log("Client Dashboard DB Error: " . $e->getMessage());
} finally {
    $conn->close();
}

// 3. Check for specific success messages (e.g., after transfer)
if (isset($_GET['msg']) && $_GET['msg'] === 'transfer_success') {
     $message = "Transfer completed successfully!";
     $message_type = 'success';
}

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Welcome, <strong><?php echo $client_name; ?></strong>!</h1>
    <p>Your secure internet banking overview - manage your accounts and transactions.</p>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<section class="dashboard-section">
    <h2 style="margin-bottom: var(--spacing-lg); display: flex; align-items: center; gap: var(--spacing-md);">
        <span style="font-size: 24px;">💰</span>
        <span>Account Summary</span>
    </h2>
    <div class="dashboard-widgets" style="grid-template-columns: 2fr 1fr;">
        
        <div class="widget kpi-card primary-kpi">
            <h3>Total Active Funds Held</h3>
            <p class="kpi-value"><?php echo formatCurrency($total_active_balance); ?></p>
            <a href="transfer_funds.php" class="widget-link">Make a Transfer →</a>
        </div>
        
        <div class="widget quick-action-card">
            <h3 style="margin-bottom: var(--spacing-lg); padding-bottom: var(--spacing-md); border-bottom: 1px solid var(--border-color);">Quick Access</h3>
            <ul>
                <li><a href="transfer_funds.php">💸 Make a Transfer</a></li>
                <li><a href="transaction_history.php">📋 View History</a></li>
                <li><a href="loan_application.php">📝 Apply for Loan</a></li>
                <li><a href="profile.php">👤 My Profile</a></li>
            </ul>
        </div>
        
    </div>
</section>

<section class="dashboard-section" style="margin-top: var(--spacing-2xl);">
    <h2 style="margin-bottom: var(--spacing-lg); display: flex; align-items: center; gap: var(--spacing-md);">
        <span style="font-size: 24px;">💼</span>
        <span>Your Accounts</span>
    </h2>
    <?php if (!empty($client_accounts)): ?>
        <table class="data-table transaction-table">
            <thead>
                <tr>
                    <th>Account Type</th>
                    <th>Account Number</th>
                    <th>Current Balance</th>
                    <th>Status</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($client_accounts as $acc): ?>
                <tr class="<?php echo $acc['is_active'] ? '' : 'inactive-row'; ?>">
                    <td style="font-weight: 500;"><?php echo htmlspecialchars($acc['type_name']); ?></td>
                    <td><code style="background: var(--bg-secondary); padding: 2px 6px; border-radius: 3px; font-size: 12px;">••••<?php echo substr(htmlspecialchars($acc['account_number']), -4); ?></code></td>
                    <td style="text-align: right; font-weight: 600; color: var(--primary-color);"><?php echo formatCurrency($acc['current_balance']); ?></td>
                    <td>
                        <span class="status-badge <?php echo $acc['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $acc['is_active'] ? '✓ Active' : '✗ Closed'; ?>
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <a href="transfer_funds.php?source_acc=<?php echo $acc['account_id']; ?>" class="btn-small btn-view">Transfer</a>
                        <a href="transaction_history.php?acc_id=<?php echo $acc['account_id']; ?>" class="btn-small btn-secondary">History</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="message-box error-message">
            <span style="font-weight: 600;">ℹ️ No Accounts Found</span>
            <span>You currently do not have any active bank accounts. Please contact staff to set up your first account.</span>
        </div>
    <?php endif; ?>
</section>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>