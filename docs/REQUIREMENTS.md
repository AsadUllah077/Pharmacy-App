# PROJECT: Complete Pharmacy Management ERP System

## 1. Your Role and Mission

Act as a senior software architect, senior Laravel engineer, Vue.js developer, database designer, UI/UX designer, QA engineer, and security engineer.

Your task is to design, develop, test, document, and prepare a complete, production-oriented Pharmacy Management ERP system.

This must be a real, functional business application, not a demo, static dashboard, UI prototype, or collection of disconnected CRUD pages.

Implement actual database persistence, business rules, authorization, transactions, reporting, validation, automated tests, and working user interfaces.

Do not stop after creating the project structure or writing a development plan. Inspect the repository, establish the architecture, implement the software in phases, run tests, fix errors, and continue until the core workflows are functional.

If the repository already contains a project, inspect it first and preserve existing working functionality. Do not overwrite existing code unnecessarily.

If a requirement is ambiguous, choose a reasonable, configurable default, document it, and continue without unnecessarily interrupting development.

---

## 2. Project Objectives

Build a pharmacy ERP that can manage:

- Retail pharmacy operations.
- Medicine and pharmaceutical product catalogs.
- Batch-wise inventory and expiry tracking.
- Barcode-based point of sale.
- Supplier management and purchasing.
- Customer management.
- Prescription recording and dispensing workflows.
- Sales returns and purchase returns.
- Cash, bank, expenses, and payment management.
- Accounting and financial reporting.
- Multiple branches and warehouses.
- Employees, roles, permissions, and audit trails.
- Notifications and operational alerts.
- Business analytics and management dashboards.
- Data imports, exports, backups, and system settings.

The application must support future expansion into a multi-branch pharmacy chain.

Use modular architecture, clear business boundaries, reusable components, and maintainable code.

## 3. Technology Stack

Use the following technologies, subject to compatibility checks:

### Backend

- PHP 8.2 or a newer version supported by the selected Laravel release.
- Laravel 12 or the latest stable compatible Laravel release.
- Eloquent ORM.
- Laravel Form Requests for validation.
- Policies, Gates, and middleware for authorization.
- Laravel queues and scheduled tasks.
- Laravel notifications and events where appropriate.
- REST API endpoints where external integrations or future mobile applications require them.

### Frontend

- Vue 3.
- TypeScript.
- Inertia.js for a cohesive Laravel/Vue application.
- Tailwind CSS.
- Vite.
- Reusable Vue components.
- A suitable icon library.
- A charting library for dashboard analytics.

Use Vue Composition API and `<script setup lang="ts">`.

Do not mix multiple frontend architectures unnecessarily. If the repository already uses a different working architecture, assess it before introducing changes.

### Database

Support PostgreSQL and MySQL where practical.

- Use proper foreign keys, indexes, unique constraints, decimal monetary fields, and transactions.
- Avoid database-specific features unless documented.
- Use migrations and seeders for schema and initial data.
- Never use floating-point fields for monetary calculations.
- Store timestamps consistently and display them in the configured business timezone.

### Development and quality tools

- Composer.
- NPM.
- Laravel Pint.
- Pest or PHPUnit.
- TypeScript type checking.
- ESLint and formatting tools compatible with the chosen frontend setup.
- Laravel queues and scheduler.
- Redis where useful, without making it an unnecessary requirement for local development.

Verify package compatibility before installing dependencies.

---

## 4. Architecture and Coding Standards

Follow these principles:

- SOLID.
- DRY.
- KISS.
- Separation of concerns.
- Dependency inversion where appropriate.
- Secure-by-default design.
- Test-driven development for critical business rules where practical.

Organize code into clear layers.

Example structure:

- Models: database entities and relationships.
- Form Requests: input validation and authorization preparation.
- Policies: record-level authorization.
- Actions or application services: business operations.
- Controllers: HTTP request orchestration.
- Resources or DTOs: structured API responses when needed.
- Jobs: background processing.
- Events and listeners: decoupled side effects.
- Enums: stable application states.
- Vue pages: screen-level functionality.
- Vue components: reusable interface elements.

Do not create unnecessary abstractions or hundreds of classes without a meaningful purpose.

Avoid massive controllers, duplicated business logic, overly complex model methods, and generic service classes that have no clear responsibility.

Use database transactions for workflows that modify multiple related records.

Keep money calculations, inventory calculations, permissions, and other critical rules on the server.

Never trust values such as prices, discounts, totals, costs, stock quantities, roles, or permissions sent by the browser.

---

# 5. Authentication, Users, Roles, and Permissions

Implement secure authentication and account management.

Required features:

- Login and logout.
- Password reset.
- Password change.
- Optional email verification where applicable.
- User activation and deactivation.
- Session security.
- Login throttling.
- Optional two-factor authentication.
- User profile and preferences.
- Password hashing.
- Secure cookie and CSRF configuration.
- Session invalidation after important account changes.

Create configurable roles such as:

1. Super Administrator.
2. Pharmacy Owner.
3. Branch Manager.
4. Pharmacist.
5. Cashier.
6. Inventory Manager.
7. Purchasing Officer.
8. Accountant.
9. Auditor.
10. Read-Only Manager.

Implement granular permissions, including:

- medicines.view
- medicines.create
- medicines.update
- medicines.delete
- inventory.view
- inventory.adjust
- inventory.transfer
- sales.view
- sales.create
- sales.discount
- sales.cancel
- sales.refund
- purchases.create
- purchases.approve
- accounting.view
- accounting.manage
- reports.view
- users.manage
- settings.manage
- audit_logs.view

Create equivalent permissions for all modules.

Requirements:

- Enforce permissions in backend routes and policies, not only in the UI.
- Hide unauthorized navigation items and actions.
- Validate access to individual records.
- Restrict users to their authorized branches and warehouses.
- Prevent users from approving their own restricted transactions where segregation of duties is configured.
- Log sensitive operations.
- Never expose privileged administrative actions to ordinary users.

---

# 6. Multi-Branch and Warehouse Management

Create a multi-branch architecture.

Features:

- Create and edit branches.
- Branch codes and contact details.
- Business hours and timezone settings.
- Branch-specific invoice numbering.
- Branch-specific cash registers.
- Multiple warehouses per branch.
- Assign employees to branches.
- Configure default branch and warehouse.
- Branch-specific sales and purchasing.
- Branch-specific stock levels.
- Consolidated management reports.
- Inter-branch stock transfers.
- Transfer approval and receiving.
- Stock-in-transit tracking.
- Branch-specific operating expenses.
- Branch-level permissions.

Every branch-scoped transaction must be associated with the correct branch.

Prevent unauthorized cross-branch data access.

Avoid duplicating medicine master records unnecessarily. Share product definitions while tracking stock and batch quantities separately by branch and warehouse.

---

# 7. Medicine and Product Catalog

Create a comprehensive medicine catalog.

Fields should include:

- Internal product code.
- Product name.
- Generic name.
- Brand or manufacturer.
- Active ingredient.
- Strength.
- Dosage form.
- Pack size.
- Unit of measure.
- Category.
- Product type.
- Barcode and alternate barcodes.
- SKU.
- Description.
- Prescription classification.
- Storage instructions.
- Tax category.
- Minimum stock level.
- Reorder quantity.
- Default selling price.
- Active/inactive status.
- Product image where applicable.
- Supplier associations.

Support product types such as:

- Prescription medicines.
- Over-the-counter medicines.
- Medical supplies.
- Medical devices.
- Personal care products.
- Other configurable pharmacy products.

Create management screens for:

- Categories.
- Manufacturers.
- Brands.
- Dosage forms.
- Units.
- Product attributes.
- Tax categories.

Features:

- Search by name, generic name, SKU, barcode, and brand.
- Advanced filters.
- Sorting and pagination.
- Duplicate barcode detection.
- Bulk import from validated CSV/XLSX files.
- Bulk export.
- Product history.
- Archive products without destroying historical transactions.

Store product master data separately from individual purchase batches.

---

# 8. Batch-Wise Inventory and Expiry Management

This is one of the most important modules.

Track stock by product, batch, branch, and warehouse.

Each batch should support:

- Product ID.
- Batch or lot number.
- Supplier and source purchase.
- Manufacturing date where available.
- Expiry date where applicable.
- Purchase cost.
- Selling price.
- Received quantity.
- Remaining quantity.
- Reserved quantity where reservation is supported.
- Warehouse location.
- Batch status.
- Creation and receipt timestamps.

Create stock movement records containing:

- Product.
- Batch.
- Branch.
- Warehouse.
- Movement type.
- Quantity in.
- Quantity out.
- Unit cost where applicable.
- Reference transaction.
- User responsible.
- Reason.
- Timestamp.

Movement types should include:

- Purchase receipt.
- Sale.
- Customer return.
- Supplier return.
- Stock adjustment.
- Damaged stock.
- Expired stock.
- Branch transfer.
- Transfer receipt.
- Transfer reversal.
- Opening stock.
- Approved stock correction.

Inventory rules:

1. Never permit negative sellable stock by default.
2. Prevent concurrent sales from overselling the same stock.
3. Lock or atomically update affected inventory rows during critical operations.
4. Record every stock movement.
5. Do not allow users to edit historical movement records directly.
6. Use controlled correction and reversal workflows.
7. Exclude expired and quarantined stock from sellable inventory.
8. Warn about batches approaching expiry.
9. Support configurable expiry alert periods.
10. Prioritize the earliest-expiring eligible batch using FEFO.
11. Prevent stock issued to customers from being allocated to an ineligible batch.
12. Provide reconciliation between stock movement records and current stock balances.

Design stock adjustment approvals for significant quantity or value changes.

Create inventory reports:

- Current stock.
- Stock valuation.
- Batch-wise stock.
- Expired stock.
- Near-expiry stock.
- Low-stock products.
- Out-of-stock products.
- Slow-moving products.
- Fast-moving products.
- Damaged and quarantined stock.
- Stock movement history.
- Inventory discrepancies.

Define and document the inventory valuation method. Use a consistent method such as weighted average or FIFO for accounting valuation, while retaining FEFO for expiry-aware dispensing. Do not confuse the stock dispensing rule with the accounting valuation method.

---

# 9. Point of Sale (POS)

Build a fast, keyboard-friendly pharmacy POS.

Interface requirements:

- Search medicines by name, generic name, SKU, or barcode.
- Support USB barcode scanners that behave as keyboard input devices.
- Support manual item selection.
- Show available stock and eligible batches.
- Display expiry warnings where relevant.
- Add and remove cart items.
- Change quantities within authorized limits.
- Apply permitted line-level and invoice-level discounts.
- Calculate taxes according to configuration.
- Support customer selection.
- Support walk-in customers.
- Display subtotal, discounts, taxes, and grand total.
- Accept multiple payment methods.
- Calculate change for cash payments.
- Print invoices.
- Reprint authorized historical invoices.
- Support parked and resumed sales where safe.
- Support sales history and cashier shifts.
- Provide responsive layouts.

Payment methods may include:

- Cash.
- Bank transfer.
- Card.
- Mobile wallet.
- Split payment across multiple methods.

Use a server-side pricing and calculation service.

At checkout:

1. Validate the branch, warehouse, customer, cart, and payment.
2. Recalculate prices and discounts on the server.
3. Verify stock availability.
4. Select eligible batches using the configured allocation rules.
5. Lock the necessary stock records.
6. Create the sale and line items.
7. Create stock movement records.
8. Record payments.
9. Update inventory balances.
10. Commit all database changes atomically.
11. Generate an invoice number that is unique within its configured scope.
12. Trigger receipts and other side effects after the transaction commits.

Prevent duplicate checkout requests through idempotency or another reliable duplicate-submission strategy.

If any critical step fails, roll back the transaction.

Never mark a sale as completed if the corresponding stock and payment records were not correctly persisted.

Support configurable policies for credit sales and customer balances.

---

# 10. Sales, Returns, Refunds, and Voids

Create complete sales management.

Features:

- Sales list and details.
- Search by invoice number.
- Filter by date, cashier, branch, payment status, and customer.
- Sale detail with batch allocations.
- Invoice printing.
- Authorized cancellation or voiding.
- Partial and full returns.
- Refund processing.
- Exchange workflows where appropriate.
- Return reason and approval.
- Customer credit handling.
- Sales audit history.

Return rules:

- Never delete completed sales to correct an error.
- Create linked return transactions.
- Limit returns to the unreturned quantity.
- Preserve original sale prices and discounts.
- Track the original batch when available.
- Assess returned medicine condition and storage integrity.
- Return medicine to sellable inventory only when an authorized user confirms it is safe and compliant with applicable rules.
- Otherwise place it in quarantine or record it as damaged.
- Record refund liabilities and payment reversals.
- Prevent repeated refunds for the same returned quantity.

Maintain a complete relationship between the original sale, return, stock movement, and refund.

---

# 11. Supplier Management and Purchasing

Create supplier records containing:

- Supplier name.
- Registration or tax number where applicable.
- Contact information.
- Address.
- Payment terms.
- Credit limit.
- Opening balance.
- Notes.
- Status.

Create purchasing workflows:

- Purchase request.
- Purchase order.
- Approval.
- Goods receipt.
- Supplier invoice.
- Purchase bill.
- Purchase payment.
- Partial receipt.
- Backorder tracking where required.
- Purchase cancellation.
- Supplier return.
- Purchase return credit.
- Outstanding balance tracking.

Purchase items must support:

- Product.
- Batch number.
- Expiry date where applicable.
- Received quantity.
- Free quantity if supported.
- Unit cost.
- Discount.
- Tax.
- Additional allocated costs.
- Line total.

Receiving stock must create batch records and stock movement records atomically.

Use separate purchase order, goods receipt, supplier bill, and payment concepts where needed. Do not assume an order has been received or paid merely because it was created.

Support reconciliation between purchase orders, goods receipts, and supplier invoices.

---

# 12. Customers and Customer Accounts

Create customer management with:

- Name.
- Customer code.
- Contact details.
- Optional address.
- Optional tax information.
- Account status.
- Credit limit.
- Opening balance.
- Notes.

Features:

- Customer sales history.
- Returns and refunds.
- Receipts and payment history.
- Outstanding balances.
- Customer statements.
- Credit sales, if enabled.
- Due date and payment reminders.
- Customer ledger.

Collect only necessary customer information.

Treat patient-related information as sensitive and apply appropriate access controls and retention policies.

---

# 13. Prescription and Dispensing Management

Create a prescription module with configurable dispensing controls.

Features:

- Prescription number.
- Patient or customer reference.
- Prescriber information.
- Prescription date.
- Medicine items.
- Dosage instructions.
- Quantity prescribed.
- Quantity dispensed.
- Remaining quantity where applicable.
- Pharmacist review.
- Dispensing history.
- Attachments where permitted.
- Prescription status.

Implement configurable workflows for:

- Draft.
- Under review.
- Approved.
- Partially dispensed.
- Fully dispensed.
- Rejected.
- Cancelled.

Prescription requirements:

- Apply the appropriate legal and business rules for the operating jurisdiction.
- Prevent unauthorized dispensing of restricted products.
- Do not assume all medicines require prescriptions or that all prescriptions permit unrestricted refills.
- Require authorized professional review where applicable.
- Record dispensing actions and the responsible pharmacist.
- Protect prescription and patient information.
- Avoid diagnosing patients or recommending medication through automated software.

Do not hard-code drug schedules or prescription laws without a verified jurisdiction-specific source. Make classifications and applicable rules configurable.

---

# 14. Cash Register and Shift Management

Create a cash management module.

Features:

- Open shift.
- Opening cash balance.
- Cash sales.
- Cash refunds.
- Cash expenses.
- Cash deposits and withdrawals.
- Expected closing balance.
- Actual closing balance.
- Variance.
- Shift closing approval.
- Cashier reconciliation.
- Shift report.

Use a clear cash reconciliation formula based on opening balance, recorded cash movements, and closing cash.

Restrict users to their authorized registers.

Maintain an audit trail of cash corrections and approvals.

---

# 15. Accounting and Finance

Implement a structured accounting module.

Support:

- Chart of accounts.
- General journal.
- Double-entry accounting.
- Accounts receivable.
- Accounts payable.
- Customer receipts.
- Supplier payments.
- Operating expenses.
- Cash and bank accounts.
- Journal entries.
- Trial balance.
- General ledger.
- Profit and loss statement.
- Balance sheet.
- Cash flow reporting.
- Period closing.
- Financial adjustments.

Accounting rules:

1. Every posted journal entry must balance: total debits equal total credits.
2. Posted entries must not be silently edited or deleted.
3. Corrections must use reversals and replacement entries.
4. Define and document when a sale, purchase, refund, and payment generate journal entries.
5. Reconcile inventory valuation with the accounting ledger.
6. Track accounts receivable and payable through proper ledger transactions.
7. Prevent unauthorized backdated entries or postings to closed periods.
8. Record the source transaction for each generated journal entry.
9. Prevent duplicate posting of the same source transaction.
10. Use database transactions to maintain consistency.

Provide configurable account mappings for:

- Cash.
- Bank.
- Sales revenue.
- Sales returns.
- Inventory.
- Cost of goods sold.
- Accounts receivable.
- Accounts payable.
- Tax payable.
- Discounts.
- Operating expenses.

Document the accounting assumptions and obtain qualified local accounting review before using financial reports for statutory purposes.

---

# 16. Expense Management

Create an expense module with:

- Expense category.
- Description.
- Amount.
- Date.
- Payment method.
- Branch.
- Supplier or payee where applicable.
- Attachment or receipt.
- Created by.
- Approval status.
- Notes.

Features:

- Expense creation.
- Approval workflow.
- Expense payment.
- Filtering and reporting.
- Category-wise expense reports.
- Branch-wise expense reports.
- Audit history.

Approved and paid expenses must be reflected in the accounting system according to the configured accounting policy.

---

# 17. Returns, Damages, Recalls, and Quarantine

Create workflows for products that cannot be sold.

Features:

- Expired stock quarantine.
- Damaged stock.
- Suspected counterfeit stock.
- Supplier recall.
- Product quality issue.
- Investigation and resolution.
- Authorized write-off.
- Supplier return.
- Destruction or disposal records where applicable.

Prevent quarantined products from being sold.

Track affected batches, quantities, reasons, approvals, and final disposition.

Support recall searches that identify affected inventory and relevant transaction records.

---

# 18. Reports and Analytics

Create professional reporting screens with date filters, branch filters, exports, and appropriate permission checks.

Dashboard KPIs:

- Today's sales.
- Sales this month.
- Purchase totals.
- Gross profit estimate.
- Operating expenses.
- Net profit estimate.
- Current inventory value.
- Low-stock products.
- Expired stock.
- Near-expiry stock.
- Outstanding customer balances.
- Outstanding supplier balances.
- Best-selling products.
- Recent transactions.

Reports:

### Sales

- Daily sales.
- Weekly sales.
- Monthly sales.
- Annual sales.
- Product-wise sales.
- Category-wise sales.
- Cashier-wise sales.
- Branch-wise sales.
- Payment method summary.
- Sales returns.
- Discount report.
- Tax report.

### Inventory

- Current stock.
- Stock valuation.
- Batch expiry.
- Stock adjustments.
- Stock movement.
- Stock transfer.
- Damaged stock.
- Inventory reconciliation.
- Fast- and slow-moving products.

### Purchasing

- Purchase summary.
- Supplier-wise purchases.
- Purchase returns.
- Purchase payment history.
- Outstanding supplier invoices.

### Financial

- Income and expenses.
- Gross profit.
- Net profit.
- Accounts receivable.
- Accounts payable.
- Customer statements.
- Supplier statements.
- General ledger.
- Trial balance.
- Profit and loss.
- Balance sheet.
- Cash flow.

Clearly distinguish sales revenue, gross profit, operating profit, and cash flow. Do not calculate net profit by simply subtracting purchases from sales without accounting for inventory, returns, expenses, and other applicable adjustments.

Use efficient database queries and indexes. Avoid N+1 query problems.

For large reports, use pagination, background exports, or streaming where appropriate.

---

# 19. Notifications and Automated Tasks

Implement notifications for:

- Low-stock products.
- Expiring batches.
- Expired stock.
- Supplier payment due dates.
- Customer payment reminders.
- Pending purchase approvals.
- Stock transfer approvals.
- Large stock adjustments.
- Cash closing discrepancies.
- Recall events.

Support in-app notifications initially.

Provide an architecture for email and other notification channels where configured.

Use Laravel scheduled tasks for recurring checks.

Avoid duplicate alerts. Record notification status and support retries for failed background jobs.

Document how to configure the scheduler and queue worker.

---

# 20. System Settings

Create a settings module for:

- Business name.
- Logo.
- Address and contact details.
- Default currency.
- Business timezone.
- Date and time formats.
- Invoice format.
- Invoice numbering rules.
- Tax configuration.
- Discount limits.
- Negative stock policy.
- Expiry warning intervals.
- Inventory valuation method.
- Payment methods.
- Return policies.
- Credit sales settings.
- Branch-specific settings.
- Receipt footer.
- Print settings.
- Notification preferences.

Use sensible initial defaults, but avoid hard-coding assumptions that should vary by business or jurisdiction.

For a Pakistan deployment, make PKR, configurable tax settings, and local date/number formatting available. Do not assume one tax rate or one legal prescription policy applies to every pharmacy.

---

# 21. User Interface and Experience

Build a polished, professional ERP interface.

Design characteristics:

- Clean, modern pharmacy dashboard.
- Consistent typography.
- Professional spacing.
- Responsive layout.
- Accessible form controls.
- Clear status badges.
- Appropriate loading indicators.
- Confirmation dialogs for destructive or irreversible actions.
- Empty states and helpful error messages.
- Keyboard-friendly forms.
- Consistent page headers and breadcrumbs.
- Reusable data tables.
- Search, sorting, pagination, and filters.
- Dark mode where practical.
- Mobile and tablet support.

Create the following primary navigation:

1. Dashboard.
2. POS.
3. Medicines.
4. Inventory.
5. Batches and Expiry.
6. Stock Transfers.
7. Suppliers.
8. Purchases.
9. Sales.
10. Sales Returns.
11. Customers.
12. Prescriptions.
13. Cash Registers.
14. Expenses.
15. Accounting.
16. Reports.
17. Notifications.
18. Users and Roles.
19. Branches and Warehouses.
20. Settings.
21. Audit Logs.

Render menu items according to permissions.

Do not create decorative controls that do nothing. Every visible action must work, or it must be clearly marked as unavailable and documented.

Use server-side pagination for large datasets.

Prevent accidental double submissions and clearly show validation errors.

---

# 22. Database Design Requirements

Design a normalized, maintainable relational schema.

At minimum, evaluate the need for these tables:

### Identity and access

- users
- roles
- permissions
- role_user or equivalent assignments
- permission_role or equivalent assignments
- user_branch
- user_warehouse
- user_preferences

### Organization

- branches
- warehouses
- warehouse_locations
- cash_registers
- cash_shifts

### Product catalog

- medicines
- categories
- manufacturers
- brands
- units
- tax_categories
- medicine_barcodes
- supplier_medicines

### Inventory

- medicine_batches
- inventory_balances
- stock_movements
- stock_adjustments
- stock_adjustment_items
- stock_transfers
- stock_transfer_items
- inventory_reservations where needed
- stock_counts
- stock_count_items
- quarantined_stock or an equivalent controlled stock status model

### Purchasing

- suppliers
- purchase_orders
- purchase_order_items
- goods_receipts
- goods_receipt_items
- supplier_bills
- supplier_bill_items
- purchase_returns
- purchase_return_items
- supplier_payments

### Sales

- customers
- sales
- sale_items
- sale_item_batches
- payments
- payment_allocations where needed
- sales_returns
- sales_return_items
- refunds
- parked_sales where appropriate

### Prescriptions

- prescriptions
- prescription_items
- dispensing_records
- prescription_attachments where applicable

### Accounting

- accounts
- journal_entries
- journal_lines
- fiscal_periods
- accounting_periods or equivalent
- expense_categories
- expenses
- bank_accounts
- reconciliation records where needed

### Platform

- audit_logs
- notifications
- system_settings
- import_batches
- export_jobs
- idempotency_records where needed

This is a proposed inventory, not a requirement to create redundant tables. Consolidate or separate entities according to clear domain rules.

For every table, define:

- Primary key.
- Foreign keys.
- Required and optional fields.
- Data types.
- Indexes.
- Unique constraints.
- Deletion behavior.
- Relationships.
- Business invariants.

Use soft deletion only where appropriate. Never cascade-delete completed financial transactions or inventory history.

For money, use DECIMAL fields and a documented rounding policy.

For quantity, choose precision appropriate to supported products and units.

For identifiers, use consistent conventions.

Use immutable transaction references for completed sales, receipts, stock movements, and journal entries.

Create an ERD in Mermaid format and document important relationships.

---

# 23. API and Validation

Even when using Inertia.js, design reusable, validated backend operations.

Where API endpoints are needed:

- Use consistent REST conventions.
- Use named routes.
- Return predictable response structures.
- Validate all inputs.
- Apply authentication and authorization.
- Paginate collection responses.
- Support appropriate filters.
- Use API resources for consistent serialization.
- Avoid exposing internal stack traces.
- Apply throttling to sensitive endpoints.

Document the APIs using OpenAPI where appropriate.

Never expose confidential patient data, credentials, secrets, or unnecessary internal fields.

---

# 24. Security Requirements

Implement secure application defaults.

Include:

- CSRF protection.
- Output escaping.
- SQL injection protection through parameterized database operations.
- Authorization checks on every protected operation.
- Mass-assignment protection.
- Upload validation.
- Safe file storage.
- Login throttling.
- Secure password hashing.
- Sensitive-data minimization.
- Audit logging.
- Secure environment configuration.
- Error handling without leaking secrets.
- Dependency vulnerability review.
- Secure backup handling.
- Protection against insecure direct object references.
- Rate limiting on sensitive operations.

Do not place API keys, database credentials, or production secrets in source control.

Provide an `.env.example` without real credentials.

Never log passwords, access tokens, full payment card data, or unnecessary prescription information.

---

# 25. Performance and Scalability

Implement:

- Database indexes based on real query patterns.
- Eager loading for related models.
- Efficient aggregate queries.
- Server-side pagination.
- Cached reference data where safe.
- Background processing for large exports and notifications.
- Optimized dashboard queries.
- Transactional stock updates.
- Appropriate database connection configuration.
- Configurable queue drivers.

Do not cache sensitive, user-specific data across authorization boundaries.

Do not use cache as the only source of truth for stock or accounting balances.

Create seed data for a realistic development environment without including real patient information.

---

# 26. Automated Testing

Use Pest or PHPUnit.

Write tests for:

### Authentication and permissions

- Login and logout.
- Unauthorized requests.
- Role and permission enforcement.
- Branch isolation.
- Record ownership and access boundaries.

### Products and inventory

- Product CRUD.
- Barcode uniqueness.
- Batch creation.
- Stock receiving.
- Stock adjustments.
- Expiry restrictions.
- Quarantine restrictions.
- FEFO batch allocation.
- Inventory reconciliation.
- Concurrent stock deduction where supported by the test database.

### Sales

- Successful checkout.
- Multiple items.
- Multiple batches.
- Discounts and tax.
- Split payments.
- Insufficient stock.
- Duplicate checkout prevention.
- Failed transaction rollback.
- Invoice numbering.
- Cashier restrictions.

### Returns

- Partial returns.
- Full returns.
- Excess return rejection.
- Duplicate return prevention.
- Refund calculations.
- Quarantine of unsuitable returned products.

### Purchasing

- Purchase orders.
- Partial receipts.
- Batch creation.
- Supplier bills.
- Supplier payments.
- Purchase returns.

### Accounting

- Balanced journal entries.
- Source transaction linkage.
- Duplicate posting prevention.
- Journal reversal.
- Closed-period restrictions.
- Financial report calculations.

### Other modules

- Prescription authorization.
- Cash reconciliation.
- Expense approval.
- Audit logs.
- Notifications.
- Import validation.
- Report permissions.

Test important workflows end-to-end.

Do not rely only on tests that assert a page loads.

Use isolated test databases and test factories. Never run destructive test setup against production databases.

Fix failing tests before marking a feature complete.

---

# 27. Development Workflow

Follow this implementation sequence.

## Phase 1: Repository inspection and foundation

- Inspect all existing files.
- Identify the Laravel and PHP versions.
- Inspect frontend tooling and installed dependencies.
- Check environment configuration and database connectivity.
- Identify existing tests and coding conventions.
- Create `docs/PROJECT_PLAN.md`.
- Create `docs/ARCHITECTURE.md`.
- Create `docs/DATABASE_ERD.md`.
- Create `docs/IMPLEMENTATION_STATUS.md`.

Do not delete working code simply to match a preferred folder structure.

## Phase 2: Authentication and organization

Implement:

- Authentication.
- Roles and permissions.
- Branches.
- Warehouses.
- User assignments.
- System settings.
- Audit logging foundation.

## Phase 3: Product catalog and inventory

Implement:

- Medicines.
- Categories.
- Manufacturers.
- Barcodes.
- Batches.
- Stock balances.
- Stock movements.
- Expiry alerts.
- Inventory reports.

## Phase 4: Suppliers and purchasing

Implement:

- Suppliers.
- Purchase orders.
- Goods receipts.
- Supplier bills.
- Supplier payments.
- Purchase returns.

## Phase 5: POS and sales

Implement:

- Cash registers.
- Cashier shifts.
- POS interface.
- Server-side checkout.
- Payments.
- Invoices.
- Sales history.
- Sales returns and refunds.

## Phase 6: Customers and prescriptions

Implement:

- Customer accounts.
- Customer statements.
- Prescription records.
- Pharmacist review.
- Dispensing history.
- Applicable authorization rules.

## Phase 7: Finance and accounting

Implement:

- Expenses.
- Chart of accounts.
- Journal entries.
- Accounts payable and receivable.
- General ledger.
- Trial balance.
- Financial statements.
- Cash reconciliation.

## Phase 8: Advanced ERP features

Implement:

- Multi-branch transfers.
- Stock counts.
- Quarantine and recalls.
- Notifications.
- Bulk imports and exports.
- Advanced reports.

## Phase 9: Quality assurance

- Run migrations on a clean test database.
- Run seeders.
- Run backend tests.
- Run frontend type checks.
- Run frontend linting.
- Run production builds.
- Fix errors.
- Test critical workflows manually or through browser automation if available.
- Review security and performance.

## Phase 10: Deployment and documentation

Create:

- `README.md`.
- `.env.example`.
- Installation instructions.
- Database setup instructions.
- Seeder instructions.
- Test instructions.
- Queue worker instructions.
- Scheduler configuration.
- Production deployment guide.
- Backup and restore guide.
- User roles and permissions guide.
- POS user guide.
- Accounting assumptions.
- Known limitations and future improvements.

---

# 28. Required Deliverables

The completed repository must contain:

1. Working Laravel application.
2. Functional Vue frontend.
3. Database migrations.
4. Model relationships.
5. Factories and seeders.
6. Authentication and permissions.
7. All implemented business modules.
8. Validated business operations.
9. Responsive user interfaces.
10. Automated tests.
11. Database ERD.
12. Architecture documentation.
13. API documentation where applicable.
14. Installation guide.
15. Production deployment guide.
16. Backup and recovery instructions.
17. Development credentials or demo accounts generated only through local seeders.
18. A clear implementation status report.

Do not claim that unimplemented modules are complete.

Do not replace working features with placeholder pages.

Do not generate dummy financial figures to make the dashboard look populated. Dashboard metrics must come from real database queries; development seeders may generate clearly fictional test transactions.

---

# 29. Definition of Done

A module is complete only when:

- Database migrations work.
- Model relationships are correct.
- Backend business logic works.
- Validation works.
- Authorization is enforced.
- The frontend is connected to the backend.
- Create, read, update, and applicable delete/archive workflows work.
- Loading, error, and empty states exist.
- Related records remain consistent.
- Critical workflows have automated tests.
- Documentation is updated.

The core application is ready for controlled acceptance testing only when the critical workflows work together.

At minimum, demonstrate these end-to-end scenarios:

### Scenario A: Receive stock and sell medicine

1. Create a supplier.
2. Create a medicine.
3. Create a purchase order.
4. Receive stock with batch and expiry information.
5. Verify stock balances and stock movement records.
6. Sell the medicine through POS.
7. Verify the invoice, payment, batch deduction, and accounting entries.

### Scenario B: Handle a return

1. Find a completed sale.
2. Return part of the purchased quantity.
3. Validate the remaining returnable quantity.
4. Record the return and refund.
5. Quarantine unsuitable medicine.
6. Verify stock balances and accounting effects.

### Scenario C: Expiry management

1. Create multiple batches with different expiry dates.
2. Run the expiry alert process.
3. Verify eligible batch selection.
4. Ensure expired stock cannot be sold.
5. Verify near-expiry and expired-stock reports.

### Scenario D: Accounting reconciliation

1. Post a sale and its payment.
2. Receive purchased inventory and record the supplier liability.
3. Record a supplier payment and operating expense.
4. Verify balanced journal entries.
5. Generate the trial balance and financial statements.
6. Reconcile relevant inventory, cash, receivable, and payable balances.

### Scenario E: Permissions and branches

1. Create two branches.
2. Create users with different branch assignments.
3. Attempt to access unauthorized branch data.
4. Verify that both backend and frontend enforce the intended restrictions.
5. Verify that sensitive changes are audited.

---

# 30. Instructions for Working Autonomously

Start by inspecting the repository and available environment.

Then:

1. Summarize the existing project and any compatibility issues.
2. Write the architecture and implementation plan.
3. Implement the first functional phase.
4. Run its migrations and tests where the environment permits.
5. Fix errors.
6. Update the implementation status.
7. Continue to the next phase.

Use the available terminal, file-editing, and testing tools.

Do not ask for approval for every file or routine implementation decision.

If a required external service is unavailable, provide a working local alternative where possible and document the limitation.

If the entire system cannot be completed in one session, prioritize a complete, integrated vertical slice instead of generating thousands of disconnected files. Then continue with the remaining modules in dependency order.

Never report that a command, migration, test, build, or deployment succeeded unless it actually ran successfully.

At the end of each phase, report:

- Features implemented.
- Important files created or changed.
- Database changes.
- Commands executed.
- Tests and build results.
- Known issues.
- Next implementation phase.

Begin now by inspecting the repository and implementing the application systematically.

The final objective is a secure, maintainable, well-tested Pharmacy Management ERP that can be configured for real pharmacy operations and prepared for production after appropriate business, security, accounting, and regulatory acceptance testing.