<?php
/**
 * terms.php - Terms of Service Page
 */
require_once __DIR__ . '/sessions.php';
?>

<?php require_once __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1>Terms of Service</h1>
    <p>Legal terms and conditions for using SecureBank's online services.</p>
</div>

<section class="dashboard-section">
    <div class="widget">
        <h3>Acceptance</h3>
        <p class="text-muted">By using our services you agree to these terms. Please read them carefully.</p>

        <h3>Services</h3>
        <p class="text-muted">We provide online banking services subject to applicable laws and regulations.</p>

        <h3>User Responsibilities</h3>
        <p class="text-muted">Users must protect their login credentials and notify us promptly of any suspicious activity.</p>

        <h3>Limitation of Liability</h3>
        <p class="text-muted">SecureBank is not liable for losses due to unauthorized access where users have not followed security best practices.</p>

        <h3>Contact</h3>
        <p class="text-muted">Questions about these terms should be directed to <a href="contact.php">Support</a>.</p>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
