# Phase 2 — Customer and Sales implementation

Status: In progress  
Started: 2026-08-12  
Current slice: Customer/Outlet register and territory membership

Phase 2 is now active behind `FEATURE_CUSTOMER_SALES`. It is not complete and none of the Phase 2 exit criteria are claimed yet.

## Implemented in the opening slice

- Separate organization-scoped `client_accounts`, `client_outlets`, `client_contacts`, and `client_outlet_addresses` records.
- Effective-dated `outlet_way_assignments` with preserved prior segments when a Shop moves to another Way.
- Customer lifecycle states: Prospect, Pending Verification, Active, Suspended, and Closed.
- COD-only safe onboarding foundation. Approved credit cannot be assigned through the Customer editor.
- Assigned price-book and acquiring-Sales-profile references using Phase 1 masters.
- Duplicate-candidate reporting by normalized phone or exact English business name without silently merging records.
- Organization-scoped Customer search by code, localized Shop name, alias, contact phone, lifecycle, and Way.
- Area/Way consistency validation, optimistic locking, required change/closure reasons, and append-only audit events.
- Bilingual Office Customer Register at `/office/customers` with create, edit, filter, and close workflows.
- Customer API contracts under `/api/v1/customer-sales/customers`.

Automated opening-slice coverage: 5 feature tests / 42 assertions.

Verification on 2026-08-12: the full PHP suite passes 68 tests / 530 assertions; Vitest passes 2 tests; TypeScript, the production Vite build, Pint, Composer validation, migrations, and route registration pass. Apache returns HTTP 200 for the Office Customer page, Customer options API, and Customer register API. Interactive rendered-browser verification is pending because the available browser bridge rejected startup due missing environment sandbox metadata.

## Deliberate boundaries

- A newly registered Customer is always `COD_CASH`. Approved-credit profiles, limits, exposure, holds, and approval history will be introduced through a maker-checker workflow.
- One primary Shop/contact/address is managed by the opening UI. The schema and relationships permit additional Shops, contacts, and addresses; dedicated child-record workflows remain pending.
- Customer duplication produces review candidates. Merge approval, source-identifier retention, and first-delivery acquisition rules remain pending.
- Outlet-to-Way history is implemented. Monthly Sales-to-Way draft/review/publish calendars and KPI attribution are the next territory-control slice.
- No Order, invoice, stock, Delivery, receivable, or financial posting is activated by this slice.

## Next implementation sequence

1. Monthly Sales-to-Way assignment calendar with copy-previous-month, overlap locking, publish validation, approval, and history.
2. Additional Outlet/contact/address management, duplicate-review queue, verification, suspension, and merge contracts.
3. Phone/OTP identity, trusted-device sessions, active-Shop selection, and security controls.
4. Three-screen Client ordering plus shared assisted-Order contracts, idempotency, snapshots, and offline replay.
5. Sales visits, leads, targets, credit/discount/FOC/return/collection requests, and performance views.
6. Controlled usability, Myanmar/English, low-bandwidth, security, and Phase 2 exit-gate UAT.

## Acceptance traceability started

| Requirement | Opening evidence | State |
|---|---|---|
| Client master account/Shop/contact/location structure | Dedicated schema, API resource, and Office register | Started |
| Effective-dated Outlet-to-Way membership | Transactional revision preserves superseded and current date segments | Implemented for primary membership |
| Duplicate Customer control | Normalized phone/name candidates returned on create | Started; merge review pending |
| Credit control | COD-only onboarding prevents direct credit activation | Foundation only |
| AC-WAY-01 | Outlet membership segments covered; monthly Sales ownership not yet implemented | Partial |
| Phase 2 exit criteria | Requires Order, Sales, identity, offline, and controlled UAT work above | Not met |
