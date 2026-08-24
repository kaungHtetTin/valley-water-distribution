# Valley Operations Runbook

## Production security baseline

- Set `APP_ENV=production`, `APP_DEBUG=false`, and a unique `APP_KEY`.
- Serve the application over HTTPS and restrict database access to the application host.
- Keep `VALLEY_DEMO_ENDPOINTS=false` so demo account discovery and demo validation routes return 404.
- Use least-privilege MySQL credentials and do not reuse a human administrator account.
- Set `VALLEY_SLOW_QUERY_MS=250` (or an observed production threshold) and review `storage/logs/laravel.log` for slow-query warnings.
- Run `php artisan route:cache`, `php artisan config:cache`, and `php artisan view:cache` after deployment.

## Database backup

`php artisan valley:backup` creates a timestamped compressed SQL backup in `storage/app/backups`. Set `MYSQLDUMP_PATH` if `mysqldump` is not on `PATH`; the XAMPP binary is discovered automatically on Windows. Set `VALLEY_BACKUP_DIRECTORY` to an encrypted off-host or synchronized location in production.

The scheduler runs the backup daily at 02:00 and retains 14 days by default. Configure the server to run Laravel's scheduler every minute:

```text
* * * * * php /path/to/valley/artisan schedule:run
```

On Windows Task Scheduler, run `php artisan schedule:run` every minute from the project directory.

### Restore drill

1. Put the application in maintenance mode: `php artisan down`.
2. Copy the selected `.sql.gz` backup to a protected working directory.
3. Decompress it and restore into a new empty database; never overwrite the only production database during a drill.
4. Set a temporary connection to the restored database and run `php artisan migrate:status`.
5. Sign in and verify company settings, a customer, an invoice, stock balance, and an audit event.
6. Switch back to the production connection and run `php artisan up`.

Test restore commands:

```text
gzip -dc valley-mysql-YYYYMMDD-HHMMSS.sql.gz | mysql -u USER -p RESTORE_DATABASE
php artisan migrate:status
```

Backups are not considered operational until a restore drill succeeds and the result is recorded in the UAT issue tracker.

## Demo/UAT reset

`php artisan valley:demo-reset --force` runs a fresh migration and deterministic seed only in `local` or `testing`. The command refuses to run in production. Use `--dry-run` to verify availability without changing data.

## Audit and incident review

Successful API mutations are recorded in `audit_logs` with actor, app, action, endpoint, entity, response status, duration, IP, and a redacted request payload. Passwords and tokens are never stored. Owners can review the log at `/office/uat`.

For an incident:

1. Preserve the database and application logs.
2. Filter the UAT audit log by date, app, action, or entity.
3. Record the incident as a critical UAT finding with reproducible evidence.
4. Rotate affected credentials and invalidate sessions if necessary.
5. Fix, test, and move the finding through Retest to Closed.

## Launch gate

- All automated tests and the production frontend build pass.
- No open critical or high UAT findings remain.
- A database backup and restore drill has passed.
- Office desktop and mobile role flows pass in English and Myanmar.
- Client, Sales, and Driver flows pass on at least one real Android phone used by the business.
