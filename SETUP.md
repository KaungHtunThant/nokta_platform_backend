# Backend — Setup

> This repo ships the **conventions skeleton** (layered `app/` structure, config, CI, base classes,
> arch tests). The Laravel framework runtime files (`bootstrap/`, `public/index.php`, `artisan`,
> `config/*`) are generated once via the official installer, then this structure layers on top.

## Prerequisites
- PHP 8.3+, Composer 2
- MySQL 8 (or PostgreSQL 15+)
- Redis 7 (cache, queue, sessions, pre-caching)
- Meilisearch (search/reporting) — optional until Phase 4
- Node (only if using Vite for any backend assets; the SPA is a separate repo)

## One-time framework bootstrap
The skeleton intentionally omits framework runtime files so nothing is half-built. To initialize:

```bash
# from a temp dir, create a vanilla Laravel app, then copy its runtime files
# (bootstrap/, public/, artisan, config/) into this repo WITHOUT overwriting app/, tests/,
# routes/, database/, composer.json, or the config files shipped here.
composer create-project laravel/laravel _laravel_tmp "^11.0"
# copy bootstrap/ public/ artisan config/ from _laravel_tmp into this repo, then:
rm -rf _laravel_tmp
```
(Alternatively, initialize Laravel in place first, then copy this repo's `app/`, `tests/`,
`routes/api.php`, `database/`, and root config files over it.)

## Install & run
```bash
composer install
cp .env.example .env
php artisan key:generate
# configure DB/Redis/Meilisearch in .env
php artisan migrate
php artisan serve            # API
php artisan reverb:start     # WebSockets (tenant-prefixed channels)
php artisan queue:work       # jobs, imports/exports, projections, cache warming
```

## Quality gates (also run in CI)
```bash
composer lint      # Pint (declare_strict_types enforced)
composer analyse   # Larastan level 8
composer test      # Pest: Unit + Feature + Arch
composer check     # all of the above
```

## First work
Start with **Phase 0 — Tenancy Spine** (see `../crm_emr_platform_plan/roadmap/` and the
`Delivery-Roadmap-and-Phase-Plan.docx`). Deliver test-first.
