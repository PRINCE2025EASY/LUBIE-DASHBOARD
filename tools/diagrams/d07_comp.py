from common import *

f = DrawioFile(OUTDIR + "07-component-diagrams.drawio")
COMP = "shape=component;align=left;spacingLeft=36;" + WRAP + "fillColor=#dae8fc;strokeColor=#6c8ebf;"
LAYER = "swimlane;horizontal=0;startSize=40;" + WRAP + "fillColor=#f5f5f5;strokeColor=#666666;collapsible=0;fontStyle=1;"
BALL = "shape=ellipse;perimeter=ellipsePerimeter;fillColor=#ffffff;strokeColor=#333333;labelPosition=center;verticalLabelPosition=top;align=center;verticalAlign=bottom;fontSize=10;allowOverlap=1;"
PROVIDES = "endArrow=none;strokeColor=#333333;"
REQUIRES = "endArrow=halfCircle;endFill=0;endSize=14;dashed=1;strokeColor=#333333;"
NODE = "shape=cube;size=12;" + WRAP + "verticalAlign=top;align=left;spacingLeft=10;spacingTop=6;fillColor=#f5f5f5;strokeColor=#666666;fontStyle=1;collapsible=0;container=1;"
ART = "shape=note;size=10;" + WRAP + "fillColor=#ffffff;strokeColor=#6c8ebf;fontSize=11;"
PATH = "endArrow=none;fontSize=10;labelBackgroundColor=#ffffff;strokeWidth=2;"

# ---------- Page 1: logical three-tier ----------
LEG1 = ["Box with tabs = component", "Circle = provided interface (ball)", "Dashed line with socket = required interface (depends on abstraction)",
        "Solid line component-ball = provides", "Bands = tiers", "[It.2] = Iteration 2"]
p = f.page("cp1", "Logical three-tier view", "Component diagram 1: logical three-tier architecture", width=2200, height=1400, legend=LEG1, notation="UML 2.5 component diagram")
p.legend_pos = (1740, 20)
pres = p.vertex("tierP", "Presentation tier (browser)", 40, 140, 1640, 240, LAYER)
app = p.vertex("tierA", "Application tier (PHP 8.1+ on Apache)", 40, 420, 1640, 660, LAYER)
dat = p.vertex("tierD", "Data tier", 40, 1120, 1640, 180, LAYER)
c = {}
c["shell"] = p.vertex("shell", "Role page shells (HTML5, Bootstrap 5.3, dark/light)", 80, 40, 300, 70, COMP, parent=pres)
c["js"] = p.vertex("js", "Dashboard JS modules (fetch, polling, forms, drafts)", 440, 40, 300, 70, COMP, parent=pres)
c["chart"] = p.vertex("chart", "Chart.js 4 (vendored)", 800, 40, 240, 70, COMP, parent=pres)
c["sw"] = p.vertex("sw", "Offline app: service worker + IndexedDB outbox [It.2]", 1100, 40, 320, 70, COMP, parent=pres)
c["fc"] = p.vertex("fc", "Front controller + Router (public/index.php)", 80, 50, 300, 70, COMP, parent=app)
c["mw"] = p.vertex("mw", "Middleware: Session, CSRF, RBAC, ProjectScope, RateLimit, SecurityHeaders", 80, 200, 300, 90, COMP, parent=app)
c["ctl"] = p.vertex("ctl", "Controllers (thin) + DTO role filters", 460, 50, 300, 70, COMP, parent=app)
c["svc"] = p.vertex("svc", "Services: Auth, Project, Progress, Budget, Payment, Resource, Kpi, Alert (rules A1-A15), Report, Backup, Sync", 460, 200, 360, 110, COMP, parent=app)
c["repo"] = p.vertex("repo", "Repositories (PDO, prepared statements)", 460, 420, 300, 70, COMP, parent=app)
c["sess"] = p.vertex("sess", "DbSessionHandler", 80, 420, 260, 70, COMP, parent=app)
c["sms"] = p.vertex("sms", "SMS adapters: SandboxSmsGateway, HttpSmsGateway [It.2]", 1000, 200, 320, 80, COMP, parent=app)
c["pdf"] = p.vertex("pdf", "PdfRenderer (Dompdf) / CsvWriter", 1000, 360, 300, 70, COMP, parent=app)
c["clk"] = p.vertex("clk", "Clock (Africa/Accra)", 1000, 500, 240, 60, COMP, parent=app)
c["evt"] = p.vertex("evt", "Event dispatcher + AuditLogger", 1360, 360, 220, 70, COMP, parent=app)
c["db"] = p.vertex("db", "MySQL / MariaDB database (31 tables)", 600, 50, 340, 80, COMP, parent=dat)
balls = {}
def ball(key, label, x, y, provider):
    balls[key] = p.vertex(key, label, x, y, 20, 20, BALL)
    p.edge(c[provider], balls[key], "", PROVIDES)
ball("iApi", "REST API /api/v1 (JSON)", 690, 400, "fc")
ball("iSvc", "Service interfaces", 640, 570, "svc")
ball("iRepo", "Repository interfaces", 640, 780, "repo")
ball("iSess", "SessionHandlerInterface", 240, 770, "sess")
ball("iSms", "SmsGateway", 950, 650, "sms")
ball("iPdf", "ReportRenderer", 950, 800, "pdf")
ball("iClk", "Clock", 950, 940, "clk")
ball("iPdo", "PDO (TLS optional)", 790, 1100, "db")
for consumer, iface in [("js", "iApi"), ("sw", "iApi"), ("ctl", "iSvc"), ("svc", "iRepo"), ("mw", "iSess"), ("svc", "iSms"), ("svc", "iPdf"),
                        ("svc", "iClk"), ("repo", "iPdo"), ("sess", "iPdo")]:
    p.edge(c[consumer], balls[iface], "", REQUIRES)
p.edge(c["fc"], c["mw"], "pipeline", DASH + "fontSize=10;")
p.edge(c["mw"], c["ctl"], "dispatch", DASH + "fontSize=10;exitX=1;exitY=0.5;entryX=0;entryY=0.5;exitPerimeter=0;entryPerimeter=0;", points=[(460, 660), (460, 510)])
p.edge(c["svc"], c["evt"], "publish events", DASH + "fontSize=10;exitX=1;exitY=0.9;entryX=0.5;entryY=0;exitPerimeter=0;entryPerimeter=0;", points=[(1510, 740)])
p.edge(c["shell"], c["js"], "loads", DASH + "fontSize=10;")
p.edge(c["js"], c["chart"], "uses", DASH + "fontSize=10;")
p.vertex("n1", "Dependencies point to interfaces (dependency inversion): services never call PDO or an SMS provider directly. The browser talks only to /api/v1; role and field filtering happen on the server.", 1740, 260, 420, 140, NOTE)

# ---------- Page 2: local deployment ----------
LEG2 = ["3D box = node (device or execution environment)", "Folded note = deployed artefact", "Thick line = communication path (protocol)",
        "Versions marked TBC are confirmed by env_check.ps1", "No data leaves the PC except optional Git push", "Local only: HTTP is acceptable"]
p = f.page("cp2", "Local deployment (XAMPP)", "Component diagram 2: local deployment view (Windows + XAMPP)", width=2000, height=1100, legend=LEG2, notation="UML 2.5 deployment view")
p.legend_pos = (1540, 20)
pc = p.vertex("pc", "Developer PC: Windows, XAMPP 3.3.0 control panel", 40, 140, 1440, 780, NODE)
br = p.vertex("br", "Web browser (Chrome / Edge / Firefox)", 40, 60, 320, 160, NODE, parent=pc)
p.vertex("brA", "Page shells, JS modules, Chart.js (cached)", 20, 60, 280, 60, ART, parent=br)
ap = p.vertex("ap", "Apache 2.4 + PHP 8.x (version TBC), mod_rewrite, OPcache", 460, 60, 460, 300, NODE, parent=pc)
p.vertex("apA", "lubie-dashboard (DocumentRoot = public/), vendor/ via Composer", 20, 60, 400, 60, ART, parent=ap)
p.vertex("apB", ".env (local secrets, outside public/)", 20, 150, 300, 50, ART, parent=ap)
p.vertex("apC", "bin/console.php (setup, migrate, restore:verify)", 20, 220, 360, 50, ART, parent=ap)
db = p.vertex("db", "MariaDB (XAMPP, version TBC)", 1020, 60, 380, 300, NODE, parent=pc)
p.vertex("dbA", "lubie_dashboard (dev data)", 20, 60, 300, 50, ART, parent=db)
p.vertex("dbB", "lubie_test (reset by script)", 20, 130, 300, 50, ART, parent=db)
p.vertex("dbC", "lubie_restore_check (restore tests)", 20, 200, 300, 50, ART, parent=db)
ts = p.vertex("ts", "Windows Task Scheduler", 460, 480, 400, 200, NODE, parent=pc)
p.vertex("tsA", "Daily mysqldump + 7-Zip AES backup to external drive", 20, 60, 340, 60, ART, parent=ts)
vs = p.vertex("vs", "VS Code / Sublime Text, Git, PHPUnit", 40, 480, 320, 200, NODE, parent=pc)
p.vertex("vsA", "Source, tests, docs/diagrams", 20, 60, 260, 50, ART, parent=vs)
ext = p.vertex("ext", "External drive / company cloud folder (off-site copy)", 1020, 480, 380, 200, NODE, parent=pc)
ph = p.vertex("ph", "Phone on same Wi-Fi (optional test)", 40, 960, 360, 100, NODE)
gh = p.vertex("gh", "GitHub repository (PRINCE2025EASY/LUBIE-DASHBOARD)", 1540, 400, 400, 120, NODE)
p.edge(br, ap, "HTTP localhost / lubie.local :80", PATH)
p.edge(ap, db, "PDO, TCP 3306 (least-privilege user)", PATH)
p.edge(ts, db, "mysqldump", PATH)
p.edge(ts, ext, "encrypted .sql.gz", PATH)
p.edge(ph, ap, "HTTP over LAN (testing only)", PATH)
p.edge(vs, gh, "git push (after approval, Stage 5)", PATH)

# ---------- Page 3: production deployment ----------
LEG3 = ["3D box = node", "Folded note = artefact", "Thick line = communication path", "Red dashed frame = TLS boundary (all traffic encrypted)",
        "Host and DB provider decided in Stage 6", "No persistent disk relied on in the container"]
p = f.page("cp3", "Production deployment", "Component diagram 3: production deployment view (free tier, provider decided in Stage 6)", width=2200, height=1200, legend=LEG3, notation="UML 2.5 deployment view")
p.legend_pos = (1740, 20)
tls = p.vertex("tls", "TLS boundary", 420, 120, 1260, 1000, "rounded=1;arcSize=2;dashed=1;strokeColor=#b85450;fillColor=none;strokeWidth=2;verticalAlign=top;align=right;spacingRight=10;fontColor=#b85450;fontStyle=1;allowOverlap=1;")
dev = p.vertex("dev", "User devices: smartphones (9/15), desktops (5/15), tablets", 40, 200, 320, 220, NODE)
p.vertex("devA", "Browser + installed offline web app [It.2]", 20, 70, 270, 60, ART, parent=dev)
host = p.vertex("host", "Container host (free tier; Render.com or alternative)", 480, 200, 560, 400, NODE)
ctr = p.vertex("ctr", "Docker container: official php:8.x-apache, non-root, honours $PORT", 20, 60, 520, 300, NODE, parent=host)
p.vertex("ctrA", "lubie-dashboard image (public/ DocumentRoot, OPcache, production php.ini)", 20, 60, 460, 60, ART, parent=ctr)
p.vertex("ctrB", "Secrets as environment variables (DB, APP_KEY, SMS, BACKUP_TOKEN)", 20, 150, 460, 60, ART, parent=ctr)
p.vertex("ctrC", "/health endpoint (no secrets)", 20, 230, 300, 50, ART, parent=ctr)
dbn = p.vertex("dbn", "Managed MySQL-compatible database (free tier, provider TBD)", 1120, 200, 500, 220, NODE)
p.vertex("dbnA", "lubie_dashboard (prod schema + production seed + demo seed for evaluation)", 20, 70, 440, 60, ART, parent=dbn)
smsn = p.vertex("smsn", "SMS gateway (Arkesel-compatible HTTPS API)", 1120, 500, 500, 140, NODE)
sch = p.vertex("sch", "External scheduler (free cron service)", 480, 700, 500, 140, NODE)
bst = p.vertex("bst", "Company-controlled off-site backup storage", 1120, 760, 500, 160, NODE)
p.vertex("bstA", "Encrypted daily backups: 7 daily, 4 weekly", 20, 70, 360, 50, ART, parent=bst)
ghn = p.vertex("ghn", "GitHub (source, CI later)", 40, 700, 320, 120, NODE)
adm = p.vertex("adm", "Administrator PC (manual backup download)", 40, 940, 320, 120, NODE)
p.edge(dev, host, "HTTPS 443 (HSTS, forced redirect)", PATH)
p.edge(host, dbn, "MySQL over TLS", PATH)
p.edge(host, smsn, "HTTPS POST (5 s timeout)", PATH)
p.edge(sch, host, "HTTPS POST /backup/run (Bearer token)", PATH)
p.edge(sch, bst, "store encrypted file", PATH)
p.edge(ghn, host, "deploy on push to main", PATH)
p.edge(adm, host, "HTTPS (Admin backup download)", PATH)
p.vertex("n3", "Free tiers can sleep (cold start), have ephemeral disks and may lack cron; the design is stateless (DB sessions, streamed exports, external scheduler) so these limits do not lose data. Free tiers suit a thesis prototype, not long-term production.",
         1740, 260, 420, 170, NOTE)

rep = f.save()
print(rep["pages"], rep["vertices"], rep["edges"], rep["problems"][:10])
