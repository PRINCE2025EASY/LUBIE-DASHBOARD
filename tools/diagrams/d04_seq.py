from common import *

f = DrawioFile(OUTDIR + "04-sequence-diagrams.drawio")
LEG = ["Box + dashed line = lifeline; stick figure = actor", "Thin bar = activation", "Solid arrow = call / request",
       "Dashed open arrow = return / response", "Frame alt / opt / loop with [guard]", "API = front controller + middleware (session, CSRF, RBAC, project scope)"]
LW, GAP, TOP, STEP = 170, 230, 140, 40

def seq(fid, name, title, parts, steps, note=None):
    n = len(parts)
    # pass 1: y positions
    y = TOP + 70
    pos, frames, stack = [], [], []
    involved = {k: [] for k, _, _ in parts}
    idx = {k: i for i, (k, _, _) in enumerate(parts)}
    for st in steps:
        kind = st[0]
        if kind in ("m", "r"):
            pos.append((st, y)); involved[st[1]].append(y); involved[st[2]].append(y); y += STEP
        elif kind == "s":
            pos.append((st, y)); involved[st[1]] += [y, y + 30]; y += STEP + 20
        elif kind == "frame":
            fr = dict(kind=st[1], guard=st[2], i0=idx[st[3]], i1=idx[st[4]], y0=y, elses=[])
            stack.append(fr); frames.append(fr); y += 40
        elif kind == "else":
            stack[-1]["elses"].append((y, st[1])); y += 40
        elif kind == "end":
            fr = stack.pop(); fr["y1"] = y; y += 20
    height = y + 40
    width = max(1800, 40 + n * GAP + 420)
    p = f.page(fid, name, title, width=width, height=height + 80, legend=LEG, notation="UML 2.5 sequence diagram")
    p.legend_pos = (40 + n * GAP + 20, 20)
    life, bars, bar_span = {}, {}, {}
    for i, (k, label, kind) in enumerate(parts):
        x = 40 + i * GAP
        st = "shape=umlLifeline;perimeter=lifelinePerimeter;" + WRAP + "container=1;collapsible=0;recursiveResize=0;outlineConnect=0;size=50;fillColor=#dae8fc;strokeColor=#6c8ebf;"
        if kind == "actor":
            st = "shape=umlLifeline;participant=umlActor;perimeter=lifelinePerimeter;" + WRAP + "container=1;collapsible=0;recursiveResize=0;verticalAlign=top;spacingTop=36;outlineConnect=0;size=50;fillColor=#f5f5f5;"
        life[k] = p.vertex(k, label, x, TOP, LW, height - TOP, st)
        ys = involved[k] or [TOP + 80]
        b0, b1 = min(ys) - 10, max(ys) + 10
        b0, b1 = (b0 // 10) * 10, ((b1 + 9) // 10) * 10
        bar_span[k] = (b0, b1)
        bars[k] = p.vertex(k + "_act", "", LW / 2 - 5, b0 - TOP, 10, b1 - b0,
                           "html=1;points=[];perimeter=orthogonalPerimeter;outlineConnect=0;targetShapes=umlLifeline;portConstraint=eastwest;fillColor=#f5f5f5;", parent=life[k], allow_empty=True)
    def rel(k, yy):
        b0, b1 = bar_span[k]; return max(0.0, min(1.0, (yy - b0) / (b1 - b0)))
    for st, yy in pos:
        kind = st[0]
        if kind in ("m", "r"):
            a, b, lab = st[1], st[2], st[3]
            right = idx[b] > idx[a]
            style = ("endArrow=block;endFill=1;" if kind == "m" else "endArrow=open;endFill=0;dashed=1;") + \
                    f"exitX={1 if right else 0};exitY={rel(a, yy):.4f};entryX={0 if right else 1};entryY={rel(b, yy):.4f};exitPerimeter=0;entryPerimeter=0;" + \
                    "verticalAlign=bottom;fontSize=11;labelBackgroundColor=none;"
            p.edge(bars[a], bars[b], lab, style)
        else:
            a, lab = st[1], st[2]
            x = 40 + idx[a] * GAP + LW / 2 + 5
            p.edge(bars[a], bars[a], lab, f"endArrow=block;endFill=1;exitX=1;exitY={rel(a, yy):.4f};entryX=1;entryY={rel(a, yy + 30):.4f};exitPerimeter=0;entryPerimeter=0;align=left;spacingLeft=4;fontSize=11;",
                   points=[(x + 40, yy), (x + 40, yy + 30)])
    for fr in frames:
        x0 = 40 + fr["i0"] * GAP - 10
        x1 = 40 + fr["i1"] * GAP + LW + 10
        p.vertex("frame", fr["kind"], x0, fr["y0"] - 10, x1 - x0, fr["y1"] - fr["y0"] + 10,
                 "shape=umlFrame;" + WRAP + "width=60;height=24;boundedLbl=1;verticalAlign=top;align=left;spacingLeft=6;fontStyle=1;allowOverlap=1;fillColor=none;")
        p.vertex("guard", f"[{fr['guard']}]", x0 + 70, fr["y0"] - 10, 360, 20, TEXT + "fontStyle=2;allowOverlap=1;")
        for ey, eg in fr["elses"]:
            p.vertex("else", "", x0, ey, x1 - x0, 10, "line;strokeWidth=1;dashed=1;fillColor=none;allowOverlap=1;", allow_empty=True)
            p.vertex("eguard", f"[{eg}]", x0 + 10, ey + 10, 400, 20, TEXT + "fontStyle=2;allowOverlap=1;")
    if note:
        p.vertex("note", note, 40 + n * GAP + 20, 240, 380, 160, NOTE)

U, B, A = "actor", "box", "box"
# (a) Unified login
seq("sqa", "(a) Unified login", "Sequence (a): unified login with lockout, role lookup, audit and redirect",
    [("u", "User (any role)", U), ("br", "Browser", B), ("api", "API (front controller + middleware)", B), ("ac", "AuthController", B),
     ("as", "AuthService", B), ("ur", "UserRepository", B), ("au", "AuditLogger", B), ("db", "Database", B)],
    [("m", "u", "br", "enter username, password"), ("m", "br", "api", "POST /api/v1/auth/login + CSRF"), ("s", "api", "check CSRF and IP rate limit"),
     ("frame", "alt", "IP rate limit exceeded", "br", "api"), ("r", "api", "br", "429 try later"),
     ("else", "allowed"), ("m", "api", "ac", "login(request)"), ("m", "ac", "as", "authenticate(username, password)"),
     ("m", "as", "ur", "findByUsername()"), ("m", "ur", "db", "SELECT user + role"), ("r", "db", "ur", "row or none"), ("r", "ur", "as", "user or null"),
     ("frame", "alt", "unknown user or wrong password", "ac", "db"), ("s", "as", "verify (dummy hash if unknown)"),
     ("m", "as", "ur", "increment failures, lock at 5"), ("m", "as", "au", "login_failed"), ("m", "au", "db", "INSERT audit_logs"), ("r", "as", "ac", "failure"),
     ("else", "account locked or deactivated"), ("m", "as", "au", "login_failed (locked)"), ("r", "as", "ac", "failure"),
     ("else", "credentials valid and account active"), ("m", "as", "ur", "reset failures, set last_login_at"), ("m", "as", "db", "regenerate session id (DB session store)"),
     ("m", "as", "au", "login"), ("r", "as", "ac", "user, roles.dashboard_path"), ("end",),
     ("r", "ac", "api", "result"), ("r", "api", "br", "200 {redirect} or 401 generic message"), ("end",),
     ("frame", "opt", "login succeeded", "u", "api"), ("m", "br", "api", "GET /pm (role page)"), ("s", "api", "RBAC: role may view page"), ("r", "api", "br", "PM dashboard shell"),
     ("r", "br", "u", "dashboard shown"), ("end",)],
    note="Role is read only from users.role_id via the database. Opening another role's page returns 403 and writes audit action 'forbidden'. Error messages never reveal whether the username exists.")

# (b) Progress submission and approval
seq("sqb", "(b) Progress and approval", "Sequence (b): progress submission, PM approval or return, KPI and alert update",
    [("se", "Site Engineer", U), ("bs", "SE browser", B), ("api", "API", B), ("ps", "ProgressService", B), ("db", "Repository / DB", B),
     ("al", "AlertService", B), ("bp", "PM browser", B), ("pm", "Project Manager", U)],
    [("m", "se", "bs", "% complete, date, delay reason"), ("m", "bs", "api", "POST /activities/{id}/progress"), ("s", "api", "auth, CSRF, project scope"),
     ("m", "api", "ps", "submit(entry)"), ("s", "ps", "validate 0-100, date, assignment"),
     ("frame", "alt", "invalid", "bs", "ps"), ("r", "ps", "api", "422 field errors"), ("r", "api", "bs", "keep form, show errors"),
     ("else", "valid"), ("m", "ps", "db", "INSERT progress (submitted), audit, last_change_at"), ("m", "ps", "al", "evaluate(project)"), ("m", "al", "db", "open A13 (and A15 if stoppage or safety)"),
     ("r", "ps", "api", "201 submitted"), ("r", "api", "bs", "confirmation: Submitted"), ("end",),
     ("m", "bp", "api", "GET approval queue (poll or open)"), ("r", "api", "bp", "pending entries"), ("m", "pm", "bp", "approve or return with reason"),
     ("m", "bp", "api", "POST /progress/{id}/approve | return"), ("m", "api", "ps", "decide(id, decision)"),
     ("frame", "alt", "approve", "api", "al"), ("m", "ps", "db", "status approved, reviewer, time, audit"), ("m", "ps", "al", "evaluate(project): KPIs recalculated"),
     ("m", "al", "db", "clear A13, A1/A11 as conditions change"),
     ("else", "return"), ("m", "ps", "db", "status returned, reason, audit"), ("end",),
     ("r", "ps", "api", "200"), ("r", "api", "bp", "updated queue"), ("m", "bs", "api", "next poll: GET /changes"), ("r", "api", "bs", "changed: status Approved or Returned")],
    note="Only approved records count in KPIs. A returned entry can be corrected and resubmitted (resubmission_of).")

# (c) Expenditure, alert, SMS
seq("sqc", "(c) Expenditure and SMS", "Sequence (c): QS records expenditure, alert evaluation, SMS dispatch",
    [("qs", "Quantity Surveyor", U), ("br", "Browser", B), ("api", "API", B), ("cs", "CostService", B), ("db", "Repository / DB", B),
     ("al", "AlertService", B), ("gw", "SmsGateway (adapter)", B), ("pr", "SMS provider", B)],
    [("m", "qs", "br", "expenditure details"), ("m", "br", "api", "POST /projects/{id}/expenditures"), ("m", "api", "cs", "record(expense)"),
     ("s", "cs", "validate amount > 0, date, category"), ("m", "cs", "db", "INSERT expenditure, audit, last_change_at"), ("m", "cs", "al", "evaluate(project)"),
     ("m", "al", "db", "AC, budget, EV, thresholds"), ("r", "db", "al", "values"), ("s", "al", "A5, A6, A6b, A7 conditions"),
     ("frame", "opt", "new condition true (no open alert with same key)", "api", "pr"), ("m", "al", "db", "INSERT notification + recipients"),
     ("frame", "loop", "each SMS-level recipient (A6b: Director, PM, QS)", "al", "pr"), ("m", "al", "db", "count today's SMS for user"),
     ("frame", "alt", "cap reached or SMS disabled", "al", "pr"), ("m", "al", "db", "sms_status = capped"),
     ("else", "within cap"), ("m", "al", "gw", "send(phone, text)"), ("m", "gw", "pr", "HTTPS POST (5 s timeout)"),
     ("frame", "alt", "accepted", "gw", "pr"), ("r", "pr", "gw", "message id"), ("else", "timeout or error"), ("r", "pr", "gw", "error"), ("end",),
     ("r", "gw", "al", "sent or failed"), ("m", "al", "db", "sms_log; failed sets retry_due"), ("end",), ("end",), ("end",),
     ("r", "cs", "api", "201"), ("r", "api", "br", "saved; KPI cards refresh")],
    note="Sandbox adapter writes to sms_log only and never reports 'sent' to a phone. Clients never receive SMS.")

# (d) Budget revision
seq("sqd", "(d) Budget revision", "Sequence (d): budget revision proposed and approved by the Director",
    [("qs", "QS or PM", U), ("bq", "Browser", B), ("api", "API", B), ("bs", "BudgetService", B), ("db", "Repository / DB", B),
     ("al", "AlertService", B), ("bd", "Director browser", B), ("di", "Director", U)],
    [("m", "qs", "bq", "new amount, reason"), ("m", "bq", "api", "POST /projects/{id}/budgets (submit)"), ("m", "api", "bs", "requestRevision()"),
     ("m", "bs", "db", "INSERT version n+1 status pending, audit"), ("m", "bs", "al", "evaluate"), ("m", "al", "db", "open A12; SMS to Director"),
     ("r", "api", "bq", "201 pending"), ("m", "bd", "api", "GET pending revisions"), ("r", "api", "bd", "list"), ("m", "di", "bd", "approve or reject"),
     ("m", "bd", "api", "POST /budgets/{id}/approve | reject"), ("m", "api", "bs", "decide()"),
     ("frame", "alt", "Director is the requester", "api", "bs"), ("r", "bs", "api", "409 separation of duties"),
     ("else", "approve"), ("m", "bs", "db", "BEGIN; old current -> superseded; new -> approved, is_current = 1; COMMIT"), ("m", "bs", "al", "evaluate: clear A12, recheck A5-A7"),
     ("else", "reject"), ("m", "bs", "db", "status rejected, note, audit"), ("m", "bs", "al", "clear A12"), ("end",),
     ("r", "api", "bd", "200"), ("r", "api", "bq", "next poll shows decision")],
    note="The baseline is never overwritten. The unique key (project_id, is_current) guarantees one current version even under concurrent approvals.")

# (e) Polling
seq("sqe", "(e) Polling refresh", "Sequence (e): near-real-time polling refresh",
    [("u", "User", U), ("js", "Dashboard JS", B), ("api", "API", B), ("ds", "DashboardService", B), ("db", "Repository / DB", B)],
    [("m", "u", "js", "open dashboard"), ("m", "js", "api", "GET /projects/{id}/dashboard"), ("m", "api", "ds", "build DTO (role-filtered)"), ("m", "ds", "db", "snapshot queries"),
     ("r", "api", "js", "KPIs, charts, last_change_at"),
     ("frame", "loop", "every refresh_interval (60 s) while tab visible", "js", "db"), ("m", "js", "api", "GET /changes?since=last_change_at"), ("m", "api", "db", "SELECT last_change_at"),
     ("frame", "alt", "changed", "js", "db"), ("r", "api", "js", "{changed: true}"), ("m", "js", "api", "GET dashboard DTO"), ("r", "api", "js", "new data"), ("s", "js", "redraw cards, charts; 'Checked' time"),
     ("else", "not changed"), ("r", "api", "js", "{changed: false} (< 1 KB)"),
     ("else", "network error or 503 (server waking)"), ("s", "js", "banner; backoff 60-120-240 s"), ("end",), ("end",),
     ("frame", "opt", "tab hidden", "u", "js"), ("s", "js", "pause timer until visible"), ("end",)],
    note="Users see 'Data last updated' (last save on the server) and 'Checked' (last successful poll) separately.")

# (f) Client summary
seq("sqf", "(f) Client summary", "Sequence (f): client summary with field-level filtering",
    [("cl", "Client", U), ("br", "Browser", B), ("api", "API (RBAC + scope)", B), ("cs", "ClientSummaryService", B), ("kp", "KpiService", B),
     ("dto", "ClientSummaryDto", B), ("db", "Repository / DB", B), ("au", "AuditLogger", B)],
    [("m", "cl", "br", "open /client"), ("m", "br", "api", "GET /client/summary?project_id=7"), ("s", "api", "role client; check assignment"),
     ("frame", "alt", "project not assigned to this client", "br", "au"), ("m", "api", "au", "forbidden (IDOR attempt)"), ("r", "api", "br", "404 not found"),
     ("else", "assigned"), ("m", "api", "cs", "summary(project)"), ("m", "cs", "kp", "completion %"), ("m", "kp", "db", "activities, approved progress"), ("r", "kp", "cs", "% complete"),
     ("m", "cs", "db", "milestones, certificate totals, A4 notices"), ("m", "cs", "dto", "build from allow-list"),
     ("s", "dto", "drop expenditure, labour, revisions, internal alerts, rates"), ("r", "dto", "cs", "client DTO"), ("r", "api", "br", "200 summary"), ("end",),
     ("r", "br", "cl", "one screen: %, milestones, payments")],
    note="Field filtering happens on the server; hidden fields are absent from the JSON, verified by ST-09.")

# (g) Report export
seq("sqg", "(g) Report export", "Sequence (g): report export with permission checks",
    [("u", "User", U), ("br", "Browser", B), ("api", "API", B), ("rs", "ReportService", B), ("kp", "KpiService", B), ("rn", "PDF / CSV renderer", B),
     ("db", "Repository / DB", B), ("au", "AuditLogger", B)],
    [("m", "u", "br", "choose report, format"), ("m", "br", "api", "GET /reports/expenditure?project_id&format"), ("s", "api", "RBAC report permission; scope"),
     ("frame", "alt", "not permitted (e.g. SE asks expenditure)", "br", "au"), ("m", "api", "au", "forbidden"), ("r", "api", "br", "403"),
     ("else", "permitted"), ("m", "api", "rs", "build(type, filters, role)"), ("m", "rs", "db", "same queries as dashboard"), ("m", "rs", "kp", "KPIs for report"),
     ("s", "rs", "apply role DTO filter"),
     ("frame", "alt", "format = pdf", "rs", "rn"), ("m", "rs", "rn", "render Dompdf (A4)"), ("else", "format = csv"), ("m", "rs", "rn", "write CSV, formula guard"), ("end",),
     ("r", "rn", "api", "stream"), ("m", "rs", "au", "export"), ("r", "api", "br", "file download (not stored on server)"), ("end",)],
    note="Exports use the same permissions and calculations as dashboards (DRY).")

# (h) Project threshold
seq("sqh", "(h) Project threshold", "Sequence (h): PM sets a project threshold within the global limit",
    [("pm", "Project Manager", U), ("br", "Browser", B), ("api", "API", B), ("ts", "ThresholdService", B), ("db", "Repository / DB", B),
     ("al", "AlertService", B), ("au", "AuditLogger", B)],
    [("m", "pm", "br", "budget warning = 75%"), ("m", "br", "api", "PUT /projects/{id}/thresholds/budget_warning_pct"), ("s", "api", "role PM; assigned"),
     ("m", "api", "ts", "setProjectValue()"), ("m", "ts", "db", "global row: min 50, max 95"), ("r", "db", "ts", "limits"),
     ("frame", "alt", "value outside global limits", "br", "au"), ("r", "ts", "api", "422 'between 50 and 95'"), ("r", "api", "br", "error shown"),
     ("else", "within limits"), ("m", "ts", "db", "UPSERT project setting"), ("m", "ts", "au", "threshold_change (old, new)"), ("m", "ts", "al", "evaluate(project)"),
     ("r", "api", "br", "200 saved"), ("end",)],
    note="QS proposals follow the same path but are stored in threshold_proposals until the Director approves.")

# (i) Backup and restore
seq("sqi", "(i) Backup and restore", "Sequence (i): scheduled backup through the protected endpoint, and restore test",
    [("sc", "External scheduler", U), ("api", "API /backup/run", B), ("bk", "BackupService", B), ("db", "Production DB", B), ("st", "Off-site storage", B),
     ("ad", "Administrator", U), ("cn", "Console restore:verify", B), ("tdb", "Separate test DB", B)],
    [("m", "sc", "api", "POST /backup/run (Bearer token)"),
     ("frame", "alt", "token missing or wrong", "sc", "bk"), ("r", "api", "sc", "401; audit; rate limit"),
     ("else", "token valid"), ("m", "api", "bk", "create()"), ("m", "bk", "db", "INSERT backup_log started"),
     ("frame", "loop", "each table in dependency order", "bk", "db"), ("m", "bk", "db", "SELECT chunk"), ("r", "db", "bk", "rows"), ("end",),
     ("s", "bk", "gzip, AES-256-GCM, SHA-256"), ("m", "bk", "db", "backup_log succeeded, row counts"), ("r", "api", "sc", "encrypted file stream"), ("end",),
     ("m", "sc", "st", "store; apply 7 daily / 4 weekly retention"),
     ("m", "ad", "st", "download latest file"), ("m", "ad", "cn", "php bin/console.php restore:verify file"), ("s", "cn", "decrypt, check SHA-256"),
     ("m", "cn", "tdb", "import into empty database"), ("m", "cn", "tdb", "count rows; recompute KPIs"),
     ("frame", "alt", "counts and KPIs match log", "ad", "tdb"), ("r", "cn", "ad", "PASS (time recorded for RTO)"), ("else", "mismatch"), ("r", "cn", "ad", "FAIL: use previous backup"), ("end",)],
    note="The production database is never overwritten by the test. Switch-over follows the documented recovery procedure.")

# (j) Poor network
seq("sqj", "(j) Poor network and retry", "Sequence (j): failed submission on a poor network, draft autosave, offline queue and retry",
    [("se", "Site Engineer", U), ("fm", "Progress form (JS)", B), ("dr", "Local draft store", B), ("ob", "Offline outbox (IndexedDB) [It.2]", B),
     ("api", "API /sync", B), ("ps", "ProgressService", B), ("db", "Repository / DB", B)],
    [("m", "se", "fm", "type entry"), ("m", "fm", "dr", "autosave draft every change"), ("m", "se", "fm", "Submit"), ("m", "fm", "api", "POST progress (fetch, 15 s timeout)"),
     ("r", "api", "fm", "network error / timeout"),
     ("frame", "alt", "offline mode enabled", "se", "ob"), ("m", "fm", "ob", "queue {client_uuid, body}, encrypted"), ("r", "fm", "se", "'Saved on phone, waiting to sync'"),
     ("else", "offline mode off"), ("r", "fm", "se", "'Not saved'; form and draft kept"), ("end",),
     ("frame", "loop", "on reconnect or app start, until outbox empty", "fm", "db"), ("m", "ob", "api", "POST /sync {items <= 50}"), ("s", "api", "session, CSRF, role, scope"),
     ("m", "api", "db", "sync_requests has client_uuid?"),
     ("frame", "alt", "replayed uuid", "ob", "db"), ("r", "api", "ob", "stored first result"),
     ("else", "new uuid"), ("m", "api", "ps", "submit(entry)"), ("m", "ps", "db", "validate, INSERT, sync_requests"), ("r", "api", "ob", "201 or 422 per item"), ("end",),
     ("s", "ob", "remove synced; keep rejects in 'Needs attention'"), ("end",),
     ("m", "fm", "dr", "clear draft after success")],
    note="Login and approvals need a connection. Duplicate submissions are impossible because client_uuid is unique in both sync_requests and progress_records.")

rep = f.save()
print(rep["pages"], rep["vertices"], rep["edges"], rep["problems"][:10])
