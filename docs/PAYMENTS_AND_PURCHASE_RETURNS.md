# Payments and purchase returns

Implemented 16 October 2026. The updated PDF is in `output/pdf/PharmaFlow_User_Training_and_Data_Entry_Manual.pdf`; pages 19-21 cover these workflows.

## Supplier payments

Open **Supplier payments** in navigation, choose a supplier and click **View supplier**. Enter any positive amount up to the outstanding received-goods balance, choose its actual payment method and click **Record supplier payment**. Payments entered here allocate to the supplier's oldest outstanding purchases in the selected branch. Open a purchase's **Supplier payment** section to pay that order specifically. Each purchase shows received value, returned value, payments, applied credits, remaining amount and payment status, plus payment history. No advances against unreceived orders are entered through this mechanism.

Existing supplier payments are preserved and allocated FIFO to received orders by the migration; their financial journals are not reposted. New supplier payment retries reject changed payloads for the same request key.

## Partial sales and collections

At POS, select a named customer and enable **Allow partial / unpaid sale**. Enter the actual initial payment, or leave it blank for an entirely unpaid invoice. The entire sale still issues an invoice and deducts stock. The unpaid portion is posted to Customer receivables, account 1100. Walk-in balances and implicit underpayment are rejected.

Open the invoice from **Sales & returns** and use **Receive customer payment** for later installments. Payments cannot exceed its current remaining balance. Each collection debits the selected payment account and credits receivables; it does not post revenue or deduct stock again. Invoice details and the sales list show remaining balance and payment status. Invoice printouts include payment dates, total payments, returned value, cash refunds and balance remaining.

Customer returns first reduce outstanding receivables, then refund any return value beyond that amount in cash. Returned units enter quarantine. This prevents cash refunds of amounts that were never paid. The service computes the balance under the same branch lock as collections and checkout.

## Purchase returns

On the original purchase, use **Return goods to supplier**. Select quantities against received batches and their available or quarantine bucket. Return value uses original batch cost; quantity cannot exceed current stock or original received quantity less previous purchase returns. One bucket per batch can be returned in each request. Use separate returns to remove both buckets of one batch.

Return value reduces this purchase's unpaid balance first. For the paid portion, choose **Keep as supplier credit**, or **Supplier refund received** and its actual payment method if money has already been received. Supplier credits automatically apply to other or future received purchases for the same supplier and branch, oldest first. Credit allocations are persisted, so they cannot be consumed twice. A fully returned order is shown as Returned in its payment status; its receiving status continues to describe the original receipts, and its ordered quantity is not reopened.

Purchase returns credit Inventory at original cost and debit Accounts payable for the liability reduction/credit, or Cash/Bank for a supplier refund. Supplier credits remain debit balances in the supplier's payable account until allocated; allocation between the same supplier's purchases needs no second financial journal. The stock movement records a negative available or quarantine delta for reconciliation. Purchase detail retains return references, reasons, values, credits and refund history.

## Access and integrity

- Viewing supplier balances uses `accounting.view`; posting supplier payments uses `accounting.manage`.
- Posting purchase returns uses `purchases.return`, granted to existing/new Branch Manager, Inventory Manager, Purchasing Officer and Accountant roles, as well as wildcard owner/admin roles.
- Recording a supplier refund additionally requires `accounting.manage`.
- Partial checkout and later invoice collections use `sales.create`; individual invoice viewing and authorized branch membership are also enforced.
- Transactions lock the branch, validate source ownership, post source records/stock/journals atomically and protect duplicate requests. New collections, supplier payments and purchase returns reject reuse of a request key with changed content.
- Automated tests run only in isolated in-memory SQLite and verify balanced journals, receivable/payable reconciliation, quantities, credits, duplicate/overpayment protection and authorization. Production concurrency still needs qualification against the chosen production database.

## Current limits

Customer credit limits, due dates/aging, opening balances, supplier advances, customer statements and a separate payout of previously issued supplier credit are not implemented. A supplier refund may be recorded at the time of purchase return; do not claim it was received if settlement is still pending. General journal correction, purchase order cancellation and noncash customer refunds remain unavailable.
