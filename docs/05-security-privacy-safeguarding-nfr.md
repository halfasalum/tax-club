# 05 — Security, Privacy, Safeguarding and Non-Functional Requirements

> Legal points in this document are engineering assumptions to confirm with TRA Legal and the Data Protection Officer. They are not legal advice.

## 1. Regulatory and standards baseline (to confirm)

| Area | Baseline assumed |
|---|---|
| Personal data | Tanzania **Personal Data Protection Act, 2022** and its regulations: lawful basis, purpose limitation, data minimisation, data-subject rights, breach notification, registration of TRA as data controller/processor with the Personal Data Protection Commission, cross-border transfer restrictions |
| Children | Under-18 members are treated as **children**. Parental/guardian consent is captured where the law or TRA policy requires it (decision D-04) |
| Government ICT | e-Government Authority (e-GA) standards and guidelines and TRA ICT security policy (hosting, interoperability, security testing) |
| App security | **OWASP ASVS 4 Level 2** (API and web), **OWASP MASVS L1 + resilience basics** (mobile), OWASP Top 10 |
| Accessibility | **WCAG 2.1 AA** for web and admin, platform accessibility APIs on mobile |

## 2. Immediate remediation (Release 0, week 1)

1. **Rotate credentials** exposed in the public repository: both FTP accounts in `.vscode/sftp.json`, and everything in `backend/engine.zip/.env` (`APP_KEY`, DB, mail).
2. Delete `.vscode/sftp.json`, `backend/engine.zip` and `backend/public/public.zip`. Add `.vscode/` and `*.zip` to `.gitignore`. Rewrite git history with `git filter-repo` and force-push. Ask GitHub Support to purge cached views. Consider making the repository **private**.
3. Turn on GitHub secret scanning and push protection. Add a `gitleaks` pre-commit hook and a CI step.
4. Regenerate both `package-lock.json` files against the official npm registry (they currently resolve every package from `registry.npmmirror.com`). Pin the registry in `.npmrc` and fail CI on non-approved `resolved` hosts.
5. Stop FTP deployment; move to the CI/CD pipeline (doc 08).
6. Close the public `/leaderboard` endpoint; remove the auto-verify on registration; replace the seeded `password`.

## 3. Identity, authentication and session

| Control | Requirement |
|---|---|
| Identifiers | Login by **phone number** (primary; most students have no email) or email. Phone is normalised to E.164 (`+255…`) |
| Passwords | Minimum 8 characters, checked against a breached/common-password list, bcrypt/argon2id, no forced periodic rotation |
| OTP | 6 digits, hashed at rest, 10-minute expiry, 5 attempts, single use, previous codes invalidated (REG-REQ05) |
| Lockout | 5 failed logins → 15-minute lock, then exponential. Every attempt goes to `login_events` (REG-REQ04 §7) |
| Staff accounts | MFA mandatory (TOTP or SMS OTP) for every admin-portal role. TRA SSO (SAML/OIDC) if available (decision D-08) |
| Tokens | Access 60 min, refresh 30 days rotating, revoked on password change, suspension or deactivation. "Log out all devices" option |
| Admin session | Cookie session, 30-minute idle timeout, 8-hour absolute, `SameSite=Lax`, `Secure`, `HttpOnly` |
| Status enforcement | `EnsureAccountUsable` middleware on every authenticated route checks `AccessStateResolver`. Suspension takes effect on the next request (REG-REQ06 §6) |

## 4. Authorisation

- Deny-by-default. Every route carries `permission:` middleware and every model access goes through a **Policy** that checks **scope** (doc 02 §4.5).
- Field-level filtering through API Resources: an officer sees only the fields their role needs (PRO-REQ03 §7).
- No privileged action by the item's own author: segregation of duties for approve, publish, results approval and reward issuance.
- Mass-assignment protection: FormRequests with explicit `validated()` fields. The prototype's `$request->only()` pattern is replaced.

## 5. Privacy by design

| Principle | Implementation |
|---|---|
| Minimisation | Collect DOB (to derive age band) but display only the age band. Gender optional. No national ID unless decision D-06 requires it |
| Purpose limitation | Membership data is used only for Tax Club services; analytics use aggregates |
| Visibility | Profile visibility matrix by viewer role × subject age band (below). Search never returns hidden fields (PRO-REQ03 §6) |
| Verification responses | Masked name (`Asha M.`), status, category, institution only. No phone, DOB or photo (MEM-REQ03 §4-5, CER-REQ02 §3-4) |
| Data-subject rights | Export my data (JSON/PDF), correction requests (PRO-REQ02 §2), deletion/anonymisation workflow subject to retention |
| Retention (draft) | Login events 12 months; notifications 12 months; closed feedback 3 years; moderation and safeguarding records 7 years (restricted); rejected registrations 6 months; anonymise inactive accounts after 5 years. TRA to approve (D-10) |
| Encryption | TLS 1.2+ everywhere, HSTS; DB and backups encrypted at rest; sensitive columns (DOB, guardian phone) encrypted with Laravel `encrypted` casts |
| Logs | No passwords, OTPs, tokens or full phone numbers in logs; Sentry PII scrubbing |

### Profile visibility matrix (default, configurable)

| Field | Self | Other member (adult) | Other member (minor) | Patron (own club) | Coordinator (scope) | Public verify |
|---|---|---|---|---|---|---|
| Full name | ✔ | First name + initial | First name + initial | ✔ | ✔ | Masked |
| Photo | ✔ | Opt-in (adults only) | ✗ | ✔ | ✔ | ✗ |
| Institution | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Phone / email | ✔ | ✗ | ✗ | ✔ | ✔ | ✗ |
| DOB | ✔ | ✗ | ✗ | Age band | Age band | ✗ |
| Points / badges | ✔ | ✔ | ✔ | ✔ | ✔ | ✗ |

## 6. Safeguarding of minors (SAF-REQ05, MNT-REQ06, COM-REQ05/06, GAM-REQ10)

| Rule | Enforcement |
|---|---|
| Age band assigned at registration from DOB and re-evaluated nightly (a member turning 18 changes band automatically) | Scheduler job |
| **No private adult→minor messaging.** The platform has no 1:1 DM for minors; mentorship with a minor uses supervised channels only (a patron or coordinator is copied on logged sessions) | Policy + feature absence |
| Minors' posts in national spaces are visible only to members; never public | Audience rules |
| Leaderboards show minors as first name + initial + institution | Resource transformer |
| Alumni directory excludes minors entirely | Query scope |
| Mentors need a safeguarding declaration, approval and optional background check (D-11) before matching with a minor | MNT-REQ01/03 validation |
| Urgent safeguarding reports (category flags) are routed to the Safeguarding Officer queue, with SMS/email alerts within 5 minutes and a 24-hour SLA | Case engine |
| Safeguarding cases are restricted to the `view_safeguarding_cases` permission; every read is audited | Policy + audit |
| A configurable keyword filter on posts holds suspected contact sharing (phone numbers, social handles) for review when the author or the audience includes minors | Content rules |

## 7. Proposed non-functional requirements (for TRA approval)

| ID | Category | Requirement |
|---|---|---|
| NFR-01 | Availability | 99.5 % monthly for member-facing API (excluding announced maintenance windows, Sundays 01:00–04:00 EAT) |
| NFR-02 | Performance | p95 API latency ≤ 500 ms for read endpoints, ≤ 1 s for writes, at 500 concurrent users. Quiz submit ≤ 2 s at 2,000 concurrent submissions (national competition peak) |
| NFR-03 | Scalability | Designed for 250,000 registered members and 25,000 daily active users without re-architecture |
| NFR-04 | Mobile footprint | Android APK ≤ 30 MB; runs on Android 8.0+ with 2 GB RAM; iOS 15+ |
| NFR-05 | Low bandwidth | Home screen ≤ 150 KB transfer after first load; images served as WebP with responsive sizes; video low rendition ≤ 400 kbps |
| NFR-06 | Offline | Membership card, downloaded lessons and the notification inbox readable offline |
| NFR-07 | Localisation | All UI, templates, categories and certificates in **Kiswahili and English**; user-selectable; Kiswahili default |
| NFR-08 | Accessibility | WCAG 2.1 AA (web/admin); dynamic type and screen-reader labels (mobile) |
| NFR-09 | Browser support | Last 2 versions of Chrome, Edge, Firefox, Safari; Chrome on Android 8+ |
| NFR-10 | Security testing | SAST and dependency scanning on every PR; DAST on staging per release; independent penetration test before go-live and annually |
| NFR-11 | Backup / DR | Encrypted daily full + 15-min binlog backups; RPO ≤ 15 min, RTO ≤ 4 h; quarterly restore test |
| NFR-12 | Auditability | All state-changing admin actions audited; audit records immutable and retained ≥ 7 years |
| NFR-13 | Observability | Centralised logs, error tracking, uptime alerts to the on-call channel within 5 min |
| NFR-14 | Maintainability | ≥ 70 % backend line coverage on domain/services; OpenAPI spec published per release; ADRs for significant decisions |
| NFR-15 | Notifications | Transactional notifications dispatched ≤ 60 s after the triggering event (p95); broadcasts of 100k recipients completed ≤ 30 min |
| NFR-16 | SEO | Public pages server-rendered with canonical, `hreflang` (sw/en), Open Graph and JSON-LD; Lighthouse SEO ≥ 95 and LCP ≤ 2.5 s on a mid-range Android over 4G; sitemap updated on publish; non-production environments blocked from indexing |
