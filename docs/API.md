# HTTP interface

The first release uses Laravel session authentication and Inertia responses. These are browser application endpoints, not a public token API. Writes require CSRF protection. Request validation errors use Laravel's standard 422 JSON error shape (`message`, `errors`) for JSON clients, or the Inertia error bag for browser navigation. Forbidden access returns 403; inaccessible/mismatched records return 403 or 404. Mutation success redirects to an Inertia page. Use named routes from `php artisan route:list`.

| Method / path | Permission / operation |
| --- | --- |
| POST /login, /logout | Throttled credentials / invalidate session |
| POST /forgot-password, /reset-password | Throttled password broker |
| POST /branch | Change only to an assigned branch |
| GET/POST /medicines; PUT /medicines/{id} | View/create/update medicine with unique SKU/barcode |
| GET/POST /suppliers or /customers; PUT /{module}/{id} | Permission-checked shared contact master |
| POST /purchases | `purchases.create`: supplier_id and items[{medicine_id, quantity, unit_cost}] |
| POST /purchases/{id}/returns | `purchases.return`: UUID request_key, reason, settlement (`credit`/`refund`), method?, items[{batch_id,stock_bucket,quantity}]; supplier refunds also require `accounting.manage` |
| POST /purchases/{id}/receive | `inventory.receive`: warehouse_id, UUID request_key, items[{item_id,quantity,lot_number,expires_at?}] |
| GET /catalog-search?q= | `purchases.create`: up to 20 active catalog matches |
| GET /pos/search?warehouse_id=&q= | `sales.create`: up to 30 products including eligible stock aggregate |
| GET /pos/customers?q= | `sales.create`: up to 20 customer names/IDs |
| POST /checkout | `sales.create`: warehouse_id, customer_id?, allow_partial?, UUID request_key, items[{medicine_id,quantity,unit_discount?}], payments[{method,amount}] |
| POST /sales/{id}/payments | `sales.create` and invoice access: UUID request_key, amount, method; reduces receivable |
| POST /sales/{id}/returns | `sales.refund`: UUID request_key, reason, items[{allocation_id,quantity}] |
| POST /inventory/{id}/quarantine | `inventory.adjust`: reason; entire remaining balance |
| GET /supplier-payments?supplier_id= | `accounting.view`: branch supplier balances, purchase balances and payment history |
| POST /supplier-payments | `accounting.manage`: supplier_id, purchase_order_id?, amount, method, UUID request_key |
| POST /expenses | `accounting.manage`: category, description, amount, method, UUID request_key |
| GET /accounting | `accounting.view`: accounting report links and supplier payments link |
| GET /accounting/{report} | `accounting.view`: trial-balance, operating-result, journal-entries (optional from/to inclusive dates), balance-sheet (optional as_of date, cumulative), or current stock-reconciliation |
| GET /exports/sales | `reports.view`: streaming branch sales CSV |
| POST /administration/users | `users.manage`: validated identity, role_ids, branch_ids, active and optional password |
| POST /administration/branches | `settings.manage`: name, code, timezone and initial warehouse |

Supported payment methods: cash/card/bank/wallet; customer cash refunds follow receivable reduction; purchase-return supplier refunds can use the confirmed payment method. Decimal values should be strings with at most two fractional digits. Quantity is a positive integer. Unit price, cart totals, cost, branch assignment and permissions are determined server-side, not accepted from checkout payload. Checkouts have request-hash comparison and reject reuse of a key with changed content. Receipt/refund/payment/expense retries return their original transaction for the same source/user/key; clients must use new UUIDs for new operations.

Lists are server paginated, generally 20–30 records. The frontend looks up large medicine/customer collections with bounded search rather than embedding entire catalogs. Supplier options on purchase creation are currently unpaginated and should be converted to bounded lookup before a very large supplier deployment.

Partial/unpaid sales require `allow_partial=true` and a named active customer. `payments` must be an array, possibly empty for an unpaid sale. Later receipts cannot overpay the remaining invoice balance. See [payment and return behavior](PAYMENTS_AND_PURCHASE_RETURNS.md).
