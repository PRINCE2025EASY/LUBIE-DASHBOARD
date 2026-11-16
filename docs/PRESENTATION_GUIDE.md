# Presentation guide (local XAMPP)

For showing the dashboard to your supervisor on your own computer, without internet.

## One-time set-up (about 2 minutes)

1. XAMPP Control Panel: start **Apache** and **MySQL**.
2. In File Explorer open `C:\xampp\htdocs\lubie-dashboard` and double-click **demo-setup.bat**.
   - Root password: press Enter (XAMPP default has none).
   - If the database already has tables, type `RESET` to replace them with the presentation data.
   - Choose one password (10+ characters, letters and digits) for all presentation accounts.
     Write it down for the presentation.
3. The window ends with the doctor check: every line should start with OK.

What the set-up does: builds the local database `lubie_dashboard` from the schema, loads the
presentation data (your list of people and the three Nadowli projects, with illustrative
schedules and amounts), creates the restricted `lubie_app` account with a new random password,
and points `.env` at the local database. Your previous `.env` (Aiven) is kept as
`.env.before-demo`; copy it back over `.env` to use Aiven again.

Run it again any time you want a fresh start before a presentation.

## On the day

1. Start Apache and MySQL, then double-click **demo-start.bat**. It checks the set-up and opens
   http://localhost/lubie-dashboard in your browser.
2. Use Chrome. For a phone view press F12, then Ctrl+Shift+M.

## Accounts

| Username | Role | Project(s) |
|---|---|---|
| admin | System Administrator | all (set-up only, no finance) |
| director | Director | all projects (read, approvals) |
| pm1 | Project Manager 1 | Feeder road (LCW-P1), Six-unit classroom block (LCW-P3) |
| pm2 | Project Manager 2 | 24 Hour Economy Market (LCW-P2) |
| qs1 | Quantity Surveyor 1 | LCW-P1, LCW-P2 |
| qs2 | Quantity Surveyor 2 | LCW-P3 |
| se1 | Site Engineer 1 | LCW-P1 |
| se2 | Site Engineer 2 | LCW-P2 |
| se3 | Site Engineer 3 | LCW-P1, LCW-P3 |
| se4 | Site Engineer 4 | deactivated (shows that old accounts cannot log in) |
| client1 | Client 1 | LCW-P1 |
| client2 | Client 2 | LCW-P2 |
| client3 | Client 3 | LCW-P3 |

All use the password chosen during set-up.

## Suggested 10-minute demonstration

1. **Login page**: one login for every role; company photo and logo; show the password eye.
2. **se1** (phone view): My activities > Update progress on R05 > 40%, delay reason "Rain or
   flooding" > Submit. Point out: no money is visible to site staff.
   Then Materials and labour (task T8): Attendance for General labourers; Used for cement (note the
   amber "low" badge); Request chippings for next week; Status of the roller > Faulty.
3. **pm1**: Approvals > approve the entry; Overview shows the new completion %, SPI, CPI, the
   planned-versus-earned chart and the budget chart. Open a KPI card's (i) to show the formula.
   Resources: approve the chippings request, mark it Delivered (the store balance rises), and show
   the bell: low cement (A10) and faulty equipment (A14) alerts.
4. **qs1**: Expenditure > record a payment with the confirmation tick; Valuations; Budget.
5. **director**: Portfolio of all three projects; Approvals > approve the waiting budget revision.
6. **client2**: one summary page with progress, milestones and payments; download the PDF.
7. **admin**: Users, Audit trail (every action above is listed), Backups > create a backup.
8. Theme button (half-moon) switches light and dark; offline: F12 > Network > Offline, submit
   progress as se1, then Online again and it is sent by itself.

## If something goes wrong

| Symptom | Fix |
|---|---|
| Browser shows "Not Found" | Apache not started, or wrong address: http://localhost/lubie-dashboard |
| "The database refused the connection settings" | MySQL not started, or run demo-setup.bat again |
| Old page design or theme button not responding | Press Ctrl+Shift+R once (reloads the new files) |
| Forgot the presentation password | Run demo-setup.bat again and type RESET |
