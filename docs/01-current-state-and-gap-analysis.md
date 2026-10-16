# 01 — Current State Assessment and Gap Analysis

| Item | Value |
|---|---|
| Baseline | Prototype commit `7ad3d35` (15 Apr 2026): "login, registration, tax hub, quiz, leaderboard implemented" |
| Reference | TRA Tax Club Digital Platform — Functional Requirements Catalogue v1.0 (Working Draft, Sept 2026), 100 requirements across 12 modules |
| Companion | `Requirements-Traceability-Matrix.xlsx` (per-requirement status, gap, release, estimate) |

---

## 1. What the prototype contains

| Layer | Technology | What exists |
|---|---|---|
| Backend API | Laravel 12, PHP 8.2, Sanctum 4, MySQL, UUID keys | 13 controllers, 92 routes under `/api/v1`: auth (register/login/logout/me/change-password), users, roles, modules & controls (RBAC), institutions, content, quizzes (questions, answers, attempts, scoring, leaderboards), opportunities, forum (posts + replies), events (register/cancel/attendees), user progress, dashboard counts |
| Admin portal | React 19, Vite 8, Mantine 9, TipTap, Axios | Sign-in, forgot/reset password screens, dashboard, users, roles, modules/permissions, institutions, content (rich text), quizzes and question editor |
| Member web app | — | **Not present** |
| Mobile app (iOS/Android) | — | **Not present in the repository** (if a mobile prototype exists elsewhere it should be added to the repo) |
| Tests | PHPUnit (default example tests only) | No feature tests for any business logic |
| CI/CD | — | None. Deployment is FTP upload from VS Code (`.vscode/sftp.json`, `uploadOnSave: true` for backend) |

### What works well and should be kept
- **RBAC model** (`modules → module_controls → role_controls ← roles ← user_roles`) with a `permission:{slug}` middleware. It is a sound base; it needs *scope* (region / institution / club) added, not replacing.
- **Consistent API envelope** (`BaseController::success / error / paginated`).
- **UUID primary keys** throughout — good for mobile offline sync and non-guessable identifiers.
- **Quiz engine core**: questions, answers, attempts, per-question responses, best-attempt tracking and points accumulation.
- **Admin portal screens** (React/Mantine): useful as a functional reference while they are rebuilt in Livewire (ADR 001); the React code itself is retired.

---

## 2. Critical findings (fix before any further work)

| # | Severity | Finding | Evidence | Action |
|---|---|---|---|---|
| C1 | **Critical** | **Live FTP credentials committed to a public repository** for two servers (`FLUX LOAN FRONTEND`, `TAXCLUB BACKEND`) | `.vscode/sftp.json` | Change both FTP passwords **now**; delete the file; add `.vscode/` to `.gitignore`; purge from git history (`git filter-repo`) |
| C2 | **Critical** | **Zipped production backend including `.env` (APP_KEY, DB and mail settings) and full `vendor/`** committed (47 MB, 14,289 files) | `backend/engine.zip` | Rotate `APP_KEY` (invalidates encrypted data/sessions — plan it), DB and mail passwords; delete zip; purge history |
| C3 | High | Registration **auto-verifies** every account (`is_verified => true`) and issues a token immediately — bypasses REG-REQ02/03 verification entirely | `AuthController::register` | Replace with Pending Verification lifecycle (Release 1) |
| C4 | High | **Public, unauthenticated leaderboard exposes full names and membership numbers** of all members — many members are minors | `GET /api/v1/leaderboard` (no auth) | Move behind auth; apply minor-display rules (GAM-REQ10, PRO-REQ03, SAF-REQ05) |
| C5 | High | Default super-admin seeded with password `password` | `Superuserseeder.php` | Seed from env var / force reset on first login |
| C6 | High | No rate limiting on login/registration; tokens never expire (`sanctum.expiration = null`); no login audit | `config/sanctum.php`, routes | Throttle, token TTL, login audit (REG-REQ04) |
| C7 | Medium | Exception messages returned to clients (`'Failed to submit quiz: ' . $e->getMessage()`) | `QuizController` | Log server-side, return generic message |
| C8a | High | **All npm dependencies resolve from a third-party mirror** (`registry.npmmirror.com`): 344 entries in `frontend/package-lock.json`, 153 in `backend/package-lock.json`, none from the official registry | Lockfiles | Regenerate both lockfiles against `registry.npmjs.org` (or a TRA-approved internal proxy); enforce the registry in `.npmrc` and CI |
| C8 | Medium | `APP_DEBUG=true` in example env; FTP `uploadOnSave` pushes unreviewed code to the server | `.env.example`, `.vscode/sftp.json` | CI/CD pipeline (doc 08) |

## 3. Defects that block a clean build

| # | Defect | Impact |
|---|---|---|
| D1 | Migration `2026_04_06_090814_update_personal_access_tokens_table` uses MySQL-only `ALTER … MODIFY` and its `down()` drops/creates the same index twice | `migrate:fresh` fails on anything but MySQL; test suite cannot use SQLite. Fix: use `uuidMorphs('tokenable')` in the create migration and delete the patch migration |
| D2 | `sessions.user_id` is `foreignId` (bigint) while `users.user_id` is UUID | Web sessions cannot link to users |
| D3 | `routes/api_v1.php` contains `Route::get('', [DashboardController::class, ''])` | Calling `/api/v1` throws a 500 |
| D4 | Frontend `package-lock.json` is inconsistent (`@mantine/dates@9.0.1` needs `@mantine/core@9.0.1`, lock has 9.0.0) | `npm ci` fails; only `--legacy-peer-deps` installs |
| D5 | Frontend calls endpoints that do not exist: forgot/reset password, refresh token | Screens exist but cannot work |
| D6 | Sidebar uses permission slugs that are never seeded (`view_quizzes`, `view_opportunities`, `view_events`, …) | Menu items invisible even to super admin |
| D7 | Controllers check role slugs `admin` / `educator` that are not seeded (only `super_admin`, `student`) | Admin-only visibility rules silently misbehave |
| D8 | `src/router/index.tsx` and `App.tsx` define two different routers; only `App.tsx` is used | Dead code, confusion |
| D9 | Pass mark hard-coded to 50 %; quiz results endpoint returns correct answers unconditionally | Conflicts with GAM-REQ01/06 |

## 4. Coverage against the SRS

Status per requirement: **Implemented** (meets the requirement), **Partial** (some behaviour exists but key rules are missing), **Not started**.

| Module | Reqs | Implemented | Partial | Not started | Main gaps |
|---|---:|---:|---:|---:|---|
| 1 Registration & Account | 7 | 0 | 2 | 5 | Pending-verification workflow, verification queue, activation, account recovery, suspension, alumni transition, consent for minors |
| 2 Profile & Digital Membership | 7 | 0 | 2 | 5 | Self-service profile edit, privacy rules, membership number on activation, digital card with QR, verification endpoint, status history |
| 3 Institution & Tax Club | 8 | 0 | 3 | 5 | Institution status/history, Tax Club entity, patrons, coordinators with geographic scope, statistics |
| 4 Learning & Content | 8 | 0 | 5 | 3 | Review/approval workflow, scheduling/expiry/archive, categories & targeting, structured modules/lessons, versioning |
| 5 Q&A, Experts & Community | 9 | 0 | 2 | 7 | Ask-TRA Q&A pipeline, discussion spaces, reporting, moderation, collaboration groups |
| 6 Alumni & Mentorship | 9 | 0 | 0 | 9 | Entire module |
| 7 Opportunities | 8 | 0 | 3 | 5 | Approval, scheduling/auto-close, targeting/eligibility, bookmarks, career resources, analytics |
| 8 Quiz & Gamification | 10 | 0 | 8 | 2 | Approval workflow, scheduling, eligibility, attempt/time limits, configurable pass mark, points rules & ledger, badges, period leaderboards |
| 9 Competitions, Rewards, Certificates | 9 | 0 | 0 | 9 | Entire module |
| 10 Events & Campaigns | 8 | 0 | 4 | 4 | Approval, targeting, capacity/waitlist, QR check-in, materials, close-out summary |
| 11 Notifications | 7 | 0 | 0 | 7 | Entire module (templates, push/SMS/email, inbox, preferences, retries) |
| 12 Feedback, Safety & Moderation | 10 | 0 | 0 | 10 | Entire module |
| **Total** | **100** | **0** | **29** | **71** | |

**Interpretation:** the prototype proves the stack and the basic CRUD for five content types. No requirement is fully met yet, mainly because the cross-cutting behaviours the SRS asks for almost everywhere are missing: approval workflows, targeting, audit trails, notifications, status history and minor safeguarding. Building these **once, as shared platform services** (see doc 02 §4) is the single biggest lever on cost and schedule.

## 5. Gaps in the SRS itself (to raise with the TRA BA team)

The catalogue is thorough on functional behaviour. These items are referenced or implied but not specified:

1. **Analytics & Reporting module** — referenced by INS-REQ08, OPP-REQ08, EVT-REQ08, SAF-REQ06 ("central analytics and reporting module") but has no requirements of its own. Proposed: add module 13 (RPT) with programme dashboards and exports.
2. **Platform administration** — RBAC, master data (regions, districts, categories, tags), system configuration and audit-log viewer are assumed but not catalogued. Proposed: add module 14 (ADM).
3. **Non-functional requirements** — performance, availability, scalability, accessibility, localisation (English/Swahili appears only in NOT-REQ01), offline/low-bandwidth, browser/OS support, data retention. Draft NFRs are proposed in doc 05 §7 for TRA approval.
4. **Integrations** — SMS gateway, push providers, email relay, identity (e.g. NIDA or institution student ID for verification), TRA SSO for staff, TRA website. None are specified.
5. **"Approved" business rules** — 263 of the 833 sub-requirements depend on rules TRA has yet to set ("approved statuses", "approved criteria", "where applicable"). They are collected in `09-decisions-and-business-rules-register.md` for owners to resolve.
6. **Duplicate requirement** — COM-REQ07/08 and SAF-REQ01/03 describe the same reporting and moderation capability. Proposed: build it once (SAF engine) and trace both IDs to it.
