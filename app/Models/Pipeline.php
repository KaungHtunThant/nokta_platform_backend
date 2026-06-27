<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configured pipeline for an entity type. Stages belong to a pipeline; records reference a
 * pipeline + stage via locked columns. Thin model (relations/casts only).
 */
class Pipeline extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['tenant_id', 'entity_type_id', 'key', 'label', 'position'];

    /** @var array<string, string> */
    protected $casts = [
        'label' => 'array',
        'position' => 'integer',
    ];

    /** @return BelongsTo<EntityType, $this> */
    public function entityType(): BelongsTo
    {
        return $this->belongsTo(EntityType::class);
    }

    /** @return HasMany<Stage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('position');
    }
}
