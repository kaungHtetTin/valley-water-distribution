# Valley Water Distribution Platform

Phase 0 engineering foundation and the completed Phase 1 master-data release for the Myanmar B2B bottled-water distribution platform defined in [`SOFTWARE_SPECIFICATION.md`](SOFTWARE_SPECIFICATION.md).

## Applications

The Laravel API serves four React applications from one shared codebase:

- `/office` — desktop-first operations console
- `/sales` — mobile-first field sales workspace
- `/driver` — mobile-first trip and delivery workspace
- `/client` — simple mobile-first reseller ordering workspace
- `/api/v1/health` — versioned platform health contract
- `/api/v1/master-data/areas` — feature-gated, organization-scoped Area register API
- `/api/v1/master-data/ways` — effective-dated commercial territory and service-policy API
- `/api/v1/master-data/branches` — Branch operating-settings register API
- `/api/v1/master-data/warehouses` — Warehouse location and fulfillment-settings register API
- `/api/v1/master-data/locations/options` — active Branch and Area selection data
- `/api/v1/master-data/organization-controls` — Company settings, Business Calendars, Fiscal Periods, and Document Sequence configuration
- `/api/v1/master-data/controlled-locations` — Warehouse Zone/Bin, SKU replenishment, and physical Cash Location configuration
- `/api/v1/master-data/route-templates` — ordered reusable Way coverage for Branch/Warehouse dispatch planning
- `/api/v1/master-data/catalog-setup` — Product Category, Brand, Product, Unit, Price Type, and Price Book administration
- `/api/v1/master-data/foundation-masters/{type}` — controlled Customer, Supplier, people, fleet, finance, reason, and payroll master registry
- `/api/v1/master-data/access-controls` — dynamic roles, permissions, approval limits, users, and branch/data scopes
- `/api/v1/master-data/pricing-controls` — price assignments, effective resolution, and approved weighted-average cost history
- `/api/v1/master-data/audit-history` — organization-scoped append-only master-data history
- `/api/v1/master-data/skus` — dynamic SKU and versioned unit-conversion API
- `/api/v1/master-data/prices` — effective-dated price-entry API

## Local requirements

- PHP 8.2+
- Composer 2
- Node.js 22+
- MySQL 8 and Redis for the target runtime

The local runtime and `.env.example` use the `valley_water` MySQL database. XAMPP's MySQL service is supported for local development; the PHPUnit configuration intentionally uses isolated SQLite so automated tests cannot alter development data.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate --seed
```

Run the development processes:

```bash
composer run dev
```

Run verification:

```bash
composer test
npm test
npm run build
```

## Architecture

- Laravel 12 modular monolith with bounded modules under `app/Domain`
- React 19 + TypeScript + Vite shared frontend packages
- API endpoints versioned under `/api/v1`
- UTC storage/application time with `Asia/Yangon` business-time rendering
- MMK fixed-point money policy
- correlation IDs and baseline security headers on every response
- cross-phase workflows disabled behind feature flags until their acceptance gates pass

Phase status and unresolved work are tracked under [`docs/phase-0`](docs/phase-0) and [`docs/phase-1`](docs/phase-1).
