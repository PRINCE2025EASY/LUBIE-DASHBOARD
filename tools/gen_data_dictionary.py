import os, sys
sys.path.insert(0, os.path.dirname(__file__))
from schema_model import parse
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
T = parse(os.path.join(ROOT, 'database', 'schema.sql'))
PURPOSE = {
 'roles':'Six system roles and their dashboard path (thesis entity).','users':'Accounts, SMS phone, password hash, lockout state (thesis entity).',
 'sessions':'Database session store so the web tier is stateless (added: NFR-11).','login_attempts':'Throttling and lockout evidence (added: FR-29, ST-08).',
 'projects':'Core project data, payment period, status, polling stamp (thesis entity).','project_assignments':'Resolves users x projects; unique pair (thesis entity).',
 'activities':'Tasks with planned/actual dates and budgeted cost (thesis entity).','activity_dependencies':'Optional finish-to-start links (added: FR-08).',
 'milestones':'Key events with due/achieved dates (thesis entity).','delay_reasons':'Standard delay-reason list; critical flag drives A15 (added: FR-32).',
 'progress_records':'Dated % complete with approval workflow (thesis entity, extended: FR-30).','progress_photos':'One optional compressed photo per record (added: FR-39, Could).',
 'budgets':'Budget versions with Director approval and supersession (thesis entity, extended: FR-31).','expenditure_categories':'Standard expenditure categories (added: NFR-07 consistency).',
 'expenditures':'Expenditure entries; void instead of delete (thesis entity).','payment_certificates':'Valuations and certificates (thesis entity).',
 'payment_transactions':'Partial and multiple payments per certificate (added: FR-28).','resource_categories':'Labour, material, equipment (thesis entity).',
 'resources':'Project resource items, reorder level, rates, equipment status (thesis entity).','resource_usage':'Dated receipts, usage, attendance, hours (thesis entity).',
 'material_requests':'Simple request tracker (added: FR-33).','alert_settings':'Global limits and project thresholds (thesis entity, extended: FR-21).',
 'threshold_proposals':'QS threshold proposals for Director approval (added: FR-21).','notifications':'Alert instances with de-duplication (thesis entity, redesigned).',
 'notification_recipients':'Per-recipient read/acknowledge and SMS sub-status (added: FR-20, FR-22).','sms_log':'SMS attempts and cap accounting (added: FR-22).',
 'audit_logs':'Append-only audit trail (thesis entity).','backup_log':'Backups, restores and restore tests (added: FR-36).',
 'system_settings':'Configurable parameters (added: NFR-10).','sync_requests':'Offline replay idempotency (added: FR-38).','schema_migrations':'Applied migration versions (added: maintainability).'}
o = ["# Data dictionary", "", "Generated from `database/schema.sql` v0.2.0 by `tools/gen_data_dictionary.py`. Do not edit by hand.", "",
     f"{len(T)} tables. Thesis entities: 16 (all kept). Added tables are justified in the Purpose line.", ""]
for n, t in T.items():
    o += [f"## {n}", "", PURPOSE.get(n, ''), "", "| Column | Type | Null | Default | Key | Notes |", "|---|---|---|---|---|---|"]
    fkmap = {f['col']: f for f in t['fks']}
    for c in t['cols']:
        key = []
        if c['name'] in t['pk']: key.append('PK')
        if c['name'] in fkmap: key.append(f"FK → {fkmap[c['name']]['ref']}.{fkmap[c['name']]['refcol']}")
        for un, uc in t['uqs']:
            if c['name'] in uc: key.append(f"UQ {un}")
        notes = c['comment'] + (' Auto-increment.' if c['auto'] else '') + (' Generated (stored).' if c['generated'] else '')
        o.append(f"| {c['name']} | {c['type']} | {'Y' if c['null'] else 'N'} | {c['default']} | {'; '.join(key)} | {notes.strip()} |")
    if t['checks']:
        o += ["", "Checks: " + "; ".join(f"`{k}`: {v}" for k, v in t['checks'])]
    o.append("")
open(os.path.join(ROOT, 'docs', 'DATA_DICTIONARY.md'), 'w', encoding='utf-8').write("\n".join(o))
print(len(o))
