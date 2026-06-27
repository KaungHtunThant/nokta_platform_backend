<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldDefinition extends BaseModel
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'entity_type_id', 'key', 'type', 'label', 'help', 'validation',
        'ui', 'default_value', 'is_system', 'is_translatable', 'position',
        'storage_strategy', 'storage_column', 'is_filterable', 'is_sortable', 'is_reportable',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'label' => 'array',
        'help' => 'array',
        'validation' => 'array',
        'ui' => 'array',
        'default_value' => 'array',
        'is_system' => 'boolean',
        'is_translatable' => 'boolean',
        'is_filterable' => 'boolean',
        'is_sortable' => 'boolean',
        'is_reportable' => 'boolean',
        'position' => 'integer',
    ];

    /** @return BelongsTo<EntityType, $this> */
    public function entityType(): BelongsTo
    {
        return $this->belongsTo(EntityType::class);
    }

    /** @return HasMany<FieldOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(FieldOption::class)->orderBy('position');
    }

    public function isRequired(): bool
    {
        return (bool) ($this->validation['required'] ?? false);
    }
}
