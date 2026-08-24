# Valley User Acceptance Test Plan

Use `/office/uat` to record every finding. Password for seeded test accounts is `password`. Reset only disposable local data with `php artisan valley:demo-reset --force` before a formal run.

## Acceptance workflow

For each scenario, record tester, device/browser, date, result, and evidence. A failed scenario must have a UAT issue code. Critical and high findings must be fixed, retested, and closed before launch.

| Phase | Role | Acceptance scenario | Evidence |
| --- | --- | --- | --- |
| 0 Foundation | All | Sign into the correct app; reject cross-app login; switch English/Myanmar, light/dark, compact/comfortable | Screenshots and role name |
| 1 Master data | Office | Create and edit area, route, customer, employee, product, price, and vehicle; confirm recoverable delete | Record codes and audit events |
| 2 Attendance | Office/mobile | Print QR, scan on a real phone, allow GPS, accept inside radius, reject outside radius | Attendance record and phone model |
| 3 Payroll | Office/Sales/Driver | Generate draft, adjust, approve, pay, and view personal salary history | Payroll code |
| 4 Orders | Client/Sales/Office | Place a two-line mobile order, confirm in Office, invoice it, then verify mobile status | Order and invoice codes |
| 5 Stock | Office | Receive, issue, transfer, damage, close, and verify stock card/value | Movement codes and balances |
| 6 Delivery | Office/Driver/Client | Assign invoice, confirm load, start route, share GPS, deliver, and verify customer status | Delivery code and map points |
| 7 Finance | Office/Sales/Driver/Client | Submit field collection and expense quickly, approve in Office, and verify receivable/books/P&L | Collection and expense codes |
| 8 Vehicle | Driver/Office | Submit route cost/issue, approve, enter distance, and verify cost/km and performance | Vehicle cost code |
| 9 Reports | Owner/Office | Filter sales, stock, delivery, finance, payroll, and vehicle reports; print required views | Printed/PDF evidence |
| 10 Dashboards | Owner/mobile | Compare KPI cards to source transactions and change date/month filters | Source codes and screenshot |
| 11 Launch | Owner | Review audit history, resolve findings, run backup/restore drill, and approve launch gate | Backup filename and sign-off |

## Real-phone checks

Run on at least one business Android device at normal browser zoom:

- Touch targets can be activated without accidental adjacent taps.
- Myanmar labels wrap without hiding amounts, status, or actions.
- Client order entry can be completed without opening optional details.
- Sales collection and expense entry can be completed during a route stop.
- Driver QR attendance requests GPS clearly.
- GPS sharing occurs only for the assigned active route and stops after completion.
- Delivery completion and expense submission survive a temporary network interruption by retaining entered form data and allowing retry after reconnection.

## Sign-off

Launch is accepted only when the owner confirms:

- No unresolved critical/high findings.
- Required medium findings have an agreed release decision.
- Backup restore drill passed.
- Permission matrix and audit history were reviewed.
- Real-phone scenarios passed.
