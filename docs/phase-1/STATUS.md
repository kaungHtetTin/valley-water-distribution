# Phase 1 status

Started: 2026-08-12  
Completed: 2026-08-12  
Release: Internal development master-data release

Phase 1 is complete. The project now provides the controlled reference data and governance contracts required by Phase 2 Customer/Sales and Phase 3 Warehouse work without hard-coded business masters.

## Completed scope

- Company identity, Branch, Business Calendar, Fiscal Period, and Document Sequence configuration.
- Areas, effective-dated Ways/Territories, ordered Route Templates, Warehouses, Zones, Bins, staging/quarantine types, replenishment policies, and physical Cash Locations.
- Product Categories, Brands, Products, localized SKUs, barcode/size/lot/expiry policy, Units of Measure, immutable packaging-conversion revisions, and guarded archive dependencies.
- Price Types, Price Books, effective Price Entries, Customer/classification/Way price assignments, deterministic price precedence resolution, approval status, and monetary approval limits.
- Configurable weighted-average inventory valuation policy plus Warehouse/SKU Product Cost History with overlap prevention and Finance approval.
- One controlled Foundation Masters registry covering Customers, Suppliers, Employees, Departments, Positions, Cost Centers, Drivers, Sales Profiles, Vehicles, Banks, GL Accounts, and configurable expense, damage, return, FOC, failure, maintenance, earning, deduction, incentive, and allowance types.
- Dynamic users, Roles, Permissions, organization/branch data scopes, approval permissions, and approval thresholds. When authentication is enabled, every master-data request is denied unless the required view/manage/import/export/approval/access permission is present.
- CSV preview, row validation, duplicate reporting, atomic commit/rollback, repeatable import templates, and organization-scoped controlled CSV export for Foundation Masters.
- Append-only organization-scoped audit history for create, update, archive, import, assignment, revocation, price approval, and cost approval actions.
- Optimistic locking, effective-date overlap protection, dependency-aware archival, search/status filters, pagination, bilingual English/Myanmar display, dark/light themes, and compact/comfortable density.
- Bilingual LaLaPick-style Office workspaces for Areas, Ways, Route Templates, locations, Company Controls, Storage/Cash, Catalog/Pricing, Catalog Setup, Foundation Masters, and Access/Pricing Governance.
- Representative configurable Valley Water, Taunggyi, Aye Thar Yar, Nam San, Warehouse, Brand, UOM, SKU, Price Type/Book, and master-data role seeds. Unapproved Pack/Carton factors, commercial prices, Ways, Customers, and accounting values are intentionally not invented.

## Accepted phase boundaries

- Fiscal close/reopen, row-locked transactional document-number allocation, inventory balances, cash balances, route stops on live Trips, monthly Sales ownership, and Customer operational history are transaction workflows owned by later phases; Phase 1 supplies their masters and configuration.
- Map/geofence editors, forecasting, capacity projections, product media processing, and advanced merge tooling are enhancements, not Phase 1 reference-data blockers.
- Actual Pack/Carton factors, prices, Customer lists, opening costs, and approved Ways remain deployment business data. The system and validated templates are ready to load them without code changes.
- Authentication enforcement remains feature-gated for local development. When enabled, the master-data middleware requires authenticated, organization-matched permission assignments; it was verified in denial and approval-threshold tests.

## Verification

- `php artisan test`: 63 tests, 488 assertions.
- `npm test -- --run`: 2 tests.
- `npm run typecheck`: passed.
- `npm run build`: passed with vendor chunk separation and no size warning.
- `vendor/bin/pint --test`: passed.
- `composer validate --no-check-publish`: passed; the installed Composer runtime emits upstream deprecation notices.
- Local migrations through `2026_08_12_001100`, repeatable seed, and live HTTP checks for the Foundation, Governance, Catalog, pricing-control, access-control, audit-history, and existing Phase 1 routes: passed.
- Browser-rendered viewport automation is an accepted environment exception: the in-app browser connection rejected its sandbox metadata before opening a tab. Source-level accessibility, responsive CSS, production rendering compilation, and live HTTP delivery passed, but this exception should be rerun when the browser bridge is available.

The detailed exit evidence is recorded in [EXIT-GATE.md](EXIT-GATE.md).
