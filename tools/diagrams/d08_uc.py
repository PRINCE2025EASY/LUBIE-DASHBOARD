from common import *

f = DrawioFile(OUTDIR + "08-use-case-diagram.drawio")
LEG = ["Stick figure = actor (primary left/right, secondary below)", "Ellipse = use case (UCnn)", "Solid line = association",
       "Dashed arrow «include» = always performed", "Dashed arrow «extend» [condition] = optional extension", "Specifications: docs/USE_CASE_SPECIFICATIONS.md"]
p = f.page("uc", "Use case diagram", "Use case diagram: Lubie Construction Works Monitoring Dashboard", width=2200, height=1400, legend=LEG, notation="UML 2.5 use case diagram")
p.legend_pos = (1740, 20)
p.vertex("boundary", "Lubie Project Monitoring Dashboard (system boundary)", 360, 140, 1160, 960,
         "rounded=0;" + WRAP + "fillColor=none;strokeColor=#333333;verticalAlign=top;align=center;fontStyle=1;spacingTop=6;allowOverlap=1;")
UCS = "shape=ellipse;perimeter=ellipsePerimeter;" + WRAP + "fillColor=#dae8fc;strokeColor=#6c8ebf;fontSize=11;allowOverlap=1;"
ACT = "shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;outlineConnect=0;fillColor=#f5f5f5;strokeColor=#333333;fontStyle=1;"
U = {}
def uc(k, label, x, y): U[k] = p.vertex(k, f"{k}\n{label}", x, y, 240, 70, UCS)
uc("UC01", "Log in", 760, 180); uc("UC02", "Redirect to role dashboard", 1060, 180)
left = [("UC06", "Manage projects and assignments"), ("UC03", "Manage users and settings"), ("UC04", "View audit trail"), ("UC05", "Back up and restore data"),
        ("UC13", "Compare portfolio of projects"), ("UC15", "Approve or reject budget revision"), ("UC19", "Set alert thresholds"), ("UC23", "View client summary")]
mid = [("UC24", "Change password"), ("UC11", "View project dashboard and KPIs"), ("UC12", "Calculate KPIs"), ("UC22", "Export reports (PDF, CSV)"),
       ("UC20", "Receive and manage alerts"), ("UC25", "Evaluate alerts"), ("UC21", "Send SMS notification")]
right = [("UC07", "Maintain activities and milestones"), ("UC08", "Record progress"), ("UC09", "Work offline and sync [It.2]"), ("UC18", "Record resources and material requests"),
         ("UC10", "Approve or return progress"), ("UC14", "Record budget and request revision"), ("UC16", "Record expenditure"), ("UC17", "Record valuations and payments")]
for i, (k, l) in enumerate(left): uc(k, l, 400, 320 + i * 100)
for i, (k, l) in enumerate(mid): uc(k, l, 820, 320 + i * 100)
for i, (k, l) in enumerate(right): uc(k, l, 1240, 320 + i * 100)
A = {}
for k, x, y in [("Administrator", 140, 360), ("Director", 140, 660), ("Client", 140, 960), ("Site Engineer", 1660, 360), ("Project Manager", 1660, 640), ("Quantity Surveyor", 1660, 900)]:
    A[k] = p.vertex(k, k, x, y, 40, 80, ACT)
A["SMS Gateway"] = p.vertex("smsgw", "«system» SMS Gateway (secondary)", 920, 1180, 40, 80, ACT)
ASSOC = "endArrow=none;strokeColor=#333333;"
# shared Log in via gutters
for k in ("Administrator", "Director", "Client"):
    p.edge(A[k], U["UC01"], "", ASSOC + "exitX=1;exitY=0.3;entryX=0;entryY=0.5;exitPerimeter=0;", points=[(300, p.get(A[k])["y"] + 20), (300, 220)])
for k in ("Site Engineer", "Project Manager", "Quantity Surveyor"):
    p.edge(A[k], U["UC01"], "", ASSOC + "exitX=0;exitY=0.3;entryX=0.5;entryY=0;exitPerimeter=0;", points=[(1580, p.get(A[k])["y"] + 20), (1580, 150), (880, 150)])
links = {"Administrator": ["UC03", "UC04", "UC05", "UC06", "UC22", "UC24"],
         "Director": ["UC11", "UC13", "UC15", "UC19", "UC20", "UC22"],
         "Client": ["UC23", "UC20", "UC22"],
         "Site Engineer": ["UC07", "UC08", "UC09", "UC18", "UC11", "UC20"],
         "Project Manager": ["UC06", "UC07", "UC08", "UC10", "UC11", "UC14", "UC18", "UC19", "UC20", "UC22"],
         "Quantity Surveyor": ["UC11", "UC14", "UC16", "UC17", "UC19", "UC20", "UC22"],
         "SMS Gateway": ["UC21"]}
for a, ucs in links.items():
    for k in ucs:
        p.edge(A[a], U[k], "", ASSOC)
INC = "dashed=1;endArrow=open;endFill=0;fontSize=10;labelBackgroundColor=#ffffff;"
for a, b in [("UC01", "UC02"), ("UC11", "UC12"), ("UC22", "UC12"), ("UC13", "UC12"), ("UC23", "UC12"),
             ("UC08", "UC25"), ("UC10", "UC25"), ("UC16", "UC25"), ("UC17", "UC25")]:
    p.edge(U[a], U[b], "«include»", INC)
p.edge(U["UC21"], U["UC25"], "«extend» [Critical or High, staff recipient]", INC)
p.edge(U["UC09"], U["UC08"], "«extend» [no connection]", INC)
p.vertex("n", "Every actor uses the single UC01 Log in, which always includes UC02 (server-side redirect by role from the database). Clients see only UC23 and their own A4 notices in UC20; they never receive SMS. Admin has no project-editing use cases beyond UC06 set-up and assignments.",
         1740, 260, 420, 170, NOTE)
rep = f.save()
print(rep["pages"], rep["vertices"], rep["edges"], rep["problems"][:10])
