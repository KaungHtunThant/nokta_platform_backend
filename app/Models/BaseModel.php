<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Thin abstract base for every Eloquent model in the platform.
 *
 * Convention (see TECHNICAL-STRUCTURE, "Models/ folder convention"):
 *  - Models stay THIN: relations, casts, scopes, traits only — NO business logic.
 *  - All concrete models extend this base.
 *  - Fixed-schema tables get strictness from typed casts here.
 *  - Dynamic entities (deal/contact/patient) share ONE Record model (entity types are
 *    data; custom fields are JSON/EAV, not columns). Strict per-domain data lives in
 *    DTOs (app/DTOs), NOT in Eloquent subclasses.
 */
abstract class BaseModel extends Model implements AuditableContract
{
    use Auditable;
    use BelongsToTenant;
}
