# Employee types and permission roles

Employee types determine salary defaults and KPI templates. Matching access roles use the same display names:

| Employee type | Access role | Default app / permissions |
| --- | --- | --- |
| Sale Helper | Sale Helper | No app access or permissions |
| Sale Representative | Sale Representative | Sales app, existing sales workflow |
| Storekeeper | Storekeeper | Office inventory viewing and stock management |
| Accountant | Accountant | Office accounting and payroll payment |
| Manager | Manager | Existing Finance Manager financial review, KPI/payroll approval |
| Sale Manager | Sale Manager | Supervisor app, assigned-team oversight |
| HR | HR | Office employee records, attendance, KPI preparation, payroll drafts |
| Driver | Driver | Driver app, existing delivery workflow |
| Other | Other | Existing Office Staff operational permissions |

Owner and Customer remain separate access roles. The employee form supports optional email/password accounts for every listed type except Sale Helper. Enter email, password, and matching password confirmation to create an account with the employee type's matching role. Leave email and password blank to create an employee without an account. An existing employee without a login can receive an account later through Edit. Passwords require at least eight characters. Email must be unique across accounts and employees.

For linked accounts, changing email updates the login; leaving password blank preserves it. An unrelated employee edit preserves an existing customized role. Changing employee type assigns the new type's role; changing to Sale Helper removes the previous role and revokes personal access tokens. Existing linked account email is shown even if the employee record's email was blank. Account creation, password/email changes, and changes of type on a linked employee require `office.access.users.manage`; HR employee-management permission alone does not grant account administration. Salary and KPI settings remain determined by employee type.

Deploy the backend changes, compiled `public/build` assets, and `2026_11_07_000001_align_roles_with_employee_types.php` together. Run `php artisan migrate --force` and `php artisan optimize:clear` during the deployment. Native packages load the hosted frontend and do not require rebuilding for this change.

The migration renames Sales Representative, Sales Supervisor, Finance Manager, and Office Staff roles and updates their users. It creates missing Storekeeper and Sale Helper roles. Rerunning it preserves customized permissions. If both an old role and its new name exist with different access settings, it rolls back and reports the conflict instead of combining permissions. Rename the conflicting new-name role to a custom name through Roles & Permissions before retrying; that form also updates its existing users. Equivalent duplicates can be consolidated automatically.
