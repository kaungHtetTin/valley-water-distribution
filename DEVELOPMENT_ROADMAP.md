# Enterprise Water Distribution Platform

## Phase-by-Phase Development Roadmap

| Item | Planning baseline |
| --- | --- |
| Related specification | `SOFTWARE_SPECIFICATION.md`, Version 1.1 |
| Technology | Laravel API, React + Vite applications, MySQL, Redis/queues, object storage |
| Applications | Office, Sales, Driver, Client |
| Delivery method | Two-week sprints with phase-end business acceptance |
| Product languages | Myanmar and English |
| Initial Customer payment method | Physical cash |
| Sales settlement | COD by default; permission-controlled approved credit |
| Estimated sequential duration | Approximately 50 weeks, excluding procurement and business-approval delays |
| Estimated duration with controlled overlap | Approximately 38–42 weeks for a suitably staffed team |
| First complete operational release | Phase 6 |

The durations are planning estimates, not fixed commitments. They must be recalculated after Phase 0 using confirmed data volumes, team size, devices, providers, migration quality, accounting policy, and acceptance scope.

---

## 1. Delivery principles

1. Build one modular platform, not four disconnected products.
2. Keep business logic in Laravel domain/application services and expose it through versioned APIs.
3. Share React design tokens, localization, API clients, authentication, permissions, forms, status components, and offline primitives across applications.
4. Treat Stock, credit, Delivery, Payment, cash custody, Payroll, and accounting records as auditable transactions.
5. Use feature flags for incomplete cross-phase workflows. A UI must not activate a transaction before its dependent posting engine is ready.
6. Develop dashboards and report facts incrementally, but release reconciled enterprise reports in Phases 7 and 8.
7. Test Myanmar language, low-bandwidth operation, low/mid-range Android devices, and offline recovery throughout development.
8. Do not launch the full Order-to-Cash/Credit process until the Phase 6 atomic Delivery integration passes reconciliation and pilot acceptance.

## 2. Recommended delivery team

| Responsibility | Recommended allocation |
| --- | --- |
| Product owner / business decision maker | 1 client-side owner with authority to approve policies |
| Product manager / business analyst | 1–2 |
| Solution architect / technical lead | 1 |
| Laravel backend engineers | 3 |
| React engineers | 3, covering Office and mobile-first applications |
| QA automation/manual engineers | 2 |
| UI/UX designer | 1 |
| DevOps/SRE | 0.5–1 |
| Finance/accounting adviser | Part-time throughout Phases 0, 3, 4, 5, 7, and 8 |
| Myanmar localization reviewer | Part-time throughout development |

A smaller team can deliver the platform, but the schedule will increase and fewer phases can safely overlap.

## 3. Roadmap summary

| Phase | Indicative duration | Main outcome | Release status |
| --- | ---: | --- | --- |
| Phase 0 — Discovery and Foundations | 4 weeks | Approved architecture, environments, shared application foundation | Engineering-ready |
| Phase 1 — Master Data | 4 weeks | Controlled organization and operational master records | Internal release |
| Phase 2 — Customer and Sales | 6 weeks | Client/Sales onboarding, Ways, pricing, Orders, draft invoices | Controlled UAT |
| Phase 3 — Warehouse | 6 weeks | Auditable Stock receipt, balance, reservation, transfer, pick/load, close | Warehouse UAT |
| Phase 4 — Finance | 8 weeks | AR/AP, credit, cash/bank, custody, expenses, journals, close foundations | Finance sandbox/UAT |
| Phase 5 — HR and Payroll | 6 weeks | Employee, attendance, OT, Payroll, advances, salary history | Payroll pilot |
| Phase 6 — Delivery and Vehicle | 8 weeks | Driver app, GPS, dispatch, atomic Delivery/finance integration, fleet | Operational pilot/go-live gate |
| Phase 7 — Reports | 4 weeks | Reconciled operational, financial, KPI, and scheduled reports | Management rollout |
| Phase 8 — Executive Dashboard | 4 weeks | Owner and department dashboards with drill-down | Executive rollout |

### Critical dependency path

`Phase 0 → Phase 1 → Phase 2/Phase 3 → Phase 4 → Phase 6 → Phase 7 → Phase 8`

Phase 5 can begin when Phase 4's Cash/Bank, chart-of-accounts, advances, and journal contracts are stable. Report fact tables should be designed during Phases 2–6 even though the complete Report Center is delivered in Phase 7.

---

## 4. Phase 0 — Discovery and Platform Foundations

**Indicative duration:** 4 weeks

### Phase 0 objectives

- Remove business-policy uncertainty before transaction development.
- Establish secure, testable, deployable application foundations.
- Confirm the delivery sequence, ownership, acceptance process, and operational constraints.

### Business and solution decisions

- Confirm organization, branches, Warehouses, Areas, Ways, products, packaging, price types, and cost method.
- Confirm COD and approved-credit rules, limits, terms, holds, overrides, collections, refunds, FOC, and returns.
- Confirm invoice timing, receipt numbering, tax treatment, chart of accounts, accounting periods, and Management Net Profit inclusions.
- Confirm factory versus external Supplier treatment and whether PO/GRN/invoice matching is required.
- Confirm attendance, OT, incentive, allowance, advances, Payroll rules, and confidentiality.
- Confirm Driver devices, Android versions, background GPS policy, POD evidence, cash handover, and printer requirements.
- Select map/routing, OTP/SMS, email/push, storage, monitoring, and hosting providers.
- Confirm capacity, retention, backup RPO/RTO, security, privacy, and legal/accounting review requirements.

### Engineering work

- Establish repository structure for Laravel, Office, Sales, Driver, Client, and shared React packages.
- Configure local, CI, development, staging, pre-production, and production environment strategy.
- Create automated build, test, lint, static-analysis, dependency, migration, and deployment pipelines.
- Establish Laravel modular boundaries, API conventions, OpenAPI generation, typed React API client, and error/status contracts.
- Configure MySQL conventions, Redis cache/queues, object storage, scheduled jobs, logging, metrics, tracing, and alerting.
- Implement organization scoping, authentication foundation, phone/OTP provider abstraction, Google identity linking, sessions, trusted devices, and MFA foundation.
- Implement dynamic RBAC, data scopes, maker-checker approvals, audit events, and feature flags.
- Implement Myanmar/English localization foundation, shared fresh-blue tokens, Office shell, and mobile application shells.
- Establish test factories, seed framework, idempotency infrastructure, transactional outbox, document sequences, and file-upload security.

### Deliverables

- Approved solution architecture and domain boundary document.
- Approved policy/decision register.
- Working application shells in both languages.
- CI/CD and environment runbook.
- Initial OpenAPI contract and database conventions.
- Security threat model, permission model, test strategy, and migration strategy.
- Prioritized product backlog linked to SRS acceptance criteria.

### Phase 0 exit criteria

- All decisions that change database or transaction design have an owner and approved answer or an accepted default.
- All four applications authenticate against the development API.
- Organization scope, permissions, localization, audit, feature flags, queues, and observability pass smoke tests.
- A reversible database migration and automated deployment complete successfully in development and staging.
- Product owner and technical lead approve the Phase 1 backlog.

---

## 5. Phase 1 — Master Data

**Indicative duration:** 4 weeks

**Implementation status:** Complete on 2026-08-12 for the internal development release. All required masters are configurable, governed by organization-aware permissions and audit history, and supported by validated import/export, price assignment/resolution, and approval controls. Transactional fiscal close, document allocation, and later operational workflows remain in their owning phases. See `docs/phase-1/STATUS.md` and `docs/phase-1/EXIT-GATE.md`.

### Phase 1 objectives

- Create the controlled reference data required by every later transaction.
- Prevent hard-coded products, prices, places, roles, and expense types.

### Phase 1 scope

- Company, branch, business calendar, numbering sequence, and fiscal-period setup.
- Area, Way/Territory, route template, Warehouse, zone, bin, staging, quarantine, and cash-location masters.
- Brand, product, SKU, localized names, size, unit, packaging conversion, barcode, lot/expiry policy, and status.
- Retail, wholesale, special, and future price types; price books; Customer assignments; effective dates; and approval.
- Product cost history and weighted-average-cost configuration.
- Customer/Outlet classification, Supplier, Employee/person, department, position, cost center, Driver, Sales profile, Vehicle, Bank, and GL account masters.
- Configurable expense, damage, return, FOC, failure, maintenance, earning, deduction, incentive, and allowance types.
- Dynamic roles, permissions, data scopes, and approval thresholds.
- CSV/XLSX import preview, validation, duplicate detection, commit, and error export.

### Office application work

- Permission-aware navigation and reusable CRUD/list/detail/audit patterns.
- Server-side search, filters, sort, pagination, bulk status change, and controlled export.
- Light/dark themes and compact/comfortable density.
- Loading skeletons plus empty, error, stale, offline, permission-denied, and conflict states.

### Data work

- Prepare clean seed/import templates.
- Load representative Areas such as Taunggyi, Aye Thar Yar, and Nam San as data, not source-code constants.
- Load representative Warehouses and Brands as configurable records.
- Define duplicate/merge rules and immutable effective-dated history.

### Phase 1 testing

- CRUD permissions and organization/branch isolation.
- Effective-date overlap and price precedence.
- Unit conversion and decimal precision.
- Import validation, duplicate prevention, audit, and rollback.
- Myanmar/English display, search normalization, and export.

### Phase 1 exit criteria

- Business users can configure all required masters without code changes.
- Product/unit/price/cost examples resolve correctly for representative Customers.
- Role and scope tests prove unauthorized data is absent from UI, API, search, and export.
- Imported master totals and duplicates are signed off.
- Phase 2 and Phase 3 reference-data dependencies are complete.

---

## 6. Phase 2 — Customer and Sales

**Indicative duration:** 6 weeks

**Implementation status:** In progress since 2026-08-12. The first Customer/Outlet register and effective-dated Outlet-to-Way membership slice is implemented with automated coverage; remaining assignment, identity, ordering, approval, offline, and UAT work is tracked in `docs/PHASE_2_IMPLEMENTATION.md`.

### Phase 2 objectives

- Make Client ordering simple enough for Myanmar reseller shops.
- Give Sales representatives controlled mobile tools for Customer acquisition, visits, Orders, and KPI visibility.
- Establish effective-dated Way ownership and credit-request foundations.

### Customer and territory scope

- Customer account, Outlet/Shop, contact, phone, address, landmark, map pin, category, language, status, and price-book assignment.
- Customer history, duplicate detection, merge review, verification, suspension, and audit timeline.
- Area and effective-dated Outlet-to-Way membership.
- Monthly Sales-to-Way assignment, copy-previous-month, validation, publication, mid-month change, and historical attribution.
- Sales targets by representative, Way, value, quantity, and period.

### Authentication and Client application

- Phone number + OTP, trusted-device session, PIN/biometric local unlock, recovery, revocation, and session expiry.
- Google sign-in linked only to a verified phone/Outlet membership.
- Secure short-lived quick-order link that creates only a verified Order or a pending verification request according to risk.
- Active Outlet selection for identities linked to multiple Shops.
- Three-screen repeat ordering: Home, Products, Review.
- Numeric keypad and large `−/+` quantity controls, assigned prices, line totals, saved address, next service date, and COD/approved-credit label.
- Order reference, plain-language status, history, repeat Order, invoice/receipt placeholder, and simple purchase summary.
- Local draft preservation, offline pending state, idempotent submission, and conflict recovery.

### Sales application

- Today, Clients, Order, KPI, and More navigation.
- Assigned Ways/Clients, search, map/list, visit plan, notes, photos, follow-up, leads, and verification status.
- Fast new-Shop registration using phone, Shop name, and deliverable location.
- Assisted Order entry using the same pricing, credit, validation, and idempotency rules as the Client app.
- Credit request, discount/FOC request, return request, and collection-request capture without self-approval.
- Current/historical/future Way assignment visibility.
- Draft Sales KPI views with source drill-down and separate collection metrics.

### Backend and Office work

- Customer, Way, assignment, target, visit, lead, Order, line, revision, and status-event APIs.
- Price-resolution snapshots and Order commercial snapshots.
- Draft/pro forma invoice preview only; no final invoice or Sales/COGS posting before accepted Delivery in Phase 6.
- Credit profile/request and commitment contracts behind feature flags until the Phase 4 engine is accepted.
- Office Customer Register, History, Credit-request queue, Orders, assignment calendar, and Sales performance screens.

### Phase 2 testing

- Three-screen, under-60-second repeat-order usability test.
- OTP abuse/rate-limit, Google linking, device revocation, quick-link isolation, and multi-Outlet access tests.
- Duplicate tap, timeout retry, offline replay, and operation-ID deduplication.
- Price/discount/FOC/credit permission and snapshot tests.
- Monthly Way switch, mid-month attribution, duplicate Customer, and merge tests.
- Myanmar/English and low-bandwidth tests on representative Android devices.

### Phase 2 exit criteria

- Returning Clients can submit an Order in no more than three screens.
- Sales can register a provisional Shop and place an authorized assisted Order.
- Orders remain non-financial operational requests until later phases activate Delivery posting.
- Way history and KPI ownership remain correct after assignment changes.
- Authentication, offline, security, and usability acceptance criteria pass controlled UAT.

---

## 7. Phase 3 — Warehouse

**Indicative duration:** 6 weeks

### Phase 3 objectives

- Establish an immutable, auditable inventory ledger.
- Support finished-goods receipt, reservation, transfer, picking, loading, counting, damage, and closing.

### Inventory foundation

- Typed inventory locations for Warehouse bins, staging, quarantine, damage, transfer custody, and Vehicles.
- Stock states: on hand, available, reserved, staged, loaded, in transfer, quarantine, damaged, expired, and returned pending inspection.
- Immutable double-sided Stock movements, cost layers/weighted average, projected balance, and reconciliation.
- Negative-Stock prohibition and concurrency-safe reservation.

### Warehouse workflows

- Approved cutover/new-location Opening Stock documents.
- Finished-goods GRN/Stock Receive with Supplier/factory source, lots, dates, damage/rejection, evidence, and cost.
- Reservation, allocation request, pick list, FEFO, staging, barcode/manual verification, and load manifest.
- Warehouse-to-Warehouse transfer, in-transit custody, receipt, shortage/overage, damage, and reconciliation.
- Customer return receipt, inspection, restock, quarantine, or scrap.
- Damage, expiry, adjustment, cycle count, full count, recount, and approval.
- Reorder/low-Stock/expiry alerts.
- Derived Closing Stock and signed period snapshot; later opening derives from the prior close.
- Stock Balance, Stock Card, movement, valuation, fast/slow moving, and count-variance views.

### Integration work

- Connect Orders to reservations without treating an Order as a Stock issue.
- Transfer picked/loaded goods into Vehicle custody.
- Implement and test the Delivery-generated Sales Issue/COGS interface behind a feature flag for activation in Phase 6.
- Prepare Supplier/PO/GRN references for Phase 4 AP matching.

### Phase 3 testing

- Concurrent reservation and negative-Stock tests.
- Unit conversion, lot/FEFO, cost valuation, transfer, reversal, and count tests.
- Opening/Closing equation and inventory-GL contract tests.
- Vehicle custody and delivery-above-load rejection tests.
- Large ledger pagination, filtering, and performance tests.

### Phase 3 exit criteria

- Every physical quantity can be traced from source to current location.
- Opening, movement, and Closing quantities and values reconcile.
- Two users cannot allocate the same available Stock.
- Vehicle loading transfers custody without recognizing Sales or COGS.
- Warehouse users approve the end-to-end receive, reserve, pick, load, transfer, return, count, and close scenarios.

---

## 8. Phase 4 — Finance

**Indicative duration:** 8 weeks

### Phase 4 objectives

- Build the financial and settlement foundations required for COD and approved credit.
- Ensure Customer, Supplier, cash, bank, expense, advance, inventory, and journal balances reconcile.

### Receivables and credit

- Customer credit profile, limit history, payment days, holds, temporary limits, overrides, and maker-checker approval.
- Mutually exclusive credit commitments: reserved, dispatched-not-invoiced, consumed, and released.
- Draft/final invoice entities, AR open items, notes, write-offs, statements, aging, collection tasks/promises, and Customer Ledger.
- Customer Payment, AR-open-item allocation, numbered receipt, tender/change detail, and reversal.
- Opening AR and Customer-advance liability migration.

### Cash and treasury

- Collector, cashier, safe, petty-cash, cash-in-transit, and Bank treasury accounts.
- Immutable cash-custody movement linked one-to-one to treasury/journal posting.
- Collection batch, dual handover, count, shortage/overage, variance approval, reconciliation, safe transfer, and bank deposit.
- Cash Book, opening/closing, daily cash close, and cash-location reconciliation.
- Bank Book, account security, deposits, withdrawals, transfers, statement import, matching, reconciliation, and dual approval.

### Suppliers and accounts payable

- Supplier terms, Bank details, purchase Order where enabled, GRN, invoice, matching/tolerance, AP open item, credit note, return, Payment, allocation, aging, and statement.
- Distinguish internal factory clearing from external Supplier AP.
- Supplier advances and opening AP.

### Expenses, advances, and accounting

- Daily Expense with cost-center and optional Warehouse/Way/Trip/Vehicle/department dimensions.
- Expense, employee, Supplier, and Customer advances with separate asset/liability subledgers.
- Configurable chart of accounts and balanced posting rules.
- Fiscal periods, journal review, reversal, reconciliation, close, and privileged reopen.
- Profit formulas and mutually exclusive management-expense buckets.
- COGS and Delivery-originated invoice/AR/COD postings implemented against test fixtures but feature-gated until Phase 6.

### Phase 4 testing

- Credit exposure, commitment reclassification, concurrent limits, holds, and override tests.
- One Payment across invoices and several Payments against one invoice.
- COD, later cash collection, receipt numbering, custody, handover, variance, and reversal tests.
- Cash-in-transit and Bank reconciliation tests.
- PO/GRN/invoice match, duplicate Supplier invoice, AP aging, and Payment tests.
- Advance, write-off, return/credit note/refund, journal balance, and period-close tests.
- Complete subledger-to-control-account reconciliation.

### Phase 4 exit criteria

- AR, AP, cash by location, Bank, advances, and journal controls reconcile in the finance test scenario.
- Credit cannot exceed its approved exposure without audited maker-checker override.
- Cash collection cannot be silently netted against Driver or other expenses.
- Posted documents are immutable and corrected through linked reversal/adjustment.
- Finance approves the accounting mappings, controls, statements, and P&L definitions.

---

## 9. Phase 5 — HR and Payroll

**Indicative duration:** 6 weeks

### Phase 5 objectives

- Add the requested Employee, attendance, OT, incentive, allowance, advance, Payroll, and salary-history capabilities.
- Protect confidential Employee data and connect Payroll to Finance.

### Phase 5 scope

- One shared person/Employee record connected to application access, Sales, and Driver profiles.
- Department, position, cost center, supervisor, contract, work location, and effective-dated salary/assignment history.
- Schedule, shift, roster, attendance event, daily summary, absence classification, manual correction, and approval.
- OT request, approval, result, rate/rule, and Payroll-period assignment.
- Earning/deduction types, allowances, incentives, and frozen approved KPI inputs.
- Employee salary advance and repayment schedule.
- Payroll lifecycle: draft, calculated, reviewed, approved, posted, paid, and closed.
- Payroll register, payslip, payment register/file, salary history, journals, and cost-center reporting.
- Opening Payroll liabilities where required.

### Security and controls

- Confidential field-level scopes for salary, Bank details, attendance, advances, payslips, and exports.
- MFA/step-up and maker-checker approval for Payroll review, posting, Payment, correction, and export.
- Immutable posted Payroll with reversal/recalculation.
- GPS may support evidence but cannot be the sole reason for a deduction or disciplinary decision.

### Phase 5 testing

- Effective-dated salary and assignment tests.
- Attendance/OT calculation, correction, and approval tests.
- KPI incentive snapshot tests.
- Advance disbursement/deduction/balance tests.
- Payroll calculation, journal, Payment, reversal, close, and confidentiality tests.
- Myanmar/English payslip and report review.

### Phase 5 exit criteria

- Approved inputs reproduce the same frozen Payroll result.
- Payroll register, payable, journals, Payments, and advances reconcile.
- Unauthorized users cannot view sensitive Employee data through UI, API, search, report, or export.
- HR and Finance approve one representative Payroll cycle and correction cycle.

---

## 10. Phase 6 — Delivery and Vehicle

**Indicative duration:** 8 weeks

### Phase 6 objectives

- Complete Delivery planning and field execution.
- Activate the atomic Order-to-Delivery-to-Cash/Credit transaction.
- Provide live GPS, route history, Driver KPI, and complete Vehicle cost/performance management.

### Office delivery operations

- Allocation and split Shipment review by Warehouse.
- Delivery Planning, Driver/Vehicle assignment, route template, stop sequence, capacity, availability, and conflict validation.
- Pick/load publication, dispatch, Trip lifecycle, exception queue, reassignment, breakdown, and custody transfer.
- Live map/list with active route, Stops, Driver location, progress, last-seen, stale/offline, battery/connectivity where available, and route-history playback.

### Driver application

- Trip, Stops, Map, and More navigation.
- Permission/GPS health onboarding, pre-Trip checklist, odometer, load verification, and custody acceptance.
- Cached manifest, Client details, navigation, settlement term, cash due/credit authorization, and offline queue.
- Arrival, actual accepted quantity, partial/failure/reschedule, POD, COD tender/change, credit acknowledgement, return, damage, incident, and expense capture.
- Assigned later-collection task using `PostCustomerPayment`.
- End-of-Trip Stop, Stock, cash, receipt, handover, odometer, and expense reconciliation.
- Visible GPS/offline/sync indicators and conflict resolution.

### Atomic Delivery activation

The server `PostDelivery` transaction must commit exactly once:

- accepted Delivery quantity and immutable event timeline;
- inventory Sales/FOC issue and COGS/FOC expense;
- posted invoice and AR where applicable;
- COD Payment/allocation/receipt/custody, approved-credit open balance/exposure consumption, or approved zero-due result;
- balanced journals;
- Sales, Driver, Way, Warehouse, and profitability KPI facts;
- audit record, permanent idempotency result, and transactional outbox.

If any mandatory step fails, the transaction fails without partial posting. Offline data remains pending and visible.

### GPS and fleet

- Hybrid/native Driver package for reliable background GPS, subject to device acceptance.
- Adaptive tracking, offline batches, server-issued session/partition bucket, deduplication, latest-location projection, retention, and archive.
- Vehicle fuel, maintenance, engine oil, tyres, insurance, license, documents, incidents, downtime, service reminders, and odometer.
- Prevent duplicate Vehicle/Daily Expense entry through one Finance source document.
- Route history, availability, utilization, fuel efficiency, cost/km, cost/Stop, cost/bottle, and contribution.
- Driver delivered-bottle KPI using actual accepted base quantity, plus POD, completion, cash, and contextual performance measures.

### Phase 6 testing

- Online and offline `PostDelivery`/`PostCustomerPayment` idempotency and atomic rollback.
- Partial, failed, rejected, rescheduled, return, FOC, cash, credit, and zero-due scenarios.
- Delivery cannot exceed Vehicle custody.
- Receipt, cash variance, handover, and unposted-cash exception tests.
- Screen-locked/background GPS, device restart, power-saving, offline reconnect, stale point, spoof indicator, and burst ingestion tests.
- Performance, security, authorization, accessibility, low-bandwidth, backup/restore, and disaster-recovery tests.
- Full accounting reconciliation from receipt to Stock to Delivery to Payment/AR to journal and profit.

### Recommended pilot

- Begin with one Warehouse, a limited set of Ways, 3–5 Drivers, controlled Sales users, and 20–50 representative Client Shops.
- Run parallel reconciliation against the existing operational records for at least one complete business cycle.
- Hold daily defect/variance review and prohibit unresolved Stock/cash variances from carrying silently.
- Expand only after the pilot exit criteria remain stable.

### Phase 6 exit criteria — operational go-live gate

- All Phase 6 acceptance scenarios pass without duplicate or partially posted business transactions.
- Stock, invoices, AR, Payments, custody, Cash/Bank, COGS, KPIs, and journals reconcile.
- Driver background GPS and offline behavior pass on approved production devices.
- Pilot users complete Trips and handovers without critical manual database intervention.
- Security, performance, restore drill, operations runbook, training, support, and rollback plan are approved.

---

## 11. Phase 7 — Reports

**Indicative duration:** 4 weeks

### Phase 7 objectives

- Deliver reconciled operational and management reports from governed definitions.
- Replace ad-hoc spreadsheet calculations with traceable, permission-scoped reporting.

### Data and analytics work

- Finalize daily/monthly fact tables for Sales, AR, collection, Stock, cash/bank, AP, FOC, Payroll, Delivery, fleet, and profitability.
- Add source-document IDs, policy/version, freshness, `as of` time, and reconciliation status.
- Use asynchronous generation for large reports and exports.

### Report catalog

- Daily, monthly, and annual Sales.
- Daily/monthly Stock, Opening/Receive/Issue/Transfer/Damage/Closing, Stock Balance, and Stock Card.
- Daily Cash, Cash Book, Bank Book, collection, handover, variance, and reconciliation.
- Customer Ledger, statement, AR aging, overdue, credit utilization, and collection promises.
- Supplier Ledger, AP aging, matching, advances, returns, and Payments.
- Daily/monthly Expense and Profit & Loss.
- Monthly Payroll, attendance, OT, incentive, allowance, advances, salary history, and cost center.
- Driver, Delivery, route history, POD, cash accuracy, and bottle KPI.
- Vehicle cost, fuel, maintenance, utilization, cost/km, and contribution.
- Annual Management Summary and audit/reconciliation reports.

### Report Center

- Common date/period, branch, Warehouse, Area, Way, Brand, Product, Customer, Sales, Driver, Vehicle, and status filters.
- Daily/Monthly/Annual as presets over shared definitions.
- Saved filters, scheduled runs, status/progress, expiring secure download, and CSV/XLSX/PDF outputs.
- Drill-down from totals to facts, source documents, subledgers, and journals.
- Myanmar/English layouts with spreadsheet-injection protection and permission checks at execution and download.

### Phase 7 testing

- Golden-dataset formula and boundary-date tests.
- Source-to-report and report-to-GL reconciliation.
- Permission/export/data-leakage tests.
- Myanmar/English PDF/XLSX visual review.
- Large-export concurrency, timeout, retry, expiry, and performance tests.

### Phase 7 exit criteria

- Every financial and KPI total reconciles to its governed source.
- Daily/Monthly/Annual presets return the same definition for equivalent periods.
- Business owners approve report definitions, filters, date bases, and sample output.
- Large exports do not degrade operational APIs beyond approved targets.

---

## 12. Phase 8 — Executive Dashboard

**Indicative duration:** 4 weeks

### Phase 8 objectives

- Provide Owner and department views that are fast, understandable, and fully traceable.
- Surface exceptions and decisions rather than presenting decorative charts.

### Dashboard views

- **Executive/Owner:** Today/MTD/YTD Sales, target achievement, active Customer count, outstanding receivable, expense, Management Net Profit, Cash/Bank position, Warehouse value, Vehicle cost, and Payroll cost.
- **Sales:** Area/Way Sales, ranking, new/Top Customers, coverage, target versus actual, returns, and cash/credit/FOC mix.
- **Stock:** current/available Stock, value, low Stock, stockout, fast/slow moving, expiry risk, movement, and count variance.
- **Delivery:** completed Delivery/Stops, on-time-in-full, active Trips, Vehicle utilization, cost/base unit, Driver performance, and exceptions.
- **Finance:** collection, outstanding/overdue AR, expense, gross profit, Management Net Profit, Cash/Bank, AP, variance, and profit trend.

### UX implementation

- One permission-aware Dashboard route with internal view tabs/selectors.
- Four or six primary KPI cards, then trend/operations and attention/approval panels.
- Freshness and calculation basis on every KPI.
- Accessible list/table alternatives for charts and maps.
- Responsive view selector for narrow Office widths and long Myanmar labels.
- Drill-down to the Phase 7 Report Center and original transactions.

### Phase 8 testing

- KPI-to-report-to-journal reconciliation.
- Role, branch, Warehouse, and scope isolation.
- Freshness, cache invalidation, delayed-fact, and failed-refresh behavior.
- Light/dark, compact/comfortable, responsive, Myanmar/English, accessibility, and performance tests.

### Phase 8 exit criteria

- Owner and department stakeholders approve definitions and drill-downs.
- No dashboard calculates an independent total that differs from the Report Center.
- Common dashboards meet the performance target and clearly display freshness.
- Approval/exception actions obey maker-checker and audit controls.

---

## 13. Post-Core Optimization

Begin only after stable operational data and measured business value justify the work:

- explainable route optimization;
- demand and replenishment forecasting;
- advanced BI/data warehouse;
- optional Customer payment channels;
- external accounting integration;
- Supplier portal or procurement automation;
- reusable-container/deposit ledger;
- advanced maintenance and telematics integration.

Each addition requires its own scope, privacy/security review, reconciliation design, acceptance criteria, and feature flag.

---

## 14. Cross-Phase Definition of Done

A story or feature is complete only when applicable items below are satisfied:

- acceptance criteria are linked to the SRS and automated/manual tests;
- API authorization and organization/data scope are enforced server-side;
- database migrations, indexes, reversal behavior, and audit events are reviewed;
- retryable writes are idempotent and transaction boundaries are tested;
- Myanmar and English copy is complete;
- loading skeleton, empty, error, offline, stale, permission-denied, conflict, and success states are implemented;
- responsive and accessibility requirements pass;
- logging, metrics, alerts, correlation IDs, and operational runbook are updated;
- security and sensitive-export controls pass;
- performance is measured against the agreed profile;
- documentation, training notes, and support procedures are complete;
- product owner and module owner accept the result in staging.

## 15. Environment and Release Strategy

| Environment | Purpose |
| --- | --- |
| Local/CI | Automated unit, static, migration, and contract checks |
| Development | Shared integration and rapid feature verification |
| Staging | Stable cross-module testing with synthetic data |
| Pre-production/UAT | Production-like configuration, migrated sample data, business UAT, performance and security tests |
| Production | Approved releases only, monitored and recoverable |

Release requirements:

- backward-compatible API/database deployment where possible;
- feature flags for incomplete or risky modules;
- automated backup before material migration;
- tested rollback or forward-fix procedure;
- release notes and known limitations;
- post-deployment smoke and reconciliation checks;
- heightened monitoring after release;
- no direct production-data repair without an approved audited procedure.

## 16. Data Migration and Cutover Waves

### Wave 1 — Clean and map master data

- Company, branches, Warehouses, Areas, Ways, Brands, Products, units, Customers, Suppliers, Employees, Vehicles, and prices.
- Normalize phone numbers, Myanmar Unicode, codes, duplicate Shops, units, and locations.

### Wave 2 — Opening operational balances

- Stock by Warehouse/location/SKU/lot/cost.
- Vehicle odometer and maintenance due values.
- Current published Way assignments and active Customer price assignments.

### Wave 3 — Opening financial and workforce balances

- AR, AP, Cash, Bank, Customer/expense/employee/Supplier advances, Payroll liabilities, and GL opening journals.
- Import historical transactions or opening balances for a domain, never both.

### Final cutover

1. Freeze agreed source changes.
2. Take verified source and destination backups.
3. Run final imports using approved templates and operation IDs.
4. Reconcile record counts, quantities, values, subledgers, and control accounts.
5. Publish assignments, prices, calendars, permissions, and document sequences.
6. Execute cross-application smoke tests.
7. Obtain signed business/Finance/operations approval.
8. Enable production feature flags in the approved sequence.
9. Monitor and reconcile daily during the stabilization period.

## 17. Governance and Progress Reporting

### Sprint cadence

- Sprint planning with acceptance and dependency review.
- Daily engineering coordination.
- Weekly product/operations/Finance risk review.
- Mid-sprint design/API/data review for high-risk transactions.
- Sprint demonstration in both languages where applicable.
- Retrospective and measurable action items.

### Phase reporting

Report the following at least weekly:

- completed versus planned acceptance criteria;
- blocker and decision age;
- open severity-one/two defects;
- automated-test and critical-flow coverage;
- performance and error-rate trend;
- migration/reconciliation exceptions;
- security/privacy findings;
- UAT completion and sign-off status;
- scope, schedule, and capacity risk.

### Change control

A requested change requires impact analysis when it changes:

- settlement or accounting behavior;
- Stock/Delivery/Payment transaction boundaries;
- credit, approval, or permission rules;
- data migration/opening balances;
- Payroll/statutory behavior;
- GPS/privacy/retention;
- external providers or deployment architecture;
- a signed report/KPI formula.

## 18. Major Delivery Risks and Mitigation

| Risk | Mitigation |
| --- | --- |
| Business rules remain undecided | Phase 0 decision log with named owner and deadline |
| Poor source/master data | Early profiling, templates, dry-run imports, duplicate review, signed reconciliation |
| COD/credit/accounting mismatch | Accountant-approved postings and end-to-end reconciliation tests |
| Inventory or cash variance | Immutable custody ledgers, maker-checker, daily close, exception queues |
| Offline duplicate posting | Permanent operation IDs, idempotency, atomic transactions, replay tests |
| Background GPS fails on devices | Approved hybrid package and physical device acceptance matrix |
| Myanmar UI is difficult to use | Native-language review on real devices in every phase |
| Reports disagree with transactions | Governed facts, one formula definition, source/journal drill-down |
| Scope grows during core delivery | Feature flags, formal change control, post-core backlog |
| Phase dependencies are ignored | Release gates; final invoice/COGS/COD activation remains blocked until Phase 6 |

## 19. Final Program Completion Criteria

The initial enterprise program is complete when:

- Office, Sales, Driver, and Client applications are operational in Myanmar and English;
- Client repeat ordering is simple, secure, and reliable on agreed mobile devices/networks;
- Stock, Delivery, COD/credit, cash custody, AR/AP, Cash/Bank, Payroll, and journals reconcile;
- Sales, Driver, Warehouse, Vehicle, Finance, and Executive KPIs retain historical attribution and drill down to source records;
- required reports and dashboards meet freshness, security, export, accessibility, and performance requirements;
- backup restoration and operational support procedures are proven;
- all critical/high defects are closed or formally accepted with a time-bound remediation plan;
- business, Operations, Finance, HR, and technical owners sign the production acceptance record.
