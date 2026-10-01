# Valley Water Distribution

Laravel and React operations system for water distribution. It includes the Office, Client, Sales, Sales Supervisor, and Driver applications, with shared authentication, English/Myanmar locale support, light/dark themes, and role based authorization.

## Requirements

- PHP 8.2 or later with the extensions required by Laravel and MySQL
- Composer 2
- MySQL 8 or MariaDB
- Node.js 22 and npm
- A web server whose document root is the `public` directory

## Local setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm ci
npm run dev
```

Normal `db:seed` creates system roles, permissions, configuration, and the initial owner. It does not insert demo transactions. A disposable local database can be populated with a complete linked month using:

```bash
php artisan valley:demo-reset --force
php artisan valley:demo-seed --month=2026-09
```

Both demo commands refuse to run in production.

## Quality gates

```bash
composer validate --strict
composer audit --locked
composer check:encoding
php vendor/bin/pint --test
php artisan test
npm ci
npm audit --audit-level=high
npm run build
```

## Production

Start from [`.env.production.example`](.env.production.example), provide real secrets and shared storage paths, then require this command to pass before migration or traffic switch:

```bash
php artisan valley:production-check
```

Use the [production deployment runbook](docs/production-deployment-runbook.md) for first deployment, backup verification, scheduled jobs, smoke testing, and rollback. The current release assessment is in the [production readiness audit](docs/production-readiness-audit.md).
