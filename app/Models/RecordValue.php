<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One typed projection row for a record's custom field (ARCHITECTURE §3). Rebuildable from
 * records.data; never the source of truth. Thin model — the projection ENGINE lives in
 * App\Services\Records (FieldProjector) and querying in App\Services\Records\RecordQueryBuilder.
 */
class RecordValue extends BaseModel
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'record_id', 'field_definition_id',
        'value_string', 'value_number', 'value_date', 'value_bool', 'value_option_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value_number' => 'float',
        'value_date' => 'datetime',
        'value_bool' => 'boolean',
        'value_option_id' => 'integer',
    ];

    /** @return BelongsTo<Record, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /** @return BelongsTo<FieldDefinition, $this> */
    public function fieldDefinition(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class);
    }
}
