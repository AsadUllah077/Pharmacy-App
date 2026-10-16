# PharmaFlow Pharmacy ERP

A working Laravel 12 / Vue 3 / TypeScript / Inertia application with database-backed pharmacy purchasing, inventory, POS, quarantined returns and double-entry accounting. This is an integrated initial release, **not completion of the entire ERP brief**. See [implementation status](docs/IMPLEMENTATION_STATUS.md) for remaining modules and acceptance limits.

The application is running in this session at **http://127.0.0.1:8085**.

## Run locally

PHP 8.2+ with BCMath and PDO; Composer; Node 22+.

```sh
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
php artisan db:seed
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000. Local seed accounts: `admin@example.test` or `cashier@example.test`, password `ChangeMe123!Local`. Local seeders create fictional catalog records, two branches and **zero stock/sales**. Start with a purchase order and goods receipt. These accounts are not seeded in production. The current workstation has dependencies installed, migrations applied, seed data and compiled assets.

The npm wrapper on this workstation is broken. Replace `npm` with `node 'C:\Program Files\nodejs\node_modules\npm\bin\npm-cli.js'` in PowerShell. PHP 8.2 compatibility is why Laravel 12 was selected ([Laravel release requirements](https://laravel.com/docs/13.x/releases)).

## Implemented workflow

- Login/logout, password recovery/change, throttling, active accounts, seeded roles, backend permissions, branch assignments, access revocation and audit records.
- Catalog create/edit/archive with unique barcode and SKU; supplier/customer management.
- Purchase orders and partial receiving, batch-based purchase returns, supplier credits/refunds, and a dedicated Supplier payments page with order allocations and installments.
- FEFO checkout with expired/quarantined stock exclusion, row locks, conditional deductions, authoritative integer money calculations, split payments, named-customer partial/unpaid sales, later invoice collections, cash change and idempotent invoice creation.
- Sales history, printable invoices, partial returns limited to original allocations, cash refunds and quarantine movements.
- Paid expenses, source-linked balanced journals, trial balance, balance sheet with as-of dates, operating result, stock reconciliation and CSV sales export.
- Real dashboard aggregates, responsive permission-aware navigation, inventory filters, scheduled deduplicated expiry/low-stock alerts and user/branch administration.

## Verify

```sh
php artisan test
php vendor/bin/pint --test
npm run typecheck
npm run lint
npm run format:check
npm run build
composer audit
npm audit
npm run test:ui
```

PHPUnit is forced to an isolated in-memory SQLite database. It never migrates the configured application database. Tests cover both service-level failure paths and integrated HTTP workflows. Production simultaneous-writer behavior still needs MySQL/PostgreSQL acceptance testing; SQLite cannot validate row locks. Playwright checks responsive pages in light/dark themes, theme persistence, mobile navigation and print styling using installed Google Chrome. It starts a local server on 8085 if needed and uses the local seeded administrator. Run it only against a local seeded development instance. Hardware scanner/printer checks remain manual.

Use the sun/moon button in the top bar or login screen to switch themes. Your account → Appearance offers Light, Dark or Use system setting. The preference is saved on the current device and applied before rendering to avoid a light flash. Printing always uses a light palette.

## Documentation

- [Architecture](docs/ARCHITECTURE.md), [plan](docs/PROJECT_PLAN.md), [schema and Mermaid ERD](docs/DATABASE_ERD.md)
- [Installation, queues, scheduler, deployment, backup and restore](docs/OPERATIONS.md)
- [POS and purchasing guide](docs/POS_USER_GUIDE.md), [roles and access](docs/ROLES.md)
- [Partial payments and purchase returns](docs/PAYMENTS_AND_PURCHASE_RETURNS.md)
- [Accounting assumptions](docs/ACCOUNTING.md), [HTTP interface](docs/API.md)
- [Implementation status and remaining scope](docs/IMPLEMENTATION_STATUS.md)

Business defaults are PKR and Asia/Karachi, with configurable tax per medicine. No prescription law or drug schedule is hard-coded. Restricted medicines are blocked until the prescription workflow is implemented. No application has been deployed or certified for statutory/accounting/regulatory use.
