# User manual

Lubie Construction Works project monitoring dashboard. Version 0.5. All screens work on a phone
(360 px wide) and on a computer. Dates show as DD/MM/YYYY and money as GH₵.

## For everyone

- One address and one login page. After login the system opens your own dashboard. You never
  choose a role.
- Forgot your password: ask the Administrator. They set a temporary one and you choose a new one
  at the next login.
- Five wrong passwords lock the account for 15 minutes.
- You are logged out after 20 minutes without activity.
- The bell shows your alerts. Open it to read them. Acknowledge tells others you have seen it.
- The half-moon button switches between light and dark mode.
- "Data last updated" shows when the figures last changed. The page checks for changes every
  minute and refreshes itself.
- On the free online server the first visit after a quiet period takes about a minute. The page
  says "Waking up the server". Wait; do not reload.
- Install on a phone: open the address in Chrome, menu (three dots) > Install app.

## Site Engineer: quick start on a phone

1. Log in. Tap your project. "My activities" lists each activity with its planned and approved %.
2. Tap Update progress on an activity.
3. Check the date, type the total % complete so far (not just today's work), choose a delay
   reason if work was slowed, add a short remark, tap Submit for approval.
4. The entry shows "Submitted, waiting for PM". The Project Manager approves or returns it. A returned
   entry shows the reason; update the activity again and it replaces the returned one.
5. No signal: fill the form as usual. The phone saves it ("Saved on this phone") and sends it by
   itself when the connection returns. A blue notice at the top of My activities counts waiting entries; tap View to see them.
6. Work stoppage or safety incident: choose it as the delay reason. Management gets an urgent alert.
7. Add activity: for work not yet listed. Cost is set by the Project Manager.
8. Materials and labour tab (task T8):
   - Materials: tap Received when a delivery arrives (type the delivery note in Remarks) and Used
     when material goes into the work. "In store" is received minus used. An amber badge means the
     balance is below the reorder level and the Project Manager has been alerted.
   - Using more than is in store shows a warning; check the count, then confirm only if it is right.
   - Request: ask the Project Manager for more material, with the date you need it. You can cancel
     your request until it is approved. When it arrives, tap Delivered; the quantity is added to the store.
   - Labour: tap Attendance and type the number of workers present today.
   - Equipment: tap Hours for hours worked, or Status to report Faulty, Idle, Off site or Working.
     Faulty alerts the Project Manager at once.
   - All of these also work without signal and are sent later, like progress.

You do not see budgets, spending, rates or supplier prices. This is by design.

## Project Manager: approvals and schedule

- Approvals tab: every waiting progress entry with the last approved %. Approve, or type a
  reason and Return. Only approved progress counts in the figures.
- Activities: add or edit activities, set budgeted cost, link predecessors, archive work that
  is no longer needed (nothing is deleted).
- Milestones: due dates and achieved dates. Overdue milestones appear to the client as a notice.
- Cost: budget, spending and earned value (read-only). Request a budget revision with a reason;
  the Director decides.
- Resources: materials with store balance, value used and reorder level; labour attendance and
  cost; equipment status and hours. Set unit prices, daily rates and hourly rates (only you can),
  approve or cancel material requests (not your own), mark deliveries, archive items no longer used.
- Team: add or remove Site Engineers, Quantity Surveyors and the client for the project.
- Settings: project alert thresholds inside the Director's limits, project status
  (planning, active, on hold, completed, archived).

## Quantity Surveyor: cost guide

- Cost: budget used, cost variance, CPI, spending by category.
- Expenditure: record a payment with date, amount, category, voucher number and supplier.
  Tick "I have checked the amount and date" before saving. The same voucher number cannot be
  entered twice. Wrong entry: Void with a reason; it stays in the history.
- Valuations: add a certificate, certify the amount, record payments. A payment above the
  outstanding balance is refused. Certificates unpaid after the payment period raise an alert.
- Budget: request a revision; the Director approves or rejects it. You cannot approve your own.
- Thresholds: propose a different alert threshold; the Director decides.
- Resources: read-only view of materials, labour and equipment with rates and values, and the
  Labour, materials and equipment report (PDF or CSV).

## Director: overview

- Portfolio: every project on one screen with completion, planned %, SPI, CPI, budget used,
  delayed activities, next milestone and open alerts. Colour, icon and text always appear
  together.
- Approvals: budget revisions and threshold proposals waiting for you. A rejection needs a note.
- Company thresholds: set company-wide values and the limits Project Managers must stay within.
- Open any project for its overview, cost and payments, and resources (stock, labour, equipment).
- SMS (when switched on) reaches you for critical events: long delays, overruns above the
  limit, stoppages, revisions waiting.

## Client guide

- One page per project: overall progress (approved work only), planned progress by today,
  milestones, contract sum, certified, paid and outstanding amounts, and notices such as an
  overdue milestone.
- Download summary (PDF) gives the same page as a file.
- You do not see internal costs, labour, revisions, internal alerts or other clients' projects.

## Administrator guide

- Summary: projects and users by status, open alerts, last backup, locked accounts, and
  Set-up checks (each row says OK or what to fix).
- Users: create a user with a role and a temporary password (give it in person), edit, deactivate,
  reset a password (signs the user out everywhere), unlock a locked account.
- Projects: create a project and assign its team. You do not see budgets or payments.
- Settings: refresh interval, sign-out time, lockout rules, password length, SMS on or off and
  daily and monthly SMS limits, offline mode. Send a test SMS (sandbox sends nothing).
- Audit trail: every login, change, approval, export and refused access, with filters.
  Nobody can edit or delete it.
- Backups: Create and download backup gives an encrypted file. Keep it off the computer.
  The file opens only with the backup key held in the server settings.
