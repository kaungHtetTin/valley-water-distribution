# KPI Phase 0 — Accepted Business Rules

Status: **Complete**  
Accepted: **2026-09-27**  
Source workbook: [`KPI.xlsx`](./KPI.xlsx)  
Roadmap: [`kpi-implementation-roadmap.md`](./kpi-implementation-roadmap.md)

## Product Direction

The first KPI release must be easy for Office staff to operate. It covers Sales Representatives and Drivers only. The application uses the useful weights from the workbook without copying its broken formulas or repeated employee blocks.

Helper and storekeeper reviews, formula builders, period reopening, and complex audit workflows remain postponed. Sales Supervisor team hierarchy and scoped representative KPI review were added after the core monthly flow was validated.

## Accepted Rules

| Rule | Decision |
|---|---|
| KPI period | Calendar month |
| Roles in first release | Sales Representative and Driver |
| Target bonus | 40,000 MMK per employee and month |
| Score cap | 100% per metric |
| Workflow | Draft → Submitted → Approved |
| Draft entry | Office enters targets, actuals, and manager scores in one form |
| Approval | User with Payroll Manage permission |
| Missing values | Remain Pending and block submission |
| Automatic metric formula | Higher result: `MIN(actual ÷ target, 100%)` |
| Manual metric formula | Manager score from 0 to 100 |
| Weighted score | Achievement × metric weight |
| Overall score | Sum of weighted scores |

## Proportional Bonus

Bonus = monthly target bonus × displayed overall score ÷ 100, rounded to two decimal places. The score is capped at 100%; there is no minimum payout threshold. For example, 40,000 MMK × 60.77% = 24,308 MMK. Employee and personal mobile KPI panels refresh every 20 seconds while visible and when the window regains focus. Refreshing leaves open target and manager forms intact.

Draft payroll includes the current KPI bonus for the month, even before KPI approval or posting. Loading the draft refreshes KPI figures and payroll totals; the detail page refreshes every 20 seconds. A posted KPI bonus is counted through its adjustment instead of added a second time. Other incentive adjustments are added normally. Payroll approval refreshes and freezes the amounts, and subsequent reads preserve approved and paid payroll totals. Previously approved or posted KPI bonuses retain their historical payout amounts.

## Sales Representative Template

| Metric | Weight | First release entry |
|---|---:|---|
| Net sales achievement | 35% | Net sold stock quantity in units; default monthly target 4,000 |
| New customer acquisition | 15% | Target and actual |
| Customer visit completion | 15% | Target and actual |
| Collection achievement | 20% | Target and actual |
| Attendance and punctuality | 5% | Target and actual |
| Teamwork and discipline | 5% | Manager score |
| Task and report completion | 5% | Manager score |

Net sales achievement uses sale-item quantities from issued, delivered, and partially delivered invoices attributed to the sales employee. Confirmed sales-return quantities are deducted in the month of the return. FOC items and returns of FOC items are excluded; the net quantity cannot fall below zero. Achievement is net quantity ÷ quantity target × 100, capped at 100%. Monetary sales targets do not override the KPI quantity target. Existing staff targets and draft reviews move to the 4,000-unit default; finalized legacy reviews retain their MMK unit and bonuses, and reports separate quantities from monetary values.

## Driver Template

| Metric | Weight | First release entry |
|---|---:|---|
| Attendance and punctuality | 20% | Target and actual |
| Leave compliance | 5% | Manager score |
| Delivery task completion | 20% | Target and actual |
| Complaint-free delivery | 10% | Manager score |
| Teamwork and responsibility | 20% | Manager score |
| Damage-free delivery | 15% | Manager score |
| On-time and safe delivery | 5% | Manager score |
| Vehicle and fuel cost control | 5% | Manager score |

Manager entry keeps the first release operational where the application does not yet hold reliable complaint, leave, incident, or damage responsibility data. Automatic sources can replace those inputs later without changing the monthly review screen.

## Deferred Decisions

- Sales invoice, return, collection, visit, and attendance automation.
- Driver attendance, completed delivery, damage, complaint, and vehicle cost automation.
- Bonus posting to payroll.
- Personal KPI pages in the Sales and Driver apps.
- Template version management, locking, reopening, and historical Excel import.

## Sales Supervisor Update — 2026-10-01

- Sales Supervisor is a separate employee type and does not create sales.
- One supervisor can manage multiple Sales Representatives.
- A Sales Representative can belong to only one supervisor.
- The Supervisor mobile application provides team, attendance, profile/security, and scoped KPI reporting.
- Supervisor access is read only for representative results; KPI preparation and approval remain Office permissions.

## Staff Target Setup Update — 2026-09-27

Office can now assign one KPI role and reusable monthly targets to each employee. New monthly reviews copy these staff targets automatically. Existing draft reviews are updated when a staff target changes, while submitted and approved history remains unchanged.

The default roles now match the original workbook:

- Sales Representative
- Sales Supervisor
- Driver
- Helper
- Storekeeper

Active Sales staff default to Sales Representative, Drivers default to Driver, and Warehouse staff default to Storekeeper. Office can change the assignment for an individual employee from **Payroll → KPI Targets**.
