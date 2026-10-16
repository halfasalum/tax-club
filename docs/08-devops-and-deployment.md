# 08 — DevOps, Environments and Deployment

## 1. Source control
- **Private** GitHub repository (after the history purge, doc 05 §2), branch protection on `main`: PRs only, CI required, signed commits recommended.
- Trunk-based: short-lived branches `feat/<REQ-ID>-short-name`, `fix/…`; commit messages reference SRS IDs (`REG-REQ02: add verification queue`).
- Releases tagged `vR1.0.0`; mobile builds carry `version` + `buildNumber` from the tag.
- `CODEOWNERS`: tech lead on `app/Domain/Shared`, security reviewer on auth/RBAC/safeguarding paths.

## 2. CI/CD pipeline (GitHub Actions)

```mermaid
flowchart LR
  PR[Pull request] --> S[Static: lint, types,<br/>gitleaks, SAST, audit]
  S --> T[Unit + feature tests<br/>MySQL + Redis services]
  T --> B[Build: API image,<br/>admin bundle, Expo web]
  B --> M{merge to main}
  M --> DEV[Deploy dev]
  DEV --> STG[Deploy staging<br/>on release tag]
  STG --> E2E[Playwright + Maestro<br/>ZAP baseline]
  E2E --> APP{Change Advisory<br/>approval}
  APP --> PROD[Deploy production<br/>blue/green]
  B --> EAS[EAS Build: Android AAB,<br/>iOS IPA]
  EAS --> STORE[TestFlight / Play internal →<br/>staged rollout]
```

| Artefact | Build | Deploy |
|---|---|---|
| API | Docker image (php-fpm 8.3 + nginx), `composer install --no-dev`, `config:cache`, `route:cache` | Blue/green or rolling on container host; `php artisan migrate --force` as a pre-deploy job; queue workers and scheduler as separate containers |
| Admin portal | `vite build` → static bundle | Served by nginx/CDN behind the same domain (`admin.<domain>`) |
| Member web | `expo export --platform web` | Static hosting/CDN (`app.<domain>`) |
| Mobile | EAS Build (managed credentials) | TestFlight / Play internal → closed testing (pilot) → staged production rollout 10 % → 50 % → 100 % |
| OTA fixes | EAS Update for JS-only fixes, channel per release | Only for non-native changes; store review policies respected |

**FTP deployment and `uploadOnSave` are retired.** Production changes happen only through the pipeline.

## 3. Environments and infrastructure

| Component | Dev | Staging/UAT | Production |
|---|---|---|---|
| API containers | 1 | 2 | ≥ 3 (autoscale / N+1) |
| Queue workers | 1 | 1 | 2–4 (Horizon-supervised) |
| Scheduler | 1 | 1 | 1 (leader lock via Redis) |
| MySQL 8 | single | single | primary + replica, automated backups, PITR |
| Redis 7 | single | single | HA (sentinel or managed) |
| Object storage | MinIO | MinIO / bucket | Encrypted bucket + CDN, versioning |
| Secrets | `.env` (local only) | Secret manager / CI secrets | Secret manager (Vault or cloud KMS); **never** in repo or images |
| Monitoring | — | Sentry, logs | Sentry, centralised logs, uptime, Pulse, DB slow query log, alert routing |

Hosting location (TRA data centre vs government/cloud) is decision D-01. The containerised design runs on any of them.

## 4. Operational runbooks (delivered by RH)
Deploy and rollback, DB migration failure, queue backlog, SMS provider outage (fail over to push + email), push credential rotation, certificate/key rotation, safeguarding emergency (take down content, freeze accounts, notify officers), data-subject request handling, backup restore, incident communication template.

## 5. Go-live checklist (excerpt)
- [ ] Pen test: no open Critical/High; ZAP clean on production config
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS only, HSTS, CSP on admin
- [ ] Rotated production secrets; admin accounts with MFA; seed accounts removed
- [ ] Backups verified with a restore; RPO/RTO test recorded
- [ ] Monitoring alerts tested end-to-end; on-call rota published
- [ ] Data migration rehearsal ×2 reconciled (counts per table, membership numbers, points balances)
- [ ] Store listings (en/sw), privacy policy URL, data-safety forms, age rating
- [ ] Support desk scripts, FAQ, patron/coordinator training completed
- [ ] Safeguarding Officer and moderators rostered; SOP signed
- [ ] Rollback plan approved by Change Advisory
