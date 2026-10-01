# Production Readiness Audit

Audit date: 2026-10-01

Project: Valley Water Distribution

Decision: **The repository is release candidate ready. Production traffic remains blocked until the deployment environment passes the production check, the one open field UAT item is closed, business data is reconciled, and the owner signs off.**

## Verified release gates

| Gate | Result |
| --- | --- |
| Laravel / PHP | Laravel 12 on PHP 8.2 |
| Automated regression | **149 passed, 1,642 assertions** |
| Test isolation | PHPUnit forces SQLite `:memory:` and dedicated owner credentials |
| Composer validation | Passed with strict validation |
| Composer security | No advisories |
| npm security | 0 vulnerabilities at high/critical threshold |
| Source encoding | Passed; malformed Driver Myanmar strings were repaired and CI now checks UTF-8/mojibake |
| PHP formatting | Laravel Pint passed across the repository and is enforced in CI |
| Production build | Passed with Vite 8.3.1; 1,873 modules transformed |
| Main JavaScript entry | 385.64 kB, 123.61 kB gzip |
| Supervisor chunk | 15.17 kB, 4.18 kB gzip |
| Routes | 195 non-vendor route-list lines; route cache generation passed |
| Schema | All migrations are applied, including Sales Supervisor team/mobile migrations |
| Backup | `valley-mysql-20261001-185858.sql.gz` |
| Restore drill | Passed against an isolated temporary database |
| Framework caches | Config, events, routes, and views generated successfully |
| Runtime visual smoke | Office, Client, Sales, Driver, and Supervisor pages passed headless authenticated checks; Driver and Supervisor Myanmar mobile renders passed mojibake detection |
| CI | Dependency validation/audits, encoding check, regression suite, and production build are configured |

## Application scope

| Area | Status | Evidence | Production action |
| --- | --- | --- | --- |
| Authentication and access | Complete | Five app scopes, nine default roles, permission tests, profile/security settings, throttling and headers | Create real users and review assignments |
| Master data | Complete | Customer, supplier, employee, vehicle, warehouse, route, product, pricing and setup workflows | Import and approve production masters |
| Attendance and HR | Complete in application | QR/GPS validation, records, calendar view, employee scope and payroll inputs | Test each physical location and phone |
| Payroll and KPI | Complete | Role targets, monthly reviews, approvals, bonus posting, payroll calculation, CSV export and employee views | Approve launch targets and first payroll |
| Sales Supervisor | Complete | Separate employee type/app, one-supervisor team constraint, team detail and scoped KPI reporting | Assign the live sales team |
| Orders and invoicing | Complete | Office, Client and Sales entry, discounts/FOC, invoices, returns and status workflow | Run controlled order-to-cash UAT |
| Stock and purchasing | Complete | Supplier-linked receipts, movement controls, balances/value, transfers and stock cards | Import and reconcile opening stock |
| Delivery and Driver | Complete in application | Multi-order trips, loading, stop closeout, GPS, returns, cash handover and vouchers | Complete the open Android GPS UAT item |
| Finance | Complete | Collections, receivables, expenses, supplier payable/ledger, cash/bank books and P&L | Import and reconcile opening balances |
| Vehicle operations | Complete | Driver assignment, costs, route history, cost/km and performance | Validate real odometer and expense entry |
| Reports and dashboards | Complete | Consolidated operations, KPI, payroll, finance, stock, delivery and vehicle reporting with filters/exports | Reconcile totals against imported balances |
| Backup, audit and recovery | Complete in repository | Scheduled backup, restore verifier, audit log, UAT workspace and rollback runbook | Configure off-host encrypted copy and monitoring |

## Closed defects from this audit

- Updated Axios through the lock file and cleared the reported high severity advisory.
- Isolated PHPUnit owner credentials from the developer `.env`.
- Updated regression expectations for the new Sales Supervisor app and separated role permissions.
- Added five Supervisor mobile authorization and team-scope tests.
- Replaced the MySQL-only supplier outstanding expression with a portable SQL expression used by tests and MySQL.
- Restricted demo seeders to local/testing and stopped normal production `db:seed` from inserting operational fixtures.
- Moved initial owner settings into cached configuration so seeding works after `config:cache`.
- Added production checks for owner credentials, persistent writable backup/upload directories, SMTP, and sender address.
- Added a production environment template with secure defaults and explicit shared paths.
- Restored 599 Myanmar characters in the Driver delivery source and added an encoding gate to CI.
- Replaced the generic Laravel README with project setup, quality gate, and deployment guidance.

## Current local production-check result

The local XAMPP `.env` intentionally fails twelve deployment-only checks: environment, debug mode, HTTPS URL, disabled demo endpoints, secure cookies, least privilege database user, database password, production logging, real owner email, strong owner password, production SMTP host, and business sender address. The repository provides [`.env.production.example`](../.env.production.example); the production host must pass `php artisan valley:production-check` with zero failures.

## Blocking production requirements

1. Provision the HTTPS host, PHP 8.2+, MySQL/MariaDB, real SMTP, persistent profile-photo storage, and an encrypted off-host backup destination.
2. Create production `.env` from `.env.production.example`, replace every blank/example value, and pass `php artisan valley:production-check`.
3. Import approved master/opening data and reconcile stock, receivables, payables, cash, bank, payroll, and KPI targets.
4. Run the release revision through CI and deploy using [production-deployment-runbook.md](production-deployment-runbook.md).
5. Close `UAT-202608-0001`: **Verify GPS sharing on a business Android phone**.
6. Run real-device attendance QR/GPS, long-route/offline recovery, partial return, stock return, collection/cash handover, Sales, Supervisor, payroll/KPI, and report reconciliation UAT.
7. Obtain owner approval after all critical/high findings are closed and the remaining medium finding is accepted or closed.

No repository defect currently blocks a release candidate. Infrastructure configuration, production data, physical-device UAT, reconciliation, and owner approval are required before live traffic.
