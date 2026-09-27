# Development Progress

## Trip-level Multi-order Distribution

- [x] One trip is limited to orders from one route.
- [x] Route-matching orders appear first in order selection, with an option to show all eligible orders.
- [x] Office staff can select several orders and set their stop sequence.
- [x] Driver loading is confirmed once for the whole trip with quantities allocated to each order.
- [x] Drivers can hold several assigned or loaded trips without vehicle-capacity rules.
- [x] Only one trip can be on route at a time; each stop is completed separately before the trip is closed.
- [x] Trip-level cards, totals, dashboard entries, GPS tracking, and history are used across Office and Driver apps.

Status: Complete. Verified with 124 automated tests and a production Vite build.

## Phase 8: Vehicle Cost and Route History

- [x] Requirements and current delivery/vehicle foundations audited.
- [x] Vehicle cost schema and route-distance fields.
- [x] Office and Driver APIs with permissions.
- [x] Office vehicle cost and performance screens.
- [x] Driver assigned-vehicle issue/cost submission.
- [x] Demo data, automated tests, build, and visual verification.

Status: Complete. Verified with 90 automated tests, a production Vite build, and authenticated desktop, tablet, dark-theme, comfortable-density, Myanmar, and Driver mobile renders.

## Phase 10: Executive Dashboard and KPI Charts

- [x] Dashboard requirements and current data sources audited.
- [x] Cached owner and KPI dashboard APIs.
- [x] Office owner, sales, stock, delivery, and finance dashboards.
- [x] Client, Sales, and Driver mobile KPI summaries.
- [x] Automated tests, build, and rendered viewport verification.

Status: Complete. Verified with 96 automated tests, a production Vite build, and authenticated desktop, tablet, dark-theme, comfortable-density, Myanmar, Client, Sales, and Driver mobile renders.

## Phase 11: Polish, Security, and UAT

- [x] API permission inventory, throttling, security headers, and production demo-route protection.
- [x] Mutation audit trail and creator/updater fields on important master records.
- [x] Recoverable soft deletion for important master records.
- [x] Composite query indexes and slow-query monitoring.
- [x] Scheduled database backup command, retention policy, restore runbook, and safe demo reset.
- [x] Office UAT readiness, issue tracking, and audit log workspace.
- [x] Simplified mobile ordering, offline warning, touch targets, focus states, and print styles.
- [x] Phase-by-phase UAT and real-phone test plan.
- [x] Full automated regression, dependency audits, production build, backup restore drill, and final emulated viewport verification.
- [x] Production configuration checker, CI quality gate, and deployment/rollback runbook.
- [ ] Business Android real-phone execution and owner UAT sign-off.

Status: Release candidate ready after production environment setup. Implementation verified on 2026-09-27 with 141 passing tests (1,561 assertions), zero Composer/npm advisories, a production Vite build, cacheable routes/config/views, a fresh isolated MySQL restore drill, and authenticated desktop, tablet, dark-theme, comfortable-density, Myanmar, offline, Client, Sales, Driver, and public attendance renders. The seeded Delivery UAT finding tracks the remaining physical Android GPS/QR/route sign-off.

## Phase 9: Consolidated Operations Reports

- [x] Daily, monthly, yearly, and custom date scopes.
- [x] Route, customer, warehouse, employee, driver, and vehicle filters.
- [x] Sales, orders, stock, cash, expense, payroll, delivery, and customer summaries.
- [x] Daily/monthly trend and route, customer, product, expense, payroll, driver, and stock breakdowns.
- [x] CSV export, print layout, permissions, automated tests, and responsive Office UI.

Status: Complete. The report APIs, export, permissions, visual screen, and shared filter scope are covered by the production regression suite.

## KPI Phase 0–1: Rules and Monthly Review Foundation

- [x] Reviewed `docs/KPI.xlsx` and corrected the workbook weight/formula gaps in the system design.
- [x] Accepted a simple Sales and Driver first release.
- [x] Added seeded Sales and Driver templates with 100% weight totals.
- [x] Added monthly review generation for active employees.
- [x] Added target, actual, manager score, weighted score, and bonus calculations.
- [x] Added Draft, Submitted, and Approved workflow using payroll permissions.
- [x] Added the Office KPI Reviews list, filters, summaries, and compact review modal.
- [x] Applied the additive database migration and generated September Sales demo reviews.

Status: Complete. PHP syntax, API routes, live API generation, 100% metric weights, and the production Vite build were verified. Browser screenshot automation was unavailable because the browser runtime did not provide its required sandbox metadata.

## KPI Phase 2: Sales Automatic Figures

- [x] Attributed eligible invoices through the Sales-created order and employee user account.
- [x] Deducted confirmed returns using the original Sales order owner.
- [x] Imported existing monthly sales targets when available.
- [x] Counted newly registered active customers with explicit Sales creator attribution.
- [x] Counted completed route visits, approved employee collections, and distinct accepted attendance days.
- [x] Stored refresh time, source count, and a short source explanation per automatic metric.
- [x] Kept automatic actuals read only while targets and manager scores remain editable.
- [x] Added a draft-only Refresh figures action and refresh during monthly generation.
- [x] Migrated the schema and refreshed the September Sales reviews.

Status: Complete. Live API verification returned 496,500 MMK from two eligible Sales Demo invoices, both Sales reviews refreshed successfully, PHP syntax passed, API routes loaded, and the production Vite build completed.

## KPI Phase 3: Driver Automatic Figures

- [x] Counted distinct accepted Driver attendance days.
- [x] Used assigned non-cancelled delivery stops as the completion target.
- [x] Counted delivered and partially delivered stops as completed.
- [x] Included delivered quantity in the source explanation.
- [x] Kept attendance working-day target editable.
- [x] Kept complaint, damage, safety, leave, discipline, and vehicle cost as manager scores.
- [x] Added Driver refresh during monthly generation and from the review modal.
- [x] Migrated the schema and generated the September Driver review.

Status: Complete. Live API verification returned 1 accepted attendance day, 5 of 5 completed stops, and 146 delivered units for Driver Demo. PHP syntax, API routes, migration status, and the production Vite build were verified.

## KPI Phase 4: Employee View and Evidence

- [x] Added a personal KPI page to the Sales and Driver apps.
- [x] Scoped the mobile KPI API to the signed-in employee.
- [x] Added month selection, score, workflow status, and bonus preview.
- [x] Added previous-review comparison and recent monthly history.
- [x] Added compact metric contribution cards that fit the mobile width.
- [x] Displayed short source evidence for automatically refreshed metrics.
- [x] Kept the employee experience read only for every review status.

Status: Complete. Authenticated live API checks returned only Sales Demo's review in the Sales app and only Driver Demo's review in the Driver app. PHP syntax, the mobile KPI route, and the production Vite build were verified.

## KPI Phase 5: Payroll Bonus

- [x] Added explicit bonus posting for Approved KPI reviews.
- [x] Created one active payroll incentive with the KPI month end as its effective date.
- [x] Added KPI and payroll adjustment references, month, score, amount, and posting time.
- [x] Prevented duplicate posting with a persisted link and transaction lock.
- [x] Protected KPI generated adjustments from manual edit and deletion.
- [x] Displayed KPI source details in Payroll Adjustments.
- [x] Displayed payroll posting status in Office and employee KPI views.

Status: Complete. Migration 000005 ran successfully. Authenticated API checks loaded KPI references and payroll adjustments, and the posting guard returned HTTP 409 for an unapproved review. PHP syntax, route registration, schema status, and the production Vite build were verified.

## KPI Phase 6: Reports

- [x] Added the Office KPI Reports page under Payroll.
- [x] Added monthly and yearly filters with role and employee selection.
- [x] Added role-level review, score, approval, bonus, and payroll posting summaries.
- [x] Added monthly and five-year score trend charts.
- [x] Added approval and bonus status analysis.
- [x] Added target-versus-actual metric breakdowns.
- [x] Added a detailed KPI result table with source references.
- [x] Added Excel-compatible CSV and print exports.

Status: Complete. Authenticated live API checks returned three September reviews across two roles and a filtered Driver yearly report with eight metrics. PHP syntax, report route registration, responsive production assets, and the Vite build were verified.
