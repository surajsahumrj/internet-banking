<?php
/**
 * pending_accounts.php (Staff) - Lists clients who have signed up but have no bank accounts open yet.
 * * These are the clients who are "pending" their first banking service setup.
 */

$required_role = 'Staff';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$pending_clients = [];
$message = '';
$message_type = '';

// 2. Fetch Pending Clients (Clients with Role=Client AND NO entries in the Accounts table)
try {
    $sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at
            FROM users u
            JOIN user_roles r ON u.role_id = r.role_id
            LEFT JOIN accounts a ON u.user_id = a.user_id
            WHERE r.role_name = 'Client' AND a.account_id IS NULL AND u.is_active = TRUE
            ORDER BY u.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pending_clients[] = $row;
        }
    } else {
        $message = "Great job! All registered clients currently have at least one active bank account.";
        $message_type = 'success';
    }
    
    $stmt->close();
} catch (Exception $e) {
    $message = "Database error fetching pending clients: " . $e->getMessage();
    $message_type = 'error';
    error_log("Staff Pending Accounts DB Error: " . $e->getMessage());
} finally {
    $conn->close();
}

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Pending Account Setup</h1>
    <p>List of clients who have registered but require an initial bank account setup.</p>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo nl2br(htmlspecialchars($message)); ?>
    </div>
<?php endif; ?>

<section class="pending-client-list">
    <?php if (!empty($pending_clients)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Registration Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pending_clients as $client): ?>
            <tr>
                <td><?php echo htmlspecialchars($client['user_id']); ?></td>
                <td>**<?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>**</td>
                <td><?php echo htmlspecialchars($client['email']); ?></td>
                <td><?php echo htmlspecialchars($client['phone'] ?: 'N/A'); ?></td>
                <td><?php echo formatDate($client['created_at'], 'M j, Y'); ?></td>
                <td>
                    <a href="open_account.php?client_id=<?php echo $client['user_id']; ?>" class="btn-small btn-primary">Open Account</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>