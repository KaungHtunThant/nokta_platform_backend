<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A directed link between two records (both in `records`), keyed by `relation_key`. A single
 * record_id FK per side (no morph) — all entities share the records table. Used from Phase 6.
 */
class RecordLink extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['tenant_id', 'from_record_id', 'to_record_id', 'relation_key', 'position'];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
    ];

    /** @return BelongsTo<Record, $this> */
    public function fromRecord(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'from_record_id');
    }

    /** @return BelongsTo<Record, $this> */
    public function toRecord(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'to_record_id');
    }
}
