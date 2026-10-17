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
| `/verify`, `/verify/{token}` | Check a membership card by QR scan or number (public, rate-limited) |
| `/admin` | Administration Portal: registration queue, institutions and Tax Clubs |
| `/admin/institutions` | Register or import institutions, set up Tax Clubs, assign patrons |
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

## Roles and access

- **Super Admin** gets every permission automatically (re-run `php artisan db:seed --class=SuperAdminRoleSeeder` after upgrades so new permissions are granted).
- **Patron** is assigned from an institution's page. Patrons confirm registrations **for their own institution only**.
- **TRA officers** who confirm registrations for every institution need the `view_all_registrations` permission ("View All Registrations") on their role. Without it they are treated like patrons.
- After pulling new code, run `php artisan migrate` and `php artisan db:seed --class=ModuleSeeder` (adds new permissions such as Verify Membership) and `php artisan db:seed --class=PatronRoleSeeder`.

## Tests

```bash
cd backend
php artisan test
```
