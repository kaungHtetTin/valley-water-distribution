# Valley Water Distribution Software Specification

## 1. Purpose

Build a simple CRUD-based water bottle distribution system for managing reseller customers, sales orders, warehouse stock, deliveries, finance records, HR records, vehicles, reports, and owner dashboards.

The system is not intended to be a complex ERP in the first version. It should focus on clear data entry, simple approvals/status changes, basic stock movement, payment tracking, and useful reports.

## 2. Technology Stack

- Backend: Laravel REST API
- Frontend: React + Vite single page application
- Database: MySQL
- UI: Admin dashboard style from `admin-dashboard-ui-kit.md`
- Languages: English and Myanmar
- Performance: Fast page loads, paginated tables, skeleton loading, lightweight dashboards

## 3. Application Areas

### 3.1 Office Admin App

Desktop-first admin dashboard used by office staff and owners.

Main responsibilities:

- Manage master data
- Review and create sales orders
- Create invoices
- Assign warehouse, driver, vehicle, and route for delivery
- Manage stock receive, stock issue, transfer, damage, and balances
- Record collections, expenses, cash book, bank book, and salary costs
- View customer ledger, supplier ledger, vehicle cost, sales reports, stock reports, and profit/loss
- Manage users, roles, and permissions

### 3.2 Sales App

Mobile-first app for sales representatives. A sales representative is an employee account with sales app access.

Main responsibilities:

- View assigned route/way and assigned customers
- Create customer orders
- Register new customers
- View monthly sales target and sales KPI
- View customer history and outstanding credit
- Record simple collection notes if allowed

### 3.3 Driver App

Mobile-first app for drivers. A driver is an employee account with driver app access.

Main responsibilities:

- View assigned delivery list
- Update delivery status
- Record delivered quantities, returned quantities, and damage quantities
- Share GPS position while delivery is in progress
- View delivery history and simple driver KPI

### 3.4 Client App

Mobile-first app for reseller customers such as restaurants, grocery shops, and water resellers.

Main responsibilities:

- Login with phone/email and optional Google login
- Place water bottle orders with a very simple order form
- View order history and current order status
- View basic ledger/outstanding balance if enabled

## 4. Design Direction

- Office app uses the compact admin dashboard design from `admin-dashboard-ui-kit.md`.
- Sales, driver, and client apps should reuse the same visual identity in mobile-first form.
- Theme should use a fresh water-related blue color family.
- Use clear tables, simple forms, status badges, filters, and action buttons.
- Avoid heavy decoration and enterprise-style complexity.

## 5. Business Scope

### Included

- Master data CRUD
- Customer, employee, vehicle, route, warehouse, product, and price management
- Sales order and invoice management
- Cash sale, credit sale, wholesale, retail, special price, FOC, damage, return, and collection records
- Warehouse stock receive, issue, transfer, damage, closing stock, and stock value
- Delivery planning, vehicle assignment, driver assignment, delivery status, and delivery history
- Finance entries, receivables, expenses, ledgers, payroll, and profit/loss summary
- Basic KPI dashboards and reports
- Dynamic user roles and permission assignment
- Bilingual UI

### Excluded From V1

- Factory production planning
- Complex manufacturing cost calculation
- Advanced route optimization
- Full accounting system with chart of accounts and audited financial statements
- Complex approval workflows
- Multi-company consolidation
- Advanced forecasting or AI analytics

## 6. Master Data Modules

### Company Setup

Stores company name, logo, address, phone, default currency, default language, and invoice settings.

### Area / Route / Way Master

Areas are treated as delivery/sales routes. Example areas include Taunggyi, Aye Thar Yar, and Nam San.

Each route should support:

- Name
- Code
- Active/inactive status
- Optional description

### Warehouse Master

Initial warehouse examples:

- TGI Warehouse
- Nam San Warehouse

Each warehouse should support stock balance by product.

### Product / Item Master

Products are dynamic. Initial water bottle sizes may include 0.5L, 0.7L, and 1L, but new products must be addable later.

Fields:

- Product code
- Product name
- Brand
- Size
- Unit
- Cost price
- Active/inactive status

### Brand Master

Supports Valley and other brands.

### Price Master

Price types are dynamic. Initial price types:

- Retail
- Wholesale
- Special

Each price record should connect product, price type, amount, start date, and active status.

### Customer Master

Customers are reseller shops/restaurants.

Fields:

- Customer code
- Shop name
- Owner/contact name
- Phone
- Address
- Area/route
- Assigned sales representative
- Credit limit
- Opening balance
- Active/inactive status

### Employee Master

Stores office staff, sales representatives, drivers, HR users, managers, and other employees.

Sales representatives and drivers must be managed as employees, not as separate people records. Their payroll, attendance, advances, incentives, allowances, salary history, and employment status are handled through the HR module. Their app access and operational assignments are controlled by role/permission and route/delivery assignment records.

### Vehicle Master

Stores vehicle records for delivery and vehicle cost tracking.

## 7. Sales Module

The sales module should support simple order-to-invoice flow.

Order types:

- Wholesale
- Retail
- Cash
- Credit
- FOC
- Damage
- Sales return

Basic flow:

1. Customer or sales representative creates an order.
2. Office reviews the order.
3. Office confirms product quantities, price type, discount, and payment type.
4. Office creates invoice.
5. Office assigns delivery.
6. Payment/collection is recorded.

Order statuses:

- Draft
- Pending
- Confirmed
- Invoiced
- Assigned
- Delivering
- Delivered
- Cancelled

## 8. Warehouse Module

The warehouse module tracks stock movement only. It does not handle production.

Stock movement types:

- Opening stock
- Stock in / receive
- Stock out / issue
- Transfer
- Damage
- Sales issue
- Sales return
- Closing stock adjustment

Required views:

- Stock receive
- Stock issue
- Stock balance
- Stock transfer
- Damage entry
- Closing stock
- Stock value
- Stock card per product

## 9. Delivery Module

Office assigns deliveries to a warehouse, driver, vehicle, and route.

Delivery fields:

- Delivery number
- Invoice/order
- Warehouse
- Route
- Driver
- Vehicle
- Planned date
- Status
- Delivery note

Delivery statuses:

- Planned
- Assigned
- Loading
- On route
- Delivered
- Partially delivered
- Failed
- Cancelled

Driver app should allow status updates and simple quantity confirmation.

GPS tracking should store latest driver position during active delivery so office can view drivers on a live map.

## 10. Finance Module

The finance module is simple bookkeeping for distribution operations.

Included:

- Cash book
- Bank book
- Daily expense
- Advance
- Collection
- Outdoor employee expense
- Outdoor employee collection
- Customer ledger
- Supplier ledger
- Customer receivable
- Expense entry
- Profit and loss report

Outdoor employee finance applies to sales representatives, drivers, and other employees who work outside the office. The system should record their route-related expenses and money collected from customers so office finance can reconcile cash, bank, customer receivable, and profit/loss.

Outdoor employee expense fields should include employee, role/type, route, date, expense category, amount, payment method, description, and optional attachment/receipt.

Outdoor employee collection fields should include employee, customer, route, invoice/order, collection date, amount, payment method, reference number, and note.

Profit calculation should be simple:

Sales revenue - product cost - office expenses - outdoor employee expenses - vehicle cost - employee salary/payroll cost = net profit

## 11. HR Module

HR module should support simple employee administration.

Included:

- Employee CRUD
- Attendance
- Payroll
- OT
- Incentive
- Allowance
- Advance
- Salary history

Payroll can be simple monthly payroll, not a complex HR system. Payroll and attendance must include all employee types, including office staff, sales representatives, warehouse staff, and drivers.

Sales representative payroll may include salary, allowance, incentive, advance deduction, and attendance/OT adjustment. Driver payroll may include salary, allowance, delivery incentive, advance deduction, and attendance/OT adjustment.

### QR Attendance Flow

Attendance should use a simple public URL with QR codes placed at each office or warehouse.

Basic flow:

1. Office admin creates an attendance location for an office or warehouse.
2. The system generates a public attendance URL/QR code for that location.
3. The QR code is printed and placed at the physical office or warehouse.
4. Employee arrives at the location and scans the QR code.
5. The public page asks for employee ID.
6. The browser requests the employee device GPS position.
7. The system compares the registered office/warehouse GPS position with the employee device GPS position.
8. If the employee is within about 20 meters, the system creates the attendance record.
9. If the employee is outside the allowed radius or GPS permission is denied, the system rejects the attendance entry and shows a clear message.

Attendance record fields should include employee, attendance location, scan date/time, attendance type, device latitude, device longitude, distance from location, status, and note.

Attendance locations should include name, type, office/warehouse reference, latitude, longitude, allowed radius in meters, public QR token, and active status. The default allowed radius should be 20 meters.

## 12. Vehicle Module

Vehicle module should track operational cost and route history.

Included:

- Fuel
- Maintenance
- Insurance
- License
- Engine oil
- Tyre
- Cost per KM
- Monthly vehicle cost
- Route history
- Vehicle performance report

## 13. Reports

Required reports:

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
- Sales performance
- Driver performance
- Vehicle performance

Reports should support basic date filters, route filters, customer filters, warehouse filters, and export/print where useful.

## 14. Owner Dashboard

Owner dashboard should show:

- Today's sales
- Monthly sales
- Annual sales
- Cash balance
- Bank balance
- Outstanding credit
- Warehouse value
- Vehicle cost
- Salary cost
- Net profit
- KPI charts

## 15. KPI Dashboards

### Owner KPI

- Sales
- Target achievement percentage
- Customer count
- Receivable
- Expense
- Outdoor employee expense
- Field collection
- Profit

### Sales KPI

- Area sales
- Sales ranking
- New customer count
- Top customer
- Target vs actual

Sales KPI should be based on the sales amount from customers inside the route assigned to that sales representative for the selected month.

### Stock KPI

- Current stock
- Fast moving product
- Slow moving product
- Stock alert

### Delivery KPI

- Completed delivery
- Vehicle usage
- Delivery cost
- Driver performance

Driver KPI can be based on total delivered bottle quantity during a selected month or date range.

### Finance KPI

- Collection
- Debt balance
- Expense analysis
- Outdoor expense
- Field collection
- Profit trend

## 16. Roles and Permissions

Office admin must support dynamic role creation instead of fixed roles only.

Examples:

- Owner
- Admin
- Manager
- HR
- Accountant
- Warehouse user
- Sales supervisor
- Sales representative
- Driver
- Customer

Permissions should be CRUD-based:

- View
- Create
- Edit
- Delete
- Export/print
- Approve/confirm where needed

## 17. Data Relationship Summary

Main sales relationship:

Customer -> Order -> Invoice -> Delivery -> Payment -> Profit Report

Main stock relationship:

Stock Receive -> Stock Balance -> Sales Issue -> Closing Stock

Main assignment relationship:

Route -> Customer
Route -> Monthly Sales Representative Assignment
Delivery -> Warehouse + Driver + Vehicle + Route

## 18. Non-Functional Requirements

- Responsive UI for desktop and mobile apps
- Bilingual English/Myanmar UI
- Skeleton loading for lists, dashboards, and detail pages
- Server-side pagination for large tables
- Search and filters on main list screens
- Audit fields on important records: created by, updated by, created at, updated at
- Soft delete for important master data where practical
- Database backups should be supported operationally
- Role-based route and API protection
- GPS tracking should only be active during driver delivery work

## 19. V1 Delivery Priority

### Phase 1: Master Data

Company, area/route, warehouse, product, brand, price, customer, employee, vehicle, roles, users.

### Phase 2: Sales

Orders, invoice, payment type, discount, FOC, damage, return, collection.

### Phase 3: Warehouse

Opening stock, stock in, stock out, transfer, damage, closing stock, stock value.

### Phase 4: Finance

Cash book, bank book, daily expense, advance, collection, customer ledger, supplier ledger.

### Phase 5: HR

Employee, attendance, payroll, OT, incentive, allowance, advance, salary history.

### Phase 6: Vehicle

Fuel, maintenance, insurance, license, engine oil, tyre, cost per KM, route history.

### Phase 7: Reports

Daily, monthly, annual, customer ledger, stock card, profit/loss, performance reports.

### Phase 8: Executive Dashboard

Owner dashboard, KPI cards, KPI charts, sales/stock/finance/delivery summary.
