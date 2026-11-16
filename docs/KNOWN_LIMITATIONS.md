# Known limitations, open items and future enhancements

State on 16/11/2026. Evidence for each item is in `docs/TEST_REPORT.md` and
`docs/TRACEABILITY_PACK.csv`.

## Limitations of this version

1. Single case. Built and evaluated for one company, Lubie Construction Works; results do not
   generalise to other contractors without further study.
2. Synthetic data only. All tests and demonstrations use invented projects, people and amounts.
   No result here describes real company performance.
3. Timeliness depends on data entry. Figures are only as current as the last approved progress
   entry and the last recorded expenditure. The "Data last updated" stamp and alert A11 show
   stale data but cannot prevent it.
4. SMS depends on a paid gateway and the mobile network. Only the sandbox was tested. Delivery
   to real phones is unproven until a provider account is configured and tested.
5. Offline capture covers progress, labour attendance, materials received and used, equipment hours
   and equipment status, tested in Chromium. iPhone Safari behaviour is untested.
6. Free hosting sleeps. The free web service wakes in about a minute after 15 idle minutes, and
   the free database has 1 GB of storage and no service-level agreement.
7. Not yet built (planned): progress photos (FR-39). Labour, materials, equipment, material requests
   and the reorder alert were completed on 16/11/2026 (task T8).
8. Accessibility checked by automated scan only; no screen-reader or 200 % zoom session yet.
9. Usability evidence pending: sessions T1 to T5 with the 15 participants are run by the owner.
10. MariaDB 10.4.32 is exercised by CI on GitHub; local runs used MariaDB 10.11 and MySQL 8.0.

## Open items

| ID | Item | Owner |
|---|---|---|
| O-1 | XAMPP compression (mod_deflate) off | Owner decision (httpd.conf) |
| O-2 | Browser date picker follows the device language | User setting |
| O-6 | Real SMS provider not configured | Owner, paid account |
| O-7 | Live deployment checks (health, logins, audit IP, phone install, offline, backup) | After DEPLOY.md |
| O-8 | CI PHPUnit step failing on MariaDB 10.4 since b08ce59; failing test names now published as annotations | Next push |
| O-9 | Repository public; switch to private before real data | Owner |

No known open code defects other than O-8, which is under investigation.

## Future enhancements

- Progress photos (FR-39), compressed in the browser and queued offline.
- Paid hosting tier to remove the cold start; scheduled daily backups once the repository is private.
- Real SMS provider with delivery reports; optional e-mail digest.
- Screen-reader and field usability study on site, with low-end Android phones.
- Read-only reporting account and export to a spreadsheet for analysis.
- Out of scope by design (master prompt): GPS, AI prediction, WhatsApp, EAC, multi-tenant use,
  full inventory or fleet systems.
