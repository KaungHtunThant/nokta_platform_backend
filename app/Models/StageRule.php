<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single transition rule on a stage: a `rule` key + a JSON `value` argument. Interpreted by the
 * matching App\Rules\Stage evaluator (generalizes the old StageHasRule).
 */
class StageRule extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['tenant_id', 'stage_id', 'rule', 'value'];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'array',
    ];

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
