<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * The single Eloquent model for ALL dynamic entities (deal/contact/patient/...).
 * Entity types are data; custom field values live in `data` (JSON) — Phase 1.
 * Stays thin: strict per-domain shapes live in app/DTOs.
 *
 * Searchable (Phase 4): each record is indexed (per-tenant index) over its entity type's REPORTABLE
 * fields for global search/reporting. The Scout observer keeps the index in sync on save/delete; the
 * index is rebuildable from JSON (RebuildProjection), like the EAV projection.
 */
class Record extends BaseModel implements HasMedia
{
    use InteractsWithMedia;
    use Searchable;
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

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return HasMany<RecordValue, $this> EAV projection rows (Phase 4) — rebuildable from `data`. */
    public function values(): HasMany
    {
        return $this->hasMany(RecordValue::class);
    }

    /** Per-tenant search index (keeps tenants isolated at the index level too). */
    public function searchableAs(): string
    {
        return 'records_tenant_'.$this->tenant_id;
    }

    /**
     * Index only the entity type's REPORTABLE custom fields (plus the locked attributes needed to
     * scope/return results). The long tail of display-only fields stays out of the search index.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $reportableKeys = FieldDefinition::query()
            ->where('entity_type_id', $this->entity_type_id)
            ->where('is_reportable', true)
            ->pluck('key')
            ->all();

        $reportable = array_intersect_key($this->data ?? [], array_flip($reportableKeys));

        return array_merge([
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'entity_type_id' => $this->entity_type_id,
            'status' => $this->status,
        ], $reportable);
    }
}
