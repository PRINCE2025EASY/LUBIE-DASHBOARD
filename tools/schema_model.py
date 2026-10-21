"""Parse schema.sql into a model (tables, columns, PK, FKs, uniques, checks, comments). Shared by the
data dictionary and the ERD generator so both match schema.sql exactly."""
import re
def parse(path):
    s = open(path, encoding='utf-8').read()
    tables = {}
    for m in re.finditer(r'CREATE TABLE (\w+) \((.*?)\n\) ENGINE', s, re.S):
        name, body = m.group(1), m.group(2)
        pre = s[:m.start()]
        cols, pk, fks, uqs, checks = [], [], [], [], []
        for raw in body.split('\n'):
            line = raw.strip()
            if not line: continue
            comment = ''
            if '--' in line:
                line, comment = line.split('--', 1); line = line.strip(); comment = comment.strip()
            line = line.rstrip(',')
            if line.startswith('PRIMARY KEY'):
                pk = [c.strip() for c in re.search(r'\((.*)\)', line).group(1).split(',')]
            elif line.startswith('UNIQUE KEY'):
                mm = re.match(r'UNIQUE KEY (\w+) \((.*)\)', line); uqs.append((mm.group(1), [c.strip() for c in mm.group(2).split(',')]))
            elif line.startswith('KEY'):
                pass
            elif line.startswith('CONSTRAINT'):
                fk = re.match(r'CONSTRAINT (\w+)\s+FOREIGN KEY \((\w+)\)\s+REFERENCES (\w+) \((\w+)\)(.*)', line)
                if fk: fks.append(dict(name=fk.group(1), col=fk.group(2), ref=fk.group(3), refcol=fk.group(4), action=fk.group(5).strip()))
                else:
                    ck = re.match(r'CONSTRAINT (\w+)\s+CHECK \((.*)\)$', line)
                    if ck: checks.append((ck.group(1), ck.group(2)))
            else:
                mm = re.match(r'(\w+)\s+(.*)', line)
                if not mm: continue
                cname, rest = mm.group(1), mm.group(2)
                typ = re.match(r'([A-Z]+(?:\([^)]*\))?(?: UNSIGNED)?)', rest).group(1)
                if typ.startswith('ENUM'):
                    typ = re.match(r"(ENUM\([^)]*\))", rest).group(1)
                nullable = 'NOT NULL' not in rest
                default = re.search(r"DEFAULT ('[^']*'|\S+)", rest)
                gen = ' AS (' in rest
                cols.append(dict(name=cname, type=typ, null=nullable, default=default.group(1) if default else '',
                                 auto='AUTO_INCREMENT' in rest, generated=gen, comment=comment))
        tables[name] = dict(cols=cols, pk=pk, fks=fks, uqs=uqs, checks=checks)
    return tables
