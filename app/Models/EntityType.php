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

    /** @return HasMany<Pipeline, $this> */
    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class)->orderBy('position');
    }

    /** The field key used as a record's human label (relation pickers, related-record lists). */
    public function titleField(): ?string
    {
        $field = $this->config['title_field'] ?? null;

        return is_string($field) && $field !== '' ? $field : null;
    }

    /** A short display label for one of this type's records, falling back to "#id". */
    public function titleFor(Record $record): string
    {
        $field = $this->titleField();
        $value = $field !== null ? ($record->data[$field] ?? null) : null;

        return is_scalar($value) && $value !== '' ? (string) $value : '#'.$record->id;
    }
}
