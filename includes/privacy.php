<?php
/**
 * privacy.php - Privacy Policy Page
 */
require_once __DIR__ . '/sessions.php';
?>

<?php require_once __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1>Privacy Policy</h1>
    <p>How SecureBank collects, uses, and protects your personal information.</p>
</div>

<section class="dashboard-section">
    <div class="widget">
        <h3>Information We Collect</h3>
        <p class="text-muted">We collect personal information necessary to provide banking services, including name, contact details, identification documents, and transactional data.</p>

        <h3>How We Use Information</h3>
        <p class="text-muted">Information is used to deliver banking services, process transactions, comply with legal requirements, and improve our services.</p>

        <h3>Security</h3>
        <p class="text-muted">We protect your data using industry-standard measures including encryption, strong access controls, and secure hosting environments.</p>

        <h3>Retention</h3>
        <p class="text-muted">We retain information as required for operational, legal, and regulatory purposes.</p>

        <h3>Contact</h3>
        <p class="text-muted">If you have questions about privacy, please <a href="contact.php">contact our support team</a>.</p>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
