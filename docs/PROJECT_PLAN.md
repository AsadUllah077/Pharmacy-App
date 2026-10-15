# Pharmacy ERP implementation plan

The repository was empty. Environment: Windows, PHP 8.2.4 with BCMath and SQLite/MySQL/PostgreSQL PDO, Composer 2.7.7, Node 24.21. Laravel 12 is selected for PHP 8.2 compatibility. The npm wrapper is broken; invoke `node "C:/Program Files/nodejs/node_modules/npm/bin/npm-cli.js"` on this workstation.

1. Foundation: Laravel, Vue 3/TypeScript/Inertia, authentication, branch assignments, role permissions, audit.
2. Catalog and inventory: medicines, barcode uniqueness, batches, immutable movements, expiry alerts.
3. Integrated purchasing: suppliers, orders, partial goods receipt, liability and payment.
4. Integrated sales: server pricing, FEFO, atomic stock deduction, split payments, idempotency, invoice, quarantined returns and refunds.
5. Finance: balanced source journals, trial balance, expenses and inventory reconciliation.
6. QA: isolated SQLite feature tests, typecheck, lint, build, dependency audit, operational documentation.
7. Further ERP scope: prescription review/dispensing, cash shifts, transfer approvals, stock counts, recall disposition, advanced accounting and reporting, imports and backups automation. Report actual status rather than marking planned capabilities complete.

Prioritize one usable integrated workflow over disconnected module placeholders. Keep the application local: the requested PHP backend requires PHP hosting rather than Sites Worker hosting.
