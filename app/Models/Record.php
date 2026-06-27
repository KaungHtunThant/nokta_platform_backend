<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The single Eloquent model for ALL dynamic entities (deal/contact/patient/...).
 * Entity types are data; custom field values live in `data` (JSON) — Phase 1.
 * Stays thin: strict per-domain shapes live in app/DTOs.
 */
class Record extends BaseModel
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'entity_type_id', 'pipeline_id', 'stage_id', 'owner_id',
        'assignee_id', 'contact_id', 'position', 'status', 'is_locked', 'data',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'data' => 'array',
        'position' => 'float',
        'is_locked' => 'boolean',
    ];

    /** @return BelongsTo<EntityType, $this> */
    public function entityType(): BelongsTo
    {
        return $this->belongsTo(EntityType::class);
    }
}
