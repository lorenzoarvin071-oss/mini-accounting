# User Manual: Mini Accounting System

## 1. Signing in
Open the system in a browser and enter your username and password. Roles:
- **Treasurer**: everything, including Settings and voiding entries.
- **Officer**: add and edit members, record payments, advances, and expenses.
- **Adviser**: view and print reports only.
- **Member**: sees only their own balance and receipts.

Change your password under **My account** before using real data.

After signing in, each role lands on its own **Home** page: the treasurer sees open advances, unpaid balances, and recent activity; the officer sees quick actions, members to follow up, and their own recent entries; the adviser sees a view-only summary with links to every report. The **Dashboard** page shows the full financial figures for all roles.

## 1a. Member accounts
Members can create their own account on the sign-in page (**Create your member account**). Enter your name and student ID, choose your course and year level from the lists, and pick a username and password. If you were already added or imported, your account links to your record by student ID and exact name; otherwise a new record is created and a Member ID (for example M-001) is assigned automatically. After signing in, members see only their own page: fee, total paid, balance, status, and payment receipts. They cannot see other members or any other records. A forgotten password is reset by the treasurer (Members → Edit → Reset password).

## 2. Members
1. Go to **Members**, fill in the name, course, year, contact, and fee, then click **Add member**.
2. Each member gets an ID (M-001). Status is **Unpaid**, **Partial**, or **Paid**, computed from payments.
3. Use **Search** to find a member. Click **Edit** to fix details. A fee cannot be lowered below what the member already paid.
4. **Export CSV** downloads the member list.

### Importing many members at once
Treasurers and officers can use **Members → Import from file**. Paste rows copied from Excel, or upload a CSV file. Columns: student_id, name, course, year, contact, fee, paid (only student_id and name are required). A blank fee uses the default fee, and a number in *paid* records a payment with a receipt number. Rows with a duplicate student ID, an unknown course, or other problems are skipped and listed with the reason; the rest are imported. Members who were imported can later create an account by entering the same student ID and name.

## 3. Collecting fees
1. Go to **Fee collection** and choose a member. The balance fills in automatically.
2. Set the date, amount, and method, then click **Record payment**.
3. The system issues a receipt number (OR-0001). Partial payments are allowed; paying more than the balance is blocked.
Unpaid fees are never counted as cash.

## 4. Expenses and liquidation
1. **Issue a cash advance**: enter who received it, the purpose, and the amount. It gets a reference (CA-001).
2. **Record an expense**: enter a description, category, amount, and the receipt reference (required). Under *Charge to advance*, choose the advance it came from, or leave it as "None (paid from cash)".
3. The **Liquidation** table compares each advance with its expenses and shows what must be **returned** or **reimbursed**. Enter the amount and click **Record return** or **Reimburse**. When the balance reaches ₱0 the advance shows **Liquidated**.

## 5. Dashboard and reports
- **Dashboard**: cash on hand, fees assessed, collected, outstanding, expenses, and open advances.
- **Cash on hand** = fees collected + cash returned − advances issued − reimbursements − expenses paid directly from cash.
- **Reports**: summary, monthly financial summary, collection status, and liquidation summary. Click **Print report** (or save as PDF from the print dialog).

## 6. History, corrections, and audit trail
- **Transaction history** lists every entry with a running cash balance. **Export CSV** downloads it.
- Entries are never deleted. To correct a mistake, the treasurer clicks **Void** and gives a reason, then records the correct entry. Voided entries stay visible (struck through) but do not count in any total. An advance can only be voided after its linked expenses and returns are voided.
- **Audit trail** records who did what and when.

## 7. Testing with the sample scenario (treasurer)
**Settings → Load sample scenario** loads 50 members × ₱100, ₱4,500 collected, a ₱2,000 advance, ₱1,650 receipted expenses, and ₱350 returned. Expected result: **cash on hand ₱2,850.00** and **outstanding fees ₱500.00**. **Clear all data** empties the records.
