# Nokta Platform — Backend (API)

Configurable, multi-tenant CRM + EMR — Laravel 11 REST API + Reverb (WebSockets) + Redis.
Built **MVC-first**, with **SOLID** principles and **test-driven** development (Pest).

See the planning set in [`../crm_emr_platform_plan/`](../crm_emr_platform_plan/) — especially
`Technical-Functional-Documentation.docx` and `Delivery-Roadmap-and-Phase-Plan.docx`.

## Conventions (enforced)
- **Layering:** Route → thin Controller → FormRequest → Action/Service → Repository (interface) →
  thin Eloquent Model → API Resource; typed DTOs (`spatie/laravel-data`) across layers.
- **Models stay thin** (`app/Models/BaseModel.php`); strict per-domain data lives in `app/DTOs`.
- **Extension points (OCP):** `app/FieldTypes`, `app/Storage` (column/json/eav), `app/Rules/Stage`.
- **Tenancy:** `app/Support/Tenancy` (row-level isolation, global scope, tenant resolution).
- **Permissions:** two guards — `op` (operations) + `ui` (visibility); enum catalog in
  `app/Enums/RolesAndPermissions`; roles are customizable, the catalog is permanent.
- **Caching:** Redis behind `CacheRepositoryInterface`; controllers/actions never touch the cache.
- Enforced by `tests/Arch/ArchTest.php`, Larastan (level 8), and Pint.

## Status
Skeleton only — no business features yet. Build order is in the roadmap (Phase 0 first).

## Getting started
See [`SETUP.md`](SETUP.md).
