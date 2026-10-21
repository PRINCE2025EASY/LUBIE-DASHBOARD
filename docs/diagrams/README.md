# Diagrams: how to open, edit and check them

## Files

| File | Pages (tabs) |
|---|---|
| 01-database-erd.drawio | ERD A identity and projects; B schedule and progress; C cost and payment; D resources; E alerts, audit and system (31 tables, 57 relationships) |
| 02-system-structure-chart.drawio | Top level; module detail (10 control modules, 6 shared utilities) |
| 03-data-flow-diagram.drawio | Context; Level 0; Level 1 progress and KPI; Level 1 cost and budget revision; Level 1 alerts and notification |
| 04-sequence-diagrams.drawio | (a) to (j): login, progress approval, expenditure and SMS, budget revision, polling, client summary, report export, project threshold, backup and restore, poor network |
| 05-activity-diagrams.drawio | Site-to-management flow; progress approval; budget revision; alert evaluation and SMS; user lifecycle; backup and recovery |
| 06-state-diagrams.drawio | Activity schedule status; progress record; budget version; alert with SMS sub-states; payment certificate; user account; project |
| 07-component-diagrams.drawio | Logical three-tier; local deployment (XAMPP); production deployment |
| 08-use-case-diagram.drawio | Use case diagram (specifications in `docs/USE_CASE_SPECIFICATIONS.md`) |

The `.drawio` files are authoritative. `previews/*.png` are quick-look images only (see "Previews" below).

## Open and edit

1. Go to https://app.diagrams.net (or install draw.io Desktop).
2. File > Open from > Device, choose a `.drawio` file. Each diagram is a tab at the bottom.
3. Every element is a native shape: click to move, resize, rename (double-click), delete, or drag new shapes from the left panel. Connectors stay attached when you move shapes.
4. ERD rows are separate text cells inside each table, so you can add or remove a column row and the table resizes.
5. To export for the thesis: File > Export as > PNG (300 % zoom, transparent off) or SVG; choose "Selection only" off and the current page.
6. If you change the database, regenerate instead of editing by hand: `bash tools/diagrams/build_all.sh` (Python 3) rebuilds all eight files from `database/schema.sql` and the scripts, so the ERD always matches the schema.

## Validation performed on 21/10/2026

| Check | Result |
|---|---|
| Parsed as XML (`xml.etree`) | All 8 files well-formed |
| Uncompressed `mxfile` / `mxGraphModel` | Yes, no base64 or compressed pages |
| Embedded images | None |
| Duplicate cell IDs (within and across pages) | None |
| Dangling connectors (edge without existing source and target) | None; every connector has source and target |
| Empty vertices | None, except intentional ones flagged `allowEmpty=1` (initial/final nodes, fork bars, activation bars, alt dividers) |
| Grid | All x, y, width, height are multiples of 10 |
| Overlaps between sibling shapes | None, except frames, the TLS boundary, the system boundary, interface balls and use-case ellipses inside the boundary, which are flagged `allowOverlap=1` by design |
| Title, version (v0.2.0), date and legend on every page | Yes (39 pages) |
| ERD vs `schema.sql` | 31 of 31 tables, every column row present, 57 of 57 foreign keys drawn |
| Totals | 39 pages, 1,418 vertices, 726 connectors |

## Previews

The PNG previews were rendered in headless Chromium with the open-source mxGraph 4.2.2 library, which is the engine draw.io grew from. It lacks some draw.io-only shapes, so the previews are approximate: crow's-foot end markers, UML lifeline headers, actor stick figures, component tabs, 3D node boxes and swimlane header bars appear as plain shapes or not at all. They were used to find overlaps and crossing problems, which were fixed. Open the `.drawio` file in diagrams.net for the true rendering.

## Notation per file

ERD: crow's foot (ERmandOne, ERzeroToOne, ERzeroToMany). Structure chart: Yourdon and Constantine (○ data couple, ● control couple). DFD: Gane and Sarson (processes numbered, stores D1 to D10, duplicates of external entities marked *). Sequence, activity, state, component, deployment and use case: UML 2.5.

Elements planned for later iterations carry "[It.2]". Diagrams will be updated whenever the implementation changes.
