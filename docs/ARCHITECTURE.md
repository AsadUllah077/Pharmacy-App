# Architecture

Laravel 12 owns authorization, validation and all business calculations. Inertia serves Vue 3 pages using TypeScript and Tailwind. SQLite is the local development alternative; production targets MySQL 8/PostgreSQL with row locks. UTC storage; business dates use the configured timezone (Asia/Karachi default). PKR is a configurable display default. No jurisdiction-specific drug schedule or tax rule is assumed.

HTTP controllers orchestrate Form Requests and focused transactional services. Role permissions are enforced server-side. A user must be active and assigned to the requested branch; warehouse ownership is checked separately. Branch selection is session state validated on every request. Master medicines are shared, while all financial and inventory transactions are branch scoped.

Quantities are whole dispensing units in this slice. Monetary columns are DECIMAL(14,2); services convert decimal strings to integer minor units using BCMath and round tax half up per dispensing unit. Browser prices and totals are ignored. Inventory uses FEFO physical dispensing and specific batch acquisition cost for accounting valuation; this is documented explicitly rather than claiming weighted-average/FIFO valuation.

Purchases create orders, receipts and supplier liabilities independently of payments. Checkout locks the branch (invoice sequence and idempotency serialization), then eligible batches in stable product/expiry order. Conditional stock updates also prevent overselling. Completed sales, movements and journals have no edit/delete routes. Returns retain original allocation, price and tax; stock is quarantined by default and remains an inventory asset until a subsequent write-off workflow. Cash refunds reverse revenue/tax/COGS and reinstate the asset. Each journal source is unique and balanced. All effects occur in one database transaction.

SQLite serializes writes and does not implement row-level locks; production concurrency acceptance testing must use the target MySQL/PostgreSQL engine. No browser offline stock authority. No external payment gateway or legal compliance certification is implied.
