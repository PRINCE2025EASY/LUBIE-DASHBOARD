from common import *

f = DrawioFile(OUTDIR + "02-system-structure-chart.drawio")
LEG = ["Rectangle = module (called top-down)", "Purple rectangle = shared utility module (fan-in)",
       "○ name = data couple; ● name = control couple (flag)", "Arrow = call from calling to called module",
       "Couple arrows ↓ passed down, ↑ returned up", "Fan-in/fan-out counts in the note"]

# ---------------- Page 1: top level ----------------
p = f.page("sc1", "Structure chart - top level", "System Structure Chart (Yourdon/Constantine) - top level", width=2300, height=1100,
           legend=LEG, notation="Yourdon and Constantine structure chart")
p.legend_pos = (1840, 20)
main = p.vertex("main", "0  Lubie Dashboard\nFront Controller and Router", 960, 140, 260, 70, BOX + "fontStyle=1;")
mods = ["1  Authentication", "2  Administration", "3  Project and Activity Management", "4  Progress and Approval",
        "5  Cost and Payment", "6  Resource Management", "7  Alert and Notification", "8  Dashboard and KPI",
        "9  Reporting", "10  Audit and Backup"]
couples = [("○ credentials ↓  ● authenticated ↑"), ("○ user record ↓  ○ result ↑"), ("○ project data ↓  ○ saved id ↑"),
           ("○ progress entry ↓  ● approved ↑"), ("○ cost entry ↓  ○ saved id ↑"), ("○ movement ↓  ○ balance ↑"),
           ("○ project id ↓  ○ alerts ↑"), ("○ project id, date ↓  ○ KPI DTO ↑"), ("○ report request ↓  ○ file stream ↑"),
           ("○ audit event ↓  ● logged ↑")]
mid = {}
for i, m in enumerate(mods):
    x = 40 + i * 220
    mid[i + 1] = p.vertex(f"m{i+1}", m, x, 400, 200, 60, BOX)
    p.edge(main, mid[i + 1], "", ORTHO + "exitX=0.5;exitY=1;entryX=0.5;entryY=0;")
    p.vertex(f"c{i+1}", couples[i].replace("  ", "\n"), x, 300, 200, 40, TEXT + "fontSize=10;align=center;verticalAlign=middle;fontColor=#1f3a68;")
utils = [("U1  Authorise Request\n(role + project scope)", [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]),
         ("U2  Validate Input", [2, 3, 4, 5, 6]),
         ("U3  Write Audit Entry", [1, 2, 3, 4, 5, 6, 9]),
         ("U4  Evaluate Alerts", [3, 4, 5, 6, 8]),
         ("U5  Calculate KPIs", [7, 8, 9]),
         ("U6  Data Access (PDO repositories)", [1, 2, 3, 4, 5, 6, 7, 8, 9, 10])]
for j, (u, callers) in enumerate(utils):
    uid = p.vertex(f"u{j+1}", u, 120 + j * 360, 760, 240, 60, UTIL)
    for c in callers:
        p.edge(mid[c], uid, "", ORTHO + "strokeColor=#9673a6;exitX=0.5;exitY=1;entryX=0.5;entryY=0;")
p.vertex("fan", "Fan-out: module 0 = 10. Fan-in: U1 = 10, U6 = 10, U3 = 7, U2 = 5, U4 = 5, U5 = 3.\nU4 Evaluate Alerts is called by modules 3 to 6 after saves and by 8 on the first dashboard request of the day (no cron). Module 7 owns rule evaluation detail (page 2).",
          40, 900, 820, 90, NOTE)

# ---------------- Page 2: detail ----------------
p = f.page("sc2", "Structure chart - module detail", "System Structure Chart - control modules and sub-modules", width=2300, height=1500,
           legend=LEG, notation="Yourdon and Constantine structure chart")
p.legend_pos = (1000, 1260)
detail = [
 ("1  Authentication", [("1.1 Verify Credentials", "○ username, password ↓ ● valid ↑"), ("1.2 Check Lockout", "○ user id ↓ ● locked ↑"),
                        ("1.3 Create Session", "○ user id ↓ ○ session id ↑"), ("1.4 Resolve Role Dashboard", "○ role id ↓ ○ path ↑"),
                        ("1.5 Change Password", "○ old, new ↓ ● changed ↑")]),
 ("2  Administration", [("2.1 Manage Users", "○ user data ↓ ○ user id ↑"), ("2.2 Reset Password", "○ user id ↓ ● reset ↑"),
                        ("2.3 Manage Settings", "○ key, value ↓ ● saved ↑"), ("2.4 Manage SMS Settings", "○ provider ↓ ● test ok ↑")]),
 ("3  Project and Activity Mgmt", [("3.1 Maintain Projects", "○ project ↓ ○ id ↑"), ("3.2 Assign Users", "○ project, user ↓ ● assigned ↑"),
                        ("3.3 Maintain Activities", "○ activity ↓ ○ id ↑"), ("3.4 Maintain Milestones", "○ milestone ↓ ○ id ↑")]),
 ("4  Progress and Approval", [("4.1 Record Progress", "○ entry ↓ ○ status ↑"), ("4.2 Approve or Return", "○ record id ↓ ● approved ↑"),
                        ("4.3 Sync Offline Entries [It.2]", "○ queued items ↓ ○ results ↑")]),
 ("5  Cost and Payment", [("5.1 Maintain Budget Versions", "○ amount ↓ ○ version ↑"), ("5.2 Approve Revision", "○ version id ↓ ● current ↑"),
                        ("5.3 Record Expenditure", "○ expense ↓ ○ id ↑"), ("5.4 Certify Valuation", "○ amount ↓ ○ status ↑"),
                        ("5.5 Record Payment", "○ payment ↓ ○ outstanding ↑")]),
 ("6  Resource Management", [("6.1 Record Labour", "○ attendance ↓ ○ id ↑"), ("6.2 Record Material Movement", "○ movement ↓ ○ balance ↑"),
                        ("6.3 Update Equipment Status", "○ status ↓ ● faulty ↑"), ("6.4 Track Material Requests", "○ request ↓ ○ status ↑")]),
 ("7  Alert and Notification", [("7.1 Evaluate Rules", "○ snapshot ↓ ○ conditions ↑"), ("7.2 De-duplicate and Clear", "○ conditions ↓ ○ changes ↑"),
                        ("7.3 Resolve Recipients", "○ alert ↓ ○ users ↑"), ("7.4 Dispatch SMS [It.2]", "○ message ↓ ● sent/capped ↑"),
                        ("7.5 Manage Thresholds [It.2]", "○ key, value ↓ ● within limit ↑")]),
 ("8  Dashboard and KPI", [("8.1 Build Snapshot", "○ project id ↓ ○ snapshot ↑"), ("8.2 Compute EVM", "○ snapshot, date ↓ ○ PV, EV, AC ↑"),
                        ("8.3 Classify Schedule Status", "○ activity ↓ ○ status ↑"), ("8.4 Serve Changes Poll", "○ since ↓ ● changed ↑"),
                        ("8.5 Filter DTO by Role", "○ KPIs, role ↓ ○ DTO ↑")]),
 ("9  Reporting", [("9.1 Select Report Data", "○ type, filters ↓ ○ rows ↑"), ("9.2 Render PDF", "○ rows ↓ ○ PDF stream ↑"),
                        ("9.3 Stream CSV", "○ rows ↓ ○ CSV stream ↑"), ("9.4 Client Summary", "○ project id ↓ ○ summary ↑")]),
 ("10  Audit and Backup", [("10.1 Write Audit Entry", "○ event ↓ ● logged ↑"), ("10.2 View Audit Trail", "○ filters ↓ ○ entries ↑"),
                        ("10.3 Create Encrypted Backup", "○ trigger ↓ ○ file stream ↑"), ("10.4 Verify Restore", "○ file ↓ ● counts match ↑")]),
]
for k, (m, subs) in enumerate(detail):
    col, row = k % 5, k // 5
    x0, y0 = 40 + col * 440, 140 + row * 560
    pid = p.vertex(f"d{k}", m, x0, y0, 220, 50, BOX + "fontStyle=1;")
    for s_i, (s, cpl) in enumerate(subs):
        sid = p.vertex(f"d{k}_{s_i}", s, x0 + 170, y0 + 90 + s_i * 90, 220, 50, BOX)
        p.edge(pid, sid, "", ORTHO + "exitX=0.25;exitY=1;entryX=0;entryY=0.5;",
               points=[(x0 + 50, y0 + 110 + s_i * 90)])
        p.vertex(f"c{k}_{s_i}", cpl, x0 + 60, y0 + 70 + s_i * 90, 100, 40, TEXT + "fontSize=9;verticalAlign=bottom;fontColor=#1f3a68;")
rep = f.save()
print(rep["pages"], rep["vertices"], rep["edges"], rep["problems"][:10])
