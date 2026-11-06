# Mini Accounting System (PHP + MySQL)

## Setup (XAMPP / Laragon / WAMP)
1. Copy the `mini-accounting` folder into `htdocs` (XAMPP) or `www` (Laragon).
2. Start Apache and MySQL.
3. Open phpMyAdmin → Import → choose `database.sql` → Go.
4. If your MySQL has a password, edit `config.php`.
5. Open http://localhost/mini-accounting/ and sign in.

| Username | Password | Role |
|---|---|---|
| treasurer | treasurer123 | full access (incl. Settings) |
| officer | officer123 | record members, payments, expenses |
| adviser | adviser123 | view and print only |

Settings → "Load sample scenario" fills the handout's test case. Expected cash balance: ₱2,850.00.

## Upgrading an existing database
If you imported an earlier `database.sql`, run this once in phpMyAdmin (SQL tab, database `mini_accounting`):
`ALTER TABLE transactions ADD voided TINYINT(1) NOT NULL DEFAULT 0, ADD void_reason VARCHAR(200) DEFAULT '' AFTER voided;`

## Upgrading for member accounts
If your database already exists, run this once (SQL tab, database `mini_accounting`):
`ALTER TABLE users MODIFY role ENUM('treasurer','officer','adviser','member') NOT NULL, ADD member_id INT NULL, ADD INDEX (member_id);`

## Upgrading for student ID
Run once (SQL tab, database `mini_accounting`):
`ALTER TABLE members ADD student_id VARCHAR(30) NULL AFTER member_code, ADD UNIQUE (student_id);`

## Files
- `database.sql` – tables (users, members, transactions, audit_log, counters, settings)
- `includes/bootstrap.php` – database connection, login/roles, CSRF, and ALL accounting formulas
- `index.php` dashboard · `members.php` · `payments.php` · `expenses.php` · `ledger.php` · `audit.php` · `report.php` · `settings.php`

## Expanding later
- New transaction type: add it to the `type` ENUM in `database.sql`, add a case in `CASH_SQL` and `effect()` (ledger.php).
- New page: copy any page, then add it to the `$nav` list in `head()`.
- Ideas: user management, date-range reports, online payment.
- See `USER_MANUAL.md` for the user guide.
