# Valley Production Deployment Runbook

## Release gate

Create a release candidate only when all of these checks pass:

- `composer audit --locked` reports no advisories.
- `npm audit --audit-level=high` reports no high or critical vulnerabilities.
- `php artisan test` passes the complete isolated SQLite suite.
- `npm run build` completes without warnings or oversized entry chunks.
- `php artisan valley:backup` creates a current backup.
- `php artisan valley:backup-verify <backup-path>` restores that backup into an isolated temporary database and removes it after verification.
- `php artisan valley:production-check` passes with the production environment loaded.
- Office, Client, Sales, Driver, attendance QR, GPS, delivery closeout, cash handover, and offline recovery pass business UAT.
- The business owner accepts all remaining medium and low UAT findings. No critical or high finding may remain open.

## First deployment

1. Provision PHP 8.2 or later, MySQL 8 or MariaDB, Node 22 for asset compilation, HTTPS, and a non-root deployment account.
2. Create a dedicated MySQL database and a least privilege application user. Grant only the privileges needed by the Valley schema.
3. Copy the application to a new release directory. Keep `.env`, user uploads, logs, and backups outside the versioned release directory.
4. Create `.env` from `.env.example` and set:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - the public HTTPS `APP_URL`
   - a unique `APP_KEY`
   - `APP_TIMEZONE=Asia/Yangon`
   - the dedicated database credentials
   - `SESSION_SECURE_COOKIE=true`
   - `VALLEY_DEMO_ENDPOINTS=false`
   - an absolute protected `VALLEY_BACKUP_DIRECTORY`, preferably copied off host
   - the real SMTP settings and sender address
5. Run `composer install --no-dev --classmap-authoritative --no-interaction`.
6. Run `npm ci` and `npm run build` in the release directory.
7. Run `php artisan valley:production-check`.
8. Put the current application into maintenance mode: `php artisan down --retry=30`.
9. Run `php artisan valley:backup` and verify it with `php artisan valley:backup-verify <backup-path>`.
10. Run `php artisan migrate --force`.
11. Run `php artisan storage:link` if the shared storage link is not present.
12. Run `php artisan optimize`.
13. Point the web server document root to the release `public` directory and reload PHP/web services.
14. Run `php artisan up`.
15. Verify login and one read-only screen for each app. Then verify Office reports, a test attendance scan, and a controlled order-to-cash transaction.

## Required scheduled jobs

- Run `php artisan schedule:run` every minute under the deployment account.
- The scheduler creates the database backup daily at 02:00.
- Copy backups to an encrypted off-host location and monitor the copy job.
- Run `php artisan valley:backup-verify` against the newest copied backup on a regular restore-drill schedule.

## Upgrade deployment

1. Confirm the CI quality gate is green for the release revision.
2. Deploy into a new release directory and install dependencies/assets there.
3. Run `php artisan valley:production-check` before switching traffic.
4. Enter maintenance mode, create and verify a backup, and run migrations with `--force`.
5. Run `php artisan optimize`, switch the current-release link, reload services, and exit maintenance mode.
6. Complete the smoke checks and monitor the application and web server error logs.

## Rollback

1. Put the application into maintenance mode.
2. Switch the web server/current-release link back to the previous application release.
3. Restore the pre-deployment database backup only when the migration cannot run safely with the previous code. Confirm this decision with the release owner because it discards transactions created after the backup.
4. Clear and rebuild caches for the restored release, reload services, and run the smoke checks.
5. Record the incident and preserve the failed release logs and database backup for investigation.

## External field sign-off

The repository can automate code, API, desktop, and emulated mobile checks. A person must still execute these on the actual business Android phone and production network:

- camera permission and attendance QR scan at every warehouse;
- GPS permission, live route updates, and recovery after signal/network loss;
- long Driver trip with many stops, partial return, end-trip stock return, and cash handover;
- Sales order creation with quantity/FOC, order discount, collection, expense, attendance, and KPI views;
- owner reconciliation of sales, stock, cash, expenses, payroll, KPI bonus, and Profit/Loss reports.
