# Sedona Court user guide

## Cashier and owner tutorial

This guide teaches the everyday tasks in Sedona Court PMS, using the button names in the current system. Start with the short checklist for your role, then read the lesson for the task you need. You do not need to understand programming or accounting terms to use the cashier lessons.

**Guide edition:** 10 October 2026. **Application reviewed:** version 3, commit `5845917`. A different installation or account permission may show fewer buttons. Examples use made-up names and amounts. Always charge the actual amount shown by your system.

**The most important rule:** start your cashier shift before taking payments. Keep it open while serving guests. Check out each departing guest. Count and close your shift when your duty ends. The owner closes the business day separately.

Passwords and real guest details are intentionally excluded. Use the account assigned to you by the owner.

### Choose your starting point

- **New cashier:** read Lessons 1 to 4, then follow Lessons 5 to 15 as needed. Keep [the cashier checklist](#26-cashier-checklist-to-keep-beside-the-desk) beside your desk.
- **Owner:** read Lessons 16 to 24 and use [the owner checklist](#27-owner-checklist-for-daily-review). Read the cashier lessons when covering the desk or training staff.
- **Checkout blocked:** go directly to [common problems](#25-common-problems-and-what-to-do), then check the drawer and handover lessons.
- **Training a new employee:** use [the practice walkthrough](#28-practice-walkthrough-for-staff-training) in a separate training installation.

## 1 Start here

### Open the system and sign in

1. Connect your computer to the property's local network.
2. Open Chrome, Edge, or your usual browser.
3. Open the address supplied by the owner. For the current LAN setup, the agreed address is **http://192.168.0.103:8000**. It works while the server computer is running the application and your device can reach that network.
4. Enter your assigned email and password. Click the login button.
5. Look at the name and role in the top-right corner. Make sure you are signed in as yourself.

Your browser is the window into the system. The server computer holds the shared records. Everyone using the same server sees the same rooms and transactions.

### Three actions that do different jobs

| Action | What it does | When to use it |
| --- | --- | --- |
| Guest checkout | Settles one guest's bill and ends that stay | When that guest leaves |
| Close cashier shift | Records the final cash count and starts drawer handover | When the cashier's duty ends |
| Close business day | Locks a reviewed financial period | Owner or admin review after the cutoff |

**Do not close your shift after every guest checkout.** One open shift can contain many guest payments and POS sales.

### When you finish using a shared computer

Open your name in the top-right corner and click **Logout**. Logging out does not close your shift or count the drawer. Complete the handover lesson when another cashier takes over.

## 2 Simple words and the menu

### Words you will see

| Word | Plain meaning |
| --- | --- |
| Folio | One guest stay's bill and record |
| Pax | Number of people staying |
| Tier | The selected stay length, such as 3 or 12 hours |
| Xtend | Add paid hours to a stay |
| POS | The food, drink, and amenity sales screen |
| Drawer | The cash register money assigned to a cashier |
| Opening float | Cash already in the drawer when you start |
| Tendered | The full cash amount the guest hands you |
| Variance | The difference between expected and counted cash |
| Handover | One cashier passes the drawer to another |
| Pending | Waiting for someone to act |
| Approved | A reviewer has agreed; execution may still be needed |
| Executed | The approved action has been applied |
| Reconciliation | Compare recorded money with actual counts or statements |
| Write-off | An approved unpaid amount recorded as a loss |
| Audit history | A record of who performed an action and when |

### Where to go

Cashiers mainly use **Front Desk**, **Reservations**, **POS**, **Shifts & Drawer**, **Stock**, **Approvals & Tasks**, and **Transactions & Audit**.

Owners also use **Reports & Audits**, **Ledger**, **Daily Finance**, **Executive P&L**, **Expenses & Petty Cash**, **Shift Reconciliations**, **Loss Slips**, **Master Pricing Editor**, and **Staff Accounts & RBAC**. **Accounting setup** is reached from **Daily Finance**.

On a small screen, use the menu button near the top to open the sidebar. A window that appears over the page is a form; fill it out, then use its save or submit button.

Cashiers normally see their own transaction and audit history. Owners with the normal owner permissions can review property-wide records. A missing button or a **403** message can mean the account does not have permission.

The top bar has **Sound ON / OFF**. Enable sound when your station should hear stay alerts. **Stop Alarm Sound** silences the current alarm; it does not extend a stay, remove overtime, or check out a guest. Owners can also use the notification menu to find items needing attention.

## 3 Cashier quick start

Follow this order during a normal duty:

1. **Sign in as yourself.** Check the name in the top-right corner.
2. **Open Shifts & Drawer.** Start a shift or accept the previous cashier's handover.
3. **Count the starting cash.** Enter what is actually there, including any float you receive.
4. **Use Front Desk.** Check in guests and manage active stays.
5. **Use POS for orders.** Choose whether each order goes on a room bill or is paid immediately.
6. **Record deposits when you receive them.** Use the separate deposit form.
7. **Check out departing guests.** Verify the bill, receive payment, and click **Complete Official Check-Out**.
8. **Review your records.** Use **Transactions & Audit** and **Approvals & Tasks**.
9. **At the end of duty, count the drawer.** Close the shift and hand it to the incoming cashier.
10. **Log out.** Let the incoming cashier use their own account.

### Five habits that prevent mistakes

- Confirm the room and guest before adding anything or taking payment.
- Enter cash and GCash in their correct fields.
- Read the success message after a submission.
- Check history before retrying a payment after a connection problem.
- Enter actual counts. A guessed number can create a shortage or overage later.

## 4 Cashier starting the drawer

**Go to:** Shifts & Drawer.

The application allows one active drawer at a time. Two computers do not create two separate drawers. The open drawer belongs to the cashier who started or accepted it.

### If you see Start cashier shift

1. Count the cash physically in the drawer.
2. Enter that amount in **Starting cash in drawer**.
3. If the drawer is empty, enter **0**.
4. Click **Start cashier shift**.
5. Confirm the page shows your open drawer.

**Example:** you receive two ₱500 bills and five ₱100 bills. Your opening cash is ₱1,500. Enter `1500`, rather than the expected sales for your duty.

An opening float is existing cash. It is not a guest payment or a new sale.

### If you see Waiting for drawer handover

The previous shift has already been closed. You must accept its handover instead of starting a second shift. Follow Lesson 13. The outgoing cashier cannot accept their own handover.

### If the drawer belongs to another cashier

Ask that cashier and the supervisor to arrange a proper handover. Payments should be recorded under the cashier who actually has custody of the drawer. Signing out of their account does not transfer custody.

### If the drawer is frozen

Payments are blocked. Ask the admin or owner to investigate the drawer status. The current screens do not provide a general **Unfreeze** button, so do not spend time searching for one.

The checkout page also offers **Start cashier shift** when there is no open drawer and no pending handover. Enter the actual starting cash there; the system returns you to checkout.

## 5 Cashier checking in a walk-in guest

**Go to:** Front Desk.

1. Find an **Available** room of the type the guest wants.
2. Click **Check In** beside that room.
3. Confirm the room number at the top of the form.
4. Enter the guest name.
5. Enter **No. of Guest (Pax)**.
6. Choose the **Stay Duration Tier**: 3, 6, 12, or 24 hours, or the displayed midnight promo. The current promo is a 12-hour stay.
7. Read the **Standard Room Rate** preview and confirm the selected stay with the guest.
8. If applying a Senior Citizen or PWD discount, select the appropriate box and enter the required **Senior / PWD ID Reference**.
9. Click **Process Check In**.
10. Confirm the success message and find the room in **Occupied rooms**.

The check-in form uses the live clock. A correction to the recorded time needs the approval workflow in Lesson 12.

### What the system creates

It creates the guest stay record, assigns a folio reference, calculates the expected checkout time, and marks the room occupied. **Check-in itself does not mean the guest has paid.** Record actual deposits and settlement separately.

### If the room has become unavailable

Another operator may have used it, or it may be under maintenance. Refresh Front Desk and choose an available room. Do not use a room-status change to remove an active guest.

Permanent staff quarters have a separate staff-order workflow. They are not ordinary rooms for guest check-in.

## 6 Cashier reservations

**Go to:** Reservations.

### Create a reservation

1. Click **New Guest Reservation**.
2. Choose the target room and rate tier.
3. Enter the guest name and contact number.
4. Enter the arrival date and time and the number of guests.
5. Add useful notes, such as an arrival instruction.
6. Click **Save Reservation**.
7. Confirm it appears under **Scheduled (Active)**.

The system checks for overlapping scheduled reservations. It rejects maintenance rooms and staff quarters. A reservation does not collect money and does not guarantee the room is physically vacant when the guest arrives. Check the live room board too.

### When the reserved guest arrives

1. Find the correct reservation under **Scheduled (Active)**.
2. Confirm the guest, room, and agreed stay length.
3. Confirm the room is available now.
4. Click **Check-In** on that reservation.
5. Confirm success in Front Desk and the reservation's **Checked-In** status.

### Cancel a reservation

Use the cancellation action on a scheduled reservation, then check **Cancelled**. Only a scheduled reservation can be cancelled. A guest already checked in must go through the guest-stay workflow.

Use **All Records** when searching for a reservation that is no longer scheduled. The current reservation screen does not provide a general edit form for an existing booking; coordinate an incorrect scheduled booking with the supervisor before cancelling and replacing it.

## 7 Cashier extensions discounts and transfers

**Go to:** Front Desk, then find the occupied room. Each list entry groups the room information and its actions. Confirm the room and guest first.

### Add stay hours with Xtend

1. Click **Xtend**.
2. Choose how many hours to add.
3. Read the displayed extra charge. The current extension rate is **₱130 per hour**.
4. Click **Save Extension**.
5. Confirm the remaining time and stay details changed.

An extension adds time and a charge to the bill. It does not collect payment. A second extension submission adds more hours, so verify the result before submitting again.

The current billing calculation rounds a started excess hour up to a full hour. An overdue stay can therefore gain an overtime charge. Use the checkout calculation shown by the system rather than estimating it yourself.

### Apply a Senior Citizen or PWD discount

1. Click **Discount** for the correct room, or use the checkout discount fields.
2. Select the appropriate discount and enter the ID reference.
3. Check the displayed deduction.
4. Use **Apply Fixed Discount**, or complete the checkout form when working there.

The application uses its configured deduction table. A Sedona Card / DC change, removal of an existing discount, rate override, or time correction may require independent approval. Use Lesson 12 when the system directs you to Approvals. Some older form options remain visible even when the action requires approval.

### Transfer a guest to another room

1. Click **Transfer**.
2. Select an available target room.
3. Confirm the target type and bill with the guest; the bill is recalculated for the folio in its new room.
4. Click **Complete Transfer**.
5. Confirm the guest is shown in the new room and the old room is available.

Existing orders follow the folio. Confirm the old room is physically ready before assigning it again. Transfer does not perform cleaning or settle the guest's bill.

## 8 Cashier food drinks and room add-ons

### Add one item to a room bill

**Go to:** Front Desk, occupied room, **Add On**.

1. Confirm the room and guest.
2. Select the menu item or amenity.
3. Enter the quantity.
4. Click **Save Add On**.
5. Confirm the success message and check the room's bill or order slip.

This adds a room charge, reduces tracked stock, and records the quick add-on as delivered. Use it when the item is already being supplied. For an order the kitchen must prepare, use the POS order workflow below.

### Send a kitchen order to a room

**Go to:** POS.

1. Choose the category and add the requested items to the cart.
2. Use the **+** and **-** controls to correct quantities.
3. Choose **Charge to Guest Room Folio** under **Order Destination**.
4. Choose the correct occupied room.
5. Enter **Kitchen / Room Notes**, such as a preparation request.
6. Confirm the cart and total.
7. Click **Place Order & Dispatch Ticket**.
8. Confirm the order appears in recent orders with the correct room.

The amount goes on the guest's bill for settlement at checkout. Do not also enter the same order as a paid walk-in sale.

### Follow the order status

The normal order stages are **new**, **preparing**, **ready**, and **delivered**. The action buttons may say **Cooking**, **Ready**, and **Delivered**. Mark delivery when it actually happens. An order status tells you about preparation; it does not by itself prove payment.

If an item is unavailable, check stock and ask the kitchen or supervisor. Some dishes also consume egg stock. Use **Clear All** only when you want to empty the unsent cart.

## 9 Cashier paid walk-in POS sales

**Go to:** POS. Your drawer must be open for an immediate payment.

1. Add the items and quantities to the cart.
2. Choose **Direct Walk-In Sale (POS)** under **Order Destination**.
3. Confirm the order total.
4. Choose **Cash Payment** or **GCash E-Wallet**.
5. For cash, enter the full amount received in **Cash Received**. For GCash, enter the amount received and the actual **GCash Reference**.
6. Confirm the payment has actually been received and the entered amount covers the order.
7. Click **Place Order & Dispatch Ticket**.
8. Confirm success and find the sale in **Transactions & Audit**.

**Example:** the order is ₱180 and the customer hands over ₱200. Enter `200` as cash received. Give ₱20 change. The cash kept from the sale is ₱180.

GCash for a walk-in sale must match the sale total. The POS screen currently offers cash or GCash; the **Split (Cash + GCash)** option is on guest checkout.

### If the order is wrong after submission

Do not send a second copy to fix the first. Check recent orders and transaction history, then tell the supervisor exactly what happened. The current POS screen does not provide a general cancellation or room-order refund button.

A paid walk-in refund uses **Approvals & Tasks → Refund a walk-in sale**. Select the original payment, enter the approved amount and refund method, and give the reason. Follow Lesson 12 for review and execution. A money refund does not automatically restore stock.

## 10 Cashier receiving a guest deposit

**Go to:** the guest's checkout screen from Front Desk. Confirm the room and folio.

A deposit is guest money held for that stay. Recording a deposit and completing the guest's final checkout are separate actions.

### Record the deposit when you receive it

1. Confirm your drawer notice says **Cashier shift active**.
2. Find **Record deposit received** near the top.
3. Enter only the additional money being received now in **Amount received**.
4. Choose **Cash** or **GCash**.
5. For GCash, enter the actual reference.
6. Click **Record deposit received**.
7. Confirm the success message and the updated deposit total.
8. Open the deposit slip if the guest needs a record.

**Example:** a deposit of ₱300 is already recorded. The guest gives another ₱200. Enter `200`. The new held deposit is ₱500. Entering `500` would add another ₱500.

### Apply the deposit to the bill

The checkout page has an **Apply Deposit** checkbox. When selected, the held deposit reduces the final balance by up to the amount of the bill. An excess remains refundable. Check the **LESS: SECURITY DEPOSIT**, **FINAL BALANCE TO PAY**, and **REMAINING DEPOSIT TO REFUND** lines.

The recorded deposit field is read-only. Checking Apply Deposit does not collect new money, and printing a deposit slip does not collect new money.

After settlement, any remaining refund needs independent review and execution. Read Lesson 12 before handing back deposit money.

## 11 Cashier completing a guest checkout

**Go to:** Front Desk, find the occupied room, then use its **Occupied** action to open the checkout screen.

### Check the bill before accepting payment

1. Verify the **ROOM NUMBER**, **CUSTOMER NAME**, and folio reference.
2. Read the drawer notice at the top. Resolve it if payment is blocked.
3. Review the room rate, stay length, extensions, overtime, and add-ons.
4. Review the discount and its required ID reference.
5. Decide whether to apply the recorded deposit and check the remaining deposit.
6. Read **FINAL BALANCE TO PAY**. This is the amount still needed for settlement.

### Enter and complete payment

1. Select **Cash Payment**, **GCash E-Wallet**, or **Split (Cash + GCash)**.
2. For cash, enter the full notes-and-coins amount the guest hands you in **CASH TENDERED**.
3. For GCash, enter the actual amount in **GCASH AMOUNT PAID** and enter **GCASH REFERENCE**.
4. For split payment, enter both amounts. Together they must cover the final balance. GCash cannot exceed the balance.
5. Check **CHANGE DUE TO GUEST** and give the correct change.
6. Click **Complete Official Check-Out** once and confirm the prompt.
7. Wait for **Guest checked out and payment recorded**.
8. Confirm the room is no longer occupied. Use the receipt and gate-pass links in the success message.

**Example:** final balance ₱800; guest pays ₱500 by GCash and hands you ₱500 cash. Enter GCash `500`, its reference, and cash `500`. Give ₱200 change. The recorded payment kept is ₱500 GCash plus ₱300 cash.

### What counts as completion

Leaving a payment field can save a draft. **A saved draft is not payment.** Previewing or printing a bill is not payment either. The final checkout submission records settlement and marks the room available. Confirm the room is physically ready before assigning it to the next guest.

If an error appears, read it and check whether the stay is still active. After a timeout, check history before collecting or recording the payment again.

## 12 Cashier approvals refunds and printouts

### Request a correction before checkout

**Go to:** Approvals & Tasks, **Rate, time or discretionary discount**.

1. Select the correct guest folio.
2. Enter only the fields that need changing: room charge, check-in time, or discretionary discount.
3. Leave unchanged fields empty.
4. Explain the reason and include an evidence reference when useful.
5. Click **Submit request**.
6. Ask a different admin or owner to review it.
7. After approval, use **Execute approved action**, or have the authorized reviewer execute it.
8. Reload checkout and confirm the corrected bill before collecting payment.

### Return a remaining guest deposit

1. Settle the guest's bill first.
2. Open **Approvals & Tasks → Return remaining guest deposit**.
3. Choose the folio, refund amount, and actual refund method. Enter a GCash reference for a GCash refund.
4. Enter the reason and evidence reference. Click **Submit request**.
5. Have a different admin or owner review it.
6. Execute the approved action when the refund is actually made. Cash payment needs the usable drawer assigned to the operator, and the money movement is recorded at execution.
7. Check the negative refund entry in transaction history and the refund summary.

The deposit refund slip also has a button named **Record actual refund**. In the current version, that submission creates a refund approval request. Read its resulting message: it does not pay the guest or post the refund immediately.

### Use the right printout

| Printout | What it is for |
| --- | --- |
| Statement of Account / Billing | Review the guest's charges |
| Settlement receipt | Record the completed guest settlement |
| Deposit slip | Record a deposit already received |
| Deposit refund summary | Review recorded refunds and remaining refundable money |
| Exit Gate Pass | Confirm the guest has completed the required exit workflow |
| Order slip | Review room items and orders |
| Turnover slip | Review the cashier's shift count and turnover |

### Find and print a completed folio

After checkout, use the success-message links. If those links are gone, ask the owner or admin to find the guest folio in **Ledger**, which has Receipt, Deposit, and Billing links. Use the print-request or copy button where provided, then the browser's print dialog. Choose the property's correct printer and paper size; several slips are designed for 80mm paper.

Some screen labels say **Official Receipt**. The current receipt document is a **Settlement Receipt**. Configured numbered invoices are a separate feature in Lesson 24. A print request records a request; check the physical printer to confirm paper actually printed.

## 13 Cashier closing a shift and accepting handover

**Go to:** Shifts & Drawer. Do this at the end of duty, while the next operator is ready to receive the cash.

### Outgoing cashier counts and closes

1. Finish and verify payments and cash expenses already handled.
2. Read **Expected Drawer Cash**.
3. Count the physical notes and coins.
4. Enter the number of each bill in **PHYSICAL CASH DRAWER COUNT**. For three ₱1,000 bills, enter `3`, not `3000`.
5. Enter the **Coins Total** as a peso amount. For ₱47.50 in coins, enter `47.50`.
6. Check **Counted Drawer Cash** and **Audit Discrepancy / Variance**.
7. Recount any difference. Add **Handoff Notes** explaining incidents and outstanding work.
8. Click **Close Shift & Record Count**, then confirm.
9. Confirm the shift is closed and its handover is pending. Review the turnover slip.

Closing the shift stops further payments under that drawer. It does not check out guests who are still staying.

### Incoming cashier counts and accepts

1. Sign in using your own incoming cashier account.
2. Open **Shifts & Drawer** and find the pending handover.
3. Count the received cash yourself.
4. Enter **Incoming count** and **Handover notes**.
5. Click **Count and accept handover**.
6. If accepted, confirm **Handover accepted; your new drawer is open**. You are ready to take payments. Do not start another shift.

### If the counts differ

The handover becomes **disputed**. An independent admin or owner must review and execute the handover-variance approval. The designated incoming cashier then returns and accepts the reviewed count. See Lesson 17.

There are two possible differences: the outgoing count versus expected cash, and the incoming count versus outgoing cash. Either may need its own review. Keep the actual counts and explain the difference; do not change them simply to get past a block.

## 14 Cashier expenses and drawer movements

### Record cash actually paid from the drawer

**Go to:** Shifts & Drawer, **Log Shift Expense (Cash Out)**.

1. Confirm this is your active drawer.
2. Choose the expense category.
3. Enter what was bought or paid for in **Disbursement Description**.
4. Enter the actual amount paid, receipt or invoice reference, and useful notes.
5. Use the expense form's submit button.
6. Confirm the expense appears and expected drawer cash is reduced.
7. Keep the supporting receipt for the admin or owner review.

This form records money already paid out. It creates a paid expense awaiting review. Do not also record the same payout as an owner expense voucher.

**Example:** expected cash was ₱2,000 and you paid ₱150 for cleaning supplies. Recording that drawer expense reduces expected cash to ₱1,850.

### Move cash without treating it as an expense

In **Approvals & Tasks**, use the appropriate request:

- **Drop drawer cash to safe:** cash leaves the register but remains property money.
- **Add drawer float:** cash enters the register from the stated source.
- **Deposit drawer cash to bank:** cash leaves the register for the bank.

Select the current drawer, enter the actual amount, destination or source, movement reference, and reason. Submit, obtain independent review, and execute when the movement is actually carried out. Check transaction history and expected cash afterward.

These movements transfer money; they are not new guest sales. The current application records them but does not transfer money through a banking service.

## 15 Cashier stock and transaction checks

### Check your recorded payments

**Go to:** Transactions & Audit.

1. Search the folio reference, order reference, or GCash reference.
2. Use **From**, **To**, **Kind**, or **Shift**, then click **Filter**.
3. Confirm the date, amount, source, cashier, and shift.
4. Use **Audit history** to review recorded actions.

Positive payment amounts usually mean money received; refunds and cash leaving the drawer appear as negative movements. **Collections include deposits and refunds. They are not the same as earned revenue.** Cashiers normally see their own entries.

**Recorded; provider verification pending** means the GCash entry exists, but a reviewer has not matched it to an imported provider statement yet. It does not mean you should collect the payment a second time.

### Adjust or restock an item

**Go to:** Stock, item, **Adjust / Restock**.

1. Count the item physically.
2. Enter **New Exact Physical Count** as the final quantity on hand.
3. Choose the reason and add the supplier invoice or explanation.
4. Submit the form. It goes to **Approvals & Tasks**.
5. Have a different admin or owner review and execute it.
6. Refresh Stock and check the new quantity and movement history.

**Example:** the shelf has 12 bottles and you receive 8 more. Enter `20` as the new exact count, not `8`.

Use **Cashier Shift Recount** for several items, and **Submit Shift Recount** to send the counts for review. Leave items you are not counting unchanged. Stock is updated after approved execution. **Export Movement CSV** downloads the history for review.

## 16 Owner daily quick start

The owner's job is to check that the guest records, actual money, and recorded money agree, then resolve exceptions and preserve a reviewed day.

1. **Front Desk:** review occupied rooms, overdue stays, and pending force-checkout requests.
2. **Shifts & Drawer / Shift Reconciliations:** review the current operator, closed cash counts, differences, and pending handovers.
3. **Approvals & Tasks:** review requests raised by someone else. Check evidence and complete approved execution where required.
4. **Transactions & Audit:** investigate unusual payments, refunds, or adjustments.
5. **Expenses & Petty Cash:** review paid vouchers and their sources.
6. **Stock:** review low stock, recount requests, and unexplained changes.
7. **Daily Finance:** select the business date, match statements, and resolve the listed close checks.
8. **After the cutoff:** close a ready business day. Prepare an export only when accounting setup is approved.

### Owner permissions in plain language

The owner can review property-wide finance, change master prices, create accounts, reset staff passwords, deactivate accounts, edit accounting setup, and reopen a closed business day. Admins share many operational and financial functions, but account changes, accounting setup edits, and day reopening are owner functions in the current version.

An owner still cannot approve their own request or review their own prepared export. Arrange a second authorized admin or owner for independent review. A normal cashier cannot act as the financial reviewer.

If you work at the cashier desk yourself, follow the cashier lessons too. Covering another operator's drawer requires an override reason when the relevant form requests one.

## 17 Owner reviewing approvals and cash differences

**Go to:** Approvals & Tasks, **Property review queue**.

### Review a request

1. Read the request type, requester, folio or payment number, recorded details, reason, and evidence.
2. Compare it with the guest record, receipt, drawer count, or other supporting information.
3. Enter a clear **Review reason**.
4. Use **Approve recorded details** or **Reject**.
5. For most approved requests, a second step is needed: the requester or authorized reviewer clicks **Execute approved action**.
6. Check that the status becomes **executed** and the affected record changes as expected.

**Approved does not usually mean finished.** Refunds, rate changes, stock changes, and cash movements apply at execution. Paid expense review is an exception: approving its review records the reviewed outcome immediately.

If the source changed after the request, the approval may be stale. Recheck the current record, cancel an unexecuted request with a reason where allowed, and submit accurate details. Do not keep trying to execute an old request.

### Review the outgoing cash variance

When a cashier closes with a count different from expected cash, the system creates **shift variance** review. Check the turnover count, payments, change, expenses, and cash movements. Record the conclusion, approve if supported, and execute the action if it remains approved. Review preserves the actual shortage or overage; it does not rewrite the count.

### Review a disputed handover

The system creates **handover variance** when the incoming cashier counts a different amount from the outgoing cashier.

1. Recount with the relevant people and investigate the difference.
2. Find the matching handover-variance request and check the incoming recorded amount.
3. A reviewer different from the requester records the review and approves supported details.
4. Execute the approved action. The handover becomes **variance_approved**.
5. The designated incoming cashier returns to **Shifts & Drawer** and accepts that exact reviewed count.

The new incoming shift opens after acceptance. An outgoing variance and a handover variance are separate reviews, even when they involve the same drawer.

## 18 Owner unpaid stays and expense corrections

### A guest leaves without settling

The cashier uses **Force** on the room and **Request Force Checkout**, selects the incident reason, and clicks **Submit for Approval**. This sends a write-off request; it does not immediately clear the room or create a normal paid checkout.

As reviewer:

1. Open **Approvals & Tasks** and find the **write off** request.
2. Check the room, guest, current balance, and incident reason.
3. Investigate the unpaid amount and record the review reason.
4. Approve supported details, then execute the approved action.
5. Check **Loss Slips** for the resulting loss record and printable slip.
6. Confirm the room status and arrange physical preparation for the next guest.

If the bill changes while approval is pending, the recorded amount may no longer be valid. Recheck the bill and request details. A write-off records unpaid money as a loss; it is not money collected from the guest.

### Review paid operating expenses

**Go to:** Expenses & Petty Cash for vouchers, and Approvals & Tasks for their reviews.

Check the amount, receipt, expense date, category, and **Payment Source**. Cash Drawer Register affects the cashier drawer. Executive Petty Cash Fund and Bank / Online Wire are recorded separately.

Use **Save Expense Voucher** to record an actual paid expense with the correct source. A drawer payment needs an open drawer and the current business date. If covering another cashier's drawer, enter the override reason when prompted.

A rejected paid expense remains paid money and requires correction. Rejecting it does not put cash back into the register. Use **Request cash return** on the expense row to create an **expense reversal** request with a reason and evidence. Review and execute it only when the returned-money correction actually occurs. The original voucher remains in history.

Before adding a voucher, check whether the cashier already recorded the payout. Recording it twice would reduce the expected cash and reported result twice.

## 19 Owner matching GCash and bank statements

**Go to:** Daily Finance.

Matching means checking that a recorded payment or bank movement agrees with the provider's statement. Typing a GCash reference at checkout records it; statement matching is the independent verification step.

### Import the statement

1. Click **Download CSV template**.
2. Fill its columns in the same order: `reference`, `posted_at`, `gross_amount`, `fee_amount`, `net_amount`.
3. Use actual provider data. For every row, net must equal gross minus fees.
4. Choose **GCash** or **Bank** under **Statement import**.
5. Choose the prepared CSV and click **Import statement**.
6. Check the imported rows under **Recent statements & matching**.

Timestamps without an offset are interpreted using Asia/Manila. A file can contain at most 2,000 rows. Exact duplicates are skipped, but a changed row can become a separate import; check before re-importing.

### Match a row to recorded movements

1. Find its recorded source entries under **Day movements** or **Transactions & Audit**.
2. For GCash, use the **Payment IDs**. For a bank deposit, use the **Bank movement IDs**.
3. Enter the exact IDs, separated by commas when grouping entries, such as `12,13`.
4. Confirm the provider, reference, direction, and combined gross amount agree.
5. Click **Match exact total** and confirm **Matched**.

**Example:** gross payment ₱1,000, fee ₱10, net ₱990. Match source GCash payment entries totaling ₱1,000. The ₱10 is the provider fee; it does not reduce the guest's recorded payment.

The reviewer must be different from the person who recorded the source movement. You cannot reuse a payment already matched to another row.

For an incorrect unmatched import, use **Approvals & Tasks → Exclude an incorrect statement import**, provide the reason, obtain independent review, and execute. The original row remains recorded. A matched row cannot be excluded through that workflow.

## 20 Owner closing and reopening a business day

**Go to:** Daily Finance.

### Understand the reporting day

The business-day cutoff is **6:00 AM, Asia/Manila**. A business date covers 6:00 AM on that date up to, but not including, 6:00 AM the next day.

**Example:** business date 10 October covers 10 October at 6:00 AM through 11 October just before 6:00 AM. A payment at 11 October 2:00 AM belongs to the 10 October business day.

This cutoff groups financial movements. It does not force guests to leave or change their paid stay length.

### Close a reviewed day

1. Select the **Business date** and click **Review day**.
2. Check the displayed start and end times.
3. Read every item under **Close checks**.
4. Resolve open intersecting drawers, pending handovers, cash-variance reviews, paid-expense reviews, unresolved requests, and unmatched statements as listed.
5. Check guest deposits and any historical records needing reconciliation.
6. Wait until that day's next-morning 6:00 AM cutoff has passed.
7. Enter the closing reason and click **Close business day**.
8. Confirm **closed** and the preserved version.

A shift that overlaps the selected day can block closing until it is counted and closed. Coordinate turnover with the cashiers. Guests still staying can remain open; you do not need to force-check them out to close a financial day.

### Reopen to correct a closed day

Only the owner can reopen it through the current screens.

1. Select the closed day and document why a correction is needed.
2. Use **Reopen with retained history** and enter the reason.
3. Make the authorized correction and complete any review or matching it needs.
4. Close the day again and confirm the new version.

The previous snapshot is retained. Existing exports of the old version become superseded. If an export was delivered already, coordinate the correction export with the accountant before sending it.

## 21 Owner accounting CSV exports

**Go to:** Daily Finance, selected closed business day.

This feature prepares accounting data for manual delivery. It does not connect directly to an external accounting application.

### Prepare review and deliver

1. Confirm the business day is closed and reviewed.
2. Confirm the accountant-approved configuration and account mappings are enabled in **Accounting setup**.
3. Click **Prepare reviewed CSV batch**. The prepared batch starts as **draft**; its button name does not mean review has already happened.
4. Under **Accounting export outbox**, check the batch number, day, version, destination, and lines.
5. A different authorized admin or owner uses **Approve export**.
6. After review, use **Download reviewed CSV**.
7. Send or import the file through the property's agreed accounting process.
8. Enter the actual delivery reference and click **Record manual delivery**.
9. Confirm the batch status is **delivered**.

The preparer cannot review their own batch. Download and delivery acknowledgment are separate actions. Recording manual delivery does not upload a file or notify the accountant.

### If the day was reopened

Use the export for the latest reclosed version. An export that was delivered earlier can require a correction batch containing reversals of the previous export plus the new version. Coordinate that batch with the accountant so they do not import both versions as ordinary new sales.

P&L CSV reports and accounting export batches serve different purposes. A P&L report is a summary; the reviewed accounting batch contains mapped accounting entries.

## 22 Owner reading reports without mixing totals

### Which page answers which question

| Page | Use it to answer |
| --- | --- |
| Front Desk | Which rooms are in use and which stays need attention? |
| Transactions & Audit | Which money movements and actions were recorded? |
| Ledger | Which guest bills, walk-in orders, and vouchers are recorded? |
| Shift Reconciliations | What did the cashier expect and count at closeout? |
| Reports & Audits | What is the recent operational and sales picture? |
| Executive P&L | What is the result for the selected reporting period? |
| Daily Finance | Is this 6:00 AM business day reconciled and ready to close? |
| Loss Slips | Which unpaid stays were approved as losses? |

In **Executive P&L**, select the period or custom start and end dates, click **Filter**, and use **Export CSV Report** when needed. In **Ledger**, use the guest folio's Receipt, Deposit, or Billing links to inspect its printouts.

### Why two totals can be different

- **Opening float** is starting cash, not earned revenue.
- **Guest deposits** are money held for guests until applied or refunded.
- **Change** is part of tendered cash returned to the guest.
- **GCash** is a digital collection, not physical drawer cash.
- **Refunds** reduce money collected and remain visible as separate records.
- **Cash drops and bank deposits** move property money; they are not sales.
- **Write-offs** are unpaid losses, not cash collections.

The P&L uses the system's settled-sale reporting and recorded costs and tax. It is only as complete as those records. An active stay is not a finished paid sale.

**Date warning:** Daily Finance uses the 6:00 AM business day. Transaction date filters and the P&L's date ranges use calendar-day boundaries; Reports & Audits uses its displayed recent period. Check the time window before comparing their totals.

## 23 Owner staff accounts prices and stock

### Create a staff account

**Go to:** Staff Accounts & RBAC. RBAC means the role determines the person's access.

1. Open the new-staff form.
2. Enter the person's name and unique login email.
3. Choose the role: cashier, kitchen, admin, owner, or customer display.
4. Enter a unique initial password and click **Create Staff Account**.
5. Give the login details privately to that person and confirm they can sign in.

Use a cashier role for cashier duties. Reserve owner access for people who should control accounts and accounting configuration. The current minimum password length is six characters; use a stronger unique password than that minimum.

### Reset or deactivate access

Use **Reset Staff Password** to set a new password. The employee may need to sign in again. Use **Deactivate** when access should stop. Deactivation preserves transaction history. The owner cannot deactivate their own current account through this action. The current page does not provide a general account reactivation or role-editing form.

Complete any drawer turnover before removing the current cashier's access.

### Change room and catalog prices

**Go to:** Master Pricing Editor.

1. Find the exact room and edit its displayed rate tiers.
2. Save that room's row and confirm the success message.
3. For a POS item, edit its price and availability, then save its row.
4. Tell operators when the new prices take effect.

Master room-rate changes apply to future check-ins. An existing stay's stored base charge needs its own approved folio correction. Existing order lines keep their recorded sale price.

Use the add-item form for a new name, category, price, tracking choice, initial stock, reorder level, availability, and kitchen-hours option. Retiring an item removes it from new sales while preserving history. Use **Stock** and its approval workflow for later exact-count adjustments.

### Put a vacant room under maintenance

Use its maintenance action in **Master Pricing Editor**, and use it again when the room is ready to return to available. An occupied room or active folio blocks this change. The current room workflow has available, occupied, and maintenance states; physical cleaning still needs to be coordinated by staff.

## 24 Owner accounting setup and configured invoices

**Go to:** Daily Finance, **Accounting setup**.

The owner should complete this page using the property's accountant-approved information. The tutorial describes the software fields; the accountant supplies the actual legal, tax, numbering, and account details.

### Complete approved setup

1. Enter the registered legal name, address, tax identification number, and registration details.
2. Enter the **Accountant approval reference**.
3. Select the approved **Tax treatment**.
4. If needed, enter the approved tax rate in basis points. **100 basis points means 1%.**
5. Enter the approved invoice series and next sequence.
6. Enter the **Opening balance reconciliation reference** and **Accounting CSV destination**.
7. Fill every account mapping needed for exports using the accountant's account names or codes.
8. Enable configured invoices and reviewed CSV exports only when the required setup is complete.
9. Click **Save approved configuration** and confirm success.

Tax extraction records the configured tax portion within the sale; it does not add another tax charge to the guest's displayed price. The cutoff remains 6:00 AM, Asia/Manila.

### Issue a configured invoice

1. Complete a paid guest checkout or paid walk-in sale.
2. Open **Approvals & Tasks**, then **Configured invoices**.
3. Select the correct completed source and click **Issue / open invoice**.
4. Verify the invoice number, guest or source, totals, and deposit application.
5. Use **Record print request**, then print through the browser and check the paper output.

Invoices need approved configuration and an open financial day for their original sale. Issue required invoices before closing that day. Numbers already issued cannot be reused or moved to a different series through setup.

For a Senior Citizen or PWD paid folio under VAT-inclusive configuration, **Statutory invoice tax basis** needs the accountant-approved taxable amount and evidence. Submit it, obtain independent review, execute it, then issue the invoice. Do not guess a tax basis to clear an error.

An issued invoice retains its recorded details. The current screens do not provide a general invoice edit or void workflow. Refer a required correction to the owner and accountant.

## 25 Common problems and what to do

### Complete Official Check-Out is disabled

Read the drawer notice above the bill. **Cashier shift not started:** start with actual opening cash. **Waiting for drawer handover:** a different incoming cashier counts and accepts. **Drawer assigned to another cashier:** arrange the proper turnover. **Drawer frozen:** have the owner investigate. **Cashier access required:** use an account authorized to record payments.

After resolving the drawer, reload checkout and recheck the bill. The earlier message **Open an unfrozen shift before recording money movements** means the same payment control was blocking you. An older installation may need the latest code and a server restart by the person maintaining it.

### I opened the shift earlier but cannot pay now

Check whether it was subsequently closed. Closing leaves handover pending. Signing out and back in does not reopen it. Ask the incoming cashier to complete handover. Do not click Close Shift simply to finish a guest checkout.

### A GCash reference is required

Enter the actual reference for the GCash amount received. Do not enter cash as GCash to work around a drawer problem. For mixed guest payment, use Split and enter both amounts correctly.

### Actual payment must cover the bill

Check the final balance, selected payment method, and actual amounts entered. GCash cannot exceed the remaining bill. For cash, enter the full cash tendered; the system calculates change.

### Business day is closed

The owner must select that day in Daily Finance and reopen it with a recorded reason before authorized corrections. Changing the computer clock or resetting the database is not a correction workflow.

### A different reviewer is required

The same person cannot request and approve their own action, match their own recorded source movement, or prepare and review their own export. Use another authorized admin or owner account operated by that reviewer.

### The page timed out or showed an error after payment

Check transaction history, the folio status, and the success result before retrying. Confirm whether the money was actually received. Give the owner the room, folio or order reference, approximate time, entered amounts, and exact error. Do not charge the guest a second time while investigating.

### My printout did not come out

Check the printer, paper, connection, selected paper size, and print dialog. Use the appropriate copy or print-request button when provided. A print request in history does not prove paper printing succeeded.

### Stock will not let me sell an item

Check the item's available flag, tracked quantity, required ingredient stock, and kitchen availability. Refresh after an approved restock. Ask the supervisor to investigate if the physical count and system disagree.

### Daily Finance refuses to close

Read the Close checks list and fix each named item. An unresolved approved request still needs execution or a valid cancellation. An actual rejected paid expense needs correction. Historical sales or deposits without complete payment records need the owner and maintainer's reconciliation, not made-up replacement payments.

## 26 Cashier checklist to keep beside the desk

### Start of duty

- [ ] I am signed in using my own account.
- [ ] I counted the actual cash received.
- [ ] I started a shift or accepted the previous handover.
- [ ] The usable open drawer is assigned to me.
- [ ] I reviewed occupied rooms, reservations, overdue stays, and handoff notes.

### Every guest checkout

- [ ] Correct room, guest, and folio.
- [ ] Correct stay, extensions, overtime, add-ons, and discount reference.
- [ ] Deposit application and remaining refund checked.
- [ ] Actual cash and GCash entered in the correct fields.
- [ ] GCash reference entered where required.
- [ ] Change checked and given correctly.
- [ ] Checkout submitted and success confirmed.
- [ ] Receipt and gate pass handled.
- [ ] Room readiness communicated to the responsible staff.

### End of duty

- [ ] Received payments and actual expenses verified.
- [ ] Physical notes and coins counted accurately.
- [ ] Differences recounted and explained in handoff notes.
- [ ] Shift closed once with its final count.
- [ ] Turnover slip reviewed.
- [ ] Incoming cashier counted and accepted, or unresolved handover was escalated.
- [ ] I logged out.

**Remember:** guest checkout ends a guest stay. Closing your shift ends your cashier duty.

## 27 Owner checklist for daily review

### Operational and control review

- [ ] Overdue stays and unpaid-departure requests reviewed.
- [ ] Drawer operator, outgoing counts, and handovers checked.
- [ ] Cash shortages or overages have supported independent reviews.
- [ ] Refunds, rate changes, stock changes, and cash movements checked.
- [ ] Approved actions executed where required.
- [ ] Paid expenses reviewed; rejected paid items corrected.
- [ ] Cash and GCash records compared with evidence.

### Business-day close

- [ ] Correct business date and 6:00 AM window selected.
- [ ] Cutoff has passed.
- [ ] Intersecting cashier shifts are closed and their required reviews are resolved.
- [ ] Handover and approval exceptions resolved.
- [ ] GCash and bank statement matching completed.
- [ ] Required configured invoices issued before closing.
- [ ] Deposit and historical-record exceptions investigated.
- [ ] Close checks are clear.
- [ ] Business day closed with a reason and its version checked.

### Accounting delivery when enabled

- [ ] Correct closed day and approved accounting setup.
- [ ] Export prepared and independently reviewed.
- [ ] Latest reviewed CSV downloaded.
- [ ] File actually delivered through the agreed process.
- [ ] Manual delivery reference recorded.
- [ ] Corrections coordinated if an earlier version was already delivered.

## 28 Practice walkthrough for staff training

Use a separate training installation with test data. These tasks create real stays, stock movements, and money records when performed in the live system.

### Practice a cashier duty

1. Start a training drawer with ₱1,000 actual test float.
2. Check a made-up guest into an available room for a 3-hour tier.
3. Add one room item and verify it on the bill. Note how stock changes.
4. Record a ₱200 test deposit using the separate deposit form.
5. Add one extension and verify the stay time and charge.
6. Open checkout and read the actual calculated final balance.
7. Apply the deposit and enter sufficient test cash. Check the calculated change.
8. Complete checkout and confirm the settled guest record and receipt.
9. Find the deposit and settlement entries in Transactions & Audit.
10. Count the test drawer and close the shift. Use another training cashier account to count and accept handover.

### Practice owner review

1. Have a training cashier submit a supported rate correction on an active test folio.
2. Sign in as a different training owner or admin. Review, approve, and execute it.
3. Return to the cashier view, reload the bill, and confirm the correction.
4. Submit a test restock with the final physical count. Review and execute it; verify Stock and the movement history.
5. Inspect Transactions & Audit and find who performed each action.
6. Review a completed past test business day in Daily Finance and identify the close checks. Close only after its cutoff and all required control steps.

### Questions the trainee should be able to answer

- Which button finishes one guest's bill?
- When should a cashier close their shift?
- Can the outgoing cashier accept their own handover?
- Does typing payment details record money immediately?
- Does Approved always mean the action is finished?
- Are GCash collections part of the physical cash count?
- Is the business-day cutoff the same as guest checkout time?

**Answers:** Complete Official Check-Out; end of cashier duty; no; no, the final posting action is needed; no, most need execution; no; no.

## 29 What this guide covers

This edition describes the current Sedona version 3 screens and enforced workflows. It includes the drawer guidance introduced in commit `5845917`, the shared single-drawer model, independent reviews, the 6:00 AM Asia/Manila cutoff, and manual reviewed CSV delivery.

Some functions need owner-approved configuration before use. The application records GCash and bank references and imports statements; it does not initiate external wallet or bank transfers. Accounting export delivery is manual. Physical cleaning, cash counting, refunds, and printing still need the responsible staff to perform and verify them.

The operator steps were checked against the current navigation, views, controllers, and services for check-in, checkout, reservations, POS, shifts, stock, approvals, reporting, finance, accounts, and pricing. The guide does not claim that a settlement slip or the system's tax settings alone establish legal compliance.

Keep a recovery plan for the server computer and its database with the person maintaining the system. There is no built-in backup and restore screen in this edition. A Git clone contains the application code; it is not a copy of the live guest and payment database. Report exports are useful records, but they do not replace a database backup.

When asking for help, provide your role, screen name, room or source reference, approximate time, exact message, and what happened immediately before it. Keep passwords and unnecessary guest information out of the help message.
