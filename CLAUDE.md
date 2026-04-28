# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

- **Backend:** Laravel 13 on PHP 8.3+ (Docker image uses PHP 8.4-fpm). MySQL in local/dev, SQLite `:memory:` for tests.
- **Frontend:** Inertia.js + Vue 3 + Quasar (with `pt-BR` lang pack), Tailwind 4, Vite 8. Entry: `resources/js/app.js`; pages resolved from `resources/js/src/pages/**/*.vue`.
- **Root Blade template:** `resources/views/app.blade.php` (configured in `app/Http/Middleware/HandleInertiaRequests.php` as `$rootView = 'app'`).
- **Routing:** Laravel 11/12-style bootstrap in `bootstrap/app.php`. `HandleInertiaRequests` + `AddLinkHeadersForPreloadedAssets` are appended to the `web` group. Health endpoint: `/up`.

## Common commands

```bash
# One-time setup (creates .env, keys, runs migrations, installs npm, builds)
composer setup

# All-in-one dev: php serve + queue listener + pail (logs) + vite, via concurrently
composer dev

# Run the PHPUnit test suite (clears config first, as CI does)
composer test
# Single test file / filter
php artisan test tests/Feature/ExampleTest.php
php artisan test --filter=SomeTestName

# Linting / static analysis (CI enforces these — see azure-pipelines.yml)
vendor/bin/pint --test          # format check (no --test = apply fixes)
vendor/bin/phpstan analyse --memory-limit=512M

# Coverage gate used in CI (must stay ≥ 90%)
php -d memory_limit=512M artisan test --coverage --min=90

# Frontend
npm run dev      # vite dev server
npm run build    # production build (required before `php artisan serve` serves assets)
```

Note: `phpstan` and `pest` are invoked in CI but are **not** declared in `composer.json`'s `require-dev`. If they're missing locally, install them before running those commands. PHPUnit is the bundled test runner; `artisan test` proxies to it.

## Architecture notes worth knowing up front

- **The app directory is nearly empty.** `app/Http/Controllers/` contains only the abstract `Controller.php` base, `app/Models/` only `User.php`, `routes/web.php` only renders the `Welcome` Inertia page. This is a fresh skeleton — expect to create controllers, routes, models, and Vue pages from scratch rather than slot into existing patterns.
- **Inertia is the seam between PHP and Vue.** Controllers/routes return `Inertia::render('PageName', [...props])`; `PageName` must match a `.vue` file under `resources/js/src/pages/`. Shared props go in `HandleInertiaRequests::share()`.
- **Vite `base` is env-driven.** `vite.config.js` reads `VITE_BASE_PATH` (often set to `${APP_URL}`) and derives the asset base path, appending `build/`. This matters for subpath deployments (e.g. `/homolog/app/`) — changing `APP_URL` requires a rebuild for asset URLs to stay correct.
- **Quasar is globally registered** with `Notify`, `Loading`, `Dialog` plugins and the `pt-BR` language pack. SASS variables come from `resources/css/quasar-variables.sass`. Component auto-import uses `combined` case.
- **Three env files drive Docker builds:** `env.dev`, `env.homo`, `env.prod`. The Dockerfile's `environment` build-arg selects which one is copied to `.env` inside the image; the same arg chooses which Vite build happens in the node stage. `.env.example` is the template; `.env` is local-only.

## Deploy pipeline (Azure DevOps → ECR → k8s)

`azure-pipelines.yml` runs on `main` / `homolog` / `development` branches and maps branch → env:

| Branch | env | profile | namespace |
|---|---|---|---|
| `main` | prod | prod | production |
| `homolog` | hml | homo | homolog |
| `development` (default) | dev | dev | development |

Stages: `QualityChecks` (Pint + PHPStan + Pest `--min=90`) and `Repository` (S3 bucket creation) run in parallel, then `Build` (Docker → ECR + Trivy scan), then `Deploy` (renders a k8s manifest via the external `DevOps_CI` repo and commits it to that repo's `main`, where a separate system applies it). **Quality gates block the build** — a Pint/PHPStan/coverage failure prevents any deploy.

The app image runs nginx + php-fpm under supervisord on port 80; `docker-compose.yml` exposes it locally on `55501`.

## Gotchas

- `composer.lock` pins `laravel/framework ^13.0` and the `allow-plugins` config permits `pestphp/pest-plugin` even though Pest isn't in `require-dev` — CI installs it on-the-fly in the PHP 8.3 container. If you add Pest locally, install into `require-dev`.
- `storage/app/uptime.dat` is reset to `1` on every container build (see Dockerfile); don't rely on its prior contents.
- There is no `git` repository here (`.git` absent). Don't attempt `git` operations unless the user initializes one first.
