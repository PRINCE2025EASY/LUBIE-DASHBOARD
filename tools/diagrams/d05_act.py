from common import *

f = DrawioFile(OUTDIR + "05-activity-diagrams.drawio")
LEG = ["Black dot = initial node; ringed dot = final node", "Rounded box = action", "Diamond = decision / merge with [guard] on flows",
       "Black bar = fork / join", "Vertical lanes = swimlanes (who does it)", "[It.2] = planned for Iteration 2"]
LANE_W, ROW_H, X0, Y0, HEAD = 300, 100, 40, 140, 40

def act(fid, name, title, lanes, nodes, flows, note=None):
    rows = max(n[2] for n in nodes) + 1
    H = HEAD + rows * ROW_H + 20
    W = LANE_W * len(lanes)
    p = f.page(fid, name, title, width=max(1800, X0 + W + 460), height=Y0 + H + 80, legend=LEG, notation="UML 2.5 activity diagram")
    p.legend_pos = (X0 + W + 30, 20)
    pool = p.vertex("pool", "", X0, Y0, W, H, "swimlane;childLayout=stackLayout;resizeParent=1;resizeParentMax=0;horizontal=1;startSize=0;horizontalStack=1;collapsible=0;fillColor=none;", allow_empty=True)
    L = []
    for i, ln in enumerate(lanes):
        L.append(p.vertex(f"lane{i}", ln, i * LANE_W, 0, LANE_W, H, "swimlane;startSize=40;horizontal=1;collapsible=0;fillColor=#f5f5f5;fontStyle=1;swimlaneFillColor=#ffffff;", parent=pool))
    ids = {}
    for key, lane, row, kind, label in nodes:
        cy = HEAD + row * ROW_H + ROW_H / 2
        if kind == "start":
            ids[key] = p.vertex(key, "", LANE_W / 2 - 15, cy - 15, 30, 30, "shape=ellipse;perimeter=ellipsePerimeter;fillColor=#000000;strokeColor=#000000;", parent=L[lane], allow_empty=True)
        elif kind == "end":
            ids[key] = p.vertex(key, "", LANE_W / 2 - 20, cy - 20, 40, 40, "shape=doubleEllipse;perimeter=ellipsePerimeter;fillColor=#000000;strokeColor=#000000;", parent=L[lane], allow_empty=True)
        elif kind == "dec":
            ids[key] = p.vertex(key, label, LANE_W / 2 - 80, cy - 40, 160, 80, "shape=rhombus;perimeter=rhombusPerimeter;" + WRAP + "fillColor=#fff2cc;strokeColor=#d6b656;fontSize=11;", parent=L[lane])
        elif kind == "bar":
            span = label  # number of lanes to span to the right
            ids[key] = p.vertex(key, "", lane * LANE_W + 40, cy - 5 + 0, LANE_W * span - 80, 10, "rounded=0;fillColor=#000000;strokeColor=#000000;allowOverlap=1;", parent=pool, allow_empty=True)
        else:
            ids[key] = p.vertex(key, label, 30, cy - 30, LANE_W - 60, 60, "rounded=1;arcSize=30;" + WRAP + "fillColor=#dae8fc;strokeColor=#6c8ebf;", parent=L[lane])
    where = {key: (lane, row, kind) for key, lane, row, kind, label in nodes}
    chan = {}
    def cy(row): return Y0 + HEAD + row * ROW_H + ROW_H / 2
    for fl_ in flows:
        a, b = fl_[0], fl_[1]
        lab = fl_[2] if len(fl_) > 2 else ""
        la, ra, ka = where[a]; lb, rb, kb = where[b]
        pts, cons = None, ""
        if la == lb and ka != "bar" and kb != "bar" and not (rb == ra + 1):
            k = chan.get(la, 0); chan[la] = k + 1
            side_right = (k % 2 == 0)
            gx = X0 + la * LANE_W + (280 - 10 * (k // 2) if side_right else 20 + 10 * (k // 2))
            pts = [(gx, cy(ra)), (gx, cy(rb))]
            cons = f"exitX={1 if side_right else 0};exitY=0.5;entryX={1 if side_right else 0};entryY=0.5;exitPerimeter=0;entryPerimeter=0;"
        elif la != lb and ra != rb and ka != "bar" and kb != "bar":
            right = lb > la
            gx = X0 + (la + 1) * LANE_W - 10 if right else X0 + la * LANE_W + 10
            pts = [(gx, cy(ra)), (gx, cy(rb))]
            cons = f"exitX={1 if right else 0};exitY=0.5;entryX={0 if right else 1};entryY=0.5;exitPerimeter=0;entryPerimeter=0;"
        p.edge(ids[a], ids[b], lab, ORTHO + "fontSize=11;labelBackgroundColor=#ffffff;" + cons, points=pts)
    if note:
        p.vertex("note", note, X0 + W + 30, 260, 400, 180, NOTE)

# 1 Site-to-management information flow (to-be)
act("ac1", "Site to management flow", "Activity diagram 1: site-to-management information flow (with the dashboard)",
    ["Site Engineer", "System", "Project Manager", "QS / Director / Client"],
    [("s", 0, 0, "start", ""), ("rec", 0, 1, "act", "Record site event in phone form (progress, delay reason, resources)"),
     ("con", 0, 2, "dec", "Connection available?"), ("q", 0, 3, "act", "Save in offline outbox [It.2]"), ("sy", 0, 4, "act", "Auto-sync when back online"),
     ("val", 1, 4, "act", "Validate, store as Submitted, stamp last update"), ("ntf", 1, 5, "act", "Raise A13; show 'Submitted' to sender"),
     ("rev", 2, 5, "act", "Review entry"), ("ok", 2, 6, "dec", "Acceptable?"), ("ret", 2, 7, "act", "Return with reason"),
     ("apr", 2, 8, "act", "Approve"), ("kpi", 1, 8, "act", "Recalculate KPIs; evaluate alerts; SMS if critical"),
     ("fk", 1, 9, "bar", 3), ("v1", 1, 10, "act", "Refresh dashboards within 60 s"), ("v2", 3, 10, "act", "QS: cost KPIs; Director: portfolio, SMS; Client: summary"),
     ("jn", 1, 11, "bar", 3), ("e", 1, 12, "end", "")],
    [("s", "rec"), ("rec", "con"), ("con", "q", "[no]"), ("q", "sy"), ("sy", "val"), ("con", "val", "[yes]"), ("val", "ntf"), ("ntf", "rev"),
     ("rev", "ok"), ("ok", "ret", "[no]"), ("ret", "rec", "returned (seen by SE)"), ("ok", "apr", "[yes]"), ("apr", "kpi"), ("kpi", "fk"),
     ("fk", "v1"), ("fk", "v2"), ("v1", "jn"), ("v2", "jn"), ("jn", "e")],
    note="Replaces the manual relay (paper diary, WhatsApp, Excel, weekly report). The system cannot shorten the time before the engineer records the event; it removes re-typing and makes deviations visible within one refresh interval.")

# 2 Progress entry and approval
act("ac2", "Progress entry and approval", "Activity diagram 2: progress entry and approval",
    ["Site Engineer", "System", "Project Manager"],
    [("s", 0, 0, "start", ""), ("sel", 0, 1, "act", "Select assigned activity"), ("ent", 0, 2, "act", "Enter %, date (pre-filled), delay reason, remarks"),
     ("sub", 0, 3, "act", "Submit"), ("chk", 1, 3, "dec", "Valid and in scope?"), ("err", 1, 4, "act", "Show field errors; keep input"),
     ("crit", 1, 5, "dec", "Stoppage or safety reason?"), ("a15", 1, 6, "act", "Raise A15 (Critical, SMS to Director, PM, QS)"),
     ("sto", 1, 7, "act", "Store Submitted; raise A13"), ("q", 2, 7, "act", "Open approval queue"), ("dec", 2, 8, "dec", "Approve?"),
     ("ret", 2, 9, "act", "Return with reason"), ("apr", 2, 10, "act", "Approve"), ("upd", 1, 10, "act", "Count in KPIs; clear A13; re-evaluate A1, A8, A11"),
     ("see", 0, 10, "act", "See status: Approved or Returned"), ("e", 0, 11, "end", "")],
    [("s", "sel"), ("sel", "ent"), ("ent", "sub"), ("sub", "chk"), ("chk", "err", "[no]"), ("err", "ent", "correct"), ("chk", "crit", "[yes]"),
     ("crit", "a15", "[yes]"), ("a15", "sto"), ("crit", "sto", "[no]"), ("sto", "q"), ("q", "dec"), ("dec", "ret", "[no]"), ("ret", "see"),
     ("dec", "apr", "[yes]"), ("apr", "upd"), ("upd", "see"), ("see", "e")])

# 3 Budget revision approval
act("ac3", "Budget revision approval", "Activity diagram 3: budget revision approval",
    ["QS or PM", "System", "Director"],
    [("s", 0, 0, "start", ""), ("dr", 0, 1, "act", "Prepare revision (amount, reason) as draft"), ("sb", 0, 2, "act", "Submit for approval"),
     ("pd", 1, 2, "act", "Status Pending; raise A12; SMS Director"), ("rv", 2, 3, "act", "Review revision and history"),
     ("same", 1, 4, "dec", "Director = requester?"), ("blk", 1, 5, "act", "Reject action (409, separation of duties)"),
     ("d", 2, 5, "dec", "Approve?"), ("rj", 2, 6, "act", "Reject with note"), ("ap", 2, 7, "act", "Approve"),
     ("tx", 1, 7, "act", "One transaction: old current -> Superseded; new -> Approved, current"),
     ("cl", 1, 8, "act", "Clear A12; re-evaluate A5, A6, A6b, A7; audit"), ("nt", 0, 8, "act", "Requester sees decision"), ("e", 0, 9, "end", "")],
    [("s", "dr"), ("dr", "sb"), ("sb", "pd"), ("pd", "rv"), ("rv", "same"), ("same", "blk", "[yes]"), ("same", "d", "[no]"),
     ("d", "rj", "[no]"), ("rj", "cl"), ("d", "ap", "[yes]"), ("ap", "tx"), ("tx", "cl"), ("cl", "nt"), ("nt", "e"), ("blk", "rv")])

# 4 Alert evaluation, de-duplication and SMS
act("ac4", "Alert evaluation and SMS", "Activity diagram 4: alert evaluation, de-duplication and SMS dispatch",
    ["AlertService (create path)", "AlertService (clear path)", "SMS gateway", "Recipient"],
    [("s", 0, 0, "start", ""), ("tr", 0, 1, "act", "Trigger: data saved, or first request of the day"), ("sn", 0, 2, "act", "Build snapshot; load effective thresholds"),
     ("ru", 0, 3, "act", "Take next rule (A1 to A15)"), ("ct", 0, 4, "dec", "Condition true?"),
     ("op", 0, 5, "dec", "Open alert with same key?"), ("cr", 0, 6, "act", "Create alert and recipient rows"),
     ("sm", 0, 7, "dec", "SMS-level and staff recipient?"), ("cap", 0, 8, "dec", "Within daily and monthly caps?"),
     ("snd", 2, 8, "act", "Send SMS (5 s timeout)"), ("acc", 2, 9, "dec", "Accepted?"), ("lf", 2, 10, "act", "Log failed; retry_due"),
     ("ls", 0, 10, "act", "Log sent / capped"), ("pan", 3, 6, "act", "Alert appears in panel (read, acknowledge)"),
     ("op2", 1, 4, "dec", "Open alert for this key?"), ("clr", 1, 5, "act", "Clear alert (resolved)"),
     ("mr", 0, 11, "dec", "More rules?"), ("e", 0, 12, "end", "")],
    [("s", "tr"), ("tr", "sn"), ("sn", "ru"), ("ru", "ct"), ("ct", "op", "[yes]"), ("ct", "op2", "[no]"), ("op2", "clr", "[yes]"), ("op2", "mr", "[no]"),
     ("clr", "mr"), ("op", "mr", "[yes] no duplicate"), ("op", "cr", "[no]"), ("cr", "pan"), ("cr", "sm"), ("sm", "mr", "[no]"), ("sm", "cap", "[yes]"),
     ("cap", "ls", "[no] capped"), ("cap", "snd", "[yes]"), ("snd", "acc"), ("acc", "lf", "[no]"), ("acc", "ls", "[yes] sent"), ("lf", "mr"), ("ls", "mr"),
     ("mr", "ru", "[yes]"), ("mr", "e", "[no]")],
    note="Swimlane 'Recipient' shows what users see. Clearing happens automatically when a condition stops being true; an alert can open again later (new row). Clients only receive the in-app A4 notice.")

# 5 User lifecycle and project assignment
act("ac5", "User lifecycle and assignment", "Activity diagram 5: user lifecycle and project assignment",
    ["Administrator", "System", "Project Manager", "User"],
    [("s", 0, 0, "start", ""), ("cu", 0, 1, "act", "Create account (role, phone, temporary password)"), ("st", 1, 1, "act", "Store active, must change password; audit"),
     ("as", 2, 2, "act", "Assign user to project"), ("uq", 1, 2, "dec", "Already assigned?"), ("ae", 1, 3, "act", "Reject (409)"),
     ("sa", 1, 4, "act", "Store assignment; audit"), ("li", 3, 4, "act", "First login"), ("ch", 3, 5, "act", "Change password"),
     ("use", 3, 6, "act", "Work on assigned projects"), ("lk", 1, 7, "dec", "5 failed logins?"), ("lock", 1, 8, "act", "Lock 15 min; audit"),
     ("ul", 0, 8, "act", "Unlock or reset password"), ("dx", 0, 9, "act", "Deactivate when user leaves"), ("ks", 1, 9, "act", "Delete sessions; block login; keep history"),
     ("e", 1, 10, "end", "")],
    [("s", "cu"), ("cu", "st"), ("st", "as"), ("as", "uq"), ("uq", "ae", "[yes]"), ("uq", "sa", "[no]"), ("sa", "li"), ("li", "ch"), ("ch", "use"),
     ("use", "lk"), ("lk", "use", "[no]"), ("lk", "lock", "[yes]"), ("lock", "ul"), ("ul", "use"), ("use", "dx", "leaves"), ("dx", "ks"), ("ks", "e")])

# 6 Backup and recovery
act("ac6", "Backup and recovery", "Activity diagram 6: backup and recovery",
    ["External scheduler", "System", "Administrator", "Off-site storage"],
    [("s", 0, 0, "start", ""), ("cl", 0, 1, "act", "Daily: call /backup/run with token"), ("tk", 1, 1, "dec", "Token valid?"), ("den", 1, 2, "act", "401; audit"),
     ("dp", 1, 3, "act", "Dump tables, gzip, encrypt, hash; log"), ("sv", 3, 3, "act", "Store file; keep 7 daily, 4 weekly"),
     ("mon", 2, 4, "act", "Check backup log daily"), ("fail", 2, 5, "dec", "Data lost or corrupted?"), ("tst", 2, 6, "act", "Monthly restore test"),
     ("dl", 2, 7, "act", "Download latest valid file"), ("rv", 1, 8, "act", "restore:verify into empty DB; compare counts and KPIs"),
     ("okk", 1, 9, "dec", "Verified?"), ("prev", 2, 9, "act", "Use previous backup"), ("sw", 2, 10, "act", "Switch app to restored DB; tell users the restore point"),
     ("e", 2, 11, "end", "")],
    [("s", "cl"), ("cl", "tk"), ("tk", "den", "[no]"), ("tk", "dp", "[yes]"), ("dp", "sv"), ("sv", "mon"), ("mon", "fail"), ("fail", "tst", "[no]"),
     ("fail", "dl", "[yes]"), ("tst", "dl"), ("dl", "rv"), ("rv", "okk"), ("okk", "prev", "[no]"), ("prev", "rv"), ("okk", "sw", "[yes] (real recovery)"), ("sw", "e")],
    note="Targets: RPO 24 h, RTO 4 h. For a monthly test, the switch step is skipped and the restore time is recorded in backup_log (kind restore_test).")

rep = f.save()
print(rep["pages"], rep["vertices"], rep["edges"], rep["problems"][:10])
