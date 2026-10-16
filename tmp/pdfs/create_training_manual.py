from pathlib import Path
from html import escape
import re
from reportlab.pdfgen import canvas
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, KeepTogether
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'output/pdf/PharmaFlow_User_Training_and_Data_Entry_Manual.pdf'
pdfmetrics.registerFont(TTFont('Arial', 'C:/Windows/Fonts/arial.ttf'))
pdfmetrics.registerFont(TTFont('Arial-Bold', 'C:/Windows/Fonts/arialbd.ttf'))
pdfmetrics.registerFont(TTFont('Arial-Italic', 'C:/Windows/Fonts/ariali.ttf'))
pdfmetrics.registerFontFamily('Arial', normal='Arial', bold='Arial-Bold', italic='Arial-Italic')
NAVY = colors.HexColor('#173447')
TEAL = colors.HexColor('#087F82')
MUTED = colors.HexColor('#536573')
PALE = colors.HexColor('#EAF5F4')
LINE = colors.HexColor('#DCE5E9')
WIDTH, HEIGHT = A4
CONTENT = WIDTH - 96
styles = {
    'body': ParagraphStyle('body', fontName='Arial', fontSize=10, leading=14.5, textColor=NAVY, spaceAfter=7),
    'small': ParagraphStyle('small', fontName='Arial', fontSize=8.7, leading=12, textColor=MUTED, spaceAfter=5),
    'cell': ParagraphStyle('cell', fontName='Arial', fontSize=9, leading=12, textColor=NAVY),
    'th': ParagraphStyle('th', fontName='Arial-Bold', fontSize=9, leading=12, textColor=colors.white),
    'h1': ParagraphStyle('h1', fontName='Arial-Bold', fontSize=24, leading=29, textColor=NAVY, spaceAfter=9),
    'h2': ParagraphStyle('h2', fontName='Arial-Bold', fontSize=12, leading=17, textColor=TEAL, spaceBefore=9, spaceAfter=7),
    'kicker': ParagraphStyle('kicker', fontName='Arial-Bold', fontSize=9, leading=13, textColor=TEAL, spaceAfter=9),
    'cover': ParagraphStyle('cover', fontName='Arial-Bold', fontSize=37, leading=43, textColor=NAVY, spaceAfter=20),
}
story = []
sections = []

def para(text, style='body'):
    text = escape(text).replace('\n', '<br/>')
    text = re.sub(r'\*\*(.*?)\*\*', r'<b>\1</b>', text)
    return Paragraph(text, styles[style])

def p(text, style='body'):
    story.append(para(text, style))

def h(text):
    story.append(para(text, 'h2'))

def table(headers, rows, widths=None):
    data = [[para(x, 'th') for x in headers]]
    data += [[para(str(x), 'cell') for x in row] for row in rows]
    widths = [CONTENT * x for x in widths] if widths else [CONTENT / len(headers)] * len(headers)
    t = Table(data, colWidths=widths, repeatRows=1, hAlign='LEFT')
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), NAVY),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 9),
        ('RIGHTPADDING', (0, 0), (-1, -1), 9),
        ('TOPPADDING', (0, 0), (-1, -1), 7),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 7),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, colors.HexColor('#F4F7F9')]),
        ('LINEBELOW', (0, 0), (-1, 0), .5, NAVY),
        ('LINEBELOW', (0, 1), (-1, -1), .35, LINE),
    ]))
    story.extend([t, Spacer(1, 10)])

def callout(title, text):
    t = Table([[para(title, 'kicker')], [para(text)]], colWidths=[CONTENT])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), PALE), ('BOX', (0, 0), (-1, -1), .5, LINE),
        ('LEFTPADDING', (0, 0), (-1, -1), 13), ('RIGHTPADDING', (0, 0), (-1, -1), 13),
        ('TOPPADDING', (0, 0), (-1, 0), 11), ('BOTTOMPADDING', (0, -1), (-1, -1), 9),
    ]))
    story.extend([KeepTogether(t), Spacer(1, 10)])

def steps(items):
    for i, text in enumerate(items, 1):
        t = Table([[para(f'{i:02}', 'kicker'), para(text)]], colWidths=[29, CONTENT-29])
        t.setStyle(TableStyle([('VALIGN', (0,0), (-1,-1), 'TOP'), ('LEFTPADDING',(0,0),(-1,-1),0), ('RIGHTPADDING',(0,0),(-1,-1),4), ('BOTTOMPADDING',(0,0),(-1,-1),6)]))
        story.append(KeepTogether(t))

def page(number, title, subtitle):
    if story:
        story.append(PageBreak())
    sections.append((number, title))
    p(f'USER TRAINING  /  {number:02}', 'kicker')
    p(title, 'h1')
    p(subtitle, 'small')
    story.append(Spacer(1, 7))

class NumberedCanvas(canvas.Canvas):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.saved_pages = []
    def showPage(self):
        self.saved_pages.append(dict(self.__dict__))
        self._startPage()
    def save(self):
        total = len(self.saved_pages)
        for state in self.saved_pages:
            self.__dict__.update(state)
            self.setStrokeColor(LINE)
            self.line(48, 43, WIDTH-48, 43)
            self.setFont('Arial', 8)
            self.setFillColor(MUTED)
            self.drawString(48, 29, 'PHARMAFLOW  |  User training & data entry  |  16 October 2026')
            self.drawRightString(WIDTH-48, 29, f'{self._pageNumber:02} / {total:02}')
            if self._pageNumber > 1:
                self.setFillColor(TEAL)
                self.setFont('Arial-Bold', 8)
                self.drawString(48, HEIGHT-32, 'PHARMAFLOW / WORKING GUIDE')
                self.setStrokeColor(LINE)
                self.line(48, HEIGHT-39, WIDTH-48, HEIGHT-39)
            super().showPage()
        super().save()

# 01 - Cover
p('PHARMAFLOW  /  PHARMACY ERP', 'kicker')
story.append(Spacer(1, 50))
p('User training &\ndata entry manual'.replace('\n', ' '), 'cover')
p('A practical guide for cashiers, inventory staff, purchasing officers, accountants and administrators.')
story.append(Spacer(1, 22))
table(['LEARN THE WORKFLOW', 'CHECK THE RESULT'], [
    ('Create records and receive stock', 'Confirm quantities, batches and supplier liability'),
    ('Sell, print and refund', 'Check invoices, cash change and quarantine'),
    ('Record payments and expenses', 'Review separate accounting report pages'),
], [.50, .50])
story.append(Spacer(1, 22))
callout('TRAINING EDITION - 16 OCTOBER 2026', 'Updated for supplier installments, partial/unpaid sales, payment collection, purchase returns and the separate accounting report pages. Currency: PKR. Business timezone: Asia/Karachi.')
h('Use this manual during a guided practice session')
p('Follow the screen instructions, then complete the exercise on pages 16-17. The examples use fictional products and contacts. Practice in a dedicated training copy of the application; every confirmed receipt, sale, refund, payment and expense creates a real record in the selected database.')
p('This manual teaches software operation and data entry. Product classification, pricing, tax and business decisions must follow your organization\'s approved instructions.', 'small')

# 02 - Contents
page(2, 'Find the right workflow', 'Use the page number below to jump to a task while training.')
table(['PAGE', 'TASK', 'MAIN USER'], [
    ('03', 'Sign in, select a branch and prepare', 'All users'),
    ('04', 'Enter and maintain medicine records', 'Catalog / inventory staff'),
    ('05', 'Enter suppliers and customers', 'Purchasing / authorized staff'),
    ('06', 'Create a purchase order', 'Purchasing officer'),
    ('07', 'Receive goods and create batch stock', 'Receiving staff'),
    ('08', 'Enter and complete a POS sale', 'Cashier'),
    ('09', 'Find invoices, print and process returns', 'Cashier / refund-authorized staff'),
    ('10', 'Check inventory and quarantine a batch', 'Inventory staff'),
    ('11', 'Pay suppliers and record paid expenses', 'Accountant'),
    ('12', 'Read the separate accounting reports', 'Accountant / manager'),
    ('13', 'Understand automatic accounting entries', 'Accountant / trainer'),
    ('14', 'Create users, roles and branch access', 'Administrator'),
    ('15', 'Daily checks and common entry problems', 'All users'),
    ('16-17', 'Complete the practice exercise and checks', 'Trainee + trainer'),
    ('18', 'Training sign-off and feature boundaries', 'Trainer / supervisor'),
    ('19-21', 'Partial payments and purchase return practice', 'Purchasing / sales / finance'),
], [.10, .64, .26])
callout('THE ENTRY ORDER', 'Branch and warehouse -> supplier and product -> purchase order -> goods receipt -> POS sale -> invoice / return -> supplier payment and expense -> reports.')
p('Menus and buttons depend on your role. A missing action usually means you do not have that permission. Ask the administrator to review your role and branch access; do not share another user\'s login.')

# 03
page(3, 'Sign in and prepare', 'Start every transaction by checking who you are and where you are working.')
steps([
    'Open the application URL supplied by your administrator. On this configured computer, use http://127.0.0.1:8085. This localhost address works on the computer running the application.',
    'Enter your assigned email and password, then select **Sign in to workspace**. Each staff member should use a separate account.',
    'Check the selected branch in the top bar. Switch to the authorized branch before opening a transaction. If the branch is missing, request access from the administrator.',
    'Open the required menu. For receiving and POS, also verify the warehouse. A branch choice does not automatically mean every warehouse or feature is available.',
    'Review the Overview dashboard for the selected branch. The figures summarize recorded activity; they do not replace a physical cash or stock check.',
])
h('Account and display settings')
p('Use the sun/moon button to switch themes. Open your account/profile to choose Light, Dark or Use system setting. On mobile, use the menu button; wide tables scroll inside their panels.')
p('To change your password, open the profile page, enter the current password, new password and confirmation, then select **Change password**. Sign out when leaving the workstation.')
callout('IF YOU FORGET YOUR PASSWORD', 'Use the password recovery link on the login screen. This local setup writes reset emails to the application log; it does not deliver normal email. Ask the administrator to help retrieve the link or reset your access.')
h('Basic entry rules')
p('Enter whole dispensing units for quantities. Enter money using up to two decimal places. Use actual document quantities, batch numbers and expiry dates. Read the success message and confirm the saved record before moving on.')
p('If a page stalls after posting, check the purchase, invoice or history first. Do not start a new transaction merely to repeat the same one.')

# 04
page(4, 'Create a medicine record', 'Menu: Medicines > Add medicine. Medicines are shared across branches; stock is not.')
table(['FIELD', 'WHAT TO ENTER', 'FICTIONAL EXAMPLE'], [
    ('Medicine name', 'Required; clear product description', 'Training Bandage 5 cm'),
    ('SKU', 'Required; unique internal product code', 'TRN-BAND-001'),
    ('Barcode', 'Optional; unique primary barcode', 'Leave blank for the exercise'),
    ('Generic name', 'Generic or descriptive name', 'Sterile bandage'),
    ('Manufacturer', 'Approved manufacturer name', 'Training Supplies'),
    ('Category', 'Required; your agreed grouping', 'Medical supply'),
    ('Strength / Dosage form', 'Enter where applicable', 'Blank / Bandage'),
    ('Dispensing unit', 'Required; smallest unit used here', 'piece'),
    ('Selling price', 'Required; price per dispensing unit', '80.00'),
    ('Tax in basis points', '100 = 1%; use approved rate', '0 for this exercise'),
    ('Reorder level', 'Whole-unit low-stock threshold', '10'),
    ('Storage instructions', 'Approved product instructions', 'Training item - dry storage'),
    ('Prescription required', 'Use the correct classification', 'Off for this fictional supply'),
    ('Active', 'On for a usable product record', 'On'),
], [.27, .43, .30])
p('Select **Save record**, search for the SKU and check the saved details. To correct a catalog record, use **Edit record** (pencil), change the fields and save. Turn Active off to archive an obsolete record.')
callout('PRICE IS NOT STOCK', 'Creating a medicine does not add quantity or acquisition cost. Stock is created through a goods receipt. Prescription-classified products are blocked at checkout in this version; never change the classification to bypass that block.')

# 05
page(5, 'Enter suppliers and customers', 'Menus: Suppliers and Customers. These contact records are shared across branches.')
steps([
    'Search for the person or organization before adding a new record. Check spelling, phone and email to avoid duplicates.',
    'Select **Add supplier** or **Add customer**. Enter the name; add phone, email and address where available.',
    'Keep **Active** selected for a contact that should be available for new transactions. Select **Save record**.',
    'Search again and inspect the saved record. Use the pencil action to correct details or turn Active off to archive it.',
])
table(['FIELD', 'SUPPLIER EXAMPLE', 'CUSTOMER EXAMPLE'], [
    ('Name (required)', 'Training Medical Supply', 'Training Customer'),
    ('Phone', 'Use an approved training placeholder or leave blank', 'Leave blank'),
    ('Email', 'supplier@example.test', 'customer@example.test'),
    ('Address', 'Training address only', 'Training address only'),
    ('Active', 'On', 'On'),
], [.23, .40, .37])
h('When the record is used')
p('Choose a supplier when creating a purchase order and when recording its payment. Select a customer at POS if the sale should carry a named customer; otherwise keep **Walk-in customer**.')
callout('CONTACTS DO NOT CREATE BALANCES', 'A supplier contact does not create a payable, and a customer contact does not create credit. Goods receipts create supplier liabilities. Named customers may have partial/unpaid sales when the POS option is enabled. Customer account statements are not available; outstanding balances appear on invoices.')
h('Trainer check')
p('Ask the trainee to find an existing contact, add one fictional supplier and one fictional customer, and explain the difference between editing a contact and entering a transaction.')

# 06
page(6, 'Create a purchase order', 'Menu: Purchases > New purchase order. Role: purchasing-authorized user.')
steps([
    'Check the branch. Select **New purchase order** and choose the correct active supplier.',
    'Use the medicine picker to search and select a product. Add each required product once.',
    'Enter the ordered **Quantity** as a whole number and **Unit cost** as the acquisition cost per dispensing unit. Do not enter the retail selling price here.',
    'Review every row. Remove an unwanted item with its remove/trash action. Check supplier, units, quantity and cost against the purchase document.',
    'Select **Create order**. Note the displayed PO reference and open its detail page using **View / receive** when required.',
])
table(['ENTRY', 'PRACTICE VALUE', 'MEANING'], [
    ('Supplier', 'Training Medical Supply', 'Who will supply the goods'),
    ('Product', 'TRN-BAND-001', 'Training Bandage 5 cm'),
    ('Quantity', '100', '100 individual pieces'),
    ('Unit cost', '50.00', 'PKR 50 per piece'),
    ('Expected goods value', '5,000.00', '100 x 50.00'),
], [.25, .33, .42])
callout('AN ORDER DOES NOT ADD STOCK', 'The purchase order records what you intend to buy. It does not add inventory or a supplier liability until goods are received. A saved order cannot be edited or cancelled through the current screens; review it carefully before saving.')
h('If an order is wrong')
p('Do not receive goods against incorrect quantities or costs to make the screen agree. Record the PO reference and ask the supervisor to decide the next action. Received goods can be returned from the purchase detail page; order cancellation is not implemented.')

# 07
page(7, 'Receive goods into stock', 'Menu: Purchases > View / receive > Receive goods. Check the physical delivery first.')
steps([
    'Open the correct PO and compare ordered, already received and remaining quantities. Choose the actual **Receiving warehouse**.',
    'For each line, replace the default remaining quantity with the quantity actually received. Enter **0** for a line that is not being received now.',
    'Enter the exact **Batch / lot** from the package and the **Expiry date**. The entered expiry must be after today. Leave expiry blank only for a product that genuinely has no expiry date.',
    'Check the physical quantity, product, lot, expiry and warehouse together. Select **Confirm receipt** and accept the confirmation only after this check.',
    'Review **Goods receipts** on the order page. Note the GR reference and value. Open Inventory & expiry and confirm the batch quantity in the intended warehouse.',
])
table(['FIELD', 'PRACTICE VALUE'], [
    ('Receiving warehouse', 'Your training branch warehouse'),
    ('Quantity', '100'),
    ('Batch / lot', 'TRN-LOT-001'),
    ('Expiry date', '2028-12-31, or another future date when training later'),
    ('Expected result', '100 available units; receipt value PKR 5,000.00'),
], [.31, .69])
callout('PARTIAL DELIVERIES AND MULTIPLE LOTS', 'Receive only the delivered quantity; the remainder stays pending. The form accepts one lot per product in a receipt. For the same product delivered in multiple lots, receive each lot separately, within the remaining ordered quantity.')
p('The receipt automatically increases inventory and the supplier payable. Do not also enter the goods cost as an expense or make a second receipt for the same delivery.')

# 08
page(8, 'Enter a POS sale', 'Menu: Point of sale. Role: cashier or another sales-authorized user.')
steps([
    'Confirm branch and warehouse before adding products. Search by medicine name, generic name, SKU or barcode. F2 focuses the search; a keyboard scanner can send a barcode followed by Enter.',
    'Select the product and enter the whole-unit quantity in **Current sale**. Review the product, price, quantity and available stock. Remove an unwanted cart line with its remove action.',
    'Search for and select a customer, or leave **Walk-in customer**. If your role allows discounts, enter the discount **per unit**, within the allowed limit; do not enter a whole-line discount in that field.',
    'Review subtotal, discount, tax and total. The system recalculates the authoritative sale value when it posts.',
    'Choose cash, card, bank or wallet and enter the confirmed payment amount. Use **Exact cash** or **Split payment**. To leave a balance, enable **Allow partial / unpaid sale** and select a named customer. Leave payment blank only when nothing is paid now.',
    'Select **Complete sale** once. Wait for the invoice page. Check the invoice number, total, recorded payments, remaining balance and cash change, then print if needed.',
])
table(['PRACTICE SALE', 'EXPECTED VALUE'], [
    ('TRN-BAND-001: 5 pieces x 80.00', 'Subtotal and total: PKR 400.00'),
    ('Discount / tax', '0.00 / 0.00 for this exercise'),
    ('Cash received', 'PKR 500.00'),
    ('Change to give back', 'PKR 100.00'),
    ('Remaining sellable stock', '95 pieces after the sale'),
], [.57, .43])
callout('PAYMENT AND STOCK RULES', 'Only cash overpayment can become change; noncash overpayment is rejected. Card, bank and wallet processing happens outside the app. Expired, quarantined and prescription-classified stock cannot be sold here. FEFO selects eligible batches with the earliest expiry automatically.')

# 09
page(9, 'Invoices, printing and returns', 'Menu: Sales & returns. Refunds require separate authorization.')
h('Find or print an invoice')
steps([
    'Open **Sales & returns** and search by invoice number. Open the matching invoice; verify the branch, date, customer, items and batch allocations.',
    'Select **Print invoice**, choose the printer and review the browser print preview. Printing uses a light background regardless of the selected theme.',
])
h('Enter a return and cash refund')
steps([
    'Open the original invoice and select **Return items**. A return cannot be entered as a new negative sale.',
    'For the relevant original batch allocation, enter the **Return quantity**. If the sale used multiple batches, select quantities against the correct allocations. Leave other rows blank or zero.',
    'Enter a clear **Return reason**, for example: "Training return - package condition uncertain". The quantity must not exceed the remaining returnable quantity.',
    'Review the actual refund with the authorized supervisor. Select **Confirm return**. The return reduces the unpaid invoice balance first; only the remaining return value is refunded in cash. Check the preview before confirming.',
    'Check **Return history** on the invoice and the quarantined quantity in Inventory & expiry. Record the invoice and return references for follow-up.',
])
table(['EXERCISE: RETURN 1 OF THE 5 PIECES', 'EXPECTED RESULT'], [
    ('Cash refund at original sale price', 'PKR 80.00'),
    ('Sellable quantity', 'Stays 95'),
    ('Quarantined quantity', 'Increases to 1'),
    ('Total physical quantity recorded', '96 pieces: 95 sellable + 1 quarantined'),
], [.58, .42])
callout('RETURNS DO NOT BECOME SELLABLE STOCK', 'Customer returns reduce the unpaid balance first, then refund any remainder in cash, and always put returned items in quarantine. Noncash customer refunds, exchanges, voids and quarantine release are unavailable. Completed invoices cannot be edited through the application.')

# 10
page(10, 'Inventory and quarantine', 'Menu: Inventory & expiry. Stock is tracked by branch, warehouse and batch.')
steps([
    'Search by medicine name or lot number. Use the available filters: All, Near expiry, Expired and Quarantined.',
    'Read the warehouse, lot, expiry, available balance and quarantined quantity together. An unquarantined balance can still be expired, so it is not automatically eligible for sale.',
    'Use **Stock movements** to review quantity changes and their source references. Compare receipts, sales, customer returns and quarantine movements when investigating a difference.',
])
h('Move an entire batch into quarantine')
steps([
    'Identify the correct batch and physically separate affected goods under your organization\'s procedure. This action requires inventory adjustment permission.',
    'Select the quarantine action. Enter a specific reason, such as "Training scenario - damaged outer packaging".',
    'Confirm only after checking the batch: **all remaining available units of that batch** move to quarantine. The screen does not accept a partial quarantine quantity.',
    'Recheck the batch and movement history. Available quantity becomes zero; quarantined quantity increases by the amount moved.',
])
callout('DO NOT USE QUARANTINE TO FIX A COUNT', 'Quarantine changes stock status; it does not remove physical units or write off their value. You can return available or quarantined received batches to the supplier from the purchase detail page. There is no quarantine release, disposal, stock-count adjustment or branch-transfer entry screen. Escalate those cases to the supervisor.')
h('Notifications')
p('Open **Notifications** to review current expiry and low-stock alerts. Alerts depend on scheduled inventory checks being run by the administrator. Refreshing the page alone does not run the check. Use the underlying inventory records to verify the situation.')
p('The current warning window is configured as 90 days. A product expiring today is excluded from POS. Change configuration only through the administrator, not through catalog classification changes.', 'small')

# 11
page(11, 'Supplier payments and expenses', 'Role: Accountant or another user with accounting management permission.')
h('Pay a supplier: Supplier payments or Purchases > View / receive / pay')
steps([
    'Confirm the branch and the actual payment evidence. Choose a supplier on **Supplier payments** and select **View supplier**, or open the specific purchase and find **Supplier payment**.',
    'Enter **Amount** and select the actual **Payment method**: bank, cash, card or wallet. The amount must not exceed the supplier\'s outstanding received-goods liability in this branch.',
    'Select **Record supplier payment**, confirm, then check the remaining balance and payment history. A supplier-page payment applies to the oldest purchases; a purchase-page payment applies only to that order.',
])
h('Record an expense: Expenses > Record paid expense')
steps([
    'Open **Record paid expense**. Enter **Category**, **Description**, **Amount** and the actual payment **Method**.',
    'Review the receipt or evidence and select **Post expense**. Confirm that the expense and its payment actually occurred.',
    'Check the new row in Expenses and the resulting journal entry. Use a useful description so another staff member can identify the purpose.',
])
table(['ENTRY', 'PRACTICE EXAMPLE'], [
    ('Supplier payment', 'Training Medical Supply; 3,000.00; bank'),
    ('Remaining supplier liability', '5,000.00 received - 3,000.00 paid = 2,000.00'),
    ('Expense category', 'Training utilities'),
    ('Expense description / amount / method', 'Training utility payment / 100.00 / cash'),
], [.38, .62])
callout('AVOID DOUBLE ENTRY', 'Goods receipts create inventory and payables, not operating expenses. Supplier payments reduce that payable. The expense screen records immediately paid operating costs; it does not record an unpaid bill, an opening balance or a second copy of a supplier payment.')

# 12
page(12, 'Use the accounting report pages', 'Menu: Accounting > Accounting reports. Each report opens on a separate page.')
table(['REPORT', 'HOW TO USE IT'], [
    ('Trial balance\n/accounting/trial-balance', 'Choose From and To, then Apply dates. Read account debit and credit totals, the difference and the Balanced / Out of balance badge. Reset dates to return to all posting dates.'),
    ('Operating result\n/accounting/operating-result', 'Apply the required posting-date period. Read income, sales returns and expense accounts. The result is revenue less returns, cost of goods sold and paid operating expenses.'),
    ('Stock reconciliation\n/accounting/stock-reconciliation', 'Checks current batch balances against all recorded stock movements. It has no date filter. A success message means recorded quantities agree; up to 100 discrepancies are shown otherwise.'),
    ('Journal entries\n/accounting/journal-entries', 'Filter by posting date, inspect source, description and date, and move between pages. Date filters remain when paging. Use source references to trace the transaction.'),
], [.36, .64])
steps([
    'Check the selected branch before interpreting any report. All four reports use that branch.',
    'Use the report navigation links to switch reports, or **Back to accounting** to return to the report menu and the link to Supplier payments.',
    'For date-filtered reports, use the same From and To values when comparing results. Dates are inclusive; To cannot be earlier than From.',
])
callout('WHAT THE RESULTS MEAN', 'A balanced trial balance does not prove every entry is correct. Stock reconciliation compares two recorded quantities, not a physical count. Operating result is not cash flow. Quarantined and expired stock can remain in inventory value until an authorized disposition process is implemented.')
p('Journal entries are generated automatically. There is no manual journal edit/delete/reversal screen. Transaction posting dates are generated by the system using the business date; users do not enter backdated posting dates.', 'small')

# 13
page(13, 'Automatic accounting entries', 'For trainers and accountants: users enter business transactions; the app posts the journals.')
table(['WHEN YOU CONFIRM', 'AUTOMATIC DEBIT', 'AUTOMATIC CREDIT'], [
    ('Goods receipt', 'Inventory (1200)', 'Accounts payable (2000)'),
    ('Supplier payment', 'Accounts payable (2000)', 'Cash (1000) or bank/clearing (1010)'),
    ('Sale', 'Cash/bank received plus customer receivable (1100) for unpaid amount', 'Sales revenue (4000); tax payable (2100) when applicable'),
    ('Later customer payment', 'Cash or bank/clearing received', 'Customer receivables (1100)'),
    ('Cost of sold units', 'Cost of goods sold (5000)', 'Inventory (1200)'),
    ('Customer return', 'Sales returns (4100); tax payable when applicable', 'Receivables reduced first, then cash refunded'),
    ('Purchase return', 'Accounts payable reduced or supplier refund received', 'Inventory at original batch cost'),
    ('Returned units at original cost', 'Inventory (1200), held in quarantine', 'Cost of goods sold (5000)'),
    ('Paid operating expense', 'Operating expenses (6000)', 'Cash or bank/clearing'),
], [.28, .36, .36])
h('Worked example: full payment, no discount and no tax')
table(['EVENT', 'AMOUNT / ACCOUNTING EFFECT'], [
    ('Receive 100 x 50', 'Inventory +5,000; supplier payable +5,000'),
    ('Sell 5 x 80', 'Revenue +400; retained cash +400; COGS +250; inventory -250'),
    ('Return 1 x 80', 'Cash -80; sales returns +80; inventory +50 in quarantine; COGS -50'),
    ('Pay supplier 3,000 by bank', 'Supplier payable -3,000; bank/clearing -3,000'),
    ('Pay expense 100 in cash', 'Operating expenses +100; cash -100'),
], [.37, .63])
p('Operating result: 400 - 80 - (250 - 50) - 100 = **PKR 20.00**. Inventory carrying value: (95 + 1) x 50 = **PKR 4,800.00**. Remaining supplier payable: **PKR 2,000.00**.')
p('The practice database begins with no opening cash/bank balance. Training payments can therefore leave those ledger accounts negative; do not treat this exercise as a complete cash funding or opening-balance procedure. Noncash methods share one clearing account.', 'small')

# 14
page(14, 'Administration and access', 'Menu: Administration. Use an administrator account; ordinary staff should not manage access.')
h('Create or update a user')
steps([
    'Select **Add user**. Enter name, a unique email, and a password of at least 12 characters. Keep Active selected for an enabled account.',
    'Select at least one existing **Role** and at least one **Branch access** checkbox. Assign only the duties and branches the person needs.',
    'Select **Save user**. Have the staff member sign in and confirm the intended menus and branch choices.',
    'Use **Edit access** to change another user\'s name, roles, branches, active status or password. On edits, leave the new password blank to retain it. Saving access changes revokes existing sessions.',
])
table(['ROLE', 'PRACTICAL USE'], [
    ('Cashier', 'Ordinary sales and invoice viewing; no discounts, refunds or finance'),
    ('Inventory Manager', 'Catalog, inventory, receiving and purchase returns'),
    ('Purchasing Officer', 'Suppliers, purchase orders, receiving and purchase returns'),
    ('Accountant', 'Financial reports, supplier payments/refunds, purchase returns and paid expenses'),
    ('Branch Manager', 'Operational management; no finance posting or user creation'),
    ('Auditor / Read-Only Manager', 'Review permitted records; no transaction posting'),
    ('Super Administrator / Pharmacy Owner', 'All currently implemented permissions'),
], [.40, .60])
h('Create a branch with its initial warehouse')
p('Under **Branches & warehouses**, enter Branch name, a unique Code such as TRN01, Timezone such as Asia/Karachi, and Warehouse name. Select **Create branch**. Assign other users to it using Edit access.')
p('The creator receives access to the new branch. Existing branch/warehouse editing and custom role creation have no current UI. You cannot change your own access; another administrator must do that.', 'small')

# 15
page(15, 'Daily checks and entry problems', 'Use these checks at the start and end of a working session. There is no register-shift close screen.')
table(['WHEN', 'CHECK'], [
    ('Start', 'Correct user, branch and warehouse; printer/scanner ready; physical cash checked under local procedure; alerts and expiry reviewed.'),
    ('During entry', 'Match actual documents; verify quantities, units, lot and expiry; review totals before confirming; note generated references.'),
    ('End', 'Review invoices, refunds, supplier payments and expenses; compare actual cash/payment evidence; review accounting and stock discrepancies; sign out.'),
], [.18, .82])
h('Common problems and the next action')
table(['PROBLEM', 'WHAT TO CHECK'], [
    ('Product missing or unavailable at POS', 'Active status, selected warehouse, receipt existence, quantity, expiry, quarantine and prescription classification.'),
    ('Duplicate SKU or barcode', 'Search for the existing medicine. Correct or use that record; do not create a second product under a misleading code.'),
    ('Receipt rejected', 'Remaining ordered quantity, correct warehouse, whole units, lot number and future expiry. Use 0 only to skip a line in the form.'),
    ('Payment or sale rejected', 'For an unpaid sale, enable partial payment and select a named customer; noncash not overpaid; later payments within the remaining balance; money has at most two decimal places.'),
    ('Return rejected', 'Correct original invoice and batch allocation; quantity within the unreturned balance; refund permission and reason.'),
    ('Missing button / access denied', 'Active account, assigned role and branch. Contact the administrator.'),
    ('Uncertain whether a post succeeded', 'Find the existing PO, GR, invoice or journal source first. Record the reference and ask for help before posting another transaction.'),
    ('Wrong completed financial entry', 'Do not alter a different record to compensate. Give the supervisor the reference, actual facts and intended correction; a general correction UI is not available.'),
], [.36, .64])
p('**Audit trail:** authorized reviewers can open this menu to inspect recorded user actions and source references for the selected branch. It is a read-only history, not a place to enter or correct transactions.')
p('For support, capture: screen name, selected branch/warehouse, transaction reference, expected result and exact error message. Avoid including passwords or unnecessary customer information.', 'small')

# 16
page(16, 'Guided practice: enter the records', 'Use a dedicated training branch/database. Repeat runs must use a new SKU and lot suffix.')
callout('BEFORE THE EXERCISE', 'Trainer: prepare authorized accounts, the training branch and warehouse, and a clean training dataset. Existing seed catalog records have no stock. All values below are fictional. Do not run the exercise in live trading data.')
table(['STEP', 'ENTER / DO', 'WRITE THE REFERENCE'], [
    ('1. Supplier', 'Training Medical Supply; supplier@example.test; Active on', 'Supplier:\n________________'),
    ('2. Customer', 'Training Customer; customer@example.test; Active on', 'Customer:\n________________'),
    ('3. Product', 'Training Bandage 5 cm; SKU TRN-BAND-001; category Medical supply; unit piece; selling price 80.00; tax 0; reorder 10; prescription off; Active on', 'SKU:\n________________'),
    ('4. Order', 'Supplier above; 100 pieces at unit cost 50.00', 'PO:\n________________'),
    ('5. Receive', '100 pieces; training warehouse; lot TRN-LOT-001; expiry 2028-12-31 or another valid future date', 'GR:\n________________'),
    ('6. Sale', 'Select training customer; sell 5 pieces at 80.00; no discount; cash 500.00; give change 100.00', 'Invoice:\n________________'),
    ('7. Return', 'Return 1 piece against that invoice allocation; reason: Training return - package condition uncertain; refund cash 80.00', 'Return:\n________________'),
    ('8. Supplier payment', 'Pay the training supplier 3,000.00 by bank', 'Source:\n________________'),
    ('9. Expense', 'Category Training utilities; description Training utility payment; amount 100.00; method cash', 'Source:\n________________'),
], [.17, .58, .25])
p('Use role-specific accounts where practical: purchasing/receiving, cashier, refund-authorized user and accountant. An administrator may demonstrate all steps, but a trainee must know which actions their own role allows.', 'small')

# 17
page(17, 'Guided practice: verify the results', 'Expected values apply only to the isolated exercise, with no other entries and no tax or discount.')
table(['CHECK', 'EXPECTED', 'OBSERVED / PASS'], [
    ('After receipt', '100 available units; receipt value 5,000.00', '________________'),
    ('After sale', 'Invoice 400.00; cash tendered 500.00; change 100.00; available stock 95', '________________'),
    ('After return', 'Refund 80.00; available 95; quarantine 1; physical total recorded 96', '________________'),
    ('Supplier balance', 'Received 5,000.00 less paid 3,000.00 = 2,000.00 payable', '________________'),
    ('Paid expense', '100.00 in the Expenses list', '________________'),
    ('Operating result', '400 revenue - 80 returns - 200 net COGS - 100 expense = 20.00', '________________'),
    ('Inventory value', '96 total recorded units x 50.00 = 4,800.00, including quarantine', '________________'),
    ('Trial balance', 'Total posted debits = credits = 8,880.00 for all exercise entries; difference 0.00', '________________'),
    ('Stock reconciliation', 'Batch balances agree with all recorded stock movements', '________________'),
    ('Journal entries', 'Sources exist for receipt, sale, return, supplier payment and expense', '________________'),
], [.28, .49, .23])
h('How to verify')
p('Open each Accounting report page in the training branch. Use a date range covering every exercise posting, or reset dates. Check the supplier liability account in Trial balance, the operating result, inventory records and the relevant journal sources.')
callout('EXTRA PRACTICE - AFTER THE CHECKS ABOVE', 'In a separate run, receive an order in two parts and two lots. Try split payment that equals the sale total. Ask an authorized inventory user to quarantine a batch. These extra transactions change the expected figures above, so complete and sign off the base exercise first.')
p('Trainer notes: __________________________________________________________________\n________________________________________________________________________________', 'small')

# 18
page(18, 'Trainer sign-off and scope', 'Use this checklist to confirm independent operation, not just observation.')
table(['TRAINEE CAN DEMONSTRATE', 'PASS / RETRAIN'], [
    ('Sign in with their own account; select correct branch and warehouse', '________________'),
    ('Find an existing record and avoid duplicate catalog/contact entries', '________________'),
    ('Explain selling price versus acquisition unit cost and dispensing unit', '________________'),
    ('Create an order, receive actual goods and return original batches to suppliers', '________________'),
    ('Complete full/partial sales, collect remaining balances and print invoices', '________________'),
    ('Explain original-invoice returns, cash refunds and quarantine', '________________'),
    ('Post partial supplier payments/paid expenses only if permitted', '________________'),
    ('Open all four reports and explain the result of the practice exercise', '________________'),
    ('Escalate a wrong posted entry or missing permission correctly', '________________'),
], [.76, .24])
h('Feature boundaries to explain during training')
p('This version does not provide opening-stock/opening-balance entry, stock-count adjustments, branch transfers, purchase cancellation, prescription dispensing, parked sales, register shifts, voids/exchanges, noncash refunds, quarantine release/disposal, manual journals, period close or complete statutory financial statements.')
p('Users should not invent workarounds by changing product classification, posting fictional receipts or recording unrelated expenses. Report the actual business need to the supervisor.')
p('Sales CSV export is available to users with reports permission at /exports/sales. It exports the selected branch\'s completed sales, with UTC timestamps, and is not a net-refund or cash-flow report.', 'small')
h('Completion record')
p('Trainee: __________________________  Role / branch: __________________________')
p('Trainer: __________________________  Date: __________________________________')
p('Outcome / follow-up: ________________________________________________________')
p('Prepared from the current application screens, request validation, transaction services and repository operation guides. Local training edition, 16 October 2026. Updated for separate report pages, partial payments and purchase returns; see pages 19-21 for the new workflows.', 'small')


# 19 - Supplier installments
page(19, 'Partial supplier payments', 'Menu: Supplier payments. You can also pay from an individual purchase detail page.')
steps([
    'Confirm the branch. Open **Supplier payments**, choose the supplier and select **View supplier**. Review outstanding balance and available credit.',
    'For a supplier-wide installment, enter an amount and method, then select **Record supplier payment**. It automatically pays the oldest outstanding purchases first.',
    'For one purchase only, use **View / pay** and the **Supplier payment** section. Enter the installment amount, or select **Pay remaining balance** to fill the full outstanding amount.',
    'Confirm only after paying the supplier. Check **Payments recorded**, **Remaining to pay**, payment status and payment history. Record each later installment separately with its actual method.',
])
table(['PRACTICE ENTRY', 'EXPECTED RESULT'], [
    ('Receive 100 pieces at cost 50.00', 'Purchase received value 5,000.00; unpaid 5,000.00'),
    ('Pay 2,000.00 by bank', 'Paid 2,000.00; remaining 3,000.00; Partially paid'),
    ('Pay another 1,000.00 by bank', 'Paid 3,000.00; remaining 2,000.00'),
    ('Pay remaining 2,000.00', 'Paid 5,000.00; remaining 0.00; Paid'),
], [.48,.52])
callout('PAYMENT CONTROLS', 'Only accounting-authorized users can post supplier payments. No payment can exceed the remaining received-goods balance. Purchase returns and supplier credits affect that balance automatically. An order with no receipts has no amount to pay yet.')
p('Existing supplier payments have been allocated to received orders, oldest first, during this update. The migration does not create new payment journals for those historical allocations.', 'small')

# 20 - Customer installments
page(20, 'Partial sales and later collection', 'Menu: Point of sale, then Sales & returns > the original invoice.')
steps([
    'Add the products and quantities as usual. Search for and select a **named customer**. A walk-in sale cannot leave an unpaid balance.',
    'Enable **Allow partial / unpaid sale**. Enter the amount actually paid now. If nothing is paid, leave the payment amount blank. Review **Remaining after this payment**.',
    'Select **Complete sale**. The invoice is issued and stock is deducted for the whole sale. The unpaid part is recorded as a customer receivable; it is not an additional future sale.',
    'For each later payment, open the original invoice from **Sales & returns**. In **Receive customer payment**, enter amount and method and select **Record customer payment**. Use **Pay remaining balance** to fill the final amount.',
    'Check the invoice payment list, remaining balance and Paid / Partially paid / Unpaid status. The next payment cannot exceed the current balance.',
])
table(['PRACTICE ENTRY', 'EXPECTED RESULT'], [
    ('Sell 5 pieces at 80.00, no tax/discount; receive 150.00 now', 'Invoice 400.00; paid 150.00; balance 250.00'),
    ('Collect another 100.00 on the invoice', 'Total payments 250.00; balance 150.00'),
    ('Return 1 piece worth 80.00 before final payment', 'Balance reduces to 70.00; cash refund 0.00'),
    ('Collect final 70.00', 'Net invoice 320.00; total paid 320.00; Paid'),
], [.56,.44])
callout('RETURNS ON UNPAID INVOICES', 'Return value first cancels the remaining receivable. Only the amount beyond that balance is refunded in cash. Returned units still enter quarantine. Always read the balance-reduction and cash-refund preview before confirming.')
p('Cash, bank, card and wallet receipts are supported. Customer collection requires sales-create permission and access to the invoice branch. Later collections do not issue another sales invoice or create revenue again.', 'small')

# 21 - Purchase returns
page(21, 'Purchase returns and settlement', 'Menu: Purchases > View / receive / pay > Return goods to supplier.')
steps([
    'Open the original purchase and identify the received product and lot. Choose **Available** or **Quarantine** for the stock being handed back. Enter the return quantity for each relevant batch.',
    'Enter a clear **Return reason**. You cannot return sold/absent units, exceed the selected stock bucket, or exceed the original received quantity after previous supplier returns.',
    'Review the return value at original unit cost, the reduction of this order balance and any paid portion. The receiving status stays based on the original receipts; a return does not reopen the PO for another delivery.',
    'Choose **Keep as supplier credit** for the paid portion, or, if accounting-authorized, choose **Supplier refund received** and the actual method. Select refund only when that money has really been received.',
    'Select **Confirm purchase return**. Verify PR reference, purchase return history, stock quantities, remaining balance and Journal entries. Supplier credits automatically settle oldest outstanding or future purchases for the same supplier and branch.',
])
table(['EXAMPLE: RETURN VALUE 1,000.00', 'EXPECTED RESULT'], [
    ('Order still owes 2,000.00', 'Outstanding becomes 1,000.00; no credit or refund'),
    ('Order still owes 600.00; choose credit', 'Outstanding becomes 0.00; supplier credit 400.00'),
    ('Order fully paid; choose credit', 'Supplier credit 1,000.00; it applies to other/future purchases'),
    ('Order fully paid; actual bank refund received', 'Record refund 1,000.00 by bank; no supplier credit'),
], [.52,.48])
callout('STOCK AND ACCESS', 'Only users granted purchase-return permission can post returns. Recording a supplier refund also requires accounting management permission. A purchase return removes goods from inventory and reverses their original cost. It is not a customer refund or a paid operating expense.')
p('One stock bucket can be selected per batch in each return. If the same lot must be returned from both available and quarantined quantities, record separate returns. Refunds received after a supplier credit has already been issued are not entered through a separate credit-refund screen in this version.', 'small')

doc = SimpleDocTemplate(str(OUT), pagesize=A4, rightMargin=48, leftMargin=48,
    topMargin=61, bottomMargin=60, title='PharmaFlow - User Training and Data Entry Manual',
    author='PharmaFlow Training', subject='Practical user training for the implemented pharmacy workflows')
doc.build(story, canvasmaker=NumberedCanvas)
print(OUT)
