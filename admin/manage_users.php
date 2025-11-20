<?php
/**
 * manage_users.php (Admin) - Management of all system users (Staff and Clients).
 * * Allows filtering by role and searching by name/email.
 */

$required_role = 'Admin';

// Include core files
// NOTE: We use '../' to step up one directory level from /admin/ to the root
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$current_role_name = 'Client'; // Default role to show
$current_role_id = 3; // Default role ID for 'Client' (Assuming 1=Admin, 2=Staff, 3=Client)

// Determine which role to manage based on URL parameter
if (isset($_GET['role'])) {
    $requested_role = strtolower($_GET['role']);
    if ($requested_role === 'staff') {
        $current_role_name = 'Staff';
        $current_role_id = 2;
    } elseif ($requested_role === 'admin') {
        $current_role_name = 'Admin';
        $current_role_id = 1;
    }
}


$search_term = $_GET['search'] ?? '';
$users = [];
$message = '';
$message_type = '';

// 2. Fetch Users based on Role and Search
try {
    // --- Get the role ID dynamically in case the IDs are different ---
    $stmt_role_id = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = ?");
    $stmt_role_id->bind_param("s", $current_role_name);
    $stmt_role_id->execute();
    $result_role_id = $stmt_role_id->get_result();
    if ($result_role_id->num_rows > 0) {
        $current_role_id = $result_role_id->fetch_assoc()['role_id'];
    } else {
        $message = "Error: Could not determine the role ID for {$current_role_name}.";
        $message_type = 'error';
        goto render_page;
    }
    $stmt_role_id->close();
    // ------------------------------------------------------------------

    $sql = "SELECT user_id, first_name, last_name, email, phone, is_active 
            FROM users 
            WHERE role_id = ?";
    
    $params = [$current_role_id];
    $types = "i";

    // Add search condition if a search term is provided
    if (!empty($search_term)) {
        $search_like = "%" . $search_term . "%";
        // Search across first_name, last_name, or email
        $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $params = array_merge($params, [$search_like, $search_like, $search_like]);
        $types .= "sss";
    }

    $sql .= " ORDER BY last_name ASC";

    $stmt = $conn->prepare($sql);
    
    // Dynamically bind parameters (must be done carefully in PHP with call_user_func_array)
    // For simplicity, we use the prepared array method:
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        $message = "No {$current_role_name} users found" . (!empty($search_term) ? " matching '{$search_term}'." : ".");
    }
    
    $stmt->close();
} catch (Exception $e) {
    $message = "Database error fetching users: " . $e->getMessage();
    $message_type = 'error';
    error_log("Manage Users DB Error: " . $e->getMessage());
}

render_page:
$conn->close();

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Manage <?php echo htmlspecialchars($current_role_name); ?> Accounts</h1>
    <p>View, search, and manage the login details and status of all <?php echo strtolower(htmlspecialchars($current_role_name)); ?> users.</p>
</div>

<div class="filter-section">
    <div class="filter-row">
        <form method="GET" action="manage_users.php" class="search-form" style="flex:1;">
            <input type="hidden" name="role" value="<?php echo strtolower($current_role_name); ?>">
            <div class="search-wrap">
                <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="search-btn" aria-label="Search">
                    <!-- inline magnifier icon -->
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </form>

        <div class="role-buttons">
            <a href="manage_users.php?role=client" class="btn-secondary <?php echo $current_role_name === 'Client' ? 'active-filter' : ''; ?>">Clients</a>
            <a href="manage_users.php?role=staff" class="btn-secondary <?php echo $current_role_name === 'Staff' ? 'active-filter' : ''; ?>">Staff</a>
            <a href="manage_users.php?role=admin" class="btn-secondary <?php echo $current_role_name === 'Admin' ? 'active-filter' : ''; ?>">Admins</a>
        </div>

        <div class="add-user-wrap">
            <a href="add_user.php" class="btn-primary">+ Add New User</a>
        </div>
    </div>
</div>
<hr>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo nl2br(htmlspecialchars($message)); ?>
    </div>
<?php endif; ?>

<section class="user-list-section">
    <?php if (!empty($users)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr class="<?php echo $user['is_active'] ? '' : 'inactive-row'; ?>">
                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></td>
                <td>
                    <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </td>
                <td>
                    <a href="user_details.php?id=<?php echo $user['user_id']; ?>" class="btn-small btn-view">View/Edit</a>
                    <?php if ($current_role_name === 'Client'): ?>
                         <a href="client_accounts.php?id=<?php echo $user['user_id']; ?>" class="btn-small btn-tertiary">Accounts</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<?php 
// NOTE: You need to add styles for .active-filter, .btn-secondary, .data-table, etc. to assets/css/style.css
require_once __DIR__ . '/../includes/footer.php'; 
?>