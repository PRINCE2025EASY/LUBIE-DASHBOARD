from common import *
import textwrap
def wrapl(t, n=30):
    return "\n".join("\n".join(textwrap.wrap(part, n)) for part in t.split("\n"))

f = DrawioFile(OUTDIR + "06-state-diagrams.drawio")
LEG = ["Black dot = initial pseudo-state; ringed dot = final state", "Rounded box = state", "Arrow label = event [guard] / action",
       "Container with header = composite state", "'derived' = computed, not stored in a column", "Alert ids refer to Design Specification s9.2"]
CW, RH, X0, Y0 = 380, 190, 60, 160
ST = "rounded=1;arcSize=30;" + WRAP + "fillColor=#dae8fc;strokeColor=#6c8ebf;fontStyle=1;"
TR = "endArrow=open;endFill=0;fontSize=10;labelBackgroundColor=#ffffff;"

def centre(p, cid):
    c = p.get(cid); x, y = c["x"], c["y"]
    par = c["parent"]
    while par != "1":
        pc = p.get(par); x += pc["x"]; y += pc["y"]; par = pc["parent"]
    return x + c["w"] / 2, y + c["h"] / 2

def machine(fid, name, title, states, trans, note=None, composites=()):
    p = f.page(fid, name, title, width=1900, height=1200, legend=LEG, notation="UML 2.5 state machine")
    p.legend_pos = (1420, 20)
    ids = {}
    for key, label, col, row, kind in states:
        x, y = X0 + col * CW, Y0 + row * RH
        if kind == "init":
            ids[key] = p.vertex(key, "", x + 85, y + 25, 30, 30, "shape=ellipse;perimeter=ellipsePerimeter;fillColor=#000000;strokeColor=#000000;", allow_empty=True)
        elif kind == "final":
            ids[key] = p.vertex(key, "", x + 80, y + 20, 40, 40, "shape=doubleEllipse;perimeter=ellipsePerimeter;fillColor=#000000;strokeColor=#000000;", allow_empty=True)
        else:
            ids[key] = p.vertex(key, label, x, y, 200, 80, ST + ("dashed=1;" if kind == "derived" else ""))
    for ckey, clabel, col, row, w, h, subs, strans in composites:
        cx, cy = X0 + col * CW, Y0 + row * RH
        box = p.vertex(ckey, clabel, cx, cy, w, h, "swimlane;rounded=1;arcSize=6;startSize=30;" + WRAP + "fillColor=#f5f5f5;strokeColor=#666666;collapsible=0;verticalAlign=top;fontStyle=1;")
        ids[ckey] = box
        for skey, slabel, sx, sy, kind in subs:
            if kind == "init":
                ids[skey] = p.vertex(skey, "", sx, sy, 30, 30, "shape=ellipse;perimeter=ellipsePerimeter;fillColor=#000000;", parent=box, allow_empty=True)
            else:
                ids[skey] = p.vertex(skey, slabel, sx, sy, 160, 60, ST, parent=box)
        trans = list(trans) + list(strans)
    seen = set()
    for tr in trans:
        a, b, lab = tr[:3]
        pts = tr[3] if len(tr) > 3 else None
        if pts is None and ((b, a) in seen or a == b):
            (ax, ay), (bx, by) = centre(p, ids[a]), centre(p, ids[b])
            if a == b:
                pts = [(ax + 130, ay - 20), (ax + 130, ay + 40)]
            else:
                dx, dy = bx - ax, by - ay; L = max((dx * dx + dy * dy) ** 0.5, 1)
                pts = [((ax + bx) / 2 - dy / L * 60, (ay + by) / 2 + dx / L * 60)]
        seen.add((a, b))
        p.edge(ids[a], ids[b], wrapl(lab), TR, points=pts)
    if note:
        p.vertex("note", note, 1420, 260, 420, 170, NOTE)

machine("st1", "Activity schedule status", "State diagram 1: activity schedule status (derived by KpiService)",
    [("i", "", 0, 0, "init"), ("ns", "Not started", 0, 1, "derived"), ("ls", "Late start", 1, 0, "derived"), ("ip", "In progress", 1, 2, "derived"),
     ("dl", "Delayed", 2, 2, "derived"), ("cp", "Completed", 1, 4, "derived"), ("cl", "Completed late", 2, 4, "derived"), ("f", "", 2, 5, "final")],
    [("i", "ns", "activity created"), ("ns", "ls", "date passes planned start [no actual start, no progress] / raise A2"),
     ("ns", "ip", "progress approved [% > 0]"), ("ls", "ip", "progress approved [% > 0] / clear A2"),
     ("ip", "dl", "date passes planned finish [% < 100] / raise A1"), ("ip", "cp", "progress approved [% = 100, actual finish <= planned finish]"),
     ("dl", "cl", "progress approved [% = 100] / clear A1"), ("ip", "cl", "progress approved [% = 100, actual finish > planned finish]"),
     ("cp", "f", "activity archived"), ("cl", "f", "activity archived")],
    note="States are computed from dates and the latest APPROVED progress, so they are shown dashed (derived, not stored). A1 sends SMS only when the delay exceeds delay_sms_days (default 14).")

machine("st2", "Progress record", "State diagram 2: progress record",
    [("i", "", 0, 1, "init"), ("sb", "Submitted", 1, 1, "s"), ("ap", "Approved", 2, 0, "s"), ("rt", "Returned", 2, 2, "s"),
     ("f1", "", 3, 0, "final"), ("f2", "", 3, 2, "final")],
    [("i", "sb", "SE or PM submits [valid, assigned] / raise A13; A15 if stoppage or safety"),
     ("sb", "ap", "PM approves [assigned PM] / counts in KPIs; clear A13; audit"),
     ("sb", "rt", "PM returns [reason given] / notify submitter; audit"),
     ("rt", "f2", "corrected entry submitted as new record (resubmission_of)"),
     ("ap", "f1", "kept as history (later approved record becomes latest)")],
    note="Approved records are never edited. Corrections are new records, so the audit history and the PV/EV chart stay consistent.")

machine("st3", "Budget version", "State diagram 3: budget version",
    [("i", "", 0, 1, "init"), ("dr", "Draft", 1, 1, "s"), ("pd", "Pending approval", 2, 1, "s"), ("ap", "Approved (current)", 3, 0, "s"),
     ("rj", "Rejected", 2, 3, "s"), ("su", "Superseded", 3, 4, "s"), ("f1", "", 2, 4, "final"), ("f2", "", 1, 3, "final")],
    [("i", "dr", "QS or PM creates"), ("dr", "pd", "submit / raise A12; SMS Director"),
     ("pd", "ap", "Director approves [approver <> requester] / previous current -> Superseded (one transaction); clear A12"),
     ("pd", "rj", "Director rejects / note; clear A12"), ("ap", "su", "newer version approved"),
     ("rj", "f2", "kept as history"), ("su", "f1", "kept as history")],
    note="Exactly one Approved (current) version per project, enforced by UNIQUE(project_id, is_current). The baseline is never overwritten.")

machine("st4", "Alert and SMS", "State diagram 4: alert (in-app) with SMS sub-states per recipient",
    [("i", "", 0, 0, "init"), ("gn", "Generated", 0, 1, "s"), ("dv", "Delivered (in panel)", 1, 1, "s"), ("rd", "Read", 2, 1, "s"),
     ("ak", "Acknowledged", 3, 1, "s"), ("rs", "Resolved / cleared", 1, 3, "s"), ("f", "", 1, 5, "final")],
    [("i", "gn", "condition first true\n[no open alert with same key]\n/ create alert + recipients"),
     ("gn", "dv", "recipient opens dashboard"), ("dv", "rd", "recipient opens alert"), ("rd", "ak", "recipient acknowledges"),
     ("gn", "rs", "condition false / cleared_at"), ("dv", "rs", "condition false"), ("ak", "rs", "condition false"),
     ("rs", "f", "kept as history; condition true again -> new alert")],
    composites=[("sms", "SMS sub-state (per staff recipient, SMS-level rules only)", 2, 3, 720, 400,
                 [("si", "", 20, 60, "init"), ("q", "Queued", 90, 50, "s"), ("se", "Sent", 480, 50, "s"), ("fa", "Failed", 480, 290, "s"), ("ca", "Capped", 90, 290, "s")],
                 [("si", "q", "SMS-level alert created"), ("q", "se", "gateway accepts / provider ref"), ("q", "fa", "timeout or error / retry_due"),
                  ("fa", "q", "next evaluation [attempts < 3]"), ("q", "ca", "daily or monthly cap reached / no send")])],
    note="Clients are never SMS recipients. Sandbox provider records sms_log entries but they are not reported as delivered to phones.")

machine("st5", "Payment certificate", "State diagram 5: payment certificate",
    [("i", "", 0, 1, "init"), ("sb", "Submitted", 1, 1, "s"), ("ce", "Certified", 2, 1, "s"), ("pp", "Part-paid", 3, 1, "s"),
     ("pd", "Paid", 3, 3, "s"), ("od", "Overdue (derived)", 1, 3, "derived"), ("f", "", 3, 4, "final")],
    [("i", "sb", "QS records valuation"), ("sb", "ce", "QS certifies [amount, date] "), ("ce", "pp", "payment recorded [outstanding > 0]"),
     ("ce", "pd", "payment recorded [outstanding = 0]"), ("pp", "pd", "payment recorded [outstanding = 0]"),
     ("sb", "od", "today > submitted + p days [not fully paid] / raise A9"), ("ce", "od", "today > submitted + p [not fully paid] / A9"),
     ("pp", "od", "today > submitted + p [outstanding > 0] / A9"), ("od", "pd", "payment clears balance / clear A9"), ("pd", "f", "project archived")],
    note="Overdue is computed (not stored) from submitted_date, projects.payment_period_days and payments. Stored status stays Submitted, Certified, Part-paid or Paid. Overpayment is rejected (422).")

machine("st6", "User account", "State diagram 6: user account",
    [("i", "", 0, 1, "init"), ("ac", "Active", 1, 1, "s"), ("lk", "Locked", 2, 0, "s"), ("dx", "Deactivated", 2, 2, "s"), ("f", "", 3, 2, "final")],
    [("i", "ac", "admin creates [temporary password] / must change at first login"),
     ("ac", "lk", "5th failed login / lock 15 min; audit"), ("lk", "ac", "15 min elapse or admin unlocks / audit"),
     ("ac", "dx", "admin deactivates / delete sessions; audit"), ("lk", "dx", "admin deactivates"),
     ("dx", "ac", "admin reactivates [new temporary password]"), ("dx", "f", "retained for audit history (never deleted)")],
    note="Password change, reset and forced change are events within Active. A deactivated user's old session is rejected on the next request (ST-10).")

machine("st7", "Project", "State diagram 7: project",
    [("i", "", 0, 1, "init"), ("pl", "Planning", 1, 1, "s"), ("ac", "Active", 2, 1, "s"), ("oh", "On hold", 2, 3, "s"),
     ("cp", "Completed", 3, 1, "s"), ("ar", "Archived", 3, 3, "s"), ("f", "", 4, 3, "final")],
    [("i", "pl", "PM or Admin creates / creator PM assigned"), ("pl", "ac", "start [actual start date set]"),
     ("ac", "oh", "suspend [reason]"), ("oh", "ac", "resume"), ("ac", "cp", "complete [all activities 100% approved, actual finish set]"),
     ("cp", "ar", "archive"), ("pl", "ar", "cancel / archive", [(540, 940), (1300, 940)]), ("ar", "f", "read-only history")],
    note="Alerts are evaluated for Active and On hold projects only. Archived projects are read-only and hidden from default lists; nothing is deleted.")

rep = f.save()
print(rep["pages"], rep["vertices"], rep["edges"], rep["problems"][:10])
