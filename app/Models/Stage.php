<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stage (kanban column) within a pipeline, with ordering, win/lost flags, and transition rules.
 * Thin model: the rule ENGINE lives in App\Services\Stages + App\Rules\Stage, not here.
 */
class Stage extends BaseModel
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'pipeline_id', 'parent_id', 'key', 'label', 'color',
        'position', 'is_initial', 'is_won', 'is_lost', 'config',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'label' => 'array',
        'config' => 'array',
        'position' => 'integer',
        'is_initial' => 'boolean',
        'is_won' => 'boolean',
        'is_lost' => 'boolean',
    ];

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return HasMany<StageRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(StageRule::class);
    }
}
