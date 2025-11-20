<?php
/**
 * system_settings.php (Admin) - Configuration of system-wide settings.
 * * Uses config/settings.php to store and retrieve non-database settings.
 */

$required_role = 'Admin';
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

checkAuth($required_role);

$message = '';
$message_type = '';
$settings_file = __DIR__ . '/../config/settings.php';
$settings = [];

// 1. Load Settings (from config/settings.php)
if (file_exists($settings_file)) {
    // Load the file, which defines the $SETTINGS array
    require_once $settings_file;
    $settings = $SETTINGS;
} else {
    // Default settings if file doesn't exist
    $settings = [
        'BANK_NAME' => 'SecureBank',
        'BANK_EMAIL' => 'support@securebank.com',
        'TRANSFER_FEE_PERCENTAGE' => '0.50', // 0.50% fee
        'MIN_DEPOSIT_AMOUNT' => '10.00',
        'MAX_TRANSFER_DAILY' => '10000.00'
    ];
}


// 2. Handle POST Request to Update Settings
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Sanitize and validate inputs
    $new_settings = [
        'BANK_NAME' => trim($_POST['BANK_NAME'] ?? $settings['BANK_NAME']),
        'BANK_EMAIL' => trim($_POST['BANK_EMAIL'] ?? $settings['BANK_EMAIL']),
        'TRANSFER_FEE_PERCENTAGE' => number_format((float)($_POST['TRANSFER_FEE_PERCENTAGE'] ?? 0.00), 2, '.', ''),
        'MIN_DEPOSIT_AMOUNT' => number_format((float)($_POST['MIN_DEPOSIT_AMOUNT'] ?? 0.00), 2, '.', ''),
        'MAX_TRANSFER_DAILY' => number_format((float)($_POST['MAX_TRANSFER_DAILY'] ?? 0.00), 2, '.', '')
    ];
    
    // Validation check (e.g., email format)
    if (!filter_var($new_settings['BANK_EMAIL'], FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid Bank Email address format.";
        $message_type = 'error';
    } else {
        
        // 3. Write Settings to File (config/settings.php)
        $content = "<?php\n";
        $content .= "/**\n * settings.php - System Configuration Constants\n */\n\n";
        $content .= "\$SETTINGS = [\n";
        
        foreach ($new_settings as $key => $value) {
            $content .= "\t'$key' => '" . $value . "',\n";
        }
        
        $content .= "];\n";
        $content .= "?>";

        // Attempt to write to file
        if (file_put_contents($settings_file, $content) !== false) {
            $settings = $new_settings; // Update local array to show new values
            $message = "System settings updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: Could not write to settings file. Check file permissions on config/settings.php.";
            $message_type = 'error';
            error_log("Settings write error: Permission denied on $settings_file");
        }
    }
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>System Settings</h1>
    <p>Configure bank name, contact information, and critical financial limits.</p>
</div>

<?php if ($message): ?>
    <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<section class="settings-form-section">
    <form method="POST" action="system_settings.php" class="settings-form">
        
        <h2>General Information</h2>
        <div class="form-group">
            <label for="BANK_NAME">Bank Name</label>
            <input type="text" id="BANK_NAME" name="BANK_NAME" 
                   value="<?php echo htmlspecialchars($settings['BANK_NAME'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="BANK_EMAIL">Official Contact Email</label>
            <input type="email" id="BANK_EMAIL" name="BANK_EMAIL" 
                   value="<?php echo htmlspecialchars($settings['BANK_EMAIL'] ?? ''); ?>" required>
        </div>
        
        <h2>Financial Parameters</h2>
        <div class="form-group">
            <label for="TRANSFER_FEE_PERCENTAGE">Transfer Fee Percentage (%)</label>
            <input type="number" step="0.01" min="0" id="TRANSFER_FEE_PERCENTAGE" name="TRANSFER_FEE_PERCENTAGE" 
                   value="<?php echo htmlspecialchars($settings['TRANSFER_FEE_PERCENTAGE'] ?? '0.00'); ?>" required>
        </div>
        <div class="form-group">
            <label for="MIN_DEPOSIT_AMOUNT">Minimum Deposit Amount (₹)</label>
            <input type="number" step="0.01" min="1.00" id="MIN_DEPOSIT_AMOUNT" name="MIN_DEPOSIT_AMOUNT" 
                   value="<?php echo htmlspecialchars($settings['MIN_DEPOSIT_AMOUNT'] ?? '0.00'); ?>" required>
        </div>
        <div class="form-group">
            <label for="MAX_TRANSFER_DAILY">Maximum Daily Transfer Limit (₹)</label>
            <input type="number" step="0.01" min="100.00" id="MAX_TRANSFER_DAILY" name="MAX_TRANSFER_DAILY" 
                   value="<?php echo htmlspecialchars($settings['MAX_TRANSFER_DAILY'] ?? '0.00'); ?>" required>
        </div>
        
        <button type="submit" class="btn-primary">Save System Settings</button>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>