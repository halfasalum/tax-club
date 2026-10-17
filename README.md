# TRA Tax Club Digital Platform

Website, member portal, Administration Portal and mobile API for the Tanzania Revenue Authority Tax Club programme.

| Part | Where | Technology |
|---|---|---|
| Public website (SEO), member portal, Administration Portal, `/api/v1` | `backend/` | Laravel 13, Blade, Livewire 4, Tailwind CSS 4, Sanctum |
| iOS and Android apps | to be added (Flutter) | Calls `/api/v1` |
| Legacy React admin (frozen, being replaced) | `frontend/` | React 19, Mantine |

Delivery documents, the requirements traceability matrix and architecture decisions are in [`docs/`](docs/README.md).

## Run locally

Requirements: PHP 8.3+, Composer 2, Node 22+, and MySQL 8 (or SQLite for a quick start).

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Quick start with SQLite: set DB_CONNECTION=sqlite in .env, remove the other DB_* lines, then: touch database/database.sqlite
php artisan migrate --seed          # prints a generated super-admin password in local
npm install
npm run build                       # or: composer run dev  (server + queue + Vite)
php artisan serve
```

| URL | What |
|---|---|
| `/`, `/en` | Public website in Kiswahili and English |
| `/register`, `/login` | Member registration and sign-in |
| `/forgot-password` | Password reset with a 6-digit SMS code |
| `/app` | Member portal |
| `/app/card` | Digital membership card with QR code (print or save as PDF) |
| `/app/profile` | Profile: photo, contact details, SMS language, phone and password change, alumni request |
| `/app/notifications` | In-app notification inbox |
| `/app/learn` | Learning hub: modules, search, lessons with progress |
| `/app/quizzes` | Quizzes with timer, autosave and results |
| `/app/points` | Points, badges and leaderboards |
| `/verify`, `/verify/{token}` | Check a membership card by QR scan or number (public, rate-limited) |
| `/admin` | Administration Portal: registration queue, institutions and Tax Clubs |
| `/admin/institutions` | Register or import institutions, set up Tax Clubs, assign patrons |
| `/admin/members` | Search members, suspend / deactivate / reactivate, alumni requests and bulk move to alumni |
| `/admin/statistics` | Members by region, institution and category, with CSV download |
| `/admin/coordinators` | Assign TRA coordinators to regions |
| `/admin/notifications`, `/admin/sms` | Message wording and channels; SMS delivery with retry |
| `/admin/learning` | Write lessons, review, publish and version them; modules and categories |
| `/admin/quizzes` | Build quizzes, review, open for a period, results |
| `/admin/gamification` | Point values, daily cap, leaderboards on/off, badges |
| `/admin/audit` | Audit log of changes and sign-ins |
| `/sitemap.xml`, `/robots.txt` | SEO |
| `/api/v1/...` | Mobile API |

Outside `local`/`testing`, seeding requires `SUPERADMIN_PASSWORD` to be set.

## SMS (Beem) and background jobs

SMS messages (registration received, approved or rejected, patron assigned, password reset code, password changed) are sent through [Beem](https://beem.africa) and recorded in the `sms_messages` table with their status and Beem `request_id`.

```dotenv
SMS_DRIVER=beem            # "log" (default) writes messages to the log and sms_messages instead of sending
BEEM_API_KEY=...
BEEM_SECRET_KEY=...
BEEM_SENDER_ID=INFO        # use your approved sender ID from the Beem dashboard
QUEUE_CONNECTION=database
```

- SMS go through the queue, so a worker must be running: `php artisan queue:work`, or `composer run dev` locally. Failed sends are retried twice (after 1 and 5 minutes). A failed SMS never blocks the action that triggered it.
- SMS text uses the member's language (the language they registered in).
- Set `APP_URL` to the public address, because sign-in links in SMS are built from it.
- Run the scheduler every minute in production (`* * * * * php artisan schedule:run`). It moves members to the adult age band on their 18th birthday (`members:refresh-age-bands`, daily at 00:15).

## Registration rules

- Members must be 11–35 years old (date of birth is required). Gender is optional.
- Applicants under 18 must give a parent or guardian's name and phone and confirm that the guardian agrees. The patron must tick "I have confirmed that the parent or guardian agrees" before approving. Who confirmed it, and when, is recorded.
- Leaderboards are visible to signed-in members only and show first name plus last initial (e.g. "Asha M.").

## Membership numbers and cards

- A number is issued when a registration is approved, in the form `TC-YY-RRR-NNNNNN-C`: year, institution region code, running number for the year, and a check digit that catches mistyped numbers. Numbers are never reused. Members approved earlier keep their existing numbers.
- The QR code on the card opens `/verify/{token}`. The token is random, so a card cannot be forged from the number. Members can replace their QR code if the card is lost or shared, and old copies then stop verifying.
- Anyone can check a card. They see whether it is valid and the member's short name (e.g. "Asha M."). Officers with the **Verify Membership** permission also see the full name, number, category, institution and an "Under 18" badge. Every check is recorded in `membership_verifications`.
- API: `GET /api/v1/membership/card` and `POST /api/v1/membership/card/reissue` (signed in), and `POST /api/v1/membership/verify` with `token` or `membership_number` (public; full details with an officer's token).

## Learning, quizzes and points (Release 2)

- **Lessons** are written in Markdown, sent for review, and published now or on a date, with an optional end date. Every change is a new version that is reviewed again; members keep seeing the live version until the new one is published. Tax content is due for review 12 months after publication.
- **Reviews** need a second person: nobody can approve their own lesson or quiz (`FOUR_EYES_REVIEW=true`).
- **Quizzes** can be scored or practice, with a pass mark, time limit, number of attempts, which attempt counts (best or latest), and when correct answers are shown. The server times each attempt and closes it when time runs out, using the answers already saved. Questions are locked once members have taken a quiz; make a new version instead.
- **Points** (decision D-15 values, editable): quiz pass 10, each correct answer 1 (best attempt only), lesson 2, module 10, at most 50 automatic points per day. Officers can add or remove points with a reason. Badges are awarded automatically.
- Uploaded lesson files go to the `public` disk: run `php artisan storage:link` once.
- The scheduler also publishes scheduled lessons and closes overdue quiz attempts.
- After pulling, also run `php artisan db:seed --class=ContentCategorySeeder` and `php artisan db:seed --class=BadgeSeeder`.

## Speed

The app itself answers most pages in 15–40 ms with under 15 database queries. If it feels slow locally, it is almost always the local PHP setup:

1. **Turn on PHP's code cache for the command line.** `php artisan serve` uses the CLI, where opcache is usually off, so every request re-reads all of Laravel. In your `php.ini` (find it with `php --ini`):
   ```ini
   opcache.enable_cli=1
   opcache.validate_timestamps=1
   opcache.revalidate_freq=0
   ```
2. **Let the dev server answer requests in parallel.** Keep `PHP_CLI_SERVER_WORKERS=4` in `.env`. `composer run dev` starts `php artisan serve --no-reload` so the setting is used (restart it after changing `.env`).
3. **Windows:** prefer [Laravel Herd](https://herd.laravel.com) (nginx + PHP-FPM with opcache) over `php artisan serve`, and exclude the project folder from real-time antivirus scanning; scanning `vendor/` on every request is a common cause of 1–2 s page loads.
4. **Not editing CSS or JS?** Run `npm run build` once instead of `npm run dev`.
5. Keep `BOOST_BROWSER_LOGS_WATCHER=false` unless you need browser logs in Laravel Boost.

What the app does to stay fast:
- Moving between pages inside the portal swaps only the page content (Livewire `wire:navigate`); menu links start loading on hover.
- Fonts are served from this site, not a third-party font server.
- Each person's permissions are loaded once per request.
- Indexes cover member lists, unread notifications, quiz attempts and leaderboards.

**Production checklist:** `composer install --no-dev --optimize-autoloader`, `npm run build`, `php artisan optimize` (caches config, routes, views and events; run again after each deploy), PHP-FPM with opcache on, Redis for `CACHE_STORE`, `SESSION_DRIVER` and `QUEUE_CONNECTION` when available, and long browser caching for `/build`, `/fonts` and `/images` (already set in `public/.htaccess` for Apache).

## Roles and access

| Role | Sees | Can |
|---|---|---|
| **Super Admin** | Everything | Everything (re-run `SuperAdminRoleSeeder` after upgrades so new permissions are granted) |
| **Coordinator** (assigned at `/admin/coordinators`) | Institutions, registrations, members and statistics **in their regions** | Confirm registrations, suspend/reactivate members, approve alumni, check cards with full details |
| **Patron** (assigned on an institution's page) | Registrations and members **of their own institution** | Confirm registrations, approve alumni |
| **National officer** | Every region, if their role has **View All Registrations** (nationwide access) | Whatever their role allows |

Permissions are grouped by module in `database/seeders/ModuleSeeder.php` (Members, Notifications and Audit were added in Release 1).

**Sign-in security**
- Anyone with an administration permission confirms sign-in with a 6-digit SMS code (`STAFF_TWO_FACTOR=true`). **Give every staff account a phone number**: staff without one are let in, and the skip is written to the audit log.
- Mobile app tokens expire after 30 days (`SANCTUM_EXPIRATION`, minutes). Suspended or deactivated accounts lose access on their next request.
- Every change to members, institutions, Tax Clubs, patrons, coordinators and notification settings is kept in an append-only audit log, together with sign-ins and failed sign-ins.

**After pulling new code**

```bash
composer install
php artisan migrate
php artisan db:seed --class=ModuleSeeder          # new permissions
php artisan db:seed --class=SuperAdminRoleSeeder  # grants them to Super Admin
php artisan db:seed --class=PatronRoleSeeder      # Patron and Coordinator roles
```

## Tests

```bash
cd backend
php artisan test
```
