<?php
/**
 * header.php - Reusable Header Component
 * * Includes: Starts the main HTML structure, links CSS, and displays role-based navigation.
 * * This file assumes sessions.php has already been included and checkAuth() has run 
 * * successfully on the calling page, so $_SESSION['user_role'] is safe to use.
 */

// Define the base path for assets (assuming this file is 1 level deep, e.g., in /includes or /admin)
$base_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/staff/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/client/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/includes/') !== false) ? '../' : '';

// Get the user role for conditional navigation display
$user_role = $_SESSION['user_role'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internet Banking System - <?php echo htmlspecialchars($user_role); ?> Portal</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
    </head>
<body>

<header class="main-header">
    <div class="logo-container">
        <a href="<?php echo $base_path; ?>index.php" class="logo">🏦 SecureBank</a>
    </div>
    
    <nav class="main-nav">
        <ul>

            <?php if ($user_role !== 'Guest'): ?>
                <li><a href="<?php echo $base_path . strtolower($user_role); ?>/dashboard.php">Dashboard</a></li>
            <?php endif; ?>

            <?php if ($user_role === 'Client'): ?>
                <li><a href="<?php echo $base_path; ?>client/accounts.php">My Accounts</a></li>
                <li><a href="<?php echo $base_path; ?>client/transfer_funds.php">Transfer</a></li>
                <li><a href="<?php echo $base_path; ?>client/transaction_history.php">History</a></li>
                <li><a href="<?php echo $base_path; ?>client/loan_application.php">Loans</a></li>

            <?php elseif ($user_role === 'Staff'): ?>
                <li><a href="<?php echo $base_path; ?>staff/manage_clients.php">Manage Clients</a></li>
                <li><a href="<?php echo $base_path; ?>staff/process_transaction.php">Teller Services</a></li>
                <li><a href="<?php echo $base_path; ?>staff/view_transactions.php">Transactions</a></li>
                <li><a href="<?php echo $base_path; ?>staff/pending_accounts.php">Pending Accounts</a></li>

            <?php elseif ($user_role === 'Admin'): ?>
                <li><a href="<?php echo $base_path; ?>admin/manage_users.php">Manage Users</a></li>
                <li><a href="<?php echo $base_path; ?>admin/process_transaction.php">Transaction</a></li>
                <li><a href="<?php echo $base_path; ?>admin/financial_reports.php">Reports</a></li>
                <li><a href="<?php echo $base_path; ?>admin/manage_account_types.php">Account Config</a></li>
                <li><a href="<?php echo $base_path; ?>admin/system_settings.php">Settings</a></li>
            <?php endif; ?>

            <li class="nav-right">
                <?php if ($user_role !== 'Guest'): ?>
                    <?php if ($user_role === 'Client'): ?>
                        <a href="<?php echo $base_path; ?>client/profile.php">Profile</a>
                    <?php endif; ?>
                    <a href="<?php echo $base_path; ?>logout.php" class="btn-logout">Logout</a>
                <?php else: ?>
                    <a href="<?php echo $base_path; ?>login.php" class="btn-primary">Login</a>
                    <a href="<?php echo $base_path; ?>signup.php" class="btn-secondary">Sign Up</a>
                <?php endif; ?>
            </li>
        </ul>
    </nav>
</header>

<main class="main-content-wrapper">