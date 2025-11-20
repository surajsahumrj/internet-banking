<?php
/**
 * loan_application.php (Client) - Allows the client to submit a new loan application.
 * * Inserts the request into the 'loans' table with status 'Pending'.
 */

$required_role = 'Client';

// Include core files
require_once __DIR__ . '/../includes/sessions.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
checkAuth($required_role);

$conn = connectDB();
$client_id = $_SESSION['user_id'];
$message = '';
$message_type = '';


// --- Loan Calculation Function (For Display/Validation) ---
/**
 * Calculates the fixed monthly payment for a loan (re-included for front-end validation).
 */
function calculateMonthlyPayment(float $principal, float $annual_rate, int $terms_months): float {
    if ($annual_rate <= 0 || $terms_months == 0) return 0.00;
    
    $monthly_rate = $annual_rate / 12;
    $monthly_payment = $principal * (
        ($monthly_rate * pow((1 + $monthly_rate), $terms_months)) / 
        (pow((1 + $monthly_rate), $terms_months) - 1)
    );
    
    return round($monthly_payment, 2);
}
// --------------------------------------------------------


// 2. Handle POST Request (Submit Application)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $amount_requested = number_format((float)($_POST['amount_requested'] ?? 0), 2, '.', '');
    $term_years = (int)($_POST['term_years'] ?? 1);
    $interest_rate = number_format((float)($_POST['interest_rate'] ?? 5.00), 2, '.', ''); // Pre-defined rate for this bank product
    $term_months = $term_years * 12;
    
    // Validation
    if ($amount_requested <= 100.00) {
        $message = "Loan amount must be greater than ₹100.00.";
        $message_type = 'error';
        goto skip_application;
    }
    if ($term_years <= 0 || $term_years > 30) {
        $message = "Loan term must be between 1 and 30 years.";
        $message_type = 'error';
        goto skip_application;
    }

    $conn->begin_transaction();
    
    try {
        // A. Calculate estimated monthly payment for display
        $monthly_payment = calculateMonthlyPayment($amount_requested, $interest_rate / 100, $term_months);
        
        // B. Generate Loan Account Number (Placeholder, Admin sets final number, but reserve one)
        // NOTE: In a professional setup, this number would only be generated upon approval.
        // We'll use a temporary placeholder and rely on Admin to set the final unique number.
        $loan_account_number = 'PENDING-' . $client_id . '-' . time();
        
        // C. Insert Loan Request
        $stmt_insert = $conn->prepare("INSERT INTO loans 
                                        (user_id, loan_account_number, amount_requested, term_months, interest_rate, monthly_payment, status) 
                                        VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        
        $stmt_insert->bind_param("isdddd", 
                                  $client_id, 
                                  $loan_account_number, 
                                  $amount_requested, 
                                  $term_months, 
                                  $interest_rate,
                                  $monthly_payment);

        if (!$stmt_insert->execute()) {
             throw new Exception("Loan application submission failed: " . $conn->error);
        }
        
        $conn->commit();
        $message = "Loan application for **" . formatCurrency($amount_requested) . "** submitted successfully! Estimated monthly payment: " . formatCurrency($monthly_payment) . ". We will notify you when it has been reviewed.";
        $message_type = 'success';
        
        // Clear post data after success
        $_POST = array(); 
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Application failed due to a system error: " . $e->getMessage();
        $message_type = 'error';
        error_log("Client Loan Application Error: " . $e->getMessage());
    }

    skip_application:
}

$conn->close();

// Default form values
$default_amount = htmlspecialchars($_POST['amount_requested'] ?? '5000.00');
$default_term = htmlspecialchars($_POST['term_years'] ?? '5');
$default_rate = htmlspecialchars($_POST['interest_rate'] ?? '5.00'); // Example default rate
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-title">
    <h1>Apply for a Loan</h1>
    <p>Submit a request for a personal or consumer loan.</p>
</div>

<div class="user-form-container" style="max-width: 600px; margin: 0 auto;">

    <?php if ($message): ?>
        <div class="message-box <?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="widget">
        <h2>Loan Request Details</h2>
        <p style="color: var(--danger-color); font-size: 0.9em; margin-bottom: 20px;">
            **Note:** Rates are illustrative. Final terms will be confirmed upon review and approval.
        </p>
        <form method="POST" action="loan_application.php">
            
            <div class="form-group">
                  <label for="amount_requested">Loan Amount Requested (₹) <span class="required">*</span></label>
                <input type="number" step="100.00" min="100.00" id="amount_requested" name="amount_requested" required 
                       value="<?php echo $default_amount; ?>" oninput="updatePaymentEstimate()">
            </div>

            <div class="form-group">
                <label for="term_years">Loan Term (Years) <span class="required">*</span></label>
                <select id="term_years" name="term_years" required onchange="updatePaymentEstimate()">
                    <?php for ($i = 1; $i <= 30; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i == $default_term ? 'selected' : ''; ?>>
                            <?php echo $i; ?> Year(s)
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="interest_rate">Fixed Annual Interest Rate (%)</label>
                <input type="number" step="0.01" min="0.00" id="interest_rate" name="interest_rate" required 
                       value="<?php echo $default_rate; ?>" oninput="updatePaymentEstimate()">
            </div>
            
            <hr>
            
            <div style="padding: 15px; background: var(--background-light); border-radius: 4px; margin-bottom: 20px;">
                <p>Estimated Monthly Payment:</p>
                <h3 id="monthly_payment_display" style="color: var(--primary-color);">...</h3>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%;">Submit Loan Application</button>
        </form>
    </div>
</div>

<script>
    function formatCurrencyJS(value) {
        return '₹' + parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function calculateMonthlyPaymentJS(principal, annualRate, termMonths) {
        if (principal <= 0 || termMonths <= 0) return 0;
        
        let monthlyRate = (annualRate / 100) / 12;

        if (monthlyRate === 0) {
            return principal / termMonths;
        }

        // Amortization formula
        let powerTerm = Math.pow((1 + monthlyRate), termMonths);
        let monthlyPayment = principal * ((monthlyRate * powerTerm) / (powerTerm - 1));
        
        return monthlyPayment;
    }

    function updatePaymentEstimate() {
        const amount = parseFloat(document.getElementById('amount_requested').value) || 0;
        const termYears = parseInt(document.getElementById('term_years').value) || 1;
        const annualRate = parseFloat(document.getElementById('interest_rate').value) || 0;
        
        const termMonths = termYears * 12;

        if (amount > 0 && termMonths > 0) {
            const payment = calculateMonthlyPaymentJS(amount, annualRate, termMonths);
            document.getElementById('monthly_payment_display').textContent = formatCurrencyJS(payment);
        } else {
            document.getElementById('monthly_payment_display').textContent = 'Please enter valid amounts.';
        }
    }

    // Initialize the estimate on page load
    document.addEventListener('DOMContentLoaded', updatePaymentEstimate);
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>