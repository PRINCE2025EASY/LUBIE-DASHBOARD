"""Independent reference calculation of KPIs and alert conditions (Stage 2 check).

Reads a database loaded with schema.sql + seed_production.sql + seed_demo.sql and
applies the business rules in the Design Specification s9 by hand-written Python,
separately from the PHP services built in Stage 3. Used to confirm that the demo
seed triggers every alert rule, and later as an oracle for PHPUnit expectations.

Usage: python3 reference_kpi_check.py <database> [mariadb client args...]
"""
import subprocess, sys, datetime as dt
from decimal import Decimal as D

DB = sys.argv[1]
EXTRA = sys.argv[2:]

def q(sql):
    out = subprocess.run(["mariadb", *EXTRA, "-B", "-N", DB, "-e", sql], capture_output=True, text=True, check=True).stdout
    return [line.split("\t") for line in out.strip().split("\n") if line]

today = dt.date.fromisoformat(q("SELECT CURDATE()")[0][0])
d = lambda s: None if s in ("NULL", "") else dt.date.fromisoformat(s)
setting = {k: D(v) for k, v in q("SELECT setting_key, value FROM alert_settings WHERE project_id IS NULL")}

def planned_fraction(ps, pf, t):
    if t < ps: return D(0)
    if t >= pf: return D(1)
    return D((t - ps).days) / D((pf - ps).days)

found = {}
def hit(rule, msg): found.setdefault(rule, []).append(msg)

for pid, code, status, period in q("SELECT id, code, status, payment_period_days FROM projects"):
    if status not in ("active", "on_hold"):
        continue
    proj = {k: v for k, v in q(f"SELECT setting_key, value FROM alert_settings WHERE project_id={pid}")}
    s = {**setting, **{k: D(v) for k, v in proj.items()}}
    acts = q(f"""SELECT a.id, a.code, a.planned_start, a.planned_finish, a.actual_start, IFNULL(a.budgeted_cost,'NULL'),
                 IFNULL((SELECT percent_complete FROM progress_records p WHERE p.activity_id=a.id AND p.status='approved'
                         ORDER BY record_date DESC, id DESC LIMIT 1),'NULL')
                 FROM activities a WHERE a.project_id={pid} AND a.status='active'""")
    bac = ev = pv = D(0)
    for aid, acode, ps, pf, ast, cost, pct in acts:
        ps, pf, ast = d(ps), d(pf), d(ast)
        pctv = D(0) if pct == "NULL" else D(pct)
        c = D(0) if cost == "NULL" else D(cost)
        bac += c; ev += c * pctv / 100; pv += c * planned_fraction(ps, pf, today)
        if today > pf and pctv < 100:
            late = (today - pf).days
            hit("A1", f"{code} {acode} delayed {late} d" + (" (SMS)" if late > s["delay_sms_days"] else ""))
        if today > ps and ast is None and pct == "NULL":
            hit("A2", f"{code} {acode} late start")
    ac = D(q(f"SELECT IFNULL(SUM(amount),0) FROM expenditures WHERE project_id={pid} AND status='active' AND expense_date<=CURDATE()")[0][0])
    budget = D(q(f"SELECT IFNULL(MAX(amount),0) FROM budgets WHERE project_id={pid} AND is_current=1")[0][0])
    consumed = ac / budget * 100 if budget else None
    if consumed is not None:
        if consumed >= s["budget_warning_pct"]: hit("A5", f"{code} budget consumed {consumed:.1f}%")
        if consumed > 100: hit("A6", f"{code} budget exceeded {consumed:.1f}%")
        if consumed > 100 + s["budget_overrun_sms_pct"]: hit("A6b", f"{code} critical overrun {consumed:.1f}%")
    cpi = ev / ac if ac else None
    spi = ev / pv if pv else None
    if ev > 0 and cpi is not None and cpi < s["cpi_min"]: hit("A7", f"{code} CPI {cpi:.2f}")
    if pv > 0 and spi is not None and spi < s["spi_min"]: hit("A8", f"{code} SPI {spi:.2f}")
    print(f"{code}: BAC {bac:,.2f}  PV {pv:,.2f}  EV {ev:,.2f}  AC {ac:,.2f}  completion {ev/bac*100:.1f}%  "
          f"SPI {spi:.2f}  CPI {cpi:.2f}  budget consumed {consumed:.1f}%")
    for mid, name, due, ach in q(f"SELECT id, name, due_date, IFNULL(achieved_date,'NULL') FROM milestones WHERE project_id={pid}"):
        due = d(due)
        if ach == "NULL" and today > due: hit("A4", f"{code} milestone overdue: {name}")
        elif ach == "NULL" and 0 <= (due - today).days <= s["milestone_due_days"]: hit("A3", f"{code} milestone due soon: {name}")
    for cid, cert, sub, certified, paid in q(f"""SELECT c.id, c.cert_no, c.submitted_date, IFNULL(c.amount_certified,0),
            IFNULL((SELECT SUM(amount) FROM payment_transactions t WHERE t.certificate_id=c.id),0)
            FROM payment_certificates c WHERE c.project_id={pid}"""):
        fully_paid = D(certified) > 0 and D(paid) >= D(certified)
        if not fully_paid and today > d(sub) + dt.timedelta(days=int(period)):
            hit("A9", f"{code} {cert} overdue, outstanding {D(certified) - D(paid):,.2f}")
    for name, bal, reorder in q(f"""SELECT r.name,
            IFNULL(SUM(CASE u.movement WHEN 'received' THEN u.quantity WHEN 'used' THEN -u.quantity ELSE 0 END),0), r.reorder_level
            FROM resources r LEFT JOIN resource_usage u ON u.resource_id=r.id
            WHERE r.project_id={pid} AND r.category_id=2 AND r.reorder_level IS NOT NULL GROUP BY r.id"""):
        if D(bal) < D(reorder): hit("A10", f"{code} {name} balance {bal} < {reorder}")
    last = q(f"""SELECT IFNULL(MAX(p.record_date),'NULL') FROM progress_records p JOIN activities a ON a.id=p.activity_id
                 WHERE a.project_id={pid} AND p.status='approved'""")[0][0]
    if last == "NULL" or (today - d(last)).days >= s["stale_data_days"]:
        hit("A11", f"{code} no approved progress since {last}")
    for (v,) in q(f"SELECT version_no FROM budgets WHERE project_id={pid} AND status='pending'"):
        hit("A12", f"{code} budget v{v} pending Director approval")
    for (n,) in q(f"""SELECT COUNT(*) FROM progress_records p JOIN activities a ON a.id=p.activity_id
                      WHERE a.project_id={pid} AND p.status='submitted'"""):
        if int(n): hit("A13", f"{code} {n} progress entries awaiting PM approval")
    for (name,) in q(f"SELECT name FROM resources WHERE project_id={pid} AND equipment_status='faulty'"):
        hit("A14", f"{code} equipment faulty: {name}")
    for (rid,) in q(f"""SELECT p.id FROM progress_records p JOIN activities a ON a.id=p.activity_id
                        JOIN delay_reasons r ON r.id=p.delay_reason_id WHERE a.project_id={pid} AND r.raises_critical=1"""):
        hit("A15", f"{code} stoppage/safety reported (progress record {rid})")

rules = ["A1", "A2", "A3", "A4", "A5", "A6", "A6b", "A7", "A8", "A9", "A10", "A11", "A12", "A13", "A14", "A15"]
print()
missing = [r for r in rules if r not in found]
for r in rules:
    print(f"{r:4} {'TRIGGERED' if r in found else 'NOT TRIGGERED'}  " + "; ".join(found.get(r, [])))
print("\nRESULT:", "all 16 rules triggered" if not missing else f"missing {missing}")
sys.exit(1 if missing else 0)
