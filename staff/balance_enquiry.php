<?php
/**
 * balance_enquiry.php (Staff) - Utility page for staff to quickly look up the balance of a client account.
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
$account_balance = null;
$selected_account_info = null;
$message = '';
$message_type = '';

// 2. Fetch All Active Client Accounts for the Dropdown
try {
    // Get Client Role ID
    $stmt_role = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client'");
    $stmt_role->execute();
    $client_role_id = $stmt_role->get_result()->fetch_assoc()['role_id'] ?? 0;
    $stmt_role->close();
    
    // Fetch active accounts belonging to clients
    $stmt_accs = $conn->prepare("SELECT a.account_id, a.account_number, a.current_balance, at.type_name,
                                        u.first_name, u.last_name
                                FROM accounts a
                                JOIN users u ON a.user_id = u.user_id
                                JOIN account_types at ON a.type_id = at.type_id
                                WHERE a.is_active = TRUE AND u.role_id = ?
                                ORDER BY u.last_name, a.account_number");
    $stmt_accs->bind_param("i", $client_role_id);
    $stmt_accs->execute();
    $result_accs = $stmt_accs->get_result();
    while ($row = $result_accs->fetch_assoc()) {
        $accounts[] = $row;
    }
    $stmt_accs->close();
} catch (Exception $e) {
    $message = "Database error fetching account list.";
    $message_type = 'error';
    error_log("Balance Enquiry Account Fetch Error: " . $e->getMessage());
}

$selected_account_id = (int)($_POST['account_id'] ?? ($_GET['account_id'] ?? 0));

// 3. Handle Balance Lookup (POST or GET)
if ($selected_account_id > 0) {
    
    // Find the selected account details from the fetched list (prevents a second DB query)
    $found = false;
    foreach ($accounts as $acc) {
        if ($acc['account_id'] == $selected_account_id) {
            $selected_account_info = $acc;
            $account_balance = (float)$acc['current_balance'];
            $found = true;
            break;
        }
    }

    if (!$found) {
        $message = "Selected account is invalid or inactive.";
        $message_type = 'error';
    } else {
        $message_type = 'success';
        $message = "Balance retrieved successfully for account **{$selected_account_info['account_number']}**.";
    }
}

$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Account Balance Enquiry</h1>
    <p>Quickly look up the current balance of any active client bank account.</p>
</div>

<div class="user-form-container" style="max-width: 600px; margin: 0 auto;">
    
    <?php if ($message): ?>
        <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
            <?php echo nl2br(htmlspecialchars($message)); ?>
        </div>
    <?php endif; ?>

    <div class="widget">
        <h2>Select Account</h2>
        <form method="POST" action="balance_enquiry.php">
            
            <div class="form-group">
                <label for="account_id">Client Account Number</label>
                <select id="account_id" name="account_id" required>
                    <option value="">-- Select Client Account --</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc['account_id']; ?>" 
                                <?php echo $acc['account_id'] == $selected_account_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($acc['first_name'] . ' ' . $acc['last_name']); ?> | #<?php echo htmlspecialchars($acc['account_number']); ?> (<?php echo htmlspecialchars($acc['type_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">Check Balance</button>
        </form>
    </div>

    <?php if ($account_balance !== null): ?>
    <div class="widget kpi-card primary-kpi" style="margin-top: 20px;">
        <h2>Current Account Balance</h2>
        <p style="font-size: 1.2em; color: var(--secondary-color);">Account: **<?php echo htmlspecialchars($selected_account_info['account_number']); ?>**</p>
        <p style="font-size: 2.5em; font-weight: bold; color: var(--success-color); margin-top: 10px;">
            <?php echo formatCurrency($account_balance); ?>
        </p>
        <p style="font-size: 0.9em; margin-top: 10px;">Account Holder: <?php echo htmlspecialchars($selected_account_info['first_name'] . ' ' . $selected_account_info['last_name']); ?></p>
    </div>
    <?php endif; ?>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>