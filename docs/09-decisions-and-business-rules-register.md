# 09 — Decisions and Business Rules Register

The SRS defers many rules to "approved" or "where applicable" values. Each item below has a **proposed default** so development can start. The default is used until the owner records a decision. **"Needed by"** is the last sprint start at which a change can still be absorbed without rework.

| ID | Decision | Proposed default | Owner | Needed by | SRS refs | Status |
|---|---|---|---|---|---|---|
| D-01 | Hosting location for staging and production | TRA data centre (or e-GA-approved government cloud); containerised to stay portable | TRA ICT | S1 | NFR | Open |
| D-02 | Applicable government ICT standards and approval steps (e-GA, TRA security policy) | Follow e-GA standards; TRA ICT Security sign-off per release | TRA ICT | S0 | all | Open |
| D-03 | Membership number format and digital card design | `TC-YY-RRR-NNNNNN-C` (year joined, region code, sequence, check digit); card shows name, photo, number, category, institution, status, QR | Programme Office | S3 | MEM-REQ01/02 | Implemented as proposed (no photo yet); confirm with Programme Office |
| D-04 | Minor policy: age threshold, guardian consent required?, display rules | Under 18 = minor; guardian name + phone + consent checkbox at registration, patron confirms during verification; minors shown as first name + initial | Legal/DPO + Programme Office | S2 | REG-REQ01, PRO-REQ03, GAM-REQ10, SAF-REQ05 | Decided (implemented, ages 11–35; confirm with Legal/DPO) |
| D-05 | Treatment of accounts created in the prototype | Set to Pending Verification; notify to re-confirm institution; keep points | PO | S6 | REG-REQ02 | Open |
| D-06 | Unique attributes for duplicate detection | Phone number (unique) + fuzzy match on name + DOB + institution → manual review; no NIDA requirement for students | Programme Office | S2 | REG-REQ01 §5 | Open |
| D-07 | Login identifier and recovery channel | Phone number + password; SMS OTP for recovery; email optional | PO + ICT | S2 | REG-REQ04/05 | Decided (implemented: 6-digit SMS code, 10 min, 5 attempts) |
| D-08 | Staff authentication | TRA SSO (OIDC/SAML) if available, otherwise local accounts with mandatory MFA | TRA ICT | S3 | REG-REQ04 | Implemented: local staff accounts with SMS sign-in code (STAFF_TWO_FACTOR); TRA SSO can replace it later. Confirm with TRA ICT |
| D-09 | Who verifies registrations, and the coordinator scope structure | Patron verifies own club (first line); TRA Coordinator (region scope) approves; national officers see all | Programme Office | S3 | REG-REQ02, INS-REQ05/06 | Implemented as proposed: patron (own club), coordinator (assigned regions), nationwide officers (View All Registrations). Confirm with Programme Office |
| D-10 | Retention periods; content review cycle | As in doc 05 §5; tax content review due every 12 months or on Finance Act changes (1 July) | Legal/DPO + Content owner | S7 | LRN-REQ08, SAF-REQ06, NOT-REQ06 | Partly implemented: content review due 12 months after publication (Review due list); retention periods still open |
| D-11 | Mentor vetting and supervision for minors | Declaration + coordinator approval for all mentors; background check for mentors of minors; patron copied on all mentorship sessions with minors | Programme Office + Legal | S16 | MNT-REQ01/03/06, SAF-REQ05 | Open |
| D-12 | Alumni transition trigger | Member requests, patron/coordinator approves; bulk transition per graduating class each December | Programme Office | S5 | REG-REQ07 | Implemented: member requests, patron/coordinator approves; officers can also move a whole class at once. Confirm timing with Programme Office |
| D-13 | Public (unauthenticated) membership verification allowed? | Yes, rate-limited, returns status + masked name only; full check requires a verifier role | Programme Office + DPO | S5 | MEM-REQ03 | Implemented as proposed (20 checks/min per IP); confirm with DPO |
| D-14 | Q&A publication policy | Answer goes privately to the requester by default; reviewer may publish an anonymised version to the knowledge base | Taxpayer Education | S15 | COM-REQ04 | Open |
| D-15 | Points rules values | Quiz pass 10 + 1 per correct answer (best attempt only); lesson 2; module 10; event attended 5; daily cap 50 | Programme Office | S8 | GAM-REQ08 | Implemented with the proposed values and an editable rules screen; event attendance points come in Release 3. Confirm with Programme Office |
| D-16 | Rewards with financial/physical obligations (airtime, merchandise) | Need Finance approval before publish; delivery evidence required; airtime integration out of scope for v1 | Finance + Programme Office | S16 | RWD-REQ01/02 | Open |
| D-17 | Certificate templates, signatories, issuing authority | TRA Commissioner for Taxpayer Services signature image; bilingual; QR to public verify page | Programme Office | S16 | CER-REQ01 | Open |
| D-18 | Notification channels, SMS budget, default languages | In-app + push for all; SMS only for OTP, verification decisions and urgent notices; Kiswahili default | PO + Finance | S4 | NOT-REQ01/02/05 | Partly decided: SMS provider is Beem; SMS budget still open |
| D-19 | Analytics & Reporting module scope (not in SRS) | Add M13 with programme dashboards: membership, learning, quizzes, events, opportunities, safety; CSV/XLSX export | PO | S13 | INS-REQ08, OPP-REQ08, EVT-REQ08, SAF-REQ06 | Open |
| D-20 | Confirm member-app technology | Web: server-rendered Laravel (Blade + Livewire + Tailwind). Mobile: Flutter (ADR 001) | TRA ICT + Tech Lead | S0 | — | **Decided 16 Oct 2026** (product owner: no React, Laravel serves web + API) |
| D-21 | Repository visibility and ownership | Move to a TRA-owned private GitHub organisation; vendor access through teams | TRA ICT | S0 | — | Open |
| D-22 | Content moderation staffing model | 2 TRA moderators + patrons as space moderators for their club; Safeguarding Officer on call | Programme Office | S14 | COM-REQ08, SAF-REQ02 | Open |

## Change log
| Date | ID | Decision | By |
|---|---|---|---|
| | | | |
