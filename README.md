# LoanPro – Loan Management System

A loan management system for microfinance institutions and lenders with multiple branches. It covers the whole loan lifecycle: borrower registration, application, approval, disbursement, repayment, arrears follow-up and write-off. Double-entry accounting runs in the background, and the whole interface is available in **English** and **Khmer (ខ្មែរ)**.

Built with Laravel 13, AdminLTE 4 (Bootstrap 5) for the admin panel, and Tailwind CSS 4 for the public and login pages.

## Features

**Lending**
- Borrower profiles: KYC fields, photo, uploaded documents and blacklisting
- Loan products: flat or reducing-balance (EMI) interest; daily, weekly, every-2-weeks or monthly repayments; amount and term limits; processing fee; late penalty with grace days
- Loan workflow: application → approval or rejection → disbursement → repayments → closed or written off
- Repayment schedule created at disbursement, with a printable schedule and receipts
- Payments go to the oldest unpaid installment first: penalty, then interest, then principal
- Late penalties are charged automatically by a scheduled job
- Loan calculator and a live estimate on the application form

**Branches**
- Staff assigned to a branch only see that branch's borrowers, loans, payments and books
- Administrators and head-office staff can switch between branches or view all of them
- The dashboard compares branches side by side

**Accounting**
- Chart of accounts with English and Khmer names
- Automatic journal entries for disbursements, repayments and write-offs
- Expense and income forms, manual journal entries, and reversals
- Account ledger, trial balance, income statement and balance sheet, per branch or for all branches

**Reporting and administration**
- Dashboard with headline figures (outstanding balance, collections, overdue amount, portfolio at risk), a 12-month chart, and overdue and upcoming installments
- Collections, disbursements, arrears/aging and portfolio reports with CSV export
- Roles: Administrator, Manager, Loan Officer and Accountant
- Activity log, company and currency settings, light/dark mode

## Roles

| Role | Can do |
| --- | --- |
| Loan Officer | Register borrowers, submit applications, receive repayments |
| Manager | Everything a Loan Officer can, plus: approve, reject and disburse loans; manage loan products; view reports and accounting |
| Accountant | Everything a Loan Officer can, plus: accounting, financial statements and reports |
| Administrator | Everything above, plus: write off loans; manage branches, users and settings; view the activity log; see all branches |

## Requirements

- PHP 8.3 or newer
- Composer 2
- Node.js 20 or newer and npm
- MySQL 8 (SQLite also works for local testing)

## Installation

```bash
git clone https://github.com/rsokkheng/loan-management-system.git
cd loan-management-system

composer install
npm install
npm run build

cp .env.example .env
php artisan key:generate
```

Create a MySQL database. Then set `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`, and run:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan app:create-admin
```

`migrate --seed` creates only the base data a new installation needs: company settings, the chart of accounts and a "Head Office" branch. It adds no users or sample records, and it's safe to run again. `app:create-admin` asks for a name, email and password and creates the first administrator.

Then serve the app, for example with Laravel Herd or `composer run dev`.

### Demo data (development only)

To try the system with realistic data, load the demo on an **empty** development database:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

This adds 3 branches, staff accounts, loan products, borrowers, a year of loans and repayments, and the matching accounting entries. `DemoSeeder` refuses to run when `APP_ENV=production`.

All demo accounts use the password `password`.

| Email | Role | Branch |
| --- | --- | --- |
| admin@loanpro.test | Administrator | All branches |
| accountant@loanpro.test | Accountant | All branches |
| manager@loanpro.test | Manager | Head Office – Phnom Penh |
| officer@loanpro.test | Loan Officer | Head Office – Phnom Penh |
| sr.manager@loanpro.test | Manager | Siem Reap |
| sr.officer@loanpro.test | Loan Officer | Siem Reap |
| bb.manager@loanpro.test | Manager | Battambang |
| bb.officer@loanpro.test | Loan Officer | Battambang |

## Deploying to production

1. Set these values in the server's `.env`:

    ```dotenv
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://your-domain.com
    DB_CONNECTION=mysql
    DB_DATABASE=...
    DB_USERNAME=...
    DB_PASSWORD=...
    ```

2. Install, build and set up the database:

    ```bash
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    php artisan key:generate        # first deploy only
    php artisan migrate --force --seed
    php artisan storage:link
    php artisan app:create-admin    # first deploy only
    php artisan optimize
    ```

3. Add the scheduler to cron (see [Scheduled jobs](#scheduled-jobs)) so late penalties are charged every night.

4. Sign in as the administrator. Then set the company details and currency under **Settings**, rename or add branches, create staff users, and set up your loan products.

On later deploys, run `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force` and `php artisan optimize`. Never run `migrate:fresh` or `DemoSeeder` on the production server.

## Scheduled jobs

Late penalties are charged by `loans:apply-penalties` every day at 00:30. In production, add the Laravel scheduler to cron:

```cron
* * * * * cd /path/to/loan-management-system && php artisan schedule:run >> /dev/null 2>&1
```

For local development, run `php artisan schedule:work` instead.

## Useful commands

| Command | Purpose |
| --- | --- |
| `php artisan loans:apply-penalties` | Charge late penalties now |
| `php artisan app:create-admin` | Create an administrator account (the password is asked for, not typed on the command line) |
| `php artisan accounting:backfill` | Create journal entries for loans and payments that don't have any yet (for example, data from before accounting was added) |
| `php artisan db:seed --class=DemoSeeder` | Load demo data into an empty development database |
| `php artisan test` | Run the test suite |
| `npm run dev` | Rebuild CSS and JavaScript as you edit |

## Accounting entries

Loan activity is posted to the books automatically:

| Event | Debit | Credit |
| --- | --- | --- |
| Loan disbursed | Loans Receivable | Cash / Bank / Mobile Money (net payout), Processing Fee Income |
| Repayment received | Cash / Bank / Mobile Money | Loans Receivable (principal), Interest Income, Penalty Income |
| Loan written off | Loan Loss Expense | Loans Receivable (unpaid principal) |

Interest is recorded as income when it is paid (cash basis). The default chart of accounts is created automatically the first time it is needed.

## Project structure

| Path | Contents |
| --- | --- |
| `app/Services/LoanCalculator.php` | Repayment schedules for flat and reducing-balance interest |
| `app/Services/LoanService.php` | Loan lifecycle: apply, approve, reject, disburse, write off |
| `app/Services/RepaymentService.php` | Splitting each payment across installments |
| `app/Services/PenaltyService.php` | Late penalties |
| `app/Services/LedgerService.php` | Double-entry journal posting and reversals |
| `app/Services/AccountingReportService.php` | Trial balance, income statement, balance sheet, ledgers |
| `app/Services/BranchContext.php` and `app/Models/Concerns/BelongsToBranch.php` | Limiting data to the user's branch |
| `resources/css/admin.css`, `resources/js/admin.js` | Admin panel styles and scripts (AdminLTE 4 + Bootstrap 5) |
| `resources/css/app.css` | Landing and login page styles (Tailwind CSS 4) |
| `lang/km.json`, `lang/km/` | Khmer translations |

AdminLTE/Bootstrap and Tailwind are built from separate Vite entrypoints and never load on the same page, because their class names clash.

## Translations

Interface text is written in English inside `__('...')` calls, and the Khmer version lives in `lang/km.json`. When you add new text, add its Khmer translation too: `LocalizationTest` fails if any string is missing. Each user's language is saved on their profile and can be changed from the top bar.

## Testing

```bash
php artisan test
```

The tests run on an in-memory SQLite database. They cover schedule calculations, the loan workflow, payment allocation, penalties, branch isolation, accounting entries and statements, translations, and permissions. They also open every page in English and in Khmer.
