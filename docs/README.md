# TRA Tax Club Digital Platform — Delivery Documentation

These documents take the April 2026 prototype to the full **Functional Requirements Catalogue v1.0** (100 requirements, 12 modules) across the **Mobile App (iOS & Android)**, **Member Web App** and **Administration Portal**.

| # | Document | Purpose | Primary audience |
|---|---|---|---|
| 01 | [Current State & Gap Analysis](01-current-state-and-gap-analysis.md) | What the prototype does, critical security findings, defects, coverage per module, gaps in the SRS itself | Sponsor, PO, Tech Lead |
| 02 | [Solution Architecture](02-solution-architecture.md) | Target architecture, technology stack, shared platform services, module structure, member-app design | TRA ICT, Tech Lead, devs |
| 03 | [Logical Data Model](03-data-model.md) | Entities, statuses, ERD, prototype → target data migration | Devs, BA |
| 04 | [API Specification](04-api-specification.md) | API conventions and endpoint catalogue traced to SRS IDs | Devs, QA |
| 05 | [Security, Privacy, Safeguarding & NFRs](05-security-privacy-safeguarding-nfr.md) | Immediate remediation, auth, RBAC, PDPA privacy-by-design, minor safeguarding, proposed NFRs | TRA ICT Security, DPO, Legal |
| 06 | [Test Strategy & UAT Plan](06-test-strategy-and-uat-plan.md) | Test levels, critical journeys, UAT entry/exit, quality gates | QA, BA, SMEs |
| 07 | [Execution Plan](07-execution-plan.md) | Releases, sprint plan, team, RACI, governance, risks, procurement, next steps | Sponsor, PO, Delivery Mgr |
| 08 | [DevOps & Deployment](08-devops-and-deployment.md) | CI/CD, environments, mobile release process, runbooks, go-live checklist | DevOps, TRA ICT |
| 09 | [Decisions & Business Rules Register](09-decisions-and-business-rules-register.md) | 22 open decisions with proposed defaults, owners and deadlines | PO, Programme Office |
| — | [Requirements-Traceability-Matrix.xlsx](Requirements-Traceability-Matrix.xlsx) | All 100 requirements: status, gap, release, sprint, size, decisions, test cases; release capacity plan; sprint calendar | Everyone |

## Summary
- **Prototype coverage:** 29 requirements partially implemented, 71 not started, none complete.
- **Urgent:** FTP passwords and a zipped production `.env` are committed to the public repository. Rotate the credentials and purge the history before any other work (doc 05 §2).
- **Plan:** 6 releases + hardening over 22 two-week sprints, **2 Nov 2026 → 24 Sep 2027**. Pilot from **1 Mar 2027**; public store launch **12 Apr 2027**.
- **Key design choice:** build the reusable engines once (workflow, audience targeting, cases, audit, scoped RBAC, notifications). The SRS repeats each of these patterns across 6–7 modules.
- **Stack:** keep Laravel 12 + React/Mantine admin; add one **React Native (Expo)** codebase for the iOS, Android and member web apps.

## Maintaining these documents
- The RTM is the single source of truth for scope and status. Update the *Delivery status* column at every sprint review.
- Record decisions in doc 09's change log. Update the affected acceptance criteria in the backlog the same week.
- Architecture changes are recorded as ADRs in `docs/adr/` (template to be added in S0).
