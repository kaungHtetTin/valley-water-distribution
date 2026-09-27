# KPI Phase 0 — Accepted Business Rules

Status: **Complete**  
Accepted: **2026-09-27**  
Source workbook: [`KPI.xlsx`](./KPI.xlsx)  
Roadmap: [`kpi-implementation-roadmap.md`](./kpi-implementation-roadmap.md)

## Product Direction

The first KPI release must be easy for Office staff to operate. It covers Sales Representatives and Drivers only. The application uses the useful weights from the workbook without copying its broken formulas or repeated employee blocks.

Supervisor, helper, storekeeper, team hierarchy, formula builders, period reopening, and complex audit workflows are postponed until the core monthly review is proven useful.

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

## Bonus Bands

| Overall score | Payout |
|---:|---:|
| Below 75% | 0% of target bonus |
| 75%–79.99% | 50% |
| 80%–89.99% | 80% |
| 90%–99.99% | 100% |
| 100% | 120% |

The KPI page shows the projected bonus. Posting that bonus into payroll remains a later phase so Office staff can verify the scores first.

## Sales Representative Template

| Metric | Weight | First release entry |
|---|---:|---|
| Net sales achievement | 35% | Target and actual |
| New customer acquisition | 15% | Target and actual |
| Customer visit completion | 15% | Target and actual |
| Collection achievement | 20% | Target and actual |
| Attendance and punctuality | 5% | Target and actual |
| Teamwork and discipline | 5% | Manager score |
| Task and report completion | 5% | Manager score |

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
- Team assignments, template version management, locking, reopening, and historical Excel import.

## Staff Target Setup Update — 2026-09-27

Office can now assign one KPI role and reusable monthly targets to each employee. New monthly reviews copy these staff targets automatically. Existing draft reviews are updated when a staff target changes, while submitted and approved history remains unchanged.

The default roles now match the original workbook:

- Sales Representative
- Sales Supervisor
- Driver
- Helper
- Storekeeper

Active Sales staff default to Sales Representative, Drivers default to Driver, and Warehouse staff default to Storekeeper. Office can change the assignment for an individual employee from **Payroll → KPI Targets**.
