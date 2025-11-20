<?php
/**
 * manage_clients.php (Admin) - Dedicated page for Admins to view and manage ONLY Client users.
 * * Functionally similar to manage_users.php but defaults and filters exclusively for the Client role.
 */

$required_role = 'Admin';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$client_role_name = 'Client';
$client_role_id = null; // Will be fetched from DB

$search_term = $_GET['search'] ?? '';
$clients = [];
$message = '';
$message_type = '';

// 2. Fetch the Client Role ID
try {
    $stmt_role_id = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client'");
    $stmt_role_id->execute();
    $result_role_id = $stmt_role_id->get_result();
    if ($result_role_id->num_rows > 0) {
        $client_role_id = $result_role_id->fetch_assoc()['role_id'];
    } else {
        $message = "System Error: Client role definition not found in the database.";
        $message_type = 'error';
        goto render_page;
    }
    $stmt_role_id->close();
} catch (Exception $e) {
    $message = "Database error fetching client role ID.";
    $message_type = 'error';
    error_log("Admin Manage Clients Role ID Error: " . $e->getMessage());
    goto render_page;
}


// 3. Fetch Clients based on Role and Search
try {
    $sql = "SELECT user_id, first_name, last_name, email, phone, is_active 
            FROM users 
            WHERE role_id = ?";
    
    $params = [$client_role_id];
    $types = "i";

    // Add search condition if a search term is provided
    if (!empty($search_term)) {
        $search_like = "%" . $search_term . "%";
        $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $params = array_merge($params, [$search_like, $search_like, $search_like]);
        $types .= "sss";
    }

    $sql .= " ORDER BY last_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
    } else {
        $message = "No client users found" . (!empty($search_term) ? " matching '{$search_term}'." : ".");
    }
    
    $stmt->close();
} catch (Exception $e) {
    $message = "Database error fetching client list: " . $e->getMessage();
    $message_type = 'error';
    error_log("Admin Client List DB Error: " . $e->getMessage());
}

render_page:
$conn->close();

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Manage Client Accounts</h1>
    <p>Admin oversight for all customer user accounts and access to their financial data.</p>
    <a href="add_user.php?role=client" class="btn-primary" style="float: right;">+ Add New Client Manually</a>
</div>

<div class="filter-section">
    <form method="GET" action="manage_clients.php" class="search-form" style="display: flex; gap: 10px;">
        <input type="text" name="search" placeholder="Search client by name or email..." value="<?php echo htmlspecialchars($search_term); ?>" style="flex-grow: 1;">
        <button type="submit" class="btn-primary">Search</button>
        <a href="manage_clients.php" class="btn-secondary active-filter">View All Clients</a>
    </form>
</div>
<hr>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo nl2br(htmlspecialchars($message)); ?>
    </div>
<?php endif; ?>

<section class="client-list-section">
    <?php if (!empty($clients)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email (Login)</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $client): ?>
            <tr class="<?php echo $client['is_active'] ? '' : 'inactive-row'; ?>">
                <td><?php echo htmlspecialchars($client['user_id']); ?></td>
                <td>**<?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>**</td>
                <td><?php echo htmlspecialchars($client['email']); ?></td>
                <td>
                    <span class="status-badge <?php echo $client['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $client['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </td>
                <td>
                    <a href="user_details.php?id=<?php echo $client['user_id']; ?>" class="btn-small btn-view">Edit Profile</a>
                    
                    <a href="client_accounts.php?id=<?php echo $client['user_id']; ?>" class="btn-small btn-tertiary">View Accounts</a>
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