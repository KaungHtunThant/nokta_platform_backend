<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldOption extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['tenant_id', 'field_definition_id', 'key', 'label', 'color', 'position', 'is_active'];

    /** @var array<string, string> */
    protected $casts = [
        'label' => 'array',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<FieldDefinition, $this> */
    public function fieldDefinition(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class);
    }
}
