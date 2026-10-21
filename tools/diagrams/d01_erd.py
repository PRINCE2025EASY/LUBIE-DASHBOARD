import os
from drawio import DrawioFile
from schema_model import parse

T = parse(os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "database", "schema.sql"))
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "docs", "diagrams", "01-database-erd.drawio")
W, RH, HEAD = 340, 20, 30

LEGEND = ["PK = primary key, FK = foreign key, UQ = unique",
          "Crow's foot: ERmandOne = exactly one; ERzeroToOne = zero or one",
          "ERzeroToMany = zero or many (child side of every FK)",
          "Dashed grey box = entity drawn in full on another page",
          "Integrity rules: see note box on each page",
          "Generated from database/schema.sql v0.2.0 (31 tables)"]

PAGES_OLD = [
 ("A", "ERD A - Identity and projects", [["roles", "users"], ["sessions", "login_attempts", "sync_requests"], ["projects", "project_assignments"]], {},
  "Rules: one role per user; username unique; phone must start +233; (project_id, user_id) unique; planned_finish >= planned_start; contract_sum >= 0; users.created_by and projects.created_by optional (SET NULL)."),
 ("B", "ERD B - Schedule and progress", [["activities", "activity_dependencies"], ["milestones", "delay_reasons"], ["progress_records", "progress_photos"]], {"projects": "A", "users": "A"},
  "Rules: activity code unique per project; finish >= start; budgeted_cost >= 0 or NULL; no self-dependency; percent 0-100; returned requires reason; reviewed requires reviewer; one photo per record, <= 300 KB, JPEG/PNG/WebP; client_uuid unique (offline replay)."),
 ("C", "ERD C - Cost and payment", [["budgets"], ["expenditure_categories", "expenditures"], ["payment_certificates", "payment_transactions"]], {"projects": "A", "users": "A", "activities": "B"},
  "Rules: (project_id, version_no) unique; one current approved budget per project via UQ(project_id, is_current) with is_current 1 or NULL; requester <> approver after v1; expenditure amount > 0; void needs reason; cert_no unique per project; certified needs amount and date; payment amount > 0; overdue derived, not stored."),
 ("D", "ERD D - Resources", [["resource_categories", "resources"], ["resource_usage"], ["material_requests"]], {"projects": "A", "users": "A", "activities": "B"},
  "Rules: resource name unique per project and category; quantities >= 0; balance = received - used (derived); equipment_status only for equipment; request quantity > 0."),
 ("E", "ERD E - Alerts, audit and system", [["alert_settings", "threshold_proposals"], ["notifications", "notification_recipients", "sms_log"], ["audit_logs", "backup_log", "system_settings", "schema_migrations"]], {"projects": "A", "users": "A"},
  "Rules: one setting per (scope, key) via generated scope_project_id; min_allowed <= max_allowed; only one OPEN alert per dedup_key (generated open_dedup_key); resolved requires cleared_at; one recipient row per (alert, user); audit_logs append-only (DB grant: SELECT, INSERT only)."),
]

PAGES = [
 ("A", "ERD A - Identity and projects", [["sessions", "login_attempts", "sync_requests"], ["roles", "users"], ["project_assignments", "projects"]], {}, PAGES_OLD[0][4]),
 ("B", "ERD B - Schedule and progress", [["progress_photos", "delay_reasons"], ["progress_records", "*users"], ["activities", "activity_dependencies"], ["milestones", "*projects"]], {"projects": "A", "users": "A"}, PAGES_OLD[1][4]),
 ("C", "ERD C - Cost and payment", [["expenditure_categories", "payment_transactions"], ["expenditures", "payment_certificates"], ["*projects", "*users", "*activities"], ["budgets"]], {"projects": "A", "users": "A", "activities": "B"}, PAGES_OLD[2][4]),
 ("D", "ERD D - Resources", [["resource_categories", "material_requests"], ["resources", "resource_usage"], ["*projects", "*users", "*activities"]], {"projects": "A", "users": "A", "activities": "B"}, PAGES_OLD[3][4]),
 ("E", "ERD E - Alerts, audit and system", [["sms_log", "notification_recipients"], ["notifications", "threshold_proposals"], ["*projects", "*users"], ["alert_settings", "audit_logs", "backup_log"], ["system_settings", "schema_migrations"]], {"projects": "A", "users": "A"}, PAGES_OLD[4][4]),
]
f = DrawioFile(OUT)
for code, name, columns, stubs, rules in PAGES:
    p = f.page(f"erd{code}", name, name.replace("ERD", "Entity Relationship Diagram"), width=2000, height=1300,
               legend=LEGEND, notation="Crow's foot (Information Engineering)")
    row_of = {}      # (table, column) -> cell id
    x0, colgap = 40, 90
    tables_here = [t for col in columns for t in col if not t.startswith('*')]
    # stub column on the far right below legend
    for ci, col in enumerate(columns):
        y = 140
        x = x0 + ci * (W + colgap)
        for tname in col:
            if tname.startswith("*"):
                sname = tname[1:]
                box = p.vertex(f"stub_{sname}", f"{sname} (see page {stubs[sname]})", x, y, W, HEAD + RH,
                               "swimlane;fontStyle=2;childLayout=stackLayout;horizontal=1;startSize=30;verticalAlign=top;horizontalStack=0;collapsible=0;dashed=1;fillColor=#f5f5f5;strokeColor=#999999;")
                row_of[(sname, "id")] = p.vertex(f"stub_{sname}_id", "PK     id", 0, HEAD, W, RH,
                                                 "text;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;spacingLeft=4;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;fontFamily=Courier New;fontSize=11;fontStyle=5;", parent=box)
                y += HEAD + RH + 60
                continue
            t = T[tname]
            h = HEAD + RH * len(t["cols"])
            box = p.vertex(tname, tname, x, y, W, h,
                           "swimlane;fontStyle=1;childLayout=stackLayout;horizontal=1;startSize=30;verticalAlign=top;horizontalStack=0;resizeParent=1;resizeParentMax=0;resizeLast=0;collapsible=0;marginBottom=0;fillColor=#dae8fc;strokeColor=#6c8ebf;")
            fkcols = {fk["col"]: fk for fk in t["fks"]}
            uqcols = {c for _, cols in t["uqs"] for c in cols}
            for ri, c in enumerate(t["cols"]):
                tags = []
                if c["name"] in t["pk"]: tags.append("PK")
                if c["name"] in fkcols: tags.append("FK")
                if c["name"] in uqcols and "PK" not in tags: tags.append("UQ")
                typ = c["type"]
                if typ.startswith("ENUM"):
                    typ = "ENUM"
                label = f"{','.join(tags):6} {c['name']} : {typ}{'' if c['null'] else ' NN'}"
                if c["name"] in fkcols:
                    label += f" → {fkcols[c['name']]['ref']}"
                style = "text;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;spacingLeft=4;spacingRight=4;overflow=hidden;rotatable=0;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;fontFamily=Courier New;fontSize=11;"
                if "PK" in tags:
                    style += "fontStyle=5;"
                row_of[(tname, c["name"])] = p.vertex(f"{tname}_{c['name']}", label, 0, HEAD + RH * ri, W, RH, style, parent=box)
            y += h + 40
    sx = x0 + len(columns) * (W + colgap)
    sy = 330
    # rules note
    p.legend_pos = (sx, sy + 260)
    p.vertex("rules", rules, sx, sy, 300, 240,
             "shape=note;whiteSpace=wrap;align=left;verticalAlign=top;spacingLeft=6;spacingTop=4;fillColor=#fff2cc;strokeColor=#d6b656;size=14;")
    # FK edges
    for tname in tables_here:
        t = T[tname]
        single_unique = {cols[0] for _, cols in t["uqs"] if len(cols) == 1}
        if len(t["pk"]) == 1:
            single_unique.add(t["pk"][0])
        for fk in t["fks"]:
            src = row_of.get((tname, fk["col"]))
            tgt = row_of.get((fk["ref"], fk["refcol"]))
            if not src or not tgt:
                raise SystemExit(f"missing endpoint for {tname}.{fk['col']} -> {fk['ref']}")
            col = next(c for c in t["cols"] if c["name"] == fk["col"])
            parent_end = "ERzeroToOne" if col["null"] else "ERmandOne"
            child_end = "ERzeroToOne" if fk["col"] in single_unique else "ERzeroToMany"
            p.edge(src, tgt, "", f"edgeStyle=entityRelationEdgeStyle;startArrow={child_end};endArrow={parent_end};startFill=0;endFill=0;strokeColor=#555555;")

rep = f.save()
print(rep["pages"], rep["vertices"], rep["edges"], rep["problems"][:10])
