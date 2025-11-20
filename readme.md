# SecureBank — Project README

This README follows the ordered sections requested and documents the project's purpose, how to run it locally, configuration, features, and contacts.

-- CSV mapping (Order, Heading, Purpose)

1, "Project Title, Tagline, & Badges", "Immediate project identification, status, and stability"
2, "Short Description", "A concise, one- or two-sentence summary of the project's purpose."
3, "🚀 Getting Started", "The absolute minimum steps required to run the project."
4, "⚙️ Prerequisites", "List of required software (Node, Python, PHP, etc.)."
5, "Installation", "Step-by-step instructions (e.g., cloning, installing dependencies)."
6, "💡 Features / Functionality", "Detailed breakdown of what the project can do."
7, "Usage / Examples", "Code snippets or command-line instructions demonstrating key features."
8, "Configuration", "How to customize settings (e.g., environment variables, config files)."
9, "🛣️ Roadmap", "Future plans, features currently under development."
10, "🤝 Contributing", "How others can help (brings users to the CONTRIBUTING.md)."
11, "🛡️ License", "Statement of the project's legal terms (LICENSE file)."
12, "Credits / Acknowledgements", "Thanking major contributors or referencing third-party sources."
13, "❓ Contact", "Where to find help or report issues."

---

## 1) Project Title, Tagline, & Badges

**SecureBank** — Lightweight Internet Banking Prototype

Tagline: "A simple, role-based PHP/MySQL internet-banking prototype for learning and internal demos."

Status: `Prototype` • Stability: `Development`

Badges:

- Build / Status: (local) no CI configured
- License: MIT (see `LICENSE`)

Repository root: `c:\Xampp\htdocs\securebank` (local dev path in this workspace)

Folder snapshot:

```
./
├─ admin/                 # Admin pages and reports
├─ staff/                 # Staff pages (client onboarding, processing)
├─ client/                # Client-facing pages
├─ assets/                # CSS, JS, images
├─ config/                # DB config, settings
├─ database/              # SQL schema and seeds
├─ includes/              # Shared headers, footers, functions, sessions
├─ tools/                 # Maintenance scripts
├─ login.php
├─ signup.php
├─ forgot_password.php
├─ reset_password.php
└─ readme.md
```

---

## 2) Short Description

SecureBank is a PHP + MySQL internet banking prototype demonstrating role-based pages (Admin, Staff, Client), authentication, account management, and basic transaction flows. It is intended for local development, demos, and learning rather than production use.

---

## 3) 🚀 Getting Started (minimum)

Quickest steps to run locally (assumes Windows + XAMPP):

1. Ensure Apache + MySQL are running (via XAMPP Control Panel).
2. Place the project in your webroot (e.g., `C:\xampp\htdocs\securebank`).
3. Create a MySQL database (e.g., `securebank`) and import `database/securebank.sql` (and optionally `generatedseeds.sql`).
4. Update DB credentials in `config/db_config.php`.
5. Open `http://localhost/securebank/` in your browser.

That's the absolute minimum — see Installation for more detail.

---

## 4) ⚙️ Prerequisites

- PHP 7.4+ (or PHP 8.x) with mysqli enabled
- MySQL / MariaDB
- XAMPP / WAMP (recommended for Windows local dev)
- A modern browser (Chrome, Firefox, Edge)

Optional but helpful:

- Composer (if you add third-party PHP packages later)
- A local SMTP tester (MailHog, Mailtrap) to receive password-reset emails

---

## 5) Installation

Steps to set up locally (PowerShell shown for Windows):

```powershell
# Clone (if remote repo)
git clone https://github.com/<owner>/internet-banking.git
cd internet-banking

# Move to XAMPP htdocs, or clone directly into it
# Copy files to C:\xampp\htdocs\securebank
```

Database import (phpMyAdmin or CLI):

```powershell
# CLI example
mysql -u root -p securebank < "C:\path\to\securebank\database\securebank.sql"
mysql -u root -p securebank < "C:\path\to\securebank\database\generatedseeds.sql"
```

Edit `config/db_config.php` and set your DB host, user, password, and database name.

Start Apache and MySQL from XAMPP and open `http://localhost/securebank/`.

Test accounts (development only):

- Admin email: `admin@securebank.com` — password: `securebank`
- Default (clients & staff) password for seeded users: `password`

> IMPORTANT: Change or delete these test credentials in any shared/staging environment.

---

## 6) 💡 Features / Functionality

- Role-based pages and navigation for Admin, Staff and Client
- User registration (`signup.php`), login/logout, and sessions
- Forgot-password flow:
  - `forgot_password.php` generates a secure token and stores it in `password_resets` and (in local/dev) logs a reset link.
  - `reset_password.php` validates the token, enforces password rules, and updates the `users` table.
- Admin pages for user/account management (under `admin/`)
- Staff pages for onboarding and transaction processing (under `staff/`)
- Client portal for account summaries and transfers (under `client/`)
- Utilities in `tools/` for database fixes and password migrations

---

## 7) Usage / Examples

Login via `login.php` using seeded accounts or newly created users.

Example: Register a new client

1. Open `signup.php` and submit registration details.
2. Then log in via `login.php` with your email and password.

Password reset test (local):

1. Open `forgot_password.php`, enter an existing user's email.
2. Check your PHP error log or local SMTP tester for the reset link.
3. Open the link (`reset_password.php?token=...`) and set a new password.

Programmatic sample — DB connection via `config/db_config.php` (pseudo):

```php
<?php
require_once __DIR__ . '/config/db_config.php';
$conn = connectDB();
// Use prepared statements for queries
?>
```

---

## 8) Configuration

Key configuration files:

- `config/db_config.php` — database connection function `connectDB()`.
- `config/settings.php` — general settings (if present).

For local development you can:

- Edit `config/db_config.php` with DB credentials.
- Add a local `.env` or move credentials out of the codebase when moving to staging/production.

Security tips:

- In production, use environment variables (Apache vhost or system env) instead of hard-coded credentials.
- Use HTTPS and secure/HTTP-only cookies.
- Disable verbose error display in `php.ini` and log errors to files.

---

## 9) 🛣️ Roadmap

Planned improvements and ideas:

- Add automated tests (PHPUnit) and basic CI
- Replace homegrown auth helpers with a well-tested library
- Add granular audit logging for transactions
- Add rate limiting and CAPTCHA to `forgot_password.php` to prevent abuse
- Create a public component library (CSS/JS) and a Figma file

---

## 10) 🤝 Contributing

Contributions are welcome. Suggested workflow:

1. Fork the repository.
2. Create a feature branch: `git checkout -b feat/your-feature`.
3. Add tests or manual verification steps.
4. Open a pull request describing your change.

Please follow these guidelines:

- Keep security in mind (do not check in secrets).
- Use prepared statements for DB access and validate inputs.

Consider adding a `CONTRIBUTING.md` for detailed contribution rules.

---

## 11) 🛡️ License

This project uses the MIT License. See `LICENSE` in the repository root for full details.

---

## 12) Credits / Acknowledgements

- Project scaffold and initial pages by the SecureBank Development Team (internal prototype).
- Third-party references: PHP, MySQL, XAMPP.

---

## 13) ❓ Contact

Problems, questions, or security issues:

- Open an issue in this repository.
- Contact the project maintainer or team lead (check your team docs).

---

End of README — last updated: 2025-11-21
