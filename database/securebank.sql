-- =======================================================
-- Import-ready SQL for an existing database (do NOT create/drop DB)
-- Make sure you have selected the correct target database in phpMyAdmin
-- =======================================================

-- =======================================================
-- 1. VARIABLES / PASSWORD HASHES (for test users)
-- =======================================================
-- Passwords are bcrypt hashes for testing (verify with password_verify in PHP)
SET @default_password_hash = '$2y$10$oBK2lPs8sQfUTAjBZmWDIO9RcLQEJjKspDMUcP1OKkqgy4WzefHxu';
SET @admin_password_hash   = '$2y$10$DEm6t4K9U0RDnZOBdufCSuUzSJs76SYf/3oNNRWrpfseJ0nKIsHoO';

-- =======================================================
-- 2. CORE TABLES CREATION
-- (Creates tables only — safe to run in an existing DB)
-- =======================================================

-- Table: user_roles
CREATE TABLE IF NOT EXISTS user_roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: users
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: account_types
CREATE TABLE IF NOT EXISTS account_types (
    type_id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(100) NOT NULL UNIQUE,
    interest_rate DECIMAL(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: accounts
CREATE TABLE IF NOT EXISTS accounts (
    account_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    account_number VARCHAR(20) NOT NULL UNIQUE,
    type_id INT NOT NULL,
    current_balance DECIMAL(15,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    opened_date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (type_id) REFERENCES account_types(type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: transactions
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description VARCHAR(255),
    recipient_account_number VARCHAR(20),
    status VARCHAR(50) DEFAULT 'Completed',
    FOREIGN KEY (account_id) REFERENCES accounts(account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: password_resets
CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: loans
CREATE TABLE IF NOT EXISTS loans (
    loan_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    loan_account_number VARCHAR(20) NOT NULL UNIQUE,
    amount_requested DECIMAL(15,2) NOT NULL,
    term_months INT NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    monthly_payment DECIMAL(10,2),
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approval_date DATE NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- 3. SEED DATA INSERTION
-- (Inserts roles, account types, users, accounts, transactions, loans)
-- =======================================================

-- 3.1 User Roles
INSERT IGNORE INTO user_roles (role_id, role_name) VALUES
(1, 'Admin'),
(2, 'Staff'),
(3, 'Client');

-- 3.2 Account Types
INSERT IGNORE INTO account_types (type_id, type_name, interest_rate) VALUES
(1, 'Savings', 3.50),
(2, 'Checking', 0.50),
(3, 'Loan', 12.00);

-- 3.3 Users
-- Keep ONE admin: Suraj Sahu (user_id = 1000)
-- Other sample users for staff and clients kept as-is
INSERT INTO users (user_id, role_id, first_name, last_name, email, phone, password_hash, created_at, is_active) VALUES
(1001, 1, 'Suraj', 'Sahu', 'admin@securebank.com',  '9616685651', @admin_password_hash, '2025-10-01 09:00:00', TRUE),
(2001, 2, 'Priya', 'Sharma', 'priya.sharma@indianbank.com', '9870000001', @default_password_hash, '2025-10-05 10:15:00', TRUE),
(2002, 2, 'Anil', 'Gupta', 'anil.gupta@indianbank.com', '9870000002', @default_password_hash, '2025-10-15 11:30:00', TRUE),
(3001, 3, 'Deepak', 'Singh', 'deepak.singh@example.com', '9870000011', @default_password_hash, '2025-10-01 12:00:00', TRUE),
(3002, 3, 'Neha',  'Patel',  'neha.patel@example.com',       '9870000012', @default_password_hash, '2025-10-10 15:30:00', TRUE),
(3003, 3, 'Sanjay','Reddy',  'sanjay.reddy@example.com',     '9870000013', @default_password_hash, '2025-11-01 09:00:00', TRUE),
(3004, 3, 'Kiran', 'Jha', 'kiran.jha@example.com', '9870000014', @default_password_hash, '2025-11-12 14:00:00', TRUE);

-- Ensure emails/IDs not duplicated — use INSERT ... ON DUPLICATE KEY behavior intentionally avoided to keep seed predictable.

-- 3.4 Accounts
INSERT INTO accounts (user_id, account_number, type_id, current_balance, opened_date) VALUES
(3001, '1000100011', 1, 45500.50, '2025-10-02'),
(3001, '1000100012', 2, 12000.00, '2025-10-02'),
(3002, '1000100021', 1, 89000.75, '2025-10-10'),
(3003, '1000100031', 2, 2500.00,  '2025-11-01');

-- 3.5 Capture account_ids into variables for later inserts
SELECT account_id INTO @acc_deepak_savings  FROM accounts WHERE account_number = '1000100011' LIMIT 1;
SELECT account_id INTO @acc_deepak_checking FROM accounts WHERE account_number = '1000100012' LIMIT 1;
SELECT account_id INTO @acc_neha_savings    FROM accounts WHERE account_number = '1000100021' LIMIT 1;
SELECT account_id INTO @acc_sanjay_checking FROM accounts WHERE account_number = '1000100031' LIMIT 1;

-- 3.6 Transactions
INSERT INTO transactions (account_id, transaction_type, amount, description, transaction_date, recipient_account_number, status) VALUES
(@acc_deepak_savings,  'Deposit',       50000.00, 'Initial Salary Deposit',          '2025-10-05 10:00:00', NULL, 'Completed'),
(@acc_deepak_savings,  'Withdrawal',     4000.00, 'ATM Withdrawal - Weekend',        '2025-10-07 15:30:00', NULL, 'Completed'),
(@acc_deepak_savings,  'Transfer-Debit', 1500.00, 'Transfer to Checking (Internal)', '2025-10-12 11:00:00', '1000100012', 'Completed'),
(@acc_deepak_savings,  'Fee',              7.50, 'Transfer Fee (Internal)',         '2025-10-12 11:00:00', NULL, 'Completed'),
(@acc_deepak_savings,  'Deposit',        1000.00, 'Refund from Vendor',              '2025-11-03 09:30:00', NULL, 'Completed'),

(@acc_deepak_checking, 'Transfer-Credit',1500.00, 'Transfer received from Savings',  '2025-10-12 11:00:00', '1000100011', 'Completed'),
(@acc_deepak_checking, 'Withdrawal',      500.00, 'Online Bill Payment',             '2025-10-20 18:00:00', NULL, 'Completed'),

(@acc_neha_savings,    'Deposit',       10000.00, 'Monthly Savings Contribution',     '2025-10-15 14:00:00', NULL, 'Completed'),
(@acc_neha_savings,    'Transfer-Debit',5000.00, 'External Transfer for Investment', '2025-10-25 10:30:00', NULL, 'Completed'),
(@acc_neha_savings,    'Fee',              25.00, 'Transfer Fee (External)',         '2025-10-25 10:30:00', NULL, 'Completed'),
(@acc_neha_savings,    'Deposit',           50.00, 'Interest Credit',                 '2025-11-01 00:00:00', NULL, 'Completed'),

(@acc_sanjay_checking, 'Deposit',        3000.00, 'Initial Deposit',                 '2025-11-01 09:00:00', NULL, 'Completed'),
(@acc_sanjay_checking, 'Withdrawal',       500.00, 'Groceries Payment',               '2025-11-02 12:00:00', NULL, 'Completed');

-- 3.7 Loans
INSERT INTO loans (user_id, loan_account_number, amount_requested, term_months, interest_rate, monthly_payment, status, application_date, approval_date) VALUES
(3001, '7000100011', 50000.00, 60, 10.00, 966.67, 'Active', '2025-10-01 16:00:00', '2025-10-07'),
(3002, 'PENDING-20251110', 100000.00, 120, 9.50, NULL, 'Pending', '2025-11-10 11:00:00', NULL);

-- 3.8 Ensure AUTO_INCREMENT counters are ahead of seeded IDs
ALTER TABLE users AUTO_INCREMENT = 4000;
ALTER TABLE accounts AUTO_INCREMENT = 5000;
ALTER TABLE transactions AUTO_INCREMENT = 6000;
ALTER TABLE loans AUTO_INCREMENT = 7000;

-- =======================================================
-- 4. FINAL CHECK / STATUS ROW
-- =======================================================
SELECT 'Import complete. Verify data in your selected database. Admin user kept: Suraj Sahu (user_id=1000).' AS Status;
