<?php
/**
 * contact.php - Contact / Support Page
 */
require_once __DIR__ . '/sessions.php';
require_once __DIR__ . '/functions.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $error_message = 'Please fill out all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // For the local development environment we won't attempt to send email.
        // Store or notify support via DB / email in production. Here we simulate success.
        $success_message = 'Thanks, ' . htmlspecialchars($name) . '. Your message has been received. Our support team will contact you soon.';

        // Optionally, you could log the message to a support table or send mail using mail().
    }
}

?>

<?php require_once __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1>Contact Support</h1>
    <p>Get in touch with our support team for help with accounts, transactions, and services.</p>
</div>

<div class="user-form-container">
    <?php if ($error_message): ?>
        <div class="message-box error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="message-box success-message">
            <?php echo $success_message; ?>
        </div>
    <?php else: ?>
        <form method="POST" action="contact.php">
            <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" id="name" name="name" required placeholder="Full name">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="your.email@example.com">
            </div>

            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" required placeholder="How can we help?"></textarea>
            </div>

            <button type="submit" class="btn-primary">Send Message</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
