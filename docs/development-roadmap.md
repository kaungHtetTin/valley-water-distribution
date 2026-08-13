# Valley Water Distribution Development Roadmap

## Roadmap Goal

Build the system phase by phase with visible results at the end of every phase. Each phase should include enough UI screens and sample data so the owner can test the visual flow in Office, Client, Sales, and Driver apps.

The project should stay CRUD-first. Do not build complex enterprise workflows before the basic list, create, edit, detail, status, and report screens are working.

## App Types

- Office App: desktop-first admin dashboard for owner/admin/office staff.
- Client App: mobile-first app for reseller customers.
- Sales App: mobile-first app for sales representatives.
- Driver App: mobile-first app for drivers.

## Visual Testing Rule

Every phase must provide:

- Working navigation/menu for the phase.
- At least one list/table screen.
- At least one create/edit form where relevant.
- Detail or status screen where relevant.
- Loading, empty, and error states for important screens.
- Desktop test for Office App.
- Mobile viewport test for Client, Sales, and Driver apps.
- Seed/demo data so screens can be visually reviewed immediately.

Recommended review viewports:

- Office desktop: 1440x900 and 1280x720
- Tablet: 1024px width
- Mobile apps: 390x844 and 430x932

## Phase 0: Project Foundation and UI Shell

### Objective

Create the technical base, app routing, authentication shell, layout system, theme, localization, and demo navigation for all apps.

### Backend

- Create Laravel project structure and API base.
- Configure MySQL connection.
- Add authentication foundation.
- Add users, roles, permissions, and basic seed data.
- Add language files for English and Myanmar.
- Add API response format and validation error format.

### Office App

- Build admin layout from `admin-dashboard-ui-kit.md`.
- Add sidebar, topbar, theme color, density mode, language switcher, and dashboard placeholder.
- Add login screen.
- Add role-aware navigation placeholder.

### Client App

- Build mobile-first layout.
- Add login screen.
- Add simple home screen placeholder.

### Sales App

- Build mobile-first layout.
- Add login screen.
- Add assigned route placeholder.

### Driver App

- Build mobile-first layout.
- Add login screen.
- Add assigned delivery placeholder.

### Visual Test Result

- User can open all four app shells.
- Office app looks like a compact operations dashboard.
- Mobile apps feel related to the office app but optimized for phones.
- Language switcher changes visible labels.

## Phase 1: Master Data

### Objective

Build the master data required by every later module.

### Backend

- Create CRUD APIs for company, areas/routes, warehouses, brands, products, price types, product prices, customers, employees, vehicles, roles, and permissions.
- Add validation, pagination, search, filters, and active/inactive status.
- Seed sample products, warehouses, customers, employees, sales reps, drivers, and vehicles.

### Office App

- Company setup screen.
- Area/route master list and form.
- Warehouse master list and form.
- Brand master list and form.
- Product/item master list and form.
- Price type and price master screens.
- Customer master list, form, and detail.
- Employee master list, form, and detail.
- Vehicle master list and form.
- Role and permission management screens.

### Client App

- Customer profile screen.
- Read-only assigned route and shop information.

### Sales App

- Assigned customers list.
- Customer detail screen.
- New customer registration form.

### Driver App

- Driver profile screen.
- Assigned vehicle placeholder.

### Visual Test Result

- Office admin can visually review all master data tables and forms.
- Sales app can show assigned customer list on mobile.
- Client app can show customer profile.
- Driver app can show driver profile.

## Phase 2: QR Attendance and HR Base

### Objective

Build attendance first because all employee types depend on it, including office staff, sales reps, warehouse staff, and drivers.

### Backend

- Create attendance locations table and CRUD API.
- Generate public QR token per attendance location.
- Create public attendance submit API.
- Create attendance records table and API.
- Implement GPS distance check against the attendance location.
- Default allowed radius: 20 meters.
- Add rejection status for denied GPS, invalid employee ID, inactive QR token, and outside allowed radius.

### Office App

- Attendance location list and form.
- QR code preview/download/print screen.
- Attendance records list with filters by employee, location, date, and status.
- Employee attendance detail.

### Client App

- No required work in this phase.

### Sales App

- Public QR attendance page opened from QR scan.
- Employee ID entry and GPS permission flow.
- Success/failure attendance result screen.

### Driver App

- Same public QR attendance flow as sales employees.

### Visual Test Result

- Office can create an office/warehouse attendance location.
- Office can view QR code.
- Employee can scan/open public URL, enter employee ID, and see attendance result.
- Out-of-range and GPS-denied states are visible.

## Phase 3: HR Payroll, Advances, Allowances, and Salary History

### Objective

Complete simple employee HR records and payroll cost tracking.

### Backend

- Create attendance summary API.
- Create payroll, payroll items, OT, incentive, allowance, and employee advance APIs.
- Connect payroll with employees, attendance, advances, allowances, incentives, and OT.

### Office App

- Attendance summary by employee/month.
- Payroll list, create, edit, and detail.
- Employee advance screen.
- OT, incentive, and allowance screens.
- Salary history screen.

### Client App

- No required work in this phase.

### Sales App

- Sales employee can view own attendance history.
- Sales employee can view own salary/payment history if permission allows.

### Driver App

- Driver can view own attendance history.
- Driver can view own salary/payment history if permission allows.

### Visual Test Result

- Office can generate a monthly payroll draft.
- Salary cost appears as a visible total.
- Sales and driver apps can show employee attendance history.

## Phase 4: Customer Ordering and Sales

### Objective

Build customer order creation, sales rep order creation, office review, and invoice creation.

### Backend

- Create order and order item APIs.
- Create invoice and invoice item APIs.
- Support cash, credit, wholesale, retail, special price, FOC, damage, and sales return records.
- Add order status flow: draft, pending, confirmed, invoiced, assigned, delivering, delivered, cancelled.

### Office App

- New order screen.
- Orders list with tabs/status filters.
- Order detail and confirmation action.
- Invoice list, create from order, and invoice detail.
- Sales return and damage entry screens.

### Client App

- Simple order form.
- Order history.
- Order status detail.

### Sales App

- Create order for assigned customer.
- View customer order history.
- View outstanding balance/credit status.
- Register new customer from route.

### Driver App

- No delivery execution yet, only invoice/delivery placeholder if needed.

### Visual Test Result

- Client can place a simple mobile order.
- Sales rep can place order for assigned customer.
- Office can confirm order and create invoice.
- Order and invoice status changes are visible.

## Phase 5: Warehouse Stock

### Objective

Build stock receive, issue, transfer, damage, closing stock, stock value, and stock card.

### Backend

- Create stock movement APIs.
- Create stock balance calculation/update logic.
- Support opening stock, stock in, stock out, transfer, damage, sales issue, sales return, and adjustment.
- Add stock card report API.

### Office App

- Opening stock screen.
- Stock receive screen.
- Stock issue screen.
- Stock transfer screen.
- Damage stock screen.
- Stock balance table.
- Stock value screen.
- Stock card report.

### Client App

- Show product availability indicator if required.

### Sales App

- Show available products for order entry.

### Driver App

- Show loading quantity placeholder for assigned delivery.

### Visual Test Result

- Office can receive stock into TGI/Nam San warehouse.
- Office can transfer stock between warehouses.
- Stock balance and stock value update visibly.
- Stock card shows product movement history.

## Phase 6: Delivery Planning and Driver App

### Objective

Build delivery assignment from invoices and driver delivery execution.

### Backend

- Create delivery and delivery item APIs.
- Add delivery assignment to warehouse, route, driver, and vehicle.
- Add delivery statuses: planned, assigned, loading, on route, delivered, partially delivered, failed, cancelled.
- Connect delivery completion with invoice/order status.
- Add driver location tracking API during active delivery.

### Office App

- Delivery planning screen.
- Vehicle assignment screen.
- Delivery calendar/list view.
- Delivery detail.
- Driver live map view.
- Delivery history.

### Client App

- Show delivery status for customer orders.

### Sales App

- Show delivery status for sales rep customer orders.

### Driver App

- Assigned delivery list.
- Delivery detail.
- Loading confirmation.
- Delivered/partial/failed status update.
- Delivered quantity, returned quantity, damage quantity, and note form.
- GPS sharing while active delivery is in progress.

### Visual Test Result

- Office can assign invoice to warehouse, vehicle, driver, and route.
- Driver can update delivery status on mobile.
- Client and sales apps can see delivery status.
- Office can see driver latest location on map.

## Phase 7: Finance, Collections, Expenses, and Ledgers

### Objective

Build financial tracking for collections, receivables, cash/bank, outdoor employee expenses, and simple profit/loss.

### Backend

- Create payment/collection APIs.
- Create customer receivable and ledger APIs.
- Create cash book and bank book APIs.
- Create daily expense API.
- Create outdoor employee expense API.
- Create outdoor employee collection API.
- Connect collections to customer receivable.
- Include outdoor employee expenses in reports and profit/loss.

### Office App

- Collection screen.
- Outdoor employee collection screen.
- Customer receivable screen.
- Customer ledger.
- Supplier ledger.
- Cash book.
- Bank book.
- Daily expense.
- Outdoor employee expense screen.
- Profit/loss report.

### Client App

- Customer ledger/outstanding balance screen if enabled.
- Payment/collection history.

### Sales App

- Record field collection if permission allows.
- Record outdoor expense if permission allows.
- View submitted collections and expenses.

### Driver App

- Record delivery-related collection if permission allows.
- Record outdoor expense if permission allows.
- View submitted collections and expenses.

### Visual Test Result

- Office can see customer receivable decrease after collection.
- Sales/driver can submit outdoor expense from mobile.
- Office can approve/review submitted field finance records.
- Profit/loss shows payroll, vehicle cost, and outdoor employee expense.

## Phase 8: Vehicle Cost and Route History

### Objective

Build vehicle operational cost tracking and performance report.

### Backend

- Create vehicle cost APIs for fuel, maintenance, insurance, license, engine oil, tyre, and other costs.
- Connect vehicle usage with delivery route history.
- Calculate cost per KM when distance data is available.

### Office App

- Fuel screen.
- Maintenance screen.
- Insurance screen.
- License screen.
- Engine oil screen.
- Tyre screen.
- Vehicle monthly cost screen.
- Vehicle route history.
- Cost per KM report.
- Vehicle performance report.

### Client App

- No required work in this phase.

### Sales App

- No required work in this phase.

### Driver App

- Driver can view assigned vehicle.
- Driver can submit basic vehicle issue/cost note if permission allows.

### Visual Test Result

- Office can enter monthly vehicle costs.
- Vehicle performance report shows route history and cost summary.

## Phase 9: Reports

### Objective

Build the reporting layer after operational data exists.

### Backend

- Create report APIs with date filters and related filters.
- Add export/print endpoints where needed.

### Office App

- Daily sales.
- Daily stock.
- Daily cash.
- Monthly sales.
- Monthly stock.
- Monthly expense.
- Monthly payroll.
- Annual summary.
- Customer ledger.
- Supplier ledger.
- Stock card.
- Profit and loss.
- Sales performance.
- Driver performance.
- Vehicle performance.

### Client App

- Order history report.
- Ledger/outstanding report if enabled.

### Sales App

- Sales performance report.
- Route/customer sales report.
- New customer report.
- Collection report.

### Driver App

- Driver delivery history report.
- Driver KPI report.
- Outdoor expense/collection report.

### Visual Test Result

- Office can filter reports by date, route, customer, warehouse, employee, driver, and vehicle where useful.
- Mobile apps can view their own history and performance reports.
- Print/export controls are visible for office reports.

## Phase 10: Executive Dashboard and KPI Charts

### Objective

Build owner dashboards and KPI summaries after the underlying data is reliable.

### Backend

- Create dashboard summary APIs.
- Create KPI chart APIs.
- Cache expensive dashboard summaries where useful.

### Office App

- Owner dashboard:
  - Today's sales
  - Monthly sales
  - Annual sales
  - Cash balance
  - Bank balance
  - Outstanding credit
  - Warehouse value
  - Vehicle cost
  - Salary cost
  - Outdoor employee expense
  - Net profit
  - KPI charts
- Sales KPI dashboard.
- Stock KPI dashboard.
- Delivery KPI dashboard.
- Finance KPI dashboard.

### Client App

- Small home summary:
  - Current order status
  - Recent orders
  - Outstanding balance if enabled

### Sales App

- Sales rep dashboard:
  - Monthly sales
  - Target vs actual
  - New customers
  - Collections
  - Assigned route

### Driver App

- Driver dashboard:
  - Today deliveries
  - Completed deliveries
  - Delivered quantity
  - Assigned route
  - Submitted expenses/collections

### Visual Test Result

- Owner can review all important numbers from one dashboard.
- Sales and driver users can see simple personal KPI summaries.
- Dashboard cards and charts look correct in desktop and mobile viewports.

## Phase 11: Polish, Security, and UAT

### Objective

Prepare the application for real user testing.

### Backend

- Review permissions on every API.
- Add audit fields on important records.
- Add soft delete where needed.
- Add database backup plan.
- Optimize slow queries.
- Add seed data reset for demo/testing.

### Office App

- Fix visual consistency.
- Add loading/empty/error states where missing.
- Check English/Myanmar labels.
- Check dark/light theme if enabled.
- Check compact/comfortable density if enabled.

### Client App

- Simplify order form based on user testing.
- Improve mobile touch targets.
- Confirm Myanmar labels fit.

### Sales App

- Test real route/customer/order flow.
- Confirm field collection and expense entry are fast.

### Driver App

- Test QR attendance, delivery status, GPS sharing, and expense entry on real phones.

### Visual Test Result

- Full user acceptance test can be run phase by phase.
- Owner can review final desktop and mobile screens.
- Bugs are tracked by module and fixed before launch.

## Recommended Build Order Summary

1. Foundation and UI shell
2. Master data
3. QR attendance and HR base
4. HR payroll
5. Customer ordering and sales
6. Warehouse stock
7. Delivery and driver app
8. Finance
9. Vehicle cost
10. Reports
11. Executive dashboards
12. UAT and launch polish

## Phase Review Checklist

Use this checklist before accepting each phase:

- Office app screens are visible and navigable.
- Related mobile app screens are visible and usable.
- Demo data exists.
- CRUD actions work for the phase.
- Tables have search/filter/pagination.
- Forms show validation errors.
- Loading and empty states are visible.
- English/Myanmar labels are prepared for the screens.
- Permission behavior is checked for main actions.
- The owner can test the phase without reading code.
