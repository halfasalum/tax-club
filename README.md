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
| `/app` | Member portal |
| `/admin` | Administration Portal: registration queue, institutions and Tax Clubs |
| `/admin/institutions` | Register or import institutions, set up Tax Clubs, assign patrons |
| `/sitemap.xml`, `/robots.txt` | SEO |
| `/api/v1/...` | Mobile API |

Outside `local`/`testing`, seeding requires `SUPERADMIN_PASSWORD` to be set.

## Roles and access

- **Super Admin** gets every permission automatically (re-run `php artisan db:seed --class=SuperAdminRoleSeeder` after upgrades so new permissions are granted).
- **Patron** is assigned from an institution's page. Patrons confirm registrations **for their own institution only**.
- **TRA officers** who confirm registrations for every institution need the `view_all_registrations` permission ("View All Registrations") on their role. Without it they are treated like patrons.
- After pulling new code, run `php artisan migrate` and `php artisan db:seed --class=ModuleSeeder` (adds new permissions) and `php artisan db:seed --class=PatronRoleSeeder`.

## Tests

```bash
cd backend
php artisan test
```
