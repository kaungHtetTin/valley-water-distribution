# API Permission Matrix

All business APIs are behind session authentication. Public exceptions are limited to login/auth state, local-only demo diagnostics, and tokenized attendance; sensitive public submissions and login attempts are throttled. `PhaseElevenHardeningTest` inventories every registered API route and fails if a new route is neither authenticated nor explicitly reviewed as public.

| API group | View permission | Mutation permission | Scope enforcement |
| --- | --- | --- | --- |
| Company and master data | `office.master-data.view` | `office.master-data.manage` | Office app; linked records block deletion |
| Orders and adjustments | `office.orders.view` | `office.orders.manage` | Office app |
| Invoices | `office.invoices.view` | `office.invoices.manage` | Office app and confirmed-order rules |
| Stock | `office.inventory.view` | `office.inventory.manage` | Warehouse/product validation and balance checks |
| Delivery | `office.deliveries.view` | `office.deliveries.manage` | Office app; driver endpoints require assigned driver |
| Attendance | `office.attendance.view` | `office.attendance.manage` | Public scan is token/radius throttled; mobile history is employee-scoped |
| Payroll | `office.payroll.view` | `office.payroll.manage` | Mobile salary history is employee-scoped |
| Finance | `office.finance.view` | `office.finance.manage` | Sales route, driver delivery, and client customer scope |
| Vehicle costs | `office.vehicle-costs.view` | `office.vehicle-costs.manage` | Driver submission requires assigned vehicle |
| Dashboards | `office.dashboard.view` | None | Client/Sales/Driver home permissions and identity scope |
| UAT and audit | `office.uat.view` | `office.uat.manage` | Owner/Office only; audit log is read-only |
| Client mobile orders | `client.orders.view` | `client.orders.create` | Signed-in customer only |
| Sales mobile orders/customers | `sales.orders.view`, `sales.customers.view` | Matching `.create` permission | Assigned route only |
| Sales/Driver finance | Matching `.collections.view`, `.expenses.view` | Matching `.create` permission | Assigned route/customer/delivery only |
| Driver execution | `driver.load.view`, `driver.route.view`, `driver.confirm.view` | Matching `.update` permission | Assigned delivery only; GPS requires active route |

Successful mutations are also passed through `audit.api`, which records the actor and redacted change payload after authorization succeeds.
