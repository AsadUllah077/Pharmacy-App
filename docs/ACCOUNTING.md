# Accounting policy in the integrated slice

All calculations use integer minor units (BCMath conversion), with DECIMAL(14,2) storage. Sale tax is rounded half up **per dispensing unit**, then multiplied by quantity. Unit discount is retained so partial refunds have deterministic amounts without rounding drift. The allowed unit discount defaults to 20% and requires `sales.discount`. Tax is configured per medicine in basis points; 100 basis points = 1%. Initial tax is zero. No statutory tax classification is inferred.

FEFO controls physical dispensing. Accounting uses specific batch acquisition cost from the actual allocations. This is not a claim of FIFO or weighted-average valuation. Whole dispensing units are the supported quantity precision. No package conversion or fractional quantities.

| Event | Debit | Credit |
| --- | --- | --- |
| Goods receipt | Inventory at received units × agreed unit cost | Accounts payable |
| Supplier payment | Accounts payable | Cash or bank/clearing |
| Completed sale | Cash/bank/clearing retained plus customer receivables for unpaid portion | Net sales revenue, tax payable |
| Cost of sale | Cost of goods sold | Inventory |
| Customer return | Sales returns, tax payable | Receivables reduced first, then cash for excess |
| Later invoice collection | Cash/bank/clearing | Customer receivables |
| Purchase return | Accounts payable or supplier refund received | Inventory at original cost |
| Returned units | Inventory at original allocated cost | Cost of goods sold |
| Paid operating expense | Operating expenses | Cash or bank/clearing |

Quarantine remains an inventory asset; a refund reinstates original cost into quarantine rather than sellable stock. An authorized impairment/write-off process is required before relying on quarantine carrying values in final financial statements. Such disposition is not implemented in this slice.

Receipts accrue the supplier liability without requiring payment. The order itself has no accounting effect. Separate supplier invoice matching, purchase tax/discount/freight allocation and purchase freight/tax allocation remain pending; batch-based purchase returns now reverse original cost. Cash/card/bank/wallet sales are fully settled; explicit partial/unpaid sales require named customers, with later collections posted against receivables. Noncash methods post to one bank/clearing account; actual settlement/reconciliation is external and pending.

The trial balance sums posted journal lines with posting-date filters. The operating result is net sales less returns, COGS and paid operating expenses; it is not cash flow or a statutory net-profit report. Dashboard sales are gross completed invoice totals before refunds and are labeled accordingly. Inventory valuation includes quarantine and expired assets; expired quantities are excluded from sellable stock.

Posting and source updates are atomic. Unique source references prevent duplicate posting. There is no journal edit/delete endpoint. Automatic return journals are linked reversals of the affected sale quantities. General-purpose journal reversal/replacement, period close, receivable/payable aging, opening balances, fiscal years and cash-flow statements are not implemented. No backdated entry input is exposed; posting dates use the current business date. Ledger correctness and valuation policy require qualified accounting acceptance before statutory use, as requested in the project brief.

Supplier return credits automatically allocate to outstanding or future received purchases, oldest first, within supplier and branch. No second journal is needed for allocations within one supplier payable. See [settlement details](PAYMENTS_AND_PURCHASE_RETURNS.md).

## Balance sheet

Accounting > Balance sheet reports all posted balances for the selected branch through an inclusive as-of business date (default: today in Asia/Karachi). Assets use debit less credit; liabilities and equity use credit less debit. All unclosed income, contra-income and expense balances through the date form accumulated earnings/loss within equity, including prior years. It reports assets, liabilities, equity, their totals and the exact difference in minor units; it does not invent opening balances or capital. Negative balances remain visible in parentheses. This is a ledger-based position report; account reclassification, opening balances, period closing and complete statutory disclosures are not provided.
