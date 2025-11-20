<?php
/**
 * footer.php - Reusable Footer Component
 * * Includes: Closes the main content wrapper, provides copyright info, and links JavaScript files.
 */

// Define the base path for assets, consistent with header.php logic
$base_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/staff/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/client/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/includes/') !== false) ? '../' : '';

?>
</main>
<footer class="main-footer">
    <div class="footer-content">
        <p>&copy; <?php echo date("Y"); ?> SecureBank Internet Banking System. All Rights Reserved.</p>
    </div>
    <div class="footer-links">
        <a href="<?php echo $base_path; ?>includes/privacy.php">Privacy Policy</a> | 
        <a href="<?php echo $base_path; ?>includes/terms.php">Terms of Service</a> | 
        <a href="<?php echo $base_path; ?>includes/contact.php">Contact Support</a>
    </div>
</footer>

<script src="<?php echo $base_path; ?>assets/js/main.js"></script>

</body>
</html>