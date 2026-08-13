# Valley Water Distribution Software Requirements

## 1. Requirement Style

This document converts the notes from `requirement.md` and the phase photos into developer-ready requirements.

The required system is a simple CRUD distribution application. Development should prefer clean forms, tables, statuses, and reports over complicated enterprise business logic.

## 2. Global Requirements

REQ-GEN-001: The system shall use Laravel as the backend API.

REQ-GEN-002: The system shall use React + Vite as the frontend SPA.

REQ-GEN-003: The system shall use MySQL as the database.

REQ-GEN-004: The system shall support English and Myanmar language labels.

REQ-GEN-005: The system shall show skeleton loading on list, detail, report, and dashboard pages.

REQ-GEN-006: The system shall use server-side pagination for large lists.

REQ-GEN-007: The system shall provide search and basic filters on major list pages.

REQ-GEN-008: The office admin UI shall follow `admin-dashboard-ui-kit.md`.

REQ-GEN-009: Mobile apps for sales, driver, and client users shall use a mobile-first version of the same visual system.

REQ-GEN-010: The system shall use a fresh blue water-related theme color.

## 3. User and Permission Requirements

REQ-AUTH-001: Users shall be able to log in with email/phone and password.

REQ-AUTH-002: Customer users shall optionally be able to log in with Google.

REQ-AUTH-003: Office admin shall be able to create, edit, delete, and view roles.

REQ-AUTH-004: Office admin shall be able to assign permissions to roles dynamically.

REQ-AUTH-005: Permissions shall control view, create, edit, delete, export/print, and confirm actions where applicable.

REQ-AUTH-006: Menu items and API access shall respect assigned permissions.

## 4. Master Data Requirements

REQ-MAS-001: Office admin shall manage company setup.

REQ-MAS-002: Office admin shall manage area/route records.

REQ-MAS-003: Office admin shall manage warehouse records.

REQ-MAS-004: Office admin shall manage product/item records.

REQ-MAS-005: Office admin shall manage brand records.

REQ-MAS-006: Office admin shall manage customer records.

REQ-MAS-007: Office admin shall manage employee records.

REQ-MAS-007A: Sales representatives and drivers shall be employee records with assigned roles/app access, not separate people master records.

REQ-MAS-008: Office admin shall manage vehicle records.

REQ-MAS-009: Office admin shall manage dynamic price types such as retail, wholesale, and special.

REQ-MAS-010: Office admin shall assign customers to routes.

REQ-MAS-011: Office admin shall assign customers to sales representatives.

REQ-MAS-012: Office admin shall support monthly sales representative route assignment because routes can change every month.

## 5. Customer Requirements

REQ-CUS-001: Customer records shall include customer code, shop name, phone, address, area/route, credit limit, and active status.

REQ-CUS-002: Customer management shall provide register, history, credit control, and customer report screens.

REQ-CUS-003: Customer history shall show orders, invoices, collections, returns, and outstanding balance.

REQ-CUS-004: Credit control shall show credit limit, current receivable, and overdue/remaining balance.

REQ-CUS-005: Customer app order entry shall be simple and mobile-friendly.

REQ-CUS-006: Customer app shall show order history and order status.

## 6. Product and Price Requirements

REQ-PRO-001: Product records shall include product code, product name, brand, size, unit, cost price, and active status.

REQ-PRO-002: The system shall allow new product sizes and product types in the future without code changes.

REQ-PRO-003: Price records shall connect product, price type, amount, effective date, and active status.

REQ-PRO-004: The system shall allow new price types in the future without code changes.

## 7. Sales Requirements

REQ-SAL-001: Office admin and sales users shall be able to create orders.

REQ-SAL-002: Customer users shall be able to create their own orders from the client app.

REQ-SAL-003: Orders shall support cash, credit, wholesale, retail, FOC, damage, and sales return cases.

REQ-SAL-004: Orders shall include customer, order date, route, sales representative, line items, quantities, price type, discount, FOC quantity, and note.

REQ-SAL-005: Office admin shall be able to review and confirm customer/sales orders.

REQ-SAL-006: Office admin shall be able to create invoices from confirmed orders.

REQ-SAL-007: Invoice records shall include invoice number, customer, invoice date, items, subtotal, discount, tax if used, total, paid amount, balance, and status.

REQ-SAL-008: Sales returns shall reduce customer balance or create a return record based on office action.

REQ-SAL-009: Sales reports shall support daily, monthly, annual, customer, product, route, and sales representative filters.

REQ-SAL-010: Sales KPI shall calculate monthly sales by route assignment.

## 8. Warehouse Requirements

REQ-WHS-001: Office admin shall manage opening stock per warehouse and product.

REQ-WHS-002: Office admin shall record stock receive.

REQ-WHS-003: Office admin shall record stock issue.

REQ-WHS-004: Office admin shall record warehouse-to-warehouse transfer.

REQ-WHS-005: Office admin shall record damage stock.

REQ-WHS-006: The system shall show current stock balance by warehouse and product.

REQ-WHS-007: The system shall show closing stock.

REQ-WHS-008: The system shall calculate stock value from quantity and product cost.

REQ-WHS-009: The system shall provide stock card report per product.

REQ-WHS-010: Invoice delivery issue shall reduce stock from the selected warehouse.

REQ-WHS-011: The system shall not include factory production management in v1.

## 9. Delivery Requirements

REQ-DEL-001: Office admin shall create delivery plans from invoices/orders.

REQ-DEL-002: Office admin shall assign warehouse, route, driver, and vehicle to delivery.

REQ-DEL-003: Driver app shall show assigned delivery list.

REQ-DEL-004: Driver app shall allow status updates: assigned, loading, on route, delivered, partially delivered, failed.

REQ-DEL-005: Driver app shall allow recording delivered quantity, return quantity, damage quantity, and note.

REQ-DEL-006: Office admin shall view delivery history.

REQ-DEL-007: Office admin shall view driver live GPS position while the driver has active delivery work.

REQ-DEL-008: Driver KPI shall show delivered bottle quantity for a selected month/date range.

## 10. Finance Requirements

REQ-FIN-001: Office admin shall manage cash book records.

REQ-FIN-002: Office admin shall manage bank book records.

REQ-FIN-003: Office admin shall manage daily expense records.

REQ-FIN-004: Office admin shall manage advance records.

REQ-FIN-005: Office admin shall manage collection records.

REQ-FIN-005A: Office admin shall manage outdoor employee collection records for sales representatives, drivers, and other field employees.

REQ-FIN-005B: Outdoor employee collection records shall include employee, customer, route, invoice/order, collection date, amount, payment method, reference number, and note.

REQ-FIN-006: Office admin shall view customer ledger.

REQ-FIN-007: Office admin shall view supplier ledger.

REQ-FIN-008: Office admin shall view customer receivable.

REQ-FIN-009: Office admin shall view profit and loss report.

REQ-FIN-010: Profit/loss shall use simple calculation based on revenue, product cost, expenses, vehicle costs, and salary costs.

REQ-FIN-011: Salary cost shall include payroll cost for all employee types, including office staff, sales representatives, warehouse staff, and drivers.

REQ-FIN-012: Office admin shall manage outdoor employee expense records for sales representatives, drivers, and other field employees.

REQ-FIN-013: Outdoor employee expense records shall include employee, role/type, route, date, expense category, amount, payment method, description, and optional attachment/receipt.

REQ-FIN-014: Outdoor employee expenses shall be included in expense reports and profit/loss calculation.

REQ-FIN-015: Outdoor employee collections shall update customer receivable and shall be visible in cash/bank reconciliation reports.

## 11. HR Requirements

REQ-HR-001: Office admin shall manage employee records.

REQ-HR-001A: Employee records shall support employee type or role assignment such as office staff, sales representative, warehouse staff, driver, HR, accountant, and manager.

REQ-HR-002: Office admin shall manage attendance records.

REQ-HR-002A: Attendance shall be tracked for all employee types, including sales representatives and drivers.

REQ-HR-002B: Office admin shall manage attendance locations for offices and warehouses.

REQ-HR-002C: Each attendance location shall store name, type, office/warehouse reference, latitude, longitude, allowed radius in meters, public QR token, and active status.

REQ-HR-002D: The system shall generate a public attendance URL and QR code for each active attendance location.

REQ-HR-002E: Employees shall scan the QR code at the office/warehouse and enter their employee ID to submit attendance.

REQ-HR-002F: The public attendance page shall request device GPS position before creating an attendance record.

REQ-HR-002G: The system shall compare the attendance location GPS position with the employee device GPS position.

REQ-HR-002H: The system shall accept attendance when the employee device is within the configured allowed radius. The default allowed radius shall be 20 meters.

REQ-HR-002I: The system shall reject attendance when GPS permission is denied, GPS data is unavailable, employee ID is invalid, location QR token is invalid/inactive, or distance is outside the allowed radius.

REQ-HR-002J: Attendance records shall include employee, attendance location, scan date/time, attendance type, device latitude, device longitude, calculated distance, status, and note.

REQ-HR-003: Office admin shall manage monthly payroll records.

REQ-HR-003A: Payroll shall be calculated for all employee types, including sales representatives and drivers.

REQ-HR-004: Office admin shall manage OT records.

REQ-HR-005: Office admin shall manage incentive records.

REQ-HR-006: Office admin shall manage allowance records.

REQ-HR-007: Office admin shall manage employee advance records.

REQ-HR-008: Office admin shall view salary history.

REQ-HR-009: Sales representative payroll shall support salary, allowance, incentive, advance deduction, attendance adjustment, and OT where used.

REQ-HR-010: Driver payroll shall support salary, allowance, delivery incentive, advance deduction, attendance adjustment, and OT where used.

## 12. Vehicle Requirements

REQ-VEH-001: Office admin shall manage vehicle fuel records.

REQ-VEH-002: Office admin shall manage vehicle maintenance records.

REQ-VEH-003: Office admin shall manage insurance records.

REQ-VEH-004: Office admin shall manage license records.

REQ-VEH-005: Office admin shall manage engine oil records.

REQ-VEH-006: Office admin shall manage tyre records.

REQ-VEH-007: Office admin shall view monthly vehicle cost.

REQ-VEH-008: Office admin shall view route history by vehicle.

REQ-VEH-009: Office admin shall view cost per KM if distance data is entered.

REQ-VEH-010: Office admin shall view vehicle performance report.

## 13. Report Requirements

REQ-REP-001: The system shall provide daily sales report.

REQ-REP-002: The system shall provide daily stock report.

REQ-REP-003: The system shall provide daily cash report.

REQ-REP-004: The system shall provide monthly sales report.

REQ-REP-005: The system shall provide monthly stock report.

REQ-REP-006: The system shall provide monthly expense report.

REQ-REP-007: The system shall provide monthly payroll report.

REQ-REP-008: The system shall provide annual summary report.

REQ-REP-009: The system shall provide customer ledger report.

REQ-REP-010: The system shall provide supplier ledger report.

REQ-REP-011: The system shall provide stock card report.

REQ-REP-012: The system shall provide profit and loss report.

REQ-REP-013: Reports shall include date filters.

REQ-REP-014: Reports shall include related filters where useful, such as customer, route, warehouse, product, employee, driver, and vehicle.

REQ-REP-015: Reports shall support print/export where useful.

## 14. Dashboard Requirements

REQ-DASH-001: Owner dashboard shall show today's sales.

REQ-DASH-002: Owner dashboard shall show monthly sales.

REQ-DASH-003: Owner dashboard shall show annual sales.

REQ-DASH-004: Owner dashboard shall show cash balance.

REQ-DASH-005: Owner dashboard shall show bank balance.

REQ-DASH-006: Owner dashboard shall show outstanding credit.

REQ-DASH-007: Owner dashboard shall show warehouse value.

REQ-DASH-008: Owner dashboard shall show vehicle cost.

REQ-DASH-009: Owner dashboard shall show salary cost.

REQ-DASH-010: Owner dashboard shall show net profit.

REQ-DASH-011: Owner dashboard shall show KPI charts.

REQ-DASH-012: Sales dashboard shall show area sales, ranking, new customer, top customer, and target vs actual.

REQ-DASH-013: Stock dashboard shall show current stock, fast moving product, slow moving product, and stock alert.

REQ-DASH-014: Delivery dashboard shall show completed delivery, vehicle usage, delivery cost, and driver performance.

REQ-DASH-015: Finance dashboard shall show collection, debt balance, expense analysis, and profit trend.

## 15. Suggested Database Tables

- users
- roles
- permissions
- role_user
- permission_role
- companies
- areas
- routes
- warehouses
- brands
- products
- price_types
- product_prices
- customers
- employees
- vehicles
- monthly_route_assignments
- orders
- order_items
- invoices
- invoice_items
- payments
- outdoor_employee_collections
- stock_movements
- stock_balances
- deliveries
- delivery_items
- driver_locations
- expenses
- outdoor_employee_expenses
- cash_book_entries
- bank_book_entries
- advances
- attendance_locations
- attendance_records
- payrolls
- payroll_items
- vehicle_costs

## 16. Main Menu Tree

Dashboard:

- Owner dashboard
- Sales dashboard
- Stock dashboard
- Finance dashboard
- Delivery dashboard
- Management report

Master Data:

- Company setup
- Area/route master
- Warehouse master
- Product/item master
- Brand master
- Customer master
- Employee master
- Vehicle master
- Price master

Customer Management:

- Customer register
- Customer history
- Credit control
- Customer report

Sales Management:

- New order
- Invoice
- Sales target
- Sales performance
- Sales report

Warehouse Management:

- Stock receive
- Stock issue
- Stock transfer
- Damage
- Stock balance
- Stock report

Delivery Management:

- Delivery planning
- Vehicle assignment
- Driver report
- Delivery history

Finance Management:

- Cash book
- Bank book
- Collection
- Outdoor employee collection
- Receivable
- Expense
- Outdoor employee expense
- Profit/loss

HR Management:

- Employee
- Attendance locations
- Attendance
- Payroll
- OT
- Incentive
- Allowance
- Advance
- Salary history

Vehicle Management:

- Fuel
- Maintenance
- Insurance
- License
- Engine oil
- Tyre
- Cost per KM
- Route history

Reports:

- Daily sales
- Daily stock
- Daily cash
- Monthly sales
- Monthly stock
- Monthly expense
- Monthly payroll
- Annual summary
- Customer ledger
- Supplier ledger
- Stock card
- Profit and loss

## 17. Acceptance Criteria

- Main CRUD screens can create, view, edit, delete, search, filter, and paginate records.
- Orders can be converted to invoices.
- Invoices can be assigned to delivery.
- Delivery can update stock issue and delivery status.
- Collections update customer receivable.
- Stock balance is visible by warehouse and product.
- Owner dashboard shows the required summary cards.
- Role permissions affect menus and actions.
- English and Myanmar labels are available.
- Mobile client order form is simple enough to place an order quickly.
- Reports can be filtered by date and printed/exported where required.
