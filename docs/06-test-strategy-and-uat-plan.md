# 06 — Test Strategy and UAT Plan

## 1. Objectives
- Prove every SRS requirement, including its **Exceptional** conditions, through traceable tests (`TC-<ReqID>-NN` in the RTM).
- Catch regressions automatically on every pull request.
- Give TRA business owners a structured UAT sign-off per release.
- Verify the NFRs (doc 05 §7) for performance, security, accessibility and safeguarding before go-live.

## 2. Test levels

| Level | Scope | Tooling | Owner | Gate |
|---|---|---|---|---|
| Static | Lint, types, formatting, secrets, dependency CVEs, SAST | Pint, PHPStan (level 6+), ESLint, `tsc`, gitleaks, `composer audit`, `npm audit`, Semgrep | Devs | Every PR |
| Unit | Pure domain logic: scoring, points rules, eligibility, numbering, state machines | PHPUnit / Pest; Vitest | Devs | Every PR |
| Feature (API) | Each endpoint: happy path, validation, permission + **scope** denial, workflow transition errors, exceptional conditions from the SRS | Laravel HTTP tests on MySQL 8 (CI service) | Devs | Every PR; ≥ 70 % coverage of `app/Domain` |
| Contract | API matches OpenAPI; generated TS client compiles | Spectral lint + schema assertion | Devs | Every PR |
| Component/UI | Livewire components (web); Flutter widgets (mobile) | `Livewire::test()` feature tests; `flutter test` widget tests | Devs | Every PR |
| End-to-end | Critical journeys on real builds | Playwright (public site, member portal, admin), **Maestro** or Flutter `integration_test` (Android/iOS) | QA automation | Nightly on staging; must pass for release |
| Exploratory & device | Low-end Android lab (5 devices, Android 8–14), iOS 15+, slow 3G profiles | Manual, charters | QA | Each sprint |
| Performance | NFR-02/03/15: quiz peak, broadcast fan-out, list endpoints | k6 | QA + DevOps | R2, R3, RH |
| Security | OWASP ASVS L2 checklist, DAST (ZAP) on staging, independent pen test (API, web, mobile) | ZAP, MobSF, external vendor | Security | Each release (DAST); RH (pen test) |
| Accessibility | WCAG 2.1 AA | axe (CI), manual screen reader (TalkBack, VoiceOver, NVDA) | QA | Each release |
| Localisation | Every screen in Kiswahili and English; text expansion; date/number formats | Pseudo-locale build + native-speaker review | QA + TRA | Each release |
| UAT | Business acceptance against the SRS | Scripts in TestRail/Xray (or the RTM) | TRA BA + SMEs | End of each release |

## 3. Critical end-to-end journeys (automated)

| # | Journey | SRS |
|---|---|---|
| E2E-01 | Student registers (minor, guardian consent) → status Pending → coordinator approves → activation SMS/push → login → digital card shows Active + QR | REG-01-04, MEM-01-02, NOT-02 |
| E2E-02 | Officer scans member QR → Valid; after suspension → Suspended; masked data only | MEM-03, REG-06 |
| E2E-03 | Forgot password via SMS OTP → reset → old OTP rejected → old tokens revoked | REG-05 |
| E2E-04 | Content: author drafts → reviewer returns with comment → resubmit → approve → scheduled publish → visible only to targeted region | LRN-01-04 |
| E2E-05 | Quiz: timed attempt, network drop, auto-submit at expiry, attempt limit reached, points awarded once, leaderboard updates, minor shown masked | GAM-05-10 |
| E2E-06 | Event at capacity → waitlist → cancellation → promotion offer → confirm → QR check-in → close-out summary | EVT-04-08 |
| E2E-07 | Report a post → moderator hides → user restricted → appeal/restore → audit trail complete | SAF-01-04, SAF-06 |
| E2E-08 | Urgent safeguarding report → routed to Safeguarding Officer → restricted visibility verified for non-authorised roles | SAF-05, MNT-06 |
| E2E-09 | Competition → entries → manual scoring with reason → results approval → certificate issued → public verification valid → revoked → verification shows Revoked | CMP-04-05, CER-01-02 |
| E2E-10 | Broadcast to "Form 3–4 students in Mwanza" → audience preview count → schedule → delivery stats → failed SMS retried to limit | NOT-03, NOT-07 |

## 4. Test data
- Factories and seeders produce a synthetic national dataset: 31 regions, 600 institutions, 50,000 members (≈ 70 % minors), for load and UI testing.
- **No production personal data in non-production environments.** Copies of production for defect reproduction must be anonymised by the scripted pipeline and approved by the DPO.
- UAT accounts for every role, in at least two regions, to exercise scoped access.

## 5. UAT approach

| Step | Detail |
|---|---|
| Preparation | BA converts SRS *Behavior* rows into step-by-step scripts and *Exceptional* rows into negative scripts. Scripts are reviewed by the SME named in each requirement |
| Environment | Staging with the release candidate, synthetic data, test SMS/email sinks plus a whitelist of UAT phones |
| Window | First 5 working days of the sprint after each release; defects triaged daily |
| Participants | SMEs per module (from the SRS "Subject Matter Expert" row), 2–3 patrons, 1–2 coordinators, student testers (with consent) for usability |
| Entry criteria | All release stories Done; no open Critical/High defects; E2E suite green; release notes published |
| Exit criteria | 100 % of High-priority scripts passed; ≥ 95 % of Medium passed with workarounds documented; no open Critical/High defects; sign-off form completed by the Product Owner |
| Defect severity | Critical (blocks journey / security / safeguarding, fix before release), High (major function wrong, fix before release), Medium (workaround exists, fix in next sprint), Low (cosmetic) |

## 6. Release quality gates

| Gate | Criteria |
|---|---|
| Merge to `main` | CI green (static, unit, feature, contract, component), 1 approving review (2 for security-sensitive code: auth, RBAC, safeguarding, payments/rewards) |
| Deploy to staging | All of the above + migrations reversible + OpenAPI diff reviewed |
| Release to production | E2E green, UAT signed, DAST: no High findings, performance budget met, Change Advisory approval, rollback plan tested |
| Go-live (RH) | Independent pen test: no open Critical/High; DR restore test passed; runbooks approved; support rota in place |
