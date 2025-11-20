<?php
/**
 * functions.php - Core Helper Functions
 * * Contains reusable functions for security, data formatting, and banking logic.
 */

// ------------------------------------------------------------------
// 1. Security and Hashing Functions
// ------------------------------------------------------------------

/**
 * Hashes a password using the secure PASSWORD_DEFAULT algorithm.
 * @param string $password The plain-text password.
 * @return string The hashed password string.
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Generates a strong, random password reset token.
 * @param int $length The desired length of the token (default 64 characters).
 * @return string The securely generated token.
 */
function generateSecureToken(int $length = 64): string {
    // Generates random bytes and converts to a hex string
    return bin2hex(random_bytes($length / 2));
}


// ------------------------------------------------------------------
// 2. Banking and Data Formatting Functions
// ------------------------------------------------------------------

/**
 * Generates a unique, 10-digit bank account number.
 * Note: A proper system should check the database to ensure uniqueness.
 * @param object $conn The database connection object (mysqli).
 * @return string A unique 10-digit account number.
 */
function generateUniqueAccountNumber($conn): string {
    do {
        // Generate a random 10-digit number
        $number = str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        
        // Check database for uniqueness (Crucial Step)
        $stmt = $conn->prepare("SELECT account_number FROM accounts WHERE account_number = ?");
        $stmt->bind_param("s", $number);
        $stmt->execute();
        $stmt->store_result();
        $is_unique = $stmt->num_rows === 0;
        $stmt->close();

    } while (!$is_unique);

    return $number;
}

/**
 * Formats a raw currency value into a displayable string (e.g., 1234.50 -> "₹1,234.50").
 * @param float $amount The numerical amount.
 * @param string $currency_symbol The symbol to prepend (default '₹').
 * @return string The formatted currency string.
 */
function formatCurrency(float $amount, string $currency_symbol = '₹'): string {
    return $currency_symbol . number_format($amount, 2, '.', ',');
}

/**
 * Formats a MySQL date/datetime string into a readable format.
 * @param string $datetime The MySQL datetime string (e.g., 'YYYY-MM-DD HH:MM:SS').
 * @param string $format The desired output format (default 'M j, Y g:i A').
 * @return string The formatted date/time string.
 */
function formatDate(string $datetime, string $format = 'M j, Y g:i A'): string {
    try {
        $date_obj = new DateTime($datetime);
        return $date_obj->format($format);
    } catch (Exception $e) {
        // Handle invalid date strings gracefully
        return "Invalid Date";
    }
}

/**
 * Get the next user_id for a given role in a transaction-safe way.
 * This function starts a transaction and locks the relevant rows (SELECT ... FOR UPDATE).
 * The caller MUST perform the subsequent INSERT using the same $conn and then commit or rollback.
 *
 * @param mysqli $conn The active DB connection (InnoDB required)
 * @param int $role_id The role_id for which to allocate the next user_id
 * @return int The next user_id to use for the INSERT
 */
function getNextUserId(mysqli $conn, int $role_id): int {
    // Define base ranges for role IDs (adjust if your seeded values differ)
    $base_by_role = [
        1 => 1001, // Admin
        2 => 2001, // Staff
        3 => 3001, // Client
    ];

    $base = $base_by_role[$role_id] ?? 3001;

    // Start transaction so SELECT ... FOR UPDATE locks the necessary rows
    $conn->begin_transaction();

    $sql = "SELECT IFNULL(MAX(user_id), 0) AS mx FROM users WHERE role_id = ? FOR UPDATE";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Unable to prepare; throw to let caller handle rollback
        throw new Exception('Failed to prepare getNextUserId statement: ' . $conn->error);
    }
    $stmt->bind_param('i', $role_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    $mx = (int)($row['mx'] ?? 0);
    $next = max($mx + 1, $base);

    return $next;
}
?>