# 04 — API Specification (v1 target catalogue)

This is the contract outline. The authoritative, machine-readable spec will be generated as **OpenAPI 3.1** from the code (Release 0) and published at `/api/v1/docs` in non-production environments.

## 1. Conventions

| Topic | Rule |
|---|---|
| Base path | `/api/v1`. Breaking changes go to `/api/v2`; additive changes are allowed in v1 |
| Auth | Mobile: `Authorization: Bearer <sanctum token>` (access token TTL 60 min + refresh token 30 days, rotated on use, stored in the device keystore). Admin portal: Sanctum SPA cookie session + CSRF |
| Envelope | Keep the prototype envelope: `{ success, message, data, errors?, meta? }` |
| Pagination | `?page=&per_page=` (max 100); `meta: { current_page, per_page, total, last_page }`. Cursor pagination for feeds and inbox |
| Filtering / sorting | `?filter[status]=published&filter[region]=ARU&sort=-published_at&q=vat` |
| Localisation | `Accept-Language: sw | en`; templates and categories are returned in the requested locale |
| Idempotency | `Idempotency-Key` header required on submit-type POSTs (registration, quiz submit, competition entry, event RSVP, feedback) |
| Errors | 401 unauthenticated, 403 forbidden (permission/scope), 404, 409 state conflict (e.g. invalid workflow transition), 422 validation `{ errors: { field: [msg] } }`, 423 account locked, 429 rate limited. No stack traces or exception text in responses |
| Rate limits | Auth endpoints 5/min per IP+identifier; OTP send 3/15 min; public verify 30/min per IP; general 120/min per user |
| Timestamps | ISO-8601 UTC |
| IDs | UUIDs in paths; human reference numbers (`REG-…`, `Q-…`) searchable via `?q=` |
| Workflow actions | `POST /{resource}/{id}/actions/{action}` with `{ comment?, publish_at? }`. Actions: `submit, start-review, approve, return, reject, schedule, publish, unpublish, close, archive` |

## 2. Endpoint catalogue

Legend: **M** member app, **A** admin portal, **P** public (no auth). The SRS IDs give traceability.

### M1 Accounts
| Method & path | Who | SRS |
|---|---|---|
| `POST /auth/register` → 201 `{ reference_no, status: pending_verification }` (no token issued) | P | REG-REQ01 |
| `GET /auth/registration-status?reference_no=&phone=` | P | REG-REQ01 §8 |
| `POST /auth/login` `{ identifier (phone/email), password }` | P | REG-REQ04 |
| `POST /auth/otp/verify`, `POST /auth/token/refresh`, `POST /auth/logout`, `POST /auth/logout-all` | M | REG-REQ04 |
| `POST /auth/recover` → sends OTP/link; `POST /auth/recover/verify`; `POST /auth/recover/reset` | P | REG-REQ05 |
| `POST /auth/change-password` | M A | REG-REQ05 |
| `GET /admin/registrations?filter[status]=pending&filter[institution]=&filter[region]=&filter[category]=&filter[from]=` | A (scoped) | REG-REQ02 |
| `GET /admin/registrations/{id}` · `POST /admin/registrations/{id}/approve` · `POST …/reject {reason}` | A | REG-REQ02/03 |
| `POST /admin/users/{id}/suspend {reason, until?}` · `/deactivate {reason}` · `/reactivate {confirmation}` · `GET …/status-history` | A | REG-REQ06 |
| `POST /me/alumni-transition` · `GET /admin/alumni-transitions` · `POST /admin/alumni-transitions/{id}/approve|reject` · `POST /admin/alumni-transitions/bulk` (graduating class) | M A | REG-REQ07 |

### M2 Profile & membership
| Method & path | Who | SRS |
|---|---|---|
| `GET /me` · `PATCH /me` (editable fields only) · `POST /me/photo` | M | PRO-REQ01/02 |
| `PATCH /me/privacy` | M | PRO-REQ03 |
| `POST /me/profile-change-requests` (protected fields such as name, DOB, institution) | M | PRO-REQ02 §2 |
| `GET /me/membership-card` → card data + signed short-lived QR payload (JWT-like, `exp` 24 h, refreshed while online) | M | MEM-REQ02 |
| `POST /verify/membership { membership_no | qr_payload }` → `{ status: valid|suspended|inactive|not_found, display_name_masked, category, institution? }` | A (verifier) and P (limited) | MEM-REQ03 |
| `GET /admin/members?q=membership_no|name` · `GET /admin/members/{id}/membership-history` | A | MEM-REQ01/04 |

### M3 Institutions & clubs
| Method & path | Who | SRS |
|---|---|---|
| `GET /institutions?filter[category]=&filter[region]=&filter[district]=&q=` (public returns active institutions with minimal fields) | P A | INS-REQ01 §10, INS-REQ07 |
| `POST /admin/institutions` · `PATCH /admin/institutions/{id}` · `POST …/activate` · `POST …/deactivate {reason}` · `GET …/history` | A | INS-REQ01-03 |
| `POST /admin/institutions/{id}/clubs` · `PATCH /admin/clubs/{id}` · `POST /admin/clubs/{id}/activate|deactivate` | A | INS-REQ04 |
| `POST /admin/clubs/{id}/patrons {user_id|new_person, starts_at}` · `POST /admin/club-officers/{id}/end {ends_at, reason}` | A | INS-REQ05 |
| `POST /admin/coordinator-assignments {user_id, scope_type, scope_id, starts_at, ends_at?}` · `GET /admin/coordinator-assignments` | A | INS-REQ06 |
| `GET /admin/institutions/{id}/stats` · `GET /admin/clubs/{id}/stats` | A | INS-REQ08 |

### M4 Learning
| Method & path | Who | SRS |
|---|---|---|
| `GET /learn/categories` · `GET /learn/content?filter[category]=&filter[type]=&filter[level]=&q=` · `GET /learn/content/{id}` | M | LRN-REQ05/07 |
| `GET /learn/modules/{id}` · `PUT /learn/progress/{lessonId} {position, completed}` · `GET /me/learning` | M | LRN-REQ06 |
| `POST /admin/content` · `PATCH /admin/content/{id}` (draft only) · `POST /admin/content/{id}/versions` · `POST /admin/content/{id}/actions/{action}` | A | LRN-REQ01-03, 08 |
| `PUT /admin/content/{id}/audience` · `GET|POST|PATCH /admin/categories` · `/admin/tags` | A | LRN-REQ04 |
| `GET /admin/review-queue?type=content|quiz|opportunity|event|competition` | A | LRN-REQ02 and peers |
| `GET /admin/content/due-for-review` | A | LRN-REQ08 §9 |

### M5 Community
| Method & path | Who | SRS |
|---|---|---|
| `POST /questions` · `GET /me/questions` · `GET /questions/{id}` · `GET /knowledge-base?q=` | M | COM-REQ01, 04 |
| `GET /admin/questions` · `POST /admin/questions/{id}/assign {expert_id|team_id}` · `/mark-duplicate {of}` · `/return {comment}` | A | COM-REQ02 |
| `PUT /admin/questions/{id}/answer` (draft) · `POST …/answer/submit` · `POST …/answer/approve|return|reject` · `POST …/publish {visibility}` | A | COM-REQ03/04 |
| `GET /spaces` · `GET /spaces/{id}/posts` · `POST /spaces/{id}/posts` · `POST /posts/{id}/replies` · `PATCH|DELETE /posts/{id}` | M | COM-REQ05/06 |
| `POST /admin/spaces` · `PATCH /admin/spaces/{id}` · `POST /admin/spaces/{id}/moderators` · `/close|archive` | A | COM-REQ05, 09 |
| `POST /reports {subject_type, subject_id, reason, details}` (shared with SAF) | M | COM-REQ07, SAF-REQ01 |

### M6 Alumni & mentorship
| Method & path | Who | SRS |
|---|---|---|
| `GET /alumni/directory?q=&filter[institution]=&filter[field]=` · `GET /alumni/{id}` · `POST /alumni/{id}/connect` | M | ALM-REQ02 |
| `GET /alumni/sessions` · `POST /alumni/sessions/{id}/register` · admin CRUD + workflow | M A | ALM-REQ03 |
| `POST /mentor-applications` · `GET /admin/mentor-applications` · `POST …/{id}/approve|return|reject` | M A | MNT-REQ01 |
| `POST /mentorship-requests` · `GET /me/mentorship-requests` | M | MNT-REQ02 |
| `POST /admin/mentorships {request_id, mentor_id}` (runs the safeguarding checks) · `POST /mentorships/{id}/acknowledge` | A M | MNT-REQ03 |
| `POST /mentorships/{id}/milestones` · `/sessions` · `/progress` · `/pause|complete|close {reason}` | M A | MNT-REQ04/05 |
| `POST /mentorships/{id}/concerns` → creates a restricted safeguarding case | M | MNT-REQ06 |

### M7 Opportunities
| Method & path | Who | SRS |
|---|---|---|
| `GET /opportunities?filter[category]=&filter[eligible]=true&filter[closing_before]=&q=` · `GET /opportunities/{id}` (includes `eligibility: { eligible, reasons[] }`) | M | OPP-REQ04/05 |
| `POST|DELETE /opportunities/{id}/bookmark` · `GET /me/bookmarks` · `POST /opportunities/{id}/apply-click` → returns URL + "leaving platform" notice | M | OPP-REQ06 |
| `GET /careers/resources` (content category `career`) | M | OPP-REQ07 |
| Admin CRUD + `actions/{action}` + `PUT …/audience` · `GET /admin/opportunities/stats` | A | OPP-REQ01-03, 05, 08 |

### M8 Quiz & gamification
| Method & path | Who | SRS |
|---|---|---|
| `GET /quizzes` (available to me) · `GET /quizzes/{id}` (instructions, limits, attempts left) | M | GAM-REQ05 |
| `POST /quizzes/{id}/attempts` → server start time + expires_at · `PUT /attempts/{id}/answers` (autosave) · `POST /attempts/{id}/submit` · `GET /attempts/{id}/result` (answers/explanations per quiz rules) | M | GAM-REQ05-07 |
| `GET /me/points` (balance + ledger) · `GET /me/badges` · `GET /leaderboards` · `GET /leaderboards/{id}?period=2026-10` | M | GAM-REQ08-10 |
| Admin quiz CRUD, questions/answers, reorder, `actions/{action}`, audience · `GET /admin/quizzes/{id}/results` (aggregate) | A | GAM-REQ01-04, 06 |
| `GET|POST|PATCH /admin/point-rules` · `POST /admin/users/{id}/points-adjustments {points, reason}` · `/admin/badges` · `/admin/leaderboards` | A | GAM-REQ08-10 |

### M9 Competitions, rewards, certificates
| Method & path | Who | SRS |
|---|---|---|
| `GET /competitions` · `POST /competitions/{id}/register` · `DELETE …/register` · `POST /competitions/{id}/entries` (multipart) | M | CMP-REQ03/04 |
| Admin CRUD + workflow · `GET /admin/competitions/{id}/scores` · `POST …/scores/{entryId} {score, reason}` · `POST …/results/approve` · `POST …/results/publish` | A | CMP-REQ01, 02, 05 |
| `GET|POST /admin/rewards` · `POST /admin/reward-awards` · `PATCH /admin/reward-awards/{id} {status, evidence}` · `GET /me/rewards` | A M | RWD-REQ01/02 |
| `GET /me/certificates` · `GET /certificates/{id}/pdf` · `POST /admin/certificates/{id}/revoke|reissue {reason}` | M A | CER-REQ01 |
| `GET /verify/certificate/{certificate_no}?t={token}` → web page + JSON | P | CER-REQ02 |

### M10 Events
| Method & path | Who | SRS |
|---|---|---|
| `GET /events` · `GET /events/{id}` · `POST /events/{id}/registrations {answers}` → confirmed or waitlisted · `DELETE /events/{id}/registrations/me` · `POST /registrations/{id}/confirm-offer` · `GET /me/tickets` (QR) | M | EVT-REQ03-05 |
| `GET /events/{id}/updates` | M | EVT-REQ07 |
| Admin CRUD + workflow + audience · `POST /admin/events/{id}/postpone|cancel {reason}` · `GET …/participants` · `POST …/check-in {qr_payload|membership_no}` · `PATCH …/attendance/{regId} {status, reason}` · `POST …/updates` · `POST …/close {remarks}` · `GET …/summary` | A | EVT-REQ01-08 |

### M11 Notifications
| Method & path | Who | SRS |
|---|---|---|
| `GET /me/notifications?cursor=` · `GET /me/notifications/unread-count` · `POST /me/notifications/{id}/read` · `POST /me/notifications/read-all` | M | NOT-REQ06 |
| `GET|PUT /me/notification-preferences` · `POST /me/devices {platform, token}` | M | NOT-REQ05 |
| `GET|POST|PATCH /admin/notification-templates` · `POST …/{id}/preview` | A | NOT-REQ01 |
| `POST /admin/broadcasts/preview-audience` · `POST /admin/broadcasts` · `POST …/{id}/cancel` · `GET …/{id}/stats` | A | NOT-REQ03 |
| `GET /admin/notification-deliveries?filter[status]=failed` · `POST …/{id}/resend` | A | NOT-REQ07 |

### M12 Feedback & safety
| Method & path | Who | SRS |
|---|---|---|
| `POST /feedback` (multipart) · `GET /me/feedback?q=&filter[status]=` · `GET /me/feedback/{id}` (public responses only) | M | FBK-REQ01, 04 |
| `GET /admin/feedback` · `POST …/{id}/classify {category, priority}` · `/assign` · `/notes` · `/respond` · `/close {resolution}` | A | FBK-REQ02/03 |
| `POST /reports` · `GET /admin/moderation/cases?filter[status]=&filter[priority]=&filter[safeguarding]=1` · `POST …/{id}/assign|escalate|notes` | M A | SAF-REQ01/02 |
| `POST /admin/moderation/content/{type}/{id}/hide|remove|restore {reason, case_id}` | A | SAF-REQ03, COM-REQ08 |
| `POST /admin/users/{id}/restrictions {type, features, starts_at, ends_at, reason, case_id}` · `POST /admin/restrictions/{id}/lift` | A | SAF-REQ04 |
| `GET /admin/safety/reports?group_by=category|status|institution&from=&to=` | A | SAF-REQ06 |

### Proposed M13 Reporting
`GET /admin/reports/{membership|learning|quiz|events|opportunities|notifications|safety}` with `from, to, region, institution, category` filters, CSV/XLSX export (async job + download link), scoped by the caller's region and institution.

## 3. Implemented in Release 3 (`/api/v1`, Sanctum token)

The catalogue above is the target design. Release 3 ships these endpoints; officer actions such as review, publishing and broadcasts are done in the Administration Portal.

| Method & path | Who | SRS |
|---|---|---|
| `GET /events?type=&search=` (upcoming events for the member's audience) · `GET /events/mine` · `GET /events/{id or slug}` (with updates, materials and the meeting link once registered) | M | EVT-REQ03/04/07 |
| `POST /events/{id}/register {answer?}` (returns `registered` or `waitlisted` with position) · `DELETE /events/{id}/register` | M | EVT-REQ04/05 |
| `POST /events/{id}/check-in {card, walk_in?}` (`card` = scanned QR text or membership number; returns `checked_in`, `already`, `not_registered`, `not_valid` or `not_found`) | A (`check_in_attendees`) | EVT-REQ06 |
| `GET /events/{id}/attendees?status=` · `GET /events/{id}/summary` | A (`view_event_attendees`) | EVT-REQ08 |
| `POST /events` · `PUT /events/{id}` (drafts and changes; `submit=1` sends for approval) · `DELETE /events/{id}` (unpublished drafts only) | A | EVT-REQ01 |
| `GET /opportunities?search=&type=&closing_soon=1&saved=1` · `GET /opportunities/{id or slug}` · `GET /opportunities/career-resources` | M | OPP-REQ04/07 |
| `POST /opportunities/{id}/save` · `DELETE /opportunities/{id}/save` · `POST /opportunities/{id}/apply` (counts the click, returns where to apply) | M | OPP-REQ06/08 |
| `POST /opportunities` · `PUT /opportunities/{id}` · `DELETE /opportunities/{id}` (unpublished drafts only) | A | OPP-REQ01 |
| `GET /feedback` · `POST /feedback {category, subject, message, related_type?, related_id?}` · `GET /feedback/{reference}` · `POST /feedback/{reference}/messages {message}` | M | FBK-REQ01/04 |

## 4. Implemented in Releases 4 and 5 (`/api/v1`, Sanctum token)

| Method & path | Who | SRS |
|---|---|---|
| `GET /community/spaces` · `GET /community/spaces/{slug}/posts` · `POST /community/spaces/{slug}/posts {title?, body}` · `GET /community/posts/{id}` · `POST /community/posts/{id}/comments {body}` · `DELETE /community/posts/{id}` | M | COM-REQ05/06/09 |
| `POST /reports {type: post|question|alumni_profile|alumni_story|mentorship|user, id, category, details?}` | M | SAF-REQ01, COM-REQ07 |
| `GET /questions` · `POST /questions {subject, body, category_id?, allow_publish?}` · `GET /answers?search=&category_id=&language=` | M | COM-REQ01/04 |
| `GET /competitions` · `GET /competitions/{slug}` (with results once published) · `POST /competitions/{slug}/register` · `POST /competitions/{slug}/entry {text|url|file}` | M | CMP-REQ03-05 |
| `GET /achievements` (certificates and rewards) · `POST /certificates/verify {number|token}` (public) | M / public | CER-REQ01/02, RWD-REQ02 |
| `GET /alumni/directory?search=` (adults only) · `GET /mentorships` · `POST /mentorships/requests {interests[], goals, mode?, guardian_consent?}` · `POST /mentorships/{id}/sessions {held_on, mode, topic, summary?, duration_minutes?, milestone?}` | M | ALM-REQ02, MNT-REQ02/04 |

Moderation, reviewing answers, judging, results, rewards, certificates, alumni stories and mentor vetting are done in the Administration Portal. The old `/forum` endpoints were removed.

## 5. Added for the mobile app

All responses use the language in the `Accept-Language` header (`sw` or `en`, Kiswahili by default).

| Method & path | Who | Notes |
|---|---|---|
| `GET /app/config` | public | Minimum and latest app version, store links, website links, whether the leaderboard is on, and translated labels for the app's lists (user types, event and opportunity types, feedback and report categories, mentor areas and more) |
| `GET /notification-preferences` · `PUT /notification-preferences {category, sms, inbox}` | M | NOT-REQ05 |
| `GET /badges` | M | Every active badge, with `earned` and `earned_at` |
| `GET /dashboard` | M | Adds `full_name`, `display_name`, `total_points`, `badges_earned` and `unread_notifications` |
| `GET /community/spaces` · posts | M | Spaces add `posting_problem` (why the member cannot post, or null); posts add `is_mine` |
| `GET /achievements` | M | Adds `total_points` |
