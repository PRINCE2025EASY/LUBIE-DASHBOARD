# Stage 1 findings summary

Date: 20/10/2026. Prepared from the five input files in `SYSTEM BUILDING`. Originals were not modified; text was extracted from copies.

## What was read

| File | How it was read | Content confirmed |
|---|---|---|
| project work.docx | Converted to text with pandoc; all 1,124 lines read | Chapters 1 to 3 up to s3.17.5. Sections 3.18 to 3.22 absent (testing, evaluation, ethics referenced only). 37 CONFIRM placeholders. |
| Interview_Analysis_Lubie_Construction.docx | pandoc; read in full | 14 interviews: DIR-01, PM-01/02, QS-01/02, SE-01 to SE-04, CL-01 to CL-05. 15 tables, issue list in s7. |
| Document_Review_Analysis.docx | pandoc; read in full | 36 documents (DT-01 to DT-12 x P1, P2, P3), 28 B items, 9 quality criteria, 106 conflict resolutions. |
| Questionnaire CSV | Parsed with pandas; all 101 columns | 15 responses, 13 to 17/10/2026, all consented. Roles: Director 1, PM 3, SE 4, QS 2, Client 5. |
| SPSS output (.doc, RTF) | Copy converted to text with LibreOffice (80 KB text from 36 MB file) | Frequencies, descriptives, multiple-response sets, Cronbach's alpha, composites, Spearman, client vs staff. SPSS version not stated in the output. |

Verification: means, SDs, counts and alphas recomputed from the CSV match the SPSS output and the master prompt s3 (B1-B12 alpha .934, C1-C16 .951, D1-D15 .951, E1-E21 .943, E23-E30 .853, F1-F5 .596).

## Findings per source

### Questionnaire (n = 15)

- Weakest practice: B12 deviations identified early, M = 2.47, 2/15 rate 4-5. Strongest: B8 materials monitored, M = 4.13.
- Top challenge: C11 no central place, M = 3.67. Lowest: C14 airtime/data for site staff, M = 2.07.
- B17: 9/15 need more than a day for current status. B18: telephone for urgent problems 12/15.
- D16 device: smartphone 9, desktop/laptop 5, tablet 1.
- D17 concerns: connectivity 11, security 10, training 9, data cost 7, entry time 6, power 4, staff resistance 3.
- D18 most prominent: completion % 13, planned vs actual 10, expenditure 8, delayed activities 8. Milestones only 2.
- D22 hide from client: detailed expenditure 10, staff/labour 10, budget revisions 8, internal alerts 8, supplier prices 5; "None" 3.
- D23 security: role-limited 13, username/password 11, activity log 11, admin reset 11, auto-logout 10, daily backup 7.
- E22 refresh: immediately 8, daily 3, every 15-30 min 2, hourly 1, every few minutes 1.
- Section F: predictive 4.67, GPS 4.60, offline 4.40, mobile app 3.40, WhatsApp 2.80. F alpha .596 (low, 5 items).
- Rule-only Must items: D2, D3, D7, E1, E2, E11, E17, E18, E19, E20, E21, E27, E28, E29, plus F2, F4, F5 (out of scope except F2).

### Interviews (n = 14)

- Manual relay: paper diary, WhatsApp, Excel, email, phone; WhatsApp used by 14/14.
- Delays from 2 days (site to PM) to 2 weeks (site to QS, company to client), with concrete incidents (idle gangs, postponed pour, corrected valuations, late payment).
- 14/14 want planned vs actual and the last-updated time. Same-day currency is enough.
- Workflow rules: PM approves progress; Director approves budget revisions; two-level thresholds; QS proposes cost thresholds.
- Director's numeric SMS rule: overrun > 10%, delay > 14 days, stoppage or safety; QS-01 monthly SMS cap.
- Simplicity (11), mobile layout (12), short forms (9). Offline entry requested by SE-01 and SE-04.
- Targets: RPO 24 h, RTO a few hours.

### Document review (36 documents)

- Excel 29/36; paper 15; PDF 12; WhatsApp in 22 flows.
- E8 delays Weak in 36/36; E3 consistency Weak 67%; E9 cost status Weak 67%; E7 planned vs actual Weak 64%.
- 21/36 (58%) take 4 days or more to reach management; only 2 same day.
- 47 duplication links; feedback to preparers missing for 20/36 (56%).
- Expenditure has one source (DT-10); budget, valuations, labour, materials, equipment two each.
- P1 internal cost and labour records sometimes reach the client.

### Thesis (Chapters 1 to 3)

- Five objectives (s1.5) and five RQs (s1.6) copied verbatim into the traceability pack (Stage 7).
- Table 3.4 FR-01 to FR-27, Table 3.5 NFR-01 to NFR-10, Table 3.6 rule, Table 3.10 KPIs, Table 3.11 alerts A1-A11, Table 3.12 16 entities.
- Out of date relative to the data in several places (Issues Register I-01 to I-20).

## Conflicts beyond master prompt Section 4

Listed in full in `02_Issues_Register.md`. The most important new ones:

1. Offline: your master prompt says exclude it (s4.14, s15) and include it (s0 mission, s8.17). Q1.
2. Thesis Table 3.2 already shows Director 1 and total 15, so the "Director 5, sum 19" problem is fixed in the file, but s3.7 text still lists "two clients, two PMs, two SEs, two QSs and two administrative staff", and s3.6 still says eight interviewees. No administrative staff took part in either instrument.
3. Thesis s3.8.1 describes B17, B18, B19, C14, C15, D22, D23 differently from the administered CSV (for example D22 is "hide from client", not payment status; there is no B19).
4. Interview question numbers: thesis s3.8.2 puts security in Part F and Q28 to Q31 on payments and reports; the interview analysis cites Q26 for barriers and Q28 to Q30 for security. Table 3.4 cites "Int. Q26" for login.
5. Doc review used 28 B items and 9 quality criteria; thesis s3.8.3 says 25 fields and 6 criteria.
6. SMS to clients: interview Table 9 sends stoppage and milestone SMS to clients; D22 (8/15) says hide internal alerts. Baseline: no client SMS.
7. Thesis s3.12.1 says no inferential tests; the SPSS output includes Spearman correlations with p-values.
