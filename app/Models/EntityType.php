<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class EntityType extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['tenant_id', 'key', 'label', 'icon', 'supports_pipeline', 'config'];

    /** @var array<string, string> */
    protected $casts = [
        'label' => 'array',
        'config' => 'array',
        'supports_pipeline' => 'boolean',
    ];

    /** @return HasMany<FieldDefinition, $this> */
    public function fieldDefinitions(): HasMany
    {
        return $this->hasMany(FieldDefinition::class)->orderBy('position');
    }

    /** @return HasMany<Record, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }
}
