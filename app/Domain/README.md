# Domain modules

The Laravel application is a modular monolith. Business code belongs in one of these bounded modules rather than in controllers:

- Identity and Access
- Organization and Master Data
- Client and Territory
- Catalog and Pricing
- Orders
- Inventory and Warehousing
- Dispatch and Delivery
- Fleet and GPS
- Receivables, Cash, Treasury, Payables, and Finance
- HR, Attendance, and Payroll
- KPI and Reporting
- Notifications and Files
- Audit and System Administration

Modules communicate through explicit application services and domain events. Controllers validate transport concerns and delegate; they do not query across module boundaries.
