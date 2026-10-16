# ADR 001 — One server-rendered Laravel app for web; Flutter for mobile

| | |
|---|---|
| Status | Accepted (16 Oct 2026) |
| Supersedes | The React-based front ends in doc 02 v1 (Expo member app, React/Mantine admin portal) |

## Context
- The public website must rank in search engines (lessons, events, programme information) in **Kiswahili and English**.
- The product owner asked for the latest Laravel and Tailwind CSS, **no React**, with the `backend/` application serving both the web front end and the API.
- iOS and Android apps are still required (SRS §1.0).

## Decision
1. **`backend/` is the only web application.** Laravel 13 renders the public website, the member web portal and the Administration Portal with **Blade + Livewire 4 (Alpine.js) + Tailwind CSS 4**. It also serves the JSON API (`/api/v1`, Sanctum tokens) for the mobile apps.
2. **SEO is server-side:** every public page is complete HTML on first response, with canonical URLs, `hreflang` alternates (Kiswahili at `/`, English at `/en`), Open Graph tags, JSON-LD (Organization, WebSite, Article, Event, BreadcrumbList), `sitemap.xml` and an environment-aware `robots.txt`.
3. **Mobile apps are built in Flutter** against the same `/api/v1`. React Native no longer offers code sharing with the web, and Flutter performs well on low-end Android.
4. The React admin in `frontend/` is **frozen**. It is deleted once the Livewire admin reaches feature parity (users, roles, modules, institutions, content, quizzes) in Release 1.

## Consequences
- One codebase, one deployment and one auth system for the web; no SPA build or client routing.
- Interactive admin screens use Livewire round-trips. That is fine for forms and queues; very rich editors (e.g. quiz builder drag-and-drop) use Alpine or a small isolated JS widget.
- Hosting needs **PHP 8.3+** (Laravel 13). The prototype targeted PHP 8.2.
- The mobile team works in Dart; the API contract (OpenAPI) is the integration boundary.
- Registration now follows REG-REQ01–03: accounts stay *Pending Verification* until approved, and the API no longer issues a token at registration or allows self-verification. **Mobile builds of the prototype must be updated** to show the registration reference instead of signing in.
