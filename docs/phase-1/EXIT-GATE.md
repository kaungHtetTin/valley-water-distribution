# Phase 1 exit gate

Decision: **Passed for internal development release**  
Date: 2026-08-12

| Exit criterion | Result | Evidence |
| --- | --- | --- |
| Required masters are configurable without code changes | Pass | Dedicated Area/location/catalog/control registers plus the 21-type Foundation Masters registry and bilingual Office UI |
| Product, unit, price, and cost examples resolve correctly | Pass | Effective price overlap, conversion, Customer assignment precedence, pending approval, monetary threshold, and weighted-average cost tests |
| Unauthorized data is absent from API, search, and export | Pass | Authentication-required, permission-denied, organization-locked context, scoped search, and scoped CSV export tests |
| Import totals and duplicates are controlled | Pass | Preview totals, invalid-row reporting, intra-file/database duplicate detection, atomic commit, rollback, audit events, and CSV templates |
| Phase 2/3 reference dependencies exist | Pass | Customer/Supplier/Employee/Driver/Sales/Vehicle/finance/type masters, Ways/Templates, Warehouse topology, SKU/UOM/conversion/price/cost contracts |
| Localization and operational UI conventions are present | Pass with environment exception | English/Myanmar key parity, no empty translations, production build, theme/density/responsive primitives; browser bridge exception documented in STATUS.md |
| Audit and stale-write controls are present | Pass | Append-only history, correlation IDs, archive reasons, optimistic locks, parent/dependency cycles, and effective-date conflict tests |
| Roles, scopes, and approvals are dynamic | Pass | Configurable roles/users/assignments, organization/branch scope, permission middleware, approval permissions, and monetary thresholds |

## Accepted defaults

- Inventory valuation configuration defaults to `weighted_average`, matching the specification’s recommended default; Product Cost History still requires explicit Finance approval before resolution.
- Special prices remain pending until an authorized approver acts. Retail/Wholesale entries follow their configured Price Type approval flag.
- Customer price resolution order is explicit Customer assignment, Way assignment, Customer classification, Branch default, then Organization default; assignment priority and Price Type precedence break ties.
- All supplied representative records remain database data. Values not present in approved source material are left for controlled import rather than invented.

## Release note

This gate closes Phase 1 implementation. Enabling authentication and `master_data` in a shared environment still requires assigning at least one `MASTER-ADMIN` role to an approved user and loading signed business data through the preview/commit process.
