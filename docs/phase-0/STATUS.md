# Phase 0 status

Started: 2026-08-12

## Established

- Laravel 12 / PHP 8.2 application foundation.
- React, TypeScript, and Vite shared frontend workspace.
- Office, Sales, Driver, and Client same-origin routes.
- Fresh-blue Office token system with light/dark and compact/comfortable modes.
- Mobile-first Sales, Driver, and Client navigation shells.
- English/Myanmar localization catalog and catalog-parity test.
- `/api/v1` routing, health contract, correlation IDs, and baseline security headers.
- Request-scoped organization context and cross-phase feature flags.
- PHP feature/unit tests and frontend type/test/build scripts.

## Phase 0 work still open

- Approve and record every transaction-shaping business decision.
- Profile source data and prepare import templates.
- Implement phone/OTP, Google linking, MFA, trusted-device, and session foundations.
- Implement dynamic RBAC, data scopes, approval limits, and maker-checker flows.
- Add OpenAPI generation, generated TypeScript client, idempotency infrastructure, and transactional outbox.
- Configure development/staging infrastructure for MySQL 8, Redis, queues, object storage, monitoring, backup, and restore.
- Select OTP, map/routing, push, storage, monitoring, and hosting providers.
- Complete threat model, migration strategy, test strategy, operational runbooks, and prioritized Phase 1 backlog.
- Perform the full UI verification matrix in both locales and modes.

## Local environment observations

- XAMPP provides PHP 8.2.12 and MariaDB 10.4.32; the specification calls for MySQL. MySQL 8 compatibility and the development database topology remain to be approved.
- The XAMPP MySQL client exists at `D:\xampp\mysql\bin\mysql.exe` but is not on `PATH`.
- The local PHP build does not currently load the Redis extension; Redis-backed cache and queues remain an environment setup task.
- Local HTTPS package access is inspected by AVG. A temporary project-local CA/Composer bootstrap was used under ignored `.tools`; no TLS verification was disabled.
