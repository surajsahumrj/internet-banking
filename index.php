<?php
/**
 * index.php - Public Home Page
 * Modern landing page using the app design system (assets/css/style.css)
 * Shows simple KPIs and quick actions; redirects authenticated users to their dashboard.
 */

require_once __DIR__ . '/includes/sessions.php';
// Redirect authenticated users to their dashboard
if (isset($_SESSION['user_id'])) {
        if ($_SESSION['user_role'] === 'Admin') {
                header('Location: admin/dashboard.php');
                exit;
        } elseif ($_SESSION['user_role'] === 'Staff') {
                header('Location: staff/dashboard.php');
                exit;
        } else {
                header('Location: client/dashboard.php');
                exit;
        }
}

?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- Landing Hero -->
<section class="hero">
    <div class="container hero-inner">
        <div class="hero-content">
            <h1>SecureBank — Banking, Simplified</h1>
            <p class="lead">Fast, secure and intuitive online banking for personal and business customers. Open an account, transfer funds, and manage finances from anywhere.</p>
            <div class="hero-ctas">
                <a href="login.php" class="btn-primary btn-large">Log In</a>
                <a href="signup.php" class="btn-secondary btn-large">Open An Account</a>
            </div>
            <p class="hero-note">Trusted security &amp; 24/7 support — your money is safe with us.</p>
        </div>
        <div class="hero-media">
            <img src="assets/img/hero.svg" alt="SecureBank illustration" class="hero-image">
        </div>
    </div>
</section>

<!-- Services -->
<section class="section services">
    <div class="container">
        <h2 class="section-title">We Provide World's Best Banking Services</h2>
        <p class="section-sub">We use secure and powerful servers for safe online banking and provide the fastest, most secure bank-to-bank transactions.</p>

        <div class="services-grid">
  <div class="service-card">
    <h3>Secure Payment</h3>
    <p>The security of customers is our number one priority. We use end-to-end encryption for payments.</p>
  </div>

  <div class="service-card">
    <h3>Credit &amp; Debit Cards</h3>
    <p>Manage your credit and debit card activity easily and securely without any problems.</p>
  </div>

  <div class="service-card">
    <h3>Online Banking</h3>
    <p>Access your account 24/7 without visiting a branch — full online banking availability.</p>
  </div>

  <div class="service-card">
    <h3>Insurance</h3>
    <p>Secure your and your family’s future with our insurance plans and services.</p>
  </div>

  <div class="service-card">
    <h3>24 x 7 Service</h3>
    <p>Our team is ready to solve your problems at any time of the day.</p>
  </div>

  <div class="service-card">
    <h3>Loans</h3>
    <p>Loans approved with digital documents and digital signatures for fast processing.</p>
  </div>

  <div class="service-card">
    <h3>Savings &amp; Investments</h3>
    <p>Flexible savings accounts, fixed deposits and tailored investment plans to grow your wealth.</p>
  </div>

  <div class="service-card">
    <h3>Fraud Protection</h3>
    <p>Advanced monitoring and instant alerts to protect your account against suspicious activity.</p>
  </div>
</div>
    </div>
</section>

<!-- Features -->
<section class="section features">
    <div class="container">
        <h2 class="section-title">Features</h2>
        <div class="features-grid">
            <div class="feature-item">
                <h4>Secure Pay</h4>
                <p>We provide end-to-end encrypted details during payments.</p>
            </div>
            <div class="feature-item">
                <h4>Graphical Dashboard</h4>
                <p>Easy-to-use dashboard with visualized graphs for better insights.</p>
            </div>
            <div class="feature-item">
                <h4>2 Step Verification</h4>
                <p>An extra layer of security to protect accounts if the password is compromised.</p>
            </div>
            <div class="feature-item">
                <h4>Instant Message Alert</h4>
                <p>Receive instant payment alerts after every transaction on your account.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
