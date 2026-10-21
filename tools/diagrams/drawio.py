"""Minimal builder for uncompressed draw.io (mxfile) diagrams with validation.

Every page gets a title block (title, version, date) and a legend. Vertices snap to a
10-px grid. validate() checks XML well-formedness, unique IDs, edge endpoints, empty
labels, grid alignment and overlaps between sibling vertices.
"""
from xml.sax.saxutils import escape
import xml.etree.ElementTree as ET
import re

VERSION = "v0.2.0"
DATE = "21/10/2026"
FONT = "fontFamily=Helvetica;fontSize=12;"

def q(v):
    return escape(str(v), {'"': "&quot;", "\n": "&#xa;"})

def snap(v):
    return int(round(v / 10.0) * 10)

class Page:
    def __init__(self, fid, name, title, width=1600, height=1200, legend=None, notation=None):
        self.fid, self.name, self.title = fid, name, title
        self.cells = []   # dicts
        self.ids = set()
        self.n = 0
        self.width, self.height = width, height
        self.vertex(f"title", f"{title}\nLubie Construction Works Monitoring Dashboard  |  {VERSION}  |  {DATE}" + (f"\nNotation: {notation}" if notation else ""),
                    20, 20, 900, 60 if not notation else 70,
                    "text;strokeColor=none;fillColor=none;align=left;verticalAlign=top;whiteSpace=wrap;fontStyle=1;fontSize=14;spacingLeft=4;")
        self.legend_items = legend or []

    def nid(self, hint):
        self.n += 1
        base = re.sub(r"[^A-Za-z0-9_]", "_", str(hint))[:40]
        i = f"{self.fid}_{base}_{self.n}"
        return i

    def vertex(self, key, label, x, y, w, h, style, parent="1", allow_empty=False):
        cid = self.nid(key)
        if "whiteSpace=wrap" in style:
            style = style.replace("html=0;", "")
            if "html=1" not in style:
                style = "html=1;" + style
            label = escape(str(label)).replace("\n", "<br>")
        if allow_empty and "allowEmpty=1" not in style:
            style = style + ("" if style.endswith(";") else ";") + "allowEmpty=1;"
        self.cells.append(dict(id=cid, kind="v", label=label, x=snap(x), y=snap(y), w=snap(w), h=snap(h),
                               style=style if FONT in style or "fontSize" in style else style + FONT,
                               parent=parent, allow_empty=allow_empty))
        return cid

    def edge(self, src, tgt, label="", style="", parent="1", points=None):
        cid = self.nid("e")
        base = "html=0;rounded=0;" + FONT
        self.cells.append(dict(id=cid, kind="e", label=label, src=src, tgt=tgt, style=base + style, parent=parent,
                               points=points or []))
        return cid

    def get(self, cid):
        for c in self.cells:
            if c["id"] == cid:
                return c
        raise KeyError(cid)

    def add_legend(self, x=None, y=None):
        if not self.legend_items:
            return
        w = 440
        h = 30 + 22 * len(self.legend_items)
        x = self.width - w - 20 if x is None else x
        y = 20 if y is None else y
        box = self.vertex("legend", "Legend", x, y, w, h,
                          "swimlane;startSize=26;fontStyle=1;align=left;verticalAlign=top;spacingLeft=6;collapsible=0;fillColor=#f5f5f5;")
        for i, item in enumerate(self.legend_items):
            self.vertex("legitem", item, 6, 30 + 22 * i, w - 12, 20,
                        "text;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;whiteSpace=wrap;spacingLeft=4;", parent=box)
        return box

    def xml(self):
        out = [f'<diagram id="{q(self.fid)}" name="{q(self.name)}">',
               f'<mxGraphModel dx="1400" dy="900" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="{self.width}" pageHeight="{self.height}" math="0" shadow="0">',
               '<root>', '<mxCell id="0"/>', '<mxCell id="1" parent="0"/>']
        for c in self.cells:
            if c["kind"] == "v":
                out.append(f'<mxCell id="{c["id"]}" value="{q(c["label"])}" style="{q(c["style"])}" vertex="1" parent="{c["parent"]}">'
                           f'<mxGeometry x="{c["x"]}" y="{c["y"]}" width="{c["w"]}" height="{c["h"]}" as="geometry"/></mxCell>')
            else:
                pts = ""
                if c["points"]:
                    pts = '<Array as="points">' + "".join(f'<mxPoint x="{snap(px)}" y="{snap(py)}"/>' for px, py in c["points"]) + "</Array>"
                out.append(f'<mxCell id="{c["id"]}" value="{q(c["label"])}" style="{q(c["style"])}" edge="1" parent="{c["parent"]}" source="{c["src"]}" target="{c["tgt"]}">'
                           f'<mxGeometry relative="1" as="geometry">{pts}</mxGeometry></mxCell>')
        out += ['</root>', '</mxGraphModel>', '</diagram>']
        return "\n".join(out)


class DrawioFile:
    def __init__(self, path):
        self.path = path
        self.pages = []

    def page(self, *a, **k):
        p = Page(*a, **k)
        self.pages.append(p)
        return p

    def save(self):
        for p in self.pages:
            p.add_legend(*getattr(p, 'legend_pos', (None, None)))
        body = "\n".join(p.xml() for p in self.pages)
        xml = (f'<?xml version="1.0" encoding="UTF-8"?>\n'
               f'<mxfile host="app.diagrams.net" agent="lubie-diagram-generator" version="24.7.17" type="device">\n{body}\n</mxfile>\n')
        open(self.path, "w", encoding="utf-8").write(xml)
        return validate(self.path)


def abs_box(cells_by_id, c):
    x, y = c["x"], c["y"]
    p = c["parent"]
    while p not in ("0", "1"):
        pc = cells_by_id[p]
        x += pc["x"]; y += pc["y"]
        p = pc["parent"]
    return x, y, c["w"], c["h"]


def validate(path):
    """Return a report dict; raises on hard errors."""
    tree = ET.parse(path)               # well-formed XML
    root = tree.getroot()
    assert root.tag == "mxfile"
    report = {"file": path, "pages": 0, "vertices": 0, "edges": 0, "problems": []}
    all_ids = set()
    for d in root.findall("diagram"):
        report["pages"] += 1
        model = d.find("mxGraphModel")
        assert model is not None, "compressed or missing mxGraphModel"
        cells = model.find("root").findall("mxCell")
        ids = [c.get("id") for c in cells]
        dup = {i for i in ids if ids.count(i) > 1}
        if dup:
            report["problems"].append(f"{d.get('name')}: duplicate ids {sorted(dup)[:5]}")
        cross = all_ids.intersection(ids) - {"0", "1"}
        if cross:
            report["problems"].append(f"{d.get('name')}: ids reused across pages {sorted(cross)[:5]}")
        all_ids.update(ids)
        idset = set(ids)
        byid = {}
        for c in cells:
            if c.get("vertex") == "1":
                report["vertices"] += 1
                g = c.find("mxGeometry")
                vals = [float(g.get(k, 0)) for k in ("x", "y", "width", "height")]
                if any(v % 10 for v in vals):
                    report["problems"].append(f"{d.get('name')}: off-grid {c.get('id')} {vals}")
                if not (c.get("value") or "").strip() and "allowEmpty=1" not in (c.get("style") or ""):
                    report["problems"].append(f"{d.get('name')}: empty vertex {c.get('id')}")
                byid[c.get("id")] = dict(x=vals[0], y=vals[1], w=vals[2], h=vals[3], parent=c.get("parent"),
                                         style=c.get("style") or "", id=c.get("id"))
            elif c.get("edge") == "1":
                report["edges"] += 1
                s, t = c.get("source"), c.get("target")
                if not s or not t or s not in idset or t not in idset:
                    report["problems"].append(f"{d.get('name')}: dangling edge {c.get('id')} {s}->{t}")
            if c.get("parent") not in idset and c.get("id") not in ("0",):
                report["problems"].append(f"{d.get('name')}: bad parent {c.get('id')}")
        # overlaps among siblings (skip overlay/frames marked allowOverlap=1)
        groups = {}
        for v in byid.values():
            if "allowOverlap=1" in v["style"]:
                continue
            groups.setdefault(v["parent"], []).append(v)
        for par, vs in groups.items():
            for i in range(len(vs)):
                for j in range(i + 1, len(vs)):
                    a, b = vs[i], vs[j]
                    if a["x"] < b["x"] + b["w"] and b["x"] < a["x"] + a["w"] and a["y"] < b["y"] + b["h"] and b["y"] < a["y"] + a["h"]:
                        report["problems"].append(f"{d.get('name')}: overlap {a['id']} / {b['id']}")
    return report
