# **SecureBank — Internet Banking Prototype**

A lightweight PHP/MySQL internet-banking prototype featuring authentication, role-based dashboards (Admin, Staff, Client), account management, transactions, and a unified modern design system.
Built for **learning, demos, and academic projects** — not production deployment.

---

## **📁 Project Structure**

The project follows a clear modular layout.
Below is a condensed and readable tree — based on your actual file paths:

```
SecureBank/
├── admin/                    # Admin dashboard, user mgmt, accounts, reports
│   ├── add_user.php
│   ├── client_accounts.php
│   ├── dashboard.php
│   ├── financial_reports.php
│   ├── manage_account_types.php
│   ├── manage_clients.php
│   ├── manage_loans.php
│   ├── manage_users.php
│   ├── process_transaction.php
│   ├── system_settings.php
│   ├── transactions_engine.php
│   └── user_details.php
│
├── staff/                    # Staff portal — operations & processing
│   ├── add_client.php
│   ├── balance_enquiry.php
│   ├── client_details.php
│   ├── dashboard.php
│   ├── financial_reporting.php
│   ├── manage_clients.php
│   ├── manage_loans.php
│   ├── open_account.php
│   ├── pending_accounts.php
│   ├── process_transaction.php
│   └── view_transactions.php
│
├── client/                   # Client-facing portal
│   ├── accounts.php
│   ├── change_password.php
│   ├── dashboard.php
│   ├── deposit_funds.php
│   ├── loan_application.php
│   ├── profile.php
│   ├── transaction_history.php
│   ├── transfer_funds.php
│   └── withdrawal_funds.php
│
├── assets/
│   ├── css/
│   ├── img/
│   └── js/
│
├── config/                   # DB config & app settings
│   ├── db_config.php
│   └── settings.php
│
├── database/                 # SQL schema & seed data
│   ├── securebank.sql
│   └── generatedseeds.sql
│
├── includes/                 # Shared templates & utilities
│   ├── header.php
│   ├── footer.php
│   ├── functions.php
│   ├── sessions.php
│   ├── contact.php
│   ├── privacy.php
│   └── terms.php
│
├── overview.md               # Design system document (local) :contentReference[oaicite:1]{index=1}
├── login.php
├── signup.php
├── forgot_password.php
├── reset_password.php
├── logout.php
└── index.php
```

---

## **🔍 Short Description**

SecureBank showcases a complete role-based banking workflow implemented in PHP and MySQL.
Core features include:

* User authentication & password reset flow
* Admin dashboard for managing users, loans, and transactions
* Staff portal for onboarding, processing, and account operations
* Client dashboard for balances, transfers, deposits, and withdrawals
* Clean, modern UI based on a consistent design system

---

## **🚀 Getting Started (Essential Minimum)**

1. Install **XAMPP** (Apache + MySQL).
2. Place the project in:

```
C:\xampp\htdocs\securebank
```

3. Create a database named:

```
securebank
```

4. Import:

```
database/securebank.sql
database/generatedseeds.sql
```

5. Update DB credentials in:

```
config/db_config.php
```

6. Start Apache + MySQL → visit:

```
http://localhost/securebank/
```

---

## **⚙️ Prerequisites**

* PHP 7.4+ / PHP 8.x
* MySQL or MariaDB
* Apache (bundled with XAMPP/WAMP)
* Browser: Chrome / Firefox / Edge

Optional:

* Composer
* MailHog / Mailtrap for password-reset email capture
* VS Code + PHP extensions

---

## **🖥️ Modern Design System (Summary)**

Full documentation lives in `overview.md` .

Key principles:

* **8px spacing grid** for consistent layout
* **System UI font stack** for performance
* **Full semantic color palette** (Primary Blue, Success Green, Danger Red, etc.)
* **Clear typography hierarchy** (12px → 36px)
* **Responsive breakpoints** for tablet/mobile
* **Components:**

  * Buttons (Primary, Secondary, Tertiary, Logout, View)
  * Form system (labels, inputs, messages, validation)
  * Widgets, KPI Cards, Tables, Status Badges
  * Header / Footer / Navigation

The entire UI is intentionally clean, flat, and enterprise-friendly.

---

## **💡 Features**

### **Authentication**

* Login / Signup
* Secure password hashing
* Forgot password + email token flow
* Session-based access control

### **Admin**

* Manage users, accounts, loans, transactions
* System settings
* Financial reports
* Dashboard KPIs

### **Staff**

* Client onboarding
* Account opening
* Loan management
* Transaction processing
* Activity reports

### **Client**

* View account balances
* Transfer funds
* Deposit / withdraw
* Loan applications
* Transaction history

---

## **📦 Installation (Full)**

```bash
# If cloning from GitHub
git clone https://github.com/surajsahumrj/internetbanking.git
cd securebank
```

Import SQL schema:

```bash
mysql -u root -p securebank < database/securebank.sql
mysql -u root -p securebank < database/generatedseeds.sql
```

Edit DB credentials:

```
config/db_config.php
```

Start XAMPP → Apache + MySQL → visit:

```
http://localhost/securebank/
```

---

## **📘 Usage Examples**

### Test Admin Login

```
admin@securebank.com
Password: securebank
```

### Test Client/Staff Accounts

```
Password: password
```

### Password Reset

1. Open `forgot_password.php`
2. Enter registered email
3. Grab reset link from mail logs
4. Reset password via token

---

## **⚙️ Configuration Overview**

Main config files:

| File                    | Purpose                      |
| ----------------------- | ---------------------------- |
| `config/db_config.php`  | Database connection settings |
| `config/settings.php`   | App-level settings           |
| `includes/sessions.php` | Session handler              |
| `.env` (optional)       | Recommended for credentials  |

Security guidelines:

* Never deploy with test credentials
* Use HTTPS in real environments
* Disable display_errors in production

---

## **🛣️ Roadmap**

* Dark-mode support
* Modal + dropdown component library
* Full Figma design kit
* API layer for mobile apps
* Audit logging & security hardening
* PHPUnit tests + CI workflows

---

## **🤝 Contributing Guidelines**

1. Fork → create feature branch
2. Commit with clear messages
3. Open PR with description
4. Follow coding style + security rules

---

## **🛡️ License**

This project uses the **MIT License**.
See the `LICENSE` file for details.
