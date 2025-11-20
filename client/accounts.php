<?php
/**
 * accounts.php (Client) - Lists all bank accounts belonging to the logged-in client.
 * * Provides details and links for quick transactions.
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
$total_active_balance = 0.00;
$message = '';
$message_type = '';

// 2. Fetch Client Accounts
try {
    $stmt_accounts = $conn->prepare("SELECT a.account_id, a.account_number, a.current_balance, a.is_active, a.opened_date, at.type_name, at.interest_rate 
                                    FROM accounts a 
                                    JOIN account_types at ON a.type_id = at.type_id
                                    WHERE a.user_id = ? 
                                    ORDER BY a.is_active DESC, a.account_id ASC");
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
    $message = "Database error fetching account details: " . $e->getMessage();
    $message_type = 'error';
    error_log("Client Accounts DB Error: " . $e->getMessage());
} finally {
    $conn->close();
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>My Accounts</h1>
    <p>View your consolidated balances and account details.</p>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<section class="account-overview">
    <div class="widget kpi-card primary-kpi" style="max-width: 350px; margin-bottom: 30px;">
        <h3>Total Available Funds</h3>
        <p class="kpi-value"><?php echo formatCurrency($total_active_balance); ?></p>
    </div>
    
    <h2>Detailed Account Information</h2>
    <?php if (!empty($client_accounts)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Account Number</th>
                    <th>Current Balance</th>
                    <th>Rate/Terms</th>
                    <th>Opened Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($client_accounts as $acc): ?>
                <tr class="<?php echo $acc['is_active'] ? '' : 'inactive-row'; ?>">
                    <td>**<?php echo htmlspecialchars($acc['type_name']); ?>**</td>
                    <td><?php echo htmlspecialchars($acc['account_number']); ?></td>
                    <td>**<?php echo formatCurrency($acc['current_balance']); ?>**</td>
                    <td>
                        <?php 
                            // Only show interest rate for active accounts that are not loans (which have special terms)
                            if ($acc['is_active'] && $acc['type_name'] != 'Loan'):
                                echo htmlspecialchars($acc['interest_rate']) . '% APR';
                            elseif ($acc['type_name'] == 'Loan'):
                                echo 'See Loan Details';
                            else:
                                echo 'N/A';
                            endif;
                        ?>
                    </td>
                    <td><?php echo formatDate($acc['opened_date'], 'M j, Y'); ?></td>
                    <td>
                        <span class="status-badge <?php echo $acc['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $acc['is_active'] ? 'Active' : 'Closed'; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($acc['is_active']): ?>
                            <a href="transfer_funds.php?source_acc=<?php echo $acc['account_id']; ?>" class="btn-small btn-primary">Transfer</a>
                            <a href="transaction_history.php?acc_id=<?php echo $acc['account_id']; ?>" class="btn-small btn-secondary">History</a>
                        <?php else: ?>
                            <span style="color: var(--secondary-color);">No action</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="message-box error-message">
            <p>You currently do not have any bank accounts associated with your profile.</p>
        </div>
    <?php endif; ?>
</section>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>