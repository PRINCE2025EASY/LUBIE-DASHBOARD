from common import *

f = DrawioFile(OUTDIR + "03-data-flow-diagram.drawio")
LEG = ["Grey thick square = external entity", "Rounded box with number = process",
       "Open-ended box 'Dn | name' = data store", "Arrow = data flow (labelled)",
       "Off-page process (dashed) = process of another level", "Levels balanced: see note on each page"]
NOT = "Gane and Sarson"
EXT = "rounded=0;" + WRAP + "fillColor=#f5f5f5;strokeColor=#333333;strokeWidth=3;fontStyle=1;"
PROC = "rounded=1;arcSize=14;" + WRAP + "fillColor=#dae8fc;strokeColor=#6c8ebf;verticalAlign=top;spacingTop=4;"
STORE = "shape=partialRectangle;right=0;" + WRAP + "fillColor=#fff2cc;strokeColor=#d6b656;align=left;spacingLeft=6;"
OFF = "rounded=1;arcSize=14;dashed=1;" + WRAP + "fillColor=#ffffff;strokeColor=#6c8ebf;verticalAlign=top;spacingTop=4;"
FLOW = "edgeStyle=orthogonalEdgeStyle;orthogonalLoop=1;jettySize=auto;endArrow=block;endFill=1;fontSize=10;labelBackgroundColor=#ffffff;"
STRAIGHT = "endArrow=block;endFill=1;fontSize=10;labelBackgroundColor=#ffffff;"

def ext(p, n, x, y, w=170, h=60): return p.vertex(n, n, x, y, w, h, EXT)
def proc(p, num, n, x, y, w=200, h=90, off=False): return p.vertex(f"P{num}", f"{num}\n{n}", x, y, w, h, OFF if off else PROC)
def store(p, d, n, x, y, w=220, h=40): return p.vertex(d, f"{d} | {n}", x, y, w, h, STORE)
def _centre(p, cid):
    c = p.get(cid); return c["x"] + c["w"] / 2, c["y"] + c["h"] / 2
def fl(p, a, b, label, style=STRAIGHT, pts=None):
    pairs = p.__dict__.setdefault("pairs", set())
    if pts is None and style == STRAIGHT and ((b, a) in pairs or (a, b) in pairs):
        (ax, ay), (bx, by) = _centre(p, a), _centre(p, b)
        dx, dy = bx - ax, by - ay; L = max((dx * dx + dy * dy) ** 0.5, 1)
        off = 50 if (b, a) in pairs else -50
        pts = [((ax + bx) / 2 - dy / L * off, (ay + by) / 2 + dx / L * off)]
    pairs.add((a, b))
    return p.edge(a, b, label, style, points=pts)

# ---------- Context ----------
p = f.page("ctx", "DFD Context (Level -1)", "Data Flow Diagram - Context diagram", width=2000, height=1300, legend=LEG, notation=NOT)
p.legend_pos = (1540, 20)
sysp = p.vertex("sys", "0\nLubie Project Monitoring Dashboard", 800, 520, 320, 200, PROC + "fontStyle=1;fontSize=14;")
left = [("Administrator", ["user details, settings, backup request", "credentials"], ["user list, audit trail, backup file"]),
        ("Director", ["revision decision, global thresholds, proposal decision", "credentials"], ["portfolio KPIs, alerts, reports"]),
        ("Project Manager", ["project, activity, milestone data, assignments, approvals, project thresholds, credentials"], ["project dashboard, approval queue, alerts, reports"]),
        ("Quantity Surveyor", ["budget, expenditure, certificates, payments, threshold proposals, credentials"], ["cost KPIs, alerts, reports"])]
right = [("Site Engineer", ["activities, progress entries, delay reasons, resource movements, requests, credentials"], ["activity status, submission status, alerts"]),
         ("Client", ["credentials, summary request"], ["progress and payment summary, milestone notice"]),
         ("SMS Gateway", ["delivery status"], ["SMS message"]),
         ("External Scheduler", ["backup trigger (token)"], ["encrypted backup file"]),
         ("Off-site Backup Storage", [], ["encrypted backup file (Administrator download)"])]
for i, (n, ins, outs) in enumerate(left):
    e = ext(p, n, 80, 160 + i * 260)
    fl(p, e, sysp, "; ".join(ins), STRAIGHT + "exitX=1;exitY=0.3;entryX=0;entryY=%.2f;" % (0.1 + i * 0.22))
    fl(p, sysp, e, "; ".join(outs), STRAIGHT + "exitX=0;exitY=%.2f;entryX=1;entryY=0.8;" % (0.2 + i * 0.22))
for i, (n, ins, outs) in enumerate(right):
    e = ext(p, n, 1660, 300 + i * 200)
    if ins:
        fl(p, e, sysp, "; ".join(ins), STRAIGHT + "exitX=0;exitY=0.3;entryX=1;entryY=%.2f;" % (0.08 + i * 0.2))
    fl(p, sysp, e, "; ".join(outs), STRAIGHT + "exitX=1;exitY=%.2f;entryX=0;entryY=0.8;" % (0.16 + i * 0.2))
p.vertex("ctxnote", "Scope: one system process. Every flow here appears again on Level 0 (balancing). Clients receive in-app notices only; SMS goes to staff for Critical and High alerts.", 800, 780, 320, 100, NOTE)

# ---------- Level 0 ----------
p = f.page("l0", "DFD Level 0", "Data Flow Diagram - Level 0", width=2560, height=1800, legend=LEG + ["* = duplicate copy of an external entity"], notation=NOT)
p.legend_pos = (2100, 20)
CW, CH, X0, Y0 = 680, 540, 40, 140
cells = {"1.0": (0, 0), "2.0": (1, 0), "9.0": (2, 0), "3.0": (0, 1), "7.0": (1, 1), "8.0": (2, 1), "4.0": (0, 2), "6.0": (1, 2), "5.0": (2, 2)}
names = {"1.0": "Authenticate and authorise users", "2.0": "Administer users and settings", "3.0": "Maintain projects and schedule",
         "4.0": "Record and approve progress", "5.0": "Manage cost and payments", "6.0": "Manage resources",
         "7.0": "Calculate KPIs, dashboards and reports", "8.0": "Evaluate alerts and notify", "9.0": "Audit and back up"}
P = {}
for num, (cx, cy) in cells.items():
    P[num] = proc(p, num, names[num], X0 + cx * CW + 240, Y0 + cy * CH + 210, 200, 90)
def slot(num, sl):
    cx, cy = cells[num]; bx, by = X0 + cx * CW, Y0 + cy * CH
    return {"L1": (bx, by + 40), "L2": (bx, by + 160), "L3": (bx, by + 280), "L4": (bx, by + 400),
            "R1": (bx + 500, by + 40), "R2": (bx + 500, by + 160), "R3": (bx + 500, by + 280), "R4": (bx + 500, by + 400),
            "B1": (bx + 160, by + 400), "B2": (bx + 400, by + 460), "T1": (bx + 220, by + 60)}[sl]
N = {}
def dup_ext(key, label, num, sl):
    x, y = slot(num, sl); N[key] = p.vertex(key, label, x, y, 160, 60, EXT); return N[key]
def st(key, label, num, sl, w=220):
    x, y = slot(num, sl); N[key] = store(p, key, label, x, y, w); return N[key]
# externals (duplicates marked *)
for i, n in enumerate(["Administrator", "Director", "Project Manager"]):
    dup_ext(f"x1_{i}", n + " *", "1.0", f"L{i+1}")
for i, n in enumerate(["Quantity Surveyor", "Site Engineer", "Client"]):
    dup_ext(f"x1_{i+3}", n + " *", "1.0", f"R{i+1}")
dup_ext("x2a", "Administrator *", "2.0", "L2"); dup_ext("x2d", "Director *", "2.0", "R2")
dup_ext("x9a", "Administrator *", "9.0", "L2"); dup_ext("x9s", "External Scheduler", "9.0", "R1"); dup_ext("x9b", "Off-site Backup Storage", "9.0", "R3")
dup_ext("x3p", "Project Manager *", "3.0", "L1"); dup_ext("x3s", "Site Engineer *", "3.0", "L3")
dup_ext("x7d", "Director *", "7.0", "L1"); dup_ext("x7p", "Project Manager *", "7.0", "L3")
dup_ext("x7q", "Quantity Surveyor *", "7.0", "R1"); dup_ext("x7s", "Site Engineer *", "7.0", "R3"); dup_ext("x7c", "Client *", "7.0", "L4")
dup_ext("x8p", "Project Manager *", "8.0", "R1"); dup_ext("x8q", "Quantity Surveyor *", "8.0", "R2"); dup_ext("x8m", "SMS Gateway", "8.0", "R4")
dup_ext("x4s", "Site Engineer *", "4.0", "L1"); dup_ext("x4p", "Project Manager *", "4.0", "L3")
dup_ext("x6s", "Site Engineer *", "6.0", "L1"); dup_ext("x6p", "Project Manager *", "6.0", "L3")
dup_ext("x5q", "Quantity Surveyor *", "5.0", "R1"); dup_ext("x5d", "Director *", "5.0", "R3")
# stores
st("D1", "Users and roles", "1.0", "B2", 200); st("D8", "Settings and thresholds", "2.0", "B2", 220); st("D9", "Audit log", "9.0", "B1", 200)
st("D2", "Projects and assignments", "3.0", "R2", 220); st("D3", "Activities and milestones", "3.0", "B2", 220)
st("D10", "Alerts and SMS log", "8.0", "B1", 220); st("D4", "Progress records", "4.0", "R2", 200)
st("D7", "Resources and requests", "6.0", "R2", 220); st("D5", "Budgets and expenditure", "5.0", "L1", 220); st("D6", "Certificates and payments", "5.0", "L3", 220)
node = lambda k: P.get(k) or N[k]
flows = [
 ("x1_0", "1.0", "credentials"), ("x1_1", "1.0", "credentials"), ("x1_2", "1.0", "credentials"), ("x1_3", "1.0", "credentials"), ("x1_4", "1.0", "credentials"), ("x1_5", "1.0", "credentials"),
 ("1.0", "D1", "login state"), ("D1", "1.0", "account, role"), ("1.0", "D9", "login events"),
 ("x2a", "2.0", "user details, settings"), ("2.0", "x2a", "user list"), ("x2d", "2.0", "global thresholds, proposal decision"),
 ("2.0", "D1", "user records"), ("2.0", "D8", "settings, global limits"),
 ("x9a", "9.0", "backup request, audit query"), ("9.0", "x9a", "audit trail, backup file"), ("x9s", "9.0", "backup trigger (token)"),
 ("9.0", "x9s", "encrypted backup file"), ("9.0", "x9b", "encrypted backup file"), ("9.0", "D9", "audit entries, backup log"), ("D9", "9.0", "audit entries"),
 ("x3p", "3.0", "project, activity, milestone data, assignments"), ("x3s", "3.0", "activity data"), ("3.0", "D2", "projects, assignments"), ("3.0", "D3", "activities, milestones"),
 ("D2", "7.0", "projects"), ("D3", "7.0", "activities"), ("D4", "7.0", "approved progress"), ("D5", "7.0", "budget, AC"), ("D6", "7.0", "payments"), ("D7", "7.0", "balances"),
 ("D10", "7.0", "open alerts"),
 ("7.0", "x7d", "portfolio KPIs, alerts, reports"), ("7.0", "x7p", "project dashboard, reports"), ("7.0", "x7q", "cost KPIs, reports"),
 ("7.0", "x7s", "activity status"), ("7.0", "x7c", "progress and payment summary, milestone notice"), ("x7c", "7.0", "summary request"),
 ("7.0", "8.0", "KPI snapshot"), ("x8p", "8.0", "project thresholds"), ("x8q", "8.0", "threshold proposals"), ("D8", "8.0", "thresholds"),
 ("8.0", "D10", "alerts, SMS log"), ("8.0", "x8m", "SMS message"), ("x8m", "8.0", "delivery status"),
 ("x4s", "4.0", "progress entry, delay reason"), ("4.0", "x4s", "submission status"), ("x4p", "4.0", "approve or return"), ("4.0", "x4p", "approval queue"),
 ("D3", "4.0", "activity"), ("4.0", "D4", "progress records"),
 ("x6s", "6.0", "resource movements, requests"), ("x6p", "6.0", "request decisions"), ("6.0", "D7", "resources, usage"),
 ("x5q", "5.0", "budget, expenditure, certificates, payments"), ("x5d", "5.0", "revision decision"), ("5.0", "D5", "budget versions, expenditure"), ("5.0", "D6", "certificates, payments"),
]
for a, b, lab in flows:
    fl(p, node(a), node(b), lab, STRAIGHT)
p.vertex("l0note", "Balancing with the context diagram: every context flow is drawn here between the same external entity (or its duplicate *) and a numbered process. "
         "Processes 2.0 to 6.0 also send audit entries to 9.0 (lines omitted for clarity; shown on Level 1).",
         2100, 320, 420, 140, NOTE)

# ---------- Level 1: 4.0 progress approval and 7.0 KPI ----------
p = f.page("l1a", "DFD Level 1 - progress and KPI", "Data Flow Diagram - Level 1: 4.0 Record and approve progress, 7.0 Calculate KPIs", width=2200, height=1300, legend=LEG + ["* = duplicate copy of an external entity"], notation=NOT)
p.legend_pos = (1720, 20)
se, pm = ext(p, "Site Engineer", 80, 200), ext(p, "Project Manager", 80, 700)
cl = ext(p, "Client", 1760, 1000)
pm2, se2 = ext(p, "Project Manager *", 1760, 860), ext(p, "Site Engineer *", 1760, 1140)
d3, d4 = store(p, "D3", "Activities and milestones", 520, 140), store(p, "D4", "Progress records", 980, 420)
d2, d5, d6, d7 = store(p, "D2", "Projects and assignments", 1440, 380), store(p, "D5", "Budgets and expenditure", 1760, 520), store(p, "D6", "Certificates and payments", 1760, 600), store(p, "D7", "Resources and requests", 1760, 680)
p41, p42 = proc(p, "4.1", "Validate entry (range, date, scope)", 460, 300), proc(p, "4.2", "Store submitted entry", 460, 540)
p43, p44 = proc(p, "4.3", "Approve or return entry", 460, 800), proc(p, "4.4", "Confirm status to submitter", 960, 800)
p71, p72 = proc(p, "7.1", "Build project snapshot", 1440, 560), proc(p, "7.2", "Compute EVM and schedule status", 1440, 800)
p73 = proc(p, "7.3", "Filter by role and serve", 1440, 1040)
p8 = proc(p, "8.0", "Evaluate alerts and notify (Level 1 page 3)", 960, 1060, off=True)
for a, b, lab in [(se, p41, "progress entry, delay reason"), (d3, p41, "activity, dates"), (p41, p42, "valid entry"), (p41, se, "validation errors"),
                  (p42, d4, "submitted record"), (d4, p43, "approval queue"), (pm, p43, "approve or return (reason)"), (p43, d4, "status update"),
                  (p43, p44, "decision"), (p44, se, "submission status"), (p43, pm, "approval queue"),
                  (d4, p71, "approved progress"), (d3, p71, "activities"), (d2, p71, "project"), (d5, p71, "budget, AC"), (d6, p71, "payments"), (d7, p71, "balances"),
                  (p71, p72, "snapshot"), (p72, p73, "KPIs, statuses"), (p72, p8, "KPI snapshot"), (p73, pm2, "project dashboard, reports"),
                  (p73, se2, "activity status"), (p73, cl, "progress and payment summary"), (cl, p73, "summary request")]:
    fl(p, a, b, lab)
p.vertex("l1anote", "Balancing with Level 0: inputs (progress entry, delay reason, approve or return, summary request) and outputs (submission status, approval queue, dashboards, activity status, client summary, KPI snapshot to 8.0) match process 4.0 and 7.0 flows. Director and QS outputs of 7.0 follow the same 7.3 path and are omitted here for space.",
          1720, 300, 440, 170, NOTE)

# ---------- Level 1: 5.0 cost and budget revision ----------
p = f.page("l1b", "DFD Level 1 - cost and budget revision", "Data Flow Diagram - Level 1: 5.0 Manage cost and payments", width=2000, height=1250, legend=LEG + ["* = duplicate copy of an external entity"], notation=NOT)
p.legend_pos = (1520, 20)
qs, di, pm = ext(p, "Quantity Surveyor", 80, 200), ext(p, "Director", 80, 760), ext(p, "Project Manager", 80, 480)
qs2 = ext(p, "Quantity Surveyor *", 760, 1060)
d5, d6, d9, d10 = store(p, "D5", "Budgets and expenditure", 1000, 140), store(p, "D6", "Certificates and payments", 1000, 980), store(p, "D9", "Audit log", 1460, 600), store(p, "D10", "Alerts and SMS log", 1460, 700)
p51, p52, p53 = proc(p, "5.1", "Record budget version (draft)", 480, 160), proc(p, "5.2", "Submit revision for approval", 480, 420), proc(p, "5.3", "Director decision (approve, supersede, reject)", 480, 700)
p54, p55, p56 = proc(p, "5.4", "Record or void expenditure", 1000, 300), proc(p, "5.5", "Certify valuation", 1000, 720), proc(p, "5.6", "Record payment and outstanding", 1460, 900)
p8 = proc(p, "8.0", "Evaluate alerts and notify", 1460, 300, off=True)
for a, b, lab in [(qs, p51, "budget amount, reason"), (pm, p52, "revision request"), (p51, d5, "draft version"), (p51, p52, "draft"),
                  (p52, d5, "pending version"), (p52, d10, "A12 pending revision"), (d5, p53, "pending version"), (di, p53, "revision decision"),
                  (p53, d5, "approved current / superseded / rejected"), (p53, di, "decision confirmation"), (p53, d9, "approval audit"),
                  (qs, p54, "expenditure entry"), (p54, d5, "expenditure"), (p54, p8, "cost changed"), (qs2, p55, "certificate, certified amount"),
                  (p55, d6, "certificate"), (qs2, p56, "payment"), (d6, p56, "certified amount"), (p56, d6, "payment, status"), (p56, p8, "payment changed")]:
    fl(p, a, b, lab)
p.vertex("l1bnote", "Balancing with Level 0: QS inputs (budget, expenditure, certificates, payments), Director input (revision decision) and stores D5, D6 match process 5.0. Writes to D9, D10 and the event to 8.0 are internal refinements (audit and A12).",
          1520, 1020, 440, 150, NOTE)

# ---------- Level 1: 8.0 alerts and notification ----------
p = f.page("l1c", "DFD Level 1 - alerts and notification", "Data Flow Diagram - Level 1: 8.0 Evaluate alerts and notify", width=2000, height=1200, legend=LEG, notation=NOT)
p.legend_pos = (1520, 20)
pm, qs, sms = ext(p, "Project Manager", 80, 200), ext(p, "Quantity Surveyor", 80, 420), ext(p, "SMS Gateway", 1640, 1080)
d8, d10, d1, d2 = store(p, "D8", "Settings and thresholds", 520, 140), store(p, "D10", "Alerts and SMS log", 1000, 620), store(p, "D1", "Users and roles", 1000, 1000), store(p, "D2", "Projects and assignments", 520, 1000)
p7 = proc(p, "7.0", "Calculate KPIs (Level 1 page 1)", 520, 620, off=True)
p81, p82, p83 = proc(p, "8.1", "Maintain thresholds within global limits", 520, 300), proc(p, "8.2", "Evaluate rules A1 to A15", 1000, 300), proc(p, "8.3", "Create, de-duplicate and clear alerts", 1000, 800)
p84, p85 = proc(p, "8.4", "Resolve recipients", 1460, 620), proc(p, "8.5", "Send SMS within caps", 1460, 900)
for a, b, lab in [(pm, p81, "project thresholds"), (qs, p81, "threshold proposals"), (p81, d8, "thresholds"), (d8, p82, "effective thresholds"),
                  (p7, p82, "KPI snapshot"), (p82, p83, "true conditions"), (p83, d10, "alerts, cleared alerts"), (d10, p83, "open alerts"),
                  (p83, p84, "new alerts"), (d2, p84, "assignments"), (d1, p84, "users, phones"), (p84, d10, "recipient rows"),
                  (p84, p85, "SMS-level recipients"), (p85, sms, "SMS message"), (sms, p85, "delivery status"), (p85, d10, "SMS status (sent, failed, capped)")]:
    fl(p, a, b, lab)
p.vertex("l1cnote", "Balancing with Level 0: inputs (KPI snapshot from 7.0, thresholds from PM and QS, D8) and outputs (alerts and SMS log D10, SMS message, delivery status) match process 8.0. Director global limits arrive through 2.0 into D8.",
          1520, 300, 440, 140, NOTE)

rep = f.save()
print(rep["pages"], rep["vertices"], rep["edges"], rep["problems"][:10])
