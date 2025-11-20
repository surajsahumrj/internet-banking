<?php
/**
 * manage_clients.php (Staff) - Search and manage the list of client users.
 * * This page focuses ONLY on the 'Client' role.
 */

$required_role = 'Staff';

// Include core files
// NOTE: We use '../' to step up one directory level from /staff/ to the root
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$client_role_id = null;
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
        $message = "System Error: Client role definition not found.";
        $message_type = 'error';
        goto render_page;
    }
    $stmt_role_id->close();
} catch (Exception $e) {
    $message = "Database error fetching client role ID.";
    $message_type = 'error';
    error_log("Staff Manage Clients Role ID Error: " . $e->getMessage());
    goto render_page;
}


// 3. Fetch Clients based on Search
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
    error_log("Staff Client List DB Error: " . $e->getMessage());
}

render_page:
$conn->close();

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Manage Client Accounts</h1>
    <p>Search, view details, and access banking services for clients.</p>
    <!-- Add button moved into the search row for alignment with the search form below -->
</div>

<div class="filter-section">
    <form method="GET" action="manage_clients.php" class="search-form" style="display: flex; gap: 10px; align-items: center; flex-wrap: nowrap;">
        <div class="search-input-wrap" style="position: relative; flex: 1; min-width: 0;">
            <input type="text" name="search" placeholder="Search client by name or email..." value="<?php echo htmlspecialchars($search_term); ?>" style="width:100%; padding-right: 42px; box-sizing: border-box;">

            <!-- Icon inside the input (right) -->
            <button type="submit" class="btn-icon" aria-label="Search" title="Search" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: none; background: transparent; padding: 4px; display: inline-flex; align-items: center; color: inherit;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <!-- Add New Client button immediately to the right (same line) -->
        <a href="add_client.php" class="btn-primary" style="white-space: nowrap;">+ Add New Client</a>
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
                <th>Phone</th>
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
                <td><?php echo htmlspecialchars($client['phone'] ?: 'N/A'); ?></td>
                <td>
                    <span class="status-badge <?php echo $client['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $client['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </td>
                <td>
                    <a href="client_details.php?id=<?php echo $client['user_id']; ?>" class="btn-small btn-view">View Details</a>
                    
                    <?php 
                        // Simplified check to see if client needs account opened
                        // NOTE: This assumes a client needs a bank account linked to their user_id
                        if ($client['is_active']): // Add a logic to check for existing accounts here in real app
                    ?>
                         <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<?php 
// NOTE: Ensure all necessary CSS styles (like .data-table, .status-badge, etc.) are in assets/css/style.css
require_once __DIR__ . '/../includes/footer.php'; 
?>